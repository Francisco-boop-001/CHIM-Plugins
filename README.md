# World of Drama-Llama — Francisco's CHIM Plugins

Skyrim already has dragons, a civil war and Nazeem. Naturally, it also needed gossip with consequences and NPC conversations that are not automatically about you. You're welcome. Condolences.

**World of Drama-Llama** is a small family of CHIM server plugins for private scenes, spoken reflection, NPC promises and changing opinions. The emo llama supervises. Its qualifications remain disputed.

![Two characters whisper in a snowy landscape while an emo llama lurks at the side.](assets/dashboard-art-source.png)

## Pick your particular disaster

### Mind Poisoning 0.1.28 — BETA CANDIDATE

Gossip can change an NPC's opinion of the person being discussed. A listener can believe praise, resent a reported betrayal, doubt the speaker or change nothing. Player-origin claims and compatible Private Conversation reflections are supported; optional overhearing can include eligible witnesses. Effects go into CHIM's relationship data, while Skyrim's native relationship ranks remain separate. Our reputation-management qualifications amount to an unusually large sample of Skyrim gossip.

**New: intimacy arrangements without deleting the complicated feelings.** Once enabled, recognition can record an NPC's own current `casual`, `romantic` or explicit `none` report separately from affinity and emotional type. An NPC can be at **−15 / suspicious** and still report casual intimacy. No new main tag, automatic affection fee or reverse relationship is invented. The other person's agreement, exclusivity and willingness remain separate questions. The llama declines to judge their life choices.

Mind Poisoning's intimacy-arrangement recognition is **off by default**. It uses exact addressed NPC speech or compatible registered reflection; wishes, invitations and third-party gossip are not grounds. Direct speech can add one synchronous configured-model request per eligible acknowledged line, including split replies. Reflection uses at most one request and can assess a fresh arrangement even when its affinity basis was already processed. Read the [arrangement guide](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/intimacy-arrangements.md) for activation, cost, examples and limits. Apparently friends with benefits also needed configuration management.

