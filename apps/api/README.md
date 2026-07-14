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

Operational endpoints are available at:

- `GET /api/v1/health` for process liveness;
- `GET /api/v1/health/readiness` for application and PostgreSQL readiness;
- `GET /api/health` as a temporary compatibility alias.

The probes do not start a first-party Sanctum session, so liveness is independent of the session backend. Every JSON API response includes a server-generated `X-Request-ID` header and matching request ID in its success or error envelope.

## First-party authentication

EduConnect uses Laravel Sanctum browser sessions, not frontend bearer tokens. A browser first requests `GET /sanctum/csrf-cookie`, then sends the decoded `XSRF-TOKEN` cookie as `X-XSRF-TOKEN` on state-changing requests.

The Phase 05 API provides:

- `POST /api/v1/auth/register`, `/login`, and `/logout`;
- `POST /api/v1/auth/forgot-password` and `/reset-password`;
- `POST /api/v1/auth/email/verification-notification` and signed `GET /api/v1/auth/verify-email/{user}/{hash}`;
- `POST /api/v1/auth/logout-all`, which requires the current password;
- canonical `GET /api/v1/me` and the deprecated `/api/v1/auth/me` compatibility alias.

Registration starts an unverified session and queues an encrypted verification notification. Unverified users may access current-user, logout, reset, and resend-verification flows; later onboarding and product APIs require verified email. Password-reset requests return the same response for known and unknown email addresses, and a successful reset revokes every existing session without automatically logging the user in.

## Local Service Defaults

Local development uses:

- PostgreSQL for persistent application data
- Database-backed cache, sessions, and queues
- The private local filesystem disk
- Logged mail delivery

Production environments should switch cache and queues to Redis or Valkey, run a queue worker for authentication notifications, and switch file storage to the configured private S3-compatible disk.

Production uses a same-origin gateway: the public web origin proxies `/api/*` and `/sanctum/*` to Laravel. `APP_URL` and `FRONTEND_URL` must be the same exact HTTPS origin. The session cookie must be `__Host-educonnect-session`, host-only (`SESSION_DOMAIN=null`), Secure, HttpOnly, encrypted, non-partitioned, and SameSite=Lax. CORS and Sanctum accept only that exact origin and host during the student-authentication phase; the isolated admin boundary is added in its owning phase.

Production boot also fails clearly unless PostgreSQL, persistent cache and queue drivers, after-commit queue dispatch, bounded reset/verification expiry, and real SMTP delivery are configured safely. Keep credentials outside source control.

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

The executable OpenAPI contract is stored at `openapi.yaml`; focused feature tests validate real health and authentication requests and responses against it.

## Data Foundation

- PostgreSQL is the only supported relational database.
- Database sessions and application datetimes use UTC; SQL datetime columns use PostgreSQL `timestamptz` semantics.
- Bigint primary and foreign keys remain internal. Externally exposed aggregate identifiers are immutable lowercase ULIDs.
- Email values are stored as trimmed lowercase values and protected by PostgreSQL constraints.
- Default seeding never creates a user account. Reference data is introduced only with its owning feature.

When upgrading a non-empty schema created before the Phase 04 migrations, first verify that every legacy `timestamp without time zone` value represents UTC. Then set `DB_LEGACY_TIMESTAMP_TIMEZONE=UTC`, clear any cached configuration, and run the migrations. Leave the variable blank otherwise. The Phase 04 entry migration stops before its first write when legacy timestamp provenance has not been explicitly verified.

From the repository root, `pnpm run check` runs these backend checks together with toolchain verification and any scripts owned by future web/admin/shared workspace packages.

The API is independently runnable and intentionally contains no Blade application views, frontend asset pipeline, or administration interface code.
