<?php
declare(strict_types=1);

define('CHIM_MIND_POISONING_TEST_FIXTURES_ONLY', true);
require_once __DIR__ . '/runtime_test.php';
require_once __DIR__ . '/../server/reflection.php';

use function ChimMindPoisoning\mindPoisoningEvaluateReflection;

function reflectionFixture(array $options = []): array
{
    [, , , $db] = baseFixture();
    $actor = $db->npcs[11];
    $actor['extended_data']->relationships->Lydia = (object)['aff' => 10, 'type' => 'ally'];
    $actor['personality'] = 'A cautious companion';
    $db->npcs[11] = $actor;
    $speech = $options['speech'] ?? 'In my heart, Lydia earned my trust again.';
    $source = $options['source'] ?? 'Aela: I still think Lydia earned my trust. (Talking to explicit_disable_rechat)';
    $eventId = $options['event_id'] ?? 100;
    $utteranceId = $options['utterance_id'] ?? 'utt_0123456789abcdef';
    $deliveryState = $options['delivery_state'] ?? 'emitted';
    $db->events[$eventId] = [
        'event_id' => $eventId,
        'utterance_id' => $utteranceId,
        'gamets' => 10.0,
        'source_data' => $source,
        'delivery_state' => $deliveryState,
        'type' => 'chat',
    ];
    $registration = [
        'event_id' => $eventId,
        'utterance_id' => $utteranceId,
        'actor_id' => 11,
        'actor_name' => 'Aela',
        'playthrough_id' => '1',
        'config_id' => '12345678-1234-4234-8234-123456789abc',
        'rechat_target_hint' => 'explicit_disable_rechat',
        'speech_hash' => hash('sha256', $speech),
    ];
    $ack = ['_speech', 0, 10, json_encode([
        'speaker' => 'Aela',
        'listener' => 'Dragonborn',
        'speech' => $speech,
        'utterance_id' => $utteranceId,
    ], JSON_THROW_ON_ERROR)];
    return [$registration, $ack, $db];
}

function reflectionResponse(int $delta = 3): string
{
    return validModelResponse([[
        'subject' => 'npc:22',
        'subject_mentioned' => true,
        'delta' => $delta,
        'reason' => 'The actor recalls Lydia honoring a promise.',
        'evidence' => 'Lydia earned my trust again.',
    ]]);
}

