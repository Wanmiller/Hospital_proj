<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('Receptionist');
$scheduleRole = 'Receptionist';
require '../includes/day_schedule.php';
