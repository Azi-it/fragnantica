<?php
session_start();
include('../includes/config.php');

// 1. Must be logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['message'] = 'please log in with a customer account';
    header("Location: ../user/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle order placement (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Verify customer delivery details
    $sql = "SELECT addressline, town, zipcode, phone FROM customer WHERE user_id = ? LIMIT 1";
    $result = mysqli_execute_query($conn, $sql, [$user_id]);
    $customer = mysqli_fetch_assoc($result);

    if (!$customer || empty(trim($customer['addressline'])) || empty(trim($customer['town'])) || empty(trim($customer['zipcode'])) || empty(trim($customer['phone']))) {
        $_SESSION['message'] = 'Please complete your delivery address in your profile before checking out.';
        header("Location: ../user/profile.php");
        exit();
    }

    $shipping_address = "{$customer['addressline']}, {$customer['town']}, {$customer['zipcode']} (Phone: {$customer['phone']})";

    // BEGIN TRANSACTION
    mysqli_begin_transaction($conn);

    try {
        // 1. Fetch current cart items
        $sql = "SELECT c.product_id, c.quantity, p.name, p.sell_price
                FROM cart c
                INNER JOIN product p USING (product_id)
                WHERE c.user_id = ?";
        $result = mysqli_execute_query($conn, $sql, [$user_id]);
        $items = mysqli_fetch_all($result, MYSQLI_ASSOC);

        if (empty($items)) {
            mysqli_rollback($conn);
            $_SESSION['message'] = 'Your cart is empty.';
            header("Location: index.php");
            exit();
        }

        $grandTotal = 0;

        // 2. Lock each stock row with SELECT ... FOR UPDATE and verify availability
        foreach ($items as $item) {
            $product_id = (int)$item['product_id'];
            $requestedQty = (int)$item['quantity'];

            $lockSql = "SELECT quantity FROM stock WHERE product_id = ? FOR UPDATE";
            $lockResult = mysqli_execute_query($conn, $lockSql, [$product_id]);
            $stockRow = mysqli_fetch_assoc($lockResult);

            $currentStock = $stockRow ? (int)$stockRow['quantity'] : 0;

            if ($currentStock < $requestedQty) {
                mysqli_rollback($conn);
                $safeName = htmlspecialchars($item['name'], ENT_QUOTES);
                $_SESSION['message'] = "Not enough stock for {$safeName}. Only {$currentStock} remaining.";
                header("Location: index.php");
                exit();
            }

            $grandTotal += $item['sell_price'] * $requestedQty;
        }

        // 3. Create the order
        $orderSql = "INSERT INTO orders (user_id, total_amount, status, shipping_address, created_at)
                     VALUES (?, ?, 'completed', ?, NOW())";
        mysqli_execute_query($conn, $orderSql, [$user_id, $grandTotal, $shipping_address]);
        $order_id = mysqli_insert_id($conn);

        // 4. Insert order_items and deduct stock
        $itemInsertSql = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        $itemInsertStmt = mysqli_prepare($conn, $itemInsertSql);

        $deductStockSql = "UPDATE stock SET quantity = quantity - ? WHERE product_id = ?";
        $deductStockStmt = mysqli_prepare($conn, $deductStockSql);

        foreach ($items as $item) {
            $product_id = (int)$item['product_id'];
            $quantity = (int)$item['quantity'];
            $price = (float)$item['sell_price'];

            mysqli_stmt_bind_param($itemInsertStmt, 'iiid', $order_id, $product_id, $quantity, $price);
            mysqli_stmt_execute($itemInsertStmt);

            mysqli_stmt_bind_param($deductStockStmt, 'ii', $quantity, $product_id);
            mysqli_stmt_execute($deductStockStmt);
        }

        // 5. Empty the customer's cart
        $clearCartSql = "DELETE FROM cart WHERE user_id = ?";
        mysqli_execute_query($conn, $clearCartSql, [$user_id]);

        // 6. COMMIT the transaction
        mysqli_commit($conn);

        $_SESSION['success'] = "Order #{$order_id} placed successfully!";
        header("Location: order_confirmation.php?id={$order_id}");
        exit();

    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = 'Checkout failed due to a database error. Please try again.';
        header("Location: index.php");
        exit();
    }
}

