<?php
declare(strict_types=1);

define('CHIM_MIND_POISONING_TEST_FIXTURES_ONLY', true);
$GLOBALS['overhearing_test_enabled'] = false;
function chimGetGeneralSettingBool(string $id, bool $default = false): bool
{
    return $id === 'mind_poisoning_overhearing_enabled'
        ? ($GLOBALS['overhearing_test_enabled'] ?? $default)
        : $default;
}

require_once __DIR__ . '/runtime_test.php';

use ChimMindPoisoning\RequestLog;
use function ChimMindPoisoning\handlePlayerInput;
use function ChimMindPoisoning\handleSpeechAck;

function overhearingCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function overhearingAck(array $event): array
{
    return ['_speech', 0, $event['gamets'], json_encode([
        'speaker' => $event['speaker_name'],
        'listener' => $event['listener_name'],
        'speech' => $event['text'],
        'utterance_id' => $event['utterance_id'],
    ], JSON_THROW_ON_ERROR)];
}

function overhearingAddNpc(MemoryStoreDb $db, int $id, string $name): void
{
    $db->npcs[$id] = [
        'id' => $id,
        'npc_name' => $name,
        'gamets_last_updated' => 0.0,
        'extended_data' => (object)['relationships' => new stdClass()],
        'plugin_extended_data' => new stdClass(),
    ];
}

function overhearingBatchResponse(array $messages, array $deltas = [], array $typeChanges = []): string
{
    $payload = json_decode($messages[1]['content'] ?? '', true, 64, JSON_THROW_ON_ERROR);
    $recipients = $payload['untrusted_data']['recipients'] ?? null;
    if (!is_array($recipients) || !array_is_list($recipients)) {
        return json_encode(['judgments' => []], JSON_THROW_ON_ERROR);
    }
    $rows = [];
    foreach ($recipients as $recipient) {
        $listenerId = $recipient['listener_id'] ?? null;
        $candidates = $recipient['candidates'] ?? null;
        if (!is_int($listenerId) || !is_array($candidates)) {
            continue;
        }
        foreach (array_keys($candidates) as $subject) {
            $row = [
                'listener_id' => $listenerId,
                'subject' => $subject,
                'subject_mentioned' => true,
                'delta' => $deltas[$listenerId][$subject] ?? 1,
                'reason' => 'The statement may affect this listener.',
                'evidence' => str_starts_with($subject, 'npc:') ? 'Jarl Balgruuf' : 'Dragonborn',
            ];
            if (isset($typeChanges[$listenerId][$subject])) {
                $row['type_change'] = $typeChanges[$listenerId][$subject];
            }
            $rows[] = $row;
        }
    }
    return json_encode(['judgments' => $rows], JSON_THROW_ON_ERROR);
}

function overhearingNormalResponse(array $messages): string
{
    $payload = json_decode($messages[1]['content'] ?? '', true, 64, JSON_THROW_ON_ERROR);
    $candidates = $payload['untrusted_data']['candidates'] ?? [];
    $rows = [];
    foreach (array_keys(is_array($candidates) ? $candidates : []) as $subject) {
        $rows[] = [
            'subject' => $subject,
            'subject_mentioned' => true,
            'delta' => 1,
            'reason' => 'The statement may affect the addressed listener.',
            'evidence' => str_starts_with($subject, 'npc:') ? 'Jarl Balgruuf' : 'Dragonborn',
        ];
    }
    return json_encode(['judgments' => $rows], JSON_THROW_ON_ERROR);
}

function overhearingFinishedFor(array $records, int $listenerId): array
{
    return array_values(array_filter($records, static fn(array $record): bool =>
        ($record['event'] ?? null) === 'request_finished'
        && ($record['listener_id'] ?? null) === (string)$listenerId
    ));
}

function overhearingFixture(string $people = '|Aela|Lydia|Inigo|'): array
{
    [, , , $db] = baseFixture();
    $db->events[100]['people'] = $people;
    overhearingAddNpc($db, 44, 'Inigo');
    return [$db->events[100], $db];
}

