# Operational Logging Contract

**Status:** Pin 1 logger/state, Pin 2 hook/UI/CLI, and Pin 3 package/source review are lead approved at source and isolated-fixture/package level. The logging update is a development candidate, not a runtime-certified deployment. Artifact: `dist/private_conversation-0.1.1.dwpkg`, 3,584,087 bytes, SHA-256 `afa1811daf5b027f75b94f16808e8af1bc7d1b013a7fc58a0b2e5b7330f2b704`. The historical `0.1.0` package review remains recorded separately in the project ledger. The logger uses a private per-install directory under `sys_get_temp_dir()` outside the public webroot, scoped to the effective server user and separated by a hash of the canonical extension path. A trusted server environment variable `PCV_LOG_DIR` may override the default for an admin-provisioned durable directory only with an absolute path that passes the same safety checks. No path is accepted from HTTP input. If the chosen directory cannot be proven safe, the logger falls back to PHP's configured `error_log` and drops the JSONL event. Temporary-directory cleanup can limit retention; CLI diagnostics must run as the same effective user as the web server to read the default location.

The artifact and pin history above describe earlier candidates. The `0.1.4` PRE-ALPHA release packages the current background presence, pair/solo routing and reflection diagnostics. Those additions were reviewed with separate source and isolated-fixture evidence; the historical `0.1.1`/`0.1.2` results do not establish current gameplay or deployed behavior. Current distribution details are in the [README](../README.md).

## Purpose and limits

The extension records small operational diagnostics for its web and ordinary-request lifecycle. Logging must not change scope transitions, hook outcomes, or HTTP responses. It is not a transcript, audit trail of roleplay content, or proof of runtime behavior. Director mode remains outside the feature contract.

Do not log prompts, player/NPC dialogue, raw request bodies, credentials, exception messages, or arbitrary caller strings. Raw content capture is not part of this implementation. Context is an event-specific allowlist of short scalar values, with stable IDs preferred over display names. Reasons are stable codes, not free text. If a caller supplies an unsupported context key or value, omit it.

## PHP interface

`server/log.php` exposes:

```php
pcv_log_begin_request(?string $configId = null): void
pcv_log_request_id(): string
pcv_log_set_config_id(?string $configId): void
pcv_log_set_playthrough_ref(?string $playthroughKey): void
pcv_log_path(): ?string
pcv_log_set_test_directory(string $directory): bool
pcv_log_event(
    string $event,
    string $severity,
    string $outcome,
    ?string $reason = null,
    array $context = []
): void
pcv_log_exception(string $event, string $severity, string $outcome, string $reason, Throwable $error, array $context = []): void
```

`pcv_log_request_id()` lazily initializes request context if needed, so a missed begin call cannot break logging. One request ID and `hrtime(true)` start value are retained for that PHP request. Events carry elapsed milliseconds computed from the monotonic clock. Debug entries are recorded only when a server-side `PCV_LOG_DEBUG_UNTIL` epoch setting is valid, still in the future, and no more than one hour ahead; a web form or request parameter cannot enable debug. Normal operational entries remain enabled. Invalid or expired debug settings mean debug is off. `pcv_log_set_playthrough_ref()` accepts the 64-character hashed state key and logs only a second hash prefix for pseudonymous association.

`pcv_log_path()` returns the configured destination only to CLI callers so the separate CLI diagnostics command can reuse the same path policy. It returns null in web requests. There is no web log viewer because the deployed CHIM authentication boundary has not been proven.

`pcv_log_set_test_directory()` is available only when `PCV_LOG_TESTING` is defined and the process is running under CLI SAPI. It accepts an explicit isolated fixture directory and does not change the production default.

Each JSONL entry has the fixed envelope `schema_version`, `plugin_version`, `timestamp`, `event`, `severity`, `outcome`, `reason`, `request_id`, `config_id`, `playthrough_ref`, `elapsed_ms`, and `context`. `timestamp` is UTC with millisecond precision. `schema_version` is held in one constant; `plugin_version` comes from a once-per-request cached `server/manifest.json` value. `pcv_log_exception()` adds only an allowlisted exception class, numeric code, source basename, and line; never the exception message or full path.

## State correlation and compatibility

