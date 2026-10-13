<?php
session_start();
include("../includes/config.php");
include("../includes/functions.php");

// this page only handles the register form
if (!isset($_POST['submit'])) {
    header("Location: register.php");
    exit();
}

$name = strip_tags(post_value('name'));
$email = post_value('email');
$password = post_value('password');
$confirmPass = post_value('confirmPass');
$photo = isset($_FILES['photo']) ? $_FILES['photo'] : null;
$errors = array();

//validation
if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
}
if ($name === '') {
    $errors['name'] = 'Please enter your name.';
} else if (mb_strlen($name) > 100) {
    $errors['name'] = 'The name should be 100 characters or less.';
}
if ($email === '') {
    $errors['email'] = 'Please enter your email address.';
} else if (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors['email'] = 'Please enter a valid email address.';
} else if (mysqli_num_rows(mysqli_execute_query($conn, "SELECT user_id FROM users WHERE email=? LIMIT 1", [$email])) > 0) {
    $errors['email'] = 'This email is already registered.';
}
if (strlen($password) < 6) {
    $errors['password'] = 'The password should be at least 6 characters.';
}
if ($password !== $confirmPass) {
    $errors['confirmPass'] = 'The passwords do not match.';
}
// the photo is optional, but when one is chosen it must be a JPG or PNG
$photoError = photo_upload_error($photo, false);
if ($photoError !== '') {
    $errors['photo'] = $photoError;
}

if ($errors || isset($_SESSION['message'])) {
    // keep the typed name and email so the user does not have to type them again
    keep_form('register', $errors, array('name' => $name, 'email' => $email));
    header("Location: register.php");
    exit();
}

// save the photo in user/images with a new random name
$imgPath = null;
if (is_array($photo) && $photo['error'] === UPLOAD_ERR_OK) {
    $fileName = save_photo($photo, __DIR__ . '/images');
    if ($fileName === '') {
        keep_form('register', array('photo' => 'The photo could not be saved. Please try again.'), array('name' => $name, 'email' => $email));
        header("Location: register.php");
        exit();
    }
    $imgPath = 'user/images/' . $fileName;
}

try {
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $sql = "INSERT INTO users (name, email, password, img_path, role, status, created_at) VALUES (?, ?, ?, ?, 'customer', 'active', NOW())";
    $stmt1 = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt1, 'ssss', $name, $email, $hash, $imgPath);
    mysqli_stmt_execute($stmt1);

    $_SESSION['success'] = 'registration successful, you can now log in';
    header("Location: login.php");
    exit();
} catch (mysqli_sql_exception $e) {
    if ($imgPath !== null) {
        delete_photo_file(__DIR__ . '/../' . $imgPath);
    }
    $_SESSION['message'] = 'Registration failed. Please try again.';
    keep_form('register', array(), array('name' => $name, 'email' => $email));
    header("Location: register.php");
    exit();
}
