# Conservative relationship-type changes implementation plan — 2026-10-15

> For agentic workers: use subagent-driven-development with exclusive file ownership, Ponytail FULL and isolated verification. Preserve other contributors' edits.

## Approved goal and bounds

Extend existing MP evaluation with an optional conservative native CHIM relationship-type judgment. User approved the preceding in-chat design: keep current type by default; require meaningful supported change; protect enduring relationships from transient emotional replacement; allow type-only change at zero affinity delta; use existing locks, atomic persistence, replay and diagnostics. Simultaneous labels are explicitly deferred in K:/ActorwrightExchange/projects/CHIM-Social-Drama-Ideas.md.

No live HTTP, WSL/services, provider/database calls, game/modlist/installed-file changes, commits, pushes, publication or version/pin advancement. Use K:/ActorwrightExchange/artifacts/php-8.3.35-windows-cli/runtime/php.exe -n. Existing standalone dashboard fix and unrelated dirty work stay preserved. Lead writes plans/evidence only.

## Frozen contract and rulings

- Existing exact affinity-only model rows remain accepted. One optional extra key: type_change:null|{from,to}; closed bounded values. Omitted/null means keep and preserves the old parsed shape. Batch rows retain exact listener/subject correlation. New nonnull proposal requires genuine subject mention and current-utterance evidence.
- Canonicalize through CHIM's helper. New targets are built-in CHIM types; preserve current custom types. No invented types or duplicate platform enum. Explicit current type is captured before the model; a mismatched model from is invalid. A different current type under the existing save lock makes the whole decision stale before writes.
- Current romantic/familial/professional types cannot be replaced by jealous, protective, suspicious, fearful, obsessed, awed, contempt, pitying, grateful, curious, dismissive or admirer. This deterministic guard supplements the model's lasting-change instruction. Valid protected proposals keep type while allowing an otherwise valid affinity change; malformed or unknown proposals invalidate the response.
- Same provider call assesses affinity and type. No source eligibility expansion or reflection receipt/API version change. Reuse all existing subject, scope, pause, interaction, lock and replay gates.
- Keep affinity changes array exactly {subject,delta,before,after}. Add type_changes:[{subject,before,after}] for confirmed applied transitions only, and type_decisions:[{subject,from,to,reason}] for proposals/guard decisions. Closed decision reasons: change_eligible,same_type,protected_type_preserved,custom_type_preserved. No speech, evidence or prose in these fields. Bounds:8 rows; canonical ASCII type tokens up to50 characters.
- Ruling: changed_count counts distinct relationships changed in affinity or type; add affinity_changed_count and type_changed_count for each dimension. Old affinity-only counts stay identical. This keeps the unchanged PCV importer truthful for a type-only commit (it uses changed_count>0 and ignores new optional details). No PCV product edits. Cost: consumers must not interpret total changed_count as an affinity-only count; document the separate field and prove importer compatibility.
- Zero-change means both dimensions unchanged. Type-only delta0 still writes edge+dedupe ledger+history in one transaction. Unknown commit must not publish actual applied changes.
- Ledger version remains1 with backward-compatible optional judgment type_change and optional entry type_decisions/type_changes. Historical optional types are bounded tokens; old entries stay readable. Do not rewrite existing stored data.

## Task 1 — Evaluation contract (type_evaluation)

Ownership: server/influence.php, server/prerequest.php, server/reflection.php; tests/influence_test.php and tests/relationship_type_judgments_test.php.

- [x] Trace builders/parsers/callers and reusable core helper loading.
- [x] Add failing checks for strict legacy/new rows and snapshot/canonical/evidence behavior.
- [x] Implement optional judgments in all three builders and parser routes, preserving one model call and recipient isolation.
- [x] Publish the exact reusable type helper signatures for persistence; review focused output/diff before task3 starts.

## Task 2 — Diagnostics and dashboard consumer (type_diagnostics_ui)

Ownership: server/logging.php, server/dashboard_data.php, server/dashboard_view.php; tests/logging_test.php, tests/dashboard_data_test.php, tests/dashboard_type_changes_test.php. Review expanded ownership to tests/dashboard_standalone_relationship_bootstrap_test.php and tests/dashboard_current_bounds_test.php solely to preserve strict assertions after the return shape adds type fields. Preserve the pending standalone bootstrap fix.

