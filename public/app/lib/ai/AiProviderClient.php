<?php

declare(strict_types=1);

namespace App\Lib\Ai;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;
use Throwable;

class AiProviderClient
{
    private string $vendor;
    private string $model;
    private ?string $endpointKind;
    private string $apiKey;
    private Client $client;

    public function __construct(string $vendor, string $model, ?string $endpointKind, string $apiKey, ?Client $client = null)
    {
        $this->vendor = $vendor;
        $this->model = $model;
        $this->endpointKind = $endpointKind ? trim($endpointKind) : null;
        if ($this->endpointKind === '') {
            $this->endpointKind = null;
        }
        $this->apiKey = $apiKey;
        $this->client = $client ?? new Client([
            'timeout' => 120.0,
            'connect_timeout' => 10.0,
        ]);
    }

    /**
     * Executes the generation request against the resolved endpoint.
     * Returns an array with structure:
     * [
     *     'ok' => bool,
     *     'text' => ?string, // The extracted text on success
     *     'error' => ?string, // Mapped error message
     *     'url' => string, // Resolved URL
     *     'kind' => string, // Resolved transport kind
     *     'status' => ?int, // Upstream HTTP status code (if available)
     *     'rawResponse' => ?string // Preview of the raw response
     * ]
     */
    public function generate(string $systemPrompt, string $userPrompt): array
    {
        try {
            $attempt = $this->getTransportAttempt();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'text' => null,
                'error' => 'Endpoint resolution failed: ' . $e->getMessage(),
                'url' => '',
                'kind' => 'unknown',
                'status' => null,
                'rawResponse' => null
            ];
        }

        $url = $attempt['url'];
        $kind = $attempt['kind'];
        $normalizedModel = $this->normalizeModelForProvider();

        $headers = [
            'Content-Type' => 'application/json',
        ];
        $body = [];

