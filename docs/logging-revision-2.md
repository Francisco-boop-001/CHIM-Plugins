# Follow the llama's paperwork

This guide describes **logging revision 2 in the Private Conversation 0.1.5 PRE-ALPHA candidate**. Published 0.1.4 archives remain unchanged. Source checks and isolated PHP/browser fixtures are not a deployed CHIM or Skyrim test. The final review records the evidence, rather than letting the llama certify itself.

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

The JSONL envelope stays at schema version 1. New records carry `logging_revision: 2`; old records without that field remain readable. This candidate sets the release version to 0.1.5; the published 0.1.4 package remains unchanged. Correlation IDs are evidence labels, never authorization tokens.

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
- `routing.request_finished` records the final observed PCV lifecycle outcome. `postrequest_observed` means that hook ran; it does not prove generated speech, playback or hearing. `unobserved` means no terminal outcome was observed.
- `reflection.output_registered` means the exact fresh native output passed registration checks. It does not prove audio played.
- `reflection.ack_skipped` explains missing, stale, mismatched or already claimed acknowledgement evidence.
- `reflection.ack_error` records operational/integration failure. A returned MP failure never becomes a successful PCV evaluation record.
- `reflection.evaluation_finished` means the MP adapter returned its committed status. Published Mind Poisoning 0.1.13 supplies an optional observer for bounded solo model/persistence diagnostics; without it, detailed outcomes remain in MP's own diagnostics.
- Presence observations distinguish available, known empty, aged stale, missing, awaiting an ordering baseline, malformed and operationally unavailable. Missing evidence is never an empty room.
- Browser refresh failures use fixed codes and are explicitly **client-reported**. They do not prove a server or game failure.

Browser reports use the same diagnostics access and CSRF checks. A locked remote client cannot submit them; a disconnected client may be unable to deliver them at all. Duplicate codes are suppressed during a failed refresh episode, reset after a successful refresh, and throttled by the server for 60 seconds per session/code. Reporting failures are not retried or recursively reported.

Routing requests that reached `routing.request_started` register a fatal shutdown observer. Its breadcrumbs contain the safe source basename, line and PHP error type. Caught exceptions contain class/code/basename/line. Failures before the plugin logger runs, abrupt process termination, and other unobserved PHP fatal paths still require the server's error log. Messages and stack traces are omitted from plugin records because “please debug this” is not permission to spray private dialogue into a log.

## Mind Poisoning stays in its own lane

Mind Poisoning 0.1.12 remains compatible with PCV's optional opinion effects but has no observer. In that pairing, MP continues normal processing and PCV records `reflection.observer_unavailable` / `observer_unsupported` for a validated ACK. This means unified telemetry is unavailable, not that evaluation failed. Published Mind Poisoning 0.1.13 adds the optional sanitized `RequestLog` observer used by this PCV candidate.

With observer-capable Mind Poisoning 0.1.13, PCV imports bounded solo model/persistence timing, fixed causes, bounded opinion changes and confirmed/unconfirmed/not-attempted commit state, correlated to the exact validated ACK tuple. Unknown commit stays unknown; zero change remains a legitimate result. These source and fixture checks do not establish live adapter, database, provider or gameplay behavior.

Pair gossip has no verified PCV registration tuple tying its separate MP hook to the scene. PCV logs the pair's routing lifecycle; inspect MP for the opinion evaluation. No newest-row guessing or pretend end-to-end trace.

## Storage and failure limits

The writer keeps **five segments of at most 10 MiB**, with **8 KiB per entry**, outside the webroot. Directories are private (`0700`), files private (`0600`), worker-owned and checked for symlinks/non-regular files. Rotation and append use the existing nonblocking lock. The reader briefly captures bounded file handles/sizes under a shared lock, then releases it before parsing.

Default storage is per-install/per-worker temporary storage. OS cleanup can remove it. For longer retention, an administrator can provision an absolute, worker-owned, private directory outside the webroot and set trusted worker environment variable `PCV_LOG_DIR`. The directory must already exist and pass the same checks. Invalid overrides visibly fall back to safe temporary storage when possible. No browser-provided path is accepted.

Health describes **this request**, not a historical guarantee that every worker wrote successfully. A later successful append does not erase earlier failure codes. Logging failures send bounded fixed-code fallbacks to PHP's configured error log without replacing CHIM's error handler. That fallback destination must itself be configured by the administrator.

Logging is best-effort: contention, permissions, disk failure, process termination and temporary cleanup can lose records. The reader shows busy/unavailable states and bounded omissions; it cannot recover already lost history. There is no tamper-evident archive, remote collector, audio verification or unlimited transcript.

Debug routing details require administrator environment variable `PCV_LOG_DEBUG_UNTIL`, a Unix timestamp no more than one hour ahead. Normal operational records stay enabled. Raw dialogue, prompts, credentials, claim tokens, subtitle digests, caller URLs and arbitrary error strings remain excluded.

The design follows relevant [OWASP logging guidance](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html) and the [OpenTelemetry log data model](https://opentelemetry.io/docs/specs/otel/logs/data-model/). It adds no logging dependency and makes no claim to be a complete OpenTelemetry exporter or to universally exceed every logging standard.
