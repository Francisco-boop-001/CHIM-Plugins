# Lessons

- Runtime isolation includes the interpreter's environment, not only fixture data. Never execute even read-only PHP checks inside `DwemerAI4Skyrim3`: booting it can start the real CHIM environment and write its VHD. Use an independent PHP installation or the explicitly named disposable clone under its documented safety procedure. Historical commands naming the gaming distro are evidence, not commands to repeat.

- A transport listener can be the Player even when the NPC catalog contains a same-named row. Establish the path's authoritative actor/target contract before applying ordinary NPC identity ambiguity rules to a registered reflection.
- A numeric API version bump does not preserve an existing caller that requires exact equality. Keep the old entry point and its advertised version usable; negotiate new behavior through a separate verified capability.
- Multi-line correlation must bind the immutable ordered output of one server request. Matching names, targets or adjacent IDs cannot establish reply membership; replay protection must cover every consumed source line in the same transaction as the effect.

- When a user rejects a UI as generic/AI-like and supplies a visual reference, treat the rejection as a design correction even if prior functionality and accessibility checks passed. Inspect the reference, rebuild the visual hierarchy around its materials and composition, use real integrated artwork when requested, and review desktop/mobile plus every explicit theme before handoff.

- In cross-shell byte checks, count carriage returns with `bytes([13])`; do not infer newline corruption from a shell-escaped `\r` search. Confirm actual byte values before asking owners to normalize files.

- When documenting stored model context, name every isolation boundary enforced by the code: listener, playthrough, and relevant subjects. Check the player guide against the implementation and technical guide.

- CHIM integration fixtures must represent the actual helper contract, including passive headers and interaction generations. A mocked global On/Off flag cannot establish callback eligibility.
- Keep event correlation separate from text representation: when CHIM logs context text but acknowledges client speech, bind the source by exact event/actors and make explicit which reported text drives subjects and evidence. Never infer delivery of a logged-only tail.

- Public READMEs must address prospective users first: purpose, a concrete example, current status, installation, requirements and support. Keep release procedures, verification ledgers, local machine paths and database internals in developer documentation. Use Francisco's dry, humorous, sarcastic voice in introductory copy while keeping instructions and limitations precise.

- For a server-only CHIM plugin, inspect the user's intended Plugin Manager/catalog workflow before selecting a distribution artifact. The schema-4 `.dwpkg` sync path and repository tarball installer are different consumers; verify the selected consumer's archive layout and UI rather than describing a generic upload step.
- A repository hosting multiple plugins needs per-plugin manifest and release URLs. Avoid repository-wide `latest`, and check catalog identity matching before adding a second plugin under the same `git_repo`; tag-specific assets do not fix a first-match-by-repository UI bug.
- Keep CHIM distribution gates separate: top-level `Data/CHIM` CSV imports, server code under `HerikaServer/ext/<plugin>`, the installed plugin manifest's `schema_version: 2` plus nonempty `git_repo` update gate, and this project's outer schema-4 `.dwpkg` envelope are different consumers. Verify the shipped manifest, matching channel fields, and intended installer; a passing archive check does not prove manager-update metadata is present or fixed.
- Resolve protected-repository identifiers with an explicit repository path (`git -C <repo> ...`, plus the required safe-directory setting when applicable). If changing directories fails, stop; never let the command fall through and report the caller checkout's `HEAD` as the protected repository revision.
- Use CHIM's per-plugin `NpcMaster` data methods for isolated NPC values, but do not treat a one-namespace statement as a transaction or history snapshot. Before combining plugin data, relationships, dedupe state, and history, verify same-connection transaction and rollback behavior on the target server.

- Before catalog submission, reread the current official submission checklist and upstream PR template, not just runtime consumer code. Supported custom manifest URLs/channels do not establish catalog-policy compliance. Verify repository-root manifest, required main channel, clean-server installation evidence, and maintainer discussion; keep technical compatibility and maintainer acceptance separate.