function captureObservedReflectionLog(array &$sinkRecords, array &$observedSummaries): object
{
    $requestLog = captureRequestLog($sinkRecords);
    $requestLog->observe(static function (array $record, string $level) use (&$observedSummaries): void {
        if (($record['event'] ?? null) === 'request_finished') {
            $observedSummaries[] = $record;
        }
    });
    return $requestLog;
}

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$stages = [];
$modelCalls = 0;
$reflectionRecords = [];
$reflectionObserved = [];
$status = mindPoisoningEvaluateReflection(
    $registration,
    $ack,
    $db,
    static function (array $given, string $stage) use (&$stages, $registration): bool {
        $stages[] = $stage;
        return $given === $registration;
    },
    static function (array $messages) use (&$modelCalls): string {
        $modelCalls++;
        $payload = json_decode($messages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
        same('Lydia', $payload['candidates']['npc:22']['name'] ?? null, 'Solo model context should use the real subject catalog.');
        same('In my heart, Lydia earned my trust again.', $payload['current_reflection'] ?? null, 'The model must judge the exact ACK subtitle.');
        return reflectionResponse();
    },
    captureObservedReflectionLog($reflectionRecords, $reflectionObserved)
);
same('committed', $status, 'A registered emitted line with its genuine ACK should commit.');
same(1, $modelCalls, 'One source ACK should make one provider request.');
check(in_array('pre_model', $stages, true) && in_array('transaction', $stages, true), 'Registration must be revalidated before model work and under transaction.');
same(2, count(array_filter($stages, static fn(string $stage): bool => $stage === 'transaction')), 'Registration must be checked after acquiring the actor lock and again before commit.');
same(13, $db->npcs[11]['extended_data']->relationships->Lydia->aff, 'A reflection may update only its actor-owned opinion edge.');
same(-50, $db->npcs[22]['extended_data']->relationships->Aela->aff, 'A reflection must not mutate another NPC.');
$reflectionSummary = lastRequestSummary($reflectionRecords);
$reflectionObservedSummary = lastRequestSummary($reflectionObserved);
same('reflection', $reflectionSummary['source_kind'] ?? null, 'Diagnostics must identify reflection provenance.');
same('11', $reflectionSummary['opinion_owner_id'] ?? null, 'Diagnostics must identify the actor who owns the opinion.');
check(!array_key_exists('listener_id', $reflectionSummary), 'Reflection diagnostics must not invent a listener identity.');
same($registration['config_id'], $reflectionObservedSummary['config_id'] ?? null, 'A validated registration should correlate its configuration UUID.');
same('100', $reflectionObservedSummary['event_id'] ?? null, 'The final summary should retain the exact registered event.');
same($registration['utterance_id'], $reflectionObservedSummary['utterance_id'] ?? null, 'The final summary should retain the exact registered utterance.');
check(preg_match('/\A[a-f0-9]{24}\z/D', $reflectionObservedSummary['request_id'] ?? '') === 1, 'The final summary should retain its generated request ID.');
same('npc', $reflectionObservedSummary['speaker_kind'] ?? null, 'Reflection should retain the validated NPC speaker kind.');
same('11', $reflectionObservedSummary['speaker_id'] ?? null, 'Reflection should retain the registered NPC speaker ID.');
same('11', $reflectionObservedSummary['opinion_owner_id'] ?? null, 'Reflection should retain its opinion owner in the observed summary.');
check(!array_key_exists('listener_id', $reflectionObservedSummary), 'The observed summary must not invent a listener identity.');
same('committed', $reflectionObservedSummary['persistence_outcome'] ?? null, 'The observer summary should retain persistence outcome.');
same('committed', $reflectionObservedSummary['persistence_reason'] ?? null, 'The observer summary should retain the persistence reason.');
same('confirmed', $reflectionObservedSummary['commit_state'] ?? null, 'The observer summary should retain confirmed commit state.');
same(true, $reflectionObservedSummary['committed'] ?? null, 'The observer summary should retain the commit result.');
same(1, $reflectionObservedSummary['changed_count'] ?? null, 'The observer summary should retain the number of changes.');
same([['subject' => 'npc:22', 'delta' => 3, 'before' => 10.0, 'after' => 13]], $reflectionObservedSummary['changes'] ?? null, 'The observer summary should retain sanitized changes.');
check(is_numeric($reflectionObservedSummary['model_ms'] ?? null) && is_numeric($reflectionObservedSummary['persistence_ms'] ?? null), 'The observer summary should retain model and persistence timing.');
$reflectionLedger = $db->npcs[11]['plugin_extended_data']->mind_poisoning;
same('reflection', $reflectionLedger->events[0]->source_kind, 'The ledger must retain distinct reflection provenance.');
same(64, strlen($reflectionLedger->reflection_state->basis), 'The bounded actor evidence basis should be retained separately from rolling event history.');
same(['npc:22'], $reflectionLedger->reflection_state->subjects, 'The processed subject token should be recorded for the unchanged basis.');

resetAckLoggingInteraction();
[$typeOnlyRegistration, $typeOnlyAck, $typeOnlyDb] = reflectionFixture();
$typeOnlyDb->npcs[11]['extended_data']->relationships->Lydia->type = 'neutral';
$typeOnlyRecords = [];
$typeOnlyStatus = mindPoisoningEvaluateReflection(
    $typeOnlyRegistration,
    $typeOnlyAck,
    $typeOnlyDb,
    static fn(array $given): bool => $given === $typeOnlyRegistration,
    static fn(array $messages): string => validModelResponse([[
        'subject' => 'npc:22',
        'subject_mentioned' => true,
        'delta' => 0,
        'reason' => 'A lasting reflection changes the actor opinion.',
        'evidence' => 'Lydia earned my trust again.',
        'type_change' => ['from' => 'neutral', 'to' => 'suspicious'],
    ]]),
    captureRequestLog($typeOnlyRecords)
);
same('committed', $typeOnlyStatus, 'A reflection may persist a type-only change.');
same(10, $typeOnlyDb->npcs[11]['extended_data']->relationships->Lydia->aff, 'A type-only reflection should leave affinity unchanged.');
same('suspicious', $typeOnlyDb->npcs[11]['extended_data']->relationships->Lydia->type, 'A type-only reflection should update the actor-owned type.');
$typeOnlySummary = lastRequestSummary($typeOnlyRecords);
same(1, $typeOnlySummary['changed_count'] ?? null, 'A type-only reflection should count as one changed relationship.');
same([], $typeOnlySummary['changes'] ?? null, 'A type-only reflection should not create an affinity change tuple.');
same([['subject' => 'npc:22', 'before' => 'neutral', 'after' => 'suspicious']], $typeOnlySummary['type_changes'] ?? null, 'The reflection summary should contain only the confirmed type transition.');
$typeOnlyEntry = $typeOnlyDb->npcs[11]['plugin_extended_data']->mind_poisoning->events[0];
same('neutral', $typeOnlyEntry->judgments[0]->type_change->from, 'Reflection history should preserve the validated type origin.');
same('suspicious', $typeOnlyEntry->type_changes[0]->after, 'Reflection history should preserve the confirmed type result.');

resetAckLoggingInteraction();
[$emptyMapRegistration, $emptyMapAck, $emptyMapDb] = reflectionFixture();
$emptyMapDb->npcs[11]['extended_data']->relationships = [];
$emptyMapModelCalls = 0;
same('committed', mindPoisoningEvaluateReflection(
    $emptyMapRegistration,
    $emptyMapAck,
    $emptyMapDb,
    static fn(): bool => true,
    static function (array $messages) use (&$emptyMapModelCalls): string {
        $emptyMapModelCalls++;
        return reflectionResponse();
    }
), 'An empty stored actor map should accept a public NPC-only reflection.');
same(1, $emptyMapModelCalls, 'An empty-map reflection should make one provider request.');
check($emptyMapDb->npcs[11]['extended_data']->relationships instanceof stdClass, 'An empty-map reflection should store relationships as an object.');
same(3, $emptyMapDb->npcs[11]['extended_data']->relationships->Lydia->aff ?? null, 'An empty-map reflection should commit its actor-owned affinity.');
same('neutral', $emptyMapDb->npcs[11]['extended_data']->relationships->Lydia->type ?? null, 'A new reflection edge should use the neutral default.');
same(1, count($emptyMapDb->history), 'An empty-map reflection should create one actor snapshot.');

foreach ([
    ['In my heart, Lydia earned my trust again.', 'Lydia earned my trust again.', 'stale'],
    ['In my heart, Lydia Vance earned my trust again.', 'Lydia Vance earned my trust again.', 'committed'],
] as [$raceSpeech, $raceEvidence, $expectedRaceStatus]) {
    resetAckLoggingInteraction();
    [$raceRegistration, $raceAck, $raceDb] = reflectionFixture(['speech' => $raceSpeech]);
    $raceDb->npcs[22]['npc_name'] = 'Lydia Vance';
    $raceDb->npcs[11]['extended_data']->relationships->{'Lydia Vance'} = (object)['aff' => 10, 'type' => 'ally'];
    $beforeRaceActor = unserialize(serialize($raceDb->npcs[11]));
    $raceModelCalls = 0;
    $insertAmbiguousReflectionAlias = static function (array $messages) use ($raceDb, $raceEvidence, &$raceModelCalls): string {
        $raceModelCalls++;
        $payload = json_decode($messages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
        same('Lydia Vance', $payload['candidates']['npc:22']['name'] ?? null, 'Reflection should retain the selected subject canonical name.');
        $raceDb->npcs[44] = [
            'id' => 44,
            'npc_name' => 'Lydia Hart',
            'extended_data' => (object)['relationships' => new stdClass()],
            'plugin_extended_data' => new stdClass(),
        ];
        return validModelResponse([[
            'subject' => 'npc:22',
            'delta' => 3,
            'reason' => 'A supported reflection claim.',
            'evidence' => $raceEvidence,
        ]]);
    };
    same($expectedRaceStatus, mindPoisoningEvaluateReflection(
        $raceRegistration,
        $raceAck,
        $raceDb,
        static fn(): bool => true,
        $insertAmbiguousReflectionAlias
    ), 'Reflection persistence must reject a newly ambiguous alias but retain a canonical full-name match.');
    same(1, $raceModelCalls, 'Reflection subject revalidation should follow one model call.');
    if ($expectedRaceStatus === 'stale') {
        check(ChimMindPoisoning\sameJsonValue($beforeRaceActor, $raceDb->npcs[11]), 'An ambiguous reflection alias must not change affinity or the ledger.');
        same([], $raceDb->history, 'An ambiguous reflection alias must not create a history snapshot.');
        check(!property_exists($raceDb->npcs[11]['plugin_extended_data'], 'mind_poisoning'), 'An ambiguous reflection alias must not create a dedupe ledger.');
    } else {
        same(13, $raceDb->npcs[11]['extended_data']->relationships->{'Lydia Vance'}->aff, 'A canonical full-name reflection remains usable with the same ambiguous short alias.');
        same(1, count($raceDb->history), 'A canonical full-name reflection should create its history snapshot.');
    }
}

resetAckLoggingInteraction();
[$playerCollisionRegistration, $playerCollisionAck, $playerCollisionDb] = reflectionFixture();
$playerCollisionDb->npcs[44] = [
    'id' => 44,
    'npc_name' => $playerCollisionDb->playerName,
    'extended_data' => (object)['relationships' => new stdClass()],
    'plugin_extended_data' => new stdClass(),
];
$playerCollisionPayload = json_decode($playerCollisionAck[3], true, 32, JSON_THROW_ON_ERROR);
$playerCollisionPayload['listener'] = $playerCollisionDb->playerName;
$playerCollisionAck[3] = json_encode($playerCollisionPayload, JSON_THROW_ON_ERROR);
$playerCollisionCalls = 0;
same('committed', mindPoisoningEvaluateReflection(
    $playerCollisionRegistration,
    $playerCollisionAck,
    $playerCollisionDb,
    static fn(): bool => true,
    static function () use (&$playerCollisionCalls): string {
        $playerCollisionCalls++;
        return reflectionResponse();
    }
), 'A Player listener sharing a catalog NPC name must remain valid after exact source validation.');
same(1, $playerCollisionCalls, 'A same-named Player/NPC listener must reach the model once.');

resetAckLoggingInteraction();
[$malformedPlayerRegistration, $malformedPlayerAck, $malformedPlayerDb] = reflectionFixture([
    'speech' => 'In my heart, Dragonborn is brave again.',
    'source' => 'Aela: I still think Dragonborn is brave again. (Talking to explicit_disable_rechat)',
]);
$malformedPlayerDb->npcs[11]['extended_data']->relationships->Dragonborn = [1];
$beforeMalformedPlayerReflection = serialize($malformedPlayerDb->npcs[11]);
$malformedPlayerReflectionCalls = 0;
same('actor-invalid', mindPoisoningEvaluateReflection(
    $malformedPlayerRegistration,
    $malformedPlayerAck,
    $malformedPlayerDb,
    static fn(): bool => true,
    static function () use (&$malformedPlayerReflectionCalls): string {
        $malformedPlayerReflectionCalls++;
        return reflectionResponse();
    }
), 'Reflection must reject a malformed raw Player alias before model evaluation.');
same(0, $malformedPlayerReflectionCalls, 'Malformed stored Player credibility must not reach the reflection model.');
same($beforeMalformedPlayerReflection, serialize($malformedPlayerDb->npcs[11]), 'Malformed reflection Player data must remain unchanged.');
same([], $malformedPlayerDb->history, 'Malformed reflection Player data must not create a snapshot.');
check(!property_exists($malformedPlayerDb->npcs[11]['plugin_extended_data'], 'mind_poisoning'), 'Malformed reflection Player data must not write a ledger.');

foreach (['Lydia', 'explicit_disable_rechat'] as $invalidListener) {
    resetAckLoggingInteraction();
    [$invalidListenerRegistration, $invalidListenerAck, $invalidListenerDb] = reflectionFixture();
    $invalidListenerPayload = json_decode($invalidListenerAck[3], true, 32, JSON_THROW_ON_ERROR);
    $invalidListenerPayload['listener'] = $invalidListener;
    $invalidListenerAck[3] = json_encode($invalidListenerPayload, JSON_THROW_ON_ERROR);
    $invalidListenerCalls = 0;
    same('event-mismatch', mindPoisoningEvaluateReflection(
        $invalidListenerRegistration,
        $invalidListenerAck,
        $invalidListenerDb,
        static fn(): bool => true,
        static function () use (&$invalidListenerCalls): string {
            $invalidListenerCalls++;
            return reflectionResponse();
        }
    ), 'A distinct NPC or the source sentinel must not be accepted as the ACK listener.');
    same(0, $invalidListenerCalls, 'An invalid ACK listener must stop before model work.');
}

resetAckLoggingInteraction();
[$wrongSourceRegistration, $wrongSourceAck, $wrongSourceDb] = reflectionFixture([
    'source' => 'Aela: I still think Lydia earned my trust. (Talking to Lydia)',
]);
$wrongSourceCalls = 0;
same('event-mismatch', mindPoisoningEvaluateReflection(
    $wrongSourceRegistration,
    $wrongSourceAck,
    $wrongSourceDb,
    static fn(): bool => true,
    static function () use (&$wrongSourceCalls): string {
        $wrongSourceCalls++;
        return reflectionResponse();
    }
), 'A Player listener match must not bypass the exact source sentinel.');
same(0, $wrongSourceCalls, 'A source without the exact sentinel must stop before model work.');

$replayRecords = [];
$replayObserved = [];
same('duplicate', mindPoisoningEvaluateReflection($registration, $ack, $db, static fn(): bool => true, static function () use (&$modelCalls): string {
    $modelCalls++;
    return reflectionResponse();
}, captureObservedReflectionLog($replayRecords, $replayObserved)), 'An exact replay must be deduped.');
same('duplicate-event', lastRequestSummary($replayObserved)['reason'] ?? null, 'An exact replay should retain its fixed duplicate reason.');
same('info', lastRequestSummary($replayObserved)['level'] ?? null, 'An exact replay should remain informational.');
[$nextRegistration, $nextAck] = $registration === [] ? [[], []] : [$registration, $ack];
$nextRegistration['event_id'] = 101;
$nextRegistration['utterance_id'] = 'utt_abcdef0123456789';
$nextAck[3] = json_encode([
    'speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => 'In my heart, Lydia earned my trust again.',
    'utterance_id' => $nextRegistration['utterance_id'],
], JSON_THROW_ON_ERROR);
$db->events[101] = [
    'event_id' => 101, 'utterance_id' => $nextRegistration['utterance_id'], 'gamets' => 11.0,
    'source_data' => 'Aela: I still think Lydia earned my trust. (Talking to explicit_disable_rechat)',
    'delivery_state' => 'spoken', 'type' => 'chat',
];
same('duplicate', mindPoisoningEvaluateReflection($nextRegistration, $nextAck, $db, static fn(): bool => true, static function () use (&$modelCalls): string {
    $modelCalls++;
    return reflectionResponse();
}), 'A new utterance over an unchanged profile/history basis must not compound.');
same(1, $modelCalls, 'Exact and unchanged-basis duplicates must both stop before provider work.');

$evictedLedger = $db->npcs[11]['plugin_extended_data']->mind_poisoning;
$evictedLedger->floor_event_id = 100;
$evictedLedger->events = [];
for ($eventId = 101; $eventId <= 228; $eventId++) {
    $evictedLedger->events[] = (object)['event_id' => $eventId, 'utterance_id' => 'utt_' . str_pad((string)$eventId, 16, '0', STR_PAD_LEFT), 'judgments' => []];
}
$db->npcs[11]['plugin_extended_data']->mind_poisoning = $evictedLedger;
$evictionRegistration = $registration;
$evictionRegistration['event_id'] = 300;
$evictionRegistration['utterance_id'] = 'utt_3000000000000000';
$evictionAck = $ack;
$evictionPayload = json_decode($evictionAck[3], true, 32, JSON_THROW_ON_ERROR);
$evictionPayload['utterance_id'] = $evictionRegistration['utterance_id'];
$evictionAck[3] = json_encode($evictionPayload, JSON_THROW_ON_ERROR);
$db->events[300] = [
    'event_id' => 300, 'utterance_id' => $evictionRegistration['utterance_id'], 'gamets' => 12.0,
    'source_data' => 'Aela: I still think Lydia earned my trust. (Talking to explicit_disable_rechat)',
    'delivery_state' => 'emitted', 'type' => 'chat',
];
same('duplicate', mindPoisoningEvaluateReflection($evictionRegistration, $evictionAck, $db, static fn(): bool => true, static function () use (&$modelCalls): string {
    $modelCalls++;
    return reflectionResponse();
}), 'The separate reflection basis must survive eviction from the 128-event rolling ledger.');
same(1, $modelCalls, 'Rolling-ledger eviction must not re-enable an unchanged reflection.');

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$staleCalls = 0;
$staleRecords = [];
$staleObserved = [];
same('stale', mindPoisoningEvaluateReflection($registration, $ack, $db, static fn(): bool => false, static function () use (&$staleCalls): string {
    $staleCalls++;
    return reflectionResponse();
}, captureObservedReflectionLog($staleRecords, $staleObserved)), 'A changed or missing PCV registration must fail closed.');
same('reflection-registration-stale', lastRequestSummary($staleObserved)['reason'] ?? null, 'A stale registration should retain its fixed reason.');
same('info', lastRequestSummary($staleObserved)['level'] ?? null, 'A stale registration should remain informational.');
same(0, $staleCalls, 'A stale registration must not reach the model.');
same([], $db->history, 'A stale registration must not create a snapshot.');

resetAckLoggingInteraction();
[$invalidRegistration, $ack, $db] = reflectionFixture();
$invalidRegistration['speech_hash'] = 'not-a-hash';
$invalidRecords = [];
$invalidObserved = [];
same('invalid-payload', mindPoisoningEvaluateReflection(
    $invalidRegistration,
    $ack,
    $db,
    static fn(): bool => true,
    static fn(): string => reflectionResponse(),
    captureObservedReflectionLog($invalidRecords, $invalidObserved)
), 'A malformed registration should stop before evaluation.');
$invalidSummary = lastRequestSummary($invalidObserved);
same('reflection-registration-invalid', $invalidSummary['reason'] ?? null, 'A malformed registration should retain its fixed reason.');
same('warning', $invalidSummary['level'] ?? null, 'A malformed registration should remain a warning.');
foreach (['config_id', 'event_id', 'utterance_id', 'speaker_id', 'opinion_owner_id'] as $field) {
    check(!array_key_exists($field, $invalidSummary), 'An invalid registration must not contribute the ' . $field . ' correlation field.');
}

check(file_put_contents($pauseControlPath, '{"enabled":"false"}') !== false, 'The malformed pause control fixture should be written.');
resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$pauseRecords = [];
$pauseObserved = [];
same('paused', mindPoisoningEvaluateReflection(
    $registration,
    $ack,
    $db,
    static fn(): bool => true,
    static fn(): string => reflectionResponse(),
    captureObservedReflectionLog($pauseRecords, $pauseObserved)
), 'An invalid operator pause control should stop reflection.');
$pauseSummary = lastRequestSummary($pauseObserved);
same('pause_control_invalid', $pauseSummary['reason'] ?? null, 'An invalid pause control should retain its fixed reason.');
same('warning', $pauseSummary['level'] ?? null, 'An invalid pause control should remain a warning.');
same('not_called', $pauseSummary['model_outcome'] ?? null, 'An invalid pause control must stop before model work.');
@unlink($pauseControlPath);

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture(['delivery_state' => 'aborted']);
$abortedCalls = 0;
same('event-unmatched', mindPoisoningEvaluateReflection($registration, $ack, $db, static fn(): bool => true, static function () use (&$abortedCalls): string {
    $abortedCalls++;
    return reflectionResponse();
}), 'Aborted speech must not be promoted to a reflection event.');
same(0, $abortedCalls, 'Aborted speech must stop before provider work.');

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$badAck = $ack;
$badPayload = json_decode($badAck[3], true, 32, JSON_THROW_ON_ERROR);
$badPayload['speech'] = 'A different unrelated line.';
$badAck[3] = json_encode($badPayload, JSON_THROW_ON_ERROR);
$badAckCalls = 0;
same('event-mismatch', mindPoisoningEvaluateReflection($registration, $badAck, $db, static fn(): bool => true, static function () use (&$badAckCalls): string {
    $badAckCalls++;
    return reflectionResponse();
}), 'The PCV exact-emitted subtitle digest must match the genuine native ACK.');
same(0, $badAckCalls, 'An unrelated ACK must not reach the model.');

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$db->busy = true;
$busyCalls = 0;
same('busy', mindPoisoningEvaluateReflection($registration, $ack, $db, static fn(): bool => true, static function () use (&$busyCalls): string {
    $busyCalls++;
    return reflectionResponse();
}), 'A contested actor lock must keep the reflection unapplied.');
same(1, $busyCalls, 'The provider must remain outside the actor database lock.');
same(10, $db->npcs[11]['extended_data']->relationships->Lydia->aff, 'A busy transaction must preserve the actor opinion.');
same([], $db->history, 'A busy transaction must not create a snapshot.');

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$db->npcs[11]['extended_data']->relationships_locked = true;
$lockedCalls = 0;
same('locked', mindPoisoningEvaluateReflection($registration, $ack, $db, static fn(): bool => true, static function () use (&$lockedCalls): string {
    $lockedCalls++;
    return reflectionResponse();
}), 'A relationship lock must apply to the reflecting actor.');
same(0, $lockedCalls, 'A locked actor must stop before provider work.');

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$zeroCalls = 0;
$zeroRecords = [];
$zeroObserved = [];
same('committed', mindPoisoningEvaluateReflection($registration, $ack, $db, static fn(): bool => true, static function (array $messages) use (&$zeroCalls): string {
    $zeroCalls++;
    return reflectionResponse(0);
}, captureObservedReflectionLog($zeroRecords, $zeroObserved)), 'An all-zero reflection should still persist the unchanged-basis dedupe token.');
same('committed', lastRequestSummary($zeroObserved)['persistence_outcome'] ?? null, 'A zero change remains a committed persistence outcome.');
same('zero-change', lastRequestSummary($zeroObserved)['persistence_reason'] ?? null, 'A zero change should retain its distinct persistence reason.');
same('confirmed', lastRequestSummary($zeroObserved)['commit_state'] ?? null, 'A zero change should retain confirmed commit state.');
same(0, lastRequestSummary($zeroObserved)['changed_count'] ?? null, 'A zero change should report no changed edges.');
same([], lastRequestSummary($zeroObserved)['changes'] ?? null, 'A zero change should report an empty change list.');
same(10, $db->npcs[11]['extended_data']->relationships->Lydia->aff, 'An all-zero reflection must not alter affinity.');
$zeroNext = $registration;
$zeroNext['event_id'] = 102;
$zeroNext['utterance_id'] = 'utt_1020000000000000';
$zeroAck = $ack;
$zeroPayload = json_decode($zeroAck[3], true, 32, JSON_THROW_ON_ERROR);
$zeroPayload['utterance_id'] = $zeroNext['utterance_id'];
$zeroAck[3] = json_encode($zeroPayload, JSON_THROW_ON_ERROR);
$db->events[102] = [
    'event_id' => 102, 'utterance_id' => $zeroNext['utterance_id'], 'gamets' => 11.0,
    'source_data' => 'Aela: I still think Lydia earned my trust. (Talking to explicit_disable_rechat)',
    'delivery_state' => 'emitted', 'type' => 'chat',
];
same('duplicate', mindPoisoningEvaluateReflection($zeroNext, $zeroAck, $db, static fn(): bool => true, static function (array $messages) use (&$zeroCalls): string {
    $zeroCalls++;
    return reflectionResponse(0);
}), 'A zero decision must suppress another model call over the unchanged basis.');
same(1, $zeroCalls, 'The zero-decision basis must be durable.');

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$basisEvent = [
    'event_id' => $registration['event_id'],
    'opinion_owner_id' => $registration['actor_id'],
    'player_name' => $db->playerName,
];
$basisHistory = null;
$basisReason = null;
$otherScopeBasis = ChimMindPoisoning\reflectionBasisForEvent($db->npcs[11], $db, $basisEvent, $basisHistory, $basisReason);
check(is_string($otherScopeBasis), 'The scope-reset test needs a stable evidence basis.');
$db->npcs[11]['plugin_extended_data']->mind_poisoning = (object)[
    'playthrough_id' => '2',
    'floor_event_id' => 0,
    'events' => [],
    'reflection_state' => (object)['basis' => $otherScopeBasis, 'subjects' => ['npc:33']],
];
same('committed', mindPoisoningEvaluateReflection($registration, $ack, $db, static fn(): bool => true, static fn(array $messages): string => reflectionResponse()), 'A new playthrough should start a fresh reflection ledger.');
$newScopeLedger = $db->npcs[11]['plugin_extended_data']->mind_poisoning;
same('1', $newScopeLedger->playthrough_id, 'The committed state should bind to the active playthrough.');
same(['npc:22'], $newScopeLedger->reflection_state->subjects, 'A prior scope subject must not leak into the new playthrough basis.');

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$transactionCalls = 0;
$transactionModelCalls = 0;
same('stale', mindPoisoningEvaluateReflection($registration, $ack, $db, static function (array $given, string $stage) use (&$transactionCalls): bool {
    $transactionCalls++;
    return $stage !== 'transaction';
}, static function () use (&$transactionModelCalls): string {
    $transactionModelCalls++;
    return reflectionResponse();
}), 'A scope or registration change during model work must abort the actor transaction.');
same(1, $transactionModelCalls, 'The transaction recheck should happen only after the out-of-lock model call.');
same(3, $transactionCalls, 'A stale transaction should be checked twice before model work and once after acquiring the actor lock.');
same(10, $db->npcs[11]['extended_data']->relationships->Lydia->aff, 'A stale transaction must preserve the actor opinion.');
same([], $db->history, 'A stale transaction must not create a snapshot.');

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$historyChangedCalls = 0;
same('stale', mindPoisoningEvaluateReflection($registration, $ack, $db, static fn(): bool => true, static function () use (&$historyChangedCalls, $db): string {
    $historyChangedCalls++;
    $db->reflectionRows[] = [
        'event_id' => 90, 'utterance_id' => 'utt_9090909090909090', 'delivery_state' => 'spoken',
        'people' => '|Aela|Lydia|', 'source_data' => 'Lydia: I kept my promise to Aela. (Talking to Aela)',
    ];
    return reflectionResponse();
}), 'A changed genuine-history basis during model work must not commit a stale judgment.');
same(1, $historyChangedCalls, 'The basis-stale case should make one provider call before its transaction recheck.');
same(10, $db->npcs[11]['extended_data']->relationships->Lydia->aff, 'A stale actor-history basis must preserve affinity.');
same([], $db->history, 'A stale actor-history basis must not create a snapshot.');

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$failedRecords = [];
$privateText = json_decode($ack[3], true, 32, JSON_THROW_ON_ERROR)['speech'];
$privateHash = $registration['speech_hash'];
same('failed', mindPoisoningEvaluateReflection($registration, $ack, $db, static fn(): bool => true, static function (): string {
    throw new RuntimeException('provider fixture failure');
}, captureRequestLog($failedRecords)), 'Provider failures must remain failures.');
same([], $db->history, 'A provider failure must not create a snapshot.');
$encodedFailureLog = json_encode($failedRecords, JSON_THROW_ON_ERROR);
check(!str_contains($encodedFailureLog, $privateText) && !str_contains($encodedFailureLog, $privateHash), 'Logs must contain neither reflection dialogue nor the private speech digest.');

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$db->failSnapshot = true;
$persistenceRecords = [];
$persistenceObserved = [];
same('failed', mindPoisoningEvaluateReflection(
    $registration,
    $ack,
    $db,
    static fn(): bool => true,
    static fn(): string => reflectionResponse(),
    captureObservedReflectionLog($persistenceRecords, $persistenceObserved)
), 'A snapshot verification failure must remain a failed reflection.');
$persistenceSummary = lastRequestSummary($persistenceObserved);
same('failed', $persistenceSummary['persistence_outcome'] ?? null, 'The final observer should retain persistence failure.');
same('snapshot-verification-failed', $persistenceSummary['persistence_reason'] ?? null, 'The final observer should retain the specific persistence reason.');
same('not_attempted', $persistenceSummary['commit_state'] ?? null, 'A pre-commit snapshot failure should retain its commit state.');
same('error', $persistenceSummary['level'] ?? null, 'Persistence failure should remain an error.');

resetAckLoggingInteraction();
[$registration, $ack, $db] = reflectionFixture();
$db->npcs[11]['extended_data']->relationships->Lydia->aff = 250;
$invalidAffinityRecords = [];
$invalidAffinityObserved = [];
$invalidAffinityLog = captureRequestLog($invalidAffinityRecords);
$invalidAffinityLog->observe(static function (array $record, string $level) use (&$invalidAffinityObserved): void {
    $invalidAffinityObserved[] = $record + ['level' => $level];
});
same('invalid', mindPoisoningEvaluateReflection(
    $registration,
    $ack,
    $db,
    static fn(): bool => true,
    static fn(): string => reflectionResponse(),
    $invalidAffinityLog
), 'An out-of-range actor affinity should reject reflection persistence.');
$invalidPersistenceEvents = array_values(array_filter(
    $invalidAffinityObserved,
    static fn(array $record): bool => ($record['event'] ?? null) === 'persistence_finished'
));
$invalidAffinityPersistence = $invalidPersistenceEvents[0] ?? [];
$invalidAffinitySummary = lastRequestSummary($invalidAffinityObserved);
same('affinity-invalid', $invalidAffinityPersistence['persistence_reason'] ?? null, 'The persistence event should retain the invalid affinity reason.');
same('warning', $invalidAffinityPersistence['level'] ?? null, 'Invalid persistence should emit a warning.');
same('invalid', $invalidAffinitySummary['persistence_outcome'] ?? null, 'The terminal event should retain the invalid persistence outcome.');
same('affinity-invalid', $invalidAffinitySummary['persistence_reason'] ?? null, 'The terminal event should retain the invalid affinity reason.');
same('rejected', $invalidAffinitySummary['outcome'] ?? null, 'Invalid persistence should be a rejected terminal result.');
same('warning', $invalidAffinitySummary['level'] ?? null, 'The terminal event should retain warning severity for invalid persistence.');
same(250, $db->npcs[11]['extended_data']->relationships->Lydia->aff, 'Invalid affinity must remain unchanged.');
same([], $db->history, 'Invalid affinity must not create a snapshot.');

echo "reflection checks passed\n";
