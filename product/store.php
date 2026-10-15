<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// only admins can add products
require_admin();

if (isset($_POST['submit'])) {
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

    // the main photo is required, and only real JPG and PNG files are accepted
    $mainPhoto = isset($_FILES['img_path']) ? $_FILES['img_path'] : null;
    $photoError = photo_upload_error($mainPhoto, true);
    if ($photoError !== '') {
        $_SESSION['imageError'] = $photoError;
        $hasErrors = true;
    }

    // MP1: more photos are optional; each one is checked the same way
    $extraPhotos = uploaded_files_list(isset($_FILES['photos']) ? $_FILES['photos'] : null);
    if (count($extraPhotos) > MAX_PRODUCT_PHOTOS) {
        $_SESSION['photosError'] = 'You can add up to ' . MAX_PRODUCT_PHOTOS . ' more photos.';
        $hasErrors = true;
    } else {
        foreach ($extraPhotos as $file) {
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
        header("Location: create.php");
        exit();
    }

    $category_id = (int)$categoryInput;
    $size = (int)$sizeInput;
    $cost = (float)$costInput;
    $sell = (float)$sellInput;
    $qty = (int)$qtyInput;

    // save the photos in the images folder, each with a new random name so files do not overwrite each other
    $saved = array();
    foreach (array_merge(array($mainPhoto), $extraPhotos) as $file) {
        $fileName = save_photo($file, __DIR__ . '/images');
        if ($fileName === '') {
            foreach ($saved as $path) {
                delete_photo_file(__DIR__ . '/' . $path);
            }
            $_SESSION['message'] = 'The photos could not be saved. Please try again.';
            header("Location: create.php");
            exit();
        }
        $saved[] = 'images/' . $fileName;
    }
    $target = $saved[0];                    // the main photo
    $extraPaths = array_slice($saved, 1);   // the other photos

    // save the product, its stock and its extra photos together
    mysqli_begin_transaction($conn);

    try {
        $sql = "INSERT INTO product (category_id, name, brand, scent_type, size, unit, description, cost_price, sell_price, img_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt1 = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt1, 'isssissdds', $category_id, $name, $brand, $scent, $size, $unit, $description, $cost, $sell, $target);
        mysqli_stmt_execute($stmt1);
        $product_id = mysqli_insert_id($conn);

        $sql = "INSERT INTO stock (product_id, quantity) VALUES (?, ?)";
        $stmt2 = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt2, 'ii', $product_id, $qty);
        mysqli_stmt_execute($stmt2);

        // one row in product_images per extra photo
        $sql = "INSERT INTO product_images (product_id, img_path) VALUES (?, ?)";
        $stmt3 = mysqli_prepare($conn, $sql);
        foreach ($extraPaths as $path) {
            mysqli_stmt_bind_param($stmt3, 'is', $product_id, $path);
            mysqli_stmt_execute($stmt3);
        }

        mysqli_commit($conn);
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn);
        // the product was not saved, so its photo files are not needed
        foreach ($saved as $path) {
            delete_photo_file(__DIR__ . '/' . $path);
        }
        $_SESSION['message'] = 'Could not save the product: ' . $e->getMessage();
        header("Location: create.php");
        exit();
    }

    $_SESSION['success'] = 'Product added successfully with ' . count($saved) . ' photo' . (count($saved) === 1 ? '' : 's');
    header("Location: index.php");
    exit();
}

header("Location: create.php");
exit();
