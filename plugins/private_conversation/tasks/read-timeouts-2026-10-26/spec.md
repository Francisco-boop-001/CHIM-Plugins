# PCV SQL read wait bounds

The human approved the reviewed identity/catalog timeout fixes and coordinated PCV/Department publication. Current owner baseline is e89e67dfbccbad75376a4141684a85a317244c31, released0.1.23. New available version:0.1.24 BETA.

Use the unchanged official ptp_connect() FORCE_NEW connection for PCV reads. One small owned-read helper starts a read-only repeatable-read transaction, caps lock waits at2000ms and each statement at3000ms while retaining shorter positive configured limits, and commits or rolls back and closes only its own connection. Reuse it for identity and catalog. Read the same full public.core_npc_master rows/types as NpcMaster::getAll(), with no new count/filter/order policy. Existing identity cache, explicit refresh, no-active/no-table fallback and fail-closed error handling remain. Other plugins' shared connection, transaction, work and settings remain untouched.

Approaches: changing CHIM's private shared sql connection would cross ownership and risk caller work; global/session settings would leak policy. A dedicated official owned connection is the smallest supported isolation. No dependencies, state/routing/consent/schema changes, NPC cap, DLL, providers or new telemetry.

Only TEST2, a fresh owned disposable PostgreSQL cluster and isolated fixture exports may run. Host-side registry/distro/lease checks and explicit Department handoff precede WSL entry. Preserve initial stopped state. No installed core/server, live/game/campaign DB or other distro changes. Per-statement bounds are not a whole-request deadline.

PCV0.1.24 and Department20 remain unpublished until both exact candidates, overlay/known23-backup migration, focused runtime proof and draft assets are ready. New source hashes are computed from actual bytes, never guessed. Keep prior tags/assets immutable and browser fallback available; no support for unknown versions by loosening pins.