// GET: Display checkout review page
$sql = "SELECT c.product_id, c.quantity, p.name, p.brand, p.size, p.unit, p.sell_price, p.img_path,
               s.quantity AS stock
        FROM cart c
        INNER JOIN product p USING (product_id)
        INNER JOIN stock s USING (product_id)
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC";
$result = mysqli_execute_query($conn, $sql, [$user_id]);
$cartItems = mysqli_fetch_all($result, MYSQLI_ASSOC);

if (empty($cartItems)) {
    $_SESSION['message'] = 'Your cart is empty.';
    header("Location: index.php");
    exit();
}

// Fetch customer delivery details
$customerSql = "SELECT u.name, u.email, c.addressline, c.town, c.zipcode, c.phone
                FROM users u
                LEFT JOIN customer c USING (user_id)
                WHERE u.user_id = ? LIMIT 1";
$customerResult = mysqli_execute_query($conn, $customerSql, [$user_id]);
$customer = mysqli_fetch_assoc($customerResult);

$hasDeliveryDetails = !empty($customer['addressline']) && !empty($customer['town']) && !empty($customer['zipcode']) && !empty($customer['phone']);

$hasStockIssue = false;
$grandTotal = 0;
foreach ($cartItems as $item) {
    $grandTotal += $item['sell_price'] * $item['quantity'];
    if ($item['quantity'] > $item['stock']) {
        $hasStockIssue = true;
    }
}

include('../includes/header.php');
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <h2>Checkout Review</h2>

    <div class="row mt-4">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Order Summary</strong>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cartItems as $item):
                            $lineTotal = $item['sell_price'] * $item['quantity'];
                            $name = htmlspecialchars($item['name'], ENT_QUOTES);
                            $brand = htmlspecialchars($item['brand'], ENT_QUOTES);
                            $img = htmlspecialchars($item['img_path'], ENT_QUOTES);
                            $stockWarning = $item['quantity'] > $item['stock']
                                ? "<br><small class='text-danger'>Only {$item['stock']} available in stock</small>" : '';
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="../product/<?= $img ?>" width="50" height="50" class="me-2 rounded" alt="<?= $name ?>">
                                        <div>
                                            <strong><?= $name ?></strong><br>
                                            <small class="text-muted"><?= $brand ?> &middot; <?= $item['size'] ?> <?= $item['unit'] ?></small>
                                            <?= $stockWarning ?>
                                        </div>
                                    </div>
                                </td>
                                <td>&#8369; <?= number_format($item['sell_price'], 2) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>&#8369; <?= number_format($lineTotal, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th>&#8369; <?= number_format($grandTotal, 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <a href="index.php" class="btn btn-secondary">&larr; Back to Cart</a>
        </div>

        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Delivery Information</strong>
                    <a href="../user/profile.php" class="btn btn-sm btn-outline-primary">Edit Profile</a>
                </div>
                <div class="card-body">
                    <?php if ($hasDeliveryDetails): ?>
                        <p class="mb-1"><strong>Recipient:</strong> <?= htmlspecialchars($customer['name'], ENT_QUOTES) ?></p>
                        <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($customer['email'], ENT_QUOTES) ?></p>
                        <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($customer['phone'], ENT_QUOTES) ?></p>
                        <p class="mb-1"><strong>Address:</strong> <?= htmlspecialchars($customer['addressline'], ENT_QUOTES) ?></p>
                        <p class="mb-3"><strong>Town / Zipcode:</strong> <?= htmlspecialchars($customer['town'], ENT_QUOTES) ?>, <?= htmlspecialchars($customer['zipcode'], ENT_QUOTES) ?></p>

                        <?php if ($hasStockIssue): ?>
                            <div class="alert alert-warning mb-3">
                                Some items exceed available stock. Please adjust quantities in your cart before placing order.
                            </div>
                            <button class="btn btn-success w-100 btn-lg" disabled>Place Order</button>
                        <?php else: ?>
                            <form action="checkout.php" method="POST">
                                <button type="submit" name="place_order" class="btn btn-success w-100 btn-lg">
                                    <i class="fa-solid fa-check"></i> Place Order (&#8369; <?= number_format($grandTotal, 2) ?>)
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>Missing Delivery Address!</strong><br>
                            You must fill in your delivery details before placing an order.
                        </div>
                        <a href="../user/profile.php" class="btn btn-primary w-100">
                            Complete Delivery Details
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
