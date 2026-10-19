# Preserve-on-reload compatibility

## Plan and ownership

- [x] Re-pin MP `fe7b76550f82fcf9b357fa7723aecc484e9726dd` and CHIM 3.5.1 `57f292415b008cfaf1a4531f0654d2563362ce70`; verify all 29 retained core source hashes and reuse the completed restore assessment.
- [x] Record the lifecycle contract and challenge restore, scope, identity and concurrency counterexamples before product edits.
- [x] Delegate storage/module/package ownership, callers/context/view/docs ownership, and independent focused test ownership to gpt-6-luna agents at Max using Ponytail FULL. The lead reviews product code and runs release operations; the lead writes no product code.
- [x] Establish failing ACK/Player/reflection/overhearing fixtures before replacing the old refusal. Verify the changed routes and failure handling with deterministic substitutes.
- [x] Complete the reviewed disposable PostgreSQL restore/interleaving/recovery proof.
- [ ] Review final changes and verification, then complete the separately gated commit/publication authorized by the user.

Others share the checkout. Preserve unrelated dirty plans, lessons, companion-plugin content, critique and untracked files. Native fixture ownership was explicitly handed from the Windows test owner to the storage owner; only the lead may execute the reviewed native launcher.

## Implemented contract

With `NEVER_CLEAR_RELATIONSHIP_DATA=false`, use the existing timeline/receipt path without the new bounds query or MAIN lock. When true, every registered output source must be newer than the latest actual `eventlog` INIT row. Capture that boundary before evaluation and check it again under an independently acquired CHIM MAIN lock before the owner transaction. Hold MAIN through transaction cleanup; never hold it across the model call.

Existing bounded exact-event receipts and their eviction floor handle retries within that interval. The INIT cutoff expires earlier sources, including pending ones; it does not manufacture historical receipts. New utterance IDs repeating the same story remain new evidence. Reflection keeps its existing basis guard as well as checking every registered reply output source.

Current relationship values, types and notes remain relationship state. Earlier MP judgment entries are excluded from prior-judgment prompt context after INIT; ordinary restored core history keeps its timeline semantics. A nonempty owner namespace from another scope is baselined to the current event-row maximum, snapshotted atomically, and its triggering event skipped without changing opinions. Matching-scope receipts remain intact. Actor identity, active scope, manual/profile locks and source correlation still require their existing checks.

Changing the policy requires draining requests and restarting the web request pool. This does not promise safe hot reclassification. Missing authoritative INIT/capabilities, malformed state, an INIT fast-command override, changed scope/boundary or unavailable serialization retain specific refusals. Support is bounded to the inspected single-host/shared-IPC deployment and coherent database/profile restoration; partial manual database edits and late client intent inserted as a new row are not distinguished from new evidence.

## Evidence and review

Detailed artifacts are under `K:/ActorwrightExchange/artifacts/mp-restore-policy-implementation-20261018/`. `plan.md`, `contract-proposal.md` and `review.md` separate source facts, decisions and limits.

Windows fixture command uses standalone `artifacts/php-8.3.35-windows-cli/runtime/php.exe -n`, explicit mbstring/pgsql modules and task-owned temporary paths. `tests/restore_policy_test.php` executes the existing runtime baseline and the new deterministic route/fault cases. Saved RED cases return the old `restore-policy` refusal; GREEN cases reach the expected commit/skip/refusal. `tests/dashboard_restore_policy_test.php` checks both dashboard tabs, raw records, escaping and input immutability. Windows denied symlink creation; those inherited runtime checks are explicitly skipped, not passed.

The initial native substrate passed the exact pinned `main.php`/`processor/comm.php` INIT path with real NpcMaster and MAIN, synthetic PostgreSQL, inert unrelated services and no network. That alone is not compatibility proof. The added true-policy/native adapter/interleaving/recovery checks remain a separate gate below.

Lead review returned defects for partial transaction setup cleanup, malformed-state classification, confirmed commit followed by cleanup failure, native PostgreSQL string IDs and per-recipient baseline attribution. Owners corrected these and reran the relevant focused checks. Readability review moved restore orchestration into one allowlisted `restore_policy.php` module, keeping the adapter in `store.php` and prompt cutoff in `influence.php`.

## Native gate — 2026-10-19

Command: `& 'K:/ActorwrightExchange/artifacts/mp-restore-policy-implementation-20261018/proof/driver-native.ps1'`. Exit **0**. The reviewed opt-in runner enters only `DwemerAI4Skyrim3-test`, uses private network/IPC namespaces, PHP 8.2.29 with `-n`, and a fresh socket-only PostgreSQL 15.14 cluster. The exact pinned MAIN/INIT/restore path runs with synthetic data and explicitly inert unrelated services. `sql::$link` is a test shim to that private native connection; no installed CHIM configuration or database is loaded.

Results in `proof/native-compatibility-pass.txt`:

- Actual INIT 3004 retained affinity 13 while restoring the earlier owner ledger/history; retained source 3000 was fenced, and at/after-load source/speech rows were pruned by the actual handler.
- Post-INIT event 3005 committed through production PostgresStoreDb/persistJudgments; its exact retry was duplicate without another snapshot.
- While actual INIT waited on production MAIN, an ordered method-level `beginForListener → writeNpc → backupAndVerify → commit` saved event 3006. INIT 3008 resumed after release and retained affinity 15 and the receipt. This proves that deliberate ordered sequence, not a complete concurrent ACK/client request.
- An intentional history-insert error rolled back a scope baseline without changing relationships or the imported namespace. A successful retry wrote the explicit baseline/floor 3009 with a matching history snapshot.
- `pg_dump`/`pg_restore` to a second empty owned database reproduced the owner, ledger/floor, history, INIT and relevant source/trace projection. The synthetic dump SHA-256 was `032d3c03868b1d81c0af9e6a538fe852f1d9498bb45cc2e280c75066024bd49b`.

The cluster and verified owned scratch root were removed; the owned test distro was stopped. `proof/post-native-compatibility.json` records unchanged gaming VHD length/timestamp and both gaming/test distros stopped. No gaming distro was entered.

The final Windows fake-store checks also cover persisted-but-unconfirmed baseline retry, all v2 output-source fencing, per-recipient overhearing commits/retries, zero/type-only receipts, scope initialization, missing/unsafe INIT, policy transitions, changed boundary during model evaluation and transaction cleanup failure. Deterministic model substitutes use no provider.

## Release gate

Native implementation acceptance passed. Exact staging, final clean-source checks, clean-tag artifact comparison and public release verification are the separately gated publication work authorized by the user. Do not present those as complete until the final receipt exists.

## Limits

Synthetic source/store/model substitutions are not provider behavior, installed client playback, gameplay or full crash/durability proof. No provider credentials/quota, real player database, installed mods or gaming distro are used. The live server and game data remain untouched. Maturity stays BETA CANDIDATE; a standard GitHub release, if accepted, serves CHIM discovery only.
