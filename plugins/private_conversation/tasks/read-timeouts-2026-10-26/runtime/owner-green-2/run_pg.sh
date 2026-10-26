#!/bin/bash
set -euo pipefail

if [[ "${WSL_DISTRO_NAME:-}" != "DwemerAI4Skyrim3-test2" ]]; then
  echo 'Refusing to run outside DwemerAI4Skyrim3-test2.' >&2
  exit 90
fi

if [[ $# -ne 3 ]]; then
  echo 'Usage: run_pg.sh SOURCE_ROOT baseline|candidate|paired RUN_ID' >&2
  exit 2
fi

source_arg=$1
phase=$2
run_id=$3
case "$phase" in baseline|candidate|paired) ;; *) echo 'Invalid phase.' >&2; exit 2 ;; esac
if [[ ! "$run_id" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{0,39}$ ]]; then
  echo 'Invalid run ID.' >&2
  exit 2
fi
case "$source_arg" in /mnt/k/ActorwrightExchange/*) ;; *) echo 'Source root must be inside K:\\ActorwrightExchange.' >&2; exit 2 ;; esac

if [[ "${PCV_READ_TEST_NETNS:-}" != 1 ]]; then
  exec unshare --net env PCV_READ_TEST_NETNS=1 /bin/bash "$0" "$@"
fi

if [[ "$(readlink /proc/self/ns/net)" == "$(readlink /proc/1/ns/net)" ]]; then
  echo 'Refusing to run without a private network namespace.' >&2
  exit 91
fi
if [[ "$(id -u)" != 0 ]]; then
  echo 'Run this harness as root inside the guarded TEST2 distro.' >&2
  exit 92
fi

ip link set lo up

artifact=/mnt/k/ActorwrightExchange/artifacts/pcv-read-timeouts-20261026
source_root=$(realpath -e -- "$source_arg")
case "$source_root" in /mnt/k/ActorwrightExchange/*) ;; *) echo 'Resolved source escaped the workspace.' >&2; exit 93 ;; esac
test -f "$source_root/server/index.php"
test -f "$source_root/server/scope.php"
test -f "$source_root/server/state.php"

mkdir -p "$artifact/runs"
run_dir="$artifact/runs/$run_id"
if [[ -e "$run_dir" || -L "$run_dir" ]]; then
  echo 'Run ID already exists; refusing to overwrite evidence.' >&2
  exit 94
fi
mkdir -m 0700 -- "$run_dir"
umask 077
scratch=$(mktemp -d /tmp/pcv-read-timeouts-20261026-XXXXXXXX)
case "$scratch" in /tmp/pcv-read-timeouts-20261026-????????) ;; *) echo 'Unexpected scratch path.' >&2; exit 95 ;; esac
chown postgres:postgres "$scratch"
chmod 0700 "$scratch"

pg=/usr/lib/postgresql/15/bin
pgdata="$scratch/pg"
cleanup() {
  rc=$?
  trap - EXIT
  set +e
  cluster_stopped=false
  if [[ -f "$pgdata/postmaster.pid" ]]; then
    pid=$(head -n 1 "$pgdata/postmaster.pid")
    if [[ "$pid" =~ ^[0-9]+$ ]] && [[ -r "/proc/$pid/cmdline" ]] \
      && tr '\0' ' ' < "/proc/$pid/cmdline" | grep -F -- "-D $pgdata" >/dev/null; then
      if runuser -u postgres -- "$pg/pg_ctl" -D "$pgdata" -m fast -w stop \
        >>"$run_dir/stop.log" 2>&1 && [[ ! -e "$pgdata/postmaster.pid" ]]; then
        cluster_stopped=true
      else
        printf 'Owned cluster did not stop cleanly; scratch retained at %s\n' "$scratch" >"$run_dir/cleanup-error.txt"
        rc=96
      fi
    else
      printf 'Refused to stop unverified PID %s\n' "$pid" >"$run_dir/cleanup-error.txt"
      rc=96
    fi
  elif [[ ! -d "$pgdata" ]]; then
    cluster_stopped=true
  elif ! runuser -u postgres -- "$pg/pg_ctl" -D "$pgdata" status >>"$run_dir/stop.log" 2>&1; then
    cluster_stopped=true
  else
    printf 'Cluster status is uncertain; scratch retained at %s\n' "$scratch" >"$run_dir/cleanup-error.txt"
    rc=96
  fi
  if [[ -f "$scratch/pg.log" ]]; then cp -- "$scratch/pg.log" "$run_dir/postgres.log"; fi
  scratch_removed=false
  if [[ "$cluster_stopped" == true && -d "$scratch" && "$scratch" =~ ^/tmp/pcv-read-timeouts-20261026-[A-Za-z0-9]+$ ]]; then
    rm -rf -- "$scratch"
    [[ ! -e "$scratch" ]] && scratch_removed=true
  else
    if [[ "$cluster_stopped" != true ]]; then
      rc=96
    else
      printf 'Refused to remove unverified scratch path %s\n' "$scratch" >"$run_dir/cleanup-error.txt"
      rc=97
    fi
  fi
  printf '{"cluster_stopped":%s,"private_netns":true,"scratch_removed":%s}\n' \
    "$cluster_stopped" "$scratch_removed" >"$run_dir/cleanup.json"
  exit "$rc"
}
trap cleanup EXIT

cp -- "$0" "$run_dir/run_pg.sh"
runner_sha256=$(sha256sum "$run_dir/run_pg.sh" | awk '{print $1}')
export PCV_READ_TEST_RUNNER_COPY="$run_dir/run_pg.sh"
export PCV_READ_TEST_RUNNER_SHA256="$runner_sha256"

runuser -u postgres -- "$pg/initdb" -U dwemer -D "$pgdata" \
  --auth-local=trust --auth-host=trust --no-instructions >"$run_dir/initdb.log" 2>&1
runuser -u postgres -- "$pg/pg_ctl" -D "$pgdata" -l "$scratch/pg.log" \
  -o "-h 127.0.0.1 -k $scratch -p 5432" -w start >"$run_dir/start.log" 2>&1
psql "host=127.0.0.1 port=5432 dbname=postgres user=dwemer" -XqAt -v ON_ERROR_STOP=1 \
  -c 'CREATE DATABASE dwemer' >"$run_dir/create-db.log" 2>&1

export PCV_READ_TEST_ARTIFACT="$artifact"
export PCV_READ_TEST_SCRATCH="$scratch"
export PCV_READ_TEST_RUN_DIR="$run_dir"
python3 "$artifact/pg_driver.py" "$source_root" "$phase"

cleanup
