<?php
/*
 * orders.php  -  The customer's own order history
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo    = db();
$userId = current_user_id();

$filter  = get_str('status', 30);
$allowed = array_merge(ORDER_FLOW, ['Rejected', 'Cancelled']);

$sql    = 'SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count,
                  (SELECT COUNT(*) FROM feedback f WHERE f.order_id = o.order_id) AS has_feedback
             FROM orders o WHERE o.user_id = ?';
$params = [$userId];

if ($filter !== '' && in_array($filter, $allowed, true)) {
    $sql .= ' AND o.order_status = ?';
    $params[] = $filter;
} else {
    $filter = '';
}
$sql .= ' ORDER BY o.created_at DESC, o.order_id DESC';

$st = $pdo->prepare($sql);
$st->execute($params);
$orders = $st->fetchAll();

// small thumbnails for each order
$thumbs = [];
if ($orders) {
    $ids = array_column($orders, 'order_id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $ts  = $pdo->prepare("SELECT oi.order_id, p.image FROM order_items oi
                            JOIN products p ON p.product_id = oi.product_id
                           WHERE oi.order_id IN ($in) ORDER BY oi.order_item_id");
    $ts->execute($ids);
    foreach ($ts->fetchAll() as $row) {
        $thumbs[$row['order_id']][] = $row['image'];
    }
}

$counts = $pdo->prepare('SELECT order_status, COUNT(*) AS n FROM orders WHERE user_id = ? GROUP BY order_status');
$counts->execute([$userId]);
$byStatus = [];
foreach ($counts->fetchAll() as $r) { $byStatus[$r['order_status']] = (int) $r['n']; }

$pageTitle = 'My Orders';
$activeNav = 'orders';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-head">
        <h1>My Orders</h1>
        <p>Track your pre-orders and reorder your favourites in one click.</p>
    </div>

    <div class="chip-row">
        <a class="chip" href="<?= url('orders.php') ?>" style="<?= $filter === '' ? 'border-color:var(--green);color:var(--green)' : '' ?>">
            All (<?= array_sum($byStatus) ?>)
        </a>
        <?php foreach ($allowed as $s): if (empty($byStatus[$s])) { continue; } ?>
            <a class="chip" href="<?= url('orders.php?status=' . urlencode($s)) ?>"
               style="<?= $filter === $s ? 'border-color:var(--green);color:var(--green)' : '' ?>">
                <?= e($s) ?> (<?= $byStatus[$s] ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$orders): ?>
        <div class="empty-note" style="padding:44px">
            <div style="color:var(--green);margin-bottom:10px"><?= icon('box', 54) ?></div>
            <h2>No orders yet</h2>
            <p>When you place a pre-order it will show up here with its QR code and live status.</p>
            <a class="btn btn-accent btn-lg" href="<?= url('products.php') ?>">Start shopping</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $o):
            $canCancel   = $o['order_status'] === 'Pending';
            $canFeedback = in_array($o['order_status'], ['Collected', 'Completed'], true) && !$o['has_feedback'];
        ?>
            <article class="order-card">
                <div class="order-card-top">
                    <div>
                        <span class="mono" style="font-size:16px;color:var(--green)"><?= e($o['order_code']) ?></span><br>
                        <span class="small muted"><?= date('d M Y, g:i A', strtotime($o['created_at'])) ?>
                            &middot; <?= (int) $o['item_count'] ?> item(s)</span>
                    </div>
                    <div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center">
                        <?= status_badge($o['order_status']) ?>
                        <?= pay_badge($o['payment_status']) ?>
                    </div>
                </div>

                <div class="order-card-thumbs">
                    <?php foreach (array_slice($thumbs[$o['order_id']] ?? [], 0, 8) as $img): ?>
                        <img src="<?= product_image($img) ?>" alt="" width="46" height="46">
                    <?php endforeach; ?>
                    <?php if (count($thumbs[$o['order_id']] ?? []) > 8): ?>
                        <span class="chip">+<?= count($thumbs[$o['order_id']]) - 8 ?> more</span>
                    <?php endif; ?>
                </div>

                <div class="order-card-foot">
                    <div>
                        <span class="small muted"><?= e($o['payment_method']) ?></span><br>
                        <strong style="font-size:17px"><?= money($o['total_amount']) ?></strong>
                    </div>

                    <div class="row-actions" style="flex-wrap:wrap">
                        <?php if ($canFeedback): ?>
                            <a class="btn btn-accent btn-sm" href="<?= url('feedback.php?code=' . urlencode($o['order_code'])) ?>">
                                <?= icon('star', 15) ?> Rate order
                            </a>
                        <?php endif; ?>

                        <form method="post" action="<?= url('cart_action.php') ?>" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reorder">
                            <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm"><?= icon('repeat', 15) ?> Reorder</button>
                        </form>

                        <?php if ($canCancel): ?>
                            <form method="post" action="<?= url('order_details.php?code=' . urlencode($o['order_code'])) ?>"
                                  style="margin:0" data-confirm="Cancel order <?= e($o['order_code']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit" class="btn btn-ghost btn-sm">Cancel</button>
                            </form>
                        <?php endif; ?>

                        <a class="btn btn-sm" href="<?= url('order_details.php?code=' . urlencode($o['order_code'])) ?>">
                            View details <?= icon('arrow', 14) ?>
                        </a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
