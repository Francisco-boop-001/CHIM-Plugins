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
        pcvBlockRequest('Private Conversation selected an NPC outside the scene; request stopped for safety.', 'speaker_outside_scene', 'context_pre', $requestScope);
    }
    if ($solo) {
        if (!pcvSoloReflectionRequest($requestScope)) {
            pcvBlockRequest('Private Conversation request origin is unavailable; request stopped for safety.', 'mode_changed', 'context_pre', $requestScope);
        }
        $listeners = ['explicit_disable_rechat'];
    } else {
        if (!pcvPairRoutedRequest($requestScope)) {
            pcvBlockRequest('Private Conversation pair routing is unavailable; request stopped for safety.', 'mode_changed', 'context_pre', $requestScope);
        }
        // CHIM runs PCV scenes in tight rechat mode: whoever this line addresses answers next.
        $listeners = pcvGroupListeners($speaker, $scope);
        // 0.1.14: this turn's plan (SHARMAT pin, wrap-up sentinel, turn spreading) may narrow the choices, but only
        // to other members or the closing sentinel; anything else keeps the scene's own listeners.
        $planned = $GLOBALS['PCV_TURN_PLAN']['listeners'] ?? null;
        if (is_array($planned) && $planned !== []) {
            $allowed = array_merge($listeners, ['explicit_disable_rechat']);
            if (array_diff($planned, $allowed) === []) {
                $listeners = array_values($planned);
            }
        }
        if ($listeners === []) {
            pcvBlockRequest('Private Conversation listener constraints are unavailable; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
        }
    }
    $listener = $listeners[0];

    $responseSchema = $GLOBALS['structuredOutputTemplate'] ?? null;
    $schema = is_array($responseSchema)
        ? ($responseSchema['json_schema']['schema']['properties']['listener'] ?? null)
        : null;
    if (!is_array($schema)) {
        pcvBlockRequest('Private Conversation listener constraints are unavailable; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
    }
    $schema['type'] = 'string';
    $schema['enum'] = $listeners;
    $schema['description'] = $solo
        ? 'Use the fixed self-reflection listener value.'
        : ($listeners === ['explicit_disable_rechat'] ? 'Use the fixed closing listener value; nobody answers this parting line.' : null);
    $schema['description'] ??= $solo
        ? 'Use the fixed self-reflection listener value.'
        : (count($listeners) === 1
            ? 'Use the selected conversation counterpart as listener.'
            : 'Use the member of this private conversation you are addressing as listener.');
    $responseSchema['json_schema']['schema']['properties']['listener'] = $schema;
    $GLOBALS['structuredOutputTemplate'] = $responseSchema;

    if (!is_array($GLOBALS['responseTemplate'] ?? null)) {
        pcvBlockRequest('Private Conversation listener template is unavailable; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
    }
    $encodedListener = json_encode(count($listeners) === 1 ? $listener : $listeners, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encodedListener)) {
        pcvBlockRequest('Private Conversation listener template is unavailable; request stopped for safety.', 'actions_unavailable', 'context_pre', $requestScope, true);
    }
    $GLOBALS['responseTemplate']['listener'] = count($listeners) === 1
        ? 'Use exactly this listener value: ' . $encodedListener . '.'
        : 'Use one of these listener values (whoever you are addressing): ' . $encodedListener . '.';
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
