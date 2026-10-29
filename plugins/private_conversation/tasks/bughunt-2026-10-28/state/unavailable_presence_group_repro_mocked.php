<?php
declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: unavailable_presence_group_repro_mocked.php <pcv-repo-root>\n");
    exit(2);
}
$repo = rtrim($argv[1], DIRECTORY_SEPARATOR);
$runtimeDir = __DIR__ . DIRECTORY_SEPARATOR . 'mock-runtime-final-20261028';
$caseDir = __DIR__ . DIRECTORY_SEPARATOR . 'unavailable-presence-case-final-20261028';
foreach ([$runtimeDir, $caseDir] as $path) {
    if (file_exists($path) || is_link($path)) {
        fwrite(STDERR, "Refusing to reuse an existing fixture path.\n");
        exit(2);
    }
    if (!mkdir($path, 0700)) {
        fwrite(STDERR, "Could not create an isolated fixture directory.\n");
        exit(2);
    }
}

$logger = <<<'LOGGER'
<?php
declare(strict_types=1);
function pcv_log_default_directory(bool $create): ?string { return null; }
function pcv_log_new_uuid(): ?string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}
function pcv_log_valid_uuid(string $value): bool
{
    return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value) === 1;
}
function pcv_log_event(...$args): void {}
function pcv_log_exception(...$args): void {}
function pcv_log_presence_observed(...$args): void {}
function pcv_log_set_playthrough_ref(...$args): void {}
function pcv_log_set_config_id(...$args): void {}
function pcv_log_begin_request(...$args): void {}
function pcv_log_set_request_type(...$args): void {}
function pcv_log_set_terminal(...$args): void {}
function pcv_log_enum_values(...$args): array { return []; }
LOGGER;
$sourceHashes = [];
foreach (['state.php', 'scope.php'] as $file) {
    $source = $repo . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . $file;
    $target = $runtimeDir . DIRECTORY_SEPARATOR . $file;
    if (!copy($source, $target)) {
        fwrite(STDERR, "Could not copy source file: {$file}\n");
        exit(2);
    }
    $sourceHashes[$file] = hash_file('sha256', $source);
    if (!hash_equals($sourceHashes[$file], hash_file('sha256', $target))) {
        fwrite(STDERR, "Copied source hash mismatch: {$file}\n");
        exit(2);
    }
}
if (file_put_contents($runtimeDir . DIRECTORY_SEPARATOR . 'log.php', $logger) !== strlen($logger)) {
    fwrite(STDERR, "Could not write the fixture-only logger stub.\n");
    exit(2);
}
require_once $runtimeDir . DIRECTORY_SEPARATOR . 'state.php';
require_once $runtimeDir . DIRECTORY_SEPARATOR . 'scope.php';

$key = str_repeat('a', 64);
$now = time();
$heartbeat = $now * 1000000000;
$baseline = $heartbeat - 2000000000;
$catalog = [
    ['id' => 101, 'profile_id' => 1, 'npc_name' => 'Aela', 'metadata' => ['activity_status' => ['timestamp' => $heartbeat - 1000000000]]],
    ['id' => 202, 'profile_id' => 1, 'npc_name' => 'Bryn', 'metadata' => ['activity_status' => ['timestamp' => $heartbeat - 1000000000]]],
    ['id' => 303, 'profile_id' => 1, 'npc_name' => 'Lydia', 'metadata' => ['activity_status' => ['timestamp' => $baseline]]],
    ['id' => 404, 'profile_id' => 1, 'npc_name' => 'Faendal', 'metadata' => ['activity_status' => ['timestamp' => $baseline]]],
];
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
$doc = [
    'version' => PCV_BACKGROUND_PRESENCE_VERSION,
    'source' => 'infonpc_close_v1',
    'key' => $key,
    'player_name' => 'Player',
    'state' => 'ready',
    'reason' => null,
    'observed_at' => $now,
    'heartbeat_timestamp' => $heartbeat,
    'baseline_timestamp' => $baseline,
    'actors' => [['name' => 'Aela'], ['name' => 'Bryn']],
    'recent' => [
        'aela' => ['name' => 'Aela', 'seen_at' => $now],
        'bryn' => ['name' => 'Bryn', 'seen_at' => $now],
    ],
];
if (file_put_contents($caseDir . DIRECTORY_SEPARATOR . 'background_presence.json', json_encode($doc, JSON_THROW_ON_ERROR)) === false) {
    fwrite(STDERR, "Could not write the fixture presence report.\n");
    exit(2);
}
$lock = pcv_lock_state($caseDir, true, LOCK_EX);
try {
    pcv_write_store($caseDir, $stored);
} finally {
    pcv_unlock_state($lock);
}

