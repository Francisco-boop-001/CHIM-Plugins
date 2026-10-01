# Lead logging review - 2026-09-30

## Judgment

Private Conversation logging revision 2 is accepted at the source and isolated-fixture level. This is development source, not a newly published or installed release. The original logger owner copied the controlled 21-file runtime/test/build allowlist; the lead independently verified every copied file by SHA256. The final gate below is the mirror evidence; no duplicate owner report is required.

The lead wrote documentation and review evidence only. Three gpt-6-luna Max owners used Ponytail FULL: logger/reader, runtime instrumentation, and the dependent UI slice. Coding ownership was exclusive; the UI started after the shared logger/reader contract passed review. Fixes went back to their original owners. No additional logging dependency, service, database schema or CHIM core change was introduced.

## Reviewed behavior and failure paths

- Request records retain UTC milliseconds, monotonic elapsed time, version, request/config IDs, known actor IDs and fixed event/outcome/reason fields. Correlation labels do not imply authenticated human identity.
- Routing hooks and all affected sibling callers were inspected. A postrequest observation never becomes proof of speech or playback; blocked/failed outcomes remain sticky. Registered routing shutdown observers preserve safe fatal breadcrumbs. Other pre-logger or abrupt termination paths remain dependent on PHP's server error log.
- Solo registration and exact ACK preserve validated correlation without changing registry matching/replay or opinion semantics. Published MP without an observer continues functioning. Unified model/persistence import is explicitly unavailable until the separate MP adapter exists; pair evaluation remains in MP's own diagnostics.
- Presence logs distinguish missing, ordering baseline, aged stale, known empty and unavailable evidence. Internal observation metadata is stripped before public results return, preserving previous caller behavior.
- Bounded private JSONL storage, nonblocking writes, fixed fallback codes and per-request failure health were inspected. Later successful writes cannot erase earlier failures. Reader handles capture bounded sizes under a short shared lock and parse after release; nonregular files are rejected before opening. Legacy records, unsupported revisions, malformed data and normal filters/caps remain distinguishable.
- The standalone Logs route precedes scene/CHIM dependencies. Only exact direct loopback without forwarding headers or trusted server REMOTE_USER grants diagnostics access; all POST operations also require session CSRF. Fixed filters, escaping, validated projections, safe failure responses and partial-export headers keep private content/paths out of output. Scene controls retain their prior access behavior.
- Browser reports contain fixed codes and current CSRF only. Failed episodes deduplicate until recovery; the server throttles each session/code for 60 seconds. Reporter failures are contained and never recursively retried. Locked or disconnected clients cannot guarantee report delivery.

## Verification examined

Owners' exact commands and outputs are in logger-review.md, ui-review.md and ../../instrumentation-review.md. No broad optional suite was repeated after its risks were resolved.

| Check | Result and concrete question |
| --- | --- |
| log_check.php | Exit 0: safe schema/correlation/redaction, permissions/rotation/concurrency, degraded storage, sticky outcomes, real isolated normal and fatal shutdown children, optional importer truthfulness. |
| diagnostics_check.php | Exit 0: legacy/unsupported records, filters/caps/omissions, unlocked snapshot parsing, bounded FIFO rejection, CLI output and HTTP denial. |
| background_presence_check.php | Exit 0: baseline/monotonic receipt/status join, empty versus stale, identity reset and no legacy fallback. |
| reflection_registry_check.php | Exit 0: exact registration/ACK/replay, stale/provider failures, no false accepted summaries, published MP without observer. |
| postrequest_terminal_check.php | Exit 0: empty output is hook observation only; registration exception metadata is safe. |
| ui_check.php | Exit 0: diagnostic access predicate, filters, escaping/redaction and retained scene controls. |
| ui_diagnostics_http_check.php | Red on original route, green after implementation: actual local HTTP, CSRF/access rejections, redacted reads/exports, stored rejection/report records, throttle, partial health and busy/unavailable export failures. Final fixture-isolation rerun exit 0; server sessions stay within cleanup directory. |
| ui_refresh_check.mjs | Exit 0, 8/8: draft/eligibility safety, fixed codes/CSRF, async/sync reporter failures and recovery allowing a later incident. |
| package_check.py | Exit 0, 4 tests: new shared reader belongs to the explicit payload; existing release artifacts unchanged. |
| Changed PHP syntax checks | Exit 0: syntax only, not runtime certification. |

Lead returned and reviewed fixes for false success, severity/reason contradictions, uncertain commit masking, presence classification/public-result drift, nonregular-file blocking, storage failure masking, reporter dedupe, unsafe export outcomes and test cleanup. All returned defects are resolved in the accepted source.

## Boundaries and remaining limits

Mind Poisoning tracked product/test files retain the user's baseline. Its separate requested prompt is tasks/mind-poisoning-logging-prompt.md. PCV includes the optional compatibility seam, but does not promise unified MP model/transaction records before that task is implemented. No guessed pair-scene tuple was added.

No installed CHIM, live database/provider, production authentication/proxy, Skyrim speech or audio was tested or modified. Temporary storage, contention, disk failure and process termination can lose records; bounded history is not complete or tamper-evident. An administrator must provision safe external storage for longer retention. No version, tag, catalog, old release archive, staging, commit or push is part of this task.

README and logging guides distinguish published 0.1.4 from revision 2 development source. todo.md records the checks; lessons.md records the user's separate-project ownership correction. The controlled mirror preserves unrelated files and matches accepted source bytes.

## Final handoff gate

- Independent lead Python SHA256 comparison: exit 0, `PASS: all 21 accepted runtime/test/build files match source SHA256`.
- Protected tracked paths (`server`, `tests`, `distribution`, `releases`, PCV server manifest) have no task diff outside the allowed nested source work. HEAD remains `f0784bcb1462d2c8d9ad06614c52a999c3731c37`.
- PCV source and mirror manifest SHA256 remain `07FA32314A70556ED5AA4F053D97CACEEFC7090B9FF22D8AAFD5962023DEFDB5`.
- Lead guide/task mirror: 11 documents passed exact SHA256 comparison. Final scoped `git diff --check` returned exit 0 after the lead removed an extra trailing blank line in todo.md. Only documentation whitespace changed after the focused runtime checks; no additional runtime suite was needed.
