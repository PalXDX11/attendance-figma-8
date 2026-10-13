<?php
require 'auth.php';
require 'db.php';
require_once 'includes/functions.php';
require_once 'includes/mailer.php';
require_once 'includes/auth_layout.php';
date_default_timezone_set('Asia/Manila');

$error = '';
$sent  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $ip    = clientIp();

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (tooManyAttempts($pdo, 'forgot', $ip, 5, 60) || tooManyAttempts($pdo, 'forgot_email', $email, 3, 60)) {
        $error = 'Too many requests. Please try again in an hour.';
    } else {
        recordAttempt($pdo, 'forgot', $ip);
        recordAttempt($pdo, 'forgot_email', $email);

        $stmt = $pdo->prepare("
            SELECT u.id, u.username FROM users u
            LEFT JOIN employees e ON e.id = u.employee_id
            WHERE u.email = ? AND u.is_active = 1 AND (u.role = 'admin' OR e.status = 'active')
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL")->execute([$user['id']]);
            $token = newToken();
            $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)")
                ->execute([$user['id'], $token['hash'], date('Y-m-d H:i:s', time() + 3600), date('Y-m-d H:i:s')]);

            [$html, $text] = mailTemplate('Reset your password', [
                "We got a request to reset the password for your account ({$user['username']}).",
                'Click the button to choose a new password. The link works for 1 hour and only once.',
                'If you did not ask for this, you can ignore this email. Your password will stay the same.',
            ], 'Choose a new password', appBaseUrl() . '/reset.php?token=' . $token['raw']);
            sendMail($email, 'Reset your password', $html, $text);
        }
        // Same answer whether or not the email exists, so nobody can check who has an account
        $sent = true;
    }
}

authPageStart('Forgot password', 'Account help', 'Forgot your password?', 'Enter the email you registered with and we will send you a reset link.');
if ($sent): ?>
            <div class="msg success">If that email belongs to an account, a reset link is on its way. It works for 1 hour.</div>
            <p class="sub">Check your inbox (and the spam folder). Did not get anything? Make sure you used the same email you registered with.</p>
            <a class="ls-cta" href="login.php">Back to sign in</a>
<?php else: ?>
            <form method="POST" class="ls-fields">
                <?= csrfField() ?>
                <?php if ($error) echo "<div class='msg error'>" . htmlspecialchars($error) . "</div>"; ?>
                <?php authField('email', 'Email', 'email', 'mail', 'you@example.com', $_POST['email'] ?? '', 'required autofocus autocomplete="email"'); ?>
                <button type="submit" class="btn-block">Send reset link <img src="assets/images/icons/arrow-right.svg" alt=""></button>
            </form>
            <p class="ls-alt"><a href="login.php">&larr; Back to sign in</a></p>
<?php endif;
authPageEnd();
