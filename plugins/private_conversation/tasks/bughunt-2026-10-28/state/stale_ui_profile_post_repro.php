<?php
declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: stale_ui_profile_post_repro.php <pcv-repo-root>\n");
    exit(2);
}
$repo = rtrim($argv[1], DIRECTORY_SEPARATOR);
$root = __DIR__ . DIRECTORY_SEPARATOR . 'stale-ui-profile-fixture-20261028';
$server = $root . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'HerikaServer'
    . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'private_conversation';
$engine = $root . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'HerikaServer';
$sessions = $root . DIRECTORY_SEPARATOR . 'sessions';
foreach ([$server, $sessions, $engine . DIRECTORY_SEPARATOR . 'lib', $engine . DIRECTORY_SEPARATOR . 'conf'] as $directory) {
    if (file_exists($directory) || is_link($directory)) {
        fwrite(STDERR, "Refusing to reuse an existing fixture path.\n");
        exit(2);
    }
    if (!mkdir($directory, 0700, true)) {
        fwrite(STDERR, "Could not create an isolated fixture directory.\n");
        exit(2);
    }
}
foreach (['index.php', 'scope.php', 'installation_guard.php', 'manifest.json'] as $file) {
    if (!copy($repo . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . $file,
        $server . DIRECTORY_SEPARATOR . $file)) {
        fwrite(STDERR, "Could not copy actual controller file: {$file}\n");
        exit(2);
    }
}

$logger = <<<'PHP'
<?php
declare(strict_types=1);
function pcv_log_begin_request(...$args): void {}
function pcv_log_event(...$args): void {}
function pcv_log_exception(...$args): void {}
function pcv_log_set_config_id(...$args): void {}
function pcv_log_set_playthrough_ref(...$args): void {}
function pcv_log_set_terminal(...$args): void {}
function pcv_log_set_request_type(...$args): void {}
function pcv_log_enum_values(...$args): array { return []; }
function pcv_log_request_id(): string { return 'fixture-request-id'; }
PHP;
if (file_put_contents($server . DIRECTORY_SEPARATOR . 'log.php', $logger) !== strlen($logger)) {
    fwrite(STDERR, "Could not write the fixture-only logger stub.\n");
    exit(2);
}

