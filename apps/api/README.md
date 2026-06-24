# EduConnect API

The EduConnect API is a Laravel 13 modular-monolith backend for the public website, student application, and private administration console.

## Requirements

- PHP 8.5
- Composer 2.x
- PostgreSQL 18.x
- Redis or Valkey for production cache and queues
- S3-compatible private object storage for production files

## Local Setup

Install dependencies and create local environment configuration from the provided example:

```bash
composer install
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

## Local Service Defaults

Local development uses:

- PostgreSQL for persistent application data
- Database-backed cache, sessions, and queues
- The private local filesystem disk
- Logged mail delivery

Production environments should switch cache and queues to Redis or Valkey and file storage to the configured private S3-compatible disk.

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
composer validate --strict
composer check-platform-reqs
```

The API must remain independently runnable and must not contain frontend or administration interface code.
