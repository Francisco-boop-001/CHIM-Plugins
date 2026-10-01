<?php
declare(strict_types=1);

require_once __DIR__ . '/log.php';

const PCV_DIAGNOSTICS_MAX_LIMIT = 1000;

function pcv_diagnostics_normalize_filters(array $filters): array
{
    $defaults = [
        'request' => null,
        'config' => null,
        'event' => null,
        'severity' => null,
        'event_id' => null,
        'utterance_id' => null,
        'linked_request_id' => null,
        'limit' => 100,
    ];
    foreach ($filters as $key => $_value) {
        if (!array_key_exists($key, $defaults) && !in_array($key, ['jsonl', 'help'], true)) {
            throw new InvalidArgumentException('invalid filter');
        }
    }
    $normalized = $defaults;
    foreach ($defaults as $key => $default) {
        if (array_key_exists($key, $filters)) {
            $normalized[$key] = $filters[$key];
        }
    }
    if ($normalized['request'] !== null
        && (!is_string($normalized['request']) || preg_match('/\A(?:[a-f0-9]{32}|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})\z/D', $normalized['request']) !== 1)) {
        throw new InvalidArgumentException('invalid request id');
    }
    if ($normalized['config'] !== null
        && (!is_string($normalized['config']) || !pcv_log_valid_uuid($normalized['config']))) {
        throw new InvalidArgumentException('invalid config id');
    }
    if ($normalized['event'] !== null
        && (!is_string($normalized['event']) || !isset(pcv_log_event_rules()[$normalized['event']]))) {
        throw new InvalidArgumentException('invalid event');
    }
    if ($normalized['severity'] !== null
        && (!is_string($normalized['severity']) || !in_array($normalized['severity'], ['debug', 'info', 'warning', 'error'], true))) {
        throw new InvalidArgumentException('invalid severity');
    }
    if ($normalized['event_id'] !== null
        && ((!is_string($normalized['event_id']) && !is_int($normalized['event_id']))
            || preg_match('/\A[1-9][0-9]{0,18}\z/D', (string)$normalized['event_id']) !== 1)) {
        throw new InvalidArgumentException('invalid event id');
    }
    if ($normalized['utterance_id'] !== null && !pcv_log_valid_utterance_id($normalized['utterance_id'])) {
        throw new InvalidArgumentException('invalid utterance id');
    }
    if ($normalized['linked_request_id'] !== null
        && (!is_string($normalized['linked_request_id']) || preg_match('/\A[a-f0-9]{24}\z/D', $normalized['linked_request_id']) !== 1)) {
        throw new InvalidArgumentException('invalid linked request id');
    }
    if (!is_int($normalized['limit']) || $normalized['limit'] < 1 || $normalized['limit'] > PCV_DIAGNOSTICS_MAX_LIMIT) {
        throw new InvalidArgumentException('invalid limit');
    }
    return $normalized;
}

function pcv_diagnostics_private_handle($handle): bool
{
    if (!is_resource($handle)) {
        return false;
    }
    $stat = @fstat($handle);
    $uid = pcv_log_effective_uid();
    return is_array($stat) && $uid !== null && ($stat['uid'] ?? null) === $uid
        && (($stat['mode'] ?? 0) & 0170000) === 0100000
        && (($stat['mode'] ?? 0) & 0077) === 0
        && (($stat['mode'] ?? 0) & 0600) === 0600;
}

function pcv_diagnostics_close_snapshot_files(array &$files): void
{
    foreach ($files as $file) {
        if (is_resource($file['handle'] ?? null)) {
            @fclose($file['handle']);
        }
    }
    $files = [];
}

