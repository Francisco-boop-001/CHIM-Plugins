from __future__ import annotations

import argparse
import configparser
import hashlib
import json
import re
import stat
import tarfile
import zipfile
from pathlib import Path, PurePosixPath

parser = argparse.ArgumentParser()
parser.add_argument("--source-root", type=Path)
parser.add_argument("--release-dir", type=Path)
parser.add_argument("--report", type=Path)
options = parser.parse_args()
ROOT = options.source_root.resolve() if options.source_root else Path(__file__).resolve().parents[3]
RELEASE = options.release_dir.resolve() if options.release_dir else Path(__file__).resolve().parent / "candidate"
VERSION = "0.1.25"

def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()

def safe_name(name: str) -> bool:
    path = PurePosixPath(name)
    return bool(name) and not name.startswith("/") and "\\" not in name and not path.is_absolute() and all(part not in ("", ".", "..") for part in path.parts)

source_root = ROOT / "server"
source_files = sorted(path for path in source_root.rglob("*") if path.is_file())
if any(path.is_symlink() for path in source_files):
    raise SystemExit("source includes a symlink")
source = {path.relative_to(source_root).as_posix(): path.read_bytes() for path in source_files}
if len(source) != 21 or any(name.startswith("state/") for name in source):
    raise SystemExit("unexpected server source inventory or mutable state file")
manifest = json.loads(source["manifest.json"])
if manifest.get("version") != VERSION or manifest.get("name") != "private_conversation":
    raise SystemExit("source manifest identity/version mismatch")

expected_assets = {
    "SHA256SUMS.txt",
    "chim-plugin.tar.gz",
    f"private_conversation-{VERSION}-mo2.zip",
    f"private_conversation-{VERSION}.dwpkg",
    "private_conversation.tar.gz",
}
children = list(RELEASE.iterdir())
if len(children) != 5 or {path.name for path in children} != expected_assets or any(path.is_symlink() or not path.is_file() for path in children):
    raise SystemExit("candidate is not exactly five regular files")
asset_records = [{"name": path.name, "bytes": path.stat().st_size, "sha256": digest(path.read_bytes())} for path in sorted(children)]

pkg_path = RELEASE / f"private_conversation-{VERSION}.dwpkg"
pkg_bytes = pkg_path.read_bytes()
with zipfile.ZipFile(pkg_path) as archive:
    package_names = archive.namelist()
    expected_package_names = sorted(["manifest.json", "checksums.sha256", *[f"server/{name}" for name in source]])
    if package_names != expected_package_names or archive.testzip() is not None or any(not safe_name(name) for name in package_names):
        raise SystemExit("DWPkg member layout, CRC, or paths are invalid")
    package = {name: archive.read(name) for name in package_names}
    if any(stat.S_IFMT(archive.getinfo(name).external_attr >> 16) == stat.S_IFLNK for name in package_names):
        raise SystemExit("DWPkg contains a symlink")
outer = json.loads(package["manifest.json"])
if outer != {"name": "private_conversation", "schema_version": 4, "server": {"mutable_paths": ["state"]}, "version": VERSION}:
    raise SystemExit("DWPkg outer manifest is incorrect")
if json.loads(package["server/manifest.json"]) != manifest:
    raise SystemExit("DWPkg inner manifest differs from source")
if any(package[f"server/{name}"] != contents for name, contents in source.items()):
    raise SystemExit("DWPkg server payload differs from source")
checksum_lines = package["checksums.sha256"].decode("ascii").splitlines()
checksum_map = {}
for line in checksum_lines:
    match = re.fullmatch(r"([0-9a-f]{64})  (.+)", line)
    if not match or match.group(2) in checksum_map:
        raise SystemExit("DWPkg checksums are malformed")
    checksum_map[match.group(2)] = match.group(1)
if set(checksum_map) != set(package) - {"checksums.sha256"} or any(checksum_map[name] != digest(data) for name, data in package.items() if name != "checksums.sha256"):
    raise SystemExit("DWPkg checksums do not match exact contents")

