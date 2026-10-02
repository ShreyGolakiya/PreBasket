<?php
/*
 * includes/auth.php
 * ---------------------------------------------------------------
 * Customer login helpers.
 * Include this file on any page that only a logged-in CUSTOMER may open.
 */
require_once __DIR__ . '/functions.php';

/**
 * Stops the page if nobody is logged in.
 * The page the user wanted is remembered, so after login we can send them back.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['after_login'] = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php')
            . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        flash('info', 'Please login to continue.');
        redirect('login.php');
    }
}

/** Saves the login state in the session. */
function login_user(array $user): void
{
    session_regenerate_id(true);          // new session id = safer
    $_SESSION['user_id']   = (int) $user['user_id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
}

function logout_user(): void
{
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['checkout']);
}

/** The logged-in customer's row (or null). */
function current_user(): ?array
{
    static $user = null;
    if ($user === null && is_logged_in()) {
        $st = db()->prepare('SELECT * FROM users WHERE user_id = ?');
        $st->execute([current_user_id()]);
        $row = $st->fetch();
        $user = $row ?: null;
        if (!$row) {
            logout_user();               // account was deleted
        }
    }
    return $user ?: null;
}

/* ------------------------------------------------------------------
 * Validation helpers used by register.php and login.php
 * ------------------------------------------------------------------ */
function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 150;
}

function valid_phone(string $phone): bool
{
    return (bool) preg_match('/^[0-9]{10}$/', $phone);
}

function valid_name(string $name): bool
{
    return mb_strlen($name) >= 3 && mb_strlen($name) <= 100 && preg_match('/^[\p{L} .\'-]+$/u', $name);
}

function password_problem(string $pw): ?string
{
    if (mb_strlen($pw) < 6) {
        return 'Password must be at least 6 characters long.';
    }
    if (mb_strlen($pw) > 72) {
        return 'Password is too long (maximum 72 characters).';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw)) {
        return 'Password must contain at least one letter and one number.';
    }
    return null;
}
