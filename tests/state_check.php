<?php
declare(strict_types=1);

$stateModule = dirname(__DIR__) . '/server/state.php';
if (!is_file($stateModule)) {
    fwrite(STDERR, "FAIL: expected state API module at server/state.php\n");
    exit(1);
}
define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
$logFixture = sys_get_temp_dir() . '/chim-private-conversation-log-' . bin2hex(random_bytes(8));
if (!mkdir($logFixture, 0700) || !pcv_log_set_test_directory($logFixture)) {
    fwrite(STDERR, "FAIL: could not prepare isolated logging fixture\n");
    exit(1);
}
require_once $stateModule;

function pcvCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pcvCheckState(
    array $actual,
    string $status,
    ?array $scope,
    bool $pending,
    string $label,
    ?array $pendingScope = null
): void
{
    pcvCheck(($actual['status'] ?? null) === $status, "$label status mismatch");
    pcvCheck(($actual['scope'] ?? null) === $scope, "$label scope mismatch");
    pcvCheck(($actual['pending'] ?? null) === $pending, "$label pending mismatch");
    pcvCheck(array_key_exists('pending_scope', $actual) && $actual['pending_scope'] === $pendingScope,
        "$label pending scope mismatch");
}

function pcvCleanFixture(string $directory): void
{
    @unlink($directory . '/state.json');
    @unlink($directory . '/state.lock');
    @rmdir($directory);
}

function pcvCleanLogFixture(string $directory): void
{
    foreach (['events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl', 'events.lock'] as $name) {
        @unlink($directory . '/' . $name);
    }
    @rmdir($directory);
}

$fixture = sys_get_temp_dir() . '/chim-private-conversation-' . bin2hex(random_bytes(8));
$isolatedLockState = $fixture . '-logger-lock';
$oldErrorLog = ini_get('error_log');
$validCharacterId = str_repeat('a', 32);
$invalidCharacterId = 'not-a-character-id';
$homeState = [
    'available' => true,
    'active_id' => 17,
    'playthroughs' => [[
        'id' => 17,
        'active' => true,
        'character_id' => $validCharacterId,
        'player_name' => 'Runa',
    ]],
];

