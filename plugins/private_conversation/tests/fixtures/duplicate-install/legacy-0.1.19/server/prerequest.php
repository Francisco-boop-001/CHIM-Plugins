<?php
declare(strict_types=1);

$gameRequest = $GLOBALS['gameRequest'] ?? null;

require_once __DIR__ . '/scope.php';

$requestType = is_string($gameRequest[0] ?? null) ? $gameRequest[0] : '';
if ($requestType === '_speech') {
    $reflectionPath = __DIR__ . '/reflection.php';
    if (is_file($reflectionPath) && !is_link($reflectionPath)) {
        try {
            require_once $reflectionPath;
        } catch (Throwable $error) {
            pcv_log_exception('reflection.ack_error', 'error', 'failed', 'internal_error', $error, [
                'phase' => 'ack',
            ]);
            return;
        }
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

// Group scenes start with the auto or picked opener; pairs and solo start with actor A as before.
$speaker = $requestScope['opener_name'] ?? ($requestScope['scope']['actor_a'] ?? null);
$profileId = $requestScope['profile_id_opener'] ?? ($requestScope['profile_id_a'] ?? null);
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
// 0.1.14 SHARMAT compatibility: SHARMAT's prerequest ran before this one (alphabetical extension order) and computed
// its per-speaker state for the NPC CHIM chose. Remember whether PCV changes the speaker, so its listener pin is
// only honored when it belongs to the NPC who actually speaks.
$GLOBALS['PCV_REQUEST_SCOPE']['speaker_switched'] =
    pcv_scope_name_key(trim((string)($GLOBALS['HERIKA_NAME'] ?? ''))) !== pcv_scope_name_key($speaker);
try {
    $switched = chimSwitchActiveNpcProfile($speaker);
} catch (Throwable $error) {
    pcvRoutingLogException('prerequest', $error, $requestScope);
    throw $error;
}
if (!$switched) {
    pcvBlockRequest('Private Conversation could not select the starting NPC; request stopped for safety.', 'profile_switch_failed', 'prerequest', $requestScope, true);
}

// The eligible ordinary input starts with the opener (A for pairs and solo). This selects the generated
// responder only; the input remains an unattributed scene direction, never actor speech.
$GLOBALS['PCV_REQUEST_SCOPE']['start'] = false;
pcvRoutingLogDetail('prerequest', 'responder_selected', pcvRoutingLogCurrentType(), $requestScope);
