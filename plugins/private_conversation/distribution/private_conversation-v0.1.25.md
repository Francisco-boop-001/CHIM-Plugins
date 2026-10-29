# Private Conversation 0.1.25 — BETA

Pick a cast for a Pair, Group, or Free scene—or let one NPC think aloud—then stage it for CHIM’s next eligible Standard input. CHIM and the model own the response; I brought a clipboard, not a traffic-control tower.

## Install one copy

Use the [PCV 0.1.25 tagged release](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.25) and choose one route:

- **CHIM Server Plugins:** use the repository row’s **Install Plugin** or **Update** action. It downloads the flat [`chim-plugin.tar.gz`](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.25/chim-plugin.tar.gz). Open the generated **Plugin Page** after install.
- **MO2:** install [`private_conversation-0.1.25-mo2.zip`](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.25/private_conversation-0.1.25-mo2.zip) with `CHIM` directly under the game’s Data root. The ZIP carries the versioned DWPkg; do not enable another PCV source alongside it.
- **Advanced manual sync:** put [`private_conversation-0.1.25.dwpkg`](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.25/private_conversation-0.1.25.dwpkg) at `Data/CHIM/server-plugins/private_conversation/0.1.25.dwpkg`. Do not unpack it into Data.
- The [`private_conversation.tar.gz`](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.25/private_conversation.tar.gz) is for the candidate-manifest route only; it is not the flat Plugin Manager asset. [`SHA256SUMS.txt`](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.25/SHA256SUMS.txt) covers the four payload assets.

After MO2 or manual DWPkg sync, start CHIM connected to the game, load a save, then open **Server Plugins → Automatic Game Plugin Sync → Refresh Status** and confirm server version **0.1.25**. MO2’s displayed version is separate. This is a verification instruction, not a claim that this release was freshly installed here.

Keep one active PCV copy. Installing a second copy creates another state owner, not redundancy. The [README installation and duplicate-recovery section](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.25/README.md#install-and-update) explains the supported routes and distinguishes the two HTTP 409 cases.

The Department’s current source pin remains PCV 0.1.24, and its overlapping companion overlay has not been qualified against 0.1.25. This standalone release does not advance that pin or establish a new Department pairing. If you use the Department interface, stay with the PCV version declared by that Department release until its owner publishes a separately verified match. The standalone PCV Plugin Page is the route for using 0.1.25.

## Set a scene

Open the **Plugin Page**, wait for eligibility to finish checking, choose a mode and cast, and click **Arm or update on next input**. ARM stages settings; it does not start dialogue. Send one ordinary **Standard** input in Skyrim for the next eligible request.

| Mode | Setup | What PCV requests |
| --- | --- | --- |
| **Pair** | Choose two eligible NPCs. Include the player for in-character speech, or exclude them to make your input scene direction. | A two-person scene. |
| **Group** | Choose two to four members, including optional C and D. | A group scene; unavailable members may be dropped only while two eligible members remain. |
| **Free** | Choose a cap from two to six. | Up to that many eligible NPCs in CHIM’s candidate order; the player is excluded. |
| **Solo reflection** | Choose one NPC. | One think-aloud response per eligible Standard input; the player is excluded. |

Scene cards, turn length, and optional actions guide the request; they do not make the model obey. “Present but silent” is model guidance, not a physical privacy barrier. PCV scopes the current request audience; it does not erase stored history, memories, profiles, vanilla greetings, actual room placement, or model behavior.

## Ending and action targets

- If Solo is active or queued, `wrap up` immediately attempts to clear PCV state, even when a Pair, Group, or Free scene is currently active. It does not generate a final reflection or activate the queued Solo. If PCV cannot confirm the clear, it stops that input and reports failure. With no active or queued Solo, `wrap up: <how they close>` in Pair, Group, or Free remains a stage direction that can receive a closing turn. An invalid player-prefixed direction is refused. A valid closing sentinel wins over a valid SHARMAT listener pin for that response, but it does not stop OStim or independent SHARMAT requests; CHIM still owns generation and playback.
- If a replacement cannot be verified while a scene is active—including when its strict eligibility map is unavailable—PCV refuses that input, clears the queued replacement, and leaves the current scene in place. It does not redirect the refused input to either cast. Send a fresh ordinary Standard input to recheck the existing scene, then stage the replacement again if needed. An initial ARM with no active scene remains pending and retryable.
- A Plugin Page form rendered for one playthrough cannot write after CHIM switches to another. Stale ARM and **End now** submissions receive HTTP 409 before staging; refresh the page and select the cast again. Same-playthrough polling retains eligible actor/mode drafts, while a detected identity change clears the cast and Solo/Free selections.
- PCV only narrows actions CHIM and SHARMAT already enabled. An included player can remain an action target only if CHIM already offered that player; PCV does not add disabled actions or invent new targets. The model can still decline any listed action.

## Ownership and evidence limits

CHIM owns request generation and playback. SHARMAT keeps its own action gates. Mind Poisoning remains optional and owns opinion evaluation and persistence. This PCV release does not change those components.

The public release tag `private_conversation-v0.1.25` resolves to commit `886a54967db7c6c08725c8bec8854ea3919ea532`. A clean-source build produced five assets byte-identical to the tested candidate, and all five public assets were downloaded anonymously and matched the clean build. The versioned [CHIM-Plugins bridge release](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/private_conversation-v0.1.25) is public too; its five anonymous assets match the canonical clean build, while the hub’s global latest selector remains `private_conversation-v0.1.24`. See the [immutable tagged source](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/tree/886a54967db7c6c08725c8bec8854ea3919ea532) and [publication record](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/main/tasks/release-0.1.25/publication/record.md) for asset receipts. Four focused PHP behavior fixtures and the 16/16 Node UI DOM run passed in the source review; four package checks and an independent inventory passed. The POSIX-guarded broad page fixture could not activate its logger on the review host and is unperformed, not a pass. No fresh provider request, installation, or Skyrim gameplay is claimed. The separate early-ACK capacity candidate remains unconfirmed and unchanged.
