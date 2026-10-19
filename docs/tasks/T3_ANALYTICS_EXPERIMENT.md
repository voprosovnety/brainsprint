# T3 — trustworthy events, landing A/B experiment, and shared metrics

Status: ready for an independent executor.

## Goal and inputs

Add useful Growth Engineering behavior to the existing single BrainSprint funnel: one real landing experiment, UTM attribution, correctly sourced/deduplicated events, and one shared aggregate service for the later Filament and compact public demo views. This task implements the analytics foundation, not two dashboards.

Read root/application AGENTS, relevant sections of `docs/ARCHITECTURE.md`, `contracts/README.md` and OpenAPI, README, and T2 delivery notes only where they affect event semantics. T2 is accepted at `cf4e385`. Coordinator fix `9b9aa61` removes a stale PHPUnit Unit suite absent from clean checkouts; preserve it. Do not read old interviews or entire lockfiles.

## Ownership and runtime

You are the only implementation writer in the shared checkout. Own analytics/experiment code, necessary compatible schema changes, frontend instrumentation/landing variants, consumer contracts, focused tests/CI, relevant docs, and this brief. Do not edit coordinator-owned `docs/IMPLEMENTATION_PLAN.md`.

Existing services: web http://127.0.0.1:3000, Laravel http://127.0.0.1:8008, PostgreSQL loopback 55432. Check state before starting/stopping known services; port 8000 is unrelated. Project-local Composer is `.tools/composer`. Tests must use only guarded `brainsprint_test`. Do not reset the development DB or alter historical answers/results.

## Required behavior

1. One server-assigned landing experiment, two coherent content/composition/CTA variants leading to exactly the same funnel. Assign before variant rendering to avoid A→B flicker, persist assignment in the anonymous Laravel session, and copy experiment ID/variant into newly created runs. Refresh/retries keep the assignment. Existing immutable configurations and old runs remain usable; do not fabricate historical assignments or exposures.
2. Record actual landing/experiment exposure, not merely bootstrap requests or assignment. A returning session restored directly to a challenge must not claim a landing view. Main conversion: unique exposed visitors who start after exposure / unique exposed visitors for that variant, with an explicit observation window. Keep counts and denominators visible in the service output; repeated views, refresh, and retries must not inflate them. Return insufficient-data status rather than a winner.
3. Support event taxonomy `landing_viewed`, `funnel_started`, `step_viewed`, `step_completed`, `quiz_completed`, `result_viewed`, `paywall_reached`, `demo_completed`, plus explicit experiment exposure if useful. Keep existing server state-transition milestones authoritative and transactional. Client requests cannot mint server-only milestone names or choose a visitor identity. For T2, `completed_at` is terminal completion; derive quiz completion from `quiz_completed`, including T1 legacy runs.
4. Add a bounded, validated, CSRF-protected same-origin client event endpoint with allowlisted properties and retry-safe event IDs/semantic deduplication. Check ownership of referenced runs and validity of referenced screens; no arbitrary future-screen exposure or cross-run injection. Derive server context (visitor, configuration, variant, synthetic marker) rather than trusting client values. Actual screen views must work with Back/Forward and restore, while unique-view metrics remain deduplicated.
5. Capture only bounded `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`. Preserve first-touch session attribution and copy relevant attribution on run creation. Do not overwrite original attribution on refresh/internal navigation. Support a first visit with campaign parameters even if view delivery and Start happen close together. No raw URL dumping, click-ID integrations, multichannel attribution model, or sensitive data collection.
6. Implement one small Laravel aggregate service/query layer that later accepts an explicitly constrained real/synthetic dataset and time range. Return totals, stage conversions, step views/completions/drop-off, elapsed completion time, UTM grouping, and A/B counts/rates. Define cohort/time-window rules clearly; do not label background-tab time as active play time or unfinished recent runs as certain abandonment. Step reporting respects conditional exposure, not a fixed assumption that every run saw every screen.
7. Ordinary analytics exclude synthetic events/runs by default. A future public demo will select synthetic data explicitly and expose aggregates only. Keep this distinction in the service and tests now. Historical events may lack newly added fields: keep them safe/readable and exclude ineligible rows from A/B denominators instead of inventing values. Avoid unnecessary tables; prefer extending existing run/event fields and session-held assignment.
8. Keep both landing variants at the existing consumer design quality. Inspect mobile and desktop rendering, no flicker, correct CTA, focus/reduced-motion, and actual event delivery in a real browser. Use separate fresh browser contexts for variants rather than adding public force-variant query parameters. Analytics errors/retries must not destroy saved progress or freeze the funnel. Existing mechanics, stable demo result, and boundary remain functional.
9. Update OpenAPI, TypeScript, validation, README and architecture where needed. Document exact metric/event definitions once, and how T4 reuses the aggregate service. Add focused PostgreSQL tests and update the relevant CI command to include the new suite. Check corrected hosted CI if possible; report an unobserved/failing run honestly. Commit the implementation.

## Explicit boundaries

T4 owns Filament installation/admin authentication, admin resources, compact public synthetic analytics UI, and a small reusable synthetic demonstration dataset. Do not build those views or a separate dashboard in T3. Test fixtures are allowed to validate aggregates. No PostHog, external analytics services, Redis, queues, workers, payments, scoring engine, new product, visual funnel builder, or public deployment. Do not add statistical significance machinery or declare an unsupported winning variant.

Independent review of event trust, deduplication, and experiment denominators is authorized and required for this complex change. Keep review read-only and fix substantive findings before completion.

## Definition of done

- A/B assignment is server-controlled and stable, actual exposure is distinct, and both versions lead through the same working funnel.
- PostgreSQL tests verify assignment, refresh/retries, actual-exposure eligibility, repeated start/view/complete behavior, ownership and invalid-event rejection, stable UTM, and separation of authoritative server milestones from client events.
- Aggregate tests use a small known fixture with asserted exact numerators/denominators, branching and historical rows, real/synthetic isolation, and time/cohort boundaries. Multiple callers can use the same service without recalculating metrics independently.
- Browser checks at mobile and desktop sizes verify both variant presentations and real event delivery, restore/history, and no blocker from failed analytics delivery.
- No saved runs/results are erased or rewritten; schema migration works on the existing development dataset. New flags/context cannot be forged by clients.
- Necessary targeted backend tests, build/type checks, and independent review are actually complete, with limitations disclosed. Do not repeat unrelated successful checks without cause.
- Docs, contract, a clean commit, and a concise T4 handoff match actual behavior. No public admin data or real credentials are introduced.

## Report

Brief Russian summary: commit, what works, actual tests/browser checks, any hosted CI result, limitations, local URLs, and the aggregate service API T4 should consume. The Product Owner authorized reporting back to coordinator chat `01a14708-527c-7253-9ebf-453f1e5cdf3f`; send the completion summary there when available and leave it as the final response here. Do not edit the coordinating plan or publish/deploy.
