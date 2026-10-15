<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// public page: anyone can view a product, no login needed
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id) {
    header("Location: ../index.php");
    exit();
}

$sql = "SELECT p.*, c.name AS category, s.quantity AS stock
        FROM product p
        INNER JOIN category c USING (category_id)
        INNER JOIN stock s USING (product_id)
        WHERE p.product_id = ? LIMIT 1";
$product = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$product_id]));

if (!$product) {
    $_SESSION['message'] = 'product not found';
    header("Location: ../index.php");
    exit();
}

// up to 4 other products from the same category, in-stock ones first
$relatedSql = "SELECT p.product_id, p.name, p.brand, p.sell_price, p.img_path, s.quantity AS stock
               FROM product p
               INNER JOIN stock s USING (product_id)
               WHERE p.category_id = ? AND p.product_id <> ?
               ORDER BY (s.quantity > 0) DESC, p.product_id DESC
               LIMIT 4";
$related = mysqli_fetch_all(mysqli_execute_query($conn, $relatedSql, [$product['category_id'], $product_id]), MYSQLI_ASSOC);

$stock = (int)$product['stock'];
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if ($stock <= 0) {
    $stockText = "<span class='text-danger'><i class='fa-solid fa-circle-xmark me-1'></i>Out of stock</span>";
} else if ($stock <= 5) {
    $stockText = "<span class='text-warning-emphasis'><i class='fa-solid fa-triangle-exclamation me-1'></i>Only {$stock} left</span>";
} else {
    $stockText = "<span class='text-success'><i class='fa-solid fa-circle-check me-1'></i>In stock</span>";
}

include('../includes/header.php');
?>
<div class="container mt-4 product-detail">
    <?php include('../includes/alert.php'); ?>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
            <li class="breadcrumb-item"><?= e($product['category']) ?></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-md-5">
            <img src="<?= e($product['img_path']) ?>" class="product-detail-photo" alt="<?= e($product['name']) ?>">
        </div>
        <div class="col-md-7">
            <p class="text-uppercase text-muted small mb-1"><?= e($product['category']) ?></p>
            <h2 class="mb-1"><?= e($product['name']) ?></h2>
            <p class="fw-bold mb-3"><?= e($product['brand']) ?></p>

            <p class="price mb-2"><?= peso($product['sell_price']) ?></p>
            <p class="mb-3"><?= $stockText ?></p>

            <table class="table table-sm w-auto mb-3">
                <tr><th class="pe-4">Scent type</th><td><?= e($product['scent_type']) ?></td></tr>
                <tr><th class="pe-4">Size</th><td><?= (int)$product['size'] ?> <?= e($product['unit']) ?></td></tr>
                <?php if ($isAdmin): ?>
                    <tr><th class="pe-4">Cost price</th><td><?= peso($product['cost_price']) ?> <small class="text-muted">(admins only)</small></td></tr>
                    <tr><th class="pe-4">In stock</th><td><?= $stock ?></td></tr>
                <?php endif; ?>
            </table>

            <?php if ($product['description'] !== null && $product['description'] !== ''): ?>
                <p class="mb-4"><?= e($product['description']) ?></p>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <a href="edit.php?id=<?= (int)$product['product_id'] ?>" class="btn btn-outline-secondary">
                    <i class="fa-regular fa-pen-to-square me-1"></i> Edit product
                </a>
            <?php elseif ($stock > 0): ?>
                <form action="../cart/add.php" method="POST" class="d-flex gap-2 align-items-center" style="max-width: 320px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>">
                    <input type="hidden" name="return" value="product">
                    <label for="qty" class="visually-hidden">Quantity</label>
                    <input type="number" id="qty" name="quantity" value="1" min="1" max="<?= $stock ?>" class="form-control" style="max-width: 90px;">
                    <button type="submit" name="submit" class="btn btn-primary flex-fill">
                        <i class="fa-solid fa-cart-plus me-1"></i> Add to cart
                    </button>
                </form>
            <?php else: ?>
                <button class="btn btn-secondary" disabled>Out of stock</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($related): ?>
        <h4 class="mt-5 mb-3">More in <?= e($product['category']) ?></h4>
        <div class="row g-3">
            <?php foreach ($related as $r): ?>
                <div class="col-6 col-md-3">
                    <a href="view.php?id=<?= (int)$r['product_id'] ?>" class="card h-100 text-decoration-none text-reset related-card">
                        <img src="<?= e($r['img_path']) ?>" class="card-img-top p-2" alt="<?= e($r['name']) ?>">
                        <div class="card-body p-2">
                            <p class="small text-muted mb-0"><?= e($r['brand']) ?></p>
                            <p class="mb-1"><?= e($r['name']) ?></p>
                            <p class="price mb-0"><?= peso($r['sell_price']) ?></p>
                            <?php if ((int)$r['stock'] <= 0): ?><small class="text-danger">Out of stock</small><?php endif; ?>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include('../includes/footer.php'); ?>
