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
