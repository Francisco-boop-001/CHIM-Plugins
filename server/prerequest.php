<?php
declare(strict_types=1);

$gameRequest = $GLOBALS['gameRequest'] ?? null;
if (is_array($gameRequest) && ($gameRequest[0] ?? null) === 'ext_pcv_presence') {
    require_once __DIR__ . '/state.php';
    try {
        pcv_capture_autonomous_presence_report(
            pcv_current_playthrough_key(),
            $gameRequest[3] ?? null,
            pcv_current_player_name(),
            $gameRequest[1] ?? null
        );
    } catch (Throwable $error) {
        pcv_invalidate_eligible_npcs();
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'presence_unavailable', $error, ['operation' => 'presence_capture']);
    }
    terminate();
    return;
}

require_once __DIR__ . '/scope.php';

$requestType = is_string($gameRequest[0] ?? null) ? $gameRequest[0] : '';
if ($requestType === '_speech') {
    $reflectionPath = __DIR__ . '/reflection.php';
    if (is_file($reflectionPath) && !is_link($reflectionPath)) {
        require_once $reflectionPath;
    }
    if (function_exists('pcvReflectionEvaluateAck')) {
        pcvReflectionEvaluateAck($gameRequest);
    }
    return;
}

$requestScope = $GLOBALS['PCV_REQUEST_SCOPE'] ?? null;
if (!is_array($requestScope)
    || ($requestScope['status'] ?? null) !== 'active'
    || ($requestScope['start'] ?? false) !== true) {
    return;
}

$speaker = $requestScope['scope']['actor_a'] ?? null;
$profileId = $requestScope['profile_id_a'] ?? null;
pcvRoutingLogSetState($requestScope);
pcvRoutingLogStart(pcvRoutingLogCurrentType());
if (!pcvRequestScopeModeMatches($requestScope)) {
    pcvBlockRequest('Private Conversation stopped because the execution mode changed during routing.', 'mode_changed', 'prerequest', $requestScope);
}
if (!in_array($requestScope['origin_request_type'] ?? null, ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'], true)
    || ($requestScope['origin_mode'] ?? null) !== 'STANDARD'
    || !in_array($requestScope['route'] ?? null, ['scene_direction', 'player_speech', 'solo_reflection'], true)) {
    pcvBlockRequest('Private Conversation request origin is unavailable; request stopped for safety.', 'mode_changed', 'prerequest', $requestScope);
}
if (!is_string($speaker) || $speaker === '' || !is_int($profileId) || $profileId < 1) {
    pcvBlockRequest('Private Conversation actors are unavailable; request stopped for safety.', 'actors_unavailable', 'prerequest', $requestScope, true);
}
if (!function_exists('chimSwitchActiveNpcProfile')) {
    pcvBlockRequest('Private Conversation could not select the starting NPC; request stopped for safety.', 'profile_switch_failed', 'prerequest', $requestScope, true);
}
try {
    $switched = chimSwitchActiveNpcProfile($speaker);
} catch (Throwable $error) {
    pcvRoutingLogException('prerequest', $error, $requestScope);
    throw $error;
}
if (!$switched) {
    pcvBlockRequest('Private Conversation could not select the starting NPC; request stopped for safety.', 'profile_switch_failed', 'prerequest', $requestScope, true);
}

// The eligible ordinary input always starts with actor A. This selects the generated
// responder only; the input remains an unattributed scene direction, never actor speech.
$GLOBALS['PCV_REQUEST_SCOPE']['start'] = false;
pcvRoutingLogDetail('prerequest', 'responder_selected', pcvRoutingLogCurrentType(), $requestScope);
