<?php

declare(strict_types=1);

/**
 * Feature modularity flags — content-safe semantics.
 *
 * Toggling a feature OFF blocks creating NEW content (and its AI surfaces)
 * only. Existing published content keeps its public URLs, stays listed in
 * public navigation, and remains editable/deletable in admin. Flags are
 * stored as a `features_json` map inside site_settings.settings_json; a
 * missing or unreadable store fails OPEN (everything enabled) so fresh
 * installs work before the database exists.
 */

/**
 * key => [
 *   label, description, group (panel subtab),
 *   requires  => parent feature keys that must also be enabled,
 *   model     => model class holding this feature's content (null = no content),
 *   admin_href => admin index this feature's blocked routes redirect to,
 * ]
 */
function feature_registry(): array
{
    return [
        'pieces' => [
            'label' => 'Art Pieces',
            'description' => 'Platform art pieces: manual creation, versions, and forks.',
            'group' => 'pieces',
            'requires' => [],
            'model' => 'PlatformArtPiece',
            'admin_href' => '/admin/pieces',
        ],
        'platform_collections' => [
            'label' => 'Platform Collections',
            'description' => 'Collections of art pieces and media.',
            'group' => 'pieces',
            'requires' => ['pieces'],
            'model' => 'PlatformCollection',
            'admin_href' => '/admin/platform-collections',
        ],
        'exhibits' => [
            'label' => 'Exhibits',
            'description' => 'Native exhibits with carousel media.',
            'group' => 'exhibits',
            'requires' => [],
            'model' => 'Exhibit',
            'admin_href' => '/admin/exhibits',
        ],
        'exhibit_collections' => [
            'label' => 'Exhibit Collections',
            'description' => 'Curated groups of exhibits.',
            'group' => 'exhibits',
            'requires' => ['exhibits'],
            'model' => 'Collection',
            'admin_href' => '/admin/exhibit-collections',
        ],
        'blog' => [
            'label' => 'Blog',
            'description' => 'Posts, blog categories, and external feed import.',
            'group' => 'blog',
            'requires' => [],
            'model' => 'BlogPost',
            'admin_href' => '/admin/posts',
        ],
        'ai' => [
            'label' => 'AI',
            'description' => 'Turning this off disables every AI capability at once.',
            'group' => 'ai',
            'requires' => [],
            'model' => null,
            'admin_href' => '/admin/features?tab=ai',
        ],
        'ai_pieces_code' => [
            'label' => 'Piece code generation',
            'description' => 'Generate Piece with AI and AI Refine for art piece code.',
            'group' => 'ai',
            'requires' => ['ai', 'pieces'],
            'model' => null,
            'admin_href' => '/admin/pieces',
        ],
        'ai_pieces_p5' => [
            'label' => 'P5.js',
            'description' => 'Allow AI generation for P5.js canvas pieces.',
            'group' => 'ai',
            'requires' => ['ai', 'ai_pieces_code'],
            'model' => null,
            'admin_href' => '/admin/pieces',
        ],
        'ai_pieces_c2' => [
            'label' => 'C2.js',
            'description' => 'Allow AI generation and AI Refine for C2.js pieces.',
            'group' => 'ai',
            'requires' => ['ai', 'ai_pieces_code'],
            'model' => null,
            'admin_href' => '/admin/pieces',
        ],
        'ai_pieces_c2_interactive' => [
            'label' => 'C2.js Interactive',
            'description' => 'Allow AI generation for click, touch, and drag C2.js pieces.',
            'group' => 'ai',
            'requires' => ['ai', 'ai_pieces_code'],
            'model' => null,
            'admin_href' => '/admin/pieces',
        ],
        'ai_pieces_three' => [
            'label' => 'Three.js',
            'description' => 'Allow AI generation and AI Refine for Three.js pieces.',
            'group' => 'ai',
            'requires' => ['ai', 'ai_pieces_code'],
            'model' => null,
            'admin_href' => '/admin/pieces',
        ],
        'ai_pieces_svg' => [
            'label' => 'SVG',
            'description' => 'Allow AI generation and AI Refine for SVG pieces.',
            'group' => 'ai',
            'requires' => ['ai', 'ai_pieces_code'],
            'model' => null,
            'admin_href' => '/admin/pieces',
        ],
        'ai_pieces_aframe' => [
            'label' => 'A-Frame',
            'description' => 'Allow AI generation and AI Refine for A-Frame pieces.',
            'group' => 'ai',
            'requires' => ['ai', 'ai_pieces_code'],
            'model' => null,
            'admin_href' => '/admin/pieces',
        ],
        'ai_theme' => [
            'label' => 'Theme generation',
            'description' => 'AI Assist for site theme CSS/JS/HTML in Site Identity.',
            'group' => 'ai',
            'requires' => ['ai'],
            'model' => null,
            'admin_href' => '/admin/site-identity?tab=design',
        ],
        'ai_alt_text' => [
            'label' => 'Image descriptions',
            'description' => 'AI alt-text generation for images (vision profiles).',
            'group' => 'ai',
            'requires' => ['ai'],
            'model' => null,
            'admin_href' => '/admin/media',
        ],
        'ai_editor' => [
            'label' => 'Editor AI',
            'description' => 'Master switch for text improvement tools inside content editors.',
            'group' => 'ai',
            'requires' => ['ai'],
            'model' => null,
            'admin_href' => '/admin/features?tab=ai',
        ],
        'ai_text_pages' => [
            'label' => 'Pages',
            'description' => 'Text improvement sparkle in the page editor.',
            'group' => 'ai',
            'requires' => ['ai', 'ai_editor'],
            'model' => null,
            'admin_href' => '/admin/pages',
        ],
        'ai_text_blog' => [
            'label' => 'Blog',
            'description' => 'Text improvement sparkle in the post editor.',
            'group' => 'ai',
            'requires' => ['ai', 'ai_editor'],
            'model' => null,
            'admin_href' => '/admin/posts',
        ],
        'ai_text_exhibits' => [
            'label' => 'Exhibits',
            'description' => 'Text improvement sparkle in the exhibit editor.',
            'group' => 'ai',
            'requires' => ['ai', 'ai_editor'],
            'model' => null,
            'admin_href' => '/admin/exhibits',
        ],
        'ai_text_platform_collections' => [
            'label' => 'Platform Collections',
            'description' => 'Text improvement sparkle in the platform collection editor.',
            'group' => 'ai',
            'requires' => ['ai', 'ai_editor'],
            'model' => null,
            'admin_href' => '/admin/platform-collections',
        ],
        'ai_text_media' => [
            'label' => 'Media',
            'description' => 'Text improvement sparkle in the media library.',
            'group' => 'ai',
            'requires' => ['ai', 'ai_editor'],
            'model' => null,
            'admin_href' => '/admin/media',
        ],
    ];
}

