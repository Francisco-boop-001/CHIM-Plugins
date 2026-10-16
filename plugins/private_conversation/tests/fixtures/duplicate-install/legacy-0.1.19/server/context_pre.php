<?php
declare(strict_types=1);

require_once __DIR__ . '/scope.php';

$requestType = is_string($GLOBALS['gameRequest'][0] ?? null) ? $GLOBALS['gameRequest'][0] : 'other';
$requestScope = $GLOBALS['PCV_REQUEST_SCOPE'] ?? null;
$validStatuses = ['active', 'off', 'pending', 'unavailable', 'identity_unavailable', 'ignored'];
if (!is_array($requestScope) || !in_array($requestScope['status'] ?? null, $validStatuses, true)) {
    return;
}
if (in_array($requestScope['status'], ['off', 'pending', 'identity_unavailable', 'ignored'], true)) {
    return;
}

pcvRoutingLogSetState($requestScope);
pcvRoutingLogStart($requestType);
if (($requestScope['status'] ?? null) === 'unavailable' || !is_array($requestScope['scope'] ?? null)) {
    pcvBlockRequest('Private Conversation state or actors are unavailable; request stopped for safety.', 'state_unavailable', 'context_pre', $requestScope, true);
}
if (!pcvRequestScopeModeMatches($requestScope) || ($requestScope['origin_mode'] ?? null) !== 'STANDARD') {
    pcvBlockRequest('Private Conversation stopped because the execution mode changed during routing.', 'mode_changed', 'context_pre', $requestScope);
}

$scope = $requestScope['scope'];
$solo = ($scope['scene_mode'] ?? 'pair') === 'solo';
$route = $requestScope['route'] ?? null;
if ($solo) {
    if (!pcvSoloReflectionRequest($requestScope)) {
        pcvBlockRequest('Solo reflection accepts one ordinary Standard input and does not continue through rechat.', 'solo_rechat_unsupported', 'context_pre', $requestScope);
    }
} elseif (!pcvPairRoutedRequest($requestScope)) {
    pcvBlockRequest('Private Conversation request origin is unavailable; request stopped for safety.', 'mode_changed', 'context_pre', $requestScope);
}

$speaker = trim((string)($GLOBALS['HERIKA_NAME'] ?? ''));
if (!pcvScopeSpeakerAllowed($speaker, $scope)) {
    pcvBlockRequest('Private Conversation selected an NPC outside the scene; request stopped for safety.', 'speaker_outside_scene', 'context_pre', $requestScope);
}
$GLOBALS['RECHAT_MODE'] = 'tight';
// 0.1.15 (L1 safety net): with silent bystanders, CHIM background narration still runs and its instruction-type
// user_input rows would supersede this scene request (CHIM exempts them only for direct player input). Align the
// request timestamp past rows written while this request waited for CHIM's lock, before the LLM call checks it.
$requestTs = $GLOBALS['gameRequest'][1] ?? null;
if ($requestType === 'instruction' && ($scope['bystander_mode'] ?? 'exclude') === 'silent'
    && (is_string($requestTs) || is_int($requestTs))) {
    $alignedTs = pcvLatestInstructionInputTs($GLOBALS['db'] ?? null, (string)$requestTs);
    if (is_string($alignedTs)) {
        $GLOBALS['gameRequest'][1] = $alignedTs;
    }
}
try {
    $playerName = pcv_current_player_name();
} catch (Throwable $error) {
    pcvRoutingLogException('context_pre', $error, $requestScope);
    throw $error;
}
if (!is_string($playerName) || trim($playerName) === '') {
    pcvBlockRequest('Private Conversation player identity is unavailable; request stopped for safety.', 'player_identity_unavailable', 'context_pre', $requestScope, true);
}
$others = $solo ? [] : pcvGroupListeners($speaker, $scope);
// Two members: the counterpart. Three or four: no single listener; the model addresses one of the others.
$listener = count($others) === 1 ? $others[0] : '';
if (!$solo && in_array($requestType, ['rechat', 'continue', 'continue_group'], true)) {
    // Keep strict-rechat's previous-speaker override inside the scene: the rechat's own speaker when it is a
    // member (already clamped in preprocessing), else the first other member.
    $rechatPayload = is_string($GLOBALS['gameRequest'][3] ?? null) ? json_decode($GLOBALS['gameRequest'][3], true) : null;
    $previous = is_array($rechatPayload) && is_string($rechatPayload['speaker'] ?? null) ? trim($rechatPayload['speaker']) : '';
    $GLOBALS['RECHAT_PREVIOUS_SPEAKER'] = $previous !== '' && in_array(pcv_scope_name_key($previous),
        array_map('pcv_scope_name_key', $others), true) ? $previous : ($others[0] ?? '');
}

