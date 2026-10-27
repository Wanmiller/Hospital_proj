<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/patients.php';
if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        $error = t('Invalid CSRF token.');
    } else {
        try {
            registerPatient($pdo, $_POST);
            $_SESSION['patient_registered'] = true;
            header('Location: login.php'); exit;
        } catch (DomainException $e) { $error = t($e->getMessage()); }
        catch (PDOException $e) { $error = t(($e->errorInfo[1] ?? 0) == 1062 ? 'Username already exists.' : 'Database error.'); }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="auth-brand"><?= uiIcon('cross') ?><span><?= h(t('Clinic')) ?></span></div>
<div class="card auth-card"><div class="card-body">
 <h1 class="h2 mb-3"><?= h(t('Patient registration')) ?></h1>
 <p class="text-secondary"><?= h(t('Patient registration explanation')) ?></p>
 <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
 <form method="post">
  <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
  <?php foreach (['first_name'=>'First Name','last_name'=>'Last Name','username'=>'Username','phone'=>'Phone'] as $key=>$label): ?>
  <div class="mb-3"><label class="form-label" for="<?= $key ?>"><?= h(t($label)) ?></label><input class="form-control" id="<?= $key ?>" name="<?= $key ?>" type="<?= $key === 'phone' ? 'tel' : 'text' ?>" maxlength="<?= $key === 'phone' ? '20' : '50' ?>" value="<?= h(patientInput($_POST,$key)) ?>" autocomplete="<?= ['first_name'=>'given-name','last_name'=>'family-name','username'=>'username','phone'=>'tel'][$key] ?>" required><?php if ($key === 'username'): ?><small class="form-hint"><?= h(t('Patient username rule')) ?></small><?php endif; ?></div>
  <?php endforeach; ?>
  <div class="mb-3"><label class="form-label" for="dob"><?= h(t('Date of Birth')) ?></label><input class="form-control" type="date" name="dob" id="dob" min="1900-01-01" max="<?= h(clinicToday()) ?>" value="<?= h(patientInput($_POST,'dob')) ?>" required></div>
  <div class="mb-3"><label class="form-label" for="gender"><?= h(t('Gender')) ?></label><select class="form-select" name="gender" id="gender" required><option value=""><?= h(t('Select gender')) ?></option><?php foreach (['Male','Female','Other'] as $gender): ?><option value="<?= $gender ?>" <?= patientInput($_POST,'gender') === $gender ? 'selected' : '' ?>><?= h(t($gender)) ?></option><?php endforeach; ?></select></div>
  <div class="mb-3"><label class="form-label" for="password"><?= h(t('Password')) ?></label><input class="form-control" type="password" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required><small class="form-hint"><?= h(t('Patient password rule')) ?></small></div>
  <div class="mb-4"><label class="form-label" for="password_confirm"><?= h(t('Confirm password')) ?></label><input class="form-control" type="password" id="password_confirm" name="password_confirm" minlength="8" maxlength="72" autocomplete="new-password" required></div>
  <button class="btn btn-primary w-100" type="submit"><?= h(t('Register')) ?></button>
 </form>
 <a class="d-block text-center mt-3" href="login.php"><?= h(t('Back to sign in')) ?></a>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
