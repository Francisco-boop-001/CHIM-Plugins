<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
require_once __DIR__ . '/../server/log.php';
define('PCV_UI_TEST', true);
require_once __DIR__ . '/../server/index.php';
require_once __DIR__ . '/../server/log_reader.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejects(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

check(pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '127.0.0.1']), 'Exact IPv4 loopback must authorize diagnostics.');
check(pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '::1']), 'Exact IPv6 loopback must authorize diagnostics.');
check(pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '10.0.0.2', 'REMOTE_USER' => 'operator']),
    'A nonempty web-server REMOTE_USER must authorize diagnostics.');
check(pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '10.0.0.2', 'REMOTE_USER' => 'operator', 'HTTP_X_FORWARDED_FOR' => '127.0.0.1']),
    'A trusted server REMOTE_USER must authorize independently of caller forwarding headers.');
check(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '10.0.0.2', 'HTTP_REMOTE_USER' => 'operator', 'PHP_AUTH_USER' => 'operator']),
    'Caller-controlled user headers must not authorize diagnostics.');
foreach (['HTTP_FORWARDED', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $forwardedHeader) {
    check(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '127.0.0.1', $forwardedHeader => '']),
        'Loopback with any forwarding header must not authorize diagnostics.');
}
check(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '127.0.0.2']), 'Non-loopback private addresses must not authorize diagnostics.');
check(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '10.0.0.2', 'REMOTE_USER' => '   ']), 'Blank server REMOTE_USER must not authorize diagnostics.');

$normalizedFilters = pcv_ui_logs_filters([
    'action' => 'read', 'csrf' => 'token', 'request' => str_repeat('a', 32), 'event' => 'ui.page_open',
    'severity' => 'info', 'limit' => '25',
]);
check($normalizedFilters['request'] === str_repeat('a', 32) && $normalizedFilters['event'] === 'ui.page_open'
    && $normalizedFilters['severity'] === 'info' && $normalizedFilters['limit'] === 25,
    'Diagnostics form values must normalize to the reader’s strict bounded filter contract.');
rejects(static fn() => pcv_ui_logs_filters(['action' => 'read', 'csrf' => 'token', 'limit' => '1001']),
    'The diagnostics UI must reject limits above 1000.');
rejects(static fn() => pcv_ui_logs_filters(['action' => 'read', 'csrf' => 'token', 'message' => 'dialogue']),
    'The diagnostics UI must reject unrecognized posted fields.');

