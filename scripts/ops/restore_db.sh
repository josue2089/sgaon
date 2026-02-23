#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <backup-file(.gz|.sql|.sqlite)> [--yes]" >&2
  exit 1
fi

BACKUP_FILE="$1"
ASSUME_YES="${2:-}"

if [[ ! -f "${BACKUP_FILE}" ]]; then
  echo "Backup file not found: ${BACKUP_FILE}" >&2
  exit 1
fi

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Missing .env at ${ENV_FILE}" >&2
  exit 1
fi

read_env() {
  local key="$1"
  grep -E "^${key}=" "${ENV_FILE}" | tail -n 1 | cut -d= -f2- | sed 's/^"//;s/"$//'
}

DB_CONNECTION="$(read_env DB_CONNECTION)"

if [[ "${ASSUME_YES}" != "--yes" ]]; then
  echo "This will overwrite current database data."
  read -r -p "Continue? (yes/no): " reply
  if [[ "${reply}" != "yes" ]]; then
    echo "Aborted."
    exit 1
  fi
fi

if [[ "${DB_CONNECTION}" == "sqlite" ]]; then
  DB_DATABASE="$(read_env DB_DATABASE)"
  if [[ -z "${DB_DATABASE}" ]]; then
    DB_DATABASE="${ROOT_DIR}/database/database.sqlite"
  fi
  mkdir -p "$(dirname "${DB_DATABASE}")"

  if [[ "${BACKUP_FILE}" == *.gz ]]; then
    gunzip -c "${BACKUP_FILE}" > "${DB_DATABASE}"
  else
    cp "${BACKUP_FILE}" "${DB_DATABASE}"
  fi

  echo "SQLite restored into ${DB_DATABASE}"
  exit 0
fi

if [[ "${DB_CONNECTION}" == "mysql" ]]; then
  DB_HOST="$(read_env DB_HOST)"
  DB_PORT="$(read_env DB_PORT)"
  DB_DATABASE="$(read_env DB_DATABASE)"
  DB_USERNAME="$(read_env DB_USERNAME)"
  DB_PASSWORD="$(read_env DB_PASSWORD)"

  if ! command -v mysql >/dev/null 2>&1; then
    echo "mysql client not found" >&2
    exit 1
  fi

  if [[ "${BACKUP_FILE}" == *.gz ]]; then
    gunzip -c "${BACKUP_FILE}" | MYSQL_PWD="${DB_PASSWORD}" mysql \
      --host="${DB_HOST}" \
      --port="${DB_PORT:-3306}" \
      --user="${DB_USERNAME}" \
      "${DB_DATABASE}"
  else
    MYSQL_PWD="${DB_PASSWORD}" mysql \
      --host="${DB_HOST}" \
      --port="${DB_PORT:-3306}" \
      --user="${DB_USERNAME}" \
      "${DB_DATABASE}" < "${BACKUP_FILE}"
  fi

  echo "MySQL restore completed for ${DB_DATABASE}"
  exit 0
fi

echo "Unsupported DB_CONNECTION=${DB_CONNECTION}" >&2
exit 1
