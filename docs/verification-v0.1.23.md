# Mind Poisoning 0.1.23 — verification and limits

## Status

The relationship-context addition has focused isolated PHP coverage. This page records that source-level evidence and its limits; it is not a package-install, provider, live-database, or gameplay report.

## Focused checks

The following checks passed using standalone Windows PHP 8.3.35 with `-n` and the bundled `mbstring` extension:

- `tests/relationship_context_test.php` — passed. It checks the owner-to-subject context projection for ordinary, reflection, and overhearing builders; bounded UTF-8 strings and omission of invalid optional fields; unchanged affinity/type and other projection inputs; and rejection of relationship notes alone as event evidence.
- `tests/relationship_type_judgments_test.php` — passed.
- `tests/influence_test.php` — passed.
- `tests/reflection_test.php` — passed.
- `tests/overhearing_test.php` — passed.
- PHP syntax checks for `server/influence.php` and `tests/relationship_context_test.php` — passed.

The checks use isolated test seams and do not contact a PostgreSQL server or model provider. The optional snippets come from existing owner-to-subject relationship data; each valid nonempty string is limited to 160 UTF-8 bytes. The feature adds no model call, schema, persisted relationship state, or relationship label. It does not test whether a provider interprets a note correctly, and no outcome is guaranteed.

The earlier context-comparison design is not evidence of final 0.1.23 provider behavior. No provider comparison was run for this release change. The 10-scenario B1 PostgreSQL and 9-scenario B3 install/recovery campaigns used 0.1.20; they remain useful for those tested paths but do not validate a 0.1.23 package. The installed 0.1.21 reflection observation is separate historical evidence, not a test of the new relationship context.

For route selection and duplicate-copy recovery steps, see the [0.1.23 release notes](../distribution/mind_poisoning-v0.1.23.md).

## Not established here

- Cold installation or upgrade of the 0.1.23 package, including installed-file hashes and the chosen MO2 or CHIM sync route.
- Live database persistence of the new prompts' resulting judgments.
- Provider interpretation, latency, cost, or reliability for relationship context.
- Skyrim vanilla relationship-rank changes, audible playback, or gameplay outcomes.

The release remains **BETA CANDIDATE**. Standard GitHub release classification is retained for newer CHIM topic discovery; it does not change this maturity label or substitute for the checks above.
