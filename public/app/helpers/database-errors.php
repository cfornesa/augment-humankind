<?php

declare(strict_types=1);

function ah_is_pdo_connection_failure(PDOException $e): bool
{
    $code = (string) $e->getCode();
    $message = strtolower($e->getMessage());

    if (in_array($code, ['2002', '2003', '2006', '1044', '1045', '1049'], true)) {
        return true;
    }

    foreach ([
        'getaddrinfo',
        'connection refused',
        'no route to host',
        'access denied',
        'unknown database',
        'server has gone away',
        'no such file or directory',
        'can\'t connect to mysql server',
    ] as $needle) {
        if (str_contains($message, $needle)) {
            return true;
        }
    }

    return false;
}
