<?php
// shared helpers: login checks, output escaping, form helpers, CSRF protection,
// order statuses and payment methods
// include this after config.php (it needs $conn for the order functions)

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/profanity.php';

// ---------------------------------------------------------------
// login and access control
//   require_login()    any logged in user
//   require_customer() customers only (cart, checkout, reviews)
//   require_admin()    admins only (every CRUD page and script)
// a visitor who is not logged in is sent to the login page with a message,
// and comes back to the page they wanted after logging in.
// a logged in customer who opens an admin page is sent to the home page.
// ---------------------------------------------------------------

// send the visitor to the login page with a message, remembering where they wanted to go
function redirect_to_login($message)
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SERVER['REQUEST_URI'])) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'];
    }
    // keep a message that is already waiting (e.g. "your account was deactivated")
    if (empty($_SESSION['message'])) {
        $_SESSION['message'] = $message;
    }
    header('Location: ' . BASE_PATH . '/user/login.php');
    exit();
}

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        redirect_to_login('Please log in to continue.');
    }
}

function require_customer()
{
    if (!isset($_SESSION['user_id'])) {
        redirect_to_login('Please log in with your customer account to continue.');
    }
    if ($_SESSION['role'] !== 'customer') {
        $_SESSION['message'] = 'That page is for customers. Admin accounts cannot shop or write reviews.';
        header('Location: ' . BASE_PATH . '/index.php');
        exit();
    }
}

function require_admin()
{
    if (!isset($_SESSION['user_id'])) {
        redirect_to_login('Please log in with an admin account to open that page.');
    }
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        $_SESSION['message'] = 'Access denied. Only administrators can open that page.';
        header('Location: ' . BASE_PATH . '/index.php');
        exit();
    }
}

function is_admin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_customer()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
}

// the page to open after logging in: the page the user was sent away from, if it is one of ours
function after_login_url($role)
{
    $target = isset($_SESSION['after_login']) ? (string)$_SESSION['after_login'] : '';
    unset($_SESSION['after_login']);
    if ($target !== '' && strpos($target, BASE_PATH . '/') === 0 && strpos($target, '//') === false
        && !preg_match('/[\r\n]/', $target) && strpos($target, '/user/login.php') === false) {
        return $target;
    }
    return BASE_PATH . ($role === 'admin' ? '/admin/index.php' : '/index.php');
}

// read the logged in user again on every page, so a change made by an admin
// (deactivated account, new role, new name or photo) takes effect straight away
function sync_session_user($conn)
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    try {
        $sql = "SELECT name, email, img_path, role, status FROM users WHERE user_id = ? LIMIT 1";
        $user = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$_SESSION['user_id']]));
    } catch (mysqli_sql_exception $e) {
        die('The database is out of date. In phpMyAdmin, import <b>migrations/003_users_photos_reviews_views.sql</b> and reload this page.');
    }

    if (!$user || $user['status'] !== 'active') {
        $_SESSION = array();
        session_regenerate_id(true);
        $_SESSION['message'] = 'Your account has been deactivated, so you have been logged out. Please contact the store if you think this is a mistake.';
        return;
    }
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['photo'] = $user['img_path'];
}

// ---------------------------------------------------------------
// form helpers
// validation is done in PHP only; forms use "novalidate" so the browser does not check them
// ---------------------------------------------------------------

// a submitted text field, trimmed; '' when it is missing or not text
function post_value($key)
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

// after a failed submit: remember the errors and what was typed, for the form to show once
function keep_form($form, $errors, $old)
{
    $_SESSION['forms'][$form] = array('errors' => $errors, 'old' => $old);
}

// take back what keep_form() remembered (and forget it): array($errors, $old)
function take_form($form)
{
    $saved = isset($_SESSION['forms'][$form]) ? $_SESSION['forms'][$form] : array('errors' => array(), 'old' => array());
    unset($_SESSION['forms'][$form]);
    return array($saved['errors'], $saved['old']);
}

// " is-invalid" for a field with an error (Bootstrap shows a red border)
function invalid_class($errors, $field)
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

