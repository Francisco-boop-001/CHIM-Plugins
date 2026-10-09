# Private Conversation

**Part of the World of Drama-Llama.** Two NPCs, one conversation, and considerably less Dragonborn-shaped interference. Or one NPC thinking aloud, because apparently Skyrim also needed unsolicited introspection.

![Two travelers speaking quietly in an inn, with an emo llama portrait on the hanging banner behind them.](server/assets/private-conversation-scene.png)

**Candidate: 0.1.17 — PRE-ALPHA.** This CHIM server extension directs ordinary Standard-mode scenes and can register exact solo-reflection output for compatible Mind Poisoning. Protected logging revision 2 shipped in 0.1.5; 0.1.6 added the versioned Mind Poisoning API check and early-ACK diagnostics; 0.1.7 added bounded early-ACK recovery and routing, expiry, state-protection and diagnostic fixes; 0.1.8 added an unclaimed-registration lease, private runtime-state migration and bounded logger-contention handling. 0.1.9 was the first release tested against a live CHIM server (a disposable clone with a real player database and a simulated game client); **0.1.10 fixes what the first in-game session found** that the simulated client could not. A handsome llama is not a QA department, but it now has a test server and a player.

**0.1.7 baseline:** managed scene routing is narrower, END clears active and pending settings immediately, packaged Apache rules deny HTTP reads of runtime state, and negative unmatched-ACK checks avoid unnecessary fresh identity work. Early-ACK recovery preserves the original receipt time and rechecks active scope and interaction epoch under the ledger lock before reuse or mutation. See the [maintenance review](tasks/maintenance-2026-10-01/final-review.md), [early-ACK review](tasks/ack-handoff-fix-2026-10-01/final-review.md) and [expiry/stale-writer review](tasks/ack-bug-fix-2026-10-01/final-review.md). These reviews describe isolated source behavior, not a deployed CHIM or Skyrim guarantee.

**0.1.8 additions:** a distinct output may supersede an unclaimed registration after 60 seconds from its original timestamp; duplicate registration does not renew that lease, while exact direct-ACK and claimed-effect protection remains up to 600 seconds. Runtime state now defaults to a validated private per-install temporary root and migrates a valid legacy state directory on first resolution by whole-directory atomic rename. Logging retries lock acquisition within one cumulative 100 ms request budget, then attempts each sanitized bounded event line in PHP's `error_log`; health stays degraded and fallback remains best effort, outside the Logs reader and without a total-I/O or no-loss guarantee.

**0.1.17 packaging only:** the MO2 ZIP adds a root `meta.ini` with `version=0.1.17` and `validated=true`. No PCV runtime code changed; the 0.1.16 release assets remain intact. This MO2 flag is metadata, not proof of a warning-free install or successful CHIM sync.

**0.1.16 changes: actions inside scenes (opt-in).** Before, every scene was talk only. Now three boxes on the page let a scene act, not only talk. All are off by default, and END turns them off again, so a new scene never inherits them by surprise.

- **Personal:** drink, toast, eat or drink from inventory, take a seat, relax. A reflecting NPC can now actually drink alone. Only drinking a real alcoholic item from inventory raises a SHARMAT drunk stage; "Drink" and "Toast" are flavour.
- **Physical:** a bare-fisted brawl between scene members, or surrender. Not for solo. Attack and killing are never allowed in scenes, ticked or not.
- **Intimate:** SHARMAT's intimacy actions (hug, hold hands, kiss, undress, sex and its variants, massage, accept or refuse) between scene members, or self-directed moments in solo. SHARMAT's own consent and safety rules still decide what each NPC may do. PCV only narrows them and never turns on anything SHARMAT turned off. SHARMAT's role actions (prostitution, worship, freedom) stay out.
- **Members only.** Every action must stay inside the scene. A married couple with the player excluded can be tender with each other, and nobody, the player included, can be drawn in. A brawl can't target the player or a bystander. An action aimed outside the scene is dropped after the reply and logged as `routing.action_dropped`. If the player is included in the scene, the player counts as a member.
- **CHIM's own switch wins.** If actions are turned off in your CHIM settings, ticking a box does not turn them on.
**0.1.15 changes: fixes from the first in-game session** ([evidence](tasks/live-issues-2026-10-04.md)).

- **Scenes are no longer cancelled by CHIM's background chatter.** While a scene with *excluded* bystanders is running, CHIM's idle ("bored") and background narration events wait. Before, a background narration arriving while your direction waited its turn could make CHIM cancel the scene's reply, and its narration could pull bystanders (Aelva!) into the scene. Background life resumes when the scene ends. With *present but silent* bystanders, background narration keeps running, but it can no longer cancel the scene's reply while it waits.
- **Phrases work by voice.** "Wrap up, they part ways", "Wrap up." and "End-scene." are recognised as well as the typed forms.
- **"wrap up:" during solo ends the reflection** instead of becoming its next topic. "wrap up: end scene" is just an end.
- **Quieter reflection logs.** Lines acknowledged while a long reflection is still being spoken are logged as "reply in progress", even if an older reflection is still on record.
**0.1.14 changes: roleplay tools.** This release also includes the 0.1.13 reliability fixes below; 0.1.13 was never published on its own.

