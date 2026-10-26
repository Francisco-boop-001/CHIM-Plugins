# Private Conversation Mode Worklist

## Solo reflection and Mind Poisoning integration — 2026-09-30

- [x] Pin 0: freeze reviewed runtime/state/influence contracts and implementation plan.
- [x] Pin 1: backward-compatible solo state, unprofiled identity, diagnostic classification and reusable reflection influence foundation.
- [x] Pin 2: Standard-only coherent runtime hooks, audible solo delivery, pair integration and solo page controls.
- [x] Pin 3: review every owned diff and focused verification; document source maturity and remaining live gates.

User preapproved design/plan and multiple-agent implementation. Evidence and rulings: `tasks/implementation-2026-09-30/progress.md`. Prior pins below remain historical and do not approve the new changes.

- [x] **0 Contract:** Publish `docs/spec.md` and `docs/plan.md`; lead reviewed and approved the exact interface and constraints.
- [x] **1 Foundation:** State source, isolated fixture, and additive `pending_scope` disclosure for staged ARM/pair/END details are lead approved at source/isolated-fixture level. This does not certify runtime or integration behavior.
- [x] **2 Hooks/UI:** Lead approved at source, isolated-fixture, and rendered-preview level. Lead independently ran `ui_check` and `scope_check` (exit 0), reviewed all changes and desktop/mobile previews, and received PHP lint success for all six routing-owned files. This does not verify installed deployment authentication, live connector behavior, or in-game behavior.
- [x] **3 Package/review gates — development candidate:** Lead approved source/package review. `dist/private_conversation-0.1.0.dwpkg` is 3,514,293 bytes, SHA-256 `58f907cbe44881571226a78c8f7f4e39136c7897710e179f6b29aacc9f89d66a`; archive membership, source checksums, CRC, and schema-4 mutable-state preservation passed. No install, publish, or deployment.

## Review

Final lead verdict: extension-only development candidate approved at source/package level. Stage 0-3 contract, source, focused-fixture, rendered-preview, and package gates passed. Installed deployment authentication, live connector behavior, in-game behavior, and cross-extension ordering remain unverified; this is not runtime certification.

## Operational logging follow-up

- [x] **0 Logging contract:** `docs/logging.md` records the fixed event envelope, no-content policy, rotation bounds, debug expiry, private per-install/effective-user temporary default, and trusted `PCV_LOG_DIR` override; lead approved the contract and sink policy before product edits.
- [x] **1 Logger/state foundation:** Lead approved `server/log.php`, optional persisted state `config_id`, and isolated logger/state fixtures after independent source review and rerun; both fixtures and all four PHP lint checks pass.
- [x] **2 Hook/UI events:** Lead approved the hook/UI/CLI implementation after source review, focused checks, lint, and isolated page-flow regressions covering GET failure, concurrent readback, and unavailable readback.
- [x] **3 Package/review — logging update development candidate:** Lead approved `dist/private_conversation-0.1.1.dwpkg` at source/isolated-fixture/package level after independent verification of exact archive membership/source bytes, checksums, CRC, schema-4 state preservation, and the package check. Size: 3,584,087 bytes; SHA-256: `afa1811daf5b027f75b94f16808e8af1bc7d1b013a7fc58a0b2e5b7330f2b704`. No live install/deployment or runtime certification.

### Logging review

The logging contract, sink policy, and Pins 1-3 are lead approved at source, isolated-fixture, and package level for the `0.1.1` development candidate. The earlier `0.1.0` artifact and approval remain historical and unchanged. This does not certify a live install, server authentication, connector behavior, or in-game behavior.

## Follow-up: picker eligibility (open)

- [x] **Routing/foundation owner:** Implement and fixture-test `pcv_read_eligible_npcs()` with the frozen result shape. Cache only bounded game-presence data by validated playthrough; join snapshot names to exactly one current normalized catalog name and return `core_npc_master.id => canonical name`. Lead approved the source and isolated eligibility/state fixtures; installed CHIM Beta binary equivalence remains unverified.
- [x] **UI owner:** Load current catalog rows and call the shared helper on GET and POST. Render only its current eligible map, distinguish valid empty from missing/stale/unavailable, and disable ARM until two IDs are eligible. END still requires identity and CSRF but bypasses catalog/presence. Lead reviewed the page source and isolated page flow.
- [x] **Routing/UI integration:** At POST, stage only IDs in the helper's current map; on pending enable, revalidate both actors against that eligible Standard input's own raw snapshot. END bypasses actor availability; off scope stays inert. Do not use runtime RefIDs as persistent pair identity or resolve duplicate/missing catalog names. Lead approved source and isolated fixtures; no runtime equivalence is claimed.
- [x] Focused helper, activation, and UI checks pass: eligibility helper fixtures; helper-map rendering; empty/missing/stale copy; fewer-than-two ARM rejection; stale POST reason logging; END without catalog data; pending-activation and rechat-expiry state fixtures.
- [x] Build package `0.1.2`: `dist/private_conversation-0.1.2.dwpkg`, 3,612,956 bytes, SHA-256 `dc336c05902dcf583edb83242c74676244c65ff642ae26c49b3fa44e98ce374a`. Builder verification, direct `verify_package`, and the one-test `package_check.py` pass; schema 4 preserves mutable `state/`.
- [x] Lead package review for `0.1.2`: approved at source/isolated-fixture/package level after independent archive membership/source-byte/checksum/CRC/schema-4 mutable-state verification and `package_check.py` PASS. Preserve `0.1.1` as the approved prior development artifact. No install, publish, or runtime certification.

### Picker UI review

Lead reviewed `server/index.php` and the isolated page-flow scenarios; `tests/ui_check.php` and `tests/page_check.php` pass, as do PHP syntax checks for the controller and both UI tests. This closes the UI source gate. Routing/UI activation integration was later approved at source and isolated-fixture level. Package `0.1.2` was built and approved after builder verification, direct `verify_package`, `package_check.py`, and independent archive verification. Package `0.1.1` is unchanged.

## Follow-up: autonomous presence refresh (package approved; runtime pending)

