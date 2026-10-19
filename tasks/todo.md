# CHIM Mind Poisoning — implementation ledger

## Release 0.1.19 — 2026-10-09

- [x] Prepare/review 0.1.19 metadata, clean-export package gates, immutable tag, canonical/mirror publication and final evidence.

Plan: [release-v0.1.19-plan.md](release-v0.1.19-plan.md). Receipt: [release-v0.1.19.md](release-v0.1.19.md). Both PRE-ALPHA releases are public and byte-verified; main refs updated after publication. No installed-runtime changes; other contributors and historical assets preserved.

## MO2 packaging and installation usability — 2026-10-08

- [x] Verify external feedback against MO2 sources, CHIM guidance, active builders and existing installation instructions.
- [x] Add deterministic version/source metadata to the active plain MO2 ZIP, with a failing regression first and unchanged embedded DWPkg.
- [x] Replace stale user instructions with one recommended MO2 download, verification steps and separate advanced formats.
- [x] Review every diff/caller and focused verification; build a clearly named local preview and compare its inner payload with published 0.1.18.
- [x] Record result and limitations, including the separate unconfirmed CHIM read error.

Scope: scripts/package.py and tests/test_package.py (package owner); README.md, docs/mind-poisoning.md and docs/deployment-migration.md (documentation owner); lead task records only. Existing work branch is reused with exclusive ownership. No other plugin edits, server payload/version changes, installed runtime/modlist/WSL/provider/database operations, commit/push/publication, or overwriting retained release assets. The obsolete FOMOD format is not revived. No forced validated flag, fake Nexus ID or dummy Skyrim files. Metadata does not establish successful MO2 installation or CHIM synchronization.

Review: package regression red-to-green (7/7), all five product/document diffs and active callers reviewed, real local preview CRC/INI/nested bytes verified against public 0.1.18, 19 relative links and scoped diff checks passed. Independent review found no package blocker; its one-version wording concern was fixed and verified. All 913 protected hashes and HEAD are unchanged. Native MO2 acceptance, installed CHIM sync and the unspecified read error remain unverified. Local-only correction; release pin stays 0.1.18. Plan/evidence: [mo2-usability-2026-10-08.md](mo2-usability-2026-10-08.md).

## PCV 0.1.16 hub update and integration note — 2026-10-05

- [x] Inspect current tree, hub, instructions and published PCV release metadata read-only.
- [x] Update only the hub README's PCV version/links and opt-in scene-action summary.
- [x] Review MP sentinel handling and action-evidence limits read-only; keep L5 unverified without the exact receipt/catalog.
- [x] Verify links, diff and protected files; commit/push only hub documentation and verify public bytes.

Scope: README and this ledger only. PCV remains a read-only source; no embedded snapshot update, MP product/API/version changes, package release, installed-server/WSL/provider/database access or external messages. Origin/main is the only publication destination. Keep PRE-ALPHA status; an issued action does not prove execution.

Review: README owner read the complete page and published PCV guide, then updated only active PCV links and the opt-in action summary. Lead reviewed the diff and corrected the overly broad claim that actions cannot affect affinity: MP judgments use dialogue; other systems may process actions. Three PCV download links match published assets; the tagged README/logging guide exist; stale active 0.1.15 references are absent. MP/S&S versions and all 13 protected-file SHA256 baselines remain unchanged; scoped diff checks passed. Independent source review and lead inspection confirmed the immutable MP 0.1.18 tag already excludes the sentinel before listener/catalog resolution (server/prerequest.php:1073-1075) and from witness recipients (:335-343), including case-insensitive catalog-name collisions; existing fixtures are tests/overhearing_test.php:340-372 and were inspected, not rerun. No actions_issued consumer exists in server/. MP consumes correlated speech evidence and registered reflection tuples; an issued action supplies no completion/witness proof. L5 remains unverified: a v1/fallback final line or an ambiguous first-name alias can explain it, requiring the exact registration and contemporaneous catalog. No product change is justified by this handoff.

Publication: README commit `27afa1cc8d552446a87b42a4fb0c6e9c2a0bf15a` is public on CHIM-Plugins origin/main. Public README bytes exactly equal the committed Git blob (SHA256 `b2ef9b6f9059e015155fbc7cab0371a4dff79d0bb6dfdeb7e0479c4c5c7679eb`); all 13 protected hashes still match. PCV is listed as 0.1.16 PRE-ALPHA, MP remains 0.1.18 and S&S remains 0.1.5. The subsequent ledger-only commit records completion. Saved release metadata/baseline/public verification are under ignored dist/hub-pcv-0.1.16/. No runtime tests, WSL, installation, live provider/database work, plugin release or sibling-repository changes occurred.

## Three-plugin hub overview — 2026-10-04

- [x] Verify published source/status/links for Mind Poisoning, Private Conversation and Sworn & Scorned read-only.
- [x] Update only the collection README with useful descriptions, current releases, guides, downloads and integration limits.
- [x] Review the diff and verify destinations/asset names, protected files and scope.
- [x] Commit/push the documentation-only hub update and confirm public README bytes.

Scope: CHIM-Plugins landing page. Other plugin projects are read-only information sources; no source snapshot import, runtime changes, releases, package builds, WSL/server or installation work. Preserve unrelated dirt and Mind Poisoning 0.1.18 manifest. Push this hub documentation to origin/main only.

Review: The lead reviewed the complete README and delegated corrections to its owner. Three published prereleases, nine asset URLs, seven relative targets, table structure and seven protected hashes passed; GitHub GFM rendering succeeded. Optional MP dialogue/reflection integration is distinguished from independent S&S lifecycle updates. Only README and task records are authorized for origin/main; README commit `a364b6d` is public on origin/main; public README bytes equal the committed Git blob and the MP manifest remains 0.1.18. No sibling project, runtime payload, release or installed environment was modified. [Receipt](hub-overview-2026-10-04.md).

## Authorized 0.1.18 publication — 2026-10-04

- [x] Review metadata, clean export and package/consumer evidence.
- [x] Commit/tag and reproduce all assets from the clean tag.
- [x] Independently review, push tags, verify drafts, publish and verify public downloads.
- [x] Advance public main branches after release verification and record receipt.

Plan: [release-v0.1.18-plan.md](release-v0.1.18-plan.md). PRE-ALPHA; no installation or WSL/live runtime operation.

Review: Release commit `83908fe` and annotated tag `mind_poisoning-v0.1.18` are public in both repositories. Clean-tag source equality, native consumer/canonicalizer checks, independent archive review, exact draft/public download equality, main manifests/README and old-release preservation gates passed. Private Conversation stays 0.1.15; protected dirty work is preserved. PRE-ALPHA, no install or WSL/live action. [Receipt](release-v0.1.18.md).

## Relationship compatibility fixes — 2026-10-04

- [x] Capture targeted failing tests against a83da0a and agree shared read/write contracts.
- [x] Implement core normalization/persistence and request/dashboard adapters with exclusive ownership.
- [x] Verify isolated behavior and actual PostgreSQL writes; review every diff and failure path.
- [x] Record final evidence, limitations and unchanged release pin.

Plan: [relationship-compatibility-2026-10-04/plan.md](relationship-compatibility-2026-10-04/plan.md). Source-only fix; no installation, version/pin advance, commit/push or publication.

Review: Both opinion-owner compatibility defects are fixed in the unreleased working tree. Strict stored-map validation accepts only objects or exact []; Player aliases use pinned CHIM canonical weighting for prompt/dashboard and locked save, with atomic alias deletion and edge/ledger/timeline updates. Lead reviewed all product, adapter, documentation and regression diffs, returned defects to their original owners, and reviewed the separate owners' cross-reviews. Focused PHP 8.2 behavioral checks passed; the guarded PostgreSQL 15 writer/transaction fixture passed with second-connection commit/rollback verification. Changed server/test PHP syntax and scoped whitespace checks passed. Scratch PostgreSQL stopped and its exact root was removed; the test clone was terminated, and independent Windows metadata comparison confirms the gaming VHD unchanged. The SQL runner's post-cleanup CRLF exit 2 is documented separately from PHP fixture exit 0. No live database/provider/game/install proof is claimed. HEAD remains a83da0a; published version/tag remains 0.1.17. See relationship-compatibility-2026-10-04/verification.md. No commit, publication or sibling product edits.

## Authorized recovery and relationship handoff review — 2026-10-04

- [x] Fresh-check the exact approved generated-file allowlist, source status, retained hashes and exclusive file access.
- [x] Remove only approved paths and independently verify recovered bytes and retained files.
- [x] Review the relationships-array and Player-alias handoff against current MP and pinned CHIM source; give a read-only assessment.

Scope: recover the previously recommended 409,841,002 logical bytes. Preserve source, Git, PHP, receipts, canonical release copies and the PCV 0.1.12 tar. Handoff implementation instructions are review material, not authorization to change plugin code or run a distro/database.


Review: Recovered 409,841,002 logical bytes (390.85 MiB) by removing the 43 approved generated paths / 4,401 files. Independent verification confirmed every target absent, all 3,240 retained dist files and 413 pre-existing source/user files unchanged by SHA256; remaining dist is 606,000,091 bytes (577.93 MiB). The recursive empty-directory error and safe literal-file/empty-directory recovery are recorded in disk-space-recovery-receipt-2026-10-04.json. Source, Git, PHP, receipts, retained release sets and PCV12 tar were preserved. Both relationship issues merit correction; the review also identifies first-alias model-context inconsistency and corrects the overly broad no-model-call claim. See relationship-compatibility-review-2026-10-04.md. No plugin product code, sibling project, server/distro, release pin, commit or remote was changed.

## Generated-file space audit — 2026-10-04

- [x] Inspect protected status and measure only the local generated dist tree, skipping reparse paths.
- [x] Verify redundant release assets against retained copies and preserve source archives where local tags are missing.
- [x] Save an exact candidate-path inventory and report recoverable space without deletion.

Review: dist contains 1,015,841,093 logical bytes (968.78 MiB); latest PCV 0.1.15 scratch is 206.13 MiB. The conservative recovery candidates total 409,841,002 bytes (390.85 MiB), including 108.76 MiB from the latest run. Keep portable PHP, small receipts/transcript, one verified local asset set for each selected MP release, the PCV 0.1.12 source tar, all source/Git/user files and unreviewed historic artifacts. No deletion, WSL/server access, commit or push was performed. Inventories: `tasks/disk-space-audit-2026-10-04.json` and `tasks/disk-space-recovery-candidates-2026-10-04.json`. Future deletion needs explicit scope authorization and fresh path/hash/access checks.

## PCV 0.1.15 hub snapshot — 2026-10-04

- [x] Verify published tag, clean-tag packages and supplied checksum.
- [x] Update exact pinned snapshot and current hub links; preserve unrelated work.
- [x] Review sentinel exclusion and reported reflection-no-subjects against source and retained evidence.
- [x] Fix the confirmed reserved-sentinel catalog collision; verify with isolated native PHP, without changing the MP release/version.
- [x] Review diffs, verify clean export, commit/push and confirm public hub state.

Scope: hub/source/package checks plus a separate unreleased MP sentinel fix with isolated native PHP; no WSL distro, live database/provider, installation or new plugin release. Exact live cause remains unconfirmed unless retained evidence proves it.

Review: Hub commit `eaf379b1ded1d38b4eed0f2036a4ae8de9fec29b` is public in both main branches with PCV 0.1.15 links and the verified snapshot; all four assets match clean-tag builds and the supplied checksum. The separate two-guard MP source fix passed red-to-green native PHP checks and remains unreleased. Reflection API contracts and MP 0.1.17 metadata/tag are unchanged. The reported reflection cause remains unresolved without registration line count and catalog alias owners. Details: [hub receipt](hub-pcv-0.1.15-2026-10-04.md) and [sentinel/reflection review](sentinel-and-reflection-review-2026-10-04.md).

## PCV 0.1.14 hub snapshot — 2026-10-03

- [x] Verify the published 0.1.14 tag, source inventory, public packages and supplied DWPkg checksum.
- [x] Import the exact snapshot while preserving the dirty PCV plan and local-only files; update current hub links and feature summary.
- [x] Review sentinel/skip-reason integration impact without changing MP product behavior or reflection APIs.
- [x] Verify changed scope/source equality, commit and push the hub update, then confirm public pins and links.

PCV 0.1.13 was unreleased; current published PCV is 0.1.14 PRE-ALPHA. No new MP/PCV release, installation, runtime/provider/database or gaming-distro operation is authorized by this hub update. Source verification uses the immutable published PCV tag and packages, not the drifting sibling development tree.

Review: Commit `006ca3c4e13bddc49037e9849106d14076ab9653` updates both public main branches with the independently reviewed PCV 0.1.14 snapshot and hub links. All four published assets match clean-tag builds; the supplied DWPkg hash matches. Public README and both manifest blobs match the committed source; MP stays at 0.1.17 and its tag is unchanged. The protected dirty PCV plan remains excluded. No WSL, PHP, live provider/database, installation or game operation was performed. Sentinel-targeted wrap-up lines still lack witness-only evaluation. Evidence: [hub-pcv-0.1.14-2026-10-03.md](hub-pcv-0.1.14-2026-10-03.md).

## Public hub release visibility — 2026-10-03

- [x] Inspect the public default branch and README against the published version update.
- [x] Put current release links and the 0.1.17 feature summary above the poster.
- [x] Verify scoped Markdown/link metadata, push only documentation, and confirm the public README.

The public hub already has correct 0.1.17/0.1.12 tables, but the large image precedes them. Make the current versions visible near the title. Preserve immutable tags/packages and unrelated work; this task has no runtime or installation step.

Review: README places linked MP 0.1.17 and PCV 0.1.12 PRE-ALPHA versions before the poster and summarizes default-off overhearing without changing feature scope. Public release/asset metadata and scoped whitespace checks pass. Only README and this ledger are committed; confirm the public README after the documentation push. No runtime/package/tag changes or WSL commands are part of this task.

## Mind Poisoning 0.1.17 publication — 2026-10-03

- [x] Inspect source/evidence, remote state and protected edits.
- [x] Prepare and review PRE-ALPHA metadata and release notes.
- [x] Verify exact clean staged source and build/tag reproducibility.
- [x] Commit, push tag, verify draft/public packages, publish and advance update branches.
- [x] Record final receipt, limitations and preserved state.

