<?php
declare(strict_types=1);

/**
 * B3 native helper. Only called by campaign.py inside its private disposable
 * namespace. It uses the exact copied core and installed extension tree.
 */

function mpInstallChildResult(string $status, string $code, array $details = []): never
{
    $payload = ['status' => $status, 'code' => $code, 'details' => $details];
    echo 'MP_INSTALL_CHILD_RESULT=' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($status === 'passed' ? 0 : 1);
}

function mpInstallRequiredEnv(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        mpInstallChildResult('failed', 'env-missing');
    }
    return $value;
}

function mpInstallWithin(string $path, string $root): bool
{
    $realPath = realpath($path);
    $realRoot = realpath($root);
    if ($realPath === false || $realRoot === false) {
        return false;
    }
    return $realPath === $realRoot
        || str_starts_with($realPath, rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
}

function mpInstallPrepare(string $phase, string $mode): array
{
    $root = mpInstallRequiredEnv('MP_INSTALL_RECOVERY_ROOT');
    $app = mpInstallRequiredEnv('MP_INSTALL_APP_ROOT');
    if (preg_match('~\A/tmp/mp-install-recovery-[a-f0-9]{32}\z~D', $root) !== 1
        || !mpInstallWithin($app, $root)
        || !in_array(basename($app), ['clean', 'upgrade'], true)
    ) {
        mpInstallChildResult('failed', 'path-boundary');
    }

    $app = realpath($app);
    if (!is_string($app)) {
        mpInstallChildResult('failed', 'app-root');
    }

    $logDir = $app . DIRECTORY_SEPARATOR . 'log';
    if (!is_dir($logDir) && !mkdir($logDir, 0700, true) && !is_dir($logDir)) {
        mpInstallChildResult('failed', 'log-root');
    }
    $errorLog = $root . DIRECTORY_SEPARATOR . 'evidence' . DIRECTORY_SEPARATOR . 'php-error.log';
    ini_set('error_log', $errorLog);
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    date_default_timezone_set('UTC');

    if (!defined('ENGINE_PATH')) {
        define('ENGINE_PATH', rtrim($app, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }
    if (!defined('DEFAULT_LOG')) {
        define('DEFAULT_LOG', $logDir . DIRECTORY_SEPARATOR . 'chim.log');
    }
    $GLOBALS['ENGINE_PATH'] = ENGINE_PATH;
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
    $_SERVER['REQUEST_METHOD'] = 'CLI';

    if ($phase === 'guard') {
        if (!in_array($mode, ['paused', 'core-off', 'enabled', 'interaction-off'], true)) {
            mpInstallChildResult('failed', 'guard-mode');
        }
        $stateDir = $app . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'chim_interaction';
        if (!is_dir($stateDir) && !mkdir($stateDir, 0700, true) && !is_dir($stateDir)) {
            mpInstallChildResult('failed', 'interaction-dir');
        }
        $statePath = $stateDir . DIRECTORY_SEPARATOR . 'state.json';
        $stateWritten = file_put_contents(
            $statePath,
            json_encode(['enabled' => $mode !== 'interaction-off', 'generation' => 1], JSON_THROW_ON_ERROR),
            LOCK_EX
        );
        $dataDir = $app . DIRECTORY_SEPARATOR . 'data';
        if (!is_dir($dataDir) && !mkdir($dataDir, 0700, true) && !is_dir($dataDir)) {
            mpInstallChildResult('failed', 'plugin-data-dir');
        }
        $pausePath = $dataDir . DIRECTORY_SEPARATOR . 'mind_poisoning.json';
        $pauseWritten = file_put_contents(
            $pausePath,
            json_encode(['enabled' => $mode !== 'paused'], JSON_THROW_ON_ERROR),
            LOCK_EX
        );
        if (!is_int($stateWritten) || !is_int($pauseWritten)) {
            mpInstallChildResult('failed', 'guard-config-write');
        }
    }

    $coreFiles = [
        $app . '/lib/logger.php',
        $app . '/lib/settings.php',
        $app . '/lib/chat_helper_functions.php',
        $app . '/lib/chim_interaction.php',
        $app . '/lib/playthrough_runtime.php',
        $app . '/lib/relationship_manager.php',
        $app . '/lib/postgresql.class.php',
    ];
    foreach ($coreFiles as $file) {
        if (!is_file($file)) {
            mpInstallChildResult('failed', 'core-file-missing');
        }
    }

    require_once $coreFiles[0];
    // The runner binds the legacy absolute install path to this disposable app.
    // Keep the real pinned error handler and route ordinary Logger output locally.
    if (method_exists('Logger', 'setCustomLog')) {
        Logger::setCustomLog($logDir . DIRECTORY_SEPARATOR . 'chim.log');
    }

    require_once $coreFiles[1];
    require_once $coreFiles[2];
    require_once $coreFiles[3];
    require_once $coreFiles[4];
    require_once $coreFiles[5];
    require_once $coreFiles[6];

    return [$root, $app];
}

function mpInstallRunGuard(string $mode, string $app): never
{
    if (!in_array($mode, ['paused', 'core-off', 'enabled', 'interaction-off'], true)) {
        mpInstallChildResult('failed', 'guard-mode');
    }
    $extension = $app . DIRECTORY_SEPARATOR . 'ext' . DIRECTORY_SEPARATOR . 'mind_poisoning';
    $pausePath = $app . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'mind_poisoning.json';
    if (!is_file($extension . DIRECTORY_SEPARATOR . 'prerequest.php')
        || !is_file($extension . DIRECTORY_SEPARATOR . 'store.php')
        || !is_file($extension . DIRECTORY_SEPARATOR . 'influence.php')
    ) {
        mpInstallChildResult('failed', 'extension-file-missing');
    }

    $statePath = $app . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'chim_interaction' . DIRECTORY_SEPARATOR . 'state.json';
    if (!is_file($statePath) || !is_file($pausePath)) {
        mpInstallChildResult('failed', 'guard-config-missing');
    }

    require_once $extension . DIRECTORY_SEPARATOR . 'prerequest.php';

    if (!function_exists('ChimMindPoisoning\\handleSpeechAck')
        || !function_exists('chimIsGlobalLlmConnectorEnabled')
        || !function_exists('ChimMindPoisoning\\speechAckInteractionStatus')
    ) {
        mpInstallChildResult('failed', 'guard-function-missing');
    }

    $GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] = $mode !== 'core-off';
    $GLOBALS['RELLLM_CONNECTOR'] = 1;

    $sql = new sql();
    $GLOBALS['db'] = $sql;
    $store = null;
    $calls = 0;
    $status = null;
    try {
        $store = new \ChimMindPoisoning\PostgresStoreDb();
        $request = [
            '_speech',
            0,
            10,
            json_encode([
                'speaker' => 'Aela',
                'listener' => 'Lydia',
                'speech' => 'I trust Jarl Balgruuf. The Dragonborn is brave.',
                'utterance_id' => 'utt_b3_native_enabled_01',
            ], JSON_THROW_ON_ERROR),
        ];
        $requestModel = static function (array $messages) use (&$calls): string {
            $calls++;
            return 'B3_SYNTHETIC_INVALID_MODEL_RESPONSE';
        };
        $requestLog = new \ChimMindPoisoning\RequestLog();
        $status = \ChimMindPoisoning\handleSpeechAck($request, $store, $requestModel, $requestLog);
    } catch (Throwable) {
        mpInstallChildResult('failed', 'guard-execution');
    } finally {
        if ($store instanceof \ChimMindPoisoning\PostgresStoreDb) {
            try {
                $store->release();
            } catch (Throwable) {
                // The child result below remains fail-closed on the behavioral assertions.
            }
        }
        try {
            $sql->close();
        } catch (Throwable) {
            // The short-lived CLI process will release the remaining native handle.
        }
    }

    $expectedStatus = match ($mode) {
        'paused' => 'plugin-paused',
        'core-off' => 'disabled',
        'interaction-off' => 'interaction-off',
        default => 'failed',
    };
    $expectedCalls = $mode === 'enabled' ? 1 : 0;
    if ($status !== $expectedStatus || $calls !== $expectedCalls) {
        mpInstallChildResult('failed', 'guard-assertion', [
            'mode' => $mode,
            'expected_calls' => $expectedCalls,
            'actual_calls' => $calls,
            'expected_status' => $expectedStatus,
            'actual_status' => is_string($status) ? $status : 'missing',
        ]);
    }
    if ($mode === 'enabled') {
        $loggedReason = false;
        $logContents = @file_get_contents(DEFAULT_LOG);
        if (is_string($logContents)) {
            foreach (preg_split('/\\R/', $logContents) ?: [] as $line) {
                $start = strpos($line, '{');
                if ($start === false) {
                    continue;
                }
                $record = json_decode(substr($line, $start), true);
                if (is_array($record)
                    && ($record['event'] ?? null) === 'request_finished'
                    && ($record['reason'] ?? null) === 'response_json_invalid'
                ) {
                    $loggedReason = true;
                    break;
                }
            }
        }
        if (!$loggedReason) {
            mpInstallChildResult('failed', 'controlled-rejection-log-missing');
        }
    }
    mpInstallChildResult('passed', 'ok', [
        'mode' => $mode,
        'status' => $status,
        'model_calls' => $calls,
        'reason' => $mode === 'enabled' ? 'response_json_invalid' : null,
    ]);
}

function mpInstallRunBackup(string $root, string $app): never
{
    $backupSource = $app . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'automatic_backup.php';
    if (!is_file($backupSource)) {
        mpInstallChildResult('failed', 'backup-class-missing');
    }
    require_once $backupSource;
    try {
        $backup = new AutomaticBackup();
        if (!$backup->isEnabled() || $backup->createBackup() !== true) {
            mpInstallChildResult('failed', 'backup-not-created');
        }
        $files = $backup->getBackups();
        if (!is_array($files) || $files === []) {
            mpInstallChildResult('failed', 'backup-list-empty');
        }
        $latest = $files[0];
        $path = realpath((string)($latest['filepath'] ?? ''));
        $backupRoot = realpath($app . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'databasebackups');
        if ($path === false || $backupRoot === false || !mpInstallWithin($path, $backupRoot)) {
            mpInstallChildResult('failed', 'backup-path-boundary');
        }
        $name = basename($path);
        $size = filesize($path);
        $sha = hash_file('sha256', $path);
        if (!preg_match('/\Aauto_backup_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}\.sql\z/D', $name)
            || !is_int($size) || $size < 1
            || !is_string($sha) || preg_match('/\A[a-f0-9]{64}\z/D', $sha) !== 1
        ) {
            mpInstallChildResult('failed', 'backup-metadata');
        }
        mpInstallChildResult('passed', 'ok', [
            'filename' => $name,
            'bytes' => $size,
            'sha256' => $sha,
        ]);
    } catch (Throwable) {
        mpInstallChildResult('failed', 'backup-execution');
    }
}

try {
    $phase = $argv[1] ?? '';
    $mode = $argv[2] ?? '';
    [$root, $app] = mpInstallPrepare($phase, $mode);
    if ($phase === 'guard') {
        mpInstallRunGuard($mode, $app);
    }
    if ($phase === 'backup') {
        mpInstallRunBackup($root, $app);
    }
    mpInstallChildResult('failed', 'phase-invalid');
} catch (Throwable) {
    mpInstallChildResult('failed', 'helper-execution');
}
