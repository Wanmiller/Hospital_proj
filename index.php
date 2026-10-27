<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'Admin') header("Location: admin/dashboard.php");
    elseif ($role === 'Doctor') header("Location: doctor/dashboard.php");
    elseif ($role === 'Receptionist') header("Location: receptionist/dashboard.php");
    elseif ($role === 'Patient') header("Location: patient/dashboard.php");
    exit();
} else {
    header("Location: login.php");
    exit();
}
?>
