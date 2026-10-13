<?php
// store settings (no passwords in here; this file is safe to commit)

// the store's timezone. XAMPP's php.ini defaults to Europe/Berlin, while MySQL follows
// the computer's clock, so set it here to keep PHP times in step with the database
date_default_timezone_set('Asia/Manila');

// the address of the store, used in links inside emails (no slash at the end)
// change this when the site is moved to a real domain
define('APP_URL', 'http://localhost/Fragnantica');

// the path part of APP_URL ("/Fragnantica"), used for links and redirects inside the site
define('BASE_PATH', rtrim((string)parse_url(APP_URL, PHP_URL_PATH), '/'));

// how emails are delivered:
//   'smtp' = sent through an SMTP server. By default that is Mailtrap (https://mailtrap.io),
//            which catches every email in a test inbox instead of delivering it.
//            Put your Mailtrap username and password in includes/mail_config.php
//            (copy includes/mail_config.example.php). See docs/EMAIL_SETUP.md.
//   'log'  = saved as files in storage/mail/ instead of being sent (works without any setup)
// 'smtp' without a username in mail_config.php falls back to 'log', so the store keeps working.
if (file_exists(__DIR__ . '/mail_config.php')) {
    require_once __DIR__ . '/mail_config.php';
}
defined('MAIL_DRIVER') || define('MAIL_DRIVER', 'smtp');
defined('SMTP_HOST') || define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
defined('SMTP_PORT') || define('SMTP_PORT', 2525);
defined('SMTP_ENCRYPTION') || define('SMTP_ENCRYPTION', 'tls'); // 'tls' (STARTTLS), 'ssl', or '' for none
defined('SMTP_USERNAME') || define('SMTP_USERNAME', '');
defined('SMTP_PASSWORD') || define('SMTP_PASSWORD', '');

defined('MAIL_FROM') || define('MAIL_FROM', 'no-reply@fragrancestore.test');
defined('MAIL_FROM_NAME') ||define('MAIL_FROM_NAME', 'Fragrance & Scent Store');

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

// largest photo that can be uploaded (products and profile pictures), in bytes
define('MAX_PHOTO_BYTES', 2 * 1024 * 1024);

// how many extra photos one product may have (besides its main photo)
define('MAX_PRODUCT_PHOTOS', 8);
