<?php
declare(strict_types=1);

function timingCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function timingRemoveTestDirectory(string $path): void
{
    if (!str_starts_with($path, sys_get_temp_dir() . DIRECTORY_SEPARATOR) || !is_dir($path) || is_link($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) {
            @rmdir($item->getPathname());
        } elseif (!$item->isLink()) {
            @unlink($item->getPathname());
        }
    }
    @rmdir($path);
}

timingCheck(function_exists('proc_open'), 'The local PHP CLI must support isolated timing fixtures.');
$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv_reflection_timing_' . bin2hex(random_bytes(8));
timingCheck(mkdir($testRoot, 0700), 'Create the isolated timing fixture.');
register_shutdown_function(static fn() => timingRemoveTestDirectory($testRoot));

$server = $testRoot . '/webroot/HerikaServer/ext/private_conversation';
$mindPoisoning = $testRoot . '/webroot/HerikaServer/ext/mind_poisoning';
$logs = $testRoot . '/logs';
timingCheck(mkdir($server, 0700, true) && mkdir($mindPoisoning, 0700, true) && mkdir($logs, 0700),
    'Create the isolated extension layout.');
$source = dirname(__DIR__) . '/server';
foreach (['reflection.php', 'log.php'] as $file) {
    timingCheck(copy($source . '/' . $file, $server . '/' . $file), 'Copy isolated ' . $file . '.');
}

$stateSource = <<<'PHP'
<?php
function pcv_current_identity(bool $refresh = false): array { return ['key' => str_repeat('a', 64), 'player_name' => 'Player']; }
function pcv_valid_key($key): bool { return is_string($key) && preg_match('/\A[a-f0-9]{64}\z/D', $key) === 1; }
function pcv_state_directory(?string $directory): string { return $directory ?? (__DIR__ . '/state'); }
function pcv_lock_state(string $directory, bool $create, int $mode) { return null; }
function pcv_unlock_state($handle): void {}
PHP;
$scopeSource = <<<'PHP'
<?php
function pcvReadResolvedScope(): array { return $GLOBALS['pcv_test_scope'] ?? ['status' => 'off']; }
function pcv_scope_name_key(string $name): string
{
    $name = trim($name);
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}
PHP;
$legacyModule = <<<'PHP'
<?php
namespace ChimMindPoisoning;
function mindPoisoningEvaluateReflection(...$args): string { $GLOBALS['pcv_provider_calls']++; return 'committed'; }
function reflectionSourceParts(...$args): array { return []; }
class PostgresStoreDb
{
    public function __construct() { $GLOBALS['pcv_store_constructions']++; }
}
PHP;
timingCheck(file_put_contents($server . '/state.php', $stateSource) === strlen($stateSource)
    && file_put_contents($server . '/scope.php', $scopeSource) === strlen($scopeSource)
    && file_put_contents($mindPoisoning . '/reflection.php', $legacyModule) === strlen($legacyModule),
    'Write the isolated legacy API and scope fixtures.');

$runner = $testRoot . '/runner.php';
$runnerSource = <<<'PHP'
<?php
declare(strict_types=1);
define('PCV_LOG_TESTING', true);
require __SERVER__ . '/reflection.php';
if (!pcv_log_set_test_directory(__LOGS__)) {
    throw new RuntimeException('Could not isolate diagnostics.');
}

$GLOBALS['pcv_provider_calls'] = 0;
$GLOBALS['pcv_store_constructions'] = 0;
$GLOBALS['pcv_test_scope'] = [
    'status' => 'active',
    'config_id' => '123e4567-e89b-42d3-a456-426614174000',
    'actor_a_id' => '11',
    'scope' => ['scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null, 'exclude_player' => true],
];
$utteranceId = 'utt_1234567890abcdef';
$ack = ['_speech', '', '', json_encode([
    'speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => 'private test line', 'utterance_id' => $utteranceId,
], JSON_THROW_ON_ERROR)];
pcvReflectionEvaluateAck($ack);
$logPath = pcv_log_path();
$logText = is_string($logPath) && is_file($logPath) ? (string)file_get_contents($logPath) : '';
$entries = array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR),
    array_filter(explode("\n", $logText), static fn(string $line): bool => $line !== ''));
$misses = array_values(array_filter($entries, static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.ack_skipped'
    && ($entry['reason'] ?? null) === 'registration_missing'));
