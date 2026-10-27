<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('Admin');

// Counters
$total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$total_doctors = $pdo->query("SELECT COUNT(*) FROM doctors d JOIN users u ON u.id=d.user_id WHERE u.role='Doctor' AND u.is_active=1")->fetchColumn();
$total_departments = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$total_appointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();

$today = clinicToday();
$today_appointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ?");
$today_appointments->execute([$today]);
$count_today = $today_appointments->fetchColumn();

$completed_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Completed'")->fetchColumn();
$cancelled_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Cancelled'")->fetchColumn();

// Recent Appointments (Limit 10)
$recent_query = "SELECT a.appointment_date, a.appointment_time, a.status,
                        p.first_name AS pat_first, p.last_name AS pat_last,
                        u.first_name AS doc_first, u.last_name AS doc_last,
                        dept.name AS dept_name
                 FROM appointments a
                 JOIN patients p ON a.patient_id = p.id
                 JOIN doctors d ON a.doctor_id = d.id
                 JOIN users u ON d.user_id = u.id
                 LEFT JOIN departments dept ON d.department_id = dept.id
                 ORDER BY a.created_at DESC
                 LIMIT 10";
$recent_appointments = $pdo->query($recent_query)->fetchAll();

$path_prefix = '../';
require_once '../includes/header.php';
?>

<div class="mb-3"><h2><?= h(t('Admin Dashboard')) ?></h2></div>
<div class="day-stats">
 <?php foreach ([t('Total Patients')=>$total_patients,t('Active doctors')=>$total_doctors,t('Today\'s appointments')=>$count_today] as $label=>$value): ?>
 <div class="card"><div class="card-body"><span><?= h(t($label)) ?></span><strong><?= h($value) ?></strong></div></div>
 <?php endforeach; ?>
</div>
<div class="card mb-5">
    <div class="card-header bg-light">
        <h5 class="mb-0"><?= h(t('Recent Appointments')) ?></h5>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('Patient')) ?></th>
                    <th><?= h(t('Doctor')) ?></th>
                    <th><?= h(t('Department')) ?></th>
                    <th><?= h(t('Date & Time')) ?></th>
                    <th><?= h(t('Status')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recent_appointments as $app): ?>
                <tr>
                    <td><?= h($app['pat_first'] . ' ' . $app['pat_last']) ?></td>
                    <td><?= h(t('Dr.')) ?> <?= h($app['doc_first'] . ' ' . $app['doc_last']) ?></td>
                    <td><?= h($app['dept_name'] ?? t('Unassigned')) ?></td>
                    <td>
                        <?= h(displayDate($app['appointment_date'])) ?>  
                        <?= h(displayTime($app['appointment_time'])) ?>
                    </td>
                    <td>
                        <?= statusBadge($app['status']) ?>
                        
                        
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($recent_appointments) == 0): ?>
                <tr><td colspan="5" class="text-center text-muted"><?= h(t('No recent appointments found.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
