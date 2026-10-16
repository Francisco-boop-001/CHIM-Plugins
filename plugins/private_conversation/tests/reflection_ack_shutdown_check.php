<?php
declare(strict_types=1);

function shutdownCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function shutdownRemoveTree(string $path): void
{
    $root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($path, $root) || !is_dir($path) || is_link($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isLink()) {
            @unlink($item->getPathname());
        } elseif ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($path);
}

shutdownCheck(extension_loaded('pgsql') && function_exists('proc_open'), 'PHP pgsql and proc_open are required.');
$host = getenv('PCV_ACK_TEST_HOST') ?: '';
$port = filter_var(getenv('PCV_ACK_TEST_PORT'), FILTER_VALIDATE_INT);
$database = getenv('PCV_ACK_TEST_DB') ?: '';
$user = getenv('PCV_ACK_TEST_USER') ?: '';
shutdownCheck(preg_match('/\A\/tmp\/pcv-ack-db-[a-f0-9]{16}\/socket\z/D', $host) === 1
    && is_int($port) && $port >= 50000 && $port <= 59999
    && $database === 'pcv_ack_fixture' && $user === 'postgres',
    'Refusing a connection outside the guarded disposable PostgreSQL target.');
$dsn = sprintf('host=%s port=%d dbname=%s user=%s connect_timeout=2', $host, $port, $database, $user);
$admin = @pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
shutdownCheck($admin instanceof PgSql\Connection, 'Could not connect to the guarded disposable PostgreSQL database.');
$created = @pg_query($admin, 'CREATE TABLE public.speech (
    rowid bigserial PRIMARY KEY,
    utterance_id text NOT NULL,
    speaker text NOT NULL,
    listener text NOT NULL,
    speech text NOT NULL
)');
shutdownCheck($created !== false, 'Could not create the isolated native speech fixture table.');
pg_free_result($created);
$GLOBALS['pcv_outer_connection'] = $admin;

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv_ack_shutdown_' . bin2hex(random_bytes(8));
shutdownCheck(mkdir($testRoot, 0700), 'Could not create the isolated callback fixture root.');
register_shutdown_function(static fn() => shutdownRemoveTree($testRoot));
$webRoot = $testRoot . '/webroot/HerikaServer';
$server = $webRoot . '/ext/private_conversation';
$mindPoisoning = $webRoot . '/ext/mind_poisoning';
$logsRoot = $testRoot . '/logs';
$statesRoot = $testRoot . '/states';
shutdownCheck(mkdir($server, 0700, true) && mkdir($mindPoisoning, 0700, true)
    && mkdir($webRoot . '/lib', 0700, true) && mkdir($logsRoot, 0700) && mkdir($statesRoot, 0700),
    'Could not create the isolated extension layout.');
$source = dirname(__DIR__) . '/server';
foreach (['reflection.php', 'reflection_receipt.php', 'log.php', 'prerequest.php', 'installation_guard.php', 'manifest.json'] as $file) {
    shutdownCheck(copy($source . '/' . $file, $server . '/' . $file), 'Could not copy production ' . $file . '.');
}

