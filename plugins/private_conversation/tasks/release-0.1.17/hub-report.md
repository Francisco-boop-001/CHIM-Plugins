# CHIM-Plugins hub preparation for Private Conversation 0.1.17

## Baseline and branch

- Hub remote: `https://github.com/Francisco-boop-001/CHIM-Plugins.git`
- Prepared branch: `release/pcv-0.1.17`
- Original clone baseline and original commit parent: `b1adf9a610e12d5d81d3cc72fb8f1b1c957b50ff`
- Original root tree: `3d715e255b3e420bfc8a7000c1fc0abfc28533bb`
- Original `plugins` tree: `aaacd231ad428110048bca69d729e52529a48519`
- Original `plugins/private_conversation` tree: `9fc95f48ad1d1cfa9b40fc2290b56f293e9cf6b5`
- Fetched concurrent `origin/main` for rebase: `d7e513c48c37232801cf46d73a5ac119004fb78e` (root tree `9f1cf7b25536fa2c34a4b82e75ff7f9fc372e7ff`; `plugins` and PCV trees unchanged from the original baseline).
- Hub inspection found the collection repository's versioned PCV release tags stop at 0.1.8, while its distribution folder retains later historical PCV notes. The current README points to dedicated PCV releases. The prepared changes follow that current dedicated-release convention and do not add a duplicate collection release note.

## Prepared scope

The hub root documentation changes are limited to these files:

- `README.md`: advance the current PCV heading, release/checksum and tagged-document links, download table row, and support-guide link to 0.1.17. Describe it as packaging-only: MO2 metadata is added, runtime code is unchanged, and package metadata advances to 0.1.17.
- `docs/deployment-migration.md`: advance the current PCV candidate pointer and the old 0.1.4/0.1.5 migration destination to 0.1.17. Retain the historical explanation of the 0.1.14 manifest and 0.1.8 bridge.
- `docs/development.md`: advance the current PCV release pointer to 0.1.17 while preserving the Mind Poisoning 0.1.18 references and other historical content.

The documentation portion is 9 insertions and 7 deletions across those three files. The `plugins/` directory in the hub contains only `private_conversation`; there is no sibling plugin subtree in this collection snapshot. The Mind Poisoning references and other existing hub paths remain unchanged.

## Gate and limits

The accepted canonical source commit is `d8ef51c199a7be90415893e10c190c19e63024ad`, with root tree `7c15efd27a96e1875e24256f900421797c116e4f`. I created the PCV hub subtree from `git archive` of that commit, not the canonical working tree. The final `plugins/private_conversation` tree exactly equals the canonical root tree (`7c15efd27a96e1875e24256f900421797c116e4f`).

The rebased commit tree is `65afdd7a35b7593fab8553f18b7f82065804f161`. Its 36 changed paths contain 33 paths under `plugins/private_conversation` plus exactly the three accepted documentation paths. Relative to the new `origin/main`, ten other root paths and six other children under `docs/` retain their baseline tree/blob hashes. The `plugins/` directory has one child, `private_conversation`; no other plugin subtree was present to copy or alter. The rebased diff check passes.

The branch was rebased to preserve the concurrent Mind Poisoning 0.1.19 update in `origin/main`; no PCV subtree content changed upstream. The final hub commit on `release/pcv-0.1.17` is `e29553e864f73e4775f7c8d4b829c9d821d7e5b5` (`Publish Private Conversation 0.1.17 hub links and tagged source snapshot`), with parent `d7e513c48c37232801cf46d73a5ac119004fb78e`. The fetched `origin/main` remains at that parent, and the working tree is clean. No push has been made; the commit is awaiting lead verification.

The dedicated 0.1.17 release is public. The lead reports independently downloading all four public assets anonymously and matching their bytes to the approved assets; this hub review did not perform those downloads. This review does not verify MO2 UI behavior, client sync, or gameplay. Mind Poisoning 0.1.19 content from the concurrent update and unrelated hub content were preserved in the rebased commit.

## Lead publication completion

A second concurrent, evidence-only hub update d04e84f809affa40318fe4e34c4de4162a1b9d96 was preserved by a clean Git rebase. Lead verified the previously reviewed three-doc blobs unchanged and exact canonical PCV tree equality, then non-force pushed 5d868c25806064a59ae7c651e333e7304940b1d2 to main. Public hub README and source manifest checks passed. Full final review: publication-evidence.md.
