# BrainSprint implementation plan

Approved scope: `ARCHITECTURE.md`. The coordinating chat owns this file; executors report status in their task brief and completion summary.

## Current state

- The full local funnel, stable landing experiment, events, protected Filament, compact shared-service synthetic analytics, anonymous deletion/retention and production package are implemented. Accepted commits are recorded below.
- Source: https://github.com/voprosovnety/brainsprint. GitHub API reports `private=false` on 2026-10-20; this deployment task has not changed repository visibility. Local web: http://127.0.0.1:3000; demo: /demo-analytics; admin: http://127.0.0.1:8008/admin; PostgreSQL loopback: 55432. Check service state and README startup instructions before use.
- Release candidate `7361261` is accepted after local coordinator inspection of ownership/lifecycle transactions, retention scope, deliberate admin provisioning and deployment packaging, executor evidence, and successful hosted run 39354228417. Its independent chat is `01a154ce-e3ce-7ff3-a0b2-3565600d8a10`.
- Local release evidence: 40 existing PostgreSQL cases and eight distinct release cases across the initial sweep/focused regressions; build/typecheck, HTTP session/CSRF/ownership/admin checks, real browser at 390×844 and 1280×720, effective reduced motion, independently reviewed fixes, and a built production Apache/PHP image checked against a disposable isolated PostgreSQL. Existing development history was preserved.
- Hosted T3 run 39274872105 and T4 run 39279279150 passed. Real provider ingress/TLS, backup restore and recurring maintenance remain external checks; physical touch and additional browser engines are unverified.
- Local admin credentials exist only in ignored `.local/admin-access.json` with mode 0600. Never copy their content into tracked documents or chat output.
- Deployment/environment/admin/backup/maintenance procedures are in `docs/DEPLOYMENT.md`. Permanent $0 publication is explicitly authorized; no paid plan, card linking, trials or keep-alive traffic is authorized.
- Actual providers: Vercel Hobby and Neon Free, no card. Frontend: https://brainsprint-voprosovnety.vercel.app ; analytics: https://brainsprint-voprosovnety.vercel.app/demo-analytics ; backend/admin: https://brainsprint-api-voprosovnety.vercel.app/admin . First CLI deployments serve the production demo. PostgreSQL 17 is a new Neon Frankfurt database `brainsprint`; existing local history is untouched.
- Neon configuration/sample migrations, append-only publication, 18 labeled synthetic runs and secret-file administrator provisioning succeeded. Initial hosted initialize/provision runs: 39407588710 / 39408074306. Private release credentials are only in ignored mode-0600 `.local/release-admin-access.json`; do not copy their contents into chat or tracked documents.
- Render Hobby registration succeeded, but actual Free service creation required credit-card verification; no service/card was created. serv00 form was prepared but not submitted because its terms prohibit automated account creation; this provider is unnecessary. Vercel Container Runtime was excluded because no permanent free VCR image-storage allowance was established.
- Backend uses pinned `vercel-php@0.9.0`, PHP 8.5, ordinary per-request Laravel and generated Filament assets. The runtime's old libpq lacks SNI; explicit Neon `PGOPTIONS=endpoint=...` resolved the public database error while keeping `sslmode=verify-full`. PostgreSQL shared cache/locks and bounded cache maintenance support function instances.
- Public HTTP acceptance passed: full funnel and illustrative result, secure host-only HttpOnly cookies, CSRF/origin rejection, ownership, stable A/B assignment, concurrent completion deduplication, saved restoration and deletion of only the newly created acceptance visitors. `/up`, generated Filament CSS and protected direct API behavior passed; source/environment paths returned 404. Mobile landing was visually inspected at 390×844; full browser interaction and desktop/admin review remain open.
- Additional local checks passed: shared-cache/retention 3 tests / 18 assertions, frontend typecheck; prior cold-start frontend build and production Docker package checks passed. Tests use only `brainsprint_test`, never Neon. Public acceptance uses explicit disposable visitors, not a production test suite.
- An authenticated encrypted 32,722-byte database backup was created and restored into newly created local `brainsprint_restore_check_20261020`, validating 1 configuration, 18 synthetic runs and 1 administrator. Tampering was rejected; the temporary database/plaintext were removed. Private recovery key: ignored mode-0600 `.local/release-backup-key.pem`; owner must retain an independent private recovery copy.
- Official Vercel GitHub App access was explicitly approved only for `voprosovnety/brainsprint` and installed. Both Vercel projects are linked; root directories and Frankfurt region are set. Production tracks `codex/release-api` and `codex/release-web`; other Git branches are disabled in each project configuration. Commit `b775b93` passed hosted Funnel checks 39471668268. Exact-commit publication validation remains open; hosted daily maintenance succeeded as 39471882792.
- Prepared maintenance: daily 03:23 UTC / 10:23 Vietnam, encrypted artifacts retained three days, 20 MiB dump cap and 100 MiB artifact guard, backup-before-retention, bounded expired cache/session/visitor cleanup and idempotent synthetic sample refresh. Hosted execution 39471882792 passed, including PostgreSQL 17 dump encryption/upload and bounded maintenance. GitHub schedules can disable after 60 days without repository activity; no fake keep-alive will be added.
- Independent review found that revision-only backend confirmation could promote a broken API; the publisher now also requires healthy Laravel `/up` and database-backed `/admin/login`. The review stopped on an account usage limit; no additional reviewer was spawned. The final implementation diff and configured-secret exclusion were inspected before commit; hosted package checks passed.

