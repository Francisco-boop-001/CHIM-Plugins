# Private Conversation 0.1.22 — BETA

Want a pair of NPCs to argue about the harvest while the Dragonborn stays out of it, or one to think aloud to no one? PCV stages the cast and the scene note; CHIM and the model still write the words. The llama has a clipboard, not a subpoena.

## Install and start a scene

This walkthrough uses CHIM Server Plugins, one supported route:

1. In **Server Plugins**, click **Refresh Plugins**, then choose **Install Plugin** on the Private Conversation repository row. For an update, choose **Update** on the installed PCV row and confirm the install.
2. Open the PCV **Plugin Page** and wait for its status and eligible actor list to finish checking.
3. Choose **Pair**, **Group**, **Free scene**, or **Solo reflection**; set who belongs in the current scene and choose optional actions only if you want them offered.
4. Add a one-line **Scene card** of up to 300 characters if useful. It is a setting or mood note, not a replacement for an NPC's own state.
5. Click **Arm or update on next input**, then send one ordinary **Standard** input in Skyrim with a clear direction. ARM stages settings; it does not start dialogue by itself.
6. Click **End now** when finished and wait for the page to confirm the state readback. If it cannot confirm, do not assume the scene is off. End now cannot cancel generation already underway or speech already queued.

The flat [chim-plugin.tar.gz](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.22/chim-plugin.tar.gz) is the Plugin Manager asset. Keep one active PCV installation. The [README's install and recovery steps](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.22/README.md#install-and-update) cover MO2, manual sync, and duplicate recovery too.

## Keep the direction and action list useful

When scene actions are enabled, the Available Actions section now reflects PCV's filtered, rebuilt action list for that scene. Talk-only stays action-free. This keeps the described actions and target scope aligned; it does not promise that a model will choose an action or that CHIM/SHARMAT will execute it. Their own settings and gates still apply.

Keep the Scene card to one line and at most 300 characters. If the form rejects it, the page shows that exact rule and refuses the edit before staging it. A typo is a small problem; a mysteriously saved typo is a small problem wearing a fake moustache.

## Read the status without guessing

The refusal timer remains five minutes of consecutive failed eligibility checks. The request that reaches the threshold is still refused. The page now distinguishes the result of the check that ended the scene: **too few eligible NPCs** reports that the check found too few; **could not verify eligibility** means the check could not establish eligibility. The five minutes count failed checks, not observed physical absence. An absent or stale report can still take the existing ordinary ineligible path; it does not always mean an evidence-read error.

If a scene was already left unreadable by a backward clock change before 0.1.22, use **End now** on the Plugin Page and wait for the page to confirm it is off before arming again. 0.1.22 keeps a new refusal timestamp from preceding scene activation; it does not silently repair an already-saved invalid marker. If the page cannot confirm the end, do not assume it is off.

Pair/Group can include the player or use the player's ordinary Standard input as scene direction. Free and Solo always exclude the player; Solo returns one reflection per eligible input, not an automatic monologue. “Present but silent” is guidance to the model, not a physical privacy barrier. PCV scopes the current request; history, memories, profiles, vanilla greetings, actual room placement, and model behavior can still expose or ignore context.

## What was checked

The [0.1.22 verification record](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.22/tasks/critique-fixes-2026-10-20/verification.md) records focused test-distro PHP fixture RED/GREEN results and syntax checks for the changed paths. These synthetic fixtures are not a fresh full CHIM request, provider run, installation, native playback check, or Skyrim gameplay session.

The earlier 0.1.20 Pair/Solo playback and operator outcomes remain historical evidence in the [acceptance record](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.22/tasks/beta-2026-10-17/acceptance-evidence.md). 0.1.21 was separately verified as runtime-payload-identical to exercised 0.1.20, but was not itself played. 0.1.22 changes runtime code, so that byte-match and gameplay history do not establish 0.1.22 behavior in Skyrim. The [player guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.22/docs/player-guide.md) has the longer operating notes.
