<?php
declare(strict_types=1);

require_once __DIR__ . '/../server/influence.php';
require_once __DIR__ . '/../server/store.php';
require_once __DIR__ . '/../server/prerequest.php';

use ChimMindPoisoning\StoreDb;
use ChimMindPoisoning\PostgresStoreDb;
use ChimMindPoisoning\RequestLog;
use ChimMindPoisoning\WitnessSnapshotStoreDb;
use function ChimMindPoisoning\assertIdleTransactionStatus;
use function ChimMindPoisoning\assertPgSqlConnection;
use function ChimMindPoisoning\eventAlreadyProcessed;
use function ChimMindPoisoning\handleSpeechAck;
use function ChimMindPoisoning\handlePlayerInput;
use function ChimMindPoisoning\nextLedger;
use function ChimMindPoisoning\normalizeEventRow;
use function ChimMindPoisoning\persistJudgments;
use function ChimMindPoisoning\resolvePlaythroughContext;

function chimInteractionAllowed(): bool
{
    chimInteractionBegin();
    $state = chimInteractionState();
    return ($_SERVER['HTTP_X_CHIM_PASSIVE'] ?? '') !== '1'
        && $state['enabled']
        && $state['generation'] === $GLOBALS['chim_interaction_generation'];
}

function chimInteractionState(): array
{
    return [
        'enabled' => $GLOBALS['runtime_test_interaction_allowed'] ?? true,
        'generation' => $GLOBALS['runtime_test_interaction_generation'] ?? 1,
    ];
}

function chimInteractionBegin(): void
{
    if (isset($GLOBALS['chim_interaction_generation'])) {
        return;
    }
    $state = chimInteractionState();
    $GLOBALS['chim_interaction_generation'] = isset($_SERVER['HTTP_X_CHIM_GENERATION'])
        ? (int)$_SERVER['HTTP_X_CHIM_GENERATION']
        : $state['generation'];
}

function resetInteractionTestRequest(int $requestGeneration, int $currentGeneration, bool $enabled, bool $passive): void
{
    $GLOBALS['runtime_test_interaction_generation'] = $currentGeneration;
    $GLOBALS['runtime_test_interaction_allowed'] = $enabled;
    $_SERVER['HTTP_X_CHIM_GENERATION'] = (string)$requestGeneration;
    if ($passive) {
        $_SERVER['HTTP_X_CHIM_PASSIVE'] = '1';
    } else {
        unset($_SERVER['HTTP_X_CHIM_PASSIVE']);
    }
    unset($GLOBALS['chim_interaction_generation']);
    chimInteractionBegin();
}

function validModelResponse(array $judgments): string
{
    foreach ($judgments as &$judgment) {
        if (is_array($judgment) && !array_key_exists('subject_mentioned', $judgment)) {
            $judgment['subject_mentioned'] = true;
        }
    }
    unset($judgment);
    return json_encode(['judgments' => $judgments], JSON_THROW_ON_ERROR);
}

function chimIsGlobalLlmConnectorEnabled(string $connectorField): bool
{
    return $connectorField === 'RELLLM_CONNECTOR' && ($GLOBALS['runtime_test_relationship_enabled'] ?? true);
}

function extractSpeakerNameFromChatEvent($eventData): string
{
    $eventData = preg_replace('/^\s*\(\s*context[^)]*\)\s*/iu', '', trim((string)$eventData));
    return preg_match('/^\s*([^:]{1,128})\s*:/u', (string)$eventData, $matches) === 1
        ? trim($matches[1], " |\t\n\r\0\x0B")
        : '';
}

function extractCoreUtteranceFromChatEvent($eventData): string
{
    $eventData = preg_replace('/^\s*\(\s*context[^)]*\)\s*/iu', '', trim((string)$eventData));
    if (preg_match('/^\s*[^:]{1,128}\s*:\s*(.*)$/us', (string)$eventData, $matches) === 1) {
        $eventData = trim($matches[1]);
    }
    return trim((string)preg_replace('/\s*\(\s*(?:(?:talking|whispering|shouting)\s+to|speaking\s+(?:loudly|privately)\s+to)\s+[^)]*\)\s*$/iu', '', (string)$eventData));
}

function extractTalkTargetMetadata($eventData): array
{
    if (!preg_match('/\(\s*(?:(?:talking|whispering|shouting)\s+to|speaking\s+(?:loudly|privately)\s+to)\s+([^()]+?)(?:\s+from\s+far\s+away)?\s*\)/i', (string)$eventData, $matches)) {
        return ['hasExplicitTarget' => false, 'isBroadcast' => false, 'targets' => []];
    }
    $hint = trim($matches[1]);
    if (strcasecmp($hint, 'everyone') === 0) {
        return ['hasExplicitTarget' => true, 'isBroadcast' => true, 'targets' => []];
    }
    $targets = preg_split('/\s*(?:,|&| and )\s*/i', $hint);
    return ['hasExplicitTarget' => true, 'isBroadcast' => false, 'targets' => array_values(array_filter(array_map('trim', $targets ?: [])))];
}

function talkTargetsIncludeName($targetNames, $candidateName): bool
{
    foreach (is_array($targetNames) ? $targetNames : [] as $targetName) {
        if (is_string($targetName) && is_string($candidateName) && strcasecmp(trim($targetName), trim($candidateName)) === 0) {
            return true;
        }
    }
    return false;
}

/* Core routing/parser fixture copied from chat_helper_functions.php at cf5030f.
 * Present-actor and mood normalization are inert stubs because these cases do not use them.
 */
if (!function_exists('parsePeoplePipeList')) {
    function parsePeoplePipeList($peoplePipe): array
    {
        $peoplePipe = trim((string)$peoplePipe);
        if ($peoplePipe === '') {
            return [];
        }

        $tokens = explode('|', $peoplePipe);
        $cleanPeople = [];
        foreach ($tokens as $token) {
            $token = trim((string)$token);
            if ($token === '') {
                continue;
            }
            if (!in_array($token, $cleanPeople, true)) {
                $cleanPeople[] = $token;
            }
        }

        return $cleanPeople;
    }
}

if (!function_exists('chimNormalizePresentActors')) {
    function chimNormalizePresentActors($actors): array
    {
        return [];
    }
}

if (!function_exists('chimNormalizePlayerMood')) {
    function chimNormalizePlayerMood($mood): string
    {
        return '';
    }
}

if (!function_exists('chimNormalizeCustomPlayerMood')) {
    function chimNormalizeCustomPlayerMood($mood): string
    {
        return '';
    }
}

if (!function_exists('normalizePeoplePipeList')) {
    function normalizePeoplePipeList($peopleNames): string
    {
        if (!is_array($peopleNames) || $peopleNames === []) {
            return '';
        }
        $cleanPeople = [];
        foreach ($peopleNames as $name) {
            $name = trim(trim((string)$name), '|');
            if ($name !== '' && !in_array($name, $cleanPeople, true)) {
                $cleanPeople[] = $name;
            }
        }
        return $cleanPeople === [] ? '' : '|' . implode('|', $cleanPeople) . '|';
    }
}

if (!function_exists('chimDecodePlayerRoutingSnapshotField')) {
    function chimDecodePlayerRoutingSnapshotField($rawField): array
    {
    $result = [
        'listener' => '',
        'target_mode' => '',
        'audience' => '',
        'present_actors' => [],
        'chat_shortcut_routed' => false,
        'execution_mode' => '',
        'player_mood' => '',
        'player_mood_custom' => '',
    ];
    $rawField = trim((string)$rawField);
    if ($rawField === '') {
        return $result;
    }

    $decoded = base64_decode($rawField, true);
    if ($decoded === false || $decoded === '') {
        return $result;
    }

    $payload = json_decode($decoded, true);
    if (!is_array($payload)) {
        return $result;
    }

    if (in_array($payload['source'] ?? '', ['plugin_player_routing_v2', 'plugin_spatial_input_v1'], true)) {
        $listener = $payload['listener'] ?? '';
        if (is_string($listener) && strlen($listener) <= 256 && !preg_match('/[\x00-\x1F|]/', $listener)) {
            $result['listener'] = trim($listener);
        }
        $targetMode = $payload['target_mode'] ?? '';
        if (in_array($targetMode, ['automatic', 'direct', 'everyone', 'narrator'], true)) {
            $result['target_mode'] = $targetMode;
        }
    }

    if (!empty($payload['people']) && is_string($payload['people'])) {
        $result['audience'] = normalizePeoplePipeList(parsePeoplePipeList($payload['people']));
    } elseif (!empty($payload['companions']) && is_array($payload['companions'])) {
        $result['audience'] = normalizePeoplePipeList($payload['companions']);
    }

    $result['present_actors'] = chimNormalizePresentActors($payload['present_actors'] ?? []);
    $result['chat_shortcut_routed'] =
        ($payload['source'] ?? '') === 'plugin_player_routing_v2'
        && ($payload['chat_shortcut_routed'] ?? false) === true;
    if (($payload['source'] ?? '') === 'plugin_player_routing_v2') {
        $mode = is_string($payload['execution_mode'] ?? null) ? strtoupper(trim($payload['execution_mode'])) : '';
        if (in_array($mode, [
            'STANDARD', 'WHISPER', 'CLOSE', 'SHOUT', 'NARRATOR', 'DIRECTOR', 'CHEATMODE',
            'HYPNOSIS', 'AUTOCHAT', 'INJECTION_LOG', 'INJECTION_CHAT',
        ], true)) {
            $result['execution_mode'] = $mode;
        }
        $playerMood = chimNormalizePlayerMood($payload['player_mood'] ?? '');
        if ($playerMood !== '') {
            $result['player_mood'] = $playerMood;
            if ($playerMood === 'custom') {
                $result['player_mood_custom'] = chimNormalizeCustomPlayerMood($payload['player_mood_custom'] ?? '');
            }
        }
    }
    return $result;
}
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function captureRequestLog(array &$records, bool $diagnostic = false): RequestLog
{
    return new RequestLog(static function (string $json, string $level) use (&$records): void {
        $records[] = json_decode($json, true, 512, JSON_THROW_ON_ERROR) + ['sink_level' => $level];
    }, $diagnostic);
}

function lastRequestSummary(array $records): array
{
    foreach (array_reverse($records) as $record) {
        if (($record['event'] ?? null) === 'request_finished') {
            return $record;
        }
    }
    throw new RuntimeException('Missing request_finished record.');
}

function resetAckLoggingInteraction(mixed $generation = 1, bool $enabled = true): void
{
    $GLOBALS['runtime_test_interaction_allowed'] = $enabled;
    $GLOBALS['runtime_test_interaction_generation'] = $generation;
    $GLOBALS['runtime_test_relationship_enabled'] = true;
    $GLOBALS['RELLLM_CONNECTOR'] = 7;
    $_SERVER['HTTP_X_CHIM_GENERATION'] = '1';
    unset($_SERVER['HTTP_X_CHIM_PASSIVE'], $GLOBALS['chim_interaction_generation'], $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA']);
}

function same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nexpected: ' . var_export($expected, true) . '\nactual: ' . var_export($actual, true));
    }
}

final class ProfileContextQueryDb
{
    public bool $profileTableExists = true;
    public array $activeProfiles = [];
    public mixed $playerName = 'Hawke';
    public mixed $catalogOverride = 'auto';
    public bool $omitCatalogField = false;
    public bool $omitPlayerName = false;
    public array $queries = [];

    public function fetchOne(string $query, array $parameters = []): array
    {
        $this->queries[] = [$query, $parameters];
        if (str_contains($query, 'to_regclass')) {
            if ($this->omitCatalogField) {
                return [];
            }
            if ($this->catalogOverride !== 'auto') {
                return ['profile_table' => $this->catalogOverride];
            }
            return ['profile_table' => $this->profileTableExists ? 'chim_meta.playthrough_profiles' : null];
        }
        if (str_contains($query, 'jsonb_agg')) {
            $row = ['active_profiles' => json_encode($this->activeProfiles, JSON_THROW_ON_ERROR)];
            if (!$this->omitPlayerName) {
                $row['player_name'] = $this->playerName;
            }
            return $row;
        }
        if (str_contains($query, 'FROM core_player')) {
            return $this->omitPlayerName ? [] : ['player_name' => $this->playerName];
        }
        return [];
    }

    public function fetchAll(string $query): array
    {
        $this->queries[] = [$query, []];
        return str_contains($query, 'FROM chim_meta.playthrough_profiles') ? $this->activeProfiles : [];
    }
}

final class MemoryStoreDb implements StoreDb, WitnessSnapshotStoreDb
{
    public array $npcs = [];
    public array $events = [];
    public array $history = [];
    public array $reflectionRows = [];
    public string $profileId = '1';
    public string $playerName = 'Dragonborn';
    public bool $busy = false;
    public bool $failSnapshot = false;
    public bool $failRelease = false;
    public bool $failCommit = false;
    public ?int $throwNpcLookupId = null;
    public array $failWrites = [];
    public int $eventByIdWithPeopleCalls = 0;
    public $onBegin = null;
    public int $beginCalls = 0;
    public int $activePlaythroughCalls = 0;
    private bool $transaction = false;
    private ?array $before = null;

    public function activePlaythrough(): ?array
    {
        $this->activePlaythroughCalls++;
        return ['id' => $this->profileId, 'player_name' => $this->playerName];
    }

    public function acknowledgedEvent(string $utteranceId): ?array
    {
        foreach ($this->events as $event) {
            if (($event['utterance_id'] ?? null) === $utteranceId) {
                return $event;
            }
        }
        return null;
    }

    public function acknowledgedEventWithPeople(string $utteranceId): ?array
    {
        $event = $this->acknowledgedEvent($utteranceId);
        if (!is_array($event)) {
            return null;
        }
        $event['source_people'] = $event['people'] ?? null;
        return $event;
    }

    public function playerInputEvent(array $source): ?array
    {
        $fields = ['type', 'ts', 'gamets', 'data', 'localts', 'sess'];
        $matches = [];
        foreach ($this->events as $event) {
            $match = true;
            foreach ($fields as $field) {
                if (($event[$field] ?? null) !== ($source[$field] ?? null)) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $matches[] = $event;
            }
        }
        if (count($matches) !== 1) {
            return null;
        }
        return ChimMindPoisoning\normalizePlayerInputRow($matches[0]);
    }

    public function npcIdentities(): array
    {
        return array_map(
            static fn(array $npc): array => ['id' => $npc['id'], 'npc_name' => $npc['npc_name']],
            array_values($this->npcs)
        );
    }

    public function eventById(int $eventId, string $utteranceId): ?array
    {
        $event = $this->events[$eventId] ?? null;
        if (!is_array($event)) {
            return null;
        }
        if (str_starts_with($utteranceId, 'input_')) {
            if (preg_match('/\Ainput_([1-9][0-9]*)\z/', $utteranceId, $matches) !== 1
                || (int)$matches[1] !== $eventId
                || (int)($event['rowid'] ?? $event['event_id'] ?? 0) !== $eventId) {
                return null;
            }
            $normalized = $this->playerInputEvent($event);
            return is_array($normalized) && $normalized['event_id'] === $eventId ? $normalized : null;
        }
        return ($event['utterance_id'] ?? null) === $utteranceId ? $event : null;
    }

    public function eventByIdWithPeople(int $eventId, string $utteranceId): ?array
    {
        $this->eventByIdWithPeopleCalls++;
        if (str_starts_with($utteranceId, 'input_')) {
            return null;
        }
        $event = $this->eventById($eventId, $utteranceId);
        if (!is_array($event)) {
            return null;
        }
        $event['source_people'] = $event['people'] ?? null;
        return $event;
    }

    public function npcById(int $npcId, bool $forUpdate = false): ?array
    {
        if ($this->throwNpcLookupId === $npcId) {
            throw new RuntimeException('PRIVATE_SYNTHETIC_LOOKUP_DETAIL');
        }
        return $this->npcs[$npcId] ?? null;
    }

    public function reflectionHistory(string $actorName, int $beforeEventId): array
    {
        $rows = array_values(array_filter($this->reflectionRows, static function (array $row) use ($actorName, $beforeEventId): bool {
            if ((int)($row['event_id'] ?? 0) >= $beforeEventId || ($row['delivery_state'] ?? null) !== 'spoken') {
                return false;
            }
            $members = array_map('trim', explode('|', (string)($row['people'] ?? '')));
            return count(array_filter($members, static fn(string $name): bool => strcasecmp($name, $actorName) === 0)) === 1;
        }));
        usort($rows, static fn(array $a, array $b): int => (int)$b['event_id'] <=> (int)$a['event_id']);
        return array_slice($rows, 0, 16);
    }

    public function beginForListener(int $listenerId): bool
    {
        $this->beginCalls++;
        if ($this->busy || $this->transaction || !isset($this->npcs[$listenerId])) {
            return false;
        }
        if (is_callable($this->onBegin)) {
            ($this->onBegin)($listenerId, $this);
        }
        $this->before = unserialize(serialize([$this->npcs, $this->history]));
        $this->transaction = true;
        return true;
    }

