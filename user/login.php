<?php
session_start();
include("../includes/config.php");
include("../includes/functions.php");

// already logged in: nothing to do here
if (isset($_SESSION['user_id'])) {
    header('Location: ' . after_login_url($_SESSION['role']));
    exit();
}

$errors = array();
$email = '';

if (isset($_POST['submit'])) {
    $email = post_value('email');
    $password = post_value('password');

    // validation (PHP only, the form has "novalidate")
    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } else if (strlen($email) > 100) {
        $errors['email'] = 'The email address is too long.';
    } else if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Please enter a valid email address, e.g. juan@example.com.';
    }
    if ($password === '') {
        $errors['password'] = 'Please enter your password.';
    }

    if (!$errors) {
        $sql = "SELECT user_id, name, email, password, img_path, role, status FROM users WHERE email=? LIMIT 1";
        $result = mysqli_execute_query($conn, $sql, [$email]);
        $row = mysqli_fetch_assoc($result);

        if (!$row || !password_verify($password, $row['password'])) {
            // the same message for both, so nobody can find out which emails are registered
            $_SESSION['message'] = 'Wrong email or password.';
        } else if ($row['status'] !== 'active') {
            $_SESSION['message'] = 'This account has been deactivated. Please contact the store if you think this is a mistake.';
        } else {
            // new session id on login, so a session id planted before login is useless
            $afterLogin = isset($_SESSION['after_login']) ? $_SESSION['after_login'] : null;
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['name'] = $row['name'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['photo'] = $row['img_path'];
            if ($afterLogin !== null) {
                $_SESSION['after_login'] = $afterLogin;
            }

            // an admin still using a password that was published with this project
            // must change it before doing anything else
            if ($row['role'] === 'admin' && in_array($password, KNOWN_DEFAULT_PASSWORDS, true)) {
                $_SESSION['default_password'] = true;
                $_SESSION['message'] = 'You are using the default admin password, which is published online. Change it now below.';
                header("Location: profile.php");
                exit();
            }

            $_SESSION['success'] = 'Welcome back, ' . e($row['name']) . '!';
            // back to the page they wanted, otherwise admins go to the dashboard and customers to the store
            header('Location: ' . after_login_url($row['role']));
            exit();
        }
    }
}

// a freshly imported store has no admin yet: point to the setup page
$noAdmin = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'"))['total'] === 0;

include("../includes/header.php");
?>
<div class="row col-md-6 mx-auto mt-4 px-3">
    <h2>Login</h2>
    <?php include("../includes/alert.php"); ?>
    <?php if ($noAdmin): ?>
        <div class="alert alert-info">
            This store has no admin account yet. <a href="../setup.php" class="alert-link">Set up your store</a> to create one.
        </div>
    <?php endif; ?>
    <form action="login.php" method="POST" novalidate>
        <!-- Email input -->
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input type="text" id="email" class="form-control<?= invalid_class($errors, 'email') ?>" name="email"
                value="<?= e($email) ?>" autocomplete="email" />
            <?= field_error($errors, 'email') ?>
        </div>

        <!-- Password input -->
        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <label class="form-label" for="password">Password</label>
                <a href="forgot_password.php" class="small">Forgot password?</a>
            </div>
            <input type="password" id="password" class="form-control<?= invalid_class($errors, 'password') ?>" name="password"
                autocomplete="current-password" />
            <?= field_error($errors, 'password') ?>
        </div>

        <!-- Submit button -->
        <button type="submit" class="btn btn-primary mb-4" name="submit">Sign in</button>

        <!-- Register link -->
        <div class="text-center">
            <p>Not a member? <a href="register.php">Register</a></p>
        </div>
    </form>
</div>
<?php
include("../includes/footer.php");
?>
