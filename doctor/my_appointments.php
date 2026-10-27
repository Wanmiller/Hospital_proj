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

// Filters
$filter_date = $_GET['date'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_patient = trim($_GET['patient_name'] ?? '');

$query = "SELECT a.id, a.appointment_date, a.appointment_time, a.reason, a.status,
                 p.first_name, p.last_name
          FROM appointments a
          JOIN patients p ON a.patient_id = p.id
          WHERE a.doctor_id = ?";
$params = [$doctor_id];

if ($filter_date !== '') {
    $query .= " AND a.appointment_date = ?";
    $params[] = $filter_date;
}
if ($filter_status !== '') {
    $query .= " AND a.status = ?";
    $params[] = $filter_status;
}
if ($filter_patient !== '') {
    $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ?)";
    $params[] = "%$filter_patient%";
    $params[] = "%$filter_patient%";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$path_prefix = '../';
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?= h(t('My Appointments')) ?></h2>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="my_appointments.php" class="row g-3 align-items-center">
            <div class="col-md-3">
                <input type="date" name="date" class="form-control" value="<?= h($filter_date) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value=""><?= h(t('All Statuses')) ?></option>
                    <option value="Scheduled" <?= $filter_status === 'Scheduled' ? 'selected' : '' ?>><?= h(t('Scheduled')) ?></option>
                    <option value="Completed" <?= $filter_status === 'Completed' ? 'selected' : '' ?>><?= h(t('Completed')) ?></option>
                    <option value="Cancelled" <?= $filter_status === 'Cancelled' ? 'selected' : '' ?>><?= h(t('Cancelled')) ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="patient_name" class="form-control" placeholder="<?= h(t('Search Patient Name')) ?>" value="<?= h($filter_patient) ?>">
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
                    <th><?= h(t('Patient Name')) ?></th>
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
                    <td><?= h($app['first_name'] . ' ' . $app['last_name']) ?></td>
                    <td><?= h($app['reason']) ?></td>
                    <td>
                        <?= statusBadge($app['status']) ?>
                        
                        
                    </td>
                    <td>
                        <a href="appointment_details.php?id=<?= h($app['id']) ?>" class="btn btn-sm btn-outline-primary"><?= h(t('View')) ?></a>
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

<?php require_once '../includes/footer.php'; ?>
