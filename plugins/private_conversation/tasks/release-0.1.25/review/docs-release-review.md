# PCV 0.1.25 documentation review and staging inventory

## Player-facing scope

The README and release note identify 0.1.25 as a runtime-changing BETA and link only to its versioned release route and assets. The player guide and release note describe three accepted changes: Solo wrap priority over queued activation; an included player target retained only when CHIM already offered that player; and stale ARM/END forms rejected with HTTP 409 before staging. They preserve the existing failed-replacement behavior: refuse the input, clear the queued replacement, retain the current scene, then recheck on a fresh ordinary Standard input. Initial ARM remains pending and retryable.

The Department copy states that its current PCV pin remains 0.1.24 and does not claim that 0.1.25 is paired with its overlay. If a player uses the Department interface, the guidance is to follow the PCV version declared by that Department release; the separate PCV Plugin Page is the route for standalone 0.1.25. The guide continues to separate PCV scene/audience ownership from CHIM generation/playback, SHARMAT action gates, and Mind Poisoning opinion processing.

## Evidence and limits at this drafting point

The accepted source review records four focused PHP behavior fixtures and 16/16 Node UI DOM tests ([lead review](../../bugfix-2026-10-28/lead-review.md), [independent review](../../bugfix-2026-10-28/review/independent-review.md)). The POSIX-guarded broad page fixture could not activate its logger on the review host, so that route check is unperformed, not a pass. The release candidate contains five named assets and the package owner reports four package checks plus a passing independent inventory. The candidate was built from the dirty workspace snapshot; its hashes are provisional until the clean tagged-source rebuild. No package/public-byte, provider, installation, or Skyrim gameplay conclusion is made here. The early-ACK capacity candidate remains unconfirmed and unchanged.

The accepted prior 0.1.24 verification record stays historical and is not evidence for the runtime-changing 0.1.25 payload. Release publication and the final clean-tag asset checks remain separate gates.

## Documentation-owned staging inventory

Root owns staging. From this documentation assignment, stage only:

- `README.md`
- `docs/player-guide.md`
- `distribution/private_conversation-v0.1.25.md`
- `tasks/release-0.1.25/review/docs-release-review.md`

Do not stage this task directory recursively. In particular, the generated candidate binaries under `tasks/release-0.1.25/package/candidate/`, scratch directories, runtime/player traces, state files, private logs, and unrelated untracked task artifacts are outside this documentation inventory. Source/test and package receipts must be selected by their respective owners and staged by the lead using explicit paths.
