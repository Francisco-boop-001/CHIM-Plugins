# CHIM-Plugins hub preflight

Read-only preflight on 2026-10-20. No hub files, refs, indexes, or worktrees were changed; no fetch, reset, checkout, or worktree creation was performed. `git ls-remote --heads origin refs/heads/main` returned the public `main` SHA below with terminal prompts disabled; no credentials were printed.

## Location and base

Both local clones use origin `https://github.com/Francisco-boop-001/CHIM-Plugins.git`.

- `K:\ActorwrightExchange\projects\CHIM-Plugins-pcv-0.1.19-hub` is the PCV-specific clone. It is on `main` at `27b835165f8432b807f256dadc971f8156698ace`; its local `origin/main` is the same commit. `git status --short --branch --untracked-files=all` showed only `## main...origin/main`, and `git worktree list --porcelain` showed that checkout as its sole worktree. It is clean but stale relative to public main.
- `K:\ActorwrightExchange\projects\CHIM-Plugins-intimacy-hub` is a separate clean clone on `main` at `c1cd9d0cb3739c0908e6561e4c8ddd2436d3a70c`. Its `origin/main` equals that SHA, and the unauthenticated public `ls-remote` result also equals it. Its status showed only `## main...origin/main`; its worktree list contains only itself. This is the safe current base and location for any later, separately authorized mirror. It already contains the PCV subtree and current root README. The ancestry relationship between this HEAD and the older `27b8351` object was not established in these local objects; no fetch was done.

The PCV subtree is `plugins/private_conversation/`. The current manifest there is still version `0.1.21` (schema 2), so the next mirror must wait for the canonical PCV release and use that release’s exact manifest/package allowlist. Do not overlay files or the README from the stale PCV-specific clone onto the current base: its root README has older Mind Poisoning metadata (`0.1.23`), while current main has the verified `0.1.29` material.

## PCV-only allowlist

After the canonical PCV release is available and the root authorizes the mirror, restrict source payload changes to the approved release files under `plugins/private_conversation/**`. In the shared root `README.md`, allow only PCV-specific wording and links in the current PCV stanza (lines 34–42), PCV download row (67), discovery asset (72), install/update guidance (80), duplicate-owner guidance (82), and logging guidance (90), when the canonical release requires those updates. Keep adjacent Mind Poisoning and Sworn & Scorned entries and shared cross-plugin ownership statements unchanged. No root `server/`, `docs/`, `distribution/`, or other plugin paths are in scope.

The current README identifies Private Conversation as `0.1.21 — BETA` and links its evidence, release, install and support guidance in those locations. This preflight establishes the hub destination and scope only; it does not validate or publish a new PCV candidate.