Authorization and gates: [release-v0.1.17-plan.md](release-v0.1.17-plan.md). Publication does not authorize installation or gaming-distro/provider/database work.

Review: Published PRE-ALPHA tag `mind_poisoning-v0.1.17` at `bd5f5ae` in both repositories after exact-source fixtures, tag builds, draft/public byte comparisons and independent review. Default-off overhearing and the verified PCV 0.1.12 hub snapshot are included. Gaming remains untouched; no installation or live provider/database proof is claimed. Evidence: [release-v0.1.17.md](release-v0.1.17.md).

## Overheard gossip and PCV 0.1.12 hub update — 2026-10-03

- [x] Confirm approved scope, current source/pins and protected edits; assign exclusive owners.
- [x] Assess defensive-programming claim against concrete baseline call paths and counterarguments.
- [x] Verify and import the immutable published PCV0.1.12 snapshot; update hub descriptions/links.
- [x] Extend existing NPC evaluation to one opt-in bounded witness batch with per-listener persistence and diagnostics.
- [x] Run focused isolated behavior/integration checks and changed-file syntax checks in the authorized PHP environment.
- [x] Review every diff and output, return defects, and record final evidence and limits.

Plan: [overheard-gossip-2026-10-03.md](overheard-gossip-2026-10-03.md). Published MP pin remains 0.1.16 PRE-ALPHA. No installation or publication is part of this implementation task.

Review: Three exclusive gpt-6-luna/Max owners/reviewer completed the scoped work using Ponytail FULL; lead wrote no product code. Default-off NPC ACK overhearing uses one model call for the addressed NPC and up to four eligible extras, with closed response validation and independent atomic listener commits/diagnostics. Returned prompt-shape, Player lookup, observer, cleanup attribution, duplicate-preflight and failure-classification defects were corrected and reviewed. Eight focused isolated runtime/integration fixtures and eleven changed-PHP syntax checks passed against the frozen source in the explicitly authorized test clone. It was stopped afterward; the gaming distro was never entered and its VHD metadata is unchanged. The hub's PCV 0.1.12 snapshot/packages were verified against the immutable tag and actual public downloads; the protected dirty plan and unrelated files remain intact. New overhearing is UNRELEASED, with real provider/database/game acceptance pending and no concurrent provider-call reservation promised. Details and defensive-programming judgment are in the linked plan and [assessment](defensive-programming-review-2026-10-03.md).

## Mind Poisoning 0.1.16 publication — 2026-10-02

- [x] Inspect pending correction and review evidence; confirm both remote main refs and preserve unrelated work.
- [x] Prepare and review PRE-ALPHA metadata/current docs and focused release verification.
- [x] Commit explicit files, build clean source/tag and verify identical packages/checksums.
- [x] Push the new tag, verify draft downloads, publish, then advance update branches.
- [x] Verify public artifacts/manifests and record final evidence and runtime limits.

Authorization: commit, push and publish. Scope and safety gates: release-v0.1.16-plan.md. Gaming distro remains forbidden; publication does not authorize installation or live provider/database work.

Review: Release source `ace3d21` and tag `mind_poisoning-v0.1.16` are published PRE-ALPHA in both repositories. Two clean-export fixtures and four PHP syntax checks passed in the explicitly guarded test clone. All packages/checksums reproduce from the tag and match draft and public downloads. Update-main manifests match the tagged payload; historical tags/assets and unrelated work remain preserved. Receipt: [release-v0.1.16.md](release-v0.1.16.md). Longer solo replies still require companion adoption and live acceptance; no installation or live provider/database check was performed.

## Reply cap and runtime safety correction — 2026-10-02

- [x] Record the gaming-distro execution ban in root project instructions and reusable lessons.
- [x] Establish Windows-only distro state and VHD metadata baseline; record supplied pair-path evidence with its limits.
- [x] Raise reply line cap to 24 while preserving joined-text and subject bounds; distinguish unpublished source behavior in current docs.
- [x] Execute focused red/green reply checks using the disposable clone only; review diff and output.
- [x] Finish clone cleanup, compare gaming VHD metadata and record review; preserve published pin and assets.

Plan and ownership: reflection-reply-cap-2026-10-02.md. No installation, provider/database access, release/version change or publication is authorized by this correction.

Review: One shared constant covers the four initial/revalidation/persistence guards. The executed fixture proves longer replies commit once with all line IDs while count/text overflow fails without mutation; changed-file syntax checks pass. Both CHIM distros are stopped after test-clone cleanup; gaming VHD timestamp and size match the baseline. Prior environment-isolation claims were corrected, and user-reported pair-path evidence is recorded without extending it to solo/gameplay proof. PRE-ALPHA published pin 0.1.15 and unrelated dirty work are preserved; these source fixes are local and unpublished.

## Plugin dashboard — 2026-09-27

User approved implementation of the proposed two-tab read-only dashboard and delegated execution. Assume operator journal with player-friendly presentation. Preserve L-01 uncommitted work, critique.md, published pins and all protected installations. No core edits, database migrations, provider calls or deployment.

- [x] Inspect native plugin UI/auth/data conventions read-only and freeze a minimal contract.
- [x] Record implementation design, ownership and checks before product edits.
- [x] Implement authenticated bounded read-only data and plugin-only exports.
- [x] Implement accessible distinctive Interactions/Diagnostics interface with honest missing-data states.
- [x] Integrate package allowlist and user documentation; verify both archive formats.
- [x] Review all diffs, security/failure paths and focused checks; visually inspect browser desktop/mobile states and repair defects.
- [x] Record evidence, known live limits and unchanged release pins.

Initial independent discovery: runtime owns platform/auth report; influence owns data/provenance report; packaging owns visual design report. Implementation ownership/contracts follow evidence. All gpt-6-luna/max, Ponytail full; lead writes planning/evidence only. No dependent product tasks run concurrently before contract freeze.

### Dashboard review

Lead reviewed controller, data, view, CSS, manifest/allowlist and fixtures. Returned and resolved native log-prefix mismatch, overly broad event/request attribution, fabricated zero for unavailable data, bounded-read/performance gaps, Unicode/numeric display issues and preview-data contradictions. Guarded local/server-authenticated access precedes reads; forwarding headers remove loopback exemption. Actual database connection path was inspected only; isolated fixtures never connect to CHIM. Lead viewed desktop interaction/diagnostic and mobile screenshots; fresh viewport geometry shows no horizontal overflow. Native navigation/details and source-unavailable states are implemented without external assets or scripts.

Lead dashboard_data_test.php and dashboard_http_test.php passed; final dashboard_data_test.php and real-module dashboard_integration_test.php passed. Packaging suite:4 passed. Both verification-only archives under dist/dashboard-check match source: tar SHA-256 e4bdfc5b896d587c87999dbc31b263ed3b927e65afbfccbd3df44ac7c0aea015 (41008 bytes), DWPkg SHA-256 b2c435138be85f4862f571c11a6539c612a78721b1dd94f205b566ee0f70d2c9 (193743 bytes). Preview/tests/review artifacts are excluded. Existing L-01 changes and user critique remain preserved. No commit/publication/install or version/compatibility/catalog pin advancement. Native database queries, deployed remote authentication and in-game behavior remain unverified.

## L-01 safe failure diagnostics — 2026-09-27

Scope: distinguish plugin-owned connector and judgment validation failures without exposing arbitrary exception text. Preserve gameplay, return statuses, global restoration and severity. Also soften evidence-bound description to reflect client-reported excerpt validation. No live execution, release or pin changes; critique.md preserved.

- [x] Inspect published baseline, tree, previous verification and affected catches.
- [x] Runtime owner reproduces classification loss, implements minimal trusted codes, and verifies actual emitted records plus unknown-error fallback.
- [x] Independent owner reviews trust boundaries and reachable preflight/severity cases.
- [x] Documentation owner corrects description and marks unreleased source changes.
- [x] Lead reviews every diff/call path/test output, returns defects and runs focused final check.
- [x] Record final evidence, limitations and unchanged pins.

Ownership: runtime owns model/influence/hook/logger and focused fixtures; packaging owns manifest/catalog description and developer note; influence owns read-only boundary report. All use Ponytail full, gpt-6-luna/max. No dependent or overlapping implementation is parallelized. Lead writes no product code.

### Review

Lead reviewed all product, fixture and documentation diffs plus the independent boundary report. Returned and resolved over-specific provider attribution, unnecessary parser catch state, missing connector-unavailable coverage and missing terminal error assertion. Known plugin failures now carry allowlisted typed reason codes; unknown exceptions remain generic. Nine parser rejection classes retain warning severity and unchanged hook statuses. Lead model_test.php and runtime_test.php both exited 0 after final edits; expected injected persistence messages were observed. Manifest/catalog wording now reflects client-reported speech. No product logic outside failure diagnostics changed. No live CHIM/mod/database/provider execution, package replacement, commit, publication or pin advancement.

## Publish logging v0.1.2 — 2026-09-27

User authorized commit, push and publication. Preserve critique.md and protected installation. Publish a development prerelease; compatibility reference unchanged.

- [x] Check tree, prior debug evidence, remote main and release availability.
- [x] Prepare and review v0.1.2 metadata and release notes.
- [x] Verify focused fixtures and build source-matching packages.
- [x] Commit reviewed files, verify packages against clean tag, push and publish.
- [x] Download release assets, compare checksums and record outcome.

### Publication review

Published commit 02c7616 and tag mind_poisoning-v0.1.2 as a public development prerelease. Both packages matched a clean tag export before upload and downloaded GitHub assets matched local bytes/checksums. Focused logging/runtime/store fixtures passed. User critique.md remains untracked; protected CHIM/mod were unchanged. Compatibility/deployment references unchanged; catalog snippet advanced to this verified release, with no official catalog submission. Live runtime remains unverified.

## Logging debug run — 2026-09-27

User requested a bounded debug pass after logging implementation. Prior helper/runtime/store fixtures and archive checks passed. Existing uncommitted work and user critique.md must be preserved. No live CHIM/mod/configuration/database/provider changes; no publication or pin advancement. Lead coordinates/reviews; original gpt-6-luna/max owners use Ponytail full.

- [x] Inspect current tree, relevant lessons and completed logging verification.
- [x] Helper owner: inspect untested context/lifecycle/native-sink boundaries; reproduce and fix only confirmed defects.
- [x] Hook owner: inspect bootstrap/include-scope and terminal attribution gaps; reproduce and fix only confirmed defects.
- [x] Store owner: inspect cleanup/reuse/numeric reporting gaps; reproduce and fix only confirmed defects.
- [x] Lead review evidence and every incremental diff; run only checks needed for accepted changes, update artifacts only if payload changes.
- [x] Record outcome, remaining live-test limitations, and unchanged pin status.

Assumptions: this is an isolated code/fixture debug run, not permission to install or exercise protected live services. Success is evidence-backed findings/fixes or a supported no-change result, not a new feature list. Hypotheses are not findings; existing green fixtures do not prove native runtime behavior.

### Debug review

Three original owners reviewed helper, hook and persistence boundaries. Lead inspected their findings and the new composed cleanup fixture. No reachable product defect was reproduced; server payload remains byte-identical to the frozen logging-check archive, so no rebuild was needed. Added one runtime regression case proving a release exception after commit preserves confirmed commit, affinity/history and error-level cleanup attribution in the final request summary. Runtime fixture lint and execution passed (exit 0); expected injected failures are documented in tasks/debug-logging-hook.md. Helper isolated reproduction confirmed reserved-field protection and an unused diagnostic-context edge; no speculative fix was made. Native logging/PG/provider/game behavior remains unverified. Logging remains local and uncommitted; all release/compatibility/deployment pins unchanged. CHIM and installed mod were not modified.

## Structured plugin logging — 2026-09-27

Bounded extension of existing ACK/model/persistence flow, authorized by the user's request following the in-chat logging design. Preserve existing gameplay decisions and status contracts. Normal mode records attributable outcomes; diagnostic mode adds bounded stage detail. No raw prompts, credentials or uncontrolled exception messages. No CHIM core/configuration/mod edits or live database/provider calls. Existing player-guide clarification and lesson remain preserved; critique.md is user-owned. No release/deployment pin advances or publication in this task.

- [x] Inspect working tree, prior evidence, lessons and existing logging gaps.
- [x] Inspect native logging sink and freeze the smallest safe shared logging contract.
- [x] Implement and verify bounded structured emission, correlation and diagnostic filtering.
- [x] Instrument hook/model lifecycle and precise terminal outcomes; verify skips/failures/success.
- [x] Instrument persistence results and cleanup with actual committed before/after values; verify rollback and zero changes.
- [x] Document location, controls, retention ownership and troubleshooting; verify package payload inclusion.
- [x] Lead review every diff and affected call path, examine focused checks, resolve defects and record limitations.

Ownership: packaging agent first owns read-only platform evidence, then shared logger/tests and packaging/docs as assigned; runtime owns prerequest/model and runtime tests; influence owns store and dedicated store logging checks. Lead owns this plan/review and no product code. All agents use gpt-6-luna/max and Ponytail full, preserve shared edits, and await dependencies before implementation.

Contract: one plugin-local RequestLog with safe context/event/finish methods, native loaded Logger or PHP error_log fallback, plugin-only MIND_POISONING_LOG_LEVEL=debug opt-in, allowlisted fields and bounded diagnostic model rationale. Native thresholds remain respected and configuration untouched. No per-request global logger, database logging table, daemon or framework. Helper verification precedes dependent hook/store edits.

Acceptance: one correlated final summary per handled ACK under normal execution, specific gate reasons, UTC/severity/version, monotonic timings, model proposal distinguished from verified commit, bounded safe diagnostic detail, no log-sink failure changes data handling. Tests prove changed behavior using isolated fixtures, not live-runtime claims.

Helper review: returned finish-order suppression, severity, oversized counts and exact numeric affinity issues to the original owner. Corrected helper passes independent logging_test.php (exit 0), including native stub and PHP fallback, environment opt-in, safe field filtering, newline escaping, bounded rationale, idempotent finish and throwing-sink isolation. Actual native service writes are untested. Hook/store workers now integrate the frozen API with distinct ownership.

