<?php
declare(strict_types=1);

// 0.1.13 E4: unreadable presence evidence is logged and never drops members (the turn is refused instead).
// 0.1.13 E5: five minutes of refusals ends the scene; the stored reason reflects the final eligibility check. An accepted turn resets the clock.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';
require_once dirname(__DIR__) . '/server/log_reader.php';

function resCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function resStored(string $directory): array
{
    return json_decode((string)file_get_contents($directory . '/state.json'), true) ?: [];
}

function resClean(string $directory): void
{
    foreach (glob($directory . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
        if (is_dir($path) && !in_array(basename($path), ['.', '..'], true)) {
            @rmdir($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($directory);
}

$stateDirectory = sys_get_temp_dir() . '/pcv-resilience-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-resilience-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    resCheck(mkdir($stateDirectory, 0700) && mkdir($logDirectory, 0700), 'could not create fixture directories');
    resCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');
    $known = ['404' => 'Stalleo', '505' => 'Guard One', '606' => 'Guard Two'];
    $trio = ['enabled' => true, 'scene_mode' => 'pair', 'actor_ids' => ['404', '505', '606'], 'opener' => 'auto',
        'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $key = hash('sha256', 'resilience playthrough');
    $rows = [['id' => 404, 'npc_name' => 'Stalleo', 'profile_id' => 1], ['id' => 505, 'npc_name' => 'Guard One', 'profile_id' => 2],
        ['id' => 606, 'npc_name' => 'Guard Two', 'profile_id' => 3]];

    // E4: an unreadable close report is an error, not absence.
    mkdir($stateDirectory . '/background_presence.json', 0700);
    $inScene = pcv_read_active_scene_npcs($key, $rows, 'Hero', ['404', '505'], $stateDirectory);
    echo 'in_scene=' . json_encode($inScene) . "\n";
    resCheck(($inScene['error'] ?? false) === true, 'An unreadable close report is reported as an error.');
    rmdir($stateDirectory . '/background_presence.json');
    $missing = pcv_read_active_scene_npcs($key, $rows, 'Hero', ['404'], $stateDirectory);
    resCheck(($missing['error'] ?? false) === false, 'A missing report is absence, not an error.');

    // E4: with drops disallowed, a narrowing becomes a refusal and nobody is dropped.
    pcv_stage($key, $trio, $known, $stateDirectory);
    pcv_begin_request($key, true, $stateDirectory, $known);
    $r = pcv_begin_request($key, false, $stateDirectory, null, ['404' => 'S', '505' => 'G1'], 'presence_error', null, false);
    $stored = resStored($stateDirectory);
    resCheck(($r['reason'] ?? null) === 'scene_not_eligible' && ($stored['active']['config']['actor_ids'] ?? null) === ['404', '505', '606']
        && !isset($stored['active']['dropped']), 'An evidence error refuses the turn instead of dropping a member.');

    // E5: refusals start a clock; five minutes of refusals end the scene.
    resCheck(is_int($stored['active']['refused_since'] ?? null), 'The first refusal records refused_since.');
    $stored['active']['activated_at'] = time() - 400;
    $stored['active']['expires_at'] = time() - 400 + PCV_ACTIVE_TTL;
    $stored['active']['refused_since'] = time() - 301;
    file_put_contents($stateDirectory . '/state.json', json_encode($stored));
    resCheck(pcv_valid_stored_state(resStored($stateDirectory)), 'refused_since is a valid stored field.');
    $r = pcv_begin_request($key, false, $stateDirectory, null, ['404' => 'S'], 'wide_absent');
    $stored = resStored($stateDirectory);
    echo 'after_end=' . json_encode(['active' => $stored['active'], 'last_end' => $stored['last_end'] ?? null]) . "\n";
    resCheck(($r['reason'] ?? null) === 'scene_not_eligible' && $stored['active'] === null, 'Five minutes of refusals end the scene; this input is still refused.');
    resCheck(($stored['last_end']['reason'] ?? null) === 'members_gone', 'The automatic end is remembered for the page.');
    resCheck((pcv_read($key, $stateDirectory)['last_end']['reason'] ?? null) === 'members_gone', 'pcv_read exposes the automatic end.');
    resCheck((pcv_begin_request($key, true, $stateDirectory, $known)['status'] ?? null) === 'off', 'The next input passes normally.');

    // E5: an accepted turn clears the refusal clock.
    pcv_stage($key, $trio, $known, $stateDirectory);
    resCheck(!isset(resStored($stateDirectory)['last_end']), 'Arming again clears the remembered end.');
    pcv_begin_request($key, true, $stateDirectory, $known);
    pcv_begin_request($key, false, $stateDirectory, null, ['404' => 'S'], 'wide_absent');
    resCheck(is_int(resStored($stateDirectory)['active']['refused_since'] ?? null), 'fixture: refusal clock started.');
    pcv_begin_request($key, false, $stateDirectory, null, $known);
    resCheck(!isset(resStored($stateDirectory)['active']['refused_since']), 'An accepted turn clears the refusal clock.');

    // E5: the timeout reason describes the final eligibility check, not the whole mixed refusal interval.
    pcv_begin_request($key, false, $stateDirectory, null, ['404' => 'S'], 'wide_absent');
    $stored = resStored($stateDirectory);
    resCheck(($stored['active']['config']['actor_ids'] ?? null) === ['404', '505', '606'] && !isset($stored['active']['dropped']),
        'An insufficient readable map refuses without dropping any group member.');
    $stored['active']['activated_at'] = time() - 400;
    $stored['active']['expires_at'] = $stored['active']['activated_at'] + PCV_ACTIVE_TTL;
    $stored['active']['refused_since'] = time() - 301;
    file_put_contents($stateDirectory . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    resCheck(pcv_valid_stored_state(resStored($stateDirectory)), 'mixed refusal fixture remains valid.');
    $r = pcv_begin_request($key, false, $stateDirectory, null, ['404' => 'S'], 'presence_error', null, false);
    $stored = resStored($stateDirectory);
    resCheck(($r['reason'] ?? null) === 'scene_not_eligible' && $stored['active'] === null,
        'Five minutes of refusals still end the scene and refuse this input.');
    resCheck(($stored['last_end']['reason'] ?? null) === 'evidence_unavailable',
        'An unreadable final check ends with evidence_unavailable after a mixed refusal interval.');
    resCheck(pcv_valid_stored_state($stored), 'evidence_unavailable is a valid stored end reason.');
    resCheck((pcv_read($key, $stateDirectory)['last_end']['reason'] ?? null) === 'evidence_unavailable',
        'pcv_read exposes the evidence_unavailable end reason.');

    $entries = array_values(array_filter(array_map(static fn($line) => json_decode($line, true), file($logDirectory . '/events.jsonl') ?: []), 'is_array'));
    $ended = array_values(array_filter($entries, static fn($e) => ($e['event'] ?? null) === 'state.scope_ended' && ($e['reason'] ?? null) === 'members_gone'));
    resCheck(count($ended) === 1, 'The automatic end is logged as state.scope_ended members_gone.');
    $unavailableEnds = array_values(array_filter($entries, static fn($e) => ($e['event'] ?? null) === 'state.scope_ended'
        && ($e['reason'] ?? null) === 'evidence_unavailable'));
    resCheck(count($unavailableEnds) === 1, 'The unreadable final check is logged as state.scope_ended evidence_unavailable.');
    $readback = pcv_diagnostics_read($logDirectory . '/events.jsonl', ['event' => 'state.scope_ended']);
    $readbackReasons = array_column($readback['entries'] ?? [], 'reason');
    resCheck(($readback['status'] ?? null) === 'ok' && count(array_filter($readbackReasons,
        static fn($reason) => $reason === 'evidence_unavailable')) === 1,
        'The diagnostics reader retains the evidence_unavailable end reason.');
    $readErrors = array_values(array_filter($entries, static fn($e) => ($e['event'] ?? null) === 'state.unavailable'
        && ($e['context']['operation'] ?? null) === 'presence_read'));
    resCheck(count($readErrors) >= 1, 'The unreadable report was logged.');

    echo "PASS evidence errors refuse instead of dropping; five minutes of refusals end a stuck scene\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    resClean($stateDirectory);
    foreach (glob($logDirectory . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($logDirectory);
}
exit($exitCode);
