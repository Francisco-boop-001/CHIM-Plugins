<?php
declare(strict_types=1);

require_once __DIR__ . '/scope.php';

$requestScope = $GLOBALS['PCV_REQUEST_SCOPE'] ?? null;
if (!is_array($requestScope) || ($requestScope['status'] ?? null) !== 'active') {
    return;
}

if (pcvPairRoutedRequest($requestScope)) {
    pcv_log_set_terminal('postrequest_observed', null, [
        'phase' => 'postrequest',
        'route' => $requestScope['route'],
        'actor_a_id' => $requestScope['actor_a_id'] ?? null,
        'actor_b_id' => $requestScope['actor_b_id'] ?? null,
    ]);
    return;
}
if (($requestScope['scope']['scene_mode'] ?? null) !== 'solo'
    || ($requestScope['route'] ?? null) !== 'solo_reflection') {
    return;
}

$eventContext = [
    'phase' => 'postrequest',
    'route' => 'solo_reflection',
    'actor_a_id' => $requestScope['actor_a_id'] ?? null,
];
if (!pcvSoloReflectionRequest($requestScope) || !pcvRequestScopeModeMatches($requestScope)
    || !pcvScopeSpeakerAllowed((string)($GLOBALS['HERIKA_NAME'] ?? ''), $requestScope['scope'])) {
    pcv_log_set_terminal('skipped', 'scope_ineligible', $eventContext);
    return;
}
pcv_log_set_terminal('postrequest_observed', null, [
    'phase' => 'postrequest',
    'route' => 'solo_reflection',
    'actor_a_id' => $requestScope['actor_a_id'] ?? null,
]);