function overhearingExecute(
    string $people,
    bool $enabled,
    callable $model,
    ?callable $prepare = null,
    ?callable $onBegin = null
): array {
    $GLOBALS['overhearing_test_enabled'] = $enabled;
    resetAckLoggingInteraction();
    [$event, $db] = overhearingFixture($people);
    if ($prepare !== null) {
        $prepare($db, $event);
    }
    $db->onBegin = $onBegin;
    $records = [];
    $calls = 0;
    $capturedMessages = null;
    $requestLog = new RequestLog(static function (string $json, string $level) use (&$records): void {
        $records[] = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    }, false);
    $status = handleSpeechAck(
        overhearingAck($event),
        $db,
        static function (array $messages) use (&$calls, &$capturedMessages, $model, $db): string {
            $calls++;
            $capturedMessages = $messages;
            return $model($messages, $db);
        },
        $requestLog
    );
    return compact('status', 'calls', 'capturedMessages', 'records', 'event', 'db');
}

function overhearingLedgerHas(MemoryStoreDb $db, int $listenerId): bool
{
    $ledger = $db->npcs[$listenerId]['plugin_extended_data']->mind_poisoning ?? null;
    return $ledger instanceof stdClass && is_array($ledger->events ?? null) && $ledger->events !== [];
}

function overhearingBatchProvider(array $messages, MemoryStoreDb $db): string
{
    return overhearingBatchResponse($messages);
}

$forkLogLines = [];
$forkObserverCalls = 0;
$forkObserverReentered = false;
$forkLog = null;
$forkLog = new RequestLog(static function (string $json, string $level) use (&$forkLogLines): void {
    $forkLogLines[] = [$json, $level];
}, false);
$forkLog->observe(static function (array $record, string $level) use (&$forkLog, &$forkObserverCalls, &$forkObserverReentered): void {
    $forkObserverCalls++;
    if (!$forkObserverReentered) {
        $forkObserverReentered = true;
        $forkLog?->fork()->event('observer_reentrant', 'info');
    }
});
$forkLog->fork(['listener_id' => 44, 'listener_role' => 'overheard'])->event('fork_probe', 'info');
overhearingCheck(
    $forkObserverCalls === 1 && count($forkLogLines) === 2,
    'Forked request observers should receive child events without recursive observer delivery.'
);

// Off preserves the one-recipient ACK call and does not ledger a bystander.
$off = overhearingExecute('|Aela|Lydia|Inigo|', false, 'overhearingNormalResponse');
overhearingCheck($off['status'] === 'committed' && $off['calls'] === 1, 'OFF should preserve one direct provider call.');
overhearingCheck(overhearingLedgerHas($off['db'], 22) && !overhearingLedgerHas($off['db'], 44), 'OFF must persist only the addressed listener.');

// One bounded provider result carries independent direct/overheard contexts and deltas.
$positive = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    static fn(array $messages): string => overhearingBatchResponse($messages, [22 => ['npc:33' => 2], 44 => ['npc:33' => 3]]),
    static function (MemoryStoreDb $db, array &$event): void {
        $event['text'] = 'I trust Jarl Balgruuf. Lydia told Inigo about the Dragonborn.';
        $db->events[100]['source_data'] = 'Aela: ' . $event['text'] . ' (Talking to Lydia)';
        $db->npcs[44]['extended_data']->relationships->Aela = (object)['aff' => -72, 'type' => 'neutral'];
    }
);
$positiveSummary = array_map(static fn(array $record): array => array_intersect_key($record, array_flip([
    'event', 'request_id', 'listener_id', 'addressed_listener_id', 'listener_role', 'outcome', 'reason', 'model_outcome', 'stage',
])), $positive['records']);
overhearingCheck(
    $positive['status'] === 'committed' && $positive['calls'] === 1,
    'Enabled NPC overhearing should use exactly one provider call; status=' . $positive['status']
        . ', calls=' . $positive['calls'] . ', terminals=' . json_encode($positiveSummary, JSON_UNESCAPED_SLASHES)
);
overhearingCheck(($positive['db']->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff ?? null) === 100, 'The addressed listener should receive its own capped judgment.');
overhearingCheck(($positive['db']->npcs[44]['extended_data']->relationships->{'Jarl Balgruuf'}->aff ?? null) === 3, 'The overhearer should receive its distinct judgment.');
overhearingCheck(overhearingLedgerHas($positive['db'], 22) && overhearingLedgerHas($positive['db'], 44), 'Both listeners should have independent ledger entries.');
$batchPayload = json_decode($positive['capturedMessages'][1]['content'], true, 64, JSON_THROW_ON_ERROR)['untrusted_data'];
$recipients = $batchPayload['recipients'] ?? [];
overhearingCheck(
    count($recipients) === 2
        && ($recipients[0]['listener_id'] ?? null) === 22
        && ($recipients[0]['listener_role'] ?? null) === 'addressed'
        && ($recipients[1]['listener_id'] ?? null) === 44
        && ($recipients[1]['listener_role'] ?? null) === 'overheard',
    'The batch should label its addressed and roster-listed listeners explicitly.'
);
overhearingCheck(
    !isset($recipients[0]['candidates']['npc:22']) && !isset($recipients[0]['candidates']['npc:11'])
        && !isset($recipients[1]['candidates']['npc:44']) && !isset($recipients[1]['candidates']['npc:11'])
        && ($recipients[0]['listener_prior_relation_to_speaker']['aff'] ?? null) !== ($recipients[1]['listener_prior_relation_to_speaker']['aff'] ?? null),
    'Each listener needs a separate context with speaker/listener self-relationships excluded.'
);

