<?php declare(strict_types=1); ?>
<div data-listing-batch
     data-next-offset="<?= (int) $nextOffset ?>"
     data-has-more="<?= $hasMore ? 'true' : 'false' ?>">
    <?php foreach ($pieces as $piece): ?>
        <?php require __DIR__ . '/_piece-card.php'; ?>
    <?php endforeach; ?>
</div>
