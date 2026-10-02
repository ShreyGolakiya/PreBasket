<?php
/*
 * admin/order_details.php  -  Everything about one order, for the staff
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$pdo  = db();
$code = get_str('code', 20);

if (!valid_order_code($code)) {
    flash('error', 'Invalid order number.');
    redirect('admin/orders.php');
}

$order = find_order($pdo, $code);
if (!$order) {
    flash('error', 'Order not found.');
    redirect('admin/orders.php');
}

if (is_post()) {
    require_csrf('admin/order_details.php?code=' . urlencode($code));

    try {
        $msg = change_order_status($pdo, (int) $order['order_id'], post_str('new_status', 30), 'admin');
        flash('success', $msg);
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    } catch (Throwable $ex) {
        log_exception($ex);
        flash('error', 'Something went wrong. Please try again.');
    }
    redirect('admin/order_details.php?code=' . urlencode($code));
}

$items = find_order_items($pdo, (int) $order['order_id']);

$pay = $pdo->prepare('SELECT * FROM payments WHERE order_id = ?');
$pay->execute([$order['order_id']]);
$payment = $pay->fetch();

$fb = $pdo->prepare('SELECT * FROM feedback WHERE order_id = ?');
$fb->execute([$order['order_id']]);
$feedback = $fb->fetch();

$status      = $order['order_status'];
$transitions = order_transitions()[$status] ?? [];
$stepNow     = array_search($status, ORDER_FLOW, true);
$isBad       = in_array($status, ['Rejected', 'Cancelled'], true);

admin_header('Order ' . $order['order_code'], 'orders');
?>

<p><a class="btn btn-ghost btn-sm" href="<?= url('admin/orders.php') ?>">&larr; Back to all orders</a></p>

<div class="admin-grid">
    <div>
        <!-- ---------------- items ---------------- -->
        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 class="mono" style="color:var(--green);margin:0"><?= e($order['order_code']) ?></h2>
                    <span class="small muted">Placed <?= date('d M Y, g:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <div style="display:flex;gap:7px;flex-wrap:wrap">
                    <?= status_badge($status) ?><?= pay_badge($order['payment_status']) ?>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th colspan="2">Product</th><th class="num">Price paid</th><th class="num">Qty</th><th class="num">Subtotal</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td style="width:50px"><img class="thumb-sm" src="<?= product_image($it['image']) ?>" alt="" width="40" height="40"></td>
                            <td>
                                <strong><?= e($it['product_name_snapshot']) ?></strong>
                                <?php if ($it['product_status'] !== 'active'): ?>
                                    <br><span class="badge tag-inactive">Product now removed</span>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= money($it['price_at_purchase']) ?></td>
                            <td class="num"><strong><?= (int) $it['quantity'] ?></strong></td>
                            <td class="num"><?= money($it['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="num"><strong>Total</strong></td>
                            <td class="num"><strong style="color:var(--green);font-size:16px"><?= money($order['total_amount']) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ---------------- progress ---------------- -->
        <div class="panel">
            <h2>Progress</h2>
            <?php if ($isBad): ?>
                <div class="flash flash-error" style="margin:8px 0 0">
                    <span>This order was <?= e(strtolower($status)) ?> and the stock has been returned.</span>
                </div>
            <?php else: ?>
                <ol class="track">
                    <?php foreach (ORDER_FLOW as $i => $step):
                        $cls = $i < $stepNow ? 'done' : ($i === $stepNow ? 'now' : 'todo'); ?>
                        <li class="<?= $cls ?>">
                            <span class="dot"><?= icon(status_icon_name($step), 19) ?></span>
                            <span>
                                <strong><?= e($step) ?></strong>
                                <small><?= $i < $stepNow ? 'Done' : ($i === $stepNow ? 'Current step' : 'Waiting') ?></small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>

        <?php if ($feedback): ?>
            <div class="panel">
                <h2>Customer feedback</h2>
                <p class="star-static"><?php for ($i = 0; $i < (int) $feedback['rating']; $i++) { echo icon('star', 20); } ?>
                    <strong style="margin-left:6px"><?= (int) $feedback['rating'] ?> / 5</strong></p>
                <?php if ($feedback['comments']): ?><p class="muted"><?= e($feedback['comments']) ?></p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ---------------- right: customer + actions ---------------- -->
    <div>
        <div class="panel">
            <h2>Customer</h2>
            <div class="mini-line"><span>Name</span><span><strong><?= e($order['customer_name']) ?></strong></span></div>
            <div class="mini-line"><span>Phone</span><span><a href="tel:<?= e($order['customer_phone']) ?>"><?= e($order['customer_phone']) ?></a></span></div>
            <div class="mini-line"><span>Email</span><span class="small"><?= e($order['customer_email']) ?></span></div>
        </div>

        <div class="panel">
            <h2>Payment</h2>
            <div class="mini-line"><span>Method</span><span><?= e($order['payment_method']) ?></span></div>
            <div class="mini-line"><span>Status</span><span><?= pay_badge($order['payment_status']) ?></span></div>
            <?php if ($payment): ?>
                <div class="mini-line"><span>Amount</span><span><strong><?= money($payment['amount']) ?></strong></span></div>
                <?php if ($payment['transaction_ref']): ?>
                    <div class="mini-line"><span>Demo reference</span><span class="mono small"><?= e($payment['transaction_ref']) ?></span></div>
                <?php endif; ?>
                <?php if ($payment['paid_at']): ?>
                    <div class="mini-line"><span>Paid on</span><span class="small"><?= date('d M Y, g:i A', strtotime($payment['paid_at'])) ?></span></div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($order['payment_method'] === 'Cash at Store' && $order['payment_status'] === 'Pending'): ?>
                <div class="free-note" style="text-align:left;margin-top:12px">
                    <?= icon('alert', 16) ?>
                    <span>Collect <strong><?= money($order['total_amount']) ?></strong> in cash at the counter.
                          It is marked Paid automatically when you mark the order Collected.</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>Change status</h2>

            <?php if (!$transitions): ?>
                <p class="small muted">This order is finished. No further changes are possible.</p>
            <?php else: ?>
                <p class="small muted" style="margin-top:-4px">Current status: <?= status_badge($status) ?></p>
                <div style="display:grid;gap:8px;margin-top:10px">
                    <?php foreach ($transitions as $t):
                        $danger = in_array($t, ['Rejected', 'Cancelled'], true); ?>
                        <form method="post" style="margin:0"
                              data-confirm="Change this order to &quot;<?= e($t) ?>&quot;?<?= $danger ? ' The stock will go back on the shelf.' : '' ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="new_status" value="<?= e($t) ?>">
                            <button type="submit" class="btn btn-block <?= $danger ? 'btn-ghost' : 'btn-accent' ?>">
                                <?= icon(status_icon_name($t), 16) ?> <?= e(next_action_label($t)) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($status === 'Ready for Collection'): ?>
                <div class="free-note" style="text-align:left;margin-top:12px">
                    <?= icon('scan', 16) ?>
                    <span>Waiting for the customer. Use <a href="<?= url('admin/verify_order.php?code=' . urlencode($code)) ?>">Verify &amp; Collect</a>
                          when they arrive.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php admin_footer(); ?>
