<?php
/*
 * includes/header.php
 * Top part of every CUSTOMER page.
 * Set $pageTitle and $activeNav before including this file.
 */
if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}
$pageTitle = $pageTitle ?? 'PreBasket';
$activeNav = $activeNav ?? '';
$cartCount = cart_count();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> &middot; PreBasket</title>
<meta name="description" content="PreBasket - pre-order your supermarket shopping and collect it without waiting in the billing queue.">
<link rel="icon" href="<?= url('assets/icons/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<script>
/* Two values the JavaScript needs to talk to PHP safely. */
var BASE = <?= json_encode(BASE_URL) ?>;
var CSRF = <?= json_encode(csrf_token()) ?>;
</script>
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= url('index.php') ?>">
            <?= logo_mark(34) ?>
            <span class="brand-text">Pre<span>Basket</span></span>
        </a>

        <form class="header-search" action="<?= url('products.php') ?>" method="get" role="search">
            <?= icon('search', 18) ?>
            <input type="search" name="q" placeholder="Search products&hellip;" aria-label="Search products"
                   maxlength="60" value="<?= e(get_str('q')) ?>">
        </form>

        <button class="nav-toggle" type="button" id="navToggle" aria-label="Open menu" aria-expanded="false">
            <?= icon('menu', 24) ?>
        </button>

        <nav class="main-nav" id="mainNav">
            <a class="<?= $activeNav === 'home' ? 'active' : '' ?>" href="<?= url('index.php') ?>">
                <?= icon('home', 18) ?><span>Home</span>
            </a>
            <a class="<?= $activeNav === 'products' ? 'active' : '' ?>" href="<?= url('products.php') ?>">
                <?= icon('store', 18) ?><span>Products</span>
            </a>
            <?php if (is_logged_in()): ?>
                <a class="<?= $activeNav === 'orders' ? 'active' : '' ?>" href="<?= url('orders.php') ?>">
                    <?= icon('list', 18) ?><span>My Orders</span>
                </a>
                <a class="nav-cart <?= $activeNav === 'cart' ? 'active' : '' ?>" href="<?= url('cart.php') ?>">
                    <?= icon('cart', 18) ?><span>Cart</span>
                    <span class="cart-badge<?= $cartCount ? '' : ' is-hidden' ?>" id="cartBadge"><?= (int) $cartCount ?></span>
                </a>
                <a href="<?= url('logout.php') ?>" class="nav-user">
                    <?= icon('user', 18) ?><span><?= e($_SESSION['user_name'] ?? 'Account') ?></span>
                </a>
            <?php else: ?>
                <a class="nav-cart <?= $activeNav === 'cart' ? 'active' : '' ?>" href="<?= url('cart.php') ?>">
                    <?= icon('cart', 18) ?><span>Cart</span>
                    <span class="cart-badge is-hidden" id="cartBadge">0</span>
                </a>
                <a class="<?= $activeNav === 'login' ? 'active' : '' ?>" href="<?= url('login.php') ?>">
                    <?= icon('user', 18) ?><span>Login</span>
                </a>
                <a class="btn btn-accent btn-sm nav-btn" href="<?= url('register.php') ?>">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="main">
<?php render_flashes(); ?>
