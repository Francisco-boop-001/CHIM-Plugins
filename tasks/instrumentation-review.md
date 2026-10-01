# Operational instrumentation review

## Result

The PCV instrumentation slice is ready for lead review. Routing and reflection diagnostics use fixed outcomes and bounded context, and the changes leave routing, presence, and opinion-evaluation decisions intact. `postrequest_observed` means only that the hook ran; it is not evidence of emitted audio or playback.

Solo reflection logs retain the validated `config_id`, exact event ID, and utterance ID across registration and ACK. The adapter checks for `RequestLog::observe()` and isolates observer failures. The currently published Mind Poisoning interface does not provide that method, so PCV records `reflection.observer_unavailable` and continues evaluation. Detailed model and persistence records remain in Mind Poisoning's own log. PCV emits `reflection.evaluation_finished` only for an API result of `committed`; failed, stale, and rejected outcomes do not become accepted summaries.

Presence diagnostics distinguish an actually empty report from a missing report, an established baseline awaiting advancing ordering evidence, a stale report, and an unavailable read. The private observation override is removed before returning results to existing callers, preserving their prior status and reason values. Registration exceptions now use the shared safe exception logger, retaining only exception class, integer code, basename, and line number.

Pair gossip continues through its ordinary route. This slice does not correlate pair model/persistence results into PCV logs; those remain in Mind Poisoning diagnostics. No audio, playback, or human-hearing proof is claimed.

## Files in this slice

- Changed runtime files: `server/preprocessing.php`, `server/postrequest.php`, `server/scope.php`, `server/state.php`, and `server/reflection.php`.
- Changed/new focused checks: `tests/background_presence_check.php`, `tests/reflection_registry_check.php`, and `tests/postrequest_terminal_check.php`.
- Reviewed unchanged call paths: `server/prerequest.php`, `server/context_pre.php`, `server/context.php`, `server/prepostrequest.php`, and `tests/scope_check.php`. These have no current-task diff; no new scope-check run is claimed here.
- Mind Poisoning tracked source/test files were restored to the user's baseline; no MP code change remains from this task.

## Verification

Focused checks ran in WSL PHP 8.2.29 using isolated temporary fixtures only:

- `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/reflection_registry_check.php` — exit 0; `PCV reflection registry checks passed.` Assertions cover exact ACK correlation and replay, stale/provider-failure behavior, unchanged/zero-change handling, no false accepted count, and graceful operation without the optional MP observer. The fixture's MP records were emitted by its isolated fake store/model; no live provider or database was called.
- `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/background_presence_check.php` — exit 0; `PASS: heartbeat baseline, monotonic receipt, bounded status join, empty versus stale, identity reset, and no legacy fallback`.
- `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/postrequest_terminal_check.php` — exit 0; `PASS: empty-output postrequest is logged as hook observation only`. Its isolated failing registration asserts bounded exception metadata and confirms the exception message and dialogue are absent from logs.
- PHP lint passed for `server/postrequest.php`, `server/state.php`, and `tests/postrequest_terminal_check.php`; the existing focused lint gate also passed for `server/reflection.php`, `server/scope.php`, `server/preprocessing.php`, `tests/reflection_registry_check.php`, and `tests/background_presence_check.php`.

The broader logger/reader checks were run and reported green by the logger owner; they were not repeated here. No installed CHIM, game, database, provider, package build, or external publication was used.

## Follow-up Mind Poisoning task prompt

Add an optional sanitized `RequestLog` observer while preserving the existing sink and evaluation behavior if the observer throws. Forward only validated reflection `config_id` and exact event/utterance correlation plus fixed model, persistence, and final outcome fields. Cover committed, zero-change, uncertain, failed, and stale results; test that observer failure cannot change the original sink or affinity evaluation. Keep this separate from PCV and do not change release or package metadata in that task.
