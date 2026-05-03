#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/deploy/common.sh
source "$SCRIPT_DIR/common.sh"

DB_BACKUP_FILE="${DB_BACKUP_FILE:-}"
UPLOADS_BACKUP_FILE="${UPLOADS_BACKUP_FILE:-}"
RESTORE_DB_NAME="${RESTORE_DB_NAME:-}"
KEEP_RESTORE_DB="${KEEP_RESTORE_DB:-no}"

usage() {
  cat <<'USAGE'
Usage:
  CONFIRM_RESTORE_REHEARSAL=yes tools/deploy/restore-rehearsal.sh \
    --db-backup /path/to/database.dump \
    --uploads-backup /path/to/uploads.tar.gz

Options:
  --db-backup PATH       PostgreSQL custom-format dump to restore.
  --uploads-backup PATH  Optional uploads tar.gz archive to extract and verify.
  --restore-db NAME      Optional temporary database name.
  --keep-db              Keep the restored database for manual inspection.
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --db-backup)
      DB_BACKUP_FILE="${2:-}"
      shift 2
      ;;
    --uploads-backup)
      UPLOADS_BACKUP_FILE="${2:-}"
      shift 2
      ;;
    --restore-db)
      RESTORE_DB_NAME="${2:-}"
      shift 2
      ;;
    --keep-db)
      KEEP_RESTORE_DB="yes"
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      fail "Unknown argument: $1"
      ;;
  esac
done

[[ "${CONFIRM_RESTORE_REHEARSAL:-no}" == "yes" ]] || fail "Set CONFIRM_RESTORE_REHEARSAL=yes to run restore rehearsal."
[[ -n "$DB_BACKUP_FILE" && -f "$DB_BACKUP_FILE" ]] || fail "Database backup file is required."

require_command "$PHP_BIN"
require_command createdb
require_command dropdb
require_command pg_restore
require_command psql

prepare_shared_layout
load_shared_env
export_pg_env_from_database_url "$DATABASE_URL"

SOURCE_DB="$PGDATABASE"
RESTORE_DB_NAME="${RESTORE_DB_NAME:-${SOURCE_DB}_restore_rehearsal_$(date '+%Y%m%d_%H%M%S')}"

[[ "$RESTORE_DB_NAME" != "$SOURCE_DB" ]] || fail "Refusing to restore into source database: $SOURCE_DB"

cleanup_restore_db() {
  if [[ "$KEEP_RESTORE_DB" != "yes" ]]; then
    dropdb --if-exists "$RESTORE_DB_NAME" >/dev/null 2>&1 || true
  fi
}
trap cleanup_restore_db EXIT

log "Verify PostgreSQL backup metadata: $DB_BACKUP_FILE"
pg_restore -l "$DB_BACKUP_FILE" >/dev/null

log "Create temporary restore database: $RESTORE_DB_NAME"
dropdb --if-exists "$RESTORE_DB_NAME" >/dev/null 2>&1 || true
createdb "$RESTORE_DB_NAME"

log "Restore database backup into $RESTORE_DB_NAME"
pg_restore --dbname="$RESTORE_DB_NAME" --no-owner --no-privileges "$DB_BACKUP_FILE"

table_count="$(psql --dbname="$RESTORE_DB_NAME" --tuples-only --no-align --command "select count(*) from information_schema.tables where table_schema = 'public';")"
if [[ "${table_count:-0}" -lt 1 ]]; then
  fail "Restore produced no public tables in $RESTORE_DB_NAME"
fi

log "Restored database has $table_count public tables"

if [[ -n "$UPLOADS_BACKUP_FILE" ]]; then
  [[ -f "$UPLOADS_BACKUP_FILE" ]] || fail "Uploads backup file does not exist: $UPLOADS_BACKUP_FILE"
  require_command tar

  uploads_tmp_dir="$(mktemp -d)"
  trap 'rm -rf "$uploads_tmp_dir"; cleanup_restore_db' EXIT

  log "Verify uploads archive metadata: $UPLOADS_BACKUP_FILE"
  tar -tzf "$UPLOADS_BACKUP_FILE" >/dev/null

  log "Extract uploads archive into temporary directory"
  tar -xzf "$UPLOADS_BACKUP_FILE" -C "$uploads_tmp_dir"

  if [[ ! -d "$uploads_tmp_dir/uploads" ]]; then
    fail "Uploads archive does not contain top-level uploads directory"
  fi

  log "Uploads archive restored successfully"
fi

if [[ "$KEEP_RESTORE_DB" == "yes" ]]; then
  log "Restore rehearsal passed; restored database kept: $RESTORE_DB_NAME"
else
  log "Restore rehearsal passed; temporary database will be removed"
fi
