# Private Conversation

**Part of the World of Drama-Llama.** Two NPCs, one conversation, and considerably less Dragonborn-shaped interference. Or one NPC thinking aloud, because apparently Skyrim also needed unsolicited introspection.

![Two travelers speaking quietly in an inn, with an emo llama portrait on the hanging banner behind them.](server/assets/private-conversation-scene.png)

**Candidate: 0.1.4 — PRE-ALPHA.** This CHIM server extension directs ordinary Standard-mode scenes. Source, isolated behavioral fixtures and browser artwork have been reviewed. Current-release installation, live feed delivery, model compliance, native playback and gameplay acceptance remain unverified. A handsome llama is not a QA department.

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
| Pair | A generates the opening response; supported rechat stays with A and B. CHIM's rechat settings determine whether another turn occurs. |
| Solo reflection | A thinks aloud for one generated response. B is disabled, the player excluded and rechat/continuation blocked. A response may contain multiple sentences/audio chunks. |
| Exclude the player | Input becomes an unattributed `instruction` event; guidance tells the model not to address, include, quote or narrate the player. |
| Include the player | Pair mode retains input as player speech. A and B remain the selected generated speakers. |
| Exclude bystanders | Removes their explicit current presence from the scoped audience/nearby context. |
| Bystanders present but silent | Adds generic silent scenery; it does not retain named bystanders or suppress vanilla Skyrim greetings. |

This controls the inspected Standard pipeline. It is not an acoustic simulation, a physical privacy barrier or a guarantee that an LLM will develop manners.

## Install and update

Requires a working CHIM/HerikaServer with the supported request, prompt and response hooks. Compatibility was inspected against server `cf5030f15781637498be86debe26fcf102f5690d` and native source `12e035d0a810b9b932fe2df1f688407a72cd27a1`; these references do not prove your installed DLL matches. Test with an isolated CHIM server/database. A disposable Skyrim save does not isolate server data.

Download from the [0.1.4 release](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/private_conversation-v0.1.4). Choose **one source**:

| Asset | Intended route |
| --- | --- |
| [MO2 ZIP](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/download/private_conversation-v0.1.4/private_conversation-0.1.4-mo2.zip) | Plain import with `CHIM/server-plugins/private_conversation/0.1.4.dwpkg`. Keep `CHIM` directly under the data root. |
| [DWPkg](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/download/private_conversation-v0.1.4/private_conversation-0.1.4.dwpkg) | Rename to `0.1.4.dwpkg`; place at `Data/CHIM/server-plugins/private_conversation/0.1.4.dwpkg`. Do not unpack it into Data. |
| [Repository tar](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/download/private_conversation-v0.1.4/private_conversation.tar.gz) | CHIM repository/Plugin Manager ingestion; strip its one `private_conversation/` wrapper, exposing `manifest.json` and extension files. A different consumer from DWPkg sync. |
| [SHA256SUMS.txt](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/download/private_conversation-v0.1.4/SHA256SUMS.txt) | Verify downloaded assets. |

MO2 may warn that CHIM-only data does not look like Skyrim content. Retain the intended layout with its manual installer's **OK → Ignore** override if necessary; that does not prove successful sync. Check the client `SERVER_PLUGIN_SYNC` log and **installed** version in CHIM Plugin Manager. Open **Plugin Page**, or the server's `ext/private_conversation/index.php` path with its actual origin/port/base path.

**Replace older packages; do not stack enabled versions.** When switching to Plugin Manager, disable/remove the old sync source first. Routes have separate ledgers. This repository contains multiple plugins: use per-plugin manifests and explicit release assets, not repository-wide `releases/latest`. Official CHIM catalog listing has not been submitted or approved.

**0.1.4 requires no separate Papyrus companion, ESP or ESL and consumes no Skyrim plugin slot.** It reads CHIM's existing background reports. The older 0.1.3 companion was a historical experimental candidate, not a requirement; its artifacts remain untouched.

Optional opinion effects need compatible [Mind Poisoning 0.1.12](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/mind_poisoning-v0.1.12), enabled alongside it. Scene direction works without Mind Poisoning.

## Direct your first scene

