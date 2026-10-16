# Private Conversation 0.1.18 source metadata and release notes plan

> **For agentic workers:** Metadata/docs owner only. The lead performs staging, commit, push, tag, and publication; the build owner produces package evidence.

**Goal:** Prepare accurate 0.1.18 ALPHA source metadata and install/release documentation for the authorized prerelease.

**Architecture:** Update current README/install instructions, manifest version and description, operator-evidence framing, and a new version-specific distribution note. Keep 0.1.17 PRE-ALPHA history intact; keep package checksums blank until actual builder output is available.

**Tech Stack:** Markdown and JSON; bounded Windows-only source checks.

**Spec:** Root task in the current thread, following the CHIM server-plugin release checklist.

## Global constraints

- Version is `0.1.18`; prerelease maturity is ALPHA.
- Preserve schema, `status=development_candidate`, candidate channel/default/label, compatibility reference, and release/install routes.
- Update current versioned download names and paths; preserve explicit 0.1.17 PRE-ALPHA release history/evidence.
- State actual synthetic, clone/provider, and Skyrim evidence with the END fixture boundary; claim no playback, universal privacy, native-install, sync, or all-route certification.
- Do not edit the package builder, tests, runtime code, CHIM core, or Mind Poisoning.
- No package build, installation, provider/live game/database/server access, commit, tag, push, or publication by this owner.
- Stage only the exact allowlist below; exclude unrelated historical scratch, binaries, and raw dumps.

## Review focus

- Manifest description and README must call this 0.1.18 ALPHA, while the archived 0.1.17 publication remains PRE-ALPHA.
- Every current download name, URL, and installed version must agree on 0.1.18; historical 0.1.17 references must remain historical.
- END success is a confirmed PCV state clear; an unconfirmed clear must report the existing error honestly and may leave the scene active.
- Release-note hashes may be added only from the builder's actual output; fixture evidence must not imply live install, speech playback, or privacy guarantees.
- Installation instructions choose one enabled source, recommend the MO2 ZIP, and describe raw DWPkg/TAR alternatives accurately.

---

### Task 1: Release metadata and docs — owner: evidence_assessment

**Files:** `README.md`, `server/manifest.json`, `docs/operator-acceptance.md`, `distribution/private_conversation-v0.1.18.md`.

- [x] Set manifest version and current README/install paths to 0.1.18; change manifest description to plain ALPHA; preserve all other manifest fields and the 0.1.17 historical label/evidence.
- [x] Add version-specific ALPHA release notes with accurate END wording, evidence levels/limits, one-source install guidance, and no invented checksums.
- [x] Capture pre-edit hashes and write `source-report.md` with the exact source diff and explicit staging allowlist.
- [x] Run bounded JSON, current-link/name, historical-preservation, and whitespace checks; no runtime or package tests from this owner.

### Task 2: Lead/build handoff

**Files:** build-owner package report; hub-owner report; `tasks/release-0.1.18/publication-evidence.md` (after public verification, separate from the initial source commit).

- [x] Metadata owner sends source-diff summary and exact staging allowlist; build owner produces candidate package and focused final-tree checks.
- [x] Lead commits only the explicit source allowlist; build owner rebuilds from the clean committed tree and records exact package hashes.
- [x] Lead pushes the immutable version tag and verifies draft asset downloads against the clean build, then publishes only verified assets.
- [x] Hub owner advances the canonical main/hub snapshot and pointers only after publication; lead verifies public refs and downloads.
- [x] Record checksums and publication evidence only from actual builder and public-download output; keep that later report out of the initial source commit.

## Explicit source staging allowlist

Only these paths are in scope for the initial source commit. Later build/publication evidence is recorded separately after public verification and is not part of this initial allowlist. This list does not authorize this agent to stage them.

- `README.md`
- `distribution/private_conversation-v0.1.18.md`
- `docs/followups/chim-core-delivery-diagnostics-2026-10-15.md`
- `docs/followups/mind-poisoning-subject-diagnostics-2026-10-15.md`
- `docs/logging-revision-2.md`
- `docs/operator-acceptance.md`
- `server/index.php`
- `server/manifest.json`
- `server/preprocessing.php`
- `tasks/alpha-end-2026-10-15/baseline.json`
- `tasks/alpha-end-2026-10-15/docs-report.md`
- `tasks/alpha-end-2026-10-15/end-report.md`
- `tasks/alpha-end-2026-10-15/final-review.md`
- `tasks/alpha-end-2026-10-15/plan.md`
- `tasks/diagnostic-clarity-2026-10-15/baseline.json`
- `tasks/diagnostic-clarity-2026-10-15/docs-report.md`
- `tasks/diagnostic-clarity-2026-10-15/final-review.md`
- `tasks/diagnostic-clarity-2026-10-15/plan.md`
- `tasks/diagnostic-clarity-2026-10-15/preservation-check.json`
- `tasks/diagnostic-clarity-2026-10-15/review.md`
- `tasks/diagnostic-clarity-2026-10-15/ui-report.md`
- `tasks/lessons.md`
- `tasks/release-0.1.18/plan.md`
- `tasks/release-0.1.18/source-report.md`
- `tasks/todo.md`
- `tests/in_game_end_hook_failure_check.php`
- `tests/log_shutdown_context_check.php`
- `tests/ui_logs_host_access_check.php`

All other untracked historical task folders, binaries, archives, raw data, and unrelated files are excluded.
