<?php
declare(strict_types=1);

if (($argv[1] ?? null) === '--case') {
    try {
        echo json_encode(runObserverCase((string)($argv[2] ?? '')), JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage());
        exit(1);
    }
    exit(0);
}

function observerCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function runObserverCase(string $case): array
{
    if (!in_array($case, ['committed', 'type_only', 'zero', 'provider_failure', 'commit_unconfirmed', 'warning'], true)) {
        throw new RuntimeException('Unknown observer fixture case.');
    }
    define('CHIM_MIND_POISONING_TEST_FIXTURES_ONLY', true);
    define('PCV_LOG_TESTING', true);
    require_once __DIR__ . '/runtime_test.php';
    require_once __DIR__ . '/../server/reflection.php';
    $pcvRoot = realpath(__DIR__ . '/../plugins/private_conversation');
    if (!is_string($pcvRoot)) {
        throw new RuntimeException('Could not resolve embedded Private Conversation source.');
    }
    require_once $pcvRoot . '/server/reflection.php';
    observerCheck(defined('ChimMindPoisoning\\MIND_POISONING_REFLECTION_API_VERSION')
        && constant('ChimMindPoisoning\\MIND_POISONING_REFLECTION_API_VERSION') === 1,
        'Mind Poisoning reflection API version is missing or unsupported.');
    if (!class_exists(ObserverUnconfirmedStore::class, false)) {
        final class ObserverUnconfirmedStore implements \ChimMindPoisoning\StoreDb
        {
            public function __construct(private \ChimMindPoisoning\StoreDb $inner) {}
            public function activePlaythrough(): ?array { return $this->inner->activePlaythrough(); }
            public function acknowledgedEvent(string $id): ?array { return $this->inner->acknowledgedEvent($id); }
            public function playerInputEvent(array $source): ?array { return $this->inner->playerInputEvent($source); }
            public function eventById(int $id, string $utteranceId): ?array { return $this->inner->eventById($id, $utteranceId); }
            public function npcIdentities(): array { return $this->inner->npcIdentities(); }
            public function npcById(int $id, bool $forUpdate = false): ?array { return $this->inner->npcById($id, $forUpdate); }
            public function reflectionHistory(string $name, int $beforeId): array { return $this->inner->reflectionHistory($name, $beforeId); }
            public function beginForListener(int $id): bool { return $this->inner->beginForListener($id); }
            public function writeNpc(int $id, array $edges, object $data, float $gamets, array $relationshipKeysToRemove = []): bool { return $this->inner->writeNpc($id, $edges, $data, $gamets, $relationshipKeysToRemove); }
            public function backupAndVerify(int $id, array $expected): bool { return $this->inner->backupAndVerify($id, $expected); }
            public function commit(): bool { return false; }
            public function rollback(): void { $this->inner->rollback(); }
            public function release(): void { $this->inner->release(); }
        }
    }

    $logDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mp-pcv-observer-' . bin2hex(random_bytes(8));
    observerCheck(mkdir($logDirectory, 0700), 'Could not create isolated importer log directory.');
    register_shutdown_function(static function () use ($logDirectory): void {
        @unlink($logDirectory . DIRECTORY_SEPARATOR . 'events.jsonl');
        @unlink($logDirectory . DIRECTORY_SEPARATOR . 'events.lock');
        @rmdir($logDirectory);
    });
    $importerSinkAvailable = pcv_log_set_test_directory($logDirectory);
    observerCheck($importerSinkAvailable || PHP_OS_FAMILY === 'Windows' || !function_exists('posix_geteuid'),
        'Could not set the isolated importer log directory.');

    [$sourceEvent, , , $memory] = baseFixture();
    $eventId = 100;
    $utteranceId = 'utt_0123456789abcdef';
    $configId = '12345678-1234-4234-8234-123456789abc';
    $speech = 'In my heart, Lydia earned my trust again.';
    $sourceText = 'Aela: I still think Lydia earned my trust. (Talking to explicit_disable_rechat)';
    $memory->npcs[11]['extended_data']->relationships->Lydia = (object)[
        'aff' => 10,
        'type' => $case === 'type_only' ? 'neutral' : 'ally',
    ];
    $memory->events[$eventId] = array_replace($sourceEvent, [
        'event_id' => $eventId,
        'utterance_id' => $utteranceId,
        'gamets' => 10.0,
        'source_data' => $sourceText,
        'delivery_state' => 'emitted',
        'type' => 'chat',
    ]);
    $registration = [
        'event_id' => $eventId,
        'utterance_id' => $utteranceId,
        'actor_id' => 11,
        'actor_name' => 'Aela',
        'playthrough_id' => '1',
        'config_id' => $configId,
        'rechat_target_hint' => 'explicit_disable_rechat',
        'speech_hash' => hash('sha256', $speech),
    ];
    $ack = ['_speech', 0, 10, json_encode([
        'speaker' => 'Aela',
        'listener' => 'Dragonborn',
        'speech' => $speech,
        'utterance_id' => $utteranceId,
    ], JSON_THROW_ON_ERROR)];
    resetAckLoggingInteraction();
    if ($case === 'warning') {
        observerCheck(file_put_contents($GLOBALS['ENGINE_PATH'] . 'data/mind_poisoning.json', '{"enabled":"false"}') !== false,
            'Could not create the invalid pause-control fixture.');
    }

    $sinkRecords = [];
    $log = new \ChimMindPoisoning\RequestLog(static function (string $json, string $level) use (&$sinkRecords): void {
        $sinkRecords[] = json_decode($json, true, 512, JSON_THROW_ON_ERROR) + ['sink_level' => $level];
    }, false);
    observerCheck(pcv_reflection_attach_mp_observer($log), 'Could not attach the standalone importer observer.');
    $store = $case === 'commit_unconfirmed' ? new ObserverUnconfirmedStore($memory) : $memory;
    $model = static function () use ($case): string {
        if ($case === 'provider_failure') {
            throw new RuntimeException('PRIVATE_PROVIDER_FAILURE_TEXT');
        }
        $judgment = [
            'subject' => 'npc:22',
            'subject_mentioned' => true,
            'delta' => in_array($case, ['zero', 'type_only'], true) ? 0 : 3,
            'reason' => 'The actor recalls Lydia honoring a promise.',
            'evidence' => 'Lydia earned my trust again.',
        ];
        if ($case === 'type_only') {
            $judgment['type_change'] = ['from' => 'neutral', 'to' => 'suspicious'];
        }
        return validModelResponse([$judgment]);
    };
    $status = \ChimMindPoisoning\mindPoisoningEvaluateReflection(
        $registration,
        $ack,
        $store,
        static fn(array $given, string $phase): bool => $given === $registration,
        $model,
        $log
    );

    $persistenceRecord = null;
    $evaluationRecord = null;
    foreach ($sinkRecords as $record) {
        if (($record['event'] ?? null) === 'persistence_finished') {
            $persistenceRecord = $record;
        } elseif (($record['event'] ?? null) === 'request_finished') {
            $evaluationRecord = $record;
        }
    }
    observerCheck(is_array($persistenceRecord) && is_array($evaluationRecord), 'Mind Poisoning did not emit the records needed for importer classification.');
    if (in_array($case, ['type_only', 'commit_unconfirmed'], true)) {
        $expectedPersistence = $case === 'type_only'
            ? ['committed', 'info', null]
            : ['unconfirmed', 'error', 'commit_unconfirmed'];
        observerCheck(\pcv_log_mp_persistence_outcome($persistenceRecord) === $expectedPersistence,
            'The unchanged importer did not classify the real sanitized persistence record as expected.');
        $expectedEvaluation = $case === 'type_only'
            ? ['committed', 'info', null]
            : ['unconfirmed', 'error', 'commit_unconfirmed'];
        observerCheck(\pcv_log_mp_evaluation_outcome($evaluationRecord, $evaluationRecord['sink_level'] ?? '') === $expectedEvaluation,
            'The unchanged importer did not classify the real sanitized evaluation record as expected.');
    }

    if (!$importerSinkAvailable) {
        $importerRequest =& pcv_log_request_context();
        return [
            'case' => $case,
            'status' => $status,
            'source' => $sinkRecords,
            'summary' => $evaluationRecord,
            'imported' => [],
            'importer_sink' => 'posix_uid_unavailable',
            'importer_context' => [
                'config_id' => $importerRequest['config_id'] ?? null,
                'correlation' => $importerRequest['correlation'] ?? [],
            ],
        ];
    }

    $path = pcv_log_path();
    observerCheck(is_string($path) && is_file($path), 'The real importer did not write its isolated JSONL file.');
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    observerCheck(is_array($lines), 'Could not read importer JSONL output.');
    $imported = array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR), $lines);
    $raw = implode("\n", $lines);
    foreach ([$speech, $sourceText, $registration['speech_hash'], 'PRIVATE_PROVIDER_FAILURE_TEXT', 'model_reason'] as $privateValue) {
        observerCheck(!str_contains($raw, $privateValue), 'Sensitive source text or debug rationale reached the importer log.');
    }
    $summary = [];
    foreach ($sinkRecords as $record) {
        if (($record['event'] ?? null) === 'request_finished') {
            $summary = $record;
            break;
        }
    }
    observerCheck($summary !== [], 'Mind Poisoning did not emit a final summary.');
    return ['case' => $case, 'status' => $status, 'source' => $sinkRecords, 'summary' => $summary, 'imported' => $imported];
}

