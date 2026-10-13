<?php
// the fields of the Add Product and Edit Product forms.
// expects: $values (field => value), $errors (field => message), $categories (rows),
// $product (the saved product when editing, otherwise null) and $extraPhotos (rows, when editing)
if (!function_exists('product_scent_types')) {
    // opened directly in the browser instead of through create.php or edit.php
    http_response_code(404);
    exit();
}
$scentTypes = product_scent_types();
$units = product_units();
$isEdit = $product !== null;
?>
<div class="row gx-3 mb-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Product Name</label>
        <input type="text" class="form-control<?= invalid_class($errors, 'name') ?>" id="name" placeholder="e.g. Lavender Dreams" name="name"
            value="<?= e($values['name']) ?>" />
        <?= field_error($errors, 'name') ?>
    </div>
    <div class="col-md-6">
        <label for="category" class="form-label">Category</label>
        <select class="form-select<?= invalid_class($errors, 'category_id') ?>" id="category" name="category_id">
            <option value="">-- choose category --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['category_id'] ?>" <?= (string)$values['category_id'] === (string)$cat['category_id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'category_id') ?>
    </div>
</div>

<div class="row gx-3 mb-3">
    <div class="col-md-6">
        <label for="brand" class="form-label">Brand</label>
        <input type="text" class="form-control<?= invalid_class($errors, 'brand') ?>" id="brand" placeholder="e.g. Dior" name="brand"
            value="<?= e($values['brand']) ?>" />
        <?= field_error($errors, 'brand') ?>
    </div>
    <div class="col-md-6">
        <label for="scent" class="form-label">Scent Type</label>
        <select class="form-select<?= invalid_class($errors, 'scent_type') ?>" id="scent" name="scent_type">
            <option value="">-- choose scent type --</option>
            <?php foreach ($scentTypes as $scent): ?>
                <option value="<?= e($scent) ?>" <?= $values['scent_type'] === $scent ? 'selected' : '' ?>><?= e($scent) ?></option>
            <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'scent_type') ?>
    </div>
</div>

<div class="row gx-3 mb-3">
    <div class="col-md-3">
        <label for="size" class="form-label">Size</label>
        <input type="text" inputmode="numeric" class="form-control<?= invalid_class($errors, 'size') ?>" id="size" placeholder="e.g. 100" name="size"
            value="<?= e($values['size']) ?>" />
    </div>
    <div class="col-md-3">
        <label for="unit" class="form-label">Unit</label>
        <select class="form-select" id="unit" name="unit">
            <?php foreach ($units as $unit): ?>
                <option value="<?= e($unit) ?>" <?= $values['unit'] === $unit ? 'selected' : '' ?>><?= e($unit) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label d-none d-md-block">&nbsp;</label>
        <p class="small text-muted mb-0">ml for liquids, g for candles and wax melts, pcs for items sold per piece</p>
    </div>
    <div class="col-12"><?= field_error($errors, 'size') ?></div>
</div>

<div class="row gx-3 mb-3">
    <div class="col-md-4">
        <label for="cost" class="form-label">Cost Price (&#8369;)</label>
        <input type="text" inputmode="decimal" class="form-control<?= invalid_class($errors, 'cost_price') ?>" id="cost" placeholder="e.g. 850.00" name="cost_price"
            value="<?= e($values['cost_price']) ?>" />
        <?= field_error($errors, 'cost_price') ?>
    </div>
    <div class="col-md-4">
        <label for="sell" class="form-label">Selling Price (&#8369;)</label>
        <input type="text" inputmode="decimal" class="form-control<?= invalid_class($errors, 'sell_price') ?>" id="sell" placeholder="e.g. 1250.00" name="sell_price"
            value="<?= e($values['sell_price']) ?>" />
        <?= field_error($errors, 'sell_price') ?>
    </div>
    <div class="col-md-4">
        <label for="qty" class="form-label"><?= $isEdit ? 'Stock' : 'Initial Stock' ?></label>
        <input type="text" inputmode="numeric" class="form-control<?= invalid_class($errors, 'quantity') ?>" id="qty" placeholder="0" name="quantity"
            value="<?= e($values['quantity']) ?>" />
        <?= field_error($errors, 'quantity') ?>
    </div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Description (optional, up to 255 characters)</label>
    <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description" rows="2"
        placeholder="e.g. burn time 40 hours, lasts up to 30 days"><?= e($values['description']) ?></textarea>
    <?= field_error($errors, 'description') ?>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>Photos</strong> <span class="small text-muted">JPG or PNG, up to <?= MAX_PHOTO_BYTES / 1024 / 1024 ?> MB each</span></div>
    <div class="card-body">
        <div class="mb-3">
            <?php if ($isEdit): ?>
                <label class="form-label">Main photo</label><br />
                <img src="<?= e($product['img_path']) ?>" width="120" height="120" class="product-photo border mb-2" alt="Current main photo" />
                <br />
                <label for="photo" class="form-label small">New main photo (leave empty to keep the current one)</label>
            <?php else: ?>
                <label for="photo" class="form-label">Main photo</label>
            <?php endif; ?>
            <input class="form-control<?= invalid_class($errors, 'photo') ?>" type="file" id="photo" name="img_path" accept=".jpg,.jpeg,.png" />
            <?= field_error($errors, 'photo') ?>
        </div>

        <?php if ($isEdit && $extraPhotos): ?>
            <label class="form-label">More photos <span class="small text-muted">(tick a photo to remove it)</span></label>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($extraPhotos as $photo): ?>
                    <label class="photo-tile">
                        <img src="<?= e($photo['img_path']) ?>" alt="Extra photo">
                        <span class="d-block small mt-1">
                            <input type="checkbox" class="form-check-input" name="delete_photos[]" value="<?= (int)$photo['image_id'] ?>"> Remove
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label for="photos" class="form-label"><?= $isEdit ? 'Add more photos' : 'More photos (optional)' ?></label>
        <input class="form-control<?= invalid_class($errors, 'photos') ?>" type="file" id="photos" name="photos[]" accept=".jpg,.jpeg,.png" multiple />
        <div class="form-text">Hold Ctrl (or Cmd) to choose several photos. Up to <?= MAX_PRODUCT_PHOTOS ?> extra photos per product.</div>
        <?= field_error($errors, 'photos') ?>
    </div>
</div>
