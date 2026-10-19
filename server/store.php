<?php
declare(strict_types=1);

namespace ChimMindPoisoning;

use JsonException;
use RuntimeException;
use Throwable;

const MIND_POISONING_REFLECTION_REPLY_MAX_LINES = 24;

require_once __DIR__ . '/logging.php';
require_once __DIR__ . '/influence.php';

interface StoreDb
{
    public function activePlaythrough(): ?array;
    public function acknowledgedEvent(string $utteranceId): ?array;
    public function playerInputEvent(array $source): ?array;
    public function eventById(int $eventId, string $utteranceId): ?array;
    public function npcIdentities(): array;
    public function npcById(int $npcId, bool $forUpdate = false): ?array;
    public function reflectionHistory(string $actorName, int $beforeEventId): array;
    public function beginForListener(int $listenerId): bool;
    public function writeNpc(
        int $npcId,
        array $relationshipEdges,
        object $mindPoisoningData,
        float $gamets,
        array $relationshipKeysToRemove = []
    ): bool;
    public function backupAndVerify(int $npcId, array $expected): bool;
    public function commit(): bool;
    public function rollback(): void;
    public function release(): void;
}

/** Optional exact eventlog.people snapshots for the guarded NPC overhearing path. */
interface WitnessSnapshotStoreDb
{
    public function acknowledgedEventWithPeople(string $utteranceId): ?array;
    public function eventByIdWithPeople(int $eventId, string $utteranceId): ?array;
}

/** Optional state and process barrier used only when core preserves relationships across INIT. */
interface RestorePolicyStoreDb
{
    /** @return array{latest_init_event_id: int, max_event_id: int}|null */
    public function restoreEventBounds(): ?array;
    public function acquireRestorePolicyBarrier(): bool;
    public function releaseRestorePolicyBarrier(): void;
}

require_once __DIR__ . '/restore_policy.php';

function resolvePlaythroughContext(array $profileRows, mixed $playerName): ?array
{
    $name = static fn(mixed $value): string => is_string($value) ? trim($value) : '';
    if ($profileRows === []) {
        return ['id' => 'unprofiled', 'player_name' => $name($playerName)];
    }
    if (count($profileRows) !== 1 || !is_array($profileRows[0])) {
        return null;
    }
    $idValue = $profileRows[0]['id'] ?? null;
    if (is_int($idValue) && $idValue > 0) {
        $id = (string)$idValue;
    } elseif (is_string($idValue) && preg_match('/\A[1-9][0-9]{0,18}\z/D', $idValue) === 1) {
        $id = $idValue;
    } else {
        return null;
    }
    return [
        'id' => $id,
        'player_name' => $name($playerName),
    ];
}

function assertPgSqlConnection(mixed $connection): void
{
    if (!class_exists(\PgSql\Connection::class) || !$connection instanceof \PgSql\Connection) {
        throw new RuntimeException('Mind Poisoning requires the inspected PgSql connection type.');
    }
}

function assertIdleTransactionStatus(int $status): void
{
    if ($status !== PGSQL_TRANSACTION_IDLE) {
        throw new RuntimeException('Mind Poisoning will not nest inside a caller transaction.');
    }
}

function decodeJsonObject(mixed $value, string $field): object
{
    if (is_object($value)) {
        return $value;
    }
    if ($value === null || $value === '') {
        return new \stdClass();
    }
    if (!is_string($value)) {
        throw new RuntimeException("Invalid stored {$field} value.");
    }
    try {
        $decoded = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException("Invalid stored {$field} JSON.", 0, $error);
    }
    if (!is_object($decoded)) {
        throw new RuntimeException("Stored {$field} must be a JSON object.");
    }
    return $decoded;
}

function knownLedgerArray(mixed $value): ?array
{
    if ($value instanceof \stdClass) {
        $value = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }
    return is_array($value) ? $value : null;
}

function validHistoricalTypeToken(mixed $type): bool
{
    return is_string($type) && strlen($type) <= 50 && preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,49}\z/D', $type) === 1;
}

