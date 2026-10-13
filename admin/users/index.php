<?php
session_start();
include('../../includes/config.php');
include('../../includes/functions.php');

// User CRUD: admins only
require_admin();

$perPage = 20;
$roles = array('customer' => 'Customer', 'admin' => 'Admin');

// filters: ?q=juan&role=customer&status=inactive&page=2
$keyword = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$role = isset($_GET['role']) && is_string($_GET['role']) && isset($roles[$_GET['role']]) ? $_GET['role'] : '';
$status = isset($_GET['status']) && in_array($_GET['status'], array('active', 'inactive'), true) ? $_GET['status'] : '';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
if (!$page) {
    $page = 1;
}

// how many accounts are active and inactive (for the tabs)
$counts = array('active' => 0, 'inactive' => 0);
$result = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM users GROUP BY status");
while ($row = mysqli_fetch_assoc($result)) {
    $counts[$row['status']] = (int)$row['total'];
}

$where = " WHERE 1=1";
$params = array();
if ($keyword !== '') {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
}
if ($role !== '') {
    $where .= " AND u.role = ?";
    $params[] = $role;
}
if ($status !== '') {
    $where .= " AND u.status = ?";
    $params[] = $status;
}

$total = (int)mysqli_fetch_assoc(mysqli_execute_query($conn, "SELECT COUNT(*) AS total FROM users u" . $where, $params))['total'];
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = "SELECT u.user_id, u.name, u.email, u.img_path, u.role, u.status, u.created_at,
               (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.user_id) AS orders
        FROM users u" . $where . "
        ORDER BY u.created_at DESC, u.user_id DESC
        LIMIT {$perPage} OFFSET {$offset}";
$users = mysqli_fetch_all(mysqli_execute_query($conn, $sql, $params), MYSQLI_ASSOC);

$me = (int)$_SESSION['user_id'];
$keep = array('q', 'role', 'status');

include('../../includes/header.php');
?>
<div class="container mt-4">
    <?php include('../../includes/alert.php'); ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="mb-0">Users</h2>
        <a href="create.php" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i> Add User</a>
    </div>

    <ul class="nav nav-pills mt-3 flex-wrap">
        <li class="nav-item">
            <a class="nav-link <?= $status === '' ? 'active' : '' ?>" href="<?= e(url_with(array('status' => null, 'page' => null), $keep)) ?>">
                All <span class="badge bg-light text-dark"><?= $counts['active'] + $counts['inactive'] ?></span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status === 'active' ? 'active' : '' ?>" href="<?= e(url_with(array('status' => 'active', 'page' => null), $keep)) ?>">
                Active <span class="badge bg-light text-dark"><?= $counts['active'] ?></span></a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status === 'inactive' ? 'active' : '' ?>" href="<?= e(url_with(array('status' => 'inactive', 'page' => null), $keep)) ?>">
                Deactivated <span class="badge bg-light text-dark"><?= $counts['inactive'] ?></span></a>
        </li>
    </ul>

    <form class="row g-2 mt-2" method="GET" action="index.php" role="search">
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <div class="col-sm-6 col-md-5">
            <input class="form-control" type="search" name="q" value="<?= e($keyword) ?>" placeholder="Name or email" aria-label="Search users">
        </div>
        <div class="col-6 col-md-3">
            <select class="form-select" name="role" aria-label="Role">
                <option value="">All roles</option>
                <?php foreach ($roles as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $role === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2 d-flex gap-1">
            <button class="btn btn-outline-secondary flex-fill" type="submit">Search</button>
            <?php if ($keyword !== '' || $role !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= e(url_with(array(), array('status'))) ?>">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($users)): ?>
        <p class="mt-4">No users found.</p>
    <?php else: ?>
        <p class="text-muted small mt-3 mb-2">Showing <?= $offset + 1 ?>&ndash;<?= $offset + count($users) ?> of <?= $total ?> user<?= $total === 1 ? '' : 's' ?></p>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr><th>User</th><th>Role</th><th>Status</th><th>Joined</th><th>Orders</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $id = (int)$u['user_id'];
                    $isMe = $id === $me;
                    $active = $u['status'] === 'active';
                ?>
                    <tr class="<?= $active ? '' : 'table-secondary' ?>">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?= avatar($u['name'], $u['img_path'], 40) ?>
                                <div>
                                    <strong><?= e($u['name']) ?></strong><?= $isMe ? " <span class='badge bg-light text-dark border'>you</span>" : '' ?><br>
                                    <small class="text-muted"><?= e($u['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge <?= $u['role'] === 'admin' ? 'bg-dark' : 'bg-light text-dark border' ?>"><?= e($roles[$u['role']] ?? $u['role']) ?></span></td>
                        <td><?= $active ? "<span class='badge bg-success'>Active</span>" : "<span class='badge bg-secondary'>Deactivated</span>" ?></td>
                        <td class="small"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td><?= (int)$u['orders'] ?></td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                <a href="edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fa-regular fa-pen-to-square"></i> Edit</a>
                                <?php if (!$isMe): ?>
                                    <form action="status.php" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $id ?>">
                                        <?php if ($active): ?>
                                            <button type="submit" name="status" value="inactive" class="btn btn-sm btn-outline-warning"
                                                onclick="return confirm('Deactivate <?= e(addslashes($u['name'])) ?>? They will be logged out and cannot log in until reactivated.');">
                                                <i class="fa-solid fa-user-slash"></i> Deactivate</button>
                                        <?php else: ?>
                                            <button type="submit" name="status" value="active" class="btn btn-sm btn-outline-success">
                                                <i class="fa-solid fa-user-check"></i> Activate</button>
                                        <?php endif; ?>
                                    </form>
                                    <?php if ((int)$u['orders'] === 0): ?>
                                        <form action="delete.php" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $id ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                                onclick="return confirm('Permanently delete <?= e(addslashes($u['name'])) ?>? This cannot be undone.');">
                                                <i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted">Users with orders cannot be deleted, so their order history is kept. Deactivate them instead.</p>
        <?= pagination($page, $totalPages, $keep) ?>
    <?php endif; ?>
</div>
<?php include('../../includes/footer.php'); ?>
