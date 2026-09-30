<?php

declare(strict_types=1);

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';

function scopeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class PcvHookFixtureAbort extends RuntimeException {}

function pcv_current_playthrough_key(): ?string
{
    return array_key_exists('pcv_fixture_key', $GLOBALS) ? $GLOBALS['pcv_fixture_key'] : 'fixture-key';
}

function pcv_current_player_name(): ?string
{
    return 'Player';
}

function pcv_read(string $key): array
{
    return $GLOBALS['pcv_fixture_state'];
}

function pcv_capture_presence_snapshot(?string $key, $raw): array
{
    $GLOBALS['pcv_fixture_capture_raw'][] = $raw;
    return $GLOBALS['pcv_fixture_current_presence'] ?? ['status' => 'unavailable', 'actors' => [], 'reason' => 'presence_missing'];
}

function pcv_read_eligible_npcs(?string $key, array $catalogRows, ?string $playerName, ?string $stateDirectory = null): array
{
    $GLOBALS['pcv_fixture_cache_read_calls'] = ($GLOBALS['pcv_fixture_cache_read_calls'] ?? 0) + 1;
    return $GLOBALS['pcv_fixture_cached_presence'] ?? ['status' => 'missing', 'known_npcs' => [], 'reason' => 'presence_missing'];
}

function pcvFixtureMapHasPair(?array $map, array $scope): bool
{
    if (!is_array($map) || !array_key_exists((string)($scope['actor_a'] ?? ''), $map)) {
        return false;
    }
    if (($scope['scene_mode'] ?? 'pair') === 'solo') {
        return ($scope['actor_b'] ?? null) === null;
    }
    return array_key_exists((string)($scope['actor_b'] ?? ''), $map);
}

function pcv_begin_request(string $key, bool $eligible, ?string $stateDirectory = null, ?array $eligibleNpcMap = null): array
{
    $GLOBALS['pcv_fixture_eligible'][] = $eligible;
    $GLOBALS['pcv_fixture_begin_maps'][] = $eligibleNpcMap;
    $state = $GLOBALS['pcv_fixture_state'];
    $pendingScope = $state['pending_scope'] ?? null;
    if ($eligible && is_array($pendingScope)) {
        if (($pendingScope['enabled'] ?? null) === false) {
            $state = ['status' => 'off', 'scope' => null, 'pending' => false];
            $GLOBALS['pcv_fixture_state'] = $state;
            return $state;
        }
        if (!pcvFixtureMapHasPair($eligibleNpcMap, $pendingScope)) {
            pcv_log_event('state.scope_skipped', 'info', 'skipped', 'scene_not_eligible', [
                'operation' => 'begin', 'scene_mode' => $pendingScope['scene_mode'] ?? 'pair',
            ]);
            return ['status' => 'unavailable', 'scope' => null, 'pending' => true,
                'pending_scope' => $pendingScope, 'reason' => 'scene_not_eligible'];
        }
        $state = [
            'status' => 'active', 'scope' => $pendingScope, 'pending' => false,
            'config_id' => $GLOBALS['pcv_fixture_activated_config_id'] ?? '123e4567-e89b-42d3-a456-426614174001',
        ];
        $GLOBALS['pcv_fixture_state'] = $state;
    }
    if (($state['status'] ?? null) === 'active' && !pcvFixtureMapHasPair($eligibleNpcMap, $state['scope'] ?? [])) {
        pcv_log_event('state.scope_skipped', 'info', 'skipped', 'scene_not_eligible', [
            'operation' => 'begin', 'scene_mode' => $state['scope']['scene_mode'] ?? 'pair',
        ]);
        return ['status' => 'unavailable', 'scope' => null, 'pending' => (bool)($state['pending'] ?? false),
            'config_id' => $state['config_id'] ?? null, 'reason' => 'scene_not_eligible'];
    }
    return $state;
}

final class NpcMaster
{
    public function getAll(): array
    {
        $GLOBALS['pcv_fixture_catalog_calls'] = ($GLOBALS['pcv_fixture_catalog_calls'] ?? 0) + 1;
        if (!empty($GLOBALS['pcv_fixture_catalog_throw'])) {
            throw new RuntimeException('fixture catalog failure');
        }
        return $GLOBALS['pcv_fixture_catalog_rows'] ?? [
            ['id' => '101', 'npc_name' => 'Aela', 'profile_id' => 9],
            ['id' => '202', 'npc_name' => 'Bryn', 'profile_id' => 10],
            ['id' => '303', 'npc_name' => 'Nazeem', 'profile_id' => 11],
        ];
    }
}

function chimSwitchActiveNpcProfile(string $name): bool
{
    if (!empty($GLOBALS['pcv_fixture_profile_throw'])) {
        throw new RuntimeException('fixture-only exception details');
    }
    $GLOBALS['pcv_fixture_profile_switch'] = $name;
    $GLOBALS['HERIKA_NAME'] = $name;
    return true;
}

function chimRemovePromptXmlBlock($content, string $tag)
{
    $tag = preg_quote($tag, '/');
    return preg_replace('/\n*<' . $tag . '>\s*.*?\s*<\/' . $tag . '>\n*/s', "\n", (string)$content);
}

function chimRefreshJsonResponseState(bool $loadExtensionCustomizers = false): void
{
    $GLOBALS['pcv_fixture_refresh_seen_enabled'] = $GLOBALS['FUNCTIONS_ARE_ENABLED'] ?? null;
    $GLOBALS['FUNC_LIST'] = [];
    $GLOBALS['responseTemplate'] = ['listener' => 'base listener description'];
    $GLOBALS['structuredOutputTemplate'] = ['json_schema' => ['schema' => ['properties' => [
        'action' => ['type' => 'string'], 'listener' => ['type' => 'string', 'description' => 'base listener'],
    ]]]];
    foreach ($GLOBALS['HOOKS']['JSON_TEMPLATE'] ?? [] as $hook) {
        if (is_callable($hook)) {
            $hook();
        }
    }
}

function terminate(): void
{
    throw new PcvHookFixtureAbort('request stopped');
}

function scopeCheckHookStops(string $hookPath): bool
{
    ob_start();
    try {
        include $hookPath;
    } catch (PcvHookFixtureAbort) {
        $stopped = true;
    } finally {
        ob_end_clean();
    }
    return $stopped ?? false;
}

function scopeCheckHookThrows(string $hookPath): ?Throwable
{
    try {
        include $hookPath;
    } catch (Throwable $error) {
        return $error;
    }
    return null;
}

function scopeCheckBeginSimulatedRequest(): void
{
    unset(
        $GLOBALS['PCV_ROUTING_LOG_STARTED'],
        $GLOBALS['PCV_ROUTING_LOG_TERMINAL'],
        $GLOBALS['PCV_ROUTING_LOG_REQUEST_TYPE'],
        $GLOBALS['PCV_ROUTING_LOG_ACTOR_IDS']
    );
    pcv_log_set_config_id(null);
}

function scopeCheckLogEntries(): array
{
    $logPath = pcv_log_path();
    if (!is_string($logPath) || !is_file($logPath) || is_link($logPath)) {
        return [];
    }
    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }
    return array_map(static fn(string $line): array => json_decode($line, true, 16, JSON_THROW_ON_ERROR), $lines);
}