$strict = pcv_read_eligible_npcs($key, $catalog, 'Player', $caseDir);
$currentInput = ['status' => 'ready', 'actors' => [['name' => 'Aela'], ['name' => 'Bryn']]];
$eligibleMap = pcvScopeEligibleMapFromPresence($currentInput['actors'], $catalog, 'Player');
$inScene = pcv_read_active_scene_npcs($key, $catalog, 'Player', ['101', '202', '303', '404'], $caseDir, $now);
// Match scope.php:553-559: current-input eligibility plus active in-scene evidence, with drops disabled only on error.
$activeMap = ($eligibleMap ?? []) + $inScene['known_npcs'];
$allowDrops = ($inScene['error'] ?? false) !== true;
$result = pcv_begin_request($key, true, $caseDir, $eligibleMap, $activeMap,
    $inScene['missing'] === [] ? null : (string)reset($inScene['missing']), null, $allowDrops);
$after = pcv_read($key, $caseDir);
$afterIds = is_array($after['scope'] ?? null) ? pcv_config_actor_ids($after['scope']) : [];

// Control the same state transition under the evidence-unavailable policy described by state.php:2449-2451.
$lock = pcv_lock_state($caseDir, true, LOCK_EX);
try {
    pcv_write_store($caseDir, $stored);
} finally {
    pcv_unlock_state($lock);
}
$control = pcv_begin_request($key, true, $caseDir, $eligibleMap, $activeMap, 'presence_error', null, false);
$controlAfter = pcv_read($key, $caseDir);
$controlIds = is_array($controlAfter['scope'] ?? null) ? pcv_config_actor_ids($controlAfter['scope']) : [];

$summary = [
    'copied_source_sha256' => $sourceHashes,
    'close_report_state' => $doc['state'],
    'strict_reader' => ['status' => $strict['status'] ?? null, 'known_npcs' => $strict['known_npcs'] ?? null],
    'current_request_map' => $eligibleMap,
    'wide_report_present' => file_exists($caseDir . DIRECTORY_SEPARATOR . 'background_wide_presence.json'),
    'active_reader' => ['error' => $inScene['error'] ?? null, 'missing' => $inScene['missing'] ?? null, 'known_npcs' => $inScene['known_npcs'] ?? null],
    'production_caller_allow_drops' => $allowDrops,
    'actual_begin_status' => $result['status'] ?? null,
    'actual_active_ids_after' => $afterIds,
    'actual_dropped_after' => $after['dropped'] ?? [],
    'unreadable_control_begin_status' => $control['status'] ?? null,
    'unreadable_control_active_ids_after' => $controlIds,
];
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

if (($strict['status'] ?? null) !== 'ready' || ($strict['known_npcs'] ?? null) !== ['101' => 'Aela', '202' => 'Bryn']
    || ($inScene['error'] ?? true) !== false || $allowDrops !== true
    || $afterIds !== ['101', '202'] || count($summary['actual_dropped_after']) !== 2
    || $controlIds !== ['101', '202', '303', '404']) {
    fwrite(STDERR, "The expected unavailable-wide partial-group transition did not reproduce.\n");
    exit(1);
}