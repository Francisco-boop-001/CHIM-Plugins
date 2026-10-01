from __future__ import annotations

import hashlib
import importlib.util
import json
import subprocess
import sys
import tarfile
import tempfile
import unittest
from pathlib import Path
from zipfile import ZipFile


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("pcv_package", ROOT / "scripts" / "build-package.py")
assert SPEC is not None and SPEC.loader is not None
PACKAGE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(PACKAGE)


class PackageChecks(unittest.TestCase):
    def test_deterministic_allowlist_manifest_and_checksums(self) -> None:
        with tempfile.TemporaryDirectory(prefix=".pcv-package-check-", dir=ROOT) as directory:
            first = Path(directory) / "first.dwpkg"
            second = Path(directory) / "second.dwpkg"
            manifest = PACKAGE.build_package(ROOT, first)
            PACKAGE.build_package(ROOT, second)
            self.assertEqual(first.read_bytes(), second.read_bytes())
            self.assertEqual(manifest["name"], "private_conversation")
            PACKAGE.verify_package(first, ROOT)

            with ZipFile(first) as archive:
                expected = {"manifest.json", "checksums.sha256"}
                expected.update(f"server/{name}" for name in PACKAGE.SERVER_FILES)
                self.assertEqual(archive.namelist(), sorted(expected))
                self.assertEqual(archive.testzip(), None)
                outer = json.loads(archive.read("manifest.json"))
                inner = json.loads(archive.read("server/manifest.json"))
                self.assertEqual(outer["schema_version"], 4)
                self.assertEqual(outer["version"], inner["version"])
                self.assertEqual(outer["server"]["mutable_paths"], ["state"])
                self.assertEqual(inner["config_url"], "../ext/private_conversation/index.php")
                self.assertIn("server/diagnostics.php", expected)
                self.assertIn("server/log.php", expected)
                self.assertIn("server/log_reader.php", expected)
                self.assertIn("server/assets/private-conversation-scene.png", expected)
                self.assertIn("server/assets/style.css", expected)
                self.assertIn("server/assets/ui-refresh.js", expected)
                self.assertFalse(any(name.startswith("server/state/") for name in expected))
                self.assertFalse(any(name.startswith("tests/") for name in expected))

    def test_release_bundle_is_deterministic_and_matches_all_consumers(self) -> None:
        with tempfile.TemporaryDirectory(prefix=".pcv-release-check-", dir=ROOT / "tests") as directory:
            scratch = Path(directory)
            first = scratch / "first"
            second = scratch / "second"
            manifest = PACKAGE.build_release(ROOT, first)
            PACKAGE.build_release(ROOT, second)

            names = {
                "private_conversation-0.1.6.dwpkg",
                "private_conversation.tar.gz",
                "private_conversation-0.1.6-mo2.zip",
                "SHA256SUMS.txt",
            }
            self.assertEqual({path.name for path in first.iterdir()}, names)
            self.assertEqual(
                {path.name: path.read_bytes() for path in first.iterdir()},
                {path.name: path.read_bytes() for path in second.iterdir()},
            )
            self.assertEqual(manifest["version"], "0.1.6")
            self.assertEqual(manifest["status"], "development_candidate")
            self.assertEqual(manifest["schema_version"], 2)
            self.assertEqual(manifest["git_repo"], "Francisco-boop-001/CHIM-PrivateConversation")
            self.assertEqual(
                manifest["server_compatibility_reference"],
                "cf5030f15781637498be86debe26fcf102f5690d",
            )
            channel = manifest["channels"]["candidate"]
            self.assertEqual(manifest["default_channel"], "candidate")
            self.assertEqual(channel["label"], "Development candidate")
            self.assertEqual(channel["branch"], "main")
            self.assertEqual(channel["package_source"], "release")
            self.assertFalse(channel["allow_force"])
            self.assertEqual(
                channel["manifest_url"],
                "https://raw.githubusercontent.com/Francisco-boop-001/CHIM-PrivateConversation/main/server/manifest.json",
            )
            self.assertEqual(
                channel["package_urls"],
                ["https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v<version>/private_conversation.tar.gz"],
            )
            self.assertEqual(channel["archive_strip_components"], 1)

            dwpkg = first / "private_conversation-0.1.6.dwpkg"
            PACKAGE.verify_package(dwpkg, ROOT)
            with ZipFile(dwpkg) as archive:
                self.assertEqual(archive.testzip(), None)
                packaged = json.loads(archive.read("server/manifest.json"))
                self.assertEqual(packaged["version"], manifest["version"])
                package_manifest = json.loads(archive.read("manifest.json"))
                self.assertEqual(package_manifest["schema_version"], 4)
                self.assertEqual(package_manifest["server"]["mutable_paths"], ["state"])

            repository = first / "private_conversation.tar.gz"
            PACKAGE.verify_repository_archive(repository, ROOT)
            damaged_tar = scratch / "damaged.tar.gz"
            damaged_bytes = bytearray(repository.read_bytes())
            damaged_bytes[-8] ^= 0x01
            damaged_tar.write_bytes(damaged_bytes)
            with self.assertRaisesRegex(PACKAGE.PackageError, "Could not verify repository archive"):
                PACKAGE.verify_repository_archive(damaged_tar, ROOT)
            with tarfile.open(repository, "r:gz") as archive:
                members = archive.getmembers()
                self.assertEqual(
                    [member.name for member in members],
                    ["private_conversation", *[f"private_conversation/{name}" for name in PACKAGE.SERVER_FILES]],
                )
                extracted = scratch / "extracted"
                extracted.mkdir()
                for member in members[1:]:
                    relative = member.name.split("/", 1)[1]
                    target = extracted / relative
                    target.parent.mkdir(parents=True, exist_ok=True)
                    with archive.extractfile(member) as source:
                        self.assertIsNotNone(source)
                        target.write_bytes(source.read())
            extracted_files = {
                path.relative_to(extracted).as_posix(): path.read_bytes()
                for path in extracted.rglob("*")
                if path.is_file()
            }
            self.assertEqual(
                extracted_files,
                {name: (ROOT / "server" / name).read_bytes() for name in PACKAGE.SERVER_FILES},
            )

            mo2 = first / "private_conversation-0.1.6-mo2.zip"
            member = "CHIM/server-plugins/private_conversation/0.1.6.dwpkg"
            with ZipFile(mo2) as archive:
                self.assertEqual(archive.namelist(), [member])
                self.assertEqual(archive.testzip(), None)
                self.assertEqual(archive.read(member), dwpkg.read_bytes())

            sums = (first / "SHA256SUMS.txt").read_text(encoding="ascii").splitlines()
            self.assertEqual(len(sums), 3)
            self.assertEqual({line.split("  ", 1)[1] for line in sums}, names - {"SHA256SUMS.txt"})
            for line in sums:
                digest, name = line.split("  ", 1)
                self.assertEqual(digest, hashlib.sha256((first / name).read_bytes()).hexdigest())

    def test_release_cli_accepts_a_project_relative_output_directory(self) -> None:
        with tempfile.TemporaryDirectory(prefix=".pcv-release-cli-", dir=ROOT / "tests") as directory:
            output = Path(directory) / "release"
            result = subprocess.run(
                [
                    sys.executable,
                    str(ROOT / "scripts" / "build-package.py"),
                    "--format", "release",
                    "--release-dir", output.relative_to(ROOT).as_posix(),
                ],
                cwd=ROOT,
                capture_output=True,
                text=True,
                check=False,
            )
            self.assertEqual(result.returncode, 0, msg=result.stdout + result.stderr)
            self.assertEqual(
                {path.name for path in output.iterdir()},
                {
                    "private_conversation-0.1.6.dwpkg",
                    "private_conversation.tar.gz",
                    "private_conversation-0.1.6-mo2.zip",
                    "SHA256SUMS.txt",
                },
            )

            package_output = Path(directory) / "relative.dwpkg"
            package_result = subprocess.run(
                [
                    sys.executable,
                    str(ROOT / "scripts" / "build-package.py"),
                    "--format", "dwpkg",
                    "--output", package_output.relative_to(ROOT).as_posix(),
                ],
                cwd=ROOT,
                capture_output=True,
                text=True,
                check=False,
            )
            self.assertEqual(package_result.returncode, 0, msg=package_result.stdout + package_result.stderr)
            self.assertTrue(package_output.is_file())
            PACKAGE.verify_package(package_output, ROOT)

    def test_release_refuses_to_overwrite_a_nonempty_directory(self) -> None:
        with tempfile.TemporaryDirectory(prefix=".pcv-release-preserve-", dir=ROOT / "tests") as directory:
            output = Path(directory) / "release"
            output.mkdir()
            sentinel = output / "keep.txt"
            sentinel.write_text("preserve existing files\n", encoding="utf-8")
            with self.assertRaisesRegex(PACKAGE.PackageError, "new or empty"):
                PACKAGE.build_release(ROOT, output)
            self.assertEqual([path.name for path in output.iterdir()], ["keep.txt"])
            self.assertEqual(sentinel.read_text(encoding="utf-8"), "preserve existing files\n")


if __name__ == "__main__":
    unittest.main()
