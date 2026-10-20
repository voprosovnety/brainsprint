# BrainSprint implementation plan

Approved scope: `ARCHITECTURE.md`. The coordinating chat owns this file; executors report status in their task brief and completion summary.

## Current state

- The full local funnel, stable landing experiment, events, protected Filament, compact shared-service synthetic analytics, anonymous deletion/retention and production package are implemented. Accepted commits are recorded below.
- Private source: https://github.com/voprosovnety/brainsprint. Local web: http://127.0.0.1:3000; demo: /demo-analytics; admin: http://127.0.0.1:8008/admin; PostgreSQL loopback: 55432. Check service state and README startup instructions before use.
- Release candidate `7361261` is accepted after local coordinator inspection of ownership/lifecycle transactions, retention scope, deliberate admin provisioning and deployment packaging, executor evidence, and successful hosted run 39354228417. Its independent chat is `01a154ce-e3ce-7ff3-a0b2-3565600d8a10`.
- Local release evidence: 40 existing PostgreSQL cases and eight distinct release cases across the initial sweep/focused regressions; build/typecheck, HTTP session/CSRF/ownership/admin checks, real browser at 390×844 and 1280×720, effective reduced motion, independently reviewed fixes, and a built production Apache/PHP image checked against a disposable isolated PostgreSQL. Existing development history was preserved.
- Hosted T3 run 39274872105 and T4 run 39279279150 passed. Real provider ingress/TLS, backup restore and recurring maintenance remain external checks; physical touch and additional browser engines are unverified.
- Local admin credentials exist only in ignored `.local/admin-access.json` with mode 0600. Never copy their content into tracked documents or chat output.
- Deployment/environment/admin/backup/maintenance instructions are in `docs/DEPLOYMENT.md`. Retention previews by default; actual daily execution and cloud resources have not been configured.
- No public deployment exists. Paid service activation and public publication still require explicit approval after a concrete release-ready result.

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
4. Executors may implement, install compatible dependencies, run relevant checks, fix issues, and commit within assigned scope. No paid services, public publication, unrelated files, or destructive operations.
5. Each executor updates its task brief, relevant docs, and commit; returns what changed, actual checks, limitations, and next step. Coordinator independently assesses acceptance criteria and integrates.
6. Failed acceptance criteria remain open. A successful build or executor message alone is not completion.

## Immediate next action

Await explicit public-deployment/Railway-budget approval for the accepted local release candidate. No further feature task is needed for this MVP. After approval, establish authorized provider access, configure runtime secrets/origins, provision within the approved budget, set backup/maintenance policies, deploy, and verify real HTTPS/ingress/cookie behavior before claiming a public launch. Keep the source private unless separately authorized otherwise.
