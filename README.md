# World of Drama-Llama — Francisco's CHIM Plugins

Skyrim already has dragons, a civil war and Nazeem. Naturally, it also needed gossip with consequences and NPC conversations that are not automatically about you. You're welcome. Condolences.

**World of Drama-Llama** is a small family of CHIM server plugins for private scenes, spoken reflection, NPC promises and changing opinions. The emo llama supervises. Its qualifications remain disputed.

![Two characters whisper in a snowy landscape while an emo llama lurks at the side.](assets/dashboard-art-source.png)

## Pick your particular disaster

### Mind Poisoning 0.1.21 — BETA CANDIDATE

When A tells B something about C, B may reconsider their opinion of C. For example, Aela might tell Lydia that Nazeem is arrogant; Lydia can agree, disagree or change her mind. Praise, criticism, a balanced account or no change can all be valid outcomes; gossip is not shared world truth.

It also supports Player-origin input, an opinion dashboard in CHIM Plugin Manager → Plugin Page, and optional overhearing. Overhearing is off by default and can evaluate up to four eligible NPCs alongside the addressed listener in one model call. This candidate carries the optional single CHIM relationship-type judgments and canonical Player handling from 0.1.20, and adds the root Plugin Page entrypoint and API declarations for experimental topic-based site discovery. Disposable PostgreSQL and server-install/recovery checks covered 0.1.20; 0.1.21's new root entrypoint has only a local access-gate check. No provider, MO2/native sync, or gameplay behavior is claimed. See the [candidate evidence summary](docs/beta-candidate-evidence.md).

Relationship updates use one native CHIM type at a time; simultaneous labels and Skyrim vanilla relationship ranks are outside this feature. See the player guide for the type safeguards and verification limits.

