<?php
/*
 * register.php  -  Create a new customer account
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$old    = ['name' => '', 'email' => '', 'phone' => ''];
$errors = [];

if (is_post()) {
    require_csrf('register.php');

    $name    = post_str('name', 100);
    $email   = mb_strtolower(post_str('email', 150));
    $phone   = preg_replace('/\D/', '', post_str('phone', 15));
    $pass    = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $old = ['name' => $name, 'email' => $email, 'phone' => $phone];

    // ---- server side validation (JavaScript is only a helper) ----
    if (!valid_name($name))   { $errors['name']  = 'Please enter your full name (letters only, at least 3 characters).'; }
    if (!valid_email($email)) { $errors['email'] = 'Please enter a valid email address.'; }
    if (!valid_phone($phone)) { $errors['phone'] = 'Phone number must be exactly 10 digits.'; }

    $pwProblem = password_problem($pass);
    if ($pwProblem)            { $errors['password'] = $pwProblem; }
    if ($pass !== $confirm)    { $errors['confirm_password'] = 'The two passwords do not match.'; }

    if (!$errors) {
        $pdo = db();
        $st  = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) {
            $errors['email'] = 'An account with this email already exists. Please login instead.';
        } else {
            try {
                $ins = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?) RETURNING user_id');
                $ins->execute([$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)]);

                $uid = (int) $ins->fetchColumn();
                login_user(['user_id' => $uid, 'name' => $name, 'email' => $email]);

                flash('success', 'Welcome to PreBasket, ' . $name . '! Your account is ready.');
                redirect('products.php');
            } catch (PDOException $ex) {
                log_exception($ex);
                // 23000 = duplicate key (two people registering at the same moment)
                $errors['email'] = ($ex->getCode() === '23000')
                    ? 'An account with this email already exists.'
                    : 'Something went wrong. Please try again.';
            }
        }
    }
}

$pageTitle = 'Create account';
$activeNav = 'register';
require __DIR__ . '/includes/header.php';
?>

<div class="container auth-wrap">
    <div class="auth-card">
        <a class="brand" href="<?= url('index.php') ?>"><?= logo_mark(34) ?><span class="brand-text">Pre<span>Basket</span></span></a>

        <div class="auth-head">
            <h1>Create your account</h1>
            <p>It takes a minute. Then you can pre-order and skip the queue.</p>
        </div>

        <form method="post" action="<?= url('register.php') ?>" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" value="<?= e($old['name']) ?>"
                       data-rule="name" data-label="Full name" required maxlength="100" autocomplete="name">
                <span class="err"><?= e($errors['name'] ?? '') ?></span>
            </div>

            <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?= e($old['email']) ?>"
                       data-rule="email" data-label="Email" required maxlength="150" autocomplete="email">
                <span class="err"><?= e($errors['email'] ?? '') ?></span>
            </div>

            <div class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>">
                <label for="phone">Phone number</label>
                <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>"
                       data-rule="phone" data-label="Phone number" required maxlength="10"
                       inputmode="numeric" autocomplete="tel">
                <span class="hint">10 digits. We use it only to call you about your pickup.</span>
                <span class="err"><?= e($errors['phone'] ?? '') ?></span>
            </div>

            <div class="field-row">
                <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
                    <label for="password">Password</label>
                    <div class="pw-wrap">
                        <input type="password" id="password" name="password"
                               data-rule="password" data-label="Password" required maxlength="72" autocomplete="new-password">
                        <button type="button" class="pw-toggle">SHOW</button>
                    </div>
                    <span class="err"><?= e($errors['password'] ?? '') ?></span>
                </div>

                <div class="field<?= isset($errors['confirm_password']) ? ' has-error' : '' ?>">
                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           data-rule="match" data-match="password" data-label="Confirm password"
                           required maxlength="72" autocomplete="new-password">
                    <span class="err"><?= e($errors['confirm_password'] ?? '') ?></span>
                </div>
            </div>

            <p class="small muted">At least 6 characters, with one letter and one number.</p>

            <button type="submit" class="btn btn-accent btn-lg btn-block">Create account</button>
        </form>

        <p class="auth-foot">Already have an account? <a href="<?= url('login.php') ?>">Login here</a></p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
