<?php

declare(strict_types=1);

class PiecesAdminController
{
    public static function index(): void
    {
        admin_check();

        $tab = (string) ($_GET['tab'] ?? 'art-pieces');
        if (!in_array($tab, ['art-pieces', 'templates'], true)) {
            $tab = 'art-pieces';
        }

        $q      = trim((string) ($_GET['q'] ?? ''));
        $engine = (string) ($_GET['engine'] ?? '');
        $sort   = (string) ($_GET['sort'] ?? 'sort_order');
        $dir    = strtolower((string) ($_GET['dir'] ?? 'asc'));

        $allowedSorts = ['sort_order', 'newest', 'title', 'engine', 'status', 'created', 'updated', 'relevance'];
        if (!in_array($sort, $allowedSorts, true) || ($sort === 'relevance' && $q === '')) {
            $sort = 'sort_order';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }
        $allowedEngineFilters = array_merge(art_piece_supported_engines(), ['c2_interactive']);
        if (!in_array($engine, $allowedEngineFilters, true)) {
            $engine = '';
        }

        $pieces = PlatformArtPiece::allForAdmin(
            $q !== '' ? $q : null,
            ($engine !== '' && $engine !== 'c2_interactive') ? $engine : null,
            $sort,
            $dir
        );
        if ($engine === 'c2_interactive') {
            $pieces = array_values(array_filter($pieces, static function (array $piece): bool {
                return art_piece_version_generation_mode((array) ($piece['current_version'] ?? []), $piece) === 'c2_interactive';
            }));
        }
        $templatesTableReady = class_exists('ArtPieceStarterTemplate') ? ArtPieceStarterTemplate::tableReady() : false;
        $templates = $tab === 'templates' && $templatesTableReady ? ArtPieceStarterTemplate::all() : [];

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
        $defaultTemplate = class_exists('ArtPieceStarterTemplate') ? ArtPieceStarterTemplate::defaultForMode('p5') : false;
        $piece = [
            'engine' => 'p5',
            'status' => 'active',
            'current_version' => $defaultTemplate ? [
                'html_code' => $defaultTemplate['html_code'] ?? '',
                'css_code' => $defaultTemplate['css_code'] ?? '',
                'generated_code' => $defaultTemplate['js_code'] ?? '',
                'engine' => 'p5',
            ] : [],
        ];
        $error = null;
        $artMedia = Category::all();
        $assignedCategoryIds = [];
        [$profiles, $preferredProfileId, $personas] = self::loadProfilesData();
        $starterTemplates = class_exists('ArtPieceStarterTemplate') ? ArtPieceStarterTemplate::defaultMap() : [];
        require dirname(__DIR__, 2) . '/views/admin/pieces/form.php';
    }

    public static function templates(): void
    {
        admin_check();
        header('Location: /admin/pieces?tab=templates', true, 301);
        exit;
    }

    public static function templateEdit(string $id): void
    {
        admin_check();
        $template = ArtPieceStarterTemplate::find((int) $id);
        if (!$template) {
            header('Location: /admin/pieces?tab=templates');
            exit;
        }
        $templateError = null;
        require dirname(__DIR__, 2) . '/views/admin/pieces/template-form.php';
    }

