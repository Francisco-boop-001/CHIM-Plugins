# Mind Poisoning 0.1.21 BETA CANDIDATE — evidence and limits

The 0.1.21 GitHub release is configured as a standard release (`prerelease=false`) so a newer CHIM manager can discover its `chim-plugin.tar.gz` asset. The release text and package metadata call it a **BETA CANDIDATE**. GitHub's release classification is a discovery requirement; it does not mean every beta gate passed.

## Completed disposable checks

### B1: native PostgreSQL persistence — 10 scenarios passed

The campaign used Mind Poisoning 0.1.20 product source, the pinned CHIM core reference, synthetic event and relationship rows, and a fresh disposable PostgreSQL cluster. It exercised combined affinity/type and type-only commits, Player aliases, exact timeline/history snapshots, rollback after a snapshot-verification failure, sequential and barrier-coordinated concurrent duplicate delivery, actual core `RelationshipLLM` locking/rebasing in both call orders, stale type/scope/lock refusal, backend termination before commit, and a lost commit acknowledgement after the server committed.

The duplicate-delivery cases establish at-most-one persistence of the tested synthetic judgment. They do not establish once-only model execution or billing. The commit-acknowledgement case preserves the distinction between a confirmed commit and an unconfirmed outcome.

### B3: server install, upgrade, dashboard, and recovery — 9 scenarios passed

The campaign installed public package 0.1.20 through CHIM's actual package upload API, verified all 17 active server files, upgraded 0.1.19 to 0.1.20, and checked the old-extension backup plus preservation of synthetic database/configuration state and an unrelated file. It tested the actual standalone dashboard's loopback/authentication and method gates, CSP, and filtered JSONL download of one real synthetic request-log record.

Pause, relationship-feature Off, and interaction Off each made zero model-callback calls; an enabled control reached a counting callback once and deliberately returned invalid JSON. Separate SQL snapshots showed no writes in all four controls. No provider was invoked.

The unmodified CHIM automatic-backup implementation produced a SQL dump that passed independent content checks and a strict restore into a new empty task-owned database. Ten imported native tables and seven sequence states matched the captured baseline. The environment-local `chim_meta.settings` table is intentionally excluded by the upstream backup; the campaign captured, reapplied, and independently compared those local settings. Six selected filesystem roots were restored and hash-checked. The actual dashboard then read the restored synthetic ledger row, affinity 99, and type `friend`.

## 0.1.21 route and remaining limits

The B1 and B3 campaigns tested the 0.1.20 package, not a 0.1.21 `chim-plugin.tar.gz` installation. The new root `index.php` entrypoint received a separate local-only access-gate check: a synthetic non-loopback GET defaults to the existing fixed-localhost handoff while preserving the requested path and filters; forwarded requests remain denied by the existing dashboard gate; explicit `local=0` remains denied; POST remains 405. This check does not load dashboard data and does not prove a live CHIM site install.

An isolated relocated compatibility check verified that `globals.php` exposes the existing reflection API before the vendored PCV loader snapshot runs, and that the dashboard parser accepts logs stamped with the exact release tag after logger normalization. Reflection API v1 and reply API v2 remain unchanged. The fixture uses a vendored PCV loader snapshot, not the full current PCV runtime; actual installed PCV integration remains unverified.

The topic-discovery route requires a newer CHIM manager than pinned core `cf5030f`, which does not contain topic discovery. Source review of a newer manager shows that a clean install uses `ext/CHIM-MindPoisoning`; when an existing folder has the same valid `git_repo`, the manager reuses that folder in place rather than renaming it. An unknown-source legacy folder may remain. This is source-review evidence, not a live migration guarantee. Inspect the manager result and stop if an unknown-source or duplicate folder remains; do not stack or delete folders blindly. The isolated companion-loader check is limited to its vendored snapshot and does not prove installed compatibility with the full current PCV runtime.

These checks used disposable synthetic data. They do not establish MO2 or native-client sync, a cold operator following the public guide, full CHIM service integration, provider behavior, real player data, Skyrim relationship state, audible speech, or gameplay. The 0.1.21 site-discovery route itself still needs an isolated end-to-end install check before that specific claim is made.
