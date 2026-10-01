<?php
declare(strict_types=1);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function http_request(string $url, string $method = 'GET', ?string $body = null, ?string $cookie = null, array $headers = []): array
{
    if ($cookie !== null) {
        $headers[] = 'Cookie: ' . $cookie;
    }
    $options = ['method' => $method, 'header' => implode("\r\n", $headers), 'ignore_errors' => true, 'timeout' => 3];
    if ($body !== null) {
        $options['content'] = $body;
    }
    $response = @file_get_contents($url, false, stream_context_create(['http' => $options]));
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('/\AHTTP\/\S+ ([0-9]{3})/', $header, $match) === 1) {
            $status = (int)$match[1];
            break;
        }
    }
    return ['status' => $status, 'body' => is_string($response) ? $response : '', 'headers' => $responseHeaders];
}

function response_header(array $response, string $name): ?string
{
    foreach ($response['headers'] as $header) {
        if (stripos($header, $name . ':') === 0) {
            return trim(substr($header, strlen($name) + 1));
        }
    }
    return null;
}

function remove_fixture(string $directory): void
{
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
        }
    }
    @rmdir($directory);
}

$root = dirname(__DIR__);
$logDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-ui-http-' . bin2hex(random_bytes(8));
if (!mkdir($logDirectory, 0700) && !is_dir($logDirectory)) {
    throw new RuntimeException('Could not create isolated HTTP diagnostics storage.');
}
chmod($logDirectory, 0700);
putenv('PCV_LOG_DIR=' . $logDirectory);
require_once $root . '/server/log.php';
pcv_log_begin_request();
pcv_log_event('ui.page_open', 'info', 'ok');

