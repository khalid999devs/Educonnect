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
│   └── docker/    Local infrastructure configuration
└── .github/       Repository automation and contribution templates
```

The backend is a Laravel modular monolith. Business capabilities remain separated by domain while sharing one deployable API and one PostgreSQL database. Slow or unreliable work is designed for queues, private files use object storage, and authorization is always enforced by the backend.

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13.x |
| Backend runtime | PHP 8.5 preferred, PHP 8.4 fallback |
| Database | PostgreSQL 18.x |
| Cache and queues | Redis or Valkey |
| Object storage | Cloudflare R2 or another S3-compatible service |
| Web applications | Next.js 16.x and React 19.x |
| Frontend language | TypeScript 5.x |
| Styling | Tailwind CSS 4.x and shadcn/ui |
| Node runtime | Node.js 24 LTS |
| Package manager | pnpm 10.x |

## Current Status

EduConnect is under active MVP development. The Laravel 13 API foundation is initialized and verified. Student web and administration applications will be introduced after the required backend foundations and API contracts are ready.

## Requirements

Install the following supported toolchain before developing locally:

- PHP 8.5
- Composer 2.x
- PostgreSQL 18.x
- Node.js 24 LTS
- pnpm 10.x
- Redis or Valkey when queue and cache-backed features are enabled

## Backend Development

Install backend dependencies:

```bash
cd apps/api
composer install
```

Create local runtime configuration from the provided example, set the required service values, generate an application key, and apply migrations:

```bash
php artisan key:generate
php artisan migrate
```

Start the local API server:

```bash
php artisan serve
```

## Quality Checks

Run backend tests:

```bash
cd apps/api
php artisan test
```

Check backend formatting:

```bash
composer exec pint -- --test
```

Validate backend dependencies:

```bash
composer validate --strict
composer check-platform-reqs
composer audit --locked
```

Verify the JavaScript toolchain from the repository root:

```bash
node --version
pnpm --version
pnpm list --recursive --depth -1
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
