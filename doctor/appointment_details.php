<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('Doctor');

$user_id = $_SESSION['user_id'];
$doc_stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
$doc_stmt->execute([$user_id]);
$doctor_id = $doc_stmt->fetchColumn();

if (!$doctor_id) {
    die(h(t('Doctor profile not found.')));
}

$app_id = $_GET['id'] ?? ($_POST['appointment_id'] ?? null);

if (!$app_id || !is_numeric($app_id)) {
    die(h(t('Invalid appointment ID.')));
}

$msg = '';
$msgType = 'success';

// Handle POST actions for Medical Records and Prescriptions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = t('Invalid CSRF token.');
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        // Ensure the appointment belongs to this doctor before any POST action
        $check_app = $pdo->prepare("SELECT status FROM appointments WHERE id = ? AND doctor_id = ?");
        $check_app->execute([$app_id, $doctor_id]);
        $app_status = $check_app->fetchColumn();
        
        if ($app_status === false) {
            denyAccess('Access Denied: You do not have permission to modify this record.');
        }
        
        if ($app_status === 'Cancelled') {
            denyAccess('Access Denied: Cannot modify a cancelled appointment.');
        }
        
        if ($action === 'save_medical_record') {
            $diagnosis = trim($_POST['diagnosis'] ?? '');
            $treatment = trim($_POST['treatment'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($diagnosis)) {
                $msg = t('Diagnosis is required.');
                $msgType = 'danger';
            } else {
                try {
                    $pdo->beginTransaction();
                    // Serialize completion with receptionist edits and cancellation.
                    $locked_app = $pdo->prepare("SELECT status FROM appointments WHERE id = ? AND doctor_id = ? FOR UPDATE");
                    $locked_app->execute([$app_id, $doctor_id]);
                    $app_status = $locked_app->fetchColumn();
                    if ($app_status === false || $app_status === 'Cancelled') {
                        $pdo->rollBack();
                        denyAccess('Access Denied: Appointment is no longer available for this doctor.');
                    }
                    
                    // Check if medical record already exists
                    $check_mr = $pdo->prepare("SELECT id FROM medical_records WHERE appointment_id = ?");
                    $check_mr->execute([$app_id]);
                    $mr_id = $check_mr->fetchColumn();
                    
                    if ($mr_id) {
                        // Update
                        $stmt = $pdo->prepare("UPDATE medical_records SET diagnosis=?, treatment=?, notes=? WHERE id=?");
                        $stmt->execute([$diagnosis, $treatment, $notes, $mr_id]);
                        $msg = t('Medical record updated successfully.');
                    } else {
                        // Insert
                        $stmt = $pdo->prepare("INSERT INTO medical_records (appointment_id, diagnosis, treatment, notes) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$app_id, $diagnosis, $treatment, $notes]);
                        
                        // Update appointment status if scheduled
                        if ($app_status === 'Scheduled') {
                            $upd_app = $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ?");
                            $upd_app->execute([$app_id]);
                        }
                        $msg = t('Medical record saved and appointment completed.');
                    }
                    $pdo->commit();
                } catch(PDOException $e) {
                    $pdo->rollBack();
                    $msg = t('Database error occurred.');
                    $msgType = 'danger';
                }
            }
        } elseif (in_array($action, ['add_prescription', 'edit_prescription', 'delete_prescription'])) {
            $prescription_id = $_POST['prescription_id'] ?? null;
            
            // Get medical record ID for this appointment
            $get_mr = $pdo->prepare("SELECT id FROM medical_records WHERE appointment_id = ?");
            $get_mr->execute([$app_id]);
            $mr_id = $get_mr->fetchColumn();
            
            if (!$mr_id) {
                $msg = t('Please save the medical record first before adding prescriptions.');
                $msgType = 'danger';
            } else {
                if ($action === 'delete_prescription' && $prescription_id) {
                    // Verify ownership of prescription
                    $check_rx = $pdo->prepare("SELECT mr.appointment_id FROM prescriptions rx JOIN medical_records mr ON rx.medical_record_id = mr.id WHERE rx.id = ?");
                    $check_rx->execute([$prescription_id]);
                    if ($check_rx->fetchColumn() == $app_id) {
                        $del_rx = $pdo->prepare("DELETE FROM prescriptions WHERE id = ?");
                        $del_rx->execute([$prescription_id]);
                        $msg = t('Prescription deleted.');
                    } else {
                        denyAccess('Access Denied: Invalid prescription ownership.');
                    }
                } elseif ($action === 'add_prescription' || $action === 'edit_prescription') {
                    $medicine = trim($_POST['medicine_name'] ?? '');
                    $dosage = trim($_POST['dosage'] ?? '');
                    $frequency = trim($_POST['frequency'] ?? '');
                    $duration = trim($_POST['duration'] ?? '');
                    
                    if (empty($medicine) || empty($dosage) || empty($frequency) || empty($duration)) {
                        $msg = t('All prescription fields are required.');
                        $msgType = 'danger';
                    } else {
                        if ($action === 'edit_prescription' && $prescription_id) {
                            $check_rx = $pdo->prepare("SELECT mr.appointment_id FROM prescriptions rx JOIN medical_records mr ON rx.medical_record_id = mr.id WHERE rx.id = ?");
                            $check_rx->execute([$prescription_id]);
                            if ($check_rx->fetchColumn() == $app_id) {
                                $upd_rx = $pdo->prepare("UPDATE prescriptions SET medicine_name=?, dosage=?, frequency=?, duration=? WHERE id=?");
                                $upd_rx->execute([$medicine, $dosage, $frequency, $duration, $prescription_id]);
                                $msg = t('Prescription updated.');
                            } else {
                                denyAccess('Access Denied: Invalid prescription ownership.');
                            }
                        } else {
                            $ins_rx = $pdo->prepare("INSERT INTO prescriptions (medical_record_id, medicine_name, dosage, frequency, duration) VALUES (?, ?, ?, ?, ?)");
                            $ins_rx->execute([$mr_id, $medicine, $dosage, $frequency, $duration]);
                            $msg = t('Prescription added.');
                        }
                    }
                }
            }
        }
    }
}

