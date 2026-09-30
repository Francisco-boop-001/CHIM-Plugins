<?php
declare(strict_types=1);

if (!function_exists('pcv_current_playthrough_key')) {
    require_once __DIR__ . '/state.php';
}
if (!function_exists('pcvPrepareScopedInput')) {
    require_once __DIR__ . '/scope.php';
}

$gameRequest = $GLOBALS['gameRequest'] ?? null;
if (!is_array($gameRequest)) {
    return;
}

$requestType = (string)($gameRequest[0] ?? '');
if ($requestType === 'infonpc_close') {
    try {
        if (!function_exists('pcv_capture_background_presence_report')) {
            throw new RuntimeException('Background presence capture is unavailable.');
        }
        pcv_capture_background_presence_report(
            pcv_current_playthrough_key(),
            $gameRequest[3] ?? null,
            pcv_current_player_name(),
            $gameRequest[1] ?? null
        );
    } catch (Throwable $error) {
        pcv_invalidate_eligible_npcs();
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'presence_unavailable', $error, ['operation' => 'presence_capture']);
    }
    return;
}
$fastCommands = $GLOBALS['external_fast_commands'] ?? [];
if (!is_array($fastCommands)) {
    $fastCommands = [];
}
if ($requestType === 'ext_pcv_presence') {
    if (!in_array('ext_pcv_presence', $fastCommands, true)) {
        $fastCommands[] = 'ext_pcv_presence';
    }
    $GLOBALS['external_fast_commands'] = $fastCommands;
    return;
}
$ordinaryInputTypes = ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'];
$isOrdinaryInput = in_array($requestType, $ordinaryInputTypes, true);
$isIdentitySync = in_array($requestType, ['playerinfo', 'newgame'], true);
if ($isIdentitySync) {
    pcv_invalidate_eligible_npcs();
    return;
}
$continuationTypes = ['rechat', 'continue', 'continue_group'];
$isContinuation = in_array($requestType, $continuationTypes, true);
if (!$isOrdinaryInput && !$isContinuation) {
    return;
}
$mode = pcvEffectiveExecutionMode($gameRequest);
if ($mode === 'DIRECTOR') {
    // Director has a separate worker and is explicitly outside this extension's scope.
    pcvRoutingLogDetail('preprocessing', 'director_excluded', $requestType);
    return;
}

if ($mode !== 'STANDARD') {
    pcvRoutingLogStart($requestType);
    if ($mode === 'CLOSE' || $mode === 'WHISPER') {
        try {
            $modeKey = pcv_current_playthrough_key();
            $modeState = $modeKey === null ? null : pcv_read($modeKey);
        } catch (Throwable $error) {
            pcvRoutingLogException('preprocessing', $error);
            throw $error;
        }
        if (($modeState['status'] ?? null) === 'active') {
            pcvRoutingLogSetState([
                'config_id' => $modeState['config_id'] ?? null,
                'actor_a_id' => $modeState['scope']['actor_a'] ?? null,
                'actor_b_id' => $modeState['scope']['actor_b'] ?? null,
            ]);
            pcvBlockRequest('Private Conversation does not support Close or Whisper mode; request stopped for safety.', 'unsupported_special_mode', 'preprocessing');
        }
        if (($modeState['status'] ?? null) === 'unavailable' || ($modeKey === null && pcvScopeStoredStateExists())) {
            pcvBlockRequest('Private Conversation state is unavailable; request stopped for safety.', 'state_unavailable', 'preprocessing', null, true);
        }
    }
    $GLOBALS['PCV_REQUEST_SCOPE'] = ['status' => 'ignored', 'scope' => null, 'start' => false];
    pcv_log_event('routing.request_skipped', 'info', 'skipped', 'unsupported_mode', [
        'phase' => 'preprocessing',
        'request_type' => pcvRoutingLogType($requestType),
        'state_status' => 'off',
        'mode' => pcvRoutingLogMode($mode),
    ]);
    $GLOBALS['PCV_ROUTING_LOG_TERMINAL'] = true;
    pcvRoutingLogDetail('preprocessing', 'unsupported_mode', $requestType);
    return;
}

$currentPresence = null;
if ($isOrdinaryInput) {
    try {
        $presenceKey = pcv_current_playthrough_key();
    } catch (Throwable) {
        $presenceKey = null;
    }
    try {
        $currentPresence = pcv_capture_presence_snapshot($presenceKey, $gameRequest[4] ?? null);
    } catch (Throwable $error) {
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'presence_unavailable', $error, ['operation' => 'presence_capture']);
        $currentPresence = ['status' => 'unavailable', 'actors' => [], 'reason' => 'presence_unavailable'];
    }
}

