<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Not found.\n";
    exit;
}

require_once __DIR__ . '/log.php';

const PCV_DIAGNOSTICS_MAX_LIMIT = 1000;

function pcv_diagnostics_usage(): string
{
    return "Usage: php server/diagnostics.php [--request ID] [--config UUID] [--limit N] [--jsonl]\n"
        . "       php server/diagnostics.php --help\n\n"
        . "Show recent operational events from the private extension log. The default limit is 100; N must be 1 through 1000.\n"
        . "Run as the same effective user as the CHIM PHP worker. --jsonl writes sanitized matching events to stdout.\n";
}

function pcv_diagnostics_parse_arguments(array $arguments): array
{
    $options = ['request' => null, 'config' => null, 'limit' => 100, 'jsonl' => false, 'help' => false];
    $seen = [];
    for ($index = 1; $index < count($arguments); $index++) {
        $argument = $arguments[$index];
        if ($argument === '--help') {
            if (isset($seen['help'])) {
                throw new InvalidArgumentException('duplicate option');
            }
            $seen['help'] = true;
            $options['help'] = true;
            continue;
        }
        if ($argument === '--jsonl') {
            if (isset($seen['jsonl'])) {
                throw new InvalidArgumentException('duplicate option');
            }
            $seen['jsonl'] = true;
            $options['jsonl'] = true;
            continue;
        }

        $equals = strpos($argument, '=');
        $name = $equals === false ? $argument : substr($argument, 0, $equals);
        if (!in_array($name, ['--request', '--config', '--limit'], true) || isset($seen[$name])) {
            throw new InvalidArgumentException('invalid option');
        }
        if ($equals === false) {
            if (!isset($arguments[$index + 1]) || str_starts_with($arguments[$index + 1], '--')) {
                throw new InvalidArgumentException('missing option value');
            }
            $value = $arguments[++$index];
        } else {
            $value = substr($argument, $equals + 1);
        }
        if ($value === '') {
            throw new InvalidArgumentException('empty option value');
        }
        $seen[$name] = true;

        if ($name === '--request') {
            if (preg_match('/\A(?:[a-f0-9]{32}|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})\z/D', $value) !== 1) {
                throw new InvalidArgumentException('invalid request id');
            }
            $options['request'] = $value;
        } elseif ($name === '--config') {
            if (!pcv_log_valid_uuid($value)) {
                throw new InvalidArgumentException('invalid config id');
            }
            $options['config'] = $value;
        } else {
            if (preg_match('/\A[0-9]{1,4}\z/D', $value) !== 1 || (int)$value < 1 || (int)$value > PCV_DIAGNOSTICS_MAX_LIMIT) {
                throw new InvalidArgumentException('invalid limit');
            }
            $options['limit'] = (int)$value;
        }
    }
    return $options;
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

/** @return array{status:string,handle:mixed} */
function pcv_diagnostics_acquire_snapshot(string $logPath): array
{
    if (basename($logPath) !== 'events.jsonl') {
        return ['status' => 'unavailable', 'handle' => null];
    }
    $directoryPath = dirname($logPath);
    clearstatcache(true, $directoryPath);
    if (!file_exists($directoryPath) && !is_link($directoryPath)) {
        return ['status' => 'empty', 'handle' => null];
    }
    $directory = realpath($directoryPath);
    if ($directory === false) {
        return ['status' => 'unavailable', 'handle' => null];
    }

    $hasLogs = false;
    foreach (['events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl'] as $name) {
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (is_link($path)) {
            return ['status' => 'unavailable', 'handle' => null];
        }
        $hasLogs = $hasLogs || file_exists($path);
    }

    $lockPath = $directory . DIRECTORY_SEPARATOR . 'events.lock';
    if (!file_exists($lockPath) && !is_link($lockPath)) {
        return ['status' => $hasLogs ? 'unavailable' : 'empty', 'handle' => null];
    }
    if (is_link($lockPath) || !pcv_log_private_file($lockPath)) {
        return ['status' => 'unavailable', 'handle' => null];
    }
    $handle = @fopen($lockPath, 'rb');
    if (!pcv_diagnostics_private_handle($handle)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return ['status' => 'unavailable', 'handle' => null];
    }
    if (!@flock($handle, LOCK_SH | LOCK_NB)) {
        fclose($handle);
        return ['status' => 'busy', 'handle' => null];
    }
    return ['status' => 'locked', 'handle' => $handle];
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
    $rule = pcv_log_event_rules()[$event];
    $timestamp = $row['timestamp'] ?? null;
    $pluginVersion = $row['plugin_version'] ?? null;
    $requestId = $row['request_id'] ?? null;
    $configId = $row['config_id'] ?? null;
    $playthroughRef = $row['playthrough_ref'] ?? null;
    $elapsed = $row['elapsed_ms'] ?? null;
    $reason = $row['reason'] ?? null;
    $context = $row['context'] ?? null;
    if (!is_string($timestamp) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{3}Z\z/D', $timestamp) !== 1
        || !is_string($pluginVersion) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9.+-]{0,31}\z/D', $pluginVersion) !== 1
        || !is_string($requestId) || preg_match('/\A(?:[a-f0-9]{32}|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})\z/D', $requestId) !== 1
        || ($configId !== null && (!is_string($configId) || !pcv_log_valid_uuid($configId)))
        || ($playthroughRef !== null && (!is_string($playthroughRef) || preg_match('/\A[a-f0-9]{16}\z/D', $playthroughRef) !== 1))
        || ($elapsed !== null && (!is_int($elapsed) || $elapsed < 0))
        || !is_string($row['severity'] ?? null) || $row['severity'] !== $rule['severity']
        || !is_string($row['outcome'] ?? null) || $row['outcome'] !== $rule['outcome']
        || ($reason !== null && !is_string($reason))
        || !is_array($context) || !pcv_log_reason_allowed($event, $reason)) {
        return null;
    }

    return [
        'schema_version' => PCV_LOG_SCHEMA_VERSION,
        'plugin_version' => $pluginVersion,
        'timestamp' => $timestamp,
        'event' => $event,
        'severity' => $rule['severity'],
        'outcome' => $rule['outcome'],
        'reason' => $reason,
        'request_id' => $requestId,
        'config_id' => $configId,
        'playthrough_ref' => $playthroughRef,
        'elapsed_ms' => $elapsed,
        'context' => pcv_log_clean_context($event, $context),
    ];
}

