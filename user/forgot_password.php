<?php
session_start();
include("../includes/config.php");
include("../includes/functions.php");

// step 1 of resetting a password: ask for the email and send a one-time link

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $_SESSION['message'] = 'Your session expired. Please try again.';
        header("Location: forgot_password.php");
        exit();
    }

    $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $_SESSION['message'] = 'please enter a valid email address';
        header("Location: forgot_password.php");
        exit();
    }

    try {
        $sql = "SELECT user_id, name, email FROM users WHERE email = ? LIMIT 1";
        $user = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$email]));

        if ($user) {
            // at most 3 links per account per hour, so nobody can flood someone's inbox
            $sql = "SELECT COUNT(*) AS recent FROM password_resets WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 HOUR";
            $recent = (int)mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$user['user_id']]))['recent'];

            if ($recent < 3) {
                // the link carries a random token; the database only keeps its hash,
                // so even someone who reads the database cannot use an unused link
                $token = bin2hex(random_bytes(32));
                $sql = "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ? MINUTE)";
                mysqli_execute_query($conn, $sql, [$user['user_id'], hash('sha256', $token), (int)RESET_LINK_MINUTES]);

                $link = APP_URL . '/user/reset_password.php?token=' . $token;
                send_password_reset_email($user['email'], $user['name'], $link);
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log('forgot_password: ' . $e->getMessage());
    }

    // the same answer whether or not the email has an account,
    // so this page cannot be used to find out who is registered
    $_SESSION['success'] = 'If an account exists for that email, we have sent a link to reset its password. The link expires in '
        . (int)RESET_LINK_MINUTES . ' minutes.';
    header("Location: login.php");
    exit();
}

include("../includes/header.php");
?>
<div class="row col-md-6 mx-auto mt-4 px-3">
    <h2>Forgot your password?</h2>
    <?php include("../includes/alert.php"); ?>
    <p class="text-muted">Enter the email you registered with and we will send you a link to choose a new password.</p>
    <form action="forgot_password.php" method="POST" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input type="text" id="email" class="form-control" name="email" autofocus />
        </div>
        <button type="submit" class="btn btn-primary mb-4">Send reset link</button>
        <div class="text-center">
            <p>Remembered it? <a href="login.php">Back to login</a></p>
        </div>
    </form>
</div>
<?php
include("../includes/footer.php");
?>
