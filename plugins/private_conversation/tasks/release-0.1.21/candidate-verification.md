# Private Conversation 0.1.21 BETA candidate verification

This receipt covers the approved metadata-only candidate build. It does not record a commit, tag, upload, publication, install, live server test, or Skyrim gameplay run. Those gates remain with the lead.

## Source and protected baseline

- Canonical repository: `K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev`; origin is `https://github.com/Francisco-boop-001/CHIM-PrivateConversation.git`.
- Pre-edit branch and HEAD: `fix/mo2-metadata-2026-10-08`, `1dcaaf2d10d3b72e4b45d3f95854d67a4e01af00`. Remote `origin/main` was the same commit. The local `main` branch is nine commits behind; this work stayed on the branch based on current `origin/main`.
- Existing immutable source tag: annotated `private_conversation-v0.1.20`, tag object `d79fdce5f0dcf58694f6712b17db2ff59860c4c8`, peeled commit `85e9dd08c8bacbb6f1553e36b0d1f4c2caacf45e`.
- Pre-edit tracked tree IDs at HEAD: `server` `cf2418fdcaf7f67fdf2b293e6340c99893c52401`; `scripts` `26bc4ce982bf6e01248267d1ac294e10bde7290e`; `tests` `ae8ce974d94f565065d0f400880955ddef61aaeb`; `distribution` `8af8e4c16ce360a7f9c6fa2a4c38838be76e076f`.
- The only source change is `server/manifest.json`: version `0.1.20` to `0.1.21` and description prefix `ALPHA:` to `BETA:`. The manifest comparison against the immutable tag confirms every other field is identical. Tracked `scripts/` and `tests/` remain unchanged. Pre-existing untracked `tests/beta_reflection_integration_check.php` and `distribution/discord-v0.1.9.md` were left untouched and excluded from this release scope.
- The lead's bounded caller review found `server/installation_guard.php:71-90` parses the semantic version and applies the same guarded-install path to 0.1.20 and 0.1.21; `server/log.php:597-615` reads the version for diagnostics. No runtime description consumer was found. No behavior or compatibility-pin change is implied.

## Host checks and build

Host Python was `3.12.4`; no WSL distro, CHIM service, provider, endpoint, or database was entered or called.

- `py -3 tests/package_check.py` — exit 0; 4 tests passed. The unchanged assertions pin `status=development_candidate`, `default_channel=candidate`, channel label `Development candidate`, branch `main`, package source `release`, the tag-specific wrapped-TAR URL, and compatibility reference. The version value is read dynamically from the manifest. Those fields and URLs remain unchanged.
- `py -3 scripts/build-package.py --format release --release-dir dist/release-v0.1.21` — exit 0; builder verified all five outputs. `dist/` is ignored by Git.

| Candidate asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `SHA256SUMS.txt` | 381 | `d69825f0173ca5a87bb1ea8ba636b294455b9c915f329f678f5cb34e0f414697` |
| `chim-plugin.tar.gz` | 3053489 | `d2f8dfde43ab0cef03aeeb7a06b110c18378aa6f7e322d64975d6d4f5e9a5b55` |
| `private_conversation-0.1.21-mo2.zip` | 3056680 | `8480d15479d0371a7e78979c809d698abf1fab35efb4ef98071ab9f9c811db5e` |
| `private_conversation-0.1.21.dwpkg` | 3475089 | `0dd26c6387dcb880caadd410580d63945412f04ce3178505df136fce242eda94` |
| `private_conversation.tar.gz` | 3053560 | `74629c0faa167143f4de7947862326a8465a9fc6058652dd07effdbd29943fe9` |

## Runtime-payload equivalence

Compared the 20 `server/` payload members other than `server/manifest.json` in the candidate DWPkg, wrapped TAR, flat discovery TAR, and DWPkg embedded in the MO2 ZIP against the corresponding four downloaded 0.1.20 public assets. All four candidate formats matched all 20 files byte-for-byte. The current source bytes for those files also match the blobs read directly from `private_conversation-v0.1.20`.

The old public downloads used for comparison match the current GitHub release API digests and the prior publication receipt: `chim-plugin.tar.gz` `50405eca2d8a7b6e7dc9a307740b6ab0518366e21cfcbc743b88e88d94cbcd7b`; `private_conversation-0.1.20-mo2.zip` `7bd8c4e67e970416c116965a0470328a48e7ca4921750fafabf759a2826290db`; `private_conversation-0.1.20.dwpkg` `d5da301dd332d45a97e962da96c8f0af9269d4d402e2083fe7a221e921090e8c`; `private_conversation.tar.gz` `e949fe37d1e7fb2f8091e28f0c4af106ca1039a15809401082ea460572798bb8`.

