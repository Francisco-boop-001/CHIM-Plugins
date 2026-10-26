<?php
declare(strict_types=1);

if (($argv[1] ?? null) !== '--child') {
    $process = proc_open([PHP_BINARY, '-n', __FILE__, '--child'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        fwrite(STDERR, "FAIL: could not start isolated database-read fixture\n");
        exit(1);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        fwrite(STDERR, $stderr !== '' ? $stderr : "FAIL: database-read fixture failed\n");
        exit($status);
    }
    echo trim((string)$stdout) . "\n";
    exit(0);
}

if (!defined('PGSQL_TRANSACTION_IDLE')) define('PGSQL_TRANSACTION_IDLE', 0);
if (!defined('PGSQL_TRANSACTION_INTRANS')) define('PGSQL_TRANSACTION_INTRANS', 2);
if (!defined('PGSQL_TRANSACTION_INERROR')) define('PGSQL_TRANSACTION_INERROR', 3);
if (!defined('PGSQL_COMMAND_OK')) define('PGSQL_COMMAND_OK', 1);
if (!defined('PGSQL_CONNECTION_OK')) define('PGSQL_CONNECTION_OK', 0);

final class PcvReadBoundsResult
{
    public array $rows;
    public int $offset = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }
}

$GLOBALS['pcvReadFixture'] = [];
$GLOBALS['db'] = new stdClass();
$GLOBALS['pcvReadSharedDb'] = $GLOBALS['db'];

function pcvReadBoundsCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pcvReadBoundsResult(array $rows): PcvReadBoundsResult
{
    return new PcvReadBoundsResult($rows);
}

function pcvReadBoundsSettingMilliseconds(string $setting): string
{
    if (preg_match('/^([0-9]+)(ms|s)?$/iD', trim($setting), $match) !== 1) {
        throw new RuntimeException('read-bounds fixture has an invalid timeout setting');
    }
    return (string)((int)$match[1] * (strtolower($match[2] ?? 'ms') === 's' ? 1000 : 1));
}

