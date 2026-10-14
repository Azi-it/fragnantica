<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// only admins can see the dashboard
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'admin access only, please log in with an admin account';
    header("Location: ../user/login.php");
    exit();
}

// small helper: run a query and return the first row
function one_row($conn, $sql, $params = array())
{
    return mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, $params));
}

// "sales" = every order that was not cancelled; "collected" = cash actually received
$sales = one_row($conn, "SELECT COALESCE(SUM(total_amount), 0) AS amount, COUNT(*) AS orders
                         FROM orders
                         WHERE status <> 'cancelled' AND created_at >= NOW() - INTERVAL 30 DAY");
$previous = one_row($conn, "SELECT COALESCE(SUM(total_amount), 0) AS amount
                            FROM orders
                            WHERE status <> 'cancelled'
                              AND created_at >= NOW() - INTERVAL 60 DAY AND created_at < NOW() - INTERVAL 30 DAY");
$collected = one_row($conn, "SELECT COALESCE(SUM(total_amount), 0) AS amount
                             FROM orders
                             WHERE payment_status = 'paid' AND paid_at >= NOW() - INTERVAL 30 DAY");
// profit uses the cost saved with each order line (older lines fall back to today's cost)
$profit = one_row($conn, "SELECT COALESCE(SUM((oi.price - COALESCE(oi.cost, p.cost_price)) * oi.quantity), 0) AS amount
                          FROM order_items oi
                          INNER JOIN orders o USING (order_id)
                          INNER JOIN product p USING (product_id)
                          WHERE o.status <> 'cancelled' AND o.created_at >= NOW() - INTERVAL 30 DAY");
$inventory = one_row($conn, "SELECT COALESCE(SUM(s.quantity * p.cost_price), 0) AS cost_value,
                                    COALESCE(SUM(s.quantity), 0) AS units
                             FROM stock s INNER JOIN product p USING (product_id)");

$salesAmount = (float)$sales['amount'];
$orderCount = (int)$sales['orders'];
$averageOrder = $orderCount > 0 ? $salesAmount / $orderCount : 0;
$previousAmount = (float)$previous['amount'];
if ($previousAmount > 0) {
    $change = ($salesAmount - $previousAmount) / $previousAmount * 100;
    $changeText = ($change >= 0 ? '&#9650; ' : '&#9660; ') . number_format(abs($change), 0) . '% vs previous 30 days';
    $changeClass = $change >= 0 ? 'text-success' : 'text-danger';
} else {
    $changeText = 'no sales in the previous 30 days';
    $changeClass = 'text-muted';
}

// orders that need someone to act on them
$open = array_fill_keys(array('pending', 'processing', 'shipped'), 0);
$result = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM orders WHERE status IN ('pending', 'processing', 'shipped') GROUP BY status");
while ($row = mysqli_fetch_assoc($result)) {
    $open[$row['status']] = (int)$row['total'];
}

// sales per day for the last 14 days, including days with no sales.
// the dates come from MySQL so they match the order times exactly.
$today = new DateTime(one_row($conn, "SELECT CURDATE() AS d")['d']);
$daily = array();
for ($i = 13; $i >= 0; $i--) {
    $day = (clone $today)->modify("-{$i} day")->format('Y-m-d');
    $daily[$day] = array('amount' => 0.0, 'orders' => 0);
}
$result = mysqli_query($conn, "SELECT DATE(created_at) AS d, SUM(total_amount) AS amount, COUNT(*) AS orders
                               FROM orders
                               WHERE status <> 'cancelled' AND created_at >= CURDATE() - INTERVAL 13 DAY
                               GROUP BY DATE(created_at)");
while ($row = mysqli_fetch_assoc($result)) {
    if (isset($daily[$row['d']])) {
        $daily[$row['d']] = array('amount' => (float)$row['amount'], 'orders' => (int)$row['orders']);
    }
}
$maxDay = max(1, max(array_column($daily, 'amount')));
$fortnightTotal = array_sum(array_column($daily, 'amount'));

$lowStock = mysqli_fetch_all(mysqli_execute_query($conn,
    "SELECT p.product_id, p.name, p.brand, s.quantity
     FROM product p INNER JOIN stock s USING (product_id)
     WHERE s.quantity <= ?
     ORDER BY s.quantity, p.name", [(int)LOW_STOCK_THRESHOLD]), MYSQLI_ASSOC);

$topSellers = mysqli_fetch_all(mysqli_query($conn,
    "SELECT p.product_id, p.name, p.brand, SUM(oi.quantity) AS units, SUM(oi.price * oi.quantity) AS revenue
     FROM order_items oi
     INNER JOIN orders o USING (order_id)
     INNER JOIN product p USING (product_id)
     WHERE o.status <> 'cancelled' AND o.created_at >= NOW() - INTERVAL 30 DAY
     GROUP BY p.product_id, p.name, p.brand
     ORDER BY units DESC, revenue DESC
     LIMIT 5"), MYSQLI_ASSOC);

$recent = mysqli_fetch_all(mysqli_query($conn,
    "SELECT o.order_id, o.total_amount, o.status, o.created_at, u.name AS customer
     FROM orders o INNER JOIN users u USING (user_id)
     ORDER BY o.created_at DESC, o.order_id DESC
     LIMIT 5"), MYSQLI_ASSOC);

include('../includes/header.php');
?>
<div class="container mt-4 dashboard">
    <?php include('../includes/alert.php'); ?>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <h2 class="mb-0">Dashboard</h2>
        <span class="text-muted small">Figures cover the last 30 days unless stated otherwise.</span>
    </div>

    <!-- key numbers -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg">
            <div class="card kpi h-100"><div class="card-body">
                <div class="kpi-label">Sales</div>
                <div class="kpi-value"><?= peso($salesAmount) ?></div>
                <div class="small <?= $changeClass ?>"><?= $changeText ?></div>
            </div></div>
        </div>
        <div class="col-6 col-lg">
            <div class="card kpi h-100"><div class="card-body">
                <div class="kpi-label">Cash collected</div>
                <div class="kpi-value"><?= peso($collected['amount']) ?></div>
                <div class="small text-muted">orders marked paid</div>
            </div></div>
        </div>
        <div class="col-6 col-lg">
            <div class="card kpi h-100"><div class="card-body">
                <div class="kpi-label">Gross profit</div>
                <div class="kpi-value"><?= peso($profit['amount']) ?></div>
                <div class="small text-muted">selling price minus cost price</div>
            </div></div>
        </div>
        <div class="col-6 col-lg">
            <div class="card kpi h-100"><div class="card-body">
                <div class="kpi-label">Orders</div>
                <div class="kpi-value"><?= $orderCount ?></div>
                <div class="small text-muted">average <?= peso($averageOrder) ?></div>
            </div></div>
        </div>
        <div class="col-12 col-lg">
            <div class="card kpi h-100"><div class="card-body">
                <div class="kpi-label">Stock value (at cost)</div>
                <div class="kpi-value"><?= peso($inventory['cost_value']) ?></div>
                <div class="small text-muted"><?= (int)$inventory['units'] ?> units on hand, today</div>
            </div></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between">
                    <strong>Sales, last 14 days</strong>
                    <span class="small text-muted"><?= peso($fortnightTotal) ?> total</span>
                </div>
                <div class="card-body">
                    <div class="sales-chart" role="img" aria-label="Bar chart of sales per day for the last 14 days">
                        <?php foreach ($daily as $day => $d):
                            $height = $d['amount'] > 0 ? max(3, round($d['amount'] / $maxDay * 100)) : 0;
                            $label = date('M j', strtotime($day));
                            $tip = $label . ': ' . html_entity_decode(peso($d['amount']), ENT_QUOTES, 'UTF-8') . ', ' . $d['orders'] . ' order' . ($d['orders'] === 1 ? '' : 's');
                        ?>
                            <div class="bar-col" title="<?= e($tip) ?>">
                                <div class="bar-track"><div class="bar" style="height: <?= $height ?>%;"></div></div>
                                <div class="bar-label"><?= e(date('j', strtotime($day))) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="small text-muted mb-0 mt-2">
                        <?= e(date('M j', strtotime(array_key_first($daily)))) ?> to <?= e(date('M j', strtotime(array_key_last($daily)))) ?>.
                        Hover over a bar for the amount.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><strong>Top sellers</strong></div>
                <div class="card-body p-0">
                    <?php if (empty($topSellers)): ?>
                        <p class="text-muted m-3">No sales in the last 30 days yet.</p>
                    <?php else: ?>
                        <table class="table align-middle mb-0">
                            <thead class="table-light"><tr><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
                            <tbody>
                            <?php foreach ($topSellers as $t): ?>
                                <tr>
                                    <td><a href="../product/view.php?id=<?= (int)$t['product_id'] ?>"><?= e($t['name']) ?></a> <small class="text-muted"><?= e($t['brand']) ?></small></td>
                                    <td class="text-end"><?= (int)$t['units'] ?></td>
                                    <td class="text-end"><?= peso($t['revenue']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <strong>Recent orders</strong>
                    <a href="../order/index.php" class="small">All orders</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent)): ?>
                        <p class="text-muted m-3">No orders yet.</p>
                    <?php else: ?>
                        <table class="table align-middle mb-0">
                            <tbody>
                            <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td><a href="../order/view.php?id=<?= (int)$r['order_id'] ?>">#<?= (int)$r['order_id'] ?></a></td>
                                    <td><?= e($r['customer']) ?></td>
                                    <td class="small text-muted"><?= date('M j, g:i a', strtotime($r['created_at'])) ?></td>
                                    <td class="text-end"><?= peso($r['total_amount']) ?></td>
                                    <td class="text-end"><?= status_badge($r['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><strong>Needs attention</strong></div>
                <ul class="list-group list-group-flush">
                    <?php
                    $todo = array(
                        'pending' => 'waiting to be processed',
                        'processing' => 'being prepared, not shipped yet',
                        'shipped' => 'on the way, not completed yet',
                    );
                    foreach ($todo as $key => $text): ?>
                        <a href="../order/index.php?status=<?= $key ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><?= status_badge($key) ?> <span class="small text-muted"><?= e($text) ?></span></span>
                            <strong><?= $open[$key] ?></strong>
                        </a>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <strong>Low stock</strong>
                    <span class="small text-muted"><?= (int)LOW_STOCK_THRESHOLD ?> or fewer left</span>
                </div>
                <?php if (empty($lowStock)): ?>
                    <div class="card-body"><p class="text-muted mb-0">Everything is well stocked.</p></div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($lowStock as $item):
                            $qty = (int)$item['quantity'];
                            $badge = $qty === 0 ? 'bg-danger' : 'bg-warning text-dark';
                        ?>
                            <a href="../product/edit.php?id=<?= (int)$item['product_id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><?= e($item['name']) ?><br><small class="text-muted"><?= e($item['brand']) ?></small></span>
                                <span class="badge <?= $badge ?>"><?= $qty === 0 ? 'Out of stock' : $qty . ' left' ?></span>
                            </a>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include('../includes/footer.php'); ?>
