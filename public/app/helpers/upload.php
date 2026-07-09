<?php

declare(strict_types=1);

const ALLOWED_IMAGE_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/avif' => 'avif',
];

const ALLOWED_VIDEO_MIME = [
    'video/mp4'       => 'mp4',
    'video/webm'      => 'webm',
    'video/quicktime' => 'mov',
];

// 3D model formats are routed by file extension (finfo is unreliable for these:
// .glb sniffs as application/octet-stream, .obj as text/plain, .gltf as JSON/text),
// then stored under a canonical model/* MIME so downstream classification and
// serving stay reliable. Geometry-only for v1 — no bundled .mtl/textures.
const ALLOWED_MODEL_EXT = [
    'glb'  => 'model/gltf-binary',
    'gltf' => 'model/gltf+json',
    'obj'  => 'model/obj',
];

// Kept ≤ the session max_allowed_packet raised in upload_media() so the blob
// insert cannot exceed what the DB will accept in one packet.
const MODEL_MAX_BYTES = 64 * 1024 * 1024;

function upload_ini_limit_message(): string
{
    $uploadMax = ini_get('upload_max_filesize') ?: 'unknown';
    $postMax = ini_get('post_max_size') ?: 'unknown';
    return 'Server limits are upload_max_filesize=' . $uploadMax . ' and post_max_size=' . $postMax . '.';
}

function upload_resolve_mime(array $file, string $label = 'File'): string
{
    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        throw new RuntimeException($label . ' upload could not be inspected. ' . upload_ini_limit_message());
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpName);
    if (!is_string($mime) || $mime === '') {
        throw new RuntimeException($label . ' type could not be detected.');
    }

    return $mime;
}

function upload_media(array $file, array $allowedMimeMap, int $maxBytes, string $label = 'File', array $attributes = []): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload size limit. ' . upload_ini_limit_message(),
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];
        throw new RuntimeException($messages[$file['error']] ?? 'Upload error: ' . $file['error']);
    }

    $mime = upload_resolve_mime($file, $label);

    if (!isset($allowedMimeMap[$mime])) {
        throw new RuntimeException($label . ' type not permitted.');
    }

    $blob = file_get_contents((string) $file['tmp_name']);
    if ($blob === false) {
        throw new RuntimeException('Could not read uploaded file.');
    }

    if (mb_strlen($blob, '8bit') > $maxBytes) {
        throw new RuntimeException($label . ' exceeds the upload limit.');
    }

    try {
        db()->exec('SET SESSION max_allowed_packet = 67108864');
    } catch (\Exception) {
    }

    $id = MediaFile::create($blob, $mime, basename((string) ($file['name'] ?? '')), $attributes);

    return [
        'id' => $id,
        'mime_type' => $mime,
        'url' => '/media/' . $id,
        'legacy_url' => str_starts_with($mime, 'image/') ? '/image/' . $id : null,
    ];
}

/**
 * Returns the lowercased extension for an uploaded file, or '' if none.
 */
function upload_file_extension(array $file): string
{
    $name = (string) ($file['name'] ?? '');
    return strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
}

/**
 * Stores an uploaded 3D model (OBJ/GLTF/GLB). Routed by extension rather than
 * by finfo MIME, then persisted under a canonical model/* MIME so the media
 * grid, picker, and /media/{id} serving all classify it correctly.
 */
function upload_model_media(array $file, array $attributes = []): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        // Reuse upload_media()'s error triage for a specific message.
        return upload_media($file, ALLOWED_MODEL_EXT, MODEL_MAX_BYTES, '3D model', $attributes);
    }

    $ext = upload_file_extension($file);
    if (!isset(ALLOWED_MODEL_EXT[$ext])) {
        throw new RuntimeException('3D model type not permitted. Allowed formats: OBJ, GLTF, GLB.');
    }
    $canonicalMime = ALLOWED_MODEL_EXT[$ext];

    // Sanity-check the bytes are inspectable (throws with a clear message otherwise).
    upload_resolve_mime($file, '3D model');

    $blob = file_get_contents((string) $file['tmp_name']);
    if ($blob === false) {
        throw new RuntimeException('Could not read uploaded 3D model.');
    }
    if (mb_strlen($blob, '8bit') > MODEL_MAX_BYTES) {
        throw new RuntimeException('3D model exceeds the upload limit.');
    }

    try {
        db()->exec('SET SESSION max_allowed_packet = 67108864');
    } catch (\Exception) {
    }

    $id = MediaFile::create($blob, $canonicalMime, basename((string) ($file['name'] ?? '')), $attributes);

    return [
        'id' => $id,
        'mime_type' => $canonicalMime,
        'url' => '/media/' . $id,
        'legacy_url' => null,
    ];
}

function upload_media_auto(array $file, array $attributes = []): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return upload_media($file, ALLOWED_IMAGE_MIME, 8 * 1024 * 1024, 'File', $attributes);
    }

    // 3D models are identified by extension (finfo can't distinguish them reliably)
    // and only when the feature is enabled; otherwise they fall through and are
    // rejected by the image/video allowlists below.
    $modelsEnabled = !function_exists('feature_enabled') || feature_enabled('media_models');
    if ($modelsEnabled && isset(ALLOWED_MODEL_EXT[upload_file_extension($file)])) {
        return upload_model_media($file, $attributes);
    }

    $mime = upload_resolve_mime($file);
    if (isset(ALLOWED_VIDEO_MIME[$mime])) {
        return upload_media($file, ALLOWED_VIDEO_MIME, 64 * 1024 * 1024, 'Video', $attributes);
    }

    return upload_media($file, ALLOWED_IMAGE_MIME, 8 * 1024 * 1024, 'Image', $attributes);
}