1. Choose eligible A and B on the page. Both must remain eligible at activation.
2. Keep **Exclude the player** checked for an NPC-only scene. Choose excluded or silent bystanders.
3. Click **Arm or update on next input**. It stages settings; the browser does not launch dialogue.
4. In Skyrim, use ordinary **Standard** text/voice input: `Aela asks Lydia whether Nazeem can be trusted. Lydia considers the accusation skeptically.`
5. CHIM generates Aela's opening response. Pair rechat depends on CHIM settings; this is not an endless autonomous conversation engine.

While the player is excluded, supported ordinary input is scene direction. To speak as yourself, include the player in pair mode or end it first. `Hello, Lydia` is a poor direction unless ambiguity is your hobby.

For solo, enable **Solo reflection**, select the **Reflecting NPC**, arm it and enter: `Lydia thinks aloud about her recent encounters with Nazeem and whether her first impression was fair.` One generated response follows. Solo remains selected for further inputs until changed, ended or expired; it does not continuously generate monologues.

**END is staged for the next eligible Standard input.** Queued audio may finish; Use CHIM's **Stop All Dialogue** for an immediate-stop request; this release's source checks do not verify that external control in-game. Pending settings expire after **15 minutes**, active settings after **60 minutes**. One scene is active per validated scope. Close/Whisper inputs are blocked while active. Director and a separate early rolemaster child path are outside these guards; avoid them when evaluating isolation.

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

**The whole prompt is not scrubbed of other people.** History, memories, profiles and other retained contributions may mention the player or bystanders. Lydia can remember travelling with the Dragonborn while the Dragonborn is absent from the current conversation, and discuss Nazeem without summoning him. Complete secrecy remains unproven.

The plugin does not rewrite memories, erase database records, move actors or suppress vanilla dialogue. A model can still disregard guidance. Report that as behavior to investigate, not proof that the inn acquired telepathy.

## Solo reflection and Mind Poisoning

PCV supplies the scene; Mind Poisoning owns optional opinion evaluation/persistence.

| Speech path | Opinion owner | Target |
| --- | --- | --- |
| A speaks to B about C | B | B's opinion of C |
| A reflects aloud about C | A | A's own opinion of C |

The pair path uses real NPC acknowledgement processing. Solo registers the **exact last emitted native utterance**, actor, scope/configuration, ID and private subtitle digest. Its matching `_speech` acknowledgement must arrive. Registration lasts at most **10 minutes**, is claimed once and retains no dialogue in the private registry. Missing, malformed, stale, mismatched or duplicate evidence cannot authorize an effect.

Solo evaluation uses the actor's real profile and bounded relevant prior speech history; it is not someone else's testimony. Unchanged evidence cannot repeatedly accumulate changes for the same subject. Disabled/paused processing, locks, stale scope, absent integration or provider/persistence failures leave opinions unchanged. Valid deltas are **-5 to +5**, including zero; affinity is bounded to **-100 to +100**. Zero is a result. The gossip machine is permitted to disappoint you.

The ACK reports a line attempt, not independent audio/hearing proof. Only the last chunk is registered. CHIM's A↔B processing is separate. Neither plugin sets native Skyrim relationship ranks, quest outcomes or shared world truth.

## Automatic character selection

The server captures existing no-chat `infonpc_close` reports before CHIM's fast-event handling without consuming the core event. Nearby names intersect catalog identities and bounded recent AI-activity evidence. No ordinary chat/rechat is needed to produce the feed.

The page rereads eligibility every **15 seconds while visible**, and on becoming visible again. Polling cannot create reports or renew them. Activity evidence expires at projected age **45 seconds**, not a guaranteed interval from the exact instant AI deactivates.

CHIM supplies a broader scan, not precise earshot or door/floor checks. Ambiguous names, stale/missing evidence and uncertain identity fail closed; same-name in-game references are not individually distinguished. Known empty differs from unknown. See [background presence](docs/background-presence.md) for clocks/matching.

## Logs and troubleshooting

Records explain **when, what, why, success/skip/failure**, with request IDs and configuration UUIDs connecting stages. Ordinary PCV logs omit raw prompts/dialogue; bounded NPC IDs and exception metadata may appear.

Run the reader on the server as the PHP worker's effective user:

```sh
cd /var/www/html/HerikaServer/ext/private_conversation
php diagnostics.php --limit 100
php diagnostics.php --request REQUEST_ID --config CONFIG_UUID --limit 100
php diagnostics.php --config CONFIG_UUID --jsonl > /restricted/path/private-conversation.jsonl
```

