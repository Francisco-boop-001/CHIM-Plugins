#!/usr/bin/env python3
"""Drive a private, disposable PostgreSQL run; no external database is used."""

from __future__ import annotations

import hashlib
import json
import os
import fcntl
from pathlib import Path
import shutil
import subprocess
import sys
import time


ARTIFACT = Path(os.environ["PCV_READ_TEST_ARTIFACT"])
SCRATCH = Path(os.environ["PCV_READ_TEST_SCRATCH"])
RUN_DIR = Path(os.environ["PCV_READ_TEST_RUN_DIR"])
CORE_SOURCE = ARTIFACT.parent / "department-maintainer-audit-20261026" / "host" / "server-dev"
PROBE_SCRIPT = ARTIFACT / "probe.php"
EXPECTED_OWNER_HASHES = {
    "candidate": {
        "server/state.php": "49c2b53466572015e77117653739966377267b5b50ec4514155e53256bca7361",
        "server/index.php": "58310584871af7e1fc081da1908bc86bcb60afa6cf3c7fc9ad02b9d246c5dc4f",
        "server/scope.php": "e21fd73985424c099332dcbb035e3587c72ab78a21e21781669978dc25e39686",
    },
    "paired": {
        "server/state.php": "b64277236a520b5464a26becc9c7d15dce233fdf2e3f4c99201c568da22e16d6",
        "server/index.php": "58310584871af7e1fc081da1908bc86bcb60afa6cf3c7fc9ad02b9d246c5dc4f",
        "server/scope.php": "1aac2c5124f8886cf87f22e26c621906fdd6c569d66c08b908406fd64d67865f",
    },
}
OWNER_FILES = ["server/state.php", "server/index.php", "server/scope.php"]


def run(command: list[str], *, input_text: str | None = None, timeout: float = 30) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, input=input_text, text=True, capture_output=True, timeout=timeout, check=True)


def psql(sql: str) -> str:
    result = run(
        ["psql", "host=127.0.0.1 port=5432 dbname=dwemer user=dwemer", "-XqAt", "-v", "ON_ERROR_STOP=1", "-c", sql]
    )
    return result.stdout.strip()


def digest_rows(rows: list[dict[str, object]]) -> str:
    ordered = sorted(rows, key=lambda row: int(str(row.get("id", "0"))))
    encoded = json.dumps(ordered, ensure_ascii=False, separators=(",", ":"))
    return hashlib.sha256(encoded.encode("utf-8")).hexdigest()


def retain_harness_sources() -> dict[str, str]:
    global PROBE_SCRIPT
    retained = RUN_DIR / "harness-source"
    retained.mkdir(mode=0o700)
    sources = {
        "run_pg.sh": Path(os.environ["PCV_READ_TEST_RUNNER_COPY"]),
        "pg_driver.py": Path(__file__).resolve(),
        "probe.php": ARTIFACT / "probe.php",
    }
    hashes: dict[str, str] = {}
    for name, source in sources.items():
        expected = os.environ.get("PCV_READ_TEST_RUNNER_SHA256") if name == "run_pg.sh" else None
        source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
        if expected is not None and source_hash != expected:
            raise RuntimeError("Retained run_pg.sh hash does not match the executed runner receipt.")
        target = retained / name
        shutil.copyfile(source, target)
        target_hash = hashlib.sha256(target.read_bytes()).hexdigest()
        if source_hash != target_hash:
            raise RuntimeError(f"Retained {name} differs from the source used by this run.")
        hashes[name] = target_hash
    PROBE_SCRIPT = retained / "probe.php"
    return hashes


def prepare_engine(source_root: Path) -> tuple[Path, Path]:
    engine = SCRATCH / "engine"
    package = engine / "ext" / "private_conversation"
    engine.mkdir(mode=0o700)
    (engine / "ext").mkdir()
    shutil.copytree(source_root / "server", package)
    shutil.copytree(CORE_SOURCE / "lib", engine / "lib")
    shutil.copytree(CORE_SOURCE / "conf", engine / "conf")
    (engine / "log").mkdir(mode=0o700)
    runtime_dir = engine / "log" / "playthrough_runtime"
    runtime_dir.mkdir(mode=0o700)
    (runtime_dir / "work.lock").touch(mode=0o600)
    (engine / "conf" / "conf.php").write_text(
        "<?php\n$DBDRIVER = 'postgresql';\n$PLAYER_NAME = 'Audit Player';\n",
        encoding="utf-8",
    )
    return engine, package


