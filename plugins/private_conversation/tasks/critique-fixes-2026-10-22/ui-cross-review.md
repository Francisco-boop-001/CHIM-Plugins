# Task 3 UI/security cross-review

Review is limited to the Task 3 UI and diagnostics-access changes in the shared PCV checkout. The task plan’s later ruling removes automatic WSL gateway admission because the server cannot prove the original caller across NAT; loopback and server-authenticated `REMOTE_USER` remain the supported paths.

## Verdict

Approved within that boundary. I found no actionable defect in the reviewed page copy or logs access path.

`pcv_ui_logs_access_allowed()` in `server/index.php:589-600` admits exact IPv4/IPv6 loopback only when the listed forwarding headers are absent, or a nonempty server-supplied `REMOTE_USER`. The logs route calls this gate before requiring the log reader or starting its diagnostics session (`server/index.php:778-785`); denied requests log a bounded rejection, return HTTP 403, and render the locked page. Dispatch to that route occurs before the ordinary UI session path (`server/index.php:1022-1024`). The HTTP fixture also exercises filtered reads, export, CSRF rejection, forwarding headers, and a client-supplied `Remote-User` combined with `X-Forwarded-For`; the captured final run passed.

The host-address regression rejects private gateway-like `REMOTE_ADDR` values and retains loopback and server-authenticated-user cases (`tests/ui_logs_host_access_check.php:19-30`). The old automatic `/proc/net/route` admission code is absent from the production gate. The locked page explains that private gateway addresses are not trusted and does not promise automatic Windows-host access (`server/index.php:672-683`).

The cap and voice copy matches the selectable behavior: the server validates `free_cap` as 2–6 (`server/index.php:76-82`), the page label states “2–6 nearest eligible NPCs; player excluded” (`server/index.php:520`), the pending summary uses the stored bounded cap and states the two-person minimum (`server/index.php:342-343`, `382-383`), and the client intro follows the selected cap while the non-free copy says two to four voices (`server/assets/ui-refresh.js:167-177`, `422`).

## Retained evidence

- `runtime/logs-http-final-green.output.txt` / `.exit.txt`: HTTP diagnostics checks passed; exit 0.
- `runtime/logs-access-final-green.output.txt` / `.exit.txt`: loopback/authenticated-user gate and refusal-page checks passed; exit 0.
- `runtime/free-ui-green.output.txt` / `.exit.txt`: Free page/cap fixture passed; exit 0.
- `runtime/ui-refresh-green.output.txt` / `.exit.txt`: all 14 UI refresh tests passed; exit 0.

The earlier `runtime/logs-http-green.*` receipt is an exit-255 status-copy assertion failure and is superseded by the final green receipt after the locked-page heading correction. I did not run or alter any tests.

## Limits

These fixtures verify the code path and the test server’s header mapping; they do not prove that a deployed web server sets `REMOTE_USER` only after authentication. The deployment must preserve that trust contract. Because loopback identifies the immediate peer, a local reverse proxy that forwards unauthenticated traffic without a forwarding marker is indistinguishable from a direct loopback client; configure such a proxy to authenticate and set `REMOTE_USER` or expose no unauthenticated route. The private-address checks are useful regressions, not proof of every Windows/WSL/container topology.

The gateway unit test retains one legacy-shaped call with extra arguments (`tests/ui_logs_host_access_check.php:23-25`). The current one-argument PHP user function ignores those extras; the actual production route calls it with only `$_SERVER`. The remaining assertions still cover private-address rejection, and the captured HTTP fixture covers the routed 403 behavior.
