# Release operation

This repository packages Next.js for Vercel and ordinary per-request Apache/PHP for Railway, with PostgreSQL on the same Railway private network. No cloud resources, public domains or recurring maintenance are configured by this repository. Local execution does not verify provider ingress, certificates, backups or hosted CI.

## Approval and cost boundary

Before publishing, the owner must approve the public frontend/backend origins, provider account access, repository integration, Railway billing and a resource budget. Target approximately **$5–10/month total at light usage**, not a fixed price: Railway's [Hobby subscription contributes $5 toward resource usage](https://docs.railway.com/pricing/plans); CPU, memory, database storage, egress and taxes can increase the bill. Set provider usage alerts/limits after approval and observe actual usage. [Private database networking](https://docs.railway.com/pricing/faqs) avoids unnecessary public egress.

[Vercel Hobby is for personal noncommercial use](https://vercel.com/docs/plans/hobby). The owner must confirm this demo qualifies; commercial/portfolio lead-generation use may require a different approved plan and budget. No paid plan is activated here. No repository visibility change is needed.

## Backend package

From the repository root:

```sh
docker build -f infra/production/Dockerfile -t brainsprint-api:release .
```

The image pins its PHP and Composer bases by digest and installs locked production dependencies. Apache serves **only `apps/api/public`**; Filament CSS/JS/fonts are published locally by the Composer script. The application has no development server, Octane, queue worker or scheduler process. The entrypoint validates minimum release settings, caches configuration using runtime secrets and then replaces itself with Apache. Docker/Railway stop uses Apache's graceful `SIGWINCH`; PHP requests have a 30-second execution bound. Health checks use `/up` (application boot, not a database readiness probe); Railway must route its `healthcheck.railway.app` Host and `PORT` to the container. See [Railway health checks](https://docs.railway.com/deployments/healthchecks).

Railway repository root: repository root, config: `railway.json`, Dockerfile: `infra/production/Dockerfile`, one replica. No start-command override is needed (`brainsprint-start` → `apache2-foreground`). Do not put secrets in build arguments. Do not enable automatic migrations or publication in the start command.

Create a PostgreSQL service only after approval. Use its **private** host/port/credentials for the backend; do not expose a public database listener for the application. The backend needs public HTTPS because Vercel's server-side proxy and the administrator use it; a public consumer API request requires the shared proxy secret before a session is created. Admin is accessed directly at `https://<backend>/admin`, never through the Next.js proxy.

Use `infra/production/api.env.example` as the variable-name reference; all its values are placeholders:

| Variables | Requirement |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | `production`, `false`, exact canonical backend HTTPS origin |
| `APP_KEY` | Stable generated Laravel key, private runtime variable; generate with `php artisan key:generate --show` only into a secret manager, never chat/logs |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `pgsql` and Railway private PostgreSQL connection; no production connection during local checks |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_COOKIE` | `database`, `10080` minutes, `true`, `brainsprint_session` |
| `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` | `true`, `lax`; production also enforces secure, host-only cookies in code |
| `CONSUMER_PROXY_SECRET` | Random secret at least 32 bytes; same backend/Vercel server variable, never `NEXT_PUBLIC_*` |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `LOG_CHANNEL`, `PORT` | `file`, `sync`, `stderr`, Railway's supplied numeric port (default 8080) |
| `TRUSTED_PROXIES` | Optional comma-separated **verified ingress CIDRs**; empty means no forwarded headers are trusted. Only `X-Forwarded-Proto` can be trusted; forwarded host/IP/identity are never used |
| `CONSUMER_REQUESTS_PER_MINUTE`, `ANALYTICS_REQUESTS_PER_MINUTE`, `CONSUMER_GLOBAL_REQUESTS_PER_MINUTE` | Default session budgets 60/40; global budgets 600 each, configurable integers at least 1 |

Production URLs are forced to the configured HTTPS backend origin, debug output is disabled and cookies are secure regardless of inbound HTTP at the terminator. Trust only verified proxy CIDRs if request-level HTTPS detection is required; confirm actual ingress headers after deployment. Do not set arbitrary forwarded identity or a wildcard forwarded-host trust. Keep default consumer `web` and separate `admin` guards; no consumer accounts exist. No cross-origin cookie sharing/CORS is needed: backend host-only cookies relayed by Next.js become frontend host-only cookies, while the `/admin` cookie remains on the backend.

File rate limits and session locks assume **one backend replica** and a shared local container filesystem among Apache processes. Limits reset on deployment; this is practical application throttling, not bot-proof or an edge DDoS service. Public preview ranges stay fixed and synthetic-only. Additional edge protection and multiple replicas are not configured. `/up` is public. Keep the backend image filesystem ephemeral; persistence belongs in PostgreSQL, not local files.

## First release and later changes

After approval, configure runtime secrets, take/verify a database backup when updating an existing installation, and run these **once inside the deployed backend container** before exposing the consumer site:

```sh
php artisan migrate --force
php artisan db:seed --force
php artisan analytics:seed-demo
```

`db:seed` validates and appends the immutable content-addressed configuration; it never resets history. `analytics:seed-demo` appends an idempotent labeled synthetic daily sample and does not publish configuration. Refresh the sample manually as its seven-day public window ages out. On later releases, migrations run once before traffic; publish only a deliberately reviewed configuration. Never run `migrate:fresh`, `db:wipe`, or test suites against deployment databases.

Administrator creation/rotation is an explicit operator operation, with no automatic bootstrap, default password, public registration or environment password:

```sh
php artisan admin:release operator@example.com --password-file=/run/secrets/brainsprint-admin
```

Prepare/mount the secret through an approved confidential operator channel. It must be an absolute regular non-symlink file, no group/other permissions (0400/0600), at most 1024 bytes; its password must be **20–72 bytes** without control characters (a trailing newline is ignored). Never paste the password into command arguments, shell history, chat or deployment logs. Remove/unmount it immediately after successful use. Only its hash is persisted; provisioning authorizes the panel and revokes that user's stored sessions and remember token. Other admins/anonymous visitors are preserved. The local helper `admin:local` refuses production and still writes only ignored `.local/admin-access.json` (0600).

For rollback, retain the previous release image/commit and stable `APP_KEY`; redeploy compatible application code first. Do not automatically reverse migrations or delete immutable versions. Restore a verified database backup only by deliberate operator decision, acknowledging loss of later writes and possible restoration of previously deleted data. Backup encryption, retention/expiry and a restore rehearsal must be configured/confirmed with the provider before publication; they are not claimed here. Runtime key rotation invalidates encrypted sessions and needs its own plan.

## Frontend

Vercel root directory: `apps/web`, framework Next.js, Node.js 24, install `npm ci`, build `npm run build`, default Vercel runtime. No static export. Set production **server-only** variables:

- `API_ORIGIN=https://<backend-origin>` (no path/trailing slash).
- `WEB_ORIGIN=https://<frontend-origin>` (exact canonical origin for write-origin validation).
- `CONSUMER_PROXY_SECRET=<same private backend secret>`.

Do not add `NEXT_PUBLIC_` to these names. Choose exact origins before enabling traffic; update `WEB_ORIGIN` after approved domain changes. Preview deployments must not silently write to production: give them isolated data/secrets and their own exact origin or leave consumer API unavailable. No production access for previews is configured. The proxy allowlists only existing consumer routes and synthetic summary; admin/Livewire cannot pass it. Private responses and exceptions are no-store; public HTML contains no embedded session state. Verify actual HTTPS, cookie scope, origin/CSRF rejection, assets and admin isolation after deployment, before announcing launch.

## Lifecycle and maintenance

The quiet consumer disclosure offers confirmation/cancel plus permanent deletion of **the current server-owned anonymous visitor's** run (answers/result) and all events, including landing-only exposures and legacy linked events. It invalidates the consumer session/CSRF and returns a new cookie. Admin authentication and other visitors are untouched. Client analytics retries settle before deletion; after returning to the landing, a new anonymous visit can collect new activity. Cleared-cookie/unlinked browsers cannot be recovered or deleted through this action. There is no account or recovery platform.

`privacy:retention` previews by default. Runs/events, including labeled synthetic samples, expire strictly **more than 30 days after the visitor's latest stored activity**: run creation/update, restoring an owned run by bootstrap/show, or any associated event (including legacy events). Fresh activity preserves the visitor's old landing exposure too. Orphan landing-only visitors use their latest event; old unattributable events use their own timestamp. Synthetic samples use the same policy and can be explicitly reseeded after expiry. Configurations/admin users are retained. Consumer/admin sessions expire separately after configured seven-day/two-hour inactivity; boundaries are exclusive, so unexpired sessions survive. Normal request-based session garbage collection is disabled so maintenance has one explicit policy.

```sh
php artisan privacy:retention --limit=500
# Only after reviewing preview and backup policy:
php artisan privacy:retention --execute --limit=500
```

The limit is 1–1000 visitor groups, plus that many expired sessions **per table** and unattributed events; run-associated events are bounded by the funnel's finite semantic identities, rather than split across visits. Each visitor is transactionally rechecked under the same PostgreSQL advisory lock as API requests. Session deletion rechecks expiry. The command preserves configuration versions, administrators and unrelated/fresh data. Invoke once daily through an approved external one-shot operator job against the backend container, repeating bounded batches if needed. **No recurring invocation is configured yet**; the UI says eligibility rather than promising exact automatic removal. No permanently running worker is needed. Backup copies follow the separately approved backup expiry policy.

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
