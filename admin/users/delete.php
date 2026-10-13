<?php
session_start();
include('../../includes/config.php');
include('../../includes/functions.php');

// User CRUD (delete): admins only
require_admin();

// only accept the form (POST), never a link
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
    header("Location: index.php");
    exit();
}
if (!$user_id || (int)$user_id === (int)$_SESSION['user_id']) {
    $_SESSION['message'] = 'You cannot delete your own account.';
    header("Location: index.php");
    exit();
}

$sql = "SELECT name, img_path, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.user_id) AS orders FROM users u WHERE user_id = ? LIMIT 1";
$user = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$user_id]));
if (!$user) {
    $_SESSION['message'] = 'User not found.';
    header("Location: index.php");
    exit();
}
if ((int)$user['orders'] > 0) {
    $_SESSION['message'] = e($user['name']) . ' has orders, so the account cannot be deleted. Deactivate it instead.';
    header("Location: edit.php?id={$user_id}");
    exit();
}

// delete the rows that point to the user first, then the user, all or nothing
mysqli_begin_transaction($conn);
try {
    mysqli_execute_query($conn, "DELETE FROM cart WHERE user_id = ?", [$user_id]);
    mysqli_execute_query($conn, "DELETE FROM review WHERE user_id = ?", [$user_id]);
    mysqli_execute_query($conn, "DELETE FROM customer WHERE user_id = ?", [$user_id]);
    mysqli_execute_query($conn, "DELETE FROM users WHERE user_id = ?", [$user_id]);
    mysqli_commit($conn);
} catch (mysqli_sql_exception $e) {
    // e.g. an admin whose name is on order status changes
    mysqli_rollback($conn);
    $_SESSION['message'] = e($user['name']) . ' is linked to store records and cannot be deleted. Deactivate the account instead.';
    header("Location: edit.php?id={$user_id}");
    exit();
}

if ($user['img_path']) {
    delete_photo_file(app_file($user['img_path']));
}

$_SESSION['success'] = e($user['name']) . ' was deleted.';
header("Location: index.php");
exit();
