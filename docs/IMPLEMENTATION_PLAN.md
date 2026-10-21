# BrainSprint implementation plan

Target scope: [ARCHITECTURE.md](ARCHITECTURE.md). Project/task statuses live exclusively here. The redesign is implemented and published under the subsequent explicit hosting request. The latest hosting outcome and remaining acceptance limits are recorded below.

## UI follow-up — 2026-10-21

Request: center the final “One quick question” prompts and match the reference’s immediate question/artwork presentation.

Implemented locally: explicit fixed/inset/auto-margin centering for preparation and checkout dialogs; bounded/decode-aware static image warming, upcoming-screen priority, atomic artwork presentation and image retry; immediate deterministic question previews during the previous save, with the next input held until confirmation; exact rollback/retry on save errors; confirmed-only report/offer transitions; deferred actual-view telemetry until server eligibility. No API/schema changes or new dependencies.

Diagnosis is source-based because the user’s verification budget prohibits executable reproduction. The supplied preview URL was attempted with the web reader but was inaccessible; no new reference/runtime comparison is claimed. Status: final static diff reviewed; publishing through the already-authorized manual release path. No local/browser tests or measurements ran.

## Hosting publication — 2026-10-21

The user now explicitly authorizes publication of the new version and prioritizes it over legacy-run review. Manual `Release operations: release` will publish the committed v3 source through the existing backend-first release branches. The separate Funnel checks suite is not requested; the release commit skips that automatic suite. Hosting compilation and the existing revision/health readiness gate are necessary deployment steps. A bounded encrypted backup is uploaded before database publication; no visitor reset or retrospective migration is required.