    public function writeNpc(
        int $npcId,
        array $relationshipEdges,
        object $mindPoisoningData,
        float $gamets,
        array $relationshipKeysToRemove = []
    ): bool
    {
        if (!$this->transaction || !isset($this->npcs[$npcId]) || isset($this->failWrites[$npcId])) {
            return false;
        }
        $extendedData = $this->npcs[$npcId]['extended_data'];
        $storedRelationships = $extendedData->relationships ?? new stdClass();
        if ($storedRelationships instanceof stdClass) {
            $relationships = clone $storedRelationships;
        } elseif ($storedRelationships === []) {
            $relationships = new stdClass();
        } else {
            return false;
        }
        foreach ($relationshipKeysToRemove as $name) {
            if (!is_string($name)) {
                return false;
            }
            unset($relationships->{$name});
        }
        foreach ($relationshipEdges as $name => $edge) {
            $relationships->{$name} = clone $edge;
        }
        if ($relationshipEdges !== [] || $relationshipKeysToRemove !== []) {
            $extendedData->relationships = $relationships;
        }
        $this->npcs[$npcId]['extended_data'] = $extendedData;
        $this->npcs[$npcId]['plugin_extended_data']->mind_poisoning = json_decode(
            json_encode($mindPoisoningData, JSON_THROW_ON_ERROR),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->npcs[$npcId]['gamets_last_updated'] = $gamets;
        return true;
    }

    public function backupAndVerify(int $npcId, array $expected): bool
    {
        if (!$this->transaction || !isset($this->npcs[$npcId])) {
            return false;
        }
        $npc = $this->npcs[$npcId];
        $npc['extended_data']->_chim_history_source = 'relationship';
        $this->history[] = $npc;
        $expectedExtended = clone $expected['extended_data'];
        $expectedExtended->_chim_history_source = 'relationship';
        return !$this->failSnapshot
            && ChimMindPoisoning\sameJsonValue($npc['extended_data'], $expectedExtended)
            && ChimMindPoisoning\sameJsonValue($npc['plugin_extended_data'], $expected['plugin_extended_data'])
            && (float)$npc['gamets_last_updated'] === (float)$expected['gamets_last_updated'];
    }

    public function commit(): bool
    {
        if (!$this->transaction || $this->failCommit) {
            return false;
        }
        $this->transaction = false;
        $this->before = null;
        return true;
    }

    public function rollback(): void
    {
        if ($this->transaction && $this->before !== null) {
            [$this->npcs, $this->history] = $this->before;
        }
        $this->transaction = false;
        $this->before = null;
    }

    public function release(): void
    {
        if ($this->failRelease) {
            throw new RuntimeException('fixture release failure');
        }
    }
}

function baseFixture(): array
{
    $event = [
        'utterance_id' => 'utt_0123456789abcdef',
        'speaker_id' => 11,
        'listener_id' => 22,
        'speaker_name' => 'Aela',
        'listener_name' => 'Lydia',
        'text' => 'I trust Jarl Balgruuf. The Dragonborn is brave.',
        'gamets' => 10.0,
        'event_id' => 100,
        'playthrough_id' => '1',
        'player_name' => 'Dragonborn',
        'source_data' => 'Aela: I trust Jarl Balgruuf. The Dragonborn is brave. (Talking to Lydia)',
    ];
    $subjects = [
        'npc:33' => ['name' => 'Jarl Balgruuf', 'id' => 33],
        'player' => ['name' => 'Player', 'id' => null],
    ];
    $judgments = [
        'npc:33' => ['delta' => 5, 'reason' => 'Trusting claim', 'evidence' => 'I trust Jarl Balgruuf.'],
        'player' => ['delta' => -2, 'reason' => 'Negative reaction', 'evidence' => 'The Dragonborn is brave.'],
    ];
    $db = new MemoryStoreDb();
    $db->events[100] = $event + ['type' => 'chat', 'delivery_state' => 'spoken'];
    $db->npcs = [
        11 => [
            'id' => 11, 'npc_name' => 'Aela',
            'extended_data' => (object)['relationships' => (object)['Jarl Balgruuf' => (object)['aff' => 25, 'type' => 'ally']]],
            'plugin_extended_data' => new stdClass(),
        ],
        22 => [
            'id' => 22,
            'npc_name' => 'Lydia',
            'gamets_last_updated' => 20.0,
            'extended_data' => (object)[
                'unrelated' => (object)['preserve' => true, 'empty_object' => new stdClass()],
                'relationships' => (object)[
                    'Aela' => (object)['aff' => -50, 'type' => 'rival'],
                    'Jarl Balgruuf' => (object)['aff' => 99, 'type' => 'ally', 'note' => 'existing', 'nested_empty' => new stdClass()],
                    'Other NPC' => (object)['aff' => -40, 'type' => 'enemy', 'x' => 9],
                ],
            ],
            'plugin_extended_data' => (object)['other_plugin' => (object)['keep' => 'yes']],
        ],
        33 => ['id' => 33, 'npc_name' => 'Jarl Balgruuf', 'extended_data' => (object)['relationships' => new stdClass()], 'plugin_extended_data' => new stdClass()],
    ];
    return [$event, $subjects, $judgments, $db];
}

function playerInputFixture(MemoryStoreDb $db, array $options = []): array
{
    $db->npcs[44] ??= ['id' => 44, 'npc_name' => 'Inigo'];
    $GLOBALS['CHIM_EXECUTION_MODE'] = $options['effective_mode'] ?? 'STANDARD';
    $type = $options['type'] ?? 'inputtext';
    $people = $options['people'] ?? '|Lydia|Inigo|';
    $route = $options['route'] ?? [
        'source' => 'plugin_player_routing_v2',
        'listener' => 'Lydia',
        'target_mode' => 'automatic',
    ];
    $text = $options['tts_text'] ?? $db->playerName . ': I believe Jarl Balgruuf kept his promise.';
    $data = $options['data'] ?? $text . ' (talking to Inigo)';
    $insert = [
        'rowid' => $options['rowid'] ?? 884,
        'type' => $type,
        'ts' => '1740000000',
        'gamets' => '10',
        'data' => $data,
        'localts' => 1740000000,
        'sess' => 'web',
        'people' => $people,
    ];
    $db->events[$insert['rowid']] = $insert;
    $GLOBALS['PLAYER_TTS_SOURCE_TEXT'] = $text;
    $routeField = base64_encode(json_encode($route, JSON_THROW_ON_ERROR));
    $gameRequest = [$type, $insert['ts'], $insert['gamets'], $data, $routeField];
    return [$gameRequest, $insert];
}

$pauseTestRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mind_poisoning_runtime_' . bin2hex(random_bytes(8));
$pauseTestData = $pauseTestRoot . DIRECTORY_SEPARATOR . 'data';
$pauseControlPath = $pauseTestData . DIRECTORY_SEPARATOR . 'mind_poisoning.json';
check(mkdir($pauseTestData, 0700, true), 'The pause-control fixture data directory should be created.');
check(file_put_contents($pauseTestRoot . DIRECTORY_SEPARATOR . 'main.php', "<?php\n") !== false, 'The temporary CHIM root marker should be created.');
$GLOBALS['ENGINE_PATH'] = $pauseTestRoot . DIRECTORY_SEPARATOR;
register_shutdown_function(static function () use ($pauseTestRoot, $pauseTestData, $pauseControlPath): void {
    if (is_dir($pauseControlPath)) {
        @rmdir($pauseControlPath);
    } elseif (is_file($pauseControlPath) || is_link($pauseControlPath)) {
        @unlink($pauseControlPath);
    }
    @unlink($pauseTestRoot . DIRECTORY_SEPARATOR . 'main.php');
    if (is_link($pauseTestData)) {
        @unlink($pauseTestData);
    } else {
        @rmdir($pauseTestData);
    }
    @rmdir($pauseTestRoot);
});

if (($argv[1] ?? null) === '--relationship-helper-unavailable') {
    check(!class_exists(\RelationshipManager::class, false), 'The missing-helper fixture must run before loading the pinned CHIM helper.');
    resetAckLoggingInteraction();
    [, , , $missingHelperDb] = baseFixture();
    $missingHelperDb->npcs[22]['extended_data']->relationships->Dragonborn = (object)['aff' => 8, 'type' => 'friend'];
    [$missingHelperRequest, $missingHelperInsert] = playerInputFixture($missingHelperDb);
    $missingHelperCalls = 0;
    same('listener-invalid', handlePlayerInput(
        $missingHelperRequest,
        $missingHelperInsert,
        $missingHelperDb,
        static function (array $messages) use (&$missingHelperCalls): string {
            $missingHelperCalls++;
            return '{}';
        }
    ), 'Player-origin gossip must fail closed if canonical credibility normalization is unavailable.');
    same(0, $missingHelperCalls, 'Unavailable Player credibility normalization must not reach the model.');
    unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);
    echo "relationship helper unavailable: failed closed before model\n";
    exit(0);
}
require_once __DIR__ . '/fixtures/relationship_manager.cf5030f15781637498be86debe26fcf102f5690d.php';

if (defined('CHIM_MIND_POISONING_TEST_FIXTURES_ONLY') && CHIM_MIND_POISONING_TEST_FIXTURES_ONLY === true) {
    return;
}

resetAckLoggingInteraction();
[$genericPhraseEvent, , , $genericPhraseDb] = baseFixture();
$genericPhraseEvent['speaker_id'] = 5126;
$genericPhraseEvent['listener_id'] = 5124;
$genericPhraseEvent['speaker_name'] = 'Elsa';
$genericPhraseEvent['listener_name'] = 'Lidia';
$genericPhraseEvent['text'] = 'He sold a safe-house location to a vampire coven while innocents were still inside.';
$genericPhraseEvent['source_data'] = 'Elsa: ' . $genericPhraseEvent['text'] . ' (Talking to Lidia)';
$genericPhraseDb->events[100] = $genericPhraseEvent + ['type' => 'chat', 'delivery_state' => 'spoken'];
$genericPhraseDb->npcs = [
    5126 => [
        'id' => 5126, 'npc_name' => 'Elsa',
        'extended_data' => (object)['relationships' => new stdClass()],
        'plugin_extended_data' => new stdClass(),
    ],
    5124 => [
        'id' => 5124, 'npc_name' => 'Lidia', 'gamets_last_updated' => 20.0,
        'extended_data' => (object)['relationships' => new stdClass()],
        'plugin_extended_data' => new stdClass(),
    ],
    5066 => [
        'id' => 5066, 'npc_name' => 'Vampire Fledgling',
        'extended_data' => (object)['relationships' => new stdClass()],
        'plugin_extended_data' => new stdClass(),
    ],
];
$genericPhraseAck = ['_speech', 0, 10, json_encode([
    'speaker' => 'Elsa',
    'listener' => 'Lidia',
    'speech' => $genericPhraseEvent['text'],
    'utterance_id' => $genericPhraseEvent['utterance_id'],
], JSON_THROW_ON_ERROR)];
$genericPhraseBefore = serialize([
    'npcs' => $genericPhraseDb->npcs,
    'events' => $genericPhraseDb->events,
    'history' => $genericPhraseDb->history,
]);
$genericPhraseModelCalls = 0;
$genericPhraseStatus = handleSpeechAck(
    $genericPhraseAck,
    $genericPhraseDb,
    static function (array $messages) use (&$genericPhraseModelCalls): string {
        $genericPhraseModelCalls++;
        return validModelResponse([[
            'subject' => 'npc:5066',
            'delta' => -3,
            'reason' => 'The listener distrusts the claim.',
            'evidence' => 'to a vampire coven',
        ]]);
    }
);
same('no-subjects', $genericPhraseStatus, 'A generic vampire mention must not enter evaluation as Vampire Fledgling.');
same(0, $genericPhraseModelCalls, 'A generic category mention must be rejected before the provider seam.');
same(0, $genericPhraseDb->beginCalls, 'A generic category mention must not begin persistence.');
same([], $genericPhraseDb->history, 'A generic category mention must not create history.');
same(
    $genericPhraseBefore,
    serialize(['npcs' => $genericPhraseDb->npcs, 'events' => $genericPhraseDb->events, 'history' => $genericPhraseDb->history]),
    'A generic category mention must not change the catalog, relationships, ledger, event rows, or history.'
);

// Exercise the player path through the same extracted CHIM route decoder and store contract.
resetAckLoggingInteraction();
[$playerAliasRaceEvent, , , $playerAliasRaceDb] = baseFixture();
$playerAliasRaceEvent['text'] = 'Dragonborn trusts her.';
$playerAliasRaceEvent['source_data'] = 'Aela: Dragonborn trusts her. (Talking to Lydia)';
$playerAliasRaceDb->events[100] = $playerAliasRaceEvent + ['type' => 'chat', 'delivery_state' => 'spoken'];
$playerAliasRaceAck = ['_speech', 0, 10, json_encode([
    'speaker' => 'Aela',
    'listener' => 'Lydia',
    'speech' => $playerAliasRaceEvent['text'],
    'utterance_id' => $playerAliasRaceEvent['utterance_id'],
], JSON_THROW_ON_ERROR)];
$beforePlayerAliasRaceListener = unserialize(serialize($playerAliasRaceDb->npcs[22]));
$playerAliasRaceModelCalls = 0;
$insertPlayerIdentityAliasNpc = static function (array $messages) use ($playerAliasRaceDb, $playerAliasRaceEvent, &$playerAliasRaceModelCalls): string {
    $playerAliasRaceModelCalls++;
    $payload = json_decode($messages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
    check(isset($payload['candidates']['player']), 'The initial Dragonborn mention should select the Player identity.');
    $playerAliasRaceDb->npcs[44] = [
        'id' => 44,
        'npc_name' => 'Dragonborn the Wanderer',
        'extended_data' => (object)['relationships' => new stdClass()],
        'plugin_extended_data' => new stdClass(),
    ];
    check(!isset(ChimMindPoisoning\findSubjects($playerAliasRaceEvent, $playerAliasRaceDb->npcIdentities(), 'Dragonborn')['player']), 'The new NPC alias should make the Player token ambiguous in the current catalog.');
    return validModelResponse([[
        'subject' => 'player',
        'delta' => -2,
        'reason' => 'The Player identity was discussed.',
        'evidence' => 'Dragonborn trusts her.',
    ]]);
};
same('stale', handleSpeechAck($playerAliasRaceAck, $playerAliasRaceDb, $insertPlayerIdentityAliasNpc), 'A newly colliding NPC alias must stale the selected Player identity before persistence.');
same(1, $playerAliasRaceModelCalls, 'The Player alias race should be detected after one model call.');
check(ChimMindPoisoning\sameJsonValue($beforePlayerAliasRaceListener, $playerAliasRaceDb->npcs[22]), 'A colliding NPC alias must not change listener affinity or ledger state.');
same([], $playerAliasRaceDb->history, 'A colliding NPC alias must not create a history snapshot.');
check(!property_exists($playerAliasRaceDb->npcs[22]['plugin_extended_data'], 'mind_poisoning'), 'A colliding NPC alias must not create a dedupe ledger.');

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
[$playerRequest, $playerInsert] = playerInputFixture($db);
$playerMessages = null;
$playerRecords = [];
$playerStatus = handlePlayerInput(
    $playerRequest,
    $playerInsert,
    $db,
    static function (array $messages) use (&$playerMessages): string {
        $playerMessages = $messages;
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => 2, 'reason' => 'The listener hears a credible defense.', 'evidence' => 'kept his promise'],
        ]);
    },
    captureRequestLog($playerRecords)
);
same('committed', $playerStatus, 'A routed Player input about a third NPC should use the existing persistence path.');
same(1, count($db->history), 'A committed Player-input judgment should keep the normal history snapshot.');
same(100, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'The Player input judgment should update the listener’s third-party affinity.');
$playerPayload = json_decode($playerMessages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
same('player', $playerPayload['speaker']['kind'] ?? null, 'The Player must be represented as a player speaker, not an NPC.');
check(array_key_exists('id', $playerPayload['speaker']) && $playerPayload['speaker']['id'] === null, 'The Player speaker must not receive a fabricated NPC id.');
same('Dragonborn', $playerPayload['speaker']['name'] ?? null, 'The current profile name should be the Player speaker identity.');
same('I believe Jarl Balgruuf kept his promise.', $playerPayload['utterance']['text'] ?? null, 'Only the core TTS speech should be judged; routing suffix text is excluded.');
check(isset($playerPayload['candidates']['npc:33']), 'The named third-party NPC should be the only catalog candidate from the spoken text.');
check(!isset($playerPayload['candidates']['npc:44']), 'A bystander named only in the source suffix must not become a subject.');
same(null, $playerPayload['candidates']['npc:33']['speaker_bias'] ?? null, 'A Player speaker must not inherit an invented NPC bias.');
$playerSummary = lastRequestSummary($playerRecords);
same('884', (string)($playerSummary['event_id'] ?? ''), 'The successful Player summary should use its event row id.');
same('input_884', $playerSummary['utterance_id'] ?? null, 'The successful Player summary should use its synthetic source-row id.');
same(strlen($playerRequest[3]), (int)($playerSummary['payload_bytes'] ?? -1), 'The Player input log should retain a payload byte count.');
same(strlen('I believe Jarl Balgruuf kept his promise.'), (int)($playerSummary['speech_bytes'] ?? -1), 'The Player input log should count only the TTS speech body.');
check(!str_contains(json_encode($playerRecords, JSON_THROW_ON_ERROR), 'kept his promise'), 'Logs must not expose Player speech or model evidence.');
unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);

resetAckLoggingInteraction();
[, , , $playerTypeOnlyDb] = baseFixture();
$playerTypeOnlyDb->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 'neutral';
[$playerTypeOnlyRequest, $playerTypeOnlyInsert] = playerInputFixture($playerTypeOnlyDb);
$playerTypeOnlyCalls = 0;
$playerTypeOnlyModel = static function (array $messages) use (&$playerTypeOnlyCalls): string {
    $playerTypeOnlyCalls++;
    return validModelResponse([[
        'subject' => 'npc:33',
        'delta' => 0,
        'reason' => 'A lasting Player claim changes the listener opinion.',
        'evidence' => 'kept his promise',
        'type_change' => ['from' => 'neutral', 'to' => 'suspicious'],
    ]]);
};
$playerTypeOnlyStatus = handlePlayerInput(
    $playerTypeOnlyRequest,
    $playerTypeOnlyInsert,
    $playerTypeOnlyDb,
    $playerTypeOnlyModel
);
same('committed', $playerTypeOnlyStatus, 'The Player input path should persist a type-only model decision.');
same(99, $playerTypeOnlyDb->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'A type-only Player input should leave affinity unchanged.');
same('suspicious', $playerTypeOnlyDb->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type, 'A type-only Player input should persist the validated type transition.');
same(1, count($playerTypeOnlyDb->history), 'A type-only Player input should retain the atomic history snapshot.');
same('duplicate', handlePlayerInput($playerTypeOnlyRequest, $playerTypeOnlyInsert, $playerTypeOnlyDb, $playerTypeOnlyModel), 'A type-only Player input replay should be deduplicated.');
same(1, $playerTypeOnlyCalls, 'A type-only Player replay must stop before another model call.');
same(1, count($playerTypeOnlyDb->history), 'A type-only Player replay must not create another snapshot.');
unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);

foreach ([
    ['WHISPER', 'STANDARD'],
    ['CLOSE', 'NARRATOR'],
] as [$effectiveMode, $snapshotMode]) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $route = [
        'source' => 'plugin_player_routing_v2',
        'listener' => 'Lydia',
        'target_mode' => 'direct',
        'execution_mode' => $snapshotMode,
    ];
    [$playerRequest, $playerInsert] = playerInputFixture($db, [
        'effective_mode' => $effectiveMode,
        'route' => $route,
    ]);
    same('committed', handlePlayerInput($playerRequest, $playerInsert, $db, static fn(array $messages): string => validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'A supported speech mode.', 'evidence' => 'kept his promise'],
    ])), "Effective {$effectiveMode} mode should allow Player speech regardless of the routing snapshot mode.");
    same(1, count($db->history), "Effective {$effectiveMode} mode should use normal persistence.");
    unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);
}

foreach ([
    ['reported INJECTION_CHAT', 'INJECTION_CHAT', false],
    ['injection log', 'INJECTION_LOG', false],
    ['hypnosis', 'HYPNOSIS', false],
    ['director', 'DIRECTOR', false],
    ['cheat mode', 'CHEATMODE', false],
    ['rewritten auto-chat', 'AUTOCHAT', false],
    ['narrator', 'NARRATOR', false],
    ['unknown raw mode', 'UNRECOGNIZED_MODE_SECRET', false],
    ['missing mode', null, true],
    ['wrong-type mode', ['STANDARD'], false],
] as [$label, $effectiveMode, $unsetMode]) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    [$playerRequest, $playerInsert] = playerInputFixture($db, [
        'route' => [
            'source' => 'plugin_player_routing_v2',
            'listener' => 'Lydia',
            'target_mode' => 'automatic',
            'execution_mode' => 'STANDARD',
        ],
    ]);
    if ($unsetMode) {
        unset($GLOBALS['CHIM_EXECUTION_MODE']);
    } else {
        $GLOBALS['CHIM_EXECUTION_MODE'] = $effectiveMode;
    }
    $GLOBALS['PLAYER_TTS_SOURCE_TEXT'] = 'malformed wrapped text';
    $modelCalls = 0;
    $records = [];
    $beforeListener = serialize($db->npcs[22]);
    same('player-input-not-speech', handlePlayerInput($playerRequest, $playerInsert, $db, static function (array $messages) use (&$modelCalls): string {
        $modelCalls++;
        return '{}';
    }, captureRequestLog($records)), "$label must skip before parsing Player text.");
    $summary = lastRequestSummary($records);
    same('skipped', $summary['outcome'] ?? null, "$label must remain an informational skip.");
    same('info', $summary['level'] ?? null, "$label must not become a warning.");
    same('player-input-not-speech', $summary['reason'] ?? null, "$label must use the fixed skip reason.");
    same('not_called', $summary['model_outcome'] ?? null, "$label must stop before model evaluation.");
    same(0, $modelCalls, "$label must not call the model.");
    same(0, $db->activePlaythroughCalls, "$label must stop before profile or source correlation.");
    same($beforeListener, serialize($db->npcs[22]), "$label must not change listener state.");
    same([], $db->history, "$label must not create history.");
    check(!property_exists($db->npcs[22]['plugin_extended_data'], 'mind_poisoning'), "$label must not write a ledger.");
    check(!str_contains(json_encode($records, JSON_THROW_ON_ERROR), 'UNRECOGNIZED_MODE_SECRET'), 'Raw execution modes must not enter logs.');
    unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);
}

