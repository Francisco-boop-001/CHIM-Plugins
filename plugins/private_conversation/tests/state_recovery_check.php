<?php
declare(strict_types=1);

// 0.1.13 E2: END recovers an unreadable state file (preserving the bad copy); ARM still refuses to overwrite it.
// 0.1.13 E3: a readable store with no active or pending scene does not count as stored state, so an identity
// hiccup no longer blocks ordinary input; unreadable or scene-holding stores still do.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function recCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$stateDirectory = sys_get_temp_dir() . '/pcv-recovery-' . bin2hex(random_bytes(8));
$logDirectory = sys_get_temp_dir() . '/pcv-recovery-log-' . bin2hex(random_bytes(8));
$identityDirectories = [];
$exitCode = 0;
try {
    recCheck(mkdir($stateDirectory, 0700) && mkdir($logDirectory, 0700), 'could not create fixture directories');
    recCheck(pcv_log_set_test_directory($logDirectory), 'could not select isolated logger fixture');
    $known = ['404' => 'Stalleo', '505' => 'Guard One'];
    $pair = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '404', 'actor_b' => '505', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $key = hash('sha256', 'recovery playthrough');

    // E2: ARM on a corrupt store refuses and leaves it intact; END recovers it.
    file_put_contents($stateDirectory . '/state.json', '{broken json');
    recCheck((pcv_stage($key, $pair, $known, $stateDirectory)['status'] ?? null) === 'unavailable', 'ARM refuses a corrupt store.');
    recCheck((string)file_get_contents($stateDirectory . '/state.json') === '{broken json', 'ARM never overwrites a corrupt store.');
    $ended = pcv_stage($key, ['enabled' => false], $known, $stateDirectory);
    echo 'end=' . json_encode($ended['status'] ?? null) . "\n";
    recCheck(($ended['status'] ?? null) === 'off', 'END on a corrupt store succeeds.');
    recCheck((pcv_read($key, $stateDirectory)['status'] ?? null) === 'off', 'The store reads as off afterwards.');
    $copies = glob($stateDirectory . '/state.json.corrupt-*') ?: [];
    recCheck(count($copies) === 1 && (string)file_get_contents($copies[0]) === '{broken json', 'The bad file is preserved.');
    recCheck((pcv_stage($key, $pair, $known, $stateDirectory)['status'] ?? null) === 'pending', 'ARM works again after recovery.');

    // E2: a schema-invalid store is recovered the same way.
    file_put_contents($stateDirectory . '/state.json', json_encode(['version' => 999]));
    recCheck((pcv_stage($key, ['enabled' => false], $known, $stateDirectory)['status'] ?? null) === 'off', 'END recovers an invalid store.');

    // E2: a non-regular state.json is not "recovered".
    $odd = $stateDirectory . '-odd';
    mkdir($odd, 0700);
    mkdir($odd . '/state.json', 0700);
    recCheck((pcv_stage($key, ['enabled' => false], $known, $odd)['status'] ?? null) === 'unavailable', 'A directory named state.json is left alone.');
    recCheck(pcvScopeStoredStateExists($odd), 'A non-regular state file remains fail closed for unknown identity.');
    rmdir($odd . '/state.json');
    @unlink($odd . '/state.lock');
    rmdir($odd);

    $entries = array_values(array_filter(array_map(static fn($line) => json_decode($line, true), file($logDirectory . '/events.jsonl') ?: []), 'is_array'));
    $recovered = array_values(array_filter($entries, static fn($e) => ($e['event'] ?? null) === 'state.store_recovered'));
    recCheck(count($recovered) === 2 && ($recovered[0]['reason'] ?? null) === 'invalid_json' && ($recovered[1]['reason'] ?? null) === 'invalid_state'
        && ($recovered[0]['severity'] ?? null) === 'warning', 'Each recovery is logged as a warning with its reason.');

    // E3: an empty readable store is not "stored state"; a scene-holding or unreadable one is.
    $empty = $stateDirectory . '-empty';
    mkdir($empty, 0700);
    file_put_contents($empty . '/state.json', json_encode(pcv_empty_store($key)));
    recCheck(!pcvScopeStoredStateExists($empty), 'A store with no active or pending scene does not block an identity hiccup.');
    pcv_stage($key, $pair, $known, $empty);
    recCheck(pcvScopeStoredStateExists($empty), 'A store holding a pending scene still counts.');
    file_put_contents($empty . '/state.json', '{broken');
    recCheck(pcvScopeStoredStateExists($empty), 'An unreadable store still counts (fail closed).');
    foreach (glob($empty . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($empty);

    // E3: an expired-only but readable store is not blocking state when the playthrough identity is unknown.
    $expiredDirectory = $stateDirectory . '-expired';
    $expiredPendingDirectory = $stateDirectory . '-expired-pending';
    $liveDirectory = $stateDirectory . '-live';
    $mixedActiveDirectory = $stateDirectory . '-mixed-active';
    $mixedPendingDirectory = $stateDirectory . '-mixed-pending';
    $unsafeDirectory = $stateDirectory . '-unsafe';
    $identityDirectories = [$expiredDirectory, $expiredPendingDirectory, $liveDirectory,
        $mixedActiveDirectory, $mixedPendingDirectory, $unsafeDirectory];
    foreach ($identityDirectories as $directory) {
        recCheck(mkdir($directory, 0700), 'could not create identity-state fixture');
    }
    recCheck((pcv_stage($key, $pair, $known, $expiredDirectory)['status'] ?? null) === 'pending',
        'Could not stage a valid fixture scene.');
    recCheck((pcv_begin_request($key, true, $expiredDirectory, $known)['status'] ?? null) === 'active',
        'Could not activate a valid fixture scene.');

    $activePath = $expiredDirectory . '/state.json';
    $activeContents = file_get_contents($activePath);
    recCheck(is_string($activeContents), 'Could not read the active fixture state.');
    recCheck(file_put_contents($liveDirectory . '/state.json', $activeContents) === strlen($activeContents)
        && pcvScopeStoredStateExists($liveDirectory), 'A live active scene must still block unknown identity.');
    recCheck(file_put_contents($unsafeDirectory . '/state.json', $activeContents) === strlen($activeContents)
        && symlink($unsafeDirectory . '/state.json', $unsafeDirectory . '/state.lock')
        && pcvScopeStoredStateExists($unsafeDirectory), 'A lock-symlink failure must remain fail closed.');

    $expiredStore = json_decode($activeContents, true, 32, JSON_THROW_ON_ERROR);
    $expiresAt = time() - 60;
    $expiredStore['active']['activated_at'] = $expiresAt - PCV_ACTIVE_TTL - 60;
    $expiredStore['active']['renewed_at'] = $expiresAt - PCV_ACTIVE_TTL;
    $expiredStore['active']['expires_at'] = $expiresAt;
    recCheck(pcv_valid_stored_state($expiredStore), 'The expired-only fixture is not schema-valid.');
    $expiredPath = $expiredDirectory . '/state.json';
    $expiredContents = json_encode($expiredStore, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    recCheck(file_put_contents($expiredPath, $expiredContents) === strlen($expiredContents), 'Could not write expired fixture state.');
    touch($expiredPath, time() - 30);
    clearstatcache(true, $expiredPath);
    $beforeContents = file_get_contents($expiredPath);
    $beforeMtime = filemtime($expiredPath);
    recCheck(!pcvScopeStoredStateExists($expiredDirectory),
        'A readable store containing only an expired active entry must not block an unknown playthrough.');
    clearstatcache(true, $expiredPath);
    recCheck(file_get_contents($expiredPath) === $beforeContents && filemtime($expiredPath) === $beforeMtime,
        'Checking an expired-only store must not rewrite state for an unknown playthrough.');

    recCheck((pcv_stage($key, $pair, $known, $expiredPendingDirectory)['status'] ?? null) === 'pending',
        'Could not stage the pending-only fixture.');
    $pendingOnly = json_decode((string)file_get_contents($expiredPendingDirectory . '/state.json'), true, 32, JSON_THROW_ON_ERROR);
    $pendingExpiresAt = time() - 60;
    $pendingOnly['pending']['staged_at'] = $pendingExpiresAt - PCV_PENDING_TTL;
    $pendingOnly['pending']['expires_at'] = $pendingExpiresAt;
    recCheck(pcv_valid_stored_state($pendingOnly), 'The expired-pending fixture is not schema-valid.');
    $pendingPath = $expiredPendingDirectory . '/state.json';
    $pendingContents = json_encode($pendingOnly, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    recCheck(file_put_contents($pendingPath, $pendingContents) === strlen($pendingContents), 'Could not write expired pending fixture.');
    touch($pendingPath, time() - 30);
    clearstatcache(true, $pendingPath);
    $beforePendingMtime = filemtime($pendingPath);
    recCheck(!pcvScopeStoredStateExists($expiredPendingDirectory),
        'A readable store containing only an expired pending entry must not block an unknown playthrough.');
    clearstatcache(true, $pendingPath);
    recCheck(file_get_contents($pendingPath) === $pendingContents && filemtime($pendingPath) === $beforePendingMtime,
        'Checking an expired pending entry must not rewrite state for an unknown playthrough.');

    // Each live slot still blocks when the other slot has expired.
    recCheck((pcv_stage($key, $pair, $known, $mixedActiveDirectory)['status'] ?? null) === 'pending',
        'Could not stage the mixed-slot fixture.');
    recCheck((pcv_begin_request($key, true, $mixedActiveDirectory, $known)['status'] ?? null) === 'active',
        'Could not activate the mixed-slot fixture.');
    $mixedPendingResult = pcv_stage($key, $pair, $known, $mixedActiveDirectory);
    recCheck(($mixedPendingResult['status'] ?? null) === 'active'
        && ($mixedPendingResult['pending'] ?? false) === true
        && is_array($mixedPendingResult['pending_scope'] ?? null),
        'Could not add a pending entry alongside the live active scene.');
    $mixedLive = json_decode((string)file_get_contents($mixedActiveDirectory . '/state.json'), true, 32, JSON_THROW_ON_ERROR);
    $mixedExpiry = time() - 60;

    $mixedExpiredPending = $mixedLive;
    $mixedExpiredPending['pending']['staged_at'] = $mixedExpiry - PCV_PENDING_TTL;
    $mixedExpiredPending['pending']['expires_at'] = $mixedExpiry;
    recCheck(pcv_valid_stored_state($mixedExpiredPending), 'The live-active/expired-pending fixture is not schema-valid.');
    $mixedPendingContents = json_encode($mixedExpiredPending, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    recCheck(file_put_contents($mixedActiveDirectory . '/state.json', $mixedPendingContents) === strlen($mixedPendingContents)
        && pcvScopeStoredStateExists($mixedActiveDirectory),
        'A live active entry must block when the pending entry has expired.');

    $mixedExpiredActive = $mixedLive;
    $mixedExpiredActive['active']['activated_at'] = $mixedExpiry - PCV_ACTIVE_TTL - 60;
    $mixedExpiredActive['active']['renewed_at'] = $mixedExpiry - PCV_ACTIVE_TTL;
    $mixedExpiredActive['active']['expires_at'] = $mixedExpiry;
    recCheck(pcv_valid_stored_state($mixedExpiredActive), 'The expired-active/live-pending fixture is not schema-valid.');
    $mixedActiveContents = json_encode($mixedExpiredActive, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    recCheck(file_put_contents($mixedPendingDirectory . '/state.json', $mixedActiveContents) === strlen($mixedActiveContents)
        && pcvScopeStoredStateExists($mixedPendingDirectory),
        'A live pending entry must block when the active entry has expired.');

    $boundary = time();
    $activeAtBoundary = $expiredStore;
    $activeAtBoundary['active']['activated_at'] = $boundary - PCV_ACTIVE_TTL - 60;
    $activeAtBoundary['active']['renewed_at'] = $boundary - PCV_ACTIVE_TTL;
    $activeAtBoundary['active']['expires_at'] = $boundary;
    $pendingAtBoundary = $pendingOnly;
    $pendingAtBoundary['pending']['staged_at'] = $boundary - PCV_PENDING_TTL;
    $pendingAtBoundary['pending']['expires_at'] = $boundary;
    recCheck(pcv_valid_stored_state($activeAtBoundary) && pcv_valid_stored_state($pendingAtBoundary)
        && (pcv_visible_state($activeAtBoundary, $boundary)['status'] ?? null) === 'off'
        && (pcv_visible_state($pendingAtBoundary, $boundary)['status'] ?? null) === 'off',
        'Entries expiring exactly at the read time must be invisible.');

    echo "PASS END recovery and unknown-identity expiry/live-slot boundaries\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach (glob($stateDirectory . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($stateDirectory);
    foreach ($identityDirectories as $directory) {
        foreach (glob($directory . '/{,.}*', GLOB_BRACE) ?: [] as $path) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
    foreach (glob($logDirectory . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($logDirectory);
}
exit($exitCode);
