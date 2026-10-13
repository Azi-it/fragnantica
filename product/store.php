<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');
include('../includes/product_form.php');

// only admins can add products
require_admin();

if (!isset($_POST['submit'])) {
    header("Location: create.php");
    exit();
}

// validation (all in PHP): the fields, the main photo (required) and the extra photos (optional)
list($data, $errors, $old) = validate_product_input($conn);

if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
    keep_form('product_create', $errors, $old);
    header("Location: create.php");
    exit();
}

$mainPhoto = isset($_FILES['img_path']) ? $_FILES['img_path'] : null;
$photoError = photo_upload_error($mainPhoto, true);
if ($photoError !== '') {
    $errors['photo'] = $photoError;
}
list($extraFiles, $extraError) = validate_extra_photos(MAX_PRODUCT_PHOTOS);
if ($extraError !== '') {
    $errors['photos'] = $extraError;
}

if ($errors) {
    // keep what the admin typed so the form can be filled again (files cannot be kept)
    keep_form('product_create', $errors, $old);
    header("Location: create.php");
    exit();
}

// save the photos in the images folder with new names so files do not overwrite each other
$folder = __DIR__ . '/images';
$saved = array();
$mainName = save_photo($mainPhoto, $folder);
if ($mainName !== '') {
    $saved[] = 'images/' . $mainName;
    foreach ($extraFiles as $file) {
        $name = save_photo($file, $folder);
        if ($name === '') {
            $mainName = '';
            break;
        }
        $saved[] = 'images/' . $name;
    }
}
if ($mainName === '') {
    foreach ($saved as $path) {
        delete_photo_file(__DIR__ . '/' . $path);
    }
    $_SESSION['message'] = 'The photos could not be saved. Please try again.';
    keep_form('product_create', array(), $old);
    header("Location: create.php");
    exit();
}
$target = $saved[0];

// save the product, its stock and its extra photos together
mysqli_begin_transaction($conn);

try {
    $sql = "INSERT INTO product (category_id, name, brand, scent_type, size, unit, description, cost_price, sell_price, img_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt1 = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt1, 'isssissdds', $data['category_id'], $data['name'], $data['brand'], $data['scent_type'], $data['size'],
        $data['unit'], $data['description'], $data['cost_price'], $data['sell_price'], $target);
    mysqli_stmt_execute($stmt1);
    $product_id = mysqli_insert_id($conn);

    $sql = "INSERT INTO stock (product_id, quantity) VALUES (?, ?)";
    $stmt2 = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt2, 'ii', $product_id, $data['quantity']);
    mysqli_stmt_execute($stmt2);

    $sql = "INSERT INTO product_images (product_id, img_path) VALUES (?, ?)";
    $stmt3 = mysqli_prepare($conn, $sql);
    foreach (array_slice($saved, 1) as $path) {
        mysqli_stmt_bind_param($stmt3, 'is', $product_id, $path);
        mysqli_stmt_execute($stmt3);
    }

    mysqli_commit($conn);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    foreach ($saved as $path) {
        delete_photo_file(__DIR__ . '/' . $path);
    }
    $_SESSION['message'] = 'Could not save the product. Please try again.';
    keep_form('product_create', array(), $old);
    header("Location: create.php");
    exit();
}

$extraCount = count($saved) - 1;
$_SESSION['success'] = 'Product added successfully' . ($extraCount > 0 ? " with {$extraCount} extra photo" . ($extraCount === 1 ? '' : 's') : '') . '.';
header("Location: index.php");
exit();
