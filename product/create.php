<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');
include('../includes/product_form.php');

// only admins can add products
require_admin();

// after a failed submit: the error messages and what the admin typed
list($errors, $old) = take_form('product_create');
$values = array_merge(array(
    'name' => '', 'category_id' => '', 'brand' => '', 'scent_type' => '', 'size' => '', 'unit' => 'ml',
    'description' => '', 'cost_price' => '', 'sell_price' => '', 'quantity' => '',
), $old);

// categories come from the category table
$categories = mysqli_fetch_all(mysqli_query($conn, "SELECT category_id, name FROM category ORDER BY category_id"), MYSQLI_ASSOC);
$product = null;
$extraPhotos = array();

include('../includes/header.php');
?>
<div class="container mt-4">
    <h2>Add Product</h2>
    <?php include('../includes/alert.php'); ?>
    <?php if ($errors): ?>
        <div class="alert alert-warning">Please fix the <?= count($errors) ?> highlighted field<?= count($errors) === 1 ? '' : 's' ?> below.</div>
    <?php endif; ?>
    <!-- novalidate: the browser does not check the form, PHP does (store.php) -->
    <form method="POST" action="store.php" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <?php include('../includes/product_fields.php'); ?>
        <button type="submit" class="btn btn-primary" name="submit">Submit</button>
        <a href="index.php" role="button" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php
include('../includes/footer.php');
?>
