<?php
session_start();
include('../includes/config.php');

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

    $sql = "SELECT product_id FROM product WHERE product_id = ? LIMIT 1";
    $result = mysqli_execute_query($conn, $sql, [$product_id]);

    if (mysqli_num_rows($result) === 0) {
        $_SESSION['message'] = 'product not found';
        header("Location: index.php");
        exit();
    }

    $category_id = (int)$categoryInput;
    $size = (int)$sizeInput;
    $cost = (float)$costInput;
    $sell = (float)$sellInput;
    $qty = (int)$qtyInput;

    // update the product and its stock together
    mysqli_begin_transaction($conn);

    try {
        $sql = "UPDATE product SET category_id=?, name=?, brand=?, scent_type=?, size=?, unit=?, description=?, cost_price=?, sell_price=? WHERE product_id=?";
        $stmt1 = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt1, 'isssissddi', $category_id, $name, $brand, $scent, $size, $unit, $description, $cost, $sell, $product_id);
        mysqli_stmt_execute($stmt1);

        $sql = "UPDATE stock SET quantity=? WHERE product_id=?";
        $stmt2 = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt2, 'ii', $qty, $product_id);
        mysqli_stmt_execute($stmt2);

        mysqli_commit($conn);
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = 'Could not update the product: ' . $e->getMessage();
        header("Location: edit.php?id={$product_id}");
        exit();
    }

    $_SESSION['success'] = 'Product updated successfully';
    header("Location: index.php");
    exit();
}

header("Location: index.php");
exit();
