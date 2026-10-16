#!/usr/bin/env python3
"""Bounded B3 install, upgrade, dashboard, and native PostgreSQL recovery campaign."""

from __future__ import annotations

import hashlib
import html
import http.client
import json
import os
import re
import shutil
import socket
import subprocess
import sys
import time
import zipfile
from pathlib import Path, PurePosixPath
from typing import Any

SUITE = "mp-install-recovery-b3"
RESULT_PREFIX = "MP_INSTALL_RECOVERY_RESULT="
CHILD_PREFIX = "MP_INSTALL_CHILD_RESULT="
EXPECTED_ARCHIVES = {
    "0.1.19": "a847c19f2d855af94f86bd28de6a29092e084afc0793403bbf78346749926d33",
    "0.1.20": "d2e9e29fd16599cf4bf0bcfa3f1b8b6b0da233ee71151bebd0df4d3f70a5cfad",
}
SCENARIOS = [
    "clean-install-package-integrity",
    "upgrade-package-integrity-and-old-extension-backup",
    "upgrade-preserves-db-config-and-unrelated-file",
    "dashboard-loopback-csp-and-method-guard",
    "dashboard-filtered-diagnostics-download",
    "pause-and-core-off-no-model-or-write",
    "automatic-backup-created-and-validated",
    "fresh-database-and-filesystem-restore",
    "dashboard-reads-restored-state",
]
CHUNK_BYTES = 1_400_000
SAFE_ROOT_RE = re.compile(r"\A/tmp/mp-install-recovery-([0-9a-f]{32})\Z")
SAFE_BACKUP_RE = re.compile(r"\Aauto_backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}\.sql\Z")
SUBPROCESSES: list[dict[str, Any]] = []


class CampaignFailure(Exception):
    def __init__(self, code: str):
        super().__init__(code)
        self.code = code


def require(condition: bool, code: str) -> None:
    if not condition:
        raise CampaignFailure(code)


def required_path(name: str) -> Path:
    value = os.environ.get(name, "")
    require(bool(value), "env-missing")
    path = Path(value)
    require(path.exists(), "input-missing")
    return path.resolve(strict=True)


def required_text(name: str) -> str:
    value = os.environ.get(name, "")
    require(bool(value), "env-missing")
    return value


def contained(path: Path, base: Path) -> bool:
    try:
        path.resolve(strict=True).relative_to(base.resolve(strict=True))
        return True
    except (OSError, ValueError):
        return False


def digest_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            h.update(block)
    return h.hexdigest()


def json_file(path: Path) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError):
        raise CampaignFailure("json-input-invalid") from None


def qident(value: str) -> str:
    return '"' + value.replace('"', '""') + '"'


def sql_literal(value: str) -> str:
    return "'" + value.replace("'", "''") + "'"


def clean_relative(raw: str) -> str:
    name = raw.replace("\\", "/").strip("/")
    path = PurePosixPath(name)
    require(name != "" and not path.is_absolute() and ".." not in path.parts, "archive-path-invalid")
    return path.as_posix()


def manifest_entries(value: Any) -> list[dict[str, Any]]:
    entries: Any = value
    if isinstance(value, dict):
        entries = value.get("files", value.get("inputs", value.get("inventory")))
    if isinstance(entries, dict):
        normalized = []
        for name, meta in entries.items():
            if isinstance(meta, dict):
                normalized.append({"path": name, **meta})
        entries = normalized
    require(isinstance(entries, list) and entries, "core-inventory-invalid")
    return [entry for entry in entries if isinstance(entry, dict) and isinstance(entry.get("path"), str)]


def core_relative(raw: str) -> str:
    parts = list(PurePosixPath(raw.replace("\\", "/")).parts)
    if "runtime-core" in parts:
        parts = parts[parts.index("runtime-core") + 1 :]
    elif parts and parts[0] in ("artifacts", "mp-install-recovery-20261016") and "core" in parts:
        parts = parts[parts.index("core") + 1 :]
    return clean_relative(PurePosixPath(*parts).as_posix())


def validate_core(core: Path, inventory: Path) -> list[tuple[str, Path]]:
    entries = manifest_entries(json_file(inventory))
    validated: list[tuple[str, Path]] = []
    seen: set[str] = set()
    for entry in entries:
        rel = core_relative(entry["path"])
        path = core.joinpath(*PurePosixPath(rel).parts)
        expected_sha = entry.get("sha256")
        expected_bytes = entry.get("bytes")
        require(rel not in seen and path.is_file() and not path.is_symlink(), "core-file-missing")
        require(isinstance(expected_sha, str) and re.fullmatch(r"[a-f0-9]{64}", expected_sha), "core-hash-missing")
        require(isinstance(expected_bytes, int) and expected_bytes >= 0, "core-size-missing")
        require(path.stat().st_size == expected_bytes and digest_file(path) == expected_sha, "core-input-hash")
        validated.append((rel, path))
        seen.add(rel)
    require(len(validated) == 28, "core-inventory-size")
    required = {
        "main.php", "lib/automatic_backup.php", "lib/chat_helper_functions.php",
        "lib/chim_interaction.php", "lib/logger.php", "lib/playthrough_runtime.php",
        "lib/plugin_package_manager.php", "lib/postgresql.class.php",
        "lib/relationship_manager.php", "lib/settings.php", "ui/api/plugin_packages.php",
    }
    require(required.issubset(seen), "core-closure-incomplete")
    return validated


def package_manifest(path: Path, version: str) -> dict[str, Any]:
    require(path.is_file() and not path.is_symlink(), "package-input-invalid")
    require(digest_file(path) == EXPECTED_ARCHIVES[version], "package-input-hash")
    try:
        with zipfile.ZipFile(path, "r") as archive:
            names = archive.namelist()
            require("manifest.json" in names and "checksums.sha256" in names, "package-metadata-missing")
            manifest = json.loads(archive.read("manifest.json").decode("utf-8"))
            checksum_text = archive.read("checksums.sha256").decode("utf-8")
            checksums: dict[str, str] = {}
            for line in checksum_text.splitlines():
                match = re.fullmatch(r"\s*([a-f0-9]{64})\s+\*?(.+?)\s*", line)
                require(match is not None, "package-checksum-format")
                checksums[clean_relative(match.group(2))] = match.group(1)
            for name in names:
                if name.endswith("/") or name == "checksums.sha256":
                    continue
                safe_name = clean_relative(name)
                require(safe_name in checksums, "package-checksum-coverage")
                require(hashlib.sha256(archive.read(name)).hexdigest() == checksums[safe_name], "package-member-hash")
    except (OSError, zipfile.BadZipFile, UnicodeError, json.JSONDecodeError):
        raise CampaignFailure("package-archive-invalid") from None
    require(isinstance(manifest, dict), "package-manifest-invalid")
    require(str(manifest.get("version", "")) == version, "package-version-mismatch")
    name = str(manifest.get("name", "")).strip()
    require(re.sub(r"[^a-z0-9]+", "_", name.lower()).strip("_") == "mind_poisoning", "package-name-mismatch")
    require(str(manifest.get("schema_version", "")) == "4", "package-schema-mismatch")
    return manifest


def package_server_files(path: Path) -> dict[str, str]:
    result: dict[str, str] = {}
    try:
        with zipfile.ZipFile(path, "r") as archive:
            checksums: dict[str, str] = {}
            for line in archive.read("checksums.sha256").decode("utf-8").splitlines():
                match = re.fullmatch(r"\s*([a-f0-9]{64})\s+\*?(.+?)\s*", line)
                require(match is not None, "package-checksum-format")
                checksums[clean_relative(match.group(2))] = match.group(1)
            for name in archive.namelist():
                if name.startswith("server/") and not name.endswith("/"):
                    rel = clean_relative(name[len("server/") :])
                    result[rel] = checksums[clean_relative(name)]
    except (OSError, zipfile.BadZipFile, UnicodeError):
        raise CampaignFailure("package-archive-invalid") from None
    require(bool(result), "package-server-empty")
    return result


def file_hash_map(path: Path) -> dict[str, str]:
    require(path.is_dir() and not path.is_symlink(), "tree-missing")
    result: dict[str, str] = {}
    for current, dirs, files in os.walk(path, followlinks=False):
        current_path = Path(current)
        for name in dirs:
            require(not (current_path / name).is_symlink(), "tree-symlink")
        for name in files:
            file_path = current_path / name
            require(file_path.is_file() and not file_path.is_symlink(), "tree-entry-invalid")
            result[file_path.relative_to(path).as_posix()] = digest_file(file_path)
    return result


def copy_core_into_app(core_files: list[tuple[str, Path]], app: Path) -> None:
    app.mkdir(mode=0o700, parents=False, exist_ok=False)
    for rel, source in core_files:
        if rel.startswith("fixture/"):
            continue
        target = app.joinpath(*PurePosixPath(rel).parts)
        target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        shutil.copy2(source, target, follow_symlinks=False)
    for relative in ("log", "conf/chim_interaction", "data", "ui/data/databasebackups"):
        app.joinpath(*relative.split("/")).mkdir(mode=0o700, parents=True, exist_ok=True)
    (app / "conf" / "chim_interaction" / "state.json").write_text(
        '{"enabled":true,"generation":1}', encoding="utf-8"
    )
    (app / "data" / "mind_poisoning.json").write_text('{"enabled":true}', encoding="utf-8")


