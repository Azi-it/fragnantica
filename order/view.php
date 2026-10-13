<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// only admins can manage orders
require_admin();

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id) {
    header("Location: index.php");
    exit();
}

// the order, its customer and totals (order_summary_view view)
$sql = "SELECT * FROM order_summary_view WHERE order_id = ? LIMIT 1";
$order = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$order_id]));

if (!$order) {
    $_SESSION['message'] = 'Order not found.';
    header("Location: index.php");
    exit();
}

// one row per product with its subtotal (order_details_view view), plus the stock left now
$itemsSql = "SELECT d.product_id, d.quantity, d.price, d.subtotal, d.product_name AS name, d.brand, d.size, d.unit, d.img_path, s.quantity AS stock
             FROM order_details_view d
             LEFT JOIN stock s USING (product_id)
             WHERE d.order_id = ?
             ORDER BY d.order_item_id";
$items = mysqli_fetch_all(mysqli_execute_query($conn, $itemsSql, [$order_id]), MYSQLI_ASSOC);

$historySql = "SELECT h.status, h.note, h.changed_at, u.name AS changed_by_name, u.role AS changed_by_role
               FROM order_status_history h
               LEFT JOIN users u ON u.user_id = h.changed_by
               WHERE h.order_id = ?
               ORDER BY h.changed_at, h.history_id";
$history = mysqli_fetch_all(mysqli_execute_query($conn, $historySql, [$order_id]), MYSQLI_ASSOC);

$next = allowed_next_statuses($order['status'], 'admin');

// wording and colour of each action button
$actions = array(
    'processing' => array('label' => 'Start Processing', 'class' => 'btn-info', 'icon' => 'fa-solid fa-gears'),
    'shipped'    => array('label' => 'Mark as Shipped', 'class' => 'btn-primary', 'icon' => 'fa-solid fa-truck'),
    'completed'  => array('label' => 'Mark as Completed', 'class' => 'btn-success', 'icon' => 'fa-solid fa-circle-check'),
    'cancelled'  => array('label' => 'Cancel Order', 'class' => 'btn-outline-danger', 'icon' => 'fa-solid fa-xmark'),
);

include('../includes/header.php');
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <a href="index.php" class="btn btn-link px-0">&larr; All orders</a>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0">Order #<?= (int)$order['order_id'] ?></h2>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?= status_badge($order['status']) ?> <?= payment_badge($order['payment_status']) ?>
            <a href="receipt.php?id=<?= (int)$order['order_id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="fa-regular fa-file-pdf me-1"></i> Receipt (PDF)</a>
        </div>
    </div>

    <?= order_progress($order['status']) ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><strong>Items</strong></div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th><th>In stock now</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="../product/<?= e($item['img_path']) ?>" width="50" height="50" class="me-2 rounded product-photo" alt="<?= e($item['name']) ?>">
                                        <div>
                                            <strong><?= e($item['name']) ?></strong><br>
                                            <small class="text-muted"><?= e($item['brand']) ?> &middot; <?= (int)$item['size'] ?> <?= e($item['unit']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= peso($item['price']) ?></td>
                                <td><?= (int)$item['quantity'] ?></td>
                                <td><?= peso($item['subtotal']) ?></td>
                                <td><?= (int)$item['stock'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr><th colspan="3" class="text-end">Grand Total</th><th colspan="2"><?= peso($order['grand_total']) ?></th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>History</strong></div>
                <div class="card-body">
                    <?php if (empty($history)): ?>
                        <p class="text-muted mb-0">No history recorded.</p>
                    <?php else: ?>
                        <ul class="order-history list-unstyled mb-0">
                            <?php foreach ($history as $h): ?>
                                <li>
                                    <span class="text-muted small"><?= date('M j, Y, g:i a', strtotime($h['changed_at'])) ?></span>
                                    <?= status_badge($h['status']) ?>
                                    <?php if ($h['note'] !== null && $h['note'] !== ''): ?>
                                        <span class="small"><?= e($h['note']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($h['changed_by_name']): ?>
                                        <span class="small text-muted">by <?= e($h['changed_by_name']) ?> (<?= e($h['changed_by_role']) ?>)</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><strong>Customer</strong></div>
                <div class="card-body">
                    <p class="mb-1"><?= e($order['customer_name']) ?></p>
                    <p class="mb-2"><a href="mailto:<?= e($order['customer_email']) ?>"><?= e($order['customer_email']) ?></a></p>
                    <p class="small text-muted mb-0"><?= e($order['shipping_address']) ?></p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><strong>Payment</strong></div>
                <div class="card-body">
                    <p class="mb-1"><?= e(payment_label($order['payment_method'])) ?> <?= payment_badge($order['payment_status']) ?></p>
                    <?php if ($order['payment_status'] === 'paid' && $order['paid_at']): ?>
                        <p class="small text-muted mb-0">Paid on <?= date('M j, Y, g:i a', strtotime($order['paid_at'])) ?></p>
                    <?php elseif ($order['payment_method'] === 'cod' && $order['status'] !== 'cancelled'): ?>
                        <p class="small text-muted mb-0">Collect <?= peso($order['grand_total']) ?> on delivery. It is marked paid when you complete the order.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Update Status</strong></div>
                <div class="card-body">
                    <?php if (empty($next)): ?>
                        <p class="text-muted mb-0">This order is <?= e(strtolower(status_label($order['status']))) ?>. No further changes are possible.</p>
                    <?php else: ?>
                        <form action="update_status.php" method="POST" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                            <p class="small text-muted">The customer is emailed the new status, the products with their subtotals and the grand total, and the PDF receipt.</p>
                            <label for="note" class="form-label small">Note (optional, the customer can see it)</label>
                            <input type="text" class="form-control mb-3" id="note" name="note" placeholder="e.g. Shipped via LBC, tracking 1234">
                            <div class="d-grid gap-2">
                                <?php foreach ($next as $target):
                                    $a = $actions[$target];
                                    $confirm = $target === 'cancelled'
                                        ? " onclick=\"return confirm('Cancel this order and return its items to stock?');\"" : '';
                                ?>
                                    <button type="submit" name="status" value="<?= e($target) ?>" class="btn <?= $a['class'] ?>"<?= $confirm ?>>
                                        <i class="<?= $a['icon'] ?> me-1"></i> <?= e($a['label']) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
