# Pin 2 UI diagnostics review — 2026-09-30

## Result

Added a protected `?view=logs` route to the PCV server UI. The route executes before PCV state, CHIM configuration, NPC catalog, and database loading. It uses the accepted server access gate: direct `127.0.0.1` / `::1` without any forwarding headers, or a nonempty server-authenticated `REMOTE_USER`. It does not trust caller-supplied identity or address headers. Scene ARM/END access remains unchanged.

Authorized operators get a manual filtered read/export form. Reads use `pcv_diagnostics_load()` and render only validated projections with escaped output; JSONL downloads use a fixed content type and filename. The viewer identifies UTC timestamps, bounded-history completeness, separate corruption/unsupported and filtered/capped counts, and storage/write health for the current request only. Busy or unavailable exports return a safe error status rather than a successful empty download; partial exports report fixed completeness and omission headers.

Refresh errors submit only `timeout`, `network`, `http`, `invalid_response`, or `unknown`, with the current session CSRF token. Browser reports are rejected unless their fields are exact, stored once per session/code per 60-second window, and never include error text, response bodies, URL, or stack data. Client-side deduplication resets after a successful eligible refresh, and telemetry delivery failures are contained without retrying.

## Red/green and verification

- Before the route implementation, the isolated HTTP test failed as expected with exit 1: `The standalone Logs route must load before CHIM configuration, catalog, or database dependencies.` After implementation it passed.
- `wsl -d DwemerAI4Skyrim3 -- php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/server/index.php` — exit 0, `No syntax errors detected`.
- `wsl -d DwemerAI4Skyrim3 -- php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/ui_check.php` — exit 0, `No syntax errors detected`.
- `wsl -d DwemerAI4Skyrim3 -- php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/ui_diagnostics_http_check.php` — exit 0, `No syntax errors detected`.
- `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/ui_check.php` — exit 0, `UI checks passed.` Covers the exact access predicate, strict filter validation, escaping/redaction, and retained scene controls.
- `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/ui_diagnostics_http_check.php` — exit 0, `HTTP diagnostics checks passed.` Runs PHP’s local test server with both log storage and session files in a private temporary directory; covers dependency-free early routing, session CSRF, forwarded/forged-header denial, filtered reads and exports, redaction, fixed rejection events, duplicate-report suppression, limited-export headers, and busy/unavailable export failures.
- `node --test tests/ui_refresh_check.mjs` — exit 0, 8 tests passed, 0 failed. Covers fixed failure codes, CSRF, async and sync report-delivery failures, one report per failure episode, a new episode after successful refresh, fail-closed ARM, and preservation of eligibility/drafts.

## Limits

The HTTP checks use only an isolated local PHP server and temporary fixtures. They do not exercise installed CHIM, a production reverse proxy, Mind Poisoning provider/database behavior, or in-game playback. Remote operators need a trusted web-server `REMOTE_USER`; forwarded client IP headers alone never authorize the Logs route. History remains bounded and current-request `write_status` does not certify historical durability. The page can show PCV’s sanitized reflection/integration events when present; it does not merge a separate Mind Poisoning plugin log source.

No mirror, package, version, or release files were changed.
