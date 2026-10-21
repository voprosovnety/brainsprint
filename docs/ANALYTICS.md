# Analytics definitions

This is the metric reference for Laravel `App\Analytics\AggregateService`. Filament and public demo callers consume its output rather than calculate their own conversions. Only runs pinned to integer schema 3 enter any reported cohort, including totals, steps, timing and UTM groups. The direct-entry journey has no landing screen or A/B assignment.

## Events and authority

| Event | Authority | Meaning |
| --- | --- | --- |
| `funnel_started` | Server, run-creation transaction | Run created |
| `step_viewed` | Client actual exposure | Current or confirmed saved screen shown |
| `step_completed` | Server, answer transaction | Screen answer/acknowledgement confirmed |
| `quiz_confirmed_v3` | Server, confirmation transaction | All 41 main responses confirmed and illustrative sample report generated once |
| `processing_completed_v3` | Server, final preparation transaction | Preparation completed and report offer entered |
| `paywall_viewed_v3` | Client actual exposure | Report offer visibly shown |
| `checkout_viewed_v3` | Client actual exposure | Mock checkout dialog visibly open |
| `mock_unlocked_v3` | Server, acknowledged offer transaction | Simulated report unlock persisted; never a purchase |
| `upsell_decided_v3` | Server, guide transaction | Accept or decline persisted; decision stays in private answers/state |
| `report_viewed_v3` | Client actual exposure | Unlocked report shown on the chosen final screen |
| `funnel_completed_v3` | Server, final acknowledgement | Chosen final acknowledged |

Milestone uniqueness is run/name/triggering screen. View uniqueness is run/name/screen, so checkout and offer exposure are distinct. No purchase, revenue, financial detail, email or answer properties enter events. Social examples in the funnel are illustrative content, separate from the analytics dataset.

## Collection and trust

`VisitorContext` creates a random pseudonymous visitor inside the database-backed Laravel session. Bootstrap restores progress and captures first-touch UTM; it never records an exposure. The client sends views after rendering with a visible document, on visible screen changes and when a hidden document becomes visible again. Image preloading records no exposure.

Ordinary questions may preview while the previous answer saves. Their actual painted exposure waits in a small memory-only metadata outbox until confirmed server progression makes the screen eligible. Answer writes take priority over dispatch. Back/Forward and restoration may deliver the same view again without inflating unique counts.

`POST /api/v1/events` permits only the four client event names above, a UUID `event_id`, an owned `run_id`, a current/completed `step_id`, and optional `properties.surface: "page"`. Offer/checkout views require a paywall screen; report views require an unlocked final. Skipped/future screens, milestone names, timestamps, URLs, answers and context overrides are rejected. The server derives visitor, pinned version, timestamp, UTM and synthetic marker. An actual human view remains a client assertion; this is not bot-proof measurement.

PostgreSQL conflict-safe insertion deduplicates visitor/client UUID and semantic identity independently. Same UUID retries and different UUIDs for the same semantic view are no-ops. Reusing a UUID for another screen is also a no-op. `{accepted:true}` acknowledges processing, not a new insertion. Server milestones remain atomic with progression.

Cookies, CSRF, the same-origin proxy, no-store responses and request bounds protect collection. Exposure has an independent 40/minute session budget; progress has 60/minute. The client outbox makes up to three attempts with the original ID, 1.5-second deadlines and short backoff, with no persistent browser payload. Delivery may be lost on navigation, blocking or outage. Missing views are never inferred from transitions; analytics failure does not change confirmed answers.

Only `utm_source`, `utm_medium`, `utm_campaign`, `utm_content` and `utm_term` are accepted: strings up to 120 characters without control characters. First touch initializes once, including an empty/direct visit. Refresh and later campaign URLs cannot overwrite it. Start can initialize attribution if bootstrap has not done so. Runs and events copy the server-held attribution. No raw URL, referrer, click ID or multichannel attribution is collected.

Local/testing activity is synthetic by server policy; ordinary production traffic is real. Events inherit their run's stored marker. Clients cannot override it.

## Shared service and cohorts

```php
use App\Analytics\AggregateService;
use App\Analytics\Dataset;
use Carbon\CarbonImmutable;

$metrics = app(AggregateService::class)->summarize(
    from: CarbonImmutable::parse('2026-10-01T00:00:00Z'),
    to: CarbonImmutable::parse('2026-10-15T00:00:00Z'),
    asOf: CarbonImmutable::parse('2026-10-16T00:00:00Z'),
    dataset: Dataset::Real,
    observationHours: 24,
);
```

