<?php
session_start();
include("../includes/config.php");

// this page only handles the register form
if (!isset($_POST['submit'])) {
    header("Location: register.php");
    exit();
}

$name = trim(strip_tags($_POST['name']));
$email = trim($_POST['email']);
$password = trim($_POST['password']);
$confirmPass = trim($_POST['confirmPass']);

// keep the typed name and email so the user does not have to type them again
$_SESSION['regName'] = $name;
$_SESSION['regEmail'] = $email;

//validation
if ($name === '') {
    $_SESSION['message'] = 'please enter your name';
    header("Location: register.php");
    exit();
} else if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $_SESSION['message'] = 'email invalid format';
    header("Location: register.php");
    exit();
} else if (strlen($password) < 6) {
    $_SESSION['message'] = 'password should be at least 6 characters';
    header("Location: register.php");
    exit();
} else if ($password !== $confirmPass) {
    $_SESSION['message'] = 'passwords do not match';
    header("Location: register.php");
    exit();
}

// check if the email is already registered
$sql = "SELECT user_id FROM users WHERE email=? LIMIT 1";
$result = mysqli_execute_query($conn, $sql, [$email]);
if (mysqli_num_rows($result) > 0) {
    $_SESSION['message'] = 'email is already registered';
    header("Location: register.php");
    exit();
}

try {
    $password = password_hash($password, PASSWORD_BCRYPT);

    $sql = "INSERT INTO users (name, email, password, role, created_at) VALUES(?, ?, ?, 'customer', now())";
    $stmt1 = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt1, 'sss', $name, $email, $password);
    mysqli_stmt_execute($stmt1);

    unset($_SESSION['regName'], $_SESSION['regEmail']);
    $_SESSION['success'] = 'registration successful, you can now log in';
    header("Location: login.php");
    exit();
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = 'registration failed: ' . $e->getMessage();
    header("Location: register.php");
    exit();
}