- [x] Verify the pinned native producer and inspected server command path. No generic server-triggered roster query was found; the fixed `ScriptProxy` has no roster call. Installed ShARMAT does emit active OStim/SexLab scene-state events without player chat, but not a general nearby-and-managed roster.
- [x] Inspect installed CHIM Beta and ShARMAT Alpha game-side sources. CHIM's five-second timer sends cell/location data; later source tracing also found a separate greater-than-eight-second `infonpc_close`/`activity_status_bulk` report. That feed lacks exact per-actor distance/effective sneaking radius and an empty-batch clear, so it does not satisfy picker eligibility; see [docs/server-only-presence-audit.md](../docs/server-only-presence-audit.md). ShARMAT's scene events carry active scene participants; its repeated timer is for touch/grab data. Compiled PEX/DLL equivalence remains unknown.
- [x] Record evidence, the existing ShARMAT scene-event path, and bounded future producer options in [docs/autonomous-presence-verification.md](../docs/autonomous-presence-verification.md).
- [x] User authorized a separate additive game-side companion and page refresh. Routing and lead approved the `ext_pcv_presence` JSON contract; installed mods remain look-only.
- [x] Implement the bounded managed-and-nearby Papyrus publisher source and compile `PCVPresenceAlias.pex` with Caprica 0.3.0 strict checking and warnings-as-errors. Source uses a five-second load settle, location-change refresh, and 15-second timer; it emits empty snapshots and fails closed on overflow or unusable eligible names.
- [x] Build and structurally verify the additive start-game-enabled ESP quest, forced PlayerRef alias, VMAD binding, and SEQ. Mutagen readback and independent raw inspection verified one quest, one forced PlayerRef alias with `PCVPresenceAlias`, and no overrides; ESP 381 bytes, SEQ 4 bytes, PEX 4,834 bytes. Installed CHIM and ShARMAT remain untouched.
- [x] Integrate the server consumer and page refresh at source/isolated-fixture level. Lead approved routing and UI integration after the custom event test plus existing eligibility/state/scope/log and UI/page checks; the page polling JavaScript and PHP flows pass. This is not an in-game runtime or package check.
- [x] Build and review separate server and companion `0.1.3` artifacts. Server `dist/private_conversation-0.1.3.dwpkg`: 3,629,407 bytes, SHA-256 `ce0fe4bb99979c12313a60c2a051fce7cf5bd6d54c064fd52fbccbb3cb4767ee`. Companion `dist/private_conversation-companion-0.1.3.zip`: 5,661 bytes, SHA-256 `4a7d1f47719cfa4e4a94e8eeca51d7336529f477dffb7dfba8fdc483ee318e17`. Lead independently approved archive/source/CRC/schema-4/preservation review. Preserve `0.1.2` unchanged; no install or runtime test.

### Protocol gate checklist

- [x] Confirm the Papyrus `logMessage` event framing, one-way native API, and server event field mapping. The native emits a separate `infonpc` event per log call; source and isolated fixtures verify separate handling before core dialogue, but live ordering remains untested.
- [x] Confirm managed actor enumeration (`findAllAgents()`), Papyrus actor distance, and the configured Standard interior/exterior base radius with sneaking half. Exact compiled-runtime behavior remains unverified.
- [x] Locate Caprica 0.3.0 plus Skyrim flags, Creation Kit, and SSEEdit in the scoped local authoring workspace; do not install tools or write to installed mods.
- [x] Record payload, 15-second cadence, 32-actor overflow rule, empty-report behavior, and identity/timestamp limits in `docs/autonomous-presence-protocol.md`.
- [x] Record the successful strict PEX compile, exact SKSE/CHIM source import paths, and compile-only support import provenance in `companion/README.md`.
- [x] Verify quest/alias/plugin/SEQ structure independently; PEX compilation alone is not a runnable companion.
- [ ] Run manual game acceptance only after both package gates: no-chat automatic roster refresh while standing still; roster updates as managed NPCs enter/leave radius or AI deactivates; remain idle over 45 seconds; reload the save; confirm no unsolicited dialogue.

### Autonomous refresh review

Papyrus source/strict PEX compile, additive quest/alias structure, server/UI source/fixture integration, and both separate `0.1.3` package gates are approved. Manual game acceptance remains unchecked; installed CHIM DLL equivalence and in-game behavior are unverified. No live install, endpoint, or database was queried or changed.

## Server-only background-feed sufficiency audit — source checks complete

- [x] Trace source cadence: CHIM's idle loop emits a report after more than eight seconds without requiring chat, subject to game-loop conditions. This is source evidence only; runtime delivery is unverified.
- [x] Trace native payload and server/UI routes. A report exists, but it lacks the actor distance/effective sneaking radius and an authoritative empty activity batch; CHIM MCM and ShARMAT picker/log refreshes are not server roster endpoints.
- [x] Compare the existing acceptance matrix: idle feed, radius/sneaking, managed-state changes, location/save changes, empty set, delayed/out-of-order events, and browser-only polling. See [docs/server-only-presence-audit.md](../docs/server-only-presence-audit.md).
- [x] Preserve approved artifacts. Server package and companion ZIP hashes remain unchanged; all 13 packaged server source files still match the approved server archive.
- [ ] Manual runtime checks remain unperformed: silent idle beyond 45 seconds; actors crossing the effective Standard radius while sneaking and not sneaking; CHIM AI activation/deactivation; cell/worldspace/save switches; empty managed set; delayed events; and no unsolicited dialogue.

### Audit review

Lead approved the source-level verdict after review of the corrected matrix: the existing no-chat feed is real but insufficient for the exact configured-radius/sneaking picker rule; the server's per-NPC projection does not clear on empty batches, and its future-timestamp freshness behavior is unverified at runtime. All 13 packaged server files and both approved artifact hashes are unchanged. No product or package changes were made for this audit. Installed DLL equivalence and runtime delivery remain unknown.

## Existing background-feed integration — 2026-09-28
- [x] Confirm the extension preprocessing hook runs before CHIM's `infonpc_close` fast-event log/terminate path; confirm the current hook returns without capture and prerequest is too late.
- [x] Read the routing owner's exact shared capture/read helper contract before changing hook/UI callers.
- [x] Add and run red the focused regression test proving `infonpc_close` is captured without suppressing CHIM core logging/termination.
- [x] Implement the approved hook and UI status/copy integration; keep browser GET read-only and distinguish authoritative empty from unknown/stale.
- [x] Document the projected 45-second observation lease, feed limits, and clock assumptions; run focused hook/page checks and PHP lint. Do not rebuild or version packages.

### Review

Lead approved the source and isolated-fixture gate. Passing normal-PHP checks: `php tests/background_presence_check.php`, `php tests/background_presence_hook_check.php`, `php tests/scope_check.php`, `php tests/page_check.php`, `php tests/state_check.php`, and `php tests/ui_check.php`. The legacy eligibility and autonomous-presence fixtures pass with `php -n -d extension=posix tests/eligibility_check.php` and `php -n -d extension=posix tests/autonomous_presence_check.php`; plain `php -n` omitted POSIX, and no product workaround was added. Scope was three server files (`state.php`, `preprocessing.php`, `index.php`), two new tests, and four updated existing tests, plus documentation/task/lesson updates. Companion and historical packages remain unchanged; no live install, runtime test, or release-pin advance occurred. Lead approval covers source and isolated fixtures only, not runtime behavior.

