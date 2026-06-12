<?php
$pageTitle = 'Messages — Fornesus Art Admin';
ob_start();
?>
<div class="admin-section">
    <span id="reorder-status" class="reorder-status" aria-live="polite"></span>
    <div class="admin-section-head">
        <h1 class="admin-heading">Correspondence</h1>
    </div>
    <?php if (empty($messages)): ?>
        <p class="admin-empty">No messages yet.</p>
    <?php else: ?>
        <p class="admin-hint">Pinned messages (&#9733;) can be dragged to reorder.</p>
        <table class="admin-table">
            <thead>
                <tr>
                    <th></th>
                    <th>From</th>
                    <th>Email</th>
                    <th>Message</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="messages-sortable" data-reorder-url="/admin/messages/reorder">
                <?php foreach ($messages as $m): ?>
                    <tr data-id="<?= $m['id'] ?>" class="<?= $m['is_read'] ? '' : 'is-unread' ?>">
                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                        <td><?= htmlspecialchars($m['name']) ?></td>
                        <td><?= htmlspecialchars($m['email']) ?></td>
                        <td><?= htmlspecialchars(mb_strimwidth($m['message'], 0, 140, '…')) ?></td>
                        <td><?= htmlspecialchars($m['created_at']) ?></td>
                        <td class="admin-actions">
                            <form method="POST" action="/admin/messages/<?= $m['id'] ?>/toggle-read">
                                <button type="submit" class="msg-toggle-btn<?= $m['is_read'] ? '' : ' active' ?>"
                                        title="<?= $m['is_read'] ? 'Mark as unread' : 'Mark as read' ?>"
                                        aria-label="<?= $m['is_read'] ? 'Mark as unread' : 'Mark as read' ?>">
                                    <?= $m['is_read'] ? '&#9675;' : '&#9679;' ?>
                                </button>
                            </form>
                            <form method="POST" action="/admin/messages/<?= $m['id'] ?>/toggle-flag">
                                <button type="submit" class="msg-toggle-btn<?= $m['is_flagged'] ? ' active' : '' ?>"
                                        title="<?= $m['is_flagged'] ? 'Unflag' : 'Flag' ?>"
                                        aria-label="<?= $m['is_flagged'] ? 'Unflag' : 'Flag' ?>">
                                    &#9873;
                                </button>
                            </form>
                            <form method="POST" action="/admin/messages/<?= $m['id'] ?>/toggle-pin">
                                <button type="submit" class="msg-toggle-btn<?= $m['is_pinned'] ? ' active' : '' ?>"
                                        title="<?= $m['is_pinned'] ? 'Unpin' : 'Pin to top' ?>"
                                        aria-label="<?= $m['is_pinned'] ? 'Unpin' : 'Pin to top' ?>">
                                    <?= $m['is_pinned'] ? '&#9733;' : '&#9734;' ?>
                                </button>
                            </form>
                            <form method="POST" action="/admin/messages/<?= $m['id'] ?>/delete"
                                  onsubmit="return confirm('Move this message to the recycle bin?')">
                                <button type="submit" class="admin-del-btn">Move to trash</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
