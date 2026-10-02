<?php
/*
 * includes/product_form.php
 * ---------------------------------------------------------------
 * The Add Product and Edit Product screens use exactly the same
 * fields and the same rules, so both live here in one place.
 */
require_once __DIR__ . '/functions.php';

/**
 * Reads the product fields from $_POST and checks them.
 * Returns [cleanValues, errors]. If errors is empty, the values are safe to save.
 */
function product_input_validate(PDO $pdo): array
{
    $errors = [];

    $v = [
        'product_name' => post_str('product_name', 150),
        'category_id'  => post_int('category_id', 0),
        'price'        => trim((string) ($_POST['price'] ?? '')),
        'old_price'    => trim((string) ($_POST['old_price'] ?? '')),
        'unit_label'   => post_str('unit_label', 40),
        'stock'        => trim((string) ($_POST['stock_quantity'] ?? '')),
        'description'  => post_str('description', 1000),
        'rating'       => trim((string) ($_POST['rating'] ?? '4.0')),
        'is_featured'  => isset($_POST['is_featured']) ? 1 : 0,
        'status'       => post_str('status', 10) === 'inactive' ? 'inactive' : 'active',
    ];

    // ---- name
    if ($v['product_name'] === '') {
        $errors['product_name'] = 'Please enter the product name.';
    } elseif (mb_strlen($v['product_name']) < 2) {
        $errors['product_name'] = 'The product name is too short.';
    }

    // ---- category must really exist (foreign key would fail otherwise)
    $st = $pdo->prepare('SELECT category_id FROM categories WHERE category_id = ?');
    $st->execute([$v['category_id']]);
    if (!$st->fetch()) {
        $errors['category_id'] = 'Please choose a category.';
    }

    // ---- price
    if ($v['price'] === '' || !is_numeric($v['price']) || (float) $v['price'] <= 0) {
        $errors['price'] = 'Enter a price greater than 0.';
    } elseif ((float) $v['price'] > 99999.99) {
        $errors['price'] = 'That price looks too high.';
    } else {
        $v['price'] = round((float) $v['price'], 2);
    }

    // ---- old price (optional, only used to show a discount)
    if ($v['old_price'] === '') {
        $v['old_price'] = null;
    } elseif (!is_numeric($v['old_price']) || (float) $v['old_price'] <= 0) {
        $errors['old_price'] = 'The old price must be a number greater than 0, or left empty.';
    } else {
        $v['old_price'] = round((float) $v['old_price'], 2);
        if (!isset($errors['price']) && $v['old_price'] <= $v['price']) {
            $errors['old_price'] = 'The old price must be higher than the current price.';
        }
    }

    // ---- stock (never negative)
    if ($v['stock'] === '' || !ctype_digit($v['stock'])) {
        $errors['stock_quantity'] = 'Enter the stock as a whole number (0 or more).';
    } elseif ((int) $v['stock'] > 100000) {
        $errors['stock_quantity'] = 'That stock number looks too high.';
    } else {
        $v['stock'] = (int) $v['stock'];
    }

    // ---- rating
    if (!is_numeric($v['rating']) || (float) $v['rating'] < 0 || (float) $v['rating'] > 5) {
        $errors['rating'] = 'Rating must be between 0 and 5.';
    } else {
        $v['rating'] = round((float) $v['rating'], 1);
    }

    return [$v, $errors];
}

/** Prints the form fields. $p holds the current values. */
function product_form_fields(array $p, array $errors, array $categories): void
{
    $err = function (string $key) use ($errors) {
        return isset($errors[$key]) ? '<span class="err">' . e($errors[$key]) . '</span>' : '<span class="err"></span>';
    };
    $has = function (string $key) use ($errors) {
        return isset($errors[$key]) ? ' has-error' : '';
    };
    ?>
    <div class="field<?= $has('product_name') ?>">
        <label for="product_name">Product name *</label>
        <input type="text" id="product_name" name="product_name" value="<?= e($p['product_name']) ?>"
               data-label="Product name" required maxlength="150">
        <?= $err('product_name') ?>
    </div>

    <div class="field-row">
        <div class="field<?= $has('category_id') ?>">
            <label for="category_id">Category *</label>
            <select id="category_id" name="category_id" required>
                <option value="">-- choose --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['category_id'] ?>" <?= (int) $p['category_id'] === (int) $c['category_id'] ? 'selected' : '' ?>>
                        <?= e($c['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $err('category_id') ?>
        </div>

        <div class="field">
            <label for="unit_label">Pack size</label>
            <input type="text" id="unit_label" name="unit_label" value="<?= e($p['unit_label']) ?>"
                   maxlength="40" placeholder="1 kg, 500 ml, 6 pieces&hellip;">
            <span class="err"></span>
        </div>
    </div>

    <div class="field-row">
        <div class="field<?= $has('price') ?>">
            <label for="price">Selling price (&#8377;) *</label>
            <input type="number" id="price" name="price" value="<?= e($p['price']) ?>"
                   step="0.01" min="0.01" data-rule="money" data-label="Price" required>
            <?= $err('price') ?>
        </div>

        <div class="field<?= $has('old_price') ?>">
            <label for="old_price">Old price (&#8377;)</label>
            <input type="number" id="old_price" name="old_price" value="<?= e($p['old_price'] ?? '') ?>"
                   step="0.01" min="0.01">
            <span class="hint">Leave empty if there is no discount.</span>
            <?= $err('old_price') ?>
        </div>
    </div>

    <div class="field-row">
        <div class="field<?= $has('stock_quantity') ?>">
            <label for="stock_quantity">Stock quantity *</label>
            <input type="number" id="stock_quantity" name="stock_quantity" value="<?= e($p['stock']) ?>"
                   step="1" min="0" data-rule="int0" data-label="Stock" required>
            <span class="hint">0 means the customer sees &ldquo;Out of Stock&rdquo;.</span>
            <?= $err('stock_quantity') ?>
        </div>

        <div class="field<?= $has('rating') ?>">
            <label for="rating">Rating (0 to 5)</label>
            <input type="number" id="rating" name="rating" value="<?= e($p['rating']) ?>" step="0.1" min="0" max="5">
            <?= $err('rating') ?>
        </div>
    </div>

    <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" maxlength="1000"
                  placeholder="A short line about the product."><?= e($p['description']) ?></textarea>
        <span class="err"></span>
    </div>

    <div class="field">
        <label for="image">Product picture</label>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
        <span class="hint">JPG, PNG, WEBP or GIF, up to 2 MB. Leave empty to keep the current picture.</span>
        <span class="err"></span>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active"   <?= $p['status'] === 'active' ? 'selected' : '' ?>>Active (shown to customers)</option>
                <option value="inactive" <?= $p['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (hidden)</option>
            </select>
        </div>

        <div class="field">
            <label>Featured</label>
            <label style="display:flex;gap:9px;align-items:center;font-weight:500;padding-top:9px">
                <input type="checkbox" name="is_featured" value="1" <?= $p['is_featured'] ? 'checked' : '' ?>
                       style="width:auto;accent-color:var(--green)">
                Show this product on the home page
            </label>
        </div>
    </div>
    <?php
}
