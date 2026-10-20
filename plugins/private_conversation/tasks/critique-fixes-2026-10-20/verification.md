# PCV 0.1.22 maintenance verification

Base source: `a7c7a418fc6a32aaec5d718dc6f3bb39216eb8b8` (released 0.1.21 runtime). Verified 2026-10-20 using PHP 8.2.29 in **DwemerAI4Skyrim3-test only**, with the task-owned PHP configuration and no additional scanned INI files. All fixtures use synthetic actors and isolated temporary state. No installed-plugin change, provider request, production database operation or gameplay occurred.

## Questions and original results

| Question | Baseline result | Fixed result | Proof limit |
|---|---|---|---|
| Does the final captured action list follow scene restrictions before core formatting? | [D1 RED](runtime/d1-red.output.txt), exit 1 | [Actions GREEN](runtime/d1-green.output.txt), exit 0 | Real hook and archived core formatter; controlled action-rebuild fixture, not the entire CHIM request. |
| Does talk-only remain action-free? | [Baseline](runtime/d1-talk-baseline.output.txt), exit 0 | [Clean GREEN](runtime/d1-talk-green.output.txt), exit 0 | Confirms the critique's blanket talk-only leak claim was too broad. |
| Do action/schema/target gates and scoped routing survive? | Existing behavior | [Action hooks](runtime/actions-hook-green.output.txt) and [final scope check](runtime/scope-final-green.output.txt), exit 0 | Isolated hooks and fixtures, not native action execution. |
| Are invalid scene cards rejected accurately before staging? | [Corrected fixture against baseline](runtime/d2-corrected-fixture-red.output.txt), exit 255 | [UI GREEN](runtime/d2-green.output.txt), exit 0 | Real page controller with controlled dependencies. Real card validation is checked separately. |
| Do real card validation, Unicode/blank normalization and roleplay settings remain valid? | Existing contract | [Real validator GREEN](runtime/card-validator-green.output.txt), exit 0 | Real state/config functions; synthetic state. |
| Can the first refusal after clock rollback persist readable state, with existing END recovery intact? | [D3 RED](runtime/d3-red.output.txt), exit 1 | [D3 GREEN](runtime/d3-green.output.txt), exit 0 | Future activation simulates rollback; the OS clock was not changed. |
| Does an unreadable ending check produce a truthful stored/logged/readable reason? | [R7 RED](runtime/r7-red.output.txt), exit 1 | [R7 GREEN](runtime/r7-green.output.txt), exit 0 | Covers a mixed refusal interval, no early member loss, timeout, state and diagnostics readback. |
| Does the page distinguish the failed-check interval from the check ending the scene? | [UI RED](runtime/r7-ui-red.output.txt), exit 255 | [UI GREEN](runtime/r7-ui-green.output.txt), exit 0 | Rendered page fixture; no new browser or gameplay session. |
| Are old state/migration and recovery paths still accepted? | Existing contract | [Compatibility GREEN](runtime/state-compat-green.output.txt), exit 0 | Includes legacy migration, cross-device refusal, correlation, invalidation and isolated logs. |

Each command's actual exit status is retained alongside its output in a matching `.exit.txt` file. Exit 255 in the UI RED runs is the intentionally failing PHP assertion. The first UI GREEN attempt failed because its fake state dependency omitted the validator exposed by the new form path; that output is preserved. The corrected fixture was then run against an untouched baseline export as well as the fix, so a test-environment correction was not mistaken for a product fix.

Changed PHP source and fixtures passed the narrow `php -l` gate. Those outputs are static/syntax evidence and do not establish the behavior in the table.

## Reproduce independently

Use an isolated PHP 8.2 environment with mbstring, POSIX and the dependencies recorded by [preflight](runtime/preflight.output.txt). Run the named files under `tests/`; [run-check.sh](runtime/run-check.sh) records output and exit status and deliberately refuses any distro other than the test distro. The D1 fixture accepts `PCV_TEST_CHIM_CORE_ROOT` pointing to the exact core source snapshot containing `lib/prompt_composition.php`. Its default retained source is `artifacts/chim-drama-encounter-20261005`; [the integration review](integration-review.md) records the core identity and actual caller lines. It must not be represented as a provider-backed full-core run.

To reproduce the UI baseline comparison, export baseline commit `a7c7a41`'s `server/` into a scratch project, place the corrected `tests/page_check.php` beside it, and run that fixture. It must fail its invalid-card assertion. Running the fixture against the fixed source must pass. No source reversal or shared-working-tree reset is needed.

## Review and remaining limits

The lead inspected the diffs, core capture/include/format order, action gates, card validator and form catch, persisted-state validation/write/read paths, final eligibility map, UI rendering and diagnostics allowlists. Bounded cross-reviews are retained in [integration-review.md](integration-review.md) and [state-review.md](state-review.md).

The refusal timer remains five minutes of consecutive failed eligibility checks. The end reason describes the check that ended the scene, not a claim that NPCs were observed absent throughout the interval. Existing missing/stale-report eligibility semantics remain unchanged; the new reason covers unavailable/error evidence under that reader contract. No extra persistent refusal-classification field was added.

The original D1 and initial scope outputs contain fixture-cleanup logger warnings. Cleanup ordering was corrected; the final D1 and scope outputs are clean. The baseline results and initial warnings remain available rather than being rewritten.

After the PHP window closed, two obsolete comments in the state helper and resilience fixture were corrected without changing logic. The reviewed product changes remain the four bounded fixes above. Host package checks passed four tests, and [the candidate inventory](../release-0.1.22/package-evidence/package-inventory.json) records 14 integrity/content checks and stable hashes for all 21 packaged source files. This verifies the working-tree candidate, not yet a clean committed-source build or public download.

The prior 0.1.20 gameplay acceptance remains [historical evidence](../beta-2026-10-17/acceptance-evidence.md). **0.1.22 changes runtime code and is not byte-identical to that played payload.** This task establishes the stated fixture behavior; fresh installation, full CHIM/provider execution, action follow-ups, native playback and Skyrim gameplay were not rerun. CHIM core, SHARMAT and Mind Poisoning were not modified.

The window was explicitly handed to Department after [release](runtime/runtime-release-corrected.json). Windows-only checks recorded both CHIM distros stopped and unchanged gaming VHD length/UTC timestamp. This is metadata evidence, not a disk-content hash. The initial false metadata mismatch is explained in the corrected receipt and preserved separately.
