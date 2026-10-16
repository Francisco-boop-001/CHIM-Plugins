<?php
declare(strict_types=1);

use function ChimMindPoisoning\dashboardBuildInteractions;
use function ChimMindPoisoning\dashboardCurrent;
use function ChimMindPoisoning\dashboardIndexRecords;
use function ChimMindPoisoning\dashboardLogInteraction;
use function ChimMindPoisoning\dashboardNormalizedOutcome;
use function ChimMindPoisoning\dashboardParseLogLine;
use function ChimMindPoisoning\renderDashboard;

require_once __DIR__ . '/fixtures/relationship_manager.cf5030f15781637498be86debe26fcf102f5690d.php';
require_once __DIR__ . '/../server/dashboard_data.php';
require_once __DIR__ . '/../server/dashboard_view.php';

function dashboardTypeChangesAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dashboardTypeChangesLine(array $fields): string
{
    $record = array_merge([
        'schema_version' => 1,
        'plugin' => 'mind_poisoning',
        'version' => '0.1.6',
        'timestamp' => '2026-09-27T16:00:00Z',
        'request_id' => 'abcdef0123456789abcdef01',
        'level' => 'info',
        'event' => 'persistence_finished',
        'event_id' => 42,
        'playthrough_id' => '17',
        'speaker_kind' => 'player',
        'listener_id' => 7,
        'utterance_id' => 'utt_abcdefgh',
        'commit_state' => 'confirmed',
        'committed' => true,
        'outcome' => 'committed',
    ], $fields);
    return '[2026-09-27 16:00:00] [info] ' . json_encode(
        $record,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
}

$safeChange = ['subject' => 'player', 'before' => 'neutral', 'after' => 'ally'];
$safeDecision = ['subject' => 'player', 'from' => 'neutral', 'to' => 'ally', 'reason' => 'change_eligible'];
$parsed = dashboardParseLogLine(dashboardTypeChangesLine([
    'changed_count' => 1,
    'affinity_changed_count' => 0,
    'type_changed_count' => 1,
    'changes' => [],
    'type_changes' => [$safeChange],
    'type_decisions' => [$safeDecision + ['evidence' => 'drop this free text']],
    'raw_speech' => 'drop this field',
]));
dashboardTypeChangesAssert(
    is_array($parsed)
        && ($parsed['changed_count'] ?? null) === 1
        && ($parsed['affinity_changed_count'] ?? null) === 0
        && ($parsed['type_changed_count'] ?? null) === 1
        && ($parsed['type_changes'] ?? null) === [$safeChange]
        && ($parsed['type_decisions'] ?? null) === [$safeDecision]
        && !isset($parsed['raw_speech']),
    'The diagnostics parser must retain bounded relationship-type evidence and discard free text.'
);
$countOnlyTypeEvidence = $parsed;
$countOnlyTypeEvidence['changed_count'] = 0;
dashboardTypeChangesAssert(
    dashboardNormalizedOutcome($countOnlyTypeEvidence) === 'committed',
    'A confirmed type-only commit must not normalize to zero change when the affinity change list is empty.'
);

$unsafeParsed = dashboardParseLogLine(dashboardTypeChangesLine([
    'changed_count' => 9,
    'affinity_changed_count' => 0,
    'type_changed_count' => 9,
    'type_changes' => [
        ['subject' => 'npc:9', 'before' => str_repeat('a', 51), 'after' => 'ally'],
        ['subject' => 'npc:9', 'before' => 'neutral', 'after' => 'Aela told me a secret'],
        ['subject' => 'npc:9', 'before' => 'neutral', 'after' => 'ally', 'credential' => 'drop'],
    ],
    'type_decisions' => [
        ['subject' => 'npc:9', 'from' => 'neutral', 'to' => 'ally', 'reason' => 'model said so'],
        ['subject' => 'npc:9', 'from' => 'neutral', 'to' => str_repeat('a', 51), 'reason' => 'same_type'],
    ],
    'unexpected' => 'drop',
]));
dashboardTypeChangesAssert(
    is_array($unsafeParsed)
        && !isset($unsafeParsed['changed_count'], $unsafeParsed['type_changed_count'])
        && ($unsafeParsed['affinity_changed_count'] ?? null) === 0
        && ($unsafeParsed['type_changes'] ?? []) === [['subject' => 'npc:9', 'before' => 'neutral', 'after' => 'ally']]
        && ($unsafeParsed['type_decisions'] ?? []) === []
        && !isset($unsafeParsed['unexpected']),
    'The diagnostics parser must reject oversized counts, unbounded or prose type values, invalid reasons, and unknown fields.'
);

$unconfirmedParsed = dashboardParseLogLine(dashboardTypeChangesLine([
    'commit_state' => 'unconfirmed',
    'committed' => false,
    'changed_count' => 1,
    'affinity_changed_count' => 0,
    'type_changed_count' => 1,
    'type_changes' => [$safeChange],
    'type_decisions' => [$safeDecision],
]));
dashboardTypeChangesAssert(
    is_array($unconfirmedParsed)
        && dashboardNormalizedOutcome($unconfirmedParsed) === 'unconfirmed'
        && !isset($unconfirmedParsed['type_changes'], $unconfirmedParsed['type_changed_count'])
        && ($unconfirmedParsed['type_decisions'] ?? null) === [$safeDecision],
    'Unconfirmed persistence may retain a proposal but must not expose an applied relationship-type transition.'
);

$relationshipOwner = [
    'id' => '7',
    'extended_data' => (object)['relationships' => (object)[
        'Player' => (object)['aff' => 50, 'type' => 'romantic'],
        'Dragonborn' => (object)['aff' => 1, 'type' => 'neutral'],
        'Anora' => (object)['aff' => 24, 'type' => '<bespoke>'],
        'Alias' => (object)['aff' => 30, 'type' => 'romance'],
        'Custom' => (object)['aff' => 31, 'type' => 'moonbound'],
    ]],
];
$currentPlayer = dashboardCurrent($relationshipOwner, 'player', 'Player', 'Dragonborn', []);
$currentCustom = dashboardCurrent($relationshipOwner, 'npc:9', 'Anora', 'Dragonborn', ['Anora' => 1]);
dashboardTypeChangesAssert(
    ($currentPlayer['type'] ?? null) === 'romantic' && ($currentPlayer['type_state'] ?? null) === 'set'
        && ($currentCustom['type'] ?? null) === '<bespoke>' && ($currentCustom['type_state'] ?? null) === 'invalid',
    'Current type must come from the canonical Player edge or native NPC edge, and a rejected current type must remain marked invalid.'
);
$currentNativeCustom = dashboardCurrent($relationshipOwner, 'npc:11', 'Custom', 'Dragonborn', ['Custom' => 1]);
dashboardTypeChangesAssert(
    ($currentNativeCustom['type'] ?? null) === 'moonbound' && ($currentNativeCustom['type_state'] ?? null) === 'set',
    'A current native custom type accepted by the core canonicalizer must remain visible.'
);
$currentAlias = dashboardCurrent($relationshipOwner, 'npc:10', 'Alias', 'Dragonborn', ['Alias' => 1]);
dashboardTypeChangesAssert(
    ($currentAlias['type'] ?? null) === 'romantic' && ($currentAlias['type_state'] ?? null) === 'set',
    'A current native edge type should use the existing core canonicalizer when it recognizes an alias.'
);
$missingTypeOwner = ['id' => '7', 'extended_data' => (object)['relationships' => (object)['Anora' => (object)['aff' => 24]]]];
$invalidTypeOwner = ['id' => '7', 'extended_data' => (object)['relationships' => (object)['Anora' => (object)['aff' => 24, 'type' => ['bad']]]]];
dashboardTypeChangesAssert(
    (dashboardCurrent($missingTypeOwner, 'npc:9', 'Anora', 'Dragonborn', ['Anora' => 1])['type_state'] ?? null) === 'unset'
        && (dashboardCurrent($invalidTypeOwner, 'npc:9', 'Anora', 'Dragonborn', ['Anora' => 1])['type_state'] ?? null) === 'invalid'
        && (dashboardCurrent([], 'npc:9', 'Anora', 'Dragonborn', ['Anora' => 1])['type_state'] ?? null) === 'invalid',
    'Missing, invalid, and unavailable current type states must not collapse into a made-up default type.'
);

$protectedDecision = ['subject' => 'npc:9', 'from' => 'romantic', 'to' => 'jealous', 'reason' => 'protected_type_preserved'];
$sameTypeDecision = ['subject' => 'npc:10', 'from' => 'neutral', 'to' => 'neutral', 'reason' => 'same_type'];
$logFields = [
    'request_id' => 'abcdef0123456789abcdef01',
    'event_id' => '42',
    'utterance_id' => 'utt_abcdefgh',
    'playthrough_id' => '17',
    'speaker_kind' => 'player',
    'listener_id' => '7',
];
$persistence = $logFields + [
    'event' => 'persistence_finished',
    'commit_state' => 'confirmed',
    'committed' => true,
    'outcome' => 'committed',
    'changed_count' => 1,
    'affinity_changed_count' => 0,
    'type_changed_count' => 1,
    'changes' => [],
    'type_changes' => [$safeChange],
    'type_decisions' => [$safeDecision, $protectedDecision, $sameTypeDecision],
];
$request = $logFields + ['event' => 'request_finished', 'outcome' => 'committed'];
$logRecords = [$persistence, $request];
$logInteraction = dashboardLogInteraction(
    $request,
    '17',
    'active',
    ['7' => 'Listener', '9' => 'Anora'],
    ['7' => $relationshipOwner],
    dashboardIndexRecords($logRecords),
    true,
    'Dragonborn'
);
dashboardTypeChangesAssert(
    $logInteraction['outcome'] === 'committed'
        && $logInteraction['changes'] === []
        && ($logInteraction['type_changes'][0]['before'] ?? null) === 'neutral'
        && ($logInteraction['type_changes'][0]['after'] ?? null) === 'ally'
        && ($logInteraction['type_changes'][0]['current_type'] ?? null) === 'romantic'
        && ($logInteraction['type_changes'][0]['current_type_state'] ?? null) === 'set'
        && ($logInteraction['type_decisions'][1]['reason'] ?? null) === 'protected_type_preserved'
        && ($logInteraction['type_decisions'][1]['current_type'] ?? null) === '<bespoke>',
    'A confirmed type-only event must remain committed and expose actual, proposed, and independently read current types.'
);
$unavailableCurrentInteraction = dashboardLogInteraction(
    $request,
    '17',
    'unverified',
    [],
    [],
    dashboardIndexRecords($logRecords),
    false,
    'Dragonborn'
);
dashboardTypeChangesAssert(
    ($unavailableCurrentInteraction['type_changes'][0]['current_type'] ?? null) === null
        && ($unavailableCurrentInteraction['type_changes'][0]['current_type_state'] ?? null) === 'unavailable',
    'Unavailable current relationship data must remain distinct from missing and invalid current types.'
);

$unconfirmedPersistence = $persistence;
$unconfirmedPersistence['commit_state'] = 'unconfirmed';
$unconfirmedPersistence['committed'] = false;
$unconfirmedPersistence['type_changes'] = [$safeChange];
$unconfirmedPersistence['type_changed_count'] = 1;
$unconfirmedRequest = $request;
$unconfirmedRequest['request_id'] = 'abcdef0123456789abcdef01';
$unconfirmedRequest['outcome'] = 'committed';
$unconfirmedIndex = dashboardIndexRecords([$unconfirmedPersistence, $unconfirmedRequest]);
$unconfirmedInteraction = dashboardLogInteraction(
    $unconfirmedRequest,
    '17',
    'active',
    ['7' => 'Listener', '9' => 'Anora'],
    ['7' => $relationshipOwner],
    $unconfirmedIndex,
    true,
    'Dragonborn'
);
dashboardTypeChangesAssert(
    $unconfirmedInteraction['outcome'] === 'unconfirmed'
        && ($unconfirmedInteraction['type_changes'] ?? []) === []
        && ($unconfirmedInteraction['type_decisions'][0]['to'] ?? null) === 'ally',
    'The interaction projection must not treat an unconfirmed logged transition as applied.'
);

$ledgerEvent = [
    'event_id' => 43,
    'utterance_id' => 'utt_typeonly1',
    'judgments' => [
        ['subject' => 'player', 'delta' => 0, 'reason' => 'protected relationship', 'evidence' => 'old bond', 'type_change' => ['from' => 'romantic', 'to' => 'jealous']],
        ['subject' => 'npc:9', 'delta' => 0, 'reason' => 'new bond', 'evidence' => 'shared work'],
    ],
    'type_decisions' => [
        ['subject' => 'player', 'from' => 'romantic', 'to' => 'jealous', 'reason' => 'protected_type_preserved'],
        ['subject' => 'npc:9', 'from' => 'neutral', 'to' => 'ally', 'reason' => 'change_eligible'],
    ],
    'type_changes' => [['subject' => 'npc:9', 'before' => 'neutral', 'after' => 'ally']],
];
$ledgerDatabase = [
    'active_playthrough' => '17',
    'player_name' => 'Dragonborn',
    'identities' => ['7' => 'Listener', '9' => 'Anora'],
    'rows' => [[
        'id' => '7',
        'extended_data' => $relationshipOwner['extended_data'],
        'plugin_extended_data' => (object)['mind_poisoning' => (object)[
            'playthrough_id' => '17',
            'floor_event_id' => 0,
            'events' => [$ledgerEvent],
        ]],
    ]],
];
$ledgerLimited = false;
$ledgerInteractions = dashboardBuildInteractions($ledgerDatabase, [], $ledgerLimited);
dashboardTypeChangesAssert(
    count($ledgerInteractions) === 1
        && !$ledgerLimited
        && $ledgerInteractions[0]['outcome'] === 'committed'
        && ($ledgerInteractions[0]['type_changes'][0]['before'] ?? null) === 'neutral'
        && ($ledgerInteractions[0]['type_changes'][0]['after'] ?? null) === 'ally'
        && count($ledgerInteractions[0]['type_decisions'] ?? []) === 2
        && ($ledgerInteractions[0]['type_decisions'][0]['reason'] ?? null) === 'protected_type_preserved',
    'A retained typed ledger entry must show explicit applied type evidence and remain committed when affinity deltas are zero.'
);
$proposalOnlyLedgerEvent = [
    'event_id' => 45,
    'utterance_id' => 'utt_proposal01',
    'judgments' => [[
        'subject' => 'npc:9',
        'delta' => 0,
        'reason' => 'new bond',
        'evidence' => 'shared work',
        'type_change' => ['from' => 'neutral', 'to' => 'suspicious'],
    ]],
    'type_decisions' => [[
        'subject' => 'npc:9',
        'from' => 'neutral',
        'to' => 'suspicious',
        'reason' => 'change_eligible',
    ]],
];
$proposalOnlyDatabase = unserialize(serialize($ledgerDatabase), ['allowed_classes' => true]);
$proposalOnlyDatabase['rows'][0]['plugin_extended_data']->mind_poisoning->events = [$proposalOnlyLedgerEvent];
$proposalOnlyLimited = false;
$proposalOnlyInteractions = dashboardBuildInteractions($proposalOnlyDatabase, [], $proposalOnlyLimited);
dashboardTypeChangesAssert(
    count($proposalOnlyInteractions) === 1
        && !$proposalOnlyLimited
        && $proposalOnlyInteractions[0]['outcome'] === 'committed'
        && ($proposalOnlyInteractions[0]['changes'][0]['proposed'] ?? null) === 0
        && ($proposalOnlyInteractions[0]['type_changes'] ?? []) === []
        && ($proposalOnlyInteractions[0]['type_decisions'][0]['reason'] ?? null) === 'change_eligible'
        && ($proposalOnlyInteractions[0]['type_decisions'][0]['from'] ?? null) === 'neutral'
        && ($proposalOnlyInteractions[0]['type_decisions'][0]['to'] ?? null) === 'suspicious',
    'A history-only eligible type proposal without applied evidence must stay readable without claiming zero change or an applied transition.'
);
$provenNoTypeChangeEvent = [
    'event_id' => 46,
    'utterance_id' => 'utt_nochange01',
    'judgments' => [
        ['subject' => 'player', 'delta' => 0, 'reason' => 'protected relationship', 'evidence' => 'old bond', 'type_change' => ['from' => 'romantic', 'to' => 'jealous']],
        ['subject' => 'npc:9', 'delta' => 0, 'reason' => 'custom relationship', 'evidence' => 'old bond', 'type_change' => ['from' => 'moonbound', 'to' => 'ally']],
        ['subject' => 'npc:10', 'delta' => 0, 'reason' => 'same relationship', 'evidence' => 'same bond', 'type_change' => ['from' => 'neutral', 'to' => 'neutral']],
    ],
    'type_decisions' => [
        ['subject' => 'player', 'from' => 'romantic', 'to' => 'jealous', 'reason' => 'protected_type_preserved'],
        ['subject' => 'npc:9', 'from' => 'moonbound', 'to' => 'ally', 'reason' => 'custom_type_preserved'],
        ['subject' => 'npc:10', 'from' => 'neutral', 'to' => 'neutral', 'reason' => 'same_type'],
    ],
];
$provenNoTypeChangeDatabase = unserialize(serialize($ledgerDatabase), ['allowed_classes' => true]);
$provenNoTypeChangeDatabase['rows'][0]['plugin_extended_data']->mind_poisoning->events = [$provenNoTypeChangeEvent];
$provenNoTypeChangeLimited = false;
$provenNoTypeChangeInteractions = dashboardBuildInteractions($provenNoTypeChangeDatabase, [], $provenNoTypeChangeLimited);
dashboardTypeChangesAssert(
    count($provenNoTypeChangeInteractions) === 1
        && !$provenNoTypeChangeLimited
        && $provenNoTypeChangeInteractions[0]['outcome'] === 'zero-change'
        && ($provenNoTypeChangeInteractions[0]['type_changes'] ?? []) === []
        && count($provenNoTypeChangeInteractions[0]['type_decisions'] ?? []) === 3,
    'Protected, custom, and same-type ledger decisions prove no type application and must preserve zero-change classification.'
);
$legacyZeroEvent = [
    'event_id' => 47,
    'utterance_id' => 'utt_legacyzero1',
    'judgments' => [['subject' => 'npc:9', 'delta' => 0, 'reason' => 'same bond', 'evidence' => 'no change']],
];
$legacyZeroDatabase = unserialize(serialize($ledgerDatabase), ['allowed_classes' => true]);
$legacyZeroDatabase['rows'][0]['plugin_extended_data']->mind_poisoning->events = [$legacyZeroEvent];
$legacyZeroLimited = false;
$legacyZeroInteractions = dashboardBuildInteractions($legacyZeroDatabase, [], $legacyZeroLimited);
dashboardTypeChangesAssert(
    count($legacyZeroInteractions) === 1
        && !$legacyZeroLimited
        && $legacyZeroInteractions[0]['outcome'] === 'zero-change'
        && ($legacyZeroInteractions[0]['type_decisions'] ?? []) === []
        && ($legacyZeroInteractions[0]['type_changes'] ?? []) === [],
    'Legacy affinity-only zero history must remain zero-change when there is no unresolved type proposal.'
);
$ledgerUnconfirmedRecord = [
    'request_id' => 'abcdef0123456789abcdef01',
    'event' => 'persistence_finished',
    'event_id' => '43',
    'utterance_id' => 'utt_typeonly1',
    'playthrough_id' => '17',
    'listener_id' => '7',
    'commit_state' => 'unconfirmed',
    'committed' => false,
    'changed_count' => 1,
    'type_changed_count' => 1,
    'type_changes' => [['subject' => 'npc:9', 'before' => 'neutral', 'after' => 'ally']],
];
$ledgerUnconfirmedLimited = false;
$ledgerUnconfirmedInteractions = dashboardBuildInteractions($ledgerDatabase, [$ledgerUnconfirmedRecord], $ledgerUnconfirmedLimited);
dashboardTypeChangesAssert(
    ($ledgerUnconfirmedInteractions[0]['outcome'] ?? null) === 'unconfirmed'
        && ($ledgerUnconfirmedInteractions[0]['type_changes'] ?? []) === []
        && ($ledgerUnconfirmedInteractions[0]['type_decisions'][0]['reason'] ?? null) === 'protected_type_preserved',
    'An explicit unconfirmed persistence record must suppress even a stored ledger transition while retaining its proposal.'
);

$legacyEvent = [
    'event_id' => 44,
    'utterance_id' => 'utt_legacy01',
    'judgments' => [['subject' => 'npc:9', 'delta' => 2, 'reason' => 'friendlier', 'evidence' => 'helped in a fight']],
];
$legacyDatabase = $ledgerDatabase;
$legacyDatabase['rows'][0]['plugin_extended_data']->mind_poisoning->events = [$legacyEvent];
$legacyLimited = false;
$legacyInteractions = dashboardBuildInteractions($legacyDatabase, [], $legacyLimited);
dashboardTypeChangesAssert(
    ($legacyInteractions[0]['changes'][0]['proposed'] ?? null) === 2
        && ($legacyInteractions[0]['changes'][0]['current'] ?? null) === 24
        && ($legacyInteractions[0]['changes'][0]['type_event_state'] ?? null) === 'unavailable'
        && ($legacyInteractions[0]['changes'][0]['current_type'] ?? null) === '<bespoke>',
    'Legacy affinity history must preserve its values, report event type as unavailable, and read current custom type separately.'
);

$typeOnlyHtmlModel = [
    'version' => '0.1.6',
    'generated_at' => '2026-09-27T16:00:00Z',
    'notices' => [],
    'source' => ['logs' => 'available', 'database' => 'available', 'limited' => false],
    'interactions' => [$logInteraction],
    'records' => [$parsed],
];
ob_start();
renderDashboard($typeOnlyHtmlModel, ['tab' => 'interactions']);
$typeOnlyHtml = ob_get_clean();
dashboardTypeChangesAssert(
    is_string($typeOnlyHtml)
        && str_contains($typeOnlyHtml, 'Applied type transition')
        && str_contains($typeOnlyHtml, 'neutral')
        && str_contains($typeOnlyHtml, 'ally')
        && str_contains($typeOnlyHtml, 'Protected type preserved')
        && str_contains($typeOnlyHtml, 'romantic')
        && str_contains($typeOnlyHtml, 'jealous')
        && str_contains($typeOnlyHtml, 'Retained type')
        && preg_match('/<div><dt>Decision<\/dt><dd>Same type<\/dd><\/div>\s*<div><dt>Retained type<\/dt><dd>neutral<\/dd><\/div>/s', $typeOnlyHtml) === 1
        && str_contains($typeOnlyHtml, '&lt;bespoke&gt;')
        && !str_contains($typeOnlyHtml, '<bespoke>'),
    'The interaction view must separate applied transitions, protected proposals, retained types, and escaped current types.'
);

$legacyHtmlModel = $typeOnlyHtmlModel;
$legacyHtmlModel['interactions'] = $legacyInteractions;
ob_start();
renderDashboard($legacyHtmlModel, ['tab' => 'interactions']);
$legacyHtml = ob_get_clean();
$legacyRows = [];
preg_match_all('/<dl class="change-row">([\s\S]*?)<\/dl>/', (string)$legacyHtml, $legacyRows);
$legacyRowMarkup = $legacyRows[1][0] ?? '';
$legacyRowCells = [];
preg_match_all('/<div(?:\s[^>]*)?>/', $legacyRowMarkup, $legacyRowCells);
$legacyTransitionCell = [];
$legacyCurrentCell = [];
preg_match('/<div class="change-transition">([\s\S]*?)<\/div>/', $legacyRowMarkup, $legacyTransitionCell);
preg_match('/<div>\s*<dt>Current<\/dt>([\s\S]*?)<\/div>/', $legacyRowMarkup, $legacyCurrentCell);
dashboardTypeChangesAssert(
    is_string($legacyHtml)
        && str_contains($legacyHtml, 'Type at event')
        && str_contains($legacyHtml, 'Unavailable')
        && count($legacyRowCells[0] ?? []) === 5
        && str_contains($legacyTransitionCell[1] ?? '', '<dt>Type at event</dt>')
        && str_contains($legacyCurrentCell[1] ?? '', '<dt>Current type</dt>')
        && str_contains($legacyHtml, '&lt;bespoke&gt;'),
    'Legacy event type absence must be explicit, current type must share the current cell, and affinity rows must retain five grid cells.'
);

$unconfirmedHtmlModel = $typeOnlyHtmlModel;
$unconfirmedHtmlModel['interactions'] = [$unconfirmedInteraction];
ob_start();
renderDashboard($unconfirmedHtmlModel, ['tab' => 'interactions']);
$unconfirmedHtml = ob_get_clean();
dashboardTypeChangesAssert(
    is_string($unconfirmedHtml)
        && str_contains($unconfirmedHtml, 'Unconfirmed')
        && str_contains($unconfirmedHtml, 'Proposed type change')
        && !str_contains($unconfirmedHtml, 'Applied type transition'),
    'Unconfirmed UI must display the proposal without claiming an applied type transition.'
);

$unsafeDiagnosticModel = $typeOnlyHtmlModel;
$unsafeDiagnosticModel['records'] = [$unsafeParsed];
ob_start();
renderDashboard($unsafeDiagnosticModel, ['tab' => 'diagnostics']);
$unsafeDiagnosticHtml = ob_get_clean();
dashboardTypeChangesAssert(
    is_string($unsafeDiagnosticHtml)
        && !str_contains($unsafeDiagnosticHtml, 'Aela told me a secret')
        && !str_contains($unsafeDiagnosticHtml, 'credential')
        && !str_contains($unsafeDiagnosticHtml, 'unexpected'),
    'The diagnostics renderer must show only parser-approved type fields and exclude unsafe payloads.'
);

echo "dashboard type-change checks passed\n";
