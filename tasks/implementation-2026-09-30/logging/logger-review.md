# PCV logger and shared reader review

The logger now records instrumentation revision 2 and uses fixed event/outcome/reason rules for both writes and reads. Correlation is limited to validated event, utterance, and linked MP request identifiers; unknown fields, raw speech, hashes, and arbitrary model or exception detail are excluded. The optional MP importer keeps source reasons on a finite allowlist and maps source severity independently from the PCV summary severity, including uncertain commits and warning-level pause-control skips. When the installed MP logger has no observer API, PCV records `reflection.observer_unavailable` without implying evaluation failure.

`pcv_diagnostics_load()` is the shared web-safe reader. It returns sanitized matching entries and storage/read health without a log path. The reader validates schema 1, keeps legacy rows without `logging_revision`, distinguishes unsupported revisions from malformed rows and filter/cap counts, and never treats filtered entries as data loss. It opens the five bounded JSONL segments and captures their sizes under the shared lock, releases the lock before parsing, and closes handles on every failure path. A non-regular segment is rejected before `fopen`, so a FIFO cannot block the reader. CLI output exposes storage mode, write verification, bounded-history status, and separate corruption/filter/cap counts.

The request terminal logger now labels the postrequest hook as `postrequest_observed`, not completed speech or playback. Earlier error/block outcomes survive later lower-severity observations; fatal shutdown metadata is limited to basename, line, and error type. Missing presence, an initial ordering baseline, measured staleness, and an empty report remain separate outcomes. The package server allowlist includes the new reader without changing manifest or release versions.

## Verification

- `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/log_check.php` — exit 0; `PASS: schema/redaction, IDs, debug window, five-segment rotation, private permissions, concurrent JSONL, unsafe-path rejection, lock fallback`. The fixture also checks normal registered shutdown, fatal shutdown metadata/redaction, uncertain-commit error mapping, pause-control warning source reason, and sticky terminal failure.
- `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/diagnostics_check.php` — exit 0; `Diagnostics checks passed.` It covers legacy schema-1 projection, malformed/oversized/unsupported rows, request/config/event/severity/correlation filtering, snapshot parsing after lock release, a bounded named-pipe rejection, CLI export, and the HTTP 404 guard.
- `python tests/package_check.py` from the PCV project directory — exit 0; 4 tests, `OK`.
- PHP syntax checks — exit 0, `No syntax errors detected` for `server/log.php`, `server/log_reader.php`, `server/diagnostics.php`, `tests/log_check.php`, and `tests/diagnostics_check.php`.

These are isolated source and packaging checks. No installed CHIM request, provider call, database transaction, or playback behavior was exercised. Default temporary storage and bounded rotation do not promise durable or complete history; only a configured safe external `PCV_LOG_DIR` provides operator-selected placement.

## Diagnostics rejection event addendum

Added fixed `ui.diagnostics_rejected` warning events for browser access denial, CSRF failure, invalid filters, or invalid failure-code input. Writer and reader require a fixed operation (`logs_read`, `log_export`, or `browser_report`) and `source=browser`; unrelated reasons and operation/source pairs are rejected. Projection retains only the allowlisted context fields.

- Isolated PHP rule/projection check — exit 0; all four reasons and three operations accepted, unrelated reason and operation/source rejected, and sanitized reader projection passed. It was run as `wsl -d DwemerAI4Skyrim3 -- php -r '<check below>'`:

```php
require "/mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/server/log_reader.php";
if (!pcv_log_rule_matches("ui.diagnostics_rejected", "warning", "rejected", "access_denied")
    || !pcv_log_rule_matches("ui.diagnostics_rejected", "warning", "rejected", "csrf_failed")
    || !pcv_log_rule_matches("ui.diagnostics_rejected", "warning", "rejected", "invalid_filter")
    || !pcv_log_rule_matches("ui.diagnostics_rejected", "warning", "rejected", "invalid_failure_code")
    || pcv_log_rule_matches("ui.diagnostics_rejected", "warning", "rejected", "internal_error")) exit(1);
if (!pcv_log_event_context_valid("ui.diagnostics_rejected", ["operation" => "logs_read", "source" => "browser"])
    || !pcv_log_event_context_valid("ui.diagnostics_rejected", ["operation" => "log_export", "source" => "browser"])
    || !pcv_log_event_context_valid("ui.diagnostics_rejected", ["operation" => "browser_report", "source" => "browser"])
    || pcv_log_event_context_valid("ui.diagnostics_rejected", ["operation" => "state_read", "source" => "browser"])) exit(2);
if (pcv_diagnostics_project_entry(["schema_version" => 1, "logging_revision" => 2, "plugin_version" => "0.1.4",
    "timestamp" => "2026-09-30T12:00:00.123Z", "event" => "ui.diagnostics_rejected", "severity" => "warning",
    "outcome" => "rejected", "reason" => "invalid_filter", "request_id" => "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
    "config_id" => null, "playthrough_ref" => null, "elapsed_ms" => 1,
    "context" => ["operation" => "state_read", "source" => "browser"]]) !== null) exit(3);
if ((pcv_diagnostics_project_entry(["schema_version" => 1, "logging_revision" => 2, "plugin_version" => "0.1.4",
    "timestamp" => "2026-09-30T12:00:00.123Z", "event" => "ui.diagnostics_rejected", "severity" => "warning",
    "outcome" => "rejected", "reason" => "invalid_filter", "request_id" => "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
    "config_id" => null, "playthrough_ref" => null, "elapsed_ms" => 1,
    "context" => ["operation" => "log_export", "source" => "browser", "raw" => "must_not_project"]])["context"] ?? null)
    !== ["operation" => "log_export", "source" => "browser"]) exit(4);
```

- `php -l server/log.php` — exit 0; no syntax errors.
