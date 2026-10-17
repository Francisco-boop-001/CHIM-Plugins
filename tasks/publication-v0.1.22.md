# Mind Poisoning 0.1.22 — publication verification

Published as **BETA CANDIDATE — experimental**. Standard GitHub release classification (`draft=false`, `prerelease=false`) retains the user's chosen CHIM discovery route; it is not beta certification.

## Source and public release

- Source commit: `898f461e095cf3b8eb82dee91c269bbd4df66899`.
- Annotated tag: `mind_poisoning-v0.1.22`; tag object `017ea0e0b523d2f645ed985fa8af019706f18c24`.
- [Public release](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/tag/mind_poisoning-v0.1.22), release ID `416041458`.
- The clean tag rebuilt all four installers and the checksum file byte for byte from the tested staged source.
- Fresh draft and public downloads of all five files matched the clean tag builds. Public asset sizes/digests, exact notes/title, latest-release selection, repository/topic and annotated tag were checked. The preceding 0.1.21 release identity and assets remain unchanged.

## Verification and scope

- Clean final-source checks passed: `influence_test.php`, `runtime_test.php`, `site_runtime_compatibility_test.php`, and `manifest_update_check.php`.
- Eight packaging cases and three changed-file PHP syntax checks passed. The native SQL fixture kept its pinned bytes after Git export.
- GNU tar extracted the actual flat archive and legacy strip-one archive to exactly the 19 allowlisted tag files. The standalone checker needed the existing Git gzip tool on its process PATH and MSYS path syntax; no product, archive, or system PATH workaround was made.
- Sixteen server files, including persistence, reflection, request hooks and the dashboard, are byte-identical to 0.1.21. The product change is the small generated-alias exclusion; the other payload changes are manifest metadata and packaged README text.
- Tests used standalone Windows PHP 8.3.35 with `-n` and bundled `mbstring`/`pgsql`, in-memory storage and fake model callbacks. No database connection or model provider was used. Existing failure-path diagnostics were expected; Windows symlink-creation cases were skipped.
- The earlier reflection-reply and overhearing isolated checks remain bounded evidence for the same unchanged callers. The 0.1.20 native PostgreSQL and server-install/recovery campaigns and bounded 0.1.21 live observations retain their original version scopes; they were not repeated for this publication.
- All 446 protected source/private files matched the release baseline. Only the 13 reviewed source/doc paths were included in the release commit; unrelated task records and companion-plugin edits were excluded.

## Published assets

| Asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `mind_poisoning-0.1.22-mo2.zip` | 640175 | `ce0835a2d66ad1eff0fdf897fa70331fffac12dad651dcbac520a9b5922e5130` |
| `mind_poisoning-0.1.22.dwpkg` | 986157 | `cc0979eaf7095a0ee4419dd4b4e12e82c5c3ece04908190fc0b5ed901230772a` |
| `mind_poisoning.tar.gz` | 638062 | `979d2322ad19a2382feb209094d9d29f41a5d25172ac76ed11801d5a3ed2f804` |
| `chim-plugin.tar.gz` | 638023 | `03475ad784a84c8899dd0664355aafe8daca88c13ac94669d6ec7984028be8de` |
| `SHA256SUMS.txt` | 363 | `604f20768d16c7e925e0ed52311c9e4b2572ea32038a64afb0b803b877dbb3bb` |

## Remaining limits

This release was not installed into CHIM or tested in gameplay. No gaming/test WSL distro, live server, provider, database, modlist or game data was used or changed. Current-version cold installation, natural NPC semantic/editor/history checks, client timing/cost/timeout recovery and broader companion integration remain open; see the [candidate evidence](../docs/beta-candidate-evidence.md).

The narrow fix excludes generated `Vampire` and `Black` aliases, while exact catalog names and ordinary personal aliases remain eligible. Exact full-name homonyms and unlisted generic aliases can still be misunderstood. Earlier erroneous relationship data was not repaired. Use one installation route and follow the [migration/recovery guide](../docs/deployment-migration.md) before switching routes.