foreach ([
    [-2, 97, 'Dragonborn: Jarl Balgruuf betrayed me.', 'betrayed me', ['route' => ['source' => 'plugin_player_routing_v2', 'listener' => 'Lydia', 'target_mode' => 'direct']]],
    [0, 99, 'Dragonborn: Jarl Balgruuf said nothing.', 'said nothing', []],
] as [$delta, $expectedAffinity, $speech, $evidence, $options]) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $options['tts_text'] = $speech;
    [$playerRequest, $playerInsert] = playerInputFixture($db, $options);
    $calls = 0;
    same('committed', handlePlayerInput($playerRequest, $playerInsert, $db, static function (array $messages) use (&$calls, $delta, $evidence): string {
        $calls++;
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => $delta, 'reason' => 'The listener hears the Player.', 'evidence' => $evidence],
        ]);
    }), 'Negative and zero Player judgments should use normal persistence and dedupe.');
    same(1, $calls, 'Each eligible Player utterance should make one model call.');
    same($expectedAffinity, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'The Player judgment delta should apply within affinity bounds.');
    same(1, count($db->history), 'Negative and zero Player judgments should preserve the normal listener snapshot.');
    check(isset($db->npcs[22]['plugin_extended_data']->mind_poisoning->events[0]), 'A zero judgment should still record the processed Player event.');
    unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);
}

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
[$playerRequest, $playerInsert] = playerInputFixture($db);
$calls = 0;
$playerResponse = static function (array $messages) use (&$calls): string {
    $calls++;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible statement', 'evidence' => 'kept his promise'],
    ]);
};
same('committed', handlePlayerInput($playerRequest, $playerInsert, $db, $playerResponse), 'The first routed Player event should commit.');
same('duplicate', handlePlayerInput($playerRequest, $playerInsert, $db, $playerResponse), 'The same source row should be deduplicated.');
same(1, $calls, 'An exact Player input replay must stop before a second model call.');
same(1, count($db->history), 'A duplicate Player event must not create another snapshot.');
unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);

foreach ([
    ['wrong listener', ['route' => ['source' => 'plugin_player_routing_v2', 'listener' => 'Teldryn', 'target_mode' => 'automatic']], 'listener-unmatched'],
    ['broadcast routing', ['route' => ['source' => 'plugin_player_routing_v2', 'listener' => 'Lydia', 'target_mode' => 'everyone']], 'listener-unmatched'],
    ['audience missing listener', ['people' => '|Inigo|'], 'listener-unmatched'],
    ['ambiguous audience alias', ['people' => '|Lydia|LYDIA|Inigo|'], 'listener-unmatched'],
    ['Player identity mismatch', ['tts_text' => 'Someone Else: I believe Jarl Balgruuf kept his promise.'], 'player-identity-mismatch'],
] as [$case, $options, $expectedStatus]) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    [$playerRequest, $playerInsert] = playerInputFixture($db, $options);
    $calls = 0;
    $records = [];
    same($expectedStatus, handlePlayerInput($playerRequest, $playerInsert, $db, static function (array $messages) use (&$calls): string {
        $calls++;
        return '{}';
    }, captureRequestLog($records)), "$case must fail closed before model evaluation.");
    same(0, $calls, "$case must not make a model request.");
    same([], $db->history, "$case must not create a listener history snapshot.");
    $summary = lastRequestSummary($records);
    same('884', (string)($summary['event_id'] ?? ''), "$case should retain opaque source-row correlation after exact source match.");
    same('input_884', $summary['utterance_id'] ?? null, "$case should retain synthetic input correlation without actor names.");
    same(strlen($playerRequest[3]), (int)($summary['payload_bytes'] ?? -1), "$case should retain only the bounded payload byte count.");
    unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);
}

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
[$playerRequest, $playerInsert] = playerInputFixture($db);
$db->events[885] = $db->events[884];
$calls = 0;
same('event-unmatched', handlePlayerInput($playerRequest, $playerInsert, $db, static function (array $messages) use (&$calls): string {
    $calls++;
    return '{}';
}), 'An ambiguous exact source tuple must not be assigned to either row.');
same(0, $calls, 'An ambiguous source tuple must not spend model work.');
same([], $db->history, 'An ambiguous source tuple must not create a listener snapshot.');
unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
[$playerRequest, $playerInsert] = playerInputFixture($db);
$db->npcs[22]['extended_data']->relationships->Dragonborn = (object)['aff' => 1, 'type' => 'neutral', 'custom_info' => 'legacy alias'];
$db->npcs[22]['extended_data']->relationships->Player = (object)['aff' => 50, 'type' => 'romantic', 'custom_info' => 'canonical edge'];
$calls = 0;
$playerGossipPrompt = null;
same('committed', handlePlayerInput($playerRequest, $playerInsert, $db, static function (array $messages) use (&$calls, &$playerGossipPrompt): string {
    $calls++;
    $playerGossipPrompt = json_decode($messages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'A credible defense.', 'evidence' => 'kept his promise'],
    ]);
}), 'Player-origin gossip about an NPC should resolve the canonical listener-to-Player relationship.');
same(1, $calls, 'Core-resolvable Player aliases must still reach the model.');
same(50, $playerGossipPrompt['listener_prior_relation_to_speaker']['aff'] ?? null, 'Player credibility should use the core-selected alias even when Player is not a candidate subject.');
check(!array_key_exists('player', $playerGossipPrompt['candidates'] ?? []), 'Player should remain excluded as a subject in Player-origin gossip.');
same(100, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'The third-party NPC judgment should commit.');
same(1, $db->npcs[22]['extended_data']->relationships->Dragonborn->aff, 'NPC-only Player gossip must leave the actual-name Player alias unchanged.');
same(50, $db->npcs[22]['extended_data']->relationships->Player->aff, 'NPC-only Player gossip must leave the canonical Player edge unchanged.');
same(1, count($db->history), 'The Player gossip update should keep normal history behavior.');
unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->Dragonborn = [1];
[$playerRequest, $playerInsert] = playerInputFixture($db);
$beforeMalformedPlayerInputEdge = serialize($db->npcs[22]);
$calls = 0;
same('listener-invalid', handlePlayerInput($playerRequest, $playerInsert, $db, static function (array $messages) use (&$calls): string {
    $calls++;
    return '{}';
}), 'Player-origin NPC gossip must reject a malformed raw Player edge before building model context.');
same(0, $calls, 'A malformed raw Player edge must not reach the model on Player input.');
same($beforeMalformedPlayerInputEdge, serialize($db->npcs[22]), 'Malformed Player credibility data must not change the listener.');
same([], $db->history, 'Malformed Player credibility data must not create a snapshot.');
unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
[$playerRequest, $playerInsert] = playerInputFixture($db);
$db->npcs[22]['extended_data']->relationships_locked = 'false';
$lockedRecords = [];
$calls = 0;
same('locked', handlePlayerInput($playerRequest, $playerInsert, $db, static function (array $messages) use (&$calls): string {
    $calls++;
    return '{}';
}, captureRequestLog($lockedRecords)), 'The Player path must honor the core listener relationship lock.');
same(0, $calls, 'A locked listener must stop before model work.');
same('884', (string)(lastRequestSummary($lockedRecords)['event_id'] ?? ''), 'A locked skip should retain exact source-row correlation.');
same([], $db->history, 'A locked Player input must not create a history snapshot.');
unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
[$playerRequest, $playerInsert] = playerInputFixture($db);
$beforePlayerOff = unserialize(serialize($db->npcs[22]));
$offDuringPlayerModel = static function (array $messages): string {
    $GLOBALS['runtime_test_interaction_allowed'] = false;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 2, 'reason' => 'Credible statement', 'evidence' => 'kept his promise'],
    ]);
};
$offDuringPlayerRecords = [];
same('interaction-off', handlePlayerInput($playerRequest, $playerInsert, $db, $offDuringPlayerModel, captureRequestLog($offDuringPlayerRecords)), 'A CHIM Off transition during Player model work must stop before persistence.');
same('post_model_gate', lastRequestSummary($offDuringPlayerRecords)['stage'] ?? null, 'The mid-request Off transition should be attributed to the post-model gate.');
check(ChimMindPoisoning\sameJsonValue($beforePlayerOff, $db->npcs[22]), 'A mid-request Player Off transition must not mutate the listener.');
same([], $db->history, 'A mid-request Player Off transition must not create a history snapshot.');
unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);

resetAckLoggingInteraction(enabled: false);
[$event, $subjects, $judgments, $db] = baseFixture();
[$playerRequest, $playerInsert] = playerInputFixture($db);
$calls = 0;
same('interaction-off', handlePlayerInput($playerRequest, $playerInsert, $db, static function (array $messages) use (&$calls): string {
    $calls++;
    return '{}';
}), 'Player input should obey CHIM interaction Off.');
same(0, $calls, 'Interaction Off must prevent a Player-input model request.');
same([], $db->history, 'Interaction Off must not create a Player-input history snapshot.');
unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);

check(
    ChimMindPoisoning\sameJsonValue(['second' => 2, 'first' => 1], json_decode('{"first":1,"second":2}', false, 512, JSON_THROW_ON_ERROR)),
    'Associative PHP arrays must compare as JSON objects regardless of key order.'
);
check(!ChimMindPoisoning\sameJsonValue([1, 2], [2, 1]), 'JSON list ordering must remain significant.');

assertIdleTransactionStatus(PGSQL_TRANSACTION_IDLE);
$activeRejected = false;
try {
    assertIdleTransactionStatus(PGSQL_TRANSACTION_INTRANS);
} catch (RuntimeException) {
    $activeRejected = true;
}
check($activeRejected, 'Active caller transaction must be rejected before the adapter begins work.');
$unsupportedRejected = false;
try {
    assertPgSqlConnection(new stdClass());
} catch (RuntimeException) {
    $unsupportedRejected = true;
}
check($unsupportedRejected, 'Unsupported connection objects must fail closed before BEGIN.');
same(null, normalizeEventRow([
    'rowid' => 1, 'type' => 'script', 'utterance_id' => 'utt_0123456789abcdef',
    'delivery_state' => 'spoken', 'gamets' => 1, 'data' => 'Speaker: text',
]), 'Non-chat events must not normalize as acknowledged speech.');
same(null, normalizeEventRow([
    'rowid' => 1, 'type' => 'chat', 'utterance_id' => 'utt_0123456789abcdef',
    'gamets' => 1, 'data' => 'Speaker: text',
]), 'Missing delivery state must not be defaulted to emitted.');
same(null, normalizeEventRow([
    'rowid' => 1, 'type' => 'chat', 'utterance_id' => 'utt_0123456789abcdef',
    'delivery_state' => 'aborted', 'gamets' => 1, 'data' => 'Speaker: text',
]), 'Aborted chat events must not normalize as acknowledged speech.');
same(null, normalizeEventRow([
    'rowid' => 1, 'type' => 'chat', 'utterance_id' => 'utt_0123456789abcdef',
    'delivery_state' => 'pending', 'gamets' => 1, 'data' => 'Speaker: text',
]), 'Unknown chat delivery states must fail closed.');
same([
    'event_id' => 884,
    'utterance_id' => 'utt_0123456789abcdef',
    'gamets' => 12.5,
    'source_data' => 'Aela: I trust Jarl Balgruuf. (Talking to Lydia)',
    'delivery_state' => 'emitted',
], normalizeEventRow([
    'rowid' => '884', 'type' => 'chat', 'utterance_id' => 'utt_0123456789abcdef',
    'delivery_state' => 'emitted', 'gamets' => '12.5',
    'data' => 'Aela: I trust Jarl Balgruuf. (Talking to Lydia)',
]), 'Actual rowid-shaped eventlog rows must normalize using rowid as the event ID.');

