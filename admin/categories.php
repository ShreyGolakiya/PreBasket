<?php
/*
 * admin/categories.php  -  Add, rename and remove categories
 */
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$pdo    = db();
$errors = [];
$editId = get_int('edit', 0);

if (is_post()) {
    require_csrf('admin/categories.php');

    $action = post_str('action', 10);
    $name   = post_str('category_name', 80);
    $iconId = post_str('icon', 30);
    $id     = post_int('category_id', 0);

    if (!in_array($iconId, category_icon_keys(), true)) {
        $iconId = 'grocery';
    }

    /* ---------------- delete ---------------- */
    if ($action === 'delete') {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $chk->execute([$id]);
        $used = (int) $chk->fetchColumn();

        if ($used > 0) {
            // the foreign key would block this anyway - we explain it nicely instead
            flash('error', 'This category still has ' . $used . ' product(s). Move them to another category first.');
        } else {
            try {
                $pdo->prepare('DELETE FROM categories WHERE category_id = ?')->execute([$id]);
                flash('success', 'Category deleted.');
            } catch (Throwable $ex) {
                log_exception($ex);
                flash('error', 'That category could not be deleted.');
            }
        }
        redirect('admin/categories.php');
    }

    /* ---------------- add / rename ---------------- */
    if ($name === '' || mb_strlen($name) < 2) {
        $errors['category_name'] = 'Please enter a category name (at least 2 characters).';
    } else {
        $dupSql = 'SELECT category_id FROM categories WHERE category_name = ?';
        $params = [$name];
        if ($action === 'update') {
            $dupSql .= ' AND category_id <> ?';
            $params[] = $id;
        }
        $dup = $pdo->prepare($dupSql);
        $dup->execute($params);
        if ($dup->fetch()) {
            $errors['category_name'] = 'A category with that name already exists.';
        }
    }

    if (!$errors) {
        try {
            if ($action === 'update') {
                $pdo->prepare('UPDATE categories SET category_name = ?, icon = ? WHERE category_id = ?')
                    ->execute([$name, $iconId, $id]);
                flash('success', 'Category updated successfully.');
            } else {
                $pdo->prepare('INSERT INTO categories (category_name, icon) VALUES (?, ?)')
                    ->execute([$name, $iconId]);
                flash('success', 'Category added successfully.');
            }
            redirect('admin/categories.php');
        } catch (Throwable $ex) {
            log_exception($ex);
            $errors['category_name'] = 'Something went wrong. Please try again.';
        }
    }
}

$categories = $pdo->query(
    "SELECT c.*,
            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.category_id) AS total,
            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.category_id AND p.status = 'active') AS active
       FROM categories c ORDER BY c.category_name"
)->fetchAll();

// values for the form on the right
$editRow = ['category_id' => 0, 'category_name' => '', 'icon' => 'grocery'];
if ($editId) {
    foreach ($categories as $c) {
        if ((int) $c['category_id'] === $editId) {
            $editRow = $c;
        }
    }
}

admin_header('Category Management', 'categories');
?>

<div class="admin-grid">
    <!-- ---------------- list ---------------- -->
    <div class="panel">
        <div class="panel-head">
            <h2><?= count($categories) ?> categories</h2>
            <span class="small muted">A category can only be deleted when it has no products.</span>
        </div>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th colspan="2">Category</th><th class="num">Products</th><th class="num">Active</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td style="width:50px">
                            <span class="cat-icon" style="width:38px;height:38px"><?= icon($c['icon'], 20) ?></span>
                        </td>
                        <td>
                            <strong><?= e($c['category_name']) ?></strong><br>
                            <span class="small muted">icon: <?= e($c['icon']) ?></span>
                        </td>
                        <td class="num"><?= (int) $c['total'] ?></td>
                        <td class="num"><?= (int) $c['active'] ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="btn btn-ghost btn-sm" href="<?= url('admin/categories.php?edit=' . (int) $c['category_id']) ?>">
                                    <?= icon('edit', 14) ?> Edit
                                </a>
                                <form method="post" style="margin:0"
                                      data-confirm="Delete the category &quot;<?= e($c['category_name']) ?>&quot;?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= (int) $c['category_id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm"
                                        <?= (int) $c['total'] > 0 ? 'disabled title="Move its products first"' : '' ?>>
                                        <?= icon('trash', 14) ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ---------------- add / edit form ---------------- -->
    <div class="panel">
        <h2><?= $editId ? 'Edit category' : 'Add a new category' ?></h2>

        <form method="post" action="<?= url('admin/categories.php') ?>" data-validate novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editId ? 'update' : 'create' ?>">
            <input type="hidden" name="category_id" value="<?= (int) $editRow['category_id'] ?>">

            <div class="field<?= isset($errors['category_name']) ? ' has-error' : '' ?>">
                <label for="category_name">Category name *</label>
                <input type="text" id="category_name" name="category_name"
                       value="<?= e($editRow['category_name']) ?>" data-label="Category name" required maxlength="80">
                <span class="err"><?= e($errors['category_name'] ?? '') ?></span>
            </div>

            <div class="field">
                <label>Icon (drawn with SVG)</label>
                <div class="icon-pick">
                    <?php foreach (category_icon_keys() as $key): ?>
                        <label>
                            <input type="radio" name="icon" value="<?= e($key) ?>" <?= $editRow['icon'] === $key ? 'checked' : '' ?>>
                            <span><?= icon($key, 26) ?></span>
                            <span><?= e($key) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="buy-row">
                <button type="submit" class="btn btn-accent">
                    <?= icon($editId ? 'edit' : 'plus', 16) ?> <?= $editId ? 'Save changes' : 'Add category' ?>
                </button>
                <?php if ($editId): ?>
                    <a class="btn btn-ghost" href="<?= url('admin/categories.php') ?>">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php admin_footer(); ?>
