<?php
session_start();
include('../includes/config.php');
include('../includes/loginCheck.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $product_id = (int)$_POST['product_id'];
    $user_id = $_SESSION['user_id'];
    $rating = trim($_POST['rating']);
    $comment = trim(strip_tags($_POST['comment']));
    $back = "../product/view.php?id={$product_id}";

    // only a customer who bought this product (a completed order) can review it
    $sql = "SELECT order_id FROM order_details_view WHERE user_id = ? AND product_id = ? AND status = 'completed' LIMIT 1";
    $result = mysqli_execute_query($conn, $sql, [$user_id, $product_id]);
    if (mysqli_num_rows($result) === 0) {
        $_SESSION['message'] = 'Only customers who bought this product can review it';
        header("Location: {$back}");
        exit();
    }

    // validation
    $hasErrors = false;
    if (filter_var($rating, FILTER_VALIDATE_INT) === false || $rating < 1 || $rating > 5) {
        $_SESSION['ratingError'] = 'Please choose a rating from 1 to 5';
        $hasErrors = true;
    }
    if ($comment === '') {
        $_SESSION['commentError'] = 'Please write your review';
        $hasErrors = true;
    } else if (strlen($comment) > 1000) {
        $_SESSION['commentError'] = 'Your review should be 1000 characters or less';
        $hasErrors = true;
    }
    if ($hasErrors) {
        $_SESSION['reviewRating'] = $rating;
        $_SESSION['reviewComment'] = $comment;
        header("Location: {$back}");
        exit();
    }

    // mask bad words with a regular expression, e.g. "shit" becomes "****"
    // \b = whole words only, so "class" or "assessment" are not changed; i = any capital letters
    $badWords = array('fuck', 'shit', 'bitch', 'asshole', 'bastard', 'damn', 'putangina', 'tangina', 'gago', 'tanga', 'bobo', 'ulol', 'tarantado', 'punyeta');
    foreach ($badWords as $word) {
        $comment = preg_replace("/\b{$word}\w*\b/i", "****", $comment);
    }

    $sql = "SELECT review_id FROM review WHERE user_id = ? AND product_id = ? LIMIT 1";
    $result = mysqli_execute_query($conn, $sql, [$user_id, $product_id]);

    if (mysqli_num_rows($result) === 1) {
        // the customer already reviewed this product: update the review
        $sql = "UPDATE review SET rating = ?, comment = ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?";
        mysqli_execute_query($conn, $sql, [$rating, $comment, $user_id, $product_id]);
        $_SESSION['success'] = 'your review was updated';
    } else {
        $sql = "INSERT INTO review (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())";
        mysqli_execute_query($conn, $sql, [$product_id, $user_id, $rating, $comment]);
        $_SESSION['success'] = 'thank you for your review';
    }
    header("Location: {$back}");
    exit();
}
header("Location: ../index.php");