    public static function templateUpdate(string $id): void
    {
        admin_check();
        $template = ArtPieceStarterTemplate::find((int) $id);
        if (!$template) {
            header('Location: /admin/pieces?tab=templates');
            exit;
        }

        try {
            $engine = (string) ($template['engine'] ?? 'p5');
            $generationMode = art_piece_normalize_generation_mode((string) ($template['generation_mode'] ?? $engine), $engine);
            $html = (string) ($_POST['html_code'] ?? '');
            $css = (string) ($_POST['css_code'] ?? '');
            $js = (string) ($_POST['js_code'] ?? '');
            if (function_exists('art_piece_preflight_document')) {
                art_piece_preflight_document($engine, $html, $css, $js, $generationMode);
            }
            ArtPieceStarterTemplate::update((int) $id, $_POST);
            header('Location: /admin/pieces?tab=templates');
            exit;
        } catch (Throwable $e) {
            $template = array_merge($template, $_POST);
            $templateError = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/pieces/template-form.php';
        }
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
                $generationMode = self::requestedGenerationModeFromPost($data['engine']);
                art_piece_preflight_document($data['engine'], $code['html_code'], $code['css_code'], $code['generated_code'], $generationMode);
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
                    'generation_mode' => $generationMode,
                    'validation_status' => null,
                    'generation_attempt_count' => 0,
                    'notes' => null,
                    'sonic_params' => $data['sonic_params'] ?? null,
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
            $starterTemplates = class_exists('ArtPieceStarterTemplate') ? ArtPieceStarterTemplate::defaultMap() : [];
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
        $starterTemplates = class_exists('ArtPieceStarterTemplate') ? ArtPieceStarterTemplate::defaultMap() : [];
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
                $nextSonicParams = self::resolveSonicParamsFromPost($currentVersion['sonic_params'] ?? null);
                $codeChanged = !$currentVersion
                    || self::normalizeCode($code['html_code']) !== self::normalizeCode($currentVersion['html_code'] ?? null)
                    || self::normalizeCode($code['css_code']) !== self::normalizeCode($currentVersion['css_code'] ?? null)
                    || self::normalizeCode($code['generated_code']) !== self::normalizeCode($currentVersion['generated_code'] ?? null);
                $sonicChanged = $currentVersion
                    && art_piece_sonic_params_supported()
                    && !art_piece_sonic_params_equal($nextSonicParams, $currentVersion['sonic_params'] ?? null);

                if ($currentVersion && !$codeChanged && !$sonicChanged) {
                    // Code and AI-authored sonic content are both unchanged.
                    // Audio-tab extras (voice visibility, volume, synth
                    // tuning) are mechanical settings, not creative content —
                    // update them in place on the current version row rather
                    // than forking a new version, so routine tweaks don't
                    // clutter version/AI-refine history.
                    if (art_piece_sonic_params_supported() && $nextSonicParams !== ($currentVersion['sonic_params'] ?? null)) {
                        PlatformArtPieceVersion::update((int) $currentVersion['id'], array_merge($currentVersion, [
                            'sonic_params' => $nextSonicParams,
                        ]));
                    }
                } else {
                    $generationMode = self::resolveVersionGenerationModeForUpdate($data['engine'], $currentVersion);
                    $versionCode = $codeChanged || !$currentVersion ? $code : [
                        'html_code' => $currentVersion['html_code'] ?? null,
                        'css_code' => $currentVersion['css_code'] ?? null,
                        'generated_code' => $currentVersion['generated_code'] ?? '',
                    ];
                    if ($codeChanged) {
                        // Only validate code that's actually being saved new —
                        // re-running preflight against unchanged code (e.g. a
                        // sonic-only save) can spuriously reject an
                        // already-live, already-working piece over a rule it
                        // was never checked against on whatever path first
                        // saved it.
                        art_piece_preflight_document($data['engine'], $versionCode['html_code'], $versionCode['css_code'], $versionCode['generated_code'], $generationMode);
                    }
                    // Every code-changing save creates a new version rather
                    // than overwriting the current one in place, so version
                    // history is meaningful and "Revert" has something to
                    // revert to. Tone-only saves also create versions because
                    // sound is authored per version.
                    $versionId = PlatformArtPieceVersion::create([
                        'art_piece_id' => (int) $id,
                        'version_number' => PlatformArtPieceVersion::nextVersionNumber((int) $id),
                        'prompt' => $data['prompt'] !== null && $data['prompt'] !== ''
                            ? $data['prompt']
                            : ($currentVersion['prompt'] ?? $data['title']),
                        'structured_spec' => $currentVersion['structured_spec'] ?? null,
                        'html_code' => self::normalizeCode($versionCode['html_code']),
                        'css_code' => self::normalizeCode($versionCode['css_code']),
                        'generated_code' => self::normalizeCode($versionCode['generated_code'] ?? ($currentVersion['generated_code'] ?? '')),
                        'engine' => $data['engine'],
                        'generation_vendor' => $currentVersion['generation_vendor'] ?? null,
                        'generation_model' => $currentVersion['generation_model'] ?? null,
                        'generation_mode' => $generationMode,
                        'validation_status' => $currentVersion['validation_status'] ?? null,
                        'generation_attempt_count' => $currentVersion['generation_attempt_count'] ?? 0,
                        'notes' => null,
                        'ai_profile_id' => $data['ai_profile_id'] ?? null,
                        'ai_persona_id' => $data['ai_persona_id'] ?? null,
                        'sonic_params' => $nextSonicParams,
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
            $starterTemplates = class_exists('ArtPieceStarterTemplate') ? ArtPieceStarterTemplate::defaultMap() : [];
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
        $versions = PlatformArtPieceVersion::allForPieceIncludingDrafts((int) $id);
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
        // Defense in depth: the Versions UI already hides Revert for
        // draft-attempt rows, but this guards the endpoint itself against
        // a stale tab or a direct POST — an AI Refine draft attempt must
        // never become the piece's current version except via the
        // explicit Accept flow (refineSave(), which promotes it properly).
        $version = PlatformArtPieceVersion::find((int) $vid);
        if ($version && (int) ($version['is_draft_attempt'] ?? 0) === 1) {
            header('Location: /admin/pieces/' . $id . '/versions');
            exit;
        }
        PlatformArtPiece::updateCurrentVersion((int) $id, (int) $vid);
        header('Location: /admin/pieces/' . $id . '/versions');
        exit;
    }

    // "Revive it as a new piece" — lets the admin salvage a non-revertible
    // draft attempt (or any version) by copying its code into a brand new,
    // fully independent art_pieces row, rather than only being able to
    // hand-edit it in place on the original piece. Created as 'draft'
    // status (not immediately public) since the source code may be a
    // failed attempt that was never validated as renderable.
    public static function versionFork(string $id, string $vid): void
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

        $engine = $version['engine'] ?? ($piece['engine'] ?? 'p5');
        $newPieceId = PlatformArtPiece::create([
            'title' => trim((string) ($piece['title'] ?? 'Untitled')) . ' (forked from v' . (int) $version['version_number'] . ')',
            'prompt' => $version['prompt'] ?? null,
            'engine' => $engine,
            'status' => 'draft',
        ]);
        $newVersionId = PlatformArtPieceVersion::create([
            'art_piece_id' => $newPieceId,
            'version_number' => 1,
            'prompt' => $version['prompt'] ?? null,
            'html_code' => $version['html_code'] ?? null,
            'css_code' => $version['css_code'] ?? null,
            'generated_code' => $version['generated_code'] ?? null,
            'engine' => $engine,
            'generation_mode' => self::storedGenerationMode($version, $engine),
        ]);
        PlatformArtPiece::updateCurrentVersion($newPieceId, $newVersionId);

        header('Location: /admin/pieces/' . $newPieceId . '/edit');
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

        $engine = art_piece_generation_mode_to_engine($_POST['engine'] ?? 'p5');
        if (!in_array($engine, art_piece_supported_engines(), true)) {
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
            'sonic_params' => self::resolveSonicParamsFromPost(),
        ];
    }

    private static function draftPieceFromPost(?int $existingId = null): array
    {
        $existing = $existingId ? PlatformArtPiece::find($existingId) : null;

        $draft = [
            'id' => $existingId,
            'title' => trim((string) ($_POST['title'] ?? ($existing['title'] ?? ''))),
            'prompt' => trim((string) ($_POST['prompt'] ?? ($existing['prompt'] ?? ''))),
            'engine' => art_piece_generation_mode_to_engine($_POST['engine'] ?? ($existing['engine'] ?? 'p5')),
            'status' => $_POST['status'] ?? ($existing['status'] ?? 'active'),
            'thumbnail_url' => trim((string) ($_POST['thumbnail_url'] ?? ($existing['thumbnail_url'] ?? ''))),
            'description' => trim((string) ($_POST['description'] ?? ($existing['description'] ?? ''))),
            'comments_enabled' => isset($_POST['comments_enabled']) ? 1 : ($existing['comments_enabled'] ?? 0),
            'category_ids' => array_map('intval', $_POST['category_ids'] ?? ($existing ? PlatformArtPiece::categoryIds((int) $existing['id']) : [])),
        ];
        $draft['current_version'] = array_merge(
            $existing['current_version'] ?? [],
            self::resolveVersionCodeFromPost(),
            ['sonic_params' => self::resolveSonicParamsFromPost($existing['current_version']['sonic_params'] ?? null)]
        );
        return $draft;
    }

    private static function getStandardHtmlForEngine(string $engine, ?string $defaultHtml = ''): string
    {
        switch ($engine) {
            case 'p5':
                return '<div id="canvas-container"></div>';
            case 'c2':
                return '<canvas id="piece-canvas"></canvas>';
            case 'three':
                return '<div id="container"></div>';
            case 'aframe':
            case 'svg':
            default:
                return $defaultHtml ?? '';
        }
    }

    private static function resolveVersionData(int $pieceId): array
    {
        $prompt = trim($_POST['prompt'] ?? '');
        if ($prompt === '') {
            throw new InvalidArgumentException('Prompt is required for a version.');
        }

        $engine = art_piece_generation_mode_to_engine($_POST['engine'] ?? 'p5');
        if (!in_array($engine, art_piece_supported_engines(), true)) {
            $engine = 'p5';
        }

        $html = trim($_POST['html_code'] ?? '') ?: null;
        if (in_array($engine, art_piece_canvas_managed_engines(), true)) {
            $html = self::getStandardHtmlForEngine($engine);
        }

        return [
            'art_piece_id' => $pieceId,
            'version_number' => PlatformArtPieceVersion::nextVersionNumber($pieceId),
            'prompt' => $prompt,
            'structured_spec' => trim($_POST['structured_spec'] ?? '') ?: null,
            'html_code' => $html,
            'css_code' => trim($_POST['css_code'] ?? '') ?: null,
            'generated_code' => trim($_POST['generated_code'] ?? '') ?: null,
            'engine' => $engine,
            'generation_vendor' => trim($_POST['generation_vendor'] ?? '') ?: null,
            'generation_model' => trim($_POST['generation_model'] ?? '') ?: null,
            'generation_mode' => self::requestedGenerationModeFromPost($engine),
            'validation_status' => $_POST['validation_status'] ?? 'validated',
            'generation_attempt_count' => (int) ($_POST['generation_attempt_count'] ?? 1),
            'notes' => trim($_POST['notes'] ?? '') ?: null,
            'ai_profile_id' => (int) ($_POST['ai_profile_id'] ?? 0) ?: null,
            'ai_persona_id' => (int) ($_POST['ai_persona_id'] ?? 0) ?: null,
            'sonic_params' => self::resolveSonicParamsFromPost(),
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
            'engine' => art_piece_generation_mode_to_engine($_POST['engine'] ?? 'p5'),
            'generation_vendor' => trim((string) ($_POST['generation_vendor'] ?? '')),
            'generation_model' => trim((string) ($_POST['generation_model'] ?? '')),
            'generation_mode' => self::requestedGenerationModeFromPost((string) ($_POST['engine'] ?? 'p5')),
            'validation_status' => $_POST['validation_status'] ?? 'validated',
            'generation_attempt_count' => (int) ($_POST['generation_attempt_count'] ?? 1),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
            'ai_profile_id' => (int) ($_POST['ai_profile_id'] ?? 0) ?: null,
            'ai_persona_id' => (int) ($_POST['ai_persona_id'] ?? 0) ?: null,
            'sonic_params' => self::resolveSonicParamsFromPost(),
        ];
    }

    // The manual Metadata-tab sound toggle was removed (generation and AI
    // Refine are the only paths that create/update sonic_params now), so the
    // main Save Changes form never submits sound_enabled/sound_feel — this
    // just preserves whatever the version already had.
    private static function resolveSonicParamsFromPost(?string $fallback = null): ?string
    {
        if (!art_piece_sonic_params_supported() || $fallback === null || trim($fallback) === '') {
            return null;
        }
        $decoded = json_decode($fallback, true);
        if (!is_array($decoded)) {
            return null;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $decoded['enabled'] = isset($_POST['sound_playback_active']);
        }
        $aiJson = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $extras = self::resolveSonicExtrasFromPost($decoded['extras'] ?? null);
        return art_piece_sonic_json_merge_extras($aiJson, $extras);
    }

    // Audio tab fields: mechanical, non-AI-authored per-piece settings
    // (public-visibility toggles per voice + admin-only synth tuning). Same
    // "trust the POST on a real submission, fall back to what's already
    // stored otherwise" pattern as sound_playback_active above.
    private static function resolveSonicExtrasFromPost(mixed $fallbackExtras): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return validate_art_piece_sonic_extras($fallbackExtras);
        }
        return validate_art_piece_sonic_extras([
            'default_volume' => $_POST['sonic_default_volume'] ?? null,
            'voices' => [
                'ambient' => isset($_POST['sonic_voice_ambient']),
                'movement' => isset($_POST['sonic_voice_movement']),
                'melodic' => isset($_POST['sonic_voice_melodic']),
                'hand_tracking' => isset($_POST['sonic_voice_hand_tracking']),
            ],
            'synth' => [
                'octave_min' => $_POST['sonic_octave_min'] ?? null,
                'octave_max' => $_POST['sonic_octave_max'] ?? null,
                'filter_cutoff' => $_POST['sonic_filter_cutoff'] ?? null,
                'filter_resonance' => $_POST['sonic_filter_resonance'] ?? null,
                'filter_type' => $_POST['sonic_filter_type'] ?? null,
                'effects' => [
                    'distortion' => [
                        'enabled' => isset($_POST['sonic_fx_distortion_enabled']),
                        'amount' => $_POST['sonic_fx_distortion_amount'] ?? null,
                    ],
                    'chorus' => [
                        'enabled' => isset($_POST['sonic_fx_chorus_enabled']),
                        'depth' => $_POST['sonic_fx_chorus_depth'] ?? null,
                        'rate' => $_POST['sonic_fx_chorus_rate'] ?? null,
                    ],
                    'tremolo' => [
                        'enabled' => isset($_POST['sonic_fx_tremolo_enabled']),
                        'depth' => $_POST['sonic_fx_tremolo_depth'] ?? null,
                        'rate' => $_POST['sonic_fx_tremolo_rate'] ?? null,
                    ],
                    'pitch_shift' => [
                        'enabled' => isset($_POST['sonic_fx_pitch_shift_enabled']),
                        'semitones' => $_POST['sonic_fx_pitch_shift_semitones'] ?? null,
                    ],
                    'bitcrusher' => [
                        'enabled' => isset($_POST['sonic_fx_bitcrusher_enabled']),
                        'bits' => $_POST['sonic_fx_bitcrusher_bits'] ?? null,
                    ],
                    'flanger' => [
                        'enabled' => isset($_POST['sonic_fx_flanger_enabled']),
                        'depth' => $_POST['sonic_fx_flanger_depth'] ?? null,
                        'rate' => $_POST['sonic_fx_flanger_rate'] ?? null,
                        'feedback' => $_POST['sonic_fx_flanger_feedback'] ?? null,
                    ],
                    'ring_mod' => [
                        'enabled' => isset($_POST['sonic_fx_ring_mod_enabled']),
                        'frequency' => $_POST['sonic_fx_ring_mod_frequency'] ?? null,
                    ],
                ],
            ],
        ]);
    }