def prepare_catalog() -> None:
    psql("CREATE TABLE public.core_profiles (id integer PRIMARY KEY)")
    ddl = CORE_SOURCE / "lib" / "core" / "database_schema" / "core_npc_master.sql"
    result = run(
        ["psql", "host=127.0.0.1 port=5432 dbname=dwemer user=dwemer", "-X", "-v", "ON_ERROR_STOP=1", "-f", str(ddl)]
    )
    (RUN_DIR / "catalog-schema.stdout.txt").write_text(result.stdout, encoding="utf-8")
    (RUN_DIR / "catalog-schema.stderr.txt").write_text(result.stderr, encoding="utf-8")
    psql("INSERT INTO public.core_profiles(id) VALUES (19)")
    insert_catalog_rows()


def insert_catalog_rows() -> None:
    psql(
        "INSERT INTO public.core_npc_master "
        "(id,npc_name,prompt_head,npc_static_bio,personality,occupation,metadata,gender,race,refid,profile_id,"
        "extended_data,plugin_extended_data,core,base,tags) VALUES "
        "(4001,'Aela','head-a','bio-a','personality-a','hunter','{\"fixture\":\"a\"}'::jsonb,'female','Nord','0000000000012345',19,'{\"extra\":1}'::jsonb,'{\"pcv\":{\"a\":true}}'::jsonb,'core-a','base-a','tag-a'), "
        "(4002,'Faendal','head-b','bio-b','personality-b','archer','{\"fixture\":\"b\"}'::jsonb,'male','Bosmer','0000000000012346',19,'{\"extra\":2}'::jsonb,'{\"pcv\":{\"b\":true}}'::jsonb,'core-b','base-b','tag-b'), "
        "(4003,'Lydia','head-c','bio-c','personality-c','housecarl','{\"fixture\":\"c\"}'::jsonb,'female','Nord','0000000000012347',19,'{\"extra\":3}'::jsonb,'{\"pcv\":{\"c\":true}}'::jsonb,'core-c','base-c','tag-c')"
    )


def php_probe(engine: Path, package: Path, mode: str) -> dict[str, object]:
    result = subprocess.run(
        ["php", str(PROBE_SCRIPT), str(package), str(engine), mode],
        text=True,
        capture_output=True,
        timeout=9,
    )
    (RUN_DIR / f"{mode}.stdout.txt").write_text(result.stdout, encoding="utf-8")
    (RUN_DIR / f"{mode}.stderr.txt").write_text(result.stderr, encoding="utf-8")
    if result.returncode != 0:
        raise RuntimeError(f"Probe {mode} exited {result.returncode}; inspect its bounded stdout/stderr receipt.")
    try:
        value = json.loads(result.stdout)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"Probe {mode} did not return JSON.") from exc
    if not isinstance(value, dict):
        raise RuntimeError(f"Probe {mode} returned an invalid result shape.")
    return value


def runtime_barrier_probe(engine: Path, package: Path, mode: str) -> subprocess.CompletedProcess[str]:
    result = subprocess.run(
        ["php", str(PROBE_SCRIPT), str(package), str(engine), mode],
        text=True,
        capture_output=True,
        timeout=6,
    )
    (RUN_DIR / f"runtime_barrier_{mode}.stdout.txt").write_text(result.stdout, encoding="utf-8")
    (RUN_DIR / f"runtime_barrier_{mode}.stderr.txt").write_text(result.stderr, encoding="utf-8")
    return result


def passed_case(case: str, result: dict[str, object], passed: bool) -> dict[str, object]:
    result["case"] = case
    result["passed"] = passed
    return result