function scopeCheckEventCount(string $event, ?string $reason = null): int
{
    return count(array_filter(scopeCheckLogEntries(), static fn(array $entry): bool =>
        ($entry['event'] ?? null) === $event && ($reason === null || ($entry['reason'] ?? null) === $reason)));
}

$scopeSource = dirname(__DIR__) . '/server/scope.php';
if (!is_file($scopeSource)) {
    fwrite(STDERR, "FAIL: ordinary scope helper is missing.\n");
    exit(1);
}
$logTestDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'private-conversation-scope-check-' . getmypid() . '-' . substr(hash('sha256', microtime(true) . ':' . getmypid()), 0, 12);
if (!@mkdir($logTestDirectory, 0700) && !is_dir($logTestDirectory)) {
    fwrite(STDERR, "FAIL: Could not create isolated logger fixture directory.\n");
    exit(1);
}
@chmod($logTestDirectory, 0700);
if (!pcv_log_set_test_directory($logTestDirectory)) {
    fwrite(STDERR, "FAIL: Logger rejected the isolated fixture directory.\n");
    exit(1);
}
register_shutdown_function(static function () use ($logTestDirectory): void {
    $directory = realpath($logTestDirectory);
    $tempRoot = realpath(sys_get_temp_dir());
    if ($directory === false || $tempRoot === false || !pcv_log_path_is_within($directory, $tempRoot)
        || basename($directory) !== basename($logTestDirectory) || is_link($logTestDirectory)) {
        return;
    }
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path) && !is_link($path)) {
            @unlink($path);
        }
    }
    @rmdir($directory);
});
require_once $scopeSource;

$postrequestFixtureScope = $logTestDirectory . DIRECTORY_SEPARATOR . 'scope.php';
$postrequestFixtureReflection = $logTestDirectory . DIRECTORY_SEPARATOR . 'reflection.php';
$postrequestFixtureHook = $logTestDirectory . DIRECTORY_SEPARATOR . 'postrequest.php';
$postrequestFixtureScopeSource = "<?php\nrequire_once " . var_export($scopeSource, true) . ";\n";
$postrequestFixtureReflectionSource = <<<'PHP'
<?php
function pcvReflectionRegisterLastOutput(array $requestScope): void
{
    $GLOBALS['pcv_fixture_reflection_register_calls'][] = $requestScope;
}
PHP;
if (file_put_contents($postrequestFixtureScope, $postrequestFixtureScopeSource) === false
    || file_put_contents($postrequestFixtureReflection, $postrequestFixtureReflectionSource) === false
    || !copy(dirname(__DIR__) . '/server/postrequest.php', $postrequestFixtureHook)) {
    fwrite(STDERR, "FAIL: Could not prepare isolated postrequest hook fixture.\n");
    exit(1);
}