- **Say "end scene" in game.** Typing or saying exactly "end scene" (or "end the scene") ends the scene and anything queued, like END on the page. That line is not sent to any NPC. With no scene running it is ordinary speech.
- **Wrap it up.** "wrap up: they part ways at the gate" makes the next reply a closing one: one NPC gives parting words, nobody answers, and the scene ends. (One parting reply for now, not a two-sided goodbye.)
- **Turns spread around the group.** In scenes of three or more, each speaker can only address members who have not spoken yet this round. CHIM still decides *how many* follow-up turns happen; PCV now decides *who* gets them. To do this, PCV turns off CHIM's **Strict Rechat Targeting** for those follow-up turns only. That setting otherwise forces every reply back to the previous speaker, which is why groups used to ping-pong between two NPCs. Pairs and everything outside PCV scenes keep your setting.
- **Scene card.** An optional line of up to 300 characters on the page ("Late night at the Bannered Mare. Tense. The treaty is on the table."). It frames every scene turn, follow-ups included. It is staging, not dialogue. Honest note: in testing the card reached every prompt, but a model busy with a long character profile (and a drunk stage) often ignored it.
- **Turn length.** Short (one or two sentences), Normal, or Long (up to six). Models follow this only partly.
- **Free-scene size.** Pick how many of the nearest NPCs join a free scene, from 2 to 6.
- **SHARMAT-compatible.**
  - Everything PCV adds sits beside each NPC's own state and never replaces it. A SHARMAT drunk stage, for example, still decides how a drunk NPC talks.
  - During a SHARMAT NPC-to-NPC intimate scene, PCV honours SHARMAT's choice of who the speaker addresses, when that partner is a scene member.
  - In 0.1.14 and 0.1.15 scenes disabled actions entirely, so nobody took a new drink mid-scene. 0.1.16 makes actions opt-in (see above). Anyone already drunk stays drunk.
**0.1.13 changes: reliability.** No new features; fixes from a code audit after 0.1.12 ([audit](tasks/audit-2026-10-03.md)).

- **Long scenes no longer switch off at the one-hour mark.** The hour now counts from the last scene turn. Before, a scene expired one hour after it started, and your next direction went out as plain player speech.
- **END fixes a damaged state file.** A corrupt or unreadable state file used to block every input, and END could not clear it. END now sets the bad file aside (kept for diagnosis) and starts clean. ARM still refuses to overwrite it.
- **A brief database hiccup no longer silences CHIM.** Once the plugin had been used, any identity lookup failure blocked all input, even with no scene. Now it only blocks when a scene is actually armed or running.
- **Stuck scenes end themselves.** If every request is refused for five minutes because the NPCs are gone, the scene ends and the page tells you why.
- **Read errors never remove members.** A presence file that cannot be read now refuses that one turn and is logged, instead of quietly dropping a member as "left the scene".
- **Reflections survive a small drift.** A solo NPC who wanders a little during a long reply gets the same 60-second grace as routing; during a heartbeat gap the check stays strict.
- **Honest logs.** A refused direction is a warning (`routing.request_blocked`), not an informational skip. A failed reflection-scope read is `scope_unavailable`, not `identity_changed`. A new test checks every log call against the schema.

**0.1.12 changes: free scenes.** Also requested on the CHIM Discord: "just exclude the player".

- **Tick Free scene, ARM, talk.** There are no pickers. On your next ordinary input, the **nearest six** close and AI-active NPCs become the scene, nearest first. Fewer than two nearby, and it waits.
- **The player is always out.** That is the point of the mode. "Other NPCs" (exclude or silent) applies to everyone beyond the six.
- **Who opens:**
  - the member you name first ("Aela, tell them about the hunt");
  - otherwise the NPC you are facing, if they made the cut;
  - otherwise the nearest member.
- **Fixed cast.** After it starts, a free scene is an ordinary group scene. The addressed member answers, members who wander off are dropped while two remain, and latecomers do not join.
- The page shows "Current free scene: …", or "Next: free scene (nearest six)." while it waits.

**0.1.11 changes: group scenes.** Requested on the CHIM Discord ([requests](tasks/feature-requests.md)).

- **Two to four NPCs.** Pick NPC A and B, plus optional NPC C and D. Pair mode is simply a group of two.
- **Auto opener.** The member named earliest in your direction speaks first: "What does Bruce reply?" opens with Bruce. A first name counts when no other member shares it. If nobody is named, the **Who speaks first** picker decides, else NPC A.
- **The addressed member answers.** Each line names one other member as its listener, and CHIM's rechat then gives that member the next turn. Only members can speak, and everyone else stays out.
- **Whoever is there starts.** If a checked NPC is not nearby when the scene starts, it starts with the others (at least two). The page says "Started without X (not nearby)", and they do not join later.
- **Wanderers are dropped, not fatal.** A member out of range beyond the 0.1.10 grace and wide checks leaves the scene ("X left the scene") while two remain.
- **Gossip.** Each line's witness list is exactly the members (plus you, if included). Mind Poisoning judges the addressed listener today; overheard gossip arrives when Mind Poisoning supports judging every witness.
- **Compatibility.** Pair scenes armed or active before the update keep working.

Free scenes followed in 0.1.12 (above).

**0.1.10 changes** (from the first in-game session; [evidence](tasks/live-issues-2026-10-03.md)):

