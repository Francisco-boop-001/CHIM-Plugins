from __future__ import annotations

import ast
import datetime as dt
import gzip
import hashlib
import io
import json
import pathlib
import re
import stat
import subprocess
import sys
import tarfile
import zipfile


ROOT = pathlib.Path(r"K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev")
CANDIDATE = ROOT / "tasks/release-0.1.20/package-evidence/candidate"
SOURCE = ROOT / "server"
EVIDENCE = pathlib.Path(__file__).resolve().parent
LEGACY_TAG = "c7ec6cde8231ebb103ef7d443b09789eb4b29691"
EXPECTED_ASSETS = {
    "chim-plugin.tar.gz",
    "private_conversation.tar.gz",
    "private_conversation-0.1.20.dwpkg",
    "private_conversation-0.1.20-mo2.zip",
}
ZIP_TIME = (1980, 1, 1, 0, 0, 0)
TAR_TIME = 315532800
checks: dict[str, bool] = {}
details: dict[str, object] = {}


def record(name: str, passed: bool, detail: object | None = None) -> None:
    checks[name] = bool(passed)
    if detail is not None:
        details[name] = detail


def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def zip_metadata_ok(info: zipfile.ZipInfo, compression: int) -> bool:
    return (
        info.date_time == ZIP_TIME
        and info.compress_type == compression
        and info.create_system == 3
        and info.external_attr == ((stat.S_IFREG | 0o644) << 16)
        and info.extra == b""
        and info.comment == b""
    )


