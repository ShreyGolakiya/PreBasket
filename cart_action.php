<?php
/*
 * cart_action.php
 * ---------------------------------------------------------------
 * The ONE place that changes the cart: add, set, remove, clear, reorder.
 *
 * It answers in two ways:
 *   - normal form post  -> redirect back with a flash message
 *   - fetch() from JS   -> a small JSON reply (?ajax=1)
 *
 * Stock is ALWAYS checked here on the server, never only in JavaScript.
 */
require_once __DIR__ . '/includes/auth.php';

$isAjax = !empty($_POST['ajax']) || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
$back   = safe_redirect_target(post_str('redirect', 120), 'cart.php');

/** Sends the answer in the right format and stops. */
function cart_reply(bool $ok, string $message, string $type, string $back, bool $isAjax, array $extra = []): void
{
    if ($isAjax) {
        json_out(array_merge([
            'ok'         => $ok,
            'message'    => $message,
            'cart_count' => cart_count(true),
        ], $extra));
    }
    flash($ok ? $type : 'error', $message);
    redirect($back);
}

if (!is_post()) {
    redirect('cart.php');
}

if (!csrf_valid()) {
    cart_reply(false, 'Your session expired. Please refresh the page and try again.', 'error', $back, $isAjax);
}

if (!is_logged_in()) {
    if ($isAjax) {
        json_out(['ok' => false, 'login' => true, 'message' => 'Please login to add products to your cart.', 'cart_count' => 0]);
    }
    $_SESSION['after_login'] = $back;
    flash('info', 'Please login to use your cart.');
    redirect('login.php');
}

$pdo    = db();
$userId = current_user_id();
$action = post_str('action', 20);

