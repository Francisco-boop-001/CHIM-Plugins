# PCV 0.1.25 local hub mirror preparation

Status: ready for lead review. The local hub checkout is prepared but remains unstaged and uncommitted. No hub tag, push, release, updater change, Department file, or pin was changed.

## Pinned inputs

- Hub: `publish/private-conversation-0.1.25`, based on `HEAD` and `origin/main` `2cf4d1740052e09b379a81acdd96c432f7054477`; tree `cc27fee101ed3f241a85cfdf04503e94f475400e`.
- Canonical source: `private_conversation-v0.1.25^{commit}` resolves to `886a54967db7c6c08725c8bec8854ea3919ea532`; tree `c97ea63536365c6a27c3a5bc2a470bd4fcd27468`.
- The mirror delta starts at the released 0.1.24 source commit `ce0a998773237a9fc91f6914725a327d9e621718`. The retained 0.1.24 hub publication branch points to the same commit as this hub baseline.

## Scope and verification

- The committed source delta has 151 paths. Its `tasks/todo.md` change was excluded to preserve hub-specific task-list history; 150 source changes were mirrored. The allowlist also includes all 21 tagged server payload files, producing 165 mapped paths total (six changed and 15 unchanged server paths). The prepared worktree has 144 changed paths, all inside the allowlist.
- All 21 server payload files match the immutable source tag byte-for-byte; the source map reports zero server mismatches. Every mapped source file/deletion matches the tag's blob/mode, and generated candidate/build files or untracked owner files were not copied.
- The root README changed only PCV lines 34, 40, 42, 67, 72, and 90. The line map verifies all other root README lines byte-for-byte unchanged. The release/download/discovery/log-guide links now target 0.1.25; line 40 records four focused PHP fixtures, 16/16 Node UI checks, and the clean-tag five-asset byte comparison. It retains the unperformed POSIX-guarded page-check and no-provider/install/gameplay limits.
- The 258 tracked paths outside `plugins/private_conversation/` and root `README.md` match their before snapshot by Git-normalized path, mode, and blob. The protected diff is empty, the index is clean, all worktree changes are inside the allowlist, and the existing hub `tasks/todo.md` is unchanged. Source Git modes are recorded in the map; Windows working-copy permissions are not treated as an independent executable-bit check. This is Git-normalized tracked-tree preservation; it is not a claim that every protected working-copy byte bypasses Git's configured EOL normalization.
- The helper verifies the requested branch and baseline, the exact source tag/base commits and trees, the saved before snapshots on recheck, and the final changed-path set. It reads only committed source blobs via `git show`.

## Commands and receipts

- `py -3 K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\release-0.1.25\source\hub-prepare-mirror.py` — exit 0; captured in `hub-mirror-prepare.output.txt` and `hub-mirror-prepare.exit.txt`.
- `py -3 K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tasks\release-0.1.25\source\hub-prepare-mirror.py --verify-prepared` — exit 0; the final receipt is `hub-mirror-verify-final.output.txt` and `hub-mirror-verify-final.exit.txt`. An earlier verify receipt is retained separately.
- `git diff --check -- README.md` in the hub checkout — exit 0.

Checkable receipts: `hub-baseline.json`, `hub-protected-tree-before.json`, `hub-protected-tree-after.json`, `hub-pcv-paths-before.txt`, `hub-mirror-allowlist.txt`, `hub-mirror-source-map.json`, `hub-README-line-change-map.json`, `hub-root-readme.diff`, and `hub-mirror-verification.json`. The final adapted helper SHA-256 is `1b64b32e3cf11b661299f381727359bda71d7f0b33f2d17d86cf474021e24637`.

The clean-tag asset equality statement is based on the release lead's retained `tasks/release-0.1.25/package/clean-assets/candidate-comparison.json` receipt. The hub branch itself has not been committed or published.
