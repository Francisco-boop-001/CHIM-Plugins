<?php
declare(strict_types=1);

function hookCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pcv_current_playthrough_key(): ?string
{
    return 'validated-playthrough-key';
}

function pcv_current_player_name(): ?string
{
    return 'Runa';
}

function pcvPrepareScopedInput(): void {}

function pcv_capture_background_presence_report(
    ?string $key,
    $raw,
    ?string $playerName,
    $requestTimestamp,
    ?string $stateDirectory = null
): array {
    $GLOBALS['background_capture_calls'][] = [$key, $raw, $playerName, $requestTimestamp, $stateDirectory];
    if (($GLOBALS['background_capture_throw'] ?? false) === true) {
        throw new RuntimeException('capture failed');
    }
    return ['status' => 'ready'];
}

function pcv_invalidate_eligible_npcs(): array
{
    $GLOBALS['background_invalidations'] = ($GLOBALS['background_invalidations'] ?? 0) + 1;
    return ['status' => 'missing'];
}

function pcv_log_exception(...$arguments): void
{
    $GLOBALS['background_logged'] = true;
    $GLOBALS['background_log_arguments'] = $arguments;
}

function terminate(): void
{
    $GLOBALS['background_terminated'] = true;
}

$serverDirectory = dirname(__DIR__) . '/server';
$raw = "NPC report\nnearby names";
$request = ['infonpc_close', '987654321', '12345', $raw, 'unused'];
$GLOBALS['gameRequest'] = $request;
$GLOBALS['external_fast_commands'] = [];
$GLOBALS['background_capture_calls'] = [];
$GLOBALS['background_invalidations'] = 0;
$GLOBALS['background_logged'] = false;
$GLOBALS['background_log_arguments'] = [];
$GLOBALS['background_terminated'] = false;

include $serverDirectory . '/preprocessing.php';

hookCheck($GLOBALS['background_capture_calls'] === [[
    'validated-playthrough-key', $raw, 'Runa', '987654321', null,
]], 'infonpc_close should capture its raw report, validated identity, and native timestamp');
hookCheck($GLOBALS['gameRequest'] === $request, 'the event payload must remain unchanged for CHIM core logging');
hookCheck($GLOBALS['external_fast_commands'] === [], 'the existing infonpc_close fast route must not be replaced');
hookCheck($GLOBALS['background_terminated'] === false, 'the extension hook must not terminate the core event');

$GLOBALS['background_capture_calls'] = [];
$GLOBALS['gameRequest'] = ['infonpc', '987654322', '12345', $raw, 'unused'];
include $serverDirectory . '/preprocessing.php';
hookCheck($GLOBALS['background_capture_calls'] === [], 'the separate infonpc event must not create a background heartbeat');

$GLOBALS['gameRequest'] = $request;
$GLOBALS['background_capture_calls'] = [];
$GLOBALS['background_capture_throw'] = true;
include $serverDirectory . '/preprocessing.php';
hookCheck($GLOBALS['background_invalidations'] === 1, 'capture exceptions must fail closed by invalidating eligibility');
hookCheck($GLOBALS['background_logged'] === true, 'capture exceptions should be recorded');
hookCheck(($GLOBALS['background_log_arguments'][5]['operation'] ?? null) === 'presence_capture',
    'capture errors must use the existing approved log operation');
hookCheck($GLOBALS['gameRequest'] === $request && $GLOBALS['background_terminated'] === false,
    'capture failure must not consume or rewrite the core fast event');

echo "background presence hook checks passed\n";
