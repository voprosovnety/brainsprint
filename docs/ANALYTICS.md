# Analytics and experiment definitions

This is the single metric definition reference for the Laravel `App\Analytics\AggregateService`. Filament and public demo callers must consume its output rather than calculate their own versions of these metrics.

## V3 semantics and version cohorts

V3 does not show a landing or create a new landing-copy assignment. Existing v1/v2 assignment, first exposure and events remain unchanged. First-touch UTM and synthetic policy apply to all versions. The legacy collection/event/cohort descriptions below keep their original meanings.

| V3 event | Authority | Meaning |
| --- | --- | --- |
| `quiz_confirmed_v3` | Server, confirmation transaction | All 41 main responses confirmed and the illustrative sample report generated once |
| `processing_completed_v3` | Server, final preparation transaction | Profile preparation completed and paywall entered; earlier report preparation remains a step completion |
| `paywall_viewed_v3` | Client actual exposure | Paywall shown, without inferring checkout/report access |
| `checkout_viewed_v3` | Client actual exposure | The common mock checkout dialog is visibly open |
| `mock_unlocked_v3` | Server, acknowledged offer transaction | Simulated report unlock persisted; never a purchase |
| `upsell_decided_v3` | Server, guide transaction | Accept or decline persisted; outcome stays in private answers/state |
| `report_viewed_v3` | Client actual exposure | Unlocked report shown on the chosen final screen |
| `funnel_completed_v3` | Server, final acknowledgement | Chosen final acknowledged |

`funnel_started`, `step_viewed` and `step_completed` retain their existing meanings. V3 milestone uniqueness is run/name/triggering screen; view uniqueness is run/name/screen so checkout and paywall are distinct. The API validates owned current/completed screens and requires unlock for report view. No purchase, revenue, financial detail, email, answer or guide outcome properties enter events. Screen views remain client assertions with bounded delivery, never invented from transitions. Ordinary questions may preview while the previous write finishes: capture their actual painted exposure, then defer delivery in a memory-only metadata outbox until server-confirmed progression makes that screen eligible. Static image warming records no exposure. Answer writes take priority over dispatch of this outbox.

The shared service returns a separate `v3` object: run count, unique stage totals, adjacent `stage_conversions` and quiz-confirmation wall-clock time. The path is `funnel_started → quiz_confirmed_v3 → processing_completed_v3 → paywall_viewed_v3 → checkout_viewed_v3 → mock_unlocked_v3 → upsell_decided_v3 → report_viewed_v3 → funnel_completed_v3`. Every denominator is observed previous-stage runs pinned to schema 3; numerators intersect both sets. Both guide outcomes qualify. Legacy conversions exclude v3 from eligible denominators, and legacy elapsed time continues to use only `quiz_completed`. Top-level `runs`/`funnel_started` cover the complete cohort; older stage totals retain their event meanings. V3 timing is separate. UTM summaries add `quiz_confirmed_v3` alongside historical completion counts. Steps remain grouped by pinned version; checkout exposure is a separate stage, not an invented ordinary screen view. Zero denominators stay null.

Public synthetic and protected administrative views use those shared results. Synthetic seeding appends a `demo-v3` cohort with partial paths and both final outcomes, while preserving earlier `demo-v1` samples and their experiment. Seeding never publishes a version, updates ordinary visitors or generates a real purchase. Sample report content and social fixtures shown in the funnel are separate from this analytics dataset.

## Collection and trust

`VisitorContext` creates a pseudonymous visitor inside the database-backed Laravel session. `landing-curiosity-v1` uses a server-keyed deterministic 50/50 A/B assignment stored in that session before either landing is rendered. New runs copy the assignment; existing runs retain null historical context. The browser cannot select a variant. A uses the published landing copy and Memory preview; B uses curiosity copy, a three-category composition, and **Find my rhythm**. Both start the identical published funnel.

Bootstrap assigns and restores; it never records a view. The client sends exposure after two animation frames with a visible document, on visible screen changes and when a hidden document becomes visible again. Start also delivers the visible landing before attempting run creation. Returning directly to a saved challenge, result or finish does not claim a landing view.

| Event | Authority | Semantic identity |
| --- | --- | --- |
| `landing_viewed` | Client reports rendered landing | Visitor + experiment (first view only) |
| `funnel_started` | Server transaction creates a run | Run |
| `step_viewed` | Client reports ordinary current/saved screen | Run + screen |
| `step_completed` | Server transaction confirms answer/acknowledgment | Run + screen |
| `quiz_completed` | Server transaction creates the illustrative result | Run |
| `result_viewed` | Client reports the result screen | Run + screen |
| `paywall_reached` | Client reports the demo boundary screen | Run + screen |
| `demo_completed` | Server transaction finishes the boundary | Run |

