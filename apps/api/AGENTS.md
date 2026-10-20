# BrainSprint Laravel API

Follow the root AGENTS.md and current architecture; historical task briefs do not define v3 scope. This is the API half of a Next.js/Laravel monorepo; Laravel's generated frontend, accounts, queues, and Boost bootstrap are outside the consumer funnel.

Use the project-local Composer at `../../.tools/composer`; do not install global tools. PostgreSQL is required for development and tests. `php artisan test --compact --filter='MemoryRoundTest|FunnelProgressionTest'` uses the separate `brainsprint_test` database. Never point tests at the development or a production database.

Consumer endpoints use the web middleware stack for normal database sessions and CSRF. Keep the same-origin proxy and OpenAPI contract aligned. Keep configuration publication append-only and transitions transactional. Do not expose visitor/session identifiers or debug details. Keep consumer progression separate from later Filament/analytics work. No accounts, scoring engine, workers or additional services.

V3 uses bounded schema-3 configuration, strict immutable answers and a private result envelope for sample report/commerce state. Serialize only teaser before mock unlock, never the envelope. Preserve legacy validators and versions. No financial inputs, payment providers, delivery or consumer accounts. Executable checks and seeding require a direct request; implementation alone does not authorize them.
