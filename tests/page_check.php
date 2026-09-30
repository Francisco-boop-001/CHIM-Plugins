<?php
declare(strict_types=1);

function pageCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pageCheckChild(string $runner, string $scenario, string $root): array
{
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $runner, $scenario, $root],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not launch the isolated UI request.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 || !is_string($stdout) || trim($stdout) === '' || !is_string($stderr) || $stderr !== '') {
        throw new RuntimeException('The isolated UI request failed: ' . trim((string)$stderr));
    }
    $decoded = base64_decode(trim($stdout), true);
    $result = is_string($decoded) ? json_decode($decoded, true, 32, JSON_THROW_ON_ERROR) : null;
    if (!is_array($result)) {
        throw new RuntimeException('The isolated UI request returned invalid fixture output.');
    }
    return $result;
}

function pageCheckSessionId(string $scenario): string
{
    return 'pagecheck-' . str_replace('_', '-', $scenario);
}

$root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pcv-page-check-' . bin2hex(random_bytes(8));
$server = $root . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'server';
$engine = $root;
$logs = $root . DIRECTORY_SEPARATOR . 'logs';
$sessions = $root . DIRECTORY_SEPARATOR . 'sessions';
foreach ([$server, $logs, $sessions, $engine . '/lib/core', $engine . '/conf'] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the isolated UI fixture directory.');
    }
    chmod($directory, 0700);
}

$packageServer = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'server';
foreach (['index.php', 'log.php', 'scope.php'] as $file) {
    if (!copy($packageServer . DIRECTORY_SEPARATOR . $file, $server . DIRECTORY_SEPARATOR . $file)) {
        throw new RuntimeException('Could not copy the actual UI controller into the isolated fixture.');
    }
}