if (count($misses) !== 1 || ($misses[0]['severity'] ?? null) !== 'info'
    || ($misses[0]['context']['route'] ?? null) !== 'solo_reflection'
    || ($misses[0]['context']['phase'] ?? null) !== 'ack') {
    throw new RuntimeException('A valid ACK before registration must be an informational scoped skip.');
}
if (str_contains($logText, $utteranceId)
    || $GLOBALS['pcv_provider_calls'] !== 0 || $GLOBALS['pcv_store_constructions'] !== 0) {
    throw new RuntimeException('An unmatched ACK must not expose its ID or call the store/provider.');
}

pcvReflectionEvaluateAck(['_speech', '', '', json_encode(['speaker' => 'Aela', 'speech' => 'malformed no ID'], JSON_THROW_ON_ERROR)]);
$GLOBALS['pcv_test_scope']['scope']['scene_mode'] = 'solo';
$GLOBALS['pcv_test_scope']['scope']['actor_b'] = null;
pcvReflectionEvaluateAck(['_speech', '', '', json_encode([
    'speaker' => 'Lydia', 'listener' => 'Dragonborn', 'speech' => 'other NPC line', 'utterance_id' => $utteranceId,
], JSON_THROW_ON_ERROR)]);
pcvReflectionEvaluateAck(['_speech', '', '', json_encode([
    'speaker' => 'Aela', 'listener' => 'Lydia', 'speech' => 'unrelated listener line', 'utterance_id' => $utteranceId,
], JSON_THROW_ON_ERROR)]);
$GLOBALS['pcv_test_scope']['actor_a_id'] = ['malformed'];
set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});
try {
    pcvReflectionEvaluateAck($ack);
} finally {
    restore_error_handler();
}
$GLOBALS['pcv_test_scope']['actor_a_id'] = '11';
$GLOBALS['pcv_test_scope']['scope']['scene_mode'] = 'pair';
$GLOBALS['pcv_test_scope']['scope']['actor_b'] = '12';
pcvReflectionEvaluateAck($ack);
$GLOBALS['pcv_test_scope'] = ['status' => 'off'];
pcvReflectionEvaluateAck($ack);
$logText = is_string($logPath) && is_file($logPath) ? (string)file_get_contents($logPath) : '';
$afterQuietAcks = array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR),
    array_filter(explode("\n", $logText), static fn(string $line): bool => $line !== ''));
$afterQuietMisses = array_values(array_filter($afterQuietAcks, static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.ack_skipped'
    && ($entry['reason'] ?? null) === 'registration_missing'));
if (count($afterQuietMisses) !== 1) {
    throw new RuntimeException('Malformed or out-of-scope ACKs must remain quiet.');
}

pcvReflectionRegisterLastOutput(['route' => 'solo_reflection', 'config_id' => '123e4567-e89b-42d3-a456-426614174000', 'actor_a_id' => '11']);
$logText = is_string($logPath) && is_file($logPath) ? (string)file_get_contents($logPath) : '';
$afterCompatibility = array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR),
    array_filter(explode("\n", $logText), static fn(string $line): bool => $line !== ''));
$incompatible = array_values(array_filter($afterCompatibility, static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.registration_skipped'
    && ($entry['reason'] ?? null) === 'reflection_api_incompatible'));
if (count($incompatible) !== 1 || ($incompatible[0]['severity'] ?? null) !== 'info'
    || $GLOBALS['pcv_provider_calls'] !== 0 || $GLOBALS['pcv_store_constructions'] !== 0) {
    throw new RuntimeException('A versionless Mind Poisoning module must fail closed before database/provider work.');
}
echo "isolated timing/API boundary passed\n";
PHP;
$runnerSource = str_replace(
    ['__SERVER__', '__LOGS__'],
    [var_export($server, true), var_export($logs, true)],
    $runnerSource
);
timingCheck(file_put_contents($runner, $runnerSource) === strlen($runnerSource), 'Write the isolated runner.');
$process = proc_open([PHP_BINARY, $runner], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
timingCheck(is_resource($process), 'Start the isolated timing/API fixture.');
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
timingCheck(proc_close($process) === 0, 'The isolated timing/API fixture failed: ' . $stderr);
timingCheck($stdout === "isolated timing/API boundary passed\n", 'Unexpected isolated fixture output: ' . $stdout);
echo "PCV reflection hook timing and API compatibility checks passed.\n";
