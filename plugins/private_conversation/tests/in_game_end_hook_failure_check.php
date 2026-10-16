<?php
declare(strict_types=1);

// Hook-level coverage for in-game END paths when the state store cannot confirm a clear.
final class HookFixtureStopped extends RuntimeException {}

function endHookCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pcv_current_playthrough_key(): ?string { return 'validated-playthrough-key'; }
function pcv_current_player_name(): ?string { return 'Runa'; }
function pcvEffectiveExecutionMode(...$arguments): string { return 'STANDARD'; }
function pcv_read(string $key): ?array { return $GLOBALS['fixture_state']; }
function pcvInGameCommand($raw, ?string $playerName = null): ?array
{
    $text = trim((string)$raw);
    if (preg_match('/^(?:Runa:\s*)?end scene[.!?]*$/i', $text)) {
        return ['command' => 'end'];
    }
    if (preg_match('/^(?:Runa:\s*)?wrap up:\s*(.*)$/i', $text, $matches)) {
        return ['command' => 'wrap', 'direction' => trim($matches[1]) ?: 'They part ways.'];
    }
    return null;
}
function pcvWrapEndsScene(?array $state): bool
{
    return in_array($state['status'] ?? null, ['active', 'pending'], true)
        && ($state['scope']['scene_mode'] ?? null) === 'solo';
}
function pcv_stage(string $key, array $desired, array $knownNpcs, ?string $stateDirectory = null, ?string $endReason = null): array
{
    $GLOBALS['stage_calls'][] = [$key, $desired, $endReason];
    if (($GLOBALS['stage_result'] ?? 'unavailable') === 'unavailable') {
        return ['status' => 'unavailable'];
    }
    $GLOBALS['fixture_state'] = ['status' => 'off', 'scope' => null];
    return ['status' => 'off'];
}
function pcvRoutingLogStart(...$arguments): void {}
function pcvRoutingLogSetState(...$arguments): void {}
function pcvRoutingLogException(...$arguments): void {}
function pcvRoutingLogDetail(...$arguments): void { $GLOBALS['routing_details'][] = $arguments; }
function pcvRoutingAudienceCount(...$arguments): int { return 0; }
function pcvRoutingPresenceCount(...$arguments): int { return 0; }
function pcvEndRequestQuietly(...$arguments): void
{
    $GLOBALS['end_success'][] = $arguments;
    throw new HookFixtureStopped('request ended');
}
function pcvBlockRequest(...$arguments): void
{
    $GLOBALS['blocked'][] = $arguments;
    throw new HookFixtureStopped('request blocked');
}
function pcv_capture_presence_snapshot(...$arguments): array { return ['status' => 'ready', 'actors' => []]; }
function pcvBeginResolvedScope(...$arguments): array { return $GLOBALS['fixture_state']; }
function pcv_log_set_terminal(...$arguments): void {}
function pcv_log_event(...$arguments): void {}
function pcvRoutingLogType(...$arguments): string { return 'inputtext'; }
function pcvRoutingLogMode(...$arguments): string { return 'standard'; }
function pcvPrepareScopedInput(...$arguments): array
{
    $GLOBALS['prepared_requests'][] = $arguments[0];
    return ['status' => 'applied', 'game_request' => $arguments[0], 'routing_snapshot' => []];
}
function pcvGroupPickOpener(...$arguments): array { return ['name' => 'A', 'source' => 'first']; }
function pcvScopeMembers(...$arguments): array { return ['A', 'B']; }
function pcvSnapshotDirectTarget(...$arguments): ?string { return null; }

function runEndHook(array $request, array $state, string $stageResult): array
{
    $GLOBALS['gameRequest'] = $request;
    $GLOBALS['fixture_state'] = $state;
    $GLOBALS['stage_result'] = $stageResult;
    $GLOBALS['stage_calls'] = [];
    $GLOBALS['end_success'] = [];
    $GLOBALS['blocked'] = [];
    $GLOBALS['prepared_requests'] = [];
    $GLOBALS['routing_details'] = [];
    try {
        include dirname(__DIR__) . '/server/preprocessing.php';
        return ['stopped' => false];
    } catch (HookFixtureStopped $stopped) {
        return ['stopped' => true, 'request' => $GLOBALS['gameRequest']];
    }
}

