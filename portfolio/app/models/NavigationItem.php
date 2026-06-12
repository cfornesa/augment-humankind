<?php

declare(strict_types=1);

class NavigationItem
{
    private static ?bool $tableExistsCache = null;
    private static bool $isInitializing = false;
    private static bool $isInitialized = false;

    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_PAGE = 'page';
    public const SOURCE_EXTERNAL = 'external';

    private const SYSTEM_ITEMS = [
        'gallery' => [
            'label' => 'Gallery',
            'url' => '/',
            'is_visible' => 1,
            'sort_order' => 0,
        ],
        'categories' => [
            'label' => 'Categories',
            'url' => '/categories',
            'is_visible' => 1,
            'sort_order' => 1,
        ],
    ];

    public static function publicItems(): array
    {
        if (!self::ensureStorageReady()) {
            return self::legacyPublicItems();
        }

        self::ensureInitialized();

        $stmt = db()->query(
            "SELECT
                n.*,
                p.slug AS page_slug,
                p.title AS page_title,
                p.nav_label AS page_nav_label,
                p.status AS page_status,
                p.deleted_at AS page_deleted_at
             FROM navigation_items n
             LEFT JOIN pages p ON p.id = n.page_id
             WHERE n.is_visible = 1
             ORDER BY n.sort_order ASC, n.id ASC"
        );

        return self::hydratePublicItems($stmt->fetchAll());
    }

    public static function adminItems(bool $isVisible): array
    {
        if (!self::ensureStorageReady()) {
            return self::legacyAdminItems($isVisible);
        }

        self::ensureInitialized();

        $stmt = db()->prepare(
            "SELECT
                n.*,
                p.slug AS page_slug,
                p.title AS page_title,
                p.nav_label AS page_nav_label,
                p.status AS page_status,
                p.deleted_at AS page_deleted_at
             FROM navigation_items n
             LEFT JOIN pages p ON p.id = n.page_id
             WHERE n.is_visible = ?
               AND (n.source_type != 'page' OR p.id IS NOT NULL)
               AND (n.source_type != 'page' OR p.deleted_at IS NULL)
             ORDER BY n.sort_order ASC, n.id ASC"
        );
        $stmt->execute([$isVisible ? 1 : 0]);
        return self::hydrateAdminItems($stmt->fetchAll());
    }

    public static function legacyModeItems(bool $isVisible): array
    {
        return self::legacyAdminItems($isVisible);
    }

