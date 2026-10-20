# Private Conversation 0.1.22 — BETA

I once let the Available Actions text keep guest names after PCV had scoped the scene. The filter did its job; my handoff to CHIM did not. 0.1.22 fixes that prompt handoff. The rest is still deliberately simple: choose a cast, arm it, then send an ordinary Standard input. The llama owns the clipboard, not mind control.

## Install and use

This release note walks through CHIM Server Plugins:

1. Open **Server Plugins**, click **Refresh Plugins**, and choose **Install Plugin** on the Private Conversation row. To update, choose **Update** on the installed PCV row and confirm.
2. Open PCV's **Plugin Page** and wait for status and actor eligibility to finish checking.
3. Choose Pair, Group, Free scene, or Solo reflection. Set player and bystander participation, then select optional scene actions if wanted.
4. Add a one-line Scene card of at most 300 characters if it helps.
5. Click **Arm or update on next input**, then send one ordinary **Standard** text or speech-to-text input in Skyrim. ARM queues settings; it does not start dialogue.
6. Use **End now** when finished and wait for confirmed state readback. If the page cannot confirm, do not assume the scene is off. End now does not stop a response already generating or speech already queued.

The Plugin Manager uses the flat [chim-plugin.tar.gz](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.22/chim-plugin.tar.gz). Keep one active copy. The [installation and recovery guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.22/README.md#install-and-update) covers the other supported routes.

When you opt into scene actions, the model's Available Actions section now follows the rebuilt action list after PCV narrows it to the scene. Talk-only remains empty. A filtered action is still an offer, not a promise of selection, item availability, or execution; CHIM and SHARMAT keep their own gates.

Scene cards must be one line and no longer than 300 characters. The page tells you that rule and rejects an invalid edit before staging it, so you can fix the note instead of wondering what the plugin saved. At 300 characters, this is a scene note, not the novella I keep trying to write.

## If a scene refuses or ends

After five minutes of consecutive failed eligibility checks, a later request can end the scene; that request is still refused. The page explains what the latest ending check found: too few eligible NPCs, or an inability to verify eligibility. The interval is failed checks, not proof that NPCs were physically absent throughout. A missing or stale report can still be treated as ordinary ineligibility under the current reader contract.

The first refusal after a backward wall-clock change now keeps its timestamp at or after the scene's activation time, preserving a readable state. If a prior clock change already left a saved invalid refusal marker, use **End now** on the Plugin Page and wait for confirmed off status before arming again. The fix does not rewrite old invalid state for you; the llama has enough administrative duties.

Pair/Group may include the player; when excluded, ordinary player input is scene direction. Free and Solo always exclude the player. Solo gives one reflection per eligible input, not a continuous monologue. Current audience scope is not privacy for stored history, memories, profiles, vanilla greetings, or physical earshot.

## Evidence

The [focused 0.1.22 verification record](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.22/tasks/critique-fixes-2026-10-20/verification.md) reports test-distro PHP fixture RED/GREEN results and syntax checks. It does not claim a fresh installation, full CHIM/provider request, native playback, or Skyrim gameplay. CHIM core, SHARMAT, and Mind Poisoning were not changed.

Historical PCV 0.1.20 Pair/Solo playback and operator outcomes are documented in the [acceptance record](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.22/tasks/beta-2026-10-17/acceptance-evidence.md). The 0.1.21 metadata-only release was verified against 0.1.20's runtime payload; that comparison does not apply to 0.1.22, which changes runtime code. See the [player guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.22/docs/player-guide.md) for details and the [release tag](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.22) for assets and checksums.
