<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/server/scope.php';

final class RoutingFixFixtureStopped extends RuntimeException {}

function routingFixCheck(bool $condition, string $message, array &$failures): void
{
    if (!$condition) {
        $failures[] = $message;
    }
}

function terminate(): void
{
    throw new RoutingFixFixtureStopped('hook terminated');
}

function pcv_current_playthrough_key(): ?string { return 'routing-fix-fixture'; }
function pcv_current_player_name(): ?string { return 'Player'; }
function pcv_read(string $key): array { return $GLOBALS['routing_fix_state']; }
function pcv_read_npc_catalog(): array
{
    return [
        ['id' => '101', 'npc_name' => 'Aela', 'profile_id' => 11],
        ['id' => '202', 'npc_name' => 'Bryn', 'profile_id' => 12],
    ];
}
function pcv_capture_presence_snapshot(?string $key, $raw): array
{
    return ['status' => 'ready', 'actors' => [
        ['name' => 'Aela', 'distance' => 10], ['name' => 'Bryn', 'distance' => 20],
    ]];
}
function pcv_read_active_scene_npcs($key, $rows, $playerName, $actorIds): array
{
    return ['known_npcs' => ['101' => 'Aela', '202' => 'Bryn'], 'missing' => [], 'error' => false, 'report_fresh' => true];
}
function pcv_begin_request($key, $eligible, ...$arguments): array
{
    $GLOBALS['routing_fix_begin_calls']++;
    $state = $GLOBALS['routing_fix_state'];
    $pending = $state['pending_scope'] ?? null;
    if ($eligible && is_array($pending) && ($pending['enabled'] ?? null) === true) {
        $state = [
            'status' => 'active', 'scope' => $pending, 'pending' => false,
            'pending_scope' => null, 'config_id' => 'promoted-config',
        ];
        $GLOBALS['routing_fix_state'] = $state;
    }
    return $state;
}
function pcv_stage(string $key, array $desired, array $knownNpcs, ?string $stateDirectory = null, ?string $endReason = null): array
{
    $GLOBALS['routing_fix_stage_calls'][] = ['desired' => $desired, 'reason' => $endReason];
    if (($GLOBALS['routing_fix_stage_result'] ?? 'off') !== 'off') {
        return ['status' => 'unavailable'];
    }
    $GLOBALS['routing_fix_state'] = ['status' => 'off', 'scope' => null, 'pending' => false, 'pending_scope' => null];
    return $GLOBALS['routing_fix_state'];
}
function pcv_solo_inflight_mark(...$arguments): void {}

function chimSwitchActiveNpcProfile(string $name): bool
{
    $GLOBALS['HERIKA_NAME'] = $name;
    return true;
}

function chimRefreshJsonResponseState(bool $loadExtensionCustomizers = false): void
{
    $actions = array_values(array_unique(array_merge(['Talk'], $GLOBALS['ENABLED_FUNCTIONS'] ?? [])));
    $GLOBALS['FUNC_LIST'] = $actions;
    $GLOBALS['responseTemplate'] = ['listener' => 'base listener'];
    $GLOBALS['structuredOutputTemplate'] = ['json_schema' => ['schema' => ['properties' => [
        'action' => ['enum' => $actions],
        'listener' => ['type' => 'string', 'description' => 'base listener'],
    ]]]];
    foreach ($GLOBALS['HOOKS']['JSON_TEMPLATE'] ?? [] as $hook) {
        if (is_string($hook) && function_exists($hook)) {
            $hook();
        } elseif (is_callable($hook)) {
            $hook();
        }
    }
}

