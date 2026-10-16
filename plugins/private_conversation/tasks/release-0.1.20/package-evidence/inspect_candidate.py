from __future__ import annotations
import hashlib, json, tarfile, zipfile
from pathlib import Path, PurePosixPath

repo = Path(__file__).resolve().parents[3]
source_root = repo / "server"
candidate = Path(__file__).resolve().parent / "candidate"
assets = sorted(p for p in candidate.iterdir() if p.is_file())
if len(assets) != 5:
    raise SystemExit(f"expected 5 release files, found {len(assets)}")
summary = {"candidate": str(candidate), "source_root": str(source_root), "assets": []}
asset_bytes = {p.name: p.read_bytes() for p in assets}
for path in assets:
    item = {"name": path.name, "size_bytes": path.stat().st_size, "sha256": hashlib.sha256(asset_bytes[path.name]).hexdigest()}
    if path.name.endswith(".dwpkg") or path.name.endswith("-mo2.zip"):
        with zipfile.ZipFile(path) as archive:
            if archive.testzip() is not None:
                raise SystemExit(f"ZIP CRC failure: {path.name}")
            names = archive.namelist()
            item["members"] = names
            item["member_count"] = len(names)
            if path.name.endswith(".dwpkg"):
                package_manifest = json.loads(archive.read("manifest.json"))
                plugin_manifest = json.loads(archive.read("server/manifest.json"))
                item["package_schema_version"] = package_manifest.get("schema_version")
                item["plugin_manifest"] = {k: plugin_manifest.get(k) for k in ("name", "version", "status", "default_channel", "server_compatibility_reference")}
                server_members = [n for n in names if n.startswith("server/")]
                item["server_member_count"] = len(server_members)
                item["source_member_equality"] = {}
                for member in server_members:
                    rel = PurePosixPath(member).relative_to("server")
                    source = source_root.joinpath(*rel.parts).read_bytes()
                    payload = archive.read(member)
                    item["source_member_equality"][member] = {
                        "equal": payload == source,
                        "sha256": hashlib.sha256(payload).hexdigest(),
                    }
                    if payload != source:
                        raise SystemExit(f"source mismatch in DWPkg: {member}")
                if len(server_members) != 21 or "server/installation_guard.php" not in names:
                    raise SystemExit("DWPkg runtime payload does not contain the expected 21 server files/helper")
            else:
                if len(names) != 2:
                    raise SystemExit("MO2 wrapper must have exactly two entries")
                inner = [n for n in names if n.endswith(".dwpkg")]
                if len(inner) != 1:
                    raise SystemExit("MO2 wrapper must contain exactly one DWPkg")
                item["inner_dwpkg_equal"] = archive.read(inner[0]) == asset_bytes["private_conversation-0.1.20.dwpkg"]
                if not item["inner_dwpkg_equal"]:
                    raise SystemExit("MO2 embedded DWPkg differs from standalone asset")
    elif path.name.endswith(".tar.gz"):
        with tarfile.open(path, "r:gz") as archive:
            members = archive.getmembers()
            item["members"] = [m.name for m in members]
            item["member_count"] = len(members)
            item["member_types"] = ["dir" if m.isdir() else "file" if m.isfile() else "other" for m in members]
            if any(not (m.isdir() or m.isfile()) for m in members):
                raise SystemExit(f"unexpected TAR member type in {path.name}")
            prefix = "private_conversation/" if path.name == "private_conversation.tar.gz" else ""
            comparisons = {}
            for member in members:
                if not member.isfile():
                    continue
                rel = PurePosixPath(member.name)
                if prefix:
                    rel = rel.relative_to("private_conversation")
                source = source_root.joinpath(*rel.parts).read_bytes()
                stream = archive.extractfile(member)
                if stream is None:
                    raise SystemExit(f"cannot read TAR member: {member.name}")
                payload = stream.read()
                comparisons[member.name] = {"equal": payload == source, "sha256": hashlib.sha256(payload).hexdigest()}
                if payload != source:
                    raise SystemExit(f"source mismatch in TAR: {member.name}")
            item["source_member_equality"] = comparisons
            item["file_member_count"] = len(comparisons)
    summary["assets"].append(item)
sums = (candidate / "SHA256SUMS.txt").read_text(encoding="ascii").splitlines()
if len(sums) != 4:
    raise SystemExit(f"expected four SHA256SUMS entries, found {len(sums)}")
for line in sums:
    digest, name = line.split("  ", 1)
    if name not in asset_bytes or hashlib.sha256(asset_bytes[name]).hexdigest() != digest:
        raise SystemExit(f"SHA256SUMS mismatch: {name}")
summary["sha256sums_lines"] = len(sums)
print(json.dumps(summary, indent=2, sort_keys=True))
