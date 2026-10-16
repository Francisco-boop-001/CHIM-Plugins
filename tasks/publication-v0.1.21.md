# Mind Poisoning 0.1.21 — publication verification

Published as **BETA CANDIDATE — experimental**. The user expressly chose a standard GitHub release (`draft=false`, `prerelease=false`) so current CHIM topic discovery can find it. This classification does not certify beta readiness.

## Source and public release

- Source commit: `399427765cfbc49e47eb3f11f959413805fb8f26`.
- Annotated tag: `mind_poisoning-v0.1.21`; tag object `a3d6348baceec880ee9038df0d4c99849d7fcb4d`.
- [Public release](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/tag/mind_poisoning-v0.1.21), release ID `415379781`.
- Draft downloads and fresh public downloads of all five assets equal the clean tag builds byte for byte. Asset size/digest metadata, exact release title/body, latest-release selection, public repository/topic and older 0.1.20 release identities were independently checked.
- The public release body uses absolute tag-pinned documentation URLs; the source release note retains its repository-relative links.

## Verification

- Six focused PHP behavior checks passed from the clean staged export: relocated API/PCV loader/version parsing, the real index/dashboard access boundary, structured logging, dashboard reads, observer/importer and legacy manifest/update helpers. The observer check explicitly skipped its POSIX UID-dependent JSONL sink on Windows.
- Eight packaging tests, seven changed/new PHP syntax checks and Python compile checks passed. The pinned native SQL fixture remained byte-exact after Git export.
- The annotated tag rebuilt the same four installers and checksum file as the executed staged source. GNU tar extracted both the flat archive and the legacy strip-one archive to the exact 19 allowlisted server files.
- Actual pinned current CHIM discovery helpers accepted the stable-release asset, archive listing and extracted tree, preserved an attributable legacy folder, selected the repository folder for a fresh install and generated the exact tag/index URL. This was offline helper execution, not a Plugin Manager installation.
- Current discovery source: `f9cb8eb101c5643245a9a1d4c42216979b7fcde5`; `lib/plugin_discovery.php` SHA-256 `a39ce2f099e9a659ea0f175208b9319833b7aeae7e58b767e0970ed714007e0c`.
- 14 existing server files, including evaluation, persistence, reflection and request handling, are byte-identical to 0.1.20. Runtime changes are the declaration-only globals entrypoint, dashboard index handoff and exact release-tag normalization for logs.
- Previously completed disposable campaigns remain bounded evidence for 0.1.20: ten native PostgreSQL persistence cases and nine server install/upgrade/backup/restore cases. They were not repeated for this publication. See [candidate evidence](../docs/beta-candidate-evidence.md).
- All 414 tracked protected source files and 17 private untracked evidence files matched their release baselines. Older tags/assets were retained; no live server, gaming distro, provider, database, modlist or game installation was used during publication.

## Published assets

| Asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `mind_poisoning-0.1.21-mo2.zip` | 639605 | `dde6ed969cf9e4896ec2cfe295a841cc8ed31695c607da09d0da32be750df596` |
| `mind_poisoning-0.1.21.dwpkg` | 984485 | `2fb63bf9fe1c2767884f4a4f68a5a13e18c95f4224d4e45100d2915084686995` |
| `mind_poisoning.tar.gz` | 637492 | `4d1341d106231f616cf291491339f2a90365c72417367dcad0662d5afa74511a` |
| `chim-plugin.tar.gz` | 637449 | `d42dd03a92e80e26b466aa75560b61d1b0c2fade4591b111059fbf4cf862b26e` |
| `SHA256SUMS.txt` | 363 | `f2b7fe9f465047a04018e220ccc93a864d59e000b9bae85b2c9bc01da67f4188` |

## Remaining gates

An end-to-end installation through the newer CHIM site, current provider/client relationship-type behavior, full installed companion integration, real latency/cost/timeout recovery, cold operator reproduction and MO2/native sync remain unproven by these release checks. Earlier live affinity observations remain version-specific evidence. The release is a gameplay-test candidate; backup the CHIM database/configuration and keep one installation route active before testing.