// the error message under a field
function field_error($errors, $field)
{
    return isset($errors[$field]) ? "<div class='invalid-feedback d-block'>" . e($errors[$field]) . "</div>" : '';
}

// check a user's name and email; $ignoreUserId lets a user keep their own email when editing.
// returns the errors as array(field => message)
function user_field_errors($conn, $name, $email, $ignoreUserId = 0)
{
    $errors = array();
    if ($name === '') {
        $errors['name'] = 'Please enter a name.';
    } else if (mb_strlen($name) > 100) {
        $errors['name'] = 'The name should be 100 characters or less.';
    }
    if ($email === '') {
        $errors['email'] = 'Please enter an email address.';
    } else if (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Please enter a valid email address.';
    } else {
        $sql = "SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1";
        if (mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$email, (int)$ignoreUserId]))) {
            $errors['email'] = 'This email is already used by another account.';
        }
    }
    return $errors;
}

// ---------------------------------------------------------------
// pictures
// ---------------------------------------------------------------

// a user's profile photo, or a circle with their initials when they have none
function avatar($name, $imgPath, $size = 40)
{
    $size = (int)$size;
    if ($imgPath !== null && $imgPath !== '') {
        return "<img src='" . e(BASE_PATH . '/' . $imgPath) . "' alt='' class='avatar' width='{$size}' height='{$size}'>";
    }
    $initials = '';
    foreach (array_slice(preg_split('/\s+/', trim((string)$name)), 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    $font = round($size * 0.4);
    return "<span class='avatar avatar-initials' style='width:{$size}px;height:{$size}px;font-size:{$font}px;' aria-hidden='true'>" . e($initials) . "</span>";
}

// 1 to 5 stars; halves are shown for averages like 4.5
function stars($rating)
{
    $rating = (float)$rating;
    $html = "<span class='stars' title='" . e(number_format($rating, 1)) . " out of 5'>";
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= "<i class='fa-solid fa-star'></i>";
        } else if ($rating >= $i - 0.5) {
            $html .= "<i class='fa-solid fa-star-half-stroke'></i>";
        } else {
            $html .= "<i class='fa-regular fa-star'></i>";
        }
    }
    return $html . "<span class='visually-hidden'>" . e(number_format($rating, 1)) . " out of 5 stars</span></span>";
}

// ---------------------------------------------------------------
// output
// ---------------------------------------------------------------

// escape any value before printing it into HTML
function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// format a price in pesos, e.g. "&#8369; 1,250.00"
function peso($amount)
{
    return '&#8369; ' . number_format((float)$amount, 2);
}

// ---------------------------------------------------------------
// links and pagination
// ---------------------------------------------------------------

// link to the current page, keeping only the listed query parameters and applying $changes
// e.g. url_with(array('page' => 2), array('search', 'sort')) -> "?search=oud&sort=price_asc&page=2"
function url_with($changes, $keep)
{
    $params = array();
    foreach ($keep as $key) {
        if (isset($_GET[$key]) && is_string($_GET[$key]) && $_GET[$key] !== '') {
            $params[$key] = $_GET[$key];
        }
    }
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    return '?' . http_build_query($params);
}