[$event, $subjects, $judgments, $db] = baseFixture();
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'Valid judgments should commit.');
same(100, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Affinity must clamp at +100.');
same('ally', $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type, 'Existing relation type must be preserved.');
same('existing', $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->note, 'Other fields on the changed edge must be preserved.');
same(true, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->nested_empty instanceof stdClass, 'An empty object nested in an edge must remain an object.');
same(-2, $db->npcs[22]['extended_data']->relationships->Player->aff, 'The Player edge should receive its own judgment.');
same(-40, $db->npcs[22]['extended_data']->relationships->{'Other NPC'}->aff, 'Unrelated relationship edges must remain untouched.');
same(true, $db->npcs[22]['extended_data']->unrelated->empty_object instanceof stdClass, 'An unrelated empty object must not become an array.');
same('yes', $db->npcs[22]['plugin_extended_data']->other_plugin->keep, 'Other plugin namespaces must remain untouched.');
same(20.0, $db->npcs[22]['gamets_last_updated'], 'An out-of-order ACK must not move the whole-NPC timestamp backward.');
same(1, count($db->history), 'A committed event must create one full history snapshot.');
same('relationship', $db->history[0]['extended_data']->_chim_history_source, 'History snapshot must use the relationship marker.');
same(100, $db->history[0]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'History must contain the committed relationship state.');

[$event, $subjects, $judgments, $db] = baseFixture();
$judgments['npc:33']['delta'] = 0;
$judgments['player']['delta'] = 0;
$beforeRelationships = clone $db->npcs[22]['extended_data']->relationships;
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'Zero judgments still need an atomic dedupe snapshot.');
same(true, ChimMindPoisoning\sameJsonValue($beforeRelationships, $db->npcs[22]['extended_data']->relationships), 'Zero decisions must not write affinity.');
same(1, count($db->history), 'Zero decisions must still snapshot listener state.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 'neutral';
$judgments['npc:33']['delta'] = 0;
$judgments['npc:33']['type_change'] = ['from' => 'neutral', 'to' => 'suspicious'];
$judgments['player']['delta'] = 0;
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'A valid type-only decision should commit.');
same('suspicious', $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type, 'A type-only decision must update the existing relationship edge.');
same('suspicious', $db->history[0]['extended_data']->relationships->{'Jarl Balgruuf'}->type, 'A type-only decision must be present in the same listener history snapshot.');
same(1, count($db->history), 'A type-only decision must retain one atomic history snapshot.');
$typeOnlyEntry = $db->npcs[22]['plugin_extended_data']->mind_poisoning->events[0];
same(['from' => 'neutral', 'to' => 'suspicious'], (array)$typeOnlyEntry->judgments[0]->type_change, 'The ledger should preserve the original type proposal.');
same('change_eligible', $typeOnlyEntry->type_decisions[0]->reason, 'The ledger should record the bounded type decision reason.');
same('suspicious', $typeOnlyEntry->type_changes[0]->after, 'The ledger should record only the committed type transition.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = '  ';
$judgments['npc:33']['delta'] = 0;
$judgments['npc:33']['type_change'] = ['from' => 'neutral', 'to' => 'suspicious'];
$judgments['player']['delta'] = 0;
same(['npc:33' => 'neutral'], ChimMindPoisoning\relationshipTypeSnapshot($db->npcs[22], ['npc:33' => $subjects['npc:33']]), 'The model snapshot should treat a whitespace stored type as neutral.');
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'An empty stored type should use the same neutral default as the relationship snapshot.');
same('suspicious', $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type, 'An empty stored type should allow a matching neutral-origin proposal.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 42;
$judgments['npc:33']['delta'] = 0;
$judgments['npc:33']['type_change'] = ['from' => 'neutral', 'to' => 'suspicious'];
$judgments['player']['delta'] = 0;
$beforeMalformedType = serialize($db->npcs[22]);
same('invalid', persistJudgments($event, $subjects, $judgments, $db), 'A non-string stored type must remain invalid.');
same($beforeMalformedType, serialize($db->npcs[22]), 'A non-string stored type must not mutate listener state.');
same([], $db->history, 'A non-string stored type must not create a history snapshot.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 'neutral';
$judgments['npc:33']['delta'] = 0;
$judgments['npc:33']['type_change'] = ['from' => 'neutral', 'to' => 'custom_target'];
$judgments['player']['delta'] = 0;
$beforeInvalidTypeProposal = serialize($db->npcs[22]);
same('invalid', persistJudgments($event, $subjects, $judgments, $db), 'Direct storage input must reject custom or unavailable target types.');
same($beforeInvalidTypeProposal, serialize($db->npcs[22]), 'An invalid direct type proposal must not mutate listener state.');
same([], $db->history, 'An invalid direct type proposal must not create a history snapshot.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 'neutral';
$judgments['npc:33']['delta'] = 1;
$judgments['npc:33']['type_change'] = ['from' => 'neutral', 'to' => 'suspicious'];
$judgments['player']['delta'] = -2;
$staleTypeBefore = null;
$db->onBegin = static function (int $listenerId, MemoryStoreDb $lockedDb) use (&$staleTypeBefore): void {
    $lockedDb->npcs[$listenerId]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 'romantic';
    $staleTypeBefore = serialize($lockedDb->npcs[$listenerId]);
};
same('stale', persistJudgments($event, $subjects, $judgments, $db), 'A stale type origin must reject the whole decision before affinity changes.');
same($staleTypeBefore, serialize($db->npcs[22]), 'A stale type origin must preserve the locked listener state.');
same([], $db->history, 'A stale type origin must not create a history snapshot.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 'romantic';
$judgments['npc:33']['delta'] = 1;
$judgments['npc:33']['type_change'] = ['from' => 'romantic', 'to' => 'suspicious'];
$judgments['player']['delta'] = 0;
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'A protected type proposal must still allow its valid affinity change.');
same('romantic', $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type, 'A protected romantic type must be preserved.');
same(100, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Affinity should still apply when a protected type is preserved.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 'ally';
$judgments['npc:33']['delta'] = 1;
$judgments['npc:33']['type_change'] = ['from' => 'ally', 'to' => 'suspicious'];
$judgments['player']['delta'] = 0;
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'A custom type proposal must still allow its valid affinity change.');
same('ally', $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type, 'An existing custom type must be preserved.');
same(100, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Affinity should still apply when a custom type is preserved.');

[$event, $subjects, $judgments, $db] = baseFixture();
$subjects = ['player' => $subjects['player']];
$judgments = ['player' => ['delta' => -2, 'reason' => 'Lasting change', 'evidence' => 'The Dragonborn is brave.', 'type_change' => ['from' => 'neutral', 'to' => 'suspicious']]];
$db->npcs[22]['extended_data']->relationships->Player = (object)['aff' => 0, 'type' => 'neutral', 'custom_info' => 'preserve me'];
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'A combined Player affinity and type decision should commit.');
same(-2, $db->npcs[22]['extended_data']->relationships->Player->aff, 'A combined Player update should apply its affinity delta.');
same('suspicious', $db->npcs[22]['extended_data']->relationships->Player->type, 'The Player affinity update must retain its simultaneous type transition.');
same('preserve me', $db->npcs[22]['extended_data']->relationships->Player->custom_info, 'A combined Player update must preserve sibling edge fields.');

[$event, $subjects, $judgments, $db] = baseFixture();
$subjects = ['player' => $subjects['player']];
$judgments = ['player' => ['delta' => 0, 'reason' => 'Lasting change', 'evidence' => 'The Dragonborn is brave.', 'type_change' => ['from' => 'neutral', 'to' => 'suspicious']]];
$db->npcs[22]['extended_data']->relationships->Dragonborn = (object)['aff' => 50, 'type' => 'neutral', 'custom_info' => 'preserve alias data'];
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'A type-only Player decision should commit through the alias normalizer.');
same(50, $db->npcs[22]['extended_data']->relationships->Player->aff, 'Type-only Player persistence must preserve the canonical alias affinity.');
same('suspicious', $db->npcs[22]['extended_data']->relationships->Player->type, 'Type-only Player persistence must write under the canonical key.');
same('preserve alias data', $db->npcs[22]['extended_data']->relationships->Player->custom_info, 'Type-only Player persistence must preserve alias metadata.');
check(!property_exists($db->npcs[22]['extended_data']->relationships, 'Dragonborn'), 'A real type-only Player change must remove its old name alias.');
check(property_exists($db->npcs[22]['extended_data']->relationships, 'Other NPC'), 'Player alias collapse must preserve unrelated relationship edges.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff = 'invalid';
$db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 'neutral';
$judgments['npc:33']['delta'] = 0;
$judgments['npc:33']['type_change'] = ['from' => 'neutral', 'to' => 'suspicious'];
$beforeInvalidTypeOnlyAffinity = serialize($db->npcs[22]);
same('invalid', persistJudgments($event, $subjects, $judgments, $db), 'A type-only write must reject malformed affinity on the same edge.');
same($beforeInvalidTypeOnlyAffinity, serialize($db->npcs[22]), 'An invalid type-only edge must not change listener state.');
same([], $db->history, 'An invalid type-only edge must not create history.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->failSnapshot = true;
$beforeNpc = unserialize(serialize($db->npcs[22]));
same('failed', persistJudgments($event, $subjects, $judgments, $db), 'Snapshot failure must fail the commit.');
check(ChimMindPoisoning\sameJsonValue($beforeNpc, $db->npcs[22]), 'Snapshot failure must roll back affinity and dedupe state.');
same([], $db->history, 'A failed snapshot must not remain in history after rollback.');

[$event, $subjects, $judgments, $db] = baseFixture();
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'First delivery should commit.');
$afterFirst = $db->npcs[22];
same('duplicate', persistJudgments($event, $subjects, $judgments, $db), 'The same listener/event pair must be idempotent.');
check(ChimMindPoisoning\sameJsonValue($afterFirst, $db->npcs[22]), 'A duplicate must not change state.');
same(1, count($db->history), 'A duplicate must not write another snapshot.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->profileId = '2';
same('stale', persistJudgments($event, $subjects, $judgments, $db), 'Profile switch after evaluation must reject the stale event.');
same([], $db->history, 'Stale profile must not snapshot.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships_locked = true;
same('locked', persistJudgments($event, $subjects, $judgments, $db), 'Manual relationship lock must prevail after model evaluation.');
same([], $db->history, 'Locked listener must not snapshot.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships_locked = 'false';
same('locked', persistJudgments($event, $subjects, $judgments, $db), 'Core treats any nonempty legacy lock value as locked.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['lock_profile'] = 1;
same('locked', persistJudgments($event, $subjects, $judgments, $db), 'Core-locked profiles must fail closed.');
same([], $db->history, 'Core-locked listener must not snapshot.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data'] = (object)['other_data' => (object)['empty' => new stdClass()]];
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'A missing relationships parent must be created for a nonzero edge.');
same(5, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'The new edge should be written under a new relationships object.');
same(true, $db->npcs[22]['extended_data']->other_data->empty instanceof stdClass, 'Creating the parent must preserve unrelated JSON objects.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->Dragonborn = (object)['aff' => 0, 'type' => 'friend', 'extra' => new stdClass()];
$db->npcs[22]['extended_data']->relationships->Dragonborn->extra = 'keep winning metadata';
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'A single Player alias should normalize before persistence.');
same(-2, $db->npcs[22]['extended_data']->relationships->Player->aff, 'The canonical Player edge should receive the judgment.');
same('friend', $db->npcs[22]['extended_data']->relationships->Player->type, 'The normalized Player relationship type should remain intact.');
same('keep winning metadata', $db->npcs[22]['extended_data']->relationships->Player->extra, 'The normalized Player edge should preserve arbitrary metadata.');
check(!property_exists($db->npcs[22]['extended_data']->relationships, 'Dragonborn'), 'The old actual-name key should be removed after a Player update.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->Dragonborn = (object)['aff' => 0, 'type' => 'friend'];
$db->npcs[22]['extended_data']->relationships->Player = (object)['aff' => 0, 'type' => 'neutral'];
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'The pinned CHIM helper should resolve duplicate Player aliases under persistence.');
same(-2, $db->npcs[22]['extended_data']->relationships->Player->aff, 'The selected duplicate alias should receive the judgment under the canonical key.');
same('friend', $db->npcs[22]['extended_data']->relationships->Player->type, 'The selected duplicate alias should preserve its core-normalized type.');
check(!property_exists($db->npcs[22]['extended_data']->relationships, 'Dragonborn'), 'The duplicate alias should be removed after the Player update.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->onBegin = static function (int $listenerId, MemoryStoreDb $lockedDb): void {
    $lockedDb->npcs[$listenerId]['extended_data']->relationships = (object)[
        'Dragonborn' => (object)['aff' => 1, 'type' => 'neutral', 'custom_info' => 'locked alias'],
        'Player' => (object)['aff' => 50, 'type' => 'romantic', 'custom_info' => 'locked canonical'],
    ];
};
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'Persistence must normalize the current Player aliases after acquiring the listener lock.');
same(48, $db->npcs[22]['extended_data']->relationships->Player->aff, 'Locked revalidation must apply the fixture judgment delta to the current canonical prior.');
same('locked alias', $db->npcs[22]['extended_data']->relationships->Player->custom_info, 'Locked revalidation must preserve custom_info selected by the pinned CHIM normalizer.');
check(!property_exists($db->npcs[22]['extended_data']->relationships, 'Dragonborn'), 'The alias observed under lock must be removed atomically with the Player update.');

$compatibilityStatuses = [];
[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships = null;
$beforeNullRelationshipMap = serialize($db->npcs[22]);
same('invalid', persistJudgments($event, ['npc:33' => $subjects['npc:33']], ['npc:33' => $judgments['npc:33']], $db), 'An explicitly null stored relationship map must be rejected.');
same($beforeNullRelationshipMap, serialize($db->npcs[22]), 'A null stored relationship map must not mutate or snapshot listener state.');
same([], $db->history, 'A null stored relationship map must not create history.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships = [];
$compatibilityStatuses['empty NPC map'] = persistJudgments(
    $event,
    ['npc:33' => $subjects['npc:33']],
    ['npc:33' => $judgments['npc:33']],
    $db
);
same(true, $db->npcs[22]['extended_data']->relationships instanceof stdClass, 'An empty NPC relationship map must persist as an object.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->Dragonborn = [1];
$beforeInvalidStoredPlayerEdge = serialize($db->npcs[22]);
same(
    'failed',
    persistJudgments(
        $event,
        ['player' => $subjects['player']],
        ['player' => ['delta' => -3, 'reason' => 'A neutral Player judgment.', 'evidence' => 'The Dragonborn is brave.']],
        $db
    ),
    'A malformed array-shaped raw Player edge must fail closed during locked persistence.'
);
same($beforeInvalidStoredPlayerEdge, serialize($db->npcs[22]), 'A malformed raw Player edge must not mutate the listener or ledger.');
same([], $db->history, 'A malformed raw Player edge must not create a history snapshot.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships = [];
$compatibilityStatuses['empty Player map'] = persistJudgments(
    $event,
    ['player' => $subjects['player']],
    ['player' => ['delta' => -3, 'reason' => 'A neutral Player judgment.', 'evidence' => 'The Dragonborn is brave.']],
    $db
);
same(true, $db->npcs[22]['extended_data']->relationships instanceof stdClass, 'An empty Player relationship map must persist as an object.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->Player = (object)[
    'aff' => 50, 'type' => 'romantic', 'custom_info' => 'preserve this edge',
];
$db->npcs[22]['extended_data']->relationships->Dragonborn = (object)[
    'aff' => 1, 'type' => 'neutral', 'custom_info' => 'legacy alias',
];
$compatibilityStatuses['duplicate Player keys'] = persistJudgments(
    $event,
    ['player' => $subjects['player']],
    ['player' => ['delta' => -3, 'reason' => 'A neutral Player judgment.', 'evidence' => 'The Dragonborn is brave.']],
    $db
);

$hadPlayerName = array_key_exists('PLAYER_NAME', $GLOBALS);
$previousPlayerName = $GLOBALS['PLAYER_NAME'] ?? null;
$GLOBALS['PLAYER_NAME'] = 'Unrelated ambient name';
$playerEdgeAfterRepair = $db->npcs[22]['extended_data']->relationships->Player ?? null;
same(47, $playerEdgeAfterRepair->aff ?? null, 'The stronger Player affinity 50 must receive the -3 judgment.');
same('romantic', $playerEdgeAfterRepair->type ?? null, 'The winning Player relationship type must be preserved.');
same('preserve this edge', $playerEdgeAfterRepair->custom_info ?? null, 'Player-authored custom_info must survive alias normalization and affinity update.');
check(!property_exists($db->npcs[22]['extended_data']->relationships, 'Dragonborn'), 'The losing Player-name alias must be removed after a Player update.');
same(99, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Unrelated listener relationship edges must survive Player normalization.');
same(true, $db->npcs[22]['extended_data']->unrelated->preserve, 'Unrelated extended_data fields must survive Player normalization.');
same('Unrelated ambient name', $GLOBALS['PLAYER_NAME'] ?? null, 'Canonicalization must restore an existing PLAYER_NAME value.');
require_once __DIR__ . '/../server/dashboard_data.php';
same(
    ['value' => 47, 'state' => 'set', 'type' => 'romantic', 'type_state' => 'set'],
    ChimMindPoisoning\dashboardCurrent($db->npcs[22], 'player', 'Player', 'Dragonborn', []),
    'The dashboard must display the same canonical Player affinity and type that persistence wrote.'
);
unset($GLOBALS['PLAYER_NAME']);
$restoredUnsetMap = ChimMindPoisoning\canonicalStoredRelationshipMap([
    'Dragonborn' => (object)['aff' => 47, 'type' => 'romantic'],
], 'Dragonborn');
same(false, array_key_exists('PLAYER_NAME', $GLOBALS), 'Canonicalization must restore an originally absent PLAYER_NAME global.');
same(['Player' => ['aff' => 47, 'type' => 'romantic']], $restoredUnsetMap, 'The pinned helper must return the normalized canonical Player edge.');
if ($hadPlayerName) {
    $GLOBALS['PLAYER_NAME'] = $previousPlayerName;
} else {
    unset($GLOBALS['PLAYER_NAME']);
}

echo 'relationship compatibility cases: ' . json_encode($compatibilityStatuses, JSON_THROW_ON_ERROR) . "\n";
same(
    ['empty NPC map' => 'committed', 'empty Player map' => 'committed', 'duplicate Player keys' => 'committed'],
    $compatibilityStatuses,
    'Empty relationship maps and core-resolvable Player aliases must commit through the public persistence seam.'
);

$ledger = ['playthrough_id' => '1', 'floor_event_id' => 0, 'events' => []];
for ($id = 1; $id <= 129; $id++) {
    $ledger = nextLedger($ledger, '1', $id, 'utt_' . str_pad((string)$id, 16, '0', STR_PAD_LEFT), [
        'player' => ['delta' => 0, 'reason' => 'neutral', 'evidence' => 'line'],
    ]);
}
same(128, count($ledger['events']), 'The idempotency ledger must remain bounded at 128 events.');
same(1, $ledger['floor_event_id'], 'Eviction must advance the numeric event floor.');
$belowFloorRejected = false;
try {
    nextLedger($ledger, '1', 1, 'utt_0000000000000001', [
        'player' => ['delta' => 0, 'reason' => 'neutral', 'evidence' => 'line'],
    ]);
} catch (RuntimeException) {
    $belowFloorRejected = true;
}
check($belowFloorRejected, 'Evicted event ids at or below the floor must not become eligible again.');

$legacyOrdinaryLedger = (object)['mind_poisoning' => (object)[
    'playthrough_id' => '1',
    'floor_event_id' => 0,
    'events' => [[
        'event_id' => 1,
        'utterance_id' => 'legacy_ordinary_entry',
        'judgments' => ['historical-shape'],
    ]],
]];
$legacyOrdinaryNamespace = ChimMindPoisoning\storedLedgerNamespace($legacyOrdinaryLedger);
same('historical-shape', $legacyOrdinaryNamespace['events'][0]['judgments'][0] ?? null, 'Legacy ordinary ledger entries must retain their historical judgment validation behavior.');

$reorderedHistoricalTypeChange = ['to' => 'suspicious', 'from' => 'old_custom'];
same(['to', 'from'], array_keys($reorderedHistoricalTypeChange), 'The regression fixture must represent JSONB-reordered nested type keys.');
$typedOrdinaryLedger = (object)['mind_poisoning' => (object)[
    'playthrough_id' => '1',
    'floor_event_id' => 0,
    'events' => [[
        'event_id' => 2,
        'utterance_id' => 'typed_ordinary_entry',
        'judgments' => [[
            'subject' => 'npc:33',
            'delta' => 0,
            'reason' => 'Historical type decision',
            'evidence' => 'A saved interaction.',
            'type_change' => $reorderedHistoricalTypeChange,
        ]],
        'type_decisions' => [[
            'subject' => 'npc:33',
            'from' => 'old_custom',
            'to' => 'suspicious',
            'reason' => 'change_eligible',
        ]],
        'type_changes' => [[
            'subject' => 'npc:33',
            'before' => 'old_custom',
            'after' => 'suspicious',
        ]],
    ]],
]];
$typedOrdinaryNamespace = ChimMindPoisoning\storedLedgerNamespace($typedOrdinaryLedger);
check(is_array($typedOrdinaryNamespace), 'A historical type proposal with JSONB-reordered keys and custom tokens must remain valid.');
same($reorderedHistoricalTypeChange, $typedOrdinaryNamespace['events'][0]['judgments'][0]['type_change'] ?? null, 'Historical type proposal key order must not be rewritten during validation.');
$invalidTypedLedger = unserialize(serialize($typedOrdinaryLedger));
$invalidTypedLedger->mind_poisoning->events[0]['type_decisions'][0]['reason'] = 'unbounded_reason';
same(null, ChimMindPoisoning\storedLedgerNamespace($invalidTypedLedger), 'Optional ledger type fields must reject reasons outside the closed contract.');

$typedReflectionLedger = (object)['mind_poisoning' => (object)[
    'playthrough_id' => '1',
    'floor_event_id' => 0,
    'reflection_state' => ['basis' => str_repeat('a', 64), 'subjects' => ['npc:33']],
    'events' => [[
        'event_id' => 3,
        'utterance_id' => 'typed_reflection_entry',
        'source_kind' => 'reflection',
        'judgments' => [[
            'subject' => 'npc:33',
            'delta' => 0,
            'reason' => 'Historical reflection type decision',
            'evidence' => 'A saved reflection.',
            'type_change' => $reorderedHistoricalTypeChange,
        ]],
        'type_decisions' => [[
            'subject' => 'npc:33',
            'from' => 'old_custom',
            'to' => 'suspicious',
            'reason' => 'change_eligible',
        ]],
        'type_changes' => [[
            'subject' => 'npc:33',
            'before' => 'old_custom',
            'after' => 'suspicious',
        ]],
    ]],
]];
check(is_array(ChimMindPoisoning\storedLedgerNamespace($typedReflectionLedger)), 'Reflection history must accept JSONB-reordered nested type keys too.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['plugin_extended_data']->mind_poisoning = (object)[
    'playthrough_id' => '1', 'floor_event_id' => 0,
    'events' => [['event_id' => 100, 'utterance_id' => $event['utterance_id']]],
];
check(eventAlreadyProcessed($db->npcs[22], '1', 100, $event['utterance_id']), 'Preflight should recognize an exact dedupe hit.');

$GLOBALS['runtime_test_interaction_allowed'] = true;
$GLOBALS['runtime_test_relationship_enabled'] = true;
$GLOBALS['RELLLM_CONNECTOR'] = 7;
[$event, $subjects, $judgments, $db] = baseFixture();
$ack = ['_speech', 0, 10, json_encode([
    'speaker' => 'Aela',
    'listener' => 'Lydia',
    'speech' => $event['text'],
    'utterance_id' => $event['utterance_id'],
], JSON_THROW_ON_ERROR)];
$modelCalls = 0;
$capturedMessages = null;
$model = static function (array $messages) use (&$modelCalls, &$capturedMessages): string {
    $modelCalls++;
    $capturedMessages = $messages;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 2, 'reason' => 'Credible praise', 'evidence' => 'I trust Jarl Balgruuf.'],
        ['subject' => 'player', 'delta' => -1, 'reason' => 'Listener reaction', 'evidence' => 'The Dragonborn is brave.'],
    ]);
};
same('committed', handleSpeechAck($ack, $db, $model), 'A matching direct NPC speech acknowledgment should compose through persistence.');
same(1, $modelCalls, 'One acknowledged utterance should make one model request.');
$promptContext = json_decode($capturedMessages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
same(-50, $promptContext['listener_prior_relation_to_speaker']['aff'], 'The current listener credibility must reach model context.');
same(99, $promptContext['candidates']['npc:33']['listener_prior_relation']['aff'], 'The listener subject affinity must reach model context.');
same(25, $promptContext['candidates']['npc:33']['speaker_bias']['aff'], 'The speaker subject bias must reach model context.');
same(100, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Composed hook should apply the validated NPC subject judgment.');
same(-1, $db->npcs[22]['extended_data']->relationships->Player->aff, 'Composed hook should apply the validated Player judgment.');
same(1, count($db->history), 'Composed hook should snapshot the changed listener once.');
same('duplicate', handleSpeechAck($ack, $db, $model), 'An acknowledged utterance should be deduped before another model call.');
same(1, $modelCalls, 'Preflight dedupe must avoid a second model call.');
same(1, count($db->history), 'Preflight duplicate must not create a second snapshot.');

[$typeOnlyEvent, , , $typeOnlyDb] = baseFixture();
$typeOnlyDb->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type = 'neutral';
$typeOnlyDb->events[100] = $typeOnlyEvent + ['type' => 'chat', 'delivery_state' => 'spoken'];
$typeOnlyAck = ['_speech', 0, 10, json_encode([
    'speaker' => 'Aela',
    'listener' => 'Lydia',
    'speech' => $typeOnlyEvent['text'],
    'utterance_id' => $typeOnlyEvent['utterance_id'],
], JSON_THROW_ON_ERROR)];
$typeOnlyCalls = 0;
$typeOnlyModel = static function (array $messages) use (&$typeOnlyCalls): string {
    $typeOnlyCalls++;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 0, 'reason' => 'The lasting claim changes her opinion.', 'evidence' => 'I trust Jarl Balgruuf.', 'type_change' => ['from' => 'neutral', 'to' => 'suspicious']],
        ['subject' => 'player', 'delta' => 0, 'reason' => 'No durable change to the Player relation.', 'evidence' => 'The Dragonborn is brave.'],
    ]);
};
$typeOnlyStatus = handleSpeechAck($typeOnlyAck, $typeOnlyDb, $typeOnlyModel);
same('committed', $typeOnlyStatus, 'The ordinary ACK path should persist a type-only model decision.');
same(99, $typeOnlyDb->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'A type-only ACK should leave affinity unchanged.');
same('suspicious', $typeOnlyDb->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->type, 'A type-only ACK should persist its validated type transition.');
same('neutral', $typeOnlyDb->npcs[22]['plugin_extended_data']->mind_poisoning->events[0]->type_changes[0]->before, 'The ordinary ledger should record the confirmed transition origin.');
same(1, count($typeOnlyDb->history), 'A type-only ACK should retain the atomic history snapshot.');
same('duplicate', handleSpeechAck($typeOnlyAck, $typeOnlyDb, $typeOnlyModel), 'An ordinary type-only ACK replay should be deduplicated.');
same(1, $typeOnlyCalls, 'An ordinary type-only replay must stop before another model call.');
same(1, count($typeOnlyDb->history), 'An ordinary type-only replay must not create another snapshot.');

foreach ([
    ['NPC-only', 'I trust Jarl Balgruuf.', ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Supported NPC claim.', 'evidence' => 'I trust Jarl Balgruuf.'], 'Jarl Balgruuf', 1],
    ['Player-only', 'The Dragonborn is brave.', ['subject' => 'player', 'delta' => -1, 'reason' => 'Supported Player claim.', 'evidence' => 'The Dragonborn is brave.'], 'Player', -1],
] as [$emptyMapLabel, $emptyMapSpeech, $emptyMapJudgment, $emptyMapTarget, $emptyMapAffinity]) {
    [$emptyMapEvent, , , $emptyMapDb] = baseFixture();
    $emptyMapDb->npcs[22]['extended_data']->relationships = [];
    $emptyMapEvent['text'] = $emptyMapSpeech;
    $emptyMapEvent['source_data'] = 'Aela: ' . $emptyMapSpeech . ' (Talking to Lydia)';
    $emptyMapDb->events[100] = $emptyMapEvent + ['type' => 'chat', 'delivery_state' => 'spoken'];
    $emptyMapAck = ['_speech', 0, 10, json_encode([
        'speaker' => 'Aela',
        'listener' => 'Lydia',
        'speech' => $emptyMapEvent['text'],
        'utterance_id' => $emptyMapEvent['utterance_id'],
    ], JSON_THROW_ON_ERROR)];
    $emptyMapModelCalls = 0;
    $emptyMapRecords = [];
    $emptyMapStatus = handleSpeechAck($emptyMapAck, $emptyMapDb, static function (array $messages) use (&$emptyMapModelCalls, $emptyMapJudgment): string {
        $emptyMapModelCalls++;
        return validModelResponse([$emptyMapJudgment]);
    }, captureRequestLog($emptyMapRecords));
    same('committed', $emptyMapStatus, 'An empty stored relationship map should accept a public ' . $emptyMapLabel . ' judgment: ' . json_encode(lastRequestSummary($emptyMapRecords), JSON_THROW_ON_ERROR));
    same(1, $emptyMapModelCalls, 'An empty-map ' . $emptyMapLabel . ' acknowledgment should make one provider request.');
    check($emptyMapDb->npcs[22]['extended_data']->relationships instanceof stdClass, 'An empty-map ' . $emptyMapLabel . ' commit should store relationships as an object.');
    same($emptyMapAffinity, $emptyMapDb->npcs[22]['extended_data']->relationships->{$emptyMapTarget}->aff ?? null, 'The empty-map ' . $emptyMapLabel . ' edge should commit its affinity.');
    same('neutral', $emptyMapDb->npcs[22]['extended_data']->relationships->{$emptyMapTarget}->type ?? null, 'A new empty-map ' . $emptyMapLabel . ' edge should use the neutral default.');
    same(1, count($emptyMapDb->history), 'An empty-map ' . $emptyMapLabel . ' commit should create one listener snapshot.');
}

[$event, $subjects, $judgments, $db] = baseFixture();
$crTerminatedAck = ['_speech', 0, 10, json_encode([
    'speaker' => 'Aela',
    'listener' => 'Lydia',
    'speech' => $event['text'],
    'utterance_id' => $event['utterance_id'] . "\r",
], JSON_THROW_ON_ERROR)];
$crModelCalls = 0;
$crRecords = [];
$crModel = static function (array $messages) use (&$crModelCalls): string {
    $crModelCalls++;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'A tracked line.', 'evidence' => 'I trust Jarl Balgruuf.'],
        ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change.', 'evidence' => 'The Dragonborn is brave.'],
    ]);
};
same('committed', handleSpeechAck($crTerminatedAck, $db, $crModel, captureRequestLog($crRecords)), 'A core-trimmed trailing CR must preserve an otherwise valid ACK.');
same('utt_0123456789abcdef', lastRequestSummary($crRecords)['utterance_id'] ?? null, 'The ACK summary must correlate the normalized ID.');
$duplicateCrRecords = [];
same('duplicate', handleSpeechAck($crTerminatedAck, $db, $crModel, captureRequestLog($duplicateCrRecords)), 'The normalized ID must dedupe the same ACK.');
same(1, $crModelCalls, 'A CR-terminated retry must not make a second model call.');
same('utt_0123456789abcdef', lastRequestSummary($duplicateCrRecords)['utterance_id'] ?? null, 'The duplicate summary must use the normalized ID.');

[$event, $subjects, $judgments, $db] = baseFixture();
$GLOBALS['CHIM_EXECUTION_MODE'] = 'INJECTION_CHAT';
$npcModeCalls = 0;
$npcModeAck = ['_speech', 0, 10, json_encode([
    'speaker' => 'Aela',
    'listener' => 'Lydia',
    'speech' => $event['text'],
    'utterance_id' => $event['utterance_id'],
], JSON_THROW_ON_ERROR)];
same('committed', handleSpeechAck($npcModeAck, $db, static function (array $messages) use (&$npcModeCalls): string {
    $npcModeCalls++;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'NPC speech remains eligible.', 'evidence' => 'I trust Jarl Balgruuf.'],
        ['subject' => 'player', 'delta' => 0, 'reason' => 'No Player change.', 'evidence' => 'The Dragonborn is brave.'],
    ]);
}), 'Execution-mode gating must not change the NPC ACK path.');
same(1, $npcModeCalls, 'A non-speech Player mode must not disable a valid NPC ACK.');
unset($GLOBALS['CHIM_EXECUTION_MODE']);

[$event, $subjects, $judgments, $db] = baseFixture();
$event['text'] = 'You may trust Jarl Balgruuf.';
$event['source_data'] = 'Aela: You may trust Jarl Balgruuf. (Talking to Lydia)';
$db->events[100] = $event + ['type' => 'chat', 'delivery_state' => 'spoken'];
$db->npcs[44] = [
    'id' => 44, 'npc_name' => 'May',
    'extended_data' => (object)['relationships' => new stdClass()],
    'plugin_extended_data' => new stdClass(),
];
$commonWordAck = ['_speech', 0, 10, json_encode([
    'speaker' => 'Aela',
    'listener' => 'Lydia',
    'speech' => $event['text'],
    'utterance_id' => $event['utterance_id'],
], JSON_THROW_ON_ERROR)];
$beforeCommonWord = unserialize(serialize($db->npcs[22]));
$commonWordMessages = null;
$commonWordRecords = [];
$commonWordModel = static function (array $messages) use (&$commonWordMessages): string {
    $commonWordMessages = $messages;
    return validModelResponse([
        ['subject' => 'npc:33', 'subject_mentioned' => true, 'delta' => 0, 'reason' => 'The Jarl is named but no change is supported.', 'evidence' => 'Jarl Balgruuf'],
        ['subject' => 'npc:44', 'subject_mentioned' => false, 'delta' => 2, 'reason' => 'May is an ordinary word here.', 'evidence' => 'may'],
    ]);
};
same('failed', handleSpeechAck($commonWordAck, $db, $commonWordModel, captureRequestLog($commonWordRecords)), 'A common-word candidate explicitly marked as unmentioned must reject a nonzero delta.');
$commonWordContext = json_decode($commonWordMessages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
check(isset($commonWordContext['candidates']['npc:44']), 'The common-word catalog match should reach the model as a candidate, not as a confirmed mention.');
same('judgment_mention_invalid', lastRequestSummary($commonWordRecords)['reason'] ?? null, 'The common-word rejection must have the stable validation reason.');
check(ChimMindPoisoning\sameJsonValue($beforeCommonWord, $db->npcs[22]), 'Rejected common-word judgments must not mutate listener state.');
same([], $db->history, 'Rejected common-word judgments must not create a history snapshot.');
same(0, $db->beginCalls, 'Rejected common-word judgments must fail before persistence begins.');

[$event, $subjects, $judgments, $db] = baseFixture();
$event['text'] = 'I met may by the gate.';
$event['source_data'] = 'Aela: I met may by the gate. (Talking to Lydia)';
$db->events[100] = $event + ['type' => 'chat', 'delivery_state' => 'spoken'];
$db->npcs[44] = [
    'id' => 44, 'npc_name' => 'May',
    'extended_data' => (object)['relationships' => new stdClass()],
    'plugin_extended_data' => new stdClass(),
];
$lowercaseMayAck = ['_speech', 0, 10, json_encode([
    'speaker' => 'Aela',
    'listener' => 'Lydia',
    'speech' => $event['text'],
    'utterance_id' => $event['utterance_id'],
], JSON_THROW_ON_ERROR)];
$lowercaseMayMessages = null;
$lowercaseMayModel = static function (array $messages) use (&$lowercaseMayMessages): string {
    $lowercaseMayMessages = $messages;
    return validModelResponse([
        ['subject' => 'npc:44', 'subject_mentioned' => true, 'delta' => 2, 'reason' => 'The sentence refers to May.', 'evidence' => 'met may by the gate'],
    ]);
};
same('committed', handleSpeechAck($lowercaseMayAck, $db, $lowercaseMayModel), 'A lowercased genuine NPC name remains eligible when the model confirms the mention.');
$lowercaseMayContext = json_decode($lowercaseMayMessages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
check(isset($lowercaseMayContext['candidates']['npc:44']), 'Lowercase client text must retain the matching NPC candidate.');
same(2, $db->npcs[22]['extended_data']->relationships->May->aff, 'A confirmed lowercase-name judgment should persist through the composed path.');
same(1, count($db->history), 'A confirmed lowercase-name judgment should snapshot the listener.');

foreach ([
    ['Lydia kept the promise.', 'Lydia', 'stale'],
    ['Lydia Vance kept the promise.', 'Lydia Vance', 'committed'],
] as [$raceText, $evidence, $expectedRaceStatus]) {
    resetAckLoggingInteraction();
    [$raceEvent, , , $raceDb] = baseFixture();
    $raceEvent['listener_name'] = 'Inigo';
    $raceEvent['text'] = $raceText;
    $raceEvent['source_data'] = 'Aela: ' . $raceText . ' (Talking to Inigo)';
    $raceDb->npcs[22]['npc_name'] = 'Inigo';
    $raceDb->npcs[22]['extended_data']->relationships = (object)[
        'Lydia Vance' => (object)['aff' => 5, 'type' => 'friend'],
    ];
    $raceDb->npcs[33]['npc_name'] = 'Lydia Vance';
    $raceDb->events[100] = $raceEvent + ['type' => 'chat', 'delivery_state' => 'spoken'];
    $raceAck = ['_speech', 0, 10, json_encode([
        'speaker' => 'Aela',
        'listener' => 'Inigo',
        'speech' => $raceText,
        'utterance_id' => $raceEvent['utterance_id'],
    ], JSON_THROW_ON_ERROR)];
    $beforeRaceListener = unserialize(serialize($raceDb->npcs[22]));
    $raceModelCalls = 0;
    $insertAmbiguousAliasNpc = static function (array $messages) use ($raceDb, $evidence, &$raceModelCalls): string {
        $raceModelCalls++;
        $payload = json_decode($messages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
        same('Lydia Vance', $payload['candidates']['npc:33']['name'] ?? null, 'The selected subject should retain its canonical catalog name.');
        $raceDb->npcs[44] = [
            'id' => 44,
            'npc_name' => 'Lydia Hart',
            'extended_data' => (object)['relationships' => new stdClass()],
            'plugin_extended_data' => new stdClass(),
        ];
        return validModelResponse([[
            'subject' => 'npc:33',
            'delta' => 2,
            'reason' => 'A supported personal claim.',
            'evidence' => $evidence,
        ]]);
    };
    same($expectedRaceStatus, handleSpeechAck($raceAck, $raceDb, $insertAmbiguousAliasNpc), 'Commit-time subject matching must reject only the newly ambiguous alias.');
    same(1, $raceModelCalls, 'The identity race must be detected after one model call.');
    if ($expectedRaceStatus === 'stale') {
        check(ChimMindPoisoning\sameJsonValue($beforeRaceListener, $raceDb->npcs[22]), 'An ambiguous alias must not change listener affinity or ledger state.');
        same([], $raceDb->history, 'An ambiguous alias must not create a history snapshot.');
        check(!property_exists($raceDb->npcs[22]['plugin_extended_data'], 'mind_poisoning'), 'An ambiguous alias must not create a dedupe ledger.');
    } else {
        same(7, $raceDb->npcs[22]['extended_data']->relationships->{'Lydia Vance'}->aff, 'A canonical full name remains usable with the same ambiguous short alias.');
        same(1, count($raceDb->history), 'A canonical full-name judgment should still create its history snapshot.');
    }
}

[$event, $subjects, $judgments, $db] = baseFixture();
$event['text'] = 'I trust Jarl Balgruuf. The Dragonborn is brave.';
$event['source_data'] = 'Aela: I trust Jarl Balgruuf. The Dragonborn is brave. (Talking to Lydia)';
$db->events[100] = $event + ['type' => 'chat', 'delivery_state' => 'spoken'];
$truncatedAck = ['_speech', 0, 10, json_encode([
    'speaker' => 'Aela',
    'listener' => 'Lydia',
    'speech' => 'I trust Jarl Balgruuf.',
    'utterance_id' => $event['utterance_id'],
], JSON_THROW_ON_ERROR)];
$capturedAckMessages = null;
$ackTextModel = static function (array $messages) use (&$capturedAckMessages): string {
    $capturedAckMessages = $messages;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible praise', 'evidence' => 'I trust Jarl Balgruuf.'],
    ]);
};
same('committed', handleSpeechAck($truncatedAck, $db, $ackTextModel), 'A transformed ACK matching the exact utterance ID should not be rejected for text inequality.');
$ackPrompt = json_decode($capturedAckMessages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
same('I trust Jarl Balgruuf.', $ackPrompt['utterance']['text'], 'Only the client ACK speech may be used as the judged utterance.');
check(!array_key_exists('player', $ackPrompt['candidates']), 'Logged-only truncated tail must not add a Player candidate.');

foreach ([
    ['utterance_id', 'utt_ffffffffffffffff', 'event-unmatched'],
    ['speaker', 'Jarl Balgruuf', 'event-mismatch'],
    ['listener', 'Jarl Balgruuf', 'event-mismatch'],
] as [$field, $wrongValue, $expectedStatus]) {
    [$event, $subjects, $judgments, $db] = baseFixture();
    $payload = json_decode($truncatedAck[3], true, 32, JSON_THROW_ON_ERROR);
    $payload[$field] = $wrongValue;
    $wrongBindingAck = ['_speech', 0, 10, json_encode($payload, JSON_THROW_ON_ERROR)];
    $modelCalls = 0;
    $mustNotRunModel = static function (array $messages) use (&$modelCalls): string {
        $modelCalls++;
        return '{}';
    };
    same($expectedStatus, handleSpeechAck($wrongBindingAck, $db, $mustNotRunModel), 'Transformed ACKs must retain exact utterance and actor binding.');
    same(0, $modelCalls, 'A wrong utterance ID, speaker, or listener must not reach the model.');
    same([], $db->history, 'A wrong utterance ID, speaker, or listener must not create history.');
}

[$event, $subjects, $judgments, $db] = baseFixture();
resetInteractionTestRequest(1, 1, true, true);
$modelCalls = 0;
same('committed', handleSpeechAck($ack, $db, $model), 'Passive speech ACKs must remain eligible while the interaction switch is On.');
same(1, $modelCalls, 'A current passive ACK should reach the model once.');

[$event, $subjects, $judgments, $db] = baseFixture();
resetInteractionTestRequest(1, 1, false, true);
$modelCalls = 0;
same('interaction-off', handleSpeechAck($ack, $db, $model), 'Passive speech ACKs must still honor CHIM Off.');
same(0, $modelCalls, 'CHIM Off must reject a passive ACK before the model request.');

[$event, $subjects, $judgments, $db] = baseFixture();
resetInteractionTestRequest(1, 2, true, true);
$modelCalls = 0;
same('interaction-stale', handleSpeechAck($ack, $db, $model), 'A passive ACK from an older interaction generation must be rejected.');
same(0, $modelCalls, 'A stale interaction generation must not reach the model.');

[$event, $subjects, $judgments, $db] = baseFixture();
resetInteractionTestRequest(1, 1, true, true);
$modelCalls = 0;
$beforeInteractionChange = unserialize(serialize($db->npcs[22]));
$toggleOffModel = static function (array $messages) use (&$modelCalls): string {
    $modelCalls++;
    $GLOBALS['runtime_test_interaction_allowed'] = false;
    $GLOBALS['runtime_test_interaction_generation'] = 2;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible praise', 'evidence' => 'I trust Jarl Balgruuf.'],
        ['subject' => 'player', 'delta' => -1, 'reason' => 'Listener reaction', 'evidence' => 'The Dragonborn is brave.'],
    ]);
};
same('interaction-stale', handleSpeechAck($ack, $db, $toggleOffModel), 'An Off/generation change during model evaluation must reject persistence.');
same(1, $modelCalls, 'A request interrupted during evaluation should make only its original model call.');
check(ChimMindPoisoning\sameJsonValue($beforeInteractionChange, $db->npcs[22]), 'An Off/generation change during evaluation must not mutate listener state.');
same([], $db->history, 'An Off/generation change during evaluation must not snapshot the listener.');
resetInteractionTestRequest(2, 2, true, false);

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->Dragonborn = (object)['aff' => 1, 'type' => 'neutral', 'custom_info' => 'legacy alias'];
$db->npcs[22]['extended_data']->relationships->Player = (object)['aff' => 50, 'type' => 'romantic', 'custom_info' => 'preserve canonical metadata'];
$aliasAckMessages = null;
$modelCalls = 0;
$aliasAckModel = static function (array $messages) use (&$modelCalls, &$aliasAckMessages): string {
    $modelCalls++;
    $aliasAckMessages = $messages;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible praise.', 'evidence' => 'I trust Jarl Balgruuf.'],
        ['subject' => 'player', 'delta' => -3, 'reason' => 'A mild reaction.', 'evidence' => 'The Dragonborn is brave.'],
    ]);
};
same('committed', handleSpeechAck($ack, $db, $aliasAckModel), 'Core-resolvable Player aliases should pass addressed-speech preflight.');
same(1, $modelCalls, 'Canonical Player aliases should make exactly one addressed-speech model request.');
$aliasAckPrompt = json_decode($aliasAckMessages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
$expectedAliasAckMap = \RelationshipManager::normalizeRelationshipMap([
    'Dragonborn' => ['aff' => 1, 'type' => 'neutral', 'custom_info' => 'legacy alias'],
    'Player' => ['aff' => 50, 'type' => 'romantic', 'custom_info' => 'preserve canonical metadata'],
]);
same(50, $aliasAckPrompt['candidates']['player']['listener_prior_relation']['aff'] ?? null, 'The prompt must use the core-selected canonical Player edge.');
same(47, $db->npcs[22]['extended_data']->relationships->Player->aff ?? null, 'The addressed Player judgment should apply to the canonical prior.');
same('romantic', $db->npcs[22]['extended_data']->relationships->Player->type ?? null, 'The addressed Player update should preserve the winning relationship type.');
same($expectedAliasAckMap['Player']['custom_info'] ?? null, $db->npcs[22]['extended_data']->relationships->Player->custom_info ?? null, 'The addressed Player update should preserve custom_info selected by the pinned CHIM normalizer.');
check(!property_exists($db->npcs[22]['extended_data']->relationships, 'Dragonborn'), 'The addressed Player update should remove the actual-name alias.');
same(1, count($db->history), 'A canonicalized Player update should create one history snapshot.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships->Dragonborn = [1];
$beforeMalformedPlayerEdge = serialize($db->npcs[22]);
$modelCalls = 0;
same('listener-invalid', handleSpeechAck($ack, $db, static function (array $messages) use (&$modelCalls): string {
    $modelCalls++;
    return '{}';
}), 'A malformed array-shaped stored Player edge must fail addressed-speech preflight.');
same(0, $modelCalls, 'A malformed stored Player edge must not make a model request.');
same($beforeMalformedPlayerEdge, serialize($db->npcs[22]), 'A malformed stored Player edge must not mutate the listener.');
same([], $db->history, 'A malformed stored Player edge must not create a snapshot.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships = null;
$beforeNullRelationships = serialize($db->npcs[22]);
$modelCalls = 0;
same('listener-invalid', handleSpeechAck($ack, $db, static function (array $messages) use (&$modelCalls): string {
    $modelCalls++;
    return '{}';
}), 'An explicitly null stored relationships map must not be silently treated as an empty map.');
same(0, $modelCalls, 'An explicitly null stored relationship map must stop before model work.');
same($beforeNullRelationships, serialize($db->npcs[22]), 'An explicitly null stored map must remain untouched after refusal.');
same([], $db->history, 'An explicitly null stored map must not create history.');

[$event, $subjects, $judgments, $db] = baseFixture();
$event['text'] = 'I trust Jarl Balgruuf.';
$event['source_data'] = 'Aela: I trust Jarl Balgruuf. (Talking to Lydia)';
$db->events[100] = $event + ['type' => 'chat', 'delivery_state' => 'spoken'];
$db->npcs[22]['extended_data']->relationships->Dragonborn = (object)['aff' => 7, 'type' => 'friend'];
$db->npcs[22]['extended_data']->relationships->Player = (object)['aff' => -3, 'type' => 'neutral'];
$npcOnlyWithAliasesAck = ['_speech', 0, 10, json_encode([
    'speaker' => 'Aela',
    'listener' => 'Lydia',
    'speech' => $event['text'],
    'utterance_id' => $event['utterance_id'],
], JSON_THROW_ON_ERROR)];
$modelCalls = 0;
$npcOnlyWithAliasesModel = static function (array $messages) use (&$modelCalls): string {
    $modelCalls++;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible praise', 'evidence' => 'I trust Jarl Balgruuf.'],
    ]);
};
same('committed', handleSpeechAck($npcOnlyWithAliasesAck, $db, $npcOnlyWithAliasesModel), 'NPC-only judgments must remain eligible with core-resolvable Player aliases.');
same(1, $modelCalls, 'NPC-only judgments should still reach the model.');
same(7, $db->npcs[22]['extended_data']->relationships->Dragonborn->aff, 'NPC-only updates must leave the legacy Player alias unchanged.');
same(-3, $db->npcs[22]['extended_data']->relationships->Player->aff, 'NPC-only updates must leave the canonical Player alias unchanged.');

[$event, $subjects, $judgments, $db] = baseFixture();
$event['player_name'] = 'Dovah';
$event['text'] = 'I trust Jarl Balgruuf. Dovah is brave.';
$event['source_data'] = 'Aela: I trust Jarl Balgruuf. Dovah is brave. (Talking to Lydia)';
$db->playerName = 'Dovah';
$db->events[100] = $event + ['type' => 'chat', 'delivery_state' => 'spoken'];
$playerAck = ['_speech', 0, 10, json_encode([
    'speaker' => 'Aela',
    'listener' => 'Lydia',
    'speech' => $event['text'],
    'utterance_id' => $event['utterance_id'],
], JSON_THROW_ON_ERROR)];
$beforePlayerDrift = unserialize(serialize($db->npcs[22]));
$renamingModel = static function (array $messages) use ($db): string {
    $db->playerName = 'Dovah Prime';
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible praise', 'evidence' => 'I trust Jarl Balgruuf.'],
        ['subject' => 'player', 'delta' => -1, 'reason' => 'Named Player reaction', 'evidence' => 'Dovah is brave.'],
    ]);
};
same('stale', handleSpeechAck($playerAck, $db, $renamingModel), 'A same-profile Player rename during evaluation must reject stale Player identity context.');
check(ChimMindPoisoning\sameJsonValue($beforePlayerDrift, $db->npcs[22]), 'A same-profile Player rename must not mutate listener state.');
same([], $db->history, 'A same-profile Player rename must not create a history snapshot.');

