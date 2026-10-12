<?php
session_start();
include('../includes/config.php');

// only admins can edit products
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'admin access only, please log in with an admin account';
    header("Location: ../user/login.php");
    exit();
}

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

// after a failed update show what the admin typed, otherwise show the saved values
$name = isset($_SESSION['productName']) ? $_SESSION['productName'] : $product['name'];
$categoryId = isset($_SESSION['categoryId']) ? $_SESSION['categoryId'] : $product['category_id'];
$brand = isset($_SESSION['brand']) ? $_SESSION['brand'] : $product['brand'];
$scentType = isset($_SESSION['scent']) ? $_SESSION['scent'] : $product['scent_type'];
$size = isset($_SESSION['size']) ? $_SESSION['size'] : $product['size'];
$unitValue = isset($_SESSION['unit']) ? $_SESSION['unit'] : $product['unit'];
$description = isset($_SESSION['description']) ? $_SESSION['description'] : $product['description'];
$cost = isset($_SESSION['cost']) ? $_SESSION['cost'] : $product['cost_price'];
$sell = isset($_SESSION['sell']) ? $_SESSION['sell'] : $product['sell_price'];
$qty = isset($_SESSION['qty']) ? $_SESSION['qty'] : $product['quantity'];

// the typed values are only shown once
unset($_SESSION['productName'], $_SESSION['categoryId'], $_SESSION['brand'], $_SESSION['scent'], $_SESSION['size'], $_SESSION['unit'], $_SESSION['description'], $_SESSION['cost'], $_SESSION['sell'], $_SESSION['qty']);

include('../includes/header.php');

// categories come from the category table
$categories = mysqli_query($conn, "SELECT category_id, name FROM category ORDER BY category_id");

$scentTypes = array('Floral', 'Woody', 'Fresh', 'Citrus', 'Fruity', 'Oriental', 'Aquatic', 'Gourmand', 'Spicy', 'Herbal', 'Musky');
$units = array('ml', 'g', 'pcs');