The unpackaged solo-reflection source adds the fixed `scene_mode` values `pair` and `solo` to scene transition diagnostics. `state.scope_skipped` and `routing.request_skipped` with `scene_not_eligible` are informational: stale or missing eligibility stops generation without claiming a storage failure. Corrupt state and failed operations remain errors.

Reflection diagnostics use `reflection.output_registered`, `reflection.registration_skipped`, `reflection.registration_error`, `reflection.ack_skipped`, `reflection.ack_error`, and `reflection.evaluation_finished`. They retain a configuration ID and validated actor ID when available. Registration or an accepted evaluation is not an audio-playback success record. Missing optional compatible Mind Poisoning support skips registration; registry/database faults are failures. Mind Poisoning's existing RequestLog separately records model/persistence outcomes with `source_kind=reflection` and the actual `opinion_owner_id`, without a fictitious listener. Dialogue, prompts, subtitle digests and claim tokens are excluded from both logs.

The state module keeps state-file version 1 and accepts existing v1 records. It adds an optional `config_id` to pending and active records:

- A newly staged setting receives a random ID persisted with the pending configuration.
- Applying pending state copies that same ID to active state, so stage and activation events correlate.
- An END operation also receives an ID while pending; activation clears active state as it does today.
- Existing active records without an ID remain valid and are labeled uncorrelated (`config_id: null`) until replaced.
- A legacy pending record without an ID may receive one during promotion. Missing or malformed optional IDs normalize to null in results and logs; they do not invalidate an otherwise valid scope. If secure randomness is unavailable, keep the transition outcome and leave the ID null; logging or ID generation failure must never change functional state behavior.

The ID is correlation metadata, not an authorization token. The state key and actor validation remain authoritative.

## Storage and failure behavior

The writer uses one plugin-owned directory outside the public webroot, with restrictive directory/file permissions and symlink checks. It creates directories with mode `0700` and files with mode `0600`; it rejects symlinked paths/files, mismatched owners, and group/world-accessible permissions. The default is a per-install/effective-user directory under `sys_get_temp_dir()`. The logger canonicalizes the extension and webroot paths before accepting the default or an environment override, and rejects any destination inside the webroot. `PCV_LOG_DIR` is only a trusted server environment override for an admin-provisioned durable directory; it passes the same owner, permission, and webroot checks. If an override is invalid, the logger selects the safe default if that path passes checks, otherwise it uses the PHP error log. No user-facing path setting or caller-controlled destination is allowed.

Append, rotation, and the CLI reader use one stable lock; writer acquisition is nonblocking. Keep five JSONL files total (active plus four rotations), each no larger than 10 MiB; the separate lock file is not a log segment. Each serialized entry is bounded to 8 KiB; oversized optional context is omitted before writing. Rotation occurs under the same lock before an append would exceed the per-file limit. A reader holding the lock can make a concurrent writer drop an event, just as a writer can make a reader defer. Fixture code uses an explicit isolated temporary directory and never touches the production destination.

Logging is best-effort: `pcv_log_event()` does not emit output or throw into a hook/UI response. On an encode, permission, lock, rotation, or append failure, the logger sends one short fallback to PHP's configured `error_log` destination at most once per request, then drops subsequent logger failures. The fallback contains only a stable failure code, request ID, and valid config ID when available; it contains no exception message, prompt, dialogue, or secret and does not recursively call this logger. Logger-internal failures are caught; product operations are not blanket-caught to make them appear successful.

The fixture directory override must be test-only, explicit, and unavailable to HTTP callers (for example, an API gated by a test constant plus CLI SAPI). `PCV_LOG_DIR` is read only from the trusted server process environment and must pass the storage checks above; never take a path from HTTP input.

## Event and context policy

The state, routing, and UI callers use this fixed event/context allowlist. The routing layer emits at most one routing terminal event per request. State events report low-level state changes or causes, while UI events report page/submission outcomes; both may be present under one request ID.