/** @return array{status:string,files:list<array{handle:resource,size:int}>} */
function pcv_diagnostics_acquire_snapshot(string $logPath): array
{
    if (basename($logPath) !== 'events.jsonl') {
        return ['status' => 'unavailable', 'files' => []];
    }
    $directoryPath = dirname($logPath);
    if (pcv_log_has_symlink_component($directoryPath)) {
        return ['status' => 'unavailable', 'files' => []];
    }
    clearstatcache(true, $directoryPath);
    if (!file_exists($directoryPath) && !is_link($directoryPath)) {
        return ['status' => 'empty', 'files' => []];
    }
    if (is_link($directoryPath) || !pcv_log_directory_is_safe($directoryPath, true)) {
        return ['status' => 'unavailable', 'files' => []];
    }
    $directory = realpath($directoryPath);
    if ($directory === false) {
        return ['status' => 'unavailable', 'files' => []];
    }

    $names = ['events.4.jsonl', 'events.3.jsonl', 'events.2.jsonl', 'events.1.jsonl', 'events.jsonl'];
    $hasLogs = false;
    foreach ($names as $name) {
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_link($path)) {
            return ['status' => 'unavailable', 'files' => []];
        }
        $hasLogs = $hasLogs || file_exists($path);
    }

    $lockPath = $directory . DIRECTORY_SEPARATOR . 'events.lock';
    if (!file_exists($lockPath) && !is_link($lockPath)) {
        return ['status' => $hasLogs ? 'unavailable' : 'empty', 'files' => []];
    }
    if (is_link($lockPath) || !pcv_log_private_file($lockPath)) {
        return ['status' => 'unavailable', 'files' => []];
    }
    $lock = @fopen($lockPath, 'rb');
    if (!pcv_diagnostics_private_handle($lock)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        return ['status' => 'unavailable', 'files' => []];
    }
    if (!@flock($lock, LOCK_SH | LOCK_NB)) {
        fclose($lock);
        return ['status' => 'busy', 'files' => []];
    }

    $files = [];
    $status = 'ok';
    try {
        foreach ($names as $name) {
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            clearstatcache(true, $path);
            if (is_link($path)) {
                $status = 'unavailable';
                break;
            }
            if (!file_exists($path)) {
                continue;
            }
            if (!pcv_log_private_file($path)) {
                $status = 'unavailable';
                break;
            }
            $file = @fopen($path, 'rb');
            if (!pcv_diagnostics_private_handle($file)) {
                if (is_resource($file)) {
                    fclose($file);
                }
                $status = 'unavailable';
                break;
            }
            $stat = @fstat($file);
            $size = is_array($stat) ? ($stat['size'] ?? null) : null;
            if (!is_int($size) || $size < 0 || $size > PCV_LOG_MAX_FILE_BYTES) {
                fclose($file);
                $status = 'unavailable';
                break;
            }
            $files[] = ['handle' => $file, 'size' => $size];
        }
    } catch (Throwable) {
        $status = 'unavailable';
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }

    if ($status !== 'ok') {
        pcv_diagnostics_close_snapshot_files($files);
        return ['status' => $status, 'files' => []];
    }
    if ($files === []) {
        return ['status' => 'empty', 'files' => []];
    }
    return ['status' => 'ok', 'files' => $files];
}

