<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function eligibilityCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function eligibilityPayload(array $overrides = []): string
{
    $payload = array_replace([
        'source' => 'plugin_player_routing_v2',
        'execution_mode' => 'STANDARD',
        'speech_mode' => 'standard',
        'audience_radius_units' => 1000,
        'present_actors' => [
            ['form_id' => 10, 'name' => 'Aela', 'distance' => 20, 'managed' => true, 'creature' => false],
            ['form_id' => 20, 'name' => 'Faendal', 'distance' => 30, 'managed' => true, 'creature' => false],
            ['form_id' => 30, 'name' => 'Lydia', 'distance' => 1001, 'managed' => true, 'creature' => false],
            ['form_id' => 40, 'name' => 'Unmanaged', 'distance' => 40, 'managed' => false, 'creature' => false],
            ['form_id' => 50, 'name' => 'Troll', 'distance' => 50, 'managed' => true, 'creature' => true],
        ],
    ], $overrides);
    return base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
}

function eligibilityCleanup(string $directory): void
{
    foreach (['state.json', 'presence.json', 'background_presence.json', 'state.lock'] as $name) {
        @unlink($directory . DIRECTORY_SEPARATOR . $name);
    }
    @rmdir($directory);
}

$stateDirectory = sys_get_temp_dir() . '/pcv-eligibility-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-eligibility-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    eligibilityCheck(mkdir($logDirectory, 0700), 'could not create isolated logger fixture');
    eligibilityCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');

    $key = hash('sha256', 'eligibility fixture playthrough');
    $otherKey = hash('sha256', 'different eligibility fixture playthrough');
    $parsed = pcv_parse_presence_snapshot(eligibilityPayload());
    eligibilityCheck(($parsed['status'] ?? null) === 'ready', 'valid ordinary snapshot should parse');
    eligibilityCheck(count($parsed['actors'] ?? []) === 3, 'unmanaged and out-of-radius actors must not be eligible');
    eligibilityCheck(in_array('Troll', array_column($parsed['actors'], 'name'), true), 'creature records must not be filtered by species');

    eligibilityCheck(pcv_parse_presence_snapshot(eligibilityPayload(['speech_mode' => 'shout']))['status'] === 'unavailable',
        'non-standard speech mode must be rejected');
    eligibilityCheck(pcv_parse_presence_snapshot(eligibilityPayload(['execution_mode' => 'DIRECTOR']))['status'] === 'unavailable',
        'non-standard execution mode must be rejected');
    eligibilityCheck(pcv_parse_presence_snapshot(eligibilityPayload(['source' => 'other']))['status'] === 'unavailable',
        'unrecognized producer must be rejected');
    eligibilityCheck(pcv_parse_presence_snapshot(eligibilityPayload(['present_actors' => []]))['status'] === 'empty',
        'valid empty presence report must remain distinct from missing or invalid');
    $extendedActor = ['form_id' => 10, 'name' => 'Aela', 'distance' => 20, 'managed' => true, 'creature' => false, 'extra' => true];
    $extended = pcv_parse_presence_snapshot(eligibilityPayload(['present_actors' => [$extendedActor]]));
    eligibilityCheck(($extended['status'] ?? null) === 'ready'
        && array_keys($extended['actors'][0] ?? []) === ['form_id', 'name', 'distance'],
        'harmless producer fields should be ignored and never projected into cached metadata');
    eligibilityCheck(pcv_parse_presence_snapshot(null)['status'] === 'missing', 'missing payload should be distinct');

    eligibilityCheck(pcv_read_eligible_npcs($key, [], 'Runa', $stateDirectory)['status'] === 'missing',
        'absence of a snapshot should be distinct from an empty live report');
    eligibilityCheck(pcv_capture_presence_snapshot($key, eligibilityPayload(), $stateDirectory)['status'] === 'ready',
        'valid presence snapshot should be stored');
    $catalog = [
        ['id' => 101, 'profile_id' => 1, 'npc_name' => 'Aela'],
        ['id' => 202, 'profile_id' => 2, 'npc_name' => 'Faendal'],
        ['id' => 303, 'profile_id' => 3, 'npc_name' => 'Troll'],
        ['id' => 404, 'profile_id' => 4, 'npc_name' => 'Lydia'],
    ];
    $ordinaryActors = pcv_parse_presence_snapshot(eligibilityPayload())['actors'];
    $known = pcvScopeEligibleMapFromPresence($ordinaryActors, $catalog, 'Runa');
    eligibilityCheck(array_keys($known) === [101, 202, 303],
        'ordinary request parsing should still intersect the current catalog without species filtering');
    eligibilityCheck(pcv_read_eligible_npcs($key, $catalog, 'Runa', $stateDirectory)['status'] === 'missing',
        'an ordinary request cache must not authorize the background picker');
    $renamedCatalog = $catalog;
    $renamedCatalog[0]['npc_name'] = 'Renamed Aela';
    $renamedMap = pcvScopeEligibleMapFromPresence($ordinaryActors, $renamedCatalog, 'Runa');
    eligibilityCheck(!array_key_exists('101', $renamedMap), 'catalog changes must be re-resolved on every request');
    $ambiguousCatalog = $catalog;
    $ambiguousCatalog[] = ['id' => 505, 'profile_id' => 5, 'npc_name' => 'Aela'];
    eligibilityCheck(!array_key_exists('101', pcvScopeEligibleMapFromPresence($ordinaryActors, $ambiguousCatalog, 'Runa')),
        'ambiguous current catalog names must be excluded');

    eligibilityCheck(pcv_capture_presence_snapshot($key, eligibilityPayload(['present_actors' => []]), $stateDirectory)['status'] === 'empty',
        'valid empty snapshot should replace prior presence');
    $emptyOrdinary = json_decode((string)file_get_contents($stateDirectory . '/presence.json'), true, 16, JSON_THROW_ON_ERROR);
    eligibilityCheck(($emptyOrdinary['actors'] ?? null) === [], 'ordinary empty request snapshot should persist its own empty result');
    pcv_capture_presence_snapshot($key, eligibilityPayload(), $stateDirectory);
    eligibilityCheck(pcv_capture_presence_snapshot($key, eligibilityPayload(['speech_mode' => 'shout']), $stateDirectory)['status'] === 'unavailable',
        'non-standard ordinary input should invalidate an earlier standard report');
    eligibilityCheck(!is_file($stateDirectory . '/presence.json'), 'non-standard ordinary input must clear its previous report');
    pcv_capture_presence_snapshot($key, eligibilityPayload(), $stateDirectory);
    eligibilityCheck(pcv_capture_presence_snapshot($key, null, $stateDirectory)['status'] === 'missing',
        'missing ordinary snapshot should be reported');
    eligibilityCheck(!is_file($stateDirectory . '/presence.json'), 'missing ordinary input must invalidate the old request snapshot');

    pcv_capture_presence_snapshot($otherKey, eligibilityPayload(), $stateDirectory);
    $wrongPlaythrough = json_decode((string)file_get_contents($stateDirectory . '/presence.json'), true, 16, JSON_THROW_ON_ERROR);
    eligibilityCheck(($wrongPlaythrough['key'] ?? null) === $otherKey,
        'an ordinary report remains bound to the key supplied by its request');
    $duplicateActors = pcv_parse_presence_snapshot(eligibilityPayload(['present_actors' => [
        ['form_id' => 10, 'name' => 'Aela', 'distance' => 20, 'managed' => true, 'creature' => false],
        ['form_id' => 11, 'name' => 'aela', 'distance' => 21, 'managed' => true, 'creature' => false],
    ]]))['actors'];
    eligibilityCheck(pcvScopeEligibleMapFromPresence($duplicateActors, $catalog, 'Runa') === [],
        'duplicate normalized nearby names must not resolve to one NPC ID');
    pcv_capture_presence_snapshot($key, eligibilityPayload(), $stateDirectory);
    $storedPresence = json_decode((string)file_get_contents($stateDirectory . '/presence.json'), true, 16, JSON_THROW_ON_ERROR);
    $storedPresence['observed_at'] = time() - 46;
    file_put_contents($stateDirectory . '/presence.json', json_encode($storedPresence, JSON_THROW_ON_ERROR));
    eligibilityCheck(!str_contains((string)file_get_contents($stateDirectory . '/presence.json'), 'Runa'),
        'presence storage must not contain player conversation input');

    $config = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    pcv_stage($key, $config, ['101' => 'Aela', '202' => 'Faendal'], $stateDirectory);
    $withoutMap = pcv_begin_request($key, true, $stateDirectory);
    eligibilityCheck(($withoutMap['status'] ?? null) === 'unavailable' && ($withoutMap['pending'] ?? false),
        'enabled pending config must fail closed without current eligibility');
    $partialMap = pcv_begin_request($key, true, $stateDirectory, ['101' => 'Aela']);
    eligibilityCheck(($partialMap['status'] ?? null) === 'unavailable' && ($partialMap['pending'] ?? false),
        'enabled pending config must fail closed unless both actors are eligible');
    eligibilityCheck(($partialMap['scope'] ?? null) === null, 'blocked ARM must not expose an active scope');
    $stateAfterBlockedArm = pcv_read($key, $stateDirectory);
    eligibilityCheck(($stateAfterBlockedArm['status'] ?? null) === 'pending', 'blocked ARM must remain pending on disk');
    $blockedEvents = array_map(static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR),
        file((string)pcv_log_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $blockedActivation = array_filter($blockedEvents, static fn(array $event): bool =>
        ($event['event'] ?? null) === 'state.scope_activated');
    eligibilityCheck($blockedActivation === [],
        'blocked ARM must not emit an activation event');
    $pairRejectionLogged = array_values(array_filter($blockedEvents, static fn(array $event): bool =>
        ($event['event'] ?? null) === 'state.scope_skipped' && ($event['reason'] ?? null) === 'scene_not_eligible'));
    eligibilityCheck(count($pairRejectionLogged) === 2
        && ($pairRejectionLogged[0]['severity'] ?? null) === 'info'
        && ($pairRejectionLogged[1]['severity'] ?? null) === 'info',
        'both expected ineligibility outcomes should log bounded informational skips');
    $ineligibilityErrors = array_filter($blockedEvents, static fn(array $event): bool =>
        ($event['event'] ?? null) === 'state.unavailable' && ($event['reason'] ?? null) === 'scene_not_eligible');
    eligibilityCheck($ineligibilityErrors === [], 'expected ineligibility must not be logged as an error');
    $activated = pcv_begin_request($key, true, $stateDirectory, ['101' => 'Aela', '202' => 'Faendal']);
    eligibilityCheck(($activated['status'] ?? null) === 'active' && !($activated['pending'] ?? true),
        'enabled pending pair should promote when both IDs are eligible');
    $activeWithoutPair = pcv_begin_request($key, false, $stateDirectory, ['101' => 'Aela']);
    eligibilityCheck(($activeWithoutPair['status'] ?? null) === 'unavailable'
        && ($activeWithoutPair['scope'] ?? null) === null
        && ($activeWithoutPair['config_id'] ?? null) === ($activated['config_id'] ?? null),
        'an already-active pair must fail closed when current presence no longer includes both actors');
    $activePairRestored = pcv_begin_request($key, false, $stateDirectory, ['101' => 'Aela', '202' => 'Faendal']);
    eligibilityCheck(($activePairRestored['status'] ?? null) === 'active',
        'an active pair should continue when both IDs remain eligible');

    $replacement = ['enabled' => true, 'actor_a' => '101', 'actor_b' => '303', 'exclude_player' => false, 'bystander_mode' => 'silent'];
    pcv_stage($key, $replacement, ['101' => 'Aela', '303' => 'Troll'], $stateDirectory);
    $blockedReplacement = pcv_begin_request($key, true, $stateDirectory, ['101' => 'Aela']);
    eligibilityCheck(($blockedReplacement['status'] ?? null) === 'unavailable',
        'ineligible replacement must fail closed for its attempted request');
    $stateAfterBlockedReplacement = pcv_read($key, $stateDirectory);
    eligibilityCheck(($stateAfterBlockedReplacement['status'] ?? null) === 'active'
        && ($stateAfterBlockedReplacement['scope'] ?? null) === $config
        && ($stateAfterBlockedReplacement['pending'] ?? false),
        'blocked replacement must preserve old active and pending config on disk');

    pcv_stage($key, ['enabled' => false], [], $stateDirectory);
    $ended = pcv_begin_request($key, true, $stateDirectory);
    eligibilityCheck(($ended['status'] ?? null) === 'off', 'END must promote without an eligibility map');

    echo "PASS: native snapshot validation, background-only cache boundary, catalog identity mapping, and guarded activation\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    eligibilityCleanup($stateDirectory);
    if (is_dir($logDirectory)) {
        foreach (['events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl', 'events.lock'] as $name) {
            @unlink($logDirectory . DIRECTORY_SEPARATOR . $name);
        }
        @rmdir($logDirectory);
    }
}

exit($exitCode);
