# Private Conversation: player guide

*Current release: 0.1.23 BETA · Part of the World of Drama-Llama.*

Private Conversation (PCV) sets the scene boundaries and passes direction into CHIM's next eligible Standard request. CHIM and the model generate the words; PCV cannot make an actor obey a script. The llama has a clipboard, not mind control.

**Who controls what:** PCV manages the pending/active cast and request routing, then narrows optional scene actions. CHIM owns its request pipeline, provider and client playback. The model writes dialogue and receives scene-card/turn-length guidance, which it can ignore. SHARMAT keeps its own action gates. Mind Poisoning is optional and alone owns opinion evaluation and persistence.

## First scene

1. If you need to install or update, use the canonical [README installation and duplicate-recovery instructions](../README.md#install-and-update). They cover one enabled source and what to do when a Plugin Page returns HTTP 409.
2. Open PCV's Plugin Page. Wait for the status and actor list to finish checking eligibility.
3. Choose a mode and its actors. Set whether the player and other NPCs belong in the scene.
4. Click **Arm or update on next input**. This stages the settings; it does not start dialogue.
5. In Skyrim, send one ordinary **Standard** text or speech-to-text input with a clear direction (or speak as your character if you included the player). The next eligible input activates or updates the scene.
6. Use **End now** when you are done. A successful write clears pending and active PCV settings; wait for the page’s state readback before assuming the scene is off. If you forget, your next Standard input follows the player setting: when you are excluded, the robots treat it as scene direction; when included, it is player speech. They are very literal for machines that improvise so much.

## Pick a mode

| Mode | What you select | What PCV does |
| --- | --- | --- |
| **Pair** | Two distinct eligible NPCs, A and B. | Directs a two-person scene. The **Exclude the player** box is on by default for a new pair; uncheck it to participate as Player. |
| **Group** | A and B, plus optional NPC C and D. | Directs two to four selected NPCs. Any unavailable member, including original A or B, can be dropped; at least two eligible members must remain. |
| **Free scene** | No actor pickers; choose **How many (free scene)** from 2 to 6. | At activation, picks up to that many nearest eligible NPCs from CHIM's candidate order, then fixes that roster for the scene. The player is always excluded. The default cap is six. |
| **Solo reflection** | One **Reflecting NPC**. | Requests one think-aloud reflection, addressed to no one, for each eligible Standard input. The player is always excluded. Solo has no rechat or continuation. |

A pair/group player who is included contributes **player speech**. When excluded, your Standard input is **scene direction**, not your character's dialogue. To speak in-character again, include yourself in a pair/group update or end the scene first. Solo and Free do not offer that choice.

## Controls and options

- **Solo reflection** switches between one reflector and pair/group controls. **Free scene (player excluded)** bypasses the selected actor fields; its separate cap control lets you choose 2–6 eligible NPCs.
- **NPC A / NPC B / NPC C / NPC D** choose the pair or group members. C and D are optional. A and B must be different.
- **Who speaks first** is available for pair/group. The earliest selected member named in your direction takes precedence even when you chose a specific opener. If nobody is named, PCV uses the selected opener; with **Auto**, it falls back to NPC A. In Free mode there is no opener picker: PCV uses a named member, then the direct target if that NPC is in the roster, then the nearest member.
- **Other NPCs** has two choices: **Exclude from this conversation** or **Present but silent**. Excluded extras are left out of the current audience. With silent extras, PCV tells the model to use generic scenery and not let them speak, act, or be quoted; only selected members are allowed to take speaking turns. Treat that as model guidance, not proof of physical privacy or guaranteed obedience. If you want an NPC to listen and respond, select them as a group member.
- **Exclude the player** is a pair/group choice. It is forced on in Solo and Free. The current page summary shows the active and queued setting.
- **Scene card (optional)** adds one line of up to 300 characters of setting or mood. If it is invalid, the page explains the limit and rejects the form before staging a new configuration. **Turn length** is Short (one or two sentences), Normal, or Long (up to six); it is available for Pair, Group, and Free, and disabled in Solo. These are model guidance and do not replace an NPC's own state.
- **Scene actions** are optional and unchecked by default. If none is selected, the scene is talk-only. Personal offers Drink, Toast, Consume, TakeASeat, and Relax. Consume targets an inventory item; these function names do not promise a particular drink, food, animation, or item being available. Physical offers Brawl and Surrender (the UI describes the group as a bare-fisted brawl between members). Intimate offers eligible SHARMAT partner-directed and self-directed actions, subject to SHARMAT's own rules.

PCV narrows the functions CHIM/SHARMAT already enabled; it never turns a disabled function back on. Pair and Group can offer eligible partner-directed Intimate actions and self-actions. Solo can offer the five Personal functions and only these three self-directed Intimate functions: put on clothes, remove clothes, and self-directed masturbation. It cannot offer Physical or partner actions. Offered actions still depend on CHIM/SHARMAT settings and model choice; attacks and killing are never offered. Function filters enforce that actions stay within the scene, and solo actions cannot target another person.

When actions are enabled, the model's Available Actions section now uses CHIM's rebuilt list after PCV applies the scene filters. That keeps the described actions and targets within the configured scene; the section stays empty for talk-only. A listed offer still is not a promise that the model will choose it or CHIM/SHARMAT will execute it.

## Presence and the actor list

The list is based on CHIM's background nearby reports intersected with recent AI activity and the NPC catalog. CHIM supplies this background feed; you do not need to send player chat or rechat to populate the roster. The page checks again every 15 seconds while visible and refreshes when you return to the tab. Refresh only rereads the latest report; it cannot create one. Activity evidence expires after 45 seconds, so after a loading screen or long menu the list may need a fresh background report before ARM becomes available again.

CHIM's nearby scan is broader than precise earshot: PCV does not check which side of a door, floor, or room an NPC occupies. Missing, stale, ambiguous, or duplicate-name evidence fails closed. Names that repeat in Skyrim cannot be individually distinguished. PCV never substitutes a random NPC: pairs and solo need their selected actors; a group may drop an unavailable member while at least two eligible members remain.

## Give the model a useful direction

Think of the direction as a stage note, not a screenplay. Name who is speaking, to whom, and about what; give one motive, piece of context, or tension; then leave the reply room to breathe. Use recognizable names. Phrase rumors as rumors and allegations as allegations: CHIM may retain a narrator direction in history, and a model can mistake a confidently worded instruction for an established fact. No amount of llama confidence makes it canon.

Examples below are prompts to try, not guaranteed outcomes:

| Goal | Setup and direction |
| --- | --- |
| Gossip | Pair Aela and Lydia. `Aela tells Lydia she heard Nazeem underpaid a caravan guard. Lydia asks what Aela actually witnessed and weighs the answer.` This sets a disputed claim, not a theft record. |
| Praise | Pair Lydia and Aela. `Lydia tells Aela why Farkas's repair of the west gate mattered. Aela answers with what she respects about his work.` |
| Debate | Pair Aela and Lydia. `They debate whether the Jarl should tax the caravans. Each gives one reason and answers the other's strongest point; neither has to agree or settle it.` |
| Solo | Select Lydia as the **Reflecting NPC**. `Lydia thinks aloud, to no one, about what she actually heard Aela say about Nazeem and whether that evidence changed her view.` A named subject and real prior context help; the direction alone cannot manufacture evidence. |
| Eavesdropping | Select Aela and Lydia, keep **Exclude the player** checked, and direct: `Aela and Lydia discuss the missing ledger quietly over supper.` For roleplay, stand nearby and listen; PCV leaves the player out of the model's current audience but does not verify actual earshot, room, floor, or door placement. |
| Inn atmosphere | Put `The Bannered Mare near closing; rain outside, one quiet table, a tense but civil mood.` in **Scene card**. Direction: `Aela asks Lydia whether the caravan should leave before dawn; Lydia has one practical objection.` |

For a scene that should open with someone specific, name that NPC early or select them in **Who speaks first**. If the player is included, speak in-character; do not write a narrator direction and expect it to be recast as NPC speech. If the player is excluded, say what should happen, not the exact lines you want the NPC to claim they said.

## Solo reflection and Mind Poisoning

PCV enforcement keeps Solo to one selected NPC, excludes the player, and blocks rechat/continuation. Its prompt asks the NPC to think aloud about their own experience, address no one, refer to the subject in the third person, and keep the reflection brief (at most five sentences). Those are model instructions; the request boundaries are enforced, but the model may still ignore some wording.

Opinion effects are optional. PCV works without Mind Poisoning. When a compatible Mind Poisoning API is loaded, PCV can register the accepted Solo reflection output; **Mind Poisoning owns the evaluation and persistence**. In an ordinary pair conversation, Mind Poisoning may evaluate the addressed listener's view of a named subject from what another NPC said; in Solo, the reflector may evaluate a named subject from their own reflection. A Solo opinion subject can be a third NPC from CHIM's catalog; they need not be selected or nearby, but the emitted reflection must mention an unambiguous catalog name or alias Mind Poisoning recognizes. The actual emitted speech must contain a resolvable subject, relevant new evidence must exist, and Mind Poisoning's checks must accept it. An effect can be skipped, rejected, or produce a zero delta. Zero is a valid result; PCV does not force an opinion, affinity, quest, or world outcome. The robots are permitted to say, “I have considered it and changed nothing.”

Scene direction can work without Mind Poisoning. For an opinion that did not change, check PCV's routing/acknowledgement logs and Mind Poisoning's evaluation logs separately; a requested topic is not proof that a line was spoken or an effect committed.

## Ending, wrap-up, and expiry

- **End now** clears pending and active PCV settings on a successful write. Wait for the page’s state readback before assuming the scene is off. It does not cancel generation already underway or speech already queued. Use CHIM's **Stop All Dialogue** if you need to request a playback stop; PCV's source checks do not prove that external control in-game.
- In ordinary Standard input, `end scene` ends an active or queued scene without sending that phrase to an NPC; without a scene, it is ordinary speech. Voice input can use it if speech-to-text transcribes the command.
- If a replacement cannot be verified while a scene is active—including when its strict eligibility map is unavailable—PCV refuses that input, clears the queued replacement, and preserves the existing scene. It does not redirect the refused request to the old or new cast. Send a fresh ordinary Standard input to recheck the existing scene; stage the replacement again if needed. Initial ARM with no active scene stays pending and retryable.
- For Pair, Group, or Free, `wrap up: <how they close>` is a stage direction even when the player is included. PCV validates the direction before it clears the stored scene; an invalid player-prefixed direction, such as one made ambiguous by a colon-bearing identity, is refused without ending the scene. A valid closing reply releases the PCV scene before generation. Its closing sentinel takes precedence over a valid SHARMAT listener pin for this response, but it does not end OStim or cancel independent SHARMAT requests. CHIM still controls generation and playback. Example: `wrap up: They agree to revisit the dispute after the harvest.`
- Solo has no closing turn. `wrap up` ends a pending or active Solo reflection immediately; it does not ask the NPC for one last speech.
- Pending settings expire after 15 minutes. An active scene expires after one hour without a scene turn; a turn renews that timer. After five minutes of consecutive failed eligibility checks, a later request can end the scene; the request that reaches the threshold is still refused. The page describes the latest check; it is not a countdown or a claim that NPCs were physically absent for five minutes.
- If the current playthrough identity is unavailable, a safely readable store containing only expired scenes no longer blocks an input. PCV still fails closed when stored state cannot be read safely, and it does not rewrite or delete scene-state data under an unknown identity.

ARM or an update waits for the next eligible ordinary Standard input. Solo produces one response for that input; it does not start a continuous monologue. Pair/group continuation depends on CHIM's rechat routing and settings. Close and Whisper are stopped while a scene is active; Director mode is outside this feature.

## Troubleshooting and logs

| What you see | What to try |
| --- | --- |
| No selectable NPCs | Keep the page visible for a fresh check, then wait for CHIM's next background report. Check for stale AI activity or duplicate names; reloading alone cannot make a new report. |
| ARM does not speak immediately | Expected. Send an eligible ordinary Standard input and check whether the page says pending or active. |
| Status says “Private conversation is off.” | “Off” means there is no pending or active PCV scene; it is not an install check. If actor pickers or ARM are unavailable, read the eligibility status: an empty roster differs from missing, stale, or unavailable evidence. The page checks every 15 seconds while visible, but refresh only rereads CHIM's report; it cannot create one. AI activity evidence expires after 45 seconds. |
| An actor stops responding | The roster is revalidated. PCV refuses when the required actors are unavailable rather than changing your cast; groups may continue only if at least two eligible members remain. |
| Scene card is rejected | Keep it on one line and at most 300 characters. PCV shows the rule and refuses before staging the invalid edit. |
| Scene ended after five minutes of failed checks | Read the latest-check wording. “Too few eligible NPCs” means that readable check found too few; “could not verify eligibility” means the check could not establish eligibility. The latter is not an empty roster, and neither message says actors were gone throughout the interval. Ordinary missing or stale reports may still follow the existing ineligible path. The request that reaches the threshold is still refused; check status before sending again. |
| A scene became unreadable after a backward clock change | Use **End now** on the Plugin Page and wait for it to confirm off before arming again. The 0.1.22 clock fix keeps a new refusal timestamp from preceding activation; it does not silently rewrite an already-saved invalid marker. |
| Player or bystander appears in context | This is not a privacy barrier. Current audience is scoped, but stored history, memories, and profiles can still mention them; vanilla greetings and model noncompliance remain possible. |
| Solo only answers once | Expected for each input. Solo does not use rechat; send another ordinary Standard input for another reflection. |
| No opinion change | Mind Poisoning is optional. Check that it is loaded with a compatible API, then inspect a relevant PCV acknowledgement and Mind Poisoning evaluation result; zero or a skip is valid. |
| Plugin Page returns HTTP 409 | Follow the [canonical install and duplicate-recovery steps](../README.md#install-and-update); the message names which copy owns the scene state. |

Open **Operational logs** from the Plugin Page for sanitized lifecycle records; the [logging guide](logging-revision-2.md) explains filtered viewing and export. This access rule protects only the diagnostics view and log export; scene controls are unaffected. Direct loopback requests are allowed only without forwarding headers. An existing trusted web server may instead supply `REMOTE_USER` after authentication. A reverse proxy must authenticate the upstream client and set `REMOTE_USER` server-side, or mark proxied traffic so PCV rejects it as direct loopback; never pass a client-supplied `REMOTE_USER` header through as the authenticated server identity. Preserve trustworthy source-address information too: forwarded identity/address headers alone are not authentication. A private default gateway is not caller identity, so PCV no longer grants automatic gateway access. Depending on the WSL and host network setup, a Windows browser may not reach the service through localhost. If localhost cannot reach it and no authenticated route already exists, the server operator must provide authenticated access or inspect the logs locally. PCV does not configure authentication or ship a login page. A useful bug report includes PCV version, CHIM version/revision if known, mode and settings, a short reproduction, expected versus observed behavior, and only the relevant bounded log records. Do not send credentials or a full private dump. PCV logs ordinarily omit raw prompt/dialogue; Mind Poisoning owns its separate opinion details.

## Evidence and limits for 0.1.23

Versions 0.1.22 and 0.1.23 change runtime code. The current [verification record](../tasks/critique-fixes-2026-10-22/verification.md) documents 19 selected PHP fixture files passing in the test distro, 14/14 host-only Node DOM checks, and syntax checks for the five changed PHP files. The receipts preserve original failing outputs and corrected fixture results. These are focused synthetic checks, not a complete CHIM request, provider run, package-content check, fresh installation, or Skyrim gameplay.

The earlier 0.1.20 gameplay evidence remains historical. The publisher verified that 0.1.21 had the same runtime code as exercised 0.1.20: all 20 non-manifest payload members matched byte for byte, and the package check passed 4/4. Only the manifest version and description changed; 0.1.21 itself was not separately played. That comparison does not extend to runtime-changing 0.1.22 or 0.1.23; no 0.1.23 gameplay, installation, or provider run is claimed.

The exercised 0.1.20 run included an ordinary Standard Elsa–Lidia Pair flow. Retained native client traces show all five Pair utterances (two by Elsa and three by Lidia) and all three Solo reflection utterances reaching the `completed` playback stage. That is playback-stage evidence for these outputs, not a guarantee of audible delivery in every setup or a proof of the server-side request join. Separately, dated PCV/Mind Poisoning server review records show an Elsa-to-Bruce opinion change stored and END with an empty active scope. The operator also confirmed direct ordinary Standard input after END, repeated Solo reflections, and removal without harm. No request ID or timestamp was retained to correlate the direct post-END input with a specific route log. See the [acceptance evidence](../tasks/beta-2026-10-17/acceptance-evidence.md) for the bounded records and evidence distinctions.

One separate Pair opinion attempt used a generic “vampire coven” reference, which Mind Poisoning resolved to Vampire Fledgling; no Lidia-to-Bruce change is claimed from that attempt. Mind Poisoning owns opinion evaluation and persistence. SHARMAT keeps its own action gates; this release does not repair an external stale lock. Director and independent requests are outside PCV's ordinary Standard scope. END clears stored PCV scope when its write succeeds, but does not cancel generation or speech already queued.

PCV scopes its current request audience; it does not make stored history, memories, profiles, vanilla greetings, actual room placement, or model behavior private. The [0.1.23 release note](../distribution/private_conversation-v0.1.23.md) describes the BETA controls and limits. The llama is doing its best with a clipboard; please do not ask it to double as a privacy officer.
