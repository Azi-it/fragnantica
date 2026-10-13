<?php
session_start();
include('./includes/config.php');
include('./includes/functions.php');

// one-time setup: create the first admin account.
// works only while there is no admin yet, and only from the computer the store runs on
// (unless ALLOW_REMOTE_SETUP is switched on in includes/settings.php).

function admin_count($conn)
{
    return (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'"))['total'];
}

if (admin_count($conn) > 0) {
    $_SESSION['message'] = 'Setup is already complete. Log in with the admin account.';
    header("Location: user/login.php");
    exit();
}

$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', array('127.0.0.1', '::1'), true);
$allowed = $isLocal || ALLOW_REMOTE_SETUP;

$name = '';
$email = '';

if ($allowed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) && is_string($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
    $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? trim($_POST['password']) : '';
    $confirm = isset($_POST['confirmPass']) && is_string($_POST['confirmPass']) ? trim($_POST['confirmPass']) : '';

    if (!csrf_valid()) {
        $_SESSION['message'] = 'Your session expired. Please try again.';
    } else if ($name === '') {
        $_SESSION['message'] = 'please enter a name';
    } else if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $_SESSION['message'] = 'please enter a valid email address';
    } else if (strlen($password) < 8) {
        $_SESSION['message'] = 'the admin password should be at least 8 characters';
    } else if ($password !== $confirm) {
        $_SESSION['message'] = 'passwords do not match';
    } else if (in_array($password, KNOWN_DEFAULT_PASSWORDS, true)) {
        $_SESSION['message'] = 'that password was published with this project; please choose another';
    } else {
        try {
            $exists = mysqli_fetch_assoc(mysqli_execute_query($conn, "SELECT user_id FROM users WHERE email = ? LIMIT 1", [$email]));
            if ($exists) {
                $_SESSION['message'] = 'that email is already registered; use a different email for the admin';
            } else {
                // one statement that only inserts while there is still no admin,
                // so two people submitting at the same moment cannot both become admin
                $sql = "INSERT INTO users (name, email, password, role, created_at)
                        SELECT ?, ?, ?, 'admin', NOW() FROM DUAL
                        WHERE NOT EXISTS (SELECT 1 FROM users WHERE role = 'admin')";
                mysqli_execute_query($conn, $sql, [$name, $email, password_hash($password, PASSWORD_BCRYPT)]);

                if (mysqli_affected_rows($conn) === 1) {
                    // log the new admin straight in
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = mysqli_insert_id($conn);
                    $_SESSION['name'] = $name;
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = 'admin';
                    $_SESSION['success'] = 'Admin account created. Welcome to your store!';
                    header("Location: admin/index.php");
                    exit();
                }

                $_SESSION['message'] = 'Setup is already complete. Log in with the admin account.';
                header("Location: user/login.php");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            $_SESSION['message'] = 'Could not create the admin account. Please try again.';
        }
    }
}

include('./includes/header.php');
?>
<div class="row col-md-6 mx-auto mt-4 px-3">
    <h2>Set up your store</h2>
    <?php include('./includes/alert.php'); ?>

    <?php if (!$allowed): ?>
        <div class="alert alert-warning">
            <strong>Setup is only allowed from the computer the store runs on.</strong><br>
            Open <code>http://localhost/Fragnantica/setup.php</code> on that computer.
            On a hosted server, set <code>ALLOW_REMOTE_SETUP</code> to <code>true</code> in
            <code>includes/settings.php</code>, run setup, then set it back to <code>false</code>.
        </div>
    <?php else: ?>
        <p class="text-muted">There is no admin account yet. Create one now. You will use it to manage products, orders and the dashboard.
            This page stops working once an admin exists.</p>
        <form action="setup.php" method="POST" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="name">Your name</label>
                <input type="text" id="name" class="form-control" name="name" value="<?= e($name) ?>" autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Admin email</label>
                <input type="text" id="email" class="form-control" name="email" value="<?= e($email) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" class="form-control" name="password">
                <div class="form-text">At least 8 characters.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirmPass">Confirm password</label>
                <input type="password" id="confirmPass" class="form-control" name="confirmPass">
            </div>
            <button type="submit" class="btn btn-primary mb-4">Create admin account</button>
        </form>
    <?php endif; ?>
</div>
<?php include('./includes/footer.php'); ?>
