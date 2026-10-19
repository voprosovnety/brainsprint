# BrainSprint

A mobile-first, interactive cognitive challenge funnel built around visual memory, pattern recognition, and logical reasoning.

BrainSprint is an entertainment demo, not an IQ test or cognitive assessment. Illustrative result indicators will be generated once per run and explicitly identified as unrelated to answer correctness.

## Status

The approved architecture, repository structure, and first implementation task are prepared. The application is not implemented or deployed yet. There are no working setup commands or public demo URLs to publish at this stage.

## Planned experience

Landing → onboarding → three interactive challenges → illustrative result → a polished paywall boundary that honestly ends the demo. No payments or subscriptions are included.

The MVP includes configurable progression, anonymous progress restoration, a landing-page A/B test, UTM attribution, event analytics, a protected Filament panel, and a compact public view of synthetic analytics using the same metric logic.

## Architecture and structure

| Path | Responsibility |
| --- | --- |
| `apps/web/` | Next.js App Router, React, TypeScript, Tailwind CSS |
| `apps/api/` | Laravel API, PostgreSQL persistence, Filament, shared analytics |
| `contracts/` | Consumer API and configuration contracts |
| `infra/` | Reproducible local and deployment configuration |
| `docs/` | Architecture, implementation plan, and task briefs |

Laravel owns business state and progression. The frontend renders the experience and accesses Laravel through its own origin. Configuration versions are pinned to runs; demo results remain stable after refresh.

## Development, configuration, and verification

The first task will add runnable local services, `.env.example` files, dependency lockfiles, and exact startup, migration, test, and shutdown commands. Only placeholders belong in example environment files.

Verification must include the actual browser experience on mobile and desktop, plus focused backend checks for the implemented behavior. Supported commands and their actual results will be documented once available.

## Deployment

The intended low-cost deployment is Next.js on Vercel Hobby and Laravel/PostgreSQL on Railway. It is not provisioned. Paid services and public deployment require approval. The source repository starts private.

## Documentation

- [Agent instructions](AGENTS.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Implementation plan](docs/IMPLEMENTATION_PLAN.md)
- [Initial API contract](contracts/README.md)
