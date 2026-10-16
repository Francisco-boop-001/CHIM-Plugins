# Solo reflection integration API

`server/reflection.php` exposes the opt-in evaluator below. Loading it does not register a hook or call the model.

The API declares `\ChimMindPoisoning\MIND_POISONING_REFLECTION_API_VERSION = 1`. Private Conversation 0.1.15 still requires exactly version 1 before using reflection; a missing or unsupported version is treated as unavailable. This check is a companion compatibility contract, not a CHIM core pin.

## Relationship-type judgments in v0.1.20

Version 0.1.20 supports an optional `type_change` in a judgment and has passed isolated fixture checks. A response may set it to `null` or to `{ "from": "<snapshot type>", "to": "<proposed type>"}`; legacy affinity-only responses without the field remain accepted. It uses the existing model call and eligibility gates for ordinary NPC gossip, Player-origin input, NPC acknowledgements and overhearing, and reflection. It changes CHIM relationship data, not Skyrim's vanilla relationship ranks, and does not add a reflection API version or change the v1/v2 function signatures.

The default is to keep the current type; a change requires clear lasting grounds and exact evidence. Current custom types are preserved. Current `romantic`, `familial`, and `professional` types are protected from replacement by `jealous`, `protective`, `suspicious`, `fearful`, `obsessed`, `awed`, `contempt`, `pitying`, `grateful`, `curious`, `dismissive`, or `admirer`; a valid affinity delta may still apply when such a type proposal is preserved. Unknown or malformed type values reject the response. A `from` value that differs from the snapshot invalidates the model response. If the actual type changed before the locked write, the entire judgment is stale and no affinity portion is applied. A type-only change with zero affinity delta is valid and commits atomically under the existing lock and replay rules.

Ledger v1 entries may add an optional `judgment.type_change` plus `type_decisions` and `type_changes`; old entries without these fields remain readable. The dashboard bounds and canonicalizes optional type-history details, skips malformed or unknown entries, and may show a valid legacy judgment proposal without a reason when an older entry lacks `type_decisions`; it never treats a proposal as application. Explicit `type_decisions` use `{subject, from, to, reason}` with fixed reasons `change_eligible`, `same_type`, `protected_type_preserved`, or `custom_type_preserved`. `type_changes` contains only confirmed applied transitions as `{subject, before, after}`. `changed_count` counts distinct relationships changed by either affinity or type, while `affinity_changed_count` and `type_changed_count` separate them. The existing affinity `changes` array retains its fields.

Private Conversation's revision 2 importer is unchanged. A type-only commit has `changed_count > 0`, so the importer continues to classify it as committed while ignoring added type details; consult Mind Poisoning's dashboard or filtered JSONL log for those details. Isolated integration checks confirmed type-bearing MP records are accepted and classified by the unchanged importer. The physical POSIX JSONL sink was not exercised on Windows. No PCV code or API version change is required. The new type behavior has no live database, provider, or gameplay verification; v0.1.20 remains PRE-ALPHA.

The evaluator function, `StoreDb`, and `RequestLog` are in namespace `ChimMindPoisoning`; import them or use their fully qualified names. Mind Poisoning never loads Private Conversation. A companion such as PCV attaches the observer and passes its `RequestLog`; Mind Poisoning continues its normal evaluation and ordinary sink delivery.

```php
use ChimMindPoisoning\RequestLog;
use ChimMindPoisoning\StoreDb;
use function ChimMindPoisoning\mindPoisoningEvaluateReflection;

mindPoisoningEvaluateReflection(
    array $registration,
    array $gameRequest,
    StoreDb $store,
    callable $revalidate,
    ?callable $requestModel = null,
    ?RequestLog $requestLog = null
): string
```

