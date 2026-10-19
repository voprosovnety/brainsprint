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

Taxonomy: `landing_viewed`, `funnel_started`, `step_viewed`, `step_completed`, `quiz_completed`, `result_viewed`, `paywall_reached`, and `demo_completed` when the user finishes the boundary screen.

The server emits successful state transitions; the client reports actual screen exposure through an allowlisted, validated endpoint. Deduplicate event retries by ID and completion milestones by business identity. Keep pseudonymous visitor ID, run ID when present, configuration version, screen ID, variant, timestamps, and bounded UTM fields. Never accept server-only milestone names or a visitor identity chosen by the client.

One landing experiment has A/B content and CTA variants leading to the same funnel. Assign before rendering, persist in the server session, and copy into runs. Persist actual exposure. Measure unique exposed visitors → unique starters for the same variant; also show quiz completion and boundary reach, counts, denominators, and elapsed time. First-touch UTM and run attribution are sufficient; no multichannel model or click-ID integrations are required.

Real statistics exclude synthetic runs and events by default. The public demo explicitly selects synthetic data. Both views use one aggregate service and identical metric definitions. Do not assert a winning variant from insufficient data or invent payment conversions.

## UX, security, and delivery

Retain the proposed light/blue visual direction as a starting point, not a substitute for browser inspection. Signature interactive memory grid, coherent typography, restrained motion, clear progress, touch-sized controls, reduced-motion support, visible focus, and actionable loading/error states are baseline quality.

Validate server inputs, enforce run ownership, use CSRF protection for cookie-authenticated writes, rate-limit public APIs, and protect admin routes. Consumer responses contain neither admin details nor secrets. Private responses must not enter shared caches. Retain anonymous run/event data only as needed; the release task must implement and document a small retention/deletion policy rather than an account-management system.

Target one repository with reproducible local services and CI. Prefer Vercel Hobby plus Railway Laravel/PostgreSQL, approximately $5–10/month at light usage, subject to actual resources, taxes, and platform terms. No services are purchased or deployed by this decision. Public publication and costs require approval; a private GitHub repository is authorized.

## Implementation and review

Start with a real vertical slice, not complete infrastructure. Inspect each significant screen in a browser on mobile and desktop as it is built. Check persistence and API behavior against PostgreSQL. Deliver progression and mechanics before analytics, experiment, and Filament. Share API contracts before parallel work. Independent review is required for complex ownership, transition, or analytics changes.
