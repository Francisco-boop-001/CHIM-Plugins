<?php
declare(strict_types=1);

require_once __DIR__ . '/scope.php';

/** Pin the generated listener to the scoped counterpart or solo native sentinel. */
function pcvCustomizeJsonResponseTemplate(): void
{
    $requestScope = $GLOBALS['PCV_REQUEST_SCOPE'] ?? null;
    if (!is_array($requestScope) || ($requestScope['status'] ?? null) !== 'active'
        || !is_array($requestScope['scope'] ?? null)) {
        return;
    }

    if (!pcvRequestScopeModeMatches($requestScope)) {
        pcvBlockRequest('Private Conversation stopped because the execution mode changed during routing.', 'mode_changed', 'context_pre', $requestScope);
    }

    $scope = $requestScope['scope'];
    $solo = ($scope['scene_mode'] ?? 'pair') === 'solo';
    $speaker = trim((string)($GLOBALS['HERIKA_NAME'] ?? ''));
    if (!pcvScopeSpeakerAllowed($speaker, $scope)) {
        pcvBlockRequest('Private Conversation selected an NPC outside the pair; request stopped for safety.', 'speaker_outside_pair', 'context_pre', $requestScope);
    }
    if ($solo) {
        if (!pcvSoloReflectionRequest($requestScope)) {
            pcvBlockRequest('Private Conversation request origin is unavailable; request stopped for safety.', 'mode_changed', 'context_pre', $requestScope);
        }
        $listener = 'explicit_disable_rechat';
    } else {
        if (!pcvPairRoutedRequest($requestScope)) {
            pcvBlockRequest('Private Conversation pair routing is unavailable; request stopped for safety.', 'mode_changed', 'context_pre', $requestScope);
        }
        $listener = strcasecmp($speaker, (string)$scope['actor_a']) === 0
            ? (string)$scope['actor_b'] : (string)$scope['actor_a'];
    }

    $responseSchema = $GLOBALS['structuredOutputTemplate'] ?? null;
    $schema = is_array($responseSchema)
        ? ($responseSchema['json_schema']['schema']['properties']['listener'] ?? null)
        : null;
    if (!is_array($schema)) {
        pcvBlockRequest('Private Conversation listener constraints are unavailable; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
    }
    $schema['type'] = 'string';
    $schema['enum'] = [$listener];
    $schema['description'] = $solo
        ? 'Use the fixed self-reflection listener value.'
        : 'Use the selected conversation counterpart as listener.';
    $responseSchema['json_schema']['schema']['properties']['listener'] = $schema;
    $GLOBALS['structuredOutputTemplate'] = $responseSchema;

    if (!is_array($GLOBALS['responseTemplate'] ?? null)) {
        pcvBlockRequest('Private Conversation listener template is unavailable; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
    }
    $encodedListener = json_encode($listener, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encodedListener)) {
        pcvBlockRequest('Private Conversation listener template is unavailable; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
    }
    $GLOBALS['responseTemplate']['listener'] = 'Use exactly this listener value: ' . $encodedListener . '.';
}

function pcvRegisterJsonResponseCustomizer(): void
{
    if (!is_array($GLOBALS['HOOKS'] ?? null)) {
        $GLOBALS['HOOKS'] = [];
    }
    if (!is_array($GLOBALS['HOOKS']['JSON_TEMPLATE'] ?? null)) {
        $GLOBALS['HOOKS']['JSON_TEMPLATE'] = [];
    }
    if (!in_array('pcvCustomizeJsonResponseTemplate', $GLOBALS['HOOKS']['JSON_TEMPLATE'], true)) {
        $GLOBALS['HOOKS']['JSON_TEMPLATE'][] = 'pcvCustomizeJsonResponseTemplate';
    }
}

pcvRegisterJsonResponseCustomizer();