/** Loads a model class on demand — public pages don't go through router.php's requires. */
function feature_require_class(string $class): bool
{
    if (class_exists($class)) {
        return true;
    }
    $file = dirname(__DIR__) . '/models/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
    return class_exists($class);
}

/**
 * Raw stored flag map. $setOverride/$applyOverride is a test seam so CLI
 * tests can inject flags (or reset with null) without a database.
 */
function feature_flags(?array $setOverride = null, bool $applyOverride = false): array
{
    static $override = null;
    static $cache = null;

    if ($applyOverride) {
        $override = $setOverride;
        $cache = null;
        return $override ?? [];
    }
    if ($override !== null) {
        return $override;
    }
    if ($cache !== null) {
        return $cache;
    }

    $flags = [];
    try {
        if (function_exists('db') && feature_require_class('SiteSettings')) {
            $settings = SiteSettings::current();
            if (is_array($settings)) {
                $raw = $settings['features_json'] ?? null;
                if (is_string($raw) && trim($raw) !== '') {
                    $decoded = json_decode($raw, true);
                    $raw = is_array($decoded) ? $decoded : null;
                }
                if (is_array($raw)) {
                    $flags = $raw;
                }
            }
        }
    } catch (Throwable) {
        $flags = [];
    }

    return $cache = $flags;
}

function feature_flags_override(?array $flags): void
{
    feature_flags($flags, true);
}

/**
 * Effective flag value: stored value (default TRUE, fail-open on junk),
 * AND'd with every feature it requires. Unknown keys are always enabled —
 * un-toggleable surfaces (pages) never appear in the registry.
 */
