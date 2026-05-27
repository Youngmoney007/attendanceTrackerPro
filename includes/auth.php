<?php
// =============================================================
// includes/auth.php
// Session management, login, logout, and access control
// =============================================================

require_once __DIR__ . '/config.php';

// Start a secure session (called once per request)
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,          // Set true on HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 300) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// -----------------------------------------------------------
// Attempt login; returns employee row or false
// -----------------------------------------------------------
function attemptLogin(string $email, string $password): array|false {
    $db  = getDB();
    $sql = "SELECT * FROM employees WHERE email = :email AND status = 'active' LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':email' => strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user) return false;

    // Verify password against bcrypt hash
    if (!password_verify($password, $user['password_hash'])) return false;

    return $user;
}

// -----------------------------------------------------------
// Store authenticated user in session
// -----------------------------------------------------------
function loginUser(array $user): void {
    startSession();
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['emp_code']      = $user['emp_code'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['dept']          = $user['department'];
    $_SESSION['is_super_admin']= !empty($user['is_super_admin']) ? 1 : 0;
    $_SESSION['logged_in']     = true;
}

// -----------------------------------------------------------
// Check whether current user is the main super admin
// -----------------------------------------------------------
function isSuperAdmin(): bool {
    startSession();
    return !empty($_SESSION['is_super_admin']);
}

// -----------------------------------------------------------
// Destroy session on logout
// -----------------------------------------------------------
function logoutUser(): void {
    startSession();
    $_SESSION = [];
    session_destroy();
}

// -----------------------------------------------------------
// Check if any user is logged in (redirect otherwise)
// -----------------------------------------------------------
function requireLogin(): void {
    startSession();
    if (empty($_SESSION['logged_in'])) {
        header('Location: ' . APP_URL . '/index.php?msg=Please+log+in');
        exit;
    }
}

// -----------------------------------------------------------
// Check admin role (redirect if not admin)
// -----------------------------------------------------------
function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . APP_URL . '/employee/dashboard.php');
        exit;
    }
}

// -----------------------------------------------------------
// Return current logged-in user array from session
// -----------------------------------------------------------
function currentUser(): array {
    startSession();
    return [
        'id'            => $_SESSION['user_id']   ?? 0,
        'emp_code'      => $_SESSION['emp_code']  ?? '',
        'full_name'     => $_SESSION['full_name'] ?? '',
        'email'         => $_SESSION['email']     ?? '',
        'role'          => $_SESSION['role']      ?? '',
        'dept'          => $_SESSION['dept']      ?? '',
        'is_super_admin'=> $_SESSION['is_super_admin'] ?? 0,
    ];
}

// -----------------------------------------------------------
// Fetch unread notification count for current user
// -----------------------------------------------------------
function getUnreadNotifications(int $userId): int {
    $db   = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE employee_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}
