<?php
declare(strict_types=1);

namespace ChimMindPoisoning;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use ReflectionClass;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/store.php';
require_once __DIR__ . '/influence.php';

const DASHBOARD_LOG_BYTES = 2097152;
const DASHBOARD_LOG_RECORDS = 1000;
const DASHBOARD_NPC_ROWS = 1000;
const DASHBOARD_LEDGER_ROWS = 100;
const DASHBOARD_LEDGER_BYTES = 65536;
const DASHBOARD_INTERACTIONS = 200;

function dashboardFilters(array $query): array
{
    $read = static function (string $key, string $default) use ($query): string {
        if (!array_key_exists($key, $query)) {
            return $default;
        }
        if (!is_string($query[$key])) {
            throw new \InvalidArgumentException('Invalid dashboard filter.');
        }
        return $query[$key];
    };

    $tab = $read('tab', 'interactions');
    $q = $read('q', '');
    $outcome = $read('outcome', '');
    $level = $read('level', '');
    if (!in_array($tab, ['interactions', 'diagnostics'], true)) {
        throw new \InvalidArgumentException('Invalid dashboard filter.');
    }
    if (
        strlen($q) > 480
        || preg_match('//u', $q) !== 1
        || preg_match('/[\x00-\x1F\x7F]/', $q) === 1
        || preg_match_all('/./us', $q, $unused) === false
        || count($unused[0]) > 120
        || !in_array($outcome, ['', 'committed', 'zero-change', 'unconfirmed', 'skipped', 'rejected', 'failed'], true)
        || !in_array($level, ['', 'debug', 'info', 'warning', 'error'], true)
    ) {
        throw new \InvalidArgumentException('Invalid dashboard filter.');
    }

    return ['tab' => $tab, 'q' => $q, 'outcome' => $outcome, 'level' => $level];
}