[$event, $subjects, $judgments, $db] = baseFixture();
$event['player_name'] = 'Dovah';
$event['text'] = 'I trust Jarl Balgruuf.';
$event['source_data'] = 'Aela: I trust Jarl Balgruuf. (Talking to Lydia)';
$db->playerName = 'Dovah';
$db->events[100] = $event + ['type' => 'chat', 'delivery_state' => 'spoken'];
$npcOnlyAck = ['_speech', 0, 10, json_encode([
    'speaker' => 'Aela',
    'listener' => 'Lydia',
    'speech' => $event['text'],
    'utterance_id' => $event['utterance_id'],
], JSON_THROW_ON_ERROR)];
$npcOnlyModel = static function (array $messages) use ($db): string {
    $db->playerName = 'Dovah Prime';
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible praise', 'evidence' => 'I trust Jarl Balgruuf.'],
    ]);
};
same('committed', handleSpeechAck($npcOnlyAck, $db, $npcOnlyModel), 'A same-profile Player rename must not reject an NPC-only judgment.');
same(1, count($db->history), 'NPC-only judgments remain snapshot eligible after a Player rename.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['plugin_extended_data']->mind_poisoning = 'corrupt';
$beforeMalformedLedger = unserialize(serialize($db->npcs[22]));
same('invalid', persistJudgments($event, $subjects, $judgments, $db), 'A present malformed ledger namespace must fail closed rather than reset dedupe state.');
check(ChimMindPoisoning\sameJsonValue($beforeMalformedLedger, $db->npcs[22]), 'Malformed ledger state must not mutate the listener.');
same([], $db->history, 'Malformed ledger state must not create a history snapshot.');
check(eventAlreadyProcessed($db->npcs[22], '1', 100, $event['utterance_id']), 'Preflight must fail closed for a present malformed ledger namespace.');
foreach ([null, new stdClass()] as $malformedLedger) {
    [$event, $subjects, $judgments, $db] = baseFixture();
    $db->npcs[22]['plugin_extended_data']->mind_poisoning = $malformedLedger;
    same('invalid', persistJudgments($event, $subjects, $judgments, $db), 'Present null and empty-object ledgers must fail closed.');
    check(eventAlreadyProcessed($db->npcs[22], '1', 100, $event['utterance_id']), 'Preflight must fail closed for present null and empty-object ledgers.');
    same([], $db->history, 'Invalid present ledger state must not create history.');
}

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['plugin_extended_data']->mind_poisoning = (object)[
    'playthrough_id' => '2', 'floor_event_id' => 0, 'events' => [],
];
same('committed', persistJudgments($event, $subjects, $judgments, $db), 'A valid ledger from another playthrough may start a fresh playthrough ledger.');

[$event, $subjects, $judgments, $db] = baseFixture();
$modelCalls = 0;
same('ignored', handleSpeechAck(['_speech_abort', 0, 10, $ack[3]], $db, $model), 'Abort acknowledgments must not enter the evaluation path.');
same(0, $modelCalls, 'Abort acknowledgment must not call the model.');
$GLOBALS['runtime_test_interaction_allowed'] = false;
same('interaction-off', handleSpeechAck($ack, $db, $model), 'The hook must honor CHIM interaction Off before the core Off handler runs.');
same(0, $modelCalls, 'Interaction Off must skip model evaluation.');
$GLOBALS['runtime_test_interaction_allowed'] = true;
$GLOBALS['runtime_test_relationship_enabled'] = false;
same('disabled', handleSpeechAck($ack, $db, $model), 'The global relationship setting must gate evaluation.');
same(0, $modelCalls, 'Disabled relationship processing must not call the model.');
$GLOBALS['runtime_test_relationship_enabled'] = true;
unset($GLOBALS['RELLLM_CONNECTOR']);
same('connector-invalid', handleSpeechAck($ack, $db, $model), 'A missing relationship connector ID must fail closed before evaluation.');
same(0, $modelCalls, 'A missing connector must not call the model.');
$GLOBALS['RELLLM_CONNECTOR'] = 7;

[$event, $subjects, $judgments, $db] = baseFixture();
$db->events[100]['source_data'] = 'Aela: I trust Jarl Balgruuf. The Dragonborn is brave. (Talking to Everyone)';
$modelCalls = 0;
same('event-mismatch', handleSpeechAck($ack, $db, $model), 'Broadcast events must not be assigned to one direct listener.');
same(0, $modelCalls, 'Broadcast event must not call the model.');

[$event, $subjects, $judgments, $db] = baseFixture();
$db->npcs[22]['extended_data']->relationships_locked = 'false';
$modelCalls = 0;
same('locked', handleSpeechAck($ack, $db, $model), 'Legacy nonempty relationship lock values must gate before model evaluation.');
same(0, $modelCalls, 'Locked listener must not call the model.');

[$event, $subjects, $judgments, $db] = baseFixture();
$GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = true;
$modelCalls = 0;
same('restore-policy', handleSpeechAck($ack, $db, $model), 'Relationship-preserving restore mode must skip before model evaluation.');
same(0, $modelCalls, 'Restore-policy gate must not call the model.');
unset($GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA']);

[$event, $subjects, $judgments, $db] = baseFixture();
$GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = 'false';
same('committed', handleSpeechAck($ack, $db, $model), 'A string false restore setting must retain core boolean semantics.');
unset($GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA']);

[$event, $subjects, $judgments, $db] = baseFixture();
$modelCalls = 0;
$badModel = static function (array $messages) use (&$modelCalls): string {
    $modelCalls++;
    return '{';
};
same('failed', handleSpeechAck($ack, $db, $badModel), 'Invalid model output must fail closed at composition.');
same(1, $modelCalls, 'Malformed model output should be rejected after one request.');
same([], $db->history, 'Invalid model output must not snapshot or persist.');

$GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = true;
[$event, $subjects, $judgments, $db] = baseFixture();
same('restore-policy', persistJudgments($event, $subjects, $judgments, $db), 'Unsafe relationship-preserving restore policy must fail closed.');
same(0, $db->beginCalls, 'Restore-policy rejection must happen before transaction begin.');
unset($GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA']);

resetAckLoggingInteraction();
$loggingResponse = static fn(array $messages): string => validModelResponse([
    ['subject' => 'npc:33', 'delta' => 2, 'reason' => 'Credible praise', 'evidence' => 'I trust Jarl Balgruuf.'],
    ['subject' => 'player', 'delta' => -1, 'reason' => 'Listener reaction', 'evidence' => 'The Dragonborn is brave.'],
]);
[$event, $subjects, $judgments, $db] = baseFixture();
$requestRecords = [];
same('committed', handleSpeechAck($ack, $db, $loggingResponse, captureRequestLog($requestRecords, true)), 'Request logging must preserve a successful ACK result.');
$requestSummary = lastRequestSummary($requestRecords);
same('committed', $requestSummary['outcome'] ?? null, 'Successful ACKs should finish as committed.');
same('committed', $requestSummary['persistence_outcome'] ?? null, 'The summary should include the persistence result.');
same('validated', $requestSummary['model_outcome'] ?? null, 'The summary should distinguish validated model output.');
same('utt_0123456789abcdef', $requestSummary['utterance_id'] ?? null, 'The summary should correlate by the validated utterance ID.');
same('7', $requestSummary['connector_id'] ?? null, 'The summary should identify the configured connector row.');
check(is_numeric($requestSummary['model_ms'] ?? null) && is_numeric($requestSummary['persistence_ms'] ?? null), 'Model and persistence durations should be recorded.');
same(1, count(array_filter($requestRecords, static fn(array $record): bool => ($record['event'] ?? null) === 'ack_started')), 'One ACK should emit one start event.');
same(1, count(array_filter($requestRecords, static fn(array $record): bool => ($record['event'] ?? null) === 'request_finished')), 'One ACK should emit exactly one final summary.');
same(2, count(array_filter($requestRecords, static fn(array $record): bool => ($record['event'] ?? null) === 'judgment_proposal')), 'Validated judgments should emit bounded debug proposals.');
$requestLogText = json_encode($requestRecords, JSON_THROW_ON_ERROR);
foreach (['Aela', 'Lydia', 'Dragonborn', 'private evidence', 'I trust Jarl Balgruuf'] as $privateValue) {
    check(!str_contains($requestLogText, $privateValue), 'Structured logs must not contain actor names, speech, or model evidence.');
}

resetAckLoggingInteraction(enabled: false);
[$event, $subjects, $judgments, $db] = baseFixture();
$offModelCalls = 0;
$offRecords = [];
$offStatus = handleSpeechAck($ack, $db, static function (array $messages) use (&$offModelCalls): string {
    $offModelCalls++;
    return '{}';
}, captureRequestLog($offRecords));
same('interaction-off', $offStatus, 'The logging wrapper must preserve the CHIM Off result.');
same(0, $offModelCalls, 'Off ACKs must not make a model request.');
$offSummary = lastRequestSummary($offRecords);
same('skipped', $offSummary['outcome'] ?? null, 'Off ACKs should be recorded as skipped.');
same('interaction_off', $offSummary['reason'] ?? null, 'The summary should distinguish an actual Off setting.');
same('not_called', $offSummary['model_outcome'] ?? null, 'Off ACKs should record that the model was not called.');

foreach ([
    ['corrupt-ledger', 'ledger-invalid', static function (MemoryStoreDb $db): void {
        $db->npcs[22]['plugin_extended_data']->mind_poisoning = 'corrupt';
    }],
    ['ledger-floor', 'ledger-floor', static function (MemoryStoreDb $db): void {
        $db->npcs[22]['plugin_extended_data']->mind_poisoning = (object)[
            'playthrough_id' => '1', 'floor_event_id' => 100, 'events' => [],
        ];
    }],
    ['exact-duplicate', 'duplicate-event', static function (MemoryStoreDb $db): void {
        $db->npcs[22]['plugin_extended_data']->mind_poisoning = (object)[
            'playthrough_id' => '1', 'floor_event_id' => 0,
            'events' => [['event_id' => 100, 'utterance_id' => 'utt_0123456789abcdef']],
        ];
    }],
] as [$label, $expectedReason, $seedLedger]) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $seedLedger($db);
    $duplicateCalls = 0;
    $duplicateRecords = [];
    same('duplicate', handleSpeechAck($ack, $db, static function (array $messages) use (&$duplicateCalls): string {
        $duplicateCalls++;
        return '{}';
    }, captureRequestLog($duplicateRecords)), $label . ' must preserve the duplicate return status.');
    same(0, $duplicateCalls, $label . ' must stop before paid model work.');
    same($expectedReason, lastRequestSummary($duplicateRecords)['reason'] ?? null, $label . ' must have a precise safe log reason.');
    same([], $db->history, $label . ' must not create a history snapshot.');
}

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$midModelOffRecords = [];
$offDuringModel = static function (array $messages): string {
    $GLOBALS['runtime_test_interaction_allowed'] = false;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 2, 'reason' => 'Credible praise', 'evidence' => 'I trust Jarl Balgruuf.'],
        ['subject' => 'player', 'delta' => -1, 'reason' => 'Listener reaction', 'evidence' => 'The Dragonborn is brave.'],
    ]);
};
same('interaction-off', handleSpeechAck($ack, $db, $offDuringModel, captureRequestLog($midModelOffRecords)), 'An Off transition during model evaluation must preserve the skip status.');
$midModelOffSummary = lastRequestSummary($midModelOffRecords);
same('post_model_gate', $midModelOffSummary['stage'] ?? null, 'An Off transition should be attributed to the post-model gate.');
same('interaction_off', $midModelOffSummary['reason'] ?? null, 'An Off transition should use its stable reason code.');
same('validated', $midModelOffSummary['model_outcome'] ?? null, 'The summary should retain that model output was validated before Off.');
check(!array_key_exists('committed', $midModelOffSummary) && !array_key_exists('changes', $midModelOffSummary), 'A post-model Off skip must not claim persistence.');
same([], $db->history, 'An Off transition during model evaluation must not snapshot the listener.');