The registration has exactly these keys: `event_id`, `utterance_id`, `actor_id`, `actor_name`, `playthrough_id`, `config_id`, `rechat_target_hint`, and `speech_hash`. It identifies one positive event ID and `utt_` ID, the NPC owner, active playthrough/configuration, the `explicit_disable_rechat` sentinel, and the SHA-256 digest of the acknowledged subtitle. The game request must be the matching native `_speech` ACK with `speaker`, `listener`, `speech`, and `utterance_id`. The evaluator checks the exact emitted/spoken source event and subtitle; it does not select a row by recency or text similarity.

`$revalidate($registration, $phase)` must read the companion's private registration and return whether the same scope is still active. It is called at `pre_model` and twice during `transaction`, so it must be read-only and safe to repeat. `$requestModel` is a test/integration seam; if omitted, the normal Mind Poisoning request function is used. The return value is an evaluator status string; it does not replace the structured request log.

## Full-reply reflection API (v0.1.16)

Mind Poisoning 0.1.15 introduced the opt-in `MIND_POISONING_REFLECTION_REPLY_API_VERSION = 2` and `mindPoisoningEvaluateReflectionReply()`, accepting up to eight reply lines; the published 0.1.16 release raises that cap to 24. MP 0.1.14 exposes only v1. The v1 constant and function, v2 capability and function, registration fields and other guards remain unchanged. Embedded Private Conversation 0.1.15 still requires exactly v1, then uses v2 only when the exact `MIND_POISONING_REFLECTION_REPLY_API_VERSION = 2`, `mindPoisoningEvaluateReflectionReply()`, `chatSourceParts()` and `reflectionSourceParts()` APIs are available and it can prove the ordered reply window from one request. Otherwise, including when grouping is unproven or exceeds the negotiated cap, it falls back to the v1 final-line registration. The imported PCV 0.1.15 README's `.14–.15` final-line-only summary is too broad for MP 0.1.15's published v2 support; the imported README remains byte-identical to its tag. The recorded five-case observer/importer pass ran before this pin while the fixture loaded PCV 0.1.12. The current working-tree fixture now resolves the path to PCV 0.1.15, but that fixture has not been rerun against this snapshot. The immutable published MP 0.1.16 tag still contains PCV 0.1.8 at that path. See the [0.1.16 release notes](../distribution/mind_poisoning-v0.1.16.md) for the MP release delta and evidence limits.

```php
use function ChimMindPoisoning\mindPoisoningEvaluateReflectionReply;

mindPoisoningEvaluateReflectionReply(
    array $registration,
    array $gameRequest,
    StoreDb $store,
    callable $revalidate,
    ?callable $requestModel = null,
    ?RequestLog $requestLog = null
): string
```

The top-level registration has exactly the eight v1 keys (`event_id`, `utterance_id`, `actor_id`, `actor_name`, `playthrough_id`, `config_id`, `rechat_target_hint`, `speech_hash`) plus `lines`. The top-level event, utterance, and hash identify and equal the final tuple. In published 0.1.15, `lines` is a PHP list of 1–8 exact-key tuples; the published 0.1.16 release accepts 1–24. Each tuple is `{event_id: positive int, utterance_id: valid utt_ string, speech_hash: lowercase SHA-256}`. Event IDs increase strictly and utterance IDs are unique. Numeric ordering rejects reversal but does not establish reply membership. The API accepts no caller dialogue, client-supplied group, recency lookup, or synthesized ACK. This cap change leaves the eight-subject limit and 2,000-code-point/8,000-byte joined-text limits unchanged.

The companion's server-only callback receives the complete original registration. At both `pre_model` checkpoints and both `transaction` checks, it must confirm the same claim, configuration, playthrough, actor, active interaction scope, and complete immutable ordered output list captured from one companion request; it must reject a list mixed across replies. MP has no source-backed reply-group identifier and cannot independently prove that grouping. After callbacks, MP re-reads each source; it also checks every source under the actor lock before commit. Missing, aborted, changed, or mismatched rows fail closed; an `emitted` to `spoken` transition remains valid.

