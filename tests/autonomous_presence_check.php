<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);

final class PcvAutonomousTerminated extends RuntimeException {}

function terminate(): void
{
    throw new PcvAutonomousTerminated();
}

function autonomousCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function autonomousPayload(array $actors, array $overrides = []): string
{
    return json_encode(array_replace([
        'version' => 1,
        'player_name' => 'Runa',
        'radius' => 1000,
        'overflow' => false,
        'actors' => $actors,
    ], $overrides), JSON_THROW_ON_ERROR);
}

function autonomousRunRequest(string $serverDirectory, string $payload, int $timestamp): void
{
    $GLOBALS['gameRequest'] = ['ext_pcv_presence', (string)$timestamp, '500', $payload];
    $GLOBALS['external_fast_commands'] = [];
    include $serverDirectory . '/preprocessing.php';
    autonomousCheck(in_array('ext_pcv_presence', $GLOBALS['external_fast_commands'] ?? [], true),
        'preprocessing must register the autonomous report as a fast command');

    $GLOBALS['pcv_hook_terminated'] = false;
    try {
        include $serverDirectory . '/prerequest.php';
    } catch (PcvAutonomousTerminated) {
        $GLOBALS['pcv_hook_terminated'] = true;
    }
    autonomousCheck($GLOBALS['pcv_hook_terminated'] === true,
        'the report hook must terminate before the core dialogue/LLM path');
}

function autonomousCheckInfoEventIsUntouched(string $serverDirectory): void
{
    $GLOBALS['gameRequest'] = ['infonpc', '1', '500', '{}'];
    $GLOBALS['external_fast_commands'] = [];
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $serverDirectory . '/preprocessing.php';
    autonomousCheck(!in_array('ext_pcv_presence', $GLOBALS['external_fast_commands'] ?? [], true),
        'the custom fast command must not claim CHIM’s separate infonpc side event');
    try {
        include $serverDirectory . '/prerequest.php';
    } catch (PcvAutonomousTerminated) {
        throw new RuntimeException('the infonpc side event must not be consumed by the autonomous hook');
    }
}

function autonomousSeed(string $serverDirectory, string $key, string $payload, int $timestamp): void
{
    autonomousRunRequest($serverDirectory, $payload, $timestamp);
    autonomousCheck(is_file($serverDirectory . '/state/presence.json'), 'accepted report should create a presence snapshot');
}

function autonomousCleanup(string $root, string $serverDirectory, string $logDirectory): void
{
    foreach (['state.json', 'presence.json', 'background_presence.json', 'state.lock'] as $name) {
        @unlink($serverDirectory . '/state/' . $name);
    }
    @rmdir($serverDirectory . '/state');
    foreach (['events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl', 'events.lock'] as $name) {
        @unlink($logDirectory . '/' . $name);
    }
    @rmdir($logDirectory);
    foreach (['state.php', 'scope.php', 'log.php', 'preprocessing.php', 'prerequest.php', 'manifest.json'] as $name) {
        @unlink($serverDirectory . '/' . $name);
    }
    @unlink($root . '/lib/playthrough_home.php');
    @rmdir($root . '/lib');
    @rmdir($serverDirectory);
    @rmdir(dirname($serverDirectory));
    @rmdir($root);
}

$root = sys_get_temp_dir() . '/pcv-autonomous-' . bin2hex(random_bytes(8));
$serverDirectory = $root . '/extension/server';
$logDirectory = $root . '/logs';
$exitCode = 0;

