# Private Conversation 0.1.20 release report

## Status

PCV 0.1.20 remains an ALPHA candidate. The source worktree is based on b7030f259bf25101303f56c03090a060290992a5 and remains uncommitted. The package contract suite passed, and the deterministic working-tree candidate was built and independently inventoried under tasks/release-0.1.20/package-evidence/candidate. This is pre-gate package evidence, not a clean committed-source release build. The source gate has since been accepted; root retains exact staging and commit/tag authority. Nothing was staged, committed, tagged, pushed, published, installed, or advanced in the hub by this release-preparation lane.

## Baselines and boundaries

The PCV source baseline is branch fix/mo2-metadata-2026-10-08 at b7030f259bf25101303f56c03090a060290992a5; origin/main was recorded at the same commit. The last release tag is private_conversation-v0.1.19 at c7ec6cde8231ebb103ef7d443b09789eb4b29691 with root tree dc76ae7ac1551622767135b47487b6b6118aaa43.

The hub checkout was recorded clean at main/origin/main commit 301dce06b7e0559180a38449aaba7ba4551ff4a4; its current PCV subtree is tree dc76ae7ac1551622767135b47487b6b6118aaa43. No hub edits have been made.

The builder allowlist and package assertions require 21 server files, including server/installation_guard.php. The plugin manifest remains ALPHA/development_candidate on the candidate channel, with schema version 2 and compatibility reference cf5030f15781637498be86debe26fcf102f5690d. No CHIM core, Mind Poisoning source, live install, or Skyrim data is in scope. Pre-existing untracked beta, MP, older task, and release evidence remains unselected for staging.

## Runtime preflight and postcheck

The exclusive test-only target was DwemerAI4Skyrim3-test, PHP 8.2.29, with source mounted at /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev. WSL_DISTRO_NAME, uid, temp path, mounted source path, the initial distro state, and Windows-only test/live VHD metadata are recorded in runtime-evidence/preflight.md. The live distro was not entered and no PostgreSQL, server, or provider was started by the preflight.

After the bounded checks, root reported all WSL distros stopped. The Windows-only live VHD metadata remained 184905891840 bytes with LastWriteUTC 2026-10-16T17:33:22.4859084Z. This is metadata evidence only, not a content hash or disk-content verification. No live server or Skyrim data was entered.

## Focused runtime results

### Solo action offer and post-filter

The pre-fix hook fixture exited 1 on the expected solo action assertion. Root's exact RED command and reported output are recorded in runtime-evidence/solo-red.md; no separate raw RED stdout/stderr files were retained. After the scope fix, scene_actions_hook_check.php, scene_actions_check.php, and PHP lint for server/scope.php passed. Raw GREEN stdout/stderr are in runtime-evidence/solo-green.stdout.txt and solo-green.stderr.txt.

The offering fixture narrows synthetic ENABLED_FUNCTIONS and passes context_pre-shaped FUNC_LIST values plus action-schema enum values through pcvSceneActionListAllowed(). It does not invoke CHIM core action reconstruction. The separate post-filter assertion checks that disallowed serialized actions and self-actions targeting another NPC are removed. This is focused PCV fixture proof, not full CHIM/SHARMAT action dispatch or generated-action proof.

### Duplicate-copy owner and inactive HTTP boundary

The accepted duplicate command ran in the test-only distro with a task-owned TMPDIR: set -eu; assert WSL_DISTRO_NAME; change to the mounted source; create TMPDIR with mktemp; unset PCV_LOG_DIR; lint tests/duplicate_install_check.php; then run tests/duplicate_install_check.php. The recorded exit was 0. Raw output is runtime-evidence/duplicate-green-layout.stdout.txt and duplicate-green-layout.stderr.txt.

The fixture passed new/new owner election in both hook load orders, exact 0.1.19/new mixed installs in both physical path placements and orders, generated Plugin Manager identity and single-owner election, and fail-closed ambiguous paths. It exercised the elected owner's actual pcv_stage() ARM, pcv_read(), END, and readback through the hook-loaded state API and verified the owner-path state store. Separate loopback requests confirmed that inactive POST /index.php, GET /index.php?refresh=1, and GET /index.php?view=logs return HTTP 409, identify the owner, preserve seeded owner state, and create no inactive store. CLI diagnostics also identify the owner.

The state exercise is compositional proof through the actual state API, not ARM/END POSTs through the owner Plugin Page. The guard returns before full CHIM database/catalog bootstrap. One unguarded pre-0.1.20 copy remains preferred over a new copy so the old copy can continue owning during an update. Two unguarded 0.1.19 copies can still collide even if a guarded 0.1.20 copy is present; upgrade/replace or remove extra old copies until only one owner remains. No automatic state migration occurs.

### Bounded guard regression and final syntax/turn-hook check

