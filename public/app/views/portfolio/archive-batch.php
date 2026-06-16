<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
?>
<div data-listing-batch data-next-offset="<?= (int) $nextOffset ?>" data-has-more="<?= $hasMore ? 'true' : 'false' ?>">
    <?php require __DIR__ . '/archive-cards.php'; ?>
</div>
