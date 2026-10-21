# Consumer contract

The contract is [openapi.json](openapi.json) (OpenAPI 3.1). Only the direct-entry schema-3 journey is supported. Its screen inventory, answers and sample-report/commerce behavior are specified in [V3.md](V3.md). Frontend types live in `apps/web/src/lib/v3.ts`; the transport lives in `apps/web/src/lib/api.ts`. Laravel validates answers in `app/Funnel/Answer.php`, publication in `V3Configuration.php`, and transactional progression in `FunnelController`.

The browser uses only the frontend origin. The allowlisted `/api/v1` proxy forwards Laravel cookies and the CSRF header, preserves every `Set-Cookie`, never caches private state, and rejects foreign write origins. It forwards no browser-controlled identity header. Call `GET /api/v1/bootstrap` to initialize Laravel session and XSRF cookies; send the URL-decoded XSRF cookie as `X-XSRF-TOKEN` for writes. The session cookie is HttpOnly. Production requires secure cookies and HTTPS.

| Method | Route | Behavior |
| --- | --- | --- |
| GET | `/api/v1/bootstrap` | Owned supported run and its pinned configuration, otherwise latest published schema-3 configuration; first-touch UTM; no exposure recorded |
| POST | `/api/v1/privacy/delete` | Exactly an empty JSON object, no query; delete the session-owned run/answers/report/events and rotate anonymous session/CSRF cookies |
| POST | `/api/v1/runs` | Return the existing supported run or create one pinned to the latest supported publication and its configured entry |
| POST | `/api/v1/events` | Validated actual screen exposure; independent 40/minute limit; UUID and semantic deduplication |
| GET | `/api/v1/runs/{run}` | Owner-only confirmed progress, answers and public report state; foreign and missing IDs both return 404 |
| POST | `/api/v1/runs/{run}/steps/{step}/complete` | Validate the pinned screen's answer, persist it with milestones, then follow the server-selected transition |
| GET | `/api/v1/demo-analytics` | Fixed-window synthetic aggregates only; every query parameter rejected |

Completion is exactly `{ "answer": ... }`. See [answer shapes](V3.md). Extra answer/body keys, arbitrary transitions, invalid choices and malformed values are rejected. A valid answer for a future/skipped screen returns 409. Repeating an identical canonical answer returns current state even after subsequent steps; changing a confirmed answer returns 409. A unique visitor constraint, row lock, database transaction and milestone uniqueness protect retries. Failed event writes roll back the answer, transition and report changes.

Run responses include `current_step`, `completed_steps`, owner-only `answers`, `started_at`, `teaser`, `commerce`, `result` and `demo_completed`. The private report envelope is never serialized. Before simulated unlock, `result` is null. Final acknowledgement sets `current_step: "done"`, `demo_completed: true` and terminal `completed_at`. Actual exposure events are client-reported and never inferred from transitions.

One run is retained per anonymous browser session, including after completion. Starting again returns it. Restoration is limited to the same browser cookies; no recovery account or new-attempt flow exists. Browser Back/Forward may review confirmed answers without writing but may not skip ahead. After a save error, the client retains the submitted answer for an identical retry or offers restoration from the server.

## Publication and unsupported saved runs

`resources/funnel/brainsprint-v3.json` is the only publication source. `php artisan db:seed` validates the bounded configuration before inserting a content-addressed immutable version. Changing source affects newly published versions and new sessions; supported existing runs stay pinned. No configuration-write API exists. Publication rejects unsupported schemas, invalid parameters, duplicate IDs, cycles, missing/unreachable screens and invalid final branches.

Bootstrap returns `experiment: null` and `restart_required: false` for normal sessions. No landing screen or assignment is created. If this session owns a run from an unsupported schema, bootstrap returns the latest supported configuration with `restart_required: true` and `run: null`; it does not return that run's configuration, answers or result. The home page explains that the saved challenge has retired and exposes the existing data-deletion control. Start, owner reads, completion and otherwise valid screen events for that run return HTTP 410 with `error.code: "run_retired"`. Ownership is checked before the retired-run response: foreign and missing IDs remain indistinguishable 404 responses.

