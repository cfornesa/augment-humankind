<?php

declare(strict_types=1);

/**
 * Migration script to rename native portfolio "Works" -> "Exhibit" and "Exhibits" -> "Collections".
 * Also renames "Platform Exhibits" -> "Platform Collections".
 *
 * Safe, idempotent database schema changes on the target DB only.
 * Does not mutate PLATFORM_* source database.
 */

require __DIR__ . '/apply-platform-assimilation-schema.php';

// Reuse the target PDO connection helper from apply-platform-assimilation-schema.php
loadEnvFile(dirname(__DIR__) . '/.env');
$pdo = targetPdo();

$pdo->beginTransaction();

try {
    echo "Dropping old foreign keys to prepare for rename...\n";
    
    // Drop foreign keys on tables we are going to modify/rename
    if (tableExists($pdo, 'artwork_categories')) {
        dropForeignKeysOnTable($pdo, 'artwork_categories');
    }
    if (tableExists($pdo, 'exhibit_categories')) {
        dropForeignKeysOnTable($pdo, 'exhibit_categories');
    }
    if (tableExists($pdo, 'artwork_media_items')) {
        dropForeignKeysOnTable($pdo, 'artwork_media_items');
    }
    if (tableExists($pdo, 'exhibit_media_items')) {
        dropForeignKeysOnTable($pdo, 'exhibit_media_items');
    }
    if (tableExists($pdo, 'exhibit_artworks')) {
        dropForeignKeysOnTable($pdo, 'exhibit_artworks');
    }
    if (tableExists($pdo, 'collection_exhibits')) {
        dropForeignKeysOnTable($pdo, 'collection_exhibits');
    }
    if (tableExists($pdo, 'platform_exhibit_items')) {
        dropForeignKeysOnTable($pdo, 'platform_exhibit_items');
    }
    if (tableExists($pdo, 'platform_collection_items')) {
        dropForeignKeysOnTable($pdo, 'platform_collection_items');
    }

    echo "Renaming tables...\n";

    // 1. Rename exhibits -> collections (do this first to free up the "exhibits" name)
    if (tableExists($pdo, 'exhibits') && !tableExists($pdo, 'collections')) {
        $pdo->exec("RENAME TABLE exhibits TO collections");
        echo "Renamed table exhibits -> collections\n";
    }

    // 2. Rename artworks -> exhibits
    if (tableExists($pdo, 'artworks') && !tableExists($pdo, 'exhibits')) {
        $pdo->exec("RENAME TABLE artworks TO exhibits");
        echo "Renamed table artworks -> exhibits\n";
    }

    // 3. Rename artwork_categories -> exhibit_categories
    if (tableExists($pdo, 'artwork_categories') && !tableExists($pdo, 'exhibit_categories')) {
        $pdo->exec("RENAME TABLE artwork_categories TO exhibit_categories");
        echo "Renamed table artwork_categories -> exhibit_categories\n";
    }

    // 4. Rename artwork_media_items -> exhibit_media_items
    if (tableExists($pdo, 'artwork_media_items') && !tableExists($pdo, 'exhibit_media_items')) {
        $pdo->exec("RENAME TABLE artwork_media_items TO exhibit_media_items");
        echo "Renamed table artwork_media_items -> exhibit_media_items\n";
    }

    // 5. Rename exhibit_artworks -> collection_exhibits
    if (tableExists($pdo, 'exhibit_artworks') && !tableExists($pdo, 'collection_exhibits')) {
        $pdo->exec("RENAME TABLE exhibit_artworks TO collection_exhibits");
        echo "Renamed table exhibit_artworks -> collection_exhibits\n";
    }

    // 6. Rename platform_exhibits -> platform_collections
    if (tableExists($pdo, 'platform_exhibits') && !tableExists($pdo, 'platform_collections')) {
        $pdo->exec("RENAME TABLE platform_exhibits TO platform_collections");
        echo "Renamed table platform_exhibits -> platform_collections\n";
    }

    // 7. Rename platform_exhibit_items -> platform_collection_items
    if (tableExists($pdo, 'platform_exhibit_items') && !tableExists($pdo, 'platform_collection_items')) {
        $pdo->exec("RENAME TABLE platform_exhibit_items TO platform_collection_items");
        echo "Renamed table platform_exhibit_items -> platform_collection_items\n";
    }

    echo "Altering table columns...\n";

    // Modify columns inside renamed tables to match new terms
    if (tableExists($pdo, 'exhibit_categories')) {
        if (columnExists($pdo, 'exhibit_categories', 'artwork_id')) {
            $pdo->exec("ALTER TABLE exhibit_categories CHANGE COLUMN artwork_id exhibit_id INT NOT NULL");
            echo "Altered exhibit_categories: artwork_id -> exhibit_id\n";
        }
    }

    if (tableExists($pdo, 'exhibit_media_items')) {
        if (columnExists($pdo, 'exhibit_media_items', 'artwork_id')) {
            $pdo->exec("ALTER TABLE exhibit_media_items CHANGE COLUMN artwork_id exhibit_id INT NOT NULL");
            echo "Altered exhibit_media_items: artwork_id -> exhibit_id\n";
        }
    }

    if (tableExists($pdo, 'collection_exhibits')) {
        if (columnExists($pdo, 'collection_exhibits', 'exhibit_id')) {
            $pdo->exec("ALTER TABLE collection_exhibits CHANGE COLUMN exhibit_id collection_id INT NOT NULL");
            echo "Altered collection_exhibits: exhibit_id -> collection_id\n";
        }
        if (columnExists($pdo, 'collection_exhibits', 'artwork_id')) {
            $pdo->exec("ALTER TABLE collection_exhibits CHANGE COLUMN artwork_id exhibit_id INT NOT NULL");
            echo "Altered collection_exhibits: artwork_id -> exhibit_id\n";
        }
    }

    if (tableExists($pdo, 'platform_collection_items')) {
        if (columnExists($pdo, 'platform_collection_items', 'exhibit_id')) {
            $pdo->exec("ALTER TABLE platform_collection_items CHANGE COLUMN exhibit_id collection_id INT NOT NULL");
            echo "Altered platform_collection_items: exhibit_id -> collection_id\n";
        }
    }

    echo "Recreating foreign key constraints...\n";

    // Recreate constraints on exhibit_categories
    $pdo->exec("ALTER TABLE exhibit_categories 
        ADD CONSTRAINT fk_exhibit_categories_exhibit FOREIGN KEY (exhibit_id) REFERENCES exhibits(id) ON DELETE CASCADE,
        ADD CONSTRAINT fk_exhibit_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE");
    echo "Recreated foreign keys on exhibit_categories\n";

    // Recreate constraints on exhibit_media_items
    $pdo->exec("ALTER TABLE exhibit_media_items 
        ADD CONSTRAINT fk_exhibit_media_items_exhibit FOREIGN KEY (exhibit_id) REFERENCES exhibits(id) ON DELETE CASCADE");
    echo "Recreated foreign keys on exhibit_media_items\n";

    // Recreate constraints on collection_exhibits
    $pdo->exec("ALTER TABLE collection_exhibits 
        ADD CONSTRAINT fk_collection_exhibits_collection FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
        ADD CONSTRAINT fk_collection_exhibits_exhibit FOREIGN KEY (exhibit_id) REFERENCES exhibits(id) ON DELETE CASCADE");
    echo "Recreated foreign keys on collection_exhibits\n";

    // Recreate constraints on platform_collection_items
    $pdo->exec("ALTER TABLE platform_collection_items 
        ADD CONSTRAINT fk_platform_collection_items_collection FOREIGN KEY (collection_id) REFERENCES platform_collections(id) ON DELETE CASCADE");
    echo "Recreated foreign keys on platform_collection_items\n";

    $pdo->commit();
    echo "SUCCESS: Database schema rename completed successfully!\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "ERROR: Schema rename failed: " . $e->getMessage() . "\n";
    exit(1);
}

function dropForeignKeysOnTable(PDO $pdo, string $tableName): void
{
    $stmt = $pdo->prepare("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
        WHERE CONSTRAINT_TYPE = 'FOREIGN KEY' 
          AND TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$tableName]);
    $constraints = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($constraints as $constraint) {
        $pdo->exec("ALTER TABLE `$tableName` DROP FOREIGN KEY `$constraint`");
        echo "Dropped foreign key constraint `$constraint` from table `$tableName`.\n";
    }
}
