# 0.1.17 source-owner report

## Changes

- `server/manifest.json` changes only the plugin version from 0.1.16 to 0.1.17. The compatibility reference, channel, URL template, schema and maturity are unchanged.
- `README.md` identifies 0.1.17 as current, recommends one MO2 ZIP that contains its DWPkg, lists standalone DWPkg/TAR as advanced alternatives and checksums as optional. It describes the root `meta.ini` and `validated=true` as MO2 metadata, not runtime or sync proof. Current PCV install links and filenames point to 0.1.17; Mind Poisoning 0.1.16 references and historical PCV sections remain intact.
- `distribution/private_conversation-v0.1.17.md` records the packaging-only update and its verification limits. The public 0.1.16 assets were not changed.

## Package gate

Command: `python tests\package_check.py` (exit 0).

```text
....
----------------------------------------------------------------------
Ran 4 tests in 3.458s

OK
```

The existing package checks cover the manifest-derived version, DWPkg integrity and allowlist, MO2 member layout and parsed metadata, embedded DWPkg equality, repository TAR contents, checksums, deterministic outputs and overwrite refusal.

## Fresh candidate builds

Commands, each exit 0:

```text
python scripts\build-package.py --format release --release-dir tasks/release-0.1.17/evidence/candidate-a
python scripts\build-package.py --format release --release-dir tasks/release-0.1.17/evidence/candidate-b
```

Candidate A output:

```text
Built and verified tasks\release-0.1.17\evidence\candidate-a\SHA256SUMS.txt (296 bytes, sha256 48c718b421143728f9e6a09be42e32c6c313f81a34db6a26a8c7e61606ca8e9a)
Built and verified tasks\release-0.1.17\evidence\candidate-a\private_conversation-0.1.17-mo2.zip (3054196 bytes, sha256 c338af8b21a2fe82f762f773ba88f7a60b04e9043684038373500c81107b8121)
Built and verified tasks\release-0.1.17\evidence\candidate-a\private_conversation-0.1.17.dwpkg (3463704 bytes, sha256 9a8784473844a47e03494de8837d6e00de21d8de91cc9029804503c7ef4c15f7)
Built and verified tasks\release-0.1.17\evidence\candidate-a\private_conversation.tar.gz (3051189 bytes, sha256 90d5b48fa887c731bbfb8fbf147fe129dbf4998877f99edf68f5cc6711615d5b)
```

Candidate B produced the same four sizes and SHA-256 values under `candidate-b/`, confirming deterministic outputs. Both fresh exports are retained under `tasks/release-0.1.17/evidence/`; `dist/release-v0.1.16/` was not used as an output directory.

## Scope and limits

The only changed path under `server/` is `server/manifest.json`; no runtime code, compatibility pin or Mind Poisoning snapshot changed. The README check found no current PCV install link or instruction still targeting 0.1.16. `git diff --check -- README.md server/manifest.json` exited 0. No commit, push, tag, release publication, MO2 UI/install, CHIM sync, provider, database or Skyrim gameplay action was performed; source and candidate assets await the lead review gate.

MO2's native UI behavior, whether an import retains the displayed version, installed server sync and gameplay are not proven by the source or package checks.
