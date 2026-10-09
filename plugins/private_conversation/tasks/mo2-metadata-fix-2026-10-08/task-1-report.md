# Task 1 — MO2 archive metadata

## Change

The MO2 ZIP builder now adds a root `meta.ini` containing only `[General]`, the version read from `server/manifest.json`, and `validated=true`, encoded as UTF-8 with LF endings. The existing CHIM DWPkg member remains byte-for-byte unchanged and retains its deterministic ZIP metadata. The package regression now checks the exact two-member layout, CRC, exact metadata bytes and parsed values, embedded DWPkg equality, release checksums, and reproducibility.

Changed files: `scripts/build-package.py`, `tests/package_check.py`. Candidate outputs are under `tasks/mo2-metadata-fix-2026-10-08/evidence/candidate-a/` and `candidate-b/`; existing `dist/release-v0.1.16/` files were not written.

## RED / GREEN

RED command (exit 1):

```text
python tests\package_check.py PackageChecks.test_release_bundle_is_deterministic_and_matches_all_consumers
```

Output:

```text
F
======================================================================
FAIL: test_release_bundle_is_deterministic_and_matches_all_consumers (__main__.PackageChecks.test_release_bundle_is_deterministic_and_matches_all_consumers)
----------------------------------------------------------------------
Traceback (most recent call last):
  File "K:\ActorwrightExchange\projects\CHIM-PrivateConversation-dev\tests\package_check.py", line 151, in test_release_bundle_is_deterministic_and_matches_all_consumers
    self.assertEqual(archive.namelist(), ["meta.ini", member])
AssertionError: Lists differ: ['CHIM/server-plugins/private_conversation/0.1.16.dwpkg'] != ['meta.ini', 'CHIM/server-plugins/private_conversation/0.1.16.dwpkg']

First differing element 0:
'CHIM/server-plugins/private_conversation/0.1.16.dwpkg'
'meta.ini'

Second list contains 1 additional elements.
First extra element 1:
'CHIM/server-plugins/private_conversation/0.1.16.dwpkg'

- ['CHIM/server-plugins/private_conversation/0.1.16.dwpkg']
+ ['meta.ini', 'CHIM/server-plugins/private_conversation/0.1.16.dwpkg']
?  ++++++++++++


----------------------------------------------------------------------
Ran 1 test in 1.347s

FAILED (failures=1)
```

GREEN command (exit 0): same focused test command.

```text
.
----------------------------------------------------------------------
Ran 1 test in 1.322s

OK
```

Full package check command (exit 0): `python tests\package_check.py`.

```text
....
----------------------------------------------------------------------
Ran 4 tests in 2.491s

OK
```

## Candidate verification

Built twice into separate fresh task scratch directories:

```text
python scripts\build-package.py --format release --release-dir tasks/mo2-metadata-fix-2026-10-08/evidence/candidate-a
python scripts\build-package.py --format release --release-dir tasks/mo2-metadata-fix-2026-10-08/evidence/candidate-b
```

Both commands exited 0. Candidate A output:

```text
Built and verified tasks\mo2-metadata-fix-2026-10-08\evidence\candidate-a\SHA256SUMS.txt (296 bytes, sha256 f6964b3a07a6ee3655ed4fb672f9337843f0f760b29a6c4abde8de00a67cb5a0)
Built and verified tasks\mo2-metadata-fix-2026-10-08\evidence\candidate-a\private_conversation-0.1.16-mo2.zip (3054192 bytes, sha256 23361a5c695bbcf6aecde0e8d56f62159859c0145d7a2dc3de2221b2cede287c)
Built and verified tasks\mo2-metadata-fix-2026-10-08\evidence\candidate-a\private_conversation-0.1.16.dwpkg (3463704 bytes, sha256 5d604a99701d080b264dbdee96ff6d3e3b8735cfa3745b1d768150f988da353e)
Built and verified tasks\mo2-metadata-fix-2026-10-08\evidence\candidate-a\private_conversation.tar.gz (3051189 bytes, sha256 9f21ca0128dce2e7d69b7ab222a3f1ea91c1c4a6aa8fda5716fce5f41ab30793)
```

Candidate B output:

```text
Built and verified tasks\mo2-metadata-fix-2026-10-08\evidence\candidate-b\SHA256SUMS.txt (296 bytes, sha256 f6964b3a07a6ee3655ed4fb672f9337843f0f760b29a6c4abde8de00a67cb5a0)
Built and verified tasks\mo2-metadata-fix-2026-10-08\evidence\candidate-b\private_conversation-0.1.16-mo2.zip (3054192 bytes, sha256 23361a5c695bbcf6aecde0e8d56f62159859c0145d7a2dc3de2221b2cede287c)
Built and verified tasks\mo2-metadata-fix-2026-10-08\evidence\candidate-b\private_conversation-0.1.16.dwpkg (3463704 bytes, sha256 5d604a99701d080b264dbdee96ff6d3e3b8735cfa3745b1d768150f988da353e)
Built and verified tasks\mo2-metadata-fix-2026-10-08\evidence\candidate-b\private_conversation.tar.gz (3051189 bytes, sha256 9f21ca0128dce2e7d69b7ab222a3f1ea91c1c4a6aa8fda5716fce5f41ab30793)
```

The identical outputs confirm reproducibility.

Read-only `Get-FileHash -Algorithm SHA256` comparisons returned:

```text
dist\release-v0.1.16\private_conversation-0.1.16.dwpkg 5D604A99701D080B264DBDEE96FF6D3E3B8735CFA3745B1D768150F988DA353E equal=True
tasks\mo2-metadata-fix-2026-10-08\evidence\candidate-a\private_conversation-0.1.16.dwpkg 5D604A99701D080B264DBDEE96FF6D3E3B8735CFA3745B1D768150F988DA353E
dist\release-v0.1.16\private_conversation.tar.gz 9F21CA0128DCE2E7D69B7AB222A3F1EA91C1C4A6AA8FDA5716FCE5F41AB30793 equal=True
tasks\mo2-metadata-fix-2026-10-08\evidence\candidate-a\private_conversation.tar.gz 9F21CA0128DCE2E7D69B7AB222A3F1EA91C1C4A6AA8FDA5716FCE5F41AB30793
```

The existing candidates were read only.

## Review and limits

The implementation changes only the MO2 wrapper. It uses the existing manifest parser, ZIP compression, timestamp, Unix creator, and file mode for both entries; release metadata and the standalone DWPkg/TAR outputs remain unchanged. The test requires exactly `meta.ini` plus the expected DWPkg, rejects CRC errors, parses the fields with the standard INI parser, and checks exact bytes so extra or personal MO2 metadata cannot slip in.

No MO2 UI/install or game/server sync was run. `validated=true` is an intentional MO2 invalid-data warning override based on source review; persistence through a native clean install/update and actual sync remain unverified. No FOMOD, fake game payload, version bump, manifest/pin edit, publish, or install was made.
