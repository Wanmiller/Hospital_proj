<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('Receptionist');

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    die(h(t('Invalid patient ID.')));
}

// Fetch Patient Details
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    die(h(t('Patient not found.')));
}

// Fetch Appointment History
$app_query = "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.reason,
                     u.first_name AS doc_first, u.last_name AS doc_last, dept.name AS dept_name
              FROM appointments a
              JOIN doctors d ON a.doctor_id = d.id
              JOIN users u ON d.user_id = u.id
              LEFT JOIN departments dept ON d.department_id = dept.id
              WHERE a.patient_id = ?
              ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$stmt = $pdo->prepare($app_query);
$stmt->execute([$id]);
$appointments = $stmt->fetchAll();

$path_prefix = '../';
require_once '../includes/header.php';
?>

<div class="mb-4">
    <a href="patients.php" class="btn btn-sm btn-outline-secondary">&larr; <?= h(t('Back to Patients')) ?></a>
</div>

<div class="row">
    <!-- Patient Profile -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= h(t('Patient Profile')) ?></h5>
            </div>
            <div class="card-body">
                <h4 class="card-title"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></h4>
                <hr>
                <p><strong><?= h(t('DOB')) ?>:</strong> <?= h(displayDate($patient['dob'])) ?></p>
                <p><strong><?= h(t('Gender')) ?>:</strong> <?= h(t($patient['gender'])) ?></p>
                <p><strong><?= h(t('Phone')) ?>:</strong> <?= h($patient['phone']) ?></p>
                <p><strong><?= h(t('Email')) ?>:</strong> <?= h($patient['email'] ?: t('N/A')) ?></p>
                <p><strong><?= h(t('Address')) ?>:</strong><br><?= nl2br(h($patient['address'] ?: t('N/A'))) ?></p>
                <p class="text-muted small mt-4"><?= h(t('Registered')) ?>: <?= h(displayDate($patient['created_at'])) ?></p>
            </div>
        </div>
    </div>
    
    <!-- Appointment History -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><?= h(t('Appointment History')) ?></h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th><?= h(t('Date & Time')) ?></th>
                            <th><?= h(t('Doctor')) ?></th>
                            <th><?= h(t('Department')) ?></th>
                            <th><?= h(t('Reason')) ?></th>
                            <th><?= h(t('Status')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($appointments as $app): ?>
                        <tr>
                            <td>
                                <strong><?= h(displayDate($app['appointment_date'])) ?></strong><br>
                                <small class="text-muted"><?= h(displayTime($app['appointment_time'])) ?></small>
                            </td>
                            <td><?= h(t('Dr.')) ?> <?= h($app['doc_first'] . ' ' . $app['doc_last']) ?></td>
                            <td><?= h($app['dept_name'] ?? t('Unassigned')) ?></td>
                            <td><?= h($app['reason']) ?></td>
                            <td>
                                <?= statusBadge($app['status']) ?>
                                
                                
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(count($appointments) == 0): ?>
                        <tr><td colspan="5" class="text-center text-muted"><?= h(t('No appointments found.')) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