function pcvReadBoundsExecute($connection, string $query, array $params = [])
{
    $fixture =& $GLOBALS['pcvReadFixture'];
    if ($connection !== $fixture['connection']) {
        throw new RuntimeException('bounded read used a connection other than its owned fixture');
    }
    $fixture['operations'][] = ['sql' => $query, 'params' => $params, 'status' => $fixture['transaction_status']];
    $normalized = strtolower(trim($query));

    if (!empty($fixture['fail_bounds'])
        && (str_contains($normalized, 'set_config') || preg_match('/^set\s+local\s+/i', trim($query)) === 1)) {
        return false;
    }
    if (!empty($fixture['fail_catalog']) && str_contains($normalized, 'core_npc_master')) {
        return false;
    }

    if (str_starts_with($normalized, 'begin ')) {
        if ($fixture['transaction_status'] !== PGSQL_TRANSACTION_IDLE) {
            return false;
        }
        $fixture['call_order'][] = 'begin';
        $fixture['transaction_original_settings'] = $fixture['effective_settings'];
        $fixture['transaction_status'] = PGSQL_TRANSACTION_INTRANS;
        return true;
    }
    if ($normalized === 'commit') {
        if (!empty($fixture['fail_commit'])) {
            return false;
        }
        $fixture['transaction_status'] = PGSQL_TRANSACTION_IDLE;
        $fixture['effective_settings'] = $fixture['transaction_original_settings'];
        return true;
    }
    if ($normalized === 'rollback') {
        $fixture['transaction_status'] = PGSQL_TRANSACTION_IDLE;
        $fixture['effective_settings'] = $fixture['transaction_original_settings'];
        return true;
    }

    if (str_contains($normalized, 'pg_settings')) {
        return pcvReadBoundsResult([[
            'lock_timeout' => pcvReadBoundsSettingMilliseconds($fixture['effective_settings']['lock_timeout']),
            'statement_timeout' => pcvReadBoundsSettingMilliseconds($fixture['effective_settings']['statement_timeout']),
        ]]);
    }

    if (str_contains($normalized, 'current_setting')) {
        $row = [];
        foreach (['lock_timeout', 'statement_timeout'] as $name) {
            if (str_contains($normalized, $name) || in_array($name, $params, true)) {
                $row[$name] = $fixture['effective_settings'][$name];
            }
        }
        return pcvReadBoundsResult([$row]);
    }
    if (preg_match('/^show\s+(lock_timeout|statement_timeout)$/i', trim($query), $match) === 1) {
        $name = strtolower($match[1]);
        return pcvReadBoundsResult([[$name => $fixture['effective_settings'][$name]]]);
    }

    if (str_contains($normalized, 'set_config')) {
        $settings = [];
        if (isset($params[0], $params[1]) && in_array($params[0], ['lock_timeout', 'statement_timeout'], true)) {
            $settings[$params[0]] = (string)$params[1];
        } else {
            $names = array_values(array_filter(['lock_timeout', 'statement_timeout'],
                static fn(string $name): bool => str_contains($normalized, $name)));
            foreach ($names as $index => $name) {
                if (preg_match('/set_config\s*\(\s*[\'\"]?' . $name . '[\'\"]?\s*,\s*\$([0-9]+)/i', $query, $match) === 1) {
                    $settings[$name] = (string)($params[((int)$match[1]) - 1] ?? '');
                } elseif (count($names) === 1) {
                    $settings[$name] = (string)($params[0] ?? '');
                } else {
                    $settings[$name] = (string)($params[$index] ?? '');
                }
            }
        }
        foreach ($settings as $name => $value) {
            $fixture['effective_settings'][$name] = $value;
        }
        return pcvReadBoundsResult([array_replace($settings, ['setting' => end($settings) ?: ''])]);
    }
    if (preg_match('/^set\s+local\s+(lock_timeout|statement_timeout)\s*(?:=|to)\s*[\'\"]?([^\'\";\s]+)[\'\"]?/i', trim($query), $match) === 1) {
        $name = strtolower($match[1]);
        $fixture['effective_settings'][$name] = $match[2];
        return true;
    }

    if (str_contains($normalized, 'select * from public.core_npc_master')) {
        return pcvReadBoundsResult($fixture['catalog_rows']);
    }
    if (str_contains($normalized, 'to_regclass')) {
        return pcvReadBoundsResult([['profile_table' => 'chim_meta.playthrough_profiles']]);
    }
    if (str_contains($normalized, 'public.core_player')) {
        return pcvReadBoundsResult([['value' => 'Runa']]);
    }

    return pcvReadBoundsResult([]);
}

function pg_transaction_status($connection): int
{
    if ($connection !== $GLOBALS['pcvReadFixture']['connection']) {
        throw new RuntimeException('transaction status checked on a non-owned connection');
    }
    return $GLOBALS['pcvReadFixture']['transaction_status'];
}

function pg_connection_status($connection): int
{
    return $connection === $GLOBALS['pcvReadFixture']['connection'] ? PGSQL_CONNECTION_OK : 1;
}

function pg_query($connection, string $query)
{
    return pcvReadBoundsExecute($connection, $query);
}

function pg_query_params($connection, string $query, array $params)
{
    return pcvReadBoundsExecute($connection, $query, $params);
}

function pg_num_rows($result): int
{
    return $result instanceof PcvReadBoundsResult ? count($result->rows) : 0;
}

function pg_fetch_assoc($result)
{
    if (!$result instanceof PcvReadBoundsResult || !array_key_exists($result->offset, $result->rows)) {
        return false;
    }
    return $result->rows[$result->offset++];
}

function pg_fetch_all($result)
{
    return $result instanceof PcvReadBoundsResult ? $result->rows : false;
}

function pg_fetch_result($result, int $row = 0, $field = 0)
{
    if (!$result instanceof PcvReadBoundsResult || !isset($result->rows[$row])) {
        return false;
    }
    if (is_string($field)) {
        return $result->rows[$row][$field] ?? false;
    }
    return array_values($result->rows[$row])[$field] ?? false;
}

function pg_free_result($result): bool { return true; }
function pg_result_status($result): int { return $result === false ? 0 : PGSQL_COMMAND_OK; }
function pg_last_error($connection = null): string { return 'fixture database error'; }

function pg_close($connection): bool
{
    if ($connection !== $GLOBALS['pcvReadFixture']['connection']) {
        throw new RuntimeException('bounded reader attempted to close a non-owned connection');
    }
    $GLOBALS['pcvReadFixture']['closed'][] = $connection;
    return true;
}

