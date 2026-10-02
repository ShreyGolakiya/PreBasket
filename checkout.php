<?php
/*
 * checkout.php  -  Review the order and choose how to pay
 *
 *   Cash at Store  -> the order is placed straight away (payment Pending)
 *   Online Payment -> we go to the DEMO payment page first
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo    = db();
$userId = current_user_id();
$user   = current_user();

$items    = cart_items($userId);
$problems = cart_problems($items);
$total    = cart_total($items);

if (!$items) {
    flash('info', 'Your cart is empty. Add some products first.');
    redirect('products.php');
}

if (is_post()) {
    require_csrf('checkout.php');

    $method = post_str('payment_method', 30);

    if ($problems) {
        flash('error', 'Please fix the items in your cart before placing the order.');
        redirect('cart.php');
    }
    if (!in_array($method, ['Online Payment', 'Cash at Store'], true)) {
        flash('error', 'Please choose a payment method.');
        redirect('checkout.php');
    }

    if ($method === 'Online Payment') {
        // Remember the choice and go to the demo payment screen.
        $_SESSION['checkout'] = ['method' => $method, 'amount' => $total, 'at' => time()];
        redirect('payment.php');
    }

    // ---- Cash at Store: place the order now
    try {
        $result = place_order($pdo, $userId, $method, false);
        flash('success', 'Order placed successfully!');
        redirect('order_success.php?code=' . urlencode($result['order_code']));
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
        redirect('cart.php');
    } catch (Throwable $ex) {
        log_exception($ex);
        flash('error', 'Something went wrong while placing your order. Please try again.');
        redirect('cart.php');
    }
}

$count = 0;
foreach ($items as $it) { $count += (int) $it['quantity']; }

$pageTitle = 'Checkout';
$activeNav = 'cart';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-head">
        <h1>Checkout</h1>
        <p>Check your basket, choose how you want to pay, and we will start packing.</p>
    </div>

    <?php if ($problems): ?>
        <div class="flash flash-error" style="margin-bottom:16px">
            <span>Some items are no longer available in the quantity you chose.
                  <a href="<?= url('cart.php') ?>">Go back to the cart</a> to fix them.</span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= url('checkout.php') ?>">
        <?= csrf_field() ?>

        <div class="two-col">
            <div>
                <!-- ---- who is collecting ---- -->
                <div class="panel">
                    <h2>Collection details</h2>
                    <div class="mini-line"><span>Name</span><span><strong><?= e($user['name']) ?></strong></span></div>
                    <div class="mini-line"><span>Phone</span><span><?= e($user['phone']) ?></span></div>
                    <div class="mini-line"><span>Email</span><span><?= e($user['email']) ?></span></div>
                    <div class="mini-line"><span>Collect from</span><span>PreBasket Supermarket &mdash; Counter 2</span></div>
                    <p class="small muted" style="margin:12px 0 0">
                        <?= icon('alert', 14) ?> Show your Order ID or QR code at the counter to collect your parcel.
                    </p>
                </div>

                <!-- ---- the items ---- -->
                <div class="panel">
                    <h2>Your items (<?= $count ?>)</h2>
                    <div class="table-wrap">
                        <table class="data">
                            <thead>
                                <tr><th>Product</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Subtotal</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($it['product_name']) ?></strong><br>
                                        <span class="small muted"><?= e($it['unit_label']) ?></span>
                                    </td>
                                    <td class="num"><?= money($it['price']) ?></td>
                                    <td class="num"><?= (int) $it['quantity'] ?></td>
                                    <td class="num"><strong><?= money((float) $it['price'] * (int) $it['quantity']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ---- payment method ---- -->
                <div class="panel">
                    <h2>Payment method</h2>
                    <div class="pay-options">
                        <label class="pay-opt">
                            <input type="radio" name="payment_method" value="Online Payment" checked>
                            <span>
                                <strong><?= icon('shield', 16) ?> Pay online now (demo)</strong>
                                <small>A practice payment screen for this college project. No real money and no real
                                       payment gateway is used. Your order is marked <b>Paid</b> straight away.</small>
                            </span>
                        </label>

                        <label class="pay-opt">
                            <input type="radio" name="payment_method" value="Cash at Store">
                            <span>
                                <strong><?= icon('store', 16) ?> Cash at Store</strong>
                                <small>Pay at the collection counter when you pick up your parcel.
                                       Payment status stays <b>Pending</b> until then.</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- ---- summary ---- -->
            <aside>
                <div class="panel">
                    <h2>Order summary</h2>
                    <div class="summary-row"><span>Items (<?= $count ?>)</span><span><?= money($total) ?></span></div>
                    <div class="summary-row"><span>Packing</span><span>Free</span></div>
                    <div class="summary-row"><span>Delivery</span><span>&mdash;</span></div>
                    <div class="summary-row total"><span>Total payable</span><span><?= money($total) ?></span></div>

                    <?php if ($problems): ?>
                        <a class="btn btn-lg btn-block" href="<?= url('cart.php') ?>">Back to cart</a>
                    <?php else: ?>
                        <button type="submit" class="btn btn-accent btn-lg btn-block">Place order</button>
                    <?php endif; ?>

                    <p class="small muted center" style="margin:12px 0 0">
                        By placing the order you agree to collect it from the store.
                    </p>
                </div>
            </aside>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