## Solo reflection UI slice — 2026-09-30

- [x] Confirm the owned UI files and focused tests match the saved implementation baseline.
- [x] Add red PHP tests for backward-compatible pair parsing, authoritative solo normalization, and one-actor rendering/staging.
- [x] Add red browser-harness checks for toggle labels/disablement, eligible B restoration, polling draft preservation, and identity-change clearing.
- [x] Implement only `index.php`, `assets/ui-refresh.js`, and owned UI tests; change CSS or preview fixture only if needed.
- [x] Run focused PHP and Node checks, syntax-check changed PHP, inspect assigned-file diffs against baseline, and write `tasks/implementation-2026-09-30/ui-report.md`.

### Review

Lead accepted the UI source and focused PHP/Node fixtures; the browser harness does not establish rendered browser or game behavior. Evidence: tasks/implementation-2026-09-30/ui-report.md.

## Pin 2A runtime adapter — 2026-09-30
- [x] Add early and late effective-mode guards before scope, presence, profile, and context mutations.
- [x] Implement solo A-only and pair A/B request constraints, strict-listener clamp, and request-local tight rechat.
- [x] Register only the exact last current-request native output with unambiguous fields and bounded private evidence.
- [x] Gate Mind Poisoning reflection on genuine correlated acknowledgement and fresh scope/config/identity validation.
- [x] Add same-repo hook-order and routing fixture checks; run relevant checks.
- [x] Complete cross-plugin exact-output/ACK fixtures with the registry owner.

Review: Lead accepted runtime routing and registry integration after inspecting call paths, returned defects and focused outputs. Final independently rerun registry fixture passed after dependency-load failure coverage. Source pins are complete; no release version, archive, installation or runtime certification advanced. See tasks/implementation-2026-09-30/final-review.md.


## Focused bug run — 2026-09-30

- [x] Inspect runtime/UI and reflection/influence boundaries independently with their existing coding owners; lead reviews state/eligibility/log boundaries.
- [x] Reproduce confirmed defects before minimal owner fixes; review every changed diff and affected callers.
- [x] Run only checks answering remaining risks, review outputs and document unresolved/live limitations.

Lead review complete: one exact-ACK normalization defect fixed and verified through red/green and an independent registry rerun; no additional confirmed defect. See tasks/implementation-2026-09-30/bug-run-review.md. No package, version, installation or live-state changes.


## Drama-llama visual credit — 2026-09-30

- [x] Inspect Mind Poisoning's emo llama reference and current PCV artwork; use built-in image editing to replace the horse emblem on the hanging banner with an emo llama portrait, removing the earlier cameo and preserving the inn scene.
- [x] Add `Part of the World of Drama-llama` to the current page and static preview; update image alternative text.
- [x] Review assigned diffs/render check and inspect the refreshed browser preview; preserve manifests, packages and runtime behavior.

Lead review complete: source/renderer checks and direct browser visual review passed. See tasks/implementation-2026-09-30/drama-llama-visual-review.md. No runtime or package changes.

## World of Drama-Llama release — 2026-09-30

- [x] Confirm explicit user authorization, destination and independent file ownership.
- [x] Prepare PCV 0.1.4 and compatible MP 0.1.12 candidate packages and metadata.
- [x] Write and review the collection README and detailed PCV operating/developer guide.
- [x] Review intended changes, clean-source deterministic packages and required evidence.
- [x] Commit, push and publish verified versioned assets, then advance main and verify public manifests.

Review and verification are recorded in release-v0.1.4-plan.md and the final publication report. Installed/game/provider behavior remains outside this release task.

Review complete: two clean builds, exact nested ACK fixture and all eight downloaded release assets passed. Both PRE-ALPHA releases and public source/manifests verified. See the collection tasks/world-of-drama-llama-publication-2026-09-30.md. No live gameplay claim.

## Thorough operational logging - 2026-09-30

- [x] Settle contract and exclusive file ownership.
- [x] Implement and review logger/reader and independent instrumentation.
- [x] Implement protected viewer/export and client refresh diagnostics after API gate.
- [x] Verify focused failures, mirror accepted source, update documentation and final review.

### Pin 2 UI implementation checklist

- [x] Add a standalone diagnostics route with exact loopback/server-user access checks and session CSRF, before CHIM dependency loading.
- [x] Add bounded filtered read/export rendering and fixed-code, throttled browser refresh reports.
- [x] Add focused HTTP and refresh tests for access, CSRF, sanitization, throttling, and unchanged scene controls.
- [x] Run only the focused checks and PHP lint; record outputs and runtime limits in the owned review.

Plan and evidence: logging-improvements-plan.md. No release or installed-runtime certification advances.

### Pin 2 UI review

The protected viewer/export and fixed-code refresh reporter pass the focused HTTP, PHP UI, and Node refresh checks. The diagnostic route runs before CHIM dependency loading; access, CSRF, invalid input, limited history, current-request write status, and safe export behavior are covered. See tasks/implementation-2026-09-30/logging/ui-review.md. Mirror and release work remain unadvanced pending lead review.

### Logging handoff review

Lead accepted logger/reader, instrumentation and dependent UI after returning defects to their original owners. Focused checks and final fixture isolation passed; 21 accepted runtime/test/build files match the controlled repository mirror by SHA256. Guides and task evidence accompany the source. Mind Poisoning product files, installed CHIM, versions and published archives remain unchanged. Full judgment and limits: tasks/implementation-2026-09-30/logging/final-review.md. Separate MP prompt: tasks/mind-poisoning-logging-prompt.md.

## Logging integration bug run - 2026-09-30

- [x] Inspect current diagnostics and their routing/presence/reflection/UI boundaries with exclusive original owners.
- [x] Reproduce confirmed defects, return minimal fixes to their owners and review focused evidence.
- [x] Complete current-run diff/scope/mirror checks and record limitations in the lead report.

Plan: tasks/logging-bug-run-2026-09-30.md. No installed or release changes.

Review: malformed-refresh draft loss, FIFO lock blocking and optional-observer correlation were reproduced and fixed. Lead returned the missing fresh-ACK binding to the same logger owner; the final real in-memory ACK boundary check passes. Focused Node (9/9), logger and registry checks pass. Exactly six current-run product/test files match source and repository mirror by SHA256; manifest/release pin unchanged. Limits and a narrow externally removed-state logging gap are recorded in tasks/implementation-2026-09-30/logging-bug-run/final-review.md.

## Mind Poisoning API check - 2026-09-30

