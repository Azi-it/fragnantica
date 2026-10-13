<?php
// the review form, used on the product page both to write a review and to update it.
// expects: $product_id, $myReview (null when writing a new one), $formRating, $formComment,
// $reviewErrors (field => message) and $ratingWords
if (!isset($product_id, $ratingWords)) {
    // opened directly in the browser instead of through product/view.php
    http_response_code(404);
    exit();
}
?>
<form action="../review/save.php" method="POST" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="product_id" value="<?= (int)$product_id ?>">

    <fieldset class="mb-2">
        <legend class="form-label fs-6 mb-1">Your rating</legend>
        <!-- the stars are radio buttons; they are listed 5 to 1 so CSS can colour the hovered star and the ones before it -->
        <div class="star-input">
            <?php for ($star = 5; $star >= 1; $star--): ?>
                <input type="radio" id="rating-<?= $star ?>" name="rating" value="<?= $star ?>" <?= $formRating === $star ? 'checked' : '' ?>>
                <label for="rating-<?= $star ?>" title="<?= e($ratingWords[$star]) ?>"><i class="fa-solid fa-star"></i><span class="visually-hidden"><?= $star ?> star<?= $star === 1 ? '' : 's' ?> (<?= e($ratingWords[$star]) ?>)</span></label>
            <?php endfor; ?>
        </div>
        <?= field_error($reviewErrors, 'rating') ?>
    </fieldset>

    <div class="mb-2">
        <label for="comment" class="form-label">Your review</label>
        <textarea class="form-control<?= invalid_class($reviewErrors, 'comment') ?>" id="comment" name="comment" rows="4"
            placeholder="How does it smell? How long does it last?"><?= e($formComment) ?></textarea>
        <?= field_error($reviewErrors, 'comment') ?>
        <div class="form-text">10 to 1000 characters. Rude words are hidden automatically.</div>
    </div>

    <button type="submit" name="submit" class="btn btn-primary"><?= $myReview ? 'Update review' : 'Post review' ?></button>
</form>
