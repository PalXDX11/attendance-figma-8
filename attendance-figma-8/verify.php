<?php
require 'auth.php';
require 'db.php';
require_once 'includes/functions.php';
require_once 'includes/auth_layout.php';
date_default_timezone_set('Asia/Manila');

$token = $_GET['token'] ?? '';
$state = 'invalid';

if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $pdo->prepare("SELECT id, created_at FROM registrations WHERE token_hash = ? AND status = 'unverified'");
    $stmt->execute([tokenHash($token)]);
    $reg = $stmt->fetch();

    if ($reg && strtotime($reg['created_at']) > time() - 86400) {
        // The link is single use: remove the token and move the registration to the admin's queue
        $pdo->prepare("UPDATE registrations SET status = 'pending', verified_at = ?, token_hash = NULL WHERE id = ?")
            ->execute([date('Y-m-d H:i:s'), $reg['id']]);
        $state = 'ok';
    } elseif ($reg) {
        $state = 'expired';
    }
}

if ($state === 'ok') {
    authPageStart('Email confirmed', 'Email confirmed', 'Thank you!', '');
    echo '<div class="msg success">Your email is confirmed.</div>';
    echo '<p class="sub">Now the admin will review your registration. You will get an email when it is approved, then you can sign in.</p>';
} elseif ($state === 'expired') {
    authPageStart('Link expired', 'Link expired', 'This link has expired', '');
    echo '<div class="msg error">The confirmation link is older than 24 hours.</div>';
    echo '<p class="sub">Please register again to get a new link.</p>';
    echo '<a class="ls-cta" href="register.php">Register again</a>';
    authPageEnd();
    exit;
} else {
    authPageStart('Invalid link', 'Invalid link', 'This link is not valid', '');
    echo '<div class="msg info">The link was already used or is not correct. If you already confirmed your email, you only need to wait for the admin.</div>';
}
echo '<a class="ls-cta" href="login.php">Go to sign in</a>';
authPageEnd();