// Bootstrap pagination: Previous, page numbers around the current page, Next
function pagination($page, $totalPages, $keep)
{
    if ($totalPages <= 1) {
        return '';
    }

    $html = "<nav aria-label='Pages'><ul class='pagination justify-content-center flex-wrap'>";

    $prev = $page > 1 ? e(url_with(array('page' => $page - 1), $keep)) : '#';
    $html .= "<li class='page-item" . ($page <= 1 ? " disabled" : "") . "'><a class='page-link' href='{$prev}'>&laquo; Previous</a></li>";

    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($start > 1) {
        $html .= "<li class='page-item'><a class='page-link' href='" . e(url_with(array('page' => 1), $keep)) . "'>1</a></li>";
        if ($start > 2) {
            $html .= "<li class='page-item disabled'><span class='page-link'>&hellip;</span></li>";
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            $html .= "<li class='page-item active' aria-current='page'><span class='page-link'>{$i}</span></li>";
        } else {
            $html .= "<li class='page-item'><a class='page-link' href='" . e(url_with(array('page' => $i), $keep)) . "'>{$i}</a></li>";
        }
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= "<li class='page-item disabled'><span class='page-link'>&hellip;</span></li>";
        }
        $html .= "<li class='page-item'><a class='page-link' href='" . e(url_with(array('page' => $totalPages), $keep)) . "'>{$totalPages}</a></li>";
    }

    $next = $page < $totalPages ? e(url_with(array('page' => $page + 1), $keep)) : '#';
    $html .= "<li class='page-item" . ($page >= $totalPages ? " disabled" : "") . "'><a class='page-link' href='{$next}'>Next &raquo;</a></li>";

    return $html . "</ul></nav>";
}

// ---------------------------------------------------------------
// CSRF protection
// every form that changes data carries a secret token from the session,
// so another website cannot submit the form on the user's behalf
// ---------------------------------------------------------------

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid()
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// ---------------------------------------------------------------
// payment methods
// to add an online gateway later (e.g. PayMongo for GCash and cards),
// add an entry here and handle it in cart/checkout.php
// ---------------------------------------------------------------

function payment_methods()
{
    return array(
        'cod' => array(
            'label' => 'Cash on Delivery',
            'description' => 'Pay in cash when your order arrives.',
            'icon' => 'fa-solid fa-money-bill-wave',
        ),
    );
}

function payment_label($code)
{
    $methods = payment_methods();
    return isset($methods[$code]) ? $methods[$code]['label'] : ucfirst((string)$code);
}

function payment_badge($paymentStatus)
{
    if ($paymentStatus === 'paid') {
        return "<span class='badge bg-success'>Paid</span>";
    }
    return "<span class='badge bg-light text-dark border'>Unpaid</span>";
}

// ---------------------------------------------------------------
// order statuses
// ---------------------------------------------------------------

function order_statuses()
{
    return array(
        'pending'    => array('label' => 'Pending',    'badge' => 'bg-warning text-dark'),
        'processing' => array('label' => 'Processing', 'badge' => 'bg-info text-dark'),
        'shipped'    => array('label' => 'Shipped',    'badge' => 'bg-primary'),
        'completed'  => array('label' => 'Completed',  'badge' => 'bg-success'),
        'cancelled'  => array('label' => 'Cancelled',  'badge' => 'bg-secondary'),
    );
}

function status_label($status)
{
    $statuses = order_statuses();
    return isset($statuses[$status]) ? $statuses[$status]['label'] : ucfirst((string)$status);
}

function status_badge($status)
{
    $statuses = order_statuses();
    $class = isset($statuses[$status]) ? $statuses[$status]['badge'] : 'bg-secondary';
    return "<span class='badge {$class}'>" . e(status_label($status)) . "</span>";
}

// which statuses an order may move to next
//   admin:    pending -> processing -> shipped -> completed, and cancel before shipping
//   customer: may only cancel while the order is still pending
function allowed_next_statuses($status, $role)
{
    if ($role === 'customer') {
        return $status === 'pending' ? array('cancelled') : array();
    }

    $flow = array(
        'pending'    => array('processing', 'cancelled'),
        'processing' => array('shipped', 'cancelled'),
        'shipped'    => array('completed'),
        'completed'  => array(),
        'cancelled'  => array(),
    );
    return isset($flow[$status]) ? $flow[$status] : array();
}