try {
    switch ($action) {

        /* ---------------- add a product (or increase it) -------------- */
        case 'add': {
            $pid = post_int('product_id');
            $qty = post_int('quantity', 1);
            if ($qty < 1) { $qty = 1; }
            if ($qty > 99) { $qty = 99; }

            $st = $pdo->prepare('SELECT product_name, stock_quantity, status FROM products WHERE product_id = ?');
            $st->execute([$pid]);
            $p = $st->fetch();

            if (!$p || $p['status'] !== 'active') {
                cart_reply(false, 'That product is not available.', 'error', $back, $isAjax);
            }
            $stock = (int) $p['stock_quantity'];
            if ($stock <= 0) {
                cart_reply(false, $p['product_name'] . ' is out of stock.', 'error', $back, $isAjax);
            }

            // how many are already in the cart?
            $cur = $pdo->prepare('SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?');
            $cur->execute([$userId, $pid]);
            $have = (int) $cur->fetchColumn();
            $want = $have + $qty;

            if ($want > $stock) {
                $want = $stock;
                if ($have >= $stock) {
                    cart_reply(false, 'Only ' . $stock . ' items available for ' . $p['product_name'] . '.', 'error', $back, $isAjax);
                }
            }

            // one row per (user, product) - the UNIQUE key makes this safe
            $pdo->prepare(
                'INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
                 ON CONFLICT (user_id, product_id) DO UPDATE SET quantity = EXCLUDED.quantity'
            )->execute([$userId, $pid, $want]);

            $msg = $want < $have + $qty
                ? 'Added. Only ' . $stock . ' items available for ' . $p['product_name'] . '.'
                : $p['product_name'] . ' added to your cart.';
            cart_reply(true, $msg, 'success', $back, $isAjax);
            break;
        }

        /* ---------------- set an exact quantity ----------------------- */
        case 'set': {
            $pid = post_int('product_id');
            $qty = post_int('quantity', 1);

            if ($qty < 1) {
                $pdo->prepare('DELETE FROM cart WHERE user_id = ? AND product_id = ?')->execute([$userId, $pid]);
                cart_reply(true, 'Item removed from your cart.', 'info', $back, $isAjax);
            }

            $st = $pdo->prepare('SELECT product_name, stock_quantity, status FROM products WHERE product_id = ?');
            $st->execute([$pid]);
            $p = $st->fetch();
            if (!$p || $p['status'] !== 'active') {
                cart_reply(false, 'That product is not available.', 'error', $back, $isAjax);
            }

            $stock = (int) $p['stock_quantity'];
            if ($stock <= 0) {
                cart_reply(false, $p['product_name'] . ' is out of stock.', 'error', $back, $isAjax);
            }

            $capped = false;
            if ($qty > $stock) { $qty = $stock; $capped = true; }

            $upd = $pdo->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?');
            $upd->execute([$qty, $userId, $pid]);

            cart_reply(
                true,
                $capped ? 'Only ' . $stock . ' items available for ' . $p['product_name'] . '.' : 'Cart updated.',
                $capped ? 'warning' : 'success',
                $back, $isAjax
            );
            break;
        }

        /* ---------------- remove one line ----------------------------- */
        case 'remove': {
            $pid = post_int('product_id');
            $pdo->prepare('DELETE FROM cart WHERE user_id = ? AND product_id = ?')->execute([$userId, $pid]);
            cart_reply(true, 'Item removed from your cart.', 'info', $back, $isAjax);
            break;
        }

        /* ---------------- empty the cart ------------------------------ */
        case 'clear': {
            $pdo->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$userId]);
            cart_reply(true, 'Your cart is now empty.', 'info', $back, $isAjax);
            break;
        }

        /* ---------------- fix every line to the stock available ------- */
        case 'fix': {
            $items = cart_items($userId);
            $fixed = 0;
            foreach ($items as $it) {
                $stock = (int) $it['stock_quantity'];
                if ($it['status'] !== 'active' || $stock <= 0) {
                    $pdo->prepare('DELETE FROM cart WHERE user_id = ? AND product_id = ?')
                        ->execute([$userId, $it['product_id']]);
                    $fixed++;
                } elseif ((int) $it['quantity'] > $stock) {
                    $pdo->prepare('UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?')
                        ->execute([$stock, $userId, $it['product_id']]);
                    $fixed++;
                }
            }
            cart_reply(true, $fixed ? 'Your cart has been updated to match the stock we have.' : 'Your cart was already fine.',
                'success', $back, $isAjax);
            break;
        }

        /* ---------------- quick reorder ------------------------------- */
        case 'reorder': {
            $orderId = post_int('order_id');

            // make sure the order really belongs to this customer
            $chk = $pdo->prepare('SELECT order_id, order_code FROM orders WHERE order_id = ? AND user_id = ?');
            $chk->execute([$orderId, $userId]);
            $order = $chk->fetch();
            if (!$order) {
                cart_reply(false, 'Order not found.', 'error', 'orders.php', $isAjax);
            }

            $items = $pdo->prepare(
                'SELECT oi.product_id, oi.quantity, oi.product_name_snapshot,
                        p.product_name, p.stock_quantity, p.status
                   FROM order_items oi
                   JOIN products p ON p.product_id = oi.product_id
                  WHERE oi.order_id = ?'
            );
            $items->execute([$orderId]);

            $added = [];
            $skipped = [];
            foreach ($items->fetchAll() as $it) {
                $stock = (int) $it['stock_quantity'];

                // unavailable products are NOT added, and are clearly reported
                if ($it['status'] !== 'active') {
                    $skipped[] = $it['product_name_snapshot'] . ' (no longer sold)';
                    continue;
                }
                if ($stock <= 0) {
                    $skipped[] = $it['product_name'] . ' (out of stock)';
                    continue;
                }

                $qty = min((int) $it['quantity'], $stock);
                $pdo->prepare(
                    'INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
                     ON CONFLICT (user_id, product_id) DO UPDATE SET quantity = LEAST(cart.quantity + EXCLUDED.quantity, ?)'
                )->execute([$userId, $it['product_id'], $qty, $stock]);

                $added[] = $it['product_name'] . ($qty < (int) $it['quantity'] ? ' (only ' . $qty . ' available)' : '');
            }

            if (!$added && $skipped) {
                flash('error', 'Nothing could be added: ' . implode(', ', $skipped) . '.');
                redirect('order_details.php?code=' . urlencode($order['order_code']));
            }

            flash('success', count($added) . ' item(s) from order ' . $order['order_code'] . ' added to your cart.');
            if ($skipped) {
                flash('warning', 'Not added: ' . implode(', ', $skipped) . '.');
            }
            redirect('cart.php');
            break;
        }

        default:
            cart_reply(false, 'Unknown cart action.', 'error', $back, $isAjax);
    }
} catch (AppException $ex) {
    cart_reply(false, $ex->getMessage(), 'error', $back, $isAjax);
} catch (Throwable $ex) {
    log_exception($ex);
    cart_reply(false, 'Something went wrong. Please try again.', 'error', $back, $isAjax);
}