Status: published successfully. Manual [Release operations 39482906131](https://github.com/voprosovnety/brainsprint/actions/runs/39482906131) completed successfully for `d9d06ce477caca9119ef4e301396ad38339375d3`. The encrypted backup upload and append-only v3 configuration publication succeeded. Both Vercel deployments reported success; the publisher confirmed matching backend/frontend release manifests, backend `/up` and `/admin/login`, and frontend `/` readiness before completing.

Public frontend: [BrainSprint](https://brainsprint-voprosovnety.vercel.app). Backend/admin: [administration](https://brainsprint-api-voprosovnety.vercel.app/admin). New anonymous visits receive schema 3 at `remember-word`. No visitor reset, credential rotation, paid resource, real payment or mailing occurred. Existing pinned visitors still follow their stored version; no additional legacy acceptance work was performed after the user deprioritized it.

Verification in this hosting task: required Vercel production build, deployment statuses and the existing revision/basic-health publication gate only. The separate Funnel checks suite was skipped as declared; no local tests, lint, type-check, browser/UI review or full-path API smoke ran. Complete v3 journey/visual acceptance remains unverified. Backup is the release workflow artifact with the existing three-day retention; no plaintext backup was published.

## Current direction — 2026-10-21

The earlier demo is implemented, but its consumer format is superseded by the supplied [FunnelFox reference](https://app.funnelfox.com/preview/01M4AW6YXV7DZH8J83YGBPP9RR/main/5mAW9BVG). Replace the landing, onboarding, tutorial, six game rounds, immediate snapshot and payment boundary with the full reference-style quiz-to-paywall journey.

The reference contains 58 configured entries: immediate word memory; 39 mixed quiz responses and two demographics; three timed digit reveals and four quiz interstitials; confirmation/trust; staged preparation with modal prompts; email; a shorter preparation stage; long paywall; guide upsell; and two alternative final screens. The architecture maps every position, including 23 distinct visual completion questions. This replaces the 3–4 minute constraint, not just the existing colors.

The commercial presentation, simulated checkout and upsell are required. There will be no real payment, provider integration, card collection, subscription, recurring billing or marketing delivery. Remove repeated demo warnings and the old boundary from the new consumer journey. Preserve infrastructure, historical progress, privacy controls and protected administration.

## Redesign work

| ID | Work | Depends on | Status | Completion criteria |
| --- | --- | --- | --- | --- |
| R0 | Inspect reference and rewrite plan/architecture | User request | Complete — documentation only | Catalog of 58 entries mapped; intro/visual question/paywall inspected; immediate answer navigation observed; unavailable reference payment methods disclosed; no application changes |
| R1 | Define v3 content, configuration and contract | R0; implementation request | Published | Every reference position mapped to target content/assets; all 23 visual questions specified; answer schemas, transitions, teaser/unlock boundary, legacy behavior and event meanings reflected in contracts/code |
| R2 | Build visual system and direct entry | R1 | Published — full acceptance unrun | Gray/periwinkle palette, serif headings, narrow responsive layout, brand/timer/progress header, answer states and bottom CTA; new visitors enter at word memory without legacy onboarding |
| R3 | Implement complete mixed quiz | R1–R2 | Published — full acceptance unrun | All 39 quiz responses, three reveals, four interstitials and two demographics in mapped order; immediate selection/advance, required answers, later word recall, saved restoration and accurate progress; original assets |
| R4 | Implement confirmation and preparation | R3 | Published — full acceptance unrun | Confirmation saves once; trust screen, staged preparation, information modal/two binary prompts, optional email and shorter preparation; report generated once; explicit retry/restoration; no mailing |
| R5 | Implement paywall and simulated unlock | R1–R2, R4 | Published — full acceptance unrun | Full sticky offer/countdown, hero, coherent pricing, checkout, benefits, terms layout, trust/comparison, teaser, sample social content, reassurance and FAQ; no payment provider; server persists unlock once and withholds full report beforehand |
| R6 | Implement guide upsell and final destinations | R5 | Published — full acceptance unrun | Complete $1 mock guide offer; working/persisted accept and decline; corresponding final compositions with accessible sample report/guide; no dead app links or payment receipts |
| R7 | Adapt analytics, compatibility and documentation | R1, R3–R6 | Published — full acceptance unrun | v1/v2 preserved; no forced reset; old experiment retired only for v3; shared analytics/fixtures cover new stages; no purchase/revenue events; README, contracts, analytics definitions and agent guidance match code |
| R8 | Review and prepare publication | R2–R7 | Published; static review complete | Final diff reviewed; requested checks/limitations recorded; full journey and historical-state invariants assessed; publication prepared separately under applicable authorization |

R1–R8 were implemented under local-only authorization. The later hosting request above now authorizes the ordinary release migrations/append-only publication and backend/frontend deployment; it does not authorize credential rotation or visitor resets. Analytics and operational features must not become extra consumer screens. The checked-in v3 source becomes available to new visitors only after a separately authorized publication; existing visitors retain their pinned versions.

## Local implementation record — 2026-10-21

- R1: `brainsprint-v3.json`, `contracts/V3.md`, OpenAPI, typed v3 interface and full 58-position content/asset manifest define explicit immutable transitions and the two final alternatives. V1/v2 sources are untouched.
- R2–R4: root dispatch prefers the owned pinned run, isolates the legacy UI and starts v3 immediately. All 39 quiz responses, two demographics, three timed reveals, four quiz interludes and trust/preparation/email screens are present. The 23 distinct visual problems have 115 original prompt/option SVGs; landmark/community add two more. Classification choices use original inline drawings. Timer, progress, answer locking, exact retry, saved-history review, modal preferences and reduced-motion preparation are implemented.
- R5–R6: complete paywall/checkout, long guide upsell, both final compositions, full readable report and four guide practices are implemented. Three original commercial SVGs complete 120 local files. Every unlock CTA opens the same inert dialog. Mock terms use consistent pricing and exact $9.00 savings; no financial fields, SDK, subscription or delivery exists. Server UTC deadline, private report envelope, immutable unlock/guide answer and milestone transactions use existing tables.
- R7: v3 has no new landing assignment; attribution, session/CSRF/proxy/admin/lifecycle boundaries remain in place. Shared version-specific stage aggregates, public/admin presentation and append-only v3 fixtures are added. Existing regression suites explicitly seed v2 rather than accidentally switching to the new publication source. Future HTTP scripts support v3 answers and respect rate limits. README/contracts/analytics/agent guidance are synchronized.
- Integration addressed nullable historical experiment responses, semantic title/diagram alignment, duplicate privacy/footer surfaces, nested main landmarks, CTA contrast, saved commercial-screen history layout and painted checkout exposure. No user-authored architecture changes were discarded.
- Verification performed: task-relevant source reading and final static integration diff review only. **No tests, build, lint, type-check, browser/UI inspection, API smoke, application/Docker startup, migration, seeding or deployment ran.** No production/database rows, published versions or visitors were changed.
- Remaining limitations: the implementation has no executed acceptance evidence, and the complete mobile/desktop journey, both guide outcomes, visual fidelity, compilation and runtime/security invariants still require separately requested checks. Existing regression fixtures cover legacy behavior; they are not v3 acceptance. Typography uses local system Georgia/Arial-style fallbacks rather than downloaded Newsreader/Inter fonts. The source/seeder is ready, but v3 has not been published into a database; existing services will continue using their current published version until publication is explicitly authorized.

## Decisions for implementation

- Publish an append-only v3 configuration with semantic IDs and the architecture's full positional map. Keep v1/v2 validators/runtime behavior. Replace assumptions requiring onboarding/tutorial/three game categories/result/boundary only for v3.
- Reuse bounded components: intro, choice, scale, reveal, interstitial, confirmation, processing, email, paywall, upsell and final. Image/text choices share validation. No raw reference scripts or generic page builder.
- Create an asset manifest for all 23 visual problems and alternatives, classification/landmark images, hero, report preview and guide cover. Use original local assets and accessible descriptions. Repeated placeholder puzzles and hotlinked reference images are not completion.
- Specify answer locking, processing prompt payloads, email skip, countdown/expiry, simulated unlock, upsell decision and final completion in the contract. Keep a public teaser separate from the full session-owned report. Reuse run JSON unless a concrete constraint warrants a migration.
- Keep one coherent mock offer: $9.99 comparison/$0.99 introductory display, three introductory days, $39.99 every four weeks; guide comparison $27/$1. Do not implement renewal or hidden contradictory offer variants.
- Persist report/strength fixtures independently of correctness, without a scoring engine. Avoid claims of measured IQ, clinical validation, endorsement or live customers. Keep any sample-data explanation in the quiet information surface rather than repeated main-flow banners.
- Keep checkout details inert and use placeholders. The mock action submits only an allowed offer ID; real card/bank/wallet data never enters forms, APIs, events or logs.
- Define v3 event authority and metric denominators alongside the contract. Preserve old meanings; server milestones cover confirmation/unlock/completion, while exposure describes what was actually displayed.

## Acceptance criteria

- A new visitor starts at word memory. Legacy promotional landing, onboarding, grid tutorial, card-arrangement games, pre-paywall three-indicator snapshot and payment boundary are absent from v3.
- The complete inventory/order matches the architecture, including four interstitials at mapped positions and the commercial sequence. Word/digit recall corresponds to the earlier reveals.
- The visual direction matches the reference's centered serif-heading/periwinkle treatment. Mobile CTAs never obscure options. Desktop keeps a deliberate narrow funnel. Keyboard, focus, zoom and reduced motion work throughout.
- Confirmed progression stays server-authoritative. Ordinary next-screen previews cannot submit until their preceding answer is confirmed; double taps/retries cannot skip screens or duplicate milestones. Refresh restores confirmed progress; unfinished timed/presentation states recover coherently. Browser history cannot access future steps or the locked report.
- The paywall is a complete first-class page. Every CTA reaches the same functional mock checkout. Countdown and prices stay consistent. The old boundary and unavailable-method placeholder are not acceptable substitutes.
- Simulated unlock and both upsell outcomes persist once. Final screens expose usable sample content. No real financial, email or consumer-account operation occurs.
- Historical configuration, answers, results, attribution and completion semantics are preserved. New state respects ownership/deletion/retention. Public analytics stay aggregate-only and administration stays protected.
- Documentation changes alongside implemented behavior. Older completed MVP tasks do not imply that their replacements are complete.

## Verification budget

For this implementation, inspect the final integration diff only. No tests, build, lint, type-check, application startup, local UI, API smoke, database publication or deployment checks are requested or run. Inspecting the external reference is requested product research, not application acceptance testing.

During implementation, executable checks require the user's explicit request by default. When requested, use the narrow relevant set: v3 publication/answers/transitions; ownership and CSRF; retry/unlock/milestone deduplication; historical compatibility; mobile/desktop full-path review with both upsell choices. One narrow check is permitted for a concrete risk of irreversible loss, secret exposure or authorization failure after explaining it. Do not create blanket evidence directories or repeat successful checks without relevant changes.

## Existing implementation and operational baseline

This is the previous implementation record, not acceptance of R1–R8. Runtime/provider state was not rechecked during this planning task.

| Previous work | Accepted revision | Recorded outcome |
| --- | --- | --- |
| T0: foundation | Initial history | Repository, contracts and task organization |
| T1: vertical slice | `ebacae1`, `9b9aa61` | Memory-only slice and PHPUnit discovery fix |
| T2: earlier complete funnel | `cf4e385` | Six-challenge flow, persistence and historical compatibility |
| T3: analytics/experiment | `8236371` | Assignment, UTM, actual exposures and metric definitions |
| T4: admin/sample analytics | `785bcbb` | Protected read-only Filament and shared synthetic aggregates |
| T5: release/lifecycle | `7361261` | Accepted local release candidate and hosted checks |
| Publication/automation | `73723ca` | Accepted public release and backend-first exact-revision publication |

- Source: [voprosovnety/brainsprint](https://github.com/voprosovnety/brainsprint). Visibility was recorded as public on 2026-10-20; planning does not change it.
- Recorded public addresses: [frontend](https://brainsprint-voprosovnety.vercel.app), [sample analytics](https://brainsprint-voprosovnety.vercel.app/demo-analytics), [backend administration](https://brainsprint-api-voprosovnety.vercel.app/admin). Providers: Vercel Hobby and Neon Free PostgreSQL 17, Frankfurt, within the existing strict $0/month boundary.
- The release uses pinned `vercel-php@0.9.0`, PHP 8.5, ordinary per-request Laravel, generated Filament assets and shared PostgreSQL cache/locks. The Apache/PHP package remains available. Preserve the working infrastructure.
- Local documented addresses: web `http://127.0.0.1:3000`, API/admin `http://127.0.0.1:8008/admin`, PostgreSQL loopback `55432`. Consult README before startup; this record does not establish service state.
- Prior acceptance covered the old flow, persistence, ownership, CSRF, retries, stable assignment, deletion, admin, synthetic aggregates and layouts at 390×844 and 1280×720. These checks do not validate the redesign. Physical touch and other browser engines remain unverified.
- Accepted-release hosted checks: Funnel `39472251856`, Release operations `39472376098`; maintenance `39471882792`. Both release manifests confirmed `73723ca` and backend health/database readiness preceded frontend publication.
- Production tracks `codex/release-api` and `codex/release-web`; other branches are disabled for deployment. Preserve those controls. A documentation edit is not authority to publish application changes.
- Daily maintenance was recorded at 03:23 UTC / 10:23 Vietnam: encrypted backup before bounded retention, expired cache/session/visitor cleanup and synthetic-sample refresh. Artifact retention: three days; dump cap: 20 MiB; artifact guard: 100 MiB. Schedules may disable after 60 days without activity; no paid fallback or fake keep-alive is authorized.
- An encrypted backup was restored into an isolated local database and tampering rejected; temporary plaintext/database were removed. Historical artifact expiry does not establish current recovery availability. Preserve the private recovery key independently.
- Local/admin release access and backup recovery files remain ignored under `.local/`, mode `0600`. Never copy credentials, cookies or keys into tracked documents or chat.
- Setup stays in README; operational procedures stay in [DEPLOYMENT.md](DEPLOYMENT.md). Preserve ordinary visitor history and synthetic samples.

## Execution protocol

Work in this chat/checkout by default. Do not create executor chats or spawn agents for routine implementation, documentation lookup or repeated review. Delegate only when requested or required by applicable instructions. The change owner updates this file with completed scope, actual checks and limitations. The previous mandatory executor-chat process and historical task briefs do not define the new product scope.
