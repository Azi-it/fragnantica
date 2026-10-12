<?php
session_start();
include("../includes/config.php");
include("../includes/functions.php");

if (isset($_POST['submit'])) {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if ($email === '' || $password === '') {
        $_SESSION['message'] = 'please enter your email and password';
    } else {
        $sql = "SELECT user_id, name, email, password, role FROM users WHERE email=? LIMIT 1";
        $result = mysqli_execute_query($conn, $sql, [$email]);

        if (mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            if (password_verify($password, $row['password'])) {
                // new session id on login, so a session id planted before login is useless
                session_regenerate_id(true);
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['name'] = $row['name'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];

                // an admin still using a password that was published with this project
                // must change it before doing anything else
                if ($row['role'] === 'admin' && in_array($password, KNOWN_DEFAULT_PASSWORDS, true)) {
                    $_SESSION['default_password'] = true;
                    $_SESSION['message'] = 'You are using the default admin password, which is published online. Change it now below.';
                    header("Location: profile.php");
                    exit();
                }

                // admins go to the dashboard, customers go to the store
                if ($row['role'] === 'admin') {
                    header("Location: ../admin/index.php");
                } else {
                    header("Location: ../index.php");
                }
                exit();
            } else {
                $_SESSION['message'] = 'wrong email or password';
            }
        } else {
            $_SESSION['message'] = 'wrong email or password';
        }
    }
}

// a freshly imported store has no admin yet: point to the setup page
$noAdmin = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'"))['total'] === 0;

include("../includes/header.php");
?>
<div class="row col-md-6 mx-auto mt-4">
    <h2>Login</h2>
    <?php include("../includes/alert.php"); ?>
    <?php if ($noAdmin): ?>
        <div class="alert alert-info">
            This store has no admin account yet. <a href="../setup.php" class="alert-link">Set up your store</a> to create one.
        </div>
    <?php endif; ?>
    <form action="login.php" method="POST">
        <!-- Email input -->
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input type="email" id="email" class="form-control" name="email" />
        </div>

        <!-- Password input -->
        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <label class="form-label" for="password">Password</label>
                <a href="forgot_password.php" class="small">Forgot password?</a>
            </div>
            <input type="password" id="password" class="form-control" name="password" />
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
