<?php
session_start();
include('../includes/config.php');

// only admins can add products
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'admin access only, please log in with an admin account';
    header("Location: ../user/login.php");
    exit();
}

include('../includes/header.php');

// categories come from the category table
$categories = mysqli_query($conn, "SELECT category_id, name FROM category ORDER BY category_id");

$scentTypes = array('Floral', 'Woody', 'Fresh', 'Citrus', 'Fruity', 'Oriental', 'Aquatic', 'Gourmand', 'Spicy', 'Herbal', 'Musky');
$units = array('ml', 'g', 'pcs');

?>
<div class="container mt-4">
    <h2>Add Product</h2>
    <?php include('../includes/alert.php'); ?>
    <form method="POST" action="store.php" enctype="multipart/form-data">
        <div class="row gx-3 mb-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Product Name</label>
                <input type="text" class="form-control" id="name" placeholder="e.g. Lavender Dreams" name="name"
                    value="<?php if (isset($_SESSION['productName'])) echo $_SESSION['productName']; ?>" />
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
                        if (isset($_SESSION['categoryId']) && $_SESSION['categoryId'] == $cat['category_id']) {
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
                <input type="text" class="form-control" id="brand" placeholder="e.g. Dior" name="brand"
                    value="<?php if (isset($_SESSION['brand'])) echo $_SESSION['brand']; ?>" />
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
                        if (isset($_SESSION['scent']) && $_SESSION['scent'] === $scent) {
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
                <input type="number" class="form-control" id="size" placeholder="e.g. 100" name="size"
                    value="<?php if (isset($_SESSION['size'])) echo $_SESSION['size']; ?>" />
            </div>
            <div class="col-md-3">
                <label for="unit" class="form-label">Unit</label>
                <select class="form-select" id="unit" name="unit">
                    <?php
                    foreach ($units as $unit) {
                        if (isset($_SESSION['unit']) && $_SESSION['unit'] === $unit) {
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
                <input type="text" class="form-control" id="cost" placeholder="Enter cost price" name="cost_price"
                    value="<?php if (isset($_SESSION['cost'])) echo $_SESSION['cost']; ?>" />
                <small class="text-danger"><?php
                        if (isset($_SESSION['costError'])) {
                            echo $_SESSION['costError'];
                            unset($_SESSION['costError']);
                        }
                        ?></small>
            </div>
            <div class="col-md-4">
                <label for="sell" class="form-label">Selling Price</label>
                <input type="text" class="form-control" id="sell" placeholder="Enter selling price" name="sell_price"
                    value="<?php if (isset($_SESSION['sell'])) echo $_SESSION['sell']; ?>" />
                <small class="text-danger"><?php
                        if (isset($_SESSION['sellError'])) {
                            echo $_SESSION['sellError'];
                            unset($_SESSION['sellError']);
                        }
                        ?></small>
            </div>
            <div class="col-md-4">
                <label for="qty" class="form-label">Initial Stock</label>
                <input type="number" class="form-control" id="qty" placeholder="0" name="quantity"
                    value="<?php if (isset($_SESSION['qty'])) echo $_SESSION['qty']; ?>" />
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
            <textarea class="form-control" id="description" name="description" rows="2" maxlength="255"
                placeholder="e.g. burn time 40 hours, lasts up to 30 days"><?php if (isset($_SESSION['description'])) echo $_SESSION['description']; ?></textarea>
            <small class="text-danger"><?php
                    if (isset($_SESSION['descError'])) {
                        echo $_SESSION['descError'];
                        unset($_SESSION['descError']);
                    }
                    ?></small>
        </div>

        <div class="mb-3">
            <label for="photo" class="form-label">Photo (JPG or PNG only)</label>
            <input class="form-control" type="file" id="photo" name="img_path" accept=".jpg,.jpeg,.png" />
            <small class="text-danger"><?php
                    if (isset($_SESSION['imageError'])) {
                        echo $_SESSION['imageError'];
                        unset($_SESSION['imageError']);
                    }
                    ?></small>
        </div>

        <button type="submit" class="btn btn-primary" name="submit">Submit</button>
        <a href="index.php" role="button" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php
// the typed values are only shown once
unset($_SESSION['productName'], $_SESSION['categoryId'], $_SESSION['brand'], $_SESSION['scent'], $_SESSION['size'], $_SESSION['unit'], $_SESSION['description'], $_SESSION['cost'], $_SESSION['sell'], $_SESSION['qty']);

include('../includes/footer.php');
?>