### Logging integration review

Lead reviewed all helper, hook, persistence and fixture diffs and relevant callers. Returned and resolved: premature finish suppression, native warning escape, misleading stage/severity, unbounded bootstrap decode, include-scope variable collisions, duplicate start records, missing model lifecycle events, fixture evidence not quoting speech, float precision/clamped-change counting, uncertain commit acknowledgement, and corrupt-ledger/floor mislabeling as duplicate. Processing statuses and affinity rules remain preserved. Previous player-guide clarification remains included; critique.md stays untouched.

Independent final focused run: logging_test.php exit 0, then store_logging_test.php exit 0 (includes runtime_test.php). Output: logging checks passed; runtime store checks passed; store logging checks passed. Two injected persistence failures emitted their expected safe stage reasons. Checks cover a throwing sink, native warning suppression, PHP fallback, diagnostic control, bounds/correlation, bootstrap failure, model/validation failure, mid-model Off, dedupe causes, snapshots, zero/clamped changes, cleanup exceptions and commit uncertainty. No unchanged influence/model suite or live provider/DB/game run was needed. Lead independently verified both frozen-source archives under dist/logging-check, including the new logger payload. Tar SHA-256 1d8155f4eb4bb70e337f7c7d2099e626ee9fdda8faa821085f8b095a9fa706cd; DWPkg SHA-256 0cdc05f5fd95c6de7a09873a83b3fdac66458610a41a2b066ca5b0345b79d1f7. Both published-era local v0.1.1 artifact hashes remain unchanged. All eight payload files are LF-only. Documentation/build instructions distinguish local verification artifacts from published packages. Work remains local/uncommitted; no version, release, compatibility or deployment pin advanced. CHIM core/configuration and installed mod were not modified.

## Publish v0.1.1 — 2026-09-27

User explicitly authorized commit, push and publication. Publish a development prerelease; retain runtime limitations and compatibility/deployment pins. Keep user-owned critique.md untracked.

- [x] Confirm remote main matches the reviewed baseline and the new tag does not exist.
- [x] Update release-facing docs and review their diff.
- [x] Run focused PHP checks; rebuild and verify final source-matching archives.
- [x] Commit reviewed files, push main and version tag, publish both assets.
- [x] Verify remote commit/tag and downloaded artifact hashes; record final review.

### Publication review

Published release commit c9b7883e26fdb7f4ae161180a90915c299cbd3e1 and annotated tag mind_poisoning-v0.1.1. GitHub reports a public, non-draft prerelease with both assets uploaded. Downloaded both assets and verified exact local byte equality and source payload equality; hashes recorded in tasks/verification.md. User critique.md remains untracked. No official catalog submission, installation, live runtime verification or deployment-pin change.

## Critique fixes — 2026-09-27

User authorized fixes following the independent critique assessment. Baseline `0a20770`; only user-owned `critique.md` is untracked and must stay untouched. Reuse this isolated plugin repository/branch. Protected WSL server and F: mod remain read-only. Prior debug fixes are complete and published; do not redo them.

- [x] Inspect current source state, critique assessment and prior verification; assign independent owners.
- [x] Runtime: reproduce/correct passive ACK eligibility while preserving Off/generation guards; move ambiguous Player-alias rejection before paid calls, retaining transactional checks.
- [x] Client evidence: resolve ACK text/identity contract through bounded read-only source tracing; document missing client evidence without guessing.
- [x] Runtime after policy review: correct transformed speech handling without treating undelivered logged text as heard; regression checks for valid and mismatched ACKs.
- [x] Influence: provide bounded recent same-playthrough, relevant prior judgments to the model for repetition-aware decisions; no hard cooldown or new persisted schema.
- [x] Lead: review all diffs and relevant output, return defects to owners; independent focused verification.
- [x] Packaging after source freeze: prepare a new local development candidate/version and source-matching artifacts/docs using existing packaging, without changing published tags/assets or deployment pins.
- [x] Record final evidence, remaining live-test limits and publication status.

Ownership: runtime owns prerequest/store and runtime tests; influence owns pure influence module/tests; packaging initially owns `tasks/critique-client-evidence.md`, then release metadata/docs/artifact validation after freeze. Lead owns this ledger and aggregate verification, not product code. All use Ponytail full and gpt-6-luna/max; preserve others' edits.

Decisions: retain history snapshots including zero changes; retain synchronous execution pending measurements; no capitalization/generic-name blacklist, weak quote-length heuristic, queue or daemon. Repetition is addressed with existing history context, not an invented anti-gossip policy. Local candidate preparation is authorized; external publication and live testing are separate steps.

### Critique source review

Lead reviewed both changed PHP modules, complete focused fixture diffs and their callers. MP-02 retains On plus captured/current-generation equality before and after model evaluation while allowing passive ACKs. MP-03 deliberately follows CHIM core's client-reported speech contract: exact source identity remains required, but only validated ACK text drives subjects and evidence. Client-supplied text is not independent audio proof; actual client header/echo behavior remains unobserved. MP-07 reuses the alias resolver before paid work for Player subjects and retains its transactional recheck. MP-05 sends bounded relevant prior event judgments from the same listener/playthrough; it is model guidance, not deterministic claim dedupe.

Lead returned an incorrect enabled-state proposal before implementation, brittle prompt-prose assertions, and an unbounded nested-history case to their owners; all corrected. Lead independently ran the final influence and runtime PHP fixtures: exit 0. Runtime output contained the three existing injected failure logs. A separate read-only review of the hook by the influence agent found no blocking issue. Runtime/influence source and documentation are frozen. Lead verified documentation links/fences and planned v0.1.1 catalog-version agreement, returned lost contributor limits and ambiguous history-count wording, and accepted the corrected docs. Both archives under `dist/0.1.1/` independently pass their existing source-byte verifiers. Old v0.1.0 local artifacts retain their published hashes. Package report: `tasks/critique-package-report.md`. No package-builder changes or repeat installer/extraction suite were necessary. New source/artifacts are local and uncommitted; no push, release, catalog submission or deployment-pin advancement occurred. Live client, PostgreSQL/concurrency, provider and in-game checks remain pending.

## Proactive debug run — 2026-09-27

Starting at clean `2a54ac3`. No failing user scenario supplied. Prior fixture, SQL planning, packaging, and publication gates are complete; do not repeat them without a concrete unresolved risk. This run targets fixture blind spots at identity/model, transaction/hook, and installed-loader boundaries. Installed distro/mod remain read-only; no provider calls, live DB mutations, publication, or pin advancement.

- [x] Inspect working tree, recent changes, prior plans, lessons, design and verification limits.
- [x] Influence: investigate pure subject/model boundary cases not established by existing fixtures; reproduce confirmed defects before smallest repairs. Own `server/influence.php`, `server/model.php`, `tests/influence_test.php`, `tests/model_test.php`, `tasks/debug-influence.md`.
- [x] Runtime: investigate ACK identity, transaction/dedupe and lifecycle failure paths; reproduce confirmed defects before smallest repairs. Own `server/prerequest.php`, `server/store.php`, `tests/runtime_test.php`, `tasks/debug-runtime.md`.
- [x] Packaging: inspect real loader/bootstrap and shipped entry-point compatibility against fixture assumptions, read-only product review. Own only `tests/bootstrap_check.php` if a meaningful isolated check is possible, and `tasks/debug-bootstrap.md`. Report cross-owner defects rather than editing runtime modules. Completed package-format checks need no repetition.
- [x] Lead: review every finding/diff, inspect affected callers and targeted output, return defects to original owner, and record exact evidence limits and pin status.

All agents use Ponytail full and gpt-6-luna/max; shared workspace edits must be preserved. Acceptance: each confirmed defect has reproduction/root-cause evidence and focused passing verification, or an explicit unresolved limitation; no speculative cleanup. Lead owns this ledger and final review, not product code.

### Debug review and handoff

Three independent gpt-6-luna/max agents completed bounded reviews with distinct ownership. Influence/model and installed-loader investigations confirmed existing contracts and justified no code/test changes. Runtime reproduced two defects before fixing them: malformed present dedupe state committed instead of rejecting, and a same-profile Player rename during evaluation committed stale Player context. Shared namespace validation now guards preflight and persistence; Player-subject persistence revalidates the evaluated nonempty name. Absent first use, valid other-playthrough state, and NPC-only decisions remain eligible.

Lead reviewed every changed product/test line and the surrounding hook/store call paths, returned the overwritten shared model fixture for isolation, and requested the narrow null/empty-object and NPC-only checks. Final independent runtime fixture exited 0 under WSL, with exactly the three existing injected failure logs (snapshot failure, ambiguous aliases, malformed model JSON). Worker lint for both changed PHP files and final diff checks passed. Evidence: `tasks/debug-runtime.md`, `tasks/debug-influence.md`, `tasks/debug-bootstrap.md`.

At debug-run handoff these were local uncommitted source fixes. The user subsequently authorized their commit and push to GitHub; that publication covers source and regression evidence, not a rebuilt release. Existing v0.1.0 assets still represent the earlier source and do not contain these fixes. No package/release/deployment pin advanced. No installed files, live DB, provider or game were modified/exercised; actual transaction/concurrency/provider/in-game acceptance remains pending. No broad unchanged suite was repeated.

## Public README rewrite

User approved the reader-first README direction and asked to push it. Starting state: published source/release complete; the only existing local edit is the approved README lesson in `tasks/lessons.md`.

- [x] Review current README, approved tone/structure and existing local changes.
- [x] Influence owner: replace the root README with a concise plugin-home introduction in the user's dry, humorous voice; create `docs/mind-poisoning.md` for users and `docs/development.md` for technical/release material. Preserve accurate prerelease/catalog status and package payload.
- [x] Lead: review all prose, installation claims and links; check Markdown structure and absence of machine-specific paths in public docs. No runtime tests needed for this documentation-only change.
- [x] Commit and push reviewed docs; verify the public README and linked guides. Keep published release tag/assets unchanged.

Review: root README and both guides reviewed against the accepted behavior and install route. Local links resolve, Markdown fences are balanced, public docs contain no machine-specific paths, and GitHub-rendered README HTML has the expected headings and links. `git diff --check` passed. No server, packaging, test or distribution files changed; runtime tests are not applicable. Published as `a8315cf` on `origin/main`; anonymous GitHub requests confirmed all three public documents byte-for-byte against the commit. The existing release remains a prerelease with both assets. No deployment pin advanced.

## GitHub publication

User selected and authorized `Francisco-boop-001/CHIM-Plugins` as a separate public home for CHIM plugins, with Mind Poisoning first. Existing local source is clean at `f8dcb0e`, no remote configured; accepted artifacts match the recorded hashes. GitHub CLI account verified as `Francisco-boop-001`. Protected installed roots remain read-only.

- [x] Inspect current source, working tree, artifact identity, repository state and authentication.
- [x] Finalize the confirmed repository URLs, public README and development prerelease notes; preserve payload bytes.
- [x] Lead review and commit the publication metadata; create repository, push source and plugin-specific tag, upload verified prerelease assets.
- [x] Verify the public manifest/release download paths and report actual publication/catalog status. No local game installation or deployment pin change.

Ownership: influence handles README/catalog/release-note metadata using Ponytail full; lead reviews, maintains this ledger and performs authorized GitHub publication. No new runtime work or broad test rerun is needed.

Lead reviewed all publication metadata and both archive verifiers passed against current payload source. `CHIM-Plugins` was created public under the verified account and connected as local `origin`; description identifies it as the user's plugin home with Mind Poisoning first. Source was pushed to `main`, with tag `mind_poisoning-v0.1.0` at publication commit `0a6f3a8`. The prerelease includes both verified archives. Anonymous requests fetched the exact catalog manifest URL and both assets; every byte matched local source/artifacts. GitHub confirmed `PUBLIC`, default branch `main`, `isPrerelease=true`, `isDraft=false`, and uploaded asset digests matching the recorded hashes. Official CHIM catalog submission/approval remain separate and pending. No protected installation was modified and no deployment pin advanced.

## Repository distribution follow-up

User approved preparing CHIM Plugin Manager distribution. The user's GitHub repository will be a home for multiple CHIM plugins; Mind Poisoning is the first. Account verified as `Francisco-boop-001`; repository name awaits clarification. No remote is configured. Source starts clean at `a3b54f9`; the accepted schema-4 archive and its evidence already exist and require no reimplementation. Installed distro and F: remain read-only.

- [x] Inspect working tree, package builder, installer/catalog source, prior checks and repository destination.
- [x] Packaging owner: add the repository tarball using the existing seven-file payload allowlist; preserve the existing schema-4 path. Verify deterministic output, source-byte identity, and the installed legacy installer's strip-one layout in scratch storage.
- [x] Documentation owner: prepare the shared repository introduction, per-plugin catalog entry and prerelease instructions. Use explicit per-plugin manifest/asset URLs so another plugin's release cannot become this plugin's update.
- [x] Lead: review changed call paths, tests and actual archives; resolve defects with original owners and complete evidence for the local commit.
- [x] Record exact external handoff status. Publication/catalog submission and live runtime acceptance are separate from local package acceptance.

Ownership: packaging owns `scripts/package.py`, `tests/test_package.py`, an optional focused tar extraction check, and `tasks/repository-package-report.md`. Influence owns root `README.md`, `server/README.md`, catalog/distribution documentation and `tasks/repository-docs-report.md`. Runtime PHP and payload manifest remain unchanged. Lead owns this ledger and lessons. Workers share the workspace and must preserve others' edits. Final archive build waits for documentation freeze.

### Follow-up review

Lead reviewed builder, test and extraction-harness changes, catalog schema against the installed loader, and both READMEs. Returned incorrect tar schema metadata, leftover schema-4 prerequisites and installed README source-only links to their original owners; corrected. Actual GNU tar extraction found epoch-zero timestamps incompatible with project DrvFs; fixed member timestamps at 1980-01-01 preserve reproducibility and pass the same extraction check.

Fresh lead checks: all four packaging tests passed; actual candidate extraction with GNU tar 1.34 and `--strip-components=1` passed with seven byte-identical files and scratch cleanup; both artifact validators matched current source. Runtime PHP and `server/manifest.json` have no diff, so no unchanged runtime suite was repeated. Evidence and hashes: `tasks/repository-package-report.md` and `tasks/verification.md`.

