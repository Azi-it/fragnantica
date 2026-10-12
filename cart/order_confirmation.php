<?php
session_start();
include('../includes/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['message'] = 'please log in with a customer account';
    header("Location: ../user/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$order_id) {
    header("Location: index.php");
    exit();
}

// Fetch order scoped to current user
$orderSql = "SELECT order_id, total_amount, status, shipping_address, created_at
             FROM orders
             WHERE order_id = ? AND user_id = ?
             LIMIT 1";
$orderResult = mysqli_execute_query($conn, $orderSql, [$order_id, $user_id]);
$order = mysqli_fetch_assoc($orderResult);

if (!$order) {
    $_SESSION['message'] = 'Order not found.';
    header("Location: index.php");
    exit();
}

// Fetch order items
$itemsSql = "SELECT oi.quantity, oi.price, p.name, p.brand, p.size, p.unit, p.img_path
             FROM order_items oi
             INNER JOIN product p USING (product_id)
             WHERE oi.order_id = ?";
$itemsResult = mysqli_execute_query($conn, $itemsSql, [$order_id]);
$items = mysqli_fetch_all($itemsResult, MYSQLI_ASSOC);

include('../includes/header.php');
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>

    <div class="card border-success mx-auto" style="max-width: 800px;">
        <div class="card-header bg-success text-white py-3">
            <h4 class="mb-0"><i class="fa-solid fa-circle-check me-2"></i>Order Confirmed!</h4>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Order ID:</strong> #<?= $order['order_id'] ?></p>
                    <p class="mb-1"><strong>Date:</strong> <?= date('F j, Y, g:i a', strtotime($order['created_at'])) ?></p>
                    <p class="mb-1"><strong>Status:</strong> <span class="badge bg-success"><?= ucfirst($order['status']) ?></span></p>
                </div>
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Shipping Details:</strong></p>
                    <p class="text-muted mb-0"><?= htmlspecialchars($order['shipping_address'], ENT_QUOTES) ?></p>
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
                <?php foreach ($items as $item):
                    $subtotal = $item['price'] * $item['quantity'];
                    $name = htmlspecialchars($item['name'], ENT_QUOTES);
                    $brand = htmlspecialchars($item['brand'], ENT_QUOTES);
                    $img = htmlspecialchars($item['img_path'], ENT_QUOTES);
                ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="../product/<?= $img ?>" width="50" height="50" class="me-2 rounded" alt="<?= $name ?>">
                                <div>
                                    <strong><?= $name ?></strong><br>
                                    <small class="text-muted"><?= $brand ?> &middot; <?= $item['size'] ?> <?= $item['unit'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td>&#8369; <?= number_format($item['price'], 2) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td>&#8369; <?= number_format($subtotal, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3" class="text-end">Total Amount Paid:</th>
                        <th>&#8369; <?= number_format($order['total_amount'], 2) ?></th>
                    </tr>
                </tfoot>
            </table>

            <div class="d-flex justify-content-between mt-4">
                <a href="../index.php" class="btn btn-secondary">Continue Shopping</a>
                <a href="orders.php" class="btn btn-primary"><i class="fa-solid fa-box me-1"></i> View All Orders</a>
            </div>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
