<?php
/*
 * admin/product_delete.php  -  SOFT DELETE
 * ---------------------------------------------------------------
 * We never run "DELETE FROM products".
 * A product may already be used in old orders, and deleting the row
 * would break order_items (and the foreign key would refuse anyway).
 *
 * Instead we set status = 'inactive':
 *   - the product disappears from the customer website
 *   - old orders keep working and still show the correct product
 *   - the admin can restore it any time
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

if (!is_post()) {
    redirect('admin/products.php');
}
require_csrf('admin/products.php');

$pdo = db();
$id  = post_int('product_id');
$to  = post_str('to', 10);

if (!in_array($to, ['active', 'inactive'], true)) {
    flash('error', 'Invalid action.');
    redirect('admin/products.php');
}

$st = $pdo->prepare('SELECT product_name, status FROM products WHERE product_id = ?');
$st->execute([$id]);
$product = $st->fetch();

if (!$product) {
    flash('error', 'Product not found.');
    redirect('admin/products.php');
}

if ($product['status'] === $to) {
    flash('info', 'That product is already ' . ($to === 'active' ? 'active' : 'removed') . '.');
    redirect('admin/products.php');
}

try {
    $pdo->prepare('UPDATE products SET status = ? WHERE product_id = ?')->execute([$to, $id]);

    if ($to === 'inactive') {
        // take it out of any customer's cart so nobody can check out with it
        $pdo->prepare('DELETE FROM cart WHERE product_id = ?')->execute([$id]);
        flash('success', $product['product_name'] . ' has been removed from the website. '
            . 'Old orders are not affected and you can restore it any time.');
    } else {
        flash('success', $product['product_name'] . ' is active again and visible to customers.');
    }
} catch (Throwable $ex) {
    log_exception($ex);
    flash('error', 'Something went wrong. Please try again.');
}

redirect('admin/products.php');