For v2, MP resolves every tuple through `StoreDb`, parses the existing explicit `explicit_disable_rechat` source, trims its UTF-8 body, and requires the registered digest to equal SHA-256 of that parsed body. V1 retains its existing contract: the native ACK speech digest must match the single-line registration, while v1 does not compare that digest with the parsed source body. V2's native `_speech` ACK must match the registered actor, supported Player transport listener, and final tuple; an earlier valid ACK returns the fixed informational `reflection-non-final-ack` skip. MP joins source bodies in tuple order with one ASCII space. The joined text is limited inclusively to 2,000 Unicode code points and 8,000 bytes; it is rejected without truncation. A valid base registration with more than eight lines in the published 0.1.15 package, or more than 24 in the published 0.1.16 release, returns `reflection-reply-too-many-lines`; text overflow returns `reflection-reply-too-large`.

Replay checks cover every registered event ID and utterance ID against the existing ledger and numeric eviction floor before provider work and again inside the transaction. A confirmed reply records all tuples in the existing bounded 128-entry ledger in one atomic relationship write and history snapshot; earlier entries have empty judgments and the final entry carries the reply decision. Confirmed zero decisions still record every tuple. No table or `StoreDb` method was added. V2's persisted source digest hashes the joined parsed bodies; terminal diagnostics remain correlated to the final event and utterance. A false or throwing commit remains failed/unconfirmed.

When `RequestLog` is omitted, the shared evaluator creates the normal request logger; when supplied, it reuses that instance and preserves its observer delivery. Each invoked evaluation attempts one terminal `request_finished` summary. Expected skips such as non-final ACK, replay, locked actor, or no eligible subjects are informational. Malformed registration/ACK, source mismatch, and reply caps are warning-level rejections. Provider/internal and persistence failures are errors, including an unconfirmed commit; invalid model responses are warnings. The sanitized schema contains fixed reasons and bounded correlation/status data, not dialogue, source hashes, claim tokens, or exception messages. Delivery is best-effort: logger construction or sink failure cannot change the evaluator result, and a failed sink can drop a record. PCV must log attributable reflection exits before it calls MP; ordinary or unregistered ACKs that never reach MP do not create MP records.

The focused source checks are `php tests/reflection_diagnostics_test.php` and `php tests/reflection_reply_test.php`; the latter covers MP's v2 behavior. Before the 0.1.14 pin, the lead ran `php tests/reflection_observer_test.php` with the embedded PCV 0.1.12 snapshot; all five cases passed (committed delta, zero change, provider failure, unconfirmed commit, warning skip). The separate PCV full-reply fixture also passed against the then-pinned 0.1.12 source. Neither result is a PCV 0.1.15 test. The immutable published MP 0.1.16 tag retains PCV 0.1.8. These isolated fixtures do not prove companion grouping/subtitle correspondence, live provider behavior, PostgreSQL durability, installed extension order, or audible playback.

## Private Conversation source and overhearing boundaries

The pinned PCV 0.1.15 snapshot keeps the v1/v2 reflection negotiation described above; its API markers and registration contract are unchanged. Its roleplay path is distinct from Mind Poisoning's event evidence:

The published PCV 0.1.14 guide retains an addressed-only summary; MP 0.1.17 already supports optional bounded overhearing on ordinary addressed NPC ACKs, as described here.

