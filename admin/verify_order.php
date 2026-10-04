<?php
/*
 * admin/verify_order.php  -  The collection counter screen
 * ---------------------------------------------------------------
 * The customer shows the Order ID (or the QR code, which simply
 * contains that same Order ID). Staff type or scan it here, check
 * the order on screen, and mark it Collected.
 *
 * Typing the Order ID always works, so the counter never depends on
 * a camera. Any USB/bluetooth barcode scanner also works, because
 * those act like a keyboard and just type the code into the box.
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$pdo   = db();
$code  = strtoupper(trim(get_str('code', 20)));
$error = '';
$order = null;
$items = [];

/* ---------- staff pressed "Mark Collected" / "Mark Completed" ---------- */
if (is_post()) {
    require_csrf('admin/verify_order.php');

    $postCode  = strtoupper(post_str('order_code', 20));
    $newStatus = post_str('new_status', 30);

    if (!valid_order_code($postCode)) {
        flash('error', 'Invalid Order ID.');
        redirect('admin/verify_order.php');
    }

    $target = find_order($pdo, $postCode);
    if (!$target) {
        flash('error', 'Order not found.');
        redirect('admin/verify_order.php');
    }

    try {
        $msg = change_order_status($pdo, (int) $target['order_id'], $newStatus, 'admin');
        flash('success', $msg);
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    } catch (Throwable $ex) {
        log_exception($ex);
        flash('error', 'Something went wrong. Please try again.');
    }
    redirect('admin/verify_order.php?code=' . urlencode($postCode));
}

/* ---------- look up the code that was typed ---------- */
if ($code !== '') {
    if (!valid_order_code($code)) {
        $error = 'That does not look like a QuickCart Order ID. It should look like PB' . date('Ymd') . '0001.';
    } else {
        $order = find_order($pdo, $code);
        if (!$order) {
            $error = 'No order found with the ID ' . $code . '. Please check and try again.';
        } else {
            $items = find_order_items($pdo, (int) $order['order_id']);
        }
    }
}

$ready = $pdo->query(
    "SELECT o.order_code, o.total_amount, o.payment_method, o.payment_status, u.name
       FROM orders o JOIN users u ON u.user_id = o.user_id
      WHERE o.order_status = 'Ready for Collection'
      ORDER BY o.updated_at ASC LIMIT 12"
)->fetchAll();

admin_header('Verify & Collect', 'verify');
?>

