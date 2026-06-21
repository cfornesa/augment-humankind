<?php

declare(strict_types=1);

class PiecesAdminController
{
    public static function index(): void
    {
        admin_check();

        $q      = trim((string) ($_GET['q'] ?? ''));
        $engine = (string) ($_GET['engine'] ?? '');
        $sort   = (string) ($_GET['sort'] ?? 'sort_order');
        $dir    = strtolower((string) ($_GET['dir'] ?? 'asc'));

        $allowedSorts = ['sort_order', 'newest', 'title', 'engine', 'status', 'created', 'updated'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'sort_order';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }
        if (!in_array($engine, ['p5', 'c2', 'three', 'svg'], true)) {
            $engine = '';
        }

        $pieces = PlatformArtPiece::allForAdmin(
            $q !== '' ? $q : null,
            $engine !== '' ? $engine : null,
            $sort,
            $dir
        );

        require dirname(__DIR__, 2) . '/views/admin/pieces/index.php';
    }

    public static function library(): void
    {
        admin_check();
        header('Content-Type: application/json');

        $pieces = array_map(static function (array $piece): array {
            return [
                'id' => (int) $piece['id'],
                'title' => $piece['title'] ?? 'Untitled Piece',
                'engine' => $piece['engine'] ?? 'p5',
                'thumbnail_url' => $piece['thumbnail_url'] ?? '',
                'status' => $piece['status'] ?? 'active',
            ];
        }, PlatformArtPiece::all());

        echo json_encode($pieces);
        exit;
    }

    public static function aiProfilesLibrary(): void
    {
        admin_check();
        header('Content-Type: application/json');

        $profiles = db()->query(
            "SELECT uavs.*, u.name AS user_name FROM user_ai_vendor_settings uavs
             JOIN users u ON u.id = uavs.user_id
             WHERE uavs.enabled = 1 ORDER BY uavs.profile_name ASC"
        )->fetchAll();

        $result = array_map(static function (array $profile): array {
            $capabilityDiagnostics = UserAiVendorSettings::capabilityDiagnostics($profile);
            return [
                'id'           => (int) $profile['id'],
                'profile_name' => $profile['profile_name'] ?? '',
                'vendor'       => $profile['vendor'] ?? '',
                'model'        => $profile['model'] ?? '',
                'user_name'    => $profile['user_name'] ?? '',
                'capabilities' => $capabilityDiagnostics['capabilities_csv'],
                'capability_source' => $capabilityDiagnostics['capability_source'],
                'explicit_capabilities' => $capabilityDiagnostics['explicit_capabilities_csv'],
                'inferred_capabilities' => $capabilityDiagnostics['inferred_capabilities_csv'],
                'transport_kind' => $capabilityDiagnostics['transport_kind'],
                'vision_inferred' => $capabilityDiagnostics['vision_inferred'],
                'capabilities_schema_supported' => $capabilityDiagnostics['capabilities_schema_supported'],
            ];
        }, $profiles);

        echo json_encode($result);
        exit;
    }

    public static function create(): void
    {
        admin_check();
        $piece = null;
        $error = null;
        $artMedia = Category::all();
        $assignedCategoryIds = [];
        [$profiles, $preferredProfileId, $personas] = self::loadProfilesData();
        require dirname(__DIR__, 2) . '/views/admin/pieces/form.php';
    }

    public static function store(): void
    {
        admin_check();

        try {
            $data = self::resolvePieceData();
            $pieceId = PlatformArtPiece::create($data);
            PlatformArtPiece::syncCategories($pieceId, $data['category_ids']);
            if ($data['sort_order'] !== null) {
                reorder_shift_position($pieceId, $data['sort_order'], 'art_pieces');
            }

            $code = self::resolveVersionCodeFromPost();
            if (self::hasAnyVersionCode($code)) {
                $versionId = PlatformArtPieceVersion::create([
                    'art_piece_id' => $pieceId,
                    'version_number' => 1,
                    'prompt' => $data['prompt'] !== null && $data['prompt'] !== ''
                        ? $data['prompt']
                        : $data['title'],
                    'structured_spec' => null,
                    'html_code' => $code['html_code'],
                    'css_code' => $code['css_code'],
                    'generated_code' => $code['generated_code'] ?? '',
                    'engine' => $data['engine'],
                    'generation_vendor' => null,
                    'generation_model' => null,
                    'validation_status' => null,
                    'generation_attempt_count' => 0,
                    'notes' => null,
                ]);
                PlatformArtPiece::updateCurrentVersion($pieceId, $versionId);
            }

            header('Location: /admin/pieces');
        } catch (Throwable $e) {
            $piece = self::draftPieceFromPost();
            $error = $e->getMessage();
            $artMedia = Category::all();
            $assignedCategoryIds = $piece['category_ids'];
            [$profiles, $preferredProfileId, $personas] = self::loadProfilesData();
            require dirname(__DIR__, 2) . '/views/admin/pieces/form.php';
        }
        exit;
    }

    public static function edit(string $id): void
    {
        admin_check();
        $piece = PlatformArtPiece::find((int) $id);
        if (!$piece) {
            header('Location: /admin/pieces');
            exit;
        }
        $error = null;
        $artMedia = Category::all();
        $assignedCategoryIds = PlatformArtPiece::categoryIds((int) $id);
        [$profiles, $preferredProfileId, $personas] = self::loadProfilesData();
        require dirname(__DIR__, 2) . '/views/admin/pieces/form.php';
    }

    public static function update(string $id): void
    {
        admin_check();
        // form.php's main Save submit uses fetch() (not a traditional form
        // POST) specifically so a stale/dropped connection after a long
        // preceding AI Refine + capture sequence can be retried — the same
        // reason generate-preview.php's Save and the AI Refine request
        // itself use fetch(). A JSON response is required for that caller;
        // a non-JS form POST still gets the original redirect/inline-error
        // behavior untouched below.
        $wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

        $existing = PlatformArtPiece::find((int) $id);
        if (!$existing) {
            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Piece not found.']);
                exit;
            }
            header('Location: /admin/pieces');
            exit;
        }