- [x] Inspect current documented evaluator/observer contract and PCV callers.
- [x] Verify five evaluator-to-importer cases and PCV fresh-ACK registry behavior with existing isolated checks.
- [x] Record compatibility and source/publication/runtime limits in tasks/mp-api-check-2026-09-30.md.

Review: current PCV development source already uses the new observer API. Both focused commands exited 0. No product, installed environment, release or collaborator edits were made.

## Private Conversation 0.1.5 publication

- [x] Confirm PCV-only authorization and isolate reviewed source from separate MP publication.
- [x] Prepare and review PRE-ALPHA metadata, guides and explicit package expectations.
- [x] Verify focused release gates and deterministic clean-source artifacts; commit and tag.
- [x] Verify downloaded draft assets, publish before updating main and record public evidence.

Plan: tasks/release-v0.1.5-plan.md. No installation or gameplay certification is claimed.
Review: 0.1.5 PRE-ALPHA published after focused checks, clean commit/tag exports and exact draft/public download equality. Public hub and PCV manifest now point at 0.1.5; separately published MP 0.1.13 remains unchanged. Source tag is immutable; tasks/release-v0.1.5-evidence.md and package review retain hashes, gates and runtime limits.

## Private Conversation 0.1.6 publication

- [x] Review earlier solo registration, scoped missing-ACK diagnostics and strict Mind Poisoning API v1 compatibility.
- [x] Preserve published logging revision 2; document wire limits and own-test cleanup.
- [x] Establish independent deployment identity and canonical source line endings.
- [x] Verify clean exports/packages and downloaded drafts; publish PRE-ALPHA before advancing main.

Review: release-v0.1.6-evidence.md records matched MP 0.1.14/PCV 0.1.6 sources, focused fixtures, exact package hashes, public asset checks and migration. Registration remains after output flush, so early ACK recovery and live integration remain unproven. No installed CHIM, modlist or live data changes.

## Maintenance - 2026-10-01

- [x] Narrow private-scene routing and provide immediate END.
- [x] Make unmatched ACK diagnostics cheap; contain optional module failures; assess delivery race.
- [x] Protect runtime state from HTTP in all package formats, with isolated HTTP evidence.
- [x] Review owner diffs/checks and cross-task failure paths; document behavior/limits.
- [x] Clearly label rejected build and legacy authoring folder without deletion.

Plan: tasks/maintenance-2026-10-01/plan.md. Source-only development; no publication, installation or pin advancement authorized in this maintenance task.

Review: tasks/maintenance-2026-10-01/final-review.md records owner RED/GREEN gates, lead corrections, caller/failure review and protected-file comparison. Accepted for source integration only; early ACK recovery and live installation/gameplay remain unverified. Existing version pins and published assets were not advanced.

## Early-ACK handoff investigation - 2026-10-01

- [x] Trace native delivery, ACK ingestion and reusable exact evidence.
- [x] Verify Mind Poisoning API and captured-context requirements.
- [x] Review concurrency, expiry, END, replay and crash behavior.
- [x] Reconcile evidence and report extension-only feasibility and verification gates.

Plan: tasks/ack-handoff-investigation-2026-10-01/plan.md. Investigation only; existing source changes are preserved.

Review: tasks/ack-handoff-investigation-2026-10-01/final-report.md records the PCV-only persisted-native-ACK plus bounded private receipt design, including the stale ACK-header counterexample, both arrival orders, once-only claims, unresolved-registration capacity and crash limits. No mandatory core/Papyrus/MP code change demonstrated; runtime recovery remains unverified and unimplemented. Product/protected-file hashes unchanged.

## Early-ACK fix implementation — 2026-10-01

- [x] Reproduce early ACK loss and implement the reviewed bounded receipt/dual reconciliation.
- [x] Verify exact MP v1 integration, epoch/freshness/duplicate/capacity/failure guards.
- [x] Verify isolated SQL/shutdown bootstrap where available; label remaining runtime limits.
- [x] Review all diffs, affected package/docs gates, protected files and unchanged pins.

Plan: tasks/ack-handoff-fix-2026-10-01/plan.md. No installation, publication or collaborator edits.

Review: tasks/ack-handoff-fix-2026-10-01/final-review.md accepts extension-only source integration after actual MP evaluator fixtures, ten disposable PostgreSQL reader cases, four production-hook shutdown scenarios and affected logger/package gates. Lead returned defects to the same owners and reviewed final output. Legacy v1 direct compatibility, receipt expiry/capacity and crash/ambient-transaction limits are explicit. All 52 protected files and pins remain unchanged. Full installed CHIM/provider/Skyrim behavior remains unverified.

## ACK recovery bug run — 2026-10-01

- [x] Independently audit claim/effect, receipt/SQL and actual hook/bootstrap/diagnostic boundaries.
- [x] Reproduce material suspected defects with the smallest meaningful checks.
- [x] Review confirmed defects, proposed minimal fixes and reproduction output with the original owners.
- [x] Verify current-task scope, protected files and unchanged pins; record final judgment and limits.

Plan: tasks/ack-bug-run-2026-10-01/plan.md. Preserve all accepted work; no installed, collaborator or release changes.

Review: tasks/ack-bug-run-2026-10-01/final-review.md reports two confirmed P2 defects: an early receipt can expire before claim yet reach model work, and a delayed stale ACK can prune newer valid receipt evidence. Two focused regressions remain intentionally RED against unchanged product source. Cross-request unmatched-ACK tracing is a separate documented privacy limitation. Lead reviewed each owner finding, source path and actual output. All baseline/protected files and pins are unchanged; no live CHIM/provider/Skyrim proof or product fix is claimed.

## Two ACK bug fixes — 2026-10-01

- [x] Fix TTL admission at atomic claim without limiting already claimed providers.
- [x] Reject stale receipt writers before they can prune newer valid evidence.
- [x] Verify the two regressions and directly affected public-hook/direct-ACK/package behavior.
- [x] Review independent findings, every current diff and protected-file/pin evidence.

Plan: tasks/ack-bug-fix-2026-10-01/plan.md. Product fixes only; no installation, collaborator or release changes.

Review: tasks/ack-bug-fix-2026-10-01/final-review.md accepts both minimal fixes after targeted expiry/timestamp and stale-admission regressions, existing evaluator/direct/hook checks, isolated PostgreSQL shutdown cases and package/syntax gates. The lead reviewed all current-task diffs, returned fixture corrections to the same owner and examined both independent reports and actual verification output. Exactly two runtime files and three tests changed; 52 protected files and pins are unchanged. Live CHIM/provider/Skyrim behavior remains unverified.

## Publish Private Conversation 0.1.7

