<?php
$pageTitle = 'Posts Calendar — Augment Humankind Admin';

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = $monday->modify("+{$i} days");
}

$postsByDate = [];
foreach ($posts as $post) {
    $date = $post['scheduled_at']
        ? substr($post['scheduled_at'], 0, 10)
        : substr($post['created_at'], 0, 10);
    $postsByDate[$date][] = $post;
}

$statusLabels = ['published' => 'Published', 'draft' => 'Draft', 'scheduled' => 'Scheduled'];
$statusColors = ['published' => '#2d6a2d', 'draft' => '#7a7a7a', 'scheduled' => '#8a6000'];

ob_start();
?>
<div class="admin-section">
    <div class="admin-section-head">
        <h1 class="admin-heading">Posts Calendar</h1>
        <a href="/admin/posts/create" class="admin-btn">+ New Post</a>
    </div>

    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.2rem;flex-wrap:wrap">
        <a href="/admin/posts/calendar?week=<?= htmlspecialchars($prevWeek) ?>" class="admin-btn admin-btn-ghost">&#8592; Prev week</a>
        <span style="font-family:ui-monospace,monospace;font-size:0.82rem;font-weight:800">
            <?= $days[0]->format('D j M') ?> – <?= $days[6]->format('D j M Y') ?>
        </span>
        <a href="/admin/posts/calendar?week=<?= htmlspecialchars($nextWeek) ?>" class="admin-btn admin-btn-ghost">Next week &#8594;</a>
        <a href="/admin/posts" class="admin-btn admin-btn-ghost" style="margin-left:auto">List view</a>
    </div>

    <div class="post-calendar-grid">
        <?php foreach ($days as $day): ?>
            <?php
            $dateKey = $day->format('Y-m-d');
            $isToday = $day->format('Y-m-d') === (new DateTimeImmutable())->format('Y-m-d');
            $dayPosts = $postsByDate[$dateKey] ?? [];
            ?>
            <div class="post-calendar-col<?= $isToday ? ' is-today' : '' ?>">
                <div class="post-calendar-col-head">
                    <span class="post-cal-dayname"><?= $day->format('D') ?></span>
                    <span class="post-cal-daynum<?= $isToday ? ' is-today' : '' ?>"><?= $day->format('j') ?></span>
                </div>
                <div class="post-calendar-col-body">
                    <?php if (empty($dayPosts)): ?>
                        <a href="/admin/posts/create" class="post-cal-new-btn" title="New post on <?= $day->format('D j M') ?>">+</a>
                    <?php else: ?>
                        <?php foreach ($dayPosts as $p): ?>
                            <?php
                            $ts = $p['scheduled_at'] ?: $p['created_at'];
                            $timeStr = $ts ? date('H:i', strtotime($ts)) : '';
                            $titleStr = ($p['title'] !== null && $p['title'] !== '') ? $p['title'] : '(untitled)';
                            $st = $p['status'] ?? 'draft';
                            ?>
                            <div class="post-cal-card">
                                <a href="/admin/posts/<?= (int) $p['id'] ?>/edit" class="post-cal-card-title"><?= htmlspecialchars($titleStr) ?></a>
                                <div class="post-cal-card-meta">
                                    <span class="post-cal-badge" style="color:<?= $statusColors[$st] ?? '#555' ?>"><?= $statusLabels[$st] ?? ucfirst($st) ?></span>
                                    <?php if ($timeStr): ?><span class="post-cal-time"><?= $timeStr ?></span><?php endif ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                        <a href="/admin/posts/create" class="post-cal-new-btn" title="New post on <?= $day->format('D j M') ?>">+</a>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>

<style>
.post-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0;
    border: 3px solid var(--line);
}
.post-calendar-col {
    border-right: 3px solid var(--line);
    min-height: 260px;
    display: flex;
    flex-direction: column;
}
.post-calendar-col:last-child { border-right: none; }
.post-calendar-col.is-today { background: var(--paper-deep); }
.post-calendar-col-head {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.5rem 0.4rem 0.4rem;
    border-bottom: 3px solid var(--line);
    gap: 0.15rem;
}
.post-cal-dayname {
    font-family: ui-monospace, monospace;
    font-size: 0.62rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    opacity: 0.6;
    color: var(--ink);
}
.post-cal-daynum {
    font-family: ui-monospace, monospace;
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--ink);
    line-height: 1;
}
.post-cal-daynum.is-today {
    background: var(--ink);
    color: var(--white);
    width: 1.7rem;
    height: 1.7rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.88rem;
}
.post-calendar-col-body {
    flex: 1;
    padding: 0.4rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.post-cal-card {
    background: var(--white);
    border: 2px solid var(--line);
    padding: 0.35rem 0.45rem;
    display: flex;
    flex-direction: column;
    gap: 0.18rem;
}
.post-cal-card-title {
    font-size: 0.74rem;
    color: var(--ink);
    text-decoration: none;
    font-weight: 700;
    line-height: 1.3;
    word-break: break-word;
}
.post-cal-card-title:hover { text-decoration: underline; }
.post-cal-card-meta {
    display: flex;
    gap: 0.4rem;
    align-items: center;
    flex-wrap: wrap;
}
.post-cal-badge {
    font-family: ui-monospace, monospace;
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.post-cal-time {
    font-family: ui-monospace, monospace;
    font-size: 0.62rem;
    color: var(--ink-soft);
}
.post-cal-new-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.4rem;
    height: 1.4rem;
    border: 2px dashed var(--line);
    color: var(--ink);
    text-decoration: none;
    font-size: 1rem;
    opacity: 0.35;
    transition: opacity 0.12s;
    margin-top: auto;
    align-self: center;
}
.post-cal-new-btn:hover { opacity: 0.8; }

@media (max-width: 700px) {
    .post-calendar-grid { grid-template-columns: 1fr; }
    .post-calendar-col { border-right: none; border-bottom: 3px solid var(--line); min-height: auto; }
    .post-calendar-col:last-child { border-bottom: none; }
}
</style>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
