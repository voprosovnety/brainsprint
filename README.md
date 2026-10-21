# BrainSprint

A mobile-first cognitive challenge funnel. BrainSprint is an entertainment demo, not an IQ test or cognitive assessment.

## Experience

Newly published v3 starts immediately with a word to remember. Its 58 configured entries contain 39 quiz responses (including 23 distinct visual problems), two demographic choices, three timed number reveals, four quiz interludes, answer confirmation, report preparation with two binary preference decisions, optional email, a long report offer, simulated checkout, a guide offer and two alternative final destinations. Original local SVG artwork, serif headings and a gray/periwinkle interface accompany the narrow mobile-first flow. No scoring engine measures ability or answer correctness.

Ordinary questions show their configured successor immediately while the previous answer saves in the background; the next answer remains locked until confirmation. Illustrations are preloaded and decoded ahead, and a question is presented only with its complete artwork. Report/unlock transitions still wait for server state. A failed save returns to the exact submitted answer for retry. Refresh restores the confirmed screen; history can review completed screens without skipping ahead. Save errors retain the exact answer for retry. Elapsed time includes pauses; five-second reveals are presentation, not ability measurements. Preparation leads to a server-generated illustrative report. Before simulated unlock, the API returns only a teaser; afterward the owning session can read the full sample and its practical exercises. Guide accept/decline is saved and selects the corresponding final composition.

The commercial screens display a $0.99 introductory offer (comparison $9.99), three introductory days and $39.99 every four weeks as mock terms. The guide displays $1 (comparison $27). Checkout contains inert placeholders and accepts only an acknowledged mock offer ID. No financial data, payment provider, real subscription, account, email delivery or marketing enrollment exists. A server-owned five-minute deadline survives refresh; expiry changes the displayed offer state while leaving the demonstration accessible. The optional email stays only in private answers, never events or aggregates. The quiet information/privacy surface explains sample content and deletion.

Next.js presents the experience and proxies allowlisted routes. Laravel/PostgreSQL own anonymous database sessions, immutable versions, validation, progression, report/offer state and transactional milestones. Existing v1/v2 runs keep their original published configuration and behavior; they are never reset or moved to v3. New v3 runs have no landing or landing experiment. Historical A/B assignment, first-touch UTM and synthetic/real separation remain intact.

A shared aggregate service powers protected read-only Filament and public synthetic analytics. V3 stage conversions have their own eligible cohort and event meanings, separate from legacy completion/boundary metrics. See [consumer contract](contracts/README.md), [v3 interface](contracts/V3.md) and [analytics definitions](docs/ANALYTICS.md). Publication and verification status live only in [the implementation plan](docs/IMPLEMENTATION_PLAN.md).

## Local requirements

- Node.js 24 and npm (Node 22+ supported by this project).
- PHP 8.3–8.5 with PDO PostgreSQL, mbstring, curl, XML, and zip; development used PHP 8.5.3.
- Docker Desktop/Engine with Compose v2 running.
- Ports 3000 (web), 8008 (API), and 55432 (PostgreSQL) available. Port 8000 is deliberately not used.