$engineRoot = dirname(__DIR__, 2);
$helperDirectory = $engineRoot . DIRECTORY_SEPARATOR . 'lib';
$helperPath = $helperDirectory . DIRECTORY_SEPARATOR . 'playthrough_home.php';
$switchingHelperPath = $helperDirectory . DIRECTORY_SEPARATOR . 'playthrough_switching.php';
$createdHelperDirectory = false;
if (is_file($helperPath) || is_link($helperPath)) {
    fwrite(STDERR, "FAIL: refusing to replace an existing playthrough helper.\n");
    exit(1);
}
if (is_file($switchingHelperPath) || is_link($switchingHelperPath)) {
    fwrite(STDERR, "FAIL: refusing to replace an existing playthrough switching helper.\n");
    exit(1);
}
if (!is_dir($helperDirectory)) {
    if (!mkdir($helperDirectory, 0700, true) && !is_dir($helperDirectory)) {
        fwrite(STDERR, "FAIL: could not create isolated read-bounds helper directory.\n");
        exit(1);
    }
    $createdHelperDirectory = true;
}
if (file_put_contents($helperPath, <<<'PHP'
<?php
function ptr_runtime_enter() {
    $GLOBALS['pcvReadFixture']['call_order'][] = 'lease';
}
function ptp_connect() {
    $GLOBALS['pcvReadFixture']['connects']++;
    $GLOBALS['pcvReadFixture']['call_order'][] = 'connect';
    return $GLOBALS['pcvReadFixture']['connect_failure'] ? false : $GLOBALS['pcvReadFixture']['connection'];
}
function pth_state($connection) {
    $GLOBALS['pcvReadFixture']['home_connections'][] = $connection;
    return $GLOBALS['pcvReadFixture']['home_state'];
}
PHP
) === false) {
    fwrite(STDERR, "FAIL: could not create isolated read-bounds helper.\n");
    exit(1);
}
register_shutdown_function(static function () use ($helperPath, $switchingHelperPath, $helperDirectory, $createdHelperDirectory): void {
    if (is_file($helperPath) && !is_link($helperPath)) {
        @unlink($helperPath);
    }
    if (is_file($switchingHelperPath) && !is_link($switchingHelperPath)) {
        @unlink($switchingHelperPath);
    }
    if ($createdHelperDirectory && is_dir($helperDirectory) && !is_link($helperDirectory)) {
        @rmdir($helperDirectory);
    }
});
if (file_put_contents($switchingHelperPath, <<<'PHP'
<?php
function pas_guard($connection, bool $required = false): void
{
    $fixture =& $GLOBALS['pcvReadFixture'];
    $fixture['call_order'][] = 'guard';
    $fixture['guard_calls'][] = [
        'connection' => $connection,
        'required' => $required,
        'status' => $fixture['transaction_status'],
        'settings' => $fixture['effective_settings'],
    ];
}
PHP
) === false) {
    fwrite(STDERR, "FAIL: could not create isolated playthrough switching helper.\n");
    exit(1);
}

function pcvReadBoundsReset(string $lockTimeout = '0', string $statementTimeout = '0'): void
{
    $GLOBALS['pcvReadFixture'] = [
        'connection' => $GLOBALS['pcvReadFixture']['connection'] ?? (object)['id' => 'pcv-owned'],
        'connects' => 0,
        'closed' => [],
        'operations' => [],
        'call_order' => [],
        'guard_calls' => [],
        'transaction_status' => PGSQL_TRANSACTION_IDLE,
        'transaction_original_settings' => [],
        'effective_settings' => ['lock_timeout' => $lockTimeout, 'statement_timeout' => $statementTimeout],
        'catalog_rows' => [],
        'home_state' => ['available' => false, 'active_id' => 0, 'playthroughs' => []],
        'home_connections' => [],
        'connect_failure' => false,
        'fail_bounds' => false,
        'fail_catalog' => false,
        'fail_commit' => false,
    ];
}

function pcvReadBoundsHasOperation(string $prefix): bool
{
    foreach ($GLOBALS['pcvReadFixture']['operations'] as $operation) {
        if (str_starts_with(strtolower(trim($operation['sql'])), strtolower($prefix))) {
            return true;
        }
    }
    return false;
}

