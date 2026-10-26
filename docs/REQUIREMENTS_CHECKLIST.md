# ITIM211 requirements checklist

Where each requirement is in the project and how to show it.

**Before the demo:** put the project folder in `C:\xampp\htdocs\Veloura` (open it at `http://localhost/Veloura`), import `database.sql`
in phpMyAdmin, and set up Mailtrap (`docs/EMAIL_SETUP.md`).

Accounts: admin `admin@veloura.test` / `admin123`, customer `customer@veloura.test` / `customer123`.

## MP1 Product/Service CRUD

| Requirement | Where |
|---|---|
| Product CRUD | `product/index.php`, `create.php`/`store.php`, `edit.php`/`update.php`, `delete.php`, `view.php` |
| Upload a single photo | Main photo, saved in `product/images/`, path in `product.img_path` |
| Upload/view multiple photos | "More Photos" (`photos[]`, multiple) on Add/Edit, saved in the `product_images` table. Edit has a "remove" checkbox per photo. `product/view.php` shows all photos |

## MP2 User CRUD

| Requirement | Where |
|---|---|
| User CRUD | `admin/users/`: `index.php`, `create.php`/`store.php`, `edit.php`/`update.php`, `delete.php` |
| Registration uploads a photo | `user/register.php`, `user/store.php` (saved in `user/images/`) |
| User updates profile with a photo | `user/profile.php` > Upload new image |
| Admin deactivates a user | `admin/users/status.php` (Deactivate/Activate buttons in the users list). `user/login.php` refuses inactive accounts |
| Admin updates an active user's role | `admin/users/update.php`. The role list is disabled for an inactive user and the server refuses the change |

## MP3 Login/logout and access control

| Requirement | Where |
|---|---|
| Login with email, logout | `user/login.php`, `user/logout.php` |
| Restrict ordinary users from CRUD pages | `includes/adminCheck.php`, included at the top of every product, order and user page and script |
| Redirect unauthenticated users with a message | `adminCheck.php` / `loginCheck.php`: "Please login first to access that page" |
| Admins can access CRUD pages/scripts | `adminCheck.php` lets `role = 'admin'` through |

## MP4 Review CRUD

| Requirement | Where |
|---|---|
| Only a customer who availed the product can review | `review/store.php` and `product/view.php` check `order_details_view` for a **completed** order with the product |
| Reviews on the product details page | `product/view.php` > Customer Reviews |
| User can update the review | Same form shows "Update review"; `review/store.php` updates it (one review per customer per product) |
| Regex filter for bad words | `review/store.php`: `preg_replace("/\b{$word}\w*\b/i", "****", ...)` |

## MP5 Form validation in PHP, no HTML5 validation

Login (`user/login.php`) and product create/update (`product/store.php`, `product/update.php`) are
checked in PHP. The forms use `novalidate` and plain `type="text"` inputs (no `required`, `type="email"`
or `type="number"`). Each error shows under its field.

## Term Test Lab

| Requirement | Where |
|---|---|
| Completed transaction uses prepared statements | `cart/checkout.php`: one transaction, `mysqli_prepare` / `mysqli_stmt_bind_param` |
| Admin views all orders and updates the status | `admin/orders.php`, `admin/orderDetails.php`, `admin/updateOrder.php` |
| Email the customer about the update | `admin/updateOrder.php` (PHPMailer) |
| Email lists the products, subtotals and grand total | Same file, the table in `$mail->Body` |
| Update the status of the order | `UPDATE orders SET status = ?` (cancelling returns the items to stock) |
| PDF receipt attachment | FPDF in `admin/updateOrder.php`, attached with `addStringAttachment` |
| Mailtrap | `includes/mail_config.php` (see `docs/EMAIL_SETUP.md`) |

## Unit 1 Database design

Every table has a single-column primary key, so there are no partial dependencies (2NF).
Repeating data has its own table: `product_images` (many photos per product), `order_items`
(many products per order), `review`, `customer` (delivery details), `category`, `stock`.

Views (`database.sql`):

* `order_details_view`: one row per product in an order, with the customer, address and **subtotal**.
* `order_totals_view`: one row per order with its **grand total**.

## Quiz 3 Search on the homepage

`index.php`: search box at the top of the home page (and in the navbar). Searches product name,
brand, category and description.
