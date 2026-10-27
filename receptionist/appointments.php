<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/appointments.php';

requireRole('Receptionist');

$msg = '';
$msgType = 'success';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = t('Invalid CSRF token.');
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $patient_id = $_POST['patient_id'] ?? '';
            $doctor_id = $_POST['doctor_id'] ?? '';
            $appointment_date = $_POST['appointment_date'] ?? '';
            $appointment_time = $_POST['appointment_time'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            
            if (empty($patient_id) || empty($doctor_id) || empty($appointment_date) || empty($appointment_time)) {
                $msg = t('All fields except Reason are required.');
                $msgType = 'danger';
            } else {
                try {
                    saveAppointment($pdo, $action, [
                        'id' => $id, 'patient_id' => $patient_id, 'doctor_id' => $doctor_id,
                        'date' => $appointment_date, 'time' => $appointment_time, 'reason' => $reason
                    ]);
                    $msg = $action === 'edit' ? t('Appointment updated successfully.') : t('Appointment added successfully.');
                } catch (DomainException $e) {
                    $msg = $e->getMessage();
                    $msgType = 'danger';
                } catch (PDOException $e) {
                    $msg = ($e->errorInfo[1] ?? null) == 1062
                        ? t('This doctor already has an appointment at this date and time.')
                        : t('Database error occurred.');
                    $msgType = 'danger';
                }
            }
        } elseif ($action === 'cancel') {
            $id = $_POST['id'] ?? null;
            if ($id) {
                try {
                    cancelAppointment($pdo, $id);
                    $msg = t('Appointment cancelled successfully.');
                } catch (DomainException $e) {
                    $msg = $e->getMessage();
                    $msgType = 'danger';
                } catch(PDOException $e) {
                    $msg = t('Database error occurred.');
                    $msgType = 'danger';
                }
            }
        }
    }
}

// Fetch active doctors for dropdown
$doctors_query = "SELECT d.id, u.first_name, u.last_name, dept.name AS dept_name, sp.name AS specialization 
                  FROM doctors d 
                  JOIN users u ON d.user_id = u.id 
                  LEFT JOIN specializations sp ON sp.id = d.specialization_id
                  LEFT JOIN departments dept ON d.department_id = dept.id 
                  WHERE u.is_active = 1 AND u.role = 'Doctor'
                  ORDER BY u.last_name ASC";
$doctors = $pdo->query($doctors_query)->fetchAll();

// Fetch patients for dropdown
$patients = $pdo->query("SELECT id, first_name, last_name FROM patients ORDER BY last_name ASC")->fetchAll();
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

// Filters
$filter_date = $_GET['date'] ?? '';
$filter_doctor = $_GET['doctor_id'] ?? '';
$filter_patient = $_GET['patient_id'] ?? '';
$filter_dept = $_GET['department_id'] ?? '';
$filter_status = $_GET['status'] ?? '';

$query = "SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.appointment_time, a.reason, a.status,
                 p.first_name AS pat_first, p.last_name AS pat_last,
                 u.first_name AS doc_first, u.last_name AS doc_last,
                 dept.id AS dept_id, dept.name AS dept_name, mr.id AS medical_record_id
          FROM appointments a
          JOIN patients p ON a.patient_id = p.id
          JOIN doctors d ON a.doctor_id = d.id
          JOIN users u ON d.user_id = u.id
          LEFT JOIN departments dept ON d.department_id = dept.id
          LEFT JOIN medical_records mr ON mr.appointment_id = a.id
          WHERE 1=1";
$params = [];