?>
<div class="container mt-4">
    <h2>Edit Product</h2>
    <?php include('../includes/alert.php'); ?>
    <form method="POST" action="update.php" enctype="multipart/form-data">
        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>" />

        <div class="row gx-3 mb-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Product Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo $name; ?>" />
                <small class="text-danger"><?php
                        if (isset($_SESSION['nameError'])) {
                            echo $_SESSION['nameError'];
                            unset($_SESSION['nameError']);
                        }
                        ?></small>
            </div>
            <div class="col-md-6">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category_id">
                    <option value="">-- choose category --</option>
                    <?php
                    while ($cat = mysqli_fetch_assoc($categories)) {
                        if ($categoryId == $cat['category_id']) {
                            echo "<option value='{$cat['category_id']}' selected>{$cat['name']}</option>";
                        } else {
                            echo "<option value='{$cat['category_id']}'>{$cat['name']}</option>";
                        }
                    }
                    ?>
                </select>
                <small class="text-danger"><?php
                        if (isset($_SESSION['categoryError'])) {
                            echo $_SESSION['categoryError'];
                            unset($_SESSION['categoryError']);
                        }
                        ?></small>
            </div>
        </div>

        <div class="row gx-3 mb-3">
            <div class="col-md-6">
                <label for="brand" class="form-label">Brand</label>
                <input type="text" class="form-control" id="brand" name="brand" value="<?php echo $brand; ?>" />
                <small class="text-danger"><?php
                        if (isset($_SESSION['brandError'])) {
                            echo $_SESSION['brandError'];
                            unset($_SESSION['brandError']);
                        }
                        ?></small>
            </div>
            <div class="col-md-6">
                <label for="scent" class="form-label">Scent Type</label>
                <select class="form-select" id="scent" name="scent_type">
                    <option value="">-- choose scent type --</option>
                    <?php
                    foreach ($scentTypes as $scent) {
                        if ($scentType === $scent) {
                            echo "<option value='{$scent}' selected>{$scent}</option>";
                        } else {
                            echo "<option value='{$scent}'>{$scent}</option>";
                        }
                    }
                    ?>
                </select>
                <small class="text-danger"><?php
                        if (isset($_SESSION['scentError'])) {
                            echo $_SESSION['scentError'];
                            unset($_SESSION['scentError']);
                        }
                        ?></small>
            </div>
        </div>

        <div class="row gx-3 mb-3">
            <div class="col-md-3">
                <label for="size" class="form-label">Size</label>
                <input type="number" class="form-control" id="size" name="size" value="<?php echo $size; ?>" />
            </div>
            <div class="col-md-3">
                <label for="unit" class="form-label">Unit</label>
                <select class="form-select" id="unit" name="unit">
                    <?php
                    foreach ($units as $unit) {
                        if ($unitValue === $unit) {
                            echo "<option value='{$unit}' selected>{$unit}</option>";
                        } else {
                            echo "<option value='{$unit}'>{$unit}</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">&nbsp;</label>
                <p class="small text-muted mb-0">ml for liquids, g for candles and wax melts, pcs for items sold per piece</p>
            </div>
            <div class="col-12">
                <small class="text-danger"><?php
                        if (isset($_SESSION['sizeError'])) {
                            echo $_SESSION['sizeError'];
                            unset($_SESSION['sizeError']);
                        }
                        ?></small>
            </div>
        </div>

        <div class="row gx-3 mb-3">
            <div class="col-md-4">
                <label for="cost" class="form-label">Cost Price</label>
                <input type="text" class="form-control" id="cost" name="cost_price" value="<?php echo $cost; ?>" />
                <small class="text-danger"><?php
                        if (isset($_SESSION['costError'])) {
                            echo $_SESSION['costError'];
                            unset($_SESSION['costError']);
                        }
                        ?></small>
            </div>
            <div class="col-md-4">
                <label for="sell" class="form-label">Selling Price</label>
                <input type="text" class="form-control" id="sell" name="sell_price" value="<?php echo $sell; ?>" />
                <small class="text-danger"><?php
                        if (isset($_SESSION['sellError'])) {
                            echo $_SESSION['sellError'];
                            unset($_SESSION['sellError']);
                        }
                        ?></small>
            </div>
            <div class="col-md-4">
                <label for="qty" class="form-label">Stock</label>
                <input type="number" class="form-control" id="qty" name="quantity" value="<?php echo $qty; ?>" />
                <small class="text-danger"><?php
                        if (isset($_SESSION['qtyError'])) {
                            echo $_SESSION['qtyError'];
                            unset($_SESSION['qtyError']);
                        }
                        ?></small>
            </div>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description (optional)</label>
            <textarea class="form-control" id="description" name="description" rows="2" maxlength="255"><?php echo $description; ?></textarea>
            <small class="text-danger"><?php
                    if (isset($_SESSION['descError'])) {
                        echo $_SESSION['descError'];
                        unset($_SESSION['descError']);
                    }
                    ?></small>
        </div>

        <div class="mb-3">
            <label class="form-label">Current Photo</label><br />
            <img src="<?php echo $product['img_path']; ?>" width="120" height="120" class="product-photo border mb-2" />
            <br />
            <label for="photo" class="form-label">New Photo (JPG or PNG only, leave empty to keep the current photo)</label>
            <input class="form-control" type="file" id="photo" name="img_path" accept=".jpg,.jpeg,.png" />
            <small class="text-danger"><?php
                    if (isset($_SESSION['imageError'])) {
                        echo $_SESSION['imageError'];
                        unset($_SESSION['imageError']);
                    }
                    ?></small>
        </div>

        <button type="submit" class="btn btn-primary" name="submit">Save Changes</button>
        <a href="index.php" role="button" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php
include('../includes/footer.php');
?>