$safeLogEntry = pcv_diagnostics_project_entry([
    'schema_version' => 1, 'logging_revision' => 2, 'plugin_version' => '0.1.4',
    'timestamp' => '2026-09-30T12:00:00.123Z', 'event' => 'ui.page_open', 'severity' => 'info', 'outcome' => 'ok',
    'reason' => null, 'request_id' => str_repeat('a', 32), 'config_id' => null, 'playthrough_ref' => null,
    'elapsed_ms' => 1, 'context' => [], 'raw_dialogue' => 'SECRET-DIALOGUE', 'absolute_path' => 'C:/private/events.jsonl',
]);
$logsHtml = pcv_render_logs_page('valid-token', pcv_ui_logs_filters([]), [
    'status' => 'ok', 'entries' => [$safeLogEntry],
    'health' => [
        'read_status' => 'ok', 'storage' => ['storage_mode' => 'external', 'write_status' => 'written',
            'failure_codes' => [], 'retention' => 'bounded_rotation', 'max_files' => 5,
            'max_file_bytes' => 10485760, 'max_entry_bytes' => 8192, 'completeness' => 'bounded_history'],
        'completeness' => 'bounded_history', 'captured_segments' => 1,
        'omissions' => ['malformed' => 0, 'oversized' => 0, 'unknown_schema' => 0, 'unsupported_revision' => 0,
            'filtered' => 0, 'capped' => 0, 'read_failed' => 0],
    ],
], '<script>alert(1)</script>');
check(str_contains($logsHtml, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($logsHtml, '<script>alert(1)</script>'),
    'Diagnostics HTML must escape untrusted presentation text.');
check(!str_contains($logsHtml, 'SECRET-DIALOGUE') && !str_contains($logsHtml, 'C:/private/events.jsonl'),
    'Diagnostics rendering must not expose raw record fields or filesystem paths.');
check(str_contains($logsHtml, 'UTC') && str_contains($logsHtml, 'Current request only')
    && str_contains($logsHtml, 'filtered') && str_contains($logsHtml, 'capped'),
    'Diagnostics must label UTC time, current-request write health, and normal filter/cap counts.');

$known = pcv_build_known_npcs([
    ['id' => 101, 'profile_id' => 7, 'npc_name' => '<script>alert(1)</script>'],
    ['id' => 102, 'profile_id' => 7, 'npc_name' => 'Aela'],
    ['id' => 103, 'profile_id' => 7, 'npc_name' => 'aela'],
    ['id' => 104, 'profile_id' => 7, 'npc_name' => 'Lydia'],
    ['id' => 105, 'profile_id' => 7, 'npc_name' => 'The Narrator'],
    ['id' => 106, 'profile_id' => 7, 'npc_name' => 'bad|name'],
    ['id' => 107, 'profile_id' => 0, 'npc_name' => 'No active profile'],
    ['id' => 108, 'npc_name' => 'Missing profile'],
    ['id' => 0, 'profile_id' => 7, 'npc_name' => 'Invalid'],
    ['id' => 'not-an-id', 'profile_id' => 7, 'npc_name' => 'Invalid ID'],
    ['id' => 109, 'profile_id' => 7, 'npc_name' => ''],
], 'Lydia');
check(array_map('strval', array_keys($known)) === ['101'], 'Player, narrator, unsafe, invalid, and ambiguous identities must not be selectable.');

$arm = pcv_form_desired_state([
    'csrf' => 'valid-token',
    'action' => 'arm',
    'actor_a' => '101',
    'actor_b' => '202',
    'exclude_player' => '1',
    'bystander_mode' => 'silent',
], 'valid-token', ['101' => 'A', '202' => 'B']);
check($arm === [
    'enabled' => true,
    'scene_mode' => 'pair',
    'actor_a' => '101',
    'actor_b' => '202',
    'exclude_player' => true,
    'bystander_mode' => 'silent',
], 'Arm submission must be normalized to the frozen state contract.');
check(pcv_form_desired_state([
    'csrf' => 'valid-token', 'action' => 'arm', 'actor_a' => '101', 'actor_b' => ['untrusted'],
    'scene_mode' => 'solo', 'exclude_player' => '0', 'bystander_mode' => 'silent',
], 'valid-token', ['101' => 'A']) === [
    'enabled' => true,
    'scene_mode' => 'solo',
    'actor_a' => '101',
    'actor_b' => null,
    'exclude_player' => true,
    'bystander_mode' => 'silent',
], 'Solo mode must ignore actor B, preserve its valid bystander choice, and force player exclusion using one eligible actor.');
rejects(
    static fn() => pcv_form_desired_state([
        'csrf' => 'valid-token', 'action' => 'arm', 'actor_a' => '101', 'actor_b' => '202',
        'scene_mode' => [], 'bystander_mode' => 'exclude',
    ], 'valid-token', ['101' => 'A', '202' => 'B']),
    'An array scene mode must be rejected.'
);
rejects(
    static fn() => pcv_form_desired_state([
        'csrf' => 'valid-token', 'action' => 'arm', 'actor_a' => '101', 'actor_b' => '202',
        'scene_mode' => null, 'bystander_mode' => 'exclude',
    ], 'valid-token', ['101' => 'A', '202' => 'B']),
    'An explicitly null scene mode must be rejected.'
);
rejects(
    static fn() => pcv_form_desired_state([
        'csrf' => 'valid-token', 'action' => 'arm', 'actor_a' => '101', 'actor_b' => ['untrusted'],
        'scene_mode' => 'solo', 'bystander_mode' => ['untrusted'],
    ], 'valid-token', ['101' => 'A']),
    'Solo mode must reject malformed bystander choices.'
);
check(pcv_form_desired_state([
    'csrf' => 'valid-token', 'action' => 'arm', 'actor_a' => '101', 'actor_b' => '202',
    'bystander_mode' => 'exclude',
], 'valid-token', ['101' => 'A', '202' => 'B'])['exclude_player'] === false, 'Unchecked player exclusion must be preserved as false.');

$off = pcv_form_desired_state(['csrf' => 'valid-token', 'action' => 'end'], 'valid-token', []);
check($off === ['enabled' => false], 'End submission must stage only the disabled state.');
check(pcv_form_can_stage($off, 'a-valid-playthrough-key', false), 'End must remain available when the NPC catalog is unavailable.');
check(!pcv_form_can_stage($off, null, false), 'End must not stage without a current playthrough identity.');
check(!pcv_form_can_stage($arm, 'a-valid-playthrough-key', false), 'Arming a pair must require a current NPC catalog.');

rejects(
    static fn() => pcv_form_desired_state(['csrf' => 'wrong', 'action' => 'end'], 'valid-token', []),
    'Invalid CSRF token was accepted.'
);
rejects(
    static fn() => pcv_form_desired_state([
        'csrf' => 'valid-token', 'action' => 'arm', 'actor_a' => '101', 'actor_b' => '999',
        'bystander_mode' => 'exclude',
    ], 'valid-token', ['101' => 'A']),
    'Unknown actor ID was accepted.'
);
rejects(
    static fn() => pcv_form_desired_state([
        'csrf' => 'valid-token', 'action' => 'arm', 'actor_a' => '999', 'actor_b' => '202',
        'bystander_mode' => 'exclude',
    ], 'valid-token', ['101' => 'A', '202' => 'B']),
    'Unknown actor A was accepted.'
);
rejects(
    static fn() => pcv_form_desired_state([
        'csrf' => 'valid-token', 'action' => 'arm', 'actor_a' => '101', 'actor_b' => '101',
        'bystander_mode' => 'exclude',
    ], 'valid-token', ['101' => 'A']),
    'Equal actor IDs were accepted.'
);

$html = pcv_render_page(
    'valid-token',
    ['status' => 'active', 'scope' => [
        'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'silent',
    ], 'pending' => true, 'pending_scope' => [
        'enabled' => true, 'actor_a' => '202', 'actor_b' => '303', 'exclude_player' => false, 'bystander_mode' => 'exclude',
    ]],
    ['101' => '<script>alert(1)</script>', '202' => 'B', '303' => 'C'],
    'Change queued.'
);
check(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'NPC names must be escaped in options.');
check(!str_contains($html, '<script>alert(1)</script>'), 'Raw NPC markup reached the page.');
check(str_contains($html, 'next eligible ordinary in-game input'), 'Pending transition boundary is not explained.');
check(str_contains($html, 'player is excluded'), 'Current player exclusion is not shown.');
check(str_contains($html, 'Next pair: B and C'), 'Queued pair is not shown separately from the active pair.');
check(str_contains($html, 'id="mode-guidance" class="small-note" hidden>'), 'Solo-only guidance is hidden in pair mode.');
check(str_contains($html, 'queued setting will include the player'), 'Queued player setting is not shown.');
check(str_contains($html, 'value="202" selected'), 'Pending pair is not prefilled in the form.');
check(str_contains($html, 'Standard'), 'Standard-only scope is not shown.');
check(str_contains($html, '<title>CHIM Private Conversation — Part of the World of Drama-llama</title>')
    && str_contains($html, '<p class="eyebrow hero-credit">Part of the World of Drama-llama</p>')
    && str_contains($html, 'emo llama illustrated on a hanging banner in the background'),
    'The page title, visible hero credit, and scene description must carry the Drama-llama brand cue.');
check(str_contains($html, 'not a privacy barrier') && str_contains($html, 'vanilla greetings'), 'Privacy and vanilla-greeting limitations are not shown.');
check(str_contains($html, 'existing history, memories') && str_contains($html, 'generic scenery'), 'Retained context and silent-mode limitations are not shown.');
check(!str_contains($html, '<textarea'), 'UI must not expose a speech text field.');
check(!str_contains($html, 'api_key'), 'UI must not expose secrets.');
check(str_contains($html, 'assets/style.css') && str_contains($html, 'assets/private-conversation-scene.png'), 'Hero assets are not referenced locally.');
check(str_contains($html, 'assets/ui-refresh.js') && str_contains($html, 'data-refresh-url="?refresh=1"'),
    'Automatic refresh must use a local external script and same-origin route.');
check(str_contains($html, 'href="?view=logs"') && str_contains($html, 'data-logs-url="?view=logs"'),
    'The scene page must expose a standalone diagnostics route and same-origin client-report target.');
check(str_contains($html, 'class="scope-form"') && str_contains($html, 'End on next input'),
    'Diagnostics-only access checks must leave the existing scene ARM and END controls intact.');
check(str_contains($html, 'This list polls automatically for updates')
    && str_contains($html, 'Uses CHIM’s broader nearby range')
    && str_contains($html, 'AI observations expire after 45 seconds')
    && str_contains($html, 'NPCs sharing the same name cannot be distinguished')
    && !str_contains($html, 'companion presence reporting is active'),
    'Picker guidance must describe the background scan and bounded, name-matched observations.');

$pendingEnd = pcv_render_page('valid-token', [
    'status' => 'pending', 'scope' => null, 'pending' => true,
    'pending_scope' => ['enabled' => false, 'actor_a' => '', 'actor_b' => '', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
], ['101' => 'A', '202' => 'B']);
check(str_contains($pendingEnd, 'End is queued'), 'Pending end is not identified.');
check(str_contains($pendingEnd, 'no scene is active now'), 'Pending end implies a scene is active.');
check(!str_contains($pendingEnd, 'will become active'), 'Pending end is described as an activation.');

$singlePicker = pcv_render_page('valid-token', ['status' => 'off', 'scope' => null, 'pending' => false],
    ['101' => 'Aela'], '', 'ready', true);
check(str_contains($singlePicker, 'Pair mode requires two distinct nearby CHIM-AI-active characters')
    && str_contains($singlePicker, 'Solo reflection needs one nearby CHIM-AI-active character')
    && str_contains($singlePicker, 'id="actor-a" name="actor_a" required')
    && str_contains($singlePicker, 'id="solo-mode" name="scene_mode" value="solo"')
    && str_contains($singlePicker, 'id="arm-button" class="primary-button" type="submit" data-roster-ready="1" disabled>'),
    'One eligible NPC must remain selectable for solo while pair ARM stays disabled.');
$soloPicker = pcv_render_page('valid-token', [
    'status' => 'pending', 'scope' => null, 'pending' => true,
    'pending_scope' => [
        'enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '101', 'actor_b' => null,
        'exclude_player' => true, 'bystander_mode' => 'exclude',
    ],
], ['101' => 'Aela'], '', 'ready', true);
check(str_contains($soloPicker, 'Next reflection: Aela.')
    && str_contains($soloPicker, 'for="actor-a">Reflecting NPC</label>')
    && str_contains($soloPicker, 'for="actor-b">Second NPC (pair mode only)</label>')
    && str_contains($soloPicker, 'id="actor-b" name="actor_b" required disabled')
    && str_contains($soloPicker, 'id="solo-mode" name="scene_mode" value="solo" checked')
    && str_contains($soloPicker, 'Solo reflection always excludes the player.')
    && str_contains($soloPicker, 'Opinion changes require compatible Mind Poisoning support.')
    && str_contains($soloPicker, 'id="mode-guidance" class="small-note">')
    && str_contains($soloPicker, 'id="arm-button" class="primary-button" type="submit" data-roster-ready="1">'),
    'Solo page must select one reflecting NPC, disable B, force player exclusion, and arm with one eligible actor.');
$emptyPicker = pcv_render_page('valid-token', ['status' => 'off', 'scope' => null, 'pending' => false],
    [], '', 'empty', true);
$missingPicker = pcv_render_page('valid-token', ['status' => 'off', 'scope' => null, 'pending' => false],
    [], '', 'missing', true);
$stalePicker = pcv_render_page('valid-token', ['status' => 'off', 'scope' => null, 'pending' => false],
    [], '', 'stale', true);
check(str_contains($emptyPicker, 'No eligible NPCs matched the latest report')
    && str_contains($emptyPicker, 'This list polls automatically for updates')
    && str_contains($emptyPicker, 'reported empty result'), 'Valid empty presence needs explicit empty-state guidance.');
check(str_contains($missingPicker, 'No qualifying CHIM presence report is available yet')
    && str_contains($missingPicker, 'unknown, not empty')
    && !str_contains($missingPicker, 'No eligible NPCs matched the latest report'), 'Missing presence must differ from valid empty.');
check(str_contains($stalePicker, 'Fresh nearby and AI activity could not be confirmed; current eligibility is unknown')
    && str_contains($stalePicker, 'This list polls automatically for updates')
    && !str_contains($stalePicker, 'No qualifying CHIM presence report is cached yet'), 'Stale presence must differ from missing.');
$catalogFailure = pcv_render_page('valid-token', ['status' => 'off', 'scope' => null, 'pending' => false],
    [], '', 'unavailable', false);
check(str_contains($catalogFailure, 'ARM is unavailable; END remains available')
    && str_contains($catalogFailure, 'type="submit">End on next input</button>'), 'END must remain available if the catalog fails.');
$armedPicker = pcv_render_page('valid-token', [
    'status' => 'pending', 'scope' => null, 'pending' => true,
    'pending_scope' => ['enabled' => true, 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
], ['101' => 'Aela', '202' => 'Bryn']);
check(str_contains($armedPicker, 'only if both selected NPCs are still nearby and CHIM-AI-active'),
    'Armed status must qualify activation on current presence and AI state.');

$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-ui-' . bin2hex(random_bytes(6));
mkdir($fixture . '/lib/core', 0777, true);
mkdir($fixture . '/conf', 0777, true);
file_put_contents($fixture . '/lib/runtime_bootstrap.php', <<<'PHP'
<?php
function chimRuntimeImportConfigVariables(array $variables): void
{
    foreach ($variables as $name => $value) {
        if (is_string($name) && preg_match('/\A[A-Z0-9_]+\z/', $name) === 1) {
            $GLOBALS[$name] = $value;
        }
    }
}
function chimRuntimeBootstrap(string $path, array $options = []): void
{
    throw new RuntimeException('The side-effectful runtime bootstrap must not run on this page.');
}
PHP);
file_put_contents($fixture . '/conf/conf.sample.php', "<?php \$DBDRIVER = 'fixture_db';\n");
file_put_contents($fixture . '/conf/conf.php', "<?php \$FIXTURE_CONFIG_LOADED = true;\n");
file_put_contents($fixture . '/lib/fixture_db.class.php', <<<'PHP'
<?php
class sql
{
    public function __construct()
    {
        $GLOBALS['pcv_fixture_db_opened'] = true;
    }
}
PHP);
file_put_contents($fixture . '/lib/core/npc_master.class.php', <<<'PHP'
<?php
class NpcMaster
{
    public function __construct()
    {
        if (!($GLOBALS['db'] ?? null) instanceof sql) {
            throw new RuntimeException('NPC master was not given the existing CHIM database object.');
        }
    }
    public function getAll(): array
    {
        $GLOBALS['pcv_fixture_get_all_called'] = true;
        return [['id' => 301, 'npc_name' => 'Fixture NPC']];
    }
}
PHP);
try {
    $rows = pcv_npc_rows($fixture);
    check(($GLOBALS['pcv_fixture_db_opened'] ?? false) === true, 'UI bootstrap did not construct the CHIM SQL driver.');
    check(($GLOBALS['pcv_fixture_get_all_called'] ?? false) === true, 'UI bootstrap did not call the instance NpcMaster::getAll().');
    check($rows === [['id' => 301, 'npc_name' => 'Fixture NPC']], 'UI bootstrap did not return the current NPC rows.');
} finally {
    foreach ([
        '/lib/core/npc_master.class.php', '/lib/fixture_db.class.php', '/lib/runtime_bootstrap.php',
        '/conf/conf.sample.php', '/conf/conf.php',
    ] as $relative) {
        @unlink($fixture . $relative);
    }
    @rmdir($fixture . '/lib/core');
    @rmdir($fixture . '/lib');
    @rmdir($fixture . '/conf');
    @rmdir($fixture);
}

$logFixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-ui-log-' . bin2hex(random_bytes(6));
mkdir($logFixture, 0700);
chmod($logFixture, 0700);
check(pcv_log_set_test_directory($logFixture), 'UI logger fixture directory was not accepted.');
$fallbackLog = $logFixture . DIRECTORY_SEPARATOR . 'fallback.log';
$oldErrorLog = ini_get('error_log');
check(is_string($oldErrorLog) && ini_set('error_log', $fallbackLog) !== false, 'Could not isolate the logger fallback.');
$pendingConfigId = '44444444-4444-4444-8444-444444444444';
$activeConfigId = '33333333-3333-4333-8333-333333333333';
try {
    mkdir($logFixture . DIRECTORY_SEPARATOR . 'events.lock', 0700);
    ob_start();
    pcv_ui_log_stage_accepted([
        'status' => 'active',
        'pending' => true,
        'config_id' => $activeConfigId,
        'pending_config_id' => $pendingConfigId,
    ], 'enable');
    $loggerOutput = ob_get_clean();
    $requestContext =& pcv_log_request_context();
    check($loggerOutput === '', 'A logger failure wrote into the UI response.');
    check($requestContext['config_id'] === $pendingConfigId, 'Accepted UI event did not correlate to the staged pending config ID.');
    check(!file_exists($logFixture . DIRECTORY_SEPARATOR . 'events.jsonl'), 'Logger failure created a partial event file.');
    check(is_file($fallbackLog) && str_contains((string)file_get_contents($fallbackLog), 'config_id=' . $pendingConfigId),
        'Logger failure did not use its bounded fallback with pending-config correlation.');
} finally {
    if (is_string($oldErrorLog)) {
        ini_set('error_log', $oldErrorLog);
    }
    @rmdir($logFixture . DIRECTORY_SEPARATOR . 'events.lock');
    @unlink($fallbackLog);
    @rmdir($logFixture);
}

echo "UI checks passed.\n";
