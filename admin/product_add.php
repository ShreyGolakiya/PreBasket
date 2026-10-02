<?php
/*
 * admin/product_add.php  -  Add a brand new product
 * As soon as this is saved the product appears on the customer website.
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/product_form.php';
require_once __DIR__ . '/../includes/image_upload.php';
require_admin();

$pdo        = db();
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

// starting values for a new product
$p = [
    'product_name' => '', 'category_id' => get_int('category', 0), 'price' => '', 'old_price' => '',
    'unit_label' => '', 'stock' => '0', 'description' => '', 'rating' => '4.0',
    'is_featured' => 0, 'status' => 'active',
];
$errors = [];

if (is_post()) {
    require_csrf('admin/product_add.php');

    [$v, $errors] = product_input_validate($pdo);
    $p = $v;

    if (!$errors) {
        try {
            $image = save_product_image($_FILES['image'] ?? [], $v['product_name']) ?? 'placeholder.svg';

            $st = $pdo->prepare(
                'INSERT INTO products
                    (product_name, category_id, price, old_price, unit_label, stock_quantity,
                     description, image, rating, is_featured, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $v['product_name'], $v['category_id'], $v['price'], $v['old_price'], $v['unit_label'],
                $v['stock'], $v['description'] !== '' ? $v['description'] : null,
                $image, $v['rating'], $v['is_featured'], $v['status'],
            ]);

            flash('success', 'Product added successfully. It is now live on the customer website.');
            redirect('admin/products.php');
        } catch (AppException $ex) {
            $errors['image'] = $ex->getMessage();
        } catch (Throwable $ex) {
            log_exception($ex);
            $errors['product_name'] = 'Something went wrong while saving. Please try again.';
        }
    }
}

admin_header('Add Product', 'products');
?>

<p><a class="btn btn-ghost btn-sm" href="<?= url('admin/products.php') ?>">&larr; Back to products</a></p>

<?php if (!empty($errors['image'])): ?>
    <div class="flash flash-error" style="margin-bottom:14px"><span><?= e($errors['image']) ?></span></div>
<?php endif; ?>

<div class="panel" style="max-width:780px">
    <h2>New product</h2>
    <p class="small muted" style="margin-top:-6px">Fields marked * are required.</p>

    <form method="post" action="<?= url('admin/product_add.php') ?>" enctype="multipart/form-data" data-validate novalidate>
        <?= csrf_field() ?>
        <?php product_form_fields($p, $errors, $categories); ?>

        <div class="buy-row">
            <button type="submit" class="btn btn-accent btn-lg"><?= icon('plus', 17) ?> Save product</button>
            <a class="btn btn-ghost" href="<?= url('admin/products.php') ?>">Cancel</a>
        </div>
    </form>
</div>

<?php admin_footer(); ?>
