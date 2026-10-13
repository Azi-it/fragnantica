<?php
session_start();

// remove everything saved in the session (user_id, name, email, role)
// and start a new session id, so the old one is worthless
$_SESSION = array();
session_regenerate_id(true);

$_SESSION['success'] = 'you have been logged out';
header("Location: login.php");
exit();
