<?php

/**
 * Handles the shifting and reordering of items when their sort_order is edited directly.
 * 
 * @param int $itemId The ID of the item being moved
 * @param int $newPos The new 0-indexed position
 * @param string $table The table name (art_pieces, platform_collections, exhibits, collections)
 */
function reorder_shift_position(int $itemId, int $newPos, string $table): void
{
    $db = db();
    
    // Fetch all active items in their current order
    $stmt = $db->prepare("SELECT id FROM {$table} WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC");
    $stmt->execute();
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Find current index of the item
    $oldIdx = array_search($itemId, $ids);
    if ($oldIdx === false) {
        return; // Item not found or deleted
    }

    // Clamp newPos to valid bounds
    $newPos = max(0, min(count($ids) - 1, $newPos));

    if ($oldIdx === $newPos) {
        // Just normalize order to sequential integers
        $stmtUpdate = $db->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");
        foreach ($ids as $index => $id) {
            $stmtUpdate->execute([$index, $id]);
        }
        return;
    }

    // Shift elements
    unset($ids[$oldIdx]);
    $ids = array_values($ids); // Reindex array keys
    array_splice($ids, $newPos, 0, $itemId);

    // Save back the sequential orders
    $stmtUpdate = $db->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");
    foreach ($ids as $index => $id) {
        $stmtUpdate->execute([$index, $id]);
    }
}
