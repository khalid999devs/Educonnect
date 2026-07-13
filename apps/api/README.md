# EduConnect API

The EduConnect API is a Laravel 13 modular-monolith backend for the public website, student application, and private administration console.

## Requirements

- PHP 8.5.8
- Composer 2.10.2
- PostgreSQL 18.4
- Redis or Valkey for production cache and queues
- S3-compatible private object storage for production files

## Local Setup

Install the API dependencies directly:

```bash
cd apps/api
composer install
```

Alternatively, the root `pnpm run install:all` command installs all current workspace dependencies. Then create local API configuration from the provided example:

```bash
cp .env.example .env
php artisan key:generate
```

Create a local PostgreSQL database:

```bash
createdb educonnect
```

Set the local database host, port, name, username, and password, then run:

```bash
php artisan config:clear
php artisan migrate
```

PostgreSQL uses port `5432` by default. Set a different database port in local environment configuration if the standard port is unavailable.

Start the API:

```bash
php artisan serve
```

Foundation operational endpoints are available at:

- `GET /api/v1/health` for process liveness;
- `GET /api/v1/health/readiness` for application and PostgreSQL readiness;
- `GET /api/health` as a temporary compatibility alias.

The probes do not start a first-party Sanctum session, so liveness is independent of the session backend. Every JSON API response includes a server-generated `X-Request-ID` header and matching request ID in its success or error envelope.

## Local Service Defaults

Local development uses:

- PostgreSQL for persistent application data
- Database-backed cache, sessions, and queues
- The private local filesystem disk
- Logged mail delivery

Production environments should switch cache and queues to Redis or Valkey and file storage to the configured private S3-compatible disk.

Production boot fails clearly unless `APP_KEY` is valid, `APP_DEBUG=false`, `APP_URL` uses HTTPS, and `DB_CONNECTION=pgsql`. Session cookie/origin topology is configured separately when the authentication phase is reconciled.

Cloudflare R2 can use the standard S3 variables. Set the region to `auto`, provide the bucket and account endpoint, and keep credentials outside source control.

## Quality Checks

Create the isolated PostgreSQL test database once:

```bash
createdb educonnect_test
```

The test suite forces the `pgsql` connection and `educonnect_test` database name while inheriting local PostgreSQL host, port, and credentials.

```bash
php artisan test
composer exec pint -- --test
composer analyse
composer contract:validate
composer validate --strict
composer check-platform-reqs
```

The Phase 03 OpenAPI contract is stored at `openapi.yaml`; focused feature tests validate real liveness/readiness requests and responses against it. Authentication routes remain outside that contract until their dedicated reconciliation phase.

From the repository root, `pnpm run check` runs these backend checks together with toolchain verification and any scripts owned by future web/admin/shared workspace packages.

The API is independently runnable and intentionally contains no Blade application views, frontend asset pipeline, or administration interface code.
