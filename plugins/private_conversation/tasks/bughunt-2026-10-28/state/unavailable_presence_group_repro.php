<?php
declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: unavailable_presence_group_repro.php <pcv-repo-root>\n");
    exit(2);
}
$repo = rtrim($argv[1], DIRECTORY_SEPARATOR);
$caseDir = __DIR__ . DIRECTORY_SEPARATOR . 'unavailable-presence-case-retry';
$logDir = __DIR__ . DIRECTORY_SEPARATOR . 'unavailable-presence-logs-retry';
foreach ([$caseDir, $logDir] as $path) {
    if (file_exists($path) || is_link($path)) {
        fwrite(STDERR, "Refusing to reuse an existing fixture path.\n");
        exit(2);
    }
    if (!mkdir($path, 0700)) {
        fwrite(STDERR, "Could not create an isolated fixture directory.\n");
        exit(2);
    }
}

define('PCV_LOG_TESTING', true);
require_once $repo . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'log.php';
if (!pcv_log_set_test_directory($logDir)) {
    fwrite(STDERR, "Could not set the fixture-only log directory.\n");
    exit(2);
}
require_once $repo . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'state.php';
require_once $repo . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'scope.php';

$key = str_repeat('a', 64);
$now = time();
$originalConfig = [
    'enabled' => true,
    'scene_mode' => 'pair',
    'actor_a' => '101',
    'actor_b' => '202',
    'actor_ids' => ['101', '202', '303', '404'],
    'opener' => 'auto',
    'exclude_player' => true,
    'bystander_mode' => 'exclude',
];
$stored = [
    'version' => PCV_STATE_VERSION,
    'key' => $key,
    'active' => [
        'config' => $originalConfig,
        'config_id' => '11111111-1111-4111-8111-111111111111',
        'activated_at' => $now - 1000,
        'renewed_at' => $now,
        'expires_at' => $now + PCV_ACTIVE_TTL,
    ],
    'pending' => null,
];
if (!pcv_valid_stored_state($stored)) {
    fwrite(STDERR, "Fixture state is invalid.\n");
    exit(2);
}
$lock = pcv_lock_state($caseDir, true, LOCK_EX);
try {
    pcv_write_store($caseDir, $stored);
} finally {
    pcv_unlock_state($lock);
}

// This is the producer's real failure path: a malformed close report persists state=unavailable.
$capture = pcv_capture_background_presence_report($key, '', 'Player', (string)$now, $caseDir);
$catalog = [
    ['id' => 101, 'profile_id' => 1, 'npc_name' => 'Aela'],
    ['id' => 202, 'profile_id' => 1, 'npc_name' => 'Bryn'],
    ['id' => 303, 'profile_id' => 1, 'npc_name' => 'Lydia'],
    ['id' => 404, 'profile_id' => 1, 'npc_name' => 'Faendal'],
];
$strictCurrentMap = ['101' => 'Aela', '202' => 'Bryn'];
$inScene = pcv_read_active_scene_npcs($key, $catalog, 'Player', ['101', '202', '303', '404'], $caseDir, $now);

// Match the production caller's map/error decision at scope.php:541-559.
$activeMap = $strictCurrentMap + $inScene['known_npcs'];
$allowDrops = ($inScene['error'] ?? false) !== true;
$result = pcv_begin_request($key, true, $caseDir, $strictCurrentMap, $activeMap,
    $inScene['missing'] === [] ? null : (string)reset($inScene['missing']), null, $allowDrops);
$after = pcv_read($key, $caseDir);
$afterIds = is_array($after['scope'] ?? null) ? pcv_config_actor_ids($after['scope']) : [];

// Control: the same current strict map remains a refusal when the caller knows the auxiliary evidence failed.
$lock = pcv_lock_state($caseDir, true, LOCK_EX);
try {
    pcv_write_store($caseDir, $stored);
} finally {
    pcv_unlock_state($lock);
}
$control = pcv_begin_request($key, true, $caseDir, $strictCurrentMap, $activeMap,
    'presence_error', null, false);
$controlAfter = pcv_read($key, $caseDir);
$controlIds = is_array($controlAfter['scope'] ?? null) ? pcv_config_actor_ids($controlAfter['scope']) : [];

$summary = [
    'capture_status' => $capture['status'] ?? null,
    'stored_report_state' => json_decode((string)file_get_contents($caseDir . DIRECTORY_SEPARATOR . 'background_presence.json'), true)['state'] ?? null,
    'active_reader_error' => $inScene['error'] ?? null,
    'active_reader_report_fresh' => $inScene['report_fresh'] ?? null,
    'active_reader_missing' => $inScene['missing'] ?? null,
    'production_caller_allow_drops' => $allowDrops,
    'actual_begin_status' => $result['status'] ?? null,
    'actual_active_ids_after' => $afterIds,
    'actual_dropped_after' => $after['dropped'] ?? [],
    'error_control_begin_status' => $control['status'] ?? null,
    'error_control_active_ids_after' => $controlIds,
];
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

if (($summary['stored_report_state'] ?? null) !== 'unavailable'
    || ($summary['active_reader_error'] ?? true) !== false
    || $allowDrops !== true
    || $afterIds !== ['101', '202']
    || count($summary['actual_dropped_after']) !== 2
    || $controlIds !== ['101', '202', '303', '404']) {
    fwrite(STDERR, "The expected partial-group transition did not reproduce.\n");
    exit(1);
}
