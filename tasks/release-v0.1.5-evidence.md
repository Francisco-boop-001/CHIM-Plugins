# Private Conversation 0.1.5 publication evidence

## Source and scope

Requested publication is Private Conversation only. Source commit: `64a9ad4705fe1cba7384239f08f5d5e0e2bf3b4c`. Local annotated tag `private_conversation-v0.1.5` resolves to that exact commit. Source includes the previously accepted logging revision 2 and bug-run fixes, plus reviewed release metadata/docs and explicit 0.1.5 package expectations. Version remains PRE-ALPHA/development candidate; existing manifest schema/config/core compatibility remain unchanged.

The isolated release worktree fast-forwarded to independently published MP base `adf741548462bf196c87a19edc0fde2e604d4e0c` before editing. Protected MP server/tests/docs/release notes have no diff against that base. Original shared checkout/index, installed CHIM/game, live database/provider and catalog are outside this publication. The lead wrote review/task evidence and performed Git/release operations; product changes remained with the original gpt-6-luna Max owners using Ponytail FULL.

## Lead verification

Accepted every new release metadata/test diff, complete current guides/notes and original source/bug-run call-path reviews. The notes' relative guide link was returned to its original owner and replaced with an absolute tag-specific URL. Sixty frozen files outside six authorized release edits matched original hashes. The final staged whitespace gate exposed one extra trailing blank line in a copied task plan; the lead trimmed that documentation-only whitespace. The staged scope contained 46 intended files, including PCV source/evidence, the PCV root README rows and notes; no MP product change or generated archive was staged. Source commit was clean.

Fresh focused release checks:

| Check | Result | Question answered |
| --- | --- | --- |
| `node --test tests/ui_refresh_check.mjs` | 9/9, exit 0 | Eligibility/draft safety, malformed refresh, fixed client failures and recovery. |
| `php tests/log_check.php` | Exit 0 | Bounded storage, IDs/schema/redaction, private permissions/concurrency, unsafe paths and degraded locking. |
| `php tests/ui_diagnostics_http_check.php` | Exit 0 | Isolated HTTP access/CSRF/filter/export/report/throttle and safe failure behavior. |
| `php tests/reflection_registry_check.php` | Exit 0 | Exact registry/ACK and observer integration against already published MP 0.1.13 fixtures. |
| `python tests/package_check.py` | Four tests, exit 0 | Explicit formats/version/routes, checksums/CRC, tar extraction, MO2 nesting, deterministic outputs and overwrite refusal. |

PHP checks ran in WSL with isolated temporary fixtures after sandbox E_ACCESSDENIED was resolved by authorized escalation. No installed CHIM, live adapter/database/provider or Skyrim behavior was tested. Earlier accepted relevant fixture results are retained in the logging and bug-run review documents; broad optional suites were not repeated.

## Candidate asset inventory

Lead independently read the clean-source-a candidate sizes and SHA-256 values:

| Asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `private_conversation-0.1.5-mo2.zip` | 3018677 | `fbe5a083b83d5bc1db265d4e670b135225a28b424676b5ab2afba68aaa3d4903` |
| `private_conversation-0.1.5.dwpkg` | 3304531 | `3fcd8ae2a4b1b783cc54404b45947d5efe95e86e941d0af6991817c7bb120915` |
| `private_conversation.tar.gz` | 3016308 | `8e6959da008dfcb3ae5d489bad57aeeba1381ed9dddb3a72f396a68058a6a305` |
| `SHA256SUMS.txt` | 294 | `013a8ed289c56bf220a53a946ce4cb25e6477aeddaa5a5d7fa2f4c9f6bfed89b` |

Canonical comparison and remote publication evidence follow after their gates complete. This evidence document is outside the immutable release tag.

## Canonical and remote gates completed

Lead accepted the original package owner's final report: commit and tag Git archives each have 3,901,440 bytes and SHA-256 `ae970f1537b409062683400b574aad2ba6a09bd05e09b6b040bc93ac693ea86e`. Both clean-source builder commands exited 0; 70 source files and all four assets matched byte-for-byte. Existing verifier functions checked DWPkg allowlist/checksums, tar wrapper/content and MO2 single-member CRC/embedded package bytes. No generated state or test/task evidence enters the runtime allowlist.

Pushed only the annotated tag first. First draft-create command rejected an incorrectly combined PowerShell asset argument before creating anything; `gh release view` confirmed release-not-found. Corrected to four independently validated asset arguments, then created a draft prerelease with reviewed notes. Downloaded all four draft assets into a fresh directory: exact byte equality and all three downloaded checksum entries passed. Published with `--draft=false --prerelease --latest=false` only after that gate. GitHub reports publication at **2026-10-01T02:30:48Z** (2026-09-30 22:30:48 in Caracas), draft false, prerelease true, four uploaded assets with the exact hashes above.

Public release: https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/private_conversation-v0.1.5

Fetched main and confirmed ancestry before the normal fast-forward push from `adf7415` to source `64a9ad4`. Remote annotated tag object is `b11944803b1bee597f71e761646468680a3ecb8d`, peeled commit `64a9ad4705fe1cba7384239f08f5d5e0e2bf3b4c`. GitHub contents API verified public PCV manifest version 0.1.5 and its tag-specific tar URL, hub links for PCV 0.1.5 and MP 0.1.13, unchanged MP manifest version 0.1.13, and repository public status. Downloaded all four public assets into a second fresh directory and compared their full bytes/hashes with the canonical build: all passed.

Plan/todo and this evidence are committed separately after publication; the version tag and all package payloads remain on the reviewed source commit. No old tag/asset or installed environment was changed.

## Final judgment

Commit, push and PCV-only publication are complete with source/package/remote evidence. PRE-ALPHA maturity remains appropriate. Logging is bounded/best-effort; uncertain commit remains uncertain, pair MP correlation remains separate, and the externally removed-state ACK breadcrumb gap stays documented. Fixture/package/source/publication results do not establish installed CHIM, authentication/proxy deployment, live database/provider, native speech/playback/hearing or Skyrim gameplay. Official catalog submission is outside this publication.