// Fetch Appointment and Patient Data
$app_query = "SELECT a.*, p.first_name, p.last_name, p.dob, p.gender, p.phone, p.email 
              FROM appointments a 
              JOIN patients p ON a.patient_id = p.id 
              WHERE a.id = ? AND a.doctor_id = ?";
$stmt = $pdo->prepare($app_query);
$stmt->execute([$app_id, $doctor_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    denyAccess('Access Denied or Appointment not found.');
}

// Fetch Medical Record
$mr_stmt = $pdo->prepare("SELECT * FROM medical_records WHERE appointment_id = ?");
$mr_stmt->execute([$app_id]);
$medical_record = $mr_stmt->fetch();

// Fetch Prescriptions
$prescriptions = [];
if ($medical_record) {
    $rx_stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE medical_record_id = ? ORDER BY created_at ASC");
    $rx_stmt->execute([$medical_record['id']]);
    $prescriptions = $rx_stmt->fetchAll();
}

$path_prefix = '../';
require_once '../includes/header.php';
?>

<div class="mb-4">
    <a href="my_appointments.php" class="btn btn-sm btn-outline-secondary">&larr; <?= h(t('Back to My Appointments')) ?></a>
</div>

<?php if($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
        <?= h(t($msg)) ?>
        <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Patient Info -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= h(t('Patient Profile')) ?></h5>
            </div>
            <div class="card-body">
                <h4 class="card-title"><?= h($appointment['first_name'] . ' ' . $appointment['last_name']) ?></h4>
                <hr>
                <p><strong><?= h(t('DOB')) ?>:</strong> <?= h(displayDate($appointment['dob'])) ?></p>
                <p><strong><?= h(t('Gender')) ?>:</strong> <?= h(t($appointment['gender'])) ?></p>
                <p><strong><?= h(t('Phone')) ?>:</strong> <?= h($appointment['phone']) ?></p>
                <p><strong><?= h(t('Email')) ?>:</strong> <?= h($appointment['email'] ?: t('N/A')) ?></p>
                <a href="patient_history.php?patient_id=<?= $appointment['patient_id'] ?>" class="btn btn-sm btn-outline-primary mt-2"><?= h(t('View Medical History')) ?></a>
            </div>
        </div>
    </div>
    
    <!-- Appointment Info -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><?= h(t('Appointment Details')) ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-6">
                        <p><strong><?= h(t('Date')) ?>:</strong> <?= h(displayDate($appointment['appointment_date'])) ?></p>
                        <p><strong><?= h(t('Time')) ?>:</strong> <?= h(displayTime($appointment['appointment_time'])) ?></p>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <p><strong><?= h(t('Status')) ?>:</strong> 
                            <?= statusBadge($appointment['status']) ?>
                            
                            
                        </p>
                    </div>
                </div>
                <hr>
                <p><strong><?= h(t('Reason for Visit')) ?>:</strong><br><?= nl2br(h($appointment['reason'])) ?></p>
            </div>
        </div>
    </div>
</div>

<?php if($appointment['status'] !== 'Cancelled'): ?>
<div class="row">
    <!-- Medical Record Form -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-dark">
                <h5 class="mb-0"><?= h(t('Medical Record')) ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="appointment_details.php?id=<?= $app_id ?>">
                    <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_medical_record">
                    <input type="hidden" name="appointment_id" value="<?= $app_id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label"><?= h(t('Diagnosis')) ?> *</label>
                        <textarea name="diagnosis" class="form-control" rows="2" required><?= h($medical_record['diagnosis'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= h(t('Treatment')) ?></label>
                        <textarea name="treatment" class="form-control" rows="2"><?= h($medical_record['treatment'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= h(t('Notes')) ?></label>
                        <textarea name="notes" class="form-control" rows="2"><?= h($medical_record['notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= $medical_record ? t('Update Record') : t('Complete Visit & Save Record') ?></button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Prescriptions List -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= h(t('Prescriptions')) ?></h5>
                <?php if($medical_record): ?>
                    <button class="btn btn-sm btn-light text-success" data-bs-toggle="modal" data-bs-target="#rxModal" onclick="resetRxForm()"><?= h(t('Add Prescription')) ?></button>
                <?php endif; ?>
            </div>
            <div class="card-body table-responsive">
                <?php if(!$medical_record): ?>
                    <p class="text-muted"><?= h(t('Save the medical record first to add prescriptions.')) ?></p>
                <?php else: ?>
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th><?= h(t('Medicine')) ?></th>
                                <th><?= h(t('Dosage')) ?></th>
                                <th><?= h(t('Freq')) ?></th>
                                <th><?= h(t('Duration')) ?></th>
                                <th><?= h(t('Actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($prescriptions as $rx): ?>
                            <tr>
                                <td><?= h($rx['medicine_name']) ?></td>
                                <td><?= h($rx['dosage']) ?></td>
                                <td><?= h($rx['frequency']) ?></td>
                                <td><?= h($rx['duration']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-edit-handler="editRx" data-edit-values="<?= json_attr([$rx['id'], $rx['medicine_name'], $rx['dosage'], $rx['frequency'], $rx['duration']]) ?>" data-bs-toggle="modal" data-bs-target="#rxModal"><?= h(t('Edit')) ?></button>
                                    <form method="POST" class="d-inline" data-confirm="<?= h(t('Delete this prescription?')) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_prescription">
                                        <input type="hidden" name="prescription_id" value="<?= h($rx['id']) ?>">
                                        <input type="hidden" name="appointment_id" value="<?= h($app_id) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><?= h(t('Del')) ?></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($prescriptions) == 0): ?>
                            <tr><td colspan="5" class="text-muted text-center"><?= h(t('No prescriptions added.')) ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Prescription Modal -->
<div class="modal fade" id="rxModal" tabindex="-1" aria-labelledby="rxModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="appointment_details.php?id=<?= $app_id ?>">
          <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
          <input type="hidden" name="action" id="rx_action" value="add_prescription">
          <input type="hidden" name="prescription_id" id="rx_id">
          <input type="hidden" name="appointment_id" value="<?= h($app_id) ?>">
          
          <div class="modal-header">
            <h5 class="modal-title" id="rxModalLabel"><?= h(t('Add Prescription')) ?></h5>
            <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label"><?= h(t('Medicine Name')) ?> *</label>
                  <input type="text" name="medicine_name" id="rx_med" class="form-control" required>
              </div>
              <div class="row">
                  <div class="col-md-4 mb-3">
                      <label class="form-label"><?= h(t('Dosage')) ?> *</label>
                      <input type="text" name="dosage" id="rx_dose" class="form-control" placeholder="<?= h(t('e.g. 500mg')) ?>" required>
                  </div>
                  <div class="col-md-4 mb-3">
                      <label class="form-label"><?= h(t('Frequency')) ?> *</label>
                      <input type="text" name="frequency" id="rx_freq" class="form-control" placeholder="<?= h(t('e.g. Twice a day')) ?>" required>
                  </div>
                  <div class="col-md-4 mb-3">
                      <label class="form-label"><?= h(t('Duration')) ?> *</label>
                      <input type="text" name="duration" id="rx_dur" class="form-control" placeholder="<?= h(t('e.g. 7 days')) ?>" required>
                  </div>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= h(t('Cancel')) ?></button>
            <button type="submit" class="btn btn-primary"><?= h(t('Save Prescription')) ?></button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetRxForm() {
    document.getElementById('rx_action').value = 'add_prescription';
    document.getElementById('rx_id').value = '';
    document.getElementById('rx_med').value = '';
    document.getElementById('rx_dose').value = '';
    document.getElementById('rx_freq').value = '';
    document.getElementById('rx_dur').value = '';
    document.getElementById('rxModalLabel').innerText = <?= json_encode(t('Add Prescription')) ?>;
}
function editRx(id, med, dose, freq, dur) {
    document.getElementById('rx_action').value = 'edit_prescription';
    document.getElementById('rx_id').value = id;
    document.getElementById('rx_med').value = med;
    document.getElementById('rx_dose').value = dose;
    document.getElementById('rx_freq').value = freq;
    document.getElementById('rx_dur').value = dur;
    document.getElementById('rxModalLabel').innerText = <?= json_encode(t('Edit Prescription')) ?>;
}
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