| Event | Severity/outcome and reason | Allowed context |
| --- | --- | --- |
| `state.scope_staged` | `info` / `ok`; no reason | `action` (`enable|end`), optional decimal `actor_a_id`/`actor_b_id`, `exclude_player` (bool), `bystander_mode` (`exclude|silent`) |
| `state.scope_activated` | `info` / `ok`; no reason | Same as staged; END carries `action=end` and no actor IDs |
| `state.scope_expired` | `info` / `expired`; reason `active_ttl|pending_ttl` | `target` (`active|pending`) |
| `state.scope_invalidated` | `warning` / `invalidated`; reason `playthrough_changed` | optional valid `active_config_id` and `pending_config_id` |
| `state.unavailable` | `error` / `unavailable`; reason `invalid_state_key|identity_unavailable|state_unavailable|corrupt_state|symlinked_state|not_regular_file|state_stat_failed|state_too_large|state_read_failed|invalid_json|invalid_state|state_stage_failed|state_transition_failed|profile_lookup_failed|catalog_unavailable|presence_unavailable|presence_stale|presence_missing|presence_invalid|presence_key_mismatch|pair_not_eligible|unsupported_special_mode` | `operation` (`identity|read|stage|begin|presence_capture|presence_read|presence_invalidate`), optional structured exception metadata |
| `state.presence_refreshed` | `debug` / `accepted`; no reason | `actor_count` (integer 0–10000) |
| `state.presence_rejected` | `warning` / `rejected`; reason `presence_stale` | `operation=presence_capture` |
| `ui.page_open` | `info` / `ok`; no reason | none |
| `ui.unavailable` | `error` / `unavailable`; reason `session_unavailable|identity_unavailable|catalog_unavailable|state_unavailable` | `operation` (`session|identity|catalog|read`) |
| `ui.scope_stage_accepted` | `info` / `ok`; no reason | `action` (`enable|end`), `status` (`active|pending|off|unavailable`), `pending` (bool) |
| `ui.scope_stage_rejected` | `warning` / `rejected`; reason `invalid_configuration|actor_unavailable|actor_ambiguous|missing_settings|state_unavailable|access_denied|csrf_failed|internal_error|presence_missing|presence_stale|presence_unavailable|presence_invalid|presence_key_mismatch` | none |
| `ui.scope_stage_failed` | `error` / `failed`; reason `state_unavailable|readback_mismatch|internal_error` | `action` (`enable|end`), `operation` (`stage|readback`) |
| `routing.request_started` | `info` / `ok`; no reason | `request_type` (`inputtext|inputtext_s|ginputtext|ginputtext_s|rechat|narrator_inputtext|chat|prechat|continue|continue_group|instruction|bored|narration|other`) |
| `routing.request_prepared` | `info` / `ok`; no reason | `phase=context`; `route` (`scene_direction|player_speech|rechat_clamped|generated_event`), optional actor/speaker IDs, `exclude_player`, `bystander_mode` |
| `routing.request_skipped` | `info` / `skipped`; reason `scope_off|scope_pending|identity_unavailable|unsupported_mode` | `phase`, `request_type`, `state_status` (`off|pending|identity_unavailable|active`), `mode` (`standard|close|whisper|other`) |
| `routing.request_blocked` | `warning` / `blocked`; reason `unsupported_special_mode|invalid_input_prefix|invalid_input_encoding|empty_input|malformed_rechat|rechat_speaker_outside_pair|speaker_outside_pair` | `phase`, `request_type`, optional actor IDs |
| `routing.request_error` | `error` / `failed`; reason `state_unavailable|actors_unavailable|player_identity_unavailable|profile_switch_failed|actions_unavailable|context_unavailable|hook_exception` | `phase`, `request_type`, optional actor IDs and structured exception metadata |
| `routing.request_detail` | `debug` / `ok`; optional stable reason code | `phase`, `decision`, `request_type`, optional actor/speaker IDs, and integer before/after audience/presence counts |

Routing `phase` is `preprocessing|prerequest|context_pre|context`. `mode` is `standard|close|whisper|other`. Debug `decision` is one of `non_candidate_request|director_excluded|scope_off|scope_pending|identity_unavailable|unsupported_mode|input_rewritten|player_speech_preserved|rechat_clamped|responder_selected|context_prepared|action_constraints_refreshed|action_instructions_removed`. Counts are integers from 0 through 10000. Speaker IDs are optional validated decimal IDs and never names. `scope_rejected` is represented by the UI's `ui.scope_stage_rejected`, not a duplicate state event.

