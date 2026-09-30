# Pin 1A: state and identity report

## Result

Added backward-compatible `pair`/`solo` scene state and a fail-closed shared-server identity path. Missing `scene_mode` reads and stages as `pair`; new configs carry the canonical mode. Solo ignores the submitted actor B value, stores `actor_b: null`, forces `exclude_player: true`, and requires only actor A for activation. Pair mode retains its two-actor eligibility requirement. If the current eligible roster does not satisfy the selected mode, activation returns `scene_not_eligible` and writes an informational `state.scope_skipped` event while preserving the staged/active configuration and correlation IDs. Existing staging behavior still creates a pending ID for each stage request.

## Owned file changes

- `server/state.php`: mode normalization and legacy reads; solo eligibility; current identity resolution; fail-closed validation.
- `server/log.php`: fixed `scene_mode` context and informational skip event/reason validation.
- `tests/state_check.php`: legacy pair, solo normalization/eligibility, identity reset, and preservation cases. Solo's ignored actor B fixture uses an array to prove it is not type-validated.
- `tests/log_check.php`: fixed scene-mode context and informational skip event checks.
- `tests/eligibility_check.php`: expected ineligibility is checked as an informational skip.
- `tests/identity_source_check.php`: new isolated adapter test for identity-source query behavior.

The frozen-baseline comparison also shows a concurrent change to `tests/ui_refresh_check.mjs`; that file is outside this ownership and was not edited or verified here.

## Identity contract and failure paths

The server first checks `to_regclass('chim_meta.playthrough_profiles')` on the same connection used for profile lookup. If the table is absent, or if `pth_state()` confirms a zero-active-profile state, identity uses the current `public.core_player` `player_name`. With a profile table present and a valid active profile, the existing profile/character-derived key is retained unchanged. A present table with unavailable profile settings, malformed/ambiguous player rows, failed queries, or invalid player names fails closed; none of those conditions fall back to shared identity. Shared-server keys include the normalized, validated player name, so a changed Player name invalidates state. This identity is scoped to the shared CHIM server's current Player identity; it is not a claim of unique save isolation.

Same-key staged/active settings remain stored. The existing activation caller must supply fresh eligible presence before a scene can activate. No game-save/load event was introduced because no supported restore signal was established for this pin.

## Verification

Before the implementation changes, the focused state fixture failed at the new absent-profile-table/core-player identity case, and the logging fixture rejected the informational skip-event contract. After the changes, these five focused checks completed under the installed WSL PHP 8.2 runtime; each command exited 0:

```text
wsl -d DwemerAI3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/state_check.php
PASS: identity, config correlation, legacy compatibility, invalidation/expiry logs, corruption preservation and isolated state logs

wsl -d DwemerAI3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/log_check.php
PASS: schema/redaction, IDs, debug window, five-segment rotation, private permissions, concurrent JSONL, unsafe-path rejection, lock fallback

wsl -d DwemerAI3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/eligibility_check.php
PASS: native snapshot validation, background-only cache boundary, catalog identity mapping, and guarded activation

wsl -d DwemerAI3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/background_presence_check.php
PASS: heartbeat baseline, monotonic receipt, bounded status join, empty versus stale, identity reset, and no legacy fallback

wsl -d DwemerAI3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/identity_source_check.php
identity source query checks passed
```

The identity-source test stubs the PostgreSQL functions in an isolated PHP subprocess. No database, provider, installed CHIM runtime, game, package, or deployment was called. These results verify source and isolated fixtures only; live connector and in-game behavior remain unverified.

## Review

Parent review approved the owned state/log/config and identity diffs and the focused test diffs. Pin 1A is ready for the pin owner to integrate with the remaining Pin 1 work; this report does not advance Pin 1 or certify runtime behavior.
