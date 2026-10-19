# BrainSprint implementation plan

Approved scope: `ARCHITECTURE.md`. The coordinating chat owns this file; executors report status in their task brief and completion summary.

## Current state

- Approved reduced scope and final UX/analytics clarifications are documented.
- Local repository and initial structure exist.
- No application, dependencies, local services, or deployment exist yet.
- No paid service activation or public publication is authorized.

## MVP

| ID | Task | Depends on | Status | Completion criteria |
| --- | --- | --- | --- | --- |
| T0 | Repository foundation and task organization | Approval | In progress | Initial commit, private remote, documents/contracts, first independent executor dispatched |
| T1 | First working vertical slice | T0 | Ready | Real landing → Memory → Laravel → PostgreSQL → stable demo result; refresh restoration; mobile/desktop browser checks and focused backend verification; reproducible setup and minimal CI |
| T2 | Complete configurable funnel | T1 | Planned | Three interactive mechanics, one conditional tutorial branch, pinned versions, navigation, save/error states, polished result and boundary; browser checks during development |
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

Dispatch T1 using `tasks/T1_VERTICAL_SLICE.md`. After T1 acceptance, prepare and dispatch T2; do not wait for the Product Owner to invent the next task.
