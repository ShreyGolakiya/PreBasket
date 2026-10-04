<?php
/*
 * includes/functions.php
 * ---------------------------------------------------------------
 * Helper functions shared by every page:
 *   - error handling, sessions, base URL
 *   - output escaping, flash messages, CSRF protection
 *   - cart helpers
 *   - order placement (with a database transaction)
 *   - order status changes (with allowed transitions)
 */

require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Asia/Kolkata');

/* ------------------------------------------------------------------
 * 1. Error handling  (users never see ugly PHP errors)
 * ------------------------------------------------------------------ */
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/error.log');

/** Shows a friendly error page and stops. */
function show_fatal_page(string $title, string $detailHtml = ''): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>QuickCart - Error</title>'
       . '<style>body{font-family:Segoe UI,Arial,sans-serif;background:#f4f7f5;color:#17231d;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
       . '.box{background:#fff;border:1px solid #dfe7e2;border-radius:16px;padding:32px;max-width:520px;margin:16px}'
       . 'h1{font-size:22px;margin:0 0 10px;color:#0f6b4a}p{line-height:1.6;color:#5f6f66}a{color:#0f6b4a;font-weight:600}</style></head><body>'
       . '<div class="box"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>' . $detailHtml . '</p>'
       . '<p><a href="' . htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') . '/index.php">Go to Home page</a></p></div></body></html>';
    exit;
}

/** Writes an exception to logs/error.log */
function log_exception(Throwable $ex): void
{
    error_log('[QuickCart] ' . get_class($ex) . ': ' . $ex->getMessage()
        . ' in ' . $ex->getFile() . ':' . $ex->getLine());
}

set_exception_handler(function (Throwable $ex) {
    log_exception($ex);
    $detail = 'Something went wrong. Please try again.';
    if (APP_DEBUG) {
        $detail .= '<br><br><code>' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</code>';
    }
    show_fatal_page('Oops!', $detail);
});

/** An error whose message is safe and useful to show to the user. */
class AppException extends Exception
{
}

/* ------------------------------------------------------------------
 * 2. Session + base URL
 * ------------------------------------------------------------------ */

/*
 * Store PHP sessions in PostgreSQL instead of the temporary
 * local filesystem. This is important for Vercel/serverless
 * deployments because different requests may run on different
 * instances.
 */

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private ?PDO $pdo = null;

    private function connection(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = db();
        }

        return $this->pdo;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $st = $this->connection()->prepare(
            'SELECT data FROM php_sessions
             WHERE id = ?
               AND last_activity > ?'
        );

        $st->execute([
            $id,
            time() - 86400
        ]);

        $data = $st->fetchColumn();

        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        $st = $this->connection()->prepare(
            'INSERT INTO php_sessions (id, data, last_activity)
             VALUES (?, ?, ?)
             ON CONFLICT (id)
             DO UPDATE SET
                 data = EXCLUDED.data,
                 last_activity = EXCLUDED.last_activity'
        );

        return $st->execute([
            $id,
            $data,
            time()
        ]);
    }

    public function destroy(string $id): bool
    {
        $st = $this->connection()->prepare(
            'DELETE FROM php_sessions WHERE id = ?'
        );

        return $st->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $st = $this->connection()->prepare(
            'DELETE FROM php_sessions WHERE last_activity < ?'
        );

        $st->execute([
            time() - $max_lifetime
        ]);

        return $st->rowCount();
    }
}

if (session_status() === PHP_SESSION_NONE) {

    session_set_save_handler(
        new DatabaseSessionHandler(),
        true
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);

    session_start();
}

/** Works out the folder of the project (e.g. /QuickCart) automatically. */
function compute_base_url(): string
{
    $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $root    = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    if ($docRoot !== '' && $root !== '' && stripos($root, $docRoot) === 0) {
        return rtrim(substr($root, strlen($docRoot)), '/');
    }
    return '/QuickCart';
}
define('BASE_URL', compute_base_url());

/* ------------------------------------------------------------------
 * 3. Small helpers
 * ------------------------------------------------------------------ */
