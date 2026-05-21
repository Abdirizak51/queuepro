<?php
// index.php – entry point
require_once __DIR__ . '/bootstrap.php';
if (isLoggedIn()) {
    redirect(dashboardUrl());
}
redirect(APP_URL . '/login.php');
