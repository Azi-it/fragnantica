<?php
session_start();
include('../../includes/config.php');
include('../../includes/functions.php');

// User CRUD: admins only
require_admin();

list($errors, $old) = take_form('user_create');
$role = $old['role'] ?? 'customer';

include('../../includes/header.php');
?>
<div class="container mt-4" style="max-width: 720px;">
    <a href="index.php" class="btn btn-link px-0">&larr; All users</a>
    <h2>Add User</h2>
    <?php include('../../includes/alert.php'); ?>
    <form action="store.php" method="POST" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="row gx-3 mb-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control<?= invalid_class($errors, 'name') ?>" id="name" name="name" value="<?= e($old['name'] ?? '') ?>">
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="text" class="form-control<?= invalid_class($errors, 'email') ?>" id="email" name="email" value="<?= e($old['email'] ?? '') ?>">
                <?= field_error($errors, 'email') ?>
            </div>
        </div>
        <div class="row gx-3 mb-3">
            <div class="col-md-6">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control<?= invalid_class($errors, 'password') ?>" id="password" name="password" autocomplete="new-password">
                <?= field_error($errors, 'password') ?>
                <small class="text-muted">at least 6 characters</small>
            </div>
            <div class="col-md-6">
                <label for="confirmPass" class="form-label">Confirm Password</label>
                <input type="password" class="form-control<?= invalid_class($errors, 'confirmPass') ?>" id="confirmPass" name="confirmPass" autocomplete="new-password">
                <?= field_error($errors, 'confirmPass') ?>
            </div>
        </div>
        <div class="row gx-3 mb-3">
            <div class="col-md-6">
                <label for="role" class="form-label">Role</label>
                <select class="form-select<?= invalid_class($errors, 'role') ?>" id="role" name="role">
                    <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
                <?= field_error($errors, 'role') ?>
            </div>
            <div class="col-md-6">
                <label for="photo" class="form-label">Photo (optional, JPG or PNG)</label>
                <input type="file" class="form-control<?= invalid_class($errors, 'photo') ?>" id="photo" name="photo" accept=".jpg,.jpeg,.png">
                <?= field_error($errors, 'photo') ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary" name="submit">Create user</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php include('../../includes/footer.php'); ?>
