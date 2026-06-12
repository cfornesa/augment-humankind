-- Phase 2: seed the "Services" and "Field Notes" pages with the current
-- static content from public/index.php, so /services and /notes become
-- admin-editable via /admin/pages while rendering identically to today.
--
-- Each page gets a single page_sections row with heading = NULL, whose
-- content is the full set of <section> blocks currently hardcoded in
-- public/index.php. app/views/managed_page.php renders heading-less
-- sections raw (unwrapped), preserving the existing markup/classes exactly.

INSERT INTO pages
    (title, slug, status, template, nav_label, show_in_nav, meta_title, meta_description, sort_order)
VALUES
    ('Services', 'services', 'published', 'standard', 'Services', 1, NULL,
     'Three focused AI consulting services for nontechnical teams: strategy, prototype builds, and capability transfer.',
     1),
    ('Field Notes', 'notes', 'published', 'standard', 'Field Notes', 1, NULL,
     'Learning notes and practical observations from building useful AI workflows.',
     2);

INSERT INTO page_sections (page_id, heading, content, sort_order)
VALUES
    (
        (SELECT id FROM pages WHERE slug = 'services'),
        NULL,
        '<section class="page-hero" aria-labelledby="services-title">
    <p class="eyebrow">Services</p>
    <h1 id="services-title">Three focused offers for teams that want AI to become usable.</h1>
    <p>Each service is narrow enough for a solo consultant to deliver well and concrete enough for nontechnical teams to understand what happens next.</p>
</section>

<section class="services-detail" aria-label="Service offerings">
    <article class="service-detail">
        <div class="service-kicker">
            <span>01</span>
            <h2>AI Strategy Fieldguide</h2>
        </div>
        <p class="service-summary">A short engagement for nontechnical teams that need clarity before adopting new AI tools.</p>
        <p><strong>Best for:</strong> Teams with curiosity, scattered ideas, and no shared map yet.</p>
        <div>
            <h3>Typical deliverables</h3>
            <ul class="deliverable-list">
                <li>Opportunity map</li>
                <li>Use-case shortlist</li>
                <li>Risk boundaries</li>
                <li>Practical adoption roadmap</li>
            </ul>
        </div>
    </article>
    <article class="service-detail">
        <div class="service-kicker">
            <span>02</span>
            <h2>Workflow Prototype Build</h2>
        </div>
        <p class="service-summary">A focused build around one useful workflow, kept small enough to test and maintain.</p>
        <p><strong>Best for:</strong> Teams that have one clear problem and need a working first version.</p>
        <div>
            <h3>Typical deliverables</h3>
            <ul class="deliverable-list">
                <li>Lightweight prototype</li>
                <li>Workflow documentation</li>
                <li>Handoff notes</li>
                <li>Maintainability guidance</li>
            </ul>
        </div>
    </article>
    <article class="service-detail">
        <div class="service-kicker">
            <span>03</span>
            <h2>Team Capability Transfer</h2>
        </div>
        <p class="service-summary">Guided practice that helps people keep using AI well after the first strategy or build engagement.</p>
        <p><strong>Best for:</strong> Teams that need confidence, shared norms, and repeatable habits.</p>
        <div>
            <h3>Typical deliverables</h3>
            <ul class="deliverable-list">
                <li>Team playbooks</li>
                <li>Prompt and workflow examples</li>
                <li>Office-hour style guidance</li>
                <li>Learning paths informed by public resources</li>
            </ul>
        </div>
    </article>
</section>

<section class="callout" aria-labelledby="service-boundary-title">
    <h2 id="service-boundary-title">What this is not</h2>
    <p>This is not a promise to automate everything, replace staff, or drop a tool into a team without changing the surrounding work. The offer is disciplined augmentation: identify where AI helps, build only what is useful, and teach the team how to keep judgment in the loop.</p>
    <a class="button button-primary" href="/contact">Discuss a focused project</a>
</section>',
        0
    ),
    (
        (SELECT id FROM pages WHERE slug = 'notes'),
        NULL,
        '<section class="page-hero notes-hero" aria-labelledby="notes-title">
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
</section>',
        0
    );
