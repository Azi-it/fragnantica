# Veloura: Project Defense Guide

A map of every feature in the project: **which file it is in, which lines, and the code that does the work.**

> **Important first point for the panel:** this project does **not** declare its own PHP functions (there is no `function myFunction() {}` anywhere in our code). It is written in **procedural PHP**: each `.php` file *is* one function of the system (one page or one action).
> The only `function` / `class` declarations are inside the two third-party libraries, **FPDF** (`fpdf/`) and **PHPMailer** (`PHPMailer/`), which we use but did not write.
>
> So in this guide, "function" means **a feature of the system**, and the "code block" is the part of the file that does it.

---

## 0. The pattern every file follows

Almost every action file follows the same five steps. If you can explain this, you can explain any file:

```php
session_start();                          // 1. start/resume the session ($_SESSION)
include('../includes/config.php');        // 2. connect to the database ($conn)
include('../includes/adminCheck.php');    // 3. security guard (or loginCheck.php)
// 4. validate input -> run SQL with a prepared statement
$_SESSION['success'] = 'product saved';   // 5. store a message, then redirect
header("Location: index.php");
```

* **Flash messages**: a message is stored in `$_SESSION['message']` (error) or `$_SESSION['success']`, shown once by `includes/alert.php`, then deleted with `unset()`.
* **POST → Redirect → GET**: forms POST to a "store/update" file, which redirects back to a page. Refreshing the page does not resubmit the form.
* **Prepared statements**: every query with user input uses `?` placeholders (`mysqli_execute_query` or `mysqli_prepare` + `mysqli_stmt_bind_param`). This prevents **SQL injection**.
* **Server-side validation**: forms have `novalidate`, so the browser does not check them. PHP does all the checking.

---

## 1. Shared files (`includes/`)

### 1.1 Database connection: [includes/config.php](includes/config.php:1)
Lines 2–9. Creates `$conn`, used by every other file.
```php
$conn = mysqli_connect($db_host, $db_username, $db_passwd) or die("Could not connect!\n");
$db_name = "fragrance_store";
mysqli_select_db($conn, $db_name) or die(...);
```

### 1.2 Login guard: [includes/loginCheck.php](includes/loginCheck.php:3)
Lines 3–7. Sends anyone not logged in to the login page.
```php
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Please login first to access that page';
    header("Location: /Veloura/user/login.php");
    exit();
}
```

### 1.3 Admin guard: [includes/adminCheck.php](includes/adminCheck.php:5)
Two checks: not logged in (lines 5–9) → login page; logged in but `role !== 'admin'` (lines 12–16) → home page. Included at the top of every admin file.

### 1.4 Flash messages: [includes/alert.php](includes/alert.php:3)
Lines 3–9 show `$_SESSION['message']` as a red Bootstrap alert; lines 11–17 show `$_SESSION['success']` as a blue one. Each is `unset()` after showing, so it appears only once.

### 1.5 Navigation bar: [includes/header.php](includes/header.php:33)
* Lines 33–38: admin links (Products, Orders, Users) only if `$_SESSION['role'] === 'admin'`.
* Lines 40–44: Cart, My Orders, My Profile only if logged in.
* Lines 47–51: search box (sends `?search=` to `index.php`).
* Lines 52–65: Login/Register if logged out; otherwise the user's photo, email and Logout.

### 1.6 Footer: [includes/footer.php](includes/footer.php:2)
Prints the copyright with the current year: `date('Y')`.

### 1.7 Email settings: [includes/mail_config.example.php](includes/mail_config.example.php:9)
`define()` constants for the Mailtrap SMTP server. Copied to `mail_config.php` (which is in `.gitignore` so the password is never uploaded to GitHub).

---

## 2. Customer side

### 2.1 Home page, product list and search: [index.php](index.php:7)

| Lines | What it does |
|---|---|
| 7–14 | **Search**: if `?search=` is given, look for the keyword in name, brand, category or description using `LIKE` |
| 16–21 | No search: show all products |
| 22 | `mysqli_num_rows()` counts the results |
| 48–60 | For each product: show the **Add to Cart** form if `qty > 0`, otherwise "Out of stock" |
| 62–71 | Build the product card (photo, name, brand, size, price) |