`paywall_reached` is retained taxonomy: the actual product boundary has no price or checkout. It is not inferred from entering its server state. There is no separate experiment-exposure event; the first `landing_viewed` with valid context is the exposure. Views represent unique exposure, not pageview frequency. Back/Forward and restore can deliver them again but cannot inflate counts.

`POST /api/v1/events` permits only the four client names above, UUID `event_id`, appropriate `run_id`/`step_id`, and optional `properties.surface: "page"`. The server derives identity, pinned configuration, experiment, timestamp, UTM and synthetic marker. Owned runs can report only their current or completed screen, with a matching event name; skipped/future screens are rejected. Clients cannot send milestone names, answer data, timestamps, URLs or context overrides. An actual human view remains a client assertion; this is not bot-proof measurement.

Uniqueness covers visitor + client UUID and semantic identity independently, using PostgreSQL conflict-safe insertion. Same UUID retries and different UUIDs for the same screen are no-ops. Reusing a UUID for another screen is also a no-op. `{accepted:true}` acknowledges processing, not a new insertion. Milestones retain their run/name/screen uniqueness and atomic progression transaction. Existing nullable historical fields remain untouched.

Cookies, CSRF, same-origin proxy validation, private/no-store responses and request bounds protect collection. Exposure writes have an independent 40/minute session limit; progress has 60/minute. The in-memory client outbox makes up to three attempts with the original ID, 1.5-second deadlines and short backoff; it stores no browser identifier or payload persistently. Start waits at most 1.6 seconds for landing delivery. Delivery can be lost on navigation, blocking or outage; a delayed exposure arriving after Start cannot retroactively qualify that conversion. Missing telemetry is not fabricated. Analytics failure never changes confirmed answers or blocks the funnel indefinitely.

Only `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term` are accepted: strings up to 120 characters without control characters. Bootstrap captures the initial URL values before internal history rewrites; Start also supplies those same initial values for close delivery/start timing. First touch is initialized once, including an empty/direct visit; refresh and later campaign URLs do not overwrite it. New runs and events copy the server-held attribution. No raw URL, referrer, click ID or multichannel attribution is collected.

Local/testing runs and exposures are synthetic by server environment; ordinary production traffic is real. A run's events always inherit its stored marker, including legacy runs. Clients cannot override it. `analytics:seed-demo` appends recognizable synthetic daily scenarios; see README for dates, idempotency and refresh behavior.

## Shared service API

```php
use App\Analytics\AggregateService;
use App\Analytics\Dataset;
use Carbon\CarbonImmutable;

$metrics = app(AggregateService::class)->summarize(
    from: CarbonImmutable::parse('2026-10-01T00:00:00Z'),
    to: CarbonImmutable::parse('2026-10-15T00:00:00Z'),
    asOf: CarbonImmutable::parse('2026-10-16T00:00:00Z'),
    dataset: Dataset::Real, // default; a public demo must hard-code Dataset::Synthetic
    observationHours: 24,
);
```

The range is half-open `[from,to)` and at most 93 days; `to <= asOf`, with `asOf` defaulting to now. Observation is 1–168 hours, default 24. All inputs are instants: SQL bindings preserve their offsets and microseconds. Filament constrains authenticated date ranges and observation windows. The public demo caller hard-codes the synthetic enum, rolling seven-day bounds and 24-hour observation, rejects all query parameters, and returns a compact allowlist of the shared result fields, including the separate v3 aggregate object. Output includes aggregates only, with no run IDs, visitors, cookies or answers. UTM group labels still contain supplied campaign strings; they are safe only within the chosen dataset/access policy and must be rendered as text.

This small demo service loads the selected cohort's rows into memory. It is not a warehouse or a high-volume reporting engine. There is no `all` dataset or client-controlled synthetic flag. A public controller must not forward arbitrary query parameters into the dataset selection.

## Cohorts and returned fields

