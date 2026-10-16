"""Build and verify deterministic CHIM plugin distribution packages."""

from __future__ import annotations

import argparse
import gzip
import hashlib
import io
import json
import os
import re
import stat
import tarfile
import tempfile
import xml.etree.ElementTree as ET
from pathlib import Path
from xml.sax.saxutils import escape, quoteattr
from zipfile import ZIP_DEFLATED, ZIP_STORED, BadZipFile, ZipFile, ZipInfo


SCHEMA_VERSION = 4
SERVER_FILES = (
    "AGENTS.md",
    "README.md",
    "controls.php",
    "dashboard-art.webp",
    "dashboard.css",
    "dashboard.js",
    "dashboard.php",
    "dashboard_data.php",
    "dashboard_view.php",
    "globals.php",
    "influence.php",
    "index.php",
    "logging.php",
    "manifest.json",
    "model.php",
    "postrequest.php",
    "prerequest.php",
    "reflection.php",
    "store.php",
)
_NAME = re.compile(r"^[A-Za-z0-9][A-Za-z0-9 ._-]{0,63}$")
_VERSION = re.compile(r"^[0-9A-Za-z][0-9A-Za-z._+-]{0,63}$")
_CHECKSUM = re.compile(r"^([a-f0-9]{64})  (.+)$")
# DrvFs rejects extraction when tar members carry the Unix epoch timestamp.
REPOSITORY_TAR_MTIME = 315532800  # 1980-01-01 UTC


class PackageError(ValueError):
    pass


def _read_entries(project_root: Path) -> tuple[dict, dict[str, bytes]]:
    root = project_root.resolve()
    server_root = root / "server"
    if server_root.is_symlink() or not server_root.is_dir():
        raise PackageError("Missing server payload directory")
    missing = [
        name
        for name in SERVER_FILES
        if not (server_root / name).is_file() or (server_root / name).is_symlink()
    ]
    if missing:
        raise PackageError(f"Missing allowlisted server payload: {', '.join(missing)}")

    entries = {}
    for name in SERVER_FILES:
        path = server_root / name
        if not path.resolve().is_relative_to(server_root.resolve()):
            raise PackageError(f"Server payload escapes its root: {name}")
        entries[f"server/{name}"] = path.read_bytes()

    try:
        inner = json.loads(entries["server/manifest.json"])
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        raise PackageError("server/manifest.json must be valid UTF-8 JSON") from error
    if not isinstance(inner, dict):
        raise PackageError("server/manifest.json must contain an object")
    name, version = inner.get("name"), inner.get("version")
    if (
        not isinstance(name, str)
        or not _NAME.fullmatch(name)
        or name.endswith((".", " "))
    ):
        raise PackageError("server/manifest.json has an invalid package name")
    if not isinstance(version, str) or not _VERSION.fullmatch(version):
        raise PackageError("server/manifest.json has an invalid package version")

    outer = {
        "schema_version": SCHEMA_VERSION,
        "name": name,
        "version": version,
        "server": {"mutable_paths": []},
    }
    entries["manifest.json"] = (
        json.dumps(outer, ensure_ascii=False, indent=2, sort_keys=True) + "\n"
    ).encode("utf-8")
    checksums = "".join(
        f"{hashlib.sha256(data).hexdigest()}  {path}\n"
        for path, data in sorted(entries.items())
    )
    entries["checksums.sha256"] = checksums.encode("ascii")
    return outer, entries


def verify_archive(archive_path: Path, project_root: Path) -> dict:
    manifest, expected = _read_entries(Path(project_root))
    try:
        with ZipFile(archive_path) as archive:
            names = archive.namelist()
            if names != sorted(expected) or len(names) != len(set(names)):
                raise PackageError("Archive member list does not match the explicit allowlist")
            if archive.testzip() is not None:
                raise PackageError("Archive contains a CRC failure")
            actual = {name: archive.read(name) for name in names}
    except (OSError, BadZipFile, KeyError, ValueError) as error:
        if isinstance(error, PackageError):
            raise
        raise PackageError(f"Could not verify package archive: {error}") from error

    hashes = {}
    try:
        for line in actual["checksums.sha256"].decode("ascii").splitlines():
            match = _CHECKSUM.fullmatch(line)
            if not match or match[2] in hashes:
                raise PackageError("Archive checksum list is malformed")
            hashes[match[2]] = match[1]
    except UnicodeDecodeError as error:
        raise PackageError("Archive checksum list is not ASCII") from error
    if set(hashes) != set(actual) - {"checksums.sha256"}:
        raise PackageError("Archive checksum list does not cover its exact member list")
    for name, contents in actual.items():
        if name == "checksums.sha256":
            continue
        if hashes[name] != hashlib.sha256(contents).hexdigest():
            raise PackageError(f"Archive checksum mismatch: {name}")
        if contents != expected[name]:
            raise PackageError(f"Archive source mismatch: {name}")
    return manifest