    public static function visibilityByPageId(int $pageId): ?bool
    {
        if (!self::ensureStorageReady()) {
            return null;
        }

        self::ensureInitialized();
        $stmt = db()->prepare(
            'SELECT is_visible FROM navigation_items WHERE source_type = ? AND page_id = ? LIMIT 1'
        );
        $stmt->execute([self::SOURCE_PAGE, $pageId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (bool) $value;
    }

    public static function createExternal(string $label, string $url, bool $isVisible, bool $openInNewTab): void
    {
        if (!self::ensureStorageReady()) {
            throw new RuntimeException('Navigation management is unavailable until the navigation_items table is created.');
        }

        self::ensureInitialized();

        $stmt = db()->prepare(
            'INSERT INTO navigation_items
                (source_type, label, url, target, is_visible, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            self::SOURCE_EXTERNAL,
            $label,
            $url,
            $openInNewTab ? '_blank' : null,
            $isVisible ? 1 : 0,
            self::nextSortOrder($isVisible),
        ]);
    }

    public static function toggleVisibility(int $id): void
    {
        if (!self::ensureStorageReady()) {
            throw new RuntimeException('Navigation management is unavailable until the navigation_items table is created.');
        }

        self::ensureInitialized();

        $item = self::find($id);
        if (!$item) {
            throw new InvalidArgumentException('Navigation item not found.');
        }

        $newVisibility = (int) !$item['is_visible'];
        $stmt = db()->prepare(
            'UPDATE navigation_items
             SET is_visible = ?, sort_order = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$newVisibility, self::nextSortOrder((bool) $newVisibility), $id]);
    }

    public static function deleteExternal(int $id): void
    {
        if (!self::ensureStorageReady()) {
            throw new RuntimeException('Navigation management is unavailable until the navigation_items table is created.');
        }

        self::ensureInitialized();

        $item = self::find($id);
        if (!$item) {
            throw new InvalidArgumentException('Navigation item not found.');
        }
        if (($item['source_type'] ?? '') !== self::SOURCE_EXTERNAL) {
            throw new InvalidArgumentException('Only external links can be deleted.');
        }

        $stmt = db()->prepare('DELETE FROM navigation_items WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function toggleExternalTarget(int $id): void
    {
        if (!self::ensureStorageReady()) {
            throw new RuntimeException('Navigation management is unavailable until the navigation_items table is created.');
        }

        self::ensureInitialized();

        $item = self::find($id);
        if (!$item) {
            throw new InvalidArgumentException('Navigation item not found.');
        }
        if (($item['source_type'] ?? '') !== self::SOURCE_EXTERNAL) {
            throw new InvalidArgumentException('Only external links can change tab behavior.');
        }

        $newTarget = (($item['target'] ?? null) === '_blank') ? null : '_blank';
        $stmt = db()->prepare(
            'UPDATE navigation_items
             SET target = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$newTarget, $id]);
    }

    public static function updateExternalLabel(int $id, string $label): void
    {
        if (!self::ensureStorageReady()) {
            throw new RuntimeException('Navigation management is unavailable until the navigation_items table is created.');
        }

        self::ensureInitialized();

        $item = self::find($id);
        if (!$item) {
            throw new InvalidArgumentException('Navigation item not found.');
        }
        if (($item['source_type'] ?? '') !== self::SOURCE_EXTERNAL) {
            throw new InvalidArgumentException('Only external links can change labels here.');
        }

        $cleanLabel = trim($label);
        if ($cleanLabel === '') {
            throw new InvalidArgumentException('External link labels cannot be empty.');
        }

        $stmt = db()->prepare(
            'UPDATE navigation_items
             SET label = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$cleanLabel, $id]);
    }

    public static function reorder(bool $isVisible, array $ids): void
    {
        if (!self::ensureStorageReady()) {
            throw new RuntimeException('Navigation management is unavailable until the navigation_items table is created.');
        }

        self::ensureInitialized();

        $stmt = db()->prepare(
            'UPDATE navigation_items SET sort_order = ?, updated_at = NOW() WHERE id = ? AND is_visible = ?'
        );
        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index, $id, $isVisible ? 1 : 0]);
        }
    }

    public static function syncPageItem(array $pageData, bool $appendToVisible = false): void
    {
        if (!self::ensureStorageReady()) {
            return;
        }

        if (!self::$isInitializing) {
            self::ensureInitialized();
        }

        $pageId = (int) ($pageData['id'] ?? 0);
        if ($pageId <= 0) {
            return;
        }

        $existing = self::findByPageId($pageId);
        $isVisible = !empty($pageData['show_in_nav']) ? 1 : 0;
        $label = trim((string) ($pageData['nav_label'] ?? ''));
        $url = '/' . ltrim((string) ($pageData['slug'] ?? ''), '/');

        if ($existing) {
            $sortOrder = (int) $existing['sort_order'];
            if ((int) $existing['is_visible'] !== $isVisible && $isVisible === 1) {
                $sortOrder = self::nextSortOrder(true);
            } elseif ((int) $existing['is_visible'] !== $isVisible && $isVisible === 0) {
                $sortOrder = self::nextSortOrder(false);
            }

            $stmt = db()->prepare(
                'UPDATE navigation_items
                 SET label = ?, url = ?, is_visible = ?, sort_order = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([$label ?: null, $url, $isVisible, $sortOrder, (int) $existing['id']]);
            return;
        }

        $sortOrder = $isVisible && $appendToVisible
            ? self::nextSortOrder(true)
            : self::nextSortOrder((bool) $isVisible);

        $stmt = db()->prepare(
            'INSERT INTO navigation_items
                (source_type, page_id, label, url, is_visible, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            self::SOURCE_PAGE,
            $pageId,
            $label ?: null,
            $url,
            $isVisible,
            $sortOrder,
        ]);
    }

    public static function ensureInitialized(): void
    {
        if (!self::tableExists()) {
            return;
        }

        if (self::$isInitialized || self::$isInitializing) {
            return;
        }

        self::$isInitializing = true;
        try {
            self::seedSystemItems();
            self::removeDefunctSystemItems();
            self::syncAllPages();
            self::$isInitialized = true;
        } finally {
            self::$isInitializing = false;
        }
    }

    private static function seedSystemItems(): void
    {
        $select = db()->prepare('SELECT id FROM navigation_items WHERE source_type = ? AND system_key = ? LIMIT 1');
        $insert = db()->prepare(
            'INSERT INTO navigation_items
                (source_type, system_key, label, url, is_visible, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );

        foreach (self::SYSTEM_ITEMS as $systemKey => $config) {
            $select->execute([self::SOURCE_SYSTEM, $systemKey]);
            if ($select->fetchColumn() !== false) {
                continue;
            }

            $insert->execute([
                self::SOURCE_SYSTEM,
                $systemKey,
                $config['label'],
                $config['url'],
                $config['is_visible'],
                $config['sort_order'],
            ]);
        }
    }

    private static function syncAllPages(): void
    {
        try {
            $pages = db()->query(
                "SELECT p.id, p.slug, p.title, p.nav_label, p.show_in_nav
                 FROM pages p
                 LEFT JOIN navigation_items n
                    ON n.source_type = 'page' AND n.page_id = p.id
                 WHERE p.deleted_at IS NULL
                   AND n.id IS NULL
                 ORDER BY p.show_in_nav DESC, p.sort_order ASC, p.id ASC"
            )->fetchAll();
        } catch (Throwable) {
            return;
        }

        foreach ($pages as $page) {
            self::insertMissingPageItem($page, !empty($page['show_in_nav']));
        }
    }

    private static function hydratePublicItems(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (!self::isPublicRowRenderable($row)) {
                continue;
            }

            $items[] = [
                'id' => (int) $row['id'],
                'source_type' => $row['source_type'],
                'label' => self::resolvedLabel($row),
                'url' => self::resolvedUrl($row),
                'target' => $row['target'] ?? null,
                'active_key' => self::activeKey($row),
            ];
        }

        return $items;
    }

    private static function hydrateAdminItems(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'source_type' => $row['source_type'],
                'system_key' => $row['system_key'],
                'label' => self::resolvedLabel($row),
                'url' => self::resolvedUrl($row),
                'target' => $row['target'] ?? null,
                'page_slug' => $row['page_slug'] ?? null,
                'page_status' => $row['page_status'] ?? null,
                'is_visible' => (int) $row['is_visible'],
                'can_delete' => $row['source_type'] === self::SOURCE_EXTERNAL,
            ];
        }

        return $items;
    }

    private static function resolvedLabel(array $row): string
    {
        if (($row['source_type'] ?? '') === self::SOURCE_PAGE) {
            $pageLabel = trim((string) ($row['page_nav_label'] ?? ''));
            $pageTitle = trim((string) ($row['page_title'] ?? ''));
            $storedLabel = trim((string) ($row['label'] ?? ''));
            return $storedLabel !== '' ? $storedLabel : ($pageLabel !== '' ? $pageLabel : $pageTitle);
        }

        return trim((string) ($row['label'] ?? ''));
    }

    private static function resolvedUrl(array $row): string
    {
        if (($row['source_type'] ?? '') === self::SOURCE_PAGE && !empty($row['page_slug'])) {
            return '/' . ltrim((string) $row['page_slug'], '/');
        }

        return (string) ($row['url'] ?? '#');
    }

    private static function activeKey(array $row): ?string
    {
        if (($row['source_type'] ?? '') === self::SOURCE_PAGE) {
            return $row['page_slug'] ?? null;
        }

        return $row['system_key'] ?? null;
    }

    private static function isPublicRowRenderable(array $row): bool
    {
        if (($row['source_type'] ?? '') !== self::SOURCE_PAGE) {
            return true;
        }

        if (empty($row['page_slug']) || !empty($row['page_deleted_at'])) {
            return false;
        }

        return ($row['page_status'] ?? 'draft') === 'published';
    }

    private static function legacyPublicItems(): array
    {
        $items = [
            [
                'id' => 0,
                'source_type' => self::SOURCE_SYSTEM,
                'label' => 'Gallery',
                'url' => '/',
                'target' => null,
                'active_key' => 'gallery',
            ],
            [
                'id' => 0,
                'source_type' => self::SOURCE_SYSTEM,
                'label' => 'Categories',
                'url' => '/categories',
                'target' => null,
                'active_key' => 'categories',
            ],
        ];

        foreach (Page::navItems() as $page) {
            $items[] = [
                'id' => (int) $page['id'],
                'source_type' => self::SOURCE_PAGE,
                'label' => $page['nav_label'] ?: $page['title'],
                'url' => '/' . $page['slug'],
                'target' => null,
                'active_key' => $page['slug'],
            ];
        }

        return $items;
    }

    private static function legacyAdminItems(bool $isVisible): array
    {
        $visibleItems = self::legacyPublicItems();
        $visibleLookup = [];
        foreach ($visibleItems as $item) {
            $visibleLookup[self::legacyLookupKey($item['source_type'], $item['active_key'] ?? null, $item['url'])] = true;
        }

        if ($isVisible) {
            return array_map(
                static fn (array $item): array => [
                    'id' => $item['id'],
                    'source_type' => $item['source_type'],
                    'system_key' => $item['source_type'] === self::SOURCE_SYSTEM ? ($item['active_key'] ?? null) : null,
                    'label' => $item['label'],
                    'url' => $item['url'],
                    'target' => $item['target'] ?? null,
                    'page_slug' => $item['source_type'] === self::SOURCE_PAGE ? ($item['active_key'] ?? null) : null,
                    'page_status' => $item['source_type'] === self::SOURCE_PAGE ? 'published' : null,
                    'is_visible' => 1,
                    'can_delete' => false,
                    'is_legacy' => true,
                ],
                $visibleItems
            );
        }

        $hiddenItems = [];

        foreach (self::SYSTEM_ITEMS as $systemKey => $systemItem) {
            $lookupKey = self::legacyLookupKey(self::SOURCE_SYSTEM, $systemKey, $systemItem['url']);
            if (isset($visibleLookup[$lookupKey])) {
                continue;
            }

            $hiddenItems[] = [
                'id' => 0,
                'source_type' => self::SOURCE_SYSTEM,
                'system_key' => $systemKey,
                'label' => $systemItem['label'],
                'url' => $systemItem['url'],
                'target' => null,
                'page_slug' => null,
                'page_status' => null,
                'is_visible' => 0,
                'can_delete' => false,
                'is_legacy' => true,
            ];
        }

        foreach (Page::all() as $page) {
            $lookupKey = self::legacyLookupKey(self::SOURCE_PAGE, $page['slug'], '/' . $page['slug']);
            if (isset($visibleLookup[$lookupKey])) {
                continue;
            }

            $hiddenItems[] = [
                'id' => (int) $page['id'],
                'source_type' => self::SOURCE_PAGE,
                'system_key' => null,
                'label' => $page['nav_label'] ?: $page['title'],
                'url' => '/' . $page['slug'],
                'target' => null,
                'page_slug' => $page['slug'],
                'page_status' => $page['status'] ?? null,
                'is_visible' => 0,
                'can_delete' => false,
                'is_legacy' => true,
            ];
        }

        return $hiddenItems;
    }

    public static function isAvailable(): bool
    {
        return self::ensureStorageReady();
    }

    private static function legacyLookupKey(string $sourceType, ?string $activeKey, string $url): string
    {
        return $sourceType . '|' . ($activeKey ?? $url);
    }

    private static function ensureStorageReady(): bool
    {
        if (self::tableExists()) {
            return true;
        }

        self::bootstrapStorage();
        return self::tableExists();
    }

    private static function bootstrapStorage(): void
    {
        try {
            db()->exec(
                "CREATE TABLE IF NOT EXISTS navigation_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    source_type ENUM('system', 'page', 'external') NOT NULL,
                    system_key VARCHAR(100) NULL,
                    page_id INT NULL,
                    label VARCHAR(255) NULL,
                    url VARCHAR(500) NULL,
                    target VARCHAR(20) NULL,
                    is_visible TINYINT(1) NOT NULL DEFAULT 1,
                    sort_order INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_navigation_system (system_key),
                    UNIQUE KEY uniq_navigation_page (page_id),
                    CONSTRAINT fk_navigation_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
                )"
            );
            self::$tableExistsCache = null;
            self::ensureInitialized();
        } catch (Throwable) {
            self::$tableExistsCache = false;
        }
    }

    private static function removeDefunctSystemItems(): void
    {
        $allowedKeys = array_keys(self::SYSTEM_ITEMS);
        if (empty($allowedKeys)) {
            db()->exec("DELETE FROM navigation_items WHERE source_type = 'system'");
            return;
        }

        $placeholders = implode(',', array_fill(0, count($allowedKeys), '?'));
        $stmt = db()->prepare(
            "DELETE FROM navigation_items
             WHERE source_type = 'system'
               AND system_key IS NOT NULL
               AND system_key NOT IN ($placeholders)"
        );
        $stmt->execute($allowedKeys);
    }

    private static function nextSortOrder(bool $isVisible): int
    {
        $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM navigation_items WHERE is_visible = ?');
        $stmt->execute([$isVisible ? 1 : 0]);
        return (int) $stmt->fetchColumn();
    }

    private static function insertMissingPageItem(array $pageData, bool $appendToVisible = false): void
    {
        $pageId = (int) ($pageData['id'] ?? 0);
        if ($pageId <= 0 || self::findByPageId($pageId)) {
            return;
        }

        $isVisible = !empty($pageData['show_in_nav']) ? 1 : 0;
        $sortOrder = $isVisible && $appendToVisible
            ? self::nextSortOrder(true)
            : self::nextSortOrder((bool) $isVisible);

        $stmt = db()->prepare(
            'INSERT INTO navigation_items
                (source_type, page_id, label, url, is_visible, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            self::SOURCE_PAGE,
            $pageId,
            trim((string) ($pageData['nav_label'] ?? '')) ?: null,
            '/' . ltrim((string) ($pageData['slug'] ?? ''), '/'),
            $isVisible,
            $sortOrder,
        ]);
    }

    private static function find(int $id): array|false
    {
        $stmt = db()->prepare('SELECT * FROM navigation_items WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    private static function findByPageId(int $pageId): array|false
    {
        $stmt = db()->prepare('SELECT * FROM navigation_items WHERE source_type = ? AND page_id = ? LIMIT 1');
        $stmt->execute([self::SOURCE_PAGE, $pageId]);
        return $stmt->fetch();
    }

    private static function tableExists(): bool
    {
        if (self::$tableExistsCache !== null) {
            return self::$tableExistsCache;
        }

        try {
            db()->query('SELECT 1 FROM navigation_items LIMIT 1');
            self::$tableExistsCache = true;
        } catch (Throwable) {
            self::$tableExistsCache = false;
        }

        return self::$tableExistsCache;
    }
}
