<?php
declare(strict_types=1);

use ChimMindPoisoning\RequestLog;
use function ChimMindPoisoning\dashboardBuildInteractions;
use function ChimMindPoisoning\dashboardCurrent;
use function ChimMindPoisoning\dashboardFilters;
use function ChimMindPoisoning\dashboardIndexRecords;
use function ChimMindPoisoning\dashboardLoad;
use function ChimMindPoisoning\dashboardLogInteraction;
use function ChimMindPoisoning\dashboardLogInteractions;
use function ChimMindPoisoning\dashboardNormalizedOutcome;
use function ChimMindPoisoning\dashboardParseLogLine;
use function ChimMindPoisoning\dashboardRecordMatches;
use function ChimMindPoisoning\renderDashboard;

require_once __DIR__ . '/fixtures/relationship_manager.cf5030f15781637498be86debe26fcf102f5690d.php';
require_once __DIR__ . '/../server/dashboard_data.php';
require_once __DIR__ . '/../server/dashboard_view.php';

function dashboardDataAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dashboardDataThrows(callable $callable, string $message): void
{
    try {
        $callable();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

dashboardDataThrows(static fn() => dashboardFilters(['tab' => 'other']), 'Unknown tab was accepted.');
dashboardDataThrows(static fn() => dashboardFilters(['q' => str_repeat('é', 121)]), 'Overlong UTF-8 query was accepted.');
dashboardDataThrows(static fn() => dashboardFilters(['q' => "\xFF"]), 'Invalid UTF-8 query was accepted.');
dashboardDataAssert(
    dashboardFilters(['tab' => 'diagnostics', 'q' => 'Aela']) === ['tab' => 'diagnostics', 'q' => 'Aela', 'outcome' => '', 'level' => ''],
    'Valid filters were not normalized.'
);

$writes = [];
$requestLog = new RequestLog(static function (string $json, string $level) use (&$writes): void {
    $writes[] = [$json, $level];
}, false);
$requestLog->context([
    'event_id' => 42,
    'utterance_id' => 'utt_abcdefgh',
    'playthrough_id' => 17,
    'speaker_id' => 8,
    'listener_id' => 7,
]);
$requestLog->finish('committed', 'committed', [
    'stage' => 'request',
    'outcome' => 'committed',
    'commit_state' => 'confirmed',
    'committed' => true,
    'changed_count' => 0,
    'model_reason' => 'private rationale',
]);
dashboardDataAssert(count($writes) === 1, 'RequestLog fixture did not emit one terminal record.');
[$wireJson, $wireLevel] = $writes[0];
$wireRecord = json_decode($wireJson, false, 32, JSON_THROW_ON_ERROR);
$wireRecord->raw_speech = 'must not escape';
$wireRecord->unknown_provider_field = ['secret' => 'must not escape'];
$wireLine = '[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($wireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$parsed = dashboardParseLogLine($wireLine);
dashboardDataAssert(is_array($parsed) && $parsed['event'] === 'request_finished', 'Native Logger envelope rejected a RequestLog record.');
dashboardDataAssert(
    !array_key_exists('speaker_kind', $parsed) && ($parsed['speaker_id'] ?? null) === '8',
    'Legacy NPC records without speaker_kind must retain their existing ID-based attribution.'
);
foreach (['raw_speech', 'unknown_provider_field', 'model_reason'] as $forbiddenField) {
    dashboardDataAssert(!array_key_exists($forbiddenField, $parsed), 'Private or unknown log field escaped sanitization: ' . $forbiddenField);
}
dashboardDataAssert(dashboardNormalizedOutcome($parsed) === 'zero-change', 'Diagnostics did not normalize confirmed zero changes.');
dashboardDataAssert(
    dashboardRecordMatches($parsed, ['tab' => 'diagnostics', 'q' => '', 'outcome' => 'zero-change', 'level' => ''])
        && !dashboardRecordMatches($parsed, ['tab' => 'diagnostics', 'q' => '', 'outcome' => 'unconfirmed', 'level' => '']),
    'Diagnostics outcome filtering did not match normalized zero-change state.'
);
$exposureWireRecord = clone $wireRecord;
$exposureWireRecord->speaker_kind = 'npc';
$exposureWireRecord->listener_role = 'overheard';
$exposureWireRecord->addressed_listener_id = 9;
$exposureWireRecord->batch_id = $wireRecord->request_id;
$exposureParsed = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($exposureWireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert(
    is_array($exposureParsed)
        && ($exposureParsed['listener_role'] ?? null) === 'overheard'
        && ($exposureParsed['addressed_listener_id'] ?? null) === '9'
        && ($exposureParsed['batch_id'] ?? null) === $wireRecord->request_id,
    'A well-formed per-listener exposure record should retain its safe role and batch link.'
);
$forgedExposure = clone $exposureWireRecord;
$forgedExposure->listener_role = 'addressed';
$forgedParsed = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($forgedExposure, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert(
    is_array($forgedParsed) && !isset($forgedParsed['listener_role'], $forgedParsed['addressed_listener_id'], $forgedParsed['batch_id']),
    'A mismatched addressed-listener identity must not survive dashboard log sanitization.'
);
$wireRecord->commit_state = 'unconfirmed';
$unconfirmed = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($wireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert(is_array($unconfirmed) && dashboardNormalizedOutcome($unconfirmed) === 'unconfirmed', 'Unconfirmed commit state was not normalized.');

$sharedWireRecord = clone $wireRecord;
$sharedWireRecord->playthrough_id = 'unprofiled';
$sharedParsed = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($sharedWireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert(
    is_array($sharedParsed) && ($sharedParsed['playthrough_id'] ?? null) === 'unprofiled',
    'The exact shared-server scope was not retained from a plugin log record.'
);
$sharedWireRecord->event_id = 'unprofiled';
$invalidSharedIds = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($sharedWireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert(
    is_array($invalidSharedIds)
        && ($invalidSharedIds['playthrough_id'] ?? null) === 'unprofiled'
        && !isset($invalidSharedIds['event_id']),
    'The shared scope exception weakened numeric event-ID validation.'
);
$sharedWireRecord->playthrough_id = 'unprofiled:1';
$forgedSharedScope = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($sharedWireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert(is_array($forgedSharedScope) && !isset($forgedSharedScope['playthrough_id']), 'A forged shared scope was accepted.');

$playerWireRecord = clone $wireRecord;
$playerWireRecord->utterance_id = 'input_42';
$playerWireRecord->speaker_kind = 'player';
$playerWireRecord->speaker_name = 'Private Player Name';
unset($playerWireRecord->speaker_id);
$playerParsed = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($playerWireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert(
    is_array($playerParsed)
        && ($playerParsed['speaker_kind'] ?? null) === 'player'
        && ($playerParsed['utterance_id'] ?? null) === 'input_42'
        && !isset($playerParsed['speaker_id'], $playerParsed['speaker_name']),
    'Explicit player logs should retain the safe kind and input row ID without a speaker ID.'
);
$invalidPlayerWireRecord = clone $playerWireRecord;
$invalidPlayerWireRecord->speaker_kind = 'Player One';
$invalidPlayerParsed = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($invalidPlayerWireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert(
    is_array($invalidPlayerParsed) && !array_key_exists('speaker_kind', $invalidPlayerParsed) && !isset($invalidPlayerParsed['speaker_id']),
    'Invalid player markers must be discarded rather than inferred from a missing ID.'
);
$npcWireRecord = clone $wireRecord;
$npcWireRecord->speaker_kind = 'npc';
$npcParsed = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($npcWireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert(
    is_array($npcParsed) && ($npcParsed['speaker_kind'] ?? null) === 'npc' && ($npcParsed['speaker_id'] ?? null) === '8',
    'The explicit NPC source kind should be retained with its numeric ID.'
);
$reflectionWireRecord = clone $wireRecord;
$reflectionWireRecord->speaker_id = 11;
$reflectionWireRecord->speaker_kind = 'npc';
$reflectionWireRecord->source_kind = 'reflection';
$reflectionWireRecord->opinion_owner_id = 11;
$reflectionWireRecord->speech_hash = str_repeat('a', 64);
$reflectionWireRecord->reflection_basis = str_repeat('b', 64);
unset($reflectionWireRecord->listener_id);
$reflectionParsed = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($reflectionWireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert(
    is_array($reflectionParsed)
        && ($reflectionParsed['source_kind'] ?? null) === 'reflection'
        && ($reflectionParsed['opinion_owner_id'] ?? null) === '11'
        && !array_key_exists('listener_id', $reflectionParsed)
        && !array_key_exists('speech_hash', $reflectionParsed)
        && !array_key_exists('reflection_basis', $reflectionParsed),
    'Reflection owner/provenance should survive log sanitization without leaking private hashes or inventing a listener.'
);
$earlyReflectionWireRecord = clone $reflectionWireRecord;
$earlyReflectionWireRecord->listener_id = 7;
unset($earlyReflectionWireRecord->speaker_id, $earlyReflectionWireRecord->speaker_kind, $earlyReflectionWireRecord->opinion_owner_id);
$earlyReflectionParsed = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($earlyReflectionWireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert(
    is_array($earlyReflectionParsed)
        && ($earlyReflectionParsed['source_kind'] ?? null) === 'reflection'
        && !array_key_exists('opinion_owner_id', $earlyReflectionParsed)
        && !array_key_exists('speaker_id', $earlyReflectionParsed)
        && !array_key_exists('listener_id', $earlyReflectionParsed),
    'A preflight reflection record without identity should remain ownerless reflection data, never a listener-attributed row.'
);
$mismatchedReflectionWireRecord = clone $reflectionWireRecord;
$mismatchedReflectionWireRecord->opinion_owner_id = 12;
$mismatchedReflectionParsed = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($mismatchedReflectionWireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
dashboardDataAssert($mismatchedReflectionParsed === null, 'A reflection log with a different opinion owner and speaker degraded into another attribution.');
foreach (['input_0', 'input_01', 'input_9223372036854775808'] as $invalidInputId) {
    $invalidIdWireRecord = clone $playerWireRecord;
    $invalidIdWireRecord->utterance_id = $invalidInputId;
    $invalidIdParsed = dashboardParseLogLine('[2026-09-27 16:00:00] [' . $wireLevel . '] ' . json_encode($invalidIdWireRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    dashboardDataAssert(is_array($invalidIdParsed) && !isset($invalidIdParsed['utterance_id']), 'Invalid CHIM input ID escaped log parsing.');
}

$player = ['id' => '7', 'extended_data' => (object)['relationships' => new stdClass()]];
$missing = dashboardCurrent($player, 'npc:9', 'Subject', '', ['Subject' => 1]);
$player['extended_data']->relationships->Subject = (object)['aff' => 'not-a-number'];
$malformed = dashboardCurrent($player, 'npc:9', 'Subject', '', ['Subject' => 1]);
dashboardDataAssert($missing === ['value' => null, 'state' => 'unset_default_zero', 'type' => null, 'type_state' => 'unset'], 'Missing edge was not kept distinct from observed zero or unset type.');
dashboardDataAssert($malformed === ['value' => null, 'state' => 'invalid', 'type' => null, 'type_state' => 'unset'], 'Malformed affinity was coerced to a numeric value or unset type.');
$player['extended_data']->relationships = null;
dashboardDataAssert(
    dashboardCurrent($player, 'npc:9', 'Subject', '', ['Subject' => 1]) === ['value' => null, 'state' => 'invalid', 'type' => null, 'type_state' => 'invalid'],
    'Unavailable oversized relationship data was treated as an empty object.'
);
$player['extended_data']->relationships = (object)[
    'Player' => (object)['aff' => 50, 'type' => 'romantic'],
    'Dragonborn' => (object)['aff' => 1, 'type' => 'neutral'],
];
dashboardDataAssert(
    dashboardCurrent($player, 'player', 'Player', 'Dragonborn', []) === ['value' => 50, 'state' => 'set', 'type' => 'romantic', 'type_state' => 'set'],
    'The dashboard must read the core-selected canonical Player edge from stored aliases.'
);
$player['extended_data']->relationships->Dragonborn = [1];
dashboardDataAssert(
    dashboardCurrent($player, 'player', 'Player', 'Dragonborn', []) === ['value' => null, 'state' => 'invalid', 'type' => null, 'type_state' => 'invalid'],
    'The dashboard must reject malformed array-shaped persisted Player edges.'
);
$player['extended_data']->relationships = [];
dashboardDataAssert(
    dashboardCurrent($player, 'player', 'Player', 'Dragonborn', []) === ['value' => null, 'state' => 'unset_default_zero', 'type' => null, 'type_state' => 'unset'],
    'An empty legacy array map should behave as an unset Player edge in the dashboard.'
);

$emptyServer = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mp-dashboard-data-' . bin2hex(random_bytes(6));
dashboardDataAssert(mkdir($emptyServer), 'Could not create an isolated empty server fixture.');
try {
    $emptyModel = dashboardLoad($emptyServer, []);
} finally {
    rmdir($emptyServer);
}
dashboardDataAssert(
    $emptyModel['source'] === ['logs' => 'unavailable', 'database' => 'unavailable', 'limited' => false]
        && in_array('CHIM logging is best-effort; suppressed or fallback log writes may be missing.', $emptyModel['notices'], true),
    'Unavailable fixture sources were not reported safely.'
);

$active = '17';
$event = [
    'event_id' => 42,
    'utterance_id' => 'utt_abcdefgh',
    'judgments' => [['subject' => 'npc:9', 'delta' => 2, 'reason' => 'friendlier', 'evidence' => 'shared work']],
];
$database = [
    'active_playthrough' => $active,
    'player_name' => 'Player One',
    'identities' => ['7' => 'Listener', '8' => 'Speaker', '9' => 'Subject', '22' => 'Addressee'],
    'rows' => [[
        'id' => '7',
        'extended_data' => (object)['relationships' => (object)['Subject' => (object)['aff' => 2]]],
        'plugin_extended_data' => (object)['mind_poisoning' => (object)[
            'playthrough_id' => $active,
            'floor_event_id' => 0,
            'events' => [$event],
        ]],
    ]],
];
$baseRecord = [
    'timestamp' => '2026-09-27T16:00:00Z',
    'event_id' => '42',
    'utterance_id' => 'utt_abcdefgh',
    'playthrough_id' => $active,
    'speaker_id' => '8',
    'listener_id' => '7',
    'listener_role' => 'overheard',
    'addressed_listener_id' => '22',
    'batch_id' => 'abcdef0123456789abcdef01',
];
$records = [
    $baseRecord + [
        'request_id' => 'req_confirmed',
        'event' => 'persistence_finished',
        'persistence_outcome' => 'committed',
        'persistence_reason' => 'committed',
        'commit_state' => 'confirmed',
        'committed' => true,
        'changed_count' => 1,
        'persistence_ms' => 8.5,
        'changes' => [['subject' => 'npc:9', 'delta' => 2, 'before' => 0, 'after' => 2]],
    ],
    $baseRecord + [
        'request_id' => 'req_confirmed',
        'event' => 'request_finished',
        'outcome' => 'committed',
        'reason' => 'committed',
        'model_ms' => 120.0,
    ],
    array_merge($baseRecord, [
        'request_id' => 'req_old_profile',
        'event' => 'persistence_finished',
        'playthrough_id' => '18',
        'commit_state' => 'confirmed',
        'committed' => true,
        'changed_count' => 1,
        'changes' => [['subject' => 'npc:9', 'delta' => 2, 'before' => 50, 'after' => 52]],
    ]),
];
$limited = false;
$interactions = dashboardBuildInteractions($database, $records, $limited);
dashboardDataAssert(count($interactions) === 1 && !$limited, 'Active ledger did not produce exactly one bounded interaction.');
dashboardDataAssert(
    $interactions[0]['outcome'] === 'committed'
        && $interactions[0]['speaker'] === 'Speaker'
        && $interactions[0]['listener'] === 'Listener'
        && $interactions[0]['listener_role'] === 'overheard'
        && $interactions[0]['addressed_listener'] === 'Addressee'
        && $interactions[0]['batch_id'] === 'abcdef0123456789abcdef01'
        && $interactions[0]['changes'][0]['before'] === 0
        && $interactions[0]['changes'][0]['after'] === 2
        && $interactions[0]['changes'][0]['applied'] === 2,
    'Legacy NPC speaker and confirmed persistence values did not correlate to the active playthrough.'
);

$reflectionEvent = [
    'event_id' => 44,
    'utterance_id' => 'utt_reflect01',
    'source_kind' => 'reflection',
    'judgments' => [['subject' => 'npc:9', 'delta' => 2, 'reason' => 'changed view', 'evidence' => 'earlier shared event']],
];
$reflectionDatabase = [
    'active_playthrough' => $active,
    'player_name' => 'Player One',
    'identities' => ['9' => 'Subject', '11' => 'Reflector'],
    'rows' => [[
        'id' => '11',
        'extended_data' => (object)['relationships' => (object)['Subject' => (object)['aff' => 6]]],
        'plugin_extended_data' => (object)['mind_poisoning' => (object)[
            'playthrough_id' => $active,
            'floor_event_id' => 0,
            'events' => [$reflectionEvent],
            'reflection_state' => (object)['basis' => str_repeat('a', 64), 'subjects' => ['npc:9']],
        ]],
    ]],
];
$reflectionRecordBase = [
    'timestamp' => '2026-09-27T16:01:00Z',
    'event_id' => '44',
    'utterance_id' => 'utt_reflect01',
    'playthrough_id' => $active,
    'speaker_id' => '11',
    'speaker_kind' => 'npc',
    'source_kind' => 'reflection',
    'opinion_owner_id' => '11',
];
$reflectionRecords = [
    $reflectionRecordBase + [
        'request_id' => 'req_reflection',
        'event' => 'persistence_finished',
        'persistence_outcome' => 'committed',
        'persistence_reason' => 'committed',
        'commit_state' => 'confirmed',
        'committed' => true,
        'changed_count' => 1,
        'persistence_ms' => 4.0,
        'changes' => [['subject' => 'npc:9', 'delta' => 2, 'before' => 4, 'after' => 6]],
    ],
    $reflectionRecordBase + [
        'request_id' => 'req_reflection',
        'event' => 'request_finished',
        'outcome' => 'committed',
        'reason' => 'committed',
        'model_ms' => 80.0,
    ],
];
$reflectionLimited = false;
$reflectionInteractions = dashboardBuildInteractions($reflectionDatabase, $reflectionRecords, $reflectionLimited);
dashboardDataAssert(
    count($reflectionInteractions) === 1
        && $reflectionInteractions[0]['source_kind'] === 'reflection'
        && $reflectionInteractions[0]['speaker'] === 'Reflector'
        && $reflectionInteractions[0]['opinion_owner'] === 'Reflector'
        && $reflectionInteractions[0]['listener'] === null
        && $reflectionInteractions[0]['changes'][0]['before'] === 4
        && $reflectionInteractions[0]['changes'][0]['after'] === 6
        && $reflectionInteractions[0]['changes'][0]['current'] === 6,
    'Reflection ledger and persistence logs did not join under the actual opinion owner.'
);
$logOnlyReflectionRecords = [
    $reflectionRecordBase + [
        'request_id' => 'req_reflection_log_only',
        'event' => 'persistence_finished',
        'commit_state' => 'confirmed',
        'committed' => true,
        'changed_count' => 1,
        'changes' => [['subject' => 'npc:9', 'delta' => 2, 'before' => 4, 'after' => 6]],
    ],
    $reflectionRecordBase + [
        'request_id' => 'req_reflection_log_only',
        'event' => 'request_finished',
        'outcome' => 'committed',
        'reason' => 'committed',
    ],
];
$logOnlyLimited = false;
$logOnlyReflection = dashboardLogInteractions($logOnlyReflectionRecords, null, false, $logOnlyLimited);
dashboardDataAssert(
    count($logOnlyReflection) === 1
        && $logOnlyReflection[0]['attribution'] === 'unverified'
        && $logOnlyReflection[0]['source_kind'] === 'reflection'
        && $logOnlyReflection[0]['speaker'] === 'NPC #11'
        && $logOnlyReflection[0]['opinion_owner'] === 'NPC #11'
        && $logOnlyReflection[0]['listener'] === null
        && $logOnlyReflection[0]['changes'][0]['before'] === 4,
    'Log-only reflection attribution should identify the owner without manufacturing a listener.'
);

$sharedDatabase = $database;
$sharedDatabase['rows'][0]['plugin_extended_data'] = clone $database['rows'][0]['plugin_extended_data'];
$sharedDatabase['rows'][0]['plugin_extended_data']->mind_poisoning = clone $database['rows'][0]['plugin_extended_data']->mind_poisoning;
$sharedDatabase['active_playthrough'] = 'unprofiled';
$sharedDatabase['rows'][0]['plugin_extended_data']->mind_poisoning->playthrough_id = 'unprofiled';
$sharedRecords = array_map(static function (array $record): array {
    if (($record['playthrough_id'] ?? null) === '17') {
        $record['playthrough_id'] = 'unprofiled';
    }
    return $record;
}, $records);
$sharedLimited = false;
$sharedInteractions = dashboardBuildInteractions($sharedDatabase, $sharedRecords, $sharedLimited);
dashboardDataAssert(
    count($sharedInteractions) === 1
        && $sharedInteractions[0]['playthrough_id'] === 'unprofiled'
        && $sharedInteractions[0]['attribution'] === 'shared'
        && $sharedInteractions[0]['changes'][0]['before'] === 0
        && $sharedInteractions[0]['changes'][0]['after'] === 2,
    'Shared-server ledger and log records did not correlate within their exact scope.'
);
$profiledDatabase = $sharedDatabase;
$profiledDatabase['rows'][0]['plugin_extended_data'] = clone $sharedDatabase['rows'][0]['plugin_extended_data'];
$profiledDatabase['rows'][0]['plugin_extended_data']->mind_poisoning = clone $sharedDatabase['rows'][0]['plugin_extended_data']->mind_poisoning;
$profiledDatabase['active_playthrough'] = '17';
$profiledDatabase['rows'][0]['plugin_extended_data']->mind_poisoning->playthrough_id = '17';
$profiledLimited = false;
$profiledInteractions = dashboardBuildInteractions($profiledDatabase, $sharedRecords, $profiledLimited);
dashboardDataAssert(
    count($profiledInteractions) === 1
        && $profiledInteractions[0]['playthrough_id'] === '17'
        && $profiledInteractions[0]['attribution'] === 'active'
        && $profiledInteractions[0]['changes'][0]['before'] === null,
    'Unprofiled history or logs leaked into an explicit profile scope.'
);

$playerDatabase = $database;
$playerDatabase['rows'][0]['plugin_extended_data']->mind_poisoning->events = [[
    'event_id' => 43,
    'utterance_id' => 'input_123',
    'judgments' => [['subject' => 'npc:9', 'delta' => 2, 'reason' => 'friendlier', 'evidence' => 'heard from Anora']],
]];
$playerBaseRecord = [
    'request_id' => 'req_player_input',
    'event_id' => '43',
    'utterance_id' => 'input_123',
    'playthrough_id' => $active,
    'speaker_kind' => 'player',
    'listener_id' => '7',
    'timestamp' => '2026-09-27T16:00:00Z',
];
$playerRecords = [
    $playerBaseRecord + [
        'event' => 'persistence_finished',
        'commit_state' => 'confirmed',
        'committed' => true,
        'changed_count' => 1,
        'persistence_outcome' => 'committed',
        'changes' => [['subject' => 'npc:9', 'delta' => 2, 'before' => 0, 'after' => 2]],
    ],
    $playerBaseRecord + ['event' => 'request_finished', 'outcome' => 'committed', 'reason' => 'committed'],
];
$limited = false;
$playerInteractions = dashboardBuildInteractions($playerDatabase, $playerRecords, $limited);
dashboardDataAssert(
    count($playerInteractions) === 1
        && $playerInteractions[0]['speaker'] === 'Player'
        && $playerInteractions[0]['utterance_id'] === 'input_123',
    'An input-row player record should correlate with its ledger event and display Player without a speaker ID.'
);
$legacyInteraction = dashboardLogInteraction($parsed, $active, 'active', ['8' => 'Speaker'], [], [], true, 'Player One');
$playerLogInteraction = dashboardLogInteraction($playerParsed, $active, 'active', [], [], [], true, 'Player One');
$unknownSpeakerInteraction = dashboardLogInteraction($invalidPlayerParsed, $active, 'active', [], [], [], true, 'Player One');
dashboardDataAssert(
    $legacyInteraction['speaker'] === 'Speaker'
        && $playerLogInteraction['speaker'] === 'Player'
        && $unknownSpeakerInteraction['speaker'] === null,
    'Only an explicit player marker may label a speaker as Player; legacy ID and unknown cases must remain distinct.'
);

$dashboardModel = [
    'version' => '0.1.6',
    'generated_at' => '2026-09-27T16:00:00Z',
    'notices' => [],
    'source' => ['logs' => 'available', 'database' => 'unavailable', 'limited' => false],
    'interactions' => $playerInteractions,
    'records' => [$playerParsed],
];
ob_start();
renderDashboard($dashboardModel, ['tab' => 'diagnostics']);
$diagnosticHtml = ob_get_clean();
dashboardDataAssert(
    is_string($diagnosticHtml)
        && str_contains($diagnosticHtml, '&quot;speaker_kind&quot;: &quot;player&quot;')
        && !str_contains($diagnosticHtml, 'speaker_name'),
    'Diagnostics should expose the sanitized speaker kind without adding name fields.'
);
$reflectionDashboardModel = $dashboardModel;
$reflectionDashboardModel['records'] = [$reflectionParsed];
ob_start();
renderDashboard($reflectionDashboardModel, ['tab' => 'diagnostics']);
$reflectionDiagnosticHtml = ob_get_clean();
dashboardDataAssert(
    is_string($reflectionDiagnosticHtml)
        && str_contains($reflectionDiagnosticHtml, '&quot;source_kind&quot;: &quot;reflection&quot;')
        && str_contains($reflectionDiagnosticHtml, '&quot;opinion_owner_id&quot;: &quot;11&quot;')
        && !str_contains($reflectionDiagnosticHtml, 'speech_hash')
        && !str_contains($reflectionDiagnosticHtml, 'reflection_basis'),
    'Diagnostic rendering should retain safe reflection attribution and suppress private correlation hashes.'
);
ob_start();
renderDashboard($dashboardModel, ['tab' => 'interactions']);
$interactionHtml = ob_get_clean();
dashboardDataAssert(
    is_string($interactionHtml)
        && str_contains($interactionHtml, 'Input event <code>input_123</code>')
        && str_contains($interactionHtml, 'Player<span class="exchange-arrow"')
        && str_contains($interactionHtml, 'Listener'),
    'Input row correlation IDs or pair speaker/listener presentation changed in the dashboard.'
);
$exposureViewModel = $dashboardModel;
$exposureViewModel['interactions'] = $interactions;
ob_start();
renderDashboard($exposureViewModel, ['tab' => 'interactions']);
$exposureHtml = ob_get_clean();
dashboardDataAssert(
    is_string($exposureHtml)
        && str_contains($exposureHtml, 'Roster-listed overhearer evaluation')
        && str_contains($exposureHtml, 'addressed to Addressee; playback is not verified'),
    'The interaction view should label the listener role without implying proven playback.'
);
$sharedViewModel = $dashboardModel;
$sharedViewModel['scope_label'] = 'Shared server';
$sharedViewModel['scope_notice'] = 'This history is not isolated to a unique Skyrim save.';
$sharedViewModel['interactions'] = $sharedInteractions;
ob_start();
renderDashboard($sharedViewModel, ['tab' => 'interactions']);
$sharedHtml = ob_get_clean();
dashboardDataAssert(
    is_string($sharedHtml)
        && str_contains($sharedHtml, 'Scope: <strong>Shared server</strong>')
        && str_contains($sharedHtml, 'This history is not isolated to a unique Skyrim save.')
        && str_contains($sharedHtml, 'Scope <code>Shared server</code>')
        && str_contains($sharedHtml, 'Shared server history')
        && !str_contains($sharedHtml, 'Playthrough <code>unprofiled</code>'),
    'The dashboard described the no-profile context as a unique playthrough instead of a shared server scope.'
);
$reflectionViewModel = $dashboardModel;
$reflectionViewModel['interactions'] = $reflectionInteractions;
ob_start();
renderDashboard($reflectionViewModel, ['tab' => 'interactions']);
$reflectionHtml = ob_get_clean();
dashboardDataAssert(
    is_string($reflectionHtml)
        && str_contains($reflectionHtml, 'Reflector')
        && str_contains($reflectionHtml, 'Solo reflection')
        && !str_contains($reflectionHtml, 'Reflector<span class="exchange-arrow"'),
    'Reflection should name its actor and mode without rendering an NPC-to-self conversation.'
);
$unknownReflectionModel = $reflectionViewModel;
$unknownReflectionModel['interactions'] = [dashboardLogInteraction($earlyReflectionParsed, $active, 'unverified', [], [], [], false, '')];
ob_start();
renderDashboard($unknownReflectionModel, ['tab' => 'interactions']);
$unknownReflectionHtml = ob_get_clean();
dashboardDataAssert(
    is_string($unknownReflectionHtml)
        && str_contains($unknownReflectionHtml, 'Unknown NPC · Solo reflection')
        && !str_contains($unknownReflectionHtml, 'exchange-arrow'),
    'Ownerless preflight diagnostics should remain a solo reflection with an explicit unknown actor label.'
);
$databaseWithoutSubjectName = $database;
unset($databaseWithoutSubjectName['identities']['9']);
$limited = false;
$unknownSubjectName = dashboardBuildInteractions($databaseWithoutSubjectName, [], $limited);
dashboardDataAssert(
    $unknownSubjectName[0]['changes'][0]['current'] === null
        && $unknownSubjectName[0]['changes'][0]['current_state'] === 'unavailable',
    'A fallback NPC label was treated as a real relationship key.'
);

$otherRequestRecords = [
    $baseRecord + [
        'request_id' => 'req_other',
        'event' => 'persistence_finished',
        'commit_state' => 'unconfirmed',
        'committed' => false,
        'changed_count' => 0,
    ],
];
$skippedRecord = $baseRecord + ['request_id' => 'req_skip', 'event' => 'request_finished', 'outcome' => 'skipped', 'reason' => 'no_subject'];
$crossRequest = dashboardLogInteraction(
    $skippedRecord,
    $active,
    'active',
    [],
    [],
    dashboardIndexRecords(array_merge($otherRequestRecords, [$skippedRecord])),
    true,
    ''
);
dashboardDataAssert($crossRequest['outcome'] === 'skipped' && $crossRequest['changes'] === [], 'Another request’s uncertain commit contaminated this row.');

$logDerivedRecords = [
    $baseRecord + [
        'request_id' => 'req_cleanup',
        'event' => 'persistence_finished',
        'persistence_outcome' => 'failed',
        'persistence_reason' => 'release-failed',
        'commit_state' => 'confirmed',
        'committed' => true,
        'changed_count' => 1,
        'changes' => [['subject' => 'npc:9', 'delta' => 2, 'before' => 0, 'after' => 2]],
    ],
    $baseRecord + [
        'request_id' => 'req_cleanup',
        'event' => 'request_finished',
        'outcome' => 'failed',
        'reason' => 'persistence_failed',
    ],
];
$logIndex = dashboardIndexRecords($logDerivedRecords);
$cleanupRecord = $logDerivedRecords[1];
$listenerRows = ['7' => $database['rows'][0]];
$missingLogIdentity = dashboardLogInteraction(
    $cleanupRecord,
    $active,
    'active',
    ['7' => 'Listener', '8' => 'Speaker'],
    $listenerRows,
    $logIndex,
    true,
    'Player One'
);
$duplicateLogIdentity = dashboardLogInteraction(
    $cleanupRecord,
    $active,
    'active',
    ['7' => 'Listener', '8' => 'Speaker', '9' => 'Subject', '10' => 'Subject'],
    $listenerRows,
    $logIndex,
    true,
    'Player One'
);
dashboardDataAssert(
    $missingLogIdentity['changes'][0]['current'] === null
        && $missingLogIdentity['changes'][0]['current_state'] === 'unavailable',
    'Log-derived fallback labels were treated as real subject identities.'
);
dashboardDataAssert(
    $duplicateLogIdentity['changes'][0]['current'] === null
        && $duplicateLogIdentity['changes'][0]['current_state'] === 'ambiguous',
    'Log-derived duplicate subject names were reported as a known current affinity.'
);

$noProfileRecord = $baseRecord + ['request_id' => 'req_no_profile', 'event' => 'request_finished', 'outcome' => 'skipped'];
$limited = false;
$noProfile = dashboardBuildInteractions(['active_playthrough' => null, 'rows' => []], [$noProfileRecord], $limited);
dashboardDataAssert(count($noProfile) === 1 && $noProfile[0]['attribution'] === 'unverified', 'Missing active profile was reported as verified attribution.');

$manyRows = [];
foreach (['7', '8'] as $listenerId) {
    $events = [];
    for ($eventId = 1; $eventId <= 128; $eventId++) {
        $events[] = [
            'event_id' => $eventId,
            'utterance_id' => 'utt_' . str_pad((string)$eventId, 8, '0', STR_PAD_LEFT),
            'judgments' => [],
        ];
    }
    $manyRows[] = [
        'id' => $listenerId,
        'extended_data' => (object)['relationships' => new stdClass()],
        'plugin_extended_data' => (object)['mind_poisoning' => (object)[
            'playthrough_id' => $active,
            'floor_event_id' => 0,
            'events' => $events,
        ]],
    ];
}
$limited = false;
$bounded = dashboardBuildInteractions(['active_playthrough' => $active, 'rows' => $manyRows], [], $limited);
dashboardDataAssert(count($bounded) === 200 && $limited, 'Interaction truncation was not surfaced to the caller.');

echo "dashboard_data_test: ok\n";
