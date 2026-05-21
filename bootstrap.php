<?php
// bootstrap.php  –  included by every entry-point

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/csrf.php';

// Start session once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
