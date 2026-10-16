# CHIM core/client delivery diagnostics handoff

Copy this prompt to the CHIM core/client owner. This is an independent, separately owned implementation task; it does not authorize changes to PCV or Mind Poisoning.

```text
Use gpt-6-luna agents at Max effort and Ponytail FULL. Split independent work into coherent tasks with distinct file ownership, use available capacity only where useful, and reuse each responsible agent for follow-up fixes. Do not parallelize dependent tasks or shared-file edits. Others share the workspace; preserve edits. The lead reviews every diff, call path, failure and test output, and writes no product code. Run the smallest meaningful checks; do not repeat broad suites without a remaining risk.

Investigate the bounded delivery and cancellation behavior below in CHIM core and the Skyrim client. Keep this work separate from Private Conversation (PCV) and Mind Poisoning (MP); do not infer playback from server output or native queue insertion.

Observed trace: Caracas, 2026-10-14 21:22–21:35, PCV 0.1.17, MP 0.1.19, client 3.5.1 (2026-10-13), pair Elsa (5124)/Lidia (5126). Seven unacknowledged NPC chunks had native utterance IDs. Six were pushed to native ScriptQueue; queue-clear events followed newer player inputs at 21:24:02, 21:27:32 and 21:28:53. There is no playback trace, so the records do not say whether any chunk had already played. A seventh, utt_8848273663d6cf180588, had no native queue entry. At 21:29:53.124, HTTPManager.cpp:953 reported no data after 60 seconds and cancelled; the server emitted a fallback at 21:29:56, too late for that client request.

Please trace the existing provider-error, invalid-JSON, streaming timeout and cancellation paths. In particular:
1. Can a 503 or invalid JSON be recovered within the client's 60-second idle limit, and in what order do bounded retries, stream keepalives and terminal errors occur?
2. What happens to in-flight generation and already queued utterances when a newer input clears ScriptQueue or the client times out? Can a late fallback or chunk still be delivered after cancellation?
3. Which existing supported extension-facing diagnostics, if any, can report a per-utterance terminal state and the core stop reason without parsing log files at runtime? Prefer an existing supported surface; identify unknowns instead of inventing IDs or outcomes.

Do not start, access or write to the stopped live game distro. For every WSL fixture, explicitly invoke `wsl.exe --distribution DwemerAI4Skyrim3-test` and verify inside the distro that `WSL_DISTRO_NAME` is `DwemerAI4Skyrim3-test` before running tests; never rely on an inherited/default distro selector. Use only the retained trace above and this test distro with deterministic stubbed provider responses and isolated test state. Cover 503, invalid JSON, no data through the idle deadline, a newer input clearing queued output, cancellation followed by a late server response, and a healthy response. Assert the observed boundary between generated output, delivered/queued output and playback evidence. If source confirms a defect, implement the smallest core/client fix for bounded timeout/recovery and correlated cancellation/stop diagnostics through an existing supported surface, then add focused tests. Do not create a new runtime log parser or telemetry subsystem. Do not call a live provider, touch a live database, log raw dialogue, fabricate an utterance ID, edit PCV/MP, publish, or release.

Report exact core/client revisions, commands and outputs. Separate source inspection and test-distro behavior from installed-client, audio and gameplay proof. Preserve shared work and return only the owned core/client diff and focused test evidence for lead review.
```