- **Partners may wander mid-scene.** In game, NPCs walk off between turns, and CHIM's close-range report then drops them, so 0.1.9 refused the partner's rechat and the scene went silent. Inside an **already active** scene a participant now still counts when named in the current close report (even the first report after a long gap), seen in it within the last **60 seconds**, or listed once in CHIM's wider "beings in range" report. Starting a scene still needs everyone close and AI-active. A refusal logs which check failed (`presence_check`) and how many were missing.
- **Solo reflection is addressed to no one.** With the subject standing nearby, 0.1.9 reflections were spoken *to* them. The direction now says to think aloud, not address anyone present, and refer to the person in the third person. Models may still slip.
- **The Logs page works from your normal browser.** Opening CHIM through the WSL address (`http://172.x.x.x:8081/...`) was refused. The Windows PC hosting the server is now trusted (exactly that one address); anything else is refused with a link to the `127.0.0.1` address.
- **Silent turns are explainable.** CHIM ends a rechat chain without speech once its rechat budget is used (`Rechat: pre-roll budget exhausted`, invisible to plugins). Silent finishes now record the request type, and the page shows the active scene's **last scene turn** (spoke / ended without speech).
- **Quieter logs.** The real client acknowledges early lines while CHIM is still writing the reply; those now log as `reply_in_progress` (debug) instead of a misleading `registration_missing`.

Group scenes followed in 0.1.11 (above).

**0.1.9 changes.** Live-server runs — a disposable clone of a real CHIM server and player database, driven by a simulated game client ([procedure](docs/live-server-testing.md)) — found defects that isolated fixtures missed. Fixed:

- **Solo opinion effects never fired in 0.1.7–0.1.8.** Stored scenes hold catalog IDs, but the ACK precheck compared the ACK speaker's *name* with that ID, so every solo ACK was silently dropped. ACKs now bind the scene by ID and the speaker by the live-resolved name, and a rejected reflection line logs its reason.
- **Eligibility flickered.** A read in the same second as a heartbeat dropped every NPC (about one check in ten: ARM, rechat, solo checks).
- **Busy places had no eligible NPCs.** 22.9% of 13,224 real heartbeats listed more than 33 names and were discarded. Up to 128 actors are now accepted; duplicate names stay ambiguous.
- **END could swallow the next reflection.** Ending a solo scene mid-reply left its unfinished registration holding the slot for 60 seconds, so a new solo scene's reflection was silently lost. A registration from an ended or re-armed scene can never be evaluated, so it now yields at once.

Added:

- **Whole-reply reflections.** CHIM splits a reply into lines of about 125 characters; earlier versions showed Mind Poisoning only the last one, so "Bruce Wayne… he… him…" evaluated as a reflection about nobody. With Mind Poisoning 0.1.16 (reply API v2) PCV registers every line of the reply (up to 24), proven from the request's own database rows; when grouping cannot be proven it falls back to the final line.
- **Shorter solo replies.** The solo direction asks for at most five sentences naming the person reflected on. Models treat this as a suggestion; live replies still ran 1–15 lines.

Removed: the legacy `ext_pcv_presence` companion command. Routine presence is now logged only when it changes. The [operator checklist](docs/operator-acceptance.md) was run on the cloned server with a simulated client; the real game client, audio and gameplay feel are not covered by that run.

## Start here