/** Escape text before printing it in HTML (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Price formatting: 25 => ₹25, 25.5 => ₹25.50 */
function money($amount): string
{
    $a = (float) $amount;
    return '₹' . (floor($a) == $a ? number_format($a, 0) : number_format($a, 2));
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** Trimmed string from POST (empty string when missing). */
function post_str(string $key, int $maxLen = 255): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return mb_substr(trim($v), 0, $maxLen);
}

function get_str(string $key, int $maxLen = 100): string
{
    $v = $_GET[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return mb_substr(trim($v), 0, $maxLen);
}

function post_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? null;
    if ($v === null || !is_scalar($v) || filter_var($v, FILTER_VALIDATE_INT) === false) {
        return $default;
    }
    return (int) $v;
}

function get_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? null;
    if ($v === null || !is_scalar($v) || filter_var($v, FILTER_VALIDATE_INT) === false) {
        return $default;
    }
    return (int) $v;
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/** Only allow redirecting back to a simple page of our own site. */
function safe_redirect_target(string $target, string $default = 'index.php'): string
{
    $target = ltrim($target, '/');
    if ($target !== '' && preg_match('/^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $target)) {
        return $target;
    }
    return $default;
}

/* ------------------------------------------------------------------
 * 4. Flash messages (one-time messages shown after a redirect)
 * ------------------------------------------------------------------ */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];   // success | error | warning | info
}

function render_flashes(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    echo '<div class="flash-wrap">';
    foreach ($_SESSION['flash'] as $f) {
        echo '<div class="flash flash-' . e($f['type']) . '" role="alert">'
           . '<span>' . e($f['msg']) . '</span>'
           . '<button type="button" class="flash-close" aria-label="Close">&times;</button></div>';
    }
    echo '</div>';
    unset($_SESSION['flash']);
}

/* ------------------------------------------------------------------
 * 5. CSRF protection (blocks forged form submissions)
 * ------------------------------------------------------------------ */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== ''
        && hash_equals($_SESSION['csrf_token'] ?? '', $sent);
}

/** Call at the top of every POST handler. */
function require_csrf(string $backTo = 'index.php'): void
{
    if (!csrf_valid()) {
        flash('error', 'Your session expired. Please try again.');
        redirect($backTo);
    }
}

/* ------------------------------------------------------------------
 * 6. Login state
 * ------------------------------------------------------------------ */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/* ------------------------------------------------------------------
 * 7. Products
 * ------------------------------------------------------------------ */
/** URL of a product image; falls back to the placeholder picture. */
function product_image(?string $file): string
{
    $file = basename((string) $file);
    $dir  = __DIR__ . '/../assets/images/products/';
    if ($file === '' || !is_file($dir . $file)) {
        $file = 'placeholder.svg';
    }
    return url('assets/images/products/' . rawurlencode($file));
}

function discount_percent($price, $oldPrice): int
{
    if ($oldPrice !== null && (float) $oldPrice > (float) $price && (float) $oldPrice > 0) {
        return (int) round(((float) $oldPrice - (float) $price) * 100 / (float) $oldPrice);
    }
    return 0;
}

function stock_label(int $stock): string
{
    if ($stock <= 0) {
        return 'Out of Stock';
    }
    if ($stock <= 5) {
        return 'Only ' . $stock . ' left';
    }
    return 'In Stock';
}

function star_html($rating): string
{
    return '<span class="rating">' . icon('star', 14, 'filled') . ' ' . number_format((float) $rating, 1) . '</span>';
}

/* ------------------------------------------------------------------
 * 8. Cart helpers
 * ------------------------------------------------------------------ */
function cart_count(bool $refresh = false): int
{
    if (!is_logged_in()) {
        return 0;
    }

    // The cart badge appears in the header of almost every customer page.
    // Avoid an extra remote Supabase query on every page while keeping the
    // cache short-lived. Cart-changing actions explicitly refresh it.
    $now = time();
    if (!$refresh
        && isset($_SESSION['cart_count_cache'], $_SESSION['cart_count_cache_at'])
        && ($now - (int) $_SESSION['cart_count_cache_at']) < 3) {
        return (int) $_SESSION['cart_count_cache'];
    }

    $st = db()->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart WHERE user_id = ?');
    $st->execute([current_user_id()]);
    $count = (int) $st->fetchColumn();
    $_SESSION['cart_count_cache'] = $count;
    $_SESSION['cart_count_cache_at'] = $now;
    return $count;
}

