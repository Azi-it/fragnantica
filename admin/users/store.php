<?php
session_start();
include('../../includes/config.php');
include('../../includes/adminCheck.php');

$name = trim(strip_tags($_POST['name']));
$email = trim($_POST['email']);
$password = trim($_POST['password']);
$role = $_POST['role'];
$_SESSION['regName'] = $name;
$_SESSION['regEmail'] = $email;

//validation
if ($name === '') {
    $_SESSION['message'] = 'please enter the name';
    header("Location: create.php");
    exit();
} else if (!preg_match("/^[\w.+-]+@\w+(\.\w+)+$/", $email)) {
    $_SESSION['message'] = 'email invalid format';
    header("Location: create.php");
    exit();
} else if (!(strlen($password) >= 6)) {
    $_SESSION['message'] = 'password should be at least 6 characters';
    header("Location: create.php");
    exit();
} else if ($role !== 'customer' && $role !== 'admin') {
    $_SESSION['message'] = 'please choose a role';
    header("Location: create.php");
    exit();
}

$result = mysqli_execute_query($conn, "SELECT user_id FROM users WHERE email = ? LIMIT 1", [$email]);
if (mysqli_num_rows($result) > 0) {
    $_SESSION['message'] = 'this email is already registered';
    header("Location: create.php");
    exit();
}

// the photo is optional
$target = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['photo']['type'] == "image/jpeg" || $_FILES['photo']['type'] == "image/jpg" || $_FILES['photo']['type'] == "image/png") {
        $source = $_FILES['photo']['tmp_name'];
        $target = 'images/' . time() . '_' . $_FILES['photo']['name'];
        // user photos are kept in the user/images folder
        move_uploaded_file($source, '../../user/' . $target) or die("Couldn't copy");
    } else {
        $_SESSION['message'] = 'wrong file type, please use JPG or PNG';
        header("Location: create.php");
        exit();
    }
}

try {
    $password = password_hash($password, PASSWORD_BCRYPT);

    $sql = "INSERT INTO users (name, email, password, img_path, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())";
    $stmt1 = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt1, 'sssss', $name, $email, $password, $target, $role);
    mysqli_stmt_execute($stmt1);

    unset($_SESSION['regName'], $_SESSION['regEmail']);
    $_SESSION['success'] = 'user added';
    header("Location: index.php");
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = 'could not add the user';
    header("Location: create.php");
}