/** @return array{status:string,entries:list<array<string,mixed>>} */
function pcv_diagnostics_read(string $logPath, array $filters): array
{
    $snapshot = pcv_diagnostics_acquire_snapshot($logPath);
    if ($snapshot['status'] !== 'locked') {
        return ['status' => $snapshot['status'], 'entries' => []];
    }

    $handle = $snapshot['handle'];
    $directory = (string)realpath(dirname($logPath));
    $paths = [];
    for ($index = 4; $index >= 1; $index--) {
        $paths[] = $directory . DIRECTORY_SEPARATOR . 'events.' . $index . '.jsonl';
    }
    $paths[] = $directory . DIRECTORY_SEPARATOR . 'events.jsonl';
    $entries = [];

    try {
        foreach ($paths as $path) {
            clearstatcache(true, $path);
            if (!file_exists($path) && !is_link($path)) {
                continue;
            }
            if (is_link($path) || !pcv_log_private_file($path)) {
                return ['status' => 'unavailable', 'entries' => []];
            }
            $size = @filesize($path);
            if (!is_int($size) || $size > PCV_LOG_MAX_FILE_BYTES) {
                return ['status' => 'unavailable', 'entries' => []];
            }
            $file = @fopen($path, 'rb');
            if (!pcv_diagnostics_private_handle($file)) {
                if (is_resource($file)) {
                    fclose($file);
                }
                return ['status' => 'unavailable', 'entries' => []];
            }

            $bytesRead = 0;
            while (!feof($file)) {
                $line = '';
                $lineComplete = false;
                do {
                    $chunk = fgets($file, PCV_LOG_MAX_ENTRY_BYTES + 1);
                    if ($chunk === false) {
                        break;
                    }
                    $bytesRead += strlen($chunk);
                    if ($bytesRead > PCV_LOG_MAX_FILE_BYTES) {
                        fclose($file);
                        return ['status' => 'unavailable', 'entries' => []];
                    }
                    if (strlen($line) <= PCV_LOG_MAX_ENTRY_BYTES) {
                        $line .= $chunk;
                    }
                    $lineComplete = str_ends_with($chunk, "\n") || feof($file);
                } while (!$lineComplete);

                if ($chunk === false) {
                    break;
                }
                if (!$lineComplete || strlen($line) > PCV_LOG_MAX_ENTRY_BYTES) {
                    continue;
                }
                $decoded = json_decode(rtrim($line, "\r\n"), true);
                $entry = pcv_diagnostics_project_entry($decoded);
                if ($entry === null
                    || ($filters['request'] !== null && $entry['request_id'] !== $filters['request'])
                    || ($filters['config'] !== null && $entry['config_id'] !== $filters['config'])) {
                    continue;
                }
                $entries[] = $entry;
                if (count($entries) > $filters['limit']) {
                    array_shift($entries);
                }
            }
            fclose($file);
        }
    } finally {
        @flock($handle, LOCK_UN);
        fclose($handle);
    }
    return ['status' => 'ok', 'entries' => $entries];
}