<div class="admin-grid">
    <div>
        <!-- ---------------- the search box ---------------- -->
        <div class="panel">
            <h2>Scan or type the Order ID</h2>
            <p class="small muted" style="margin-top:-6px">
                The QR code on the customer's phone contains exactly this Order ID.
            </p>

            <form method="get" action="<?= url('admin/verify_order.php') ?>" class="verify-input" style="margin-top:12px">
                <input type="text" name="code" id="codeBox" value="<?= e($code) ?>"
                       placeholder="PB<?= date('Ymd') ?>0001" maxlength="20" autocomplete="off" autofocus
                       aria-label="Order ID">
                <button type="submit" class="btn btn-lg"><?= icon('scan', 18) ?> Check</button>
            </form>

            <?php if ($error): ?>
                <div class="flash flash-error" style="margin-top:14px"><span><?= e($error) ?></span></div>
            <?php endif; ?>
        </div>

        <!-- ---------------- the result ---------------- -->
        <?php if ($order): ?>
            <?php
            $canCollect  = $order['order_status'] === 'Ready for Collection';
            $canComplete = $order['order_status'] === 'Collected';
            $notReady    = in_array($order['order_status'], ['Pending', 'Accepted', 'Preparing'], true);
            $isBad       = in_array($order['order_status'], ['Rejected', 'Cancelled'], true);
            ?>
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="mono" style="color:var(--green);margin:0"><?= e($order['order_code']) ?></h2>
                        <span class="small muted">Placed <?= date('d M Y, g:i A', strtotime($order['created_at'])) ?></span>
                    </div>
                    <div style="display:flex;gap:7px;flex-wrap:wrap">
                        <?= status_badge($order['order_status']) ?><?= pay_badge($order['payment_status']) ?>
                    </div>
                </div>

                <?php if ($isBad): ?>
                    <div class="flash flash-error"><span>This order was <?= e(strtolower($order['order_status'])) ?>. Do not hand over any parcel.</span></div>
                <?php elseif ($canCollect): ?>
                    <div class="flash flash-success"><span>Parcel is packed and ready. Hand it over and mark it Collected.</span></div>
                <?php elseif ($notReady): ?>
                    <div class="flash flash-warning"><span>This order is still being prepared (<?= e($order['order_status']) ?>). It is not ready yet.</span></div>
                <?php elseif ($canComplete): ?>
                    <div class="flash flash-info"><span>Already handed over. You can close it by marking it Completed.</span></div>
                <?php else: ?>
                    <div class="flash flash-info"><span>This order is already Completed.</span></div>
                <?php endif; ?>

                <div class="mini-line"><span>Customer</span><span><strong><?= e($order['customer_name']) ?></strong></span></div>
                <div class="mini-line"><span>Phone</span><span><?= e($order['customer_phone']) ?></span></div>
                <div class="mini-line"><span>Payment method</span><span><?= e($order['payment_method']) ?></span></div>
                <div class="mini-line"><span>Payment status</span><span><?= pay_badge($order['payment_status']) ?></span></div>
                <div class="mini-line"><span><strong>Amount</strong></span>
                    <span><strong style="font-size:19px;color:var(--green)"><?= money($order['total_amount']) ?></strong></span></div>

                <?php if ($order['payment_method'] === 'Cash at Store' && $order['payment_status'] === 'Pending'): ?>
                    <div class="free-note" style="text-align:left;margin-top:12px">
                        <?= icon('alert', 17) ?>
                        <span><strong>Collect <?= money($order['total_amount']) ?> in cash</strong> before handing over the parcel.
                              Marking the order Collected will set the payment to Paid.</span>
                    </div>
                <?php endif; ?>

                <h3 style="margin-top:18px">Items to hand over (<?= count($items) ?>)</h3>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th colspan="2">Product</th><th class="num">Qty</th><th class="num">Subtotal</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td style="width:50px"><img class="thumb-sm" src="<?= product_image($it['image']) ?>" alt="" width="40" height="40"></td>
                                <td><?= e($it['product_name_snapshot']) ?></td>
                                <td class="num"><strong style="font-size:16px"><?= (int) $it['quantity'] ?></strong></td>
                                <td class="num"><?= money($it['subtotal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="buy-row" style="margin-top:18px">
                    <?php if ($canCollect): ?>
                        <form method="post" style="margin:0"
                              data-confirm="Confirm that the parcel has been handed over to <?= e($order['customer_name']) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_code" value="<?= e($order['order_code']) ?>">
                            <input type="hidden" name="new_status" value="Collected">
                            <button type="submit" class="btn btn-accent btn-lg"><?= icon('st-collected', 18) ?> Mark as Collected</button>
                        </form>
                    <?php elseif ($canComplete): ?>
                        <form method="post" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_code" value="<?= e($order['order_code']) ?>">
                            <input type="hidden" name="new_status" value="Completed">
                            <button type="submit" class="btn btn-accent btn-lg"><?= icon('st-completed', 18) ?> Mark as Completed</button>
                        </form>
                    <?php endif; ?>

                    <a class="btn btn-ghost" href="<?= url('admin/order_details.php?code=' . urlencode($order['order_code'])) ?>">
                        Full order details
                    </a>
                    <a class="btn btn-ghost" href="<?= url('admin/verify_order.php') ?>">Check another order</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ---------------- waiting list ---------------- -->
    <div>
        <div class="panel">
            <div class="panel-head">
                <h2>Ready for collection</h2>
                <span class="badge tag-active"><?= count($ready) ?></span>
            </div>

            <?php if (!$ready): ?>
                <p class="small muted">No parcels are waiting at the counter right now.</p>
            <?php else: ?>
                <p class="small muted" style="margin-top:-6px">Click an Order ID to open it instantly.</p>
                <div class="table-wrap">
                    <table class="data">
                        <tbody>
                        <?php foreach ($ready as $r): ?>
                            <tr>
                                <td>
                                    <a class="mono" href="<?= url('admin/verify_order.php?code=' . urlencode($r['order_code'])) ?>">
                                        <?= e($r['order_code']) ?>
                                    </a><br>
                                    <span class="small muted"><?= e($r['name']) ?></span>
                                </td>
                                <td class="num">
                                    <strong><?= money($r['total_amount']) ?></strong><br>
                                    <?php if ($r['payment_status'] === 'Pending'): ?>
                                        <span class="badge pay-pending">Cash due</span>
                                    <?php else: ?>
                                        <span class="badge pay-paid">Paid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>How the QR works</h2>
            <ol class="small" style="margin:0;padding-left:18px;line-height:1.9">
                <li>The customer's QR code holds only the Order ID, nothing private.</li>
                <li>A USB barcode scanner types it into the box for you.</li>
                <li>No scanner? Just read the Order ID and type it &mdash; same result.</li>
                <li>Check the name and the amount on screen before handing over the parcel.</li>
            </ol>
        </div>
    </div>
</div>

<?php
// A barcode scanner types very fast and ends with Enter, so the form submits by itself.
$inline = <<<'JS'
var box = document.getElementById("codeBox");
if (box) {
    box.focus();
    box.select();
    box.addEventListener("input", function () {
        box.value = box.value.toUpperCase().replace(/[^A-Z0-9]/g, "");
        // a complete code is PB + 12 digits = 14 characters
        if (box.value.length === 14) { box.form.submit(); }
    });
}
JS;
admin_footer([], $inline);
?>