- `cohort`: requested bounds, observation cutoff and hours.
- `totals`: runs created in `[from,to)` in the chosen dataset; distinct runs with each named stage event through `asOf` (inclusive). Outcomes may occur after `to`. Runs without events remain in `runs`, but milestones are never invented. Historical events with null new context still count for their run and event name.
- `stage_conversions`: adjacent stages `funnel_started → quiz_completed → result_viewed → paywall_reached → demo_completed`. Numerator is the intersection of observed runs at both stages; denominator is runs with the previous event whose pinned configuration supports the destination. T1 result viewers have no boundary and are excluded from that transition denominator. Zero denominators return `rate:null`; missing client views can make observed milestones exceed their corresponding views, so totals and intersections are both visible. Rates do not assert causal effects.
- `steps`: keyed by configuration version and screen ID, only for screens with a recorded view or completion in the run cohort. `viewed`/`completed` are unique runs. `completion_among_viewed` intersects completed and viewed runs, using actual viewed runs as denominator. Conditional/disabled/skipped screens are not assumed to have been exposed to all runs. `completed_without_recorded_view` makes historical/missing telemetry visible.
- Step `not_completed_after_observation`: viewed, not completed by `asOf`, with at least the observation hours elapsed since first recorded view. `pending_observation`: remaining viewed/uncompleted runs with less time elapsed. These are observed noncompletion and pending follow-up, never certain abandonment; completion can still happen later. They do not claim an active session timeout.
- `quiz_elapsed_seconds`: number and mean wall-clock seconds from run creation to authoritative `quiz_completed`, through `asOf`. Includes paused/background time. It is not active play time. T1 and T2 both use the event, never `completed_at` (T1 used that timestamp for quiz completion; T2 uses terminal completion).
- `utm`: groups the run cohort by the complete five-field first-touch attribution tuple; missing/empty context is direct/unknown `{}`. Each row gives run count and observed quiz-completed count. It is not landing-visitor attribution or channel causality.

Every rate returns `numerator`, `denominator`, `rate` (0–1 or null). No interpolation, invented historical exposure, scoring or payment conversion occurs.

## Experiment cohort and observation window

A/B uses a separate **first-exposure cohort**, not the run-created cohort. The first eligible landing exposure per visitor must lie in `[from,to)`, have `landing-curiosity-v1`, A/B, visitor and configuration context, and match the selected dataset. Assignment and bootstrap without an exposure are ineligible. Historical null context is ineligible. First exposure is chosen chronologically; duplicates cannot enlarge the denominator or move the visitor into a later cohort.

For each variant, `exposed` includes all such unique visitors; `eligible_exposed` contains those whose full observation window ends by `asOf`; `pending_observation` is the rest. All three conversion denominators are `eligible_exposed`. A starter requires a same-visitor, same-experiment/variant run created **at or after exposure and no later than exposure + observationHours**, with `funnel_started` in that interval. Quiz and boundary numerators require that starter and the respective event no later than the same exposure-window endpoint. Endpoint timestamps are inclusive. Run creation and event timestamps retain microseconds so a returning visitor's preexisting run is not accidentally counted as a later start in the same second.

A start after `to` can qualify if it is within that visitor's exposure window. Starts before exposure or after the observation window do not qualify. Quiz/boundary rates are exposed-visitor rates, not per-starter rates; their raw counts and denominators are returned. A late event after `asOf` cannot affect this snapshot. Pending visitors are shown rather than counted as failures. Output always reports `status: insufficient_data` and `winner: null`; T3 implements no significance or decision machinery and declares no winner.

## Presentations

Filament uses `AggregateService` for its validated UTC range and selected Real (default) or Synthetic dataset. It shows totals, adjacent conversions, step observation/noncompletion/pending, elapsed wall-clock time, UTM tuples and mature first-exposure A/B counts without declaring a winner. Administrative text is escaped, including supplied UTM. The public caller uses the same service with fixed inputs and returns only cohort/totals/stage conversions/elapsed/experiment and v3 aggregate fields. Its bar lengths merely represent returned stage totals relative to run count; neither frontend recomputes conversion denominators. Public samples include all synthetic activity in that window, not just seeded rows.

## Data lifecycle

The consumer may delete its server-session visitor's run and all associated events, including landing exposure. This changes subsequent aggregate denominators/numerators; no tombstone identity or historical fabricated counts are retained. The fresh anonymous session can record new activity. Retention uses the visitor's latest run creation/update/restore or associated event and removes eligible groups after more than 30 days, including synthetic samples and landing-only visitors. Legacy linked events refresh their owning visitor; old unattributable events are removed by timestamp. Configurations/admin users remain. Session expiry is separate. `privacy:retention` previews bounded counts unless `--execute` is supplied; release maintenance invokes the bounded policy after its backup step; operational status is recorded in the implementation plan. See [release operation](DEPLOYMENT.md) for exact boundaries, commands and backups.
