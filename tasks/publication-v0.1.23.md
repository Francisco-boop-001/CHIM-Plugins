# Mind Poisoning 0.1.23 — publication verification

Published as **BETA CANDIDATE — experimental**. Standard GitHub release classification (`draft=false`, `prerelease=false`) retains the user's chosen CHIM discovery route; it is not beta certification.

## Source and release

- Source commit: `d97266b5994ef6e36a37364c4a97056516e04e29`.
- Annotated tag: `mind_poisoning-v0.1.23`; tag object `5455884914371836d25ff433c853902360ea9c07`.
- [Public release](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/tag/mind_poisoning-v0.1.23), release ID `416105144`.
- The clean tag rebuilt all four installers and the checksum file byte for byte from the executed staged tree.
- Fresh draft and public downloads of all five assets matched the clean-tag builds. Public sizes/digests, notes/title, latest-release selection, repository/topic and annotated tag were checked. The preceding 0.1.22 release identity, classification and assets remain unchanged.

## Verification and scope

- Six clean-source PHP checks passed: `relationship_context_test.php`, `relationship_type_judgments_test.php`, `reflection_test.php`, `overhearing_test.php`, `site_runtime_compatibility_test.php`, and `manifest_update_check.php`.
- Eight packaging cases and syntax checks for both changed PHP files passed. The native SQL fixture retained its pinned bytes after Git export.
- GNU tar extracted the flat archive and legacy strip-one archive to exactly the 19 allowlisted tag files, byte for byte.
- Tests used standalone Windows PHP 8.3.35 with `-n` and bundled `mbstring`/`pgsql`, in-memory stores and fake model callbacks. No database connection or model provider was used. Expected failure-path diagnostics were reviewed; Windows symlink-creation cases were skipped.
- The product change adds only bounded existing owner-to-subject `relation`, `note`, `best`, and `worst` evaluator context. Persistence, reflection API, eligibility, dedupe, logging, state schema and relationship-type safeguards are unchanged. Three payload files differ from 0.1.22: influence logic, manifest metadata and packaged README.
- Independent source and metadata reviews found no release blocker. All 455 protected source/private files matched the release baseline; only the 11 reviewed paths were included in the source commit. Unrelated private task records and companion edits were excluded.
- Prior comparison, disposable database/install/recovery campaigns and live observations retain their original version scopes. They are not proof of provider or gameplay behavior for this new context projection.

## Published assets

| Asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `mind_poisoning-0.1.23-mo2.zip` | 640543 | `ad9afee72cafb09f94ec609eee97ac09a450c0454858c8a72e82840a0f2bc35b` |
| `mind_poisoning-0.1.23.dwpkg` | 988715 | `9884227a4f40d0c870422bd79a099f2fd4a6c0092ba1b71a28c615279fd51ddb` |
| `mind_poisoning.tar.gz` | 638471 | `5cc7acbcc6b00598dee32fb00a9336432f6a1d50359072879c7e5c32bbf06452` |
| `chim-plugin.tar.gz` | 638428 | `42b343ba93d42881631092840117725a8426e7bb52c4bf70c6f874b8f2173a01` |
| `SHA256SUMS.txt` | 363 | `caf486fc12dddf2e0ff30afa910a1cad7b425f916b731fbaed1ad1c92d3e1101` |
## Remaining limits

This release was not installed into CHIM or tested with a real provider or gameplay. No gaming/test WSL distro, live server, provider, database, modlist or game data was used or changed. The bounded notes can be stale, truncated or one-sided; they do not establish reciprocal feelings, consent, exclusivity or mutual arrangements. Situationships have no new label or agreement engine.

Current-version cold installation, natural NPC/editor/history checks, provider variance, client timing and broader companion integration remain open; see the [candidate evidence](../docs/beta-candidate-evidence.md). Use one installation route and follow the [migration/recovery guide](../docs/deployment-migration.md) before changing routes.
