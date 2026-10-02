</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <?= logo_mark(30) ?>
            <div>
                <strong>PreBasket</strong>
                <p>Shop before you shop. Pre-order your supermarket basket and collect it without standing in the billing queue.</p>
            </div>
        </div>

        <div class="footer-col">
            <h4>Shop</h4>
            <a href="<?= url('products.php') ?>">All products</a>
            <a href="<?= url('products.php?sort=price_low') ?>">Lowest price</a>
            <a href="<?= url('products.php?sort=rating') ?>">Top rated</a>
            <a href="<?= url('cart.php') ?>">My cart</a>
        </div>

        <div class="footer-col">
            <h4>Account</h4>
            <?php if (is_logged_in()): ?>
                <a href="<?= url('orders.php') ?>">My orders</a>
                <a href="<?= url('logout.php') ?>">Logout</a>
            <?php else: ?>
                <a href="<?= url('login.php') ?>">Login</a>
                <a href="<?= url('register.php') ?>">Create account</a>
            <?php endif; ?>
            <a href="<?= url('index.php#how') ?>">How it works</a>
        </div>

        <div class="footer-col">
            <h4>Store</h4>
            <a href="<?= url('admin/login.php') ?>">Staff login</a>
            <span class="footer-note">PreBasket Supermarket<br>Ahmedabad, Gujarat</span>
        </div>
    </div>

    <div class="container footer-bottom">
        <span>&copy; <?= date('Y') ?> PreBasket &mdash; college web technology project.</span>
        <span>Built with HTML, CSS, JavaScript, PHP, MySQL, SVG and Canvas.</span>
    </div>
</footer>

<script src="<?= url('assets/js/script.js') ?>"></script>
<?php if (!empty($pageScripts)) { foreach ((array) $pageScripts as $s) { echo '<script src="' . url($s) . '"></script>' . "\n"; } } ?>
<?php if (!empty($inlineScript)) { echo '<script>' . $inlineScript . '</script>' . "\n"; } ?>
</body>
</html>