$typeIsolation = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    static fn(array $messages): string => overhearingBatchResponse(
        $messages,
        [22 => ['npc:33' => 0, 'player' => 0], 44 => ['npc:33' => 0, 'player' => 0]],
        [
            22 => ['npc:33' => ['from' => 'neutral', 'to' => 'suspicious']],
            44 => ['npc:33' => ['from' => 'romantic', 'to' => 'suspicious']],
        ]
    ),
    static function (MemoryStoreDb $db, array &$event): void {
        $event['text'] = 'I trust Jarl Balgruuf.';
        $db->events[100]['source_data'] = 'Aela: ' . $event['text'] . ' (Talking to Lydia)';
        $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 'neutral';
        $db->npcs[44]['extended_data']->relationships = (object)[
            'Jarl Balgruuf' => (object)['aff' => 7, 'type' => 'romantic'],
        ];
    }
);
overhearingCheck($typeIsolation['status'] === 'committed', 'Type-only proposals for addressed and overheard recipients should keep the batch committed.');
overhearingCheck(($typeIsolation['db']->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type ?? null) === 'suspicious', 'The addressed listener should apply its own eligible type transition.');
overhearingCheck(($typeIsolation['db']->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff ?? null) === 99, 'The addressed type-only transition should leave its affinity unchanged.');
overhearingCheck(($typeIsolation['db']->npcs[44]['extended_data']->relationships->{'Jarl Balgruuf'}->type ?? null) === 'romantic', 'The overhearer should preserve its protected current type.');
overhearingCheck(($typeIsolation['db']->npcs[44]['extended_data']->relationships->{'Jarl Balgruuf'}->aff ?? null) === 7, 'The overhearer type-only proposal should leave its affinity unchanged.');
$addressedTypeLedger = $typeIsolation['db']->npcs[22]['plugin_extended_data']->mind_poisoning->events[0] ?? null;
$overheardTypeLedger = $typeIsolation['db']->npcs[44]['plugin_extended_data']->mind_poisoning->events[0] ?? null;
overhearingCheck(($addressedTypeLedger->type_changes[0]->after ?? null) === 'suspicious', 'The addressed ledger should contain its confirmed transition.');
overhearingCheck(($overheardTypeLedger->type_decisions[0]->reason ?? null) === 'protected_type_preserved'
    && !property_exists($overheardTypeLedger, 'type_changes'), 'The overhearer ledger should record its own preservation decision without a false applied transition.');
$addressedTypeSummary = overhearingFinishedFor($typeIsolation['records'], 22);
$overheardTypeSummary = overhearingFinishedFor($typeIsolation['records'], 44);
overhearingCheck(($addressedTypeSummary[0]['changed_count'] ?? null) === 1
    && ($overheardTypeSummary[0]['changed_count'] ?? null) === 0, 'Each recipient should report its own relationship change count.');

$addressedFinished = overhearingFinishedFor($positive['records'], 22);
$overheardFinished = overhearingFinishedFor($positive['records'], 44);
overhearingCheck(
    count($addressedFinished) === 1 && count($overheardFinished) === 1
        && ($addressedFinished[0]['listener_role'] ?? null) === 'addressed'
        && ($overheardFinished[0]['listener_role'] ?? null) === 'overheard'
        && ($overheardFinished[0]['addressed_listener_id'] ?? null) === '22'
        && ($addressedFinished[0]['request_id'] ?? null) !== ($overheardFinished[0]['request_id'] ?? null)
        && ($addressedFinished[0]['batch_id'] ?? null) === ($overheardFinished[0]['batch_id'] ?? null),
    'Each listener should have a distinct truthful terminal correlation linked by the safe batch ID.'
);