def checksum_map_from_record(value: Any, package_files: dict[str, str]) -> dict[str, str]:
    if isinstance(value, dict):
        result: dict[str, str] = {}
        for key, item in value.items():
            rel = clean_relative(str(key)).removeprefix("server/")
            if isinstance(item, str) and re.fullmatch(r"[a-f0-9]{64}", item):
                result[rel] = item
            elif isinstance(item, dict):
                sha = item.get("sha256", item.get("hash"))
                if isinstance(sha, str) and re.fullmatch(r"[a-f0-9]{64}", sha):
                    result[rel] = sha
        return result
    if isinstance(value, list):
        result = {}
        for item in value:
            if isinstance(item, str):
                rel = clean_relative(item).removeprefix("server/")
                if rel in package_files:
                    result[rel] = package_files[rel]
            elif isinstance(item, dict):
                name = item.get("path", item.get("file"))
                sha = item.get("sha256", item.get("hash"))
                if isinstance(name, str) and isinstance(sha, str):
                    result[clean_relative(name).removeprefix("server/")] = sha
        return result
    return {}

def find_package_record(app: Path, manifest: dict[str, Any], package_files: dict[str, str]) -> tuple[Path, dict[str, Any]]:
    packages_dir = app / "data" / "plugin_packages" / "packages"
    require(packages_dir.is_dir(), "registry-missing")
    wanted_name = str(manifest.get("name", "")).strip().lower()
    matches: list[tuple[Path, dict[str, Any]]] = []
    for path in packages_dir.glob("*.json"):
        record = json_file(path)
        if not isinstance(record, dict):
            continue
        record_manifest = record.get("manifest")
        record_manifest = record_manifest if isinstance(record_manifest, dict) else {}
        name = str(record_manifest.get("name", record.get("name", ""))).strip().lower()
        if name == wanted_name:
            matches.append((path, record))
    require(len(matches) == 1, "registry-record-count")
    path, record = matches[0]
    require(record.get("archive_sha256") == manifest.get("_fixture_archive_sha256"), "registry-archive-hash")
    record_manifest = record.get("manifest")
    require(isinstance(record_manifest, dict) and record_manifest.get("version") == manifest.get("version"), "registry-version")
    server = record.get("server")
    ledger = checksum_map_from_record(server.get("files") if isinstance(server, dict) else None, package_files)
    if not ledger:
        ledger = checksum_map_from_record(record.get("files"), package_files)
    require(ledger == package_files, "registry-file-ledger")
    return path, record


def api_request(base: str, method: str, path: str, body: bytes | None = None, content_type: str | None = None) -> tuple[int, dict[str, str], bytes]:
    connection = http.client.HTTPConnection("127.0.0.1", int(base.rsplit(":", 1)[1]), timeout=5)
    headers = {"Connection": "close"}
    if content_type:
        headers["Content-Type"] = content_type
    try:
        connection.request(method, path, body=body, headers=headers)
        response = connection.getresponse()
        return response.status, {key.lower(): value for key, value in response.getheaders()}, response.read()
    except (OSError, http.client.HTTPException):
        raise CampaignFailure("http-transport") from None
    finally:
        connection.close()


class PHPServer:
    def __init__(self, app: Path, root: Path, evidence: Path):
        self.app = app
        self.root = root
        self.evidence = evidence
        self.process: subprocess.Popen[bytes] | None = None
        self.base = ""
        self.log_path = evidence / "http" / (app.name + ".server.log")
        self.log_stream = None

    def __enter__(self) -> "PHPServer":
        php = required_path("MP_INSTALL_PHP_BINARY")
        extension_dir = required_path("MP_INSTALL_PHP_EXTENSION_DIR")
        modules = [
            required_path("MP_INSTALL_PHP_PGSQL"),
            required_path("MP_INSTALL_PHP_MBSTRING"),
            required_path("MP_INSTALL_PHP_ZIP"),
        ]
        self.log_path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
        sock.close()
        self.base = f"http://127.0.0.1:{port}"
        app_mount = Path(required_text("MP_INSTALL_APP_MOUNT"))
        source = required_path("MP_INSTALL_SOURCE_ROOT")
        core = required_path("MP_INSTALL_CORE_ROOT")
        open_basedir = os.pathsep.join((str(self.root), str(source), str(core), str(app_mount)))
        command = [str(php), "-n", "-d", f"extension_dir={extension_dir}"]
        for module in modules:
            command.extend(["-d", f"extension={module}"])
        command.extend(
            [
                "-d", "display_errors=0",
                "-d", f"error_log={self.evidence / 'php-server-error.log'}",
                "-d", f"open_basedir={open_basedir}",
                "-S", f"127.0.0.1:{port}",
                "-t", str(self.app),
            ]
        )
        child_env = os.environ.copy()
        child_env["HOME"] = str(self.root / "home")
        child_env["TMPDIR"] = "/tmp"
        pg_bin = Path(required_path("MP_INSTALL_PGDUMP_BINARY")).parent
        child_env["PATH"] = str(pg_bin) + os.pathsep + child_env.get("PATH", "")
        self.log_stream = self.log_path.open("wb")
        try:
            self.process = subprocess.Popen(
                command, cwd=self.app, env=child_env, stdin=subprocess.DEVNULL,
                stdout=self.log_stream, stderr=subprocess.STDOUT,
            )
            ready_deadline = time.monotonic() + 12
            while time.monotonic() < ready_deadline:
                if self.process.poll() is not None:
                    raise CampaignFailure("http-server-exit")
                try:
                    status, _, body = api_request(self.base, "GET", "/ui/api/plugin_packages.php?action=packages")
                    payload = json.loads(body.decode("utf-8"))
                    if status == 200 and isinstance(payload, dict) and payload.get("ok") is True:
                        return self
                except (CampaignFailure, UnicodeError, json.JSONDecodeError):
                    pass
                time.sleep(0.1)
            raise CampaignFailure("http-server-timeout")
        except BaseException:
            self.__exit__(None, None, None)
            raise

    def __exit__(self, exc_type: Any, exc: Any, tb: Any) -> None:
        if self.process is not None and self.process.poll() is None:
            self.process.terminate()
            try:
                self.process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                self.process.kill()
                self.process.wait(timeout=5)
        if self.log_stream is not None:
            self.log_stream.close()


def api_json(server: PHPServer, method: str, path: str, payload: Any = None, expected_status: tuple[int, ...] = (200,)) -> dict[str, Any]:
    body = None if payload is None else json.dumps(payload, separators=(",", ":")).encode("utf-8")
    content_type = "application/json" if body is not None else None
    status, _, raw = api_request(server.base, method, path, body, content_type)
    require(status in expected_status, "package-api-status")
    try:
        value = json.loads(raw.decode("utf-8"))
    except (UnicodeError, json.JSONDecodeError):
        raise CampaignFailure("package-api-json") from None
    require(isinstance(value, dict), "package-api-shape")
    return value


def install_package(server: PHPServer, package: Path, manifest: dict[str, Any]) -> dict[str, Any]:
    probe = api_json(
        server, "POST", "/ui/api/plugin_packages.php?action=probe",
        {"name": manifest["name"], "version": manifest["version"]},
    )
    require(probe.get("ok") is True and isinstance(probe.get("package"), dict), "package-probe")
    require(probe["package"].get("upload_required") is True, "package-upload-not-required")
    data = package.read_bytes()
    chunk_count = (len(data) + CHUNK_BYTES - 1) // CHUNK_BYTES
    started = api_json(
        server, "POST", "/ui/api/plugin_packages.php?action=start-upload",
        {
            "name": manifest["name"],
            "version": manifest["version"],
            "archive_name": package.name,
            "size": len(data),
            "total_chunks": chunk_count,
        },
        expected_status=(201,),
    )
    upload = started.get("upload")
    require(isinstance(upload, dict) and isinstance(upload.get("upload_id"), str), "upload-start")
    upload_id = upload["upload_id"]
    final: dict[str, Any] | None = None
    for index in range(chunk_count):
        chunk = data[index * CHUNK_BYTES : (index + 1) * CHUNK_BYTES]
        path = f"/ui/api/plugin_packages.php?action=upload-chunk&upload_id={upload_id}&index={index}"
        status, _, raw = api_request(server.base, "POST", path, chunk, "application/octet-stream")
        require(status in (200, 201), "upload-chunk-status")
        try:
            value = json.loads(raw.decode("utf-8"))
        except (UnicodeError, json.JSONDecodeError):
            raise CampaignFailure("upload-chunk-json") from None
        require(isinstance(value, dict) and value.get("ok") is True and isinstance(value.get("upload"), dict), "upload-chunk-shape")
        final = value["upload"]

    require(isinstance(final, dict), "upload-final-missing")
    job = final.get("job")
    if not isinstance(job, dict):
        job_id = final.get("job_id", final.get("id"))
        require(isinstance(job_id, str) and re.fullmatch(r"[a-f0-9]{32}", job_id), "upload-job-id")
        deadline = time.monotonic() + 15
        while time.monotonic() < deadline:
            status_payload = api_json(
                server, "GET",
                f"/ui/api/plugin_packages.php?action=status&job_id={job_id}",
            )
            candidate = status_payload.get("job")
            require(isinstance(candidate, dict), "upload-job-status-shape")
            if candidate.get("status") in ("completed", "failed"):
                job = candidate
                break
            time.sleep(0.1)
    require(isinstance(job, dict) and job.get("status") == "completed", "package-job-not-completed")
    require(str(job.get("version", manifest["version"])) == manifest["version"], "package-job-version")
    return job


