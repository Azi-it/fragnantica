<?php
session_start();
include('../includes/config.php');
include('../includes/adminCheck.php');
include('../includes/header.php');

// drop down select
$category_result = mysqli_query($conn, "SELECT category_id, name FROM category ORDER BY name");
?>
<div class="container mt-4">
    <h2>Add Product</h2>
    <?php include('../includes/alert.php'); ?>
    <!-- novalidate: the browser does not check the form, store.php does -->
    <form method="POST" action="store.php" enctype="multipart/form-data" novalidate>
        <div class="form-group">
            <label for="name">Product Name</label>
            <input type="text" class="form-control" id="name" placeholder="Enter product name" name="name"
                value="<?php if (isset($_SESSION['prodName'])) echo $_SESSION['prodName']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['nameError'])) {
                    echo $_SESSION['nameError'];
                    unset($_SESSION['nameError']);
                }
                ?></small><br>

            <label for="category">Category</label>
            <select name="category_id" id="category" class="form-control">
                <option value="">Choose a category</option>
                <?php
                while ($row = mysqli_fetch_assoc($category_result)) {
                    if (isset($_SESSION['category']) && $_SESSION['category'] == $row['category_id']) {
                        echo "<option value='{$row['category_id']}' selected>{$row['name']}</option>";
                    } else {
                        echo "<option value='{$row['category_id']}'>{$row['name']}</option>";
                    }
                }
                ?>
            </select>
            <small class="text-danger"><?php
                if (isset($_SESSION['categoryError'])) {
                    echo $_SESSION['categoryError'];
                    unset($_SESSION['categoryError']);
                }
                ?></small><br>

            <label for="brand">Brand</label>
            <input type="text" class="form-control" id="brand" placeholder="Enter brand" name="brand"
                value="<?php if (isset($_SESSION['brand'])) echo $_SESSION['brand']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['brandError'])) {
                    echo $_SESSION['brandError'];
                    unset($_SESSION['brandError']);
                }
                ?></small><br>

            <label for="size">Size</label>
            <input type="text" class="form-control" id="size" placeholder="e.g. 100 ml" name="size"
                value="<?php if (isset($_SESSION['size'])) echo $_SESSION['size']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['sizeError'])) {
                    echo $_SESSION['sizeError'];
                    unset($_SESSION['sizeError']);
                }
                ?></small><br>

            <label for="desc">Description</label>
            <textarea class="form-control" id="desc" placeholder="Enter product description" name="description" rows="3"><?php if (isset($_SESSION['desc'])) echo $_SESSION['desc']; ?></textarea>
            <small class="text-danger"><?php
                if (isset($_SESSION['descError'])) {
                    echo $_SESSION['descError'];
                    unset($_SESSION['descError']);
                }
                ?></small><br>

            <label for="cost">Cost Price</label>
            <input type="text" class="form-control" id="cost" placeholder="Enter cost price" name="cost_price"
                value="<?php if (isset($_SESSION['cost'])) echo $_SESSION['cost']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['costError'])) {
                    echo $_SESSION['costError'];
                    unset($_SESSION['costError']);
                }
                ?></small><br>

            <label for="sell">Sell Price</label>
            <input type="text" class="form-control" id="sell" placeholder="Enter sell price" name="sell_price"
                value="<?php if (isset($_SESSION['sell'])) echo $_SESSION['sell']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['sellError'])) {
                    echo $_SESSION['sellError'];
                    unset($_SESSION['sellError']);
                }
                ?></small><br>

            <label for="qty">Quantity</label>
            <input type="text" class="form-control" id="qty" placeholder="0" name="quantity"
                value="<?php if (isset($_SESSION['qty'])) echo $_SESSION['qty']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['qtyError'])) {
                    echo $_SESSION['qtyError'];
                    unset($_SESSION['qtyError']);
                }
                ?></small><br>

            <label for="img_path">Main Photo (JPG or PNG)</label>
            <input class="form-control" type="file" id="img_path" name="img_path" />
            <small class="text-danger"><?php
                if (isset($_SESSION['imageError'])) {
                    echo $_SESSION['imageError'];
                    unset($_SESSION['imageError']);
                }
                ?></small><br>

            <label for="photos">More Photos (optional, you can choose many)</label>
            <input class="form-control" type="file" id="photos" name="photos[]" multiple />
            <small class="text-danger"><?php
                if (isset($_SESSION['photosError'])) {
                    echo $_SESSION['photosError'];
                    unset($_SESSION['photosError']);
                }
                ?></small><br>
        </div>
        <button type="submit" class="btn btn-primary" name="submit">Submit</button>
        <a href="index.php" role="button" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php
// the typed values are shown once
unset($_SESSION['prodName'], $_SESSION['category'], $_SESSION['brand'], $_SESSION['size'], $_SESSION['desc'], $_SESSION['cost'], $_SESSION['sell'], $_SESSION['qty']);
include('../includes/footer.php');
?>
