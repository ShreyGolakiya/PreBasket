<?php
/*
 * login.php  -  Customer login
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$email = '';
$error = '';

if (is_post()) {
    require_csrf('login.php');

    $email = mb_strtolower(post_str('email', 150));
    $pass  = (string) ($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
        $error = 'Please enter your email and password.';
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$email]);
        $user = $st->fetch();

        // password_verify() compares the typed password with the stored hash
        if ($user && password_verify($pass, $user['password_hash'])) {
            login_user($user);

            // If the password was hashed with an older algorithm, upgrade it quietly.
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
                    ->execute([password_hash($pass, PASSWORD_DEFAULT), $user['user_id']]);
            }

            flash('success', 'Welcome back, ' . $user['name'] . '!');
            $target = $_SESSION['after_login'] ?? 'index.php';
            unset($_SESSION['after_login']);
            redirect(safe_redirect_target($target));
        }

        // Same message for a wrong email and a wrong password,
        // so nobody can find out which emails are registered.
        $error = 'Invalid login details. Please check your email and password.';
    }
}

$pageTitle = 'Login';
$activeNav = 'login';
require __DIR__ . '/includes/header.php';
?>

<div class="container auth-wrap">
    <div class="auth-card">
        <a class="brand" href="<?= url('index.php') ?>"><?= logo_mark(34) ?><span class="brand-text">Pre<span>Basket</span></span></a>

        <div class="auth-head">
            <h1>Welcome back</h1>
            <p>Login to place a pre-order and track your collection.</p>
        </div>

        <div class="demo-hint">
            <strong>Demo account:</strong> <b>demo@prebasket.test</b> &nbsp;/&nbsp; <b>demo123</b>
        </div>

        <?php if ($error): ?>
            <div class="flash flash-error" style="margin-bottom:14px"><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="post" action="<?= url('login.php') ?>" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>"
                       data-rule="email" data-label="Email" required maxlength="150" autocomplete="email" autofocus>
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

            <button type="submit" class="btn btn-accent btn-lg btn-block">Login</button>
        </form>

        <p class="auth-foot">
            New to PreBasket? <a href="<?= url('register.php') ?>">Create an account</a><br>
            <span class="small">Shop staff? <a href="<?= url('admin/login.php') ?>">Use the admin login</a></span>
        </p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
