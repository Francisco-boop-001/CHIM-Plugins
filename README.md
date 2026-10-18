# World of Drama-Llama — Francisco's CHIM Plugins

Skyrim already has dragons, a civil war and Nazeem. Naturally, it also needed gossip with consequences and NPC conversations that are not automatically about you. You're welcome. Condolences.

**World of Drama-Llama** is a small family of CHIM server plugins for private scenes, spoken reflection, NPC promises and changing opinions. The emo llama supervises. Its qualifications remain disputed.

![Two characters whisper in a snowy landscape while an emo llama lurks at the side.](assets/dashboard-art-source.png)

## Pick your particular disaster

### Mind Poisoning 0.1.24 — BETA CANDIDATE

Mind Poisoning lets a CHIM character react to a claim about a named person. Aela can tell Lydia that Farkas kept his promise; Lydia may warm to him, treat the claim skeptically, or leave her opinion of him unchanged. Praise, criticism, and zero change are all possible, and the response belongs to the listener. Our reputation-management qualifications amount to an unusually large sample of Skyrim gossip.

Direct Player speech can also prompt checks about named NPCs. A compatible Private Conversation reflection evaluates the reflecting actor’s own opinion. Optional overhearing stays off by default; when enabled, it can consider up to four additional eligible NPCs alongside the addressed listener. Effects appear in CHIM relationship data, while Skyrim’s native rank remains separate. Optional relationship-type judgments remain available, with updates limited to one native CHIM type at a time.

Version 0.1.23 added the opinion owner’s existing, bounded notes about the person being discussed to ordinary speech, reflection, and enabled overhearing. That can help with a complicated situationship, a lingering grudge, or one person expecting more than the other. The notes may be stale, incomplete, truncated, or one-sided. One person’s attraction or hope cannot establish a mutual or exclusive commitment. The context change adds no model call, relationship label, or saved relationship state; the listener can still disagree or make no change.

Version 0.1.24 clarifies `restore-policy` in the player guide and in both dashboard views, **Interactions** and **Logs**. The guard stays in place: CHIM restore can retain newer relationship scores while restoring older Mind Poisoning processing history, which can make an already-applied event eligible again. Read the recorded **Outcome**, `stage`, and `model_outcome` together: `preflight` with `not_called` stopped before model work; the persistence backstop can stop after model work. The reason describes that event, not today’s setting. Filtered JSONL records and their filters are unchanged. Ask the CHIM administrator to review any setting change and back up the database and configuration first. The evaluator, API, guard, and schema are unchanged; the explanation is new, not the restore policy.