```php
$sql = "SELECT ... FROM product p INNER JOIN category c USING (category_id) INNER JOIN stock s USING (product_id)
        WHERE p.name LIKE ? OR p.brand LIKE ? OR c.name LIKE ? OR p.description LIKE ?";
$like = "%{$keyword}%";
$results = mysqli_execute_query($conn, $sql, [$like, $like, $like, $like]);
```

### 2.2 Product details page: [product/view.php](product/view.php:6)
Anyone can view it; no login needed.

| Lines | What it does |
|---|---|
| 6–16 | Load the product with its category and stock (3-table JOIN). Not found → back to home |
| 19 | Load the extra photos from `product_images` |
| 22–26 | Load all reviews with the reviewer's name and photo (JOIN `users`) |
| 28–33 | **Average rating**: `SELECT AVG(rating)`, rounded with `round(..., 1)` |
| 36–45 | **Can this user review?** Only if they have a *completed* order containing this product |
| 74–84 | Add to Cart form if in stock |
| 93–150 | Review form (shows "Post review" or "Update review" if they already wrote one) |
| 158–180 | Show each review with filled/empty stars (loop `for ($i = 1; $i <= 5; $i++)`) |

```php
$sql = "SELECT order_id FROM order_details_view WHERE user_id = ? AND product_id = ? AND status = 'completed' LIMIT 1";
$canReview = mysqli_num_rows($result) > 0;
```

### 2.3 Registration form: [user/register.php](user/register.php:9)
Just the form (name, email, password, confirm password, optional photo). Lines 13 and 19 refill name/email from the session if validation failed.

### 2.4 Save a new account: [user/store.php](user/store.php:12)

| Lines | What it does |
|---|---|
| 13–29 | **Validation**: name required; email matches the regex; password ≥ 6 characters; passwords match |
| 31–36 | **Duplicate email check** |
| 39–50 | Optional photo: JPG/PNG only, saved as `images/<timestamp>_<name>` with `move_uploaded_file()` |
| 53 | **Password hashing**: `password_hash($password, PASSWORD_BCRYPT)` |
| 55–58 | `INSERT INTO users` with a prepared statement; role is always `customer` |

```php
} else if (!preg_match("/^[\w.+-]+@\w+(\.\w+)+$/", $email)) {   // email format
...
$password = password_hash($password, PASSWORD_BCRYPT);
$stmt1 = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt1, 'ssss', $name, $email, $password, $target);
mysqli_stmt_execute($stmt1);
```

### 2.5 Login: [user/login.php](user/login.php:5)

| Lines | What it does |
|---|---|
| 11–21 | Validation: email required and valid format; password required |
| 24–25 | Find the user by email (prepared statement) |
| 29 | **Check the password**: `password_verify()` compares with the bcrypt hash |
| 31 | Block deactivated accounts (`status !== 'active'`) |
| 34–37 | Save `email`, `user_id`, `role`, `photo` in `$_SESSION` |
| 38–42 | Admin → product list; customer → home page |

```php
if (!password_verify($password, $row['password'])) {
    $_SESSION['message'] = 'wrong email or password';
} else if ($row['status'] !== 'active') {
    $_SESSION['message'] = 'your account has been deactivated';
} else {
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['role'] = $row['role'];
```
Note: the same message "wrong email or password" is used for both a wrong email and a wrong password, so an attacker cannot tell which emails exist.

### 2.6 Logout: [user/logout.php](user/logout.php:2)
`session_start(); session_destroy();` then back to the home page.

### 2.7 Profile and delivery details: [user/profile.php](user/profile.php:9)

| Lines | What it does |
|---|---|
| 9–26 | **Upload a profile photo** (JPG/PNG), update `users.img_path` and `$_SESSION['photo']` |
| 29–59 | **Save name and delivery details** |
| 37–42 | Validation: all fields required; zip code = 4 digits `/^[0-9]{4}$/`; phone = `/^09[0-9]{9}$/` |
| 48–54 | **Insert or update**: first time → `INSERT INTO customer`, after that → `UPDATE customer` |
| 61–62 | Load the profile with a `LEFT JOIN` (a new user has no `customer` row yet) |

### 2.8 Shopping cart (stored in the session)
The cart is kept in `$_SESSION["cart_products"]`, an array indexed by `product_id`. Nothing is saved to the database until checkout.