function feature_enabled(string $key): bool
{
    $registry = feature_registry();
    if (!isset($registry[$key])) {
        return true;
    }

    $flags = feature_flags();
    if (array_key_exists($key, $flags)) {
        $value = filter_var($flags[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === false) {
            return false;
        }
    }

    foreach ($registry[$key]['requires'] as $parent) {
        if (!feature_enabled($parent)) {
            return false;
        }
    }

    return true;
}

/** Non-deleted rows of any status — drives the admin "manage existing only" state. */
function feature_has_existing_content(string $key): bool
{
    $model = feature_registry()[$key]['model'] ?? null;
    if ($model === null || !function_exists('db') || !feature_require_class($model) || !method_exists($model, 'countExisting')) {
        return false;
    }
    try {
        return $model::countExisting() > 0;
    } catch (Throwable) {
        return false;
    }
}

/** Published/active rows — drives public navigation visibility. */
function feature_has_public_content(string $key): bool
{
    if (!function_exists('db')) {
        return false;
    }

    try {
        return match ($key) {
            'pieces' => feature_require_class('PlatformArtPiece') && PlatformArtPiece::countActive() > 0,
            'platform_collections' => feature_require_class('PlatformCollection') && PlatformCollection::countVisible() > 0,
            'exhibits' => feature_require_class('Exhibit') && Exhibit::countVisible() > 0,
            'exhibit_collections' => feature_require_class('Collection') && Collection::countWithAtLeastOneExhibit() > 0,
            'blog' => feature_require_class('BlogPost') && BlogPost::countPublished() > 0,
            default => false,
        };
    } catch (Throwable) {
        return false;
    }
}

/**
 * Maps an editor context (posted by the shared TipTap editor) to its
 * per-area editor-AI flag.
 */
function feature_ai_text_flag_for_context(string $context): ?string
{
    $map = [
        'pages' => 'ai_text_pages',
        'blog' => 'ai_text_blog',
        'exhibits' => 'ai_text_exhibits',
        'platform_collections' => 'ai_text_platform_collections',
        'media' => 'ai_text_media',
    ];
    return $map[$context] ?? null;
}

function feature_ai_piece_flag_for_generation_mode(string $mode): ?string
{
    $map = [
        'p5' => 'ai_pieces_p5',
        'c2' => 'ai_pieces_c2',
        'c2_interactive' => 'ai_pieces_c2_interactive',
        'three' => 'ai_pieces_three',
        'svg' => 'ai_pieces_svg',
        'aframe' => 'ai_pieces_aframe',
    ];
    return $map[$mode] ?? null;
}

function feature_ai_piece_flag_for_engine(string $engine): ?string
{
    return feature_ai_piece_flag_for_generation_mode($engine === 'c2_interactive' ? 'c2' : $engine);
}

function feature_ai_piece_generation_mode_enabled(string $mode): bool
{
    $flag = feature_ai_piece_flag_for_generation_mode($mode);
    return $flag !== null && feature_enabled($flag);
}

function feature_ai_piece_engine_enabled(string $engine): bool
{
    $flag = feature_ai_piece_flag_for_engine($engine);
    return $flag !== null && feature_enabled($flag);
}

function feature_any_ai_piece_generation_mode_enabled(): bool
{
    foreach (['p5', 'c2', 'c2_interactive', 'three', 'svg', 'aframe'] as $mode) {
        if (feature_ai_piece_generation_mode_enabled($mode)) {
            return true;
        }
    }
    return false;
}

function feature_art_media_creation_enabled(): bool
{
    return feature_enabled('pieces') || feature_enabled('exhibits');
}

/** Admin index banner for a disabled feature; empty string when enabled. */
function feature_disabled_notice(string $key): string
{
    if (feature_enabled($key)) {
        return '';
    }
    return '<div class="form-status" role="status"><p>This feature is turned off — existing items stay editable and deletable, but new ones can&#8217;t be created. Enable it under <a href="/admin/features">Features</a>.</p></div>';
}

/**
 * Response for a route whose feature is disabled: JSON endpoints get a 403
 * payload, form/browse routes bounce to the feature's admin index with the
 * standard ?error= notice.
 */
function feature_blocked_response(string $key, string $method, string $pattern): void
{
    $jsonPatterns = [
        '/admin/pieces/generate',
        '/admin/pieces/generate/save',
        '/admin/pieces/refine-ai',
        '/admin/pieces/([0-9]+)/refine-save',
        '/admin/site-identity/theme-generate',
        '/admin/site-identity/theme-refine',
        '/admin/ai/process',
        '/admin/ai/describe-image',
    ];

    if ($method === 'POST' && in_array($pattern, $jsonPatterns, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'This feature is disabled. Enable it under Admin → Features.']);
        return;
    }

    $registry = feature_registry();
    $target = (string) ($registry[$key]['admin_href'] ?? '/admin/features');
    $separator = str_contains($target, '?') ? '&' : '?';
    http_response_code(303);
    header('Location: ' . $target . $separator . 'error=' . rawurlencode('This feature is disabled. Enable it under Features to create new items.'));
}
