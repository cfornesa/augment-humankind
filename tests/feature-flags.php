<?php
/**
 * Tests the feature-flag helper's effective-value rules: fail-open defaults,
 * dependency chains, and the AI master switch.
 * Run with: php tests/feature-flags.php
 */

declare(strict_types=1);

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

function assert_true(bool $cond, string $msg): void {
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

require __DIR__ . '/../public/app/helpers/features.php';
require __DIR__ . '/../public/app/helpers/admin-navigation.php';

echo "=== feature_enabled() ===\n";

test('defaults to enabled with no stored flags (fresh install fails open)', function () {
    feature_flags_override([]);
    foreach (array_keys(feature_registry()) as $key) {
        assert_true(feature_enabled($key), "{$key} should default to enabled");
    }
});

test('unknown keys are always enabled (pages has no toggle)', function () {
    feature_flags_override(['pages' => false]);
    assert_true(feature_enabled('pages'), 'pages must not be toggleable');
    assert_true(feature_enabled('some_future_key'), 'unknown keys fail open');
});

test('a stored false disables the feature', function () {
    feature_flags_override(['blog' => false]);
    assert_true(!feature_enabled('blog'), 'blog should be disabled');
    assert_true(feature_enabled('exhibits'), 'other features unaffected');
});

test('junk stored values fail open', function () {
    feature_flags_override(['blog' => 'banana']);
    assert_true(feature_enabled('blog'), 'unparseable value should fail open');
});

test('string representations of booleans are accepted', function () {
    feature_flags_override(['blog' => 'false', 'exhibits' => '0', 'pieces' => 'true']);
    assert_true(!feature_enabled('blog'), "'false' disables");
    assert_true(!feature_enabled('exhibits'), "'0' disables");
    assert_true(feature_enabled('pieces'), "'true' enables");
});

echo "\n=== Dependency chains ===\n";

test('platform collections require pieces', function () {
    feature_flags_override(['pieces' => false, 'platform_collections' => true]);
    assert_true(!feature_enabled('platform_collections'), 'child off when parent off');
});

test('exhibit collections require exhibits', function () {
    feature_flags_override(['exhibits' => false, 'exhibit_collections' => true]);
    assert_true(!feature_enabled('exhibit_collections'), 'child off when parent off');
});

test('re-enabling the parent restores a stored-true child', function () {
    feature_flags_override(['exhibits' => true, 'exhibit_collections' => true]);
    assert_true(feature_enabled('exhibit_collections'), 'child restored with parent');
});

echo "\n=== AI master switch ===\n";

test('master AI off disables every AI capability', function () {
    feature_flags_override(['ai' => false]);
    foreach (array_keys(feature_registry()) as $key) {
        if ($key !== 'ai' && str_starts_with($key, 'ai')) {
            assert_true(!feature_enabled($key), "{$key} should be off under master switch");
        }
    }
    assert_true(feature_enabled('blog'), 'content features unaffected by AI master');
});

test('piece code generation also requires the pieces feature', function () {
    feature_flags_override(['pieces' => false]);
    assert_true(!feature_enabled('ai_pieces_code'), 'no piece generation without pieces');
    assert_true(!feature_enabled('ai_pieces_p5'), 'no P5 generation without pieces');
});

test('piece code generation disables all piece AI mode flags', function () {
    feature_flags_override(['ai_pieces_code' => false]);
    foreach (['ai_pieces_p5', 'ai_pieces_c2', 'ai_pieces_c2_interactive', 'ai_pieces_three', 'ai_pieces_svg', 'ai_pieces_aframe'] as $key) {
        assert_true(!feature_enabled($key), "{$key} should be off when piece code generation is off");
    }
    assert_true(feature_enabled('ai_theme'), 'theme generation unaffected by piece code generation');
});

test('editor AI master disables editor function flags', function () {
    feature_flags_override(['ai_editor' => false]);
    foreach (['ai_text_pages', 'ai_text_blog', 'ai_text_exhibits', 'ai_text_platform_collections', 'ai_text_media'] as $key) {
        assert_true(!feature_enabled($key), "{$key} should be off when editor AI is off");
    }
    assert_true(feature_enabled('ai_pieces_code'), 'piece code generation unaffected by editor AI');
});

echo "\n=== Editor context mapping ===\n";

test('every editor context maps to a registered flag', function () {
    foreach (['pages', 'blog', 'exhibits', 'platform_collections', 'media'] as $context) {
        $flag = feature_ai_text_flag_for_context($context);
        assert_true($flag !== null, "context {$context} must map to a flag");
        assert_true(isset(feature_registry()[$flag]), "flag {$flag} must be registered");
    }
    assert_true(feature_ai_text_flag_for_context('pieces') === null, 'pieces do not have editor text AI');
    assert_true(feature_ai_text_flag_for_context('bogus') === null, 'unknown contexts are rejected');
});

echo "\n=== Piece AI mapping ===\n";

test('piece generation mode mapping covers supported AI modes', function () {
    $expected = [
        'p5' => 'ai_pieces_p5',
        'c2' => 'ai_pieces_c2',
        'c2_interactive' => 'ai_pieces_c2_interactive',
        'three' => 'ai_pieces_three',
        'svg' => 'ai_pieces_svg',
        'aframe' => 'ai_pieces_aframe',
    ];
    foreach ($expected as $mode => $flag) {
        assert_true(feature_ai_piece_flag_for_generation_mode($mode) === $flag, "{$mode} should map to {$flag}");
    }
    assert_true(feature_ai_piece_flag_for_generation_mode('bogus') === null, 'unknown generation modes are rejected');
});

test('piece engine mapping uses C2.js for saved C2 interactive pieces', function () {
    assert_true(feature_ai_piece_flag_for_engine('c2_interactive') === 'ai_pieces_c2', 'saved C2 interactive refine uses C2 flag');
    feature_flags_override(['ai_pieces_svg' => false]);
    assert_true(!feature_ai_piece_generation_mode_enabled('svg'), 'disabled SVG flag blocks SVG generation');
    assert_true(feature_ai_piece_engine_enabled('c2'), 'C2 remains enabled');
});

test('piece generation availability requires at least one enabled mode', function () {
    feature_flags_override([
        'ai_pieces_p5' => false,
        'ai_pieces_c2' => false,
        'ai_pieces_c2_interactive' => false,
        'ai_pieces_three' => false,
        'ai_pieces_svg' => false,
        'ai_pieces_aframe' => false,
    ]);
    assert_true(!feature_any_ai_piece_generation_mode_enabled(), 'all disabled engine flags hide generation entrypoint');

    feature_flags_override(['ai_pieces_svg' => false]);
    assert_true(feature_any_ai_piece_generation_mode_enabled(), 'one enabled mode is enough to show generation entrypoint');
});

echo "\n=== Content creation helpers ===\n";

test('art media creation requires pieces or exhibits', function () {
    feature_flags_override(['pieces' => false, 'exhibits' => false]);
    assert_true(!feature_art_media_creation_enabled(), 'art media creation is disabled when both parents are off');

    feature_flags_override(['pieces' => true, 'exhibits' => false]);
    assert_true(feature_art_media_creation_enabled(), 'pieces enables art media creation');

    feature_flags_override(['pieces' => false, 'exhibits' => true]);
    assert_true(feature_art_media_creation_enabled(), 'exhibits enables art media creation');
});

echo "\n=== Admin navigation mapping ===\n";

test('AI Settings is configuration and is not hidden by the AI runtime switch', function () {
    assert_true(!isset(admin_navigation_feature_map()['ai_settings']), 'AI Settings must not be feature-gated by ai');
});

echo "\n=== Registry integrity ===\n";

test('ai_text_pieces is not registered', function () {
    assert_true(!isset(feature_registry()['ai_text_pieces']), 'piece editor AI flag must not exist');
});

test('every requires target exists in the registry', function () {
    $registry = feature_registry();
    foreach ($registry as $key => $meta) {
        foreach ($meta['requires'] as $parent) {
            assert_true(isset($registry[$parent]), "{$key} requires unknown feature {$parent}");
        }
    }
});

feature_flags_override(null);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