External handoff remains pending the exact repository name/URL. The connected account is `Francisco-boop-001`; no matching CHIM repository was found among accessible repositories. Catalog URLs intentionally contain `REPOSITORY_NAME` and must not be submitted as-is. No repository was created, source pushed, release/tag published, catalog submitted, or deployment pin advanced. The repository introduction explicitly identifies this as the user's future home for CHIM plugins, with Mind Poisoning first.

Future second-plugin constraint: the installed Plugin Manager matches the first catalog entry with the same repository OR package name. It needs an upstream exact-identity-first lookup before a second entry shares this repository. This does not block the first plugin or shared source hosting. Documented in the README and verification evidence; no speculative core fix or protected-tree modification was made.

## Scope and starting evidence

- User authorized plugin development and independent gpt-6-luna / max agents; lead reviews and coordinates without writing product code.
- Installed WSL distro and `F:\EldergleamNext\mods\CHIM Beta` stay read-only. Deliver source and a local package; deployment and in-game proof are not authorized by this task.
- Existing Exchange tasks concern Actorwright. No mind-poisoning project, task, candidate, or verification evidence was found here. This is a new isolated repository.
- Inspected server revision: `cf5030f15781637498be86debe26fcf102f5690d`; tracked tree clean, user runtime/untracked files present. PHP CLI 8.2.29.
- Prior viability review established hooks and directed affinity storage, not a working plugin.

## Checklist

- [x] Inspect current workspace, existing plans/lessons, prior work and installed revision.
- [x] Resolve utterance identity, relationship-write/lifecycle contract, package format and isolated verification environment; transaction implementation remains a review gate.
- [x] Record design and acceptance criteria (`tasks/design.md`); independent evaluation and packaging briefs assigned. Runtime brief follows lifecycle research.
- [x] Implement only the missing plugin behavior through assigned workers.
- [x] Review every diff and verify the targeted behavior; return defects to the same worker.
- [x] Build and inspect an installable candidate package.
- [x] Record evidence, known limits and pin status.

## Agent assignments — discovery

- `hook_flow`: exact utterance/recipient identity and model-call surface, read-only.
- `state_flow`: guarded writes, state lifecycle and save/rollback integration, read-only.
- `package_flow`: package contract and isolated PHP/PostgreSQL verification options, read-only.

## Pin status

No plugin pin or installation exists. The inspected server revision is a compatibility reference, not an advanced deployment pin. Actorwright pins are unrelated and unchanged.

## Implementation assignments

- `influence` owns pure evaluation module and its focused tests, per `tasks/influence-brief.md`.
- `packaging` owns deterministic packaging, documentation and isolated real-manager validation, per `tasks/package-brief.md`.
- `runtime` owns callback, model and transactional persistence modules plus focused checks, per `tasks/runtime-brief.md`; hook integration waits for pure-module review.
- After pure-module acceptance, the unwritten model adapter was reassigned to `influence` (`model.php`, separate `model_test.php` and report). Runtime retains store/hook ownership. Both workers were informed before dispatch; no duplicate implementation.

## Design decisions

- Use client `_speech` acknowledgement via `prerequest.php`, not generated-response postrequest globals: source proves aborted or early-ended callbacks skip postrequest.
- Use a synchronous acknowledgement evaluation with the existing playthrough lease, subject to verified connector timeout behavior. It adds acknowledgement latency but avoids a separate queue/daemon and its stale-job lifecycle.
- Explicit named subjects only, conservative signed delta -5..5 and zero permitted; no unknown identity guesses or native Skyrim rank writes.
- Core `NEVER_CLEAR_RELATIONSHIP_DATA` preserves scores while restoring plugin history. Version 0.1 must skip influence while enabled to preserve score/ledger consistency.

## Review

- Pure evaluation: complete file/test review; independent focused PHP run passed.
- Model adapter: complete file/test review; actual instance API, string IDs, factory and transitive class dependencies checked; independent focused PHP run passed.
- Packaging: builder, allowlist, fixture runner and actual-manager scratch harness reviewed; fixture checks passed. Actual payload remains gated.
- Persistence: initial review returned namespace nesting, JSON object preservation/equality, exact event state/type, fresh identity uniqueness, legacy Player alias and source-event locking defects to the same runtime worker. Native retained-connection approach replaces reconnecting core mutation helpers. Independent read-only review is also in progress.
- Persistence gate subsequently passed: fixes reviewed, independent review caught and resolved `eventlog.rowid` versus `id`, manual/profile locks aligned with core, focused store fixtures passed. Live catalog/EXPLAIN checks under forced read-only mode confirmed SQL shapes, matching full-row history columns and missing-parent JSONB semantics. No mutation or concurrency execution was performed. Hook integration authorized.
- No installation, live provider call, live database mutation or pin advancement has occurred.
- Hook gate passed: real loader scope and parser contracts verified; canonical Player name reads current `core_player`. Final independent PHP run linted all eight PHP files and passed influence/model/runtime runners. Expected fixture errors were snapshot rollback, ambiguous Player aliases, and malformed model JSON. Python packaging checks passed (3 tests). Actual archive build and scratch-manager acceptance are now authorized.
- Final audit temporarily reopened the local gate: a database-like JSON roundtrip changes nested associative PHP ledger arrays into objects; the comparison normalized only existing objects, so equivalent data compared false. Lead reproduced this without database access. Same runtime owner is correcting comparison and fixture fidelity; packaging is held until targeted verification passes.
- Reopened gate resolved: owner reproduced the failure with the strengthened namespace roundtrip fixture, normalized non-list PHP arrays as JSON objects, and passed the same check. Lead reviewed the exact fix and independently reran runtime checks (exit 0). Seven payload files are LF-only. Final packaging resumed; no remaining identified source defect.
- Final package gate passed: deterministic actual archive, source-byte verification, and installed real manager in project-local scratch roots accepted all seven payload files. Tampered archive rejected while preserving scratch state; no migration invoked. Lead independently verified the final archive against source and reviewed the final report. SHA-256: `4ab1c38bc30bd2c09876f9df47fd66853615eee33b154aade6ced3243d44b94f` (67,799 bytes).
- Handoff: version 0.1.0 is a development candidate. Native database transaction/concurrency execution, live provider quality/latency, and in-game/save-load acceptance remain unverified. Protected installations and deployment pins are unchanged. Evidence: `tasks/verification.md` and owner reports.

## Dashboard poster redesign — 2026-09-27

User rejected the prior dashboard visual treatment and supplied an approved distressed Nordic print reference. Keep safe data handling and navigation, replace the generic admin styling with original whispering-adventurers poster art, a compact ledger, and explicit persistent day/night URL mode. No version or publication changes.

- [x] Inspect current view, controller/theme contract, fixture preview, package allowlist, and prior visual evidence.
- [x] Coordinate `theme=day|night` normalization and same-origin art serving with the runtime owner.
- [x] Generate original text-free poster artwork; rebuild the selectable-title masthead and compact Interactions/Logs ledger without weakening escaping or source labels.
- [x] Preserve tab, filters, theme, and download behavior with GET navigation; extend synthetic preview checks.
- [x] Add artwork to package allowlist and update dashboard guide, correction lesson, and task report.
- [x] Verify focused PHP preview/integration checks; inspect desktop/mobile day/night and tablet Logs screenshots; record exact evidence and limits. Archive consumer verification is delegated to influence.

### Review

Implementation and visual review complete. Preview and real-module integration checks passed. Day foreground colors were adjusted after contrast review; ratios against the data ground are recorded in `tasks/dashboard-poster-review.md`. Browser captures show the local synthetic fixture only. Archive builds/source identity remain with influence and are not claimed here; this task did not change the version, deployment pins, or installed CHIM files.

## Prepare v0.1.3 release metadata and docs — 2026-09-27

Prepare a development-candidate release description for the L-01 safe reason-code fixes and packaged poster dashboard. Keep compatibility reference and development-candidate status; do not state that v0.1.3 is published before the lead's release action.

- [x] Check current manifest/catalog tags, compatibility reference, release-note convention, and implementation evidence.
- [x] Update manifest/catalog version pins to v0.1.3 without changing compatibility or release status.
- [x] Update player/operator/developer guidance and release notes; distinguish published v0.1.2 assets from the v0.1.3 candidate.
- [x] Parse/check release metadata and review only the assigned documentation diff; leave build, tag, commit, and publication to the lead.

### Review

Reviewed `README.md`, player/developer/dashboard guides, contributor notes, manifest/catalog metadata, and the new `distribution/mind_poisoning-v0.1.3.md`. The compatibility reference and `development_candidate` status are preserved; historical v0.1.2 package facts remain labeled historical. Scoped JSON/diff validation passed. This task changed no source code or archive and performed no commit, tag, or publication action; the compatibility reference and deployment pin were not advanced.

## Publish v0.1.3 — completed 2026-09-27

- [x] Review current tree and release metadata; preserve user critique and protected installations.
- [x] Run targeted release gates and verify final packages against source.
- [x] Commit source and tag mind_poisoning-v0.1.3; rebuild from clean tag export and confirm byte identity.
- [x] Push main/tag and publish development prerelease with both packages and checksums.
- [x] Download published assets and confirm matching bytes, digests, and prerelease status.

Review: release source is 7212d7b24aee80d2c5d09e26b0eb14cafd519197. Release and catalog snippet advance to v0.1.3; compatibility reference and installed deployment remain unchanged. Live database/provider/game and deployed authentication remain unverified. Full release evidence is in tasks/verification.md. Only user-owned critique.md remains outside Git.

## Manifest update flow and storage API review — 2026-09-27

- [x] Trace installed Plugin Manager/update consumers and add minimal manifest metadata plus a consumer-focused regression check.
- [x] Review supported NpcMaster/database transaction facilities against atomic affinity, dedupe and history requirements; make no storage changes without evidence.
- [x] Lead review all diffs and focused output, record limitations and unchanged release/deployment pins.
- [x] Save verified CHIM plugin development guidance in the authorized memory extension folder.

Ownership: packaging owns manifest and focused update-flow test/report; runtime owns read-only storage review/report; influence owns repository development guidance/lessons after findings. All retained gpt-6-luna/max agents use Ponytail full and preserve shared edits. Protected distro/mod remain read-only. No publication or pin advancement in this task.

### Lead review

Reviewed manifest/catalog diff and retained candidate identity; rejected misleading Stable channel rename. Reviewed isolated helper harness and removed release-version coupling. Independently ran manifest_update_check.php: all four checks passed. Built both verification-only archives under dist/manifest-check and verified source bytes plus inner schema2/outer dwpkg schema4. Read installed reconnect/setPluginData bodies and confirmed compatibility revision with explicit git -C; storage exception retained because public helpers cannot pin the composite transaction across connection failures. No gameplay/storage code changed. Added reusable project guide and lessons; saved user-authorized memory note at L:/Proyectos/Snake/codex_home_test/memories/extensions/ad_hoc/notes/20260927-chim-plugin-contracts.md. Release version/tag/deployment unchanged; published0.1.3 is still unfixed. A later version and one-time upgrade are required to distribute this change. No live DB/provider/game/install proof.

## Delegated bug hunt — 2026-09-27

- [x] Inspect current tree and prior verified fixes; preserve pending metadata work and critique.md.
- [x] Packaging owner audit update/distribution path and fix reproducible defects with focused checks.
- [x] Runtime owner audit ACK/model/persistence failure paths and fix reproducible defects with focused checks.
- [x] Dashboard owner audit reader/auth/render/filter/export paths and fix reproducible defects with focused checks.
- [x] Lead review every diff, relevant callers/failures and verification output; return defects to owners.
- [x] Record final findings, unresolved runtime limits and unchanged pins.

Ownership: packaging owns manifest/catalog/package scripts + update/package tests; runtime owns prerequest/model/influence/store/logging + matching tests; influence owns dashboard PHP/CSS + dashboard tests. Each owns tasks/bughunt-<area>.md. No overlapping edits; cross-area dependencies must be reported before changes. All retained gpt-6-luna/max workers use Ponytail full. Lead coordinates/reviews only, writes no product code. No live CHIM/mod/provider/database execution or release/pin change.

### Bug-hunt lead review

Reviewed both final code/test diffs and their callers: the dashboard reader feeds the limited-source notice; its empty-tail guard preserves unfinished-line handling. Independently ran the isolated empty/unfinished log fixture successfully under WSL PHP. Reviewed the packaging harness's exact installed member/byte comparison, tamper rejection, state preservation and migration guard; returned the duplicated thirteen-file count to its owner and accepted the nonempty guard after the focused real-manager scratch harness passed all four checks. Runtime owner reproduced the existing May/common-word ambiguity; no capitalization heuristic was added because it would reject valid lowercase names without resolving sentence-initial ambiguity. No other confirmed product defect in this bounded hunt. Reports: tasks/bughunt-dashboard.md, tasks/bughunt-packaging.md, tasks/bughunt-runtime.md. Prior metadata work and user critique preserved. Changes remain local; version, compatibility, release and deployment pins unchanged. No live database/provider/game or installed CHIM/mod execution or modification.

## Resolve common-word subject ambiguity — 2026-09-27

- [x] Runtime owner trace candidate/judgment/persistence contract and implement semantic disambiguation without capitalization regressions.
- [x] Verify negative common-word and positive lowercase-name cases with isolated checks.
- [x] Lead review all changes and evidence; update limitations, preserve pins and protected installations.

Scope: remaining MP-06 finding only. Runtime owns implementation/tests; documentation follows the accepted contract. Lead coordinates/reviews and writes no product code. No live provider, database, deployment or publication.

### Mention-guard lead review

Reviewed prompt/parser changes, every changed fixture, validation catch before persistence, and zero-delta storage path. Required subject_mentioned boolean rejects malformed or contradictory responses with no persistence; parser preserves the existing stored shape. Generic-role and ordinary-word ambiguity is explicitly judged within the existing model call; lowercase candidates remain eligible. Returned prose-locked assertions and unpublished-version wording to owners for correction. Independently ran influence_test.php and runtime_test.php: exit 0, with only the runtime suite's expected injected persistence-failure messages. Checks use fake model/database dependencies and do not prove provider language accuracy. Documentation and review distinguish that limit. Prior changes preserved; no commit, publication, version/compatibility/deployment pin or protected installation change.

