<?php
/*
 * includes/icons.php
 * ---------------------------------------------------------------
 * SVG icons used all over the website.
 * Every icon is a small inline <svg>. Use it like:
 *      <?= icon('cart', 22) ?>
 * Icons take their colour from CSS "color" (stroke = currentColor).
 */

function icon(string $name, int $size = 22, string $class = ''): string
{
    static $paths = null;
    if ($paths === null) {
        $paths = [
            // ----- shopping / general -----
            'cart'    => '<circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M2.5 3.5h2.8l2.2 11h10l2-8H6.4"/>',
            'basket'  => '<path d="M3 9h18l-2 11H5z"/><path d="M8 9l3-5M16 9l-3-5"/><path d="M9.5 13v3M14.5 13v3"/>',
            'store'   => '<path d="M3 9l1.5-5h15L21 9"/><path d="M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0"/><path d="M5 12v8h14v-8"/><path d="M10 20v-5h4v5"/>',
            'pickup'  => '<path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 016 0v2"/>',
            'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
            'heart'   => '<path d="M12 20s-7-4.6-9-9.2C1.7 7.3 4 4.5 7 4.5c2 0 3.5 1 5 3 1.5-2 3-3 5-3 3 0 5.3 2.8 4 6.3-2 4.6-9 9.2-9 9.2z"/>',
            'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>',
            'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 3-5.5 6.5-5.5s6.500 1.900 6.500 5.500"/><path d="M16 4.800a3.500 3.500 0 010 6.400M18 14.800c2 .7 3.500 2.200 3.500 5.200"/>',
            'menu'    => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
            'plus'    => '<path d="M12 5v14M5 12h14"/>',
            'minus'   => '<path d="M5 12h14"/>',
            'trash'   => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v6M14 11v6"/>',
            'edit'    => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.500 6.500l4 4"/>',
            'logout'  => '<path d="M9 4H5v16h4"/><path d="M16 8l4 4-4 4M20 12H9"/>',
            'tag'     => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.500" cy="8.500" r="1.200"/>',
            'box'     => '<path d="M3 7.500L12 3l9 4.500v9L12 21l-9-4.500z"/><path d="M3 7.500L12 12l9-4.500M12 12v9"/>',
            'chart'   => '<path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 16v-5M12 16V8M16 16v-3"/>',
            'list'    => '<path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
            'message' => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/>',
            'shield'  => '<path d="M12 3l8 3v6c0 5-3.500 8-8 9-4.500-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
            'scan'    => '<path d="M4 8V5a1 1 0 011-1h3M16 4h3a1 1 0 011 1v3M20 16v3a1 1 0 01-1 1h-3M8 20H5a1 1 0 01-1-1v-3"/><path d="M4 12h16"/>',
            'qr'      => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h2v2h-2zM18 14h2v2M14 18h2v2M18 18h2v2"/>',
            'repeat'  => '<path d="M4 11V9a3 3 0 013-3h11l-3-3M20 13v2a3 3 0 01-3 3H6l3 3"/>',
            'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'star'    => '<path d="M12 3l2.700 5.600 6.100.9-4.400 4.300 1 6.100L12 17l-5.400 2.900 1-6.100L3.200 9.500l6.100-.9z"/>',
            'home'    => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
            'alert'   => '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17v.5"/>',
            'camera'  => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.500"/>',

            // ----- order status icons -----
            'st-pending'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'st-accepted'  => '<circle cx="12" cy="12" r="9"/><path d="M8 12.500l3 3 5-6"/>',
            'st-preparing' => '<path d="M3 7.500L12 3l9 4.500v9L12 21l-9-4.500z"/><path d="M3 7.500L12 12l9-4.500M12 12v9"/>',
            'st-ready'     => '<path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 016 0v2"/><path d="M9.500 14l2 2 3.500-4"/>',
            'st-collected' => '<circle cx="10" cy="8" r="4"/><path d="M3 20c0-4 3-6 7-6"/><path d="M15 17l2 2 4-4"/>',
            'st-completed' => '<circle cx="12" cy="10" r="6"/><path d="M8.500 15L7 21l5-2.500L17 21l-1.500-6"/>',
            'st-rejected'  => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',

            // ----- category icons -----
            'grocery'   => '<path d="M12 21V8"/><path d="M12 8c-3 0-4-2-4-4 3 0 4 2 4 4zM12 8c3 0 4-2 4-4-3 0-4 2-4 4zM12 14c-3 0-4-2-4-4 3 0 4 2 4 4zM12 14c3 0 4-2 4-4-3 0-4 2-4 4zM12 20c-3 0-4-2-4-4 3 0 4 2 4 4zM12 20c3 0 4-2 4-4-3 0-4 2-4 4z"/>',
            'dairy'     => '<path d="M8 3h8l2 4v14H6V7z"/><path d="M6 7h12"/><path d="M9 12h6M9 16h6"/>',
            'snacks'    => '<path d="M6 4h12l-1.200 16H7.200z"/><path d="M6.500 8h11"/><circle cx="12" cy="13.500" r="2.500"/>',
            'beverages' => '<path d="M10 3h4v4l2 3v10a1 1 0 01-1 1H9a1 1 0 01-1-1V10l2-3z"/><path d="M8 13h8"/>',
            'personal'  => '<path d="M9 9h6v11a1 1 0 01-1 1h-4a1 1 0 01-1-1z"/><path d="M12 9V5h3"/><path d="M9 13h6"/>',
            'household' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
            'fruits'    => '<path d="M12 8c-3-2-7 0-7 5 0 4 3 8 5 8 1 0 1.500-.500 2-.500s1 .500 2 .500c2 0 5-4 5-8 0-5-4-7-7-5z"/><path d="M12 8c0-2 1-4 3-5"/>',
        ];
    }

    if (!isset($paths[$name])) {
        $name = 'box';
    }
    $cls = trim('icon ' . $class);
    return '<svg class="' . e($cls) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" '
         . 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
         . 'aria-hidden="true">' . $paths[$name] . '</svg>';
}