`ui.scope_stage_rejected` uses a fixed presence reason when an ARM POST fails because the report is missing, stale, invalid, unavailable, or belongs to another playthrough. A valid empty report or selected actor mismatch uses `actor_unavailable`; no UI rejection logs NPC names or IDs. `routing.request_prepared` means the extension finished its request-preparation work and passed its own guards. It does not prove the LLM accepted the request, generation completed, or audio/text was played. If a routing terminal event is absent, treat the diagnostic trace as incomplete, not as a successful response.

`state.presence_refreshed` is emitted only when debug logging is enabled and a validated autonomous report is committed to the 45-second presence cache; it records the count only, never actor names, raw payload, or native timestamp. `state.presence_rejected` means a report's native timestamp was not newer than the recent accepted report; the existing cache is retained. Other malformed, mismatched, or unavailable reports use `state.unavailable` with a fixed presence reason and clear prior eligibility when possible. The receiver is still a workspace source candidate; no package or live runtime claim follows from these entries.

Severity is one of `info`, `warning`, `error`, or `debug`; outcomes are closed per event. `ui.page_open` records a successfully prepared GET page, while `ui.unavailable` records failed GET setup. `ui.scope_stage_failed` records the submission outcome; a lower-level `state.unavailable` may also be logged under the same request ID with the safe cause. Actor IDs are approved only as validated decimal NPC identifiers in state/routing diagnostics; do not log actor names. Exception metadata is limited to class, integer code, source basename, and line. No event accepts arbitrary exception strings, request headers, or raw user input.

`ui.scope_stage_accepted` means the requested pending configuration was committed to state. If a later `ui.scope_stage_failed` reports `operation=readback`, the page could not confirm the resulting state; that report does not roll back the committed setting, which may still apply at the next eligible in-game input.

The state API adds top-level `config_id` (current active configuration, otherwise null), `pending_config_id` (queued configuration, otherwise null), and an optional safe `reason` to its result. During promotion, state emits the activation event with the pending ID before clearing the pending record; this includes END, whose resulting state is off. Existing callers can continue to consume the existing result keys. If an active pair fails the request-boundary eligibility guard, the result is unavailable with `reason=pair_not_eligible`, while the active `config_id` remains available for correlation; the state event is logged against that active configuration. A blocked pending enable is instead correlated to its pending configuration ID.

## Implementation and verification gates

1. **Pin 0 — contract:** approved by lead before implementation.
2. **Pin 1 — logger/state foundation:** implemented and approved at source/isolated-fixture level. State v1 compatibility, event redaction, correlation IDs, entry/file bounds, rotation, lock contention, and fallback behavior passed focused checks and PHP lint.
3. **Pin 2 — routing/UI/CLI diagnostics integration:** approved by lead after source review, focused checks, lint, and isolated page-flow regressions. The page-flow cases verified GET failure without a false page-open event and matching request ID, concurrent changed readback without a false stage-failure event and with the staged config ID, and unavailable readback with the staged config ID. The CLI reader uses `pcv_log_path()` and bounded operational entries only. Access to the default directory requires the web server's effective user. No web log viewer, transcript, or prompt capture is included.
4. **Pin 3 — package/review:** the `0.1.1` logging development candidate passed its package review after independent verification of archive membership/source bytes, checksums, CRC, schema-4 state-preservation behavior, and package checks. The later picker-eligibility update is package `0.1.2`, approved at source, isolated-fixture, and package level; its artifact hash and checks are recorded in `README.md` and `tasks/todo.md`. No live install/deployment, server authentication check, live connector run, installed-DLL match, or in-game certification is included.

## Known boundaries

The extension runs inside a shared PHP application. Per-request static context assumes PHP's normal request isolation; this remains to be checked against the installed execution model. CHIM's built-in logger is not a suitable dependency for this feature: its observed default is under the public `log/` tree and loading it also installs a global error handler. A bounded fixture proves serialization and rotation logic but cannot prove deployment filesystem permissions, server log routing, hook ordering, or live game behavior. The log-path accessor is for CLI diagnostics; it is not an authorization mechanism for a browser route.
