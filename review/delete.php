<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// a customer can delete their own review; an admin can delete any review (moderation)
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}

$review_id = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
$review = $review_id ? mysqli_fetch_assoc(mysqli_execute_query($conn,
    "SELECT review_id, product_id, user_id FROM review WHERE review_id = ? LIMIT 1", [$review_id])) : null;

if (!$review) {
    $_SESSION['message'] = 'Review not found.';
    header("Location: ../index.php");
    exit();
}
$back = "../product/view.php?id=" . (int)$review['product_id'] . "#reviews";

$isOwner = (int)$review['user_id'] === (int)$_SESSION['user_id'];
if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
} else if (!$isOwner && !is_admin()) {
    $_SESSION['message'] = 'You can only delete your own review.';
} else {
    mysqli_execute_query($conn, "DELETE FROM review WHERE review_id = ?", [$review_id]);
    $_SESSION['success'] = $isOwner ? 'Your review was deleted.' : 'The review was deleted.';
}

header("Location: {$back}");
exit();
