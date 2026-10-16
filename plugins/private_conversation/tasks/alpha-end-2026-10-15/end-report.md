# END persistence-failure fix report

## Change

`server/preprocessing.php` now treats a scene clear as successful only when `pcv_stage()` returns status `off`. Ordinary END and solo wrap-up block with `state_unavailable` when the clear is unconfirmed (lines 135–146). Pair/group wrap-up also blocks if the playthrough key is unavailable, staging throws, or staging returns any status other than `off` (lines 337–352), so preprocessing cannot report a routed closing turn after an unconfirmed clear. The error says the scene may remain active/pending; it does not claim the stored state is unchanged because an unavailable result can occur after a write.

`tests/in_game_end_hook_failure_check.php` exercises the real preprocessing include with a narrow in-memory state/stage seam. It covers active and pending END failures, solo and pair/group wrap-up failures, confirmed solo and pair/group wraps, successful active/pending END, and an unarmed exact END phrase. Failure assertions check the existing error outcome and that early END paths stop before input preparation. The existing `tests/in_game_commands_check.php` continues to cover exact command matching and successful state-stage logging.

## Baseline and verification

The docs owner captured `server/preprocessing.php` before the product edit: SHA-256 `99C4B79D018567E61D36C4FB69A8B8638AABA9C1F46E308F03D0A1E0F19F3B8A`. I independently confirmed this hash immediately before editing.

RED command:

```powershell
wsl.exe --distribution DwemerAI4Skyrim3-test --exec /bin/bash -lc 'if [ "${WSL_DISTRO_NAME-}" != "DwemerAI4Skyrim3-test" ]; then echo "FAIL wrong WSL_DISTRO_NAME=${WSL_DISTRO_NAME-<unset>}"; exit 97; fi; printf "DISTRO=%s PHP=" "$WSL_DISTRO_NAME"; php -v | head -n 1; php "/mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/in_game_end_hook_failure_check.php"'
```

Observed before the source edit: `DISTRO=DwemerAI4Skyrim3-test`, PHP 8.2.29, then `FAIL: active END must use the existing blocked-error path` (exit 1). This was the expected regression: the hook reached success after the fixture returned `unavailable`.

GREEN command:

```powershell
wsl.exe --distribution DwemerAI4Skyrim3-test --exec /bin/bash -lc 'if [ "${WSL_DISTRO_NAME-}" != "DwemerAI4Skyrim3-test" ]; then echo "FAIL wrong WSL_DISTRO_NAME=${WSL_DISTRO_NAME-<unset>}"; exit 97; fi; printf "DISTRO=%s PHP=" "$WSL_DISTRO_NAME"; php -v | head -n 1; php "/mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/in_game_end_hook_failure_check.php" && php "/mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/in_game_commands_check.php"'
```

Observed after the edit: `DISTRO=DwemerAI4Skyrim3-test`, PHP 8.2.29; both fixtures passed:

- `PASS in-game END failure paths block honestly; successful active/pending END and unarmed input remain intact`
- `PASS in-game end and wrap-up phrases match exactly and end the scene with a logged reason`

After simplifying pair/group clear handling and adding healthy wrap-up controls, I reran only the affected hook fixture with the same explicit distro assertion. It passed with: `PASS END failures block; solo/group wraps and active/pending END success plus unarmed input remain intact`.

## Limits

The hook regression stubs `pcv_stage()` results; it verifies caller behavior but does not reproduce an atomic-storage write fault or assert real HTTP status/logger output. The existing command fixture exercises actual state-stage success. Only these two PHP fixtures ran; the full suite, live CHIM/provider/database paths, and Skyrim runtime were not exercised. No production state or server was read or changed. No claim is made that every `unavailable` result leaves the prior store intact.
