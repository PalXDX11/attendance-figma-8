<?php
require 'auth.php';
require 'db.php';
require_once 'includes/functions.php';
require_once 'includes/mailer.php';
require_once 'includes/auth_layout.php';
date_default_timezone_set('Asia/Manila');

$error = '';
$done  = false;
$v = ['full_name' => '', 'email' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['full_name'] = trim($_POST['full_name'] ?? '');
    $v['email']     = strtolower(trim($_POST['email'] ?? ''));
    $v['username']  = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $ip = clientIp();

    // Forget sign-ups that were never confirmed within 24 hours
    $pdo->prepare("DELETE FROM registrations WHERE status = 'unverified' AND created_at < ?")
        ->execute([date('Y-m-d H:i:s', time() - 86400)]);

    $taken = function ($column, $value) use ($pdo) {
        $stmt = $pdo->prepare("SELECT (SELECT COUNT(*) FROM users WHERE $column = ?) + (SELECT COUNT(*) FROM registrations WHERE $column = ? AND status IN ('unverified','pending'))");
        $stmt->execute([$value, $value]);
        return (int)$stmt->fetchColumn() > 0;
    };

    if (tooManyAttempts($pdo, 'register', $ip, 5, 60)) {
        $error = 'Too many sign-up attempts. Please try again later.';
    } elseif (strlen($v['full_name']) < 2 || strlen($v['full_name']) > 100) {
        $error = 'Please enter your full name.';
    } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 190) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $v['username'])) {
        $error = 'Username must be 3-50 characters (letters, numbers, . _ -).';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } elseif ($taken('email', $v['email'])) {
        $error = 'This email is already registered. Try signing in, or use "Forgot password".';
    } elseif ($taken('username', $v['username'])) {
        $error = 'That username is already taken.';
    } else {
        recordAttempt($pdo, 'register', $ip);
        $token = newToken();
        $pdo->prepare("INSERT INTO registrations (full_name, email, username, password_hash, token_hash, status, created_at) VALUES (?, ?, ?, ?, ?, 'unverified', ?)")
            ->execute([$v['full_name'], $v['email'], $v['username'], password_hash($password, PASSWORD_DEFAULT), $token['hash'], date('Y-m-d H:i:s')]);
        $regId = (int)$pdo->lastInsertId();

        $link = appBaseUrl() . '/verify.php?token=' . $token['raw'];
        [$html, $text] = mailTemplate('Confirm your email', [
            "Hi {$v['full_name']}, thanks for registering at Oppa's Samgyeopsal.",
            'Please confirm your email address. The link works for 24 hours.',
            'After that, the admin will review your registration. You will get an email when it is approved.',
            "If you did not register, you can ignore this email.",
        ], 'Confirm my email', $link);

        if (sendMail($v['email'], 'Confirm your email', $html, $text)) {
            $done = true;
        } else {
            $pdo->prepare("DELETE FROM registrations WHERE id = ?")->execute([$regId]);
            $error = 'We could not send the confirmation email. Please check the email address and try again, or tell your manager.';
        }
    }
}

if ($done) {
    authPageStart('Check your email', 'Almost there', 'Check your email', '');
    ?>
            <div class="msg success">We sent a confirmation link to <strong><?= htmlspecialchars($v['email']) ?></strong>.</div>
            <p class="sub">1. Open the email and click <b>Confirm my email</b> (valid for 24 hours).<br>
               2. The admin reviews your registration.<br>
               3. When it is approved you will get another email, and you can sign in.</p>
            <a class="ls-cta" href="login.php">Back to sign in</a>
    <?php
    authPageEnd();
    exit;
}

authPageStart('Register', 'New employee', 'Create your account', 'Register with your email. The admin must approve your account before you can sign in.');
?>
            <form method="POST" class="ls-fields">
                <?= csrfField() ?>
                <?php if ($error) echo "<div class='msg error'>" . htmlspecialchars($error) . "</div>"; ?>

                <?php authField('full_name', 'Full name', 'text', 'user-round', 'As it appears on your work record', $v['full_name'], 'required maxlength="100" autofocus autocomplete="name"'); ?>
                <?php authField('email', 'Email', 'email', 'mail', 'you@example.com', $v['email'], 'required maxlength="190" autocomplete="email"'); ?>
                <?php authField('username', 'Username', 'text', 'user-round', 'Choose a username', $v['username'], 'required maxlength="50" pattern="[A-Za-z0-9._-]{3,50}" autocomplete="username"'); ?>
                <div>
                    <?php authField('password', 'Password', 'password', 'lock-keyhole', 'At least 8 characters', '', 'required minlength="8" autocomplete="new-password"'); ?>
                </div>
                <?php authField('confirm', 'Confirm password', 'password', 'lock-keyhole', 'Type the password again', '', 'required minlength="8" autocomplete="new-password"'); ?>

                <button type="submit" class="btn-block">Register <img src="assets/images/icons/arrow-right.svg" alt=""></button>
            </form>

            <p class="ls-alt">Already have an account? <a href="login.php">Sign in</a></p>
<?php authPageEnd();
