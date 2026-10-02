<?php
/*
 * admin/feedback.php  -  What customers said about their pickups
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$pdo    = db();
$filter = get_int('rating', 0);

$sql = 'SELECT f.*, o.order_code, o.total_amount, u.name AS customer_name
          FROM feedback f
          JOIN orders o ON o.order_id = f.order_id
          JOIN users  u ON u.user_id  = f.user_id';
$params = [];
if ($filter >= 1 && $filter <= 5) {
    $sql .= ' WHERE f.rating = ?';
    $params[] = $filter;
} else {
    $filter = 0;
}
$sql .= ' ORDER BY f.created_at DESC LIMIT 100';

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$stats = $pdo->query('SELECT COUNT(*) AS n, AVG(rating) AS avg_rating FROM feedback')->fetch();
$total = (int) $stats['n'];
$avg   = $total ? round((float) $stats['avg_rating'], 2) : 0;

$spread = array_fill(1, 5, 0);
foreach ($pdo->query('SELECT rating, COUNT(*) AS n FROM feedback GROUP BY rating')->fetchAll() as $r) {
    $spread[(int) $r['rating']] = (int) $r['n'];
}

admin_header('Customer Feedback', 'feedback');
?>

<div class="kpi-grid">
    <div class="kpi"><span class="kpi-ic orange"><?= icon('star', 22) ?></span>
        <div><b><?= $total ? $avg . ' / 5' : '&ndash;' ?></b><span>Average rating</span></div></div>
    <div class="kpi"><span class="kpi-ic"><?= icon('message', 22) ?></span>
        <div><b><?= $total ?></b><span>Total reviews</span></div></div>
    <div class="kpi"><span class="kpi-ic"><?= icon('st-completed', 22) ?></span>
        <div><b><?= $spread[5] + $spread[4] ?></b><span>Happy customers (4&ndash;5 stars)</span></div></div>
    <div class="kpi"><span class="kpi-ic red"><?= icon('alert', 22) ?></span>
        <div><b><?= $spread[1] + $spread[2] ?></b><span>Need attention (1&ndash;2 stars)</span></div></div>
</div>

<div class="admin-grid">
    <div>
        <div class="chip-row">
            <a class="chip" href="<?= url('admin/feedback.php') ?>" style="<?= $filter === 0 ? 'border-color:var(--green);color:var(--green)' : '' ?>">All</a>
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <a class="chip" href="<?= url('admin/feedback.php?rating=' . $i) ?>"
                   style="<?= $filter === $i ? 'border-color:var(--green);color:var(--green)' : '' ?>">
                    <?= $i ?> star<?= $i > 1 ? 's' : '' ?> (<?= $spread[$i] ?>)
                </a>
            <?php endfor; ?>
        </div>

        <?php if (!$rows): ?>
            <p class="empty-note">No feedback yet. Customers can rate an order once they have collected it.</p>
        <?php else: ?>
            <?php foreach ($rows as $f): ?>
                <article class="fb-card">
                    <div class="fb-top">
                        <div>
                            <strong><?= e($f['customer_name']) ?></strong>
                            <a class="mono small" href="<?= url('admin/order_details.php?code=' . urlencode($f['order_code'])) ?>">
                                <?= e($f['order_code']) ?>
                            </a>
                            <span class="small muted">&middot; <?= money($f['total_amount']) ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span class="star-static">
                                <?php for ($i = 0; $i < (int) $f['rating']; $i++) { echo icon('star', 17); } ?>
                            </span>
                            <span class="badge <?= (int) $f['rating'] >= 4 ? 'tag-active' : ((int) $f['rating'] <= 2 ? 'tag-inactive' : 'st-pending') ?>">
                                <?= (int) $f['rating'] ?> / 5
                            </span>
                        </div>
                    </div>

                    <?php if ($f['comments']): ?>
                        <p><?= e($f['comments']) ?></p>
                    <?php else: ?>
                        <p class="small muted"><em>No comment was written.</em></p>
                    <?php endif; ?>

                    <p class="small muted" style="margin-top:8px"><?= date('d M Y, g:i A', strtotime($f['created_at'])) ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Rating spread</h2>
        <?php for ($i = 5; $i >= 1; $i--):
            $pct = $total ? round($spread[$i] * 100 / $total) : 0; ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:9px">
                <span class="small" style="width:46px"><?= $i ?> star</span>
                <span style="flex:1;height:9px;background:#eef2f0;border-radius:999px;overflow:hidden">
                    <span style="display:block;height:100%;width:<?= $pct ?>%;background:var(--green)"></span>
                </span>
                <span class="small muted" style="width:52px;text-align:right"><?= $spread[$i] ?> (<?= $pct ?>%)</span>
            </div>
        <?php endfor; ?>

        <?php if ($total === 0): ?>
            <p class="small muted" style="margin-top:12px">Nothing to show yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php admin_footer(); ?>
