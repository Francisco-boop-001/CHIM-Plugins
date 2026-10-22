# PCV confirmed reliability fixes

Baseline: 3cd9f840ec5ce3d66da1a5e72252c1d28929c6a5. The user authorizes the previously recommended PCV fixes, test-distro checks, commit, push and new publication. Existing dedicated PCV feature checkout is reused; protected components and earlier releases remain unchanged.

## Global constraints

- Lead reviews and publishes; no lead-authored product code.
- Reuse the three responsible gpt-6-luna Max agents; Ponytail FULL; preserve others' shared edits. No nested agents or agent commits.
- Exclusive file ownership below. No runtime until the MP coordination handoff. Only DwemerAI4Skyrim3-test; no installed plugins, DB, provider, game, core, MP or SHARMAT changes.
- First prepare targeted regressions, retain genuine baseline RED, then implement and retain GREEN. Lead runs coordinated tests and reviews callers/output. No optional broad suites.
- New release is immutable; no old tag/asset replacement; version identity is selected after remote preflight. Useful sarcastic player guidance, no README changelog.

## Ownership and checkable tasks

- [x] Task 1 / autonomy_bgl_boundaries: server/state.php and eligibility-focused tests. Consume a rejected pending replacement once, preserve valid current scene and configuration, refuse that one input without retargeting or renewing; next Standard rechecks current scene. Preserve fail-closed active-invalid handling and valid pending activation. Update old pending-preservation assertion. No scope.php or UI edits.
- [x] Task 2 / certify_routing: server/scope.php, server/preprocessing.php and directly necessary context hooks; scope/state-recovery/wrap tests. Expired-only readable unknown-playthrough state must not block; live/corrupt/unsafe state must still fail closed without unknown-identity writes. Wrap suffix always becomes scene direction while retaining included-player audience; ordinary player speech unchanged. PCV wrap no-reply sentinel wins over SHARMAT listener pin. No SHARMAT changes.
- [x] Task 3 / docs_publication_verify: server/index.php, server/assets/ui-refresh.js and page/log-access/UI tests. Correct stale page fixture; accurate Free cap and Group voice copy. Restrict gateway log access to positively identified supported WSL; preserve direct loopback and trusted REMOTE_USER, reject forwarded/non-WSL gateway access. Report topology proof limits; no new auth system.
- [x] Lead: coordinate MP lease, run baseline RED and focused GREEN in TEST; review every diff and actual caller/failure modes; return defects to owner. Cross-review after all patches; no broad reruns without a concrete remaining concern.
- [ ] Documentation/release follow-up, assigned only after code acceptance: current guidance, evidence, metadata, packages, clean committed-source comparison and public download checks; preserve all non-PCV hub paths.
- [ ] Lead: explicit staging, source commit, clean rebuild evidence, new immutable tag/release, canonical/hub push and final receipts after gates pass.

## Conflict and interface scan

| Tasks | Boundary | Ruling |
| --- | --- | --- |
| 1 / 2 | state result consumed by scope | Existing unavailable reason retained. Task 1 clears failed pending only; Task 2 does not alter pending policy. Any new result contract must be agreed before implementation. |
| 2 / 3 | routing outcome presented by UI | Preserve existing reason names; UI tests inspect truthful current text. |
| 1 / 3 | state.php vs UI Free cap | UI reads existing cap; no schema change. |
| 1 | failed replacement vs current scene | Reject input once; preserve current state, clear failed queue; no TTL renewal on refusal. |
| 2 | wrap direction vs included audience | Direction attribution changes; player inclusion does not. Wrap closes PCV reply chain, not native SHARMAT scene. |
| 3 | security vs Windows host access | WSL identity is necessary, not proof about arbitrary NAT; reject unverified environments and state remaining deployment assumption. |

## Review focus and stopping rule

Rejecting a queue must not consume the active scene or redirect the failed input. Unknown identity must not clean another playthrough's state. Included-player wrap remains a direction. Valid external pins survive ordinary turns but never override PCV wrap close. Log gateway access must not become a general private-network exception. Stop testing when these changed contracts and their connected existing checks pass and review finds no unresolved material defect. Synthetic PHP/HTTP checks are not Skyrim playback proof.

## Progress

Plan and contract approved by user request. Baseline tracked clean; pre-existing untracked evidence preserved. All scoped runtime gates and source reviews are accepted; TEST has been released. Metadata and publication gates remain.

## Review

Lead acceptance: the targeted failures were reproduced before fixes. All six product-file diffs and their callers, failure paths and fixtures were reviewed; defects in the first pending-map and wrap-prefix patches were returned to the responsible agents and corrected. Independent cross-reviews found no actionable remaining blocker. Nineteen selected PHP fixture files, fourteen host-only Node DOM cases and five PHP syntax checks passed. TEST is released; no installation, provider or gameplay proof is claimed. Packaging and public readback remain separate gates.

Ruling: Kernel identity cannot distinguish Docker under WSL2 or prove original client identity behind NAT. Remove automatic gateway admission instead of adding heuristic WSL detection; retain direct loopback and server-authenticated REMOTE_USER. The supported Windows-host route may require existing web-server authentication. No new authentication system is introduced. This intentionally narrows an unsafe address exception and supersedes Task 3's proposed WSL predicate.
