<?php

declare(strict_types=1);

class UserProfilesAdminController
{
    public static function index(): void
    {
        admin_check();
        $users = self::allUsers();
        $settings = UserAiVendorSettings::all();
        $keys = UserAiVendorKeys::all();
        require dirname(__DIR__, 2) . '/views/admin/user-profiles/index.php';
    }

    public static function aiSettingsIndex(): void
    {
        admin_check();
        $settings = UserAiVendorSettings::all();
        $keys = UserAiVendorKeys::all();
        $owner = PlatformUser::owner();
        $profiles = $owner ? UserAiVendorSettings::allForUser((string) $owner['id']) : [];
        $personas = self::allPersonas();
        $siteSettings = SiteSettings::current() ?: [];
        $themeDefaultProfileId = (int) ($siteSettings['ai_theme_default_profile_id'] ?? 0);
        $capabilitiesSchemaSupported = UserAiVendorSettings::supportsCapabilitiesColumn();
        $personasSchemaSupported = ah_table_exists('ai_personas');
        $tab = $_GET['tab'] ?? 'profiles';
        require dirname(__DIR__, 2) . '/views/admin/ai-settings/index.php';
    }

    public static function personaCreate(): void
    {
        admin_check();
        $persona = null;
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/ai-settings/persona-form.php';
    }

