<?php

declare(strict_types=1);

class UserAiVendorSettings
{
    public static function supportsCapabilitiesColumn(): bool
    {
        return ah_column_exists('user_ai_vendor_settings', 'capabilities');
    }

    public static function all(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            "SELECT uavs.*, u.name AS user_name
             FROM user_ai_vendor_settings uavs
             JOIN users u ON u.id = uavs.user_id
             ORDER BY uavs.created_at DESC"
        )->fetchAll();
    }

    public static function allForUser(string $userId): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $stmt = db()->prepare(
            'SELECT * FROM user_ai_vendor_settings WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM user_ai_vendor_settings WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public static function create(array $data): int
    {
        if (self::supportsCapabilitiesColumn()) {
            $stmt = db()->prepare(
                'INSERT INTO user_ai_vendor_settings
                    (user_id, vendor, profile_name, endpoint_kind, enabled, capabilities, model)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['user_id'],
                $data['vendor'],
                $data['profile_name'] ?? 'Default',
                $data['endpoint_kind'] ?? null,
                $data['enabled'] ?? 0,
                $data['capabilities'] ?? 'text,code',
                $data['model'] ?? null,
            ]);
        } else {
            $stmt = db()->prepare(
                'INSERT INTO user_ai_vendor_settings
                    (user_id, vendor, profile_name, endpoint_kind, enabled, model)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['user_id'],
                $data['vendor'],
                $data['profile_name'] ?? 'Default',
                $data['endpoint_kind'] ?? null,
                $data['enabled'] ?? 0,
                $data['model'] ?? null,
            ]);
        }
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        if (self::supportsCapabilitiesColumn()) {
            $stmt = db()->prepare(
                'UPDATE user_ai_vendor_settings SET
                    vendor = ?, profile_name = ?, endpoint_kind = ?, enabled = ?, capabilities = ?, model = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $data['vendor'],
                $data['profile_name'] ?? 'Default',
                $data['endpoint_kind'] ?? null,
                $data['enabled'] ?? 0,
                $data['capabilities'] ?? 'text,code',
                $data['model'] ?? null,
                $id,
            ]);
        } else {
            $stmt = db()->prepare(
                'UPDATE user_ai_vendor_settings SET
                    vendor = ?, profile_name = ?, endpoint_kind = ?, enabled = ?, model = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $data['vendor'],
                $data['profile_name'] ?? 'Default',
                $data['endpoint_kind'] ?? null,
                $data['enabled'] ?? 0,
                $data['model'] ?? null,
                $id,
            ]);
        }
    }

    public static function hasCapability(array $profile, string $capability): bool
    {
        $diagnostics = self::capabilityDiagnostics($profile);
        return in_array($capability, $diagnostics['capabilities'], true);
    }

    public static function capabilityDiagnostics(array $profile): array
    {
        $explicitCapabilities = self::supportsCapabilitiesColumn()
            ? self::parseCapabilities((string) ($profile['capabilities'] ?? ''))
            : [];
        $inferredCapabilities = self::inferCapabilities(
            (string) ($profile['vendor'] ?? ''),
            (string) ($profile['model'] ?? ''),
            $profile['endpoint_kind'] ?? null
        );

        $resolvedCapabilities = array_values(array_unique(array_merge($explicitCapabilities, $inferredCapabilities)));
        if ($resolvedCapabilities === []) {
            $resolvedCapabilities = ['text'];
        }

        $source = 'inferred';
        if ($explicitCapabilities !== [] && $inferredCapabilities !== []) {
            $source = $resolvedCapabilities === $explicitCapabilities ? 'explicit' : 'explicit+inferred';
        } elseif ($explicitCapabilities !== []) {
            $source = 'explicit';
        }

        $transportKind = self::inferTransportKind(
            (string) ($profile['vendor'] ?? ''),
            (string) ($profile['model'] ?? ''),
            $profile['endpoint_kind'] ?? null
        );
        $modelSupportsVision = in_array('vision', $inferredCapabilities, true);

        return [
            'capabilities' => $resolvedCapabilities,
            'capabilities_csv' => implode(',', $resolvedCapabilities),
            'explicit_capabilities' => $explicitCapabilities,
            'explicit_capabilities_csv' => implode(',', $explicitCapabilities),
            'inferred_capabilities' => $inferredCapabilities,
            'inferred_capabilities_csv' => implode(',', $inferredCapabilities),
            'capability_source' => $source,
            'transport_kind' => $transportKind,
            'transport_supports_vision' => in_array($transportKind, ['chat-completions', 'google-generate-content', 'anthropic-messages', 'openai-responses'], true),
            'model_supports_vision' => $modelSupportsVision,
            'vision_inferred' => !in_array('vision', $explicitCapabilities, true) && in_array('vision', $resolvedCapabilities, true),
            'capabilities_schema_supported' => self::supportsCapabilitiesColumn(),
        ];
    }

    public static function visionSupportStatus(array $profile): array
    {
        $diagnostics = self::capabilityDiagnostics($profile);

        if (!in_array('vision', $diagnostics['capabilities'], true)) {
            return [
                'ok' => false,
                'code' => 'vision_not_enabled',
                'message' => 'This AI profile is not marked or inferred as vision-capable. Enable vision in AI Settings or choose a vision-capable model.',
                'diagnostics' => $diagnostics,
            ];
        }

        if (!$diagnostics['transport_supports_vision']) {
            return [
                'ok' => false,
                'code' => 'vision_transport_unsupported',
                'message' => 'This profile resolves to a transport that does not support image input for alt-text generation.',
                'diagnostics' => $diagnostics,
            ];
        }

        if (!$diagnostics['model_supports_vision']) {
            return [
                'ok' => false,
                'code' => 'vision_model_unsupported',
                'message' => 'This model does not appear to support image description. Choose a vision-capable model for this profile.',
                'diagnostics' => $diagnostics,
            ];
        }

        return [
            'ok' => true,
            'code' => null,
            'message' => null,
            'diagnostics' => $diagnostics,
        ];
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM user_ai_vendor_settings WHERE id = ?');
        $stmt->execute([$id]);
    }

    private static function tableExists(): bool
    {
        return ah_table_exists('user_ai_vendor_settings');
    }

    private static function parseCapabilities(string $value): array
    {
        $caps = array_filter(array_map(
            static fn (string $cap): string => trim(strtolower($cap)),
            explode(',', $value)
        ));
        return array_values(array_unique($caps));
    }

    private static function inferCapabilities(string $vendor, string $model, ?string $endpointKind): array
    {
        $vendor = strtolower(trim($vendor));
        $model = strtolower(trim($model));
        $caps = ['text', 'code'];

        if ($vendor === 'deepseek') {
            return $caps;
        }

        if ($vendor === 'google') {
            $caps[] = 'vision';
            return array_values(array_unique($caps));
        }

        $visionMarkers = [
            'vision', 'pixtral', 'mistral-small', 'gpt-4o', 'gpt-4.1',
            'claude-3', 'claude-3.5', 'claude-3.7', 'claude-sonnet-4',
            'claude-opus-4', 'gemini', 'qwen-vl', 'qwen2.5-vl', 'llava',
        ];

        foreach ($visionMarkers as $marker) {
            if ($model !== '' && str_contains($model, $marker)) {
                $caps[] = 'vision';
                break;
            }
        }

        if (in_array($vendor, ['mistral', 'mistral-vibe'], true) && $model === '') {
            $caps[] = 'vision';
        }

        if (in_array($vendor, ['openrouter', 'opencode-zen', 'opencode-go'], true) && $endpointKind !== null) {
            $kind = strtolower(trim($endpointKind));
            if (in_array($kind, ['google-generate', 'google-generate-content', 'anthropic-messages', 'openai-responses', 'chat-completions'], true) && $model !== '') {
                foreach ($visionMarkers as $marker) {
                    if (str_contains($model, $marker)) {
                        $caps[] = 'vision';
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($caps));
    }

    private static function inferTransportKind(string $vendor, string $model, ?string $endpointKind): ?string
    {
        $vendor = strtolower(trim($vendor));
        $model = strtolower(trim($model));
        $endpointKind = $endpointKind !== null ? strtolower(trim($endpointKind)) : null;

        return match ($vendor) {
            'openrouter', 'deepseek', 'mistral', 'mistral-vibe' => 'chat-completions',
            'google' => 'google-generate-content',
            'opencode-zen' => self::inferOpencodeZenTransportKind($model, $endpointKind),
            'opencode-go' => self::inferOpencodeGoTransportKind($model, $endpointKind),
            default => null,
        };
    }

    private static function inferOpencodeZenTransportKind(string $model, ?string $endpointKind): ?string
    {
        if (in_array($endpointKind, ['openai-responses', 'anthropic-messages', 'google-generate-content', 'chat-completions'], true)) {
            return $endpointKind;
        }
        if ($endpointKind === 'google-generate') {
            return 'google-generate-content';
        }
        if (str_starts_with($model, 'gpt-')) {
            return 'openai-responses';
        }
        if (str_starts_with($model, 'claude-')) {
            return 'anthropic-messages';
        }
        if (str_starts_with($model, 'gemini-')) {
            return 'google-generate-content';
        }
        return 'chat-completions';
    }

    private static function inferOpencodeGoTransportKind(string $model, ?string $endpointKind): ?string
    {
        if (in_array($endpointKind, ['anthropic-messages', 'chat-completions'], true)) {
            return $endpointKind;
        }

        $anthropicModels = ['minimax-m2.7', 'minimax-m2.5'];
        if (in_array($model, $anthropicModels, true)) {
            return 'anthropic-messages';
        }

        return 'chat-completions';
    }
}

class UserAiVendorKeys
{
    public static function all(): array
    {
        if (!self::tableExists()) {
            return [];
        }

        return db()->query(
            "SELECT uavk.*, u.name AS user_name
             FROM user_ai_vendor_keys uavk
             JOIN users u ON u.id = uavk.user_id
             ORDER BY uavk.created_at DESC"
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM user_ai_vendor_keys WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: false;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO user_ai_vendor_keys (user_id, vendor, encrypted_api_key)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $data['user_id'],
            $data['vendor'],
            $data['encrypted_api_key'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE user_ai_vendor_keys SET vendor = ?, encrypted_api_key = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['vendor'],
            $data['encrypted_api_key'],
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM user_ai_vendor_keys WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function findForUserVendor(string $userId, string $vendor): array|false
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = db()->prepare(
            'SELECT * FROM user_ai_vendor_keys WHERE user_id = ? AND vendor = ? LIMIT 1'
        );
        $stmt->execute([$userId, $vendor]);
        return $stmt->fetch() ?: false;
    }

    private static function tableExists(): bool
    {
        return ah_table_exists('user_ai_vendor_keys');
    }
}
