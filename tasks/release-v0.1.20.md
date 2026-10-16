# Mind Poisoning 0.1.20 publication receipt — 2026-10-16

## Result

- [Canonical PRE-ALPHA release](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/tag/mind_poisoning-v0.1.20)
- [Collection mirror PRE-ALPHA release](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/mind_poisoning-v0.1.20)

Canonical source commit: `66ed8014cd6d8b48e99c98867981dc974bdd8ce0`; annotated tag object: `16ce957a780c99481c727d959fc4467cc8340d63`. Hub source commit: `37144ff6d71662f30d67810f2d8871a267ce8583`; annotated tag object: `ec8ac9c4bfe20db6a667fb45ab4608c42d54cfac`. Each repository has its own immutable `mind_poisoning-v0.1.20` tag. The source trees differ because the hub retains its newer Private Conversation 0.1.18 snapshot and current documentation; all server bytes and all four distributed files match.

Both releases are public non-draft prereleases. Both main branches were advanced non-force only after freshly downloaded public assets passed verification. The main manifest version and hub MP links now advertise 0.1.20. `development_candidate`/PRE-ALPHA status is retained; no catalog submission or installation occurred.

## Shipped work and scope

Conservative optional native single relationship-type changes join the existing affinity judgment, with legacy response compatibility, custom/enduring-type protection, locked stale-type checks, type-only persistence and truthful confirmed/uncertain diagnostics. The standalone dashboard loads its required relationship helper from the validated server root, distinguishing unavailable dependency from malformed stored data. Simultaneous labels remain deferred in the separate social-drama notebook. Reflection API versions/signatures and PCV product code are unchanged.

The lead reviewed the earlier owners' affected call paths, diffs and RED/GREEN evidence before release, then delegated metadata and the three hub documentation conflict resolutions to the original docs owner. The preview owner fixed a stale whitespace-sensitive assertion discovered by the clean-export gate; no extra runtime patch was needed. The lead reviewed that diff and re-ran its exact check. Thirty-six explicit source/docs/test/task paths were committed. Private gameplay reports, cumulative todo, unrelated PCV task work, critique, submission drafts, recovery artifacts and screenshots were excluded and preserved locally. A sanitized aggregate acceptance summary replaces the private report link in the player guide.

## Verification

Standalone Windows PHP 8.3.35 with `-n` and explicit mbstring/pgsql extensions executed controlled fixtures. No WSL distribution or installed CHIM root was entered. Offline plugin-manager declarations were taken from immutable core `cf5030f15781637498be86debe26fcf102f5690d`, with fixture fetches; no provider/database calls occurred.

```text
php -n [explicit extensions] <clean-export>/tests/<focused check>.php
20 isolated checks passed, including parser/helper failures, runtime/store/logging,
reflection/reply/observer, overhearing, dashboard readers/bootstrap/type display,
preview and offline manifest/update contract.
php -n -l <clean-tag>/server/*.php and tests/*.php
35 files passed.
python -B -m unittest discover -s <clean-export>/tests -p test_package.py -v
7 tests passed.
python -B dist/release-0.1.20/verify-release.py sources
425 tagged source files equal the executed staged export.
python -B dist/release-0.1.20/verify-release.py build source-tag assets-tag
python -B dist/release-0.1.20/verify-release.py verify assets-staged
python -B dist/release-0.1.20/verify-release.py build source-hub-tag assets-hub-tag
python -B dist/release-0.1.20/verify-release.py verify assets-hub-tag
All four files equal byte for byte across both clean tags and the staged build.
```

The unchanged repository TAR test ran through a Windows executable/path adapter selecting GNU tar 1.35, its gzip dependency and MSYS paths. Strip-one extraction produced exactly the 17 allowlisted files and removed its scratch directory. The default Windows bsdtar, Win32 drive syntax, and default text decoder initially prevented tooling checks; using explicit GNU tooling/path syntax and UTF-8 corrected the verification environment without altering source/packages.

Both tags were pushed, then drafts uploaded and downloaded/compared before publication. Fresh public downloads from both repositories matched tag builds and checksum files; GitHub asset SHA-256 digests and sizes agree. Public main README, manifest and catalog-snippet bytes match each repository's tag. The hub's PCV 0.1.18 Git tree (`893b09c40602674d3c5e468b884cfb2efac5ea1c`) is unchanged. Its newer importer also passed confirmed/unconfirmed type-only classification and the offline manifest check from a clean hub tag. All 209 protected baseline hashes match. Previous 0.1.19 tag objects and asset IDs/sizes/digests remain unchanged.

## Published files

| Asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `SHA256SUMS.txt` | 278 | `c30728b74fc045ca69129d3ee11e03cbcc7ec4942ae80469cfc9fa6d7ef6f671` |
| `mind_poisoning-0.1.20-mo2.zip` | 639098 | `262cac2d1e57e67c525ac5b3b0954106e65af2b5af44d9937cd44a29c2341192` |
| `mind_poisoning-0.1.20.dwpkg` | 983321 | `d2e9e29fd16599cf4bf0bcfa3f1b8b6b0da233ee71151bebd0df4d3f70a5cfad` |
| `mind_poisoning.tar.gz` | 637027 | `39672b8b915a3a7c3fad9885635d8fd162e6bd9846ceb48c3cf3ec24ee17761d` |

## Limitations and final review

Windows symlink fixtures and the physical POSIX PCV JSONL sink were skipped; actual sanitized callback/importer execution passed. The optional PostgreSQL fixture segment was not run: fake-store transaction checks do not prove database durability or concurrent writers. No live provider, installed dashboard/browser, game, MO2 UI, synchronization or new relationship-type gameplay acceptance was performed. Prior live affinity observations remain distinct from this new feature's isolated proof. No core/server/game data or modlist was changed, and no historical release was replaced.

Ignored `dist/release-0.1.20/` retains full commands/helpers, baseline, clean exports, test outputs, asset inventories, draft/public downloads and JSON verification gates. The first preview RED result is retained alongside its GREEN result; the adjacent-markup assertion now permits whitespace while still requiring the exact Unavailable current value. This evidence-only follow-up records completion without changing or retagging published payloads.
