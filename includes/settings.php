<?php
// store settings (no passwords in here; this file is safe to commit)

// the store's timezone. XAMPP's php.ini defaults to Europe/Berlin, while MySQL follows
// the computer's clock, so set it here to keep PHP times in step with the database
date_default_timezone_set('Asia/Manila');

// the address of the store, used in links inside emails (no slash at the end)
// change this when the site is moved to a real domain
define('APP_URL', 'http://localhost/Fragnantica');

// how emails are delivered:
//   'log'  = saved as files in storage/mail/ instead of being sent (default, works without any setup)
//   'mail' = really sent through PHP's mail() function; set up XAMPP's sendmail first (see docs/EMAIL_SETUP.md)
define('MAIL_DRIVER', 'log');

define('MAIL_FROM', 'no-reply@fragrancestore.local');
define('MAIL_FROM_NAME', 'Fragrance & Scent Store');

// products at or below this quantity show up as "low stock" on the admin dashboard
define('LOW_STOCK_THRESHOLD', 5);

// how long a password reset link stays valid, in minutes
define('RESET_LINK_MINUTES', 60);

// setup.php (create the first admin) only works from the computer the store runs on.
// on a hosted server you cannot use localhost: set this to true, run setup.php straight
// after uploading, then set it back to false.
define('ALLOW_REMOTE_SETUP', false);

// passwords that were published with older versions of this project.
// an admin who logs in with one of these is sent to change it.
define('KNOWN_DEFAULT_PASSWORDS', array('admin123'));
