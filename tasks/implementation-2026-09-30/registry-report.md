# PCV reflection registry foundation report

## Contract implemented

The exported hooks are `pcvReflectionRegisterLastOutput(array $requestScope): void`, `pcvReflectionEvaluateAck(array $gameRequest): void`, and `pcvReflectionRevalidate(array $registration, string $phase, string $claimToken): bool`.

Registration reads the core's full, CRLF-terminated `DEBUG_DATA['OUTPUT_LOG']` wire line. It requires exactly three pipe fields, speaker equal to selected actor A, queue `ScriptQueue`, and exactly eight slash fields. Native field 2 and rechat field 6 must both be `explicit_disable_rechat`; field 7 must equal a fresh `SCRIPTLINE_UTTERANCE_ID`. It hashes only the trimmed subtitle at field 0. The independently queried MP event must match that utterance ID, event ID, actor speaker, explicit target sentinel, and a non-aborted `emitted` or `spoken` state.

A bounded private `reflection.json` record is atomically written under the PCV state lock with mode 0600 and a 600-second lifetime. A unique ACK claims it once. The PCV lock is released before fresh identity/scope reads, MP database calls, or model work. MP's actual `mindPoisoningEvaluateReflection()` API receives the registration, StoreDb, revalidation callback, and RequestLog. Revalidation checks the claim, PCV playthrough key, active config, actor, and solo scope before model work and before commit. Duplicate ACKs are consumed without a second provider call.

The MP RequestLog records reflection provenance and actor opinion ownership; PCV logs record accepted completion or bounded skip/error events without dialogue, speech digests, or claim tokens. CHIM's `emitted` state records server emission; a matching `_speech` ACK establishes a correlated native line attempt, not proof that audio played for the user.

Both public hooks contain optional MP module load/parse failures and record bounded internal-error diagnostics; a broken module cannot abort ordinary ACK handling.

## Verification

- PHP lint passed for `server/reflection.php` and `tests/reflection_registry_check.php`.
- Focused fixture passed: `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/reflection_registry_check.php`.
- The fixture uses the real MP reflection API, fixture-only `MemoryStoreDb`, a local model callback, and isolated temporary state/log directories. It covers exact CRLF native framing and field positions, differing core source text versus subtitle, wrong ID/subtitle/sentinels/extra delimiters, aborted source, stale baseline and expiry, owner-only persistence, duplicate ACK, scope change at transaction recheck, provider failure, corrupt registry, lock release before provider work, log redaction/attribution, and containment of a malformed optional MP module in a subprocess.
- Read-only `_source_entries()` inspection found 17 declared server files, including the four new runtime files; no archive was built.

## Limits

No installed extension, live database, provider, or audio playback was exercised. The fixed MP path was checked against the flat deployed extension layout by source assertion; it was not tested against an installed CHIM tree. The package allowlist source now includes `json_response_custom.php`, `prepostrequest.php`, `postrequest.php`, and `reflection.php`; the package artifact was not built or versioned.
