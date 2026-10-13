<?php
session_start();
include("../includes/config.php");
include("../includes/functions.php");

// step 2 of resetting a password: the link from the email opens this page

// the token is in the address bar, so never send this page's address to other sites
// (for example in the Referer header when the page loads Bootstrap from a CDN)
header('Referrer-Policy: no-referrer');

$token = '';
if (isset($_POST['token']) && is_string($_POST['token'])) {
    $token = $_POST['token'];
} else if (isset($_GET['token']) && is_string($_GET['token'])) {
    $token = $_GET['token'];
}

// a valid link: the token exists, has not been used and has not expired
$reset = null;
if (preg_match('/^[0-9a-f]{64}$/', $token)) {
    $sql = "SELECT r.reset_id, r.user_id, u.name, u.email
            FROM password_resets r INNER JOIN users u USING (user_id)
            WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW()
            LIMIT 1";
    $reset = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [hash('sha256', $token)]));
}

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = isset($_POST['password']) && is_string($_POST['password']) ? trim($_POST['password']) : '';
    $confirm = isset($_POST['confirmPass']) && is_string($_POST['confirmPass']) ? trim($_POST['confirmPass']) : '';

    if (!csrf_valid()) {
        $_SESSION['message'] = 'Your session expired. Please try again.';
    } else if (strlen($password) < 6) {
        $_SESSION['message'] = 'password should be at least 6 characters';
    } else if ($password !== $confirm) {
        $_SESSION['message'] = 'passwords do not match';
    } else if (in_array($password, KNOWN_DEFAULT_PASSWORDS, true)) {
        $_SESSION['message'] = 'that password was published with this project; please choose another';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $sql = "UPDATE users SET password = ? WHERE user_id = ?";
            mysqli_execute_query($conn, $sql, [password_hash($password, PASSWORD_BCRYPT), $reset['user_id']]);

            // this link, and any other unused link for the same account, can no longer be used
            $sql = "UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL";
            mysqli_execute_query($conn, $sql, [$reset['user_id']]);

            mysqli_commit($conn);
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            $_SESSION['message'] = 'Could not change your password. Please try again.';
            header("Location: reset_password.php?token=" . urlencode($token));
            exit();
        }

        notify_password_changed($reset['email'], $reset['name']);
        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$reset['user_id']) {
            unset($_SESSION['default_password']);
        }

        $_SESSION['success'] = 'Your password has been changed. You can now log in with the new password.';
        header("Location: login.php");
        exit();
    }

    header("Location: reset_password.php?token=" . urlencode($token));
    exit();
}

include("../includes/header.php");
?>
<div class="row col-md-6 mx-auto mt-4 px-3">
    <h2>Choose a new password</h2>
    <?php include("../includes/alert.php"); ?>

    <?php if (!$reset): ?>
        <div class="alert alert-warning">
            This reset link is invalid, has expired, or has already been used.
        </div>
        <p><a href="forgot_password.php" class="btn btn-primary">Send me a new link</a></p>
    <?php else: ?>
        <p class="text-muted">Setting a new password for <strong><?= e($reset['email']) ?></strong>.</p>
        <form action="reset_password.php" method="POST" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3">
                <label class="form-label" for="password">New password</label>
                <input type="password" id="password" class="form-control" name="password" autofocus />
                <div class="form-text">At least 6 characters.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirmPass">Confirm new password</label>
                <input type="password" id="confirmPass" class="form-control" name="confirmPass" />
            </div>
            <button type="submit" class="btn btn-primary mb-4">Change password</button>
        </form>
    <?php endif; ?>
</div>
<?php
include("../includes/footer.php");
?>
