<?php
declare(strict_types=1);

require_once 'K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/scope.php';

// Test doubles isolate only persistence and core catalog/status reads. The actual PCV command parser, resolved-scope
// logic, wrap predicate, and scoped-input transformer execute from server/scope.php.
function pcv_current_playthrough_key() { return 'fixture-playthrough'; }
function pcv_current_player_name() { return 'Hawke'; }
function pcv_read($key) { return $GLOBALS['fixture_state']; }
function pcv_read_npc_catalog() { return $GLOBALS['fixture_catalog']; }
function pcv_read_active_scene_npcs($key, $rows, $playerName, $actorIds)
{
    return ['known_npcs' => ['11' => 'Aela', '12' => 'Bryn'], 'missing' => [], 'error' => false];
}
function pcv_begin_request($key, $eligible, ...$args)
{
    $state = $GLOBALS['fixture_state'];
    if ($eligible && is_array($state['pending_scope'] ?? null)) {
        return ['status' => 'active', 'scope' => $state['pending_scope'], 'pending' => false, 'config_id' => 'promoted-solo'];
    }
    return ['status' => 'active', 'scope' => $state['scope'], 'pending' => false, 'config_id' => 'current-pair'];
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$GLOBALS['fixture_catalog'] = [
    ['id' => 11, 'npc_name' => 'Aela', 'profile_id' => 101],
    ['id' => 12, 'npc_name' => 'Bryn', 'profile_id' => 102],
];
$currentPair = [
    'enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '11', 'actor_b' => '12',
    'exclude_player' => true, 'bystander_mode' => 'exclude',
];
$queuedSolo = [
    'enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '11', 'actor_b' => null,
    'exclude_player' => true, 'bystander_mode' => 'exclude',
];
$presence = ['status' => 'ready', 'actors' => [['name' => 'Aela'], ['name' => 'Bryn']]];

function runWrapCase(array $currentPair, ?array $pendingScope, array $presence): array
{
    $stored = ['status' => 'active', 'scope' => $currentPair, 'pending_scope' => $pendingScope, 'pending' => $pendingScope !== null];
    $GLOBALS['fixture_state'] = $stored;
    $raw = 'Hawke: wrap up: They part ways at dusk.';
    $command = pcvInGameCommand($raw, 'Hawke');
    check(($command['command'] ?? null) === 'wrap', 'wrap directive was not recognized');
    $endedBeforeActivation = pcvWrapEndsScene($stored);
    $resolved = pcvBeginResolvedScope(true, $presence, false);
    check(($resolved['status'] ?? null) === 'active' && is_array($resolved['scope'] ?? null), 'scene did not resolve after eligible ordinary boundary');

    $wrapUp = ($command['command'] ?? null) === 'wrap' && ($resolved['scope']['scene_mode'] ?? 'pair') !== 'solo';
    $request = ['inputtext', '1700000000', 'g', $raw, 'client-json'];
    if ($wrapUp) {
        $request[3] = 'Hawke: ' . $command['direction'];
    }
    $prepared = pcvPrepareScopedInput($request, [], $resolved['scope'], 'Hawke', 'STANDARD', $wrapUp);
    $route = ($resolved['scope']['scene_mode'] ?? 'pair') === 'solo'
        ? 'solo_reflection'
        : (($wrapUp || ($resolved['scope']['exclude_player'] ?? true)) ? 'scene_direction' : 'player_speech');

    return [
        'stored_active_mode' => $currentPair['scene_mode'],
        'stored_pending_mode' => $pendingScope['scene_mode'] ?? null,
        'solo_end_predicate_before_activation' => $endedBeforeActivation,
        'resolved_mode' => $resolved['scope']['scene_mode'],
        'wrap_close_turn' => $wrapUp,
        'route' => $route,
        'prepared_request_type' => $prepared['game_request'][0] ?? null,
        'prepared_text' => $prepared['game_request'][3] ?? null,
    ];
}

$pairOnly = runWrapCase($currentPair, null, $presence);
$pairWithQueuedSolo = runWrapCase($currentPair, $queuedSolo, $presence);

check($pairOnly['wrap_close_turn'] === true && $pairOnly['route'] === 'scene_direction', 'active pair control did not route a closing turn');
check($pairWithQueuedSolo['solo_end_predicate_before_activation'] === false, 'the active-pair stored state unexpectedly matched the solo-end predicate');
check($pairWithQueuedSolo['resolved_mode'] === 'solo', 'eligible request did not promote queued Solo');
check($pairWithQueuedSolo['wrap_close_turn'] === false && $pairWithQueuedSolo['route'] === 'solo_reflection', 'queued Solo case was incorrectly treated as a closing turn');
check(str_contains((string)$pairWithQueuedSolo['prepared_text'], 'wrap up:'), 'queued Solo repro did not preserve the wrap text as its input direction');

fwrite(STDOUT, json_encode(['active_pair_control' => $pairOnly, 'active_pair_queued_solo' => $pairWithQueuedSolo], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");