For a first check, follow the [ordinary interaction steps](docs/mind-poisoning.md#try-one-ordinary-interaction) and compare CHIM affinity and type before and after. Then check **Plugin Manager → Plugin Page → Interactions**; the [dashboard](docs/dashboard.md) is read-only, and its **Logs** tab can download filtered JSONL. Use the guide’s [Private Conversation reflection workflow](docs/mind-poisoning.md#solo-reflection-with-private-conversation) for a reflection—there is no special Mind Poisoning command.

0.1.24 remains a **BETA CANDIDATE**. Dashboard render and PHP lint checks passed, as did the manifest-update and site-compatibility checks and all eight packaging cases. Four candidate installer assets were built and verified locally. These checks do not verify public release bytes, a clean-tag export, installed CHIM behavior, provider behavior, live-database persistence, or gameplay. Our tests can inspect the plumbing; they have not held a conversation with Lydia. See the [candidate evidence summary](docs/beta-candidate-evidence.md) and [verification summary](docs/verification-v0.1.24.md).

[Recommended MO2 ZIP](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.24/mind_poisoning-0.1.24-mo2.zip) · [Release notes](distribution/mind_poisoning-v0.1.24.md) · [Release and checksums](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/tag/mind_poisoning-v0.1.24) · [Source](https://github.com/Francisco-boop-001/CHIM-MindPoisoning) · [Player guide](docs/mind-poisoning.md) · [Dashboard and logs](docs/dashboard.md) · [Developer guide](docs/development.md)

### Private Conversation 0.1.21 — BETA

Want two NPCs to talk while the Dragonborn stays out of it? Choose Pair A/B, select **Exclude the player**, choose how to treat bystanders, arm the scene, then send one ordinary Standard direction. The button means the next input; the llama still needs you to point it at the right conversation.

Pair, Group, Free scene, and Solo reflection are available. Optional Personal and Physical actions follow the scene rules; Intimate actions remain subject to CHIM's switches and SHARMAT's consent and safety gates. PCV scopes the current request audience; it does not guarantee physical privacy, erase stored history, or control every model response.

The public 0.1.21 BETA release has the same 20 non-manifest package payload files as exercised 0.1.20. The evidence record covers 0.1.20 runtime observations and the 0.1.21 package comparison; 0.1.21 itself was not separately played in-game. See [current beta evidence and limits](plugins/private_conversation/docs/operator-acceptance.md) and the [0.1.21 release note](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.21/distribution/private_conversation-v0.1.21.md).

[Release and checksums](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.21) · [Release notes](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.21/distribution/private_conversation-v0.1.21.md) · [Source](https://github.com/Francisco-boop-001/CHIM-PrivateConversation) · [Player guide](plugins/private_conversation/docs/player-guide.md) · [Current README and installation](plugins/private_conversation/README.md#install-and-update) · [Logging guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.21/docs/logging-revision-2.md)

### Sworn & Scorned 0.1.5 — PRE-ALPHA

Sworn & Scorned tracks NPC-owned promises and grievances. For example, an NPC's promise to help another with a problem can remain as an issue; if it is open and unmuted, it may provide one short reminder in a later eligible conversation. The model chooses whether to mention it; no confrontation or recognition of a particular quest or item is guaranteed. Qualifying outcomes can also affect the NPC owner's CHIM opinion of the counterpart.

It starts disabled. Enable it and choose a cast of up to eight NPCs. PHP CLI workers are required. CHIM's relationship evaluator is the default; Jev's experimental decision evaluator is optional, off by default, and does not generate dialogue.

[Release and checksums](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/releases/tag/sworn_and_scorned-v0.1.5) · [Source](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned) · [Tagged guide](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/blob/sworn_and_scorned-v0.1.5/README.md) · [Release notes](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/blob/sworn_and_scorned-v0.1.5/distribution/sworn_and_scorned-v0.1.5.md)

## How they fit together

These are separate CHIM server plugins; install only the ones you want. Private Conversation does not require Mind Poisoning for scene direction. With both installed, Mind Poisoning can evaluate eligible Private Conversation dialogue acknowledgements and registered solo reflections for opinion effects. Sworn & Scorned works with CHIM on its own; it can coexist with Mind Poisoning but does not send its lifecycle decisions through it.

None needs an extra ESP/ESL or Papyrus companion. Sworn & Scorned does require PHP CLI workers. Check each tagged guide for setup details. Opinion changes concern CHIM affinity, not Skyrim's vanilla relationship ranks.

## Current releases and downloads

Check the published release and its `SHA256SUMS.txt` before installing; a checksum verifies downloaded bytes, not CHIM sync. The plain MO2 ZIP is the recommended route for MO2 users. It records its version and source in root `meta.ini` and contains `CHIM/server-plugins/mind_poisoning/0.1.24.dwpkg`, so no second download, manual unpacking, or package insertion is needed. MO2 may still show its Skyrim content warning, and metadata does not prove CHIM read or synchronized the package. The raw DWPkg is an advanced alternative for manual CHIM file sync; the server TAR is an advanced alternative for an existing manifest/channel install. The separate flat `chim-plugin.tar.gz` is the experimental topic-discovery route for a newer CHIM manager; that route remains unverified end to end. GitHub's autogenerated Source code archives are not installers.

Pick one install route per plugin. Each release page lists its `SHA256SUMS.txt` file.

| Plugin | MO2 ZIP (recommended for MO2 users) | Advanced: CHIM DWPkg (manual file sync) | Advanced: server TAR (existing manifest/channel) |
| --- | --- | --- | --- |
| Mind Poisoning 0.1.24 | [Download](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.24/mind_poisoning-0.1.24-mo2.zip) | [Download](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.24/mind_poisoning-0.1.24.dwpkg) | [Download](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.24/mind_poisoning.tar.gz) |
| Private Conversation 0.1.21 BETA | [Download](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.21/private_conversation-0.1.21-mo2.zip) | [Download](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.21/private_conversation-0.1.21.dwpkg) | [Download](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.21/private_conversation.tar.gz) |
| Sworn & Scorned 0.1.5 | [Download](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/releases/download/sworn_and_scorned-v0.1.5/sworn_and_scorned-0.1.5-mo2.zip) | [Download](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/releases/download/sworn_and_scorned-v0.1.5/sworn_and_scorned-0.1.5.dwpkg) | [Download](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/releases/download/sworn_and_scorned-v0.1.5/sworn_and_scorned.tar.gz) |

Experimental new CHIM topic-discovery route: flat [`chim-plugin.tar.gz`](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.24/chim-plugin.tar.gz). Use one Mind Poisoning route per server; see the [candidate evidence and compatibility notes](docs/beta-candidate-evidence.md) before choosing it. This route has not passed an end-to-end install check.

Private Conversation’s discovery asset is the flat [`chim-plugin.tar.gz`](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.21/chim-plugin.tar.gz). The table entry above is the wrapped legacy `private_conversation.tar.gz` route; CHIM’s repository installer strips that configured wrapper, so users should not unpack it by hand. The published BETA release has `prerelease=false` so CHIM can discover it; that flag is a filter, not a maturity certification.

## Install carefully

Use one route for each plugin. A clean Mind Poisoning install through one route creates one extension copy. Keep exactly one enabled Mind Poisoning MO2 package version. When both Mind Poisoning extension copies are present, CHIM's current bootstrap stops all CHIM conversation requests until one copy is removed. In an auditor's 0.1.21/0.1.21 reproduction, CHIM raised `Cannot redeclare ChimMindPoisoning\mindPoisoningEvaluateReflection` during `main.php` globals boot; the web Plugin Manager remained available.

For the Manager route, disable or remove the MO2 sync source before installing and do not load a save while it can resync. For MO2, update as **Replace**, never **Merge**, and do not click Mind Poisoning **Install/Update** in the web Plugin Manager. After any install or route change, verify exactly one of `ext/mind_poisoning` or `ext/CHIM-MindPoisoning` exists; if both remain, follow the linked recovery guide. Disabling or removing MO2 alone does not uninstall a server copy. Back up the database and config before folder cleanup; deleting extension code does not remove stored affinity or history from CHIM's database. Follow the [deployment and migration guide](docs/deployment-migration.md) for version-specific recovery.

For Private Conversation discovery, open CHIM Plugin Manager → **Server Plugins** → **Refresh Plugins**, then choose **Install Plugin** on the PCV repository row. For an installed row, choose **Update <channel label>**, confirm **Install Plugin** or **Update Plugin** in the installer, return with **Back to Plugin Manager**, and open **Plugin Page**. This route requires a normal published GitHub release (`draft=false`, `prerelease=false`); the current release is labeled BETA, and those technical flags are discovery filters rather than maturity certification. A fresh repository-slug install normally uses `ext/CHIM-PrivateConversation`, while a matching update may preserve `ext/private_conversation`; use the generated Plugin Page link instead of assuming a folder. The MO2 ZIP embeds the DWPkg; do not install it separately or stack it with another PCV source.

If the PCV Plugin Page returns HTTP 409 for an inactive duplicate, use the named owner path and send END there before switching or removing a copy. Keep one active source; PCV does not automatically migrate state between paths.

For Mind Poisoning through MO2, launch Skyrim through MO2 with CHIM connected and load a save. On CHIM's Server Plugins page, open **Automatic Game Plugin Sync** and choose **Refresh Status**. Confirm `mind_poisoning` version `0.1.24` is installed before opening CHIM Plugin Manager → **Plugin Page**. The [player guide](docs/mind-poisoning.md) has the full steps and sync troubleshooting.

CHIM stores relationship data on the server. A separate Skyrim save or MO2 profile does not isolate that database, and removing a plugin does not undo its stored data. Use a separate CHIM server/database for testing.

## Support

For Mind Poisoning, open CHIM Plugin Manager → Plugin Page → Logs → Download filtered plugin log. For Private Conversation, follow its [tagged logging guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.21/docs/logging-revision-2.md); for Sworn & Scorned, follow its [tagged guide](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/blob/sworn_and_scorned-v0.1.5/README.md). Each plugin has its own logging instructions.

For a useful report, include installed plugin versions, CHIM/client revision if known, relevant settings, reproduction steps, and expected versus observed behavior. Omit credentials and full private conversation dumps. Mind Poisoning's optional debug rationale can contain sensitive context, so review it before sharing.

Historical live observations are summarized in the [public acceptance summary](docs/live-acceptance-summary.md). They describe specific cases and versions, not general gameplay reliability for any plugin. No current gameplay acceptance is claimed here for Sworn & Scorned.

[Issues and support](https://github.com/Francisco-boop-001/CHIM-Plugins/issues)

Maintained by [Francisco](https://github.com/Francisco-boop-001), built for [CHIM by Dwemer Dynamics](https://github.com/Dwemer-Dynamics/CHIM). They made the platform. I brought the llama and made things awkward.