[Recommended MO2 ZIP](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.21/mind_poisoning-0.1.21-mo2.zip) · [Release notes](distribution/mind_poisoning-v0.1.21.md) · [Release and checksums](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/tag/mind_poisoning-v0.1.21) · [Source](https://github.com/Francisco-boop-001/CHIM-MindPoisoning) · [Player guide](docs/mind-poisoning.md) · [Dashboard and logs](docs/dashboard.md) · [Developer guide](docs/development.md)

### Private Conversation 0.1.18 — ALPHA

Stage a pair, a group of 2–4, a free scene, or one solo response. For example, choose Aela and Lydia on the Private Conversation page, arm the scene, then send a Standard-mode direction in Skyrim. Arming alone does not start dialogue. Voice-friendly “wrap up” and “end scene” phrases close scenes. Scene direction works without Mind Poisoning. When installed, Mind Poisoning can evaluate eligible dialogue acknowledgements and registered solo reflections for opinion effects; its judgments still come from dialogue, not scene-action execution. Scene scope does not enforce physical earshot or erase retained memories.

Scenes now support optional Personal, Physical and Intimate actions. All three are off by default and can be opted into per scene. Personal covers solo or shared activities, Physical covers scene-member brawls or surrender and is unavailable in solo scenes, and Intimate uses SHARMAT's gated actions. Targets stay within the scene; Attack and KillTarget are excluded. CHIM's action switch remains authoritative, and SHARMAT's consent and safety gates still apply to Intimate actions.

Version 0.1.18 is the current ALPHA, with END failure handling and honest last-turn diagnostics; in-game playback remains unverified.

[Release and checksums](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.18) · [Source](https://github.com/Francisco-boop-001/CHIM-PrivateConversation) · [Tagged guide and installation](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.18/README.md) · [Logging guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.18/docs/logging-revision-2.md)

### Sworn & Scorned 0.1.5 — PRE-ALPHA

Sworn & Scorned tracks NPC-owned promises and grievances. For example, an NPC's promise to help another with a problem can remain as an issue; if it is open and unmuted, it may provide one short reminder in a later eligible conversation. The model chooses whether to mention it; no confrontation or recognition of a particular quest or item is guaranteed. Qualifying outcomes can also affect the NPC owner's CHIM opinion of the counterpart.

It starts disabled. Enable it and choose a cast of up to eight NPCs. PHP CLI workers are required. CHIM's relationship evaluator is the default; Jev's experimental decision evaluator is optional, off by default, and does not generate dialogue.

[Release and checksums](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/releases/tag/sworn_and_scorned-v0.1.5) · [Source](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned) · [Tagged guide](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/blob/sworn_and_scorned-v0.1.5/README.md) · [Release notes](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/blob/sworn_and_scorned-v0.1.5/distribution/sworn_and_scorned-v0.1.5.md)

## How they fit together

These are separate CHIM server plugins; install only the ones you want. Private Conversation does not require Mind Poisoning for scene direction. With both installed, Mind Poisoning can evaluate eligible Private Conversation dialogue acknowledgements and registered solo reflections for opinion effects. Sworn & Scorned works with CHIM on its own; it can coexist with Mind Poisoning but does not send its lifecycle decisions through it.

None needs an extra ESP/ESL or Papyrus companion. Sworn & Scorned does require PHP CLI workers. Check each tagged guide for setup details. Opinion changes concern CHIM affinity, not Skyrim's vanilla relationship ranks.

## Current releases and downloads

For Mind Poisoning, the v0.1.21 plain MO2 ZIP is the recommended route for MO2 users. Install and enable it as a normal mod; its root `meta.ini` records version and source, and it contains `CHIM/server-plugins/mind_poisoning/0.1.21.dwpkg`, so no second download, manual unpacking, or package insertion is needed. MO2 may still show its Skyrim content warning, and metadata does not prove CHIM read or synchronized the package. The raw DWPkg is an advanced alternative for manual CHIM file sync; the server TAR is an advanced alternative for an existing manifest/channel install. The separate flat `chim-plugin.tar.gz` is intended for experimental topic-based site discovery on a newer CHIM manager; that .21 route has not been verified by the .20 install/recovery campaign. A release checksum verifies downloaded bytes; it does not install the plugin. GitHub's autogenerated Source code archives are not installers.

Pick one install route per plugin. Each release page lists its `SHA256SUMS.txt` file.

| Plugin | MO2 ZIP (recommended for MO2 users) | Advanced: CHIM DWPkg (manual file sync) | Advanced: server TAR (existing manifest/channel) |
| --- | --- | --- | --- |
| Mind Poisoning 0.1.21 | [Download](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.21/mind_poisoning-0.1.21-mo2.zip) | [Download](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.21/mind_poisoning-0.1.21.dwpkg) | [Download](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.21/mind_poisoning.tar.gz) |
| Private Conversation 0.1.18 | [Download](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.18/private_conversation-0.1.18-mo2.zip) | [Download](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.18/private_conversation-0.1.18.dwpkg) | [Download](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.18/private_conversation.tar.gz) |
| Sworn & Scorned 0.1.5 | [Download](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/releases/download/sworn_and_scorned-v0.1.5/sworn_and_scorned-0.1.5-mo2.zip) | [Download](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/releases/download/sworn_and_scorned-v0.1.5/sworn_and_scorned-0.1.5.dwpkg) | [Download](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/releases/download/sworn_and_scorned-v0.1.5/sworn_and_scorned.tar.gz) |

Experimental new CHIM topic-discovery route: [flat `chim-plugin.tar.gz`](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.21/chim-plugin.tar.gz). Use one Mind Poisoning route per server; see the [candidate evidence and compatibility notes](docs/beta-candidate-evidence.md) before choosing it.

## Install carefully

Use one route for each plugin on a server. Replace older enabled packages; do not stack versions or mix competing sync and Plugin Manager sources. Follow the version-specific guide and confirm the installed version in CHIM Plugin Manager. For upgrades from the former CHIM-Plugins collection install, see the [deployment and migration guide](docs/deployment-migration.md).

For Mind Poisoning through MO2, launch Skyrim through MO2 with CHIM connected and load a save. On CHIM's Server Plugins page, open **Automatic Game Plugin Sync** and choose **Refresh Status**. Confirm `mind_poisoning` version `0.1.21` is installed before opening CHIM Plugin Manager → **Plugin Page**. The [player guide](docs/mind-poisoning.md) has the full steps and sync troubleshooting.

CHIM stores relationship data on the server. A separate Skyrim save or MO2 profile does not isolate that database, and removing a plugin does not undo its stored data. Use a separate CHIM server/database for testing.

## Support

For Mind Poisoning, open CHIM Plugin Manager → Plugin Page → Logs → Download filtered plugin log. For Private Conversation, follow its [tagged logging guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.18/docs/logging-revision-2.md); for Sworn & Scorned, follow its [tagged guide](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/blob/sworn_and_scorned-v0.1.5/README.md). Each plugin has its own logging instructions.

For a useful report, include installed plugin versions, CHIM/client revision if known, relevant settings, reproduction steps, and expected versus observed behavior. Omit credentials and full private conversation dumps. Mind Poisoning's optional debug rationale can contain sensitive context, so review it before sharing.

Historical reports include [Mind Poisoning 0.1.10 Player-origin cases](tasks/live-acceptance-v0.1.10.md) and [user-reported Mind Poisoning NPC pair-path observations collected during Private Conversation scenes](tasks/reflection-reply-cap-2026-10-02.md). They describe specific cases and versions, not general gameplay reliability for either plugin. No current gameplay acceptance is claimed here for Sworn & Scorned.

[Issues and support](https://github.com/Francisco-boop-001/CHIM-Plugins/issues)

Maintained by [Francisco](https://github.com/Francisco-boop-001), built for [CHIM by Dwemer Dynamics](https://github.com/Dwemer-Dynamics/CHIM). They made the platform. I brought the llama and made things awkward.
