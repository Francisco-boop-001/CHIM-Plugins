# Private Conversation 0.1.20 independent source/package review

Read-only review of the PCV checkout at `b7030f259bf25101303f56c03090a060290992a5`, against the immutable 0.1.19 release commit/tag `c7ec6cde8231ebb103ef7d443b09789eb4b29691`. The source-contract review and candidate-archive inspection are separate from runtime testing and release approval.

## Findings

- **Follow-up to the static fixture finding:** the current shared worktree version of `duplicateSetPluginManagerManifest()` now reads the copied source manifest's numeric version and builds the corresponding full tag (`tests/duplicate_install_check.php:76-87`). The `.19` fixture therefore receives `private_conversation-v0.1.19`, while the `.20` copy receives `private_conversation-v0.1.20`; the mixed-case scenarios no longer mislabel the legacy payload. This resolves the source-level finding. Root separately ran and accepted the duplicate-green-layout and guard-final checks with exit code 0; raw output is retained in `tasks/release-0.1.20/runtime-evidence/duplicate-green-layout.stdout.txt`, `duplicate-green-layout.stderr.txt`, `guard-final.stdout.txt`, and `guard-final.stderr.txt`. I did not execute those runtime checks.
- The explicit runtime allowlist in `scripts/build-package.py:28` contains `installation_guard.php` once. `tests/package_check.py:47` asserts that `server/installation_guard.php` is present in the package. The hook/UI entrypoints call the guard before registering hooks or loading mutable state.
- The bundled/internal discovery manifest at `server/manifest.json` declares `name=private_conversation`, `version=0.1.20`, `schema_version=2`, `status=development_candidate`, and `git_repo=Francisco-boop-001/CHIM-PrivateConversation`. Its compatibility reference is unchanged from 0.1.19: `cf5030f15781637498be86debe26fcf102f5690d`. The DWPkg builder separately emits root package schema 4 and preserves only `state` as mutable (`scripts/build-package.py:42, 79-89, 122-124`). These are distinct manifest contracts.
- The generated Plugin Manager identity fixture has `name=CHIM-PrivateConversation`, full tag `version=private_conversation-v0.1.20`, exact `git_repo=Francisco-boop-001/CHIM-PrivateConversation`, `source=github-release`, and `generated_by=CHIM Plugin Manager`. The retained consumer code writes `name` from the physical `$packageName`, `version` from the full release tag, and `git_repo` from the repository identity; these generated fields are separate from the bundled/internal manifest (`name=private_conversation`, numeric version `0.1.20`, schema 2). The guard accepts this generated shape only when `name` matches the physical directory, the repository and source match exactly, and the version parses after removing the full tag prefix (`server/installation_guard.php:58-73`).
- I checked the retained consumer snapshot's `chimPluginGeneratedManifest()` implementation at `C:\Users\Francisco Estaba\AppData\Local\Temp\chim-plugin-review-f9cb8eb-b22979c4aedd4a0fb5389b62942a67b7\lib\plugin_discovery.php:1029-1071`. It removes bundled reserved identity fields, then writes `name` from `$packageName`, `version` from the full `$source['tag']`, and `git_repo` from `$source['repo']`, with `source=github-release`. Snapshot SHA-256: `A39CE2F099E9A659EA0F175208B9319833B7AEAE7E58B767E0970ED714007E0C`. This is a retained review snapshot, not proof of the currently installed CHIM core revision. The separate identity fixture carries the fields PCV consumes. The test helper overlays those fields onto the copied internal manifest, so its merged test file is a focused identity fixture rather than a byte-exact generated Plugin Manager manifest.
- The legacy fixture tree `tests/fixtures/duplicate-install/legacy-0.1.19/server` contains exactly the 20 `server/` paths from the 0.1.19 release tree. Raw blob IDs matched `git rev-parse <tag>:server/<path>` against `git hash-object --no-filters <fixture-path>` for all files: 20 checked, 0 missing, 0 extra, 0 mismatches.
- The PCV checkout's tracked diff is scoped to PCV. The separate Mind Poisoning checkout currently has its own modified and untracked files; it was left untouched. Existing `tasks/beta-*` and Mind Poisoning work remain outside this review's staging scope.

## Limits

Per assignment, I did not run PHP, tests, package builds, WSL/distro commands, installation, or game/runtime checks, and did not stage or alter product files. Root's separately recorded runtime checks are limited to their captured scenarios; neither they nor the package audit establish DB/ACK behavior, full-core compatibility, live installation, or gameplay.

## Candidate package audit

I inspected the prepared candidate assets read-only with Python 3.12.4 and `tasks/release-0.1.20/package-evidence/independent-review/inspect_candidate.py`; the exact invocation is saved alongside the script. The inspection completed at `2026-10-16T23:22:02.724746+00:00`, and its machine-readable receipt is `tasks/release-0.1.20/package-evidence/independent-review/candidate-inspection.json`. All 29 checks passed against the 21-file explicit allowlist. This verifies archive bytes and metadata against the current allowlisted source snapshot; it does not build or install the package.

The candidate directory contains exactly these four assets and `SHA256SUMS.txt`, whose four rows match the asset bytes:

| Asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `chim-plugin.tar.gz` | 3,053,486 | `50405eca2d8a7b6e7dc9a307740b6ab0518366e21cfcbc743b88e88d94cbcd7b` |
| `private_conversation.tar.gz` | 3,053,559 | `e949fe37d1e7fb2f8091e28f0c4af106ca1039a15809401082ea460572798bb8` |
| `private_conversation-0.1.20.dwpkg` | 3,475,090 | `d5da301dd332d45a97e962da96c8f0af9269d4d402e2083fe7a221e921090e8c` |
| `private_conversation-0.1.20-mo2.zip` | 3,056,680 | `7bd8c4e67e970416c116965a0470328a48e7ca4921750fafabf759a2826290db` |

The DWPkg has 23 exact members, schema 4 at the root and schema 2 in its internal plugin manifest, both versioned 0.1.20; its checksum table and CRCs pass and all 21 `server/` payloads match the inspected source bytes. The wrapped TAR has 22 members including its top-level directory, while the flat TAR has exactly 21 payload members; both match source bytes, pass gzip integrity, and use the expected deterministic TAR metadata. The MO2 ZIP has exactly root `meta.ini` plus the inner DWPkg, and that DWPkg is byte-identical to the standalone asset. ZIP timestamps, TAR/gzip timestamps, ownership/modes, and LF/UTF-8 text inputs match the builder contract. The 20-file `.19` fixture still matches raw Git blobs from the immutable release tag.

**Package candidate result: PASS (static archive inspection only).**
