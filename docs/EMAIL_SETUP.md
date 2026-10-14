# Sending real emails

The store sends emails for: order received, order status changes, password reset links,
and "your password was changed" notices.

By default (`MAIL_DRIVER` is `'log'` in `includes/settings.php`) nothing is sent. Each email is
saved as a web page in `storage/mail/` instead. Open those files in a browser to read them, for
example to click a password reset link while testing. That folder is blocked from the web and
ignored by git, because the files contain reset links.

To send real emails through a Gmail account with XAMPP:

## 1. Get a Gmail app password

1. Sign in to the Google account the store will send from.
2. Turn on **2-Step Verification** (Google Account > Security).
3. Create an **App password** (Google Account > Security > App passwords). Google shows a
   16-character password once. Copy it.

Use the app password below, never your normal Gmail password.

## 2. Configure XAMPP's sendmail

Edit `C:\xampp\sendmail\sendmail.ini` and set these lines (they already exist, some commented out):

```ini
smtp_server=smtp.gmail.com
smtp_port=587
smtp_ssl=tls
auth_username=your.store@gmail.com
auth_password=the16characterapppassword
force_sender=your.store@gmail.com
error_logfile=error.log
```

This file is outside the project folder, so the password never ends up on GitHub.

## 3. Point PHP at sendmail

Edit `C:\xampp\php\php.ini`, find the `[mail function]` section and set:

```ini
sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"
```

Then restart **Apache** in the XAMPP Control Panel.

## 4. Switch the store over

In `includes/settings.php`:

```php
define('MAIL_DRIVER', 'mail');
define('MAIL_FROM', 'your.store@gmail.com');
```

`MAIL_FROM` should be the same Gmail address, or Gmail may reject or rewrite the sender.

## 5. Test it

Use **Forgot password?** on the login page with an account whose inbox you can check.
If nothing arrives, look in `C:\xampp\sendmail\error.log` and in Apache's
`C:\xampp\apache\logs\error.log`.

## When the site goes live

Change `APP_URL` in `includes/settings.php` to the real address of the store, otherwise the
links inside emails will point to `localhost`.
