# Mind Poisoning v0.1.25 — BETA CANDIDATE

Mind Poisoning 0.1.25 keeps the **BETA CANDIDATE** label. Standard GitHub release classification (`prerelease=false`) supports CHIM topic discovery; it is not a maturity promotion. This release adds a preserve-on-reload compatibility path. The llama has learned where the ledger went, not how to predict the next one.

## What changed

When CHIM's effective `NEVER_CLEAR_RELATIONSHIP_DATA` policy is enabled, covered source IDs at or before the latest actual `init` row are expired. The boundary is checked before model work and again under CHIM's per-host `MAIN` barrier before persistence. CHIM can retain current relationship values while restoring older Mind Poisoning history, so some newer duplicate-prevention receipts can be rewound. The independent source-ID fence expires pre-load rows. A nonempty ledger from another playthrough scope is baselined before evaluation: current relationship values remain unchanged, the triggering event is skipped, and already-inserted pending sources can expire. Later eligible IDs can proceed under existing safeguards. The disabled-policy path is unchanged.

The dashboard's **Interactions** and **Logs** views explain the new boundary and baseline reasons while preserving the original fixed reason and request record.

This protects source-ID replay. It does not deduplicate repeated narrative under a new ordinary event ID, guarantee a nonzero affinity change, guarantee provider success, or make reflection evaluate a newer line when its existing evidence-basis guard rejects it. Policy changes require a quiescent request pool and restart; an in-flight request may be discarded. The serialization contract is for one CHIM host using its shared IPC/`MAIN` mechanism, not multiple application hosts.

## Verification scope

A disposable native PostgreSQL fixture passed against the pinned CHIM 3.5.1 source revision `57f292415b008cfaf1a4531f0654d2563362ce70`, with unrelated services inert. It exercised the actual pinned INIT prune/restore path, production store boundaries, synthetic persistence, exact retry, ordered store/INIT locking, scope baseline, rollback, and dump/restore projection. It made no model or provider call.

The fixture did not run a full ACK request, the game, an installed CHIM server, a provider, or a general concurrent-ACK/crash campaign. See the [verification summary](../docs/verification-v0.1.25.md) and [candidate evidence](../docs/beta-candidate-evidence.md). Package checks, clean-tag rebuild, and publication-byte gates are separate and must be reported from their own results.

## Choose one installation route

- **MO2:** use the recommended plain [MO2 ZIP](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.25/mind_poisoning-0.1.25-mo2.zip). Keep one enabled Mind Poisoning version and update with **Replace**, not **Merge**.
- **CHIM topic discovery:** use `chim-plugin.tar.gz` only with a manager that supports the `chim-plugin` topic. Standard release classification is required for discovery; it does not change the BETA CANDIDATE status.
- **Existing explicit manifest/channel install:** use the [server TAR](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.25/mind_poisoning.tar.gz). For manual CHIM sync, use the versioned [DWPkg](https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/mind_poisoning-v0.1.25/mind_poisoning-0.1.25.dwpkg).

Choose one route per server. Check the published release's `SHA256SUMS.txt` after publication; a checksum does not prove CHIM synchronized the package. Back up the database and configuration before restore or policy changes. Do not change a protection setting just to clear a log reason.