$eligible = $isOrdinaryInput && $mode === 'STANDARD';
$snapshot = $GLOBALS['requestRoutingSnapshot'] ?? [];
if (!is_array($snapshot)) {
    $snapshot = [];
}
pcvRoutingLogStart($requestType);
try {
    $state = pcvBeginResolvedScope($eligible, $currentPresence, $isContinuation);
} catch (Throwable $error) {
    pcvRoutingLogException('preprocessing', $error);
    throw $error;
}
$status = $state['status'] ?? 'unavailable';
pcvRoutingLogSetState($state);
if ($status === 'identity_unavailable') {
    // With no validated playthrough key, no stored scope can be matched to this character.
    $GLOBALS['PCV_REQUEST_SCOPE'] = ['status' => 'identity_unavailable', 'scope' => null, 'start' => false];
    pcv_log_event('routing.request_skipped', 'info', 'skipped', 'identity_unavailable', [
        'phase' => 'preprocessing',
        'request_type' => pcvRoutingLogType($requestType),
        'state_status' => 'identity_unavailable',
        'mode' => pcvRoutingLogMode($mode),
    ]);
    $GLOBALS['PCV_ROUTING_LOG_TERMINAL'] = true;
    pcvRoutingLogDetail('preprocessing', 'identity_unavailable', $requestType);
    return;
}

if ($status === 'off' || $status === 'pending') {
    $GLOBALS['PCV_REQUEST_SCOPE'] = ['status' => $status, 'scope' => null, 'start' => false];
    $reason = $status === 'pending' ? 'scope_pending' : 'scope_off';
    pcv_log_event('routing.request_skipped', 'info', 'skipped', $reason, [
        'phase' => 'preprocessing',
        'request_type' => pcvRoutingLogType($requestType),
        'state_status' => $status,
        'mode' => pcvRoutingLogMode($mode),
    ]);
    $GLOBALS['PCV_ROUTING_LOG_TERMINAL'] = true;
    pcvRoutingLogDetail('preprocessing', $status === 'pending' ? 'scope_pending' : 'scope_off', $requestType, $state);
    return;
}
if ($status === 'unavailable' || !is_array($state['scope'] ?? null)) {
    $GLOBALS['PCV_REQUEST_SCOPE'] = ['status' => 'unavailable', 'scope' => null, 'start' => false];
    if (($state['reason'] ?? null) === 'scene_not_eligible') {
        pcvBlockRequest('Private Conversation actors are no longer eligible; request stopped for safety.', 'scene_not_eligible', 'preprocessing', $state);
    }
    pcvBlockRequest('Private Conversation is unavailable; request stopped for safety.', 'state_unavailable', 'preprocessing', $state, true);
}

if ($isContinuation && ($state['scope']['scene_mode'] ?? 'pair') === 'solo') {
    pcvBlockRequest('Solo reflection accepts one ordinary Standard input and does not continue through rechat.', 'solo_rechat_unsupported', 'preprocessing', $state);
}
if (in_array($requestType, ['continue', 'continue_group'], true)
    && ($state['scope']['scene_mode'] ?? 'pair') === 'pair'
    && ($state['scope']['exclude_player'] ?? true) === true) {
    // Core's continue prompt explicitly injects a player gesture; this would violate
    // the excluded-player contract even though the continuation speaker is scoped.
    pcvBlockRequest('This Private Conversation continuation prompt includes the player; request stopped for safety.', 'pair_continuation_player_excluded', 'preprocessing', $state);
}

try {
    $playerName = pcv_current_player_name();
} catch (Throwable $error) {
    pcvRoutingLogException('preprocessing', $error, $state);
    throw $error;
}
if (!is_string($playerName) || trim($playerName) === '') {
    pcvBlockRequest('Private Conversation player identity is unavailable; request stopped for safety.', 'player_identity_unavailable', 'preprocessing', $state, true);
}
$resolvedScope = $state['scope'];
$profileA = $state['profile_id_a'] ?? null;
if (!is_int($profileA) || $profileA < 1) {
    pcvBlockRequest('Private Conversation actor profile is unavailable; request stopped for safety.', 'actors_unavailable', 'preprocessing', $state, true);
}

