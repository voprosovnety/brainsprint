# T1 — first working vertical slice

Status: implemented and locally verified; ready for coordinator integration.

## Goal

Build the smallest attractive, real BrainSprint slice: landing → short Memory interaction → Laravel API → PostgreSQL → persisted illustrative result. This is an incremental slice of the one approved funnel, not a second product or alternate funnel.

## Read first

- Root `AGENTS.md`.
- `docs/ARCHITECTURE.md`, especially persistence, UX, and boundaries.
- `contracts/README.md` for the initial cross-application contract.
- `README.md` for actual current state.

No need to read previous chats or unrelated projects.

## Ownership and inputs

The executor owns `apps/web`, `apps/api`, `infra`, executable contracts, dependency manifests, local startup tooling, relevant CI, this task brief, and updates needed to README/AGENTS/architecture. The coordinator alone owns `docs/IMPLEMENTATION_PLAN.md`. There are no other implementation writers at dispatch time.

Use the approved stack and the `frontend-design` skill. Check actual stable compatibility from official documentation before installing dependencies. Known environment at dispatch: Node 24.8.0, npm, pnpm, PHP 8.5.3, Docker CLI available; Composer is not on PATH. Docker daemon availability is unverified. Prefer project-local or containerized tooling; do not modify global tools or neighboring projects without necessity and authorization.

## Scope

1. Bootstrap Next.js and Laravel in the existing application directories without overwriting root planning files. Pin dependencies and create safe environment examples.
2. Provide a reproducible local PostgreSQL-backed environment and concise exact startup/shutdown/migration commands. Do not silently replace PostgreSQL with SQLite. Add a small relevant GitHub Actions workflow once its commands exist.
3. Implement a minimal typed configuration from Laravel, pinned to the run. An initial Memory-only configuration is sufficient for this slice.
4. Implement database-backed anonymous sessions through the frontend origin, ownership checks, and CSRF protection. Do not add consumer accounts.
5. Implement start/resume, one visual Memory round, transactional completion, and one-time server-created demo result. Duplicate requests do not create duplicate active runs/results. Refresh restores confirmed progress.
6. Build a distinctive, coherent landing and challenge/result interface with clear illustrative-result labeling, touch/keyboard interaction, reduced motion, loading/saving/error states, and useful desktop adaptation. The final paywall-boundary screen belongs to T2; do not create a technical-looking temporary checkout.
7. Verify the actual UI during implementation, not only at the end. Use mobile and desktop browser sizes. Check API/persistence against PostgreSQL with a focused set of meaningful tests.
8. Update actual setup, environment, commands, implemented feature status, and limitations in README. Update AGENTS current state and architecture only if needed. Commit the completed slice when acceptance criteria are met; otherwise commit useful non-misleading progress and clearly report the blocker.

## Out of scope

No Stripe, payments, subscriptions, scoring engine, Reaction Speed, recovery codes, post-purchase product, Redis, workers, PostHog, public deployment, paid resources, full analytics panel, or complete T2 implementation. Filament and A/B analytics are later tasks; preserve room for them without building them now.

## Definition of done

- A reviewer can start the documented local environment and use the actual slice end to end.
- Laravel/PostgreSQL really store the answer, current step, pinned configuration version, and illustrative result.
- Refresh restores confirmed progress; repeated start/complete calls do not duplicate state.
- A separate anonymous session cannot read or modify another run.
- Result values remain unchanged after reload and are explicitly unrelated to cognitive measurement or answer correctness.
- Actual mobile and desktop browser interaction has been checked; identified visual problems are fixed.
- Relevant backend checks and frontend build/type checks actually ran, with results reported truthfully. Do not run broad repetitive checks.
- Examples contain no real credentials. No secrets, generated runtime data, or unnecessary evidence artifacts are committed.
- README, relevant project instructions, and this brief reflect actual implementation. A clear local commit exists.

## Completion report

Report briefly in Russian: implementation summary, commit SHA, changed areas, actual tests/browser checks, current local URLs if still running, blockers/limitations, and recommended next task. Do not mark the coordinator's plan complete or publish/deploy. The Product Owner authorizes sending this result to the coordinating chat when a reliable chat identifier is available; otherwise leave the final summary in this chat for coordinator retrieval.

## T1 delivery notes

- Implemented Next.js/React/TypeScript/Tailwind presentation and Laravel 13/PostgreSQL persistence, using committed dependency lockfiles and project-local Composer.
- Four consumer routes, normal database sessions/CSRF, owner-only access, session-scoped rate limits, pinned content-addressed configuration, idempotent start/completion, and transactional milestones/results are implemented.
- Landing, one Memory round, and explicitly illustrative result work at mobile and desktop sizes. Refresh restores confirmed state; unfinished reveals restart with clear copy. Result values remain unchanged after reload or a temporary API outage.
- Actual checks: 5 PostgreSQL feature tests / 53 assertions; real same-origin HTTP cookies/CSRF/origin/ownership/retry/rate-limit check; Next.js production build and TypeScript; real browser at 390×844 and 1440×1000, with keyboard/click selection, reload, and unavailable-API retry.
- Independent review found shared proxy-IP throttling; replaced with a server-session limiter and verified that exhausting one session leaves another usable.
- Local services: web `http://127.0.0.1:3000`, API `http://127.0.0.1:8008`, PostgreSQL loopback port 55432. Exact setup/shutdown commands are in README.
- Limitations: one retained run per anonymous session; no new-attempt flow, other mechanics, onboarding, boundary screen, A/B assignment, analytics, Filament, or deployment. Development HTTP overlap checks do not constitute a multi-worker load test. GitHub Actions workflow is added but a hosted run has not been observed.
- The coordinating implementation plan was not edited by this executor. Subsequent product work belongs to T2 after coordinator review.