function pcv_diagnostics_project_entry($row): ?array
{
    if (!is_array($row) || ($row['schema_version'] ?? null) !== PCV_LOG_SCHEMA_VERSION) {
        return null;
    }
    $event = $row['event'] ?? null;
    if (!is_string($event) || !isset(pcv_log_event_rules()[$event])) {
        return null;
    }
    $timestamp = $row['timestamp'] ?? null;
    $pluginVersion = $row['plugin_version'] ?? null;
    $requestId = $row['request_id'] ?? null;
    $configId = $row['config_id'] ?? null;
    $playthroughRef = $row['playthrough_ref'] ?? null;
    $elapsed = $row['elapsed_ms'] ?? null;
    $reason = $row['reason'] ?? null;
    $context = $row['context'] ?? null;
    $severity = $row['severity'] ?? null;
    $outcome = $row['outcome'] ?? null;
    if (!is_string($timestamp) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{3}Z\z/D', $timestamp) !== 1
        || !is_string($pluginVersion) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9.+-]{0,31}\z/D', $pluginVersion) !== 1
        || !is_string($requestId) || preg_match('/\A(?:[a-f0-9]{32}|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})\z/D', $requestId) !== 1
        || ($configId !== null && (!is_string($configId) || !pcv_log_valid_uuid($configId)))
        || ($playthroughRef !== null && (!is_string($playthroughRef) || preg_match('/\A[a-f0-9]{16}\z/D', $playthroughRef) !== 1))
        || ($elapsed !== null && (!is_int($elapsed) || $elapsed < 0))
        || !is_string($severity) || !is_string($outcome) || ($reason !== null && !is_string($reason))
        || !is_array($context) || !pcv_log_rule_matches($event, $severity, $outcome, $reason)
        || !pcv_log_event_context_valid($event, $context)) {
        return null;
    }
    $entry = [
        'schema_version' => PCV_LOG_SCHEMA_VERSION,
        'plugin_version' => $pluginVersion,
        'timestamp' => $timestamp,
        'event' => $event,
        'severity' => $severity,
        'outcome' => $outcome,
        'reason' => $reason,
        'request_id' => $requestId,
        'config_id' => $configId,
        'playthrough_ref' => $playthroughRef,
        'elapsed_ms' => $elapsed,
        'context' => pcv_log_clean_context($event, $context),
    ];
    if (array_key_exists('logging_revision', $row)) {
        if ($row['logging_revision'] !== PCV_LOG_INSTRUMENTATION_REVISION) {
            return null;
        }
        $entry['logging_revision'] = PCV_LOG_INSTRUMENTATION_REVISION;
    }
    return $entry;
}

function pcv_diagnostics_filter_matches(array $entry, array $filters): bool
{
    if (($filters['request'] !== null && $entry['request_id'] !== $filters['request'])
        || ($filters['config'] !== null && $entry['config_id'] !== $filters['config'])
        || ($filters['event'] !== null && $entry['event'] !== $filters['event'])
        || ($filters['severity'] !== null && $entry['severity'] !== $filters['severity'])) {
        return false;
    }
    $correlation = $entry['context']['correlation'] ?? [];
    foreach (['event_id', 'utterance_id', 'linked_request_id'] as $key) {
        if ($filters[$key] !== null && ($correlation[$key] ?? null) !== (string)$filters[$key]) {
            return false;
        }
    }
    return true;
}

function pcv_diagnostics_empty_omissions(): array
{
    return ['malformed' => 0, 'oversized' => 0, 'unknown_schema' => 0, 'unsupported_revision' => 0,
        'filtered' => 0, 'capped' => 0, 'read_failed' => 0];
}

function pcv_diagnostics_parse_file(array $file, array $filters, array &$entries, array &$omissions): bool
{
    $handle = $file['handle'] ?? null;
    $remaining = $file['size'] ?? null;
    if (!is_resource($handle) || !is_int($remaining) || $remaining < 0) {
        $omissions['read_failed']++;
        return false;
    }
    $line = '';
    $oversized = false;
    $lineConsumer = static function (string $rawLine) use ($filters, &$entries, &$omissions): void {
        $row = json_decode(rtrim($rawLine, "\r\n"), true, 32);
        if (!is_array($row)) {
            $omissions['malformed']++;
            return;
        }
        if (($row['schema_version'] ?? null) !== PCV_LOG_SCHEMA_VERSION) {
            $omissions['unknown_schema']++;
            return;
        }
        if (array_key_exists('logging_revision', $row)
            && $row['logging_revision'] !== PCV_LOG_INSTRUMENTATION_REVISION) {
            $omissions['unsupported_revision']++;
            return;
        }
        $entry = pcv_diagnostics_project_entry($row);
        if ($entry === null) {
            $omissions['malformed']++;
            return;
        }
        if (!pcv_diagnostics_filter_matches($entry, $filters)) {
            $omissions['filtered']++;
            return;
        }
        $entries[] = $entry;
        if (count($entries) > $filters['limit']) {
            array_shift($entries);
            $omissions['capped']++;
        }
    };

    while ($remaining > 0) {
        $chunk = @fread($handle, min(8192, $remaining));
        if (!is_string($chunk) || $chunk === '') {
            $omissions['read_failed']++;
            return false;
        }
        $remaining -= strlen($chunk);
        $parts = explode("\n", $chunk);
        $last = count($parts) - 1;
        foreach ($parts as $index => $part) {
            $terminated = $index < $last;
            $piece = $part . ($terminated ? "\n" : '');
            if ($oversized) {
                if ($terminated) {
                    $oversized = false;
                    $omissions['oversized']++;
                }
                continue;
            }
            if (strlen($line) + strlen($piece) > PCV_LOG_MAX_ENTRY_BYTES) {
                $line = '';
                $oversized = true;
                if ($terminated) {
                    $oversized = false;
                    $omissions['oversized']++;
                }
                continue;
            }
            $line .= $piece;
            if ($terminated) {
                $lineConsumer($line);
                $line = '';
            }
        }
    }
    if ($oversized) {
        $omissions['oversized']++;
    } elseif ($line !== '') {
        $lineConsumer($line);
    }
    return true;
}

