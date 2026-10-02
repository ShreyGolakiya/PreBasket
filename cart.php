<?php
/*
 * cart.php  -  The customer's shopping cart
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId   = current_user_id();
$items    = cart_items($userId);
$problems = cart_problems($items);
$total    = cart_total($items);
$count    = 0;
foreach ($items as $it) { $count += (int) $it['quantity']; }

$pageTitle = 'My Cart';
$activeNav = 'cart';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-head">
        <h1>My Cart</h1>
        <p><?= $count ?> item<?= $count === 1 ? '' : 's' ?> ready to be pre-ordered.</p>
    </div>

    <?php if (!$items): ?>
        <div class="empty-note" style="padding:44px">
            <div style="color:var(--green);margin-bottom:10px"><?= icon('basket', 54) ?></div>
            <h2>Your cart is empty</h2>
            <p>Add some products and we will keep them packed and ready for you.</p>
            <a class="btn btn-accent btn-lg" href="<?= url('products.php') ?>">Start shopping</a>
        </div>
    <?php else: ?>

        <?php if ($problems): ?>
            <div class="flash flash-warning" style="margin-bottom:16px">
                <span>Some items need your attention before checkout. See the notes below.</span>
                <form method="post" action="<?= url('cart_action.php') ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="fix">
                    <input type="hidden" name="redirect" value="cart.php">
                    <button type="submit" class="btn btn-sm">Fix my cart automatically</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="two-col">
            <!-- ---------- the items ---------- -->
            <div>
                <div class="panel">
                    <div class="panel-head">
                        <h2>Items in your basket</h2>
                        <form method="post" action="<?= url('cart_action.php') ?>"
                              data-confirm="Remove every item from your cart?" style="margin:0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="clear">
                            <input type="hidden" name="redirect" value="cart.php">
                            <button type="submit" class="btn btn-ghost btn-sm"><?= icon('trash', 15) ?> Empty cart</button>
                        </form>
                    </div>

                    <?php foreach ($items as $it):
                        $stock = (int) $it['stock_quantity'];
                        $qty   = (int) $it['quantity'];
                        $line  = round((float) $it['price'] * $qty, 2);
                        $bad   = $problems[$it['product_id']] ?? '';
                    ?>
                        <div class="cart-line">
                            <a href="<?= url('product.php?id=' . (int) $it['product_id']) ?>">
                                <img src="<?= product_image($it['image']) ?>" alt="<?= e($it['product_name']) ?>" width="74" height="74">
                            </a>

                            <div class="cart-info">
                                <a href="<?= url('product.php?id=' . (int) $it['product_id']) ?>"><strong><?= e($it['product_name']) ?></strong></a>
                                <span class="unit"><?= e($it['unit_label']) ?> &middot; <?= money($it['price']) ?> each</span>
                            </div>

                            <div class="cart-right">
                                <div class="cart-actions">
                                    <?php if ($it['status'] === 'active' && $stock > 0): ?>
                                        <form class="qty-form" method="post" action="<?= url('cart_action.php') ?>" style="margin:0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="set">
                                            <input type="hidden" name="product_id" value="<?= (int) $it['product_id'] ?>">
                                            <input type="hidden" name="redirect" value="cart.php">
                                            <div class="qty-box">
                                                <button type="button" data-step="-1" aria-label="Decrease"><?= icon('minus', 15) ?></button>
                                                <input type="number" name="quantity" value="<?= $qty ?>" min="1" max="<?= $stock ?>"
                                                       aria-label="Quantity of <?= e($it['product_name']) ?>">
                                                <button type="button" data-step="1" aria-label="Increase"><?= icon('plus', 15) ?></button>
                                            </div>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" action="<?= url('cart_action.php') ?>" style="margin:0"
                                          data-confirm="Remove <?= e($it['product_name']) ?> from your cart?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?= (int) $it['product_id'] ?>">
                                        <input type="hidden" name="redirect" value="cart.php">
                                        <button type="submit" class="icon-btn" aria-label="Remove <?= e($it['product_name']) ?>">
                                            <?= icon('trash', 16) ?>
                                        </button>
                                    </form>
                                </div>
                                <span class="line-total"><?= money($line) ?></span>
                            </div>

                            <?php if ($bad): ?>
                                <p class="line-warn"><?= icon('alert', 14) ?> <?= e($bad) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p style="margin-top:14px">
                    <a class="btn btn-ghost" href="<?= url('products.php') ?>"><?= icon('arrow', 16) ?> Continue shopping</a>
                </p>
            </div>

            <!-- ---------- the summary ---------- -->
            <aside>
                <div class="panel">
                    <h2>Order summary</h2>

                    <div class="summary-row"><span>Items (<?= $count ?>)</span><span><?= money($total) ?></span></div>
                    <div class="summary-row"><span>Packing charge</span><span>Free</span></div>
                    <div class="summary-row"><span>Delivery</span><span>Not needed &mdash; you collect</span></div>
                    <div class="summary-row total"><span>Total</span><span><?= money($total) ?></span></div>

                    <div class="free-note">
                        <?= icon('pickup', 17) ?>
                        <span>You save the delivery charge and the billing queue. Just walk in and collect.</span>
                    </div>

                    <?php if ($problems): ?>
                        <button class="btn btn-lg btn-block is-disabled" disabled>Fix the items above first</button>
                    <?php else: ?>
                        <a class="btn btn-accent btn-lg btn-block" href="<?= url('checkout.php') ?>">
                            Proceed to checkout <?= icon('arrow', 17) ?>
                        </a>
                    <?php endif; ?>

                    <p class="small muted center" style="margin:12px 0 0">
                        <?= icon('shield', 14) ?> Stock is checked again when you place the order.
                    </p>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
