# Release operation

Current public URLs, account state and acceptance results belong exclusively in [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md). This document describes configuration and operator procedures.

## Permanent free boundary

The complete personal, noncommercial demo uses two Vercel Hobby projects and Neon Free PostgreSQL 17. No card, paid resources, trial credits or keep-alive traffic are required. Hobby quotas are shared across the two projects; exhaustion can interrupt the demo. Neon includes 1 GB storage, 100 CU-hours and 5 GB monthly egress, with mandatory sleep after five idle minutes and a short six-hour recovery window. Free services provide no application availability guarantee. Recheck provider terms before changing plans or usage. Sources: [Vercel Hobby](https://vercel.com/docs/plans/hobby), [Neon plans](https://neon.com/docs/introduction/plans).

Render Free required card verification in the actual account, so it is excluded. serv00 registration must be manual under its terms and its account expires after 90 days without an owner login; it is not needed by this package. Vercel Container Registry storage has no verified permanent free allowance, so this package uses no container registry. Historical paid comparisons remain in [HOSTING_OPTIONS.md](HOSTING_OPTIONS.md); they authorize no spending.

## PHP function package

Vercel backend root: `apps/api`; Node.js 22 is required by the pinned community runtime `vercel-php@0.9.0`. The [runtime's source and requirements](https://github.com/vercel-community/php) are separate from Laravel and must be reviewed when upgrading. The function region is Frankfurt (`fra1`), matching Neon. This is ordinary per-request Laravel, without Octane or a persistent application worker. The runtime launches its own PHP request server; the existing Apache Docker image is an alternative package, not a Vercel image.

`vercel.json` sends every path through `api/index.php`. The adapter serves only allowlisted generated Filament CSS/JS/fonts under `public/`; real-path checks prevent traversal. Source, environment files, tests and dependencies are never public static routes. `/release.json` exposes only the public source revision for release confirmation. `/up` checks Laravel boot; `/admin/login` also checks session/database readiness.

The builder installs `composer.lock` without development packages and invokes `scripts/vercel-build.php` through Composer's `vercel` script. That script verifies required extensions and publishes Filament assets without migrations or configuration publication. Compiled views and temporary files use `/tmp/brainsprint`; sessions, shared rate budgets and locks use PostgreSQL. No persistent disk or queue service is required. `.vercelignore` and function exclusions keep local credentials and build output out of uploads.

Set production-only private backend variables:

| Variables | Value/requirement |
| --- | --- |
| `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL` | `BrainSprint`, `production`, `false`, canonical backend HTTPS origin |
| `APP_KEY` | Stable private Laravel key; never rotate during an ordinary release |
| `DB_CONNECTION`, `DB_PORT`, `DB_URL` | `pgsql`, `5432`, direct Neon `/brainsprint` URI with `sslmode=verify-full` |
| `PGOPTIONS` | `endpoint=<Neon endpoint ID>`; required because the community runtime's libpq lacks SNI |
| `PGCONNECT_TIMEOUT` | `10` |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_COOKIE` | `database`, `10080`, `true`, `brainsprint_session` |
| `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` | `true`, `lax` |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `LOG_CHANNEL` | `database`, `sync`, `stderr` |
| `LOG_STDERR_FORMATTER` | `Monolog\Formatter\JsonFormatter`; compact private errors without oversized traces |
| `CONSUMER_PROXY_SECRET` | Stable random private secret shared only with the frontend server |
| `VERCEL_PHP_DOCROOT` | `public` |

The adapter selects a readable operating-system CA bundle for libpq. Do not weaken TLS to work around endpoint routing or certificate errors. CLI release jobs use a current PostgreSQL client and an explicit OS CA path in `RELEASE_DATABASE_URL`; they do not need the legacy-runtime compatibility parameter.

The proxy gate runs before anonymous sessions. Filament uses separate authentication and host-only `/admin` cookies; the consumer origin cannot proxy admin or Livewire routes. Production enforces secure cookies and the canonical HTTPS URL. No wildcard forwarded-host/IP trust is configured. Database cache tables share locks and throttles across function instances; they do not provide edge DDoS protection.

## Frontend package

Vercel frontend root: `apps/web`, Next.js, Node.js 24, install `npm ci --no-audit --no-fund`, build `npm run build`, region `fra1`. Use the full Next.js runtime, not static export. Set production-only server variables:

- `API_ORIGIN`: exact backend HTTPS origin, without a trailing slash.
- `WEB_ORIGIN`: exact canonical frontend HTTPS origin.
- `CONSUMER_PROXY_SECRET`: the backend's stable private proxy secret.
- `API_UPSTREAM_TIMEOUT_MS=45000`: bounded initial database/function wake tolerance.

Never prefix these variables with `NEXT_PUBLIC_`. Preview environments receive no production database, key or proxy secret. Only release branches are enabled for automatic Git deployment. Consumer loading states explain a slow first request; saved progress is server-owned and independent of function instances.

## Explicit release and rollback

The production GitHub environment stores `RELEASE_APP_KEY`, `RELEASE_DATABASE_URL`, `RELEASE_PROXY_SECRET` and `RELEASE_ADMIN_PASSWORD`; environment variables hold `RELEASE_API_ORIGIN` and `RELEASE_ADMIN_EMAIL`. Test jobs never receive production credentials. `release-target.php` checks the dedicated Neon database name and verified TLS before operator work. The public repository uses standard GitHub-hosted runners, which have free runtime; paid runners are not enabled.

`Release operations` supports manual `initialize`, `release`, `provision-admin` and `maintenance-preview`. Initialization runs migrations, append-only validated configuration publication and an idempotent labeled synthetic seed. Administrator provisioning reads a temporary mode-0600 password file, persists only its hash and removes the file. No default password, public registration or automatic bootstrap exists. Local access details belong only in ignored mode-0600 `.local/release-admin-access.json`.

Automatic publication is gated by repository variable `RELEASE_DEPLOY_ENABLED=true` and a successful `Funnel checks` run for this repository's `main` commit. Superseded automatic runs stop. A serialized release applies migrations/publication, then advances `codex/release-api` to that exact checked SHA. Vercel production tracks this branch. The publisher waits for the matching public revision and healthy Laravel/admin login before advancing `codex/release-web`; the frontend project tracks that branch. No long-lived Vercel account token or production deploy credential is stored in GitHub. The official GitHub App is restricted to this repository. Normal branches/PRs cannot deploy to these production environments.

Never run test suites, `migrate:fresh`, `db:wipe` or a reset against Neon. Keep migrations compatible with the currently served revision. Take a verified encrypted backup before an operator change that can affect existing data. To roll back, redeploy a compatible prior code revision with stable runtime keys; do not automatically reverse migrations or erase immutable configuration/history. Restoring a database is a separate deliberate operator decision because later writes can be lost.

## Daily maintenance and backups

`Release maintenance`, enabled by `RELEASE_MAINTENANCE_ENABLED=true`, runs daily at 03:23 UTC (10:23 Vietnam time) and supports manual dispatch. GitHub can delay scheduled jobs and disables public-repository schedules after prolonged repository inactivity (normally 60 days); inspect Actions failures and re-enable a disabled schedule ([GitHub schedule policy](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/disable-and-enable-workflows)). This does not keep Vercel/Neon awake between real requests.

Before retention, the job creates a compressed PostgreSQL dump using the pinned PostgreSQL 17.6 client image and encrypts it with OpenSSL CMS AES-256-GCM and RSA-OAEP/SHA-256. Only the public recipient certificate is committed. The private recovery key exists only in ignored mode-0600 `.local/release-backup-key.pem`; preserve an independent owner-controlled copy. Losing that key makes encrypted artifacts unrecoverable. Runtime keys/credentials also need their own private recovery copy; the database dump does not replace them.

Only ciphertext is uploaded as `release-backup-<run ID>`, retained for three days. A 20 MiB dump cap and a 100 MiB repository artifact guard bound storage; excessive size stops the job before retention. All backup artifacts in this public repository must be assumed downloadable by others; confidentiality depends on the private recovery key. The upload must succeed before deletion runs. Plaintext exists only in a mode-0700 temporary directory and is removed automatically; no secrets or raw dump are printed.

The job then executes `privacy:retention --execute --limit=500` and refreshes the labeled daily synthetic analytics sample. Retention preserves configurations, administrators and active visitors, and removes visitor groups only after more than 30 days without recorded activity. Sessions expire after their configured inactivity; expired shared cache/locks are also pruned with expiration rechecked at deletion. Each category is bounded by the limit. `privacy:retention --limit=500` previews without deleting. Application activity can be newer than an exposure, so an old landing event alone does not make an active visitor eligible.

For recovery, download the desired encrypted artifact to a private local directory, then decrypt with OpenSSL 3:

```sh
umask 077
openssl cms -decrypt -binary -inform DER -in brainsprint-backup.cms \
  -inkey .local/release-backup-key.pem -out .local/recovery.dump
```

Restore first into a newly created isolated disposable PostgreSQL database using `pg_restore --exit-on-error --no-owner --no-acl`, validate the configuration/sample/admin counts and remove plaintext afterward. Never point a rehearsal at existing development or production data. The encrypted artifact is authenticated: decryption must fail if it has been altered. Actual rehearsal evidence and schedule acceptance are recorded in the implementation plan.

## Reproduce the local production-package check

Local-only fixture setup builds the production image and uses a separate `brainsprint_test` PostgreSQL container with tmpfs and no database host port. It never mounts the development volume. `browser_api` uses the same production image in local HTTP mode with a distinct fixture cookie, for browser interactions without changing OS/certificate settings.

```sh
./infra/checks/prepare-release-fixture.sh
python3 infra/checks/release-package.py
```

The package check deliberately provisions only its disposable admin DB using the existing ignored local credential file; it tests Apache health/document root/static assets, production secret gate, secure scoped cookies, HTTPS URL generation, CSRF, deletion preserving admin, synthetic-only metrics, and retention preview. It sends manual cookies over loopback to simulate a TLS terminator: **this does not test real TLS/certificates**. Destructive retention is proved by isolated PostgreSQL tests, never existing development history.

For interactive review start a temporary production-built Next.js frontend with `API_ORIGIN=http://127.0.0.1:8012 WEB_ORIGIN=http://localhost:3011` and bind `localhost:3011`. Reuse the HTTP smoke with `BRAINSPRINT_WEB_ORIGIN=http://localhost:3011 BRAINSPRINT_SESSION_COOKIE=brainsprint_release_fixture`. Stop temporary processes and remove the disposable services afterward:

```sh
docker compose --env-file .local/t5-container.env -f infra/production/compose.check.yaml down
```

The fixture environment is ignored, generated privately (0600), and contains only disposable secrets. It is not a release secret store. Local fixture logs/build output are temporary and not committed. Hosted checks, provider configuration, billing and public release remain separate external operations.