The reader is CLI-only, not a public HTTP endpoint. It accepts 1–1000 matching records and scans at most five rotated 10 MiB files. Missing/capped logs are not proof nothing happened. The private per-install temporary location can be cleaned by the OS. For retention, configure **`PCV_LOG_DIR`** in the trusted worker environment: absolute/private, outside the webroot, worker-owned, mode `0700`. The reader needs the same identity/environment.

**`PCV_LOG_DEBUG_UNTIL`** enables temporary routing detail using a Unix timestamp no more than one hour ahead. It is an administrator environment setting, not a web parameter/toggle. Mind Poisoning's dashboard separately displays opinion outcomes/provenance; see its [dashboard guide](https://github.com/Francisco-boop-001/CHIM-Plugins/blob/main/docs/dashboard.md).

| Symptom | Check first |
| --- | --- |
| No selectable actors | Fresh background reports, unique resolvable names and recent AI activity. Reload cannot manufacture evidence. |
| ARM does not speak immediately | Expected: send eligible Standard input and check armed/active status. |
| Actor becomes unavailable | Revalidation stops the request instead of substituting a random NPC. |
| Player addressed | Record version/settings/IDs/observed line. Retained context or model compliance may matter; native facing alone is not model-addressing proof. |
| Only one turn | Solo intentionally produces one response; pair depends on rechat settings/routing. |
| No opinion change | Compatible MP, enabled processing, unlocked owner, resolvable subject, genuine correlated ACK; inspect skip/zero/failure reasons. |
| Missing logs | Worker identity/environment, permissions, retention and cap. |

The page uses CSRF protection, **not a login system**. Keep CHIM behind an independently trusted access boundary. Scope uses validated playthrough identity; absent/verified-zero active profile state uses explicit shared-server scope under Player/install identity, not unique Skyrim-save isolation. Ambiguous identity stops processing.

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
| [log.php](server/log.php), [diagnostics.php](server/diagnostics.php) | Bounded private diagnostics and CLI export. |
| [build-package.py](scripts/build-package.py) | Deterministic formats from a 17-file server allowlist. |

The existing native marker `explicit_disable_rechat` is not a pretend second NPC. Exact registration never guesses identity from the newest database row. Filesystem locks are released before model/database work. Invalid optional modules are caught/logged instead of counted as success.

### Build and verify source

Python **3.10+** builds packages; PHP/Node run fixtures. From this directory:

```sh
python scripts/build-package.py --help
python scripts/build-package.py --format release --release-dir dist/my-new-candidate
python tests/package_check.py
php tests/scope_check.php
node tests/ui_refresh_check.mjs
```

Use a fresh release-output directory; never overwrite old assets. `php tests/reflection_registry_check.php` also runs in this repository's nested layout or alongside the original `CHIM-MindPoisoning` project. It uses isolated stores, not live CHIM. Runtime packages exclude tests, previews, task notes, live state and the historical companion.

## Limits and verification

Accepted source/isolated checks cover state, identity, eligibility, feed capture, routing, output/ACK correlation, logging, UI and opinion processing. A focused bug run fixed trailing carriage returns in ACK IDs while rejecting interior corruption. Browser review covered artwork/credit in a local fixture preview, not a live scene.

These checks are not installed-client equivalence, clean installation, provider/database failure recovery, native audio, physical privacy, cross-extension ordering or gameplay proof. Director and the early rolemaster child remain outside the scoped pipeline. Models can disregard directions; vanilla greetings exist; unresolved listeners may produce Player-facing presentation.

Schema-4 sync marks `state/` mutable. Catalog replacement has different semantics: back up state before switching routes. Disabling a game-carried package stops discovery but does not uninstall its server copy; remove through the server route too. Removing either plugin does not undo stored MP affinities. Back up the server for reversible experiments.

[Report issues](https://github.com/Francisco-boop-001/CHIM-Plugins/issues) with versions, CHIM/client revision if known, settings, reproduction, expected/observed behavior and bounded relevant logs. Omit credentials/full private dumps. "The llama looked suspicious" is excellent atmosphere and a terrible reproduction.

Maintained by [Francisco](https://github.com/Francisco-boop-001), for [CHIM by Dwemer Dynamics](https://github.com/Dwemer-Dynamics/CHIM). Its authors are not responsible for the llama's haircut.
