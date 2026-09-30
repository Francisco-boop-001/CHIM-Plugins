# MindPoisoning reflection foundation handoff

## Result

MindPoisoning now exposes a side-effect-free solo-reflection API for PCV to call from its existing `prerequest.php` ACK hook. The pair ACK route remains on its existing path. No PCV product files, package version, install, database, or provider were changed or used.

## PCV caller contract

```php
mindPoisoningEvaluateReflection(
    array $registration,
    array $gameRequest,
    StoreDb $store,
    callable $revalidate,
    ?callable $requestModel = null,
    ?RequestLog $requestLog = null
): string
```

Require `server/reflection.php` directly; it does not load or execute MindPoisoning's `prerequest.php`. Registration must be obtained from PCV's private server-side registry, never browser input, and contain exactly:

```php
[
    'event_id' => int,
    'utterance_id' => string, // utt_[A-Za-z0-9_-]{8,128}
    'actor_id' => int,
    'actor_name' => string,
    'playthrough_id' => string,
    'config_id' => string, // UUID
    'rechat_target_hint' => 'explicit_disable_rechat',
    'speech_hash' => string, // lowercase SHA-256 of the exact emitted subtitle
]
```

The API independently verifies the exact ACK utterance ID, registered actor as speaker, the ACK speech digest, the unique native event ID, native source speaker, and explicit `explicit_disable_rechat` target. The source event's subtitle may differ from `source_data` (for example, formatted narration), so the digest binds the ACK to PCV's registered exact subtitle while the event ID/speaker/target bind it to the native source. The current event can be `emitted` or `spoken`: CHIM calls extension prerequest before `_speech` sets its final state. `aborted` is rejected. This only establishes a server-side line attempt, not successful playback.

The callback is called as `$revalidate($registration, $phase)` at `pre_model` and `transaction` phases. PCV must re-read its private registry and verify the same event/utterance/actor/config/playthrough and active scope at each phase. False or an exception fails closed. The `StoreDb` must be the existing CHIM-backed MindPoisoning adapter on the captured connection. The API does not create a second adapter, open a provider under a DB lock, fabricate an ACK, or make the actor its own listener.

## Persistence and evidence

Reflection writes the actor's own relationship opinion (`opinion_owner_id = actor_id`, `speaker_id = actor_id`, `listener_id = null`) and tags the ledger event and diagnostics with `source_kind = reflection`. Player identity is used only to validate the native ACK transport. Model output remains limited to the actor's opinion of verified subjects; prior reflection output is excluded from evidence and from reflection basis construction.

Evidence uses the actor profile plus up to eight bounded, earlier `spoken` utterances that include the actor in the native `people` membership. The SQL filters membership and explicit rechat-target rows before its bounded window. The evidence basis is retained separately from the 128-entry rolling ledger with one current basis and up to 32 processed subject tokens; this prevents unchanged evidence from becoming eligible again after ledger eviction. A zero-change result still stores the basis. A new playthrough resets the subject-token set. Affinity and reflection text do not enter the basis.

The broad source-data exclusion for `explicit_disable_rechat` is intentionally conservative and can omit otherwise legitimate history that mentions that marker. History is bounded and event-time anchored before the registered source event; utterances arriving during a model request do not alter that event's evaluation basis.

## Changed MindPoisoning files

- `server/reflection.php`: public callable, exact registration/ACK/source checks, scope and state gates.
- `server/controls.php`, `server/prerequest.php`: extracted shared pure interaction/pause gates; pair ACK flow retains those same gates.
- `server/influence.php`: actor-only reflection prompt and bounded evidence basis; excludes reflection output from pair evidence.
- `server/store.php`: bounded history query, validated epoch metadata, owner-only locked persistence and in-transaction event/scope/evidence rechecks.
- `server/logging.php`: permits sanitized reflection provenance and opinion owner fields without logging dialogue or its digest.
- `tests/reflection_test.php`, `tests/runtime_test.php`, `tests/store_logging_test.php`: fake-store fixtures and focused behavior coverage. `runtime_test.php` has a fixture-only guard so reflection tests do not rerun the ordinary suite.

## Verification

Run from the workspace PowerShell shell:

```powershell
wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-MindPoisoning/tests/reflection_test.php
wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-MindPoisoning/tests/runtime_test.php
wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-MindPoisoning/tests/store_logging_test.php
```

Observed results: `reflection checks passed`; `runtime store checks passed` (with the two expected negative-fixture log lines for snapshot verification and ambiguous Player alias); `store logging checks passed`. PHP lint passed for all nine changed PHP source/test files. `git diff --check` was clean.

Fixtures cover owner/provenance, exact replay and unchanged-basis dedupe, rolling-ledger eviction, new-scope reset, emitted-vs-aborted source status, registered subtitle digest vs unrelated ACK text, callback stale checks before model and during persistence, actor lock, provider exception, zero-change durable dedupe, and redaction. These are fake-store checks only: no installed database, provider, package, or playback behavior was exercised.

## Dashboard and package source follow-up

The dashboard log sanitizer retains `source_kind=reflection` for early preflight records even when actor identity is not yet available, removes any reflection `listener_id`, rejects mismatched nonempty speaker/owner IDs, and publishes `opinion_owner_id` only when corroborated by the NPC speaker ID. It groups reflection ledger/log persistence by opinion owner, queries current affinity from that owner's row, and renders `Actor · Solo reflection` (or `Unknown NPC · Solo reflection`) with no listener field. Pair records continue to group by listener and render the existing speaker-to-listener presentation. Diagnostics show the safe owner/provenance fields and continue to omit `speech_hash` and `reflection_basis`.

`tests/dashboard_data_test.php` passed with exit code 0. It covers reflection-ledger to persistence-log owner joins, log-only unverified attribution, owner/provenance sanitizer behavior, private hash suppression, ownerless preflight records, rejection of mismatched actor IDs, a null reflection listener, solo-reflection rendering, and the unchanged pair rendering. PHP lint passed with exit code 0 for `server/dashboard_data.php`, `server/dashboard_view.php`, and `tests/dashboard_data_test.php`; `git diff --check` was clean.

`scripts/package.py` now explicitly allowlists only the two new runtime files, `controls.php` and `reflection.php`. The source-membership check passed with exit code 0. No archive/package was built, so this is not package verification.

The source-checkout `README.md` and `server/README.md` describe the opt-in API, owner semantics, bounded dedupe basis, dependency on a private caller registration, and the line-attempt/runtime evidence limits; they identify the addition as absent from published v0.1.11 assets.
