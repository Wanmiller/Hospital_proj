<?php
require_once '../includes/auth.php';
requireRole('Admin');
header('Location: dashboard.php');
exit();