- [x] Prepare and review metadata, intended source inventory and PCV-only hub route.
- [x] Commit scoped source and verify immutable exports/reproducible packages.
- [x] Verify draft assets, publish, then advance update manifests.
- [x] Verify public releases/refs/downloads and record the review.

Plan: tasks/release-0.1.7/plan.md. User authorized commit, push and publication; Mind Poisoning stays separate and installed environments remain untouched.

Review: tasks/release-0.1.7/publication-evidence.md records 0.1.7 PRE-ALPHA publication in the dedicated repository and CHIM-Plugins hub. Lead reviewed owner diffs, source/packaging gates, exact hub subtree identity and downloaded assets before publication. Both main branches advanced afterward without force. Mind Poisoning root payload and all 52 protected authoring files are unchanged; installed CHIM/provider/Skyrim behavior remains unverified.

## Critique follow-up — 2026-10-01

- [x] Fix unclaimed solo registration liveness while preserving claimed-effect safety.
- [x] Move default runtime state outside the webroot with safe legacy handling.
- [x] Improve bounded logging contention/fallback and END event semantics.
- [x] Add only useful CHIM skill lessons and concise operator guidance.
- [x] Review all changes, meaningful checks, protected files and runtime limits.

Plan: tasks/critique-followup-2026-10-01/plan.md. No installation, publication, Mind Poisoning changes or pin advancement.

Review: tasks/critique-followup-2026-10-01/final-review.md accepts the 60-second unclaimed lease, private-state migration, bounded contention fallback and accurate END event after focused regressions, final shared-module integration, isolated HTTP checks and independent source review. The CHIM skill received three narrow additions. Manual operator checks remain unrun; source fixes are unreleased and all 52 protected files and pins are unchanged.

## Publish Private Conversation 0.1.8

- [x] Prepare reviewed release metadata and scoped source commit.
- [x] Verify clean exports, deterministic assets and PCV-only hub synchronization.
- [x] Verify draft downloads, publish, then advance update manifests.
- [x] Verify public state and record final evidence.

Plan: tasks/release-0.1.8/plan.md. User authorized commit, push and publish Private Conversation only; installed environments remain unchanged.

Review: tasks/release-0.1.8/publication-evidence.md records the 0.1.8 PRE-ALPHA release and hub bridge. Clean-source and clean-tag package gates passed; both draft and fresh public downloads matched exact bytes. The hub tagged snapshot equals canonical source, both update branches advanced only after publication, and final public refs/manifests and all 52 protected hashes were verified. Historical tags/assets and unrelated dirty files remain intact. Installed CHIM/provider/Skyrim behavior remains unverified.

## MO2 packaging warning investigation — 2026-10-08

- [x] Inspect current package, public CHIM examples and MO2 content/metadata checks.
- [x] Review native CHIM sync discovery and distinguish it from MO2 warnings.
- [x] Respect online-only scope and record the smallest source-supported packaging remedy.

Plan: tasks/mo2-warning-check-2026-10-08/plan.md. Review: tasks/mo2-warning-check-2026-10-08/final-review.md. Root meta.ini with validated=true is the candidate; unchanged DWPkg and no FOMOD/game payload required. MO2 UI/install/update and actual CHIM sync are unverified in this investigation. No product changes or publication. tasks/lessons.md records the checker-tracing correction.
## MO2 metadata fix — 2026-10-08

- [x] Implement minimal deterministic metadata and targeted package regression.
- [x] Clarify installation instructions and preserve documented verification limits.
- [x] Independently verify resulting package, review all diffs and unchanged runtime/pins.

Plan: tasks/mo2-metadata-fix-2026-10-08/plan.md. Local implementation only; no publication or installed-environment changes.
Review: tasks/mo2-metadata-fix-2026-10-08/final-review.md accepts the minimal metadata wrapper and corrected install instructions after RED/GREEN, four package checks, reproducible builds, independent artifact inspection and lead review. All runtime files/pins and prior assets are unchanged. MO2 UI/install/update and native/game sync remain unverified; nothing was committed or published.

## Publish Private Conversation 0.1.17 — 2026-10-09

- [x] Prepare/review 0.1.17 source metadata and PCV-only hub changes.
- [x] Commit scoped source, verify immutable tag/reproducible packages.
- [x] Verify draft assets, publish, then advance mutable update refs.
- [x] Verify public downloads/refs/hub and record final judgment.

Plan: tasks/release-0.1.17/plan.md. User authorized commit, push and publication; no installed changes or separate Mind Poisoning work.
Review: tasks/release-0.1.17/publication-evidence.md accepts the published 0.1.17 PRE-ALPHA release, exact public asset bytes, update manifest and tagged hub snapshot. Concurrent MP 0.1.19 work was preserved. Package proof is not native MO2/sync/gameplay proof; no installed environments were changed.

## PCV diagnostic clarity — 2026-10-15

- [x] Correct last-turn UI claims with focused render verification.
- [x] Clarify diagnostic boundaries and prepare separate core/MP handoff prompts.
- [x] Review all diffs, checks and scope preservation.

Plan: tasks/diagnostic-clarity-2026-10-15/plan.md. Live distro remains stopped; only the test distro is authorized for checks. No publication or installation.

Review: tasks/diagnostic-clarity-2026-10-15/final-review.md accepts the local diagnostic wording fix, explicit test-distro render PASS, four-case subject probe and separate handoff prompts. No publication or core/MP implementation. Gaming VHD timestamp change remains unattributed; final explicit test left gaming stopped and its later timestamp stable.

## PCV working-tree ALPHA assessment and END acceptance docs — 2026-10-15

- [x] Capture pre-product-edit hashes and current shared diffs.
- [x] Scene owner: verify disable-failure handling for direct END, solo wrap-up, and pair/group wrap-up with an isolated test-distro fixture and successful/no-scene controls.
- [x] Update README overview, compatible manifest description, and operator acceptance evidence/limits.
- [x] Run bounded Windows-only JSON/link/diff checks; no WSL/runtime command from the documentation owner.
- [x] Complete final review after lead conclusion.

Plan: tasks/alpha-end-2026-10-15/plan.md. Current working-tree ALPHA assessment only; published 0.1.17 remains PRE-ALPHA. No packaging, install, commit, or publication.

Review: lead accepted after independent aggregate review. The scene owner's RED/GREEN hook fixture and existing END-command fixture passed in the explicitly asserted test distro; the hook test stubs stage results and is not a real storage-fault or live-game check. Manifest JSON and unchanged fields, new Markdown paths/fragments, tracked diff whitespace, and baseline PCV server hashes passed Windows-only checks. See tasks/alpha-end-2026-10-15/final-review.md.

## Private Conversation 0.1.18 ALPHA source metadata and release docs — 2026-10-15