function runIsolatedCase(string $name): array
{
    $command = [PHP_BINARY];
    if (PHP_OS_FAMILY === 'Windows') {
        $command = [
            PHP_BINARY,
            '-n',
            '-d', 'extension_dir=' . dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'ext',
            '-d', 'extension=php_mbstring.dll',
        ];
    }
    array_push($command, __FILE__, '--case', $name);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start isolated case ' . $name . '.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        throw new RuntimeException($name . ' failed: ' . trim((string)$stderr . ' ' . (string)$stdout));
    }
    return json_decode((string)$stdout, true, 64, JSON_THROW_ON_ERROR);
}

function imported(array $result, string $event, ?string $outcome = null): array
{
    foreach ($result['imported'] as $record) {
        if (($record['event'] ?? null) === $event && ($outcome === null || ($record['outcome'] ?? null) === $outcome)) {
            return $record;
        }
    }
    throw new RuntimeException($result['case'] . ' did not import ' . $event . ' / ' . (string)$outcome . '.');
}

if (PHP_OS_FAMILY === 'Windows' || !function_exists('posix_geteuid')) {
    $typeOnly = runIsolatedCase('type_only');
    $typePersistence = null;
    foreach ($typeOnly['source'] as $record) {
        if (($record['event'] ?? null) === 'persistence_finished') {
            $typePersistence = $record;
            break;
        }
    }
    observerCheck(($typeOnly['status'] ?? null) === 'committed'
        && ($typeOnly['summary']['commit_state'] ?? null) === 'confirmed'
        && ($typeOnly['summary']['changed_count'] ?? null) === 1
        && ($typeOnly['summary']['changes'] ?? null) === []
        && ($typeOnly['summary']['type_changed_count'] ?? null) === 1
        && ($typePersistence['type_changes'][0] ?? null) === ['subject' => 'npc:22', 'before' => 'neutral', 'after' => 'suspicious'],
        'The real type-only reflection did not report its confirmed relationship transition: ' . json_encode($typeOnly, JSON_UNESCAPED_SLASHES));
    observerCheck(($typeOnly['importer_sink'] ?? null) === 'posix_uid_unavailable', 'The unsupported importer sink should be reported explicitly.');
    observerCheck(($typeOnly['importer_context']['config_id'] ?? null) === '12345678-1234-4234-8234-123456789abc'
        && ($typeOnly['importer_context']['correlation']['event_id'] ?? null) === '100'
        && ($typeOnly['importer_context']['correlation']['utterance_id'] ?? null) === 'utt_0123456789abcdef'
        && ($typeOnly['importer_context']['correlation']['linked_request_id'] ?? null) === ($typeOnly['summary']['request_id'] ?? null),
        'The full importer did not preserve exact in-memory config and event correlation.');

    $unconfirmed = runIsolatedCase('commit_unconfirmed');
    observerCheck(($unconfirmed['summary']['commit_state'] ?? null) === 'unconfirmed'
        && ($unconfirmed['summary']['changed_count'] ?? null) === 0
        && ($unconfirmed['summary']['committed'] ?? null) === false
        && !array_key_exists('type_changes', $unconfirmed['summary'])
        && ($unconfirmed['importer_sink'] ?? null) === 'posix_uid_unavailable'
        && ($unconfirmed['importer_context']['config_id'] ?? null) === '12345678-1234-4234-8234-123456789abc'
        && ($unconfirmed['importer_context']['correlation']['linked_request_id'] ?? null) === ($unconfirmed['summary']['request_id'] ?? null),
        'The real unconfirmed persistence did not remain distinct from an applied transition: ' . json_encode($unconfirmed, JSON_UNESCAPED_SLASHES));
    fwrite(STDOUT, "reflection observer/importer: type-only confirmed and unconfirmed classifications passed; JSONL sink skipped because POSIX UID support is unavailable\n");
    exit(0);
}

