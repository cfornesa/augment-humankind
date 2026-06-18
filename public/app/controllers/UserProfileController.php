<?php

declare(strict_types=1);

class UserProfileController
{
    public static function show(string $username): void
    {
        $username = trim($username);
        if ($username === '') {
            http_response_code(404);
            require dirname(__DIR__) . '/views/404.php';
            exit;
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $profileUser = $stmt->fetch();

        if (!$profileUser && ctype_digit($username)) {
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$username]);
            $profileUser = $stmt->fetch();
        }

        if (!$profileUser) {
            http_response_code(404);
            require dirname(__DIR__) . '/views/404.php';
            exit;
        }

        $userId = (string) $profileUser['id'];

        $posts = [];
        try {
            $stmt2 = db()->prepare(
                "SELECT id, title, status, featured_image_url, created_at
                 FROM posts
                 WHERE author_user_id = ? AND status = 'published' AND deleted_at IS NULL
                 ORDER BY created_at DESC
                 LIMIT 20"
            );
            $stmt2->execute([$userId]);
            $posts = $stmt2->fetchAll();
        } catch (Throwable) {}

        $pieces = [];
        $piecesHasMore = false;
        try {
            $showAllPieces = isset($_GET['show_pieces']) && $_GET['show_pieces'] === 'all';
            $piecesLimit = $showAllPieces ? 200 : 13;
            $stmt3 = db()->prepare(
                'SELECT id, title, engine, thumbnail_url
                 FROM art_pieces
                 WHERE owner_user_id = ? AND deleted_at IS NULL
                 ORDER BY created_at DESC
                 LIMIT ' . $piecesLimit
            );
            $stmt3->execute([$userId]);
            $pieces = $stmt3->fetchAll();
            if (!$showAllPieces && count($pieces) === 13) {
                $piecesHasMore = true;
                $pieces = array_slice($pieces, 0, 12);
            }
        } catch (Throwable) {}

        $comments = [];
        try {
            $stmt4 = db()->prepare(
                'SELECT c.id, c.content, c.created_at, c.item_type, c.item_id
                 FROM comments c
                 WHERE c.author_id = ? AND c.deleted_at IS NULL
                 ORDER BY c.created_at DESC
                 LIMIT 20'
            );
            $stmt4->execute([$userId]);
            $comments = $stmt4->fetchAll();
        } catch (Throwable) {}

        $pageTitle = ($profileUser['name'] ?? $username) . ' — Augment Humankind';
        $pageDescription = $profileUser['bio'] ?? ('Profile page for ' . $username . ' on Augment Humankind.');
        $bodyClass = 'page-user-profile';

        $isOwnProfile = false;
        if (user_logged_in()) {
            $me = current_user();
            $isOwnProfile = $me !== null && (string) $me['id'] === (string) $profileUser['id'];
        }

        require dirname(__DIR__) . '/views/user/profile.php';
    }

    public static function settings(): void
    {
        if (!user_logged_in()) {
            header('Location: /user/login?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/user/settings'));
            exit;
        }
        $user = current_user();
        if (!$user) {
            user_logout();
            header('Location: /user/login');
            exit;
        }
        $error = null;
        $success = null;
        $pageTitle = 'Profile Settings — Augment Humankind';
        $bodyClass = 'page-user-settings';
        require dirname(__DIR__) . '/views/user/settings.php';
    }

    public static function settingsProfileUpdate(): void
    {
        if (!user_logged_in()) {
            http_response_code(403);
            exit;
        }
        $user = current_user();
        if (!$user) {
            header('Location: /user/login');
            exit;
        }

        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 255);
        $bio = mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 500);
        $website = mb_substr(trim((string) ($_POST['website'] ?? '')), 0, 2048);

        $stmt = db()->prepare(
            'UPDATE users SET name = ?, bio = ?, website = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$name ?: null, $bio ?: null, $website ?: null, $user['id']]);

        // Refresh session display name
        $_SESSION['user_display_name'] = $name;

        header('Location: /user/settings?success=profile');
        exit;
    }

    public static function settingsPhotoUpload(): void
    {
        if (!user_logged_in()) {
            http_response_code(403);
            exit;
        }
        $user = current_user();
        if (!$user) {
            header('Location: /user/login');
            exit;
        }

        try {
            $file = $_FILES['profile_photo'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new InvalidArgumentException('No photo file was uploaded.');
            }
            $mime = upload_resolve_mime($file, 'Photo');
            if (!isset(ALLOWED_IMAGE_MIME[$mime])) {
                throw new RuntimeException('Photo type not permitted. Only JPEG, PNG, GIF, WebP, and AVIF are allowed.');
            }
            $blob = file_get_contents((string) $file['tmp_name']);
            if ($blob === false) {
                throw new RuntimeException('Could not read uploaded photo.');
            }
            $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', basename((string) ($file['name'] ?? 'photo.jpg')));
            $stmt = db()->prepare(
                'INSERT INTO profile_photo_assets (filename, mime_type, file_data, created_at)
                 VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute([$filename, $mime, $blob]);
            $imageUrl = '/api/profile-photos/' . $filename;

            $stmt = db()->prepare('UPDATE users SET image = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$imageUrl, $user['id']]);

            header('Location: /user/settings?success=photo');
        } catch (Throwable $e) {
            header('Location: /user/settings?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function settingsStyleUpdate(): void
    {
        if (!user_logged_in()) {
            http_response_code(403);
            exit;
        }
        $user = current_user();
        if (!$user) {
            header('Location: /user/login');
            exit;
        }

        $colorColumns = [
            'color_background', 'color_foreground',
            'color_background_dark', 'color_foreground_dark',
            'color_primary', 'color_primary_foreground',
            'color_secondary', 'color_secondary_foreground',
            'color_accent', 'color_accent_foreground',
            'color_muted', 'color_muted_foreground',
            'color_destructive', 'color_destructive_foreground',
        ];

        $sets = ['theme = ?', 'palette = ?'];
        $params = [
            mb_substr(trim((string) ($_POST['theme'] ?? '')), 0, 32) ?: null,
            mb_substr(trim((string) ($_POST['palette'] ?? '')), 0, 32) ?: null,
        ];

        foreach ($colorColumns as $col) {
            $val = mb_substr(trim((string) ($_POST[$col] ?? '')), 0, 64);
            // Accept "H S% L%" or "H S L" format, reject anything unsafe
            if ($val !== '' && !preg_match('/^[\d.]+\s+[\d.]+%?\s+[\d.]+%?$/', $val)) {
                $val = '';
            }
            $sets[] = "$col = ?";
            $params[] = $val ?: null;
        }

        $params[] = $user['id'];
        $stmt = db()->prepare(
            'UPDATE users SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute($params);

        header('Location: /user/settings?success=style');
        exit;
    }
}
