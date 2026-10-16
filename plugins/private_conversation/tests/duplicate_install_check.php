<?php
declare(strict_types=1);

const PCV_DUPLICATE_HOOKS = [
    'preprocessing.php', 'prerequest.php', 'json_response_custom.php', 'context_pre.php',
    'context.php', 'prepostrequest.php', 'postrequest.php',
];

function duplicateCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function duplicateRemoveTree(string $path): void
{
    $realPath = realpath($path);
    $tempRoot = realpath(sys_get_temp_dir());
    if ($realPath === false || $tempRoot === false
        || !str_starts_with($realPath, $tempRoot . DIRECTORY_SEPARATOR) || is_link($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isLink() || !$item->isDir()) {
            @unlink($item->getPathname());
        } else {
            @rmdir($item->getPathname());
        }
    }
    @rmdir($realPath);
}

function duplicateDefaultLogRoot(string $extension): string
{
    $path = realpath($extension);
    $tempRoot = realpath(sys_get_temp_dir());
    $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
    duplicateCheck(is_string($path) && is_string($tempRoot) && is_int($uid),
        'Could not resolve the default PCV store path for an extension fixture.');
    return $tempRoot . DIRECTORY_SEPARATOR . 'private-conversation-' . $uid . '-'
        . substr(hash('sha256', $path), 0, 16);
}

function duplicateCopyPayload(string $source, string $target): void
{
    if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) {
        throw new RuntimeException('Could not create a duplicate-install extension root.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $destination = $target . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
                throw new RuntimeException('Could not copy duplicate-install payload directory: ' . $relative);
            }
        } elseif ($item->isFile() && !copy($item->getPathname(), $destination)) {
            throw new RuntimeException('Could not copy duplicate-install payload file: ' . $relative);
        }
    }
}

function duplicateCreateExtension(string $root, bool $legacy, string $sourceDirectory, string $legacyFixture): void
{
    duplicateCopyPayload($legacy ? $legacyFixture : $sourceDirectory, $root);
}

function duplicateSetPluginManagerManifest(string $root, string $identityFixture): void
{
    $path = $root . DIRECTORY_SEPARATOR . 'manifest.json';
    $manifest = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    $generatedIdentity = json_decode((string)file_get_contents($identityFixture), true, 16, JSON_THROW_ON_ERROR);
    $originalVersion = $manifest['version'] ?? null;
    if (!is_string($originalVersion) || preg_match('/\A\d+\.\d+\.\d+\z/', $originalVersion) !== 1) {
        throw new RuntimeException('The source manifest does not contain the numeric version expected by Plugin Manager.');
    }
    $generatedIdentity['name'] = basename($root);
    $generatedIdentity['version'] = 'private_conversation-v' . $originalVersion;
    $manifest = array_replace($manifest, $generatedIdentity);
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($path, $json) !== strlen($json)) {
        throw new RuntimeException('Could not write the generated Plugin Manager manifest fixture.');
    }
}

function duplicateRunProcess(array $command): array
{
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not launch a duplicate-install child process.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['exit_code' => proc_close($process), 'stdout' => (string)$stdout, 'stderr' => (string)$stderr];
}

function duplicateHttpRequest(int $port, string $path, string $method = 'GET', ?string $body = null): array
{
    $headers = ['Connection: close'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $options = ['method' => $method, 'header' => implode("\r\n", $headers), 'ignore_errors' => true, 'timeout' => 2];
    if ($body !== null) {
        $options['content'] = $body;
    }
    $response = @file_get_contents('http://127.0.0.1:' . $port . $path, false,
        stream_context_create(['http' => $options]));
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('/\AHTTP\/\S+ ([0-9]{3})/', $header, $match) === 1) {
            $status = (int)$match[1];
            break;
        }
    }
    return ['status' => $status, 'body' => is_string($response) ? $response : '', 'headers' => $headers];
}