def verify_installed(app: Path, server: PHPServer, manifest: dict[str, Any], package_files: dict[str, str]) -> dict[str, Any]:
    files = file_hash_map(app / "ext" / "mind_poisoning")
    require(files == package_files, "active-file-hashes")
    fixture_manifest = dict(manifest)
    fixture_manifest["_fixture_archive_sha256"] = EXPECTED_ARCHIVES[str(manifest["version"])]
    registry_path, record = find_package_record(app, fixture_manifest, package_files)
    api = api_json(server, "GET", "/ui/api/plugin_packages.php?action=packages")
    packages = api.get("packages")
    require(api.get("ok") is True and isinstance(packages, list), "package-list")
    found = []
    for item in packages:
        if not isinstance(item, dict):
            continue
        listed_manifest = item.get("manifest") if isinstance(item.get("manifest"), dict) else {}
        name = item.get("name", listed_manifest.get("name", ""))
        if str(name).strip().lower() == str(manifest["name"]).strip().lower():
            found.append(item)
    require(len(found) == 1, "package-list-record")
    listed_manifest = found[0].get("manifest") if isinstance(found[0].get("manifest"), dict) else {}
    listed_version = found[0].get("version", listed_manifest.get("version"))
    require(listed_version == manifest["version"], "package-list-version")
    return {
        "version": manifest["version"],
        "archive_sha256": EXPECTED_ARCHIVES[str(manifest["version"])],
        "active_files": len(files),
        "registry_file": registry_path.name,
        "registry_version": record["manifest"]["version"],
    }


def install_and_verify(app: Path, root: Path, evidence: Path, package: Path, manifest: dict[str, Any], files: dict[str, str]) -> dict[str, Any]:
    with PHPServer(app, root, evidence) as server:
        job = install_package(server, package, manifest)
        verification = verify_installed(app, server, manifest, files)
    return {"job_status": job["status"], **verification}

def run_php_child(root: Path, evidence: Path, app: Path, phase: str, arg: str | None = None) -> dict[str, Any]:
    php = required_path("MP_INSTALL_PHP_BINARY")
    extension_dir = required_path("MP_INSTALL_PHP_EXTENSION_DIR")
    modules = [
        required_path("MP_INSTALL_PHP_PGSQL"),
        required_path("MP_INSTALL_PHP_MBSTRING"),
        required_path("MP_INSTALL_PHP_ZIP"),
    ]
    source = required_path("MP_INSTALL_SOURCE_ROOT")
    core = required_path("MP_INSTALL_CORE_ROOT")
    helper = source / "tests" / "install_recovery_integration_test.php"
    require(helper.is_file(), "php-helper-missing")
    log_dir = evidence / "children"
    log_dir.mkdir(mode=0o700, parents=True, exist_ok=True)
    label = phase + (("-" + arg) if arg else "")
    safe_label = re.sub(r"[^a-zA-Z0-9_-]", "_", label)
    stdout_path = log_dir / (safe_label + ".stdout.txt")
    stderr_path = log_dir / (safe_label + ".stderr.txt")
    command = [str(php), "-n", "-d", f"extension_dir={extension_dir}"]
    for module in modules:
        command.extend(["-d", f"extension={module}"])
    app_mount = Path(required_text("MP_INSTALL_APP_MOUNT"))
    open_basedir = os.pathsep.join((str(root), str(source), str(core), str(app_mount)))
    command.extend(
        [
            "-d", "display_errors=0",
            "-d", f"error_log={evidence / 'php-cli-error.log'}",
            "-d", f"open_basedir={open_basedir}",
            str(helper), phase,
        ]
    )
    if arg is not None:
        command.append(arg)
    child_env = os.environ.copy()
    child_env["MP_INSTALL_APP_ROOT"] = str(app)
    child_env["HOME"] = str(root / "home")
    child_env["TMPDIR"] = "/tmp"
    pg_bin = Path(required_path("MP_INSTALL_PGDUMP_BINARY")).parent
    child_env["PATH"] = str(pg_bin) + os.pathsep + child_env.get("PATH", "")
    try:
        completed = subprocess.run(
            command, cwd=app, env=child_env, stdin=subprocess.DEVNULL,
            stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=45, check=False,
        )
    except (OSError, subprocess.TimeoutExpired):
        stdout_path.write_bytes(b"")
        stderr_path.write_bytes(b"")
        SUBPROCESSES.append({"phase": label, "exit_code": 124, "result": None})
        raise CampaignFailure("php-child-launch") from None
    stdout_path.write_bytes(completed.stdout)
    stderr_path.write_bytes(completed.stderr)
    lines = completed.stdout.decode("utf-8", errors="replace").strip().splitlines()
    markers = [line[len(CHILD_PREFIX):] for line in lines if line.startswith(CHILD_PREFIX)]
    parsed = None
    if len(markers) == 1 and len(lines) == 1:
        try:
            parsed = json.loads(markers[0])
        except json.JSONDecodeError:
            parsed = None
    child_result = parsed if isinstance(parsed, dict) else None
    SUBPROCESSES.append(
        {"phase": label, "exit_code": int(completed.returncode), "result": child_result}
    )
    require(
        completed.returncode == 0 and child_result is not None
        and child_result.get("status") == "passed" and child_result.get("code") == "ok",
        "php-child-result",
    )
    return child_result


def run_command(
    command: list[str],
    *,
    root: Path,
    evidence: Path,
    label: str,
    input_text: str | None = None,
    timeout: int = 45,
    env_overrides: dict[str, str] | None = None,
) -> subprocess.CompletedProcess[str]:
    log_dir = evidence / "commands"
    log_dir.mkdir(mode=0o700, parents=True, exist_ok=True)
    safe_label = re.sub(r"[^a-zA-Z0-9_-]", "_", label)
    child_env = os.environ.copy()
    for key in list(child_env):
        if key.startswith("PG"):
            child_env.pop(key, None)
    child_env["PGPASSWORD"] = os.environ.get("MP_INSTALL_DBPASSWORD", "dwemer")
    child_env["PGSSLMODE"] = "disable"
    child_env["HOME"] = str(root / "home")
    child_env["TMPDIR"] = "/tmp"
    if env_overrides:
        child_env.update(env_overrides)
    try:
        completed = subprocess.run(
            command, input=input_text, text=True, encoding="utf-8", errors="replace",
            env=child_env, stdin=subprocess.DEVNULL if input_text is None else None,
            stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=timeout, check=False,
        )
    except (OSError, subprocess.TimeoutExpired):
        (log_dir / f"{safe_label}.stdout.txt").write_text("", encoding="utf-8")
        (log_dir / f"{safe_label}.stderr.txt").write_text("", encoding="utf-8")
        raise CampaignFailure("command-launch") from None
    (log_dir / f"{safe_label}.stdout.txt").write_text(completed.stdout, encoding="utf-8")
    (log_dir / f"{safe_label}.stderr.txt").write_text(completed.stderr, encoding="utf-8")
    return completed


def psql_command(dbname: str, *extra: str) -> list[str]:
    binary = required_path("MP_INSTALL_PSQL_BINARY")
    return [
        str(binary), "-X", "-w",
        "-h", required_text("MP_INSTALL_DB_HOST"),
        "-p", required_text("MP_INSTALL_DB_PORT"),
        "-U", required_text("MP_INSTALL_DBUSER"),
        "-d", dbname, "-v", "ON_ERROR_STOP=1", *extra,
    ]


def query(dbname: str, sql: str, root: Path, evidence: Path, label: str) -> str:
    completed = run_command(
        psql_command(dbname, "-A", "-t", "-q"), root=root, evidence=evidence,
        label=label, input_text=sql,
    )
    require(completed.returncode == 0, "sql-query-failed")
    return completed.stdout.strip()


def exec_sql(dbname: str, sql: str, root: Path, evidence: Path, label: str) -> None:
    completed = run_command(
        psql_command(dbname, "-q"), root=root, evidence=evidence,
        label=label, input_text=sql,
    )
    require(completed.returncode == 0, "sql-write-failed")


def verify_db_identity(dbname: str, root: Path, evidence: Path, label: str) -> None:
    require(required_text("MP_INSTALL_DB_HOST") == "127.0.0.1", "database-host-boundary")
    require(required_text("MP_INSTALL_DB_PORT") == "5432", "database-port-boundary")
    require(required_text("MP_INSTALL_DBNAME") == "dwemer", "database-name-boundary")
    require(required_text("MP_INSTALL_DBUSER") == "dwemer", "database-user-boundary")
    expected_data = Path(required_text("MP_INSTALL_DATA_DIR")).resolve(strict=True)
    sql = (
        "SELECT current_database() || E'\\n' || current_user || E'\\n' || "
        "current_setting('data_directory') || E'\\n' || coalesce(host(inet_server_addr()), '');"
    )
    output = query(dbname, sql, root, evidence, label).splitlines()
    require(len(output) == 4, "database-identity-shape")
    actual_db, user, data_dir, server_addr = output
    require(actual_db == dbname and user == "dwemer", "database-identity")
    require(Path(data_dir).resolve(strict=True) == expected_data, "database-data-directory")
    require(server_addr == "127.0.0.1", "database-listener-boundary")


