# Sending emails with Mailtrap

When an admin updates an order's status (`admin/updateOrder.php`), the customer gets an email
listing the products, their subtotals and the grand total, with the receipt attached as a PDF.
The email is sent with PHPMailer (the `PHPMailer/` folder) through Mailtrap's test inbox.
The PDF is made with FPDF (the `fpdf/` folder).

## Setup

1. Log in to https://mailtrap.io and open **Email Testing > Sandboxes > your inbox**.
2. Open **Integration > SMTP** and copy the **Username** and **Password**.
3. Copy `includes/mail_config.example.php` to `includes/mail_config.php`.
4. Put your username and password in `includes/mail_config.php`.

`includes/mail_config.php` is in `.gitignore`, so it is not uploaded to GitHub. Copy it by hand
to every computer you run the project on.

If the file is missing, the status is still saved and the page tells you the email was not sent.