function set_cart_count_cache(int $count): void
{
    $_SESSION['cart_count_cache'] = max(0, $count);
    $_SESSION['cart_count_cache_at'] = time();
}

function clear_cart_count_cache(): void
{
    unset($_SESSION['cart_count_cache'], $_SESSION['cart_count_cache_at']);
}

/** All cart lines of a user together with the CURRENT product data. */
function cart_items(int $userId): array
{
    $st = db()->prepare(
        'SELECT c.cart_id, c.quantity, p.product_id, p.product_name, p.price, p.unit_label,
                p.image, p.stock_quantity, p.status
           FROM cart c
           JOIN products p ON p.product_id = c.product_id
          WHERE c.user_id = ?
          ORDER BY c.cart_id'
    );
    $st->execute([$userId]);
    $items = $st->fetchAll();

    // cart.php already has every line, so do not make the header run another
    // SELECT SUM(quantity) against the remote database.
    $count = 0;
    foreach ($items as $item) {
        $count += (int) $item['quantity'];
    }
    set_cart_count_cache($count);

    return $items;
}

/** Returns a list of problems in the cart (out of stock, too many, removed products). */
function cart_problems(array $items): array
{
    $problems = [];
    foreach ($items as $it) {
        $stock = (int) $it['stock_quantity'];
        if ($it['status'] !== 'active') {
            $problems[$it['product_id']] = $it['product_name'] . ' is no longer available.';
        } elseif ($stock <= 0) {
            $problems[$it['product_id']] = $it['product_name'] . ' is out of stock.';
        } elseif ((int) $it['quantity'] > $stock) {
            $problems[$it['product_id']] = 'Only ' . $stock . ' items available for ' . $it['product_name'] . '.';
        }
    }
    return $problems;
}

function cart_total(array $items): float
{
    $total = 0.0;
    foreach ($items as $it) {
        $total += round((float) $it['price'] * (int) $it['quantity'], 2);
    }
    return round($total, 2);
}

/* ------------------------------------------------------------------
 * 9. Orders
 * ------------------------------------------------------------------ */
const ORDER_FLOW = ['Pending', 'Accepted', 'Preparing', 'Ready for Collection', 'Collected', 'Completed'];

/** Which status may follow which (prevents random invalid changes). */
function order_transitions(): array
{
    return [
        'Pending'              => ['Accepted', 'Rejected', 'Cancelled'],
        'Accepted'             => ['Preparing', 'Cancelled'],
        'Preparing'            => ['Ready for Collection', 'Cancelled'],
        'Ready for Collection' => ['Collected', 'Cancelled'],
        'Collected'            => ['Completed'],
        'Completed'            => [],
        'Rejected'             => [],
        'Cancelled'            => [],
    ];
}

/** Text of the button that moves an order forward. */
function next_action_label(string $status): string
{
    $labels = [
        'Accepted'             => 'Accept Order',
        'Preparing'            => 'Start Preparing',
        'Ready for Collection' => 'Mark Ready',
        'Collected'            => 'Mark Collected',
        'Completed'            => 'Mark Completed',
        'Rejected'             => 'Reject',
        'Cancelled'            => 'Cancel Order',
    ];
    return $labels[$status] ?? $status;
}

/** The one "normal" next step for an order (or null). */
function next_status(string $status): ?string
{
    $t = order_transitions()[$status] ?? [];
    foreach ($t as $s) {
        if ($s !== 'Rejected' && $s !== 'Cancelled') {
            return $s;
        }
    }
    return null;
}

function status_class(string $status): string
{
    return 'st-' . strtolower(str_replace(' ', '-', $status));
}

function status_icon_name(string $status): string
{
    $map = [
        'Pending' => 'st-pending', 'Accepted' => 'st-accepted', 'Preparing' => 'st-preparing',
        'Ready for Collection' => 'st-ready', 'Collected' => 'st-collected', 'Completed' => 'st-completed',
        'Rejected' => 'st-rejected', 'Cancelled' => 'st-rejected',
    ];
    return $map[$status] ?? 'st-pending';
}

