# BrainSprint architecture

Status: approved baseline, 2026-10-19. Implementation status lives in `IMPLEMENTATION_PLAN.md`.

## Scope

One English funnel taking approximately 3–4 minutes: landing, onboarding, Visual Memory, Pattern Recognition, Logical Reasoning, demo result, paywall boundary. All screens form one coherent mobile-first consumer experience with a deliberate desktop layout.

The final screen is a designed conclusion, not a technical placeholder. `Finish demo` must actually finish the run. No payment, subscription, fake checkout, paid access, training section, scoring engine, Reaction Speed, or second funnel is included.

## Components and boundaries

- `apps/web`: Next.js App Router, React, TypeScript, Tailwind; client interaction, local animation, screen navigation, and same-origin API proxy.
- `apps/api`: PHP/Laravel; progression, configuration, session ownership, persistence, validation, result generation, experiments, events, and aggregate metrics.
- PostgreSQL: canonical state, including framework-backed sessions.
- Filament: authenticated administrative reads and real metrics.
- Public demo analytics: a compact aggregate-only view using the same analytics service as Filament over explicitly synthetic data. Do not build a second analytics engine or expose session/answer details.

Start without Redis, workers, queues, PostHog, microservices, or a custom identity system. Verify current stable framework compatibility before installation and pin exact resolved dependencies in lockfiles.

## Funnel configuration

One versioned configuration contains stable screen IDs, existing screen types, content, enabled screens, challenge parameters, allowed presentation variants, and next-step rules. Support a default next screen plus a simple answer-based conditional branch; arbitrary expressions and executable configuration are unnecessary.

Publish immutable versions and pin each new run to one version. Deliver public content and challenge payloads through Laravel. Configuration changes affect new runs only. Store the initial version source in the repository and publish it reproducibly. Filament configuration editing is outside MVP.

Demonstrate branching through a Memory tutorial: a correct response continues; an incorrect one receives one additional explanation; both paths rejoin. All three challenges remain part of the same funnel.

## Challenges and result

- Memory: remember and reconstruct a pattern in a grid.
- Pattern: choose the next visual element in a sequence.
- Logic: arrange cards according to concise visual constraints; offer tap/keyboard interaction alongside dragging.
- Start with two short unscored tasks per category and adjust pacing from browser use. Simple correctness feedback is allowed; there is no aggregate answer-based score.
- When the quiz completes, generate three illustrative indicators once on the server and persist them on the run. Label them clearly: `Illustrative demo values — not calculated from your answers.` Never imply IQ, population ranking, scientific diagnosis, or measured ability.

## Persistence and data

Prefer three domain tables: `funnel_versions`, `funnel_runs`, and `events`. A run contains its configuration version, anonymous visitor ID, attribution, assigned variant, current state, answers, and optional persisted result. Use JSONB for bounded configuration/answer payloads and ordinary indexed columns for frequently queried identifiers, timestamps, and statuses. Standard Laravel session and admin-user tables are additional.

Use a persistent, protected Laravel session cookie and database-backed sessions. Generate a pseudonymous visitor ID inside the session; never use or expose the secret session identifier as an analytics ID. No consumer accounts or recovery mechanism. Restoration is limited to the same browser while its cookie remains available.

Return the existing active run on duplicate starts. Accept only allowed transitions. Persist step completion and its milestone event atomically; retries must not duplicate answers, events, or result generation. Restore confirmed steps after refresh. An interrupted memory reveal may restart its unfinished round with clear copy; completed rounds remain saved. No offline synchronization platform.

## Analytics and experiment

One `landing-curiosity-v1` A/B experiment is assigned in the anonymous Laravel session before rendering. A retains published landing content and Memory art; B uses curiosity copy, three-category art and its own CTA, with the same funnel. New runs copy server-controlled assignment and first-touch bounded UTM. Existing runs/configurations remain unchanged; null historical fields are never fabricated.

