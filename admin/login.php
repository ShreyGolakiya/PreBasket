<?php
/*
 * admin/login.php  -  Shop owner / staff login
 * A customer account can never be used here: this checks the admins table.
 */
require_once __DIR__ . '/../includes/admin_auth.php';

if (is_admin_logged_in()) {
    redirect('admin/dashboard.php');
}

$username = '';
$error    = '';

if (is_post()) {
    require_csrf('admin/login.php');

    $username = post_str('username', 50);
    $pass     = (string) ($_POST['password'] ?? '');

    if ($username === '' || $pass === '') {
        $error = 'Please enter your username and password.';
    } else {
        $st = db()->prepare('SELECT * FROM admins WHERE username = ?');
        $st->execute([$username]);
        $admin = $st->fetch();

        if ($admin && password_verify($pass, $admin['password_hash'])) {
            login_admin($admin);
            flash('success', 'Welcome back, ' . $admin['full_name'] . '.');
            redirect('admin/dashboard.php');
        }
        $error = 'Invalid login details.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff login &middot; QuickCart Admin</title>
<link rel="icon" href="<?= url('assets/icons/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<script>
var BASE = <?= json_encode(BASE_URL) ?>;
var CSRF = <?= json_encode(csrf_token()) ?>;
</script>
</head>
<body>

<div class="container auth-wrap" style="min-height:100vh">
    <div class="auth-card">
        <a class="brand" href="<?= url('index.php') ?>"><?= logo_mark(34) ?><span class="brand-text">Pre<span>Basket</span></span></a>

        <div class="auth-head">
            <h1>Staff / Admin login</h1>
            <p>Manage products, stock and customer orders.</p>
        </div>

        <div class="demo-hint">
            <strong>Demo login:</strong> <b>admin</b> &nbsp;/&nbsp; <b>admin123</b>
        </div>

        <?php render_flashes(); ?>

        <?php if ($error): ?>
            <div class="flash flash-error" style="margin-bottom:14px"><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="post" action="<?= url('admin/login.php') ?>" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= e($username) ?>"
                       data-label="Username" required maxlength="50" autocomplete="username" autofocus>
                <span class="err"></span>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password"
                           data-label="Password" required maxlength="72" autocomplete="current-password">
                    <button type="button" class="pw-toggle">SHOW</button>
                </div>
                <span class="err"></span>
            </div>

            <button type="submit" class="btn btn-lg btn-block"><?= icon('shield', 18) ?> Login to admin panel</button>
        </form>

        <p class="auth-foot">
            Are you a customer? <a href="<?= url('login.php') ?>">Use the customer login</a>
        </p>
    </div>
</div>

<script src="<?= url('assets/js/script.js') ?>"></script>
</body>
</html>
