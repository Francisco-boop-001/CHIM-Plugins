# PCV bounded SQL reads implementation plan

> For agentic workers: use superpowers:subagent-driven-development, user-selected GPT-6-LUNA Max/Ponytail FULL. Lead reviews and publishes; lead writes no product code.

**Goal:** Bound native PCV identity/catalog SQL waits without changing scene behavior or other plugins' database state.
**Architecture:** One owned bounded reader in state.php, consumed by identity and both native catalog callers. Department separately rebases its existing companion hooks and migrates only known23 backups.
**Tech stack:** Existing PHP/PostgreSQL, Python/Node package/test tools; no dependencies.
**Spec:** spec.md.

## Global constraints and rulings

- Reuse the dedicated PCV development checkout/feature branch; tracked baseline clean. Preserve all unrelated untracked evidence and user edits. No new worktree or setup/dependency churn is needed.
- Existing user instructions and verified explicit approval of these recommended fixes select scope/execution/publication; no redundant design/permission menu. Reuse the responsible agents for corrections. Their model/effort remains Luna Max.
- User task/ledger convention is tasks/. Keep durable task evidence here and larger runtime scratch under artifacts/pcv-read-timeouts-20261026; no extra SDD scaffold or deletion of evidence.
- Product changes only state.php, index.php, scope.php, manifest.json; targeted fixtures and useful docs separately owned. No companion hooks adopted by owner.
- Root alone coordinates TEST2 runtime and Git/publication. Workers may prepare tests/code but not enter WSL until explicit root lease grant. Preserve original RED and intermediate failed outputs.
- No independent/dependent edits in parallel: task2 waits for task1 interface/code review. Harness preparation and documentation are independent. No worker commits.

## Task1 — bounded reader and identity (autonomy_bgl_boundaries)

Own server/state.php, tests/identity_source_check.php, tests/database_read_bounds_check.php.
Interfaces: pcv_with_read_connection(callable $read): mixed; pcv_apply_read_bounds($connection): void; pcv_read_npc_catalog(): array. Existing pcv_current_identity(bool $refresh=false): array stays public.
- [x] Write assertions for bounds, shorter configured settings, cleanup/error paths, identity caching/fallback; root recorded both baseline REDs in unit-red-corrected.txt before product GO.
- [x] Implement minimum owned-reader path and catalog read; no false empty-catalog on failed query.
- [x] Root reviewed Task1 diff/failure modes, returned one fix round, and captured targeted GREEN. Native caller review follows Task2.

## Task2 — native callers (certify_routing, sequential after task1)

Own server/index.php, server/scope.php and directly affected catalog/scope fixtures. Consume pcv_read_npc_catalog(): array without changing eligibility or row ordering.
- [x] Prepare real native-caller lock regressions before wiring.
- [x] Route pcv_npc_rows() and pcvScopeLoadNpcCatalog() through shared reader, removing obsolete shared DB catalog setup only when no other caller needs it.
- [x] Review and run directly affected fixtures; preserve error-vs-empty and hook/UI fail-closed behavior.

## Task3 — independent proof preparation and coordinated execution (certify_routing)

Own artifacts/pcv-read-timeouts-20261026. Reuse actual retained official core helpers and prior private-network PG pattern; source-root argument supports later exact Department-overlaid bytes.
- [x] Prepare/examine driver and baseline source export; no WSL before root host/lease guard.
- [x] Record baseline held-lock failure through identity and both native catalog callers, plus positive populated-row semantics.
- [x] Verify final candidate: default and tighter lock/statement bounds, cache/fallback, real caller catalog contents, caller transaction/temp work/settings preserved on success/cancellation/exception, legitimate empty distinct from query error.
- [x] Tiny paired positive/lock check on Department-rebased exact bytes once supplied; no broad reruns or idle daemon. Stop own cluster, remove owned scratch only after evidence saved, restore initial stopped TEST2 state and release window.

## Task4 — docs/metadata (docs_publication_verify, independent)

Own server/manifest.json version0.1.24, README.md, docs/player-guide.md, docs/logging-revision-2.md, distribution/private_conversation-v0.1.24.md.
- [ ] Useful player guidance in established witty/self-deprecating tone; clarify failed reads, per-statement limits and paired version requirements. No invented gameplay proof.
- [ ] Finalize exact paired links only after candidate/evidence supplied; validate links/metadata/LF.

## Task5 — lead acceptance and paired release

- [ ] Review every diff and real call path, inspect original runtime outputs, independent cross-review; required fixes returned to owner.
- [ ] Send exact settled source bytes/hashes early to Department; obtain overlay/backup migration success, failed-upgrade rollback, repeat and drift evidence. Keep other plugin mappings preserved.
- [ ] Commit verified source; clean committed-source build, package inventory and authenticated draft byte checks; preserve prior releases.
- [ ] Joint final publication gate with Department before either unmatched PCV release goes public. Verify anonymous assets, canonical and PCV-only hub scope, final documentation/evidence commit.

## Conflict scan / review focus

| Tasks | Shared interface or risk | Ruling |
|---|---|---|
|1/2|state helper consumed by catalog callers|2 waits for1 review; exact signature above, no simultaneous dependent product work.|
|1/3|new reader vs external probes|3 prepares existing public-call baseline independently; product waits for RED, final proof uses exact hashed candidate.|
|2/3|same worker real caller proof|Sequential wire/review then execute; no conflicting ownership.|
|1/4|state vs manifest/docs|Separate files; docs make no success claim before evidence.|
|PCV/Department|postimages and existing23 backup|Separate repo owners; freeze bytes, rebase hooks, validate/migrate only known backups; no blind repin.|
|1|failure/commit/rollback and shorter caps|Targeted unit and actual PG assertions; cache failure is retriable only on explicit refresh.|
|2|empty vs failed catalog, full row parity|Positive populated/empty plus actual held-lock callers; no NPC count limit.|
|3|caller-owned work/settings and cleanup|Own FORCE_NEW connection; prove unaffected sentinel connection on success/cancel/exception; exact cluster identity cleanup.|
|5|compatibility with other plugins|No shared DB/SET/core changes; preserve public function contracts, schema, eligibility and protected source/hub mappings.|

## Review

Pending. Static checks will not be presented as runtime or gameplay proof.
