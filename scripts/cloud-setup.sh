#!/usr/bin/env bash
# System toolchain for Claude Code cloud sessions (Ubuntu 24.04 image).
# Paste this file's contents into the cloud environment's "Setup script" field.
# Project bootstrap (dependencies, .env, migrations) lives in
# .claude/hooks/session-start.sh so it stays versioned with the repo.
set -euxo pipefail

export DEBIAN_FRONTEND=noninteractive

# --- PostgreSQL 18 apt repository (the image only ships 16) -----------------
install -d /usr/share/postgresql-common/pgdg
curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
  https://www.postgresql.org/media/keys/ACCC4CF8.asc
echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt noble-pgdg main" \
  > /etc/apt/sources.list.d/pgdg.list

# --- PHP 8.5 (ondrej PPA is preinstalled on the image) + PostgreSQL 18 -------
apt-get update -y
apt-get install -y --no-install-recommends \
  php8.5-cli php8.5-pgsql php8.5-mbstring php8.5-xml php8.5-curl \
  php8.5-zip php8.5-bcmath php8.5-intl php8.5-gd \
  postgresql-18
update-alternatives --set php /usr/bin/php8.5

# --- Composer 2.10.2 (pinned in .tool-versions) ------------------------------
composer self-update 2.10.2

# --- Node 24 + pnpm (pinned in .node-version / package.json) -----------------
npm install -g n
# The image puts /opt/node22/bin first on PATH, so install into that prefix.
N_PREFIX=/opt/node22 n 24.18.0
hash -r
corepack enable
corepack prepare pnpm@11.11.0 --activate

# --- PostgreSQL 18 cluster on the default port --------------------------------
if pg_lsclusters -h | grep -q '^16 main'; then
  pg_ctlcluster 16 main stop || true
  sed -i 's/^auto/manual/' /etc/postgresql/16/main/start.conf
fi
pg_lsclusters -h | grep -q '^18 main' || pg_createcluster 18 main --port 5432
pg_ctlcluster 18 main start || true
