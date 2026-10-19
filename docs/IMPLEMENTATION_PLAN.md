# BrainSprint implementation plan

Approved scope: `ARCHITECTURE.md`. The coordinating chat owns this file; executors report status in their task brief and completion summary.

## Current state

- Approved reduced scope and final UX/analytics clarifications are documented.
- Local repository and initial structure are committed; the private remote is https://github.com/voprosovnety/brainsprint.
- T1 is accepted at `ebacae1` after coordinator inspection of ownership, transactional persistence, retries, configuration pinning, test coverage, and the executor's actual verification record. Its independent chat is `01a1536f-fe7a-7b93-8138-ff3c9bdfe00a`.
- Local slice: http://127.0.0.1:3000 (web), http://127.0.0.1:8008/up (API); PostgreSQL is loopback-only on port 55432. Startup/shutdown instructions are in README.
- T1 verification: five PostgreSQL feature tests / 53 assertions, real-cookie HTTP checks, Next production build, TypeScript, mobile/desktop browser interaction and outage recovery. Checks were not repeated unchanged by the coordinator. Hosted CI has not yet been confirmed.
- T2 is ready for an independent executor; see `tasks/T2_COMPLETE_FUNNEL.md`.
- No deployment exists.
- No paid service activation or public publication is authorized.

## MVP

| ID | Task | Depends on | Status | Completion criteria |
| --- | --- | --- | --- | --- |
| T0 | Repository foundation and task organization | Approval | Complete | Initial commit, private remote, documents/contracts, first independent executor dispatched |
| T1 | First working vertical slice | T0 | Complete | Accepted local slice and verification at `ebacae1`; hosted CI confirmation remains a release follow-up |
| T2 | Complete configurable funnel | T1 | Ready | Three interactive mechanics, one conditional tutorial branch, pinned versions, navigation, save/error states, polished result and boundary; browser checks during development |
| T3 | Events, UTM, and landing A/B test | T2 contracts | Planned | Stable pre-render assignment, actual exposures, deduplicated server/client events, correct attribution and conversion denominators; shared aggregate service |
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

Dispatch T2 from its task brief with a fresh context and exclusive implementation ownership. Retrieve its result, assess acceptance criteria, then prepare T3. Preserve existing T1 runs and do not reset the development database for convenience.
