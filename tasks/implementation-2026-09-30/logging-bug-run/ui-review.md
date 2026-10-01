# UI logging bug run review - 2026-09-30

## Confirmed defect

A refresh that returned HTTP 200 with the expected selector IDs but a malformed `#actor-b` control could erase the user's `#actor-a` selection. `refresh()` is the only caller of `applySnapshot()`. The function checked selector presence, then handled a changed playthrough and replaced actor A's options before attempting to read/clone actor B's options. If actor B lacked a select `options` collection, cloning threw; the catch path correctly disabled ARM and reported fixed `invalid_response`, but the A draft and potentially the previous playthrough/solo draft had already been changed.

A same-origin server/proxy response must be malformed to trigger this. The failure is limited to client draft loss: ARM fails closed, the server's scene state is unchanged, and normal valid refreshes continue to pass the existing eligibility/draft tests.

## Fix

`applySnapshot()` now validates current and parsed actor controls as selects with array-like option collections that have integer lengths and string values. It clones both replacement option sets before changing playthrough identity or any current selection/draft state. A malformed response therefore follows the existing `invalid_response` path without partially applying the snapshot. No logger, server route, state, reflection, packaging, or release files were touched by this UI slice.

## Verification

Test-first regression command before the production change:

```text
node --test 'projects/CHIM-PrivateConversation/tests/ui_refresh_check.mjs'
exit 1
```

Relevant red output:

```text
✖ malformed refresh controls preserve both actor drafts and fail closed
AssertionError [ERR_ASSERTION]: Expected values to be strictly equal:
'' !== '101'
ℹ tests 9
ℹ pass 8
ℹ fail 1
```

The regression feeds an HTTP-200 snapshot with a changed playthrough reference and a non-select `#actor-b`; it asserts both actor drafts and the prior reference remain, ARM is disabled, and telemetry contains only fixed `invalid_response`.

After the fix:

```text
node --test 'projects/CHIM-PrivateConversation/tests/ui_refresh_check.mjs'
exit 0
```

```text
ℹ tests 9
ℹ pass 9
ℹ fail 0
ℹ cancelled 0
ℹ skipped 0
```

The focused file also retains its valid-refresh eligibility, solo/pair draft, hidden-tab, timeout/recovery, per-episode client telemetry deduplication (re-armed after a successful refresh), and no-recursive-report checks. Server-session throttling is outside this Node check. No PHP/HTTP rerun was needed because this fix changes only browser snapshot application. Node's DOM/parser harness is an isolated fixture, not proof from a rendered browser or installed CHIM runtime.

## Changed files

- `server/assets/ui-refresh.js`
- `tests/ui_refresh_check.mjs`
- `tasks/implementation-2026-09-30/logging-bug-run/ui-review.md` (this evidence)

Controlled mirror completed for only `server/assets/ui-refresh.js` (SHA-256 `640C3EC3848ED1E50BCB008AFD3200AAF690BF4AF02592AFA1CF87129B86E89E`) and `tests/ui_refresh_check.mjs` (SHA-256 `EDB225413AEF8C63069E6897B27CCAC6B93B4C15733C7E534271974CAB2F2E06`); source and mirror hashes matched. No release advancement was performed.
