<?php
declare(strict_types=1);

if (($argv[1] ?? null) === '--child') {
    $GLOBALS['identityQueryResults'] = [];
    $GLOBALS['identityQueries'] = [];
    $GLOBALS['identityQueryTransactions'] = [];
    $GLOBALS['identityConnection'] = 'identity-fixture-connection';
    $GLOBALS['identityConnects'] = 0;
    $GLOBALS['identityRuntimeEnters'] = 0;
    $GLOBALS['identityHomeConnections'] = [];
    $GLOBALS['identityHomeTransactions'] = [];
    $GLOBALS['identityClosedConnections'] = [];
    $GLOBALS['identityTransactionStatus'] = 0;
    $GLOBALS['identityReadSettings'] = ['lock_timeout' => '0', 'statement_timeout' => '0'];
    if (!defined('PGSQL_TRANSACTION_IDLE')) define('PGSQL_TRANSACTION_IDLE', 0);
    if (!defined('PGSQL_TRANSACTION_INTRANS')) define('PGSQL_TRANSACTION_INTRANS', 2);
    if (!defined('PGSQL_COMMAND_OK')) define('PGSQL_COMMAND_OK', 1);
    if (!defined('PGSQL_CONNECTION_OK')) define('PGSQL_CONNECTION_OK', 0);

    function pg_transaction_status($connection): int
    {
        if ($connection !== $GLOBALS['identityConnection']) {
            throw new RuntimeException('identity read used an unexpected connection');
        }
        return $GLOBALS['identityTransactionStatus'];
    }

    function pg_connection_status($connection): int
    {
        return $connection === $GLOBALS['identityConnection'] ? PGSQL_CONNECTION_OK : 1;
    }

    function pg_query($connection, string $query)
    {
        if ($connection !== $GLOBALS['identityConnection']) {
            throw new RuntimeException('identity transaction used an unexpected connection');
        }
        $normalized = strtolower(trim($query));
        if (str_starts_with($normalized, 'begin ')) {
            $GLOBALS['identityTransactionStatus'] = PGSQL_TRANSACTION_INTRANS;
            return true;
        }
        if ($normalized === 'commit' || $normalized === 'rollback') {
            $GLOBALS['identityTransactionStatus'] = PGSQL_TRANSACTION_IDLE;
            return true;
        }
        if (preg_match('/^show\s+(lock_timeout|statement_timeout)$/i', trim($query), $match) === 1) {
            $name = strtolower($match[1]);
            return ['count' => 1, 'row' => [$name => $GLOBALS['identityReadSettings'][$name]]];
        }
        if (preg_match("/set local (lock_timeout|statement_timeout)\\s*(?:=|to)\\s*'([^']+)'/i", $query, $match) === 1) {
            $name = strtolower($match[1]);
            $GLOBALS['identityReadSettings'][$name] = $match[2];
            return true;
        }
        return true;
    }

    function pg_query_params($connection, string $query, array $params)
    {
        $ownedConnection = $connection === $GLOBALS['identityConnection'];
        // Low-level legacy checks deliberately pass their sentinel connection;
        // identity resolution itself must use the owned connection above.
        if (!$ownedConnection && $connection !== 'fixture') {
            throw new RuntimeException('identity query used an unexpected connection');
        }
        $normalized = strtolower($query);
        if (str_contains($normalized, 'pg_settings')) {
            if (!$ownedConnection) {
                throw new RuntimeException('identity timeout settings used an unexpected connection');
            }
            $toMilliseconds = static function (string $setting): string {
                if (preg_match('/^([0-9]+)(ms|s)?$/iD', trim($setting), $match) !== 1) {
                    throw new RuntimeException('identity fixture has an invalid timeout setting');
                }
                return (string)((int)$match[1] * (strtolower($match[2] ?? 'ms') === 's' ? 1000 : 1));
            };
            return ['count' => 1, 'row' => [
                'lock_timeout' => $toMilliseconds($GLOBALS['identityReadSettings']['lock_timeout']),
                'statement_timeout' => $toMilliseconds($GLOBALS['identityReadSettings']['statement_timeout']),
            ]];
        }
        if (str_contains($normalized, 'set_config')) {
            throw new RuntimeException('identity fixture used an unexpected timeout command');
        }
        $GLOBALS['identityQueries'][] = [$query, $params];
        $GLOBALS['identityQueryTransactions'][] = $ownedConnection
            ? pg_transaction_status($connection)
            : PGSQL_TRANSACTION_IDLE;
        return array_shift($GLOBALS['identityQueryResults']);
    }
    function pg_num_rows($result): int { return $result['count']; }
    function pg_fetch_assoc($result): array { return $result['row']; }
    function pg_fetch_result($result, int $row = 0, $field = 0)
    {
        if (!is_array($result) || !is_array($result['row'] ?? null)) {
            return false;
        }
        if (is_string($field)) {
            return $result['row'][$field] ?? false;
        }
        $values = array_values($result['row']);
        return $values[$field] ?? ($values[0] ?? false);
    }
    function pg_fetch_all($result): array { return [$result['row']]; }
    function pg_free_result($result): void {}
    function pg_result_status($result): int { return $result === false ? 0 : PGSQL_COMMAND_OK; }
    function pg_last_error($connection = null): string { return 'identity fixture database error'; }
    if (!function_exists('pg_close')) {
        function pg_close($connection): bool
        {
            $GLOBALS['identityClosedConnections'][] = $connection;
            return true;
        }
    }

    $identityHomeDirectory = dirname(__DIR__, 2) . '/lib';
    $identityHomeHelper = $identityHomeDirectory . '/playthrough_home.php';
    if (file_exists($identityHomeHelper) || is_link($identityHomeHelper)) {
        fwrite(STDERR, "FAIL: refusing to replace an existing playthrough helper.\n");
        exit(1);
    }
    if (!is_dir($identityHomeDirectory) && !mkdir($identityHomeDirectory, 0700, true) && !is_dir($identityHomeDirectory)) {
        fwrite(STDERR, "FAIL: could not create isolated identity helper directory.\n");
        exit(1);
    }
    if (file_put_contents($identityHomeHelper, <<<'PHP'
<?php
function ptr_runtime_enter() {
    $GLOBALS['identityRuntimeEnters']++;
}
function ptp_connect() {
    $GLOBALS['identityConnects']++;
    return $GLOBALS['identityConnection'];
}
function pth_state($connection) {
    $GLOBALS['identityHomeConnections'][] = $connection;
    $GLOBALS['identityHomeTransactions'][] = pg_transaction_status($connection);
    return $GLOBALS['identityHomeState'];
}
PHP
    ) === false) {
        fwrite(STDERR, "FAIL: could not create isolated identity helper.\n");
        exit(1);
    }
    register_shutdown_function(static function () use ($identityHomeHelper, $identityHomeDirectory): void {
        if (is_file($identityHomeHelper) && !is_link($identityHomeHelper)) {
            @unlink($identityHomeHelper);
        }
        if (is_dir($identityHomeDirectory) && !is_link($identityHomeDirectory)) {
            @rmdir($identityHomeDirectory);
        }
    });

    require_once dirname(__DIR__) . '/server/state.php';
    function identitySourceCheck(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
    function expectIdentityQueryFailure(callable $call, string $expected): void
    {
        try {
            $call();
        } catch (RuntimeException $error) {
            identitySourceCheck($error->getMessage() === $expected, 'unexpected lookup failure');
            return;
        }
        throw new RuntimeException('failed identity lookup was accepted');
    }

    try {
        $GLOBALS['identityQueryResults'][] = ['count' => 1, 'row' => ['profile_table' => null]];
        identitySourceCheck(!pcv_profile_table_exists('fixture'), 'missing optional profile table was not detected');
        identitySourceCheck(str_contains($GLOBALS['identityQueries'][0][0], "to_regclass('chim_meta.playthrough_profiles')"),
            'profile-table detection must use the core catalog on the supplied connection');

        $GLOBALS['identityQueryResults'][] = ['count' => 1, 'row' => ['profile_table' => 'chim_meta.playthrough_profiles']];
        identitySourceCheck(pcv_profile_table_exists('fixture'), 'present profile table was not detected');

        $GLOBALS['identityQueryResults'][] = ['count' => 1, 'row' => ['value' => ' Runa ']];
        identitySourceCheck(pcv_core_player_name('fixture') === 'Runa', 'core player name was not trimmed');
        $coreQuery = $GLOBALS['identityQueries'][2];
        identitySourceCheck(str_contains($coreQuery[0], 'public.core_player')
            && $coreQuery[1] === ['player_name'], 'player lookup must use the current core Player row');

        $GLOBALS['identityQueryResults'][] = ['count' => 2, 'row' => ['value' => 'Runa']];
        identitySourceCheck(pcv_core_player_name('fixture') === null, 'ambiguous core Player rows must fail closed');

        $GLOBALS['identityQueryResults'][] = false;
        expectIdentityQueryFailure(static fn() => pcv_profile_table_exists('fixture'), 'Core profile lookup failed.');
        $GLOBALS['identityQueryResults'][] = false;
        expectIdentityQueryFailure(static fn() => pcv_core_player_name('fixture'), 'Core player lookup failed.');

        $GLOBALS['identityQueryResults'][] = ['count' => 1, 'row' => ['profile_table' => 'chim_meta.playthrough_profiles']];
        $GLOBALS['identityHomeState'] = [
            'available' => true, 'active_id' => 12,
            'playthroughs' => [['id' => 12, 'character_id' => str_repeat('a', 32), 'player_name' => 'Runa', 'active' => true]],
        ];
        $firstIdentity = pcv_current_identity();
        identitySourceCheck(($firstIdentity['key'] ?? null) === hash('sha256', '12:' . str_repeat('a', 32)),
            'the initial cached profile identity was not resolved');
        identitySourceCheck($GLOBALS['identityConnects'] === 1
            && $GLOBALS['identityHomeConnections'][0] === $GLOBALS['identityConnection'],
            'identity resolution did not use one owned read connection');
        identitySourceCheck(end($GLOBALS['identityQueryTransactions']) === PGSQL_TRANSACTION_INTRANS
            && $GLOBALS['identityHomeTransactions'][0] === PGSQL_TRANSACTION_INTRANS,
            'profile and playthrough identity reads did not share the bounded read transaction');

        $GLOBALS['identityHomeState'] = [
            'available' => true, 'active_id' => 13,
            'playthroughs' => [['id' => 13, 'character_id' => str_repeat('b', 32), 'player_name' => 'Serana', 'active' => true]],
        ];
        identitySourceCheck(pcv_current_identity() === $firstIdentity,
            'the default identity read must retain request-local caching');
        identitySourceCheck($GLOBALS['identityConnects'] === 1,
            'a cached identity read unexpectedly opened another connection');
        $GLOBALS['identityQueryResults'][] = ['count' => 1, 'row' => ['profile_table' => 'chim_meta.playthrough_profiles']];
        $freshIdentity = pcv_current_identity(true);
        identitySourceCheck(($freshIdentity['key'] ?? null) === hash('sha256', '13:' . str_repeat('b', 32)),
            'an explicit identity refresh did not query the changed active profile');

        $GLOBALS['identityHomeState'] = ['available' => false, 'active_id' => -1, 'playthroughs' => []];
        $GLOBALS['identityQueryResults'][] = ['count' => 1, 'row' => ['profile_table' => 'chim_meta.playthrough_profiles']];
        $failedIdentity = pcv_current_identity(true);
        identitySourceCheck(array_key_exists('key', $failedIdentity) && $failedIdentity['key'] === null
            && array_key_exists('player_name', $failedIdentity) && $failedIdentity['player_name'] === null,
            'a failed refreshed identity retained the prior active key or player name');

        $GLOBALS['identityQueryResults'][] = ['count' => 1, 'row' => ['profile_table' => null]];
        $GLOBALS['identityQueryResults'][] = ['count' => 1, 'row' => ['value' => 'Shared Server']];
        $fallbackIdentity = pcv_current_identity(true);
        identitySourceCheck(($fallbackIdentity['key'] ?? null) === hash('sha256', 'unprofiled:shared server')
            && ($fallbackIdentity['player_name'] ?? null) === 'Shared Server',
            'a verified missing profile table did not retain the shared-server identity fallback');
        identitySourceCheck(count($GLOBALS['identityClosedConnections']) === 4
            && count(array_unique($GLOBALS['identityClosedConnections'])) === 1,
            'identity reads did not close only their owned read connection');

        echo "identity source query checks passed\n";
    } catch (Throwable $error) {
        fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
        exit(1);
    }
    exit(0);
}

$process = proc_open([PHP_BINARY, '-n', __FILE__, '--child'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) {
    fwrite(STDERR, "FAIL: could not start isolated identity-query fixture\n");
    exit(1);
}
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);
if ($status !== 0) {
    fwrite(STDERR, $stderr !== '' ? $stderr : "FAIL: identity-query fixture failed\n");
    exit($status);
}
echo trim((string)$stdout) . "\n";