function routingFixRunWrap(array $state, string $stageResult = 'off'): array
{
    $GLOBALS['routing_fix_state'] = $state;
    $GLOBALS['routing_fix_stage_result'] = $stageResult;
    $GLOBALS['routing_fix_stage_calls'] = [];
    $GLOBALS['routing_fix_begin_calls'] = 0;
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    $GLOBALS['PLAYER_RESPEECH'] = false;
    $GLOBALS['HERIKA_NAME'] = 'Aela';
    $GLOBALS['gameRequest'] = ['inputtext', '1700000000', '12345', 'Player: wrap up: They part ways at dusk.', '{}'];
    $GLOBALS['requestRoutingSnapshot'] = ['audience' => '|Player|Aela|Bryn|', 'present_actors' => ['Aela', 'Bryn']];
    unset($GLOBALS['PCV_REQUEST_SCOPE']);

    ob_start();
    $stopped = false;
    try {
        include dirname(__DIR__) . '/server/preprocessing.php';
    } catch (RoutingFixFixtureStopped) {
        $stopped = true;
    } finally {
        $output = (string)ob_get_clean();
    }

    return [
        'stopped' => $stopped,
        'output' => $output,
        'request' => $GLOBALS['gameRequest'],
        'scope' => $GLOBALS['PCV_REQUEST_SCOPE'] ?? null,
        'state' => $GLOBALS['routing_fix_state'],
        'stage_calls' => $GLOBALS['routing_fix_stage_calls'],
        'begin_calls' => $GLOBALS['routing_fix_begin_calls'],
    ];
}

function routingFixRunActionHook(array $storedScope, array $originalTargets): array
{
    $scope = pcvResolveScopeNames($storedScope, ['101' => 'Aela', '202' => 'Bryn'], 'Player');
    if (!is_array($scope)) {
        throw new RuntimeException('The action fixture scope did not resolve.');
    }
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    $GLOBALS['gameRequest'] = ['inputtext', '1700000001', '12345', 'Player: spar with someone.', '{}'];
    $GLOBALS['requestRoutingSnapshot'] = ['audience' => '|Player|Aela|Bryn|', 'present_actors' => []];
    $GLOBALS['PCV_REQUEST_SCOPE'] = [
        'status' => 'active', 'scope' => $scope, 'route' => $scope['exclude_player'] ? 'scene_direction' : 'player_speech',
        'origin_request_type' => 'inputtext', 'origin_mode' => 'STANDARD', 'config_id' => 'action-config',
        'actor_a_id' => '101', 'actor_b_id' => '202',
    ];
    $GLOBALS['HERIKA_NAME'] = 'Aela';
    $GLOBALS['ENABLED_FUNCTIONS'] = ['Brawl', 'Surrender', 'Attack'];
    $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
    $GLOBALS['FUNCTION_PARM_INSPECT'] = $originalTargets;
    $GLOBALS['HOOKS']['JSON_TEMPLATE'] = [];
    // json_response_custom.php is already loaded after the first call; restore its real callback as a new request would.
    if (function_exists('pcvRegisterJsonResponseCustomizer')) {
        pcvRegisterJsonResponseCustomizer();
    }
    include dirname(__DIR__) . '/server/context_pre.php';

    $actions = ['Aela|command|Brawl@Player', 'Aela|command|Brawl@Nazeem'];
    foreach ($GLOBALS['action_post_process_fnct_ex'] ?? [] as $filter) {
        if (is_string($filter) && function_exists($filter)) {
            $actions = $filter($actions);
        } elseif (is_callable($filter)) {
            $actions = $filter($actions);
        }
    }
    return [
        'targets' => $GLOBALS['FUNCTION_PARM_INSPECT'] ?? null,
        'action_player' => $GLOBALS['PCV_REQUEST_SCOPE']['action_player'] ?? null,
        'actions' => $actions,
    ];
}

$failures = [];
$pair = [
    'enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '101', 'actor_b' => '202',
    'exclude_player' => true, 'bystander_mode' => 'exclude',
];
$solo = [
    'enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '101', 'actor_b' => null,
    'exclude_player' => true, 'bystander_mode' => 'exclude',
];
$pairControl = routingFixRunWrap(['status' => 'active', 'scope' => $pair, 'pending' => false, 'config_id' => 'pair']);
routingFixCheck(!$pairControl['stopped']
    && ($pairControl['request'][0] ?? null) === 'instruction'
    && ($pairControl['request'][3] ?? null) === 'Player: They part ways at dusk.'
    && ($pairControl['scope']['route'] ?? null) === 'scene_direction'
    && ($pairControl['scope']['wrap_up'] ?? false) === true
    && ($pairControl['stage_calls'][0]['reason'] ?? null) === 'wrapped_up',
    'A Pair-only wrap must keep its normal closing turn.', $failures);

