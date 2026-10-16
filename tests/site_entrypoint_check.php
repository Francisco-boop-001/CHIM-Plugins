<?php
declare(strict_types=1);

function siteEntrypointAssert(bool $condition, string $code): void
{
    if (!$condition) {
        throw new RuntimeException($code);
    }
}

function siteEntrypointResponse(string $url, string $method = 'GET', array $headers = [], string $body = ''): array
{
    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $body,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'timeout' => 3.0,
        ],
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = null;
    $responseHeaderMap = [];
    foreach ($responseHeaders as $line) {
        if (preg_match('/^HTTP\/\S+\s+([0-9]{3})\b/', $line, $match) === 1) {
            $status = (int)$match[1];
            continue;
        }
        $separator = strpos($line, ':');
        if ($separator !== false) {
            $responseHeaderMap[strtolower(trim(substr($line, 0, $separator)))] = trim(substr($line, $separator + 1));
        }
    }
    return [
        'status' => $status,
        'headers' => $responseHeaderMap,
        'body' => is_string($responseBody) ? $responseBody : '',
    ];
}

function siteEntrypointHeader(array $response, string $name): ?string
{
    $value = $response['headers'][strtolower($name)] ?? null;
    return is_string($value) ? $value : null;
}

$sourceIndex = realpath(__DIR__ . '/../server/index.php');
$sourceDashboard = realpath(__DIR__ . '/../server/dashboard.php');
$tempRoot = realpath(sys_get_temp_dir());
siteEntrypointAssert(
    is_string($sourceIndex)
    && is_string($sourceDashboard)
    && is_string($tempRoot),
    'source-path'
);

$sandbox = $tempRoot . DIRECTORY_SEPARATOR . 'mp-site-entrypoint-' . bin2hex(random_bytes(12));
siteEntrypointAssert(!file_exists($sandbox) && mkdir($sandbox, 0700), 'sandbox-create');
$sandboxRoot = realpath($sandbox);
siteEntrypointAssert(is_string($sandboxRoot) && dirname($sandboxRoot) === $tempRoot, 'sandbox-boundary');

$process = null;
$serverOutput = $sandboxRoot . DIRECTORY_SEPARATOR . 'server.stdout.log';
$serverError = $sandboxRoot . DIRECTORY_SEPARATOR . 'server.stderr.log';
$routerPath = $sandboxRoot . DIRECTORY_SEPARATOR . 'router.php';
$appRoot = $sandboxRoot . DIRECTORY_SEPARATOR . 'ext';
$pluginRoot = $appRoot . DIRECTORY_SEPARATOR . 'CHIM-MindPoisoning';
$port = null;

try {
    siteEntrypointAssert(mkdir($appRoot, 0700), 'app-root-create');
    siteEntrypointAssert(mkdir($pluginRoot, 0700), 'plugin-root-create');
    siteEntrypointAssert(copy($sourceIndex, $pluginRoot . DIRECTORY_SEPARATOR . 'index.php'), 'index-copy');
    siteEntrypointAssert(copy($sourceDashboard, $pluginRoot . DIRECTORY_SEPARATOR . 'dashboard.php'), 'dashboard-copy');
    $router = <<<'ROUTER'
<?php
declare(strict_types=1);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($requestPath !== '/ext/CHIM-MindPoisoning/index.php') {
    http_response_code(404);
    return true;
}
$hasForwardedHeader = array_key_exists('HTTP_FORWARDED', $_SERVER)
    || array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)
    || array_key_exists('HTTP_X_REAL_IP', $_SERVER);
