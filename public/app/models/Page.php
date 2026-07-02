<?php

declare(strict_types=1);

class Page
{
    private static ?bool $systemKeyColumnExists = null;
    private static ?bool $descriptionColumnsExist = null;
    private static ?bool $slugRedirectTableExists = null;

    private const RESERVED_SLUGS = [
        'admin', 'api', 'atom', 'blog', 'categories', 'contact', 'embed',
        'export', 'feed.json', 'feed.xml', 'feeds', 'home', 'image', 'jsonfeed',
        'media', 'p', 'portfolio', 'posts', 'search', 'settings', 'sign-in',
        'sign-up', 'users',
    ];

    private const SYSTEM_PAGES = [
        'home' => [
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
            'template' => 'standard',
            'nav_label' => 'Home',
            'show_in_nav' => true,
            'sort_order' => 0,
        ],
        'about' => [
            'title' => 'About',
            'slug' => 'about',
            'aliases' => ['bio'],
            'status' => 'published',
            'template' => 'standard',
            'nav_label' => null,
            'show_in_nav' => false,
            'sort_order' => 0,
            'show_description_section' => 1,
        ],
    ];

    /** Backward-compatible slug fallback for databases not yet migrated to
     * pages.system_key. */
    public const PROTECTED_SLUGS = ['home', 'about'];

    public static function isProtectedSlug(string $slug): bool
    {
        return in_array($slug, self::PROTECTED_SLUGS, true);
    }

    public static function isSystemPage(array $page): bool
    {
        $systemKey = (string) ($page['system_key'] ?? '');
        if ($systemKey !== '') {
            return isset(self::SYSTEM_PAGES[$systemKey]);
        }

        return self::isProtectedSlug((string) ($page['slug'] ?? ''));
    }

    public static function systemKeyForPage(array $page): ?string
    {
        $systemKey = (string) ($page['system_key'] ?? '');
        if ($systemKey !== '') {
            return isset(self::SYSTEM_PAGES[$systemKey]) ? $systemKey : null;
        }

        $slug = (string) ($page['slug'] ?? '');
        return self::isProtectedSlug($slug) ? $slug : null;
    }

    /** Idempotent, self-healing: ensures required system pages exist and have
     * stable identity when the database supports pages.system_key. */
    public static function ensureSystemPages(): void
    {
        self::ensureSystemStorageReady();
        self::backfillSystemKeys();
        self::quarantineSystemSlugDuplicates();

        foreach (self::SYSTEM_PAGES as $systemKey => $defaults) {
            if (self::findBySystemKey($systemKey) !== false) {
                continue;
            }

            if (self::findBySlug((string) $defaults['slug']) !== false) {
                continue;
            }

            self::create($defaults + [
                'system_key' => $systemKey,
                'meta_title' => null,
                'meta_description' => null,
                'og_title' => null,
                'og_description' => null,
                'og_image' => null,
            ]);
        }
    }

