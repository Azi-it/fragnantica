<?php
session_start();

// remove everything saved in the session (user_id, name, email, role)
$_SESSION = array();

$_SESSION['success'] = 'you have been logged out';
header("Location: login.php");
exit();
