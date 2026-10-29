<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
$pcvRoot = 'K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev';
$mpRoot = 'K:/ActorwrightExchange/artifacts/mp-release-0.1.23/source-tag';

// Minimal CHIM core parser contracts used by the pinned MP source parser. No CHIM request code is loaded.
function extractSpeakerNameFromChatEvent($eventData): string
{
    return preg_match('/^\s*([^:]{1,128})\s*:/u', (string)$eventData, $matches) === 1
        ? trim($matches[1]) : '';
}
function extractTalkTargetMetadata($eventData): array
{
    if (!preg_match('/\(\s*(?:(?:talking|whispering|shouting)\s+to|speaking\s+(?:loudly|privately)\s+to)\s+([^()]+?)(?:\s+from\s+far\s+away)?\s*\)/i',
        (string)$eventData, $matches)) {
        return ['hasExplicitTarget' => false, 'isBroadcast' => false, 'targets' => []];
    }
    $hint = trim($matches[1]);
    return ['hasExplicitTarget' => true, 'isBroadcast' => strcasecmp($hint, 'everyone') === 0,
        'targets' => strcasecmp($hint, 'everyone') === 0 ? [] : [$hint]];
}

require_once $mpRoot . '/server/reflection.php';
require_once $pcvRoot . '/server/reflection.php';

final class OfflineReflectionStore implements \ChimMindPoisoning\StoreDb
{
    /** @param array<string, array> $events */
    public function __construct(private array $events) {}
    public function activePlaythrough(): ?array { return ['id' => 'fixture-playthrough']; }
    public function acknowledgedEvent(string $utteranceId): ?array { return $this->events[$utteranceId] ?? null; }
    public function playerInputEvent(array $source): ?array { return null; }
    public function eventById(int $eventId, string $utteranceId): ?array
    {
        $event = $this->events[$utteranceId] ?? null;
        return is_array($event) && ($event['event_id'] ?? null) === $eventId ? $event : null;
    }
    public function npcIdentities(): array { return []; }
    public function npcById(int $npcId, bool $forUpdate = false): ?array { return null; }
    public function reflectionHistory(string $actorName, int $beforeEventId): array { return []; }
    public function beginForListener(int $listenerId): bool { return false; }
    public function writeNpc(int $npcId, array $relationshipEdges, object $mindPoisoningData, float $gamets,
        array $relationshipKeysToRemove = []): bool { return false; }
    public function backupAndVerify(int $npcId, array $expected): bool { return false; }
    public function commit(): bool { return false; }
    public function rollback(): void {}
    public function release(): void {}
}

function chimInteractionBegin(): void
{
    $GLOBALS['chim_interaction_generation'] ??= 17;
}
function chimInteractionState(): array { return ['enabled' => true, 'generation' => 17]; }