try {
    $identity = pcv_identity_from_home_state($homeState);
    pcvCheck(is_string($identity['key'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $identity['key']) === 1,
        'valid active profile and character should produce a SHA-256 key');
    pcvCheck(($identity['player_name'] ?? null) === 'Runa', 'identity adapter should expose the active player name for UI filtering');

    $renamed = $homeState;
    $renamed['playthroughs'][0]['player_name'] = 'Different Display Name';
    pcvCheck(pcv_identity_from_home_state($renamed)['key'] === $identity['key'], 'player name must not affect the key');

    $noActive = $homeState;
    $noActive['active_id'] = 0;
    pcvCheck(pcv_identity_from_home_state($noActive)['key'] === null, 'missing active profile must be unavailable');

    $badCharacter = $homeState;
    $badCharacter['playthroughs'][0]['character_id'] = $invalidCharacterId;
    pcvCheck(pcv_identity_from_home_state($badCharacter)['key'] === null, 'invalid live character ID must be unavailable');

    $ambiguous = $homeState;
    $ambiguous['playthroughs'][] = ['id' => 18, 'active' => true, 'character_id' => str_repeat('b', 32), 'player_name' => 'Runa'];
    pcvCheck(pcv_identity_from_home_state($ambiguous)['key'] === null, 'multiple active profiles must be unavailable');

    $unprofiled = pcv_identity_from_home_state([], false, ' Runa ');
    pcvCheck(($unprofiled['key'] ?? null) === hash('sha256', 'unprofiled:runa')
        && ($unprofiled['player_name'] ?? null) === 'Runa',
        'an absent optional profile table should use a normalized shared-server identity and core player name');
    $zeroProfiles = pcv_identity_from_home_state([
        'available' => true,
        'active_id' => 0,
        'playthroughs' => [],
    ], true, 'Runa');
    pcvCheck(($zeroProfiles['key'] ?? null) === hash('sha256', 'unprofiled:runa')
        && ($zeroProfiles['player_name'] ?? null) === 'Runa',
        'a verified zero-active-profile result should use shared-server identity');
    pcvCheck(pcv_identity_from_home_state([], false, 'Other Player')['key'] !== $unprofiled['key'],
        'shared-server settings must not cross validated core player identities');
    pcvCheck(pcv_identity_from_home_state([], false, null)['key'] === null,
        'shared-server identity must fail closed without a current core player name');
    pcvCheck(pcv_identity_from_home_state([], false, 'Run' . "\n" . 'a')['key'] === null,
        'shared-server identity must reject control characters in the core player name');
    pcvCheck(pcv_identity_from_home_state(['available' => false], true, 'Runa')['key'] === null,
        'an unavailable profile lookup must not fall back to shared-server identity');
    pcvCheck(pcv_identity_from_home_state([
        'available' => true,
        'active_id' => 0,
        'playthroughs' => [['id' => 17, 'active' => true, 'character_id' => $validCharacterId, 'player_name' => 'Runa']],
    ], true, 'Runa')['key'] === null,
        'an inconsistent active-profile result must not be relabelled shared-server');

    $key = $identity['key'];
    pcv_log_set_playthrough_ref($key);
    $otherKey = hash('sha256', 'another active playthrough');
    $knownNpcs = ['101' => 'Aela the Huntress', '202' => 'Faendal'];
    $a = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $b = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '202', 'actor_b' => '101', 'exclude_player' => false, 'bystander_mode' => 'silent'];

    ini_set('error_log', $logFixture . '/fallback.log');
    pcvCheck(@mkdir($isolatedLockState, 0700), 'could not create isolated lock-contention state directory');
    $logLock = fopen($logFixture . '/events.lock', 'c');
    pcvCheck(is_resource($logLock) && flock($logLock, LOCK_EX | LOCK_NB), 'could not hold logger lock for state-transition check');
    $transitionDuringLogFailure = pcv_stage($otherKey, $a, $knownNpcs, $isolatedLockState);
    flock($logLock, LOCK_UN);
    fclose($logLock);
    pcvCheckState($transitionDuringLogFailure, 'pending', null, true,
        'scope staging must succeed while logger lock is held', $a);
    pcvCleanFixture($isolatedLockState);

    pcvCheckState(pcv_read($key, $fixture), 'off', null, false, 'missing state');
    $stagedA = pcv_stage($key, $a, $knownNpcs, $fixture);
    pcvCheckState($stagedA, 'pending', null, true, 'new config stages without activating', $a);
    $configA = $stagedA['pending_config_id'] ?? null;
    pcvCheck(is_string($configA) && pcv_log_valid_uuid($configA), 'staged config should persist a UUID correlation ID');
    pcvCheck(($stagedA['config_id'] ?? null) === null, 'pending config is not reported as active');
    pcvCheckState(pcv_begin_request($key, false, $fixture), 'pending', null, true, 'ineligible request', $a);

    $activeA = $a;
    $activatedA = pcv_begin_request($key, true, $fixture, $knownNpcs);
    pcvCheckState($activatedA, 'active', $activeA, false, 'eligible request applies pending config');
    pcvCheck(($activatedA['config_id'] ?? null) === $configA, 'activation should preserve the staged config ID');
    pcvCheck(($activatedA['pending_config_id'] ?? null) === null, 'activated config is no longer pending');
    pcvCheck((pcv_read($key, $fixture)['config_id'] ?? null) === $configA, 'read should preserve the active config ID');
    pcvCheckState(pcv_read($otherKey, $fixture), 'off', null, false, 'different playthrough must not read active scope');

    $stagedB = pcv_stage($key, $b, $knownNpcs, $fixture);
    pcvCheckState($stagedB, 'active', $activeA, true, 'pending replacement leaves active scope unchanged', $b);
    $configB = $stagedB['pending_config_id'] ?? null;
    pcvCheck(is_string($configB) && pcv_log_valid_uuid($configB) && $configB !== $configA, 'replacement staging should receive a distinct config ID');
    pcvCheck(($stagedB['config_id'] ?? null) === $configA, 'staging must preserve current active config ID');
    pcvCheckState(pcv_begin_request($key, false, $fixture, $knownNpcs), 'active', $activeA, true, 'rechat leaves pending config untouched', $b);
    $activatedB = pcv_begin_request($key, true, $fixture, $knownNpcs);
    pcvCheckState($activatedB, 'active', $b, false, 'next eligible request applies replacement');
    pcvCheck(($activatedB['config_id'] ?? null) === $configB, 'replacement activation should preserve its staged ID');

    $stored = json_decode((string)file_get_contents($fixture . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    unset($stored['active']['config_id']);
    unset($stored['active']['config']['scene_mode']);
    file_put_contents($fixture . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    $legacyActive = pcv_read($key, $fixture);
    pcvCheckState($legacyActive, 'active', $b, false, 'legacy v1 pair state without config ID or scene mode remains active');
    pcvCheck(($legacyActive['config_id'] ?? null) === null, 'legacy active state is surfaced as uncorrelated');
    $stored['active']['config_id'] = 'malformed-id';
    file_put_contents($fixture . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    $malformedOptionalId = pcv_read($key, $fixture);
    pcvCheckState($malformedOptionalId, 'active', $b, false, 'malformed optional ID does not invalidate legacy scope');
    pcvCheck(($malformedOptionalId['config_id'] ?? null) === null, 'malformed optional ID should normalize to null');

    $pcvStageDisabled = ['enabled' => false];
    $disabledScope = ['enabled' => false, 'actor_a' => '', 'actor_b' => '', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $ended = pcv_stage($key, $pcvStageDisabled, [], $fixture);
    pcvCheckState($ended, 'active', $b, true, 'END remains pending before eligible input', $disabledScope);
    $endConfigId = $ended['pending_config_id'] ?? null;
    pcvCheck(is_string($endConfigId) && pcv_log_valid_uuid($endConfigId), 'pending END should have its own correlation ID');
    pcvCheckState(pcv_begin_request($key, false, $fixture, $knownNpcs), 'active', $b, true, 'ineligible request cannot apply END', $disabledScope);
    $endedResult = pcv_begin_request($key, true, $fixture);
    pcvCheckState($endedResult, 'off', null, false, 'eligible input applies END');
    pcvCheck(($endedResult['config_id'] ?? null) === null && ($endedResult['pending_config_id'] ?? null) === null,
        'completed END should return no active or pending config ID');

    pcv_stage($key, $a, $knownNpcs, $fixture);
    $beforeSwitch = pcv_begin_request($key, true, $fixture, $knownNpcs);
    pcvCheckState($beforeSwitch, 'active', $a, false, 'reactivate before playthrough switch');
    $beforeSwitchId = $beforeSwitch['config_id'] ?? null;
    pcvCheckState(pcv_begin_request($otherKey, false, $fixture), 'off', null, false, 'request for another playthrough invalidates prior state');
    pcvCheckState(pcv_begin_request($key, true, $fixture), 'off', null, false, 'switching back cannot revive stale active state');

    $entriesBeforeCorruption = array_map(
        static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR),
        file((string)pcv_log_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    );
    $invalidationLogged = false;
    foreach ($entriesBeforeCorruption as $entry) {
        if (($entry['event'] ?? null) === 'state.scope_invalidated'
            && ($entry['reason'] ?? null) === 'playthrough_changed'
            && ($entry['context']['active_config_id'] ?? null) === $beforeSwitchId) {
            $invalidationLogged = true;
            break;
        }
    }
    pcvCheck($invalidationLogged, 'playthrough reset should log the prior active config ID');

    pcv_stage($key, $a, $knownNpcs, $fixture);
    pcv_begin_request($key, true, $fixture, $knownNpcs);
    $stored = json_decode((string)file_get_contents($fixture . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    $expiredAt = time() - 1;
    $stored['active']['activated_at'] = $expiredAt - 3600;
    $stored['active']['expires_at'] = $expiredAt;
    file_put_contents($fixture . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    pcvCheckState(pcv_stage($key, $b, $knownNpcs, $fixture), 'pending', null, true,
        'staging clears expired active scope', $b);
    $entriesAfterExpiry = array_map(
        static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR),
        file((string)pcv_log_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    );
    $stageExpiryLogged = false;
    foreach ($entriesAfterExpiry as $entry) {
        if (($entry['event'] ?? null) === 'state.scope_expired'
            && ($entry['reason'] ?? null) === 'active_ttl'
            && ($entry['context']['target'] ?? null) === 'active') {
            $stageExpiryLogged = true;
            break;
        }
    }
    pcvCheck($stageExpiryLogged, 'staging should record expired active scope after successful write');

    pcv_stage($key, $a, $knownNpcs, $fixture);
    $stored = json_decode((string)file_get_contents($fixture . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    $expiredAt = time() - 1;
    $stored['pending']['staged_at'] = $expiredAt - 900;
    $stored['pending']['expires_at'] = $expiredAt;
    file_put_contents($fixture . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    pcvCheckState(pcv_read($key, $fixture), 'off', null, false, 'expired pending state is not reported as armed');
    pcvCheckState(pcv_begin_request($key, false, $fixture), 'off', null, false, 'ineligible request clears but never applies expired pending state');

    $invalidPairs = [
        ['enabled' => true, 'actor_a' => '101', 'actor_b' => 'missing', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
        ['enabled' => true, 'actor_a' => '101', 'actor_b' => '101', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
        ['enabled' => true, 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'include'],
    ];
    foreach ($invalidPairs as $bad) {
        try {
            pcv_stage($key, $bad, $knownNpcs, $fixture);
            throw new RuntimeException('invalid desired config was accepted');
        } catch (InvalidArgumentException) {
        }
    }
    try {
        pcv_stage($key, array_replace($a, ['scene_mode' => 'unknown']), $knownNpcs, $fixture);
        throw new RuntimeException('unknown scene mode was accepted');
    } catch (InvalidArgumentException) {
    }

    pcv_stage($key, $a, $knownNpcs, $fixture);
    pcv_begin_request($key, true, $fixture, $knownNpcs);
    $stored = json_decode((string)file_get_contents($fixture . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    pcvCheck(!str_contains(json_encode($stored, JSON_THROW_ON_ERROR), 'Aela the Huntress'), 'display names must not be persisted');
    pcvCheck(is_file($fixture . '/state.lock'), 'all state operations must use the stable lock file');
    $expiredAt = time() - 1;
    $stored['active']['activated_at'] = $expiredAt - 3600;
    $stored['active']['expires_at'] = $expiredAt;
    file_put_contents($fixture . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    pcvCheckState(pcv_read($key, $fixture), 'off', null, false, 'expired active state');

    file_put_contents($fixture . '/state.json', '{broken json');
    pcvCheckState(pcv_read($key, $fixture), 'unavailable', null, false, 'corrupt state');
    pcvCheckState(pcv_begin_request($key, true, $fixture), 'unavailable', null, false, 'request boundary fails closed on corrupt state');
    $corruptBytes = (string)file_get_contents($fixture . '/state.json');
    pcvCheckState(pcv_stage($key, $a, $knownNpcs, $fixture), 'unavailable', null, false, 'staging fails closed on corrupt state');
    pcvCheck((string)file_get_contents($fixture . '/state.json') === $corruptBytes, 'staging must not overwrite corrupt state');

    $sceneFixture = $fixture . '-scene-mode';
    $soloInput = [
        'enabled' => true,
        'scene_mode' => 'solo',
        'actor_a' => '101',
        'actor_b' => ['ignored in solo mode'],
        'exclude_player' => false,
        'bystander_mode' => 'silent',
    ];
    $solo = [
        'enabled' => true,
        'scene_mode' => 'solo',
        'actor_a' => '101',
        'actor_b' => null,
        'exclude_player' => true,
        'bystander_mode' => 'silent',
    ];
    $legacyPair = [
        'enabled' => true,
        'actor_a' => '101',
        'actor_b' => '202',
        'exclude_player' => true,
        'bystander_mode' => 'exclude',
    ];
    $legacyPairState = pcv_stage($key, $legacyPair, $knownNpcs, $sceneFixture);
    $canonicalPair = [
        'enabled' => true,
        'scene_mode' => 'pair',
        'actor_a' => '101',
        'actor_b' => '202',
        'exclude_player' => true,
        'bystander_mode' => 'exclude',
    ];
    pcvCheck(($legacyPairState['pending_scope'] ?? null) === $canonicalPair,
        'new configurations without a mode should be stored and exposed as pair');
    $soloStaged = pcv_stage($key, $soloInput, $knownNpcs, $sceneFixture);
    pcvCheck(($soloStaged['pending_scope'] ?? null) === $solo,
        'solo staging should discard actor B and force player exclusion');
    $soloActivated = pcv_begin_request($key, true, $sceneFixture, ['101' => 'Aela']);
    pcvCheck(($soloActivated['status'] ?? null) === 'active'
        && ($soloActivated['scope'] ?? null) === $solo,
        'solo activation should require only actor A in the current eligible map');
    $soloConfigId = $soloActivated['config_id'] ?? null;
    $sameKeyStale = pcv_begin_request($key, false, $sceneFixture, []);
    pcvCheck(($sameKeyStale['status'] ?? null) === 'unavailable'
        && ($sameKeyStale['config_id'] ?? null) === $soloConfigId,
        'same-key state should remain configured while stale or empty eligibility prevents use');
    $sameKeyFresh = pcv_begin_request($key, false, $sceneFixture, ['101' => 'Aela']);
    pcvCheck(($sameKeyFresh['status'] ?? null) === 'active'
        && ($sameKeyFresh['scope'] ?? null) === $solo
        && ($sameKeyFresh['config_id'] ?? null) === $soloConfigId,
        'same-key state should be usable again when the current actor evidence is fresh');
    $sceneEvents = array_map(
        static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR),
        file((string)pcv_log_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    );
    $ineligibleSkips = array_values(array_filter($sceneEvents, static fn(array $entry): bool =>
        ($entry['event'] ?? null) === 'state.scope_skipped'
        && ($entry['reason'] ?? null) === 'scene_not_eligible'));
    pcvCheck(count($ineligibleSkips) === 1 && ($ineligibleSkips[0]['severity'] ?? null) === 'info',
        'expected ineligibility should emit one informational skip');
    pcvCleanFixture($sceneFixture);

    $logPath = pcv_log_path();
    pcvCheck(is_string($logPath) && is_file($logPath), 'state lifecycle events should use the isolated log fixture');
    $entries = array_map(
        static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR),
        file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    );
    $endActivationLogged = false;
    foreach ($entries as $entry) {
        if (($entry['event'] ?? null) === 'state.scope_activated' && ($entry['config_id'] ?? null) === $endConfigId) {
            $endActivationLogged = true;
            break;
        }
    }
    pcvCheck($endActivationLogged, 'END activation event should retain its pending config ID');
    pcvCheck(!str_contains((string)file_get_contents($logPath), 'Aela the Huntress'), 'operational logs must not contain NPC names');
    pcvCheck(!str_contains((string)file_get_contents($logPath), $key), 'operational logs must not contain the state key');

    echo "PASS: identity, config correlation, legacy compatibility, invalidation/expiry logs, corruption preservation and isolated state logs\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    pcvCleanFixture($fixture);
    pcvCleanFixture($isolatedLockState);
    pcvCleanLogFixture($logFixture);
    if (is_string($oldErrorLog)) {
        ini_set('error_log', $oldErrorLog);
    }
}

exit($exitCode ?? 0);
