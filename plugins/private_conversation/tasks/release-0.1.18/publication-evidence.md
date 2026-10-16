# Private Conversation 0.1.18 publication evidence

Published 2026-10-16 01:39:21 UTC (2026-10-15 21:39:21 Caracas) as the GitHub **ALPHA prerelease**: [private_conversation-v0.1.18](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.18). GitHub reports `draft=false`, `prerelease=true`, and exactly four assets.

## Source and assets

Source commit `5c8b317ceeb950178ca693df6646c174849e39aa` has tree `893b09c40602674d3c5e468b884cfb2efac5ea1c`. Annotated tag `private_conversation-v0.1.18` (object `91fe32f5fd26e55598a90a2601498bf34d315570`) peels to that commit.

The four package tests and four targeted PHP fixtures passed; the fixtures ran in the explicitly asserted `DwemerAI4Skyrim3-test` distro on PHP 8.2.29. Candidate builds A/B and the independent clean archived-source rebuild match byte-for-byte across all four assets. The clean source archive SHA-256 is `26e8497c791c11c2f10680fd216c59550d7025548809959385617ec878beee3b`.

All four authenticated draft downloads and four fresh anonymous public downloads matched the clean-build sizes and SHA-256 digests; the public release API reports the same four assets. Exact asset values and package-layout checks are in the [package report](package-report.md). The final README and release-note wording corrections happened after candidate builds, but those documents are outside the package allowlist; manifest and packaged runtime bytes stayed unchanged. The previously published 0.1.17 assets retain their original sizes and digests.

## Hub snapshot

Hub commit `826565d3241de5dbb5640584855b98ca912ea0e9` has parent `5d868c25806064a59ae7c651e333e7304940b1d2`. Its PCV subtree equals source tree `893b09c40602674d3c5e468b884cfb2efac5ea1c`; only the 35 approved paths changed. All 231 protected objects matched, with no missing files or hash mismatches. The lead pushed `main` non-forced and verified the public README and snapshot manifest point to 0.1.18 ALPHA. No duplicate hub release was created; see the [hub report](hub-report.md).

An automatic-review refusal prevented deleting a shared maturity sentence that also described Mind Poisoning. The lead preserved that status and changed only the PCV clause; the final hub review and preservation checks passed.

## Maturity and remaining limits

This is an ALPHA publication, not stable or beta. Earlier named Skyrim sessions and disposable-clone/provider runs remain evidence only for their recorded versions and paths. No new provider call, live server/database access, install, or Skyrim session was performed for this release; the gaming distro `DwemerAI4Skyrim3` was not invoked. The focused fixtures ran only in the explicitly selected `DwemerAI4Skyrim3-test` distro. The END failure fixture verifies caller behavior with stubbed stage results; it does not reproduce a storage fault or establish live HTTP/logger behavior. Package and queue evidence do not prove speech playback, universal privacy, every install route, or a complete end-to-end acceptance run. CHIM core and Mind Poisoning authoring remained outside this release.

All authorized source, package, immutable-tag, draft/public-download, and PCV-only hub gates are complete. No release blocker remains.
