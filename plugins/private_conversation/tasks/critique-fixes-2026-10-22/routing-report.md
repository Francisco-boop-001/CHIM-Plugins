# Routing follow-up report

## Retained SHARMAT listener-hook check

This was a read-only source check against retained snapshots. No game, installed plugin, or runtime path was used.

The checked PCV checkout is `3cd9f840ec5ce3d66da1a5e72252c1d28929c6a5`. In `server/scope.php:1301-1305`, PCV reads `AIAGENTNSFW_FORCE_SCENE_LISTENER`; `pcvSceneTurnPlan()` at `server/scope.php:1316-1330` currently honors a valid pin before the wrap-up sentinel. `server/context_pre.php:77-92` computes that plan and applies it. It then loads the PCV JSON customizer and calls CHIM's refresh at `server/context_pre.php:112-115`. The customizer reads the plan and writes both the listener enum and response template in `server/json_response_custom.php:35-80`; registration is once-per-request at `:84-95`.

Two retained SHARMAT variants were checked:

- `SHARMAT-NonRomantic-Compatibility`, revision `123c98d95ce7a54dada6ab34f91d2f4eabe3dffb`: `prerequest.php:744-745` sets the forced listener and rechat-target globals, and `:751-764` appends a JSON-template closure that pins `responseTemplate.listener` and the structured listener enum to the partner.
- `SHARMAT-OStim-Teardown`, revision `d6c6da595eb961f338245d6e4d044fe6e86279f0`: the corresponding code is at `prerequest.php:741-742` and `:748-761`.

In both retained trees, the only SHARMAT PHP writes to the listener template are those prerequest closures. Other JSON-template closures found in `functions.php` edit action fields or the `target` field (`:2156`, `:2600` in the compatibility checkout; `:2238`, `:2669` in the teardown checkout); they do not overwrite `listener`. The retained SHARMAT PHP sets `AIAGENTNSFW_FORCE_RECHAT_TARGET`, but no consumer of that global was found in either SHARMAT tree or PCV's server source.

## Retained core dispatcher ordering

The retained core snapshot is identified in `K:\ActorwrightExchange\artifacts\chim-drama-encounter-20261005\REPORT.md:9` as HerikaServer commit `cf5030f15781637498be86debe26fcf102f5690d`; this is a static archived tree, not a live checkout. In that tree, `main.php:1125` loads extension `prerequest.php` hooks. It later includes `functions/json_response.php` at `main.php:2487`, then loads extension `context_pre.php` at `main.php:2554`. The JSON runner (`functions/json_response.php:44-47`) calls `HOOKS["JSON_TEMPLATE"]` in insertion order; `chimRefreshJsonResponseState()` rebuilds the response schema and then invokes that runner (`:62-84`), and the initial refresh is called at `:191`.

Both retained SHARMAT revisions set the force-listener and append a listener-writing JSON hook during prerequest: compatibility revision `123c98d95ce7a54dada6ab34f91d2f4eabe3dffb` at `projects/SHARMAT-NonRomantic-Compatibility/prerequest.php:744-763`, and teardown revision `d6c6da595eb961f338245d6e4d044fe6e86279f0` at `projects/SHARMAT-OStim-Teardown/prerequest.php:741-760`. PCV's `context_pre` registers its JSON customizer before calling `chimRefreshJsonResponseState(false)` (`server/context_pre.php:112-115`), so, at these retained source revisions, the PCV callback runs after the earlier SHARMAT callback and can replace its listener schema/template. This confirms hook order in the named source snapshots; it does not certify an installed mutable CHIM/SHARMAT combination.

The wrap fixture now models the retained SHARMAT callback's two listener writes before explicitly re-registering PCV's callback, preserving the real insertion order even though the fixture previously loaded the PCV customizer with `require_once`. Its assertion checks the final listener enum and the wrap prompt. The closure is an isolated fixture model of the retained mutation, not an execution of SHARMAT's full prerequest dependencies. The same fixture separately resets and re-registers PCV's callback for the ordinary included-player case.

In the checked retained SHARMAT PHP trees, `AIAGENTNSFW_FORCE_RECHAT_TARGET` is assigned but no server-side consumer was found in SHARMAT or PCV. In retained core, inbound rechat target selection is resolved from the request payload in `main.php:1154-1169`; the rechat participant list is built from `RECHAT_RESOLVED_TARGET` at `:1790-1803`. That supports the conclusion that the known listener schema hook does not itself select the subsequent rechat target. No claim is made about external extensions absent from these snapshots, later code outside the inspected request path, or game playback/delivery. No persistent END or native playback behavior is proven by the fixture.

## Task 2 implementation and focused verification

The implementation is limited to PCV. `server/scope.php` loads the store under the existing shared lock, releases the lock, then computes visibility from that loaded snapshot. A readable store with no currently visible active or pending scene no longer blocks identity recovery. The state payload bytes and mtime remain unchanged in the expired-only check; the lock helper may create or chmod lock metadata. Load, lock, corrupt-file, and unsafe-path failures still return fail-closed.

For pair scenes, preprocessing recognizes a `wrap up:` suffix, normalizes it to the exact player-prefixed direction, and passes it through `pcvPrepareScopedInput(..., sceneDirection: true)`. That reuses the existing UTF-8/prefix/empty-input validation and instruction conversion even when the player remains included; a colon-bearing player name is rejected before the state-ending write. The routing snapshot still follows `exclude_player`, so an included player remains in the audience. The request route and `pcvPairRoutedRequest()` accept this wrap direction with player inclusion; `context_pre` supplies direction-specific guidance while preserving the player as a participant. Ordinary included-player input remains `player_speech` and retains its existing wording. `pcvSceneTurnPlan()` now checks wrap-up before the SHARMAT pin, so the closing listener sentinel wins over a valid partner pin.

The focused regression fixtures are `tests/state_recovery_check.php`, `tests/turn_plan_check.php`, and the actual preprocessing/context/schema path in `tests/scope_check.php`. The lead captured the intended baseline RED for all three. The final state-recovery and scope fixtures passed through the guarded runner, which pins the TEST distro and isolated PHP INI:

```text
wsl -d DwemerAI4Skyrim3-test -- bash /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tasks/critique-fixes-2026-10-22/runtime/run-check.sh state-recovery-final-green tests/state_recovery_check.php
wsl -d DwemerAI4Skyrim3-test -- bash /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tasks/critique-fixes-2026-10-22/runtime/run-check.sh scope-complete-green tests/scope_check.php
```

Both commands exited 0. The receipts are `runtime/corrected-fixture-results.json`, `runtime/state-recovery-final-green.output.txt`, and `runtime/scope-complete-green.output.txt`. The earlier GREEN attempt exposed two fixture assumptions, not product assertion failures: state visibility reports `status: active` with `pending: true` when both slots are live, and the normal included-player fixture stopped after `context_pre` without exercising terminal `context.php`. The corrected fixtures assert the pending-slot contract and run the normal included turn through final context while preserving its speech guidance and prior history.

These source and fixture checks do not establish native playback or prove the behavior of a mutable installed CHIM/SHARMAT combination.
