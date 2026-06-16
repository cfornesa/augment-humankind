<?php

declare(strict_types=1);

class BlogCategoriesAdminController
{
    public static function index(): void
    {
        admin_check();
        $categories = BlogCategory::all();
        $taxonomyLabel = 'Category';
        $taxonomyPlural = 'Categories';
        $taxonomyIndexPath = '/admin/categories';
        $taxonomyCreatePath = '/admin/categories/create';
        $taxonomyReorderPath = '/admin/categories/reorder';
        $taxonomyDeleteMessage = 'Move this category to the recycle bin? Posts will keep their other category assignments.';
        require dirname(__DIR__, 2) . '/views/admin/categories/index.php';
    }

    public static function create(): void
    {
        admin_check();
        $category = null;
        $error = null;
        self::renderForm();
    }

    public static function store(): void
    {
        admin_check();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $category = null;
            $error = 'Name is required.';
            self::renderForm($category, $error);
            return;
        }

        try {
            BlogCategory::create(
                $name,
                self::resolvedSlug($name, null),
                0,
                trim($_POST['description'] ?? '') ?: null
            );
            header('Location: /admin/categories');
        } catch (Throwable $e) {
            $category = null;
            $error = $e->getMessage();
            self::renderForm($category, $error);
            return;
        }
        exit;
    }

    public static function edit(string $id): void
    {
        admin_check();
        $category = BlogCategory::find((int) $id);
        if (!$category) {
            header('Location: /admin/categories');
            exit;
        }

        self::renderForm($category);
    }

    public static function update(string $id): void
    {
        admin_check();
        $existing = BlogCategory::find((int) $id);
        if (!$existing) {
            header('Location: /admin/categories');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $error = 'Name is required.';
            self::renderForm($existing, $error);
            return;
        }

        try {
            BlogCategory::update(
                (int) $id,
                $name,
                self::resolvedSlug($name, (int) $id),
                (int) ($existing['sort_order'] ?? 0),
                trim($_POST['description'] ?? '') ?: null
            );
            header('Location: /admin/categories');
        } catch (Throwable $e) {
            $error = $e->getMessage();
            self::renderForm($existing, $error);
            return;
        }
        exit;
    }

    public static function delete(string $id): void
    {
        admin_check();
        BlogCategory::softDelete((int) $id);
        header('Location: /admin/categories');
        exit;
    }

    public static function reorder(): void
    {
        admin_check();
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        BlogCategory::reorder($ids);
        header('Content-Type: application/json');
        echo '{"ok":true}';
        exit;
    }

    private static function renderForm(?array $category = null, ?string $error = null): void
    {
        $taxonomyLabel = 'Category';
        $taxonomyPlural = 'Categories';
        $taxonomyIndexPath = '/admin/categories';
        $taxonomyCreatePath = '/admin/categories/create';
        $taxonomyEditBasePath = '/admin/categories';
        $showTaxonomyThumbnail = false;
        require dirname(__DIR__, 2) . '/views/admin/categories/form.php';
    }

    private static function resolvedSlug(string $name, ?int $existingId): string
    {
        $submitted = trim($_POST['slug'] ?? '');
        return $submitted !== ''
            ? slugify($submitted)
            : unique_category_slug($name, $existingId ?? 0);
    }
}