The optional SHARMAT consumer in [upstream PR #7](https://github.com/Wondernuttz/Sharmat-Alpha/pull/7) has a default-off sexual-eligibility switch: a validated exact-pair arrangement may satisfy ordinary type/liking eligibility while other applicable gates and current decisions remain. The switch controls that eligibility lane; automatic-crush policy is a separate upstream discussion and is not settled by this MP release. This MP download does not update SHARMAT or prove an OStim scene will start. Private Conversation continues to own scenes; Sworn & Scorned owns promises and grievances.

**Written-evidence API v1.** A compatible server-side producer can submit a bounded, producer-reported written-message exposure for evaluation of the named NPC recipient's ordinary opinion. MP does not deliver messages, prove authorship, payment or actual reading, notify absent characters, or create intimacy arrangements. In 0.1.28, the source scan ignores malformed unrelated rows but preserves conflicts for exact valid document/receipt/operation identities; it fails closed when a cooperative three-second scan budget expires, checked between synchronous reads rather than during a query. A compatible producer is required; no inbox or public Correspondence producer is included. The llama can judge a report. It still cannot deliver the envelope.

Arrangements follow the restored plugin timeline under **both** CHIM restore policies. Preserve mode may retain newer core affinity/type while restoring older arrangement state. The existing preserve-mode source fence and scope baseline remain; records are not reconstructed from affection or old notes. Follow the [restore guide](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/restore-policy.md) and back up database/configuration before a real update. Turning recognition off is supported; downgrading older code over newer receipts may require backup recovery.

Use **Plugin Manager → Plugin Page → Interactions** to compare proposed and confirmed results; the [read-only dashboard](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/dashboard.md) separates arrangement transitions from affinity/type. **Logs → Download filtered plugin log** exports useful diagnostics. The [player guide](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/mind-poisoning.md) covers ordinary gameplay and reflection; there is no special Mind Poisoning conversation command.

The release remains **BETA CANDIDATE**. Focused deterministic and native checks cover the 0.1.28 changes; two independent clean-tag builds matched across all four installer formats, and all five draft assets plus all five public release assets matched their verified bytes. This is source and package evidence, not live installation or gameplay. No 0.1.28 installation or gameplay, real-model interpretation, message delivery or reading is claimed. See the [0.1.28 verification summary](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/main/docs/verification-v0.1.28.md), [release notes](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/release-v0.1.28.md), and [historical acceptance summary](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.27/docs/live-acceptance-summary.md). No live server, user-installed mods or game data were touched. Publishing is not a maturity promotion.

[Recommended MO2 ZIP](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.28/mind_poisoning-0.1.28-mo2.zip) · [Release notes](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/release-v0.1.28.md) · [Release and checksums](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/tag/mind_poisoning-v0.1.28) · [Source](https://github.com/Francisco-boop-001/CHIM-MindPoisoning) · [Player guide](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/mind-poisoning.md) · [Arrangement guide](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/intimacy-arrangements.md) · [Developer API](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/integration-api.md) · [Written-evidence API](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/written-evidence-api.md)


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

Check the published 0.1.28 release and its `SHA256SUMS.txt` before installing; a checksum verifies downloaded bytes, not CHIM sync. The 0.1.28 plain MO2 ZIP is the recommended route for MO2 users. It records its version and source in root `meta.ini` and contains `CHIM/server-plugins/mind_poisoning/0.1.28.dwpkg`, so no second download, manual unpacking, or package insertion is needed. MO2 may still show its Skyrim content warning, and metadata does not prove CHIM read or synchronized the package. The raw DWPkg is an advanced alternative for manual CHIM file sync; the server TAR is an advanced alternative for an existing manifest/channel install. The separate flat `chim-plugin.tar.gz` is the experimental topic-discovery route for a newer CHIM manager; its controlled install result is described below and used candidate 0.1.26-correspondence.dev.1, not a public 0.1.28 release package. GitHub's autogenerated `Source code` archives are not installers.

Pick one install route per plugin. Each release page lists its `SHA256SUMS.txt` file.

| Plugin | MO2 ZIP (recommended for MO2 users) | Advanced: CHIM DWPkg (manual file sync) | Advanced: server TAR (existing manifest/channel) |
| --- | --- | --- | --- |
| Mind Poisoning 0.1.28 BETA CANDIDATE | [Download](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.28/mind_poisoning-0.1.28-mo2.zip) | [Download](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.28/mind_poisoning-0.1.28.dwpkg) | [Download](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.28/mind_poisoning.tar.gz) |
| Private Conversation 0.1.21 BETA | [Download](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.21/private_conversation-0.1.21-mo2.zip) | [Download](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.21/private_conversation-0.1.21.dwpkg) | [Download](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.21/private_conversation.tar.gz) |
| Sworn & Scorned 0.1.5 | [Download](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/releases/download/sworn_and_scorned-v0.1.5/sworn_and_scorned-0.1.5-mo2.zip) | [Download](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/releases/download/sworn_and_scorned-v0.1.5/sworn_and_scorned-0.1.5.dwpkg) | [Download](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/releases/download/sworn_and_scorned-v0.1.5/sworn_and_scorned.tar.gz) |

Experimental CHIM topic-discovery route: flat [`chim-plugin.tar.gz`](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.28/chim-plugin.tar.gz). Use one Mind Poisoning route per server. The controlled official Plugin Manager check passed with development candidate 0.1.26-correspondence.dev.1 against 0.1.27 runtime files; it did not install the public 0.1.28 asset. See the [0.1.28 verification summary](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/main/docs/verification-v0.1.28.md) for package receipts and limits.

Private Conversation’s discovery asset is the flat [`chim-plugin.tar.gz`](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.21/chim-plugin.tar.gz). The table entry above is the wrapped legacy `private_conversation.tar.gz` route; CHIM’s repository installer strips that configured wrapper, so users should not unpack it by hand. The published BETA release has `prerelease=false` so CHIM can discover it; that flag is a filter, not a maturity certification.

## Install carefully

Use one route for each plugin. A clean Mind Poisoning install through one route creates one extension copy. Keep exactly one enabled Mind Poisoning MO2 package version. When both Mind Poisoning extension copies are present, CHIM's current bootstrap stops all CHIM conversation requests until one copy is removed. In an auditor's 0.1.21/0.1.21 reproduction, CHIM raised `Cannot redeclare ChimMindPoisoning\mindPoisoningEvaluateReflection` during `main.php` globals boot; the web Plugin Manager remained available.

For the Manager route, disable or remove the MO2 sync source before installing and do not load a save while it can resync. For MO2, update as **Replace**, never **Merge**, and do not click Mind Poisoning **Install/Update** in the web Plugin Manager. After any install or route change, verify exactly one of `ext/mind_poisoning` or `ext/CHIM-MindPoisoning` exists; if both remain, follow the linked recovery guide. Disabling or removing MO2 alone does not uninstall a server copy. Back up the database and config before folder cleanup; deleting extension code does not remove stored affinity or history from CHIM's database. Follow the [deployment and migration guide](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/deployment-migration.md) for version-specific recovery.

For Private Conversation discovery, open CHIM Plugin Manager → **Server Plugins** → **Refresh Plugins**, then choose **Install Plugin** on the PCV repository row. For an installed row, choose **Update <channel label>**, confirm **Install Plugin** or **Update Plugin** in the installer, return with **Back to Plugin Manager**, and open **Plugin Page**. This route requires a normal published GitHub release (`draft=false`, `prerelease=false`); the current release is labeled BETA, and those technical flags are discovery filters rather than maturity certification. A fresh repository-slug install normally uses `ext/CHIM-PrivateConversation`, while a matching update may preserve `ext/private_conversation`; use the generated Plugin Page link instead of assuming a folder. The MO2 ZIP embeds the DWPkg; do not install it separately or stack it with another PCV source.

If the PCV Plugin Page returns HTTP 409 for an inactive duplicate, use the named owner path and send END there before switching or removing a copy. Keep one active source; PCV does not automatically migrate state between paths.

For Mind Poisoning through MO2, launch Skyrim through MO2 with CHIM connected and load a save. On CHIM's Server Plugins page, open **Automatic Game Plugin Sync** and choose **Refresh Status**. Confirm `mind_poisoning` version `0.1.28` is installed before opening CHIM Plugin Manager → **Plugin Page**. The [player guide](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.28/docs/mind-poisoning.md) has the full steps and sync troubleshooting.

CHIM stores relationship data on the server. A separate Skyrim save or MO2 profile does not isolate that database, and removing a plugin does not undo its stored data. Use a separate CHIM server/database for testing.

## Support

For Mind Poisoning, open CHIM Plugin Manager → Plugin Page → Logs → Download filtered plugin log. For Private Conversation, follow its [tagged logging guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.21/docs/logging-revision-2.md); for Sworn & Scorned, follow its [tagged guide](https://github.com/Francisco-boop-001/CHIM-SwornAndScorned/blob/sworn_and_scorned-v0.1.5/README.md). Each plugin has its own logging instructions.

For a useful report, include installed plugin versions, CHIM/client revision if known, relevant settings, reproduction steps, and expected versus observed behavior. Omit credentials and full private conversation dumps. Mind Poisoning's optional debug rationale can contain sensitive context, so review it before sharing.

Historical live observations are summarized in the [public acceptance summary](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/blob/mind_poisoning-v0.1.27/docs/live-acceptance-summary.md). They describe specific cases and versions, not general gameplay reliability for any plugin. No current gameplay acceptance is claimed here for Sworn & Scorned.

[Issues and support](https://github.com/Francisco-boop-001/CHIM-Plugins/issues)

Maintained by [Francisco](https://github.com/Francisco-boop-001), built for [CHIM by Dwemer Dynamics](https://github.com/Dwemer-Dynamics/CHIM). They made the platform. I brought the llama and made things awkward.