$state = <<<'PHP'
<?php
declare(strict_types=1);
function pcv_current_playthrough_key(bool $refresh = false): ?string
{
    return $GLOBALS['pcv_fixture_key'] ?? null;
}
function pcv_current_player_name(): ?string { return 'Player'; }
function pcv_read_npc_catalog(): array
{
    return [
        ['id' => 101, 'npc_name' => 'Aela', 'profile_id' => 9],
        ['id' => 202, 'npc_name' => 'Bryn', 'profile_id' => 10],
        ['id' => 303, 'npc_name' => 'Lydia', 'profile_id' => 11],
    ];
}
function pcv_read_eligible_npcs(?string $key, array $catalogRows, ?string $playerName, ?string $stateDirectory = null): array
{
    $known = [];
    foreach ($catalogRows as $row) {
        $known[(string)$row['id']] = (string)$row['npc_name'];
    }
    $GLOBALS['pcv_fixture_eligibility_keys'][] = $key;
    return ['status' => 'ready', 'known_npcs' => $known, 'observed_at' => time(), 'reason' => null];
}
function pcv_read(string $key): array
{
    $GLOBALS['pcv_fixture_read_keys'][] = $key;
    if (is_array($GLOBALS['pcv_fixture_stage'] ?? null)) {
        $stage = $GLOBALS['pcv_fixture_stage'];
        return [
            'status' => 'pending', 'scope' => null, 'pending' => true,
            'pending_scope' => $stage['desired'], 'config_id' => null,
            'pending_config_id' => '44444444-4444-4444-8444-444444444444',
        ];
    }
    return ['status' => 'off', 'scope' => null, 'pending' => false, 'pending_scope' => null,
        'config_id' => null, 'pending_config_id' => null];
}
function pcv_stage(string $key, array $desired, array $knownNpcs): array
{
    $GLOBALS['pcv_fixture_stage'] = ['key' => $key, 'desired' => $desired, 'known_npcs' => $knownNpcs];
    return [
        'status' => 'pending', 'scope' => null, 'pending' => true, 'pending_scope' => $desired,
        'config_id' => null, 'pending_config_id' => '44444444-4444-4444-8444-444444444444',
    ];
}
PHP;
if (file_put_contents($server . DIRECTORY_SEPARATOR . 'state.php', $state) !== strlen($state)) {
    fwrite(STDERR, "Could not write fixture state adapters.\n");
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
file_put_contents($engine . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'conf.php', "<?php // local fixture config\n");

ini_set('session.save_path', $sessions);
session_id('pcvstaleform20261028');
if (!session_start()) {
    fwrite(STDERR, "Could not start the isolated fixture session.\n");
    exit(2);
}
$_SESSION['pcv_csrf'] = str_repeat('a', 64);
session_write_close();

$GLOBALS['pcv_fixture_key'] = hash('sha256', 'managed-profile-A');
$GLOBALS['pcv_fixture_read_keys'] = [];
$GLOBALS['pcv_fixture_eligibility_keys'] = [];
$GLOBALS['pcv_fixture_stage'] = null;
define('PCV_UI_TEST', true);
require_once $server . DIRECTORY_SEPARATOR . 'index.php';

$_GET = [];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
pcv_run_page();
$pageA = (string)ob_get_clean();
if (preg_match('/data-playthrough-ref="([a-f0-9]{64})"/', $pageA, $refMatch) !== 1
    || preg_match('/name="csrf" value="([a-f0-9]{64})"/', $pageA, $csrfMatch) !== 1) {
    fwrite(STDERR, "The actual GET page did not render its identity reference and session token.\n");
    exit(2);
}
$oldPageRef = $refMatch[1];
$oldCsrf = $csrfMatch[1];
if ($oldPageRef !== hash('sha256', 'pcv-ui:' . hash('sha256', 'managed-profile-A'))
    || !str_contains($pageA, 'option value="101"') || !str_contains($pageA, 'option value="202"')) {
    fwrite(STDERR, "The A page did not contain the expected rendered roster.\n");
    exit(2);
}
$armForm = '';
if (preg_match('/<form method="post" class="scope-form">(.*?)<\/form>/s', $pageA, $formMatch) === 1) {
    $armForm = $formMatch[1];
}
$hasSubmittedIdentity = str_contains($armForm, 'playthrough-ref') || str_contains($armForm, 'playthrough_ref');

// Model a managed-profile switch before this tab's asynchronous poll observes it.
$keyB = hash('sha256', 'managed-profile-B');
$GLOBALS['pcv_fixture_key'] = $keyB;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf' => $oldCsrf,
    'action' => 'arm',
    'scene_mode' => 'pair',
    'actor_a' => '101',
    'actor_b' => '202',
    'actor_c' => '',
    'actor_d' => '',
    'opener' => 'auto',
    'exclude_player' => '1',
    'bystander_mode' => 'exclude',
    'card' => '',
    'pace' => 'normal',
];
ob_start();
pcv_run_page();
$postB = (string)ob_get_clean();
$stage = $GLOBALS['pcv_fixture_stage'];
$summary = [
    'page_a_ref_matches_a' => $oldPageRef === hash('sha256', 'pcv-ui:' . hash('sha256', 'managed-profile-A')),
    'arm_form_has_submitted_playthrough_identity' => $hasSubmittedIdentity,
    'csrf_carried_from_a_page' => $oldCsrf === str_repeat('a', 64),
    'profile_b_key' => $keyB,
    'stage_calls' => $stage === null ? 0 : 1,
    'stage_target_key' => is_array($stage) ? ($stage['key'] ?? null) : null,
    'stage_actor_ids' => is_array($stage) ? ($stage['desired']['actor_ids'] ?? null) : null,
    'post_response_code' => http_response_code(),
    'post_page_ref_matches_b' => str_contains($postB, 'data-playthrough-ref="' . hash('sha256', 'pcv-ui:' . $keyB) . '"'),
];
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if ($summary['arm_form_has_submitted_playthrough_identity'] !== false
    || $summary['stage_calls'] !== 1 || $summary['stage_target_key'] !== $keyB
    || $summary['stage_actor_ids'] !== ['101', '202'] || $summary['post_response_code'] !== 200
    || $summary['post_page_ref_matches_b'] !== true) {
    fwrite(STDERR, "The stale page did not reproduce an accepted POST against the current profile.\n");
    exit(1);
}