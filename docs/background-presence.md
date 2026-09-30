# CHIM background presence

The `0.1.4` PRE-ALPHA candidate reads CHIM's existing no-chat `infonpc_close` and activity-status reports. It does not require the separate Papyrus companion used by the historical `0.1.3` candidate. Historical server/companion artifacts remain unchanged; packaging this integration does not establish live delivery or game acceptance.

## Event path

In the inspected CHIM source at commit `12e035d0a810b9b932fe2df1f688407a72cd27a1`, the idle game loop emits `infonpc_close` and then posts `activity_status_bulk` after more than eight seconds, subject to game-loop conditions (`Plugin/Plugin.cpp:2310–2375`). The nearby scan uses a 3,000-game-unit bound (`Plugin/Commands.cpp:4997–5099`); the activity producer enumerates CHIM-managed actors and reports status separately (`Plugin/Plugin.cpp:7004–7070`). These are distinct samples.

On the server, `main.php:201` loads extension preprocessing before the fast-command list is built. The core later logs and terminates `infonpc_close` in its optional-event path (`main.php:959–998`), before extension `prerequest.php` runs. The extension therefore captures the report in preprocessing and returns without terminating or changing the event, so normal core logging continues and no LLM request is started.

The browser's same-origin refresh is read-only. It calls `pcv_read_eligible_npcs()` to display current state; it does not write a report or renew its receipt time.

## Matching and freshness

`pcv_capture_background_presence_report()` validates the report against the current playthrough and player identity. The native event timestamp is the heartbeat clock `H`; the server records receipt time `R`. The first heartbeat for a playthrough, or the first heartbeat after the prior one expires, establishes a timestamp baseline. That sample cannot reuse older activity rows; a player-only report can still authoritatively mean empty. Repeated or out-of-order `H` values do not extend freshness.

For each nearby report name, the reader requires a unique current catalog match and a fresh CHIM activity timestamp `S` from the catalog metadata (`metadata.activity_status.timestamp`, or the legacy `metadata.activity_status_timestamp`). A status row qualifies only when it is newer than the baseline and its projected age satisfies `0 <= (H - S) / 1,000,000,000 + (now - R) <= 45 seconds`. `H` and `S` are native nanosecond timestamps; `R` and `now` are server receipt/current times in seconds. The expression estimates age at read time by combining the native-clock difference at the heartbeat with elapsed server time since receipt. A status sampled just after `H` may be briefly ahead and is eligible once projected age reaches zero; it must still be within the 45-second lease. The reader joins by canonical name; it does not invent per-actor distances or runtime FormIDs.

This estimate assumes the native timestamps share a comparable clock and that the server wall clock is stable and nondecreasing. A backward wall-clock adjustment that leaves `now` at or after `R` but within `R + 45` can make an unchanged report appear fresh again; the receipt-time TTL has the same deployment assumption. Reads treat a future `R` as unavailable. A lower native heartbeat timestamp is rejected while the prior lease is fresh, preserving that prior bounded report; after lease expiry or when the prior `R` is in the future after a server-clock rewind, the next accepted heartbeat establishes a new baseline. Delayed event processing, file update timing, or clock behavior may also cause temporary unknown/stale results. Source checks do not establish these timing assumptions or live delivery behavior.

A valid player-only report is an authoritative empty result. No heartbeat, an expired report, malformed identity, duplicate names, or nearby NPC names without fresh activity matches are unknown/stale or unavailable, never empty. An expired heartbeat resets the baseline even when the next `H` advances. The background reader does not fall back to old ordinary-input or companion presence files. Ordinary Standard-input activation continues to use its request-local snapshot path.

## Limits

The CHIM scan's 3,000-unit bound is broader than a precise player-audience radius, and the report does not include each actor's distance or the player's effective radius while sneaking. Name matching also cannot distinguish a nearby unmanaged reference from a distant managed reference with the same name; duplicate report or catalog names fail closed, but this cross-source same-name collision remains a limitation.

CHIM AI deactivation has no matching immediate report. The 45-second limit bounds the estimated age of a stored activity observation; it is not a wall-time guarantee measured from the actual moment CHIM AI deactivates. The page may display unknown while waiting for a fresh matching report, and its polling cannot trigger or repair the game-side producer. Installed DLL equivalence, live event delivery, and in-game behavior remain unverified.

This describes the source and packaging contract for the candidate. Installation, live event delivery and gameplay remain unverified.
