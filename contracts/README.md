# Consumer contract — initial vertical slice

Status: implementation input. These routes are agreed shapes, not working endpoints yet. T1 may refine incidental details while preserving ownership and retry semantics. Convert them to a small machine-readable OpenAPI contract during T1 and make this file an index rather than maintaining duplicate schemas.

Browser requests use the frontend origin; Next.js proxies the allowlisted `/api` routes to Laravel. Forward the session/CSRF cookies safely; do not cache private state. Filament uses its own backend-origin authenticated session.

| Method | Route | Behavior |
| --- | --- | --- |
| GET | `/api/v1/bootstrap` | Public active landing/configuration, session-held experiment context when implemented, and current run reference |
| POST | `/api/v1/runs` | Start or return the session's existing unfinished run |
| GET | `/api/v1/runs/{run}` | Owner-only confirmed state and result if quiz complete |
| POST | `/api/v1/runs/{run}/steps/{step}/complete` | Validate allowed step and answer, save once, return canonical next state |
| POST | `/api/v1/events` | Later T3: bounded allowlisted client exposure events, with retry deduplication |

The T1 implementation needs only the first four routes. Establish CSRF-cookie initialization using Laravel's normal facilities as needed; do not invent a custom token platform.

## Shared invariants

- Derive ownership from the server session, not a request-supplied visitor ID. Public run IDs are not credentials.
- A run response identifies its public ID, pinned configuration version, current allowed step, completed steps, and optional persisted demo result. Never return a secret session identifier.
- Completion requests identify the step and bounded answer data. A repeated identical completion returns canonical state; a conflicting replacement of a confirmed answer is rejected clearly.
- Only Laravel decides next steps and when the quiz has completed. Public configuration contains presentation and bounded parameters; no executable code.
- Errors have a stable code, actionable message, and field details where relevant. Use ordinary HTTP statuses including 401/403, 409, 422, and 429.
- T2 adds further screen types to the same contract; T3 adds events and a stable landing variant. Do not implement these subsystems speculatively in T1.
