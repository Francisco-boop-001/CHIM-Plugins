<?php
declare(strict_types=1);

if ($argc !== 2) {
    fwrite(STDERR, "Usage: stale_ui_end_post_repro.php <pcv-repo-root>\n");
    exit(2);
}
$repo = rtrim($argv[1], DIRECTORY_SEPARATOR);
$root = __DIR__ . DIRECTORY_SEPARATOR . 'stale-ui-end-fixture-20261028';
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
file_put_contents($server . DIRECTORY_SEPARATOR . 'log.php', $logger);

$state = <<<'PHP'
<?php
declare(strict_types=1);
function pcv_current_playthrough_key(bool $refresh = false): ?string { return $GLOBALS['pcv_fixture_key'] ?? null; }
function pcv_current_player_name(): ?string { return 'Player'; }
function pcv_read_npc_catalog(): array
{
    return [['id' => 101, 'npc_name' => 'Aela', 'profile_id' => 9], ['id' => 202, 'npc_name' => 'Bryn', 'profile_id' => 10]];
}
function pcv_read_eligible_npcs(?string $key, array $catalogRows, ?string $playerName, ?string $stateDirectory = null): array
{
    $GLOBALS['pcv_fixture_eligibility_keys'][] = $key;
    return ['status' => 'empty', 'known_npcs' => [], 'observed_at' => time(), 'reason' => null];
}
function pcv_read(string $key): array
{
    $state = $key === ($GLOBALS['pcv_fixture_profile_b'] ?? null)
        && !is_array($GLOBALS['pcv_fixture_stage'] ?? null)
        ? ['status' => 'active', 'scope' => ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '101',
            'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude'], 'pending' => false,
            'pending_scope' => null, 'config_id' => '33333333-3333-4333-8333-333333333333', 'pending_config_id' => null]
        : ['status' => 'off', 'scope' => null, 'pending' => false, 'pending_scope' => null, 'config_id' => null,
            'pending_config_id' => null];
    $GLOBALS['pcv_fixture_read_states'][] = ['key' => $key, 'status' => $state['status']];
    return $state;
}
function pcv_stage(string $key, array $desired, array $knownNpcs): array
{
    $GLOBALS['pcv_fixture_stage'] = ['key' => $key, 'desired' => $desired, 'known_npcs' => $knownNpcs];
    return ['status' => 'off', 'scope' => null, 'pending' => false, 'pending_scope' => null,
        'config_id' => null, 'pending_config_id' => null];
}
PHP;
file_put_contents($server . DIRECTORY_SEPARATOR . 'state.php', $state);

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
session_id('pcvendstale20261028');
if (!session_start()) {
    fwrite(STDERR, "Could not start the isolated fixture session.\n");
    exit(2);
}
$_SESSION['pcv_csrf'] = str_repeat('b', 64);
session_write_close();

$keyA = hash('sha256', 'managed-profile-A');
$keyB = hash('sha256', 'managed-profile-B');
$GLOBALS['pcv_fixture_profile_b'] = $keyB;
$GLOBALS['pcv_fixture_key'] = $keyA;
$GLOBALS['pcv_fixture_read_states'] = [];
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
    || preg_match('/name="csrf" value="([a-f0-9]{64})"/', $pageA, $csrfMatch) !== 1
    || !str_contains($pageA, 'End now') || !str_contains($pageA, 'Off')) {
    fwrite(STDERR, "The A page did not render Off, End now, its identity reference and session token.\n");
    exit(2);
}
$armForm = '';
if (preg_match('/<form method="post" class="end-form">(.*?)<\/form>/s', $pageA, $formMatch) === 1) {
    $armForm = $formMatch[1];
}
$oldPageRef = $refMatch[1];
$oldCsrf = $csrfMatch[1];
$hasSubmittedIdentity = str_contains($armForm, 'playthrough-ref') || str_contains($armForm, 'playthrough_ref');

// Change the server's active key after rendering A; B has a live scene but no eligible NPC roster.
$GLOBALS['pcv_fixture_key'] = $keyB;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['csrf' => $oldCsrf, 'action' => 'end'];
ob_start();
pcv_run_page();
$postB = (string)ob_get_clean();
$stage = $GLOBALS['pcv_fixture_stage'];
$summary = [
    'page_a_ref_matches_a' => $oldPageRef === hash('sha256', 'pcv-ui:' . $keyA),
    'a_page_showed_off_and_end' => str_contains($pageA, 'Off') && str_contains($pageA, 'End now'),
    'end_form_has_submitted_playthrough_identity' => $hasSubmittedIdentity,
    'csrf_carried_from_a_page' => $oldCsrf === str_repeat('b', 64),
    'profile_b_pre_stage_state' => array_values(array_filter($GLOBALS['pcv_fixture_read_states'], static fn(array $r): bool => $r['key'] === $keyB))[0]['status'] ?? null,
    'profile_b_eligible_roster' => [],
    'stage_calls' => $stage === null ? 0 : 1,
    'stage_target_key' => is_array($stage) ? ($stage['key'] ?? null) : null,
    'stage_desired' => is_array($stage) ? ($stage['desired'] ?? null) : null,
    'post_response_code' => http_response_code(),
    'post_page_ref_matches_b' => str_contains($postB, 'data-playthrough-ref="' . hash('sha256', 'pcv-ui:' . $keyB) . '"'),
];
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if ($summary['end_form_has_submitted_playthrough_identity'] !== false
    || $summary['profile_b_pre_stage_state'] !== 'active'
    || $summary['stage_calls'] !== 1 || $summary['stage_target_key'] !== $keyB
    || $summary['stage_desired'] !== ['enabled' => false]
    || $summary['post_response_code'] !== 200 || $summary['post_page_ref_matches_b'] !== true) {
    fwrite(STDERR, "The stale A END form did not reproduce an accepted stage against B.\n");
    exit(1);
}