# Working-tree maturity and acceptance documentation report

Date: 2026-10-15. This report assesses the current unpublished development tree. It does not change the maturity or release status of the published 0.1.17 candidate, which remains PRE-ALPHA.

## Assessment

The working tree is documented as **ALPHA**, based on a combination of versioned synthetic/source checks, disposable CHIM clone/provider runs using a simulated game client, and recorded Skyrim sessions. The evidence supports specific paths and named revisions; it does not certify stable/beta quality, every installation route, universal privacy, or speech playback. The full operator checklist remains incomplete as a single acceptance run.

The 0.1.17 gameplay record confirms pair rechat and solo reflection/MP events, while its client trace leaves per-chunk delivery and playback unresolved. Seven incoming-player `utterance_id_absent` events are distinct from seven unacknowledged NPC chunks with IDs. Six NPC chunks entered the native queue, but queue insertion/clearing is not playback proof; the seventh had no queue entry and the server fallback arrived after the client timeout. All 45 correlated NPC ACKs in the pair returned MP `no-subjects`; a separate solo event committed an opinion change. The named-subject contract and aggregate-word limitation are recorded in [logging revision 2](../../docs/logging-revision-2.md#observed-pair-trace-2026-10-14), with separate, prepared—not completed—[CHIM/client](../../docs/followups/chim-core-delivery-diagnostics-2026-10-15.md) and [Mind Poisoning](../../docs/followups/mind-poisoning-subject-diagnostics-2026-10-15.md) follow-ups.

The targeted END hook fixture passed its RED/GREEN sequence in `DwemerAI4Skyrim3-test`, with an explicit distro identity check and PHP 8.2.29. The regression uses injected stage outcomes and an in-memory state seam. It verifies that ordinary END and solo/group wrap-up block on an unconfirmed clear, that pair/group wrap-up does not proceed to its closing turn, and that successful END/wrap and an unarmed exact phrase retain their expected caller behavior. The existing command fixture also passed. This is caller-contract evidence, not a real storage-write fault, proof that persisted state is unchanged, live HTTP/logger output, or Skyrim failure-path verification; see the [scene-owner report](end-report.md).

## Documentation changes

- [README](../../README.md) distinguishes the unpublished working-tree ALPHA assessment from published 0.1.17 PRE-ALPHA, describes pair/group/free/solo modes, corrects the action-prompt description, and states the verified END failure contract without claiming persisted state is unchanged.
- [Manifest](../../server/manifest.json) changes only the description to identify the unpublished ALPHA assessment. Version, machine status, candidate channel/label/default, URLs, and compatibility reference remain unchanged.
- [Operator acceptance](../../docs/operator-acceptance.md) replaces the obsolete “manual checks not run / 0.1.7” header with a compact evidence/limits matrix, records that the full checklist is not an end-to-end pass, and incorporates the END failure contract and its fixture boundary.

## Verification and limits

Windows-only checks passed: manifest JSON parsed and all fields other than description matched `HEAD`; all new internal documentation targets and heading fragments resolved; `git diff --check` passed for tracked owned files; the task records had no trailing whitespace; and every baseline PCV server file other than the authorized manifest and END implementation matched its captured SHA-256, including the pre-existing `server/index.php` diagnostic edit. The baseline and pre-edit tree are recorded in [baseline.json](baseline.json).

This documentation owner ran no WSL command, PHP/runtime fixture, provider request, package build, installation, or gameplay test. The END fixtures were run by their owner as recorded above. The current tree was not packaged, installed, committed, or published. The lead approved the aggregate after independent review; see [final review](final-review.md).
