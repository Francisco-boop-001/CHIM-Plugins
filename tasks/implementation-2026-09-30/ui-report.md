# Solo reflection UI slice

The page now offers pair and solo modes. Pair remains the default for legacy submissions and stored scopes. Solo needs one currently eligible NPC; the server validates actor A, ignores submitted actor B, forces player exclusion, and preserves only a valid bystander mode. Pair submissions still require both distinct eligible actors.

The solo toggle labels actor A as “Reflecting NPC,” disables the pair-only B selector, forces the player-exclusion control on, and explains that opinion changes require compatible Mind Poisoning support. Switching back restores B only while it remains eligible and distinct from A, and restores the previous player checkbox value. Polling keeps the current mode and eligible draft B without refreshing observations. A playthrough identity change clears actor selections and remembered B, resets to pair mode, and preserves the bystander and player-checkbox drafts.

Changed files:

- `server/index.php` — mode-aware input normalization, one-actor readiness, labels, scene summaries, and solo guidance.
- `server/assets/ui-refresh.js` — local toggle state, validation-aware arming, draft preservation, polling behavior, and identity reset.
- `tests/ui_check.php` and `tests/page_check.php` — server form normalization, rendering, and isolated solo staging checks.
- `tests/ui_refresh_check.mjs` — mode toggling, eligible B restoration, one-actor arming, poll preservation, and identity-change checks.

No CSS or preview fixture was needed. Focused verification completed:

```text
wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/ui_check.php
exit 0 — UI checks passed.

wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/page_check.php
exit 0:
PASS: actual page GET read failure returns a matching reference and suppresses page-open.
PASS: valid concurrent readback stays accepted with its staged config ID and renders the current state.
PASS: unavailable readback reports operation=readback, retains staged ID, and says it could not be confirmed.
PASS: refresh GET rereads eligibility without staging, logging page-open, or holding the session lock.
PASS: picker uses the helper map, disables ARM below two, and distinguishes empty/missing/stale.
PASS: stale/single-actor POSTs reject without staging; END succeeds when catalog loading fails.
PASS: solo POST stages with one eligible actor, ignores submitted actor B, and forces player exclusion.

wsl -d DwemerAI4Skyrim3 -- php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/server/index.php
exit 0 — no syntax errors.

node --test tests/ui_refresh_check.mjs
exit 0 — 6 passed, 0 failed:
✔ refresh updates status and CSRF while preserving eligible drafts and clearing stale or cross-playthrough pairs
✔ solo mode arms one eligible actor, preserves an eligible B draft across polling, and restores only a distinct B
✔ one eligible NPC enables ARM only after selecting solo mode
✔ solo mode discards a remembered B when it leaves the eligible roster during same-identity polling
✔ polling is single-flight, pauses while hidden, and refreshes immediately on return
✔ timeout fails closed and the next poll can recover
```

The page test uses isolated fixtures. No live CHIM database, provider, installed runtime, game, native code, package, or deployment was used. This is source and fixture verification only; it does not validate runtime hook behavior, actual speech, or Mind Poisoning effects, and it does not advance Pin 1.
