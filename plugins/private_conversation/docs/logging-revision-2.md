# Follow the llama's paperwork

Logging revision 2 shipped in Private Conversation 0.1.5 and remains in the 0.1.17 candidate. The private-state migration and logger lock-wait notes below describe changes first added in 0.1.8. Source checks and isolated fixtures are not a deployed CHIM or Skyrim test; release notes record evidence only for the version and environment they name.

## What the records answer

| Question | Recorded evidence |
| --- | --- |
| When? | UTC timestamp with milliseconds; elapsed request time from a monotonic clock. |
| What and how? | Fixed event, hook phase, route, scene mode and settings relevant to that operation. |
| Who? | Validated selected/speaker NPC database IDs when known. These identify actors, not an authenticated human user. |
| Why? | Closed reason codes for rejection, stale evidence, unsupported features and operational failures. |
| Did it work? | The outcome of the observed operation. Preparation, hook completion, output registration and database results are distinct. |
| Which scene? | Request ID and configuration UUID; validated event/utterance IDs for registered solo output and its acknowledgement. |
| Can I trust the history? | Storage mode, current-request write health, retained segment count and categorized reader omissions. |

The JSONL envelope stays at schema version 1. New records carry `logging_revision: 2`; old records without that field remain readable. Correlation IDs are evidence labels, never authorization tokens.

## Read and export

Open **Logs** from the scene page, or the extension's `index.php?view=logs`. The view uses the same sanitized reader as the CLI. Filter by request, configuration, event, severity, event ID, utterance ID or linked Mind Poisoning request ID. Results contain the latest **1–1000 matching records**, displayed oldest to newest within that result. Export is sanitized JSONL, not a raw-file download. Reads are manual; scene eligibility polling does not repeatedly scan log history.

Diagnostics access requires either a direct exact loopback connection without forwarding headers or a nonempty webserver-provided `REMOTE_USER`. Submitted reads, exports and browser failure reports additionally require the session CSRF token. A forwarded request, private LAN address, caller-supplied user header or CSRF token alone does not authorize access. If access is locked, scene controls remain available and the CLI is the fallback. This source guard does not certify the deployed proxy/authentication topology.

From the installed extension directory, as the PHP worker's effective user and with its environment:

```sh
php diagnostics.php --limit 100
php diagnostics.php --config CONFIG_UUID --severity error --limit 100
php diagnostics.php --event-id EVENT_ID --utterance-id UTTERANCE_ID --jsonl > /restricted/path/pcv.jsonl
```

JSONL output contains records only; CLI health goes to stderr. An empty filtered result means no matching retained record was returned. It does not mean nothing happened. Filters exclude unrelated records; malformed, oversized, unsupported and capped records are reported separately.

For a bug report, reproduce once, note settings and the observed result, then export the relevant configuration/request trace. Include PCV/MP/CHIM versions and the health summary. A full server dump is not a personality test anyone needs to take.

## Interpret outcomes honestly

- `routing.request_prepared` means PCV applied its guards and prepared context.
- Since 0.1.8, `state.scope_ended` records a successful END state change with `action: end`; it does not cancel an in-flight request or audio already delivered.
- `routing.request_finished` records PCV's lifecycle observation. `postrequest_observed` means CHIM's completion hook ran. `request_unobserved` means PCV did not observe that hook. Neither says whether text was generated, sent, queued or played, or why the request stopped; check CHIM/native logs for a stop reason.
- The shutdown observer can emit `routing.request_finished` before a later ACK-reconciliation callback runs. Any resulting reflection accepted/skipped/error entry is a separate effect result; the earlier routing event never certifies opinion evaluation. Request/configuration IDs may correlate request records; output/ACK joins require exact validated event/utterance IDs. Counts, timestamps or event order cannot reconstruct missing IDs.
- `reflection.output_registered` means the exact fresh native output passed registration checks. It does not prove audio played.
- `reflection.ack_skipped` explains a missing, stale, mismatched or already claimed acknowledgement at that check. An initial `registration_missing` can be followed by a later separate reflection result if registration and the unique native ACK row reconcile during shutdown or source registration. Some early records lack validated output IDs, so do not join them by counts or timing.
- `reflection.ack_error` records operational/integration failure. A returned MP failure never becomes a successful PCV evaluation record.
- `reflection.evaluation_finished` means the MP adapter returned its committed status. A compatible observer can add bounded solo model and persistence diagnostics; without it, detailed outcomes remain in MP's own diagnostics.
- Presence observations distinguish available, known empty, aged stale, missing, awaiting an ordering baseline, malformed and operationally unavailable. Missing evidence is never an empty room.
- Browser refresh failures use fixed codes and are explicitly **client-reported**. They do not prove a server or game failure.