    private static function normalizeSonicParamsInput(mixed $value, ?string $fallback = null): ?string
    {
        if (!art_piece_sonic_params_supported()) {
            return $fallback;
        }
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return validate_art_piece_sonic_params(is_string($value) ? $value : null);
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
        $engine = art_piece_generation_mode_to_engine($_POST['engine'] ?? 'p5');
        $html = trim((string) ($_POST['html_code'] ?? ''));
        $css = trim((string) ($_POST['css_code'] ?? ''));
        $js = trim((string) ($_POST['generated_code'] ?? ''));

        if (in_array($engine, art_piece_canvas_managed_engines(), true)) {
            $html = self::getStandardHtmlForEngine($engine);
        }

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
        $generationMode = 'p5';
        $selectedProfileId = null;
        $selectedPersonaId = null;
        $attemptLogs = null;
        $generationModes = self::aiGenerationModes();
        $availableGenerationModes = array_filter(
            $generationModes,
            static fn (array $mode): bool => feature_ai_piece_generation_mode_enabled((string) $mode['value'])
        );
        if ($availableGenerationModes !== []) {
            $generationMode = (string) array_key_first($availableGenerationModes);
            $engine = art_piece_generation_mode_to_engine($generationMode);
        }

        // Pre-select owner preferred art piece profile
        $owner = PlatformUser::owner();
        if ($owner && !empty($owner['preferred_art_piece_profile_id'])) {
            $selectedProfileId = (int) $owner['preferred_art_piece_profile_id'];
        }

        $pending = self::loadPendingGeneration();
        if (isset($_GET['restart']) && $_GET['restart'] === '1' && is_array($pending['original'] ?? null)) {
            $original = $pending['original'];
            $prompt = trim((string) ($original['prompt'] ?? ''));
            $generationMode = trim((string) ($original['generation_mode'] ?? $generationMode));
            $engine = art_piece_generation_mode_to_engine($generationMode);
            $selectedProfileId = (int) ($original['profile_id'] ?? $selectedProfileId);
            $selectedPersonaId = (int) ($original['persona_id'] ?? $selectedPersonaId);
        }

        require dirname(__DIR__, 2) . '/views/admin/pieces/generate-form.php';
    }

    private static function aiGenerationModes(): array
    {
        return [
            'p5' => ['value' => 'p5', 'label' => 'P5.js (Interactive canvas drawing)', 'group' => 'Stable engines'],
            'c2' => ['value' => 'c2', 'label' => 'C2.js (Animated drawing & geometry)', 'group' => 'Stable engines'],
            'c2_interactive' => ['value' => 'c2_interactive', 'label' => 'C2.js Interactive (Click, touch & drag)', 'group' => 'Stable engines'],
            'three' => ['value' => 'three', 'label' => 'Three.js (3D WebGL scenes & lights)', 'group' => 'Stable engines'],
            'svg' => ['value' => 'svg', 'label' => 'SVG (Vector paths & CSS animation)', 'group' => 'Stable engines'],
            'aframe' => ['value' => 'aframe', 'label' => 'A-Frame Experimental (Self-contained 3D scene)', 'group' => 'Experimental engines'],
        ];
    }

    private static function requestedGenerationModeFromPost(string $engineFallback = 'p5'): string
    {
        return art_piece_normalize_generation_mode(
            (string) ($_POST['generation_mode'] ?? ($_POST['engine'] ?? $engineFallback)),
            $engineFallback
        );
    }

    private static function storedGenerationMode(?array $version, string $engineFallback = 'p5'): string
    {
        return art_piece_version_generation_mode(is_array($version) ? $version : [], ['engine' => $engineFallback]);
    }

    private static function resolveVersionGenerationModeForUpdate(string $engine, ?array $currentVersion): string
    {
        $requestedMode = trim((string) ($_POST['generation_mode'] ?? ''));
        if ($requestedMode !== '') {
            return art_piece_normalize_generation_mode($requestedMode, $engine);
        }

        if (is_array($currentVersion) && strtolower((string) ($currentVersion['engine'] ?? '')) === strtolower($engine)) {
            return self::storedGenerationMode($currentVersion, $engine);
        }

        return art_piece_normalize_generation_mode($engine, $engine);
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

    // One AI attempt per request, mirroring refineAi()'s stateless per-attempt
    // design — the client carries attempt_number/previous_raw_response/
    // last_error/sequence_token and decides whether to send the next attempt.
    // The previous design ran all ART_PIECE_MAX_ATTEMPTS attempts inside one
    // blocking request (up to ~600s); since the documented local/production
    // server handles one request at a time, that left no way for any other
    // request (Cancel, Back, anything) to be serviced until it finished. A
    // single attempt here blocks the one worker for at most one AI call.
    public static function generate(): void
    {
        set_time_limit(150); // one ~120s AI call + buffer
        admin_check();
        header('Content-Type: application/json; charset=utf-8');
        $startedAt = microtime(true);
        $prompt = trim($_POST['prompt'] ?? '');
        $generationMode = trim($_POST['generation_mode'] ?? ($_POST['engine'] ?? 'p5'));
        $engine = art_piece_generation_mode_to_engine($generationMode);
        if (!in_array($engine, art_piece_supported_engines(), true) || !in_array($generationMode, array_merge(art_piece_supported_engines(), ['c2_interactive']), true)) {
            $generationMode = 'p5';
            $engine = 'p5';
        }
        if (!feature_ai_piece_generation_mode_enabled($generationMode)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'AI generation is disabled for this piece type. Enable it under Admin → Features → AI.',
            ]);
            exit;
        }
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $personaId = (int) ($_POST['persona_id'] ?? 0);
        $attemptNumber = max(1, (int) ($_POST['attempt_number'] ?? 1));
        $previousRawResponse = $_POST['previous_raw_response'] !== null && $_POST['previous_raw_response'] !== ''
            ? (string) ($_POST['previous_raw_response'] ?? '')
            : null;
        $lastError = trim((string) ($_POST['last_error'] ?? ''));
        $sequenceToken = trim((string) ($_POST['sequence_token'] ?? ''));

        // Optional Tone.js sonification request (piece-sound feature). Carried on
        // every attempt so retries keep asking for the sonic block.
        $soundEnabled = art_piece_sonic_params_supported() && !empty($_POST['sound_enabled']);
        $soundFeel = trim((string) ($_POST['sound_feel'] ?? ''));

