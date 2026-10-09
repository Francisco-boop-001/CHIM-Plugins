<?php
declare(strict_types=1);

// 0.1.16 scene actions: three opt-in groups (personal, physical, intimate), member-only targets, enforced before
// generation (allowed action list) and after it (CHIM's action post-filter). Attack and KillTarget are never allowed.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function saCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$logDirectory = sys_get_temp_dir() . '/pcv-scene-actions-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    saCheck(mkdir($logDirectory, 0700) && pcv_log_set_test_directory($logDirectory), 'logger fixture');
    $known = ['11' => 'Lidia', '12' => 'Bruce'];
    $pair = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '11', 'actor_b' => '12', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $solo = ['enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '11', 'actor_b' => null, 'exclude_player' => true, 'bystander_mode' => 'exclude'];

    // Stored setting.
    saCheck(pcv_valid_config($pair + ['actions' => ['personal', 'physical', 'intimate']], false), 'All three groups are valid for a pair.');
    saCheck(!pcv_valid_config($pair + ['actions' => ['lethal']], false), 'Unknown groups are rejected.');
    saCheck(!pcv_valid_config($pair + ['actions' => []], false), 'An empty list is omitted, not stored.');
    saCheck(!pcv_valid_config($pair + ['actions' => ['intimate', 'personal']], false), 'Groups are stored in canonical order.');
    saCheck(pcv_valid_config($solo + ['actions' => ['personal', 'intimate']], false), 'Solo may act personally and intimately (self only).');
    saCheck(!pcv_valid_config($solo + ['actions' => ['physical']], false), 'Solo has no one to brawl with.');
    $n = pcv_normalize_config($pair + ['actions' => ['intimate', 'personal', 'personal']], $known);
    saCheck(($n['actions'] ?? null) === ['personal', 'intimate'], 'Normalization orders and deduplicates.');
    $n = pcv_normalize_config($solo + ['actions' => ['physical', 'personal']], $known);
    saCheck(($n['actions'] ?? null) === ['personal'], 'Solo drops the physical group.');

    // Allowed codes.
    $resolved = pcvResolveScopeNames($pair + ['actions' => ['personal', 'physical', 'intimate']], $known, 'Hawke');
    saCheck(($resolved['actions'] ?? null) === ['personal', 'physical', 'intimate'], 'Resolved scopes carry the groups.');
    $codes = pcvSceneAllowedActionCodes($resolved);
    saCheck(in_array('Drink', $codes, true) && in_array('Consume', $codes, true) && in_array('Brawl', $codes, true)
        && in_array('ExtCmdKiss', $codes, true) && in_array('ExtCmdStartSex', $codes, true), 'Allowed codes cover the groups.');
    saCheck(!in_array('Attack', $codes, true) && !in_array('KillTarget', $codes, true) && !in_array('GiveItemTo', $codes, true)
        && !in_array('ExtCmdWorshipMaster', $codes, true), 'Lethal, gift and role actions are never allowed.');
    saCheck(pcvSceneAllowedActionCodes(pcvResolveScopeNames($pair, $known, 'Hawke')) === [], 'No groups: talk only, as before.');

    // Target parsing.
    saCheck(pcvActionTargetsFromParameter('{"target":"Bruce [RefID: 0001A2B3]"}') === ['Bruce'], 'JSON targets are read and RefIDs stripped.');
    saCheck(pcvActionTargetsFromParameter('Lidia, Bruce') === ['Lidia', 'Bruce'], 'Comma-separated partners are split.');
    saCheck(pcvActionTargetsFromParameter('') === [] && pcvActionTargetsFromParameter('{"target":""}') === [], 'Blank targets are empty.');

    // Verdicts: pair, player excluded.
    $v = static fn(string $code, string $param, array $scope = null, string $speaker = 'Lidia')
        => pcvSceneActionVerdict($code, $param, $scope ?? $resolved, $speaker, 'Hawke');
    saCheck($v('Brawl', '{"target":"Bruce"}') === null, 'Brawling a member is kept.');
    saCheck($v('Brawl', '{"target":"Hawke"}') === 'target_outside_scene', 'Brawling the excluded player is dropped.');
    saCheck($v('Brawl', '{"target":"Aelva"}') === 'target_outside_scene', 'Brawling a bystander is dropped.');
    saCheck($v('Brawl', '') === 'target_outside_scene', 'A brawl needs a member target.');
    saCheck($v('Attack', '{"target":"Bruce"}') === 'action_not_allowed', 'Attack is never allowed.');
    saCheck($v('Toast', '{"target":"Bruce"}') === null && $v('Toast', '{"target":"Hawke"}') === 'target_outside_scene', 'Toasts stay inside the scene.');
    saCheck($v('Consume', '{"target":"0003133C:Mead"}') === null, 'Consuming an item is kept (the target is the item).');
    saCheck($v('Drink', '') === null && $v('Surrender', '') === null, 'Untargeted personal and physical actions are kept.');
    saCheck($v('ExtCmdKiss', '{"target":"Bruce"}') === null, 'A kiss between members is kept.');
    saCheck($v('ExtCmdAcceptSex', '{"target":"Hawke"}') === 'target_outside_scene', 'SHARMAT coercion to the excluded player is dropped.');
    saCheck($v('ExtCmdStartThreesome', 'Bruce, Aelva') === 'target_outside_scene', 'A threesome reaching outside the scene is dropped.');
    saCheck($v('ExtCmdStartSex', '') === 'target_outside_scene', 'Partner actions need a member target.');
    $physicalOnly = pcvResolveScopeNames($pair + ['actions' => ['physical']], $known, 'Hawke');
    saCheck($v('ExtCmdKiss', '{"target":"Bruce"}', $physicalOnly) === 'action_not_allowed', 'Unticked groups are not allowed.');
    $withPlayer = pcvResolveScopeNames(array_replace($pair, ['exclude_player' => false]) + ['actions' => ['physical']], $known, 'Hawke');
    saCheck($v('Brawl', '{"target":"Hawke"}', $withPlayer) === null, 'An included player is a valid target.');

    // Verdicts: solo (self only).
    $soloResolved = pcvResolveScopeNames($solo + ['actions' => ['personal', 'intimate']], $known, 'Hawke');
    saCheck($v('ExtCmdStartSelfMasturbation', '', $soloResolved) === null, 'A solo self-directed moment is kept.');
    saCheck($v('Consume', '{"target":"0003133C:Mead"}', $soloResolved) === null, 'Drinking alone is kept.');
    saCheck($v('ExtCmdKiss', '{"target":"Bruce"}', $soloResolved) === 'target_outside_scene', 'Solo cannot reach anyone else.');
    saCheck($v('Brawl', '{"target":"Bruce"}', $soloResolved) === 'action_not_allowed', 'Solo never brawls.');

    // Post-filter over CHIM action strings.
    $GLOBALS['PCV_REQUEST_SCOPE'] = ['status' => 'active', 'scope' => $resolved, 'action_player' => 'Hawke'];
    $GLOBALS['HERIKA_NAME'] = 'Lidia';
    $kept = pcvSceneActionPostFilter([
        "Lidia|command|Brawl@{\"target\":\"Bruce\"}\r\n",
        "Lidia|command|Brawl@{\"target\":\"Hawke\"}\r\n",
        "Lidia|command|KillTarget@{\"target\":\"Bruce\"}\r\n",
    ]);
    saCheck(array_values($kept) === ["Lidia|command|Brawl@{\"target\":\"Bruce\"}\r\n"], 'The post-filter keeps only allowed, in-scene actions.');
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    saCheck(pcvSceneActionPostFilter(['x|command|Attack@{}']) === ['x|command|Attack@{}'], 'Outside a scene the filter changes nothing.');
    saCheck(pcv_log_rule_matches('routing.action_dropped', 'warning', 'blocked', 'target_outside_scene')
        && pcv_log_rule_matches('routing.action_dropped', 'warning', 'blocked', 'action_not_allowed'), 'Drops are logged as warnings.');

    echo "PASS scene actions: opt-in groups, member-only targets, lethal never, solo self-only, post-filter drops the rest\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach (glob($logDirectory . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($logDirectory);
}
exit($exitCode);