// the progress bar shown on order pages (Placed > Processing > Shipped > Completed)
function order_progress($status)
{
    if ($status === 'cancelled') {
        return "<div class='alert alert-secondary mb-3'><i class='fa-solid fa-ban me-2'></i>This order was cancelled. Any items were returned to stock.</div>";
    }

    $steps = array('pending' => 'Placed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'completed' => 'Completed');
    $keys = array_keys($steps);
    $current = array_search($status, $keys, true);

    $html = "<ol class='order-progress mb-3'>";
    foreach ($keys as $i => $key) {
        if ($current !== false && $i < $current) {
            $state = 'done';
        } else if ($i === $current) {
            $state = 'current';
        } else {
            $state = '';
        }
        $html .= "<li class='{$state}'><span class='dot'></span>" . e($steps[$key]) . "</li>";
    }
    return $html . "</ol>";
}

// record one line in the order's status history
function record_status($conn, $order_id, $status, $note, $changed_by)
{
    $sql = "INSERT INTO order_status_history (order_id, status, note, changed_by, changed_at) VALUES (?, ?, ?, ?, NOW())";
    mysqli_execute_query($conn, $sql, [$order_id, $status, $note, $changed_by]);
}

// change an order's status, enforcing the allowed flow
// - cancelling puts every item back into stock
// - completing a cash on delivery order marks it as paid (the cash was collected)
// returns array(true, message) or array(false, error message)
function change_order_status($conn, $order_id, $newStatus, $actor_id, $role, $note = '')
{
    $note = trim((string)$note);
    if (strlen($note) > 255) {
        return array(false, 'The note should be 255 characters or less.');
    }

    mysqli_begin_transaction($conn);

    try {
        // lock the order row so two people cannot change it at the same time
        $sql = "SELECT order_id, user_id, status, payment_method, payment_status FROM orders WHERE order_id = ? FOR UPDATE";
        $result = mysqli_execute_query($conn, $sql, [$order_id]);
        $order = mysqli_fetch_assoc($result);

        // customers can only touch their own orders
        if (!$order || ($role === 'customer' && (int)$order['user_id'] !== (int)$actor_id)) {
            mysqli_rollback($conn);
            return array(false, 'Order not found.');
        }

        if (!in_array($newStatus, allowed_next_statuses($order['status'], $role), true)) {
            mysqli_rollback($conn);
            return array(false, 'This order cannot be changed from ' . status_label($order['status']) . ' to ' . status_label($newStatus) . '.');
        }

        if ($newStatus === 'cancelled') {
            // return every item to stock
            $sql = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
            $items = mysqli_fetch_all(mysqli_execute_query($conn, $sql, [$order_id]), MYSQLI_ASSOC);
            foreach ($items as $item) {
                $sql = "UPDATE stock SET quantity = quantity + ? WHERE product_id = ?";
                mysqli_execute_query($conn, $sql, [(int)$item['quantity'], (int)$item['product_id']]);
            }
        }

        if ($newStatus === 'completed' && $order['payment_method'] === 'cod' && $order['payment_status'] !== 'paid') {
            $sql = "UPDATE orders SET status = ?, payment_status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE order_id = ?";
        } else {
            $sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
        }
        mysqli_execute_query($conn, $sql, [$newStatus, $order_id]);

        if ($note === '') {
            $note = $role === 'customer' ? 'Cancelled by customer' : 'Status updated';
        }
        record_status($conn, $order_id, $newStatus, $note, $actor_id);

        mysqli_commit($conn);
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn);
        return array(false, 'Could not update the order. Please try again.');
    }

    if ($newStatus === 'cancelled') {
        return array(true, "Order #{$order_id} was cancelled and its items were returned to stock.");
    }
    return array(true, "Order #{$order_id} is now " . status_label($newStatus) . ".");
}

// ---------------------------------------------------------------
// reviews
// ---------------------------------------------------------------

// a customer may review a product once they have received it (a completed order with that product)
function can_review_product($conn, $user_id, $product_id)
{
    $sql = "SELECT 1 FROM order_details_view WHERE user_id = ? AND product_id = ? AND status = 'completed' LIMIT 1";
    return (bool)mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$user_id, $product_id]));
}

// ---------------------------------------------------------------
// every page that includes this file checks the logged in account again
// ---------------------------------------------------------------
if (session_status() === PHP_SESSION_ACTIVE && isset($conn) && $conn instanceof mysqli) {
    sync_session_user($conn);
}
