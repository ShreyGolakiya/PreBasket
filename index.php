<?php
/*
 * index.php  -  Home page
 * Shows: hero, categories, featured products, how PreBasket works.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product_card.php';

$pdo = db();

$categories = $pdo->query(
    "SELECT c.category_id, c.category_name, c.icon,
            (SELECT COUNT(*) FROM products p
              WHERE p.category_id = c.category_id AND p.status = 'active') AS product_count
       FROM categories c
      ORDER BY c.category_id"
)->fetchAll();

$featured = $pdo->query(
    "SELECT * FROM products
      WHERE status = 'active' AND is_featured = 1
      ORDER BY product_id
      LIMIT 8"
)->fetchAll();

$pageTitle = 'Shop Before You Shop';
$activeNav = '';
require __DIR__ . '/includes/header.php';

// A little decorative "QR" for the hero picture (not a real code, just artwork)
$fakeQr = '';
for ($y = 0; $y < 9; $y++) {
    for ($x = 0; $x < 9; $x++) {
        $inFinder = ($x < 3 && $y < 3) || ($x > 5 && $y < 3) || ($x < 3 && $y > 5);
        if (!$inFinder && (($x * 7 + $y * 5 + $x * $y) % 5) < 2) {
            $fakeQr .= '<rect x="' . ($x * 7) . '" y="' . ($y * 7) . '" width="6" height="6" rx="1"/>';
        }
    }
}
foreach ([[0, 0], [6, 0], [0, 6]] as $f) {
    $fx = $f[0] * 7;
    $fy = $f[1] * 7;
    $fakeQr .= '<rect x="' . ($fx + 1) . '" y="' . ($fy + 1) . '" width="19" height="19" fill="none" stroke="#17231d" stroke-width="2"/>'
             . '<rect x="' . ($fx + 7) . '" y="' . ($fy + 7) . '" width="7" height="7"/>';
}
?>

<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <h1>Shop Before<br>You Shop.</h1>
            <p class="hero-lead">
                Pick your groceries on PreBasket, we pack them while you get ready, and you simply
                walk in and collect. No aisles to search, no billing queue to wait in.
            </p>
            <form class="hero-search" action="<?= url('products.php') ?>" method="get" role="search">
                <?= icon('search', 20) ?>
                <input type="search" name="q" placeholder="What do you need today?" aria-label="Search products" maxlength="60">
                <button type="submit" class="btn btn-accent">Search</button>
            </form>
            <div class="hero-quick">
                <span>Try:</span>
                <a href="<?= url('products.php?q=milk') ?>">milk</a>
                <a href="<?= url('products.php?q=chips') ?>">chips</a>
                <a href="<?= url('products.php?q=rice') ?>">rice</a>
                <a href="<?= url('products.php?q=apple') ?>">apple</a>
            </div>
        </div>

        <!-- SVG artwork: a pickup ticket and a packed bag -->
        <div class="hero-art" aria-hidden="true">
            <svg viewBox="0 0 400 320" role="img" aria-label="Pickup ticket with QR code and packed bag">
                <!-- packed paper bag -->
                <path d="M40 130h130l-10 170H50z" fill="#d9b382"/>
                <path d="M40 130h130l-4 30H44z" fill="#c79d66"/>
                <path d="M80 130V108a25 25 0 0150 0v22" fill="none" stroke="#a97f4b" stroke-width="5"/>
                <!-- things sticking out of the bag -->
                <rect x="60" y="62" width="26" height="72" rx="6" fill="#3a7bd5"/>
                <rect x="66" y="50" width="14" height="16" rx="3" fill="#1c4a94"/>
                <ellipse cx="112" cy="96" rx="14" ry="34" fill="#2f9e44" transform="rotate(14 112 96)"/>
                <ellipse cx="138" cy="102" rx="12" ry="30" fill="#4cb85f" transform="rotate(34 138 102)"/>
                <rect x="94" y="132" width="52" height="60" rx="8" fill="#fff" opacity=".9"/>
                <path d="M104 160l10 10 20-22" stroke="#0f6b4a" stroke-width="7" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <!-- ticket -->
                <g transform="rotate(4 270 170)">
                    <path d="M170 60h200a10 10 0 0110 10v240a10 10 0 01-10 10H170a10 10 0 01-10-10V70a10 10 0 0110-10z" fill="#fff"/>
                    <circle cx="160" cy="205" r="11" fill="#0f6b4a"/>
                    <circle cx="380" cy="205" r="11" fill="#0f6b4a"/>
                    <line x1="176" x2="364" y1="205" y2="205" stroke="#c9d6cf" stroke-width="2" stroke-dasharray="6 6"/>
                    <circle cx="192" cy="96" r="14" fill="#e3f4ea"/>
                    <path d="M185 96l5 5 9-10" stroke="#0f6b4a" stroke-width="3.500" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                    <text x="214" y="93" font-family="Trebuchet MS, Segoe UI, sans-serif" font-weight="700" font-size="17" fill="#17231d">Order ready</text>
                    <text x="214" y="112" font-family="Segoe UI, Arial, sans-serif" font-size="12" fill="#5f6f66">Collect at counter 2</text>
                    <text x="176" y="150" font-family="Segoe UI, Arial, sans-serif" font-size="11" fill="#5f6f66">Order ID</text>
                    <text x="176" y="172" font-family="Consolas, Menlo, monospace" font-weight="700" font-size="19" fill="#0f6b4a">PB202609200001</text>
                    <text x="176" y="192" font-family="Segoe UI, Arial, sans-serif" font-size="11" fill="#5f6f66">Show this at the store</text>
                    <g fill="#17231d" transform="translate(238 222)"><?= $fakeQr ?></g>
                </g>
            </svg>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>Shop by category</h2>
            <a class="link-more" href="<?= url('products.php') ?>">See all products</a>
        </div>
        <div class="cat-grid">
            <?php foreach ($categories as $c): ?>
                <a class="cat-tile" href="<?= url('products.php?category=' . (int) $c['category_id']) ?>">
                    <span class="cat-icon"><?= icon($c['icon'], 30) ?></span>
                    <span class="cat-name"><?= e($c['category_name']) ?></span>
                    <span class="cat-count"><?= (int) $c['product_count'] ?> items</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-tint">
    <div class="container">
        <div class="section-head">
            <h2>Featured products</h2>
            <a class="link-more" href="<?= url('products.php') ?>">View all</a>
        </div>
        <?php if (!$featured): ?>
            <p class="empty-note">No featured products yet.</p>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($featured as $p) { echo product_card($p); } ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section" id="how">
    <div class="container">
        <div class="section-head">
            <h2>How PreBasket works</h2>
        </div>
        <ol class="steps">
            <li>
                <span class="step-icon"><?= icon('cart', 30) ?></span>
                <h3>Choose your products</h3>
                <p>Search or browse the supermarket shelves and add items to your cart.</p>
            </li>
            <li>
                <span class="step-icon"><?= icon('shield', 30) ?></span>
                <h3>Pay online or at the store</h3>
                <p>Use the demo online payment, or choose Cash at Store and pay when you collect.</p>
            </li>
            <li>
                <span class="step-icon"><?= icon('box', 30) ?></span>
                <h3>We pack your order</h3>
                <p>Store staff accept the order, prepare it and mark it Ready for Collection.</p>
            </li>
            <li>
                <span class="step-icon"><?= icon('qr', 30) ?></span>
                <h3>Show your QR and collect</h3>
                <p>Show your Order ID or QR code at the counter, take your parcel and go.</p>
            </li>
        </ol>
    </div>
</section>

<?php if (!is_logged_in()): ?>
<section class="section">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2>Ready to skip the queue?</h2>
                <p>Create a free account and place your first pre-order in a minute.</p>
            </div>
            <a class="btn btn-accent btn-lg" href="<?= url('register.php') ?>">Create account</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
