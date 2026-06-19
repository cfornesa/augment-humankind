<?php

declare(strict_types=1);

function operational_secret_seed(): string
{
    static $seed = null;
    if (is_string($seed) && $seed !== '') {
        return $seed;
    }

    $candidates = [
        $_ENV['AI_SETTINGS_ENCRYPTION_KEY'] ?? getenv('AI_SETTINGS_ENCRYPTION_KEY') ?: '',
        $_ENV['PLATFORM_AI_SETTINGS_ENCRYPTION_KEY'] ?? getenv('PLATFORM_AI_SETTINGS_ENCRYPTION_KEY') ?: '',
        $_ENV['CRON_SECRET'] ?? getenv('CRON_SECRET') ?: '',
        $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '',
        ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '') . '|' . ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: ''),
        __DIR__,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '') {
            $seed = hash('sha256', $candidate, true);
            return $seed;
        }
    }

    $seed = hash('sha256', 'operational-fallback-seed', true);
    return $seed;
}

function operational_subject_hash(string $scope, string $subject): string
{
    return hash_hmac('sha256', $scope . '|' . $subject, operational_secret_seed());
}

function operational_request_fingerprint(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
    return $ip . '|' . $ua;
}

function operational_request_subject_hash(string $scope): string
{
    return operational_subject_hash($scope, operational_request_fingerprint());
}

function audit_log_table_exists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute(['audit_log_events']);
        $exists = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        $exists = false;
    }

    return $exists;
}

function audit_log_redact_value(string $key, mixed $value): mixed
{
    $normalizedKey = strtolower($key);
    foreach (['token', 'secret', 'password', 'api_key', 'authorization', 'code', 'prompt', 'content', 'email_body', 'message_body'] as $sensitive) {
        if (str_contains($normalizedKey, $sensitive)) {
            return '[REDACTED]';
        }
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (strlen($trimmed) > 500) {
            return substr($trimmed, 0, 500) . '…';
        }
        return $trimmed;
    }

    if (is_array($value)) {
        return audit_log_redact_array($value);
    }

    return $value;
}

function audit_log_redact_array(array $metadata): array
{
    $sanitized = [];
    foreach ($metadata as $key => $value) {
        $stringKey = is_string($key) ? $key : (string) $key;
        $sanitized[$stringKey] = audit_log_redact_value($stringKey, $value);
    }
    return $sanitized;
}

function audit_log_event(
    string $eventType,
    string $scope,
    string $outcome,
    array $context = []
): void {
    if (!audit_log_table_exists()) {
        return;
    }

    $metadata = $context['metadata'] ?? [];
    if (!is_array($metadata)) {
        $metadata = ['value' => (string) $metadata];
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_log_events
                (event_type, scope, actor_admin_identity_id, subject_hash, target_type, target_id, outcome, http_status, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $eventType,
            $scope,
            isset($context['actor_admin_identity_id']) ? (int) $context['actor_admin_identity_id'] : null,
            $context['subject_hash'] ?? null,
            $context['target_type'] ?? null,
            isset($context['target_id']) ? (string) $context['target_id'] : null,
            $outcome,
            isset($context['http_status']) ? (int) $context['http_status'] : null,
            json_encode(audit_log_redact_array($metadata), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable) {
        // Logging must never break request handling.
    }
}
