# Mind Poisoning 0.1.25 BETA CANDIDATE — evidence and limits

The 0.1.25 candidate keeps the **BETA CANDIDATE** label. Standard GitHub classification (`prerelease=false`) supports CHIM topic discovery; it is not a maturity promotion. This page combines bounded 0.1.25 compatibility evidence with explicitly historical checks for earlier versions. Source and native fixture results do not establish a published installer, provider, or gameplay outcome.

## Completed disposable checks from 0.1.20

### B1: native PostgreSQL persistence — 10 scenarios passed

The campaign used Mind Poisoning 0.1.20 product source, the pinned CHIM core reference, synthetic event and relationship rows, and a fresh disposable PostgreSQL cluster. It exercised combined affinity/type and type-only commits, Player aliases, exact timeline/history snapshots, rollback after a snapshot-verification failure, sequential and barrier-coordinated concurrent duplicate delivery, actual core `RelationshipLLM` locking/rebasing in both call orders, stale type/scope/lock refusal, backend termination before commit, and a lost commit acknowledgement after the server committed.

The duplicate-delivery cases establish at-most-one persistence of the tested synthetic judgment. They do not establish once-only model execution or billing. The commit-acknowledgement case preserves the distinction between a confirmed commit and an unconfirmed outcome.

### B3: server install, upgrade, dashboard, and recovery — 9 scenarios passed

The campaign installed public package 0.1.20 through CHIM's actual package upload API, verified all 17 active server files, upgraded 0.1.19 to 0.1.20, and checked the old-extension backup plus preservation of synthetic database/configuration state and an unrelated file. It tested the actual standalone dashboard's loopback/authentication and method gates, CSP, and filtered JSONL download of one real synthetic request-log record.

Pause, relationship-feature Off, and interaction Off each made zero model-callback calls; an enabled control reached a counting callback once and deliberately returned invalid JSON. Separate SQL snapshots showed no writes in all four controls. No provider was invoked.

The unmodified CHIM automatic-backup implementation produced a SQL dump that passed independent content checks and a strict restore into a new empty task-owned database. Ten imported native tables and seven sequence states matched the captured baseline. The environment-local `chim_meta.settings` table is intentionally excluded by the upstream backup; the campaign captured, reapplied, and independently compared those local settings. Six selected filesystem roots were restored and hash-checked. The actual dashboard then read the restored synthetic ledger row, affinity 99, and type `friend`.

These passed B1/B3 campaigns cover 0.1.20, not the 0.1.23 package. They remain useful evidence for the unchanged persistence and server-install paths; they are not a substitute for a 0.1.23 cold install or package check.

## 0.1.21 live observations and the alias defect

One installed 0.1.21 reflection-classified case with Private Conversation 0.1.20 produced a combined affinity and relationship-type change. Persistence and terminal records confirmed the commit, and a later dashboard view showed the resulting edge. This is one successful installed reflection case, not broad PCV integration or a reliability estimate. It does not establish review of the actual self-talk for semantic quality, PCV API selection, an independent SQL history snapshot, a core-editor screenshot, audio, full installed-file hashes, or all component versions. See the [public live-acceptance summary](live-acceptance-summary.md).

A separate installed 0.1.21 observation exposed a wrong-subject semantic defect: generic vampire wording matched the generated alias for the catalog character `Vampire Fledgling`, and a judgment was committed to that wrong subject. Persistence succeeded; subject selection was wrong. The public summary omits dialogue, actor/request/event identifiers, and local records.

The 0.1.22 working-tree fix filters exact `Vampire` and `Black` tokens from generated aliases across the existing first-word, phrase-before-`the`, and bracket-stripped candidate routes. It does not suppress exact catalog names, including `Vampire Fledgling`, `Black Widow`, or literal mononyms such as `Black`, `Guard`, and `King`. Other common-word names such as `May` and `Guard` can still need model semantic judgment. No pronoun-resolution, taxonomy, model-prompt, schema, or wider alias-engine change is claimed.

The focused working-tree regressions first failed on the reproduced generic-alias behavior and then passed with the fix. Four isolated test suites and three PHP syntax checks passed. Their exact scope, harness limits, and the Windows symlink skips are recorded in the [public 0.1.22 verification summary](verification-v0.1.22.md). This is isolated working-tree evidence, not an installed 0.1.22 result.

