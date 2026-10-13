# TIM211 requirements checklist

Where each requirement of the TIM211 Information Management (1st Term 2026) rubric is
implemented, and how to show it during checking.

**Before the demo:** import `database.sql` (fresh install) or run `migrations/003_users_photos_reviews_views.sql`
(keeps your data), open `setup.php` to create the admin, and set up Mailtrap (`docs/EMAIL_SETUP.md`).

---

## MP1 Product/Service CRUD (20 pts)

| Requirement | Where | How to show it |
|---|---|---|
| Product CRUD | `product/create.php`, `store.php` (Create), `index.php`, `view.php` (Read), `edit.php`, `update.php` (Update), `delete.php` (Delete) | Admin > Products / Add Product |
| Upload a single photo | Main photo field, saved in `product/images/`, path in `product.img_path` | Add Product > Main photo |
| Upload/view **multiple** photos | "More photos" field (`photos[]`, multiple) on Add/Edit, stored in the `product_images` table; Edit lets you tick photos to remove and add more | Product page shows a gallery: click a thumbnail to see it big |

Photos are checked by reading the file itself (`getimagesize()` in `includes/uploads.php`), not
the browser's claimed type, so a renamed text file is rejected. Each photo gets a random name.

## MP2 User CRUD (20 pts)

| Requirement | Where | How to show it |
|---|---|---|
| User CRUD | `admin/users/`: `index.php` (list, search, filter), `create.php`/`store.php`, `edit.php`/`update.php`, `delete.php` | Admin > Users |
| Registration uploads a photo | `user/register.php`, `user/store.php` (saved in `user/images/`, path in `users.img_path`) | Register > Profile photo; the photo shows in the navbar |
| User updates profile with a photo | `user/profile.php` > Profile Photo card (upload/replace/remove) | My Profile |
| Admin deactivates a user | `admin/users/status.php`, column `users.status` | Users > Deactivate. The user is logged out on their next click and cannot log in |
| Admin updates an **active** user's role | `admin/users/update.php` | Users > Edit > Role. For a deactivated user the role list is locked and the server refuses the change |

Safety rules: an admin cannot deactivate, delete or change the role of their own account (so
the store always has an admin). Users with orders cannot be deleted (their order history is
kept), so they are deactivated instead.

## MP3 Login/logout and access control (20 pts)

| Requirement | Where |
|---|---|
| Login with email, logout | `user/login.php`, `user/logout.php` |
| Restrict ordinary users from CRUD pages | `require_admin()` in `includes/functions.php`, called at the top of every product, order, user and dashboard page **and** every script that changes data (`store.php`, `update.php`, `delete.php`, `status.php`, `update_status.php`) |
| Redirect unauthenticated users to the login page with a message | Not logged in: sent to `user/login.php` with "Please log in with an admin account to open that page." After logging in they go back to the page they wanted |
| Admins can access CRUD pages/scripts | `require_admin()` lets `role = 'admin'` through |

A logged in customer who opens an admin page goes to the home page with "Access denied. Only
administrators can open that page." The account is read again on every page
(`sync_session_user()`), so a deactivation or role change works immediately.

## MP4 Review CRUD (20 pts)

| Requirement | Where |
|---|---|
| Only a customer who availed the product can review | `can_review_product()`: the customer must have a **completed** order containing the product (checked with `order_details_view`) |
| Reviews on the product details page | `product/view.php` "Customer Reviews": average, per-star bars, every review |
| User can update the review | Edit button on your own review (`review/save.php` updates it; one review per customer per product: `UNIQUE (product_id, user_id)`) |
| Regex filter/mask for bad words | `includes/profanity.php`. Catches capitals, repeated letters (`shiiit`), look-alike characters (`sh1t`, `f@ck`, `a$$`) and dots between letters (`f.u.c.k`), English and Filipino words, and matches whole words only (so "class" and "assessment" are left alone). `shit` becomes `s***` |

Also: delete your own review, admins can delete any review, and a "Write a review" link
appears on completed orders.

## MP5 Form validation in PHP, no HTML5 validation (20 pts)

* **Login** (`user/login.php`): required email and password, email format, length; error under each field, the email is kept.
* **Product create/update** (`includes/product_form.php`): name/brand required and at most 100 characters,
  category must exist, scent type from the list, size a whole number from 1 to 100000, unit from the list,
  prices > 0 with at most 2 decimals, selling price not below cost, stock a whole number from 0 to 100000,
  description at most 255 characters, photos JPG/PNG at most 2 MB, at most 8 extra photos.
* No HTML5 validation: every form has `novalidate`, and there are no `required`, `minlength`, `maxlength`,
  `pattern` or `type="email"` attributes anywhere.

## Term Test Lab (35 pts)

| Requirement | Where |
|---|---|
| Completed transaction uses prepared statements | `cart/checkout.php`: one database transaction, `mysqli_prepare`/`mysqli_execute_query` with `?` placeholders, stock rows locked with `SELECT ... FOR UPDATE` |
| Admin views all orders and updates the status | `order/index.php` (all orders, filter by status, search), `order/view.php`, `order/update_status.php` |
| Email the customer about the updated transaction | `notify_order_status()` in `includes/mail.php` |
| Email lists the products, their subtotals and the grand total | `email_order_table()` (from `order_details_view` and `order_summary_view`) |
| Update the status of the order | Pending > Processing > Shipped > Completed, or Cancelled (stock is returned); every change is kept in `order_status_history` |
| PDF attachment of the receipt | `includes/receipt.php` + `includes/pdf.php`; also downloadable with the **Receipt (PDF)** button |
| Use Mailtrap | `includes/smtp.php` sends through `sandbox.smtp.mailtrap.io` (STARTTLS); login in `includes/mail_config.php` |

## Unit 1 Database design (20 pts)

**At least 2NF.** Every table has a single-column primary key, so no column can depend on only
part of a key (no partial dependencies). Repeating data lives in its own table:

| Table | Holds |
|---|---|
| `users` | accounts (customers and admins), photo, role, status |
| `customer` | delivery details, one row per user |
| `category` | product categories |
| `product` | products and their main photo |
| `product_images` | extra photos, many per product |
| `stock` | quantity on hand, one row per product |
| `cart` | cart lines, `UNIQUE (user_id, product_id)` |
| `orders` | one row per order |
| `order_items` | the products in each order; price and cost are kept as they were at checkout |
| `order_status_history` | every status change of an order |
| `review` | one review per customer per product |
| `password_resets` | password reset requests |

**MySQL VIEWS for the order transaction details** (`database.sql` and `migrations/003`):

* `order_details_view`: one row per product in an order, with product name, brand, size, quantity, price and **subtotal**.
* `order_totals_view`: per order, the number of items, units and the **grand total**.
* `order_summary_view`: each order with its customer and its totals.

The views are used by the admin order list and order page, the customer's order list and order
page, the order emails, the PDF receipt and the review check.

## Unit 2 (30 pts)

* **Program execution:** every page was run with PHP's error display on, without warnings. A
  missing upgrade shows a clear message telling you which migration to import.
* **User interface:** Bootstrap 5 layout, field errors under each input, photo gallery, star
  ratings with a star picker, avatars, status badges and an order progress bar.

## Quiz 3 Search on the homepage (15 pts)

`index.php`: a large search box at the top of the home page (plus the one in the navbar).
It searches product name, brand, category, scent type and description, works together with the
category, brand, scent, price and stock filters, and shows "Showing 1-n of N products for ...".

## Term Test Lecture

Multiple choice exam, as scheduled. Nothing to build.
