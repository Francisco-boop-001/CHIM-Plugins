<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/scope.php';

function terminalCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function terminalRemoveTestDirectory(string $path): void
{
    $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pcv-postrequest-';
    if (!str_starts_with($path, $prefix) || is_link($path) || !is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $itemPath = $item->getPathname();
        if ($item->isLink() || !$item->isDir()) {
            @unlink($itemPath);
        } else {
            @rmdir($itemPath);
        }
    }
    @rmdir($path);
}

function checkRegistrationException(string $root): void
{
    $serverDirectory = $root . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'HerikaServer'
        . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'private_conversation' . DIRECTORY_SEPARATOR . 'server';
    $logDirectory = $root . DIRECTORY_SEPARATOR . 'logs';
    if (!mkdir($serverDirectory, 0700, true) || !mkdir($logDirectory, 0700)) {
        throw new RuntimeException('Could not create the isolated registration-error fixture.');
    }
    foreach (['log.php', 'scope.php', 'postrequest.php'] as $file) {
        if (!copy(dirname(__DIR__) . '/server/' . $file, $serverDirectory . DIRECTORY_SEPARATOR . $file)) {
            throw new RuntimeException('Could not copy an isolated registration-error fixture file.');
        }
    }
    if (file_put_contents($serverDirectory . DIRECTORY_SEPARATOR . 'reflection.php', <<<'PHP'
<?php
function pcvReflectionRegisterLastOutput(array $requestScope): void
{
    throw new RuntimeException('fixture private exception details', 7);
}
PHP) === false) {
        throw new RuntimeException('Could not prepare the isolated reflection failure.');
    }

    $child = $root . DIRECTORY_SEPARATOR . 'registration_exception.php';
    $childSource = "<?php\n"
        . "define('PCV_LOG_TESTING', true);\n"
        . 'require_once ' . var_export($serverDirectory . DIRECTORY_SEPARATOR . 'log.php', true) . ";\n"
        . 'require_once ' . var_export($serverDirectory . DIRECTORY_SEPARATOR . 'scope.php', true) . ";\n"
        . '$logDirectory = ' . var_export($logDirectory, true) . ";\n"
        . "if (!pcv_log_set_test_directory(\$logDirectory)) { throw new RuntimeException('Could not set isolated logs.'); }\n"
        . <<<'PHP'
$scope = [
    'status' => 'active',
    'config_id' => '123e4567-e89b-42d3-a456-426614174000',
    'actor_a_id' => '11',
    'actor_b_id' => null,
    'origin_request_type' => 'inputtext',
    'origin_mode' => 'STANDARD',
    'route' => 'solo_reflection',
    'origin_dialogue' => 'Player: describe the old bridge.',
    'scope' => ['scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null, 'exclude_player' => true],
];
$GLOBALS['PCV_REQUEST_SCOPE'] = $scope;
$GLOBALS['gameRequest'] = ['inputtext', '', '', 'private fixture dialogue'];
$GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
$GLOBALS['HERIKA_NAME'] = 'Aela';
pcvRoutingLogSetState($scope);
pcvRoutingLogStart('inputtext');
include __DIR__ . '/webroot/HerikaServer/ext/private_conversation/server/postrequest.php';
pcv_log_shutdown_terminal();
$path = pcv_log_path();
$text = is_string($path) && is_file($path) ? (string)file_get_contents($path) : '';
$entries = array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR),
    array_filter(explode("\n", $text), static fn(string $line): bool => $line !== ''));
$errors = array_values(array_filter($entries, static fn(array $entry): bool =>
    ($entry['event'] ?? null) === 'reflection.registration_error'
    && ($entry['reason'] ?? null) === 'internal_error'
    && ($entry['context']['exception_class'] ?? null) === 'RuntimeException'
    && ($entry['context']['exception_code'] ?? null) === 7
    && ($entry['context']['source_file'] ?? null) === 'reflection.php'
    && is_int($entry['context']['source_line'] ?? null)));
