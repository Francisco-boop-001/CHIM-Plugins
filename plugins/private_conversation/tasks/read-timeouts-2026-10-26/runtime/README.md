# PCV bounded-read PostgreSQL evidence

The harness exercises the PCV identity and NPC-catalog readers against a fresh disposable PostgreSQL 15 cluster. It stages the supplied server source beside the retained CHIM helper snapshot, calls the real `pcv_current_identity()`, `pcv_npc_rows()`, and `pcvScopeLoadNpcCatalog()` paths, and records source/core/harness hashes with each receipt. It does not contact or modify an installed CHIM server, game, provider, or external database.

## Accepted receipts

- `runs/owner-green-3/candidate-receipt.json`: canonical PCV candidate, 18/18 checks passed.
- `runs/dept-pcv24-green-1/paired-receipt.json`: Department's exact overlaid state/scope source with the canonical index, 6/6 checks passed.

Earlier failed runs are retained: `owner-green-1` exposed the fixture reseeding `core_profiles.id=19` after only truncating NPC rows; `owner-green-2` had 16/18 because valid barrier refusals emitted only newline whitespace. The harness now seeds the profile once and accepts whitespace-only stdout while keeping exit-code, elapsed-time, and empty-stderr assertions. The passing receipts above are the final results.

## Repeat requirements and commands

Run only in `DwemerAI4Skyrim3-test2`, as root, with `unshare`, PostgreSQL 15 (`initdb`, `pg_ctl`, `psql`), PHP with pgsql, and Python 3. The shell script checks the exact distro name before setup, creates a private network namespace, binds its new cluster to namespace loopback, and cleans only its validated temporary cluster. Every run ID must be new; the commands below use unused IDs.

Required inputs are:

- Harness files: `run_pg.sh`, `pg_driver.py`, and `probe.php` in this directory.
- Retained CHIM source: `artifacts/department-maintainer-audit-20261026/host/server-dev/{lib,conf}`. Its exact per-file hashes are recorded in each receipt.
- Canonical PCV owner source: the full 21-file server payload at `sources/pcv24-candidate3/server/`.
- Department overlay source: the full 21-file server payload at `artifacts/department-pcv24-20261026/overlaid-candidate3/server/`.

The runner stages the full server payload so its index, logging, guard, and other includes are available. It binds the three executed entry files (`state.php`, `index.php`, and `scope.php`) to the reviewed hashes and records the remaining payload and core hashes in the receipt. From Windows PowerShell, reproduce the owner and paired phases with:

```powershell
wsl.exe -d DwemerAI4Skyrim3-test2 -u root -- bash /mnt/k/ActorwrightExchange/artifacts/pcv-read-timeouts-20261026/run_pg.sh /mnt/k/ActorwrightExchange/artifacts/pcv-read-timeouts-20261026/sources/pcv24-candidate3 candidate owner-repeat-1
wsl.exe -d DwemerAI4Skyrim3-test2 -u root -- bash /mnt/k/ActorwrightExchange/artifacts/pcv-read-timeouts-20261026/run_pg.sh /mnt/k/ActorwrightExchange/artifacts/department-pcv24-20261026/overlaid-candidate3 paired dept-repeat-1
```

The `candidate` phase runs the owner suite, including empty-versus-failed catalog, fallback identity, held-lock bounds, statement cancellations, caller-connection preservation, and runtime-lease refusal. The `paired` phase is intentionally limited to six checks: populated identity/catalog parity, identity/catalog held-lock behavior, and the official runtime barrier on identity/index reads. These are isolated source/database checks; they do not establish installed-server or game-runtime behavior.

Harness hashes used by the accepted receipts: `run_pg.sh` `571adb20b1747d9009427172f1cb73f4f5957413f329af7007b99c1d52869741`; `pg_driver.py` `f0c4489f0ad2673e97b644473f6e3f3266680913f134af48d2df76e0969583c7`; `probe.php` `82127673147a98463d4c1e6503f7bc38eafed5e1499d982cf931c64263bdde53`.

The `runs/<run-id>/` receipt paths above are under this original artifact directory. The repository evidence copy stores each run directly at `tasks/read-timeouts-2026-10-26/runtime/<run-id>/` (without a `runs/` level).
