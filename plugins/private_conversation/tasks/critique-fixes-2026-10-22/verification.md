# PCV 0.1.23 bounded verification

## Baseline and scope

Baseline source is `3cd9f840ec5ce3d66da1a5e72252c1d28929c6a5`, with the same PHP runtime payload as published 0.1.22. Before any PHP product edits, the lead inspected the new regressions, corrected a simulated-request JSON hook registration defect, then ran all prepared PHP baselines in the explicitly handed-over `DwemerAI4Skyrim3-test` (PHP 8.2.29). The task wrapper refuses any other distro and uses task-local mbstring/POSIX configuration; it does not load PostgreSQL. No installed plugin, provider, database or game data is used.

Mind Poisoning explicitly released its marked cluster/holder before PCV entered TEST. The registry guard identifies TEST at E:\WSL\DwemerAI4Skyrim3-test and gaming at D:\DwemerAI4Skyrim3. The preflight reports TEST Running and gaming Stopped. These observations do not certify other owners' activity or gaming disk contents.

## Original baseline output

All original stdout and actual exits are retained under [runtime](runtime/). [The baseline result map](runtime/baseline-results.json) records the PHP commands. The unchanged original page-notes test was executed from a clean baseline export in ignored scratch; the working-tree test was not reverted.

| Question | Original RED | Corrected result |
| --- | --- | --- |
| Does a failed queued replacement refuse once without remaining queued or exposing the old scope? | eligibility-red, exit 1 at the new unavailable/no-pending assertion | eligibility-green, exit 0 |
| Can a readable expired-only state block unknown playthrough identity? | state-recovery-red, exit 1 at expired-active guard | state-recovery-final-green, exit 0 |
| Does wrap-up beat a valid SHARMAT member pin? | turn-plan-red, exit 1 at closing-listener assertion | turn-plan-green, exit 0 |
| Is included-player wrap a direction through real PCV preprocessing/context and final listener schema? | scope-red, exit 1; attribution, guidance and final enum assertions identify the known gaps | scope-complete-green, exit 0 |
| Does Free copy show the actual cap/range? | free-ui-red, exit 1 at selectable-cap label; form cap preservation already passes | free-ui-green, exit 0 |
| Is a gateway-like private address rejected for diagnostics? | logs-access-red, exit 1 at unauthorized gateway admission | logs-access-final-green, exit 0 |
| Is the old page-notes test stale? | old-page-notes-red, exit 1 at obsolete text | page-notes-green, exit 0; positive current end-reason wording remains covered by page_check |
| Does browser mode guidance reflect Group/Free controls? | host-only ui-refresh-red, 13/14; wrong Group text | host-only ui-refresh-green, 14/14, exit 0 |

The local Node DOM harness neither enters a distro nor runs a browser/server. Its accepted JS diff changes only guidance and the cap-change presentation listener. The lead inspected the full diff, event path, RED and GREEN. No rerun is required unless that code changes again.

## Caller and fixture limits

The routing owner traced retained CHIM snapshot `cf5030f15781637498be86debe26fcf102f5690d` and two named SHARMAT snapshots; the lead inspected the actual JSON runner and prerequest caller. [The routing report](routing-report.md) records the order and immutable revision limits. The new fixture models SHARMAT's two listener-template writes before PCV's callback. It runs PCV preprocessing, prerequest, context preparation and final context/schema hooks, but stubs persistent END and other core facilities. It is not a complete CHIM request, actual SHARMAT invocation, provider, delivery or playback test.

The state recovery fixture uses real PCV file state and checks both expired slots, mixed live/expired slots, unsafe/corrupt storage and unchanged expired-file bytes/mtime. The expiry-boundary assertion separately checks the existing visibility helper at an explicit read time. Unknown identity must not rewrite a valid state file.

## Final runtime gate and review

All 19 selected PHP fixture files ultimately passed. The original intermediate failures remain: `eligibility-map-red` exposed an unnecessarily retained unknown-map replacement; `scope-prefix-red` exposed instruction-prefix validation bypass in the first wrap patch. Both product defects were returned to their owners and corrected before final acceptance. `logs-http-green` returned the intended 403 but failed on a gratuitous heading rename; the established heading was restored and `logs-http-final-green` passed. The first state-recovery GREEN reached a fixture that incorrectly expected primary status `pending` alongside a live active scene; the correct result is `active` with `pending=true`. The first scope GREEN exposed a missing final context-hook call in its ordinary included-player control. Corrected fixture receipts pass; no product behavior was changed to satisfy those two setup mistakes.

The final selection is recorded in [accepted-checks.json](runtime/accepted-checks.json). The five changed PHP files passed syntax checks separately; syntax is not behavioral proof. Independent [UI/security](ui-cross-review.md) and [state/routing](routing-state-cross-review.md) reviews found no remaining actionable blocker. The lead reviewed every product/test diff, connected callers, failure paths and original outputs, and accepted this scoped maintenance change.

The [TEST release receipt](runtime/runtime-release.json) confirms the own temporary HTTP helper is gone, no PCV native work is queued, and TEST was left running. Protected gaming VHD size and exact UTC ticks are unchanged; that is metadata evidence, not a disk-content comparison. PCV entered only the literal TEST distro. Other running distros/services are not PCV's to stop.

Useful documentation, metadata, package and public-release verification follow as separate host-only gates. No new installation, provider, complete CHIM request, actual SHARMAT execution, native playback or Skyrim gameplay was performed for 0.1.23. Unknown-playthrough reads preserve state payload bytes/mtime; existing lock coordination metadata can be created or chmodded.
