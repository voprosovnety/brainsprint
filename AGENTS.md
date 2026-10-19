# BrainSprint

BrainSprint is a mobile-first cognitive challenge demo. Follow `docs/ARCHITECTURE.md` for product scope and detailed technical decisions.

## Stack and architecture
- Next.js App Router, React, TypeScript, Tailwind CSS in `apps/web/`.
- PHP, Laravel, PostgreSQL, Filament in `apps/api/`.
- Next.js owns presentation and interactions; Laravel owns configuration, progression, persistence, results, experiments, and analytics. Proxy consumer API requests through the frontend origin.
- Pin runs to immutable published configuration versions. Use database-backed anonymous Laravel sessions for consumer progress and separate administrative authentication for Filament.
- Keep the schema and transition rules minimal. Add tables, dependencies, or services only for a justified technical need.

## Development guidelines
- Read task-relevant files and follow existing conventions. Prefer simple, maintainable solutions with clear responsibilities.
- Read `contracts/README.md` before changing the consumer API. Keep frontend types and server validation aligned.
- Respect locked dependency versions; confirm stable package compatibility from official sources before installation. Commit lockfiles; do not use prereleases or unpinned Docker `latest` tags.
- Handle loading, empty, and error states explicitly. Return actionable errors without debug details.
- Keep documentation, code comments, and UI in English.

## Data and security
- Derive ownership and experiment assignment from the server session; public IDs alone never authorize access.
- Validate inputs on the server, protect cookie-authenticated writes against CSRF, rate-limit public endpoints, and enforce administrative access control.
- Persist a step and its completion event transactionally. Duplicate submissions must not duplicate milestones or results.
- Generate demo indicators once on the server and persist them per run. Clearly label them as illustrative and unrelated to cognitive ability or answer correctness.
- Mark synthetic data and exclude it from ordinary analytics. Public analytics expose aggregates only and share metric definitions and calculation logic with Filament.
- Never commit credentials, real `.env` files, session secrets, or raw cookies. Environment examples contain placeholders only; public responses must not expose secrets or administrative credentials.

## Verification
- Run relevant automated checks for code changes, covering persistence, transitions, ownership, event deduplication, and stable experiment assignment where affected.
- Verify significant UI changes in a real browser at mobile and desktop sizes. Check layout, typography, accessibility, motion, and interaction states; fix issues before considering the work complete.
- Use independent review for complex changes. Report what ran and disclose failed, blocked, or incomplete checks.
- Use screenshots and browser traces as needed for visual testing. Do not commit unnecessary temporary artifacts to Git.

## Documentation
- Update existing documentation when behavior or technical decisions change. Describe implemented behavior accurately; do not present planned features as complete.
- Keep setup instructions in `README.md`, architecture in `docs/ARCHITECTURE.md`, and consumer API documentation in `contracts/README.md`.
- Keep current project and task statuses exclusively in `docs/IMPLEMENTATION_PLAN.md`.
