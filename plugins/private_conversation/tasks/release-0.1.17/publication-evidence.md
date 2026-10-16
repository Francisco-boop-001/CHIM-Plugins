# Private Conversation 0.1.17 publication review

Date: 2026-10-09. User authorized commit, push and publish Private Conversation only. Lead reviewed and performed release operations; product changes were delegated to the existing gpt-6-luna Max owners using Ponytail FULL.

## Dedicated release — accepted

Release source: `d8ef51c199a7be90415893e10c190c19e63024ad`; immutable annotated tag `private_conversation-v0.1.17`, tag object `1f5bc72fe61ed07b62d8a4b6a6772474806e1db8`. Canonical root tree: `7c15efd27a96e1875e24256f900421797c116e4f`.

Published PRE-ALPHA release: https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.17

Lead reviewed every product diff, builder failure boundary, package verification output, source owner's report and independent reviewer report. Four focused package checks passed. Two candidate builds and an independent clean committed-source export build are byte-identical. The remote tag points to that exact clean-built commit. Four authenticated draft downloads and four fresh anonymous public downloads match approved asset bytes; the downloaded checksum list validates the three package assets. Published release has exactly four assets, is not a draft, and remains a prerelease.

| Asset | Bytes | SHA256 |
|---|---:|---|
| SHA256SUMS.txt | 296 | 48c718b421143728f9e6a09be42e32c6c313f81a34db6a26a8c7e61606ca8e9a |
| private_conversation-0.1.17-mo2.zip | 3054196 | c338af8b21a2fe82f762f773ba88f7a60b04e9043684038373500c81107b8121 |
| private_conversation-0.1.17.dwpkg | 3463704 | 9a8784473844a47e03494de8837d6e00de21d8de91cc9029804503c7ef4c15f7 |
| private_conversation.tar.gz | 3051189 | 90d5b48fa887c731bbfb8fbf147fe129dbf4998877f99edf68f5cc6711615d5b |

The MO2 ZIP contains exactly root `meta.ini` and `CHIM/server-plugins/private_conversation/0.1.17.dwpkg`. Parsed/byte metadata, CRC, embedded DWPkg identity, internal package checksums and manifests passed. Metadata contains `[General]`, `version=0.1.17`, `validated=true`, with LF endings.

Canonical main advanced only after versioned public downloads were verified. The public main manifest reports 0.1.17 and the tagged install guide resolves. Public API confirms every 0.1.16 asset retains its original SHA256 digest and size. Existing tags/assets were not replaced. Final 20-file server comparison found only manifest release metadata differs; no runtime code or compatibility pin changed. The version bump necessarily changes package manifest/checksum bytes compared with 0.1.16.

## Hub — accepted and published

Published hub commit: `5d868c25806064a59ae7c651e333e7304940b1d2` on https://github.com/Francisco-boop-001/CHIM-Plugins . Parent: `d04e84f809affa40318fe4e34c4de4162a1b9d96`.

Pre-push guards detected concurrent Mind Poisoning 0.1.19 publication and its follow-up evidence commit. The owner resolved only the three documentation conflicts onto `d7e513c48c37232801cf46d73a5ac119004fb78e`; the lead reviewed the full PCV diff and preservation proof. A second clean Git rebase preserved the evidence-only `d04e84f` update. The previously reviewed three-doc blobs and canonical PCV subtree were unchanged by this second rebase. All pushes were non-forced.

The committed `plugins/private_conversation` tree equals the immutable canonical release root `7c15efd27a96e1875e24256f900421797c116e4f`. There are 36 changed paths: 33 in that PCV snapshot and only README.md, docs/deployment-migration.md and docs/development.md outside it. The hub source was exported from the release commit, excluding untracked working files. The newer MP 0.1.19 content and all other hub files are preserved. Public hub README resolves PCV 0.1.17 and MP 0.1.19, and its PCV manifest reports 0.1.17. No duplicate hub release was created; the current dedicated-release convention remains.

Final judgment: accepted for the authorized packaging-only PRE-ALPHA publication. Release source, deterministic packages, uploaded/public bytes, mutable update pointers, exact hub snapshot and concurrent-work preservation have required evidence. No blocking source/package/publication defect remains.

## Scope and remaining runtime limitations

Only Private Conversation packaging, release identity, installation documentation and its hub snapshot/pointers are in this release. Mind Poisoning authoring and installed modlist/server/database/provider/game environments were untouched. Unrelated historical scratch and binary evidence were not staged. `tasks/todo.md`, the release plan and review records track the work.

Package/source/publication proof is not MO2 native UI, installation, Skyrim client synchronization or gameplay proof. Those were not performed; `validated=true` is metadata and does not establish warning-free UI or successful CHIM sync. PRE-ALPHA maturity remains.
