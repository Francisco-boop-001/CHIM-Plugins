<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
require_once __DIR__ . '/../server/log.php';
define('PCV_DIAGNOSTICS_TEST', true);
require_once __DIR__ . '/../server/diagnostics.php';

if (($argv[1] ?? null) === '--fifo-reader-child') {
    putenv('PCV_LOG_DIR=' . ($argv[2] ?? ''));
    $result = pcv_diagnostics_load();
    echo $result['status'] ?? 'unavailable';
    exit(0);
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function run_diagnostics(array $arguments): array
{
    $command = array_merge([PHP_BINARY, __DIR__ . '/../server/diagnostics.php'], $arguments);
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the isolated diagnostics process.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['status' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
}

function fixture_entry(string $requestId, string $configId, string $event, array $context = []): string
{
    $rules = pcv_log_event_rules()[$event];
    $entry = [
        'schema_version' => PCV_LOG_SCHEMA_VERSION,
        'plugin_version' => '0.1.1',
        'timestamp' => '2026-09-27T12:00:00.123Z',
        'event' => $event,
        'severity' => $rules['severity'],
        'outcome' => $rules['outcome'],
        'reason' => null,
        'request_id' => $requestId,
        'config_id' => $configId,
        'playthrough_ref' => '0123456789abcdef',
        'elapsed_ms' => 12,
        'context' => $context,
        'raw_capture' => "private dialogue\nMUST_NOT_EXPORT",
    ];
    return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}

function private_directory(string $path): void
{
    if (!mkdir($path, 0700) && !is_dir($path)) {
        throw new RuntimeException('Could not create an isolated diagnostics fixture.');
    }
    chmod($path, 0700);
}

function fixture_file(string $directory, string $name, string $contents): void
{
    $path = $directory . DIRECTORY_SEPARATOR . $name;
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Could not create an isolated log fixture.');
    }
    chmod($path, 0600);
}

function remove_fixture(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    foreach (new DirectoryIterator($directory) as $item) {
        if (!$item->isDot()) {
            @unlink($item->getPathname());
        }
    }
    @rmdir($directory);
}

function free_loopback_port(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if (!is_resource($socket)) {
        throw new RuntimeException('Could not reserve a loopback port for the HTTP guard check.');
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    return (int)substr(strrchr((string)$address, ':'), 1);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-diag-check-' . bin2hex(random_bytes(6));
$logDirectory = $root . DIRECTORY_SEPARATOR . 'logs';
$emptyDirectory = $root . DIRECTORY_SEPARATOR . 'empty';
$busyDirectory = $root . DIRECTORY_SEPARATOR . 'busy';
$largeDirectory = $root . DIRECTORY_SEPARATOR . 'large';
$fifoDirectory = $root . DIRECTORY_SEPARATOR . 'fifo';
private_directory($root);
private_directory($logDirectory);
private_directory($emptyDirectory);
private_directory($busyDirectory);
private_directory($largeDirectory);
private_directory($fifoDirectory);
check(pcv_diagnostics_acquire_snapshot($emptyDirectory . '/missing/events.jsonl')['status'] === 'empty',
    'A safe default directory that has not been created yet must report no entries.');

$requestA = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$requestB = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$configA = '11111111-1111-4111-8111-111111111111';
$configB = '22222222-2222-4222-8222-222222222222';
$server = null;
$lock = null;
$oldLogDirectory = getenv('PCV_LOG_DIR');

try {
    fixture_file($logDirectory, 'events.4.jsonl', fixture_entry(
        $requestA,
        $configA,
        'state.scope_staged',
        ['action' => 'enable', 'actor_a_id' => '101', 'actor_b_id' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude']
    ));
    fixture_file($logDirectory, 'events.1.jsonl', fixture_entry($requestB, $configB, 'ui.page_open'));
    $unsupportedRevision = json_decode(fixture_entry($requestA, $configA, 'state.scope_staged'), true, 16, JSON_THROW_ON_ERROR);
    $unsupportedRevision['logging_revision'] = 99;
    $contradictory = json_decode(fixture_entry($requestA, $configA, 'routing.request_started'), true, 16, JSON_THROW_ON_ERROR);
    $contradictory['event'] = 'routing.request_finished';
    $contradictory['severity'] = 'info';
    $contradictory['outcome'] = 'scoped_output';
    $contradictory['reason'] = 'fatal_error';
    fixture_file($logDirectory, 'events.jsonl',
        fixture_entry($requestA, $configB, 'ui.page_open')
        . fixture_entry($requestA, $configA, 'routing.request_started', ['request_type' => 'inputtext'])
        . "{malformed json}\n"
        . str_repeat('x', PCV_LOG_MAX_ENTRY_BYTES + 20) . "\n"
        . json_encode(['schema_version' => 99, 'event' => 'ui.page_open'], JSON_THROW_ON_ERROR) . "\n"
        . json_encode($unsupportedRevision, JSON_THROW_ON_ERROR) . "\n"
        . json_encode($contradictory, JSON_THROW_ON_ERROR) . "\n"
    );
    fixture_file($logDirectory, 'events.5.jsonl', fixture_entry($requestA, $configA, 'ui.page_open'));
    fixture_file($logDirectory, 'events.lock', '');

    putenv('PCV_LOG_DIR=' . $logDirectory);
    $loaded = pcv_diagnostics_load(['request' => $requestA, 'config' => $configA, 'limit' => 1]);
    check(($loaded['status'] ?? null) === 'ok' && count($loaded['entries'] ?? []) === 1
        && ($loaded['entries'][0]['event'] ?? null) === 'routing.request_started',
        'The shared reader must apply bounded latest-match filters.');
    check(($loaded['health']['omissions']['capped'] ?? null) === 1
        && ($loaded['health']['omissions']['malformed'] ?? null) === 2
        && ($loaded['health']['omissions']['oversized'] ?? null) === 1
        && ($loaded['health']['omissions']['unknown_schema'] ?? null) === 1
        && ($loaded['health']['omissions']['unsupported_revision'] ?? null) === 1
        && ($loaded['health']['omissions']['filtered'] ?? 0) > 0,
        'Reader health must separate malformed, oversized, unknown-schema, unsupported-revision, filtered, and capped rows.');
    $legacy = pcv_diagnostics_load(['request' => $requestA, 'config' => $configA, 'limit' => 10]);
    $legacyEntry = $legacy['entries'][0] ?? [];
    check(($legacyEntry['event'] ?? null) === 'state.scope_staged'
        && !array_key_exists('logging_revision', $legacyEntry)
        && !array_key_exists('correlation', $legacyEntry['context'] ?? []),
        'Schema-1 rows without the instrumentation revision or correlation fields must remain readable as legacy rows.');

    $snapshot = pcv_diagnostics_acquire_snapshot($logDirectory . '/events.jsonl');
    check(($snapshot['status'] ?? null) === 'ok' && count($snapshot['files'] ?? []) > 0,
        'A valid bounded snapshot should retain opened file handles.');
    $snapshotLock = fopen($logDirectory . '/events.lock', 'rb');
    check(is_resource($snapshotLock) && flock($snapshotLock, LOCK_EX | LOCK_NB),
        'Snapshot parsing must release the shared logger lock before reading captured bytes.');
    flock($snapshotLock, LOCK_UN);
    fclose($snapshotLock);
    foreach ($snapshot['files'] as $file) {
        if (is_resource($file['handle'] ?? null)) {
            fclose($file['handle']);
        }
    }

    $filtered = run_diagnostics(['--request', $requestA, '--config', $configA, '--limit', '10', '--jsonl']);
    check($filtered['status'] === 0, 'Request/config filtering failed across rotated logs: ' . $filtered['stderr']);
    $rows = array_map(static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        array_filter(explode("\n", trim($filtered['stdout'])), static fn(string $line): bool => $line !== ''));
    check(count($rows) === 2, 'Filters must return only matching rows from the five-file rotation set.');
    check($rows[0]['event'] === 'state.scope_staged' && $rows[1]['event'] === 'routing.request_started', 'Matching rotated rows must be returned oldest to newest.');
    check(!str_contains($filtered['stdout'], 'MUST_NOT_EXPORT') && !str_contains($filtered['stdout'], 'private dialogue'), 'Raw extra fields must never be exported.');

    $correlated = json_decode(fixture_entry($requestA, $configA, 'state.scope_staged', ['action' => 'enable', 'scene_mode' => 'solo', 'actor_a_id' => '101']), true, 16, JSON_THROW_ON_ERROR);
    $correlated['logging_revision'] = 2;
    $correlated['context']['correlation'] = [
        'event_id' => '1234',
        'utterance_id' => 'utt_abcdefgh12345678',
        'linked_request_id' => 'abcdefabcdefabcdefabcdef',
        'speech_hash' => 'must_not_export',
    ];
    file_put_contents($logDirectory . '/events.jsonl', json_encode($correlated, JSON_THROW_ON_ERROR) . "\n", FILE_APPEND);
    $correlationFilter = pcv_diagnostics_load([
        'request' => $requestA,
        'config' => $configA,
        'event' => 'state.scope_staged',
        'severity' => 'info',
        'event_id' => '1234',
        'utterance_id' => 'utt_abcdefgh12345678',
        'linked_request_id' => 'abcdefabcdefabcdefabcdef',
        'limit' => 10,
    ]);
    check(($correlationFilter['status'] ?? null) === 'ok' && count($correlationFilter['entries'] ?? []) === 1
        && !str_contains(json_encode($correlationFilter, JSON_THROW_ON_ERROR), 'speech_hash')
        && !str_contains(json_encode($correlationFilter, JSON_THROW_ON_ERROR), $logDirectory),
        'Event/severity/correlation filters must return only the sanitized matching revision-2 row.');
    $cliFiltered = run_diagnostics(['--request', $requestA, '--config', $configA, '--event', 'state.scope_staged',
        '--severity', 'info', '--event-id', '1234', '--jsonl']);
    $cliRows = array_map(static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        array_filter(explode("\n", trim($cliFiltered['stdout'])), static fn(string $line): bool => $line !== ''));
    check($cliFiltered['status'] === 0 && count($cliRows) === 1
        && ($cliRows[0]['logging_revision'] ?? null) === 2
        && str_contains($cliFiltered['stderr'], 'filtered=')
        && str_contains($cliFiltered['stderr'], 'capped='),
        'CLI filters must use the shared reader and preserve the instrumentation revision.');

    $readable = run_diagnostics(['--request=' . $requestA, '--limit=1']);
    check($readable['status'] === 0 && str_contains($readable['stdout'], 'state.scope_staged'), 'Default output must be readable and honor the bounded latest-match limit.');
    check(!str_contains($readable['stdout'], $requestB), 'Request filter returned an unrelated request.');
    check(run_diagnostics(['--limit', '1001'])['status'] === 2, 'A limit above 1000 must be rejected.');
    check(run_diagnostics(['--path', '/etc/passwd'])['status'] === 2, 'Caller-supplied log paths must be rejected.');

    putenv('PCV_LOG_DIR=' . $emptyDirectory);
    $empty = run_diagnostics(['--jsonl']);
    check($empty['status'] === 0 && trim($empty['stdout']) === '', 'An empty log directory must be handled without errors or output.');
    $emptyLoaded = pcv_diagnostics_load(['limit' => 10]);
    check(($emptyLoaded['status'] ?? null) === 'empty'
        && ($emptyLoaded['health']['storage']['storage_mode'] ?? null) === 'external'
        && ($emptyLoaded['health']['completeness'] ?? null) === 'bounded_history',
        'Reader health must label empty external storage and bounded-history completeness.');

    putenv('PCV_LOG_DIR=' . $busyDirectory);
    $lockPath = $busyDirectory . DIRECTORY_SEPARATOR . 'events.lock';
    $lock = fopen($lockPath, 'c');
    check(is_resource($lock), 'Could not open the isolated lock fixture.');
    chmod($lockPath, 0600);
    check(flock($lock, LOCK_EX | LOCK_NB), 'Could not hold the isolated writer lock.');
    $busy = run_diagnostics([]);
    check($busy['status'] === 1 && str_contains($busy['stderr'], 'busy'), 'A held logger lock must fail gracefully without reading a rotating snapshot.');
    flock($lock, LOCK_UN);
    fclose($lock);
    $lock = null;

    putenv('PCV_LOG_DIR=' . $largeDirectory);
    $largePath = $largeDirectory . DIRECTORY_SEPARATOR . 'events.jsonl';
    $large = fopen($largePath, 'wb');
    check(is_resource($large), 'Could not create the oversized file fixture.');
    check(ftruncate($large, PCV_LOG_MAX_FILE_BYTES + 1), 'Could not size the oversized fixture.');
    fclose($large);
    chmod($largePath, 0600);
    fixture_file($largeDirectory, 'events.lock', '');
    $oversized = run_diagnostics([]);
    check($oversized['status'] === 1 && !str_contains($oversized['stderr'], $largePath), 'An oversized segment must be rejected without disclosing its path.');

    if (!function_exists('posix_mkfifo')) {
        throw new RuntimeException('PHP posix_mkfifo is required for the bounded named-pipe reader check.');
    }
    check(posix_mkfifo($fifoDirectory . DIRECTORY_SEPARATOR . 'events.jsonl', 0600), 'Could not create named-pipe segment fixture.');
    fixture_file($fifoDirectory, 'events.lock', '');
    $fifoProcess = proc_open([PHP_BINARY, __FILE__, '--fifo-reader-child', $fifoDirectory], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $fifoPipes, null, null, ['bypass_shell' => true]);
    check(is_resource($fifoProcess), 'Could not start bounded named-pipe reader fixture.');
    fclose($fifoPipes[0]);
    stream_set_blocking($fifoPipes[1], false);
    stream_set_blocking($fifoPipes[2], false);
    $fifoOutput = '';
    $fifoError = '';
    $fifoDeadline = microtime(true) + 2.0;
    $fifoFinished = false;
    do {
        $fifoOutput .= stream_get_contents($fifoPipes[1]);
        $fifoError .= stream_get_contents($fifoPipes[2]);
        $fifoStatus = proc_get_status($fifoProcess);
        if (!$fifoStatus['running']) {
            $fifoFinished = true;
            break;
        }
        usleep(20000);
    } while (microtime(true) < $fifoDeadline);
    if (!$fifoFinished) {
        proc_terminate($fifoProcess);
    }
    fclose($fifoPipes[1]);
    fclose($fifoPipes[2]);
    proc_close($fifoProcess);
    check($fifoFinished && trim($fifoOutput) === 'unavailable',
        'A named-pipe segment must be rejected promptly without blocking or reading it: ' . $fifoError);

    putenv('PCV_LOG_DIR=' . $logDirectory);
    $port = free_loopback_port();
    $serverOutput = $root . DIRECTORY_SEPARATOR . 'server.stdout';
    $serverError = $root . DIRECTORY_SEPARATOR . 'server.stderr';
    $server = proc_open([
        PHP_BINARY,
        '-S',
        '127.0.0.1:' . $port,
        '-t',
        __DIR__ . '/../server',
    ], [
        0 => ['pipe', 'r'],
        1 => ['file', $serverOutput, 'a'],
        2 => ['file', $serverError, 'a'],
    ], $serverPipes, null, null, ['bypass_shell' => true]);
    check(is_resource($server), 'Could not start the loopback-only HTTP guard fixture.');
    fclose($serverPipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @stream_socket_client('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage, 0.05);
        if (is_resource($connection)) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(20000);
    }
    check($ready, 'The loopback HTTP fixture did not become ready.');
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 2]]);
    $body = @file_get_contents('http://127.0.0.1:' . $port . '/diagnostics.php', false, $context);
    $statusLine = $http_response_header[0] ?? '';
    check(is_string($body) && str_contains($statusLine, ' 404 '), 'HTTP callers must be rejected before diagnostics are loaded.');
    check(!str_contains($body, 'state.scope_staged') && !str_contains($body, 'MUST_NOT_EXPORT'), 'HTTP response exposed diagnostic content.');

    echo "Diagnostics checks passed.\n";
} finally {
    if (is_resource($lock)) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
    if (is_resource($server)) {
        @proc_terminate($server);
        @proc_close($server);
    }
    if ($oldLogDirectory === false) {
        putenv('PCV_LOG_DIR');
    } else {
        putenv('PCV_LOG_DIR=' . $oldLogDirectory);
    }
    foreach ([$logDirectory, $emptyDirectory, $busyDirectory, $largeDirectory, $fifoDirectory, $root] as $directory) {
        remove_fixture($directory);
    }
}
