# Prompt for the separate Mind Poisoning task

Improve Mind Poisoning's diagnostic interaction with Private Conversation. Work in `K:/ActorwrightExchange/projects/CHIM-MindPoisoning`; treat `K:/ActorwrightExchange/projects/CHIM-PrivateConversation` as read-only integration evidence. Private Conversation logging revision 2 has an optional sanitized reflection importer and gracefully supports published MP without an observer. Keep both projects' responsibilities separate.

Use the CHIM server plugin creation/review skill and Ponytail at its default FULL level. Preserve other contributors' edits. The lead must not write product code: delegate coherent exclusive ownership to gpt-6-luna agents at Max, reuse owners for fixes, and review every changed diff, affected call path and verification output. Parallelize only independent work. Record the plan/evidence in this project's task files. Do not change release/version/package metadata, install into CHIM, call live providers/databases or publish as part of this task.

First inspect current MP `server/logging.php`, `server/reflection.php`, `server/store.php`, existing logging/reflection tests and PCV `server/log.php` / `server/reflection.php` / `docs/logging-revision-2.md`. Confirm the current source contract before changing it; do not copy an old patch blindly.

Implement the smallest compatible diagnostic bridge:

1. Add an optional `RequestLog::observe(?callable $observer): void` API. Keep the existing constructor and normal sink intact. After attempting normal delivery, invoke the observer with the **already sanitized** record and its fixed severity. Contain observer failures so they cannot alter provider calls, persistence, rollback, affinity values, ordinary logging delivery or returned domain status. Keep raw data out of this callback.
2. Preserve a validated reflection configuration UUID in RequestLog context only after MP validates the registration. Retain MP's existing request ID, exact event ID, exact utterance ID, `source_kind=reflection`, speaker/opinion-owner identity and fixed reason codes. Do not alter the reflection registry schema, matching or authorization.
3. Confirm that the existing model, persistence/cleanup and final records expose the fields expected by PCV's importer. Forward their actual outcomes, timing, bounded changes and commit state. Zero change is legitimate. An attempted but unconfirmed commit must remain uncertain/error; do not relabel it committed. Preserve fixed underlying reasons such as `model_request_failed`, `commit-failed`, `pause_control_invalid`, stale scope and replay, without arbitrary messages or model prose.
4. Do not make MP depend on loading PCV or its filesystem path. PCV attaches the observer when available; MP owns its normal sink and evaluation. Do not infer pair-scene correlation from the latest row, active scene or hook order. Pair results stay in MP diagnostics unless a separate verified pair contract is explicitly authorized.

Use the current PCV importer as the compatibility reference. It expects schema version 1, plugin `mind_poisoning`, source kind `reflection`, a 24-character lowercase hexadecimal MP request ID, a valid configuration UUID, a positive exact event ID and valid `utt_...` utterance ID. It accepts `reflection_model_finished`, `persistence_finished`, `persistence_cleanup_failed` and `request_finished`, with closed source levels and fixed domain fields. Read the implementation for the exact allowlists; do not weaken either side to make a test pass.

Prove these concrete behaviors with the smallest isolated checks:

- Normal sink still receives the same sanitized record when the observer throws; neither receiver obtains dialogue, prompts, credentials, subtitle digests, claim tokens or arbitrary exception text.
- Genuine reflection registration/ACK retains the exact configuration/event/utterance tuple; invalid registration never gains that correlation.
- Committed changes, confirmed zero change, returned failure, uncertain commit and warning-level skip reach a PCV-compatible sanitized observer with truthful severity/reason. Do not assume all skipped records are informational or all persistence failures mean no write occurred.
- Stale/duplicate acknowledgements preserve existing replay/revalidation behavior and do not call the provider twice.
- MP still behaves normally with no observer and PCV absent.

Use existing isolated store/model seams, never live CHIM data. Explain red-to-green evidence, run changed PHP syntax checks, and stop optional testing after the relevant risks are covered. Static lint is not runtime proof; isolated execution is not installed-game, database durability or playback proof. Return exact files/commands/results, limitations and the final integration judgment. Do not publish or advance a release pin.