function status_badge(string $status): string
{
    return '<span class="badge ' . e(status_class($status)) . '">' . e($status) . '</span>';
}

function pay_badge(string $status): string
{
    return '<span class="badge pay-' . e(strtolower($status)) . '">' . e($status) . '</span>';
}

/** Order codes look like PB202609200001 (PB + date + 4 digit number). */
function valid_order_code(string $code): bool
{
    return (bool) preg_match('/^PB[0-9]{12}$/', $code);
}

function generate_order_code(PDO $pdo): string
{
    $prefix = 'PB' . date('Ymd');
    $st = $pdo->prepare('SELECT order_code FROM orders WHERE order_code LIKE ? ORDER BY order_code DESC LIMIT 1 FOR UPDATE');
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $next = $last ? ((int) substr($last, -4)) + 1 : 1;
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

/**
 * Places an order from the customer's cart.
 *
 * Everything happens inside ONE database transaction:
 *   1. lock the cart products and check the stock again on the server
 *   2. insert the order
 *   3. insert the order items (price is copied = price_at_purchase)
 *   4. reduce the stock
 *   5. insert the payment row and empty the cart
 * If anything fails, ROLLBACK is called, so we never get half-finished orders.
 */
function place_order(PDO $pdo, int $userId, string $method, bool $paymentDone): array
{
    if (!in_array($method, ['Online Payment', 'Cash at Store'], true)) {
        throw new AppException('Please choose a payment method.');
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'SELECT c.product_id, c.quantity, p.product_name, p.price, p.stock_quantity, p.status
               FROM cart c
               JOIN products p ON p.product_id = c.product_id
              WHERE c.user_id = ?
              ORDER BY p.product_id
              FOR UPDATE'
        );
        $st->execute([$userId]);
        $lines = $st->fetchAll();

        if (!$lines) {
            throw new AppException('Your cart is empty.');
        }

        // ---- 1. validate on the server (never trust JavaScript only)
        $total = 0.0;
        foreach ($lines as $l) {
            $qty   = (int) $l['quantity'];
            $stock = (int) $l['stock_quantity'];
            $price = (float) $l['price'];

            if ($l['status'] !== 'active') {
                throw new AppException($l['product_name'] . ' is no longer available. Please remove it from your cart.');
            }
            if ($qty < 1) {
                throw new AppException('Invalid quantity for ' . $l['product_name'] . '.');
            }
            if ($price <= 0) {
                throw new AppException('Invalid price for ' . $l['product_name'] . '.');
            }
            if ($stock <= 0) {
                throw new AppException($l['product_name'] . ' is out of stock.');
            }
            if ($qty > $stock) {
                throw new AppException('Only ' . $stock . ' items available for ' . $l['product_name'] . '.');
            }
            $total += round($price * $qty, 2);
        }
        $total = round($total, 2);

        // ---- 2. insert order
        $code = generate_order_code($pdo);
        $payStatus = $paymentDone ? 'Paid' : 'Pending';
        $ins = $pdo->prepare(
            "INSERT INTO orders (order_code, user_id, total_amount, payment_method, payment_status, order_status)
             VALUES (?, ?, ?, ?, ?, 'Pending')
             RETURNING order_id"
        );
        $ins->execute([$code, $userId, $total, $method, $payStatus]);
        $orderId = (int) $ins->fetchColumn();

        // ---- 3 + 4. items and stock
        $insItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name_snapshot, price_at_purchase, quantity, subtotal)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $updStock = $pdo->prepare(
            'UPDATE products SET stock_quantity = stock_quantity - ?
              WHERE product_id = ? AND stock_quantity >= ?'
        );
        foreach ($lines as $l) {
            $qty   = (int) $l['quantity'];
            $price = (float) $l['price'];
            $insItem->execute([$orderId, $l['product_id'], $l['product_name'], $price, $qty, round($price * $qty, 2)]);

            $updStock->execute([$qty, $l['product_id'], $qty]);
            if ($updStock->rowCount() !== 1) {
                throw new AppException('Sorry, ' . $l['product_name'] . ' just went out of stock.');
            }
        }

        // ---- 5. payment row + clear cart
        $ref = null;
        if ($paymentDone) {
            $ref = 'DEMO' . strtoupper(bin2hex(random_bytes(4)));
        }
        $pay = $pdo->prepare(
            'INSERT INTO payments (order_id, amount, method, status, transaction_ref, paid_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $pay->execute([$orderId, $total, $method, $payStatus, $ref, $paymentDone ? date('Y-m-d H:i:s') : null]);

        $pdo->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$userId]);

        $pdo->commit();
        return ['order_id' => $orderId, 'order_code' => $code, 'total' => $total];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();       // undo everything - no half-finished order
        }
        throw $ex;
    }
}

