<?php
// the Add Product and Edit Product forms share these: the choices, the PHP validation,
// and the form fields (includes/product_fields.php)

function product_scent_types()
{
    return array('Floral', 'Woody', 'Fresh', 'Citrus', 'Fruity', 'Oriental', 'Aquatic', 'Gourmand', 'Spicy', 'Herbal', 'Musky');
}

function product_units()
{
    return array('ml', 'g', 'pcs');
}

// a price like "1250" or "1250.50": digits, and at most 2 decimals
function valid_price($value)
{
    return preg_match('/^\d{1,8}(\.\d{1,2})?$/', $value) === 1;
}

// read and check the submitted product form (everything except the photos).
// returns array($data, $errors, $old): clean values to save, error messages per field,
// and the typed values to show again if there are errors
function validate_product_input($conn)
{
    $old = array(
        'name' => strip_tags(post_value('name')),
        'category_id' => post_value('category_id'),
        'brand' => strip_tags(post_value('brand')),
        'scent_type' => post_value('scent_type'),
        'size' => post_value('size'),
        'unit' => post_value('unit'),
        'description' => strip_tags(post_value('description')),
        'cost_price' => post_value('cost_price'),
        'sell_price' => post_value('sell_price'),
        'quantity' => post_value('quantity'),
    );
    $errors = array();

    if ($old['name'] === '') {
        $errors['name'] = 'Please enter the product name.';
    } else if (mb_strlen($old['name']) > 100) {
        $errors['name'] = 'The product name should be 100 characters or less.';
    }

    $categoryId = filter_var($old['category_id'], FILTER_VALIDATE_INT);
    if ($old['category_id'] === '') {
        $errors['category_id'] = 'Please choose a category.';
    } else if (!$categoryId || !mysqli_fetch_assoc(mysqli_execute_query($conn, "SELECT 1 FROM category WHERE category_id = ?", [$categoryId]))) {
        $errors['category_id'] = 'Please choose a category from the list.';
    }

    if ($old['brand'] === '') {
        $errors['brand'] = 'Please enter the brand.';
    } else if (mb_strlen($old['brand']) > 100) {
        $errors['brand'] = 'The brand should be 100 characters or less.';
    }

    if ($old['scent_type'] === '') {
        $errors['scent_type'] = 'Please choose a scent type.';
    } else if (!in_array($old['scent_type'], product_scent_types(), true)) {
        $errors['scent_type'] = 'Please choose a scent type from the list.';
    }

    $size = filter_var($old['size'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 100000)));
    if ($old['size'] === '') {
        $errors['size'] = 'Please enter the size.';
    } else if ($size === false) {
        $errors['size'] = 'The size should be a whole number from 1 to 100000.';
    } else if (!in_array($old['unit'], product_units(), true)) {
        $errors['size'] = 'Please choose a unit (ml, g or pcs).';
    }

    if (mb_strlen($old['description']) > 255) {
        $errors['description'] = 'The description should be 255 characters or less (now ' . mb_strlen($old['description']) . ').';
    }

    if ($old['cost_price'] === '') {
        $errors['cost_price'] = 'Please enter the cost price.';
    } else if (!valid_price($old['cost_price']) || (float)$old['cost_price'] <= 0) {
        $errors['cost_price'] = 'Enter a price above 0 with at most 2 decimals, e.g. 1250.50.';
    }

    if ($old['sell_price'] === '') {
        $errors['sell_price'] = 'Please enter the selling price.';
    } else if (!valid_price($old['sell_price']) || (float)$old['sell_price'] <= 0) {
        $errors['sell_price'] = 'Enter a price above 0 with at most 2 decimals, e.g. 1499.00.';
    } else if (!isset($errors['cost_price']) && (float)$old['sell_price'] < (float)$old['cost_price']) {
        $errors['sell_price'] = 'The selling price should not be lower than the cost price.';
    }

    $quantity = filter_var($old['quantity'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => 100000)));
    if ($old['quantity'] === '') {
        $errors['quantity'] = 'Please enter the stock quantity (0 if none).';
    } else if ($quantity === false) {
        $errors['quantity'] = 'The stock should be a whole number from 0 to 100000.';
    }

    $data = array(
        'name' => $old['name'],
        'category_id' => (int)$categoryId,
        'brand' => $old['brand'],
        'scent_type' => $old['scent_type'],
        'size' => (int)$size,
        'unit' => $old['unit'],
        'description' => $old['description'],
        'cost_price' => (float)$old['cost_price'],
        'sell_price' => (float)$old['sell_price'],
        'quantity' => (int)$quantity,
    );
    return array($data, $errors, $old);
}

// check the "more photos" upload; returns array($files, $error)
function validate_extra_photos($slotsLeft)
{
    $files = uploaded_files_list(isset($_FILES['photos']) ? $_FILES['photos'] : null);
    if (count($files) > $slotsLeft) {
        return array(array(), $slotsLeft > 0
            ? "You can add {$slotsLeft} more photo" . ($slotsLeft === 1 ? '' : 's') . " (a product can have " . MAX_PRODUCT_PHOTOS . " extra photos)."
            : 'This product already has the most extra photos (' . MAX_PRODUCT_PHOTOS . '). Remove some first.');
    }
    foreach ($files as $file) {
        $error = photo_upload_error($file, true);
        if ($error !== '') {
            return array(array(), $file['name'] . ': ' . $error);
        }
    }
    return array($files, '');
}
