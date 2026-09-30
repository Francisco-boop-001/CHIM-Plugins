# PCV v0.1.4 release packaging

- [x] Read the CHIM plugin release checklist and reuse the existing schema-4 DWPkg and sibling repository/MO2 archive formats.
- [x] Add a failing focused package test for candidate metadata, deterministic multi-format output, checksums, and extracted allowlist.
- [x] Update the nested candidate manifest to the tag-specific v0.1.4 route without changing the schema-4 mutable-state contract.
- [x] Extend the existing builder with deterministic repository tar, plain MO2 sync ZIP, and a fresh release-folder/SHA256SUMS build.
- [x] Run focused packaging checks, archive extraction/integrity checks, Python syntax checks, and both reflection-fixture layouts; preserve all prior artifacts.
- [x] Record exact files, output hashes, and source/runtime limitations for lead review.

## Review

The fresh `dist/release-v0.1.4/` folder contains the schema-4 DWPkg, a one-wrapper `private_conversation/` tarball for the repository installer, a single-member MO2 sync ZIP, and `SHA256SUMS.txt`. The tar uses the installer’s one-component strip setting. The DWPkg retains the 17-file runtime allowlist and `mutable_paths: ["state"]`; no prior `dist/` artifact was replaced.

SHA-256 values from the verified release folder:

| Asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `private_conversation-0.1.4.dwpkg` | 3,235,929 | `9bacc5a101131f7466473bfb0abd29d44c49caf469e4e8fed705f02ed81a62d2` |
| `private_conversation.tar.gz` | 3,003,803 | `07513924e72ad9a405dafe94cfe39bd36b6231dffb3f252f66fc5b4ca619dc51` |
| `private_conversation-0.1.4-mo2.zip` | 3,005,917 | `26d5cec6de0d1bffa2cc167c7ee7c6fe0f583e5d4f5867a5f7d449f6ea1afd2a` |

Verification: `python tests/package_check.py` passed (4 tests); Python AST and manifest JSON validation passed; direct verification of the release-folder artifacts passed for package allowlist/checksums, tar payload/member layout, and MO2 ZIP member/content. The registry fixture passed in the original sibling checkout and a clean simulated public layout at `plugins/private_conversation/` (both PHP runs printed `PCV reflection registry checks passed.`). Relative CLI output paths are covered for both release folders and single DWPkg output. Historical 0.1.0–0.1.3 packages were left untouched.

The candidate manifest pins compatibility metadata to installed server revision `cf5030f15781637498be86debe26fcf102f5690d`. Installer layout was checked against read-only sources at that installed revision (`ui/server_plugins.php` and `ui/server_plugin_installer.php`); the public modders-guide page could not be reached during this pass. These are source/package checks only: no live CHIM installation, game, database, provider, or runtime behavior was exercised, and the candidate has no in-game acceptance claim.

Changed source files: `scripts/build-package.py`, `server/manifest.json`, `tests/package_check.py`, and `tests/reflection_registry_check.php` (the last only resolves the MP test fixture in either sibling or nested-public layout). Generated assets are confined to `dist/release-v0.1.4/`.
