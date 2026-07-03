<?php

declare(strict_types=1);

class SiteIdentityAdminController
{
    public static function index(): void
    {
        admin_check();
        $settings = SiteSettings::current() ?: [];
        $assets = SiteAsset::all();
        $mediaAssets = MediaAsset::all();
        $themeOptions = self::themeOptions();
        $colorGroups = self::colorGroups();
        [$profiles, , $personas] = self::loadProfilesData();
        $themeSnapshots = class_exists('SiteThemeSnapshot') ? SiteThemeSnapshot::getLast(10) : [];
        require dirname(__DIR__, 2) . '/views/admin/site-identity/index.php';
    }

    private static function themeOptions(): array
    {
        $builtin = [
            'bauhaus'     => 'Bauhaus — Heavy borders, hard shadows, all-caps',
            'traditional' => 'Traditional — Serif body, hairline borders',
            'minimalist'  => 'Minimalist — No borders, generous whitespace',
            'academic'    => 'Academic — Old-style serif, scholarly feel',
            'airy'        => 'Airy — Light weights, soft shadows, rounded',
            'nature'      => 'Nature — Friendly Nunito sans, soft radius',
            'comfort'     => 'Comfort — Quicksand, pillowy radius',
            'audacious'   => 'Audacious — Bebas Neue, oversized borders',
            'artistic'    => 'Artistic — Caveat handwriting, hand-drawn feel',
            'celestial'   => 'Celestial — Cosmic dark, parchment & amber glow',
        ];
        try {
            if (class_exists('SiteThemeCode')) {
                foreach (SiteThemeCode::getAll() as $row) {
                    if (!(bool) $row['is_builtin'] && !isset($builtin[$row['theme_name']])) {
                        $builtin[$row['theme_name']] = $row['label'] . ' (custom)';
                    }
                }
            }
        } catch (Throwable) {}
        return $builtin;
    }

    private static function colorGroups(): array
    {
        return [
            'Light Mode' => [
                'color_background'             => 'Background',
                'color_foreground'             => 'Foreground / ink',
                'color_muted'                  => 'Muted background',
                'color_muted_foreground'       => 'Muted foreground',
                'color_primary'                => 'Primary',
                'color_primary_foreground'     => 'Primary foreground',
                'color_secondary'              => 'Secondary',
                'color_secondary_foreground'   => 'Secondary foreground',
                'color_accent'                 => 'Accent',
                'color_accent_foreground'      => 'Accent foreground',
                'color_destructive'            => 'Destructive',
                'color_destructive_foreground' => 'Destructive foreground',
            ],
            'Dark Mode' => [
                'color_background_dark'             => 'Background',
                'color_foreground_dark'             => 'Foreground / ink',
                'color_muted_dark'                  => 'Muted background',
                'color_muted_foreground_dark'       => 'Muted foreground',
                'color_primary_dark'                => 'Primary',
                'color_primary_foreground_dark'     => 'Primary foreground',
                'color_secondary_dark'              => 'Secondary',
                'color_secondary_foreground_dark'   => 'Secondary foreground',
                'color_accent_dark'                 => 'Accent',
                'color_accent_foreground_dark'      => 'Accent foreground',
                'color_destructive_dark'            => 'Destructive',
                'color_destructive_foreground_dark' => 'Destructive foreground',
            ],
        ];
    }