resetAckLoggingInteraction(generation: '1');
[$event, $subjects, $judgments, $db] = baseFixture();
$invalidStateRecords = [];
same('interaction-off', handleSpeechAck($ack, $db, $loggingResponse, captureRequestLog($invalidStateRecords)), 'Malformed interaction state must retain the existing return status.');
same('interaction_state_invalid', lastRequestSummary($invalidStateRecords)['reason'] ?? null, 'Malformed interaction state must not be mislabeled as Off.');

resetAckLoggingInteraction();
$validAckFields = [
    'speaker' => 'Jarl Balgruuf',
    'listener' => 'Mira',
    'speech' => 'ACK_PRIVATE_CONTENT',
    'utterance_id' => 'utt_0123456789abcdef',
];
$missingUtteranceIdAck = $validAckFields;
unset($missingUtteranceIdAck['utterance_id']);
$invalidUtf8Ack = '{"speaker":"Jarl Balgruuf","listener":"Mira","speech":"' . "\xFF" . '","utterance_id":"utt_0123456789abcdef"}';
$malformedAckCases = [
    ['invalid-json', '{"speech":"ACK_PRIVATE_CONTENT"', 'invalid-payload', 'payload_json_invalid'],
    ['invalid-root', '[]', 'invalid-payload', 'payload_root_invalid'],
    ['missing-field', json_encode(['speaker' => 'Jarl Balgruuf'], JSON_THROW_ON_ERROR), 'invalid-payload', 'payload_field_missing'],
    ['wrong-field-type', json_encode(array_replace($validAckFields, ['speech' => ['ACK_PRIVATE_CONTENT']]), JSON_THROW_ON_ERROR), 'invalid-payload', 'payload_field_type_invalid'],
    ['oversized-field', json_encode(array_replace($validAckFields, ['speech' => str_repeat('x', 12001)]), JSON_THROW_ON_ERROR), 'invalid-payload', 'payload_field_oversized'],
    ['empty-field', json_encode(array_replace($validAckFields, ['speech' => '  ']), JSON_THROW_ON_ERROR), 'invalid-payload', 'payload_field_empty'],
    ['invalid-utf8', $invalidUtf8Ack, 'invalid-payload', 'payload_invalid_utf8'],
    ['wrong-utterance-id-type', json_encode(array_replace($validAckFields, ['utterance_id' => 42]), JSON_THROW_ON_ERROR), 'invalid-payload', 'payload_utterance_id_type_invalid'],
    ['null-utterance-id', json_encode(array_replace($validAckFields, ['utterance_id' => null]), JSON_THROW_ON_ERROR), 'invalid-payload', 'payload_utterance_id_type_invalid'],
    ['array-utterance-id', json_encode(array_replace($validAckFields, ['utterance_id' => []]), JSON_THROW_ON_ERROR), 'invalid-payload', 'payload_utterance_id_type_invalid'],
    ['invalid-utterance-id', json_encode(array_replace($validAckFields, ['utterance_id' => 'invalid-id']), JSON_THROW_ON_ERROR), 'invalid-payload', 'payload_utterance_id_invalid'],
    ['interior-cr-utterance-id', json_encode(array_replace($validAckFields, ['utterance_id' => 'utt_01234567' . "\r" . '89abcdef']), JSON_THROW_ON_ERROR), 'invalid-payload', 'payload_utterance_id_invalid'],
    ['form-feed-utterance-id', json_encode(array_replace($validAckFields, ['utterance_id' => "\f"]), JSON_THROW_ON_ERROR), 'invalid-payload', 'payload_utterance_id_invalid'],
    ['oversized-payload', str_repeat('x', 16385), 'oversized', 'payload_raw_oversized'],
    ['non-string-payload', null, 'oversized', 'payload_raw_type_invalid'],
];
$invalidPayloadCalls = 0;
foreach ($malformedAckCases as [$label, $rawPayload, $expectedStatus, $expectedReason]) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $malformedRecords = [];
    same($expectedStatus, handleSpeechAck(['_speech', 0, 10, $rawPayload], $db, static function (array $messages) use (&$invalidPayloadCalls): string {
        $invalidPayloadCalls++;
        return '{}';
    }, captureRequestLog($malformedRecords)), "$label must preserve its existing hook status.");
    $malformedSummary = lastRequestSummary($malformedRecords);
    same($expectedReason, $malformedSummary['reason'] ?? null, "$label should log a fixed safe reason code.");
    same('skipped', $malformedSummary['outcome'] ?? null, "$label should preserve the existing skipped summary outcome.");
    same('warning', $malformedSummary['level'] ?? null, "$label should remain a warning in the request summary.");
    same('not_called', $malformedSummary['model_outcome'] ?? null, "$label must stop before model work.");
    same(99, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, "$label must not change affinity.");
    same([], $db->history, "$label must not create a snapshot.");
    check(!str_contains(json_encode($malformedRecords, JSON_THROW_ON_ERROR), 'ACK_PRIVATE_CONTENT'), "$label must not log payload text.");
}

