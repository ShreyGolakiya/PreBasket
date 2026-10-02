<?php
/*
 * admin/product_edit.php  -  Change an existing product
 *
 * Changing the price here does NOT change old orders, because
 * order_items keeps its own copy of the price (price_at_purchase).
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/product_form.php';
require_once __DIR__ . '/../includes/image_upload.php';
require_admin();

$pdo = db();
$id  = get_int('id', 0);

$st = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
$st->execute([$id]);
$row = $st->fetch();

if (!$row) {
    flash('error', 'Product not found.');
    redirect('admin/products.php');
}

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

// current values shown in the form
$p = [
    'product_name' => $row['product_name'],
    'category_id'  => (int) $row['category_id'],
    'price'        => $row['price'],
    'old_price'    => $row['old_price'],
    'unit_label'   => $row['unit_label'],
    'stock'        => (int) $row['stock_quantity'],
    'description'  => (string) $row['description'],
    'rating'       => $row['rating'],
    'is_featured'  => (int) $row['is_featured'],
    'status'       => $row['status'],
];
$errors = [];

if (is_post()) {
    require_csrf('admin/product_edit.php?id=' . $id);

    [$v, $errors] = product_input_validate($pdo);
    $p = $v;

    if (!$errors) {
        try {
            $image = $row['image'];
            $new   = save_product_image($_FILES['image'] ?? [], $v['product_name']);
            if ($new !== null) {
                delete_product_image($row['image']);     // tidy up the old uploaded file
                $image = $new;
            }

            $pdo->beginTransaction();               // product + carts change together
            $upd = $pdo->prepare(
                'UPDATE products
                    SET product_name = ?, category_id = ?, price = ?, old_price = ?, unit_label = ?,
                        stock_quantity = ?, description = ?, image = ?, rating = ?, is_featured = ?, status = ?
                  WHERE product_id = ?'
            );
            $upd->execute([
                $v['product_name'], $v['category_id'], $v['price'], $v['old_price'], $v['unit_label'],
                $v['stock'], $v['description'] !== '' ? $v['description'] : null,
                $image, $v['rating'], $v['is_featured'], $v['status'], $id,
            ]);

            if ($v['stock'] === 0 || $v['status'] === 'inactive') {
                // out of stock or removed: take it out of every customer's cart
                $pdo->prepare('DELETE FROM cart WHERE product_id = ?')->execute([$id]);
            } else {
                // if the stock went down below what people have in their carts, trim those carts
                $pdo->prepare('UPDATE cart SET quantity = ? WHERE product_id = ? AND quantity > ?')
                    ->execute([$v['stock'], $id, $v['stock']]);
            }
            $pdo->commit();

            flash('success', 'Product updated successfully. The customer website now shows the new details.');
            redirect('admin/products.php');
        } catch (AppException $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $errors['image'] = $ex->getMessage();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            log_exception($ex);
            $errors['product_name'] = 'Something went wrong while saving. Please try again.';
        }
    }
}

// how many times has it been sold? (shows why we never really delete it)
$sold = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE product_id = ?');
$sold->execute([$id]);
$soldQty = (int) $sold->fetchColumn();

admin_header('Edit Product', 'products');
?>

<p><a class="btn btn-ghost btn-sm" href="<?= url('admin/products.php') ?>">&larr; Back to products</a></p>

<?php if (!empty($errors['image'])): ?>
    <div class="flash flash-error" style="margin-bottom:14px"><span><?= e($errors['image']) ?></span></div>
<?php endif; ?>

<div class="admin-grid">
    <div class="panel">
        <h2>Edit &ldquo;<?= e($row['product_name']) ?>&rdquo;</h2>
        <p class="small muted" style="margin-top:-6px">Product ID <?= $id ?> &middot; added <?= date('d M Y', strtotime($row['created_at'])) ?></p>

        <form method="post" action="<?= url('admin/product_edit.php?id=' . $id) ?>" enctype="multipart/form-data" data-validate novalidate>
            <?= csrf_field() ?>
            <?php product_form_fields($p, $errors, $categories); ?>

            <div class="buy-row">
                <button type="submit" class="btn btn-accent btn-lg"><?= icon('edit', 17) ?> Save changes</button>
                <a class="btn btn-ghost" href="<?= url('admin/products.php') ?>">Cancel</a>
            </div>
        </form>
    </div>

    <div>
        <div class="panel center">
            <h2>Current picture</h2>
            <img src="<?= product_image($row['image']) ?>" alt="<?= e($row['product_name']) ?>"
                 width="170" height="170" style="margin:0 auto;object-fit:contain">
            <p class="small muted"><?= e($row['image']) ?></p>
            <a class="btn btn-ghost btn-sm" href="<?= url('product.php?id=' . $id) ?>" target="_blank" rel="noopener">
                <?= icon('store', 15) ?> View on the website
            </a>
        </div>

        <div class="panel">
            <h2>Product history</h2>
            <div class="mini-line"><span>Times sold</span><span><strong><?= $soldQty ?></strong> unit(s)</span></div>
            <div class="mini-line"><span>Last updated</span><span><?= date('d M Y, g:i A', strtotime($row['updated_at'])) ?></span></div>
            <div class="mini-line"><span>Current status</span>
                <span><span class="badge <?= $row['status'] === 'active' ? 'tag-active' : 'tag-inactive' ?>"><?= e($row['status']) ?></span></span>
            </div>

            <?php if ($soldQty > 0): ?>
                <div class="free-note" style="margin-top:12px;text-align:left">
                    <?= icon('shield', 16) ?>
                    <span>This product appears in past orders. Changing the price here will
                          <strong>not</strong> change what those customers were charged.</span>
                </div>
            <?php endif; ?>

            <?php if ($row['status'] === 'active'): ?>
                <form method="post" action="<?= url('admin/product_delete.php') ?>" style="margin-top:12px"
                      data-confirm="Remove this product from the customer website?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= $id ?>">
                    <input type="hidden" name="to" value="inactive">
                    <button type="submit" class="btn btn-danger btn-block"><?= icon('trash', 16) ?> Remove product</button>
                </form>
                <p class="small muted center" style="margin:8px 0 0">This only hides it. Nothing is deleted.</p>
            <?php else: ?>
                <form method="post" action="<?= url('admin/product_delete.php') ?>" style="margin-top:12px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= $id ?>">
                    <input type="hidden" name="to" value="active">
                    <button type="submit" class="btn btn-block">Restore this product</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php admin_footer(); ?>
