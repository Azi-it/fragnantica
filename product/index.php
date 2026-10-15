<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// only admins can see this page
require_admin();

include('../includes/header.php');

// photos = the main photo + the extra photos in product_images
$sql = "SELECT p.*, c.name AS category, s.quantity,
               1 + (SELECT COUNT(*) FROM product_images i WHERE i.product_id = p.product_id) AS photos
        FROM product p
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
                echo "<td><img src='" . e($row['img_path']) . "' width='80' height='80' class='product-photo' alt='' />"
                    . "<div class='small text-muted text-center'><i class='fa-regular fa-images'></i> {$row['photos']}</div></td>";
                echo "<td>{$row['product_id']}</td>";
                echo "<td><a href='view.php?id={$row['product_id']}'>" . e($row['name']) . "</a></td>";
                echo "<td>{$row['category']}</td>";
                echo "<td>{$row['brand']}</td>";
                echo "<td>{$row['scent_type']}</td>";
                echo "<td>{$row['size']} {$row['unit']}</td>";
                echo "<td>{$row['cost_price']}</td>";
                echo "<td>{$row['sell_price']}</td>";
                echo "<td>{$row['quantity']}</td>";
                // Edit is a link; Delete is a small form (POST + CSRF token), see delete.php
                echo "<td class='text-nowrap'><a href='edit.php?id={$row['product_id']}' title='Edit' class='btn btn-sm btn-link px-1'><i class='fa-regular fa-pen-to-square' style='color: blue'></i></a>"
                    . "<form action='delete.php' method='POST' class='d-inline' onsubmit=\"return confirm('Delete this product and all its photos?');\">"
                    . csrf_field()
                    . "<input type='hidden' name='product_id' value='{$row['product_id']}'>"
                    . "<button type='submit' class='btn btn-sm btn-link px-1' title='Delete'><i class='fa-solid fa-trash' style='color: red'></i></button>"
                    . "</form></td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>
<?php
include('../includes/footer.php');
?>
