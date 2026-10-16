<?php
declare(strict_types=1);

const PCV_REFLECTION_RECEIPT_FILE = 'reflection_receipts.json';
const PCV_REFLECTION_RECEIPT_MAX_BYTES = 8192;
const PCV_REFLECTION_RECEIPT_MAX_COUNT = 8;
const PCV_REFLECTION_RECEIPT_TTL = 45;

/** @return array{kind: string, row?: array} */
function pcv_reflection_read_native_ack(mixed $connection, string $utteranceId): array
{
    if (preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/D', $utteranceId) !== 1
        || !function_exists('pg_query_params') || !function_exists('pg_num_rows')
        || !function_exists('pg_fetch_assoc')) {
        return ['kind' => 'invalid'];
    }

    try {
        $result = @pg_query_params(
            $connection,
            'SELECT CASE WHEN octet_length(utterance_id) <= 132 THEN utterance_id ELSE NULL END AS utterance_id,
                CASE WHEN octet_length(speaker) <= 256 THEN speaker ELSE NULL END AS speaker,
                CASE WHEN octet_length(listener) <= 256 THEN listener ELSE NULL END AS listener,
                CASE WHEN octet_length(speech) <= 12000 THEN speech ELSE NULL END AS speech
         FROM public.speech WHERE utterance_id = $1 LIMIT 2',
            [$utteranceId]
        );
    } catch (Throwable) {
        return ['kind' => 'unavailable'];
    }
    if ($result === false) {
        return ['kind' => 'unavailable'];
    }

    try {
        $count = pg_num_rows($result);
        if ($count === 0) {
            return ['kind' => 'missing'];
        }
        if ($count !== 1) {
            return ['kind' => 'ambiguous'];
        }
        $row = pg_fetch_assoc($result);
        foreach (['utterance_id', 'speaker', 'listener', 'speech'] as $field) {
            if (!is_string($row[$field] ?? null) || preg_match('//u', $row[$field]) !== 1) {
                return ['kind' => 'invalid'];
            }
        }
        if ($row['utterance_id'] !== $utteranceId || trim($row['speaker']) === ''
            || trim($row['listener']) === '' || trim($row['speech']) === '') {
            return ['kind' => 'invalid'];
        }
        return ['kind' => 'row', 'row' => $row];
    } catch (Throwable) {
        return ['kind' => 'unavailable'];
    } finally {
        if (function_exists('pg_free_result')) {
            @pg_free_result($result);
        }
    }
}

function pcv_reflection_open_native_connection(): mixed
{
    $helper = dirname(__DIR__, 2) . '/lib/playthrough_home.php';
    if (!is_file($helper)) {
        return null;
    }
    require_once $helper;
    return function_exists('ptp_connect') ? ptp_connect() : null;
}

function pcv_reflection_lookup_native_ack(string $utteranceId): array
{
    try {
        $connection = pcv_reflection_open_native_connection();
        if ($connection === null || $connection === false) {
            return ['kind' => 'unavailable'];
        }
        return pcv_reflection_read_native_ack($connection, $utteranceId);
    } catch (Throwable) {
        return ['kind' => 'unavailable'];
    } finally {
        if (isset($connection) && $connection !== null && $connection !== false
            && function_exists('pg_close')) {
            @pg_close($connection);
        }
    }
}

function pcv_reflection_ack_tuple(array $gameRequest): ?array
{
    $raw = $gameRequest[3] ?? null;
    if (($gameRequest[0] ?? null) !== '_speech' || !is_string($raw)
        || strlen($raw) > 16384 || preg_match('//u', $raw) !== 1) {
        return null;
    }
    try {
        $payload = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!$payload instanceof stdClass) {
        return null;
    }
    $fields = [];
    foreach (['speaker', 'listener', 'speech', 'utterance_id'] as $field) {
        $value = $payload->{$field} ?? null;
        if (!is_string($value)) {
            return null;
        }
        $fields[$field] = $value;
    }
    return pcv_reflection_normalize_ack_fields($fields);
}