// 0.1.14 turn plan (SHARMAT pin, wrap-up, turn spreading) for pair, group and free scenes.
$GLOBALS['PCV_TURN_PLAN'] = null;
$GLOBALS['PCV_TURN_GUIDANCE'] = [];
if (!$solo) {
    $members = pcvScopeMembers($scope);
    $spokenKeys = [];
    if (count($members) >= 3 && is_string($requestScope['config_id'] ?? null)) {
        $spokenKeys = pcv_scene_turns_record($requestScope['config_id'], $speaker, $members);
    }
    $plan = pcvSceneTurnPlan($scope, $speaker, $requestScope, pcvSharmatListenerPin(), $spokenKeys);
    $GLOBALS['PCV_TURN_PLAN'] = $plan;
    pcvApplyTurnPlanToChim($plan, $requestType);
    $GLOBALS['PCV_TURN_GUIDANCE'] = ['spread' => $plan['spread'], 'wrap_up' => $plan['wrap_up']];
    // The end of this hook rewrites PCV_REQUEST_SCOPE from $requestScope, so record the flag there.
    $requestScope['sharmat_listener'] = $plan['sharmat'];
}

$beforeAudience = $GLOBALS['CACHE_PEOPLE'] ?? ($GLOBALS['requestRoutingSnapshot']['audience'] ?? null);
$beforePresence = $GLOBALS['requestRoutingSnapshot']['present_actors'] ?? null;
try {
    $nearbyContext = pcvBuildScopeContext($scope, $speaker, $listener);
} catch (Throwable $error) {
    pcvRoutingLogException('context_pre', $error, $requestScope);
    throw $error;
}
$GLOBALS['PROMPT_NEARBY_SECTIONS'] = $nearbyContext;
// 0.1.16: opt-in scene actions narrow CHIM's enabled list (never widen it); without groups the scene stays talk-only.
$actionsOn = pcvPrepareSceneActions($scope);
if (!$actionsOn) {
    $GLOBALS['PROMPT_ACTIONS_LIST'] = '';
    $GLOBALS['actionsList'] = '';
}
if (!function_exists('chimRefreshJsonResponseState')) {
    pcvBlockRequest('Private Conversation cannot refresh action constraints; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
}
try {
    require_once __DIR__ . '/json_response_custom.php';
    // Register before the core resets its response templates and runs JSON_TEMPLATE hooks.
    chimRefreshJsonResponseState(false);
} catch (Throwable $error) {
    pcvRoutingLogException('context_pre', $error, $requestScope);
    throw $error;
}
$allowedActions = $GLOBALS['FUNC_LIST'] ?? null;
if (!is_array($allowedActions) || !pcvSceneActionListAllowed($allowedActions, $scope)) {
    pcvBlockRequest('Private Conversation action constraints could not be applied; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
}
$actionSchema = $GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']['action'] ?? null;
if (is_array($actionSchema) && array_key_exists('enum', $actionSchema)) {
    $allowedEnum = $actionSchema['enum'];
    if (!is_array($allowedEnum) || !pcvSceneActionListAllowed($allowedEnum, $scope)) {
        pcvBlockRequest('Private Conversation action constraints could not be applied; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
    }
}

$GLOBALS['FUNCTIONS_ARE_ENABLED'] = $actionsOn;
$routing = pcvScopeRoutingSnapshot([], $scope, $playerName);
$GLOBALS['CACHE_PEOPLE'] = $routing['audience'];
$GLOBALS['CACHE_PEOPLE_LIMITED'] = $routing['audience'];
if (is_array($GLOBALS['requestRoutingSnapshot'] ?? null)) {
    $GLOBALS['requestRoutingSnapshot'] = pcvScopeRoutingSnapshot(
        $GLOBALS['requestRoutingSnapshot'],
        $scope,
        $playerName
    );
}

$GLOBALS['PCV_REQUEST_SCOPE'] = array_replace($requestScope, [
    'status' => 'active', 'scope' => $scope, 'start' => false, 'route' => $route,
    'action_player' => $playerName,
]);
pcvRoutingLogDetail('context_pre', 'action_constraints_refreshed', $requestType, $requestScope, [
    'audience_before_count' => pcvRoutingAudienceCount($beforeAudience),
    'audience_after_count' => pcvRoutingAudienceCount($GLOBALS['CACHE_PEOPLE'] ?? null),
    'present_before_count' => pcvRoutingPresenceCount($beforePresence),
    'present_after_count' => 0,
]);