$stateStub = <<<'PHP'
<?php
function pcv_valid_key(string $key): bool { return preg_match('/\A[a-f0-9]{64}\z/D', $key) === 1; }
function pcv_state_directory(?string $directory): string
{
    $path = $directory ?? (getenv('PCV_ACK_SHUTDOWN_STATE') ?: '');
    if ($path === '' || str_contains($path, "\0")) { throw new RuntimeException('Invalid fixture state directory.'); }
    if (!is_dir($path) && !mkdir($path, 0700, true)) { throw new RuntimeException('Fixture state directory unavailable.'); }
    return $path;
}
function pcv_lock_state(string $directory, bool $create, int $mode)
{
    if (!is_dir($directory)) {
        if (!$create) { return null; }
        if (!mkdir($directory, 0700, true) && !is_dir($directory)) { return null; }
    }
    $handle = @fopen($directory . DIRECTORY_SEPARATOR . 'state.lock', 'c');
    if ($handle === false || !@flock($handle, $mode)) {
        if (is_resource($handle)) { fclose($handle); }
        return null;
    }
    return $handle;
}
function pcv_unlock_state($handle): void
{
    if (is_resource($handle)) { @flock($handle, LOCK_UN); fclose($handle); }
}
function pcv_current_identity(bool $refresh = false): array
{
    return ['key' => str_repeat('a', 64), 'player_name' => 'Player'];
}
function pcv_current_player_name(): string { return 'Player'; }
function pcv_load_store(string $directory): array
{
    return ['kind' => 'ready', 'state' => [
        'key' => str_repeat('a', 64),
        'active' => ['config' => [
            'enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '11', 'actor_b' => null, 'exclude_player' => true,
        ], 'config_id' => '123e4567-e89b-42d3-a456-426614174000', 'expires_at' => time() + 60],
        'pending' => null,
    ]];
}
function chimInteractionBegin(): void { $GLOBALS['chim_interaction_generation'] ??= 7; }
function chimInteractionState(): array
{
    return ['enabled' => true, 'generation' => $GLOBALS['pcv_test_current_generation'] ?? 7];
}
PHP;
$scopeStub = <<<'PHP'
<?php
require_once __DIR__ . '/log.php';
function pcv_scope_name_key(string $name): string { return strtolower(trim($name)); }
function pcvReadResolvedScope(): array { return $GLOBALS['pcv_test_scope']; }
function pcvSoloReflectionRequest(array $scope): bool
{
    return ($scope['status'] ?? null) === 'active'
        && ($scope['scope']['scene_mode'] ?? null) === 'solo'
        && ($scope['scope']['actor_b'] ?? null) === null
        && ($scope['scope']['exclude_player'] ?? null) === true
        && ($scope['origin_mode'] ?? null) === 'STANDARD'
        && in_array($scope['origin_request_type'] ?? null, ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'], true)
        && ($scope['route'] ?? null) === 'solo_reflection';
}
function pcvRequestScopeModeMatches(array $scope): bool
{
    return ($scope['origin_mode'] ?? null) === 'STANDARD'
        && in_array($scope['origin_request_type'] ?? null, ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'], true);
}
PHP;
$connectionStub = <<<'PHP'
<?php
function ptp_connect()
{
    $host = getenv('PCV_ACK_TEST_HOST') ?: '';
    $port = filter_var(getenv('PCV_ACK_TEST_PORT'), FILTER_VALIDATE_INT);
    $database = getenv('PCV_ACK_TEST_DB') ?: '';
    $user = getenv('PCV_ACK_TEST_USER') ?: '';
    if (preg_match('/\A\/tmp\/pcv-ack-db-[a-f0-9]{16}\/socket\z/D', $host) !== 1
        || !is_int($port) || $port < 50000 || $port > 59999
        || $database !== 'pcv_ack_fixture' || $user !== 'postgres') {
        return null;
    }
    $connection = @pg_connect(sprintf(
        'host=%s port=%d dbname=%s user=%s connect_timeout=2', $host, $port, $database, $user
    ), PGSQL_CONNECT_FORCE_NEW);
    if ($connection instanceof PgSql\Connection) { $GLOBALS['pcv_test_native_connections']++; }
    return $connection;
}
PHP;
$moduleStub = <<<'PHP'
<?php
namespace ChimMindPoisoning;
const MIND_POISONING_REFLECTION_API_VERSION = 1;
interface StoreDb
{
    public function activePlaythrough(): ?array;
    public function acknowledgedEvent(string $utteranceId): ?array;
}
function reflectionAckPayload(array $request): ?array
{
    try { $payload = json_decode($request[3] ?? '', true, 8, JSON_THROW_ON_ERROR); }
    catch (\Throwable) { return null; }
    return is_array($payload) ? $payload : null;
}
function reflectionPlayerTransport(string $listener, string $player): bool
{
    return in_array(strtolower(trim($listener)), [strtolower(trim($player)), 'dragonborn', 'the dragonborn'], true);
}
function reflectionSourceParts(string $source): array
{
    if (preg_match('/\A([^:]{1,256}):/', $source, $match) !== 1
        || !str_contains($source, 'Talking to explicit_disable_rechat')) { return []; }
    return ['speaker' => $match[1], 'target' => ['targets' => ['explicit_disable_rechat']]];
}
function mindPoisoningEvaluateReflection(...$args): string
{
    $GLOBALS['pcv_test_provider_calls']++;
    [$registration, $request, $store, $revalidate] = $args;
    foreach (['pre_model', 'transaction'] as $phase) {
        $GLOBALS['pcv_test_phases'][] = $phase;
        if (!is_callable($revalidate) || !$revalidate($registration, $phase)) { return 'rejected'; }
    }
    return 'committed';
}
class RequestLog {}
class PostgresStoreDb implements StoreDb
{
    public function activePlaythrough(): ?array { return ['id' => '1', 'player_name' => 'Player']; }
    public function acknowledgedEvent(string $utteranceId): ?array { return null; }
}
PHP;
shutdownCheck(file_put_contents($server . '/state.php', $stateStub) === strlen($stateStub)
    && file_put_contents($server . '/scope.php', $scopeStub) === strlen($scopeStub)
    && file_put_contents($webRoot . '/lib/playthrough_home.php', $connectionStub) === strlen($connectionStub)
    && file_put_contents($mindPoisoning . '/reflection.php', $moduleStub) === strlen($moduleStub),
    'Could not write the isolated runtime, SQL connection, and API stubs.');

$runner = $testRoot . '/runner.php';
$runnerSource = <<<'PHP'
<?php
declare(strict_types=1);
define('PCV_LOG_TESTING', true);
$scenario = $argv[1] ?? '';
$server = $argv[2] ?? '';
$logs = $argv[3] ?? '';
$stateDirectory = $argv[4] ?? '';
$resultPath = $argv[5] ?? '';
putenv('PCV_ACK_SHUTDOWN_STATE=' . $stateDirectory);
require $server . '/reflection.php';
require dirname($server, 2) . '/ext/mind_poisoning/reflection.php';
if (!pcv_log_set_test_directory($logs)) { throw new RuntimeException('Could not isolate diagnostics.'); }
$GLOBALS['pcv_test_provider_calls'] = 0;
$GLOBALS['pcv_test_phases'] = [];
$GLOBALS['pcv_test_native_connections'] = 0;
$GLOBALS['chim_interaction_generation'] = 7;
$GLOBALS['pcv_test_current_generation'] = 7;
$GLOBALS['pcv_test_scope'] = [
    'status' => 'active', 'pcv_key' => str_repeat('a', 64),
    'config_id' => '123e4567-e89b-42d3-a456-426614174000', 'actor_a_id' => '11',
    'scope' => ['scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null, 'exclude_player' => true],
];
$utteranceId = 'utt_shutdown_' . $scenario . '_12345678';
$speech = 'A short response: café, yes.';
$request = ['_speech', '', '', json_encode([
    'speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => $speech, 'utterance_id' => $utteranceId,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
$GLOBALS['gameRequest'] = $request;
require $server . '/prerequest.php';
$directory = pcv_state_directory(null);
$receiptPath = $directory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
if (!is_file($receiptPath) || str_contains((string)file_get_contents($receiptPath), $speech)) {
    throw new RuntimeException('The actual prerequest hook did not store bounded private metadata.');
}

$GLOBALS['SCRIPTLINE_UTTERANCE_ID'] = $utteranceId;
$GLOBALS['DEBUG_DATA'] = ['OUTPUT_LOG' => 'Aela|ScriptQueue|' . $speech . '/neutral/explicit_disable_rechat/none/phonetic/1/explicit_disable_rechat/' . $utteranceId . "\r\n"];
$GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
$GLOBALS['HERIKA_NAME'] = 'Aela';
$requestScope = [
    'status' => 'active', 'config_id' => '123e4567-e89b-42d3-a456-426614174000', 'actor_a_id' => '11',
    'scope' => ['scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null, 'exclude_player' => true],
    'origin_request_type' => 'inputtext', 'origin_mode' => 'STANDARD', 'origin_dialogue' => 'test request',
    'route' => 'solo_reflection', 'baseline_utterance_id' => 'utt_baseline_12345678', 'baseline_output_log' => 'previous output',
];
$source = 'Aela: A source event (Talking to explicit_disable_rechat)';
$store = new class($utteranceId, $source) implements \ChimMindPoisoning\StoreDb {
    public function __construct(private string $id, private string $source) {}
    public function activePlaythrough(): ?array { return ['id' => '1', 'player_name' => 'Player']; }
    public function acknowledgedEvent(string $utteranceId): ?array
    {
        return $utteranceId === $this->id ? [
            'utterance_id' => $this->id, 'event_id' => 200, 'delivery_state' => 'emitted', 'source_data' => $this->source,
        ] : null;
    }
};
$registrationResult = pcv_reflection_register_with_store(
    $requestScope,
    $store,
    null,
    static fn(): array => $GLOBALS['pcv_test_scope']
);
$registered = pcv_reflection_registry_probe();
if ($registrationResult !== 'registered' || ($registered['record']['status'] ?? null) !== 'registered'
    || ($registered['record']['registration']['utterance_id'] ?? null) !== $utteranceId
    || $GLOBALS['pcv_test_provider_calls'] !== 0 || $GLOBALS['pcv_test_native_connections'] !== 1) {
    throw new RuntimeException('Late registration must remain registered after the pre-insert native lookup misses.');
}

$host = getenv('PCV_ACK_TEST_HOST') ?: '';
$port = (int)(getenv('PCV_ACK_TEST_PORT') ?: 0);
$dsn = sprintf('host=%s port=%d dbname=%s user=%s connect_timeout=2',
    $host, $port, getenv('PCV_ACK_TEST_DB') ?: '', getenv('PCV_ACK_TEST_USER') ?: '');
$coreConnection = @pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
if (!$coreConnection instanceof \PgSql\Connection) { throw new RuntimeException('Could not open isolated core INSERT connection.'); }
$rowSpeech = $scenario === 'mismatch' ? 'Different native payload.' : $speech;
$insert = @pg_query_params($coreConnection,
    'INSERT INTO public.speech (utterance_id, speaker, listener, speech) VALUES ($1, $2, $3, $4)',
    [$utteranceId, 'Aela', 'Dragonborn', $rowSpeech]);
if ($insert === false) { pg_close($coreConnection); throw new RuntimeException('Isolated native ACK INSERT failed.'); }
pg_free_result($insert);
pg_close($coreConnection);

if ($scenario === 'stale') { $GLOBALS['pcv_test_current_generation'] = 8; }
if ($scenario === 'scope') { $GLOBALS['pcv_test_scope']['config_id'] = '223e4567-e89b-42d3-a456-426614174000'; }
$connectionsBeforeShutdown = $GLOBALS['pcv_test_native_connections'];
register_shutdown_function(static function () use ($scenario, $resultPath, $connectionsBeforeShutdown): void {
    $registry = pcv_reflection_registry_probe();
    $directory = pcv_state_directory(null);
    $handle = pcv_lock_state($directory, false, LOCK_SH);
    if ($handle === null) { throw new RuntimeException('Could not inspect fixture receipt state.'); }
    try { $receipts = pcv_reflection_read_receipts_locked($directory); }
    finally { pcv_unlock_state($handle); }
    $result = [
        'scenario' => $scenario,
        'provider_calls' => $GLOBALS['pcv_test_provider_calls'],
        'phases' => $GLOBALS['pcv_test_phases'],
        'native_connections' => $GLOBALS['pcv_test_native_connections'],
        'callback_connections' => $GLOBALS['pcv_test_native_connections'] - $connectionsBeforeShutdown,
        'registry_status' => $registry['record']['status'] ?? null,
        'receipt_count' => count($receipts['receipts'] ?? []),
    ];
    file_put_contents($resultPath, json_encode($result, JSON_THROW_ON_ERROR));
    echo 'CASE ' . $scenario . ' provider=' . $result['provider_calls']
        . ' callback_sql=' . $result['callback_connections']
        . ' registry=' . ($result['registry_status'] ?? 'missing') . "\n";
});
echo "native INSERT complete; die follows\n";
die();
PHP;
shutdownCheck(file_put_contents($runner, $runnerSource) === strlen($runnerSource), 'Could not write the isolated process runner.');

$expected = [
    'success' => ['provider_calls' => 1, 'phases' => ['pre_model', 'transaction'], 'callback_connections' => 1, 'registry_status' => 'consumed'],
    'mismatch' => ['provider_calls' => 0, 'phases' => [], 'callback_connections' => 1, 'registry_status' => 'registered'],
    'stale' => ['provider_calls' => 0, 'phases' => [], 'callback_connections' => 0, 'registry_status' => 'registered'],
    'scope' => ['provider_calls' => 0, 'phases' => [], 'callback_connections' => 1, 'registry_status' => 'registered'],
];
foreach ($expected as $scenario => $wanted) {
    $stateDirectory = $statesRoot . DIRECTORY_SEPARATOR . $scenario;
    $logs = $logsRoot . DIRECTORY_SEPARATOR . $scenario;
    shutdownCheck(mkdir($stateDirectory, 0700) && mkdir($logs, 0700), 'Could not create an isolated scenario directory.');
    $resultPath = $testRoot . DIRECTORY_SEPARATOR . $scenario . '.json';
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $runner, $scenario, $server, $logs, $stateDirectory, $resultPath],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    shutdownCheck(is_resource($process), 'Could not start the isolated ' . $scenario . ' callback process.');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    shutdownCheck($exitCode === 0, $scenario . ' child failed: ' . $stderr . $stdout);
    $result = json_decode((string)@file_get_contents($resultPath), true, 8, JSON_THROW_ON_ERROR);
    foreach ($wanted as $field => $value) {
        shutdownCheck(($result[$field] ?? null) === $value, $scenario . ' returned an unexpected ' . $field . '.');
    }
    shutdownCheck(($result['scenario'] ?? null) === $scenario && ($result['receipt_count'] ?? null) === 1,
        $scenario . ' did not preserve its exact bounded ACK receipt.');
    $expectedOutput = "native INSERT complete; die follows\nCASE {$scenario} provider={$wanted['provider_calls']} callback_sql={$wanted['callback_connections']} registry={$wanted['registry_status']}\n";
    shutdownCheck($stdout === $expectedOutput, $scenario . ' produced unexpected child output: ' . $stdout);
    $verify = @pg_query_params($GLOBALS['pcv_outer_connection'],
        'SELECT count(*) AS rows FROM public.speech WHERE utterance_id = $1',
        ['utt_shutdown_' . $scenario . '_12345678']);
    $verifiedRow = $verify !== false ? pg_fetch_assoc($verify) : false;
    shutdownCheck(is_array($verifiedRow) && ($verifiedRow['rows'] ?? null) === '1',
        $scenario . ' did not leave exactly one isolated native row.');
    pg_free_result($verify);
    echo 'PASS ' . $scenario . ' ' . $stdout;
}

$cleanupPath = realpath($testRoot);
shutdownCheck($cleanupPath === $testRoot && !is_link($testRoot), 'Refusing cleanup of an unexpected fixture path.');
shutdownRemoveTree($testRoot);
shutdownCheck(!file_exists($testRoot) && !is_link($testRoot), 'Isolated callback fixture cleanup did not complete.');
pg_close($GLOBALS['pcv_outer_connection']);
echo "cleanup=PASS\n";