def test_candidate_boundaries(engine: Path, package: Path) -> list[dict[str, object]]:
    results: list[dict[str, object]] = []

    result = php_probe(engine, package, "read_bounds_default")
    results.append(passed_case(
        "default_read_bounds",
        result,
        result.get("status") == "ok" and result.get("lock_ms") == 2000 and result.get("statement_ms") == 3000,
    ))

    psql("UPDATE chim_meta.playthrough_profiles SET is_active=false")
    result = php_probe(engine, package, "identity_no_active")
    results.append(passed_case(
        "identity_no_active_profile_fallback",
        result,
        result.get("status") == "ok" and result.get("fallback_matches") is True and result.get("player_matches") is True,
    ))
    psql("UPDATE chim_meta.playthrough_profiles SET is_active=true WHERE schema_name='chim_profile_audit'")

    psql("ALTER TABLE chim_meta.playthrough_profiles RENAME TO pcv_probe_profiles_hidden")
    try:
        result = php_probe(engine, package, "identity_no_profile_table")
    finally:
        psql("ALTER TABLE chim_meta.pcv_probe_profiles_hidden RENAME TO playthrough_profiles")
    results.append(passed_case(
        "identity_no_profile_table_fallback",
        result,
        result.get("status") == "ok" and result.get("fallback_matches") is True and result.get("player_matches") is True,
    ))

    psql("TRUNCATE TABLE public.core_npc_master")
    result = php_probe(engine, package, "catalog_reader")
    results.append(passed_case(
        "catalog_empty_is_available",
        result,
        result.get("status") == "ok" and result.get("row_count") == 0,
    ))
    insert_catalog_rows()

    psql("ALTER TABLE public.core_npc_master RENAME TO pcv_probe_catalog_hidden")
    try:
        result = php_probe(engine, package, "catalog_reader")
    finally:
        psql("ALTER TABLE public.pcv_probe_catalog_hidden RENAME TO core_npc_master")
    results.append(passed_case(
        "catalog_failure_is_not_empty",
        result,
        result.get("status") == "error" and result.get("failure") == "probe_exception"
        and result.get("error_class") == "RuntimeException",
    ))

    result = php_probe(engine, package, "catalog_preservation_index")
    results.append(passed_case(
        "caller_transaction_preserved_on_success",
        result,
        result.get("status") == "ok" and result.get("row_count") == 3 and result.get("caller_preserved") is True,
    ))

    result = php_probe(engine, package, "catalog_preservation_statement_default")
    elapsed = float(result.get("elapsed_ms", 0))
    results.append(passed_case(
        "default_statement_timeout_cancels_and_preserves_caller",
        result,
        result.get("status") == "error" and result.get("caller_preserved") is True
        and result.get("error_class") == "RuntimeException" and 2600 <= elapsed <= 3700,
    ))

    psql("ALTER ROLE dwemer SET lock_timeout='500ms'; ALTER ROLE dwemer SET statement_timeout='700ms'")
    result = php_probe(engine, package, "read_bounds_shorter")
    results.append(passed_case(
        "shorter_positive_bounds_are_retained",
        result,
        result.get("status") == "ok" and result.get("lock_ms") == 500 and result.get("statement_ms") == 700,
    ))

    result = hold_table("public.core_npc_master", 2.8, "catalog_preservation_index", engine, package)
    elapsed = float(result.get("elapsed_ms", 0))
    results.append(passed_case(
        "shorter_lock_timeout_fails_closed_and_preserves_caller",
        result,
        result.get("status") == "error" and result.get("caller_preserved") is True
        and result.get("error_class") == "RuntimeException" and 300 <= elapsed <= 1300,
    ))

    result = php_probe(engine, package, "catalog_preservation_statement_short")
    elapsed = float(result.get("elapsed_ms", 0))
    results.append(passed_case(
        "shorter_statement_timeout_cancels_and_preserves_caller",
        result,
        result.get("status") == "error" and result.get("caller_preserved") is True
        and result.get("error_class") == "RuntimeException" and 400 <= elapsed <= 1400,
    ))
    psql("ALTER ROLE dwemer RESET lock_timeout; ALTER ROLE dwemer RESET statement_timeout")
    return results


def test_paired_boundaries(engine: Path, package: Path) -> list[dict[str, object]]:
    """Small common-source check for the exact Department overlay."""
    results: list[dict[str, object]] = []

    identity = php_probe(engine, package, "identity")
    results.append(passed_case(
        "identity_unlocked",
        identity,
        identity.get("status") == "ok"
        and identity.get("has_identity") is True
        and identity.get("player_matches") is True,
    ))

    catalog = php_probe(engine, package, "catalog_scope")
    results.append(passed_case(
        "catalog_scope_populated",
        catalog,
        catalog.get("status") == "ok"
        and catalog.get("row_count") == 3
        and catalog.get("matches_native") is True,
    ))

    identity = hold_table("chim_meta.playthrough_profiles", 2.8, "identity_locked", engine, package)
    elapsed = float(identity.get("elapsed_ms", 0))
    results.append(passed_case(
        "identity_lock_is_bounded",
        identity,
        1500 <= elapsed <= 2400
        and identity.get("status") == "unavailable"
        and identity.get("has_identity") is False,
    ))

    catalog = hold_table("public.core_npc_master", 2.8, "catalog_index_locked", engine, package)
    elapsed = float(catalog.get("elapsed_ms", 0))
    results.append(passed_case(
        "catalog_lock_is_bounded_and_fails_closed",
        catalog,
        1500 <= elapsed <= 2400
        and catalog.get("status") == "error"
        and catalog.get("failure") == "probe_exception",
    ))

    results.extend(test_runtime_barrier(engine, package, "paired"))
    return results


