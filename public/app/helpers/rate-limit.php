<?php

declare(strict_types=1);

function rate_limit_table_exists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute(['request_rate_limits']);
        $exists = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        $exists = false;
    }

    return $exists;
}

function rate_limit_rule(string $scope): array
{
    return match ($scope) {
        'admin_oauth_start' => ['max_requests' => 8, 'window_seconds' => 300],
        'admin_oauth_callback' => ['max_requests' => 12, 'window_seconds' => 300],
        'magic_link_request' => ['max_requests' => 10, 'window_seconds' => 3600],
        'magic_link_email' => ['max_requests' => 3, 'window_seconds' => 900],
        'contact_submit' => ['max_requests' => 5, 'window_seconds' => 3600],
        'ai_process_text' => ['max_requests' => 12, 'window_seconds' => 900],
        'ai_describe_image' => ['max_requests' => 12, 'window_seconds' => 900],
        'ai_generate_piece' => ['max_requests' => 4, 'window_seconds' => 900],
        'ai_refine_piece' => ['max_requests' => 6, 'window_seconds' => 900],
        default => ['max_requests' => 10, 'window_seconds' => 300],
    };
}

function rate_limit_subject_for_scope(string $scope, ?int $adminIdentityId = null): string
{
    if ($adminIdentityId !== null && $adminIdentityId > 0) {
        return operational_subject_hash($scope, 'admin:' . $adminIdentityId);
    }

    return operational_request_subject_hash($scope);
}

function rate_limit_consume(string $scope, string $subjectHash): array
{
    $rule = rate_limit_rule($scope);
    $windowSeconds = (int) $rule['window_seconds'];
    $maxRequests = (int) $rule['max_requests'];
    $windowStartedAt = (int) (floor(time() / $windowSeconds) * $windowSeconds);
    $windowStart = gmdate('Y-m-d H:i:s', $windowStartedAt);
    $now = gmdate('Y-m-d H:i:s');

    if (!rate_limit_table_exists()) {
        return [
            'allowed' => true,
            'scope' => $scope,
            'subject_hash' => $subjectHash,
            'request_count' => 0,
            'max_requests' => $maxRequests,
            'window_seconds' => $windowSeconds,
            'retry_after' => 0,
        ];
    }

    try {
        db()->prepare(
            'DELETE FROM request_rate_limits WHERE last_request_at < DATE_SUB(UTC_TIMESTAMP(3), INTERVAL 2 DAY)'
        )->execute();

        $stmt = db()->prepare(
            'INSERT INTO request_rate_limits
                (scope, subject_hash, window_start, request_count, first_request_at, last_request_at)
             VALUES (?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE
                request_count = request_count + 1,
                last_request_at = VALUES(last_request_at),
                updated_at = CURRENT_TIMESTAMP(3)'
        );
        $stmt->execute([$scope, $subjectHash, $windowStart, $now, $now]);

        $select = db()->prepare(
            'SELECT request_count FROM request_rate_limits WHERE scope = ? AND subject_hash = ? AND window_start = ? LIMIT 1'
        );
        $select->execute([$scope, $subjectHash, $windowStart]);
        $count = (int) ($select->fetchColumn() ?: 0);
    } catch (Throwable) {
        return [
            'allowed' => true,
            'scope' => $scope,
            'subject_hash' => $subjectHash,
            'request_count' => 0,
            'max_requests' => $maxRequests,
            'window_seconds' => $windowSeconds,
            'retry_after' => 0,
        ];
    }

    $retryAfter = max(1, ($windowStartedAt + $windowSeconds) - time());
    return [
        'allowed' => $count <= $maxRequests,
        'scope' => $scope,
        'subject_hash' => $subjectHash,
        'request_count' => $count,
        'max_requests' => $maxRequests,
        'window_seconds' => $windowSeconds,
        'retry_after' => $count > $maxRequests ? $retryAfter : 0,
    ];
}
