#!/usr/bin/env bash
set -Eeuo pipefail
repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
sandbox_dir="$(mktemp -d)"
trap 'rm -rf "$sandbox_dir"' EXIT
mkdir -p "$sandbox_dir"/{dev,dev2,prod}/.git

write_env() {
  local dir="$1" database="$2" url="$3"
  cat >"$dir/.env.local" <<EOF
APP_ENV=staging
APP_SECRET=dev2-deploy-test-secret
DATABASE_URL=mysql://test:test@localhost:3306/$database?serverVersion=8.4&charset=utf8mb4
SITE_URL=$url
DEFAULT_URI=$url
STAGING_AUTH_ENABLED=0
STAGING_ALLOW_PUBLIC=1
MAILER_DSN=null://null
EOF
}
write_env "$sandbox_dir/dev" dev_db https://dev.zaborprofil.ru
write_env "$sandbox_dir/dev2" dev2_db https://dev2.zaborprofil.ru
write_env "$sandbox_dir/prod" prod_db https://zaborprofil.ru

check() {
  local path="$1"
  cat "$repo_dir/tools/deploy/deploy-beget.sh" "$repo_dir/tools/deploy/check-dev2-beget.sh" |
    DEPLOY_SOURCE_ONLY=1 DEPLOY_PATH="$path" DEV1_PATH="$sandbox_dir/dev" DEPLOY_PROD_PATH="$sandbox_dir/prod" bash -s
}
reject() {
  local path="$1" message="$2"
  if check "$path" >"$sandbox_dir/result.log" 2>&1; then
    printf 'Expected rejection: %s\n' "$message" >&2
    exit 1
  fi
}
check "$sandbox_dir/dev2" >/dev/null 2>&1
reject "$sandbox_dir/missing" "missing clone"
reject "$sandbox_dir/dev" "dev directory"
reject "$sandbox_dir/prod" "production directory"
ln -s "$sandbox_dir/dev" "$sandbox_dir/alias"
reject "$sandbox_dir/alias" "alias of dev"

write_env "$sandbox_dir/dev2" dev_db https://dev2.zaborprofil.ru
reject "$sandbox_dir/dev2" "shared database"
write_env "$sandbox_dir/dev2" dev2_db https://dev.zaborprofil.ru
reject "$sandbox_dir/dev2" "wrong URL"
write_env "$sandbox_dir/dev2" dev2_db https://dev2.zaborprofil.ru
mkdir -p "$sandbox_dir/dev/shared"
ln -s "$sandbox_dir/dev/shared" "$sandbox_dir/dev2/shared"
reject "$sandbox_dir/dev2" "shared files"
rm "$sandbox_dir/dev2/shared"
mkdir -p "$sandbox_dir/dev2/shared"
check "$sandbox_dir/dev2" >/dev/null 2>&1
printf '%s\n' "dev2 deployment isolation tests passed"