for filename, flat in (("private_conversation.tar.gz", False), ("chim-plugin.tar.gz", True)):
    with tarfile.open(RELEASE / filename, "r:gz") as archive:
        members = archive.getmembers()
        expected_names = list(source) if flat else ["private_conversation", *[f"private_conversation/{name}" for name in source]]
        actual_names = [member.name for member in members]
        if actual_names != expected_names or any(not safe_name(name) for name in actual_names):
            raise SystemExit(f"{filename} member layout is incorrect")
        payload_members = members if flat else members[1:]
        if not flat and (not members[0].isdir() or members[0].mode != 0o755 or members[0].mtime != 315532800 or members[0].uid != 0 or members[0].gid != 0):
            raise SystemExit("wrapped TAR root metadata is incorrect")
        for member in payload_members:
            relative = member.name if flat else member.name.split("/", 1)[1]
            stream = archive.extractfile(member)
            if not member.isfile() or member.mode != 0o644 or member.mtime != 315532800 or member.uid != 0 or member.gid != 0 or stream is None or stream.read() != source[relative]:
                raise SystemExit(f"{filename} payload/metadata mismatch: {member.name}")

mo2_path = RELEASE / f"private_conversation-{VERSION}-mo2.zip"
with zipfile.ZipFile(mo2_path) as archive:
    mo2_member = f"CHIM/server-plugins/private_conversation/{VERSION}.dwpkg"
    if archive.namelist() != ["meta.ini", mo2_member] or archive.testzip() is not None:
        raise SystemExit("MO2 ZIP layout or CRC is invalid")
    meta = archive.read("meta.ini")
    if archive.read(mo2_member) != pkg_bytes or meta != f"[General]\nversion={VERSION}\nvalidated=true\n".encode("utf-8"):
        raise SystemExit("MO2 metadata or embedded DWPkg differs")
    parsed_meta = configparser.ConfigParser()
    parsed_meta.read_string(meta.decode("utf-8"))
    if parsed_meta.get("General", "version") != VERSION or not parsed_meta.getboolean("General", "validated"):
        raise SystemExit("MO2 metadata values are invalid")

sums_text = (RELEASE / "SHA256SUMS.txt").read_text(encoding="ascii")
sums = {}
for line in sums_text.splitlines():
    match = re.fullmatch(r"([0-9a-f]{64})  (.+)", line)
    if not match or match.group(2) in sums:
        raise SystemExit("release checksum list is malformed")
    sums[match.group(2)] = match.group(1)
if set(sums) != expected_assets - {"SHA256SUMS.txt"} or any(sums[name] != record["sha256"] for name in sums for record in asset_records if record["name"] == name):
    raise SystemExit("release checksum list does not match the four archive assets")

text_contents = [data for name, data in source.items() if name.endswith((".php", ".js", ".css", ".json")) or name == ".htaccess"]
text_contents += [package["manifest.json"], package["checksums.sha256"], meta, sums_text.encode("ascii")]
if any(b"\r" in data for data in text_contents):
    raise SystemExit("text payload contains CR bytes")

report = {
    "status": "PASS",
    "release_version": VERSION,
    "source_file_count": len(source),
    "candidate_asset_count": len(asset_records),
    "checks": ["exact five regular candidate assets", "DWPkg safe exact member list and CRC", "DWPkg schema/version/mutable-path manifest", "DWPkg internal checksums", "all DWPkg payload bytes equal current server source", "wrapped TAR exact wrapper and deterministic metadata", "flat TAR exact allowlisted files", "MO2 meta.ini and embedded DWPkg", "outer SHA256SUMS covers four assets", "text payload LF-only", "no mutable state file embedded"],
    "assets": asset_records,
}
report_path = options.report.resolve() if options.report else Path(__file__).resolve().parent / "independent-inventory.json"
report_path.write_text(json.dumps(report, indent=2, sort_keys=True) + "\n", encoding="utf-8")
print(json.dumps(report, sort_keys=True))