$untrackedAckCases = [
    ['missing-id', $missingUtteranceIdAck],
    ['empty-id', array_replace($validAckFields, ['utterance_id' => ''])],
    ['whitespace-id', array_replace($validAckFields, ['utterance_id' => " \t\r\n "])],
    ['nul-id', array_replace($validAckFields, ['utterance_id' => "\0"])],
];
foreach ($untrackedAckCases as [$label, $fields]) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $untrackedRecords = [];
    $untrackedModelCalls = 0;
    same('untracked-speech', handleSpeechAck(
        ['_speech', 0, 10, json_encode($fields, JSON_THROW_ON_ERROR)],
        $db,
        static function (array $messages) use (&$untrackedModelCalls): string {
            $untrackedModelCalls++;
            return '{}';
        },
        captureRequestLog($untrackedRecords)
    ), "$label should skip without a correlation ID.");
    $untrackedSummary = lastRequestSummary($untrackedRecords);
    check(!array_key_exists('utterance_id', $untrackedSummary), "$label must not invent a correlation ID.");
    same('utterance_id_absent', $untrackedSummary['reason'] ?? null, "$label should use the fixed benign-skip reason.");
    same('skipped', $untrackedSummary['outcome'] ?? null, "$label should remain skipped.");
    same('info', $untrackedSummary['level'] ?? null, "$label should be informational, not a warning.");
    same('not_called', $untrackedSummary['model_outcome'] ?? null, "$label should record no model work.");
    same(0, $untrackedModelCalls, "$label must not call the model.");
    same(99, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, "$label must not change affinity.");
    same([], $db->history, "$label must not create a snapshot.");
    check(!property_exists($db->npcs[22]['plugin_extended_data'], 'mind_poisoning'), "$label must not write a ledger.");
    check(!str_contains(json_encode($untrackedRecords, JSON_THROW_ON_ERROR), 'ACK_PRIVATE_CONTENT'), "$label must not log speech text.");
}

foreach ([
    ['missing-id-empty-speech', array_replace($missingUtteranceIdAck, ['speech' => '  ']), 'payload_field_empty'],
    ['blank-id-wrong-speaker-type', array_replace($validAckFields, ['utterance_id' => " \t", 'speaker' => ['Jarl Balgruuf']]), 'payload_field_type_invalid'],
] as [$label, $fields, $expectedReason]) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $malformedOtherRecords = [];
    $malformedOtherCalls = 0;
    same('invalid-payload', handleSpeechAck(
        ['_speech', 0, 10, json_encode($fields, JSON_THROW_ON_ERROR)],
        $db,
        static function (array $messages) use (&$malformedOtherCalls): string {
            $malformedOtherCalls++;
            return '{}';
        },
        captureRequestLog($malformedOtherRecords)
    ), "$label must retain the malformed-field status.");
    same($expectedReason, lastRequestSummary($malformedOtherRecords)['reason'] ?? null, "$label must retain its specific warning reason.");
    same('warning', lastRequestSummary($malformedOtherRecords)['level'] ?? null, "$label must remain a warning before benign untracked skipping.");
    same(0, $malformedOtherCalls, "$label must stop before model work.");
    same([], $db->history, "$label must not create history.");
}

same(0, $invalidPayloadCalls, 'Malformed and oversized payloads must stop before paid model work.');

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$failedModelRecords = [];
$secretThrowingModel = static function (array $messages): string {
    throw new RuntimeException('PRIVATE_EXCEPTION_SECRET');
};
same('failed', handleSpeechAck($ack, $db, $secretThrowingModel, captureRequestLog($failedModelRecords)), 'Provider exceptions must preserve the existing failed result.');
$failedModelSummary = lastRequestSummary($failedModelRecords);
same('model_request_failed', $failedModelSummary['reason'] ?? null, 'Provider failures should use a stable safe reason code.');
same('failed', $failedModelSummary['model_outcome'] ?? null, 'Provider failures should be distinguishable from invalid output.');
check(is_numeric($failedModelSummary['model_ms'] ?? null), 'Failed model calls should retain their elapsed duration.');
check(!str_contains(json_encode($failedModelRecords, JSON_THROW_ON_ERROR), 'PRIVATE_EXCEPTION_SECRET'), 'Raw provider exception messages must not be logged.');
same(99, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Provider failure must not mutate affinity.');
same([], $db->history, 'Provider failure must not create a history snapshot.');

foreach (['connector_api_key_missing', 'model_response_empty'] as $typedReason) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $typedModelFailureRecords = [];
    $typedModelFailure = static function (array $messages) use ($typedReason): string {
        throw new ChimMindPoisoning\ModelRequestFailure($typedReason);
    };
    same('failed', handleSpeechAck($ack, $db, $typedModelFailure, captureRequestLog($typedModelFailureRecords)), "$typedReason must preserve the failed hook status.");
    $typedModelFailureSummary = lastRequestSummary($typedModelFailureRecords);
    same($typedReason, $typedModelFailureSummary['reason'] ?? null, "$typedReason should survive in request_finished.");
    same('error', $typedModelFailureSummary['level'] ?? null, "$typedReason should remain error level in request_finished.");
    $typedModelFinished = array_values(array_filter($typedModelFailureRecords, static fn(array $record): bool => ($record['event'] ?? null) === 'model_finished'));
    same(1, count($typedModelFinished), "$typedReason should emit one model_finished record.");
    same($typedReason, $typedModelFinished[0]['reason'] ?? null, "$typedReason should survive in model_finished.");
    same('error', $typedModelFinished[0]['level'] ?? null, "$typedReason should remain error level.");
    same(99, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, "$typedReason must not mutate affinity.");
    same([], $db->history, "$typedReason must not create a history snapshot.");
}

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$spoofedModelRecords = [];
$spoofedKnownMessage = static function (array $messages): string {
    throw new RuntimeException('Configured OpenRouter connector has no API key.', 151);
};
same('failed', handleSpeechAck($ack, $db, $spoofedKnownMessage, captureRequestLog($spoofedModelRecords)), 'An upstream RuntimeException must preserve the failed hook status.');
$spoofedModelSummary = lastRequestSummary($spoofedModelRecords);
same('model_request_failed', $spoofedModelSummary['reason'] ?? null, 'Untrusted exception text/code must not impersonate a typed connector failure.');
$spoofedModelFinished = array_values(array_filter($spoofedModelRecords, static fn(array $record): bool => ($record['event'] ?? null) === 'model_finished'));
same('model_request_failed', $spoofedModelFinished[0]['reason'] ?? null, 'Unknown model exceptions should retain only the generic safe reason.');
check(!str_contains(json_encode($spoofedModelRecords, JSON_THROW_ON_ERROR), 'Configured OpenRouter connector has no API key.'), 'Unknown exception messages must not enter logs.');
same([], $db->history, 'Unknown model failures must not create a history snapshot.');

$parserRows = [];
foreach ($judgments as $subject => $judgment) {
    $parserRows[] = ['subject' => $subject, 'subject_mentioned' => true] + $judgment;
}
$wrongSubjectRows = $parserRows;
$wrongSubjectRows[0]['subject'] = 'npc:999';
$wrongDeltaRows = $parserRows;
$wrongDeltaRows[0]['delta'] = 6;
$wrongReasonRows = $parserRows;
$wrongReasonRows[0]['reason'] = ' ';
$wrongEvidenceRows = $parserRows;
$wrongEvidenceRows[0]['evidence'] = 'not in the utterance';
$wrongMentionRows = $parserRows;
$wrongMentionRows[0]['subject_mentioned'] = false;
$incompleteRows = array_slice($parserRows, 0, 1);
$parserFailures = [
    'response_too_large' => str_repeat('x', 16385),
    'response_json_invalid' => '{',
    'response_schema_invalid' => json_encode(['other' => []], JSON_THROW_ON_ERROR),
    'judgment_schema_invalid' => json_encode(['judgments' => ['not-an-object']], JSON_THROW_ON_ERROR),
    'judgment_subject_invalid' => json_encode(['judgments' => $wrongSubjectRows], JSON_THROW_ON_ERROR),
    'judgment_delta_invalid' => json_encode(['judgments' => $wrongDeltaRows], JSON_THROW_ON_ERROR),
    'judgment_mention_invalid' => json_encode(['judgments' => $wrongMentionRows], JSON_THROW_ON_ERROR),
    'judgment_reason_invalid' => json_encode(['judgments' => $wrongReasonRows], JSON_THROW_ON_ERROR),
    'judgment_evidence_invalid' => json_encode(['judgments' => $wrongEvidenceRows], JSON_THROW_ON_ERROR),
    'judgment_candidates_incomplete' => json_encode(['judgments' => $incompleteRows], JSON_THROW_ON_ERROR),
];
foreach ($parserFailures as $expectedReason => $invalidResponse) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $invalidModelRecords = [];
    $modelCalls = 0;
    same('failed', handleSpeechAck($ack, $db, static function (array $messages) use (&$modelCalls, $invalidResponse): string {
        $modelCalls++;
        return $invalidResponse;
    }, captureRequestLog($invalidModelRecords)), "$expectedReason must preserve the existing failed hook status.");
    same(1, $modelCalls, "$expectedReason must stop after one model request.");
    $invalidModelSummary = lastRequestSummary($invalidModelRecords);
    same($expectedReason, $invalidModelSummary['reason'] ?? null, "$expectedReason must survive in request_finished.");
    same('invalid', $invalidModelSummary['model_outcome'] ?? null, "$expectedReason must be classified as invalid model output.");
    same('warning', $invalidModelSummary['level'] ?? null, "$expectedReason must keep warning severity.");
    $invalidModelFinished = array_values(array_filter($invalidModelRecords, static fn(array $record): bool => ($record['event'] ?? null) === 'model_finished'));
    same(1, count($invalidModelFinished), "$expectedReason must emit one model_finished record.");
    same($expectedReason, $invalidModelFinished[0]['reason'] ?? null, "$expectedReason must survive in model_finished.");
    same('warning', $invalidModelFinished[0]['level'] ?? null, "$expectedReason model_finished must be warning level.");
    same(99, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, "$expectedReason must not mutate affinity.");
    same([], $db->history, "$expectedReason must not create a history snapshot.");
}

[$event, $subjects, $judgments, $db] = baseFixture();
$db->failSnapshot = true;
$persistenceRecords = [];
same('failed', handleSpeechAck($ack, $db, $loggingResponse, captureRequestLog($persistenceRecords)), 'Persistence failures must preserve the existing failed status.');
$persistenceSummary = lastRequestSummary($persistenceRecords);
same('persistence_failed', $persistenceSummary['reason'] ?? null, 'Persistence failures should finish with a stable safe reason.');
same('failed', $persistenceSummary['persistence_outcome'] ?? null, 'The summary should identify persistence failure.');
same('snapshot-verification-failed', $persistenceSummary['persistence_reason'] ?? null, 'The store should retain its specific failed persistence stage.');

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$db->failRelease = true;
$cleanupRecords = [];
same('failed', handleSpeechAck($ack, $db, $loggingResponse, captureRequestLog($cleanupRecords)), 'A cleanup failure after commit must retain the existing failed result.');
$cleanupSummary = lastRequestSummary($cleanupRecords);
same('failed', $cleanupSummary['persistence_outcome'] ?? null, 'The request summary should retain the persistence cleanup failure.');
same('persistence_failed', $cleanupSummary['reason'] ?? null, 'The request summary should classify the cleanup exception.');
same('release-failed', $cleanupSummary['persistence_reason'] ?? null, 'The store cleanup reason must survive into the request summary.');
same('confirmed', $cleanupSummary['commit_state'] ?? null, 'The request summary must preserve the confirmed commit state.');
same(true, $cleanupSummary['committed'] ?? null, 'A release failure after commit must not imply rollback.');
same(true, $cleanupSummary['cleanup_failed'] ?? null, 'The request summary must flag cleanup failure.');
same('error', $cleanupSummary['level'] ?? null, 'A cleanup-failed request summary must be emitted at error level.');
same(100, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'The fixture should retain the committed affinity after release failure.');
same(1, count($db->history), 'The confirmed commit should retain its history snapshot after release failure.');

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$throwingSink = new RequestLog(static function (string $json, string $level): void {
    throw new RuntimeException('fixture sink failure');
}, true);
same('committed', handleSpeechAck($ack, $db, $loggingResponse, $throwingSink), 'A throwing logging sink must not alter a successful ACK result.');
same(1, count($db->history), 'A throwing sink must not prevent the successful history snapshot.');

$sentinelSource = '$gameRequest = ["_speech", 0, 0, "{}"]; $store = "sentinel"; $GLOBALS["db"] = new stdClass(); require '
    . var_export(__DIR__ . '/../server/prerequest.php', true) . '; echo $store;';
$sentinelProcess = proc_open([PHP_BINARY, '-r', $sentinelSource], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $sentinelPipes);
check(is_resource($sentinelProcess), 'The bootstrap-scope sentinel subprocess should start.');
fclose($sentinelPipes[0]);
$sentinelOutput = stream_get_contents($sentinelPipes[1]);
fclose($sentinelPipes[1]);
$sentinelError = stream_get_contents($sentinelPipes[2]);
fclose($sentinelPipes[2]);
$sentinelExit = proc_close($sentinelProcess);
same(0, $sentinelExit, 'The bootstrap sentinel subprocess should exit cleanly: ' . $sentinelError);
same('sentinel', $sentinelOutput, 'The included hook must not overwrite a caller variable named $store.');

$bootstrapPayload = json_encode(['utterance_id' => 'utt_0123456789abcdef'], JSON_THROW_ON_ERROR);
$bootstrapFailureSource = '$gameRequest = ["_speech", 0, 0, ' . var_export($bootstrapPayload, true) . ']; unset($GLOBALS["db"]); require '
    . var_export(__DIR__ . '/../server/prerequest.php', true) . ';';
$bootstrapFailureProcess = proc_open([PHP_BINARY, '-r', $bootstrapFailureSource], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $bootstrapFailurePipes);
check(is_resource($bootstrapFailureProcess), 'The bootstrap-failure subprocess should start.');
fclose($bootstrapFailurePipes[0]);
$bootstrapFailureOutput = stream_get_contents($bootstrapFailurePipes[1]);
fclose($bootstrapFailurePipes[1]);
$bootstrapFailureError = stream_get_contents($bootstrapFailurePipes[2]);
fclose($bootstrapFailurePipes[2]);
$bootstrapFailureExit = proc_close($bootstrapFailureProcess);
same(0, $bootstrapFailureExit, 'A safe store-construction failure should not escape the hook: ' . $bootstrapFailureError);
same('', $bootstrapFailureOutput, 'A store-construction failure should not write to the game response.');
$bootstrapJsonStart = strpos($bootstrapFailureError, '{');
check($bootstrapJsonStart !== false, 'The bootstrap failure should emit a structured summary.');
$bootstrapSummary = json_decode(substr($bootstrapFailureError, $bootstrapJsonStart), true, 512, JSON_THROW_ON_ERROR);
same('runtime_bootstrap_failed', $bootstrapSummary['reason'] ?? null, 'Store-construction failure should have a stable safe reason.');
same('utt_0123456789abcdef', $bootstrapSummary['utterance_id'] ?? null, 'Bootstrap failures should retain a safely validated ACK correlation ID.');

$playerBootstrapSource = '$gameRequest = ["inputtext", "1740000000", "10", "Dragonborn: I met Jarl Balgruuf.", ""]; require '
    . var_export(__DIR__ . '/../server/postrequest.php', true) . ';';
$playerBootstrapProcess = proc_open([PHP_BINARY, '-r', $playerBootstrapSource], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $playerBootstrapPipes);
check(is_resource($playerBootstrapProcess), 'The Player postrequest bootstrap subprocess should start.');
fclose($playerBootstrapPipes[0]);
$playerBootstrapOutput = stream_get_contents($playerBootstrapPipes[1]);
fclose($playerBootstrapPipes[1]);
$playerBootstrapError = stream_get_contents($playerBootstrapPipes[2]);
fclose($playerBootstrapPipes[2]);
$playerBootstrapExit = proc_close($playerBootstrapProcess);
same(0, $playerBootstrapExit, 'A Player postrequest bootstrap failure should be contained.');
same('', $playerBootstrapOutput, 'A Player postrequest bootstrap failure must not write to the game response.');
$playerBootstrapJsonStart = strpos($playerBootstrapError, '{');
check($playerBootstrapJsonStart !== false, 'The Player bootstrap failure should emit a structured summary.');
$playerBootstrapSummary = json_decode(substr($playerBootstrapError, $playerBootstrapJsonStart), true, 512, JSON_THROW_ON_ERROR);
same('runtime_bootstrap_failed', $playerBootstrapSummary['reason'] ?? null, 'A Player bootstrap failure should use its safe fixed reason.');
same('player', $playerBootstrapSummary['speaker_kind'] ?? null, 'A Player bootstrap failure should retain its speaker kind.');

