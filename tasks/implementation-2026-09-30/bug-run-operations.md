# Bug run: reflection ACK utterance ID normalization

## Confirmed defect

The PCV ACK gate rejected a valid native acknowledgement when its utterance ID ended in the CR retained by the native response parser. `pcv_reflection_ack_utterance_id()` validated the raw JSON string with the strict `utt_...` expression and returned `null`; both the public ACK hook and test orchestration returned before registry claim or MP evaluation. Mind Poisoning's `reflectionAckPayload()` applies PHP `trim()` before the same ID check, so the two consumers disagreed.

The inspected native source is pinned to `12e035d0a810b9b932fe2df1f688407a72cd27a1` in the Remaining limits section of `final-review.md`. `SPGResponse.cpp:34-47` reads response lines with `std::getline`; on CRLF input it removes LF while preserving CR. `Plugin.cpp:2384-2387` passes the line to `ScriptLine::parse`, `SpeakManager.h:106-111` copies the remaining suffix into `utteranceId` without trimming, and `SpeakManager.cpp:3587-3590` copies that ID into the `_speech` JSON. The pinned MP ACK parser trims it (`server/reflection.php:95-99`); MP's existing runtime regression at `tests/runtime_test.php:1063-1067` confirms a trailing CR is accepted and deduplicated.

Before the fix, a direct PHP reproduction returned `PCV=skip` and `MP=accept` for the same valid ACK ending in `\r`. The focused PCV registry fixture also failed as expected:

```text
Normalize the padded native ACK ID before exact registry matching.
expected: 'committed'
actual: 'not_applicable'
exit_code=1
```

## Minimal change

`server/reflection.php:572-588` now checks that `utterance_id` is a string, applies the same PHP `trim()` used by MP, then validates and returns the normalized ID. It changes no other payload field and leaves interior corruption invalid.

The focused fixture now registers the clean native ID, rejects an ACK with interior `\rX`, commits the CR-padded ACK through the real MP reflection API, then confirms the duplicate returns `claim_taken` without a second model callback. This covers the exact-registration/claim path as well as parser normalization.

```diff
 $utteranceId = $payload instanceof stdClass ? ($payload->utterance_id ?? null) : null;
-return is_string($utteranceId) && preg_match('/\\Autt_[A-Za-z0-9_-]{8,128}\\z/D', $utteranceId) === 1
-    ? $utteranceId : null;
+if (!is_string($utteranceId)) {
+    return null;
+}
+$utteranceId = trim($utteranceId);
+return preg_match('/\\Autt_[A-Za-z0-9_-]{8,128}\\z/D', $utteranceId) === 1
+    ? $utteranceId : null;
```

## Verification

- Red: the focused fixture failed on the padded ACK with `expected: 'committed'`, `actual: 'not_applicable'` before changing the parser.
- Green: PHP lint passed for `server/reflection.php` and `tests/reflection_registry_check.php`; the focused command `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/reflection_registry_check.php` exited 0 and printed `PCV reflection registry checks passed.`
- The fixture uses `MemoryStoreDb` and a local model callback. It does not call a live provider or installed database. MP source was not changed, so no MP suite was rerun.

## Scope and limits

Only `server/reflection.php` and `tests/reflection_registry_check.php` were changed for this fix; this report records the implementation. The audit also traced the one-attempt file claim, release of the PCV lock before database/model work, fresh PCV revalidation at MP pre-model and transaction phases, source-event recheck, and reflection-basis recheck under the owner transaction; no additional confirmed defect was found in those paths. No package, manifest, runtime scope/UI, installed extension, live database, provider, or audio behavior was changed or exercised.
