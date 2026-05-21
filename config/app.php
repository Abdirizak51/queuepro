<?php
// config/app.php

define('APP_NAME', 'BallanHUB');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/queuepro');
define('APP_ROOT',    dirname(__DIR__));
define('APP_TIMEZONE','Africa/Nairobi'); // UTC+3 (closest to Mogadishu)

date_default_timezone_set(APP_TIMEZONE);

// Session config (call before session_start)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_COST',     12);

// Pagination
define('PER_PAGE', 15);

// Roles
define('ROLE_ADMIN',    1);
define('ROLE_STAFF',    2);
define('ROLE_CUSTOMER', 3);
