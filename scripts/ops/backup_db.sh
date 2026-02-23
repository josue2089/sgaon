#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
BACKUP_DIR="${ROOT_DIR}/storage/backups"
TS="$(date +"%Y%m%d_%H%M%S")"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Missing .env at ${ENV_FILE}" >&2
  exit 1
fi

mkdir -p "${BACKUP_DIR}"

read_env() {
  local key="$1"
  grep -E "^${key}=" "${ENV_FILE}" | tail -n 1 | cut -d= -f2- | sed 's/^"//;s/"$//'
}

DB_CONNECTION="$(read_env DB_CONNECTION)"

if [[ "${DB_CONNECTION}" == "sqlite" ]]; then
  DB_DATABASE="$(read_env DB_DATABASE)"
  if [[ -z "${DB_DATABASE}" ]]; then
    DB_DATABASE="${ROOT_DIR}/database/database.sqlite"
  fi
  if [[ ! -f "${DB_DATABASE}" ]]; then
    echo "SQLite file not found: ${DB_DATABASE}" >&2
    exit 1
  fi
  OUT_FILE="${BACKUP_DIR}/sqlite_${TS}.sqlite"
  cp "${DB_DATABASE}" "${OUT_FILE}"
  gzip -f "${OUT_FILE}"
  echo "Backup created: ${OUT_FILE}.gz"
  exit 0
fi

if [[ "${DB_CONNECTION}" == "mysql" ]]; then
  DB_HOST="$(read_env DB_HOST)"
  DB_PORT="$(read_env DB_PORT)"
  DB_DATABASE="$(read_env DB_DATABASE)"
  DB_USERNAME="$(read_env DB_USERNAME)"
  DB_PASSWORD="$(read_env DB_PASSWORD)"

  if ! command -v mysqldump >/dev/null 2>&1; then
    echo "mysqldump not found" >&2
    exit 1
  fi

  OUT_FILE="${BACKUP_DIR}/mysql_${DB_DATABASE}_${TS}.sql.gz"
  MYSQL_PWD="${DB_PASSWORD}" mysqldump \
    --host="${DB_HOST}" \
    --port="${DB_PORT:-3306}" \
    --user="${DB_USERNAME}" \
    --single-transaction \
    --routines \
    --triggers \
    --databases "${DB_DATABASE}" | gzip > "${OUT_FILE}"

  echo "Backup created: ${OUT_FILE}"
  exit 0
fi

echo "Unsupported DB_CONNECTION=${DB_CONNECTION}" >&2
exit 1
