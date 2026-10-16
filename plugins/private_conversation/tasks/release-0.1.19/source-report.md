# Private Conversation 0.1.19 ALPHA — source report

Prepared 2026-10-16 for the lead-owned source freeze and release gates. This report describes the reviewed source/test delta and release-facing documentation; it is not proof of a committed tag, publication, 0.1.19 installation, or live behavior.

## Source and change

The working source base is `fb9e0538216d97c481d3be1c6170951ba209f126`. The reviewed product-source changes are: (1) one expression in `server/scope.php` so solo partner-action targets receive the same validation while existing self-directed exceptions remain; and (2) matching bounded plugin-version syntax in `server/log.php` and `server/log_reader.php`, allowing `_` at both diagnostic write and read points. The logging parser change is source-level only; no live diagnostic-log run is claimed. The package builder adds a flat `chim-plugin.tar.gz` discovery asset beside the retained wrapped TAR and MO2 ZIP. `tests/scene_actions_check.php` covers blank, malformed, self, outsider, valid pair/group target, and self-directed actions; `tests/package_check.py` covers the package outputs. No CHIM core or Mind Poisoning product source changed; the compatibility reference remains `cf5030f15781637498be86debe26fcf102f5690d`.

The manifest advances only `version` from `0.1.18` to `0.1.19`. JSON parsing confirmed schema 2, `development_candidate`, default channel `candidate`, label `Development candidate`, branch `main`, release package source, the legacy `private_conversation.tar.gz` URL with `archive_strip_components: 1`, and the compatibility reference are unchanged. The new flat `chim-plugin.tar.gz` is documented as a separate additive discovery asset; it is not substituted into the legacy manifest route.

## Evidence and bounds

- The action regression had the expected failing assertion before the one-expression fix and passed afterward; PHP lint passed. The four package-check tests also passed. The action cases cover PCV's serialized post-filter behavior, not downstream CHIM/SHARMAT dispatch. The parser syntax change is not evidence of runtime log behavior.
- A separate clean test-distro helper/API fixture passed once (exit 0). It used a local DWPkg with SHA-256 `23ccf9b44232056ca44a677594664aef23e51f425cfe63dd566a3c0bfaae1ec0`, identified as a PCV 0.1.18 validation candidate, not the 0.1.19 release artifact. Using isolated PostgreSQL, MP 0.1.20 production store/API, and a deterministic model callback, it observed one synthetic affinity commit from 10 to 13, a fresh duplicate suppressed with zero additional model calls, and helper END state `off`. The fixture manually assembled scope and called internal helpers; it did not exercise the public hook dispatcher. These task-local records support this source assessment but are not release assets or links promised to users.
- Earlier disposable-clone and Skyrim evidence remains tied to its recorded version/path (including the 0.1.14 and 0.1.17 sessions). It is not new 0.1.19 gameplay proof.
- Full 0.1.19 HTTP pair/END/ordinary routing, wrap-up, supported removal, public ACK scheduling, native timeout/recovery, controlled installation, and playback are NOT RUN. The release remains ALPHA, not beta-certified.

## Source-file hashes

Hashes below are SHA-256. “Before” is the captured pre-edit source baseline for this documentation task; the reviewed action fix was already present in the shared working tree and was not changed here.

| File | Before | Prepared source |
| --- | --- | --- |
| `README.md` | `AFDE39877340CE9C11A649A2B35CCD6E59013B95E1C9BF9F5D90E1DC7E619405` | `072697364105BE4683164AD340016A1178981D2F846A8792806647F62D9F16DB` |
| `server/manifest.json` | `96770B0EDA8A6917C99D8A80A231475997334112E5EE11E9683876C71B8F1038` | `4F23CEED13B7216D3B4AF398924131220F04E719C90C6C2A31797BE490D141E9` |
| `docs/operator-acceptance.md` | `DC60F86583C453C01B9C8CD00FA8FF1EEC119C39AF5300072A69012A4BA43CDE` | `792FBEC73502A2B241A93B9D96EA3ADAE80C8A618B5D26B751533C0F5DFCB692` |
| `distribution/private_conversation-v0.1.19.md` | Not present | `D0C8A417A581787781567A4C8A08A7B5FEE2819C14EF3B99FE2D3100804A00BB` |
| `tasks/release-0.1.19/plan.md` | Not present | `7DF2CB7392E213B5A7AFB1FDDE45D4D0D27A2725F39142F72A10E0D9626D42F3` |

The README, operator checklist, release notes, plan, and source report use LF; the manifest JSON also uses LF. The pre-existing mixed line endings in `tasks/todo.md` were preserved while appending this task. The documentation owner did not build assets or perform Git staging/commit, tag, push, or publication; the builder and lead own those gates. A package build and its exact asset hashes must be recorded from the builder's actual output before publication.
