<?php
session_start();
include('../../includes/config.php');
include('../../includes/functions.php');

// User CRUD (create): admins only
require_admin();

if (!isset($_POST['submit'])) {
    header("Location: create.php");
    exit();
}

$name = strip_tags(post_value('name'));
$email = post_value('email');
$password = post_value('password');
$confirmPass = post_value('confirmPass');
$role = post_value('role');
$photo = isset($_FILES['photo']) ? $_FILES['photo'] : null;
$old = array('name' => $name, 'email' => $email, 'role' => $role);

if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
    keep_form('user_create', array(), $old);
    header("Location: create.php");
    exit();
}

// validation
$errors = user_field_errors($conn, $name, $email);
if (strlen($password) < 6) {
    $errors['password'] = 'The password should be at least 6 characters.';
}
if ($password !== $confirmPass) {
    $errors['confirmPass'] = 'The passwords do not match.';
}
if (!in_array($role, array('customer', 'admin'), true)) {
    $errors['role'] = 'Please choose a role.';
}
$photoError = photo_upload_error($photo, false);
if ($photoError !== '') {
    $errors['photo'] = $photoError;
}

if ($errors) {
    keep_form('user_create', $errors, $old);
    header("Location: create.php");
    exit();
}

$imgPath = null;
if (is_array($photo) && $photo['error'] === UPLOAD_ERR_OK) {
    $fileName = save_photo($photo, app_file('user/images'));
    if ($fileName === '') {
        keep_form('user_create', array('photo' => 'The photo could not be saved. Please try again.'), $old);
        header("Location: create.php");
        exit();
    }
    $imgPath = 'user/images/' . $fileName;
}

try {
    $sql = "INSERT INTO users (name, email, password, img_path, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    $hash = password_hash($password, PASSWORD_BCRYPT);
    mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $hash, $imgPath, $role);
    mysqli_stmt_execute($stmt);
} catch (mysqli_sql_exception $e) {
    if ($imgPath !== null) {
        delete_photo_file(app_file($imgPath));
    }
    $_SESSION['message'] = 'Could not create the user. Please try again.';
    keep_form('user_create', array(), $old);
    header("Location: create.php");
    exit();
}

$_SESSION['success'] = e($name) . ' was added as ' . ($role === 'admin' ? 'an admin' : 'a customer') . '.';
header("Location: index.php");
exit();
