<?php

declare(strict_types=1);

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function ah_column_exists(string $tableName, string $columnName): bool
{
    return $tableName === 'pages' && $columnName === 'system_key';
}

function ah_table_exists(string $tableName): bool
{
    return $tableName === 'page_slug_redirects';
}

function db(): FakeDb
{
    static $db = null;
    if ($db === null) {
        $db = new FakeDb();
    }
    return $db;
}

final class FakeDb
{
    /** @var array<int, array<string, mixed>> */
    public array $pages = [];

    /** @var array<string, array<string, mixed>> */
    public array $redirects = [];

    public function prepare(string $sql): FakeStatement
    {
        return new FakeStatement($this, $sql);
    }
}

final class FakeStatement
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    private mixed $column = false;

    public function __construct(private FakeDb $db, private string $sql)
    {
    }

    /** @param array<int, mixed> $params */
    public function execute(array $params = []): bool
    {
        if (str_starts_with($this->sql, 'SELECT * FROM pages WHERE id = ?')) {
            $page = $this->db->pages[(int) $params[0]] ?? false;
            $this->rows = $page ? [$page] : [];
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT * FROM pages WHERE slug = ?')) {
            $slug = (string) $params[0];
            $this->rows = array_values(array_filter(
                $this->db->pages,
                static fn (array $page): bool => $page['slug'] === $slug && $page['deleted_at'] === null
            ));
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT * FROM pages WHERE system_key = ?')) {
            $systemKey = (string) $params[0];
            $this->rows = array_values(array_filter(
                $this->db->pages,
                static fn (array $page): bool => $page['system_key'] === $systemKey && $page['deleted_at'] === null
            ));
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE pages SET')) {
            $id = (int) $params[count($params) - 1];
            $this->db->pages[$id]['title'] = $params[0];
            $this->db->pages[$id]['slug'] = $params[1];
            $this->db->pages[$id]['status'] = $params[2];
            $this->db->pages[$id]['template'] = $params[3];
            $this->db->pages[$id]['nav_label'] = $params[4];
            $this->db->pages[$id]['show_in_nav'] = $params[5];
            $this->db->pages[$id]['meta_title'] = $params[6];
            $this->db->pages[$id]['meta_description'] = $params[7];
            $this->db->pages[$id]['og_title'] = $params[8];
            $this->db->pages[$id]['og_description'] = $params[9];
            $this->db->pages[$id]['og_image'] = $params[10];
            $this->db->pages[$id]['sort_order'] = $params[11];
            $this->db->pages[$id]['system_key'] = $params[12];
            return true;
        }

        if (str_starts_with($this->sql, 'INSERT INTO page_slug_redirects')) {
            $this->db->redirects[(string) $params[0]] = [
                'old_slug' => (string) $params[0],
                'page_id' => (int) $params[1],
                'system_key' => $params[2],
            ];
            return true;
        }

        if (str_contains($this->sql, 'FROM page_slug_redirects r')) {
            $oldSlug = (string) $params[0];
            $redirect = $this->db->redirects[$oldSlug] ?? null;
            if ($redirect === null) {
                $this->rows = [];
                return true;
            }
            $page = $this->db->pages[(int) $redirect['page_id']] ?? null;
            $this->rows = $page && $page['deleted_at'] === null && $page['slug'] !== $oldSlug
                ? [['old_slug' => $oldSlug, 'target_slug' => $page['slug']]]
                : [];
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT id FROM pages WHERE slug = ?')) {
            $slug = (string) $params[0];
            $match = array_filter($this->db->pages, static fn (array $page): bool => $page['slug'] === $slug);
            $this->column = $match === [] ? false : reset($match)['id'];
            return true;
        }

        throw new RuntimeException('Unhandled fake SQL: ' . $this->sql);
    }

    public function fetch(): array|false
    {
        return array_shift($this->rows) ?: false;
    }

    public function fetchColumn(): mixed
    {
        return $this->column;
    }
}

require_once __DIR__ . '/../public/app/models/Page.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

db()->pages[7] = [
    'id' => 7,
    'system_key' => null,
    'title' => 'About',
    'slug' => 'about',
    'status' => 'published',
    'template' => 'standard',
    'nav_label' => null,
    'show_in_nav' => 0,
    'meta_title' => null,
    'meta_description' => null,
    'og_title' => null,
    'og_description' => null,
    'og_image' => null,
    'sort_order' => 0,
    'deleted_at' => null,
];

Page::update(7, [
    'title' => 'Bio',
    'slug' => 'bio',
    'status' => 'published',
    'template' => 'standard',
    'nav_label' => '',
    'show_in_nav' => 0,
    'meta_title' => '',
    'meta_description' => '',
    'og_title' => '',
    'og_description' => '',
    'og_image' => '',
    'sort_order' => 0,
]);

assert_true(db()->pages[7]['system_key'] === 'about', 'About system identity should survive a slug rename.');
assert_true(isset(db()->redirects['about']), 'Old system slug should be recorded as a redirect.');

$redirect = Page::redirectForSlug('about');
assert_true($redirect !== false && $redirect['target_slug'] === 'bio', 'Old About slug should redirect to Bio.');
assert_true(Page::isSystemPage(db()->pages[7]), 'Renamed Bio page should still be treated as a system page.');

echo "system-page-identity tests passed\n";
