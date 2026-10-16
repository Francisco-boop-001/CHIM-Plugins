# HUB report — Private Conversation 0.1.18

## Status

The dedicated source release is public as a GitHub prerelease ALPHA. The lead verified all four draft download URLs against fresh anonymous public downloads and confirmed the tagged guide resolves: https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.18. HUB publication is complete on `main` as commit `826565d3241de5dbb5640584855b98ca912ea0e9`, parent `5d868c25806064a59ae7c651e333e7304940b1d2`, pushed non-forced by the lead.

## Documentation changes

- `CHIM-Plugins-release-0.1.17/README.md`: identifies Private Conversation 0.1.18 as ALPHA, points release/install/logging links and package rows at `private_conversation-v0.1.18`, and summarizes END failure handling plus honest last-turn diagnostics. In-game playback remains unverified.
- `docs/deployment-migration.md`: advances the current PCV pointer and migration instructions to 0.1.18 ALPHA. The shared maturity sentence now reads `Mind Poisoning remains PRE-ALPHA`; the MP maturity statement is retained.
- `docs/development.md`: advances the current PCV link to 0.1.18 ALPHA. The Mind Poisoning version and link label remain unchanged.

## Canonical snapshot

Copied the complete tracked tree from immutable source commit `5c8b317ceeb950178ca693df6646c174849e39aa` using `git archive`. The supplied source commit root tree is `893b09c40602674d3c5e468b884cfb2efac5ea1c`. The archive contained 233 tracked files; the target subtree had no files absent from the archive, so no HUB files were deleted. Every copied file passed SHA-256 comparison. The staged `plugins/private_conversation` tree is exactly `893b09c40602674d3c5e468b884cfb2efac5ea1c`.

## Preservation and verification

- Baseline checkout: branch `release/pcv-0.1.17`, HEAD `5d868c25806064a59ae7c651e333e7304940b1d2`; `origin/main` pointed to the same commit at baseline. No fetch was performed.
- Protected baseline manifest: 231 non-allowlisted tracked tree/blob records, SHA-256 `656a12debd7e59ea36e0d0198d34eeaddf8f0525dc79d225283c7c34e121dc3a`. Comparison against the staged HUB tree found zero mismatches. The recorded pre-edit ancestor tree IDs were root `5cfca7630343824cbdce0235ddb50b992193239f`, `docs` `2b49b9c7cfaf1f4ecabdd9121dfca88fdf5e0fc9`, and `plugins` `4eb1d25f04304a7867f82042d90d52999b5cf63c`; those ancestor trees include allowed edited children and are expected to change.
- Only the three listed documentation files and `plugins/private_conversation/**` are staged (35 changed paths total). There are no unstaged changes or unexpected staged paths. Staged tree ID: `8b7659f7a0d453d12ad6878bf0c748562d3d08ee`.
- `git diff --cached --check` passed. No code tests were run in this HUB documentation/snapshot task.
- This HUB task performed no commit, push, fetch, tag, package build, installation, or runtime test. The lead reports the HUB commit was pushed non-forced and independently verified publicly; no duplicate HUB release was created.

## Review corrections

Automatic review rejected an earlier edit that changed the Mind Poisoning link label; that accidental label change was restored to its baseline text. Automatic review also rejected deleting the whole shared `Both candidates remain PRE-ALPHA` sentence because that would remove Mind Poisoning's maturity statement. That deletion was not performed; under lead direction, only the shared clause was replaced with `Mind Poisoning remains PRE-ALPHA`.
## Final HUB publication checks

The lead independently verified the public `main` result:

- Commit `826565d3241de5dbb5640584855b98ca912ea0e9` has parent `5d868c25806064a59ae7c651e333e7304940b1d2`; the push was non-forced.
- The public HUB manifest reports Private Conversation 0.1.18 ALPHA. The public README shows Private Conversation 0.1.18 and Mind Poisoning 0.1.19 unchanged.
- The published subtree remains `893b09c40602674d3c5e468b884cfb2efac5ea1c`, equal to the canonical source root tree. The commit contains the 35 reviewed allowed paths.
- All 231 protected baseline objects remain unchanged (manifest SHA-256 `656a12debd7e59ea36e0d0198d34eeaddf8f0525dc79d225283c7c34e121dc3a`).