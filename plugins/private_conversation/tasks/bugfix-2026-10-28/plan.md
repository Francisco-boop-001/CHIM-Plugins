# Confirmed PCV bug fixes — 2026-10-28

Baseline: workspace HEAD 0c697793311b314e8158b122e3c2b718638ca9e0; released runtime ce0a998773237a9fc91f6914725a327d9e621718 (0.1.24). Preserve existing dirty task files and untracked evidence. Reuse the dedicated checkout and its existing branch; no commit, publication, version or pin advancement.

## Scope and acceptance

1. Routing owner: make `wrap up:` end immediately when Solo is active OR queued; never activate queued Solo as an ordinary reflection. Retain Pair closing turns and failed-clear protection. Then correct action offerings so an included player already offered by CHIM is retained as an allowed target, while excluded players, bystanders, self/partner restrictions, disabled actions and Solo gates remain intact. Inspect every helper caller and use the same validated player identity as the output verdict.
2. UI owner: bind ARM and END to the rendered opaque playthrough reference, in addition to CSRF. Reject missing, malformed or changed references before staging; preserve same-profile operations, unknown-identity refusal and END without eligible roster. Refresh hidden references with the displayed snapshot, clear Free plus existing actor/Solo drafts on identity change, preserve same-profile drafts, and log only a fixed reason. Do not trust a submitted reference as a storage key.
3. Documentation/review owner: update only directly relevant player guidance for wrap priority and stale-page rejection; no release claims. Independently review implementation after owners finish, including real callers, RED/GREEN outputs and failure cases.

## Constraints

All three reused workers are gpt-6-luna at Max, Ponytail FULL. Shared workspace: preserve all other edits. Lead writes no product code, reviews all diffs and raw verification, and returns defects to the responsible worker. No core, SHARMAT, MP or Department edits.

Only explicit Windows-host PHP/Node/Python fixtures and source inspection. No bash.exe, wsl.exe, distro launch/entry, installed-server reads, HTTP, DB, provider or game calls. If a server check becomes necessary, explain the precise check and wait for the human to confirm live is stopped before coordinated TEST-only use. No server permission is implied by the current conditional offer.

Keep the early-ACK capacity candidate separate: existing integrated evidence is blocked by Windows private-file-mode semantics. Do not change receipt or permission logic on that basis.

## Checkable steps

- [x] Owners record expected-behavior RED failures before product edits, using actual controllers/hooks with only unavailable boundaries mocked.
- [x] UI and routing owners implement minimal readable fixes under exclusive ownership and retain focused GREEN output.
- [x] Documentation owner updates useful guidance and completes independent scoped review after implementation.
- [x] Lead inspects every diff, callers, failure modes and original outputs; return defects to the same owners.
- [x] Complete only justified directly affected checks, record exact proof limits and closing review; leave release and pins unchanged.

## Ownership / preflight

| Task pair or self-check | Shared interface / consistency | Ruling |
| --- | --- | --- |
| UI / routing | Different product files; UI stages existing state, routing consumes unchanged state schema | Parallel safe; no state.php edits authorized |
| UI / docs | Docs describe reference rejection; docs must wait for final UI semantics | Docs can inspect now, final wording/review follows implementation |
| Routing / docs | Docs describe queued Solo wrap behavior and existing actions | Final wording/review follows implementation |
| UI self | index.php, assets/ui-refresh.js, directly dependent UI tests; log.php only if existing reason cannot be reused | Verify rejection before stage and both forms/refresh contracts; old direct page fixtures must send the valid reference |
| Routing self | scope.php; preprocessing.php/context_pre.php only if required; directly dependent routing/action tests | Two fixes share scope.php: same worker performs wrap, then action target work sequentially |
| Docs/review self | docs/player-guide.md and its own task reports only | Read-only product review; no product/test edits; send defects to responsible owner |

## Review

All three scoped fixes are accepted after independent and lead review. Original expected-behavior RED and final GREEN are retained; the independent reviewer requested stronger failed-clear assertions, returned to and completed by the same routing owner. Four focused PHP programs and all 16 JS cases passed the lead run; changed/new PHP and JS syntax and diff whitespace checks passed. POSIX-secured broader fixtures remain explicitly unperformed on this host. The ACK candidate is unchanged and unconfirmed. No server/distro was accessed; no release, installation, commit or pin advancement occurred. See lead-review.md and review/independent-review.md. Original bughunt evidence remains immutable under tasks/bughunt-2026-10-28/.