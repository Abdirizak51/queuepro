<?php
require_once __DIR__ . '/bootstrap.php';
logoutUser();
setcookie('remember_token', '', time() - 3600, '/');
flash('success', 'You have been signed out.');
redirect(APP_URL . '/login.php');
