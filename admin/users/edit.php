<?php
session_start();
include('../../includes/config.php');
include('../../includes/functions.php');

// User CRUD (update): admins only
require_admin();

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    header("Location: index.php");
    exit();
}

$sql = "SELECT u.user_id, u.name, u.email, u.img_path, u.role, u.status, u.created_at,
               (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.user_id) AS orders,
               (SELECT COUNT(*) FROM review r WHERE r.user_id = u.user_id) AS reviews
        FROM users u WHERE u.user_id = ? LIMIT 1";
$user = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$user_id]));
if (!$user) {
    $_SESSION['message'] = 'User not found.';
    header("Location: index.php");
    exit();
}

list($errors, $old) = take_form('user_edit_' . $user_id);
$name = $old['name'] ?? $user['name'];
$email = $old['email'] ?? $user['email'];
$role = $old['role'] ?? $user['role'];

$isMe = (int)$user['user_id'] === (int)$_SESSION['user_id'];
$active = $user['status'] === 'active';
// only an active user's role can be changed, and nobody can change their own role
$canChangeRole = $active && !$isMe;

include('../../includes/header.php');
?>
<div class="container mt-4" style="max-width: 900px;">
    <a href="index.php" class="btn btn-link px-0">&larr; All users</a>
    <?php include('../../includes/alert.php'); ?>

    <div class="d-flex align-items-center gap-3 mb-3">
        <?= avatar($user['name'], $user['img_path'], 64) ?>
        <div>
            <h2 class="mb-0"><?= e($user['name']) ?></h2>
            <span class="badge <?= $active ? 'bg-success' : 'bg-secondary' ?>"><?= $active ? 'Active' : 'Deactivated' ?></span>
            <span class="badge <?= $user['role'] === 'admin' ? 'bg-dark' : 'bg-light text-dark border' ?>"><?= e(ucfirst($user['role'])) ?></span>
            <span class="small text-muted ms-1">joined <?= date('M j, Y', strtotime($user['created_at'])) ?> &middot;
                <?= (int)$user['orders'] ?> order<?= (int)$user['orders'] === 1 ? '' : 's' ?> &middot;
                <?= (int)$user['reviews'] ?> review<?= (int)$user['reviews'] === 1 ? '' : 's' ?></span>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><strong>Edit details</strong></div>
                <div class="card-body">
                    <form action="update.php" method="POST" enctype="multipart/form-data" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control<?= invalid_class($errors, 'name') ?>" id="name" name="name" value="<?= e($name) ?>">
                            <?= field_error($errors, 'name') ?>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="text" class="form-control<?= invalid_class($errors, 'email') ?>" id="email" name="email" value="<?= e($email) ?>">
                            <?= field_error($errors, 'email') ?>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select<?= invalid_class($errors, 'role') ?>" id="role" name="role" <?= $canChangeRole ? '' : 'disabled' ?>>
                                <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>
                                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <?= field_error($errors, 'role') ?>
                            <?php if ($isMe): ?>
                                <div class="form-text">You cannot change your own role, so the store always keeps an admin.</div>
                            <?php elseif (!$active): ?>
                                <div class="form-text">Only an active user's role can be changed. Activate this user first.</div>
                            <?php else: ?>
                                <div class="form-text">Admins can manage products, orders and users. Customers can shop and write reviews.</div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="photo" class="form-label">New photo (optional, JPG or PNG)</label>
                            <input type="file" class="form-control<?= invalid_class($errors, 'photo') ?>" id="photo" name="photo" accept=".jpg,.jpeg,.png">
                            <?= field_error($errors, 'photo') ?>
                            <?php if ($user['img_path']): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo" value="1">
                                    <label class="form-check-label" for="remove_photo">Remove the current photo</label>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary" name="submit">Save changes</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><strong>Account status</strong></div>
                <div class="card-body">
                    <?php if ($isMe): ?>
                        <p class="text-muted mb-0">This is your own account. You cannot deactivate yourself.</p>
                    <?php else: ?>
                        <p class="small text-muted">
                            <?= $active
                                ? 'A deactivated user is logged out at once and cannot log in. Their orders and reviews are kept.'
                                : 'This user cannot log in. Activate the account to let them log in again.' ?>
                        </p>
                        <form action="status.php" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                            <input type="hidden" name="return" value="edit">
                            <?php if ($active): ?>
                                <button type="submit" name="status" value="inactive" class="btn btn-outline-warning w-100"
                                    onclick="return confirm('Deactivate this user?');"><i class="fa-solid fa-user-slash me-1"></i> Deactivate user</button>
                            <?php else: ?>
                                <button type="submit" name="status" value="active" class="btn btn-success w-100"><i class="fa-solid fa-user-check me-1"></i> Activate user</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$isMe && (int)$user['orders'] === 0): ?>
                <div class="card border-danger">
                    <div class="card-header text-danger"><strong>Delete user</strong></div>
                    <div class="card-body">
                        <p class="small text-muted">This user has no orders, so the account can be deleted for good, together with their reviews and cart.</p>
                        <form action="delete.php" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                            <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Permanently delete this user? This cannot be undone.');">
                                <i class="fa-solid fa-trash me-1"></i> Delete user</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include('../../includes/footer.php'); ?>
