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

$patient_id = $_GET['patient_id'] ?? null;
if ($patient_id === null) {
    $list_stmt = $pdo->prepare("SELECT DISTINCT p.id, p.first_name, p.last_name, p.dob FROM patients p JOIN appointments a ON a.patient_id = p.id WHERE a.doctor_id = ? AND a.status != 'Cancelled' ORDER BY p.last_name, p.first_name");
    $list_stmt->execute([$doctor_id]);
    $patients = $list_stmt->fetchAll();
    $path_prefix = '../';
    require_once '../includes/header.php';
    ?>
    <h2 class="mb-4"><?= h(t('Patient History')) ?></h2>
    <div class="card"><div class="card-body">
        <?php if (!$patients): ?>
            <p class="text-muted mb-0"><?= h(t('No patients found.')) ?></p>
        <?php else: ?>
            <ul class="list-group list-group-flush">
            <?php foreach ($patients as $patient): ?>
                <li class="list-group-item">
                    <a href="patient_history.php?patient_id=<?= h($patient['id']) ?>"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></a>
                    <span class="text-muted ms-2"><?= h(t('DOB')) ?>: <?= h($patient['dob']) ?></span>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div></div>
    <?php
    require_once '../includes/footer.php';
    exit();
}
if (!$patient_id || !is_numeric($patient_id)) {
    die(h(t('Invalid patient ID.')));
}

// Verify this patient has had an appointment with THIS doctor
$check_rel = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND doctor_id = ? AND status != 'Cancelled'");
$check_rel->execute([$patient_id, $doctor_id]);
if ($check_rel->fetchColumn() == 0) {
    denyAccess('Access Denied: You do not have permission to view this patient\'s history.');
}

// Fetch Patient Info
$p_stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$p_stmt->execute([$patient_id]);
$patient = $p_stmt->fetch();

if (!$patient) {
    die(h(t('Patient not found.')));
}

// Fetch Medical History (Appointments + Medical Records + Prescriptions)
// We will fetch appointments and medical records first, then fetch all prescriptions for those records
$history_query = "SELECT a.id AS app_id, a.appointment_date, a.appointment_time, a.reason,
                         mr.id AS mr_id, mr.diagnosis, mr.treatment, mr.notes,
                         u.first_name AS doc_first, u.last_name AS doc_last, dept.name AS dept_name
                  FROM appointments a
                  JOIN doctors d ON a.doctor_id = d.id
                  JOIN users u ON d.user_id = u.id
                  LEFT JOIN departments dept ON d.department_id = dept.id
                  LEFT JOIN medical_records mr ON a.id = mr.appointment_id
                  WHERE a.patient_id = ? AND a.status = 'Completed'
                  ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$h_stmt = $pdo->prepare($history_query);
$h_stmt->execute([$patient_id]);
$records = $h_stmt->fetchAll();

// Fetch all prescriptions for the patient
$rx_query = "SELECT rx.* FROM prescriptions rx 
             JOIN medical_records mr ON rx.medical_record_id = mr.id 
             JOIN appointments a ON mr.appointment_id = a.id 
             WHERE a.patient_id = ?";
$rx_stmt = $pdo->prepare($rx_query);
$rx_stmt->execute([$patient_id]);
$all_prescriptions = $rx_stmt->fetchAll();

// Group prescriptions by medical_record_id
$rx_grouped = [];
foreach ($all_prescriptions as $rx) {
    $rx_grouped[$rx['medical_record_id']][] = $rx;
}

$path_prefix = '../';
require_once '../includes/header.php';
?>

<div class="mb-4">
    <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">&larr; <?= h(t('Back')) ?></a>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><?= h(t('Patient Medical History')) ?>: <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong><?= h(t('DOB')) ?>:</strong> <?= h(displayDate($patient['dob'])) ?></div>
            <div class="col-md-3"><strong><?= h(t('Gender')) ?>:</strong> <?= h(t($patient['gender'])) ?></div>
            <div class="col-md-3"><strong><?= h(t('Phone')) ?>:</strong> <?= h($patient['phone']) ?></div>
            <div class="col-md-3"><strong><?= h(t('Email')) ?>:</strong> <?= h($patient['email'] ?: t('N/A')) ?></div>
        </div>
    </div>
</div>

<h4 class="mb-3"><?= h(t('Chronological Records')) ?></h4>

<div class="timeline">
    <?php if(count($records) == 0): ?>
        <p class="text-muted"><?= h(t('No completed medical records found for this patient.')) ?></p>
    <?php else: ?>
        <?php foreach($records as $rec): ?>
        <div class="card mb-4 border-start border-primary border-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <?= h(displayDate($rec['appointment_date'])) ?>  <?= h(displayTime($rec['appointment_time'])) ?>
                </h6>
                <span class="text-muted small"><?= h(t('Attending')) ?>:  <?= h($rec['doc_first'] . ' ' . $rec['doc_last']) ?> (<?= h($rec['dept_name']) ?>)</span>
            </div>
            <div class="card-body">
                <p><strong><?= h(t('Reason for Visit')) ?>:</strong> <?= h($rec['reason']) ?></p>
                
                <?php if($rec['mr_id']): ?>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?= h(t('Diagnosis')) ?>:</strong><br><?= nl2br(h($rec['diagnosis'])) ?></p>
                            <p><strong><?= h(t('Treatment')) ?>:</strong><br><?= nl2br(h($rec['treatment'])) ?></p>
                            <p><strong><?= h(t('Notes')) ?>:</strong><br><?= nl2br(h($rec['notes'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <strong><?= h(t('Prescriptions')) ?>:</strong>
                            <?php if(isset($rx_grouped[$rec['mr_id']])): ?>
                                <ul class="list-group list-group-flush mt-2">
                                    <?php foreach($rx_grouped[$rec['mr_id']] as $rx): ?>
                                    <li class="list-group-item bg-light p-2 mb-1 rounded">
                                        <strong><?= h($rx['medicine_name']) ?></strong> - <?= h($rx['dosage']) ?><br>
                                        <small><?= h($rx['frequency']) ?>  /  <?= h($rx['duration']) ?></small>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mt-2"><?= h(t('No prescriptions recorded.')) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted fst-italic"><?= h(t('Medical record details were not entered for this visit.')) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
