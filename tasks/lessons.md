# Lessons

- Mistake pattern: expanding private-conversation guarantees from prompt scoping into Director behavior, secrecy guarantees, vanilla NPC suppression, or NPC-authored event attribution without source proof.
  - Corrected rule: keep this extension to the verified ordinary STANDARD input path; call out every unverified capability as out of scope.
  - Prevention: carry the exact boundaries from `docs/spec.md` into each hook, UI label, test, and package review. Do not infer an event's speaker identity from prompt text.
- Mistake pattern: treating supplied visual references as permission to reuse their exact composition in new mod artwork.
  - Corrected rule: references guide style; create original mod-specific artwork unless the user explicitly requests exact reuse.
  - Prevention: separate visual style cues from protected composition details before creating UI or package art.
- Mistake pattern: treating membership in `NpcMaster::getAll()` or the UI catalog as proof that an NPC is both nearby and CHIM-AI-active.
  - Corrected rule: picker eligibility is the current intersection of immediate vicinity and CHIM AI activation; catalog identity alone proves neither condition.
  - Prevention: use an authoritative producer for the intersection, revalidate at POST and activation, fail closed for unknown or stale IDs, and test empty, unavailable, and changed eligibility states.
- Mistake pattern: treating a request-scoped actor snapshot as a continuous live feed.
  - Corrected rule: a cached snapshot's TTL bounds time since server receipt, not the producer's sample age; only a new eligible ordinary Standard input supplies authoritative activation-time presence.
  - Prevention: key the cache by validated playthrough, enforce receipt TTL and invalidation rules, explain how to refresh it, and never substitute a legacy surroundings string when the snapshot is absent.
- Mistake pattern: using a dialogue-bound presence snapshot and its TTL as the refresh mechanism for an autonomous private scene, effectively requiring player chat every expiry interval.
  - Corrected rule: a TTL only expires cached data; it does not refresh the native producer. Autonomous presence needs a verified timer/event or server-to-native query path independent of dialogue, otherwise the feature must be described as dialogue-triggered.
  - Prevention: trace producer refresh, command dispatch, native execution, and response handling end to end before promising automatic freshness; never equate a successful receipt-time TTL with an autonomous update.
- Mistake pattern: concluding that installed game-side automatic presence updates are absent after tracing only CHIM's ordinary-input producer and command queue; this missed ShARMAT's independent OStim/SexLab scene events.
  - Corrected rule: trace installed companion callbacks and their payloads through the server consumer, then distinguish active-scene state from a general nearby-and-CHIM-managed eligibility roster.
  - Prevention: inspect the exact installed companion sources before ruling out an automatic producer, and compare emitted fields and scope against the required data before treating an existing event as reusable.
- Mistake pattern: treating a game-runtime RefID or display name as durable NPC identity.
  - Corrected rule: join the snapshot name to exactly one normalized current catalog row and persist only that row's stable database ID; reject missing or ambiguous matches.
  - Prevention: never use RefIDs as persistent pair identity; if bounded cache metadata retains one, test duplicate, missing, and renamed catalog entries.
- Mistake pattern: treating a general request to proceed as permission to weaken an explicit freshness/eligibility requirement or add a new game mod before checking installed background integrations.
  - Corrected rule: preserve the user's plain-language requirement for NPCs in the player's immediate vicinity and the existing requirement for current CHIM-AI activity. Do not silently redefine “immediate vicinity” as an assistant-selected Standard `audience_radius_units` value (including a sneaking adjustment); make any geometric interpretation explicit and validate it against the user's intent. A 3000-unit surroundings scan, catalog row, or stale activity row is not by itself proof of eligibility.
  - Prevention: audit existing native producers, installed mod callbacks, server consumers, and browser/API routes end to end before proposing new code. Exercise idle-without-chat, movement in/out, AI on/off, cell/load changes, authoritative empty versus unknown/stale, and delayed/out-of-order events. Do not infer empty from a missing report or let UI polling renew producer freshness.
- Mistake pattern: writing a nested project's task plan into the shared Exchange-root `tasks/` directory, leaving the target project's checklist unchanged.
  - Corrected rule: update task and lesson files under the actual target project root, even when the workspace has a higher-level task directory.
  - Prevention: verify the target project path and read back the exact absolute file paths after writing; do not count a workspace-level checklist as the project's plan.
- Mistake pattern: running PHP fixture tests with `php -n` and treating a missing POSIX extension as a product failure.
  - Corrected rule: when disabling configured extensions for fixture isolation, explicitly load required test extensions such as POSIX; separate harness startup failures from application failures.
  - Prevention: inspect fixture dependencies before changing product code, and do not add product guards to compensate for an incomplete test invocation.

- Mistake pattern to avoid: retaining an earlier mascot placement after the user revises the visual brief.
  - Corrected rule: the new placement replaces the old cameo; use the existing background banner when requested, with exactly one mascot depiction.
  - Prevention: inspect the full artwork, remove the superseded cameo, update alternative text, and verify the current browser image rather than a cached version.

- Mistake pattern: expanding a plugin logging task into changes to its collaborating plugin without preserving the user's project ownership boundary.
  - Corrected rule: when the user assigns collaborator changes to a separate task, keep this plugin compatible with the published collaborator and provide a precise follow-up prompt.
  - Prevention: separate required local diagnostics from optional collaborator telemetry; label unsupported observation explicitly and undo only current-task collaborator edits.
