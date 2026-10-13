# Sending emails with Mailtrap

The store sends emails for: order received, every order status change, password reset links,
and "your password was changed" notices.

Order emails list every product with its price, quantity and subtotal, plus the grand total,
and carry the **PDF receipt** of the transaction as an attachment.

Emails go through [Mailtrap](https://mailtrap.io). Its Email Testing sandbox catches every email
in a test inbox, so nothing reaches a real customer. The store talks to Mailtrap over SMTP with
its own small client (`includes/smtp.php`), so there is nothing to install.

## 1. Get your Mailtrap login

1. Sign up at mailtrap.io (free).
2. Open **Email Testing** (Sandboxes) and open your inbox (one called "My Inbox" is created for you).
3. Open the **Integration** tab and choose **SMTP**. Note the **Username** and **Password**.

## 2. Put them in the store

Copy `includes/mail_config.example.php` to `includes/mail_config.php` and fill in your username
and password:

```php
define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
define('SMTP_PORT', 2525);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USERNAME', 'your-mailtrap-username');
define('SMTP_PASSWORD', 'your-mailtrap-password');
```

`includes/mail_config.php` is in `.gitignore`, so the password never ends up on GitHub.

## 3. Test it

1. Log in as a customer, add products to the cart and check out. The "Order received" email
   arrives in your Mailtrap inbox with the receipt attached.
2. Log in as the admin, open **Orders > Manage** and change the status (e.g. Start Processing).
   The customer gets a status email listing the products, their subtotals and the grand total,
   with `receipt-order-N.pdf` attached.

If nothing arrives, the admin sees "the email to the customer could not be sent", and the exact
reason is written to Apache's error log (`C:\xampp\apache\logs\error.log`). A copy of the
unsent email is also saved in `storage/mail/`.

## Without Mailtrap

While `includes/mail_config.php` does not exist (or has no username), emails are not sent but
saved as web pages in `storage/mail/`, with their PDF attachments next to them. Open them from
the folder to read them. You can also force this with `define('MAIL_DRIVER', 'log');` in
`includes/mail_config.php`. That folder is blocked from the web and ignored by git, because the
files contain password reset links.

## When the site goes live

Mailtrap's sandbox never delivers to real inboxes. To send real emails, use Mailtrap's
**Email Sending** SMTP settings (or another SMTP provider) in `includes/mail_config.php`, set
`MAIL_FROM` to an address on your verified domain, and change `APP_URL` in
`includes/settings.php` to the real address of the store, otherwise the links inside emails
point to `localhost`.
