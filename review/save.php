<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// write a new review, or update your own review of the same product
require_customer();

if (!isset($_POST['submit'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$product = $product_id ? mysqli_fetch_assoc(mysqli_execute_query($conn, "SELECT name FROM product WHERE product_id = ? LIMIT 1", [$product_id])) : null;
if (!$product) {
    $_SESSION['message'] = 'product not found';
    header("Location: ../index.php");
    exit();
}
$back = "../product/view.php?id={$product_id}#reviews";

$ratingInput = post_value('rating');
$comment = strip_tags(post_value('comment'));
// several blank lines in a row become one
$comment = preg_replace("/(\r?\n){3,}/", "\n\n", $comment);

if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
    keep_form('review_' . $product_id, array(), array('rating' => $ratingInput, 'comment' => $comment));
    header("Location: {$back}");
    exit();
}

// only a customer who received this product (a completed order) may review it
if (!can_review_product($conn, $user_id, $product_id)) {
    $_SESSION['message'] = 'Only customers who bought this product can review it, once their order is completed.';
    header("Location: {$back}");
    exit();
}

// validation
$errors = array();
$rating = filter_var($ratingInput, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 5)));
if ($rating === false) {
    $errors['rating'] = 'Please choose from 1 to 5 stars.';
}
if ($comment === '') {
    $errors['comment'] = 'Please write a few words about the product.';
} else if (mb_strlen($comment) < 10) {
    $errors['comment'] = 'Your review is too short. Please write at least 10 characters.';
} else if (mb_strlen($comment) > 1000) {
    $errors['comment'] = 'Your review is too long (' . mb_strlen($comment) . ' characters). The limit is 1000.';
}

if ($errors) {
    keep_form('review_' . $product_id, $errors, array('rating' => $ratingInput, 'comment' => $comment));
    header("Location: {$back}");
    exit();
}

// hide bad words before saving (regular expression in includes/profanity.php)
$masked = mask_bad_words($comment);
$filtered = $masked !== $comment;

try {
    $existing = mysqli_fetch_assoc(mysqli_execute_query($conn,
        "SELECT review_id FROM review WHERE product_id = ? AND user_id = ? LIMIT 1", [$product_id, $user_id]));

    if ($existing) {
        $sql = "UPDATE review SET rating = ?, comment = ?, updated_at = NOW() WHERE review_id = ? AND user_id = ?";
        mysqli_execute_query($conn, $sql, [$rating, $masked, $existing['review_id'], $user_id]);
        $text = 'Your review was updated.';
    } else {
        $sql = "INSERT INTO review (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())";
        mysqli_execute_query($conn, $sql, [$product_id, $user_id, $rating, $masked]);
        $text = 'Thank you! Your review of ' . e($product['name']) . ' was posted.';
    }
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = 'Could not save your review. Please try again.';
    keep_form('review_' . $product_id, array(), array('rating' => $ratingInput, 'comment' => $comment));
    header("Location: {$back}");
    exit();
}

if ($filtered) {
    $text .= ' Some words were hidden by our language filter.';
}
$_SESSION['success'] = $text;
header("Location: {$back}");
exit();
