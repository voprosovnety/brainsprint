# BrainSprint

## Product and scope
- One English, mobile-first cognitive challenge funnel: landing → onboarding → Visual Memory / Pattern Recognition / Logical Reasoning → illustrative result → paywall boundary.
- The final screen must feel like a finished consumer experience. Its CTA honestly completes the demo; it never simulates checkout.
- No payments, subscriptions, accounts, recovery codes, Reaction Speed, scoring engine, post-purchase reports, or training product.
- Demo indicators are generated once on the server, persisted, and clearly described as unrelated to measured cognitive ability or answer correctness.

## Stack and architecture
- Next.js App Router, React, TypeScript, Tailwind CSS in `apps/web/`.
- PHP, Laravel, PostgreSQL, Filament in `apps/api/`.
- Laravel owns configuration, progression, persistence, result creation, experiment assignment, events, and metrics. Next.js owns presentation and proxies consumer API calls through the frontend origin.
- Use one configurable funnel with immutable published versions pinned to runs. Keep transition rules small and explicit.
- Use normal database-backed anonymous Laravel sessions. Do not introduce custom identity or synchronization platforms.
- Prefer three domain tables: funnel versions, runs (including answers/result), and events; framework session/admin tables are additional.
- Filament and the compact public synthetic analytics view share one metric calculation service and data definitions. Public responses contain aggregates only.
- No Redis, persistent workers, queues, PostHog, or additional services without a concrete approved need.

## Working rules
- Read only task-relevant files. Follow `docs/IMPLEMENTATION_PLAN.md` and the assigned task brief.
- Significant implementation tasks belong in independent Codex chats. The coordinating chat owns integration and the implementation plan; do not edit that plan from an executor chat.
- A single executor owns each shared contract, dependency manifest, and migration set. Use worktrees when parallel edits are needed.
- Confirm current stable package compatibility from official sources before installation. Commit lockfiles; do not use prereleases or unpinned Docker `latest` tags.
- Keep documentation and UI English; communicate with the Product Owner in Russian.
- Update existing documentation when behavior changes. Do not describe planned features as implemented.

## API, database, and safety
- Read `contracts/README.md` before changing the consumer contract. Keep server validation and frontend types aligned.
- Derive ownership and experiment assignment from the server session; public IDs alone never authorize access.
- Validate inputs, protect cookie writes against CSRF, rate-limit public endpoints, and return actionable errors without debug details.
- Persist a step and its completion event transactionally. Duplicate submissions must not duplicate milestones or results.
- Never commit credentials, real `.env` files, session secrets, or raw cookies. Examples contain placeholders only.
- Keep synthetic data explicitly marked and excluded from ordinary analytics. Never expose administrative credentials publicly.
- No paid service activation or public publication without Product Owner approval. A private GitHub repository is authorized.

## Verification
- Targeted automated checks and browser checks are explicitly authorized and required for this project.
- Inspect every significant new screen in a real browser at mobile and desktop sizes during implementation. Fix typography, layout, motion, and interaction issues before moving on.
- Test persistence, transitions, ownership, event deduplication, and stable A/B assignment where affected. Run the necessary full checks at major milestones, not after every edit.
- Use independent review for complex changes. Report what actually ran and disclose blocked verification; never mark unmet acceptance criteria complete.
- Do not retain screenshots, traces, or large logs unless they diagnose a specific issue or are requested deliverables.

## Current state and references
- Foundation and approved planning documents exist; application implementation has not started.
- `README.md`: actual setup and feature status.
- `docs/ARCHITECTURE.md`: architecture and scope decisions.
- `docs/IMPLEMENTATION_PLAN.md`: current task status and dependencies.
- `docs/tasks/T1_VERTICAL_SLICE.md`: first executor brief.
