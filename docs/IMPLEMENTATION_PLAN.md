# BrainSprint implementation plan

Approved scope: `ARCHITECTURE.md`. The coordinating chat owns this file; executors report status in their task brief and completion summary.

## Current state

- Approved reduced scope and final UX/analytics clarifications are documented.
- Local repository and initial structure are committed; the private remote is https://github.com/voprosovnety/brainsprint.
- T1 is accepted at `ebacae1` after coordinator inspection of ownership, transactional persistence, retries, configuration pinning, test coverage, and the executor's actual verification record. Its independent chat is `01a1536f-fe7a-7b93-8138-ff3c9bdfe00a`.
- T2 is accepted at `cf4e385` after coordinator inspection of server progression/answer validation and the executor's verification/review record. Its chat is `01a15380-b09f-7ab1-aec2-796ea08f30ad`.
- Full local funnel: http://127.0.0.1:3000 (web), http://127.0.0.1:8008/up (API); PostgreSQL is loopback-only on port 55432. Startup/shutdown instructions are in README.
- T2 verification: eleven PostgreSQL tests / 229 assertions, real-cookie HTTP checks, production build, TypeScript, mobile/desktop browser checks including both branches, history, result stability and outage/retry. Independent review findings were fixed. Physical touch and OS-level reduced-motion emulation remain release checks; unchanged successful checks were not repeated by the coordinator.
- Hosted T1 CI run 39230710389 failed because PHPUnit referenced an empty untracked Unit directory. Coordinator reproduced the exact failure, removed the unused suite at `9b9aa61`, and confirmed all eleven tests are discovered without that directory. Updated code is pushed; hosted outcome remains pending.
- T3 is ready for an independent executor; see `tasks/T3_ANALYTICS_EXPERIMENT.md`.
- No deployment exists.
- No paid service activation or public publication is authorized.

## MVP

| ID | Task | Depends on | Status | Completion criteria |
| --- | --- | --- | --- | --- |
| T0 | Repository foundation and task organization | Approval | Complete | Initial commit, private remote, documents/contracts, first independent executor dispatched |
| T1 | First working vertical slice | T0 | Complete | Accepted local slice and verification at `ebacae1`; clean-checkout PHPUnit discovery fixed at `9b9aa61` |
| T2 | Complete configurable funnel | T1 | Complete | Accepted full funnel at `cf4e385`; eleven PostgreSQL tests, HTTP/browser verification, independent review fixes; no development-data reset |
| T3 | Events, UTM, and landing A/B test | T2 contracts | Ready | Stable pre-render assignment, actual exposures, deduplicated server/client events, correct attribution and conversion denominators; shared aggregate service |
| T4 | Filament and compact public demo analytics | T3 | Planned | Protected admin reads, useful real metrics, synthetic-only public aggregates using T3 service; no public administrative credentials |
| T5 | Integration and MVP release preparation | T1–T4 | Planned | Relevant complete checks, independent review of complex changes, visual fixes, current docs, deploy configuration and cost controls; public launch only after approval |

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

Dispatch T3 in a fresh independent chat, then review its results and prepare T4. Preserve existing runs, immutable configurations, and synthetic isolation. Confirm corrected hosted CI before release acceptance.