$fixtureEntry = [
    'schema_version' => 1, 'logging_revision' => 2, 'plugin_version' => '0.1.4',
    'timestamp' => '2026-09-30T12:00:00.123Z', 'event' => 'ui.page_open', 'severity' => 'info', 'outcome' => 'ok',
    'reason' => null, 'request_id' => str_repeat('b', 32), 'config_id' => null, 'playthrough_ref' => null,
    'elapsed_ms' => 1, 'context' => [], 'raw_dialogue' => 'PRIVATE_DIALOGUE_MUST_NOT_EXPORT',
    'absolute_path' => 'C:/private/CHIM/events.jsonl', 'message' => 'PRIVATE_MESSAGE_MUST_NOT_EXPORT',
];
$logPath = $logDirectory . DIRECTORY_SEPARATOR . 'events.jsonl';
if (file_put_contents($logPath, json_encode($fixtureEntry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", FILE_APPEND) === false) {
    remove_fixture($logDirectory);
    throw new RuntimeException('Could not seed the isolated sanitized-log fixture.');
}
file_put_contents($logPath, "{malformed fixture row\n", FILE_APPEND);
chmod($logPath, 0600);

$socket = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if (!is_resource($socket)) {
    remove_fixture($logDirectory);
    throw new RuntimeException('Could not reserve a local test port.');
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$port = (int)substr((string)$address, strrpos((string)$address, ':') + 1);
$environment = getenv();
$environment['PCV_LOG_DIR'] = $logDirectory;
$nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$router = $root . '/server/index.php';
$serverDirectory = $root . '/server';
$process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $logDirectory, '-S', '127.0.0.1:' . $port, '-t', $serverDirectory, $router], [
    0 => ['file', $nullDevice, 'r'], 1 => ['file', $nullDevice, 'a'], 2 => ['file', $nullDevice, 'a'],
], $pipes, $root, $environment, ['bypass_shell' => true]);
if (!is_resource($process)) {
    remove_fixture($logDirectory);
    throw new RuntimeException('Could not start the isolated PCV HTTP server.');
}

$baseUrl = 'http://127.0.0.1:' . $port . '/index.php?view=logs';
try {
    $page = null;
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $page = http_request($baseUrl);
        if ($page['status'] !== 0) {
            break;
        }
        usleep(50000);
    }
    check($page['status'] === 200 && str_contains($page['body'], 'Operational logs')
        && !str_contains($page['body'], 'runtime configuration is unavailable'),
        'The standalone Logs route must load before CHIM configuration, catalog, or database dependencies.');
    check(str_contains(implode("\n", $page['headers']), 'no-store')
        && response_header($page, 'Content-Security-Policy') !== null,
        'The Logs route must send no-store and the page security policy.');
    $setCookie = response_header($page, 'Set-Cookie');
    check(is_string($setCookie) && preg_match('/\A(PHPSESSID=[^;]+)/', $setCookie, $cookieMatch) === 1,
        'The Logs form must start a server-side CSRF session.');
    $cookie = $cookieMatch[1];
    $sessionPath = $logDirectory . DIRECTORY_SEPARATOR . 'sess_' . substr($cookie, strlen('PHPSESSID='));
    check(is_file($sessionPath), 'The isolated HTTP server must keep its session file inside the private fixture directory.');
    check(preg_match('/name="csrf" value="([a-f0-9]{64})"/', $page['body'], $csrfMatch) === 1,
        'The Logs form must include the session CSRF token.');
    $csrf = $csrfMatch[1];

    $badCsrf = http_request($baseUrl, 'POST', http_build_query(['action' => 'read', 'csrf' => 'bad']), $cookie,
        ['Content-Type: application/x-www-form-urlencoded']);
    check($badCsrf['status'] === 403, 'A Logs read without the session CSRF token must be denied.');

    $read = http_request($baseUrl, 'POST', http_build_query([
        'action' => 'read', 'csrf' => $csrf, 'event' => 'ui.page_open', 'limit' => '20',
    ]), $cookie, ['Content-Type: application/x-www-form-urlencoded']);
    check($read['status'] === 200 && str_contains($read['body'], 'ui.page_open'),
        'A valid filtered read must return sanitized diagnostic entries.');
    check(!str_contains($read['body'], 'PRIVATE_DIALOGUE_MUST_NOT_EXPORT')
        && !str_contains($read['body'], 'C:/private/CHIM/events.jsonl')
        && !str_contains($read['body'], 'PRIVATE_MESSAGE_MUST_NOT_EXPORT'),
        'The HTML viewer must not expose raw record fields, messages, or filesystem paths.');
    check(str_contains($read['body'], 'filtered') && str_contains($read['body'], 'capped')
        && str_contains($read['body'], 'Current request only'),
        'The viewer must distinguish filter/cap counts and current-request write health.');

    $export = http_request($baseUrl, 'POST', http_build_query([
        'action' => 'export', 'csrf' => $csrf, 'event' => 'ui.page_open', 'limit' => '20',
    ]), $cookie, ['Content-Type: application/x-www-form-urlencoded']);
    check($export['status'] === 200
        && response_header($export, 'Content-Type') === 'application/x-ndjson; charset=UTF-8'
        && response_header($export, 'Content-Disposition') === 'attachment; filename="private-conversation-logs.jsonl"',
        'A valid export must use a fixed JSONL content type and filename.');
    check(response_header($export, 'X-PCV-Diagnostics-Completeness') === 'bounded_history'
        && preg_match('/\Amalformed=1,oversized=0,unknown_schema=0,unsupported_revision=0,read_failed=0,filtered=[0-9]+,capped=0\z/',
            (string)response_header($export, 'X-PCV-Diagnostics-Omissions')) === 1,
        'Limited exports must expose fixed completeness and omission categories in safe response headers.');
    check(!str_contains($export['body'], 'PRIVATE_DIALOGUE_MUST_NOT_EXPORT')
        && !str_contains($export['body'], 'C:/private/CHIM/events.jsonl')
        && !str_contains($export['body'], 'PRIVATE_MESSAGE_MUST_NOT_EXPORT'),
        'The JSONL export must contain only sanitized reader projections.');
    foreach (explode("\n", trim($export['body'])) as $line) {
        if ($line !== '') {
            check(is_array(json_decode($line, true)), 'Every nonempty exported line must be valid JSON.');
        }
    }

    foreach ([['X-Forwarded-For: 127.0.0.1'], ['X-Real-IP: 127.0.0.1'],
        ['Remote-User: forged', 'X-Forwarded-For: 127.0.0.1']] as $headers) {
        $denied = http_request($baseUrl, 'GET', null, null, $headers);
        check($denied['status'] === 403 && str_contains($denied['body'], 'Logs access is locked'),
            'Forwarded and caller-supplied identity headers must receive locked Logs instructions: '
                . $denied['status'] . ' ' . substr($denied['body'], 0, 200));
    }

    $report = http_request($baseUrl, 'POST', http_build_query([
        'action' => 'client_report', 'csrf' => $csrf, 'code' => 'timeout',
    ]), $cookie, ['Content-Type: application/x-www-form-urlencoded']);
    $duplicateReport = http_request($baseUrl, 'POST', http_build_query([
        'action' => 'client_report', 'csrf' => $csrf, 'code' => 'timeout',
    ]), $cookie, ['Content-Type: application/x-www-form-urlencoded']);
    check($report['status'] === 204 && $duplicateReport['status'] === 204,
        'Fixed-code browser reports must be accepted while duplicate session/code reports are acknowledged silently.');
    $reportRead = http_request($baseUrl, 'POST', http_build_query([
        'action' => 'read', 'csrf' => $csrf, 'event' => 'ui.browser_refresh_failed', 'limit' => '20',
    ]), $cookie, ['Content-Type: application/x-www-form-urlencoded']);
    check(substr_count($reportRead['body'], '<td>ui.browser_refresh_failed</td>') === 1
        && str_contains($reportRead['body'], 'browser_refresh_timeout')
        && str_contains($reportRead['body'], '&quot;source&quot;:&quot;browser&quot;')
        && !str_contains($reportRead['body'], 'timeout private detail'),
        'The server must persist one fixed-code report per session and code without caller details.');
    $invalidReport = http_request($baseUrl, 'POST', http_build_query([
        'action' => 'client_report', 'csrf' => $csrf, 'code' => 'timeout private detail',
    ]), $cookie, ['Content-Type: application/x-www-form-urlencoded']);
    check($invalidReport['status'] === 400, 'Free-form browser failure details must be rejected.');

    $invalidFilter = http_request($baseUrl, 'POST', http_build_query([
        'action' => 'read', 'csrf' => $csrf, 'event' => 'unknown.event',
    ]), $cookie, ['Content-Type: application/x-www-form-urlencoded']);
    check($invalidFilter['status'] === 400, 'Unknown diagnostic filters must be rejected.');

    $rejections = http_request($baseUrl, 'POST', http_build_query([
        'action' => 'read', 'csrf' => $csrf, 'event' => 'ui.diagnostics_rejected', 'limit' => '50',
    ]), $cookie, ['Content-Type: application/x-www-form-urlencoded']);
    check(substr_count($rejections['body'], '<td>access_denied</td>') >= 3
        && str_contains($rejections['body'], '<td>csrf_failed</td>')
        && str_contains($rejections['body'], '<td>invalid_failure_code</td>')
        && str_contains($rejections['body'], '<td>invalid_filter</td>')
        && !str_contains($rejections['body'], 'forged') && !str_contains($rejections['body'], 'private detail'),
        'Access, CSRF, invalid-filter, and invalid-code denials must be recorded without caller payloads.');

    $lock = fopen($logDirectory . DIRECTORY_SEPARATOR . 'events.lock', 'rb');
    check(is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB), 'Could not hold the fixture log lock for the busy-reader case.');
    $busyExport = http_request($baseUrl, 'POST', http_build_query([
        'action' => 'export', 'csrf' => $csrf, 'event' => 'ui.page_open',
    ]), $cookie, ['Content-Type: application/x-www-form-urlencoded']);
    flock($lock, LOCK_UN);
    fclose($lock);
    check($busyExport['status'] === 503 && response_header($busyExport, 'Content-Disposition') === null
        && str_contains($busyExport['body'], 'The log is busy'),
        'A busy reader must return a safe failure response instead of an empty successful attachment.');

    unlink($logDirectory . DIRECTORY_SEPARATOR . 'events.lock');
    $unavailableExport = http_request($baseUrl, 'POST', http_build_query([
        'action' => 'export', 'csrf' => $csrf, 'event' => 'ui.page_open',
    ]), $cookie, ['Content-Type: application/x-www-form-urlencoded']);
    check($unavailableExport['status'] === 503 && response_header($unavailableExport, 'Content-Disposition') === null
        && str_contains($unavailableExport['body'], 'The log is unavailable'),
        'An unavailable reader must return a safe failure response instead of an empty successful attachment.');

    echo "HTTP diagnostics checks passed.\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    remove_fixture($logDirectory);
}