Resolved dependencies are committed in both lockfiles: Next.js 16.4.2, React 19.3.1, Tailwind 4.3.3, Laravel 13.37.0, Filament 5.10.2 and Livewire 4.4.7. Compatibility was checked against [Next.js installation](https://nextjs.org/docs/app/getting-started/installation), [Tailwind's Next.js guide](https://tailwindcss.com/docs/installation/framework-guides/nextjs), and [Laravel release support](https://laravel.com/docs/13.x/releases). Filament compatibility was checked against its [official v5 installation requirements](https://filamentphp.com/docs/5.x/introduction/installation) (PHP 8.2+, Laravel 11.28+, Tailwind 4.1+); its packaged CSS, JS and Inter font are served locally. PostgreSQL is pinned to `17.6-alpine`.

## First setup

From the repository root:

```sh
./infra/setup.sh
```

The script installs project-local Composer (verifying its installer checksum), locked PHP/npm dependencies, creates local environment files only when absent, generates a random database password and Laravel key without printing them, starts PostgreSQL, runs migrations, and publishes the current validated funnel configuration. No global tools or paid services are installed. Docker image initialization creates an isolated `brainsprint_test` database alongside `brainsprint`.

`apps/api/.env.example` contains placeholders; `apps/web/.env.example` contains only the local API origin. Real `.env` files, tooling, dependencies, and database volumes are ignored. The API's normal database session lasts seven days of inactivity. Use the same hostname consistently: cookies for `127.0.0.1` and `localhost` are separate. Local cookies use HTTP; production enforces secure host-only cookies and HTTPS URL generation; use the release settings below.

## Start and stop

Start Laravel in one terminal:

```sh
cd apps/api
php artisan serve --host=127.0.0.1 --port=8008
```

Start Next.js in another terminal, from the repository root:

```sh
npm run dev --prefix apps/web
```

Open [BrainSprint](http://127.0.0.1:3000). The API health endpoint is [Laravel health](http://127.0.0.1:8008/up); consumer requests use the web origin. Both application servers and the database port bind to loopback.

Stop each application server with Ctrl-C. Stop PostgreSQL while retaining its volume:

```sh
docker compose --env-file apps/api/.env -f infra/compose.yaml down
```

To resume PostgreSQL, run `./infra/setup.sh` again (existing secrets/data are preserved). To apply later migrations or publish a changed configuration:

```sh
cd apps/api
php artisan migrate
php artisan db:seed
```

Configuration source for new publication is `apps/api/resources/funnel/brainsprint-v3.json`; v1/v2 sources and published versions remain immutable. The seeder validates screen IDs, enabled screens, content, bounded variants/parameters and explicit transitions, then inserts a content-addressed immutable version. V3 supports explicit default transitions and the guide decision branch; publication validates all 58 semantic screens and the two complete final paths. V2 retains its tutorial branch validator. Broken targets, cycles, invalid bounded parameters and incomplete paths are rejected. Array order does not determine progression. To change the experience, edit the source and publish with the seeder; new sessions use it, while existing runs retain their original version. After pulling schema changes, run `php artisan migrate`; migrations preserve saved answers/results and do not backfill invented analytics context.

Historical T1 Memory-only runs continue their original Memory → result path. Their saved answers, result values and completion timestamps are preserved; they do not acquire later screens. `memory-v1.json` remains as a historical fixture. The two preexisting development runs were compared before and after T2 publication; their combined answer/result digest was unchanged.

One run is retained per browser session even after completion. Starting again reopens its saved state. The quiet **About this demo & your data** disclosure explains storage/telemetry and provides permanent deletion of this browser visitor’s run, snapshot and events, followed by a new anonymous session. Clearing cookies starts a separate anonymous session and loses browser access to the previous run; no recovery or new-attempt feature exists. For local review of T2 while keeping an existing T1 session, use a separate browser profile or [localhost](http://localhost:3000); continue using that hostname consistently.

## Administrative reads and demo analytics

After setup/migration, create or rotate a local administrator from the repository root:

```sh
cd apps/api
php artisan admin:local reviewer@brainsprint.local
```

The CLI generates a random password, stores only its hash in `admin_users`, and writes the access URL/email/password to the ignored project-local `.local/admin-access.json` with mode `0600`. It prints only the private file path. Use that file locally to sign in at [Admin](http://127.0.0.1:8008/admin). The email is an identifier, not a mail dependency. Repeating the command rotates the password and revokes that administrator's stored sessions and remember token. There are no default credentials, public registration or password-reset mail. Provisioning is refused outside local/testing; release provisioning uses the explicit secret-file command documented in [release operation](docs/DEPLOYMENT.md).

The admin guard, host-only `/admin` cookie (`brainsprint_admin_session`, SameSite Strict, 2-hour session inactivity; optional remember-me stays in the same admin scope) and `admin_sessions` table are separate from anonymous progress. Admin Livewire uses `/admin/livewire/update`; default component mutations and unused file routes are closed. Consumer requests on port 3000 keep their original cookie and CSRF behavior even on the same hostname. Admin login/logout never becomes a consumer account. Use HTTPS and secure cookies for release; this setup uses ordinary per-request PHP, without Octane or persistent application workers.

The panel offers real-data analytics by default, an explicitly synthetic selection, UTC date/range and observation inputs, read-only runs/details, events, immutable configurations, and an event-derived anonymous visitor overview. Visitor pseudonyms are not login identities or active-session counts; raw framework session payloads are never shown. All writes, exports and publishing actions are absent and model policies deny mutation.

Create a reusable sample:

```sh
cd apps/api
php artisan analytics:seed-demo
# Optional historical UTC anchor, never in the future:
php artisan analytics:seed-demo --date=2026-10-19
```

The separate `demo-v3:YYYY-MM-DD:*` batch appends 24 synthetic runs through different quiz, offer and final states, including both guide choices. It uses the already-published v3 source, no landing assignment, and the same report/state helper as ordinary progression. Historical v2 scenarios remain intact; a fresh v3-only installation skips the legacy batch when that version is not published. No sample command publishes a version.

The historical daily `demo-v1:YYYY-MM-DD:*` batch contains 24 illustrative landing exposures and 18 runs across both variants, with completed, unfinished and conditional paths. Twenty exposures occur at noon three UTC days before the anchor; four occur at anchor midnight so they are pending during that UTC day. All run/event timestamps follow those exposures. The source's existing content-addressed configuration is pinned; it must already be published by setup/`db:seed`. This command never publishes configurations; ordinary publication rows and existing answers/results are not overwritten. Repeating the same date is a no-op, including after source changes. A later anchor appends a new scenario instead of moving historical timestamps. Old samples naturally leave the rolling window; release maintenance refreshes the sample once daily. No persistent worker or keep-alive traffic is needed.

Open [Demo analytics](http://127.0.0.1:3000/demo-analytics), also linked from the funnel footer. It presents a seven-day synthetic-only aggregate sample (seeded scenarios plus local synthetic activity), stage counts/conversions, mature/pending A/B denominators and wall-clock elapsed time. The shared definitions are in [ANALYTICS.md](docs/ANALYTICS.md). Public callers cannot select real data, arbitrary dates, sessions or raw records. Empty, loading, retry and unavailable states are explicit; no statistical winner is claimed.

## Focused verification

```sh
cd apps/api
php artisan test --compact --filter='MemoryRoundTest|FunnelProgressionTest|AnalyticsExperimentTest|AnalyticsAggregateTest|AdminDemoAnalyticsTest|ReleaseLifecycleTest|SharedCacheTest'
```

Run executable verification only when explicitly requested. The existing regression suites explicitly pin immutable v2 fixtures; they do not constitute v3 acceptance.

Tests require the separate local PostgreSQL database `brainsprint_test`; a guard refuses other database names, remote hosts, and database URLs before migrations. They cover both tutorial paths, all mechanics, publication validation, reconfiguration, historical compatibility, ownership, forbidden transitions, malformed/conflicting answers, persisted results, terminal completion, duplicate milestones and transactional rollback. Analytics suites additionally cover assignment, actual exposures, first-touch UTM, client trust, semantic/UUID deduplication, CSRF, exact real/synthetic cohorts, legacy/branch denominators and timezone/microsecond boundaries. Test data rolls back.

From the repository root, with both servers running:

```sh
python3 infra/checks/http-smoke.py
# Requires the private admin:local file above; prints no credentials:
python3 infra/checks/admin-demo-http.py
npm run build --prefix apps/web
npm run typecheck --prefix apps/web
```

The HTTP check supports the complete pinned v2/v3 funnel through Next.js (respecting the v3 request budget and using an optional `BRAINSPRINT_GUIDE_CHOICE=decline` for its alternate final) with real cookies, CSRF rejection, foreign-origin rejection, concurrent start/completion retries, restoration, cross-session ownership and independent rate limits. It creates a small synthetic development run; no cookies or secrets are printed or saved. Development Laravel's server serializes requests; the checks send overlapping requests, while PostgreSQL uniqueness/row locking enforce retry safety. This is not a load test.

Verification evidence is recorded in the [T2 delivery notes](docs/tasks/T2_COMPLETE_FUNNEL.md), [T3 delivery notes](docs/tasks/T3_ANALYTICS_EXPERIMENT.md#delivery-evidence), and [T4 delivery notes](docs/tasks/T4_ADMIN_DEMO_ANALYTICS.md#delivery-evidence). GitHub Actions runs the focused PostgreSQL suites, production build and typecheck. Current hosted acceptance and public deployment status are recorded exclusively in [IMPLEMENTATION_PLAN.md](docs/IMPLEMENTATION_PLAN.md).

## Release package and data lifecycle

The [hosting comparison](docs/HOSTING_OPTIONS.md) explains the strict $0/month deployment boundary and free-provider limitations. Paid comparisons are historical references outside that budget. See the [implementation plan](docs/IMPLEMENTATION_PLAN.md) for verified deployment status and public URLs.

[Release operation](docs/DEPLOYMENT.md) documents the approved free deployment boundary, runtime variables, explicit migration/publication/seed/admin commands, maintenance, backups and rollback. The Vercel PHP adapter preserves ordinary per-request Laravel, PostgreSQL and the same-origin Next.js proxy; the Apache image remains an alternative package. Vercel Hobby requires personal noncommercial use. Do not enable paid resources or automatic paid overage.

```sh
cd apps/api
php artisan privacy:retention
# Explicit deletion only after reviewing the preview and backup policy:
php artisan privacy:retention --execute --limit=500
# Release only; secret file prepared through a private operator channel:
php artisan admin:release operator@example.com --password-file=/run/secrets/brainsprint-admin
```

Retention defaults to non-destructive preview. It selects visitors whose latest stored run creation/update/restore or associated event is more than 30 days old; landing-only exposures and synthetic samples follow the same policy. It separately removes expired consumer/admin sessions while preserving live sessions, administrators and immutable versions. The bounded execute path is tested only on isolated fixtures. Scheduled release maintenance and encrypted backup/retention procedures are described in [release operation](docs/DEPLOYMENT.md); current operational status is recorded only in the implementation plan. Consumer deletion only affects the session-owned visitor, including landing events, rotates the anonymous session and preserves admin/other visitors. A new visit can begin new collection.

## Structure and boundaries

| Path | Responsibility |
| --- | --- |
| `apps/web/` | Next.js App Router, React, TypeScript, Tailwind; presentation and same-origin proxy |
| `apps/api/` | Laravel progression, publication, sessions, validation, persistence, result generation |
| `contracts/` | OpenAPI 3.1 consumer contract and invariants |
| `infra/` | Local PostgreSQL, setup, and focused HTTP check |
| `docs/` | Architecture, coordinating implementation plan, and task briefs |

No real payments, subscriptions, consumer accounts, scoring engine, Reaction Speed, training product, Redis, workers, queues, or PostHog. Legacy A/B assignment precedes rendering; v3 has direct entry and no new assignment; client analytics delivery has bounded retries and a separate rate budget. Bootstrap alone does not claim exposure. Ordinary aggregates exclude synthetic data; local/testing collection is synthetic by server policy. Session and configurable global application rate budgets apply; production API calls require a server-only proxy secret. There is no configured edge DDoS service. The 30-day lifecycle, bounded preview/execute retention and deployment assumptions are documented in [release operation](docs/DEPLOYMENT.md). Public deployment is authorized only within the strict $0/month boundary; paid resources require separate approval.

See [architecture](docs/ARCHITECTURE.md), [consumer contract](contracts/README.md), [T1 brief](docs/tasks/T1_VERTICAL_SLICE.md), and the coordinator-owned [implementation plan](docs/IMPLEMENTATION_PLAN.md).