function pcv_diagnostics_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
}

function pcv_diagnostics_write(array $entries, bool $jsonl): void
{
    if ($entries === []) {
        if (!$jsonl) {
            fwrite(STDOUT, "No matching diagnostic events.\n");
        }
        return;
    }
    foreach ($entries as $entry) {
        if ($jsonl) {
            fwrite(STDOUT, pcv_diagnostics_json($entry) . "\n");
            continue;
        }
        $context = $entry['context'] === [] ? '' : ' context=' . pcv_diagnostics_json($entry['context']);
        $reason = $entry['reason'] === null ? '' : ' reason=' . $entry['reason'];
        $config = $entry['config_id'] === null ? 'none' : $entry['config_id'];
        $duration = $entry['elapsed_ms'] === null ? '' : ' elapsed_ms=' . $entry['elapsed_ms'];
        $playthrough = $entry['playthrough_ref'] === null ? '' : ' playthrough_ref=' . $entry['playthrough_ref'];
        fwrite(STDOUT, '[' . $entry['timestamp'] . '] ' . strtoupper($entry['severity']) . ' '
            . $entry['event'] . ' ' . $entry['outcome'] . $reason
            . ' request_id=' . $entry['request_id'] . ' config_id=' . $config
            . $duration . $playthrough . $context . "\n");
    }
}

function pcv_diagnostics_main(array $arguments): int
{
    try {
        $options = pcv_diagnostics_parse_arguments($arguments);
    } catch (InvalidArgumentException) {
        fwrite(STDERR, "Invalid diagnostics options. Use --help.\n");
        return 2;
    }
    if ($options['help']) {
        fwrite(STDOUT, pcv_diagnostics_usage());
        return 0;
    }

    $logPath = pcv_log_path();
    if (!is_string($logPath)) {
        fwrite(STDERR, "Diagnostic logs are unavailable. Run as the CHIM PHP worker user.\n");
        return 1;
    }
    $result = pcv_diagnostics_read($logPath, $options);
    if ($result['status'] === 'busy') {
        fwrite(STDERR, "Diagnostic logs are busy; retry shortly.\n");
        return 1;
    }
    if ($result['status'] === 'unavailable') {
        fwrite(STDERR, "Diagnostic logs are unavailable.\n");
        return 1;
    }
    pcv_diagnostics_write($result['entries'], $options['jsonl']);
    return 0;
}

if (!defined('PCV_DIAGNOSTICS_TEST') || PCV_DIAGNOSTICS_TEST !== true) {
    exit(pcv_diagnostics_main($argv));
}
