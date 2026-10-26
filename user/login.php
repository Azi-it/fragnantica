<?php
session_start();
include("../includes/config.php");

if (isset($_POST['submit'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $hasErrors = false;

    // validation (PHP only, the form has novalidate)
    if ($email === '') {
        $_SESSION['emailError'] = 'Please enter your email';
        $hasErrors = true;
    } else if (!preg_match("/^[\w.+-]+@\w+(\.\w+)+$/", $email)) {
        $_SESSION['emailError'] = 'Please enter a valid email, e.g. juan@gmail.com';
        $hasErrors = true;
    }
    if ($password === '') {
        $_SESSION['passwordError'] = 'Please enter your password';
        $hasErrors = true;
    }

    if (!$hasErrors) {
        $sql = "SELECT user_id, email, password, img_path, role, status FROM users WHERE email = ? LIMIT 1";
        $result = mysqli_execute_query($conn, $sql, [$email]);

        if ($result->num_rows === 1) {
            $row = mysqli_fetch_assoc($result);
            if (!password_verify($password, $row['password'])) {
                $_SESSION['message'] = 'wrong email or password';
            } else if ($row['status'] !== 'active') {
                $_SESSION['message'] = 'your account has been deactivated';
            } else {
                $_SESSION['email'] = $row['email'];
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['photo'] = $row['img_path'];
                if ($row['role'] === 'admin') {
                    header("Location: ../product/index.php");
                } else {
                    header("Location: ../index.php");
                }
                exit();
            }
        } else {
            $_SESSION['message'] = 'wrong email or password';
        }
    }
}

include("../includes/header.php");
?>
<div class="row col-md-6 mx-auto mt-4 px-3">
    <h2>Login</h2>
    <?php include("../includes/alert.php"); ?>
    <!-- novalidate: the browser does not check the form, PHP does -->
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" novalidate>
        <!-- Email input -->
        <div class="form-outline mb-4">
            <label class="form-label" for="email">Email address</label>
            <input type="text" id="email" class="form-control" name="email" />
            <small class="text-danger"><?php
                if (isset($_SESSION['emailError'])) {
                    echo $_SESSION['emailError'];
                    unset($_SESSION['emailError']);
                }
                ?></small>
        </div>

        <!-- Password input -->
        <div class="form-outline mb-4">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" class="form-control" name="password" />
            <small class="text-danger"><?php
                if (isset($_SESSION['passwordError'])) {
                    echo $_SESSION['passwordError'];
                    unset($_SESSION['passwordError']);
                }
                ?></small>
        </div>

        <!-- Submit button -->
        <button type="submit" class="btn btn-primary btn-block mb-4" name="submit">Sign in</button>

        <!-- Register buttons -->
        <div class="text-center">
            <p>Not a member? <a href="register.php">Register</a></p>
        </div>
    </form>
</div>
<?php
include("../includes/footer.php");
?>