- [x] Capture pre-edit hashes for the authorized release files and confirm the explicit source staging allowlist.
- [x] Update current README/install version references and manifest version/ALPHA description; preserve 0.1.17 PRE-ALPHA history and manifest compatibility/channel fields.
- [x] Add 0.1.18 ALPHA distribution notes with accurate END behavior, evidence limits, single-source install guidance, and no fabricated hashes.
- [x] Run bounded Windows-only JSON/link/name/diff/whitespace checks; write source report and hand off allowlist.
- [x] Build owner: candidate package and focused final-tree checks; lead: commit the explicit source allowlist.
- [x] Build owner: clean committed-source rebuild and exact package hashes; lead: immutable tag push, draft asset download checks, then publish verified assets.
- [x] Hub owner: PCV-only canonical main/hub snapshot and pointers after publication; lead: verify public refs/downloads.
- [x] Add publication evidence separately after public verification; do not put it in the initial source commit or invent checksums before actual builder output.

Plan: tasks/release-0.1.18/plan.md. Release operations remain lead-owned; no package/install/commit/tag/push/publication by this metadata owner.

Review: tasks/release-0.1.18/publication-evidence.md records the published ALPHA, immutable source/tag identity, exact verified package/download outcome, preserved 0.1.17 assets, and PCV-only hub snapshot. All authorized gates are complete; package/fixture evidence does not establish live 0.1.18 gameplay, playback, universal privacy, or every installation route.

## Private Conversation 0.1.18+solo-validationpatch bounded beta checks — 2026-10-16

- [x] Write the ordered plan distinguishing immutable public 0.1.18 from the unpublished validation candidate.
- [x] Inventory source/release identity, isolated test environment, and native-client boundary.
- [x] Scene owner: complete the scope.php action fix with RED/GREEN and lint; lead approved Gate 1.
- [x] Build the private candidate and import through the actual package API; verify all 20 installed files.
- [x] Exercise actual direct END immutable-replacement failure, recovery, and ordinary routing. Caller fixture passes with stubbed stage outcomes.
- [x] Close Gate 2 with the lead. The bounded UI GET still failed before deletion on missing public.database_versioning; package removal/reimport remains NOTRUN, with no POST or bypass.
- [ ] Exercise actual storage failure through solo/group wrap generation and full CHIM dispatch; current caller fixture stubs the state result.
- [x] Verify the exact MP 0.1.20 tag/export provenance and private DB connector identity.
- [x] Run reflection attempt 03 and preserve its outputs. Result: fixture assertion failed with sentinel_mismatch; static review attributes it to the omitted core chat helper include, so no product defect is inferred.
- [x] Prepare a fail-closed cleanup proposal for the exact attempt-03 synthetic profile/NPC rows, fixture-created eventlog/speech tables, and matching PCV state/receipt; no cleanup has run.
- [ ] After the reflection owner fixes the fixture and root resolves the automatic-review conflict, re-enter only the explicit test distro, verify all exact preconditions, and perform the authorized synthetic cleanup. Stop if any identity, count, config, receipt, or history check differs.
- [ ] Run one corrected reflection integration attempt after the exclusive window opens; preserve exact command, exit, stdout/stderr, and structured outcome.
- [ ] Reassess native/client boundary only if lead identifies a decision-changing need. MO2/client, installed game, provider, and live CHIM remain NOTRUN.
- [x] Lead review accepted; see tasks/beta-checks-2026-10-16/lead-review.md.

Plan and ledger: tasks/beta-checks-2026-10-16/plan.md and progress.md. Gate 1 and Gate 2 are closed; Gate 3 is pending corrected fixture plus runtime authorization. Published 0.1.18 and canonical core/MP sources remain unchanged.
### Final bounded runtime update (2026-10-16; supersedes the earlier pending Gate 3 bullets above)

- [x] Gate 1 action fix and Gate 2 package import/direct END evidence passed and were lead-closed.
- [x] Gate 3 production connector identity passed; the exact attempt-03 synthetic state/receipt and DB rows were removed by the approved production-helper + guarded transaction cleanup (`tasks/beta-checks-2026-10-16/reflection-evidence/attempt03-cleanup-receipt.md`).
- [x] Attempt 04 observed the actual MP relationship commit; its full fixture exited 1 because its history assertion expected pre-write state, while MP stores post-write state with the relationship provenance marker. The bounded continuation on the same committed data passed post-write history/ledger/affinity checks, consumed the registry entry, matched the present native ACK row, and returned evaluator `claim_taken`, made no model call or DB change, and staged state off. This is not fresh-receipt or public-hook replay proof; see `attempt04-continuation-result.json`.
- [x] Stop only the owned PostgreSQL cluster and foreground hold; the final Windows-only WSL status query returned no running distros. Attempt-04 DB/history/logs were preserved; no post-continuation cleanup.
- [x] Lead review accepted; see tasks/beta-checks-2026-10-16/lead-review.md. Corrected full fixture lint is NOTRUN because the test distro was already stopped; no re-entry solely for lint.
- [ ] Remaining NOTRUN: exact-build installed pair/removal and package reimport, native timeout/cancellation recovery, full CHIM solo/group wrap dispatcher under real storage failure, fresh-receipt/public-hook replay, native MO2/client, installed game, provider, and live CHIM. The complete beta gate remains NOT READY until the installed-pair/removal and native recovery checks pass.

Evidence: `tasks/beta-checks-2026-10-16/progress.md`, `environment.md`, and `reflection-evidence/attempt04-runtime-shutdown-receipt.md`.

## PCV bounded beta completion - 2026-10-16

- [x] Write the ordered plan and wait for root confirmation before any distro/runtime entry.
- [x] Inspect package install/removal callers and core schema/bootstrap locally; record the source-derived bootstrap proposal and assertions in `tasks/beta-finish-2026-10-16/install-report.md`. Full-core runtime verification remains gated.
- [x] In the released short window, build the fresh private core/DB environment and import the exact candidate through the supported API; retain connector identity, 20-file byte-match, and off-state receipts.
- [x] Hand off the corrected reflection fixture environment; after fixture/lead gate, run one corrected fixture and preserve the original output/exit receipts. Lead accepted the run; exact limits remain in the reflection report.
- [ ] Verify exact-build Standard-pair flow, END/ordinary routing, and supported package removal using actual callers in a separate fully initialized scratch environment.
- [ ] Reflection owner: fresh public-ACK scheduling seam. Native timeout/cancellation recovery only if an exact safe client seam exists; otherwise record NOTRUN.
- [x] Stop only owned processes, preserve synthetic DB/evidence, update environment/results, and request final lead review. The delayed Windows-only status query confirmed the test distro Stopped; see `install-evidence/phase1/shutdown-summary.md`.