def test_runtime_barrier(engine: Path, package: Path, phase: str) -> list[dict[str, object]]:
    """Show that readers honor CHIM's shared work lease before opening PostgreSQL."""
    lock_path = engine / "log" / "playthrough_runtime" / "work.lock"
    results: list[dict[str, object]] = []
    with lock_path.open("r+b") as lease:
        fcntl.flock(lease.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        try:
            for mode in ("identity", "catalog_index"):
                started = time.monotonic()
                process = runtime_barrier_probe(engine, package, mode)
                elapsed_ms = round((time.monotonic() - started) * 1000, 1)
                result: dict[str, object] = {
                    "case": f"runtime_barrier_{mode}",
                    "mode": mode,
                    "elapsed_ms": elapsed_ms,
                    "exit_code": process.returncode,
                }
                if phase in {"candidate", "paired"}:
                    result["passed"] = (
                        process.returncode == 75
                        and elapsed_ms < 1500
                        and process.stdout == ""
                        and process.stderr == ""
                    )
                    result["status"] = "refused_by_runtime_barrier" if result["passed"] else "unexpected_result"
                else:
                    try:
                        value = json.loads(process.stdout)
                    except json.JSONDecodeError:
                        value = {}
                    result["status"] = value.get("status", "no_json") if isinstance(value, dict) else "invalid_json"
                    result["passed"] = process.returncode == 0 and result["status"] == "ok"
                results.append(result)
        finally:
            fcntl.flock(lease.fileno(), fcntl.LOCK_UN)
    return results


def hold_table(table: str, seconds: float, probe: str, engine: Path, package: Path) -> dict[str, object]:
    if table not in {"chim_meta.playthrough_profiles", "public.core_npc_master"}:
        raise ValueError("Unexpected table in held-lock fixture.")
    holder = subprocess.Popen(
        ["psql", "host=127.0.0.1 port=5432 dbname=dwemer user=dwemer", "-XqAt", "-v", "ON_ERROR_STOP=1"],
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
    )
    assert holder.stdin is not None and holder.stdout is not None
    holder.stdin.write(
        f"SET statement_timeout=0; SET lock_timeout=0; BEGIN; "
        f"LOCK TABLE {table} IN ACCESS EXCLUSIVE MODE; \\echo HELD\n"
        f"SELECT pg_sleep({seconds}); COMMIT;\n"
    )
    holder.stdin.close()
    marker = holder.stdout.readline().strip()
    if marker != "HELD":
        stderr = holder.stderr.read() if holder.stderr else ""
        holder.wait(timeout=5)
        raise RuntimeError(f"Fixture lock did not become held for {table}: {stderr[-500:]}")
    started = time.monotonic()
    result = php_probe(engine, package, probe)
    result["lockElapsedWallMs"] = round((time.monotonic() - started) * 1000, 1)
    try:
        holder.wait(timeout=6)
    except subprocess.TimeoutExpired as exc:
        holder.kill()
        raise RuntimeError("Fixture lock holder exceeded its bounded sleep.") from exc
    if holder.returncode != 0:
        stderr = holder.stderr.read() if holder.stderr else ""
        raise RuntimeError(f"Fixture lock holder exited {holder.returncode}: {stderr[-500:]}")
    return result


def main() -> int:
    if len(sys.argv) != 3:
        raise SystemExit("Usage: pg_driver.py SOURCE_ROOT baseline|candidate|paired")
    harness_hashes = retain_harness_sources()
    source_root = Path(sys.argv[1]).resolve(strict=True)
    phase = sys.argv[2]
    if phase not in {"baseline", "candidate", "paired"} or not (source_root / "server" / "state.php").is_file():
        raise SystemExit("Invalid phase or source root.")
    source_hashes = {
        str(path): hashlib.sha256((source_root / path).read_bytes()).hexdigest()
        for path in OWNER_FILES
    }
    expected_hashes = EXPECTED_OWNER_HASHES.get(phase)
    if expected_hashes is not None and source_hashes != expected_hashes:
        raise SystemExit(f"{phase} phase requires the reviewed exact PCV owner source hashes.")
    baseline_state_hash = hashlib.sha256((source_root / "server/state.php").read_bytes()).hexdigest()
    if phase == "baseline" and baseline_state_hash != "142f5a026740dfb629af925005c7e931396aa3dcde0d534002fb42b545c5664d":
        raise SystemExit("Baseline phase requires the approved e89e67d state.php export hash.")

    engine, package = prepare_engine(source_root)
    prepare_catalog()
    initialized = php_probe(engine, package, "initialize")
    if initialized.get("initialized") is not True:
        raise RuntimeError("Profile fixture initialization failed.")

    results: list[dict[str, object]] = []
    if phase == "candidate":
        results.extend(test_runtime_barrier(engine, package, phase))
        results.extend(test_candidate_boundaries(engine, package))
    elif phase == "paired":
        results.extend(test_paired_boundaries(engine, package))
        if len(results) != 6:
            raise RuntimeError("The paired overlay phase must remain exactly six bounded checks.")
    if phase in {"baseline", "candidate"}:
        identity = php_probe(engine, package, "identity")
        identity["case"] = "identity_unlocked"
        identity["passed"] = identity.get("has_identity") is True and identity.get("player_matches") is True
        results.append(identity)

        for mode in ("catalog_index", "catalog_scope"):
            result = php_probe(engine, package, mode)
            result["case"] = f"{mode}_populated"
            result["passed"] = result.get("status") == "ok" and result.get("row_count") == 3 and result.get("matches_native") is True
            results.append(result)

        locked_cases = [
            ("identity_locked", "chim_meta.playthrough_profiles", "identity_locked"),
            ("catalog_index_locked", "public.core_npc_master", "catalog_index_locked"),
            ("catalog_scope_locked", "public.core_npc_master", "catalog_scope_locked"),
        ]
        for case_name, table, probe_name in locked_cases:
            result = hold_table(table, 2.8, probe_name, engine, package)
            elapsed = float(result.get("elapsed_ms", 0))
            result["case"] = case_name
            if phase == "baseline":
                returned_expected_value = (
                    result.get("status") == "ok"
                    and (result.get("has_identity") is True if case_name == "identity_locked" else result.get("row_count") == 3)
                )
                result["passed"] = returned_expected_value and elapsed >= 2400
            else:
                bounded_wait = 1500 <= elapsed <= 2400
                fail_closed = (
                    result.get("status") == "unavailable" and result.get("has_identity") is False
                    if case_name == "identity_locked"
                    else result.get("status") == "error" and result.get("failure") == "probe_exception"
                )
                result["passed"] = bounded_wait and fail_closed
            results.append(result)

    core_files = [
        "lib/playthrough_home.php",
        "lib/playthrough_preferences.php",
        "lib/playthrough_runtime.php",
        "lib/playthrough_switching.php",
        "lib/postgresql.class.php",
        "lib/core/npc_master.class.php",
        "lib/core/database_schema/core_npc_master.sql",
    ]
    executed_owner_hashes = {
        str(path): hashlib.sha256((package / Path(path).name).read_bytes()).hexdigest()
        for path in OWNER_FILES
    }
    if source_hashes != executed_owner_hashes:
        raise RuntimeError("Executed isolated package bytes differ from the supplied source root.")
    core_hashes = {
        str(path): hashlib.sha256((CORE_SOURCE / path).read_bytes()).hexdigest()
        for path in core_files
    }
    executed_core_hashes = {
        str(path): hashlib.sha256((engine / path).read_bytes()).hexdigest()
        for path in core_files
    }
    if core_hashes != executed_core_hashes:
        raise RuntimeError("Executed isolated CHIM helper bytes differ from the retained source snapshot.")
    receipt = {
        "phase": phase,
        "source_root": str(source_root),
        "source_sha256": source_hashes,
        "executed_package_sha256": executed_owner_hashes,
        "retained_core_root": str(CORE_SOURCE),
        "retained_core_sha256": core_hashes,
        "executed_core_sha256": executed_core_hashes,
        "harness_sha256": harness_hashes,
        "executed_probe_sha256": hashlib.sha256(PROBE_SCRIPT.read_bytes()).hexdigest(),
        "postgres_version": psql("SHOW server_version"),
        "php_version": run(["php", "-r", "echo PHP_VERSION;"]).stdout.strip(),
        "network_namespace": os.readlink("/proc/self/ns/net"),
        "initial_network_namespace": os.readlink("/proc/1/ns/net"),
        "database": "fresh private-namespace localhost:5432/dwemer only",
        "cases": results,
        "passed": sum(1 for result in results if result.get("passed") is True),
        "total": len(results),
    }
    if receipt["network_namespace"] == receipt["initial_network_namespace"]:
        raise RuntimeError("Network namespace isolation check failed.")
    path = RUN_DIR / f"{phase}-receipt.json"
    path.write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"phase": phase, "passed": receipt["passed"], "total": receipt["total"], "receipt": str(path)}, indent=2))
    if phase in {"candidate", "paired"} and receipt["passed"] != receipt["total"]:
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
