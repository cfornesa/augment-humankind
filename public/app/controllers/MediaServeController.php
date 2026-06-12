<?php

declare(strict_types=1);

class MediaServeController
{
    public static function media(string $id): void
    {
        $id = (int) $id;
        if ($id <= 0) {
            http_response_code(404);
            exit;
        }

        $row = MediaFile::getData($id);
        if (!$row || $row['deleted_at'] !== null || $row['data'] === null) {
            http_response_code(404);
            exit;
        }

        $mime = $row['mime_type'] ?? 'application/octet-stream';
        $data = $row['data'];
        $size = (int) ($row['byte_size'] ?? mb_strlen($data, '8bit'));
        $etag = '"' . sha1((string) $row['id'] . '|' . $mime . '|' . $size) . '"';

        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            exit;
        }

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('ETag: ' . $etag);
        header('Accept-Ranges: bytes');

        $rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';
        if (str_starts_with($mime, 'video/') && preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $matches)) {
            $start = $matches[1] === '' ? 0 : max(0, (int) $matches[1]);
            $end = $matches[2] === '' ? ($size - 1) : min($size - 1, (int) $matches[2]);

            if ($start > $end || $start >= $size) {
                header('Content-Range: bytes */' . $size);
                http_response_code(416);
                exit;
            }

            $length = $end - $start + 1;
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Content-Length: ' . $length);
            echo substr($data, $start, $length);
            exit;
        }

        header('Content-Length: ' . $size);
        echo $data;
        exit;
    }

    public static function image(string $id): void
    {
        $row = MediaFile::getData((int) $id);
        if (!$row || $row['deleted_at'] !== null || !str_starts_with((string) ($row['mime_type'] ?? ''), 'image/')) {
            http_response_code(404);
            exit;
        }

        self::media($id);
    }
}
