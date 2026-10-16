<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Not found.\n";
    exit;
}

require_once __DIR__ . '/log_reader.php';

function pcv_diagnostics_usage(): string
{
    return "Usage: php server/diagnostics.php [--request ID] [--config UUID] [--event NAME] [--severity LEVEL] [--event-id ID] [--utterance-id ID] [--linked-request-id ID] [--limit N] [--jsonl]\n"
        . "       php server/diagnostics.php --help\n\n"
        . "Show sanitized operational events from the private extension log. The default limit is 100; N must be 1 through 1000.\n"
        . "Run as the same effective user as the CHIM PHP worker. --jsonl writes matching events to stdout.\n";
}

function pcv_diagnostics_parse_arguments(array $arguments): array
{
    $options = ['request' => null, 'config' => null, 'event' => null, 'severity' => null,
        'event_id' => null, 'utterance_id' => null, 'linked_request_id' => null,
        'limit' => 100, 'jsonl' => false, 'help' => false];
    $valueOptions = [
        '--request' => 'request', '--config' => 'config', '--event' => 'event', '--severity' => 'severity',
        '--event-id' => 'event_id', '--utterance-id' => 'utterance_id', '--linked-request-id' => 'linked_request_id',
        '--limit' => 'limit',
    ];
    $seen = [];
    for ($index = 1; $index < count($arguments); $index++) {
        $argument = $arguments[$index];
        if ($argument === '--help' || $argument === '--jsonl') {
            $key = $argument === '--help' ? 'help' : 'jsonl';
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('duplicate option');
            }
            $seen[$key] = true;
            $options[$key] = true;
            continue;
        }
        $equals = strpos($argument, '=');
        $name = $equals === false ? $argument : substr($argument, 0, $equals);
        if (!isset($valueOptions[$name]) || isset($seen[$name])) {
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
        $key = $valueOptions[$name];
        if ($key === 'limit') {
            if (preg_match('/\A[0-9]{1,4}\z/D', $value) !== 1 || (int)$value < 1 || (int)$value > PCV_DIAGNOSTICS_MAX_LIMIT) {
                throw new InvalidArgumentException('invalid limit');
            }
            $options[$key] = (int)$value;
        } else {
            $options[$key] = $value;
        }
    }
    if ($options['help']) {
        return $options;
    }
    try {
        pcv_diagnostics_normalize_filters($options);
    } catch (InvalidArgumentException $error) {
        throw new InvalidArgumentException('invalid filter', 0, $error);
    }
    return $options;
}

function pcv_diagnostics_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
}

function pcv_diagnostics_health_line(array $health): string
{
    $storage = $health['storage'] ?? [];
    $omissions = $health['omissions'] ?? [];
    $omissions = is_array($omissions) ? $omissions : [];
    $dataOmissions = 0;
    foreach (['malformed', 'oversized', 'unknown_schema', 'unsupported_revision', 'read_failed'] as $key) {
        $dataOmissions += max(0, (int)($omissions[$key] ?? 0));
    }
    return 'Diagnostic reader: status=' . (string)($health['read_status'] ?? 'unavailable')
        . ' storage=' . (string)($storage['storage_mode'] ?? 'unavailable')
        . ' write_status=' . (string)($storage['write_status'] ?? 'not_verified')
        . ' history=' . (string)($health['completeness'] ?? 'bounded_history')
        . ' captured_segments=' . (int)($health['captured_segments'] ?? 0)
        . ' data_omitted=' . $dataOmissions
        . ' filtered=' . max(0, (int)($omissions['filtered'] ?? 0))
        . ' capped=' . max(0, (int)($omissions['capped'] ?? 0));
}

function pcv_diagnostics_write(array $entries, bool $jsonl, array $health): void
{
    $healthLine = pcv_diagnostics_health_line($health);
    if ($jsonl) {
        fwrite(STDERR, $healthLine . "\n");
    } else {
        fwrite(STDOUT, $healthLine . "\n");
    }
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
    if ($result['status'] === 'invalid') {
        fwrite(STDERR, "Invalid diagnostics filter. Use --help.\n");
        return 2;
    }
    pcv_diagnostics_write($result['entries'], $options['jsonl'], $result['health']);
    return 0;
}

if (!defined('PCV_DIAGNOSTICS_TEST') || PCV_DIAGNOSTICS_TEST !== true) {
    exit(pcv_diagnostics_main($argv));
}
