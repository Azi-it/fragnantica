<?php
session_start();
include('./includes/config.php');
include('./includes/functions.php');

$perPage = 12;

// sort options: the SQL comes from this list, never from the URL
$sorts = array(
    'newest'     => array('label' => 'Newest',             'sql' => 'p.product_id DESC'),
    'price_asc'  => array('label' => 'Price: low to high', 'sql' => 'p.sell_price ASC, p.product_id DESC'),
    'price_desc' => array('label' => 'Price: high to low', 'sql' => 'p.sell_price DESC, p.product_id DESC'),
    'name'       => array('label' => 'Name: A to Z',       'sql' => 'p.name ASC, p.product_id DESC'),
);

// read the filters from the URL, ignoring anything invalid
function get_text($key)
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
}
function get_price($key)
{
    $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_FLOAT, array('options' => array('min_range' => 0)));
    return ($value === false || $value === null) ? null : $value;
}

$keyword = get_text('search');
$brand = get_text('brand');
$scent = get_text('scent');
$minPrice = get_price('min_price');
$maxPrice = get_price('max_price');
if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
    list($minPrice, $maxPrice) = array($maxPrice, $minPrice);
}
$inStock = get_text('in_stock') === '1';
$sort = isset($sorts[get_text('sort')]) ? get_text('sort') : 'newest';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
if (!$page) {
    $page = 1;
}

// the query parameters that links on this page carry forward
$keep = array('search', 'brand', 'scent', 'min_price', 'max_price', 'in_stock', 'sort');

// "WHERE 1=1" is always true, so each filter can simply add "AND ..."
$where = " WHERE 1=1";
$params = array();

if ($keyword !== '') {
    $where .= " AND (p.name LIKE ? OR p.brand LIKE ? OR c.name LIKE ? OR p.scent_type LIKE ?)";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
}
if ($brand !== '') {
    $where .= " AND p.brand = ?";
    $params[] = $brand;
}
if ($scent !== '') {
    $where .= " AND p.scent_type = ?";
    $params[] = $scent;
}
if ($minPrice !== null) {
    $where .= " AND p.sell_price >= ?";
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where .= " AND p.sell_price <= ?";
    $params[] = $maxPrice;
}
if ($inStock) {
    $where .= " AND s.quantity > 0";
}

$from = " FROM product p
          INNER JOIN category c USING (category_id)
          INNER JOIN stock s USING (product_id)";