| Runtime payload member | SHA-256 |
| --- | --- |
| `server/.htaccess` | `ccd754e3ccb7ac8b8a8eafd60f087d6e5a4b936c17459720d5c512b5b927c05a` |
| `server/assets/private-conversation-scene.png` | `e104c8c2d8fec85185855eeb5026d582e9caa3795534e3720eb661173f6d76af` |
| `server/assets/style.css` | `25c99aa25a2e637a91b42f2b09dd61b58cb7d1843a7a48a79a95e898fc511158` |
| `server/assets/ui-refresh.js` | `836896f1e03a43d869acc60d0d4547577939d21f73bda40fc0bed907d85b43c3` |
| `server/context.php` | `2b7a4128c4718b296cecd63c903f17d5b5ba2a6c2a87a77c531437ab00a3d5bf` |
| `server/context_pre.php` | `d718553df4bd6f5732e0e28c9a6989ae1881542e5f4f41b57cd2ec3d3d1a2019` |
| `server/diagnostics.php` | `89166188fd86b7b656a90b4e014f49213c1a1829da90d74e30b5f6dad1fb6e87` |
| `server/index.php` | `01cb838d02246151b990b6ee163ab34cc35bfb8ae24f7b1a46229a21ed47b849` |
| `server/installation_guard.php` | `082cb9534bb3c8570faa384c5050d5193f2fbe19a58534381bb7f1b47e2a607d` |
| `server/json_response_custom.php` | `f1304985ee29663365cebb87bf11846edbc2dc68c77b1e981ab2b7e7a95eebd1` |
| `server/log.php` | `56362c3a9f7940320f4d824b4d112af7b8ff0fb1287f62fc6b5c879c5cf0eac7` |
| `server/log_reader.php` | `9fbe50d46ecbc9064df80d258439d77cafcbadf38b0d9b4b6ea83577f2498c97` |
| `server/postrequest.php` | `ef872ff00160d0403d856dfde6606e4c639c716bb624601641ef9a3118adef21` |
| `server/prepostrequest.php` | `3b92bd8825ae0770b49fe01646a90758e8a22b4870cffed3872c19882af7e380` |
| `server/preprocessing.php` | `e1881d60901f3cc47dc0fafe0430ac8fd8fd909e482bede08c181a3c5f19fb5e` |
| `server/prerequest.php` | `b229411c6f6d6f78403890041f6f2a3fa43ded3feb199f3a590aa397fd019434` |
| `server/reflection.php` | `d33a41331638be653d7c94119043672d7142e73ac752f8adb7b7cfc090670540` |
| `server/reflection_receipt.php` | `f2fba808ec7f1153f059b4a919f855fa95f23761fb4eb5ac3ebc7173340a7f15` |
| `server/scope.php` | `078912784620a27c3f4e4173cc252fb492ce004d7d7be8e93033d10fa53305f6` |
| `server/state.php` | `aa8d7c4e44d76b8503c81ce5de636c94d9a31a6ebfb048779c1d54c56b5cc519` |

## Public and hub baseline

- The current [0.1.20 GitHub release](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.20) is release ID `415462809`, published and non-prerelease, with ALPHA title/body and five assets. The GitHub `releases/latest` API still resolves to 0.1.20. Its existing tag and assets remain unchanged.
- Canonical public `main` and the PCV-only hub currently remain at 0.1.20. Hub repository `K:\ActorwrightExchange\projects\CHIM-Plugins-pcv-0.1.19-hub` is clean at `84dbe4f5c022764372ff23fe65a6b38be229ddf0`, matching `origin/main`; current PCV subtree is `d6abf7cb4df20a997266ee8c69c56202abd7e90d`. Protected hub subtree IDs are server `cf2418fdcaf7f67fdf2b293e6340c99893c52401`, scripts `26bc4ce982bf6e01248267d1ac294e10bde7290e`, and tests `ae8ce974d94f565065d0f400880955ddef61aaeb`.
- The new channel-specific manifest points to a versioned release asset. The eventual release must be published with `draft=false` and `prerelease=false` for Plugin Manager discovery. The release and hub updates are not performed by this receipt.

The 0.1.21 candidate is not yet committed, tagged, uploaded, or published. Candidate files and this receipt are awaiting lead review. Package/source equivalence is not a fresh runtime, provider, database, client-sync, or gameplay test.