        // Build request per transport kind
        if ($kind === 'chat-completions') {
            $isDeepSeek = ($this->vendor === 'deepseek' || str_contains($this->model, 'deepseek'));
            $isOpencode = ($this->vendor === 'opencode-zen' || $this->vendor === 'opencode-go');
            $shouldDisableThinking = ($isDeepSeek || $isOpencode);

            $finalSystemPrompt = $systemPrompt;
            if ($isOpencode) {
                $finalSystemPrompt .= ' CRITICAL: Do not output <think>, reasoning, analysis, planning notes, explanations, or prose. Output only the required fenced HTML, CSS, and JavaScript code blocks.';
            }

            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            if ($this->vendor === 'openrouter') {
                $siteUrl = $_ENV['PUBLIC_SITE_URL'] ?? getenv('PUBLIC_SITE_URL') ?? '';
                if ($siteUrl !== '') {
                    $headers['HTTP-Referer'] = trim($siteUrl);
                }
                $siteTitle = $_ENV['SITE_TITLE'] ?? getenv('SITE_TITLE') ?? '';
                if ($siteTitle !== '') {
                    $headers['X-OpenRouter-Title'] = trim($siteTitle);
                }
            }

            $body = [
                'model' => $normalizedModel,
                'max_tokens' => $isDeepSeek ? 12000 : 8192,
                'messages' => [
                    ['role' => 'system', 'content' => $finalSystemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ]
            ];

            if ($shouldDisableThinking) {
                $body['thinking'] = ['type' => 'disabled'];
            }
        } elseif ($kind === 'google-generate-content') {
            $modelPath = str_starts_with($normalizedModel, 'models/') ? substr($normalizedModel, 7) : $normalizedModel;
            $url = rtrim($url, '/') . '/' . $modelPath . ':generateContent?key=' . urlencode($this->apiKey);

            $body = [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $userPrompt]]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 8192
                ]
            ];
        } elseif ($kind === 'openai-responses') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            $body = [
                'model' => $normalizedModel,
                'instructions' => $systemPrompt,
                'input' => $userPrompt
            ];
        } elseif ($kind === 'anthropic-messages') {
            $headers['x-api-key'] = $this->apiKey;
            $headers['anthropic-version'] = '2023-06-01';

            $body = [
                'model' => $normalizedModel,
                'max_tokens' => 8192,
                'system' => $systemPrompt . ' CRITICAL: Skip all internal chain-of-thought, reasoning steps, or step-by-step planning. Output the three requested code blocks directly and immediately to prevent gateway timeouts.',
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt]
                ]
            ];
        }

        try {
            $response = $this->client->post($url, [
                'headers' => $headers,
                'json' => $body,
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            $rawText = (string) $response->getBody();
            $json = json_decode($rawText, true);

            if ($status < 200 || $status >= 300) {
                $errorMsg = $this->readErrorMessage($json) ?? "Provider request failed with status {$status}";
                return [
                    'ok' => false,
                    'text' => null,
                    'error' => $errorMsg,
                    'url' => $url,
                    'kind' => $kind,
                    'status' => $status,
                    'rawResponse' => substr($rawText, 0, 1200)
                ];
            }

            // Extract text per transport kind
            $extractedText = null;
            if ($kind === 'chat-completions') {
                $extractedText = $this->extractChatCompletionText($json);
            } elseif ($kind === 'google-generate-content') {
                $extractedText = $this->extractGoogleText($json);
            } elseif ($kind === 'openai-responses') {
                $extractedText = $this->extractOpenAiResponsesText($json);
            } elseif ($kind === 'anthropic-messages') {
                $extractedText = $this->extractAnthropicText($json);
            }

            if ($extractedText === null || trim($extractedText) === '') {
                return [
                    'ok' => false,
                    'text' => null,
                    'error' => 'The AI provider returned an empty or unusable response.',
                    'url' => $url,
                    'kind' => $kind,
                    'status' => $status,
                    'rawResponse' => substr($rawText, 0, 1200)
                ];
            }

            return [
                'ok' => true,
                'text' => $extractedText,
                'error' => null,
                'url' => $url,
                'kind' => $kind,
                'status' => $status,
                'rawResponse' => substr($rawText, 0, 1200)
            ];

        } catch (GuzzleException $e) {
            $status = null;
            if ($e instanceof RequestException && $e->getResponse() !== null) {
                $status = $e->getResponse()->getStatusCode();
            }
            return [
                'ok' => false,
                'text' => null,
                'error' => 'HTTP request failed: ' . $e->getMessage(),
                'url' => $url,
                'kind' => $kind,
                'status' => $status,
                'rawResponse' => null
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'text' => null,
                'error' => 'Unexpected error: ' . $e->getMessage(),
                'url' => $url,
                'kind' => $kind,
                'status' => null,
                'rawResponse' => null
            ];
        }
    }

    private function getTransportAttempt(): array
    {
        switch ($this->vendor) {
            case 'openrouter':
                return [
                    'kind' => 'chat-completions',
                    'url' => 'https://openrouter.ai/api/v1/chat/completions',
                    'endpointFamily' => 'chat_completions'
                ];
            case 'deepseek':
                return [
                    'kind' => 'chat-completions',
                    'url' => 'https://api.deepseek.com/chat/completions',
                    'endpointFamily' => 'chat_completions'
                ];
            case 'google':
                return [
                    'kind' => 'google-generate-content',
                    'url' => 'https://generativelanguage.googleapis.com/v1beta/models',
                    'endpointFamily' => 'generate_content'
                ];
            case 'opencode-zen':
                return $this->getOpencodeZenTransportAttempt();
            case 'opencode-go':
                return $this->getOpencodeGoTransportAttempt();
            case 'mistral':
            case 'mistral-vibe':
                return [
                    'kind' => 'chat-completions',
                    'url' => 'https://api.mistral.ai/v1/chat/completions',
                    'endpointFamily' => 'chat_completions'
                ];
            default:
                throw new RuntimeException("Unknown AI vendor: {$this->vendor}");
        }
    }

    private function getOpencodeZenTransportAttempt(): array
    {
        $kind = $this->endpointKind;
        $model = $this->model;

        if ($kind === 'openai-responses') {
            return ['kind' => 'openai-responses', 'url' => 'https://opencode.ai/zen/v1/responses', 'endpointFamily' => 'responses'];
        }
        if ($kind === 'anthropic-messages') {
            return ['kind' => 'anthropic-messages', 'url' => 'https://opencode.ai/zen/v1/messages', 'endpointFamily' => 'messages'];
        }
        if ($kind === 'google-generate' || $kind === 'google-generate-content') {
            return ['kind' => 'google-generate-content', 'url' => 'https://opencode.ai/zen/v1/models', 'endpointFamily' => 'generate_content'];
        }
        if ($kind === 'chat-completions') {
            return ['kind' => 'chat-completions', 'url' => 'https://opencode.ai/zen/v1/chat/completions', 'endpointFamily' => 'chat_completions'];
        }

        if (str_starts_with($model, 'gpt-')) {
            return ['kind' => 'openai-responses', 'url' => 'https://opencode.ai/zen/v1/responses', 'endpointFamily' => 'responses'];
        }
        if (str_starts_with($model, 'claude-')) {
            return ['kind' => 'anthropic-messages', 'url' => 'https://opencode.ai/zen/v1/messages', 'endpointFamily' => 'messages'];
        }
        if (str_starts_with($model, 'gemini-')) {
            return ['kind' => 'google-generate-content', 'url' => 'https://opencode.ai/zen/v1/models', 'endpointFamily' => 'generate_content'];
        }
        if ($this->isPrefixOf($model, ['minimax-', 'glm-', 'kimi-', 'big-pickle', 'qwen', 'nemotron-'])) {
            return ['kind' => 'chat-completions', 'url' => 'https://opencode.ai/zen/v1/chat/completions', 'endpointFamily' => 'chat_completions'];
        }

        throw new RuntimeException("Unknown OpenCode Zen model slug: {$model}");
    }

    private function getOpencodeGoTransportAttempt(): array
    {
        $kind = $this->endpointKind;
        $model = $this->model;

        if ($kind === 'anthropic-messages') {
            return ['kind' => 'anthropic-messages', 'url' => 'https://opencode.ai/zen/go/v1/messages', 'endpointFamily' => 'messages'];
        }
        if ($kind === 'chat-completions') {
            return ['kind' => 'chat-completions', 'url' => 'https://opencode.ai/zen/go/v1/chat/completions', 'endpointFamily' => 'chat_completions'];
        }

        if ($this->isOpencodeGoChatCompletionsModel($model)) {
            return ['kind' => 'chat-completions', 'url' => 'https://opencode.ai/zen/go/v1/chat/completions', 'endpointFamily' => 'chat_completions'];
        }
        if ($this->isOpencodeGoAnthropicModel($model)) {
            return ['kind' => 'anthropic-messages', 'url' => 'https://opencode.ai/zen/go/v1/messages', 'endpointFamily' => 'messages'];
        }

        throw new RuntimeException("Unknown OpenCode Go model slug: {$model}");
    }

    private function isPrefixOf(string $model, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($model, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function isOpencodeGoChatCompletionsModel(string $model): bool
    {
        if (str_starts_with($model, 'minimax-m3')) {
            return true;
        }
        $set = [
            'glm-5.1', 'glm-5', 'kimi-k2.6', 'kimi-k2.5',
            'deepseek-v4-pro', 'deepseek-v4-flash',
            'mimo-v2-pro', 'mimo-v2-omni', 'mimo-v2.5-pro', 'mimo-v2.5',
            'qwen3.6-plus', 'qwen3.5-plus'
        ];
        return in_array($model, $set, true);
    }

    private function isOpencodeGoAnthropicModel(string $model): bool
    {
        $set = ['minimax-m2.7', 'minimax-m2.5'];
        return in_array($model, $set, true);
    }

    private function normalizeModelForProvider(): string
    {
        if ($this->vendor === 'opencode-go') {
            return str_starts_with($this->model, 'opencode-go/') 
                ? substr($this->model, strlen('opencode-go/')) 
                : $this->model;
        }
        return $this->model;
    }

    private function readErrorMessage(?array $json): ?string
    {
        if (!$json) {
            return null;
        }
        // Common formats
        if (isset($json['error'])) {
            if (is_array($json['error'])) {
                return $json['error']['message'] ?? json_encode($json['error']);
            }
            return (string) $json['error'];
        }
        return null;
    }

    private function extractChatCompletionText(array $payload): ?string
    {
        if (!isset($payload['choices']) || !is_array($payload['choices'])) {
            return null;
        }
        $first = $payload['choices'][0] ?? null;
        if (!is_array($first) || !isset($first['message'])) {
            return null;
        }
        $content = $first['message']['content'] ?? null;
        if (is_string($content)) {
            return trim($content);
        }
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $item) {
                if (is_array($item) && isset($item['text']) && is_string($item['text'])) {
                    $parts[] = $item['text'];
                }
            }
            return trim(implode("\n", $parts));
        }
        return null;
    }

    private function extractGoogleText(array $payload): ?string
    {
        if (!isset($payload['candidates']) || !is_array($payload['candidates'])) {
            return null;
        }
        $first = $payload['candidates'][0] ?? null;
        if (!is_array($first) || !isset($first['content'])) {
            return null;
        }
        $parts = $first['content']['parts'] ?? [];
        if (!is_array($parts)) {
            return null;
        }
        $texts = [];
        foreach ($parts as $item) {
            if (is_array($item) && isset($item['text']) && is_string($item['text'])) {
                $texts[] = $item['text'];
            }
        }
        return trim(implode("\n", $texts));
    }

    private function extractOpenAiResponsesText(array $payload): ?string
    {
        if (!isset($payload['output']) || !is_array($payload['output'])) {
            return null;
        }
        $parts = [];
        foreach ($payload['output'] as $item) {
            if (!is_array($item) || !isset($item['content']) || !is_array($item['content'])) {
                continue;
            }
            foreach ($item['content'] as $contentItem) {
                if (is_array($contentItem) && ($contentItem['type'] ?? '') === 'output_text' && isset($contentItem['text'])) {
                    $parts[] = $contentItem['text'];
                }
            }
        }
        return trim(implode("\n", $parts));
    }

    private function extractAnthropicText(array $payload): ?string
    {
        if (!isset($payload['content']) || !is_array($payload['content'])) {
            return null;
        }
        $parts = [];
        foreach ($payload['content'] as $item) {
            if (is_array($item) && ($item['type'] ?? '') === 'text' && isset($item['text'])) {
                $parts[] = $item['text'];
            }
        }
        return trim(implode("\n", $parts));
    }

    /**
     * Simple text-only chat completion.
     * Returns the same structure as generate().
     */
    public function chat(string $systemPrompt, string $userPrompt): array
    {
        return $this->generate($systemPrompt, $userPrompt);
    }

    /**
     * Describe an image using base64 data and MIME type.
     * Returns the same structure as generate().
     */
    public function describeImage(string $base64Image, string $mimeType, string $systemPrompt = 'Describe this image in a single concise sentence suitable for an HTML alt attribute.'): array
    {
        try {
            $attempt = $this->getTransportAttempt();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'text' => null,
                'error' => 'Endpoint resolution failed: ' . $e->getMessage(),
                'url' => '',
                'kind' => 'unknown',
                'status' => null,
                'rawResponse' => null
            ];
        }

        $url = $attempt['url'];
        $kind = $attempt['kind'];
        $normalizedModel = $this->normalizeModelForProvider();

        $headers = [
            'Content-Type' => 'application/json',
        ];
        $body = [];

        if ($kind === 'chat-completions') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            if ($this->vendor === 'openrouter') {
                $siteUrl = $_ENV['PUBLIC_SITE_URL'] ?? getenv('PUBLIC_SITE_URL') ?? '';
                if ($siteUrl !== '') {
                    $headers['HTTP-Referer'] = trim($siteUrl);
                }
                $siteTitle = $_ENV['SITE_TITLE'] ?? getenv('SITE_TITLE') ?? '';
                if ($siteTitle !== '') {
                    $headers['X-OpenRouter-Title'] = trim($siteTitle);
                }
            }

            $dataUrl = 'data:' . $mimeType . ';base64,' . $base64Image;
            $body = [
                'model' => $normalizedModel,
                'max_tokens' => 512,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ['type' => 'text', 'text' => 'What is in this image?']
                    ]]
                ]
            ];
        } elseif ($kind === 'google-generate-content') {
            $modelPath = str_starts_with($normalizedModel, 'models/') ? substr($normalizedModel, 7) : $normalizedModel;
            $url = rtrim($url, '/') . '/' . $modelPath . ':generateContent?key=' . urlencode($this->apiKey);

            $body = [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['inlineData' => ['mimeType' => $mimeType, 'data' => $base64Image]],
                            ['text' => 'What is in this image?']
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 512
                ]
            ];
        } elseif ($kind === 'anthropic-messages') {
            $headers['x-api-key'] = $this->apiKey;
            $headers['anthropic-version'] = '2023-06-01';

            $body = [
                'model' => $normalizedModel,
                'max_tokens' => 512,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64Image]],
                        ['type' => 'text', 'text' => 'What is in this image?']
                    ]]
                ]
            ];
        } elseif ($kind === 'openai-responses') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            $dataUrl = 'data:' . $mimeType . ';base64,' . $base64Image;
            $body = [
                'model' => $normalizedModel,
                'instructions' => $systemPrompt,
                'input' => [
                    ['role' => 'user', 'content' => [
                        ['type' => 'input_image', 'image_url' => $dataUrl],
                        ['type' => 'input_text', 'text' => 'What is in this image?']
                    ]]
                ]
            ];
        } else {
            return [
                'ok' => false,
                'text' => null,
                'error' => 'Vision not supported for this transport kind: ' . $kind,
                'url' => $url,
                'kind' => $kind,
                'status' => null,
                'rawResponse' => null
            ];
        }

        try {
            $response = $this->client->post($url, [
                'headers' => $headers,
                'json' => $body,
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            $rawText = (string) $response->getBody();
            $json = json_decode($rawText, true);

            if ($status < 200 || $status >= 300) {
                $errorMsg = $this->readErrorMessage($json) ?? "Provider request failed with status {$status}";
                return [
                    'ok' => false,
                    'text' => null,
                    'error' => $errorMsg,
                    'url' => $url,
                    'kind' => $kind,
                    'status' => $status,
                    'rawResponse' => substr($rawText, 0, 1200)
                ];
            }

            $extractedText = null;
            if ($kind === 'chat-completions') {
                $extractedText = $this->extractChatCompletionText($json);
            } elseif ($kind === 'google-generate-content') {
                $extractedText = $this->extractGoogleText($json);
            } elseif ($kind === 'openai-responses') {
                $extractedText = $this->extractOpenAiResponsesText($json);
            } elseif ($kind === 'anthropic-messages') {
                $extractedText = $this->extractAnthropicText($json);
            }

            if ($extractedText === null || trim($extractedText) === '') {
                return [
                    'ok' => false,
                    'text' => null,
                    'error' => 'The AI provider returned an empty or unusable response.',
                    'url' => $url,
                    'kind' => $kind,
                    'status' => $status,
                    'rawResponse' => substr($rawText, 0, 1200)
                ];
            }

            return [
                'ok' => true,
                'text' => $extractedText,
                'error' => null,
                'url' => $url,
                'kind' => $kind,
                'status' => $status,
                'rawResponse' => substr($rawText, 0, 1200)
            ];

        } catch (GuzzleException $e) {
            $status = null;
            if ($e instanceof RequestException && $e->getResponse() !== null) {
                $status = $e->getResponse()->getStatusCode();
            }
            return [
                'ok' => false,
                'text' => null,
                'error' => 'HTTP request failed: ' . $e->getMessage(),
                'url' => $url,
                'kind' => $kind,
                'status' => $status,
                'rawResponse' => null
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'text' => null,
                'error' => 'Unexpected error: ' . $e->getMessage(),
                'url' => $url,
                'kind' => $kind,
                'status' => null,
                'rawResponse' => null
            ];
        }
    }
}
