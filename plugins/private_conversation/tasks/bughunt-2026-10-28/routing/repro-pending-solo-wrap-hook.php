<?php
declare(strict_types=1);

require_once 'K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/scope.php';

function pcv_current_playthrough_key(): ?string { return 'fixture-playthrough'; }
function pcv_current_player_name(): ?string { return 'Hawke'; }
function pcv_read(string $key): ?array { return $GLOBALS['fixture_state']; }
function pcv_read_npc_catalog(): array { return $GLOBALS['fixture_catalog']; }
function pcv_read_active_scene_npcs($key, $rows, $playerName, $actorIds): array
{
    return ['known_npcs' => ['11' => 'Aela', '12' => 'Bryn'], 'missing' => [], 'error' => false];
}
function pcv_capture_presence_snapshot(...$arguments): array
{
    return ['status' => 'ready', 'actors' => [['name' => 'Aela'], ['name' => 'Bryn']]];
}
function pcv_begin_request($key, $eligible, ...$arguments): array
{
    $state = $GLOBALS['fixture_state'];
    if ($eligible && is_array($state['pending_scope'] ?? null)) {
        $GLOBALS['fixture_state'] = [
            'status' => 'active', 'scope' => $state['pending_scope'], 'pending' => false,
            'pending_scope' => null, 'config_id' => 'promoted-solo',
        ];
        return $GLOBALS['fixture_state'];
    }
    return $state;
}
function pcv_stage(string $key, array $desired, array $knownNpcs, ?string $stateDirectory = null, ?string $endReason = null): array
{
    $GLOBALS['fixture_stage_calls'][] = [$desired, $endReason];
    $GLOBALS['fixture_state'] = ['status' => 'off', 'scope' => null, 'pending' => false, 'pending_scope' => null];
    return ['status' => 'off', 'scope' => null, 'pending' => false];
}
function pcv_solo_inflight_mark(...$arguments): void {}

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
$GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
$GLOBALS['PLAYER_RESPEECH'] = false;
$GLOBALS['requestRoutingSnapshot'] = ['audience' => '|Aela|Bryn|', 'present_actors' => ['Aela', 'Bryn']];
$GLOBALS['gameRequest'] = ['inputtext', '1700000000', '12345', 'Hawke: wrap up: They part ways at dusk.', '{}'];
$GLOBALS['fixture_stage_calls'] = [];

$pair = [
    'enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '11', 'actor_b' => '12',
    'exclude_player' => true, 'bystander_mode' => 'exclude',
];
$solo = [
    'enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '11', 'actor_b' => null,
    'exclude_player' => true, 'bystander_mode' => 'exclude',
];
$presence = ['status' => 'ready', 'actors' => [['name' => 'Aela'], ['name' => 'Bryn']]];

function runHookCase(array $pair, ?array $pendingScope): array
{
    $GLOBALS['fixture_state'] = [
        'status' => 'active', 'scope' => $pair, 'pending' => $pendingScope !== null,
        'pending_scope' => $pendingScope, 'config_id' => 'active-pair',
    ];
    $GLOBALS['fixture_stage_calls'] = [];
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    $GLOBALS['PLAYER_RESPEECH'] = false;
    $GLOBALS['requestRoutingSnapshot'] = ['audience' => '|Aela|Bryn|', 'present_actors' => ['Aela', 'Bryn']];
    $GLOBALS['gameRequest'] = ['inputtext', '1700000000', '12345', 'Hawke: wrap up: They part ways at dusk.', '{}'];
    include 'K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/preprocessing.php';
    return [
        'state' => $GLOBALS['fixture_state'],
        'request' => $GLOBALS['gameRequest'],
        'request_scope' => $GLOBALS['PCV_REQUEST_SCOPE'] ?? null,
        'stage_calls' => $GLOBALS['fixture_stage_calls'],
    ];
}

$pairControl = runHookCase($pair, null);
$queuedSolo = runHookCase($pair, $solo);

check(($pairControl['request_scope']['scope']['scene_mode'] ?? null) === 'pair', 'pair control changed the active mode');
check(($pairControl['request_scope']['wrap_up'] ?? false) === true, 'pair control did not set its closing-turn flag');
check(($pairControl['request_scope']['route'] ?? null) === 'scene_direction', 'pair control was not routed as a closing direction');
check(($pairControl['request'][0] ?? null) === 'instruction'
    && ($pairControl['request'][3] ?? null) === 'Hawke: They part ways at dusk.', 'pair closing direction was not normalized');
check(count($pairControl['stage_calls']) === 1, 'pair closing turn did not clear the old stored scene');

check(($queuedSolo['state']['status'] ?? null) === 'active'
    && ($queuedSolo['state']['scope']['scene_mode'] ?? null) === 'solo', 'queued Solo was not promoted at the ordinary boundary');
check(($queuedSolo['request_scope']['scope']['scene_mode'] ?? null) === 'solo', 'preprocessing did not route the promoted Solo scope');
check(($queuedSolo['request_scope']['wrap_up'] ?? true) === false, 'preprocessing marked the Solo input as a closing turn');
check(($queuedSolo['request_scope']['route'] ?? null) === 'solo_reflection', 'queued Solo was not routed as a reflection');
check(($queuedSolo['request'][0] ?? null) === 'instruction'
    && ($queuedSolo['request'][3] ?? null) === 'Hawke: wrap up: They part ways at dusk.', 'wrap text was not passed through as Solo input');
check($queuedSolo['stage_calls'] === [], 'queued Solo wrap unexpectedly cleared a scene');

fwrite(STDOUT, json_encode(['pair_control' => $pairControl, 'active_pair_queued_solo' => $queuedSolo], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");