def build_package(project_root: Path, archive_path: Path) -> dict:
    root = Path(project_root).resolve()
    output = Path(archive_path).resolve()
    try:
        output.relative_to(root)
    except ValueError as error:
        raise PackageError("Package output must stay inside the project") from error
    if output in {(root / "server" / name).resolve() for name in SERVER_FILES}:
        raise PackageError("Package output cannot replace a server source file")

    manifest, entries = _read_entries(root)
    output.parent.mkdir(parents=True, exist_ok=True)
    descriptor, temporary_name = tempfile.mkstemp(
        prefix=f".{output.name}.", suffix=".tmp", dir=output.parent
    )
    os.close(descriptor)
    temporary = Path(temporary_name)
    try:
        with ZipFile(temporary, "w", compression=ZIP_STORED) as archive:
            archive.comment = b""
            for name, contents in sorted(entries.items()):
                info = ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
                info.compress_type = ZIP_STORED
                info.create_system = 3
                info.external_attr = (stat.S_IFREG | 0o644) << 16
                archive.writestr(info, contents)
        verify_archive(temporary, root)
        os.replace(temporary, output)
    finally:
        temporary.unlink(missing_ok=True)
    return manifest


def _verify_server_tar_archive(
    archive_path: Path, project_root: Path, *, wrapped: bool
) -> dict:
    _, entries = _read_entries(Path(project_root))
    inner_manifest = json.loads(entries["server/manifest.json"])
    package_name = inner_manifest["name"]
    expected = [
        (f"{package_name}/{name}" if wrapped else name, entries[f"server/{name}"])
        for name in SERVER_FILES
    ]
    expected_names = ([package_name] if wrapped else []) + [name for name, _ in expected]
    archive_label = "Repository" if wrapped else "CHIM Plugin Manager"

    try:
        with tarfile.open(archive_path, "r:gz") as archive:
            members = archive.getmembers()
            if [member.name for member in members] != expected_names:
                raise PackageError(f"{archive_label} archive member list does not match the explicit allowlist")
            file_members = members
            if wrapped:
                root_member = members[0]
                if root_member.type != tarfile.DIRTYPE or root_member.size != 0:
                    raise PackageError("Repository archive root must be a directory")
                if (
                    root_member.mode != 0o755
                    or root_member.mtime != REPOSITORY_TAR_MTIME
                    or root_member.uid != 0
                    or root_member.gid != 0
                ):
                    raise PackageError("Repository archive root metadata is not deterministic")
                file_members = members[1:]
            for member, (name, contents) in zip(file_members, expected, strict=True):
                if member.type != tarfile.REGTYPE:
                    raise PackageError(f"{archive_label} payload is not a regular file: {member.name}")
                if (
                    member.mode != 0o644
                    or member.mtime != REPOSITORY_TAR_MTIME
                    or member.uid != 0
                    or member.gid != 0
                ):
                    raise PackageError(f"{archive_label} payload metadata is not deterministic: {member.name}")
                if member.size != len(contents):
                    raise PackageError(f"{archive_label} source size mismatch: {member.name}")
                source = archive.extractfile(member)
                if source is None:
                    raise PackageError(f"Could not read {archive_label} payload: {member.name}")
                with source:
                    if source.read() != contents:
                        raise PackageError(f"{archive_label} source mismatch: {member.name}")
    except (OSError, EOFError, tarfile.TarError) as error:
        raise PackageError(f"Could not verify {archive_label} archive: {error}") from error
    return inner_manifest


def verify_repository_archive(archive_path: Path, project_root: Path) -> dict:
    return _verify_server_tar_archive(archive_path, project_root, wrapped=True)


def verify_chim_plugin_archive(archive_path: Path, project_root: Path) -> dict:
    """Verify the flat-root TAR consumed by manifest-free Plugin Manager discovery."""
    return _verify_server_tar_archive(archive_path, project_root, wrapped=False)