$deliveryProgress = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    static function (array $messages, MemoryStoreDb $db): string {
        $db->events[100]['delivery_state'] = 'spoken';
        return overhearingBatchResponse($messages);
    },
    static function (MemoryStoreDb $db): void { $db->events[100]['delivery_state'] = 'emitted'; }
);
overhearingCheck($deliveryProgress['status'] === 'committed' && overhearingLedgerHas($deliveryProgress['db'], 44), 'A normal emitted-to-spoken delivery transition should not invalidate the witness snapshot.');

// Invalid roster content and over-cap eligible crowds fall back to a valid direct ACK.
$malformed = overhearingExecute("|Aela|Lydia|Bad\x01Name|", true, 'overhearingNormalResponse');
overhearingCheck($malformed['status'] === 'committed' && $malformed['calls'] === 1 && !overhearingLedgerHas($malformed['db'], 44), 'Malformed people must suppress extras without rejecting direct processing.');
overhearingCheck(
    count(array_filter($malformed['records'], static fn(array $record): bool => ($record['event'] ?? null) === 'overhearing_audience_skipped' && ($record['reason'] ?? null) === 'audience_people_invalid')) === 1,
    'Malformed people should produce a fixed safe skip diagnostic.'
);
$lookupFailure = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    'overhearingNormalResponse',
    static function (MemoryStoreDb $db): void { $db->throwNpcLookupId = 44; }
);
$lookupFailureDiagnostics = array_values(array_filter($lookupFailure['records'], static fn(array $record): bool =>
    ($record['event'] ?? null) === 'overhearing_audience_skipped'
        && ($record['reason'] ?? null) === 'audience_lookup_failed'
));
overhearingCheck(
    $lookupFailure['status'] === 'committed' && $lookupFailure['calls'] === 1
        && overhearingLedgerHas($lookupFailure['db'], 22) && !overhearingLedgerHas($lookupFailure['db'], 44)
        && count($lookupFailureDiagnostics) === 1 && ($lookupFailureDiagnostics[0]['level'] ?? null) === 'error'
        && !str_contains(json_encode($lookupFailure['records'], JSON_THROW_ON_ERROR), 'PRIVATE_SYNTHETIC_LOOKUP_DETAIL'),
    'A witness lookup exception should log a fixed error without details and leave direct ACK processing intact.'
);
$capPeople = '|Aela|Lydia|Inigo|Crowd One|Crowd Two|Crowd Three|Crowd Four|';
$overCap = overhearingExecute(
    $capPeople,
    true,
    'overhearingNormalResponse',
    static function (MemoryStoreDb $db): void {
        foreach ([45 => 'Crowd One', 46 => 'Crowd Two', 47 => 'Crowd Three', 48 => 'Crowd Four'] as $id => $name) {
            overhearingAddNpc($db, $id, $name);
        }
    }
);
overhearingCheck($overCap['status'] === 'committed' && $overCap['calls'] === 1, 'An over-cap roster must skip all extras and keep the direct ACK.');
foreach ([44, 45, 46, 47, 48] as $listenerId) {
    overhearingCheck(!overhearingLedgerHas($overCap['db'], $listenerId), 'The whole over-cap extra audience should be skipped.');
}
overhearingCheck(
    count(array_filter($overCap['records'], static fn(array $record): bool => ($record['event'] ?? null) === 'overhearing_audience_skipped' && ($record['reason'] ?? null) === 'audience_cap_reached')) === 1,
    'An over-cap crowd should emit one fixed diagnostic.'
);