Plan and setup proposal: `tasks/beta-finish-2026-10-16/plan.md` and `install-report.md`; environment status: `tasks/beta-finish-2026-10-16/environment.md`. Phase one is complete and its test distro is Stopped.

### Short-window update

- [x] Root released only the fresh minimal fixture setup/import window; Windows-only WSL/VHD preflight is recorded in `tasks/beta-finish-2026-10-16/install-evidence/preflight.md`.
- [x] Create the new nonce-isolated source and private DB; apply only eight pinned-source DDL tables and one synthetic player row; verify both actual core connectors use the exact private DB/socket/role.
- [x] Import the unchanged candidate through the actual package API, verify all 20 installed bytes, and initialize/read production off state; preserve the old a9d47c2e attempt untouched.
- [x] Send exact readiness to root and the reflection owner; wait for and receive lead approval before the one corrected fixture run.
- [x] Stop only owned processes and verify the test distro is Stopped. Full-core/provider/pair/wrap/removal/public-ACK/native work remains outside this short window and needs a separate gate.

### Phase-one closeout / next-window preparation

- [x] Lead accepted the corrected reflection fixture (lint exit 0, one run exit 0); raw output and state receipts are under `tasks/beta-finish-2026-10-16/reflection-evidence/`.
- [x] Preserve the fresh synthetic DB, private source/state, and receipts; stop only the recorded PHP/PG/hold helpers. After a 16-second shutdown delay, the Windows-only WSL query returned the test distro Stopped. VHD size/mtime are metadata only, not byte-preservation proof.
- [ ] Prepare and lead-review a source-derived full-core/bootstrap + actual pair/END/ordinary/wrap/removal harness in Windows-only static work. Reflection owns the public ACK SSE provider/driver; scene owner reviews PCV route assertions. Do not enter WSL until root opens a new runtime gate.

### Full-core harness preparation (2026-10-16; no runtime gate yet)

- [x] Review production callers and correct the instruction attribution chain: transport prefix is stripped by CHIM before persistence.
- [ ] Add sanitized read-only CLI checkpoints for PCV state/receipt/registry, exact native ACK tuple, hashed synthetic relationship/history state, and correlated duplicate `claim_taken` event.
- [ ] Freeze checkpoint schema with reflection owner; keep provider request inspection in memory and emit only safe booleans/hashes.
- [ ] Record source-derived full-core schema/updater, loopback connector, no-egress namespace, PHP worker overlap, TTS/background-worker guards, and actual HTTP route sequence.
- [ ] Obtain scene-owner and lead static review. No runtime window is currently open; do not enter/start the test distro until root opens a new gate.

Plan: `tasks/beta-finish-2026-10-16/install-evidence/fullcore-harness-plan.md`.

### Static closeout (2026-10-16)

- [x] Match the selected cached core schema/bootstrap source files against the exact `cf5030f` tree; inventory and limits are in `tasks/beta-finish-2026-10-16/install-evidence/fullcore-source-provenance.md`.
- [ ] Freeze the full-core checkpoint interface. The draft `post_wrap` check binds to the first pair's config UUID, but the driver ends that scene and creates a new pending pair before `wrap up:`; add a fail-closed pending-config capture before wrap in a later approved window.
- [ ] Build the full-core schema/import/updater runner and perform the approved exact-build pair, UI END/ordinary, wrap, supported removal, and public-ACK gates. None ran in this static closeout; no WSL/runtime window was entered.
- [ ] Native timeout recovery remains separate and NOTRUN unless a safe native client seam is approved.

Full-core beta remains NOT READY. The controlled-ALPHA candidate decision and accepted phase-one import/fixture are separate and unchanged.
## Private Conversation 0.1.19 ALPHA release documentation - 2026-10-16

- [x] Describe the solo target fix, underscore-compatible diagnostic writer/reader, and additive flat Plugin Manager archive.
- [x] Document the actual Server Plugins refresh/install/update/Plugin Page flow, distinguish repository-slug and legacy paths, and retain one-source/archive-wrapper guidance.
- [x] Explain loaded MP API compatibility for the released 0.1.21 repo-slug path and retain the known 0.1.16 compatibility without making either a hard requirement.
- [x] Keep clean helper evidence tied to the local 0.1.18 validation candidate; do not link uncommitted beta task artifacts or imply full 0.1.19 runtime proof.
- [x] Correct the operator checklist to distinguish disposable acceptance from the separately authorized, backed-up live ALPHA test.
- [x] Run final scoped JSON, link, LF/newline, diff-whitespace and SHA-256 checks; report exact hashes to the lead.
- [x] Builder: create and verify the candidate assets and record actual member/hash output.
- [x] Lead: review the source allowlist, commit/tag/publish the ALPHA release, verify fresh public assets and record publication evidence.
- [ ] Full HTTP pair/END/wrap/removal, public ACK scheduling, native recovery, and gameplay remain NOT RUN for 0.1.19; beta certification remains pending.

Plan and source assessment: `tasks/release-0.1.19/plan.md` and `tasks/release-0.1.19/source-report.md`. Publication is complete; final verification is in tasks/release-0.1.19/publication-evidence.md.


### Final release review

Source/docs and the PCV-only hub snapshot are accepted. The focused four package checks, 20-file payload delta, independent 16-assert archive review, clean committed-source build, tag, draft/public asset comparisons, publication, and canonical main/hub advancement are complete; see tasks/release-0.1.19/publication-evidence.md. The public ALPHA remains distinct from beta certification: exact-build full HTTP pair/END/wrap/removal, fresh public ACK scheduling, native recovery, and gameplay remain NOT RUN.


## Private Conversation 0.1.20 ALPHA release preparation - 2026-10-16

