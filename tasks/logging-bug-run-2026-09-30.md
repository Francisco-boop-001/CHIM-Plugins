# Logging integration bug run - 2026-09-30

## Scope and success criteria

Inspect the current Private Conversation development source, concentrating on new diagnostics and affected scene/presence/reflection call paths. Preserve existing dirty work and Mind Poisoning ownership. The lead reviews and writes evidence only; product fixes return to original gpt-6-luna Max owners using Ponytail FULL.

Success means any confirmed defect has a concrete reproducer, a minimal reviewed root-cause fix and a focused green check. Findings without runtime evidence remain source findings. No installed CHIM, provider, live database, release/version/tag/catalog, commit or publication changes are authorized by this bug run.

## Checklist

- [x] Read relevant skills, previous review and project lessons; capture current dirty baseline.
- [x] Independent read-only investigation: logger/shared reader; UI/browser diagnostics; presence/routing/reflection instrumentation. Owners report triggers, callers and counterarguments before any product edits.
- [x] Lead review findings and authorize only confirmed, exclusively owned fixes; sequence dependencies rather than parallel editing shared contracts.
- [x] Examine focused reproduction/verification output and every new diff. Stop optional testing after risks are covered.
- [x] Mirror only accepted current-run changes, verify scope/bytes and complete the lead report and project todo.

## Ownership

- Logger owner: server/log.php, log_reader.php, diagnostics.php and their existing focused checks. No UI/state/reflection edits.
- UI owner: server/index.php, assets/ui-refresh.js, assets/style.css and existing UI/HTTP/refresh checks. No shared logger edits.
- Instrumentation owner: server/state.php, scope.php, reflection.php, preprocessing.php, postrequest.php and existing presence/registry/postrequest checks. Other hooks may be read; coordinate any required ownership addition with lead.
- Lead: task plan/todo/report and cross-boundary call-path review. No product code.

The first phase is investigation only. A passed old check is prior evidence, not fresh proof for a new claim. Tests must answer remaining concrete questions; do not rerun broad suites, add speculative features or collect private prompts/dialogue.

## Findings and fix gates

1. Confirmed browser draft loss: applySnapshot validates selector presence, then mutates actor A before cloning actor B. A malformed same-origin 200 response with the expected B ID but wrong control shape throws after A's selection has been cleared. ARM fails closed; server state is unchanged. Original UI owner is authorized to prove red/green and validate/prepare the snapshot before mutation.
2. Confirmed writer hang: a pre-existing FIFO at the private events.lock path passes the symlink-only guard. fopen in create mode blocks before private regular-file validation or the nonblocking flock. The bounded child reproduction did not finish. Original logger owner is authorized to prove red/green and reuse the reader's pre-open private-file validation pattern. Same-user active filesystem races remain outside this bounded corruption check.
3. Confirmed optional importer tuple mismatch: a valid row with a different event or utterance ID in the same configuration could replace the already trusted tuple and emit a misleading record. The original logger owner added the minimal guard before context mutation. Independent ID mismatches fail the original code and pass the fix; matching rows and the first valid row without established correlation remain accepted. Published MP 0.1.12 lacks the observer; separate current MP working-tree changes do contain it and remain outside this task.
4. Confirmed integration gap returned and resolved by the same logger owner: registration and ACK handling are separate requests. The supported-observer ACK path lacked trusted correlation before attachment. The fresh-context regression failed with expected UUID versus NULL; the five-line binding of validated registry config/event/utterance before attachment passes the same check. Exclusive ownership extended to server/reflection.php and tests/reflection_registry_check.php; no other agent edited these files. The fixture uses current observer-capable MP source with an in-memory store/model, not installed CHIM. No-observer capability remains covered with a stub and conditional legacy expectations.

The harness refused resuming the third original instrumentation owner because its thread limit was reached. Two original Luna Max owners investigate and fix their exclusive domains; the lead independently inspects presence/routing/reflection call paths without writing product code. Remaining instrumentation hypotheses are reviewed separately; no dependency is bypassed to inflate agent count.