$maximumRoster = overhearingExecute(
    '|Aela|Lydia|Inigo|Farkas|Serana|Brynjolf|',
    true,
    static fn(array $messages): string => overhearingBatchResponse($messages, [44 => ['npc:33' => 0, 'player' => 0]]),
    static function (MemoryStoreDb $db): void {
        overhearingAddNpc($db, 45, 'Farkas');
        overhearingAddNpc($db, 46, 'Serana');
        overhearingAddNpc($db, 47, 'Brynjolf');
    }
);
$maximumPayload = json_decode($maximumRoster['capturedMessages'][1]['content'], true, 64, JSON_THROW_ON_ERROR)['untrusted_data'];
$maximumRecipients = $maximumPayload['recipients'] ?? [];
$maximumFinished = array_values(array_filter($maximumRoster['records'], static fn(array $record): bool =>
    ($record['event'] ?? null) === 'request_finished'
));
$maximumFinishedIds = array_column($maximumFinished, 'listener_id');
sort($maximumFinishedIds, SORT_STRING);
$maximumPersistence = array_values(array_filter($maximumRoster['records'], static fn(array $record): bool =>
    ($record['event'] ?? null) === 'persistence_finished'
));
$zeroDeltaPersistence = array_values(array_filter($maximumPersistence, static fn(array $record): bool =>
    ($record['listener_id'] ?? null) === '44'
));
overhearingCheck(
    $maximumRoster['status'] === 'committed' && $maximumRoster['calls'] === 1
        && array_column($maximumRecipients, 'listener_id') === [22, 44, 45, 46, 47]
        && count($maximumFinished) === 5 && count($maximumPersistence) === 5
        && $maximumFinishedIds === ['22', '44', '45', '46', '47']
        && count(array_unique(array_column($maximumFinished, 'request_id'))) === 5
        && count(array_unique(array_column($maximumFinished, 'batch_id'))) === 1
        && count($zeroDeltaPersistence) === 1
        && ($zeroDeltaPersistence[0]['changed_count'] ?? null) === 0
        && ($zeroDeltaPersistence[0]['commit_state'] ?? null) === 'confirmed'
        && ($zeroDeltaPersistence[0]['committed'] ?? null) === true,
    'The maximum six-person roster should commit five independent listeners in one call, including a confirmed zero-delta save.'
);
foreach ([22, 44, 45, 46, 47] as $listenerId) {
    overhearingCheck(overhearingLedgerHas($maximumRoster['db'], $listenerId), 'Every listener in the maximum roster should receive its own ledger entry.');
}

// Resolve/filter first: speaker, direct target, player aliases, unknown, and ambiguous names are not recipients.
$filtered = overhearingExecute(
    '|Aela|Lydia|Inigo|Farkas|Unknown NPC|Dragonborn|Player|',
    true,
    'overhearingBatchProvider',
    static function (MemoryStoreDb $db): void {
        overhearingAddNpc($db, 45, 'Inigo');
        overhearingAddNpc($db, 46, 'Farkas');
    }
);
$filteredRecipients = json_decode($filtered['capturedMessages'][1]['content'], true, 64, JSON_THROW_ON_ERROR)['untrusted_data']['recipients'] ?? [];
overhearingCheck(
    $filtered['status'] === 'committed' && $filtered['calls'] === 1
        && array_column($filteredRecipients, 'listener_id') === [22, 46]
        && !overhearingLedgerHas($filtered['db'], 44) && overhearingLedgerHas($filtered['db'], 46),
    'Only the uniquely resolved eligible Farkas should be added to the addressed recipient.'
);

$sentinelAddress = overhearingExecute(
    '|Aela|explicit_disable_rechat|',
    false,
    'overhearingNormalResponse',
    static function (MemoryStoreDb $db, array &$event): void {
        $event['listener_id'] = 55;
        $event['listener_name'] = 'EXPLICIT_DISABLE_RECHAT';
        $db->events[100]['source_data'] = 'Aela: I trust Jarl Balgruuf. The Dragonborn is brave. (Talking to explicit_disable_rechat)';
        overhearingAddNpc($db, 55, 'explicit_disable_rechat');
    }
);
overhearingCheck(
    $sentinelAddress['status'] === 'actor-unmatched' && $sentinelAddress['calls'] === 0
        && !overhearingLedgerHas($sentinelAddress['db'], 55),
    'A sentinel-targeted ACK must not resolve a same-named NPC as an addressed recipient.'
);

$sentinelWitness = overhearingExecute(
    '|Aela|Lydia|Inigo|EXPLICIT_DISABLE_RECHAT|',
    true,
    'overhearingBatchProvider',
    static function (MemoryStoreDb $db): void {
        overhearingAddNpc($db, 55, 'explicit_disable_rechat');
    }
);
$sentinelPayload = json_decode($sentinelWitness['capturedMessages'][1]['content'], true, 64, JSON_THROW_ON_ERROR)['untrusted_data'];
$sentinelRecipients = $sentinelPayload['recipients'] ?? [];
overhearingCheck(
    $sentinelWitness['status'] === 'committed' && $sentinelWitness['calls'] === 1
        && array_column($sentinelRecipients, 'listener_id') === [22, 44]
        && overhearingLedgerHas($sentinelWitness['db'], 22) && overhearingLedgerHas($sentinelWitness['db'], 44)
        && !overhearingLedgerHas($sentinelWitness['db'], 55),
    'A sentinel in the source people roster must not become an overhearer through a catalog-name collision.'
);

