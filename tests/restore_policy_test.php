<?php
declare(strict_types=1);

use function ChimMindPoisoning\handleSpeechAck;
use function ChimMindPoisoning\handlePlayerInput;
use function ChimMindPoisoning\mindPoisoningEvaluateReflectionReply;

if (!array_key_exists('restore_policy_overhearing_enabled', $GLOBALS)) {
    $GLOBALS['restore_policy_overhearing_enabled'] = false;
}
function chimGetGeneralSettingBool(string $id, bool $default = false): bool
{
    return $id === 'mind_poisoning_overhearing_enabled'
        ? (bool)($GLOBALS['restore_policy_overhearing_enabled'] ?? $default)
        : $default;
}

require_once __DIR__ . '/runtime_test.php';
require_once __DIR__ . '/../server/reflection.php';

if (interface_exists(\ChimMindPoisoning\RestorePolicyStoreDb::class)) {
    final class RestorePolicyTestDb implements
        \ChimMindPoisoning\StoreDb,
        \ChimMindPoisoning\WitnessSnapshotStoreDb,
        \ChimMindPoisoning\RestorePolicyStoreDb
    {
        public int $barrierAcquisitions = 0;
        public int $barrierReleases = 0;
        public bool $barrierAcquisition = true;
        public bool $barrierReleaseThrows = false;
        public bool $releaseThrows = false;
        public bool $commitPersistsButReportsFailure = false;
        public bool $throwAfterBegin = false;
        public int $eventBoundsReads = 0;
        public int $rollbackCalls = 0;
        public int $releaseCalls = 0;

        public function __construct(public MemoryStoreDb $memory, public mixed $eventBounds)
        {
        }

        public function activePlaythrough(): ?array { return $this->memory->activePlaythrough(); }
        public function acknowledgedEvent(string $utteranceId): ?array { return $this->memory->acknowledgedEvent($utteranceId); }
        public function acknowledgedEventWithPeople(string $utteranceId): ?array { return $this->memory->acknowledgedEventWithPeople($utteranceId); }
        public function playerInputEvent(array $source): ?array { return $this->memory->playerInputEvent($source); }
        public function eventById(int $eventId, string $utteranceId): ?array { return $this->memory->eventById($eventId, $utteranceId); }
        public function eventByIdWithPeople(int $eventId, string $utteranceId): ?array { return $this->memory->eventByIdWithPeople($eventId, $utteranceId); }
        public function npcIdentities(): array
        {
            return array_values(array_filter(array_map(static function (array $row): ?array {
                $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
                return $id !== false && $id > 0
                    ? ['id' => (int)$id, 'npc_name' => $row['npc_name']]
                    : null;
            }, $this->memory->npcIdentities())));
        }
        public function npcById(int $npcId, bool $forUpdate = false): ?array { return $this->memory->npcById($npcId, $forUpdate); }
        public function reflectionHistory(string $actorName, int $beforeEventId): array { return $this->memory->reflectionHistory($actorName, $beforeEventId); }
        public function beginForListener(int $listenerId): bool
        {
            $started = $this->memory->beginForListener($listenerId);
            if ($started && $this->throwAfterBegin) {
                throw new RuntimeException('synthetic failure after transaction acquisition');
            }
            return $started;
        }
        public function writeNpc(int $npcId, array $relationshipEdges, object $mindPoisoningData, float $gamets, array $relationshipKeysToRemove = []): bool
        {
            return $this->memory->writeNpc($npcId, $relationshipEdges, $mindPoisoningData, $gamets, $relationshipKeysToRemove);
        }
        public function backupAndVerify(int $npcId, array $expected): bool { return $this->memory->backupAndVerify($npcId, $expected); }
        public function commit(): bool
        {
            if ($this->commitPersistsButReportsFailure) {
                $this->memory->commit();
                return false;
            }
            return $this->memory->commit();
        }
        public function rollback(): void { $this->rollbackCalls++; $this->memory->rollback(); }
        public function release(): void
        {
            $this->releaseCalls++;
            $this->memory->release();
            if ($this->releaseThrows) {
                throw new RuntimeException('synthetic listener cleanup failure after confirmed commit');
            }
        }
        public function restoreEventBounds(): ?array
        {
            $this->eventBoundsReads++;
            return is_callable($this->eventBounds) ? ($this->eventBounds)($this->eventBoundsReads) : $this->eventBounds;
        }
        public function acquireRestorePolicyBarrier(): bool
        {
            $this->barrierAcquisitions++;
            return $this->barrierAcquisition;
        }
        public function releaseRestorePolicyBarrier(): void
        {
            $this->barrierReleases++;
            if ($this->barrierReleaseThrows) {
                throw new RuntimeException('synthetic barrier release failure');
            }
        }
    }
}

