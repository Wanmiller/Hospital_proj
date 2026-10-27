<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/patients.php';
requireRole('Patient');
try { $patient = patientAccount($pdo, $_SESSION['user_id']); }
catch (DomainException $e) { denyAccess($e->getMessage()); }
$error = '';
$values = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$date = patientInput($values,'date') ?: clinicToday();
$doctorId = patientInput($values,'doctor_id');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(patientInput($_POST,'csrf_token'))) $error = t('Invalid CSRF token.');
    else {
        try {
            bookPatientAppointment($pdo, $_SESSION['user_id'], $_POST);
            $_SESSION['patient_notice'] = 'Appointment added successfully.';
            header('Location: dashboard.php'); exit;
        } catch (DomainException $e) { $error = t($e->getMessage()); }
        catch (PDOException $e) { $error = t(($e->errorInfo[1] ?? 0) == 1062 ? 'This doctor already has an appointment at this date and time.' : 'Database error.'); }
    }
}
if (!patientBookingDate($date)) $error = t('Patient booking date rule');
$doctors = $pdo->query("SELECT d.id,u.first_name,u.last_name,dept.name AS department,sp.name AS specialization FROM doctors d JOIN users u ON u.id=d.user_id LEFT JOIN departments dept ON dept.id=d.department_id LEFT JOIN specializations sp ON sp.id=d.specialization_id WHERE u.is_active=1 AND u.role='Doctor' ORDER BY u.last_name,u.first_name")->fetchAll();
$slots = ctype_digit($doctorId) && patientBookingDate($date) ? patientAvailableSlots($pdo,$doctorId,$date) : [];
$path_prefix = '../'; require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4"><h2><?= h(t('Book appointment')) ?></h2><a class="btn btn-secondary" href="dashboard.php"><?= h(t('My Appointments')) ?></a></div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
<div class="card mb-3"><div class="card-body">
 <p class="text-secondary"><?= h(t('Patient booking explanation')) ?></p>
 <form method="get" class="row g-3 align-items-end">
  <div class="col-md-6"><label class="form-label" for="doctor_id"><?= h(t('Doctor')) ?></label><select class="form-select" id="doctor_id" name="doctor_id" required><option value=""><?= h(t('Select doctor')) ?></option><?php foreach ($doctors as $doctor): ?><option value="<?= h($doctor['id']) ?>" <?= (string)$doctor['id'] === $doctorId ? 'selected' : '' ?>><?= h(implode(' · ',array_filter([$doctor['first_name'].' '.$doctor['last_name'],$doctor['specialization'],$doctor['department']]))) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label" for="date"><?= h(t('Date')) ?></label><input class="form-control" type="date" id="date" name="date" value="<?= h($date) ?>" min="<?= h(clinicToday()) ?>" max="<?= h((new DateTimeImmutable(clinicToday()))->modify('+90 days')->format('Y-m-d')) ?>" required></div>
  <div class="col-md-3"><button class="btn btn-primary w-100"><?= h(t('Find available times')) ?></button></div>
 </form>
</div></div>
<?php if ($doctorId !== '' && patientBookingDate($date)): ?>
<div class="card"><div class="card-body"><h3 class="card-title"><?= h(t('Available times')) ?> · <?= h(displayDate($date)) ?></h3>
 <?php if (!$slots): ?><p class="text-secondary mb-0"><?= h(t('No available times.')) ?></p>
 <?php else: ?><form method="post">
  <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>"><input type="hidden" name="doctor_id" value="<?= h($doctorId) ?>"><input type="hidden" name="date" value="<?= h($date) ?>">
  <fieldset class="mb-3"><legend class="form-label"><?= h(t('Select time')) ?></legend><div class="patient-slots"><?php foreach ($slots as $time): ?><label class="form-selectgroup-item"><input class="form-selectgroup-input" type="radio" name="time" value="<?= h($time) ?>" <?= patientInput($_POST,'time') === $time ? 'checked' : '' ?> required><span class="form-selectgroup-label"><?= h($time) ?></span></label><?php endforeach; ?></div></fieldset>
  <div class="mb-3"><label class="form-label" for="reason"><?= h(t('Reason')) ?> <span class="text-secondary">(<?= h(t('Optional')) ?>)</span></label><textarea class="form-control" id="reason" name="reason" rows="3" maxlength="2000"><?= h(patientInput($_POST,'reason')) ?></textarea></div>
  <button class="btn btn-primary" type="submit"><?= h(t('Confirm booking')) ?></button>
 </form><?php endif; ?>
</div></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
