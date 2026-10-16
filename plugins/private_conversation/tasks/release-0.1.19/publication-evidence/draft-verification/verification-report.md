# Independent 0.1.19 draft verification

Checked 2026-10-16 21:24:20 UTC. The five supplied draft-download files match the five clean-source assets by exact name, byte length, SHA-256, and byte-for-byte comparison. The current GitHub release API digest and size for every asset also match. Full per-asset results are in `verification-result.json`.

The draft is release ID `415388759`, tag `private_conversation-v0.1.19`, title `Private Conversation 0.1.19 — ALPHA`, `draft=true`, `prerelease=false`, and has no `published_at`. Its API body matches the committed `distribution/private_conversation-v0.1.19.md` exactly (6,432 characters), and matches the saved draft metadata snapshot.

The annotated tag ref resolves to tag object `fa7d4599099fdd471ed00936517b7b0f019dbe04`, which peels to commit `c7ec6cde8231ebb103ef7d443b09789eb4b29691`; that commit's tree is `dc76ae7ac1551622767135b47487b6b6118aaa43`, matching the clean-source tree receipt. The tag is unsigned.

The current 0.1.18 GitHub release still has the same four asset IDs, names, sizes, and SHA-256 digests recorded in `protected-0.1.18-before.json`; its release ID, tag, draft status, and prerelease status also match that baseline.

This closes draft-asset parity and source/tag identity checks only. The 0.1.19 release remains unpublished (`draft=true`), so this does not establish public download behavior, installation, or runtime/gameplay. The required publication state remains `draft=false` and `prerelease=false`; no publication action was taken here.
