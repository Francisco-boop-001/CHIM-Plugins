<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function clockRegressionCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function clockRegressionClean(string $directory): void
{
    foreach (glob($directory . '/*') ?: [] as $path) {
        if (is_file($path) && !is_link($path)) {
            @unlink($path);
        }
    }
    @rmdir($directory);
}

$stateDirectory = sys_get_temp_dir() . '/pcv-clock-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-clock-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    clockRegressionCheck(mkdir($stateDirectory, 0700) && mkdir($logDirectory, 0700), 'could not create fixture directories');
    clockRegressionCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');

    $known = ['101' => 'Aela', '202' => 'Lydia'];
    $config = ['enabled' => true, 'actor_a' => '101', 'actor_b' => '202',
        'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $key = hash('sha256', 'PCV activation clock rollback regression');
    pcv_stage($key, $config, $known, $stateDirectory);
    clockRegressionCheck((pcv_begin_request($key, true, $stateDirectory, $known)['status'] ?? null) === 'active',
        'fixture scene did not activate');

    $stored = json_decode((string)file_get_contents($stateDirectory . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    $stored['active']['activated_at'] = time() + 3600;
    $stored['active']['expires_at'] = $stored['active']['activated_at'] + PCV_ACTIVE_TTL;
    unset($stored['active']['renewed_at']);
    file_put_contents($stateDirectory . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    clockRegressionCheck(pcv_valid_stored_state($stored), 'future activation fixture must be valid before first refusal');

    pcv_begin_request($key, false, $stateDirectory, null, ['101' => 'Aela'], 'presence_error', null, false);
    $stored = json_decode((string)file_get_contents($stateDirectory . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    clockRegressionCheck(is_int($stored['active']['refused_since'] ?? null)
        && $stored['active']['refused_since'] >= $stored['active']['activated_at'],
        'the first refusal timestamp must be clamped to activated_at when the wall clock is behind');
    clockRegressionCheck(pcv_valid_stored_state($stored), 'first refusal must leave a valid stored state');
    clockRegressionCheck((pcv_read($key, $stateDirectory)['status'] ?? null) === 'active',
        'state remains readable after the first refusal');

    $stored['active']['refused_since'] = $stored['active']['activated_at'] - 1;
    file_put_contents($stateDirectory . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    clockRegressionCheck((pcv_read($key, $stateDirectory)['status'] ?? null) === 'unavailable',
        'fixture must represent preexisting corrupt refused_since state');
    $ended = pcv_stage($key, ['enabled' => false], $known, $stateDirectory);
    clockRegressionCheck(($ended['status'] ?? null) === 'off'
        && (pcv_read($key, $stateDirectory)['status'] ?? null) === 'off',
        'END must still recover preexisting corrupt state');
    clockRegressionCheck(count(glob($stateDirectory . '/state.json.corrupt-*') ?: []) === 1,
        'END must preserve a diagnostic copy of the corrupt state');

    echo "PASS: backward clock refusals remain valid and END recovers corrupt state\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    clockRegressionClean($stateDirectory);
    clockRegressionClean($logDirectory);
}
exit($exitCode);