if (count($errors) !== 1 || str_contains($text, 'fixture private exception details')
    || str_contains($text, 'private fixture dialogue')) {
    fwrite(STDERR, "FAIL: registration exception logging was missing or exposed private text.\n");
    exit(1);
}
echo "PASS: registration exceptions log bounded class/code/file/line metadata only\n";
PHP;
    if (file_put_contents($child, $childSource) === false) {
        throw new RuntimeException('Could not write the isolated registration-error runner.');
    }
    $process = proc_open([PHP_BINARY, $child], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not run the isolated registration-error fixture.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    terminalCheck(proc_close($process) === 0, 'Registration exception fixture failed: ' . $stderr);
    terminalCheck($stdout === "PASS: registration exceptions log bounded class/code/file/line metadata only\n",
        'Registration exception fixture did not produce its expected result.');
}

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-postrequest-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700) || !pcv_log_set_test_directory($directory)) {
    throw new RuntimeException('Could not create isolated logger fixture.');
}

try {
    $scope = [
        'status' => 'active',
        'config_id' => '123e4567-e89b-42d3-a456-426614174000',
        'actor_a_id' => '11',
        'actor_b_id' => '22',
        'origin_request_type' => 'inputtext',
        'origin_mode' => 'STANDARD',
        'route' => 'player_speech',
        'scope' => [
            'scene_mode' => 'pair',
            'actor_a' => 'Aela',
            'actor_b' => 'Bryn',
            'exclude_player' => false,
        ],
    ];
    $GLOBALS['PCV_REQUEST_SCOPE'] = $scope;
    $GLOBALS['gameRequest'] = ['inputtext', '', '', 'private fixture dialogue'];
    $GLOBALS['DEBUG_DATA'] = ['OUTPUT_LOG' => ''];
    unset($GLOBALS['SCRIPTLINE_UTTERANCE_ID']);
    pcvRoutingLogSetState($scope);
    pcvRoutingLogStart('inputtext');
    include dirname(__DIR__) . '/server/postrequest.php';

    $request =& pcv_log_request_context();
    terminalCheck(($request['terminal']['outcome'] ?? null) === 'postrequest_observed',
        'The terminal summary should say only that the postrequest hook ran, even with no output record.');
    terminalCheck(($request['terminal']['reason'] ?? null) === null
        && ($request['terminal']['context']['phase'] ?? null) === 'postrequest'
        && ($request['terminal']['context']['route'] ?? null) === 'player_speech',
        'The terminal summary should retain only its bounded hook-stage context.');
    pcv_log_shutdown_terminal();

    $logPath = pcv_log_path();
    $text = is_string($logPath) && is_file($logPath) ? (string)file_get_contents($logPath) : '';
    $entries = array_map(
        static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR),
        array_filter(explode("\n", $text), static fn(string $line): bool => $line !== '')
    );
    $finished = array_values(array_filter($entries, static fn(array $entry): bool =>
        ($entry['event'] ?? null) === 'routing.request_finished'));
    terminalCheck(count($finished) === 1
        && ($finished[0]['outcome'] ?? null) === 'postrequest_observed'
        && ($finished[0]['context']['phase'] ?? null) === 'postrequest',
        'One truthful postrequest observation should be emitted at shutdown.');
    terminalCheck(!str_contains($text, 'private fixture dialogue')
        && !str_contains($text, 'SCRIPTLINE_UTTERANCE_ID')
        && !array_key_exists('audio', $finished[0]['context'] ?? [])
        && !array_key_exists('utterance_id', $finished[0]['context'] ?? []),
        'A hook observation must not imply transcript or playback evidence.');

    checkRegistrationException($directory . DIRECTORY_SEPARATOR . 'exception_fixture');

    echo "PASS: empty-output postrequest is logged as hook observation only\n";
} finally {
    terminalRemoveTestDirectory($directory);
}
