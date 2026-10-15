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

$sql = "SELECT c.product_id, c.quantity, p.name, p.brand, p.size, p.unit, p.sell_price, p.img_path,
               s.quantity AS stock
        FROM cart c
        INNER JOIN product p USING (product_id)
        INNER JOIN stock s USING (product_id)
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC";
$items = mysqli_fetch_all(mysqli_execute_query($conn, $sql, [$user_id]), MYSQLI_ASSOC);

$grandTotal = 0;
$totalUnits = 0;
$hasStockIssue = false;
foreach ($items as $item) {
    $grandTotal += $item['sell_price'] * $item['quantity'];
    $totalUnits += (int)$item['quantity'];
    if ($item['quantity'] > $item['stock']) {
        $hasStockIssue = true;
    }
}

include('../includes/header.php');
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <h2>My Cart</h2>

    <?php if (empty($items)) { ?>
        <p>Your cart is empty. <a href="../index.php">Browse products</a></p>
    <?php } else { ?>
        <p class="text-muted small"><?= $totalUnits ?> item<?= $totalUnits === 1 ? '' : 's' ?> in your cart. Change a quantity and press Update; set it to 0 to remove the item.</p>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Photo</th><th>Product</th><th>Price</th>
                        <th style="min-width: 190px;">Qty</th><th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $row):
                    $id = (int)$row['product_id'];
                    $stock = (int)$row['stock'];
                    $lineTotal = $row['sell_price'] * $row['quantity'];
                ?>
                    <tr>
                        <td><a href="../product/view.php?id=<?= $id ?>"><img src="../product/<?= e($row['img_path']) ?>" width="70" height="70" class="product-photo" alt="<?= e($row['name']) ?>"></a></td>
                        <td>
                            <a href="../product/view.php?id=<?= $id ?>" class="text-reset"><strong><?= e($row['name']) ?></strong></a><br>
                            <?= e($row['brand']) ?> &middot; <?= (int)$row['size'] ?> <?= e($row['unit']) ?>
                        </td>
                        <td><?= peso($row['sell_price']) ?></td>
                        <td>
                            <form action="update.php" method="POST" class="d-flex gap-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= $id ?>">
                                <input type="number" name="quantity" value="<?= (int)$row['quantity'] ?>" min="0" max="<?= $stock ?>"
                                    class="form-control form-control-sm" style="max-width: 80px;" aria-label="Quantity of <?= e($row['name']) ?>">
                                <button type="submit" name="submit" class="btn btn-outline-primary btn-sm">Update</button>
                            </form>
                            <?php if ($row['quantity'] > $stock): ?>
                                <small class="text-danger">only <?= $stock ?> left in stock</small>
                            <?php else: ?>
                                <small class="text-muted"><?= $stock ?> in stock</small>
                            <?php endif; ?>
                        </td>
                        <td><?= peso($lineTotal) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Total</th>
                        <th><?= peso($grandTotal) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php if ($hasStockIssue): ?>
            <div class="alert alert-warning">Some items exceed what is left in stock. Lower those quantities before checking out.</div>
        <?php endif; ?>
        <div class="d-flex justify-content-between">
            <a href="../index.php" class="btn btn-secondary">Continue shopping</a>
            <a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>
        </div>
    <?php } ?>
</div>
<?php include('../includes/footer.php'); ?>