    public static function settingsUpdate(): void
    {
        admin_check();

        $tab = in_array($_POST['tab'] ?? '', ['settings', 'design'], true) ? $_POST['tab'] : 'settings';

        try {
            $data = self::resolveSettingsData();
            self::updateSettings($data);

            // Dual-write: keep site_theme_code in sync when theme code is saved
            if (class_exists('SiteThemeCode') && (
                array_key_exists('custom_css', $data) ||
                array_key_exists('custom_js', $data) ||
                array_key_exists('custom_html_body', $data)
            )) {
                $activeTheme = $data['theme'] ?? (SiteSettings::current()['theme'] ?? '');
                if ($activeTheme !== '') {
                    $existing = SiteThemeCode::forTheme($activeTheme);
                    SiteThemeCode::upsert(
                        $activeTheme,
                        $existing['label'] ?? '',
                        $data['custom_css']       ?? ($existing['custom_css']       ?? ''),
                        $data['custom_js']        ?? ($existing['custom_js']        ?? ''),
                        $data['custom_html_body'] ?? ($existing['custom_html_body'] ?? ''),
                        true
                    );
                }
            }

            header('Location: /admin/site-identity?tab=' . urlencode($tab));
        } catch (Throwable $e) {
            header('Location: /admin/site-identity?tab=' . urlencode($tab) . '&error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function assetCreate(): void
    {
        admin_check();

        try {
            $data = self::resolveAssetData();
            SiteAsset::create($data);
            header('Location: /admin/site-identity');
        } catch (Throwable $e) {
            header('Location: /admin/site-identity?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function assetDelete(string $id): void
    {
        admin_check();
        SiteAsset::delete((int) $id);
        header('Location: /admin/site-identity');
        exit;
    }

    public static function mediaAssetDelete(string $id): void
    {
        admin_check();
        MediaAsset::softDelete((int) $id);
        header('Location: /admin/site-identity');
        exit;
    }

    public static function navigationOrderUpdate(): void
    {
        admin_check();

        try {
            $ids = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) ($_POST['ids'] ?? ''))
            )));
            $valid = array_column(admin_navigation_registry(), 'key');
            $ordered = [];
            foreach ($ids as $id) {
                if (in_array($id, $valid, true) && !in_array($id, $ordered, true)) {
                    $ordered[] = $id;
                }
            }
            foreach ($valid as $id) {
                if (!in_array($id, $ordered, true)) {
                    $ordered[] = $id;
                }
            }
            self::updateSettings(['admin_nav_order_json' => json_encode($ordered, JSON_THROW_ON_ERROR)]);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Settings and Design are separate <form> elements that both POST here.
     * Only resolve a field if its key was actually submitted — otherwise a
     * Design save (which has no site_title/hero_heading/etc. inputs) would
     * wipe every Settings field back to its default, and vice versa.
     */
    private static function resolveSettingsData(): array
    {
        $data = [];

        $textFieldDefaults = [
            'site_title' => 'My Site',
            'hero_heading' => '',
            'hero_subheading' => '',
            'copyright_line' => '',
            'footer_credit' => '',
            'cta_label' => '',
        ];
        foreach ($textFieldDefaults as $field => $default) {
            if (array_key_exists($field, $_POST)) {
                $data[$field] = trim((string) $_POST[$field]) ?: $default;
            }
        }

        if (array_key_exists('cta_href', $_POST)) {
            $data['cta_href'] = trim((string) $_POST['cta_href']) ?: '/';
        }
        if (array_key_exists('canonical_public_url', $_POST)) {
            $data['canonical_public_url'] = self::normalizeOptionalUrl($_POST['canonical_public_url'] ?? null);
        }
        if (array_key_exists('logo_url', $_POST)) {
            $data['logo_url'] = trim((string) $_POST['logo_url']) ?: null;
        }
        if (array_key_exists('logo_dark_url', $_POST)) {
            $data['logo_dark_url'] = trim((string) $_POST['logo_dark_url']) ?: null;
        }
        if (array_key_exists('logo_layout', $_POST)) {
            $data['logo_layout'] = trim((string) $_POST['logo_layout']) ?: 'text_only';
        }
        if (array_key_exists('default_theme_mode', $_POST)) {
            $data['default_theme_mode'] = trim((string) $_POST['default_theme_mode']) ?: 'system';
        }
        if (array_key_exists('theme', $_POST)) {
            $data['theme'] = mb_substr(trim((string) $_POST['theme']), 0, 32) ?: null;
        }
        if (array_key_exists('palette', $_POST)) {
            $data['palette'] = mb_substr(trim((string) $_POST['palette']), 0, 32) ?: null;
        }
        if (array_key_exists('custom_css', $_POST)) {
            $data['custom_css'] = (string) $_POST['custom_css'];
        }
        if (array_key_exists('custom_js', $_POST)) {
            $data['custom_js'] = (string) $_POST['custom_js'];
        }
        if (array_key_exists('custom_html_body', $_POST)) {
            $data['custom_html_body'] = (string) $_POST['custom_html_body'];
        }

        foreach (self::colorColumns() as $col) {
            if (!array_key_exists($col, $_POST)) {
                continue;
            }
            $val = trim((string) $_POST[$col]);
            // Accept "H S% L%" or bare "H S L" — reject anything else to prevent injection
            if ($val !== '' && !preg_match('/^[\d.]+\s+[\d.]+%?\s+[\d.]+%?$/', $val)) {
                $val = '';
            }
            $data[$col] = $val !== '' ? $val : null;
        }

        return $data;
    }

    private static function colorColumns(): array
    {
        return [
            'color_background', 'color_foreground',
            'color_muted', 'color_muted_foreground',
            'color_primary', 'color_primary_foreground',
            'color_secondary', 'color_secondary_foreground',
            'color_accent', 'color_accent_foreground',
            'color_destructive', 'color_destructive_foreground',
            'color_background_dark', 'color_foreground_dark',
            'color_muted_dark', 'color_muted_foreground_dark',
            'color_primary_dark', 'color_primary_foreground_dark',
            'color_secondary_dark', 'color_secondary_foreground_dark',
            'color_accent_dark', 'color_accent_foreground_dark',
            'color_destructive_dark', 'color_destructive_foreground_dark',
        ];
    }

    /**
     * Only writes columns actually present in $data. Settings, Design, and
     * navigationOrderUpdate() each submit a different subset of this row's
     * columns — never overwrite a column the caller didn't pass, or every
     * partial save (e.g. just admin_nav_order_json) wipes the rest of the row.
     */
    private static function updateSettings(array $data): void
    {
        $allFields = [
            'site_title', 'hero_heading', 'hero_subheading',
            'copyright_line', 'footer_credit', 'cta_label',
            'cta_href', 'logo_url', 'logo_dark_url', 'logo_layout', 'default_theme_mode',
            'theme', 'palette', 'custom_css', 'custom_js', 'custom_html_body',
            'canonical_public_url', 'admin_nav_order_json',
            ...self::colorColumns(),
        ];

        $available = SiteSettings::availableColumns();
        if ($available === []) {
            throw new RuntimeException('Site settings table is missing the expected editable columns.');
        }

        $columnFields = array_values(array_filter(
            $allFields,
            static fn (string $field): bool => in_array($field, $available, true)
        ));
        if ($columnFields === []) {
            throw new RuntimeException('Site settings table is missing the expected editable columns.');
        }

        $fieldsToUpdate = array_values(array_filter(
            $columnFields,
            static fn (string $field): bool => array_key_exists($field, $data)
        ));

        $fallbackPayload = [];
        foreach ($allFields as $field) {
            if (!in_array($field, $available, true) && $field !== 'settings_json' && array_key_exists($field, $data)) {
                $fallbackPayload[$field] = $data[$field];
            }
        }

        if ($fallbackPayload !== [] && in_array('settings_json', $available, true)) {
            $existing = SiteSettings::current() ?: [];
            $jsonState = json_decode((string) ($existing['settings_json'] ?? ''), true);
            if (!is_array($jsonState)) {
                $jsonState = [];
            }
            foreach ($fallbackPayload as $field => $value) {
                $jsonState[$field] = $value;
            }
            $data['settings_json'] = json_encode($jsonState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!in_array('settings_json', $fieldsToUpdate, true)) {
                $fieldsToUpdate[] = 'settings_json';
            }
        }

        if ($fieldsToUpdate === []) {
            return;
        }

        $sets = [];
        $params = [];
        foreach ($fieldsToUpdate as $field) {
            $sets[] = "$field = ?";
            $params[] = $data[$field] ?? null;
        }
        $params[] = 1; // id

        $stmt = db()->prepare(
            'UPDATE site_settings SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute($params);
    }

    private static function normalizeOptionalUrl(?string $value): ?string
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            return null;
        }
        if (!filter_var($clean, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Canonical Public URL must be a valid absolute URL.');
        }
        return rtrim($clean, '/');
    }

    // ─── AI Theme Code endpoints ──────────────────────────────────────────────

    public static function themeGenerate(): void
    {
        set_time_limit(160);
        admin_check();
        header('Content-Type: application/json; charset=utf-8');
        $startedAt = microtime(true);
        $actorId = (int) (admin_identity()['id'] ?? 0);

        $limit = rate_limit_consume('ai_generate_site_theme', rate_limit_subject_for_scope('ai_generate_site_theme', $actorId > 0 ? $actorId : null));
        if (!$limit['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . (int) $limit['retry_after']);
            echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait a moment and try again.']);
            exit;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $prompt        = trim((string) ($input['prompt'] ?? ''));
            $profileId     = (int) ($input['profile_id'] ?? 0);
            $personaId     = (int) ($input['persona_id'] ?? 0);
            $attemptNumber = max(1, (int) ($input['attempt_number'] ?? 1));
            $previousRaw   = ($input['previous_raw_response'] ?? '') !== '' ? (string) $input['previous_raw_response'] : null;
            $lastError     = trim((string) ($input['last_error'] ?? ''));
            $sequenceToken = trim((string) ($input['sequence_token'] ?? ''));

            if ($prompt === '') throw new InvalidArgumentException('Prompt is required.');
            if ($profileId <= 0) throw new InvalidArgumentException('Please select an AI profile.');
            if ($attemptNumber > SITE_THEME_MAX_ATTEMPTS) throw new InvalidArgumentException('Maximum retries reached.');

            [$profile, $apiKey] = self::loadProfileAndKey($profileId);
            $persona = self::findPersonaById($personaId);

            $aiClient = new \App\Lib\Ai\AiProviderClient($profile['vendor'], $profile['model'], $profile['endpoint_kind'], $apiKey);

            $systemPrompt = site_theme_generation_system_prompt();
            if ($persona) {
                $systemPrompt .= "\n\nPersona guidance:\n" . trim((string) $persona['system_prompt']) . "\n\nApply this style to the generated theme code.";
            }

            $basePrompt = $prompt;
            $userPrompt = $attemptNumber === 1
                ? $basePrompt
                : site_theme_repair_prompt($basePrompt, $previousRaw, $lastError ?: 'Unknown failure');

            $res = $aiClient->generate($systemPrompt, $userPrompt);
            if (!$res['ok']) throw new RuntimeException($res['error'] ?? 'API error');

            $rawText = $res['text'];
            $blocks  = site_theme_extract_code_blocks($rawText);
            site_theme_preflight($blocks['css'], $blocks['js'], $blocks['html']);

            audit_log_event('ai_request', 'ai_generate_site_theme', 'success', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 200,
                'metadata' => [
                    'profile_id' => $profileId,
                    'vendor' => $profile['vendor'] ?? '',
                    'model' => $profile['model'] ?? '',
                    'attempt_number' => $attemptNumber,
                    'sequence_token' => $sequenceToken,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
            ]);

            echo json_encode([
                'success'            => true,
                'css'                => $blocks['css'],
                'js'                 => $blocks['js'],
                'html'               => $blocks['html'],
                'raw_response'       => $rawText,
                'attempt_number'     => $attemptNumber,
                'profile_id'         => $profileId,
                'persona_id'         => $personaId,
                'sequence_token'     => $sequenceToken,
                'vendor'             => $profile['vendor'] ?? '',
                'model'              => $profile['model'] ?? '',
            ]);
        } catch (Throwable $e) {
            audit_log_event('ai_request', 'ai_generate_site_theme', 'error', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 422,
                'metadata' => ['error' => $e->getMessage()],
            ]);
            http_response_code(422);
            echo json_encode([
                'success'        => false,
                'error'          => $e->getMessage(),
                'raw_response'   => $input['previous_raw_response'] ?? null,
                'attempt_number' => $input['attempt_number'] ?? 1,
                'can_retry'      => ($input['attempt_number'] ?? 1) < SITE_THEME_MAX_ATTEMPTS,
            ]);
        }
        exit;
    }

    public static function themeRefine(): void
    {
        set_time_limit(220);
        admin_check();
        header('Content-Type: application/json; charset=utf-8');
        $startedAt = microtime(true);
        $actorId = (int) (admin_identity()['id'] ?? 0);

        $limit = rate_limit_consume('ai_refine_site_theme', rate_limit_subject_for_scope('ai_refine_site_theme', $actorId > 0 ? $actorId : null));
        if (!$limit['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . (int) $limit['retry_after']);
            echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait a moment.']);
            exit;
        }

        $draftSnapshotId = null;

        try {
            $input         = json_decode(file_get_contents('php://input'), true) ?? [];
            $prompt        = trim((string) ($input['prompt'] ?? ''));
            $profileId     = (int) ($input['profile_id'] ?? 0);
            $personaId     = (int) ($input['persona_id'] ?? 0);
            $currentCss    = (string) ($input['current_css'] ?? '');
            $currentJs     = (string) ($input['current_js'] ?? '');
            $currentHtml   = (string) ($input['current_html'] ?? '');
            $originalPrompt = trim((string) ($input['original_prompt'] ?? ''));
            $attemptNumber = max(1, (int) ($input['attempt_number'] ?? 1));
            $previousRaw   = ($input['previous_raw_response'] ?? '') !== '' ? (string) $input['previous_raw_response'] : null;
            $lastError     = trim((string) ($input['last_error'] ?? ''));
            $sequenceToken = trim((string) ($input['sequence_token'] ?? ''));

            if ($prompt === '') throw new InvalidArgumentException('Prompt is required.');
            if ($profileId <= 0) throw new InvalidArgumentException('Please select an AI profile.');
            if ($attemptNumber > SITE_THEME_MAX_ATTEMPTS) throw new InvalidArgumentException('Maximum retries reached.');

            [$profile, $apiKey] = self::loadProfileAndKey($profileId);
            $persona = self::findPersonaById($personaId);

            $aiClient = new \App\Lib\Ai\AiProviderClient($profile['vendor'], $profile['model'], $profile['endpoint_kind'], $apiKey, timeoutOverride: 180.0);

            $systemPrompt = site_theme_refine_system_prompt();
            if ($persona) {
                $systemPrompt .= "\n\nPersona guidance:\n" . trim((string) $persona['system_prompt']);
            }

            $userPrompt = $attemptNumber === 1
                ? site_theme_refine_user_prompt($prompt, $currentCss, $currentJs, $currentHtml, $originalPrompt ?: null)
                : site_theme_refine_repair_prompt($prompt, $previousRaw, $lastError ?: 'Unknown failure', $currentCss, $currentJs, $currentHtml);

            $res = $aiClient->generate($systemPrompt, $userPrompt);
            if (!$res['ok']) throw new RuntimeException($res['error'] ?? 'API error');

            $rawText = $res['text'];
            $plan    = site_theme_extract_refine_plan($rawText);
            $patches = site_theme_extract_refine_patches($rawText);

            $totalPatches = count($patches['css']) + count($patches['js']) + count($patches['html']);
            if ($totalPatches === 0) {
                throw new InvalidArgumentException('AI response contained no valid PATCH blocks. Please try requesting the change again.');
            }

            $updated = site_theme_apply_refine_patches(
                ['css' => $currentCss, 'js' => $currentJs, 'html' => $currentHtml],
                $patches
            );

            site_theme_preflight($updated['css'], $updated['js'], $updated['html']);

            // Persist draft before returning (so partially-good attempts are never lost)
            $draftSnapshotId = SiteThemeSnapshot::create([
                'custom_css'             => $updated['css'],
                'custom_js'              => $updated['js'],
                'custom_html_body'       => $updated['html'],
                'is_draft_attempt'       => 1,
                'attempt_sequence_token' => $sequenceToken,
                'generation_prompt'      => $prompt,
                'generation_vendor'      => $profile['vendor'] ?? '',
                'generation_model'       => $profile['model'] ?? '',
                'ai_profile_id'          => $profileId,
                'ai_persona_id'          => $personaId ?: null,
                'notes'                  => 'AI Refine draft attempt',
            ]);

            audit_log_event('ai_request', 'ai_refine_site_theme', 'success', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 200,
                'metadata' => [
                    'profile_id'     => $profileId,
                    'vendor'         => $profile['vendor'] ?? '',
                    'model'          => $profile['model'] ?? '',
                    'attempt_number' => $attemptNumber,
                    'sequence_token' => $sequenceToken,
                    'duration_ms'    => (int) round((microtime(true) - $startedAt) * 1000),
                ],
            ]);

            echo json_encode([
                'success'            => true,
                'plan'               => $plan,
                'css'                => $updated['css'],
                'js'                 => $updated['js'],
                'html'               => $updated['html'],
                'draft_snapshot_id'  => $draftSnapshotId,
                'raw_response'       => $rawText,
                'attempt_number'     => $attemptNumber,
                'sequence_token'     => $sequenceToken,
                'profile_id'         => $profileId,
                'persona_id'         => $personaId,
            ]);
        } catch (Throwable $e) {
            audit_log_event('ai_request', 'ai_refine_site_theme', 'error', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 422,
                'metadata' => ['error' => $e->getMessage()],
            ]);
            http_response_code(422);
            echo json_encode([
                'success'            => false,
                'error'              => $e->getMessage(),
                'draft_snapshot_id'  => $draftSnapshotId,
                'raw_response'       => $input['previous_raw_response'] ?? null,
                'attempt_number'     => $input['attempt_number'] ?? 1,
                'can_retry'          => ($input['attempt_number'] ?? 1) < SITE_THEME_MAX_ATTEMPTS,
            ]);
        }
        exit;
    }

    public static function themeSave(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input           = json_decode(file_get_contents('php://input'), true) ?? [];
            $css             = (string) ($input['css'] ?? '');
            $js              = (string) ($input['js'] ?? '');
            $html            = (string) ($input['html'] ?? '');
            $draftSnapshotId = isset($input['draft_snapshot_id']) ? (int) $input['draft_snapshot_id'] : null;
            $sequenceToken   = trim((string) ($input['sequence_token'] ?? ''));
            $label           = trim((string) ($input['label'] ?? ''));

            // Snapshot the current live state before overwriting (safe checkpoint)
            $current = SiteSettings::current() ?: [];
            SiteThemeSnapshot::create([
                'custom_css'       => $current['custom_css'] ?? null,
                'custom_js'        => $current['custom_js'] ?? null,
                'custom_html_body' => $current['custom_html_body'] ?? null,
                'is_draft_attempt' => 0,
                'notes'            => 'Auto-snapshot before AI accept',
            ]);

            // Write to live site_settings
            self::updateSettings([
                'custom_css'       => $css,
                'custom_js'        => $js,
                'custom_html_body' => $html,
            ]);

            // Dual-write to site_theme_code for the active theme
            if (class_exists('SiteThemeCode')) {
                $activeTheme = $current['theme'] ?? '';
                if ($activeTheme !== '') {
                    $existing = SiteThemeCode::forTheme($activeTheme);
                    SiteThemeCode::upsert($activeTheme, $existing['label'] ?? '', $css, $js, $html, true);
                }
            }

            // Promote draft snapshot to permanent
            if ($draftSnapshotId !== null && $draftSnapshotId > 0) {
                SiteThemeSnapshot::promoteDraft($draftSnapshotId, $label ?: 'AI-generated theme');
                if ($sequenceToken !== '') {
                    SiteThemeSnapshot::deleteBySequenceToken($sequenceToken, $draftSnapshotId);
                }
            }

            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public static function themeRevert(string $snapshotId): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $snapshot = SiteThemeSnapshot::find((int) $snapshotId);
            if (!$snapshot) {
                throw new InvalidArgumentException('Snapshot not found.');
            }

            // Snapshot current live state before reverting
            $current = SiteSettings::current() ?: [];
            SiteThemeSnapshot::create([
                'custom_css'       => $current['custom_css'] ?? null,
                'custom_js'        => $current['custom_js'] ?? null,
                'custom_html_body' => $current['custom_html_body'] ?? null,
                'is_draft_attempt' => 0,
                'notes'            => 'Auto-snapshot before revert to #' . (int) $snapshotId,
            ]);

            self::updateSettings([
                'custom_css'       => $snapshot['custom_css'],
                'custom_js'        => $snapshot['custom_js'],
                'custom_html_body' => $snapshot['custom_html_body'],
            ]);

            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Per-theme code endpoints ─────────────────────────────────────────────

    public static function themeCodeLoad(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        $theme = trim((string) ($_GET['theme'] ?? ''));
        if ($theme === '') {
            echo json_encode(['css' => '', 'js' => '', 'html' => '', 'has_defaults' => false]);
            exit;
        }

        $row = class_exists('SiteThemeCode') ? SiteThemeCode::forTheme($theme) : null;
        echo json_encode([
            'css'          => $row['custom_css']       ?? '',
            'js'           => $row['custom_js']        ?? '',
            'html'         => $row['custom_html_body'] ?? '',
            'has_defaults' => $row !== null && (
                $row['default_css'] !== null || $row['default_js'] !== null || $row['default_html_body'] !== null
            ),
        ]);
        exit;
    }

    public static function themeSaveNamed(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input     = json_decode(file_get_contents('php://input'), true) ?? [];
            $name      = trim((string) ($input['theme_name'] ?? ''));
            $label     = trim((string) ($input['label'] ?? ''));
            $css       = (string) ($input['css'] ?? '');
            $js        = (string) ($input['js'] ?? '');
            $html      = (string) ($input['html'] ?? '');
            $setActive = (bool) ($input['set_active'] ?? false);

            if ($name === '') throw new InvalidArgumentException('Theme name is required.');
            if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,62}$/', $name)) {
                throw new InvalidArgumentException('Theme name must be lowercase letters, numbers, and hyphens only (max 64 chars).');
            }
            if ($label === '') $label = ucfirst(str_replace('-', ' ', $name));

            // Determine if this is a known built-in name
            $builtinKeys = array_keys([
                'bauhaus', 'traditional', 'minimalist', 'academic', 'airy',
                'nature', 'comfort', 'audacious', 'artistic', 'celestial',
            ]);
            $isBuiltin = in_array($name, $builtinKeys, true) ||
                (class_exists('SiteThemeCode') && SiteThemeCode::exists($name) && (SiteThemeCode::forTheme($name)['is_builtin'] ?? false));

            SiteThemeCode::upsert($name, $label, $css, $js, $html, $isBuiltin);

            if ($setActive) {
                self::updateSettings([
                    'custom_css'       => $css,
                    'custom_js'        => $js,
                    'custom_html_body' => $html,
                    'theme'            => $name,
                ]);
            }

            echo json_encode(['success' => true, 'theme_name' => $name, 'label' => $label]);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public static function themeResetDefaults(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $name  = trim((string) ($input['theme_name'] ?? ''));
            if ($name === '') throw new InvalidArgumentException('theme_name is required.');

            SiteThemeCode::resetToDefaults($name);

            $row = SiteThemeCode::forTheme($name);
            echo json_encode([
                'success' => true,
                'css'     => $row['custom_css']       ?? '',
                'js'      => $row['custom_js']        ?? '',
                'html'    => $row['custom_html_body'] ?? '',
            ]);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ─── Shared helpers ───────────────────────────────────────────────────────

    private static function loadProfileAndKey(int $profileId): array
    {
        $profile = UserAiVendorSettings::find($profileId);
        if (!$profile) throw new InvalidArgumentException('AI profile not found.');

        $keyRow = UserAiVendorKeys::findForUserVendor($profile['user_id'], $profile['vendor']);
        if (!$keyRow) throw new InvalidArgumentException('No API key for vendor: ' . $profile['vendor']);

        $apiKey = decrypt_string($keyRow['encrypted_api_key'], ai_encryption_key());
        return [$profile, $apiKey];
    }

    private static function loadProfilesData(): array
    {
        $profiles = db()->query(
            "SELECT uavs.*, u.name AS user_name FROM user_ai_vendor_settings uavs
             JOIN users u ON u.id = uavs.user_id
             WHERE uavs.enabled = 1 ORDER BY uavs.profile_name ASC"
        )->fetchAll();
        $personas = self::loadPersonas();
        return [$profiles, null, $personas];
    }

    private static function loadPersonas(): array
    {
        try {
            return db()->query('SELECT id, name, system_prompt FROM ai_personas ORDER BY name ASC')->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private static function findPersonaById(int $id): ?array
    {
        if ($id <= 0) return null;
        try {
            $stmt = db()->prepare('SELECT id, name, system_prompt FROM ai_personas WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function resolveAssetData(): array
    {
        $key = trim($_POST['asset_key'] ?? '');
        if ($key === '') {
            throw new InvalidArgumentException('Asset key is required.');
        }

        $file = $_FILES['asset_file'] ?? null;
        if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Asset file is required.');
        }

        $data = file_get_contents($file['tmp_name']);
        if ($data === false) {
            throw new InvalidArgumentException('Failed to read asset file.');
        }

        return [
            'asset_key' => $key,
            'filename' => $file['name'] ?? null,
            'mime_type' => $file['type'] ?? 'application/octet-stream',
            'byte_size' => strlen($data),
            'data' => $data,
            'file_data' => $data,
        ];
    }
}
