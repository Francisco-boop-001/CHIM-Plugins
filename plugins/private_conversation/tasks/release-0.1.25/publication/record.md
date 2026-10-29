# Private Conversation 0.1.25 BETA publication record — 2026-10-28

Committed, pushed and published: [canonical release](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.25), [versioned CHIM-Plugins bridge](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/private_conversation-v0.1.25). All five assets from each public release were downloaded anonymously and match the clean tagged build byte for byte. Publication does not install the plugin or establish a fresh gameplay result.

## Source and gate evidence

| Boundary | Exact evidence | Receipt |
| --- | --- | --- |
| Canonical immutable source | `private_conversation-v0.1.25` resolves to `886a54967db7c6c08725c8bec8854ea3919ea532`; BETA maturity retained | [Source identity](../source/source-commit.json), [accepted fixes](../../bugfix-2026-10-28/lead-review.md) |
| Final-version routing/controller | Both actual-hook/controller programs pass with manifest25; prior four focused PHP programs, 16/16 JS cases and source syntax proof remain separately bounded | [Final-version checks](../source/final-version-hooks.json), [original lead outputs](../../bugfix-2026-10-28/lead-verification.json) |
| Candidate package | Existing package suite 4/4 and independent 21-file archive/source inventory pass | [Commands and output](../package/package-run.json), [inventory](../package/independent-inventory.json) |
| Clean tagged source | Safe git archive export, 1,083 source files; independent rebuild reproduces all five candidate assets | [Build receipt](../package/clean-assets/clean-build-run.json), [comparison](../package/clean-assets/candidate-comparison.json), [export](../package/clean-source/export-receipt.json) |
| Canonical draft | Five fresh authenticated downloads match clean source and draft metadata | [Draft verification](canonical-draft-verification.json) |
| Canonical public | Five direct anonymous HTTPS downloads return 200 and match clean bytes; correct public tag URL and source target | [Public verification](canonical-public-verification.json), [direct comparison](canonical-public-clean-comparison.json), [metadata](canonical-public.json) |
| Hub source and protected scope | `2816e66fba2f4c3cc24b4489f44af79d35b212bc`; all 21 server blobs equal canonical tree `d78d1ae17fe3034c851baade0619d2d88b1fa76a`; 258 protected tracked paths unchanged; root README edits restricted to six PCV lines | [Hub source identity](hub-source-commit.json), [mirror review](../source/hub-mirror-report.md), [raw payload inventory](hub-source-inventory.json) |
| Hub draft/public | Five authenticated draft and five anonymous public downloads match the same canonical clean assets; public URL is tag-specific | [Draft verification](hub-draft-verification.json), [public verification](hub-public-verification.json), [comparison](hub-public-clean-comparison.json) |
| Update-feed promotion | Main refs first advanced to canonical `886a549` and hub `2816e66` only after both public-byte gates and review passed; closing documentation does not alter the server tree | [Promotion readback](initial-main-promotion.json) |

The clean archive SHA-256 is `29b9e42e17f5cc0a6ad6d6ccc2d1bdf748dc76ffacf98c131f013220e1fab416`. The publisher did not change CHIM core, SHARMAT, Mind Poisoning, Department overlays, runtime state, provider configuration or installed game files. The lead wrote no product code; the reused Luna Max/Ponytail FULL owners retained exclusive file ownership. Independent review accepted source, docs, package and public gates; a final docs-only audit closes the record.

## Published assets

The same bytes are served by both versioned releases. `SHA256SUMS.txt` covers the four payload assets; its own hash is included here.

| Asset | Bytes | SHA-256 |
| --- | --- | --- |
| SHA256SUMS.txt | 381 | 5850351814f91293ca357427952acf9cf2c9876f1a18fd5aa8bdaaa32469e840 |
| chim-plugin.tar.gz | 3055133 | 8b6b1618d09ecc9c24413a6909e1ac9dc141c14fd9b6693f2d74eb2893786567 |
| private_conversation-0.1.25-mo2.zip | 3058334 | 0ecf7ebf31ca69261395b040dc802cd62bff4b17c26306a5c68183496e3c1cdd |
| private_conversation-0.1.25.dwpkg | 3484443 | 05fdec89855062d1dfe7f64ac745f4bcb9d1ddf7a2414a7a4ed45107e4fde3be |
| private_conversation.tar.gz | 3055206 | 1d3d209670ac92c038546c218d9e48ccaf255a6ec7ab63584cdad578dec2b93e |

The DWPkg has the exact schema-4 manifests, internal checksums and 21-file server allowlist. The MO2 ZIP contains its metadata and that unchanged DWPkg. The wrapped and flat TARs match their distinct consumers. Text payloads are LF-only and no mutable state is embedded. These are archive/source checks; there was no fresh importer execution or installation.

The host-side independent verifier gained source/release/report path options after the source tag; its archive assertions were unchanged. The candidate used the original default-path form, while clean/download checks used the later host verifier. This release-evidence tooling is separate from the tagged runtime and builder. The closing evidence commit uses two release-scoped Git attributes to preserve JSON receipts and the executed verifier byte for byte. Before that rule, staged EOL conversion broke a recorded receipt hash; targeted renormalization restored exact bytes for all 41 selected files. The failed ordinary-restage attempt is retained. Runtime payloads remain LF-only and unchanged. [Original mismatch](evidence-byte-red.json), [successful byte gate](evidence-byte-green.json). [Verifier provenance](../package/verifier-revision.json).

## Global selector correction

Publishing the hub with `gh release edit --latest=false` nevertheless temporarily made 0.1.25 the global latest release. The gate stopped before any main/update-feed advance. A selector-only API PATCH restored the previous 0.1.24 release as global latest; its body, tag/identity and asset IDs/sizes/digests were verified unchanged. PCV25 remains available at its exact versioned bridge URL; users should use the canonical/versioned links rather than infer a multi-plugin hub version from its global latest selector. [Before/after and restoration](hub-latest-restoration.json).

## Interface coordination and remaining limits

The user authorized interface compatibility coordination after publication. The Department owner acknowledged the immutable source/tag and received the frozen export, source/hash map and published asset map. The owner reported a source-only preflight with the frozen inputs ready; mixed-version refusal was inspected in source, not executed. Its separate offline rebase still needs its own implementation and release authorization and must preserve identity/state ownership, the Solo null slot, inert setup hook, new helper argument, wrap priority, action filtering and mixed-version refusal. Its existing source pin remains PCV24 until its own gates and release authorization pass. [Coordination summary](interface-coordination.json).

No PCV distro, server endpoint, database, provider, installed game or gameplay session was accessed for this release. The expanded POSIX-secured page/scope fixtures could not initialize on the Windows host; that is unperformed integration coverage, not a passing suite. The focused routing fixture's logger fallback is separately recorded. The early-ACK capacity hypothesis remains unconfirmed and unchanged. No fresh browser rendering, native action execution, audio playback or save/load proof is claimed.

A reviewer can independently export source tag `private_conversation-v0.1.25`, run the existing Python builder and package suite in a fresh directory, and compare all five assets to the table and both public URLs. Read the recorded commands and raw outputs before treating any broader runtime conclusion as established. A necessary new runtime check requires a separately coordinated TEST window after the human confirms live is stopped; no such grant was used here.
The closing whitespace gate recognizes CRLF terminators in byte-preserved Windows evidence using Git's cr-at-eol check option. It retains normal trailing-space checks and excludes only the captured hub-root-readme.diff, whose context-marker spaces are part of the raw patch format. No raw receipt or patch was trimmed to manufacture a pass.
