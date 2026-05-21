<?php
// helpers/csrf.php

/**
 * Generate or retrieve CSRF token
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden CSRF input
 */
function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

/**
 * Verify CSRF token from POST
 */
function verifyCsrf(): void {
    $token    = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!hash_equals($expected, $token)) {
        http_response_code(403);
        die('Invalid security token. Please go back and try again.');
    }
}
