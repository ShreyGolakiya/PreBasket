<?php
/*
 * feedback.php  -  Rate an order after it has been collected
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

// Feedback is only allowed once the customer actually has the parcel.
if (!in_array($order['order_status'], ['Collected', 'Completed'], true)) {
    flash('info', 'You can leave feedback once your order has been collected.');
    redirect('order_details.php?code=' . urlencode($code));
}

$exists = $pdo->prepare('SELECT * FROM feedback WHERE order_id = ?');
$exists->execute([$order['order_id']]);
$already = $exists->fetch();

$error   = '';
$rating  = (int) ($already['rating'] ?? 0);
$comment = (string) ($already['comments'] ?? '');

if (is_post()) {
    require_csrf('feedback.php?code=' . urlencode($code));

    $rating  = post_int('rating', 0);
    $comment = post_str('comments', 500);

    if ($rating < 1 || $rating > 5) {
        $error = 'Please choose a rating between 1 and 5 stars.';
    } else {
        try {
            // UNIQUE key on order_id means one feedback per order
            $pdo->prepare(
                'INSERT INTO feedback (order_id, user_id, rating, comments) VALUES (?, ?, ?, ?)
                 ON CONFLICT (order_id) DO UPDATE SET rating = EXCLUDED.rating, comments = EXCLUDED.comments'
            )->execute([$order['order_id'], $userId, $rating, $comment !== '' ? $comment : null]);

            flash('success', 'Thank you! Your feedback has been saved.');
            redirect('order_details.php?code=' . urlencode($code));
        } catch (Throwable $ex) {
            log_exception($ex);
            $error = 'Something went wrong. Please try again.';
        }
    }
}

$items = find_order_items($pdo, (int) $order['order_id']);

$pageTitle = 'Feedback';
$activeNav = 'orders';
require __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width:640px">
    <nav class="crumbs">
        <a href="<?= url('orders.php') ?>">My Orders</a><span>&rsaquo;</span>
        <a href="<?= url('order_details.php?code=' . urlencode($code)) ?>"><?= e($code) ?></a><span>&rsaquo;</span>
        Feedback
    </nav>

    <div class="panel">
        <h1 style="font-size:23px">How was your QuickCart pickup?</h1>
        <p class="muted">Order <span class="mono"><?= e($order['order_code']) ?></span>
            &middot; <?= count($items) ?> item(s) &middot; <?= money($order['total_amount']) ?></p>

        <?php if ($already): ?>
            <div class="flash flash-info" style="margin:12px 0"><span>You already rated this order. You can update it below.</span></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="flash flash-error" style="margin:12px 0"><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="post" action="<?= url('feedback.php?code=' . urlencode($code)) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label>Your rating</label>
                <!-- reversed order so CSS can colour the hovered star and the ones before it -->
                <div class="stars-input">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $rating === $i ? 'checked' : '' ?>>
                        <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><?= icon('star', 34) ?></label>
                    <?php endfor; ?>
                </div>
                <span class="hint" id="ratingText">Click a star to rate from 1 to 5.</span>
            </div>

            <div class="field">
                <label for="comments">Tell us more (optional)</label>
                <textarea id="comments" name="comments" maxlength="500"
                          placeholder="Was the parcel ready on time? Was everything correct?"><?= e($comment) ?></textarea>
                <span class="hint"><span id="cCount"><?= mb_strlen($comment) ?></span> / 500 characters</span>
            </div>

            <div class="buy-row">
                <button type="submit" class="btn btn-accent btn-lg"><?= icon('message', 17) ?> Submit feedback</button>
                <a class="btn btn-ghost" href="<?= url('order_details.php?code=' . urlencode($code)) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
$inlineScript = <<<'JS'
var words = ["", "Very poor", "Not good", "It was okay", "Good", "Excellent!"];
document.querySelectorAll('.stars-input input').forEach(function (r) {
    r.addEventListener('change', function () {
        document.getElementById('ratingText').textContent = words[parseInt(r.value, 10)];
    });
    if (r.checked) { document.getElementById('ratingText').textContent = words[parseInt(r.value, 10)]; }
});
var box = document.getElementById('comments');
if (box) {
    box.addEventListener('input', function () {
        document.getElementById('cCount').textContent = box.value.length;
    });
}
JS;
require __DIR__ . '/includes/footer.php';
?>
