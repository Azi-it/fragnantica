<?php
session_start();
include('./includes/config.php');
include('./includes/header.php');

// search by product name, brand or category, and/or show only one category
$keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);

// "WHERE 1=1" is always true, so the filters below can simply add "AND ..."
$sql = "SELECT p.product_id, p.name, p.brand, p.scent_type, p.size, p.unit, p.description, p.sell_price, p.img_path,
            c.name AS category, s.quantity AS stock
        FROM product p
        INNER JOIN category c USING (category_id)
        INNER JOIN stock s USING (product_id)
        WHERE 1=1";
$params = [];

if ($categoryId) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
}
if ($keyword !== '') {
    $sql .= " AND (p.name LIKE ? OR p.brand LIKE ? OR c.name LIKE ?)";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
}
$sql .= " ORDER BY p.product_id DESC";

$result = mysqli_execute_query($conn, $sql, $params);
$productCount = mysqli_num_rows($result);

// category buttons
$categories = mysqli_query($conn, "SELECT category_id, name FROM category ORDER BY category_id");

?>
<div class="container mt-3">
    <?php include('./includes/alert.php'); ?>
</div>
<h1 align="center">Fragrance &amp; Scent Products</h1>

<div class="text-center mb-3 px-3">
    <?php
    if ($categoryId) {
        echo "<a href='index.php' class='btn btn-sm btn-outline-secondary m-1'>All</a>";
    } else {
        echo "<a href='index.php' class='btn btn-sm btn-secondary m-1'>All</a>";
    }
    while ($cat = mysqli_fetch_assoc($categories)) {
        if ($categoryId == $cat['category_id']) {
            echo "<a href='index.php?category={$cat['category_id']}' class='btn btn-sm btn-secondary m-1'>{$cat['name']}</a>";
        } else {
            echo "<a href='index.php?category={$cat['category_id']}' class='btn btn-sm btn-outline-secondary m-1'>{$cat['name']}</a>";
        }
    }
    ?>
</div>

<?php
if ($keyword !== '') {
    $safeKeyword = htmlentities($keyword);
    echo "<p align='center'>Search results for <strong>\"{$safeKeyword}\"</strong>: {$productCount} found. <a href='index.php'>Show all products</a></p>";
}

if ($productCount > 0) {
    $products_item = '<ul class="products">';

    //fetch each product and output HTML
    while ($row = mysqli_fetch_assoc($result)) {
        $price = number_format($row['sell_price'], 2);
        $stock = (int)$row['stock'];
        $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

        if ($isAdmin) {
            $cartForm = '';
        } else if ($stock <= 0) {
            $cartForm = "<button class='btn btn-secondary btn-sm w-100 mt-2' disabled>Out of stock</button>";
        } else {
            $cartForm = "<form action='./cart/add.php' method='POST' class='d-flex gap-1 mt-2'>
                <input type='hidden' name='product_id' value='{$row['product_id']}'>
                <input type='number' name='quantity' value='1' min='1' max='{$stock}' class='form-control form-control-sm'>
                <button type='submit' name='submit' class='btn btn-primary btn-sm'>Add</button>
            </form>
            <small class='text-muted'>{$stock} in stock</small>";
        }

        $products_item .= <<<EOT
    <li class="product">
        <div class="product-content">
            <h3>{$row['name']}</h3>
            <div class="product-thumb"><img src="./product/{$row['img_path']}" width="150" height="150" alt="{$row['name']}"></div>
            <div class="product-info">
                <p class="category">{$row['category']}</p>
                <p class="brand">{$row['brand']}</p>
                <p>{$row['scent_type']} &middot; {$row['size']} {$row['unit']}</p>
                <div class="product-desc">{$row['description']}</div>
                <p class="price">&#8369; {$price}</p>
            </div>
            {$cartForm}
        </div>
    </li>
EOT;
    }

    $products_item .= '</ul>';
    echo $products_item;
} else if ($keyword !== '' || $categoryId) {
    echo "<p align='center'>No products found.</p>";
} else {
    echo "<p align='center'>No products available yet.</p>";
}

include('./includes/footer.php');
?>
