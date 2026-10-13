<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

require_customer();

$user_id = $_SESSION['user_id'];

// all orders of this customer with their item count and grand total (order_summary_view view)
$sql = "SELECT order_id, grand_total, status, payment_method, payment_status, created_at, item_count, total_units
        FROM order_summary_view
        WHERE user_id = ?
        ORDER BY created_at DESC, order_id DESC";
$result = mysqli_execute_query($conn, $sql, [$user_id]);
$orders = mysqli_fetch_all($result, MYSQLI_ASSOC);

include('../includes/header.php');
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <h2>My Orders</h2>

    <?php if (empty($orders)): ?>
        <p class="mt-3">You haven't placed any orders yet. <a href="../index.php">Browse products</a></p>
    <?php else: ?>
        <div class="table-responsive mt-3">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong>#<?= (int)$order['order_id'] ?></strong></td>
                        <td><?= date('M j, Y, g:i a', strtotime($order['created_at'])) ?></td>
                        <td><?= (int)($order['total_units'] ?? 0) ?> units (<?= (int)$order['item_count'] ?> products)</td>
                        <td><strong><?= peso($order['grand_total']) ?></strong></td>
                        <td><small><?= e(payment_label($order['payment_method'])) ?></small><br><?= payment_badge($order['payment_status']) ?></td>
                        <td><?= status_badge($order['status']) ?></td>
                        <td>
                            <a href="order_confirmation.php?id=<?= (int)$order['order_id'] ?>" class="btn btn-outline-primary btn-sm">
                                View Order
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a href="../index.php" class="btn btn-secondary mt-2">Continue Shopping</a>
    <?php endif; ?>
</div>
<?php include('../includes/footer.php'); ?>
