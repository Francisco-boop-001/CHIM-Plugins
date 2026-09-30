<?php
declare(strict_types=1);

if (($argv[1] ?? null) === '--child') {
    $GLOBALS['identityQueryResults'] = [];
    $GLOBALS['identityQueries'] = [];
    function pg_query_params($connection, string $query, array $params)
    {
        $GLOBALS['identityQueries'][] = [$query, $params];
        return array_shift($GLOBALS['identityQueryResults']);
    }
    function pg_num_rows($result): int { return $result['count']; }
    function pg_fetch_assoc($result): array { return $result['row']; }
    function pg_free_result($result): void {}
    if (!function_exists('pg_close')) {
        function pg_close($connection): bool { return true; }
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
function ptp_connect() { return 'identity-fixture-connection'; }
function pth_state($connection) { return $GLOBALS['identityHomeState']; }
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

        $GLOBALS['identityHomeState'] = [
            'available' => true, 'active_id' => 13,
            'playthroughs' => [['id' => 13, 'character_id' => str_repeat('b', 32), 'player_name' => 'Serana', 'active' => true]],
        ];
        identitySourceCheck(pcv_current_identity() === $firstIdentity,
            'the default identity read must retain request-local caching');
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
