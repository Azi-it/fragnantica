<?php
session_start();
include('../../includes/config.php');
include('../../includes/functions.php');

// User CRUD (update): admins only
require_admin();

if (!isset($_POST['submit'])) {
    header("Location: index.php");
    exit();
}

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$user = $user_id ? mysqli_fetch_assoc(mysqli_execute_query($conn,
    "SELECT user_id, name, img_path, role, status FROM users WHERE user_id = ? LIMIT 1", [$user_id])) : null;
if (!$user) {
    $_SESSION['message'] = 'User not found.';
    header("Location: index.php");
    exit();
}
$back = "edit.php?id={$user_id}";

if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
    header("Location: {$back}");
    exit();
}

$name = strip_tags(post_value('name'));
$email = post_value('email');
// a disabled role list is not sent, so keep the current role then
$role = isset($_POST['role']) ? post_value('role') : $user['role'];
$removePhoto = post_value('remove_photo') === '1';
$photo = isset($_FILES['photo']) ? $_FILES['photo'] : null;
$isMe = (int)$user_id === (int)$_SESSION['user_id'];

// validation
$errors = user_field_errors($conn, $name, $email, $user_id);
if (!in_array($role, array('customer', 'admin'), true)) {
    $errors['role'] = 'Please choose a role.';
} else if ($role !== $user['role']) {
    // the role of an active user only, and never your own
    if ($isMe) {
        $errors['role'] = 'You cannot change your own role.';
    } else if ($user['status'] !== 'active') {
        $errors['role'] = 'Only an active user\'s role can be changed. Activate this user first.';
    }
}
$photoError = photo_upload_error($photo, false);
if ($photoError !== '') {
    $errors['photo'] = $photoError;
}

if ($errors) {
    keep_form('user_edit_' . $user_id, $errors, array('name' => $name, 'email' => $email, 'role' => $role));
    header("Location: {$back}");
    exit();
}

// photo: a new one replaces the old one; "remove" clears it
$imgPath = $user['img_path'];
$newPhoto = false;
if (is_array($photo) && $photo['error'] === UPLOAD_ERR_OK) {
    $fileName = save_photo($photo, app_file('user/images'));
    if ($fileName === '') {
        keep_form('user_edit_' . $user_id, array('photo' => 'The photo could not be saved. Please try again.'),
            array('name' => $name, 'email' => $email, 'role' => $role));
        header("Location: {$back}");
        exit();
    }
    $imgPath = 'user/images/' . $fileName;
    $newPhoto = true;
} else if ($removePhoto) {
    $imgPath = null;
}

try {
    $sql = "UPDATE users SET name = ?, email = ?, role = ?, img_path = ? WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $role, $imgPath, $user_id);
    mysqli_stmt_execute($stmt);
} catch (mysqli_sql_exception $e) {
    if ($newPhoto) {
        delete_photo_file(app_file($imgPath));
    }
    $_SESSION['message'] = 'Could not save the changes. Please try again.';
    header("Location: {$back}");
    exit();
}

// the old photo file is not needed anymore
if ($user['img_path'] && $imgPath !== $user['img_path']) {
    delete_photo_file(app_file($user['img_path']));
}

$text = 'Changes to ' . e($name) . ' were saved.';
if ($role !== $user['role']) {
    $text .= ' Their role is now <strong>' . e(ucfirst($role)) . '</strong>.';
}
$_SESSION['success'] = $text;
header("Location: {$back}");
exit();