Deleting the session-owned data remains available regardless of its schema. It rotates the session; returning to the home page begins a fresh anonymous visit and the current journey. Merely deploying this code does not delete saved runs or published configurations. If no supported publication exists, bootstrap/start return 503 until configuration publication is performed. Existing nullable experiment columns from historical migrations are inert; application code neither assigns nor consumes them. No database schema migration is needed for this retirement.

Errors contain `error.code`, `error.message` and `error.fields`. Progress is limited to 60 requests/minute per session; exposure has an independent 40/minute budget, both with `Retry-After`. Filament remains on the API origin under `/admin` and is never allowlisted by the consumer proxy.

## Attribution and client exposure

Bootstrap accepts the five bounded `utm_*` query fields described in OpenAPI. Start optionally accepts `{attribution: {...}}` with the same fields, each up to 120 characters and no control characters. Attribution initializes once, including direct/empty first touch. Other Start fields are rejected; later requests cannot overwrite context.

Client events use `{event_id: UUID, name, run_id, step_id, properties?: {surface?: "page"}}`. Allowed names are `step_viewed`, `paywall_viewed_v3`, `checkout_viewed_v3` and `report_viewed_v3`. All require an owned current/completed screen with a matching event name; report exposure additionally requires an unlocked final screen. No future/skipped screen is eligible. Server milestones and context overrides are forbidden. Bodies are capped at 4096 bytes. UUID retries and repeated semantic views are no-ops, so `{accepted:true}` does not prove a new event.

Authority, delivery limitations, cohorts and calculations are defined in [analytics definitions](../docs/ANALYTICS.md).

## Public synthetic analytics

`GET /api/v1/demo-analytics` returns only `dataset`, `cohort`, `totals`, `stage_conversions` and `quiz_elapsed_seconds` from the shared `AggregateService`. All totals and conversions now describe schema-3 runs. It hard-codes `Dataset::Synthetic`, the rolling seven-day `[now-7 days, now)` UTC cohort, `asOf=now` and a 24-hour observation window. No nested version aggregate or experiment object is returned. Empty denominators remain null. No raw IDs, answers, UTM values or administrative details are exposed.

Every query parameter is rejected with 422 at both proxy and backend, including dataset, bounds, SQL and ID parameters. GET bodies cannot alter the caller. POST is unsupported. The consumer session rate budget applies; responses remain private/no-store.

## Anonymous lifecycle and release trust

Successful `{ "deleted": true }` from the deletion endpoint means the current server-session visitor's run, private answers/report and events were deleted, including unsupported run data, run-linked events without visitor context and unlinked visitor events. Consumer session/CSRF cookies rotate. The old cookie cannot recover that identity: new visitor IDs are random server values stored in the session. Other visitors and the separate admin store are unaffected.

Consumer bootstrap/start/show/complete/exposure/delete serialize session access and share a visitor PostgreSQL transaction advisory lock with retention. Restoring a supported run updates its activity timestamp. The 30-day policy and bounded default-preview maintenance command are in [release operation](../docs/DEPLOYMENT.md); scheduled maintenance performs bounded cleanup after an encrypted backup.

Administrative cookies are host-only `brainsprint_admin_session`, scoped to `/admin`, HttpOnly and SameSite Strict, with a separate `admin` guard and `admin_sessions` table. Livewire updates use `/admin/livewire/update`; default updates and unused upload/preview routes are blocked before consumer session initialization.

Production API requests require a private `X-Consumer-Proxy` value set only by the server-side Next.js proxy before any consumer session is created. The proxy overwrites this header; browsers cannot supply identity, forwarded IP or this secret. Private responses/errors are no-store. HTTPS host-only cookies and fixed backend HTTPS URL generation are enforced in production; localhost retains HTTP. Session budgets default to 60 consumer/40 exposure requests per minute with independent configurable global budgets (600 each). Throttling uses shared PostgreSQL cache; it is not edge protection.