**Add / change / remove: [cart/cart_update.php](cart/cart_update.php:7)**

| Lines | What it does |
|---|---|
| 7–45 | **Add** (`type=add`): load the product and stock, validate quantity |
| 26–28 | Same product again → add to the existing quantity |
| 29–33 | **Stock check**: cannot add more than what is in stock |
| 35–40 | Store the item in the session array |
| 48–60 | **Update quantities** from the cart page |
| 62–66 | **Remove** ticked items with `unset()` |

```php
if (isset($_SESSION["cart_products"][$product_id])) {
    $qty = $qty + $_SESSION["cart_products"][$product_id]["product_qty"];
}
if ($qty > $row['quantity']) { ... "only X left in stock" ... }
$_SESSION["cart_products"][$product_id] = $new_product;
```

**View cart: [cart/view_cart.php](cart/view_cart.php:11)**
Lines 29–44 loop the session cart, compute `subtotal = price * qty`, and add to `$total`. Quantity boxes are named `product_qty[<id>]` and remove checkboxes `remove_code[]`, so PHP receives them as arrays.

### 2.9 Checkout (the most important file): [cart/checkout.php](cart/checkout.php:6)

| Lines | What it does |
|---|---|
| 6–10 | Empty cart → stop |
| 13–19 | No delivery details yet → send to profile page |
| 24 | **Start a transaction** |
| 26–30 | `INSERT INTO orders` → get the new `order_id` with `mysqli_insert_id()` |
| 32–38 | Prepare two statements: insert each order item, and subtract stock |
| 40 | `SELECT ... FOR UPDATE` **locks the stock row** so two customers cannot buy the last item at the same time |
| 47–53 | Not enough stock → **rollback** (nothing is saved) |
| 54 | Uses **today's price** from the database, not the price stored in the session |
| 60–61 | **Commit**, then empty the cart |
| 64–68 | Any SQL error → rollback |

```php
mysqli_begin_transaction($conn);
// INSERT orders, then for each cart item:
$row = mysqli_fetch_assoc(mysqli_execute_query($conn, $q4, [$product_code]));   // ... FOR UPDATE
if (!$row || $row['quantity'] < $product_qty) {
    mysqli_rollback($conn);
    ...
}
mysqli_stmt_execute($stmt2);   // INSERT INTO order_items
mysqli_stmt_execute($stmt3);   // UPDATE stock SET quantity = quantity - ?
...
mysqli_commit($conn);
```
**Why a transaction?** An order has several steps (order, items, stock). If one fails, all are undone, so there is never an order without items or stock that was subtracted for a failed order.

### 2.10 My Orders: [user/myorders.php](user/myorders.php:7)
* Line 7: the customer's orders from the **view** `order_totals_view` (one row per order with its total).
* Lines 23–24: for each order, its products from the **view** `order_details_view`.
* Lines 28–30: a completed order shows a "Write a review" link.

### 2.11 Write or update a review: [review/store.php](review/store.php:6)

| Lines | What it does |
|---|---|
| 14–20 | **Only buyers can review**: must have a completed order with this product |
| 24–27 | Rating must be an integer 1–5 |
| 28–34 | Comment required, max 1000 characters |
| 44–47 | **Bad-word filter** with a regular expression |
| 49–61 | Already reviewed → `UPDATE`; otherwise → `INSERT` |

```php
$badWords = array('fuck', 'shit', ..., 'gago', 'tanga', 'bobo', ...);
foreach ($badWords as $word) {
    $comment = preg_replace("/\b{$word}\w*\b/i", "****", $comment);
}
```
`\b` = whole word only (so "class" is not changed), `\w*` = also catches longer forms, `i` = ignore capital letters.

---

## 3. Admin side
Every admin file includes `adminCheck.php` first.

### 3.1 Product list: [product/index.php](product/index.php:7)
Lines 7–11: all products with category and stock (3-table JOIN). Lines 34–48: table rows with edit/delete icons.

### 3.2 Add product form: [product/create.php](product/create.php:8)
* Line 8: categories for the drop-down.
* Lines 29–36: the previously chosen category stays `selected` after an error.
* Each field shows its own error (e.g. lines 19–24 for the name) and its previous value from the session.
* Line 115: `photos[]` with `multiple` lets the admin upload many extra photos.
* Line 129: clears the remembered values so they appear only once.