if ($filter_date !== '') {
    $query .= " AND a.appointment_date = ?";
    $params[] = $filter_date;
}
if ($filter_doctor !== '') {
    $query .= " AND a.doctor_id = ?";
    $params[] = $filter_doctor;
}
if ($filter_patient !== '') {
    $query .= " AND a.patient_id = ?";
    $params[] = $filter_patient;
}
if ($filter_dept !== '') {
    $query .= " AND d.department_id = ?";
    $params[] = $filter_dept;
}
if ($filter_status !== '') {
    $query .= " AND a.status = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$path_prefix = '../';
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?= h(t('Manage Appointments')) ?></h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#appModal" onclick="resetForm()"><?= h(t('Add Appointment')) ?></button>
</div>

<?php if($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
        <?= h(t($msg)) ?>
        <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="appointments.php" class="row g-3 align-items-center">
            <div class="col-md-2">
                <input type="date" name="date" class="form-control" value="<?= h($filter_date) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value=""><?= h(t('All Statuses')) ?></option>
                    <option value="Scheduled" <?= $filter_status === 'Scheduled' ? 'selected' : '' ?>><?= h(t('Scheduled')) ?></option>
                    <option value="Completed" <?= $filter_status === 'Completed' ? 'selected' : '' ?>><?= h(t('Completed')) ?></option>
                    <option value="Cancelled" <?= $filter_status === 'Cancelled' ? 'selected' : '' ?>><?= h(t('Cancelled')) ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="doctor_id" class="form-select">
                    <option value=""><?= h(t('All Doctors')) ?></option>
                    <?php foreach($doctors as $doc): ?>
                        <option value="<?= $doc['id'] ?>" <?= $filter_doctor == $doc['id'] ? 'selected' : '' ?>>
                            <?= h(t('Dr.')) ?> <?= h($doc['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="department_id" class="form-select">
                    <option value=""><?= h(t('All Departments')) ?></option>
                    <?php foreach($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $filter_dept == $dept['id'] ? 'selected' : '' ?>><?= h($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="patient_id" class="form-select">
                    <option value=""><?= h(t('All Patients')) ?></option>
                    <?php foreach($patients as $pat): ?>
                        <option value="<?= $pat['id'] ?>" <?= $filter_patient == $pat['id'] ? 'selected' : '' ?>>
                            <?= h($pat['first_name'] . ' ' . $pat['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100"><?= h(t('Filter')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('Date & Time')) ?></th>
                    <th><?= h(t('Patient')) ?></th>
                    <th><?= h(t('Doctor')) ?></th>
                    <th><?= h(t('Department')) ?></th>
                    <th><?= h(t('Reason')) ?></th>
                    <th><?= h(t('Status')) ?></th>
                    <th><?= h(t('Actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($appointments as $app): ?>
                <tr>
                    <td>
                        <strong><?= h(displayDate($app['appointment_date'])) ?></strong><br>
                        <small class="text-muted"><?= h(displayTime($app['appointment_time'])) ?></small>
                    </td>
                    <td><a href="patient_details.php?id=<?= $app['patient_id'] ?>" class="text-decoration-none"><?= h($app['pat_first'] . ' ' . $app['pat_last']) ?></a></td>
                    <td><?= h(t('Dr.')) ?> <?= h($app['doc_first'] . ' ' . $app['doc_last']) ?></td>
                    <td><?= h($app['dept_name'] ?? t('Unassigned')) ?></td>
                    <td><?= h($app['reason']) ?></td>
                    <td>
                        <?= statusBadge($app['status']) ?>
                        
                        
                    </td>
                    <td>
                        <?php if($app['status'] === 'Scheduled' && !$app['medical_record_id']): ?>
                        <button class="btn btn-sm btn-outline-primary" data-edit-handler="editApp" data-edit-values="<?= json_attr([$app['id'], $app['patient_id'], $app['doctor_id'], $app['appointment_date'], $app['appointment_time'], $app['reason']]) ?>" data-bs-toggle="modal" data-bs-target="#appModal"><?= h(t('Edit')) ?></button>
                        
                        <form method="POST" action="appointments.php" class="d-inline" data-confirm="<?= h(t('Cancel this appointment?')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="id" value="<?= h($app['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><?= h(t('Cancel')) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($appointments) == 0): ?>
                <tr><td colspan="7" class="text-center text-muted"><?= h(t('No appointments found.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="appModal" tabindex="-1" aria-labelledby="appModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="appointments.php">
          <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
          <input type="hidden" name="action" id="modal_action" value="add">
          <input type="hidden" name="id" id="app_id">
          
          <div class="modal-header">
            <h5 class="modal-title" id="appModalLabel"><?= h(t('Add Appointment')) ?></h5>
            <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Patient')) ?> *</label>
                      <select name="patient_id" id="a_patient" class="form-select" required>
                          <option value=""><?= h(t('Select Patient...')) ?></option>
                          <?php foreach($patients as $pat): ?>
                              <option value="<?= $pat['id'] ?>"><?= h($pat['first_name'] . ' ' . $pat['last_name']) ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Doctor')) ?> *</label>
                      <select name="doctor_id" id="a_doctor" class="form-select" required>
                          <option value=""><?= h(t('Select Doctor...')) ?></option>
                          <?php foreach($doctors as $doc): ?>
                              <option value="<?= $doc['id'] ?>">
                                  <?= h(t('Dr.')) ?> <?= h($doc['first_name'] . ' ' . $doc['last_name']) ?> 
                                  (<?= h($doc['dept_name'] ?? t('Unassigned')) ?> - <?= h($doc['specialization'] ?? t('Unassigned')) ?>)
                              </option>
                          <?php endforeach; ?>
                      </select>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Date')) ?> *</label>
                      <input type="date" name="appointment_date" id="a_date" class="form-control" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Time')) ?> *</label>
                      <input type="time" name="appointment_time" id="a_time" class="form-control" required>
                  </div>
              </div>
              <div class="mb-3">
                  <label class="form-label"><?= h(t('Reason / Notes')) ?></label>
                  <textarea name="reason" id="a_reason" class="form-control" rows="3"></textarea>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= h(t('Close')) ?></button>
            <button type="submit" class="btn btn-primary"><?= h(t('Save Appointment')) ?></button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetForm() {
    document.getElementById('modal_action').value = 'add';
    document.getElementById('app_id').value = '';
    document.getElementById('a_patient').value = '';
    document.getElementById('a_doctor').value = '';
    document.getElementById('a_date').value = <?= json_encode(validClinicDate($filter_date) ? $filter_date : clinicToday()) ?>;
    document.getElementById('a_time').value = '';
    document.getElementById('a_reason').value = '';
    document.getElementById('appModalLabel').innerText = <?= json_encode(t('Add Appointment')) ?>;
}
function editApp(id, pat_id, doc_id, date, time, reason) {
    document.getElementById('modal_action').value = 'edit';
    document.getElementById('app_id').value = id;
    document.getElementById('a_patient').value = pat_id;
    document.getElementById('a_doctor').value = doc_id;
    document.getElementById('a_date').value = date;
    document.getElementById('a_time').value = time;
    document.getElementById('a_reason').value = reason;
    document.getElementById('appModalLabel').innerText = <?= json_encode(t('Edit Appointment')) ?>;
}
</script>

<div id="appointment-launch" hidden data-date="<?= h(validClinicDate($filter_date) ? $filter_date : clinicToday()) ?>" data-create="<?= ($_GET['create'] ?? '') === '1' ? '1' : '0' ?>" data-edit-id="<?= h(is_scalar($_GET['edit'] ?? '') ? (string)($_GET['edit'] ?? '') : '') ?>"></div>

<?php require_once '../includes/footer.php'; ?>
