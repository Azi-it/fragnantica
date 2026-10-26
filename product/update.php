<?php
session_start();
include('../includes/config.php');
include('../includes/adminCheck.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $product_id = (int)$_POST['product_id'];
    $name = trim(strip_tags($_POST['name']));
    $category_id = trim($_POST['category_id']);
    $brand = trim(strip_tags($_POST['brand']));
    $size = trim(strip_tags($_POST['size']));
    $desc = trim(strip_tags($_POST['description']));
    $costInput = trim($_POST['cost_price']);
    $sellInput = trim($_POST['sell_price']);
    $qtyInput = trim($_POST['quantity']);
    $hasErrors = false;

    // validation (PHP only, the form has novalidate)
    if ($name === '') {
        $_SESSION['nameError'] = 'Please input the product name';
        $hasErrors = true;
    } else if (strlen($name) > 100) {
        $_SESSION['nameError'] = 'The product name should be 100 characters or less';
        $hasErrors = true;
    }
    if ($category_id === '' || !is_numeric($category_id)) {
        $_SESSION['categoryError'] = 'Please choose a category';
        $hasErrors = true;
    }
    if ($brand === '') {
        $_SESSION['brandError'] = 'Please input the brand';
        $hasErrors = true;
    }
    if ($size === '') {
        $_SESSION['sizeError'] = 'Please input the size, e.g. 100 ml';
        $hasErrors = true;
    }
    if ($desc === '') {
        $_SESSION['descError'] = 'Please input a product description';
        $hasErrors = true;
    } else if (strlen($desc) > 255) {
        $_SESSION['descError'] = 'The description should be 255 characters or less';
        $hasErrors = true;
    }
    if ($costInput === '' || !is_numeric($costInput) || $costInput <= 0) {
        $_SESSION['costError'] = 'Please enter a valid cost price';
        $hasErrors = true;
    }
    if ($sellInput === '' || !is_numeric($sellInput) || $sellInput <= 0) {
        $_SESSION['sellError'] = 'Please enter a valid sell price';
        $hasErrors = true;
    } else if (is_numeric($costInput) && $sellInput < $costInput) {
        $_SESSION['sellError'] = 'The sell price should not be lower than the cost price';
        $hasErrors = true;
    }
    if ($qtyInput === '' || filter_var($qtyInput, FILTER_VALIDATE_INT) === false || (int)$qtyInput < 0) {
        $_SESSION['qtyError'] = 'Please enter a valid quantity';
        $hasErrors = true;
    }

    // a new main photo is optional
    $newPhoto = isset($_FILES['img_path']) && $_FILES['img_path']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($newPhoto && $_FILES['img_path']['type'] != "image/jpeg" && $_FILES['img_path']['type'] != "image/jpg" && $_FILES['img_path']['type'] != "image/png") {
        $_SESSION['imageError'] = 'wrong file type, please use JPG or PNG';
        $hasErrors = true;
    }

    // more photos are optional, but they must be JPG or PNG
    if (isset($_FILES['photos']) && $_FILES['photos']['name'][0] !== '') {
        foreach ($_FILES['photos']['type'] as $key => $type) {
            if ($type != "image/jpeg" && $type != "image/jpg" && $type != "image/png") {
                $_SESSION['photosError'] = "wrong file type: {$_FILES['photos']['name'][$key]}, please use JPG or PNG";
                $hasErrors = true;
            }
        }
    }

    if ($hasErrors) {
        $_SESSION['prodName'] = $name;
        $_SESSION['category'] = $category_id;
        $_SESSION['brand'] = $brand;
        $_SESSION['size'] = $size;
        $_SESSION['desc'] = $desc;
        $_SESSION['cost'] = $costInput;
        $_SESSION['sell'] = $sellInput;
        $_SESSION['qty'] = $qtyInput;
        header("Location: edit.php?id={$product_id}");
        exit();
    }

    $cost = (float)$costInput;
    $sell = (float)$sellInput;
    $qty = (int)$qtyInput;
    $category_id = (int)$category_id;

    mysqli_begin_transaction($conn);
    try {
        if ($newPhoto) {
            $source = $_FILES['img_path']['tmp_name'];
            $target = 'images/' . time() . '_' . $_FILES['img_path']['name'];
            move_uploaded_file($source, $target) or die("Couldn't copy");

            $sql = "UPDATE product SET category_id = ?, name = ?, brand = ?, size = ?, description = ?, cost_price = ?, sell_price = ?, img_path = ? WHERE product_id = ?";
            $stmt1 = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt1, 'issssddsi', $category_id, $name, $brand, $size, $desc, $cost, $sell, $target, $product_id);
        } else {
            // keep the old main photo
            $sql = "UPDATE product SET category_id = ?, name = ?, brand = ?, size = ?, description = ?, cost_price = ?, sell_price = ? WHERE product_id = ?";
            $stmt1 = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt1, 'issssddi', $category_id, $name, $brand, $size, $desc, $cost, $sell, $product_id);
        }
        mysqli_stmt_execute($stmt1);

        $sql = "UPDATE stock SET quantity = ? WHERE product_id = ?";
        $stmt2 = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt2, 'ii', $qty, $product_id);
        mysqli_stmt_execute($stmt2);

        // remove the ticked photos
        if (isset($_POST['delete_photos']) && is_array($_POST['delete_photos'])) {
            foreach ($_POST['delete_photos'] as $image_id) {
                mysqli_execute_query($conn, "DELETE FROM product_images WHERE image_id = ? AND product_id = ?", [$image_id, $product_id]);
            }
        }

        // save the new photos
        if (isset($_FILES['photos']) && $_FILES['photos']['name'][0] !== '') {
            $sql = "INSERT INTO product_images (product_id, img_path) VALUES (?, ?)";
            $stmt3 = mysqli_prepare($conn, $sql);
            foreach ($_FILES['photos']['name'] as $key => $photoName) {
                $photo = 'images/' . time() . '_' . $photoName;
                move_uploaded_file($_FILES['photos']['tmp_name'][$key], $photo);
                mysqli_stmt_bind_param($stmt3, 'is', $product_id, $photo);
                mysqli_stmt_execute($stmt3);
            }
        }

        mysqli_commit($conn);
        $_SESSION['success'] = 'product updated';
        header("Location: index.php");
        exit();
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = 'could not update the product';
        header("Location: edit.php?id={$product_id}");
        exit();
    }
}
header("Location: index.php");
