<?php
// staff/queue.php
require_once __DIR__ . '/../bootstrap.php';
requireRole(ROLE_STAFF);

$pageTitle = 'Queue Management';

// Staff uses the same tickets page logic — reuse admin/tickets.php via include
$_GET['_staff_mode'] = true;
require_once __DIR__ . '/../admin/tickets.php';
