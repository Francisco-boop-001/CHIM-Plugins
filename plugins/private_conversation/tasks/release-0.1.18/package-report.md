# Private Conversation 0.1.18 package verification

## Scope

This report covers two deterministic candidate builds from the frozen working tree, the existing package tests, and four focused PHP fixtures. These are release candidates, not published assets. The clean immutable source export comparison remains pending the lead-supplied commit hash.

The existing builder allowlists 20 runtime files under `server/`, plus generated package manifests and checksums. Tests, documentation, and mutable `server/state/` data are excluded. The manifest is version `0.1.18`, status `development_candidate`, with compatibility reference `cf5030f15781637498be86debe26fcf102f5690d`.

## Metadata snapshot when candidates were built

| Input | SHA-256 |
| --- | --- |
| `README.md` | `93D7C32F81116621BC61267F16CA214AFFF8BEE06879E6A2AA002C050987ABB6` |
| `server/manifest.json` | `96770B0EDA8A6917C99D8A80A231475997334112E5EE11E9683876C71B8F1038` |
| `docs/operator-acceptance.md` | `DC60F86583C453C01B9C8CD00FA8FF1EEC119C39AF5300072A69012A4BA43CDE` |
| `distribution/private_conversation-v0.1.18.md` | `F652C79D4DC67671BC455523331EB681B0257DCC89E959D2A120FA8620AE6822` |

After candidate builds, the documentation owner made final wording corrections in README and the release note only. These files are not in the package allowlist; the manifest, operator acceptance guide, and packaged runtime files were unchanged, so the candidate bytes remain valid. Current hashes after those documentation-only corrections are `README.md` `AFDE39877340CE9C11A649A2B35CCD6E59013B95E1C9BF9F5D90E1DC7E619405` and `distribution/private_conversation-v0.1.18.md` `0FDD338B3CE0656C8F9BA7543F04404416C21D951B243FDCF5C54E84A0B795E2`.

## Package checks and builds

`python --version` reported Python 3.12.4. `python tests/package_check.py` output:

```text
....
----------------------------------------------------------------------
Ran 4 tests in 2.771s

OK
```

The checks cover deterministic packaging and release output, allowlist and consumer layouts, manifest/version/checksum agreement, relative CLI output, and refusal to overwrite a non-empty output directory.

The existing builder was run once per candidate:

```powershell
python scripts/build-package.py --format release --release-dir tasks/release-0.1.18/evidence/candidate-a
python scripts/build-package.py --format release --release-dir tasks/release-0.1.18/evidence/candidate-b
```

Both invocations succeeded. All four generated files are byte-identical between the two candidates. The independent comparison and member/version receipt is [candidate-comparison.json](evidence/candidate-comparison.json).

| Asset | Size | SHA-256 |
| --- | ---: | --- |
| `private_conversation-0.1.18.dwpkg` | 3,464,681 | `24e38a5fed36bb3bb3e02ba959c29020107279e588ee91ab261b1e20f5a2806f` |
| `private_conversation.tar.gz` | 3,051,282 | `7c534361b27d85cdb0a26cbc454526208e0770963b8201321be2632fb55a9e1b` |
| `private_conversation-0.1.18-mo2.zip` | 3,054,313 | `4690b7cccc54d6cca9485ab4a30b4f923da72b3d16aaf7e06f9612b1d12786b6` |
| `SHA256SUMS.txt` | 296 | `3d0d2628f651228aa36ce40b726326ab5450a26bbbd048d5a60175f5e83bdfd2` |

The receipt confirms version `0.1.18` in the package manifest, repository archive manifest, and nested MO2 package; checksums match all three payload assets. The package contains the exact 20-file runtime allowlist plus its generated manifest and checksum list. The repository tar contains the strip-one root and 20 files. The MO2 ZIP contains `meta.ini` and one nested CHIM package.

## Focused PHP verification

All four fixtures passed under PHP 8.2.29 after asserting `WSL_DISTRO_NAME` equals `DwemerAI4Skyrim3-test`:

- `PASS END failures block; solo/group wraps and active/pending END success plus unarmed input remain intact`
- `PASS in-game end and wrap-up phrases match exactly and end the scene with a logged reason`
- `PASS logs access trusts only loopback and the WSL host, and explains refusals; last scene turn shown`
- `PASS unobserved finishes carry the request type and elapsed time; last scene turn recorded`

The exact invocation was:

```powershell
wsl.exe --distribution DwemerAI4Skyrim3-test --exec /bin/bash -lc 'if [ "${WSL_DISTRO_NAME-}" != "DwemerAI4Skyrim3-test" ]; then echo "FAIL wrong WSL_DISTRO_NAME=${WSL_DISTRO_NAME-<unset>}"; exit 97; fi; printf "DISTRO=%s PHP=" "$WSL_DISTRO_NAME"; php -v | head -n 1; php "/mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/in_game_end_hook_failure_check.php" && php "/mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/in_game_commands_check.php" && php "/mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/ui_logs_host_access_check.php" && php "/mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/log_shutdown_context_check.php"'
```

## Clean immutable export

The lead supplied commit `5c8b317ceeb950178ca693df6646c174849e39aa` with tree `893b09c40602674d3c5e468b884cfb2efac5ea1c`. I verified both IDs, created `evidence/clean-export-5c8b317c/source.tar` with `git archive`, extracted it into the same unique evidence scratch, and ran that archived source tree's existing release builder once. The archive SHA-256 is `26e8497c791c11c2f10680fd216c59550d7025548809959385617ec878beee3b`.

The clean rebuild emitted the same four sizes and SHA-256 values in the asset table above. [clean-export-comparison.json](evidence/clean-export-5c8b317c/clean-export-comparison.json) records exact equality for every file between the clean rebuild, candidate-a, and candidate-b, plus the four verified manifest versions and checksum agreement. **CLEAN_EXPORT_PASS.** The four PHP fixtures were not rerun: the clean export matches the already-tested packaged runtime and source paths, and the lead confirmed the test sources are the same.

Commands:

```powershell
git -c safe.directory=K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev archive --format=tar --output=tasks/release-0.1.18/evidence/clean-export-5c8b317c/source.tar 5c8b317ceeb950178ca693df6646c174849e39aa
tar.exe -xf tasks/release-0.1.18/evidence/clean-export-5c8b317c/source.tar -C tasks/release-0.1.18/evidence/clean-export-5c8b317c/source
python scripts/build-package.py --format release --release-dir tasks/release-0.1.18/evidence/clean-rebuilt
```

The builder command ran with the extracted `source` directory as its working directory. No commit, tag, publication, installation, live provider/database access, or Skyrim runtime test was performed. The focused fixtures and package checks do not establish installed-server or gameplay behavior.