- `end scene` or `end the scene` is matched as the whole ordinary input before activation and is consumed without being sent to an NPC. `wrap up: <direction>` closes a pair, group, or free scene while routing one closing reply. The normal listener is CHIM's `explicit_disable_rechat` sentinel, and PCV disables the relationship queue for that request. Sentinel-targeted lines have no addressed NPC and do not receive MP 0.1.17 witness-only judgments. When a SHARMAT member-listener pin is honored, it can replace the sentinel; any overhearing eligibility then depends on the actual emitted source, ACK target and ordinary MP gates. That scoped interplay was source/offline checked, not tested in a live intimate scene.
- In groups of three or more, PCV narrows listener choices to members who have not spoken in the current round and lifts CHIM strict rechat targeting for those spread rechat requests only. CHIM still controls whether and how many rechats run.
- The optional scene card is added to prompt context, including rechats and solo reflection; it is not dialogue or eventlog evidence. Turn length is model guidance. Neither setting proves what a generated reply says or what a listener heard.
- PCV's roleplay guidance adds to each NPC's existing condition and explicitly says it does not replace it. Source and fixture checks cover SHARMAT condition/pin behavior; the tag's release note records clone observations, but intimate-scene interplay was not exercised with OStim. Do not read this as general installed or gameplay certification.
- `scope_unavailable` is PCV's own reason when its reflection-scope read fails. If PCV's revalidation callback only returns false, MP records its generic stale-registration result; `scope_unavailable` is not an MP overhearing reason.

Mind Poisoning 0.1.17 overhearing applies to eligible NPC ACKs with a resolvable addressed NPC listener. It uses the exact source event's `eventlog.people` roster for additional eligible listeners; that roster does not prove playback, PCV scene membership, or overhearing. Sentinel-targeted PCV wrap-up lines normally skip the MP 0.1.17 batch when no NPC catalog row resolves; if CHIM resolves a catalog row literally named `explicit_disable_rechat`, released MP 0.1.17 lacks an explicit reserved-name veto in both addressed and overheard paths. A separately reviewed source candidate adds that guard but remains unreleased. If a SHARMAT pin changes the actual target to a member, MP evaluates the source/ACK under its ordinary rules; MP does not receive a PCV wrap-up flag or add a sentinel-only witness path. The scene card does not enter that source roster. This integration boundary was reviewed from source; no new database, provider, installation, or gameplay run was performed for the PCV 0.1.15 hub pin.

## NPC ACK overhearing API (v0.1.17)

Mind Poisoning 0.1.17 PRE-ALPHA adds opt-in NPC ACK overhearing under the namespaced capability marker `\ChimMindPoisoning\MIND_POISONING_NPC_ACK_OVERHEARING_API_VERSION = 1`. This feature is disabled by default with `mind_poisoning_overhearing_enabled=false`. Administrators enable it through CHIM's supported `chimSetGeneralSetting` helper in the administrator-managed bootstrap; Mind Poisoning adds no setting-write endpoint.

For an eligible NPC `_speech` acknowledgement, one model call can evaluate the addressed listener plus up to four additional eligible NPC listeners (maximum 4,096 tokens; ordinary addressed-only evaluation remains capped at 1,024). Additional listener names are drawn from the exact source event's `eventlog.people` roster. The roster establishes event membership; it does not prove hearing or Private Conversation scene participation. If more than four eligible extras are present, skip the extras and continue the direct addressed-listener evaluation.

Each listener's persistence is independent, so partial commits are possible. Replay checks are sequential and do not reserve provider work across concurrent acknowledgements; concurrent duplicates may each make a provider call. Per-listener records carry `listener_role` (`addressed` or `overheard`), `addressed_listener_id`, and `batch_id` for correlation.

The eight focused fixtures and eleven changed-file PHP syntax checks passed in the authorized test clone. They do not establish native setting operation, live database durability, real provider behavior, installed CHIM behavior, or game/audio delivery. See the [0.1.17 release notes](../distribution/mind_poisoning-v0.1.17.md).

## Optional request observer

`RequestLog::observe(?callable $observer): void` installs one callback on that `RequestLog` instance. Each accepted log record is delivered as `(array $record, string $level)` after the normal sink attempt. A second call replaces the callback; `observe(null)` clears it. Records emitted before installation are not replayed. Observer failures are swallowed and cannot change evaluation or persistence, and reentrant observer delivery is suppressed.

