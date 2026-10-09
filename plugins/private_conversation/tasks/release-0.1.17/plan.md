# Private Conversation 0.1.17 release

User authorization: commit, push and publish Private Conversation. Preserve prior public artifacts, unrelated work and all installed environments. Maintain PRE-ALPHA maturity. Lead reviews/orchestrates, writes no product code. Reuse gpt-6-luna Max owners and Ponytail FULL.

Baseline canonical HEAD/main: 1de11b22b7d8603143070f0f884e3c41eb28d054. Local branch fix/mo2-metadata-2026-10-08 contains reviewed MO2 metadata fix. Public latest PCV release is0.1.16; next proposed version0.1.17. Hub remote main b1adf9a610e12d5d81d3cc72fb8f1b1c957b50ff.

- [x] Source owner maintenance_state_http: update server/manifest.json to0.1.17, current PCV-only references in README, new distribution/private_conversation-v0.1.17.md. Preserve MP dependency references and all runtime files. Existing builder/test changes already accepted; fresh package gate after version update. Own source-report.md and fresh candidate evidence under this task. No commits/push/publication until lead gate.
- [ ] Hub owner maintenance_routing: inspect current hub convention in fresh projects/CHIM-Plugins-release-0.1.17 clone; prepare PCV-only README/doc updates. No PCV snapshot copy until source commit accepted. Preserve all unrelated plugin paths/content. Own hub-report.md. No commit/push until lead gate.
- [ ] Independent reviewer mo2_package_review: inspect accepted metadata diff and release source scope, then sequentially verify clean-source/clean-tag builds, manifests, actual archive content/reproducibility and preservation evidence. Own package-review.md and review evidence under this task. No product edits, duplicate broad tests or installed environment access.
- [ ] Lead review source diffs, focused test output and failure paths; explicit allowlist commit and immutable tag.
- [ ] Verify clean-tag rebuild and draft download bytes; publish dedicated release only after gates. Do not overwrite older tags/assets.
- [ ] Update plugin hub using existing dedicated-release links/source snapshot convention, verify exact PCV subtree identity and unrelated-tree preservation. Advance mutable main refs only after required versioned assets are public; no force pushes.
- [ ] Verify public release/ref/assets and hub links, record final judgment.

## Acceptance and constraints

Release contains only reviewed root meta.ini packaging fix plus accurate installation docs and release identity0.1.17. Three assets + SHA256SUMS retain existing formats/legacy consumers; no new distribution-format migration. MO2 ZIP exactly root meta.ini ([General],version0.1.17,validated=true) and CHIM/server-plugins/private_conversation/0.1.17.dwpkg, CRC/checksums/reproducibility pass. Server runtime bytes except manifest version match accepted baseline. Do not claim MO2 UI, native installation/update or new gameplay verification. README recommends one ZIP and clearly distinguishes old0.1.16 assets. No change to Mind Poisoning authoring, live mods/server/database/provider/game, compatibility pins or maturity.

Expected external actions: authenticated GitHub release/tag/main pushes and user plugin hub update, explicitly authorized. No maintainer messages/catalog submission.

Ownership: source metadata/docs and hub checkout are disjoint. Reviewer checks completed producers sequentially. Reuse original responsible agent for defects. Lead keeps plan/todo/review records; never stages untracked scratch, binary candidate/downloads or unrelated historical files.

Review: source preparation and candidate package gate accepted by the lead and independent reviewer; no blocking findings. Clean committed-source build, upload and publication checks remain.