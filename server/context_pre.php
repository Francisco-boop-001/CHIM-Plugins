<?php
declare(strict_types=1);

require_once __DIR__ . '/scope.php';

$requestType = is_string($GLOBALS['gameRequest'][0] ?? null) ? $GLOBALS['gameRequest'][0] : 'other';
$requestScope = $GLOBALS['PCV_REQUEST_SCOPE'] ?? null;
$validStatuses = ['active', 'off', 'pending', 'unavailable', 'identity_unavailable', 'ignored'];
if (!is_array($requestScope) || !in_array($requestScope['status'] ?? null, $validStatuses, true)) {
    // Preserve the legacy active-pair guard for generated Standard event types
    // that preprocessing does not route. Solo reflection must remain tied to its
    // one armed ordinary input and cannot consume an unrelated generated turn.
    $managedRequestTypes = ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s', 'rechat', 'continue', 'continue_group'];
    if (in_array($requestType, $managedRequestTypes, true)
        || pcvEffectiveExecutionMode($GLOBALS['gameRequest'] ?? null) !== 'STANDARD') {
        return;
    }
    try {
        $resolvedScope = pcvReadResolvedScope();
    } catch (Throwable $error) {
        pcvRoutingLogStart($requestType);
        pcvRoutingLogException('context_pre', $error);
        throw $error;
    }
    if (in_array($resolvedScope['status'] ?? null, ['off', 'pending', 'identity_unavailable'], true)) {
        pcvRoutingLogDetail(
            'context_pre',
            ($resolvedScope['status'] ?? null) === 'pending' ? 'scope_pending'
                : (($resolvedScope['status'] ?? null) === 'off' ? 'scope_off' : 'identity_unavailable'),
            $requestType,
            $resolvedScope
        );
        return;
    }
    pcvRoutingLogSetState($resolvedScope);
    pcvRoutingLogStart($requestType);
    if (($resolvedScope['status'] ?? null) !== 'active' || !is_array($resolvedScope['scope'] ?? null)) {
        if (($resolvedScope['reason'] ?? null) === 'scene_not_eligible') {
            pcvBlockRequest('Private Conversation actors are no longer eligible; request stopped for safety.', 'scene_not_eligible', 'context_pre', $resolvedScope);
        }
        pcvBlockRequest('Private Conversation state or actors are unavailable; request stopped for safety.', 'state_unavailable', 'context_pre', $resolvedScope, true);
    }
    if (($resolvedScope['scope']['scene_mode'] ?? 'pair') === 'solo') {
        pcvBlockRequest('Solo reflection only runs on its armed ordinary Standard input.', 'solo_unrouted_request', 'context_pre', $resolvedScope);
    }
    $requestScope = array_replace($resolvedScope, [
        'status' => 'active', 'start' => false, 'route' => 'generated_event',
        'origin_request_type' => $requestType, 'origin_mode' => 'STANDARD',
    ]);
    $GLOBALS['PCV_REQUEST_SCOPE'] = $requestScope;
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
    pcvBlockRequest('Private Conversation selected an NPC outside the pair; request stopped for safety.', 'speaker_outside_pair', 'context_pre', $requestScope);
}
$GLOBALS['RECHAT_MODE'] = 'tight';
try {
    $playerName = pcv_current_player_name();
} catch (Throwable $error) {
    pcvRoutingLogException('context_pre', $error, $requestScope);
    throw $error;
}
if (!is_string($playerName) || trim($playerName) === '') {
    pcvBlockRequest('Private Conversation player identity is unavailable; request stopped for safety.', 'player_identity_unavailable', 'context_pre', $requestScope, true);
}
$listener = $solo ? '' : (strcasecmp($speaker, (string)$scope['actor_a']) === 0
    ? (string)$scope['actor_b'] : (string)$scope['actor_a']);
if (!$solo && in_array($requestType, ['rechat', 'continue', 'continue_group'], true)) {
    // Keep strict-rechat's previous-speaker override inside the selected pair.
    $GLOBALS['RECHAT_PREVIOUS_SPEAKER'] = $listener;
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
$GLOBALS['FUNCTIONS_ARE_ENABLED'] = false;
$GLOBALS['PROMPT_ACTIONS_LIST'] = '';
$GLOBALS['actionsList'] = '';
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
if (!is_array($allowedActions) || ($allowedActions !== [] && $allowedActions !== ['Talk'])) {
    pcvBlockRequest('Private Conversation action constraints could not be applied; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
}
$actionSchema = $GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']['action'] ?? null;
if (is_array($actionSchema) && array_key_exists('enum', $actionSchema)) {
    $allowedEnum = $actionSchema['enum'];
    if (!is_array($allowedEnum) || ($allowedEnum !== [] && $allowedEnum !== ['Talk'])) {
        pcvBlockRequest('Private Conversation action constraints could not be applied; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
    }
}

$GLOBALS['FUNCTIONS_ARE_ENABLED'] = false;
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
]);
pcvRoutingLogDetail('context_pre', 'action_constraints_refreshed', $requestType, $requestScope, [
    'audience_before_count' => pcvRoutingAudienceCount($beforeAudience),
    'audience_after_count' => pcvRoutingAudienceCount($GLOBALS['CACHE_PEOPLE'] ?? null),
    'present_before_count' => pcvRoutingPresenceCount($beforePresence),
    'present_after_count' => 0,
]);
