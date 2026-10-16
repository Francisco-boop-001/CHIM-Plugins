# PCV working-tree ALPHA and END failure-safety plan

> **For agentic workers:** Scene implementation is owned by scene_assessment; this subtask updates docs and reviews evidence inline.

**Goal:** Fix fail-closed END handling and record an evidence-backed ALPHA assessment for the unpublished working tree, while retaining published 0.1.17 as PRE-ALPHA.

**Architecture:** The scene owner changes server/preprocessing.php and its focused fixture. I update only README, compatible manifest description, operator acceptance, and this task's records. Evidence separates synthetic checks, disposable clone/provider runs, actual Skyrim sessions, and unverified behavior.

**Tech Stack:** PHP fixture, Markdown, and JSON.

**Spec:** Lead task direction in the current thread; current evidence in tasks/live-issues-*.md, tasks/diagnostic-clarity-2026-10-15/, docs/followups/, and tasks/release-0.1.17/.

## Global Constraints

- Keep version 0.1.17, status=development_candidate, channel, label, URLs, and default candidate unchanged.
- Published 0.1.17 remains PRE-ALPHA and packaging-only.
- Do not claim stable/beta maturity, universal privacy, playback, or all install modes.
- No core/MP changes, packaging, install, commit, or publication.
- The scene owner alone runs END fixtures, explicitly in DwemerAI4Skyrim3-test with isolated state; never start or access the gaming distro.

## Review Focus

- On state-disable failure, END and wrap-up must not claim success or forward the control phrase; if the failure occurs after a write, the scene may remain active.
- Pair/group wrap-up must block before generating its closing turn when disable fails.
- Successful END, solo/pair/group wrap-up, and no-scene controls retain their accepted behavior.
- Separate current unpublished working-tree assessment from the published release and name each evidence surface.

---

### Task 1: END failure safety — owner: scene_assessment

**Files:** server/preprocessing.php, targeted END fixture, and scene-owner report.

- [x] Add RED cases for direct END, solo wrap-up, and pair/group wrap-up with active/pending fixture state and injected non-`off` stage results.
- [x] Assert no ended/off success is reported and control text is not forwarded; allow for a write before the stage result becomes unavailable, and retain success/no-scene controls.
- [x] Apply the smallest failure guard and run the fixture only in the explicitly named test distro with isolated state.

### Task 2: Maturity and acceptance docs — owner: evidence_assessment

**Files:** README.md, server/manifest.json, docs/operator-acceptance.md, tasks/todo.md.

- [x] Capture original target and server/ hashes and preserve existing diagnostic edits.
- [x] Correct current overview/mode/action descriptions and add a compact evidence matrix and remaining gates.
- [x] Describe END failure behavior from the scene owner's confirmed semantics, with fixture status separate from live evidence.

### Task 3: Review and handoff

**Files:** tasks/alpha-end-2026-10-15/{baseline.json,docs-report.md,final-review.md}.

- [x] Run bounded Windows-only JSON, Markdown-link, and scoped diff/preservation checks; no WSL/runtime command from this documentation owner.
- [x] Complete final-review.md after the lead's approval; record the fixture boundary and remaining runtime limits.
