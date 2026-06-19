<?php

declare(strict_types=1);

class OgController
{
    public static function postImage(string $id): void
    {
        if (!extension_loaded('gd')) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'GD extension not available.';
            exit;
        }

        $post = ctype_digit($id) ? BlogPost::findPublished((int) $id) : false;
        if (!$post) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Not found.';
            exit;
        }

        $settings = SiteSettings::current() ?: [];
        $image = imagecreatetruecolor(1200, 630);
        if ($image === false) {
            http_response_code(500);
            exit;
        }

        imageantialias($image, true);

        $bg = self::allocateHex($image, self::siteColorHex($settings['color_background'] ?? null, '#f7f3e8'));
        $ink = self::allocateHex($image, self::siteColorHex($settings['color_foreground'] ?? null, '#1e1b16'));
        $muted = self::allocateHex($image, self::siteColorHex($settings['color_muted_foreground'] ?? null, '#5b5448'));
        $accent = self::allocateHex($image, self::siteColorHex($settings['color_primary'] ?? null, '#2a7c6f'));
        $panel = self::allocateHex($image, self::siteColorHex($settings['color_muted'] ?? null, '#e9dfcf'));

        imagefilledrectangle($image, 0, 0, 1200, 630, $bg);
        imagefilledrectangle($image, 64, 64, 1136, 566, $panel);
        imagefilledrectangle($image, 64, 64, 86, 566, $accent);

        $title = trim((string) (($post['title'] ?? '') ?: 'Untitled post'));
        $siteName = app_site_name();
        $date = date('M j, Y', strtotime((string) ($post['created_at'] ?? 'now')) ?: time());
        $category = '';
        if (!empty($post['categories']) && is_array($post['categories'])) {
            $first = $post['categories'][0] ?? null;
            if (is_array($first)) {
                $category = trim((string) ($first['name'] ?? ''));
            }
        }

        $bodyFont = self::usableFontPath();
        if ($bodyFont !== null) {
            self::drawWrappedText($image, 28, 0, 126, 170, 920, $siteName, $muted, $bodyFont, 40);
            self::drawWrappedText($image, 48, 0, 126, 250, 920, $title, $ink, $bodyFont, 58);

            $meta = $date . ($category !== '' ? '  •  ' . $category : '');
            self::drawWrappedText($image, 24, 0, 126, 500, 920, $meta, $accent, $bodyFont, 32);
        } else {
            imagestring($image, 5, 126, 120, $siteName, $muted);
            imagestring($image, 5, 126, 220, substr($title, 0, 90), $ink);
            imagestring($image, 4, 126, 500, $date . ($category !== '' ? ' - ' . $category : ''), $accent);
        }

        $logoPath = self::localLogoPath((string) ($settings['logo_url'] ?? ''));
        if ($logoPath !== null) {
            self::drawLogo($image, $logoPath, 930, 110, 150, 150);
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=900');
        imagepng($image);
        exit;
    }

    private static function usableFontPath(): ?string
    {
        $candidates = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Helvetica.ttf',
            '/System/Library/Fonts/SFNS.ttf',
        ];
        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private static function drawWrappedText($image, int $size, float $angle, int $x, int $y, int $maxWidth, string $text, int $color, string $font, int $lineHeight): void
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $line = '';
        $currentY = $y;
        foreach ($words as $word) {
            $test = $line === '' ? $word : $line . ' ' . $word;
            $box = imagettfbbox($size, $angle, $font, $test);
            $width = is_array($box) ? abs((int) $box[2] - (int) $box[0]) : 0;
            if ($line !== '' && $width > $maxWidth) {
                imagettftext($image, $size, $angle, $x, $currentY, $color, $font, $line);
                $line = $word;
                $currentY += $lineHeight;
                continue;
            }
            $line = $test;
        }
        if ($line !== '') {
            imagettftext($image, $size, $angle, $x, $currentY, $color, $font, $line);
        }
    }

    private static function allocateHex($image, string $hex): int
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        return imagecolorallocate($image, $r, $g, $b);
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex) ?? '000000';
        }
        if (strlen($hex) !== 6) {
            return [0, 0, 0];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function siteColorHex(?string $hsl, string $fallbackHex): string
    {
        $hsl = trim((string) $hsl);
        if ($hsl === '') {
            return $fallbackHex;
        }
        $parts = preg_split('/\s+/', str_replace(',', ' ', $hsl)) ?: [];
        if (count($parts) < 3) {
            return $fallbackHex;
        }
        $h = (float) $parts[0];
        $s = (float) rtrim((string) $parts[1], '%') / 100;
        $l = (float) rtrim((string) $parts[2], '%') / 100;
        [$r, $g, $b] = self::hslToRgb($h, $s, $l);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private static function hslToRgb(float $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        if ($h < 60) {
            [$r, $g, $b] = [$c, $x, 0];
        } elseif ($h < 120) {
            [$r, $g, $b] = [$x, $c, 0];
        } elseif ($h < 180) {
            [$r, $g, $b] = [0, $c, $x];
        } elseif ($h < 240) {
            [$r, $g, $b] = [0, $x, $c];
        } elseif ($h < 300) {
            [$r, $g, $b] = [$x, 0, $c];
        } else {
            [$r, $g, $b] = [$c, 0, $x];
        }

        return [
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        ];
    }

    private static function localLogoPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        $candidate = dirname(__DIR__, 2) . $path;
        return is_readable($candidate) ? $candidate : null;
    }

    private static function drawLogo($canvas, string $path, int $x, int $y, int $maxWidth, int $maxHeight): void
    {
        $info = @getimagesize($path);
        if (!is_array($info)) {
            return;
        }
        $source = match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => null,
        };
        if (!$source) {
            return;
        }

        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);
        if ($srcWidth <= 0 || $srcHeight <= 0) {
            imagedestroy($source);
            return;
        }

        $scale = min($maxWidth / $srcWidth, $maxHeight / $srcHeight, 1);
        $destWidth = max(1, (int) round($srcWidth * $scale));
        $destHeight = max(1, (int) round($srcHeight * $scale));
        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);
        imagedestroy($source);
    }
}
