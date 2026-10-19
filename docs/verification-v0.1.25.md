# Mind Poisoning 0.1.25 — verification and limits

## Status

The 0.1.25 source adds preserve-on-reload compatibility while retaining the existing safety policy. The focused Windows fixtures and one bounded native PostgreSQL compatibility fixture passed. This is **BETA CANDIDATE** evidence, not a promotion. Package/source-export checks, clean-tag rebuild, and publication-byte checks are separate gates; consult their recorded results before treating those gates as complete.

## Native compatibility fixture

The fixture used the pinned CHIM 3.5.1 source revision `57f292415b008cfaf1a4531f0654d2563362ce70`, a disposable PostgreSQL 15.14 database, PHP 8.2.29, synthetic rows, and inert unrelated services. Exit code was 0, cleanup removed the verified scratch database root, and the separately recorded gaming WSL state and VHD length were unchanged.

The fixture exercised the pinned `INIT` pruning and NPC-restore ordering, production store boundaries, and synthetic persistence. A pre-INIT source was rejected; a post-INIT event committed and its exact retry was a duplicate; an ordered store write held `MAIN` while the actual `INIT` waited; a later `INIT` established a new boundary; a mismatched-scope baseline advanced the floor, cleared imported MP context, and left relationship values intact; a history-write fault rolled back; and a native dump/restore projection matched. No model or provider was called.

## Limits

- This was not a full ACK runtime request. The ordered concurrency case exercised the store transaction under the real `MAIN` semaphore while pinned CHIM `INIT` waited; it did not model the complete concurrent ACK request.
- The fixture does not establish arbitrary crash/race behavior, multi-host coordination, client retry semantics, live installation, provider behavior, or Skyrim gameplay.
- The exact user-facing setting label, UI location, and default remain unverified. A historical `restore-policy` record does not reveal the current effective value.
- Standard release classification for CHIM topic discovery does not promote maturity or prove an end-to-end topic-discovery installation.

The pinned source, exact fixture boundaries, and outputs are retained in the local compatibility-proof artifacts; those scratch artifacts are not part of the plugin package. See the [preserve-mode contract](restore-policy.md), [candidate evidence](beta-candidate-evidence.md), and [release notes](../distribution/mind_poisoning-v0.1.25.md).

The candidate remains **BETA CANDIDATE**. Do not infer provider health, installed behavior, or broad gameplay reliability from this fixture.
