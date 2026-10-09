# MO2 metadata fix — approved bounded implementation

User approval: Proceed after the source-only packaging investigation. Keep the existing CHIM sync route; add minimal MO2 metadata. No MO2 UI or installed environment access, no publication, no version/pin advancement.

Base: 1de11b22b7d8603143070f0f884e3c41eb28d054.
Local branch: fix/mo2-metadata-2026-10-08. Use the existing dedicated development checkout, preserving all baseline task notes/untracked files.

- [x] Task 1: minimal deterministic root meta.ini in MO2 builder; update existing meaningful package regression; capture red/green check output. Owner maintenance_state_http: scripts/build-package.py, tests/package_check.py, task-1-report.md and generated evidence within this task only.
- [x] Task 2: clear MO2 installation/download instructions and metadata limits; fix stale upgrade version. Owner maintenance_routing: README.md and task-2-report.md only.
- [x] Task 3: independent package/call-path review and focused artifact verification after Task 1/2 finish. Owner reviewer: task-3-report.md and scratch evidence under this task only. Preflight may inspect unchanged baseline concurrently; no dependent implementation verification until producers finish.
- [x] Lead: inspect every diff, test output, failure paths, protected hashes and unchanged pins; return defects to responsible original agents; record final review.

## Global constraints and acceptance

- Lead writes no product code. Every agent uses Ponytail FULL, preserves shared edits and runs only useful checks. Reuse original agents for fixes. No worker subagents.
- The MO2 ZIP contains exactly the existing CHIM/server-plugins/private_conversation/<manifest-version>.dwpkg and root meta.ini. DWPkg bytes, standalone package and repository TAR remain unchanged for this source revision.
- Generate minimal [General] metadata from the existing manifest version, with version=<version> and validated=true; LF text, no personal MO2 metadata, fake Nexus identity or extra game component. Reuse existing ZIP machinery and deterministic metadata; no dependencies/FOMOD.
- Existing package test checks actual parsed metadata, exact archive members, CRC, unchanged embedded DWPkg, release checksums and reproducibility. Do not add a framework or broad runtime tests for packaging.
- Documentation: one recommended MO2 ZIP download already includes DWPkg; install/enable ZIP, keep CHIM at data root, load a save to sync, verify installed server version. Label DWPkg/TAR as alternative advanced routes and SHA256SUMS as optional verification, never four required downloads. No guarantee version display survives MO2 install; explain validated override and unperformed native install proof. Fix stale 0.1.10 instruction without upgrading distribution contracts.
- Preserve current 0.1.16 and all manifests/pins. Build checks in fresh task scratch, never replace dist/release-v0.1.16 or published assets. No commit/push/release, live modlist/server/database/provider/game changes.

## Dependency / conflict review

| Task(s) | Interface / ownership | Finding |
| --- | --- | --- |
| 1 | builder and existing package test are coupled under one owner | coherent, no duplicate test owner |
| 2 | README consumes fixed metadata/install contract | independent edit; final review waits for actual builder |
| 1 and 2 | documented ZIP content and limitations | shared contract above; disjoint files |
| 3 and 1/2 | reviewer consumes finished diffs and output | sequential final verification, concurrent unchanged preflight only |

Review: accepted; see final-review.md. Package behavior is not MO2 UI, native sync or Skyrim runtime proof.
Task 1 complete: targeted missing-meta RED then GREEN; 4 package checks pass; two candidate builds identical, DWPkg/TAR match original bytes. Task 2 complete: lead returned explicit rename, validation semantics and sync-status wording corrections to original owner, then reviewed corrected README. Independent final artifact review accepted; no blocking findings.