def import_schema(root: Path, evidence: Path, core: Path, eventlog_ddl: Path) -> None:
    verify_db_identity("dwemer", root, evidence, "db-identity-initial")
    existing = query(
        "dwemer",
        "SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace "
        "WHERE n.nspname NOT IN ('pg_catalog','information_schema') AND c.relkind IN ('r','p','v','S');",
        root, evidence, "db-pristine-check",
    )
    require(existing == "0", "database-not-pristine")
    schema_dir = core / "lib" / "core" / "database_schema"
    names = [
        "core_api_badge.sql", "core_llm_connector.sql", "core_itt_connector.sql",
        "core_tts_connector.sql", "core_profiles.sql", "core_npc_master.sql",
        "core_npc_master_history.sql", "core_player.sql", "general_settings.sql",
    ]
    for index, name in enumerate(names):
        path = schema_dir / name
        require(path.is_file(), "schema-input-missing")
        completed = run_command(
            psql_command("dwemer", "-q", "-f", str(path)),
            root=root, evidence=evidence, label=f"schema-{index}", timeout=60,
        )
        require(completed.returncode == 0, "schema-import-failed")
    completed = run_command(
        psql_command("dwemer", "-q", "-f", str(eventlog_ddl)),
        root=root, evidence=evidence, label="schema-eventlog", timeout=30,
    )
    require(completed.returncode == 0, "eventlog-import-failed")


def table_columns(dbname: str, table: str, root: Path, evidence: Path, label: str) -> list[str]:
    sql = (
        "SELECT column_name FROM information_schema.columns "
        f"WHERE table_schema='public' AND table_name={sql_literal(table)} ORDER BY ordinal_position;"
    )
    result = [line for line in query(dbname, sql, root, evidence, label).splitlines() if line]
    require(bool(result), "table-columns-missing")
    return result


def seed_database(root: Path, evidence: Path, core: Path, eventlog_ddl: Path) -> dict[str, Any]:
    import_schema(root, evidence, core, eventlog_ddl)
    relationships = {
        "Aela": {"aff": -50, "type": "rival", "note": "baseline speaker relation"},
        "Jarl Balgruuf": {"aff": 99, "type": "friend", "note": "baseline target relation"},
        "Other NPC": {"aff": -40, "type": "enemy", "note": "must remain untouched"},
    }
    plugin_data = {
        "mind_poisoning": {
            "playthrough_id": "unprofiled",
            "floor_event_id": 0,
            "events": [{
                "event_id": 90,
                "utterance_id": "utt_b3_seed_prior_01",
                "judgments": [{
                    "subject": "npc:33", "delta": 2,
                    "reason": "Synthetic baseline ledger event.",
                    "evidence": "I trust Jarl Balgruuf.",
                }],
                "type_changes": [],
            }],
        },
        "fixture_unrelated_plugin": {"preserve": "B3_SYNTHETIC_BASELINE"},
    }
    rows = [
        (11, "Aela", {"relationships": {"Lydia": {"aff": 20, "type": "friend"}}}, {}, 10),
        (22, "Lydia", {"unrelated": {"keep": True}, "relationships": relationships}, plugin_data, 20),
        (33, "Jarl Balgruuf", {"relationships": {}}, {}, 30),
    ]
    inserts = []
    for npc_id, name, extended, plugin, gamets in rows:
        inserts.append(
            "INSERT INTO public.core_npc_master "
            "(id,npc_name,extended_data,plugin_extended_data,gamets_last_updated) VALUES "
            f"({npc_id},{sql_literal(name)},{sql_literal(json.dumps(extended, separators=(',', ':')))}::jsonb,"
            f"{sql_literal(json.dumps(plugin, separators=(',', ':')))}::jsonb,{gamets});"
        )
    inserts.extend([
        "INSERT INTO public.core_player(id,value) VALUES ('player_name','Dragonborn');",
        "INSERT INTO public.general_settings(id,value,description) VALUES "
        "('B3_FIXTURE_SETTING','baseline-value','synthetic B3 preservation fixture'),"
        "('RELATIONSHIP_SYSTEM_ENABLED','true','synthetic core setting'),"
        "('RELLLM_CONNECTOR','1','synthetic connector selection');",
        "CREATE SCHEMA IF NOT EXISTS chim_meta;",
        "CREATE TABLE IF NOT EXISTS chim_meta.settings (key TEXT PRIMARY KEY, value TEXT);",
        "INSERT INTO chim_meta.settings(key,value) VALUES "
        "('AUTOMATIC_DATABASE_BACKUPS','true'),"
        "('AUTOMATIC_BACKUP_MAX_COUNT','5'),"
        "('B3_LOCAL_SETTING_SENTINEL','preserve-outside-native-dump');",
        "INSERT INTO public.eventlog "
        "(rowid,type,data,sess,gamets,localts,ts,utterance_id,delivery_state,people,location,party) VALUES "
        "(100,'chat','Aela: I trust Jarl Balgruuf. The Dragonborn is brave. (Talking to Lydia)',"
        "'web',10,10,1000,'utt_b3_native_enabled_01','spoken',"
        "'|Aela|Lydia|Jarl Balgruuf|','Whiterun','');",
    ])
    exec_sql("dwemer", "\n".join(inserts), root, evidence, "db-seed")
    master_columns = table_columns("dwemer", "core_npc_master", root, evidence, "master-columns")
    require({"id", "relationships", "extended_data"}.issubset(set(master_columns)), "master-schema-shape")
    history_columns = [name for name in master_columns if name != "id"]
    require("created" in table_columns("dwemer", "core_npc_master_history", root, evidence, "history-columns"), "history-created-missing")
    target_cols = ["npc_id"] + history_columns + ["created"]
    source_cols = ["id"] + history_columns
    history_sql = (
        f"INSERT INTO public.core_npc_master_history ({','.join(qident(c) for c in target_cols)}) "
        f"SELECT {','.join([qident(c) for c in source_cols] + ['CURRENT_TIMESTAMP'])} "
        "FROM public.core_npc_master WHERE id=22;"
    )
    exec_sql("dwemer", history_sql, root, evidence, "db-seed-history")
    verify_db_identity("dwemer", root, evidence, "db-identity-seeded")
    return database_snapshot("dwemer", root, evidence, "db-baseline")


def database_snapshot(dbname: str, root: Path, evidence: Path, label: str) -> dict[str, Any]:
    local_exists = query(
        dbname, "SELECT to_regclass('chim_meta.settings') IS NOT NULL;",
        root, evidence, label + "-local-settings-exists",
    )
    require(local_exists in ("t", "f"), "database-local-settings-exists-shape")
    local_settings_sql = (
        "COALESCE((SELECT jsonb_agg(to_jsonb(s) ORDER BY s.key) FROM chim_meta.settings s), '[]'::jsonb)"
        if local_exists == "t" else "NULL::jsonb"
    )
    sql = f"""
SELECT jsonb_build_object(
  'api_badges', COALESCE((SELECT jsonb_agg(to_jsonb(t) ORDER BY t.id) FROM public.core_api_badge t), '[]'::jsonb),
  'llm_connectors', COALESCE((SELECT jsonb_agg(to_jsonb(t) ORDER BY t.id) FROM public.core_llm_connector t), '[]'::jsonb),
  'itt_connectors', COALESCE((SELECT jsonb_agg(to_jsonb(t) ORDER BY t.id) FROM public.core_itt_connector t), '[]'::jsonb),
  'tts_connectors', COALESCE((SELECT jsonb_agg(to_jsonb(t) ORDER BY t.id) FROM public.core_tts_connector t), '[]'::jsonb),
  'profiles', COALESCE((SELECT jsonb_agg(to_jsonb(t) ORDER BY t.id) FROM public.core_profiles t), '[]'::jsonb),
  'master', COALESCE((SELECT jsonb_agg(to_jsonb(m) ORDER BY m.id) FROM public.core_npc_master m), '[]'::jsonb),
  'history', COALESCE((SELECT jsonb_agg(to_jsonb(h) ORDER BY h.history_id) FROM public.core_npc_master_history h), '[]'::jsonb),
  'players', COALESCE((SELECT jsonb_agg(to_jsonb(p) ORDER BY p.id) FROM public.core_player p), '[]'::jsonb),
  'eventlog', COALESCE((SELECT jsonb_agg(to_jsonb(e) ORDER BY e.rowid) FROM public.eventlog e), '[]'::jsonb),
  'general_settings', COALESCE((SELECT jsonb_agg(to_jsonb(g) ORDER BY g.id) FROM public.general_settings g), '[]'::jsonb),
  'local_settings', {local_settings_sql},
  'sequences', jsonb_build_object(
    'api_badge_id_seq', (SELECT jsonb_build_object('last_value', last_value, 'is_called', is_called) FROM public.api_badge_id_seq),
    'llm_connector_id_seq', (SELECT jsonb_build_object('last_value', last_value, 'is_called', is_called) FROM public.llm_connector_id_seq),
    'itt_connector_id_seq', (SELECT jsonb_build_object('last_value', last_value, 'is_called', is_called) FROM public.itt_connector_id_seq),
    'tts_connector_id_seq', (SELECT jsonb_build_object('last_value', last_value, 'is_called', is_called) FROM public.tts_connector_id_seq),
    'profiles_id_seq', (SELECT jsonb_build_object('last_value', last_value, 'is_called', is_called) FROM public.profiles_id_seq),
    'npc_master_id_seq', (SELECT jsonb_build_object('last_value', last_value, 'is_called', is_called) FROM public.npc_master_id_seq),
    'core_npc_master_history_history_id_seq', (SELECT jsonb_build_object('last_value', last_value, 'is_called', is_called) FROM public.core_npc_master_history_history_id_seq)
  )
)::text;
"""
    output = query(dbname, sql, root, evidence, label)
    try:
        result = json.loads(output)
    except json.JSONDecodeError:
        raise CampaignFailure("database-snapshot-invalid") from None
    require(isinstance(result, dict), "database-snapshot-shape")
    return result

