# BrainSprint architecture

This document defines the target experience and technical boundaries. Current implementation and release status live exclusively in [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md). The target replaces the earlier six-challenge, 3–4 minute scope and payment-boundary ending; it does not imply that the redesign is already implemented.

## Product direction and reference

Build one English, mobile-first cognitive quiz funnel closely matching the structure, pacing, interactions and visual hierarchy of the supplied [FunnelFox preview](https://app.funnelfox.com/preview/01M4AW6YXV7DZH8J83YGBPP9RR/main/5mAW9BVG). Keep the BrainSprint identity. Start immediately with a word-memory prompt, continue through a substantial mixed quiz and profile questions, prepare a report, then present a complete commercial-style paywall, simulated unlock, guide upsell and final destination.

The project is exclusively demonstrative. Prices and checkout interactions are presentation data; no real payment, subscription, renewal, email delivery or paid entitlement exists. The consumer journey follows the reference without repeated demo banners, technical explanations, mandatory disclaimer screens or the old payment-boundary ending. Any explanation of sample report data belongs in the existing quiet information/privacy surface. A real training product, consumer account system and psychometric scoring engine are outside scope.

The reference's public preview configuration contains 58 screen entries. Its ordered catalog supplies the map below. Browser inspection additionally covered the intro, a visual question and paywall; selecting a visual answer advanced immediately. The two final entries are alternatives. The payment area reported unavailable methods, so successful payment and wallet operation were not observed. The entire sequence was not manually completed. Hidden alternative offer elements are source context, not evidence of the intended visible offer.

## Reference screen map

Positions are zero-based in the reference catalog. Every question in a range remains a separate configured screen. Preserve the order, counts and interleaving instead of implementing a shorter selection of examples. Write original English content and create matching original diagrams/illustrations; do not hotlink assets or copy the reference's branding, endorsements, testimonials or company details.

| Position | Reference screen ID or range endpoints | Target content and interaction |
| --- | --- | --- |
| 0 | `5mAW9BVG` | Word-memory introduction, compact brand/timer header, bottom CTA; no preceding landing |
| 1–3 | `GB55j3G2` … `ippNpqhN` | Three short verbal, arithmetic or general-reasoning choices |
| 4 | `yzwpARtX` | Five-point learning-preference scale |
| 5 | `9py0dxMr` | Numerical sequence choice |
| 6–7 | `0kcxw3VF` → `yC8FR3bZ` | Five-digit timed reveal, then positional recall |
| 8 | `tYNJn5dA` | Second numerical sequence choice |
| 9–10 | `N3ObhEr2` → `tpIDf89C` | Six-digit reveal and positional recall |
| 11–12 | `05pOZczw` → `zpS0r9AQ` | Seven-digit reveal and positional recall |
| 13 | `CV5aOqZG` | Five-point spatial-confidence scale |
| 14 | `Xh5APWtC` | Visual completion question 1 |
| 15 | `bVV0xRdb` | Brief pace/encouragement interstitial |
| 16–18 | `cixbDaa5` … `NYZ1N4i9` | Visual completion questions 2–4 |
| 19 | `kx1acnY5` | Six-option object classification |
| 20–22 | `JjUm8ipP` … `XH3wAIbK` | Visual completion questions 5–7 |
| 23 | `nMeltby7` | Six-option category classification |
| 24–27 | `01d0Z4GL` … `ir5AfugS` | Visual completion questions 8–11 |
| 28 | `snyvHv5l` | Landmark/image knowledge choice |
| 29 | `aA3fRTzD` | Short encouragement interstitial |
| 30 | `Uc9has5M` | Visual completion question 12 |
| 31 | `Po3OQPww` | Recall the initial word among five choices |
| 32–37 | `Dxxqvlye` … `dHnKxjCt` | Visual completion questions 13–18 |
| 38 | `RQtkS5K3` | Emerging-profile interstitial |
| 39–42 | `Zd9J87Ah` … `kg9lmawi` | Visual completion questions 19–22 |
| 43 | `hLwGMiD3` | Near-completion interstitial with accurate progress |
| 44 | `aRCdcvUu` | Visual completion question 23 |
| 45–46 | `7cXr2xHl` → `tANF0bcQ` | Two five-point attitude/self-comparison scales |
| 47–48 | `6JvoSfUO` → `lWY1hdI9` | Age bracket and education choices |
| 49 | `LGXDtk0Q` | Explicit answer confirmation |
| 50 | `jUVqS68r` | Short community/trust composition |
| 51 | `uedp0OAi` | Staged report preparation, information modal and two binary preference prompts |
| 52 | `DzfcmvVv` | Email-style report delivery form |
| 53 | `gzIiu2cV` | Shorter profile-preparation animation |
| 54 | `1jBca9dq` | Long-form paywall, pricing, preview, repeated CTAs and FAQ |
| 55 | `b8t9KfZI` | Optional guide upsell with working accept and decline actions |
| 56–57 | `UqornVcI` / `nNJpvRvj` | Alternative final destinations reflecting the guide decision |

There are 39 quiz response screens and two demographic response screens, plus three digit-reveal screens and four quiz interstitials. The two processing prompts are additional to those 41 main responses. Keep the full length and remove the old 3–4 minute promise. Display a duration only when it can be estimated from the finished content.

## Presentation and interaction

- Pale gray background near `#F5F6F8`, periwinkle accents near `#7E8AF7`, darker violet near `#4F5BD0`, dark ink text and rounded white answer/image surfaces replace the current blue game-dashboard composition.
- Pair a Newsreader-style serif display face with an Inter-style UI face and tabular/monospaced timer numerals. Use the existing font/asset pipeline; no new UI framework is needed.
- Keep the quiz in a centered column approximately 420–560 px wide on larger displays. Desktop retains the same focused sequence without a hero, sidebar or dashboard. Paywall sections may use a deliberately wider composition.
- Show a small brand mark on the left, elapsed time on the right and a thin progress line below. Use generous spacing and one task per screen. Progress derives from the configured path and completed questions, including on interstitials.
- Use text answer rows, two-column visual choices, five-point scales and demographic rows. Selection can show the configured next ordinary question immediately while its answer saves; that next question cannot submit until the previous write is confirmed. Server-dependent report/offer transitions wait for confirmation. Preload/decode upcoming local artwork, and present each question atomically with its images rather than inserting them after navigation. A bottom CTA provides an accessible equivalent after a choice; it cannot skip a required answer. Selected, focused, saving and error states are explicit. Do not insert correctness/explanation pages after ordinary answers.
- Display each digit sequence for about five seconds before its recall question. Recall the opening word much later. Local timing stays responsive while Laravel persists confirmed transitions. Elapsed time neither imposes a deadline nor implies measured ability.
- Keep the main CTA near the viewport bottom on short screens, with safe-area and content clearance. It must not cover answers, errors or the mobile keyboard. Support browser zoom, keyboard operation, visible focus and reduced motion.
- Center native dialogs explicitly within the viewport, with bounded height and scrolling on small displays. Use short interstitials, staged animations and restrained modal prompts for the reference's rhythm. Refresh may restart unfinished presentation, but not completed steps. Reduced motion reaches the same usable state without decorative waits.

## Paywall and simulated checkout

The paywall is a complete designed page, not a technical placeholder. Preserve this hierarchy:

1. Brand, a narrow social-proof-style strip, and a sticky bar with price, five-minute countdown and unlock CTA.
2. Completion headline, report-focused subheading and an original hero illustration.
3. Offer card with $9.99 comparison/$0.99 introductory pricing, an applied-promotion treatment and a checkout-style action area. Keep discount arithmetic correct and the offer consistent throughout.
4. Four benefit rows covering the report, strengths, thinking patterns and practical suggestions, followed by compact commercial terms. The reference depicts three introductory days followed by $39.99 every four weeks; these are mock terms only.
5. Methodology/trust composition, current/goal comparison, repeated CTA, partially obscured report preview, sample community/review cards, reassurance, FAQ and local footer links.

Use original sample content for those design roles. Do not claim verified academic endorsement, clinical validity, live customer activity or guaranteed improvement. Keep social fixtures separate from real analytics; no repeated qualification banners are needed in the main journey. Do not reproduce the reference's inconsistent hidden alternative prices.

Every unlock CTA opens or scrolls to the same functioning simulated checkout. Wallet-style/card-style presentation may use fixed placeholder details and inert fields, but must not request or accept real card numbers, expiry dates, CVC, bank details or wallet authorization. Load no payment SDK, provider script, hosted payment field, payment link, webhook or provider secret, including test-mode integrations. Submit only a configured mock offer ID and acknowledgement through the existing session/CSRF boundary. Persist simulated unlock once; errors allow an identical retry. Do not issue a payment receipt or emit purchase/revenue events.

Create a server-owned countdown deadline at first paywall entry; refresh cannot restart it. Expiry changes the displayed offer state deterministically without charging, removing answers or blocking demonstration access.

## Upsell, final destination and email

Follow the reference's long guide offer: original cover, contents, sample feedback, $27 comparison/$1 offer, reassurance, FAQ and clear accept/decline CTAs. Both decisions persist once and lead to corresponding final compositions. The final destination contains a usable unlocked sample report and the saved guide state; include a guide preview when offered. Do not promise an app download, sent email, real subscription or unavailable content. Refresh restores the same outcome.

The email screen retains its visual role but performs no mailing, account creation or marketing subscription. Provide a sample address and optional skip path. If an entered address is retained, store it only in session-owned answers under the existing deletion/retention policy; exclude it from events, URLs, aggregates and logs. Do not copy the reference's marketing-consent statement.

## Components and configuration

- `apps/web`: existing Next.js App Router, React, TypeScript and Tailwind; presentation, answer controls, animation, local drafts, navigation and same-origin API proxy.
- `apps/api`: existing Laravel; publication, anonymous ownership, validation, progression, report fixtures, simulated offer state, transactional milestones and analytics.
- PostgreSQL: canonical run state and database-backed sessions/cache. Filament: existing separately authenticated read-only administration and shared aggregate metrics.

Reuse locked dependencies, the three domain tables (`funnel_versions`, `funnel_runs`, `events`), session isolation and release infrastructure. Do not add a funnel-builder product, payment/subscription/customer tables, Redis, queues, workers or services without a concrete technical need.

Publish an immutable schema-versioned configuration, provisionally `brainsprint-v3.json`, with semantic screen IDs and explicit transitions. Reference IDs are documentation mappings, not runtime dependencies. Replace raw reference HTML/scripts with maintainable React components and inert structured content. Configuration must not execute HTML, JavaScript or arbitrary expressions.

Support bounded screen types: word intro, single choice with text/image presentation, scale, timed reveal, interstitial, confirmation, processing with fixed prompts, email, paywall, upsell and final. Recall reuses single choice. Processing prompts are fixed fields on the parent screen, not a nested workflow engine. Required branches are email skip and upsell accept/decline, with explicit terminal destinations. V3 has no correctness-based tutorial branch.

Laravel validates against the pinned version, requires preceding answers and permits only configured transitions. Generate a sample report once at answer confirmation, independently of correctness. Do not introduce IQ normalization, population-percentile calculation or clinical interpretation. Before simulated unlock return only a public teaser payload; withholding the full report must be enforced on the server, not by CSS.

Extend bounded run state for confirmation, processing, countdown deadline, simulated unlock and upsell outcome. Prefer existing JSON payloads; add a small migration only for a justified indexed/constrained field. These are demonstration states, not invoices or transferable access rights. Saves, report generation and milestone events remain atomic and idempotent.

## Compatibility, API and analytics

Existing v1/v2 runs retain their immutable configuration, answers, results and completion semantics. Do not force them into the new sequence. New visitors use v3 after deliberate publication. Existing browsers may therefore retain the old experience; use a separate development visitor or the existing session-owned deletion action for review, never a production-wide reset.

Read [contracts/README.md](../contracts/README.md) before implementation. Update OpenAPI, frontend types, configuration/answer validators and progression together when behavior is implemented. Preserve proxy allowlisting, database sessions, server-owned identity, CSRF, ownership, rate budgets, bounded input, private/no-store responses and the protected `/admin` boundary. README/contracts continue to describe implemented behavior until their corresponding code changes land.

Retain first-touch UTM, actual visible-screen exposures, transactional milestones, synthetic-data separation and `AggregateService`. Retire the old landing-copy experiment for new v3 runs instead of adding a landing to accommodate it; preserve historical assignments/analytics. A new experiment is outside scope.

Version event semantics explicitly: confirmed quiz completion, processing, paywall exposure, checkout exposure, simulated unlock, upsell decision and final completion are separate stages. Reuse events only when meanings match. Unlock/final milestones are server-authoritative and deduplicated; views require actual exposure. Report view means the accessible report, not its locked teaser. Do not generate revenue or real purchase events. Extend shared metric definitions and synthetic fixtures without relabeling history or mixing version-specific denominators.

## Data lifecycle, delivery and verification

Preserve anonymous deletion/retention, per-visitor transaction serialization, separate consumer/admin cookies, production proxy secret, secure host-only cookies, fixed backend origin and protected administration. Keep secrets out of configuration and responses. Include every new profile/report/offer field in the existing deletion/retention mechanisms.

Reuse Vercel/Neon and the strict $0/month boundary. Paid resources, card linking, trial-credit dependencies and keep-alive traffic are outside scope. Operational procedures stay in [DEPLOYMENT.md](DEPLOYMENT.md). A planning edit does not migrate data, publish configuration or deploy code.

Review the final implementation diff. Follow the user's verification budget: executable checks require an explicit request, except one narrow check for a concrete risk of irreversible harm. When UI review is requested, compare all screen families, processing/modal states, the complete paywall, checkout, both upsell choices and final destinations on mobile/desktop. Disclose unrun checks; do not claim acceptance from the old release's results.
