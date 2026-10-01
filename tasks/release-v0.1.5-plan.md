# Private Conversation 0.1.5 publication

The user authorizes commit, push and publication. Prepare PCV 0.1.5 as PRE-ALPHA with logging revision 2 and reviewed bug fixes. Keep Mind Poisoning's separate staged publication work intact. No installed CHIM/game/provider/database or catalog changes.

- [x] Inspect dirty source, release process and live GitHub refs; create an isolated release worktree from f0784bc.
- [x] Freeze only reviewed nested PCV source and task evidence; verify copy hashes.
- [x] Delegate version/docs/release notes and bounded packaging changes with exclusive ownership.
- [x] Lead review every release diff and current behavior evidence; preserve limitations. Package gate is recorded below.
- [x] Commit only intended files; clean-source rebuild, tag and verify deterministic assets.
- [x] Push immutable tag, upload draft assets, download and compare hashes; publish before advancing main.
- [x] Merge any independently published main changes without overwriting MP; push main without force.
- [x] Verify public release/manifest/source, record final evidence and complete task checklist.

Use an isolated worktree so the original work/mind-poisoning index remains untouched. The source release tag stays immutable. If main advances from the other publication, integrate it by a normal merge before the final fast-forward push; do not force push or reset the other checkout.

Metadata and documentation owner: original UI owner after source-copy prerequisite. Packaging/check owner: original logger owner, isolated packaging test and outputs only, after metadata gate. Lead owns review/plan/publication operations and writes no product code.

User confirmed PCV-only scope. The independently published MP main adf741548462bf196c87a19edc0fde2e604d4e0c was fast-forwarded into this isolated base before release edits; the original staged/index state was never touched. Initial source inventory records its earlier creation base. Lead independently verified 61 frozen files outside five explicitly owned release metadata/test edits. Published modders guide was inaccessible through the web tool; installed CHIM consumer/source revision cf5030f15781637498be86debe26fcf102f5690d was inspected read-only and retains the existing manifest/tar extraction contracts. No compatibility pin change is needed.

## Lead pre-commit review

Reviewed the source-freeze inventory and accepted logging/bug-run call-path reports, every new release metadata/test diff, complete current PCV README and both logging guides, release notes and ownership reports. Corrected the release-note relative guide link through its original documentation owner so it resolves on the GitHub release page. Manifest schema/config/core compatibility and MP product/tests are preserved.

Fresh focused checks on the isolated source: Node ui_refresh_check.mjs 9/9; PHP log_check.php, ui_diagnostics_http_check.php and reflection_registry_check.php all exit 0. WSL fixtures required sandbox escalation after E_ACCESSDENIED and use isolated temporary stores. The reflection fixture resolved the already published MP 0.1.13 from the collection base. These are fixture behavior results, not installed CHIM/provider/database/gameplay evidence. The package owner runs the four existing package checks before commit. Canonical assets will be built twice from clean Git exports after commit; uploaded bytes will be downloaded and compared before publication and main advancement.
Package owner gate accepted: python tests/package_check.py, four tests, exit 0 (2.443s). Payload allowlist/checksums/CRC, explicit versioned routes, tar extraction, MO2 nesting, determinism and refusal to overwrite existing output passed. Lead verified no diff in protected MP server/tests/docs/release-notes paths. Sixty frozen inventory files still match exact hashes after excluding the six assigned release metadata/test/todo paths. Remaining steps are clean-source canonical assets and remote publication.
Final review: source and tag export gate, exact draft/public download comparison, published prerelease status and public manifest/hub checks all passed. Main advanced by normal fast-forward only after verified publication; MP 0.1.13 remains published and unchanged. Final evidence and completed checklist are separate documentation commits, preserving immutable tagged runtime payloads.
