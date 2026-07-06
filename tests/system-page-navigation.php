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

function db(): FakeNavigationDb
{
    static $db = null;
    if ($db === null) {
        $db = new FakeNavigationDb();
    }
    return $db;
}

final class FakeNavigationDb
{
    /** @var array<int, array<string, mixed>> */
    public array $pages = [];

    /** @var array<int, array<string, mixed>> */
    public array $navigationItems = [];

    public int $nextNavigationId = 1;

    public function prepare(string $sql): FakeNavigationStatement
    {
        return new FakeNavigationStatement($this, $sql);
    }

    public function query(string $sql): FakeNavigationStatement
    {
        $stmt = new FakeNavigationStatement($this, $sql);
        $stmt->execute();
        return $stmt;
    }
}

final class FakeNavigationStatement
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    private mixed $column = false;

    public function __construct(private FakeNavigationDb $db, private string $sql)
    {
    }

    /** @param array<int, mixed> $params */
    public function execute(array $params = []): bool
    {
        if (str_starts_with($this->sql, 'SELECT 1 FROM navigation_items LIMIT 1')) {
            $this->rows = [['1' => 1]];
            return true;
        }

        if (str_starts_with($this->sql, 'SELECT id FROM navigation_items WHERE source_type = ? AND system_key = ?')) {
            $sourceType = (string) $params[0];
            $systemKey = (string) $params[1];
            foreach ($this->db->navigationItems as $item) {
                if ($item['source_type'] === $sourceType && $item['system_key'] === $systemKey) {
                    $this->column = $item['id'];
                    return true;
                }
            }
            $this->column = false;
            return true;
        }

        if (str_starts_with($this->sql, 'UPDATE navigation_items SET sort_order = sort_order + 1')) {
            $sourceType = (string) $params[0];
            $minimumSort = (int) $params[1];
            foreach ($this->db->navigationItems as &$item) {
                if ($item['source_type'] === $sourceType && (int) $item['sort_order'] >= $minimumSort) {
                    $item['sort_order']++;
                }
            }
            unset($item);
            return true;
        }

        if (str_starts_with($this->sql, 'INSERT INTO navigation_items')) {
            $id = $this->db->nextNavigationId++;
            $this->db->navigationItems[$id] = [
                'id' => $id,
                'source_type' => (string) $params[0],
                'system_key' => $params[1],
                'page_id' => null,
                'label' => $params[2],
                'url' => $params[3],
                'target' => null,
                'is_visible' => (int) $params[4],
                'sort_order' => (int) $params[5],
            ];
            return true;
        }

        if (str_starts_with($this->sql, 'DELETE FROM navigation_items')) {
            $allowed = array_map('strval', $params);
            foreach ($this->db->navigationItems as $id => $item) {
                if (
                    $item['source_type'] === 'system'
                    && $item['system_key'] !== null
                    && !in_array((string) $item['system_key'], $allowed, true)
                ) {
                    unset($this->db->navigationItems[$id]);
                }
            }
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

        if (str_starts_with($this->sql, 'UPDATE navigation_items') && str_contains($this->sql, 'WHERE source_type = ? AND system_key = ?')) {
            $sourceType = (string) $params[3];
            $systemKey = (string) $params[4];
            foreach ($this->db->navigationItems as &$item) {
                if ($item['source_type'] === $sourceType && $item['system_key'] === $systemKey) {
                    $item['label'] = $params[0];
                    $item['url'] = $params[1];
                    $item['is_visible'] = (int) $params[2];
                }
            }
            unset($item);
            return true;
        }

        if (str_starts_with($this->sql, 'DELETE n')) {
            $systemSlugs = array_map('strval', $params);
            foreach ($this->db->navigationItems as $id => $item) {
                if (($item['source_type'] ?? '') !== 'page' || empty($item['page_id'])) {
                    continue;
                }
                $page = $this->db->pages[(int) $item['page_id']] ?? null;
                if ($page && in_array((string) $page['slug'], $systemSlugs, true)) {
                    unset($this->db->navigationItems[$id]);
                }
            }
            return true;
        }

        if (str_contains($this->sql, 'SELECT n.id AS navigation_id')) {
            $this->rows = [];
            foreach ($this->db->navigationItems as $item) {
                if (($item['source_type'] ?? '') !== 'page' || empty($item['page_id'])) {
                    continue;
                }
                $page = $this->db->pages[(int) $item['page_id']] ?? null;
                if ($page) {
                    $this->rows[] = ['navigation_id' => $item['id']] + $page;
                }
            }
            return true;
        }

        if (str_contains($this->sql, 'FROM pages p') && str_contains($this->sql, 'n.id IS NULL')) {
            $this->rows = [];
            return true;
        }

        if (str_contains($this->sql, 'FROM navigation_items n') && str_contains($this->sql, 'WHERE n.is_visible = 1')) {
            $this->rows = [];
            foreach ($this->db->navigationItems as $item) {
                if ((int) $item['is_visible'] !== 1) {
                    continue;
                }
                $systemPage = null;
                if (($item['source_type'] ?? '') === 'system' && !empty($item['system_key'])) {
                    foreach ($this->db->pages as $page) {
                        if ($page['system_key'] === $item['system_key'] && $page['deleted_at'] === null) {
                            $systemPage = $page;
                            break;
                        }
                    }
                }
                $this->rows[] = $item + [
                    'page_slug' => $systemPage['slug'] ?? null,
                    'page_title' => $systemPage['title'] ?? null,
                    'page_nav_label' => $systemPage['nav_label'] ?? null,
                    'page_status' => $systemPage['status'] ?? null,
                    'page_deleted_at' => $systemPage['deleted_at'] ?? null,
                ];
            }
            usort($this->rows, static fn (array $a, array $b): int => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
            return true;
        }

        throw new RuntimeException('Unhandled fake SQL: ' . $this->sql);
    }

    public function fetchAll(): array
    {
        return $this->rows;
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
require_once __DIR__ . '/../public/app/models/NavigationItem.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

db()->pages[7] = [
    'id' => 7,
    'system_key' => 'about',
    'title' => 'Bio',
    'slug' => 'bio',
    'status' => 'published',
    'template' => 'standard',
    'nav_label' => 'Bio',
    'show_in_nav' => 1,
    'meta_title' => null,
    'meta_description' => null,
    'og_title' => null,
    'og_description' => null,
    'og_image' => null,
    'sort_order' => 0,
    'deleted_at' => null,
];

$items = NavigationItem::publicItems();
$bioItem = null;
foreach ($items as $item) {
    if (($item['active_key'] ?? null) === 'about') {
        $bioItem = $item;
        break;
    }
}

assert_true($bioItem !== null, 'Visible About/Bio system page should create a public system navigation item.');
assert_true($bioItem['label'] === 'Bio', 'About/Bio system nav should use the page navigation label.');
assert_true($bioItem['url'] === '/bio', 'About/Bio system nav should use the current page slug.');

echo "system-page-navigation tests passed\n";
