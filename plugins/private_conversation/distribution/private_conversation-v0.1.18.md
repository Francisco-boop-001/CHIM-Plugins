# Private Conversation 0.1.18 — ALPHA

This is an ALPHA prerelease for testing on an isolated CHIM server and test profile. It is not a stable or beta release. The llama has learned to say “I can’t confirm that”; it has not learned to certify your whole modlist.

## What changed

END reports success only after PCV confirms the scene state is `off`. If clearing the state cannot be confirmed, direct END and solo wrap-up use the existing state-unavailable error path and do not forward the control phrase as dialogue. Pair/group wrap-up stops before generating its closing turn. The message is deliberately cautious: **“Could not confirm the scene ended; it may remain active.”** An unavailable result can occur after a write, so it does not prove the old state remained unchanged.

The active-scene last-turn status now distinguishes whether PCV observed CHIM's completion hook. This is lifecycle evidence only: it does not claim that speech was generated, delivered, or played, or infer a stop cause. An unobserved hook points operators to CHIM/native logs. The targeted END failure fixture exercises its caller paths with stubbed stage results. It does not inject a real storage fault or verify HTTP responses, logger output, or live failure behavior; see the [END verification report](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.18/tasks/alpha-end-2026-10-15/end-report.md). Earlier source and synthetic checks cover other PCV paths.

## Evidence and limits

| Evidence | What it supports | What it does not establish |
| --- | --- | --- |
| Focused source and synthetic fixtures | Specific state, routing, diagnostic, and END caller contracts; the END failure fixture ran in the explicitly selected test distro. | A real persistence failure, every integration path, an installed client, or Skyrim gameplay for 0.1.18. |
| Disposable CHIM clone | Selected pair, background, wrap-up, and solo flows with real provider calls and a simulated client; see the [recorded clone findings](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.18/tasks/live-issues-2026-10-04.md#0115-verification-clone-2026-10-04-about-4-ai-calls-test-dwpkg-sha256-0d408bd5). | Native game-client queue behavior, audio playback, or gameplay feel. |
| Skyrim sessions | Specific pair rechat, END, and wrap-up sessions on 0.1.14, plus a 0.1.17 pair/solo reflection trace; see the [gameplay findings](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.18/tasks/live-issues-2026-10-04.md) and [0.1.17 trace](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.18/docs/logging-revision-2.md#observed-pair-trace-2026-10-14). | Full acceptance or playback proof. The 0.1.17 trace left client-delivery questions open; MP also recorded 45 `no-subjects` outcomes. The separate [client-delivery](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.18/docs/followups/chim-core-delivery-diagnostics-2026-10-15.md) and [MP subject](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.18/docs/followups/mind-poisoning-subject-diagnostics-2026-10-15.md) follow-ups are prepared, not completed. |

These are evidence for their named paths and versions. They do not certify privacy, model compliance, speech playback, every install route, or a complete operator acceptance run. Use the [operator checklist](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.18/docs/operator-acceptance.md) to record a specific installed build and route; unfinished gates remain unfinished.

## Install one source

Use one enabled PCV source at a time. The **MO2 ZIP is recommended**; it contains the DWPkg payload, so do not separately install the same DWPkg. Disable/remove the old source before switching to another route. Version-specific links below target the 0.1.18 ALPHA release:

- [MO2 ZIP](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.18/private_conversation-0.1.18-mo2.zip) — install and enable as one MO2 mod, keeping `CHIM` directly under the game's data root.
- [DWPkg](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.18/private_conversation-0.1.18.dwpkg) — advanced manual sync route; rename to `0.1.18.dwpkg` and place under `Data/CHIM/server-plugins/private_conversation/`. Do not unpack it.
- [Repository tar](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.18/private_conversation.tar.gz) — advanced CHIM repository/Plugin Manager route; CHIM applies the manifest's `archive_strip_components: 1` to remove its single `private_conversation/` wrapper. Do not manually unpack it into the MO2 install; this is a different consumer from DWPkg sync.
- [SHA256SUMS.txt](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.18/SHA256SUMS.txt) — use the published file to verify downloaded assets. Checksums belong to the built release artifacts and are not guessed in this source note.

For the full install and update guidance, see [README — Install and update](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.18/README.md#install-and-update). Package validation does not establish native MO2 UI behavior, CHIM synchronization, rollback, or an installed-game result.