### 3.3 Save new product: [product/store.php](product/store.php:6)

| Lines | What it does |
|---|---|
| 18–58 | **Validation**: name ≤ 100 chars, category chosen, brand, size, description ≤ 255, cost > 0, sell > 0, **sell ≥ cost**, quantity is a whole number ≥ 0 |
| 61–67 | Main photo required, JPG/PNG only |
| 70–77 | Extra photos checked one by one |
| 79–90 | Errors → remember typed values in the session, back to the form |
| 98–100 | Save the main photo to `images/` |
| 102–127 | **Transaction**: insert `product`, insert `stock`, insert each extra photo into `product_images` |
| 131–136 | Error → rollback |

```php
mysqli_stmt_bind_param($stmt1, 'issssdds', $category_id, $name, $brand, $size, $desc, $cost, $sell, $target);
```
Type letters: `i` = integer, `s` = string, `d` = decimal (double).

### 3.4 Edit product form: [product/edit.php](product/edit.php:6)
* Lines 6–14: load the product; not found → back.
* Lines 17–27: after a failed save, show what the admin typed instead of the database values.
* Lines 139–142: each extra photo has a "remove" checkbox (`delete_photos[]`).

### 3.5 Save product changes: [product/update.php](product/update.php:6)
Same validation as `store.php` (lines 19–89), then a transaction (lines 96–147):
* Lines 98–111: if a new main photo was chosen, update `img_path` too; otherwise keep the old one.
* Lines 114–117: update stock.
* Lines 120–124: delete ticked photos (`AND product_id = ?` makes sure the photo belongs to this product).
* Lines 127–136: add new photos.

### 3.6 Delete product: [product/delete.php](product/delete.php:6)
Lines 14–31: transaction that deletes `stock` then `product`. Extra photos and reviews are removed automatically by `ON DELETE CASCADE`. If the product is already in an order, the foreign key blocks the delete, and the `catch` shows *"cannot be deleted because it is already in a customer order"*. This protects sales history.

### 3.7 All orders: [admin/orders.php](admin/orders.php:8)
Line 8: reads `order_totals_view` (customer, date, item count, total, status).

### 3.8 Order details: [admin/orderDetails.php](admin/orderDetails.php:9)
* Lines 9–15: customer and delivery details from `order_details_view`.
* Lines 18–19 and 40–50: products in the order and the grand total.
* Lines 55–76: status drop-down (pending, processing, shipped, completed, cancelled). A cancelled order cannot be changed.

### 3.9 Update order status + PDF receipt + email: [admin/updateOrder.php](admin/updateOrder.php:13)

| Lines | What it does |
|---|---|
| 17–22 | Status must be one of the 5 allowed values (`in_array`) |
| 31–35 | A cancelled order cannot be changed |
| 38–59 | **Transaction**: update the status |
| 46–51 | **Cancelled → return items to stock** (`UPDATE stock ... JOIN order_items ... quantity + oi.quantity`) |
| 68–115 | **Build the PDF receipt with FPDF** |
| 115 | `$pdf->Output('S')` returns the PDF as a string (not a file) so it can be attached |
| 118–123 | No `mail_config.php` → status is still saved, but a warning says the email was not sent |
| 126–152 | **Send the email with PHPMailer** through Mailtrap SMTP, HTML table + PDF attachment |
| 154–159 | Success or error message |

```php
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Helvetica', 'B', 18);
$pdf->Cell(0, 10, 'Veloura', 0, 1);
...
$pdfFile = $pdf->Output('S');

$mail = new PHPMailer();
$mail->isSMTP();
$mail->Host = SMTP_HOST;
...
$mail->addStringAttachment($pdfFile, "receipt-order-{$order_id}.pdf");
$mail->send();
```

### 3.10 User management (`admin/users/`)

