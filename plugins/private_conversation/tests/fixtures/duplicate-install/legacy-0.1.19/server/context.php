<?php
declare(strict_types=1);

require_once __DIR__ . '/scope.php';

$requestScope = $GLOBALS['PCV_REQUEST_SCOPE'] ?? null;
if (!is_array($requestScope) || ($requestScope['status'] ?? null) !== 'active'
    || !is_array($requestScope['scope'] ?? null)) {
    return;
}

pcvRoutingLogSetState($requestScope);
pcvRoutingLogStart(pcvRoutingLogCurrentType());
if (!pcvRequestScopeModeMatches($requestScope) || ($requestScope['origin_mode'] ?? null) !== 'STANDARD') {
    pcvBlockRequest('Private Conversation stopped because the execution mode changed during routing.', 'mode_changed', 'context', $requestScope);
}
$solo = ($requestScope['scope']['scene_mode'] ?? 'pair') === 'solo';
if (($solo && !pcvSoloReflectionRequest($requestScope))
    || (!$solo && !pcvPairRoutedRequest($requestScope))) {
    pcvBlockRequest('Private Conversation request origin is unavailable; request stopped for safety.', 'mode_changed', 'context', $requestScope);
}
$speaker = trim((string)($GLOBALS['HERIKA_NAME'] ?? ''));
if (!pcvScopeSpeakerAllowed($speaker, $requestScope['scope'])) {
    pcvBlockRequest('Private Conversation selected an NPC outside the scene; request stopped for safety.', 'speaker_outside_scene', 'context', $requestScope);
}

$head = $GLOBALS['head'] ?? null;
if (!is_array($head) || !function_exists('chimRemovePromptXmlBlock')) {
    pcvBlockRequest('Private Conversation could not remove action instructions; request stopped for safety.', 'context_unavailable', 'context', $requestScope, true);
}

$foundSystemPrompt = false;
foreach ($head as $index => $message) {
    if (!is_array($message) || ($message['role'] ?? null) !== 'system' || !is_string($message['content'] ?? null)) {
        continue;
    }
    try {
        $head[$index]['content'] = chimRemovePromptXmlBlock($message['content'], 'actions');
    } catch (Throwable $error) {
        pcvRoutingLogException('context', $error, $requestScope);
        throw $error;
    }
    $foundSystemPrompt = true;
}
if (!$foundSystemPrompt) {
    pcvBlockRequest('Private Conversation system context is unavailable; request stopped for safety.', 'context_unavailable', 'context', $requestScope, true);
}
$GLOBALS['head'] = $head;

$route = $requestScope['route'] ?? 'generated_event';
if (!in_array($route, ['scene_direction', 'player_speech', 'rechat_clamped', 'pair_continuation', 'solo_reflection', 'generated_event'], true)) {
    $route = 'generated_event';
}
$actorContext = pcvRoutingLogActorContext($requestScope);
$speakerId = pcvRoutingLogSpeakerId($GLOBALS['HERIKA_NAME'] ?? null, $requestScope);
$preparedContext = [
    'phase' => 'context',
    'route' => $route,
    'exclude_player' => ($requestScope['scope']['exclude_player'] ?? true) === true,
    'bystander_mode' => $requestScope['scope']['bystander_mode'] ?? 'exclude',
];
if ($speakerId !== null) {
    $preparedContext['speaker_id'] = $speakerId;
}
if (!$solo) {
    $preparedContext['member_count'] = count(pcvScopeMembers($requestScope['scope']));
    if (($requestScope['scope']['free'] ?? false) === true) {
        $preparedContext['free_scene'] = true;
    }
    // 0.1.14 roleplay settings and this turn's plan.
    $preparedContext['pace'] = in_array($requestScope['scope']['pace'] ?? null, ['short', 'long'], true) ? $requestScope['scope']['pace'] : 'normal';
    if (($GLOBALS['PCV_TURN_PLAN']['wrap_up'] ?? false) === true) {
        $preparedContext['wrap_up'] = true;
    }
    if (($requestScope['sharmat_listener'] ?? false) === true) {
        $preparedContext['sharmat_listener'] = true;
    }
}
if (is_string($requestScope['scope']['card'] ?? null) && $requestScope['scope']['card'] !== '') {
    $preparedContext['scene_card'] = true;
}
if (is_string($requestScope['opener_source'] ?? null) && ($requestScope['route'] ?? null) !== 'rechat_clamped') {
    $preparedContext['opener_source'] = $requestScope['opener_source'];
}
pcvRoutingLogDetail('context', 'action_instructions_removed', pcvRoutingLogCurrentType(), $requestScope);
if (empty($GLOBALS['PCV_ROUTING_LOG_TERMINAL'])) {
    $GLOBALS['PCV_ROUTING_LOG_TERMINAL'] = true;
    pcv_log_event('routing.request_prepared', 'info', 'ok', null, $preparedContext + $actorContext);
}
