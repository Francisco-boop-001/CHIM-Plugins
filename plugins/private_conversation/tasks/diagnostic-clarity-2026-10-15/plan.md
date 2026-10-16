# PCV diagnostic clarity — 2026-10-15

User authorization: fix what belongs to Private Conversation; CHIM core and Mind Poisoning remain separate. Use reused gpt-6-luna Max agents with Ponytail FULL. Lead reviews; writes no product code. User authorized testing in DwemerAI4Skyrim3-test and stopped the live game distro. No live execution/writes/start, no core/MP implementation, no deployment/publication/version or pin changes. Prepare separate handoff prompts; sending is not authorized by the latest answer.

Baseline source HEAD: 95a535010aa91aa80ad31065251c5e643855ce7a. Tracked tree initially clean; existing historical untracked scratch is excluded.

Ruling: the user's fix instruction accepts the preceding scoped diagnostic recommendation. This is a bounded correction of existing misleading UI/docs, not new delivery infrastructure. postrequest_observed proves a completion hook; request_unobserved means a missing hook, not silence or any particular stop reason. Preserve event/outcome codes and last_turn schema. No runtime log scraping, new telemetry subsystem, invented utterance IDs, relaxed ACK validation or global subject alias.

## Ownership and checkable tasks

- [x] UI owner maintenance_state_http: server/index.php, focused existing/new UI regression and relevant misleading test comment only; ui-report.md. Correct both last-turn labels; preserve render escaping, JSON/state schema and scene routing. Smallest meaningful RED/GREEN render check in test distro. Own fixes returned by lead.
- [x] Docs owner maintenance_routing: README.md, docs/logging-revision-2.md, two docs/followups prompts; docs-report.md. Explain observed versus unobserved lifecycle and separate actual Player input skips from NPC cancellation/late output. CHIM core and MP prompts carry verified facts, scope and acceptance checks; no changes in those projects. Work independently of UI implementation, agreed semantics fixed above.
- [x] Independent reviewer mo2_package_review: review.md only; read-only product review after completed producers. Check all changed consumers, honest runtime boundaries, no scope spill, focused verification output. Return defects to original owner.
- [x] Lead: review every diff, call path, failure mode and check output; verify unchanged runtime routing/pins and stopped live distro; complete todo and final report with handoff links.

## Verification limits

Fixture/render tests only in test distro, isolated temp directories. No provider calls, actual playback test or live database/state changes. No broad unaffected suites or package rebuild. UI source and runtime fixture evidence remain distinct from native delivery behavior.

Review: final-review.md accepts the local source/UI/documentation change after focused render/helper evidence and independent review. Gaming VHD timestamp preservation remains unassigned; no live-disk-unchanged claim. No release or external implementation.