def seed_app_preservation(app: Path) -> None:
    config = app / "data" / "mind_poisoning.json"
    config.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    config.write_text('{"enabled":true}', encoding="utf-8")
    state = app / "conf" / "chim_interaction" / "state.json"
    state.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    state.write_text('{"enabled":true,"generation":1}', encoding="utf-8")
    conf = app / "conf" / "conf.php"
    conf.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    conf.write_text("<?php return ['B3_UNRELATED_CONFIG_SENTINEL' => 'preserve'];\n", encoding="utf-8")
    (app / "conf" / "b3-unrelated.txt").write_text("B3_SYNTHETIC_UNRELATED_FILE_V1\n", encoding="utf-8")
    packages = app / "data" / "plugin_packages" / "packages"
    packages.mkdir(mode=0o700, parents=True, exist_ok=True)
    sentinel = {
        "name": "fixture_sentinel",
        "version": "9.9.9",
        "archive_sha256": "0" * 64,
        "manifest": {"name": "fixture_sentinel", "version": "9.9.9", "schema_version": 4},
        "server": {"files": {}},
    }
    (packages / "fixture-sentinel.json").write_text(
        json.dumps(sentinel, separators=(",", ":")) + "\n", encoding="utf-8"
    )
    (app / "data" / "plugin_packages" / "b3-unrelated.txt").write_text(
        "B3_SYNTHETIC_PACKAGE_STATE_SENTINEL_V1\n", encoding="utf-8"
    )


def save_files(
    app: Path, root: Path, relatives: tuple[str, ...], label: str
) -> tuple[Path, dict[str, dict[str, str]]]:
    destination = root / "evidence" / "filesystem" / label
    destination.mkdir(mode=0o700, parents=True, exist_ok=False)
    expected: dict[str, dict[str, str]] = {}
    for rel in relatives:
        source = app.joinpath(*PurePosixPath(rel).parts)
        require(contained(source, app) and source.exists() and not source.is_symlink(), "filesystem-source")
        target = destination.joinpath(*PurePosixPath(rel).parts)
        target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        if source.is_dir():
            shutil.copytree(source, target, symlinks=False)
            expected[rel] = file_hash_map(source)
        else:
            shutil.copy2(source, target, follow_symlinks=False)
            expected[rel] = {".file": digest_file(source)}
    return destination, expected


def restore_files(app: Path, snapshot: Path, expected: dict[str, dict[str, str]]) -> None:
    for rel, expected_map in expected.items():
        target = app.joinpath(*PurePosixPath(rel).parts)
        require(contained(target.parent, app), "filesystem-target-boundary")
        source = snapshot.joinpath(*PurePosixPath(rel).parts)
        require(source.exists() and not source.is_symlink(), "filesystem-snapshot-missing")
        if target.is_dir():
            require(not target.is_symlink(), "filesystem-target-symlink")
            shutil.rmtree(target)
        elif target.exists() or target.is_symlink():
            require(not target.is_symlink() and target.is_file(), "filesystem-target-type")
            target.unlink()
        target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        if ".file" in expected_map:
            shutil.copy2(source, target, follow_symlinks=False)
            require(digest_file(target) == expected_map[".file"], "filesystem-file-restore")
        else:
            shutil.copytree(source, target, symlinks=False)
            require(file_hash_map(target) == expected_map, "filesystem-tree-restore")


def verify_restore_files(app: Path, expected: dict[str, dict[str, str]]) -> None:
    for rel, expected_map in expected.items():
        path = app.joinpath(*PurePosixPath(rel).parts)
        if ".file" in expected_map:
            require(path.is_file() and digest_file(path) == expected_map[".file"], "filesystem-file-mismatch")
        else:
            require(file_hash_map(path) == expected_map, "filesystem-tree-mismatch")


def find_old_backup(app: Path, old_files: dict[str, str]) -> str:
    backup_root = app / "data" / "plugin_packages" / "backups"
    require(backup_root.is_dir(), "old-backup-root-missing")
    matches: list[Path] = []
    for candidate in backup_root.glob("*/mind_poisoning"):
        if candidate.is_dir() and not candidate.is_symlink():
            try:
                if file_hash_map(candidate) == old_files:
                    matches.append(candidate)
            except CampaignFailure:
                continue
    require(len(matches) == 1, "old-extension-backup-mismatch")
    return matches[0].relative_to(app).as_posix()


def find_package_record_path(app: Path, package_name: str) -> Path:
    package_dir = app / "data" / "plugin_packages" / "packages"
    matches = []
    for path in package_dir.glob("*.json"):
        value = json_file(path)
        if not isinstance(value, dict):
            continue
        manifest = value.get("manifest") if isinstance(value.get("manifest"), dict) else {}
        name = manifest.get("name", value.get("name", ""))
        if str(name).strip().lower() == package_name.strip().lower():
            matches.append(path)
    require(len(matches) == 1, "package-record-path")
    return matches[0]


def api_request_with_headers(
    base: str, method: str, path: str, headers: dict[str, str]
) -> tuple[int, dict[str, str], bytes]:
    connection = http.client.HTTPConnection("127.0.0.1", int(base.rsplit(":", 1)[1]), timeout=5)
    try:
        connection.request(method, path, headers={"Connection": "close", **headers})
        response = connection.getresponse()
        return response.status, {key.lower(): value for key, value in response.getheaders()}, response.read()
    except (OSError, http.client.HTTPException):
        raise CampaignFailure("http-transport") from None
    finally:
        connection.close()


def http_dashboard_checks(server: PHPServer) -> dict[str, Any]:
    dashboard = "/ext/mind_poisoning/dashboard.php?local=1"
    status, headers, body = api_request(server.base, "GET", dashboard)
    require(status == 200, "dashboard-get-status")
    require("default-src 'none'" in headers.get("content-security-policy", ""), "dashboard-csp")
    require("Mind Poisoning" in body.decode("utf-8", errors="replace"), "dashboard-title")
    post_status, post_headers, _ = api_request(
        server.base, "POST", dashboard, b"", "application/x-www-form-urlencoded"
    )
    require(post_status == 405 and "GET" in post_headers.get("allow", ""), "dashboard-post-guard")
    forward_status, _, _ = api_request_with_headers(
        server.base, "GET", "/ext/mind_poisoning/dashboard.php", {"X-Forwarded-For": "203.0.113.7"}
    )
    require(forward_status == 403, "dashboard-forwarded-guard")
    return {"get_status": status, "csp": True, "post_status": post_status, "forwarded_status": forward_status}


def dashboard_download_check(server: PHPServer, app: Path, app_mount: Path, evidence: Path) -> dict[str, Any]:
    app_log = app / "log" / "chim.log"
    app_log.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    require(app_log.is_file(), "requestlog-not-emitted")
    existing = app_log.read_text(encoding="utf-8", errors="replace")
    require('"plugin":"mind_poisoning"' in existing and '"event":"request_finished"' in existing, "requestlog-positive-control-missing")
    sentinel = '[2026-10-16T00:00:01Z] [info] {"plugin":"unrelated_fixture_sentinel","schema_version":1,"event":"must_not_download"}\n'
    with app_log.open("a", encoding="utf-8") as stream:
        stream.write(sentinel)
    # Keep the absolute CHIM log path in the runner's private bind mount in sync
    # with the actual request log, without touching any host path outside it.
    mount_log = app_mount / "log" / "chim.log"
    if mount_log != app_log:
        mount_log.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        mount_log.write_text(app_log.read_text(encoding="utf-8", errors="replace"), encoding="utf-8")

    status, headers, body = api_request(
        server.base,
        "GET",
        "/ext/mind_poisoning/dashboard.php?local=1&tab=diagnostics&level=warning&outcome=rejected&q=response_json_invalid&download=1",
    )
    response_path = evidence / "http" / "mind-poisoning-diagnostics.jsonl"
    response_path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    response_path.write_bytes(body)
    require(status == 200, "diagnostics-download-status")
    require("attachment" in headers.get("content-disposition", "").lower(), "diagnostics-download-header")
    text = body.decode("utf-8", errors="replace")
    lines = [line for line in text.splitlines() if line.strip()]
    require(lines, "diagnostics-download-empty")
    try:
        records = [json.loads(line) for line in lines]
    except json.JSONDecodeError:
        raise CampaignFailure("diagnostics-download-format") from None
    require(all(isinstance(record, dict) for record in records), "diagnostics-download-shape")
    require(all(record.get("plugin") == "mind_poisoning" for record in records), "diagnostics-filter")
    require(all(record.get("schema_version") == 1 for record in records), "diagnostics-schema")
    require("unrelated_fixture_sentinel" not in text and "must_not_download" not in text, "diagnostics-sentinel-leak")
    require(len(records) == 1 and records[0].get("event") == "request_finished", "diagnostics-query-filter")
    require(records[0].get("reason") == "response_json_invalid", "diagnostics-real-record-missing")
    return {"download_status": status, "filtered_records": len(records), "actual_requestlog_record": True}