$identityRace = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    static function (array $messages, MemoryStoreDb $db): string {
        overhearingAddNpc($db, 45, 'Jarl Balgruuf');
        return overhearingBatchResponse($messages);
    }
);
overhearingCheck(
    $identityRace['status'] === 'stale' && !overhearingLedgerHas($identityRace['db'], 22) && !overhearingLedgerHas($identityRace['db'], 44),
    'A newly ambiguous subject identity after model work should block both listener saves.'
);
$overhearerIdentityRace = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    static function (array $messages, MemoryStoreDb $db): string {
        overhearingAddNpc($db, 45, 'Inigo');
        return overhearingBatchResponse($messages);
    }
);
$overhearerIdentityRaceRows = array_values(array_filter($overhearerIdentityRace['records'], static fn(array $record): bool =>
    ($record['event'] ?? null) === 'persistence_finished' && ($record['listener_id'] ?? null) === '44'
));
overhearingCheck(
    $overhearerIdentityRace['status'] === 'committed'
        && overhearingLedgerHas($overhearerIdentityRace['db'], 22)
        && !overhearingLedgerHas($overhearerIdentityRace['db'], 44)
        && count($overhearerIdentityRaceRows) === 1
        && ($overhearerIdentityRaceRows[0]['persistence_outcome'] ?? null) === 'stale'
        && ($overhearerIdentityRaceRows[0]['persistence_reason'] ?? null) === 'actor-catalog-stale'
        && ($overhearerIdentityRaceRows[0]['commit_state'] ?? null) === 'not_attempted',
    'A newly ambiguous overhearer identity should fail closed for that listener while the addressed listener commits.'
);

// The addressed preflight result stays independent while a valid bystander proceeds.
$lockedAddress = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    'overhearingBatchProvider',
    static function (MemoryStoreDb $db): void { $db->npcs[22]['extended_data']->relationships_locked = true; }
);
$lockedAddressRows = overhearingFinishedFor($lockedAddress['records'], 22);
overhearingCheck(
    $lockedAddress['status'] === 'locked' && $lockedAddress['calls'] === 1
        && !overhearingLedgerHas($lockedAddress['db'], 22) && overhearingLedgerHas($lockedAddress['db'], 44)
        && count($lockedAddressRows) === 1 && ($lockedAddressRows[0]['reason'] ?? null) === 'relationship_locked'
        && ($lockedAddressRows[0]['listener_role'] ?? null) === 'addressed',
    'A locked addressed listener should retain its direct status while an eligible overhearer commits.'
);

// A sequential exact ACK replay should stop before paying for another batch request.
$replayCalls = 0;
$replayRecords = [];
$GLOBALS['overhearing_test_enabled'] = true;
resetAckLoggingInteraction();
$replayStatus = handleSpeechAck(
    overhearingAck($positive['event']),
    $positive['db'],
    static function (array $messages) use (&$replayCalls): string { $replayCalls++; return overhearingBatchResponse($messages); },
    new RequestLog(static function (string $json, string $level) use (&$replayRecords): void { $replayRecords[] = json_decode($json, true, 64, JSON_THROW_ON_ERROR); }, false)
);
overhearingCheck($replayStatus === 'duplicate' && $replayCalls === 0, 'A sequential exact replay should not incur a second provider call.');

