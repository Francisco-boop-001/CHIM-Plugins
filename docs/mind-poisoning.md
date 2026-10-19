# Mind Poisoning: Player Guide

Mind Poisoning can change a CHIM character’s opinion of a named person after a line about them. The character may believe the claim, doubt it, or change nothing. It changes CHIM relationship data, not Skyrim’s vanilla relationship rank. The model can still misunderstand a perfectly ordinary name; apparently naming people is harder than it looks.

**0.1.25 is a BETA CANDIDATE.** Confirm that its release assets have been published and check the matching checksum before installing. Back up your CHIM database and configuration, then try it on an isolated CHIM database before using it in a live game. The [candidate evidence summary](beta-candidate-evidence.md) and [verification summary](verification-v0.1.25.md) explain what has and has not been checked; the native fixture does not establish installed behavior.

Since 0.1.23, the plugin passes the opinion owner's existing relationship notes about a named subject into ordinary, reflection, and overhearing evaluations. Optional `relation`, `note`, `best`, and `worst` strings are bounded to 160 UTF-8 bytes apiece. They may be stale, incomplete, truncated, or one-sided: they are context, not evidence. Attraction or expectations in one person's note do not establish mutual feelings or an agreement. No new relationship state or label is added, and a listener may still disagree or make no change.

The earlier `Vampire` and `Black` exclusions apply only to generated aliases from generic wording. Exact catalog names—including `Vampire Fledgling`, `Black Widow`, and literal mononyms—remain available. Other common-word names can still be ambiguous; the model may misunderstand them.

## Install through one route

A clean install through one route creates one server-side copy. Choose the route before installing. Do not install the MO2 ZIP and the same plugin through CHIM’s Plugin Manager on the same server.

### MO2 route (recommended for a new file-sync install)