$_SERVER['SCRIPT_NAME'] = $requestPath;
$_SERVER['REMOTE_ADDR'] = ($hasForwardedHeader || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
    ? '127.0.0.1'
    : '192.0.2.44';
require __DIR__ . '/ext/CHIM-MindPoisoning/index.php';
return true;
ROUTER;
    siteEntrypointAssert(file_put_contents($routerPath, $router) !== false, 'router-write');

    $probe = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    siteEntrypointAssert(is_resource($probe), 'loopback-port-allocation');
    $address = stream_socket_get_name($probe, false);
    fclose($probe);
    siteEntrypointAssert(is_string($address) && preg_match('/^127\.0\.0\.1:([0-9]+)$/', $address, $match) === 1, 'loopback-address');
    $port = (int)$match[1];

    $descriptors = [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => ['file', $serverOutput, 'w'],
        2 => ['file', $serverError, 'w'],
    ];
    $process = proc_open(
        [PHP_BINARY, '-n', '-S', '127.0.0.1:' . $port, '-t', $sandboxRoot, $routerPath],
        $descriptors,
        $pipes,
        $sandboxRoot
    );
    siteEntrypointAssert(is_resource($process), 'server-start');

    $deadline = microtime(true) + 5.0;
    $ready = false;
    do {
        $status = proc_get_status($process);
        siteEntrypointAssert(($status['running'] ?? false) === true, 'server-exited-before-ready');
        $connection = @fsockopen('127.0.0.1', $port, $connectError, $connectMessage, 0.2);
        if (is_resource($connection)) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(50000);
    } while (microtime(true) < $deadline);
    siteEntrypointAssert($ready, 'server-readiness-timeout');

    $path = '/ext/CHIM-MindPoisoning/index.php';
    $filters = [
        'tab' => 'diagnostics',
        'outcome' => 'rejected',
        'level' => 'warning',
        'q' => 'synthetic',
        'download' => '1',
    ];
    $initialUrl = 'http://127.0.0.1:' . $port . $path . '?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
    $handoff = siteEntrypointResponse($initialUrl);
    siteEntrypointAssert(($handoff['status'] ?? null) === 302, 'default-local-handoff-status');
    $location = siteEntrypointHeader($handoff, 'location');
    siteEntrypointAssert(is_string($location), 'handoff-location-missing');
    $parts = parse_url($location);
    siteEntrypointAssert(
        is_array($parts)
        && ($parts['scheme'] ?? null) === 'http'
        && ($parts['host'] ?? null) === 'localhost'
        && ($parts['port'] ?? null) === $port
        && ($parts['path'] ?? null) === $path,
        'handoff-authority-or-path'
    );
    $redirectFilters = [];
    parse_str((string)($parts['query'] ?? ''), $redirectFilters);
    siteEntrypointAssert($redirectFilters === $filters, 'handoff-filter-preservation');
    siteEntrypointAssert(siteEntrypointHeader($handoff, 'content-security-policy') !== null, 'handoff-csp-missing');

    $followedUrl = 'http://127.0.0.1:' . $port . $path . '?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
    $forwardedHeaders = [
        'Forwarded' => 'for=127.0.0.1',
        'X-Forwarded-For' => '127.0.0.1',
        'X-Real-IP' => '127.0.0.1',
    ];
    $forwardedLoopback = siteEntrypointResponse($followedUrl, 'GET', $forwardedHeaders);
    siteEntrypointAssert(
        ($forwardedLoopback['status'] ?? null) === 403
        && siteEntrypointHeader($forwardedLoopback, 'location') === null
        && !str_contains($forwardedLoopback['body'], '<html'),
        'forwarded-loopback-not-denied'
    );

    $explicitFalse = siteEntrypointResponse(
        'http://127.0.0.1:' . $port . $path . '?local=0&tab=diagnostics',
        'GET'
    );
    siteEntrypointAssert(($explicitFalse['status'] ?? null) === 403, 'explicit-local-zero-not-denied');
    siteEntrypointAssert(siteEntrypointHeader($explicitFalse, 'location') === null, 'explicit-local-zero-redirect');

    $post = siteEntrypointResponse(
        'http://127.0.0.1:' . $port . $path . '?tab=diagnostics',
        'POST',
        ['Content-Type' => 'application/x-www-form-urlencoded'],
        'synthetic=1'
    );
    siteEntrypointAssert(($post['status'] ?? null) === 405, 'post-method-status');
    siteEntrypointAssert(siteEntrypointHeader($post, 'allow') === 'GET', 'post-method-allow');
    siteEntrypointAssert(siteEntrypointHeader($post, 'content-security-policy') !== null, 'post-csp-missing');

    echo "site-entrypoint-check: passed\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'site-entrypoint-check: failed (' . $error->getMessage() . ")\n");
    $exitCode = 1;
} finally {
    if (is_resource($process)) {
        $status = proc_get_status($process);
        if (($status['running'] ?? false) === true) {
            proc_terminate($process, 15);
            $deadline = microtime(true) + 2.0;
            do {
                $status = proc_get_status($process);
                if (($status['running'] ?? false) !== true) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            if (($status['running'] ?? false) === true) {
                proc_terminate($process, 9);
            }
        }
        proc_close($process);
    }

    foreach (['index.php', 'dashboard.php'] as $name) {
        $file = $pluginRoot . DIRECTORY_SEPARATOR . $name;
        if (is_file($file) && !is_link($file)) {
            unlink($file);
        }
    }
    foreach ([$pluginRoot, $appRoot] as $directory) {
        if (is_dir($directory) && !is_link($directory)) {
            rmdir($directory);
        }
    }
    foreach ([$routerPath, $serverOutput, $serverError] as $file) {
        if (is_file($file) && !is_link($file)) {
            unlink($file);
        }
    }
    if (is_dir($sandboxRoot) && !is_link($sandboxRoot)) {
        rmdir($sandboxRoot);
    }
}

if (isset($exitCode)) {
    exit($exitCode);
}
