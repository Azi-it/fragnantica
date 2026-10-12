<?php
// shared helpers: output escaping, CSRF protection, order statuses and payment methods
// include this after config.php (it needs $conn for the order functions)

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/mail.php';

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
