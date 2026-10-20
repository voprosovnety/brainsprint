# Consumer contract

The contract is [openapi.json](openapi.json) (OpenAPI 3.1). Frontend types live in `apps/web/src/lib/api.ts`; screen validation lives in `app/Funnel/Answer.php`, publication validation in `app/Funnel/Configuration.php`, and transactional progression in `FunnelController`.

The browser uses only the frontend origin. The allowlisted `/api/v1` proxy forwards Laravel cookies and the CSRF header, preserves every `Set-Cookie`, never caches private state, and rejects foreign write origins. It forwards no browser-controlled identity header. Call `GET /api/v1/bootstrap` to initialize Laravel session and XSRF cookies; send the URL-decoded XSRF cookie as `X-XSRF-TOKEN` for writes. The session cookie is HttpOnly. Production requires secure cookies and HTTPS.

| Method | Route | Behavior |
| --- | --- | --- |
| GET | `/api/v1/bootstrap` | Latest published landing configuration, stable server experiment assignment, first-touch UTM capture and session-owned run; no exposure recorded |
| POST | `/api/v1/privacy/delete` | Empty JSON object, no query; CSRF-protected server-owned run/answer/result/events deletion including landing exposure, then anonymous session/CSRF rotation; preserves admin/unrelated visitors |
| POST | `/api/v1/runs` | Return the existing run, or create one pinned to the latest published version and its configured entry, copying server experiment/UTM context |
| POST | `/api/v1/events` | Validated actual landing/screen exposure; independent 40/minute limit, UUID and semantic deduplication |
| GET | `/api/v1/runs/{run}` | Owner-only confirmed state, answers and result; foreign and missing IDs both return 404 |
| POST | `/api/v1/runs/{run}/steps/{step}/complete` | Validate the pinned screen's answer, persist it with milestones, and follow a server-selected transition |

Completion body is exactly `{ "answer": ... }`:

| Screen type | Answer | Validation |
| --- | --- | --- |
| `tutorial`, `visual_memory` | `{ "cells": [0, 4] }` | List of distinct integer indices bounded by `grid_size²`; empty is valid; canonicalized by sorting |
| `pattern` | `{ "choice": "diamond" }` | One configured option ID |
| `logic` | `{ "order": ["sun", "star", "moon"] }` | Exact list permutation of configured card IDs, without duplicates |
| `onboarding`, `explanation`, `result`, `boundary` | `{ "acknowledged": true }` | Literal boolean true |

Extra answer/body keys, arbitrary transition assertions, invalid choices and malformed lists are rejected. A valid answer for a future/skipped screen returns 409. Repeating the same canonical answer returns current state even after subsequent steps; changing a confirmed answer returns 409. A unique visitor constraint, row lock, database transaction and milestone uniqueness protect retries. Failed event writes roll back the answer, transition and result.

The response includes `current_step`, `completed_steps`, owner-only `answers`, `result` (initially null), and `demo_completed`. Entering the result creates the three illustrative indicators once and emits `quiz_completed`. Completing the result advances to the boundary without changing indicators. `Finish demo` acknowledges the boundary, sets `current_step: "done"` and `demo_completed: true`, stores terminal `completed_at`, and emits `demo_completed`. `step_completed` accompanies each confirmed screen; `funnel_started` accompanies creation. Actual exposure events, including `paywall_reached`, are client-reported and never inferred from transitions.

One run is retained per anonymous browser session, including after completion. Starting again returns it. Restoration is limited to the same browser cookies; no recovery account or new-attempt flow exists. Browser Back/Forward may review confirmed answers without writing, but may not skip ahead. Interrupted Memory reveals restart clearly. After a save error, the client keeps that submitted answer for an identical retry or offers restoration from the server.

## Versioned configuration

`resources/funnel/brainsprint-v2.json` is the publication source. `php artisan db:seed` validates before inserting a content-addressed immutable version. Changing source affects new sessions only; existing versions/runs are never updated. No configuration-write API exists.

The v2 configuration has `entry`, `landing`, `result`, and `steps`. Screens have stable IDs, type, title, description, boolean `enabled`, a bounded `standard`/`spotlight` variant, default `next`, and type-specific parameters. Only the tutorial has `branch.incorrect`; Laravel compares the saved cells to the tutorial pattern. The explanation rejoins the tutorial's default path. Disabled screens follow default `next`. Progress and challenge numbering follow transitions, not array order.