function pcv_reflection_normalize_ack_fields(array $fields): ?array
{
    foreach (['speaker', 'listener', 'speech', 'utterance_id'] as $field) {
        if (!is_string($fields[$field] ?? null)) {
            return null;
        }
    }
    if (strlen($fields['speaker']) > 256 || strlen($fields['listener']) > 256
        || strlen($fields['speech']) > 12000 || preg_match('//u', $fields['speaker']) !== 1
        || preg_match('//u', $fields['listener']) !== 1 || preg_match('//u', $fields['speech']) !== 1) {
        return null;
    }
    $tuple = [
        'speaker' => trim($fields['speaker']),
        'listener' => trim($fields['listener']),
        'speech' => trim($fields['speech']),
        'utterance_id' => trim($fields['utterance_id']),
    ];
    return $tuple['speaker'] !== '' && $tuple['listener'] !== '' && $tuple['speech'] !== ''
        && preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/D', $tuple['utterance_id']) === 1
        ? $tuple : null;
}

function pcv_reflection_ack_tuple_digest(array $tuple): ?string
{
    foreach (['utterance_id', 'speaker', 'listener', 'speech'] as $field) {
        if (!is_string($tuple[$field] ?? null)) {
            return null;
        }
    }
    try {
        $encoded = json_encode([
            $tuple['utterance_id'], $tuple['speaker'], $tuple['listener'], $tuple['speech'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    return hash('sha256', $encoded);
}

function pcv_reflection_native_ack_request(array $row): ?array
{
    $tuple = pcv_reflection_normalize_ack_fields($row);
    if ($tuple === null) {
        return null;
    }
    try {
        return ['_speech', '', '', json_encode($tuple, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    } catch (JsonException) {
        return null;
    }
}

function pcv_reflection_receipt_fresh(array $receipt): bool
{
    $age = time() - ($receipt['created_at'] ?? 0);
    return $age >= 0 && $age <= PCV_REFLECTION_RECEIPT_TTL;
}

function pcv_reflection_valid_receipt(array $receipt): bool
{
    $keys = ['created_at', 'utterance_id', 'pcv_key', 'config_id', 'actor_id', 'actor_name', 'ack_generation', 'tuple_digest'];
    $actual = array_keys($receipt);
    sort($actual, SORT_STRING);
    sort($keys, SORT_STRING);
    return $actual === $keys
        && is_int($receipt['created_at']) && $receipt['created_at'] > 0
        && is_string($receipt['utterance_id']) && preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/D', $receipt['utterance_id']) === 1
        && is_string($receipt['pcv_key']) && pcv_valid_key($receipt['pcv_key'])
        && is_string($receipt['config_id']) && pcv_log_valid_uuid($receipt['config_id'])
        && is_int($receipt['actor_id']) && $receipt['actor_id'] > 0
        && is_string($receipt['actor_name']) && trim($receipt['actor_name']) !== ''
        && strlen($receipt['actor_name']) <= 256 && preg_match('//u', $receipt['actor_name']) === 1
        && is_int($receipt['ack_generation']) && $receipt['ack_generation'] >= 0
        && is_string($receipt['tuple_digest']) && preg_match('/\A[a-f0-9]{64}\z/D', $receipt['tuple_digest']) === 1;
}

function pcv_reflection_receipts_match(array $left, array $right): bool
{
    foreach (['utterance_id', 'pcv_key', 'config_id', 'actor_id', 'actor_name', 'ack_generation', 'tuple_digest'] as $field) {
        if (($left[$field] ?? null) !== ($right[$field] ?? null)) {
            return false;
        }
    }
    return pcv_reflection_valid_receipt($left) && pcv_reflection_valid_receipt($right);
}

function pcv_reflection_receipt_metadata(array $receipt): array
{
    return [
        'created_at' => $receipt['created_at'],
        'ack_generation' => $receipt['ack_generation'],
        'tuple_digest' => $receipt['tuple_digest'],
    ];
}

function pcv_reflection_valid_receipt_metadata(array $metadata): bool
{
    $keys = ['created_at', 'ack_generation', 'tuple_digest'];
    $actual = array_keys($metadata);
    sort($actual, SORT_STRING);
    sort($keys, SORT_STRING);
    return $actual === $keys
        && is_int($metadata['created_at']) && $metadata['created_at'] > 0
        && is_int($metadata['ack_generation']) && $metadata['ack_generation'] >= 0
        && is_string($metadata['tuple_digest']) && preg_match('/\A[a-f0-9]{64}\z/D', $metadata['tuple_digest']) === 1;
}

function pcv_reflection_store_ack_receipt(array $tuple, array $scope, int $ackGeneration, ?string $stateDirectory = null): array
{
    $digest = pcv_reflection_ack_tuple_digest($tuple);
    $actorId = filter_var($scope['actor_a_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $receipt = [
        'created_at' => time(),
        'utterance_id' => $tuple['utterance_id'] ?? null,
        'pcv_key' => $scope['pcv_key'] ?? null,
        'config_id' => $scope['config_id'] ?? null,
        'actor_id' => $actorId === false ? null : (int)$actorId,
        'actor_name' => $scope['scope']['actor_a'] ?? null,
        'ack_generation' => $ackGeneration,
        'tuple_digest' => $digest,
    ];
    if (!is_string($digest) || !pcv_reflection_valid_receipt($receipt)) {
        return ['kind' => 'invalid'];
    }

    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, true, LOCK_EX);
        try {
            $activeState = pcv_load_store($directory);
            if (($activeState['kind'] ?? null) !== 'ready') {
                return ['kind' => ($activeState['kind'] ?? null) === 'missing' ? 'scope_changed' : 'unavailable'];
            }
            $activeScope = pcv_reflection_active_solo_from_state($activeState['state'] ?? []);
            if ($activeScope === null) {
                return ['kind' => 'scope_changed'];
            }
            if (!pcv_reflection_interaction_epochs_match($ackGeneration, $ackGeneration)) {
                return ['kind' => 'interaction_stale'];
            }
            // Stored state binds the catalog ID; the receipt name is the live-resolved name for that ID.
            if ($receipt['pcv_key'] !== $activeScope['pcv_key']
                || $receipt['config_id'] !== $activeScope['config_id']
                || (string)$receipt['actor_id'] !== $activeScope['actor_id']
                || pcv_scope_name_key($tuple['speaker']) !== pcv_scope_name_key($receipt['actor_name'])) {
                return ['kind' => 'scope_changed'];
            }

            $loaded = pcv_reflection_read_receipts_locked($directory);
            if ($loaded['kind'] !== 'ready') {
                return ['kind' => $loaded['kind']];
            }
            $now = time();
            foreach ($loaded['receipts'] as $stored) {
                if ($stored['utterance_id'] === $receipt['utterance_id']
                    && $now - $stored['created_at'] >= 0
                    && $now - $stored['created_at'] <= PCV_REFLECTION_RECEIPT_TTL) {
                    if (!pcv_reflection_receipts_match($stored, $receipt)) {
                        return ['kind' => 'conflict'];
                    }
                    return ['kind' => 'ready', 'receipt' => $stored, 'duplicate' => true];
                }
            }
            $receipts = array_values(array_filter($loaded['receipts'], static fn(array $stored): bool =>
                $now - $stored['created_at'] >= 0
                && $now - $stored['created_at'] <= PCV_REFLECTION_RECEIPT_TTL
                && $stored['pcv_key'] === $activeScope['pcv_key']
                && $stored['config_id'] === $activeScope['config_id']
                && (string)$stored['actor_id'] === $activeScope['actor_id']
                && $stored['ack_generation'] === $receipt['ack_generation']));
            foreach ($receipts as $stored) {
                if ($stored['utterance_id'] === $receipt['utterance_id']) {
                    return ['kind' => 'conflict'];
                }
            }
            if (count($receipts) !== count($loaded['receipts'])) {
                pcv_reflection_write_receipts_locked($directory, $receipts);
            }
            if (count($receipts) >= PCV_REFLECTION_RECEIPT_MAX_COUNT) {
                return ['kind' => 'busy'];
            }
            $receipts[] = $receipt;
            pcv_reflection_write_receipts_locked($directory, $receipts);
            return ['kind' => 'ready', 'receipt' => $receipt, 'duplicate' => false];
        } finally {
            pcv_unlock_state($handle);
        }
    } catch (Throwable) {
        return ['kind' => 'unavailable'];
    }
}

function pcv_reflection_read_receipts_locked(string $directory): array
{
    $path = $directory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
    if (is_link($path)) {
        return ['kind' => 'invalid', 'receipts' => []];
    }
    if (!file_exists($path)) {
        return ['kind' => 'ready', 'receipts' => []];
    }
    if (!is_file($path)) {
        return ['kind' => 'invalid', 'receipts' => []];
    }
    clearstatcache(true, $path);
    $size = @filesize($path);
    $mode = @fileperms($path);
    if (!is_int($size) || $size > PCV_REFLECTION_RECEIPT_MAX_BYTES
        || !is_int($mode) || ($mode & 0077) !== 0) {
        return ['kind' => 'invalid', 'receipts' => []];
    }
    $contents = @file_get_contents($path);
    if (!is_string($contents)) {
        return ['kind' => 'unavailable', 'receipts' => []];
    }
    try {
        $data = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return ['kind' => 'invalid', 'receipts' => []];
    }
    $keys = is_array($data) ? array_keys($data) : [];
    sort($keys, SORT_STRING);
    if (!is_array($data) || $keys !== ['receipts', 'version']
        || ($data['version'] ?? null) !== 1 || !is_array($data['receipts'] ?? null)
        || !array_is_list($data['receipts']) || count($data['receipts']) > PCV_REFLECTION_RECEIPT_MAX_COUNT) {
        return ['kind' => 'invalid', 'receipts' => []];
    }
    $seen = [];
    foreach ($data['receipts'] as $receipt) {
        if (!is_array($receipt) || !pcv_reflection_valid_receipt($receipt)
            || $receipt['created_at'] > time()
            || isset($seen[$receipt['utterance_id']])) {
            return ['kind' => 'invalid', 'receipts' => []];
        }
        $seen[$receipt['utterance_id']] = true;
    }
    return ['kind' => 'ready', 'receipts' => $data['receipts']];
}

function pcv_reflection_write_receipts_locked(string $directory, array $receipts): void
{
    if (count($receipts) > PCV_REFLECTION_RECEIPT_MAX_COUNT) {
        throw new RuntimeException('Reflection receipt capacity is full.');
    }
    $seen = [];
    foreach ($receipts as $receipt) {
        if (!is_array($receipt) || !pcv_reflection_valid_receipt($receipt)
            || isset($seen[$receipt['utterance_id']])) {
            throw new RuntimeException('Invalid reflection receipt data.');
        }
        $seen[$receipt['utterance_id']] = true;
    }
    $path = $directory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
    if (is_link($path)) {
        throw new RuntimeException('Reflection receipt store is not safe.');
    }
    $contents = json_encode(['version' => 1, 'receipts' => array_values($receipts)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    if (strlen($contents) > PCV_REFLECTION_RECEIPT_MAX_BYTES) {
        throw new RuntimeException('Reflection receipt store is too large.');
    }
    if ($receipts === []) {
        if (file_exists($path) && !@unlink($path)) {
            throw new RuntimeException('Reflection receipt store is unavailable.');
        }
        return;
    }
    $temporary = tempnam($directory, '.reflection-receipts-');
    if ($temporary === false) {
        throw new RuntimeException('Reflection receipt store is unavailable.');
    }
    try {
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('Reflection receipt store is unavailable.');
        }
        @chmod($temporary, 0600);
        if (is_link($path) || !@rename($temporary, $path)) {
            throw new RuntimeException('Reflection receipt store is unavailable.');
        }
        @chmod($path, 0600);
    } finally {
        if (is_file($temporary) && !is_link($temporary)) {
            @unlink($temporary);
        }
    }
}
