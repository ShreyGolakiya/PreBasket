<?php
/*
 * admin/inventory.php  -  Stock management
 * The stock lives in products.stock_quantity, so the customer website
 * always shows exactly what is set here.
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$pdo = db();

if (is_post()) {
    require_csrf('admin/inventory.php');

    $action = post_str('action', 10);
    $id     = post_int('product_id');
    $amount = post_int('amount', -1);      // -1 = missing / not a whole number

    $st = $pdo->prepare('SELECT product_name, stock_quantity FROM products WHERE product_id = ?');
    $st->execute([$id]);
    $product = $st->fetch();

    if (!$product) {
        flash('error', 'Product not found.');
        redirect('admin/inventory.php');
    }

    if ($amount < 0) {
        flash('error', 'Please enter a whole number (0 or more).');
        redirect('admin/inventory.php');
    }

    $current = (int) $product['stock_quantity'];
    $new     = $current;

    if ($action === 'set') {
        $new = $amount;
    } elseif ($action === 'add') {
        $new = $current + $amount;
    } elseif ($action === 'sub') {
        $new = max(0, $current - $amount);              // never below zero
    } else {
        flash('error', 'Invalid action.');
        redirect('admin/inventory.php');
    }

    if ($new > 100000) {
        flash('error', 'That stock number is too high.');
        redirect('admin/inventory.php');
    }

    try {
        $pdo->beginTransaction();               // stock + carts change together or not at all
        $pdo->prepare('UPDATE products SET stock_quantity = ? WHERE product_id = ?')->execute([$new, $id]);

        if ($new === 0) {
            // nothing left: remove the product from every customer's cart
            $pdo->prepare('DELETE FROM cart WHERE product_id = ?')->execute([$id]);
        } else {
            // keep customer carts inside the new stock level
            $pdo->prepare('UPDATE cart SET quantity = ? WHERE product_id = ? AND quantity > ?')->execute([$new, $id, $new]);
        }
        $pdo->commit();

        flash('success', $product['product_name'] . ': stock changed from ' . $current . ' to ' . $new . '.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_exception($ex);
        flash('error', 'The stock could not be updated. Please try again.');
    }
    redirect('admin/inventory.php' . (get_str('view') !== '' ? '?view=' . urlencode(get_str('view')) : ''));
}

$view = get_str('view', 10);
$where = "p.status = 'active'";
if ($view === 'out')  { $where .= ' AND p.stock_quantity = 0'; }
if ($view === 'low')  { $where .= ' AND p.stock_quantity BETWEEN 1 AND 5'; }
if ($view === 'all')  { $where = '1 = 1'; }

$products = $pdo->query(
    "SELECT p.*, c.category_name FROM products p
       JOIN categories c ON c.category_id = p.category_id
      WHERE $where
      ORDER BY p.stock_quantity ASC, p.product_name"
)->fetchAll();

$counts = $pdo->query(
    "SELECT
        COUNT(*) FILTER (WHERE status = 'active') AS active,
        COUNT(*) FILTER (WHERE status = 'active' AND stock_quantity = 0) AS out_of_stock,
        COUNT(*) FILTER (WHERE status = 'active' AND stock_quantity BETWEEN 1 AND 5) AS low,
        COALESCE(SUM(stock_quantity), 0) AS units
       FROM products"
)->fetch();

admin_header('Inventory Management', 'inventory');
?>

<div class="kpi-grid">
    <div class="kpi"><span class="kpi-ic"><?= icon('box', 22) ?></span>
        <div><b><?= (int) $counts['active'] ?></b><span>Active products</span></div></div>
    <div class="kpi"><span class="kpi-ic red"><?= icon('alert', 22) ?></span>
        <div><b><?= (int) $counts['out_of_stock'] ?></b><span>Out of stock</span></div></div>
    <div class="kpi"><span class="kpi-ic orange"><?= icon('st-pending', 22) ?></span>
        <div><b><?= (int) $counts['low'] ?></b><span>Low stock (1&ndash;5)</span></div></div>
    <div class="kpi"><span class="kpi-ic blue"><?= icon('repeat', 22) ?></span>
        <div><b><?= (int) $counts['units'] ?></b><span>Units on the shelf</span></div></div>
</div>

<div class="chip-row">
    <a class="chip" href="<?= url('admin/inventory.php') ?>" style="<?= $view === '' ? 'border-color:var(--green);color:var(--green)' : '' ?>">Active products</a>
    <a class="chip" href="<?= url('admin/inventory.php?view=low') ?>" style="<?= $view === 'low' ? 'border-color:var(--green);color:var(--green)' : '' ?>">Low stock</a>
    <a class="chip" href="<?= url('admin/inventory.php?view=out') ?>" style="<?= $view === 'out' ? 'border-color:var(--green);color:var(--green)' : '' ?>">Out of stock</a>
    <a class="chip" href="<?= url('admin/inventory.php?view=all') ?>" style="<?= $view === 'all' ? 'border-color:var(--green);color:var(--green)' : '' ?>">Including removed</a>
</div>

<div class="panel">
    <div class="panel-head">
        <h2><?= count($products) ?> product(s)</h2>
        <span class="small muted">Stock drops automatically when a customer places an order.</span>
    </div>

    <?php if (!$products): ?>
        <p class="empty-note">Nothing to show here. That is good news!</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th colspan="2">Product</th>
                        <th>Category</th>
                        <th class="num">Price</th>
                        <th class="num">Stock</th>
                        <th>Customer sees</th>
                        <th>Quick update</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $s   = (int) $p['stock_quantity'];
                    $cls = $s === 0 ? 'stock-out' : ($s <= 5 ? 'stock-low' : 'stock-ok');
                ?>
                    <tr<?= $p['status'] === 'inactive' ? ' style="opacity:.6"' : '' ?>>
                        <td style="width:50px"><img class="thumb-sm" src="<?= product_image($p['image']) ?>" alt="" width="40" height="40"></td>
                        <td>
                            <strong><?= e($p['product_name']) ?></strong><br>
                            <span class="small muted"><?= e($p['unit_label']) ?></span>
                        </td>
                        <td><?= e($p['category_name']) ?></td>
                        <td class="num"><?= money($p['price']) ?></td>
                        <td class="num"><span class="stock-pill <?= $cls ?>"><?= $s ?></span></td>
                        <td>
                            <?php if ($p['status'] !== 'active'): ?>
                                <span class="badge tag-inactive">Hidden</span>
                            <?php elseif ($s === 0): ?>
                                <span class="badge st-rejected">Out of Stock</span>
                            <?php elseif ($s <= 5): ?>
                                <span class="badge st-pending">Only <?= $s ?> left</span>
                            <?php else: ?>
                                <span class="badge tag-active">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" class="inline-form" action="<?= url('admin/inventory.php' . ($view !== '' ? '?view=' . urlencode($view) : '')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
                                <input type="number" name="amount" value="<?= $s ?>" min="0" step="1"
                                       aria-label="New stock for <?= e($p['product_name']) ?>" required>
                                <button type="submit" name="action" value="set" class="btn btn-sm">Set</button>
                                <button type="submit" name="action" value="add" class="btn btn-ghost btn-sm" title="Add this many">+</button>
                                <button type="submit" name="action" value="sub" class="btn btn-ghost btn-sm" title="Remove this many">&minus;</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="small muted" style="margin:14px 0 0">
            <strong>Set</strong> replaces the stock with the number you typed.
            <strong>+</strong> and <strong>&minus;</strong> add or remove that many units. Stock can never go below 0.
        </p>
    <?php endif; ?>
</div>

<?php admin_footer(); ?>
