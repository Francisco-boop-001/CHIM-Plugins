<?php
declare(strict_types=1);

require_once 'K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/scope.php';
$GLOBALS['HERIKA_NAME'] = 'Aela';
require_once 'K:/ActorwrightExchange/artifacts/department-maintainer-audit-20261026/host/server-dev/functions/json_response.php';

function getFunctionCodeName($name)
{
    return (string)$name;
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$known = ['11' => 'Aela', '12' => 'Bryn'];
$playerName = 'Hawke';
$GLOBALS['PLAYER_NAME'] = $playerName;
$GLOBALS['gameRequest'] = ['inputtext'];
$GLOBALS['FUNCTIONS'] = [['name' => 'Brawl', 'description' => 'Brawl description']];
$GLOBALS['F_NAMES'] = ['Attack' => 'Attack', 'Brawl' => 'Brawl'];
$GLOBALS['COMMAND_PROMPT_FUNCTIONS'] = '';

function runPair(bool $excludePlayer, array $known, string $playerName): array
{
    $stored = [
        'enabled' => true,
        'scene_mode' => 'pair',
        'actor_a' => '11',
        'actor_b' => '12',
        'exclude_player' => $excludePlayer,
        'bystander_mode' => 'exclude',
        'actions' => ['physical'],
    ];
    $scope = pcvResolveScopeNames($stored, $known, $playerName);
    check(is_array($scope), 'pair scope did not resolve');

    $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
    $GLOBALS['ENABLED_FUNCTIONS'] = ['Brawl', 'ExtCmdKiss'];

    // Match retained prompt.includes.php:36-39. Nearby CHIM actor lookup is stubbed by these two members + a bystander;
    // retained initialization appends PLAYER_NAME after DataPosibleInspectTargets().
    $GLOBALS['FUNCTION_PARM_INSPECT'] = ['Aela', 'Bryn', 'Nazeem'];
    $GLOBALS['FUNCTION_PARM_INSPECT'][] = $playerName;

    check(pcvPrepareSceneActions($scope), 'physical actions were not prepared');
    setActions(); // retained CHIM json_response.php; builds the actual available-actions prompt section.
    $prompt = (string)$GLOBALS['PROMPT_ACTIONS_LIST'];
    if (!preg_match('/available targets: ([^\)\r\n]*)/', $prompt, $match)) {
        throw new RuntimeException('retained CHIM formatter did not emit a target list');
    }
    $promptTargets = $match[1] === '' ? [] : explode(',', $match[1]);

    $GLOBALS['PCV_REQUEST_SCOPE'] = [
        'status' => 'active',
        'scope' => $scope,
        'action_player' => $playerName,
    ];
    $GLOBALS['HERIKA_NAME'] = 'Aela';
    $playerVerdict = pcvSceneActionVerdict('Brawl', json_encode(['target' => $playerName]), $scope, 'Aela', $playerName);
    $playerKept = null;
    if ($playerVerdict === null) {
        $playerAction = "Aela|command|Brawl@{\"target\":\"{$playerName}\"}\r\n";
        $playerKept = pcvSceneActionPostFilter([$playerAction]) !== [];
    }
    $bystanderVerdict = pcvSceneActionVerdict('Brawl', json_encode(['target' => 'Nazeem']), $scope, 'Aela', $playerName);
    $intimateVerdict = pcvSceneActionVerdict('ExtCmdKiss', json_encode(['target' => 'Bryn']), $scope, 'Aela', $playerName);

    return [
        'prompt_targets' => $promptTargets,
        'player_verdict' => $playerVerdict,
        'player_action_kept' => $playerKept,
        'bystander_verdict' => $bystanderVerdict,
        'intimate_action_verdict' => $intimateVerdict,
    ];
}

$included = runPair(false, $known, $playerName);
$excluded = runPair(true, $known, $playerName);

check($included['prompt_targets'] === ['Aela', 'Bryn'], 'included scope target list changed unexpectedly');
check($included['player_verdict'] === null && $included['player_action_kept'] === true, 'included player action was not accepted');
check($included['bystander_verdict'] === 'target_outside_scene', 'bystander target escaped the scene verdict');
check($included['intimate_action_verdict'] === 'action_not_allowed', 'physical-only action gate re-enabled intimate action');
check($excluded['prompt_targets'] === ['Aela', 'Bryn'], 'excluded-player control changed its target list');
check($excluded['player_verdict'] === 'target_outside_scene' && $excluded['player_action_kept'] === null, 'excluded-player control stopped rejecting the player');

fwrite(STDOUT, json_encode(['included' => $included, 'excluded_control' => $excluded], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");