The observer receives the sanitized record and fixed level. Validated `config_id`, `event_id`, and `utterance_id` are deliberately included for exact correlation; they are identifiers, not proof of a speaker or listener. Debug `model_reason` is removed from this observer-only copy. The existing diagnostic sink can still receive that bounded, untrusted text when debug logging is enabled; it may contain names or a short game/model excerpt. The observer/importer boundary does not forward raw dialogue, prompts, speech digests, claim tokens, or exception messages.

## Private Conversation revision 2 import (published in 0.1.5)

The importer accepts only `source_kind=reflection` records for `reflection_model_finished`, `persistence_finished`, `persistence_cleanup_failed`, or `request_finished`, with level `debug`, `info`, `warning`, or `error`. It then checks the event-specific outcome and level before writing a fixed Private Conversation record. Unrelated evaluator events and invalid level/outcome pairs are ignored.

| Mind Poisoning evidence | Imported result |
| --- | --- |
| `reflection_model_finished`: valid/info, invalid/warning, failed/error | `reflection.model_finished`: valid/info, invalid/warning (`model_invalid`), failed/error (`model_failed`) |
| Confirmed commit with changes | `reflection.persistence_finished`: committed/info with bounded change tuples |
| Confirmed commit with `changed_count=0` | zero_change/info, reason `zero_change` |
| `commit_state=unconfirmed` | unconfirmed/error, reason `commit_unconfirmed`; this means COMMIT was attempted without confirmation and does not prove that no database change occurred |
| Cleanup failure | cleanup_failed/error; a confirmed commit remains recorded as confirmed |
| Final rejected/failed/skipped result | Rejected at warning, failed at error, and skipped at info. A skipped final summary at warning maps to rejected/warning. |

Every imported record must carry the source's 24-character lowercase `request_id`, a positive event ID, an accepted utterance ID, and the matching configuration ID. The importer writes the source request ID as `linked_request_id` and pins `event_id` plus `utterance_id` in the current PCV request correlation. A conflicting configuration or event/utterance tuple is dropped. This hook is not a general join API: it correlates one solo-reflection tuple, and no pair-evaluation correlation contract is implemented here.

Mind Poisoning validates `config_id` as a generic UUID shape, without enforcing UUID version or letter case. The PCV importer requires lowercase UUIDv4. IDs generated by the PCV registration path satisfy that stricter check; a generic valid MP UUID may be ignored by the importer.

The importer chooses `reason` before `persistence_reason` for `source_reason`. A final `request_finished` record's `reason` is the aggregate terminal status, so its imported reason can be broad (`committed`) or `other`. Use the earlier `persistence_finished` record for the specific fixed persistence reason and commit details; that imported event is the actionable persistence record.

## Isolated cross-boundary check

From `projects/CHIM-MindPoisoning`, run `php tests/reflection_observer_test.php`. The fixture resolves `../plugins/private_conversation/server/reflection.php` dynamically, so the current checkout now loads PCV 0.1.15. The recorded five-case pass happened before this pin and loaded PCV 0.1.12; no post-pin run is claimed. The immutable published MP 0.1.16 tag still contains PCV 0.1.8 at that path. The fixture invokes the MP evaluator and observer, then the loaded PCV importer, with an in-memory store and isolated temporary log directories. It does not load the production bootstrap, use a live provider, or connect to a database.

The standalone PCV 0.1.15 `tests/reflection_registry_check.php` needs the matched MP 0.1.16 source/API fixtures. The recorded MP working-tree observer test loaded PCV 0.1.12 before this pin; it does not test PCV 0.1.15. The immutable published MP 0.1.16 tag retains its PCV 0.1.8 snapshot. A PCV-only clean source export cannot supply Mind Poisoning's evaluator or test fixtures; runtime packages contain only their own allowlisted server files and never include the other plugin or tests. These fixtures establish source/API compatibility only, not installed extension order, live persistence, provider behavior, or game/audio delivery.