- When a PostgreSQL SELECT exposes an ID as text, qualify the underlying numeric column in ORDER BY; an unqualified output alias can silently change cap/tie selection to lexical order. Exercise bounded selection against real synthetic SQL data, including IDs with different digit lengths.
- Verify the actual Windows-to-WSL request source before treating host access as loopback. Preserve the access gate when a localhost route works; never infer safe access from private-range or default-gateway membership.
- A FOMOD source/destination mapping proves where selected files are copied, not whether MO2 classifies the resulting mod as valid game content. For CHIM-only payloads, verify the intended mapping and describe any remaining content warning; preview installers only when they can be canceled before installation, and never alter a user's modlist for a packaging check.
- State supported speaker, listener and subject roles explicitly before suggesting live test scenarios. Player-origin dialogue and NPC speech acknowledgements are different event paths; inspect the actual player input contract before extending support, rather than merely removing an actor exclusion.
- Treat named roleplay examples as acceptance examples, not feature scope. Player gossip must support arbitrary identifiable NPC subjects and positive, negative or neutral speech; never hard-code example characters or require a negative delta.

- Treat a reported installer crash as a distinct failure from a content warning. Archive checksums, XML parsing and file mappings do not prove native MO2/FOMOD execution; inspect the actual installer implementation and crash evidence before claiming compatibility.

- A plain CHIM-only ZIP bypasses FOMOD but does not satisfy MO2's Skyrim content checker. When asked to remove its validation error, inspect the exact checker and ship a legitimate accepted payload layout; do not present bypassing one installer as resolving content validation.

- Separate CHIM's supported regular-mod/file-sync distribution from MO2's generic content classifier. A checker warning does not invalidate the author's supported distribution route. Explain the boundary and investigate installer compatibility before proposing a different installation system.

- Do not make optional CHIM Playthrough Saves an implicit plugin prerequisite. Verify ordinary unprofiled operation as well as active-profile mode; keep shared-database scope distinct from a unique Skyrim save, and apply the same identity contract to persistence, logs and dashboard readers.

- Do not label file length or repeated validation as technical debt without demonstrating a maintenance cost. Trace callers, trust boundaries and transaction timing first; propose extraction only when it reduces real coupling or rule drift without hiding safety-critical sequencing.

- Aggregate application logs are not necessarily the last available evidence. Before calling a cause unrecoverable, check whether authorized retained request logs can be correlated by timestamps and payload shape. Attribute that conclusion to the report that performed the correlation; do not claim an independent re-decode, and keep historical release behavior separate from an unshipped working-tree fix.

- Catalogue matching is a release gate across every plugin in a collection, not just a per-manifest check. At the inspected CHIM revision, repository-or-name first matching makes shared git_repo identities unsafe for independent plugins. Test both entry orders and single-entry catalogues before listing; explicit versioned assets alone do not solve selection.
- Cross-plugin evidence must identify the exact consumer source and whether it is committed/published. A passing test against a drifting sibling development folder does not establish published-consumer compatibility. Keep a repository-contained, versioned integration snapshot or a reproducible pinned dependency, and execute the check from a clean tag.
- Hook placement must be checked against emission, request-lock release and existing guards. A hook named prepostrequest still runs after flushing at the inspected revision; report early acknowledgements explicitly and never claim a timing race is closed solely by moving a hook.

## Standalone deployment source exports

When splitting a plugin into its own repository, carry its source-controlled line-ending policy. Equal Git trees do not prove equal exported build inputs under different attributes and host settings. Compare clean commit/tag consumer assets byte for byte before publication; keep failed candidate tags local until the mismatch is resolved.

## Reserved transport markers — 2026-10-04

Do not infer that a non-person marker cannot resolve through the NPC catalog. A catalog row can share its name. Exclude reserved listener markers explicitly before addressed/witness identity resolution; exercise a same-named row with the existing isolated provider/store seams. Keep the marker's valid source-registration role separate from recipient selection.

- Installation feedback is a functional usability defect: choose one recommended download per audience and explain what it already contains. Keep public release versions synchronized with the active guide. Distinguish MO2 metadata/version display, game-content classification and CHIM package synchronization; never present meta.ini or Ignore as proof that a server installation succeeded.

- Before asking a user to reproduce or transcribe an interaction, check authorized retained speech/request logs beyond the sanitized plugin export. Use exact IDs, bounded read-only access and shape/name-presence analysis; preserve the gaming-distro execution ban and never treat model metadata or prompt context as spoken evidence.

- Standalone dashboards must be tested in a fresh PHP process without gameplay globals or preloaded core classes. A fixture that preloads a dependency can hide an entrypoint bootstrap defect; distinguish a missing helper from invalid stored data instead of assigning both the same display state.