### Observed pair trace: 2026-10-14

The Caracas 21:22–21:35 trace used PCV 0.1.17, Mind Poisoning 0.1.19 and client 3.5.1 (2026-10-13), with Elsa (5124) and Lidia (5126). Seven MP `utterance_id_absent` events were acknowledgements of incoming Hawke Player input, not missing IDs for NPC output. Separately, seven unacknowledged NPC chunks did have IDs: six reached native `ScriptQueue`, then queue-clear events followed newer inputs at 21:24:02, 21:27:32 and 21:28:53; there is no playback trace for them. The seventh, `utt_8848273663d6cf180588`, did not appear in the native queue. At 21:29:53.124, `HTTPManager.cpp:953` reported no data after 60 seconds and cancelled; the server fallback arrived at 21:29:56, too late for that request. Queueing, clearing and a late server write do not establish whether any earlier chunk played.

All 45 correlated NPC ACKs in that pair trace returned the MP reason `no-subjects` (the separate `reflection-no-subjects` reason is for solo reflection). Aggregate word counts across their ACK text were Hawke 0, Aela 0, Elsa 0, Lidia 0, Bloodstone 7, husband 3, you 12 and your 13; these totals do not map words to individual ACKs. The current MP resolver matches NPC catalog names and recognized aliases, plus Player aliases, in ACK text; it excludes that event's speaker and listener and does not infer pronouns or relationship phrases. Under that named-subject contract, `no-subjects` is expected when no eligible third-party name is spoken; this trace does not establish a matcher defect. A separate solo Lidia event at 06:34 committed subject `npc:2905`, affinity 37 to 34. See the separate copyable [CHIM delivery investigation](followups/chim-core-delivery-diagnostics-2026-10-15.md) and [MP subject investigation](followups/mind-poisoning-subject-diagnostics-2026-10-15.md); neither asks PCV to infer missing client or MP outcomes.

Browser reports use the same diagnostics access and CSRF checks. A locked remote client cannot submit them; a disconnected client may be unable to deliver them at all. Duplicate codes are suppressed during a failed refresh episode, reset after a successful refresh, and throttled by the server for 60 seconds per session/code. Reporting failures are not retried or recursively reported.

Routing requests that reached `routing.request_started` register a fatal shutdown observer. Its breadcrumbs contain the safe source basename, line and PHP error type. Caught exceptions contain class/code/basename/line. Failures before the plugin logger runs, abrupt process termination, and other unobserved PHP fatal paths still require the server's error log. Messages and stack traces are omitted from plugin records because “please debug this” is not permission to spray private dialogue into a log.

## Mind Poisoning stays in its own lane

Mind Poisoning 0.1.13 introduced the optional sanitized `RequestLog` observer; 0.1.14 declares reflection API version 1. PCV 0.1.6 introduced, and 0.1.7 retains, the exact API-v1 check before invoking the evaluator; a missing or unsupported API is `reflection_api_incompatible`. This companion check is separate from CHIM core compatibility. Scene direction works without Mind Poisoning.

When the compatible observer is available, PCV imports allowlisted solo model and persistence records with exact validated correlation IDs, fixed causes, bounded timings and opinion changes, and confirmed/unconfirmed/not-attempted commit state. The observer does not change Mind Poisoning's ordinary evaluation or sink delivery. If a compatible request log has no observer, PCV records `reflection.observer_unavailable`; this means unified telemetry is unavailable, not failed evaluation. Unknown commit stays unknown; zero change remains a legitimate result. These source and fixture checks do not establish live adapter, database, provider or gameplay behavior.

Pair gossip has no verified PCV registration tuple tying its separate MP hook to the scene. PCV logs the pair's routing lifecycle; inspect MP for the opinion evaluation. No newest-row guessing or pretend end-to-end trace.

## Storage and failure limits

