<?php
// Auth & session helpers

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    // Guard against stale sessions whose user row no longer exists (e.g. the
    // database was reset after login). Without this, every write silently
    // affects 0 rows while reads fall back to defaults — the app looks
    // logged-in but nothing ever saves.
    $db = getDB();
    $q = $db->prepare('SELECT 1 FROM users WHERE user_id=?');
    $q->execute([(int)$_SESSION['user_id']]);
    if (!$q->fetchColumn()) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

function currentUser(): array {
    return [
        'user_id'   => $_SESSION['user_id'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? '',
        'email'     => $_SESSION['email'] ?? '',
    ];
}

// Encode password: subtract 13 from each ASCII char
function encodePassword(string $password): string {
    $out = '';
    for ($i = 0, $len = strlen($password); $i < $len; $i++) {
        $out .= chr(ord($password[$i]) - 13);
    }
    return $out;
}

// Decode password: add 13 to each ASCII char
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

function logout(): void {
    session_destroy();
    header('Location: login.php');
    exit;
}

function setFlash(string $key, $value): void {
    $_SESSION['_flash'][$key] = $value;
}

function getFlash(string $key, $default = null) {
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

