<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');
include('../includes/product_form.php');

// only admins can edit products
require_admin();

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$product_id) {
    header("Location: index.php");
    exit();
}

$sql = "SELECT p.*, s.quantity FROM product p INNER JOIN stock s USING (product_id) WHERE p.product_id = ? LIMIT 1";
$result = mysqli_execute_query($conn, $sql, [$product_id]);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    $_SESSION['message'] = 'product not found';
    header("Location: index.php");
    exit();
}

$extraPhotos = mysqli_fetch_all(mysqli_execute_query($conn,
    "SELECT image_id, img_path FROM product_images WHERE product_id = ? ORDER BY image_id", [$product_id]), MYSQLI_ASSOC);

// after a failed update show what the admin typed, otherwise show the saved values
list($errors, $old) = take_form('product_edit_' . $product_id);
$values = array_merge(array(
    'name' => $product['name'],
    'category_id' => $product['category_id'],
    'brand' => $product['brand'],
    'scent_type' => $product['scent_type'],
    'size' => $product['size'],
    'unit' => $product['unit'],
    'description' => $product['description'],
    'cost_price' => $product['cost_price'],
    'sell_price' => $product['sell_price'],
    'quantity' => $product['quantity'],
), $old);

// categories come from the category table
$categories = mysqli_fetch_all(mysqli_query($conn, "SELECT category_id, name FROM category ORDER BY category_id"), MYSQLI_ASSOC);

include('../includes/header.php');
?>
<div class="container mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="mb-0">Edit Product</h2>
        <a href="view.php?id=<?= (int)$product_id ?>" class="btn btn-outline-secondary btn-sm"><i class="fa-regular fa-eye me-1"></i> View product page</a>
    </div>
    <div class="mt-3"><?php include('../includes/alert.php'); ?></div>
    <?php if ($errors): ?>
        <div class="alert alert-warning">Please fix the <?= count($errors) ?> highlighted field<?= count($errors) === 1 ? '' : 's' ?> below.</div>
    <?php endif; ?>
    <!-- novalidate: the browser does not check the form, PHP does (update.php) -->
    <form method="POST" action="update.php" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>" />
        <?php include('../includes/product_fields.php'); ?>
        <button type="submit" class="btn btn-primary" name="submit">Save Changes</button>
        <a href="index.php" role="button" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php
include('../includes/footer.php');
?>