$active = ['status' => 'active', 'config_id' => 'active-config', 'scope' => [
    'scene_mode' => 'pair', 'actor_a' => 'A', 'actor_b' => 'B', 'exclude_player' => true,
], 'profile_id_a' => 11, 'profiles' => ['A' => 11, 'B' => 12], 'actor_a_id' => 'a-id'];
$pending = ['status' => 'pending', 'config_id' => 'pending-config', 'scope' => [
    'scene_mode' => 'pair', 'actor_a' => 'A', 'actor_b' => 'B', 'exclude_player' => true,
]];
$solo = ['status' => 'active', 'config_id' => 'solo-config', 'scope' => [
    'scene_mode' => 'solo', 'actor_a' => 'A', 'exclude_player' => true,
], 'profile_id_a' => 11, 'profiles' => ['A' => 11], 'actor_a_id' => 'a-id'];
$request = static fn(string $text): array => ['inputtext', '1234567890', '12345', $text, []];

try {
    foreach ([['active END', $active, 'Runa: end scene'], ['pending END', $pending, 'Runa: end scene'],
             ['solo wrap-up', $solo, 'Runa: wrap up: They part ways.']] as [$label, $state, $text]) {
        $result = runEndHook($request($text), $state, 'unavailable');
        endHookCheck($result['stopped'], "$label must stop when stage returns unavailable");
        endHookCheck(count($GLOBALS['blocked']) === 1, "$label must use the existing blocked-error path");
        endHookCheck(($GLOBALS['blocked'][0][1] ?? null) === 'state_unavailable', "$label must report state_unavailable");
        endHookCheck(($GLOBALS['blocked'][0][4] ?? null) === true, "$label must log an error outcome");
        endHookCheck(str_contains($GLOBALS['blocked'][0][0] ?? '', 'could not confirm'), "$label must state that ending could not be confirmed");
        endHookCheck($GLOBALS['end_success'] === [], "$label must not claim that the scene ended");
        endHookCheck(count($GLOBALS['stage_calls']) === 1, "$label must attempt one stored-state clear");
        endHookCheck($GLOBALS['prepared_requests'] === [], "$label must stop before input preparation");
    }

    $groupWrap = runEndHook($request('Runa: wrap up: They part ways.'), $active, 'unavailable');
    endHookCheck($groupWrap['stopped'], 'pair/group wrap-up must stop when stage returns unavailable');
    endHookCheck(($GLOBALS['blocked'][0][1] ?? null) === 'state_unavailable', 'pair/group wrap-up must use state_unavailable');
    endHookCheck(($GLOBALS['blocked'][0][4] ?? null) === true, 'pair/group wrap-up must log an error outcome');
    endHookCheck($GLOBALS['end_success'] === [], 'pair/group wrap-up must not claim successful completion');
    endHookCheck(count($GLOBALS['prepared_requests']) === 1, 'pair/group wrap-up should reach its closing-turn preparation before ending storage');
    endHookCheck(count($GLOBALS['routing_details']) === 0, 'pair/group wrap-up must not report a routed closing turn after clear failure');

    $soloWrap = runEndHook($request('Runa: wrap up: They part ways.'), $solo, 'off');
    endHookCheck($soloWrap['stopped'] && count($GLOBALS['end_success']) === 1, 'solo wrap-up should retain its success response after a confirmed clear');
    endHookCheck($GLOBALS['blocked'] === [] && $GLOBALS['prepared_requests'] === [], 'solo wrap-up should end before ordinary preparation when clear succeeds');

    $pairWrap = runEndHook($request('Runa: wrap up: They part ways.'), $active, 'off');
    endHookCheck(!$pairWrap['stopped'], 'pair/group wrap-up should continue after a confirmed clear');
    endHookCheck(($GLOBALS['PCV_REQUEST_SCOPE']['wrap_up'] ?? false) === true, 'pair/group wrap-up should retain its closing-turn route');
    endHookCheck(($GLOBALS['fixture_state']['status'] ?? null) === 'off', 'pair/group wrap-up should clear storage before routing completion');

    foreach ([['active END', $active], ['pending END', $pending]] as [$label, $state]) {
        $result = runEndHook($request('Runa: end scene'), $state, 'off');
        endHookCheck($result['stopped'] && count($GLOBALS['end_success']) === 1, "$label should retain its successful END response");
        endHookCheck($GLOBALS['blocked'] === [], "$label should not block after a confirmed clear");
    }

    $ordinary = runEndHook($request('Runa: end scene'), ['status' => 'off', 'scope' => null], 'unavailable');
    endHookCheck(!$ordinary['stopped'] && $GLOBALS['stage_calls'] === [], 'an unarmed exact END phrase must remain ordinary input');

    echo "PASS END failures block; solo/group wraps and active/pending END success plus unarmed input remain intact\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
