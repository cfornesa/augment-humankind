-- Homepage: seed CMS-managed content for the root path.
-- This inserts a single 'home' page with 4 sections, matching the
-- static markup currently in public/index.php.
-- After seeding, the homepage will be editable via /admin/pages.

INSERT INTO pages
    (title, slug, status, template, nav_label, show_in_nav, meta_title, meta_description, og_title, og_description, og_image, sort_order)
VALUES
    ('Home', 'home', 'published', 'standard', 'Home', 1,
     'Augment Humankind | AI fieldguides for nontechnical teams',
     'A mission-first AI consulting practice helping nontechnical teams use AI to extend their capabilities.',
     NULL, NULL, NULL, 0);

INSERT INTO page_sections (page_id, heading, content, sort_order)
VALUES
    (
        (SELECT id FROM pages WHERE slug = 'home'),
        NULL,
        '<section class="hero section-grid" aria-labelledby="hero-title">
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
</section>',
        0
    ),
    (
        (SELECT id FROM pages WHERE slug = 'home'),
        NULL,
        '<section class="mission-band" aria-labelledby="mission-title">
    <p class="eyebrow">Mission</p>
    <h2 id="mission-title">AI should make people more capable, not more dependent.</h2>
    <p>Augment Humankind is being built as a solo AI consulting practice for teams that need useful adoption, clear strategy, and practical workflows without enterprise theatre.</p>
</section>',
        1
    ),
    (
        (SELECT id FROM pages WHERE slug = 'home'),
        NULL,
        '<section class="service-preview" aria-labelledby="service-preview-title">
    <div class="section-heading">
        <p class="eyebrow">Three ways in</p>
        <h2 id="service-preview-title">Start with clarity, build one useful thing, then transfer the capability.</h2>
    </div>
    <div class="service-strip">
        <article class="service-card">
            <span class="service-number">01</span>
            <h3>AI Strategy Fieldguide</h3>
            <p>A short engagement for nontechnical teams that need clarity before adopting new AI tools.</p>
        </article>
        <article class="service-card">
            <span class="service-number">02</span>
            <h3>Workflow Prototype Build</h3>
            <p>A focused build around one useful workflow, kept small enough to test and maintain.</p>
        </article>
        <article class="service-card">
            <span class="service-number">03</span>
            <h3>Team Capability Transfer</h3>
            <p>Guided practice that helps people keep using AI well after the first strategy or build engagement.</p>
        </article>
    </div>
    <a class="text-link" href="/services">See the full service fieldguide</a>
</section>',
        2
    ),
    (
        (SELECT id FROM pages WHERE slug = 'home'),
        NULL,
        '<section class="proof-grid" aria-labelledby="method-title">
    <div>
        <p class="eyebrow">Operating method</p>
        <h2 id="method-title">Small promises, visible learning, practical transfer.</h2>
    </div>
    <ul class="method-list">
        <li><strong>Bounded engagements.</strong> Work is shaped so a one-person practice can deliver it without pretending to be a large agency.</li>
        <li><strong>Human review.</strong> AI-generated prose, analysis, and artifacts are treated as drafts until a person owns the final judgment.</li>
        <li><strong>Open learning path.</strong> Capability grows through active study and practice, including public resources such as IBM SkillsBuild, Coursera, and DataCamp.</li>
    </ul>
</section>',
        3
    );
