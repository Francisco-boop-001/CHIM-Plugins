# Solo action offerings

Solo scenes now offer the five existing personal actions and only `ExtCmdStartSelfMasturbation`, `ExtCmdPutOnClothes`, and `ExtCmdRemoveClothes` from the intimate group. The shared allowlist also drops physical actions, including Brawl, for solo scopes. Pair action offerings are unchanged. `ENABLED_FUNCTIONS` remains a narrowing filter, so CHIM or SHARMAT-disabled actions are never re-enabled.

The change is in `server/scope.php` at `pcvSceneAllowedActionCodes()`. That shared result feeds the action verdict, `pcvPrepareSceneActions()`, `pcvSceneActionListAllowed()`, and roleplay guidance. `server/context_pre.php` already applies the list helper to both `FUNC_LIST` and the action schema enum, so no hook edit was needed. The post-generation target check remains active; a regression now verifies that an offered self-action targeting another NPC is dropped.

## Verification

- RED on the pre-fix source (`server/scope.php` SHA-256 `07D2CDA62A2F7D64CA41C69CB89B42030685AA7CA8CD8531D5D671EBD5E4F89C`): coordinator ran the updated hook fixture in `DwemerAI4Skyrim3-test`, PHP 8.2.29. It exited 1 at the new assertion: `Solo offers personal actions and the three self-directed intimate actions only, even if handed a physical group.`
- GREEN in the same test-only distro: `php tests/scene_actions_hook_check.php`, `php tests/scene_actions_check.php`, and `php -l server/scope.php` all passed. Captured output is in `tasks/release-0.1.20/runtime-evidence/solo-green.stdout.txt`; stderr is empty.
- `git diff --check` passed for the three changed source/test files.

The hook fixture narrows synthetic `ENABLED_FUNCTIONS`, then checks context_pre-shaped `FUNC_LIST` and `structuredOutputTemplate...action.enum` through `pcvSceneActionListAllowed()`. It does not invoke the real core action rebuild or establish live server, provider, database, client, or Skyrim behavior. The test-only distro preflight is recorded in `tasks/release-0.1.20/runtime-evidence/preflight.md`; no live distro or service was started.