## Publish v0.1.4 — 2026-09-27

- [x] Prepare release metadata/docs and inspect exact pending scope.
- [x] Run focused release checks, build source-verified packages, commit and tag.
- [x] Rebuild clean tag; publish and verify downloads before advancing main update pointer.
- [x] Record release evidence; preserve critique and protected installations.

Review: release source 7ce9b50, tag mind_poisoning-v0.1.4. Clean-tag builds match both source-verified archives byte-for-byte. GitHub prerelease published and all three downloaded assets matched local bytes and digests before main advancement. Official catalog submission and live deployment were not performed. User critique excluded; protected installations and compatibility/deployment references unchanged.

## CHIM catalog submission — 2026-09-27

- [x] Read current official submission guide, upstream PR template, and catalog; check duplicates.
- [x] Prepare pre-alpha catalog entry and reviewable PR body with evidence and unmet prerequisites.
- [ ] Resolve maintainer-discussion status and guide compatibility before submitting; never claim live tests were run.
- [x] Record submission status and link if created.

Current blockers: current guide requires main channel, root manifest, clean-server install confirmation; v0.1.4 has candidate channel, explicit nested manifest URL, and only isolated/source/package proof. Upstream PR template asks for prior Discord discussion. User asked for discussion status; protected installs remain read-only.

Review: user confirmed no prior Discord discussion and requested preparation only. Draft entry, PR body and Discord message are prepared under distribution/submission and tasks/catalog-submission.md. Lead verified JSON, unchanged transport fields and visible PRE-ALPHA warnings. No fork, PR, remote change or installation performed. Submission gate remains pending maintainer discussion, channel/root-manifest resolution, and clean-server loading proof.

## Dashboard deployment fixes and installable candidate — 2026-09-27

- [x] D01: verify host connection behavior in isolated responder; implement secure usable access and denial guidance.
- [x] D02: select recent relevant ledger activity before bounded truncation; verify over-limit case.
- [x] D03: re-encode poster, review visual fidelity, update every consumer/package allowlist.
- [x] Lead review diffs/failure paths and targeted evidence; build local v0.1.5 packages and installation guide.

Owners: runtime dashboard access/tests; influence dashboard data/recency/tests; packaging image/view/packaging/docs. Shared edits preserved. No CHIM/mod installation, provider/database calls, GitHub publication or catalog submission. Model worker remains deferred pending real latency evidence.

Review: lead's composed dashboard integration check passed. The v0.1.5 manifest preserves candidate status/channel and compatibility reference. Built and source-verified `.dwpkg`, repository `.tar.gz`, and MO2 wrapper under `dist/0.1.5/`; the wrapper's single nested package matches the directly verified `.dwpkg`. Exact sizes/hashes and image-conversion limits are in `tasks/d03-image.md`. Candidate remains unpublished; no protected install or release/deployment pin changed.

## Prepare v0.1.5 PRE-ALPHA release documentation — 2026-09-27

- [x] Replace current-candidate wording/links with v0.1.5 PRE-ALPHA release information while retaining historical release notes.
- [x] Create v0.1.5 release notes covering D-01/D-02/D-03 and truthful runtime limits.
- [x] Validate release links/text and inspect only the assigned documentation diff; do not alter payload/version/package files.
- [x] Leave commit, tag, asset publication, and publication confirmation to the lead (completed below).

### Dashboard-fix lead review

Accepted D01 fixed denial guidance after isolated Windows-to-WSL localhost forwarding returned127.0.0.1 and HTTP access tests passed; no gateway/private-range access exemption added. Reviewed D02 query/caller/fixture, returned fixture shim/catalog/overflow mistakes, then accepted actual PostgreSQL15 synthetic-reader proof and numeric catalog/tie corrections. Scratch clusters stopped/removed. Reviewed WebP side-by-side (82 percent smaller artwork) and every asset/package reference; original preserved outside payload. Independently ran dashboard_integration_test.php successfully and verified final dwpkg/tar against source plus exact nested MO2 package bytes/path. Reviewed installation steps and corrected filename/sync ordering. Local0.1.5 pre-alpha artifacts under dist/0.1.5: MO2 ZIP697184bytes, dwpkg854348bytes, tar695905bytes; hashes in SHA256SUMS. Guide docs/local-candidate-v0.1.5.md. No commit/push/publication/deployment; public0.1.4 and compatibility reference unchanged. No installed CHIM/mod mutation or live CHIM DB/provider/game verification. Synchronous model call remains pending latency evidence. Prior submission drafts and user critique preserved.

## Publish v0.1.5 — final review

- [x] Release documentation reviewed, including direct v0.1.3-to-v0.1.5 sync and unchanged dashboard access gate.
- [x] Focused release checks passed; final artifacts source-verified.
- [x] Committed ce1932c and tagged mind_poisoning-v0.1.5; clean tag reproduced all three artifacts exactly.
- [x] Published PRE-ALPHA release and downloaded all assets; exact bytes/checksums match local builds.
- [x] Approved main update only after published-asset verification.

Review: source/package gates complete. Compatibility reference unchanged; no deployment pin advanced. Live clean-server install, CHIM writes/provider/game acceptance remain unverified. Catalog submission and protected installations remain untouched. Exact publication evidence is in tasks/verification.md.

## Correct MO2 custom-content wrapper — 2026-09-27

- [x] Inspect the existing wrapper and official MO2 FOMOD extraction behavior; preserve the published v0.1.5 ZIP.
- [x] Add a distinct deterministic FOMOD wrapper that maps the unchanged `.dwpkg` to its CHIM virtual path.
- [x] Add a focused test for FOMOD mapping, exact nested package bytes, archive layout and deterministic output.
- [x] Build the local corrected wrapper and record its identity and limitations; do not install it or publish it.

### Review

Focused packaging tests and actual archive/source-byte verification passed. The FOMOD mapping sets the expected CHIM path but does not clear MO2's post-install custom-content flag. No MO2 GUI/install or profile change was performed. Evidence: tasks/mo2-wrapper-correction.md.

## Publish v0.1.5 installer revision 1

- [x] Review source-only release links/notes and focused package evidence.
- [x] Commit and tag mind_poisoning-v0.1.5-installer.1; reproduce ZIP from clean tag.
- [x] Publish separate PRE-ALPHA installer revision and verify downloaded assets.
- [x] Push main after downloads are verified; preserve runtime manifest and original release assets.

Installer.1 final review: documentation links and diffs accepted; focused tests passed; clean-tag ZIP and both published downloads matched exactly. All publication gates complete before main push. Runtime remains v0.1.5 PRE-ALPHA with unchanged compatibility reference; no deployment or modlist changes. GUI installation, live CHIM sync and game behavior remain unverified; custom-content flag may remain.

## Dashboard automatic refresh — 2026-09-28

Approved bounded design: read-only updates every five seconds with Auto pause control and last-updated feedback; preserve filters, scroll and interaction state; suspend hidden tabs and prevent overlapping requests. Reuse the existing escaped page renderer and data reader, external same-origin JavaScript only. No model calls, writes, installed CHIM/modlist changes or release/pin advancement.

- [x] Implement polling and minimal renderer/CSP hooks (runtime owner).
- [x] Add package/fixture support and focused behavioral checks (packaging owner).
- [x] Review all affected call paths, failure handling and verification output; return defects to owners.
- [x] Record evidence and remaining runtime limitations.

### Automatic refresh review

- Lead reviewed controller access/CSP, renderer regions, polling lifecycle, failure handling, package allowlist and test changes. Returned and resolved the active-tab region mismatch, missing request timeout and unstable diagnostic-detail identity before acceptance.
- Isolated browser preview: both tabs updated; timestamp advanced from 11:59:43Z to 11:59:48Z; pause retained 11:59:48Z beyond five seconds; resume advanced to 12:00:10Z while an unsaved filter remained. Logs updated from 12:00:19Z to 12:00:34Z with one open detail, SUMMARY focus and scrollY 497.6 retained. Browser error log empty. Day and Night controls visually reviewed; screenshot: tasks/evidence/dashboard-refresh-night.png.
- Focused checks passed: Node syntax and dashboard_refresh_test.js (simulated filtered GET, no overlap, failure/timeout retention, hidden-tab stale response); PHP preview self-test, dashboard_http_test.php and dashboard_integration_test.php (isolated fixtures); all seven test_package.py tests (exact JS inclusion in DWPkg, tarball and nested MO2 payload). Diff whitespace check passed.
- Read-only behavior reuses the existing GET controller and data reader; no model calls or relationship writes added. The preview server was stopped and its browser tab closed. Installed CHIM, mods and modlist were untouched.
- Version remains v0.1.5; compatibility pin and published packages unchanged. This source addition is uncommitted and unpublished. Live CHIM installation, authentication, database/provider/game behavior and real polling load remain unverified.
## Publish automatic refresh v0.1.6 — 2026-09-28

User authorized commit, push and publish. Keep PRE-ALPHA status and CHIM compatibility reference unchanged; preserve user critique and pending catalog drafts.
- [x] Prepare and review v0.1.6 manifest, current documentation and release notes.
- [x] Verify focused release gates and build all three packages.
- [x] Commit/tag approved files and reproduce packages from clean tagged source.
- [x] Publish prerelease, download and compare checksums, then advance main.
- [x] Record publication evidence and remote state.

Release preparation review: metadata/docs accepted; seven package tests, Node refresh behavior, isolated PHP dashboard integration, and read-only installed Manager/installer helper checks passed. All three v0.1.6 packages built and internally verified. Clean-tag reproduction and publication verification remain pending. PRE-ALPHA status and compatibility reference unchanged.

Publication review: source commit a596906, annotated tag mind_poisoning-v0.1.6. All three packages rebuilt byte-identically from clean git-archive tag source; initial rebuild output path was corrected to stay inside that source tree, as required by the existing builder. Added the missing JavaScript LF attribute before tagging to preserve reproducibility on Windows. GitHub release is public (not draft), explicitly prerelease. Downloaded all four published assets and compared exact bytes. Only after successful comparisons was main advanced from 78663d3 to a596906. See tasks/release-v0.1.6.md for asset hashes. Historical releases, compatibility reference, installed CHIM and modlist remain unchanged; user critique and pending submission drafts remain untracked.

## Player-origin gossip — 2026-09-28

User approved general Player-origin praise, slander and neutral gossip about resolvable third-party NPCs. Extend existing judgment/atomic persistence using a source-bound player-input path; preserve NPC speech handling, Player-listener exclusion, locks, interaction/playthrough gates, and deduplication. Installed CHIM/client/modlist remain read-only. No release/version/pin advance is part of this implementation request.
- [x] Trace actual CHIM player input source identity, target and hook timing; audit shared prompt/store/dashboard assumptions.
- [x] Assign small nonoverlapping implementation tasks after the event contract is established.
- [x] Implement and run focused positive/negative player-flow checks plus affected NPC regression coverage.
- [x] Lead review every diff and verification output, return defects to owners, record runtime limits.

Scope clarification: player-origin praise, slander and neutral gossip apply generally to any resolvable third-party NPC subject; Hawke/Lidia/Bruce are examples only. Zero-change judgments remain valid. No character-specific behavior.

Design ruling: CHIM main.php records the full player event at lines 1943–1959 before loading plugin postrequest.php at 2943. Core insert returns no rowid. Resolve exactly one row by complete type/ts/gamets/data/localts/sess tuple, never latest/text-only; ambiguity skips. Use distinct input_<rowid> correlation, explicit Player speaker kind/null NPC ID, real NPC listener, and transaction-time source/profile revalidation. Ordinary inputtext/inputtext_s/ginputtext/ginputtext_s only; narrator/broadcast/ambiguous target excluded. This is recorded player input, not an NPC audio ACK.

Ownership: runtime = prerequest/shared evaluation, new postrequest entry and runtime/player integration tests; influence = prompt/subject handling, store adapter/persistence and focused store tests; packaging = allowlisted logging/dashboard attribution, package inclusion and current docs. Lead = plan/evidence/review only. All workers preserve shared edits and protected installed roots.

### Player-origin review and evidence

- General Player praise, slander and neutral gossip now reuse the existing model validation and listener-only atomic persistence. Player identity comes from the active profile; no NPC speaker is fabricated. Exact inserted input rows use `input_<rowid>` correlation. Automatic/direct routing requires one resolved NPC listener; bystanders do not become subjects from routing metadata.
- Lead reviewed the affected hook, source binding, prompt, store, logging, dashboard, package and documentation diffs. Returned and resolved exact bigint comparison, duplicate fixture helper, early skip correlation, bootstrap Player marker and formatting issues. Runtime/store cross-review found no remaining blocking contract mismatch.
- Lead ran `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-MindPoisoning/tests/player_store_test.php`: exit 0, `runtime store checks passed`, `player store checks passed`. This includes the composed Player/NPC runtime fixture checks: positive/negative/zero decisions, automatic/direct routing, source ambiguity, listener/Player identity and alias checks, locks, replay dedupe, pre/post-model Off, speech-only prompt content, and safe bootstrap logging. Expected snapshot/alias failure-injection messages appeared. This invocation explicitly skipped the optional PostgreSQL socket check.
- Store owner separately executed the actual new queries on PostgreSQL 15 in a private disposable `/tmp/mp-player-store-*` cluster with TCP disabled. Unique/duplicate tuple lookup, row revalidation and adjacent bigint timestamps above 2^53 passed. The cluster was stopped and removed. This verifies synthetic queries, not a live CHIM transaction or concurrency.
- Focused influence, logging, dashboard-data and store-logging checks passed. Lead ran `py -m unittest discover -s tests -p test_package.py -k current_webp_artwork_and_postrequest_hook`: 1 test passed, exact new hook payload included in DWPkg, repository tar and nested MO2 wrapper. PHP hook syntax and `git diff --check` passed.
- No installed CHIM, database, mod files or modlist changed. Live provider, client delivery, game behavior and clean-server installation remain unverified. Source is uncommitted/unpublished; published v0.1.6 and compatibility reference remain unchanged. No release artifacts were replaced.
## Publish Player-origin gossip v0.1.7 — 2026-09-28

