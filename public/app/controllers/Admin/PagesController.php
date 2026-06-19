<?php

declare(strict_types=1);

class PagesController
{
    public static function index(): void
    {
        admin_check();
        Page::ensureSystemPages();
        $pages = Page::all();
        require dirname(__DIR__, 2) . '/views/admin/pages/index.php';
    }

    public static function create(): void
    {
        admin_check();
        $page = null;
        $pageError = null;
        require dirname(__DIR__, 2) . '/views/admin/pages/form.php';
    }

    public static function store(): void
    {
        admin_check();

        try {
            $data = self::resolvePageData(null);
            $pageId = Page::create($data);
            NavigationItem::syncPageItem($data + ['id' => $pageId], !empty($data['show_in_nav']));
            header('Location: /admin/pages/' . $pageId . '/edit');
        } catch (Throwable $e) {
            $page = null;
            $pageError = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/pages/form.php';
        }
        exit;
    }

    public static function edit(string $id): void
    {
        admin_check();
        $page = Page::find((int) $id);
        if (!$page) {
            header('Location: /admin/pages');
            exit;
        }

        $sections = PageSection::allForPage((int) $id);
        $pageError = null;
        require dirname(__DIR__, 2) . '/views/admin/pages/form.php';
    }

    public static function update(string $id): void
    {
        admin_check();
        $page = Page::find((int) $id);
        if (!$page) {
            header('Location: /admin/pages');
            exit;
        }

        try {
            $data = self::resolvePageData((int) $id);
            Page::update((int) $id, $data);
            NavigationItem::syncPageItem($data + ['id' => (int) $id]);
            header('Location: /admin/pages/' . (int) $id . '/edit?saved=1');
        } catch (Throwable $e) {
            $page = array_merge($page, $_POST);
            $sections = PageSection::allForPage((int) $id);
            $pageError = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/pages/form.php';
        }
        exit;
    }

    public static function delete(string $id): void
    {
        admin_check();
        try {
            Page::softDelete((int) $id);
            header('Location: /admin/pages');
        } catch (Throwable $e) {
            header('Location: /admin/pages?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function trash(): void
    {
        admin_check();
        $pages = Page::trashed();
        require dirname(__DIR__, 2) . '/views/admin/pages/trash.php';
    }

    public static function restore(string $id): void
    {
        admin_check();
        Page::restore((int) $id);
        header('Location: /admin/pages/trash');
        exit;
    }

    public static function hardDelete(string $id): void
    {
        admin_check();
        try {
            Page::hardDelete((int) $id);
            header('Location: /admin/pages/trash');
        } catch (Throwable $e) {
            header('Location: /admin/pages/trash?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    public static function trashEmpty(): void
    {
        admin_check();
        foreach (Page::trashed() as $page) {
            try {
                Page::hardDelete((int) $page['id']);
            } catch (Throwable) {
                // Protected pages should never reach the trash, but skip
                // rather than abort the whole bulk-empty if one ever does.
                continue;
            }
        }
        header('Location: /admin/pages/trash');
        exit;
    }

    public static function reorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        Page::reorder($ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    public static function sectionCreate(string $pageId): void
    {
        admin_check();
        $page = Page::find((int) $pageId);
        if (!$page) {
            header('Location: /admin/pages');
            exit;
        }

        $section = null;
        $sectionError = null;
        require dirname(__DIR__, 2) . '/views/admin/pages/section-form.php';
    }

    public static function sectionStore(string $pageId): void
    {
        admin_check();
        $page = Page::find((int) $pageId);
        if (!$page) {
            header('Location: /admin/pages');
            exit;
        }

        $heading      = trim($_POST['heading'] ?? '');
        $content      = trim($_POST['content'] ?? '');
        $wrapperClass = self::sanitiseWrapperClass($_POST['wrapper_class'] ?? '');
        if ($content === '') {
            $section = null;
            $sectionError = 'Content is required.';
            require dirname(__DIR__, 2) . '/views/admin/pages/section-form.php';
            return;
        }

        PageSection::create((int) $pageId, $heading, $content, 0, $wrapperClass);
        header('Location: /admin/pages/' . (int) $pageId . '/edit');
        exit;
    }

    public static function sectionEdit(string $sectionId): void
    {
        admin_check();
        $section = PageSection::find((int) $sectionId);
        if (!$section) {
            header('Location: /admin/pages');
            exit;
        }
        $page = Page::find((int) $section['page_id']);
        $sectionError = null;
        require dirname(__DIR__, 2) . '/views/admin/pages/section-form.php';
    }

    public static function sectionUpdate(string $sectionId): void
    {
        admin_check();
        $section = PageSection::find((int) $sectionId);
        if (!$section) {
            header('Location: /admin/pages');
            exit;
        }

        $page         = Page::find((int) $section['page_id']);
        $heading      = trim($_POST['heading'] ?? '');
        $content      = trim($_POST['content'] ?? '');
        $wrapperClass = self::sanitiseWrapperClass($_POST['wrapper_class'] ?? '');
        if ($content === '') {
            $sectionError = 'Content is required.';
            require dirname(__DIR__, 2) . '/views/admin/pages/section-form.php';
            return;
        }

        PageSection::update((int) $sectionId, $heading, $content, $wrapperClass);
        header('Location: /admin/pages/' . (int) $section['page_id'] . '/edit');
        exit;
    }

    public static function sectionDelete(string $sectionId): void
    {
        admin_check();
        $section = PageSection::find((int) $sectionId);
        if ($section) {
            PageSection::delete((int) $sectionId);
            header('Location: /admin/pages/' . (int) $section['page_id'] . '/edit');
            exit;
        }

        header('Location: /admin/pages');
        exit;
    }

    public static function sectionReorder(string $pageId): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        PageSection::reorder((int) $pageId, $ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    private static function sanitiseWrapperClass(string $raw): ?string
    {
        $allowed = ['mission-band', 'callout', 'content-cards', 'managed-section'];
        $value = trim($raw);
        return in_array($value, $allowed, true) ? $value : null;
    }

    private static function resolvePageData(?int $existingId): array
    {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $slugInput = trim($_POST['slug'] ?? '');
        $slug = Page::validateSlug($slugInput !== '' ? $slugInput : $title, $existingId ?? 0);

        $status = $_POST['status'] ?? 'published';
        if (!in_array($status, ['published', 'draft'], true)) {
            throw new InvalidArgumentException('Invalid page status.');
        }

        return [
            'title'            => $title,
            'slug'             => $slug,
            'status'           => $status,
            'template'         => 'standard',
            'nav_label'        => trim($_POST['nav_label'] ?? ''),
            'show_in_nav'      => !empty($_POST['show_in_nav']) ? 1 : 0,
            'meta_title'       => trim($_POST['meta_title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? ''),
            'og_title'         => trim($_POST['og_title'] ?? ''),
            'og_description'   => trim($_POST['og_description'] ?? ''),
            'og_image'         => trim($_POST['og_image'] ?? ''),
            'sort_order'       => (int) ($_POST['sort_order'] ?? ($existingId ? (Page::find($existingId)['sort_order'] ?? 0) : 0)),
        ];
    }
}
