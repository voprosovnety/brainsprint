# BrainSprint architecture

BrainSprint is an English, mobile-first cognitive challenge demo. The current funnel starts with word memory, continues through a mixed quiz and profile questions, prepares an illustrative report, and finishes with a simulated report unlock and guide offer.

## Application boundaries

| Component | Responsibility |
| --- | --- |
| `apps/web/` | Next.js presentation, answer controls, animation, local drafts, navigation and the same-origin API proxy |
| `apps/api/` | Laravel configuration publication, anonymous ownership, validation, progression, report generation, offer state and analytics |
| PostgreSQL | Canonical run state, immutable configuration versions, events, sessions, shared locks and rate budgets |
| Filament | Separately authenticated, read-only administration using shared aggregate definitions |

The consumer has no account system. Anonymous database sessions establish ownership; a public run ID alone never authorizes access. Administration uses its own guard, session table and host-only cookie scoped to `/admin`.

## Funnel configuration and persistence

`apps/api/resources/funnel/brainsprint-v3.json` defines the current journey. Its 58 entries include 39 quiz responses, two demographic choices, three timed number reveals, four quiz interludes, report preparation, an optional email step, report/guide offers and two alternative final destinations. Artwork is original local SVG content.

The seeder validates bounded screen types, semantic IDs, parameters and explicit transitions before publishing a content-addressed immutable version. Configuration cannot execute HTML, JavaScript or arbitrary expressions. Array order does not determine progression. Supported runs stay pinned to their published version. Unsupported saved runs expose a retirement notice and require session-owned deletion before a new journey; they never render old screens.

The domain tables are `funnel_versions`, `funnel_runs` and `events`. Answers, sample reports and offer state use existing JSON payloads. Laravel validates each answer against the owned run's version and selects the next transition. Answer persistence, report creation and milestone events are transactional. Unique constraints, row locking and per-visitor transaction serialization prevent duplicate submissions from duplicating state or milestones. Confirmed answers are immutable; an identical retry returns the current state.

## Presentation and navigation

The quiz uses a narrow centered column, pale gray/periwinkle surfaces, serif headings and one task per screen. Text choices, visual choices, five-point scales, timed reveals and short interludes share a compact brand/timer/progress header. Progress derives from the configured path.

Quiz screens fill the available viewport without page scrolling. Question headings share the same top anchor, artwork adapts to the remaining space, and the primary action stays at the bottom above the device safe area. On constrained or zoomed viewports, the question area scrolls independently and visual puzzles retain readable minimum dimensions; this region supports keyboard focus and scrolling. Short landscape viewports place the question and choices side by side. Demo information, privacy controls and saved-progress details are available from the header information dialog.

Ordinary questions can display their configured successor while the preceding answer saves. The next answer remains locked until the server confirms that write. Report generation and offer transitions wait for confirmed server state. A failed save returns to the submitted step with its exact answer retained for retry. Refresh restores confirmed progress; browser history may review completed steps without skipping ahead.

Choice state belongs to a specific question. Selecting an option submits it immediately; keyboard selection enables Continue. Answer highlighting reflects selection only, so a stationary pointer or sticky touch hover cannot make the next question appear preselected. Keyboard focus has a separate visible outline. Continue stays visibly inactive until an answer is selected and the previous save is confirmed. The submission handler also validates the choice against the current question before previewing its successor.

Upcoming artwork is preloaded and decoded before a question is presented. Native dialogs are centered and scroll within the viewport. Keyboard controls, visible focus, safe-area spacing and reduced-motion presentation support the same journey. Timed reveals and elapsed time are presentation features, not measurements of ability.

Browser and sharing identity use original local artwork in the same gray/periwinkle palette. Next.js metadata file conventions connect the SVG icon, multi-size favicon, Apple touch icon, web manifest and 1200 × 630 Open Graph card; Twitter uses the same card. Device icons and the editable card source live in `apps/web/public/brand/`. The manifest retains browser presentation. `WEB_ORIGIN` sets the absolute metadata origin, falling back to the public BrainSprint demo URL. Sharing copy identifies the experience as a demo with illustrative results for entertainment.

## Reports and simulated commerce

Laravel generates the sample report once, independently of answer correctness. Before simulated unlock, the API returns only a teaser; afterward, the owning session can read the sample report and exercises. The full report is withheld on the server.

The report offer displays a $0.99 introduction, $9.99 comparison price, three introductory days and $39.99 every four weeks as mock terms. The guide displays $1 against a $27 comparison. Checkout accepts only a configured mock offer ID and acknowledgement. Fields are inert placeholders; no financial data, payment provider, real subscription, email delivery or marketing enrollment exists.

A server-owned five-minute deadline starts on first paywall entry and survives refresh. Expiry changes the displayed offer state while leaving the demonstration accessible. Guide acceptance or decline persists once and selects the corresponding final composition. Optional email is stored only in private answers and excluded from events and aggregates.

## API and security

The frontend proxies only allowlisted consumer routes to a fixed backend origin. Laravel enforces database-session ownership, CSRF, bounded inputs, rate limits and private/no-store responses. Production consumer requests additionally require a private server-to-server proxy secret. The proxy never forwards consumer requests to administration or Livewire routes.

Secure host-only cookies, canonical HTTPS URLs and separate consumer/admin sessions protect production boundaries. Runtime keys and database credentials remain outside source control. Public responses contain no administrative credentials, raw sessions or private report envelope.

Endpoint behavior, errors, answer shapes and unsupported-session handling are defined in the [consumer contract](../contracts/README.md) and [v3 specification](../contracts/V3.md).

## Analytics and data lifecycle

A shared `AggregateService` supplies Filament and public demo analytics. Public callers receive fixed-window synthetic aggregates only. Ordinary analytics exclude synthetic records; local/testing activity is synthetic by server policy. All reported cohorts, stages and conversions include only runs pinned to schema 3.

Server milestones are authoritative and deduplicated. Client views require actual exposure; image preloading does not count as a view. Exposures captured during an optimistic transition wait until confirmed progression makes the screen eligible. First-touch UTM is preserved. The journey has no landing experiment or assignment. No purchase/revenue events or statistical winners are generated.

Visitors can delete their session-owned run, report and events, rotating their anonymous session. Retention selects visitor groups after more than 30 days without stored activity; active visitors, administrators and immutable versions remain. Scheduled maintenance backs up the database before bounded cleanup and sample refresh.

See [analytics definitions](ANALYTICS.md) for calculation rules and [deployment](DEPLOYMENT.md) for Vercel/Neon configuration, encrypted backups, maintenance and rollback.
