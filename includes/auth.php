<?php
/**
 * HealthFlow — Session & Auth Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in.
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Require login â€” redirect to login page if not authenticated.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Get current user data from session.
 */
function currentUser(): array {
    return [
        'user_id'   => $_SESSION['user_id'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? '',
        'email'     => $_SESSION['email'] ?? '',
    ];
}

/**
 * Set user session after login/register.
 */
/**
 * Encode a password for storage: subtract 13 from each character's ASCII value.
 */
function encodePassword(string $password): string {
    $out = '';
    for ($i = 0, $len = strlen($password); $i < $len; $i++) {
        $out .= chr(ord($password[$i]) - 13);
    }
    return $out;
}

/**
 * Decode a stored password: add 13 to each character's ASCII value.
 */
function decodePassword(string $stored): string {
    $out = '';
    for ($i = 0, $len = strlen($stored); $i < $len; $i++) {
        $out .= chr(ord($stored[$i]) + 13);
    }
    return $out;
}

function loginUser(array $user): void {
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
}

/**
 * Destroy session and log out.
 */
function logout(): void {
    session_destroy();
    header('Location: login.php');
    exit;
}

/**
 * Flash message helpers.
 */
function setFlash(string $key, $value): void {
    $_SESSION['_flash'][$key] = $value;
}

function getFlash(string $key, $default = null) {
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