The earlier 0.1.21 reflection/API compatibility fixture and root entrypoint access-gate check remain limited local checks. The topic-discovery route requires a newer CHIM manager than pinned core `cf5030f`, and the 0.1.23 site-discovery install path remains unverified end to end. If duplicate extension folders or an unexpected route state is present, follow the [duplicate-install recovery steps](deployment-migration.md#mind-poisoning-duplicate-install-recovery) before sending game requests.

## 0.1.23 relationship-context addition

The working-tree change passes the evaluating owner's existing relationship edge for a named subject into ordinary, reflection, and overhearing evaluation context. It can include the existing `relation`, `note`, `best`, and `worst` strings, each valid nonempty value bounded to 160 UTF-8 bytes. Invalid optional fields are omitted independently. The change adds no model call, relationship label, or persisted state, and leaves the existing affinity/type, speaker-bias, and listener-credibility inputs unchanged.

These snippets are untrusted hints that may be stale, incomplete, truncated, or asymmetric. They are not event evidence or a mutual agreement; attraction or expectations written from one perspective do not establish reciprocal feelings or exclusivity. The model can still disagree or make no change.

The focused `relationship_context_test.php`, `relationship_type_judgments_test.php`, `influence_test.php`, `reflection_test.php`, and `overhearing_test.php` checks passed with the standalone Windows PHP CLI; targeted syntax checks also passed. These isolated tests do not call a provider or database. No provider evaluation, live database persistence, installed 0.1.23 package, or gameplay result is claimed. The prior context-comparison design is not treated as proof of the final 0.1.23 prompts or model behavior; see the [verification summary](verification-v0.1.23.md) for the exact boundary.

## Historical 0.1.23 follow-up criteria

- **B2:** Add an NPC-to-NPC conversation about a named third person with independent review of the actual NPC utterance, a core-editor/history crosscheck, and recorded current-candidate lock/scope/On-Off and save/load checks. Existing zero and older lock/off observations remain valid within their recorded scope; current-candidate checks are unrecorded, not failed.
- **B3:** Complete a cold 0.1.23 install or upgrade on the chosen supported route, checking installed-file/version hashes and CHIM/client versions. The passed B1 0.1.20 native PostgreSQL campaign (10 scenarios) and B3 0.1.20 server install/upgrade/dashboard/recovery campaign (9 scenarios) remain valid and do not need repeating; they do not validate a 0.1.23 package.
- **B4:** Record active-versus-paused client acknowledgement and next-turn timing, stalled-provider recovery and the next request, and actual selected-model cost and client/server deadline behavior. The earlier live case's model, persistence, and plugin timings are stage measurements, not full gameplay latency.

Overhearing is default-off and can remain experimental for a scoped beta. The site-discovery route may also remain experimental if the beta scope selects a different validated route. The available evidence does not establish general provider reliability, vanilla Skyrim rank changes, audible playback, or the other untested gameplay paths above.

## 0.1.24 restore-policy clarification

Version 0.1.24 adds explanatory text for `restore-policy` in the dashboard’s **Interactions** and **Logs** views and in the player guide. It retains the Player/NPC preflight, reflection, and persistence guards, evaluator/API behavior, schema, reason codes, filtered JSONL export, and existing filter semantics. The display explanation does not change the request record.

Use the recorded **Outcome**, `stage`, and `model_outcome` together. A preflight `not_called` result stopped before model work; the persistence backstop can reject after model work. In the inspected restore path, CHIM can retain newer relationship values while restoring older Mind Poisoning processing history (the deduplication ledger), risking a repeated effect. `restore-policy` describes the recorded event; it does not establish the current effective setting. Do not toggle it blindly: ask the CHIM administrator to review the policy and back up the database/configuration before any change.

The dashboard render check, PHP syntax checks, `manifest_update_check`, `site_runtime_compatibility`, and all eight packaging cases passed for the staged 0.1.24 source; all four candidate installer assets were built and verified locally. The standalone Windows PHP fixtures used `-n`, `mbstring`, and `pgsql` and made no network, database, or provider calls. Clean-tag rebuild and downloaded-asset byte comparison are separate publication gates; the staged-source checks do not establish installed-package behavior, provider behavior, database persistence, or gameplay. The 0.1.23 focused checks and 0.1.20 B1/B3 campaigns remain historical evidence for their recorded scope; they do not verify 0.1.24.

## 0.1.25 preserve-mode compatibility

The 0.1.25 candidate adds an INIT source-row fence and a mismatched-scope baseline when CHIM's effective `NEVER_CLEAR_RELATIONSHIP_DATA` policy is enabled. A baseline preserves loaded relationship values, advances the MP floor to the current maximum event row, clears imported MP event/judgment/reflection context, and skips its triggering event. It can expire already-inserted pending source rows. For a matching loaded scope, the plugin does not replace the namespace with a baseline, but CHIM may have restored an older MP ledger; the separate INIT fence expires source rows from before the load. The disabled-policy path is unchanged. New event IDs are new input, not semantic duplicates.

Focused Windows fixtures passed for ACK, Player input, reflection v2, overhearing boundaries and per-recipient baseline, policy changes, PostgreSQL-shaped string NPC IDs, and dashboard reason rendering. A bounded native fixture also passed against pinned CHIM 3.5.1 revision `57f292415b008cfaf1a4531f0654d2563362ce70`, using a disposable PostgreSQL 15.14 database and PHP 8.2.29. It exercised the actual pinned INIT pruning and NPC-restore order, production store boundaries, synthetic writes and exact retry, a method-level store/INIT lock ordering case, baseline and rollback, and a dump/restore projection. The store committed event 3005 and classified its retry as duplicate; the method-level ordering case kept the relationship delta/receipt while INIT waited; the baseline projection matched after dump/restore.

The native fixture was not a full ACK request. It made no provider/model call and does not establish full concurrent-ACK behavior, broad crash/race handling, multi-host serialization, client retry behavior, installed package behavior, or gameplay. The exact setting label, UI location, and default remain unverified. Source/package-export, clean-tag rebuild, and publication-byte checks are separate release gates. The candidate remains **BETA CANDIDATE**.
