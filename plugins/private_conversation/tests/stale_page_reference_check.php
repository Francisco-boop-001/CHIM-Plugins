<?php
declare(strict_types=1);

function stalePageCheck(bool $condition, string $failure): void
{
    if (!$condition) {
        $GLOBALS['stale_page_failures'][] = $failure;
    }
}

function stalePageRequest(string $method, array $post = []): string
{
    $_GET = [];
    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = $method;
    header_remove();
    http_response_code(200);
    ob_start();
    pcv_run_page();
    return (string)ob_get_clean();
}

$repo = dirname(__DIR__);
$scratch = $repo . DIRECTORY_SEPARATOR . 'tasks' . DIRECTORY_SEPARATOR . 'bugfix-2026-10-28'
    . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'scratch';
if (!is_dir($scratch) && !mkdir($scratch, 0700, true) && !is_dir($scratch)) {
    fwrite(STDERR, "Could not create the workspace-owned UI fixture scratch directory.\n");
    exit(2);
}
$root = $scratch . DIRECTORY_SEPARATOR . 'stale-page-reference-' . bin2hex(random_bytes(6));
$server = $root . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'HerikaServer'
    . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'private_conversation';
$engine = $root . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'HerikaServer';
$sessions = $root . DIRECTORY_SEPARATOR . 'sessions';
foreach ([$server, $sessions, $engine . DIRECTORY_SEPARATOR . 'lib', $engine . DIRECTORY_SEPARATOR . 'conf'] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
        fwrite(STDERR, "Could not create the isolated UI fixture.\n");
        exit(2);
    }
}

foreach (['index.php', 'scope.php', 'installation_guard.php', 'manifest.json'] as $file) {
    if (!copy($repo . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . $file,
        $server . DIRECTORY_SEPARATOR . $file)) {
        fwrite(STDERR, "Could not copy the actual UI controller file: {$file}\n");
        exit(2);
    }
}

$logger = <<<'PHP'
<?php
declare(strict_types=1);
function pcv_log_begin_request(...$args): void { $GLOBALS['stale_page_events'] = []; }
function pcv_log_event(string $event, string $severity, string $outcome, ?string $reason = null, array $context = []): void
{
    $GLOBALS['stale_page_events'][] = compact('event', 'severity', 'outcome', 'reason', 'context');
}
function pcv_log_exception(string $event, string $severity, string $outcome, string $reason, Throwable $error, array $context = []): void
{
    pcv_log_event($event, $severity, $outcome, $reason, $context);
}
function pcv_log_set_config_id(...$args): void {}
function pcv_log_request_id(): string { return 'stale-page-reference-fixture'; }
PHP;
if (file_put_contents($server . DIRECTORY_SEPARATOR . 'log.php', $logger) !== strlen($logger)) {
    fwrite(STDERR, "Could not create the fixture-only logger adapter.\n");
    exit(2);
}

$state = <<<'PHP'
<?php
declare(strict_types=1);
function pcv_current_playthrough_key(bool $refresh = false): ?string { return $GLOBALS['stale_page_key'] ?? null; }
function pcv_current_player_name(): ?string { return 'Player'; }
function pcv_read_npc_catalog(): array
{
    return [
        ['id' => 101, 'npc_name' => 'Aela', 'profile_id' => 9],
        ['id' => 202, 'npc_name' => 'Bryn', 'profile_id' => 10],
    ];
}
function pcv_read_eligible_npcs(?string $key, array $catalogRows, ?string $playerName, ?string $stateDirectory = null): array
{
    $available = ($GLOBALS['stale_page_roster_available'] ?? true) === true;
    return [
        'status' => $available ? 'ready' : 'empty',
        'known_npcs' => $available ? ['101' => 'Aela', '202' => 'Bryn'] : [],
        'observed_at' => time(),
        'reason' => null,
    ];
}
function pcv_read(string $key): array
{
    if (is_array($GLOBALS['stale_page_stage'] ?? null)) {
        return [
            'status' => 'pending', 'scope' => null, 'pending' => true,
            'pending_scope' => $GLOBALS['stale_page_stage']['desired'],
            'config_id' => null, 'pending_config_id' => '44444444-4444-4444-8444-444444444444',
        ];
    }
    return ['status' => 'off', 'scope' => null, 'pending' => false, 'pending_scope' => null,
        'config_id' => null, 'pending_config_id' => null];
}
function pcv_stage(string $key, array $desired, array $knownNpcs): array
{
    $GLOBALS['stale_page_stage_calls'] = ($GLOBALS['stale_page_stage_calls'] ?? 0) + 1;
    $GLOBALS['stale_page_stage'] = compact('key', 'desired', 'knownNpcs');
    return [
        'status' => 'pending', 'scope' => null, 'pending' => true, 'pending_scope' => $desired,
        'config_id' => null, 'pending_config_id' => '44444444-4444-4444-8444-444444444444',
    ];
}
PHP;
if (file_put_contents($server . DIRECTORY_SEPARATOR . 'state.php', $state) !== strlen($state)) {
    fwrite(STDERR, "Could not create fixture state adapters.\n");
    exit(2);
}

