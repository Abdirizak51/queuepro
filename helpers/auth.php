<?php
// helpers/auth.php

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Require authentication – redirect if not logged in
 */
function requireAuth(string $redirect = '/queuepro/login.php'): void {
    if (!isLoggedIn()) {
        flash('error', 'Please log in to continue.');
        redirect($redirect);
    }
}

/**
 * Require a specific role
 */
function requireRole(int|array $roles): void {
    requireAuth();
    $userRole = (int)($_SESSION['role_id'] ?? 0);
    $allowed  = is_array($roles) ? $roles : [$roles];
    if (!in_array($userRole, $allowed, true)) {
        flash('error', 'You do not have permission to access this page.');
        redirect(APP_URL . '/dashboard.php');
    }
}

/**
 * Check role without redirecting
 */
function hasRole(int|array $roles): bool {
    $userRole = (int)($_SESSION['role_id'] ?? 0);
    $allowed  = is_array($roles) ? $roles : [$roles];
    return in_array($userRole, $allowed, true);
}

/**
 * Log in a user – set session
 */
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role_id']   = $user['role_id'];
    $_SESSION['branch_id'] = $user['branch_id'];
    $_SESSION['avatar']    = $user['avatar'] ?? null;

    // Update last_login
    db()->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
    logActivity('login', 'User logged in', $user['id']);
}

/**
 * Log out
 */
function logoutUser(): void {
    logActivity('logout', 'User logged out');
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

/**
 * Get current authenticated user as array
 */
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare(
            "SELECT u.*, r.name AS role_name, r.display_name AS role_display,
                    b.name AS branch_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN branches b ON b.id = u.branch_id
             WHERE u.id = ?"
        );
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

/**
 * Dashboard redirect based on role
 */
function dashboardUrl(): string {
    return match((int)($_SESSION['role_id'] ?? 3)) {
        ROLE_ADMIN => APP_URL . '/admin/dashboard.php',
        ROLE_STAFF => APP_URL . '/staff/dashboard.php',
        default    => APP_URL . '/customer/dashboard.php',
    };
}
