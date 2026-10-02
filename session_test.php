<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['test'])) {
    $_SESSION['test'] = 'HELLO-' . time();
}

echo '<h2>PHP Session Test</h2>';
echo '<p>Session ID: ' . htmlspecialchars(session_id()) . '</p>';
echo '<p>Test value: ' . htmlspecialchars($_SESSION['test']) . '</p>';
