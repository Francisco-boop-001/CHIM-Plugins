# UI diagnostic wording correction

The active-scene status now describes the logger evidence without inferring speech or playback. `postrequest_observed` renders as “completion hook observed”; `unobserved` renders as “completion hook not observed” and says the request reason, delivery, and playback are unknown. The unobserved message points readers to CHIM/native logs for the actual reason. Neither outcome is presented as proof that the NPC spoke, that speech was delivered, or that CHIM exhausted its rechat budget.

The `last_turn.json` fields, logger event/outcome values, escaping, scene routing, and polling behavior are unchanged. The existing rendered-page fixture now covers both outcomes and still checks that another scene cannot see the fixture's turn. The outdated explanatory comment in `tests/log_shutdown_context_check.php` now describes `request_unobserved` as missing terminal observation only.

## Verification

Ran the focused fixture in the authorized `DwemerAI4Skyrim3-test` distro using:

```powershell
.\scripts\live-test\sim.ps1 -Script run-php-tests.sh tests/ui_logs_host_access_check.php
```

Before changing `server/index.php`, the updated regression failed as intended:

```text
===== tests/ui_logs_host_access_check.php
FAIL: An unobserved completion hook leaves request reason, delivery, and playback unknown.
exit=1
```

After the renderer change, the same command passed:

```text
===== tests/ui_logs_host_access_check.php
PASS logs access trusts only loopback and the WSL host, and explains refusals; last scene turn shown
exit=0
```

`git diff --check -- server/index.php tests/ui_logs_host_access_check.php tests/log_shutdown_context_check.php` exited 0 with no output. The focused fixture exercised the actual page renderer in an isolated temp logger directory; no CHIM server, provider, database, or game was used.

## Scope and remaining documentation

My changes are limited to `server/index.php`, `tests/ui_logs_host_access_check.php`, and the explanatory comment in `tests/log_shutdown_context_check.php`. A consumer search found older wording in `README.md` lines 78 and 265 that still attributes silent turns to the rechat budget and says the page shows “spoke / ended without speech.” I left those files untouched because the task plan assigns README/docs to the documentation owner; that owner should reconcile them. Historical design notes also contain the old wording, but they are not current UI behavior.

This proves rendered wording only. It does not establish CHIM's reason for any particular unobserved request or prove speech delivery, audio playback, or hearing; inspect CHIM/native logs for a specific request.

## Explicit test-distro provenance check

The earlier RED/GREEN runs used the `sim.ps1` wrapper and did not record the inherited `PCV_TEST_DISTRO` value. To remove that uncertainty for the passing result, I ran the fixture once directly in the named test distro, with no wrapper or environment override:

```powershell
wsl.exe --distribution DwemerAI4Skyrim3-test --exec /bin/bash -lc $linuxCommand
```

The exact `-lc` command first asserted the distro name, then printed the distro and PHP version and ran the fixture:

```bash
if [ "${WSL_DISTRO_NAME:-}" != "DwemerAI4Skyrim3-test" ]; then printf "DISTRO_MISMATCH=%s\n" "${WSL_DISTRO_NAME:-<unset>}"; exit 90; fi; printf "WSL_DISTRO_NAME=%s\n" "$WSL_DISTRO_NAME"; php -r 'echo "PHP_VERSION=", PHP_VERSION, "\n";'; php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/ui_logs_host_access_check.php; result=$?; printf "FIXTURE_EXIT=%s\n" "$result"; exit "$result"
```

Output:

```text
WSL_DISTRO_NAME=DwemerAI4Skyrim3-test
PHP_VERSION=8.2.29
PASS logs access trusts only loopback and the WSL host, and explains refusals; last scene turn shown
FIXTURE_EXIT=0
WSL_FIXTURE_COMMAND_EXIT=0
```

Read-only WSL inventories showed `DwemerAI4Skyrim3` stopped and `DwemerAI4Skyrim3-test` stopped before the run; afterward the gaming distro remained stopped and only the test distro was running. I then terminated only `DwemerAI4Skyrim3-test`; the final inventory showed both CHIM distros stopped. This confirms the passing fixture's distro and the post-run state; it does not establish a cause for any previously observed VHD timestamp change.