function duplicateAssertInactiveHttp(string $extension, string $owner): void
{
    $ownerLogRoot = duplicateDefaultLogRoot($owner);
    $inactiveLogRoot = duplicateDefaultLogRoot($extension);
    duplicateCheck(!file_exists($ownerLogRoot) && !file_exists($inactiveLogRoot),
        'The unique HTTP fixture store paths already exist.');

    $process = null;
    try {
        $ownerStateDirectory = $ownerLogRoot . DIRECTORY_SEPARATOR . 'state';
        if (!mkdir($ownerStateDirectory, 0700, true)) {
            throw new RuntimeException('Could not seed the legacy owner state directory for HTTP refusal checks.');
        }
        $markerPath = $ownerStateDirectory . DIRECTORY_SEPARATOR . 'duplicate-owner-sentinel.txt';
        $marker = "legacy-owner-state-must-remain-untouched\n";
        if (file_put_contents($markerPath, $marker) !== strlen($marker)) {
            throw new RuntimeException('Could not seed the legacy owner state marker.');
        }
        @chmod($markerPath, 0600);
        $originalStateEntries = scandir($ownerStateDirectory);

        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (!is_resource($socket)) {
            throw new RuntimeException('Could not reserve a loopback HTTP test port.');
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int)substr((string)$address, strrpos((string)$address, ':') + 1);
        $environment = getenv();
        if (!is_array($environment)) {
            $environment = [];
        }
        unset($environment['PCV_LOG_DIR']);
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $extension], [
            0 => ['file', $nullDevice, 'r'], 1 => ['file', $nullDevice, 'a'], 2 => ['file', $nullDevice, 'a'],
        ], $pipes, $extension, $environment, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the isolated inactive-copy HTTP server.');
        }

        $refresh = null;
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $refresh = duplicateHttpRequest($port, '/index.php?refresh=1');
            if ($refresh['status'] !== 0) {
                break;
            }
            usleep(50000);
        }
        duplicateCheck($refresh['status'] === 409 && str_contains($refresh['body'], 'ext/' . basename($owner)),
            'The real inactive UI refresh route did not return HTTP 409 naming the active owner.');
        $logs = duplicateHttpRequest($port, '/index.php?view=logs');
        duplicateCheck($logs['status'] === 409 && str_contains($logs['body'], 'ext/' . basename($owner)),
            'The real inactive Logs route did not return HTTP 409 naming the active owner.');
        $post = duplicateHttpRequest($port, '/index.php', 'POST', http_build_query([
            'csrf' => str_repeat('a', 64), 'action' => 'end',
        ]));
        duplicateCheck($post['status'] === 409 && str_contains($post['body'], 'ext/' . basename($owner)),
            'The real inactive UI POST did not return HTTP 409 naming the active owner.');
        duplicateCheck(file_get_contents($markerPath) === $marker && scandir($ownerStateDirectory) === $originalStateEntries,
            'The inactive HTTP routes changed the legacy owner state directory.');
        duplicateCheck(!file_exists($inactiveLogRoot), 'The inactive HTTP routes created a separate default log/state root.');
        echo "PASS: inactive refresh, Logs, and POST routes refuse over loopback HTTP without touching owner state\n";
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
        duplicateRemoveTree($ownerLogRoot);
        duplicateRemoveTree($inactiveLogRoot);
    }
}

/** Mirrors the CHIM recursive loader's include scope and local variable names. */
function requireFilesRecursively(string $directory, string $entry): void
{
    $dir = $directory;
    $name = basename($dir);
    $files = [$entry];
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        include $path;
    }
    duplicateCheck($dir === $directory && $name === basename($directory) && $files === [$entry]
        && $file === $entry && $path === $directory . DIRECTORY_SEPARATOR . $entry,
        'The installation guard clobbered recursive loader locals.');
}