def _build_server_tar_archive(
    project_root: Path, archive_path: Path, *, wrapped: bool
) -> dict:
    root = Path(project_root).resolve()
    output = Path(archive_path).resolve()
    try:
        output.relative_to(root)
    except ValueError as error:
        raise PackageError("Package output must stay inside the project") from error
    if output in {(root / "server" / name).resolve() for name in SERVER_FILES}:
        raise PackageError("Package output cannot replace a server source file")

    manifest, entries = _read_entries(root)
    package_name = manifest["name"]
    output.parent.mkdir(parents=True, exist_ok=True)
    descriptor, temporary_name = tempfile.mkstemp(
        prefix=f".{output.name}.", suffix=".tmp", dir=output.parent
    )
    os.close(descriptor)
    temporary = Path(temporary_name)
    try:
        with temporary.open("wb") as raw:
            with gzip.GzipFile(
                filename="", fileobj=raw, mode="wb", compresslevel=9, mtime=0
            ) as compressed:
                with tarfile.open(
                    fileobj=compressed, mode="w", format=tarfile.USTAR_FORMAT
                ) as archive:
                    if wrapped:
                        directory = tarfile.TarInfo(package_name)
                        directory.type = tarfile.DIRTYPE
                        directory.mode = 0o755
                        directory.mtime = REPOSITORY_TAR_MTIME
                        directory.uid = directory.gid = 0
                        directory.uname = directory.gname = ""
                        archive.addfile(directory)
                    for name in SERVER_FILES:
                        contents = entries[f"server/{name}"]
                        archive_name = f"{package_name}/{name}" if wrapped else name
                        member = tarfile.TarInfo(archive_name)
                        member.size = len(contents)
                        member.mode = 0o644
                        member.mtime = REPOSITORY_TAR_MTIME
                        member.uid = member.gid = 0
                        member.uname = member.gname = ""
                        archive.addfile(member, io.BytesIO(contents))
        verifier = verify_repository_archive if wrapped else verify_chim_plugin_archive
        verifier(temporary, root)
        os.replace(temporary, output)
    finally:
        temporary.unlink(missing_ok=True)
    return json.loads(entries["server/manifest.json"])


def build_repository_archive(project_root: Path, archive_path: Path) -> dict:
    """Build the legacy package-name-wrapped TAR used by manifest channels."""
    return _build_server_tar_archive(project_root, archive_path, wrapped=True)


def build_chim_plugin_archive(project_root: Path, archive_path: Path) -> dict:
    """Build the flat-root TAR required by manifest-free CHIM Plugin Manager discovery."""
    return _build_server_tar_archive(project_root, archive_path, wrapped=False)


def build_mo2_sync_archive(project_root: Path, archive_path: Path) -> dict:
    """Build a deterministic ZIP that MO2 imports as a CHIM file-sync mod."""
    root = Path(project_root).resolve()
    output = Path(archive_path).resolve()
    try:
        output.relative_to(root)
    except ValueError as error:
        raise PackageError("Package output must stay inside the project") from error
    if output in {(root / "server" / name).resolve() for name in SERVER_FILES}:
        raise PackageError("Package output cannot replace a server source file")

    manifest, _ = _read_entries(root)
    package_name = manifest["name"]
    version = manifest["version"]
    member_name = f"CHIM/server-plugins/{package_name}/{version}.dwpkg"
    metadata = (
        "[General]\n"
        f"version={version}\n"
        "customURL=https://github.com/Francisco-boop-001/CHIM-MindPoisoning\n"
    ).encode("utf-8")
    output.parent.mkdir(parents=True, exist_ok=True)

    with tempfile.TemporaryDirectory(prefix=f".{output.stem}-", dir=output.parent) as temp_name:
        temporary_root = Path(temp_name)
        package_path = temporary_root / f"{package_name}-{version}.dwpkg"
        wrapper_path = temporary_root / output.name
        build_package(root, package_path)
        package_bytes = package_path.read_bytes()

        with ZipFile(wrapper_path, "w", compression=ZIP_DEFLATED, compresslevel=9) as archive:
            for name, contents in sorted(
                (("meta.ini", metadata), (member_name, package_bytes))
            ):
                info = ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
                info.compress_type = ZIP_DEFLATED
                info.create_system = 3
                info.external_attr = (stat.S_IFREG | 0o644) << 16
                archive.writestr(info, contents)

        with ZipFile(wrapper_path) as archive:
            if (
                archive.namelist() != sorted(("meta.ini", member_name))
                or archive.testzip() is not None
            ):
                raise PackageError("MO2 archive has an invalid member list or CRC")
            if archive.read(member_name) != package_bytes:
                raise PackageError("MO2 archive changed the CHIM sync package bytes")
            if archive.read("meta.ini") != metadata:
                raise PackageError("MO2 archive metadata does not match its package version")

        os.replace(wrapper_path, output)
    return manifest


