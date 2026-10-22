# Task 1: failed queued replacement fixture

Baseline source: `3cd9f840ec5ce3d66da1a5e72252c1d28929c6a5`.

## Fixture contract

The eligibility fixture keeps the existing no-active first-ARM control: missing or partial eligibility leaves that initial ARM pending, and a later eligible Standard request activates it. It also retains the existing active-invalid refusal check and valid replacement coverage in `tests/state_check.php`.

For an active Aela/Faendal pair, the new case stages Aela/Troll while the next Standard request confirms Aela and Faendal but not Troll. It expects that input to be refused without returning a usable scope or advertising a pending replacement, the persisted pending slot to be cleared, the active configuration and ID to remain unchanged, and its already-due renewal to remain unchanged. It then confirms `pcv_read()` exposes only the original active scene and the next eligible Standard request rechecks and continues it.

A second case passes no strict activation map but supplies valid in-scene eligibility for active Aela/Faendal. The replacement is still refused without scope, and its pending slot is consumed while A+B remains intact. This covers the caller's separate strict and active maps.

## Touched paths

- `server/state.php` — consumes an ineligible pending replacement when a live active scene is present; return flags reflect whether a pending entry remains.
- `tests/eligibility_check.php` — regression fixture, including the unchanged no-active first-ARM retry path.
- `tasks/critique-fixes-2026-10-22/runtime/run-check.sh` — adapted guarded TEST runner; stores original output and exit code by label.
- `tasks/critique-fixes-2026-10-22/runtime/php.ini` — minimal `mbstring` and `posix` extensions for this fixture; no database extension is loaded.

The transition retains `scene_not_eligible` logging and skips active-scene eligibility mutation and TTL renewal for the refused input. Initial ARM remains pending until eligibility is valid. A consumed replacement returns unavailable with no scope or pending fields, and `pcv_read()` exposes the preserved active scene without a queued change. If the state write fails, the existing unavailable path prevents claiming the clear succeeded and leaves the persisted state untouched.

The lead captured baseline RED at `runtime/eligibility-red.output.txt` with exit code `1` in `runtime/eligibility-red.exit.txt`. It failed at the new assertion because the rejected replacement was still returned as queued. This owner has not run tests or runtime checks.

## Baseline TEST command

From the repository root inside `DwemerAI4Skyrim3-test`:

```sh
bash tasks/critique-fixes-2026-10-22/runtime/run-check.sh eligibility-red tests/eligibility_check.php
```

The wrapper hard-fails unless `WSL_DISTRO_NAME` is `DwemerAI4Skyrim3-test`, loads the task-local PHP config, and preserves stdout in `runtime/eligibility-red.output.txt` and the exit code in `runtime/eligibility-red.exit.txt`.

## Focused GREEN commands

The lead should run both focused checks inside `DwemerAI4Skyrim3-test` from the repository root:

```sh
bash tasks/critique-fixes-2026-10-22/runtime/run-check.sh eligibility-green tests/eligibility_check.php
bash tasks/critique-fixes-2026-10-22/runtime/run-check.sh state-green tests/state_check.php
```

The eligibility check covers refusal, one-time consumption, no renewal, first ARM retry, and the next Standard request. The state check retains valid replacement activation coverage.

The lead captured a partial-patch RED at `runtime/eligibility-map-red.output.txt` with exit code `1` in `runtime/eligibility-map-red.exit.txt`. It failed because the added null-strict-map replacement remained advertised as queued. Preserve both RED receipts; they establish the first regression and its widened edge case.

The strict-map condition was removed after this receipt. The added regression now expects any rejected replacement over a live active scene to be consumed, including when the strict map is unavailable but the active-scene map is valid.
