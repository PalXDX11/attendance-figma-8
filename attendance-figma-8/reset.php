<?php
require 'auth.php';
require 'db.php';
require_once 'includes/functions.php';
require_once 'includes/auth_layout.php';
date_default_timezone_set('Asia/Manila');

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$token = is_string($token) ? $token : '';
$error = '';

// Find a valid, unused, unexpired reset link
$reset = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL");
    $stmt->execute([tokenHash($token)]);
    $row = $stmt->fetch();
    if ($row && strtotime($row['expires_at']) > time()) $reset = $row;
}

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s'), $reset['id']]);
        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL")->execute([$reset['user_id']]);
        $pdo->prepare("DELETE FROM auth_attempts WHERE kind IN ('login','login_ip')")->execute();   // let the person try the new password
        logAction($pdo, $reset['user_id'], 'reset_password', 'Password changed using the email reset link');
        setFlash('auth', 'success', 'Your password was changed. You can sign in now.');
        header('Location: login.php');
        exit;
    }
}

if (!$reset) {
    authPageStart('Link not valid', 'Reset link', 'This link is not valid', '');
    ?>
            <div class="msg error">The reset link is wrong, already used, or older than 1 hour.</div>
            <a class="ls-cta" href="forgot.php">Get a new link</a>
    <?php
    authPageEnd();
    exit;
}

authPageStart('Choose a new password', 'Reset password', 'Choose a new password', 'Pick a password you have not used before.');
?>
            <form method="POST" class="ls-fields">
                <?= csrfField() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <?php if ($error) echo "<div class='msg error'>" . htmlspecialchars($error) . "</div>"; ?>
                <?php authField('password', 'New password', 'password', 'lock-keyhole', 'At least 8 characters', '', 'required minlength="8" autofocus autocomplete="new-password"'); ?>
                <?php authField('confirm', 'Confirm new password', 'password', 'lock-keyhole', 'Type it again', '', 'required minlength="8" autocomplete="new-password"'); ?>
                <button type="submit" class="btn-block">Change password <img src="assets/images/icons/arrow-right.svg" alt=""></button>
            </form>
<?php authPageEnd();