function storedLedgerNamespace(object $pluginExtendedData): ?array
{
    if (!property_exists($pluginExtendedData, 'mind_poisoning')) {
        return [];
    }
    $ledger = knownLedgerArray($pluginExtendedData->mind_poisoning);
    if (
        !is_array($ledger)
        || !is_string($ledger['playthrough_id'] ?? null) || $ledger['playthrough_id'] === ''
        || !is_int($ledger['floor_event_id'] ?? null) || $ledger['floor_event_id'] < 0
        || !is_array($ledger['events'] ?? null) || !array_is_list($ledger['events'])
        || count($ledger['events']) > 128
    ) {
        return null;
    }
    if (array_key_exists('reflection_state', $ledger)) {
        $state = $ledger['reflection_state'];
        if (
            !is_array($state)
            || array_keys($state) !== ['basis', 'subjects']
            || !is_string($state['basis'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/D', $state['basis']) !== 1
            || !is_array($state['subjects'] ?? null) || !array_is_list($state['subjects'])
            || count($state['subjects']) > 32
        ) {
            return null;
        }
        $seen = [];
        foreach ($state['subjects'] as $subject) {
            if (
                !is_string($subject)
                || ($subject !== 'player' && preg_match('/\Anpc:[1-9][0-9]*\z/D', $subject) !== 1)
                || isset($seen[$subject])
            ) {
                return null;
            }
            $seen[$subject] = true;
        }
    }
    $hasReflectionEvents = false;
    foreach ($ledger['events'] as $entry) {
        if (
            !is_array($entry)
            || !is_int($entry['event_id'] ?? null) || $entry['event_id'] < 1
            || !is_string($entry['utterance_id'] ?? null) || $entry['utterance_id'] === ''
            || (array_key_exists('source_kind', $entry) && $entry['source_kind'] !== 'reflection')
            || (($entry['source_kind'] ?? null) === 'reflection' && !is_array($entry['judgments'] ?? null))
        ) {
            return null;
        }
        $isReflectionEntry = ($entry['source_kind'] ?? null) === 'reflection';
        if ($isReflectionEntry) {
            $hasReflectionEvents = true;
            if (!is_array($entry['judgments'] ?? null) || !array_is_list($entry['judgments']) || count($entry['judgments']) > 8) {
                return null;
            }
            foreach ($entry['judgments'] as $judgment) {
                $judgmentKeys = is_array($judgment) ? array_keys($judgment) : [];
                sort($judgmentKeys, SORT_STRING);
                $typeChange = is_array($judgment) ? ($judgment['type_change'] ?? null) : null;
                $typeChangeKeys = is_array($typeChange) ? array_keys($typeChange) : [];
                sort($typeChangeKeys, SORT_STRING);
                if (
                    !is_array($judgment)
                    || ($judgmentKeys !== ['delta', 'evidence', 'reason', 'subject']
                        && $judgmentKeys !== ['delta', 'evidence', 'reason', 'subject', 'type_change'])
                    || !is_string($judgment['subject'] ?? null)
                    || ($judgment['subject'] !== 'player' && preg_match('/\Anpc:[1-9][0-9]*\z/D', $judgment['subject']) !== 1)
                    || !is_int($judgment['delta'] ?? null) || $judgment['delta'] < -5 || $judgment['delta'] > 5
                    || !is_string($judgment['reason'] ?? null) || strlen($judgment['reason']) > 120
                    || !is_string($judgment['evidence'] ?? null) || strlen($judgment['evidence']) > 120
                    || (array_key_exists('type_change', $judgment) && (
                        $typeChange !== null && (
                            !is_array($typeChange)
                            || array_is_list($typeChange)
                            || $typeChangeKeys !== ['from', 'to']
                            || !validHistoricalTypeToken($typeChange['from'] ?? null)
                            || !validHistoricalTypeToken($typeChange['to'] ?? null)
                        )
                    ))
                ) {
                    return null;
                }
            }
        } elseif (is_array($entry['judgments'] ?? null) && array_is_list($entry['judgments'])) {
            foreach ($entry['judgments'] as $judgment) {
                if (!is_array($judgment) || !array_key_exists('type_change', $judgment)) {
                    continue;
                }
                $typeChange = $judgment['type_change'];
                if ($typeChange === null) {
                    continue;
                }
                $typeChangeKeys = is_array($typeChange) ? array_keys($typeChange) : [];
                sort($typeChangeKeys, SORT_STRING);
                if (
                    !is_array($typeChange) || array_is_list($typeChange)
                    || $typeChangeKeys !== ['from', 'to']
                    || !validHistoricalTypeToken($typeChange['from'] ?? null)
                    || !validHistoricalTypeToken($typeChange['to'] ?? null)
                ) {
                    return null;
                }
            }
        }
        $typeDecisions = [];
        if (array_key_exists('type_decisions', $entry)) {
            if (!is_array($entry['type_decisions']) || !array_is_list($entry['type_decisions']) || count($entry['type_decisions']) > 8) {
                return null;
            }
            foreach ($entry['type_decisions'] as $decision) {
                $keys = is_array($decision) ? array_keys($decision) : [];
                sort($keys, SORT_STRING);
                if (
                    !is_array($decision)
                    || $keys !== ['from', 'reason', 'subject', 'to']
                    || !is_string($decision['subject'] ?? null)
                    || ($decision['subject'] !== 'player' && preg_match('/\Anpc:[1-9][0-9]*\z/D', $decision['subject']) !== 1)
                    || isset($typeDecisions[$decision['subject']])
                    || !validHistoricalTypeToken($decision['from'] ?? null)
                    || !validHistoricalTypeToken($decision['to'] ?? null)
                    || !in_array($decision['reason'] ?? null, ['change_eligible', 'same_type', 'protected_type_preserved', 'custom_type_preserved'], true)
                ) {
                    return null;
                }
                $typeDecisions[$decision['subject']] = $decision;
            }
        }
        $typeChanges = [];
        if (array_key_exists('type_changes', $entry)) {
            if (!is_array($entry['type_changes']) || !array_is_list($entry['type_changes']) || count($entry['type_changes']) > 8) {
                return null;
            }
            foreach ($entry['type_changes'] as $change) {
                $keys = is_array($change) ? array_keys($change) : [];
                sort($keys, SORT_STRING);
                if (
                    !is_array($change)
                    || $keys !== ['after', 'before', 'subject']
                    || !is_string($change['subject'] ?? null)
                    || ($change['subject'] !== 'player' && preg_match('/\Anpc:[1-9][0-9]*\z/D', $change['subject']) !== 1)
                    || isset($typeChanges[$change['subject']])
                    || !validHistoricalTypeToken($change['before'] ?? null)
                    || !validHistoricalTypeToken($change['after'] ?? null)
                    || $change['before'] === $change['after']
                    || !isset($typeDecisions[$change['subject']])
                    || ($typeDecisions[$change['subject']]['reason'] ?? null) !== 'change_eligible'
                    || ($typeDecisions[$change['subject']]['from'] ?? null) !== $change['before']
                    || ($typeDecisions[$change['subject']]['to'] ?? null) !== $change['after']
                ) {
                    return null;
                }
                $typeChanges[$change['subject']] = $change;
            }
        }
    }
    if ($hasReflectionEvents && !array_key_exists('reflection_state', $ledger)) {
        return null;
    }
    return $ledger;
}

function jsonValueKey(mixed $value): string
{
    if ($value instanceof \stdClass) {
        $properties = get_object_vars($value);
        ksort($properties, SORT_STRING);
        $canonical = new \stdClass();
        foreach ($properties as $key => $item) {
            $canonical->{$key} = canonicalJsonValue($item);
        }
        return json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return json_encode(canonicalJsonValue($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function canonicalJsonValue(mixed $value): mixed
{
    if ($value instanceof \stdClass) {
        $properties = get_object_vars($value);
        ksort($properties, SORT_STRING);
        $canonical = new \stdClass();
        foreach ($properties as $key => $item) {
            $canonical->{$key} = canonicalJsonValue($item);
        }
        return $canonical;
    }
    if (is_array($value)) {
        if (array_is_list($value)) {
            return array_map('ChimMindPoisoning\\canonicalJsonValue', $value);
        }
        ksort($value, SORT_STRING);
        $canonical = new \stdClass();
        foreach ($value as $key => $item) {
            $canonical->{(string)$key} = canonicalJsonValue($item);
        }
        return $canonical;
    }
    return $value;
}

function sameJsonValue(mixed $left, mixed $right): bool
{
    return jsonValueKey($left) === jsonValueKey($right);
}

function normalizeEventRow(array $row, bool $includePeople = false): ?array
{
    $eventId = filter_var($row['rowid'] ?? null, FILTER_VALIDATE_INT);
    $utteranceId = $row['utterance_id'] ?? null;
    $gamets = $row['gamets'] ?? null;
    $sourceData = $row['data'] ?? null;
    $deliveryState = $row['delivery_state'] ?? null;
    if (
        $eventId === false || $eventId < 1
        || ($row['type'] ?? null) !== 'chat'
        || !is_string($utteranceId)
        || !is_numeric($gamets) || !is_finite((float)$gamets)
        || !is_string($sourceData)
        || !in_array($deliveryState, ['emitted', 'spoken'], true)
    ) {
        return null;
    }

    $event = [
        'event_id' => (int)$eventId,
        'utterance_id' => $utteranceId,
        'gamets' => (float)$gamets,
        'source_data' => $sourceData,
        'delivery_state' => $deliveryState,
    ];
    if ($includePeople) {
        $event['source_people'] = $row['people'] ?? null;
        $event['source_people_oversized'] = filter_var($row['people_oversized'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
    return $event;
}

function playerInputTypes(): array
{
    return ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'];
}

function normalizePlayerInputRow(array $row): ?array
{
    $eventId = filter_var($row['rowid'] ?? null, FILTER_VALIDATE_INT);
    $type = $row['type'] ?? null;
    $ts = $row['ts'] ?? null;
    $gamets = $row['gamets'] ?? null;
    $localts = filter_var($row['localts'] ?? null, FILTER_VALIDATE_INT);
    $data = $row['data'] ?? null;
    $sess = $row['sess'] ?? null;
    $people = $row['people'] ?? null;
    if (
        $eventId === false || $eventId < 1
        || !is_string($type) || !in_array($type, playerInputTypes(), true)
        || (!is_string($ts) && !is_int($ts)) || !is_numeric($ts) || !is_finite((float)$ts)
        || (!is_string($gamets) && !is_int($gamets) && !is_float($gamets))
        || !is_numeric($gamets) || !is_finite((float)$gamets)
        || $localts === false
        || !is_string($data)
        || $sess !== 'web'
        || !is_string($people) || trim($people) === ''
        || (array_key_exists('source_count', $row) && (string)$row['source_count'] !== '1')
    ) {
        return null;
    }

    return [
        'event_id' => (int)$eventId,
        'utterance_id' => 'input_' . (int)$eventId,
        'speaker_kind' => 'player',
        'speaker_id' => null,
        'source_kind' => 'player_input',
        'source_type' => $type,
        'source_ts' => (string)$ts,
        'source_gamets' => (string)$gamets,
        'gamets' => (float)$gamets,
        'source_data' => $data,
        'source_localts' => (int)$localts,
        'source_sess' => $sess,
        'source_people' => $people,
    ];
}

function playerInputSourceParameters(array $source): ?array
{
    $type = $source['type'] ?? null;
    $ts = $source['ts'] ?? null;
    $gamets = $source['gamets'] ?? null;
    $data = $source['data'] ?? null;
    $localts = $source['localts'] ?? null;
    $sess = $source['sess'] ?? null;
    if (
        !is_string($type) || !in_array($type, playerInputTypes(), true)
        || (!is_string($ts) && !is_int($ts)) || !is_numeric($ts) || !is_finite((float)$ts)
        || (!is_string($gamets) && !is_int($gamets))
        || !is_numeric($gamets) || !is_finite((float)$gamets)
        || !is_string($data)
        || !is_int($localts)
        || $sess !== 'web'
    ) {
        return null;
    }
    return [$type, $ts, $gamets, $data, $localts, $sess];
}

function eventAlreadyProcessed(array $npc, string $playthroughId, int $eventId, string $utteranceId, ?string &$reason = null): bool
{
    $reason = null;
    $plugins = $npc['plugin_extended_data'] ?? null;
    if (!$plugins instanceof \stdClass) {
        $reason = 'ledger-invalid';
        return true;
    }
    $ledger = storedLedgerNamespace($plugins);
    if ($ledger === null) {
        $reason = 'ledger-invalid';
        return true;
    }
    if ($ledger === [] || $ledger['playthrough_id'] !== $playthroughId) {
        return false;
    }
    $floor = $ledger['floor_event_id'];
    $entries = $ledger['events'];
    if ($eventId <= $floor) {
        $reason = 'ledger-floor';
        return true;
    }
    foreach ($entries as $entry) {
        if ($entry['event_id'] === $eventId || $entry['utterance_id'] === $utteranceId) {
            $reason = 'duplicate-event';
            return true;
        }
    }
    return false;
}

function reflectionBasisProcessed(array $owner, string $playthroughId, string $basis, string $subject): bool
{
    if (!is_string($basis) || preg_match('/\A[a-f0-9]{64}\z/D', $basis) !== 1) {
        return true;
    }
    $plugins = $owner['plugin_extended_data'] ?? null;
    $ledger = $plugins instanceof \stdClass ? storedLedgerNamespace($plugins) : null;
    if ($ledger === null) {
        return true;
    }
    if ($ledger === [] || $ledger['playthrough_id'] !== $playthroughId) {
        return false;
    }
    $state = $ledger['reflection_state'] ?? null;
    if ($state === null || $state['basis'] !== $basis) {
        return false;
    }
    return in_array($subject, $state['subjects'], true) || count($state['subjects']) >= 32;
}

function nextReflectionState(array $ledger, string $basis, array $subjects): array
{
    if (preg_match('/\A[a-f0-9]{64}\z/D', $basis) !== 1 || count($subjects) > 8) {
        throw new RuntimeException('Reflection basis is invalid.');
    }
    $previous = $ledger['reflection_state'] ?? null;
    $tokens = is_array($previous) && ($previous['basis'] ?? null) === $basis
        ? ($previous['subjects'] ?? [])
        : [];
    foreach ($subjects as $subject) {
        if (!is_string($subject) || ($subject !== 'player' && preg_match('/\Anpc:[1-9][0-9]*\z/D', $subject) !== 1)) {
            throw new RuntimeException('Reflection subject is invalid.');
        }
        if (!in_array($subject, $tokens, true)) {
            $tokens[] = $subject;
        }
    }
    if (count($tokens) > 32) {
        throw new RuntimeException('Reflection basis subject limit was reached.');
    }
    sort($tokens, SORT_STRING);
    return ['basis' => $basis, 'subjects' => array_values($tokens)];
}

function validSubjectsAndJudgments(array $subjects, array $judgments, array $event): bool
{
    if (count($subjects) > 8 || count($subjects) !== count($judgments)) {
        return false;
    }
    foreach ($subjects as $token => $subject) {
        if (!is_string($token) || !is_array($subject) || !is_string($subject['name'] ?? null)) {
            return false;
        }
        if ($token === 'player') {
            if (
                ($event['speaker_kind'] ?? 'npc') === 'player'
                || $subject['name'] !== 'Player'
                || !array_key_exists('id', $subject)
                || $subject['id'] !== null
            ) {
                return false;
            }
        } elseif (
            preg_match('/\Anpc:([1-9][0-9]*)\z/', $token, $matches) !== 1
            || !is_int($subject['id'] ?? null)
            || (int)$matches[1] !== $subject['id']
            || $subject['id'] === ($event['speaker_id'] ?? null)
            || $subject['id'] === ($event['listener_id'] ?? null)
            || trim($subject['name']) === ''
        ) {
            return false;
        }

        $judgment = $judgments[$token] ?? null;
        if (
            !is_array($judgment)
            || !is_int($judgment['delta'] ?? null)
            || $judgment['delta'] < -5 || $judgment['delta'] > 5
            || !is_string($judgment['reason'] ?? null) || trim($judgment['reason']) === '' || strlen($judgment['reason']) > 600
            || !is_string($judgment['evidence'] ?? null) || trim($judgment['evidence']) === '' || strlen($judgment['evidence']) > 600
            || !is_string($event['text'] ?? null)
            || !str_contains($event['text'], $judgment['evidence'])
        ) {
            return false;
        }
        if (array_key_exists('type_change', $judgment) && $judgment['type_change'] !== null) {
            $typeChange = $judgment['type_change'];
            $keys = is_array($typeChange) ? array_keys($typeChange) : [];
            sort($keys, SORT_STRING);
            if (
                !is_array($typeChange) || array_is_list($typeChange)
                || $keys !== ['from', 'to']
                || !is_string($typeChange['from']) || strlen($typeChange['from']) > 50
                || !is_string($typeChange['to']) || strlen($typeChange['to']) > 50
                || ($canonicalFrom = canonicalRelationshipType($typeChange['from'], [$typeChange['from']])) !== $typeChange['from']
                || ($canonicalTo = canonicalRelationshipType($typeChange['to'])) !== $typeChange['to']
                || !in_array($canonicalTo, relationshipTypeAllowedTypes() ?? [], true)
            ) {
                return false;
            }
        }
    }
    foreach ($judgments as $token => $_judgment) {
        if (!is_string($token) || !array_key_exists($token, $subjects)) {
            return false;
        }
    }
    return true;
}

function sameActorName(mixed $left, mixed $right): bool
{
    return is_string($left) && is_string($right)
        && mb_strtolower(trim($left), 'UTF-8') === mb_strtolower(trim($right), 'UTF-8');
}

function playerInputIncludesListener(array $event, string $listenerName): bool
{
    $people = $event['source_people'] ?? null;
    if (!is_string($people) || trim($people) === '' || !function_exists('parsePeoplePipeList')) {
        return false;
    }
    try {
        $names = \parsePeoplePipeList($people);
    } catch (Throwable) {
        return false;
    }
    if (!is_array($names)) {
        return false;
    }
    $matches = 0;
    foreach ($names as $name) {
        if (sameActorName($name, $listenerName)) {
            $matches++;
        }
    }
    return $matches === 1;
}

function nextLedger(
    array $ledger,
    string $playthroughId,
    int $eventId,
    string $utteranceId,
    array $judgments,
    ?array $reflectionState = null,
    array $typeDecisions = [],
    array $typeChanges = []
): array
{
    if (($ledger['playthrough_id'] ?? null) !== $playthroughId) {
        $ledger = ['playthrough_id' => $playthroughId, 'floor_event_id' => 0, 'events' => []];
    }
    $floor = $ledger['floor_event_id'] ?? 0;
    $events = $ledger['events'] ?? [];
    if (!is_int($floor) || $floor < 0 || !is_array($events) || count($events) > 128) {
        throw new RuntimeException('Stored Mind Poisoning ledger is invalid.');
    }
    if ($eventId <= $floor) {
        throw new RuntimeException('The event is older than the bounded dedupe ledger floor.');
    }
    foreach ($events as $entry) {
        if (!is_array($entry) || !is_int($entry['event_id'] ?? null) || !is_string($entry['utterance_id'] ?? null)) {
            throw new RuntimeException('Stored Mind Poisoning ledger entry is invalid.');
        }
        if ($entry['event_id'] === $eventId || $entry['utterance_id'] === $utteranceId) {
            throw new RuntimeException('The event has already been recorded.');
        }
    }

    $details = [];
    foreach ($judgments as $token => $judgment) {
        $detail = [
            'subject' => $token,
            'delta' => $judgment['delta'],
            'reason' => mb_strcut($judgment['reason'], 0, 120, 'UTF-8'),
            'evidence' => mb_strcut($judgment['evidence'], 0, 120, 'UTF-8'),
        ];
        if (is_array($judgment['type_change'] ?? null)) {
            $detail['type_change'] = $judgment['type_change'];
        }
        $details[] = $detail;
    }
    $entry = ['event_id' => $eventId, 'utterance_id' => $utteranceId, 'judgments' => $details];
    if ($reflectionState !== null) {
        $entry['source_kind'] = 'reflection';
    }
    if ($typeDecisions !== []) {
        $entry['type_decisions'] = $typeDecisions;
    }
    if ($typeChanges !== []) {
        $entry['type_changes'] = $typeChanges;
    }
    $events[] = $entry;
    usort($events, static fn(array $left, array $right): int => $left['event_id'] <=> $right['event_id']);
    if (count($events) > 128) {
        $removed = array_splice($events, 0, count($events) - 128);
        foreach ($removed as $entry) {
            $floor = max($floor, $entry['event_id']);
        }
    }
    $result = ['playthrough_id' => $playthroughId, 'floor_event_id' => $floor, 'events' => array_values($events)];
    if ($reflectionState !== null) {
        $result['reflection_state'] = $reflectionState;
    } elseif (is_array($ledger['reflection_state'] ?? null)) {
        $result['reflection_state'] = $ledger['reflection_state'];
    }
    return $result;
}

function reflectionSourceSnapshotsValid(array $event): bool
{
    $sources = $event['reflection_sources'] ?? null;
    if (!is_array($sources) || !array_is_list($sources) || count($sources) < 1 || count($sources) > MIND_POISONING_REFLECTION_REPLY_MAX_LINES) {
        return false;
    }
    $texts = [];
    $previousEventId = 0;
    $seenUtterances = [];
    foreach ($sources as $source) {
        $keys = is_array($source) ? array_keys($source) : [];
        sort($keys, SORT_STRING);
        if (
            $keys !== ['event_id', 'gamets', 'source_data', 'speech_hash', 'text', 'utterance_id']
            || !is_int($source['event_id'] ?? null) || $source['event_id'] <= $previousEventId
            || !is_string($source['utterance_id'] ?? null) || preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/D', $source['utterance_id']) !== 1
            || isset($seenUtterances[$source['utterance_id']])
            || !is_string($source['speech_hash'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/D', $source['speech_hash']) !== 1
            || (!is_int($source['gamets'] ?? null) && !is_float($source['gamets']) && !(is_string($source['gamets'] ?? null) && is_numeric($source['gamets'])))
            || !is_finite((float)$source['gamets'])
            || !is_string($source['source_data'] ?? null) || !is_string($source['text'] ?? null)
        ) {
            return false;
        }
        $parts = reflectionSourceParts($source['source_data']);
        if (
            !is_array($parts) || !sameActorName($parts['speaker'] ?? null, $event['speaker_name'] ?? null)
            || ($parts['text'] ?? null) !== $source['text']
            || !hash_equals($source['speech_hash'], hash('sha256', $source['text']))
        ) {
            return false;
        }
        $previousEventId = $source['event_id'];
        $seenUtterances[$source['utterance_id']] = true;
        $texts[] = $source['text'];
    }
    $joined = implode(' ', $texts);
    $last = $sources[array_key_last($sources)];
    return mb_strlen($joined, 'UTF-8') <= 2000 && strlen($joined) <= 8000
        && ($event['text'] ?? null) === $joined
        && ($event['event_id'] ?? null) === $last['event_id']
        && ($event['utterance_id'] ?? null) === $last['utterance_id']
        && ($event['gamets'] ?? null) !== null && (float)$event['gamets'] === (float)$last['gamets']
        && ($event['source_data'] ?? null) === $last['source_data'];
}

function persistJudgments(
    array $event,
    array $subjects,
    array $judgments,
    StoreDb $store,
    ?RequestLog $requestLog = null,
    ?callable $revalidateReflection = null,
    ?array $registration = null,
    ?callable $revalidateListenerScope = null
): string
{
    $startedAt = hrtime(true);
    $committed = false;
    $commitAttempted = false;
    $cleanupRequired = false;
    $cleanupFailed = false;
    $cleanupError = null;
    $cleanupReason = '';
    $restorePolicyBarrierHeld = false;
    $restorePolicyEnabled = false;
    $restorePolicyInitId = $event['restore_policy_init_event_id'] ?? null;
    $status = 'failed';
    $reason = 'persistence-failed';
    $stage = 'validation';
    $speakerKind = array_key_exists('speaker_kind', $event) ? $event['speaker_kind'] : 'npc';
    $reflection = ($event['source_kind'] ?? null) === 'reflection';
    $hasListenerRole = array_key_exists('listener_role', $event);
    $listenerRole = $event['listener_role'] ?? null;
    $hasReplySources = array_key_exists('reflection_sources', $event);
    $replySources = $event['reflection_sources'] ?? null;
    $replySourcesValid = !$hasReplySources || ($reflection && reflectionSourceSnapshotsValid($event));
    $playerSpeaker = $speakerKind === 'player';
    $ownerId = $reflection ? ($event['opinion_owner_id'] ?? null) : ($event['listener_id'] ?? null);
    $edgeChanges = [];
    $typeDecisions = [];
    $typeChanges = [];
    $done = static function (string $outcome, string $code) use (&$status, &$reason): string {
        $status = $outcome;
        $reason = $code;
        return $outcome;
    };

    $requestLog?->context([
        'event_id' => $event['event_id'] ?? null,
        'utterance_id' => $event['utterance_id'] ?? null,
        'playthrough_id' => $event['playthrough_id'] ?? null,
        'speaker_id' => $event['speaker_id'] ?? null,
        'speaker_kind' => $event['speaker_kind'] ?? 'npc',
        'listener_id' => $event['listener_id'] ?? null,
        'opinion_owner_id' => $reflection ? $ownerId : null,
        'source_kind' => $reflection ? 'reflection' : null,
        'cleanup_failed' => false,
    ]);

    try {
        $restorePolicyEnabled = filter_var($GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$restorePolicyEnabled && array_key_exists('restore_policy_init_event_id', $event)) {
            return $done('restore-policy', 'restore-policy-changed');
        }
        if (
            !is_int($event['event_id'] ?? null) || $event['event_id'] < 1
            || !is_string($event['utterance_id'] ?? null) || $event['utterance_id'] === ''
            || !is_string($speakerKind) || !in_array($speakerKind, ['npc', 'player'], true)
            || ($playerSpeaker
                ? (
                    !array_key_exists('speaker_id', $event) || $event['speaker_id'] !== null
                    || !is_string($event['player_name'] ?? null) || trim($event['player_name']) === ''
                    || !is_string($event['speaker_name'] ?? null) || trim($event['speaker_name']) !== trim($event['player_name'])
                    || $event['utterance_id'] !== 'input_' . $event['event_id']
                    || ($event['source_kind'] ?? null) !== 'player_input'
                    || !is_string($event['source_type'] ?? null) || !in_array($event['source_type'], playerInputTypes(), true)
                    || !is_string($event['source_ts'] ?? null) || !is_numeric($event['source_ts']) || !is_finite((float)$event['source_ts'])
                    || !is_string($event['source_gamets'] ?? null) || !is_numeric($event['source_gamets']) || !is_finite((float)$event['source_gamets'])
                    || !is_int($event['source_localts'] ?? null)
                    || ($event['source_sess'] ?? null) !== 'web'
                    || !is_string($event['source_people'] ?? null) || trim($event['source_people']) === ''
                )
                : (
                    !is_int($event['speaker_id'] ?? null) || $event['speaker_id'] < 1
                    || ($event['source_kind'] ?? null) === 'player_input'
                ))
            || ($reflection
                ? (
                    $speakerKind !== 'npc' || $playerSpeaker
                    || !is_int($event['opinion_owner_id'] ?? null) || $event['opinion_owner_id'] < 1
                    || $event['speaker_id'] !== $event['opinion_owner_id']
                    || array_key_exists('listener_id', $event) && $event['listener_id'] !== null
                    || !is_string($event['reflection_basis'] ?? null)
                    || preg_match('/\A[a-f0-9]{64}\z/D', $event['reflection_basis']) !== 1
                    || !is_string($event['speech_hash'] ?? null)
                    || preg_match('/\A[a-f0-9]{64}\z/D', $event['speech_hash']) !== 1
                    || !is_string($event['text'] ?? null)
                    || !hash_equals($event['speech_hash'], hash('sha256', $event['text']))
                    || !is_callable($revalidateReflection)
                    || !is_array($registration)
                )
                : (
                    !is_int($event['listener_id'] ?? null) || $event['listener_id'] < 1
                    || (!$playerSpeaker && $event['speaker_id'] === $event['listener_id'])
                ))
            || !is_string($event['speaker_name'] ?? null) || trim($event['speaker_name']) === ''
            || (!$reflection && (!is_string($event['listener_name'] ?? null) || trim($event['listener_name']) === ''))
            || !is_string($event['text'] ?? null) || $event['text'] === ''
            || !is_string($event['playthrough_id'] ?? null) || $event['playthrough_id'] === ''
            || !is_numeric($event['gamets'] ?? null) || !is_finite((float)$event['gamets'])
            || !is_string($event['source_data'] ?? null)
            || ($hasListenerRole && !in_array($listenerRole, ['addressed', 'overheard'], true))
            || ($hasListenerRole && (
                !is_int($event['addressed_listener_id'] ?? null) || $event['addressed_listener_id'] < 1
                || !is_string($event['addressed_listener_name'] ?? null) || trim($event['addressed_listener_name']) === ''
                || !is_int($event['listener_id'] ?? null)
                || ($listenerRole === 'addressed' && (
                    $event['listener_id'] !== $event['addressed_listener_id']
                    || !sameActorName($event['listener_name'] ?? null, $event['addressed_listener_name'])
                ))
                || ($listenerRole === 'overheard' && (
                    $event['listener_id'] === $event['addressed_listener_id']
                    || !is_string($event['source_people'] ?? null)
                    || !in_array($event['source_delivery_state'] ?? null, ['emitted', 'spoken'], true)
                    || !($store instanceof WitnessSnapshotStoreDb)
                    || !is_callable($revalidateListenerScope)
                ))
            ))
            || !$replySourcesValid
            || !validSubjectsAndJudgments($subjects, $judgments, $event)
        ) {
            return $done('invalid', 'invalid-event');
        }

        $coveredEvents = $hasReplySources
            ? array_map(static fn(array $source): array => [
                'event_id' => $source['event_id'],
                'utterance_id' => $source['utterance_id'],
            ], $replySources)
            : [['event_id' => $event['event_id'], 'utterance_id' => $event['utterance_id']]];

        if ($restorePolicyEnabled) {
            if (!$store instanceof RestorePolicyStoreDb) {
                return $done('restore-policy', 'restore-policy-store-unavailable');
            }
            if (!is_int($restorePolicyInitId) || $restorePolicyInitId < 1) {
                return $done('restore-policy', 'restore-policy-boundary-unavailable');
            }
            foreach ($coveredEvents as $coveredEvent) {
                if (!sourceIsAfterRestoreBoundary($coveredEvent['event_id'], $restorePolicyInitId)) {
                    return $done('restore-policy', 'restore-policy-source-before-init');
                }
            }
            try {
                if (!$store->acquireRestorePolicyBarrier()) {
                    return $done('restore-policy', 'restore-policy-lock-unavailable');
                }
            } catch (Throwable) {
                return $done('restore-policy', 'restore-policy-lock-unavailable');
            }
            $restorePolicyBarrierHeld = true;
            $bounds = $store->restoreEventBounds();
            if (!restorePolicyBoundsMatch($bounds, $restorePolicyInitId)) {
                return $done('stale', 'restore-policy-init-changed');
            }
            foreach ($coveredEvents as $coveredEvent) {
                if ($coveredEvent['event_id'] > $bounds['max_event_id']) {
                    return $done('stale', 'restore-policy-source-unavailable');
                }
            }
        }

        if (!is_int($ownerId) || $ownerId < 1) {
            return $done('invalid', 'invalid-opinion-owner');
        }
        $cleanupRequired = true;
        $stage = 'begin-listener';
        if (!$store->beginForListener($ownerId)) {
            return $done('busy', 'listener-busy');
        }
        $stage = 'revalidate-event';
        $listener = $store->npcById($ownerId, true);
        $active = $store->activePlaythrough();
        $currentEvent = $listenerRole === 'overheard'
            ? ($store instanceof WitnessSnapshotStoreDb
                ? $store->eventByIdWithPeople($event['event_id'], $event['utterance_id'])
                : null)
            : $store->eventById($event['event_id'], $event['utterance_id']);
        if (
            !is_array($listener)
            || !is_array($active)
            || (string)($active['id'] ?? '') !== $event['playthrough_id']
            || !sameEvent($event, $currentEvent)
        ) {
            return $done('stale', 'event-stale');
        }
        if ($restorePolicyEnabled) {
            $bounds = $store instanceof RestorePolicyStoreDb ? $store->restoreEventBounds() : null;
            if (!restorePolicyBoundsMatch($bounds, $restorePolicyInitId)) {
                return $done('stale', 'restore-policy-init-changed');
            }
            foreach ($coveredEvents as $coveredEvent) {
                if (!sourceIsAfterRestoreBoundary($coveredEvent['event_id'], $restorePolicyInitId)
                    || $coveredEvent['event_id'] > $bounds['max_event_id']) {
                    return $done('stale', 'restore-policy-source-unavailable');
                }
            }
        }
        if ($reflection) {
            try {
                if (!$revalidateReflection($registration, 'transaction')) {
                    return $done('stale', 'reflection-registration-stale');
                }
            } catch (Throwable) {
                return $done('stale', 'reflection-registration-stale');
            }
            if ($hasReplySources && !reflectionReplySourcesCurrent($store, $replySources, $event['speaker_name'])) {
                return $done('stale', 'reflection-source-stale');
            }
            $basisReason = null;
            $currentHistory = null;
            $currentBasis = reflectionBasisForEvent($listener, $store, $event, $currentHistory, $basisReason);
            if (!is_string($currentBasis) || !hash_equals($event['reflection_basis'], $currentBasis)) {
                return $done('stale', $basisReason ?? 'reflection-basis-stale');
            }
            $pending = [];
            foreach ($subjects as $token => $_subject) {
                if (!reflectionBasisProcessed($listener, $event['playthrough_id'], $currentBasis, $token)) {
                    $pending[$token] = true;
                }
            }
            if ($pending === []) {
                return $done('duplicate', 'reflection-basis-duplicate');
            }
            foreach (array_keys($judgments) as $token) {
                if (!isset($pending[$token])) {
                    unset($judgments[$token], $subjects[$token]);
                }
            }
            if ($subjects === [] || !validSubjectsAndJudgments($subjects, $judgments, $event)) {
                return $done('invalid', 'reflection-subjects-invalid');
            }
        }
        if (
            ($reflection || $playerSpeaker || array_key_exists('player', $subjects))
            && is_string($event['player_name'] ?? null)
            && trim($event['player_name']) !== ''
            && (!is_string($active['player_name'] ?? null) || !sameActorName($event['player_name'], $active['player_name']))
        ) {
            return $done('stale', 'player-identity-stale');
        }
        if (
            $reflection
            ? !sameActorName($listener['npc_name'] ?? null, $event['speaker_name'])
            : !sameActorName($listener['npc_name'] ?? null, $event['listener_name'])
        ) {
            return $done('stale', 'listener-identity-stale');
        }
        $extendedData = $listener['extended_data'] ?? null;
        $pluginExtendedData = $listener['plugin_extended_data'] ?? null;
        if (!$extendedData instanceof \stdClass || !$pluginExtendedData instanceof \stdClass) {
            return $done('invalid', 'listener-state-invalid');
        }
        if (!empty($extendedData->relationships_locked) || (int)($listener['lock_profile'] ?? 0) !== 0) {
            return $done('locked', 'relationship-locked');
        }

        if ($playerSpeaker && !playerInputIncludesListener($currentEvent, $event['listener_name'])) {
            return $done('stale', 'input-audience-stale');
        }

        $stage = 'revalidate-actors';
        $identities = $store->npcIdentities();
        $currentPlayerName = is_string($active['player_name'] ?? null) ? $active['player_name'] : '';
        $currentSubjects = findSubjects($event, $identities, $currentPlayerName);
        $actors = $reflection
            ? [$event['opinion_owner_id'] => $event['speaker_name']]
            : [$event['listener_id'] => $event['listener_name']];
        if ($hasListenerRole && $listenerRole === 'overheard') {
            $actors[$event['addressed_listener_id']] = $event['addressed_listener_name'];
        }
        if (!$playerSpeaker && !$reflection) {
            $actors[$event['speaker_id']] = $event['speaker_name'];
        }
        foreach ($actors as $id => $name) {
            $matches = array_values(array_filter($identities, static fn(array $row): bool => sameActorName($row['npc_name'] ?? null, $name)));
            if (count($matches) !== 1 || (int)($matches[0]['id'] ?? 0) !== (int)$id) {
                return $done('stale', 'actor-catalog-stale');
            }
        }
        foreach ($subjects as $token => $subject) {
            $currentSubject = $currentSubjects[$token] ?? null;
            if (
                !is_array($currentSubject)
                || !sameActorName($currentSubject['name'] ?? null, $subject['name'])
                || ($currentSubject['id'] ?? null) !== $subject['id']
            ) {
                return $done('stale', 'subject-catalog-stale');
            }
            if ($token === 'player') {
                continue;
            }
            $matches = array_values(array_filter($identities, static fn(array $row): bool => sameActorName($row['npc_name'] ?? null, $subject['name'])));
            if (count($matches) !== 1 || (int)($matches[0]['id'] ?? 0) !== $subject['id']) {
                return $done('stale', 'subject-catalog-stale');
            }
            $stage = 'revalidate-subject';
            $target = $store->npcById($subject['id']);
            if (!is_array($target) || !sameActorName($target['npc_name'] ?? null, $subject['name'])) {
                return $done('stale', 'subject-stale');
            }
        }
        if (!$playerSpeaker && !$reflection) {
            $stage = 'revalidate-speaker';
            $speaker = $store->npcById($event['speaker_id']);
            if (!is_array($speaker) || !sameActorName($speaker['npc_name'] ?? null, $event['speaker_name'])) {
                return $done('stale', 'speaker-stale');
            }
        }

        if ($listenerRole === 'overheard') {
            $stage = 'listener-scope-revalidate';
            if (!$revalidateListenerScope($event, $currentEvent, $active)) {
                return $done('stale', 'listener-scope-stale');
            }
        }

        $stage = 'resolve-relationships';
        $storedRelationships = property_exists($extendedData, 'relationships')
            ? $extendedData->relationships
            : new \stdClass();
        $relationships = storedRelationshipMap($storedRelationships);
        if ($relationships === null) {
            return $done('invalid', 'relationships-invalid');
        }
        $canonicalPlayerRelationships = null;
        if ($playerSpeaker || array_key_exists('player', $subjects)) {
            $canonicalPlayerRelationships = canonicalStoredRelationshipMap(
                $relationships,
                is_string($event['player_name'] ?? null) ? $event['player_name'] : null
            );
            if ($canonicalPlayerRelationships === null) {
                return $done('failed', 'player-relationship-normalization-failed');
            }
        }

        $currentTypes = [];
        foreach ($judgments as $token => $judgment) {
            if (!is_array($judgment['type_change'] ?? null)) {
                continue;
            }
            $name = $token === 'player' ? 'Player' : $subjects[$token]['name'];
            $storedEdge = $token === 'player'
                ? (isset($canonicalPlayerRelationships['Player']) ? (object)$canonicalPlayerRelationships['Player'] : (object)['aff' => 0, 'type' => 'neutral'])
                : ($relationships[$name] ?? (object)['aff' => 0, 'type' => 'neutral']);
            $storedType = $storedEdge instanceof \stdClass ? ($storedEdge->type ?? 'neutral') : null;
            if (!$storedEdge instanceof \stdClass || !is_string($storedType)) {
                return $done('invalid', 'relationship-type-invalid');
            }
            if (trim($storedType) === '') {
                $storedType = 'neutral';
            }
            $currentType = canonicalRelationshipType($storedType, [$storedType]);
            if ($currentType === null) {
                return $done('invalid', 'relationship-type-invalid');
            }
            if ($currentType !== $judgment['type_change']['from']) {
                return $done('stale', 'relationship-type-stale');
            }
            $currentTypes[$token] = $currentType;
        }

        $typeUpdates = [];
        foreach ($currentTypes as $token => $currentType) {
            $typeChange = $judgments[$token]['type_change'];
            $reason = $typeChange['to'] === $currentType
                ? 'same_type'
                : relationshipTypePreservationReason($currentType, $typeChange['to']);
            $reason ??= 'change_eligible';
            $typeDecisions[] = [
                'subject' => $token,
                'from' => $currentType,
                'to' => $typeChange['to'],
                'reason' => $reason,
            ];
            if ($reason === 'change_eligible') {
                $typeUpdates[$token] = $typeChange['to'];
                $typeChanges[] = [
                    'subject' => $token,
                    'before' => $currentType,
                    'after' => $typeChange['to'],
                ];
            }
        }

        $updatedExtendedData = clone $extendedData;
        $hasPlayerChange = isset($judgments['player']) && $judgments['player']['delta'] !== 0;
        $canonicalizePlayerForWrite = $hasPlayerChange || isset($typeUpdates['player']);
        $updatedRelationships = (object)($canonicalizePlayerForWrite ? $canonicalPlayerRelationships : $relationships);
        $relationshipKeysToRemove = [];
        if ($canonicalizePlayerForWrite) {
            foreach ($relationships as $name => $_relationship) {
                if (!array_key_exists($name, $canonicalPlayerRelationships)) {
                    $relationshipKeysToRemove[] = (string)$name;
                }
            }
        }
        $edgeUpdates = [];
        foreach ($judgments as $token => $judgment) {
            $hasAffinityChange = $judgment['delta'] !== 0;
            $hasTypeChange = isset($typeUpdates[$token]);
            if (!$hasAffinityChange && !$hasTypeChange) {
                continue;
            }
            $name = $subjects[$token]['name'];
            if ($token === 'player') {
                $name = 'Player';
            }
            $prior = $token === 'player'
                ? (isset($canonicalPlayerRelationships['Player'])
                    ? (object)$canonicalPlayerRelationships['Player']
                    : (object)['aff' => 0, 'type' => 'neutral'])
                : ($updatedRelationships->{$name} ?? (object)['aff' => 0, 'type' => 'neutral']);
            if (!$prior instanceof \stdClass) {
                return $done('invalid', 'edge-invalid');
            }
            $updatedEdge = clone $prior;
            $affinity = $updatedEdge->aff ?? 0;
            if (!is_int($affinity) && !is_float($affinity) && !(is_string($affinity) && is_numeric($affinity))) {
                return $done('invalid', 'affinity-invalid');
            }
            $beforeAffinity = (float)$affinity;
            if (!is_finite($beforeAffinity) || $beforeAffinity < -100 || $beforeAffinity > 100) {
                return $done('invalid', 'affinity-invalid');
            }
            if ($hasAffinityChange) {
                $nextAffinity = max(-100, min(100, (float)$affinity + $judgment['delta']));
                $updatedEdge->aff = floor($nextAffinity) === $nextAffinity ? (int)$nextAffinity : $nextAffinity;
                $edgeChanges[] = [
                    'subject' => $token,
                    'delta' => $judgment['delta'],
                    'before' => $beforeAffinity,
                    'after' => $updatedEdge->aff,
                ];
            }
            if ($hasTypeChange) {
                $updatedEdge->type = $typeUpdates[$token];
            }
            $updatedRelationships->{$name} = $updatedEdge;
            $edgeUpdates[$name] = $updatedEdge;
        }
        if ($edgeUpdates !== [] || $relationshipKeysToRemove !== []) {
            $updatedExtendedData->relationships = $updatedRelationships;
        }

        $stage = 'validate-ledger';
        $pluginNamespace = storedLedgerNamespace($pluginExtendedData);
        if ($pluginNamespace === null) {
            return $done('invalid', 'ledger-invalid');
        }
        if ($restorePolicyEnabled && $pluginNamespace !== [] && $pluginNamespace['playthrough_id'] !== $event['playthrough_id']) {
            return $done('restore-policy', 'restore-policy-baseline-required');
        }
        if ($pluginNamespace !== [] && $pluginNamespace['playthrough_id'] === $event['playthrough_id']) {
            foreach ($coveredEvents as $coveredEvent) {
                if ($coveredEvent['event_id'] <= $pluginNamespace['floor_event_id']) {
                    return $done('below-floor', 'ledger-floor');
                }
                foreach ($pluginNamespace['events'] as $entry) {
                    if ($entry['event_id'] === $coveredEvent['event_id'] || $entry['utterance_id'] === $coveredEvent['utterance_id']) {
                        return $done('duplicate', 'duplicate-event');
                    }
                }
            }
        }
        $stage = 'update-ledger';
        $reflectionState = null;
        if ($reflection) {
            $reflectionBaseLedger = $pluginNamespace !== [] && $pluginNamespace['playthrough_id'] !== $event['playthrough_id']
                ? []
                : $pluginNamespace;
            $reflectionState = nextReflectionState($reflectionBaseLedger, $event['reflection_basis'], array_keys($subjects));
        }
        $nextLedger = $pluginNamespace;
        foreach ($coveredEvents as $index => $coveredEvent) {
            $isFinal = $index === array_key_last($coveredEvents);
            $nextLedger = nextLedger(
                $nextLedger,
                $event['playthrough_id'],
                $coveredEvent['event_id'],
                $coveredEvent['utterance_id'],
                $isFinal ? $judgments : [],
                $reflectionState,
                $isFinal ? $typeDecisions : [],
                $isFinal ? $typeChanges : []
            );
        }
        $updatedPluginExtendedData = clone $pluginExtendedData;
        $updatedPluginExtendedData->mind_poisoning = (object)$nextLedger;
        $stage = 'validate-timeline';
        $currentGamets = $listener['gamets_last_updated'] ?? 0;
        if ($currentGamets !== null && !is_numeric($currentGamets)) {
            return $done('invalid', 'timeline-invalid');
        }
        $snapshotGamets = max((float)($currentGamets ?? 0), (float)$event['gamets']);

        $stage = 'write-listener';
        if (!$store->writeNpc($ownerId, $edgeUpdates, (object)$nextLedger, $snapshotGamets, $relationshipKeysToRemove)) {
            return $done('failed', 'listener-write-failed');
        }
        $stage = 'verify-listener';
        $written = $store->npcById($ownerId);
        if (
            !is_array($written)
            || !sameJsonValue($written['extended_data'] ?? null, $updatedExtendedData)
            || !sameJsonValue($written['plugin_extended_data'] ?? null, $updatedPluginExtendedData)
            || (float)($written['gamets_last_updated'] ?? -1) !== $snapshotGamets
        ) {
            return $done('failed', 'listener-verification-failed');
        }
        $expected = [
            'extended_data' => $updatedExtendedData,
            'plugin_extended_data' => $updatedPluginExtendedData,
            'gamets_last_updated' => $snapshotGamets,
        ];
        $stage = 'verify-snapshot';
        if (!$store->backupAndVerify($ownerId, $expected)) {
            return $done('failed', 'snapshot-verification-failed');
        }
        if ($reflection) {
            $gateReason = null;
            if (speechAckInteractionStatus($gateReason) !== 'ok') {
                return $done('stale', 'reflection-' . ($gateReason ?? 'interaction-stale'));
            }
            if (mindPoisoningPauseStatus($gateReason) !== 'enabled') {
                return $done('paused', $gateReason ?? 'plugin-paused');
            }
            try {
                if (!$revalidateReflection($registration, 'transaction')) {
                    return $done('stale', 'reflection-registration-stale');
                }
            } catch (Throwable) {
                return $done('stale', 'reflection-registration-stale');
            }
            if ($hasReplySources && !reflectionReplySourcesCurrent($store, $replySources, $event['speaker_name'])) {
                return $done('stale', 'reflection-source-stale');
            }
        }
        if ($listenerRole === 'overheard') {
            $stage = 'listener-scope-precommit';
            if (!$revalidateListenerScope($event, $currentEvent, $active)) {
                return $done('stale', 'listener-scope-stale');
            }
        }
        $stage = 'commit';
        $commitAttempted = true;
        if (!$store->commit()) {
            return $done('failed', 'commit-failed');
        }
        $committed = true;
        return $done('committed', 'committed');
    } catch (Throwable $error) {
        return $done('failed', $stage . '-failed');
    } finally {
        if ($cleanupRequired) {
            if (!$committed) {
                $cleanupStage = 'rollback';
                try {
                    $store->rollback();
                } catch (Throwable $error) {
                    $cleanupFailed = true;
                    $cleanupError ??= $error;
                    $cleanupReason = $cleanupStage . '-failed';
                    $status = 'failed';
                    $reason = $cleanupReason;
                }
            }
            $cleanupStage = 'release';
            try {
                $store->release();
            } catch (Throwable $error) {
                $cleanupFailed = true;
                $cleanupError ??= $error;
                if ($cleanupReason === '') {
                    $cleanupReason = $cleanupStage . '-failed';
                }
                $status = 'failed';
                $reason = $cleanupReason;
            }
        }
        if ($restorePolicyBarrierHeld) {
            try {
                $store->releaseRestorePolicyBarrier();
            } catch (Throwable) {
                $cleanupFailed = true;
                $status = 'failed';
                $reason = 'restore-policy-main-release-failed';
            }
        }
        if ($store instanceof PostgresStoreDb && $store->cleanupFailed()) {
            $cleanupFailed = true;
        }

        $changes = $committed ? $edgeChanges : [];
        $confirmedTypeChanges = $committed ? $typeChanges : [];
        $affinityChangedSubjects = [];
        foreach ($changes as $change) {
            if ((float)$change['before'] !== (float)$change['after']) {
                $affinityChangedSubjects[$change['subject']] = true;
            }
        }
        $typeChangedSubjects = [];
        foreach ($confirmedTypeChanges as $change) {
            $typeChangedSubjects[$change['subject']] = true;
        }
        $changedCount = count($affinityChangedSubjects + $typeChangedSubjects);
        if ($committed && $changedCount === 0 && $reason === 'committed') {
            $reason = 'zero-change';
        }
        $logFields = [
            'persistence_outcome' => $status,
            'persistence_reason' => $reason,
            'persistence_ms' => RequestLog::elapsedMs($startedAt),
            'commit_state' => $committed ? 'confirmed' : ($commitAttempted ? 'unconfirmed' : 'not_attempted'),
            'committed' => $committed,
            'changes' => $changes,
            'type_decisions' => $typeDecisions,
            'type_changes' => $confirmedTypeChanges,
            'affinity_changed_count' => count($affinityChangedSubjects),
            'type_changed_count' => count($typeChangedSubjects),
            'changed_count' => $changedCount,
        ];
        if ($cleanupFailed) {
            $logFields['cleanup_failed'] = true;
            $requestLog?->context(['cleanup_failed' => true]);
        }
        if ($requestLog !== null) {
            $requestLog->context($logFields);
            $level = $cleanupFailed || $status === 'failed'
                ? 'error'
                : ($status === 'invalid' ? 'warning' : 'info');
            $requestLog->event('persistence_finished', $level, $logFields);
            if ($cleanupError !== null) {
                $requestLog->event('persistence_cleanup_failed', 'error', [
                    'stage' => $cleanupReason === 'rollback-failed' ? 'rollback' : 'release',
                    'reason' => $cleanupReason,
                    'committed' => $committed,
                    'cleanup_failed' => true,
                ]);
            }
        } elseif ($status === 'failed') {
            try {
                @error_log('Mind Poisoning persistence failed at ' . $reason . '.');
            } catch (Throwable) {
            }
        }

        if ($cleanupError !== null) {
            throw $cleanupError;
        }
    }
}

function sameEvent(array $expected, ?array $current): bool
{
    if (!is_array($current)
        || (int)($current['event_id'] ?? 0) !== ($expected['event_id'] ?? null)
        || ($current['utterance_id'] ?? null) !== ($expected['utterance_id'] ?? null)
        || (float)($current['gamets'] ?? -1) !== (float)($expected['gamets'] ?? -2)
        || ($current['source_data'] ?? null) !== ($expected['source_data'] ?? null)) {
        return false;
    }

    if (
        array_key_exists('source_people', $expected)
        && ($current['source_people'] ?? null) !== $expected['source_people']
    ) {
        return false;
    }
    if (
        array_key_exists('source_delivery_state', $expected)
        && !deliveryStateNotRegressed($expected['source_delivery_state'], $current['delivery_state'] ?? null)
    ) {
        return false;
    }

    if (($expected['speaker_kind'] ?? 'npc') === 'player') {
        foreach ([
            'speaker_kind', 'speaker_id', 'source_kind', 'source_type', 'source_ts',
            'source_localts', 'source_sess', 'source_people',
        ] as $field) {
            if (($current[$field] ?? null) !== ($expected[$field] ?? null)) {
                return false;
            }
        }
        return is_string($current['source_gamets'] ?? null)
            && $current['source_gamets'] === ($expected['source_gamets'] ?? null);
    }

    if (($expected['source_kind'] ?? null) === 'reflection') {
        $parts = function_exists(__NAMESPACE__ . '\\reflectionSourceParts')
            ? reflectionSourceParts((string)($current['source_data'] ?? ''))
            : null;
        return ($current['speaker_kind'] ?? 'npc') === 'npc'
            && in_array($current['delivery_state'] ?? null, ['emitted', 'spoken'], true)
            && is_array($parts)
            && sameActorName($parts['speaker'] ?? null, $expected['speaker_name'] ?? null)
            && is_string($expected['text'] ?? null)
            && is_string($expected['speech_hash'] ?? null)
            && hash_equals($expected['speech_hash'], hash('sha256', $expected['text']));
    }

    return ($current['speaker_kind'] ?? 'npc') === 'npc'
        && !array_key_exists('source_kind', $current)
        && in_array($current['delivery_state'] ?? null, ['emitted', 'spoken'], true);
}

function deliveryStateNotRegressed(mixed $expected, mixed $current): bool
{
    return $expected === 'emitted'
        ? in_array($current, ['emitted', 'spoken'], true)
        : ($expected === 'spoken' && $current === 'spoken');
}

final class PostgresStoreDb implements StoreDb, WitnessSnapshotStoreDb, RestorePolicyStoreDb
{
    private object $db;
    private ?RequestLog $requestLog;
    private mixed $connection = null;
    private bool $ownsTransaction = false;
    private bool $ownsAdvisoryLock = false;
    private bool $cleanupFailed = false;
    private int $advisoryKey = 0;
    private bool $ownsRestorePolicyBarrier = false;

    public function __construct(?RequestLog $requestLog = null)
    {
        $db = $GLOBALS['db'] ?? null;
        if (!is_object($db)) {
            throw new RuntimeException('CHIM database helper is unavailable.');
        }
        $this->db = $db;
        $this->requestLog = $requestLog;
    }

    public function cleanupFailed(): bool
    {
        return $this->cleanupFailed;
    }

    public function setRequestLog(?RequestLog $requestLog): ?RequestLog
    {
        $previous = $this->requestLog;
        $this->requestLog = $requestLog;
        return $previous;
    }

    public function activePlaythrough(): ?array
    {
        $catalog = $this->one("SELECT to_regclass('chim_meta.playthrough_profiles') AS profile_table");
        if (!array_key_exists('profile_table', $catalog)) {
            return null;
        }
        if ($catalog['profile_table'] === null) {
            $player = $this->one("SELECT (SELECT value FROM core_player WHERE id = 'player_name' LIMIT 1) AS player_name");
            return array_key_exists('player_name', $player)
                ? resolvePlaythroughContext([], $player['player_name'])
                : null;
        }
        if (!is_string($catalog['profile_table']) || $catalog['profile_table'] === '') {
            return null;
        }

        $row = $this->one(
            "SELECT COALESCE(
                        jsonb_agg(jsonb_build_object('id', profile.id::text) ORDER BY profile.id),
                        '[]'::jsonb
                    )::text AS active_profiles,
                    (SELECT player.value FROM core_player player WHERE player.id = 'player_name' LIMIT 1) AS player_name
             FROM (
                 SELECT id FROM chim_meta.playthrough_profiles
                 WHERE is_active IS TRUE ORDER BY id LIMIT 2
             ) profile"
        );
        if (!array_key_exists('active_profiles', $row) || !array_key_exists('player_name', $row)) {
            return null;
        }
        try {
            $profiles = json_decode((string)$row['active_profiles'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($profiles) || !array_is_list($profiles)) {
            return null;
        }
        return resolvePlaythroughContext($profiles, $row['player_name']);
    }

    public function restoreEventBounds(): ?array
    {
        $row = $this->one(
            "SELECT COALESCE((SELECT rowid FROM eventlog WHERE type = 'init' ORDER BY rowid DESC LIMIT 1), 0)::text AS latest_init_event_id,
                    COALESCE((SELECT MAX(rowid) FROM eventlog), 0)::text AS max_event_id"
        );
        $initId = filter_var($row['latest_init_event_id'] ?? null, FILTER_VALIDATE_INT);
        $maxId = filter_var($row['max_event_id'] ?? null, FILTER_VALIDATE_INT);
        if ($initId === false || $initId < 0 || $maxId === false || $maxId < $initId) {
            return null;
        }
        return ['latest_init_event_id' => $initId, 'max_event_id' => $maxId];
    }

    public function acquireRestorePolicyBarrier(): bool
    {
        if ($this->ownsRestorePolicyBarrier || !class_exists(\SemaphoreManager::class, false)) {
            return false;
        }
        try {
            if (!\SemaphoreManager::wait('MAIN', 0, 1003)) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }
        $this->ownsRestorePolicyBarrier = true;
        return true;
    }

    public function releaseRestorePolicyBarrier(): void
    {
        if (!$this->ownsRestorePolicyBarrier) {
            return;
        }
        $this->ownsRestorePolicyBarrier = false;
        try {
            if (!\SemaphoreManager::release('MAIN')) {
                $this->reportCleanupFailure('main-barrier-release', 'restore-policy-main-release-unconfirmed');
            }
        } catch (Throwable) {
            $this->reportCleanupFailure('main-barrier-release', 'restore-policy-main-release-failed');
        }
    }

    public function acknowledgedEvent(string $utteranceId): ?array
    {
        if (preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/', $utteranceId) !== 1) {
            return null;
        }
        $row = $this->one(
            "SELECT COALESCE(jsonb_agg(to_jsonb(e) ORDER BY e.rowid), '[]'::jsonb) AS events
             FROM (SELECT rowid, type, utterance_id, delivery_state, gamets, data
                   FROM eventlog WHERE type = 'chat' AND utterance_id = $1 ORDER BY rowid LIMIT 2) e",
            [$utteranceId]
        );
        $events = json_decode((string)($row['events'] ?? '[]'), true);
        if (!is_array($events) || count($events) !== 1 || !is_array($events[0])) {
            return null;
        }
        return normalizeEventRow($events[0]);
    }

    public function acknowledgedEventWithPeople(string $utteranceId): ?array
    {
        if (preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/', $utteranceId) !== 1) {
            return null;
        }
        $row = $this->one(
            "SELECT COALESCE(jsonb_agg(to_jsonb(e) ORDER BY e.rowid), '[]'::jsonb) AS events
             FROM (SELECT rowid, type, utterance_id, delivery_state, gamets, data,
                          CASE WHEN octet_length(people) <= 2048 THEN people ELSE NULL END AS people,
                          (people IS NOT NULL AND octet_length(people) > 2048)::int AS people_oversized
                   FROM eventlog WHERE type = 'chat' AND utterance_id = $1 ORDER BY rowid LIMIT 2) e",
            [$utteranceId]
        );
        try {
            $events = json_decode((string)($row['events'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($events) || count($events) !== 1 || !is_array($events[0])) {
            return null;
        }
        return normalizeEventRow($events[0], true);
    }

    public function reflectionHistory(string $actorName, int $beforeEventId): array
    {
        if (trim($actorName) === '' || strlen($actorName) > 256 || preg_match('//u', $actorName) !== 1 || $beforeEventId < 1) {
            return [];
        }
        // ponytail: the broad sentinel exclusion is conservative, but keeps reflection rows out before LIMIT.
        $query = "SELECT rowid AS event_id, utterance_id, gamets::text AS gamets,
                         data AS source_data, delivery_state, people
                  FROM eventlog e
                  WHERE e.type = 'chat' AND e.rowid < $1 AND e.delivery_state = 'spoken'
                    AND (SELECT count(*) FROM eventlog exact_event
                         WHERE exact_event.type = 'chat' AND exact_event.utterance_id = e.utterance_id) = 1
                    AND (
                        SELECT count(*) FROM unnest(string_to_array(COALESCE(e.people, ''), '|')) AS member(name)
                        WHERE lower(btrim(member.name)) = lower(btrim($2))
                    ) = 1
                    AND e.data !~* 'explicit_disable_rechat'
                    AND char_length(e.data) <= 4096
                  ORDER BY e.rowid DESC LIMIT 16";
        $rows = $this->readRows($query, [$beforeEventId, trim($actorName)]);
        if (count($rows) > 16) {
            throw new RuntimeException('Reflection history exceeded its bounded row limit.');
        }
        $normalized = [];
        $bytes = 0;
        foreach ($rows as $row) {
            $eventId = filter_var($row['event_id'] ?? null, FILTER_VALIDATE_INT);
            $utteranceId = $row['utterance_id'] ?? null;
            $sourceData = $row['source_data'] ?? null;
            $people = $row['people'] ?? null;
            if (
                $eventId === false || $eventId < 1 || $eventId >= $beforeEventId
                || !is_string($utteranceId) || preg_match('/\\Autt_[A-Za-z0-9_-]{8,128}\\z/D', $utteranceId) !== 1
                || ($row['delivery_state'] ?? null) !== 'spoken'
                || !is_string($sourceData) || strlen($sourceData) > 4096 || preg_match('//u', $sourceData) !== 1
                || !is_string($people) || strlen($people) > 2048 || preg_match('//u', $people) !== 1
                || !is_numeric($row['gamets'] ?? null) || !is_finite((float)$row['gamets'])
            ) {
                throw new RuntimeException('Reflection history returned an invalid row.');
            }
            $bytes += strlen($sourceData) + strlen($people);
            if ($bytes > 65536) {
                throw new RuntimeException('Reflection history exceeded its bounded byte limit.');
            }
            $normalized[] = [
                'event_id' => (int)$eventId,
                'utterance_id' => $utteranceId,
                'source_data' => $sourceData,
                'delivery_state' => 'spoken',
                'people' => $people,
            ];
        }
        return $normalized;
    }

    public function playerInputEvent(array $source): ?array
    {
        $parameters = playerInputSourceParameters($source);
        if ($parameters === null) {
            return null;
        }
        $row = $this->one(
            "SELECT COALESCE(jsonb_agg(to_jsonb(e) ORDER BY e.rowid), '[]'::jsonb) AS events
             FROM (SELECT rowid, type, ts::text AS ts, gamets::text AS gamets, data, localts, sess, people
                   FROM eventlog
                   WHERE type = $1 AND ts = $2 AND gamets = $3 AND data = $4 AND localts = $5 AND sess = $6
                   ORDER BY rowid LIMIT 2) e",
            $parameters
        );
        try {
            $events = json_decode((string)($row['events'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($events) || count($events) !== 1 || !is_array($events[0])) {
            return null;
        }
        return normalizePlayerInputRow($events[0]);
    }

    public function eventById(int $eventId, string $utteranceId): ?array
    {
        if (str_starts_with($utteranceId, 'input_')) {
            if (preg_match('/\Ainput_([1-9][0-9]*)\z/', $utteranceId, $matches) !== 1 || (string)$eventId !== $matches[1]) {
                return null;
            }
            $row = $this->one(
                "SELECT e.rowid, e.type, e.ts::text AS ts, e.gamets::text AS gamets, e.data, e.localts, e.sess, e.people,
                        (SELECT count(*) FROM eventlog exact_event
                         WHERE exact_event.type = e.type AND exact_event.ts = e.ts
                           AND exact_event.gamets = e.gamets AND exact_event.data = e.data
                           AND exact_event.localts = e.localts AND exact_event.sess = e.sess) AS source_count
                 FROM eventlog e
                 WHERE e.rowid = $1 AND e.type IN ('inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s')
                   AND e.sess = 'web' AND ('input_' || e.rowid::text) = $2
                 FOR SHARE OF e",
                [$eventId, $utteranceId]
            );
            return $row ? normalizePlayerInputRow($row) : null;
        }

        $sql = "SELECT e.rowid, e.type, e.utterance_id, e.delivery_state, e.gamets, e.data
                FROM eventlog e
                WHERE e.rowid = $1 AND e.type = 'chat' AND e.utterance_id = $2
                  AND (SELECT count(*) FROM eventlog exact_event
                       WHERE exact_event.type = 'chat' AND exact_event.utterance_id = $2) = 1
                FOR SHARE OF e";
        $row = $this->one($sql, [$eventId, $utteranceId]);
        return $row ? normalizeEventRow($row) : null;
    }

    public function eventByIdWithPeople(int $eventId, string $utteranceId): ?array
    {
        if (preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/D', $utteranceId) !== 1) {
            return null;
        }
        $sql = "SELECT e.rowid, e.type, e.utterance_id, e.delivery_state, e.gamets, e.data,
                       CASE WHEN octet_length(e.people) <= 2048 THEN e.people ELSE NULL END AS people,
                       (e.people IS NOT NULL AND octet_length(e.people) > 2048)::int AS people_oversized
                FROM eventlog e
                WHERE e.rowid = $1 AND e.type = 'chat' AND e.utterance_id = $2
                  AND (SELECT count(*) FROM eventlog exact_event
                       WHERE exact_event.type = 'chat' AND exact_event.utterance_id = $2) = 1
                FOR SHARE OF e";
        $row = $this->one($sql, [$eventId, $utteranceId]);
        return $row ? normalizeEventRow($row, true) : null;
    }

    public function npcIdentities(): array
    {
        $rows = $this->rows(
            "SELECT id, npc_name FROM core_npc_master
             WHERE npc_name IS NOT NULL AND trim(npc_name) <> '' AND npc_name <> 'The Narrator'
             ORDER BY id"
        );
        return array_values(array_filter(array_map(static function (array $row): ?array {
            $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
            $name = $row['npc_name'] ?? null;
            return $id !== false && $id > 0 && is_string($name) && trim($name) !== ''
                ? ['id' => (int)$id, 'npc_name' => $name]
                : null;
        }, $rows)));
    }

    public function npcById(int $npcId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM core_npc_master WHERE id = $1' . ($forUpdate ? ' FOR UPDATE' : '');
        $row = $this->one($sql, [$npcId]);
        if (!$row) {
            return null;
        }
        foreach (['extended_data', 'plugin_extended_data'] as $column) {
            $row[$column] = decodeJsonObject($row[$column] ?? null, $column);
        }
        if (isset($row['gamets_last_updated']) && $row['gamets_last_updated'] !== '') {
            if (!is_numeric($row['gamets_last_updated'])) {
                throw new RuntimeException('Invalid listener timeline value.');
            }
            $row['gamets_last_updated'] = (float)$row['gamets_last_updated'];
        }
        return $row;
    }

    public function beginForListener(int $listenerId): bool
    {
        $this->captureConnection();
        assertIdleTransactionStatus(pg_transaction_status($this->connection));
        $this->advisoryKey = 1001000000 + $listenerId;
        $result = @pg_query_params($this->connection, 'SELECT pg_try_advisory_lock($1) AS acquired', [$this->advisoryKey]);
        $this->assertConnectionIdentity();
        if ($result === false) {
            throw new RuntimeException('Could not acquire the relationship advisory lock.');
        }
        $row = pg_fetch_assoc($result);
        if (($row['acquired'] ?? null) !== 't') {
            return false;
        }
        $this->ownsAdvisoryLock = true;
        assertIdleTransactionStatus(pg_transaction_status($this->connection));
        if (@pg_query($this->connection, 'BEGIN') === false) {
            $this->assertConnectionIdentity();
            throw new RuntimeException('Could not begin the listener transaction.');
        }
        $this->ownsTransaction = true;
        $this->assertTransactionConnection();
        $this->setTransactionTimeouts();
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
        $params = [$npcId];
        $extendedExpression = 'extended_data';
        $relationshipWrite = $relationshipEdges !== [] || $relationshipKeysToRemove !== [];
        $relationshipGuard = '';
        if ($relationshipWrite) {
            if (!array_is_list($relationshipKeysToRemove)) {
                throw new RuntimeException('Invalid relationship keys to remove.');
            }
            $seenRelationshipKeys = [];
            foreach ($relationshipKeysToRemove as $key) {
                if (!is_string($key) || $key === '' || $key === 'Player' || isset($seenRelationshipKeys[$key]) || array_key_exists($key, $relationshipEdges)) {
                    throw new RuntimeException('Invalid relationship key removal.');
                }
                $seenRelationshipKeys[$key] = true;
            }
            $relationshipGuard = " AND (extended_data IS NULL OR (jsonb_typeof(extended_data) = 'object' AND (extended_data->'relationships' IS NULL OR jsonb_typeof(extended_data->'relationships') = 'object' OR extended_data->'relationships' = '[]'::jsonb)))";
            $extendedExpression = "jsonb_set(COALESCE(extended_data, '{}'::jsonb), '{relationships}', CASE WHEN extended_data->'relationships' IS NULL OR extended_data->'relationships' = '[]'::jsonb THEN '{}'::jsonb ELSE extended_data->'relationships' END, true)";
            foreach ($relationshipKeysToRemove as $key) {
                $params[] = $key;
                $keyIndex = count($params);
                $extendedExpression = "({$extendedExpression} #- ARRAY['relationships', \${$keyIndex}::text])";
            }
        }
        foreach ($relationshipEdges as $name => $edge) {
            if (!is_string($name) || $name === '' || !$edge instanceof \stdClass) {
                throw new RuntimeException('Invalid relationship edge update.');
            }
            $params[] = $name;
            $nameIndex = count($params);
            $params[] = json_encode($edge, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $edgeIndex = count($params);
            $extendedExpression = "jsonb_set(COALESCE({$extendedExpression}, '{}'::jsonb), ARRAY['relationships', \${$nameIndex}::text], \${$edgeIndex}::jsonb, true)";
        }
        $params[] = json_encode($mindPoisoningData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pluginIndex = count($params);
        $params[] = $gamets;
        $gametsIndex = count($params);
        $sql = "UPDATE core_npc_master
                SET extended_data = {$extendedExpression},
                    plugin_extended_data = jsonb_set(COALESCE(plugin_extended_data, '{}'::jsonb), ARRAY['mind_poisoning']::text[], \${$pluginIndex}::jsonb, true),
                    gamets_last_updated = \${$gametsIndex}
                WHERE id = $1{$relationshipGuard} RETURNING id";
        $result = $this->nativeQuery($sql, $params);
        return pg_fetch_assoc($result) !== false;
    }

    public function backupAndVerify(int $npcId, array $expected): bool
    {
        // The CHIM helper calls sql::re_connect() before INSERT. Native I/O keeps this inside our transaction.
        $row = $this->nativeOne('SELECT * FROM core_npc_master WHERE id = $1', [$npcId]);
        if (!$row) {
            return false;
        }
        $extendedData = decodeJsonObject($row['extended_data'] ?? null, 'extended_data');
        $extendedData->_chim_history_source = 'relationship';
        $row['extended_data'] = json_encode($extendedData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        unset($row['id']);
        $row['npc_id'] = $npcId;
        $row['created'] = date('Y-m-d H:i:s');

        $columns = array_keys($row);
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $column) !== 1) {
                throw new RuntimeException('Unexpected NPC history column name.');
            }
        }
        $slots = [];
        foreach ($columns as $index => $_column) {
            $slots[] = '$' . ($index + 1);
        }
        $insert = 'INSERT INTO core_npc_master_history (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', $slots) . ') RETURNING history_id';
        $result = $this->nativeQuery($insert, array_values($row));
        $inserted = pg_fetch_assoc($result);
        $historyId = filter_var($inserted['history_id'] ?? null, FILTER_VALIDATE_INT);
        if ($historyId === false || $historyId < 1) {
            return false;
        }

        $expectedExtended = clone $expected['extended_data'];
        $expectedExtended->_chim_history_source = 'relationship';
        $expectedExtendedJson = json_encode($expectedExtended, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $expectedPluginsJson = json_encode($expected['plugin_extended_data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $verification = $this->nativeOne(
            'SELECT npc_id,
                    (extended_data = $2::jsonb) AS extended_matches,
                    (plugin_extended_data = $3::jsonb) AS plugins_match,
                    (gamets_last_updated = $4::numeric) AS gamets_match
             FROM core_npc_master_history WHERE history_id = $1 AND npc_id = $5',
            [(int)$historyId, $expectedExtendedJson, $expectedPluginsJson, $expected['gamets_last_updated'], $npcId]
        );
        return is_array($verification)
            && (int)($verification['npc_id'] ?? 0) === $npcId
            && ($verification['extended_matches'] ?? null) === 't'
            && ($verification['plugins_match'] ?? null) === 't'
            && ($verification['gamets_match'] ?? null) === 't';
    }

    public function commit(): bool
    {
        if (!$this->ownsTransaction) {
            return false;
        }
        $result = @pg_query($this->connection, 'COMMIT');
        $this->assertConnectionIdentity();
        if ($result === false) {
            throw new RuntimeException('PostgreSQL rejected the listener transaction commit.');
        }
        $this->ownsTransaction = false;
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new RuntimeException('PostgreSQL did not return to idle after commit.');
        }
        return true;
    }

    public function rollback(): void
    {
        if (!$this->ownsTransaction) {
            return;
        }
        if (!$this->connection instanceof \PgSql\Connection
            || pg_connection_status($this->connection) !== PGSQL_CONNECTION_OK) {
            $this->reportCleanupFailure('rollback', 'rollback-connection-unavailable');
        } elseif (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE
            && @pg_query($this->connection, 'ROLLBACK') === false) {
            $this->reportCleanupFailure('rollback', 'rollback-query-failed');
        }
        $this->ownsTransaction = false;
    }

    public function release(): void
    {
        if (!$this->ownsAdvisoryLock) {
            return;
        }
        if (!$this->connection instanceof \PgSql\Connection
            || pg_connection_status($this->connection) !== PGSQL_CONNECTION_OK) {
            $this->reportCleanupFailure('release', 'advisory-unlock-connection-unavailable');
        } else {
            $result = @pg_query_params($this->connection, 'SELECT pg_advisory_unlock($1) AS released', [$this->advisoryKey]);
            if ($result === false) {
                $this->reportCleanupFailure('release', 'advisory-unlock-query-failed');
            } else {
                $row = pg_fetch_assoc($result);
                if (($row['released'] ?? null) !== 't') {
                    $this->reportCleanupFailure('release', 'advisory-unlock-not-confirmed');
                }
            }
        }
        $this->ownsAdvisoryLock = false;
    }

    private function reportCleanupFailure(string $stage, string $reason): void
    {
        $this->cleanupFailed = true;
        if ($this->requestLog !== null) {
            $this->requestLog->context(['cleanup_failed' => true]);
            $this->requestLog->event('persistence_cleanup_failed', 'error', [
                'stage' => $stage,
                'reason' => $reason,
                'cleanup_failed' => true,
            ]);
            return;
        }
        try {
            @error_log('Mind Poisoning cleanup failed at ' . $reason . '.');
        } catch (Throwable) {
        }
    }

    private function rows(string $query, array $params = []): array
    {
        if ($this->ownsTransaction) {
            $result = $this->nativeQuery($query, $params);
            $rows = [];
            while ($row = pg_fetch_assoc($result)) {
                $rows[] = $row;
            }
            return $rows;
        }
        if ($params) {
            $row = $this->db->fetchOne($query, $params);
            return is_array($row) && $row !== [] ? [$row] : [];
        }
        $rows = $this->db->fetchAll($query);
        return is_array($rows) ? $rows : [];
    }

    private function readRows(string $query, array $params): array
    {
        if ($this->ownsTransaction) {
            $result = $this->nativeQuery($query, $params);
        } else {
            $this->captureConnection();
            assertIdleTransactionStatus(pg_transaction_status($this->connection));
            $result = @pg_query_params($this->connection, $query, $params);
            $this->assertConnectionIdentity();
            if ($result === false) {
                throw new RuntimeException('Could not read bounded reflection history.');
            }
        }
        try {
            $rows = [];
            while ($row = pg_fetch_assoc($result)) {
                $rows[] = $row;
                if (count($rows) > 16) {
                    throw new RuntimeException('Reflection history exceeded its bounded row limit.');
                }
            }
            return $rows;
        } finally {
            pg_free_result($result);
        }
    }

    private function one(string $query, array $params = []): array
    {
        if ($this->ownsTransaction) {
            return $this->nativeOne($query, $params) ?? [];
        }
        $row = $this->db->fetchOne($query, $params);
        return is_array($row) ? $row : [];
    }

    private function nativeOne(string $query, array $params): ?array
    {
        $result = $this->nativeQuery($query, $params);
        $row = pg_fetch_assoc($result);
        return is_array($row) ? $row : null;
    }

    private function nativeQuery(string $query, array $params)
    {
        $this->assertTransactionConnection();
        $result = $params
            ? @pg_query_params($this->connection, $query, $params)
            : @pg_query($this->connection, $query);
        $this->assertConnectionIdentity();
        if ($result === false) {
            throw new RuntimeException('Native PostgreSQL statement failed: ' . substr(pg_last_error($this->connection), 0, 180));
        }
        return $result;
    }

    private function captureConnection(): void
    {
        if (!class_exists(\sql::class)) {
            throw new RuntimeException('CHIM sql helper is unavailable.');
        }
        $property = new \ReflectionProperty(\sql::class, 'link');
        $property->setAccessible(true);
        $connection = $property->getValue();
        assertPgSqlConnection($connection);
        if (pg_connection_status($connection) !== PGSQL_CONNECTION_OK) {
            throw new RuntimeException('CHIM PostgreSQL connection is not healthy.');
        }
        $this->connection = $connection;
    }

    private function assertConnectionIdentity(): void
    {
        if (!class_exists(\sql::class)) {
            throw new RuntimeException('CHIM sql helper disappeared during persistence.');
        }
        $property = new \ReflectionProperty(\sql::class, 'link');
        $property->setAccessible(true);
        $current = $property->getValue();
        if ($current !== $this->connection) {
            throw new RuntimeException('CHIM PostgreSQL connection changed during persistence.');
        }
        assertPgSqlConnection($this->connection);
        if (pg_connection_status($this->connection) !== PGSQL_CONNECTION_OK) {
            throw new RuntimeException('CHIM PostgreSQL connection failed during persistence.');
        }
    }

    private function assertTransactionConnection(): void
    {
        if (!$this->ownsTransaction) {
            throw new RuntimeException('No Mind Poisoning transaction is active.');
        }
        $this->assertConnectionIdentity();
        $status = pg_transaction_status($this->connection);
        if ($status !== PGSQL_TRANSACTION_INTRANS && $status !== PGSQL_TRANSACTION_INERROR) {
            throw new RuntimeException('Mind Poisoning lost its transaction boundary.');
        }
    }

    private function setTransactionTimeouts(): void
    {
        $result = $this->nativeQuery(
            "SELECT set_config(
                        'lock_timeout',
                        CASE
                            WHEN current_setting('lock_timeout')::interval = interval '0'
                              OR current_setting('lock_timeout')::interval > interval '1 second'
                            THEN '1000ms'
                            ELSE current_setting('lock_timeout')
                        END,
                        true
                    ) AS lock_timeout,
                    set_config(
                        'statement_timeout',
                        CASE
                            WHEN current_setting('statement_timeout')::interval = interval '0'
                              OR current_setting('statement_timeout')::interval > interval '3 seconds'
                            THEN '3000ms'
                            ELSE current_setting('statement_timeout')
                        END,
                        true
                    ) AS statement_timeout",
            []
        );
        try {
            $row = pg_fetch_assoc($result);
            if (!is_array($row) || !isset($row['lock_timeout'], $row['statement_timeout'])) {
                throw new RuntimeException('Could not apply listener transaction timeouts.');
            }
        } finally {
            pg_free_result($result);
        }
    }
}
