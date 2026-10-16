# Independent duplicate-install review

Reviewed the shared duplicate-install changes read-only: the installation guard, all seven hook entrypoints, JSON hook registration, the UI/diagnostics gates, package inclusion, and the duplicate-install fixtures.

## Review result

No additional actionable product defect remains in the requested scope. The guard is a declaration-free static IIFE; hooks gate before loading their modules or registering effects; JSON customizer declarations and registration are conditional on ownership. The UI calls `pcv_run_page()` only after the ownership check, so GET, refresh, Logs, and POST processing cannot reach session, logging, or state work from an inactive copy. Diagnostics checks ownership before loading the log reader and reports the elected extension path.

The fixture covers generated Plugin Manager identity/full-tag versions, both hook load orders for new/new and mixed legacy/new installs, repeated inactive-hook inclusion, owner ARM/read/END against the owner-derived store, and inactive HTTP refresh/Logs/POST refusals while checking a seeded owner-state sentinel. The package allowlist includes the guard.

## Review corrections

I found two fixture assertion issues during review and notified the implementation owner. The diagnostics assertion initially looked for refusal text on stdout even though diagnostics writes it to stderr; it now requires exit 2, empty stdout, the legacy refusal, and the exact `ext/<owner>` path on stderr. An early coordinated fixture attempt stopped at a PHP parse error caused by apostrophes in three single-quoted assertion messages; those messages were changed to double-quoted strings. The next attempt reached the store-path check but used a scratch layout that made `TMPDIR` itself the inferred public webroot, so the application correctly rejected a private default store nested beneath it. The fixture layout was then adjusted to place the extension below a separate webroot.

## Verification

Root's coordinator reports the corrected duplicate fixture passed with exit 0. I examined the saved stdout at `tasks/release-0.1.20/runtime-evidence/duplicate-green-layout.stdout.txt` and the empty stderr at `tasks/release-0.1.20/runtime-evidence/duplicate-green-layout.stderr.txt`. The output includes PHP syntax success and passes for generated-manifest single ownership, new/new load-order stability, both mixed .19/new placements, ambiguous metadata refusal, and inactive refresh/Logs/POST refusal without modifying the seeded owner state.

This is focused fixture evidence, not an installed-server or game-session result. The fixture checks real owner state ARM/read/END APIs and separately checks that the inactive copy's actual HTTP routes refuse before touching state; it does not demonstrate a complete successful owner UI page against a running CHIM installation. I did not run PHP or WSL myself.
