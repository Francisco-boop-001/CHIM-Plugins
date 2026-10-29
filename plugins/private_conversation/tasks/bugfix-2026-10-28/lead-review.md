# Lead review — confirmed PCV fixes, 2026-10-28

## Result

Three accepted PCV corrections are implemented locally. The lead wrote no product code. Independent review and lead acceptance are complete; no release, installed-runtime or gameplay claim is made.

Baseline HEAD remains `0c697793311b314e8158b122e3c2b718638ca9e0`; released runtime remains `ce0a998773237a9fc91f6914725a327d9e621718` (0.1.24). Prior task edits and historical evidence were preserved.

## Acceptance and call-path review

| Accepted gap | Correction and failure behavior | Decisive evidence |
| --- | --- | --- |
| Active Pair plus queued Solo turns wrap into Solo generation | `pcvWrapEndsScene` checks both active and pending scopes under active/pending status, before `pcvBeginResolvedScope`. Existing early-END branch stages disabled state, verifies Off and terminates; failed clear blocks without activating or claiming success. Pair-only closing generation is preserved. | Actual preprocessing hook fixture: original RED fails mixed-mode wrap and failed-clear assertions; final GREEN verifies one disabled/wrapped_up stage, unchanged Pair and queued Solo on failed write, zero activation calls, expected refusal; active Solo/pending Pair and pending Solo controls pass. |
| A stale browser page mutates another currently selected playthrough | Both forms submit the opaque rendered ref. Controller checks existing CSRF first, then matches the server-derived current ref before parsing/staging; missing/malformed/mismatched ref returns 409 with fixed reason and refresh notice. The submitted ref is never a state key. Refresh validates all hidden refs before updating its snapshot and clears actor/Solo/Free choices on identity change. | Actual copied controller fixture with state/logger adapters: ARM/END rendered for A are refused after switching to B, zero stage calls; invalid refs refused, invalid CSRF remains 400, absent current key remains 503; valid same-profile ARM and END without roster pass. JS RED 14/16, GREEN 16/16, including ref consistency and Free-draft reset/preservation. |
| Included player absent from offered Brawl targets despite output acceptance | `context_pre` passes its already validated local player name into action preparation; only non-Solo included-player scopes admit that name to the filter. Intersecting CHIM's original target list retains rather than invents targets. Output verdict uses the same identity via action_player. | Actual context hook/postfilter fixture retains the originally offered player and rejects bystanders; no-player-offered and excluded-player controls pass. Existing action hook checks preserve global-off, Solo limits and CHIM/SHARMAT gates. |

The lead inspected all changed product/test diffs, both complete new fixtures, every current preparer caller, the pre-activation wrap and failed-clear branches, page validation/staging/response branches, JS snapshot mutation ordering and the logger's event-specific reason allowlist. The independent reviewer requested stronger failed-clear assertions; the same routing owner added them and retained GREEN output. A stale hardcoded test ref was replaced with the readable hash derivation, and separate Solo POST fixtures were updated for the new form contract.

Historical bughunt drivers remain evidence for the old source. In particular the old action-offering driver uses the old one-argument private helper signature; use the new regression against current source rather than rewriting historical proof.

## Fresh lead verification

Exact commands, raw complete outputs and exits: [lead-verification.json](lead-verification.json). All invoked checks exited 0:

- `php.exe -n tests/routing_fix_hook_check.php`: actual hooks, mixed wrap priority, failure preservation, action-offering/exclusion controls.
- `php.exe -n tests/stale_page_reference_check.php`: actual controller boundary and valid/error controls.
- `php.exe -n tests/scene_actions_hook_check.php`: existing enabled-action, Solo and gated-action controls.
- `php.exe -n tests/in_game_end_hook_failure_check.php`: existing END failures/success and unarmed controls.
- `C:/nodejs/node.exe --test tests/ui_refresh_check.mjs`: 16 tests, 16 pass, 0 fail.

Eight changed/new PHP files passed `php -n -l`; actual JS passed `node --check`; `git diff --check` passed. Syntax evidence is in [lead-syntax.txt](lead-syntax.txt); syntax is not behavior proof. The UI owner separately verified the real production logger accepts the fixed playthrough_changed reason without initializing private storage.

## Limits and stopping rule

The executed PHP runtime is the explicit Windows host bundle, not a distro. State persistence, catalog/identity providers and core-refresh dependencies are mocked where named. The controller uses a local synthetic PHP session. These checks prove routing, validation and adapter calls, not real SQL writes, audio, native actions, browser rendering, gameplay or atomic isolation from an in-flight profile switch.

The existing POSIX-secured logger setup prevented broad `page_check.php` / `scope_check.php` fixtures from reaching behavior assertions. These are harness setup failures, not product RED or passing suite evidence. `scene_actions_check.php` was not run on this host for the same known requirement. Production permission checks were not weakened. The focused hook emits a known directory_unavailable logger fallback and still completes its assertions.

The separate early-ACK capacity hypothesis remains unconfirmed and untouched; its integrated reproduction requires genuine Unix private-mode semantics. A future decisive POSIX fixture needs an explicit coordinated TEST window after the human confirms live is stopped. No server window is needed to close these three source/controller fixes.

No bash.exe, WSL, distro, server endpoint, database, provider, installed game or gameplay session was accessed. No CHIM core, SHARMAT, Mind Poisoning or Department files, state schema, reflection bridge, manifest, builder, version or pin were changed. No build, install, commit, push or publication was performed.

Stop optional testing here unless final review identifies a concrete remaining risk. Any installed-runtime or release gate must be separately authorized and must not be inferred from these fixture results.

## Closing review

The [independent review](review/independent-review.md) found no material defect. The lead inspected that report and the complete guide diff: only stale-playthrough form guidance and active/queued Solo wrap priority changed. The guide distinguishes resetting actor/mode choices from wiping all settings and retains external-generation/playback limits. All task acceptance criteria are met within the fixture boundary. Changes remain local and uncommitted; published 0.1.24 and its pins remain unchanged.