| File | Lines | What it does |
|---|---|---|
| [index.php](admin/users/index.php:7) | 7–9, 30–57 | List all users with photo, role, status; Activate/Deactivate button depends on status (37–43) |
| [create.php](admin/users/create.php:11) | 11–40 | Add-user form, includes a **role** drop-down (customer/admin) |
| [store.php](admin/users/store.php:14) | 14–30, 32–37, 55–60 | Same validation as registration plus role check; duplicate email; bcrypt hash; insert |
| [edit.php](admin/users/edit.php:6) | 6–13, 41–54 | Edit form; the role drop-down is `disabled` for a deactivated user |
| [update.php](admin/users/update.php:11) | 20–23, 26–46, 48–53, 69–79 | Keep the current role if the drop-down was disabled; **an admin cannot change their own role**; email must be unique among *other* users (`user_id <> ?`); update session if admin edited themself |
| [status.php](admin/users/status.php:7) | 10–24 | Activate/deactivate; only `active`/`inactive` allowed; **an admin cannot deactivate themself** |
| [delete.php](admin/users/delete.php:6) | 12–16, 19–27 | Cannot delete yourself; a user with orders/reviews is blocked by the foreign key → "Deactivate it instead" |

---

## 4. Database: [database.sql](database.sql:1)

### Tables
| Table | Lines | Purpose / key rules |
|---|---|---|
| `users` | 37–46 | Customers and admins. `email UNIQUE`, `role ENUM('customer','admin')`, `status ENUM('active','inactive')` |
| `customer` | 49–57 | Delivery details, **one per user** (`user_id UNIQUE`), `ON DELETE CASCADE` |
| `category` | 59–62 | Product categories, `name UNIQUE` |
| `product` | 65–76 | Product info, cost and sell price `DECIMAL(10,2)` |
| `product_images` | 79–84 | **Many photos per product** (one-to-many), cascade delete |
| `stock` | 86–90 | Quantity per product (one-to-one with `product`) |
| `orders` | 93–99 | Order header, status `ENUM` |
| `order_items` | 102–110 | Products in an order; `price` = price **at the time of the order** |
| `review` | 113–124 | `UNIQUE (product_id, user_id)` = **one review per customer per product** |

### Views (saved SELECT queries)
| View | Lines | Used by |
|---|---|---|
| `order_details_view` | 127–135 | One row per product in each order, with customer, delivery details and `subtotal = quantity * price`. Used by order details, My Orders, reviews, email/PDF |
| `order_totals_view` | 138–144 | One row per order with `SUM(...)` total and item count (`GROUP BY`). Used by the orders lists |

**Why views?** The long 5-table JOIN is written once in the database and reused by many pages as a simple `SELECT ... FROM order_details_view`.

### Sample accounts (lines 146–148)
* admin: `admin@veloura.test` / `admin123`
* customer: `customer@veloura.test` / `customer123`

---

## 5. Built-in PHP functions used (and what they do)

| Function | What it does | Example location |
|---|---|---|
| `session_start()` | Starts/resumes the session so `$_SESSION` works | top of every file |
| `session_destroy()` | Logs the user out | [user/logout.php:3](user/logout.php:3) |
| `mysqli_connect()` / `mysqli_select_db()` | Connect to MySQL and pick the database | [includes/config.php:5](includes/config.php:5) |
| `mysqli_query()` | Run a query with **no** user input | [product/index.php:10](product/index.php:10) |
| `mysqli_execute_query()` | Prepare + run a query with `?` values in one step (PHP 8.2+) | [index.php:14](index.php:14) |
| `mysqli_prepare()` / `mysqli_stmt_bind_param()` / `mysqli_stmt_execute()` | Prepared statement in 3 steps, with types (`i`, `s`, `d`) | [product/store.php:105](product/store.php:105) |
| `mysqli_fetch_assoc()` | Get one row as an array (`$row['name']`) | many |
| `mysqli_fetch_all(..., MYSQLI_ASSOC)` | Get all rows at once | [admin/updateOrder.php:64](admin/updateOrder.php:64) |
| `mysqli_num_rows()` | Count rows in a result | [index.php:22](index.php:22) |
| `mysqli_insert_id()` | ID of the row just inserted | [cart/checkout.php:30](cart/checkout.php:30) |
| `mysqli_begin_transaction()` / `mysqli_commit()` / `mysqli_rollback()` | All-or-nothing database changes | [cart/checkout.php:24](cart/checkout.php:24) |
| `password_hash()` | Hash a password with bcrypt | [user/store.php:53](user/store.php:53) |
| `password_verify()` | Check a password against its hash | [user/login.php:29](user/login.php:29) |
| `preg_match()` | Check text against a regular expression (email, phone, zip) | [user/profile.php:39](user/profile.php:39) |
| `preg_replace()` | Replace text matching a regex (bad words) | [review/store.php:46](review/store.php:46) |
| `filter_var(..., FILTER_VALIDATE_INT)` | Check a value is a whole number | [cart/cart_update.php:19](cart/cart_update.php:19) |
| `filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)` | Read and validate `?id=` from the URL | [product/delete.php:6](product/delete.php:6) |
| `trim()` / `strip_tags()` | Remove spaces / remove HTML tags from input | [product/store.php:7](product/store.php:7) |
| `is_numeric()` / `strlen()` | Number check / length check in validation | [product/store.php:21](product/store.php:21) |
| `move_uploaded_file()` | Move an uploaded photo into `images/` | [product/store.php:100](product/store.php:100) |
| `time()` | Timestamp added to file names so uploads don't overwrite each other | [product/store.php:99](product/store.php:99) |
| `header("Location: ...")` + `exit()` | Redirect and stop the script | everywhere |
| `isset()` / `empty()` / `unset()` | Check a variable exists / is empty; delete it | everywhere |
| `in_array()` | Check the status is one of the allowed values | [admin/updateOrder.php:18](admin/updateOrder.php:18) |
| `number_format()` / `sprintf("%01.2f")` / `round()` | Money and rating formatting | [admin/updateOrder.php:96](admin/updateOrder.php:96) |
| `date('Y')` | Current year in the footer | [includes/footer.php:2](includes/footer.php:2) |
| `file_exists()` | Check `mail_config.php` exists before emailing | [admin/updateOrder.php:118](admin/updateOrder.php:118) |
| `define()` | Create constants for SMTP settings | [includes/mail_config.example.php:9](includes/mail_config.example.php:9) |

