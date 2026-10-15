<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['message'] = 'please log in with a customer account';
    header("Location: ../user/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$order_id) {
    header("Location: ../index.php");
    exit();
}

// Fetch order scoped to current user
$orderSql = "SELECT order_id, total_amount, status, shipping_address, payment_method, payment_status, paid_at, created_at
             FROM orders
             WHERE order_id = ? AND user_id = ?
             LIMIT 1";
$orderResult = mysqli_execute_query($conn, $orderSql, [$order_id, $user_id]);
$order = mysqli_fetch_assoc($orderResult);

if (!$order) {
    $_SESSION['message'] = 'Order not found.';
    header("Location: ../index.php");
    exit();
}

// Fetch order items
$itemsSql = "SELECT oi.product_id, oi.quantity, oi.price, p.name, p.brand, p.size, p.unit, p.img_path
             FROM order_items oi
             INNER JOIN product p USING (product_id)
             WHERE oi.order_id = ?";
$itemsResult = mysqli_execute_query($conn, $itemsSql, [$order_id]);
$items = mysqli_fetch_all($itemsResult, MYSQLI_ASSOC);

// Status history, oldest first
$historySql = "SELECT status, note, changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at, history_id";
$history = mysqli_fetch_all(mysqli_execute_query($conn, $historySql, [$order_id]), MYSQLI_ASSOC);

$canCancel = in_array('cancelled', allowed_next_statuses($order['status'], 'customer'), true);
$isPaid = $order['payment_status'] === 'paid';

include('../includes/header.php');
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>

    <div class="card mx-auto" style="max-width: 860px;">
        <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h4 class="mb-0">Order #<?= (int)$order['order_id'] ?></h4>
            <div><?= status_badge($order['status']) ?></div>
        </div>
        <div class="card-body">
            <?= order_progress($order['status']) ?>

            <div class="row mb-3">
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Placed:</strong> <?= date('F j, Y, g:i a', strtotime($order['created_at'])) ?></p>
                    <p class="mb-1"><strong>Payment:</strong> <?= e(payment_label($order['payment_method'])) ?> <?= payment_badge($order['payment_status']) ?></p>
                    <?php if ($isPaid && $order['paid_at']): ?>
                        <p class="mb-1 small text-muted">Paid on <?= date('F j, Y', strtotime($order['paid_at'])) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Shipping Details:</strong></p>
                    <p class="text-muted mb-0"><?= e($order['shipping_address']) ?></p>
                </div>
            </div>

            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="../product/<?= e($item['img_path']) ?>" width="50" height="50" class="me-2 rounded product-photo" alt="<?= e($item['name']) ?>">
                                <div>
                                    <a href="../product/view.php?id=<?= (int)$item['product_id'] ?>"><strong><?= e($item['name']) ?></strong></a><br>
                                    <small class="text-muted"><?= e($item['brand']) ?> &middot; <?= (int)$item['size'] ?> <?= e($item['unit']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= peso($item['price']) ?></td>
                        <td><?= (int)$item['quantity'] ?></td>
                        <td><?= peso($item['price'] * $item['quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end">
                            <?php
                            if ($order['status'] === 'cancelled') {
                                echo 'Order Total (cancelled):';
                            } else if ($isPaid) {
                                echo 'Total Paid:';
                            } else {
                                echo 'Amount Due on Delivery:';
                            }
                            ?>
                        </th>
                        <th><?= peso($order['total_amount']) ?></th>
                    </tr>
                </tfoot>
            </table>

            <?php if ($history): ?>
                <h6 class="mt-4">Order History</h6>
                <ul class="order-history list-unstyled mb-0">
                    <?php foreach ($history as $h): ?>
                        <li>
                            <span class="text-muted small"><?= date('M j, Y, g:i a', strtotime($h['changed_at'])) ?></span>
                            <?= status_badge($h['status']) ?>
                            <?php if ($h['note'] !== null && $h['note'] !== ''): ?>
                                <span class="small"><?= e($h['note']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="d-flex flex-wrap justify-content-between gap-2 mt-4">
                <?php if ($canCancel): ?>
                    <form action="cancel_order.php" method="POST" onsubmit="return confirm('Cancel order #<?= (int)$order['order_id'] ?>? This cannot be undone.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                        <button type="submit" class="btn btn-outline-danger"><i class="fa-solid fa-xmark me-1"></i> Cancel Order</button>
                    </form>
                <?php endif; ?>
                <a href="../index.php" class="btn btn-primary">Continue Shopping</a>
            </div>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
