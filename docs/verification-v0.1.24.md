# Mind Poisoning 0.1.24 — verification and limits

## Status

This candidate clarifies `restore-policy` in the dashboard and player guide. The restore guard, evaluator APIs, schema, and JSONL exporter are unchanged. The lead ran the scoped checks against the staged 0.1.24 source; they passed. This page records those staged-source checks; it does not substitute for separate clean-tag rebuild and downloaded-asset byte-comparison publication gates or establish installed-package, provider, live-database, or gameplay behavior.

| Check | Status |
| --- | --- |
| `dashboard_restore_policy_test.php`: render reason help in **Interactions** and **Logs** | Passed |
| PHP syntax checks for `server/dashboard_view.php` and `tests/dashboard_restore_policy_test.php` | Passed |
| `manifest_update_check.php` and `site_runtime_compatibility_test.php` against the existing pinned local core | Passed |
| Eight release-packaging cases | Passed |

The PHP fixtures used standalone Windows PHP with `-n`, `mbstring`, and `pgsql`; the compatibility helper read only the pinned local core source. The fixtures made no network, database, or provider calls. Source review confirms the filtered JSONL exporter and filter implementation are unchanged; this was not a live export check. All four candidate installer assets (`mind_poisoning-0.1.24-mo2.zip`, `mind_poisoning-0.1.24.dwpkg`, `mind_poisoning.tar.gz`, and `chim-plugin.tar.gz`) were built and verified locally. The recorded results are in `artifacts/mp-release-0.1.24/focused-checks.json`, the individual `.txt` outputs, and `asset-inventory.json`.

The checks are scoped to the display and release-metadata change. No unrelated evaluator test reruns were needed because the evaluation guard and behavior were not changed. A clean-tag export/rebuild and downloaded-asset byte comparison are separate publication gates; the staged-source results here do not establish them or an installed CHIM package.

## Restore-policy interpretation

Read a record’s **Outcome**, `stage`, and `model_outcome` together. A preflight `not_called` stop means MP stopped before model work. The persistence backstop can report the same reason after model work. The row records a historical event; it does not prove the setting’s current effective value or identify its UI location/default. The CHIM restore behavior that motivates the guard can retain newer relationship values while restoring older Mind Poisoning processing history, risking a repeated effect. The dashboard explanation is display-only and preserves the original reason code and filtered JSONL record.

The supplied 0.1.23 support export and source trace establish only the observed records and inspected guard behavior for that case. They do not establish the tester’s current setting, exact CHIM build, provider health, or results after a policy change. A zero change or another legitimate skip is not a failed candidate check. Any deliberate setting change requires administrator review and database/configuration backups.

## Not established by this record

- Standard release classification and topic-discovery support do not establish a complete 0.1.24 topic-discovery install.
- No 0.1.24 package installation, live CHIM database write, provider result, or Skyrim gameplay outcome is claimed.
- The earlier 0.1.23 focused PHP checks and 0.1.20 B1/B3 PostgreSQL and install campaigns remain evidence for those versions and paths only.

The release remains **BETA CANDIDATE**. Standard GitHub classification (`prerelease=false`) is retained for CHIM topic discovery; it does not change the maturity label or replace the separate publication gates above.
