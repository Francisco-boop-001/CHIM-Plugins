<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
define('CHIM_MIND_POISONING_TEST_FIXTURES_ONLY', true);
$mindPoisoningRoot = null;
foreach ([dirname(__DIR__, 2) . '/CHIM-MindPoisoning', dirname(__DIR__, 3)] as $candidate) {
    if (is_file($candidate . '/tests/runtime_test.php') && is_file($candidate . '/server/reflection.php')) {
        $mindPoisoningRoot = $candidate;
        break;
    }
}
if ($mindPoisoningRoot === null) {
    throw new RuntimeException('Mind Poisoning reflection test fixtures were not found.');
}
require_once $mindPoisoningRoot . '/tests/runtime_test.php';
require_once $mindPoisoningRoot . '/server/reflection.php';
require_once __DIR__ . '/../server/reflection.php';

function reflectionScopeFixture(array $changes = []): array
{
    return array_replace_recursive([
        'status' => 'active', 'pcv_key' => str_repeat('a', 64),
        'config_id' => '123e4567-e89b-42d3-a456-426614174000', 'actor_a_id' => '11',
        'scope' => ['scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null, 'exclude_player' => true],
    ], $changes);
}

function reflectionRequestScope(string $baselineOutput): array
{
    return [
        'status' => 'active', 'config_id' => '123e4567-e89b-42d3-a456-426614174000', 'actor_a_id' => '11',
        'scope' => ['scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null, 'exclude_player' => true],
        'origin_request_type' => 'inputtext', 'origin_mode' => 'STANDARD', 'origin_dialogue' => 'What do you think?',
        'route' => 'solo_reflection', 'baseline_utterance_id' => 'utt_baseline12345678',
        'baseline_output_log' => $baselineOutput,
    ];
}

function reflectionWire(string $subtitle, string $id, string $speaker = 'Aela', string $atomic = 'explicit_disable_rechat', string $rechat = 'explicit_disable_rechat'): string
{
    return $speaker . '|ScriptQueue|' . $subtitle . '/neutral/' . $atomic . '/none/phonetic/1/' . $rechat . '/' . $id . "\r\n";
}

function reflectionStore(string $id, string $delivery = 'emitted', string $source = 'Aela: I met the steward at the gate. (Talking to explicit_disable_rechat)'): MemoryStoreDb
{
    [, , , $store] = baseFixture();
    $store->events[200] = ['event_id' => 200, 'utterance_id' => $id, 'delivery_state' => $delivery, 'source_data' => $source, 'gamets' => 30.0];
    return $store;
}

function reflectionRegister(MemoryStoreDb $store, string $directory, string $wire, ?string $baseline = null): string
{
    $id = 'utt_1234567890abcdef';
    $GLOBALS['SCRIPTLINE_UTTERANCE_ID'] = $id;
    $GLOBALS['DEBUG_DATA'] = ['OUTPUT_LOG' => $wire];
    $GLOBALS['HERIKA_NAME'] = 'Aela';
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    return pcv_reflection_register_with_store(
        reflectionRequestScope($baseline ?? 'Aela|ScriptQueue|previous/neutral/explicit_disable_rechat/none/phonetic/1/explicit_disable_rechat/utt_baseline12345678'),
        $store, $directory, static fn(): array => reflectionScopeFixture()
    );
}

function reflectionAck(string $speech, string $id = 'utt_1234567890abcdef'): array
{
    return ['_speech', '', '', json_encode(['speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => $speech, 'utterance_id' => $id], JSON_THROW_ON_ERROR)];
}

function reflectionRemoveTestDirectory(string $path): void
{
    if (!str_starts_with($path, sys_get_temp_dir() . DIRECTORY_SEPARATOR) || !is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

function reflectionPcvLogEntries(): array
{
    $path = pcv_log_path();
    if (!is_string($path) || !is_file($path) || is_link($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return is_array($lines)
        ? array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR), $lines)
        : [];
}

function reflectionPcvEventCount(string $event, string $utteranceId): int
{
    return count(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
        ($entry['event'] ?? null) === $event
        && ($entry['context']['correlation']['utterance_id'] ?? null) === $utteranceId));
}

$legacyLog = new class {};
check(!pcv_reflection_attach_mp_observer($legacyLog),
    'A logger without the optional observer method must remain supported.');
$mpObserverSupported = method_exists(\ChimMindPoisoning\RequestLog::class, 'observe');
$actualLog = new \ChimMindPoisoning\RequestLog(static function (string $json): void {}, false);
check(pcv_reflection_attach_mp_observer($actualLog) === $mpObserverSupported,
    'The current Mind Poisoning RequestLog observer capability should be detected without changing its evaluation path.');
$optionalLog = new class {
    public $observer = null;
    public function observe(?callable $observer): void
    {
        $this->observer = $observer;
    }
};
check(pcv_reflection_attach_mp_observer($optionalLog) && is_callable($optionalLog->observer),
    'An optional observer-capable logger should be attached without affecting the evaluation path.');
($optionalLog->observer)([], 'info');
$failingOptionalLog = new class {
    public function observe(?callable $observer): void
    {
        throw new RuntimeException('optional observer setup failure');
    }
};
check(!pcv_reflection_attach_mp_observer($failingOptionalLog),
    'An observer setup failure must be contained and reported as unsupported.');

function checkBrokenOptionalModule(string $testRoot, string $serverSource): void
{
    check(function_exists('proc_open'), 'The local PHP CLI must support isolated subprocess fixtures.');
    $runner = $testRoot . DIRECTORY_SEPARATOR . 'broken_dependency.php';
    $source = <<<'PHP'
<?php
declare(strict_types=1);
define('PCV_LOG_TESTING', true);
$layout = __LAYOUT__;
$root = $layout . '/webroot/HerikaServer';
$serverSource = __SERVER_SOURCE__;
$extension = $root . '/ext/private_conversation';
$mindPoisoning = $root . '/ext/mind_poisoning';
mkdir($extension, 0700, true);
mkdir($mindPoisoning, 0700, true);
mkdir($layout . '/logs', 0700, true);
foreach (['reflection.php', 'log.php', 'state.php', 'scope.php'] as $name) {
    if (!copy($serverSource . '/' . $name, $extension . '/' . $name)) {
        throw new RuntimeException('Could not prepare isolated extension layout.');
    }
}
file_put_contents($mindPoisoning . '/reflection.php', "<?php\nfunction broken(\n");
require $extension . '/reflection.php';
if (!pcv_log_set_test_directory($layout . '/logs')) {
    throw new RuntimeException('Could not isolate diagnostics.');
}
$configId = '123e4567-e89b-42d3-a456-426614174000';
$utteranceId = 'utt_1234567890abcdef';
$directory = pcv_state_directory(null);
$handle = pcv_lock_state($directory, true, LOCK_EX);
try {
    pcv_reflection_write_locked($directory, [
        'version' => 1, 'pcv_key' => str_repeat('b', 64), 'config_id' => $configId,
        'actor_id' => 11, 'actor_name' => 'Aela', 'origin_request_type' => 'inputtext',
        'origin_mode' => 'STANDARD', 'route' => 'solo_reflection', 'created_at' => time(),
        'status' => 'registered', 'claim_token' => null,
        'registration' => [
            'event_id' => 200, 'utterance_id' => $utteranceId, 'actor_id' => 11,
            'actor_name' => 'Aela', 'playthrough_id' => '1', 'config_id' => $configId,
            'rechat_target_hint' => 'explicit_disable_rechat', 'speech_hash' => hash('sha256', 'private test utterance'),
        ],
    ]);
} finally {
    pcv_unlock_state($handle);
}
pcvReflectionEvaluateAck(['_speech', '', '', json_encode([
    'speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => 'private test utterance', 'utterance_id' => $utteranceId,
], JSON_THROW_ON_ERROR)]);
$path = pcv_log_path();
$records = array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR), array_filter(explode("\n", (string)file_get_contents($path))));
$failures = array_values(array_filter($records, static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.ack_error'
    && ($entry['reason'] ?? null) === 'internal_error'
    && ($entry['context']['actor_a_id'] ?? null) === '11'
    && ($entry['context']['exception_class'] ?? null) === 'ParseError'
));
if (count($failures) !== 1) {
    throw new RuntimeException('A broken optional MP module was not contained and logged.');
}
echo "broken MP module was contained\n";
PHP;
    $source = str_replace(
        ['__LAYOUT__', '__SERVER_SOURCE__'],
        [var_export($testRoot . DIRECTORY_SEPARATOR . 'broken-layout', true), var_export($serverSource, true)],
        $source
    );
    check(file_put_contents($runner, $source) === strlen($source), 'Write the isolated dependency fixture.');
    $process = proc_open([PHP_BINARY, $runner], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($process), 'Start the isolated dependency fixture.');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    same(0, proc_close($process), 'A broken optional dependency must not abort ACK processing: ' . $stderr);
    same("broken MP module was contained\n", $stdout, 'The isolated ACK handler should return normally after logging a dependency failure.');
}

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv_reflection_' . bin2hex(random_bytes(8));
check(mkdir($testRoot, 0700), 'Create the isolated reflection test root.');
$logDirectory = $testRoot . DIRECTORY_SEPARATOR . 'logs';
check(mkdir($logDirectory, 0700) && pcv_log_set_test_directory($logDirectory), 'Use isolated PCV logs.');
register_shutdown_function(static fn() => reflectionRemoveTestDirectory($testRoot));
checkBrokenOptionalModule($testRoot, dirname(__DIR__) . '/server');
pcvReflectionRegisterLastOutput(reflectionRequestScope('Aela|ScriptQueue|old/neutral/explicit_disable_rechat/none/phonetic/1/explicit_disable_rechat/utt_baseline12345678'));

$id = 'utt_1234567890abcdef';
$subtitle = 'Jarl Balgruuf betrayed me last night.';
$wire = reflectionWire($subtitle, $id);
$directory = $testRoot . DIRECTORY_SEPARATOR . 'valid';
$store = reflectionStore($id);
$GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
$GLOBALS['HERIKA_NAME'] = 'Aela';
$GLOBALS['DEBUG_DATA'] = [];
same('output_unavailable', pcv_reflection_register_with_store(
    reflectionRequestScope('Aela|ScriptQueue|previous/neutral/explicit_disable_rechat/none/phonetic/1/explicit_disable_rechat/utt_baseline12345678'),
    reflectionStore($id), $testRoot . DIRECTORY_SEPARATOR . 'output_unavailable', static fn(): array => reflectionScopeFixture()
), 'Do not attempt a registration until the native output log exists.');
resetAckLoggingInteraction();
same('registered', reflectionRegister($store, $directory, $wire), 'Register only a fresh full native output line.');
$registryPath = $directory . DIRECTORY_SEPARATOR . 'reflection.json';
$registry = json_decode((string)file_get_contents($registryPath), true, 16, JSON_THROW_ON_ERROR);
same(1, $registry['version'] ?? null, 'The registry version is required.');
same(hash('sha256', $subtitle), $registry['registration']['speech_hash'] ?? null, 'Hash only the trimmed subtitle field.');
check(!str_contains((string)file_get_contents($registryPath), $subtitle), 'Do not persist raw dialogue.');
$registeredEntries = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.output_registered'));
$registeredCorrelation = $registeredEntries[0]['context']['correlation'] ?? [];
same('200', $registeredCorrelation['event_id'] ?? null, 'The accepted registration log should carry the exact matched source event ID.');
same($id, $registeredCorrelation['utterance_id'] ?? null, 'The accepted registration log should carry the exact native utterance ID.');

$modelCalls = 0;
$prompt = null;
$claimToken = null;
$mpRecords = [];
$ackObserverBoundary = null;
$requestLog = new \ChimMindPoisoning\RequestLog(static function (string $json) use (&$mpRecords, &$ackObserverBoundary): void {
    $record = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    $mpRecords[] = $record;
    if ($ackObserverBoundary === null && ($record['source_kind'] ?? null) === 'reflection'
        && in_array($record['event'] ?? null, ['reflection_model_finished', 'persistence_finished', 'persistence_cleanup_failed', 'request_finished'], true)) {
        $request =& pcv_log_request_context();
        $ackObserverBoundary = [
            'config_id' => $request['config_id'],
            'correlation' => $request['correlation'],
        ];
    }
}, false);
$ack = reflectionAck($subtitle);
same('registration_missing', pcv_reflection_evaluate_with_store(reflectionAck($subtitle, 'utt_aaaaaaaaaaaaaaaa'), $store, static function (): never { throw new RuntimeException('unrelated ACK provider call'); }, $directory, static fn(): array => reflectionScopeFixture()), 'An unrelated utterance must not use this registration.');
same('ack_mismatch', pcv_reflection_evaluate_with_store(reflectionAck('Different words.', $id), $store, static function (): never { throw new RuntimeException('mismatched ACK provider call'); }, $directory, static fn(): array => reflectionScopeFixture()), 'A mismatched subtitle must leave the registration unused.');
$paddedDirectory = $testRoot . DIRECTORY_SEPARATOR . 'padded_id';
$paddedStore = reflectionStore($id);
same('registered', reflectionRegister($paddedStore, $paddedDirectory, $wire), 'Register the normalized native utterance ID.');
same('not_applicable', pcv_reflection_evaluate_with_store(reflectionAck($subtitle, $id . "\rX"), $paddedStore, static fn(): never => throw new RuntimeException('malformed interior ID must not call provider'), $paddedDirectory, static fn(): array => reflectionScopeFixture()), 'Trimming must not accept interior ID corruption.');
$paddedModelCalls = 0;
$paddedModel = static function () use (&$paddedModelCalls): string {
    $paddedModelCalls++;
    return validModelResponse([['subject' => 'npc:33', 'delta' => 2, 'reason' => 'The reflection supports a change.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
};
same('committed', pcv_reflection_evaluate_with_store(reflectionAck($subtitle, $id . "\r"), $paddedStore, $paddedModel, $paddedDirectory, static fn(): array => reflectionScopeFixture()), 'Normalize the padded native ACK ID before exact registry matching.');
same('claim_taken', pcv_reflection_evaluate_with_store(reflectionAck($subtitle, $id . "\r"), $paddedStore, $paddedModel, $paddedDirectory, static fn(): array => reflectionScopeFixture()), 'A repeated padded ACK must remain single-use.');
same(1, $paddedModelCalls, 'A padded duplicate must not call the provider twice.');
$expiredDirectory = $testRoot . DIRECTORY_SEPARATOR . 'expired';
same('registered', reflectionRegister(reflectionStore($id), $expiredDirectory, $wire), 'Register expiry fixture.');
$expiredPath = $expiredDirectory . DIRECTORY_SEPARATOR . 'reflection.json';
$expiredRecord = json_decode((string)file_get_contents($expiredPath), true, 16, JSON_THROW_ON_ERROR);
$expiredRecord['created_at'] = time() - PCV_REFLECTION_REGISTRY_TTL - 1;
file_put_contents($expiredPath, json_encode($expiredRecord, JSON_THROW_ON_ERROR));
@chmod($expiredPath, 0600);
$expiredCalls = 0;
same('registration_stale', pcv_reflection_evaluate_with_store($ack, reflectionStore($id), static function () use (&$expiredCalls): never { $expiredCalls++; throw new RuntimeException('expired provider call'); }, $expiredDirectory), 'An expired registration must not be evaluated.');
same(0, $expiredCalls, 'Expired registrations must not call the provider.');
// Each real ACK arrives in a new HTTP request, so prior registration globals cannot anchor the observer tuple.
pcv_log_set_config_id(null);
pcv_log_set_correlation([]);
$status = pcv_reflection_evaluate_with_store($ack, $store, static function (array $messages) use (&$modelCalls, &$prompt, &$claimToken, $directory): string {
    $modelCalls++;
    $prompt = $messages;
    $claimed = json_decode((string)file_get_contents($directory . DIRECTORY_SEPARATOR . 'reflection.json'), true, 16, JSON_THROW_ON_ERROR);
    $claimToken = $claimed['claim_token'] ?? null;
    $lock = pcv_lock_state($directory, false, LOCK_EX | LOCK_NB);
    check(is_resource($lock), 'The PCV file lock must be released before provider work.');
    pcv_unlock_state($lock);
    return validModelResponse([['subject' => 'npc:33', 'delta' => 3, 'reason' => 'The reflection supports a change.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
}, $directory, static fn(): array => reflectionScopeFixture(), $requestLog);
same('committed', $status, 'The exact ACK must reach the real Mind Poisoning reflection API.');
same('123e4567-e89b-42d3-a456-426614174000', $ackObserverBoundary['config_id'] ?? null,
    'The successful ACK must bind the validated configuration before an observer row is written.');
same('200', $ackObserverBoundary['correlation']['event_id'] ?? null,
    'The successful ACK must bind the validated event ID before an observer row is written.');
same($id, $ackObserverBoundary['correlation']['utterance_id'] ?? null,
    'The successful ACK must bind the validated utterance ID before an observer row is written.');
same(1, $modelCalls, 'Evaluate this ACK once.');
$payload = json_decode($prompt[1]['content'], true, 64, JSON_THROW_ON_ERROR)['untrusted_data'] ?? [];
same($subtitle, $payload['current_reflection'] ?? null, 'Use emitted subtitle even when core source text differs.');
same(28, $store->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Persist the reflecting actor’s opinion.');
same(99, $store->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Do not invent another NPC as listener or owner.');
$summary = null;
foreach (array_reverse($mpRecords) as $entry) {
    if (($entry['event'] ?? null) === 'request_finished') { $summary = $entry; break; }
}
same('reflection', $summary['source_kind'] ?? null, 'MP diagnostics must retain reflection provenance.');
same('11', $summary['opinion_owner_id'] ?? null, 'MP diagnostics must attribute the actor opinion owner.');
same('committed', $summary['outcome'] ?? null, 'MP diagnostics should record the persisted result.');
check(!array_key_exists('listener_id', $summary ?? []), 'MP diagnostics must not invent a listener.');
$unsupportedEntries = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.observer_unavailable'
    && ($entry['reason'] ?? null) === 'observer_unsupported'
    && ($entry['context']['correlation']['event_id'] ?? null) === '200'
    && ($entry['context']['correlation']['utterance_id'] ?? null) === $id));
same($mpObserverSupported ? 0 : 2, count($unsupportedEntries),
    'The PCV log should report observer unavailability only when the installed MP RequestLog lacks the optional API.');
if (!$mpObserverSupported && $unsupportedEntries !== []) {
    same('unavailable', $unsupportedEntries[0]['outcome'] ?? null,
        'The PCV log should state that unified MP outcome import is unsupported for a validated ACK.');
}
$importedEntries = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    in_array($entry['event'] ?? null, ['reflection.model_finished', 'reflection.persistence_finished', 'reflection.evaluation_result'], true)));
check($mpObserverSupported ? $importedEntries !== [] : $importedEntries === [],
    'Detailed Mind Poisoning outcomes should be imported only when its optional observer API is available.');
same(2, reflectionPcvEventCount('reflection.evaluation_finished', $id),
    'Each committed adapter return should be recorded once without making playback claims.');
same('claim_taken', pcv_reflection_evaluate_with_store($ack, $store, static function (): never { throw new RuntimeException('duplicate provider call'); }, $directory, static fn(): array => reflectionScopeFixture()), 'Consume duplicate ACKs without evaluation.');
same(1, $modelCalls, 'Duplicate ACKs must not call the provider again.');
$replayEntries = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.ack_skipped' && ($entry['reason'] ?? null) === 'claim_taken'));
check($replayEntries !== []
    && ($replayEntries[0]['context']['correlation']['event_id'] ?? null) === '200'
    && ($replayEntries[0]['context']['correlation']['utterance_id'] ?? null) === $id,
    'A replay skip should retain the same exact correlation tuple without making a second provider call.');

$zeroDirectory = $testRoot . DIRECTORY_SEPARATOR . 'zero_change';
$zeroStore = reflectionStore($id);
same('registered', reflectionRegister($zeroStore, $zeroDirectory, $wire), 'Register zero-change fixture.');
$zeroRecords = [];
$zeroLog = new \ChimMindPoisoning\RequestLog(static function (string $json) use (&$zeroRecords): void {
    $zeroRecords[] = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
}, false);
same('committed', pcv_reflection_evaluate_with_store($ack, $zeroStore, static fn(): string => validModelResponse([
    ['subject' => 'npc:33', 'delta' => 0, 'reason' => 'No new evidence supports a change.', 'evidence' => 'Jarl Balgruuf betrayed me'],
]), $zeroDirectory, static fn(): array => reflectionScopeFixture(), $zeroLog), 'The exact reflection may be confirmed with no affinity changes.');
$zeroSummary = null;
foreach (array_reverse($zeroRecords) as $entry) {
    if (($entry['event'] ?? null) === 'request_finished') { $zeroSummary = $entry; break; }
}
same('committed', $zeroSummary['outcome'] ?? null, 'Mind Poisoning must retain the detailed zero-change result.');
same(0, $zeroSummary['changed_count'] ?? null, 'Mind Poisoning must retain the exact zero change count.');
$unsupportedAfterZero = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.observer_unavailable'
    && ($entry['context']['correlation']['event_id'] ?? null) === '200'
    && ($entry['context']['correlation']['utterance_id'] ?? null) === $id));
same($mpObserverSupported ? 0 : 3, count($unsupportedAfterZero),
    'Each validated ACK should get an unsupported-import marker only when the optional observer API is absent.');
same(3, reflectionPcvEventCount('reflection.evaluation_finished', $id),
    'Each committed adapter return should be recorded once.');

foreach ([
    reflectionWire($subtitle, $id, 'Aela', 'Player'),
    reflectionWire($subtitle, $id, 'Aela', 'explicit_disable_rechat', 'Player'),
    reflectionWire($subtitle . '/ambiguous', $id),
    reflectionWire($subtitle . '|ambiguous', $id),
    reflectionWire($subtitle, 'utt_aaaaaaaaaaaaaaaa'),
    reflectionWire($subtitle . "\nextra", $id),
    $wire . '/extra',
    'Aela|ScriptQueue|extra|' . substr($wire, strlen('Aela|ScriptQueue|')),
] as $index => $line) {
    same('output_malformed', reflectionRegister(reflectionStore($id), $testRoot . DIRECTORY_SEPARATOR . 'malformed_' . $index, $line), 'Reject wrong sentinel positions, extra native fields, and ambiguous pipes.');
}
same('baseline_stale', reflectionRegister(reflectionStore($id), $testRoot . DIRECTORY_SEPARATOR . 'stale_baseline', $wire, $wire), 'Reject unchanged pre-generation output.');
same('source_aborted', reflectionRegister(reflectionStore($id, 'aborted'), $testRoot . DIRECTORY_SEPARATOR . 'aborted', $wire), 'Reject aborted source events.');
same('sentinel_mismatch', reflectionRegister(reflectionStore($id, 'emitted', 'Aela: I met the steward. (Talking to Lydia)'), $testRoot . DIRECTORY_SEPARATOR . 'source_target_mismatch', $wire), 'The independently parsed source event must also prove the explicit sentinel target.');

$scopeDirectory = $testRoot . DIRECTORY_SEPARATOR . 'scope_changed';
$scopeStore = reflectionStore($id);
same('registered', reflectionRegister($scopeStore, $scopeDirectory, $wire), 'Register before stale-scope check.');
$scopeNow = reflectionScopeFixture();
$scopeReader = static function () use (&$scopeNow): array { return $scopeNow; };
$staleRecords = [];
$staleLog = new \ChimMindPoisoning\RequestLog(static function (string $json) use (&$staleRecords): void { $staleRecords[] = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }, false);
$staleStatus = pcv_reflection_evaluate_with_store($ack, $scopeStore, static function () use (&$scopeNow): string {
    $scopeNow['config_id'] = '223e4567-e89b-42d3-a456-426614174000';
    return validModelResponse([['subject' => 'npc:33', 'delta' => 3, 'reason' => 'The reflection supports a change.', 'evidence' => 'Jarl Balgruuf betrayed me']]);
}, $scopeDirectory, $scopeReader, $staleLog);
same('stale', $staleStatus, 'The transaction must reject a changed active scope.');
same(25, $scopeStore->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'A stale transaction must not persist.');
same(3, reflectionPcvEventCount('reflection.evaluation_finished', $id),
    'A stale API result must not be reported as an accepted adapter completion.');

$failureDirectory = $testRoot . DIRECTORY_SEPARATOR . 'provider_failure';
$failureStore = reflectionStore($id);
same('registered', reflectionRegister($failureStore, $failureDirectory, $wire), 'Register provider-failure fixture.');
$failureRecords = [];
$failureLog = new \ChimMindPoisoning\RequestLog(static function (string $json) use (&$failureRecords): void { $failureRecords[] = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }, false);
same('failed', pcv_reflection_evaluate_with_store($ack, $failureStore, static function (): never { throw new RuntimeException('private provider failure'); }, $failureDirectory, static fn(): array => reflectionScopeFixture(), $failureLog), 'Provider exceptions should be reported as failures.');
same(25, $failureStore->npcs[11]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Provider failure must not persist opinions.');
$failureSummary = null;
foreach (array_reverse($failureRecords) as $entry) {
    if (($entry['event'] ?? null) === 'request_finished') { $failureSummary = $entry; break; }
}
same('failed', $failureSummary['outcome'] ?? null, 'MP diagnostics should label provider failure as failed.');
same('model_request_failed', $failureSummary['reason'] ?? null, 'MP diagnostics should retain the fixed provider failure code.');
$failureUnavailable = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.observer_unavailable'
    && ($entry['context']['correlation']['event_id'] ?? null) === '200'
    && ($entry['context']['correlation']['utterance_id'] ?? null) === $id));
same($mpObserverSupported ? 0 : 5, count($failureUnavailable),
    'A provider-failure ACK should report observer unavailability only when the optional observer API is absent.');
$providerError = array_values(array_filter(reflectionPcvLogEntries(), static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.ack_error'
    && ($entry['reason'] ?? null) === 'evaluation_failed'
    && ($entry['context']['correlation']['event_id'] ?? null) === '200'
    && ($entry['context']['correlation']['utterance_id'] ?? null) === $id));
same(1, count($providerError), 'A returned provider failure should have one fixed PCV error with the exact ACK correlation.');
same('error', $providerError[0]['severity'] ?? null, 'A returned provider failure must not be informational.');
same(3, reflectionPcvEventCount('reflection.evaluation_finished', $id),
    'A failed provider return must not increment accepted adapter completions.');

$corruptDirectory = $testRoot . DIRECTORY_SEPARATOR . 'corrupt';
same('registered', reflectionRegister(reflectionStore($id), $corruptDirectory, $wire), 'Register corruption fixture.');
file_put_contents($corruptDirectory . DIRECTORY_SEPARATOR . 'reflection.json', '{}');
@chmod($corruptDirectory . DIRECTORY_SEPARATOR . 'reflection.json', 0600);
same('registry_corrupt', pcv_reflection_evaluate_with_store($ack, reflectionStore($id), static fn(): string => '', $corruptDirectory), 'Corrupt registry data must fail closed.');

$pcvPath = pcv_log_path();
$pcvLogs = is_string($pcvPath) && is_file($pcvPath) ? (string)file_get_contents($pcvPath) : '';
$mpLogs = json_encode(array_merge($mpRecords, $failureRecords), JSON_THROW_ON_ERROR);
$pcvEntries = array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR), array_filter(explode("\n", $pcvLogs), static fn(string $line): bool => $line !== ''));
check(!str_contains($pcvLogs . $mpLogs, $subtitle), 'PCV and MP logs must not contain raw speech.');
check(!str_contains($pcvLogs . $mpLogs, hash('sha256', $subtitle)), 'PCV and MP logs must not contain the private speech hash.');
check(is_string($claimToken) && preg_match('/\A[a-f0-9]{32}\z/D', $claimToken) === 1 && !str_contains($pcvLogs . $mpLogs, $claimToken), 'PCV and MP logs must not expose registry claim tokens.');
check(str_contains($pcvLogs, 'reflection.evaluation_finished')
    && ($mpObserverSupported ? !str_contains($pcvLogs, 'reflection.observer_unavailable') : str_contains($pcvLogs, 'reflection.observer_unavailable'))
    && str_contains($pcvLogs, 'reflection.ack_error'),
    'PCV logs should report adapter returns, optional observer availability accurately, and registry failures.');
$missingMindPoisoning = array_values(array_filter($pcvEntries, static fn(array $entry): bool => ($entry['event'] ?? null) === 'reflection.registration_skipped' && ($entry['reason'] ?? null) === 'mind_poisoning_unavailable'));
check(count($missingMindPoisoning) === 1 && ($missingMindPoisoning[0]['severity'] ?? null) === 'info', 'Missing optional MP support should be an informational skip.');
$moduleSource = (string)file_get_contents(__DIR__ . '/../server/reflection.php');
check(str_contains($moduleSource, "dirname(__DIR__, 2) . '/ext/mind_poisoning/reflection.php'"), 'The flat installed extension layout should resolve Mind Poisoning from the engine root.');

echo "PCV reflection registry checks passed.\n";
