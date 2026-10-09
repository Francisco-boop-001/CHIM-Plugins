# Independent package review — MO2 metadata fix

## Phase 1: preflight

Read-only preflight completed against base `1de11b22b7d8603143070f0f884e3c41eb28d054` on `fix/mo2-metadata-2026-10-08`. I changed no product files or release assets. The package test was already modified by its owner, so implementation review was deferred at that stage; committed test behavior below was read from `HEAD`.

The builder flow is `build_release` → standalone DWPkg, repository TAR, and MO2 ZIP, then SHA256SUMS. `build_mo2_sync_archive` currently builds the DWPkg from the server manifest and embeds its bytes at `CHIM/server-plugins/<name>/<version>.dwpkg`. The committed release regression checks exact release members, repeat-build byte equality, DWPkg and TAR validity, one-member MO2 ZIP membership, embedded/standalone DWPkg equality, and release hashes. The new check should retain those consumers while asserting the metadata from the actual ZIP.

Read-only release baseline from `dist/release-v0.1.16`:

| Asset | SHA-256 |
| --- | --- |
| `private_conversation-0.1.16.dwpkg` | `5D604A99701D080B264DBDEE96FF6D3E3B8735CFA3745B1D768150F988DA353E` |
| `private_conversation.tar.gz` | `9F21CA0128DCE2E7D69B7AB222A3F1EA91C1C4A6AA8FDA5716FCE5F41AB30793` |
| `private_conversation-0.1.16-mo2.zip` | `F9EE5EB4C3A7A0F5B516DFB6D035BA31CD0F78AA1C99E539C2BD38C8A8FAAC90` |

The existing MO2 ZIP has exactly one member, `CHIM/server-plugins/private_conversation/0.1.16.dwpkg`; its 3,463,704 bytes match the standalone DWPkg and its SHA-256. Prior source reports establish that `meta.ini` is excluded from MO2's installed data tree and `validated=true` controls the false invalid-data flag. They also leave persistence through install/update unverified and note that MO2 may replace the displayed version.

## Phase 2 acceptance checks (completed)

- The generated ZIP has exactly the versioned CHIM DWPkg and root `meta.ini`, valid CRCs, stable bytes across builds, and release checksums that match the produced files.
- Parse the actual metadata and require `[General]`, manifest-derived `version`, and `validated=true`; check LF text and reject CR bytes or extra/personal/game-component metadata.
- Confirm the embedded DWPkg equals the standalone DWPkg and retains the baseline hash; confirm the repository TAR retains its baseline hash. Keep generated files in fresh task scratch.
- Review README wording against the source-only boundary: the change creates a new source candidate, does not rewrite an already downloaded/published 0.1.16 ZIP, and does not prove MO2 install/update persistence or UI behavior.

During Phase 1, no dependent implementation checks were run. Phase 2 used the completed candidate files read-only. MO2 UI/install, live CHIM/server/database/provider, and Skyrim runtime remain outside this review.

## Phase 2: independent review

**Verdict: accept; no blocking findings.** The product diff changes only the MO2 wrapper and its existing package regression, plus the install/update section of `README.md`. The builder derives the version from the validated server manifest, emits only root `meta.ini` and the existing versioned DWPkg, fixes ZIP metadata for both members, and checks exact membership, CRC, metadata bytes, and unchanged DWPkg bytes before replacing the output. The existing `build_release` path still produces the standalone DWPkg and repository TAR and hashes all three release assets. The test uses stdlib `configparser` against the actual ZIP and checks exact bytes, which also rules out CRLF and extra metadata.

The current README now recommends one MO2 ZIP, says it already includes the DWPkg, gives the save-load and **Server Plugins → Automatic Game Plugin Sync → Refresh Status** path, labels DWPkg/TAR as advanced alternatives and sums as optional, and includes the required rename for direct DWPkg sync. It explicitly says the published 0.1.16 ZIP predates this source change, explains `validated=true`, and preserves the MO2 version/UI uncertainty. The current link still targets that unchanged public ZIP; the README does not claim it contains the new metadata. No documentation defect found against the bounded plan.

Independent artifact check was read-only: no rebuild and no package test suite rerun. Exact output:

```text
candidate-a: members=['meta.ini', 'CHIM/server-plugins/private_conversation/0.1.16.dwpkg']; CRC=PASS; metadata=PASS; embedded_dwpkg=baseline; release_sums=PASS; standalone_sha256=5d604a99701d080b264dbdee96ff6d3e3b8735cfa3745b1d768150f988da353e; tar_sha256=9f21ca0128dce2e7d69b7ab222a3f1ea91c1c4a6aa8fda5716fce5f41ab30793
candidate-b: members=['meta.ini', 'CHIM/server-plugins/private_conversation/0.1.16.dwpkg']; CRC=PASS; metadata=PASS; embedded_dwpkg=baseline; release_sums=PASS; standalone_sha256=5d604a99701d080b264dbdee96ff6d3e3b8735cfa3745b1d768150f988da353e; tar_sha256=9f21ca0128dce2e7d69b7ab222a3f1ea91c1c4a6aa8fda5716fce5f41ab30793
candidate-a == candidate-b for all four release files: PASS
old standalone DWPkg and repository TAR unchanged: PASS
```

The artifact check parsed `[General]` and required exactly `version=0.1.16` and `validated=true`, checked exact LF bytes with no carriage returns, exact member order/list, ZIP CRCs, per-file release sums, identical outputs across both candidates, embedded DWPkg byte equality to the pre-change release, and unchanged standalone DWPkg/TAR bytes. MO2 install/update persistence, warning-free UI behavior, and CHIM/game runtime were not tested or inferred from archive proof.
