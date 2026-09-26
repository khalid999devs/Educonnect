#!/bin/bash
# Bootstraps EduConnect in Claude Code cloud sessions: starts PostgreSQL,
# installs dependencies, and prepares apps/api/.env and the databases.
# The system toolchain comes from the environment setup script
# (scripts/cloud-setup.sh).
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

cd "$CLAUDE_PROJECT_DIR"
export COMPOSER_ALLOW_SUPERUSER=1

# --- PostgreSQL ---------------------------------------------------------------
pg_ctlcluster 18 main start 2>/dev/null || true
for _ in $(seq 1 30); do pg_isready -q && break; sleep 1; done
su postgres -c "psql -qc \"ALTER USER postgres PASSWORD 'postgres';\""
for db in educonnect educonnect_test; do
  su postgres -c "psql -tAc \"SELECT 1 FROM pg_database WHERE datname='${db}'\"" | grep -q 1 \
    || su postgres -c "createdb ${db}"
done

# --- JavaScript dependencies --------------------------------------------------
pnpm install --frozen-lockfile

# --- PHP dependencies ---------------------------------------------------------
# The session's GitHub proxy blocks zipball downloads for repositories outside
# this session, but allows public git clones. Install from source, and seed the
# dist cache for phpstan/phpstan, which publishes no git source in the lockfile.
seed_dist_from_git() {
  local name="$1" repo="$2" ref="$3"
  local url="https://api.github.com/repos/${name}/zipball/${ref}"
  local dir="${HOME}/.cache/composer/files/${name}"
  local file="${dir}/$(printf %s "$url" | sha1sum | cut -d' ' -f1).zip"
  [ -s "$file" ] && return 0
  local tmp
  tmp="$(mktemp -d)"
  git init -q "$tmp"
  git -C "$tmp" fetch -q --depth 1 "$repo" "$ref"
  mkdir -p "$dir"
  git -C "$tmp" archive --format=zip --prefix=package/ -o "$file" FETCH_HEAD
  rm -rf "$tmp"
}

phpstan_ref="$(php -r '
  $lock = json_decode(file_get_contents("apps/api/composer.lock"), true);
  foreach (array_merge($lock["packages"], $lock["packages-dev"]) as $p) {
    if ($p["name"] === "phpstan/phpstan") { echo $p["dist"]["reference"]; }
  }')"
if [ -n "$phpstan_ref" ]; then
  seed_dist_from_git phpstan/phpstan https://github.com/phpstan/phpstan.git "$phpstan_ref"
fi

composer --working-dir=apps/api install --prefer-source --no-interaction --no-progress

# --- Laravel bootstrap --------------------------------------------------------
cd apps/api
if [ ! -f .env ]; then
  cp .env.example .env
  sed -i 's/^DB_PASSWORD=$/DB_PASSWORD=postgres/' .env
  php artisan key:generate --no-interaction
fi
php artisan migrate --force --no-interaction
