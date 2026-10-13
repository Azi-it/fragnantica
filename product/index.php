<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// only admins can see this page
require_admin();

include('../includes/header.php');

$sql = "SELECT p.*, c.name AS category, s.quantity,
               (SELECT COUNT(*) FROM product_images i WHERE i.product_id = p.product_id) AS extra_photos
        FROM product p
        INNER JOIN category c USING (category_id)
        INNER JOIN stock s USING (product_id)
        ORDER BY p.product_id DESC";
$result = mysqli_query($conn, $sql);

$productCount = mysqli_num_rows($result);

?>
<div class="container-fluid container-xl mt-4">
    <?php include('../includes/alert.php'); ?>
    <a href="create.php" class="btn btn-primary btn-lg" role="button">Add Product</a>
    <h2 class="mt-3">number of products <?= $productCount ?></h2>
    <div class="table-responsive">
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
            <?php while ($row = mysqli_fetch_assoc($result)):
                $id = (int)$row['product_id'];
                $photos = 1 + (int)$row['extra_photos'];
            ?>
                <tr>
                    <td>
                        <img src="<?= e($row['img_path']) ?>" width="80" height="80" class="product-photo" alt="" />
                        <div class="small text-muted text-center"><i class="fa-regular fa-images"></i> <?= $photos ?></div>
                    </td>
                    <td><?= $id ?></td>
                    <td><a href="view.php?id=<?= $id ?>"><?= e($row['name']) ?></a></td>
                    <td><?= e($row['category']) ?></td>
                    <td><?= e($row['brand']) ?></td>
                    <td><?= e($row['scent_type']) ?></td>
                    <td><?= (int)$row['size'] ?> <?= e($row['unit']) ?></td>
                    <td><?= e($row['cost_price']) ?></td>
                    <td><?= e($row['sell_price']) ?></td>
                    <td><?= (int)$row['quantity'] ?></td>
                    <td class="text-nowrap">
                        <a href="edit.php?id=<?= $id ?>" title="Edit" class="btn btn-sm btn-link px-1"><i class="fa-regular fa-pen-to-square" style="color: blue"></i><span class="visually-hidden">Edit</span></a>
                        <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this product?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= $id ?>">
                            <button type="submit" class="btn btn-sm btn-link px-1" title="Delete"><i class="fa-solid fa-trash" style="color: red"></i><span class="visually-hidden">Delete</span></button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>
<?php
include('../includes/footer.php');
?>
