<?php
/*
 * includes/product_card.php
 * ---------------------------------------------------------------
 * Builds ONE product card. Used on the home page, the products page
 * and anywhere else a grid of products is shown.
 *
 *   echo product_card($productRow);
 */
require_once __DIR__ . '/functions.php';

function product_card(array $p): string
{
    $id       = (int) $p['product_id'];
    $stock    = (int) ($p['stock_quantity'] ?? 0);
    $price    = (float) $p['price'];
    $oldPrice = $p['old_price'] ?? null;
    $off      = discount_percent($price, $oldPrice);
    $out      = $stock <= 0;
    $link     = url('product.php?id=' . $id);

    // data-* attributes let the JavaScript filter/sort without reloading the page
    $data = ' data-id="' . $id . '"'
          . ' data-name="' . e(mb_strtolower($p['product_name'])) . '"'
          . ' data-price="' . $price . '"'
          . ' data-rating="' . (float) ($p['rating'] ?? 0) . '"'
          . ' data-stock="' . $stock . '"'
          . ' data-category="' . (int) ($p['category_id'] ?? 0) . '"';

    $html = '<article class="p-card' . ($out ? ' is-out' : '') . '"' . $data . '>';

    // ---- picture area
    $html .= '<a class="p-media" href="' . $link . '">'
           . '<img src="' . product_image($p['image'] ?? '') . '" alt="' . e($p['product_name']) . '" loading="lazy" width="200" height="200">';
    if ($off > 0 && !$out) {
        $html .= discount_badge($off);
    }
    if ($out) {
        $html .= '<span class="out-tag">Out of Stock</span>';
    } elseif ($stock <= 5) {
        $html .= '<span class="low-tag">Only ' . $stock . ' left</span>';
    }
    $html .= '</a>';

    // ---- wishlist heart (a small local-only UI touch)
    $html .= '<button type="button" class="p-fav" data-fav="' . $id . '" aria-label="Save '
           . e($p['product_name']) . '" aria-pressed="false">' . icon('heart', 18) . '</button>';

    // ---- text
    $html .= '<div class="p-body">'
           . '<a class="p-name" href="' . $link . '">' . e($p['product_name']) . '</a>'
           . '<div class="p-meta"><span class="p-unit">' . e($p['unit_label'] ?? '') . '</span>' . star_html($p['rating'] ?? 4) . '</div>'
           . '<div class="p-buy">'
           . '<div class="p-price"><strong>' . money($price) . '</strong>';
    if ($off > 0) {
        $html .= '<s>' . money($oldPrice) . '</s>';
    }
    $html .= '</div>';

    if ($out) {
        $html .= '<span class="btn btn-ghost btn-sm is-disabled">Out of Stock</span>';
    } else {
        $html .= '<form class="add-form" method="post" action="' . url('cart_action.php') . '">'
               . csrf_field()
               . '<input type="hidden" name="action" value="add">'
               . '<input type="hidden" name="product_id" value="' . $id . '">'
               . '<input type="hidden" name="quantity" value="1">'
               . '<input type="hidden" name="redirect" value="' . e(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php')
                    . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')) . '">'
               . '<button type="submit" class="btn btn-add btn-sm" data-add="' . $id . '" data-stock="' . $stock . '">'
               . icon('plus', 15) . ' ADD</button>'
               . '</form>';
    }

    $html .= '</div></div></article>';
    return $html;
}
