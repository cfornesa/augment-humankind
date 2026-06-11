<?php
declare(strict_types=1);

$routes = [
    '/' => 'home',
    '/services' => 'services',
    '/notes' => 'notes',
    '/contact' => 'contact',
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (PHP_SAPI === 'cli-server') {
    $assetPath = __DIR__ . $path;
    if (is_file($assetPath)) {
        return false;
    }
}

$path = rtrim($path, '/') ?: '/';
$page = $routes[$path] ?? null;

if ($page === null) {
    http_response_code(404);
    $page = '404';
}

$siteName = 'Augment Humankind';
$contactEmail = 'contact@augmenthumankind.com';

$pageMeta = [
    'home' => [
        'title' => 'Augment Humankind | AI fieldguides for nontechnical teams',
        'description' => 'A mission-first AI consulting practice helping nontechnical teams use AI to extend their capabilities.',
    ],
    'services' => [
        'title' => 'Services | Augment Humankind',
        'description' => 'Three focused AI consulting services for nontechnical teams: strategy, prototype builds, and capability transfer.',
    ],
    'notes' => [
        'title' => 'Field Notes | Augment Humankind',
        'description' => 'Learning notes and practical observations from building useful AI workflows.',
    ],
    'contact' => [
        'title' => 'Contact | Augment Humankind',
        'description' => 'Start a focused conversation about AI strategy, prototype builds, or team capability transfer.',
    ],
    '404' => [
        'title' => 'Page not found | Augment Humankind',
        'description' => 'The requested page could not be found.',
    ],
];

$services = [
    [
        'number' => '01',
        'name' => 'AI Strategy Fieldguide',
        'summary' => 'A short engagement for nontechnical teams that need clarity before adopting new AI tools.',
        'bestFor' => 'Teams with curiosity, scattered ideas, and no shared map yet.',
        'deliverables' => [
            'Opportunity map',
            'Use-case shortlist',
            'Risk boundaries',
            'Practical adoption roadmap',
        ],
    ],
    [
        'number' => '02',
        'name' => 'Workflow Prototype Build',
        'summary' => 'A focused build around one useful workflow, kept small enough to test and maintain.',
        'bestFor' => 'Teams that have one clear problem and need a working first version.',
        'deliverables' => [
            'Lightweight prototype',
            'Workflow documentation',
            'Handoff notes',
            'Maintainability guidance',
        ],
    ],
    [
        'number' => '03',
        'name' => 'Team Capability Transfer',
        'summary' => 'Guided practice that helps people keep using AI well after the first strategy or build engagement.',
        'bestFor' => 'Teams that need confidence, shared norms, and repeatable habits.',
        'deliverables' => [
            'Team playbooks',
            'Prompt and workflow examples',
            'Office-hour style guidance',
            'Learning paths informed by public resources',
        ],
    ],
];

$navigation = [
    '/' => 'Mission',
    '/services' => 'Services',
    '/notes' => 'Field Notes',
    '/contact' => 'Contact',
];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function isActive(string $href, string $path): string
{
    return $href === $path ? ' aria-current="page"' : '';
}

function bodyClass(string $page): string
{
    return 'page-' . preg_replace('/[^a-z0-9-]/', '', $page);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageMeta[$page]['title']) ?></title>
    <meta name="description" content="<?= e($pageMeta[$page]['description']) ?>">
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body class="<?= e(bodyClass($page)) ?>">
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header" aria-label="Site header">
        <a class="brand" href="/" aria-label="Augment Humankind home">
            <span class="brand-mark" aria-hidden="true">AH</span>
            <span class="brand-text">Augment Humankind</span>
        </a>
        <nav class="site-nav" aria-label="Primary navigation">
            <?php foreach ($navigation as $href => $label): ?>
                <a href="<?= e($href) ?>"<?= isActive($href, $path) ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main id="main">
        <?php if ($page === 'home'): ?>
            <section class="hero section-grid" aria-labelledby="hero-title">
                <div class="hero-copy">
                    <p class="eyebrow">AI fieldguides for nontechnical teams</p>
                    <h1 id="hero-title">Augment Humankind</h1>
                    <p class="hero-statement">Helping people and teams use AI to extend what they can already do, not replace the judgment that makes their work matter.</p>
                    <div class="hero-actions" aria-label="Primary actions">
                        <a class="button button-primary" href="/services">Explore services</a>
                        <a class="button button-secondary" href="/contact">Start a conversation</a>
                    </div>
                </div>
                <div class="guide-panel" aria-label="Friendly guide illustration">
                    <img src="/assets/friendly-guide.png" alt="A friendly robot guide holding a bright idea light bulb.">
                    <p>The guide is friendly. The work is practical.</p>
                </div>
            </section>

            <section class="mission-band" aria-labelledby="mission-title">
                <p class="eyebrow">Mission</p>
                <h2 id="mission-title">AI should make people more capable, not more dependent.</h2>
                <p>Augment Humankind is being built as a solo AI consulting practice for teams that need useful adoption, clear strategy, and practical workflows without enterprise theatre.</p>
            </section>

            <section class="service-preview" aria-labelledby="service-preview-title">
                <div class="section-heading">
                    <p class="eyebrow">Three ways in</p>
                    <h2 id="service-preview-title">Start with clarity, build one useful thing, then transfer the capability.</h2>
                </div>
                <div class="service-strip">
                    <?php foreach ($services as $service): ?>
                        <article class="service-card">
                            <span class="service-number"><?= e($service['number']) ?></span>
                            <h3><?= e($service['name']) ?></h3>
                            <p><?= e($service['summary']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <a class="text-link" href="/services">See the full service fieldguide</a>
            </section>

            <section class="proof-grid" aria-labelledby="method-title">
                <div>
                    <p class="eyebrow">Operating method</p>
                    <h2 id="method-title">Small promises, visible learning, practical transfer.</h2>
                </div>
                <ul class="method-list">
                    <li><strong>Bounded engagements.</strong> Work is shaped so a one-person practice can deliver it without pretending to be a large agency.</li>
                    <li><strong>Human review.</strong> AI-generated prose, analysis, and artifacts are treated as drafts until a person owns the final judgment.</li>
                    <li><strong>Open learning path.</strong> Capability grows through active study and practice, including public resources such as IBM SkillsBuild, Coursera, and DataCamp.</li>
                </ul>
            </section>
        <?php elseif ($page === 'services'): ?>
            <section class="page-hero" aria-labelledby="services-title">
                <p class="eyebrow">Services</p>
                <h1 id="services-title">Three focused offers for teams that want AI to become usable.</h1>
                <p>Each service is narrow enough for a solo consultant to deliver well and concrete enough for nontechnical teams to understand what happens next.</p>
            </section>

            <section class="services-detail" aria-label="Service offerings">
                <?php foreach ($services as $service): ?>
                    <article class="service-detail">
                        <div class="service-kicker">
                            <span><?= e($service['number']) ?></span>
                            <h2><?= e($service['name']) ?></h2>
                        </div>
                        <p class="service-summary"><?= e($service['summary']) ?></p>
                        <p><strong>Best for:</strong> <?= e($service['bestFor']) ?></p>
                        <div>
                            <h3>Typical deliverables</h3>
                            <ul class="deliverable-list">
                                <?php foreach ($service['deliverables'] as $deliverable): ?>
                                    <li><?= e($deliverable) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="callout" aria-labelledby="service-boundary-title">
                <h2 id="service-boundary-title">What this is not</h2>
                <p>This is not a promise to automate everything, replace staff, or drop a tool into a team without changing the surrounding work. The offer is disciplined augmentation: identify where AI helps, build only what is useful, and teach the team how to keep judgment in the loop.</p>
                <a class="button button-primary" href="/contact">Discuss a focused project</a>
            </section>
        <?php elseif ($page === 'notes'): ?>
            <section class="page-hero notes-hero" aria-labelledby="notes-title">
                <p class="eyebrow">Field Notes</p>
                <h1 id="notes-title">A learning journal for practical AI work.</h1>
                <p>This section will hold short notes from active study, client-safe patterns, workflow experiments, and resources that sharpen the practice.</p>
            </section>

            <section class="notes-empty" aria-labelledby="first-notes-title">
                <div class="note-card">
                    <p class="eyebrow">Opening soon</p>
                    <h2 id="first-notes-title">The first notes will be small on purpose.</h2>
                    <p>Expect field observations rather than thought-leader essays: what worked, what failed, what nontechnical teams found confusing, and which resources helped translate AI into daily work.</p>
                </div>
                <div class="note-card note-card-accent">
                    <h2>Learning sources in view</h2>
                    <p>IBM SkillsBuild, Coursera, DataCamp, product documentation, and hands-on prototypes can all inform the practice. Specific certificates or course completions will only be named after they are true.</p>
                </div>
            </section>
        <?php elseif ($page === 'contact'): ?>
            <section class="page-hero contact-hero" aria-labelledby="contact-title">
                <p class="eyebrow">Contact</p>
                <h1 id="contact-title">Start with the team, not the tool.</h1>
                <p>A real intake form is a later feature. For now, send a focused email with the problem you are trying to solve and where your team feels stuck.</p>
                <a class="button button-primary" href="mailto:<?= e($contactEmail) ?>?subject=Augment%20Humankind%20Inquiry">Email <?= e($contactEmail) ?></a>
            </section>

            <section class="contact-brief" aria-labelledby="brief-title">
                <h2 id="brief-title">Helpful context to include</h2>
                <ul class="method-list">
                    <li>What your team does and who would use the workflow.</li>
                    <li>The AI idea, task, or decision you want help clarifying.</li>
                    <li>What would count as a useful first version.</li>
                    <li>Any data, privacy, or approval constraints that matter.</li>
                </ul>
            </section>
        <?php else: ?>
            <section class="page-hero" aria-labelledby="missing-title">
                <p class="eyebrow">404</p>
                <h1 id="missing-title">This field note is not on the map.</h1>
                <p>The page may have moved, or the address may be incorrect.</p>
                <a class="button button-primary" href="/">Return to the mission</a>
            </section>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> Augment Humankind. Built as a small, practical AI fieldguide.</p>
        <nav aria-label="Footer navigation">
            <a href="/services">Services</a>
            <a href="/notes">Field Notes</a>
            <a href="/contact">Contact</a>
        </nav>
    </footer>
</body>
</html>
