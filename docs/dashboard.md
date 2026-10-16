# Mind Poisoning dashboard

The read-only Interactions/Logs dashboard was introduced in v0.1.8 and is included in the published v0.1.20 PRE-ALPHA package. v0.1.20 also fixes standalone dashboard normalization for canonical Player edges by loading the CHIM relationship helper from the validated server root; the isolated fixture used the pinned core helper. A valid affinity is no longer shown as Invalid because the required helper was absent. If the required helper is missing, the result is Unavailable; malformed relationship data remains Invalid. This correction has isolated fixture coverage, not live-deployment proof. The dashboard shows retained plugin records and query-time relationship state; it is not a live event monitor or audit guarantee. The empty readable log is shown as an empty source rather than falsely marked truncated. Remote web-server authentication and broader database failure/concurrency behavior remain unverified.

## Page and access

The packaged endpoint path is `ext/mind_poisoning/dashboard.php`; its stylesheet, text-free poster illustration, and refresh script (`dashboard.css`, `dashboard-art.webp`, and `dashboard.js`) are in the same directory. The title is selectable HTML text. The manifest `config_url` points to `../ext/mind_poisoning/dashboard.php?local=1` from CHIM's server-plugin UI; `local=1` requests navigation only and grants no access. The v0.1.8 package includes this handoff. The controller preserves the existing loopback or web-server authentication gate.

Choose Day or Night from the labeled controls above the poster. The mode is stored in the `theme=day|night` query parameter, so ordinary tab links, filter submissions, and the log download retain it without JavaScript or browser storage. Day is the default.

Automatic refresh requests the current filtered dashboard after each five-second interval following the prior request's completion, with a 15-second timeout. The pause control and hidden-tab or active-data interaction stop refresh requests; they do not change the query filters. A failed or timed-out request leaves the last successful view in place. Refresh uses only the dashboard's read-only data sources; it does not call the model/provider or write data. Without JavaScript, use the browser's normal reload.

The controller permits a request when the web server supplies a nonempty `REMOTE_USER`, or when the client address is loopback (`127.0.0.1` or `::1`) and the request has no `Forwarded`, `X-Forwarded-For`, or `X-Real-IP` header. It does not add a login. A reverse proxy that reaches PHP over loopback but strips those headers can appear to be a local request; deployments with that topology must require web-server authentication on the route. Remote access must not expose the endpoint anonymously. The remote authentication wiring has not been verified in a live CHIM deployment.

For a Windows browser using a CHIM server inside WSL, **Plugin Page** opts into a one-time handoff when its original request would be denied: the controller redirects to a fixed `localhost` origin, using the request's server-reported scheme, listening port, and script path, and removes the `local=1` marker. It preserves dashboard filters. The next request still has to pass the existing loopback or web-server authentication gate. The redirect does not trust the request Host or forwarding headers and does not allow the WSL gateway or private network. To open the server UI directly, use its existing localhost origin (for example, `http://localhost:8081/HerikaServer/ui/server_plugins.php`); a denied dashboard GET without the marker still returns 403 guidance.

The page accepts GET requests only. Interactions and Logs are ordinary links with bounded GET filters. The Logs download returns filtered plugin records as JSONL; it does not return the shared CHIM log. Removing the plugin stops future changes to affinity but does not undo affinity or history already stored by CHIM.

## What it shows

The dashboard separates a model-proposed affinity delta, a commit-confirmed applied delta, historical before/after values, and the current affinity read at query time. The v0.1.20 type display has passed isolated fixture checks; it shows proposed type decisions, commit-confirmed type changes, and the current type read at query time separately. A proposal does not establish application. `type_decisions` records `{subject, from, to, reason}` with fixed reasons `change_eligible`, `same_type`, `protected_type_preserved`, or `custom_type_preserved`. `type_changes` contains only confirmed transitions as `{subject, before, after}`. For older ledger entries that lack an explicit decision reason, a valid recorded proposal may appear without a reason; malformed or unknown optional type-history entries are skipped. A proposal is not evidence of application. An unconfirmed commit is labeled **Unconfirmed**; it is not shown as no change. An unset relationship explicitly defined as default-zero is shown as `0 (default)`. Ambiguous, invalid, and unavailable values remain distinct.

`changed_count` counts distinct relationships changed by affinity or type; `affinity_changed_count` and `type_changed_count` separate those outcomes. A confirmed type-only change has a positive `changed_count` even when its affinity delta is zero. The existing `changes` array retains its affinity fields.

The v0.1.7 dashboard recognizes explicitly marked Player-origin records, displays the speaker as **Player**, and labels `input_<rowid>` correlation values as **Input event**. It never infers Player from a missing speaker ID. Published v0.1.6 assets remain NPC-origin only.

The v0.1.12 candidate recognizes `source_kind=reflection` and attributes a confirmed opinion to its corroborated NPC owner. It labels the entry **Solo reflection** and does not invent a listener or render an actor-to-self conversation. Early failures without a validated actor remain unattributed reflection diagnostics; inconsistent actor identities are rejected. Reflection logs and the dashboard omit raw speech, the subtitle digest and the evidence-basis fingerprint.

When the database source is unavailable, retained log records can still appear as unverified or unattributed history; current affinity is unavailable. The page does not infer active-profile attribution from those records. In v0.1.8, database-backed reads with no active CHIM Playthrough Saves profile use the explicit shared `unprofiled` scope. Exactly one active profile retains numeric isolation; multiple, invalid, or ambiguous states remain unavailable. With CHIM auto-switch Off, this scope does not identify a particular Skyrim save; different saves sharing the same server database are not automatically isolated. Diagnostic details show sanitized allowlisted plugin fields, not raw speech, prompts, credentials, or arbitrary exception/provider text.

## Read limits

- Log input is limited to the last 2 MiB and 1,000 parsed plugin records. The UI may report that older records are missing. The CHIM log itself can be truncated by CHIM; this view does not provide an archival audit trail.
- Database reads use a separate read-only transaction. The reader uses the guarded `sql::$connString` compatibility seam after confirming the expected CHIM class file and private property; it does not instantiate `sql` or run the CHIM bootstrap. If a future core version changes that shape, database data is shown as unavailable. The reader caps the NPC catalog at 1,000 rows and selects at most 100 listeners ordered by their newest valid retained event ID. This is a recent-listener sample, not an exhaustive query of global history. Each stored ledger is capped at 64 KiB and each relationships JSON value at 16 KiB. The interaction list is capped at 200 entries.
- Current affinity and type values are query-time reads. They can differ from the value immediately after a historical event.
- Local fixtures and an isolated PostgreSQL synthetic-schema selection check do not prove live CHIM database behavior or concurrency, remote authentication, provider calls, client ACK behavior, or in-game behavior. The v0.1.20 type change has no live/gameplay verification.

## Local preview

Run the view against an explicitly labeled synthetic fixture from the repository root:

```sh
php -S 127.0.0.1:8080 -t server tests/dashboard_preview.php
```

Open `http://127.0.0.1:8080/dashboard.php`. Append `?theme=night` for Night mode. Fixture-only states are available with `?preview=empty`, `?preview=database-unavailable`, and `?preview=logs-unavailable`; no CHIM bootstrap, database, or provider is used. Focused checks are `php tests/dashboard_preview.php --self-test`, `php tests/dashboard_data_test.php`, `php tests/dashboard_http_test.php`, and `php tests/dashboard_integration_test.php`. `node tests/dashboard_refresh_test.js` simulates the polling timer, request failure/timeout, and stale hidden-tab response; it is not a browser test. These checks use synthetic or isolated temporary fixtures only; none contacts a live CHIM service.
