# BrainSprint

A mobile-first cognitive challenge funnel. BrainSprint is an entertainment demo, not an IQ test or cognitive assessment.

## Experience

One configurable, English demo: landing → onboarding → Memory practice → two Visual Memory rounds → two Pattern Recognition rounds → two Logical Reasoning rounds → illustrative snapshot → final demo boundary. A correct practice answer continues immediately; another answer opens a short guided explanation and rejoins the same challenges. Laravel decides the branch from the saved response.

Memory reconstructs a hidden grid, Pattern chooses the next visual element, and Logic arranges cards by clues with tap-to-swap, keyboard arrows, explicit move controls and dragging. The light-blue interface supports mobile/desktop, visible focus, reduced motion, saving and retry states. Expect roughly 3–4 minutes at a relaxed pace; answers have no time limit.

Next.js presents the experience and proxies four allowlisted consumer routes. Laravel/PostgreSQL own database-backed anonymous sessions, immutable published versions, validated progression, answers, results and transactional milestones. Three illustrative values are generated once after the challenges and never depend on answers. The disclaimer is prominent: **Illustrative demo values — not calculated from your answers.** This is entertainment, with no IQ, diagnosis or performance score.

The final consumer screen closes the experience with a real **Finish demo** action. It persists terminal completion separately from quiz completion. There are no prices, subscriptions or checkout. Refresh restores confirmed progress; an unfinished reveal starts again with a notice. Back/Forward reviews saved steps without submitting again, and cannot skip ahead. A save failure retains the submitted answer for an identical retry or offers restoration from the server.

Experiments, attribution, client exposure events and aggregate analytics belong to T3; Filament and the synthetic public view belong to T4. No public deployment exists.

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

The script installs project-local Composer (verifying its installer checksum), locked PHP/npm dependencies, creates local environment files only when absent, generates a random database password and Laravel key without printing them, starts PostgreSQL, runs migrations, and publishes the current validated funnel configuration. No global tools or paid services are installed. Docker image initialization creates an isolated `brainsprint_test` database alongside `brainsprint`.

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

Configuration source is `apps/api/resources/funnel/brainsprint-v2.json`. The seeder validates screen IDs, enabled screens, content, bounded variants/parameters and explicit transitions, then inserts a content-addressed immutable version. Default transitions plus one tutorial incorrect-answer branch are supported; broken targets, cycles, incomplete paths and branches that do not rejoin are rejected. Array order does not determine progression. To change the experience, edit the source and publish with the seeder; new sessions use it, while existing runs retain their original version. No database reset or migration is needed for T2.

Historical T1 Memory-only runs continue their original Memory → result path. Their saved answers, result values and completion timestamps are preserved; they do not acquire later screens. `memory-v1.json` remains as a historical fixture. The two preexisting development runs were compared before and after T2 publication; their combined answer/result digest was unchanged.

One run is retained per browser session even after completion. Starting again reopens its saved state. Clearing cookies starts a separate anonymous session and loses browser access to the previous run; no recovery or new-attempt feature exists. For local review of T2 while keeping an existing T1 session, use a separate browser profile or [localhost](http://localhost:3000); continue using that hostname consistently.

## Focused verification

```sh
cd apps/api
php artisan test --compact --filter='MemoryRoundTest|FunnelProgressionTest'
```

Tests require the separate local PostgreSQL database `brainsprint_test`; a guard refuses other database names, remote hosts, and database URLs before migrations. They cover both tutorial paths, all mechanics, publication validation, reconfiguration, historical compatibility, ownership, forbidden transitions, malformed/conflicting answers, persisted results, terminal completion, duplicate milestones and transactional rollback. Test data rolls back.

From the repository root, with both servers running:

```sh
python3 infra/checks/http-smoke.py
npm run build --prefix apps/web
npm run typecheck --prefix apps/web
```

The HTTP check completes the full funnel through Next.js with real cookies, CSRF rejection, foreign-origin rejection, concurrent start/completion retries, restoration, cross-session ownership and independent rate limits. It creates a small synthetic development run; no cookies or secrets are printed or saved. Development Laravel's server serializes requests; the checks send overlapping requests, while PostgreSQL uniqueness/row locking enforce retry safety. This is not a load test.

Verification results are recorded in the [T2 delivery notes](docs/tasks/T2_COMPLETE_FUNNEL.md). GitHub Actions runs the focused PostgreSQL feature tests and frontend production build; its hosted execution has not been observed in this session.

## Structure and boundaries

| Path | Responsibility |
| --- | --- |
| `apps/web/` | Next.js App Router, React, TypeScript, Tailwind; presentation and same-origin proxy |
| `apps/api/` | Laravel progression, publication, sessions, validation, persistence, result generation |
| `contracts/` | OpenAPI 3.1 consumer contract and invariants |
| `infra/` | Local PostgreSQL, setup, and focused HTTP check |
| `docs/` | Architecture, coordinating implementation plan, and task briefs |

No payments, subscriptions, accounts, scoring engine, Reaction Speed, training product, Redis, workers, queues, or PostHog. No A/B assignment or exposure tracking occurs yet. The API rate limit is per server session; release work must add deployment-specific edge protection and retention/deletion policy. Public hosting and paid services require Product Owner approval.

See [architecture](docs/ARCHITECTURE.md), [consumer contract](contracts/README.md), [T1 brief](docs/tasks/T1_VERTICAL_SLICE.md), and the coordinator-owned [implementation plan](docs/IMPLEMENTATION_PLAN.md).
