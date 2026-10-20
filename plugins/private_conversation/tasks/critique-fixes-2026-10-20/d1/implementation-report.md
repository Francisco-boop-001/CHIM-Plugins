# D1: synchronize the captured action prompt

## Change

At `server/context_pre.php:132-136`, after CHIM has rebuilt the response state and PCV has validated the action list and schema, an actions-enabled scene now copies the rebuilt `PROMPT_ACTIONS_LIST` into the global `actionsList` captured earlier by CHIM. A malformed non-string rebuild becomes empty rather than retaining the previously captured unrestricted prompt. The existing talk-only path still clears `PROMPT_ACTIONS_LIST` and `actionsList` at `server/context_pre.php:104-108`. The action enum and `pcvSceneActionPostFilter` checks remain ahead of the synchronization.

This matches the core flow in the pinned encounter snapshot: `main.php:2508-2510` captures the string before `main.php:2554` loads `context_pre`; prompt assembly later consumes `$actionsList` at `main.php:2600`, then formats it at `main.php:2630`. No broad Markdown stripping or history replacement was added.

## Regression coverage

`tests/d1_actions_prompt_check.php` exercises the hook include from function scope, starts from CHIM's actual `<available_actions_list>` tag, and invokes the pinned core `chimFormatPromptHeadSection()` formatter. The `actions` scenario checks the captured list, narrowed Brawl target names, schema enum and post-filter registration, plus preservation of history text containing `Attack` and a bystander name. The `talk-only` scenario checks that the captured and rebuilt prompt remain empty. Its shutdown cleanup is registered after the hook registers the logger observer, so the temporary test log is removed after terminal logging.

The synthetic `<actions>` inputs in `tests/scope_check.php:524-533` exercise only the legacy cleanup in `server/context.php:42`, which removes that tag. They are now labeled legacy-only; they are not evidence that the CHIM `<available_actions_list>` block is filtered there. `context.php` was not changed.

## Verification record

Before the product edit, the lead captured the expected RED at `tasks/critique-fixes-2026-10-20/runtime/d1-red.output.txt` with exit code `1` in `d1-red.exit.txt`; the first failure was the stale captured `actionsList` assertion. The lead also captured the talk-only baseline as exit `0`. Those files were left untouched. The earlier talk-only output included a fixture-generated `directory_unavailable` logger message because cleanup ran before the logger shutdown observer; the fixture ordering is corrected, but that corrected cleanup has not been executed here.

Static `git diff --check` passed for the tracked D1 changes. No PHP, WSL, or test command was run by this worker. The lead owns these targeted GREEN checks in the approved test distro:

```sh
PCV_TEST_CHIM_CORE_ROOT=/mnt/k/ActorwrightExchange/artifacts/chim-drama-encounter-20261005 php tests/d1_actions_prompt_check.php actions
PCV_TEST_CHIM_CORE_ROOT=/mnt/k/ActorwrightExchange/artifacts/chim-drama-encounter-20261005 php tests/d1_actions_prompt_check.php talk-only
php tests/scene_actions_hook_check.php
php tests/scope_check.php
```

This is source and fixture coverage only; it does not establish full CHIM bootstrap, SHARMAT action dispatch, or gameplay behavior.