        $actorId = (int) (admin_identity()['id'] ?? 0);

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
            // Defensive cap — don't trust the client alone to stop at 5; a
            // tampered or buggy client retrying past the limit would
            // otherwise keep spending tokens indefinitely.
            if ($attemptNumber > ART_PIECE_MAX_ATTEMPTS) {
                throw new InvalidArgumentException("Maximum of " . ART_PIECE_MAX_ATTEMPTS . " attempts already reached.");
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
            $allowedMediaRefs = art_piece_extract_prompt_media_refs($prompt);

            $aiClient = new \App\Lib\Ai\AiProviderClient($profile['vendor'], $profile['model'], $profile['endpoint_kind'], $apiKey);

            $systemPrompt = art_piece_generation_system_prompt($generationMode);
            // Append optional capability guidance (3D model loading + sonification)
            // without touching the engine base prompts.
            if (feature_enabled('media_models')) {
                $modelCapability = art_piece_model_capability_prompt($engine);
                if ($modelCapability !== '') {
                    $systemPrompt .= "\n\n" . $modelCapability;
                }
            }
            if ($soundEnabled) {
                $sonicCapability = art_piece_sonic_capability_prompt($engine, $soundFeel);
                if ($sonicCapability !== '') {
                    $systemPrompt .= "\n\n" . $sonicCapability;
                }
            }
            $userPromptForApi = $attemptNumber === 1
                ? $basePrompt . "\n\n" . art_piece_media_policy_prompt($allowedMediaRefs)
                : art_piece_repair_prompt($engine, $basePrompt, $previousRawResponse, $lastError !== '' ? $lastError : 'Unknown failure', $allowedMediaRefs);

            $res = $aiClient->generate($systemPrompt, $userPromptForApi);
            if (!$res['ok']) {
                throw new RuntimeException($res['error'] ?? 'API error');
            }

            $rawText = $res['text'];
            $previousRawResponse = $rawText;

            $blocks = art_piece_extract_code_blocks($rawText);
            $html = $blocks['htmlCode'] ?? '';
            $css = $blocks['cssCode'] ?? '';
            $js = $blocks['generatedCode'] ?? '';
            // Soft-validated: a bad/missing sonic block yields null (no sound) and
            // never blocks the code from validating below.
            $sonic = $soundEnabled ? validate_art_piece_sonic_params($blocks['sonicParams'] ?? null) : null;

            art_piece_preflight_document($engine, $html, $css, $js, $generationMode);
            validate_art_piece_prompted_media_refs($allowedMediaRefs, $html, $css, $js, [], true);

            // Success!
            audit_log_event('ai_request', 'ai_generate_piece', 'success', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 200,
                'metadata' => [
                    'profile_id' => $profileId,
                    'vendor' => $profile['vendor'] ?? '',
                    'model' => $profile['model'] ?? '',
                    'endpoint_kind' => $profile['endpoint_kind'] ?? '',
                    'engine' => $engine,
                    'generation_mode' => $generationMode,
                    'attempt_number' => $attemptNumber,
                    'sequence_token' => $sequenceToken,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
            ]);
            self::storePendingGeneration([
                'engine' => $engine,
                'generation_mode' => $generationMode,
                'html_code' => $html,
                'css_code' => $css,
                'generated_code' => $js,
                'sonic_params' => $sonic,
                'vendor' => $profile['vendor'] ?? '',
                'model' => $profile['model'] ?? '',
                'endpoint_kind' => $profile['endpoint_kind'] ?? '',
                'attempt_count' => $attemptNumber,
                'prompt' => $prompt,
                'profile_id' => $profileId,
                'persona_id' => $personaId,
                // Sound lineage: the regenerate endpoint derives its
                // purpose_domain PURELY from these (per the per-domain
                // rule, lineage is the only determinant). sound_feel is
                // the prose the admin wrote at generate time — needed to
                // re-emit the sonic capability instructions in the system
                // prompt on a regenerate with audio in scope. sound_enabled
                // marks that this generation was an audio+visual
                // generation, even if the model returned a null sonic
                // block (in which case sonic_params would be null but the
                // lineage intent is still audible).
                'sound_feel' => $soundFeel,
                'sound_enabled_lineage' => $soundEnabled,
            ]);
            echo json_encode(['success' => true]);

        } catch (Throwable $e) {
            audit_log_event('ai_request', 'ai_generate_piece', 'error', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 500,
                'metadata' => [
                    'profile_id' => $profileId,
                    'engine' => $engine,
                    'attempt_number' => $attemptNumber,
                    'sequence_token' => $sequenceToken,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error' => $e->getMessage(),
                ],
            ]);
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'raw_response' => $previousRawResponse,
                'attempt_number' => $attemptNumber,
                'can_retry' => $attemptNumber < ART_PIECE_MAX_ATTEMPTS,
            ]);
        }
        exit;
    }

    public static function generatePreview(): void
    {
        admin_check();

        $pending = self::loadPendingGeneration();

        if (!is_array($pending)) {
            header('Location: /admin/pieces/generate');
            exit;
        }

        $current = is_array($pending['current'] ?? null) ? $pending['current'] : $pending;
        $original = is_array($pending['original'] ?? null) ? $pending['original'] : $pending;

        $engine = (string) ($current['engine'] ?? $pending['engine'] ?? 'p5');
        $generationMode = (string) ($original['generation_mode'] ?? $pending['generation_mode'] ?? $engine);
        $htmlCode = (string) ($current['html_code'] ?? $pending['html_code'] ?? '');
        if (in_array($engine, art_piece_canvas_managed_engines(), true)) {
            $htmlCode = self::getStandardHtmlForEngine($engine);
        }
        $cssCode = (string) ($current['css_code'] ?? $pending['css_code'] ?? '');
        $generatedCode = (string) ($current['generated_code'] ?? $pending['generated_code'] ?? '');
        $sonicParams = $current['sonic_params'] ?? ($pending['sonic_params'] ?? null);
        $profile = [
            'vendor' => (string) ($original['vendor'] ?? $pending['vendor'] ?? ''),
            'model' => (string) ($original['model'] ?? $pending['model'] ?? ''),
            'endpoint_kind' => (string) ($original['endpoint_kind'] ?? $pending['endpoint_kind'] ?? ''),
        ];
        $attemptCount = (int) ($original['attempt_count'] ?? $pending['attempt_count'] ?? 1);
        $prompt = (string) ($original['prompt'] ?? $pending['prompt'] ?? '');
        $profileId = (int) ($original['profile_id'] ?? $pending['profile_id'] ?? 0);
        $personaId = (int) ($original['persona_id'] ?? $pending['persona_id'] ?? 0);
        // Lineage: audio domain constants. Rendered as hidden inputs in
        // generate-preview.php so the regenerate fetch carries them onward;
        // the regenerate endpoint derives purpose_domain PURELY from these.
        $soundFeelLineage = (string) ($original['sound_feel'] ?? '');
        $soundEnabledLineage = (bool) ($original['sound_enabled_lineage'] ?? false);

        require dirname(__DIR__, 2) . '/views/admin/pieces/generate-preview.php';
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
            if (!in_array($engine, art_piece_supported_engines(), true)) {
                throw new InvalidArgumentException('Unsupported art piece engine.');
            }
            $generationMode = trim((string) ($_POST['generation_mode'] ?? $engine));
            if (!feature_ai_piece_generation_mode_enabled($generationMode)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'AI generation is disabled for this piece type. Enable it under Admin → Features → AI.']);
                exit;
            }
            $status = $_POST['status'] ?? 'draft';

            $htmlCode = trim($_POST['html_code'] ?? '') ?: null;
            if (in_array($engine, art_piece_canvas_managed_engines(), true)) {
                $htmlCode = self::getStandardHtmlForEngine($engine);
            }
            $cssCode = trim($_POST['css_code'] ?? '') ?: null;
            $generatedCode = trim($_POST['generated_code'] ?? '') ?: null;
            // Re-validated on save so a tampered hidden field can't store junk.
            $sonicParams = validate_art_piece_sonic_params($_POST['sonic_params'] ?? null);
            art_piece_preflight_document($engine, $htmlCode, $cssCode, $generatedCode, $generationMode);

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
            if ($engine === 'aframe' && $thumbnailUrl === null) {
                throw new RuntimeException('A-Frame pieces must pass sandbox render/capture before they can be saved. Wait for thumbnail capture to complete, then save again.');
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
                'generation_mode' => art_piece_normalize_generation_mode($generationMode, $engine),
                'validation_status' => 'validated',
                'generation_attempt_count' => (int) ($_POST['generation_attempt_count'] ?? 1),
                'notes' => 'Generated via AI',
                'ai_profile_id' => (int) ($_POST['profile_id'] ?? 0) ?: null,
                'ai_persona_id' => (int) ($_POST['persona_id'] ?? 0) ?: null,
                'sonic_params' => $sonicParams,
            ]);

            PlatformArtPiece::updateCurrentVersion($pieceId, $versionId);
            self::clearPendingGeneration();

            echo json_encode(['success' => true, 'redirect' => '/admin/pieces']);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public static function generateRegenerate(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        $input = [];
        $previousRawResponse = null;
        $attemptNumber = 1;

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $prompt = trim((string) ($input['prompt'] ?? ''));
            $engine = trim((string) ($input['engine'] ?? 'p5'));
            if (!feature_ai_piece_engine_enabled($engine)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'AI regeneration is disabled for this piece engine. Enable it under Admin → Features → AI.']);
                exit;
            }
            $profileId = (int) ($input['profile_id'] ?? 0);
            $personaId = (int) ($input['persona_id'] ?? 0);
            $html = (string) ($input['html_code'] ?? '');
            $css = (string) ($input['css_code'] ?? '');
            $js = (string) ($input['generated_code'] ?? '');
            $attemptNumber = max(1, (int) ($input['attempt_number'] ?? 1));
            $clientPreviousRawResponse = $input['previous_raw_response'] !== null && $input['previous_raw_response'] !== ''
                ? (string) ($input['previous_raw_response'] ?? '')
                : null;
            $clientLastError = trim((string) ($input['last_error'] ?? ''));
            $allowedMediaRefs = art_piece_extract_prompt_media_refs($prompt);
            $existingMediaRefs = art_piece_collect_cms_media_refs($html, $css, $js);

            // Audio lineage (per the per-domain rule, lineage is the ONLY
            // determinant of what a regenerate may touch). For generation
            // lineage the visual domain is ALWAYS in scope (pieces MUST
            // have a visual prompt — generation never produces sound-only
            // pieces), so the lineage-induced purpose_domain is either
            // 'visual' (no audio lineage) or 'audio_visual' (audio lineage
            // present). 'audio' (sound-only) lineage here is a no-op —
            // generation can never carry it, and a future refine-lineage
            // regenerate flow can use the same arch cleanly when wired in.
            $soundFeelLineage = trim((string) ($input['sound_feel_lineage'] ?? ''));
            $soundEnabledLineage = art_piece_sonic_params_supported() && !empty($input['sound_enabled_lineage']);
            $currentSonicParams = (string) ($input['sonic_params'] ?? '');
            $purposeDomain = $soundEnabledLineage ? 'audio_visual' : 'visual';
            $soundOnly = ($purposeDomain === 'audio');

            if ($prompt === '') {
                throw new InvalidArgumentException('Prompt is required.');
            }
            if ($profileId <= 0) {
                throw new InvalidArgumentException('Please select an active AI profile.');
            }
            if ($attemptNumber > ART_PIECE_MAX_ATTEMPTS) {
                throw new InvalidArgumentException("Maximum of " . ART_PIECE_MAX_ATTEMPTS . " attempts already reached.");
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
            $aiClient = new \App\Lib\Ai\AiProviderClient($profile['vendor'], $profile['model'], $profile['endpoint_kind'], $apiKey, timeoutOverride: 180.0);
            $persona = self::findPersonaById($personaId);

            $generationMode = trim((string) ($input['generation_mode'] ?? $input['engine'] ?? $engine));
            $systemPrompt = art_piece_refine_system_prompt($generationMode);
            if (feature_enabled('media_models')) {
                $modelCapability = art_piece_model_capability_prompt($engine);
                if ($modelCapability !== '') {
                    $systemPrompt .= "\n\n" . $modelCapability;
                }
            }
            // Sonic capability instructions when the lineage placed audio
            // in scope — parity with refineAi()'s handling, so a regenerate
            // of an audio+visual generation actually improves the sound
            // design rather than silently dropping it. Previously regenerate
            // discarded the audio lineage and produced visual-only output.
            if ($soundEnabledLineage) {
                $sonicCapability = art_piece_sonic_capability_prompt($engine, $soundFeelLineage);
                if ($sonicCapability !== '') {
                    $systemPrompt .= "\n\n" . $sonicCapability;
                }
            }
            if ($persona) {
                $systemPrompt .= "\n\nPersona guidance:\n" . trim((string) $persona['system_prompt']) . "\n\nUse the persona to influence style and creative direction, but still obey all engine, safety, and output-format requirements.";
            }

            // Lineage-aware regenerate instruction: visual-only lineage
            // rebuilds the visuals; audio+visual lineage improves both the
            // visuals AND the sound design together (the only mode in
            // which regenerate may touch sound).
            $regenerateInstruction = $soundEnabledLineage
                ? 'Rebuild this piece so it better fulfills the original creative prompt. Treat the current code as the starting point, but revise any relevant HTML, CSS, or JS needed to improve visual fidelity, composition, and behavior, AND revise the sound design / instrumentation to better realize the sonic feel, while preserving the prompt-requested CMS media.'
                : 'Rebuild this piece so it better fulfills the original creative prompt. Treat the current code as the starting point, but revise any relevant HTML, CSS, or JS needed to improve fidelity, composition, and behavior while preserving the prompt-requested CMS media. Do not produce any sound design; the lineage did not request sound.';
            if ($attemptNumber === 1) {
                $userPromptForApi = art_piece_refine_user_prompt($engine, $regenerateInstruction, $html, $css, $js, $prompt, $allowedMediaRefs, $purposeDomain);
            } else {
                $userPromptForApi = art_piece_refine_repair_prompt($engine, $regenerateInstruction, $clientPreviousRawResponse, $clientLastError !== '' ? $clientLastError : 'Unknown failure', $html, $css, $js, $allowedMediaRefs, $purposeDomain);
            }

            $res = $aiClient->generate($systemPrompt, $userPromptForApi, suppressPlanningPreamble: false, maxTokensOverride: 24576);
            if (!$res['ok']) {
                throw new RuntimeException($res['error'] ?? 'API error');
            }
            if (\App\Lib\Ai\AiProviderClient::finishReasonMeansTruncated($res['finishReason'] ?? null)) {
                $previousRawResponse = $res['text'];
                throw new RuntimeException("The AI's response was cut off before finishing (token limit reached) — try again or reduce the scope of the current preview edits.");
            }

            $rawText = $res['text'];
            $previousRawResponse = $rawText;
            $patches = art_piece_extract_refine_patches($rawText);
            // Sonic-block extraction: when the lineage placed audio in
            // scope, a regenerated sonic block is valid output (when the
            // model revises only the sound, with visuals unchanged). When
            // the lineage was visual-only, any sonic output is discarded
            // — the OUT-OF-SCOPE domain must not change.
            $blocks = art_piece_extract_code_blocks($rawText);
            if ($soundEnabledLineage) {
                $regeneratedSonicParams = validate_art_piece_sonic_params($blocks['sonicParams'] ?? null)
                    ?? art_piece_sonic_params_from_feel($soundFeelLineage);
                $currentSonicForCompare = $currentSonicParams !== '' ? $currentSonicParams : null;
                $sonicChanged = !art_piece_sonic_params_equal($regeneratedSonicParams, $currentSonicForCompare);
            } else {
                $regeneratedSonicParams = null;
                $sonicChanged = false;
            }
            if (!$patches['html'] && !$patches['css'] && !$patches['js'] && !$sonicChanged) {
                if (preg_match('/```(?:css|javascript|js)\b/i', $rawText) && !preg_match('/\bPATCH\s/i', $rawText)) {
                    throw new RuntimeException('AI ignored the required PATCH format and returned full rewritten files in fenced code blocks instead of a diff.');
                }
                throw new RuntimeException('AI response contained no valid PATCH blocks in the required format — at least one PATCH' . ($soundEnabledLineage ? ' or a valid ```sonic``` block' : '') . ' is required to regenerate the preview.');
            }

            $extractedHtml = art_piece_apply_refine_patches($html, $patches['html']);
            if (in_array($engine, art_piece_canvas_managed_engines(), true)) {
                $extractedHtml = self::getStandardHtmlForEngine($engine);
            }
            $extractedCss = art_piece_apply_refine_patches($css, $patches['css']);
            $extractedJs = art_piece_apply_refine_patches($js, $patches['js']);

            art_piece_preflight_document($engine, $extractedHtml, $extractedCss, $extractedJs, $generationMode);
            validate_art_piece_prompted_media_refs($allowedMediaRefs, $extractedHtml, $extractedCss, $extractedJs, $existingMediaRefs, false);

            if (in_array($engine, art_piece_canvas_managed_engines(), true) && !empty($patches['html'])) {
                throw new RuntimeException('HTML changes are not allowed for p5, c2, and three engine types. Focus regeneration edits on CSS or JS instead.');
            }

            self::updatePendingGenerationCurrent([
                'engine' => $engine,
                'html_code' => $extractedHtml,
                'css_code' => $extractedCss,
                'generated_code' => $extractedJs,
                // Persist the regenerated (or carried-forward-unchanged)
                // sonic_params on the pending preview row, parity with
                // refineAi()'s draft-version persistence. Previously
                // regenerate silently dropped the audio lineage here.
                'sonic_params' => $regeneratedSonicParams,
            ]);

            echo json_encode([
                'success' => true,
                'html_code' => $extractedHtml,
                'css_code' => $extractedCss,
                'generated_code' => $extractedJs,
                'sonic_params' => (string) ($regeneratedSonicParams ?? ''),
                'plan' => art_piece_extract_refine_plan($rawText),
                'attempt_number' => $attemptNumber,
            ]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'raw_response' => $previousRawResponse,
                'attempt_number' => $attemptNumber,
                'can_retry' => $attemptNumber < ART_PIECE_MAX_ATTEMPTS,
            ]);
        }
        exit;
    }

    public static function aiProcessText(): void
    {
        admin_check();
        header('Content-Type: application/json; charset=utf-8');

        // Shared endpoint across every editor: the caller declares which
        // area it is so the matching per-area editor-AI flag applies.
        $context = (string) ($_POST['context'] ?? '');
        $contextFlag = feature_ai_text_flag_for_context($context);
        if ($contextFlag === null || !feature_enabled($contextFlag)) {
            http_response_code(403);
            echo json_encode(['error' => 'AI text assistance is disabled for this editor. Enable it under Admin → Features.']);
            exit;
        }

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

    // Performs exactly ONE AI Refine attempt per call — the caller (the
    // edit form's JS) drives whether to spend another attempt, showing the
    // failure and asking before any further tokens are spent, instead of
    // this method silently burning up to ART_PIECE_MAX_ATTEMPTS automatic
    // retries in one request. This also incidentally fixes the risk of a
    // long chained-attempts request outliving this app's hosting
    // infrastructure's own connection timeout (previously mitigated with a
    // 240s in-loop budget check, now moot since there is no more loop to
    // bound — one attempt at this AI vendor/model's observed pace, ~20-120s
    // per audit_log_events, comfortably fits inside both this script's own
    // set_time_limit and that external timeout).
    //
    // Every attempt that produces extracted code — whether it then passes
    // or fails validation — is persisted immediately as its own
    // is_draft_attempt version row, grouped by the client-supplied
    // sequence_token, so a partially-good failed attempt's tokens are never
    // just thrown away (the user can inspect, hand-edit, or fork it later
    // from the Versions list). Attempts that fail before producing any code
    // at all (an API error, a truncated response with nothing extractable)
    // have nothing concrete to persist and create no row.
    public static function refineAi(): void
    {
        // 220s, not the AI call's own 180s timeout (see the AiProviderClient
        // instantiation below) — real audit data showed repair attempts
        // that restructure code (e.g. to InstancedMesh) taking up to 120s+
        // and exceeding the AI client's old 120s ceiling; 180s gives that
        // harder task real room, and this script's own limit needs enough
        // buffer above 180s for validation/draft-persist/audit-log after
        // the call returns.
        set_time_limit(220);
        admin_check();
        header('Content-Type: application/json; charset=utf-8');
        $startedAt = microtime(true);
        $actorId = (int) (admin_identity()['id'] ?? 0);
        $limit = rate_limit_consume('ai_refine_piece', rate_limit_subject_for_scope('ai_refine_piece', $actorId > 0 ? $actorId : null));
        if (!$limit['allowed']) {
            self::emitRateLimitedJson('ai_refine_piece', (int) $limit['retry_after'], $actorId);
        }

        $previousRawResponse = null;
        $attemptNumber = 1;
        $draftVersionId = null;

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $prompt = trim((string) ($input['prompt'] ?? ''));
            $engine = trim((string) ($input['engine'] ?? 'p5'));
            if (!feature_ai_piece_engine_enabled($engine)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'AI Refine is disabled for this piece engine. Enable it under Admin → Features → AI.']);
                exit;
            }
            $profileId = (int) ($input['profile_id'] ?? 0);
            $personaId = (int) ($input['persona_id'] ?? 0);
            $html = (string) ($input['html_code'] ?? '');
            $css = (string) ($input['css_code'] ?? '');
            $js = (string) ($input['generated_code'] ?? '');
            $originalPrompt = trim((string) ($input['original_prompt'] ?? ''));
            $soundEnabled = art_piece_sonic_params_supported() && !empty($input['sound_enabled']);
            $soundFeel = trim((string) ($input['sound_feel'] ?? ''));
            $allowedMediaRefs = art_piece_extract_prompt_media_refs($prompt);
            $existingMediaRefs = art_piece_collect_cms_media_refs($html, $css, $js);
            $pieceId = (int) ($input['piece_id'] ?? 0);
            $piece = $pieceId > 0 ? PlatformArtPiece::find($pieceId) : false;
            $persistedGenerationMode = $piece && !empty($piece['current_version'])
                ? self::storedGenerationMode((array) $piece['current_version'], $engine)
                : art_piece_normalize_generation_mode($engine, $engine);
            $attemptNumber = max(1, (int) ($input['attempt_number'] ?? 1));
            $clientPreviousRawResponse = $input['previous_raw_response'] !== null && $input['previous_raw_response'] !== ''
                ? (string) ($input['previous_raw_response'] ?? '')
                : null;
            $clientLastError = trim((string) ($input['last_error'] ?? ''));
            $sequenceToken = trim((string) ($input['sequence_token'] ?? ''));

            // Three-state purpose domain, derived from which instruction
            // field(s) the admin filled in this request:
            //  - prompt set, sound unchecked        => 'visual'
            //  - prompt empty + sound feel provided => 'audio' (sound only)
            //  - prompt set + sound feel provided   => 'audio_visual'
            // Only one direction filled => only that direction is in scope;
            // the other is OUT OF SCOPE and must not change (enforced inbound
            // via art_piece_refine_user_prompt()'s $purposeDomain, outbound
            // via the patch force-clear backstop below for 'audio'). The
            // original creative prompt is always sent as CONTEXT, never as
            // the directive — see the ### PURPOSE OF THIS REFINEMENT header.
            $inputDomain = trim((string) ($input['purpose_domain'] ?? ''));
            if ($inputDomain === 'audio' || $inputDomain === 'visual' || $inputDomain === 'audio_visual') {
                $purposeDomain = $inputDomain;
            } else {
                $hasVisualPrompt = $prompt !== '';
                $hasAudioPrompt = $soundEnabled && $soundFeel !== '';
                if ($hasVisualPrompt && $hasAudioPrompt) {
                    $purposeDomain = 'audio_visual';
                } elseif ($hasAudioPrompt) {
                    $purposeDomain = 'audio';
                } else {
                    $purposeDomain = 'visual';
                }
            }
            $soundOnly = ($purposeDomain === 'audio');
            if ($soundOnly) {
                $baseInstruction = 'Keep the existing visuals exactly as they are — only update the sound design/instrumentation per the sonic instructions.';
                if ($prompt !== '') {
                    $prompt = $baseInstruction . "\n\nSpecific sound instruction/feedback: " . $prompt;
                } else {
                    $prompt = $baseInstruction;
                }
            }
            if ($prompt === '') {
                throw new InvalidArgumentException('Prompt is required.');
            }
            if ($profileId <= 0) {
                throw new InvalidArgumentException('Please select an active AI profile.');
            }
            // Defensive cap — don't trust the client alone to stop at 5;
            // a tampered or buggy client retrying past the limit would
            // otherwise keep spending tokens indefinitely.
            if ($attemptNumber > ART_PIECE_MAX_ATTEMPTS) {
                throw new InvalidArgumentException("Maximum of " . ART_PIECE_MAX_ATTEMPTS . " attempts already reached.");
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
            // 180s, not the client's 120s default — real audit data showed
            // repair attempts that restructure code (e.g. to InstancedMesh)
            // consistently needing more than 120s. Passed explicitly only
            // here; every other AiProviderClient caller (generation,
            // ai_process_text, ai_describe_image) keeps the unchanged 120s
            // default.
            $aiClient = new \App\Lib\Ai\AiProviderClient($profile['vendor'], $profile['model'], $profile['endpoint_kind'], $apiKey, timeoutOverride: 180.0);
            $persona = self::findPersonaById($personaId);

            $systemPrompt = art_piece_refine_system_prompt($persistedGenerationMode);
            if (feature_enabled('media_models')) {
                $modelCapability = art_piece_model_capability_prompt($engine);
                if ($modelCapability !== '') {
                    $systemPrompt .= "\n\n" . $modelCapability;
                }
            }
            if ($soundEnabled) {
                $sonicCapability = art_piece_sonic_capability_prompt($engine, $soundFeel);
                if ($sonicCapability !== '') {
                    $systemPrompt .= "\n\n" . $sonicCapability;
                }
            }
            if ($persona) {
                $systemPrompt .= "\n\nPersona guidance:\n" . trim((string) $persona['system_prompt']) . "\n\nUse the persona to influence style and creative direction, but still obey all engine, safety, and output-format requirements.";
            }

            if ($attemptNumber === 1) {
                $userPromptForApi = art_piece_refine_user_prompt($engine, $prompt, $html, $css, $js, $originalPrompt ?: null, $allowedMediaRefs, $purposeDomain);
            } else {
                $userPromptForApi = art_piece_refine_repair_prompt($engine, $prompt, $clientPreviousRawResponse, $clientLastError !== '' ? $clientLastError : 'Unknown failure', $html, $css, $js, $allowedMediaRefs, $purposeDomain);
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
                throw new RuntimeException($res['error'] ?? 'API error');
            }
            if (\App\Lib\Ai\AiProviderClient::finishReasonMeansTruncated($res['finishReason'] ?? null)) {
                $previousRawResponse = $res['text'];
                throw new RuntimeException("The AI's response was cut off before finishing (token limit reached) — try a smaller, more specific instruction.");
            }

            $rawText = $res['text'];
            $previousRawResponse = $rawText;

            // Apply the AI's patches against the ORIGINAL code (not a
            // regenerated file) — anything not named in a patch is
            // carried forward unchanged, which is the actual guarantee
            // that an unscoped refinement can't quietly rewrite the rest
            // of the piece.
            $patches = art_piece_extract_refine_patches($rawText);
            if ($soundOnly) {
                // Backstop: visual code was never even shown to the model
                // (see art_piece_refine_user_prompt()'s audio-purpose
                // branch, where purposeDomain==='audio' omits the visual
                // code blocks), but if it hallucinates a patch anyway,
                // discard it here — a sound-only request must never be
                // able to change html/css/js, regardless of what the
                // model returns.
                $patches['html'] = [];
                $patches['css'] = [];
                $patches['js'] = [];
            }
            $blocks = art_piece_extract_code_blocks($rawText);
            $sonicParams = ($purposeDomain === 'visual')
                ? ($piece['current_version']['sonic_params'] ?? null)
                : (validate_art_piece_sonic_params($blocks['sonicParams'] ?? null) ?? art_piece_sonic_params_from_feel($soundFeel));
            $sonicChanged = ($purposeDomain !== 'visual')
                && !art_piece_sonic_params_equal($sonicParams, $piece['current_version']['sonic_params'] ?? null);

            // A response with zero patches across every file is not
            // a legitimate "nothing needed changing" outcome here —
            // the admin always asked for a real, visible change. Left
            // unchecked this silently "succeeds" by returning the
            // original code untouched, which is indistinguishable
            // from the refinement never having happened at all.
            if (!$patches['html'] && !$patches['css'] && !$patches['js'] && !$sonicChanged) {
                // Confirmed in production (audit log events #141, #147): the
                // model sometimes ignores the PLAN/PATCH protocol entirely
                // and dumps full-file fenced code blocks instead of a diff.
                // That's a different mistake from "responded with nothing
                // usable" — naming it specifically here lets the repair
                // prompt below call out the exact violation instead of
                // repeating the same generic instruction that already
                // failed to prevent it once.
                if (preg_match('/```(?:css|javascript|js)\b/i', $rawText) && !preg_match('/\bPATCH\s/i', $rawText)) {
                    throw new RuntimeException('AI ignored the required PATCH format and returned full rewritten files in fenced code blocks instead of a diff.');
                }
                throw new RuntimeException('AI response contained no valid PATCH blocks in the required format — at least one PATCH is required to make the requested change.');
            }

            $extractedHtml = art_piece_apply_refine_patches($html, $patches['html']);
            if (in_array($engine, art_piece_canvas_managed_engines(), true)) {
                $extractedHtml = self::getStandardHtmlForEngine($engine);
            }
            $extractedCss = art_piece_apply_refine_patches($css, $patches['css']);
            $extractedJs = art_piece_apply_refine_patches($js, $patches['js']);

            // From this point on we have real extracted code, even if a
            // validation check below ultimately rejects it — persist it as
            // a draft attempt now so a rejection further down still leaves
            // something salvageable, rather than only persisting on the
            // success path.
            $draftVersionId = self::persistDraftAttempt(
                $pieceId, $engine, $persistedGenerationMode, $prompt, $extractedHtml, $extractedCss, $extractedJs,
                $profileId, $personaId, $sequenceToken, $attemptNumber, 'pending', $sonicParams
            );

            if (!$soundOnly) {
                art_piece_preflight_document($engine, $extractedHtml, $extractedCss, $extractedJs, $persistedGenerationMode);
                validate_art_piece_prompted_media_refs($allowedMediaRefs, $extractedHtml, $extractedCss, $extractedJs, $existingMediaRefs, false);
            }

            // Canvas Preservation Constraints
            if (in_array($engine, art_piece_canvas_managed_engines(), true)) {
                if (!empty($patches['html'])) {
                    throw new RuntimeException('HTML changes are not allowed for p5, c2, and three engine types. The canvas is automatically managed. Focus your edits on CSS or JS instead.');
                }
            }

            // Success! Mark the draft row's validation status accordingly —
            // it stays a draft (is_draft_attempt = 1) until the user
            // actually clicks Accept and refineSave() promotes it.
            if ($draftVersionId !== null) {
                self::updateDraftValidationStatus($draftVersionId, 'validated');
            }
            $plan = art_piece_extract_refine_plan($rawText);

            echo json_encode([
                'success' => true,
                'html_code' => $extractedHtml,
                'css_code' => $extractedCss,
                'generated_code' => $extractedJs,
                // The AI's stated plan before patching, surfaced to the
                // admin alongside the diff for the same before-acting
                // visibility a plan gives.
                'plan' => $plan,
                // Echoed back so the client can carry these through to the
                // version that gets created when the accepted code is saved.
                'profile_id' => $profileId,
                'persona_id' => $personaId > 0 ? $personaId : null,
                'sonic_params' => $sonicParams,
                'draft_version_id' => $draftVersionId,
                'sequence_token' => $sequenceToken,
                'attempt_number' => $attemptNumber,
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
                    'attempt_number' => $attemptNumber,
                    'sequence_token' => $sequenceToken,
                    'draft_version_id' => $draftVersionId,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    // Silent diagnostic only — never gates or warns the
                    // user (the mesh-count rejection was tried at two
                    // thresholds and removed entirely; see DECISIONS.md).
                    // Kept here so a future investigation has real numbers
                    // without needing to reproduce this live again.
                    'mesh_object_count' => $engine === 'three' ? art_piece_count_three_object_calls($extractedJs) : null,
                    // Truncated raw model response, so a future "succeeded
                    // but did nothing useful" report can be diagnosed from
                    // the log directly instead of by inference.
                    'raw_response' => mb_substr((string) $previousRawResponse, 0, 4000),
                ],
            ]);
            exit;

        } catch (Throwable $e) {
            if ($draftVersionId !== null) {
                self::updateDraftValidationStatus($draftVersionId, 'failed_attempt');
            }
            audit_log_event('ai_request', 'ai_refine_piece', 'error', [
                'actor_admin_identity_id' => $actorId > 0 ? $actorId : null,
                'http_status' => 500,
                'metadata' => [
                    'attempt_number' => $attemptNumber,
                    'draft_version_id' => $draftVersionId,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error' => $e->getMessage(),
                    // Silent diagnostic only, same as the success path —
                    // only available when extraction got far enough to
                    // produce JS before the failure.
                    'mesh_object_count' => (isset($engine, $extractedJs) && $engine === 'three') ? art_piece_count_three_object_calls($extractedJs) : null,
                    'raw_response' => mb_substr((string) ($previousRawResponse ?? ''), 0, 4000),
                ],
            ]);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'raw_response' => $previousRawResponse,
                'draft_version_id' => $draftVersionId,
                'attempt_number' => $attemptNumber,
                'can_retry' => $attemptNumber < ART_PIECE_MAX_ATTEMPTS,
            ]);
            exit;
        }
    }

    // Persists one AI Refine attempt's extracted code as a non-current,
    // non-revertible draft version — called as soon as code has actually
    // been extracted, before validation decides whether it's usable, so a
    // later rejection still leaves something salvageable. Returns null
    // (and persists nothing) when there's no piece to attach to yet, e.g.
    // a not-yet-saved new piece — refine still works for that case, just
    // without persistence, since there's nothing to attach a version to.
    private static function persistDraftAttempt(
        int $pieceId,
        string $engine,
        string $generationMode,
        string $prompt,
        string $html,
        string $css,
        string $js,
        int $profileId,
        int $personaId,
        string $sequenceToken,
        int $attemptNumber,
        string $validationStatus,
        ?string $sonicParams = null
    ): ?int {
        if ($pieceId <= 0 || !PlatformArtPiece::find($pieceId)) {
            return null;
        }
        return PlatformArtPieceVersion::create([
            'art_piece_id' => $pieceId,
            'version_number' => PlatformArtPieceVersion::nextVersionNumber($pieceId),
            'prompt' => $prompt,
            'html_code' => $html,
            'css_code' => $css,
            'generated_code' => $js,
            'engine' => $engine,
            'generation_mode' => art_piece_normalize_generation_mode($generationMode, $engine),
            'validation_status' => $validationStatus,
            'generation_attempt_count' => $attemptNumber,
            'ai_profile_id' => $profileId ?: null,
            'ai_persona_id' => $personaId ?: null,
            'sonic_params' => $sonicParams,
            'is_draft_attempt' => true,
            'attempt_sequence_token' => $sequenceToken ?: null,
        ]);
    }

    private static function updateDraftValidationStatus(int $versionId, string $status): void
    {
        try {
            db()->prepare('UPDATE art_piece_versions SET validation_status = ? WHERE id = ? AND is_draft_attempt = 1')
                ->execute([$status, $versionId]);
        } catch (Throwable) {
            // Best-effort status label only — never let this break the
            // actual refine response.
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
        if (!feature_ai_piece_engine_enabled((string) ($piece['engine'] ?? 'p5'))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'AI Refine is disabled for this piece engine. Enable it under Admin → Features → AI.']);
            exit;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $html = (string) ($input['html_code'] ?? '');
            if (in_array($piece['engine'] ?? 'p5', art_piece_canvas_managed_engines(), true)) {
                $html = self::getStandardHtmlForEngine($piece['engine']);
            }
            $css = (string) ($input['css_code'] ?? '');
            $js = (string) ($input['generated_code'] ?? '');
            $refinementPrompt = trim((string) ($input['refinement_prompt'] ?? ''));
            $profileId = (int) ($input['profile_id'] ?? 0) ?: null;
            $personaId = (int) ($input['persona_id'] ?? 0) ?: null;
            $hasSubmittedSonic = array_key_exists('sonic_params', $input);
            $sonicParams = $hasSubmittedSonic
                ? self::normalizeSonicParamsInput($input['sonic_params'] ?? null, $currentVersion['sonic_params'] ?? null)
                : ($currentVersion['sonic_params'] ?? null);
            // The successful attempt that produced this code already
            // persisted itself as a draft version (refineAi()) — accepting
            // it should promote that exact row to current rather than
            // inserting a duplicate. Falls back to the legacy insert-new
            // behavior below if no draft is given or it doesn't resolve
            // (e.g. an older client, or the piece had no id yet when the
            // attempt ran).
            $draftVersionId = (int) ($input['draft_version_id'] ?? 0) ?: null;
            $sequenceToken = trim((string) ($input['sequence_token'] ?? ''));

            $codeChanged = self::normalizeCode($html) !== self::normalizeCode($currentVersion['html_code'] ?? null)
                || self::normalizeCode($css) !== self::normalizeCode($currentVersion['css_code'] ?? null)
                || self::normalizeCode($js) !== self::normalizeCode($currentVersion['generated_code'] ?? null);
            $sonicChanged = $hasSubmittedSonic
                && art_piece_sonic_params_supported()
                && !art_piece_sonic_params_equal($sonicParams, $currentVersion['sonic_params'] ?? null);

            if ($refinementPrompt === '') {
                if (!$codeChanged && $sonicChanged) {
                    $sonicParamsDecoded = json_decode((string) $sonicParams, true);
                    $soundFeel = $sonicParamsDecoded['feel'] ?? 'unspecified feel';
                    $refinementPrompt = 'Update sound design: ' . ($soundFeel !== '' ? $soundFeel : 'unspecified feel');
                } else {
                    throw new InvalidArgumentException('Refinement prompt is required.');
                }
            }

            if (!$codeChanged && !$sonicChanged) {
                echo json_encode([
                    'success' => true,
                    'changed' => false,
                    'version_id' => (int) $currentVersion['id'],
                    'version_number' => (int) $currentVersion['version_number'],
                ]);
                exit;
            }

            $draftVersion = $draftVersionId !== null ? PlatformArtPieceVersion::find($draftVersionId) : false;
            $draftIsUsable = $draftVersion
                && (int) $draftVersion['art_piece_id'] === (int) $id
                && (int) ($draftVersion['is_draft_attempt'] ?? 0) === 1;
            $currentGenerationMode = self::storedGenerationMode($currentVersion, (string) ($piece['engine'] ?? 'p5'));

            if ($draftIsUsable) {
                // Re-write the draft's code too, not just promote it as-is —
                // the admin can hand-edit the proposed code in the textareas
                // before clicking Accept, so the draft's originally-stored
                // attempt content isn't guaranteed to match what's being
                // accepted here.
                PlatformArtPieceVersion::update($draftVersionId, [
                    'prompt' => $refinementPrompt,
                    'structured_spec' => $currentVersion['structured_spec'] ?? null,
                    'html_code' => self::normalizeCode($html),
                    'css_code' => self::normalizeCode($css),
                    'generated_code' => self::normalizeCode($js),
                    'engine' => $draftVersion['engine'] ?? ($currentVersion['engine'] ?? $piece['engine']),
                    'generation_vendor' => $currentVersion['generation_vendor'] ?? null,
                    'generation_model' => $currentVersion['generation_model'] ?? null,
                    'generation_mode' => self::storedGenerationMode($draftVersion, $currentGenerationMode),
                    'validation_status' => 'validated',
                    'generation_attempt_count' => $draftVersion['generation_attempt_count'] ?? 1,
                    'notes' => 'Saved via AI Refine accept.',
                    'ai_profile_id' => $profileId,
                    'ai_persona_id' => $personaId,
                    'sonic_params' => $sonicParams,
                ]);
                PlatformArtPieceVersion::promoteDraftToCurrent($draftVersionId, $refinementPrompt);
                PlatformArtPiece::updateCurrentVersion((int) $id, $draftVersionId);
                $versionId = $draftVersionId;
                $versionNumber = (int) $draftVersion['version_number'];
            } else {
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
                    'generation_mode' => $currentGenerationMode,
                    'validation_status' => $currentVersion['validation_status'] ?? null,
                    'generation_attempt_count' => $currentVersion['generation_attempt_count'] ?? 0,
                    'notes' => 'Saved via AI Refine accept.',
                    'ai_profile_id' => $profileId,
                    'ai_persona_id' => $personaId,
                    'sonic_params' => $sonicParams,
                ]);
                PlatformArtPiece::updateCurrentVersion((int) $id, $versionId);
            }

            // Delete the failed-attempt siblings from this same retry
            // sequence now that one of them succeeded and was accepted —
            // per explicit instruction, only on success; a sequence that's
            // abandoned without ever succeeding keeps all its drafts.
            if ($sequenceToken !== '') {
                PlatformArtPieceVersion::deleteBySequenceToken((int) $id, $sequenceToken, $versionId);
            }

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

    private static function loadPendingGeneration(): ?array
    {
        self::ensureWritableSession();
        $pending = $_SESSION['pending_generation'] ?? null;
        session_write_close();
        return is_array($pending) ? $pending : null;
    }

    private static function storePendingGeneration(array $pending): void
    {
        self::ensureWritableSession();
        $_SESSION['pending_generation'] = [
            'original' => [
                'prompt' => (string) ($pending['prompt'] ?? ''),
                'engine' => (string) ($pending['engine'] ?? 'p5'),
                'generation_mode' => (string) ($pending['generation_mode'] ?? ($pending['engine'] ?? 'p5')),
                'vendor' => (string) ($pending['vendor'] ?? ''),
                'model' => (string) ($pending['model'] ?? ''),
                'endpoint_kind' => (string) ($pending['endpoint_kind'] ?? ''),
                'attempt_count' => (int) ($pending['attempt_count'] ?? 1),
                'profile_id' => (int) ($pending['profile_id'] ?? 0),
                'persona_id' => (int) ($pending['persona_id'] ?? 0),
                // Audio-lineage constants (per the per-domain rule,
                // regenerate derives its purpose_domain PURELY from these).
                // sound_feel is the prose the admin wrote at generate time
                // (reused to re-emit sonic capability instructions on an
                // audio-in-scope regenerate); sound_enabled_lineage marks
                // that this generation was an audio+visual generation,
                // even if the model produced no usable sonic_params block.
                'sound_feel' => (string) ($pending['sound_feel'] ?? ''),
                'sound_enabled_lineage' => (bool) ($pending['sound_enabled_lineage'] ?? false),
            ],
            'current' => [
                'engine' => (string) ($pending['engine'] ?? 'p5'),
                'html_code' => (string) ($pending['html_code'] ?? ''),
                'css_code' => (string) ($pending['css_code'] ?? ''),
                'generated_code' => (string) ($pending['generated_code'] ?? ''),
                'sonic_params' => isset($pending['sonic_params']) ? (string) $pending['sonic_params'] : null,
            ],
        ];
        session_write_close();
    }

    private static function updatePendingGenerationCurrent(array $current): void
    {
        self::ensureWritableSession();
        $pending = $_SESSION['pending_generation'] ?? null;
        if (!is_array($pending)) {
            $pending = ['original' => [], 'current' => []];
        }
        $pending['current'] = array_merge(is_array($pending['current'] ?? null) ? $pending['current'] : [], $current);
        $_SESSION['pending_generation'] = $pending;
        session_write_close();
    }

    private static function clearPendingGeneration(): void
    {
        self::ensureWritableSession();
        unset($_SESSION['pending_generation']);
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