The corrected bounded regression invocation was: wsl.exe --distribution DwemerAI4Skyrim3-test --exec /bin/sh -lc 'set -eu; test "$WSL_DISTRO_NAME" = DwemerAI4Skyrim3-test || exit 97; cd /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev || exit 98; run_root=$(mktemp -d /tmp/pcv-020-regressions.XXXXXX); export TMPDIR="$run_root"; unset PCV_LOG_DIR; printf "RUNTIME_TMPDIR=%s\n" "$TMPDIR"; php -l server/json_response_custom.php; php tests/page_check.php; php tests/scope_check.php; php tests/postrequest_terminal_check.php; php tests/reflection_ack_shutdown_check.php; php tests/turn_hooks_check.php'. It exited 255 at reflection_ack_shutdown_check.php's guarded precondition because no disposable PostgreSQL target was configured; the fixture refused before a connection. The three preceding behavior fixtures page_check.php, scope_check.php, and postrequest_terminal_check.php passed. Exact raw streams are runtime-evidence/guard-regressions-corrected.stdout.txt and runtime-evidence/guard-regressions-corrected.stderr.txt.

The postrequest terminal fixture's stderr also contains a logger directory_unavailable warning. It is retained in the same stderr receipt and remains unattributed; do not describe that stderr as clean. Root reports no database connection was attempted. The ACK shutdown behavior itself remains NOTRUN.

A separate final command exited 0. It asserted the test distro and mounted source, set a task-owned TMPDIR, unset PCV_LOG_DIR, linted all 11 touched product PHP files plus tests/reflection_ack_shutdown_check.php, then ran tests/turn_hooks_check.php. The output is runtime-evidence/guard-final.stdout.txt and runtime-evidence/guard-final.stderr.txt. This confirms syntax and bounded turn-hook behavior, not ACK shutdown behavior or full CHIM runtime.

Earlier harness failures remain preserved in runtime-evidence. They are not overwritten by the accepted final duplicate/guard receipts.

## Package contract and working-tree candidate

Host Python was Python 3.12.4. The exact package test command was py -3 tests/package_check.py; exit 0, four tests passed. Raw streams and command receipt are package-evidence/package-check.stdout.txt, package-check.stderr.txt, and package-check.run.json.

The exact candidate command was py -3 scripts/build-package.py --format release --release-dir tasks/release-0.1.20/package-evidence/candidate; exit 0. Builder output and receipt are package-evidence/package-build.stdout.txt, package-build.stderr.txt, and package-build.run.json. The output folder was new and task-owned; dist was not changed.

The independent inventory command was py -3 tasks/release-0.1.20/package-evidence/inspect_candidate.py; exit 0. Its raw output and receipt are package-evidence/candidate-inventory.stdout.txt, package-evidence/candidate-inventory.stderr.txt, and package-evidence/candidate-inventory.run.json. It confirmed each archived server payload byte equals the current server source, the plugin manifest says version 0.1.20/status development_candidate/channel candidate, the standalone DWPkg has 23 members including 21 server files, wrapped TAR has 22 entries (root plus 21 files), the MO2 ZIP has 2 entries and embeds byte-identical DWPkg, the flat TAR has 21 files, and all four SHA256SUMS lines match.

| Candidate asset | Bytes | SHA-256 | Members |
| --- | ---: | --- | ---: |
| private_conversation-0.1.20.dwpkg | 3475090 | d5da301dd332d45a97e962da96c8f0af9269d4d402e2083fe7a221e921090e8c | 23 |
| private_conversation.tar.gz | 3053559 | e949fe37d1e7fb2f8091e28f0c4af106ca1039a15809401082ea460572798bb8 | 22 |
| private_conversation-0.1.20-mo2.zip | 3056680 | 7bd8c4e67e970416c116965a0470328a48e7ca4921750fafabf759a2826290db | 2 |
| chim-plugin.tar.gz | 3053486 | 50405eca2d8a7b6e7dc9a307740b6ab0518366e21cfcbc743b88e88d94cbcd7b | 21 |
| SHA256SUMS.txt | 381 | e51d7d230b586ad3f544084b60ad40e4a7fe4fc830d0e35b12061977ad056fee | 4 lines |

These are working-tree candidate outputs and will need a clean accepted-source rebuild and byte-for-byte comparison after the source gate. No asset was uploaded or published.

## Remaining limits and next gate

Full CHIM bootstrap/schema refresh, the owner Plugin Page's ARM/END POST flow, provider/database behavior, public ACK scheduling, native recovery, client sync, and Skyrim gameplay remain unverified. The ACK shutdown behavior was not run because its guarded disposable-database target was unavailable. One retained logger warning remains unattributed. This remains ALPHA evidence only.

The exact source staging allowlist and source-hash manifest are prepared. Root owns staging, commit/tag, clean export, public asset/fresh-download comparison, publication, and PCV-only hub/path/mode/blob proof. No CHIM core or Mind Poisoning updates are part of this release lane.
