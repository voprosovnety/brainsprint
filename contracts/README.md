# Consumer contract

The implemented T1 contract is [openapi.json](openapi.json) (OpenAPI 3.1). Frontend types live in `apps/web/src/lib/api.ts`; server validation lives in `FunnelController`. Changes must keep all three aligned.

The browser uses only the frontend origin. Its allowlisted `/api/v1` proxy forwards Laravel cookies and the CSRF header, preserves every `Set-Cookie`, never caches private state, and rejects foreign write origins. It forwards no browser-controlled identity header. Call `GET /api/v1/bootstrap` first to initialize the normal Laravel session and XSRF cookies; send the URL-decoded XSRF cookie as `X-XSRF-TOKEN` for writes. The session cookie is HttpOnly. Local HTTP is supported; production requires secure cookies and HTTPS.

| Method | Route | Behavior |
| --- | --- | --- |
| GET | `/api/v1/bootstrap` | Active landing configuration and current session-owned run, including a completed result |
| POST | `/api/v1/runs` | Return the existing run or create one pinned to the current published version |
| GET | `/api/v1/runs/{run}` | Owner-only confirmed state; foreign and missing IDs both return 404 |
| POST | `/api/v1/runs/{run}/steps/{step}/complete` | Validate bounded cells, save atomically, and return persisted result |

Completion accepts an unordered set of 0–15 cell indices (an empty selection is valid). Repeating the same set returns canonical state. Replacing a confirmed set returns 409. A unique visitor constraint, row lock, transaction, and milestone uniqueness protect retries. Step and quiz completion events are server-only. There is no client event endpoint yet.

T1 deliberately retains one run per anonymous browser session, including after completion: starting again returns that result. A new attempt flow belongs to later progression work. Confirmed state restores through bootstrap; unfinished reveal/selection starts again. Removing cookies removes browser access. IDs alone never authorize reads or writes.

Configuration is bounded Memory-only JSON; publication inserts a content-addressed immutable version, and runs load their pinned version even if a newer one is published. Editing the source and running the seeder publishes a new version without updating existing rows. No configuration-write endpoint exists.

Errors have `error.code`, `error.message`, and `error.fields`; rate limiting is 60 requests per minute per server session, with `Retry-After`. Production edge abuse protection is a release concern. T2 extends this same funnel with onboarding, further mechanics, and the final boundary; T3 adds experiments and client exposure events. There are no A/B assignments or analytics views in T1.