$cases = [];
foreach (['committed', 'type_only', 'zero', 'provider_failure', 'commit_unconfirmed', 'warning'] as $name) {
    $cases[$name] = runIsolatedCase($name);
}
foreach ($cases as $result) {
    $requestId = $result['summary']['request_id'] ?? null;
    observerCheck(is_string($requestId) && preg_match('/\A[a-f0-9]{24}\z/D', $requestId) === 1, 'Source request ID was not valid.');
    observerCheck(($result['summary']['config_id'] ?? null) === '12345678-1234-4234-8234-123456789abc', 'Source config correlation was lost.');
    foreach ($result['imported'] as $record) {
        observerCheck(($record['config_id'] ?? null) === '12345678-1234-4234-8234-123456789abc', 'Importer config correlation was lost.');
        observerCheck(($record['context']['correlation']['event_id'] ?? null) === '100', 'Importer event correlation was lost.');
        observerCheck(($record['context']['correlation']['utterance_id'] ?? null) === 'utt_0123456789abcdef', 'Importer utterance correlation was lost.');
        observerCheck(($record['context']['correlation']['linked_request_id'] ?? null) === $requestId, 'Importer request link did not match the source request.');
    }
}
$committed = imported($cases['committed'], 'reflection.persistence_finished', 'committed');
observerCheck(($cases['committed']['status'] ?? null) === 'committed' && ($committed['severity'] ?? null) === 'info', 'Committed evaluator result did not import as info.');
observerCheck(($committed['context']['commit_state'] ?? null) === 'confirmed' && ($committed['context']['committed'] ?? null) === true, 'Confirmed commit fields were not imported.');
observerCheck(($committed['context']['changed_count'] ?? null) === 1
    && ($committed['context']['changes'][0] ?? null) === ['subject' => 'npc:22', 'delta' => 3, 'before' => 10, 'after' => 13],
    'Committed change tuple was not imported exactly.');
