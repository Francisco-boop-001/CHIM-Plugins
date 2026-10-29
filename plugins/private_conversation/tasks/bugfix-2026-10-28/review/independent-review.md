# Independent scoped review — 2026-10-28

## Verdict

The reviewed PCV changes implement the stated Solo-wrap priority, included-player action-target correction, and stale-page binding. I found no material defect in these scoped changes. This is source and fixture evidence, not live CHIM or Skyrim validation.

## Routing and action scope

`server/preprocessing.php:118-153` parses ordinary `end scene` and `wrap up` commands before `pcvBeginResolvedScope()` at line 179, so command handling can clear a queued Solo before promotion. `server/scope.php:1054-1067` now checks both the active and pending scopes when the stored status is active, and the pending scope when the status is pending. Thus active Pair plus queued Solo, active Solo plus queued Pair, and pending Solo all take the immediate END path; a Pair-only wrap still follows its closing-turn route.

At `server/preprocessing.php:146-152`, the Solo wrap calls `pcv_stage()` with `enabled => false` and stops if the returned status is not `off`; the failure text does not claim success. `tests/routing_fix_hook_check.php` exercises the real preprocessing and context hooks with fixture state/catalog/core callbacks. Its retained RED (`routing/routing-fix-red.*`, exit 1) identifies the queued-Solo wrap, failed clear, and included-player target cases. The final GREEN (exit 0) also asserts one failed clear attempt, preserved active Pair and queued Solo, no request promotion, and no false ended response. Existing END-failure and scene-action controls also pass (exit 0, `routing/end-failure-controls.*` and `routing/scene-actions-controls.*`).

For actions, `server/context_pre.php:57-64` obtains and validates the player name, passes that same value to `pcvPrepareSceneActions()` at line 104, and records it as `action_player` at lines 150-153. `server/scope.php:1241-1261` adds the player to offered Brawl targets only when the player is included and CHIM already listed that name in `FUNCTION_PARM_INSPECT`; the post-filter at lines 1293-1304 uses the same identity. The fixture verifies that an offered included-player target survives, an unoffered player is not added, and an excluded player remains rejected. These fixtures use mocked CHIM/state/presence services; they do not prove production core behavior.

## Stale-page binding

`server/index.php:94-99` validates the 64-character opaque reference. Both forms render it; `pcv_run_page()` derives the expected value from the server-read key at line 1125 and checks CSRF, then the submitted reference, before parsing or staging at lines 1131-1137. A mismatch receives HTTP 409 with fixed reason `playthrough_changed` at lines 1173-1177. The form never uses the submitted reference as a state key. If the current key is unavailable, existing stage validation refuses the write instead of using the stale form.

`server/assets/ui-refresh.js:272-283` rejects a response whose hidden references disagree with its displayed identity before applying it. Lines 287-308 reset cast and Solo/Free draft selections after a detected identity change; same-identity refresh retains eligible choices. The client updates both hidden references only after validation at lines 350-357.

The retained actual-controller fixture (`tests/stale_page_reference_check.php`) copies the production controller, scope, and installation guard, while substituting state, logger, and runtime-config adapters. Its RED (exit 1) shows missing references, stale ARM/END, and missing/malformed END could stage before the fix. Final GREEN (exit 0) covers those refusals, CSRF precedence, missing current identity, valid same-profile ARM, and END with an empty roster. It proves controller gating, not persistence semantics: `pcv_stage()` is a fixture stub. The Node DOM suite GREEN is 16/16 and covers atomic response validation, reference updates, same-identity retention, and identity-change resets; it is not a real browser run.

The expanded `tests/page_check.php` host attempt did not reach its route assertions: it exits 255 with `Could not activate fixture logger` because this host lacks the POSIX logger prerequisite. That is not a passing integration result. The focused controller fixture and the side-effect-free logger allowlist check provide the bounded replacement evidence. The retained routing GREEN has a fixture `directory_unavailable` logger warning on stderr, but exits 0 with all assertions passing.

No provider request, database-backed gameplay, browser, installation, or Skyrim session was exercised in this review. The accepted checks are the retained focused hook/controller fixtures and UI DOM suite; they support the documented behavior but do not establish delivery or in-game output.