// how many products match, so we know how many pages there are
$countResult = mysqli_execute_query($conn, "SELECT COUNT(*) AS total" . $from . $where, $params);
$productCount = (int)mysqli_fetch_assoc($countResult)['total'];
$totalPages = max(1, (int)ceil($productCount / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = "SELECT p.product_id, p.name, p.brand, p.scent_type, p.size, p.unit, p.description, p.sell_price, p.img_path,
               c.name AS category, s.quantity AS stock"
     . $from . $where
     . " ORDER BY " . $sorts[$sort]['sql']
     . " LIMIT {$perPage} OFFSET {$offset}";
$products = mysqli_fetch_all(mysqli_execute_query($conn, $sql, $params), MYSQLI_ASSOC);

// choices for the filter dropdowns
$brands = mysqli_fetch_all(mysqli_query($conn, "SELECT DISTINCT brand FROM product ORDER BY brand"), MYSQLI_ASSOC);
$scents = mysqli_fetch_all(mysqli_query($conn, "SELECT DISTINCT scent_type FROM product ORDER BY scent_type"), MYSQLI_ASSOC);

$filtersActive = $brand !== '' || $scent !== '' || $minPrice !== null || $maxPrice !== null || $inStock;
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// after "Add to cart", come back to this same page with the same filters
$returnQuery = ltrim(url_with(array('page' => $page > 1 ? $page : null), $keep), '?');

include('./includes/header.php');
?>
<div class="container mt-3">
    <?php include('./includes/alert.php'); ?>
</div>
<h1 align="center">Fragrance &amp; Scent Products</h1>

<form class="filter-bar card card-body mb-3 mx-3 mx-lg-auto" method="GET" action="index.php">
    <?php if ($keyword !== ''): ?><input type="hidden" name="search" value="<?= e($keyword) ?>"><?php endif; ?>
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="f-brand">Brand</label>
            <select class="form-select form-select-sm" id="f-brand" name="brand">
                <option value="">All brands</option>
                <?php foreach ($brands as $b): ?>
                    <option value="<?= e($b['brand']) ?>" <?= $brand === $b['brand'] ? 'selected' : '' ?>><?= e($b['brand']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="f-scent">Scent type</label>
            <select class="form-select form-select-sm" id="f-scent" name="scent">
                <option value="">All scents</option>
                <?php foreach ($scents as $s): ?>
                    <option value="<?= e($s['scent_type']) ?>" <?= $scent === $s['scent_type'] ? 'selected' : '' ?>><?= e($s['scent_type']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="f-min">Min price (&#8369;)</label>
            <input class="form-control form-control-sm" type="number" id="f-min" name="min_price" min="0" step="0.01"
                value="<?= $minPrice !== null ? e($minPrice) : '' ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="f-max">Max price (&#8369;)</label>
            <input class="form-control form-control-sm" type="number" id="f-max" name="max_price" min="0" step="0.01"
                value="<?= $maxPrice !== null ? e($maxPrice) : '' ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="f-sort">Sort by</label>
            <select class="form-select form-select-sm" id="f-sort" name="sort">
                <?php foreach ($sorts as $key => $option): ?>
                    <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($option['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" id="f-stock" name="in_stock" value="1" <?= $inStock ? 'checked' : '' ?>>
                <label class="form-check-label small" for="f-stock">In stock only</label>
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn-sm btn-dark flex-fill" type="submit">Apply</button>
                <?php if ($filtersActive || $sort !== 'newest'): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url_with(array(), array('search'))) ?>" title="Clear filters">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<?php
if ($productCount > 0) {
    $first = $offset + 1;
    $last = $offset + count($products);
    $label = $productCount === 1 ? 'product' : 'products';
    $searchText = $keyword !== '' ? " for <strong>&quot;" . e($keyword) . "&quot;</strong>" : '';
    echo "<p class='text-center text-muted small mb-1'>Showing {$first}&ndash;{$last} of {$productCount} {$label}{$searchText}";
    if ($keyword !== '') {
        echo " &middot; <a href='index.php'>Show all products</a>";
    }
    echo "</p>";
}

if ($productCount > 0) {
    echo '<ul class="products">';

    foreach ($products as $row) {
        $id = (int)$row['product_id'];
        $stock = (int)$row['stock'];
        $link = "product/view.php?id={$id}";

        if ($isAdmin) {
            $cartForm = "<a href='product/edit.php?id={$id}' class='btn btn-outline-secondary btn-sm w-100 mt-2'>Edit product</a>";
        } else if ($stock <= 0) {
            $cartForm = "<button class='btn btn-secondary btn-sm w-100 mt-2' disabled>Out of stock</button>";
        } else {
            $cartForm = "<form action='./cart/add.php' method='POST' class='d-flex gap-1 mt-2'>
                " . csrf_field() . "
                <input type='hidden' name='product_id' value='{$id}'>
                <input type='hidden' name='return_query' value='" . e($returnQuery) . "'>
                <input type='number' name='quantity' value='1' min='1' max='{$stock}' class='form-control form-control-sm' aria-label='Quantity'>
                <button type='submit' name='submit' class='btn btn-primary btn-sm'>Add</button>
            </form>
            <small class='text-muted'>{$stock} in stock</small>";
        }

        $name = e($row['name']);
        $img = e($row['img_path']);
        $category = e($row['category']);
        $brandName = e($row['brand']);
        $scentType = e($row['scent_type']);
        $size = (int)$row['size'];
        $unit = e($row['unit']);
        $desc = e($row['description']);
        $price = peso($row['sell_price']);

        echo <<<EOT
    <li class="product">
        <div class="product-content">
            <h3><a href="{$link}">{$name}</a></h3>
            <div class="product-thumb"><a href="{$link}"><img src="./product/{$img}" width="150" height="150" alt="{$name}"></a></div>
            <div class="product-info">
                <p class="category">{$category}</p>
                <p class="brand">{$brandName}</p>
                <p>{$scentType} &middot; {$size} {$unit}</p>
                <div class="product-desc">{$desc}</div>
                <p class="price">{$price}</p>
            </div>
            {$cartForm}
        </div>
    </li>
EOT;
    }

    echo '</ul>';
    echo '<div class="mt-3">' . pagination($page, $totalPages, $keep) . '</div>';
} else if ($keyword !== '' || $filtersActive) {
    echo "<p align='center'>No products match these filters. <a href='index.php'>Show all products</a></p>";
} else {
    echo "<p align='center'>No products available yet.</p>";
}

include('./includes/footer.php');
?>
