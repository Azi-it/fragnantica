<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// only admins can manage orders
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'admin access only, please log in with an admin account';
    header("Location: ../user/login.php");
    exit();
}

$statuses = order_statuses();
$perPage = 20;

// filters: ?status=pending&q=juan&page=2
$status = isset($_GET['status']) && isset($statuses[$_GET['status']]) ? $_GET['status'] : '';
$keyword = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
if (!$page) {
    $page = 1;
}

// how many orders are in each status (for the tabs)
$counts = array_fill_keys(array_keys($statuses), 0);
$countResult = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM orders GROUP BY status");
while ($row = mysqli_fetch_assoc($countResult)) {
    $counts[$row['status']] = (int)$row['total'];
}
$allCount = array_sum($counts);

// build the WHERE part once and reuse it for the count and the page of results
$where = " WHERE 1=1";
$params = array();
if ($status !== '') {
    $where .= " AND o.status = ?";
    $params[] = $status;
}
if ($keyword !== '') {
    // "#12" or "12" finds order 12; anything else searches customer name and email
    $number = ltrim($keyword, '#');
    if (ctype_digit($number)) {
        $where .= " AND o.order_id = ?";
        $params[] = (int)$number;
    } else {
        $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $params[] = "%{$keyword}%";
        $params[] = "%{$keyword}%";
    }
}

$countSql = "SELECT COUNT(*) AS total FROM orders o INNER JOIN users u USING (user_id)" . $where;
$total = (int)mysqli_fetch_assoc(mysqli_execute_query($conn, $countSql, $params))['total'];
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = "SELECT o.order_id, o.total_amount, o.status, o.payment_method, o.payment_status, o.created_at,
               u.name AS customer_name, u.email AS customer_email,
               (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.order_id) AS units
        FROM orders o
        INNER JOIN users u USING (user_id)" . $where . "
        ORDER BY o.created_at DESC, o.order_id DESC
        LIMIT {$perPage} OFFSET {$offset}";
$orders = mysqli_fetch_all(mysqli_execute_query($conn, $sql, $params), MYSQLI_ASSOC);

include('../includes/header.php');
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="mb-0">Orders</h2>
        <form class="d-flex gap-2" method="GET" action="index.php" role="search">
            <?php if ($status !== ''): ?>
                <input type="hidden" name="status" value="<?= e($status) ?>">
            <?php endif; ?>
            <input class="form-control" type="search" name="q" value="<?= e($keyword) ?>" placeholder="Order # or customer" aria-label="Search orders">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </form>
    </div>

    <ul class="nav nav-pills mt-3 flex-wrap">
        <li class="nav-item">
            <a class="nav-link <?= $status === '' ? 'active' : '' ?>" href="<?= e(url_with(array('status' => null, 'page' => null), array('q'))) ?>">
                All <span class="badge bg-light text-dark"><?= $allCount ?></span>
            </a>
        </li>
        <?php foreach ($statuses as $key => $info): ?>
            <li class="nav-item">
                <a class="nav-link <?= $status === $key ? 'active' : '' ?>" href="<?= e(url_with(array('status' => $key, 'page' => null), array('q'))) ?>">
                    <?= e($info['label']) ?> <span class="badge bg-light text-dark"><?= $counts[$key] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($orders)): ?>
        <p class="mt-4">No orders found.<?php if ($status !== '' || $keyword !== ''): ?> <a href="index.php">Show all orders</a><?php endif; ?></p>
    <?php else: ?>
        <p class="text-muted small mt-3 mb-2">
            Showing <?= $offset + 1 ?>&ndash;<?= $offset + count($orders) ?> of <?= $total ?> order<?= $total === 1 ? '' : 's' ?>
        </p>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Units</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong>#<?= (int)$order['order_id'] ?></strong></td>
                        <td><?= date('M j, Y, g:i a', strtotime($order['created_at'])) ?></td>
                        <td><?= e($order['customer_name']) ?><br><small class="text-muted"><?= e($order['customer_email']) ?></small></td>
                        <td><?= (int)$order['units'] ?></td>
                        <td><?= peso($order['total_amount']) ?></td>
                        <td><small><?= e(payment_label($order['payment_method'])) ?></small><br><?= payment_badge($order['payment_status']) ?></td>
                        <td><?= status_badge($order['status']) ?></td>
                        <td><a href="view.php?id=<?= (int)$order['order_id'] ?>" class="btn btn-sm btn-outline-primary">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination($page, $totalPages, array('status', 'q')) ?>
    <?php endif; ?>
</div>
<?php include('../includes/footer.php'); ?>
