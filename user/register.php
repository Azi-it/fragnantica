<?php
session_start();
include("../includes/config.php");
include("../includes/functions.php");

// what was typed before a failed submit, and what was wrong with it
list($errors, $old) = take_form('register');

include("../includes/header.php");
?>
<div class="container-fluid container-lg">
    <div class="row col-md-6 mx-auto mt-4">
        <h2>Create an Account</h2>
        <?php include("../includes/alert.php"); ?>
        <form action="store.php" method="POST" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control<?= invalid_class($errors, 'name') ?>" id="name" name="name"
                    value="<?= e($old['name'] ?? '') ?>">
                <?= field_error($errors, 'name') ?>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="text" class="form-control<?= invalid_class($errors, 'email') ?>" id="email" name="email"
                    value="<?= e($old['email'] ?? '') ?>">
                <?= field_error($errors, 'email') ?>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control<?= invalid_class($errors, 'password') ?>" id="password" name="password">
                <?= field_error($errors, 'password') ?>
                <small class="text-muted">at least 6 characters</small>
            </div>

            <div class="mb-3">
                <label for="password2" class="form-label">Confirm Password</label>
                <input type="password" class="form-control<?= invalid_class($errors, 'confirmPass') ?>" id="password2" name="confirmPass">
                <?= field_error($errors, 'confirmPass') ?>
            </div>

            <div class="mb-3">
                <label for="photo" class="form-label">Profile photo (optional, JPG or PNG, up to <?= MAX_PHOTO_BYTES / 1024 / 1024 ?> MB)</label>
                <input type="file" class="form-control<?= invalid_class($errors, 'photo') ?>" id="photo" name="photo" accept=".jpg,.jpeg,.png">
                <?= field_error($errors, 'photo') ?>
            </div>

            <button type="submit" class="btn btn-primary" name="submit">Register</button>

            <p class="mt-3">Already have an account? <a href="login.php">Login</a></p>
        </form>
    </div>
</div>

<?php
include("../includes/footer.php");
?>
