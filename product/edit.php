<?php
session_start();
include('../includes/config.php');
include('../includes/adminCheck.php');

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$sql = "SELECT p.*, s.quantity FROM product p INNER JOIN stock s USING (product_id) WHERE p.product_id = ?";
$result = mysqli_execute_query($conn, $sql, [$product_id]);
$item = mysqli_fetch_assoc($result);
if (!$item) {
    $_SESSION['message'] = 'product not found';
    header("Location: index.php");
    exit();
}

// if saving failed, show what the admin typed instead
if (isset($_SESSION['prodName'])) {
    $item['name'] = $_SESSION['prodName'];
    $item['category_id'] = $_SESSION['category'];
    $item['brand'] = $_SESSION['brand'];
    $item['size'] = $_SESSION['size'];
    $item['description'] = $_SESSION['desc'];
    $item['cost_price'] = $_SESSION['cost'];
    $item['sell_price'] = $_SESSION['sell'];
    $item['quantity'] = $_SESSION['qty'];
    unset($_SESSION['prodName'], $_SESSION['category'], $_SESSION['brand'], $_SESSION['size'], $_SESSION['desc'], $_SESSION['cost'], $_SESSION['sell'], $_SESSION['qty']);
}

// drop down select
$category_result = mysqli_query($conn, "SELECT category_id, name FROM category ORDER BY name");
// the other photos of this product
$photo_result = mysqli_execute_query($conn, "SELECT image_id, img_path FROM product_images WHERE product_id = ?", [$product_id]);

include('../includes/header.php');
?>
<div class="container mt-4">
    <h2>Edit Product</h2>
    <?php include('../includes/alert.php'); ?>
    <form method="POST" action="update.php" enctype="multipart/form-data" novalidate>
        <div class="form-group">
            <label for="name">Product Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?php echo $item['name']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['nameError'])) {
                    echo $_SESSION['nameError'];
                    unset($_SESSION['nameError']);
                }
                ?></small><br>

            <label for="category">Category</label>
            <select name="category_id" id="category" class="form-control">
                <?php
                while ($row = mysqli_fetch_assoc($category_result)) {
                    if ($item['category_id'] == $row['category_id']) {
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
            <input type="text" class="form-control" id="brand" name="brand" value="<?php echo $item['brand']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['brandError'])) {
                    echo $_SESSION['brandError'];
                    unset($_SESSION['brandError']);
                }
                ?></small><br>

            <label for="size">Size</label>
            <input type="text" class="form-control" id="size" name="size" value="<?php echo $item['size']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['sizeError'])) {
                    echo $_SESSION['sizeError'];
                    unset($_SESSION['sizeError']);
                }
                ?></small><br>

            <label for="desc">Description</label>
            <textarea class="form-control" id="desc" name="description" rows="3"><?php echo $item['description']; ?></textarea>
            <small class="text-danger"><?php
                if (isset($_SESSION['descError'])) {
                    echo $_SESSION['descError'];
                    unset($_SESSION['descError']);
                }
                ?></small><br>

            <label for="cost">Cost Price</label>
            <input type="text" class="form-control" id="cost" name="cost_price" value="<?php echo $item['cost_price']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['costError'])) {
                    echo $_SESSION['costError'];
                    unset($_SESSION['costError']);
                }
                ?></small><br>

            <label for="sell">Sell Price</label>
            <input type="text" class="form-control" id="sell" name="sell_price" value="<?php echo $item['sell_price']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['sellError'])) {
                    echo $_SESSION['sellError'];
                    unset($_SESSION['sellError']);
                }
                ?></small><br>

            <label for="qty">Quantity</label>
            <input type="text" class="form-control" id="qty" name="quantity" value="<?php echo $item['quantity']; ?>" />
            <small class="text-danger"><?php
                if (isset($_SESSION['qtyError'])) {
                    echo $_SESSION['qtyError'];
                    unset($_SESSION['qtyError']);
                }
                ?></small><br>

            <label>Main Photo</label><br>
            <img src="<?php echo $item['img_path']; ?>" class="extra-photo mb-2"><br>
            <label for="img_path">Choose a new main photo (leave empty to keep this one)</label>
            <input class="form-control" type="file" id="img_path" name="img_path" />
            <small class="text-danger"><?php
                if (isset($_SESSION['imageError'])) {
                    echo $_SESSION['imageError'];
                    unset($_SESSION['imageError']);
                }
                ?></small><br>

            <label>More Photos (tick a photo to remove it)</label><br>
            <?php
            if (mysqli_num_rows($photo_result) === 0) {
                echo "<p class='text-muted'>no other photos yet</p>";
            }
            while ($photo = mysqli_fetch_assoc($photo_result)) {
                echo "<label class='me-3 mb-2'><img src='{$photo['img_path']}' class='extra-photo'><br>
                    <input type='checkbox' name='delete_photos[]' value='{$photo['image_id']}' /> remove</label>";
            }
            ?>
            <br>
            <label for="photos">Add more photos</label>
            <input class="form-control" type="file" id="photos" name="photos[]" multiple />
            <small class="text-danger"><?php
                if (isset($_SESSION['photosError'])) {
                    echo $_SESSION['photosError'];
                    unset($_SESSION['photosError']);
                }
                ?></small><br>
        </div>
        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>" />
        <button type="submit" class="btn btn-primary" name="submit">Submit</button>
        <a href="index.php" role="button" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php
include('../includes/footer.php');
?>
