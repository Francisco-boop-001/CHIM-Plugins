<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
require_once __DIR__ . '/../server/log.php';
define('PCV_DIAGNOSTICS_TEST', true);
require_once __DIR__ . '/../server/diagnostics.php';

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
private_directory($root);
private_directory($logDirectory);
private_directory($emptyDirectory);
private_directory($busyDirectory);
private_directory($largeDirectory);
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
    fixture_file($logDirectory, 'events.jsonl',
        fixture_entry($requestA, $configB, 'ui.page_open')
        . fixture_entry($requestA, $configA, 'routing.request_started', ['request_type' => 'inputtext'])
    );
    fixture_file($logDirectory, 'events.5.jsonl', fixture_entry($requestA, $configA, 'ui.page_open'));
    fixture_file($logDirectory, 'events.lock', '');

    putenv('PCV_LOG_DIR=' . $logDirectory);
    $filtered = run_diagnostics(['--request', $requestA, '--config', $configA, '--limit', '10', '--jsonl']);
    check($filtered['status'] === 0, 'Request/config filtering failed across rotated logs: ' . $filtered['stderr']);
    $rows = array_map(static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        array_filter(explode("\n", trim($filtered['stdout'])), static fn(string $line): bool => $line !== ''));
    check(count($rows) === 2, 'Filters must return only matching rows from the five-file rotation set.');
    check($rows[0]['event'] === 'state.scope_staged' && $rows[1]['event'] === 'routing.request_started', 'Matching rotated rows must be returned oldest to newest.');
    check(!str_contains($filtered['stdout'], 'MUST_NOT_EXPORT') && !str_contains($filtered['stdout'], 'private dialogue'), 'Raw extra fields must never be exported.');

    $readable = run_diagnostics(['--request=' . $requestA, '--limit=1']);
    check($readable['status'] === 0 && str_contains($readable['stdout'], 'routing.request_started'), 'Default output must be readable and honor the bounded latest-match limit.');
    check(!str_contains($readable['stdout'], $requestB), 'Request filter returned an unrelated request.');
    check(run_diagnostics(['--limit', '1001'])['status'] === 2, 'A limit above 1000 must be rejected.');
    check(run_diagnostics(['--path', '/etc/passwd'])['status'] === 2, 'Caller-supplied log paths must be rejected.');

    putenv('PCV_LOG_DIR=' . $emptyDirectory);
    $empty = run_diagnostics(['--jsonl']);
    check($empty['status'] === 0 && trim($empty['stdout']) === '', 'An empty log directory must be handled without errors or output.');

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
    foreach ([$logDirectory, $emptyDirectory, $busyDirectory, $largeDirectory, $root] as $directory) {
        remove_fixture($directory);
    }
}
