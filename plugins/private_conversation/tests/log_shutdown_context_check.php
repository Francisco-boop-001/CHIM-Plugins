<?php
declare(strict_types=1);

// request_unobserved means the completion hook did not record a terminal outcome. It does not
// establish why processing stopped or whether speech reached playback.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';

$directory = sys_get_temp_dir() . '/pcv-shutdown-context-' . bin2hex(random_bytes(6));
$exitCode = 0;
try {
    if (!mkdir($directory, 0700) || !pcv_log_set_test_directory($directory)) {
        throw new RuntimeException('could not create isolated logger fixture');
    }
    pcv_log_set_request_type('rechat');
    pcv_log_register_shutdown_observer();
    pcv_log_shutdown_terminal();
    $lines = file($directory . '/events.jsonl') ?: [];
    $last = json_decode((string)end($lines), true);
    echo 'context=' . json_encode($last['context'] ?? null) . "\n";
    if (($last['reason'] ?? null) !== 'request_unobserved' || ($last['context']['request_type'] ?? null) !== 'rechat'
        || ($last['context']['phase'] ?? null) !== 'shutdown') {
        throw new RuntimeException('An unobserved finish must carry phase=shutdown and the request type.');
    }
    if (!is_int($last['elapsed_ms'] ?? null) && !is_float($last['elapsed_ms'] ?? null)) {
        throw new RuntimeException('Every entry keeps its top-level elapsed_ms.');
    }
    // The plugin page shows the last scene turn from a tiny record, not by scanning the log.
    $config = '123e4567-e89b-42d3-a456-426614174000';
    pcv_log_set_config_id($config);
    pcv_log_event('routing.request_finished', 'info', 'postrequest_observed', null, ['phase' => 'postrequest', 'route' => 'scene_direction']);
    $turn = json_decode((string)@file_get_contents($directory . '/last_turn.json'), true);
    echo 'last_turn=' . json_encode($turn) . "\n";
    if (($turn['config_id'] ?? null) !== $config || ($turn['outcome'] ?? null) !== 'postrequest_observed'
        || !is_string($turn['timestamp'] ?? null) || count($turn) !== 3) {
        throw new RuntimeException('A finished scene turn must record only config, outcome and time.');
    }
    pcv_log_event('routing.request_finished', 'info', 'skipped', 'scope_off', ['phase' => 'preprocessing']);
    $turn = json_decode((string)@file_get_contents($directory . '/last_turn.json'), true);
    if (($turn['outcome'] ?? null) !== 'postrequest_observed') {
        throw new RuntimeException('Requests that never entered a scene must not overwrite the last scene turn.');
    }
    echo "PASS unobserved finishes carry the request type and elapsed time; last scene turn recorded\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach (glob($directory . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($directory);
}
exit($exitCode);
