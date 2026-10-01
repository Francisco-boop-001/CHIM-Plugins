<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function backgroundCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function backgroundCatalog(array $timestamps): array
{
    $rows = [];
    foreach (['Aela' => 101, 'Faendal' => 202, 'Lydia' => 303] as $name => $id) {
        $metadata = isset($timestamps[$name])
            ? json_encode(['activity_status' => ['timestamp' => $timestamps[$name]]], JSON_THROW_ON_ERROR)
            : '{}';
        $rows[] = ['id' => $id, 'profile_id' => $id, 'npc_name' => $name, 'metadata' => $metadata];
    }
    return $rows;
}

function backgroundCapture(string $key, string $raw, int $timestamp, string $directory): array
{
    return pcv_capture_background_presence_report($key, $raw, 'Runa', $timestamp, $directory);
}

function backgroundCleanup(string $directory, string $logDirectory): void
{
    foreach (['state.json', 'presence.json', 'background_presence.json', 'state.lock'] as $name) {
        @unlink($directory . DIRECTORY_SEPARATOR . $name);
    }
    @rmdir($directory);
    foreach (glob($logDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($logDirectory);
}

function backgroundLogEntries(string $logDirectory): array
{
    $path = $logDirectory . DIRECTORY_SEPARATOR . 'events.jsonl';
    if (!is_file($path) || is_link($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return is_array($lines)
        ? array_map(static fn(string $line): array => json_decode($line, true, 32, JSON_THROW_ON_ERROR), $lines)
        : [];
}

function backgroundObservation(array $entries, string $source, string $outcome): ?array
{
    foreach (array_reverse($entries) as $entry) {
        if (($entry['event'] ?? null) === 'state.presence_observed'
            && ($entry['context']['source'] ?? null) === $source
            && ($entry['outcome'] ?? null) === $outcome) {
            return $entry;
        }
    }
    return null;
}

$stateDirectory = sys_get_temp_dir() . '/pcv-background-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-background-log-' . bin2hex(random_bytes(8));
$exitCode = 0;
try {
    backgroundCheck(mkdir($logDirectory, 0700), 'could not create isolated logger fixture');
    backgroundCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');

    $key = hash('sha256', 'background presence playthrough');
    $h0 = 1_700_000_000_000_000_000;
    $h1 = $h0 + 100_000_000;
    $h2 = $h1 + 9_000_000_000;
    $report = 'Aela (busy)/Faendal (sleeping)/Runa';
    $parsed = pcv_parse_background_presence_report($report, 'Runa');
    backgroundCheck(($parsed['actors'] ?? []) === [['name' => 'Aela'], ['name' => 'Faendal']],
        'native slash-delimited names and known status suffixes should parse without extra fields');

    $first = backgroundCapture($key, $report, $h0, $stateDirectory);
    backgroundCheck(($first['status'] ?? null) === 'baseline', 'first heartbeat should establish a clock baseline only');
    $baselineObservation = backgroundObservation(backgroundLogEntries($logDirectory), 'background_capture', 'unavailable');
    backgroundCheck(($baselineObservation['reason'] ?? null) === 'presence_baseline'
        && ($baselineObservation['context']['actor_count'] ?? null) === 2,
        'A first roster baseline should be visible as awaiting ordering with a bounded count, not advertised as available or stale.');
    $read = pcv_read_eligible_npcs($key, backgroundCatalog(['Aela' => $h0 - 1, 'Faendal' => $h0 - 1]), 'Runa', $stateDirectory);
    backgroundCheck(($read['status'] ?? null) === 'stale'
        && ($read['reason'] ?? null) === 'presence_stale',
        'The diagnostic distinction must not change the public stale-baseline result.');
    $staleReadObservation = backgroundObservation(backgroundLogEntries($logDirectory), 'background_read', 'unavailable');
    backgroundCheck(($staleReadObservation['reason'] ?? null) === 'presence_baseline',
        'A baseline read must expose its fixed reason without presenting the NPC roster as available.');

    $advanced = backgroundCapture($key, $report, $h1, $stateDirectory);
    backgroundCheck(($advanced['status'] ?? null) === 'ready', 'strictly advancing heartbeat should refresh the report');
    $availableObservation = backgroundObservation(backgroundLogEntries($logDirectory), 'background_capture', 'available');
    backgroundCheck(($availableObservation['reason'] ?? null) === null
        && ($availableObservation['context']['actor_count'] ?? null) === 2,
        'A fresh bounded roster should be reported as available by count only.');
    $read = pcv_read_eligible_npcs($key, backgroundCatalog(['Aela' => $h0 + 50_000_000, 'Faendal' => $h0 - 1]), 'Runa', $stateDirectory);
    backgroundCheck(($read['status'] ?? null) === 'ready'
        && ($read['known_npcs'] ?? null) === ['101' => 'Aela'],
        'only a fresh managed-status timestamp after baseline and before heartbeat should join');

    $postHeartbeatTimestamp = $h1 + 8_000_000_000;
    $postHeartbeatTime = $postHeartbeatTimestamp + 100_000_000;
    $postHeartbeatReport = backgroundCapture($key, $report, $postHeartbeatTimestamp, $stateDirectory);
    $postHeartbeatDocument = json_decode((string)file_get_contents(
        $stateDirectory . DIRECTORY_SEPARATOR . 'background_presence.json'
    ), true, 16, JSON_THROW_ON_ERROR);
    $postHeartbeatDocument['observed_at'] = time() - 1;
    file_put_contents(
        $stateDirectory . DIRECTORY_SEPARATOR . 'background_presence.json',
        json_encode($postHeartbeatDocument, JSON_THROW_ON_ERROR)
    );
    $postHeartbeatRead = pcv_read_eligible_npcs(
        $key,
        backgroundCatalog(['Aela' => $postHeartbeatTime]),
        'Runa',
        $stateDirectory
    );
    backgroundCheck(($postHeartbeatReport['status'] ?? null) === 'ready'
        && ($postHeartbeatRead['status'] ?? null) === 'ready'
        && ($postHeartbeatRead['known_npcs'] ?? null) === ['101' => 'Aela'],
        'a post-heartbeat bulk status should join once the projected native clock catches its timestamp');

    $path = $stateDirectory . DIRECTORY_SEPARATOR . 'background_presence.json';
    $stored = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    $expiredReceipt = time() - 46;
    $stored['observed_at'] = $expiredReceipt;
    file_put_contents($path, json_encode($stored, JSON_THROW_ON_ERROR));
    $sameHeartbeat = backgroundCapture($key, $report, $postHeartbeatTimestamp, $stateDirectory);
    backgroundCheck(($sameHeartbeat['status'] ?? null) === 'stale', 'duplicate heartbeat must not refresh receipt age');
    $afterDuplicate = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    backgroundCheck(($afterDuplicate['observed_at'] ?? null) === $expiredReceipt,
        'duplicate heartbeat must leave the old receipt time untouched');
    backgroundCheck(pcv_read_eligible_npcs($key, backgroundCatalog(['Aela' => $h0 + 50_000_000]), 'Runa', $stateDirectory)['status'] === 'stale',
        'page polling and duplicate heartbeats must not renew an expired receipt');

    $fresh = backgroundCapture($key, $report, $h2, $stateDirectory);
    backgroundCheck(($fresh['status'] ?? null) === 'baseline', 'an advancing heartbeat after expiry should reset the baseline');
    backgroundCheck(pcv_read_eligible_npcs($key, backgroundCatalog(['Aela' => $postHeartbeatTime]), 'Runa', $stateDirectory)['status'] === 'stale',
        'status from before an expired heartbeat lease must not survive the new baseline');
    $h3 = $h2 + 1_000_000_000;
    backgroundCapture($key, $report, $h3, $stateDirectory);
    $futureRead = pcv_read_eligible_npcs($key, backgroundCatalog(['Aela' => $h3 + 100_000_000_000]), 'Runa', $stateDirectory);
    backgroundCheck(($futureRead['status'] ?? null) === 'stale' && ($futureRead['known_npcs'] ?? []) === [],
        'a status timestamp too far ahead of the projected clock must not be treated as fresh');

    $empty = backgroundCapture($key, 'Runa', $h3 + 100_000_000, $stateDirectory);
    backgroundCheck(($empty['status'] ?? null) === 'empty', 'player-only surroundings should be a valid known-empty report');
    $emptyObservation = backgroundObservation(backgroundLogEntries($logDirectory), 'background_capture', 'empty');
    backgroundCheck(($emptyObservation['reason'] ?? null) === null
        && ($emptyObservation['context']['actor_count'] ?? null) === 0,
        'A known-empty roster should be distinguished from stale or unavailable data.');
    backgroundCheck(pcv_read_eligible_npcs($key, backgroundCatalog([]), 'Runa', $stateDirectory)['status'] === 'empty',
        'fresh player-only report should remain distinct from missing or stale');

    $duplicate = backgroundCapture($key, 'Aela/aela/Runa', $h3 + 200_000_000, $stateDirectory);
    backgroundCheck(($duplicate['status'] ?? null) === 'ready', 'duplicate names should not invalidate the whole bounded report');
    $duplicateRead = pcv_read_eligible_npcs($key, backgroundCatalog(['Aela' => $h3 + 150_000_000]), 'Runa', $stateDirectory);
    backgroundCheck(($duplicateRead['status'] ?? null) === 'stale' && ($duplicateRead['known_npcs'] ?? []) === [],
        'ambiguous duplicate runtime names must remain unknown, not become an empty roster');

    $otherKey = hash('sha256', 'different background playthrough');
    $reset = backgroundCapture($otherKey, $report, $h0, $stateDirectory);
    backgroundCheck(($reset['status'] ?? null) === 'baseline', 'a playthrough change should start a new baseline');
    $oldCatalog = backgroundCatalog(['Aela' => $h2 + 150_000_000]);
    $oldRead = pcv_read_eligible_npcs($otherKey, $oldCatalog, 'Runa', $stateDirectory);
    backgroundCheck(($oldRead['status'] ?? null) === 'stale', 'timestamps from a prior clock segment must not cross a new baseline');
    backgroundCheck(pcv_read_eligible_npcs($key, $oldCatalog, 'Runa', $stateDirectory)['reason'] === 'presence_key_mismatch',
        'a previous playthrough must not reuse the new report');

    $elapsedHeartbeat = $h0 + 50_000_000_000;
    backgroundCapture($otherKey, $report, $elapsedHeartbeat, $stateDirectory);
    $slowStatus = backgroundCatalog(['Aela' => $h0 + 1_000_000_000]);
    backgroundCheck(pcv_read_eligible_npcs($otherKey, $slowStatus, 'Runa', $stateDirectory)['status'] === 'stale',
        'native status age beyond the 45-second source window must be unknown');

    $rewindPath = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    $rewindPath['observed_at'] = time() - 46;
    file_put_contents($path, json_encode($rewindPath, JSON_THROW_ON_ERROR));
    $rewind = backgroundCapture($otherKey, $report, 3, $stateDirectory);
    backgroundCheck(($rewind['status'] ?? null) === 'baseline', 'a clock rewind may reset only after the prior receipt lease expires');
    $rewound = json_decode((string)file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    backgroundCheck(($rewound['baseline_timestamp'] ?? null) === 3,
        'a reset report must establish a fresh baseline without carrying old membership');
    backgroundCheck(pcv_read_eligible_npcs($otherKey, $oldCatalog, 'Runa', $stateDirectory)['status'] === 'stale',
        'old high timestamps must not become eligible after a clock rewind');

    $mismatch = backgroundCapture($otherKey, 'Aela/Other Player', 4, $stateDirectory);
    backgroundCheck(($mismatch['status'] ?? null) === 'unavailable', 'a report without the current player identity must fail closed');
    backgroundCheck(pcv_read_eligible_npcs($otherKey, $oldCatalog, 'Runa', $stateDirectory)['status'] === 'unavailable',
        'an invalid matching event must shadow all older reports');

    $legacyDirectory = $stateDirectory . '-legacy';
    mkdir($legacyDirectory, 0700);
    file_put_contents($legacyDirectory . DIRECTORY_SEPARATOR . 'presence.json', json_encode([
        'version' => 1, 'key' => $key, 'observed_at' => time(), 'radius' => 1000,
        'actors' => [['name' => 'Aela', 'distance' => 10]],
    ], JSON_THROW_ON_ERROR));
    $backgroundOnly = pcv_read_eligible_npcs($key, backgroundCatalog([]), 'Runa', $legacyDirectory);
    backgroundCheck(($backgroundOnly['status'] ?? null) === 'missing', 'legacy request and companion caches must not stand in for the background feed');
    $missingObservation = backgroundObservation(backgroundLogEntries($logDirectory), 'background_read', 'unavailable');
    backgroundCheck(($missingObservation['reason'] ?? null) === 'presence_missing'
        && ($missingObservation['context']['actor_count'] ?? null) === 0,
        'A missing feed must be reported as unavailable, distinct from an observed empty roster.');
    $presenceLogText = (string)@file_get_contents($logDirectory . DIRECTORY_SEPARATOR . 'events.jsonl');
    backgroundCheck(!str_contains($presenceLogText, 'Aela') && !str_contains($presenceLogText, 'Faendal')
        && !str_contains($presenceLogText, 'Runa'),
        'Presence diagnostics may report bounded counts and reasons but never roster or player names.');

    echo "PASS: heartbeat baseline, monotonic receipt, bounded status join, empty versus stale, identity reset, and no legacy fallback\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    backgroundCleanup($stateDirectory, $logDirectory);
    $legacyDirectory = $stateDirectory . '-legacy';
    foreach (['presence.json', 'background_presence.json', 'state.lock'] as $name) {
        @unlink($legacyDirectory . DIRECTORY_SEPARATOR . $name);
    }
    @rmdir($legacyDirectory);
}
exit($exitCode);
