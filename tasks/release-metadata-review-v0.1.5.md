# Private Conversation 0.1.5 metadata and documentation review

## Candidate metadata

The isolated checkout is on `release/private-conversation-0.1.5`, based at `adf741548462bf196c87a19edc0fde2e604d4e0c`. The manifest now reports version `0.1.5` and points its sole candidate package URL at the tag-specific `private_conversation-v0.1.5/private_conversation.tar.gz` asset.

The manifest retains `schema_version: 2`, `status: development_candidate`, `default_channel: candidate`, the existing config endpoint and server compatibility reference, candidate branch `main`, `package_source: release`, one wrapper directory to strip and `allow_force: false`. No runtime, builder, mutable-state behavior, MP version, package, or Git state was changed for this metadata slice.

## Documentation changes

- The PCV guide and collection root README use the 0.1.5 PRE-ALPHA candidate tag and versioned MO2/DWPkg/tar/checksum filenames. The collection README changes only PCV entries; its MP 0.1.13 links and release notes stay as independently published.
- The guides identify logging revision 2 as included in the 0.1.5 candidate, while retaining 0.1.4 CLI-only/archive facts as history. They distinguish 0.1.12 compatibility for optional effects from the observer-capable published MP 0.1.13 needed for unified solo model/persistence diagnostics.
- The candidate notes cover the protected manual Logs view/export, routing/presence/ACK outcomes, bounded storage and current-request health/fallback, fixed-code client telemetry, malformed-refresh/FIFO/fresh-ACK fixes, replace-not-stack installation, the external state-removal breadcrumb gap, and runtime/authentication limits.
- The release-note guide link is tag-specific and absolute so it resolves when rendered on a GitHub release page.

Files changed in this slice: `plugins/private_conversation/server/manifest.json`, `plugins/private_conversation/README.md`, `plugins/private_conversation/docs/logging.md`, `plugins/private_conversation/docs/logging-revision-2.md`, collection `README.md` PCV entries, `distribution/private_conversation-v0.1.5.md`, and this review.

## Static verification

- Inline PowerShell JSON/metadata/link check: **exit 0, 7 PASS**. It checked the manifest release/config/schema/compatibility/channel contract and exact tag tar URL; PCV and collection install filenames; current revision-2/MP compatibility wording; release-note fix/limit coverage and tag-specific guide URL; and relative document targets.
- `git -c safe.directory='K:/ActorwrightExchange/projects/CHIM-PrivateConversation-release-0.1.5' -C K:\ActorwrightExchange\projects\CHIM-PrivateConversation-release-0.1.5 diff --check -- README.md plugins/private_conversation/server/manifest.json plugins/private_conversation/README.md plugins/private_conversation/docs/logging.md plugins/private_conversation/docs/logging-revision-2.md` — **exit 0**. Git emitted a line-ending notice that the PCV README's CRLF will be replaced by LF the next time Git touches it; no whitespace error was reported.
- `rg -n '[\t ]+$' distribution/private_conversation-v0.1.5.md` — **exit 1, no matches** (expected ripgrep no-match status).

No tests or build were run for these documentation/manifest-only edits. Asset availability, checksums, package contents, deployed authentication, live CHIM/MP behavior and gameplay remain unverified here. No commit, push, publication or release advancement was performed.