function pcv_diagnostics_result_health(string $status, array $omissions, int $capturedSegments): array
{
    return [
        'read_status' => $status,
        'storage' => pcv_log_storage_health(),
        'completeness' => 'bounded_history',
        'captured_segments' => $capturedSegments,
        'omissions' => $omissions,
    ];
}

function pcv_diagnostics_read(string $logPath, array $filters): array
{
    try {
        $filters = pcv_diagnostics_normalize_filters($filters);
    } catch (InvalidArgumentException) {
        return ['status' => 'invalid', 'entries' => [], 'health' => pcv_diagnostics_result_health('invalid', pcv_diagnostics_empty_omissions(), 0)];
    }
    $snapshot = pcv_diagnostics_acquire_snapshot($logPath);
    $omissions = pcv_diagnostics_empty_omissions();
    $entries = [];
    if ($snapshot['status'] !== 'ok') {
        return ['status' => $snapshot['status'], 'entries' => [],
            'health' => pcv_diagnostics_result_health($snapshot['status'], $omissions, 0)];
    }
    $files = $snapshot['files'];
    $readOk = true;
    try {
        foreach ($files as $file) {
            if (!pcv_diagnostics_parse_file($file, $filters, $entries, $omissions)) {
                $readOk = false;
                break;
            }
        }
    } finally {
        pcv_diagnostics_close_snapshot_files($files);
    }
    $status = $readOk ? 'ok' : 'unavailable';
    if (!$readOk) {
        $entries = [];
    }
    return ['status' => $status, 'entries' => $entries,
        'health' => pcv_diagnostics_result_health($status, $omissions, count($snapshot['files']))];
}

/** Web-safe reader. Results are sanitized projections and never include a filesystem path. */
function pcv_diagnostics_load(array $filters = []): array
{
    try {
        $filters = pcv_diagnostics_normalize_filters($filters);
        $directory = pcv_log_resolve_directory(false);
        if (!is_string($directory)) {
            $omissions = pcv_diagnostics_empty_omissions();
            return ['status' => 'unavailable', 'entries' => [],
                'health' => pcv_diagnostics_result_health('unavailable', $omissions, 0)];
        }
        return pcv_diagnostics_read($directory . DIRECTORY_SEPARATOR . 'events.jsonl', $filters);
    } catch (InvalidArgumentException) {
        $omissions = pcv_diagnostics_empty_omissions();
        return ['status' => 'invalid', 'entries' => [],
            'health' => pcv_diagnostics_result_health('invalid', $omissions, 0)];
    } catch (Throwable) {
        $omissions = pcv_diagnostics_empty_omissions();
        return ['status' => 'unavailable', 'entries' => [],
            'health' => pcv_diagnostics_result_health('unavailable', $omissions, 0)];
    }
}