pcvReadBoundsReset();
require_once dirname(__DIR__) . '/server/state.php';

try {
    pcvReadBoundsCheck(function_exists('pcv_with_read_connection'), 'bounded read helper is missing');
    pcvReadBoundsCheck(function_exists('pcv_apply_read_bounds'), 'read timeout helper is missing');
    pcvReadBoundsCheck(function_exists('pcv_read_npc_catalog'), 'owned NPC catalog reader is missing');

    $connection = $GLOBALS['pcvReadFixture']['connection'];
    $result = pcv_with_read_connection(static function ($readConnection) use ($connection) {
        pcvReadBoundsCheck($readConnection === $connection, 'read callback did not receive the owned connection');
        pcvReadBoundsCheck(pg_transaction_status($readConnection) === PGSQL_TRANSACTION_INTRANS,
            'read callback ran outside the owned transaction');
        pcvReadBoundsCheck($GLOBALS['pcvReadFixture']['effective_settings'] === [
            'lock_timeout' => '2000ms', 'statement_timeout' => '3000ms',
        ], 'default lock and statement bounds were not applied');
        return 'read-complete';
    });
    pcvReadBoundsCheck($result === 'read-complete', 'read callback result was not returned');
    pcvReadBoundsCheck(pcvReadBoundsHasOperation('begin isolation level repeatable read read only'),
        'owned read did not use a read-only repeatable-read transaction');
    pcvReadBoundsCheck(pcvReadBoundsHasOperation('commit') && !pcvReadBoundsHasOperation('rollback'),
        'successful read did not commit cleanly');
    pcvReadBoundsCheck(count($GLOBALS['pcvReadFixture']['closed']) === 1
        && $GLOBALS['pcvReadFixture']['closed'][0] === $connection,
        'successful read did not close exactly its owned connection');
    pcvReadBoundsCheck($GLOBALS['pcvReadFixture']['effective_settings'] === [
        'lock_timeout' => '0', 'statement_timeout' => '0',
    ], 'read-local limits leaked after commit');

    pcvReadBoundsReset('500ms', '5s');
    $shorter = pcv_with_read_connection(static function () {
        return $GLOBALS['pcvReadFixture']['effective_settings'];
    });
    pcvReadBoundsCheck($shorter === ['lock_timeout' => '500ms', 'statement_timeout' => '3000ms'],
        'shorter positive limits were not preserved independently');

    pcvReadBoundsReset('5s', '750ms');
    $shorterStatement = pcv_with_read_connection(static function () {
        return $GLOBALS['pcvReadFixture']['effective_settings'];
    });
    pcvReadBoundsCheck($shorterStatement === ['lock_timeout' => '2000ms', 'statement_timeout' => '750ms'],
        'a shorter statement limit was not preserved while the lock limit was clamped');

    pcvReadBoundsReset();
    $GLOBALS['pcvReadFixture']['connect_failure'] = true;
    $connectCallbackWasCalled = false;
    $connectFailureEscaped = false;
    try {
        pcv_with_read_connection(static function () use (&$connectCallbackWasCalled): void {
            $connectCallbackWasCalled = true;
        });
    } catch (Throwable $error) {
        $connectFailureEscaped = true;
    }
    pcvReadBoundsCheck($connectFailureEscaped && !$connectCallbackWasCalled
        && $GLOBALS['pcvReadFixture']['operations'] === []
        && $GLOBALS['pcvReadFixture']['closed'] === [],
        'connection failure did not fail closed before the callback');

    pcvReadBoundsReset();
    $callbackWasCalled = false;
    try {
        pcv_with_read_connection(static function () use (&$callbackWasCalled): void {
            $callbackWasCalled = true;
            throw new Error('simulated cancellation');
        });
        throw new RuntimeException('cancellation did not escape the read helper');
    } catch (Error $error) {
        pcvReadBoundsCheck($error->getMessage() === 'simulated cancellation', 'read helper replaced the callback error');
    }
    pcvReadBoundsCheck($callbackWasCalled && pcvReadBoundsHasOperation('rollback')
        && count($GLOBALS['pcvReadFixture']['closed']) === 1,
        'callback cancellation did not roll back and close the owned connection');

    pcvReadBoundsReset();
    $abortedCallbackReturned = false;
    $abortedTransactionRejected = false;
    try {
        pcv_with_read_connection(static function () {
            $GLOBALS['pcvReadFixture']['transaction_status'] = PGSQL_TRANSACTION_INERROR;
            return 'aborted transaction result';
        });
        $abortedCallbackReturned = true;
    } catch (RuntimeException $error) {
        $abortedTransactionRejected = $error->getMessage() === 'Core read transaction is no longer active.';
    }
    pcvReadBoundsCheck(!$abortedCallbackReturned && $abortedTransactionRejected
        && pcvReadBoundsHasOperation('rollback') && !pcvReadBoundsHasOperation('commit')
        && count($GLOBALS['pcvReadFixture']['closed']) === 1,
        'a callback that left an aborted transaction was accepted instead of rolled back and closed');

    pcvReadBoundsReset();
    $GLOBALS['pcvReadFixture']['fail_bounds'] = true;
    $boundsCallbackWasCalled = false;
    try {
        pcv_with_read_connection(static function () use (&$boundsCallbackWasCalled): void {
            $boundsCallbackWasCalled = true;
        });
        throw new RuntimeException('failed timeout setup was accepted');
    } catch (Throwable $error) {
        pcvReadBoundsCheck(!$boundsCallbackWasCalled && pcvReadBoundsHasOperation('rollback')
            && count($GLOBALS['pcvReadFixture']['closed']) === 1,
            'failed timeout setup did not fail closed with rollback and close');
    }

    pcvReadBoundsReset();
    $GLOBALS['pcvReadFixture']['fail_commit'] = true;
    try {
        pcv_with_read_connection(static fn() => 'must-not-commit');
        throw new RuntimeException('failed commit was reported as success');
    } catch (Throwable $error) {
        pcvReadBoundsCheck(pcvReadBoundsHasOperation('rollback')
            && count($GLOBALS['pcvReadFixture']['closed']) === 1,
            'failed commit did not roll back and close the owned connection');
    }

    pcvReadBoundsReset();
    $GLOBALS['pcvReadFixture']['transaction_status'] = PGSQL_TRANSACTION_INTRANS;
    $nonIdleCallbackWasCalled = false;
    try {
        pcv_with_read_connection(static function () use (&$nonIdleCallbackWasCalled): void {
            $nonIdleCallbackWasCalled = true;
        });
        throw new RuntimeException('non-idle connection was accepted');
    } catch (Throwable $error) {
        pcvReadBoundsCheck(!$nonIdleCallbackWasCalled && count($GLOBALS['pcvReadFixture']['closed']) === 1,
            'non-idle connection was not rejected and closed');
        pcvReadBoundsCheck(!pcvReadBoundsHasOperation('begin isolation level'),
            'reader began a transaction on a connection that was already in a transaction');
    }

    pcvReadBoundsReset();
    $GLOBALS['pcvReadFixture']['catalog_rows'] = [
        ['id' => '21', 'npc_name' => 'Runa', 'plugin_extended_data' => '{"private_conversation":{}}'],
        ['id' => '22', 'npc_name' => 'Serana', 'plugin_extended_data' => '{}'],
    ];
    $sharedDb = $GLOBALS['db'];
    $rows = pcv_read_npc_catalog();
    pcvReadBoundsCheck($rows === $GLOBALS['pcvReadFixture']['catalog_rows'],
        'owned NPC read changed catalog row values, types, or ordering');
    pcvReadBoundsCheck(pcvReadBoundsHasOperation('select * from public.core_npc_master'),
        'owned NPC read did not use the complete core catalog query');
    pcvReadBoundsCheck($GLOBALS['db'] === $sharedDb,
        'owned NPC read replaced or closed the shared core database object');
    $catalogOperations = array_values(array_filter($GLOBALS['pcvReadFixture']['operations'],
        static fn(array $operation): bool => str_contains(strtolower($operation['sql']), 'core_npc_master')));
    pcvReadBoundsCheck(count($catalogOperations) === 1
        && $catalogOperations[0]['status'] === PGSQL_TRANSACTION_INTRANS,
        'catalog SQL did not run inside the owned bounded transaction');

    pcvReadBoundsReset();
    pcvReadBoundsCheck(pcv_read_npc_catalog() === [],
        'a successful empty catalog query must remain distinct and empty');

    pcvReadBoundsReset();
    $GLOBALS['pcvReadFixture']['fail_catalog'] = true;
    $catalogFailureEscaped = false;
    try {
        pcv_read_npc_catalog();
    } catch (Throwable $error) {
        $catalogFailureEscaped = true;
    }
    pcvReadBoundsCheck($catalogFailureEscaped,
        'failed NPC catalog read was returned as an empty catalog');
    pcvReadBoundsCheck(pcvReadBoundsHasOperation('rollback')
        && count($GLOBALS['pcvReadFixture']['closed']) === 1,
        'failed NPC catalog read did not roll back and close its owned connection');

    $hadScriptFilename = array_key_exists('SCRIPT_FILENAME', $_SERVER);
    $oldScriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
    $hadPlaythroughHeader = array_key_exists('HTTP_X_CHIM_PLAYTHROUGH', $_SERVER);
    $oldPlaythroughHeader = $_SERVER['HTTP_X_CHIM_PLAYTHROUGH'] ?? null;
    try {
        pcvReadBoundsReset();
        $_SERVER['SCRIPT_FILENAME'] = '/fixture/plugin.php';
        $_SERVER['HTTP_X_CHIM_PLAYTHROUGH'] = 'fixture-token';
        pcv_with_read_connection(static function () {
            $GLOBALS['pcvReadFixture']['call_order'][] = 'callback';
            return 'tagged read';
        });
        $guard = $GLOBALS['pcvReadFixture']['guard_calls'][0] ?? null;
        pcvReadBoundsCheck($GLOBALS['pcvReadFixture']['call_order'] === ['lease', 'connect', 'begin', 'guard', 'callback']
            && is_array($guard) && $guard['connection'] === $GLOBALS['pcvReadFixture']['connection']
            && $guard['required'] === true && $guard['status'] === PGSQL_TRANSACTION_INTRANS
            && $guard['settings'] === ['lock_timeout' => '2000ms', 'statement_timeout' => '3000ms'],
            'tagged reads must enter the restore barrier before connect and guard the owned bounded transaction');

        foreach (['main.php', 'gamedata.php'] as $script) {
            pcvReadBoundsReset();
            $_SERVER['SCRIPT_FILENAME'] = '/fixture/' . $script;
            unset($_SERVER['HTTP_X_CHIM_PLAYTHROUGH']);
            pcv_with_read_connection(static function () {
                $GLOBALS['pcvReadFixture']['call_order'][] = 'callback';
            });
            $guard = $GLOBALS['pcvReadFixture']['guard_calls'][0] ?? null;
            pcvReadBoundsCheck($GLOBALS['pcvReadFixture']['call_order'] === ['lease', 'connect', 'begin', 'guard', 'callback']
                && is_array($guard) && $guard['required'] === true
                && $guard['connection'] === $GLOBALS['pcvReadFixture']['connection']
                && $guard['status'] === PGSQL_TRANSACTION_INTRANS
                && $guard['settings'] === ['lock_timeout' => '2000ms', 'statement_timeout' => '3000ms'],
                $script . ' must retain the core required playthrough guard inside the bounded transaction');
        }

        pcvReadBoundsReset();
        $_SERVER['SCRIPT_FILENAME'] = '/fixture/plugin.php';
        unset($_SERVER['HTTP_X_CHIM_PLAYTHROUGH']);
        pcv_with_read_connection(static fn() => null);
        pcvReadBoundsCheck($GLOBALS['pcvReadFixture']['guard_calls'] === [],
            'ordinary untagged plugin reads must preserve the existing no-guard path');
    } finally {
        if ($hadScriptFilename) {
            $_SERVER['SCRIPT_FILENAME'] = $oldScriptFilename;
        } else {
            unset($_SERVER['SCRIPT_FILENAME']);
        }
        if ($hadPlaythroughHeader) {
            $_SERVER['HTTP_X_CHIM_PLAYTHROUGH'] = $oldPlaythroughHeader;
        } else {
            unset($_SERVER['HTTP_X_CHIM_PLAYTHROUGH']);
        }
    }

    pcvReadBoundsCheck($GLOBALS['db'] === $GLOBALS['pcvReadSharedDb'],
        'bounded reads changed the shared core database handle');
    echo "database read bound checks passed\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
