# Private Conversation logging bug run - lead review

## Judgment

Three reproduced defects are fixed in development source: browser draft loss on malformed refresh, request blocking on a pre-existing FIFO log lock, and misattributed optional observer records. The observer fix includes the missing fresh-ACK binding exposed by lead review. All fixes are accepted against their isolated regression evidence. This is not an installed CHIM or gameplay certification, and no release pin advances.

Two original gpt-6-luna Max owners used Ponytail FULL with exclusive ownership. The harness refused resuming a third original owner because its thread limit was reached; the lead performed the remaining read-only call-path review. The lead wrote task/evidence documents only. Product edits stayed with the original UI and logger owners. No dependency or new logging subsystem was added.

## Defects and evidence reviewed

| Defect | Root cause and accepted fix | Focused evidence |
| --- | --- | --- |
| Malformed refresh loses actor draft | applySnapshot mutated identity/actor A before cloning B. Validate all actor controls and prepare both option sets before mutation. ARM retains its fail-closed behavior. | New Node regression RED: 8 passed, 1 failed, actor A empty instead of 101. GREEN: 9 passed, 0 failed. Both drafts and old playthrough reference survive the malformed response; fixed invalid_response telemetry remains. |
| FIFO lock blocks request | fopen on a named pipe blocked before regular-file validation/nonblocking flock. Reuse private-file validation before opening an existing lock path. | Isolated timed child RED: request did not complete. GREEN: child exits normally, degraded lock_unavailable health, no JSONL segment. Focused PHP logger check passes. |
| Optional observer relabels trusted tuple | Config-only matching allowed valid different event/utterance IDs to overwrite trusted correlation; successful ACK handling also omitted seeding in its new request. Reject established-ID mismatches and bind validated registry config/event/utterance before attaching the observer. | Importer RED: established ACK tuple changed or gained a false row. GREEN: independent mismatches preserve tuple/row count, matching/first-row imports still work. Fresh-context registry RED: expected config UUID, actual NULL before first observer row; GREEN: PCV reflection registry checks passed. |

Exact commands, exits and relevant output are in logger-review.md and ui-review.md. PHP syntax checks passed but establish syntax only. The Node DOM/parser fixture is not a rendered-browser test. The ACK check uses MemoryStoreDb, a deterministic model fixture and the current observer-capable MP checkout. No-observer API compatibility is represented by a stub; no fresh full published-MP integration claim is made. Optional testing stopped once each concrete risk was covered; broad old suites were not rerun.

## Call-path and failure-mode review

The lead examined every current-run product/test diff and affected callers: refresh to applySnapshot to failure/report handling; pcv_log_event to the shared writer and fallback health; reflection registration/ACK logging to the optional observer and importer. Private-file helper callers in reader/rotation/writer were inspected. The fresh ACK path was reviewed separately from registration, exposing the missing seeding that helper-only tests missed. The five-line fix binds identity after successful exact validation and claim, before observer setup can emit anything. The MP sink executes before its observer, so the regression captures context at the required boundary. Public contracts and scene/opinion persistence semantics remain unchanged.

Presence observation/public-result stripping, routing start/block/terminal handling, prerouting mode guards, solo registration/ACK/claim/revalidation/cleanup and observer-unavailable handling were reviewed read-only. Request initialization is idempotent, disproving a suspected context-reset problem. An isolated external-child replacement of a cached regular file with a FIFO was rejected by the helper, disproving the stale-cache counterexample. No speculative fix was added.

Source review identified a narrow remaining observability gap: if the state directory disappears after an ACK was validated but before the second claim lock, a null lock returns registration_missing without an ACK breadcrumb. This is an externally removed-state race, not a reproduced gameplay failure; it still prevents evaluation. No reflection semantics change is warranted by this logging-only bug run. Initial unrelated/missing ACKs intentionally stay quiet.

Published MP 0.1.12 has no observer, so the tuple defect is dormant there. Separate dirty MP working-tree work now contains an observer; it was preserved, not edited or certified here. No assertion of current cross-project integration or deployed adapter behavior is made.

## Scope and limits

The before/after authoring hash inventory identifies only six changed product/test files: server/log.php, server/reflection.php, server/assets/ui-refresh.js, tests/log_check.php, tests/reflection_registry_check.php and tests/ui_refresh_check.mjs. The owners copy only these accepted files into the nested PCV repository mirror. Task documents accompany them. No installed CHIM, provider, live database, game mod, version, release artifact, catalog, commit or push is changed by this task.

The FIFO check covers a pre-existing corrupted lock, not an active same-UID filesystem race between validation and opening. Diagnostics remain bounded and best effort; they are not a complete or tamper-proof audit trail. No production proxy/authentication, Skyrim speech/playback or live model call was tested.

## Final handoff gate

- Independent lead before/after inventory and SHA256 gate: exit 0, exactly six current-run product/test edits; all six accepted source/mirror files match.
- Source and mirror manifest SHA256 remain 07FA32314A70556ED5AA4F053D97CACEEFC7090B9FF22D8AAFD5962023DEFDB5; HEAD remains f0784bcb1462d2c8d9ad06614c52a999c3731c37.
- Seven exact task/evidence documents passed source/mirror SHA256 comparison (exit 0). Final scoped PCV git diff --check after documentation copying passed (exit 0). New plan/review trailing-whitespace search found no matches (rg exit 1, its expected no-match status).
- Four captured external MP source/test hashes remain equal after the mirror work. Its task documentation changed independently and was not overwritten; no broad clean-tree or cross-project completion claim is made.
- Project tasks/todo.md and task plan are complete. The lead reviewed six product/test diffs, caller/failure paths and focused verification outputs. No product code was written by the lead.
