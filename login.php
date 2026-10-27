<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'Admin') header("Location: admin/dashboard.php");
    elseif ($role === 'Doctor') header("Location: doctor/dashboard.php");
    elseif ($role === 'Receptionist') header("Location: receptionist/dashboard.php");
    elseif ($role === 'Patient') header("Location: patient/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = t('Invalid CSRF token. Please try again.');
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = t('Please enter both username and password.');
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['is_active']) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['role'] = $user['role'];

                    if ($user['role'] === 'Admin') header("Location: admin/dashboard.php");
                    elseif ($user['role'] === 'Doctor') header("Location: doctor/dashboard.php");
                    elseif ($user['role'] === 'Receptionist') header("Location: receptionist/dashboard.php");
                    elseif ($user['role'] === 'Patient') header("Location: patient/dashboard.php");
                    exit();
                } else {
                    $error = t('Your account is inactive.');
                }
            } else {
                $error = t('Invalid username or password.');
            }
        }
    }
}
$path_prefix = '';
require_once 'includes/header.php';
?>
<div class="auth-brand"><?= uiIcon('cross') ?><span><?= h(t('Clinic')) ?></span></div>
<div class="card card-md auth-card"><div class="card-body">
 <h1 class="h2 text-center mb-2"><?= h(t('Sign in')) ?></h1>
 <p class="text-secondary text-center auth-description"><?= h(t('Clinic system')) ?></p>
 <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h(t($error)) ?></div><?php endif; ?>
 <?php if (!empty($_SESSION['patient_registered'])): unset($_SESSION['patient_registered']); ?><div class="alert alert-success" role="status"><?= h(t('Patient registration complete.')) ?></div><?php endif; ?>
 <form method="POST" action="">
  <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
  <div class="mb-3"><label for="username" class="form-label"><?= h(t('Username')) ?></label><input type="text" name="username" id="username" class="form-control" autocomplete="username" required autofocus></div>
  <div class="mb-4"><label for="password" class="form-label"><?= h(t('Password')) ?></label><input type="password" name="password" id="password" class="form-control" autocomplete="current-password" required></div>
  <button type="submit" class="btn btn-primary w-100"><?= h(t('Sign in')) ?></button>
 </form>
 <a href="register.php" class="d-block text-center mt-3"><?= h(t('Patient registration')) ?></a>
</div></div>
<p class="text-secondary text-center small mt-3"><?= h(t('Contact your hospital administrator.')) ?></p>
<?php require_once 'includes/footer.php'; ?>