def build_mo2_fomod_archive(project_root: Path, archive_path: Path) -> dict:
    """Build a deterministic FOMOD wrapper with an exact CHIM sync destination."""
    root = Path(project_root).resolve()
    output = Path(archive_path).resolve()
    try:
        output.relative_to(root)
    except ValueError as error:
        raise PackageError("Package output must stay inside the project") from error
    if output in {(root / "server" / name).resolve() for name in SERVER_FILES}:
        raise PackageError("Package output cannot replace a server source file")

    manifest, _ = _read_entries(root)
    package_name = manifest["name"]
    version = manifest["version"]
    package_member = f"CHIM/server-plugins/{package_name}/{version}.dwpkg"
    info = (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        "<fomod>\n"
        "  <Name>Mind Poisoning PRE-ALPHA</Name>\n"
        f"  <Version>{escape(version)}</Version>\n"
        "  <Description>CHIM server package only. Use an isolated PRE-ALPHA test profile and server/database. No ESP/ESL or Skyrim scripts are included. Live CHIM and in-game verification are incomplete.</Description>\n"
        "</fomod>\n"
    ).encode("utf-8")
    module_config = (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        "<config>\n"
        f"  <moduleName>{escape(package_name)} PRE-ALPHA</moduleName>\n"
        "  <requiredInstallFiles>\n"
        f"    <file source={quoteattr(package_member)} destination={quoteattr(package_member)} />\n"
        "  </requiredInstallFiles>\n"
        "</config>\n"
    ).encode("utf-8")

    output.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix=f".{output.stem}-", dir=output.parent) as temp_name:
        temporary_root = Path(temp_name)
        package_path = temporary_root / f"{package_name}-{version}.dwpkg"
        wrapper_path = temporary_root / output.name
        build_package(root, package_path)
        package_bytes = package_path.read_bytes()

        with ZipFile(wrapper_path, "w", compression=ZIP_DEFLATED, compresslevel=9) as archive:
            for name, contents in (
                ("fomod/info.xml", info),
                ("fomod/ModuleConfig.xml", module_config),
                (package_member, package_bytes),
            ):
                item = ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
                item.compress_type = ZIP_DEFLATED
                item.create_system = 3
                item.external_attr = (stat.S_IFREG | 0o644) << 16
                archive.writestr(item, contents)

        try:
            with ZipFile(wrapper_path) as archive:
                expected_names = ["fomod/info.xml", "fomod/ModuleConfig.xml", package_member]
                if archive.namelist() != expected_names or archive.testzip() is not None:
                    raise PackageError("MO2 FOMOD archive has an invalid member list or CRC")
                if archive.read(package_member) != package_bytes:
                    raise PackageError("MO2 FOMOD archive changed the CHIM sync package bytes")
                config = ET.fromstring(archive.read("fomod/ModuleConfig.xml"))
                files = config.findall("./requiredInstallFiles/file")
                if len(files) != 1 or files[0].attrib != {
                    "source": package_member,
                    "destination": package_member,
                }:
                    raise PackageError("MO2 FOMOD must map the package to its exact CHIM path")
        except ET.ParseError as error:
            raise PackageError("MO2 FOMOD configuration is not valid XML") from error

        os.replace(wrapper_path, output)
    return manifest


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--format",
        choices=("dwpkg", "repository-tar-gz", "chim-plugin-tar-gz", "mo2-sync-zip", "mo2-fomod-zip"),
        default="dwpkg",
        help="package format (default: dwpkg)",
    )
    options = parser.parse_args()
    project_root = Path(__file__).resolve().parents[1]
    inner, _ = _read_entries(project_root)
    if options.format == "repository-tar-gz":
        output = project_root / "dist" / f"{inner['name']}.tar.gz"
        manifest = build_repository_archive(project_root, output)
    elif options.format == "chim-plugin-tar-gz":
        output = project_root / "dist" / "chim-plugin.tar.gz"
        manifest = build_chim_plugin_archive(project_root, output)
    elif options.format == "mo2-sync-zip":
        output = (
            project_root / "dist" / inner["version"]
            / f"{inner['name']}-{inner['version']}-mo2.zip"
        )
        manifest = build_mo2_sync_archive(project_root, output)
    elif options.format == "mo2-fomod-zip":
        output = (
            project_root / "dist" / inner["version"]
            / f"{inner['name']}-{inner['version']}-mo2-installer.zip"
        )
        manifest = build_mo2_fomod_archive(project_root, output)
    else:
        output = project_root / "dist" / f"{inner['name']}-{inner['version']}.dwpkg"
        manifest = build_package(project_root, output)
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    print(f"Built and verified {output.relative_to(project_root)} ({manifest['name']} {manifest['version']}, sha256 {digest})")


if __name__ == "__main__":
    try:
        main()
    except (OSError, KeyError, json.JSONDecodeError, PackageError) as error:
        raise SystemExit(f"package failed: {error}") from error