def validate_backup(backup_dir: Path, child: dict[str, Any]) -> tuple[Path, str]:
    details = child.get("details")
    require(isinstance(details, dict), "backup-child-details")
    name, size, sha = details.get("filename"), details.get("bytes"), details.get("sha256")
    require(isinstance(name, str) and SAFE_BACKUP_RE.fullmatch(name) is not None, "backup-name")
    require(isinstance(size, int) and size > 0, "backup-size")
    require(isinstance(sha, str) and re.fullmatch(r"[a-f0-9]{64}", sha), "backup-sha")
    path = backup_dir / name
    require(path.is_file() and not path.is_symlink(), "backup-file-missing")
    require(path.stat().st_size == size and digest_file(path) == sha, "backup-file-identity")
    with path.open("rb") as stream:
        header = stream.read(4096).decode("utf-8", errors="replace")
    require("-- PostgreSQL database dump" in header, "backup-not-pg-dump")
    require("pg_dump:" not in header and "No such file" not in header, "backup-stderr-captured")
    text = path.read_text(encoding="utf-8", errors="replace")
    for required in (
        "CREATE TABLE public.core_npc_master",
        "COPY public.core_npc_master ",
        "COPY public.core_npc_master_history ",
        "COPY public.eventlog ",
        "COPY public.general_settings ",
    ):
        require(required in text, "backup-content-incomplete")
    require("COPY chim_meta.settings " not in text and "CREATE TABLE chim_meta.settings" not in text, "backup-local-settings-included")
    return path, sha


def mutate_after_backup(app: Path, root: Path, evidence: Path) -> None:
    verify_db_identity("dwemer", root, evidence, "db-identity-before-mutation")
    mutation = """
UPDATE public.core_npc_master
SET extended_data=jsonb_set(
        jsonb_set(extended_data, ARRAY['relationships','Jarl Balgruuf','aff'], '7'::jsonb, true),
        ARRAY['relationships','Jarl Balgruuf','type'], '"hostile"'::jsonb, true),
    plugin_extended_data=jsonb_set(plugin_extended_data, ARRAY['mind_poisoning','floor_event_id'], '1'::jsonb, true),
    gamets_last_updated=999
WHERE id=22;
UPDATE public.core_npc_master_history
SET extended_data=jsonb_set(extended_data, ARRAY['relationships','Jarl Balgruuf','aff'], '7'::jsonb, true),
    plugin_extended_data=jsonb_set(plugin_extended_data, ARRAY['mind_poisoning','floor_event_id'], '1'::jsonb, true),
    gamets_last_updated=999
WHERE npc_id=22;
UPDATE public.general_settings SET value='post-backup-mutation' WHERE id='B3_FIXTURE_SETTING';
UPDATE chim_meta.settings SET value='post-backup-mutation' WHERE key='B3_LOCAL_SETTING_SENTINEL';
"""
    exec_sql("dwemer", mutation, root, evidence, "db-post-backup-mutation")
    extension_file = app / "ext" / "mind_poisoning" / "dashboard.php"
    require(extension_file.is_file() and not extension_file.is_symlink(), "mutation-extension-file")
    with extension_file.open("ab") as stream:
        stream.write(b"\n/* B3 scratch post-backup mutation */\n")
    (app / "data" / "mind_poisoning.json").write_text('{"enabled":false,"fixture_mutation":true}', encoding="utf-8")
    (app / "conf" / "chim_interaction" / "state.json").write_text('{"enabled":false,"generation":2}', encoding="utf-8")
    (app / "conf" / "conf.php").write_text("<?php return ['B3_UNRELATED_CONFIG_SENTINEL' => 'mutated'];\n", encoding="utf-8")
    (app / "conf" / "b3-unrelated.txt").write_text("B3_MUTATED_UNRELATED_FILE\n", encoding="utf-8")
    record = find_package_record_path(app, "mind_poisoning")
    record.write_text('{"fixture_mutation":true}\n', encoding="utf-8")
    (app / "data" / "plugin_packages" / "b3-unrelated.txt").write_text("B3_MUTATED_PACKAGE_STATE\n", encoding="utf-8")


def assert_post_backup_mutations(before: dict[str, Any], after: dict[str, Any]) -> None:
    old_master = next((row for row in before["master"] if row.get("id") == 22), None)
    new_master = next((row for row in after["master"] if row.get("id") == 22), None)
    require(isinstance(old_master, dict) and isinstance(new_master, dict), "mutation-master-row")
    old_edge = old_master.get("extended_data", {}).get("relationships", {}).get("Jarl Balgruuf", {})
    new_edge = new_master.get("extended_data", {}).get("relationships", {}).get("Jarl Balgruuf", {})
    require(old_edge.get("aff") != new_edge.get("aff") == 7, "mutation-affinity-not-observed")
    require(old_edge.get("type") != new_edge.get("type") == "hostile", "mutation-type-not-observed")
    old_ledger = old_master.get("plugin_extended_data", {}).get("mind_poisoning", {})
    new_ledger = new_master.get("plugin_extended_data", {}).get("mind_poisoning", {})
    require(old_ledger.get("floor_event_id") != new_ledger.get("floor_event_id") == 1, "mutation-ledger-not-observed")
    old_history = next((row for row in before["history"] if row.get("npc_id") == 22), None)
    new_history = next((row for row in after["history"] if row.get("npc_id") == 22), None)
    require(isinstance(old_history, dict) and isinstance(new_history, dict), "mutation-history-row")
    old_history_aff = old_history.get("extended_data", {}).get("relationships", {}).get("Jarl Balgruuf", {}).get("aff")
    new_history_aff = new_history.get("extended_data", {}).get("relationships", {}).get("Jarl Balgruuf", {}).get("aff")
    require(old_history_aff != new_history_aff == 7, "mutation-history-not-observed")
    old_settings = {row.get("id"): row.get("value") for row in before["general_settings"]}
    new_settings = {row.get("id"): row.get("value") for row in after["general_settings"]}
    require(old_settings.get("B3_FIXTURE_SETTING") != new_settings.get("B3_FIXTURE_SETTING") == "post-backup-mutation", "mutation-core-setting-not-observed")
    old_local = {row.get("key"): row.get("value") for row in before["local_settings"]}
    new_local = {row.get("key"): row.get("value") for row in after["local_settings"]}
    require(old_local.get("B3_LOCAL_SETTING_SENTINEL") != new_local.get("B3_LOCAL_SETTING_SENTINEL") == "post-backup-mutation", "mutation-local-setting-not-observed")


def verify_scratch_database_identity(dbname: str, root: Path, evidence: Path, label: str) -> None:
    require(
        dbname == "postgres" or dbname == "dwemer"
        or re.fullmatch(r"mp_restore_[0-9a-f]{16}", dbname) is not None
        or re.fullmatch(r"mp_before_restore_[0-9a-f]{16}", dbname) is not None,
        "restore-database-name-boundary",
    )
    require(required_text("MP_INSTALL_DB_HOST") == "127.0.0.1", "restore-database-host")
    require(required_text("MP_INSTALL_DB_PORT") == "5432", "restore-database-port")
    require(required_text("MP_INSTALL_DBUSER") == "dwemer", "restore-database-user")
    expected_data = Path(required_text("MP_INSTALL_DATA_DIR")).resolve(strict=True)
    output = query(
        dbname,
        "SELECT current_database() || E'\\n' || current_user || E'\\n' || "
        "current_setting('data_directory') || E'\\n' || coalesce(host(inet_server_addr()), '');",
        root, evidence, label,
    ).splitlines()
    require(len(output) == 4, "restore-database-identity-shape")
    actual_db, user, data_dir, address = output
    require(actual_db == dbname and user == "dwemer", "restore-database-identity")
    require(Path(data_dir).resolve(strict=True) == expected_data, "restore-data-directory")
    require(address == "127.0.0.1", "restore-database-listener")


def database_exists(dbname: str, root: Path, evidence: Path, label: str) -> bool:
    verify_scratch_database_identity("postgres", root, evidence, label + "-identity")
    value = query(
        "postgres", f"SELECT EXISTS(SELECT 1 FROM pg_database WHERE datname={sql_literal(dbname)});",
        root, evidence, label,
    )
    require(value in ("t", "f"), "database-exists-shape")
    return value == "t"


