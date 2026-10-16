# Private Conversation 0.1.19 hub snapshot plan — 2026-10-16

## Scope and gate

Prepare a PCV-only update to the `CHIM-Plugins` hub after the explicit Mind Poisoning handoff and root gate. The authorized isolated Windows checkout is at the supplied fresh `main` base. Do not stage, commit, push, publish, or touch CHIM core or Mind Poisoning files.

Release source is fixed at commit `c7ec6cde8231ebb103ef7d443b09789eb4b29691`, root tree `dc76ae7ac1551622767135b47487b6b6118aaa43`. The retained clean export is `dist/release-0.1.19-clean/source.zip`.

## Ordered checks

- [x] Receive the explicit Mind Poisoning hub handoff and root gate, including the exact resulting hub `main` commit/tree.
- [x] Create a fresh isolated Windows checkout at that commit; verify clean status and record the base identity before edits.
- [x] Compare the hub's `plugins/private_conversation` payload against the exact tracked PCV release source and clean export; record file paths, hashes, and any intended hub-only layout mapping before editing.
- [x] Update only PCV hub documentation/pointers and `plugins/private_conversation`: link the normal published 0.1.19 ALPHA release and its five verified assets, set current discovery to the flat `chim-plugin.tar.gz`, and preserve the separate legacy wrapped-TAR/MO2 route.
- [x] Verify the published Mind Poisoning 0.1.21 pointer remains intact; do not edit its subtree or shared core metadata.
- [x] Review the final diff and public links. Confirm every change is within the PCV documentation clauses, `plugins/private_conversation`, or this task's report; confirm the PCV payload matches the release source and MP/core paths are unchanged.
- [x] Record base/final identities, exact changed paths, PCV tree comparison, release URLs/assets, and verification results in `hub-report.md` for root review.

## Completion boundary

The PCV-only hub snapshot is published. Root staged and reviewed the allowlist, committed the snapshot, pushed without force, and verified the public main ref. Final commit: 301dce06b7e0559180a38449aaba7ba4551ff4a4 (parent 3feee95f9754b05902da8b77efc7d8454f4fb754, tree 9012b1ecae0d4d07af9627a225bb611303ba4532). The plugins/private_conversation subtree matches the pinned 0.1.19 release tree exactly. Git-object preservation and its evidence limits are recorded in hub-report.md, hub-verification.md, and hub-evidence.json. No beta/runtime claim is made.
