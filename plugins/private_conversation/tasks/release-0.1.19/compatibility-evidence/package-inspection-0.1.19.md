# Private Conversation 0.1.19 package inspection

Performed 2026-10-16 on Windows against the named owner-built output directory. The inspector read TAR/ZIP members in memory and compared them with the current `server/` source; it did not extract files, write artifacts, build packages, run tests, contact a provider, or install the plugin.

## Five outputs and top-level checksums

All five expected files were present. The four lowercase checksum rows in `SHA256SUMS.txt` matched the actual bytes exactly.

| File | Bytes | SHA-256 |
| --- | ---: | --- |
| `chim-plugin.tar.gz` | 3,051,196 | `2453de55c50c04c6a167432bb00d02eaa2e42ccf353ba64e71e3f3ebe99d141f` |
| `private_conversation.tar.gz` | 3,051,279 | `921518e1a750fd2595ac030412fd05ec65909b7b10e5548766c27588f111b34c` |
| `private_conversation-0.1.19.dwpkg` | 3,464,673 | `d78a969480375d448afe347142ddd320c66a201ed01ab1e43d858aa490d2ccf6` |
| `private_conversation-0.1.19-mo2.zip` | 3,054,306 | `6dcb27863fae36ab3c6deab5f8ba6255aea02a68a56af8c439df95a78774ebb9` |
| `SHA256SUMS.txt` | 381 | `096cce8e8dcbf72aa0085be890baee7d33814f8e33c0a3fd4fb60f4a1df2e56a` |

## Archive contents and source equality

The discovery asset `chim-plugin.tar.gz` contains exactly these 20 regular files at its root, in allowlist order, with no enclosing directory:

`.htaccess`, `assets/private-conversation-scene.png`, `assets/style.css`, `assets/ui-refresh.js`, `context.php`, `context_pre.php`, `diagnostics.php`, `index.php`, `json_response_custom.php`, `log.php`, `log_reader.php`, `manifest.json`, `postrequest.php`, `prepostrequest.php`, `preprocessing.php`, `prerequest.php`, `reflection.php`, `reflection_receipt.php`, `scope.php`, and `state.php`.

Each flat member’s bytes equal the corresponding current `server/<path>` file. TAR metadata is regular-file type, mode `0644`, uid/gid 0, mtime `315532800`; the gzip header mtime is zero. The root `index.php` and `manifest.json` are present and byte-equal to source. The retained legacy TAR instead has the `private_conversation/` root directory and all 20 source files under that prefix, consistent with its manifest `archive_strip_components: 1` setting.

The DWPkg has exactly 22 ZIP entries: root `manifest.json`, root `checksums.sha256`, and the 20 `server/` files. CRC checks pass; the package manifest is schema 4, `private_conversation` version `0.1.19`, and declares only `state` mutable. Its checksum table covers every other member and verifies, and every server member equals current source bytes. The embedded `server/manifest.json` matches current source.

The MO2 ZIP contains exactly `meta.ini` and `CHIM/server-plugins/private_conversation/0.1.19.dwpkg`. CRC checks pass, the nested DWPkg equals the standalone DWPkg byte-for-byte, and `meta.ini` is `[General]`, `version=0.1.19`, `validated=true`.

The expected Plugin Manager release tag is `private_conversation-v0.1.19` (28 characters). It matches the manager’s underscore-permitting tag pattern and the updated PCV logger/reader patterns, each retaining its 32-character cap. The manager’s package limit is 268,435,456 bytes; the 3,051,196-byte flat archive is below it. The staging-tree source requires root files and rejects links/special files; the inspected TAR has root regular files. These are static source/asset checks, not a running manager acceptance result or proof of a generated installed manifest.

## Reproducibility record and limits

The existing owner-run `tests/package_check.py` receipt reports four tests passing. The inspected `test_release_bundle_is_deterministic_and_matches_all_consumers` builds two temporary bundles and compares every output filename and byte string. I did not rerun this suite. The owner’s build receipt reports exit code 0; this independent review validates the named output against current source but did not perform a second 0.1.19 build. No Plugin Manager install, live server, WSL, or gameplay was exercised.
