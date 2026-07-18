<?php
/**
 * Tests the shared media_kind_from_mime() classifier used by the admin media
 * library page, the library JSON feed, and the media picker. Guards the
 * audio-misclassification regression (audio uploads previously rendered as
 * broken image cards).
 * Run with: php tests/media-kind.php
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

require __DIR__ . '/../public/app/helpers/upload.php';

echo "=== media_kind_from_mime() ===\n";

test('audio MIME types classify as audio (regression: were image)', function () {
    foreach (['audio/mpeg', 'audio/ogg', 'audio/wav'] as $mime) {
        assert_true(media_kind_from_mime($mime) === 'audio', "{$mime} should be audio");
    }
});

test('model MIME types classify as model', function () {
    assert_true(media_kind_from_mime('model/gltf-binary') === 'model', 'glb should be model');
    assert_true(media_kind_from_mime('model/gltf+json') === 'model', 'gltf should be model');
});

test('video MIME types classify as video', function () {
    foreach (['video/mp4', 'video/webm', 'video/quicktime'] as $mime) {
        assert_true(media_kind_from_mime($mime) === 'video', "{$mime} should be video");
    }
});

test('embed MIME types classify as iframe', function () {
    assert_true(media_kind_from_mime('text/html') === 'iframe', 'text/html should be iframe');
    assert_true(media_kind_from_mime('iframe') === 'iframe', 'iframe prefix should be iframe');
});

test('image MIME types classify as image', function () {
    foreach (['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'] as $mime) {
        assert_true(media_kind_from_mime($mime) === 'image', "{$mime} should be image");
    }
});

test('unknown or empty MIME falls back to image', function () {
    assert_true(media_kind_from_mime('application/octet-stream') === 'image', 'octet-stream should fall back to image');
    assert_true(media_kind_from_mime('') === 'image', 'empty mime should fall back to image');
});

echo "\n=== media_kind_can_have_poster() ===\n";

test('every non-image kind can carry a poster', function () {
    foreach (['video/mp4', 'audio/mpeg', 'model/gltf-binary', 'text/html', 'iframe'] as $mime) {
        assert_true(media_kind_can_have_poster($mime), "{$mime} should support a poster");
    }
});

test('image kinds (and the unknown fallback) cannot carry a poster', function () {
    foreach (['image/png', 'image/jpeg', 'application/octet-stream', ''] as $mime) {
        assert_true(!media_kind_can_have_poster($mime), "'{$mime}' should not support a poster");
    }
});

echo "\nPassed: {$passed}, Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