if ($isContinuation) {
    if ($requestType === 'rechat') {
        $rawRechat = $gameRequest[3] ?? null;
        $failureReason = null;
        try {
            $clamped = pcvClampRechatActiveAgents(is_string($rawRechat) ? $rawRechat : null, $resolvedScope, (string)($GLOBALS['HERIKA_NAME'] ?? ''), $failureReason);
        } catch (Throwable $error) {
            pcvRoutingLogException('preprocessing', $error, $state);
            throw $error;
        }
        if ($clamped === null) {
            $reason = $failureReason === 'rechat_speaker_outside_pair' ? $failureReason : 'malformed_rechat';
            pcvBlockRequest('Private Conversation rechat payload or speaker is outside the selected pair; request stopped for safety.', $reason, 'preprocessing', $state);
        }
        $gameRequest[3] = $clamped;
        $GLOBALS['gameRequest'] = $gameRequest;
    }
    $routing = pcvScopeRoutingSnapshot($snapshot, $resolvedScope, $playerName);
    $GLOBALS['requestRoutingSnapshot'] = $routing;
    $route = $requestType === 'rechat' ? 'rechat_clamped' : 'pair_continuation';
    $decision = $requestType === 'rechat' ? 'rechat_clamped' : 'continuation_routed';
    $GLOBALS['PCV_REQUEST_SCOPE'] = array_replace($state, [
        'status' => 'active', 'scope' => $resolvedScope, 'start' => false,
        'profile_id_a' => $profileA, 'route' => $route,
        'origin_request_type' => $requestType, 'origin_mode' => $mode,
    ]);
    pcvRoutingLogDetail('preprocessing', $decision, $requestType, $state, [
        'audience_before_count' => pcvRoutingAudienceCount($snapshot['audience'] ?? null),
        'audience_after_count' => pcvRoutingAudienceCount($routing['audience'] ?? null),
        'present_before_count' => pcvRoutingPresenceCount($snapshot['present_actors'] ?? null),
        'present_after_count' => pcvRoutingPresenceCount($routing['present_actors'] ?? null),
    ]);
    return;
}

try {
    $prepared = pcvPrepareScopedInput($gameRequest, $snapshot, $resolvedScope, $playerName, $mode);
} catch (Throwable $error) {
    pcvRoutingLogException('preprocessing', $error, $state);
    throw $error;
}
if (($prepared['status'] ?? null) !== 'applied') {
    if (($prepared['status'] ?? null) === 'unavailable') {
        pcvBlockRequest('Private Conversation actors are unavailable; request stopped for safety.', 'actors_unavailable', 'preprocessing', $state, true);
    }
    $reason = $prepared['reason'] ?? 'invalid_input_prefix';
    if (!in_array($reason, ['unsupported_special_mode', 'invalid_input_prefix', 'empty_input', 'invalid_input_encoding'], true)) {
        $reason = 'invalid_input_prefix';
    }
    pcvBlockRequest('Private Conversation input could not be routed safely; request stopped.', $reason, 'preprocessing', $state);
}
$GLOBALS['gameRequest'] = $prepared['game_request'];
$GLOBALS['requestRoutingSnapshot'] = $prepared['routing_snapshot'];
$route = ($resolvedScope['scene_mode'] ?? 'pair') === 'solo'
    ? 'solo_reflection'
    : (($resolvedScope['exclude_player'] ?? true) ? 'scene_direction' : 'player_speech');
$baselineOutputLog = is_array($GLOBALS['DEBUG_DATA'] ?? null) && is_string($GLOBALS['DEBUG_DATA']['OUTPUT_LOG'] ?? null)
    ? $GLOBALS['DEBUG_DATA']['OUTPUT_LOG'] : '';
$GLOBALS['PCV_REQUEST_SCOPE'] = array_replace($state, [
    'status' => 'active', 'scope' => $resolvedScope, 'start' => true,
    'profile_id_a' => $profileA, 'route' => $route,
    'origin_request_type' => $requestType, 'origin_mode' => $mode,
    'origin_dialogue' => is_string($gameRequest[3] ?? null) ? $gameRequest[3] : null,
    'baseline_utterance_id' => is_string($GLOBALS['SCRIPTLINE_UTTERANCE_ID'] ?? null) ? $GLOBALS['SCRIPTLINE_UTTERANCE_ID'] : null,
    'baseline_output_log' => $baselineOutputLog,
]);
pcvRoutingLogDetail(
    'preprocessing',
    $route === 'solo_reflection' ? 'solo_reflection_routed'
        : ($route === 'scene_direction' ? 'input_rewritten' : 'player_speech_preserved'),
    $requestType,
    $state,
    [
        'audience_before_count' => pcvRoutingAudienceCount($snapshot['audience'] ?? null),
        'audience_after_count' => pcvRoutingAudienceCount($prepared['routing_snapshot']['audience'] ?? null),
        'present_before_count' => pcvRoutingPresenceCount($snapshot['present_actors'] ?? null),
        'present_after_count' => pcvRoutingPresenceCount($prepared['routing_snapshot']['present_actors'] ?? null),
    ]
);
