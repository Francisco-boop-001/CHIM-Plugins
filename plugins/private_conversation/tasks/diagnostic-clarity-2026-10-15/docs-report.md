# Diagnostic clarity documentation report

## Result

Updated the PCV README and logging guide to separate completion-hook observation from speech generation, client delivery, playback and stop cause. Added two copyable investigation prompts that keep CHIM core delivery and MP subject matching in their respective ownership lanes.

## Changes

- `README.md`: removed the rechat-budget explanation as a fact, corrected the completion-hook and last-turn descriptions, and clarified that a matching ACK proves a line attempt rather than playback. The solo example now identifies a subject other than the reflecting actor (including Player), and the named-subject boundary is documented without changing historical release/version claims.
- `docs/logging-revision-2.md`: distinguished `postrequest_observed` from `request_unobserved`, limited joins to validated IDs, and documented the 2026-10-14 trace. The pair result is recorded as MP `no-subjects`; `reflection-no-subjects` is reserved for solo reflection.
- `docs/followups/chim-core-delivery-diagnostics-2026-10-15.md`: copyable CHIM/client prompt focused on provider/JSON recovery, the 60-second idle timeout, native queue cancellation and supported per-utterance diagnostics. It explicitly forbids access to the stopped live distro and asks the separate owner to implement the smallest confirmed fix with focused checks.
- `docs/followups/mind-poisoning-subject-diagnostics-2026-10-15.md`: copyable MP prompt separates incoming Player-ID absence from NPC ACK validation, preserves the pair `no-subjects` and solo `reflection-no-subjects` codes, and requests the smallest confirmed diagnostic fix. Relationship-context resolution remains a separate, unapproved design question.
- Both handoffs specify gpt-6-luna Max effort with Ponytail FULL, distinct ownership, shared-work preservation, lead review without lead product edits, minimal meaningful checks, and no release/live writes. Any WSL fixture must explicitly select DwemerAI4Skyrim3-test and verify `WSL_DISTRO_NAME` before running; the stopped game distro is prohibited.

## Evidence and checks

- The observed trace facts documented here are from the task's supplied read-only evidence: PCV 0.1.17, MP 0.1.19 and client 3.5.1 (2026-10-13), Caracas 2026-10-14. The report does not reproduce private dialogue or claim count-based per-ACK joins.
- Verified the current authoring MP `server/influence.php` SHA-256 as `4dced6b050a218dd7fcd87792cd625e1b31c28fbae0e19f4f276d4e84f82ce57`.
- In `DwemerAI4Skyrim3-test`, PHP 8.2.29, a temporary pure-PHP probe called that source's `findSubjects` helper with synthetic catalog/text data, without application bootstrap. It printed:

  ```text
  PASS relationship reference unresolved
  PASS player name resolves to player
  PASS named third NPC resolves
  PASS speaker and listener excluded, third NPC retained
  SUBJECT_PROBE_PASS
  PROBE_EXIT=0
  ```

  The temporary script was removed after the run. This confirms only the helper behavior for those synthetic cases; it is not a request, transaction, provider, database or gameplay test.
- `git diff --check -- README.md docs/logging-revision-2.md` returned exit 0 with no output. A PowerShell content check found zero trailing-whitespace lines and paired code fences in all five owned Markdown files; all four added local links resolve. A targeted scan found none of the retired “silent rechat means budget exhausted” or “spoke / ended without speech” claims.
- Final prompt-text corrections received a documentation-only check: `git diff --check` and exact distro/reason-code text scans; no WSL or behavioral reruns were performed.

## Limits

No live distro, game, provider, database, MP request, actual client queue or audio playback was exercised. Queue insertion/clear records and server lifecycle labels do not establish playback or cancellation cause. The separate follow-up prompts are prepared text only; they were not sent to another task or owner.
