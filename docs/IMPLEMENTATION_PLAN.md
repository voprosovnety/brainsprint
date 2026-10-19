# BrainSprint implementation plan

Approved scope: `ARCHITECTURE.md`. The coordinating chat owns this file; executors report status in their task brief and completion summary.

## Current state

- The full local funnel, stable landing experiment, events, protected Filament, and compact shared-service synthetic analytics are implemented. Accepted commits are recorded below.
- Private source: https://github.com/voprosovnety/brainsprint. Local web: http://127.0.0.1:3000; demo: /demo-analytics; admin: http://127.0.0.1:8008/admin; PostgreSQL loopback: 55432. Check service state and README startup instructions before use.
- T4 is accepted locally at `785bcbb` after coordinator inspection of admin session/auth/policies, hard-coded public Synthetic scope, shared metrics, and delivery evidence. Executor chat: `01a154b2-9b80-7970-949c-7677fe049ecf`.
- T4 evidence: 36 PostgreSQL tests / 545 assertions in its main run plus focused added regressions; build/typecheck, both HTTP checks, real browser at 1280×720 and 390×844, and independent security review. Existing consumer history remains preserved. Hosted T3 run 39274872105 passed; hosted T4 run 39279279150 is queued and not yet confirmed successful.
- Local admin credentials exist only in ignored `.local/admin-access.json` with mode 0600. Never copy their content into tracked documents or chat output.
- T5 is dispatched to independent local chat `01a154ce-e3ce-7ff3-a0b2-3565600d8a10`: release lifecycle, packaging and final local integration checks. Physical touch remains unverified; effective reduced-motion preference should be checked with available browser tooling.
- No public deployment exists. Paid service activation and public publication still require explicit approval after a concrete release-ready result.

## MVP

| ID | Task | Depends on | Status | Completion criteria |
| --- | --- | --- | --- | --- |
| T0 | Repository foundation and task organization | Approval | Complete | Initial commit, private remote, documents/contracts, first independent executor dispatched |
| T1 | First working vertical slice | T0 | Complete | Accepted local slice and verification at `ebacae1`; clean-checkout PHPUnit discovery fixed at `9b9aa61` |
| T2 | Complete configurable funnel | T1 | Complete | Accepted full funnel at `cf4e385`; eleven PostgreSQL tests, HTTP/browser verification, independent review fixes; no development-data reset |
| T3 | Events, UTM, and landing A/B test | T2 contracts | Complete | Accepted at `8236371`; definitions in `docs/ANALYTICS.md`; hosted run 39274872105 passed |
| T4 | Filament and compact public demo analytics | T3 | Complete | Accepted locally at `785bcbb`; protected read-only admin, shared aggregate service, synthetic-only preview and repeatable seed; hosted CI pending |
| T5 | Integration and MVP release preparation | T1–T4 | Dispatched | Relevant complete checks, independent review of changed release/lifecycle code, visual fixes, current docs, deploy configuration and cost controls; public launch only after approval |

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

Retrieve T5 progress/completion and assess its local release criteria; confirm hosted T4 checks. Present the concrete release candidate and required publication/cost/account approvals to the Product Owner. Publication and infrastructure provisioning remain a separate gate; do not mark the whole project publicly launched before they happen.