$runtime = <<<'PHP'
<?php
function chimRuntimeImportConfigVariables(array $variables): void
{
    foreach ($variables as $name => $value) {
        if (is_string($name) && preg_match('/\A[A-Z0-9_]+\z/', $name) === 1) {
            $GLOBALS[$name] = $value;
        }
    }
}
PHP;
file_put_contents($engine . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php', $runtime);
file_put_contents($engine . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'conf.sample.php', "<?php \$DBDRIVER = 'fixture';\n");
file_put_contents($engine . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'conf.php', "<?php // isolated fixture config\n");

ini_set('session.save_path', $sessions);
session_id('pcv-stale-page-reference');
if (!session_start()) {
    fwrite(STDERR, "Could not start the isolated UI fixture session.\n");
    exit(2);
}
$_SESSION['pcv_csrf'] = str_repeat('a', 64);
session_write_close();

$GLOBALS['stale_page_key'] = hash('sha256', 'managed-profile-A');
$GLOBALS['stale_page_stage_calls'] = 0;
$GLOBALS['stale_page_stage'] = null;
$GLOBALS['stale_page_roster_available'] = true;
$GLOBALS['stale_page_failures'] = [];
define('PCV_UI_TEST', true);
require_once $server . DIRECTORY_SEPARATOR . 'index.php';

$pageA = stalePageRequest('GET');
preg_match('/data-playthrough-ref="([a-f0-9]{64})"/', $pageA, $pageRefMatch);
preg_match('/name="csrf" value="([a-f0-9]{64})"/', $pageA, $csrfMatch);
$refA = $pageRefMatch[1] ?? '';
$csrf = $csrfMatch[1] ?? '';
stalePageCheck($refA === hash('sha256', 'pcv-ui:' . $GLOBALS['stale_page_key'])
    && substr_count($pageA, 'name="playthrough_ref" value="' . $refA . '"') === 2,
    'GET must render the opaque current reference into both ARM and END forms.');
stalePageCheck($csrf === str_repeat('a', 64), 'GET must retain the existing CSRF token in the rendered form.');

$keyB = hash('sha256', 'managed-profile-B');
$GLOBALS['stale_page_key'] = $keyB;
$oldArm = [
    'csrf' => $csrf, 'playthrough_ref' => $refA, 'action' => 'arm', 'scene_mode' => 'pair',
    'actor_a' => '101', 'actor_b' => '202', 'opener' => 'auto', 'exclude_player' => '1',
    'bystander_mode' => 'silent', 'card' => '', 'pace' => 'normal',
];
$staleArmResponse = stalePageRequest('POST', $oldArm);
stalePageCheck(http_response_code() === 409 && ($GLOBALS['stale_page_stage_calls'] ?? 0) === 0,
    'ARM from profile A must be refused before staging after the server switches to profile B.');
stalePageCheck(str_contains($staleArmResponse, 'Refresh the page before making this change.'),
    'A stale ARM must show the fixed refresh notice.');
$rejections = array_values(array_filter($GLOBALS['stale_page_events'] ?? [], static fn(array $event): bool =>
    ($event['event'] ?? null) === 'ui.scope_stage_rejected'));
stalePageCheck(count($rejections) === 1 && ($rejections[0]['reason'] ?? null) === 'playthrough_changed',
    'A stale ARM must use the fixed playthrough_changed rejection reason.');

$GLOBALS['stale_page_stage_calls'] = 0;
stalePageRequest('POST', ['csrf' => $csrf, 'action' => 'end', 'playthrough_ref' => $refA]);
stalePageCheck(http_response_code() === 409 && ($GLOBALS['stale_page_stage_calls'] ?? 0) === 0,
    'END from profile A must be refused before staging after the server switches to profile B.');

foreach ([
    ['csrf' => $csrf, 'action' => 'end'],
    ['csrf' => $csrf, 'playthrough_ref' => ['malformed'], 'action' => 'end'],
] as $invalidRefPost) {
    $GLOBALS['stale_page_stage_calls'] = 0;
    $invalidRefResponse = stalePageRequest('POST', $invalidRefPost);
    stalePageCheck(http_response_code() === 409 && ($GLOBALS['stale_page_stage_calls'] ?? 0) === 0
        && str_contains($invalidRefResponse, 'Refresh the page before making this change.'),
        'END must reject a missing or malformed reference before staging.');
}

$GLOBALS['stale_page_stage_calls'] = 0;
stalePageRequest('POST', ['csrf' => str_repeat('b', 64), 'playthrough_ref' => $refA, 'action' => 'end']);
$csrfRejections = array_values(array_filter($GLOBALS['stale_page_events'] ?? [], static fn(array $event): bool =>
    ($event['event'] ?? null) === 'ui.scope_stage_rejected'));
stalePageCheck(http_response_code() === 400 && ($GLOBALS['stale_page_stage_calls'] ?? 0) === 0
    && count($csrfRejections) === 1 && ($csrfRejections[0]['reason'] ?? null) === 'csrf_failed',
    'Invalid CSRF must keep its existing 400 response and precede a stale-reference rejection.');

$GLOBALS['stale_page_key'] = null;
$GLOBALS['stale_page_stage_calls'] = 0;
stalePageRequest('POST', ['csrf' => $csrf, 'action' => 'end']);
stalePageCheck(http_response_code() === 503 && ($GLOBALS['stale_page_stage_calls'] ?? 0) === 0,
    'Unavailable current identity must retain its existing 503 refusal, not become a 409 stale-page result.');

$GLOBALS['stale_page_key'] = $keyB;
$pageB = stalePageRequest('GET');
preg_match('/data-playthrough-ref="([a-f0-9]{64})"/', $pageB, $pageBRefMatch);
$refB = $pageBRefMatch[1] ?? '';
$GLOBALS['stale_page_stage_calls'] = 0;
stalePageRequest('POST', array_replace($oldArm, ['playthrough_ref' => $refB]));
$stage = $GLOBALS['stale_page_stage'] ?? null;
stalePageCheck(http_response_code() === 200 && ($GLOBALS['stale_page_stage_calls'] ?? 0) === 1
    && is_array($stage) && ($stage['key'] ?? null) === $keyB,
    'A valid same-profile ARM must continue to stage against the server-derived current key.');

$GLOBALS['stale_page_roster_available'] = false;
$GLOBALS['stale_page_stage_calls'] = 0;
stalePageRequest('POST', ['csrf' => $csrf, 'playthrough_ref' => $refB, 'action' => 'end']);
$stage = $GLOBALS['stale_page_stage'] ?? null;
stalePageCheck(http_response_code() === 200 && ($GLOBALS['stale_page_stage_calls'] ?? 0) === 1
    && is_array($stage) && ($stage['key'] ?? null) === $keyB && ($stage['desired']['enabled'] ?? true) === false
    && ($stage['knownNpcs'] ?? null) === [],
    'A valid same-profile END must remain available without an eligible roster.');

if ($GLOBALS['stale_page_failures'] !== []) {
    fwrite(STDERR, "FAIL: " . implode("\nFAIL: ", $GLOBALS['stale_page_failures']) . "\n");
    exit(1);
}
echo "PASS: rendered ARM/END references, stale/missing/malformed rejection, CSRF and identity precedence, and valid controls.\n";
