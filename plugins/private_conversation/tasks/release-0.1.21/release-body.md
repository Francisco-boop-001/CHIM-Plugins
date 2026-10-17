Want two NPCs to discuss a subject while the Dragonborn stays out of it, or one NPC to think aloud to no one? PCV stages the cast and scene boundaries for CHIM's next eligible ordinary **Standard** input. CHIM and the model write the words; PCV narrows the current audience and available scene actions. I have spent an unreasonable amount of time teaching a llama to hold a clipboard. It still isn't a bouncer.

## Start a scene

1. Install or update one PCV copy using the [README's installation and recovery steps](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.21/README.md#install-and-update). The currently listed 0.1.21 assets and checksums are there.
2. Open PCV's Plugin Page. Choose **Pair** for two NPCs, **Group** for two to four selected NPCs, **Free scene** for two to six nearby eligible NPCs, or **Solo reflection** for one NPC.
3. Decide whether the player and other NPCs belong in the current scene. “Present but silent” is guidance to the model, not a privacy barrier. Choose only actions you want PCV to offer; CHIM and SHARMAT retain their own gates.
4. Click **Arm or update on next input**. In Skyrim, send one ordinary Standard input with a direction such as `Aela tells Lydia what she saw at the gate; Lydia asks what she knows for certain.` ARM stages the scene; it does not start dialogue by itself.
5. Use **End now** when you are done. In ordinary Standard input, `end scene` ends a pending or active scene. Solo has no closing turn; `wrap up` ends it immediately.

Solo produces one reflection for each eligible Standard input and does not use rechat or continuation. Pair and Group continuation depends on CHIM's rechat routing and settings. Free scene fixes the eligible roster at activation. Close and Whisper are stopped while a scene is active; Director and independent requests are outside this feature.

## What BETA means

In the observed 0.1.20 run, Pair on ordinary Standard input selected Elsa and Lidia and reached native rechat playback. Solo completed three speech segments, and Mind Poisoning stored an Elsa-to-Bruce opinion change. END recorded `ended_in_game` and cleared the active scope. The operator also confirmed direct ordinary Standard input after END, repeated Solo reflections, and removal without harm; the direct post-END input has no retained request ID or timestamp for independent route correlation.

The publisher verified that 0.1.21 uses the same runtime code as exercised 0.1.20; 0.1.21 itself was not separately played in-game. The [acceptance evidence](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.21/tasks/beta-2026-10-17/acceptance-evidence.md) separates log facts from operator-confirmed outcomes and records the package comparison.

## Ending, opinions, and recovery

- **Current audience is not total secrecy.** Stored history, memories, profiles, vanilla greetings, or model noncompliance can still mention the player or bystanders. PCV does not check actual room, floor, door, or physical earshot.
- **The model can ignore stage directions.** PCV enforces request boundaries and filters offered actions, but it does not make an actor follow a script.
- **END is a state change, not a playback stop.** It clears stored PCV settings when the write succeeds. It does not cancel generation already underway or speech already queued; use CHIM's Stop All Dialogue when you need to request a playback stop.
- **SHARMAT keeps its own action gates.** PCV does not change SHARMAT's state or certify that external action locks are clear.
- **Use one active installation.** An old unguarded 0.1.19 copy can remain the owner and cause a guarded 0.1.20 or 0.1.21 copy to defer with HTTP 409. Two unguarded 0.1.19 copies can still collide. Follow the [README's duplicate-recovery steps](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.21/README.md#install-and-update) and use the Plugin Page named as the owner.
- **Mind Poisoning is optional.** It owns opinion evaluation and persistence. PCV cannot promise every topic resolves or produces a nonzero result. In one separate Pair attempt, Mind Poisoning resolved a generic “vampire coven” reference as Vampire Fledgling; no Lidia-to-Bruce opinion change is claimed from that attempt.

The [player guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.21/docs/player-guide.md) has longer examples and troubleshooting. This is BETA evidence for the documented ordinary Standard flows, not stable certification, a privacy guarantee, or proof of unrelated vanilla gameplay behavior.
