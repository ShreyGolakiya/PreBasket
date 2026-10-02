<?php
/*
 * admin/orders.php  -  All customer orders
 * Staff can move an order to its next status straight from this list.
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$pdo = db();

/* ---------- a status change was submitted ---------- */
if (is_post()) {
    require_csrf('admin/orders.php');

    $orderId   = post_int('order_id');
    $newStatus = post_str('new_status', 30);

    try {
        $msg = change_order_status($pdo, $orderId, $newStatus, 'admin');
        flash('success', $msg);
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    } catch (Throwable $ex) {
        log_exception($ex);
        flash('error', 'Something went wrong. Please try again.');
    }
    redirect('admin/orders.php' . (get_str('status') !== '' ? '?status=' . urlencode(get_str('status')) : ''));
}

$filter  = get_str('status', 30);
$q       = get_str('q', 30);
$allowed = array_merge(ORDER_FLOW, ['Rejected', 'Cancelled']);

$where  = ['1 = 1'];
$params = [];
if ($filter !== '' && in_array($filter, $allowed, true)) {
    $where[]  = 'o.order_status = ?';
    $params[] = $filter;
} else {
    $filter = '';
}
if ($q !== '') {
    $where[]  = '(o.order_code LIKE ? OR u.name LIKE ? OR u.phone LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$whereSql = implode(' AND ', $where);

$st = $pdo->prepare(
    "SELECT o.*, u.name AS customer_name, u.phone AS customer_phone,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count
       FROM orders o JOIN users u ON u.user_id = o.user_id
      WHERE $whereSql
      ORDER BY CASE o.order_status
                   WHEN 'Pending' THEN 1
                   WHEN 'Accepted' THEN 2
                   WHEN 'Preparing' THEN 3
                   WHEN 'Ready for Collection' THEN 4
                   WHEN 'Collected' THEN 5
                   WHEN 'Completed' THEN 6
                   WHEN 'Rejected' THEN 7
                   WHEN 'Cancelled' THEN 8
                   ELSE 9
               END,
               o.created_at DESC
      LIMIT 200"
);
$st->execute($params);
$orders = $st->fetchAll();

$counts = [];
foreach ($pdo->query('SELECT order_status, COUNT(*) AS n FROM orders GROUP BY order_status')->fetchAll() as $r) {
    $counts[$r['order_status']] = (int) $r['n'];
}

admin_header('Order Management', 'orders');
?>

<div class="toolbar">
    <form method="get" action="<?= url('admin/orders.php') ?>" class="toolbar" style="margin:0;flex:1">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Order ID, customer name or phone&hellip;" style="min-width:250px">
        <?php if ($filter !== ''): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
        <button type="submit" class="btn btn-ghost btn-sm"><?= icon('search', 15) ?> Search</button>
        <?php if ($q !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/orders.php') ?>">Clear</a><?php endif; ?>
    </form>
    <a class="btn btn-accent" href="<?= url('admin/verify_order.php') ?>"><?= icon('scan', 16) ?> Verify &amp; collect</a>
</div>

<div class="chip-row">
    <a class="chip" href="<?= url('admin/orders.php') ?>" style="<?= $filter === '' ? 'border-color:var(--green);color:var(--green)' : '' ?>">
        All (<?= array_sum($counts) ?>)
    </a>
    <?php foreach ($allowed as $s): ?>
        <a class="chip" href="<?= url('admin/orders.php?status=' . urlencode($s)) ?>"
           style="<?= $filter === $s ? 'border-color:var(--green);color:var(--green)' : '' ?>">
            <?= e($s) ?> (<?= $counts[$s] ?? 0 ?>)
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-head">
        <h2><?= count($orders) ?> order(s)</h2>
        <span class="small muted">Orders can only move one step at a time, in the correct order.</span>
    </div>

    <?php if (!$orders): ?>
        <p class="empty-note">No orders match this view.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Order</th><th>Customer</th><th class="num">Items</th><th class="num">Total</th>
                        <th>Payment</th><th>Status</th><th>Move to</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o):
                    $next = next_status($o['order_status']);
                    $canReject = in_array('Rejected', order_transitions()[$o['order_status']] ?? [], true);
                ?>
                    <tr>
                        <td>
                            <span class="mono"><?= e($o['order_code']) ?></span><br>
                            <span class="small muted"><?= date('d M Y, g:i A', strtotime($o['created_at'])) ?></span>
                        </td>
                        <td>
                            <strong><?= e($o['customer_name']) ?></strong><br>
                            <span class="small muted"><?= e($o['customer_phone']) ?></span>
                        </td>
                        <td class="num"><?= (int) $o['item_count'] ?></td>
                        <td class="num"><strong><?= money($o['total_amount']) ?></strong></td>
                        <td>
                            <span class="small"><?= e($o['payment_method']) ?></span><br>
                            <?= pay_badge($o['payment_status']) ?>
                        </td>
                        <td><?= status_badge($o['order_status']) ?></td>
                        <td>
                            <?php if ($next): ?>
                                <form method="post" class="inline-form"
                                      action="<?= url('admin/orders.php' . ($filter !== '' ? '?status=' . urlencode($filter) : '')) ?>"
                                      data-confirm="Change <?= e($o['order_code']) ?> to &quot;<?= e($next) ?>&quot;?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= e($next) ?>">
                                    <button type="submit" class="btn btn-sm"><?= e(next_action_label($next)) ?></button>
                                </form>
                            <?php else: ?>
                                <span class="small muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <?php if ($canReject): ?>
                                    <form method="post" style="margin:0"
                                          action="<?= url('admin/orders.php' . ($filter !== '' ? '?status=' . urlencode($filter) : '')) ?>"
                                          data-confirm="Reject <?= e($o['order_code']) ?>? The stock will go back on the shelf.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                                        <input type="hidden" name="new_status" value="Rejected">
                                        <button type="submit" class="btn btn-ghost btn-sm" title="Reject this order"><?= icon('close', 14) ?></button>
                                    </form>
                                <?php endif; ?>
                                <a class="btn btn-ghost btn-sm" href="<?= url('admin/order_details.php?code=' . urlencode($o['order_code'])) ?>">Open</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php admin_footer(); ?>
