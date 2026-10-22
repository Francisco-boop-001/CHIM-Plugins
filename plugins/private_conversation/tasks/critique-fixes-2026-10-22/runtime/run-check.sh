#!/bin/bash
set -euo pipefail
test "${WSL_DISTRO_NAME:-}" = DwemerAI4Skyrim3-test
repo=/mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev
out="$repo/tasks/critique-fixes-2026-10-22/runtime"
label=$1
shift
case "$label" in ''|*[!a-zA-Z0-9_-]*) exit 98;; esac
cd "$repo"
export PHPRC="$out/php.ini"
export PHP_INI_SCAN_DIR=
set +e
php "$@" > "$out/$label.output.txt" 2>&1
result=$?
set -e
printf '%s\n' "$result" > "$out/$label.exit.txt"
cat "$out/$label.output.txt"
exit "$result"
