#!/usr/bin/env bash
if [[ "${WSL_DISTRO_NAME:-}" != "DwemerAI4Skyrim3-test2" ]]; then
  echo 'wrong distro' >&2
  exit 90
fi
cd "$1" || exit 91
php -n tests/identity_source_check.php
identity_status=$?
php -n tests/database_read_bounds_check.php
bounds_status=$?
printf '\nIDENTITY_EXIT=%s\nBOUNDS_EXIT=%s\n' "$identity_status" "$bounds_status"
(( identity_status == 0 && bounds_status == 0 ))