        try {
            $data = self::resolvePieceData();
            $sortOrder = $data['sort_order'] ?? ($existing['sort_order'] ?? 0);
            $data['sort_order'] = (int) ($existing['sort_order'] ?? 0);
            PlatformArtPiece::update((int) $id, $data);
            reorder_shift_position((int) $id, $sortOrder, 'art_pieces');
            PlatformArtPiece::syncCategories((int) $id, $data['category_ids']);

            $code = self::resolveVersionCodeFromPost();
            if (self::hasAnyVersionCode($code)) {
                $currentVersion = $existing['current_version'] ?? null;
                $codeChanged = !$currentVersion
                    || self::normalizeCode($code['html_code']) !== self::normalizeCode($currentVersion['html_code'] ?? null)
                    || self::normalizeCode($code['css_code']) !== self::normalizeCode($currentVersion['css_code'] ?? null)
                    || self::normalizeCode($code['generated_code']) !== self::normalizeCode($currentVersion['generated_code'] ?? null);

                if ($currentVersion && !$codeChanged) {
                    // Code is unchanged (a metadata-only save) — leave the
                    // current version's row and its AI attribution alone.
                } else {
                    // Every code-changing save creates a new version rather
                    // than overwriting the current one in place, so version
                    // history is meaningful and "Revert" has something to
                    // revert to — this applies to manual edits and AI Refine
                    // saves alike.
                    $versionId = PlatformArtPieceVersion::create([
                        'art_piece_id' => (int) $id,
                        'version_number' => PlatformArtPieceVersion::nextVersionNumber((int) $id),
                        'prompt' => $data['prompt'] !== null && $data['prompt'] !== ''
                            ? $data['prompt']
                            : ($currentVersion['prompt'] ?? $data['title']),
                        'structured_spec' => $currentVersion['structured_spec'] ?? null,
                        'html_code' => self::normalizeCode($code['html_code']),
                        'css_code' => self::normalizeCode($code['css_code']),
                        'generated_code' => self::normalizeCode($code['generated_code'] ?? ($currentVersion['generated_code'] ?? '')),
                        'engine' => $data['engine'],
                        'generation_vendor' => $currentVersion['generation_vendor'] ?? null,
                        'generation_model' => $currentVersion['generation_model'] ?? null,
                        'validation_status' => $currentVersion['validation_status'] ?? null,
                        'generation_attempt_count' => $currentVersion['generation_attempt_count'] ?? 0,
                        'notes' => null,
                        'ai_profile_id' => $data['ai_profile_id'] ?? null,
                        'ai_persona_id' => $data['ai_persona_id'] ?? null,
                    ]);
                    PlatformArtPiece::updateCurrentVersion((int) $id, $versionId);
                }
            }

            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => '/admin/pieces']);
            } else {
                header('Location: /admin/pieces');
            }
        } catch (Throwable $e) {
            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $piece = self::draftPieceFromPost((int) $id);
            $error = $e->getMessage();
            $artMedia = Category::all();
            $assignedCategoryIds = $piece['category_ids'];
            [$profiles, $preferredProfileId, $personas] = self::loadProfilesData();
            require dirname(__DIR__, 2) . '/views/admin/pieces/form.php';
        }
        exit;
    }

    public static function delete(string $id): void
    {
        admin_check();
        PlatformArtPiece::softDelete((int) $id);
        header('Location: /admin/pieces');
        exit;
    }

    public static function setStatus(string $id): void
    {
        admin_check();
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['active', 'draft', 'archived'], true)) {
            PlatformArtPiece::setStatus((int) $id, $status);
        }
        header('Location: /admin/pieces');
        exit;
    }

    public static function reorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        PlatformArtPiece::reorder($ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function versions(string $id): void
    {
        admin_check();
        $piece = PlatformArtPiece::find((int) $id);
        if (!$piece) {
            header('Location: /admin/pieces');
            exit;
        }
        $versions = PlatformArtPieceVersion::allForPiece((int) $id);
        require dirname(__DIR__, 2) . '/views/admin/pieces/versions.php';
    }

    public static function versionCreate(string $id): void
    {
        admin_check();
        $piece = PlatformArtPiece::find((int) $id);
        if (!$piece) {
            header('Location: /admin/pieces');
            exit;
        }
        $version = null;
        $error = null;
        [$profiles, , $personas] = self::loadProfilesData();
        require dirname(__DIR__, 2) . '/views/admin/pieces/version-form.php';
    }

    public static function versionStore(string $id): void
    {
        admin_check();
        $piece = PlatformArtPiece::find((int) $id);
        if (!$piece) {
            header('Location: /admin/pieces');
            exit;
        }

        try {
            $data = self::resolveVersionData((int) $id);
            $versionId = PlatformArtPieceVersion::create($data);
            PlatformArtPiece::updateCurrentVersion((int) $id, $versionId);
            header('Location: /admin/pieces/' . $id . '/versions');
        } catch (Throwable $e) {
            $version = self::draftVersionFromPost();
            $error = $e->getMessage();
            [$profiles, , $personas] = self::loadProfilesData();
            require dirname(__DIR__, 2) . '/views/admin/pieces/version-form.php';
        }
        exit;
    }

    public static function versionEdit(string $id, string $vid): void
    {
        admin_check();
        $piece = PlatformArtPiece::find((int) $id);
        if (!$piece) {
            header('Location: /admin/pieces');
            exit;
        }
        $version = PlatformArtPieceVersion::find((int) $vid);
        if (!$version || (int) $version['art_piece_id'] !== (int) $id) {
            header('Location: /admin/pieces/' . $id . '/versions');
            exit;
        }
        $error = null;
        [$profiles, , $personas] = self::loadProfilesData();
        require dirname(__DIR__, 2) . '/views/admin/pieces/version-form.php';
    }

    public static function versionUpdate(string $id, string $vid): void
    {
        admin_check();
        $piece = PlatformArtPiece::find((int) $id);
        if (!$piece) {
            header('Location: /admin/pieces');
            exit;
        }
        $version = PlatformArtPieceVersion::find((int) $vid);
        if (!$version || (int) $version['art_piece_id'] !== (int) $id) {
            header('Location: /admin/pieces/' . $id . '/versions');
            exit;
        }

        try {
            $data = self::resolveVersionData((int) $id);
            PlatformArtPieceVersion::update((int) $vid, $data);
            PlatformArtPiece::touchUpdatedAt((int) $id);
            header('Location: /admin/pieces/' . $id . '/versions');
        } catch (Throwable $e) {
            $version = self::draftVersionFromPost();
            $error = $e->getMessage();
            [$profiles, , $personas] = self::loadProfilesData();
            require dirname(__DIR__, 2) . '/views/admin/pieces/version-form.php';
        }
        exit;
    }

    public static function versionDelete(string $id, string $vid): void
    {
        admin_check();
        PlatformArtPieceVersion::delete((int) $vid);
        PlatformArtPiece::touchUpdatedAt((int) $id);
        header('Location: /admin/pieces/' . $id . '/versions');
        exit;
    }

    public static function versionSetCurrent(string $id, string $vid): void
    {
        admin_check();
        PlatformArtPiece::updateCurrentVersion((int) $id, (int) $vid);
        header('Location: /admin/pieces/' . $id . '/versions');
        exit;
    }

    private static function loadProfilesData(): array
    {
        $profiles = db()->query("SELECT uavs.*, u.name AS user_name FROM user_ai_vendor_settings uavs JOIN users u ON u.id = uavs.user_id WHERE uavs.enabled = 1 ORDER BY uavs.profile_name ASC")->fetchAll();
        $owner = PlatformUser::owner();
        $preferredProfileId = $owner && !empty($owner['preferred_art_piece_profile_id']) ? (int) $owner['preferred_art_piece_profile_id'] : null;
        return [$profiles, $preferredProfileId, self::loadPersonas()];
    }

    private static function resolvePieceData(): array
    {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $engine = $_POST['engine'] ?? 'p5';
        if (!in_array($engine, ['p5', 'c2', 'three', 'svg'], true)) {
            $engine = 'p5';
        }

        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'draft', 'archived'], true)) {
            $status = 'active';
        }

        return [
            'title' => $title,
            'prompt' => trim($_POST['prompt'] ?? '') ?: null,
            'engine' => $engine,
            'status' => $status,
            'thumbnail_url' => trim($_POST['thumbnail_url'] ?? '') ?: null,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'sort_order' => isset($_POST['sort_order']) ? max(0, (int) $_POST['sort_order']) : null,
            'comments_enabled' => isset($_POST['comments_enabled']) ? 1 : 0,
            'category_ids' => array_map('intval', $_POST['category_ids'] ?? []),
            // Not persisted on art_pieces itself — read by update() when a
            // code change creates a new art_piece_versions row.
            'ai_profile_id' => isset($_POST['ai_profile_id']) ? ((int) $_POST['ai_profile_id'] ?: null) : null,
            'ai_persona_id' => isset($_POST['ai_persona_id']) ? ((int) $_POST['ai_persona_id'] ?: null) : null,
        ];
    }

    private static function draftPieceFromPost(?int $existingId = null): array
    {
        $existing = $existingId ? PlatformArtPiece::find($existingId) : null;

        $draft = [
            'id' => $existingId,
            'title' => trim((string) ($_POST['title'] ?? ($existing['title'] ?? ''))),
            'prompt' => trim((string) ($_POST['prompt'] ?? ($existing['prompt'] ?? ''))),
            'engine' => $_POST['engine'] ?? ($existing['engine'] ?? 'p5'),
            'status' => $_POST['status'] ?? ($existing['status'] ?? 'active'),
            'thumbnail_url' => trim((string) ($_POST['thumbnail_url'] ?? ($existing['thumbnail_url'] ?? ''))),
            'description' => trim((string) ($_POST['description'] ?? ($existing['description'] ?? ''))),
            'comments_enabled' => isset($_POST['comments_enabled']) ? 1 : ($existing['comments_enabled'] ?? 0),
            'category_ids' => array_map('intval', $_POST['category_ids'] ?? ($existing ? PlatformArtPiece::categoryIds((int) $existing['id']) : [])),
        ];
        $draft['current_version'] = array_merge(
            $existing['current_version'] ?? [],
            self::resolveVersionCodeFromPost()
        );
        return $draft;
    }

    private static function resolveVersionData(int $pieceId): array
    {
        $prompt = trim($_POST['prompt'] ?? '');
        if ($prompt === '') {
            throw new InvalidArgumentException('Prompt is required for a version.');
        }

        $engine = $_POST['engine'] ?? 'p5';
        if (!in_array($engine, ['p5', 'c2', 'three', 'svg'], true)) {
            $engine = 'p5';
        }

        return [
            'art_piece_id' => $pieceId,
            'version_number' => PlatformArtPieceVersion::nextVersionNumber($pieceId),
            'prompt' => $prompt,
            'structured_spec' => trim($_POST['structured_spec'] ?? '') ?: null,
            'html_code' => trim($_POST['html_code'] ?? '') ?: null,
            'css_code' => trim($_POST['css_code'] ?? '') ?: null,
            'generated_code' => trim($_POST['generated_code'] ?? '') ?: null,
            'engine' => $engine,
            'generation_vendor' => trim($_POST['generation_vendor'] ?? '') ?: null,
            'generation_model' => trim($_POST['generation_model'] ?? '') ?: null,
            'validation_status' => $_POST['validation_status'] ?? 'validated',
            'generation_attempt_count' => (int) ($_POST['generation_attempt_count'] ?? 1),
            'notes' => trim($_POST['notes'] ?? '') ?: null,
            'ai_profile_id' => (int) ($_POST['ai_profile_id'] ?? 0) ?: null,
            'ai_persona_id' => (int) ($_POST['ai_persona_id'] ?? 0) ?: null,
        ];
    }

    private static function draftVersionFromPost(): array
    {
        return [
            'prompt' => trim((string) ($_POST['prompt'] ?? '')),
            'structured_spec' => trim((string) ($_POST['structured_spec'] ?? '')),
            'html_code' => trim((string) ($_POST['html_code'] ?? '')),
            'css_code' => trim((string) ($_POST['css_code'] ?? '')),
            'generated_code' => trim((string) ($_POST['generated_code'] ?? '')),
            'engine' => $_POST['engine'] ?? 'p5',
            'generation_vendor' => trim((string) ($_POST['generation_vendor'] ?? '')),
            'generation_model' => trim((string) ($_POST['generation_model'] ?? '')),
            'validation_status' => $_POST['validation_status'] ?? 'validated',
            'generation_attempt_count' => (int) ($_POST['generation_attempt_count'] ?? 1),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'ai_profile_id' => (int) ($_POST['ai_profile_id'] ?? 0) ?: null,
            'ai_persona_id' => (int) ($_POST['ai_persona_id'] ?? 0) ?: null,
        ];
    }

    private static function normalizeCode(?string $code): string
    {
        if ($code === null) {
            return '';
        }
        return str_replace("\r\n", "\n", trim($code));
    }

    private static function resolveVersionCodeFromPost(): array
    {
        $html = trim((string) ($_POST['html_code'] ?? ''));
        $css = trim((string) ($_POST['css_code'] ?? ''));
        $js = trim((string) ($_POST['generated_code'] ?? ''));

        return [
            'html_code' => $html !== '' ? $html : null,
            'css_code' => $css !== '' ? $css : null,
            'generated_code' => $js !== '' ? $js : null,
        ];
    }

    private static function hasAnyVersionCode(array $code): bool
    {
        return $code['html_code'] !== null
            || $code['css_code'] !== null
            || $code['generated_code'] !== null;
    }

    public static function captureThumbnail(string $id): void
    {
        admin_check();
        header('Content-Type: application/json');

        $piece = PlatformArtPiece::find((int) $id);
        if (!$piece) {
            http_response_code(404);
            echo json_encode(['error' => 'Piece not found.']);
            exit;
        }

        $raw = trim((string) ($_POST['image_data'] ?? ''));
        if ($raw === '') {
            http_response_code(400);
            echo json_encode(['error' => 'No image data received.']);
            exit;
        }

        // Strip data URI prefix if present
        if (str_contains($raw, ',')) {
            $raw = substr($raw, strpos($raw, ',') + 1);
        }

        $binary = base64_decode($raw, strict: true);
        if ($binary === false || strlen($binary) < 100) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid image data.']);
            exit;
        }

        $mediaId = MediaFile::create($binary, 'image/png', 'piece-thumbnail.png');
        $url = '/image/' . $mediaId;
        PlatformArtPiece::update((int) $id, array_merge($piece, [
            'thumbnail_url' => $url,
            'comments_enabled' => (int)(bool) ($piece['comments_enabled'] ?? 0),
        ]));

        // Auto-set thumbnail alt text from the piece's generation prompt (no AI tokens)
        $piecePrompt = (string) ($piece['prompt'] ?? '');
        if ($piecePrompt !== '') {
            try {
                if (ah_column_exists('art_pieces', 'thumbnail_alt_text')) {
                    db()->prepare('UPDATE art_pieces SET thumbnail_alt_text = ? WHERE id = ?')
                        ->execute([mb_substr($piecePrompt, 0, 500), (int) $id]);
                }
            } catch (Throwable) {}
        }

        echo json_encode(['ok' => true, 'url' => $url]);
        exit;
    }

    public static function generateForm(): void
    {
        admin_check();
        $profiles = db()->query("SELECT uavs.*, u.name AS user_name FROM user_ai_vendor_settings uavs JOIN users u ON u.id = uavs.user_id WHERE uavs.enabled = 1 ORDER BY uavs.profile_name ASC")->fetchAll();
        $personas = self::loadPersonas();
        $error = null;
        $prompt = '';
        $engine = 'p5';
        $selectedProfileId = null;
        $selectedPersonaId = null;
        $attemptLogs = null;

        // Pre-select owner preferred art piece profile
        $owner = PlatformUser::owner();
        if ($owner && !empty($owner['preferred_art_piece_profile_id'])) {
            $selectedProfileId = (int) $owner['preferred_art_piece_profile_id'];
        }

        require dirname(__DIR__, 2) . '/views/admin/pieces/generate-form.php';
    }

    private static function loadPersonas(): array
    {
        try {
            return db()->query('SELECT id, name, system_prompt FROM ai_personas ORDER BY name ASC')->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private static function findPersonaById(int $personaId): array|false
    {
        if ($personaId <= 0) {
            return false;
        }

        try {
            $stmt = db()->prepare('SELECT id, name, system_prompt FROM ai_personas WHERE id = ? LIMIT 1');
            $stmt->execute([$personaId]);
            return $stmt->fetch() ?: false;
        } catch (Throwable) {
            return false;
        }
    }

    public static function generate(): void
    {
        set_time_limit(660); // 5 attempts × 2 min + buffer
        admin_check();
        header('Content-Type: application/json; charset=utf-8');
        $startedAt = microtime(true);
        $prompt = trim($_POST['prompt'] ?? '');
        $engine = trim($_POST['engine'] ?? 'p5');
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $personaId = (int) ($_POST['persona_id'] ?? 0);

        $actorId = (int) (admin_identity()['id'] ?? 0);
        self::writeGenerateProgress(null, $engine);

        $limit = rate_limit_consume('ai_generate_piece', rate_limit_subject_for_scope('ai_generate_piece', $actorId > 0 ? $actorId : null));
        if (!$limit['allowed']) {
            audit_log_event('ai_request', 'ai_generate_piece', 'throttled', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 429,
                'metadata' => ['retry_after' => (int) $limit['retry_after']],
            ]);
            http_response_code(429);
            header('Retry-After: ' . (int) $limit['retry_after']);
            echo json_encode([
                'success' => false,
                'error' => 'Too many AI generation requests. Please wait a few minutes and try again.',
            ]);
            exit;
        }

        try {
            if ($prompt === '') {
                throw new InvalidArgumentException('Prompt is required.');
            }
            if ($profileId <= 0) {
                throw new InvalidArgumentException('Please select an active AI profile.');
            }

            $profile = UserAiVendorSettings::find($profileId);
            if (!$profile) {
                throw new InvalidArgumentException('Selected AI profile was not found.');
            }

            $keyRow = UserAiVendorKeys::findForUserVendor($profile['user_id'], $profile['vendor']);
            if (!$keyRow) {
                throw new InvalidArgumentException("No API key configured for vendor: " . $profile['vendor']);
            }

            $apiKey = decrypt_string($keyRow['encrypted_api_key'], ai_encryption_key());

            // Apply persona if selected
            $persona = null;
            if ($personaId > 0) {
                try {
                    $stmt = db()->prepare('SELECT * FROM ai_personas WHERE id = ? LIMIT 1');
                    $stmt->execute([$personaId]);
                    $persona = $stmt->fetch() ?: null;
                } catch (Throwable) {}
            }
            $basePrompt = $persona
                ? trim($persona['system_prompt']) . "\n\nApply this to the following prompt:\n\n" . $prompt
                : $prompt;

            $aiClient = new \App\Lib\Ai\AiProviderClient($profile['vendor'], $profile['model'], $profile['endpoint_kind'], $apiKey);

            $attemptCount = 0;
            $success = false;
            $htmlCode = '';
            $cssCode = '';
            $generatedCode = '';
            $attemptLogs = [];
            $previousRawResponse = null;
            $lastError = '';

            while ($attemptCount < ART_PIECE_MAX_ATTEMPTS) {
                $attemptCount++;
                self::writeGenerateProgress($attemptCount, $engine);
                $currentAttemptLog = [
                    'attempt' => $attemptCount,
                    'vendor' => $profile['vendor'],
                    'model' => $profile['model'],
                    'success' => false,
                    'api_error' => null,
                    'validation_error' => null,
                    'raw_response' => null,
                    'url' => '',
                    'status' => null,
                ];

                $systemPrompt = art_piece_generation_system_prompt($engine);
                if ($attemptCount === 1) {
                    $userPromptForApi = $basePrompt;
                } else {
                    $userPromptForApi = art_piece_repair_prompt($engine, $basePrompt, $previousRawResponse, $lastError);
                }

                $res = $aiClient->generate($systemPrompt, $userPromptForApi);
                $currentAttemptLog['url'] = $res['url'] ?? '';
                $currentAttemptLog['status'] = $res['status'] ?? null;

                if (!$res['ok']) {
                    $lastError = $res['error'] ?? 'API error';
                    $currentAttemptLog['api_error'] = $lastError;
                    $attemptLogs[] = $currentAttemptLog;
                    continue;
                }

                $rawText = $res['text'];
                $previousRawResponse = $rawText;
                $currentAttemptLog['raw_response'] = $rawText;

                // Extract blocks
                $blocks = art_piece_extract_code_blocks($rawText);
                $html = $blocks['htmlCode'] ?? '';
                $css = $blocks['cssCode'] ?? '';
                $js = $blocks['generatedCode'] ?? '';

                // Preflight
                try {
                    if ($html === '' && $engine !== 'svg') {
                        throw new RuntimeException('HTML block is empty');
                    }
                    if ($js !== '') {
                        art_piece_preflight_code($engine, $js);
                    } elseif ($engine !== 'svg') {
                        throw new RuntimeException('JavaScript block is empty');
                    }

                    // Canvas & SVG Preservation Constraints
                    if (in_array($engine, ['p5', 'c2', 'three'], true)) {
                        if (!preg_match('/id\s*=\s*["\'](?:container|canvas-container|sketch-container|runtime-root)["\']/i', $html) && !preg_match('/<canvas/i', $html)) {
                            throw new RuntimeException('HTML block must contain a container element (e.g. <div id="container"></div> or a <canvas> element) to mount the canvas.');
                        }
                        if (preg_match('/(?:canvas|#container|#scene|#c2-canvas)\s*\{[^}]*\bdisplay\s*:\s*none\b/i', $css)) {
                            throw new RuntimeException('CSS cannot hide the canvas or container element (display: none is forbidden).');
                        }
                        if (preg_match('/(?:canvas|#container|#scene|#c2-canvas)\s*\{[^}]*\bvisibility\s*:\s*hidden\b/i', $css)) {
                            throw new RuntimeException('CSS cannot hide the canvas or container element (visibility: hidden is forbidden).');
                        }
                    }

                    if ($engine === 'svg') {
                        if (!preg_match('/<svg/i', $html)) {
                            throw new RuntimeException('HTML code must contain an <svg> element for SVG pieces.');
                        }
                        if (preg_match('/(?:svg|#container)\s*\{[^}]*\bdisplay\s*:\s*none\b/i', $css)) {
                            throw new RuntimeException('CSS cannot hide the SVG or container element (display: none is forbidden).');
                        }
                        if (preg_match('/(?:svg|#container)\s*\{[^}]*\bvisibility\s*:\s*hidden\b/i', $css)) {
                            throw new RuntimeException('CSS cannot hide the SVG or container element (visibility: hidden is forbidden).');
                        }
                    }

                    // Success!
                    $htmlCode = $html;
                    $cssCode = $css;
                    $generatedCode = $js;
                    $success = true;
                    $currentAttemptLog['success'] = true;
                    $attemptLogs[] = $currentAttemptLog;
                    break;
                } catch (Throwable $e) {
                    $lastError = $e->getMessage();
                    $currentAttemptLog['validation_error'] = $lastError;
                    $attemptLogs[] = $currentAttemptLog;
                }
            }

            if (!$success) {
                throw new RuntimeException('All AI generation attempts failed validation.');
            }

            // Render preview
            audit_log_event('ai_request', 'ai_generate_piece', 'success', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 200,
                'metadata' => [
                    'profile_id' => $profileId,
                    'vendor' => $profile['vendor'] ?? '',
                    'model' => $profile['model'] ?? '',
                    'endpoint_kind' => $profile['endpoint_kind'] ?? '',
                    'engine' => $engine,
                    'attempt_count' => $attemptCount,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
            ]);
            self::storePendingGeneration([
                'engine' => $engine,
                'html_code' => $htmlCode,
                'css_code' => $cssCode,
                'generated_code' => $generatedCode,
                'vendor' => $profile['vendor'] ?? '',
                'model' => $profile['model'] ?? '',
                'endpoint_kind' => $profile['endpoint_kind'] ?? '',
                'attempt_count' => $attemptCount,
                'prompt' => $prompt,
                'profile_id' => $profileId,
                'persona_id' => $personaId,
            ]);
            echo json_encode(['success' => true]);

        } catch (Throwable $e) {
            audit_log_event('ai_request', 'ai_generate_piece', 'error', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 500,
                'metadata' => [
                    'profile_id' => $profileId,
                    'engine' => $engine,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error' => $e->getMessage(),
                ],
            ]);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public static function generatePreview(): void
    {
        admin_check();

        $pending = $_SESSION['pending_generation'] ?? null;
        unset($_SESSION['pending_generation']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (!is_array($pending)) {
            header('Location: /admin/pieces/generate');
            exit;
        }

        $engine = (string) ($pending['engine'] ?? 'p5');
        $htmlCode = (string) ($pending['html_code'] ?? '');
        $cssCode = (string) ($pending['css_code'] ?? '');
        $generatedCode = (string) ($pending['generated_code'] ?? '');
        $profile = [
            'vendor' => (string) ($pending['vendor'] ?? ''),
            'model' => (string) ($pending['model'] ?? ''),
            'endpoint_kind' => (string) ($pending['endpoint_kind'] ?? ''),
        ];
        $attemptCount = (int) ($pending['attempt_count'] ?? 1);
        $prompt = (string) ($pending['prompt'] ?? '');
        $profileId = (int) ($pending['profile_id'] ?? 0);
        $personaId = (int) ($pending['persona_id'] ?? 0);

        require dirname(__DIR__, 2) . '/views/admin/pieces/generate-preview.php';
    }

    public static function generateProgress(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        $progress = $_SESSION['generate_progress'] ?? [];
        echo json_encode([
            'attempt' => isset($progress['attempt']) ? (int) $progress['attempt'] : null,
            'max_attempts' => isset($progress['max_attempts']) ? (int) $progress['max_attempts'] : ART_PIECE_MAX_ATTEMPTS,
            'engine' => isset($progress['engine']) ? (string) $progress['engine'] : null,
            'complete' => !empty($progress['complete']),
            'updated_at' => isset($progress['updated_at']) ? (int) $progress['updated_at'] : null,
        ]);
        exit;
    }

    public static function generateSave(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $title = trim($_POST['title'] ?? '');
            if ($title === '') {
                throw new InvalidArgumentException('Title is required.');
            }

            $prompt = trim($_POST['prompt'] ?? '') ?: null;
            $engine = $_POST['engine'] ?? 'p5';
            $status = $_POST['status'] ?? 'draft';

            $htmlCode = trim($_POST['html_code'] ?? '') ?: null;
            $cssCode = trim($_POST['css_code'] ?? '') ?: null;
            $generatedCode = trim($_POST['generated_code'] ?? '') ?: null;

            $owner = PlatformUser::owner();
            $ownerId = $owner ? $owner['id'] : null;

            // Decode auto-captured thumbnail from the preview page
            $thumbnailUrl = null;
            $rawThumb = trim($_POST['thumbnail_data'] ?? '');
            if ($rawThumb !== '') {
                if (str_contains($rawThumb, ',')) {
                    $rawThumb = substr($rawThumb, strpos($rawThumb, ',') + 1);
                }
                $binary = base64_decode($rawThumb, strict: true);
                if ($binary !== false && strlen($binary) >= 100) {
                    $mediaId = MediaFile::create($binary, 'image/png', 'piece-thumbnail.png');
                    $thumbnailUrl = '/image/' . $mediaId;
                }
            }

            $pieceId = PlatformArtPiece::create([
                'owner_user_id' => $ownerId,
                'title' => $title,
                'prompt' => $prompt,
                'engine' => $engine,
                'status' => $status,
                'thumbnail_url' => $thumbnailUrl,
                'description' => trim($_POST['description'] ?? '') ?: null,
            ]);

            // Auto-set thumbnail alt text from the generation prompt (no AI tokens)
            if ($prompt !== null && $prompt !== '') {
                try {
                    if (ah_column_exists('art_pieces', 'thumbnail_alt_text')) {
                        db()->prepare('UPDATE art_pieces SET thumbnail_alt_text = ? WHERE id = ?')
                            ->execute([mb_substr($prompt, 0, 500), $pieceId]);
                    }
                } catch (Throwable) {}
            }

            $versionId = PlatformArtPieceVersion::create([
                'art_piece_id' => $pieceId,
                'version_number' => 1,
                'prompt' => $prompt !== null ? $prompt : $title,
                'structured_spec' => null,
                'html_code' => $htmlCode,
                'css_code' => $cssCode,
                'generated_code' => $generatedCode ?? '',
                'engine' => $engine,
                'generation_vendor' => trim($_POST['generation_vendor'] ?? '') ?: null,
                'generation_model' => trim($_POST['generation_model'] ?? '') ?: null,
                'validation_status' => 'validated',
                'generation_attempt_count' => (int) ($_POST['generation_attempt_count'] ?? 1),
                'notes' => 'Generated via AI',
                'ai_profile_id' => (int) ($_POST['profile_id'] ?? 0) ?: null,
                'ai_persona_id' => (int) ($_POST['persona_id'] ?? 0) ?: null,
            ]);

            PlatformArtPiece::updateCurrentVersion($pieceId, $versionId);

            echo json_encode(['success' => true, 'redirect' => '/admin/pieces']);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public static function aiProcessText(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');
        $startedAt = microtime(true);
        $actorId = (int) (admin_identity()['id'] ?? 0);
        $limit = rate_limit_consume('ai_process_text', rate_limit_subject_for_scope('ai_process_text', $actorId > 0 ? $actorId : null));
        if (!$limit['allowed']) {
            self::emitRateLimitedJson('ai_process_text', (int) $limit['retry_after'], $actorId);
        }

        try {
            $profileId = (int) ($_POST['profile_id'] ?? 0);
            $personaId = (int) ($_POST['persona_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $mode = $_POST['mode'] ?? 'text';

            if ($profileId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Please select an AI profile.']);
                exit;
            }
            if ($content === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Content is required.']);
                exit;
            }

            $profile = UserAiVendorSettings::find($profileId);
            if (!$profile) {
                http_response_code(400);
                echo json_encode(['error' => 'Selected AI profile was not found.']);
                exit;
            }

            $keyRow = UserAiVendorKeys::findForUserVendor($profile['user_id'], $profile['vendor']);
            if (!$keyRow) {
                http_response_code(400);
                echo json_encode(['error' => 'No API key configured for vendor: ' . $profile['vendor']]);
                exit;
            }

            $apiKey = decrypt_string($keyRow['encrypted_api_key'], ai_encryption_key());
            $aiClient = new \App\Lib\Ai\AiProviderClient($profile['vendor'], $profile['model'], $profile['endpoint_kind'], $apiKey);
            $persona = self::findPersonaById($personaId);

            if ($mode === 'html') {
                $systemPrompt = 'You are a writing assistant. Improve the clarity, tone, and flow of the visible text content only. You MUST preserve ALL HTML tags, attributes, iframes, images, videos, figures, and embedded elements exactly as they appear — do not remove, modify, wrap, or reorder any HTML element. Only change the words inside text nodes. Make at least one meaningful wording improvement instead of echoing the draft unchanged. Return only the improved HTML with no markdown fences or explanations.';
            } else {
                $systemPrompt = 'You are a helpful writing assistant. Improve the provided plain text for clarity, tone, and flow. Make at least one meaningful wording improvement instead of echoing the draft unchanged. Return only the improved plain text with no markdown fences, explanations, or prose.';
            }

            if ($persona) {
                $systemPrompt .= "\n\nPersona guidance:\n" . trim((string) $persona['system_prompt']) . "\n\nApply the persona only to tone, voice, and rhetorical framing. Preserve factual meaning and all structural constraints.";
            }

            $res = $aiClient->chat($systemPrompt, $content);
            if (!$res['ok']) {
                audit_log_event('ai_request', 'ai_process_text', 'provider_error', [
                    'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                    'http_status' => 502,
                    'metadata' => [
                        'profile_id' => $profileId,
                        'vendor' => $profile['vendor'] ?? '',
                        'model' => $profile['model'] ?? '',
                        'endpoint_kind' => $profile['endpoint_kind'] ?? '',
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                        'error' => $res['error'] ?? 'AI request failed.',
                    ],
                ]);
                http_response_code(502);
                echo json_encode(['error' => $res['error'] ?? 'AI request failed.']);
                exit;
            }

            audit_log_event('ai_request', 'ai_process_text', 'success', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 200,
                'metadata' => [
                    'profile_id' => $profileId,
                    'vendor' => $profile['vendor'] ?? '',
                    'model' => $profile['model'] ?? '',
                    'endpoint_kind' => $profile['endpoint_kind'] ?? '',
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
            ]);
            echo json_encode(['result' => $res['text']]);
            exit;
        } catch (Throwable $e) {
            audit_log_event('ai_request', 'ai_process_text', 'error', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 500,
                'metadata' => [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error' => $e->getMessage(),
                ],
            ]);
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    public static function aiDescribeImage(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');
        $startedAt = microtime(true);
        $actorId = (int) (admin_identity()['id'] ?? 0);
        $limit = rate_limit_consume('ai_describe_image', rate_limit_subject_for_scope('ai_describe_image', $actorId > 0 ? $actorId : null));
        if (!$limit['allowed']) {
            self::emitRateLimitedJson('ai_describe_image', (int) $limit['retry_after'], $actorId);
        }

        try {
            $profileId = (int) ($_POST['profile_id'] ?? 0);
            $personaId = (int) ($_POST['persona_id'] ?? 0);
            $imageUrl = trim($_POST['image_url'] ?? '');
            $existingAltText = trim((string) ($_POST['existing_alt_text'] ?? ''));

            if ($profileId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Please select an AI profile.']);
                exit;
            }
            if ($imageUrl === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Image URL is required.']);
                exit;
            }

            $profile = UserAiVendorSettings::find($profileId);
            if (!$profile) {
                http_response_code(400);
                echo json_encode(['error' => 'Selected AI profile was not found.']);
                exit;
            }

            $visionSupport = UserAiVendorSettings::visionSupportStatus($profile);
            if (!$visionSupport['ok']) {
                http_response_code(422);
                echo json_encode([
                    'code' => $visionSupport['code'],
                    'error' => $visionSupport['message'],
                    'diagnostics' => $visionSupport['diagnostics'],
                ]);
                exit;
            }

            $keyRow = UserAiVendorKeys::findForUserVendor($profile['user_id'], $profile['vendor']);
            if (!$keyRow) {
                http_response_code(400);
                echo json_encode([
                    'code' => 'missing_api_key',
                    'error' => 'No API key configured for vendor: ' . $profile['vendor'],
                    'diagnostics' => $visionSupport['diagnostics'],
                ]);
                exit;
            }

            // Resolve image binary data
            $blob = null;
            $mimeType = 'image/jpeg';

            if (str_starts_with($imageUrl, '/api/media-assets/')) {
                $assetId = (int) basename($imageUrl);
                $asset = MediaAsset::find($assetId);
                if ($asset && !empty($asset['file_data'])) {
                    $blob = $asset['file_data'];
                    $mimeType = $asset['mime_type'] ?: 'image/jpeg';
                }
            } elseif (str_starts_with($imageUrl, '/api/media/')) {
                $filename = basename($imageUrl);
                $asset = MediaAsset::findByFilename($filename);
                if ($asset && !empty($asset['file_data'])) {
                    $blob = $asset['file_data'];
                    $mimeType = $asset['mime_type'] ?: 'image/jpeg';
                }
            } elseif (str_starts_with($imageUrl, '/media/')) {
                $id = (int) basename($imageUrl);
                $file = MediaFile::getData($id);
                if ($file && !empty($file['data'])) {
                    $blob = $file['data'];
                    $mimeType = $file['mime_type'] ?: 'image/jpeg';
                }
            } elseif (str_starts_with($imageUrl, '/image/')) {
                $id = (int) basename($imageUrl);
                $file = MediaFile::getData($id);
                if ($file && !empty($file['data'])) {
                    $blob = $file['data'];
                    $mimeType = $file['mime_type'] ?: 'image/jpeg';
                }
            }

            if ($blob === null) {
                http_response_code(400);
                echo json_encode([
                    'code' => 'image_load_failed',
                    'error' => 'Could not load image data from the provided URL.',
                    'diagnostics' => $visionSupport['diagnostics'],
                ]);
                exit;
            }

            $apiKey = decrypt_string($keyRow['encrypted_api_key'], ai_encryption_key());
            $aiClient = new \App\Lib\Ai\AiProviderClient($profile['vendor'], $profile['model'], $profile['endpoint_kind'], $apiKey);
            $persona = self::findPersonaById($personaId);

            $describePrompt = 'Write concise, factual alt text for this image. Return only the alt text. Keep it accessible, specific, and under 160 characters when possible.';
            if ($existingAltText !== '') {
                $describePrompt .= "\n\nExisting alt text to refine:\n" . $existingAltText;
            }
            if ($persona) {
                $describePrompt .= "\n\nOptional persona guidance:\n" . trim((string) $persona['system_prompt']) . "\n\nUse the persona only if it helps tone or focus, but keep the result factual and accessibility-first.";
            }

            $base64 = base64_encode($blob);
            $res = $aiClient->describeImage($base64, $mimeType, $describePrompt);
            if (!$res['ok']) {
                audit_log_event('ai_request', 'ai_describe_image', 'provider_error', [
                    'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                    'http_status' => 502,
                    'metadata' => [
                        'profile_id' => $profileId,
                        'vendor' => $profile['vendor'] ?? '',
                        'model' => $profile['model'] ?? '',
                        'endpoint_kind' => $profile['endpoint_kind'] ?? '',
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                        'error' => $res['error'] ?? 'AI request failed.',
                    ],
                ]);
                http_response_code(502);
                echo json_encode([
                    'code' => 'provider_request_failed',
                    'error' => $res['error'] ?? 'AI request failed.',
                    'diagnostics' => $visionSupport['diagnostics'],
                ]);
                exit;
            }

            audit_log_event('ai_request', 'ai_describe_image', 'success', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 200,
                'metadata' => [
                    'profile_id' => $profileId,
                    'vendor' => $profile['vendor'] ?? '',
                    'model' => $profile['model'] ?? '',
                    'endpoint_kind' => $profile['endpoint_kind'] ?? '',
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
            ]);
            echo json_encode([
                'result' => $res['text'],
                'diagnostics' => $visionSupport['diagnostics'],
            ]);
            exit;
        } catch (Throwable $e) {
            audit_log_event('ai_request', 'ai_describe_image', 'error', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 500,
                'metadata' => [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error' => $e->getMessage(),
                ],
            ]);
            http_response_code(500);
            echo json_encode([
                'code' => 'unexpected_error',
                'error' => $e->getMessage(),
            ]);
            exit;
        }
    }

    public static function refineAi(): void
    {
        set_time_limit(660); // 5 attempts × 2 min + buffer
        admin_check();
        header('Content-Type: application/json; charset=utf-8');
        $startedAt = microtime(true);
        $actorId = (int) (admin_identity()['id'] ?? 0);
        $limit = rate_limit_consume('ai_refine_piece', rate_limit_subject_for_scope('ai_refine_piece', $actorId > 0 ? $actorId : null));
        if (!$limit['allowed']) {
            self::emitRateLimitedJson('ai_refine_piece', (int) $limit['retry_after'], $actorId);
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $prompt = trim((string) ($input['prompt'] ?? ''));
            $engine = trim((string) ($input['engine'] ?? 'p5'));
            $profileId = (int) ($input['profile_id'] ?? 0);
            $personaId = (int) ($input['persona_id'] ?? 0);
            $html = (string) ($input['html_code'] ?? '');
            $css = (string) ($input['css_code'] ?? '');
            $js = (string) ($input['generated_code'] ?? '');
            $originalPrompt = trim((string) ($input['original_prompt'] ?? ''));

            if ($prompt === '') {
                throw new InvalidArgumentException('Prompt is required.');
            }
            if ($profileId <= 0) {
                throw new InvalidArgumentException('Please select an active AI profile.');
            }

            $profile = UserAiVendorSettings::find($profileId);
            if (!$profile) {
                throw new InvalidArgumentException('Selected AI profile was not found.');
            }

            $keyRow = UserAiVendorKeys::findForUserVendor($profile['user_id'], $profile['vendor']);
            if (!$keyRow) {
                throw new InvalidArgumentException("No API key configured for vendor: " . $profile['vendor']);
            }

            $apiKey = decrypt_string($keyRow['encrypted_api_key'], ai_encryption_key());
            $aiClient = new \App\Lib\Ai\AiProviderClient($profile['vendor'], $profile['model'], $profile['endpoint_kind'], $apiKey);
            $persona = self::findPersonaById($personaId);

            $attemptCount = 0;
            $success = false;
            $htmlCode = '';
            $cssCode = '';
            $generatedCode = '';
            $plan = '';
            $previousRawResponse = null;
            $lastError = '';

            while ($attemptCount < ART_PIECE_MAX_ATTEMPTS) {
                $attemptCount++;
                $systemPrompt = art_piece_refine_system_prompt($engine);
                if ($persona) {
                    $systemPrompt .= "\n\nPersona guidance:\n" . trim((string) $persona['system_prompt']) . "\n\nUse the persona to influence style and creative direction, but still obey all engine, safety, and output-format requirements.";
                }

                if ($attemptCount === 1) {
                    $userPromptForApi = art_piece_refine_user_prompt($engine, $prompt, $html, $css, $js, $originalPrompt ?: null);
                } else {
                    $userPromptForApi = art_piece_refine_repair_prompt($engine, $prompt, $previousRawResponse, $lastError, $html, $css, $js);
                }

                // suppressPlanningPreamble=false: the PLAN+PATCH protocol
                // requires a PLAN section — leaving the old "skip planning
                // notes" instruction on (correct for fresh generation, which
                // calls this same client) would directly contradict it.
                // maxTokensOverride is raised because a patch's output cost
                // is structurally higher than fresh generation's: every
                // patch reproduces a verbatim SEARCH anchor on top of its
                // REPLACE content.
                $res = $aiClient->generate($systemPrompt, $userPromptForApi, suppressPlanningPreamble: false, maxTokensOverride: 24576);
                if (!$res['ok']) {
                    $lastError = $res['error'] ?? 'API error';
                    continue;
                }
                if (\App\Lib\Ai\AiProviderClient::finishReasonMeansTruncated($res['finishReason'] ?? null)) {
                    $lastError = "The AI's response was cut off before finishing (token limit reached) — try a smaller, more specific instruction.";
                    $previousRawResponse = $res['text'];
                    continue;
                }

                $rawText = $res['text'];
                $previousRawResponse = $rawText;

                // Apply the AI's patches against the ORIGINAL code (not a
                // regenerated file) — anything not named in a patch is
                // carried forward unchanged, which is the actual guarantee
                // that an unscoped refinement can't quietly rewrite the rest
                // of the piece.
                try {
                    $patches = art_piece_extract_refine_patches($rawText);

                    // A response with zero patches across every file is not
                    // a legitimate "nothing needed changing" outcome here —
                    // the admin always asked for a real, visible change. Left
                    // unchecked this silently "succeeds" by returning the
                    // original code untouched, which is indistinguishable
                    // from the refinement never having happened at all.
                    if (!$patches['html'] && !$patches['css'] && !$patches['js']) {
                        throw new RuntimeException('AI response contained no valid PATCH blocks in the required format — at least one PATCH is required to make the requested change.');
                    }

                    $extractedHtml = art_piece_apply_refine_patches($html, $patches['html']);
                    $extractedCss = art_piece_apply_refine_patches($css, $patches['css']);
                    $extractedJs = art_piece_apply_refine_patches($js, $patches['js']);

                    if ($extractedHtml === '' && $engine !== 'svg') {
                        throw new RuntimeException('HTML is empty after applying patches');
                    }
                    if ($extractedJs !== '') {
                        art_piece_preflight_code($engine, $extractedJs);
                    } elseif ($engine !== 'svg') {
                        throw new RuntimeException('JavaScript is empty after applying patches');
                    }

                    // Canvas & SVG Preservation Constraints
                    if (in_array($engine, ['p5', 'c2', 'three'], true)) {
                        if (!empty($patches['html'])) {
                            throw new RuntimeException('HTML changes are not allowed for p5, c2, and three engine types. The canvas is automatically managed. Focus your edits on CSS or JS instead.');
                        }
                        if (preg_match('/(?:canvas|#container|#scene|#c2-canvas)\s*\{[^}]*\bdisplay\s*:\s*none\b/i', $extractedCss)) {
                            throw new RuntimeException('CSS cannot hide the canvas or container element (display: none is forbidden).');
                        }
                        if (preg_match('/(?:canvas|#container|#scene|#c2-canvas)\s*\{[^}]*\bvisibility\s*:\s*hidden\b/i', $extractedCss)) {
                            throw new RuntimeException('CSS cannot hide the canvas or container element (visibility: hidden is forbidden).');
                        }
                    }

                    if ($engine === 'svg') {
                        if (!preg_match('/<svg/i', $extractedHtml)) {
                            throw new RuntimeException('HTML code must contain an <svg> element for SVG pieces.');
                        }
                        if (preg_match('/(?:svg|#container)\s*\{[^}]*\bdisplay\s*:\s*none\b/i', $extractedCss)) {
                            throw new RuntimeException('CSS cannot hide the SVG or container element (display: none is forbidden).');
                        }
                        if (preg_match('/(?:svg|#container)\s*\{[^}]*\bvisibility\s*:\s*hidden\b/i', $extractedCss)) {
                            throw new RuntimeException('CSS cannot hide the SVG or container element (visibility: hidden is forbidden).');
                        }
                    }

                    // Success!
                    $htmlCode = $extractedHtml;
                    $cssCode = $extractedCss;
                    $generatedCode = $extractedJs;
                    $plan = art_piece_extract_refine_plan($rawText);
                    $success = true;
                    break;
                } catch (Throwable $e) {
                    $lastError = $e->getMessage();
                }
            }

            if (!$success) {
                throw new RuntimeException('All AI refinement attempts failed validation: ' . $lastError);
            }

            echo json_encode([
                'success' => true,
                'html_code' => $htmlCode,
                'css_code' => $cssCode,
                'generated_code' => $generatedCode,
                // The AI's stated plan before patching, surfaced to the
                // admin alongside the diff for the same before-acting
                // visibility a plan gives.
                'plan' => $plan,
                // Echoed back so the client can carry these through to the
                // version that gets created when the accepted code is saved.
                'profile_id' => $profileId,
                'persona_id' => $personaId > 0 ? $personaId : null,
            ]);
            audit_log_event('ai_request', 'ai_refine_piece', 'success', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 200,
                'metadata' => [
                    'profile_id' => $profileId,
                    'vendor' => $profile['vendor'] ?? '',
                    'model' => $profile['model'] ?? '',
                    'endpoint_kind' => $profile['endpoint_kind'] ?? '',
                    'engine' => $engine,
                    'attempt_count' => $attemptCount,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    // Truncated raw model response, so a future "succeeded
                    // but did nothing useful" report can be diagnosed from
                    // the log directly instead of by inference.
                    'raw_response' => mb_substr((string) $previousRawResponse, 0, 4000),
                ],
            ]);
            exit;

        } catch (Throwable $e) {
            audit_log_event('ai_request', 'ai_refine_piece', 'error', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 500,
                'metadata' => [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error' => $e->getMessage(),
                    'raw_response' => mb_substr((string) ($previousRawResponse ?? ''), 0, 4000),
                ],
            ]);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
            exit;
        }
    }

    /**
     * Saves an accepted AI Refine suggestion as a new version immediately —
     * no separate "Save Changes" submit required. The version's prompt is
     * the refinement instruction that produced it (what was actually asked
     * for), not the piece's original creative prompt — those are different
     * things and conflating them was the bug that made every version show
     * the same original prompt regardless of what each refinement actually
     * changed.
     */
    public static function refineSave(string $id): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        $piece = PlatformArtPiece::find((int) $id);
        if (!$piece) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Piece not found.']);
            exit;
        }

        $currentVersion = $piece['current_version'] ?? null;
        if (!$currentVersion) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'This piece has no current version to refine.']);
            exit;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $html = (string) ($input['html_code'] ?? '');
            $css = (string) ($input['css_code'] ?? '');
            $js = (string) ($input['generated_code'] ?? '');
            $refinementPrompt = trim((string) ($input['refinement_prompt'] ?? ''));
            $profileId = (int) ($input['profile_id'] ?? 0) ?: null;
            $personaId = (int) ($input['persona_id'] ?? 0) ?: null;

            if ($refinementPrompt === '') {
                throw new InvalidArgumentException('Refinement prompt is required.');
            }

            $codeChanged = self::normalizeCode($html) !== self::normalizeCode($currentVersion['html_code'] ?? null)
                || self::normalizeCode($css) !== self::normalizeCode($currentVersion['css_code'] ?? null)
                || self::normalizeCode($js) !== self::normalizeCode($currentVersion['generated_code'] ?? null);

            if (!$codeChanged) {
                echo json_encode([
                    'success' => true,
                    'changed' => false,
                    'version_id' => (int) $currentVersion['id'],
                    'version_number' => (int) $currentVersion['version_number'],
                ]);
                exit;
            }

            $versionNumber = PlatformArtPieceVersion::nextVersionNumber((int) $id);
            $versionId = PlatformArtPieceVersion::create([
                'art_piece_id' => (int) $id,
                'version_number' => $versionNumber,
                'prompt' => $refinementPrompt,
                'structured_spec' => $currentVersion['structured_spec'] ?? null,
                'html_code' => self::normalizeCode($html),
                'css_code' => self::normalizeCode($css),
                'generated_code' => self::normalizeCode($js),
                'engine' => $currentVersion['engine'] ?? $piece['engine'],
                'generation_vendor' => $currentVersion['generation_vendor'] ?? null,
                'generation_model' => $currentVersion['generation_model'] ?? null,
                'validation_status' => $currentVersion['validation_status'] ?? null,
                'generation_attempt_count' => $currentVersion['generation_attempt_count'] ?? 0,
                'notes' => 'Saved via AI Refine accept.',
                'ai_profile_id' => $profileId,
                'ai_persona_id' => $personaId,
            ]);
            PlatformArtPiece::updateCurrentVersion((int) $id, $versionId);

            echo json_encode([
                'success' => true,
                'changed' => true,
                'version_id' => $versionId,
                'version_number' => $versionNumber,
            ]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private static function emitRateLimitedJson(string $scope, int $retryAfter, int $actorId): void
    {
        audit_log_event('ai_request', $scope, 'throttled', [
            'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
            'http_status' => 429,
            'metadata' => ['retry_after' => $retryAfter],
        ]);
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        echo json_encode(['error' => 'Too many requests. Please wait and try again.']);
        exit;
    }

    private static function ensureWritableSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private static function writeGenerateProgress(?int $attempt, string $engine): void
    {
        self::ensureWritableSession();
        unset($_SESSION['pending_generation']);
        $_SESSION['generate_progress'] = [
            'attempt' => $attempt,
            'max_attempts' => ART_PIECE_MAX_ATTEMPTS,
            'engine' => $engine,
            'complete' => false,
            'updated_at' => time(),
        ];
        session_write_close();
    }

    private static function storePendingGeneration(array $pending): void
    {
        self::ensureWritableSession();
        $_SESSION['pending_generation'] = $pending;
        $_SESSION['generate_progress'] = [
            'attempt' => (int) ($pending['attempt_count'] ?? 1),
            'max_attempts' => ART_PIECE_MAX_ATTEMPTS,
            'engine' => (string) ($pending['engine'] ?? ''),
            'complete' => true,
            'updated_at' => time(),
        ];
        session_write_close();
    }

    private static function renderRateLimitedHtml(int $retryAfter, string $message, int $profileId, int $personaId): void
    {
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        $profiles = db()->query("SELECT uavs.*, u.name AS user_name FROM user_ai_vendor_settings uavs JOIN users u ON u.id = uavs.user_id WHERE uavs.enabled = 1 ORDER BY uavs.profile_name ASC")->fetchAll();
        $personas = self::loadPersonas();
        $prompt = trim((string) ($_POST['prompt'] ?? ''));
        $engine = trim((string) ($_POST['engine'] ?? 'p5'));
        $error = $message;
        $selectedProfileId = $profileId > 0 ? $profileId : null;
        $selectedPersonaId = $personaId > 0 ? $personaId : null;
        $attemptLogs = null;
        require dirname(__DIR__, 2) . '/views/admin/pieces/generate-form.php';
        exit;
    }
}