function requireFixture(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

$key = str_repeat('a', 64);
$configId = '123e4567-e89b-42d3-a456-426614174000';
$actorId = '11';
$actorName = 'Aela';
$generation = 17;
$GLOBALS['chim_interaction_generation'] = $generation;
$GLOBALS['HERIKA_NAME'] = $actorName;
$GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
$GLOBALS['gameRequest'] = ['inputtext', '1900000000000001', '', 'What do you think?'];

$root = __DIR__ . '/scratch-' . bin2hex(random_bytes(8));
$stateDirectory = $root . '/state';
requireFixture(mkdir($stateDirectory, 0700, true), 'Could not create scratch state directory.');

try {
    $now = time();
    $state = pcv_empty_store($key);
    $state['active'] = [
        'config' => ['enabled' => true, 'scene_mode' => 'solo', 'actor_a' => $actorId,
            'actor_b' => null, 'exclude_player' => true, 'bystander_mode' => 'exclude'],
        'config_id' => $configId, 'activated_at' => $now, 'expires_at' => $now + PCV_ACTIVE_TTL,
    ];
    $stateLock = pcv_lock_state($stateDirectory, true, LOCK_EX);
    try { pcv_write_store($stateDirectory, $state); } finally { pcv_unlock_state($stateLock); }

    $scope = ['status' => 'active', 'pcv_key' => $key, 'config_id' => $configId, 'actor_a_id' => $actorId,
        'scope' => ['scene_mode' => 'solo', 'actor_a' => $actorName, 'actor_b' => null, 'exclude_player' => true]];
    $freshScopeReader = static fn(): array => $scope;
    $events = [];
    $rows = [];
    $texts = [];
    for ($line = 1; $line <= 13; $line++) {
        $id = sprintf('utt_capacity_line_%04d', $line);
        $text = sprintf('Aela reflection segment %02d.', $line);
        $texts[$id] = $text;
        $eventId = 200 + $line;
        $events[$id] = ['event_id' => $eventId, 'utterance_id' => $id, 'delivery_state' => 'emitted',
            'source_data' => "$actorName: $text (talking to explicit_disable_rechat)", 'gamets' => 292500000,
            'type' => 'chat'];
        $rows[] = ['rowid' => $eventId, 'type' => 'chat', 'utterance_id' => $id];
    }
    $store = new OfflineReflectionStore($events);
    $receiptKinds = [];
    foreach ($texts as $id => $text) {
        $gameRequest = ['_speech', '', '', json_encode(['speaker' => $actorName, 'listener' => 'Dragonborn',
            'speech' => $text, 'utterance_id' => $id], JSON_THROW_ON_ERROR)];
        $tuple = pcv_reflection_ack_tuple($gameRequest);
        requireFixture(is_array($tuple), 'The production ACK tuple parser rejected a fixture ACK.');
        $receiptKinds[] = [$id, pcv_reflection_store_ack_receipt($tuple, $scope, $generation, $stateDirectory)['kind'] ?? 'missing'];
    }
    $preRegistrationLedger = pcv_reflection_read_receipts_locked($stateDirectory);
    $receiptPath = $stateDirectory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
    echo 'ack_capture=' . json_encode(array_column($receiptKinds, 1), JSON_THROW_ON_ERROR)
        . ' ledger_kind=' . ($preRegistrationLedger['kind'] ?? 'missing')
        . ' ledger_count=' . count($preRegistrationLedger['receipts'] ?? [])
        . ' file_mode=' . decoct((int)@fileperms($receiptPath)) . "\n";

    $finalId = 'utt_capacity_line_0013';
    $finalText = $texts[$finalId];
    $baselineId = 'utt_baseline12345678';
    $baselineOutput = "$actorName|ScriptQueue|Previous line./neutral/explicit_disable_rechat/none/phonetic/1/explicit_disable_rechat/$baselineId\r\n";
    $GLOBALS['SCRIPTLINE_UTTERANCE_ID'] = $finalId;
    $GLOBALS['DEBUG_DATA'] = ['OUTPUT_LOG' => "$actorName|ScriptQueue|$finalText/neutral/explicit_disable_rechat/none/phonetic/1/explicit_disable_rechat/$finalId\r\n"];
    $GLOBALS['gameRequest'] = ['inputtext', '1900000000000001', '', 'What do you think?'];
    $requestScope = ['status' => 'active', 'config_id' => $configId, 'actor_a_id' => $actorId,
        'scope' => ['scene_mode' => 'solo', 'actor_a' => $actorName, 'actor_b' => null, 'exclude_player' => true],
        'origin_request_type' => 'inputtext', 'origin_mode' => 'STANDARD', 'origin_dialogue' => 'What do you think?',
        'route' => 'solo_reflection', 'baseline_utterance_id' => $baselineId, 'baseline_output_log' => $baselineOutput];
    $nativeReads = 0;
    $modelCalls = 0;
    $nativeAckReader = static function (string $id) use (&$nativeReads): array {
        $nativeReads++;
        return ['kind' => 'missing'];
    };
    $requestModel = static function () use (&$modelCalls): string {
        $modelCalls++;
        return '{}';
    };
    $rowsReader = static fn(string $requestTs, int $finalEventId): array =>
        ['anchor_count' => 1, 'rows' => $rows];

    $registerStatus = pcv_reflection_register_with_store($requestScope, $store, $stateDirectory,
        $freshScopeReader, $nativeAckReader, $requestModel, $rowsReader);
    $registry = pcv_reflection_registry_probe($stateDirectory);
    echo 'preflight_request=' . (pcv_reflection_request_is_eligible($requestScope) ? 'eligible' : 'ineligible')
        . ' mp_api=' . (pcv_reflection_mind_poisoning_api_compatible() ? 'yes' : 'no')
        . ' reply_api=' . (pcv_reflection_mind_poisoning_reply_api_compatible() ? 'yes' : 'no') . "\n";
    echo 'registration_status=' . $registerStatus . ' registry_kind=' . ($registry['kind'] ?? 'missing') . "\n";
    requireFixture(($registry['kind'] ?? null) === 'ready', 'The real PCV registration helper did not persist a readable record.');
    $record = $registry['record'];
    $lineCount = count($record['registration']['lines'] ?? []);
    $receiptProbe = pcv_reflection_receipt_for_registration($record, $stateDirectory);
    $drainStatus = pcv_reflection_reconcile_registration($record, $store, $stateDirectory,
        $freshScopeReader, $nativeAckReader, $requestModel);
    $ledger = pcv_reflection_read_receipts_locked($stateDirectory);
    $finalStored = in_array($finalId, array_column($ledger['receipts'] ?? [], 'utterance_id'), true);
    $busy = array_values(array_filter($receiptKinds, static fn(array $item): bool => $item[1] === 'busy'));

    requireFixture($registerStatus === 'registered', 'The full-reply source registration did not complete.');
    requireFixture($lineCount === 13, 'The actual PCV registration path did not group all 13 reply lines.');
    requireFixture(($receiptProbe['kind'] ?? null) === 'missing' && $drainStatus === 'registration_missing',
        'The missing final ACK was not left unmatched by the registration drain.');
    requireFixture(count($busy) === 5 && end($receiptKinds)[1] === 'busy' && !$finalStored,
        'The expected over-capacity ACKs were not lost from the bounded receipt ledger.');
    requireFixture(($ledger['kind'] ?? null) === 'ready' && count($ledger['receipts'] ?? []) === 8,
        'The receipt ledger did not remain within its eight-entry cap.');
    requireFixture(($registry['record']['status'] ?? null) === 'registered',
        'A missing early receipt should leave the registration pending, not claimed or consumed.');
    requireFixture($nativeReads === 0 && $modelCalls === 0,
        'A missing final receipt must not read native ACK data or invoke the evaluator model.');

    echo 'registered=' . $registerStatus . ' full_reply_lines=' . $lineCount . "\n";
    echo 'receipt_limit=' . PCV_REFLECTION_RECEIPT_MAX_COUNT . ' stored=' . count($ledger['receipts'])
        . ' busy=' . count($busy) . ' final_ack=' . end($receiptKinds)[1] . "\n";
    echo 'final_receipt=' . $receiptProbe['kind'] . ' registration_drain=' . $drainStatus
        . ' registry_status=' . $registry['record']['status'] . "\n";
    echo 'native_ack_reads=' . $nativeReads . ' model_calls=' . $modelCalls . "\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($root);
}
