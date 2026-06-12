<?php

declare(strict_types=1);

// Load .env file from project root
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $cleanValue = rtrim(preg_replace('/[^\x20-\x7E]/', '', trim($value)), '%');
        $_ENV[trim($key)] = $cleanValue;
        putenv(trim($key) . '=' . $cleanValue);
    }
}

session_start();

// Simple PSR-0-style autoloader for app/ classes
spl_autoload_register(function (string $class): void {
    $base = __DIR__;
    $map = [
        'controllers\\' => $base . '/controllers/',
        'models\\'      => $base . '/models/',
        'helpers\\'     => $base . '/helpers/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . substr($class, strlen($prefix)) . '.php';
            if (file_exists($file)) {
                require $file;
            }
            return;
        }
    }
});

require __DIR__ . '/config/database.php';
