<?php

declare(strict_types=1);

const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

const SPAM_HONEYPOT_FIELD = 'website';
const SPAM_TIMETRAP_FIELD = 'form_rendered_at';
const SPAM_TIMETRAP_MIN_SECONDS = 3;

function recaptcha_site_key(): string
{
    return oauth_env('RECAPTCHA_SITE_KEY');
}

function recaptcha_secret_key(): string
{
    return oauth_env('RECAPTCHA_SECRET_KEY');
}

function recaptcha_score_threshold(): float
{
    $value = oauth_env('RECAPTCHA_SCORE_THRESHOLD');
    if ($value === '') {
        return 0.5;
    }

    $threshold = (float) $value;
    if ($threshold <= 0.0 || $threshold > 1.0) {
        return 0.5;
    }

    return $threshold;
}

/**
 * success=false/score=0.0/error='missing-input-response': empty token (no JS) -> flagged, not discarded.
 * success=false/score=0.0/error='not-configured': secret key unset -> flagged, not discarded (form still works pre-setup).
 * Transport failure or malformed response: fails open -> success=true/score=1.0/error='verification-unavailable'.
 * Otherwise: real Google response forwarded as-is (success/score/first error code).
 */
function recaptcha_verify(string $token, ?string $remoteIp = null): array
{
    if (trim($token) === '') {
        return ['success' => false, 'score' => 0.0, 'error' => 'missing-input-response'];
    }

    $secret = recaptcha_secret_key();
    if ($secret === '') {
        return ['success' => false, 'score' => 0.0, 'error' => 'not-configured'];
    }

    $params = [
        'secret'   => $secret,
        'response' => $token,
    ];
    if ($remoteIp !== null && $remoteIp !== '') {
        $params['remoteip'] = $remoteIp;
    }

    try {
        $result = oauth_http_request(
            'POST',
            RECAPTCHA_VERIFY_URL,
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query($params)
        );
    } catch (Throwable $error) {
        return ['success' => true, 'score' => 1.0, 'error' => 'verification-unavailable'];
    }

    $data = json_decode($result['body'], true);
    if (!is_array($data)) {
        return ['success' => true, 'score' => 1.0, 'error' => 'verification-unavailable'];
    }

    $errorCodes = $data['error-codes'] ?? null;

    return [
        'success' => (bool) ($data['success'] ?? false),
        'score'   => isset($data['score']) ? (float) $data['score'] : 0.0,
        'error'   => is_array($errorCodes) && $errorCodes !== [] ? (string) $errorCodes[0] : null,
    ];
}

function spam_honeypot_tripped(array $post): bool
{
    return !empty($post[SPAM_HONEYPOT_FIELD]);
}

function spam_timetrap_tripped(array $post): bool
{
    $renderedAt = (int) ($post[SPAM_TIMETRAP_FIELD] ?? 0);
    if ($renderedAt <= 0) {
        return true;
    }

    return (time() - $renderedAt) < SPAM_TIMETRAP_MIN_SECONDS;
}
