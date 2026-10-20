# PCV 0.1.22 documentation review

## Scope and source

This review covers the assigned README, player guide, distribution note, GitHub release body, and `server/manifest.json` only. The source baseline is `a7c7a418fc6a32aaec5d718dc6f3bb39216eb8b8`; the lead-owned [verification record](verification.md) reports the accepted focused checks. No documentation edit claims that those checks are a full CHIM, provider, installation, or gameplay run.

## User-facing claims checked

- The manifest changes only `version` to `0.1.22` and its short description. Schema, development-candidate status, candidate channel, config URL, and compatibility reference remain preserved.
- Current install links and displayed package versions in the README point to the `private_conversation-v0.1.22` tag and use the existing asset names and paths. Prior release notes and 0.1.20/0.1.21 evidence are retained as history.
- The action text explains that the Available Actions section uses the rebuilt list after PCV filtering and that talk-only remains empty. It does not promise selection or execution.
- Scene-card guidance says one line, at most 300 characters, and that an invalid edit is rejected before staging with the requirement shown.
- Clock guidance distinguishes the new refusal timestamp clamp from recovery of an older invalid marker, for which the guide directs the player to End now and to wait for confirmed off status.
- Auto-end wording distinguishes the five-minute consecutive failed-check interval from the result of the latest check. `could not verify eligibility` is not physical departure evidence; missing or stale reports may still follow the normal ineligible path.
- Prior 0.1.20 playback/operation evidence remains historical. The 0.1.21 byte-equivalence claim is explicitly limited to that metadata-only release and does not extend to runtime-changing 0.1.22.

## Evidence and release gates

The verification record reports focused test-distro PHP fixture RED/GREEN outcomes and syntax checks using synthetic inputs. It records no fresh install, full CHIM/provider execution, native playback, or Skyrim gameplay. The release body and README use absolute GitHub links for the intended `private_conversation-v0.1.22` tag; those are release targets, not a claim that the tag or assets are already public. Local links to the verification and historical acceptance records resolve in this checkout.

I did not run tests or build packages for this documentation task. Package integrity, clean tagged-source rebuild, checksum/download verification, publication, and manifest advancement remain separate lead-owned release gates. No CHIM core, SHARMAT, or Mind Poisoning behavior is changed by these docs.