$typeOnly = imported($cases['type_only'], 'reflection.persistence_finished', 'committed');
observerCheck(($cases['type_only']['status'] ?? null) === 'committed'
    && ($typeOnly['severity'] ?? null) === 'info'
    && ($typeOnly['context']['commit_state'] ?? null) === 'confirmed'
    && ($typeOnly['context']['changed_count'] ?? null) === 1
    && ($typeOnly['context']['changes'] ?? null) === []
    && !array_key_exists('type_changes', $typeOnly['context']),
    'The importer must classify a confirmed type-only persistence as committed using changed_count.');
$typeOnlySourcePersistence = null;
foreach ($cases['type_only']['source'] as $record) {
    if (($record['event'] ?? null) === 'persistence_finished') {
        $typeOnlySourcePersistence = $record;
        break;
    }
}
observerCheck(($cases['type_only']['summary']['type_changed_count'] ?? null) === 1
    && ($cases['type_only']['summary']['changed_count'] ?? null) === 1
    && ($typeOnlySourcePersistence['type_changes'][0] ?? null) === ['subject' => 'npc:22', 'before' => 'neutral', 'after' => 'suspicious'],
    'The Mind Poisoning source summary must retain the confirmed type transition even though PCV ignores that detail.');
$modelFinished = imported($cases['committed'], 'reflection.model_finished', 'valid');
$evaluationResult = imported($cases['committed'], 'reflection.evaluation_result', 'committed');
observerCheck(is_numeric($modelFinished['context']['model_ms'] ?? null)
    && is_numeric($committed['context']['persistence_ms'] ?? null)
    && is_numeric($evaluationResult['context']['model_ms'] ?? null)
    && is_numeric($evaluationResult['context']['persistence_ms'] ?? null), 'Committed timings were not imported.');
$zero = imported($cases['zero'], 'reflection.persistence_finished', 'zero_change');
observerCheck(($zero['context']['commit_state'] ?? null) === 'confirmed'
    && ($zero['context']['changed_count'] ?? null) === 0
    && ($zero['context']['changes'] ?? null) === []
    && ($zero['context']['source_reason'] ?? null) === 'zero-change', 'Zero-change persistence was not imported accurately.');
$failure = imported($cases['provider_failure'], 'reflection.model_finished', 'failed');
observerCheck(($failure['severity'] ?? null) === 'error'
    && ($failure['reason'] ?? null) === 'model_failed'
    && ($failure['context']['source_reason'] ?? null) === 'model_request_failed', 'Provider failure was not imported as a fixed error code.');
$uncertain = imported($cases['commit_unconfirmed'], 'reflection.persistence_finished', 'unconfirmed');
observerCheck(($uncertain['severity'] ?? null) === 'error' && ($uncertain['reason'] ?? null) === 'commit_unconfirmed', 'Unconfirmed commit was not imported as an error.');
observerCheck(($uncertain['context']['commit_state'] ?? null) === 'unconfirmed'
    && ($uncertain['context']['committed'] ?? null) === false
    && ($uncertain['context']['source_reason'] ?? null) === 'commit-failed', 'Unconfirmed commit details were lost.');
$uncertainSummary = imported($cases['commit_unconfirmed'], 'reflection.evaluation_result', 'unconfirmed');
observerCheck(($uncertainSummary['severity'] ?? null) === 'error', 'Unconfirmed final evaluation summary was not imported as an error.');
$warning = imported($cases['warning'], 'reflection.evaluation_result', 'rejected');
observerCheck(($cases['warning']['status'] ?? null) === 'paused' && ($warning['severity'] ?? null) === 'warning', 'Warning-level pre-model skip was not imported.');
observerCheck(($warning['reason'] ?? null) === 'evaluation_rejected', "Skipped evaluation should use the importer's fixed warning reason.");
fwrite(STDOUT, "reflection observer/importer: 6 cases passed (committed delta, type-only commit, zero change, provider failure, unconfirmed commit, warning skip)\n");
