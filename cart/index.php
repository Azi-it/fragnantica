<?php
session_start();
include('../includes/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['message'] = 'please log in with a customer account';
    header("Location: ../user/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT c.product_id, c.quantity, p.name, p.brand, p.size, p.unit, p.sell_price, p.img_path,
               s.quantity AS stock
        FROM cart c
        INNER JOIN product p USING (product_id)
        INNER JOIN stock s USING (product_id)
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC";
$result = mysqli_execute_query($conn, $sql, [$user_id]);
$itemCount = mysqli_num_rows($result);
$grandTotal = 0;

include('../includes/header.php');
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <h2>My Cart</h2>

    <?php if ($itemCount === 0) { ?>
        <p>Your cart is empty. <a href="../index.php">Browse products</a></p>
    <?php } else { ?>
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>Photo</th><th>Product</th><th>Price</th>
                    <th>Qty</th><th>Subtotal</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            while ($row = mysqli_fetch_assoc($result)) {
                $lineTotal = $row['sell_price'] * $row['quantity'];
                $grandTotal += $lineTotal;

                $name = htmlspecialchars($row['name'], ENT_QUOTES);
                $brand = htmlspecialchars($row['brand'], ENT_QUOTES);
                $img = htmlspecialchars($row['img_path'], ENT_QUOTES);
                $warning = $row['quantity'] > $row['stock']
                    ? "<br><small class='text-danger'>only {$row['stock']} left in stock</small>" : '';

                echo "<tr>
                    <td><img src='../product/{$img}' width='70' height='70' class='product-photo' alt='{$name}'></td>
                    <td><strong>{$name}</strong><br>{$brand} &middot; {$row['size']} {$row['unit']}</td>
                    <td>&#8369; " . number_format($row['sell_price'], 2) . "</td>
                    <td>{$row['quantity']}{$warning}</td>
                    <td>&#8369; " . number_format($lineTotal, 2) . "</td>
                    <td>
                        <form action='remove.php' method='POST'>
                            <input type='hidden' name='product_id' value='{$row['product_id']}'>
                            <button type='submit' name='submit' class='btn btn-outline-danger btn-sm'>Remove</button>
                        </form>
                    </td>
                </tr>";
            }
            ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-end">Total</th>
                    <th colspan="2">&#8369; <?= number_format($grandTotal, 2) ?></th>
                </tr>
            </tfoot>
        </table>
        <div class="d-flex justify-content-between">
            <a href="../index.php" class="btn btn-secondary">Continue shopping</a>
            <a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>
        </div>
    <?php } ?>
</div>
<?php include('../includes/footer.php'); ?>
