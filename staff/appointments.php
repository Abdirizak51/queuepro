<?php
// staff/appointments.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_STAFF);
$pageTitle = 'Appointments';
require_once __DIR__ . '/../admin/appointments.php';
