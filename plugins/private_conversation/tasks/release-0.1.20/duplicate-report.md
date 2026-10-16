# Duplicate-install safety report

## Behavior and caller map

`server/installation_guard.php` makes one cached owner decision for the canonical `ext` root. It is a declaration-free immediately invoked closure, so including it from CHIM's recursive loader does not overwrite loader locals. The decision cache lives only in namespaced `$GLOBALS` keys. Hooks use `require` on every invocation and return before loading modules or registering side effects when their copy is inactive.

The guard covers the seven game hooks in the core's phase order: `preprocessing.php`, `prerequest.php`, `json_response_custom.php`, `context_pre.php`, `context.php`, `prepostrequest.php`, and `postrequest.php`. JSON-template function declarations and registration are conditional on ownership because PHP compiles unconditional function declarations before executing a top-level guard. `index.php` checks ownership before dispatching any page route; an inactive copy returns HTTP 409 before logger/state modules load. CLI `diagnostics.php` refuses with exit 2 on stderr before loading the log reader; direct HTTP access keeps its existing 404 behavior.

Candidate identity accepts the package's internal `name: private_conversation` manifest or CHIM Plugin Manager's generated form: `name` equals the physical folder name, `git_repo` is exactly `Francisco-boop-001/CHIM-PrivateConversation`, and `source` is `github-release`. Numeric release versions and generated `private_conversation-vX.Y.Z` tags are supported. A known PCV folder with missing, malformed, unreadable, or unsupported metadata fails closed.

Completed installs elect a legacy pre-0.1.20 copy first, then `private_conversation`, then `CHIM-PrivateConversation`, then the lexically sorted canonical path. A mixed 0.1.19/0.1.20 install therefore leaves the old copy active and returns 409 from the new copy with the old folder named. No state is migrated, opened, or taken over by the new copy. Remove the duplicate before using the new copy. This does not make two unguarded old copies cooperate, and it does not cover transient staging/previous folders during a concurrent Plugin Manager install.

## Sources and fixtures

The legacy fixture under `tests/fixtures/duplicate-install/legacy-0.1.19/server/` is the exact server tree exported from source commit `c7ec6cde8231ebb103ef7d443b09789eb4b29691`. The current checkout baseline was `b7030f259bf25101303f56c03090a060290992a5`. The archived .19 source is unchanged; only temporary installed-copy manifests are converted to the generated format, with the full tag derived from each copy's original numeric version.

`tests/fixtures/duplicate-install/plugin-manager-generated-identity.json` records the generated identity contract observed in CHIM `chimPluginGeneratedManifest()` (`lib/plugin_discovery.php`, lines 1029–1075). The fixture exercises generated identity in both old/new folder placements and a generated-manifest single install where that copy must be elected owner.

The duplicate fixture copies actual payloads into a completed `webroot/HerikaServer/ext/<folder>` layout. It loads all seven hooks phase-major in both folder orders, preserves CHIM recursive-loader locals, repeats the non-owner's hooks in the same process, and checks that only the owner registers `JSON_TEMPLATE` and provides the loaded state/scope functions. It then calls the actual owner `pcv_stage()` ARM, `pcv_read()`, END, and readback functions. Reflection and the default path hash verify that the real state store belongs to the elected physical copy; the non-owner's store root must remain absent.

The owner state exercise is compositional proof through the actual hook-loaded state API, not an ARM/END POST through the owner browser page. The inactive page boundary is checked separately through real loopback HTTP: POST `/index.php`, GET `/index.php?refresh=1`, and GET `/index.php?view=logs` all return 409. A seeded legacy-owner state sentinel remains byte-for-byte unchanged and the inactive copy creates no default store. A full CHIM database/catalog bootstrap is not included because the refusal must happen before that bootstrap.

## Verification

The coordinator ran `php -l tests/duplicate_install_check.php` and the duplicate fixture with task-owned `TMPDIR=/tmp/pcv-020-checks.YDvK5l`. Lint returned 0 and the fixture returned 0. Retained final evidence is in `tasks/release-0.1.20/runtime-evidence/duplicate-green-layout.stdout.txt` and `duplicate-green-layout.stderr.txt`.

The final output confirms the inactive HTTP routes refused twice without changing owner state; generated-manifest single-owner election passed; new/new election was stable in both hook orders; exact .19/new mixes deferred to the legacy copy in both folder placements and hook orders; and ambiguous known-path metadata failed closed. The final stderr file is empty.

Earlier harness failures are retained for audit: `duplicate-red.stderr.txt` records the original duplicate-function fatal (`pcv_scope_name_key`); `duplicate-green.stderr.txt` records the fixture's apostrophe parse error; and `duplicate-green-corrected.stderr.txt` records the first store-path assertion failure. That last failure came from a synthetic `TMPDIR/ext/<copy>` layout causing the logger to infer `TMPDIR` as public webroot. The fixture was moved to `TMPDIR/webroot/HerikaServer/ext/<copy>` to match CHIM's actual topology; production logger/state code and storage rules were not weakened.

The coordinator also ran the changed-entry regressions: `page_check.php` (7 assertions), `scope_check.php` (6 assertions), `postrequest_terminal_check.php`, and `turn_hooks_check.php` passed. The page fixture now uses `TMPDIR/webroot/HerikaServer/ext/private_conversation`, with logs and sessions outside the webroot, matching CHIM's inferred public-root boundary. Retained regression output is `tasks/release-0.1.20/runtime-evidence/guard-regressions-corrected.stdout.txt` and `guard-regressions-corrected.stderr.txt`.

`php -l` passed for the 11 changed product PHP files; the guarded ACK shutdown fixture also parsed successfully. Its database behavior was not run: the fixture refused before connecting because the guarded disposable PostgreSQL host/port/database/user settings were not supplied. The postrequest regression emitted one `directory_unavailable` logger fallback after its PASS; its trigger was not established, so this is recorded as an unattributed observation, not claimed as a new regression or a baseline behavior. Final syntax and turn-hook output is retained in `tasks/release-0.1.20/runtime-evidence/guard-final.stdout.txt` and `guard-final.stderr.txt`.

This evidence is PHP fixture and loopback HTTP proof. It does not establish installation in a live CHIM server, gameplay behavior, provider behavior, or handling of concurrent/transient package-manager operations.