1. Install the [0.1.25 plain MO2 ZIP](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.25/mind_poisoning-0.1.25-mo2.zip) as a normal mod after publication. Keep exactly one enabled Mind Poisoning version. The ZIP has `CHIM` at its data root and contains the DWPkg; do not unpack or add another package.
2. When updating, choose **Replace**, not **Merge**. Do not click Mind Poisoning **Install** or **Update** in CHIM’s web Plugin Manager while MO2 sync is your route.
3. In CHIM, enable **Automatic Game Plugin Sync**. Before loading a save on an existing install or after changing routes, check for duplicate folders; if both exist, follow the [recovery guide](deployment-migration.md#mind-poisoning-duplicate-install-recovery) first. For a clean first sync, launch Skyrim through MO2 and load a save to trigger the documented sync.
4. On **Server Plugins → Automatic Game Plugin Sync**, choose **Refresh Status** and confirm `mind_poisoning` version `0.1.25`. Then open **Plugin Manager → Plugin Page**. The [dashboard guide](dashboard.md) explains access and the local handoff.

MO2 may warn that this CHIM-only mod does not look like Skyrim game content. That is MO2’s game-file classification; **Ignore** or **Continue**, if offered, does not prove CHIM read or synced the DWPkg. A CHIM “couldn’t read” or sync error is separate and remains undiagnosed. Record its exact text, package path, version, client/server revisions, and relevant sanitized `SERVER_PLUGIN_SYNC` lines rather than guessing at a cause.

### Existing CHIM Plugin Manager route

If your server already uses CHIM’s canonical Plugin Manager route (available since the 0.1.14 bridge), continue using it and do not add the MO2 copy. Follow CHIM’s [Plugin Guide](https://dwemerdynamics.com/chim/plugin-guide.html) for its current controls. To switch from the Manager route to MO2, keep MO2 sync disabled while removing the Manager-only folder; re-enable sync only after the MO2 copy remains. To switch from MO2 to the Manager, disable MO2 sync first so it cannot recreate its copy. For a 0.1.13-or-older installation, back up the database and configuration first; either upgrade through the [0.1.14 bridge](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/mind_poisoning-v0.1.14), or follow the [version-specific migration and duplicate-folder recovery](deployment-migration.md#mind-poisoning-migration-from-0113-or-older) before using the Manager route.

The flat `chim-plugin.tar.gz` is for topic-based discovery in a CHIM manager that supports that feature; a full 0.1.25 discovery install remains unverified. The current compatibility reference is CHIM 3.5.1 revision `57f292415b008cfaf1a4531f0654d2563362ce70`. The [raw DWPkg](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.25/mind_poisoning-0.1.25.dwpkg) and [server TAR](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.25/mind_poisoning.tar.gz) are advanced options for manual sync and existing manifest/channel installs.

**For existing installs or route changes, check for duplicate copies before loading a save.** Two installed Mind Poisoning copies stop all CHIM conversation requests until one is removed. In an auditor’s 0.1.21/0.1.21 reproduction, CHIM reported `Cannot redeclare ChimMindPoisoning\mindPoisoningEvaluateReflection` during `main.php` globals boot; the web Plugin Manager remained available. After any install or route change, verify that exactly one of `ext/mind_poisoning` or `ext/CHIM-MindPoisoning` exists. A clean first MO2 sync requires the save-load step above; verify its server folder and version before continuing play. If both folders exist, stop game loading, back up the database and configuration, and follow the [recovery guide](deployment-migration.md#mind-poisoning-duplicate-install-recovery):

- **MO2 route:** disable MO2 sync, remove `ext/CHIM-MindPoisoning`, and keep the MO2-managed `ext/mind_poisoning` copy.
- **Plugin Manager route:** disable MO2 sync, remove `ext/mind_poisoning`, and keep or update `ext/CHIM-MindPoisoning`.
- An existing Manager installation using `ext/mind_poisoning` can be valid when it is the only copy; do not rename it just to match the newer folder name.
- If preserving a removed copy, store it outside CHIM’s `ext/` directory. Renaming it inside `ext/` does not disable recursive extension loading.

Disabling or removing the MO2 mod only stops future sync; it does not uninstall the server copy. Removing either copy does not roll back affinity or history in the database.

## Try one ordinary interaction

1. Choose two known CHIM catalog characters. If Lydia or Farkas is not in your catalog, substitute two names that are. In CHIM’s relationship editor, note **Lydia’s affinity and relationship type toward Farkas** before the test.
2. Pick **one** of these example claims, not both:
   - Praise: “Farkas kept his promise at the west gate last night.”
   - Criticism: “Farkas abandoned us at the west gate last night.”
3. Use ordinary, direct Player speech to address Lydia and say your chosen line. Use the subject’s full catalog name; a pronoun such as “he” can be ambiguous. For this first check, do not use Director text or injected prompt context. The event must be Player speech in `STANDARD`, `WHISPER`, or `CLOSE` mode.
4. Wait for CHIM to finish the request. Open the Plugin Page’s **Interactions** tab, find the event, and compare it with the baseline. Check the affinity and type again in CHIM’s relationship editor.

A zero is a valid result: the listener can disbelieve the claim or remain uncertain. No opinion change is forced. If something changed, that is the actual delta—not proof the claim was true. There is no mandatory-gullibility switch; I can still supply a poor example, so keep the first test small.

Affinity can move by -5 to +5 per update, within -100 to +100. The provider call is synchronous and can add latency or cost; the 12-second I/O timeout is not a guaranteed end-to-end deadline. A new event with a repeated claim can influence opinion again; there is no hard cooldown or cumulative gossip cap.

For an NPC-to-NPC check, use one ordinary line where a speaker addresses one listener and names a third person—for example, Aela telling Lydia that Farkas abandoned the watch. Only an eligible NPC reply correlated to a recorded event qualifies; a Director instruction itself does not count, though an actual NPC reply after one may qualify. An ordinary NPC line can concern the Player or a known NPC. Player-origin input instead evaluates named other NPCs, not the Player or addressed listener. An ACK records the client’s line; it does not prove the line was audible.

Overhearing is off by default. If an administrator enables it, one ordinary NPC ACK can evaluate the addressed listener and up to four additional eligible NPCs from that event roster in one model call. Roster membership does not prove hearing, and sentinel-targeted Private Conversation wrap-up lines do not create a witness-only route.

## Solo reflection with Private Conversation

Mind Poisoning has no player-facing solo-reflection command. If you use a compatible Private Conversation version, follow its [private-reflection workflow](https://github.com/Francisco-boop-001/CHIM-PrivateConversation) rather than typing a special Mind Poisoning command. A reflection evaluates the actor’s opinion. For example, direct the actor to reflect on Farkas keeping watch at the west gate until dawn. The reply may span multiple lines, but it must identify Farkas for Mind Poisoning to assess; it need not retell the remembered event. A hidden instruction alone is not evidence. Use the full catalog name when names could be confused.

The current candidate’s full installed companion behavior is unverified. See the [integration API](integration-api.md) for the source and eligibility boundaries; that document describes the integration, not player controls.

### How existing relationship context is used

The 0.1.23 evaluator can use the opinion owner's existing relationship fields about the subject (`relation`, `note`, `best`, and `worst`), each valid nonempty string capped at 160 UTF-8 bytes. Ordinary talk, reflection, and enabled overhearing use the same bounded context. It adds no model call, new relationship label, or persistent state. These notes can be old, incomplete, truncated, or asymmetric, so treat them as fallible context rather than proof or new spoken evidence. One person's attraction or expectation is not a mutual commitment, and the model can still choose no change.

## Read Interactions and Logs

**Interactions** shows decision history:

| Field or outcome | What it tells you |
| --- | --- |
| **Proposed** | The model’s recommendation. |
| **Applied** | The numeric affinity delta actually applied, not a yes/no persistence flag. |
| **At event (before to after)** | Values recorded at that transaction. |
| **Type at event** | A type transition recorded for that event. |
| **Current / Current type** | CHIM relationship values now; later events may have changed them. |
| **Committed** | The transaction was committed. Check the numeric delta and type row; a type-only change can have zero affinity delta. |
| **Zero change** | A valid decision produced no affinity or type change. |
| **Skipped** | An eligibility or safety gate declined the event. |
| **Rejected** | The registration, source, or input was invalid, or the model answer was invalid or unsafe. |
| **Failed** | Request, provider, or persistence work failed; inspect its reason. |
| **Unconfirmed** | Commit was attempted but its confirmation was lost. The database may or may not contain the change; ask an administrator to inspect before retrying. |

The relationship type normally stays as-is. A type change is optional and needs clear, lasting grounds; custom and protected established types are guarded against temporary reaction labels. Type-only changes are possible. Nothing here changes Skyrim’s vanilla relationship ranks. More detail is in the [integration API](integration-api.md).

In **Logs**, search by request, utterance, or reason, then use the **Outcome** and **Level** filters. Levels are Debug, Info, Warning, and Error. **Download filtered plugin log** saves the filtered records as JSONL. The records are bounded, so save the download soon after reproducing the issue. Default records omit speech; opt-in debug rationale can include a short excerpt or name, so leave debug off and remove private details before sharing. Provider responses are not needed for a useful report.

For a report, include the Mind Poisoning, CHIM, and (if used) Private Conversation versions; approximate time; speaker, listener, and subject; route (normal speech, Director/injected text, or reflection); expected and actual result; and the before/after relationship-editor values. Attach a screenshot and the filtered log download. “It felt off” is a fair first impression; the row and log are what let someone reproduce it.

The dashboard is read-only. **Pause updates** pauses only the page’s automatic refresh; it does not pause Mind Poisoning. Day/Night changes appearance. Neither control changes plugin settings or relationships. Opening **Plugin Page** may hand a WSL browser off to localhost, but that navigation does not grant access; the dashboard’s existing authentication gate still applies. See the [dashboard guide](dashboard.md).

## If nothing changed

Start with the Interactions outcome, then check the matching log reason:

- **No subjects**: mention a known person by full name. Ordinary speech can show this with `no-subjects` or `no_subjects` in the log; reflection can show `reflection-no-subjects`.
- **Locked relationship**: the opinion owner is the listener for ordinary speech and the actor for reflection. Logs may use `locked`, `relationship_locked`, or `relationship-locked`.
- **Duplicate**: the same recorded event may be skipped as `duplicate-event` or `ledger-floor`; `reflection-basis-duplicate` means a new reflection was rejected because its evidence basis was unchanged. This prevents some repeat applications; it does not promise once-only model calls or billing.
- **Feature or provider unavailable**: CHIM global relationship processing and the CHIM interaction switch must both be On; Mind Poisoning must not be paused; and `RELLLM_CONNECTOR` must identify a configured `openrouterjson` or `openrouterjsoncached` connector. Ask the server administrator to check these settings.
- **Restore policy** (`restore-policy`): Check the logged **Outcome**, `stage`, and `model_outcome` together; see **Restore-policy setting** below.
- **Shared or ambiguous profile**: with no active CHIM playthrough profile, the dashboard labels history **Shared server**. An MO2 profile or Skyrim save alone does not isolate server data. Multiple or ambiguous active profiles can prevent evaluation.

The server operator pause is controlled by `data/mind_poisoning.json`; ask the administrator to use the [developer guide](development.md). It prevents new evaluations, but in-flight work may finish; it cannot cancel a provider call already running or undo a database change. The dashboard’s **Pause updates** only stops page refresh.

### Restore-policy setting

For a historical `restore-policy` record, check the row’s **Outcome**, `stage`, and `model_outcome`. `stage=preflight` with `model_outcome=not_called` means that request stopped before model work; a persistence-stage refusal can happen after model work. Newer reason codes distinguish an expired source, a scope baseline, a changed INIT boundary or setting, and other safety checks. These records show the condition at that check, not the current setting value.

The published 0.1.24 build still refuses when `NEVER_CLEAR_RELATIONSHIP_DATA` is effectively true. The 0.1.25 candidate adds a compatibility path: sources at or before the latest CHIM `init` event row are skipped; a nonempty mismatched Mind Poisoning scope is baselined at the current maximum event row, which skips the triggering event and can expire already-inserted pending sources. It retains current relationship values. CHIM may restore an older same-scope MP ledger, so this does not promise every newer receipt survives; the source-ID fence independently expires pre-load rows. Exact-event receipts and the bounded floor apply to the history CHIM loaded. This protects source-ID replay; it does not deduplicate a repeated claim under a new ordinary event ID or guarantee that reflection evaluates a newer line.

Ask the CHIM administrator to confirm the build and effective value. The exact user-facing setting label, UI location, and default are not verified here. Keep the intended protection enabled; before a deliberate setting or restore test, back up the database and configuration, drain pending work, quiesce the request pool, make the change, then restart the workers. Focused Windows fixtures and a bounded disposable native PostgreSQL fixture pass; they do not establish a full ACK request, provider, installed package, or gameplay behavior. Reinstalling or changing the provider/API key does not resolve a restore-policy reason.

## Stop or uninstall

To uninstall, remove Mind Poisoning from the installation route you chose and remove its server-side copy: remove the MO2 package or manual-sync source and delete the server folder, or delete the plugin through CHIM Plugin Manager and verify its folder is gone. Removing the MO2 mod alone only stops future sync; removing server files alone may let sync restore them. Neither action deletes relationship changes or history from CHIM’s database. Back up first and follow the [migration and recovery guide](deployment-migration.md) if changing routes or removing an unfamiliar copy.
