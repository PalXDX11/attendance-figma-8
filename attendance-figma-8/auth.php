<?php
// Safer session cookie: not readable by JavaScript, not sent from other sites
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// Every POST form must carry the CSRF token (see includes/security.php)
require_once __DIR__ . '/includes/security.php';
requireCsrf();

// Relative URL to a file in the app root, so the app works from any folder name.
function appUrl($path) {
    $rel = str_replace('\\', '/', substr(realpath(dirname($_SERVER['SCRIPT_FILENAME'])), strlen(__DIR__)));
    $rel = trim($rel, '/');
    return str_repeat('../', $rel === '' ? 0 : substr_count($rel, '/') + 1) . $path;
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . appUrl("login.php"));
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        exit("Forbidden");
    }
}

// Employee-only pages. Admins are sent to their dashboard instead.
function requireEmployee() {
    requireLogin();
    if ($_SESSION['role'] === 'admin') {
        header("Location: " . appUrl("admin/dashboard.php"));
        exit;
    }
    if (empty($_SESSION['employee_id'])) {
        http_response_code(403);
        exit("This account is not linked to an employee.");
    }
}
