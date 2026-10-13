<?php
require 'auth.php';
require 'db.php';
require_once 'includes/functions.php';
require_once 'includes/auth_layout.php';
date_default_timezone_set('Asia/Manila');

$error = "";
$notice = getFlash('auth');   // e.g. "Password changed" after a reset

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $ip    = clientIp();
    $key   = strtolower($login) . '|' . $ip;

    if (tooManyAttempts($pdo, 'login', $key, 5, 15) || tooManyAttempts($pdo, 'login_ip', $ip, 20, 15)) {
        $error = "Too many failed attempts. Please wait 15 minutes and try again.";
    } else {
        // Sign in with the username or the registered email
        $stmt = $pdo->prepare("
            SELECT u.* FROM users u
            LEFT JOIN employees e ON e.id = u.employee_id
            WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1
              AND (u.role = 'admin' OR e.status = 'active')
        ");
        $stmt->execute([$login, strtolower($login)]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password_hash'])) {
            clearAttempts($pdo, 'login', $key);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['employee_id'] = $user['employee_id'];
            header("Location: " . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'employee/home.php'));
            exit;
        }

        // Not an account yet: maybe a registration that is still being processed
        $stmt = $pdo->prepare("
            SELECT status, reject_reason, password_hash FROM registrations
            WHERE (username = ? OR email = ?) AND status IN ('unverified','pending','rejected')
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$login, strtolower($login)]);
        $reg = $stmt->fetch();

        if ($reg && password_verify($pass, $reg['password_hash'])) {
            if ($reg['status'] === 'unverified') {
                $error = "Please confirm your email first. Open the link we sent you.";
            } elseif ($reg['status'] === 'pending') {
                $error = "Your registration is waiting for the admin's approval.";
            } else {
                $error = "Your registration was not approved." . ($reg['reject_reason'] ? " Reason: " . $reg['reject_reason'] : "") . " Please talk to your manager.";
            }
        } else {
            recordAttempt($pdo, 'login', $key);
            recordAttempt($pdo, 'login_ip', $ip);
            $error = "Invalid username or password.";
        }
    }
}

authPageStart('Login', 'Welcome back', 'Sign in to your account', 'Access attendance, live salary totals and authorized management tools.');
?>
            <form method="POST" class="ls-fields">
                <?= csrfField() ?>
                <?php if ($notice) echo "<div class='msg " . htmlspecialchars($notice['type']) . "'>" . htmlspecialchars($notice['msg']) . "</div>"; ?>
                <?php if ($error) echo "<div class='msg error'>" . htmlspecialchars($error) . "</div>"; ?>

                <?php authField('username', 'Username or email', 'text', 'user-round', 'Enter your username or email', $_POST['username'] ?? '', 'required autofocus autocomplete="username"'); ?>
                <?php authField('password', 'Password', 'password', 'lock-keyhole', 'Enter your password', '', 'required autocomplete="current-password"'); ?>

                <div class="ls-links">
                    <span></span>
                    <a href="forgot.php">Forgot password?</a>
                </div>

                <button type="submit" class="btn-block">Sign in <img src="assets/images/icons/arrow-right.svg" alt=""></button>
            </form>

            <p class="ls-alt">New employee? <a href="register.php">Create an account</a></p>
<?php authPageEnd();
