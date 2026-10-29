# Offline routing bughunt

**Baseline:** PCV `HEAD 0c697793311b314e8158b122e3c2b718638ca9e0` (read-only). No product or existing test files were changed. This is source and isolated-host-fixture evidence only; it does not prove installed CHIM behavior, native action execution, or in-game playback.

## Confirmed findings

### P3 — Included-player Brawl is accepted but omitted from its offered target list

**Trigger:** Pair scope, Physical actions enabled, `exclude_player=false`.

PCV's current [player guide](../../../docs/player-guide.md) says scene actions and their targets are narrowed to the configured scene (lines 39, 41, 43). The action design says the player counts as a member for targets when included (line 69 of `docs/superpowers/specs/2026-10-05-pcv-0.1.16-scene-actions-design.md`). Retained CHIM initialization appends `PLAYER_NAME` to `FUNCTION_PARM_INSPECT` (`artifacts/department-maintainer-audit-20261026/host/server-dev/prompt.includes.php:36-39`), and its actual `setActions()` formatter prints that list for Brawl (`.../functions/json_response.php:267-269`). CHIM's retained Brawl description explicitly allows the player (`.../functions/functions.php:987`).

The mismatch is in PCV: `context_pre.php:104` calls `pcvPrepareSceneActions()`. The verdict helper includes the player in the allowed target set when `exclude_player=false` (`server/scope.php:1197-1204`), and the action post-filter keeps an action when that verdict is null (`:1289-1292`). But the prompt-list filter always retains only `pcvScopeMembers()`—the selected NPCs—without adding the included player (`:1243-1247`). So the prompt says Aela and Bryn are the available Brawl targets, while the same request's output filter accepts a Brawl targeting Hawke.

**Reproduction:** `repro-included-player-action-target.php` executes the actual PCV helper and post-filter plus retained CHIM `setActions()`. The fixture seeds a synthetic nearby-NPC list and minimal Brawl function catalog/name mapping (including `getFunctionCodeName`), then appends `PLAYER_NAME` as retained `prompt.includes.php` does; actual PCV helpers/post-filter and retained CHIM `setActions()` run. Output: included case prompt targets `[Aela, Bryn]`, player verdict `null`, and player action kept `true`; bystander verdict remains `target_outside_scene`; Physical-only still rejects `ExtCmdKiss` as `action_not_allowed`. Excluded-player control keeps `[Aela, Bryn]` and refuses Hawke with `target_outside_scene`.

**Impact/limit:** This is a prompt/offer inconsistency, not a privacy bypass: the model may still choose a target from context or ignore the prompt, and no game action was executed. Small correction direction: when the player is included, add the player to the prompt target list while keeping the existing verdict/post-filter rules.

### P2 — A queued Solo can turn a Pair's `wrap up:` into Solo input

**Trigger:** Active Pair with a queued Solo replacement, then an ordinary Standard `wrap up: ...` input.

The current [player guide](../../../docs/player-guide.md) states that Pair/Group/Free `wrap up:` is a closing turn (line 81) and that `wrap up` ends pending or active Solo immediately (line 82). The code checks the wrap before activation (`server/preprocessing.php:118-153`), but `pcvWrapEndsScene()` selects the active scope whenever the state is active and checks only that scope's mode (`server/scope.php:1053-1058`). Thus active Pair + pending Solo does not take the Solo END path. Preprocessing then calls `pcvBeginResolvedScope()` at `:179`; that resolver treats an eligible pending config as an activation candidate (`server/scope.php:474-479, 567-577`). Once it resolves to Solo, preprocessing sets `$wrapUp` only for non-Solo scopes (`server/preprocessing.php:284-289`), then routes Solo to `solo_reflection` (`:311-313`). `pcvPrepareScopedInput()` turns the excluded-player request into an `instruction` while retaining its text after the player prefix (`server/scope.php:852-887`).

**Reproduction:** `repro-pending-solo-wrap-hook.php` includes the actual `server/preprocessing.php` and uses the actual `pcvWrapEndsScene()`, `pcvBeginResolvedScope()`, and `pcvPrepareScopedInput()` paths. Only playthrough/state read/write, catalog, and presence APIs are doubles; the state transition double promotes an existing pending scope on the eligible Standard boundary. In the actual hook, Pair-only control sets `wrap_up=true`, routes `scene_direction`, normalizes to `Hawke: They part ways at dusk.`, calls the mocked stage/END path, and receives the stubbed `off` result. Active Pair + queued Solo promotes Solo, leaves `wrap_up=false`, routes `solo_reflection`, sends `Hawke: wrap up: They part ways at dusk.` as instruction, and makes no stage/clear call. `repro-pending-solo-wrap.php` is the smaller helper-level reproduction of the same transition.

**Impact/limit:** The command does not honor either documented close behavior in this mixed state and can activate a replacement scene on the request intended to close. The fixture proves the actual hook branch and route, while the pending-promotion API result is stubbed; no persistence or gameplay call was made. Small correction direction: process a wrap against the pre-activation state and prevent the same wrap request from promoting/routing a queued Solo as ordinary input; retain the documented Pair closing turn and Solo immediate-end behavior.

## Reproduction receipts

All three fixtures used the explicit Windows PHP 8.3.35 runtime with `-n` and no DB, HTTP, provider, or game access:

```powershell
& 'K:\ActorwrightExchange\artifacts\php-8.3.35-windows-cli\runtime\php.exe' -n 'K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\bughunt-2026-10-28\routing\repro-included-player-action-target.php'
& 'K:\ActorwrightExchange\artifacts\php-8.3.35-windows-cli\runtime\php.exe' -n 'K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\bughunt-2026-10-28\routing\repro-pending-solo-wrap.php'
& 'K:\ActorwrightExchange\artifacts\php-8.3.35-windows-cli\runtime\php.exe' -n 'K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\bughunt-2026-10-28\routing\repro-pending-solo-wrap-hook.php'
```

Each exited `0`. Raw stdout, stderr, and exit receipts are alongside the scripts. The retained formatter prints its two expected `[FUNCTIONS]` diagnostics. The wrap fixtures print a `directory_unavailable` logger diagnostic under `php -n`: the logger requires a POSIX effective UID and returns before creating its fallback directory when that UID is unavailable. The routing assertions still pass; no product logger path or external state was modified by the fixtures.

## Source binding

PCV tree: `0c697793311b314e8158b122e3c2b718638ca9e0`.

SHA-256 of inspected source/contracts:

- `server/scope.php`: `e21fd73985424c099332dcbb035e3587c72ab78a21e21781669978dc25e39686`
- `server/preprocessing.php`: `5a0d46d3b29ba5f1a986f90b1fa7bbc8ce41402dd6b8d30f49a2fcba7783d49c`
- `server/context_pre.php`: `531e2e3e24bae4728e95e523800b53fbee76db0c91ac8d15756caf3481ae7201`
- retained `prompt.includes.php`: `9f37190f45b67a31b75248bff87ebae74c363df6608ecbfe7cac3da32b85532d`
- retained `functions/json_response.php`: `2046b47019fa170fc91183ba7a762aafbcf24b89be405ab542ceab447b64f9fd`
- retained `functions/functions.php`: `5bcc5160590a429a21ac02ca8b8a52c44fdd28a4fd8f0e3732aa8d291284ba4a`
- `docs/player-guide.md`: `dd935381d30cdd8f9e42550293fd76a33b1fb040973af58e5d1e4e085ec38ff0`
- action design spec: `91501f4b637b04908e725d4370013a6e6686c5d996ec10a8c98fb75f19f67acc`