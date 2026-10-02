<?php
/*
 * product.php  -  One product in detail
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product_card.php';

$pdo = db();
$id  = get_int('id', 0);

$st = $pdo->prepare(
    'SELECT p.*, c.category_name FROM products p
       JOIN categories c ON c.category_id = p.category_id
      WHERE p.product_id = ?'
);
$st->execute([$id]);
$p = $st->fetch();

if (!$p || $p['status'] !== 'active') {
    $pageTitle = 'Product not found';
    $activeNav = 'products';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="empty-note" style="margin:40px 0">'
       . '<h2>Product not available</h2>'
       . '<p>This product is no longer sold at our store.</p>'
       . '<p><a class="btn" href="' . url('products.php') . '">Browse all products</a></p>'
       . '</div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stock = (int) $p['stock_quantity'];
$out   = $stock <= 0;
$off   = discount_percent($p['price'], $p['old_price']);

// a few more products from the same category
$rel = $pdo->prepare(
    "SELECT * FROM products
      WHERE category_id = ? AND product_id <> ? AND status = 'active'
      ORDER BY (stock_quantity = 0) ASC, is_featured DESC, product_id
      LIMIT 6"
);
$rel->execute([$p['category_id'], $p['product_id']]);
$related = $rel->fetchAll();

$pageTitle = $p['product_name'];
$activeNav = 'products';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <nav class="crumbs">
        <a href="<?= url('index.php') ?>">Home</a><span>&rsaquo;</span>
        <a href="<?= url('products.php') ?>">Products</a><span>&rsaquo;</span>
        <a href="<?= url('products.php?category=' . (int) $p['category_id']) ?>"><?= e($p['category_name']) ?></a><span>&rsaquo;</span>
        <?= e($p['product_name']) ?>
    </nav>

    <div class="detail-grid">
        <div class="detail-media">
            <?php if ($off > 0 && !$out) { echo discount_badge($off); } ?>
            <img src="<?= product_image($p['image']) ?>" alt="<?= e($p['product_name']) ?>" width="260" height="260">
            <?php if ($out): ?><p><span class="badge st-rejected">Out of Stock</span></p><?php endif; ?>
        </div>

        <div class="detail-box">
            <h1><?= e($p['product_name']) ?></h1>
            <p class="muted" style="margin-top:0">
                <?= e($p['unit_label']) ?> &middot;
                <a href="<?= url('products.php?category=' . (int) $p['category_id']) ?>"><?= e($p['category_name']) ?></a>
                &middot; <?= star_html($p['rating']) ?>
            </p>

            <div class="detail-price">
                <strong><?= money($p['price']) ?></strong>
                <?php if ($off > 0): ?>
                    <s><?= money($p['old_price']) ?></s>
                    <span class="save"><?= $off ?>% OFF</span>
                <?php endif; ?>
            </div>

            <?php if ($p['description']): ?>
                <p><?= e($p['description']) ?></p>
            <?php endif; ?>

            <div class="detail-facts">
                <div><span>Category</span><span><?= e($p['category_name']) ?></span></div>
                <div><span>Pack size</span><span><?= e($p['unit_label']) ?></span></div>
                <div><span>Rating</span><span><?= star_html($p['rating']) ?></span></div>
                <div>
                    <span>Availability</span>
                    <span>
                        <?php if ($out): ?>
                            <b style="color:var(--red)">Out of Stock</b>
                        <?php elseif ($stock <= 5): ?>
                            <b style="color:var(--amber)">Only <?= $stock ?> left</b>
                        <?php else: ?>
                            <b style="color:var(--green)">In Stock (<?= $stock ?>)</b>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <?php if ($out): ?>
                <p class="muted">This item is out of stock right now. Please check again later or pick something similar below.</p>
                <a class="btn btn-ghost" href="<?= url('products.php?category=' . (int) $p['category_id']) ?>">See similar products</a>
            <?php else: ?>
                <form class="add-form" method="post" action="<?= url('cart_action.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
                    <input type="hidden" name="redirect" value="cart.php">

                    <div class="buy-row">
                        <div class="qty-box">
                            <button type="button" data-step="-1" aria-label="Decrease quantity"><?= icon('minus', 16) ?></button>
                            <input type="number" name="quantity" value="1" min="1" max="<?= $stock ?>" aria-label="Quantity">
                            <button type="button" data-step="1" aria-label="Increase quantity"><?= icon('plus', 16) ?></button>
                        </div>
                        <button type="submit" class="btn btn-accent btn-lg"><?= icon('cart', 18) ?> Add to cart</button>
                        <a class="btn btn-ghost btn-lg" href="<?= url('cart.php') ?>">Go to cart</a>
                    </div>
                    <p class="stock-msg"></p>
                </form>
            <?php endif; ?>

            <p class="small muted" style="margin-top:14px">
                <?= icon('pickup', 15) ?> Pre-order now and collect at the counter. No delivery charge, no queue.
            </p>
        </div>
    </div>

    <?php if ($related): ?>
        <section class="section">
            <div class="section-head">
                <h2>More from <?= e($p['category_name']) ?></h2>
                <a class="link-more" href="<?= url('products.php?category=' . (int) $p['category_id']) ?>">View all</a>
            </div>
            <div class="product-grid">
                <?php foreach ($related as $r) { echo product_card($r); } ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
