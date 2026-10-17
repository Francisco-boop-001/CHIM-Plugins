# Mind Poisoning 0.1.22 BETA CANDIDATE — evidence and limits

The 0.1.22 release uses standard GitHub classification (`prerelease=false`) to retain CHIM topic-discovery eligibility. Its release text and package metadata label it **BETA CANDIDATE**; standard classification is not a beta graduation. This page records source-level checks and earlier-version campaigns, not package validation.

## Completed disposable checks from 0.1.20

### B1: native PostgreSQL persistence — 10 scenarios passed

The campaign used Mind Poisoning 0.1.20 product source, the pinned CHIM core reference, synthetic event and relationship rows, and a fresh disposable PostgreSQL cluster. It exercised combined affinity/type and type-only commits, Player aliases, exact timeline/history snapshots, rollback after a snapshot-verification failure, sequential and barrier-coordinated concurrent duplicate delivery, actual core `RelationshipLLM` locking/rebasing in both call orders, stale type/scope/lock refusal, backend termination before commit, and a lost commit acknowledgement after the server committed.

The duplicate-delivery cases establish at-most-one persistence of the tested synthetic judgment. They do not establish once-only model execution or billing. The commit-acknowledgement case preserves the distinction between a confirmed commit and an unconfirmed outcome.

### B3: server install, upgrade, dashboard, and recovery — 9 scenarios passed

The campaign installed public package 0.1.20 through CHIM's actual package upload API, verified all 17 active server files, upgraded 0.1.19 to 0.1.20, and checked the old-extension backup plus preservation of synthetic database/configuration state and an unrelated file. It tested the actual standalone dashboard's loopback/authentication and method gates, CSP, and filtered JSONL download of one real synthetic request-log record.

Pause, relationship-feature Off, and interaction Off each made zero model-callback calls; an enabled control reached a counting callback once and deliberately returned invalid JSON. Separate SQL snapshots showed no writes in all four controls. No provider was invoked.

The unmodified CHIM automatic-backup implementation produced a SQL dump that passed independent content checks and a strict restore into a new empty task-owned database. Ten imported native tables and seven sequence states matched the captured baseline. The environment-local `chim_meta.settings` table is intentionally excluded by the upstream backup; the campaign captured, reapplied, and independently compared those local settings. Six selected filesystem roots were restored and hash-checked. The actual dashboard then read the restored synthetic ledger row, affinity 99, and type `friend`.

These passed B1/B3 campaigns cover 0.1.20, not the 0.1.22 package. They remain useful evidence for the unchanged persistence and server-install paths; they are not a substitute for a 0.1.22 cold install or package check.

## 0.1.21 live observations and the alias defect

One installed 0.1.21 reflection-classified case with Private Conversation 0.1.20 produced a combined affinity and relationship-type change. Persistence and terminal records confirmed the commit, and a later dashboard view showed the resulting edge. This is one successful installed reflection case, not broad PCV integration or a reliability estimate. It does not establish review of the actual self-talk for semantic quality, PCV API selection, an independent SQL history snapshot, a core-editor screenshot, audio, full installed-file hashes, or all component versions. See the [public live-acceptance summary](live-acceptance-summary.md).

A separate installed 0.1.21 observation exposed a wrong-subject semantic defect: generic vampire wording matched the generated alias for the catalog character `Vampire Fledgling`, and a judgment was committed to that wrong subject. Persistence succeeded; subject selection was wrong. The public summary omits dialogue, actor/request/event identifiers, and local records.

The 0.1.22 working-tree fix filters exact `Vampire` and `Black` tokens from generated aliases across the existing first-word, phrase-before-`the`, and bracket-stripped candidate routes. It does not suppress exact catalog names, including `Vampire Fledgling`, `Black Widow`, or literal mononyms such as `Black`, `Guard`, and `King`. Other common-word names such as `May` and `Guard` can still need model semantic judgment. No pronoun-resolution, taxonomy, model-prompt, schema, or wider alias-engine change is claimed.

The focused working-tree regressions first failed on the reproduced generic-alias behavior and then passed with the fix. Four isolated test suites and three PHP syntax checks passed. Their exact scope, harness limits, and the Windows symlink skips are recorded in the [public 0.1.22 verification summary](verification-v0.1.22.md). This is isolated working-tree evidence, not an installed 0.1.22 result.

The earlier 0.1.21 reflection/API compatibility fixture and root entrypoint access-gate check remain limited local checks. The topic-discovery route requires a newer CHIM manager than pinned core `cf5030f`, and the 0.1.22 site-discovery install path remains unverified end to end. If duplicate extension folders or an unexpected route state is present, follow the [duplicate-install recovery steps](deployment-migration.md#mind-poisoning-duplicate-install-recovery) before sending game requests.

## Remaining before a scoped beta

- **B2:** Add an NPC-to-NPC conversation about a named third person with independent review of the actual NPC utterance, a core-editor/history crosscheck, and recorded current-candidate lock/scope/On-Off and save/load checks. Existing zero and older lock/off observations remain valid within their recorded scope; current-candidate checks are unrecorded, not failed.
- **B3:** Complete a cold 0.1.22 install or upgrade on the chosen supported route, checking installed-file/version hashes and CHIM/client versions. The passed B1 0.1.20 native PostgreSQL campaign (10 scenarios) and B3 0.1.20 server install/upgrade/dashboard/recovery campaign (9 scenarios) remain valid and do not need repeating; they do not validate a 0.1.22 package.
- **B4:** Record active-versus-paused client acknowledgement and next-turn timing, stalled-provider recovery and the next request, and actual selected-model cost and client/server deadline behavior. The earlier live case's model, persistence, and plugin timings are stage measurements, not full gameplay latency.

Overhearing is default-off and can remain experimental for a scoped beta. The site-discovery route may also remain experimental if the beta scope selects a different validated route. The available evidence does not establish general provider reliability, vanilla Skyrim rank changes, audible playback, or the other untested gameplay paths above.