def restore_database(
    backup: Path,
    expected: dict[str, Any],
    root: Path,
    evidence: Path,
) -> tuple[str, str]:
    verify_db_identity("dwemer", root, evidence, "db-identity-before-restore")
    suffix_match = SAFE_ROOT_RE.fullmatch(str(root))
    require(suffix_match is not None, "restore-root-identity")
    suffix = suffix_match.group(1)[:16]
    restored_name = "mp_restore_" + suffix
    preserved_name = "mp_before_restore_" + suffix
    require(not database_exists(restored_name, root, evidence, "restore-target-absence"), "restore-target-exists")
    require(not database_exists(preserved_name, root, evidence, "preserved-target-absence"), "preserved-target-exists")
    exec_sql("postgres", f'CREATE DATABASE "{restored_name}" OWNER "dwemer";', root, evidence, "restore-create-db")
    verify_scratch_database_identity(restored_name, root, evidence, "restore-target-identity")
    empty = query(
        restored_name,
        "SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace "
        "WHERE n.nspname NOT IN ('pg_catalog','information_schema') AND c.relkind IN ('r','p','v','S');",
        root, evidence, "restore-empty-database-check",
    )
    require(empty == "0", "restore-target-not-empty")
    require(contained(backup, root) and backup.is_file() and not backup.is_symlink(), "restore-backup-boundary")
    completed = run_command(
        psql_command(restored_name, "-1", "-q", "-f", str(backup)),
        root=root, evidence=evidence, label="restore-strict-transaction", timeout=120,
    )
    require(completed.returncode == 0, "restore-strict-psql-failed")
    verify_scratch_database_identity(restored_name, root, evidence, "restore-target-after-import")
    no_local_settings = query(
        restored_name, "SELECT to_regclass('chim_meta.settings') IS NULL;",
        root, evidence, "restore-local-settings-exclusion",
    )
    require(no_local_settings == "t", "restore-local-settings-not-excluded")
    restored_before_reapply = database_snapshot(restored_name, root, evidence, "restore-before-local-settings")
    expected_without_local = dict(expected)
    expected_without_local["local_settings"] = None
    require(restored_before_reapply == expected_without_local, "restore-native-state-mismatch")
    local_settings = expected.get("local_settings")
    require(isinstance(local_settings, list) and all(isinstance(row, dict) for row in local_settings), "local-settings-snapshot")
    inserts = [
        "CREATE SCHEMA IF NOT EXISTS chim_meta;",
        "CREATE TABLE IF NOT EXISTS chim_meta.settings (key TEXT PRIMARY KEY, value TEXT);",
    ]
    for row in local_settings:
        key, value = row.get("key"), row.get("value")
        require(isinstance(key, str) and isinstance(value, str), "local-settings-row")
        inserts.append(
            "INSERT INTO chim_meta.settings(key,value) VALUES "
            f"({sql_literal(key)},{sql_literal(value)});"
        )
    exec_sql(restored_name, "\n".join(inserts), root, evidence, "restore-reapply-local-settings")
    restored = database_snapshot(restored_name, root, evidence, "restore-after-local-settings")
    require(restored == expected, "restore-complete-state-mismatch")

    # The app and PHP server are closed by the caller before the fixed-DSN swap.
    active = query(
        "postgres",
        "SELECT count(*) FROM pg_stat_activity WHERE datname IN "
        f"({sql_literal('dwemer')},{sql_literal(restored_name)}) AND pid <> pg_backend_pid();",
        root, evidence, "restore-no-live-app-connections",
    )
    require(active == "0", "restore-active-connections")
    verify_db_identity("dwemer", root, evidence, "db-identity-before-swap")
    verify_scratch_database_identity(restored_name, root, evidence, "restore-identity-before-swap")
    exec_sql("postgres", f'ALTER DATABASE "dwemer" RENAME TO "{preserved_name}";', root, evidence, "restore-preserve-old-db")
    exec_sql("postgres", f'ALTER DATABASE "{restored_name}" RENAME TO "dwemer";', root, evidence, "restore-fixed-dsn-swap")
    verify_db_identity("dwemer", root, evidence, "db-identity-after-swap")
    require(database_snapshot("dwemer", root, evidence, "restore-swapped-state") == expected, "restore-swapped-state-mismatch")
    verify_scratch_database_identity(preserved_name, root, evidence, "preserved-db-after-swap")
    return restored_name, preserved_name


def dashboard_reads_restored_state(server: PHPServer) -> dict[str, Any]:
    status, _, body = api_request(
        server.base,
        "GET",
        "/ext/mind_poisoning/dashboard.php?local=1&tab=interactions&q=utt_b3_seed_prior_01",
    )
    require(status == 200, "restored-dashboard-status")
    page = body.decode("utf-8", errors="replace")
    article = re.search(
        r'<article class="interaction-entry">(?:(?!</article>).)*?utt_b3_seed_prior_01(?:(?!</article>).)*?</article>',
        page, re.DOTALL,
    )
    require(article is not None, "restored-dashboard-ledger-row-missing")
    change_rows = re.findall(r'<dl class="change-row">(.*?)</dl>', article.group(0), re.DOTALL)
    subject_row = next((row for row in change_rows if "Jarl Balgruuf" in html.unescape(re.sub(r"<[^>]*>", " ", row))), None)
    require(subject_row is not None, "restored-dashboard-subject-row-missing")
    require(
        re.search(r"<dt>Current</dt>\s*<dd class=\"numeric\">\s*99\s*</dd>", subject_row) is not None,
        "restored-dashboard-affinity-mismatch",
    )
    current_type = re.search(r"<dt>Current type</dt>\s*<dd>(.*?)</dd>", subject_row, re.DOTALL)
    require(current_type is not None, "restored-dashboard-type-missing")
    rendered_type = html.unescape(re.sub(r"<[^>]*>", "", current_type.group(1))).strip()
    require(rendered_type == "friend", "restored-dashboard-type-mismatch")
    require("The database source could not be read; retained log records are shown without active-profile verification." not in page, "restored-dashboard-database-fallback")
    require("Showing log-only history. Current affinity is unavailable because the database source could not be read." not in page, "restored-dashboard-affinity-fallback")
    return {"status": status, "ledger_event": True, "current_affinity": 99, "current_type": "friend"}


def run_scenario(
    results: dict[str, dict[str, Any]], name: str, operation: Any
) -> Any:
    try:
        details = operation()
    except CampaignFailure as error:
        results[name] = {"status": "failed", "code": error.code}
        raise
    except Exception:
        results[name] = {"status": "failed", "code": "fixture-internal-error"}
        raise CampaignFailure("fixture-internal-error") from None
    results[name] = {"status": "passed", "code": "ok", "details": details or {}}
    return details


def save_result(evidence: Path | None, result: dict[str, Any]) -> bool:
    encoded = json.dumps(result, sort_keys=True, separators=(",", ":"), ensure_ascii=True)
    if evidence is not None:
        try:
            evidence.mkdir(mode=0o700, parents=True, exist_ok=True)
            target = evidence / "fixture-result.json"
            temp = evidence / "fixture-result.json.tmp"
            temp.write_text(encoded + "\n", encoding="utf-8")
            os.replace(temp, target)
        except OSError:
            result["status"] = "failed"
            result["failure_code"] = result.get("failure_code") or "result-write-failed"
            encoded = json.dumps(result, sort_keys=True, separators=(",", ":"), ensure_ascii=True)
    print(RESULT_PREFIX + encoded, flush=True)
    return result.get("status") == "passed"