Server milestones remain authoritative and transactional. The client reports actual visible landing/screen exposure through a CSRF-protected, bounded allowlist endpoint, with run ownership/current-or-completed screen validation, UUID/semantic deduplication and its own rate budget. Bootstrap and state transitions alone never claim actual exposure. Failed analytics delivery cannot overwrite saved progress.

`App\Analytics\AggregateService::summarize` is the single aggregate-only layer for Filament and the explicitly synthetic public view. A constrained dataset enum defaults to real; date cohorts and observation windows are bounded. It returns raw counts, numerators, denominators, conditional step metrics, wall-clock quiz elapsed time, first-touch UTM groups and mature exposed-visitor A/B rates. It does not declare a winner. Legacy quiz completion comes from its event, never terminal `completed_at`.

[Analytics definitions](ANALYTICS.md) are the sole reference for exact event authority, deduplication, attribution, trust/delivery limitations, cohort/window rules and T4 calling examples. Filament authenticates real-data reads through a separate administrative guard/session table and a host-only cookie scoped to `/admin`. Its Livewire updates share that path; unused/default component and file endpoints are closed before consumer session handling. Read-only resource policies deny writes. The public `/demo-analytics` page consumes only `/api/v1/demo-analytics`, whose caller hard-codes Synthetic and a rolling seven-day UTC cohort with a 24-hour observation window. It rejects query parameters and omits raw identifiers, answers and UTM values. The daily idempotent synthetic seed appends named scenarios without rewriting consumer history; see README for provisioning and seed commands.

## UX, security, and delivery

Retain the proposed light/blue visual direction as a starting point, not a substitute for browser inspection. Signature interactive memory grid, coherent typography, restrained motion, clear progress, touch-sized controls, reduced-motion support, visible focus, and actionable loading/error states are baseline quality.

Validate server inputs, enforce run ownership, use CSRF protection for cookie-authenticated writes, rate-limit public APIs, and protect admin routes. Consumer responses contain neither admin details nor secrets. Private responses must not enter shared caches. Retain anonymous run/event data only as needed; the session-owned deletion endpoint and bounded `privacy:retention` preview/execute command implement the small policy described in [release operation](DEPLOYMENT.md), with no account-management system.

Target one repository with reproducible local services and CI. Prefer Vercel Hobby plus Railway Laravel/PostgreSQL, approximately $5–10/month at light usage, subject to actual resources, taxes, and platform terms. No services are purchased or deployed by this decision. Public publication and costs require approval; a private GitHub repository is authorized.

## Implementation and review

Start with a real vertical slice, not complete infrastructure. Inspect each significant screen in a browser on mobile and desktop as it is built. Check persistence and API behavior against PostgreSQL. Deliver progression and mechanics before analytics, experiment, and Filament. Share API contracts before parallel work. Independent review is required for complex ownership, transition, or analytics changes.

## Release and anonymous lifecycle

Consumer session accesses that mutate identity/state are serialized; API transactions and retention use the same per-visitor PostgreSQL advisory lock. New visitors receive random server-generated references stored only in their session. Deletion removes the owned run (cascading legacy events) and all visitor events, invalidates/rotates consumer session and CSRF, and preserves the separate administrative scope. Restoring runs updates activity; retention considers the latest run creation/update/restore or linked event, including legacy and orphan landing exposures, after a strict 30-day inactivity boundary. Synthetic samples have the same policy. Expired sessions use seven-day consumer/two-hour admin inactivity, independently of run retention. Maintenance is bounded and previews by default; scheduled execution remains an operator deployment task.

The production image uses pinned Apache/PHP and Composer bases, locked production dependencies, local Filament assets and a public-only document root. Vercel server-side proxy calls require a shared private secret before backend session initialization. Production fixes the backend HTTPS origin, forces secure host-only cookies, rejects untrusted hosts and trusts only the forwarded scheme from explicitly configured ingress proxies. File-backed throttles/locks support one backend replica; no persistent PHP application workers, queue, Redis or edge protection are introduced. Safe release admin provisioning reads a private secret file, hashes the password and revokes that administrator's sessions/remember token. Exact environments, commands, cost assumptions and remaining cloud approvals are in [release operation](DEPLOYMENT.md).