/**
 * Changes the status of an order (used by admin and by the customer cancel button).
 * $actor is 'admin' or 'customer'.
 * Returns a success message. Throws AppException for invalid changes.
 */
function change_order_status(PDO $pdo, int $orderId, string $newStatus, string $actor = 'admin', int $userId = 0): string
{
    $transitions = order_transitions();
    if (!isset($transitions[$newStatus])) {
        throw new AppException('Invalid order status.');
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM orders WHERE order_id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $order = $st->fetch();
        if (!$order) {
            throw new AppException('Order not found.');
        }
        if ($actor === 'customer') {
            if ((int) $order['user_id'] !== $userId) {
                throw new AppException('Order not found.');
            }
            if ($order['order_status'] !== 'Pending' || $newStatus !== 'Cancelled') {
                throw new AppException('This order can no longer be cancelled online. Please contact the store.');
            }
        }

        $current = $order['order_status'];
        if (!in_array($newStatus, $transitions[$current] ?? [], true)) {
            throw new AppException('Cannot change status from "' . $current . '" to "' . $newStatus . '".');
        }

        $newPayStatus = $order['payment_status'];

        // Rejected / Cancelled: put the products back into stock
        if ($newStatus === 'Rejected' || $newStatus === 'Cancelled') {
            $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
            $items->execute([$orderId]);
            $restock = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?');
            foreach ($items->fetchAll() as $it) {
                $restock->execute([(int) $it['quantity'], (int) $it['product_id']]);
            }
            $newPayStatus = ($order['payment_status'] === 'Paid') ? 'Refunded' : 'Cancelled';
        }

        // Cash at store: the customer pays at the counter when collecting
        if ($newStatus === 'Collected' && $order['payment_method'] === 'Cash at Store' && $order['payment_status'] === 'Pending') {
            $newPayStatus = 'Paid';
        }

        $pdo->prepare('UPDATE orders SET order_status = ?, payment_status = ? WHERE order_id = ?')
            ->execute([$newStatus, $newPayStatus, $orderId]);

        if ($newPayStatus !== $order['payment_status']) {
            $pdo->prepare('UPDATE payments SET status = ?, paid_at = CASE WHEN ? = \'Paid\' THEN CURRENT_TIMESTAMP ELSE paid_at END WHERE order_id = ?')
                ->execute([$newPayStatus, $newPayStatus, $orderId]);
        }

        $pdo->commit();
        return 'Order ' . $order['order_code'] . ' is now "' . $newStatus . '".';
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Finds one order by its code. If $userId > 0 the order must belong to that customer. */
function find_order(PDO $pdo, string $code, int $userId = 0)
{
    $sql = 'SELECT o.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email
              FROM orders o JOIN users u ON u.user_id = o.user_id
             WHERE o.order_code = ?';
    $params = [$code];
    if ($userId > 0) {
        $sql .= ' AND o.user_id = ?';
        $params[] = $userId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetch();
}

/** Items of an order (with the price the customer paid, plus current stock info). */
function find_order_items(PDO $pdo, int $orderId): array
{
    $st = $pdo->prepare(
        'SELECT oi.*, p.status AS product_status, p.stock_quantity AS current_stock, p.image
           FROM order_items oi
           JOIN products p ON p.product_id = oi.product_id
          WHERE oi.order_id = ?
          ORDER BY oi.order_item_id'
    );
    $st->execute([$orderId]);
    return $st->fetchAll();
}

// Icons are used on almost every page, so load them here.
require_once __DIR__ . '/icons.php';
