<?php
session_start();
include('../includes/config.php');
include('../includes/adminCheck.php');
include('../includes/header.php');

$sql = "SELECT p.product_id, p.name, p.brand, p.size, p.img_path, p.cost_price, p.sell_price, c.name AS category, s.quantity
        FROM product p INNER JOIN category c USING (category_id) INNER JOIN stock s USING (product_id)
        ORDER BY p.product_id DESC";
$result = mysqli_query($conn, $sql);
$itemCount = mysqli_num_rows($result);
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <a href="create.php" class="btn btn-primary btn-lg" role="button">Add product</a>
    <h2 class="mt-3">number of products <?= $itemCount ?></h2>
    <table class="table table-striped table-bordered align-middle">
        <thead>
            <tr>
                <th>Photo</th>
                <th>Id</th>
                <th>Name</th>
                <th>Brand</th>
                <th>Category</th>
                <th>Size</th>
                <th>Cost price</th>
                <th>Sell price</th>
                <th>Stock</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td><img src='{$row['img_path']}' width='80' height='80' /></td>";
                echo "<td>{$row['product_id']}</td>";
                echo "<td><a href='view.php?id={$row['product_id']}'>{$row['name']}</a></td>";
                echo "<td>{$row['brand']}</td>";
                echo "<td>{$row['category']}</td>";
                echo "<td>{$row['size']}</td>";
                echo "<td>{$row['cost_price']}</td>";
                echo "<td>{$row['sell_price']}</td>";
                echo "<td>{$row['quantity']}</td>";
                echo "<td><a href='edit.php?id={$row['product_id']}'><i class='fa-regular fa-pen-to-square' style='color: blue'></i></a>
                    <a href='delete.php?id={$row['product_id']}'><i class='fa-solid fa-trash' style='color: red'></i></a></td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<?php
include('../includes/footer.php');
?>
