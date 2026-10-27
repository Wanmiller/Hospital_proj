<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$path_prefix = $path_prefix ?? '';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ui.php';
$signed_in = isset($_SESSION['user_id']);
$role = $_SESSION['role'] ?? '';
$menus = [
 'Admin' => [['admin/dashboard.php','Overview','dashboard'],['admin/departments.php','Departments','department'],['admin/users.php','Team members','patients'],['admin/doctors.php','Doctors','doctor'],['admin/reports.php','Reports','report']],
 'Receptionist' => [['receptionist/dashboard.php','Day appointments','calendar'],['receptionist/patients.php','Patients','patients'],['receptionist/appointments.php','Appointments','history']],
 'Doctor' => [['doctor/dashboard.php','My day appointments','calendar'],['doctor/my_appointments.php','My Appointments','history'],['doctor/patient_history.php','Patient History','patients']],
 'Patient' => [['patient/dashboard.php','My Appointments','calendar'],['patient/book.php','Book appointment','history']],
];
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$active_page = ['patient_details.php'=>'patients.php','appointment_details.php'=>'my_appointments.php'][$current] ?? $current;
$page_label = 'Workspace';
foreach ($menus[$role] ?? [] as $item) if (basename($item[0]) === $active_page) $page_label=$item[1];
$full_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
$avatar_letter = mb_substr($full_name, 0, 1, 'UTF-8');
?>
<!doctype html>
<html lang="kk" data-bs-theme="light">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="theme-color" content="#ffffff">
 <title><?= h(t($signed_in ? $page_label : 'Sign in')) ?> &middot; <?= h(t('Clinic')) ?></title>
 <link rel="icon" href="<?= $path_prefix ?>assets/vendor/tabler-icons/medical-cross.svg" type="image/svg+xml">
 <link rel="stylesheet" href="<?= $path_prefix ?>assets/vendor/tabler/tabler.min.css">
 <link rel="stylesheet" href="<?= $path_prefix ?>assets/css/style.css">
</head>
<body class="<?= $signed_in ? 'workspace' : 'auth-page' ?>" data-required-message="<?= h(t('This field is required.')) ?>" data-email-message="<?= h(t('Invalid email format.')) ?>" data-invalid-message="<?= h(t('Invalid field value.')) ?>">
<a class="skip-link" href="#main-content"><?= h(t('Skip to content')) ?></a>
<?php if ($signed_in): ?>
<div class="page">
 <aside class="navbar navbar-vertical navbar-expand-lg" aria-label="<?= h(t('Toggle navigation')) ?>">
  <div class="container-fluid">
   <a class="navbar-brand" href="<?= $path_prefix . h($menus[$role][0][0] ?? 'index.php') ?>"><?= uiIcon('cross') ?><span><?= h(t('Clinic')) ?></span></a>
   <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="<?= h(t('Toggle navigation')) ?>"><span class="navbar-toggler-icon"></span></button>
   <div class="collapse navbar-collapse" id="navbarNav">
    <ul class="navbar-nav pt-lg-3">
    <?php foreach ($menus[$role] ?? [] as [$url,$label,$icon]): $active=basename($url)===$active_page; ?>
     <li class="nav-item<?= $active ? ' active' : '' ?>"><a class="nav-link" href="<?= $path_prefix . h($url) ?>"<?= $active ? ' aria-current="page"' : '' ?>><span class="nav-link-icon"><?= uiIcon($icon) ?></span><span class="nav-link-title"><?= h(t($label)) ?></span></a></li>
    <?php endforeach; ?>
    </ul>
    <div class="sidebar-account mt-lg-auto border-top pt-3">
     <div class="d-flex align-items-center gap-2 px-3 mb-3"><span class="avatar avatar-sm bg-blue-lt"><?= h($avatar_letter) ?></span><div class="account-name"><strong><?= h($full_name) ?></strong><small class="text-secondary d-block"><?= h(t($role)) ?></small></div></div>
     <a class="nav-link px-3 pb-3" href="<?= $path_prefix ?>logout.php"><?= uiIcon('logout') ?><span class="ms-2"><?= h(t('Sign out')) ?></span></a>
    </div>
   </div>
  </div>
 </aside>
 <div class="page-wrapper">
  <main id="main-content" class="page-body"><div class="container-fluid app-content">
<?php else: ?>
 <main id="main-content" class="page page-center"><div class="container container-tight py-4">
<?php endif; ?>