// Exact source people, listener lock, and the setting are rechecked after provider work.
$membershipRace = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    static function (array $messages, MemoryStoreDb $db): string {
        $db->events[100]['people'] = '|Aela|Lydia|';
        return overhearingBatchResponse($messages);
    }
);
overhearingCheck($membershipRace['status'] === 'committed' && overhearingLedgerHas($membershipRace['db'], 22) && !overhearingLedgerHas($membershipRace['db'], 44), 'A changed witness snapshot should block only the overhearer write.');
$lockRace = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    'overhearingBatchProvider',
    null,
    static function (int $listenerId, MemoryStoreDb $db): void {
        if ($listenerId === 44) { $db->npcs[44]['extended_data']->relationships_locked = true; }
    }
);
overhearingCheck($lockRace['status'] === 'committed' && overhearingLedgerHas($lockRace['db'], 22) && !overhearingLedgerHas($lockRace['db'], 44), 'A lock acquired after model work should block that listener only.');
$settingRace = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    'overhearingBatchProvider',
    null,
    static function (int $listenerId): void {
        if ($listenerId === 44) { $GLOBALS['overhearing_test_enabled'] = false; }
    }
);
overhearingCheck($settingRace['status'] === 'committed' && overhearingLedgerHas($settingRace['db'], 22) && !overhearingLedgerHas($settingRace['db'], 44), 'A setting switch inside the save path should allow direct persistence and block extra persistence.');
$providerSettingRace = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    static function (array $messages): string {
        $GLOBALS['overhearing_test_enabled'] = false;
        return overhearingBatchResponse($messages);
    }
);
overhearingCheck($providerSettingRace['status'] === 'committed' && overhearingLedgerHas($providerSettingRace['db'], 22) && !overhearingLedgerHas($providerSettingRace['db'], 44), 'Turning the feature off after the model must retain the direct write only.');

// A response outside the closed listener/subject tuple set rejects the whole batch before writes.
$badBatch = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    static function (array $messages): string {
        return json_encode(['judgments' => [[
            'listener_id' => 999, 'subject' => 'npc:33', 'subject_mentioned' => true,
            'delta' => 5, 'reason' => 'unsupported recipient', 'evidence' => 'Jarl Balgruuf',
        ]]], JSON_THROW_ON_ERROR);
    }
);
overhearingCheck($badBatch['status'] === 'model-invalid' && $badBatch['calls'] === 1 && !overhearingLedgerHas($badBatch['db'], 22) && !overhearingLedgerHas($badBatch['db'], 44), 'A malformed recipient tuple must reject before any listener write.');

// One listener can fail independently; confirmed commits and uncertain commits stay visible per listener.
$partial = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    'overhearingBatchProvider',
    static function (MemoryStoreDb $db): void { $db->failWrites[44] = true; }
);
$partialAddressed = overhearingFinishedFor($partial['records'], 22);
$partialOverheard = overhearingFinishedFor($partial['records'], 44);
overhearingCheck(
    $partial['status'] === 'committed' && overhearingLedgerHas($partial['db'], 22) && !overhearingLedgerHas($partial['db'], 44)
        && ($partialAddressed[0]['commit_state'] ?? null) === 'confirmed'
        && ($partialOverheard[0]['outcome'] ?? null) === 'failed'
        && ($partialOverheard[0]['commit_state'] ?? null) === 'not_attempted',
    'An overhearer save failure must not change the confirmed addressed result or be reported as committed.'
);
$uncertain = overhearingExecute(
    '|Aela|Lydia|Inigo|',
    true,
    'overhearingBatchProvider',
    static function (MemoryStoreDb $db): void { $db->failCommit = true; }
);
$uncertainAddressed = overhearingFinishedFor($uncertain['records'], 22);
$uncertainOverheard = overhearingFinishedFor($uncertain['records'], 44);
overhearingCheck(
    $uncertain['status'] === 'failed'
        && ($uncertainAddressed[0]['commit_state'] ?? null) === 'unconfirmed'
        && ($uncertainOverheard[0]['commit_state'] ?? null) === 'unconfirmed',
    'An uncertain commit must remain uncertain in each affected listener terminal record.'
);

// Player input still uses the existing Player event query even though it carries people.
[, , , $playerDb] = baseFixture();
[$playerRequest, $playerInsert] = playerInputFixture($playerDb);
$GLOBALS['overhearing_test_enabled'] = true;
$playerStatus = handlePlayerInput(
    $playerRequest,
    $playerInsert,
    $playerDb,
    static fn(array $messages): string => validModelResponse([[
        'subject' => 'npc:33', 'delta' => 1,
        'reason' => 'A supported Player statement.', 'evidence' => 'kept his promise',
    ]])
);
overhearingCheck($playerStatus === 'committed' && $playerDb->eventByIdWithPeopleCalls === 0, 'Player persistence must retain its ordinary source-query path.');
unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);

echo "Overhearing ACK tests passed.\n";
