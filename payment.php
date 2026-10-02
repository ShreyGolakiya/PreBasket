<?php
/*
 * payment.php  -  DEMO payment screen
 * ---------------------------------------------------------------
 * IMPORTANT: this is NOT a real payment gateway. No card data is
 * stored or sent anywhere. It only exists so the project can show
 * the "Online Payment" path of the order flow.
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo    = db();
$userId = current_user_id();

$checkout = $_SESSION['checkout'] ?? null;
if (!$checkout || ($checkout['method'] ?? '') !== 'Online Payment') {
    flash('info', 'Please choose a payment method first.');
    redirect('checkout.php');
}

$items    = cart_items($userId);
$problems = cart_problems($items);
$total    = cart_total($items);

if (!$items) {
    unset($_SESSION['checkout']);
    flash('info', 'Your cart is empty.');
    redirect('products.php');
}
if ($problems) {
    flash('error', 'Some items changed. Please check your cart again.');
    redirect('cart.php');
}

if (is_post()) {
    require_csrf('checkout.php');

    if (post_str('demo_pay', 10) !== 'yes') {
        flash('info', 'Payment cancelled.');
        redirect('checkout.php');
    }

    try {
        // The money is "paid" in the demo, so payment_status becomes Paid.
        $result = place_order($pdo, $userId, 'Online Payment', true);
        unset($_SESSION['checkout']);
        flash('success', 'Demo payment successful. Order placed!');
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

$user      = current_user();
$pageTitle = 'Demo payment';
$activeNav = 'cart';
require __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width:640px">
    <div class="page-head center" style="margin-top:28px">
        <h1>Demo payment</h1>
        <p>Practice screen for the college project.</p>
    </div>

    <div class="demo-strip">
        <?= icon('alert', 16) ?>
        <strong>This is a demo.</strong> No real payment gateway is connected and no card details are stored.
        Clicking &ldquo;Pay Now&rdquo; simply marks the order as Paid in the database.
    </div>

    <div class="paycard">
        <div class="pc-top">
            <span>PreBasket Demo Card</span>
            <span><?= icon('shield', 18) ?></span>
        </div>
        <div class="pc-num">4242&nbsp;&nbsp;4242&nbsp;&nbsp;4242&nbsp;&nbsp;4242</div>
        <div class="pc-bot">
            <span><?= e(strtoupper($user['name'])) ?></span>
            <span>VALID 12 / 30</span>
        </div>
    </div>

    <div class="panel">
        <h2>Amount to pay</h2>
        <div class="summary-row"><span>Items</span><span><?= count($items) ?> product(s)</span></div>
        <div class="summary-row"><span>Payment method</span><span>Online Payment (demo)</span></div>
        <div class="summary-row total"><span>Total</span><span><?= money($total) ?></span></div>

        <form method="post" action="<?= url('payment.php') ?>" style="margin-top:18px"
              data-confirm="Confirm the demo payment of <?= e(money($total)) ?> and place your order?">
            <?= csrf_field() ?>
            <input type="hidden" name="demo_pay" value="yes">
            <button type="submit" class="btn btn-accent btn-lg btn-block" id="payBtn">
                <?= icon('shield', 18) ?> Pay Now <?= e(money($total)) ?>
            </button>
        </form>

        <p class="center" style="margin:14px 0 0">
            <a class="small muted" href="<?= url('checkout.php') ?>">Cancel and go back to checkout</a>
        </p>
    </div>
</div>

<?php
// A tiny touch: the button shows "Processing..." so the demo feels real.
$inlineScript = <<<'JS'
var payForm = document.querySelector('form[action$="payment.php"]');
if (payForm) {
    payForm.addEventListener('submit', function () {
        var b = document.getElementById('payBtn');
        b.disabled = true;
        b.textContent = 'Processing payment\u2026';
    });
}
JS;
require __DIR__ . '/includes/footer.php';
?>