- [x] Read the required Ponytail, CHIM release, and prerelease workflow instructions plus the 0.1.18/0.1.19 source, package, publication, and hub receipts.
- [x] Record the current PCV and hub remotes, refs, release trees, untracked protected evidence, and accepted duplicate-copy ownership design in tasks/release-0.1.20/plan.md.
- [x] Open the exclusive test-only lease and record registered distro names/status, test/live VHD metadata, WSL_DISTRO_NAME, PHP version, uid, temp path, and mounted source path. The live distro was not entered; the final Windows-only postcheck found all WSL distros stopped. The live VHD size and timestamp remain metadata only, not a content hash.
- [x] Capture solo RED before the scope fix and solo GREEN afterward. Raw GREEN streams and the reported RED marker are in tasks/release-0.1.20/runtime-evidence.
- [x] Review the accepted solo and duplicate-copy diffs and run the bounded test-distro fixtures. Solo action checks and duplicate owner/state/HTTP refusal gates passed in both required path/load-order configurations.
- [x] Run the bounded guard regression and final syntax/turn-hook checks. Page, scope, and postrequest terminal fixtures passed; the regression runner stopped at the ACK shutdown fixture's explicit missing disposable PostgreSQL precondition before connecting. The final command passed turn_hooks_check.php and syntax for 11 touched product PHP files plus the ACK fixture. ACK shutdown behavior remains NOTRUN, and one directory_unavailable logger warning remains unattributed.
- [x] Finish README/distribution notes and manifest version for ALPHA 0.1.20 while preserving the schema/channel contract and compatibility reference. Document the limited solo-helper proof, duplicate recovery, state-migration limit, and unsupported multiple unguarded-old-copy case.
- [x] Add only server/installation_guard.php to the package runtime allowlist and update exact package member/count assertions to 21 server files.
- [x] Run py -3 tests/package_check.py (4 tests, exit 0) and build the task-owned working-tree candidate under tasks/release-0.1.20/package-evidence/candidate. The candidate is not a clean committed-source release build.
- [x] Independently inventory asset members, source-byte equality, MO2 inner-DWPkg equality, and all four SHA256SUMS entries; see package-evidence/candidate-inventory.stdout.txt.
- [x] Prepare the exact source staging allowlist and hash manifest, run git diff --check, and record a review section. Do not stage before root accepts the source gate.
- [x] Stage the explicit reviewed source allowlist, pass the cached whitespace gate, create the accepted source commit and annotated private_conversation-v0.1.20 tag, rebuild from a clean export, and compare all five assets byte-for-byte.
- [x] Publish the normal-flag ALPHA release, verify all five anonymous public downloads, advance canonical main and the PCV-only hub non-force, and independently verify the exact subtree and protected paths.

### Review

Solo action behavior and duplicate owner election/inactive HTTP refusal passed their bounded fixtures. Package checks passed; the exact source commit was clean-built, tagged, published as a normal-flag ALPHA release, and all five anonymous public downloads matched the clean build. Canonical main and the PCV-only hub mirror advanced non-force; independent review proved 356 exact PCV path/mode/blob entries, three authorized shared-doc edits, and 239 protected paths unchanged. The ACK shutdown behavior remains NOTRUN because no disposable PostgreSQL target was configured; no connection was made. One postterminal logger directory_unavailable warning remains unattributed. Full CHIM bootstrap/schema refresh, live install/provider/database operation, public ACK scheduling, native recovery, client sync, and Skyrim gameplay remain unverified. The release is ALPHA, not beta or stable certification. CHIM core, Mind Poisoning, live installation, and game data remain outside this change. See tasks/release-0.1.20/publication-evidence/final-publication-receipt.md.

## Reader-first README refresh (2026-10-16)

- [x] Preserve the current ALPHA identity, existing art, release/download URLs, and safety boundaries; inspect the README and documentation map.
- [x] Rewrite the README around quickstart, mode/capability map, examples/results, and current limits; check workflow claims against the player guide and keep optional Mind Poisoning integration separate.
- [x] Complete source-backed review of the player guide and reader-first README; run focused Markdown link, line-ending, and whitespace checks.
- [x] Prepare the full replacement body for the existing 0.1.20 GitHub release; after lead review, apply the reader-first intro, guide/README links, already-installed quickstart, and End now reminder.
- [x] Push canonical documentation commit b6f0e82877038746497719d12952f51253fb59e5 and verify the public README and player-guide blobs against it.
- [x] Mirror only the two PCV documents and one PCV link line to CHIM-Plugins; push non-force and verify the public hub commit and unchanged protected trees.

### Review

The documentation refresh is published. Canonical commit b6f0e82877038746497719d12952f51253fb59e5 is public, and the current 0.1.20 release body matches the reviewed body SHA-256 f43a584592cd504eff14af95c07ea4a7e7f2aeb489007fc571440f9fd6be4b5f. The original body-equals-tag-note evidence refers to initial publication; it is not a claim about the updated body. The PCV-only hub documentation mirror is public at 84dbe4f5c022764372ff23fe65a6b38be229ddf0. The 0.1.20 source tag, five assets, release flags, product code, CHIM core, and Mind Poisoning were not changed by this follow-up. No new runtime or gameplay proof was produced. See [the documentation publication receipt](player-docs-2026-10-16/docs-publication-receipt.md).

## Private Conversation 0.1.21 BETA evidence and operator acceptance update - 2026-10-17

- [x] Keep this documentation-only task separate from CHIM core, SHARMAT, Mind Poisoning, installation, and live-server work; preserve immutable 0.1.20 source/package/release artifacts.
- [x] Record bounded 0.1.20 runtime/log evidence and direct operator confirmations in `tasks/beta-2026-10-17/acceptance-evidence.md`; do not include raw dialogue, private prompts, credentials, or unsupported exact timing/removal-route details.
- [x] Update `docs/operator-acceptance.md` to distinguish observed/operator-confirmed practical outcomes from independent log proof, and identify version, scope, and evidence limits.
- [x] Describe 0.1.21 as a metadata-only BETA candidate; the publisher receipt verifies exact equality for all 20 non-manifest payload members. Do not describe the candidate as natively tested.
- [x] Append the reusable lesson to `tasks/lessons.md`: distinguish operator confirmation from log proof; do not label an operator-confirmed check NOT RUN solely because its independent trace is narrower.
- [x] Read back the four owned files, confirm no raw dialogue/private prompt material was added, and record a review that marks payload comparison verified and lead acceptance complete while leaving release mechanics to the separate release record.

### Review

Read back the four owned files. The evidence is a dated summary of previously inspected 0.1.20 runtime records and operator confirmations, with no raw dialogue or private prompts. The publisher receipt verifies 20/20 non-manifest runtime-payload identity for the 0.1.21 candidate; this is not a 0.1.21 gameplay claim. Practical repeat, post-END ordinary Standard, and removal remain accepted as operator-confirmed with their stated detail limits. The lead accepted this bounded evidence; release mechanics are recorded separately under `tasks/release-0.1.21/`, and this review does not claim publication.

## Paired PCV SQL wait bounds — 2026-10-26

- [ ] Record scoped baseline RED, implement bounded owned identity/catalog reader and native callers under exclusive Luna Max/Ponytail FULL ownership.
- [ ] Verify actual isolated TEST2 behavior, paired Department overlay/known23 backup migration and independent review.
- [ ] Coordinate verified0.1.24/Department20 source, packages and publication; preserve other components and prior releases.

Plan: [SQL read timeouts](read-timeouts-2026-10-26/plan.md).