file_put_contents($server . DIRECTORY_SEPARATOR . 'state.php', <<<'PHP'
<?php
declare(strict_types=1);

function pcv_current_playthrough_key(): ?string { return 'fixture-playthrough'; }
function pcv_current_player_name(): ?string { return 'Player'; }

function pcv_read_eligible_npcs(?string $key, array $catalogRows, ?string $playerName, ?string $stateDirectory = null): array
{
    $scenario = $GLOBALS['pcv_page_scenario'];
    $GLOBALS['pcv_page_session_closed'] = session_status() === PHP_SESSION_NONE;
    $GLOBALS['pcv_eligibility_calls'][] = [
        'key' => $key,
        'catalog_count' => count($catalogRows),
        'player_name' => $playerName,
    ];
    $status = match ($scenario) {
        'get_single', 'post_single', 'post_solo' => 'ready',
        'get_empty' => 'empty',
        'get_missing' => 'missing',
        'get_stale', 'post_stale' => 'stale',
        default => 'ready',
    };
    $known = match ($scenario) {
        'get_single', 'post_single', 'post_solo' => ['101' => 'Aela'],
        'post_stale' => [],
        'get_empty', 'get_missing', 'get_stale' => [],
        default => ['101' => 'Aela', '202' => 'Bryn'],
    };
    return [
        'status' => $status,
        'known_npcs' => $known,
        'observed_at' => $status === 'missing' ? null : time(),
        'reason' => match ($status) {
            'missing' => 'presence_missing',
            'stale' => 'presence_stale',
            default => null,
        },
    ];
}

function pcv_read(string $key): array
{
    $scenario = $GLOBALS['pcv_page_scenario'];
    $count = ($GLOBALS['pcv_page_read_count'] ?? 0) + 1;
    $GLOBALS['pcv_page_read_count'] = $count;
    if ($scenario === 'get_read_failure') {
        throw new RuntimeException('private state fixture details');
    }

    if ($count > 1 && $scenario === 'post_readback_unavailable') {
        $state = ['status' => 'unavailable', 'scope' => null, 'pending' => false, 'config_id' => null, 'pending_config_id' => null];
    } elseif ($count > 1 && $scenario === 'post_readback_changed') {
        $state = [
            'status' => 'active',
            'scope' => ['enabled' => true, 'actor_a' => '202', 'actor_b' => '303', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
            'pending' => false,
            'pending_scope' => null,
            'config_id' => '77777777-7777-4777-8777-777777777777',
            'pending_config_id' => null,
        ];
    } else {
        $state = [
            'status' => 'active',
            'scope' => ['enabled' => true, 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
            'pending' => false,
            'pending_scope' => null,
            'config_id' => '33333333-3333-4333-8333-333333333333',
            'pending_config_id' => null,
        ];
    }
    pcv_log_set_config_id($state['config_id'] ?? null);
    return $state;
}

function pcv_stage(string $key, array $desired, array $knownNpcs): array
{
    $GLOBALS['pcv_page_stage_calls'] = ($GLOBALS['pcv_page_stage_calls'] ?? 0) + 1;
    $GLOBALS['pcv_stage_known_npcs'] = $knownNpcs;
    $GLOBALS['pcv_stage_desired'] = $desired;
    $state = [
        'status' => 'active',
        'scope' => ['enabled' => true, 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
        'pending' => true,
        'pending_scope' => $desired,
        'config_id' => '33333333-3333-4333-8333-333333333333',
        'pending_config_id' => '44444444-4444-4444-8444-444444444444',
    ];
    pcv_log_set_config_id($state['pending_config_id']);
    return $state;
}
PHP);

file_put_contents($engine . '/lib/runtime_bootstrap.php', <<<'PHP'
<?php
function chimRuntimeImportConfigVariables(array $variables): void
{
    foreach ($variables as $name => $value) {
        if (is_string($name) && preg_match('/\A[A-Z0-9_]+\z/', $name) === 1) {
            $GLOBALS[$name] = $value;
        }
    }
}
PHP);
file_put_contents($engine . '/conf/conf.sample.php', "<?php \$DBDRIVER = 'fixture_db';\n");
file_put_contents($engine . '/conf/conf.php', "<?php // Isolated test configuration.\n");
file_put_contents($engine . '/lib/fixture_db.class.php', <<<'PHP'
<?php
class sql {}
PHP);
file_put_contents($engine . '/lib/core/npc_master.class.php', <<<'PHP'
<?php
class NpcMaster
{
    public function getAll(): array
    {
        if (($GLOBALS['pcv_page_scenario'] ?? null) === 'end_catalog_failure') {
            throw new RuntimeException('private catalog fixture details');
        }
        return [
            ['id' => 101, 'npc_name' => 'Aela', 'profile_id' => 9],
            ['id' => 202, 'npc_name' => 'Bryn', 'profile_id' => 10],
            ['id' => 303, 'npc_name' => 'Lydia', 'profile_id' => 11],
        ];
    }
}
PHP);

file_put_contents($server . DIRECTORY_SEPARATOR . 'request.php', <<<'PHP'
<?php
declare(strict_types=1);

$scenario = $argv[1];
$root = $argv[2];
$GLOBALS['pcv_page_scenario'] = $scenario;
unset($_SERVER['DOCUMENT_ROOT']);
putenv('DOCUMENT_ROOT');
ini_set('session.save_path', $root . '/sessions');
session_id('pagecheck-' . str_replace('_', '-', $scenario));
if (!session_start()) {
    fwrite(STDERR, "Could not start fixture session.\n");
    exit(2);
}
$_SESSION['pcv_csrf'] = str_repeat('a', 64);

define('PCV_LOG_TESTING', true);
require_once __DIR__ . '/log.php';
$logDirectory = $root . '/logs/' . $scenario;
if (!mkdir($logDirectory, 0700) || !pcv_log_set_test_directory($logDirectory)) {
    fwrite(STDERR, "Could not activate fixture logger.\n");
    exit(2);
}
define('PCV_UI_TEST', true);
require_once __DIR__ . '/index.php';

$_SERVER['REQUEST_METHOD'] = str_starts_with($scenario, 'get_') ? 'GET' : 'POST';
$_POST = $_SERVER['REQUEST_METHOD'] === 'GET' ? [] : ($scenario === 'end_catalog_failure' ? [
    'csrf' => str_repeat('a', 64),
    'action' => 'end',
] : ($scenario === 'post_solo' ? [
    'csrf' => str_repeat('a', 64),
    'action' => 'arm',
    'scene_mode' => 'solo',
    'actor_a' => '101',
    'actor_b' => ['untrusted'],
    'exclude_player' => '0',
    'bystander_mode' => 'silent',
] : [
    'csrf' => str_repeat('a', 64),
    'action' => 'arm',
    'actor_a' => '101',
    'actor_b' => '202',
    'exclude_player' => '1',
    'bystander_mode' => 'silent',
]));
$_GET = $scenario === 'get_refresh' ? ['refresh' => '1'] : [];
ob_start();
pcv_run_page();
$body = ob_get_clean();
session_write_close();
$logPath = pcv_log_path();
$lines = is_string($logPath) && is_file($logPath)
    ? file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];
$entries = array_map(static fn(string $line): array => json_decode($line, true, 16, JSON_THROW_ON_ERROR), $lines ?: []);
$result = [
    'status' => http_response_code(),
    'body' => $body,
    'entries' => $entries,
    'eligibility_calls' => $GLOBALS['pcv_eligibility_calls'] ?? [],
    'stage_calls' => $GLOBALS['pcv_page_stage_calls'] ?? 0,
    'stage_known_npcs' => $GLOBALS['pcv_stage_known_npcs'] ?? null,
    'stage_desired' => $GLOBALS['pcv_stage_desired'] ?? null,
    'session_closed' => $GLOBALS['pcv_page_session_closed'] ?? false,
];
echo base64_encode(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
PHP);

$scenarios = [
    'get_read_failure', 'post_readback_changed', 'post_readback_unavailable',
    'get_ready', 'get_refresh', 'get_single', 'get_empty', 'get_missing', 'get_stale', 'post_stale', 'post_single', 'post_solo', 'end_catalog_failure',
];
try {
    $responses = [];
    foreach ($scenarios as $scenario) {
        $responses[$scenario] = pageCheckChild($server . DIRECTORY_SEPARATOR . 'request.php', $scenario, $root);
    }

    $get = $responses['get_read_failure'];
    $getUnavailable = array_values(array_filter($get['entries'], static fn(array $entry): bool => ($entry['event'] ?? null) === 'ui.unavailable'));
    $getPageOpen = array_filter($get['entries'], static fn(array $entry): bool => ($entry['event'] ?? null) === 'ui.page_open');
    preg_match('/Reference ID: ([a-f0-9-]+)\./', (string)$get['body'], $referenceMatch);
    pageCheck(($get['status'] ?? null) === 200 && count($getUnavailable) === 1 && $getPageOpen === []
        && ($getUnavailable[0]['reason'] ?? null) === 'state_unavailable'
        && ($getUnavailable[0]['context']['operation'] ?? null) === 'read'
        && isset($referenceMatch[1]) && ($getUnavailable[0]['request_id'] ?? null) === $referenceMatch[1]
        && str_contains((string)$get['body'], 'Private Conversation state is unavailable.')
        && !str_contains((string)$get['body'], 'private state fixture details'),
        'GET read failure did not correlate its unavailable event and safe response notice.');

    $changed = $responses['post_readback_changed'];
    $accepted = array_values(array_filter($changed['entries'], static fn(array $entry): bool => ($entry['event'] ?? null) === 'ui.scope_stage_accepted'));
    $changedFailures = array_filter($changed['entries'], static fn(array $entry): bool => ($entry['event'] ?? null) === 'ui.scope_stage_failed');
    pageCheck(($changed['status'] ?? null) === 200 && count($accepted) === 1 && $changedFailures === []
        && ($accepted[0]['config_id'] ?? null) === '44444444-4444-4444-8444-444444444444'
        && ($accepted[0]['context']['action'] ?? null) === 'enable'
        && str_contains((string)$changed['body'], 'Current pair: Bryn and Lydia.'),
        'A different valid readback was misreported as stage failure or lost the original staged config ID.');

    $unavailable = $responses['post_readback_unavailable'];
    $readbackFailures = array_values(array_filter($unavailable['entries'], static fn(array $entry): bool => ($entry['event'] ?? null) === 'ui.scope_stage_failed'));
    preg_match('/Reference ID: ([a-f0-9-]+)\./', (string)$unavailable['body'], $failureReference);
    pageCheck(($unavailable['status'] ?? null) === 503 && count($readbackFailures) === 1
        && ($readbackFailures[0]['reason'] ?? null) === 'state_unavailable'
        && ($readbackFailures[0]['context']['operation'] ?? null) === 'readback'
        && ($readbackFailures[0]['config_id'] ?? null) === '44444444-4444-4444-8444-444444444444'
        && isset($failureReference[1]) && ($readbackFailures[0]['request_id'] ?? null) === $failureReference[1]
        && str_contains((string)$unavailable['body'], 'The requested change could not be confirmed.')
        && !str_contains((string)$unavailable['body'], 'private state fixture details'),
        'Unavailable readback did not retain staged correlation or explain that confirmation failed.');

    $ready = $responses['get_ready'];
    pageCheck(($ready['status'] ?? null) === 200 && count($ready['eligibility_calls'] ?? []) === 1
        && ($ready['eligibility_calls'][0]['catalog_count'] ?? null) === 3
        && ($ready['eligibility_calls'][0]['player_name'] ?? null) === 'Player'
        && str_contains((string)$ready['body'], 'value="101"') && str_contains((string)$ready['body'], 'value="202"')
        && !str_contains((string)$ready['body'], 'value="303"'),
        'The picker did not use the current eligible-ID map from the shared helper.');

    $refresh = $responses['get_refresh'];
    $refreshPageOpen = array_filter($refresh['entries'], static fn(array $entry): bool => ($entry['event'] ?? null) === 'ui.page_open');
    $refreshRef = hash('sha256', 'pcv-ui:fixture-playthrough');
    pageCheck(($refresh['status'] ?? null) === 200 && count($refresh['eligibility_calls'] ?? []) === 1
        && ($refresh['stage_calls'] ?? null) === 0 && $refreshPageOpen === [] && ($refresh['session_closed'] ?? false) === true
        && str_contains((string)$refresh['body'], 'data-playthrough-ref="' . $refreshRef . '"')
        && !str_contains((string)$refresh['body'], 'fixture-playthrough')
        && str_contains((string)$refresh['body'], 'assets/ui-refresh.js')
        && str_contains((string)$refresh['body'], 'name="csrf" value="' . str_repeat('a', 64) . '"'),
        'Automatic GET refresh must reread shared eligibility without staging, opening-page logging, or exposing the playthrough key.');

    $single = $responses['get_single'];
    pageCheck(str_contains((string)$single['body'], 'Pair mode requires two distinct nearby CHIM-AI-active characters')
        && str_contains((string)$single['body'], 'Solo reflection needs one nearby CHIM-AI-active character')
        && str_contains((string)$single['body'], 'type="submit" data-roster-ready="1" disabled>'),
        'A single eligible character did not disable ARM with a specific explanation.');

    $empty = $responses['get_empty'];
    $missing = $responses['get_missing'];
    $staleGet = $responses['get_stale'];
    pageCheck(str_contains((string)$empty['body'], 'No eligible NPCs matched the latest report')
        && str_contains((string)$empty['body'], 'This list polls automatically for updates')
        && str_contains((string)$empty['body'], 'reported empty result'),
        'A valid empty report was not distinguished from unknown presence.');
    pageCheck(str_contains((string)$missing['body'], 'No qualifying CHIM presence report is available yet')
        && str_contains((string)$missing['body'], 'unknown, not empty')
        && !str_contains((string)$missing['body'], 'No eligible NPCs matched the latest report'),
        'A missing report was conflated with valid empty presence.');
    pageCheck(str_contains((string)$staleGet['body'], 'Fresh nearby and AI activity could not be confirmed; current eligibility is unknown')
        && str_contains((string)$staleGet['body'], 'This list polls automatically for updates')
        && !str_contains((string)$staleGet['body'], 'No qualifying CHIM presence report is cached'),
        'Unconfirmed fresh nearby and AI activity did not get a generic unknown notice.');

    foreach (['post_stale', 'post_single'] as $scenario) {
        $rejected = $responses[$scenario];
        $rejections = array_values(array_filter($rejected['entries'], static fn(array $entry): bool => ($entry['event'] ?? null) === 'ui.scope_stage_rejected'));
        $expectedReason = $scenario === 'post_stale' ? 'presence_stale' : 'actor_unavailable';
        pageCheck(($rejected['status'] ?? null) === 400 && ($rejected['stage_calls'] ?? null) === 0
            && count($rejections) === 1 && ($rejections[0]['reason'] ?? null) === $expectedReason
            && str_contains((string)$rejected['body'], 'Reference ID:'),
            'POST staged a pair that was stale or had fewer than two eligible actors.');
    }
    pageCheck(str_contains((string)$responses['post_stale']['body'], 'Fresh nearby and AI activity could not be confirmed; current eligibility is unknown')
        && str_contains((string)$responses['post_single']['body'], 'Pair mode requires two distinct nearby CHIM-AI-active characters'),
        'POST rejection did not distinguish stale from insufficient eligibility.');

    $solo = $responses['post_solo'];
    $soloAccepted = array_values(array_filter($solo['entries'], static fn(array $entry): bool =>
        ($entry['event'] ?? null) === 'ui.scope_stage_accepted'));
    pageCheck(($solo['status'] ?? null) === 200 && ($solo['stage_calls'] ?? null) === 1
        && ($solo['stage_known_npcs'] ?? null) === ['101' => 'Aela']
        && ($solo['stage_desired'] ?? null) === [
            'enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '101', 'actor_b' => null,
            'exclude_player' => true, 'bystander_mode' => 'silent',
        ] && count($soloAccepted) === 1 && ($soloAccepted[0]['context']['action'] ?? null) === 'enable',
        'Solo POST must stage with one current eligible actor, ignore malformed actor B, and force player exclusion.');

    $endWithoutCatalog = $responses['end_catalog_failure'];
    $endAccepted = array_values(array_filter($endWithoutCatalog['entries'], static fn(array $entry): bool =>
        ($entry['event'] ?? null) === 'ui.scope_stage_accepted'));
    pageCheck(($endWithoutCatalog['status'] ?? null) === 200 && ($endWithoutCatalog['stage_calls'] ?? null) === 1
        && ($endWithoutCatalog['stage_known_npcs'] ?? null) === []
        && ($endWithoutCatalog['eligibility_calls'] ?? null) === []
        && count($endAccepted) === 1 && ($endAccepted[0]['context']['action'] ?? null) === 'end'
        && str_contains((string)$endWithoutCatalog['body'], 'The end request was staged.')
        && !str_contains((string)$endWithoutCatalog['body'], 'private catalog fixture details'),
        'END depended on presence/catalog or exposed a catalog exception.');

    echo "PASS: actual page GET read failure returns a matching reference and suppresses page-open.\n";
    echo "PASS: valid concurrent readback stays accepted with its staged config ID and renders the current state.\n";
    echo "PASS: unavailable readback reports operation=readback, retains staged ID, and says it could not be confirmed.\n";
    echo "PASS: refresh GET rereads eligibility without staging, logging page-open, or holding the session lock.\n";
    echo "PASS: picker uses the helper map, disables ARM below two, and distinguishes empty/missing/stale.\n";
    echo "PASS: stale/single-actor POSTs reject without staging; END succeeds when catalog loading fails.\n";
    echo "PASS: solo POST stages with one eligible actor, ignores submitted actor B, and forces player exclusion.\n";
} finally {
    foreach ($scenarios as $scenario) {
        foreach (['events.jsonl', 'events.lock'] as $file) {
            @unlink($logs . DIRECTORY_SEPARATOR . $scenario . DIRECTORY_SEPARATOR . $file);
        }
        @rmdir($logs . DIRECTORY_SEPARATOR . $scenario);
        @unlink($sessions . DIRECTORY_SEPARATOR . pageCheckSessionId($scenario));
    }
    foreach ([
        $server . DIRECTORY_SEPARATOR . 'index.php',
        $server . DIRECTORY_SEPARATOR . 'log.php',
        $server . DIRECTORY_SEPARATOR . 'scope.php',
        $server . DIRECTORY_SEPARATOR . 'state.php',
        $server . DIRECTORY_SEPARATOR . 'request.php',
        $engine . '/lib/runtime_bootstrap.php',
        $engine . '/lib/fixture_db.class.php',
        $engine . '/lib/core/npc_master.class.php',
        $engine . '/conf/conf.sample.php',
        $engine . '/conf/conf.php',
    ] as $file) {
        @unlink($file);
    }
    @rmdir($engine . '/lib/core');
    @rmdir($engine . '/lib');
    @rmdir($engine . '/conf');
    @rmdir($server);
    @rmdir($root . DIRECTORY_SEPARATOR . 'plugin');
    @rmdir($logs);
    @rmdir($sessions);
    @rmdir($root);
}
