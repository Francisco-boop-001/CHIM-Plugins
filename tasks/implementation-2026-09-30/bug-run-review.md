# Bug-run lead judgment — 2026-09-30

Result: one confirmed defect fixed and accepted. No additional defect was confirmed in the bounded review. This is source and isolated-fixture evidence; it is not live CHIM or gameplay certification.

## Defect and fix

PCV rejected genuine reflection acknowledgements whose utterance ID retained a trailing carriage return from native CRLF parsing. MP already normalizes this field. The early PCV rejection silently prevented registry matching and reflection evaluation.

The registry owner changed only PCV server/reflection.php and tests/reflection_registry_check.php: validate the ID type, trim that ID, then perform the same strict validation and exact match. Interior invalid characters remain rejected. No raw dialogue matching, fuzzy correlation or provider retry was introduced.

Lead reviewed the parser's public ACK and orchestration callers, pinned native response/ScriptLine/ACK path, new assertions and owner red/green output. The new fixture failed before the fix (`committed` expected, `not_applicable` actual), then passed. It verifies a registered clean ID plus CR-padded ACK commits once, an identical retry does not call the model again, and interior corruption is rejected. Lead independently reran the complete isolated registry fixture: exit 0, `PCV reflection registry checks passed.` Its logged commit is MemoryStoreDb/model-callback evidence only.

## Bounded audit coverage

Runtime/UI owner reviewed effective mode changes, solo/pair transitions and B restoration, generated-event origin, rechat/continuation constraints, listener schema and disabled/stale handling. No new confirmed defect; no product/test edits or unchanged suite reruns.

Registry/MP owner reviewed correlation, claim lifecycle, lock boundaries, fresh scope validation, opinion ownership, source-event recheck and unchanged-evidence protection under transaction. Only the ACK normalization defect was confirmed; MP product source remains unchanged by this run.

Lead reviewed state normalization/staging/activation, shared identity, stale/empty roster capture/read and diagnostic boundaries. Fresh focused checks all exited 0:
- state_check.php: identity, config correlation, legacy compatibility, invalidation/expiry logs and corruption preservation.
- background_presence_check.php: heartbeat baseline, monotonic receipt, bounded status join, empty versus stale, identity reset and no legacy fallback.
- log_check.php: redaction, rotation, private permissions, concurrent JSONL, unsafe paths and lock fallback.

The owner also supplied successful PHP lint for both changed files. No broad MP, browser, package or game suite was rerun.

## Scope and evidence limits

Lead wrote review/task documents, not product code. Existing gpt-6-luna Max coding owners applied Ponytail FULL and the CHIM plugin skill, with distinct ownership and preservation instructions. A third independent slot could not be started because the retained agent thread limit was reached; the lead covered state/log review instead.

Both manifests are byte-identical to the saved preimplementation baseline. Historical PCV artifacts retain their approved SHA256 values:
- private_conversation-0.1.3.dwpkg: ce0fe4bb99979c12313a60c2a051fce7cf5bd6d54c064fd52fbccbb3cb4767ee
- private_conversation-companion-0.1.3.zip: 4a7d1f47719cfa4e4a94e8eeca51d7336529f477dffb7dfba8fdc483ee318e17

No package build, release-pin advance, installation, live database/provider calls or game changes. Existing limits remain: Director/separate rolemaster paths, installed DLL equivalence, physical hearing/privacy, native playback success, live cross-extension ordering and projected roster freshness are not certified by this audit.

Detailed evidence: bug-run-operations.md, bug-run-modes.md, bug-run-state.md in this directory. Accepted original implementation review remains final-review.md; this bug report supplements it with the corrected ACK boundary.
