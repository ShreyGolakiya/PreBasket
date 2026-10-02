<?php
/*
 * admin/dashboard.php  -  Overview for the shop owner
 * The chart is drawn with HTML5 CANVAS using real data from PostgreSQL.
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$pdo = db();

/* ---------- the numbers on the top cards ---------------------------
 * Keep the dashboard to a few database round trips. Supabase is remote,
 * so combining independent COUNT/SUM queries noticeably improves load time.
 */
$kpi = $pdo->query(
    "SELECT
        COUNT(*) AS total_orders,
        COUNT(*) FILTER (WHERE created_at >= CURRENT_DATE) AS today_orders,
        COALESCE(SUM(total_amount) FILTER (
            WHERE created_at >= CURRENT_DATE
              AND order_status NOT IN ('Rejected','Cancelled')
        ), 0) AS today_sales,
        COALESCE(SUM(total_amount) FILTER (
            WHERE order_status NOT IN ('Rejected','Cancelled')
        ), 0) AS total_sales,
        COUNT(*) FILTER (WHERE order_status IN ('Pending','Accepted','Preparing')) AS waiting,
        COUNT(*) FILTER (WHERE order_status = 'Ready for Collection') AS ready_now
     FROM orders"
)->fetch();

$productKpi = $pdo->query(
    "SELECT
        COUNT(*) FILTER (WHERE status = 'active') AS active_products,
        COUNT(*) FILTER (WHERE status = 'active' AND stock_quantity = 0) AS out_of_stock,
        COUNT(*) FILTER (WHERE status = 'active' AND stock_quantity BETWEEN 1 AND 5) AS low_stock
     FROM products"
)->fetch();

$customerKpi = $pdo->query(
    "SELECT COUNT(*) AS customers FROM users"
)->fetchColumn();

$avgRating = $pdo->query('SELECT AVG(rating) FROM feedback')->fetchColumn();
$avgRating = $avgRating !== null ? round((float) $avgRating, 1) : null;

$totalOrders    = (int) $kpi['total_orders'];
$todayOrders    = (int) $kpi['today_orders'];
$todaySales     = (float) $kpi['today_sales'];
$totalSales     = (float) $kpi['total_sales'];
$waiting        = (int) $kpi['waiting'];
$readyNow       = (int) $kpi['ready_now'];
$activeProducts = (int) $productKpi['active_products'];
$outOfStock     = (int) $productKpi['out_of_stock'];
$lowStock       = (int) $productKpi['low_stock'];
$customers      = (int) $customerKpi;

/* ---------- data for the CANVAS chart: the last 7 days -------------- */
$st = $pdo->prepare(
    "SELECT created_at::date AS d,
            COUNT(*) AS orders,
            COALESCE(SUM(CASE WHEN order_status NOT IN ('Rejected','Cancelled') THEN total_amount ELSE 0 END), 0) AS sales
       FROM orders
      WHERE created_at >= CURRENT_DATE - INTERVAL '6 days'
      GROUP BY created_at::date"
);
$st->execute();

$byDate = [];
foreach ($st->fetchAll() as $r) {
    $byDate[$r['d']] = ['orders' => (int) $r['orders'], 'sales' => (float) $r['sales']];
}

$labels = $orderCounts = $salesTotals = [];
for ($i = 6; $i >= 0; $i--) {
    $day       = date('Y-m-d', strtotime("-$i day"));
    $labels[]  = date('D', strtotime($day));         // Mon, Tue, ...
    $orderCounts[] = $byDate[$day]['orders'] ?? 0;
    $salesTotals[] = round($byDate[$day]['sales'] ?? 0, 2);
}
$hasChartData = array_sum($orderCounts) > 0;

/* ---------- lists ---------------------------------------------------- */
$recent = $pdo->query(
    'SELECT o.*, u.name AS customer_name
       FROM orders o JOIN users u ON u.user_id = o.user_id
      ORDER BY o.created_at DESC, o.order_id DESC LIMIT 8'
)->fetchAll();

$lowList = $pdo->query(
    "SELECT product_id, product_name, stock_quantity, image
       FROM products WHERE status = 'active' AND stock_quantity <= 5
      ORDER BY stock_quantity ASC, product_name LIMIT 8"
)->fetchAll();

$topProducts = $pdo->query(
    "SELECT oi.product_name_snapshot AS name, SUM(oi.quantity) AS qty, SUM(oi.subtotal) AS amount
       FROM order_items oi JOIN orders o ON o.order_id = oi.order_id
      WHERE o.order_status NOT IN ('Rejected','Cancelled')
      GROUP BY oi.product_name_snapshot
      ORDER BY qty DESC LIMIT 6"
)->fetchAll();

admin_header('Dashboard', 'dashboard');
?>

