<?php
session_start();
include('../includes/config.php');
include('../includes/adminCheck.php');
include('../includes/header.php');

// all orders, from the order_totals_view view
$sql = "SELECT order_id, name, email, status, date_placed, items, total FROM order_totals_view ORDER BY order_id DESC";
$result = mysqli_query($conn, $sql);
$orderCount = mysqli_num_rows($result);
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <h2>number of orders <?= $orderCount ?></h2>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Email</th>
                <th>Date placed</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>{$row['order_id']}</td>";
                echo "<td>{$row['name']}</td>";
                echo "<td>{$row['email']}</td>";
                echo "<td>{$row['date_placed']}</td>";
                echo "<td>{$row['items']}</td>";
                echo "<td>{$row['total']}</td>";
                echo "<td>{$row['status']}</td>";
                echo "<td><a href='orderDetails.php?id={$row['order_id']}'><i class='fa-regular fa-eye' style='color: blue'></i></a></td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<?php
include('../includes/footer.php');
?>
