# Augment Humankind

A small no-framework PHP site for `augmenthumankind.com`.

## Direction

Augment Humankind is a mission-first AI consulting practice for nontechnical
teams. The v1 site uses a Fieldguide position: practical, candid, and visibly
friendly, with `Friendly Guide.png` serving as the primary mascot signal.

The business promise is disciplined augmentation: help teams use AI to extend
their capabilities without overclaiming what a one-person practice can deliver.

## Services

- **AI Strategy Fieldguide** — clarify useful AI opportunities before adopting tools.
- **Workflow Prototype Build** — build one focused, maintainable AI-assisted workflow.
- **Team Capability Transfer** — help teams keep using AI well through playbooks, examples, and guided practice.

## Routes

- `/` — mission-led homepage
- `/services` — three focused service offers
- `/notes` — lightweight field notes landing page
- `/contact` — email-based inquiry page

## Run Locally

```sh
php -S 127.0.0.1:8080 -t public public/index.php
```

Then open:

- `http://127.0.0.1:8080/`
- `http://127.0.0.1:8080/services`
- `http://127.0.0.1:8080/notes`
- `http://127.0.0.1:8080/contact`

## Notes

- No database is required.
- No external services or vendor dependencies are used.
- `/contact` uses an email link for v1; a real backend contact form is a future decision.
- Public routes are treated as durable. If they move later, add permanent redirects.
