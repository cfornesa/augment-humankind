# docs/

Reference documentation for the CMS. The authoritative
[Documentation Map](../README.md#documentation-map) in the project README
explains how all the docs fit together; this folder holds the detailed
references:

- **[api.md](api.md)** — request/response contract for every public and admin
  route, plus feature flags, forms, comments, syndication, and cron.
- **[dependencies.md](dependencies.md)** — external dependencies: data sent
  off-domain, failure modes, config keys, and self-hosting alternatives.
- **[decisions-archive.md](decisions-archive.md)** — append-only archive of
  session decision logs prior to the current month (the archived tail of
  [`../DECISIONS.md`](../DECISIONS.md)).
- **[migrations/](migrations/)** — dated `.sql` files, the documentation of
  record for each schema change (applied by `scripts/setup-database.php`; see
  [SETUP.md](../SETUP.md)).

The generative-art algorithms write-up lives at the repo root:
[../ALGORITHMS.md](../ALGORITHMS.md). Its rendered `ALGORITHMS.pdf` is a
build artifact published as a GitHub Release asset (tagged
`algorithms-latest`) rather than committed to the tree. The
[publish-algorithms-pdf.yml](../.github/workflows/publish-algorithms-pdf.yml)
workflow rebuilds and re-publishes the PDF automatically whenever
`ALGORITHMS.md` or `diagrams/` change on `main`. To trigger a rebuild
manually, use the "Run workflow" button on the Actions tab.
