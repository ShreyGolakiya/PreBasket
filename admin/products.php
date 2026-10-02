<?php
/*
 * admin/products.php  -  List of every product with quick actions
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$pdo = db();

$q      = get_str('q', 60);
$catId  = get_int('category', 0);
$status = get_str('status', 10);
if (!in_array($status, ['active', 'inactive'], true)) {
    $status = '';
}

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

$where  = ['1 = 1'];
$params = [];
if ($q !== '')      { $where[] = 'p.product_name LIKE ?'; $params[] = '%' . $q . '%'; }
if ($catId > 0)     { $where[] = 'p.category_id = ?';     $params[] = $catId; }
if ($status !== '') { $where[] = 'p.status = ?';          $params[] = $status; }
$whereSql = implode(' AND ', $where);

$st = $pdo->prepare(
    "SELECT p.*, c.category_name,
            (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.product_id = p.product_id) AS sold
       FROM products p JOIN categories c ON c.category_id = p.category_id
      WHERE $whereSql
      ORDER BY p.status ASC, p.product_id DESC"
);
$st->execute($params);
$products = $st->fetchAll();

admin_header('Product Management', 'products');
?>

<div class="toolbar">
    <form method="get" action="<?= url('admin/products.php') ?>" class="toolbar" style="margin:0;flex:1">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search product name&hellip;" style="min-width:210px">

        <select name="category">
            <option value="0">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['category_id'] ?>" <?= $catId === (int) $c['category_id'] ? 'selected' : '' ?>>
                    <?= e($c['category_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status">
            <option value="">All statuses</option>
            <option value="active"   <?= $status === 'active' ? 'selected' : '' ?>>Active only</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Removed (inactive)</option>
        </select>

        <button type="submit" class="btn btn-ghost btn-sm"><?= icon('search', 15) ?> Filter</button>
        <?php if ($q !== '' || $catId || $status !== ''): ?>
            <a class="btn btn-ghost btn-sm" href="<?= url('admin/products.php') ?>">Clear</a>
        <?php endif; ?>
    </form>

    <a class="btn btn-accent" href="<?= url('admin/product_add.php') ?>"><?= icon('plus', 16) ?> Add new product</a>
</div>

<div class="panel">
    <div class="panel-head">
        <h2><?= count($products) ?> product(s)</h2>
        <span class="small muted">Removing a product only hides it from customers. Old orders stay correct.</span>
    </div>

    <?php if (!$products): ?>
        <p class="empty-note">No products match your search.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th colspan="2">Product</th>
                        <th>Category</th>
                        <th class="num">Price</th>
                        <th class="num">Stock</th>
                        <th class="num">Sold</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $s = (int) $p['stock_quantity'];
                    $stockCls = $s === 0 ? 'stock-out' : ($s <= 5 ? 'stock-low' : 'stock-ok');
                ?>
                    <tr<?= $p['status'] === 'inactive' ? ' style="opacity:.62"' : '' ?>>
                        <td style="width:54px">
                            <img class="thumb-sm" src="<?= product_image($p['image']) ?>" alt="" width="40" height="40">
                        </td>
                        <td>
                            <strong><?= e($p['product_name']) ?></strong><br>
                            <span class="small muted"><?= e($p['unit_label']) ?>
                                <?= $p['is_featured'] ? ' &middot; <span class="badge tag-active">Featured</span>' : '' ?>
                            </span>
                        </td>
                        <td><?= e($p['category_name']) ?></td>
                        <td class="num">
                            <strong><?= money($p['price']) ?></strong>
                            <?php if ($p['old_price']): ?><br><s class="small muted"><?= money($p['old_price']) ?></s><?php endif; ?>
                        </td>
                        <td class="num"><span class="stock-pill <?= $stockCls ?>"><?= $s ?></span></td>
                        <td class="num"><?= (int) $p['sold'] ?></td>
                        <td>
                            <span class="badge <?= $p['status'] === 'active' ? 'tag-active' : 'tag-inactive' ?>">
                                <?= $p['status'] === 'active' ? 'Active' : 'Removed' ?>
                            </span>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a class="btn btn-ghost btn-sm" href="<?= url('admin/product_edit.php?id=' . (int) $p['product_id']) ?>">
                                    <?= icon('edit', 14) ?> Edit
                                </a>

                                <form method="post" action="<?= url('admin/product_delete.php') ?>" style="margin:0"
                                      data-confirm="<?= $p['status'] === 'active'
                                          ? 'Remove &quot;' . e($p['product_name']) . '&quot; from the customer website? Old orders will not change.'
                                          : 'Put &quot;' . e($p['product_name']) . '&quot; back on the website?' ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
                                    <input type="hidden" name="to" value="<?= $p['status'] === 'active' ? 'inactive' : 'active' ?>">
                                    <button type="submit" class="btn btn-sm <?= $p['status'] === 'active' ? 'btn-ghost' : '' ?>">
                                        <?= $p['status'] === 'active' ? 'Remove' : 'Restore' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php admin_footer(); ?>