$queuedSoloWrap = routingFixRunWrap([
    'status' => 'active', 'scope' => $pair, 'pending' => true, 'pending_scope' => $solo,
    'config_id' => 'pair', 'pending_config_id' => 'queued-solo',
]);
routingFixCheck($queuedSoloWrap['stopped']
    && ($queuedSoloWrap['state']['status'] ?? null) === 'off'
    && ($queuedSoloWrap['stage_calls'][0]['reason'] ?? null) === 'wrapped_up'
    && $queuedSoloWrap['begin_calls'] === 0,
    'A queued Solo wrap must end before the queued Solo is promoted or generated.', $failures);

$activeSoloQueuedPair = routingFixRunWrap([
    'status' => 'active', 'scope' => $solo, 'pending' => true, 'pending_scope' => $pair,
    'config_id' => 'solo', 'pending_config_id' => 'queued-pair',
]);
routingFixCheck($activeSoloQueuedPair['stopped']
    && ($activeSoloQueuedPair['state']['status'] ?? null) === 'off'
    && ($activeSoloQueuedPair['stage_calls'][0]['reason'] ?? null) === 'wrapped_up'
    && $activeSoloQueuedPair['begin_calls'] === 0,
    'An active Solo wrap must end before its queued Pair is promoted.', $failures);

$pendingSolo = routingFixRunWrap([
    'status' => 'pending', 'scope' => null, 'pending' => true, 'pending_scope' => $solo,
    'pending_config_id' => 'pending-solo',
]);
routingFixCheck($pendingSolo['stopped']
    && ($pendingSolo['state']['status'] ?? null) === 'off'
    && ($pendingSolo['stage_calls'][0]['reason'] ?? null) === 'wrapped_up'
    && $pendingSolo['begin_calls'] === 0,
    'A pending Solo wrap must end before Solo activation.', $failures);

$failedSoloClear = routingFixRunWrap([
    'status' => 'active', 'scope' => $pair, 'pending' => true, 'pending_scope' => $solo,
    'config_id' => 'pair', 'pending_config_id' => 'queued-solo',
], 'unavailable');
routingFixCheck($failedSoloClear['stopped']
    && ($failedSoloClear['state']['status'] ?? null) === 'active'
    && count($failedSoloClear['stage_calls']) === 1
    && ($failedSoloClear['stage_calls'][0]['desired'] ?? null) === ['enabled' => false]
    && ($failedSoloClear['stage_calls'][0]['reason'] ?? null) === 'wrapped_up'
    && ($failedSoloClear['state']['scope'] ?? null) === $pair
    && ($failedSoloClear['state']['pending_scope'] ?? null) === $solo
    && $failedSoloClear['begin_calls'] === 0
    && str_contains($failedSoloClear['output'], 'could not confirm that the scene ended')
    && !str_contains($failedSoloClear['output'], 'reflection ended'),
    'A failed Solo clear must block without claiming END success or generating a response.', $failures);

$includedAction = routingFixRunActionHook(
    array_replace($pair, ['exclude_player' => false, 'actions' => ['physical']]),
    ['Aela', 'Bryn', 'Player', 'Nazeem']
);
routingFixCheck($includedAction['targets'] === ['Aela', 'Bryn', 'Player']
    && $includedAction['action_player'] === 'Player'
    && $includedAction['actions'] === ['Aela|command|Brawl@Player'],
    'An originally offered included-player target must match the validated output-verdict identity.', $failures);

$includedActionNotOffered = routingFixRunActionHook(
    array_replace($pair, ['exclude_player' => false, 'actions' => ['physical']]),
    ['Aela', 'Bryn', 'Nazeem']
);
routingFixCheck($includedActionNotOffered['targets'] === ['Aela', 'Bryn'],
    'The included player must not be added to Brawl targets when CHIM did not offer the player.', $failures);

$excludedAction = routingFixRunActionHook(
    array_replace($pair, ['exclude_player' => true, 'actions' => ['physical']]),
    ['Aela', 'Bryn', 'Player', 'Nazeem']
);
routingFixCheck($excludedAction['targets'] === ['Aela', 'Bryn']
    && $excludedAction['actions'] === [],
    'An excluded player must remain absent from Brawl targets and rejected by the output filter.', $failures);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "PASS actual hooks: Pair and Solo wrap priority, clear failure, included-player Brawl offer, and exclusion controls.\n");