function duplicateAssertOwnerState(string $owner, ?string $nonOwner): void
{
    $ownerPath = realpath($owner);
    $tempRoot = realpath(sys_get_temp_dir());
    $uid = pcv_log_effective_uid();
    duplicateCheck(is_string($ownerPath) && $tempRoot !== false && is_int($uid),
        'Could not resolve the elected owner state-store identity.');

    $ownerLogRoot = $tempRoot . DIRECTORY_SEPARATOR . 'private-conversation-' . $uid . '-'
        . substr(hash('sha256', $ownerPath), 0, 16);
    $nonOwnerLogRoot = null;
    if ($nonOwner !== null) {
        $nonOwnerPath = realpath($nonOwner);
        duplicateCheck(is_string($nonOwnerPath), 'Could not resolve the non-owner extension path.');
        $nonOwnerLogRoot = $tempRoot . DIRECTORY_SEPARATOR . 'private-conversation-' . $uid . '-'
            . substr(hash('sha256', $nonOwnerPath), 0, 16);
    }

    try {
        $logSource = (new ReflectionFunction('pcv_log_default_directory'))->getFileName();
        duplicateCheck(is_string($logSource) && realpath(dirname($logSource)) === $ownerPath,
            'The default logger was not loaded from the elected owner.');
        duplicateCheck(pcv_log_default_directory(false) === $ownerLogRoot,
            'The default log root is not keyed to the elected owner path.');
        $stateDirectory = pcv_state_directory(null);
        duplicateCheck($stateDirectory === $ownerLogRoot . DIRECTORY_SEPARATOR . 'state',
            'The default state directory is not under the elected owner log root.');
        duplicateCheck($nonOwnerLogRoot === null || !file_exists($nonOwnerLogRoot),
            'The inactive copy already created a separate default store.');

        $key = str_repeat('a', 64);
        $knownNpcs = ['1001' => 'Aela', '1002' => 'Lydia'];
        $armed = pcv_stage($key, [
            'enabled' => true,
            'scene_mode' => 'pair',
            'actor_a' => '1001',
            'actor_b' => '1002',
            'exclude_player' => true,
            'bystander_mode' => 'exclude',
        ], $knownNpcs);
        duplicateCheck(($armed['status'] ?? null) === 'pending' && ($armed['pending'] ?? null) === true,
            "The elected owner's real ARM call did not stage state.");
        duplicateCheck(is_file($stateDirectory . DIRECTORY_SEPARATOR . 'state.json'),
            "The elected owner's ARM call did not write its default state store.");

        $pending = pcv_read($key);
        duplicateCheck(($pending['status'] ?? null) === 'pending'
            && ($pending['pending_scope']['actor_a'] ?? null) === '1001',
            'The elected owner could not read back its staged state.');
        $ended = pcv_stage($key, ['enabled' => false], $knownNpcs);
        duplicateCheck(($ended['status'] ?? null) === 'off' && ($ended['pending'] ?? null) === false,
            "The elected owner's real END call did not clear state.");
        duplicateCheck((pcv_read($key)['status'] ?? null) === 'off',
            'The elected owner could not read back its END result.');
        duplicateCheck($nonOwnerLogRoot === null || !file_exists($nonOwnerLogRoot),
            'The inactive copy created a separate default store during the owner ARM/END calls.');
    } finally {
        duplicateRemoveTree($ownerLogRoot);
    }
}

function duplicateHookChild(array $arguments): never
{
    $first = $arguments[2];
    $second = $arguments[3];
    $expectedOwner = $arguments[4];
    $repeatNonOwner = $arguments[5];
    putenv('PCV_LOG_DIR');
    $GLOBALS['gameRequest'] = null;
    $GLOBALS['HOOKS'] = [];
    unset($GLOBALS['PCV_REQUEST_SCOPE']);
    $extensions = [$first];
    if ($second !== '-') {
        $extensions[] = $second;
    }
    foreach (PCV_DUPLICATE_HOOKS as $entry) {
        foreach ($extensions as $extension) {
            requireFilesRecursively($extension, $entry);
        }
    }
    if ($repeatNonOwner !== '-') {
        foreach (PCV_DUPLICATE_HOOKS as $entry) {
            requireFilesRecursively($repeatNonOwner, $entry);
        }
    }
    duplicateCheck(function_exists('pcv_current_playthrough_key') && function_exists('pcvPrepareScopedInput'),
        'The selected owner did not load its real state and scope modules.');
    $statePath = (new ReflectionFunction('pcv_current_playthrough_key'))->getFileName();
    $scopePath = (new ReflectionFunction('pcvPrepareScopedInput'))->getFileName();
    duplicateCheck(is_string($statePath) && is_string($scopePath)
        && realpath(dirname($statePath)) === realpath($expectedOwner)
        && realpath(dirname($scopePath)) === realpath($expectedOwner),
        'A non-owner hook loaded the real state or scope module.');
    duplicateCheck(($GLOBALS['HOOKS']['JSON_TEMPLATE'] ?? []) === ['pcvCustomizeJsonResponseTemplate'],
        'JSON_TEMPLATE was not registered exactly once by the selected owner.');
    duplicateAssertOwnerState($expectedOwner, $repeatNonOwner === '-' ? null : $repeatNonOwner);
    echo "PASS: duplicate hook owner and side effects\n";
    exit(0);
}

