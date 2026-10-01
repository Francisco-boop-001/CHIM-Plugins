# Logger bug-run findings

## Confirmed fixes

1. **A pre-existing FIFO lock can hang a request.** `pcv_log_write_line()` rejected symlinks but opened `events.lock` with `fopen($lockPath, 'c')` before checking that it was a regular private file (`server/log.php:896-907`). An owner-only directory containing a FIFO therefore blocked before the intended nonblocking `flock`. The writer now validates an existing lock path with the existing `pcv_log_private_file()` helper before opening it. The missing-file create path and post-open validation remain unchanged. The sole production caller is `pcv_log_event()` (`server/log.php:1047`).

   A bounded isolated WSL subprocess called `pcv_log_event()` with a private temporary log directory containing an `events.lock` FIFO; a 1.5-second parent timeout killed the blocked child and cleaned the fixture. Output: `finished=no output= status=9` (harness exit 0). The focused regression in `tests/log_check.php` uses a two-second bound and also checks `write_status=degraded`, `lock_unavailable`, no JSONL creation, and no private output.

2. **A fresh ACK request could accept a mis-correlated first observer row.** The importer validated that `config_id` matched but then replaced event and utterance correlation with any syntactically valid values in the incoming row (`server/log.php:1167-1173`). Earlier wording incorrectly said registration logging seeded the later ACK: registration and `_speech` ACK are separate HTTP requests, so the registration request's PCV globals do not carry over. In the successful ACK path, exact validation and claim were followed by observer attachment without anchoring the new PCV request context (`server/reflection.php:559-570`). Thus the importer's intentional first-valid-row behavior had no trusted tuple to compare against.

   A disposable fresh-context observer fixture attached the current MP `RequestLog::observe()` callback with empty PCV config/correlation, then emitted a schema-valid same-config row with `(event=201, utterance=utt_wrong123456789)` while the fixture's expected ACK tuple was `(event=200, utterance=utt_expected12345678)`. Before integration binding, PCV accepted one row and recorded the wrong tuple (exit 0). The minimal fix uses existing setters after the successful registry claim and before observer attachment (`server/reflection.php:559-563`) to bind the validated registry `config_id`, string `event_id`, and `utterance_id`. It adds no event or API and preserves first-row acceptance when the importer is used without an established tuple.

   The focused registry test now clears PCV config/correlation after registration to model the request boundary. On the real successful in-memory ACK evaluation path, its `RequestLog` sink snapshots PCV fields before the observer callback sees the first supported MP reflection row, then checks the tuple matches the validated registration. The fixture loaded the current dirty observer-capable MP checkout and uses `MemoryStoreDb` plus a deterministic model callback. Its no-observer compatibility branch uses a logger stub, not the actual published `RequestLog` implementation. The published MP 0.1.12 lacks `RequestLog::observe()` and remains covered only through that compatibility seam; no live installed ACK was exercised. MP source changes remain untouched.

## Verification

- Before the FIFO fix, `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/log_check.php` exited 1 with `FAIL: a FIFO lock path must be rejected promptly instead of blocking the request:`.
- Before the correlation guard, the same focused command exited 1 with `FAIL: MP observer rows with a different event or utterance ID must not relabel or add to the established ACK tuple`.
- After both fixes and the boundary assertions, that command exited 0: `PASS: schema/redaction, IDs, debug window, five-segment rotation, private permissions, concurrent JSONL, unsafe-path rejection, lock fallback`.
- The same fixture verifies a first valid observer row is still accepted when no tuple exists, matching same-tuple imports still work, and independent event-only and utterance-only mismatches neither change the established tuple nor add rows.
- `wsl -d DwemerAI4Skyrim3 -- php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/server/log.php` and the corresponding command for `tests/log_check.php` both exited 0 with `No syntax errors detected`.
- Before the ACK binding, `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/tests/reflection_registry_check.php` exited 1 at the fresh-context assertion: `The successful ACK must bind the validated configuration before an observer row is written`, expected config UUID, actual `NULL`.
- After binding, the same focused registry command exited 0 with `PCV reflection registry checks passed.` The current observer-capable fixture emitted its model, persistence, and request-summary JSON records before the pass line.
- `wsl -d DwemerAI4Skyrim3 -- php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation/server/reflection.php` and the corresponding command for `tests/reflection_registry_check.php` both exited 0 with `No syntax errors detected`.
- Helper-cache check: another isolated PHP process replaced an accepted regular 0600 file with a 0600 FIFO; `pcv_log_private_file()` rejected the replacement. Output: `{"initial_regular_accepted":true,"replacement_child_exit":0,"fifo_accepted_by_helper":false,"actual_is_regular_file":false}` (exit 0). No cache-related code change was warranted.

## Accepted mirror

Only the two authorized files were copied into `CHIM-MindPoisoning/plugins/private_conversation`; source and destination SHA-256 values match:

- `server/log.php` — `834ae19254a25dee6f06f74d88a8d6bfa39831cd7fc3d4d6004efb588cc091d8`
- `tests/log_check.php` — `4f547375fb01d3e8960e8f400af9a894b08c529b56ad655d62d1fd7e5bfd2d1b`

The later accepted ACK-binding mirror contains exactly two additional files; source and destination hashes match:

- `server/reflection.php` — `7f33436258d1641dd7788fe854e830b2675231f442529479e20b86848645c3c2`
- `tests/reflection_registry_check.php` — `175aad2624e4d9f84ad351c536a67924ada355fabf2cec20a86b678a7e89c86b`

## Limits

The directory is owned by the PHP user and mode 0700, so a different unprivileged user cannot normally replace its lock path. PHP has no `O_NOFOLLOW` option in this path; a malicious process running as the same effective user can still race a path replacement between the pre-open check and `fopen()`. The fix prevents an already-present FIFO/device/directory from blocking; it does not claim to defeat that same-user race.

No CHIM request, provider call, database transaction, or playback was run. Reader/diagnostics code was source-reviewed; its previous focused checks were not rerun because this change did not touch those files. No broad suite or package/release operation was run.
