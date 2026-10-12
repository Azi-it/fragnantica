<?php
session_start();
include('../includes/config.php');

// only admins can see this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'admin access only, please log in with an admin account';
    header("Location: ../user/login.php");
    exit();
}

include('../includes/header.php');

$sql = "SELECT p.*, c.name AS category, s.quantity FROM product p
        INNER JOIN category c USING (category_id)
        INNER JOIN stock s USING (product_id)
        ORDER BY p.product_id DESC";
$result = mysqli_query($conn, $sql);

$productCount = mysqli_num_rows($result);

?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <a href="create.php" class="btn btn-primary btn-lg" role="button">Add Product</a>
    <h2 class="mt-3">number of products <?= $productCount ?></h2>
    <table class="table table-striped table-bordered align-middle">
        <thead>
            <tr>
                <th>Photo</th>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Brand</th>
                <th>Scent Type</th>
                <th>Size</th>
                <th>Cost Price</th>
                <th>Selling Price</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td><img src='{$row['img_path']}' width='80' height='80' class='product-photo' /></td>";
                echo "<td>{$row['product_id']}</td>";
                echo "<td>{$row['name']}</td>";
                echo "<td>{$row['category']}</td>";
                echo "<td>{$row['brand']}</td>";
                echo "<td>{$row['scent_type']}</td>";
                echo "<td>{$row['size']} {$row['unit']}</td>";
                echo "<td>{$row['cost_price']}</td>";
                echo "<td>{$row['sell_price']}</td>";
                echo "<td>{$row['quantity']}</td>";
                echo "<td><a href='edit.php?id={$row['product_id']}' title='Edit'><i class='fa-regular fa-pen-to-square' style='color: blue'></i></a> ";
                echo "<a href='delete.php?id={$row['product_id']}' title='Delete' onclick=\"return confirm('Delete this product?')\"><i class='fa-solid fa-trash' style='color: red'></i></a></td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<?php
include('../includes/footer.php');
?>
