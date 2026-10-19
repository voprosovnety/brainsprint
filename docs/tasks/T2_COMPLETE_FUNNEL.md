# T2 — complete the one configurable BrainSprint funnel

Status: ready for an independent executor.

## Goal and inputs

Expand the accepted T1 slice into the full approved experience: landing → onboarding → Visual Memory / Pattern Recognition / Logical Reasoning → persisted illustrative result → polished paywall boundary. Target roughly 3–4 minutes without padding the task count.

Read root and application AGENTS files, `docs/ARCHITECTURE.md`, `contracts/README.md`, the relevant OpenAPI definitions, and README startup instructions. T1 is committed at `ebacae1`; the repository is private. Do not read prior interview chats or dependency lockfiles for general context.

T1 is already running locally at web http://127.0.0.1:3000 and API http://127.0.0.1:8008; PostgreSQL is on loopback port 55432. Inspect the actual runtime state before starting or stopping anything. Port 8000 belongs to an unrelated process: do not touch it. Project-local Composer exists at `.tools/composer`.

## Ownership

You are the sole implementation writer in the existing project checkout. Own frontend, Laravel progression and configuration, necessary compatible migrations, API contracts, relevant tests/CI, README, application instructions, and this task brief. The coordinator alone owns `docs/IMPLEMENTATION_PLAN.md`; do not edit it. Coordinate any other shared-file editing before proceeding.

No public deployment, paid service, global tooling changes, or neighboring project changes. Implement and commit autonomously within the approved scope. Independent review for complex progression changes is authorized. Do not end with a plan instead of implementation.

## Required work

1. Extend one versioned BrainSprint configuration with stable screen IDs, text, enabled screens, parameters, bounded presentation variants, and explicit transitions. Support a default next step and one simple answer-based conditional branch. Validate targets and reject broken/cyclic main progression when publishing. Do not build an expression language, drag-and-drop builder, or multiple product configurations.
2. Include onboarding and a short Memory tutorial. A correct tutorial answer continues; an incorrect answer shows one additional guided explanation. Both paths rejoin before the main challenges. The branch is decided by Laravel from the saved response, not an arbitrary client assertion.
3. Deliver three genuinely interactive mechanics: reconstruct a hidden grid pattern; choose the next visual pattern element; arrange reasoning cards with both tap/keyboard and drag support where appropriate. Start with two short tasks per category, tuned for clear pacing. Simple local correctness feedback is fine; never build an answer-based aggregate scoring engine.
4. Keep Laravel responsible for allowed transitions, input validation per screen type, ownership, transactional answer/event persistence, duplicate submission handling, quiz completion, and result generation. Refactor the T1 controller and monolithic page only as needed into small, understandable handlers/components; no speculative architecture or state-machine dependency.
5. Pin each run to its published version. Preserve existing T1 runs, answers, and result values. Publish a new version rather than editing stored configurations or resetting the database. Provide the minimal compatibility needed to resume old Memory-only runs; they are historical versions of this funnel, not a second public funnel. A basic new-attempt feature is not required.
6. Preserve confirmation and recovery behavior: Back/Forward, refresh, current-step restoration, no skipping forward, safe repeat submits, useful saving/error/retry states, and a clear restart of an interrupted unfinished reveal. Browser navigation to a completed step must not resubmit it or regenerate results. Do not add offline synchronization or recovery accounts.
7. Generate the three clearly illustrative result indicators only once after the quiz, store them, and retain them when advancing through result/boundary. Display the disclaimer prominently: `Illustrative demo values — not calculated from your answers.` No diagnosis, IQ, population comparisons, or fabricated personal conclusions.
8. Design the final boundary as a cohesive premium consumer screen. It should naturally follow the result, with intentional typography/composition and a real `Finish demo` action that persists terminal completion. A clear, restrained explanation that the demo ends here is appropriate. No technical-looking placeholder, price table, subscription offer, fake checkout, fake locked content, or promised post-purchase product.
9. Reuse the T1 visual identity where effective; improve actual composition where needed. Use the frontend-design skill and installed framework documentation. Inspect each significant new screen in a real browser at mobile and desktop sizes while implementing it. Check focus, keyboard/touch interactions, reduced motion, transitions, spacing, loading/error states, and absence of horizontal overflow. Avoid saving unnecessary evidence artifacts.
10. Keep OpenAPI, frontend types, and Laravel validation aligned. Preserve T1 session, CSRF, private-cache, and session-rate-limiting guarantees. Adapt the small CI check set to cover relevant progression tests; do not leave CI only running obsolete T1 cases.
11. Update README to describe actual behavior and commands, and this task brief with delivery notes. Keep root AGENTS short and stable; current status belongs only in the coordinator's plan. Commit the finished result.

## Boundaries with later tasks

T3 owns landing A/B assignment/exposure, UTM attribution, the client event endpoint, and the shared aggregate analytics service. T4 owns Filament and the compact public synthetic analytics view. Do not implement them here. Preserve/extend only server-side progression milestones needed by T2; do not invent duplicate milestone names. Distinguish quiz completion, boundary exposure (later client event), and the user's terminal demo completion.

No payments, subscriptions, Reaction Speed, scoring engine, recovery codes, consumer accounts, report/training product, Redis, workers, or PostHog. Do not add dependencies solely to demonstrate them.

## Definition of done and relevant verification

- The full new funnel works end to end through the real Laravel/PostgreSQL backend.
- Both tutorial paths rejoin correctly; all three mechanics work on touch and keyboard.
- Reordering/configuring supported screens and parameters affects new runs without rewriting their basic components; malformed transition targets are rejected.
- A preexisting T1 run can still be restored, and previously generated result values never change. Development data has not been erased.
- Wrong-owner access, attempts to skip steps, invalid answers, conflicting retries, and duplicate completions are checked with focused PostgreSQL tests. Verify repeated completion does not regenerate results or duplicate milestones.
- Refresh and Back/Forward behave predictably through onboarding/challenges/result/boundary. Saved state survives an API outage/retry.
- Each significant screen has actually been inspected in mobile and desktop browsers during development, and identified issues are fixed. Final screen is product-quality and contains no misleading payment behavior.
- Necessary backend tests, TypeScript/build checks, browser scenarios, and independent review of complex transition changes are complete and truthfully reported. Do not repeat unrelated successful checks without a reason.
- Documentation and a clear commit accurately reflect what exists; blocked criteria are reported, not marked complete.

## Completion report

Return briefly in Russian: commit SHA, working flow, real checks and browser sizes, any limitations, changed areas, running local URLs, and recommended T3 handoff. The Product Owner authorized coordinated task reporting; send a concise completion message to coordinator chat `01a14708-527c-7253-9ebf-453f1e5cdf3f` when available, then leave the same result as your final response. Do not edit the coordinating plan or publish/deploy.