User authorized commit, push and publish. Preserve PRE-ALPHA status, compatibility reference, installed CHIM and unrelated critique/submission work.
- [x] Prepare and review v0.1.7 metadata and release notes.
- [x] Run focused release gates and build the three packages.
- [x] Commit/tag approved source and reproduce packages from clean tagged source.
- [x] Publish prerelease, download and compare all asset bytes, then advance main.
- [x] Record publication evidence and remote state.

Release preparation review: metadata accepted; composed Player/NPC, influence, logging, dashboard-data, store-logging, all seven package tests and manifest-update helper checks passed. All three v0.1.7 packages built and verified. The legacy package-manager harness initially rejected real release inputs because it requires a synthetic v0.1.0 ZIP fixture; generated the specified fixture and its 15-file scratch installation/tamper-preservation checks passed. This is not a live installation check. Tag reproduction/publication remain pending.

Publication review: f2179f0 tagged mind_poisoning-v0.1.7; all three clean-tag packages matched, all four uploaded assets downloaded and matched before draft publication. Public prerelease metadata and SHA-256 digests confirmed; main advanced only after publication. Evidence: tasks/release-v0.1.7.md. Compatibility reference and installed environment unchanged.

## v0.1.7 bug run — 2026-09-28

Scope: audit the newly published Player path and affected shared callers; fix only demonstrated defects. Installed CHIM/client/modlist remain read-only. No release/version/pin change in this task. Prior fixture, SQL and package results are recorded above; do not repeat completed work without a concrete remaining risk.
- [x] Inspect working tree, release evidence and relevant lessons; preserve critique and pending submission drafts.
- [x] Runtime owner: inspect actual hook/routing/global state contract, reproduce and fix confirmed hook defects.
- [x] Influence owner: inspect prompt/model/storage boundary and transaction/source revalidation, fix demonstrated defects.
- [x] Dashboard owner: inspect Player attribution, log correlation and dashboard failure paths, fix demonstrated defects.
- [x] Lead review changed call paths, diffs and targeted verification; record known limits and audit outcome.

Ownership is disjoint: runtime owns hook/runtime fixtures; influence owns prompt/model/store and dedicated tests; dashboard owns logger/dashboard and dedicated tests. Lead reviews and maintains this evidence only.

### v0.1.7 bug-run review

No confirmed product defect found; no product code/test changes justified. Three retained owners audited independent areas with Ponytail/systematic debugging; lead reviewed their findings and current diff.

- Hook/source contract: pinned main.php captures Player TTS source before processor/request.php:188–189 target suffix and main mood context are appended. The input row is inserted before extension postrequest. Inspected post-insert paths use copies or read the original request; top-level eventlogInsert remains available through GLOBALS. Prefix binding and speech-only judgment are consistent with this contract.
- Storage counterexample rejected: a plain active-profile SELECT does not permit switching mid-commit because the SQL constructor enters the shared work.lock request lease (playthrough_runtime.php:68–88,119–151); the core switch takes exclusive switch.lock then work.lock (playthrough_home.php:133–145) before changing the active profile. Additional locking would be redundant. Source tuple precision, actor/alias gates, strict model judgment parsing and listener locks remained consistent in the bounded audit.
- Dashboard/logging: explicit Player markers, bounded input IDs, full event/profile/listener correlation, escaping, access-before-load and read-only refresh remain consistent. The documented 100-listener selection is not exhaustive history.
- Fresh executable check: `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-MindPoisoning/tests/runtime_test.php` exited 0 with `runtime store checks passed`. Snapshot/alias error lines were expected failure injections. Previously completed package/SQL/UI tests were not repeated.
- Unknown: core decodes special execution_mode values separately from target_mode. Narrator/everyone targets and narrator_inputtext are rejected, but the searched readable client script formats did not establish whether a special execution mode can accompany an eligible ordinary direct/automatic request. No failure was reproduced; do not add speculative guards. Capture the actual routing envelope during isolated in-game acceptance to resolve this.
- Live database/provider/client/game execution remains unverified. v0.1.7 release, compatibility reference, installed CHIM and modlist unchanged. Only this local audit checklist/evidence was updated; no commit/push/publication performed for this bug run.

## MO2 ZIP crash investigation — 2026-09-28

User reports MO2 crashes when installing the archive from its Downloads list. Prior archive byte/mapping checks do not prove native installer compatibility. No retry in the user's MO2, modlist/profile changes, or installed CHIM changes authorized.
- [x] Packaging owner: inspect actual release ZIP, primary MO2/FOMOD requirements, and recent read-only installer logs; establish crash cause before editing.
- [x] Assess reproduction/fix evidence: exact crash cause not established; no speculative product fix. Provide separately labeled plain-ZIP workaround.
- [x] Lead review evidence/diff and targeted verification; distinguish native MO2 proof from format validation.

### MO2 crash investigation outcome

- User reports crashing when installing the ZIP from MO2 Downloads. Read-only logs show FomodPlusInstaller::install for mind_poisoning (tree size 2) immediately before the latest dump. This is temporal correlation, not a faulting stack. The downloaded ZIP SHA-256 matches the published wrapper exactly, ruling out download corruption.
- The minidump has no ExceptionStream; no exception code/faulting address is available. Installed FOMOD Plus DLL has no usable PE version resource, and its March 2025 timestamp could not be matched to a source commit. Current upstream source explicitly supports empty installSteps and independently processes requiredInstallFiles, so omission of steps is not a proven root cause. No invented installer step or product-code patch was made.
- Separate workaround: dist/0.1.7/mind_poisoning-0.1.7-mo2-plain.zip, 704954 bytes, SHA-256 31a084530fa1d3184f3e73bf00bbedcba3e6d22f0d05d9f6055bad7eb43bfb29. Reuses the existing plain sync-ZIP builder; contains only CHIM/server-plugins/mind_poisoning/0.1.7.dwpkg. Lead verified CRC, exact member list, absence of FOMOD metadata, and byte identity with the published DWPkg. It avoids the FOMOD metadata route but is NOT a confirmed native MO2 crash fix; the custom-content warning may remain.
- No user application launched, no repeated crash requested, no modlist/profile/installed CHIM changes, no debugging tool installed. Product source and public release unchanged. Native reproduction and exact fault diagnosis remain unresolved; workaround is local and unpublished.

Crash evidence paths (read-only): F:/EldergleamNext/logs/fomodplus.log; F:/EldergleamNext/logs/mo_interface.log; F:/EldergleamNext/crashDumps/ModOrganizer-2.5.2-20260929T012800.dmp; F:/EldergleamNext/plugins/fomod_plus_installer.dll.
Upstream references: https://github.com/aglowinthefield/mo2-fomod-plus/blob/main/installer/FomodPlusInstaller.cpp ; https://github.com/aglowinthefield/mo2-fomod-plus/blob/main/installer/ui/FomodViewModel.cpp ; https://github.com/aglowinthefield/mo2-fomod-plus/blob/main/installer/lib/FileInstaller.cpp . Current upstream is not confirmed identical to the installed binary.

## MO2 content validation correction — 2026-09-28

User screenshot confirms plain CHIM-only ZIP still triggers red invalid-data warning. Fix the actual MO2 game-data checker contract with a useful, non-gameplay payload layout, preserving the exact DWPkg; do not claim CRC/XML checks prove native acceptance. Installed environment remains read-only.
- [x] Packaging owner: inspect primary MO2 Skyrim content checker and identify smallest legitimate accepted layout.
- [x] Determine correction: archive already matches CHIM; no honest checker-green archive-only change exists. Correct documented manual-installer workflow instead.
- [x] Lead review payload, verification and limitations; retain exact-payload plain workaround and correct instructions.

### Content validation findings and supported continuation

CHIM author guidance, official Modders Guide and CHIM-Custom sample agree on Data/CHIM/server-plugins/<name>/<version>.dwpkg. Existing plain ZIP matches that path. The Skyrim SE ModDataChecker accepts known game folders/extensions, excluding CHIM and dwpkg; moving the data root cannot turn this server-only payload green without adding irrelevant content. No dummy game file/config or additional MO2 plugin was introduced.

Official modorganizer-installer_manual src/installdialog.cpp on_okButton_clicked explicitly offers Ignore after failed testForProblem; Ignore calls accept. src/installermanual.cpp returns RESULT_SUCCESS for QDialog::Accepted. Correct instruction: retain CHIM under <data>, click OK then Ignore in Continue?; do not select inner CHIM as data root. This confirms upstream supported behavior, not exact installed binary execution. Updated docs/mind-poisoning.md to describe this path and the unresolved published FOMOD crash; plain workaround remains local/unpublished.

Secondary FOMOD check: installed DLL SHA-256 736b43abb6c6c9869583df9bb44f26e0461d72d516fc49a1032fb4d24c00c716 did not match closest prior official v1.11.0 DLL f9f5242e7dc848e78d2b1457663ab577066249d7edd66366b0ebcb981a19980b. Exact source remains unknown. No claim that adding installer steps fixes the crash. Scratch download removed; installed files untouched.

References: https://dwemerdynamics.com/chim/modders-guide.html ; https://github.com/ModOrganizer2/modorganizer-game_bethesda/blob/master/src/games/skyrimse/skyrimsemoddatachecker.h ; https://github.com/ModOrganizer2/modorganizer-installer_manual/blob/master/src/installdialog.cpp ; https://github.com/ModOrganizer2/modorganizer-installer_manual/blob/master/src/installermanual.cpp . No release or pin changes; native user install/sync still awaiting confirmation.

## Installed dashboard Forbidden — 2026-09-29

User confirms game recognized plugin but Plugin Page returned dashboard 403. Recognition is installation evidence, not successful gossip/provider/database proof. Preserve local/authenticated-remote access policy and read-only installed environment.
- [x] Capture actual URL/client route; verified the actual localhost endpoint before further log/config inspection was needed.
- [x] Distinguish wrong origin from forwarding/access-gate incompatibility: direct WSL IP was denied, localhost succeeded; no code change justified.
- [x] Review evidence and provide exact working route without weakening access controls.

Installed dashboard verification: user supplied http://172.17.226.57:8081/HerikaServer/ext/mind_poisoning/dashboard.php (remote-origin denial). A single read-only Windows Invoke-WebRequest to http://localhost:8081/HerikaServer/ext/mind_poisoning/dashboard.php returned HTTP 200, title Mind Poisoning — Interactions and Logs, Forbidden=false. This is actual installed HTTP access evidence, not a full visual, relationship write, provider or gameplay test. No server/config/modlist/code changes. Use CHIM's main UI through the same localhost origin for subsequent Plugin Page links.

## Dashboard navigation repair and first live evidence — 2026-09-29

User requests a code fix for direct-WSL-IP Plugin Page navigation and asks to test messages now arriving. Preserve local/authenticated-remote authorization; do not trust gateway/private ranges as authorization. Installed CHIM/modlist remain read-only. Local source changes authorized; publication/deployment not requested.
- [x] Dashboard owner: implement one-time localhost navigation from Plugin Page without weakening access.
- [x] Runtime owner: inspect recent installed logs/ledger read-only to identify real evaluations, skips and commit evidence.
- [x] Lead review code/evidence and focused HTTP/integration checks; establish why the controlled game trial must wait.
- [ ] Establish which CHIM playthrough profile belongs to the loaded disposable save before running the relationship trial. No profile switch or live write authorized/performed.

### Review and live-test limits

Local navigation correction changes manifest config_url to request local=1. An otherwise denied GET receives a bodyless localhost redirect using validated server port/path; the marker is removed and the follow-up still requires existing authorization. Host and forwarding headers grant no access. Lead reviewed controller, manifest, HTTP/integration tests and docs; reran both focused PHP tests successfully; git diff --check passed. These are isolated fixture tests, not deployment proof. Installed localhost endpoint previously returned 200; installed code remains unchanged. No version, compatibility pin, release asset or publication changes.

Read-only runtime evidence: installed source matches v0.1.7. Six request_finished records at 2026-09-29 09:26:46–09:28:15 UTC are preflight/invalid-payload skips with model_outcome=not_called. No accepted utterance ID is logged. Bootstrap captures valid IDs before full validation, narrowing this to invalid JSON/object or absent/non-string/invalid-format ID; actual request shape is unavailable, so no speculative parser fix was made.

The same configured dwemer database used by core has four playthrough profiles and zero active rows. Auto-switch/session settings are absent; core defaults auto-switch off, so a connected game does not imply an active profile. Current Lidia Sobieska (5124) to Bruce Wayne (2905) affinity is 7, with no plugin ledger, but this is not a save-attributed baseline. User confirms the disposable save is loaded and CHIM connected. Await profile association; selecting a stored profile can restore server data. No paid model calls, database writes, installed files or modlist changes were made by this investigation.

User subsequently confirms they have never configured Playthrough Saves. Do not treat this as user error: the installation guide does not explicitly state the plugin's active-profile dependency. Investigate the supported current-state registration flow before recommending any switch; do not invent an unprofiled identity or weaken deduplication/save attribution.

Bounded core UI review: first-time Setup captures current state and creates an active default only when the profile table is empty. Four existing rows hide that form and make its handler return without activation. New Playthrough Save captures an inactive copy; Restore activates by loading its snapshot into live tables. No registration-only action is exposed for this populated/no-active condition. Therefore hold the game trial; do not restore an unknown profile as a workaround. Supporting ordinary unprofiled CHIM needs a separately justified identity/deduplication design, not removal of the active-profile guard.

## Unprofiled CHIM compatibility fix — 2026-09-29

User authorizes source correction for CHIM running without Playthrough Saves. Preserve existing dirty work, installed read-only boundary and current release/pin. Lead reviews; workers implement.
- [x] Trace existing core context identity and all plugin consumers; choose smallest truthful scope with transaction recheck and deduplication.
- [x] Implement shared context correction and dashboard sibling path with distinct ownership after contract is agreed.
- [x] Reproduce zero-active-profile failure and verify ordinary operation, explicit profiles, ambiguous/missing identity, duplicates and context switches with focused checks.
- [x] Lead review every diff and verification; update documentation and record remaining live-test limits.

