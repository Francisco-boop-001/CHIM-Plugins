# Standalone dashboard affinity fix — 2026-10-15

## Result and scope

Fixed locally after reproducing the cause. No live HTTP, WSL commands/services, CHIM/provider/database calls, game-data changes, installation, commit, publication or release-pin advancement. Existing unrelated dirty docs/tasks/PCV work was preserved. Product changes were made by dashboard_affinity_fix; elsa_reflection_trace and lead reviewed the affected paths and verification output.

## Cause verified before product changes

The standalone dashboard did not initialize ENGINE_PATH or load RelationshipManager. Its Player-edge reader calls canonicalStoredRelationshipMap; without either the class or the runtime engine path, the shared helper returned null, which dashboardCurrent displayed as Invalid. The ordinary game request bootstraps those dependencies, so gameplay writes can succeed while the separate dashboard misreports a valid edge.

Existing dashboard_data_test.php preloads the pinned core fixture, masking this entrypoint difference. A new fresh PHP -n process, temporary synthetic server root and no ambient globals reproduced a valid Player affinity47 as {value:null,state:invalid} before any product edit. Actual RED output is saved in K:/ActorwrightExchange/artifacts/dashboard-affinity-fix-20261015/red-before-fix.txt. The first wrapper did not capture PHP's exit code; the uncaught assertion is recorded without inventing one.

## Minimal implementation

- server/dashboard_data.php:57 loads the optional relationship helper from the dashboard's explicit realpath-validated server root, with containment/readability checks and contained include failures.
- dashboardCurrent keeps using the existing shared core-backed normalizer. A missing/incompatible helper produces unavailable; genuinely malformed/out-of-range affinity still produces invalid.
- Shared influence/store/reflection evaluation code, NPC handling, authentication, read-only SQL and global ENGINE_PATH remain unchanged.
- New tests/dashboard_standalone_relationship_bootstrap_test.php covers the actual standalone bootstrap rather than preloading the dependency. Its fixture removes only known files and owned contained temporary directories, including setup failures.

## Executed verification

Independent portable Windows PHP8.3.35 CLI, always -n. Official archive checksum/source receipt is K:/ActorwrightExchange/artifacts/php-8.3.35-windows-cli/receipt.json. No system installation or PATH changes. No pgsql/curl extension is enabled; synthetic roots contain no SQL helper. Tests assert SQL was never loaded.

Passed (exit0):

1. php.exe -n tests/dashboard_standalone_relationship_bootstrap_test.php — canonical Player47, core-selected duplicate aliases50/1→50, malformed array/string/out-of-bounds values remaining invalid, unchanged NPC18, no leaked ENGINE_PATH/PLAYER_NAME, and separate fresh missing/malformed-helper processes yielding unavailable.
2. php.exe -n tests/dashboard_data_test.php — existing data/render behavior, including alias handling and unset values.
3. php.exe -n tests/dashboard_current_bounds_test.php — existing NPC affinity bounds.
4. PHP syntax checks on both changed files.
5. Scoped git diff --check on server/dashboard_data.php.

Lead independently ran the new regression, including the final test-fixture revision, with exit0. Owner GREEN outputs are green-focused-tests.txt; final file hashes and lead exit are lead-verification.json in K:/ActorwrightExchange/artifacts/dashboard-affinity-fix-20261015/.

## Final review and limits

No blocking product issue found. Review confirmed explicit helper loading, missing dependency versus bad data, shared-normalizer reuse, unchanged NPC and read-only/authentication paths, and isolated fixture cleanup. A minor setup-failure temporary-directory leak identified during review was returned to the same owner, corrected and rechecked. Existing trust in a preloaded core helper was retained rather than introducing a new reflection identity policy.

This verifies the cause and correction in isolated PHP against the pinned core fixture. It does not verify an installed update, browser rendering after deployment, a live database or game behavior. The live page will continue using its existing code until separately updated. Published version/pin remains0.1.19; local fix is unreleased.