    private static function normalizeSlug(string $slug): string
    {
        if (function_exists('slugify')) {
            return slugify($slug);
        }

        $slug = mb_strtolower($slug, 'UTF-8');
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug) ?? '';
        $slug = preg_replace('/[\s_]+/', '-', $slug) ?? '';
        $slug = preg_replace('/-+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    public static function all(): array
    {
        return db()->query(
            'SELECT * FROM pages WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    public static function navItems(): array
    {
        return db()->query(
            'SELECT id, title, slug, nav_label, status, show_in_nav, sort_order
             FROM pages
             WHERE deleted_at IS NULL AND status = "published" AND show_in_nav = 1
             ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    public static function find(int $id): array|false
    {
        $stmt = db()->prepare('SELECT * FROM pages WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findBySlug(string $slug): array|false
    {
        $stmt = db()->prepare('SELECT * FROM pages WHERE slug = ? AND deleted_at IS NULL');
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    public static function findBySystemKey(string $systemKey): array|false
    {
        if (!self::hasSystemKeyColumn()) {
            $defaults = self::SYSTEM_PAGES[$systemKey] ?? null;
            return $defaults ? self::findBySlug((string) $defaults['slug']) : false;
        }

        $stmt = db()->prepare('SELECT * FROM pages WHERE system_key = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$systemKey]);
        return $stmt->fetch();
    }

    public static function findPublishedBySlug(string $slug): array|false
    {
        $stmt = db()->prepare('SELECT * FROM pages WHERE slug = ? AND status = ? AND deleted_at IS NULL');
        $stmt->execute([$slug, 'published']);
        return $stmt->fetch();
    }

    public static function safeFindPublishedBySlug(string $slug): array|false
    {
        try {
            return self::findPublishedBySlug($slug);
        } catch (Throwable) {
            return false;
        }
    }

    public static function safeFindBySlug(string $slug): array|false
    {
        try {
            return self::findBySlug($slug);
        } catch (Throwable) {
            return false;
        }
    }

    public static function searchPublished(string $q, int $limit = 5): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        try {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $stmt = db()->prepare(
                'SELECT id, title, slug, meta_description
                 FROM pages
                 WHERE status = ? AND deleted_at IS NULL
                   AND (title LIKE ? OR meta_description LIKE ?)
                 ORDER BY CASE WHEN title LIKE ? THEN 0 ELSE 1 END, id DESC
                 LIMIT ?'
            );
            $stmt->bindValue(1, 'published');
            $stmt->bindValue(2, $like);
            $stmt->bindValue(3, $like);
            $stmt->bindValue(4, $like);
            $stmt->bindValue(5, max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public static function create(array $data): int
    {
        $columns = [
            'title', 'slug', 'status', 'template', 'nav_label', 'show_in_nav',
            'meta_title', 'meta_description', 'og_title', 'og_description',
            'og_image', 'sort_order',
        ];
        $values = [
            $data['title'],
            $data['slug'],
            $data['status'],
            $data['template'],
            $data['nav_label'] ?: null,
            !empty($data['show_in_nav']) ? 1 : 0,
            $data['meta_title'] ?: null,
            $data['meta_description'] ?: null,
            $data['og_title'] ?: null,
            $data['og_description'] ?: null,
            $data['og_image'] ?: null,
            $data['sort_order'] ?? 0,
        ];

        if (self::hasSystemKeyColumn()) {
            array_unshift($columns, 'system_key');
            array_unshift($values, $data['system_key'] ?? null);
        }

        if (self::hasDescriptionColumns()) {
            $columns[] = 'description';
            $values[] = ($data['description'] ?? '') !== '' ? $data['description'] : null;
            $columns[] = 'show_description_section';
            $values[] = !empty($data['show_description_section']) ? 1 : 0;
        }

        $stmt = db()->prepare(
            'INSERT INTO pages (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'
        );
        $stmt->execute($values);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $existing = self::find($id);

        $assignments = [
            'title = ?', 'slug = ?', 'status = ?', 'template = ?', 'nav_label = ?',
            'show_in_nav = ?', 'meta_title = ?', 'meta_description = ?',
            'og_title = ?', 'og_description = ?', 'og_image = ?', 'sort_order = ?',
        ];
        $values = [
            $data['title'],
            $data['slug'],
            $data['status'],
            $data['template'],
            $data['nav_label'] ?: null,
            !empty($data['show_in_nav']) ? 1 : 0,
            $data['meta_title'] ?: null,
            $data['meta_description'] ?: null,
            $data['og_title'] ?: null,
            $data['og_description'] ?: null,
            $data['og_image'] ?: null,
            $data['sort_order'] ?? 0,
        ];

        if (self::hasSystemKeyColumn()) {
            $assignments[] = 'system_key = ?';
            $values[] = $data['system_key'] ?? ($existing['system_key'] ?? null) ?? ($existing ? self::systemKeyForPage($existing) : null);
        }

        if (self::hasDescriptionColumns()) {
            $assignments[] = 'description = ?';
            $values[] = ($data['description'] ?? '') !== '' ? $data['description'] : null;
            $assignments[] = 'show_description_section = ?';
            $values[] = !empty($data['show_description_section']) ? 1 : 0;
        }

        $values[] = $id;
        $stmt = db()->prepare(
            'UPDATE pages SET ' . implode(', ', $assignments) . ' WHERE id = ?'
        );
        $stmt->execute($values);

        if ($existing && self::isSystemPage($existing) && (string) $existing['slug'] !== (string) $data['slug']) {
            self::recordSlugRedirect((string) $existing['slug'], $id, self::systemKeyForPage($existing));
        }
    }

    public static function softDelete(int $id): void
    {
        self::guardAgainstDeletingProtected($id);
        $stmt = db()->prepare('UPDATE pages SET deleted_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function hardDelete(int $id): void
    {
        self::guardAgainstDeletingProtected($id);
        $stmt = db()->prepare('DELETE FROM pages WHERE id = ?');
        $stmt->execute([$id]);
    }

    private static function guardAgainstDeletingProtected(int $id): void
    {
        $page = self::find($id);
        if ($page && self::isSystemPage($page)) {
            throw new InvalidArgumentException('The Home and About pages cannot be deleted.');
        }
    }

    public static function restore(int $id): void
    {
        $stmt = db()->prepare('UPDATE pages SET deleted_at = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function trashed(): array
    {
        return db()->query(
            'SELECT * FROM pages WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        )->fetchAll();
    }

    public static function trashedCount(): int
    {
        return (int) db()->query(
            'SELECT COUNT(*) FROM pages WHERE deleted_at IS NOT NULL'
        )->fetchColumn();
    }

    public static function reorder(array $ids): void
    {
        $stmt = db()->prepare('UPDATE pages SET sort_order = ? WHERE id = ? AND deleted_at IS NULL');
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index, $id]);
        }
    }

    public static function validateSlug(string $slug, int $excludeId = 0): string
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '') {
            throw new InvalidArgumentException('Slug is required.');
        }
        if (in_array($slug, self::RESERVED_SLUGS, true) && !self::isExistingSystemPage($slug, $excludeId)) {
            throw new InvalidArgumentException('That slug is reserved by the site.');
        }

        $stmt = db()->prepare('SELECT id FROM pages WHERE slug = ? AND id != ? AND deleted_at IS NULL');
        $stmt->execute([$slug, $excludeId]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('That slug is already in use.');
        }

        return $slug;
    }

    private static function isExistingSystemPage(string $slug, int $excludeId): bool
    {
        if ($excludeId > 0 && self::isProtectedSlug($slug)) {
            $page = self::find($excludeId);
            if ($page && self::isSystemPage($page)) {
                return true;
            }
        }

        $stmt = db()->prepare('SELECT id FROM pages WHERE slug = ?');
        $stmt->execute([$slug]);
        $existingId = $stmt->fetchColumn();
        return $existingId !== false && (int) $existingId === $excludeId;
    }

    public static function redirectForSlug(string $slug): array|false
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '') {
            return false;
        }

        if (self::hasSlugRedirectTable()) {
            try {
                $stmt = db()->prepare(
                    'SELECT r.old_slug, p.slug AS target_slug
                       FROM page_slug_redirects r
                       JOIN pages p ON p.id = r.page_id
                      WHERE r.old_slug = ?
                        AND p.deleted_at IS NULL
                        AND p.slug != r.old_slug
                      LIMIT 1'
                );
                $stmt->execute([$slug]);
                $redirect = $stmt->fetch();
                if ($redirect) {
                    return $redirect;
                }
            } catch (Throwable) {
                // Fall through to default system slug lookup.
            }
        }

        if (isset(self::SYSTEM_PAGES[$slug])) {
            $page = self::findBySystemKey($slug);
            if ($page && (string) $page['slug'] !== $slug) {
                return ['old_slug' => $slug, 'target_slug' => (string) $page['slug']];
            }
        }

        return false;
    }

    private static function backfillSystemKeys(): void
    {
        if (!self::hasSystemKeyColumn()) {
            return;
        }

        foreach (self::SYSTEM_PAGES as $systemKey => $defaults) {
            $existingSystemPage = self::findBySystemKey($systemKey);
            $bestCandidate = self::findSystemBackfillCandidate($systemKey, $defaults);

            if (
                $existingSystemPage
                && $bestCandidate
                && (int) $existingSystemPage['id'] !== (int) $bestCandidate['id']
                && (string) $existingSystemPage['slug'] === (string) $defaults['slug']
                && (string) $bestCandidate['slug'] !== (string) $defaults['slug']
            ) {
                self::transferSystemKey((int) $existingSystemPage['id'], (int) $bestCandidate['id'], $systemKey);
                self::recordSlugRedirect((string) $defaults['slug'], (int) $bestCandidate['id'], $systemKey);
                continue;
            }

            if ($existingSystemPage !== false) {
                continue;
            }

            if ($bestCandidate) {
                self::assignSystemKey((int) $bestCandidate['id'], $systemKey);
                if ((string) $bestCandidate['slug'] !== (string) $defaults['slug']) {
                    self::recordSlugRedirect((string) $defaults['slug'], (int) $bestCandidate['id'], $systemKey);
                }
            }
        }
    }

    private static function quarantineSystemSlugDuplicates(): void
    {
        if (!self::hasSystemKeyColumn()) {
            return;
        }

        foreach (self::SYSTEM_PAGES as $systemKey => $defaults) {
            $systemPage = self::findBySystemKey($systemKey);
            if (!$systemPage || (string) $systemPage['slug'] === (string) $defaults['slug']) {
                continue;
            }

            try {
                $stmt = db()->prepare(
                    'UPDATE pages
                        SET status = ?, show_in_nav = 0
                      WHERE slug = ?
                        AND deleted_at IS NULL
                        AND system_key IS NULL'
                );
                $stmt->execute(['draft', (string) $defaults['slug']]);
            } catch (Throwable) {
                continue;
            }
        }
    }

    private static function findSystemBackfillCandidate(string $systemKey, array $defaults): array|false
    {
        $defaultSlug = (string) $defaults['slug'];
        $aliases = array_values(array_unique(array_merge([$defaultSlug], $defaults['aliases'] ?? [])));
        $placeholders = implode(',', array_fill(0, count($aliases), '?'));

        try {
            $stmt = db()->prepare(
                "SELECT *
                   FROM pages
                  WHERE deleted_at IS NULL
                    AND system_key IS NULL
                    AND slug IN ($placeholders)
                  ORDER BY CASE WHEN slug != ? THEN 0 ELSE 1 END, id ASC
                  LIMIT 1"
            );
            $stmt->execute([...$aliases, $defaultSlug]);
            $candidate = $stmt->fetch();
            if ($candidate) {
                return $candidate;
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    private static function assignSystemKey(int $pageId, string $systemKey): void
    {
        try {
            $stmt = db()->prepare('UPDATE pages SET system_key = ? WHERE id = ? AND system_key IS NULL');
            $stmt->execute([$systemKey, $pageId]);
        } catch (Throwable) {
            return;
        }
    }

    private static function transferSystemKey(int $fromPageId, int $toPageId, string $systemKey): void
    {
        try {
            db()->beginTransaction();
            $clear = db()->prepare('UPDATE pages SET system_key = NULL WHERE id = ? AND system_key = ?');
            $clear->execute([$fromPageId, $systemKey]);

            $assign = db()->prepare('UPDATE pages SET system_key = ? WHERE id = ? AND system_key IS NULL');
            $assign->execute([$systemKey, $toPageId]);
            db()->commit();
        } catch (Throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
        }
    }

    private static function ensureSystemStorageReady(): void
    {
        if (!self::hasSystemKeyColumn()) {
            try {
                db()->exec('ALTER TABLE pages ADD COLUMN system_key VARCHAR(100) NULL AFTER id');
                self::$systemKeyColumnExists = true;
            } catch (Throwable) {
                self::$systemKeyColumnExists = self::databaseColumnExists('pages', 'system_key');
            }
        }

        if (self::hasSystemKeyColumn()) {
            try {
                db()->exec('ALTER TABLE pages ADD UNIQUE KEY uniq_pages_system_key (system_key)');
            } catch (Throwable) {
                // Duplicate-key and unsupported-schema states are non-fatal.
            }
        }

        if (!self::hasSlugRedirectTable()) {
            try {
                db()->exec(
                    "CREATE TABLE IF NOT EXISTS page_slug_redirects (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        old_slug VARCHAR(255) NOT NULL,
                        page_id INT NOT NULL,
                        system_key VARCHAR(100) NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uniq_page_slug_redirect_old_slug (old_slug),
                        KEY idx_page_slug_redirect_page (page_id),
                        FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
                    )"
                );
                self::$slugRedirectTableExists = true;
            } catch (Throwable) {
                self::$slugRedirectTableExists = self::databaseTableExists('page_slug_redirects');
            }
        }
    }

    private static function recordSlugRedirect(string $oldSlug, int $pageId, ?string $systemKey): void
    {
        if (!self::hasSlugRedirectTable()) {
            return;
        }

        $oldSlug = self::normalizeSlug($oldSlug);
        if ($oldSlug === '') {
            return;
        }

        try {
            $stmt = db()->prepare(
                'INSERT INTO page_slug_redirects (old_slug, page_id, system_key)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE page_id = VALUES(page_id), system_key = VALUES(system_key)'
            );
            $stmt->execute([$oldSlug, $pageId, $systemKey]);
        } catch (Throwable) {
            return;
        }
    }

    private static function hasDescriptionColumns(): bool
    {
        if (self::$descriptionColumnsExist !== null) {
            return self::$descriptionColumnsExist;
        }

        return self::$descriptionColumnsExist = self::databaseColumnExists('pages', 'show_description_section');
    }

    private static function hasSystemKeyColumn(): bool
    {
        if (self::$systemKeyColumnExists !== null) {
            return self::$systemKeyColumnExists;
        }

        if (function_exists('ah_column_exists')) {
            return self::$systemKeyColumnExists = ah_column_exists('pages', 'system_key');
        }

        return self::$systemKeyColumnExists = self::databaseColumnExists('pages', 'system_key');
    }

    private static function hasSlugRedirectTable(): bool
    {
        if (self::$slugRedirectTableExists !== null) {
            return self::$slugRedirectTableExists;
        }

        if (function_exists('ah_table_exists')) {
            return self::$slugRedirectTableExists = ah_table_exists('page_slug_redirects');
        }

        return self::$slugRedirectTableExists = self::databaseTableExists('page_slug_redirects');
    }

    private static function databaseColumnExists(string $tableName, string $columnName): bool
    {
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                  LIMIT 1'
            );
            $stmt->execute([$tableName, $columnName]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private static function databaseTableExists(string $tableName): bool
    {
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                  LIMIT 1'
            );
            $stmt->execute([$tableName]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