try {
    $knownNpcs = [
        '101' => 'Aela',
        '202' => 'Bryn',
        '303' => 'Nazeem',
        '404' => 'Player',
    ];
    $storedScope = [
        'enabled' => true,
        'actor_a' => '101',
        'actor_b' => '202',
        'exclude_player' => true,
        'bystander_mode' => 'exclude',
    ];
    $scope = pcvResolveScopeNames($storedScope, $knownNpcs, 'Player');
    scopeCheck($scope === [
        'enabled' => true,
        'scene_mode' => 'pair',
        'actor_a' => 'Aela',
        'actor_b' => 'Bryn',
        'exclude_player' => true,
        'bystander_mode' => 'exclude',
    ], 'A valid stable-ID pair did not resolve to its current canonical names.');
    scopeCheck(pcvResolveScopeNames($storedScope, ['202' => 'Bryn'], 'Player') === null,
        'A missing selected NPC did not fail closed.');
    scopeCheck(pcvResolveScopeNames([
        'enabled' => true,
        'actor_a' => '404',
        'actor_b' => '202',
        'exclude_player' => true,
        'bystander_mode' => 'exclude',
    ], $knownNpcs, 'Player') === null, 'The player was accepted as one of the selected NPCs.');
    $pipeActor = $storedScope;
    $pipeActor['actor_a'] = '101';
    scopeCheck(pcvResolveScopeNames($pipeActor, ['101' => 'A|ela', '202' => 'Bryn'], 'Player') === null,
        'A protocol delimiter in a live actor name was accepted.');
    $soloStoredScope = [
        'enabled' => true,
        'scene_mode' => 'solo',
        'actor_a' => '101',
        'actor_b' => null,
        'exclude_player' => true,
        'bystander_mode' => 'silent',
    ];
    $soloScope = pcvResolveScopeNames($soloStoredScope, $knownNpcs, 'Player');
    scopeCheck($soloScope === [
        'enabled' => true,
        'scene_mode' => 'solo',
        'actor_a' => 'Aela',
        'actor_b' => null,
        'exclude_player' => true,
        'bystander_mode' => 'silent',
    ], 'A valid solo scope did not resolve one selected NPC with no second actor.');
    scopeCheck(pcvScopeSpeakerAllowed('Aela', $soloScope)
        && !pcvScopeSpeakerAllowed('Bryn', $soloScope)
        && !pcvScopeSpeakerAllowed('Player', $soloScope),
        'Solo reflection accepted a second NPC or the player as a speaker.');
    $soloPrepared = pcvPrepareScopedInput(
        ['inputtext', 't', 'g', 'Player: think about the old road.', 'raw'],
        ['audience' => '|Player|Aela|Bryn|', 'present_actors' => [['name' => 'Bryn']]],
        $soloScope,
        'Player',
        'STANDARD'
    );
    scopeCheck(($soloPrepared['status'] ?? null) === 'applied'
        && ($soloPrepared['game_request'][0] ?? null) === 'instruction'
        && ($soloPrepared['routing_snapshot']['audience'] ?? null) === '|Aela|'
        && ($soloPrepared['routing_snapshot']['present_actors'] ?? null) === [],
        'Solo reflection did not transform input to a singleton NPC audience.');
    $soloContext = pcvBuildScopeContext($soloScope, 'Aela', '');
    scopeCheck(str_contains($soloContext, 'thinking aloud')
        && str_contains($soloContext, 'silent scenery')
        && !str_contains($soloContext, 'Bryn') && !str_contains($soloContext, 'Player'),
        'Solo reflection context named a listener/player or lost the silent-bystander setting.');
    $uniqueNames = pcvScopeKnownNpcs([
        ['id' => '101', 'npc_name' => 'Aela', 'profile_id' => 9],
        ['id' => '102', 'npc_name' => 'aela', 'profile_id' => 12],
        ['id' => '202', 'npc_name' => 'Bryn', 'profile_id' => 10],
        ['id' => '303', 'npc_name' => 'Player', 'profile_id' => 11],
    ], 'Player');
    scopeCheck(!isset($uniqueNames['101'], $uniqueNames['102']) && ($uniqueNames['202'] ?? null) === 'Bryn',
        'The request-time catalog retained duplicate-name or player rows.');

    $request = ['inputtext', '1700000000', '1000000', 'Player: Meet at dusk: discuss the broken bridge.', 'client-json'];
    $snapshot = [
        'audience' => '|Player|Aela|Bryn|Nazeem|',
        'present_actors' => [['id' => '303', 'name' => 'Nazeem']],
        'unrelated' => 'preserved',
    ];
    $prepared = pcvPrepareScopedInput($request, $snapshot, $scope, 'Player', 'STANDARD');
    scopeCheck(($prepared['status'] ?? null) === 'applied', 'A valid STANDARD player input was not routed into the scoped scene.');
    scopeCheck(($prepared['game_request'][0] ?? null) === 'instruction', 'Player-excluded input did not use the existing scene-direction event path.');
    scopeCheck(($prepared['game_request'][3] ?? null) === 'Player: Meet at dusk: discuss the broken bridge.',
        'Normalization changed the direction after the exact player prefix or damaged later colons.');
    scopeCheck(($prepared['routing_snapshot']['audience'] ?? null) === '|Aela|Bryn|',
        'The authoritative event audience contains the player or a background NPC.');
    scopeCheck(($prepared['routing_snapshot']['present_actors'] ?? null) === [],
        'Physical bystanders remained in the current-turn presence snapshot.');
    scopeCheck(($prepared['routing_snapshot']['unrelated'] ?? null) === 'preserved',
        'Scoping discarded unrelated request metadata.');
    scopeCheck(($prepared['game_request'][1] ?? null) === '1700000000'
        && ($prepared['game_request'][2] ?? null) === '1000000', 'Scoping changed the request timestamps.');

    $unprefixed = pcvPrepareScopedInput(
        ['ginputtext', 't', 'g', 'Begin with a greeting.', 'raw'],
        [], $scope, 'Player', 'STANDARD'
    );
    scopeCheck(($unprefixed['status'] ?? null) === 'applied'
        && ($unprefixed['game_request'][3] ?? null) === 'Player: Begin with a greeting.',
        'Unprefixed input did not gain the exact known prefix required by the core instruction parser.');

    $ambiguousRequest = ['inputtext_s', 't', 'g', 'Someone Else: say hello.', 'raw'];
    $ambiguousSnapshot = ['audience' => '|Aela|Bryn|', 'present_actors' => []];
    $ambiguous = pcvPrepareScopedInput($ambiguousRequest, $ambiguousSnapshot, $scope, 'Player', 'STANDARD');
    scopeCheck(($ambiguous['status'] ?? null) === 'blocked'
        && ($ambiguous['game_request'] ?? null) === $ambiguousRequest
        && ($ambiguous['routing_snapshot'] ?? null) === $ambiguousSnapshot,
        'An unknown speaker prefix was stripped or synchronized instead of rejected unchanged.');

    $unsupportedMode = pcvPrepareScopedInput($request, $snapshot, $scope, 'Player', 'WHISPER');
    scopeCheck(($unsupportedMode['status'] ?? null) === 'blocked'
        && ($unsupportedMode['game_request'] ?? null) === $request,
        'An active pair silently continued through an unsupported special mode.');

    $normalRequest = ['inputtext', 't', 'g', 'Player: normal conversation.', 'raw'];
    $normalSnapshot = ['audience' => '|Player|C|', 'present_actors' => []];
    $off = pcvPrepareScopedInput($normalRequest, $normalSnapshot, ['enabled' => false], 'Player', 'STANDARD');
    scopeCheck(($off['status'] ?? null) === 'off'
        && ($off['game_request'] ?? null) === $normalRequest
        && ($off['routing_snapshot'] ?? null) === $normalSnapshot,
        'Disabled scope altered an ordinary request.');

    $includedScope = $scope;
    $includedScope['exclude_player'] = false;
    $included = pcvPrepareScopedInput($normalRequest, $normalSnapshot, $includedScope, 'Player', 'STANDARD');
    scopeCheck(($included['game_request'][0] ?? null) === 'inputtext'
        && ($included['game_request'][3] ?? null) === 'Player: normal conversation.'
        && ($included['routing_snapshot']['audience'] ?? null) === '|Player|Aela|Bryn|',
        'Included-player mode did not preserve ordinary player speech and audience.');
    scopeCheck(str_contains(pcvBuildScopeContext($includedScope, 'Aela', 'Bryn'), 'player speech')
        && !str_contains(pcvBuildScopeContext($includedScope, 'Aela', 'Bryn'), 'untrusted scene direction'),
        'Included-player context was mislabeled as excluded-player scene direction.');

    $rechatPayload = json_encode([
        'speaker' => 'Aela',
        'listener_hint' => 'Bryn',
        'rechat_target_hint' => 'Bryn',
        'chain_id' => 'turn-7',
        'active_agents' => ['Aela', 'Bryn', 'Nazeem'],
        'other' => ['keep' => true],
    ], JSON_THROW_ON_ERROR);
    $clampedPayload = pcvClampRechatActiveAgents($rechatPayload, $scope);
    $clamped = json_decode($clampedPayload ?? '', true, 32, JSON_THROW_ON_ERROR);
    scopeCheck(($clamped['active_agents'] ?? null) === ['Aela', 'Bryn'],
        'Rechat retained a nonparticipant as a possible speaker.');
    scopeCheck(($clamped['other']['keep'] ?? null) === true && ($clamped['chain_id'] ?? null) === 'turn-7',
        'Rechat clamp discarded unrelated continuation metadata.');

    scopeCheck(pcvScopeSpeakerAllowed('Aela', $scope) && pcvScopeSpeakerAllowed('Bryn', $scope),
        'A selected NPC was rejected by the pre-generation speaker guard.');
    scopeCheck(!pcvScopeSpeakerAllowed('Nazeem', $scope) && !pcvScopeSpeakerAllowed('Player', $scope),
        'The pre-generation speaker guard accepted a background NPC or the player.');

    $silentScope = $scope;
    $silentScope['bystander_mode'] = 'silent';
    $silentContext = pcvBuildScopeContext($silentScope, 'Aela', 'Bryn');
    scopeCheck(str_contains($silentContext, 'silent scenery')
        && !str_contains($silentContext, 'Nazeem')
        && !str_contains($silentContext, 'Player'),
        'Silent scenery was represented as actor membership or named the player/background NPC.');
    scopeCheck(str_contains(pcvBuildScopeContext($scope, 'Aela', 'Bryn'), 'Aela')
        && !str_contains(pcvBuildScopeContext($scope, 'Aela', 'Bryn'), 'Nazeem'),
        'Excluded background NPCs appeared in scoped context.');

    $hookDir = dirname(__DIR__) . '/server';
    $GLOBALS['pcv_fixture_key'] = 'fixture-key';
    $fixtureConfigId = '123e4567-e89b-42d3-a456-426614174000';
    $GLOBALS['pcv_fixture_activated_config_id'] = $fixtureConfigId;
    $GLOBALS['pcv_fixture_state'] = [
        'status' => 'pending', 'scope' => null, 'pending' => true,
        'pending_scope' => $storedScope, 'pending_config_id' => '123e4567-e89b-42d3-a456-426614174002',
    ];
    $GLOBALS['pcv_fixture_eligible'] = [];
    $GLOBALS['pcv_fixture_catalog_calls'] = 0;
    $GLOBALS['pcv_fixture_capture_raw'] = [];
    $GLOBALS['pcv_fixture_cache_read_calls'] = 0;
    $GLOBALS['pcv_fixture_current_presence'] = [
        'status' => 'ready',
        'actors' => [
            ['form_id' => 1, 'name' => 'Aela', 'distance' => 10],
            ['form_id' => 2, 'name' => 'Bryn', 'distance' => 20],
        ],
        'reason' => null,
    ];
    $GLOBALS['pcv_fixture_cached_presence'] = [
        'status' => 'ready', 'known_npcs' => ['101' => 'Aela', '202' => 'Bryn'], 'reason' => null,
    ];
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    $GLOBALS['gameRequest'] = $request;
    $GLOBALS['requestRoutingSnapshot'] = $snapshot;
    $GLOBALS['HERIKA_NAME'] = 'Nazeem';
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    scopeCheck(($GLOBALS['gameRequest'][0] ?? null) === 'instruction'
        && ($GLOBALS['gameRequest'][3] ?? null) === 'Player: Meet at dusk: discuss the broken bridge.'
        && ($GLOBALS['requestRoutingSnapshot']['audience'] ?? null) === '|Aela|Bryn|'
        && ($GLOBALS['requestRoutingSnapshot']['present_actors'] ?? null) === [],
        'The real preprocessing hook did not transform the eligible event and audience.');
    scopeCheck(($GLOBALS['PCV_REQUEST_SCOPE']['route'] ?? null) === 'scene_direction'
        && ($GLOBALS['PCV_REQUEST_SCOPE']['origin_request_type'] ?? null) === 'inputtext'
        && ($GLOBALS['PCV_REQUEST_SCOPE']['origin_dialogue'] ?? null) === 'Player: Meet at dusk: discuss the broken bridge.'
        && is_string($GLOBALS['PCV_REQUEST_SCOPE']['baseline_output_log'] ?? null),
        'The routed pair did not retain its private origin and pre-generation output baseline.');
    scopeCheck(($GLOBALS['pcv_fixture_eligible'] ?? null) === [true]
        && ($GLOBALS['pcv_fixture_catalog_calls'] ?? 0) === 1
        && ($GLOBALS['pcv_fixture_capture_raw'][0] ?? null) === 'client-json'
        && end($GLOBALS['pcv_fixture_begin_maps']) === ['101' => 'Aela', '202' => 'Bryn'],
        'The ordinary hook did not apply pending state at the eligible boundary with one live catalog snapshot.');

    include $hookDir . '/prerequest.php';
    scopeCheck(($GLOBALS['pcv_fixture_profile_switch'] ?? null) === 'Aela'
        && ($GLOBALS['HERIKA_NAME'] ?? null) === 'Aela',
        'The initial generated responder was not selected from the active pair.');
    $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
    $GLOBALS['FUNC_LIST'] = ['Travel_To'];
    $GLOBALS['PROMPT_ACTIONS_LIST'] = 'old action list';
    $GLOBALS['actionsList'] = 'old assembled actions';
    $GLOBALS['PROMPT_NEARBY_SECTIONS'] = 'old actor list with Nazeem';
    include $hookDir . '/context_pre.php';
    scopeCheck(($GLOBALS['FUNCTIONS_ARE_ENABLED'] ?? true) === false
        && ($GLOBALS['FUNC_LIST'] ?? null) === []
        && !array_key_exists('enum', $GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']['action'] ?? [])
        && ($GLOBALS['pcv_fixture_refresh_seen_enabled'] ?? null) === false,
        'The context hook did not rebuild the core-shaped action-free schema after disabling actions.');
    scopeCheck(($GLOBALS['CACHE_PEOPLE'] ?? null) === '|Aela|Bryn|'
        && ($GLOBALS['CACHE_PEOPLE_LIMITED'] ?? null) === '|Aela|Bryn|'
        && ($GLOBALS['PROMPT_NEARBY_SECTIONS'] ?? '') !== ''
        && !str_contains($GLOBALS['PROMPT_NEARBY_SECTIONS'], 'Nazeem')
        && !str_contains($GLOBALS['PROMPT_NEARBY_SECTIONS'], 'Player'),
        'The generation context retained a bystander/player or lost the selected pair.');
    scopeCheck(($GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']['listener']['enum'] ?? null) === ['Bryn']
        && str_contains((string)($GLOBALS['responseTemplate']['listener'] ?? ''), '"Bryn"')
        && ($GLOBALS['RECHAT_MODE'] ?? null) === 'tight',
        'The response hook did not constrain listener routing to the pair counterpart.');
    $GLOBALS['head'] = [
        ['role' => 'system', 'content' => "<actions>Travel_To</actions>\n" . $GLOBALS['PROMPT_NEARBY_SECTIONS']],
        ['role' => 'user', 'content' => 'Player previous history remains intact.'],
    ];
    include $hookDir . '/context.php';
    scopeCheck(!str_contains($GLOBALS['head'][0]['content'], '<actions>')
        && str_contains($GLOBALS['head'][0]['content'], 'Aela')
        && ($GLOBALS['head'][1]['content'] ?? null) === 'Player previous history remains intact.',
        'The final context hook did not remove only action instructions and preserve history.');
    $GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] = true;
    include $hookDir . '/prepostrequest.php';
    scopeCheck(($GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] ?? null) === true,
        'Pair output incorrectly disabled the normal relationship-system postrequest path.');

    $logPath = pcv_log_path();
    $logLines = is_string($logPath) && is_file($logPath)
        ? file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        : false;
    $logEntries = is_array($logLines)
        ? array_map(static fn(string $line): array => json_decode($line, true, 16, JSON_THROW_ON_ERROR), $logLines)
        : [];
    $preparedEntries = array_values(array_filter($logEntries, static fn(array $entry): bool => ($entry['event'] ?? null) === 'routing.request_prepared'));
    $startedEntries = array_values(array_filter($logEntries, static fn(array $entry): bool => ($entry['event'] ?? null) === 'routing.request_started'));
    $preparedContext = $preparedEntries[0]['context'] ?? [];
    scopeCheck(count($startedEntries) === 1 && count($preparedEntries) === 1
        && ($preparedEntries[0]['config_id'] ?? null) === $fixtureConfigId
        && ($preparedContext['phase'] ?? null) === 'context'
        && ($preparedContext['route'] ?? null) === 'scene_direction'
        && ($preparedContext['actor_a_id'] ?? null) === '101'
        && ($preparedContext['actor_b_id'] ?? null) === '202'
        && ($preparedContext['speaker_id'] ?? null) === '101',
        'Routing logs did not correlate the prepared pair, actual speaker, and configuration ID at the final context hook.');
    $logText = is_array($logLines) ? implode("\n", $logLines) : '';
    scopeCheck(is_array($logLines) && !str_contains($logText, 'Meet at dusk')
        && !str_contains($logText, 'Aela') && !str_contains($logText, 'Bryn')
        && !str_contains($logText, 'Nazeem') && !str_contains($logText, 'Player'),
        'Routing logs included dialogue, prompt content, or display names.');

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    $GLOBALS['PLAYER_RESPEECH'] = true;
    $GLOBALS['pcv_fixture_eligible'] = [];
    $captureCountBeforeShortcut = count($GLOBALS['pcv_fixture_capture_raw']);
    $legacyShortcut = ['inputtext', 't', 'g', '** begin an autochat command', 'raw'];
    $GLOBALS['gameRequest'] = $legacyShortcut;
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    scopeCheck(($GLOBALS['gameRequest'] ?? null) === $legacyShortcut
        && ($GLOBALS['PCV_REQUEST_SCOPE']['status'] ?? null) === 'ignored'
        && ($GLOBALS['pcv_fixture_eligible'] ?? null) === []
        && count($GLOBALS['pcv_fixture_capture_raw']) === $captureCountBeforeShortcut,
        'A legacy Standard-plus-PLAYER_RESPEECH ** shortcut mutated presence or active routing before becoming AutoChat.');
    unset($GLOBALS['PLAYER_RESPEECH']);

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    $GLOBALS['gameRequest'] = $normalRequest;
    $GLOBALS['HERIKA_NAME'] = 'Nazeem';
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'AUTOCHAT';
    scopeCheck(scopeCheckHookStops($hookDir . '/prerequest.php')
        && ($GLOBALS['HERIKA_NAME'] ?? null) === 'Nazeem',
        'A late effective-mode change selected the scoped responder before the prerequest guard.');
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';

    scopeCheckBeginSimulatedRequest();
    $cacheReadsBefore = $GLOBALS['pcv_fixture_cache_read_calls'];
    $scopeSkipBefore = scopeCheckEventCount('state.scope_skipped', 'scene_not_eligible');
    $requestSkipBefore = scopeCheckEventCount('routing.request_skipped', 'scene_not_eligible');
    $GLOBALS['pcv_fixture_current_presence'] = ['status' => 'empty', 'actors' => [], 'reason' => null];
    $GLOBALS['gameRequest'] = $normalRequest;
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    scopeCheck(scopeCheckHookStops($hookDir . '/preprocessing.php')
        && end($GLOBALS['pcv_fixture_begin_maps']) === []
        && $GLOBALS['pcv_fixture_cache_read_calls'] === $cacheReadsBefore
        && scopeCheckEventCount('state.scope_skipped', 'scene_not_eligible') === $scopeSkipBefore + 1
        && scopeCheckEventCount('routing.request_skipped', 'scene_not_eligible') === $requestSkipBefore + 1
        && scopeCheckEventCount('routing.request_blocked', 'scene_not_eligible') === 0,
        'A cached good pair overrode the current ordinary report that had no eligible actors.');

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['pcv_fixture_state'] = [
        'status' => 'pending', 'scope' => null, 'pending' => true,
        'pending_scope' => $storedScope, 'pending_config_id' => '123e4567-e89b-42d3-a456-426614174002',
    ];
    $GLOBALS['pcv_fixture_current_presence'] = ['status' => 'empty', 'actors' => [], 'reason' => null];
    $GLOBALS['gameRequest'] = $normalRequest;
    $scopeSkipBefore = scopeCheckEventCount('state.scope_skipped', 'scene_not_eligible');
    $requestSkipBefore = scopeCheckEventCount('routing.request_skipped', 'scene_not_eligible');
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    scopeCheck(scopeCheckHookStops($hookDir . '/preprocessing.php')
        && ($GLOBALS['pcv_fixture_state']['status'] ?? null) === 'pending'
        && end($GLOBALS['pcv_fixture_begin_maps']) === []
        && scopeCheckEventCount('state.scope_skipped', 'scene_not_eligible') === $scopeSkipBefore + 1
        && scopeCheckEventCount('routing.request_skipped', 'scene_not_eligible') === $requestSkipBefore + 1,
        'An ordinary report with no managed in-range actors promoted the pending pair.');

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $storedScope, 'pending' => false, 'config_id' => $fixtureConfigId];
    $GLOBALS['pcv_fixture_current_presence'] = [
        'status' => 'ready',
        'actors' => [['form_id' => 1, 'name' => 'Aela', 'distance' => 10], ['form_id' => 2, 'name' => 'Bryn', 'distance' => 20]],
        'reason' => null,
    ];

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['pcv_fixture_eligible'] = [];
    $GLOBALS['HERIKA_NAME'] = 'Bryn';
    $GLOBALS['gameRequest'] = ['rechat', 't', 'g', $rechatPayload, 'raw'];
    $GLOBALS['requestRoutingSnapshot'] = $snapshot;
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    $hookRechat = json_decode($GLOBALS['gameRequest'][3], true, 32, JSON_THROW_ON_ERROR);
    scopeCheck(($GLOBALS['pcv_fixture_eligible'] ?? null) === [false]
        && ($hookRechat['active_agents'] ?? null) === ['Aela', 'Bryn']
        && ($hookRechat['other']['keep'] ?? null) === true
        && ($GLOBALS['requestRoutingSnapshot']['audience'] ?? null) === '|Aela|Bryn|',
        'The real rechat hook did not clamp speakers while preserving continuation fields.');
    include $hookDir . '/context_pre.php';
    scopeCheck(($GLOBALS['RECHAT_MODE'] ?? null) === 'tight'
        && ($GLOBALS['RECHAT_PREVIOUS_SPEAKER'] ?? null) === 'Aela',
        'Strict pair rechat was not constrained to the selected counterpart.');
    $GLOBALS['head'] = [['role' => 'system', 'content' => $GLOBALS['PROMPT_NEARBY_SECTIONS'] . "\n<actions>Talk</actions>"]];
    include $hookDir . '/context.php';
    $rechatPrepared = array_values(array_filter(scopeCheckLogEntries(), static fn(array $entry): bool => ($entry['event'] ?? null) === 'routing.request_prepared' && ($entry['context']['route'] ?? null) === 'rechat_clamped'));
    scopeCheck(($rechatPrepared[0]['context']['speaker_id'] ?? null) === '202',
        'The actual rechat path did not log its clamped route and selected NPC ID at final context preparation.');

    scopeCheckBeginSimulatedRequest();
    $includedStoredScope = $storedScope;
    $includedStoredScope['exclude_player'] = false;
    $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $includedStoredScope, 'pending' => false, 'config_id' => $fixtureConfigId];
    $continuePayload = 'core continuation payload is not rechat JSON';
    $GLOBALS['gameRequest'] = ['continue', 't', 'g', $continuePayload, 'raw'];
    $GLOBALS['HERIKA_NAME'] = 'Aela';
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    scopeCheck(($GLOBALS['gameRequest'][3] ?? null) === $continuePayload
        && ($GLOBALS['PCV_REQUEST_SCOPE']['route'] ?? null) === 'pair_continuation',
        'Pair continue was parsed as rechat JSON or its source payload was rewritten.');
    include $hookDir . '/context_pre.php';
    scopeCheck(($GLOBALS['RECHAT_PREVIOUS_SPEAKER'] ?? null) === 'Bryn'
        && str_contains((string)($GLOBALS['PROMPT_NEARBY_SECTIONS'] ?? ''), 'player is included'),
        'Player-included pair continuation lost its selected previous speaker or participant context.');
    $GLOBALS['head'] = [['role' => 'system', 'content' => $GLOBALS['PROMPT_NEARBY_SECTIONS'] . "\n<actions>Talk</actions>"]];
    include $hookDir . '/context.php';

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $storedScope, 'pending' => false, 'config_id' => $fixtureConfigId];
    $GLOBALS['gameRequest'] = ['continue_group', 't', 'g', 'continue-group prompt', 'raw'];
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    scopeCheck(scopeCheckHookStops($hookDir . '/preprocessing.php'),
        'Excluded-player continue_group passed despite core injecting a player gesture.');
    $excludedContinueEntries = array_values(array_filter(scopeCheckLogEntries(), static fn(array $entry): bool =>
        ($entry['event'] ?? null) === 'routing.request_blocked' && ($entry['reason'] ?? null) === 'pair_continuation_player_excluded'));
    scopeCheck(($excludedContinueEntries[0]['context']['phase'] ?? null) === 'preprocessing',
        'Excluded-player continuation block was not attributed to its early core prompt path.');

    scopeCheckBeginSimulatedRequest();
    $staleScopeSkipBefore = scopeCheckEventCount('state.scope_skipped', 'scene_not_eligible');
    $staleRequestSkipBefore = scopeCheckEventCount('routing.request_skipped', 'scene_not_eligible');
    $GLOBALS['pcv_fixture_cached_presence'] = ['status' => 'stale', 'known_npcs' => [], 'reason' => 'presence_stale'];
    $GLOBALS['gameRequest'] = ['rechat', 't', 'g', $rechatPayload, 'raw'];
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    scopeCheck(scopeCheckHookStops($hookDir . '/preprocessing.php'),
        'An active rechat continued after its presence report aged past the cache window.');
    $staleRequestEntries = array_values(array_filter(scopeCheckLogEntries(), static fn(array $entry): bool =>
        ($entry['event'] ?? null) === 'routing.request_skipped' && ($entry['reason'] ?? null) === 'scene_not_eligible'));
    scopeCheck(scopeCheckEventCount('state.scope_skipped', 'scene_not_eligible') === $staleScopeSkipBefore + 1
        && scopeCheckEventCount('routing.request_skipped', 'scene_not_eligible') === $staleRequestSkipBefore + 1
        && ($staleRequestEntries[count($staleRequestEntries) - 1]['severity'] ?? null) === 'info',
        'A stale rechat was not stopped with bounded informational ineligibility diagnostics.');
    $GLOBALS['pcv_fixture_cached_presence'] = [
        'status' => 'ready', 'known_npcs' => ['101' => 'Aela', '202' => 'Bryn'], 'reason' => null,
    ];

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['pcv_fixture_state'] = ['status' => 'off', 'scope' => null, 'pending' => false];
    $GLOBALS['pcv_fixture_catalog_calls'] = 0;
    $GLOBALS['gameRequest'] = $normalRequest;
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    scopeCheck(($GLOBALS['gameRequest'] ?? null) === $normalRequest
        && ($GLOBALS['pcv_fixture_catalog_calls'] ?? 0) === 0,
        'OFF state consulted the catalog or changed an ordinary request.');

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['pcv_fixture_state'] = [
        'status' => 'active', 'scope' => $storedScope, 'pending' => true,
        'pending_scope' => ['enabled' => false, 'actor_a' => '', 'actor_b' => '', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
        'config_id' => $fixtureConfigId,
    ];
    $GLOBALS['pcv_fixture_catalog_calls'] = 0;
    $GLOBALS['pcv_fixture_catalog_throw'] = true;
    $GLOBALS['gameRequest'] = $normalRequest;
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    unset($GLOBALS['pcv_fixture_catalog_throw']);
    scopeCheck(($GLOBALS['pcv_fixture_state']['status'] ?? null) === 'off'
        && ($GLOBALS['gameRequest'] ?? null) === $normalRequest
        && ($GLOBALS['pcv_fixture_catalog_calls'] ?? 0) === 0,
        'END depended on catalog availability or rewrote the ordinary input.');
    $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $storedScope, 'pending' => false, 'config_id' => $fixtureConfigId];

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['gameRequest'] = ['inputtext', 't', 'g', '{"speaker":"Nazeem","origin_line":"ordinary input"}', 'raw'];
    $GLOBALS['requestRoutingSnapshot'] = $snapshot;
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    scopeCheck(($GLOBALS['gameRequest'][0] ?? null) === 'instruction'
        && str_starts_with((string)($GLOBALS['gameRequest'][3] ?? ''), 'Player: {"speaker"'),
        'Ordinary JSON text was mistaken for the server-side rechat request type.');
    include $hookDir . '/context_pre.php';
    $GLOBALS['head'] = [['role' => 'system', 'content' => $GLOBALS['PROMPT_NEARBY_SECTIONS'] . "\n<actions>Talk</actions>"]];
    include $hookDir . '/context.php';

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['gameRequest'] = ['rechat', 't', 'g', '{malformed', 'raw'];
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    scopeCheck(scopeCheckHookStops($hookDir . '/preprocessing.php'),
        'Malformed active rechat did not fail closed.');
    $blockedEntries = array_values(array_filter(scopeCheckLogEntries(), static fn(array $entry): bool => ($entry['event'] ?? null) === 'routing.request_blocked' && ($entry['reason'] ?? null) === 'malformed_rechat'));
    scopeCheck(($blockedEntries[0]['context']['phase'] ?? null) === 'preprocessing'
        && ($blockedEntries[0]['context']['actor_a_id'] ?? null) === '101'
        && ($blockedEntries[0]['context']['actor_b_id'] ?? null) === '202',
        'The malformed-rechat failure did not produce a bounded routing block record.');

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['gameRequest'] = ['inputtext', 't', 'g', 'Player: close mode', 'raw'];
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'CLOSE';
    $GLOBALS['pcv_fixture_current_presence'] = ['status' => 'unavailable', 'actors' => [], 'reason' => 'unsupported_mode'];
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    scopeCheck(scopeCheckHookStops($hookDir . '/preprocessing.php'),
        'Active Close mode did not fail closed.');
    $closeBlocked = array_values(array_filter(scopeCheckLogEntries(), static fn(array $entry): bool =>
        ($entry['event'] ?? null) === 'routing.request_blocked' && ($entry['reason'] ?? null) === 'unsupported_special_mode'));
    scopeCheck(($closeBlocked[0]['context']['phase'] ?? null) === 'preprocessing',
        'Active Close mode was misdiagnosed as an actor-presence failure.');

    foreach ([['pair', $storedScope], ['solo', $soloStoredScope]] as [$sceneLabel, $sceneScope]) {
        foreach (['CLOSE', 'WHISPER'] as $specialMode) {
            scopeCheckBeginSimulatedRequest();
            $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $sceneScope, 'pending' => false, 'config_id' => $fixtureConfigId];
            $GLOBALS['CHIM_EXECUTION_MODE'] = $specialMode;
            $GLOBALS['gameRequest'] = $normalRequest;
            $GLOBALS['HERIKA_NAME'] = 'Nazeem';
            $capturesBefore = count($GLOBALS['pcv_fixture_capture_raw']);
            unset($GLOBALS['PCV_REQUEST_SCOPE']);
            scopeCheck(scopeCheckHookStops($hookDir . '/preprocessing.php')
                && ($GLOBALS['gameRequest'] ?? null) === $normalRequest
                && ($GLOBALS['HERIKA_NAME'] ?? null) === 'Nazeem'
                && count($GLOBALS['pcv_fixture_capture_raw']) === $capturesBefore,
                "Active {$sceneLabel} {$specialMode} changed presence, profile, or request before fail-closed handling.");
        }
    }

    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    $GLOBALS['pcv_fixture_current_presence'] = [
        'status' => 'ready',
        'actors' => [['form_id' => 1, 'name' => 'Aela', 'distance' => 10], ['form_id' => 2, 'name' => 'Bryn', 'distance' => 20]],
        'reason' => null,
    ];

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $soloStoredScope, 'pending' => false, 'config_id' => $fixtureConfigId];
    $GLOBALS['gameRequest'] = $normalRequest;
    $GLOBALS['requestRoutingSnapshot'] = $snapshot;
    $GLOBALS['HERIKA_NAME'] = 'Nazeem';
    $GLOBALS['DEBUG_DATA'] = ['OUTPUT_LOG' => 'pre-generation log marker'];
    $GLOBALS['SCRIPTLINE_UTTERANCE_ID'] = 'utt_baseline_12345678';
    unset($GLOBALS['PCV_REQUEST_SCOPE'], $GLOBALS['RECHAT_PREVIOUS_SPEAKER']);
    include $hookDir . '/preprocessing.php';
    scopeCheck(($GLOBALS['PCV_REQUEST_SCOPE']['route'] ?? null) === 'solo_reflection'
        && array_key_exists('actor_b', $GLOBALS['PCV_REQUEST_SCOPE']['scope'] ?? [])
        && $GLOBALS['PCV_REQUEST_SCOPE']['scope']['actor_b'] === null
        && ($GLOBALS['gameRequest'][0] ?? null) === 'instruction'
        && ($GLOBALS['requestRoutingSnapshot']['audience'] ?? null) === '|Aela|',
        'The real preprocessor did not arm exactly one solo reflection actor.');
    include $hookDir . '/prerequest.php';
    $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
    $GLOBALS['FUNC_LIST'] = ['Travel_To'];
    $GLOBALS['RECHAT_MODE'] = 'open';
    include $hookDir . '/context_pre.php';
    scopeCheck(($GLOBALS['HERIKA_NAME'] ?? null) === 'Aela'
        && ($GLOBALS['CACHE_PEOPLE'] ?? null) === '|Aela|'
        && str_contains((string)($GLOBALS['PROMPT_NEARBY_SECTIONS'] ?? ''), 'thinking aloud')
        && !str_contains((string)($GLOBALS['PROMPT_NEARBY_SECTIONS'] ?? ''), 'Player')
        && ($GLOBALS['RECHAT_MODE'] ?? null) === 'tight'
        && ($GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']['listener']['enum'] ?? null) === ['explicit_disable_rechat']
        && str_contains((string)($GLOBALS['responseTemplate']['listener'] ?? ''), '"explicit_disable_rechat"'),
        'The real context hook did not constrain solo reflection to the single actor and native disable-rechat sentinel.');
    $GLOBALS['head'] = [['role' => 'system', 'content' => $GLOBALS['PROMPT_NEARBY_SECTIONS'] . "\n<actions>Talk</actions>"],
        ['role' => 'user', 'content' => 'Historical player input remains in existing history.']];
    include $hookDir . '/context.php';
    scopeCheck(!str_contains($GLOBALS['head'][0]['content'], '<actions>')
        && ($GLOBALS['head'][1]['content'] ?? null) === 'Historical player input remains in existing history.',
        'Solo reflection destroyed prior history rather than applying only the current scoped context changes.');
    $GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] = true;
    include $hookDir . '/prepostrequest.php';
    scopeCheck(($GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] ?? null) === false,
        'Solo sentinel reached core postrequest without suppressing the fictitious NPC/NPC relationship queue.');
    $GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] = true;
    include $postrequestFixtureHook;
    $reflectionCalls = $GLOBALS['pcv_fixture_reflection_register_calls'] ?? [];
    scopeCheck(count($reflectionCalls) === 1
        && ($reflectionCalls[0]['route'] ?? null) === 'solo_reflection'
        && ($reflectionCalls[0]['baseline_utterance_id'] ?? null) === 'utt_baseline_12345678'
        && ($reflectionCalls[0]['baseline_output_log'] ?? null) === 'pre-generation log marker'
        && scopeCheckEventCount('reflection.output_registered') === 0,
        'The postrequest hook did not call the registry boundary with the current solo scope and private baseline.');
    $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $storedScope, 'pending' => false, 'config_id' => $fixtureConfigId];

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['gameRequest'] = ['instruction', 't', 'g', 'an unrelated internal instruction', 'raw'];
    $GLOBALS['HERIKA_NAME'] = 'Nazeem';
    $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
    $GLOBALS['PROMPT_NEARBY_SECTIONS'] = 'unmodified internal prompt';
    $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $soloStoredScope, 'pending' => false, 'config_id' => $fixtureConfigId];
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    scopeCheck(scopeCheckHookStops($hookDir . '/preprocessing.php') === false
        && scopeCheckHookStops($hookDir . '/context_pre.php')
        && ($GLOBALS['HERIKA_NAME'] ?? null) === 'Nazeem'
        && ($GLOBALS['FUNCTIONS_ARE_ENABLED'] ?? null) === true
        && ($GLOBALS['PROMPT_NEARBY_SECTIONS'] ?? null) === 'unmodified internal prompt',
        'An unstamped internal instruction bypassed the active solo-turn boundary or mutated profile/context before rejection.');

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['gameRequest'] = ['instruction', 't', 'g', 'an eligible pair event', 'raw'];
    $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $storedScope, 'pending' => false, 'config_id' => $fixtureConfigId];
    $GLOBALS['HERIKA_NAME'] = 'Aela';
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    scopeCheck(scopeCheckHookStops($hookDir . '/preprocessing.php') === false
        && scopeCheckHookStops($hookDir . '/context_pre.php') === false
        && ($GLOBALS['PCV_REQUEST_SCOPE']['route'] ?? null) === 'generated_event'
        && ($GLOBALS['CACHE_PEOPLE'] ?? null) === '|Aela|Bryn|'
        && ($GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']['listener']['enum'] ?? null) === ['Bryn'],
        'An untagged Standard core instruction did not retain the legacy active-pair context guard: '
            . json_encode([
                'scope' => $GLOBALS['PCV_REQUEST_SCOPE'] ?? null,
                'audience' => $GLOBALS['CACHE_PEOPLE'] ?? null,
                'listener_enum' => $GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']['listener']['enum'] ?? null,
                'stopped' => $GLOBALS['pcv_fixture_last_stop'] ?? null,
            ], JSON_PARTIAL_OUTPUT_ON_ERROR));
    $GLOBALS['head'] = [['role' => 'system', 'content' => $GLOBALS['PROMPT_NEARBY_SECTIONS'] . "\n<actions>Talk</actions>"]];
    include $hookDir . '/context.php';

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['gameRequest'] = ['instruction', 't', 'g', 'stale pair presence event', 'raw'];
    $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $storedScope, 'pending' => false, 'config_id' => $fixtureConfigId];
    $GLOBALS['pcv_fixture_cached_presence'] = ['status' => 'stale', 'known_npcs' => [], 'reason' => 'presence_stale'];
    $GLOBALS['HERIKA_NAME'] = 'Aela';
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    $scopeSkipBefore = scopeCheckEventCount('state.scope_skipped', 'scene_not_eligible');
    $requestSkipBefore = scopeCheckEventCount('routing.request_skipped', 'scene_not_eligible');
    $requestErrorBefore = scopeCheckEventCount('routing.request_error');
    scopeCheck(scopeCheckHookStops($hookDir . '/context_pre.php')
        && scopeCheckEventCount('state.scope_skipped', 'scene_not_eligible') === $scopeSkipBefore + 1
        && scopeCheckEventCount('routing.request_skipped', 'scene_not_eligible') === $requestSkipBefore + 1
        && scopeCheckEventCount('routing.request_error') === $requestErrorBefore,
        'Expected stale presence in an untagged pair guard was not an informational fail-closed skip.');
    $GLOBALS['pcv_fixture_cached_presence'] = ['status' => 'ready', 'known_npcs' => ['101' => 'Aela', '202' => 'Bryn'], 'reason' => null];

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['gameRequest'] = ['bored', 't', 'g', 'narrator bored event', 'raw'];
    $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $storedScope, 'pending' => false, 'config_id' => $fixtureConfigId];
    $GLOBALS['HERIKA_NAME'] = 'The Narrator';
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    scopeCheck(scopeCheckHookStops($hookDir . '/preprocessing.php') === false
        && scopeCheckHookStops($hookDir . '/context_pre.php'),
        'An untagged Standard narrator-bored generation bypassed the legacy active-pair speaker guard.');

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['gameRequest'] = $normalRequest;
    $GLOBALS['requestRoutingSnapshot'] = $snapshot;
    $GLOBALS['HERIKA_NAME'] = 'Nazeem';
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    scopeCheck(scopeCheckHookStops($hookDir . '/context_pre.php'),
        'A stamped private route generated from an NPC outside the selected pair.');
    $outsiderBlocked = array_values(array_filter(scopeCheckLogEntries(), static fn(array $entry): bool => ($entry['event'] ?? null) === 'routing.request_blocked' && ($entry['reason'] ?? null) === 'speaker_outside_pair'));
    scopeCheck(($outsiderBlocked[0]['context']['phase'] ?? null) === 'context_pre',
        'The scoped outsider-speaker guard did not identify its actual terminal phase.');

    scopeCheckBeginSimulatedRequest();
    $GLOBALS['gameRequest'] = ['inputtext', 't', 'g', 'Player: profile switch failure', 'raw'];
    $GLOBALS['requestRoutingSnapshot'] = $snapshot;
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    $GLOBALS['pcv_fixture_profile_throw'] = true;
    $switchError = scopeCheckHookThrows($hookDir . '/prerequest.php');
    unset($GLOBALS['pcv_fixture_profile_throw']);
    scopeCheck($switchError instanceof RuntimeException && $switchError->getMessage() === 'fixture-only exception details',
        'The prerequest exception did not preserve the original failure behavior.');
    $exceptionEntries = array_values(array_filter(scopeCheckLogEntries(), static fn(array $entry): bool => ($entry['event'] ?? null) === 'routing.request_error' && ($entry['reason'] ?? null) === 'hook_exception' && ($entry['context']['phase'] ?? null) === 'prerequest'));
    scopeCheck(($exceptionEntries[0]['context']['actor_a_id'] ?? null) === '101'
        && !str_contains(implode("\n", array_map(static fn(array $entry): string => json_encode($entry, JSON_UNESCAPED_SLASHES) ?: '', $exceptionEntries)), 'fixture-only exception details'),
        'The prerequest exception was not logged with bounded metadata or leaked its message.');

    $GLOBALS['CHIM_EXECUTION_MODE'] = 'DIRECTOR';
    scopeCheckBeginSimulatedRequest();
    $GLOBALS['gameRequest'] = ['inputtext', 't', 'g', 'Director remains separate', 'raw'];
    $GLOBALS['pcv_fixture_eligible'] = [];
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    scopeCheck(($GLOBALS['pcv_fixture_eligible'] ?? null) === []
        && !isset($GLOBALS['PCV_REQUEST_SCOPE']),
        'Director mode consumed pending state or activated the private request scope.');

    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    scopeCheckBeginSimulatedRequest();
    $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $storedScope, 'pending' => false];
    $GLOBALS['PCV_REQUEST_SCOPE'] = ['status' => 'off', 'scope' => null, 'start' => false];
    $GLOBALS['HERIKA_NAME'] = 'Nazeem';
    $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
    $GLOBALS['FUNC_LIST'] = ['Travel_To'];
    scopeCheck(scopeCheckHookStops($hookDir . '/context_pre.php') === false
        && ($GLOBALS['HERIKA_NAME'] ?? null) === 'Nazeem'
        && ($GLOBALS['FUNCTIONS_ARE_ENABLED'] ?? null) === true
        && ($GLOBALS['FUNC_LIST'] ?? null) === ['Travel_To'],
        'The generation hook reread active state after preprocessing captured an off request snapshot.');

    $GLOBALS['pcv_fixture_key'] = null;
    scopeCheckBeginSimulatedRequest();
    $GLOBALS['gameRequest'] = $normalRequest;
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    include $hookDir . '/preprocessing.php';
    $freshInstallContextContinues = scopeCheckHookStops($hookDir . '/context_pre.php') === false;
    scopeCheck(($GLOBALS['gameRequest'] ?? null) === $normalRequest
        && !pcvScopeStoredStateExists() && $freshInstallContextContinues,
        'A fresh installation with no state file blocked or rewrote ordinary dialogue.');

    $allEntries = scopeCheckLogEntries();
    $startedCount = count(array_filter($allEntries, static fn(array $entry): bool => ($entry['event'] ?? null) === 'routing.request_started'));
    $terminalCount = count(array_filter($allEntries, static fn(array $entry): bool => in_array($entry['event'] ?? null, [
        'routing.request_prepared', 'routing.request_skipped', 'routing.request_blocked', 'routing.request_error',
    ], true)));
    $allLogText = implode("\n", array_map(static fn(array $entry): string => json_encode($entry, JSON_UNESCAPED_SLASHES) ?: '', $allEntries));
    scopeCheck($startedCount > 0 && $terminalCount === $startedCount,
        'The actual hook paths did not emit one start and one terminal outcome per simulated relevant request.');
    scopeCheck(!str_contains($allLogText, 'Meet at dusk') && !str_contains($allLogText, 'Aela')
        && !str_contains($allLogText, 'Bryn') && !str_contains($allLogText, 'Nazeem')
        && !str_contains($allLogText, 'Player') && !str_contains($allLogText, 'fixture-only exception details'),
        'The complete routing log fixture leaked dialogue, exception text, or display names.');

    $logPath = pcv_log_path();
    scopeCheck(is_string($logPath) && is_file($logPath) && !is_link($logPath) && @unlink($logPath)
        && @symlink('/dev/null', $logPath),
        'Could not prepare the isolated logger-unavailable fixture.');
    $previousErrorLog = ini_get('error_log');
    ini_set('error_log', '/dev/null');
    try {
        scopeCheckBeginSimulatedRequest();
        $GLOBALS['pcv_fixture_key'] = 'fixture-key';
        $GLOBALS['gameRequest'] = ['rechat', 't', 'g', '{malformed', 'raw'];
        $GLOBALS['pcv_fixture_state'] = ['status' => 'active', 'scope' => $storedScope, 'pending' => false, 'config_id' => $fixtureConfigId];
        unset($GLOBALS['PCV_REQUEST_SCOPE']);
        scopeCheck(scopeCheckHookStops($hookDir . '/preprocessing.php'),
            'Failing private log storage changed the fail-closed result for malformed rechat.');
    } finally {
        if (is_string($previousErrorLog)) {
            ini_set('error_log', $previousErrorLog);
        }
        if (is_string($logPath) && is_link($logPath)) {
            @unlink($logPath);
        }
    }

    echo "PASS: stable actor identity, ordinary event transform, authoritative audience, and presence exclusion.\n";
    echo "PASS: ambiguous speaker prefixes and unsupported modes fail closed; disabled scope is inert.\n";
    echo "PASS: rechat speaker set and pre-generation speaker guard stay within the selected pair.\n";
    echo "PASS: silent bystanders are described generically without actor membership or names.\n";
    echo "PASS: real hooks preserve the eligible-input boundary, responder selection, rechat clamp, audience, and action schema.\n";
    echo "PASS: malformed rechat/Close and outsider generation stop; Director and fresh install remain outside scope.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
