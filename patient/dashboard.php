<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/patients.php';
requireRole('Patient');
try { $patient = patientAccount($pdo, $_SESSION['user_id']); }
catch (DomainException $e) { denyAccess($e->getMessage()); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(patientInput($_POST,'csrf_token'))) $error = t('Invalid CSRF token.');
    elseif (patientInput($_POST,'action') !== 'cancel' || !ctype_digit(patientInput($_POST,'id'))) $error = t('Invalid appointment action.');
    else {
        try {
            cancelPatientAppointment($pdo, $_SESSION['user_id'], $_POST['id']);
            $_SESSION['patient_notice'] = 'Appointment cancelled successfully.';
            header('Location: dashboard.php'); exit;
        } catch (DomainException $e) { $error = t($e->getMessage()); }
        catch (PDOException $e) { $error = t('Database error.'); }
    }
}
$status = patientInput($_GET,'status');
if (!in_array($status, ['','Scheduled','Completed','Cancelled'], true)) { $status=''; $error=t('Invalid filter.'); }
$sql = "SELECT a.*,u.first_name AS doc_first,u.last_name AS doc_last,dept.name AS department,mr.id AS record_id FROM appointments a JOIN doctors d ON d.id=a.doctor_id JOIN users u ON u.id=d.user_id LEFT JOIN departments dept ON dept.id=d.department_id LEFT JOIN medical_records mr ON mr.appointment_id=a.id WHERE a.patient_id=?";
$params = [$patient['id']];
if ($status !== '') { $sql .= ' AND a.status=?'; $params[]=$status; }
$stmt = $pdo->prepare($sql . ' ORDER BY a.appointment_date DESC,a.appointment_time DESC,a.id DESC');
$stmt->execute($params);
$appointments = $stmt->fetchAll();
$notice = $_SESSION['patient_notice'] ?? ''; unset($_SESSION['patient_notice']);
$path_prefix = '../'; require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4"><h2><?= h(t('My Appointments')) ?></h2><a class="btn btn-primary" href="book.php"><?= h(t('Book appointment')) ?></a></div>
<p class="text-secondary"><?= h($patient['first_name'].' '.$patient['last_name']) ?> · <?= h(displayDate($patient['dob'])) ?></p>
<?php if ($notice): ?><div class="alert alert-success" role="status"><?= h(t($notice)) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
<div class="card mb-3"><div class="card-body"><form method="get" class="row g-3 align-items-end"><div class="col-sm-6 col-md-4"><label class="form-label" for="status"><?= h(t('Status')) ?></label><select class="form-select" id="status" name="status"><option value=""><?= h(t('All Statuses')) ?></option><?php foreach (['Scheduled','Completed','Cancelled'] as $value): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= h(t($value)) ?></option><?php endforeach; ?></select></div><div class="col-auto"><button class="btn btn-secondary"><?= h(t('Filter')) ?></button></div></form></div></div>
<div class="card"><div class="table-responsive"><table class="table"><thead><tr><?php foreach (['Date','Time','Doctor','Department','Reason','Status','Actions'] as $label): ?><th><?= h(t($label)) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($appointments as $appointment): ?>
<tr><td><?= h(displayDate($appointment['appointment_date'])) ?></td><td><?= h(displayTime($appointment['appointment_time'])) ?></td><td><?= h($appointment['doc_first'].' '.$appointment['doc_last']) ?></td><td><?= h($appointment['department'] ?? t('N/A')) ?></td><td class="patient-reason"><?= h($appointment['reason']) ?></td><td><?= statusBadge($appointment['status']) ?></td><td><?php if ($appointment['status']==='Scheduled' && !$appointment['record_id']): ?><form method="post" data-confirm="<?= h(t('Cancel this appointment?')) ?>"><input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= h($appointment['id']) ?>"><button class="btn btn-sm btn-outline-danger"><?= h(t('Cancel appointment')) ?></button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$appointments): ?><tr><td colspan="7" class="empty-state"><?= h(t('No personal appointments.')) ?></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
