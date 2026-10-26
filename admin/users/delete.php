<?php
session_start();
include('../../includes/config.php');
include('../../includes/adminCheck.php');

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id) {
    header('Location: index.php');
    exit;
}
if ($user_id == $_SESSION['user_id']) {
    $_SESSION['message'] = 'you cannot delete your own account';
    header('Location: index.php');
    exit;
}

// the delivery details are deleted with the user (ON DELETE CASCADE)
try {
    $stmt = mysqli_prepare($conn, 'DELETE FROM users WHERE user_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $_SESSION['success'] = 'user deleted';
} catch (mysqli_sql_exception $e) {
    // this happens when the user already has orders or reviews
    $_SESSION['message'] = 'This user has orders or reviews, so the account cannot be deleted. Deactivate it instead.';
}

header('Location: index.php');
exit;
