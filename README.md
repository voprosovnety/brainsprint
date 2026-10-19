# BrainSprint

A mobile-first cognitive challenge funnel. BrainSprint is an entertainment demo, not an IQ test or cognitive assessment.

## Implemented: T1

A finished landing → one Visual Memory round → persisted illustrative result. Next.js renders the experience and proxies four allowlisted consumer routes to Laravel. PostgreSQL stores anonymous sessions, immutable configuration versions, runs with answers/results, and transactional milestone events.

Refresh restores confirmed progress in the same browser. An unfinished reveal/selection starts again with a notice. Duplicate starts and identical completions return the same state; conflicting confirmed answers are rejected. Result values are randomly generated once on the server, never depend on answers, and are explicitly illustrative. Local/testing runs and their events are marked synthetic.

The UI supports touch and keyboard, visible focus, reduced motion, loading, saving, retryable errors, and mobile/desktop layouts. The result is the conclusion of this memory preview. There is no checkout or payment simulation.

Still planned: onboarding, further challenge rounds/mechanics, tutorial branching, the final demo boundary, A/B assignment, attribution, exposure events, analytics, and Filament. No public deployment exists.

## Local requirements

- Node.js 24 and npm (Node 22+ supported by this project).
- PHP 8.3–8.5 with PDO PostgreSQL, mbstring, curl, XML, and zip; development used PHP 8.5.3.
- Docker Desktop/Engine with Compose v2 running.
- Ports 3000 (web), 8008 (API), and 55432 (PostgreSQL) available. Port 8000 is deliberately not used.

Resolved dependencies are committed in both lockfiles: Next.js 16.4.2, React 19.3.1, Tailwind 4.3.3, Laravel 13.37.0. Compatibility was checked against [Next.js installation](https://nextjs.org/docs/app/getting-started/installation), [Tailwind's Next.js guide](https://tailwindcss.com/docs/installation/framework-guides/nextjs), and [Laravel release support](https://laravel.com/docs/13.x/releases). PostgreSQL is pinned to `17.6-alpine`.

## First setup

From the repository root:

```sh
./infra/setup.sh
```

The script installs project-local Composer (verifying its installer checksum), locked PHP/npm dependencies, creates local environment files only when absent, generates a random database password and Laravel key without printing them, starts PostgreSQL, runs migrations, and publishes the initial Memory configuration. No global tools or paid services are installed. Docker image initialization creates an isolated `brainsprint_test` database alongside `brainsprint`.

`apps/api/.env.example` contains placeholders; `apps/web/.env.example` contains only the local API origin. Real `.env` files, tooling, dependencies, and database volumes are ignored. The API's normal database session lasts seven days of inactivity. Use the same hostname consistently: cookies for `127.0.0.1` and `localhost` are separate. Local cookies use HTTP; production must enable HTTPS and `SESSION_SECURE_COOKIE=true`.

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

Configuration source is `apps/api/resources/funnel/memory-v1.json`. Publication inserts a content-addressed version, leaving published rows and existing runs unchanged. T1 keeps one run per browser session even after completion; the start button then reopens its saved result. Clearing site cookies starts a separate anonymous session and loses access to the previous run. No recovery or new-attempt feature is implemented.

## Focused verification

```sh
cd apps/api
php artisan test --compact --filter=MemoryRoundTest
```

Tests require the separate local PostgreSQL database `brainsprint_test`; a guard refuses other database names, remote hosts, and database URLs before migrations. They cover persisted answers/results, start/complete retries, conflicting answers, ownership, bounds, pinned configuration, and rollback when a completion event fails. Test data rolls back.

From the repository root, with both servers running:

```sh
python3 infra/checks/http-smoke.py
npm run build --prefix apps/web
npm run typecheck --prefix apps/web
```

The HTTP check exercises real cookies through Next.js, CSRF rejection, foreign-origin rejection, concurrent retries, reload restoration, cross-session ownership, and independent rate limits. It creates a small synthetic development run; no cookies or secrets are printed or saved. Development Laravel's server serializes requests; the checks send overlapping requests, while PostgreSQL uniqueness/row locking enforce retry safety. This is not a load test.

Actual T1 verification: 5 PostgreSQL feature tests / 53 assertions, real HTTP check, production frontend build and TypeScript, plus real browser inspection of landing, Memory, and result at 390×844 and 1440×1000. Keyboard/click selection, unfinished-round reload, and stable result after reload were exercised. Independent review's shared proxy-IP rate-limit finding was fixed and verified with two sessions. Screenshots were inspected without retaining artifacts. GitHub Actions runs PostgreSQL feature tests and the frontend build; its hosted run has not yet been observed.

## Structure and boundaries

| Path | Responsibility |
| --- | --- |
| `apps/web/` | Next.js App Router, React, TypeScript, Tailwind; presentation and same-origin proxy |
| `apps/api/` | Laravel progression, publication, sessions, validation, persistence, result generation |
| `contracts/` | OpenAPI 3.1 consumer contract and invariants |
| `infra/` | Local PostgreSQL, setup, and focused HTTP check |
| `docs/` | Architecture, coordinating implementation plan, and task briefs |

No payments, subscriptions, accounts, scoring engine, Reaction Speed, training product, Redis, workers, queues, or PostHog. Pattern/logic labels on the illustrative snapshot do not imply those mechanics are implemented. No A/B assignment occurs yet. The API rate limit is per server session; release work must add deployment-specific edge protection and retention/deletion policy. Public hosting and paid services require Product Owner approval.

See [architecture](docs/ARCHITECTURE.md), [consumer contract](contracts/README.md), [T1 brief](docs/tasks/T1_VERTICAL_SLICE.md), and the coordinator-owned [implementation plan](docs/IMPLEMENTATION_PLAN.md).