function restorePolicyFixture(int $eventId, string $scope, mixed $bounds, ?array $ledger = null): array
{
    [$event, , , $memory] = baseFixture();
    $event['event_id'] = $eventId;
    $event['utterance_id'] = 'utt_restore_' . str_pad((string)$eventId, 8, '0', STR_PAD_LEFT);
    $event['playthrough_id'] = $scope;
    $memory->events = [$eventId => $event + ['type' => 'chat', 'delivery_state' => 'spoken']];
    $memory->profileId = $scope;
    if ($ledger !== null) {
        $memory->npcs[22]['plugin_extended_data']->mind_poisoning = json_decode(
            json_encode($ledger, JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }
    $store = new RestorePolicyTestDb($memory, $bounds);
    $ack = ['_speech', 0, 10, json_encode([
        'speaker' => 'Aela',
        'listener' => 'Lydia',
        'speech' => $event['text'],
        'utterance_id' => $event['utterance_id'],
    ], JSON_THROW_ON_ERROR)];
    return [$event, $memory, $store, $ack];
}

function restorePolicyRunFocus(string $focus): void
{
    if ($focus === '--focus-player-red') {
        [, $memory, $store] = restorePolicyFixture(884, '1', ['latest_init_event_id' => 883, 'max_event_id' => 884]);
        [$request, $insert] = playerInputFixture($memory);
        $calls = 0;
        $status = handlePlayerInput($request, $insert, $store, static function (array $messages) use (&$calls): string {
            $calls++;
            return validModelResponse([['subject' => 'npc:33', 'delta' => 1, 'reason' => 'A credible defense', 'evidence' => 'kept his promise']]);
        });
        if ($status !== 'committed' || $calls !== 1) {
            throw new RuntimeException("Expected an eligible restore-mode Player input to commit; got {$status} after {$calls} model calls.");
        }
        echo "restore-policy Player route accepted\n";
        return;
    }

    if ($focus === '--focus-reflection-v2-red') {
        [, $memory, $store] = restorePolicyFixture(101, '1', ['latest_init_event_id' => 99, 'max_event_id' => 101]);
        $bodies = ['Lydia betrayed me after I trusted her.', 'I remember that evening clearly.'];
        $lines = [];
        foreach ($bodies as $index => $body) {
            $eventId = 100 + $index;
            $utteranceId = 'utt_restore_reply_' . str_pad((string)($index + 1), 4, '0', STR_PAD_LEFT);
            $memory->events[$eventId] = [
                'event_id' => $eventId,
                'utterance_id' => $utteranceId,
                'gamets' => 10.0 + $index,
                'source_data' => 'Aela: ' . $body . ' (Talking to explicit_disable_rechat)',
                'delivery_state' => 'spoken',
                'type' => 'chat',
            ];
            $lines[] = ['event_id' => $eventId, 'utterance_id' => $utteranceId, 'speech_hash' => hash('sha256', $body)];
        }
        $final = $lines[1];
        $registration = [
            'event_id' => $final['event_id'],
            'utterance_id' => $final['utterance_id'],
            'actor_id' => 11,
            'actor_name' => 'Aela',
            'playthrough_id' => '1',
            'config_id' => '12345678-1234-4234-8234-123456789abc',
            'rechat_target_hint' => 'explicit_disable_rechat',
            'speech_hash' => $final['speech_hash'],
            'lines' => $lines,
        ];
        $ack = ['_speech', 0, 11, json_encode([
            'speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => $bodies[1], 'utterance_id' => $final['utterance_id'],
        ], JSON_THROW_ON_ERROR)];
        [, $boundaryMemory, $boundaryStore] = restorePolicyFixture(101, '1', ['latest_init_event_id' => 99, 'max_event_id' => 101]);
        $boundaryRegistration = $registration;
        $boundaryRegistration['lines'][0]['event_id'] = 99;
        $boundaryMemory->events[99] = array_replace($memory->events[100], ['event_id' => 99]);
        $boundaryMemory->events[101] = $memory->events[101];
        $boundaryRecords = [];
        $boundaryCalls = 0;
        $boundaryStatus = mindPoisoningEvaluateReflectionReply(
            $boundaryRegistration,
            $ack,
            $boundaryStore,
            static fn(array $given, string $stage): bool => $given === $boundaryRegistration,
            static function (array $messages) use (&$boundaryCalls): string {
                $boundaryCalls++;
                return '{}';
            },
            captureRequestLog($boundaryRecords)
        );
        if (
            $registration['event_id'] <= 99 || $boundaryStatus !== 'restore-policy'
            || $boundaryCalls !== 0 || $boundaryMemory->beginCalls !== 0 || $boundaryMemory->history !== []
            || (lastRequestSummary($boundaryRecords)['reason'] ?? null) !== 'restore-policy-source-before-init'
        ) {
            throw new RuntimeException("A reflection-v2 reply with a post-INIT final line and an at-INIT earlier line was not refused before model/persistence; got {$boundaryStatus} after {$boundaryCalls} model calls.");
        }
        $calls = 0;
        $status = mindPoisoningEvaluateReflectionReply(
            $registration,
            $ack,
            $store,
            static fn(array $given, string $stage): bool => $given === $registration,
            static function (array $messages) use (&$calls): string {
                $calls++;
                return validModelResponse([[
                    'subject' => 'npc:22', 'delta' => 1, 'reason' => 'A full registered reply changes the actor view.',
                    'evidence' => 'Lydia betrayed me after I trusted her.',
                ]]);
            }
        );
        if ($status !== 'committed' || $calls !== 1 || count($memory->history) !== 1) {
            throw new RuntimeException("Expected an eligible multi-source reflection-v2 reply to commit; got {$status} after {$calls} model calls.");
        }
        $beforeRetry = serialize([
            $memory->npcs[11],
            $memory->history,
        ]);
        $retryCalls = 0;
        $retryStatus = mindPoisoningEvaluateReflectionReply(
            $registration,
            $ack,
            $store,
            static fn(array $given, string $stage): bool => $given === $registration,
            static function (array $messages) use (&$retryCalls): string {
                $retryCalls++;
                return '{}';
            }
        );
        if ($retryStatus !== 'duplicate' || $retryCalls !== 0 || serialize([$memory->npcs[11], $memory->history]) !== $beforeRetry) {
            throw new RuntimeException("An exact reflection-v2 retry was not deduplicated without another model call/write; got {$retryStatus} after {$retryCalls} model calls.");
        }
        echo "restore-policy reflection-v2 fence, commit, and exact retry checks passed\n";
        return;
    }

    if ($focus === '--focus-overhearing-commit') {
        [, $memory, $store, $ack] = restorePolicyFixture(101, '1', ['latest_init_event_id' => 99, 'max_event_id' => 101]);
        $memory->events[101]['people'] = '|Aela|Lydia|Inigo|';
        $memory->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff = 40;
        $memory->npcs[44] = [
            'id' => 44, 'npc_name' => 'Inigo', 'gamets_last_updated' => 0.0,
            'extended_data' => (object)['relationships' => (object)['Jarl Balgruuf' => (object)['aff' => 35, 'type' => 'neutral']]],
            'plugin_extended_data' => (object)['mind_poisoning' => (object)[
                'playthrough_id' => '1', 'floor_event_id' => 90, 'events' => [],
            ]],
        ];
        $GLOBALS['restore_policy_overhearing_enabled'] = true;
        $calls = 0;
        $requestModel = static function (array $messages) use (&$calls): string {
            $calls++;
            $payload = json_decode($messages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
            $judgments = [];
            foreach ($payload['recipients'] as $recipient) {
                foreach ($recipient['candidates'] as $subject => $candidate) {
                    $evidence = $subject === 'player' ? 'The Dragonborn is brave.' : 'I trust Jarl Balgruuf.';
                    $judgments[] = [
                        'listener_id' => $recipient['listener_id'],
                        'subject' => $subject,
                        'subject_mentioned' => true,
                        'delta' => $subject === 'npc:33' ? ($recipient['listener_id'] === 22 ? -1 : 2) : 0,
                        'reason' => 'Recipient-specific overheard assessment',
                        'evidence' => $evidence,
                    ];
                }
            }
            return json_encode(['judgments' => $judgments], JSON_THROW_ON_ERROR);
        };
        $beforeRetry = serialize([
            $memory->npcs[22],
            $memory->npcs[44],
            $memory->history,
        ]);
        $status = handleSpeechAck($ack, $store, $requestModel);
        $ledger22 = $memory->npcs[22]['plugin_extended_data']->mind_poisoning ?? null;
        $ledger44 = $memory->npcs[44]['plugin_extended_data']->mind_poisoning ?? null;
        if (
            $status !== 'committed' || $calls !== 1 || count($memory->history) !== 2
            || ($memory->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff ?? null) !== 39
            || ($memory->npcs[44]['extended_data']->relationships->{'Jarl Balgruuf'}->aff ?? null) !== 37
            || ($ledger22->events[0]->event_id ?? null) !== 101 || ($ledger44->events[0]->event_id ?? null) !== 101
            || ($ledger22->playthrough_id ?? null) !== '1' || ($ledger44->playthrough_id ?? null) !== '1'
        ) {
            throw new RuntimeException("Expected both same-scope overhearing recipients to commit independent judgments; got {$status} after {$calls} model calls.");
        }
        $afterCommit = serialize([$memory->npcs[22], $memory->npcs[44], $memory->history]);
        $retryStatus = handleSpeechAck($ack, $store, $requestModel);
        if ($retryStatus !== 'duplicate' || $calls !== 1 || serialize([$memory->npcs[22], $memory->npcs[44], $memory->history]) !== $afterCommit) {
            throw new RuntimeException("An exact overhearing ACK retry was not deduplicated without another provider call or score change; got {$retryStatus} after {$calls} model calls.");
        }
        if ($beforeRetry === $afterCommit) {
            throw new RuntimeException('The first overhearing evaluation did not change the persisted recipients.');
        }
        echo "restore-policy overhearing commit and exact retry checks passed\n";
        return;
    }

    if ($focus === '--focus-init-boundary-fail-closed') {
        [, $missingMemory, $missingStore, $missingAck] = restorePolicyFixture(101, '1', null);
        $missingCalls = 0;
        $missingRecords = [];
        $missingStatus = handleSpeechAck($missingAck, $missingStore, static function (array $messages) use (&$missingCalls): string {
            $missingCalls++;
            return '{}';
        }, captureRequestLog($missingRecords));
        $missingReason = lastRequestSummary($missingRecords)['reason'] ?? null;
        if (
            $missingStatus !== 'failed' || $missingCalls !== 0 || $missingMemory->beginCalls !== 0
            || ($missingStore->eventBoundsReads !== 1)
            || $missingReason !== 'restore-policy-boundary-unavailable'
        ) {
            throw new RuntimeException("A missing INIT marker was not refused before model/write with its specific reason; got {$missingStatus} / " . ($missingReason ?? 'no reason') . " after {$missingCalls} model calls.");
        }

        [, $fastMemory, $fastStore, $fastAck] = restorePolicyFixture(101, '1', ['latest_init_event_id' => 99, 'max_event_id' => 101]);
        $GLOBALS['all_fast_commands'] = ['_speech', 'init', 'request'];
        $fastCalls = 0;
        $fastRecords = [];
        $fastStatus = handleSpeechAck($fastAck, $fastStore, static function (array $messages) use (&$fastCalls): string {
            $fastCalls++;
            return '{}';
        }, captureRequestLog($fastRecords));
        $fastReason = lastRequestSummary($fastRecords)['reason'] ?? null;
        if (
            $fastStatus !== 'restore-policy' || $fastCalls !== 0 || $fastMemory->beginCalls !== 0
            || $fastStore->eventBoundsReads !== 0
            || $fastReason !== 'restore-policy-init-fast'
        ) {
            throw new RuntimeException("A fast-path INIT override was not refused before boundary read/model/write with its specific reason; got {$fastStatus} / " . ($fastReason ?? 'no reason') . " after {$fastCalls} model calls.");
        }
        echo "restore-policy missing-boundary and fast-INIT refusals passed\n";
        return;
    }

    if ($focus === '--focus-baseline-uncertain-retry') {
        $oldScopeLedger = ['playthrough_id' => '1', 'floor_event_id' => 90, 'events' => [
            ['event_id' => 98, 'utterance_id' => 'utt_old_scope_0001'],
        ]];
        [, $memory, $store, $ack] = restorePolicyFixture(100, '2', ['latest_init_event_id' => 99, 'max_event_id' => 100], $oldScopeLedger);
        $store->commitPersistsButReportsFailure = true;
        $beforeRelations = json_encode($memory->npcs[22]['extended_data']->relationships, JSON_THROW_ON_ERROR);
        $records = [];
        $calls = 0;
        $status = handleSpeechAck($ack, $store, static function (array $messages) use (&$calls): string {
            $calls++;
            return '{}';
        }, captureRequestLog($records));
        $summary = lastRequestSummary($records);
        $ledger = $memory->npcs[22]['plugin_extended_data']->mind_poisoning ?? null;
        if (
            $status !== 'failed' || $calls !== 0
            || ($summary['reason'] ?? null) !== 'restore-policy-baseline-unconfirmed'
            || ($summary['commit_state'] ?? null) !== 'unconfirmed'
            || ($summary['committed'] ?? null) !== false
            || ($ledger->playthrough_id ?? null) !== '2' || ($ledger->floor_event_id ?? null) !== 100
            || ($ledger->events ?? null) !== [] || count($memory->history) !== 1
            || json_encode($memory->npcs[22]['extended_data']->relationships, JSON_THROW_ON_ERROR) !== $beforeRelations
        ) {
            throw new RuntimeException("A baseline commit that persisted but reported failure did not preserve its uncertain outcome; got {$status}, reason " . ($summary['reason'] ?? 'none') . '.');
        }

        $retryRecords = [];
        $retryStatus = handleSpeechAck($ack, $store, static function (array $messages) use (&$calls): string {
            $calls++;
            return validModelResponse([
                ['subject' => 'npc:33', 'delta' => 5, 'reason' => 'Would be a repeated score', 'evidence' => 'I trust Jarl Balgruuf.'],
                ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change', 'evidence' => 'The Dragonborn is brave.'],
            ]);
        }, captureRequestLog($retryRecords));
        $retrySummary = lastRequestSummary($retryRecords);
        if (
            $retryStatus !== 'duplicate' || $calls !== 0
            || ($retrySummary['reason'] ?? null) !== 'ledger-floor'
            || ($memory->npcs[22]['plugin_extended_data']->mind_poisoning->playthrough_id ?? null) !== '2'
            || ($memory->npcs[22]['plugin_extended_data']->mind_poisoning->floor_event_id ?? null) !== 100
            || count($memory->history) !== 1
            || json_encode($memory->npcs[22]['extended_data']->relationships, JSON_THROW_ON_ERROR) !== $beforeRelations
        ) {
            throw new RuntimeException("The triggering source after an uncertain baseline commit was not blocked by the persisted floor; got {$retryStatus} after {$calls} model calls.");
        }
        echo "restore-policy uncertain baseline commit and old-source retry checks passed\n";
        return;
    }

    if ($focus === '--focus-overhearing-boundary-red') {
        [, $memory, $store, $ack] = restorePolicyFixture(100, '1', ['latest_init_event_id' => 100, 'max_event_id' => 100]);
        $memory->events[100]['people'] = '|Aela|Lydia|Inigo|';
        $memory->npcs[44] = [
            'id' => 44, 'npc_name' => 'Inigo', 'gamets_last_updated' => 0.0,
            'extended_data' => (object)['relationships' => new \stdClass()],
            'plugin_extended_data' => new \stdClass(),
        ];
        $GLOBALS['restore_policy_overhearing_enabled'] = true;
        $calls = 0;
        $records = [];
        $status = handleSpeechAck($ack, $store, static function (array $messages) use (&$calls): string {
            $calls++;
            return '{}';
        }, captureRequestLog($records));
        if (
            $status !== 'restore-policy' || $calls !== 0
            || (lastRequestSummary($records)['reason'] ?? null) !== 'restore-policy-source-before-init'
            || $memory->history !== []
        ) {
            throw new RuntimeException("Expected an overhearing source at INIT to be rejected before model work; got {$status} after {$calls} model calls.");
        }
        echo "restore-policy overhearing boundary rejected before model\n";
        return;
    }

    if ($focus === '--focus-overhearing-baseline-red') {
        $oldScopeLedger = ['playthrough_id' => '1', 'floor_event_id' => 90, 'events' => [
            ['event_id' => 98, 'utterance_id' => 'utt_overhear_old_scope'],
        ]];
        [, $memory, $store, $ack] = restorePolicyFixture(101, '2', ['latest_init_event_id' => 99, 'max_event_id' => 101], $oldScopeLedger);
        $memory->events[101]['people'] = '|Aela|Lydia|Inigo|';
        $memory->npcs[44] = [
            'id' => 44, 'npc_name' => 'Inigo', 'gamets_last_updated' => 0.0,
            'extended_data' => (object)['relationships' => (object)['Jarl Balgruuf' => (object)['aff' => 35, 'type' => 'neutral']]],
            'plugin_extended_data' => (object)['mind_poisoning' => json_decode(json_encode($oldScopeLedger, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR)],
        ];
        $GLOBALS['restore_policy_overhearing_enabled'] = true;
        $beforeRelations = [
            22 => json_encode($memory->npcs[22]['extended_data']->relationships, JSON_THROW_ON_ERROR),
            44 => json_encode($memory->npcs[44]['extended_data']->relationships, JSON_THROW_ON_ERROR),
        ];
        $calls = 0;
        $records = [];
        $status = handleSpeechAck($ack, $store, static function (array $messages) use (&$calls): string {
            $calls++;
            return '{}';
        }, captureRequestLog($records));
        $ledger22 = $memory->npcs[22]['plugin_extended_data']->mind_poisoning ?? null;
        $ledger44 = $memory->npcs[44]['plugin_extended_data']->mind_poisoning ?? null;
        if (
            $status !== 'restore-policy' || $calls !== 0
            || ($ledger22->playthrough_id ?? null) !== '2' || ($ledger22->floor_event_id ?? null) !== 101
            || ($ledger44->playthrough_id ?? null) !== '2' || ($ledger44->floor_event_id ?? null) !== 101
            || $memory->history === []
            || json_encode($memory->npcs[22]['extended_data']->relationships, JSON_THROW_ON_ERROR) !== $beforeRelations[22]
            || json_encode($memory->npcs[44]['extended_data']->relationships, JSON_THROW_ON_ERROR) !== $beforeRelations[44]
        ) {
            throw new RuntimeException("Expected both overhearing recipients to baseline before the triggering event; got {$status} after {$calls} model calls.");
        }
        echo "restore-policy overhearing recipients baselined before model\n";
        return;
    }

    if ($focus === '--focus-database-row-id-red') {
        [, $memory, $store, $ack] = restorePolicyFixture(101, '1', ['latest_init_event_id' => 100, 'max_event_id' => 101]);
        $memory->npcs[22]['id'] = '22';
        $calls = 0;
        $records = [];
        $status = handleSpeechAck($ack, $store, static function (array $messages) use (&$calls): string {
            $calls++;
            return validModelResponse([
                ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Trusting claim', 'evidence' => 'I trust Jarl Balgruuf.'],
                ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change', 'evidence' => 'The Dragonborn is brave.'],
            ]);
        }, captureRequestLog($records));
        if ($status !== 'committed' || $calls !== 1 || count($memory->history) !== 1) {
            $reason = lastRequestSummary($records)['reason'] ?? 'no-summary';
            throw new RuntimeException("An eligible restore-mode ACK with a PostgreSQL-style string NPC id did not commit; got {$status} after {$calls} model calls ({$reason}).");
        }
        echo "restore-policy database row id normalized\n";
        return;
    }

    throw new RuntimeException('Unknown focused restore-policy route: ' . $focus);
}

$fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mp_restore_policy_' . bin2hex(random_bytes(8));
if (!mkdir($fixtureRoot, 0700, true) || file_put_contents($fixtureRoot . DIRECTORY_SEPARATOR . 'main.php', "<?php\n") === false) {
    throw new RuntimeException('Could not create the isolated CHIM root fixture.');
}

try {
    $GLOBALS['ENGINE_PATH'] = $fixtureRoot . DIRECTORY_SEPARATOR;
    $GLOBALS['runtime_test_interaction_allowed'] = true;
    $GLOBALS['runtime_test_relationship_enabled'] = true;
    $GLOBALS['RELLLM_CONNECTOR'] = 7;
    $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = true;
    $GLOBALS['all_fast_commands'] = ['_speech', 'request'];

    $focus = $argv[1] ?? '';
    if ($focus !== '') {
        restorePolicyRunFocus($focus);
    } else {
    $priorLedger = [
        'playthrough_id' => '1',
        'floor_event_id' => 90,
        'events' => [
            ['event_id' => 98, 'utterance_id' => 'utt_before_init_0001', 'judgments' => [
                ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'PRE_INIT_REASON_SENTINEL', 'evidence' => 'PRE_INIT_EVIDENCE_SENTINEL'],
            ]],
            ['event_id' => 99, 'utterance_id' => 'utt_at_init_00001', 'judgments' => [
                ['subject' => 'npc:33', 'delta' => -1, 'reason' => 'AT_INIT_REASON_SENTINEL', 'evidence' => 'AT_INIT_EVIDENCE_SENTINEL'],
            ]],
            ['event_id' => 100, 'utterance_id' => 'utt_after_init_0001', 'judgments' => [
                ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'POST_INIT_REASON_SENTINEL', 'evidence' => 'POST_INIT_EVIDENCE_SENTINEL'],
            ]],
        ],
    ];
    [$event, $memoryDb, $db, $ack] = restorePolicyFixture(
        101,
        '1',
        ['latest_init_event_id' => 99, 'max_event_id' => 101],
        $priorLedger
    );
    $modelCalls = 0;
    $modelMessages = null;
    $requestRecords = [];
    $status = handleSpeechAck($ack, $db, static function (array $messages) use (&$modelCalls, &$modelMessages): string {
        $modelCalls++;
        $modelMessages = $messages;
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => 2, 'reason' => 'Trusting claim', 'evidence' => 'I trust Jarl Balgruuf.'],
            ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change', 'evidence' => 'The Dragonborn is brave.'],
        ]);
    }, captureRequestLog($requestRecords));

    if ($status !== 'committed') {
        throw new RuntimeException("Expected an eligible restore-mode ACK to commit; got {$status}.");
    }
    if ($modelCalls !== 1 || $memoryDb->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff !== 100 || count($memoryDb->history) !== 1) {
        throw new RuntimeException('The eligible restore-mode ACK did not reach model evaluation and persistence.');
    }
    $summary = lastRequestSummary($requestRecords);
    if (($summary['reason'] ?? null) !== 'committed' || ($db->barrierAcquisitions !== 1 || $db->barrierReleases !== 1)) {
        throw new RuntimeException('The ACK did not acquire and release its restore-policy barrier.');
    }
    $modelPayload = json_decode($modelMessages[1]['content'] ?? '', true, 512, JSON_THROW_ON_ERROR)['untrusted_data'] ?? [];
    if (
        count($modelPayload['prior_judgments'] ?? []) !== 1
        || ($modelPayload['prior_judgments'][0]['event_id'] ?? null) !== 100
        || str_contains(json_encode($modelPayload, JSON_THROW_ON_ERROR), 'PRE_INIT_')
        || str_contains(json_encode($modelPayload, JSON_THROW_ON_ERROR), 'AT_INIT_')
        || !str_contains(json_encode($modelPayload, JSON_THROW_ON_ERROR), 'POST_INIT_REASON_SENTINEL')
        || ($modelPayload['candidates']['npc:33']['listener_prior_relation']['note'] ?? null) !== 'existing'
        || ($memoryDb->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->note ?? null) !== 'existing'
    ) {
        throw new RuntimeException('The restore boundary did not filter old judgment context while preserving relationship notes.');
    }

    $retryCalls = 0;
    if (handleSpeechAck($ack, $db, static function (array $messages) use (&$retryCalls): string {
        $retryCalls++;
        return '{}';
    }) !== 'duplicate' || $retryCalls !== 0 || count($memoryDb->history) !== 1) {
        throw new RuntimeException('An exact post-restore ACK retry was not stopped before a second model call or snapshot.');
    }

    $retell = $event;
    $retell['event_id'] = 102;
    $retell['utterance_id'] = 'utt_restore_00000102';
    $memoryDb->events[102] = $retell + ['type' => 'chat', 'delivery_state' => 'spoken'];
    $db->eventBounds = ['latest_init_event_id' => 99, 'max_event_id' => 102];
    $retellAck = ['_speech', 0, 10, json_encode([
        'speaker' => 'Aela', 'listener' => 'Lydia', 'speech' => $retell['text'],
        'utterance_id' => $retell['utterance_id'],
    ], JSON_THROW_ON_ERROR)];
    $retellCalls = 0;
    if (handleSpeechAck($retellAck, $db, static function (array $messages) use (&$retellCalls): string {
        $retellCalls++;
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => 0, 'reason' => 'Repeated evidence', 'evidence' => 'I trust Jarl Balgruuf.'],
            ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change', 'evidence' => 'The Dragonborn is brave.'],
        ]);
    }) !== 'committed' || $retellCalls !== 1) {
        throw new RuntimeException('A new post-INIT event ID for repeated speech was not evaluated and ledgered.');
    }

    [$oldEvent, $oldMemory, $oldDb, $oldAck] = restorePolicyFixture(
        99,
        '1',
        ['latest_init_event_id' => 99, 'max_event_id' => 100]
    );
    $oldCalls = 0;
    $oldRecords = [];
    if (handleSpeechAck($oldAck, $oldDb, static function (array $messages) use (&$oldCalls): string {
        $oldCalls++;
        return '{}';
    }, captureRequestLog($oldRecords)) !== 'restore-policy'
        || $oldCalls !== 0 || $oldMemory->beginCalls !== 0 || count($oldMemory->history) !== 0
        || (lastRequestSummary($oldRecords)['reason'] ?? null) !== 'restore-policy-source-before-init') {
        throw new RuntimeException('A source at the INIT boundary was not rejected before model evaluation and persistence.');
    }

    [$changingEvent, $changingMemory, $changingDb, $changingAck] = restorePolicyFixture(
        100,
        '1',
        ['latest_init_event_id' => 99, 'max_event_id' => 100]
    );
    $changingCalls = 0;
    $changingRecords = [];
    $changingStatus = handleSpeechAck($changingAck, $changingDb, static function (array $messages) use (&$changingCalls, $changingDb): string {
        $changingCalls++;
        $changingDb->eventBounds = ['latest_init_event_id' => 100, 'max_event_id' => 100];
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => 2, 'reason' => 'Trusting claim', 'evidence' => 'I trust Jarl Balgruuf.'],
            ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change', 'evidence' => 'The Dragonborn is brave.'],
        ]);
    }, captureRequestLog($changingRecords));
    $changingPersistence = array_values(array_filter(
        $changingRecords,
        static fn(array $record): bool => ($record['event'] ?? null) === 'persistence_finished'
    ));
    $changingPersistenceReason = $changingPersistence === []
        ? null
        : ($changingPersistence[array_key_last($changingPersistence)]['persistence_reason'] ?? null);
    if ($changingStatus !== 'stale' || $changingCalls !== 1
        || $changingMemory->history !== []
        || $changingPersistenceReason !== 'restore-policy-init-changed') {
        $reason = lastRequestSummary($changingRecords)['reason'] ?? 'no-summary';
        throw new RuntimeException("An INIT marker change during model work did not prevent persistence; got {$changingStatus} after {$changingCalls} model calls (summary {$reason}, persistence {$changingPersistenceReason}).");
    }

    [$lockedEvent, $lockedMemory, $lockedDb, $lockedAck] = restorePolicyFixture(
        100,
        '1',
        ['latest_init_event_id' => 99, 'max_event_id' => 100]
    );
    $lockedDb->barrierAcquisition = false;
    $lockedCalls = 0;
    $lockedBefore = serialize($lockedMemory->npcs[22]);
    $lockedRecords = [];
    $lockedStatus = handleSpeechAck($lockedAck, $lockedDb, static function (array $messages) use (&$lockedCalls): string {
        $lockedCalls++;
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => 2, 'reason' => 'Trusting claim', 'evidence' => 'I trust Jarl Balgruuf.'],
            ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change', 'evidence' => 'The Dragonborn is brave.'],
        ]);
    }, captureRequestLog($lockedRecords));
    $lockedPersistence = array_values(array_filter(
        $lockedRecords,
        static fn(array $record): bool => ($record['event'] ?? null) === 'persistence_finished'
    ));
    $lockedPersistenceReason = $lockedPersistence === []
        ? null
        : ($lockedPersistence[array_key_last($lockedPersistence)]['persistence_reason'] ?? null);
    if ($lockedStatus !== 'restore-policy'
        || $lockedCalls !== 1 || $lockedMemory->history !== []
        || serialize($lockedMemory->npcs[22]) !== $lockedBefore
        || $lockedPersistenceReason !== 'restore-policy-lock-unavailable') {
        throw new RuntimeException("A failed persistence-time MAIN barrier did not prevent the post-model write (status {$lockedStatus}, model {$lockedCalls}, persistence {$lockedPersistenceReason}).");
    }

    $wrongScopeLedger = [
        'playthrough_id' => '1', 'floor_event_id' => 90,
        'events' => [['event_id' => 98, 'utterance_id' => 'utt_old_scope_0001']],
        'reflection_state' => ['basis' => str_repeat('a', 64), 'subjects' => ['npc:33']],
    ];
    [$scopeEvent, $scopeMemory, $scopeDb, $scopeAck] = restorePolicyFixture(
        100,
        '2',
        ['latest_init_event_id' => 99, 'max_event_id' => 100],
        $wrongScopeLedger
    );
    $beforeScopeRelations = json_encode($scopeMemory->npcs[22]['extended_data']->relationships, JSON_THROW_ON_ERROR);
    $scopeCalls = 0;
    $scopeRecords = [];
    if (handleSpeechAck($scopeAck, $scopeDb, static function (array $messages) use (&$scopeCalls): string {
        $scopeCalls++;
        return '{}';
    }, captureRequestLog($scopeRecords)) !== 'restore-policy' || $scopeCalls !== 0) {
        throw new RuntimeException('A playthrough mismatch should baseline and skip its triggering event.');
    }
    $scopeSummary = lastRequestSummary($scopeRecords);
    $scopeLedger = $scopeMemory->npcs[22]['plugin_extended_data']->mind_poisoning;
    if (
        $scopeCalls !== 0 || ($scopeSummary['reason'] ?? null) !== 'restore-policy-scope-baselined'
        || ($scopeSummary['commit_state'] ?? null) !== 'confirmed'
        || ($scopeLedger->playthrough_id ?? null) !== '2' || ($scopeLedger->floor_event_id ?? null) !== 100
        || ($scopeLedger->events ?? null) !== [] || count($scopeMemory->history) !== 1
        || json_encode($scopeMemory->npcs[22]['extended_data']->relationships, JSON_THROW_ON_ERROR) !== $beforeScopeRelations
    ) {
        throw new RuntimeException('A playthrough mismatch did not atomically baseline the ledger while preserving relationships.');
    }

    $afterBaseline = $scopeEvent;
    $afterBaseline['event_id'] = 101;
    $afterBaseline['utterance_id'] = 'utt_restore_00000101';
    $afterBaseline['playthrough_id'] = '2';
    $scopeMemory->events[101] = $afterBaseline + ['type' => 'chat', 'delivery_state' => 'spoken'];
    $scopeDb->eventBounds = ['latest_init_event_id' => 99, 'max_event_id' => 101];
    $afterBaselineAck = ['_speech', 0, 10, json_encode([
        'speaker' => 'Aela', 'listener' => 'Lydia', 'speech' => $afterBaseline['text'],
        'utterance_id' => $afterBaseline['utterance_id'],
    ], JSON_THROW_ON_ERROR)];
    $afterBaselineCalls = 0;
    if (handleSpeechAck($afterBaselineAck, $scopeDb, static function (array $messages) use (&$afterBaselineCalls): string {
        $afterBaselineCalls++;
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'New scoped evidence', 'evidence' => 'I trust Jarl Balgruuf.'],
            ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change', 'evidence' => 'The Dragonborn is brave.'],
        ]);
    }) !== 'committed' || $afterBaselineCalls !== 1) {
        throw new RuntimeException('A post-baseline event ID above the new floor was not eligible.');
    }

    [$failedEvent, $failedMemory, $failedDb, $failedAck] = restorePolicyFixture(
        100,
        '2',
        ['latest_init_event_id' => 99, 'max_event_id' => 100],
        $wrongScopeLedger
    );
    $failedMemory->failCommit = true;
    $failedRecords = [];
    $failedCalls = 0;
    handleSpeechAck($failedAck, $failedDb, static function (array $messages) use (&$failedCalls): string {
        $failedCalls++;
        return '{}';
    }, captureRequestLog($failedRecords));
    $failedSummary = lastRequestSummary($failedRecords);
    if (
        $failedCalls !== 0 || ($failedSummary['commit_state'] ?? null) !== 'unconfirmed'
        || $failedMemory->history !== []
        || ($failedMemory->npcs[22]['plugin_extended_data']->mind_poisoning->playthrough_id ?? null) !== '1'
    ) {
        throw new RuntimeException('A failed scope-baseline commit was not rolled back without running the model.');
    }

    [$partialEvent, $partialMemory, $partialDb, $partialAck] = restorePolicyFixture(
        100,
        '2',
        ['latest_init_event_id' => 99, 'max_event_id' => 100],
        $wrongScopeLedger
    );
    $partialDb->throwAfterBegin = true;
    $partialBefore = serialize($partialMemory->npcs[22]);
    $partialRecords = [];
    $partialCalls = 0;
    handleSpeechAck($partialAck, $partialDb, static function (array $messages) use (&$partialCalls): string {
        $partialCalls++;
        return '{}';
    }, captureRequestLog($partialRecords));
    if (
        $partialCalls !== 0 || $partialDb->rollbackCalls !== 1 || $partialDb->releaseCalls !== 1
        || $partialDb->barrierReleases !== 1 || $partialMemory->history !== []
        || serialize($partialMemory->npcs[22]) !== $partialBefore
    ) {
        throw new RuntimeException('A partial baseline transaction was not rolled back and released with MAIN.');
    }

    [$cleanupEvent, $cleanupMemory, $cleanupDb, $cleanupAck] = restorePolicyFixture(
        100,
        '2',
        ['latest_init_event_id' => 99, 'max_event_id' => 100],
        $wrongScopeLedger
    );
    $cleanupDb->releaseThrows = true;
    $cleanupBeforeRelations = json_encode($cleanupMemory->npcs[22]['extended_data']->relationships, JSON_THROW_ON_ERROR);
    $cleanupRecords = [];
    $cleanupCalls = 0;
    $cleanupStatus = handleSpeechAck($cleanupAck, $cleanupDb, static function (array $messages) use (&$cleanupCalls): string {
        $cleanupCalls++;
        return '{}';
    }, captureRequestLog($cleanupRecords));
    $cleanupSummary = lastRequestSummary($cleanupRecords);
    $cleanupReason = $cleanupSummary['reason'] ?? null;
    $cleanupLedger = $cleanupMemory->npcs[22]['plugin_extended_data']->mind_poisoning;
    if (
        $cleanupStatus !== 'failed' || $cleanupCalls !== 0
        || $cleanupReason !== 'restore-policy-baseline-cleanup-failed'
        || ($cleanupSummary['commit_state'] ?? null) !== 'confirmed'
        || ($cleanupSummary['committed'] ?? null) !== true
        || ($cleanupLedger->playthrough_id ?? null) !== '2'
        || ($cleanupLedger->floor_event_id ?? null) !== 100
        || ($cleanupLedger->events ?? null) !== [] || count($cleanupMemory->history) !== 1
        || json_encode($cleanupMemory->npcs[22]['extended_data']->relationships, JSON_THROW_ON_ERROR) !== $cleanupBeforeRelations
    ) {
        throw new RuntimeException('A confirmed baseline followed by cleanup failure did not retain its committed state and report the failure accurately.');
    }

    [, $playerMemory, $playerStore] = restorePolicyFixture(
        884,
        '1',
        ['latest_init_event_id' => 883, 'max_event_id' => 884]
    );
    [$playerRequest, $playerInsert] = playerInputFixture($playerMemory);
    $playerCalls = 0;
    if (handlePlayerInput($playerRequest, $playerInsert, $playerStore, static function (array $messages) use (&$playerCalls): string {
        $playerCalls++;
        return validModelResponse([['subject' => 'npc:33', 'delta' => 1, 'reason' => 'A credible defense', 'evidence' => 'kept his promise']]);
    }) !== 'committed' || $playerCalls !== 1 || count($playerMemory->history) !== 1) {
        throw new RuntimeException('A valid routed Player event was not evaluated and persisted after INIT.');
    }

    [, $typeMemory, $typeDb, $typeAck] = restorePolicyFixture(
        100,
        '1',
        ['latest_init_event_id' => 99, 'max_event_id' => 100]
    );
    $typeMemory->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 'neutral';
    $typeCalls = 0;
    if (handleSpeechAck($typeAck, $typeDb, static function (array $messages) use (&$typeCalls): string {
        $typeCalls++;
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => 0, 'reason' => 'Lasting change', 'evidence' => 'I trust Jarl Balgruuf.', 'type_change' => ['from' => 'neutral', 'to' => 'suspicious']],
            ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change', 'evidence' => 'The Dragonborn is brave.'],
        ]);
    }) !== 'committed' || $typeCalls !== 1
        || $typeMemory->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff !== 99
        || $typeMemory->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type !== 'suspicious'
        || count($typeMemory->history) !== 1) {
        throw new RuntimeException('A zero-delta/type-only judgment did not persist under restore policy.');
    }

    [$offEvent, $offMemory, $offDb, $offAck] = restorePolicyFixture(
        120,
        '1',
        ['latest_init_event_id' => 99, 'max_event_id' => 120]
    );
    $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = false;
    $offCalls = 0;
    if (handleSpeechAck($offAck, $offDb, static function (array $messages) use (&$offCalls): string {
        $offCalls++;
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Trusting claim', 'evidence' => 'I trust Jarl Balgruuf.'],
            ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change', 'evidence' => 'The Dragonborn is brave.'],
        ]);
    }) !== 'committed' || $offCalls !== 1 || $offDb->eventBoundsReads !== 0
        || $offDb->barrierAcquisitions !== 0 || $offDb->barrierReleases !== 0 || count($offMemory->history) !== 1) {
        throw new RuntimeException('Disabled restore policy read the optional boundary or acquired its MAIN barrier.');
    }

    [$transitionEvent, $transitionMemory, $transitionDb, $transitionAck] = restorePolicyFixture(
        200,
        '1',
        ['latest_init_event_id' => 199, 'max_event_id' => 200]
    );
    $transitionModel = static function (array $messages): string {
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Trusting claim', 'evidence' => 'I trust Jarl Balgruuf.'],
            ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change', 'evidence' => 'The Dragonborn is brave.'],
        ]);
    };
    $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = false;
    if (handleSpeechAck($transitionAck, $transitionDb, $transitionModel) !== 'committed' || $transitionDb->eventBoundsReads !== 0) {
        throw new RuntimeException('The restore-policy disabled phase did not retain the legacy path.');
    }

    $transitionEvent['event_id'] = 201;
    $transitionEvent['utterance_id'] = 'utt_restore_00000201';
    $transitionMemory->events[201] = $transitionEvent + ['type' => 'chat', 'delivery_state' => 'spoken'];
    $transitionDb->eventBounds = ['latest_init_event_id' => 200, 'max_event_id' => 201];
    $transitionAck = ['_speech', 0, 10, json_encode([
        'speaker' => 'Aela', 'listener' => 'Lydia', 'speech' => $transitionEvent['text'],
        'utterance_id' => $transitionEvent['utterance_id'],
    ], JSON_THROW_ON_ERROR)];
    $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = true;
    $transitionEnabledStatus = handleSpeechAck($transitionAck, $transitionDb, $transitionModel);
    $transitionBoundaryReads = $transitionDb->eventBoundsReads;
    if ($transitionEnabledStatus !== 'committed' || $transitionBoundaryReads === 0) {
        throw new RuntimeException("The restore-policy enabled phase did not observe its INIT boundary (status {$transitionEnabledStatus}, bounds reads {$transitionBoundaryReads}).");
    }

    $transitionEvent['event_id'] = 202;
    $transitionEvent['utterance_id'] = 'utt_restore_00000202';
    $transitionMemory->events[202] = $transitionEvent + ['type' => 'chat', 'delivery_state' => 'spoken'];
    $transitionAck = ['_speech', 0, 10, json_encode([
        'speaker' => 'Aela', 'listener' => 'Lydia', 'speech' => $transitionEvent['text'],
        'utterance_id' => $transitionEvent['utterance_id'],
    ], JSON_THROW_ON_ERROR)];
    $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = false;
    if (handleSpeechAck($transitionAck, $transitionDb, $transitionModel) !== 'committed') {
        throw new RuntimeException('Turning restore policy off did not retain the observed ledger.');
    }
    $transitionLedger = $transitionMemory->npcs[22]['plugin_extended_data']->mind_poisoning;
    $transitionIds = array_map(static fn(stdClass $entry): int => $entry->event_id, $transitionLedger->events ?? []);
    if (
        $transitionDb->eventBoundsReads !== $transitionBoundaryReads || $transitionDb->barrierAcquisitions !== 1
        || $transitionIds !== [200, 201, 202] || ($transitionLedger->playthrough_id ?? null) !== '1'
    ) {
        throw new RuntimeException('A false-to-true-to-false transition changed or failed to retain the active playthrough ledger.');
    }

    [$hotEvent, $hotMemory, $hotDb, $hotAck] = restorePolicyFixture(
        300,
        '1',
        ['latest_init_event_id' => 299, 'max_event_id' => 300]
    );
    $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = true;
    $hotBefore = serialize($hotMemory->npcs[22]);
    $hotCalls = 0;
    $hotRecords = [];
    $hotStatus = handleSpeechAck($hotAck, $hotDb, static function (array $messages) use (&$hotCalls): string {
        $hotCalls++;
        $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = false;
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Trusting claim', 'evidence' => 'I trust Jarl Balgruuf.'],
            ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change', 'evidence' => 'The Dragonborn is brave.'],
        ]);
    }, captureRequestLog($hotRecords));
    $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = true;
    $hotPersistence = array_values(array_filter(
        $hotRecords,
        static fn(array $record): bool => ($record['event'] ?? null) === 'persistence_finished'
    ));
    $hotPersistenceReason = $hotPersistence === []
        ? null
        : ($hotPersistence[array_key_last($hotPersistence)]['persistence_reason'] ?? null);
    if (
        $hotStatus !== 'restore-policy' || $hotCalls !== 1 || $hotMemory->history !== []
        || serialize($hotMemory->npcs[22]) !== $hotBefore || $hotDb->barrierAcquisitions !== 0
        || $hotPersistenceReason !== 'restore-policy-changed'
    ) {
        throw new RuntimeException("A request that captured the restore boundary committed after the policy changed off (status {$hotStatus}, persistence {$hotPersistenceReason}).");
    }

    }
    echo "restore-policy ACK accepted\n";
} finally {
    unset($GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'], $GLOBALS['runtime_test_interaction_allowed'], $GLOBALS['runtime_test_relationship_enabled'], $GLOBALS['RELLLM_CONNECTOR'], $GLOBALS['all_fast_commands'], $GLOBALS['CHIM_EXECUTION_MODE'], $GLOBALS['PLAYER_TTS_SOURCE_TEXT'], $GLOBALS['restore_policy_overhearing_enabled']);
    @unlink($fixtureRoot . DIRECTORY_SEPARATOR . 'main.php');
    @rmdir($fixtureRoot);
}
