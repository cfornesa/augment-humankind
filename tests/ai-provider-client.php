<?php
/**
 * Tests for AiProviderClient — specifically the refine-vs-generation behavior
 * switches added to fix AI Refine's silent failures on verbose pieces.
 * Run with: php tests/ai-provider-client.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../public/vendor/autoload.php';
require_once __DIR__ . '/../public/app/lib/ai/AiProviderClient.php';

use App\Lib\Ai\AiProviderClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

$passed = 0;
$failed = 0;

function test(string $label, callable $fn): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  ✓ {$label}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "  ✗ {$label}: {$e->getMessage()}\n";
        $failed++;
    }
}

function assert_true(bool $condition, string $msg = ''): void {
    if (!$condition) {
        throw new RuntimeException($msg ?: 'Expected condition to be true');
    }
}

function assert_eq($actual, $expected, string $msg = ''): void {
    if ($actual !== $expected) {
        throw new RuntimeException($msg . ' Expected: ' . var_export($expected, true) . ' Got: ' . var_export($actual, true));
    }
}

/**
 * Builds an AiProviderClient with a mock Guzzle handler that captures the
 * outgoing request into $container (an out-parameter, populated only once
 * generate() is actually called) and returns a canned chat-completions-style
 * response, so we can inspect exactly what gets sent without a real HTTP call.
 */
function buildClientWithCapturedRequest(array $responseBody, array &$container, int $status = 200): AiProviderClient {
    $container = [];
    $history = Middleware::history($container);
    $mock = new MockHandler([new Response($status, [], json_encode($responseBody))]);
    $stack = HandlerStack::create($mock);
    $stack->push($history);
    $httpClient = new Client(['handler' => $stack]);
    return new AiProviderClient('opencode-go', 'minimax-m3', null, 'fake-key', $httpClient);
}

echo "=== AiProviderClient::generate() — suppressPlanningPreamble ===\n";

test('Default (true) preserves the existing "skip planning notes" instruction for generation', function () {
    $container = [];
    $aiClient = buildClientWithCapturedRequest([
        'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
    ], $container);
    $aiClient->generate('SYSTEM PROMPT', 'USER PROMPT');
    $body = json_decode((string) $container[0]['request']->getBody(), true);
    $systemContent = $body['messages'][0]['content'];
    assert_true(str_contains($systemContent, 'Do not output'), 'Expected the suppression instruction to still be present by default');
    assert_true(str_contains($systemContent, 'planning notes'));
});

test('suppressPlanningPreamble=false omits the instruction for refine', function () {
    // This is the actual fix: AI Refine requires a PLAN section, so the old
    // "do not output planning notes" instruction (written for full-file
    // generation) must not be sent alongside a refine request.
    $container = [];
    $aiClient = buildClientWithCapturedRequest([
        'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
    ], $container);
    $aiClient->generate('SYSTEM PROMPT', 'USER PROMPT', suppressPlanningPreamble: false);
    $body = json_decode((string) $container[0]['request']->getBody(), true);
    $systemContent = $body['messages'][0]['content'];
    assert_eq($systemContent, 'SYSTEM PROMPT', 'Expected the system prompt to be sent unmodified');
});

echo "\n=== AiProviderClient::generate() — maxTokensOverride ===\n";

test('Default max_tokens for an opencode vendor is 16384', function () {
    $container = [];
    $aiClient = buildClientWithCapturedRequest([
        'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
    ], $container);
    $aiClient->generate('SYSTEM', 'USER');
    $body = json_decode((string) $container[0]['request']->getBody(), true);
    assert_eq($body['max_tokens'], 16384);
});

test('maxTokensOverride raises the ceiling for refine', function () {
    $container = [];
    $aiClient = buildClientWithCapturedRequest([
        'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
    ], $container);
    $aiClient->generate('SYSTEM', 'USER', suppressPlanningPreamble: false, maxTokensOverride: 24576);
    $body = json_decode((string) $container[0]['request']->getBody(), true);
    assert_eq($body['max_tokens'], 24576);
});

echo "\n=== AiProviderClient::generate() — finishReason ===\n";

test('finishReason is extracted and returned on success', function () {
    $container = [];
    $aiClient = buildClientWithCapturedRequest([
        'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'length']],
    ], $container);
    $res = $aiClient->generate('SYSTEM', 'USER');
    assert_eq($res['finishReason'], 'length');
});

test('finishReason is null when the provider omits it', function () {
    $container = [];
    $aiClient = buildClientWithCapturedRequest([
        'choices' => [['message' => ['content' => 'ok']]],
    ], $container);
    $res = $aiClient->generate('SYSTEM', 'USER');
    assert_eq($res['finishReason'], null);
});

echo "\n=== AiProviderClient::finishReasonMeansTruncated ===\n";

test('Recognizes chat-completions "length" as truncated', function () {
    assert_true(AiProviderClient::finishReasonMeansTruncated('length'));
});

test('Recognizes Anthropic "max_tokens" as truncated', function () {
    assert_true(AiProviderClient::finishReasonMeansTruncated('max_tokens'));
});

test('Recognizes Google "MAX_TOKENS" as truncated (case-insensitive)', function () {
    assert_true(AiProviderClient::finishReasonMeansTruncated('MAX_TOKENS'));
});

test('A normal completion ("stop") is not truncated', function () {
    assert_true(!AiProviderClient::finishReasonMeansTruncated('stop'));
});

test('Null finish reason is not treated as truncated', function () {
    assert_true(!AiProviderClient::finishReasonMeansTruncated(null));
});

echo "\n=== Results ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
echo "All AiProviderClient tests passed!\n";
