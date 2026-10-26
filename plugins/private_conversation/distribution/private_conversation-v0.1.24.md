# Private Conversation 0.1.24 — BETA

The database sometimes takes the scenic route. PCV now gives its identity and NPC-catalog reads bounded waits; it still cannot make CHIM, the provider, or the llama hurry up. I'm an author, not a database whisperer.

## Install one copy

The CHIM Server Plugins route is the simplest one to maintain. Open **Server Plugins**, click **Refresh Plugins**, then choose **Install Plugin** for the Private Conversation repository row. For an update, use **Update** on that installed row and confirm. Keep one active PCV copy; another installation is a second state owner, not extra reliability. The repository README’s install and recovery section covers MO2 and manual sync as well.

For a manual install, use only the assets for PCV 0.1.24 on the [GitHub releases page](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases). Keep `CHIM` directly under the game’s Data root for MO2, or place the versioned DWPkg at `Data/CHIM/server-plugins/private_conversation/0.1.24.dwpkg` for manual sync. Do not unpack the DWPkg into Data. After syncing, check **Server Plugins → Automatic Game Plugin Sync → Refresh Status** and confirm the server reports **0.1.24**; MO2’s displayed version is separate.

The Department prealpha.18 and prealpha.19 releases target PCV 0.1.23. If you use the Department interface, pair PCV 0.1.24 only with a Department build whose own manifest explicitly names PCV 0.1.24; standalone PCV remains available through its own browser page. Similar controls or shared interface code do not establish compatibility; leave the supported pins alone.

## Set a scene

Open PCV’s **Plugin Page**, wait for the eligibility check, choose a mode and cast, then click **Arm or update on next input**. ARM stages settings; it does not start dialogue. Send one ordinary **Standard** input in Skyrim for the next eligible request.

| Mode | Setup |
| --- | --- |
| **Pair** | Choose two eligible NPCs. Include the player for ordinary in-character speech, or exclude the player to make the input scene direction. |
| **Group** | Choose two to four members. Unavailable members, including A or B, may be dropped; at least two eligible members must remain. |
| **Free scene** | Choose a cap from two to six. PCV selects up to that many eligible nearby NPCs; the player is excluded. |
| **Solo reflection** | Choose one NPC. Each eligible Standard input requests one reflection, not a continuous monologue. The player is excluded. |

Scene cards, turn length, and optional actions guide the current request. They do not promise a model response or action execution. “Present but silent” is model guidance, not a physical privacy barrier. PCV scopes the current request audience; stored history, memories, profiles, vanilla greetings, actual room placement, and model behavior are outside that guarantee.

## When the database is busy

PCV caps database lock waits at **2 seconds** and each SQL statement at **3 seconds**, while retaining any shorter positive limit already configured for those reads. These limits apply to identity and NPC-catalog reads. They are per-operation bounds, not a deadline for the whole request.

If a read fails, PCV keeps the failure distinct from a verified empty profile or NPC roster; it does not turn a database error into “nobody is here” or a successful scene change. Wait for contention to clear, explicitly refresh the Plugin Page, and retry deliberately. The full CHIM NPC catalog is still read: this update does not add a roster-size cap. An unusually busy database can still make a request fail; bounded patience is not magic.

## Ending and integrations

Use **End now** to clear pending and active PCV settings on a successful write. Wait for the page’s readback before assuming the scene is off. It does not stop generation already underway or speech already queued. In ordinary Standard input, `end scene` ends the active or queued scene without sending the phrase to an NPC; with no scene it is ordinary speech.

For Pair, Group, or Free, `wrap up: <how they close>` is a stage direction even when the player is included. PCV validates the direction before ending the scene; an invalid player-prefixed direction is refused without ending it. A valid closing sentinel takes precedence over a valid external SHARMAT listener pin for that response, but it does not end OStim or cancel independent SHARMAT requests. CHIM still owns generation and playback. Solo has no closing turn; `wrap up` ends that reflection immediately.

Mind Poisoning remains optional and owns opinion evaluation and persistence. CHIM core, SHARMAT, and Mind Poisoning are separate components with their own rules; this PCV update does not change them.

## Evidence limits

Focused 0.1.24 source acceptance is recorded in the [verification report](../tasks/read-timeouts-2026-10-26/verification.md): five PHP fixtures, eight syntax checks, the 18/18 PCV PostgreSQL suite, and the 6/6 paired Department overlay suite passed. Four package checks passed and the builder produced five candidate assets. A clean committed-source build and public-asset verification remain pending. No fresh provider request, installation, or Skyrim gameplay is claimed here. Historical 0.1.20 Pair/Solo playback remains evidence about 0.1.20 only; the 0.1.21 payload comparison does not extend to runtime-changing versions.
