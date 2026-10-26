<?php
// include this at the top of pages that need a logged in user (after session_start)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Please login first to access that page';
    header("Location: /Fragnantica/user/login.php");
    exit();
}
