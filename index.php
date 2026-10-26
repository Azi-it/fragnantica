<?php
session_start();
include('./includes/config.php');
include('./includes/header.php');

// search: the keyword can be in the product name, brand, category or description
if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $keyword = strtolower(trim($_GET['search']));
    $sql = "SELECT p.product_id, p.name, p.brand, p.size, p.img_path, p.sell_price, c.name AS category, s.quantity AS qty
            FROM product p INNER JOIN category c USING (category_id) INNER JOIN stock s USING (product_id)
            WHERE p.name LIKE ? OR p.brand LIKE ? OR c.name LIKE ? OR p.description LIKE ?
            ORDER BY p.product_id DESC";
    $like = "%{$keyword}%";
    $results = mysqli_execute_query($conn, $sql, [$like, $like, $like, $like]);
} else {
    $keyword = '';
    $sql = "SELECT p.product_id, p.name, p.brand, p.size, p.img_path, p.sell_price, c.name AS category, s.quantity AS qty
            FROM product p INNER JOIN category c USING (category_id) INNER JOIN stock s USING (product_id)
            ORDER BY p.product_id DESC";
    $results = mysqli_query($conn, $sql);
}
$productCount = mysqli_num_rows($results);
?>
<!-- banner: a looping candle video behind the title and the search box -->
<div class="hero">
    <video class="hero-video" autoplay muted loop playsinline>
        <source src="includes/video/hero.mp4" type="video/mp4">
    </video>
    <div class="hero-content">
        <h1>Veloura Fragrances</h1>
        <p>Candles, diffusers and scents for every room</p>
        <form action="index.php" method="GET" class="d-flex col-11 col-md-6 mx-auto">
            <input class="form-control me-2" type="search" placeholder="Search by name, brand or category" name="search">
            <button class="btn btn-light" type="submit">Search</button>
        </form>
    </div>
</div>

<div class="container mt-4">
    <?php include('./includes/alert.php'); ?>

    <?php
    if ($keyword !== '') {
        echo "<p align='center'>{$productCount} product(s) found for <strong>" . strip_tags($keyword) . "</strong> <a href='index.php'>show all</a></p>";
    }

    $products_item = '<ul class="products">';
    while ($row = mysqli_fetch_assoc($results)) {
        if ($row['qty'] > 0) {
            $cartForm = <<<EOT
            <form method="POST" action="cart/cart_update.php">
                <label>Quantity <input type="text" size="2" name="product_qty" value="1" /></label>
                <input type="hidden" name="product_id" value="{$row['product_id']}" />
                <input type="hidden" name="type" value="add" />
                <button type="submit" class="btn btn-sm btn-primary mt-2">Add to Cart</button>
            </form>
EOT;
        } else {
            $cartForm = "<p class='text-danger'>Out of stock</p>";
        }

        $products_item .= <<<EOT
        <li class="product">
            <div class="product-thumb"><a href="product/view.php?id={$row['product_id']}"><img src="./product/{$row['img_path']}"></a></div>
            <h3><a href="product/view.php?id={$row['product_id']}">{$row['name']}</a></h3>
            <p class="mb-1">{$row['brand']} &middot; {$row['size']}</p>
            <p class="mb-1 small text-muted">{$row['category']}</p>
            <p class="price">&#8369; {$row['sell_price']}</p>
            {$cartForm}
        </li>
EOT;
    }
    $products_item .= '</ul>';
    echo $products_item;

    if ($productCount === 0) {
        echo "<p align='center'>No products found.</p>";
    }
    ?>
</div>
<?php
include('./includes/footer.php');
?>
