# Stale PCV page binding

## Result

ARM and END now require the opaque playthrough reference rendered with that form. The controller compares it with the reference derived from the current server key; it never uses the submitted value as a state key. A missing, malformed, or mismatched reference is rejected before `pcv_stage()` with HTTP 409, a fixed `playthrough_changed` event reason, and a refresh notice. CSRF remains first and retains its existing HTTP 400; a missing current key still reaches the existing HTTP 503 refusal.

The refresh script validates that all returned hidden references match the snapshot identity before changing the page, then updates both hidden references with the CSRF fields and visible snapshot. Identity changes clear actor, Solo, and Free drafts; same-identity refreshes retain local drafts.

## Source and callers

- [index.php](/K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/index.php:86): shared CSRF check and strict 64-character lowercase-hex reference comparison. The existing form parser signature is unchanged. Both forms render the escaped opaque reference at lines 532 and 567. The POST controller checks CSRF, then the reference, before desired-state parsing and staging at lines 1127–1137; mismatch handling returns 409 at lines 1174–1177.
- [log.php](/K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/log.php:353): admits the existing fixed `playthrough_changed` reason for `ui.scope_stage_rejected`.
- [ui-refresh.js](/K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/assets/ui-refresh.js:272): rejects incomplete or identity-inconsistent reference fields before mutating the snapshot; lines 287–308 clear identity-specific drafts, and lines 350–353 update both hidden references and the body reference.
- `pcv_run_page()` is reached by the production page entry and the isolated page fixture. Other tests call only the pure form parser, whose API remains unchanged; no non-browser page POST caller was found.

The browser can submit a page rendered for profile A after the server has switched to B, including while the tab is hidden or before its next poll. Previously the page reference was only a body data attribute, so the controller reparsed the old actor IDs against current data and staged them using B's server-derived key. The submitted opaque reference closes that request-snapshot gap. This does not claim a transactional lock against a profile switch concurrent with the same request.

## Verification

Expected-behavior RED was captured before product edits:

- `C:\nodejs\node.exe --test K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tests\ui_refresh_check.mjs` — exit 1; 14 passed and the two new hidden-reference consistency / ref-refresh cases failed. Raw output: `ui-refresh-red.output.txt`.
- `K:\ActorwrightExchange\artifacts\php-8.3.35-windows-cli\runtime\php.exe -n K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tests\stale_page_reference_check.php` — exit 1. The actual-controller fixture reported missing form refs, accepted stale ARM and END, and staged END without a reference. The CSRF, unknown-key, and valid-control assertions passed. Raw output: `stale-page-red.output.txt`.

Focused GREEN on the final source:

- `C:\nodejs\node.exe --test K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tests\ui_refresh_check.mjs` — exit 0; 16 passed, 0 failed. Raw output: `ui-refresh-green.output.txt`.
- `K:\ActorwrightExchange\artifacts\php-8.3.35-windows-cli\runtime\php.exe -n -d "extension_dir=K:\ActorwrightExchange\artifacts\php-8.3.35-windows-cli\runtime\ext" -d extension=php_mbstring.dll K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tests\stale_page_reference_check.php` — exit 0. This copies and exercises the production `index.php`, installation guard, and `scope.php`, with test-only state/logger adapters and a minimal runtime-config bootstrap. It covers refs rendered in both forms, stale ARM/END, missing/malformed END refs, CSRF precedence, unknown current identity, valid same-profile ARM, and END without an eligible roster. Raw output: `stale-page-green.output.txt`.
- PHP `-n -l` passed for `server/index.php`, `server/log.php`, `tests/page_check.php`, and `tests/stale_page_reference_check.php`; raw output: `php-lint-green.output.txt`.
- The following side-effect-free command against production `log.php` passed without initializing the logger or its private directory; raw output: `log-rule-green.output.txt`.

  ```powershell
  K:\ActorwrightExchange\artifacts\php-8.3.35-windows-cli\runtime\php.exe -n -r 'require "K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/log.php"; if (pcv_log_rule_matches("ui.scope_stage_rejected", "warning", "rejected", "playthrough_changed")) { echo "PASS: production logger accepts playthrough_changed for UI scope rejection.\n"; } else { exit(1); }'
  ```
- `git diff --check` passed for the scoped tracked files.

The expanded real-logger `tests/page_check.php` was attempted with this command but did not reach its route assertions:

```powershell
K:\ActorwrightExchange\artifacts\php-8.3.35-windows-cli\runtime\php.exe -n -d "extension_dir=K:\ActorwrightExchange\artifacts\php-8.3.35-windows-cli\runtime\ext" -d extension=php_mbstring.dll K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tests\page_check.php
```

It stopped with `Could not activate fixture logger` (exit 255). This runtime has no POSIX extension, and the production fixture gate depends on `posix_geteuid()` plus private mode bits. Treat that integrated route run as **unperformed**, not passing. The focused actual-controller fixture avoids that host limitation with a test-only logger adapter and does not weaken production logger checks. The host also did not exercise a browser, live server, or game.

## Changed files

- Product: `server/index.php`, `server/assets/ui-refresh.js`, `server/log.php`.
- Tests: `tests/page_check.php`, `tests/ui_refresh_check.mjs`, and new `tests/stale_page_reference_check.php`.
- Evidence: this report and the raw output/exit receipts in this directory.