The run-creation cohort is half-open `[from,to)` and at most 93 days, with `to <= asOf`. Outcomes are observed through `asOf` inclusive, including after `to`. Observation is 1–168 hours, default 24. SQL bindings preserve offsets and microseconds. The selected cohort's rows are loaded into memory; this demo service is not a warehouse.

The shared output contains:

- `dataset` and `cohort`: selected dataset, bounds, observation cutoff and hours.
- `totals`: schema-3 run count and distinct observed run counts for every stage below. Runs without events remain in the run count; milestones are never invented.
- `stage_conversions`: adjacent stages in `funnel_started → quiz_confirmed_v3 → processing_completed_v3 → paywall_viewed_v3 → checkout_viewed_v3 → mock_unlocked_v3 → upsell_decided_v3 → report_viewed_v3 → funnel_completed_v3`. Each denominator is observed previous-stage runs; each numerator intersects previous and destination sets. Both guide outcomes qualify. Missing views can make a later milestone count exceed an earlier view count; conversions still use the intersection.
- `steps`: grouped by pinned version and screen ID, for screens with a recorded view/completion. `viewed` and `completed` are unique runs. `completion_among_viewed` intersects both sets; `completed_without_recorded_view` exposes missing telemetry. Step views include ordinary, offer and report exposures; checkout stays a separate stage. First recorded view determines observation age. Branches use only their observed viewers.
- Step `not_completed_after_observation`: viewed but not completed by `asOf`, with at least the observation hours elapsed. `pending_observation`: viewed/uncompleted runs with less elapsed time. Neither proves abandonment; completion can still happen later.
- `quiz_elapsed_seconds`: number and mean wall-clock seconds from creation to authoritative `quiz_confirmed_v3`. Includes pauses/background time; does not measure active play time or ability. Terminal `completed_at` is not a timing substitute.
- `utm`: groups by the complete first-touch attribution tuple; missing/empty is `{}`. Each row gives `runs` and observed `quiz_confirmed_v3`. These labels are user-supplied strings and must be rendered as escaped text in administration.

Each rate has `numerator`, `denominator` and `rate` (0–1 or null when the denominator is zero). Stage rates also have `from`. No interpolation, scoring, payment conversion or statistical winner is produced.

## Public and administrative presentation

Filament uses validated UTC ranges and Real (default) or Synthetic datasets. It displays stage totals/conversions, step observation, elapsed time and escaped UTM tuples. Public `GET /api/v1/demo-analytics` hard-codes Synthetic, rolling `[now-7 days,now)` UTC bounds, `asOf=now` and 24-hour observation. All query parameters are rejected. There is no `all` dataset or caller-selected synthetic flag.

The public allowlist is `dataset`, `cohort`, `totals`, `stage_conversions` and `quiz_elapsed_seconds`. No nested version aggregates or experiment output remain. Responses contain no raw run/visitor/session IDs, answers, UTM strings or administrative details and remain private/no-store. Public bar lengths show returned totals relative to run count; clients do not recompute conversion denominators.

## Synthetic scenarios and lifecycle

`analytics:seed-demo --date=YYYY-MM-DD` appends 24 labeled `demo-v3` visitors per UTC anchor date, with partial paths, both final outcomes and mature/recent observations. Twenty starts are three days before the anchor at noon; four start shortly after anchor midnight. It is idempotent per anchor/visitor, uses a transaction/advisory lock, never publishes a version, and never modifies ordinary visitors. The exact current content-addressed publication must already exist, otherwise the command fails with publication guidance. Older sample rows are not rewritten; unsupported versions are excluded from aggregates and remain subject to deletion/retention. The public sample includes all synthetic activity in its window, including local activity.

Consumers can delete their session-owned run and associated events regardless of schema, changing aggregate denominators/numerators. No fabricated counts or tombstone identity remain. The fresh anonymous session can collect new activity. Retention uses the latest run creation/update/restore or associated event and removes visitor groups after more than 30 days, including synthetic rows and unlinked visitor events. Run-linked events refresh their owning visitor even without visitor context; unattributable events use their timestamp. Configurations/admin users remain. `privacy:retention` previews bounded counts unless `--execute` is supplied; scheduled release maintenance runs the bounded policy after encrypted backup. See [deployment](DEPLOYMENT.md).
