<?php
// Mailtrap login for sending emails. Copy this file to includes/mail_config.php
// and fill in your own details. mail_config.php is in .gitignore, so your password
// never ends up on GitHub.
//
// Where to find them: mailtrap.io > Email Testing (Sandboxes) > your inbox >
// Integration > SMTP. Copy "Username" and "Password" below.

define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
define('SMTP_PORT', 2525);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USERNAME', 'your-mailtrap-username');
define('SMTP_PASSWORD', 'your-mailtrap-password');

// the sender shown in the emails (Mailtrap's test inbox accepts any address)
define('MAIL_FROM', 'no-reply@fragrancestore.test');

// uncomment to save emails as files in storage/mail/ instead of sending them
// define('MAIL_DRIVER', 'log');
