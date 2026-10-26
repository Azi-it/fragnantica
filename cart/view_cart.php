<?php
session_start();
include('../includes/config.php');
include('../includes/loginCheck.php');
include('../includes/header.php');
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <h1 align="center">View Cart</h1>
    <?php
    if (!isset($_SESSION["cart_products"]) || count($_SESSION["cart_products"]) === 0) {
        echo "<p align='center'>Your cart is empty. <a href='../index.php'>Shop now</a></p>";
    } else {
    ?>
        <form method="POST" action="cart_update.php">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Quantity</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Total</th>
                        <th>Remove</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total = 0;
                    foreach ($_SESSION["cart_products"] as $cart_itm) {
                        $product_name = $cart_itm["product_name"];
                        $product_qty = $cart_itm["product_qty"];
                        $product_price = $cart_itm["product_price"];
                        $product_code = $cart_itm["product_id"];
                        $subtotal = ($product_price * $product_qty);

                        echo '<tr>';
                        echo '<td><input type="text" size="2" name="product_qty[' . $product_code . ']" value="' . $product_qty . '" /></td>';
                        echo '<td>' . $product_name . '</td>';
                        echo '<td>' . $product_price . '</td>';
                        echo '<td>' . sprintf("%01.2f", $subtotal) . '</td>';
                        echo '<td><input type="checkbox" name="remove_code[]" value="' . $product_code . '" /></td>';
                        echo '</tr>';
                        $total = ($total + $subtotal);
                    }
                    ?>
                    <tr>
                        <td colspan="5" align="right"><strong>Amount Payable : &#8369; <?php echo sprintf("%01.2f", $total); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            <a href="../index.php" class="btn btn-secondary">Add More Items</a>
            <button type="submit" class="btn btn-outline-primary">Update</button>
            <a href="checkout.php" class="btn btn-success">Checkout</a>
        </form>
    <?php
    }
    ?>
</div>
<?php
include('../includes/footer.php');
?>
