import json
import sys
import tarfile
import tempfile
import unittest
import xml.etree.ElementTree as ET
from contextlib import redirect_stdout
from io import StringIO
from pathlib import Path
from unittest.mock import patch
from zipfile import ZIP_STORED, ZipFile

PROJECT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT))

from scripts import package as package_builder
from scripts.package import (
    SERVER_FILES,
    REPOSITORY_TAR_MTIME,
    PackageError,
    build_package,
    build_repository_archive,
    build_mo2_fomod_archive,
    build_mo2_sync_archive,
    verify_archive,
    verify_repository_archive,
)

FIXTURE_NAME = "mind_poisoning"


def write_fixture_project(root: Path, *, extra_files: bool = False) -> None:
    server = root / "server"
    server.mkdir(parents=True)
    manifest = {"name": FIXTURE_NAME, "version": "0.1.0", "description": "Package fixture"}
    (server / "manifest.json").write_text(json.dumps(manifest), encoding="utf-8")
    for name in SERVER_FILES:
        path = server / name
        if name == "manifest.json":
            continue
        path.write_text(f"fixture:{name}\n", encoding="utf-8")
    if extra_files:
        for name in (
            "server/debug.log",
            "server/conf/live.php",
            "tasks/private.txt",
            "tests/test_package.py",
            ".git/config",
            "credentials.json",
        ):
            path = root / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text("must not be packaged\n", encoding="utf-8")


class PackageTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temporary = tempfile.TemporaryDirectory(prefix=".package-test-", dir=PROJECT / "tests")
        self.root = Path(self.temporary.name)
        self.source = self.root

    def tearDown(self) -> None:
        self.temporary.cleanup()

    def test_build_is_deterministic_and_uses_only_the_allowlist(self) -> None:
        write_fixture_project(self.source, extra_files=True)
        first = self.root / "first.dwpkg"
        second = self.root / "second.dwpkg"

        build_package(self.source, first)
        build_package(self.source, second)

        self.assertEqual(first.read_bytes(), second.read_bytes())
        expected = {"manifest.json", "checksums.sha256"} | {f"server/{name}" for name in SERVER_FILES}
        with ZipFile(first) as archive:
            self.assertEqual(set(archive.namelist()), expected)
            outer = json.loads(archive.read("manifest.json"))
            self.assertEqual(outer["schema_version"], 4)
            self.assertEqual(outer["name"], FIXTURE_NAME)
            self.assertEqual(outer["version"], "0.1.0")
        verify_archive(first, self.source)

    def test_missing_allowlisted_payload_fails_before_writing(self) -> None:
        write_fixture_project(self.source)
        (self.source / "server" / "store.php").unlink()
        output = self.root / "missing.dwpkg"

        with self.assertRaisesRegex(PackageError, "store.php"):
            build_package(self.source, output)

        self.assertFalse(output.exists())

    def test_verifier_rejects_checksum_tampering(self) -> None:
        write_fixture_project(self.source)
        archive_path = self.root / "valid.dwpkg"
        tampered_path = self.root / "tampered.dwpkg"
        build_package(self.source, archive_path)
        with ZipFile(archive_path) as original, ZipFile(tampered_path, "w", compression=ZIP_STORED) as tampered:
            for name in original.namelist():
                data = original.read(name)
                if name == "server/influence.php":
                    data += b"tampered\n"
                tampered.writestr(name, data)

        with self.assertRaisesRegex(PackageError, "checksum"):
            verify_archive(tampered_path, self.source)

    def test_repository_tar_is_deterministic_and_contains_only_regular_payload_files(self) -> None:
        write_fixture_project(self.source, extra_files=True)
        first = self.root / "first.tar.gz"
        second = self.root / "second.tar.gz"

        build_repository_archive(self.source, first)
        build_repository_archive(self.source, second)

        self.assertEqual(first.read_bytes(), second.read_bytes())
        self.assertEqual(int.from_bytes(first.read_bytes()[4:8], "little"), 0)
        self.assertEqual(
            verify_repository_archive(first, self.source),
            {
                "name": FIXTURE_NAME,
                "version": "0.1.0",
                "description": "Package fixture",
            },
        )
        expected_names = [FIXTURE_NAME] + [
            f"{FIXTURE_NAME}/{name}" for name in SERVER_FILES
        ]
        with tarfile.open(first, "r:gz") as archive:
            members = archive.getmembers()
            self.assertEqual([member.name for member in members], expected_names)
            self.assertTrue(members[0].isdir())
            self.assertEqual(members[0].type, tarfile.DIRTYPE)
            self.assertEqual(members[0].mtime, REPOSITORY_TAR_MTIME)
            for name, member in zip(SERVER_FILES, members[1:], strict=True):
                self.assertEqual(member.type, tarfile.REGTYPE)
                self.assertEqual(member.mtime, REPOSITORY_TAR_MTIME)
                with archive.extractfile(member) as contents:
                    self.assertEqual(contents.read(), (self.source / "server" / name).read_bytes())

    def test_chim_plugin_tar_is_flat_deterministic_and_independently_verified(self) -> None:
        write_fixture_project(self.source, extra_files=True)
        index = self.source / "server" / "index.php"
        index.write_bytes(b"<?php require __DIR__ . '/dashboard.php';\n")
        first = self.root / "chim-plugin-first.tar.gz"
        second = self.root / "chim-plugin-second.tar.gz"
        build = getattr(package_builder, "build_chim_plugin_archive", None)
        verify = getattr(package_builder, "verify_chim_plugin_archive", None)

        self.assertTrue(callable(build), "flat CHIM Plugin Manager archive builder is missing")
        self.assertTrue(callable(verify), "flat CHIM Plugin Manager archive verifier is missing")
        build(self.source, first)
        build(self.source, second)

        self.assertEqual(first.read_bytes(), second.read_bytes())
        self.assertEqual(int.from_bytes(first.read_bytes()[4:8], "little"), 0)
        self.assertEqual(verify(first, self.source)["name"], FIXTURE_NAME)
        expected_names = list(SERVER_FILES)
        with tarfile.open(first, "r:gz") as archive:
            members = archive.getmembers()
            self.assertEqual([member.name for member in members], expected_names)
            self.assertNotIn("mind_poisoning", [member.name for member in members])
            for member in members:
                self.assertTrue(member.isfile())
                self.assertEqual(member.mode, 0o644)
                self.assertEqual(member.mtime, REPOSITORY_TAR_MTIME)
                self.assertEqual(member.uid, 0)
                self.assertEqual(member.gid, 0)
                with archive.extractfile(member) as contents:
                    self.assertEqual(
                        contents.read(), (self.source / "server" / member.name).read_bytes()
                    )
        self.assertIn("globals.php", expected_names)
        self.assertIn("index.php", expected_names)

        (self.source / "scripts").mkdir()
        with (
            patch.object(package_builder, "__file__", str(self.source / "scripts" / "package.py")),
            patch("sys.argv", ["package.py", "--format", "chim-plugin-tar-gz"]),
            redirect_stdout(StringIO()) as output,
        ):
            package_builder.main()
        cli_archive = self.source / "dist" / "chim-plugin.tar.gz"
        self.assertTrue(cli_archive.is_file())
        self.assertIn("chim-plugin.tar.gz", output.getvalue())
        verify(cli_archive, self.source)

    def test_current_webp_artwork_and_postrequest_hook_are_packaged(self) -> None:
        artwork = (PROJECT / "server" / "dashboard-art.webp").read_bytes()
        refresh_script = (PROJECT / "server" / "dashboard.js").read_bytes()
        self.assertIn("postrequest.php", SERVER_FILES)
        postrequest_hook = (PROJECT / "server" / "postrequest.php").read_bytes()
        self.assertTrue(artwork.startswith(b"RIFF") and artwork[8:12] == b"WEBP")
        self.assertIn("dashboard.js", SERVER_FILES)
        self.assertEqual(
            (PROJECT / "assets" / "dashboard-art-source.png").read_bytes()[:8],
            b"\x89PNG\r\n\x1a\n",
        )
        self.assertIn("dashboard-art.webp", SERVER_FILES)
        self.assertNotIn("dashboard-art.png", SERVER_FILES)

        dwpkg = self.root / "current.dwpkg"
        tarball = self.root / "current.tar.gz"
        mo2_bundle = self.root / "current-mo2.zip"
        build_package(PROJECT, dwpkg)
        build_repository_archive(PROJECT, tarball)
        manifest = build_mo2_sync_archive(PROJECT, mo2_bundle)

        with ZipFile(dwpkg) as archive:
            self.assertEqual(archive.read("server/dashboard-art.webp"), artwork)
            self.assertEqual(archive.read("server/dashboard.js"), refresh_script)
            self.assertEqual(archive.read("server/postrequest.php"), postrequest_hook)
            self.assertNotIn("server/dashboard-art.png", archive.namelist())
        with tarfile.open(tarball, "r:gz") as archive:
            members = {member.name: member for member in archive.getmembers()}
            self.assertNotIn("mind_poisoning/dashboard-art.png", members)
            with archive.extractfile(members["mind_poisoning/dashboard-art.webp"]) as payload:
                self.assertEqual(payload.read(), artwork)
            with archive.extractfile(members["mind_poisoning/dashboard.js"]) as payload:
                self.assertEqual(payload.read(), refresh_script)
            with archive.extractfile(members["mind_poisoning/postrequest.php"]) as payload:
                self.assertEqual(payload.read(), postrequest_hook)
        member_name = (
            f"CHIM/server-plugins/{manifest['name']}/{manifest['version']}.dwpkg"
        )
        current_manifest = json.loads(
            (PROJECT / "server" / "manifest.json").read_text(encoding="utf-8")
        )
        with ZipFile(mo2_bundle) as wrapper:
            self.assertEqual(wrapper.namelist(), sorted(("meta.ini", member_name)))
            self.assertIsNone(wrapper.testzip())
            nested_package = self.root / "current-mo2.dwpkg"
            nested_bytes = wrapper.read(member_name)
            nested_package.write_bytes(nested_bytes)
            metadata = wrapper.read("meta.ini").decode("utf-8")
        self.assertEqual(nested_bytes, dwpkg.read_bytes())
        self.assertEqual(
            metadata,
            "[General]\n"
            f"version={current_manifest['version']}\n"
            f"customURL=https://github.com/{current_manifest['git_repo']}\n",
        )
        verify_archive(nested_package, PROJECT)
        with ZipFile(nested_package) as archive:
            self.assertEqual(archive.read("server/dashboard-art.webp"), artwork)
            self.assertEqual(archive.read("server/dashboard.js"), refresh_script)
            self.assertEqual(archive.read("server/postrequest.php"), postrequest_hook)
            self.assertNotIn("server/dashboard-art.png", archive.namelist())

    def test_mo2_sync_zip_is_deterministic_and_has_importable_path(self) -> None:
        write_fixture_project(self.source)
        first = self.root / "first-mo2.zip"
        second = self.root / "second-mo2.zip"

        build_mo2_sync_archive(self.source, first)
        build_mo2_sync_archive(self.source, second)

        self.assertEqual(first.read_bytes(), second.read_bytes())
        member_name = f"CHIM/server-plugins/{FIXTURE_NAME}/0.1.0.dwpkg"
        expected_package = self.root / "expected.dwpkg"
        build_package(self.source, expected_package)
        with ZipFile(first) as archive:
            self.assertEqual(archive.namelist(), sorted(("meta.ini", member_name)))
            self.assertIsNone(archive.testzip())
            nested_package = self.root / "nested.dwpkg"
            nested_bytes = archive.read(member_name)
            nested_package.write_bytes(nested_bytes)
            metadata = archive.read("meta.ini").decode("utf-8")
        self.assertEqual(nested_bytes, expected_package.read_bytes())
        self.assertEqual(
            metadata,
            "[General]\n"
            "version=0.1.0\n"
            "customURL=https://github.com/Francisco-boop-001/CHIM-MindPoisoning\n",
        )
        self.assertEqual(verify_archive(nested_package, self.source)["version"], "0.1.0")

    def test_mo2_fomod_zip_is_deterministic_and_maps_exact_package_bytes(self) -> None:
        write_fixture_project(self.source)
        first = self.root / "first-mo2-installer.zip"
        second = self.root / "second-mo2-installer.zip"

        build_mo2_fomod_archive(self.source, first)
        build_mo2_fomod_archive(self.source, second)

        self.assertEqual(first.read_bytes(), second.read_bytes())
        member_name = f"CHIM/server-plugins/{FIXTURE_NAME}/0.1.0.dwpkg"
        with ZipFile(first) as archive:
            self.assertEqual(
                archive.namelist(),
                ["fomod/info.xml", "fomod/ModuleConfig.xml", member_name],
            )
            info = archive.read("fomod/info.xml").decode("utf-8")
            self.assertIn("<Name>Mind Poisoning PRE-ALPHA</Name>", info)
            self.assertIn("PRE-ALPHA", info)
            self.assertIn("isolated PRE-ALPHA test profile", info)
            config = ET.fromstring(archive.read("fomod/ModuleConfig.xml"))
            self.assertIsNone(config.find("installSteps"))
            files = config.findall("./requiredInstallFiles/file")
            self.assertEqual(
                [item.attrib for item in files],
                [{"source": member_name, "destination": member_name}],
            )
            nested_package = self.root / "nested-fomod.dwpkg"
            nested_package.write_bytes(archive.read(member_name))

        expected_package = self.root / "expected.dwpkg"
        build_package(self.source, expected_package)
        self.assertEqual(nested_package.read_bytes(), expected_package.read_bytes())
        self.assertEqual(verify_archive(nested_package, self.source)["version"], "0.1.0")


def emit_manager_fixture() -> Path:
    fixture_root = PROJECT / "tests" / ".package-manager-check" / "source"
    if fixture_root.exists():
        raise PackageError(f"Remove the existing fixture first: {fixture_root}")
    fixture_root.mkdir(parents=True)
    write_fixture_project(fixture_root)
    archive_path = fixture_root / "fixture.dwpkg"
    build_package(fixture_root, archive_path)
    return archive_path


if __name__ == "__main__":
    if sys.argv[1:] == ["--manager-fixture"]:
        try:
            print(emit_manager_fixture())
        except (OSError, PackageError) as error:
            raise SystemExit(f"fixture failed: {error}") from error
    else:
        unittest.main()
