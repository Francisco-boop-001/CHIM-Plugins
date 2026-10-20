# PCV confirmed critique fixes and 0.1.22 BETA release

Authorized 2026-10-20: fix D1, D2, D3 and R7; verify on the test distro only; commit, push and publish PCV. Base: `a7c7a418fc6a32aaec5d718dc6f3bb39216eb8b8`. CHIM core, SHARMAT and Mind Poisoning remain separately owned and unchanged. Existing 0.1.21 tags and assets stay immutable.

## Checkable scope and ownership

- Prompt owner (`docs_publication_verify`, Luna Max, Ponytail FULL): context hooks and their focused fixtures. The final action prompt must contain only enabled scene actions and scene targets; talk-only stays empty. Preserve history and execution filters. Use the real core action tag, capture order and formatter.
- UI owner (`hub_publication_verify`, Luna Max, Ponytail FULL): `server/index.php` and UI fixtures. Invalid scene cards must produce an accurate configuration error, with valid cards and CSRF behavior preserved. After the state producer is reviewed, update the timeout notice to its agreed reason.
- State owner (`autonomy_bgl_boundaries`, Luna Max, Ponytail FULL): `server/state.php`, necessary logger reason allowlists, state/resilience fixtures. Clock rollback must not persist an unreadable refusal marker. Unavailable evidence must not be described as observed departure; preserve the existing timeout and old readable states.
- Lead: planning, source and verification review, test-distro coordination/execution, Git/release operations. No product code. Return defects to their responsible owner.

The dedicated PCV development checkout is clean of tracked changes and on its existing feature branch. Preserve unrelated untracked evidence and all other projects. No speculative critique risks are part of this patch.

## Gates

- [x] Confirm repository rules, baseline, runtime window and protected boundaries.
- [x] Prepare focused regression fixtures and retain original RED output before product changes.
- [x] Implement D1, D2 and D3/R7 producer independently in exclusive files.
- [x] Complete the R7 UI consumer after the producer contract is settled.
- [x] Review every diff, connected caller, failure path and targeted GREEN output; fix findings with the same owners.
- [x] Prepare informative, sarcastic and self-deprecating user documentation and 0.1.22 BETA metadata after code acceptance; keep prior gameplay evidence distinct from this release's fixture evidence.
- [ ] Verify packages and deterministic clean committed-source build, exact payload/allowlist/checksums, and protected components.
- [ ] Publish new immutable release assets, verify fresh public downloads, then advance canonical/hub references non-force as applicable.
- [ ] Record final publication receipts, review and test-window release.

## Verification limits

Use only `DwemerAI4Skyrim3-test` for PHP/runtime execution, synthetic fixture data and isolated temporary directories. Do not use the gaming distro, installed plugin state, providers, gameplay or production databases. Source, executable fixtures, packages, public downloads and gameplay are separate evidence tiers. A successful package is not a fresh gameplay test.

## Review

The lead reviewed all four product changes and their connected paths. Focused regressions failed against the baseline and passed after correction, including a clean baseline-versus-fixed run of the corrected UI fixture. Cross-reviews found no supported code blocker; report wording/link corrections are complete. The player documentation and metadata are accepted. The candidate passed four package tests and 14 independent inventory checks; all 21 source hashes stayed stable during packaging. Clean committed-source rebuild and publication remain pending. See [verification.md](verification.md).

For the hub, use the PCV-specific clean clone refreshed from current public main on a new publication branch. Preserve the Mind Poisoning-owned clone and every non-PCV path. The hub mirror follows successful canonical release verification and must not overwrite newer MP work.

The test window was released to Department after all PHP checks, with explicit user-authorized coordination. See [the corrected runtime release receipt](runtime/runtime-release-corrected.json). The initial receipt is retained: PowerShell automatically parsed a timestamp and its string comparison falsely reported a mismatch; an exact UTC-tick/length comparison confirmed the recorded gaming metadata was unchanged. No live distro entry occurred.
