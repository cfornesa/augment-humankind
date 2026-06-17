<?php declare(strict_types=1); ?>
<div data-listing-batch
     data-next-offset="<?= (int) $nextOffset ?>"
     data-has-more="<?= $hasMore ? 'true' : 'false' ?>">
    <?php foreach ($collections as $collection): ?>
        <?php require __DIR__ . '/_collection-card.php'; ?>
    <?php endforeach; ?>
</div>
