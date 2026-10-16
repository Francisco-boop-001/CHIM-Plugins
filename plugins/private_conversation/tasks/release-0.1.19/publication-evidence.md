# Private Conversation 0.1.19 publication evidence

## Published source release

The 0.1.19 source release is published as an ALPHA at `https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.19` (release ID `415388759`, published `2026-10-16T21:25:32Z`). It is a normal GitHub release for CHIM Plugin Manager discovery: `draft=false`, `prerelease=false`. Those flags make it discoverable; they do not promote the ALPHA to beta or stable status.

The annotated tag `private_conversation-v0.1.19` is object `fa7d4599099fdd471ed00936517b7b0f019dbe04`, peeling to commit `c7ec6cde8231ebb103ef7d443b09789eb4b29691` with tree `dc76ae7ac1551622767135b47487b6b6118aaa43`. The tag is unsigned. The non-forced main advance to this commit was verified by root against GitHub. The CHIM core pin is unchanged.

## Published assets

All five public downloads match the clean build and the pre-publication draft downloads byte-for-byte. The current release API asset names, sizes, and digests match as well. The exact comparisons are in [public-verification](publication-evidence/public-verification/).

| Asset | Size | SHA-256 |
| --- | ---: | --- |
| `chim-plugin.tar.gz` | 3,051,196 | `2453de55c50c04c6a167432bb00d02eaa2e42ccf353ba64e71e3f3ebe99d141f` |
| `private_conversation-0.1.19-mo2.zip` | 3,054,306 | `6dcb27863fae36ab3c6deab5f8ba6255aea02a68a56af8c439df95a78774ebb9` |
| `private_conversation-0.1.19.dwpkg` | 3,464,673 | `d78a969480375d448afe347142ddd320c66a201ed01ab1e43d858aa490d2ccf6` |
| `private_conversation.tar.gz` | 3,051,279 | `921518e1a750fd2595ac030412fd05ec65909b7b10e5548766c27588f111b34c` |
| `SHA256SUMS.txt` | 381 | `096cce8e8dcbf72aa0085be890baee7d33814f8e33c0a3fd4fb60f4a1df2e56a` |

The public release metadata and latest-release pointer match the current GitHub API. The release body matches the committed `distribution/private_conversation-v0.1.19.md` exactly. The protected 0.1.18 release still has the same release ID, tag, flags, and four asset IDs/names/sizes/digests recorded before publication; the before/after snapshots and current API agree.

## Build and source checks

The clean export's original archive extraction, package build, candidate comparison, and commit-tree receipts are copied byte-for-byte under [clean-build-verification](publication-evidence/clean-build-verification/). The clean builder exited 0; the candidate/clean comparison reports all five assets byte-identical. No clean source archive, generated package, or private candidate package was copied into this evidence folder.

The owner-run package check reports four passing tests in [package-report.md](package-report.md). The focused serialized action-target fixture had a recorded RED before the one-expression fix and GREEN afterward, with PHP lint; the release body and [compatibility report](compatibility-report.md) retain that scope. The `_` logger/reader compatibility edit was source-reviewed but did not receive a PHP runtime check on the 0.1.19 release payload.

Root verified the source advance without force. Its staged-source whitespace review found the retained original console-output blank line at EOF; that one receipt was checked with the blank-at-EOF rule disabled. This is not reported as an unqualified default full-index whitespace pass.

## Runtime limits

No CHIM Plugin Manager install, MO2 import, 0.1.19 server runtime, native client test, or Skyrim gameplay/playback test was performed for publication. The full HTTP pair/END/ordinary/wrap/removal and public ACK paths, plus native timeout/recovery, remain NOT RUN. No gaming installation or WSL action was used for publication. Earlier focused test-distro evidence applies only to its stated fixtures and versions.

The package is an ALPHA prepared for a controlled live-installation test. The normal GitHub release flags support discovery and do not certify beta readiness.

## CHIM-Plugins hub promotion

**VERIFIED.** The non-forced hub update is public at commit `301dce06b7e0559180a38449aaba7ba4551ff4a4` (parent `3feee95f9754b05902da8b77efc7d8454f4fb754`, tree `9012b1ecae0d4d07af9627a225bb611303ba4532`). Root's fresh GitHub API read of `main` matched the commit. The staged index contained 37 changed paths: all 256 PCV release paths matched the exact release commit by path, mode, and Git blob ID; all 239 protected tracked paths outside the PCV subtree and three authorized shared documents retained their base Git-object identities. Root's public-file proof confirms the README, PCV 0.1.19 manifest, and MP 0.1.21 manifest match their published Git blobs and the hub commit; see [public file verification](publication-evidence/hub-public.json). See also the [hub report](hub-report.md), [hub verification](hub-verification.md), and [machine-readable hub evidence](hub-evidence.json).

The hub comparison proves Git path/mode/blob identity for the reviewed index; it does not prove that every pre-stage working-tree byte matched the base. This publication receipt adds no runtime or gameplay claim.
