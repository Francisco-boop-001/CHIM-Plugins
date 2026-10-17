# Mind Poisoning 0.1.22 — verification and limits

## Status

The generated-alias change is implemented in the working tree and passed isolated verification. This page records only those source-level checks. Published and installed 0.1.21 remain affected by the wrong-subject case described below; installed 0.1.22 behavior is outside this evidence.

## Working-tree verification

The two new focused regressions first failed before the resolver change: the influence check selected a subject through the generic `Vampire` candidate, and the runtime check returned `committed` where `no-subjects` was expected. After the change:

- `tests/influence_test.php` — passed.
- `tests/runtime_test.php` — passed.
- `tests/reflection_reply_test.php` — passed.
- `tests/overhearing_test.php` — passed.
- PHP syntax checks for `server/influence.php`, `tests/influence_test.php`, and `tests/runtime_test.php` — all passed.

These were four isolated test scripts, not the complete repository suite. They ran with the standalone Windows PHP 8.3.35 CLI using `-n` and explicitly loaded bundled `mbstring` and `pgsql` extensions. The tests used an in-memory store and fakes; `pgsql` was needed for constants only, and no PostgreSQL connection, model provider, CHIM service, MO2 sync, or gameplay session was used. Windows skipped the runtime suite's symlink-creation checks, so these runs are not a full operating-system security proof.

A first bare `php -n` probe without `mbstring` stopped at the existing `mb_strtolower` identity-matcher call before reaching the mock callback. Loading the bundled extension removed that harness setup failure; it was not a product regression.

## Change scope

The fix excludes exact `Vampire` and `Black` tokens from generated aliases across the existing first-word, phrase-before-`the`, and bracket-stripped candidate routes. It leaves exact catalog names available, including `Vampire Fledgling`, `Black Widow`, and literal mononyms such as `Black`, `Guard`, and `King`. Tests also retain normal-name, Unicode, possessive, and alias-collision behavior. Common-word names such as `May` and `Guard` can still require model semantic judgment. This does not add pronoun resolution, a taxonomy, model-prompt changes, schema changes, or a broader alias engine.

## Related evidence

The prior installed 0.1.21 reflection observation produced a confirmed combined affinity and relationship-type commit, later visible in a read-only dashboard check. It is one successful case, not a reliability estimate. A separate 0.1.21 observation showed a generic vampire reference selecting the catalog character `Vampire Fledgling`, followed by a commit to the wrong subject. That is a subject-selection error, not a persistence error. Both are summarized without private dialogue or actor/request/event identifiers in the [live-acceptance summary](live-acceptance-summary.md).

The 10-scenario B1 PostgreSQL campaign and 9-scenario B3 server install/upgrade/dashboard/recovery campaign passed on 0.1.20. They remain evidence for those tested paths and do not validate a 0.1.22 package. See the [candidate evidence summary](beta-candidate-evidence.md) for their scope.

## Other beta gates not covered here

- Complete a cold 0.1.22 install or upgrade on the selected supported route and verify installed version/files and CHIM/client versions. End-to-end topic discovery is not established.
- Complete the remaining B2 NPC-to-NPC semantic/editor/history and current-candidate lock/scope/On-Off/save-load checks.
- Complete B4 active/paused acknowledgement and timing, stalled-provider recovery, and selected-model cost/deadline checks.
- Provider reliability, audio, and Skyrim vanilla relationship-rank changes are not established by these checks.

Overhearing remains default-off and may remain experimental within a scoped beta. Standard GitHub release classification is for CHIM discovery; it does not graduate the candidate or close these gates.
