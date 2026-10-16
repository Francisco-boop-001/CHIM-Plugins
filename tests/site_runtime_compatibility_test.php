<?php
declare(strict_types=1);

function siteRuntimeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function siteRuntimeLoadHook(string $path): void
{
    require_once $path;
}

function siteRuntimeRemoveTree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        if (!unlink($path)) {
            throw new RuntimeException('Could not remove isolated test file.');
        }
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $name) {
        if ($name !== '.' && $name !== '..') {
            siteRuntimeRemoveTree($path . DIRECTORY_SEPARATOR . $name);
        }
    }
    if (!rmdir($path)) {
        throw new RuntimeException('Could not remove isolated test directory.');
    }
}

function siteRuntimeChild(string $pluginRoot): void
{
    siteRuntimeLoadHook($pluginRoot . DIRECTORY_SEPARATOR . 'globals.php');
    siteRuntimeAssert(
        defined('ChimMindPoisoning\\MIND_POISONING_REFLECTION_API_VERSION')
            && constant('ChimMindPoisoning\\MIND_POISONING_REFLECTION_API_VERSION') === 1
            && function_exists('ChimMindPoisoning\\mindPoisoningEvaluateReflection')
            && function_exists('ChimMindPoisoning\\reflectionSourceParts')
            && class_exists('ChimMindPoisoning\\PostgresStoreDb', false),
        'The relocated globals hook did not expose the expected reflection API.'
    );

    require_once __DIR__ . '/../plugins/private_conversation/server/reflection.php';
    siteRuntimeAssert(
        pcv_reflection_load_mind_poisoning(),
        'The PCV compatibility loader did not accept the API preloaded from the repository-name folder.'
    );
    siteRuntimeAssert(!class_exists('sql', false) && !array_key_exists('db', $GLOBALS), 'Loading the API must not construct CHIM database state.');
    siteRuntimeAssert(!class_exists('LLMConnector', false), 'Loading the API must not load or construct a provider connector.');

    require_once $pluginRoot . DIRECTORY_SEPARATOR . 'dashboard_data.php';
    $records = [];
    $log = new \ChimMindPoisoning\RequestLog(static function (string $json, string $level) use (&$records): void {
        $records[] = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    }, false);
    $log->event('site_compatibility_check', 'info');
    siteRuntimeAssert(($records[0]['version'] ?? null) === '0.1.21', 'The manager release tag was not normalized for MP logs.');
    $line = '[Mind Poisoning][INFO] ' . json_encode($records[0], JSON_THROW_ON_ERROR);
    $parsed = \ChimMindPoisoning\dashboardParseLogLine($line);
    siteRuntimeAssert(is_array($parsed) && ($parsed['version'] ?? null) === '0.1.21', 'The MP dashboard dropped a log record from the installed site version.');

    $manifestPath = $pluginRoot . DIRECTORY_SEPARATOR . 'manifest.json';
    file_put_contents($manifestPath, json_encode(['version' => '0.1.21'], JSON_THROW_ON_ERROR));
    $numeric = [];
    (new \ChimMindPoisoning\RequestLog(static function (string $json) use (&$numeric): void {
        $numeric[] = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    }, false))->event('numeric_version_check', 'info');
    siteRuntimeAssert(($numeric[0]['version'] ?? null) === '0.1.21', 'An ordinary semantic version changed unexpectedly.');

    file_put_contents($manifestPath, json_encode(['version' => 'mind_poisoning-v0.1.21-rc1'], JSON_THROW_ON_ERROR));
    $invalid = [];
    (new \ChimMindPoisoning\RequestLog(static function (string $json) use (&$invalid): void {
        $invalid[] = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    }, false))->event('invalid_version_check', 'info');
    siteRuntimeAssert(($invalid[0]['version'] ?? null) === 'unknown', 'A non-exact release tag prefix was accepted.');
}

if (($argv[1] ?? null) === '--child') {
    try {
        siteRuntimeChild((string)($argv[2] ?? ''));
        echo "PASS: relocated API, PCV loaded-API path, and release-version log parsing\n";
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
    exit(0);
}

$serverRoot = __DIR__ . '/../server';
$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mp-site-compat-' . bin2hex(random_bytes(8));
siteRuntimeAssert(mkdir($temporaryRoot, 0700), 'Could not create isolated site-compatibility fixture.');
$pluginRoot = $temporaryRoot . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'CHIM-MindPoisoning';
siteRuntimeAssert(mkdir($pluginRoot, 0700, true), 'Could not create relocated plugin directory.');

try {
    foreach (glob($serverRoot . DIRECTORY_SEPARATOR . '*.php') ?: [] as $source) {
        siteRuntimeAssert(copy($source, $pluginRoot . DIRECTORY_SEPARATOR . basename($source)), 'Could not copy isolated plugin source.');
    }
    $tag = 'mind_poisoning-v0.1.21';
    $generatedManifest = [
        'name' => 'CHIM-MindPoisoning',
        'display_name' => 'Mind Poisoning',
        'version' => $tag,
        'description' => 'Generated metadata for the site installation route.',
        'git_repo' => 'Francisco-boop-001/CHIM-MindPoisoning',
        'repo_id' => 1,
        'source' => 'github-release',
        'release_tag' => $tag,
        'release_asset' => 'chim-plugin.tar.gz',
        'package_url' => 'https://github.com/Francisco-boop-001/CHIM-MindPoisoning/releases/download/' . rawurlencode($tag) . '/chim-plugin.tar.gz',
        'channel' => 'main',
        'channel_label' => 'Main',
        'generated_by' => 'CHIM Plugin Manager',
        'installed_at' => gmdate('c'),
    ];
    siteRuntimeAssert(
        file_put_contents($pluginRoot . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($generatedManifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n") !== false,
        'Could not write the generated site manifest fixture.'
    );

    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open([PHP_BINARY, '-n', __FILE__, '--child', $pluginRoot], $spec, $pipes, null, null, ['bypass_shell' => true]);
    siteRuntimeAssert(is_resource($process), 'Could not start the isolated child process.');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException('Isolated compatibility child failed: ' . trim((string)$stderr . ' ' . (string)$stdout));
    }
    echo $stdout;
} finally {
    siteRuntimeRemoveTree($temporaryRoot);
}