Publication rejects missing targets, duplicate IDs, cycles (including disabled edges), unreachable enabled screens, invalid parameters, incomplete paths, misplaced result/boundary, and a tutorial branch that does not rejoin. Each possible path includes onboarding, tutorial and all three challenge categories before result → boundary → done. Extra rounds may be disabled; at least one round per category remains required. Arrays and parameters are bounded. This is a small explicit graph, not an expression language or funnel builder.

Historical T1 configurations without `schema_version` retain their original `memory` → `result` behavior, content and stored result. Their `completed_at` retains its original quiz-completion meaning; v2 uses terminal demo completion. They do not acquire a boundary retroactively. Empty saved answers serialize as an object; no private visitor/session IDs are returned.

Errors contain `error.code`, `error.message`, and `error.fields`. Progress is limited to 60 requests/minute per session; exposure has an independent 40/minute session budget, both with `Retry-After`. Deployment edge protection is a release concern. Filament remains on the API origin under `/admin` and is never allowlisted by the consumer proxy.

## Experiment, attribution and client exposure

Bootstrap returns `experiment: {id: "landing-curiosity-v1", variant: "A" | "B"}`. It accepts only the five bounded `utm_*` query fields described in OpenAPI; Start optionally accepts `{attribution: {...}}` with the same fields, each up to 120 characters and no control characters. Attribution initializes once, including direct/empty first touch. Other Start fields are rejected; later requests cannot overwrite context. Historical runs remain unassigned.

Client events use `{event_id: UUID, name, run_id?, step_id?, properties?: {surface?: "page"}}`. Allowed names: `landing_viewed`, `step_viewed`, `result_viewed`, `paywall_reached`. Landing requires bootstrap and no run/screen; other events require an owned run and a current or completed screen with matching type. No future or skipped screen is eligible. Server milestone names and all context overrides are forbidden. Request body is capped at 4096 bytes. UUID retries and repeated semantic views are accepted as no-ops, so `{accepted:true}` does not indicate a new event.

Exact authority, deduplication, first-touch rules, delivery limitations, cohorts, numerators/denominators and the reusable T4 aggregate API are defined once in [analytics definitions](../docs/ANALYTICS.md). The public aggregate endpoint is documented below.

## Public synthetic analytics

`GET /api/v1/demo-analytics` returns `DemoAnalytics` from the shared `AggregateService`: `dataset`, `cohort`, `totals`, `stage_conversions`, `quiz_elapsed_seconds`, and `experiment` only. It hard-codes `Dataset::Synthetic`, the rolling seven-day `[now-7 days, now)` UTC cohort, `asOf=now`, and a 24-hour observation window. It returns neither raw run/visitor/session IDs, answers, UTM values nor administrative details. Empty denominators remain null; pending experiment exposures remain pending; no winner is declared.

Every query parameter is rejected with 422 at both proxy and backend, including dataset, bounds, SQL and ID parameters. GET bodies cannot alter the caller. POST is unsupported. The consumer per-session rate budget applies; responses remain private/no-store. The proxy adds only this exact GET route and cannot forward `/admin` or Livewire routes.

Administrative cookies are host-only `brainsprint_admin_session`, scoped to `/admin`, HttpOnly, SameSite Strict, with a separate `admin` guard and `admin_sessions` table. Livewire updates use `/admin/livewire/update`; default updates and unused upload/preview routes are blocked before consumer session initialization. These boundaries also apply on one hostname across different ports.

## Anonymous lifecycle and release trust

`POST /api/v1/privacy/delete` takes exactly an empty JSON object (no visitor/run identity or query). Successful `{ "deleted": true }` means the current server-session visitor's run, answers/result and events, including landing-only and legacy linked events, were deleted. Consumer session/CSRF cookies rotate. The original cookie cannot recover that visitor identity: new visitor IDs are random server values stored in the session. Other visitors and the separate admin store are unaffected. Subsequent bootstrap returns no old run and initializes a new anonymous visit; new exposure collection can resume.

Consumer bootstrap/start/show/complete/exposure/delete serialize session access and share a visitor PostgreSQL transaction advisory lock with retention. Restoring an owned run through bootstrap/show updates its stored activity timestamp. The documented 30-day policy and bounded default-preview maintenance command are in [release operation](../docs/DEPLOYMENT.md); no retention execution is automatic yet.

Production API requests require a private `X-Consumer-Proxy` value set only by the server-side Next.js proxy, before any consumer session is created. The proxy overwrites this header; browsers cannot supply identity, forwarded IP or this secret. Private responses/errors are no-store. HTTPS host-only session cookies and fixed backend HTTPS URL generation are enforced in production; localhost retains HTTP. Session budgets default to 60 consumer/40 exposure requests per minute with independent configurable global budgets (600 each). Application throttling is single-replica and not a claim of edge/bot protection.
