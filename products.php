<?php
/*
 * products.php  -  Browse / search / filter all products
 *
 * PHP does the searching and filtering in MySQL (works without JavaScript).
 * JavaScript then adds instant filtering and sorting on top of that.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product_card.php';

$pdo = db();

$q        = get_str('q', 60);
$catId    = get_int('category', 0);
$sort     = get_str('sort', 20);
$page     = max(1, get_int('page', 1));
$perPage  = 24;

$allowedSorts = ['relevance', 'price_low', 'price_high', 'rating', 'name'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'relevance';
}

$categories = $pdo->query(
    "SELECT c.category_id, c.category_name, c.icon,
            (SELECT COUNT(*) FROM products p
              WHERE p.category_id = c.category_id AND p.status = 'active') AS product_count
       FROM categories c ORDER BY c.category_id"
)->fetchAll();

$catName = '';
foreach ($categories as $c) {
    if ((int) $c['category_id'] === $catId) {
        $catName = $c['category_name'];
    }
}
if ($catId && $catName === '') {
    $catId = 0;                       // a category id that does not exist
}

// ---- build the query with prepared statements (never string-glue user input)
$where  = ["p.status = 'active'"];
$params = [];

if ($q !== '') {
    $where[]  = '(p.product_name LIKE ? OR p.description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($catId > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $catId;
}
$whereSql = implode(' AND ', $where);

$orderSql = 'p.is_featured DESC, p.product_id';
if ($sort === 'price_low')   { $orderSql = 'p.price ASC'; }
if ($sort === 'price_high')  { $orderSql = 'p.price DESC'; }
if ($sort === 'rating')      { $orderSql = 'p.rating DESC, p.product_id'; }
if ($sort === 'name')        { $orderSql = 'p.product_name ASC'; }
// out-of-stock products always go to the end of the list
$orderSql = '(p.stock_quantity = 0) ASC, ' . $orderSql;

$countSt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();

$pages  = max(1, (int) ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$st = $pdo->prepare(
    "SELECT p.*, c.category_name FROM products p
       JOIN categories c ON c.category_id = p.category_id
      WHERE $whereSql
      ORDER BY $orderSql
      LIMIT $perPage OFFSET $offset"
);
$st->execute($params);
$products = $st->fetchAll();

/** Rebuilds the current URL with one value changed. */
function shop_url(array $changes = []): string
{
    $base = ['q' => get_str('q', 60), 'category' => get_int('category', 0), 'sort' => get_str('sort', 20), 'page' => get_int('page', 1)];
    $args = array_merge($base, $changes);
    $args = array_filter($args, function ($v) { return $v !== '' && $v !== 0 && $v !== null; });
    return url('products.php' . ($args ? '?' . http_build_query($args) : ''));
}

$pageTitle = $q !== '' ? 'Search: ' . $q : ($catName !== '' ? $catName : 'All products');
$activeNav = 'products';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <nav class="crumbs">
        <a href="<?= url('index.php') ?>">Home</a><span>&rsaquo;</span>
        <a href="<?= url('products.php') ?>">Products</a>
        <?php if ($catName !== ''): ?><span>&rsaquo;</span><?= e($catName) ?><?php endif; ?>
    </nav>

    <div class="shop-layout">
        <!-- ---------- left: categories ---------- -->
        <aside class="filter-card">
            <h3>Categories</h3>
            <div class="filter-list">
                <a class="<?= $catId === 0 ? 'active' : '' ?>" href="<?= shop_url(['category' => 0, 'page' => 1]) ?>">
                    All products <small><?= array_sum(array_column($categories, 'product_count')) ?></small>
                </a>
                <?php foreach ($categories as $c): ?>
                    <a class="<?= $catId === (int) $c['category_id'] ? 'active' : '' ?>"
                       href="<?= shop_url(['category' => (int) $c['category_id'], 'page' => 1]) ?>">
                        <?= e($c['category_name']) ?> <small><?= (int) $c['product_count'] ?></small>
                    </a>
                <?php endforeach; ?>
            </div>

            <h3>Sort by</h3>
            <div class="filter-list">
                <?php
                $sortNames = ['relevance' => 'Recommended', 'price_low' => 'Price: low to high',
                              'price_high' => 'Price: high to low', 'rating' => 'Top rated', 'name' => 'Name (A-Z)'];
                foreach ($sortNames as $key => $label): ?>
                    <a class="<?= $sort === $key ? 'active' : '' ?>" href="<?= shop_url(['sort' => $key, 'page' => 1]) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- ---------- right: the products ---------- -->
        <section>
            <div class="shop-bar">
                <div class="grow">
                    <h1><?= $q !== '' ? 'Results for &ldquo;' . e($q) . '&rdquo;' : ($catName !== '' ? e($catName) : 'All products') ?></h1>
                    <span class="small muted"><span id="resultCount"><?= count($products) ?></span> of <?= $total ?> products</span>
                </div>

                <input type="search" id="liveFilter" class="live-filter" placeholder="Filter these results&hellip;"
                       aria-label="Filter the products shown below">

                <label class="small muted" for="sortSelect">Sort</label>
                <select id="sortSelect" aria-label="Sort products">
                    <?php foreach ($sortNames as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($q !== '' || $catId > 0): ?>
                <div class="chip-row">
                    <?php if ($q !== ''): ?>
                        <span class="chip">Search: <?= e($q) ?> <a href="<?= shop_url(['q' => '', 'page' => 1]) ?>" aria-label="Clear search">&times;</a></span>
                    <?php endif; ?>
                    <?php if ($catId > 0): ?>
                        <span class="chip">Category: <?= e($catName) ?> <a href="<?= shop_url(['category' => 0, 'page' => 1]) ?>" aria-label="Clear category">&times;</a></span>
                    <?php endif; ?>
                    <a class="chip" href="<?= url('products.php') ?>">Clear all</a>
                </div>
            <?php endif; ?>

            <?php if (!$products): ?>
                <div class="empty-note">
                    <p><strong>No products found.</strong></p>
                    <p>Try a different word, or <a href="<?= url('products.php') ?>">browse all products</a>.</p>
                </div>
            <?php else: ?>
                <div class="product-grid" id="productGrid">
                    <?php foreach ($products as $p) { echo product_card($p); } ?>
                </div>
                <div class="empty-note is-hidden" id="noResults" style="margin-top:16px">
                    Nothing on this page matches that word. Clear the filter box to see everything again.
                </div>
            <?php endif; ?>

            <?php if ($pages > 1): ?>
                <div class="pager">
                    <?php if ($page > 1): ?>
                        <a href="<?= shop_url(['page' => $page - 1]) ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= shop_url(['page' => $i]) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $pages): ?>
                        <a href="<?= shop_url(['page' => $page + 1]) ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
