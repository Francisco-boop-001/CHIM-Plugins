# CHIM-Plugins hub preparation for Private Conversation 0.1.17

## Baseline and branch

- Hub remote: `https://github.com/Francisco-boop-001/CHIM-Plugins.git`
- Prepared branch: `release/pcv-0.1.17`
- Remote `main` baseline and local `HEAD`: `b1adf9a610e12d5d81d3cc72fb8f1b1c957b50ff`
- Baseline root tree: `3d715e255b3e420bfc8a7000c1fc0abfc28533bb`
- Baseline `plugins` tree: `aaacd231ad428110048bca69d729e52529a48519`
- Baseline `plugins/private_conversation` tree: `9fc95f48ad1d1cfa9b40fc2290b56f293e9cf6b5`
- Hub inspection found the collection repository's versioned PCV release tags stop at 0.1.8, while its distribution folder retains later historical PCV notes. The current README points to dedicated PCV releases. The prepared changes follow that current dedicated-release convention and do not add a duplicate collection release note.

## Prepared scope

Only these hub files are modified:

- `README.md`: advance the current PCV heading, release/checksum and tagged-document links, download table row, and support-guide link to 0.1.17. Describe it as packaging-only: MO2 metadata is added, runtime code is unchanged, and package metadata advances to 0.1.17.
- `docs/deployment-migration.md`: advance the current PCV candidate pointer and the old 0.1.4/0.1.5 migration destination to 0.1.17. Retain the historical explanation of the 0.1.14 manifest and 0.1.8 bridge.
- `docs/development.md`: advance the current PCV release pointer to 0.1.17 while preserving the Mind Poisoning 0.1.18 references and other historical content.

The diff is 9 insertions and 7 deletions across those three files. The `plugins` tree, including the PCV subtree, still matches the baseline hashes above. No release-note mirror, catalog change, or other plugin content was added.

## Gate and limits

`git diff --check` passes. The working tree contains only the three documentation modifications on `release/pcv-0.1.17`. No commit or push has been made. The PCV source subtree has not been copied: this remains pending the accepted canonical source commit SHA and lead gate.

The 0.1.17 URLs are prepared pointers, not evidence that the dedicated release or its assets are published. This hub review does not verify package bytes, MO2 UI behavior, client sync, or gameplay. Mind Poisoning 0.1.18 and unrelated hub content were preserved in the prepared diff.