$priorContextDb = $GLOBALS['db'] ?? null;
$contextDb = new ProfileContextQueryDb();
$GLOBALS['db'] = $contextDb;
try {
    $contextStore = new PostgresStoreDb();
    same(['id' => 'unprofiled', 'player_name' => 'Hawke'], $contextStore->activePlaythrough(), 'The production store should resolve a zero-row profile table to the current global player context.');

    $contextDb->playerName = 'Isabela';
    $contextDb->activeProfiles = [['id' => '12']];
    same(['id' => '12', 'player_name' => 'Isabela'], $contextStore->activePlaythrough(), 'One active profile should retain the existing numeric context.');

    $contextDb->playerName = 'Hawke';
    $contextDb->profileTableExists = false;
    $contextDb->activeProfiles = [];
    same(['id' => 'unprofiled', 'player_name' => 'Hawke'], $contextStore->activePlaythrough(), 'A missing optional profile table should use the unprofiled database scope.');

    $contextDb->omitPlayerName = true;
    same(null, $contextStore->activePlaythrough(), 'A malformed current-player query result must not be interpreted as unprofiled.');
    $contextDb->omitPlayerName = false;
    $contextDb->profileTableExists = true;
    $contextDb->catalogOverride = false;
    same(null, $contextStore->activePlaythrough(), 'A non-string catalog result must fail closed instead of claiming the table is present.');
    $contextDb->catalogOverride = ['unexpected'];
    same(null, $contextStore->activePlaythrough(), 'An array catalog result must fail closed.');
    $contextDb->catalogOverride = 'auto';

    $contextDb->profileTableExists = true;
    $contextDb->activeProfiles = [['id' => '2'], ['id' => '3']];
    same(null, $contextStore->activePlaythrough(), 'The production store must reject multiple active profiles.');

    $contextDb->activeProfiles = [['id' => '0']];
    same(null, $contextStore->activePlaythrough(), 'The production store must reject an invalid active profile ID.');

    $contextDb->activeProfiles = [['id' => true]];
    same(null, $contextStore->activePlaythrough(), 'The production store must reject a boolean profile ID.');
} finally {
    if ($priorContextDb === null) {
        unset($GLOBALS['db']);
    } else {
        $GLOBALS['db'] = $priorContextDb;
    }
}

same(['id' => 'unprofiled', 'player_name' => 'Hawke'], resolvePlaythroughContext([], ' Hawke '), 'Zero active profiles should resolve to the explicit global database scope.');
same(['id' => '12', 'player_name' => 'Isabela'], resolvePlaythroughContext([['id' => '12']], ' Isabela '), 'Exactly one valid profile should keep its numeric scope and current name.');
same(['id' => '12', 'player_name' => 'Isabela'], resolvePlaythroughContext([['id' => 12]], ' Isabela '), 'An integer profile ID should normalize to its canonical decimal string.');
same(null, resolvePlaythroughContext([['id' => '2'], ['id' => '3']], 'Hawke'), 'Multiple active profiles must remain ambiguous.');
same(null, resolvePlaythroughContext([['id' => '0']], 'Hawke'), 'A nonpositive profile ID must fail closed.');
same(null, resolvePlaythroughContext([['id' => true]], 'Hawke'), 'A boolean profile ID must fail closed.');
same(null, resolvePlaythroughContext([['id' => 12.0]], 'Hawke'), 'A floating-point profile ID must fail closed.');
same(null, resolvePlaythroughContext([['id' => '012']], 'Hawke'), 'A noncanonical profile ID string must fail closed.');
same(['id' => 'unprofiled', 'player_name' => ''], resolvePlaythroughContext([], null), 'An unavailable player name must not be fabricated.');
same(['id' => 'unprofiled', 'player_name' => ''], resolvePlaythroughContext([], 42), 'A malformed player-name type must not be converted into an identity.');

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$db->profileId = 'unprofiled';
$event['playthrough_id'] = 'unprofiled';
$db->events[100]['playthrough_id'] = 'unprofiled';
$npcUnprofiledCalls = 0;
$npcUnprofiledModel = static function (array $messages) use (&$npcUnprofiledCalls): string {
    $npcUnprofiledCalls++;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible praise', 'evidence' => 'I trust Jarl Balgruuf.'],
        ['subject' => 'player', 'delta' => 0, 'reason' => 'Neutral reaction', 'evidence' => 'The Dragonborn is brave.'],
    ]);
};
same('committed', handleSpeechAck($ack, $db, $npcUnprofiledModel), 'An NPC acknowledgment should persist under the explicit unprofiled database scope.');
same('unprofiled', $db->npcs[22]['plugin_extended_data']->mind_poisoning->playthrough_id, 'The dedupe ledger should record the unprofiled scope.');
same('duplicate', handleSpeechAck($ack, $db, $npcUnprofiledModel), 'An exact replay should remain deduplicated in the unprofiled scope.');
same(1, $npcUnprofiledCalls, 'The unprofiled duplicate must stop before a second model request.');
same(1, count($db->history), 'The unprofiled duplicate must not make another history snapshot.');

resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$db->profileId = 'unprofiled';
[$playerRequest, $playerInsert] = playerInputFixture($db);
$playerUnprofiledCalls = 0;
$playerUnprofiledModel = static function (array $messages) use (&$playerUnprofiledCalls): string {
    $playerUnprofiledCalls++;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible report', 'evidence' => 'kept his promise'],
    ]);
};
same('committed', handlePlayerInput($playerRequest, $playerInsert, $db, $playerUnprofiledModel), 'A routed Player input should persist under the unprofiled database scope.');
same('unprofiled', $db->npcs[22]['plugin_extended_data']->mind_poisoning->playthrough_id, 'Player input dedupe should use the unprofiled scope too.');
same('duplicate', handlePlayerInput($playerRequest, $playerInsert, $db, $playerUnprofiledModel), 'An exact Player input replay should remain deduplicated in the unprofiled scope.');
same(1, $playerUnprofiledCalls, 'The unprofiled Player duplicate must stop before a second model request.');
same(1, count($db->history), 'The unprofiled Player duplicate must not make another history snapshot.');
unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);

foreach (['npc', 'player'] as $unprofiledPath) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $db->profileId = 'unprofiled';
    $beforeContextSwitch = unserialize(serialize($db->npcs[22]));
    $switchContextDuringModel = static function (array $messages) use ($db, $unprofiledPath): string {
        $db->profileId = '1';
        $evidence = $unprofiledPath === 'player' ? 'kept his promise' : 'I trust Jarl Balgruuf.';
        $rows = [['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible praise', 'evidence' => $evidence]];
        if ($unprofiledPath === 'npc') {
            $rows[] = ['subject' => 'player', 'delta' => 0, 'reason' => 'Neutral reaction', 'evidence' => 'The Dragonborn is brave.'];
        }
        return validModelResponse($rows);
    };
    if ($unprofiledPath === 'npc') {
        $event['playthrough_id'] = 'unprofiled';
        $db->events[100]['playthrough_id'] = 'unprofiled';
        same('stale', handleSpeechAck($ack, $db, $switchContextDuringModel), 'A profile activation during NPC model work must reject stale unprofiled context.');
    } else {
        [$playerRequest, $playerInsert] = playerInputFixture($db);
        same('stale', handlePlayerInput($playerRequest, $playerInsert, $db, $switchContextDuringModel), 'A profile activation during Player model work must reject stale unprofiled context.');
        unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);
    }
    check(ChimMindPoisoning\sameJsonValue($beforeContextSwitch, $db->npcs[22]), 'A profile context switch must not mutate listener state.');
    same([], $db->history, 'A profile context switch must not create a history snapshot.');
}

check(!file_exists($pauseControlPath), 'The pause fixture should start with no operator control file.');
$absentPauseReason = null;
same('enabled', ChimMindPoisoning\mindPoisoningPauseStatus($absentPauseReason), 'An absent pause file must preserve existing installs as enabled.');
same(null, $absentPauseReason, 'An absent pause file should not invent a diagnostic reason.');
check(file_put_contents($pauseControlPath, '{"enabled":false}') !== false, 'The disabled pause control should be written.');
foreach (['npc', 'player'] as $pausedPath) {
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $modelCalls = 0;
    $records = [];
    if ($pausedPath === 'npc') {
        $status = handleSpeechAck($ack, $db, static function (array $messages) use (&$modelCalls): string {
            $modelCalls++;
            return '{}';
        }, captureRequestLog($records));
    } else {
        [$playerRequest, $playerInsert] = playerInputFixture($db);
        $status = handlePlayerInput($playerRequest, $playerInsert, $db, static function (array $messages) use (&$modelCalls): string {
            $modelCalls++;
            return '{}';
        }, captureRequestLog($records));
        unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);
    }
    same('plugin-paused', $status, "The operator pause must stop the {$pausedPath} path before model work.");
    $summary = lastRequestSummary($records);
    same('plugin_paused', $summary['reason'] ?? null, "The {$pausedPath} pause reason must be stable.");
    same('not_called', $summary['model_outcome'] ?? null, "The {$pausedPath} pause must record no model call.");
    same(0, $modelCalls, "The {$pausedPath} pause must not call the model.");
    same(99, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, "The {$pausedPath} pause must not change affinity.");
    same([], $db->history, "The {$pausedPath} pause must not snapshot history.");
    check(!property_exists($db->npcs[22]['plugin_extended_data'], 'mind_poisoning'), "The {$pausedPath} pause must not write a ledger.");
    check(!str_contains(json_encode($records, JSON_THROW_ON_ERROR), $pauseControlPath), 'Pause-control paths must not enter logs.');
}

check(file_put_contents($pauseControlPath, " \r\n { \t\"enabled\" \r\n : \t true \n}\n") !== false, 'A whitespace-formatted enabled pause control should be written.');
resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$enabledControlCalls = 0;
$enabledControlStatus = handleSpeechAck($ack, $db, static function (array $messages) use (&$enabledControlCalls): string {
    $enabledControlCalls++;
    return validModelResponse([
        ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible praise', 'evidence' => 'I trust Jarl Balgruuf.'],
        ['subject' => 'player', 'delta' => 0, 'reason' => 'Neutral reaction', 'evidence' => 'The Dragonborn is brave.'],
    ]);
});
same('committed', $enabledControlStatus, 'An explicit enabled control must preserve the valid ACK route.');
same(1, $enabledControlCalls, 'An enabled control should make the normal single model request.');
same(1, count($db->history), 'An enabled control should preserve normal persistence.');

foreach ([['npc', true], ['player', true], ['npc', false]] as [$pausedAfterModelPath, $enabledBeforeModel]) {
    if ($enabledBeforeModel) {
        check(file_put_contents($pauseControlPath, '{"enabled":true}') !== false, 'The control should be enabled before model evaluation.');
    } else {
        @unlink($pauseControlPath);
        check(!file_exists($pauseControlPath), 'An ordinary no-control install should start enabled before model evaluation.');
    }
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $modelCalls = 0;
    $records = [];
    $pauseDuringModel = static function (array $messages) use (&$modelCalls, $pauseControlPath, $pausedAfterModelPath): string {
        $modelCalls++;
        check(file_put_contents($pauseControlPath, '{"enabled":false}') !== false, 'The model callback should pause the plugin.');
        $rows = [['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible report', 'evidence' => $pausedAfterModelPath === 'player' ? 'kept his promise' : 'I trust Jarl Balgruuf.']];
        if ($pausedAfterModelPath === 'npc') {
            $rows[] = ['subject' => 'player', 'delta' => 0, 'reason' => 'Neutral reaction', 'evidence' => 'The Dragonborn is brave.'];
        }
        return validModelResponse($rows);
    };
    if ($pausedAfterModelPath === 'npc') {
        $status = handleSpeechAck($ack, $db, $pauseDuringModel, captureRequestLog($records));
    } else {
        [$playerRequest, $playerInsert] = playerInputFixture($db);
        $status = handlePlayerInput($playerRequest, $playerInsert, $db, $pauseDuringModel, captureRequestLog($records));
        unset($GLOBALS['PLAYER_TTS_SOURCE_TEXT']);
    }
    $pauseSource = $enabledBeforeModel ? 'enabled' : 'absent';
    same('plugin-paused', $status, "A pause during model work from {$pauseSource} control state must block {$pausedAfterModelPath} persistence.");
    same('plugin_paused', lastRequestSummary($records)['reason'] ?? null, "The post-model {$pausedAfterModelPath} pause should be logged safely.");
    same(1, $modelCalls, "The post-model {$pausedAfterModelPath} case should make one model call.");
    same(99, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, "The post-model {$pausedAfterModelPath} pause must leave affinity unchanged.");
    same([], $db->history, "The post-model {$pausedAfterModelPath} pause must not create history.");
    check(!property_exists($db->npcs[22]['plugin_extended_data'], 'mind_poisoning'), "The post-model {$pausedAfterModelPath} pause must not write a ledger.");
}

check(file_put_contents($pauseControlPath, '{"enabled":"false","secret":"PAUSE_PRIVATE_CONTENT"}') !== false, 'The malformed control should be written.');
resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$invalidControlCalls = 0;
$invalidControlRecords = [];
same('control-invalid', handleSpeechAck($ack, $db, static function (array $messages) use (&$invalidControlCalls): string {
    $invalidControlCalls++;
    return '{}';
}, captureRequestLog($invalidControlRecords)), 'A known malformed control must fail closed.');
same('pause_control_invalid', lastRequestSummary($invalidControlRecords)['reason'] ?? null, 'Malformed control must have a fixed safe reason.');
same('warning', lastRequestSummary($invalidControlRecords)['level'] ?? null, 'Malformed control must be visible as a warning.');
check(!str_contains(json_encode($invalidControlRecords, JSON_THROW_ON_ERROR), 'PAUSE_PRIVATE_CONTENT'), 'Pause-control contents must never enter logs.');
same(0, $invalidControlCalls, 'Malformed control must stop before model work.');
same([], $db->history, 'Malformed control must not create history.');

foreach ([
    '{"enabled":false,"enabled":true}',
    '{"enabled":true,"enabled":false}',
] as $duplicateControl) {
    check(file_put_contents($pauseControlPath, $duplicateControl) !== false, 'A duplicate-key control should be written.');
    resetAckLoggingInteraction();
    [$event, $subjects, $judgments, $db] = baseFixture();
    $duplicateControlCalls = 0;
    $duplicateControlRecords = [];
    same('control-invalid', handleSpeechAck($ack, $db, static function (array $messages) use (&$duplicateControlCalls): string {
        $duplicateControlCalls++;
        return validModelResponse([
            ['subject' => 'npc:33', 'delta' => 1, 'reason' => 'Credible praise', 'evidence' => 'I trust Jarl Balgruuf.'],
            ['subject' => 'player', 'delta' => 0, 'reason' => 'Neutral reaction', 'evidence' => 'The Dragonborn is brave.'],
        ]);
    }, captureRequestLog($duplicateControlRecords)), 'Duplicate enabled keys must fail closed in either value order.');
    same('pause_control_invalid', lastRequestSummary($duplicateControlRecords)['reason'] ?? null, 'Duplicate enabled keys must use the fixed safe reason.');
    same(0, $duplicateControlCalls, 'Duplicate enabled keys must stop before model work.');
    same(99, $db->npcs[22]['extended_data']->relationships->{'Jarl Balgruuf'}->aff, 'Duplicate enabled keys must not change affinity.');
    same([], $db->history, 'Duplicate enabled keys must not create history.');
    check(!property_exists($db->npcs[22]['plugin_extended_data'], 'mind_poisoning'), 'Duplicate enabled keys must not write a ledger.');
}

@unlink($pauseControlPath);
check(mkdir($pauseControlPath), 'The unreadable non-file control fixture should be created.');
resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$unreadableControlCalls = 0;
same('control-invalid', handleSpeechAck($ack, $db, static function (array $messages) use (&$unreadableControlCalls): string {
    $unreadableControlCalls++;
    return '{}';
}), 'A non-file control at the known path must fail closed.');
same(0, $unreadableControlCalls, 'An unreadable/non-file control must stop before model work.');
@rmdir($pauseControlPath);

$invalidRoot = $pauseTestRoot . DIRECTORY_SEPARATOR . 'invalid-root';
check(mkdir($invalidRoot . DIRECTORY_SEPARATOR . 'data', 0700, true), 'The invalid-root fixture should be created.');
check(file_put_contents($invalidRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mind_poisoning.json', '{"enabled":false}') !== false, 'The invalid-root control should be written.');
$validEnginePath = $GLOBALS['ENGINE_PATH'];
$GLOBALS['ENGINE_PATH'] = $invalidRoot . DIRECTORY_SEPARATOR;
resetAckLoggingInteraction();
[$event, $subjects, $judgments, $db] = baseFixture();
$invalidRootCalls = 0;
same('control-invalid', handleSpeechAck($ack, $db, static function (array $messages) use (&$invalidRootCalls): string {
    $invalidRootCalls++;
    return '{}';
}), 'An explicit ENGINE_PATH without the CHIM root marker must not be treated as no control file.');
same(0, $invalidRootCalls, 'An invalid ENGINE_PATH must stop before model work.');
$GLOBALS['ENGINE_PATH'] = $validEnginePath;
@unlink($invalidRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mind_poisoning.json');
@rmdir($invalidRoot . DIRECTORY_SEPARATOR . 'data');
@rmdir($invalidRoot);

$linkedDataRoot = $pauseTestRoot . DIRECTORY_SEPARATOR . 'linked-data';
check(mkdir($linkedDataRoot, 0700), 'The symlink target data directory should be created.');
check(rmdir($pauseTestData), 'The original empty data directory should be removed.');
if (@symlink($linkedDataRoot, $pauseTestData)) {
    $linkedDataReason = null;
    same('enabled', ChimMindPoisoning\mindPoisoningPauseStatus($linkedDataReason), 'A valid linked data directory with no control file must preserve default-enabled behavior.');
    same(null, $linkedDataReason, 'A valid linked data directory should not invent a diagnostic reason.');
    check(unlink($pauseTestData), 'The temporary data symlink should be removed.');
} else {
    echo "runtime symlink checks skipped: Windows did not permit symlink creation\n";
}
check(mkdir($pauseTestData, 0700), 'The original temporary data directory should be restored.');
check(rmdir($linkedDataRoot), 'The temporary linked data target should be removed.');

$emptyRoot = $pauseTestRoot . DIRECTORY_SEPARATOR . 'empty-root';
check(mkdir($emptyRoot, 0700), 'The empty CHIM root fixture should be created.');
if (@symlink($pauseTestRoot . DIRECTORY_SEPARATOR . 'main.php', $emptyRoot . DIRECTORY_SEPARATOR . 'main.php')) {
    $validEnginePath = $GLOBALS['ENGINE_PATH'];
    $GLOBALS['ENGINE_PATH'] = $emptyRoot . DIRECTORY_SEPARATOR;
    $emptyRootReason = null;
    same('enabled', ChimMindPoisoning\mindPoisoningPauseStatus($emptyRootReason), 'A valid CHIM root without a data directory must remain enabled.');
    same(null, $emptyRootReason, 'A missing data directory should not invent a diagnostic reason.');
    $GLOBALS['ENGINE_PATH'] = $validEnginePath;
    @unlink($emptyRoot . DIRECTORY_SEPARATOR . 'main.php');
} else {
    echo "runtime root symlink check skipped: Windows did not permit symlink creation\n";
}
@rmdir($emptyRoot);

unset($GLOBALS['runtime_test_interaction_allowed'], $GLOBALS['runtime_test_interaction_generation'], $GLOBALS['runtime_test_relationship_enabled'], $GLOBALS['RELLLM_CONNECTOR']);
unset($_SERVER['HTTP_X_CHIM_GENERATION'], $_SERVER['HTTP_X_CHIM_PASSIVE']);

echo "runtime store checks passed\n";
