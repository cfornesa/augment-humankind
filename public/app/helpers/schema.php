<?php

declare(strict_types=1);

function ah_table_exists(string $tableName): bool
{
    static $cache = [];
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$tableName]);
        return $cache[$tableName] = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$tableName] = false;
    }
}

function ah_column_exists(string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = $tableName . '.' . $columnName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare(
            'SELECT 1
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
              LIMIT 1'
        );
        $stmt->execute([$tableName, $columnName]);
        return $cache[$key] = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function ah_existing_columns(string $tableName, array $columns): array
{
    $existing = [];
    foreach ($columns as $columnName) {
        if (ah_column_exists($tableName, $columnName)) {
            $existing[] = $columnName;
        }
    }
    return $existing;
}