def inspect_tar(filename: str, flat: bool, files: list[str], source_bytes: dict[str, bytes]) -> dict:
    raw = asset_bytes[filename]
    label = "flat_tar" if flat else "wrapped_tar"
    record(f"{label}_gzip_header_epoch", raw[:8] == b"\x1f\x8b\x08\x00\x00\x00\x00\x00")
    unpacked = gzip.decompress(raw)  # also checks the gzip trailer CRC and size
    archive = tarfile.open(fileobj=io.BytesIO(unpacked), mode="r:")
    members = archive.getmembers()
    expected_names = files if flat else ["private_conversation"] + [f"private_conversation/{name}" for name in files]
    record(f"{label}_exact_members", [member.name for member in members] == expected_names,
           {"count": len(members), "names": [member.name for member in members]})

    payload = members if flat else members[1:]
    metadata_ok = True
    source_ok = len(payload) == len(files)
    for member, source_name in zip(payload, files):
        metadata_ok = metadata_ok and (
            member.type == tarfile.REGTYPE
            and member.mode == 0o644
            and member.mtime == TAR_TIME
            and member.uid == 0
            and member.gid == 0
            and member.uname == ""
            and member.gname == ""
            and member.pax_headers == {}
            and member.size == len(source_bytes[source_name])
        )
        stream = archive.extractfile(member)
        source_ok = source_ok and stream is not None and stream.read() == source_bytes[source_name]
    if not flat:
        root_member = members[0] if members else None
        metadata_ok = metadata_ok and root_member is not None and (
            root_member.name == "private_conversation"
            and root_member.type == tarfile.DIRTYPE
            and root_member.size == 0
            and root_member.mode == 0o755
            and root_member.mtime == TAR_TIME
            and root_member.uid == 0
            and root_member.gid == 0
            and root_member.uname == ""
            and root_member.gname == ""
        )
    record(f"{label}_deterministic_tar_metadata", metadata_ok)
    record(f"{label}_payload_bytes_match_current_source", source_ok)
    if members:
        last = members[-1]
        end = last.offset_data + ((last.size + 511) // 512) * 512
        trailing = unpacked[end:]
        trailer_ok = len(trailing) >= 1024 and not any(trailing)
    else:
        trailer_ok = False
    record(f"{label}_zero_tar_terminator", trailer_ok)
    archive.close()
    return {"member_count": len(members), "gzip_bytes": len(raw), "sha256": sha(raw)}


try:
    tree = ast.parse((ROOT / "scripts/build-package.py").read_text(encoding="utf-8"))
    allowlist = None
    package_schema = None
    for node in tree.body:
        if isinstance(node, ast.Assign):
            targets = [target.id for target in node.targets if isinstance(target, ast.Name)]
            if "SERVER_FILES" in targets:
                allowlist = list(ast.literal_eval(node.value))
            elif "PACKAGE_SCHEMA" in targets:
                package_schema = ast.literal_eval(node.value)
    record("builder_allowlist_has_21_unique_files", allowlist is not None and len(allowlist) == 21 and len(set(allowlist)) == 21,
           allowlist)
    record("builder_dwpkg_schema_is_4", package_schema == 4, package_schema)
    if allowlist is None:
        raise RuntimeError("Could not read SERVER_FILES from the package builder.")

    candidate_files = {path.name: path for path in CANDIDATE.iterdir() if path.is_file()}
    record("candidate_has_exact_five_files", set(candidate_files) == EXPECTED_ASSETS | {"SHA256SUMS.txt"}, sorted(candidate_files))
    asset_bytes = {name: candidate_files[name].read_bytes() for name in EXPECTED_ASSETS if name in candidate_files}
    asset_hashes = {name: sha(data) for name, data in sorted(asset_bytes.items())}
    asset_sizes = {name: len(data) for name, data in sorted(asset_bytes.items())}
    sums = candidate_files.get("SHA256SUMS.txt")
    if sums is not None:
        sums_bytes = sums.read_bytes()
        expected_sums = "".join(f"{asset_hashes[name]}  {name}\n" for name in sorted(EXPECTED_ASSETS) if name in asset_hashes).encode("ascii")
        record("top_checksums_exactly_cover_four_assets", set(asset_hashes) == EXPECTED_ASSETS and sums_bytes == expected_sums,
               {"rows": sums_bytes.decode("ascii", errors="replace").splitlines(), "contains_cr": b"\r" in sums_bytes})
    else:
        record("top_checksums_exactly_cover_four_assets", False, "SHA256SUMS.txt missing")

    source_bytes = {name: (SOURCE / name).read_bytes() for name in allowlist}
    source_hashes = {name: sha(data) for name, data in sorted(source_bytes.items())}
    text_names = [name for name in allowlist if name == ".htaccess" or pathlib.PurePosixPath(name).suffix.lower() in {".php", ".css", ".js", ".json"}]
    text_failures = []
    for name in text_names:
        data = source_bytes[name]
        try:
            data.decode("utf-8")
        except UnicodeDecodeError:
            text_failures.append(f"{name}: invalid UTF-8")
        if b"\r" in data:
            text_failures.append(f"{name}: contains CR")
    record("all_allowlisted_text_inputs_are_utf8_lf", not text_failures, text_failures)

    plugin_manifest_bytes = source_bytes["manifest.json"]
    plugin_manifest = json.loads(plugin_manifest_bytes)
    source_manifest_pretty = (json.dumps(plugin_manifest, indent=2) + "\n").encode("utf-8")
    record("source_manifest_is_current_two_space_lf_json", plugin_manifest_bytes == source_manifest_pretty and b"\r" not in plugin_manifest_bytes)
    record("source_manifest_0_1_20_contract", plugin_manifest.get("name") == "private_conversation"
           and plugin_manifest.get("version") == "0.1.20"
           and plugin_manifest.get("schema_version") == 2
           and plugin_manifest.get("status") == "development_candidate"
           and plugin_manifest.get("git_repo") == "Francisco-boop-001/CHIM-PrivateConversation"
           and plugin_manifest.get("server_compatibility_reference") == "cf5030f15781637498be86debe26fcf102f5690d")

    dwpkg_path = CANDIDATE / "private_conversation-0.1.20.dwpkg"
    dwpkg_bytes = dwpkg_path.read_bytes()
    dwpkg_contents: dict[str, bytes] = {}
    expected_dwpkg_names = sorted(["manifest.json", "checksums.sha256"] + [f"server/{name}" for name in allowlist])
    expected_package_manifest = {
        "name": plugin_manifest["name"],
        "schema_version": 4,
        "server": {"mutable_paths": ["state"]},
        "version": plugin_manifest["version"],
    }
    expected_package_manifest_bytes = (json.dumps(expected_package_manifest, indent=2, sort_keys=True) + "\n").encode("utf-8")
    with zipfile.ZipFile(dwpkg_path) as archive:
        dwpkg_infos = archive.infolist()
        dwpkg_names = archive.namelist()
        dwpkg_crc_ok = archive.testzip() is None
        dwpkg_contents = {name: archive.read(name) for name in dwpkg_names}
        record("dwpkg_crc_and_exact_members", dwpkg_crc_ok and dwpkg_names == expected_dwpkg_names and len(dwpkg_names) == len(set(dwpkg_names)),
               {"count": len(dwpkg_names), "names": dwpkg_names})
        record("dwpkg_zip_reproducibility_metadata", archive.comment == b"" and all(zip_metadata_ok(info, zipfile.ZIP_STORED) for info in dwpkg_infos))
    dwpkg_checksums_expected = {f"server/{name}": source_bytes[name] for name in allowlist}
    dwpkg_checksums_expected["manifest.json"] = expected_package_manifest_bytes
    expected_dwpkg_checksum_text = "".join(f"{sha(dwpkg_checksums_expected[name])}  {name}\n" for name in sorted(dwpkg_checksums_expected)).encode("ascii")
    record("dwpkg_outer_manifest_schema4_identity", dwpkg_contents.get("manifest.json") == expected_package_manifest_bytes
           and json.loads(dwpkg_contents.get("manifest.json", b"{}")) == expected_package_manifest)
    record("dwpkg_checksum_table_exact_and_valid", dwpkg_contents.get("checksums.sha256") == expected_dwpkg_checksum_text
           and all(dwpkg_contents.get(f"server/{name}") == source_bytes[name] for name in allowlist))
    record("dwpkg_embedded_internal_manifest_matches_current_source", dwpkg_contents.get("server/manifest.json") == plugin_manifest_bytes)
    record("dwpkg_payload_has_21_current_source_files", all(dwpkg_contents.get(f"server/{name}") == source_bytes[name] for name in allowlist)
           and len([name for name in dwpkg_contents if name.startswith("server/")]) == 21)

    tar_details = {}
    tar_details["wrapped"] = inspect_tar("private_conversation.tar.gz", False, allowlist, source_bytes)
    tar_details["flat"] = inspect_tar("chim-plugin.tar.gz", True, allowlist, source_bytes)

    mo2_path = CANDIDATE / "private_conversation-0.1.20-mo2.zip"
    mo2_member = "CHIM/server-plugins/private_conversation/0.1.20.dwpkg"
    with zipfile.ZipFile(mo2_path) as archive:
        mo2_infos = archive.infolist()
        mo2_names = archive.namelist()
        mo2_crc_ok = archive.testzip() is None
        mo2_metadata = archive.read("meta.ini")
        mo2_inner = archive.read(mo2_member)
        record("mo2_crc_and_exact_root_members", mo2_crc_ok and mo2_names == ["meta.ini", mo2_member] and len(mo2_names) == len(set(mo2_names)),
               mo2_names)
        record("mo2_reproducibility_metadata", archive.comment == b"" and len(mo2_infos) == 2
               and all(zip_metadata_ok(info, zipfile.ZIP_DEFLATED) for info in mo2_infos))
    record("mo2_meta_ini_exact_lf", mo2_metadata == b"[General]\nversion=0.1.20\nvalidated=true\n")
    record("mo2_inner_dwpkg_matches_standalone_bytes", mo2_inner == dwpkg_bytes)

    legacy_root = ROOT / "tests/fixtures/duplicate-install/legacy-0.1.19/server"
    git_base = ["git", "-c", "safe.directory=K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev", "-C", str(ROOT)]
    legacy_paths = subprocess.run(git_base + ["ls-tree", "-r", "--name-only", LEGACY_TAG, "--", "server"], check=True, capture_output=True, text=True).stdout.splitlines()
    expected_legacy = [path.removeprefix("server/") for path in legacy_paths]
    actual_legacy = sorted(path.relative_to(legacy_root).as_posix() for path in legacy_root.rglob("*") if path.is_file())
    legacy_mismatches = []
    for name in expected_legacy:
        expected_oid = subprocess.run(git_base + ["rev-parse", f"{LEGACY_TAG}:server/{name}"], check=True, capture_output=True, text=True).stdout.strip()
        actual_oid = subprocess.run(git_base + ["hash-object", "--no-filters", "--", str(legacy_root / pathlib.PurePosixPath(name))], check=True, capture_output=True, text=True).stdout.strip()
        if expected_oid != actual_oid:
            legacy_mismatches.append({"path": name, "tag_blob": expected_oid, "fixture_blob": actual_oid})
    record("legacy_fixture_exact_20_paths", len(expected_legacy) == 20 and actual_legacy == sorted(expected_legacy),
           {"expected_count": len(expected_legacy), "actual_count": len(actual_legacy), "missing": sorted(set(expected_legacy) - set(actual_legacy)), "extra": sorted(set(actual_legacy) - set(expected_legacy))})
    record("legacy_fixture_raw_git_blobs_match_0_1_19", not legacy_mismatches, legacy_mismatches)

    result = {
        "all_pass": all(checks.values()),
        "checked_at_utc": dt.datetime.now(dt.timezone.utc).isoformat(),
        "candidate_directory": str(CANDIDATE),
        "candidate_asset_bytes": asset_sizes,
        "candidate_asset_sha256": asset_hashes,
        "checks": checks,
        "details": details,
        "dwpkg_member_count": len(dwpkg_contents),
        "legacy_source_tag": LEGACY_TAG,
        "python": sys.version,
        "source_allowlist_count": len(allowlist),
        "source_allowlist_sha256": source_hashes,
        "source_builder_sha256": sha((ROOT / "scripts/build-package.py").read_bytes()),
        "tar_assets": tar_details,
    }
except Exception as error:
    checks["inspection_completed"] = False
    details["fatal_error"] = f"{type(error).__name__}: {error}"
    result = {
        "all_pass": False,
        "checked_at_utc": dt.datetime.now(dt.timezone.utc).isoformat(),
        "checks": checks,
        "details": details,
        "python": sys.version,
    }

EVIDENCE.mkdir(parents=True, exist_ok=True)
receipt = EVIDENCE / "candidate-inspection.json"
receipt.write_text(json.dumps(result, indent=2, sort_keys=True) + "\n", encoding="utf-8", newline="\n")
print(json.dumps({"all_pass": result["all_pass"], "checks": checks, "receipt": str(receipt)}, sort_keys=True))
if not result["all_pass"]:
    raise SystemExit(1)
