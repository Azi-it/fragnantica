<?php
session_start();
include('../includes/config.php');
include('../includes/adminCheck.php');

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// the customer and the order (from the order_details_view view)
$sql = "SELECT order_id, name, email, addressline, town, zipcode, phone, status, date_placed FROM order_details_view WHERE order_id = ? LIMIT 1";
$customer = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$orderId]));
if (!$customer) {
    $_SESSION['message'] = 'order not found';
    header("Location: orders.php");
    exit();
}

// the products in the order
$sql = "SELECT product_name, brand, size, quantity, price, subtotal FROM order_details_view WHERE order_id = ?";
$items = mysqli_execute_query($conn, $sql, [$orderId]);

include('../includes/header.php');
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <h2>Order #<?= $customer['order_id'] ?></h2>
    <p>placed <?= $customer['date_placed'] ?> &middot; status <strong><?= $customer['status'] ?></strong></p>
    <h4><?= $customer['name'] ?> (<?= $customer['email'] ?>)</h4>
    <p><?php echo "{$customer['addressline']}, {$customer['town']} {$customer['zipcode']} &middot; {$customer['phone']}" ?></p>
    <table class="table table-striped table-bordered">
        <thead>
            <th>product</th>
            <th>brand</th>
            <th>size</th>
            <th>quantity</th>
            <th>price</th>
            <th>subtotal</th>
        </thead>
        <?php
        $grandTotal = 0;
        while ($row = mysqli_fetch_assoc($items)) {
            $grandTotal += $row['subtotal'];
            echo "<tr>";
            echo "<td>{$row['product_name']}</td>";
            echo "<td>{$row['brand']}</td>";
            echo "<td>{$row['size']}</td>";
            echo "<td>{$row['quantity']}</td>";
            echo "<td>{$row['price']}</td>";
            echo "<td>{$row['subtotal']}</td>";
            echo "</tr>";
        }
        ?>
    </table>
    <h4 class="text-end">Grand Total: &#8369; <?= sprintf("%01.2f", $grandTotal) ?></h4>

    <?php if ($customer['status'] === 'cancelled') { ?>
        <p class="text-muted">This order was cancelled, so its status can no longer be changed.</p>
    <?php } else { ?>
        <form action="updateOrder.php" method="POST" class="col-md-4">
            <label for="status">update status</label>
            <select class="form-select mb-2" id="status" name="status">
                <?php
                $statuses = array('pending', 'processing', 'shipped', 'completed', 'cancelled');
                foreach ($statuses as $status) {
                    if ($status === $customer['status']) {
                        echo "<option value='{$status}' selected>{$status}</option>";
                    } else {
                        echo "<option value='{$status}'>{$status}</option>";
                    }
                }
                ?>
            </select>
            <input type="hidden" name="order_id" value="<?= $customer['order_id'] ?>" />
            <button type="submit" class="btn btn-primary">update order</button>
            <p class="small text-muted mt-2">The customer gets an email with the order and a PDF receipt.</p>
        </form>
    <?php } ?>
</div>
<?php
include('../includes/footer.php');
?>