Accepted contract: CHIM auto-switch off returns before storing/binding a playthrough identity. Player name is not a save key. Zero active profiles therefore resolves to the exact reserved `unprofiled` shared-database scope; one active retains its numeric identity; ambiguous/invalid profiles fail closed. Reuse a shared resolver for runtime and dashboard, retain locked context/player/source rechecks and existing ledger dedupe. No unique Skyrim-save isolation is claimed for unprofiled mode. Runtime owns store/core tests; influence owns logging/dashboard/tests; packaging owns three relevant docs. All installed surfaces remain read-only.

Core-source refinement: `playthrough_profiles` is created by Playthrough Saves setup migrations, so an ordinary never-configured server can lack the table. A successful PostgreSQL catalog lookup confirming absence may select unprofiled scope; query/permission failures must remain errors. Apply the same rule in runtime and dashboard and exercise it in isolated PostgreSQL.

### Review and verification

- Shared resolver now covers both gossip preflights and the existing locked persistence recheck. The runtime profile query returns a guaranteed aggregate row, distinguishing an empty profile set from malformed/failed wrapper results. Numeric profile IDs remain bounded and canonical; player names are not fabricated from non-string values. Ambiguous profiles remain rejected.
- Logger accepts the exact reserved scope only for playthrough_id; NPC/event IDs remain numeric. Dashboard uses the shared resolver, preserves text bounds, matches only same-scope ledgers/logs, and labels unprofiled history Shared server. Docs distinguish this unreleased fix from published v0.1.7. Lead returned assertion, malformed-identity, and catalog/search-path defects to their owners; reviewed corrections and all affected call paths.
- Behavioral RED: final regression run against a disposable copy with HEAD's old store returned NULL instead of expected unprofiled/Hawke context. Scratch copy removed. Corrected runtime_test.php passed; lead independently reran it (two expected injected persistence-failure messages, then runtime store checks passed).
- logging_test.php and dashboard_data_test.php passed after their original sentinel rejection failures; lead independently reran both. Render checks verify shared-server labels and numeric-ID boundaries.
- Actual isolated PostgreSQL gate passed via dashboard_recency_test.php, including both dashboard and PostgresStoreDb queries: zero/one/multiple profiles, absent optional table, missing player, ledger isolation and existing recency caps. Disposable cluster /tmp/mp-d02-recency-20260929-influence-final was stopped and removed. This is real synthetic SQL evidence, not live CHIM/provider/game proof.
- git diff --check passed. Updated tasks/lessons.md with optional-feature dependency lesson. Prior dashboard-navigation/installer edits and unrelated untracked files preserved. No installed CHIM/modlist/live database changes, paid provider calls, commits, release assets, versions or pins advanced.

Remaining limits: unprofiled scope is one shared server timeline, not unique Skyrim-save isolation. The six observed invalid-payload ACKs remain a separate unresolved client-contract issue. Install/release and controlled game/provider testing are still pending; current installed v0.1.7 does not contain this correction.

## Publish v0.1.8 PRE-ALPHA — 2026-09-29

User authorizes commit, push and publication. Preserve installed environment and unrelated critique/submission drafts; compatibility reference remains unchanged.
- [x] Prepare/review current release metadata and notes, using the existing plain MO2 ZIP builder with an explicit content-warning limitation.
- [x] Run focused release gates and build packages; commit exact reviewed files and create version tag.
- [x] Rebuild from clean tag source and compare packages; upload draft prerelease, download assets and verify hashes before public publication.
- [x] Publish, advance remote main, record final asset and commit evidence.

Release gates before source commit: runtime, logging, dashboard-data, dashboard HTTP/integration, manifest-update helpers and all seven packaging checks passed on v0.1.8. Actual repository tar extracted with GNU tar --strip-components=1 in scratch; all 15 allowed payload files matched source. Plain MO2 ZIP contains only the versioned CHIM package, with CRC and exact embedded-DWPkg equality verified. The implementation's isolated PostgreSQL evidence remains recorded above; no repeat live test or provider call was made. Build outputs are under dist/0.1.8; clean-tag and uploaded-byte checks remain publication gates.

Publication review: source commit dd3597412c868c6488f73e396de1d7b8404cd73e, annotated tag mind_poisoning-v0.1.8. All three packages rebuilt byte-identically from clean tag export. All four draft assets were downloaded and compared byte-for-byte; checksum entries verified. GitHub release is public, not draft, and marked prerelease; public asset digests match. Remote main advanced only after publication. See release-v0.1.8.md for hashes. No installed environment changes; unrelated critique and submission drafts remain untracked and uncommitted. Compatibility reference unchanged; live-game/provider testing and invalid-payload ACK diagnosis remain open.

## Focused v0.1.8 bug hunt — 2026-09-29

Starting tree: bbf55fc, tracked files clean; critique/submission drafts remain untracked. Existing release and SQL evidence reviewed. No deployment/publication/version change authorized in this run.
- [x] Runtime owner: inspect resolver, transactional rechecks and dedupe for remaining demonstrable defects.
- [x] Dashboard owner: inspect navigation, scope correlation, sanitizers and rendering trust boundaries.
- [x] Hook owner: inspect actual Player/NPC integration flow without repeating inconclusive raw-ACK searches.
- [x] Reproduce justified findings, assign minimal owned fixes, review diffs and focused verification; record unknowns separately from confirmed defects.

Interim review: runtime found no confirmed defect in the shared resolver, malformed query handling, locked rechecks or dedupe. A possible profile-switch race was rejected because the core SQL constructor holds the shared runtime lease and switching takes exclusive switch/work locks; no new test was warranted. Hook review confirmed main.php dispatches postrequest hooks and inserts the required event snapshot fields, while ScriptQueue and the core speech handler both use utterance_id. No source-contract mismatch was demonstrated. Six invalid-payload observations still lack original request bodies; no speculative parser change was made.

### Final review

No new confirmed defect found in this bounded pass. Dashboard handoff retains fixed localhost authority and authorization on the subsequent request. A numeric-validator suspicion (19-digit values above signed BIGINT) did not produce a real correlation defect: core row IDs and stored event IDs are bounded by PostgreSQL/PHP integer handling, and Player input IDs enforce the range. No validator churn was justified.

Lead reviewed all three findings against existing caller/lock/test evidence. No product code or tests changed; existing fresh v0.1.8 release/SQL checks were not rerun without a remaining testable risk. Only this task record changed. Installed CHIM, live data and modlist untouched; release/version/compatibility pin unchanged. Live provider/game acceptance and the unavailable original invalid-payload requests remain unresolved, not certified by this review.

## Maintainability reassessment — 2026-09-29

- [x] Check request/storage flow for demonstrated maintenance costs rather than file length.
- [x] Check dashboard/log validation duplication against distinct trust boundaries.
- [x] Review findings; make only justified corrections and verify any changed behavior.


### Review
Two retained reviewers and lead inspection found no demonstrated maintenance cost justifying a refactor. Player and NPC paths already share evaluation and persistence; preflight and locked rechecks serve different moments. Persistence keeps transaction sequencing and cleanup visible. Logger emission and dashboard parsing have different input/output contracts, including omission of debug reasoning from dashboard records. File length and overlapping validators were insufficient grounds for the earlier technical-debt implication. No product or test changes; tests were not rerun for a source review. Release, pin and installed environment unchanged.

## Adversarial pattern and CHIM extension audit — 2026-09-29
- [x] Inspect plugin patterns and corresponding CHIM extension contracts read-only.
- [x] Challenge candidate gaps with assumptions, counterexamples and failure paths.
- [x] Review evidence and report ranked surviving findings; no implementation authorized.

Review: three reviewers and lead source inspection completed. See pattern-gap-audit-2026-09-29.md for findings, assumptions, counterarguments and failure paths. Confirmed diagnostic granularity gap; conditional persistence wait risk; synchronous evaluation and independent-pause design candidates; upstream writer/catalog constraints. No code changes, live writes, provider calls or test reruns. Inherited DB timeout settings and live effects remain unknown. Release and pin unchanged.

## Implement accepted audit recommendations — 2026-09-29

Scope: bounded diagnostic subreasons, transaction-local wait bounds, independent plugin pause. Prior audit and user acceptance provide design authority. Background worker remains a separate planned architecture task; upstream writer/catalog changes and speculative in-flight claims are excluded. Preserve installed CHIM/modlist and all prior dirty/untracked work; no release/version/pin advancement.
- [x] Hook owner: precise content-free ACK rejection reasons and targeted regressions (prerequest.php, runtime_test.php).
- [x] Storage owner: inspect inherited limits, implement safe transaction-local bounds and isolated contention proof (store.php, dedicated test).
- [x] Control investigator: choose smallest CHIM-compatible independent pause; no overlapping product edits.
- [x] Hook owner after diagnostics: implement approved pause mechanism and in-flight recheck tests.
- [x] Documentation owner: instructions and limits matching reviewed implementation.
- [x] Lead: inspect every diff/caller/failure path, return defects, examine focused checks and record final review.


Review checkpoint: ACK diagnostics RED/GREEN evidence reviewed; preserve skipped status and warning severity, including distinct utterance-ID codes. Pause design selected operator-owned bounded JSON at CHIM data/mind_poisoning.json, absent enabled, invalid/unreadable fail closed, reread at preflight/post-model; no dashboard mutation endpoint. Database test returned for production-path cleanup proof rather than manually cleaning up in the test.


Storage review: lead inspected owned-BEGIN placement, native connection use, strict-limit preservation and rollback/release paths. Isolated PG15 test exercised production persistJudgments under row contention and passed settings restoration, no-write/no-commit and released-lock assertions; cluster stopped/removed. Stage-specific errors retained because current sync pg_query failure has no structured SQLSTATE; no localized error parsing added. See store-timeout-report.md.

### Final implementation review
Three retained gpt-6-luna/max agents owned hook/tests, storage/test, and control investigation/docs; lead wrote no product code. Independent hook review and lead inspection preserved valid source correlation, existing statuses, warning severity, privacy allowlists and transaction sequencing. Returned defects were corrected by their owners: warning severity/outcome drift, manual-cleanup-only contention test, unnecessary existing data/main symlink rejection, duplicate JSON keys, and operator-command read permissions. Duplicate-key RED reached a fixture commit; the fixed literal one-boolean format rejected both orders before model/persistence, and scoped independent re-review was clean.

Verification: final runtime_test.php GREEN covers both source paths and pause changes after model evaluation; touched PHP lint passed. Isolated PostgreSQL 15 verified disabled/stricter/looser timeout settings, real statement cancellation, production rollback/no writes/no commit under row contention, session restoration and advisory release; scratch cluster removed. Source/doc diff checks passed. No broad suite repetition, installed CHIM writes, live provider calls or Skyrim proof. See ack-diagnostics-report.md, store-timeout-report.md, pause-runtime-report.md and pause-control-report.md for commands/evidence.

Limits: pause is an operator file, checked preflight/post-model, not an atomic cancel or dashboard button. Timeouts bound individual database operations, not total request time, and retain stage-specific failure reasons. Background processing remains separate; upstream writer/catalog issues and conditional in-flight dedupe remain deferred as in the accepted audit. Published v0.1.8, compatibility reference cf5030f15781637498be86debe26fcf102f5690d, release assets and installed environment are unchanged. Changes remain uncommitted/unpublished; prior dirty notes and untracked critique/submission material preserved.

## Prepare v0.1.9 PRE-ALPHA release — 2026-09-29

- [x] Bump only the manifest version and prepare current README/release notes; preserve the CHIM compatibility reference.
- [x] Run release gates and build the repository archive, CHIM sync package, plain MO2 ZIP, and SHA256SUMS.txt.
- [x] Rebuild from the clean tag and verify all package bytes/checksums.
- [x] Publish the verified tag/assets before advancing main.
- [x] Record commit, tag, asset hashes, and publication evidence.

Release review: runtime and logging fixtures, touched PHP lint, installed manifest-update helper checks, and seven packaging tests passed. GNU tar strip-one extraction matched all 15 allowlisted payload files. The prior isolated PostgreSQL timeout/rollback proof was reviewed, not repeated. Lead reviewed code and release metadata; compatibility reference unchanged. Clean-tag rebuild and publication verification remain pending below.

Final publication review: v0.1.9 PRE-ALPHA is public. Clean-tag rebuild and downloaded-asset byte/checksum comparisons passed before main advanced. Source commit 0502c979b1622ebd62592bfcf2850908acbd6153; see release-v0.1.9.md for hashes and limits. Installed environment and compatibility reference unchanged.

## A-01 acknowledgement classification correction

- [x] Code owner: preserve exact correlation; classify otherwise valid missing/empty-string ID ACKs as info skips, malformed IDs as warnings; prove no model/write and valid routes.
- [x] Docs owner: attribute decoded-request evidence, correct obsolete unresolved wording, and clarify one installation route/database isolation without changing historical tags or live files.
- [x] Lead: review all diffs and targeted verification; record limits. No version/pin, publication or installed changes.

A-01 verification: the new missing-ID fixture failed against the old code (expected untracked-speech, got invalid-payload). After the small ACK-only guard change, runtime_test.php and logging_test.php passed; PHP lint passed for prerequest.php, logging.php and runtime_test.php. Cases cover absent/blank IDs, absent correlation field, no model call/affinity/ledger/history write, wrong-type/null/array and NUL/malformed IDs, plus malformed speech fields. Independent consumer review found existing skipped outcome/reason parsing sufficient; no dashboard or logger changes required. These are fixture checks, not live delivery proof.

Final review: lead inspected the ACK guard, logging summary path, tests and documentation. Missing/blank IDs remain ineligible; other malformed fields and IDs retain warnings. Fixed documentation attribution and avoided a link to the untracked critique. One-route installation and database isolation guidance clarified. Changes are uncommitted/unreleased; manifest version, compatibility reference, published artifacts and installed CHIM remain unchanged. Live acceptance and installation are separate pending work.

## Prepare v0.1.10 PRE-ALPHA release — 2026-09-29

- [x] Bump the manifest version and prepare the current README and release notes; preserve the compatibility reference and prior release history.
- [x] Run release checks and build the repository archive, CHIM sync package, plain MO2 ZIP, and SHA256SUMS.txt.
- [x] Verify clean-tag rebuilds and release assets before publication.
- [x] Publish only after the release gates pass, then record commit, tag, asset hashes, and publication evidence.