    public static function personaStore(): void
    {
        admin_check();
        try {
            $data = self::resolvePersonaData();
            $id = self::insertPersona($data);
            if (self::wantsJson()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'persona' => ['id' => $id, 'name' => $data['name']]]);
                exit;
            }
            header('Location: /admin/ai-settings?tab=personas');
        } catch (Throwable $e) {
            if (self::wantsJson()) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => $e->getMessage()]);
                exit;
            }
            $persona = null;
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/ai-settings/persona-form.php';
        }
        exit;
    }

    public static function personaEdit(string $id): void
    {
        admin_check();
        $persona = self::findPersona((int) $id);
        if (!$persona) {
            header('Location: /admin/ai-settings?tab=personas');
            exit;
        }
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/ai-settings/persona-form.php';
    }

    public static function personaUpdate(string $id): void
    {
        admin_check();
        $persona = self::findPersona((int) $id);
        if (!$persona) {
            header('Location: /admin/ai-settings?tab=personas');
            exit;
        }
        try {
            $data = self::resolvePersonaData();
            self::updatePersona((int) $id, $data);
            header('Location: /admin/ai-settings?tab=personas');
        } catch (Throwable $e) {
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/ai-settings/persona-form.php';
        }
        exit;
    }

    public static function personaDelete(string $id): void
    {
        admin_check();
        db()->prepare('DELETE FROM ai_personas WHERE id = ?')->execute([(int) $id]);
        header('Location: /admin/ai-settings?tab=personas');
        exit;
    }

    public static function userEdit(string $id): void
    {
        admin_check();
        $user = self::findUser($id);
        if (!$user) {
            header('Location: /admin/user-profiles');
            exit;
        }
        $error = null;
        $aiProfiles = UserAiVendorSettings::allForUser($id);
        require dirname(__DIR__, 2) . '/views/admin/user-profiles/user-form.php';
    }

    public static function userUpdate(string $id): void
    {
        admin_check();
        $user = self::findUser($id);
        if (!$user) {
            header('Location: /admin/user-profiles');
            exit;
        }

        try {
            $data = self::resolveUserData();
            foreach (['preferred_art_piece_profile_id', 'preferred_text_improve_profile_id', 'preferred_alt_text_profile_id'] as $field) {
                if (!array_key_exists($field, $_POST)) {
                    $data[$field] = $user[$field] ?? null;
                }
            }
            self::updateUser($id, $data);
            header('Location: /admin/user-profiles');
        } catch (Throwable $e) {
            $user = self::findUser($id);
            $error = $e->getMessage();
            $aiProfiles = UserAiVendorSettings::allForUser($id);
            require dirname(__DIR__, 2) . '/views/admin/user-profiles/user-form.php';
        }
        exit;
    }

    public static function settingsCreate(): void
    {
        admin_check();
        $users = self::allUsers();
        $setting = null;
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/user-profiles/settings-form.php';
    }

    public static function settingsStore(): void
    {
        admin_check();

        try {
            $data = self::resolveSettingsData();
            UserAiVendorSettings::create($data);
            header('Location: ' . self::aiSettingsPath('profiles'));
        } catch (Throwable $e) {
            $users = self::allUsers();
            $setting = null;
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/user-profiles/settings-form.php';
        }
        exit;
    }

    public static function settingsEdit(string $id): void
    {
        admin_check();
        $setting = UserAiVendorSettings::find((int) $id);
        if (!$setting) {
            header('Location: /admin/user-profiles');
            exit;
        }
        $users = self::allUsers();
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/user-profiles/settings-form.php';
    }

    public static function settingsUpdate(string $id): void
    {
        admin_check();
        $setting = UserAiVendorSettings::find((int) $id);
        if (!$setting) {
            header('Location: /admin/user-profiles');
            exit;
        }

        try {
            $data = self::resolveSettingsData();
            UserAiVendorSettings::update((int) $id, $data);
            header('Location: ' . self::aiSettingsPath('profiles'));
        } catch (Throwable $e) {
            $users = self::allUsers();
            $setting = UserAiVendorSettings::find((int) $id);
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/user-profiles/settings-form.php';
        }
        exit;
    }

    public static function settingsDelete(string $id): void
    {
        admin_check();
        UserAiVendorSettings::delete((int) $id);
        header('Location: ' . self::aiSettingsPath('profiles'));
        exit;
    }

    public static function keyCreate(): void
    {
        admin_check();
        $users = self::allUsers();
        $key = null;
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/user-profiles/key-form.php';
    }

    public static function keyStore(): void
    {
        admin_check();

        try {
            $data = self::resolveKeyData();
            UserAiVendorKeys::create($data);
            header('Location: ' . self::aiSettingsPath('keys'));
        } catch (Throwable $e) {
            $users = self::allUsers();
            $key = null;
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/user-profiles/key-form.php';
        }
        exit;
    }

    public static function keyEdit(string $id): void
    {
        admin_check();
        $key = UserAiVendorKeys::find((int) $id);
        if (!$key) {
            header('Location: /admin/user-profiles');
            exit;
        }
        $users = self::allUsers();
        $error = null;
        require dirname(__DIR__, 2) . '/views/admin/user-profiles/key-form.php';
    }

    public static function keyUpdate(string $id): void
    {
        admin_check();
        $key = UserAiVendorKeys::find((int) $id);
        if (!$key) {
            header('Location: /admin/user-profiles');
            exit;
        }

        try {
            $data = self::resolveKeyData();
            UserAiVendorKeys::update((int) $id, $data);
            header('Location: ' . self::aiSettingsPath('keys'));
        } catch (Throwable $e) {
            $users = self::allUsers();
            $key = UserAiVendorKeys::find((int) $id);
            $error = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/user-profiles/key-form.php';
        }
        exit;
    }

    public static function keyDelete(string $id): void
    {
        admin_check();
        UserAiVendorKeys::delete((int) $id);
        header('Location: ' . self::aiSettingsPath('keys'));
        exit;
    }

    public static function vendorUpdate(): void
    {
        admin_check();
        $owner = PlatformUser::owner();
        if (!$owner) {
            header('Location: ' . self::aiSettingsPath('vendor') . '&error=' . urlencode('Owner user not found.'));
            exit;
        }

        try {
            self::updateUser((string) $owner['id'], [
                'name' => $owner['name'] ?? 'Owner',
                'username' => $owner['username'] ?? null,
                'email' => $owner['email'] ?? null,
                'bio' => $owner['bio'] ?? null,
                'website' => $owner['website'] ?? null,
                'social_links' => $owner['social_links'] ?? null,
                'theme' => $owner['theme'] ?? null,
                'palette' => $owner['palette'] ?? null,
                'preferred_art_piece_profile_id' => trim($_POST['preferred_art_piece_profile_id'] ?? '') ?: null,
                'preferred_text_improve_profile_id' => trim($_POST['preferred_text_improve_profile_id'] ?? '') ?: null,
                'preferred_alt_text_profile_id' => trim($_POST['preferred_alt_text_profile_id'] ?? '') ?: null,
                'image' => null,
            ]);
            $themeProfileId = (int) ($_POST['ai_theme_default_profile_id'] ?? 0);
            SiteSettings::updateJsonSetting('ai_theme_default_profile_id', $themeProfileId > 0 ? $themeProfileId : null);
            header('Location: ' . self::aiSettingsPath('vendor') . '&success=vendor');
        } catch (Throwable $e) {
            header('Location: ' . self::aiSettingsPath('vendor') . '&error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    private static function allUsers(): array
    {
        try {
            return db()->query("SELECT id, name, email FROM users ORDER BY name ASC")->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private static function findUser(string $id): array|false
    {
        try {
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            return $stmt->fetch() ?: false;
        } catch (Throwable) {
            return false;
        }
    }

    private static function updateUser(string $id, array $data): void
    {
        $sets = [
            'name = ?',
            'username = ?',
            'email = ?',
            'bio = ?',
            'website = ?',
        ];
        $params = [
            $data['name'],
            $data['username'] ?? null,
            $data['email'] ?? null,
            $data['bio'] ?? null,
            $data['website'] ?? null,
        ];

        $optionalColumns = [
            'social_links',
            'theme',
            'palette',
            'preferred_art_piece_profile_id',
            'preferred_text_improve_profile_id',
            'preferred_alt_text_profile_id',
        ];
        foreach ($optionalColumns as $column) {
            if (!ah_column_exists('users', $column)) {
                continue;
            }
            $sets[] = $column . ' = ?';
            $params[] = $data[$column] ?? null;
        }

        if (array_key_exists('image', $data)) {
            $sets[] = 'image = COALESCE(?, image)';
            $params[] = $data['image'] ?? null;
        }

        $params[] = $id;
        $stmt = db()->prepare(
            'UPDATE users SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute($params);
    }

    private static function resolveUserData(): array
    {
        return [
            'name' => trim($_POST['name'] ?? '') ?: 'User',
            'username' => trim($_POST['username'] ?? '') ?: null,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'bio' => trim($_POST['bio'] ?? '') ?: null,
            'website' => trim($_POST['website'] ?? '') ?: null,
            'social_links' => trim($_POST['social_links'] ?? '') ?: null,
            'theme' => trim($_POST['theme'] ?? '') ?: null,
            'palette' => trim($_POST['palette'] ?? '') ?: null,
            'preferred_art_piece_profile_id' => trim($_POST['preferred_art_piece_profile_id'] ?? '') ?: null,
            'preferred_text_improve_profile_id' => trim($_POST['preferred_text_improve_profile_id'] ?? '') ?: null,
            'preferred_alt_text_profile_id' => trim($_POST['preferred_alt_text_profile_id'] ?? '') ?: null,
        ];
    }

    public static function userPhotoUpload(string $id): void
    {
        admin_check();
        $user = self::findUser($id);
        if (!$user) {
            header('Location: /admin/user-profiles');
            exit;
        }

        try {
            $file = $_FILES['profile_photo'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new InvalidArgumentException('No photo file was uploaded.');
            }

            $role = $user['role'] ?? 'member';
            if ($role === 'owner') {
                // Owner: use standard media upload
                $result = upload_media_auto($file);
                $imageUrl = $result['url'] ?? null;
            } else {
                // Member: store in profile_photo_assets
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
            }

            if ($imageUrl) {
                $stmt = db()->prepare('UPDATE users SET image = ? WHERE id = ?');
                $stmt->execute([$imageUrl, $id]);
            }

            header('Location: /admin/user-profiles/' . $id . '/edit?success=photo');
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $aiProfiles = UserAiVendorSettings::allForUser($id);
            require dirname(__DIR__, 2) . '/views/admin/user-profiles/user-form.php';
        }
        exit;
    }

    private static function resolveSettingsData(): array
    {
        $userId = trim($_POST['user_id'] ?? '');
        if ($userId === '') {
            throw new InvalidArgumentException('User is required.');
        }

        $vendor = trim($_POST['vendor'] ?? '');
        if ($vendor === '') {
            throw new InvalidArgumentException('Vendor is required.');
        }

        $capParts = [];
        if (!empty($_POST['cap_text']))   $capParts[] = 'text';
        if (!empty($_POST['cap_code']))   $capParts[] = 'code';
        if (!empty($_POST['cap_vision'])) $capParts[] = 'vision';
        $capabilities = $capParts !== [] ? implode(',', $capParts) : 'text';

        return [
            'user_id'       => $userId,
            'vendor'        => $vendor,
            'profile_name'  => trim($_POST['profile_name'] ?? '') ?: 'Default',
            'endpoint_kind' => trim($_POST['endpoint_kind'] ?? '') ?: null,
            'enabled'       => isset($_POST['enabled']) ? 1 : 0,
            'capabilities'  => $capabilities,
            'model'         => trim($_POST['model'] ?? '') ?: null,
        ];
    }

    private static function resolveKeyData(): array
    {
        $userId = trim($_POST['user_id'] ?? '');
        if ($userId === '') {
            throw new InvalidArgumentException('User is required.');
        }

        $vendor = trim($_POST['vendor'] ?? '');
        if ($vendor === '') {
            throw new InvalidArgumentException('Vendor is required.');
        }

        $apiKey = trim($_POST['api_key'] ?? '');
        if ($apiKey === '') {
            throw new InvalidArgumentException('API key is required.');
        }

        $encryptedKey = encrypt_string($apiKey, ai_encryption_key());

        return [
            'user_id' => $userId,
            'vendor' => $vendor,
            'encrypted_api_key' => $encryptedKey,
        ];
    }

    private static function aiSettingsPath(string $tab): string
    {
        return '/admin/ai-settings?tab=' . rawurlencode($tab);
    }

    private static function allPersonas(): array
    {
        try {
            return db()->query('SELECT * FROM ai_personas ORDER BY name ASC')->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private static function findPersona(int $id): array|false
    {
        try {
            $stmt = db()->prepare('SELECT * FROM ai_personas WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            return $stmt->fetch() ?: false;
        } catch (Throwable) {
            return false;
        }
    }

    private static function insertPersona(array $data): int
    {
        $owner = PlatformUser::owner();
        $userId = $owner ? (int) $owner['id'] : 0;
        $stmt = db()->prepare(
            'INSERT INTO ai_personas (user_id, name, system_prompt) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $data['name'], $data['system_prompt']]);
        return (int) db()->lastInsertId();
    }

    private static function updatePersona(int $id, array $data): void
    {
        db()->prepare('UPDATE ai_personas SET name = ?, system_prompt = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$data['name'], $data['system_prompt'], $id]);
    }

    private static function resolvePersonaData(): array
    {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 128);
        if ($name === '') {
            throw new InvalidArgumentException('Persona name is required.');
        }
        $systemPrompt = mb_substr(trim((string) ($_POST['system_prompt'] ?? '')), 0, 4000);
        if ($systemPrompt === '') {
            throw new InvalidArgumentException('System prompt is required.');
        }
        return ['name' => $name, 'system_prompt' => $systemPrompt];
    }

    private static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_POST['_format'] ?? '') === 'json';
    }
}
