#!/bin/sh
set -eu

repo="$(CDPATH='' cd -- "$(dirname -- "$0")/../.." && pwd)"
test_file="$repo/tests/dynamix.plugin.manager/plugin_operation_lock_test.php"

if [ "$(uname -s)" = "Linux" ] && command -v php >/dev/null 2>&1 && command -v flock >/dev/null 2>&1; then
  UNRAID_PLUGIN_MANAGER_FLOCK_PATH="$(command -v flock)" php -d short_open_tag=1 "$test_file"
  if command -v docker >/dev/null 2>&1; then
    docker run --rm --network none \
      --mount "type=bind,source=$repo,target=/work,readonly" \
      php:8.4-cli \
      php -d short_open_tag=1 \
        /work/tests/dynamix.plugin.manager/production_run_alias_test.php
  else
    echo "SKIP: Docker is unavailable for the production /var/run alias test." >&2
  fi
  exit
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "Linux with PHP/flock or Docker is required to run this test." >&2
  exit 1
fi

docker run --rm --network none \
  --mount "type=bind,source=$repo,target=/work,readonly" \
  php:8.4-cli \
  sh -c '
    php -d short_open_tag=1 \
      /work/tests/dynamix.plugin.manager/plugin_operation_lock_test.php &&
    php -d short_open_tag=1 \
      /work/tests/dynamix.plugin.manager/production_run_alias_test.php
  '