- [x] Add failing bounded-sanitizer/legacy/new/proposed/unconfirmed fixtures independently of new persistence helpers.
- [x] Project new safe fields without changing affinity rows or leaking model data.
- [x] Show proposed/applied/current types separately; protection reason and unknown commit must remain truthful.
- [x] Verify legacy data, type-only outcomes, helper-bootstrap preservation and scoped PHP syntax.

## Task 3 — Atomic type persistence (type_persistence; dispatched after task1 gate)

Ownership: server/store.php; tests/runtime_test.php, tests/store_logging_test.php and relevant reflection/overhearing regression files. Depends on task1's completed parser/helper contract; do not dispatch before that gate.

- [x] Reproduce type-only row being skipped; cover legacy/new ledger validation.
- [x] Under row lock recheck from/allowed types, apply conservative guard and persist both dimensions with ledger/history.
- [x] Keep locks, duplicate/basis/stale checks and uncertain commit/rollback behavior; no new DB interface or tables.
- [x] Prove type-only, combined, protected, stale, locked/replayed, rollback/unconfirmed, Player aliases and each existing source route using synthetic StoreDb/model seams.
- [x] Prove unchanged PCV importer reports a type-only confirmed result as committed; retain normal sink/privacy/observer-failure behavior.

## Task 4 — Documentation (type_docs_maturity)

Ownership: README.md and docs/mind-poisoning.md, docs/dashboard.md, docs/integration-api.md, docs/logging.md if present.

- [x] Describe local/unreleased semantics and native single-type limits using the frozen contract; retain published PRE-ALPHA metadata.
- [x] Correct adjacent stale dashboard-candidate/NPC-origin evidence statements narrowly.
- [x] Document new count semantics and old consumer detail limitations; check local links and do not claim live type validation.

## Final review and verification

- [x] Review each owner diff/call paths and actual RED/GREEN output; return defects to original owner.
- [x] Run the smallest integrated PHP checks after all shared code stops changing, plus changed-file syntax checks.
- [x] Independent read-only final branch review checks conservative policy, stale/type-only atomicity, PCV compatibility and old ledger/readout safety.
- [x] Record exact source hashes/commands and remaining limits, update checklist and notebook implementation status. Published pin stays0.1.19; no deployment or maturity label change.

Maturity judgment: early alpha describes the already live-tested affinity core; this new type capability remains gameplay-unverified until a later authorized acceptance session.

## Review and verification

Implemented locally by exclusive gpt-6-luna Max owners; lead wrote no product code. Lead reviewed all source/test/doc changes and returned defects to their owners. Independent review covered the completed evaluator/readout and storage. Player combined/type-only writes, stale state, JSONB key order, uncertain readout, same-type retention and no-log proposal classification received concrete corrections. Independently optional historical decisions remain readable; application or zero change is not inferred from an unresolved proposal.

The lead independently executed parser/influence plus missing/throwing/malformed-helper checks; logging/dashboard plus fresh bootstrap/bounds checks; and the integrated runtime/store logging/reflection/full-reply/overhearing/PCV callback checks. All returned exit code 0. After the final reader-only correction, targeted reader tests and syntax checks passed; hashes confirm the other integrated-tested modules stayed unchanged. Twenty changed/source regression PHP files passed syntax checks. Manifest and protected PCV source hashes remain identical. Exact commands/results/hashes: ../../artifacts/conservative-types-20261015/ (lead-*.json and owner subdirectories); review dispositions: lead-review-notes.md there.

Limits: synthetic model/store fixtures are not live PostgreSQL durability/concurrency, provider-quality, installed-service or Skyrim proof. Windows symlink checks and PCV's physical POSIX JSONL sink are explicit skips; the optional Player-store PostgreSQL segment was not run. The full unchanged PCV callback's in-memory exact correlation and actual-record outcome classification did pass. No HTTP/WSL/server access, live data changes, installation, commit/push/publication, metadata/pin advance or artwork/CSS change occurred. Existing unrelated work remains preserved. Published 0.1.19 stays PRE-ALPHA; simultaneous labels remain deferred.
