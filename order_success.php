<?php
/*
 * order_success.php  -  Shown right after an order is placed
 * The QR code is drawn on an HTML5 <canvas> by assets/js/qrcode.js
 * (our own small offline library - no internet needed).
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo  = db();
$code = get_str('code', 20);

if (!valid_order_code($code)) {
    flash('error', 'Invalid order number.');
    redirect('orders.php');
}

$order = find_order($pdo, $code, current_user_id());
if (!$order) {
    flash('error', 'Order not found.');
    redirect('orders.php');
}

$items = find_order_items($pdo, (int) $order['order_id']);

$pageTitle = 'Order placed';
$activeNav = 'orders';
require __DIR__ . '/includes/header.php';
?>

<div class="container success-wrap">
    <div class="success-card">
        <div class="tick"><?= icon('st-accepted', 40) ?></div>
        <h1>Order placed successfully!</h1>
        <p class="muted">We have received your pre-order. The store will start packing it shortly.</p>

        <p class="small muted" style="margin-bottom:2px">Your Order ID</p>
        <p class="order-code"><?= e($order['order_code']) ?></p>

        <div class="qr-holder">
            <canvas id="orderQr" width="190" height="190" aria-label="QR code for order <?= e($order['order_code']) ?>"></canvas>
        </div>
        <p class="small muted">Show this QR code (or just the Order ID) at the collection counter.</p>

        <div class="ticket-cut"></div>

        <div style="text-align:left">
            <div class="mini-line"><span>Order status</span><span><?= status_badge($order['order_status']) ?></span></div>
            <div class="mini-line"><span>Payment method</span><span><?= e($order['payment_method']) ?></span></div>
            <div class="mini-line"><span>Payment status</span><span><?= pay_badge($order['payment_status']) ?></span></div>
            <div class="mini-line"><span>Placed on</span><span><?= date('d M Y, g:i A', strtotime($order['created_at'])) ?></span></div>
            <div class="mini-line"><span>Items</span><span><?= count($items) ?> product(s)</span></div>
            <div class="mini-line"><span><strong>Total amount</strong></span><span><strong style="color:var(--green);font-size:18px"><?= money($order['total_amount']) ?></strong></span></div>
        </div>

        <?php if ($order['payment_method'] === 'Cash at Store'): ?>
            <div class="free-note" style="margin-top:16px;text-align:left">
                <?= icon('alert', 17) ?>
                <span>Please carry <strong><?= money($order['total_amount']) ?></strong> in cash. You will pay at the counter when you collect.</span>
            </div>
        <?php endif; ?>

        <div class="buy-row no-print" style="justify-content:center;margin-top:20px">
            <a class="btn" href="<?= url('order_details.php?code=' . urlencode($order['order_code'])) ?>">
                <?= icon('list', 17) ?> Track this order
            </a>
            <button type="button" class="btn btn-ghost" onclick="window.print()"><?= icon('qr', 17) ?> Print / save ticket</button>
            <a class="btn btn-ghost" href="<?= url('products.php') ?>">Continue shopping</a>
        </div>
    </div>

    <div class="panel" style="margin-top:18px">
        <h2>What happens next?</h2>
        <ol class="small" style="margin:0;padding-left:20px;line-height:2">
            <li>The store accepts your order and starts preparing it.</li>
            <li>You will see the status change to <b>Ready for Collection</b> on the My Orders page.</li>
            <li>Walk in, show <b><?= e($order['order_code']) ?></b> or the QR code at the counter.</li>
            <li>Staff hands over your parcel and marks the order <b>Collected</b>.</li>
        </ol>
    </div>
</div>

<?php
$pageScripts  = ['assets/js/qrcode.js'];
$inlineScript = 'try {'
              . ' PBQR.draw(document.getElementById("orderQr"), ' . json_encode($order['order_code']) . ', 5);'
              . '} catch (err) {'
              . ' var c = document.getElementById("orderQr");'
              . ' if (c) { c.parentElement.innerHTML = "<p class=\'small muted\'>QR could not be drawn. Please use the Order ID.</p>"; }'
              . '}';
require __DIR__ . '/includes/footer.php';
?>
