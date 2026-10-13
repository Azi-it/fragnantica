<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');
include('../includes/product_form.php');

// only admins can edit products
require_admin();

if (!isset($_POST['submit'])) {
    header("Location: index.php");
    exit();
}

$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

// get the current photos so they can be kept, replaced or removed
$row = $product_id ? mysqli_fetch_assoc(mysqli_execute_query($conn, "SELECT img_path FROM product WHERE product_id = ? LIMIT 1", [$product_id])) : null;
if (!$row) {
    $_SESSION['message'] = 'product not found';
    header("Location: index.php");
    exit();
}
$back = "edit.php?id={$product_id}";
$oldPhoto = $row['img_path'];
$extraPhotos = mysqli_fetch_all(mysqli_execute_query($conn,
    "SELECT image_id, img_path FROM product_images WHERE product_id = ?", [$product_id]), MYSQLI_ASSOC);

// validation (all in PHP)
list($data, $errors, $old) = validate_product_input($conn);

if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
    keep_form('product_edit_' . $product_id, $errors, $old);
    header("Location: {$back}");
    exit();
}

// a new main photo is optional, so only check it if one was chosen
$mainPhoto = isset($_FILES['img_path']) ? $_FILES['img_path'] : null;
$photoError = photo_upload_error($mainPhoto, false);
if ($photoError !== '') {
    $errors['photo'] = $photoError;
}
$newMain = $photoError === '' && is_array($mainPhoto) && $mainPhoto['error'] === UPLOAD_ERR_OK;

// extra photos ticked for removal (only this product's own photos count)
$removeIds = array();
foreach ((array)($_POST['delete_photos'] ?? array()) as $id) {
    foreach ($extraPhotos as $photo) {
        if ((string)$photo['image_id'] === (string)$id) {
            $removeIds[] = (int)$photo['image_id'];
        }
    }
}
list($extraFiles, $extraError) = validate_extra_photos(MAX_PRODUCT_PHOTOS - count($extraPhotos) + count($removeIds));
if ($extraError !== '') {
    $errors['photos'] = $extraError;
}

if ($errors) {
    // keep what the admin typed so the form can be filled again
    keep_form('product_edit_' . $product_id, $errors, $old);
    header("Location: {$back}");
    exit();
}

// save the new photo files first
$folder = __DIR__ . '/images';
$saved = array();
$ok = true;
$target = $oldPhoto;
if ($newMain) {
    $name = save_photo($mainPhoto, $folder);
    $ok = $name !== '';
    if ($ok) {
        $target = 'images/' . $name;
        $saved[] = $target;
    }
}
$newExtras = array();
foreach ($extraFiles as $file) {
    if (!$ok) {
        break;
    }
    $name = save_photo($file, $folder);
    $ok = $name !== '';
    if ($ok) {
        $newExtras[] = 'images/' . $name;
        $saved[] = 'images/' . $name;
    }
}
if (!$ok) {
    foreach ($saved as $path) {
        delete_photo_file(__DIR__ . '/' . $path);
    }
    $_SESSION['message'] = 'The photos could not be saved. Please try again.';
    keep_form('product_edit_' . $product_id, array(), $old);
    header("Location: {$back}");
    exit();
}

// update the product, its stock and its photos together
mysqli_begin_transaction($conn);

try {
    $sql = "UPDATE product SET category_id=?, name=?, brand=?, scent_type=?, size=?, unit=?, description=?, cost_price=?, sell_price=?, img_path=? WHERE product_id=?";
    $stmt1 = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt1, 'isssissddsi', $data['category_id'], $data['name'], $data['brand'], $data['scent_type'], $data['size'],
        $data['unit'], $data['description'], $data['cost_price'], $data['sell_price'], $target, $product_id);
    mysqli_stmt_execute($stmt1);

    $sql = "UPDATE stock SET quantity=? WHERE product_id=?";
    $stmt2 = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt2, 'ii', $data['quantity'], $product_id);
    mysqli_stmt_execute($stmt2);

    foreach ($removeIds as $image_id) {
        mysqli_execute_query($conn, "DELETE FROM product_images WHERE image_id = ? AND product_id = ?", [$image_id, $product_id]);
    }
    foreach ($newExtras as $path) {
        mysqli_execute_query($conn, "INSERT INTO product_images (product_id, img_path) VALUES (?, ?)", [$product_id, $path]);
    }

    mysqli_commit($conn);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    foreach ($saved as $path) {
        delete_photo_file(__DIR__ . '/' . $path);
    }
    $_SESSION['message'] = 'Could not update the product. Please try again.';
    keep_form('product_edit_' . $product_id, array(), $old);
    header("Location: {$back}");
    exit();
}

// the replaced and removed photo files are not needed anymore
if ($newMain) {
    delete_photo_file(__DIR__ . '/' . $oldPhoto);
}
foreach ($extraPhotos as $photo) {
    if (in_array((int)$photo['image_id'], $removeIds, true)) {
        delete_photo_file(__DIR__ . '/' . $photo['img_path']);
    }
}

$_SESSION['success'] = 'Product updated successfully';
header("Location: index.php");
exit();
