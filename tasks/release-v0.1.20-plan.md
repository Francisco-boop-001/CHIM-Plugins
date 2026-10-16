# Mind Poisoning 0.1.20 release — 2026-10-16

- [x] Inspect current work, prior implementation verification and both remote histories; preserve unrelated work.
- [x] Delegate only release metadata/current documentation for 0.1.20 PRE-ALPHA; review against the approved conservative type feature and standalone dashboard fix.
- [x] Commit the reviewed implementation and release metadata with explicit path lists; verify the exact clean source export using independent Windows PHP and deterministic package checks.
- [x] Incorporate the MP commits into a separate latest-hub checkout without changing its published PCV snapshot or other plugin pins.
- [x] Tag each exact source commit; rebuild both clean tags and compare all distribution bytes and checksums.
- [x] Push immutable tags, upload drafts, download and compare; publish verified canonical and hub releases.
- [x] Advance main branches non-force only after public asset verification; record refs, checksums, protected-file checks and limitations.

Authorization: user requests commit, push and publish. No installation, WSL, CHIM/core/server access, live database/provider calls, game/modlist changes, historical tag/asset replacement, or maturity promotion. Preserve PRE-ALPHA/development_candidate status and current APIs. Lead writes release records/orchestrates Git and publication, never product code. Reuse existing gpt-6-luna Max owners for release metadata and independent review.

Current canonical main is d04e84f; hub main is 826565d and already includes PCV 0.1.18. Keep the canonical integration snapshot unchanged and preserve the hub's newer snapshot. The two Git source trees/commits may differ, but the runtime allowlist and released assets must match byte for byte. No reverse merge or rollback of either repository is authorized. Do not stage unrelated PCV task changes, critique, scratch recovery data, images or untracked scratch files.

Use independent PHP8.3.35 Windows CLI with -n and explicit bundled extensions for controlled fixtures. Existing Windows symlink and PCV POSIX disk-sink limitations remain visible. Checks establish code/package behavior, not new live type-change or PostgreSQL durability proof.

## Review

All seven release steps completed. See [publication receipt](release-v0.1.20.md) for separate canonical/hub source commits, verified package SHA-256s, preservation checks and limits. Main pointers moved only after public downloads passed. Live runtime and game data stayed untouched; no maturity promotion.
