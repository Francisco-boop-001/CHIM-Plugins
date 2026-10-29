# PCV 0.1.25 source metadata and Department pairing boundary

## Manifest bump

Baseline canonical HEAD: `0c697793311b314e8158b122e3c2b718638ca9e0` (published PCV 0.1.24 source is `ce0a998773237a9fc91f6914725a327d9e621718`). The only change in my owned metadata file is `server/manifest.json` version `0.1.24` → `0.1.25`.

- Before SHA-256: `929a0b67050c3bab4ab1362f34997651896e07f9c8cd491a6e45fb7d9dca1ff0` (committed baseline blob bytes).
- After SHA-256: `198a242e4e85cd418aca588fd7583e1643437ffe069c3eaa6070b53d2eff4a3c`.
- Size remains 1026 bytes. Encoding is UTF-8 without BOM, with 24 LF and 0 CRLF. `git diff --check -- server/manifest.json` returned exit 0.
- `php.exe -n -r 'json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)' server/manifest.json` equivalent validation passed and confirmed version `0.1.25`; raw output/exit are `manifest-json-check.output.txt` and `.exit.txt`.
- Schema 2, candidate channel/default, package URL template, status, and CHIM compatibility reference are unchanged. No builder, package layout, docs, or Department files were edited.

The focused source behavior RED/GREEN for the accepted fixes predates this metadata-only edit and is retained in `tasks/bugfix-2026-10-28/ui/`; the manifest version change does not alter those PHP/JavaScript files. Candidate build and package gates are owned by C and remain pending their receipts.

## Department compatibility is not advanced

Department's current README says its prealpha.24 source pairing retains PCV 0.1.24. Its `pcv-0.1.24-source-manifest.json` pins `private_conversation-v0.1.24` at `ce0a998773237a9fc91f6914725a327d9e621718` and stages the seven owner files from `pcv-companion/private-conversation-v0.1.24/server`. The provenance lists owner integration changes in `preprocessing.php`, `scope.php`, and `state.php`; the PCV 0.1.24 compatibility note also calls for the exact 0.1.24 pairing.

PCV 0.1.25 source now changes overlapping `scope.php`, `context_pre.php`, and `log.php` seams. In particular, `context_pre.php` passes the player name to `pcvPrepareSceneActions`; the 0.1.24 companion `scope.php` still declares the one-argument function and retains the previous wrap-selection logic. The 0.1.25 `log.php` allows `playthrough_changed` specifically for `ui.scope_stage_rejected`; the companion's 0.1.24 `log.php` event-specific allowlist does not. If the Department overlay is used with 0.1.25 owner files, its overlay can restore the old helper behavior and omit the new structured reason. This source comparison does not establish whether any wider runtime failure occurs.

Therefore this standalone PCV 0.1.25 release does not qualify the existing Department prealpha.24 / PCV 0.1.24 pairing for 0.1.25. The Department owner must rebase its pinned overlay and run its separate compatibility gates before advertising that pairing. No Department source, package, pin, or installed environment was changed or tested here.

Read-only anchors: Department `README.md:11`, `docs/PCV24-COMPATIBILITY.md:1-14`, `packaging/companion-only/pcv-0.1.24-source-manifest.json:3-8`, and `packaging/companion-only/PCV-PROVENANCE.md:3-5`; PCV 0.1.25 `server/context_pre.php:104`, `server/scope.php:1054-1065,1237-1253`, and `server/log.php:353` versus the staged 0.1.24 overlay copies.