try {
    mkdir($serverDirectory, 0700, true);
    mkdir($root . '/lib', 0700, true);
    mkdir($logDirectory, 0700, true);
    foreach (['state.php', 'scope.php', 'log.php', 'preprocessing.php', 'prerequest.php', 'manifest.json'] as $name) {
        $source = dirname(__DIR__) . '/server/' . $name;
        autonomousCheck(is_file($source) && copy($source, $serverDirectory . '/' . $name), "could not stage isolated {$name}");
    }
    file_put_contents($root . '/lib/playthrough_home.php', <<<'PHP'
<?php
function ptp_connect() { return new stdClass(); }
function pth_state($connection): array
{
    return [
        'available' => true,
        'active_id' => 7,
        'playthroughs' => [[
            'id' => 7,
            'active' => true,
            'character_id' => str_repeat('a', 32),
            'player_name' => 'Runa',
        ]],
    ];
    }
PHP
    );

    putenv('PCV_LOG_DEBUG_UNTIL=' . (time() + 120));
    require_once $serverDirectory . '/log.php';
    autonomousCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger directory');
    require_once $serverDirectory . '/state.php';
    require_once $serverDirectory . '/scope.php';

    autonomousCheck(($GLOBALS['gameRequest'] ?? null) === null, 'fixture should start without a request');
    $key = pcv_current_playthrough_key();
    autonomousCheck(is_string($key), 'fixture playthrough identity should be readable');
    autonomousCheckInfoEventIsUntouched($serverDirectory);
    $catalog = [
        ['id' => '101', 'profile_id' => 1, 'npc_name' => 'Aela'],
        ['id' => '202', 'profile_id' => 2, 'npc_name' => 'Faendal'],
        ['id' => '303', 'profile_id' => 3, 'npc_name' => 'Far Away'],
        ['id' => '404', 'profile_id' => 4, 'npc_name' => 'Runa'],
    ];
    $nearby = [
        ['name' => 'Aela', 'distance' => 12, 'future_field' => 'discard me'],
        ['name' => 'Faendal', 'distance' => 40],
        ['name' => 'Far Away', 'distance' => 1001],
    ];
    $escapedPipes = pcv_parse_autonomous_presence_report(str_replace('|', '\\u007c',
        autonomousPayload([['name' => 'Aela|Variant', 'distance' => 12]], ['player_name' => 'Runa|Variant'])));
    autonomousCheck(($escapedPipes['status'] ?? null) === 'ready'
        && ($escapedPipes['player_name'] ?? null) === 'Runa|Variant'
        && ($escapedPipes['actors'][0]['name'] ?? null) === 'Aela|Variant',
        'JSON-escaped pipe characters should survive the native request framing and parser');

    autonomousRunRequest($serverDirectory, autonomousPayload($nearby), 900000000000000001);
    $presencePath = $serverDirectory . '/state/presence.json';
    $stored = json_decode((string)file_get_contents($presencePath), true, 16, JSON_THROW_ON_ERROR);
    autonomousCheck(($stored['actors'] ?? null) === [
        ['name' => 'Aela', 'distance' => 12],
        ['name' => 'Faendal', 'distance' => 40],
    ], 'cache should keep only bounded names/distances in range and drop unrelated producer fields');
    $read = pcv_read_eligible_npcs($key, $catalog, 'Runa', $serverDirectory . '/state');
    autonomousCheck(($read['status'] ?? null) === 'missing',
        'the legacy companion report must not authorize the background-feed picker');
    autonomousCheck(($stored['autonomous_order']['request_timestamp'] ?? null) === 900000000000000001,
        'accepted report should retain its bounded native ordering timestamp');

    autonomousRunRequest($serverDirectory, autonomousPayload([
        ['name' => 'Far Away', 'distance' => 1001],
    ]), 900000000000000002);
    $storedEmpty = json_decode((string)file_get_contents($presencePath), true, 16, JSON_THROW_ON_ERROR);
    autonomousCheck(($storedEmpty['actors'] ?? null) === [], 'legacy producer should preserve its distinct empty result');

    autonomousSeed($serverDirectory, $key, autonomousPayload($nearby), 900000000000000010);
    autonomousRunRequest($serverDirectory, autonomousPayload([['name' => 'Aela', 'distance' => 12]]), 900000000000000009);
    $afterOutOfOrder = json_decode((string)file_get_contents($presencePath), true, 16, JSON_THROW_ON_ERROR);
    autonomousCheck(($afterOutOfOrder['autonomous_order']['request_timestamp'] ?? null) === 900000000000000010
        && count($afterOutOfOrder['actors'] ?? []) === 2,
        'an older fresh report must not overwrite the most recent accepted report');

    $afterOutOfOrder['observed_at'] = time() - 46;
    $afterOutOfOrder['autonomous_order']['observed_at'] = time() - 46;
    file_put_contents($presencePath, json_encode($afterOutOfOrder, JSON_THROW_ON_ERROR));
    autonomousRunRequest($serverDirectory, autonomousPayload([['name' => 'Aela', 'distance' => 7]]), 3);
    $afterClockReset = json_decode((string)file_get_contents($presencePath), true, 16, JSON_THROW_ON_ERROR);
    autonomousCheck(($afterClockReset['autonomous_order']['request_timestamp'] ?? null) === 3
        && count($afterClockReset['actors'] ?? []) === 1,
        'a clock reset must recover after the prior report has exceeded the freshness window');

    autonomousSeed($serverDirectory, $key, autonomousPayload($nearby), 900000000000000020);
    autonomousRunRequest($serverDirectory, autonomousPayload($nearby, ['player_name' => 'Another Save']), 900000000000000021);
    autonomousCheck(!is_file($presencePath),
        'a current-player mismatch must invalidate the legacy report instead of binding it to this playthrough');

    autonomousSeed($serverDirectory, $key, autonomousPayload($nearby), 900000000000000030);
    autonomousRunRequest($serverDirectory, autonomousPayload($nearby, ['overflow' => true]), 900000000000000031);
    autonomousCheck(!is_file($presencePath), 'overflow must invalidate partial actor data rather than silently truncate it');

    autonomousSeed($serverDirectory, $key, autonomousPayload($nearby), 900000000000000040);
    autonomousRunRequest($serverDirectory, '{malformed', 900000000000000041);
    autonomousCheck(!is_file($presencePath), 'malformed matching event must clear its legacy report safely');

    $events = array_map(static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR),
        file((string)pcv_log_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    autonomousCheck(count(array_filter($events, static fn(array $event): bool =>
        ($event['event'] ?? null) === 'state.presence_refreshed')) >= 1,
        'accepted heartbeat should be observable at debug level');
    autonomousCheck(count(array_filter($events, static fn(array $event): bool =>
        ($event['event'] ?? null) === 'state.presence_rejected'
        && ($event['reason'] ?? null) === 'presence_stale')) >= 1,
        'out-of-order reports should be observable as rejected without claiming presence storage failed');
    $logText = (string)file_get_contents((string)pcv_log_path());
    autonomousCheck(!str_contains($logText, 'Runa') && !str_contains($logText, 'Aela') && !str_contains($logText, 'Faendal'),
        'presence logs must not contain player or NPC display names');

    echo "PASS: legacy producer validation and isolation, fast-command termination, bounded ordering, and fail-closed invalidation\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    putenv('PCV_LOG_DEBUG_UNTIL');
    autonomousCleanup($root, $serverDirectory, $logDirectory);
}

exit($exitCode);
