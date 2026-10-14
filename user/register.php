<?php
session_start();
include("../includes/header.php");

?>
<div class="container-fluid container-lg">
    <div class="row col-md-6 mx-auto mt-4">
        <h2>Create an Account</h2>
        <?php include("../includes/alert.php"); ?>
        <form action="store.php" method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" id="name" name="name"
                    value="<?php if (isset($_SESSION['regName'])) echo $_SESSION['regName']; ?>">
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="text" class="form-control" id="email" name="email"
                    value="<?php if (isset($_SESSION['regEmail'])) echo $_SESSION['regEmail']; ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password">
                <small class="text-muted">at least 6 characters</small>
            </div>

            <div class="mb-3">
                <label for="password2" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="password2" name="confirmPass">
            </div>

            <button type="submit" class="btn btn-primary" name="submit">Register</button>

            <p class="mt-3">Already have an account? <a href="login.php">Login</a></p>
        </form>
    </div>
</div>

<?php
include("../includes/footer.php");
?>
