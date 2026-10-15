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

// MP1: all photos of the product, the main photo first, then the extra photos
$photos = array($product['img_path']);
$result = mysqli_execute_query($conn, "SELECT img_path FROM product_images WHERE product_id = ? ORDER BY image_id", [$product_id]);
while ($row = mysqli_fetch_assoc($result)) {
    $photos[] = $row['img_path'];
}

// up to 4 other products from the same category, in-stock ones first
$relatedSql = "SELECT p.product_id, p.name, p.brand, p.sell_price, p.img_path, s.quantity AS stock
               FROM product p
               INNER JOIN stock s USING (product_id)
               WHERE p.category_id = ? AND p.product_id <> ?
               ORDER BY (s.quantity > 0) DESC, p.product_id DESC
               LIMIT 4";
$related = mysqli_fetch_all(mysqli_execute_query($conn, $relatedSql, [$product['category_id'], $product_id]), MYSQLI_ASSOC);

// ---------- MP4: reviews ----------
$me = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$isCustomer = isset($_SESSION['role']) && $_SESSION['role'] === 'customer';

// every review of this product with the reviewer's name and photo;
// the logged in customer's own review is listed first
$reviewSql = "SELECT r.review_id, r.user_id, r.rating, r.comment, r.created_at, r.updated_at, u.name, u.img_path
              FROM review r
              INNER JOIN users u USING (user_id)
              WHERE r.product_id = ?
              ORDER BY (r.user_id = ?) DESC, COALESCE(r.updated_at, r.created_at) DESC";
$reviews = mysqli_fetch_all(mysqli_execute_query($conn, $reviewSql, [$product_id, $me]), MYSQLI_ASSOC);

// the average rating and how many reviews gave 1, 2, 3, 4 and 5 stars
$reviewCount = count($reviews);
$perStar = array_fill(1, 5, 0);
$ratingSum = 0;
$myReview = null;
foreach ($reviews as $r) {
    $perStar[(int)$r['rating']]++;
    $ratingSum += (int)$r['rating'];
    if ((int)$r['user_id'] === $me) {
        $myReview = $r;
    }
}
$average = $reviewCount > 0 ? $ratingSum / $reviewCount : 0;

// only a customer who received this product (a completed order) may review it
$canReview = $isCustomer && can_review_product($conn, $me, $product_id);