## MVP

| ID | Task | Depends on | Status | Completion criteria |
| --- | --- | --- | --- | --- |
| T0 | Repository foundation and task organization | Approval | Complete | Initial commit, private remote, documents/contracts, first independent executor dispatched |
| T1 | First working vertical slice | T0 | Complete | Accepted local slice and verification at `ebacae1`; clean-checkout PHPUnit discovery fixed at `9b9aa61` |
| T2 | Complete configurable funnel | T1 | Complete | Accepted full funnel at `cf4e385`; eleven PostgreSQL tests, HTTP/browser verification, independent review fixes; no development-data reset |
| T3 | Events, UTM, and landing A/B test | T2 contracts | Complete | Accepted at `8236371`; definitions in `docs/ANALYTICS.md`; hosted run 39274872105 passed |
| T4 | Filament and compact public demo analytics | T3 | Complete | Accepted at `785bcbb`; protected read-only admin, shared aggregate service, synthetic-only preview and repeatable seed; hosted run 39279279150 passed |
| T5 | Integration and MVP release preparation | T1–T4 | Complete (local release ready) | Accepted candidate `7361261`, isolated production package checks and hosted run 39354228417 passed; public deployment remains separately gated |

T1 intentionally implements only one temporary slice of the single BrainSprint configuration, not an alternative funnel. T2 expands that configuration into the full experience.

## Polish

After MVP functionality: refine typography, transitions, accessibility, pacing, responsive composition, and measured load performance. These improvements do not postpone mandatory screen-by-screen browser review or excuse weak MVP UX.

## Advanced

Optional only after a finished MVP: PostHog integration if it materially improves the demonstration, or limited configuration-text editing. No extra products or speculative platforms.

## Execution protocol

1. Coordinator establishes task boundaries and shared contracts.
2. A significant task runs in an independent Codex chat with only its brief and relevant documents. Do not fork full chat history by default.
3. The initial executor uses the project checkout exclusively; use worktrees for genuinely independent concurrent edits later.
4. Executors may implement, install compatible dependencies, run relevant checks, fix issues, and commit within assigned scope. No paid services, unrelated files, or destructive operations. The dedicated free-deployment task may publish the demo within its explicit $0 authorization.
5. Each executor updates its task brief, relevant docs, and commit; returns what changed, actual checks, limitations, and next step. Coordinator independently assesses acceptance criteria and integrates.
6. Failed acceptance criteria remain open. A successful build or executor message alone is not completion.

## Immediate next action

The reviewed Vercel package is committed and production branches are configured. Enable and verify a successful checked-commit backend-first GitHub publication and hosted maintenance backup. Complete real public desktop/mobile interactions, analytics and authenticated read-only Filament review; disclose any remaining provider/network limitations. Preserve all development data and repository visibility.
