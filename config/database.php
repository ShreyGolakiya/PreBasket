<?php

define('DB_HOST', 'aws-0-ap-northeast-1.pooler.supabase.com');
define('DB_PORT', '6543');
define('DB_NAME', 'postgres');
define('DB_USER', 'postgres.tokffzxndimbpxyojccx');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('APP_DEBUG', false);

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {

        $dsn = 'pgsql:host=' . DB_HOST .
               ';port=' . DB_PORT .
               ';dbname=' . DB_NAME .
               ';sslmode=require';

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            // Reuse the Apache/PHP PostgreSQL connection when possible.
            // This is especially helpful because Supabase is a remote database.
            PDO::ATTR_PERSISTENT => true,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // India time
            $pdo->exec("SET TIME ZONE '+05:30'");

        } catch (PDOException $ex) {

            error_log('[QuickCart] Database connection failed: ' . $ex->getMessage());

            die('Database connection failed: ' . $ex->getMessage());
        }
    }

    return $pdo;
}