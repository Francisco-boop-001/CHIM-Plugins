#!/bin/bash
set -euo pipefail
test "${WSL_DISTRO_NAME:-}" = DwemerAI4Skyrim3-test
printf 'distro=%s uid=%s time=%s\n' "$WSL_DISTRO_NAME" "$(id -u)" "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
cd /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev
test -f server/manifest.json
env PHPRC="$PWD/tasks/critique-fixes-2026-10-20/runtime/php.ini" PHP_INI_SCAN_DIR= php --ini
env PHPRC="$PWD/tasks/critique-fixes-2026-10-20/runtime/php.ini" PHP_INI_SCAN_DIR= php -v
env PHPRC="$PWD/tasks/critique-fixes-2026-10-20/runtime/php.ini" PHP_INI_SCAN_DIR= php -m
