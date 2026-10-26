# PCV 0.1.24 publication record

PCV source acceptance, the paired Department gate, the clean build, authenticated draft checks, and anonymous public-byte checks passed. The source tag and installable payload remain immutable. This is database-backed and package evidence, not a new installation, provider, HTTP-delivery, or Skyrim gameplay result.

| Boundary | Exact result | Receipt |
| --- | --- | --- |
| Owner source | `ce0a998773237a9fc91f6914725a327d9e621718`; annotated tag `private_conversation-v0.1.24` | [Source acceptance](../source-acceptance.json) |
| Clean build | All five assets reproduced the tested candidate byte for byte | [Comparison](clean-build-comparison.json) |
| Canonical draft | Five authenticated downloads matched the clean build | [Draft metadata](draft-canonical-metadata.json), [hashes](draft-canonical-hashes.json) |
| Canonical public | Release `424753792`; five anonymous downloads matched; draft=false, prerelease=false | [Metadata](public-canonical-release.json), [hashes](public-canonical-hashes.json) |
| Paired Department | Source `cc33082885aa938d588eeaf5462e67b557ab282e`, release `424756304`, version `0.1.0-prealpha.20` with companion `0.1.0-prealpha.11`; its owner pin is the exact PCV source above | [15-case migration](department-overlay-migration-results.json), [public audit](department-public-publication-audit.json) |
| Actual archive consumer | Unmodified official CHIM archive validator accepted all three exact new DWPkgs; no activation or database operation | [Consumer results](department-package-consumer-results.json), [command/output](department-root-archive-consumer-command.json) |
| Hub bridge | Release `424760356`; five authenticated draft and five anonymous public downloads matched the same clean build | [Draft hashes](draft-hub-hashes.json), [public metadata](public-hub-release.json), [public hashes](public-hub-hashes.json) |
| Hub source | Release-tag commit `92ac64b3d71b63457b6b618766cd8dfd032a2e13`; full 21-file server tree equals owner tree `3309929ea7c7572892e711ee97969d02997dbf5a`; non-PCV tracked paths preserved except the approved PCV lines in root README | [Scope](hub-publication-scope.json), [release delta content comparison](hub-delta-content-check.json) |

The owner and Department drafts were both verified before either publication. Owner assets became public first, Department assets followed, then main/updater refs advanced after both public checks. The hub bridge uses the identical owner assets. Old tags and assets were not replaced. Department prealpha.18/.19 remain pinned to PCV 0.1.23; use the matching prealpha.20 release for PCV 0.1.24.

- [Canonical PCV release](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.24)
- [Matching Department release](https://github.com/Francisco-boop-001/CHIM-DepartmentOfQuestionableDecisions/releases/tag/v0.1.0-prealpha.20)
- [PCV hub bridge](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/private_conversation-v0.1.24)

The retained verification report distinguishes the six expected old-code reproduction assertions, 18 accepted owner PostgreSQL assertions, six exact overlay assertions, five PHP fixture files, eight syntax checks, and four package checks. Earlier failed fixture attempts remain available. Do not count archive validation as installation or extend historical 0.1.20 playback to this runtime-changing version.

All controlled native commands used the literal TEST2 target and fresh private-network clusters; clusters were stopped, owned scratch removed, and TEST2 restored stopped before the lease was released. A worker's mistaken legacy Windows bash syntax invocation is separately disclosed in the verification record. The live gaming distribution remained stopped at the retained snapshots, with unchanged VHD size/write metadata; those metadata are not a VHD content comparison. No core, SHARMAT, or Mind Poisoning source was modified.

Later current-guide/evidence commits are documentation only. They do not move the immutable release source tag or change its 21 server payload files. The hub preserves historical adaptations and mirrors this release delta; this record does not claim that every historical hub file equals the complete canonical repository.
