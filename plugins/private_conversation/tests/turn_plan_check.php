<?php
declare(strict_types=1);

// 0.1.14 per-turn plan for pair, group and free scenes:
// G0 SHARMAT compatibility: honor SHARMAT's intimate-scene listener pin when it was computed for this speaker and
//    names another member; a pin computed before PCV switched the speaker, or naming an outsider, is not honored.
// G3 turn spreading: in groups of 3+, address members who have not spoken this round (new round when all have).
// G6 wrap-up: the closing turn always uses the native no-rechat sentinel.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function tpCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$stateDirectory = sys_get_temp_dir() . '/pcv-turn-plan-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    mkdir($stateDirectory, 0700);
    $trio = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => 'Lidia', 'actor_b' => 'Aela',
        'members' => ['Lidia', 'Aela', 'Bruce'], 'opener' => null, 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $pair = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => 'Lidia', 'actor_b' => 'Aela',
        'members' => ['Lidia', 'Aela'], 'opener' => null, 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $config = '123e4567-e89b-42d3-a456-426614174000';

    // G3: rounds. Lidia opens; Bruce has not spoken, so Lidia should address Bruce.
    $spoken = pcv_scene_turns_record($config, 'Lidia', ['Lidia', 'Aela', 'Bruce'], $stateDirectory);
    $plan = pcvSceneTurnPlan($trio, 'Lidia', [], null, $spoken);
    echo 'plan1=' . json_encode($plan) . "\n";
    tpCheck($plan['listeners'] === ['Aela', 'Bruce'] && $plan['spread'] === true, 'At the start, everyone else is unspoken.');
    $spoken = pcv_scene_turns_record($config, 'Aela', ['Lidia', 'Aela', 'Bruce'], $stateDirectory);
    $plan = pcvSceneTurnPlan($trio, 'Aela', [], null, $spoken);
    tpCheck($plan['listeners'] === ['Bruce'], 'Aela should hand the floor to Bruce, who has not spoken.');
    $spoken = pcv_scene_turns_record($config, 'Bruce', ['Lidia', 'Aela', 'Bruce'], $stateDirectory);
    $plan = pcvSceneTurnPlan($trio, 'Bruce', [], null, $spoken);
    tpCheck($plan['listeners'] === ['Lidia', 'Aela'], 'When all have spoken, a new round allows everyone else.');
    tpCheck(pcv_scene_turns_record('223e4567-e89b-42d3-a456-426614174000', 'Aela', ['Lidia', 'Aela', 'Bruce'], $stateDirectory) === [pcv_scope_name_key('Aela')],
        'A new scene starts a fresh round.');

    // Pairs are unchanged.
    $plan = pcvSceneTurnPlan($pair, 'Lidia', [], null, []);
    tpCheck($plan['listeners'] === ['Aela'] && $plan['spread'] === false, 'Pairs keep their single listener.');

    // G6: wrap-up uses the sentinel.
    $plan = pcvSceneTurnPlan($trio, 'Lidia', ['wrap_up' => true], null, []);
    tpCheck($plan['listeners'] === ['explicit_disable_rechat'] && $plan['wrap_up'] === true && $plan['spread'] === false, 'Wrap-up closes with no rechat.');

    // G0: SHARMAT pin honored for the same speaker when the partner is a member.
    $plan = pcvSceneTurnPlan($trio, 'Lidia', [], 'Bruce', [pcv_scope_name_key('Lidia'), pcv_scope_name_key('Bruce')]);
    tpCheck($plan['listeners'] === ['Bruce'] && $plan['sharmat'] === true && $plan['spread'] === false, 'SHARMAT pin to a member is honored.');
    $plan = pcvSceneTurnPlan($trio, 'Lidia', ['wrap_up' => true], 'Bruce', []);
    tpCheck($plan['listeners'] === ['explicit_disable_rechat'] && $plan['wrap_up'] === true
        && $plan['sharmat'] === false, 'Wrap-up must suppress a valid SHARMAT listener pin.');
    // Not honored: pin computed before PCV switched the speaker, or naming an outsider, or naming the speaker.
    tpCheck(pcvSceneTurnPlan($trio, 'Lidia', ['speaker_switched' => true], 'Bruce', [])['sharmat'] === false, 'A pin from before the speaker switch is ignored.');
    tpCheck(pcvSceneTurnPlan($trio, 'Lidia', [], 'Nazeem', [])['listeners'] === ['Aela', 'Bruce'], 'A pin to an outsider never widens the scene.');
    tpCheck(pcvSceneTurnPlan($trio, 'Lidia', [], 'Lidia', [])['sharmat'] === false, 'A pin to the speaker is ignored.');
    tpCheck(pcvSharmatListenerPin() === null, 'No SHARMAT global, no pin.');
    $GLOBALS['AIAGENTNSFW_FORCE_SCENE_LISTENER'] = ' Bruce ';
    tpCheck(pcvSharmatListenerPin() === 'Bruce', 'The SHARMAT pin global is read and trimmed.');
    unset($GLOBALS['AIAGENTNSFW_FORCE_SCENE_LISTENER']);

    echo "PASS turn plans: SHARMAT pins honored safely, groups spread their turns, wrap-up closes without rechat\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach (glob($stateDirectory . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($stateDirectory);
}
exit($exitCode);