/** List of icon keys an admin can choose for a category. */
function category_icon_keys(): array
{
    return ['grocery', 'dairy', 'snacks', 'beverages', 'personal', 'household', 'fruits'];
}

/** Small star-burst discount badge drawn with SVG. Example: discount_badge(20) */
function discount_badge(int $percent): string
{
    $points = [];
    $spikes = 12;
    for ($i = 0; $i < $spikes * 2; $i++) {
        $r     = ($i % 2 === 0) ? 22 : 18;
        $angle = M_PI * $i / $spikes;
        $points[] = round(24 + $r * sin($angle), 1) . ',' . round(24 - $r * cos($angle), 1);
    }
    return '<svg class="disc-badge" width="46" height="46" viewBox="0 0 48 48" aria-label="' . $percent . '% off">'
         . '<polygon points="' . implode(' ', $points) . '" fill="#f26b1d"/>'
         . '<text x="24" y="22" text-anchor="middle" font-family="Arial, sans-serif" font-weight="700" font-size="11" fill="#fff">' . $percent . '%</text>'
         . '<text x="24" y="33" text-anchor="middle" font-family="Arial, sans-serif" font-weight="700" font-size="8" fill="#fff">OFF</text>'
         . '</svg>';
}

/** Big logo mark (basket with a check) used in the header and login pages. */
function logo_mark(int $size = 34): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 40 40" aria-hidden="true">'
         . '<rect width="40" height="40" rx="11" fill="#0f6b4a"/>'
         . '<path d="M9 16h22l-2.500 15h-17z" fill="#fff"/>'
         . '<path d="M14 16l4-7M26 16l-4-7" stroke="#fff" stroke-width="2.400" stroke-linecap="round" fill="none"/>'
         . '<path d="M15.500 23.500l3.500 3.500 6-7" stroke="#0f6b4a" stroke-width="2.600" stroke-linecap="round" stroke-linejoin="round" fill="none"/>'
         . '</svg>';
}
