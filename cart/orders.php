<?php
session_start();
include('../includes/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['message'] = 'please log in with a customer account';
    header("Location: ../user/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all orders for this customer with item count
$sql = "SELECT o.order_id, o.total_amount, o.status, o.shipping_address, o.created_at,
               COUNT(oi.order_item_id) AS item_count,
               SUM(oi.quantity) AS total_units
        FROM orders o
        LEFT JOIN order_items oi USING (order_id)
        WHERE o.user_id = ?
        GROUP BY o.order_id
        ORDER BY o.created_at DESC";
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
                        <th>Total Amount</th>
                        <th>Delivery Address</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong>#<?= $order['order_id'] ?></strong></td>
                        <td><?= date('M j, Y, g:i a', strtotime($order['created_at'])) ?></td>
                        <td><?= $order['total_units'] ?? 0 ?> units (<?= $order['item_count'] ?> products)</td>
                        <td><strong>&#8369; <?= number_format($order['total_amount'], 2) ?></strong></td>
                        <td><small><?= htmlspecialchars($order['shipping_address'], ENT_QUOTES) ?></small></td>
                        <td><span class="badge bg-success"><?= ucfirst($order['status']) ?></span></td>
                        <td>
                            <a href="order_confirmation.php?id=<?= $order['order_id'] ?>" class="btn btn-outline-primary btn-sm">
                                View Receipt
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