function dashboardLoad(string $serverRoot, array $filters): array
{
    $filters = dashboardFilters($filters);
    $root = realpath($serverRoot);
    $relationshipManagerPath = $root === false
        ? false
        : realpath($root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'relationship_manager.php');
    if (!class_exists(\RelationshipManager::class, false)
        && $relationshipManagerPath !== false
        && is_file($relationshipManagerPath)
        && is_readable($relationshipManagerPath)
        && str_starts_with($relationshipManagerPath, rtrim((string)$root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        try {
            require_once $relationshipManagerPath;
        } catch (Throwable) {
            // A failed optional core helper only makes Player current values unavailable.
        }
    }

    $manifest = json_decode((string)@file_get_contents(__DIR__ . '/manifest.json'), true);
    $version = is_array($manifest) && is_string($manifest['version'] ?? null) ? $manifest['version'] : 'unknown';
    $model = [
        'version' => $version,
        'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'notices' => [],
        'scope_label' => null,
        'scope_notice' => null,
        'source' => ['logs' => 'unavailable', 'database' => 'unavailable', 'limited' => false],
        'interactions' => [],
        'records' => [],
    ];

    $logs = dashboardReadLogs($serverRoot);
    $model['source']['logs'] = $logs['available'] ? 'available' : 'unavailable';
    $model['source']['limited'] = $logs['limited'];
    if (!$logs['available']) {
        $model['notices'][] = 'The plugin log could not be read.';
    }
    $model['notices'][] = 'CHIM logging is best-effort; suppressed or fallback log writes may be missing.';

    $database = null;
    $interactionLimited = false;
    try {
        $database = dashboardReadDatabase($serverRoot);
        $model['source']['database'] = 'available';
        if ($database['active_playthrough'] === 'unprofiled') {
            $model['scope_label'] = 'Shared server';
            $model['scope_notice'] = 'No CHIM playthrough profile is active, so this history is shared on the server and is not isolated by Skyrim save.';
        } elseif ($database['active_playthrough'] !== null) {
            $model['scope_label'] = 'Active playthrough';
        } else {
            $model['scope_label'] = 'Unverified';
        }
        if ($database['limited']) {
            $model['source']['limited'] = true;
            $model['notices'][] = 'Some catalog, ledger, or relationship data exceeded the dashboard read limits.';
        }
        if ($database['active_playthrough'] === null) {
            $model['notices'][] = 'A single active playthrough could not be identified.';
        }
        if ($database['invalid_ledgers']) {
            $model['source']['limited'] = true;
            $model['notices'][] = 'Some saved interaction ledgers were invalid and were omitted.';
        }
        $model['interactions'] = dashboardBuildInteractions($database, $logs['records'], $interactionLimited);
    } catch (Throwable) {
        $model['notices'][] = 'The database source could not be read; retained log records are shown without active-profile verification.';
        $model['interactions'] = dashboardLogInteractions($logs['records'], null, false, $interactionLimited);
    }

    if (count($model['interactions']) > DASHBOARD_INTERACTIONS || $interactionLimited) {
        $model['interactions'] = array_slice($model['interactions'], 0, DASHBOARD_INTERACTIONS);
        $model['source']['limited'] = true;
        $model['notices'][] = 'Only the most recent 200 interactions are shown.';
    }
    $model['interactions'] = array_values(array_filter(
        $model['interactions'],
        static fn(array $interaction): bool => dashboardInteractionMatches($interaction, $filters)
    ));

    if ($logs['limited']) {
        $model['notices'][] = 'The plugin log tail or record list was capped; older records may be missing.';
    }
    $model['records'] = array_values(array_filter(
        $logs['records'],
        static fn(array $record): bool => dashboardRecordMatches($record, $filters)
    ));

    return $model;
}

/** @return array{available:bool,limited:bool,records:array} */
function dashboardReadLogs(string $serverRoot): array
{
    $root = realpath($serverRoot);
    if ($root === false) {
        return ['available' => false, 'limited' => false, 'records' => []];
    }
    $path = realpath($root . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'chim.log');
    if (
        $path === false
        || !str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        || !is_file($path) || !is_readable($path)
    ) {
        return ['available' => false, 'limited' => false, 'records' => []];
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return ['available' => false, 'limited' => false, 'records' => []];
    }
    try {
        $stat = fstat($handle);
        if (!is_array($stat) || !is_int($stat['size'] ?? null) || $stat['size'] < 0) {
            return ['available' => false, 'limited' => false, 'records' => []];
        }
        $offset = max(0, $stat['size'] - DASHBOARD_LOG_BYTES);
        if (fseek($handle, $offset, SEEK_SET) !== 0) {
            return ['available' => false, 'limited' => false, 'records' => []];
        }
        $tail = '';
        while (strlen($tail) < DASHBOARD_LOG_BYTES && !feof($handle)) {
            $chunk = fread($handle, min(65536, DASHBOARD_LOG_BYTES - strlen($tail)));
            if ($chunk === false) {
                return ['available' => false, 'limited' => false, 'records' => []];
            }
            if ($chunk === '') {
                break;
            }
            $tail .= $chunk;
        }
    } finally {
        fclose($handle);
    }

    $limited = $offset > 0;
    $lines = explode("\n", $tail);
    if ($offset > 0) {
        array_shift($lines); // The first tail line may start in the middle of a shared log entry.
    }
    if ($tail !== '' && !str_ends_with($tail, "\n")) {
        array_pop($lines); // Ignore a log line that was still being written.
        $limited = true;
    }

    $records = [];
    foreach ($lines as $line) {
        $record = dashboardParseLogLine(rtrim($line, "\r"));
        if ($record !== null) {
            $records[] = $record;
        }
    }
    if (count($records) > DASHBOARD_LOG_RECORDS) {
        $records = array_slice($records, -DASHBOARD_LOG_RECORDS);
        $limited = true;
    }

    return ['available' => true, 'limited' => $limited, 'records' => array_reverse($records)];
}

function dashboardParseLogLine(string $line): ?array
{
    if (preg_match('/\A(?:\[[^\]\r\n]{1,64}\]\s*)?\[[^\]\r\n]{1,24}\]\s*(\{.*\})\z/D', $line, $matches) !== 1) {
        return null;
    }
    try {
        $record = json_decode($matches[1], false, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!$record instanceof \stdClass || ($record->plugin ?? null) !== 'mind_poisoning' || ($record->schema_version ?? null) !== 1) {
        return null;
    }

    $version = $record->version ?? null;
    $timestamp = dashboardTimestamp($record->timestamp ?? null);
    $requestId = $record->request_id ?? null;
    $level = $record->level ?? null;
    $event = $record->event ?? null;
    if (
        !is_string($version) || preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/D', $version) !== 1
        || $timestamp === null
        || !is_string($requestId) || preg_match('/\A(?:[a-f0-9]{24}|fallback-[1-9][0-9]*)\z/D', $requestId) !== 1
        || !in_array($level, ['debug', 'info', 'warning', 'error'], true)
        || !is_string($event) || preg_match('/\A[a-z][a-z0-9_]{0,47}\z/D', $event) !== 1
    ) {
        return null;
    }

    $clean = [
        'schema_version' => 1,
        'plugin' => 'mind_poisoning',
        'version' => $version,
        'timestamp' => $timestamp,
        'request_id' => $requestId,
        'level' => $level,
        'event' => $event,
    ];
    foreach (['event_id', 'playthrough_id', 'speaker_id', 'listener_id', 'connector_id'] as $field) {
        $id = $field === 'playthrough_id'
            ? dashboardPlaythroughId($record->{$field} ?? null)
            : dashboardId($record->{$field} ?? null);
        if ($id !== null) {
            $clean[$field] = $id;
        }
    }
    $sourceKind = $record->source_kind ?? null;
    $speakerKind = $record->speaker_kind ?? null;
    if (in_array($speakerKind, ['npc', 'player'], true)) {
        $clean['speaker_kind'] = $speakerKind;
    }
    if ($sourceKind === 'reflection') {
        if ($speakerKind !== null && $speakerKind !== 'npc') {
            return null;
        }
        $speakerId = dashboardId($record->speaker_id ?? null);
        $opinionOwnerId = dashboardId($record->opinion_owner_id ?? null);
        if ($opinionOwnerId !== null && $speakerId !== null && $opinionOwnerId !== $speakerId) {
            return null;
        }
        $clean['source_kind'] = 'reflection';
        unset($clean['listener_id']);
        if ($speakerKind === 'npc' && $opinionOwnerId !== null && $opinionOwnerId === $speakerId) {
            $clean['opinion_owner_id'] = $opinionOwnerId;
        }
    }
    if ($speakerKind === 'player') {
        unset($clean['speaker_id']);
    }
    $listenerRole = $record->listener_role ?? null;
    $addressedListenerId = dashboardId($record->addressed_listener_id ?? null);
    $batchId = $record->batch_id ?? null;
    if (
        $sourceKind === null && $speakerKind === 'npc'
        && in_array($listenerRole, ['addressed', 'overheard'], true)
        && isset($clean['listener_id']) && $addressedListenerId !== null
        && (($listenerRole === 'addressed' && $clean['listener_id'] === $addressedListenerId)
            || ($listenerRole === 'overheard' && $clean['listener_id'] !== $addressedListenerId))
        && is_string($batchId) && preg_match('/\A(?:[a-f0-9]{24}|fallback-[1-9][0-9]*)\z/D', $batchId) === 1
    ) {
        $clean['listener_role'] = $listenerRole;
        $clean['addressed_listener_id'] = $addressedListenerId;
        $clean['batch_id'] = $batchId;
    }
    $utteranceId = dashboardUtteranceId($record->utterance_id ?? null);
    if ($utteranceId !== null) {
        $clean['utterance_id'] = $utteranceId;
    }
    foreach (['stage', 'outcome', 'reason', 'model_outcome', 'persistence_outcome', 'persistence_reason'] as $field) {
        $value = $record->{$field} ?? null;
        if (is_string($value) && preg_match('/\A[a-z][a-z0-9_.-]{0,63}\z/D', $value) === 1) {
            $clean[$field] = $value;
        }
    }
    $commitState = $record->commit_state ?? null;
    if (in_array($commitState, ['confirmed', 'unconfirmed', 'not_attempted'], true)) {
        $clean['commit_state'] = $commitState;
    }
    foreach (['elapsed_ms', 'model_ms', 'persistence_ms'] as $field) {
        $value = dashboardFiniteNumber($record->{$field} ?? null, 0, 86400000);
        if ($value !== null) {
            $clean[$field] = $value;
        }
    }
    foreach (['payload_bytes', 'subject_count', 'speech_bytes', 'changed_count', 'affinity_changed_count'] as $field) {
        $value = $record->{$field} ?? null;
        $max = $field === 'subject_count' || $field === 'changed_count' || $field === 'affinity_changed_count' ? 8 : 2147483647;
        if (is_int($value) && $value >= 0 && $value <= $max) {
            $clean[$field] = $value;
        }
    }
    if (is_int($record->delta ?? null) && $record->delta >= -5 && $record->delta <= 5) {
        $clean['delta'] = $record->delta;
    }
    $subject = dashboardSubject($record->subject ?? null);
    if ($subject !== null) {
        $clean['subject'] = $subject;
    }
    foreach (['committed', 'cleanup_failed'] as $field) {
        if (is_bool($record->{$field} ?? null)) {
            $clean[$field] = $record->{$field};
        }
    }
    $changes = dashboardLogChanges($record->changes ?? null);
    if ($changes !== null) {
        $clean['changes'] = $changes;
    }
    $typeDecisions = dashboardTypeDecisions($record->type_decisions ?? null);
    if ($typeDecisions !== null) {
        $clean['type_decisions'] = $typeDecisions;
    }
    if (($clean['commit_state'] ?? null) === 'confirmed' && ($clean['committed'] ?? null) === true) {
        $typeChangedCount = $record->type_changed_count ?? null;
        if (is_int($typeChangedCount) && $typeChangedCount >= 0 && $typeChangedCount <= 8) {
            $clean['type_changed_count'] = $typeChangedCount;
        }
        $typeChanges = dashboardTypeChanges($record->type_changes ?? null);
        if ($typeChanges !== null) {
            $clean['type_changes'] = $typeChanges;
        }
    }

    return $clean;
}

function dashboardTimestamp(mixed $value): ?string
{
    if (!is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D', $value) !== 1) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        ? $value
        : null;
}

function dashboardId(mixed $value): ?string
{
    if (is_int($value)) {
        $value = (string)$value;
    }
    return is_string($value) && preg_match('/\A[1-9][0-9]{0,18}\z/D', $value) === 1 ? $value : null;
}

function dashboardPlaythroughId(mixed $value): ?string
{
    return $value === 'unprofiled' ? 'unprofiled' : dashboardId($value);
}

function dashboardUtteranceId(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    if (preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/D', $value) === 1) {
        return $value;
    }
    if (preg_match('/\Ainput_([1-9][0-9]{0,18})\z/D', $value, $matches) !== 1) {
        return null;
    }
    $digits = $matches[1];
    return strlen($digits) === 19 && strcmp($digits, '9223372036854775807') > 0 ? null : $value;
}

function dashboardSubject(mixed $value): ?string
{
    if ($value === 'player') {
        return 'player';
    }
    return is_string($value) && preg_match('/\Anpc:[1-9][0-9]{0,18}\z/D', $value) === 1 ? $value : null;
}

function dashboardFiniteNumber(mixed $value, float $min, float $max): int|float|null
{
    if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value < $min || $value > $max) {
        return null;
    }
    return $value;
}

function dashboardLogChanges(mixed $changes): ?array
{
    if (!is_array($changes) || !array_is_list($changes)) {
        return null;
    }
    $clean = [];
    foreach (array_slice($changes, 0, 8) as $change) {
        if (!$change instanceof \stdClass) {
            continue;
        }
        $subject = dashboardSubject($change->subject ?? null);
        $delta = $change->delta ?? null;
        $before = dashboardFiniteNumber($change->before ?? null, -PHP_FLOAT_MAX, PHP_FLOAT_MAX);
        $after = dashboardFiniteNumber($change->after ?? null, -100, 100);
        if ($subject !== null && is_int($delta) && $delta >= -5 && $delta <= 5 && $before !== null && $after !== null) {
            $clean[] = ['subject' => $subject, 'delta' => $delta, 'before' => $before, 'after' => $after];
        }
    }
    return $clean;
}

function dashboardCanonicalType(mixed $value): ?string
{
    return is_string($value)
        && strlen($value) <= 50
        && preg_match('/\A[a-z][a-z0-9_-]{0,49}\z/D', $value) === 1
            ? $value
            : null;
}

function dashboardTypeRecords(mixed $records, string $kind): ?array
{
    if (!is_array($records) || !array_is_list($records)) {
        return null;
    }
    $clean = [];
    $seen = [];
    foreach (array_slice($records, 0, 8) as $record) {
        if ($record instanceof \stdClass) {
            $record = get_object_vars($record);
        }
        if (!is_array($record)) {
            continue;
        }
        $subject = dashboardSubject($record['subject'] ?? null);
        $from = dashboardCanonicalType($kind === 'changes' ? ($record['before'] ?? null) : ($record['from'] ?? null));
        $to = dashboardCanonicalType($kind === 'changes' ? ($record['after'] ?? null) : ($record['to'] ?? null));
        if (
            $subject === null || $from === null || $to === null || isset($seen[$subject])
            || ($kind === 'changes' && $from === $to)
        ) {
            continue;
        }
        if ($kind === 'changes') {
            $clean[] = ['subject' => $subject, 'before' => $from, 'after' => $to];
            $seen[$subject] = true;
            continue;
        }
        $reason = $record['reason'] ?? null;
        if (!in_array($reason, ['change_eligible', 'same_type', 'protected_type_preserved', 'custom_type_preserved'], true)) {
            continue;
        }
        $clean[] = ['subject' => $subject, 'from' => $from, 'to' => $to, 'reason' => $reason];
        $seen[$subject] = true;
    }
    return $clean;
}

function dashboardTypeChanges(mixed $changes): ?array
{
    return dashboardTypeRecords($changes, 'changes');
}

function dashboardTypeDecisions(mixed $decisions): ?array
{
    return dashboardTypeRecords($decisions, 'decisions');
}

function dashboardTypeChangeForSubject(array $changes, string $subject): ?array
{
    $matches = array_values(array_filter(
        $changes,
        static fn(array $change): bool => ($change['subject'] ?? null) === $subject
    ));
    return count($matches) === 1 ? $matches[0] : null;
}

function dashboardLedgerTypeDecisions(array $entry): array
{
    $decisions = dashboardTypeDecisions($entry['type_decisions'] ?? null) ?? [];
    $seen = [];
    foreach ($decisions as $decision) {
        $seen[implode("\x1f", [$decision['subject'], $decision['from'], $decision['to']])] = true;
    }
    foreach ($entry['judgments'] ?? [] as $judgment) {
        if (!is_array($judgment) || !is_array($judgment['type_change'] ?? null)) {
            continue;
        }
        $subject = dashboardSubject($judgment['subject'] ?? null);
        $from = dashboardCanonicalType($judgment['type_change']['from'] ?? null);
        $to = dashboardCanonicalType($judgment['type_change']['to'] ?? null);
        if ($subject === null || $from === null || $to === null) {
            continue;
        }
        $decision = ['subject' => $subject, 'from' => $from, 'to' => $to, 'reason' => null];
        $key = implode("\x1f", [$subject, $from, $to]);
        if (!isset($seen[$key]) && count($decisions) < 8) {
            $decisions[] = $decision;
            $seen[$key] = true;
        }
    }
    return $decisions;
}

function dashboardConfirmedTypeChangeEvidence(?array $record): bool
{
    if (
        !is_array($record)
        || ($record['commit_state'] ?? null) !== 'confirmed'
        || ($record['committed'] ?? null) !== true
    ) {
        return false;
    }
    return (is_int($record['type_changed_count'] ?? null) && $record['type_changed_count'] > 0)
        || (is_array($record['type_changes'] ?? null) && $record['type_changes'] !== []);
}

/** @return array{active_playthrough:?string,player_name:string,rows:array,limited:bool,invalid_ledgers:bool} */
function dashboardReadDatabase(string $serverRoot): array
{
    $root = realpath($serverRoot);
    if ($root === false || !function_exists('pg_connect') || !defined('PGSQL_CONNECT_FORCE_NEW')) {
        throw new RuntimeException('Database source unavailable.');
    }
    $classPath = realpath($root . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'postgresql.class.php');
    if ($classPath === false || !str_starts_with($classPath, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Database source unavailable.');
    }
    if (!class_exists('sql', false)) {
        require_once $classPath;
    }
    if (!class_exists('sql', false)) {
        throw new RuntimeException('Database source unavailable.');
    }
    $reflection = new ReflectionClass('sql');
    if ($reflection->getFileName() === false || realpath($reflection->getFileName()) !== $classPath || !$reflection->hasProperty('connString')) {
        throw new RuntimeException('Database source unavailable.');
    }
    $property = $reflection->getProperty('connString');
    $defaults = $reflection->getDefaultProperties();
    $dsn = $defaults['connString'] ?? null;
    if (!$property->isPrivate() || $property->isStatic() || !is_string($dsn) || $dsn === '' || strlen($dsn) > 4096 || str_contains($dsn, "\0")) {
        throw new RuntimeException('Database source unavailable.');
    }

    $connection = @pg_connect($dsn . ' connect_timeout=5', PGSQL_CONNECT_FORCE_NEW);
    if (!$connection instanceof \PgSql\Connection) {
        throw new RuntimeException('Database source unavailable.');
    }
    $inTransaction = false;
    try {
        $begin = @pg_query($connection, 'BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        if ($begin === false) {
            throw new RuntimeException('Database source unavailable.');
        }
        pg_free_result($begin);
        $inTransaction = true;
        $timeout = @pg_query($connection, "SET LOCAL statement_timeout = '3000ms'");
        if ($timeout === false) {
            throw new RuntimeException('Database source unavailable.');
        }
        pg_free_result($timeout);
        $profileRelation = dashboardPgRows($connection, "SELECT to_regclass('chim_meta.playthrough_profiles') IS NOT NULL AS profile_exists");
        if ($profileRelation === [] || !array_key_exists('profile_exists', $profileRelation[0]) || !in_array($profileRelation[0]['profile_exists'], ['t', 'f'], true)) {
            throw new RuntimeException('Database source unavailable.');
        }
        $profileExists = $profileRelation[0]['profile_exists'] === 't';
        $profiles = [];
        if ($profileExists) {
            $profiles = dashboardPgRows(
                $connection,
                "SELECT profile.id::text AS id,
                        (SELECT player.value FROM public.core_player player WHERE player.id = 'player_name' LIMIT 1) AS player_name
                 FROM chim_meta.playthrough_profiles profile WHERE profile.is_active IS TRUE ORDER BY profile.id LIMIT 2"
            );
        }
        $playerName = null;
        if ($profiles === []) {
            $playerRows = dashboardPgRows(
                $connection,
                "SELECT player.value AS player_name FROM public.core_player player WHERE player.id = 'player_name' LIMIT 1"
            );
            $playerName = $playerRows[0]['player_name'] ?? null;
        } elseif (count($profiles) === 1) {
            $playerName = $profiles[0]['player_name'] ?? null;
        }
        $playthrough = resolvePlaythroughContext($profiles, $playerName);
        $activeId = is_array($playthrough) ? $playthrough['id'] : null;
        $playerName = is_array($playthrough) ? dashboardLabel($playthrough['player_name'] ?? null, '') : '';
        $identityRows = dashboardPgRows(
            $connection,
            'SELECT id::text AS id, CASE WHEN char_length(npc_name) <= 160 THEN npc_name ELSE NULL END AS npc_name, (npc_name IS NOT NULL AND char_length(npc_name) > 160) AS name_limited FROM public.core_npc_master ORDER BY public.core_npc_master.id ASC LIMIT $1',
            [DASHBOARD_NPC_ROWS + 1]
        );
        $limited = count($identityRows) > DASHBOARD_NPC_ROWS;
        if ($limited) {
            $identityRows = array_slice($identityRows, 0, DASHBOARD_NPC_ROWS);
        }
        $identities = [];
        foreach ($identityRows as $identity) {
            $id = dashboardId($identity['id'] ?? null);
            if ($id === null) {
                continue;
            }
            $name = dashboardLabel($identity['npc_name'] ?? null, '');
            if ($name !== '') {
                $identities[$id] = $name;
            }
            $limited = $limited || ($identity['name_limited'] ?? null) === 't';
        }

        $rows = [];
        if ($activeId !== null) {
            $rows = dashboardPgRows(
                $connection,
                "SELECT id::text AS id,
                        CASE WHEN octet_length((extended_data->'relationships')::text) <= 16384 THEN (extended_data->'relationships')::text END AS relationships,
                        CASE WHEN octet_length((plugin_extended_data->'mind_poisoning')::text) <= $2 THEN (plugin_extended_data->'mind_poisoning')::text END AS mind_poisoning,
                        (octet_length((extended_data->'relationships')::text) > 16384) AS relationships_limited,
                        (octet_length((plugin_extended_data->'mind_poisoning')::text) > $2) AS ledger_limited,
                        (
                            jsonb_typeof(plugin_extended_data) IS NOT NULL
                            AND (
                                jsonb_typeof(plugin_extended_data) <> 'object'
                                OR (
                                    plugin_extended_data ? 'mind_poisoning'
                                    AND (
                                        jsonb_typeof(plugin_extended_data->'mind_poisoning') <> 'object'
                                        OR NULLIF(plugin_extended_data->'mind_poisoning'->>'playthrough_id', '') IS NULL
                                    )
                                )
                            )
                        ) AS namespace_invalid
                 FROM public.core_npc_master
                 LEFT JOIN LATERAL (
                     SELECT MAX(
                         CASE
                             WHEN jsonb_typeof(event.value) = 'object'
                              AND jsonb_typeof(event.value->'event_id') = 'number'
                              AND (event.value->>'event_id') ~ '^[1-9][0-9]{0,18}$'
                              AND (
                                  char_length(event.value->>'event_id') < 19
                                  OR (event.value->>'event_id') COLLATE \"C\" <= '9223372036854775807' COLLATE \"C\"
                              )
                              AND jsonb_typeof(event.value->'utterance_id') = 'string'
                              AND NULLIF(event.value->>'utterance_id', '') IS NOT NULL
                             THEN (event.value->>'event_id')::bigint
                         END
                     ) AS latest_event_id
                     FROM jsonb_array_elements(
                         CASE
                             WHEN jsonb_typeof(plugin_extended_data) = 'object'
                              AND jsonb_typeof(plugin_extended_data->'mind_poisoning') = 'object'
                              AND octet_length((plugin_extended_data->'mind_poisoning')::text) <= $2
                              AND plugin_extended_data->'mind_poisoning'->>'playthrough_id' = $1
                              AND jsonb_typeof(plugin_extended_data->'mind_poisoning'->'events') = 'array'
                              AND CASE
                                  WHEN jsonb_typeof(plugin_extended_data->'mind_poisoning'->'events') = 'array'
                                  THEN jsonb_array_length(plugin_extended_data->'mind_poisoning'->'events') <= 128
                                  ELSE false
                              END
                             THEN plugin_extended_data->'mind_poisoning'->'events'
                             ELSE '[]'::jsonb
                         END
                     ) AS event(value)
                 ) recent ON TRUE
                 WHERE jsonb_typeof(plugin_extended_data) IS NOT NULL
                   AND (
                       jsonb_typeof(plugin_extended_data) <> 'object'
                       OR (
                           plugin_extended_data ? 'mind_poisoning'
                           AND (
                               jsonb_typeof(plugin_extended_data->'mind_poisoning') <> 'object'
                               OR NULLIF(plugin_extended_data->'mind_poisoning'->>'playthrough_id', '') IS NULL
                               OR plugin_extended_data->'mind_poisoning'->>'playthrough_id' = $1
                           )
                       )
                   )
                 ORDER BY recent.latest_event_id DESC NULLS LAST, public.core_npc_master.id ASC LIMIT $3",
                [$activeId, DASHBOARD_LEDGER_BYTES, DASHBOARD_LEDGER_ROWS + 1]
            );
        }
        $limited = $limited || count($rows) > DASHBOARD_LEDGER_ROWS;
        if (count($rows) > DASHBOARD_LEDGER_ROWS) {
            $rows = array_slice($rows, 0, DASHBOARD_LEDGER_ROWS);
        }
        $invalidLedgers = false;
        foreach ($rows as &$row) {
            $row['id'] = dashboardId($row['id'] ?? null);
            $row['npc_name'] = $identities[$row['id'] ?? ''] ?? '';
            $relationships = dashboardJsonObject($row['relationships'] ?? null);
            if (($row['relationships_limited'] ?? null) === 't') {
                $relationships = null;
            }
            $row['extended_data'] = (object)['relationships' => $relationships];
            $row['plugin_extended_data'] = new \stdClass();
            $plugin = $row['plugin_extended_data'];
            $limited = $limited
                || ($row['relationships_limited'] ?? null) === 't'
                || ($row['ledger_limited'] ?? null) === 't';
            if (($row['namespace_invalid'] ?? null) === 't') {
                $invalidLedgers = true;
            }
            if (is_string($row['mind_poisoning'] ?? null)) {
                $ledgerNamespace = dashboardJsonObject($row['mind_poisoning']);
                $plugin->mind_poisoning = $ledgerNamespace;
                if ($ledgerNamespace === null || storedLedgerNamespace($plugin) === null) {
                    $invalidLedgers = true;
                }
            }
        }
        unset($row);

        return [
            'active_playthrough' => $activeId,
            'player_name' => $playerName,
            'identities' => $identities,
            'rows' => $rows,
            'limited' => $limited,
            'invalid_ledgers' => $invalidLedgers,
        ];
    } finally {
        if ($inTransaction) {
            @pg_query($connection, 'ROLLBACK');
        }
        @pg_close($connection);
    }
}

function dashboardPgRows(\PgSql\Connection $connection, string $sql, array $params = []): array
{
    $result = $params === [] ? @pg_query($connection, $sql) : @pg_query_params($connection, $sql, $params);
    if ($result === false) {
        throw new RuntimeException('Database source unavailable.');
    }
    try {
        $rows = [];
        while (($row = pg_fetch_assoc($result)) !== false) {
            $rows[] = $row;
        }
        return $rows;
    } finally {
        pg_free_result($result);
    }
}

function dashboardJsonObject(mixed $value): ?\stdClass
{
    if ($value === null || $value === '') {
        return new \stdClass();
    }
    if (!is_string($value)) {
        return null;
    }
    try {
        $decoded = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    return $decoded instanceof \stdClass ? $decoded : null;
}

function dashboardLabel(mixed $value, string $fallback): string
{
    if (!is_string($value) || preg_match('//u', $value) !== 1) {
        return $fallback;
    }
    $value = trim($value);
    while (strlen($value) > 160) {
        $value = substr($value, 0, -1);
        while ($value !== '' && preg_match('//u', $value) !== 1) {
            $value = substr($value, 0, -1);
        }
    }
    return $value === '' ? $fallback : $value;
}

function dashboardBuildInteractions(array $database, array $records, bool &$limited): array
{
    $limited = false;
    $rows = $database['rows'] ?? [];
    $activeId = dashboardPlaythroughId($database['active_playthrough'] ?? null);
    if (!is_array($rows) || !is_string($activeId)) {
        return dashboardLogInteractions($records, null, true, $limited);
    }
    $names = is_array($database['identities'] ?? null) ? $database['identities'] : [];
    $nameCounts = [];
    $owners = [];
    foreach ($rows as $row) {
        if (is_array($row) && is_string($row['id'] ?? null)) {
            $owners[$row['id']] = $row;
        }
    }
    foreach ($names as $name) {
        if (is_string($name)) {
            $nameCounts[$name] = ($nameCounts[$name] ?? 0) + 1;
        }
    }

    $recordIndex = dashboardIndexRecords($records);
    $candidates = [];
    $ledgerKeys = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !is_string($row['id'] ?? null)) {
            continue;
        }
        $plugins = $row['plugin_extended_data'] ?? null;
        if (!$plugins instanceof \stdClass) {
            continue;
        }
        $ledger = storedLedgerNamespace($plugins);
        if ($ledger === null || $ledger === [] || $ledger['playthrough_id'] !== $activeId) {
            continue;
        }
        foreach ($ledger['events'] as $entry) {
            $eventId = $entry['event_id'] ?? null;
            $utteranceId = dashboardUtteranceId($entry['utterance_id'] ?? null);
            $judgments = $entry['judgments'] ?? null;
            if (
                !is_int($eventId) || $eventId < 1
                || $utteranceId === null
                || !is_array($judgments) || !array_is_list($judgments) || count($judgments) > 8
            ) {
                continue;
            }
            $sourceKind = ($entry['source_kind'] ?? null) === 'reflection' ? 'reflection' : null;
            $ownerId = $row['id'];
            $key = dashboardInteractionKey($activeId, (string)$eventId, $utteranceId, $ownerId);
            $ledgerKeys[$key] = true;
            dashboardKeepRecent($candidates, [
                'kind' => 'ledger', 'key' => $key, 'event_id' => (string)$eventId,
                'utterance_id' => $utteranceId, 'playthrough_id' => $activeId,
                'listener_id' => $sourceKind === 'reflection' ? null : $ownerId,
                'opinion_owner_id' => $sourceKind === 'reflection' ? $ownerId : null,
                'source_kind' => $sourceKind, 'entry' => $entry, 'row' => $row,
                'attribution' => $activeId === 'unprofiled' ? 'shared' : 'active',
            ], $limited);
        }
    }

    foreach ($records as $record) {
        if (!is_array($record) || ($record['event'] ?? null) !== 'request_finished') {
            continue;
        }
        $outcome = $record['outcome'] ?? null;
        if (!in_array($outcome, ['skipped', 'rejected', 'failed'], true)) {
            continue;
        }
        $playthroughId = dashboardPlaythroughId($record['playthrough_id'] ?? null);
        if ($playthroughId !== null && $playthroughId !== $activeId) {
            continue;
        }
        $eventId = dashboardId($record['event_id'] ?? null);
        $utteranceId = dashboardUtteranceId($record['utterance_id'] ?? null);
        $sourceKind = ($record['source_kind'] ?? null) === 'reflection' ? 'reflection' : null;
        $ownerId = dashboardOpinionOwnerId($record);
        if ($playthroughId !== null && $eventId !== null && $utteranceId !== null && $ownerId !== null) {
            $key = dashboardInteractionKey($playthroughId, $eventId, $utteranceId, $ownerId);
            if (isset($ledgerKeys[$key])) {
                continue;
            }
        } else {
            $key = null;
        }
        dashboardKeepRecent($candidates, [
            'kind' => 'log', 'key' => $key, 'event_id' => $eventId,
            'utterance_id' => $utteranceId,
            'playthrough_id' => $playthroughId,
            'listener_id' => $sourceKind === 'reflection' ? null : $ownerId,
            'opinion_owner_id' => $sourceKind === 'reflection' ? $ownerId : null,
            'source_kind' => $sourceKind,
            'record' => $record,
            'attribution' => $playthroughId === null ? 'unattributed' : ($playthroughId === 'unprofiled' ? 'shared' : 'active'),
        ], $limited);
    }

    usort($candidates, 'ChimMindPoisoning\\dashboardCandidateOrder');
    if (count($candidates) > DASHBOARD_INTERACTIONS) {
        $limited = true;
    }
    $interactions = [];
    foreach (array_slice($candidates, 0, DASHBOARD_INTERACTIONS) as $candidate) {
        if ($candidate['kind'] === 'log') {
            $interactions[] = dashboardLogInteraction(
                $candidate['record'], $candidate['playthrough_id'], $candidate['attribution'],
                $names, $owners, $recordIndex, true, (string)($database['player_name'] ?? '')
            );
        } else {
            $interactions[] = dashboardLedgerInteraction($candidate, $recordIndex, $names, $owners, $nameCounts, $database['player_name'] ?? '');
        }
    }
    return $interactions;
}

function dashboardInteractionKey(string $playthroughId, string $eventId, string $utteranceId, string $opinionOwnerId): string
{
    return implode("\x1f", [$playthroughId, $eventId, $utteranceId, $opinionOwnerId]);
}

function dashboardOpinionOwnerId(array $record): ?string
{
    if (($record['source_kind'] ?? null) === 'reflection') {
        $ownerId = dashboardId($record['opinion_owner_id'] ?? null);
        return ($record['speaker_kind'] ?? null) === 'npc' && $ownerId !== null && $ownerId === dashboardId($record['speaker_id'] ?? null)
            ? $ownerId
            : null;
    }
    return dashboardId($record['listener_id'] ?? null);
}

function dashboardIndexRecords(array $records): array
{
    $index = [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $playthroughId = dashboardPlaythroughId($record['playthrough_id'] ?? null);
        $eventId = dashboardId($record['event_id'] ?? null);
        $utteranceId = dashboardUtteranceId($record['utterance_id'] ?? null);
        $opinionOwnerId = dashboardOpinionOwnerId($record);
        $event = $record['event'] ?? null;
        if ($playthroughId === null || $eventId === null || $utteranceId === null || $opinionOwnerId === null || !is_string($event)) {
            continue;
        }
        $key = dashboardInteractionKey($playthroughId, $eventId, $utteranceId, $opinionOwnerId);
        $index[$key][$event][] = $record;
    }
    return $index;
}

function dashboardKeepRecent(array &$candidates, array $candidate, bool &$limited): void
{
    $candidates[] = $candidate;
    if (count($candidates) > DASHBOARD_INTERACTIONS * 2) {
        usort($candidates, 'ChimMindPoisoning\\dashboardCandidateOrder');
        $candidates = array_slice($candidates, 0, DASHBOARD_INTERACTIONS);
        $limited = true;
    }
}

function dashboardCandidateOrder(array $left, array $right): int
{
    $leftId = $left['event_id'] ?? null;
    $rightId = $right['event_id'] ?? null;
    if (is_string($leftId) && is_string($rightId)) {
        $length = strlen($rightId) <=> strlen($leftId);
        if ($length !== 0) {
            return $length;
        }
        $order = strcmp($rightId, $leftId);
        if ($order !== 0) {
            return $order;
        }
    } elseif (is_string($leftId) !== is_string($rightId)) {
        return is_string($leftId) ? -1 : 1;
    }
    return strcmp((string)($right['record']['timestamp'] ?? ''), (string)($left['record']['timestamp'] ?? ''));
}

function dashboardLedgerInteraction(array $candidate, array $recordIndex, array $names, array $owners, array $nameCounts, string $playerName): array
{
    $group = $recordIndex[$candidate['key']] ?? [];
    [$persistence, $commitState] = dashboardPersistenceForGroup($group);
    $request = dashboardRequestForGroup($group, $persistence);
    $entry = $candidate['entry'];
    $row = $candidate['row'];
    $reflection = ($candidate['source_kind'] ?? null) === 'reflection';
    $ownerId = dashboardId($candidate['opinion_owner_id'] ?? null);
    $relationshipOwnerId = $reflection ? $ownerId : dashboardId($candidate['listener_id'] ?? null);
    $opinionOwner = $reflection && $ownerId !== null
        ? ($names[$ownerId] ?? 'NPC #' . $ownerId)
        : null;
    $relationshipOwner = $relationshipOwnerId === null ? $row : ($owners[$relationshipOwnerId] ?? $row);
    $typeChanges = $commitState === 'unconfirmed' || $commitState === 'ambiguous'
        ? []
        : (dashboardTypeChanges($entry['type_changes'] ?? null) ?? []);
    $typeDecisions = dashboardLedgerTypeDecisions($entry);
    $hasUnresolvedTypeProposal = false;
    foreach ($typeDecisions as $typeDecision) {
        $reason = $typeDecision['reason'] ?? null;
        $provenNoChange = in_array($reason, ['protected_type_preserved', 'custom_type_preserved'], true)
            || ($reason === 'same_type' && ($typeDecision['from'] ?? null) === ($typeDecision['to'] ?? null));
        if (!$provenNoChange) {
            $hasUnresolvedTypeProposal = true;
            break;
        }
    }
    $changes = [];
    $allZero = true;
    foreach ($entry['judgments'] as $judgment) {
        if (!is_array($judgment)) {
            continue;
        }
        $subject = dashboardSubject($judgment['subject'] ?? null);
        $delta = $judgment['delta'] ?? null;
        if ($subject === null || !is_int($delta) || $delta < -5 || $delta > 5) {
            continue;
        }
        $allZero = $allZero && $delta === 0;
        $subjectId = $subject === 'player' ? null : substr($subject, 4);
        $label = $subject === 'player'
            ? dashboardLabel($playerName, 'Player')
            : ($names[$subjectId] ?? 'NPC #' . $subjectId);
        $loggedChange = $commitState === 'confirmed' ? dashboardMatchedChange($persistence, $subject, $delta) : null;
        $before = $loggedChange['before'] ?? null;
        $after = $loggedChange['after'] ?? null;
        $confirmed = $loggedChange !== null && $before !== null && $after !== null;
        $current = $subject !== 'player' && !isset($names[$subjectId])
            ? ['value' => null, 'state' => 'unavailable', 'type' => null, 'type_state' => 'unavailable']
            : dashboardCurrent($relationshipOwner, $subject, $label, $playerName, $nameCounts);
        $typeTransition = dashboardTypeChangeForSubject($typeChanges, $subject);
        $changes[] = [
            'subject' => $subject,
            'label' => $label,
            'proposed' => $delta,
            'before' => $confirmed ? $before : null,
            'after' => $confirmed ? $after : null,
            'applied' => $delta === 0 ? 0 : ($confirmed ? $after - $before : null),
            'current' => $current['value'],
            'current_state' => $current['state'],
            'type_before' => $typeTransition['before'] ?? null,
            'type_after' => $typeTransition['after'] ?? null,
            'type_event_state' => $typeTransition === null ? 'unavailable' : 'set',
            'current_type' => $current['type'],
            'current_type_state' => $current['type_state'],
        ];
    }
    $outcome = is_string($request['outcome'] ?? null) ? $request['outcome'] : 'committed';
    if ($commitState === 'unconfirmed') {
        $outcome = 'unconfirmed';
    } elseif (
        $commitState === 'confirmed' && ($request['outcome'] ?? null) !== 'failed'
        && ($persistence['changed_count'] ?? null) === 0
        && !dashboardConfirmedTypeChangeEvidence($persistence)
    ) {
        $outcome = 'zero-change';
    } elseif (
        $request === null && $commitState === 'none' && $allZero && $typeChanges === []
        && !$hasUnresolvedTypeProposal
    ) {
        $outcome = 'zero-change';
    }

    $speakerId = dashboardId($request['speaker_id'] ?? $persistence['speaker_id'] ?? null);
    $speakerKind = $request['speaker_kind'] ?? $persistence['speaker_kind'] ?? null;
    $listenerRole = in_array($request['listener_role'] ?? null, ['addressed', 'overheard'], true)
        ? $request['listener_role']
        : null;
    $addressedListenerId = dashboardId($request['addressed_listener_id'] ?? null);
    $speaker = $reflection
        ? $opinionOwner
        : ($speakerKind === 'player' ? 'Player' : ($speakerId === null ? null : ($names[$speakerId] ?? 'NPC #' . $speakerId)));
    $currentForType = static function (string $subject) use ($names, $playerName, $nameCounts, $relationshipOwner): array {
        $subjectId = $subject === 'player' ? null : substr($subject, 4);
        $label = $subject === 'player'
            ? dashboardLabel($playerName, 'Player')
            : ($names[$subjectId] ?? 'NPC #' . $subjectId);
        if ($subject !== 'player' && !isset($names[$subjectId])) {
            return ['label' => $label, 'current_type' => null, 'current_type_state' => 'unavailable'];
        }
        $current = dashboardCurrent($relationshipOwner, $subject, $label, $playerName, $nameCounts);
        return ['label' => $label, 'current_type' => $current['type'], 'current_type_state' => $current['type_state']];
    };
    foreach ($typeChanges as &$typeChange) {
        $typeChange += $currentForType($typeChange['subject']);
    }
    unset($typeChange);
    foreach ($typeDecisions as &$typeDecision) {
        $typeDecision += $currentForType($typeDecision['subject']);
    }
    unset($typeDecision);
    $interaction = [
        'request_id' => $request['request_id'] ?? $persistence['request_id'] ?? null,
        'event_id' => $candidate['event_id'],
        'utterance_id' => $candidate['utterance_id'],
        'playthrough_id' => $candidate['playthrough_id'],
        'attribution' => $candidate['attribution'] ?? 'active',
        'timestamp' => $request['timestamp'] ?? $persistence['timestamp'] ?? null,
        'speaker' => $speaker,
        'listener' => $reflection ? null : ($names[$candidate['listener_id']] ?? 'NPC #' . $candidate['listener_id']),
        'listener_role' => $listenerRole,
        'addressed_listener' => $listenerRole === 'overheard' && $addressedListenerId !== null
            ? ($names[$addressedListenerId] ?? 'NPC #' . $addressedListenerId)
            : null,
        'batch_id' => $listenerRole === null ? null : ($request['batch_id'] ?? null),
        'outcome' => $outcome,
        'reason' => $request['reason'] ?? $persistence['persistence_reason'] ?? 'ledger_recorded',
        'model_ms' => $request['model_ms'] ?? null,
        'persistence_ms' => $request['persistence_ms'] ?? $persistence['persistence_ms'] ?? null,
        'changes' => $changes,
        'type_decisions' => $typeDecisions,
        'type_changes' => $typeChanges,
    ];
    if ($reflection) {
        $interaction['source_kind'] = 'reflection';
        $interaction['opinion_owner'] = $opinionOwner;
    }
    return $interaction;
}

function dashboardPersistenceForGroup(array $group): array
{
    $confirmed = [];
    $unconfirmed = [];
    foreach ($group['persistence_finished'] ?? [] as $record) {
        if (($record['commit_state'] ?? null) === 'confirmed' && ($record['committed'] ?? null) === true) {
            $confirmed[] = $record;
        } elseif (($record['commit_state'] ?? null) === 'unconfirmed') {
            $unconfirmed[] = $record;
        }
    }
    if (count($confirmed) === 1) {
        return [$confirmed[0], 'confirmed'];
    }
    if ($confirmed !== []) {
        return [null, 'ambiguous'];
    }
    if (count($unconfirmed) === 1) {
        return [$unconfirmed[0], 'unconfirmed'];
    }
    return [null, $unconfirmed === [] ? 'none' : 'ambiguous'];
}

function dashboardRequestForGroup(array $group, ?array $persistence): ?array
{
    $requests = $group['request_finished'] ?? [];
    if ($persistence !== null) {
        $requests = array_values(array_filter(
            $requests,
            static fn(array $record): bool => ($record['request_id'] ?? null) === ($persistence['request_id'] ?? null)
        ));
    } else {
        $requests = array_values(array_filter($requests, static fn(array $record): bool => ($record['outcome'] ?? null) === 'committed'));
    }
    return count($requests) === 1 ? $requests[0] : null;
}

function dashboardMatchedChange(?array $persistence, string $subject, int $delta): ?array
{
    if (!is_array($persistence) || !is_array($persistence['changes'] ?? null)) {
        return null;
    }
    $matches = array_values(array_filter(
        $persistence['changes'],
        static fn(array $change): bool => ($change['subject'] ?? null) === $subject && ($change['delta'] ?? null) === $delta
    ));
    return count($matches) === 1 ? $matches[0] : null;
}

function dashboardCurrent(array $listener, string $subject, string $subjectName, string $playerName, array $nameCounts): array
{
    $relationship = dashboardCurrentRelationship($listener, $subject, $subjectName, $playerName, $nameCounts);
    $type = dashboardCurrentRelationshipType($relationship);
    if ($relationship['state'] !== 'set') {
        return ['value' => null, 'state' => $relationship['state']] + $type;
    }
    $edge = $relationship['edge'];
    $affinity = $edge->aff ?? 0;
    if (!is_int($affinity) && !is_float($affinity) && !(is_string($affinity) && is_numeric($affinity))) {
        return ['value' => null, 'state' => 'invalid'] + $type;
    }
    $value = (float)$affinity;
    if (!is_finite($value) || $value < -100 || $value > 100) {
        return ['value' => null, 'state' => 'invalid'] + $type;
    }
    return ['value' => floor($value) === $value ? (int)$value : $value, 'state' => 'set'] + $type;
}

function dashboardCurrentRelationship(array $listener, string $subject, string $subjectName, string $playerName, array $nameCounts): array
{
    $extended = $listener['extended_data'] ?? null;
    if (!$extended instanceof \stdClass) {
        return ['edge' => null, 'state' => 'invalid'];
    }
    $relationshipValue = property_exists($extended, 'relationships') ? $extended->relationships : new \stdClass();
    $relationships = storedRelationshipMap($relationshipValue);
    if (!is_array($relationships)) {
        return ['edge' => null, 'state' => 'invalid'];
    }
    if ($subject === 'player') {
        if (!class_exists(\RelationshipManager::class, false)
            || !is_callable([\RelationshipManager::class, 'normalizeTargetName'])
            || !is_callable([\RelationshipManager::class, 'normalizeRelationshipMap'])) {
            return ['edge' => null, 'state' => 'unavailable'];
        }
        $canonicalRelationships = canonicalStoredRelationshipMap($relationships, $playerName);
        if (!is_array($canonicalRelationships)) {
            return ['edge' => null, 'state' => 'invalid'];
        }
        $relationships = $canonicalRelationships;
        $key = array_key_exists('Player', $relationships) ? 'Player' : null;
    } else {
        if (($nameCounts[$subjectName] ?? 0) > 1) {
            return ['edge' => null, 'state' => 'ambiguous'];
        }
        $key = $subjectName;
        if (!array_key_exists($key, $relationships)) {
            return ['edge' => null, 'state' => 'unset_default_zero'];
        }
    }
    if ($key === null) {
        return ['edge' => null, 'state' => 'unset_default_zero'];
    }
    $edge = $relationships[$key] ?? null;
    if ($subject === 'player' && $key === 'Player' && is_array($edge)) {
        $edge = (object)$edge;
    }
    if (!$edge instanceof \stdClass) {
        return ['edge' => null, 'state' => 'invalid'];
    }
    return ['edge' => $edge, 'state' => 'set'];
}

function dashboardCurrentRelationshipType(array $relationship): array
{
    if (($relationship['state'] ?? null) !== 'set') {
        $state = match ($relationship['state'] ?? null) {
            'unset_default_zero' => 'unset',
            'ambiguous' => 'ambiguous',
            'invalid' => 'invalid',
            default => 'unavailable',
        };
        return ['type' => null, 'type_state' => $state];
    }
    $edge = $relationship['edge'] ?? null;
    if (!$edge instanceof \stdClass) {
        return ['type' => null, 'type_state' => 'invalid'];
    }
    if (!property_exists($edge, 'type') || $edge->type === null || $edge->type === '') {
        return ['type' => null, 'type_state' => 'unset'];
    }
    $type = $edge->type;
    if (
        !is_string($type) || strlen($type) > 200 || trim($type) === ''
        || preg_match('//u', $type) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $type) === 1
    ) {
        return ['type' => null, 'type_state' => 'invalid'];
    }
    if (relationshipTypeAllowedTypes() === null) {
        return ['type' => null, 'type_state' => 'unavailable'];
    }
    $canonical = canonicalRelationshipType($type, [$type]);
    if (!is_string($canonical)) {
        return ['type' => $type, 'type_state' => 'invalid'];
    }
    return ['type' => $canonical, 'type_state' => 'set'];
}

function dashboardLogInteractions(array $records, ?string $activeId, bool $databaseAvailable, bool &$limited): array
{
    $limited = false;
    $index = dashboardIndexRecords($records);
    $interactions = [];
    foreach ($records as $record) {
        if (!is_array($record) || ($record['event'] ?? null) !== 'request_finished') {
            continue;
        }
        $playthroughId = dashboardPlaythroughId($record['playthrough_id'] ?? null);
        $outcome = $record['outcome'] ?? null;
        if ($databaseAvailable) {
            if ($activeId !== null && $playthroughId !== null && $playthroughId !== $activeId) {
                continue;
            }
            if (!in_array($outcome, ['skipped', 'rejected', 'failed'], true)) {
                continue;
            }
        }
        $attribution = $databaseAvailable
            ? ($playthroughId === null
                ? 'unattributed'
                : ($activeId === null
                    ? 'unverified'
                    : ($activeId === 'unprofiled' ? 'shared' : 'active')))
            : ($playthroughId === null ? 'unattributed' : 'unverified');
        $interactions[] = dashboardLogInteraction($record, $playthroughId, $attribution, [], [], $index, $databaseAvailable, '');
    }
    usort($interactions, 'ChimMindPoisoning\\dashboardCandidateOrder');
    if (count($interactions) > DASHBOARD_INTERACTIONS) {
        $limited = true;
    }
    return array_slice($interactions, 0, DASHBOARD_INTERACTIONS);
}

function dashboardLogInteraction(array $record, ?string $playthroughId, string $attribution, array $names, array $owners, array $recordIndex, bool $databaseAvailable, string $playerName): array
{
    $speakerId = dashboardId($record['speaker_id'] ?? null);
    $listenerId = dashboardId($record['listener_id'] ?? null);
    $reflection = ($record['source_kind'] ?? null) === 'reflection';
    $opinionOwnerId = $reflection ? dashboardId($record['opinion_owner_id'] ?? null) : null;
    $ownerId = dashboardOpinionOwnerId($record);
    $eventId = dashboardId($record['event_id'] ?? null);
    $utteranceId = dashboardUtteranceId($record['utterance_id'] ?? null);
    $key = $playthroughId !== null && $eventId !== null && $utteranceId !== null && $ownerId !== null
        ? dashboardInteractionKey($playthroughId, $eventId, $utteranceId, $ownerId)
        : null;
    $group = $key === null ? [] : ($recordIndex[$key] ?? []);
    $group = array_map(
        static fn(array $items): array => array_values(array_filter(
            $items,
            static fn(array $item): bool => ($item['request_id'] ?? null) === ($record['request_id'] ?? null)
        )),
        $group
    );
    [$persistence, $commitState] = dashboardPersistenceForGroup($group);
    if ($persistence !== null && ($persistence['request_id'] ?? null) !== ($record['request_id'] ?? null)) {
        $persistence = null;
        $commitState = 'none';
    }
    $typeChanges = $commitState === 'confirmed' && dashboardConfirmedTypeChangeEvidence($persistence)
        ? (dashboardTypeChanges($persistence['type_changes'] ?? null) ?? [])
        : [];
    $typeDecisions = dashboardTypeDecisions($persistence['type_decisions'] ?? $record['type_decisions'] ?? null) ?? [];
    $outcome = is_string($record['outcome'] ?? null) ? $record['outcome'] : 'unknown';
    if ($commitState === 'unconfirmed') {
        $outcome = 'unconfirmed';
    } elseif (
        $commitState === 'confirmed' && $outcome === 'committed'
        && ($persistence['changed_count'] ?? null) === 0
        && !dashboardConfirmedTypeChangeEvidence($persistence)
    ) {
        $outcome = 'zero-change';
    }
    $changes = [];
    $nameCounts = [];
    foreach ($names as $name) {
        if (is_string($name)) {
            $nameCounts[$name] = ($nameCounts[$name] ?? 0) + 1;
        }
    }
    if ($commitState === 'confirmed' && is_array($persistence['changes'] ?? null)) {
        foreach ($persistence['changes'] as $change) {
            $subject = $change['subject'] ?? null;
            if (!is_string($subject)) {
                continue;
            }
            $subjectId = $subject === 'player' ? null : substr($subject, 4);
            $label = $subject === 'player' ? dashboardLabel($playerName, 'Player') : ($names[$subjectId] ?? 'NPC #' . $subjectId);
            $subjectKnown = $subject === 'player' ? $playerName !== '' : isset($names[$subjectId]);
            $current = $databaseAvailable && $ownerId !== null && isset($owners[$ownerId]) && $subjectKnown
                ? dashboardCurrent($owners[$ownerId], $subject, $label, $playerName, $nameCounts)
                : ['value' => null, 'state' => 'unavailable', 'type' => null, 'type_state' => 'unavailable'];
            $typeTransition = dashboardTypeChangeForSubject($typeChanges, $subject);
            $changes[] = [
                'subject' => $subject,
                'label' => $label,
                'proposed' => $change['delta'],
                'before' => $change['before'],
                'after' => $change['after'],
                'applied' => $change['after'] - $change['before'],
                'current' => $current['value'],
                'current_state' => $current['state'],
                'type_before' => $typeTransition['before'] ?? null,
                'type_after' => $typeTransition['after'] ?? null,
                'type_event_state' => $typeTransition === null
                    ? ($commitState === 'unconfirmed' && dashboardTypeChangeForSubject($typeDecisions, $subject) !== null ? 'unconfirmed' : 'unavailable')
                    : 'set',
                'current_type' => $current['type'],
                'current_type_state' => $current['type_state'],
            ];
        }
    }
    $currentForType = static function (string $subject) use ($databaseAvailable, $ownerId, $owners, $names, $nameCounts, $playerName): array {
        $subjectId = $subject === 'player' ? null : substr($subject, 4);
        $label = $subject === 'player'
            ? dashboardLabel($playerName, 'Player')
            : ($names[$subjectId] ?? 'NPC #' . $subjectId);
        if (
            !$databaseAvailable || $ownerId === null || !isset($owners[$ownerId])
            || ($subject !== 'player' && !isset($names[$subjectId]))
        ) {
            return ['label' => $label, 'current_type' => null, 'current_type_state' => 'unavailable'];
        }
        $current = dashboardCurrent($owners[$ownerId], $subject, $label, $playerName, $nameCounts);
        return ['label' => $label, 'current_type' => $current['type'], 'current_type_state' => $current['type_state']];
    };
    foreach ($typeChanges as &$typeChange) {
        $typeChange += $currentForType($typeChange['subject']);
    }
    unset($typeChange);
    foreach ($typeDecisions as &$typeDecision) {
        $typeDecision += $currentForType($typeDecision['subject']);
    }
    unset($typeDecision);
    $speaker = ($record['speaker_kind'] ?? null) === 'player'
        ? 'Player'
        : ($reflection && $opinionOwnerId !== null
            ? ($names[$opinionOwnerId] ?? 'NPC #' . $opinionOwnerId)
            : ($speakerId === null ? null : ($names[$speakerId] ?? 'NPC #' . $speakerId)));
    $opinionOwner = $reflection && $opinionOwnerId !== null
        ? ($names[$opinionOwnerId] ?? 'NPC #' . $opinionOwnerId)
        : null;
    $listenerRole = in_array($record['listener_role'] ?? null, ['addressed', 'overheard'], true)
        ? $record['listener_role']
        : null;
    $addressedListenerId = dashboardId($record['addressed_listener_id'] ?? null);
    $interaction = [
        'request_id' => $record['request_id'] ?? null,
        'event_id' => $eventId,
        'utterance_id' => $utteranceId,
        'playthrough_id' => $playthroughId,
        'attribution' => $attribution,
        'timestamp' => $record['timestamp'] ?? null,
        'speaker' => $speaker,
        'listener' => $reflection ? null : ($listenerId === null ? null : ($names[$listenerId] ?? 'NPC #' . $listenerId)),
        'listener_role' => $listenerRole,
        'addressed_listener' => $listenerRole === 'overheard' && $addressedListenerId !== null
            ? ($names[$addressedListenerId] ?? 'NPC #' . $addressedListenerId)
            : null,
        'batch_id' => $listenerRole === null ? null : ($record['batch_id'] ?? null),
        'outcome' => $outcome,
        'reason' => $record['reason'] ?? $persistence['persistence_reason'] ?? null,
        'model_ms' => $record['model_ms'] ?? null,
        'persistence_ms' => $record['persistence_ms'] ?? $persistence['persistence_ms'] ?? null,
        'changes' => $changes,
        'type_decisions' => $typeDecisions,
        'type_changes' => $typeChanges,
    ];
    if ($reflection) {
        $interaction['source_kind'] = 'reflection';
        $interaction['opinion_owner'] = $opinionOwner;
    }
    return $interaction;
}

function dashboardRecordMatches(array $record, array $filters): bool
{
    if ($filters['level'] !== '' && ($record['level'] ?? null) !== $filters['level']) {
        return false;
    }
    $recordOutcome = dashboardNormalizedOutcome($record);
    if ($filters['outcome'] !== '' && $recordOutcome !== $filters['outcome']) {
        return false;
    }
    return dashboardContains($record, $filters['q']);
}

function dashboardNormalizedOutcome(array $record): ?string
{
    if (($record['commit_state'] ?? null) === 'unconfirmed') {
        return 'unconfirmed';
    }
    $recordOutcome = $record['outcome'] ?? $record['persistence_outcome'] ?? null;
    if (dashboardConfirmedTypeChangeEvidence($record) && $recordOutcome === 'committed') {
        return 'committed';
    }
    if (
        ($record['commit_state'] ?? null) === 'confirmed'
        && ($record['committed'] ?? null) === true
        && ($record['changed_count'] ?? null) === 0
        && $recordOutcome === 'committed'
    ) {
        return 'zero-change';
    }
    if (($record['persistence_reason'] ?? null) === 'zero-change' || ($record['reason'] ?? null) === 'zero-change') {
        return 'zero-change';
    }
    $outcome = $record['outcome'] ?? $record['model_outcome'] ?? $record['persistence_outcome'] ?? null;
    return is_string($outcome) ? $outcome : null;
}

function dashboardInteractionMatches(array $interaction, array $filters): bool
{
    if ($filters['outcome'] !== '' && ($interaction['outcome'] ?? null) !== $filters['outcome']) {
        return false;
    }
    return dashboardContains($interaction, $filters['q']);
}

function dashboardContains(array $value, string $query): bool
{
    if ($query === '') {
        return true;
    }
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }
    return mb_stripos($encoded, $query, 0, 'UTF-8') !== false;
}
