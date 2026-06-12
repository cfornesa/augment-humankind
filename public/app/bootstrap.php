<?php

declare(strict_types=1);

// public/index.php loads .env (loadEnvFile) and starts the session before
// dispatching here, so this only guards against a double session_start().
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/config/database.php';
