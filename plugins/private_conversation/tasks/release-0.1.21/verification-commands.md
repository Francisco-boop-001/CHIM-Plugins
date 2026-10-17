# 0.1.21 verification command record

- Package contract: `py -3 tests/package_check.py` (host Python 3.12.4); exit 0, four tests. `exec_command` returned combined process output; the exact captured output is preserved in [package-check.output.txt](package-check.output.txt). The original command was not rerun to reconstruct separate stdout/stderr streams.
- Candidate build: `py -3 scripts/build-package.py --format release --release-dir dist/release-v0.1.21`; exit 0. Exact output is preserved in [package-build.output.txt](package-build.output.txt).
- Source equivalence: a no-write inline Python comparison read each `server/` blob from `refs/tags/private_conversation-v0.1.20`, the prior downloaded public package assets, and the candidate assets; it compared every allowlisted member other than `server/manifest.json` across DWPkg, wrapped TAR, flat TAR and the DWPkg embedded in the MO2 ZIP. It asserted matching member sets and bytes (20/20 per format), checked old download hashes against the current release API/prior receipt, checked all other manifest fields remained equal, and verified the candidate SHA256SUMS list. Exact comparison output and member hashes are preserved in [source-equivalence.output.txt](source-equivalence.output.txt).

No test or build code changed. No Git index/ref or GitHub release operation was performed.
