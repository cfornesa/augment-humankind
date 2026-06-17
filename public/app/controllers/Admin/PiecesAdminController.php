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
            return [
                'id' => (int) $profile['id'],
                'profile_name' => $profile['profile_name'] ?? '',
                'vendor' => $profile['vendor'] ?? '',
                'model' => $profile['model'] ?? '',
                'user_name' => $profile['user_name'] ?? '',
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
        [$profiles, $preferredProfileId] = self::loadProfilesData();
        require dirname(__DIR__, 2) . '/views/admin/pieces/form.php';
    }

    public static function store(): void
    {
        admin_check();

        try {
            $data = self::resolvePieceData();
            $pieceId = PlatformArtPiece::create($data);
            PlatformArtPiece::syncCategories($pieceId, $data['category_ids']);

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
            [$profiles, $preferredProfileId] = self::loadProfilesData();
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
        [$profiles, $preferredProfileId] = self::loadProfilesData();
        require dirname(__DIR__, 2) . '/views/admin/pieces/form.php';
    }

    public static function update(string $id): void
    {
        admin_check();
        $existing = PlatformArtPiece::find((int) $id);
        if (!$existing) {
            header('Location: /admin/pieces');
            exit;
        }

        try {
            $data = self::resolvePieceData();
            $data['sort_order'] = (int) ($existing['sort_order'] ?? 0);
            PlatformArtPiece::update((int) $id, $data);
            PlatformArtPiece::syncCategories((int) $id, $data['category_ids']);

            $code = self::resolveVersionCodeFromPost();
            if (self::hasAnyVersionCode($code)) {
                $currentVersion = $existing['current_version'] ?? null;
                if ($currentVersion) {
                    $merged = $currentVersion; // start from full existing row
                    $merged['html_code'] = $code['html_code'];
                    $merged['css_code'] = $code['css_code'];
                    $merged['generated_code'] = $code['generated_code'] ?? ($currentVersion['generated_code'] ?? '');
                    $merged['engine'] = $data['engine']; // keep version engine in sync with piece engine
                    PlatformArtPieceVersion::update((int) $currentVersion['id'], $merged);
                } else {
                    $versionId = PlatformArtPieceVersion::create([
                        'art_piece_id' => (int) $id,
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
                    PlatformArtPiece::updateCurrentVersion((int) $id, $versionId);
                }
            }

            header('Location: /admin/pieces');
        } catch (Throwable $e) {
            $piece = self::draftPieceFromPost((int) $id);
            $error = $e->getMessage();
            $artMedia = Category::all();
            $assignedCategoryIds = $piece['category_ids'];
            [$profiles, $preferredProfileId] = self::loadProfilesData();
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
            header('Location: /admin/pieces/' . $id . '/versions');
        } catch (Throwable $e) {
            $version = self::draftVersionFromPost();
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/pieces/version-form.php';
        }
        exit;
    }

    public static function versionDelete(string $id, string $vid): void
    {
        admin_check();
        PlatformArtPieceVersion::delete((int) $vid);
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
        return [$profiles, $preferredProfileId];
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
            'sort_order' => isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : null,
            'comments_enabled' => isset($_POST['comments_enabled']) ? 1 : 0,
            'category_ids' => array_map('intval', $_POST['category_ids'] ?? []),
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
        ];
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

        echo json_encode(['ok' => true, 'url' => $url]);
        exit;
    }

    public static function generateForm(): void
    {
        admin_check();
        $profiles = db()->query("SELECT uavs.*, u.name AS user_name FROM user_ai_vendor_settings uavs JOIN users u ON u.id = uavs.user_id WHERE uavs.enabled = 1 ORDER BY uavs.profile_name ASC")->fetchAll();
        $error = null;
        $prompt = '';
        $engine = 'p5';
        $selectedProfileId = null;
        $attemptLogs = null;

        // Pre-select owner preferred art piece profile
        $owner = PlatformUser::owner();
        if ($owner && !empty($owner['preferred_art_piece_profile_id'])) {
            $selectedProfileId = (int) $owner['preferred_art_piece_profile_id'];
        }

        require dirname(__DIR__, 2) . '/views/admin/pieces/generate-form.php';
    }

    public static function generate(): void
    {
        set_time_limit(660); // 5 attempts × 2 min + buffer
        admin_check();
        $prompt = trim($_POST['prompt'] ?? '');
        $engine = trim($_POST['engine'] ?? 'p5');
        $profileId = (int) ($_POST['profile_id'] ?? 0);

        $profiles = db()->query("SELECT uavs.*, u.name AS user_name FROM user_ai_vendor_settings uavs JOIN users u ON u.id = uavs.user_id WHERE uavs.enabled = 1 ORDER BY uavs.profile_name ASC")->fetchAll();

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
                    $userPromptForApi = $prompt;
                } else {
                    $userPromptForApi = art_piece_repair_prompt($engine, $prompt, $previousRawResponse, $lastError);
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
            require dirname(__DIR__, 2) . '/views/admin/pieces/generate-preview.php';

        } catch (Throwable $e) {
            $error = $e->getMessage();
            $selectedProfileId = $profileId;
            require dirname(__DIR__, 2) . '/views/admin/pieces/generate-form.php';
        }
    }

    public static function generateSave(): void
    {
        admin_check();

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
            ]);

            PlatformArtPiece::updateCurrentVersion($pieceId, $versionId);

            header('Location: /admin/pieces');
        } catch (Throwable $e) {
            header('Location: /admin/pieces?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function aiProcessText(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $profileId = (int) ($_POST['profile_id'] ?? 0);
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

            if ($mode === 'html') {
                $systemPrompt = 'You are a helpful writing assistant. Improve the provided HTML text while preserving all HTML tags and structure. Return only the improved HTML with no markdown fences, explanations, or prose.';
            } else {
                $systemPrompt = 'You are a helpful writing assistant. Improve the provided plain text for clarity, tone, and flow. Return only the improved plain text with no markdown fences, explanations, or prose.';
            }

            $res = $aiClient->chat($systemPrompt, $content);
            if (!$res['ok']) {
                http_response_code(502);
                echo json_encode(['error' => $res['error'] ?? 'AI request failed.']);
                exit;
            }

            echo json_encode(['result' => $res['text']]);
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    public static function aiDescribeImage(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $profileId = (int) ($_POST['profile_id'] ?? 0);
            $imageUrl = trim($_POST['image_url'] ?? '');

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

            $keyRow = UserAiVendorKeys::findForUserVendor($profile['user_id'], $profile['vendor']);
            if (!$keyRow) {
                http_response_code(400);
                echo json_encode(['error' => 'No API key configured for vendor: ' . $profile['vendor']]);
                exit;
            }

            // Resolve image binary data
            $blob = null;
            $mimeType = 'image/jpeg';

            if (str_starts_with($imageUrl, '/api/media/')) {
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
                echo json_encode(['error' => 'Could not load image data from the provided URL.']);
                exit;
            }

            $apiKey = decrypt_string($keyRow['encrypted_api_key'], ai_encryption_key());
            $aiClient = new \App\Lib\Ai\AiProviderClient($profile['vendor'], $profile['model'], $profile['endpoint_kind'], $apiKey);

            $base64 = base64_encode($blob);
            $res = $aiClient->describeImage($base64, $mimeType);
            if (!$res['ok']) {
                http_response_code(502);
                echo json_encode(['error' => $res['error'] ?? 'AI request failed.']);
                exit;
            }

            echo json_encode(['result' => $res['text']]);
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }

    public static function refineAi(): void
    {
        set_time_limit(660); // 5 attempts × 2 min + buffer
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $prompt = trim((string) ($input['prompt'] ?? ''));
            $engine = trim((string) ($input['engine'] ?? 'p5'));
            $profileId = (int) ($input['profile_id'] ?? 0);
            $html = (string) ($input['html_code'] ?? '');
            $css = (string) ($input['css_code'] ?? '');
            $js = (string) ($input['generated_code'] ?? '');

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

            $attemptCount = 0;
            $success = false;
            $htmlCode = '';
            $cssCode = '';
            $generatedCode = '';
            $previousRawResponse = null;
            $lastError = '';

            while ($attemptCount < ART_PIECE_MAX_ATTEMPTS) {
                $attemptCount++;
                $systemPrompt = art_piece_refine_system_prompt($engine);

                if ($attemptCount === 1) {
                    $userPromptForApi = art_piece_refine_user_prompt($engine, $prompt, $html, $css, $js);
                } else {
                    $userPromptForApi = art_piece_repair_prompt($engine, $prompt, $previousRawResponse, $lastError);
                }

                $res = $aiClient->generate($systemPrompt, $userPromptForApi);
                if (!$res['ok']) {
                    $lastError = $res['error'] ?? 'API error';
                    continue;
                }

                $rawText = $res['text'];
                $previousRawResponse = $rawText;

                // Extract blocks
                $blocks = art_piece_extract_code_blocks($rawText);
                $extractedHtml = $blocks['htmlCode'] ?? '';
                $extractedCss = $blocks['cssCode'] ?? '';
                $extractedJs = $blocks['generatedCode'] ?? '';

                // SVG edge case: AI may omit JS block for CSS-only animations
                if ($engine === 'svg' && $extractedJs === '') {
                    $extractedJs = 'window.sketch = () => {};';
                }

                // Provide sensible defaults if the AI omitted them
                if ($extractedHtml === '') {
                    $extractedHtml = match ($engine) {
                        'svg' => '<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"></svg>',
                        'p5' => '<div id="canvas-container"></div>',
                        default => '<div id="container"></div>',
                    };
                }
                if ($extractedCss === '') {
                    $extractedCss = 'body, html { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; }';
                }

                // Preflight validation
                try {
                    if ($extractedHtml === '' && $engine !== 'svg') {
                        throw new RuntimeException('HTML block is empty');
                    }
                    if ($extractedJs !== '') {
                        art_piece_preflight_code($engine, $extractedJs);
                    } elseif ($engine !== 'svg') {
                        throw new RuntimeException('JavaScript block is empty');
                    }

                    // Success!
                    $htmlCode = $extractedHtml;
                    $cssCode = $extractedCss;
                    $generatedCode = $extractedJs;
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
            ]);
            exit;

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
            exit;
        }
    }
}