function duplicateUiChild(array $arguments): never
{
    $extension = $arguments[2];
    $expectedReason = $arguments[3];
    putenv('PCV_LOG_DIR');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf' => str_repeat('a', 64), 'action' => 'end'];
    ob_start();
    require $extension . DIRECTORY_SEPARATOR . 'index.php';
    $body = (string)ob_get_clean();
    duplicateCheck(http_response_code() === 409, 'The inactive UI did not reject its POST with HTTP 409.');
    duplicateCheck(str_contains(strtolower($body), $expectedReason), 'The inactive UI did not explain why it refused ARM/END.');
    duplicateCheck(!function_exists('pcv_log_default_directory') && !function_exists('pcv_stage')
        && !function_exists('pcv_current_playthrough_key'),
        'The inactive UI loaded its logger/state module or attempted a scene mutation.');
    echo "PASS: inactive UI POST refused before loading state\n";
    exit(0);
}

if (($argv[1] ?? null) === '--hooks') {
    duplicateHookChild($argv);
}
if (($argv[1] ?? null) === '--ui') {
    duplicateUiChild($argv);
}

$repoRoot = dirname(__DIR__);
$sourceDirectory = $repoRoot . DIRECTORY_SEPARATOR . 'server';
$legacyFixture = $repoRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures'
    . DIRECTORY_SEPARATOR . 'duplicate-install' . DIRECTORY_SEPARATOR . 'legacy-0.1.19' . DIRECTORY_SEPARATOR . 'server';
$generatedIdentityFixture = $repoRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures'
    . DIRECTORY_SEPARATOR . 'duplicate-install' . DIRECTORY_SEPARATOR . 'plugin-manager-generated-identity.json';
// Identity fields mirror chimPluginGeneratedManifest() in the retained CHIM lib/plugin_discovery.php (1029-1075).
duplicateCheck(is_file($generatedIdentityFixture), 'The generated Plugin Manager identity fixture is missing.');
foreach (array_merge(PCV_DUPLICATE_HOOKS, ['manifest.json']) as $file) {
    duplicateCheck(is_file($legacyFixture . DIRECTORY_SEPARATOR . $file), 'The exact .19 Git-export fixture is incomplete: ' . $file);
}
foreach (['index.php', 'diagnostics.php', 'log.php', 'scope.php', 'state.php'] as $file) {
    duplicateCheck(is_file($legacyFixture . DIRECTORY_SEPARATOR . $file), 'The exact .19 payload fixture is incomplete: ' . $file);
}

$cases = [
    ['new-new', false, false, 'private_conversation', 'CHIM-PrivateConversation', [
        ['CHIM-PrivateConversation', 'private_conversation'],
        ['private_conversation', 'CHIM-PrivateConversation'],
    ]],
    ['legacy-private', true, false, 'private_conversation', 'CHIM-PrivateConversation', [
        ['CHIM-PrivateConversation', 'private_conversation'],
        ['private_conversation', 'CHIM-PrivateConversation'],
    ]],
    ['legacy-chim', false, true, 'CHIM-PrivateConversation', 'private_conversation', [
        ['CHIM-PrivateConversation', 'private_conversation'],
        ['private_conversation', 'CHIM-PrivateConversation'],
    ]],
];

