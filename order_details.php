<?php
/*
 * order_details.php  -  One order: items, tracking timeline and QR code
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo    = db();
$userId = current_user_id();
$code   = get_str('code', 20);

if (!valid_order_code($code)) {
    flash('error', 'Invalid order number.');
    redirect('orders.php');
}

$order = find_order($pdo, $code, $userId);
if (!$order) {
    flash('error', 'Order not found.');
    redirect('orders.php');
}

// ---- the customer may cancel an order that is still Pending
if (is_post()) {
    require_csrf('order_details.php?code=' . urlencode($code));

    if (post_str('action', 20) === 'cancel') {
        try {
            $msg = change_order_status($pdo, (int) $order['order_id'], 'Cancelled', 'customer', $userId);
            flash('success', $msg . ' The items have been put back in stock.');
        } catch (AppException $ex) {
            flash('error', $ex->getMessage());
        } catch (Throwable $ex) {
            log_exception($ex);
            flash('error', 'Something went wrong. Please try again.');
        }
    }
    redirect('order_details.php?code=' . urlencode($code));
}

$items = find_order_items($pdo, (int) $order['order_id']);

$fb = $pdo->prepare('SELECT * FROM feedback WHERE order_id = ?');
$fb->execute([$order['order_id']]);
$feedback = $fb->fetch();

$status   = $order['order_status'];
$isBad    = in_array($status, ['Rejected', 'Cancelled'], true);
$stepNow  = array_search($status, ORDER_FLOW, true);

$stepNotes = [
    'Pending'              => 'We have received your order and sent it to the store.',
    'Accepted'             => 'The store has accepted your order.',
    'Preparing'            => 'Staff are picking and packing your items.',
    'Ready for Collection' => 'Your parcel is packed. Come and collect it!',
    'Collected'            => 'You have collected your parcel.',
    'Completed'            => 'Order finished. Thank you for shopping with QuickCart.',
];

$pageTitle = 'Order ' . $order['order_code'];
$activeNav = 'orders';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <nav class="crumbs">
        <a href="<?= url('index.php') ?>">Home</a><span>&rsaquo;</span>
        <a href="<?= url('orders.php') ?>">My Orders</a><span>&rsaquo;</span>
        <?= e($order['order_code']) ?>
    </nav>

    <div class="page-head">
        <h1 class="mono" style="color:var(--green)"><?= e($order['order_code']) ?></h1>
        <p>
            Placed on <?= date('d M Y, g:i A', strtotime($order['created_at'])) ?>
            &middot; <?= status_badge($status) ?> <?= pay_badge($order['payment_status']) ?>
        </p>
    </div>

    <div class="two-col">
        <div>
            <!-- ---------- tracking ---------- -->
            <div class="panel">
                <h2>Order tracking</h2>

                <?php if ($isBad): ?>
                    <div class="flash flash-error" style="margin:8px 0 0">
                        <span>This order was <?= e(strtolower($status)) ?>. Any stock has been returned to the shelf
                              <?= $order['payment_status'] === 'Refunded' ? 'and the demo payment was marked as refunded' : '' ?>.</span>
                    </div>
                <?php else: ?>
                    <ol class="track">
                        <?php foreach (ORDER_FLOW as $i => $step):
                            $cls = 'todo';
                            if ($i < $stepNow)  { $cls = 'done'; }
                            if ($i === $stepNow) { $cls = 'now'; }
                        ?>
                            <li class="<?= $cls ?>">
                                <span class="dot"><?= icon(status_icon_name($step), 19) ?></span>
                                <span>
                                    <strong><?= e($step) ?></strong>
                                    <small><?= e($stepNotes[$step]) ?></small>
                                    <?php if ($i === $stepNow): ?>
                                        <br><small class="muted">Updated <?= date('d M, g:i A', strtotime($order['updated_at'])) ?></small>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>

            <!-- ---------- items ---------- -->
            <div class="panel">
                <div class="panel-head">
                    <h2>Items in this order</h2>
                    <form method="post" action="<?= url('cart_action.php') ?>" style="margin:0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reorder">
                        <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm"><?= icon('repeat', 15) ?> Reorder these items</button>
                    </form>
                </div>

                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th colspan="2">Product</th><th class="num">Price paid</th><th class="num">Qty</th><th class="num">Subtotal</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td style="width:54px">
                                    <img class="thumb-sm" src="<?= product_image($it['image']) ?>" alt="" width="40" height="40">
                                </td>
                                <td>
                                    <!-- the NAME AND PRICE STORED AT PURCHASE TIME are shown, not today's values -->
                                    <strong><?= e($it['product_name_snapshot']) ?></strong>
                                    <?php if ($it['product_status'] !== 'active'): ?>
                                        <br><span class="badge tag-inactive">No longer sold</span>
                                    <?php elseif ((int) $it['current_stock'] <= 0): ?>
                                        <br><span class="badge st-pending">Currently out of stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="num"><?= money($it['price_at_purchase']) ?></td>
                                <td class="num"><?= (int) $it['quantity'] ?></td>
                                <td class="num"><strong><?= money($it['subtotal']) ?></strong></td>
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

                <p class="small muted" style="margin:12px 0 0">
                    <?= icon('shield', 14) ?> Prices shown are the prices at the time you ordered. They never change later.
                </p>
            </div>

            <!-- ---------- feedback ---------- -->
            <?php if ($feedback): ?>
                <div class="panel">
                    <h2>Your feedback</h2>
                    <p class="star-static">
                        <?php for ($i = 0; $i < 5; $i++) { echo icon($i < (int) $feedback['rating'] ? 'star' : 'star', 20, $i < (int) $feedback['rating'] ? '' : 'dim'); } ?>
                        <strong style="margin-left:6px"><?= (int) $feedback['rating'] ?> / 5</strong>
                    </p>
                    <?php if ($feedback['comments']): ?><p class="muted"><?= e($feedback['comments']) ?></p><?php endif; ?>
                </div>
            <?php elseif (in_array($status, ['Collected', 'Completed'], true)): ?>
                <div class="panel center">
                    <h2>How was your pickup?</h2>
                    <p class="muted">Your feedback helps the store improve.</p>
                    <a class="btn btn-accent" href="<?= url('feedback.php?code=' . urlencode($order['order_code'])) ?>">
                        <?= icon('star', 16) ?> Leave feedback
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ---------- right column: the pickup ticket ---------- -->
        <aside>
            <div class="panel center">
                <h2>Collection ticket</h2>
                <p class="small muted" style="margin-top:-6px">Show this at the counter</p>

                <?php if (!$isBad): ?>
                    <div class="qr-holder">
                        <canvas id="orderQr" width="190" height="190"
                                aria-label="QR code for order <?= e($order['order_code']) ?>"></canvas>
                    </div>
                <?php endif; ?>

                <p class="order-code" style="font-size:19px"><?= e($order['order_code']) ?></p>

                <div style="text-align:left;margin-top:12px">
                    <div class="mini-line"><span>Status</span><span><?= status_badge($status) ?></span></div>
                    <div class="mini-line"><span>Payment</span><span><?= e($order['payment_method']) ?></span></div>
                    <div class="mini-line"><span>Payment status</span><span><?= pay_badge($order['payment_status']) ?></span></div>
                    <div class="mini-line"><span>Collect from</span><span>Counter 2</span></div>
                    <div class="mini-line"><span><strong>Total</strong></span><span><strong><?= money($order['total_amount']) ?></strong></span></div>
                </div>

                <?php if ($order['payment_method'] === 'Cash at Store' && $order['payment_status'] === 'Pending'): ?>
                    <div class="free-note" style="text-align:left;margin-top:14px">
                        <?= icon('alert', 16) ?>
                        <span>Carry <strong><?= money($order['total_amount']) ?></strong> in cash for the counter.</span>
                    </div>
                <?php endif; ?>

                <div class="no-print" style="display:grid;gap:8px;margin-top:16px">
                    <button type="button" class="btn btn-ghost" onclick="window.print()">Print ticket</button>

                    <?php if ($status === 'Pending'): ?>
                        <form method="post" data-confirm="Cancel this order? The items will go back on the shelf." style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-danger btn-block">Cancel this order</button>
                        </form>
                    <?php endif; ?>

                    <a class="btn btn-ghost" href="<?= url('orders.php') ?>">Back to my orders</a>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php
$pageScripts = ['assets/js/qrcode.js'];
if (!$isBad) {
    $inlineScript = 'try {'
                  . ' PBQR.draw(document.getElementById("orderQr"), ' . json_encode($order['order_code']) . ', 5);'
                  . '} catch (err) {'
                  . ' var c = document.getElementById("orderQr");'
                  . ' if (c) { c.parentElement.innerHTML = "<p class=\'small muted\'>Please use the Order ID above.</p>"; }'
                  . '}';
}
require __DIR__ . '/includes/footer.php';
?>
