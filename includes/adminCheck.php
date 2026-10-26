<?php
// include this at the top of every admin page and script (after session_start)

// not logged in: go to the login page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Please login first to access that page';
    header("Location: /Fragnantica/user/login.php");
    exit();
}

// logged in but not an admin: go back to the home page
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'Only administrators can access that page';
    header("Location: /Fragnantica/index.php");
    exit();
}