try {
    foreach ($cases as [$name, $privateIsLegacy, $chimIsLegacy, $expectedOwnerName, $nonOwnerName, $orders]) {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-duplicate-' . $name . '-' . bin2hex(random_bytes(6));
        $ext = $root . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'HerikaServer'
            . DIRECTORY_SEPARATOR . 'ext';
        $private = $ext . DIRECTORY_SEPARATOR . 'private_conversation';
        $chim = $ext . DIRECTORY_SEPARATOR . 'CHIM-PrivateConversation';
        mkdir($ext, 0700, true);
        duplicateCreateExtension($private, $privateIsLegacy, $sourceDirectory, $legacyFixture);
        duplicateCreateExtension($chim, $chimIsLegacy, $sourceDirectory, $legacyFixture);
        if ($name === 'new-new') {
            duplicateSetPluginManagerManifest($chim, $generatedIdentityFixture);
        } elseif ($name === 'legacy-private') {
            duplicateSetPluginManagerManifest($private, $generatedIdentityFixture);
            duplicateSetPluginManagerManifest($chim, $generatedIdentityFixture);
        } elseif ($name === 'legacy-chim') {
            duplicateSetPluginManagerManifest($private, $generatedIdentityFixture);
            duplicateSetPluginManagerManifest($chim, $generatedIdentityFixture);
        }
        $expectedOwner = $expectedOwnerName === 'private_conversation' ? $private : $chim;
        $nonOwner = $nonOwnerName === 'private_conversation' ? $private : $chim;
        foreach ($orders as [$firstName, $secondName]) {
            $first = $firstName === 'private_conversation' ? $private : $chim;
            $second = $secondName === 'private_conversation' ? $private : $chim;
            $result = duplicateRunProcess([PHP_BINARY, __FILE__, '--hooks', $first, $second, $expectedOwner, $nonOwner]);
            duplicateCheck($result['exit_code'] === 0 && $result['stdout'] === "PASS: duplicate hook owner and side effects\n"
                && $result['stderr'] === '', $name . ' hook fixture failed: ' . trim($result['stderr'] . ' ' . $result['stdout']));
        }
        if ($privateIsLegacy || $chimIsLegacy) {
            $result = duplicateRunProcess([PHP_BINARY, __FILE__, '--ui', $nonOwner, 'legacy']);
            duplicateCheck($result['exit_code'] === 0 && $result['stdout'] === "PASS: inactive UI POST refused before loading state\n"
                && $result['stderr'] === '', $name . ' UI refusal fixture failed: ' . trim($result['stderr'] . ' ' . $result['stdout']));
            $diagnostics = duplicateRunProcess([PHP_BINARY, $nonOwner . DIRECTORY_SEPARATOR . 'diagnostics.php']);
            duplicateCheck($diagnostics['exit_code'] === 2 && $diagnostics['stdout'] === ''
                && str_contains(strtolower($diagnostics['stderr']), 'legacy')
                && str_contains($diagnostics['stderr'], 'ext/' . basename($expectedOwner)),
                $name . ' non-owner diagnostics did not refuse before loading the log reader.');
            duplicateAssertInactiveHttp($nonOwner, $expectedOwner);
        }
        duplicateRemoveTree($root);
    }

    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-duplicate-generated-single-' . bin2hex(random_bytes(6));
    $ext = $root . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'HerikaServer'
        . DIRECTORY_SEPARATOR . 'ext';
    $generatedOnly = $ext . DIRECTORY_SEPARATOR . 'CHIM-PrivateConversation';
    mkdir($ext, 0700, true);
    duplicateCreateExtension($generatedOnly, false, $sourceDirectory, $legacyFixture);
    duplicateSetPluginManagerManifest($generatedOnly, $generatedIdentityFixture);
    $result = duplicateRunProcess([PHP_BINARY, __FILE__, '--hooks', $generatedOnly, '-', $generatedOnly, '-']);
    duplicateCheck($result['exit_code'] === 0 && $result['stdout'] === "PASS: duplicate hook owner and side effects\n"
        && $result['stderr'] === '', 'A generated-manifest single install was not recognized as its own owner: '
            . trim($result['stderr'] . ' ' . $result['stdout']));
    echo "PASS: generated Plugin Manager manifest is recognized as a single owner\n";
    duplicateRemoveTree($root);

    foreach (['missing', 'malformed'] as $metadataFailure) {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-duplicate-metadata-' . $metadataFailure . '-' . bin2hex(random_bytes(6));
        $ext = $root . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'HerikaServer'
            . DIRECTORY_SEPARATOR . 'ext';
        $private = $ext . DIRECTORY_SEPARATOR . 'private_conversation';
        $chim = $ext . DIRECTORY_SEPARATOR . 'CHIM-PrivateConversation';
        mkdir($chim, 0700, true);
        duplicateCreateExtension($private, false, $sourceDirectory, $legacyFixture);
        if ($metadataFailure === 'malformed') {
            file_put_contents($chim . DIRECTORY_SEPARATOR . 'manifest.json', '{invalid json');
        }
        $result = duplicateRunProcess([PHP_BINARY, __FILE__, '--ui', $private, 'metadata']);
        duplicateCheck($result['exit_code'] === 0 && $result['stdout'] === "PASS: inactive UI POST refused before loading state\n"
            && $result['stderr'] === '', $metadataFailure . ' sibling manifest did not fail closed.');
        duplicateRemoveTree($root);
    }

    echo "PASS: new/new owner election is stable in both hook load orders\n";
    echo "PASS: exact .19/new mixed installs defer to the legacy owner in both path placements\n";
    echo "PASS: ambiguous known PCV paths refuse before UI state access\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
