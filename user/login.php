<?php
session_start();
include("../includes/config.php");

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
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['name'] = $row['name'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];

                // admins go to the admin page, customers go to the store
                if ($row['role'] === 'admin') {
                    header("Location: ../product/index.php");
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

include("../includes/header.php");
?>
<div class="row col-md-6 mx-auto mt-4">
    <h2>Login</h2>
    <?php include("../includes/alert.php"); ?>
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
        <!-- Email input -->
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input type="email" id="email" class="form-control" name="email" />
        </div>

        <!-- Password input -->
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
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
