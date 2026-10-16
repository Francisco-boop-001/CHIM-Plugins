# Private Conversation 0.1.18 source metadata report

## Result

Prepared the ALPHA source metadata and install/release documentation. No source files were staged or committed, and no package was built or published by this owner.

Changed for this release task:

- `README.md` — current candidate and install assets now identify 0.1.18; the 0.1.17 PRE-ALPHA publication history remains explicit. It also summarizes the completion-hook diagnostic and leaves speech/playback/stop-cause limits clear.
- `server/manifest.json` — version `0.1.18` and plain `ALPHA:` description. Schema, development-candidate status, candidate default/label, branch, package route, and compatibility reference are preserved.
- `docs/operator-acceptance.md` — identifies the current 0.1.18 candidate while preserving the historical 0.1.17 package review and its evidence limits.
- `distribution/private_conversation-v0.1.18.md` — release notes cover END confirmation, evidence by surface, limitations, and one-source installation routes. No checksum values are invented.
- `tasks/todo.md` and `tasks/release-0.1.18/plan.md` — task tracking and the full release gate sequence.

## Hashes

Pre-metadata SHA-256 baselines, captured after the release checklist was appended to `tasks/todo.md` and before editing the product docs:

| File | Before | After metadata/docs |
| --- | --- | --- |
| `README.md` | `ED6452817CECC1E70CABF53D73B82B663EFA21C82BACCF9D8C11C6B20FC7BA6E` | `AFDE39877340CE9C11A649A2B35CCD6E59013B95E1C9BF9F5D90E1DC7E619405` |
| `server/manifest.json` | `72C7EF0ACB75A0360C7640C691FCF77993825F0DE805CD32BD01C2ABD92CB544` | `96770B0EDA8A6917C99D8A80A231475997334112E5EE11E9683876C71B8F1038` |
| `docs/operator-acceptance.md` | `E16F03083B4345992FB9398ADFFB4DA7BFF0809A28334E9C8E49AD56491C771B` | `DC60F86583C453C01B9C8CD00FA8FF1EEC119C39AF5300072A69012A4BA43CDE` |
| `tasks/todo.md` | `442915944E8C792B12171A597962806ADEA3E5C7D41847B386A6E8F2C041852A` | `A573A8017A321B636947AD8AA3F9517D8F937A5917D770380CA87EFD323AAD7C` |
| `server/index.php` | `37D506ABFFF74495C5B8FC3B850D3A09E44D5B7D771AA75A9527CF1847FA09C0` | `37D506ABFFF74495C5B8FC3B850D3A09E44D5B7D771AA75A9527CF1847FA09C0` |
| `server/preprocessing.php` | `177B5921310FB6EAEFBDBFA882F77CFE56746A57AF523F95139955F99C48AA6C` | `177B5921310FB6EAEFBDBFA882F77CFE56746A57AF523F95139955F99C48AA6C` |

`distribution/private_conversation-v0.1.18.md` did not exist at baseline capture; its current SHA-256 is `0FDD338B3CE0656C8F9BA7543F04404416C21D951B243FDCF5C54E84A0B795E2`. `server/index.php` and `server/preprocessing.php` were unchanged throughout this metadata edit; the accepted diagnostic and END work is preserved.

## Checks

- Manifest parsed as JSON; version and ALPHA description are correct, and schema/status/channel/default/label/branch/package URL template/archive behavior/compatibility reference match the required values.
- README install links and package names use 0.1.18; the historical 0.1.17 PRE-ALPHA label and packaging note remain.
- README/operator relative Markdown paths resolve; the release note's absolute 0.1.18-tag blob paths and fragments resolve against the current source tree.
- Release-page review added the completion-hook diagnostic summary, clarified that CHIM—not a user unpack step—strips the configured tar wrapper, and converted release-note source links to absolute tag URLs. The manifest and runtime code stayed frozen.
- Trailing whitespace checks and `git diff --check` passed. Git emitted only its existing CRLF-to-LF advisory for `tasks/todo.md`.
- No runtime tests, package build, install, provider call, server/database access, or game session was run by this metadata owner.

## Initial source staging allowlist

The lead may stage only these paths for the source commit. This is a proposed exact allowlist, not a statement that this owner staged them. Keep `tasks/release-0.1.18/publication-evidence.md` out of this commit; add it separately only after public verification. Exclude all other historical scratch, binaries, and raw dumps.

- `README.md`
- `distribution/private_conversation-v0.1.18.md`
- `docs/followups/chim-core-delivery-diagnostics-2026-10-15.md`
- `docs/followups/mind-poisoning-subject-diagnostics-2026-10-15.md`
- `docs/logging-revision-2.md`
- `docs/operator-acceptance.md`
- `server/index.php`
- `server/manifest.json`
- `server/preprocessing.php`
- `tasks/alpha-end-2026-10-15/baseline.json`
- `tasks/alpha-end-2026-10-15/docs-report.md`
- `tasks/alpha-end-2026-10-15/end-report.md`
- `tasks/alpha-end-2026-10-15/final-review.md`
- `tasks/alpha-end-2026-10-15/plan.md`
- `tasks/diagnostic-clarity-2026-10-15/baseline.json`
- `tasks/diagnostic-clarity-2026-10-15/docs-report.md`
- `tasks/diagnostic-clarity-2026-10-15/final-review.md`
- `tasks/diagnostic-clarity-2026-10-15/plan.md`
- `tasks/diagnostic-clarity-2026-10-15/preservation-check.json`
- `tasks/diagnostic-clarity-2026-10-15/review.md`
- `tasks/diagnostic-clarity-2026-10-15/ui-report.md`
- `tasks/lessons.md`
- `tasks/release-0.1.18/plan.md`
- `tasks/release-0.1.18/source-report.md`
- `tasks/todo.md`
- `tests/in_game_end_hook_failure_check.php`
- `tests/log_shutdown_context_check.php`
- `tests/ui_logs_host_access_check.php`