Release review: prior focused A-01 runtime/logging RED/GREEN and lint evidence retained; product diff unchanged. Seven packaging tests and four installed manifest-helper checks passed for release preparation. GNU tar strip-one extraction matched all 15 payload files. Clean-tag and uploaded-byte checks pending. No installed changes.

Publication review: v0.1.10 is public as PRE-ALPHA; clean-tag and downloaded-asset byte/checksum comparisons passed. Main advanced after publication. See release-v0.1.10.md for hashes and verification scope. Installed environment and compatibility reference unchanged.

## Document live acceptance progress — 2026-09-30 UTC

- [x] Record user-supplied live outcomes and distinguish direct read-only checks from screenshots/log excerpts.
- [x] Record NPC-origin test as not performed and retain PRE-ALPHA status; review evidence for overclaims.

Review: reconciled four event records with supplied screenshots and the direct pre-test lock query. Explicitly excluded context injection from NPC-origin proof, retained incomplete-test limits and unconfirmed unlock state. Documentation only; no live modifications or release changes.

## Review author-supplied runtime reference

- [x] Compare runtime/storage and installation contracts with relevant inspected source; distinguish unstable documentation from installed version.
- [x] Update reusable CHIM skill with documented contracts and provenance; validate and sync installed skill.
- [x] Review findings, correct justified documentation gaps, and record unresolved compatibility/runtime limits. No deployment or release authorized.

Review: completed source review against installed CHIM cf5030f15781637498be86debe26fcf102f5690d; no product-code change justified. Documented version-specific execQuery return behavior and unresolved client compatibility. Updated documentation and installed skill; validator, reference checks, byte equality and diff whitespace checks passed. No runtime examples, live writes, deployment, release or pin changes.

## A-02/A-03 compatibility corrections

- [x] Verify affected core contracts and preserve prior dirty documentation.
- [x] Normalize ACK IDs and gate Player input on effective speech mode; add focused regressions.
- [x] Clarify non-speech testing and single-version installation guidance.
- [x] Review diffs and regression evidence; no deployment or release.

Review: lead reviewed the full product/test diff and affected logging/dashboard consumers; both reproduced defects now pass the existing runtime fixture suite. Effective mode is CHIM_EXECUTION_MODE (not the critique variable name). PHP lint and diff checks passed. Documentation marks changes unreleased. No version, compatibility reference, installed CHIM, database, provider or release changes.

## Publish v0.1.11

- [x] Prepare and review candidate metadata; run release packaging gates.
- [x] Commit explicit files and tag; rebuild clean tag and compare packages.
- [x] Upload draft assets, download and verify, publish before advancing main.
- [x] Verify public release and record evidence; preserve installed environment.

Review: v0.1.11 published as PRE-ALPHA after clean-tag and downloaded-asset equality checks. Main advanced only after publication. See release-v0.1.11.md. Compatibility reference and installed environment unchanged.

## Drama-Llama dashboard art and title — 2026-09-30

- [x] Inspect the supplied illustration against the existing poster layout and preserve the main Mind Poisoning heading.
- [x] Add the Drama-Llama subtitle and integrate the source PNG/runtime WebP without changing dimensions.
- [x] Render the synthetic desktop/night and mobile/day previews; review subtitle contrast and llama visibility.

Review: PHP lint and existing dashboard preview self-test passed using WSL PHP 8.2.29. Headless Edge captures of the synthetic fixture at 1440px/night and 390px/day showed the subtitle, central scene and background llama; both had no document overflow and loaded the 1536×1024 artwork. Evidence: drama-llama-desktop-night.png and drama-llama-mobile-day.png. Runtime WebP is 547,258 bytes. The temporary preview process was stopped. No release, deployment, compatibility pin, CHIM installation, database or modlist changes.

## World of Drama-Llama publication - 2026-09-30

- [x] Prepare compatible MP 0.1.12 and PCV 0.1.4 source and informative guides.
- [x] Review source, focused checks and independent README findings.
- [x] Commit and tag; compare clean builds and downloaded draft assets.
- [x] Publish verified PRE-ALPHA releases before updating main; verify public source/manifests.

Review: release evidence and authoritative hashes are in world-of-drama-llama-publication-2026-09-30.md. Installed/runtime certification remains unclaimed.

## Optional reflection diagnostics API — 2026-09-30

- [x] Establish existing evaluation, logger, persistence and PCV importer contracts; account for dirty contributor work.
- [x] Add failure-contained sanitized RequestLog observer with compatible constructor and normal delivery.
- [x] Carry validated reflection configuration correlation and truthful terminal model/persistence outcomes.
- [x] Execute isolated cross-plugin checks for success, zero, failure, uncertain commit, skips, stale/replay and no-observer behavior.
- [x] Review all diffs and verification evidence; document the API and remaining integration limits.

Scope: Mind Poisoning source, focused tests and documentation only. Private Conversation is read-only evidence, including the dirty embedded copy. No metadata/version/package edits, installation, live provider/database calls, publication or release pin changes.

Review: Optional observer API and validated reflection configuration correlation implemented. Focused logger/store/reflection checks and the real PCV importer fixture passed; malformed registrations now warn, and uncertain commits remain errors. Lead reviewed all owned diffs and privacy/failure paths. See reflection-diagnostics-api-2026-09-30.md for exact commands, source hashes and limits. No installation, publication, metadata or pin change.

## Bug hunt after reflection diagnostics API — 2026-09-30

- [x] Inspect current tree, prior checks, contributor instructions and pending integration work.
- [x] Audit observer delivery/sanitization, reflection authorization/replay, and ordinary evaluation/persistence in separate owned scopes.
- [x] Reproduce concrete defects before the smallest root-cause fixes; reject unsupported hypotheses and preserve existing work.
- [x] Review changed call paths and focused verification; record findings, counterexamples and remaining limits.

Scope: MP source and isolated checks only. Standalone and embedded Private Conversation are read-only integration evidence. Existing API changes stay intact. No live CHIM/provider/database use, metadata/pin updates, installation or publication. Lead owns task evidence and review; implementation stays delegated.
Review: Four proven defects fixed: illegal affinity silently clamped, callback PHP warnings escaped, invalid reflection persistence finished as info, and dashboard illegal current affinity shown as normal. Lead reviewed all owned diffs and independently ran five focused regression gates plus PHP lint/whitespace checks. No installation, publication, metadata or pin change. Exact evidence and counterarguments: bug-hunt-reflection-api-2026-09-30.md.

## Mind Poisoning v0.1.13 publication — 2026-09-30

- [x] Review only MP diagnostic API and bug-hunt changes; preserve unrelated Private Conversation work.
- [x] Prepare PRE-ALPHA version metadata and notes; review the package allowlist and pass focused fixture gates.
- [x] Commit explicit files; build from the clean commit, tag and compare clean-tag packages.
- [x] Push tag, upload draft assets and verify downloaded hashes; publish before advancing main.
- [x] Verify public release, main manifest and immutable tag; record review and remaining runtime limits.

Scope: User authorized commit, push and publication. No CHIM installation, live provider/database calls, modlist edits or catalog submission. Compatibility reference remains unchanged. Lead owns release evidence and review; metadata preparation stays delegated.

Review: v0.1.13 PRE-ALPHA published after clean commit/tag rebuild equality and downloaded-asset checksum verification. Main advanced only after publication; public manifest and annotated tag match. Compatibility reference, installed CHIM and pending Private Conversation work are preserved. Exact evidence and runtime limits: release-v0.1.13.md.

## Cross-plugin critique fixes and publication — 2026-09-30

- [x] Inspect current source, pending PCV logging work and prior verification; settle release/deployment ownership.
- [x] Give each plugin a distinct deployment repository identity; prove catalogue selection and preserve existing-install migration.
- [x] Move PCV registration earlier, report scoped unmatched ACKs and verify acknowledgement-first behavior without weakening exact correlation.
- [x] Add reflection API compatibility version and make cross-plugin checks reproducible from repository source.
- [x] Document final-line/wire limits and repair temporary-fixture cleanup; verify any residue ownership before removal.
- [x] Review all code, pending PCV release content and focused red/green outputs; prepare PRE-ALPHA candidate metadata.
- [x] Commit explicit reviewed files, build/export immutable tags and compare packages; publish verified assets before update manifests.
- [x] Verify public repositories/releases/migration URLs, record evidence and remaining live-runtime limits.

Scope: User authorized fixes, commit, push and publication. Lead delegates product code to existing gpt-6-luna Max owners using Ponytail FULL. Installed CHIM, its catalogue, modlist and live database/providers remain inspection-only. No maintainer message or catalogue submission is authorized. CHIM-Plugins remains a public collection and migration entry point; independent deployment identities address the source-confirmed catalogue collision. Existing logging revision2 PCV work must pass scope/evidence review before incorporation; it is not silently discarded or bulk-staged.

Ownership: reflection_correlation — MP API compatibility/contained integration fixture and PCV log-fixture cleanup; observer_logger — PCV registration/ACK and consumer version handshake; bridge_contract — deployment/package review, later coordinated metadata; lead — task/evidence review, repository creation, explicit Git and publication orchestration.

Assumptions: New deployment repository names follow the existing project names. PRE-ALPHA and compatibility reference remain unchanged. Existing installed manifests need a migration release at their old package URLs before they can switch repositories. prepostrequest still follows the flush: moving registration alone is not proof of race closure. Fixtures must represent the tested, published PCV source rather than an external drifting folder.

### Deployment metadata and handoff — bridge_contract

- [x] Reconcile local refs and confirm published PCV 0.1.5 source/evidence; set next candidate to 0.1.6.
- [x] Update MP 0.1.14 and PCV 0.1.6 deployment manifests, catalogue metadata and focused version/identity tests.
- [x] Add release notes plus a concise deployment/package index and existing-install migration guide.
- [x] Run the focused metadata and package gates; review URLs, identity selection, file scope and limitations.

Review: manifests and source links identify the distinct deployment repositories; old-hub MP 0.1.14 migration and one-time PCV 0.1.6 replacement are documented without changing historical assets. The release notes link to tagged repository docs by absolute URLs because they will also serve as GitHub release bodies. Local Markdown link check passed; targeted manifest and PCV package tests passed. No catalogue submission, remote writes, or live-server actions were performed.

Final lead review: MP 0.1.14 and PCV 0.1.6 are committed, tagged and published as PRE-ALPHA in their public deployment repositories, with identical migration-hub assets. A standalone line-ending export mismatch was caught and corrected before any push. All canonical-tag packages, draft downloads and public downloads matched exact clean-source bytes and checksums; public main manifests were verified after publication. Earlier registration diagnoses but does not recover ACK-first misses. No installed CHIM/modlist, live provider/database or gameplay changes. Exact sources, commands, hashes and limits: release-v0.1.14-pcv-v0.1.6.md.

## Reflection reply fixes — 2026-10-02

- [x] Fix reflection listener collision and add conservative shared subject aliases.
- [x] Add an opt-in full-reply API with source revalidation and atomic all-line dedupe, preserving v1.
- [x] Verify diagnostic coverage and document the consumer contract.
- [x] Review each task and whole branch; record focused verification and remaining limits.

Scope: isolated fix/reflection-reply-v2 worktree. PCV and installed CHIM are read-only. No release/version/pin changes, installation, live database/provider calls, push or publication. Plan: reflection-reply-fixes-2026-10-02.md.

Task 3 review: The shared evaluator now creates the default RequestLog only when omitted, keeps caller-supplied observer/sink delivery, and preserves v1/v2 return statuses. Focused tests confirm one terminal summary, fixed severity, sink-failure containment, and v1 importer compatibility. The source docs describe the unreleased v2 contract and current PCV v1-only caller; no PCV or release files changed. Live provider, database, installed-order, grouping, and audio behavior remain unverified.

Final lead review: All three task gates and the combined source review are approved. The one report attribution error was corrected by its owner at ec3a9fe. Product and test bytes are unchanged since the final ordinary runtime check; the isolated branch is ready for integration, not certified for live deployment. Exact review and accepted evidence boundaries: reflection-reply-review-2026-10-02.md. Original dirty edits and protected PCV/installation/release paths remain preserved. No release pin advanced.

## Mind Poisoning 0.1.15 publication — 2026-10-02

- [x] Incorporate the reviewed fixes into work/mind-poisoning and preserve newer collection commits and unrelated dirty work.
- [x] Verify merged runtime/reply behavior and v1 observer compatibility with the updated PCV 0.1.8 snapshot.
- [x] Review 0.1.15 PRE-ALPHA metadata and exact caller documentation.
- [x] Build and compare clean-commit/tag assets; verify draft downloads, publish and advance main only after release availability.
- [x] Verify public refs/assets and save final publication evidence.

Authorization: the user requested merge as needed, commit, push and publish. Scope is MP only; installed CHIM, game/modlist and live database/providers remain untouched. Plan and boundaries: release-v0.1.15-plan.md.

Review: 0.1.15 is committed, immutably tagged and published PRE-ALPHA in the dedicated repository and migration hub. Clean-tag rebuilds, both draft downloads and both public downloads match exact bytes/checksums. Update branches advanced after publication and identify 0.1.15. Unrelated dirty work and historical assets remain preserved. Full evidence: release-v0.1.15.md. Companion v2 adoption and live runtime remain unverified; no installation occurred.

## MP 0.1.26 hub documentation — 2026-10-19

- [x] Verify MP's published tag and all public installer/checksum bytes before moving hub links.
- [x] Replace only the MP README summary/current-download references; point its guides to canonical MP source.
- [x] Verify PCV 0.1.21 BETA and S&S 0.1.5 sections remain exact; allow only README.md and this task record, then commit/push documentation.

Scope: documentation only. No runtime/plugin snapshots, metadata, assets, tests, tags or release files change. Preserve companion releases and concurrent edits. No live/server/game/WSL/provider access.

Review: both companion sections compare exactly; current MP links target public 0.1.26 with clean-tag and five-file public-download verification. Diff allowlist is README.md and tasks/todo.md only. Publishing the hub is a documentation update, not an install or maturity promotion.
