<?php
/*
 * includes/admin_auth.php
 * ---------------------------------------------------------------
 * Admin (shop owner) login helpers + the admin page layout.
 * A normal customer login can NEVER open an admin page, because the
 * admin uses a completely different session key ($_SESSION['admin_id']).
 */
require_once __DIR__ . '/functions.php';

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        flash('error', 'Please login as staff to open the admin panel.');
        redirect('admin/login.php');
    }
}

function login_admin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']   = (int) $admin['admin_id'];
    $_SESSION['admin_name'] = $admin['full_name'];
}

function logout_admin(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_name']);
}

function current_admin_name(): string
{
    return (string) ($_SESSION['admin_name'] ?? 'Staff');
}

/** Number shown on the "Orders" menu item: orders still waiting for staff. */
function pending_order_count(PDO $pdo): int
{
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM orders WHERE order_status IN ('Pending','Accepted','Preparing')"
    )->fetchColumn();
}

/**
 * Prints the whole admin page top (sidebar + header).
 * $adminTitle and $adminNav must be set before calling.
 */
function admin_header(string $title, string $nav): void
{
    $pdo     = db();
    $pending = pending_order_count($pdo);
    $items = [
        'dashboard'  => ['dashboard.php',    'chart',   'Dashboard'],
        'orders'     => ['orders.php',       'list',    'Orders'],
        'verify'     => ['verify_order.php', 'scan',    'Verify / Collect'],
        'products'   => ['products.php',     'box',     'Products'],
        'inventory'  => ['inventory.php',    'repeat',  'Inventory'],
        'categories' => ['categories.php',   'tag',     'Categories'],
        'feedback'   => ['feedback.php',     'message', 'Feedback'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> &middot; QuickCart Admin</title>
<link rel="icon" href="<?= url('assets/icons/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<script>
var BASE = <?= json_encode(BASE_URL) ?>;
var CSRF = <?= json_encode(csrf_token()) ?>;
</script>
</head>
<body class="admin-body">

<aside class="admin-side" id="adminSide">
    <a class="admin-brand" href="<?= url('admin/dashboard.php') ?>">
        <?= logo_mark(30) ?>
        <span>QuickCart<small>Admin panel</small></span>
    </a>
    <nav class="admin-nav">
        <?php foreach ($items as $key => $it): ?>
            <a class="<?= $nav === $key ? 'active' : '' ?>" href="<?= url('admin/' . $it[0]) ?>">
                <?= icon($it[1], 19) ?><span><?= e($it[2]) ?></span>
                <?php if ($key === 'orders' && $pending > 0): ?>
                    <em class="side-count"><?= $pending ?></em>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="admin-side-foot">
        <a href="<?= url('index.php') ?>" target="_blank" rel="noopener"><?= icon('store', 18) ?><span>View website</span></a>
        <a href="<?= url('admin/logout.php') ?>"><?= icon('logout', 18) ?><span>Logout</span></a>
    </div>
</aside>

<div class="admin-main">
    <header class="admin-top">
        <button class="nav-toggle" type="button" id="sideToggle" aria-label="Menu"><?= icon('menu', 22) ?></button>
        <h1><?= e($title) ?></h1>
        <span class="admin-who"><?= icon('user', 18) ?> <?= e(current_admin_name()) ?></span>
    </header>
    <div class="admin-content">
        <?php render_flashes(); ?>
<?php
}

function admin_footer(array $scripts = [], string $inline = ''): void
{
    ?>
    </div><!-- /admin-content -->
</div><!-- /admin-main -->
<script src="<?= url('assets/js/script.js') ?>"></script>
<?php foreach ($scripts as $s) { echo '<script src="' . url($s) . '"></script>' . "\n"; } ?>
<?php if ($inline !== '') { echo '<script>' . $inline . '</script>' . "\n"; } ?>
</body>
</html>
<?php
}
