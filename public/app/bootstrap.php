<?php

declare(strict_types=1);

// public/index.php loads .env (loadEnvFile) and starts the session before
// dispatching here, so this only guards against a double session_start().
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require __DIR__ . '/config/database.php';
