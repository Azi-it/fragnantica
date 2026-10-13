<?php
session_start();
include('../../includes/config.php');
include('../../includes/functions.php');

// activate or deactivate a user: admins only
require_admin();

// only accept the form (POST), never a link
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$status = post_value('status');
$back = post_value('return') === 'edit' && $user_id ? "edit.php?id={$user_id}" : 'index.php';

if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
} else if (!$user_id || !in_array($status, array('active', 'inactive'), true)) {
    $_SESSION['message'] = 'Invalid request.';
} else if ((int)$user_id === (int)$_SESSION['user_id']) {
    $_SESSION['message'] = 'You cannot deactivate your own account.';
} else {
    $user = mysqli_fetch_assoc(mysqli_execute_query($conn, "SELECT name FROM users WHERE user_id = ? LIMIT 1", [$user_id]));
    if (!$user) {
        $_SESSION['message'] = 'User not found.';
        $back = 'index.php';
    } else {
        mysqli_execute_query($conn, "UPDATE users SET status = ? WHERE user_id = ?", [$status, $user_id]);
        // a deactivated user is logged out on their next page view (see sync_session_user)
        $_SESSION['success'] = $status === 'inactive'
            ? e($user['name']) . ' was deactivated and can no longer log in.'
            : e($user['name']) . ' was activated and can log in again.';
    }
}

header("Location: {$back}");
exit();
