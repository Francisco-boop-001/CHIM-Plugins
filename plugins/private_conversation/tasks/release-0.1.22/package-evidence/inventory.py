from __future__ import annotations
import configparser
import hashlib
import json
import re
import stat
import sys
import tarfile
import zipfile
from pathlib import Path, PurePosixPath

root = Path(r"K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev")
evidence = root / "tasks" / "release-0.1.22" / "package-evidence"
release = root / "dist" / "release-v0.1.22-candidate"
report = {
    "release_version": "0.1.22",
    "candidate_directory": "dist/release-v0.1.22-candidate",
    "checks": {},
    "assets": [],
    "errors": [],
}
def record(name: str, passed: bool, detail: str):
    report["checks"][name] = {"passed": bool(passed), "detail": detail}
    if not passed:
        report["errors"].append(name)
def safe_posix(name: str) -> bool:
    p = PurePosixPath(name)
    return (bool(name) and not name.startswith("/") and "\\" not in name
            and not p.is_absolute() and all(part not in ("", ".", "..") for part in p.parts))
def file_hash(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()

try:
    before = json.loads((evidence / "source-hashes-before.json").read_text(encoding="utf-8"))
    after = json.loads((evidence / "source-hashes-after.json").read_text(encoding="utf-8"))
    source_names = [item["path"].removeprefix("server/") for item in before]
    before_map = {item["path"]: item["sha256"] for item in before}
    after_map = {item["path"]: item["sha256"] for item in after}
    current_map = {
        f"server/{name}": file_hash(root / "server" / Path(name))
        for name in source_names
    }
    record("21_source_hashes_stable", len(source_names) == 21 and len(before_map) == 21
           and before_map == after_map == current_map, "Before, after, and current SHA-256 maps match for all allowlisted server files.")
    report["source_hash_records"] = {"before": "source-hashes-before.json", "after": "source-hashes-after.json", "count": len(source_names)}

    manifest = json.loads((root / "server" / "manifest.json").read_text(encoding="utf-8"))
    previous_package = root / "dist" / "release-v0.1.21" / "private_conversation-0.1.21.dwpkg"
    with zipfile.ZipFile(previous_package) as previous_zip:
        previous_inner = json.loads(previous_zip.read("server/manifest.json"))
    stable_keys = ["schema_version", "status", "default_channel", "channels", "server_compatibility_reference"]
    record("manifest_release_fields", manifest.get("name") == "private_conversation"
           and manifest.get("version") == "0.1.22"
           and manifest.get("schema_version") == 2
           and manifest.get("status") == "development_candidate"
           and manifest.get("default_channel") == "candidate"
           and manifest.get("server_compatibility_reference") == "cf5030f15781637498be86debe26fcf102f5690d"
           and manifest.get("channels", {}).get("candidate", {}).get("label") == "Development candidate"
           and manifest.get("channels", {}).get("candidate", {}).get("branch") == "main"
           and manifest.get("channels", {}).get("candidate", {}).get("package_source") == "release"
           and manifest.get("channels", {}).get("candidate", {}).get("allow_force") is False,
           "Current manifest has version 0.1.22 and the expected schema/channel/status/compatibility values.")
    record("manifest_stable_fields_match_0_1_21", all(manifest.get(k) == previous_inner.get(k) for k in stable_keys),
           "Schema, status, channel metadata, and compatibility reference equal the prior packaged 0.1.21 manifest.")

    expected_assets = {
        "private_conversation-0.1.22.dwpkg",
        "private_conversation.tar.gz",
        "private_conversation-0.1.22-mo2.zip",
        "chim-plugin.tar.gz",
        "SHA256SUMS.txt",
    }
    children = list(release.iterdir())
    actual_assets = {p.name for p in children if p.is_file()}
    no_extra_dirs = all(p.is_file() and not p.is_symlink() for p in children)
    record("exactly_five_assets", actual_assets == expected_assets and len(children) == 5 and no_extra_dirs,
           "Candidate directory contains exactly the five expected regular-file assets and no extra entries.")
    for p in sorted(children, key=lambda x: x.name):
        if p.is_file():
            report["assets"].append({"name": p.name, "bytes": p.stat().st_size, "sha256": file_hash(p)})

    source_payload = {name: (root / "server" / Path(name)).read_bytes() for name in source_names}
    dwpkg_path = release / "private_conversation-0.1.22.dwpkg"
    with zipfile.ZipFile(dwpkg_path) as zf:
        dwpkg_names = zf.namelist()
        expected_dwpkg_names = sorted(["manifest.json", "checksums.sha256", *[f"server/{n}" for n in source_names]])
        dwpkg_data = {name: zf.read(name) for name in dwpkg_names}
        crc_ok = zf.testzip() is None
        zip_names_safe = all(safe_posix(name) for name in dwpkg_names)
        modes_safe = all(not stat.S_ISLNK(info.external_attr >> 16) for info in zf.infolist())
    record("dwpkg_member_inventory", dwpkg_names == expected_dwpkg_names and len(dwpkg_names) == 23
           and crc_ok and zip_names_safe and modes_safe,
           "DWPkg has the exact 23-member allowlist, no unsafe paths or symlinks, and a clean ZIP CRC.")
    outer = json.loads(dwpkg_data["manifest.json"])
    inner = json.loads(dwpkg_data["server/manifest.json"])
    record("dwpkg_versions_and_scope", outer.get("schema_version") == 4
           and outer.get("name") == "private_conversation" and outer.get("version") == "0.1.22"
           and outer.get("server", {}).get("mutable_paths") == ["state"]
           and inner.get("version") == "0.1.22" and inner == manifest,
           "DWPkg outer schema/name/version/mutable path and inner server manifest agree with source.")
    checksum_lines = dwpkg_data["checksums.sha256"].decode("ascii").splitlines()
    checksum_map = {}
    checksum_syntax = True
    for line in checksum_lines:
        match = re.fullmatch(r"([0-9a-f]{64})  (.+)", line)
        if not match or match.group(2) in checksum_map:
            checksum_syntax = False
            continue
        checksum_map[match.group(2)] = match.group(1)
    checksum_expected = set(dwpkg_names) - {"checksums.sha256"}
    checksum_values_ok = checksum_syntax and set(checksum_map) == checksum_expected and all(
        checksum_map[name] == hashlib.sha256(dwpkg_data[name]).hexdigest() for name in checksum_expected
    )
    record("dwpkg_internal_checksums", len(checksum_lines) == 22 and checksum_values_ok,
           "Internal checksums cover exactly manifest.json plus all 21 server files and verify.")
    source_match = all(dwpkg_data[f"server/{name}"] == source_payload[name] for name in source_names)
    record("dwpkg_payload_source_equality", source_match, "Every embedded server file matches current allowlisted source bytes.")

    wrapped_path = release / "private_conversation.tar.gz"
    wrapped_payload = {}
    with tarfile.open(wrapped_path, "r:gz") as tf:
        members = tf.getmembers()
        wrapped_names = [m.name for m in members]
        expected_wrapped_names = ["private_conversation", *[f"private_conversation/{n}" for n in source_names]]
        wrapped_safe = all(safe_posix(m.name) for m in members)
        wrapped_types = len(members) == 22 and members[0].isdir() and all(m.isfile() for m in members[1:])
        for m in members[1:]:
            stream = tf.extractfile(m)
            wrapped_payload[m.name.removeprefix("private_conversation/")] = stream.read() if stream else b""
    record("wrapped_tar_layout_safety", wrapped_names == expected_wrapped_names and wrapped_safe and wrapped_types,
           "Wrapped repository TAR has one package root plus 21 regular files, exact order, and safe member paths.")

    flat_path = release / "chim-plugin.tar.gz"
    flat_payload = {}
    with tarfile.open(flat_path, "r:gz") as tf:
        members = tf.getmembers()
        flat_names = [m.name for m in members]
        flat_safe = all(safe_posix(m.name) for m in members)
        flat_types = len(members) == 21 and all(m.isfile() for m in members)
        for m in members:
            stream = tf.extractfile(m)
            flat_payload[m.name] = stream.read() if stream else b""
    record("flat_tar_layout_safety", flat_names == source_names and flat_safe and flat_types,
           "Flat discovery TAR has exactly the 21 allowlisted regular files and safe member paths.")
    record("wrapped_flat_tar_payload_equality", wrapped_payload == flat_payload == source_payload,
           "Wrapped TAR payload, flat TAR payload, and current server source are byte-identical.")

    mo2_path = release / "private_conversation-0.1.22-mo2.zip"
    with zipfile.ZipFile(mo2_path) as zf:
        mo2_names = zf.namelist()
        mo2_crc_ok = zf.testzip() is None
        mo2_member = "CHIM/server-plugins/private_conversation/0.1.22.dwpkg"
        mo2_package = zf.read(mo2_member)
        meta_bytes = zf.read("meta.ini")
    expected_meta = b"[General]\nversion=0.1.22\nvalidated=true\n"
    config = configparser.ConfigParser()
    config.read_string(meta_bytes.decode("utf-8"))
    record("mo2_wrapper_identity", mo2_names == ["meta.ini", mo2_member] and mo2_crc_ok
           and mo2_package == dwpkg_path.read_bytes()
           and meta_bytes == expected_meta and config.get("General", "version") == "0.1.22"
           and config.getboolean("General", "validated"),
           "MO2 ZIP has exact metadata/path, validated=true, and an inner DWPkg byte-identical to the standalone asset.")

    sums_path = release / "SHA256SUMS.txt"
    sums_bytes = sums_path.read_bytes()
    sums_lines = sums_bytes.decode("ascii").splitlines()
    sums_map = {}
    sums_syntax = True
    for line in sums_lines:
        match = re.fullmatch(r"([0-9a-f]{64})  (.+)", line)
        if not match or match.group(2) in sums_map:
            sums_syntax = False
            continue
        sums_map[match.group(2)] = match.group(1)
    expected_sum_names = expected_assets - {"SHA256SUMS.txt"}
    sums_ok = sums_syntax and set(sums_map) == expected_sum_names and all(
        sums_map[name] == file_hash(release / name) for name in expected_sum_names
    )
    record("sha256sums_four_assets", len(sums_lines) == 4 and sums_ok,
           "SHA256SUMS has exactly four unique non-self entries, each matching its asset digest.")

    text_sources = {name: data for name, data in source_payload.items()
                    if name.endswith((".php", ".js", ".css", ".json")) or name == ".htaccess"}
    text_sources["MO2/meta.ini"] = meta_bytes
    text_sources["SHA256SUMS.txt"] = sums_bytes
    text_sources["DWPkg/manifest.json"] = dwpkg_data["manifest.json"]
    text_sources["DWPkg/checksums.sha256"] = dwpkg_data["checksums.sha256"]
    cr_files = [name for name, data in text_sources.items() if b"\r" in data]
    record("lf_only_text", not cr_files, "Text payloads and package metadata contain no CR bytes." if not cr_files else f"CR bytes found in: {cr_files}")

    report["run_receipts"] = {
        "package_check": json.loads((evidence / "package-check.run.json").read_text(encoding="utf-8")),
        "package_build": json.loads((evidence / "package-build.run.json").read_text(encoding="utf-8")),
    }
except Exception as exc:
    report["errors"].append("inventory_exception")
    report["exception"] = f"{type(exc).__name__}: {exc}"

report["status"] = "PASS" if not report["errors"] and all(v["passed"] for v in report["checks"].values()) else "FAIL"
report["check_count"] = len(report["checks"])
(evidence / "package-inventory.json").write_text(json.dumps(report, indent=2, sort_keys=True) + "\n", encoding="utf-8")
print(json.dumps({"status": report["status"], "check_count": report["check_count"], "errors": report["errors"], "assets": report["assets"]}, sort_keys=True))
sys.exit(0 if report["status"] == "PASS" else 1)