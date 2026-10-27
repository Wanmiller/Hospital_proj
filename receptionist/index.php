<?php
require_once '../includes/auth.php';
requireRole('Receptionist');
header('Location: dashboard.php');
exit();
