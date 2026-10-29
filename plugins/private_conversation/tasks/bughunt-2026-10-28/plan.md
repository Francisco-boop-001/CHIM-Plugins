# Offline PCV bughunt — 2026-10-28

Goal: find consequential, reproducible PCV-owned defects in the released0.1.24 source. Investigate before proposing fixes; preserve expected refusals and documented limitations. No product edits, publication, pin changes or new features.

Baseline: HEAD0c697793311b314e8158b122e3c2b718638ca9e0; runtime release sourcece0a998773237a9fc91f6914725a327d9e621718. Existing tasks/todo.md changes and unrelated untracked evidence predate this review and must be preserved. Department prealpha.24 separately repaired duplicate-hook transport packaging and nullable Solo projection; do not report those resolved external defects as fresh owner bugs.

Restrictions: latest explicit server/game restriction remains in force. Workspace-only source, retained evidence and bounded isolated host fixtures. No WSL/bash/launcher aliases, live/test server or database access, HTTP/provider calls, game access, installation or deployment. Do not execute historical integration scripts without inspecting their effects. Host PHP only via the explicit retained Windows PHP8.3.35 binary; fixture workspaces confined to this task/artifacts, mocked I/O only. Missing platform extensions are harness limits, not product failures.

Same agents: reused GPT-6-LUNA Max/Ponytail FULL, shared workspace, preserve others' edits. Exclusive new report/repro directories; never edit existing product/test files. Lead writes no product code and reviews real callers, failure modes and raw verification output. Return defects to the responsible agent.

- [x] State owner A: state transitions, identity/read failure, presence/eligibility and TTL boundaries; report/repros under this task/state/.
- [x] Routing owner C: input rewriting, isolation/current-context, group/free/solo action and response paths; report/repros under this task/routing/.
- [x] Reflection owner D: exact solo output/ACK registration, receipt/lease/error handling and terminal diagnostics; report/repros under this task/reflection/.
- [x] Lead: inspect UI/security/package consumers and previous acceptance claims; review each proposed finding and focused reproduction, rejecting intended behavior and fixture artifacts.
- [x] Consolidate severity-ranked confirmed findings with absolute source references, concrete triggers, expected/actual results, component ownership and smallest fix direction. Keep unperformed server/game checks explicit.

Testing rule: each executed check must answer a specific hypothesis. Reuse sufficient existing proof; no broad suites or test-for-test's-sake. A static suspicion is not a confirmed runtime defect. Stop optional testing when the consequential question is answered.

Review complete: two reproduced PCV defects, one reproduced browser-context risk needing an explicit targeting policy, and one source-only ACK candidate with a blocked integrated fixture. Lead inspected actual consumer paths and raw outputs, returned corrections to the same agents, and excluded intended Group narrowing. No product fix or external/runtime access. See report.md; no blanket test-pass, gameplay or security claim.