// after a failed submit: the errors and what was typed; otherwise the saved review (for editing)
list($reviewErrors, $reviewOld) = take_form('review_' . $product_id);
$formRating = (int)($reviewOld['rating'] ?? ($myReview['rating'] ?? 0));
$formComment = $reviewOld['comment'] ?? ($myReview['comment'] ?? '');
$openEditForm = $myReview && $reviewErrors;
$ratingWords = array(1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent');

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
            <img src="<?= e($photos[0]) ?>" id="main-photo" class="product-detail-photo" alt="<?= e($product['name']) ?>">
            <?php if (count($photos) > 1): ?>
                <!-- MP1: small photos under the big one; clicking one shows it in the big frame -->
                <div class="gallery-thumbs" role="group" aria-label="Product photos">
                    <?php foreach ($photos as $i => $photo): ?>
                        <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-photo="<?= e($photo) ?>" aria-label="Show photo <?= $i + 1 ?> of <?= count($photos) ?>">
                            <img src="<?= e($photo) ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="small text-muted mt-1 mb-0"><?= count($photos) ?> photos. Click one to see it bigger.</p>
            <?php endif; ?>
        </div>
        <div class="col-md-7">
            <p class="text-uppercase text-muted small mb-1"><?= e($product['category']) ?></p>
            <h2 class="mb-1"><?= e($product['name']) ?></h2>
            <p class="fw-bold mb-2"><?= e($product['brand']) ?></p>
            <?php if ($reviewCount > 0): ?>
                <p class="mb-3"><a href="#reviews" class="text-reset text-decoration-none"><?= stars($average) ?>
                    <span class="small text-muted"><?= number_format($average, 1) ?> (<?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?>)</span></a></p>
            <?php endif; ?>

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

    <!-- ===================== MP4: reviews ===================== -->
    <section id="reviews" class="mt-5">
        <h4 class="mb-3">Customer Reviews</h4>
        <div class="row g-4">
            <div class="col-md-4">
                <!-- summary: average and a bar for each number of stars -->
                <div class="card rating-summary">
                    <div class="card-body">
                        <?php if ($reviewCount === 0): ?>
                            <p class="mb-1"><?= stars(0) ?></p>
                            <p class="text-muted mb-0">No reviews yet.</p>
                        <?php else: ?>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="big-number"><?= number_format($average, 1) ?></span>
                                <div><?= stars($average) ?><br><span class="small text-muted"><?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?></span></div>
                            </div>
                            <?php for ($star = 5; $star >= 1; $star--):
                                $percent = round($perStar[$star] / $reviewCount * 100);
                            ?>
                                <div class="d-flex align-items-center gap-2 small mb-1">
                                    <span style="width: 3.2em;"><?= $star ?> star</span>
                                    <div class="rating-bar flex-fill"><span style="width: <?= $percent ?>%;"></span></div>
                                    <span class="text-muted" style="width: 2em;"><?= $perStar[$star] ?></span>
                                </div>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php // who can write a review, and why not
                if (!isset($_SESSION['user_id'])): ?>
                    <p class="small text-muted mt-3"><a href="../user/login.php">Log in</a> to review a product you have bought.</p>
                <?php elseif ($isCustomer && !$myReview && !$canReview): ?>
                    <p class="small text-muted mt-3"><i class="fa-solid fa-circle-info me-1"></i>
                        Only customers who bought this product can review it. You can write a review once your order is completed.</p>
                <?php endif; ?>
            </div>

            <div class="col-md-8">
                <?php if ($canReview && !$myReview): ?>
                    <!-- write a review (only for a customer who received this product) -->
                    <div class="card mb-4">
                        <div class="card-header"><strong>Write a review</strong></div>
                        <div class="card-body">
                            <?php include('../review/form.php'); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($reviewCount === 0): ?>
                    <p class="text-muted">Be the first to share what you think of this product.</p>
                <?php endif; ?>

                <?php foreach ($reviews as $r):
                    $mine = $myReview && (int)$r['review_id'] === (int)$myReview['review_id'];
                ?>
                    <div class="review" id="review-<?= (int)$r['review_id'] ?>">
                        <div class="d-flex align-items-center gap-2">
                            <?= avatar($r['name'], $r['img_path'], 36) ?>
                            <div class="flex-fill">
                                <strong><?= e($r['name']) ?></strong>
                                <?php if ($mine): ?><span class="badge bg-light text-dark border">your review</span><?php endif; ?>
                                <span class="badge bg-success-subtle text-success-emphasis"><i class="fa-solid fa-check"></i> Verified buyer</span>
                                <br>
                                <?= stars($r['rating']) ?>
                                <span class="small text-muted"><?= date('M j, Y', strtotime($r['created_at'])) ?><?= $r['updated_at'] ? ' &middot; edited ' . date('M j, Y', strtotime($r['updated_at'])) : '' ?></span>
                            </div>
                            <?php if ($mine || $isAdmin): ?>
                                <div class="d-flex gap-1">
                                    <?php if ($mine): ?>
                                        <!-- the Edit button opens the update form below this review -->
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-review"
                                            aria-expanded="<?= $openEditForm ? 'true' : 'false' ?>" aria-controls="edit-review">
                                            <i class="fa-regular fa-pen-to-square"></i> Edit</button>
                                    <?php endif; ?>
                                    <form action="../review/delete.php" method="POST" onsubmit="return confirm('Delete this review?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="review_id" value="<?= (int)$r['review_id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete review"><i class="fa-solid fa-trash"></i><span class="visually-hidden">Delete</span></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- shown through the bad word filter as well, in case the word list changed after it was saved -->
                        <p class="review-comment"><?= e(mask_bad_words($r['comment'])) ?></p>

                        <?php if ($mine): ?>
                            <!-- update the review -->
                            <div class="collapse<?= $openEditForm ? ' show' : '' ?>" id="edit-review">
                                <div class="card card-body mt-2">
                                    <?php include('../review/form.php'); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

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
<script>
    // photo gallery: clicking a small photo shows it in the big frame
    document.querySelectorAll('.gallery-thumbs button').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('main-photo').src = button.dataset.photo;
            document.querySelectorAll('.gallery-thumbs button').forEach(function (b) { b.classList.remove('active'); });
            button.classList.add('active');
        });
    });
</script>
<?php include('../includes/footer.php'); ?>
