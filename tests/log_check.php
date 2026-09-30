<?php
declare(strict_types=1);

if (($argv[1] ?? null) === '--debug-child' || ($argv[1] ?? null) === '--writer-child') {
    define('PCV_LOG_TESTING', true);
    if ($argv[1] === '--debug-child') {
        putenv('PCV_LOG_DEBUG_UNTIL=' . (time() + 120));
    }
    require_once dirname(__DIR__) . '/server/log.php';
    if (!pcv_log_set_test_directory($argv[2] ?? '')) {
        exit(2);
    }
    if ($argv[1] === '--debug-child') {
        pcv_log_event('routing.request_detail', 'debug', 'ok', 'debug_detail', [
            'phase' => 'context_pre',
            'decision' => 'context_prepared',
            'request_type' => 'instruction',
        ]);
    } else {
        $count = filter_var($argv[3] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($count === false) {
            exit(3);
        }
        for ($index = 0; $index < $count; $index++) {
            pcv_log_event('ui.page_open', 'info', 'ok');
        }
    }
    exit(0);
}

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';

function logCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cleanLogFixture(string $directory, string $fallback): void
{
    foreach (['events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl', 'events.lock', 'fallback.log'] as $name) {
        @unlink($directory . DIRECTORY_SEPARATOR . $name);
    }
    @unlink($fallback);
    @rmdir($directory);
}

$fixture = sys_get_temp_dir() . '/chim-private-conversation-log-check-' . bin2hex(random_bytes(8));
$fallback = $fixture . '-fallback.log';
$oldErrorLog = ini_get('error_log');
$exitCode = 0;

try {
    logCheck(@mkdir($fixture, 0700), 'could not create isolated log directory');
    logCheck(pcv_log_set_test_directory($fixture), 'logger rejected isolated test directory');
    ini_set('error_log', $fallback);
    putenv('PCV_LOG_DEBUG_UNTIL');
    logCheck(!pcv_log_debug_setting_is_active(), 'debug should be off without an expiry setting');
    putenv('PCV_LOG_DEBUG_UNTIL=' . (time() + PCV_LOG_DEBUG_MAX_SECONDS + 1));
    logCheck(!pcv_log_debug_setting_is_active(), 'debug expiry must be limited to one hour');
    putenv('PCV_LOG_DEBUG_UNTIL');

    $configId = pcv_log_new_uuid();
    $playthroughKey = hash('sha256', 'fixture-playthrough');
    logCheck(is_string($configId), 'secure config ID generator should return an ID');
    pcv_log_begin_request($configId);
    pcv_log_set_playthrough_ref($playthroughKey);
    pcv_log_event('state.scope_staged', 'info', 'ok', null, [
        'action' => 'enable',
        'scene_mode' => 'solo',
        'actor_a_id' => '101',
        'actor_b_id' => '202',
        'exclude_player' => true,
        'bystander_mode' => 'silent',
        'dialogue' => 'DO NOT LOG THIS SECRET',
    ]);
    $exception = new RuntimeException('DO NOT LOG THIS EXCEPTION MESSAGE', 37);
    pcv_log_exception('state.unavailable', 'error', 'unavailable', 'state_read_failed', $exception, ['operation' => 'read']);
    pcv_log_event('ui.unavailable', 'error', 'unavailable', 'catalog_unavailable', ['operation' => 'catalog']);
    pcv_log_event('ui.scope_stage_failed', 'error', 'failed', 'readback_mismatch', [
        'action' => 'enable',
        'operation' => 'readback',
    ]);
    pcv_log_event('routing.request_detail', 'debug', 'ok', 'debug_detail', [
        'phase' => 'context_pre',
        'decision' => 'context_prepared',
        'request_type' => 'instruction',
        'audience_before_count' => 8,
        'audience_after_count' => 2,
    ]);
    pcv_log_event('state.scope_skipped', 'info', 'skipped', 'scene_not_eligible', [
        'operation' => 'begin',
        'scene_mode' => 'solo',
    ]);
    $reflectionContext = ['phase' => 'registration', 'route' => 'solo_reflection', 'actor_a_id' => '101',
        'utterance_id' => 'DO NOT LOG THIS ID', 'speech_hash' => 'DO NOT LOG THIS DIGEST', 'speech' => 'DO NOT LOG THIS SPEECH'];
    pcv_log_event('reflection.registration_skipped', 'info', 'skipped', 'baseline_stale', $reflectionContext);
    pcv_log_event('reflection.registration_error', 'error', 'failed', 'registry_corrupt', $reflectionContext);
    pcv_log_event('reflection.output_registered', 'info', 'accepted', null, $reflectionContext);
    pcv_log_event('reflection.ack_skipped', 'info', 'skipped', 'evaluation_rejected', array_replace($reflectionContext, ['phase' => 'ack']));
    pcv_log_event('reflection.ack_error', 'error', 'failed', 'database_unavailable', array_replace($reflectionContext, ['phase' => 'ack']));
    pcv_log_event('reflection.evaluation_finished', 'info', 'accepted', null, array_replace($reflectionContext, ['phase' => 'ack']));

    $path = pcv_log_path();
    logCheck(is_string($path) && is_file($path), 'CLI path accessor should return the isolated JSONL file');
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    logCheck(is_array($lines) && count($lines) === 11, 'debug event should be suppressed while ordinary events are retained');
    $entries = array_map(static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR), $lines);
    $manifest = json_decode((string)file_get_contents(dirname(__DIR__) . '/server/manifest.json'), true, 16, JSON_THROW_ON_ERROR);
    foreach ($entries as $entry) {
        logCheck(($entry['schema_version'] ?? null) === PCV_LOG_SCHEMA_VERSION, 'schema version missing');
        logCheck(($entry['plugin_version'] ?? null) === ($manifest['version'] ?? null), 'plugin version should come from manifest');
        logCheck(is_string($entry['request_id'] ?? null) && $entry['request_id'] === pcv_log_request_id(), 'request ID should be stable');
        logCheck(($entry['config_id'] ?? null) === $configId, 'config ID should correlate the request');
        logCheck(($entry['playthrough_ref'] ?? null) === substr(hash('sha256', $playthroughKey), 0, 16), 'playthrough key should be pseudonymized');
        logCheck(is_int($entry['elapsed_ms'] ?? null), 'monotonic elapsed time missing');
        logCheck(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/D', $entry['timestamp'] ?? '') === 1,
            'timestamp must be UTC with millisecond precision');
    }
    logCheck(!str_contains((string)file_get_contents($path), 'DO NOT LOG THIS'), 'raw exception/dialogue text leaked');
    logCheck(!str_contains((string)file_get_contents($path), $playthroughKey), 'full playthrough key leaked');
    logCheck(($entries[0]['context']['actor_a_id'] ?? null) === '101', 'approved actor IDs should be retained');
    logCheck(($entries[0]['context']['scene_mode'] ?? null) === 'solo', 'scene mode should be retained as a fixed enum');
    logCheck(!array_key_exists('dialogue', $entries[0]['context']), 'unapproved context must be omitted');
    logCheck(($entries[1]['context']['exception_class'] ?? null) === RuntimeException::class, 'exception class should be structured');
    logCheck(($entries[1]['context']['exception_code'] ?? null) === 37, 'exception code should be structured');
    logCheck(preg_match('/^[A-Za-z0-9_.-]+$/D', $entries[1]['context']['source_file'] ?? '') === 1, 'exception path should be reduced to basename');
    logCheck(($entries[2]['reason'] ?? null) === 'catalog_unavailable'
        && ($entries[2]['context']['operation'] ?? null) === 'catalog', 'UI unavailable event should retain fixed failure fields');
    logCheck(($entries[3]['reason'] ?? null) === 'readback_mismatch'
        && ($entries[3]['context']['operation'] ?? null) === 'readback', 'UI stage-failure event should retain fixed failure fields');
    logCheck(($entries[4]['event'] ?? null) === 'state.scope_skipped'
        && ($entries[4]['severity'] ?? null) === 'info'
        && ($entries[4]['reason'] ?? null) === 'scene_not_eligible'
        && ($entries[4]['context']['scene_mode'] ?? null) === 'solo',
        'expected actor ineligibility should be logged as a fixed informational skip');
    $reflectionEntries = array_values(array_filter($entries, static fn(array $entry): bool => str_starts_with((string)($entry['event'] ?? ''), 'reflection.')));
    logCheck(count($reflectionEntries) === 6
        && ($reflectionEntries[0]['reason'] ?? null) === 'baseline_stale'
        && ($reflectionEntries[1]['severity'] ?? null) === 'error'
        && ($reflectionEntries[2]['outcome'] ?? null) === 'accepted'
        && ($reflectionEntries[3]['context']['phase'] ?? null) === 'ack'
        && ($reflectionEntries[4]['reason'] ?? null) === 'database_unavailable'
        && ($reflectionEntries[5]['event'] ?? null) === 'reflection.evaluation_finished',
        'reflection log entries must use the fixed skip/error/accepted contracts');
    logCheck(!str_contains((string)file_get_contents($path), 'DO NOT LOG THIS ID')
        && !str_contains((string)file_get_contents($path), 'DO NOT LOG THIS DIGEST')
        && !str_contains((string)file_get_contents($path), 'DO NOT LOG THIS SPEECH'),
        'reflection log context leaked an output ID, digest, or speech');

    $permissions = fileperms($path) & 0777;
    logCheck($permissions === 0600, 'log file should be owner-only');
    $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
    logCheck($uid !== null && fileowner($path) === $uid, 'log file should belong to the effective server user');

    foreach ([1, 2, 3, 4] as $index) {
        $rotation = $fixture . '/events.' . $index . '.jsonl';
        file_put_contents($rotation, 'old rotation ' . $index . "\n");
        chmod($rotation, 0600);
    }
    file_put_contents($path, str_repeat('x', PCV_LOG_MAX_FILE_BYTES));
    chmod($path, 0600);
    pcv_log_event('ui.page_open', 'info', 'ok');
    logCheck(is_file($fixture . '/events.1.jsonl'), 'full log should rotate before append');
    logCheck(filesize($fixture . '/events.1.jsonl') <= PCV_LOG_MAX_FILE_BYTES, 'rotated log exceeded size limit');
    logCheck(filesize($path) <= PCV_LOG_MAX_ENTRY_BYTES, 'active entry exceeded size limit');
    logCheck(!file_exists($fixture . '/events.5.jsonl'), 'rotation should retain only four old files');
    $segments = glob($fixture . '/events*.jsonl');
    logCheck(is_array($segments) && count($segments) <= PCV_LOG_MAX_FILES, 'rotation must cap JSONL segments at five');

    $debugDirectory = $fixture . '/debug';
    mkdir($debugDirectory, 0700);
    $debugProcess = proc_open([PHP_BINARY, __FILE__, '--debug-child', $debugDirectory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $debugPipes);
    logCheck(is_resource($debugProcess), 'could not start isolated debug fixture process');
    fclose($debugPipes[0]);
    stream_get_contents($debugPipes[1]);
    stream_get_contents($debugPipes[2]);
    fclose($debugPipes[1]);
    fclose($debugPipes[2]);
    logCheck(proc_close($debugProcess) === 0, 'debug fixture process failed');
    $debugLines = file($debugDirectory . '/events.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $debugEntry = is_array($debugLines) ? json_decode($debugLines[0] ?? '', true, 16, JSON_THROW_ON_ERROR) : null;
    logCheck(($debugEntry['severity'] ?? null) === 'debug', 'unexpired server debug setting should admit debug event');
    foreach (['events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl', 'events.lock'] as $name) {
        @unlink($debugDirectory . '/' . $name);
    }
    rmdir($debugDirectory);

    $concurrentDirectory = $fixture . '/concurrent';
    mkdir($concurrentDirectory, 0700);
    $writers = [];
    for ($index = 0; $index < 3; $index++) {
        $process = proc_open([PHP_BINARY, __FILE__, '--writer-child', $concurrentDirectory, '30'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        logCheck(is_resource($process), 'could not start concurrent writer');
        fclose($pipes[0]);
        $writers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }
    foreach ($writers as $writer) {
        stream_get_contents($writer['stdout']);
        stream_get_contents($writer['stderr']);
        fclose($writer['stdout']);
        fclose($writer['stderr']);
        logCheck(proc_close($writer['process']) === 0, 'concurrent writer failed');
    }
    $concurrentLines = file($concurrentDirectory . '/events.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    logCheck(is_array($concurrentLines) && count($concurrentLines) > 0, 'concurrent writers produced no records');
    foreach ($concurrentLines as $line) {
        $concurrentEntry = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
        logCheck(($concurrentEntry['event'] ?? null) === 'ui.page_open', 'concurrent write produced malformed JSONL');
    }
    foreach (['events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl', 'events.lock'] as $name) {
        @unlink($concurrentDirectory . '/' . $name);
    }
    rmdir($concurrentDirectory);

    $unsafeDirectory = $fixture . '/unsafe';
    mkdir($unsafeDirectory, 0700);
    $marker = $unsafeDirectory . '/marker';
    file_put_contents($marker, 'keep');
    chmod($unsafeDirectory, 0755);
    ob_start();
    $acceptedUnsafeDirectory = pcv_log_set_test_directory($unsafeDirectory);
    $output = ob_get_clean();
    logCheck(!$acceptedUnsafeDirectory && $output === '', 'unsafe directory must be rejected without response output');
    logCheck((fileperms($unsafeDirectory) & 0777) === 0755 && file_get_contents($marker) === 'keep',
        'rejected directory must remain untouched');
    chmod($unsafeDirectory, 0700);
    unlink($marker);
    rmdir($unsafeDirectory);

    $lockPath = $fixture . '/events.lock';
    $lock = fopen($lockPath, 'c');
    logCheck(is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB), 'could not hold fixture lock');
    pcv_log_event('ui.page_open', 'info', 'ok');
    pcv_log_event('ui.page_open', 'info', 'ok');
    flock($lock, LOCK_UN);
    fclose($lock);
    $fallbackLines = is_file($fallback) ? file($fallback, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    logCheck(is_array($fallbackLines) && count($fallbackLines) === 1, 'lock contention should trigger one generic fallback per request');
    $fallbackText = (string)file_get_contents($fallback);
    logCheck(str_contains($fallbackText, 'request_id=' . pcv_log_request_id()), 'fallback should identify the request');
    logCheck(str_contains($fallbackText, 'config_id=' . $configId), 'fallback should identify the active config when valid');
    logCheck(!str_contains($fallbackText, 'DO NOT LOG THIS'), 'fallback should not contain raw exception text');

    echo "PASS: schema/redaction, IDs, debug window, five-segment rotation, private permissions, concurrent JSONL, unsafe-path rejection, lock fallback\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if (is_string($oldErrorLog)) {
        ini_set('error_log', $oldErrorLog);
    }
    cleanLogFixture($fixture, $fallback);
}

exit($exitCode);
