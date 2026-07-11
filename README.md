# EduConnect

EduConnect is a premium academic workspace for university students. It brings courses, deadlines, academic materials, tools, prompts, templates, communities, mentors, and research work into one focused dashboard.

The product is designed to help students understand what to do next, choose the right academic workflow, and keep important work organized without relying on scattered files, links, emails, and group chats.

## Product Scope

The MVP is centered on:

- Student registration and academic onboarding
- Personalized course, task, and deadline management
- Manual academic file, notice, and resource intake
- Curated tools, prompts, workflows, and templates
- Academic resources and saved items
- Research topic and reading-progress tracking
- University, department, course, and topic communities
- Mentor discovery and help requests
- A public product experience and demonstration dashboard
- A separate operational console for administration and moderation

EduConnect intentionally excludes paid marketplaces, mentor payments, full email or drive synchronization, unlimited AI chat, and native mobile applications from the MVP.

## Architecture

EduConnect uses a monorepo with independently deployable application surfaces:

```text
educonnect/
├── apps/
│   ├── api/       Laravel REST API
│   ├── web/       Public website and student application
│   └── admin/     Private administration console
├── packages/
│   └── ui/        Shared interface components when needed
├── infra/
│   └── docker/    Reserved for phase-owned local infrastructure
└── .github/       Repository automation and contribution templates
```

The backend is a Laravel modular monolith. Business capabilities remain separated by domain while sharing one deployable API and one PostgreSQL database. Slow or unreliable work is designed for queues, private files use object storage, and authorization is always enforced by the backend.

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13.17.0 |
| Backend runtime | PHP 8.5.8 |
| Database | PostgreSQL 18.4 |
| Cache and queues | Redis or Valkey |
| Object storage | Cloudflare R2 or another S3-compatible service |
| Web application targets | Next.js 16.2.x and React 19.2.x |
| Frontend language target | TypeScript 6.0.x |
| Styling target | Tailwind CSS 4.3.x and shadcn/ui |
| Node runtime | Node.js 24.18.0 LTS |
| Package manager | pnpm 11.11.0 |

## Current Status

EduConnect is under active MVP development. A Laravel 13 API and partial first-party authentication foundation exist, but the API, data, and authentication phases still require reconciliation against the final contracts. The student web and administration applications are not scaffolded yet.

## Requirements

Install the following supported toolchain before developing locally:

- PHP 8.5.8
- Composer 2.10.2
- PostgreSQL 18.4
- Node.js 24.18.0 LTS
- pnpm 11.11.0 through Corepack
- Redis or Valkey when queue and cache-backed features are enabled

## Fresh-clone setup

Activate the package manager pinned in `package.json`, then install the lock-backed JavaScript and PHP dependencies:

```bash
corepack enable
pnpm run install:all
```

Create the isolated local PostgreSQL databases once:

```bash
createdb educonnect
createdb educonnect_test
```

Create `apps/api/.env` from `apps/api/.env.example`, set the local PostgreSQL connection without committing credentials, then initialize the API:

```bash
cd apps/api
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Do not run destructive migration commands against an unknown or shared database.

## Backend development

Start the API from `apps/api`:

```bash
cd apps/api
php artisan serve
```

The combined API/queue/log/Vite development command remains available through `composer run dev` after dependencies and local services are configured.

## Quality checks

Run the complete currently available workspace baseline from the repository root:

```bash
pnpm run check
pnpm run audit
```

The baseline verifies exact toolchain pins, PHP formatting, all registered workspace lint/type-check/test scripts, PostgreSQL-backed Laravel tests, and the production asset build. `pnpm run lint` and `pnpm run typecheck` are intentional no-op fan-outs today because no JavaScript/TypeScript workspace defines those scripts; they begin running automatically when future applications introduce them.

The current Laravel asset build bundles Instrument Sans from Bunny Fonts and therefore requires outbound access to `fonts.bunny.net`.

Useful targeted commands:

```bash
pnpm run versions:check
pnpm run format:check
pnpm run typecheck
pnpm run test
pnpm run build
composer --working-dir=apps/api validate --strict
composer --working-dir=apps/api check-platform-reqs
```

## Engineering Principles

- Build small, complete, testable product slices.
- Keep controllers and interface components thin.
- Validate every write and authorize every protected operation.
- Use stable API resources instead of exposing raw persistence models.
- Paginate list endpoints and index common database queries.
- Queue slow work such as parsing, notifications, ingestion, and AI operations.
- Store private academic files outside the application server.
- Keep secrets out of source code and client bundles.
- Prefer clear names and straightforward code over premature abstractions.
- Add comments only for important business rules or non-obvious decisions.

## Security Model

EduConnect follows deny-by-default authorization and least-privilege access. Student-owned records are isolated by backend policies, administration operations use a separate security boundary, private files require controlled access, and sensitive actions are designed for auditability.

All runtime secrets and provider credentials must be supplied through environment configuration. They must never be embedded in source code or exposed to browser bundles.

## Development Workflow

1. Select one bounded feature or infrastructure slice.
2. Confirm its API, data, authorization, and failure behavior.
3. Implement only that slice.
4. Add meaningful tests for business rules and access control.
5. Run tests, formatting, and relevant build checks.
6. Review changed files for secrets, debug output, and unrelated work.

The project favors production-minded simplicity: a clean modular monolith, explicit boundaries, measurable quality gates, and no premature distributed architecture.