---

## 6. Files that are NOT used (be ready if asked)

These 5 files in `cart/` are **left over from an older version** of the project. Nothing in the navigation links to them, and they would fail if opened, because they use a `cart` table that `database.sql` now **drops** (line 22) and order columns (`total_amount`, `shipping_address`, `created_at`) that the current `orders` table does not have.

| File | Replaced by |
|---|---|
| `cart/add.php` | `cart/cart_update.php` |
| `cart/index.php` | `cart/view_cart.php` |
| `cart/remove.php` | `cart/cart_update.php` (remove checkboxes) |
| `cart/orders.php` | `user/myorders.php` |
| `cart/order_confirmation.php` | `user/myorders.php` + emailed PDF receipt |

Suggested answer: *"Those are from our first version, where the cart was saved in the database. We changed to a session cart, which only touches the database at checkout. The old files are no longer linked."* (Or delete them before the defense.)

---

## 7. Likely panel questions (short answers)

* **How do you prevent SQL injection?** Prepared statements with `?` placeholders everywhere user input reaches a query.
* **How are passwords stored?** As bcrypt hashes (`password_hash`), never plain text; checked with `password_verify`.
* **How do you stop a customer from opening admin pages?** `includes/adminCheck.php` checks `$_SESSION['role']` at the top of every admin file, not just hiding the menu links.
* **What if two customers buy the last item at the same time?** Checkout uses a transaction and `SELECT ... FOR UPDATE`, which locks the stock row until the first order commits; the second sees the new stock and is rolled back.
* **What happens to stock when an order is cancelled?** `admin/updateOrder.php` lines 46–51 add the quantities back in the same transaction.
* **Why store `price` in `order_items`?** So old orders keep the price paid, even if the product price changes later.
* **Who can review?** Only a customer with a *completed* order containing that product, checked on the page and again on the server ([review/store.php:14](review/store.php:14)).
* **How is the receipt sent?** FPDF builds the PDF in memory, and PHPMailer emails it as an attachment through Mailtrap SMTP.

### Weak points a panel might find (know them honestly)
* Some pages print database values without `htmlspecialchars()` (e.g. product names in `product/index.php`). Input is cleaned with `strip_tags()` when saved, which reduces the risk, but escaping on output would be the stronger fix.
* File uploads check the browser-reported MIME type (`$_FILES[...]['type']`), which can be faked. Checking the real file content (e.g. `getimagesize()`) would be stronger.
* Delete and activate/deactivate use GET links, with no confirmation or CSRF token.
