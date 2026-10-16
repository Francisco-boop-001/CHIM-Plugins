# Private Conversation 0.1.19 hub snapshot report

## Source and base

The isolated `CHIM-Plugins` checkout was clean at `main` commit `3feee95f9754b05902da8b77efc7d8454f4fb754` (root tree `9c8873f77dd2917f1f9c5d198dfd85fb50a234ef`) before edits. Its prior `plugins/private_conversation` subtree was `893b09c40602674d3c5e468b884cfb2efac5ea1c`.

The published PCV source is tag `private_conversation-v0.1.19` (annotated tag object `fa7d4599099fdd471ed00936517b7b0f019dbe04`), peeled commit `c7ec6cde8231ebb103ef7d443b09789eb4b29691`, root tree `dc76ae7ac1551622767135b47487b6b6118aaa43`. The hub's `plugins/private_conversation` now matches all 256 tag paths and blob IDs exactly: zero missing/extra paths, zero byte mismatches, and zero tag-blob mismatches. No hub-only mapping was needed.

## Hub changes

The README now presents PCV 0.1.19 as ALPHA, links the public release/notes/tagged guide/logging guide, describes the flat discovery asset and actual Plugin Manager steps, distinguishes the wrapped legacy candidate-manifest TAR from the flat discovery TAR and embedded MO2 DWPkg, and notes fresh versus preserved legacy extension-folder behavior. It says the normal-release flags satisfy discovery filtering, not beta/stable certification. It also explains the already-loaded compatible Mind Poisoning API, including 0.1.21 for fresh repository-slug installs and the legacy 0.1.16 reply-API-v2 option, without claiming either is installed.

`docs/deployment-migration.md` updates the current PCV release and one-time migration target to 0.1.19 and documents those separate install routes and API compatibility. It now distinguishes the plain `0.1.19` version plus repository identity for DWPkg/file-sync or the legacy version-substituted channel from the generated discovery manifest’s full `private_conversation-v0.1.19` version and `release_tag`. `docs/development.md` updates only the PCV current-release link and adds its bounded release evidence. The Mind Poisoning 0.1.21 discovery wording is preserved, and its protected hub-root source remains unchanged by Git-object identity. The hub plugins directory contains only the PCV subtree; no separate Mind Poisoning plugin subtree is claimed.

## Release reference

Per root's publication verification receipt, the normal published ALPHA release is [`private_conversation-v0.1.19`](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.19), release ID `415388759`, published `2026-10-16T21:25:32Z`, with `draft=false` and `prerelease=false`. Root reports that all five fresh anonymous public downloads matched the clean build. The release's asset hashes are retained in the source [package report](package-report.md): flat `chim-plugin.tar.gz` `2453de55c50c04c6a167432bb00d02eaa2e42ccf353ba64e71e3f3ebe99d141f`; MO2 ZIP `6dcb27863fae36ab3c6deab5f8ba6255aea02a68a56af8c439df95a78774ebb9`; DWPkg `d78a969480375d448afe347142ddd320c66a201ed01ab1e43d858aa490d2ccf6`; wrapped legacy TAR `921518e1a750fd2595ac030412fd05ec65909b7b10e5548766c27588f111b34c`; and `SHA256SUMS.txt` `096cce8e8dcbf72aa0085be890baee7d33814f8e33c0a3fd4fb60f4a1df2e56a`.

## Verification and boundary

- Raw-byte comparison against the exact tag export: 256/256 files matched; recomputed Git blob IDs: 256/256 matched.
- Protected-path gate: 239 tracked paths outside the PCV subtree and the three authorized shared documents were compared by Git path, mode, and blob ID; zero mismatches and zero protected paths staged. The reproducible machine-readable result is in [hub-evidence.json](hub-evidence.json), with the comparison described in [hub-verification.md](hub-verification.md). No retained per-path raw-byte baseline manifest supports the earlier aggregate, so this report makes no raw-working-tree-byte preservation claim.
- Focused assertions passed for current 0.1.19 pointers, flat-versus-wrapped routes, release flags, API compatibility wording, and the `#evidence-and-limits` target. `git diff --check` exited 0.
- Final shared-doc SHA-256: `README.md` `f4ca6a61eee3fbebeafb9b4b797b6201659df2ed213948791928d53c782c2bde`; `docs/deployment-migration.md` `9754eb909b1c647823256737d44b56d08d57f5380c04d97c10d3bf038b89231b`; `docs/development.md` `b3df8a12210cd167acdb4f67fac30547f343b5d531615e4de033cca4698386d2`.

Five exact-source files retain LF bytes that Git warned may normalize to CRLF on a future Windows checkout operation; the committed tree matches the exact PCV tag path/mode/blob identities. Root performed Git staging, commit, non-forced push, and public-ref verification. The published hub commit is 301dce06b7e0559180a38449aaba7ba4551ff4a4, parent 3feee95f9754b05902da8b77efc7d8454f4fb754, tree 9012b1ecae0d4d07af9627a225bb611303ba4532. The PCV subtree remains exactly tree dc76ae7ac1551622767135b47487b6b6118aaa43. This is source identity evidence only; no runtime or gameplay claim is added.
