<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// only admins can edit products
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'admin access only, please log in with an admin account';
    header("Location: ../user/login.php");
    exit();
}

if (isset($_POST['submit'])) {
    $product_id = (int)$_POST['product_id'];
    $name = trim(strip_tags($_POST['name']));
    $categoryInput = trim($_POST['category_id']);
    $brand = trim(strip_tags($_POST['brand']));
    $scent = trim($_POST['scent_type']);
    $sizeInput = trim($_POST['size']);
    $unit = trim($_POST['unit']);
    $description = trim(strip_tags($_POST['description']));
    $costInput = trim($_POST['cost_price']);
    $sellInput = trim($_POST['sell_price']);
    $qtyInput = trim($_POST['quantity']);
    $hasErrors = false;

    $units = array('ml', 'g', 'pcs');

    //validation
    if ($name === '') {
        $_SESSION['nameError'] = 'Please input the product name';
        $hasErrors = true;
    }
    if ($categoryInput === '' || filter_var($categoryInput, FILTER_VALIDATE_INT) === false) {
        $_SESSION['categoryError'] = 'Please choose a category';
        $hasErrors = true;
    }
    if ($brand === '') {
        $_SESSION['brandError'] = 'Please input the brand';
        $hasErrors = true;
    }
    if ($scent === '') {
        $_SESSION['scentError'] = 'Please choose a scent type';
        $hasErrors = true;
    }
    if ($sizeInput === '' || filter_var($sizeInput, FILTER_VALIDATE_INT) === false || (int)$sizeInput <= 0) {
        $_SESSION['sizeError'] = 'Please enter a valid size';
        $hasErrors = true;
    } else if (!in_array($unit, $units)) {
        $_SESSION['sizeError'] = 'Please choose a unit';
        $hasErrors = true;
    }
    if (strlen($description) > 255) {
        $_SESSION['descError'] = 'Description should be 255 characters or less';
        $hasErrors = true;
    }
    if ($costInput === '' || !is_numeric($costInput) || $costInput <= 0) {
        $_SESSION['costError'] = 'Please enter a valid cost price';
        $hasErrors = true;
    }
    if ($sellInput === '' || !is_numeric($sellInput) || $sellInput <= 0) {
        $_SESSION['sellError'] = 'Please enter a valid selling price';
        $hasErrors = true;
    }
    if ($qtyInput === '' || filter_var($qtyInput, FILTER_VALIDATE_INT) === false || (int)$qtyInput < 0) {
        $_SESSION['qtyError'] = 'Please enter a valid stock quantity';
        $hasErrors = true;
    }

    // a new main photo is optional, so only check it if one was chosen
    $mainPhoto = isset($_FILES['img_path']) ? $_FILES['img_path'] : null;
    $photoError = photo_upload_error($mainPhoto, false);
    if ($photoError !== '') {
        $_SESSION['imageError'] = $photoError;
        $hasErrors = true;
    }
    $newMain = $photoError === '' && is_array($mainPhoto) && $mainPhoto['error'] === UPLOAD_ERR_OK;

    // MP1: the extra photos this product has now, and the ones ticked for removal
    $sql = "SELECT image_id, img_path FROM product_images WHERE product_id = ?";
    $currentExtras = mysqli_fetch_all(mysqli_execute_query($conn, $sql, [$product_id]), MYSQLI_ASSOC);
    $removeIds = array();
    $removeFiles = array();
    $ticked = isset($_POST['delete_photos']) && is_array($_POST['delete_photos']) ? $_POST['delete_photos'] : array();
    foreach ($currentExtras as $photo) {
        // only this product's own photos can be removed
        if (in_array((string)$photo['image_id'], $ticked, true)) {
            $removeIds[] = (int)$photo['image_id'];
            $removeFiles[] = $photo['img_path'];
        }
    }

    // MP1: new extra photos, as long as the product stays within the limit
    $newExtras = uploaded_files_list(isset($_FILES['photos']) ? $_FILES['photos'] : null);
    $slotsLeft = MAX_PRODUCT_PHOTOS - count($currentExtras) + count($removeIds);
    if (count($newExtras) > $slotsLeft) {
        $_SESSION['photosError'] = $slotsLeft > 0
            ? "You can add {$slotsLeft} more photo" . ($slotsLeft === 1 ? '' : 's') . ' (up to ' . MAX_PRODUCT_PHOTOS . ' per product).'
            : 'This product already has ' . MAX_PRODUCT_PHOTOS . ' extra photos. Remove some first.';
        $hasErrors = true;
    } else {
        foreach ($newExtras as $file) {
            $error = photo_upload_error($file, true);
            if ($error !== '') {
                $_SESSION['photosError'] = $file['name'] . ': ' . $error;
                $hasErrors = true;
                break;
            }
        }
    }

    if ($hasErrors) {
        // keep what the admin typed so the form can be filled again
        $_SESSION['productName'] = $name;
        $_SESSION['categoryId'] = $categoryInput;
        $_SESSION['brand'] = $brand;
        $_SESSION['scent'] = $scent;
        $_SESSION['size'] = $sizeInput;
        $_SESSION['unit'] = $unit;
        $_SESSION['description'] = $description;
        $_SESSION['cost'] = $costInput;
        $_SESSION['sell'] = $sellInput;
        $_SESSION['qty'] = $qtyInput;
        header("Location: edit.php?id={$product_id}");
        exit();
    }

    // get the current main photo so it can be kept or replaced
    $sql = "SELECT img_path FROM product WHERE product_id = ? LIMIT 1";
    $row = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$product_id]));

    if (!$row) {
        $_SESSION['message'] = 'product not found';
        header("Location: index.php");
        exit();
    }
    $oldPhoto = $row['img_path'];

    // save the new photo files first, each with a new random name
    $saved = array();
    $toSave = $newMain ? array_merge(array($mainPhoto), $newExtras) : $newExtras;
    foreach ($toSave as $file) {
        $fileName = save_photo($file, __DIR__ . '/images');
        if ($fileName === '') {
            foreach ($saved as $path) {
                delete_photo_file(__DIR__ . '/' . $path);
            }
            $_SESSION['message'] = 'The photos could not be saved. Please try again.';
            header("Location: edit.php?id={$product_id}");
            exit();
        }
        $saved[] = 'images/' . $fileName;
    }
    $target = $newMain ? $saved[0] : $oldPhoto;
    $extraPaths = $newMain ? array_slice($saved, 1) : $saved;

    $category_id = (int)$categoryInput;
    $size = (int)$sizeInput;
    $cost = (float)$costInput;
    $sell = (float)$sellInput;
    $qty = (int)$qtyInput;

    // update the product, its stock and its photos together
    mysqli_begin_transaction($conn);

    try {
        $sql = "UPDATE product SET category_id=?, name=?, brand=?, scent_type=?, size=?, unit=?, description=?, cost_price=?, sell_price=?, img_path=? WHERE product_id=?";
        $stmt1 = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt1, 'isssissddsi', $category_id, $name, $brand, $scent, $size, $unit, $description, $cost, $sell, $target, $product_id);
        mysqli_stmt_execute($stmt1);

        $sql = "UPDATE stock SET quantity=? WHERE product_id=?";
        $stmt2 = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt2, 'ii', $qty, $product_id);
        mysqli_stmt_execute($stmt2);

        // MP1: remove the ticked photos and add the new ones
        foreach ($removeIds as $image_id) {
            mysqli_execute_query($conn, "DELETE FROM product_images WHERE image_id = ? AND product_id = ?", [$image_id, $product_id]);
        }
        foreach ($extraPaths as $path) {
            mysqli_execute_query($conn, "INSERT INTO product_images (product_id, img_path) VALUES (?, ?)", [$product_id, $path]);
        }

        mysqli_commit($conn);
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn);
        // nothing changed, so the new photo files are not needed
        foreach ($saved as $path) {
            delete_photo_file(__DIR__ . '/' . $path);
        }
        $_SESSION['message'] = 'Could not update the product: ' . $e->getMessage();
        header("Location: edit.php?id={$product_id}");
        exit();
    }

    // the replaced and removed photo files are not needed anymore
    if ($newMain) {
        delete_photo_file(__DIR__ . '/' . $oldPhoto);
    }
    foreach ($removeFiles as $path) {
        delete_photo_file(__DIR__ . '/' . $path);
    }

    $_SESSION['success'] = 'Product updated successfully';
    header("Location: index.php");
    exit();
}

header("Location: index.php");
exit();