<div class="kpi-grid">
    <div class="kpi">
        <span class="kpi-ic"><?= icon('list', 22) ?></span>
        <div><b><?= $todayOrders ?></b><span>Orders today (<?= $totalOrders ?> in total)</span></div>
    </div>
    <div class="kpi">
        <span class="kpi-ic orange"><?= icon('chart', 22) ?></span>
        <div><b><?= money($todaySales) ?></b><span>Sales today (<?= money($totalSales) ?> total)</span></div>
    </div>
    <div class="kpi">
        <span class="kpi-ic blue"><?= icon('st-preparing', 22) ?></span>
        <div><b><?= $waiting ?></b><span>Orders to prepare</span></div>
    </div>
    <div class="kpi">
        <span class="kpi-ic"><?= icon('st-ready', 22) ?></span>
        <div><b><?= $readyNow ?></b><span>Waiting for collection</span></div>
    </div>
    <div class="kpi">
        <span class="kpi-ic red"><?= icon('alert', 22) ?></span>
        <div><b><?= $outOfStock ?></b><span>Out of stock (<?= $lowStock ?> running low)</span></div>
    </div>
    <div class="kpi">
        <span class="kpi-ic"><?= icon('users', 22) ?></span>
        <div><b><?= $customers ?></b><span>Registered customers</span></div>
    </div>
    <div class="kpi">
        <span class="kpi-ic blue"><?= icon('box', 22) ?></span>
        <div><b><?= $activeProducts ?></b><span>Active products</span></div>
    </div>
    <div class="kpi">
        <span class="kpi-ic orange"><?= icon('star', 22) ?></span>
        <div><b><?= $avgRating !== null ? $avgRating . ' / 5' : '&ndash;' ?></b><span>Average feedback rating</span></div>
    </div>
</div>

<!-- ---------------- CANVAS CHART ---------------- -->
<div class="chart-card" style="margin-bottom:16px">
    <div class="panel-head">
        <div>
            <h2 style="margin:0">Last 7 days</h2>
            <span class="small muted">Live data from the orders table, drawn with HTML5 Canvas.</span>
        </div>
        <?php if ($hasChartData): ?>
            <div class="chart-tabs">
                <button type="button" class="active" data-chart="orders">Orders</button>
                <button type="button" data-chart="sales">Sales</button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($hasChartData): ?>
        <canvas id="dashChart" height="300" role="img" aria-label="Chart of orders in the last seven days"></canvas>
    <?php else: ?>
        <div class="chart-empty">
            <?= icon('chart', 44) ?>
            <div>
                <strong>No orders in the last 7 days yet.</strong><br>
                <span class="small">The chart will appear here as soon as customers start ordering.</span>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="admin-grid">
    <!-- ---------------- recent orders ---------------- -->
    <div class="panel">
        <div class="panel-head">
            <h2>Recent orders</h2>
            <a class="link-more" href="<?= url('admin/orders.php') ?>">See all orders</a>
        </div>

        <?php if (!$recent): ?>
            <p class="empty-note">No orders yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Order</th><th>Customer</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $o): ?>
                        <tr>
                            <td>
                                <span class="mono"><?= e($o['order_code']) ?></span><br>
                                <span class="small muted"><?= date('d M, g:i A', strtotime($o['created_at'])) ?></span>
                            </td>
                            <td><?= e($o['customer_name']) ?></td>
                            <td class="num"><strong><?= money($o['total_amount']) ?></strong></td>
                            <td><?= status_badge($o['order_status']) ?></td>
                            <td>
                                <a class="btn btn-ghost btn-sm" href="<?= url('admin/order_details.php?code=' . urlencode($o['order_code'])) ?>">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <!-- ---------------- low stock ---------------- -->
        <div class="panel">
            <div class="panel-head">
                <h2>Stock running low</h2>
                <a class="link-more" href="<?= url('admin/inventory.php') ?>">Inventory</a>
            </div>

            <?php if (!$lowList): ?>
                <p class="small muted">Everything is well stocked. Nice!</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <tbody>
                        <?php foreach ($lowList as $p): $s = (int) $p['stock_quantity']; ?>
                            <tr>
                                <td style="width:50px"><img class="thumb-sm" src="<?= product_image($p['image']) ?>" alt="" width="40" height="40"></td>
                                <td><?= e($p['product_name']) ?></td>
                                <td class="num">
                                    <span class="stock-pill <?= $s === 0 ? 'stock-out' : 'stock-low' ?>"><?= $s ?></span>
                                </td>
                                <td class="num">
                                    <a class="btn btn-ghost btn-sm" href="<?= url('admin/product_edit.php?id=' . (int) $p['product_id']) ?>">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ---------------- best sellers ---------------- -->
        <div class="panel">
            <h2>Best selling items</h2>
            <?php if (!$topProducts): ?>
                <p class="small muted">No sales yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Product</th><th class="num">Sold</th><th class="num">Value</th></tr></thead>
                        <tbody>
                        <?php foreach ($topProducts as $t): ?>
                            <tr>
                                <td><?= e($t['name']) ?></td>
                                <td class="num"><?= (int) $t['qty'] ?></td>
                                <td class="num"><?= money($t['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$inline = '';
if ($hasChartData) {
    $inline = 'var CHART_DATA = ' . json_encode([
            'labels' => $labels,
            'orders' => $orderCounts,
            'sales'  => $salesTotals,
        ]) . ';' . "\n" . <<<'JS'
var canvas = document.getElementById("dashChart");
var mode = "orders";

function render() {
    PBChart.draw(canvas, {
        labels: CHART_DATA.labels,
        values: mode === "orders" ? CHART_DATA.orders : CHART_DATA.sales,
        type:   mode === "orders" ? "bar" : "line",
        color:  "#0f6b4a",
        prefix: mode === "orders" ? "" : "\u20b9",
        title:  mode === "orders" ? "Number of orders per day" : "Sales amount per day"
    });
}
render();

document.querySelectorAll("[data-chart]").forEach(function (b) {
    b.addEventListener("click", function () {
        document.querySelectorAll("[data-chart]").forEach(function (x) { x.classList.remove("active"); });
        b.classList.add("active");
        mode = b.getAttribute("data-chart");
        render();
    });
});

// redraw when the window is resized so the chart always fits
var t;
window.addEventListener("resize", function () { clearTimeout(t); t = setTimeout(render, 150); });
JS;
}
admin_footer($hasChartData ? ['assets/js/chart.js'] : [], $inline);
?>
