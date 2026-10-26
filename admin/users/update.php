<?php
session_start();
include('../../includes/config.php');
include('../../includes/adminCheck.php');

$user_id = (int)$_POST['user_id'];
$name = trim(strip_tags($_POST['name']));
$email = trim($_POST['email']);
$back = "edit.php?id={$user_id}";

$sql = "SELECT role, status, img_path FROM users WHERE user_id = ? LIMIT 1";
$user = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$user_id]));
if (!$user) {
    $_SESSION['message'] = 'user not found';
    header("Location: index.php");
    exit();
}

// a disabled role list is not sent, so keep the current role then
$role = $user['role'];
if (isset($_POST['role'])) {
    $role = $_POST['role'];
}

//validation
if ($name === '') {
    $_SESSION['message'] = 'please enter the name';
    header("Location: {$back}");
    exit();
} else if (!preg_match("/^[\w.+-]+@\w+(\.\w+)+$/", $email)) {
    $_SESSION['message'] = 'email invalid format';
    header("Location: {$back}");
    exit();
} else if ($role !== 'customer' && $role !== 'admin') {
    $_SESSION['message'] = 'please choose a role';
    header("Location: {$back}");
    exit();
} else if ($role !== $user['role'] && $user['status'] !== 'active') {
    $_SESSION['message'] = 'only an active user\'s role can be changed';
    header("Location: {$back}");
    exit();
} else if ($role !== $user['role'] && $user_id == $_SESSION['user_id']) {
    $_SESSION['message'] = 'you cannot change your own role';
    header("Location: {$back}");
    exit();
}

$result = mysqli_execute_query($conn, "SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1", [$email, $user_id]);
if (mysqli_num_rows($result) > 0) {
    $_SESSION['message'] = 'this email is already used by another user';
    header("Location: {$back}");
    exit();
}

// a new photo is optional
$target = $user['img_path'];
if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['photo']['type'] == "image/jpeg" || $_FILES['photo']['type'] == "image/jpg" || $_FILES['photo']['type'] == "image/png") {
        $source = $_FILES['photo']['tmp_name'];
        $target = 'images/' . time() . '_' . $_FILES['photo']['name'];
        move_uploaded_file($source, '../../user/' . $target) or die("Couldn't copy");
    } else {
        $_SESSION['message'] = 'wrong file type, please use JPG or PNG';
        header("Location: {$back}");
        exit();
    }
}

try {
    $sql = "UPDATE users SET name = ?, email = ?, role = ?, img_path = ? WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $role, $target, $user_id);
    mysqli_stmt_execute($stmt);

    // the admin edited their own account
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['email'] = $email;
        $_SESSION['photo'] = $target;
    }
    $_SESSION['success'] = 'user updated';
    header("Location: index.php");
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = 'could not update the user';
    header("Location: {$back}");
}