- [Install and update](#install-and-update)
- [Direct your first scene](#direct-your-first-scene)
- [Get the result you actually want](#get-the-result-you-actually-want)
- [What is removed from the prompt](#what-is-removed-from-the-prompt)
- [Solo reflection and Mind Poisoning](#solo-reflection-and-mind-poisoning)
- [Automatic character selection](#automatic-character-selection)
- [Logs and troubleshooting](#logs-and-troubleshooting)
- [How it is coded](#how-it-is-coded)
- [Limits and verification](#limits-and-verification)

## What it does

Choose **two distinct NPCs** for a conversation or **one NPC** for thinking aloud. Stage the selection on the plugin page, then send an ordinary Standard text or speech-to-text input in Skyrim. The plugin validates the selection, chooses the first responder, scopes the current audience and supplies scene instructions to CHIM.

| Setting | Actual behavior |
| --- | --- |
| Group (2–4) | The auto or picked opener generates the opening response; each line addresses another member, who answers through CHIM rechat. Turns stay inside the members. CHIM's rechat settings determine how many turns follow. |
| Free scene | The nearest six close, AI-active NPCs at activation become a fixed group (at least two). The opener is the earliest-named member, else the NPC you face if a member, else the nearest. The player is always excluded. |
| Solo reflection | A thinks aloud for one generated response. B is disabled, the player excluded and rechat/continuation blocked. A response may contain multiple sentences/audio chunks. |
| Exclude the player | Input becomes an unattributed `instruction` event; guidance tells the model not to address, include, quote or narrate the player. |
| Include the player | Pair mode retains input as player speech. A and B remain the selected generated speakers. |
| Exclude bystanders | Removes their explicit current presence from the scoped audience/nearby context. |
| Bystanders present but silent | Adds generic silent scenery; it does not retain named bystanders or suppress vanilla Skyrim greetings. |

This controls the inspected Standard pipeline. It is not an acoustic simulation, a physical privacy barrier or a guarantee that an LLM will develop manners.

## Install and update

Requires a working CHIM/HerikaServer with the supported request, prompt and response hooks. Compatibility was inspected against server `cf5030f15781637498be86debe26fcf102f5690d` and native source `12e035d0a810b9b932fe2df1f688407a72cd27a1`; these references do not prove your installed DLL matches. Test with an isolated CHIM server/database. A disposable Skyrim save does not isolate server data.

Use the version-specific [0.1.17 candidate release](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.17).

**Recommended for MO2:** download the [MO2 ZIP](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.17/private_conversation-0.1.17-mo2.zip). It already contains `CHIM/server-plugins/private_conversation/0.1.17.dwpkg`; you do not need to download the DWPkg separately. Install and enable the ZIP as an ordinary MO2 mod, keeping `CHIM` directly under the game's data root.

The 0.1.17 ZIP includes a minimal root `meta.ini` (`[General]`, `version=0.1.17`, `validated=true`) beside its DWPkg. `validated=true` asks MO2 to suppress the invalid-content flag for this intentional CHIM-only payload; it is an MO2 metadata override, not game data or proof that CHIM sees or syncs the package. MO2 may overwrite the displayed version during import, and native warning-free UI behavior has not been verified.

With CHIM running and connected, load into a save to trigger sync. Then open **Server Plugins → Automatic Game Plugin Sync → Refresh Status** and verify the installed server version is **0.1.17**. Check the client `SERVER_PLUGIN_SYNC` log as well; the server version is separate from MO2's displayed version. Open **Plugin Page**, or the server's `ext/private_conversation/index.php` path with its actual origin/port/base path.

| Alternative download | Intended route |
| --- | --- |
| [DWPkg](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.17/private_conversation-0.1.17.dwpkg) | Advanced manual sync: rename the downloaded file to `0.1.17.dwpkg`, then place it at `Data/CHIM/server-plugins/private_conversation/0.1.17.dwpkg`. Do not unpack it into Data. |
| [Repository tar](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.17/private_conversation.tar.gz) | Advanced CHIM repository/Plugin Manager route; strip its one `private_conversation/` wrapper to expose `manifest.json` and extension files. This is a different consumer from DWPkg sync. |
| [SHA256SUMS.txt](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.17/SHA256SUMS.txt) | Optional verification of downloaded release assets against published checksums. |

**Replace the old enabled package; do not stack versions.** The published [0.1.4](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/private_conversation-v0.1.4) and [0.1.5](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/private_conversation-v0.1.5) manifests use literal old-hub asset URLs, so their update buttons cannot move themselves to this repository. Use this plugin's version-specific 0.1.17 asset for file sync, or a candidate channel explicitly configured for `Francisco-boop-001/CHIM-PrivateConversation`; then verify the installed version and `git_repo`. When switching to Plugin Manager, disable/remove the old sync source first. Use version-specific assets, not repository-wide `releases/latest`. Official CHIM catalog listing has not been submitted or approved.

**0.1.17 requires no separate Papyrus companion, ESP or ESL and consumes no Skyrim plugin slot.** It reads CHIM's existing background reports. The older 0.1.3 companion was a historical experimental candidate, not a requirement, and 0.1.9 no longer accepts its `ext_pcv_presence` command.

Optional opinion effects need [Mind Poisoning 0.1.16](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/tag/mind_poisoning-v0.1.16), enabled alongside it. PCV checks `\ChimMindPoisoning\MIND_POISONING_REFLECTION_API_VERSION === 1` before model or database work, and uses the whole-reply API only when `MIND_POISONING_REFLECTION_REPLY_API_VERSION === 2` and its evaluator exist. With Mind Poisoning 0.1.14–0.1.15, solo effects still work but only the final line is evaluated. The observer is optional; when supported, PCV imports accepted sanitized reflection records while Mind Poisoning continues its ordinary evaluation and sink delivery. Scene direction works without Mind Poisoning.

## Direct your first scene

1. Choose eligible NPC A and B on the page, and optionally NPC C and D. At least two must be eligible at activation; the others are left out of that scene.
2. Keep **Exclude the player** checked for an NPC-only scene. Choose excluded or silent bystanders.
3. Click **Arm or update on next input**. It stages settings; the browser does not launch dialogue.
4. In Skyrim, use ordinary **Standard** text/voice input: `Aela asks Lydia whether Nazeem can be trusted. Lydia considers the accusation skeptically.`
5. CHIM generates the opener's response. The opener is the member your direction names first (here Aela), else the picker, else NPC A. Rechat depends on CHIM settings; this is not an endless autonomous conversation engine.

While the player is excluded, supported ordinary input is scene direction. To speak as yourself, include the player in pair mode or end it first. `Hello, Lydia` is a poor direction unless ambiguity is your hobby.

For solo, enable **Solo reflection**, select the **Reflecting NPC**, arm it and enter: `Lydia thinks aloud about her recent encounters with Nazeem and whether her first impression was fair.` One generated response follows. Solo remains selected for further inputs until changed, ended or expired; it does not continuously generate monologues.

**END clears active and pending scene settings immediately**, without fresh actor reports or another in-game input. An already-started request or queued audio may finish; use CHIM's **Stop All Dialogue** for a playback-stop request. The source checks do not verify that external control in-game. ARM/update still waits for eligible Standard input. Pending settings expire after **15 minutes**. An active scene ends after **60 minutes without a scene turn** (each turn renews it), or after **five minutes** in which every request was refused because its NPCs were gone; the page then says so. One scene is active per validated scope.

**Remember END.** While a scene is active, everything you type or say in Standard mode is scene direction, not your character speaking — in live testing, a forgotten solo scene turned "Aela, are you ready to go?" into a reflection prompt for someone else.

While active, ordinary Standard inputs and supported CHIM continuations remain subject to the scene's guards. Stale or missing eligibility stops those private requests; your direction is not quietly replayed as Player speech. Close/Whisper inputs are blocked. Use **END** to return ordinary inputs to normal handling. Unrelated generated Standard events keep their normal CHIM context and are not vetoed by stored private-scene settings. This does not globally silence other AI activity. Director and a separate early rolemaster child path remain outside these guards; avoid them when evaluating isolation.

## Get the result you actually want

These are hypothetical examples, not gameplay evidence. Give the model a topic, motive and room to disagree. Writing the desired affinity into a prompt is not a database command, however confidently you threaten the robot.

| Goal | Direction with player excluded | Possible result |
| --- | --- | --- |
| Suspicious gossip | `Aela tells Lydia why she distrusts Nazeem. Lydia weighs the claim against what she knows.` | An exchange; optional Mind Poisoning may revise Lydia's opinion of Nazeem or reject the accusation. |
| Praise | `Lydia describes something kind Farkas did. Aela considers whether she underestimated him.` | Positive influence is possible. Select Lydia as A if she should start. |
| Balanced debate | `Aela and Lydia disagree about Nazeem. Each explains her reasons without immediately resolving it.` | Different perspectives; zero opinion change is valid. |
| Solo reassessment | `Lydia thinks aloud about her actual recent experiences with Nazeem and whether she judged him too harshly.` | One spoken reflection, with optional change to Lydia's own opinion. |
| Eavesdropping | Exclude player/bystanders and direct the selected pair's topic. | The current scene is theirs; physical hearing, positioning and native facing are not enforced. |
| Reserved inn chat | Choose **present but silent** and direct the exchange. | Generic room atmosphere; vanilla greetings may still happen. |

Use recognizable names and distinguish allegations from events. `Aela alleges that Nazeem stole money` sets a topic, not a theft record. An invented instruction is not guaranteed to enter real reflection history. Resolvable subjects need not be nearby: the picker lists speakers, not everyone you may discuss.

If the player is included, `I think Nazeem has been unfair to Farkas` remains player speech, a different event path from an NPC making the allegation.

## What is removed from the prompt

The plugin changes request data as well as guidance:

- Excluded-player `inputtext`, `inputtext_s`, `ginputtext` or `ginputtext_s` becomes `instruction`. It uses the validated player prefix required by CHIM's parser; core removes that prefix before attribution. Your words are never relabeled as an NPC's completed speech.
- The audience and `CACHE_PEOPLE`/`CACHE_PEOPLE_LIMITED` contain the selected speaker(s), plus an explicitly included player. The routing snapshot's `present_actors` is cleared.
- `PROMPT_NEARBY_SECTIONS` replaces the ordinary nearby section with pair/solo guidance and optional generic silent scenery.
- Listener constraints/rechat guards restrict supported turns. Actions are disabled/constrained to conversation, and action instructions are removed from the composed system prompt.

**How CHIM stores it (seen live).** CHIM records an excluded-player direction as an `instruction` row reading `The Narrator: <your direction>`, witnessed only by the selected speaker(s). Later prompts therefore show your direction as something the Narrator said, and models tend to treat narrator lines as fact: phrase allegations as allegations. Solo lines are stored as `… (talking to explicit_disable_rechat)` with the marker listed among the people present. Both are CHIM core storage behavior that PCV cannot change.

**The whole prompt is not scrubbed of other people.** History, memories, profiles and other retained contributions may mention the player or bystanders. Lydia can remember travelling with the Dragonborn while the Dragonborn is absent from the current conversation, and discuss Nazeem without summoning him. Complete secrecy remains unproven.

The plugin does not rewrite memories, erase database records, move actors or suppress vanilla dialogue. A model can still disregard guidance. Report that as behavior to investigate, not proof that the inn acquired telepathy.

## Solo reflection and Mind Poisoning

PCV supplies the scene; Mind Poisoning owns optional opinion evaluation/persistence.

| Speech path | Opinion owner | Target |
| --- | --- | --- |
| A speaks to B about C | B | B's opinion of C |
| A reflects aloud about C | A | A's own opinion of C |

The pair path uses real NPC acknowledgement processing. Solo binds an effect to the **exact emitted native utterances** of one reply (with Mind Poisoning 0.1.16: every line, up to 24; otherwise the last line), actor, scope/configuration, IDs and private subtitle digests. A matching `_speech` acknowledgement of the final line is required; ACKs of earlier lines are recognised and ignored quietly. Registry metadata contains no dialogue.

The 0.1.7 release added bounded recovery when that `_speech` ACK reaches PCV before registration. At ACK prerequest, PCV captures only the utterance ID, PCV key/config/actor binding, ACK interaction generation, original server receipt time and a digest of the normalized tuple; it stores no dialogue. Registration-side and ACK-shutdown probes reconcile only an exact registration with a unique matching row from CHIM's native `public.speech` table. A direct ACK whose registration already exists bypasses the pending receipt map. Before reuse or ledger changes, the current active solo scope and interaction epoch are rechecked under the shared ledger lock. Mind Poisoning API v1 remains unchanged, and its evaluator plus PCV's fresh scope/playthrough checks still guard evaluation and transaction work.

Pending receipts live for **45 seconds** (eight entries/8 KiB total). Duplicate capture preserves the original receipt timestamp rather than renewing the TTL; claim rechecks that original age. After expiry/pruning, a new incoming ACK request with the same ID is a new capture. If it still needs recovery, reconciliation still requires the exact native row; a matching registered ACK uses the direct request path. Both remain subject to active-scope/epoch and one-time claim checks. Since 0.1.8, an unclaimed registration blocks replacement for **60 seconds** from its original registration; a distinct fresh output may supersede it after that lease. Since 0.1.9, a registration left by an ended or re-armed scene yields immediately, because it can never be evaluated. Duplicate registrations do not refresh the timestamp, and a late ACK for the superseded output cannot claim its replacement. The existing exact direct-ACK and claimed-effect freshness/protection remains up to **600 seconds**; shortening that window could repeat an uncertain opinion write. Fresh checks reject a later effect after END; they do not cancel work already committed or audio already delivered. Provider or commit uncertainty is not automatically retried.

The parser reads the final complete `DEBUG_DATA.OUTPUT_LOG` line paired with its utterance ID. Earlier lines are collected only from this request's own `eventlog` rows: the window must start at a unique instruction row for this request, contain no other request, and hold only this actor's solo lines; anything unprovable, aborted or over the cap falls back to the final line alone. `/` and `|` are wire separators, so subtitles containing either are rejected as `output_malformed`. The ACK proves a matching line attempt, not audio playback or hearing. Older version-1 records remain eligible for the historical direct-ACK path until expiry; they lack a saved source generation, so the new source/ACK two-epoch check applies to version-2 registrations and recovered receipts. CHIM's A↔B processing is separate; neither plugin sets native Skyrim relationship ranks, quest outcomes or shared world truth.

**What a solo effect still needs (live-server findings).** With PCV 0.1.9 and Mind Poisoning 0.1.16, the three blockers found earlier are fixed and were confirmed on the test server: whole replies are evaluated, short names such as "Aela" resolve, and a catalog entry named like your character (`Hawke`) no longer makes the listener ambiguous. An opinion change still needs:

- **A subject Mind Poisoning can resolve** — an NPC in your catalog.
- **Something new.** Mind Poisoning bases a reflection on what the NPC has heard. Reflecting on the same person again without new gossip in between is skipped as `reflection-basis-duplicate`: self-talk cannot ratchet an opinion. In the live run, three of six solo reflections were skipped this way, exactly as designed.
- **A judge that quotes honestly.** Mind Poisoning rejects a judgment whose evidence is not in the reply (`judgment_evidence_invalid`); one of six live reflections was rejected this way, leaving the opinion unchanged.

The `_speech` speech field is the subtitle, so phonetic substitutions do not break matching.

Solo evaluation uses the actor's real profile and bounded relevant prior speech history; it is not someone else's testimony. Unchanged evidence cannot repeatedly accumulate changes for the same subject. Disabled/paused processing, locks, stale scope, absent integration or provider/persistence failures leave opinions unchanged. Valid deltas are **-5 to +5**, including zero; affinity is bounded to **-100 to +100**. Zero is a result. The gossip machine is permitted to disappoint you.

## Automatic character selection

The server captures existing no-chat `infonpc_close` reports before CHIM's fast-event handling without consuming the core event. Nearby names intersect catalog identities and bounded recent AI-activity evidence. No ordinary chat/rechat is needed to produce the feed.

The page rereads eligibility every **15 seconds while visible**, and on becoming visible again. Polling cannot create reports or renew them. Activity evidence expires at projected age **45 seconds**, not a guaranteed interval from the exact instant AI deactivates.

Heartbeats arrive about every 10 seconds; the first report after a gap longer than 45 seconds (loading screens, long menus) is only a new baseline, so eligibility is unknown for one more heartbeat. In 0.1.9 a report may list up to **128** actors, which covers crowded cities and inns (the largest real report seen had 79 entries); generic repeated names such as guards stay ineligible as ambiguous.

CHIM supplies a broader scan, not precise earshot or door/floor checks. Ambiguous names, stale/missing evidence and uncertain identity fail closed; same-name in-game references are not individually distinguished. Known empty differs from unknown. See [background presence](docs/background-presence.md) for clocks/matching.

## Logs and troubleshooting

**Logging revision 2 shipped in 0.1.5 and remains through 0.1.9.** It adds a protected Logs view/export, richer presence/lifecycle evidence and visible storage/reader health; see the [diagnostics guide](docs/logging-revision-2.md). Since 0.1.8, PCV retries log-lock acquisition within one cumulative 100 ms per-request budget, then attempts each sanitized bounded event line in PHP's `error_log`; health stays degraded, the fallback is best effort and is not shown by the Logs reader. `state.scope_ended` identifies a successful END state change. In 0.1.9, routine presence successes are logged when the roster's state or size changes rather than on every heartbeat or page poll; stale, baseline and failed observations are always logged. A prior `routing.request_finished` entry remains a routing lifecycle result, not an opinion outcome. Mind Poisoning's observer remains optional; without it, detailed opinion results stay in Mind Poisoning's diagnostics.

Records explain **when, what, why, success/skip/failure**, with request IDs and configuration UUIDs connecting stages. Ordinary PCV logs omit raw prompts/dialogue; bounded NPC IDs and exception metadata may appear.

Run the reader on the server as the PHP worker's effective user:

```sh
cd /var/www/html/HerikaServer/ext/private_conversation
php diagnostics.php --limit 100
php diagnostics.php --request REQUEST_ID --config CONFIG_UUID --limit 100
php diagnostics.php --config CONFIG_UUID --jsonl > /restricted/path/private-conversation.jsonl
```

`diagnostics.php` is CLI-only and still rejects HTTP. The protected Logs page and CLI use the same sanitized reader; the page supports filtered viewing and JSONL export. The reader accepts 1–1000 matching records and scans at most five rotated 10 MiB files. Missing/capped logs are not proof nothing happened. The private per-install temporary location can be cleaned by the OS. For retention, configure **`PCV_LOG_DIR`** in the trusted worker environment: absolute/private, outside the webroot, worker-owned, mode `0700`. The reader needs the same identity/environment.

**`PCV_LOG_DEBUG_UNTIL`** enables temporary routing detail using a Unix timestamp no more than one hour ahead. It is an administrator environment setting, not a web parameter/toggle. Mind Poisoning's dashboard separately displays opinion outcomes/provenance; see its [dashboard guide](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/main/docs/dashboard.md).

| Symptom | Check first |
| --- | --- |
| No selectable actors | Fresh background reports, unique resolvable names and recent AI activity. Reload cannot manufacture evidence. |
| ARM does not speak immediately | Expected: send eligible Standard input and check armed/active status. |
| Actor becomes unavailable | Revalidation stops the request instead of substituting a random NPC. |
| Player addressed | Record version/settings/IDs/observed line. Retained context or model compliance may matter; native facing alone is not model-addressing proof. |
| Only one turn | Solo intentionally produces one response; pair depends on rechat settings/routing. |
| No opinion change | Compatible MP, enabled processing, unlocked owner, resolvable subject, genuine correlated ACK; inspect skip/zero/failure reasons. |
| Missing logs | Worker identity/environment, permissions, retention and cap. |
| Logs page says access is locked | Open it on the PC running the server. Since 0.1.10 the Windows host's own WSL address works; otherwise use the `127.0.0.1` link the page shows. |
| Partner stopped answering mid-scene | Since 0.1.10 a partner may be out of close range for up to 60 s or on CHIM's wider list; the log's `presence_check` says which check failed. A silent rechat after CHIM's rechat budget is normal (*Last scene turn* on the page). |

The page uses CSRF protection, **not a login system**. Keep CHIM behind an independently trusted access boundary. Scope uses validated playthrough identity; absent/verified-zero active profile state uses explicit shared-server scope under Player/install identity, not unique Skyrim-save isolation. Ambiguous identity stops processing.

Since 0.1.8, the default runtime store is a `state/` child under the validated per-install private temporary root. Its path depends on the effective PHP temporary root/namespace, worker UID and canonical extension path; it is separate from log placement and is not changed by `PCV_LOG_DIR`. The OS may clean the temporary root. On first state resolution, not package installation, valid legacy extension-local `state/` (shown as `server/state/` in this source tree) is moved whole by atomic rename when the private target is absent and on the same filesystem. A conflict, corrupt/unknown/unsafe file, or cross-filesystem rename failure (including `EXDEV`) refuses migration and leaves the legacy source in place; the plugin does not merge, partially copy or reset it. Quiesce old requests before updating, and keep HTTP access restricted through first startup. Manual recovery must match the HTTP worker's effective temp root/namespace, UID and canonical install path, and stay on one filesystem; a same-UID CLI process may resolve a different store. Do not copy or reset live state.

The extension-root `.htaccess` remains a defense for legacy extension-local `state/` (shown as `server/state/` in this source tree) only when Apache honors overrides. If overrides are ignored, that legacy directory may remain HTTP-readable until first resolver migration, so the package alone is not an access boundary. The denial was tested with isolated Apache fixtures, not your installed server; another server or disabled overrides needs an equivalent server-level rule.

## How it is coded

PHP hooks reuse CHIM's request, profile, prompt, response and speech paths. A small browser script refreshes selectors and preserves eligible drafts. No new daemon, Papyrus publisher or CHIM core patch is introduced.

```text
Browser settings → pending scene state
Standard input → preprocessing validation + instruction conversion
→ prerequest selects A → context_pre replaces nearby context
→ listener constraints → CHIM generation/TTS/client speech
→ optional exact solo-output registration → matching _speech ACK
→ Mind Poisoning evaluation + guarded persistence
```

| Source | Responsibility |
| --- | --- |
| [index.php](server/index.php), [ui-refresh.js](server/assets/ui-refresh.js) | Settings, controls and bounded read-only polling. |
| [state.php](server/state.php) | Identity, pending/active state, expiry and presence evidence. |
| [preprocessing.php](server/preprocessing.php), [scope.php](server/scope.php) | Feed capture, input conversion, audience/rechat guards and scene text. |
| [prerequest.php](server/prerequest.php) | Initial actor profile and reflection ACK entry. |
| [context_pre.php](server/context_pre.php), [context.php](server/context.php) | Nearby replacement, action constraints and prompt/speaker validation. |
| [json_response_custom.php](server/json_response_custom.php) | Fixed counterpart or native solo no-rechat listener. |
| [prepostrequest.php](server/prepostrequest.php), [postrequest.php](server/postrequest.php), [reflection.php](server/reflection.php) | Solo relationship-queue suppression, exact output registration and ACK/integration checks. |
| [log.php](server/log.php), [log_reader.php](server/log_reader.php), [diagnostics.php](server/diagnostics.php) | Bounded private diagnostics, shared sanitized reading and CLI export. |
| [build-package.py](scripts/build-package.py) | Deterministic release formats from an explicit server allowlist; the 0.1.7 release included the shared reader, HTTP state rule and ACK-receipt recovery module. |

The existing native marker `explicit_disable_rechat` is not a pretend second NPC to PCV or the client, although CHIM's history records it as the listener of solo lines (see above). Exact registration never guesses identity from the newest database row. Filesystem locks are released before model/database work. Invalid optional modules are caught/logged instead of counted as success.

### Build and verify source

Python **3.10+** builds packages; PHP/Node run fixtures. From this directory:

```sh
python scripts/build-package.py --help
python scripts/build-package.py --format release --release-dir dist/my-new-candidate
python tests/package_check.py
php tests/scope_check.php
node tests/ui_refresh_check.mjs
```

Use a fresh release-output directory; never overwrite assets from another version. The builder reads the version from the manifest. The canonical development repository is [CHIM-PrivateConversation](https://github.com/Francisco-boop-001/CHIM-PrivateConversation). Its `tests/reflection_registry_check.php` and `tests/reflection_full_reply_check.php` need matched Mind Poisoning 0.1.16 API fixtures (a sibling `CHIM-MindPoisoning` checkout). In the separate Mind Poisoning repository, `plugins/private_conversation/` is a pinned integration snapshot, not a second canonical authoring tree; keep it aligned with the tagged standalone source. These checks use isolated stores, not live CHIM. Runtime packages exclude tests, previews, task notes, live state, live-test helpers and the historical companion.

For live-server evidence without touching a gaming server, follow [live-server testing](docs/live-server-testing.md): it clones the CHIM WSL distro, installs through CHIM's package API and drives it with `scripts/live-test/` (a guarded client simulator). Windows has no PHP; `scripts/live-test/run-php-tests.sh` runs the PHP fixtures with the clone's PHP 8.2.

## Limits and verification

Accepted source/isolated checks cover state, identity, eligibility, feed capture, routing, output/ACK correlation, logging, UI and opinion processing. For early ACK, the [core review](tasks/ack-handoff-fix-2026-10-01/core-review.md) records fixtures using production PCV registration/reconciliation paths and the actual MP API v1 evaluator with stubbed store/model dependencies. The [database report](tasks/ack-handoff-fix-2026-10-01/database-review.md) records ten isolated PostgreSQL reader cases and four callback scenarios. The [expiry/stale-writer review](tasks/ack-bug-fix-2026-10-01/final-review.md) records original-timestamp freshness and admission-under-lock checks. Those scenarios use CHIM runtime-state, store, evaluator and provider stubs and disposable PostgreSQL; they are not installed-CHIM, real-provider, or Skyrim gameplay proof. A focused bug run fixed trailing carriage returns in ACK IDs while rejecting interior corruption. Browser review covered artwork/credit in a local fixture preview, not a live scene.

**Live-server runs (2026-10-02, 0.1.8 → 0.1.9).** On a clone of a real CHIM server (`cf5030f`) with a real player database and simulated client requests: installation through CHIM's package API, ordinary chat untouched with no scene, a pair scene (A answered B with the player excluded and only the pair recorded as present), solo registration and ACK handling, END, Plugin Manager deletion and crowded heartbeats all behaved as described once the three defects above were fixed. Not covered: the real client and DLL, audio, an active playthrough profile, and gameplay feel. Plugin Manager **Delete** leaves CHIM's package ledger reporting the version as installed, so game sync will not re-upload the same version afterwards. A second, Standard run with Mind Poisoning 0.1.16 used a 60-call AI budget (38 used) across baseline chat, pair scenes with two rechats, pair with the player, silent bystanders, solo positive/negative/neutral/short-name/first-line-only reflections, player gossip, and four failure cases (reply aborted mid-way, duplicate final ACK, presence gone stale before the ACK, END during the reply). Opinion changes landed on the right owner and subject; every failure case produced no effect; the one defect found (END swallowing the next reflection) is fixed above.

These checks are not installed-client equivalence, clean installation, provider/database failure recovery, native audio, physical privacy, cross-extension ordering or gameplay proof. Director and the early rolemaster child remain outside the scoped pipeline. Models can disregard directions; vanilla greetings exist; unresolved listeners may produce Player-facing presentation.

Schema-4 sync marks `state/` mutable. Catalog replacement has different semantics: back up state before switching routes. Disabling a game-carried package stops discovery but does not uninstall its server copy; remove through the server route too. Removing either plugin does not undo stored MP affinities. Back up the server for reversible experiments.

[Report issues](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/issues) with versions, CHIM/client revision if known, settings, reproduction, expected/observed behavior and bounded relevant logs. Omit credentials/full private dumps. "The llama looked suspicious" is excellent atmosphere and a terrible reproduction.

Maintained by [Francisco](https://github.com/Francisco-boop-001), for [CHIM by Dwemer Dynamics](https://github.com/Dwemer-Dynamics/CHIM). Its authors are not responsible for the llama's haircut.