The writer keeps **five segments of at most 10 MiB**, with **8 KiB per entry**, outside the webroot. Directories are private (`0700`), files private (`0600`), worker-owned and checked for symlinks/non-regular files. Since 0.1.8, rotation and append retry the native nonblocking lock within one cumulative **100 ms per-request lock-acquisition budget**. The reader briefly captures bounded file handles/sizes under a shared lock, then releases it before parsing.

Default log storage is per-install/per-worker temporary storage. OS cleanup can remove it. For longer retention, an administrator can provision an absolute, worker-owned, private directory outside the webroot and set trusted worker environment variable `PCV_LOG_DIR`. The directory must already exist and pass the same checks. Invalid overrides visibly fall back to safe temporary storage when possible. No browser-provided path is accepted.

Since 0.1.8, runtime state is separate from diagnostic log placement. Its default is `state/` under the validated per-install private temporary root, keyed by the effective PHP temporary root/namespace, worker UID and canonical extension path; `PCV_LOG_DIR` does not change it. The OS may clean the temporary root, and a different temporary namespace, worker UID or extension path may resolve a different store. On first state resolution—not package installation—a valid legacy extension-local `state/` (shown as `server/state/` in this source tree) is moved whole to the private target by atomic rename when the target is absent and both locations share a filesystem. Corrupt, unknown, unsafe or conflicting state and a failed cross-filesystem rename (including `EXDEV`) refuse migration; the source is preserved, with no merge, partial copy or reset. Quiesce old requests before upgrading and keep HTTP access restricted through first startup. Manual recovery must match the HTTP worker's effective temp root/namespace, UID and install path and use the same filesystem; matching UID in a CLI process alone is not sufficient. Do not copy or reset live state.

The extension-root `.htaccess` is only a defense for legacy extension-local `state/` (shown as `server/state/` in this source tree) when Apache honors overrides. If overrides are ignored, legacy data may remain HTTP-readable until the first state resolver migrates it, so use an independent access boundary during upgrade. The source denial was checked in isolated Apache fixtures, not on an installed server.

Health describes **this request**, not a historical guarantee that every worker wrote successfully. A later successful append does not erase earlier failure codes. Since 0.1.8, after the lock wait budget expires, PCV marks the request degraded, emits the generic fixed-code warning once, and attempts to send each already-sanitized bounded JSONL event line to PHP's configured `error_log` sink. Sink failure is contained. This fallback is best effort, is not included in the Logs reader, and is not covered by the 100 ms lock-acquisition budget; it does not bound total I/O or promise durable/no-drop delivery. The fallback destination must itself be configured by the administrator.

Logging is best-effort: contention, permissions, disk failure, process termination and temporary cleanup can lose records. The reader shows busy/unavailable states and bounded omissions; it cannot recover already lost history. There is no tamper-evident archive, remote collector, audio verification or unlimited transcript.

## CHIM identity and NPC-catalog reads

Since PCV 0.1.24, identity and NPC-catalog reads use the official `ptp_connect()` helper, which opens its own `PGSQL_CONNECT_FORCE_NEW` connection, and a read-only repeatable-read transaction. PCV caps lock waits at **2 seconds** and each SQL statement at **3 seconds**, retaining any shorter positive limit already configured for those reads. It commits or rolls back and closes only the connection it opened; the shared CHIM connection, its transaction, and other plugins’ settings are not changed. Catalog reads keep the full `core_npc_master` result shape and add no NPC-count cap.

These bounds apply per lock wait and per SQL statement, not to the whole PHP request. A failed read stays a failure; it is not reported as a verified empty profile or roster. Wait for database contention to clear, then explicitly refresh before retrying. These database waits are separate from the JSONL writer’s **100 ms** lock-acquisition budget above.

Debug routing details require administrator environment variable `PCV_LOG_DEBUG_UNTIL`, a Unix timestamp no more than one hour ahead. Normal operational records stay enabled. Raw dialogue, prompts, credentials, claim tokens, subtitle digests, caller URLs and arbitrary error strings remain excluded.

The [operator acceptance checklist](operator-acceptance.md) is a manual procedure and has not been run. The design follows relevant [OWASP logging guidance](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html) and the [OpenTelemetry log data model](https://opentelemetry.io/docs/specs/otel/logs/data-model/). It adds no logging dependency and makes no claim to be a complete OpenTelemetry exporter or to universally exceed every logging standard.
