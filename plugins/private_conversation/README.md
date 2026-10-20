# Private Conversation

**Part of the World of Drama-Llama.** Pair and group conversations, free scenes, and the occasional NPC thinking aloud—with considerably less Dragonborn-shaped interference.

![Two travelers speaking quietly in an inn, with an emo llama portrait on the hanging banner behind them.](server/assets/private-conversation-scene.png)

Want two NPCs to trade gossip while the Dragonborn stays out of it, or one to think aloud to no one? PCV stages that conversation. I’m one author who has spent a suspicious amount of time making sure the llama’s clipboard stays out of the scene.

**Current release: 0.1.22 BETA.** Private Conversation (PCV) stages a cast and scene boundaries for CHIM’s next eligible ordinary Standard input. CHIM and the model write the response; the llama has a clipboard, not mind control. Start with the quickstart below, or use the [player guide](docs/player-guide.md) for the longer tour.

## Quick start

1. Install or update exactly one active PCV copy. The supported routes and duplicate recovery are in [Install and update](#install-and-update).
2. Open PCV’s **Plugin Page** and wait for the status and eligible actor list to finish checking.
3. For Pair, choose NPC A and B; for Group, choose A and B and optionally C/D. Toggle **Free scene** or **Solo reflection** for those modes. Choose player participation for Pair/Group and how other NPCs are treated. Add a scene card, turn length, or optional actions if useful.
4. Click **Arm or update on next input**. This queues settings; it does not start dialogue.
5. Send one ordinary **Standard** text or speech-to-text input in Skyrim with a clear direction. That next eligible input starts or updates the scene. With the player included in Pair/Group, speak as your character; with the player excluded, your input is scene direction.
6. Click **End now** when finished. If you forget, your next Standard input follows the player setting: included means player speech; excluded means scene direction. Wait for the page to confirm the state readback; if it cannot confirm, do not assume the scene is off. End now does not stop generation already underway or speech already queued.

The button is very literal about “next input.” The robots are excellent at improvising and less excellent at guessing what you meant.

## Choose a mode

| Mode | Set up | What PCV requests |
| --- | --- | --- |
| **Pair** | Choose two distinct eligible NPCs, A and B. | A two-person conversation. For a new pair, **Exclude the player** starts on; uncheck it to participate as Player. |
| **Group** | Choose A and B, plus optional C and D. | A scene with two to four selected members. If an extra member is unavailable when the scene starts, they may be dropped; at least two must remain. |
| **Free scene** | Choose **How many (free scene)** from 2 to 6. | At activation, PCV takes up to that many nearest eligible NPCs in CHIM’s candidate order and fixes the roster. The player is always excluded. |
| **Solo reflection** | Choose one **Reflecting NPC**. | One think-aloud reflection, addressed to no one, per eligible Standard input. Solo always excludes the player and has no rechat or continuation. |

For Pair/Group, **Exclude the player** makes your input scene direction rather than in-character speech. Solo and Free always exclude the player. Pair/group turns and their number depend on CHIM’s rechat routing and settings; PCV keeps the directed scene within its selected members.

### Other NPCs, scene options, and actions

**Other NPCs** controls bystanders. **Exclude from this conversation** leaves them out of the current audience. **Present but silent** asks the model to use generic scenery without letting them speak, act, or be quoted. Silent is model guidance, not a physical privacy switch; select an NPC as a group member if you want them to listen and answer.

An optional **Scene card** adds one line of up to 300 characters of setting or mood in any mode. If it is invalid, the form says so before staging the change; no guessing whether the card quietly went through. **Turn length** is Short (one or two sentences), Normal, or Long (up to six); it is available for Pair, Group, and Free, and disabled in Solo. Both guide the model and do not replace an NPC’s own state.

Scene actions are optional and off by default. Personal offers Drink, Toast, Consume, TakeASeat, and Relax. Consume targets an inventory item; only consuming a real alcoholic item can raise a SHARMAT drunk stage, while Drink and Toast are flavour. Physical offers Brawl and Surrender. Intimate offers eligible SHARMAT actions between scene members under SHARMAT’s rules. CHIM/SHARMAT settings still win; PCV never turns on a function they disabled. Attack and killing are never offered.

Pair and Group can offer eligible partner-directed Intimate actions and self-actions. Solo can offer the five Personal functions and only three self-directed Intimate functions: put on clothes, remove clothes, and self-directed masturbation. Solo cannot offer Physical or partner actions, and solo actions cannot target another person. Function filters keep actions within the scene; a function name does not promise a particular item, animation, or action will be available.

When you enable scene actions, the prompt’s Available Actions section now uses the rebuilt list after PCV applies the scene’s action and target filters. Talk-only remains empty. A listed offer still does not promise an item, animation, dispatch, or model choice.

The actor list combines CHIM background nearby reports, recent AI activity, and its NPC catalog. CHIM supplies the feed; you do not need to send player chat or rechat to populate the roster. The page checks again every 15 seconds while visible and refreshes when you return to the tab. Refresh rereads the latest report; it cannot create a new one. Activity evidence expires after 45 seconds, so after a loading screen or long menu you may need to wait for a fresh background report. “Nearby” is broader than precise earshot: PCV does not check which side of a door, floor, or room an actor occupies. Missing, stale, ambiguous, or duplicate-name evidence fails closed instead of substituting a random NPC.

## Directions to try

Think stage note, not screenplay: name the speaker, listener, topic, and one useful tension or piece of context, then leave room for a reply. These examples describe the requested scene shape, not guaranteed transcripts. The llama has yet to negotiate a contract with the language model.

| Goal | Setup and direction | Intended shape |
| --- | --- | --- |
| **Gossip** | Pair Aela and Lydia. “Aela tells Lydia she heard a caravan guard say Nazeem underpaid them. Lydia asks what Aela actually witnessed and weighs the answer.” | A disputed claim stays a claim; the direction is not a theft record or proof that anyone said it. |
| **Listen in** | Pair Aela and Lydia, with **Exclude the player** checked. “Aela and Lydia compare what they heard about the caravan guard without addressing the player.” | The player is outside the scene audience and can roleplay listening nearby; PCV does not verify actual earshot, room, floor, or door placement. |
| **Debate** | Pair Aela and Lydia. “They debate whether the Jarl should tax the caravans. Each gives one reason and answers the other’s strongest point; neither has to agree or settle it.” | A back-and-forth if CHIM’s rechat settings and the model cooperate. |
| **Praise** | Pair Lydia and Aela. “Lydia tells Aela why Farkas’s repair of the west gate mattered. Aela answers with what she respects about his work.” | A prompt for a focused exchange, not a guarantee of agreement or a new relationship value. |
| **Solo** | Select Lydia as **Reflecting NPC**. “Lydia thinks aloud, to no one, about what she actually heard Aela say about Nazeem and whether that evidence changed her view.” | One reflection response for this input; the direction cannot manufacture past evidence or force an opinion change. |
| **Atmosphere** | Put “The Bannered Mare near closing; rain outside, one quiet table, a tense but civil mood.” in **Scene card**. Then ask Aela to raise a practical question with Lydia. | A setting note added to the prompt; models may ignore it, especially when busy being models. |

Phrase rumors as rumors and allegations as allegations. CHIM may retain a narrator direction in history, and a model can mistake confident wording for established fact. Llama confidence is not a source citation.

## Ending, wrap-up, and expiry

- **End now** clears pending and active PCV settings after the page confirms the state readback. If it cannot confirm, do not assume the scene is off. It does not cancel generation already underway or speech already queued. CHIM’s **Stop All Dialogue** can request a playback stop; PCV does not prove that external control in-game.
- In ordinary Standard input, **`end scene`** ends an active or queued scene without sending that phrase to an NPC. With no scene running, it is ordinary speech. Voice input works only if speech-to-text transcribes the command.
- In Pair, Group, or Free, **`wrap up: <how they close>`** requests one closing turn and releases the stored scene before generation. If PCV cannot confirm the clear, it blocks the request. CHIM still controls generation and playback.
- Solo has no closing turn. **`wrap up`** immediately ends a pending or active reflection.
- Pending settings expire after 15 minutes. An active scene expires after one hour without a scene turn; a turn renews the timer. After five minutes of consecutive failed eligibility checks, a later request can end the scene; the request that reaches the threshold is still refused. The page explains whether the latest check found too few eligible NPCs or could not verify eligibility; that is not a report of physical absence for five minutes.
- Close and Whisper inputs are stopped while a scene is active. Director mode is outside this feature.

## Install and update

PCV 0.1.22 is a BETA release. For CHIM Plugin Manager discovery, GitHub release metadata uses `draft=false` and `prerelease=false`; those technical flags enable discovery and do not set or certify the maturity label.

Still updating from an old 0.1.4 or 0.1.5 CHIM-Plugins manifest? Those update buttons use literal old-hub asset URLs and cannot move themselves to this repository. See the historical [0.1.4 release](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/private_conversation-v0.1.4) or [0.1.5 release](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/private_conversation-v0.1.5), then use one of the current routes below.

### CHIM Server Plugins

1. Open **Server Plugins**, click **Refresh Plugins**, then choose **Install Plugin** on the Private Conversation repository row.
2. For an update, use **Update <channel label>** on the installed row, confirm **Install Plugin** or **Update Plugin**, then choose **Back to Plugin Manager**.
3. Open **Plugin Page** on the PCV row. Use this generated link: it follows the resolved install folder.

The installer uses the flat [chim-plugin.tar.gz](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.22/chim-plugin.tar.gz) asset. Fresh discovery installs normally use `ext/CHIM-PrivateConversation`; an update can retain an existing `ext/private_conversation` folder. MO2 is not needed for this server-plugin route.

### MO2 and manual downloads

For MO2, install and enable the [0.1.22 MO2 ZIP](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.22/private_conversation-0.1.22-mo2.zip) as the one active PCV source, with `CHIM` directly under the game’s Data root. The ZIP already contains `CHIM/server-plugins/private_conversation/0.1.22.dwpkg`; a separate DWPkg download is unnecessary. Its root `meta.ini` includes `validated=true`, an MO2 metadata override—not proof CHIM sees or syncs the package. MO2 may overwrite the displayed version during import; the server version is checked separately below.

| Download | Use |
| --- | --- |
| [DWPkg](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.22/private_conversation-0.1.22.dwpkg) | Advanced manual sync: rename to `0.1.22.dwpkg` and place at `Data/CHIM/server-plugins/private_conversation/0.1.22.dwpkg`. Do not unpack into Data. |
| [Legacy repository TAR](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.22/private_conversation.tar.gz) | Candidate-manifest route only; CHIM removes its `private_conversation/` wrapper using `archive_strip_components: 1`. This is not the flat Plugin Manager asset. |
| [SHA256SUMS.txt](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.22/SHA256SUMS.txt) | Optional release-asset checksum verification. |

For MO2/DWPkg sync, start CHIM connected to the game and load a save to trigger sync. Then open **Server Plugins → Automatic Game Plugin Sync → Refresh Status** and confirm the server version is **0.1.22**. The client `SERVER_PLUGIN_SYNC` log is another check; MO2’s displayed version is separate.

### Duplicate-copy recovery

Keep exactly one active PCV source. If the Plugin Page returns **HTTP 409**, read the message to identify the physical owner and open that copy’s Plugin Page. If a scene is active, click **End now on that owner page before switching or removing the duplicate**. PCV state is tied to its installation path; it is not automatically migrated between copies.

An unguarded 0.1.19 copy can remain preferred and make a newer guarded copy defer safely with HTTP 409. Two unguarded 0.1.19 copies can still collide even when a guarded 0.1.20, 0.1.21, or 0.1.22 copy is present. Upgrade/replace or remove the extra old copy until one source remains. The 0.1.21 runtime matched 0.1.20; 0.1.22 changes runtime code, so that old byte-match claim does not extend to this release. Installing another copy does not solve two old copies.

Before switching installation routes, back up the server and quiesce old requests. Disabling a game-carried package stops discovery but does not uninstall its server copy; remove it through the server route too. Removing PCV or Mind Poisoning does not undo opinion values already stored by Mind Poisoning.

## Optional Mind Poisoning integration

PCV’s scene direction and routing work without Mind Poisoning. Install Mind Poisoning separately using the instructions in the [Mind Poisoning repository](https://github.com/Francisco-boop-001/CHIM-MindPoisoning). The optional integration checks the loaded API capabilities: reflection API v1 supports evaluation, while reply API v2 enables full-reply handling and v1 remains the fallback. These are source/API compatibility details, not a hard Mind Poisoning version requirement for PCV scenes. Mind Poisoning owns evaluation and persistence. In Pair, Mind Poisoning may assess B’s view of a named subject C from what A says to B. In Solo, an opinion subject may be a third catalog NPC who was not selected or nearby; Mind Poisoning must recognize an unambiguous catalog name or alias in the emitted reflection and accept its evidence. A requested topic does not force an opinion, affinity, quest, or world outcome. Zero change is a valid result. Check PCV routing/acknowledgement records and Mind Poisoning evaluation records separately.

## Logs and troubleshooting

Open **Operational logs** from the Plugin Page for sanitized lifecycle records. PCV logs ordinarily omit raw prompts and dialogue; bounded NPC IDs and exception metadata may appear.

| What you see | What to try |
| --- | --- |
| No selectable NPCs | Keep the page visible for a fresh check, then wait for CHIM’s next background report. Check stale activity or duplicate names; reloading alone cannot create a report. |
| ARM does not speak immediately | Expected. Send the next eligible ordinary Standard input and check whether the page says pending or active. |
| An actor stops responding | PCV revalidates the roster. It refuses when required actors are unavailable rather than changing the cast; a group may continue only if at least two eligible members remain. |
| Scene card is rejected | Keep it on one line and at most 300 characters. The invalid form is rejected before staging, with the requirement shown in the page. |
| Scene auto-ended after failed eligibility checks | Read the latest-check explanation. “Too few eligible NPCs” reports that check’s readable result; “could not verify” means the check could not establish eligibility. Neither means NPCs were observed absent for the whole five-minute interval. The request that reaches the threshold is still refused; send a fresh input only after checking the page status. |
| A scene became unreadable after a backward system-clock change | Use **End now** from the Plugin Page and wait for the off/readback confirmation before arming again. 0.1.22 keeps a new refusal timestamp from going earlier than activation; it does not repair an already-saved invalid refusal marker automatically. |
| Player or bystander appears in context | This is not a privacy barrier. Stored history, memories, and profiles can still mention them; vanilla greetings and model noncompliance remain possible. |
| Solo only answers once | Expected. Send another ordinary Standard input for another reflection. |
| No opinion change | Mind Poisoning is optional. Confirm a compatible API is loaded, then inspect a relevant PCV acknowledgement and Mind Poisoning evaluation result. A skip or zero delta is valid. |
| Plugin Page returns HTTP 409 | Follow [Duplicate-copy recovery](#duplicate-copy-recovery); the message names which copy owns the scene state. |

Open **Operational logs** for filtered viewing and JSONL export. Operators can also run the CLI reader as the PHP worker’s effective user from the resolved PCV `server/` directory:

```sh
php diagnostics.php --limit 100
php diagnostics.php --request REQUEST_ID --config CONFIG_UUID --limit 100
php diagnostics.php --config CONFIG_UUID --jsonl > /restricted/path/private-conversation.jsonl
```

The CLI reader rejects HTTP; the protected Logs page and CLI share the sanitized reader. It accepts 1–1000 matching records and scans at most five rotated 10 MiB files. Missing or capped logs do not prove nothing happened. The default temporary log root can be cleaned by the OS. For retention, set `PCV_LOG_DIR` in the trusted worker environment to an absolute private path outside the webroot, owned by the worker with mode `0700`; the reader needs the same identity and environment. `PCV_LOG_DEBUG_UNTIL` is an administrator-set Unix timestamp no more than one hour ahead, not a web toggle. Under lock contention the writer can fall back to PHP’s `error_log`; that fallback is best-effort, keeps health degraded, and is not shown by the Logs reader.

The page uses CSRF protection, **not a login system**. Keep CHIM behind an independently trusted access boundary.

## How it works and what each plugin owns

The short version:

**CHIM background roster → PCV staged scene → next eligible Standard input → PCV validates and scopes audience, direction, and optional actions → CHIM/model generates the response → optional exact solo-output registration and matching speech ACK → Mind Poisoning evaluation and persistence.**

PCV owns scene membership, state, audience boundaries, action filtering, and optional exact-output registration. CHIM owns its request pipeline, generation, rechat, TTS, and client playback. SHARMAT’s own consent/safety rules and CHIM’s action switches remain authoritative. Mind Poisoning owns opinion evaluation and persistence. PCV is a server-side CHIM extension; it adds no Papyrus companion, ESP/ESL, load-order slot, daemon, or CHIM core patch.

Compatibility was inspected against CHIM server revision `cf5030f15781637498be86debe26fcf102f5690d` and native source revision `12e035d0a810b9b932fe2df1f688407a72cd27a1`; those references do not prove your installed server or DLL matches.

For deeper operating and technical details, see the [player guide](docs/player-guide.md), [logging notes](docs/logging.md), [operator acceptance and evidence limits](docs/operator-acceptance.md), [background presence notes](docs/background-presence.md), and [live-server testing procedure](docs/live-server-testing.md).

## State, access, and limits

The default runtime state lives in a private per-install temporary root, separate from log placement, and the OS may clean it. Its location depends on the PHP worker’s temp namespace, UID, and canonical extension path. On first state resolution, a valid legacy extension-local `server/state/` may move whole to the private target by atomic rename when that target is absent and both locations are on the same filesystem. A conflict, unsafe/corrupt file, or cross-filesystem failure leaves the legacy source in place; PCV does not merge, partially copy, or reset it. A same-UID CLI process may resolve a different store from the HTTP worker. Do not copy or reset live state.

The extension-root `.htaccess` protects only legacy extension-local state when Apache honors overrides. If overrides are ignored, the package is not an HTTP access boundary; another server needs an equivalent server-level rule. The denial was checked in isolated Apache fixtures, not against your installed server. See [operator acceptance](docs/operator-acceptance.md) before changing routes or handling live state.

PCV 0.1.22 changes runtime code. Its [focused verification record](tasks/critique-fixes-2026-10-20/verification.md) reports test-distro PHP fixture RED/GREEN results and syntax checks for action scoping, card validation, refusal-clock handling, and the latest eligibility-check explanation. These fixtures use synthetic inputs; they are not a fresh full CHIM request, provider run, installation check, or Skyrim session.

Earlier gameplay evidence remains historical. The exercised 0.1.20 Elsa–Lidia Pair flow has retained native client logs showing playback completion for all five utterances (two by Elsa and three by Lidia); all three Solo reflection utterances also reached playback completion. Dated PCV/Mind Poisoning server review records separately show an Elsa-to-Bruce opinion change stored and END with an empty active scope. Direct post-END recovery, repeated Solo use, and removal without harm were operator-confirmed. The publisher verified 0.1.21’s 20 non-manifest payload members match 0.1.20; 0.1.21 was not separately played. That metadata-only comparison does not apply to 0.1.22. See the [historical acceptance evidence](tasks/beta-2026-10-17/acceptance-evidence.md) and the [0.1.22 player guide](docs/player-guide.md). PCV remains a scoped current-request feature, not a privacy force field: history and memories can still mention excluded people, models can ignore guidance, and vanilla earshot is outside its checks.

## Release notes and support

The [0.1.22 release note](distribution/private_conversation-v0.1.22.md) holds current beta guidance; [0.1.21](distribution/private_conversation-v0.1.21.md) and the [0.1.20 ALPHA note](distribution/private_conversation-v0.1.20.md) preserve earlier release records. Recent history: [0.1.19](distribution/private_conversation-v0.1.19.md) and [0.1.18](distribution/private_conversation-v0.1.18.md). The [GitHub releases page](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases) and [distribution folder](distribution/) keep the older release archaeology, so this page can be about using the plugin.

[Report issues](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/issues) with PCV/CHIM versions, mode and settings, a short reproduction, expected versus observed behavior, and only relevant bounded logs. Omit credentials and full private dumps. “The llama looked suspicious” is delightful atmosphere and a poor bug report.

Maintained by [Francisco](https://github.com/Francisco-boop-001), for [CHIM by Dwemer Dynamics](https://github.com/Dwemer-Dynamics/CHIM). Its authors are not responsible for the llama’s haircut.
