# Private Conversation 0.1.19 package evidence

## Scope

This task adds the exact `chim-plugin.tar.gz` discovery asset while preserving the wrapped `private_conversation.tar.gz`, DWPkg, and MO2 ZIP routes. CHIM’s current discovery implementation requires that asset name and a flat archive root (`plugin_discovery.php`, lines 5, 8–10 and 1003–1021 in the reviewed source copy, SHA-256 `A39CE2F099E9A659EA0F175208B9319833B7AEAE7E58B767E0970ED714007E0C`). It is a package-format requirement; this evidence does not claim a live install or gameplay result.

## Implementation

`scripts/build-package.py:177–220, 226–275, 316–345` adds a `flat_root` option to the existing deterministic repository TAR builder and verifier. The default remains wrapped; release output now adds the flat discovery TAR. `tests/package_check.py:149–205, 211–267` verifies the exact 20 flat members and source bytes, preserves the legacy wrapped archive checks, checks DWPkg/MO2 compatibility, and includes the new asset in reproducibility and release CLI checks.

Source hashes at build time: `build-package.py` `510003AC52BB7239C47D5AF8F69A53D591E45D2626E1895497920BDFD08C38E6`; `package_check.py` `5988DC19BBCC42CC9D185956C20ECD5873940F9AE0D39100EC792F4DBBAB2FBE`.

## Verification

`py -3 tests/package_check.py` completed with exit code 0: four tests passed. The exact command, combined tool output, stream-capture limitation, and exit code are retained in `package-evidence/package-check.command.txt`, `package-check.combined-output.txt`, `package-check.stream-capture.txt`, and `package-check.exit-code.txt`.

The private worktree build command `py -3 scripts/build-package.py --format release --release-dir tasks/release-0.1.19/package-evidence/private-build-0.1.19` completed with exit code 0. The builder command, combined tool output and hashes, stream-capture limitation, and exit code are retained in the corresponding `package-build.*.txt` receipts; stdout and stderr were not captured separately.

| Asset | SHA-256 |
|---|---|
| `chim-plugin.tar.gz` (flat discovery payload) | `2453de55c50c04c6a167432bb00d02eaa2e42ccf353ba64e71e3f3ebe99d141f` |
| `private_conversation.tar.gz` (legacy wrapped payload) | `921518e1a750fd2595ac030412fd05ec65909b7b10e5548766c27588f111b34c` |
| `private_conversation-0.1.19.dwpkg` | `d78a969480375d448afe347142ddd320c66a201ed01ab1e43d858aa490d2ccf6` |
| `private_conversation-0.1.19-mo2.zip` | `6dcb27863fae36ab3c6deab5f8ba6255aea02a68a56af8c439df95a78774ebb9` |
| `SHA256SUMS.txt` | `096cce8e8dcbf72aa0085be890baee7d33814f8e33c0a3fd4fb60f4a1df2e56a` |

A read-only DWPkg payload comparison against the accepted 0.1.18 validation package found 20 server files and exactly three differences: `server/manifest.json`, `server/log.php`, and `server/log_reader.php`. `server/scope.php` remains byte-identical at SHA-256 `07d2cda62a2f7d64ca41c69cb89b42030685aa7ca8cd8531d5d671ebd5e4f89c`. The comparison JSON and stream-capture limitation are retained in `package-evidence/payload-delta.combined-output.json` and `payload-delta.stream-capture.txt`; stdout and stderr were not captured separately.

## Limits

This is a deterministic package check and private worktree build, not a clean-source/tag build, publication, install, importer discovery run, or live CHIM test. The final clean-source build and publication remain under the root release gate.