def main() -> int:
    results = {name: {"status": "skipped", "code": "not-run"} for name in SCENARIOS}
    evidence: Path | None = None
    failure_code: str | None = None
    try:
        root = required_path("MP_INSTALL_RECOVERY_ROOT")
        require(SAFE_ROOT_RE.fullmatch(str(root)) is not None, "scratch-root-boundary")
        require(str(root) == os.environ.get("MP_INSTALL_RECOVERY_ROOT"), "scratch-root-canonical")
        evidence = required_path("MP_INSTALL_EVIDENCE_ROOT")
        require(evidence == root / "evidence" and contained(evidence, root), "evidence-root-boundary")
        app_mount = required_path("MP_INSTALL_APP_MOUNT")
        require(str(app_mount) == "/var/www/html/HerikaServer", "app-mount-boundary")
        data_dir = required_path("MP_INSTALL_DATA_DIR")
        socket_dir = required_path("MP_INSTALL_SOCKET_DIR")
        require(contained(data_dir, root) and contained(socket_dir, root), "postgres-path-boundary")
        evidence.mkdir(mode=0o700, parents=True, exist_ok=True)
        source = required_path("MP_INSTALL_SOURCE_ROOT")
        core = required_path("MP_INSTALL_CORE_ROOT")
        core_inventory = required_path("MP_INSTALL_CORE_INPUTS")
        eventlog_ddl = required_path("MP_INSTALL_EVENTLOG_DDL")
        archive_019 = required_path("MP_INSTALL_PACKAGE_019")
        archive_020 = required_path("MP_INSTALL_PACKAGE_020")
        app_root = root / "apps"
        app_root.mkdir(mode=0o700, parents=True, exist_ok=True)
        clean_app = app_root / "clean"
        upgrade_app = app_root / "upgrade"
        core_files = validate_core(core, core_inventory)
        pinned_eventlog = dict(core_files).get("fixture/eventlog-base-ddl.sql")
        require(pinned_eventlog is not None and digest_file(eventlog_ddl) == digest_file(pinned_eventlog), "eventlog-ddl-identity")
        source_files = file_hash_map(source / "server")
        require(len(source_files) == 17, "source-server-file-count")
        manifest_019 = package_manifest(archive_019, "0.1.19")
        manifest_020 = package_manifest(archive_020, "0.1.20")
        files_019 = package_server_files(archive_019)
        files_020 = package_server_files(archive_020)
        require(files_020 == source_files, "release-source-mismatch")
        verify_db_identity("dwemer", root, evidence, "db-identity-campaign-start")

        def clean_install() -> dict[str, Any]:
            copy_core_into_app(core_files, clean_app)
            return install_and_verify(clean_app, root, evidence, archive_020, manifest_020, files_020)

        run_scenario(results, SCENARIOS[0], clean_install)

        expected: dict[str, Any] = {}
        old_backup_rel = ""

        def install_upgrade() -> dict[str, Any]:
            nonlocal expected, old_backup_rel
            copy_core_into_app(core_files, upgrade_app)
            first = install_and_verify(upgrade_app, root, evidence, archive_019, manifest_019, files_019)
            old_files = file_hash_map(upgrade_app / "ext" / "mind_poisoning")
            require(old_files == files_019, "pre-upgrade-active-files")
            seed_app_preservation(upgrade_app)
            expected = seed_database(root, evidence, core, eventlog_ddl)
            upgraded = install_and_verify(upgrade_app, root, evidence, archive_020, manifest_020, files_020)
            old_backup_rel = find_old_backup(upgrade_app, old_files)
            return {
                "from": first["registry_version"],
                "to": upgraded["registry_version"],
                "old_extension_backup": old_backup_rel,
                "old_file_count": len(old_files),
            }

        run_scenario(results, SCENARIOS[1], install_upgrade)

        def preserve_upgrade_state() -> dict[str, Any]:
            after = database_snapshot("dwemer", root, evidence, "db-after-upgrade")
            require(after == expected, "upgrade-database-state-changed")
            require((upgrade_app / "data" / "mind_poisoning.json").read_text(encoding="utf-8") == '{"enabled":true}', "upgrade-plugin-config-changed")
            require((upgrade_app / "conf" / "chim_interaction" / "state.json").read_text(encoding="utf-8") == '{"enabled":true,"generation":1}', "upgrade-pause-config-changed")
            require((upgrade_app / "conf" / "conf.php").read_text(encoding="utf-8") == "<?php return ['B3_UNRELATED_CONFIG_SENTINEL' => 'preserve'];\n", "upgrade-core-config-changed")
            require((upgrade_app / "conf" / "b3-unrelated.txt").read_text(encoding="utf-8") == "B3_SYNTHETIC_UNRELATED_FILE_V1\n", "upgrade-unrelated-file-changed")
            require((upgrade_app / "data" / "plugin_packages" / "b3-unrelated.txt").read_text(encoding="utf-8") == "B3_SYNTHETIC_PACKAGE_STATE_SENTINEL_V1\n", "upgrade-package-state-changed")
            sentinel = json_file(upgrade_app / "data" / "plugin_packages" / "packages" / "fixture-sentinel.json")
            require(isinstance(sentinel, dict) and sentinel.get("version") == "9.9.9", "upgrade-registry-sentinel-changed")
            old_backup = upgrade_app / old_backup_rel
            require(file_hash_map(old_backup) == files_019, "upgrade-old-backup-changed")
            return {"db_tables": len(expected), "old_extension_backup_verified": True, "unrelated_config_and_file_preserved": True}

        run_scenario(results, SCENARIOS[2], preserve_upgrade_state)

        def dashboard_routes() -> dict[str, Any]:
            with PHPServer(upgrade_app, root, evidence) as server:
                return http_dashboard_checks(server)

        run_scenario(results, SCENARIOS[3], dashboard_routes)

        def guard_behavior() -> dict[str, Any]:
            results_by_mode: dict[str, Any] = {}
            for mode in ("interaction-off", "enabled", "paused", "core-off"):
                before = database_snapshot("dwemer", root, evidence, "guard-before-" + mode)
                child = run_php_child(root, evidence, upgrade_app, "guard", mode)
                after = database_snapshot("dwemer", root, evidence, "guard-after-" + mode)
                require(after == before, "guard-database-write-" + mode)
                details = child.get("details")
                require(isinstance(details, dict), "guard-child-details")
                if mode == "enabled":
                    require(details.get("model_calls") == 1 and details.get("reason") == "response_json_invalid", "guard-positive-control")
                else:
                    require(details.get("model_calls") == 0, "guard-ineligible-model-call")
                results_by_mode[mode] = {
                    "status": details.get("status"),
                    "model_calls": details.get("model_calls"),
                    "database_unchanged": True,
                }
            return results_by_mode

        run_scenario(results, SCENARIOS[5], guard_behavior)

        def diagnostics_download() -> dict[str, Any]:
            with PHPServer(upgrade_app, root, evidence) as server:
                return dashboard_download_check(
                    server, upgrade_app, Path(required_text("MP_INSTALL_APP_MOUNT")), evidence,
                )

        run_scenario(results, SCENARIOS[4], diagnostics_download)

        backup_result: dict[str, Any] = {}
        backup_path: Path | None = None
        backup_expected: dict[str, Any] = {}

        def create_backup() -> dict[str, Any]:
            nonlocal backup_result, backup_path, backup_expected
            backup_expected = database_snapshot("dwemer", root, evidence, "db-before-backup")
            child = run_php_child(root, evidence, upgrade_app, "backup")
            backup_path, backup_sha = validate_backup(
                upgrade_app / "ui" / "data" / "databasebackups", child,
            )
            backup_result = {"filename": backup_path.name, "sha256": backup_sha, "bytes": backup_path.stat().st_size}
            return {**backup_result, "dump_format_checked": True}

        run_scenario(results, SCENARIOS[6], create_backup)

        def restore_recovery_state() -> dict[str, Any]:
            require(backup_path is not None, "restore-backup-not-ready")
            before = backup_expected
            require(database_snapshot("dwemer", root, evidence, "db-before-post-backup-mutation") == before, "db-changed-before-backup-mutation")
            _, filesystem_expected = save_files(
                upgrade_app, root,
                (
                    "ext/mind_poisoning",
                    "data/mind_poisoning.json",
                    "data/plugin_packages",
                    "conf/chim_interaction/state.json",
                    "conf/conf.php",
                    "conf/b3-unrelated.txt",
                ),
                "upgrade-baseline",
            )
            mutate_after_backup(upgrade_app, root, evidence)
            mutated = database_snapshot("dwemer", root, evidence, "db-after-post-backup-mutation")
            require(mutated != before, "post-backup-mutation-not-observed")
            assert_post_backup_mutations(before, mutated)
            require((upgrade_app / "conf" / "conf.php").read_text(encoding="utf-8") == "<?php return ['B3_UNRELATED_CONFIG_SENTINEL' => 'mutated'];\n", "mutation-core-config-not-observed")
            restore_names = restore_database(backup_path, before, root, evidence)
            backup_case = results.get(SCENARIOS[6])
            require(
                isinstance(backup_case, dict)
                and backup_case.get("status") == "passed"
                and isinstance(backup_case.get("details"), dict),
                "backup-scenario-result-missing",
            )
            backup_case["details"]["strict_restore_verified"] = True
            restore_files(upgrade_app, root / "evidence" / "filesystem" / "upgrade-baseline", filesystem_expected)
            verify_restore_files(upgrade_app, filesystem_expected)
            require(file_hash_map(upgrade_app / "ext" / "mind_poisoning") == files_020, "restore-active-extension-files")
            require(find_old_backup(upgrade_app, files_019) == old_backup_rel, "restore-old-backup-missing")
            require(database_snapshot("dwemer", root, evidence, "db-after-full-restore") == before, "restore-final-database-state")
            return {
                "backup_sha256": digest_file(backup_path),
                "restored_database": "dwemer",
                "preserved_pre_restore_database": restore_names[1],
                "local_settings_reapplied": True,
                "files_restored": len(filesystem_expected),
                "fixture_state_exact": True,
            }

        run_scenario(results, SCENARIOS[7], restore_recovery_state)

        def restored_dashboard() -> dict[str, Any]:
            with PHPServer(upgrade_app, root, evidence) as server:
                active = verify_installed(upgrade_app, server, manifest_020, files_020)
                page = dashboard_reads_restored_state(server)
                return {"package_version": active["version"], **page}

        run_scenario(results, SCENARIOS[8], restored_dashboard)
    except CampaignFailure as error:
        failure_code = error.code
    except Exception:
        failure_code = "fixture-internal-error"

    if failure_code is not None:
        for name in SCENARIOS:
            if results[name]["status"] == "skipped":
                results[name] = {"status": "skipped", "code": "not-run-after-failure"}
    passed = failure_code is None and all(results[name]["status"] == "passed" for name in SCENARIOS)
    result = {
        "suite": SUITE,
        "status": "passed" if passed else "failed",
        "failure_code": None if passed else (failure_code or "scenario-incomplete"),
        "scenarios": results,
        "subprocesses": SUBPROCESSES,
    }
    report_ok = save_result(evidence, result)
    return 0 if passed and report_ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
