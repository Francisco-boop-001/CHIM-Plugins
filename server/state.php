<?php
declare(strict_types=1);

require_once __DIR__ . '/log.php';

const PCV_STATE_VERSION = 1;
const PCV_PENDING_TTL = 900;
const PCV_ACTIVE_TTL = 3600;
const PCV_PRESENCE_TTL = 45;
const PCV_BACKGROUND_PRESENCE_VERSION = 1;

function pcv_shared_server_identity($playerName): array
{
    $name = is_string($playerName) ? trim($playerName) : '';
    if ($name === '' || strlen($name) > 256 || preg_match('//u', $name) !== 1
        || preg_match('/[\x00-\x1f\x7f]/', $name) === 1) {
        return ['key' => null, 'player_name' => null];
    }
    $identityName = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    return ['key' => hash('sha256', 'unprofiled:' . $identityName), 'player_name' => $name];
}

/** A verified empty profile result is distinct from an unavailable helper read. */
function pcv_home_state_has_no_active_profile(array $homeState): bool
{
    $activeId = filter_var($homeState['active_id'] ?? null, FILTER_VALIDATE_INT);
    $profiles = $homeState['playthroughs'] ?? null;
    if (($homeState['available'] ?? false) !== true || $activeId !== 0
        || !is_array($profiles)) {
        return false;
    }
    foreach ($profiles as $profile) {
        if (!is_array($profile) || !is_bool($profile['active'] ?? null) || $profile['active']) {
            return false;
        }
    }
    return true;
}

/** Derive a key from verified profile state, or an explicit shared-server scope. */
function pcv_identity_from_home_state(
    array $homeState,
    bool $profileTableExists = true,
    ?string $corePlayerName = null
): array {
    $unavailable = ['key' => null, 'player_name' => null];
    if (!$profileTableExists || pcv_home_state_has_no_active_profile($homeState)) {
        return pcv_shared_server_identity($corePlayerName);
    }
    if (($homeState['available'] ?? false) !== true) {
        return $unavailable;
    }

    $profileId = filter_var($homeState['active_id'] ?? null, FILTER_VALIDATE_INT);
    $profiles = $homeState['playthroughs'] ?? null;
    if ($profileId === false || $profileId < 1 || !is_array($profiles)) {
        return $unavailable;
    }

    $active = [];
    foreach ($profiles as $profile) {
        if (!is_array($profile) || !is_bool($profile['active'] ?? null)) {
            return $unavailable;
        }
        if ($profile['active']) {
            $active[] = $profile;
        }
    }
    if (count($active) !== 1 || (int)($active[0]['id'] ?? 0) !== $profileId) {
        return $unavailable;
    }

    $characterId = $active[0]['character_id'] ?? null;
    if (!is_string($characterId) || preg_match('/^[a-f0-9]{32}$/D', $characterId) !== 1) {
        return $unavailable;
    }

    $playerName = $active[0]['player_name'] ?? null;
    $playerName = is_string($playerName) ? trim($playerName) : '';
    if ($playerName === '' || preg_match('//u', $playerName) !== 1) {
        $playerName = null;
    }

    return [
        'key' => hash('sha256', $profileId . ':' . $characterId),
        'player_name' => $playerName,
    ];
}

function pcv_profile_table_exists($connection): bool
{
    if (!function_exists('pg_query_params') || !function_exists('pg_num_rows') || !function_exists('pg_fetch_assoc')) {
        throw new RuntimeException('Core profile lookup is unavailable.');
    }
    $result = @pg_query_params($connection,
        "SELECT to_regclass('chim_meta.playthrough_profiles') AS profile_table", []);
    if ($result === false) {
        throw new RuntimeException('Core profile lookup failed.');
    }
    try {
        if (pg_num_rows($result) !== 1) {
            throw new RuntimeException('Core profile lookup returned an invalid result.');
        }
        $row = pg_fetch_assoc($result);
        if (!is_array($row) || !array_key_exists('profile_table', $row)) {
            throw new RuntimeException('Core profile lookup returned an invalid result.');
        }
        if ($row['profile_table'] === null) {
            return false;
        }
        if (!is_string($row['profile_table']) || $row['profile_table'] === '') {
            throw new RuntimeException('Core profile lookup returned an invalid result.');
        }
        return true;
    } finally {
        @pg_free_result($result);
    }
}

function pcv_core_player_name($connection): ?string
{
    if (!function_exists('pg_query_params') || !function_exists('pg_num_rows') || !function_exists('pg_fetch_assoc')) {
        throw new RuntimeException('Core player lookup is unavailable.');
    }
    $result = @pg_query_params($connection,
        'SELECT value FROM public.core_player WHERE id = $1 LIMIT 2', ['player_name']);
    if ($result === false) {
        throw new RuntimeException('Core player lookup failed.');
    }
    try {
        if (pg_num_rows($result) !== 1) {
            return null;
        }
        $row = pg_fetch_assoc($result);
        $name = is_array($row) && is_string($row['value'] ?? null) ? trim($row['value']) : '';
        return $name !== '' && preg_match('//u', $name) === 1 ? $name : null;
    } finally {
        @pg_free_result($result);
    }
}

/** Read and cache the core's current profile or shared-server identity once per request. */
function pcv_current_identity(bool $refresh = false): array
{
    static $loaded = false;
    static $identity = ['key' => null, 'player_name' => null];
    if ($refresh) {
        $loaded = false;
        $identity = ['key' => null, 'player_name' => null];
    }
    if ($loaded) {
        return $identity;
    }
    $loaded = true;

    $engineRoot = dirname(__DIR__, 2);
    $homeHelper = $engineRoot . '/lib/playthrough_home.php';
    if (!is_file($homeHelper)) {
        return $identity;
    }

    $connection = null;
    try {
        require_once $homeHelper;
        if (!function_exists('ptp_connect')) {
            return $identity;
        }

        $connection = ptp_connect();
        if (!$connection) {
            return $identity;
        }
        $profileTableExists = pcv_profile_table_exists($connection);
        if (!$profileTableExists) {
            $identity = pcv_identity_from_home_state([], false, pcv_core_player_name($connection));
            return $identity;
        }
        if (!function_exists('pth_state')) {
            return $identity;
        }
        $homeState = pth_state($connection);
        $corePlayerName = pcv_home_state_has_no_active_profile($homeState)
            ? pcv_core_player_name($connection) : null;
        $identity = pcv_identity_from_home_state($homeState, $profileTableExists, $corePlayerName);
    } catch (Throwable $error) {
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'profile_lookup_failed', $error, ['operation' => 'identity']);
        $identity = ['key' => null, 'player_name' => null];
    } finally {
        if ($connection !== null && $connection !== false && function_exists('pg_close')) {
            @pg_close($connection);
        }
    }

    return $identity;
}

/** Return null when there is no unambiguous active profile and character identity. */
function pcv_current_playthrough_key(bool $refresh = false): ?string
{
    $key = pcv_current_identity($refresh)['key'];
    pcv_log_set_playthrough_ref($key);
    return $key;
}

/** Return the active character's display name for filtering UI choices only. */
function pcv_current_player_name(): ?string
{
    return pcv_current_identity()['player_name'];
}

function pcv_valid_key(string $key): bool
{
    return preg_match('/^[a-f0-9]{64}$/D', $key) === 1;
}

function pcv_valid_config_id($configId): bool
{
    return $configId === null || (is_string($configId) && pcv_log_valid_uuid($configId));
}

function pcv_state_directory(?string $stateDirectory): string
{
    $directory = $stateDirectory ?? (__DIR__ . '/state');
    if ($directory === '' || str_contains($directory, "\0")) {
        throw new InvalidArgumentException('Invalid state directory.');
    }
    return rtrim($directory, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
}

/** Return null for a missing, not-yet-created state directory. */
function pcv_lock_state(string $directory, bool $create, int $mode)
{
    if (is_link($directory)) {
        throw new RuntimeException('State directory is not safe.');
    }
    if (!is_dir($directory)) {
        if (!$create) {
            return null;
        }
        if (!@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('State directory is unavailable.');
        }
        @chmod($directory, 0770);
    }

    $lockPath = $directory . DIRECTORY_SEPARATOR . 'state.lock';
    if (is_link($lockPath)) {
        throw new RuntimeException('State lock is not safe.');
    }
    $handle = @fopen($lockPath, 'c');
    if ($handle === false) {
        throw new RuntimeException('State lock is unavailable.');
    }
    if (!flock($handle, $mode)) {
        fclose($handle);
        throw new RuntimeException('State lock is unavailable.');
    }
    @chmod($lockPath, 0660);
    return $handle;
}

function pcv_unlock_state($handle): void
{
    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function pcv_empty_store(string $key): array
{
    return ['version' => PCV_STATE_VERSION, 'key' => $key, 'active' => null, 'pending' => null];
}

function pcv_valid_config($config, bool $allowDisabled): bool
{
    $sceneMode = is_array($config) && array_key_exists('scene_mode', $config)
        ? $config['scene_mode'] : (is_array($config) ? 'pair' : null);
    if (!is_array($config)
        || !is_bool($config['enabled'] ?? null)
        || !is_bool($config['exclude_player'] ?? null)
        || !in_array($config['bystander_mode'] ?? null, ['exclude', 'silent'], true)
        || !in_array($sceneMode, ['pair', 'solo'], true)) {
        return false;
    }

    if (!$config['enabled']) {
        return $allowDisabled
            && $sceneMode === 'pair'
            && ($config['actor_a'] ?? null) === ''
            && ($config['actor_b'] ?? null) === '';
    }

    $actorA = $config['actor_a'] ?? null;
    $actorB = $config['actor_b'] ?? null;
    $validActorA = is_string($actorA) && $actorA !== '' && strlen($actorA) <= 256
        && preg_match('//u', $actorA) === 1 && preg_match('/[\x00-\x1f\x7f]/', $actorA) !== 1;
    if (!$validActorA) {
        return false;
    }
    if ($sceneMode === 'solo') {
        return array_key_exists('actor_b', $config) && $actorB === null && $config['exclude_player'] === true;
    }
    return is_string($actorB) && $actorB !== '' && strlen($actorB) <= 256
        && $actorA !== $actorB
        && preg_match('//u', $actorB) === 1
        && preg_match('/[\x00-\x1f\x7f]/', $actorB) !== 1;
}

function pcv_valid_stored_state(array $state): bool
{
    if (($state['version'] ?? null) !== PCV_STATE_VERSION || !pcv_valid_key($state['key'] ?? '')
        || !array_key_exists('active', $state) || !array_key_exists('pending', $state)) {
        return false;
    }

    if ($state['active'] !== null) {
        $active = $state['active'];
        if (!is_array($active) || !pcv_valid_config($active['config'] ?? null, false)
            || !is_int($active['activated_at'] ?? null) || !is_int($active['expires_at'] ?? null)
            || $active['activated_at'] < 1 || $active['expires_at'] <= $active['activated_at']
            || $active['expires_at'] > $active['activated_at'] + PCV_ACTIVE_TTL) {
            return false;
        }
    }

    if ($state['pending'] !== null) {
        $pending = $state['pending'];
        if (!is_array($pending) || !pcv_valid_config($pending['config'] ?? null, true)
            || !is_int($pending['staged_at'] ?? null) || !is_int($pending['expires_at'] ?? null)
            || $pending['staged_at'] < 1 || $pending['expires_at'] <= $pending['staged_at']
            || $pending['expires_at'] > $pending['staged_at'] + PCV_PENDING_TTL) {
            return false;
        }
    }

    return true;
}

/** The caller must hold the state lock. */
function pcv_load_store(string $directory): array
{
    $path = $directory . DIRECTORY_SEPARATOR . 'state.json';
    if (is_link($path)) {
        return ['kind' => 'unavailable', 'reason' => 'symlinked_state'];
    }
    if (!file_exists($path)) {
        return ['kind' => 'missing'];
    }
    if (!is_file($path)) {
        return ['kind' => 'unavailable', 'reason' => 'not_regular_file'];
    }

    $size = @filesize($path);
    if ($size === false) {
        return ['kind' => 'unavailable', 'reason' => 'state_stat_failed'];
    }
    if ($size > 16384) {
        return ['kind' => 'unavailable', 'reason' => 'state_too_large'];
    }
    $contents = @file_get_contents($path);
    if ($contents === false) {
        return ['kind' => 'unavailable', 'reason' => 'state_read_failed'];
    }

    try {
        $state = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return ['kind' => 'unavailable', 'reason' => 'invalid_json'];
    }
    if (!is_array($state) || !pcv_valid_stored_state($state)) {
        return ['kind' => 'unavailable', 'reason' => 'invalid_state'];
    }

    return ['kind' => 'ready', 'state' => $state];
}

/** The caller must hold the state lock. */
function pcv_write_store(string $directory, array $state): void
{
    $path = $directory . DIRECTORY_SEPARATOR . 'state.json';
    if (is_link($path)) {
        throw new RuntimeException('State file is not safe.');
    }
    $contents = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $temporary = tempnam($directory, '.state-');
    if ($temporary === false) {
        throw new RuntimeException('Could not stage state update.');
    }

    try {
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('Could not write state update.');
        }
        @chmod($temporary, 0660);
        if (!@rename($temporary, $path)) {
            throw new RuntimeException('Could not commit state update.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
}

function pcv_presence_result(string $status, array $actors = [], ?float $radius = null, ?int $observedAt = null, ?string $reason = null): array
{
    return ['status' => $status, 'actors' => $actors, 'radius' => $radius, 'observed_at' => $observedAt, 'reason' => $reason];
}

function pcv_presence_read_failure(array $empty, string $reason): array
{
    pcv_log_event('state.unavailable', 'error', 'unavailable', $reason, ['operation' => 'presence_read']);
    return array_replace($empty, ['status' => 'unavailable', 'reason' => $reason]);
}

/** Parse the native player-routing payload without retaining unrelated request data. */
function pcv_parse_presence_snapshot($raw): array
{
    if ($raw === null || $raw === '') {
        return pcv_presence_result('missing', reason: 'presence_missing');
    }
    if (!is_string($raw) || strlen($raw) > 65536) {
        return pcv_presence_result('unavailable', reason: 'presence_invalid');
    }
    $json = base64_decode($raw, true);
    if (!is_string($json) || $json === '' || strlen($json) > 32768) {
        return pcv_presence_result('unavailable', reason: 'presence_invalid');
    }
    try {
        $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return pcv_presence_result('unavailable', reason: 'presence_invalid');
    }
    if (!is_array($payload) || ($payload['source'] ?? null) !== 'plugin_player_routing_v2') {
        return pcv_presence_result('unavailable', reason: 'presence_invalid');
    }
    if (($payload['speech_mode'] ?? null) !== 'standard'
        || !is_string($payload['execution_mode'] ?? null)
        || strtoupper(trim($payload['execution_mode'])) !== 'STANDARD') {
        return pcv_presence_result('unavailable', reason: 'unsupported_mode');
    }
    $radius = $payload['audience_radius_units'] ?? null;
    if ((!is_int($radius) && !is_float($radius)) || !is_finite((float)$radius) || $radius <= 0
        || !is_array($payload['present_actors'] ?? null) || !array_is_list($payload['present_actors'])
        || count($payload['present_actors']) > 32) {
        return pcv_presence_result('unavailable', reason: 'presence_invalid');
    }

    $actors = [];
    foreach ($payload['present_actors'] as $actor) {
        if (!is_array($actor) || (!is_int($actor['form_id'] ?? null) && !is_float($actor['form_id'] ?? null))
            || !is_string($actor['name'] ?? null) || (!is_int($actor['distance'] ?? null) && !is_float($actor['distance'] ?? null))
            || !is_bool($actor['managed'] ?? null) || !is_bool($actor['creature'] ?? null)) {
            return pcv_presence_result('unavailable', reason: 'presence_invalid');
        }
        $formId = (float)$actor['form_id'];
        $distance = (float)$actor['distance'];
        $name = trim($actor['name']);
        if (!is_finite($formId) || $formId < 1 || $formId > 4294967295 || floor($formId) !== $formId
            || !is_finite($distance) || $distance < 0
            || $name === '' || strlen($name) > 256 || preg_match('//u', $name) !== 1) {
            return pcv_presence_result('unavailable', reason: 'presence_invalid');
        }
        if (!$actor['managed'] || $distance > $radius || preg_match('/[\x00-\x1f\x7f]/', $name) === 1) {
            continue;
        }
        $actors[] = ['form_id' => (int)$formId, 'name' => $name, 'distance' => $distance];
    }
    return pcv_presence_result($actors === [] ? 'empty' : 'ready', $actors, (float)$radius);
}

/** Parse the distinct no-dialogue producer; its event type is the source marker. */
function pcv_parse_autonomous_presence_report($raw): array
{
    if (!is_string($raw) || $raw === '' || strlen($raw) > 32768) {
        return pcv_presence_result('unavailable', reason: 'presence_invalid');
    }
    try {
        $payload = json_decode($raw, true, 12, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return pcv_presence_result('unavailable', reason: 'presence_invalid');
    }
    if (!is_array($payload) || ($payload['version'] ?? null) !== 1
        || !is_string($payload['player_name'] ?? null)
        || !is_bool($payload['overflow'] ?? null) || $payload['overflow']
        || (!is_int($payload['radius'] ?? null) && !is_float($payload['radius'] ?? null))
        || !is_array($payload['actors'] ?? null) || !array_is_list($payload['actors'])
        || count($payload['actors']) > 32) {
        return pcv_presence_result('unavailable', reason: 'presence_invalid');
    }

    $playerName = trim($payload['player_name']);
    $radius = (float)$payload['radius'];
    if ($playerName === '' || strlen($playerName) > 256 || preg_match('//u', $playerName) !== 1
        || preg_match('/[\x00-\x1f\x7f]/', $playerName) === 1
        || !is_finite($radius) || $radius <= 0) {
        return pcv_presence_result('unavailable', reason: 'presence_invalid');
    }

    $actors = [];
    foreach ($payload['actors'] as $actor) {
        if (!is_array($actor) || !is_string($actor['name'] ?? null)
            || (!is_int($actor['distance'] ?? null) && !is_float($actor['distance'] ?? null))) {
            return pcv_presence_result('unavailable', reason: 'presence_invalid');
        }
        $name = trim($actor['name']);
        $distance = (float)$actor['distance'];
        if ($name === '' || strlen($name) > 256 || preg_match('//u', $name) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $name) === 1
            || !is_finite($distance) || $distance < 0) {
            return pcv_presence_result('unavailable', reason: 'presence_invalid');
        }
        if ($distance <= $radius) {
            $actors[] = ['name' => $name, 'distance' => $distance];
        }
    }

    return array_replace(
        pcv_presence_result($actors === [] ? 'empty' : 'ready', $actors, $radius),
        ['player_name' => $playerName]
    );
}

/** Parse the native request timestamp without treating it as a wall-clock age. */
function pcv_parse_presence_request_timestamp($value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (!is_string($value) || preg_match('/^[1-9][0-9]{0,18}$/D', $value) !== 1) {
        return null;
    }
    $timestamp = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return is_int($timestamp) ? $timestamp : null;
}

/** Parse the names from CHIM's periodic `infonpc_close` report. */
function pcv_parse_background_presence_report($raw, ?string $currentPlayerName): array
{
    if (!is_string($raw) || $raw === '' || strlen($raw) > 32768 || preg_match('//u', $raw) !== 1
        || !is_string($currentPlayerName) || trim($currentPlayerName) === ''
        || strlen($currentPlayerName) > 256 || preg_match('//u', $currentPlayerName) !== 1) {
        return pcv_presence_result('unavailable', reason: 'presence_invalid');
    }

    $tokens = preg_split('/\//u', trim($raw));
    if (!is_array($tokens) || count($tokens) > 33) {
        return pcv_presence_result('unavailable', reason: 'presence_invalid');
    }
    if (!function_exists('pcv_scope_name_key')) {
        require_once __DIR__ . '/scope.php';
    }
    $playerKey = pcv_scope_name_key($currentPlayerName);
    $playerCount = 0;
    $actors = [];
    foreach ($tokens as $token) {
        $name = trim((string)$token);
        if ($name === '') {
            continue;
        }
        if (function_exists('chimDataStripActorStateSuffix')) {
            $name = chimDataStripActorStateSuffix($name);
        } else {
            $name = preg_replace(
                '/\s*\((?:busy|hostile|in combat|far away|too far away|restrained|dead|disabled|unavailable|sleeping|unconscious)\)\s*$/iu',
                '',
                $name
            );
            $name = trim((string)$name);
        }
        if ($name === '' || strlen($name) > 256 || preg_match('//u', $name) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $name) === 1) {
            return pcv_presence_result('unavailable', reason: 'presence_invalid');
        }

        if (pcv_scope_name_key($name) === $playerKey) {
            $playerCount++;
            continue;
        }
        $actors[] = ['name' => $name];
        if (count($actors) > 32) {
            return pcv_presence_result('unavailable', reason: 'presence_invalid');
        }
    }

    if ($playerCount !== 1) {
        return pcv_presence_result('unavailable', reason: 'identity_unavailable');
    }
    return pcv_presence_result($actors === [] ? 'empty' : 'ready', $actors);
}

/** Read an activity timestamp from the catalog's CHIM metadata projection. */
function pcv_background_activity_timestamp(array $row): ?int
{
    $metadata = $row['metadata'] ?? null;
    if (is_string($metadata)) {
        try {
            $metadata = json_decode($metadata, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }
    if (!is_array($metadata)) {
        return null;
    }
    $activity = $metadata['activity_status'] ?? null;
    $timestamp = is_array($activity)
        ? ($activity['timestamp'] ?? null)
        : ($metadata['activity_status_timestamp'] ?? null);
    return pcv_parse_presence_request_timestamp($timestamp);
}

/** Atomically replace the bounded background report while the state lock is held. */
function pcv_write_background_presence(string $directory, array $document): void
{
    $path = $directory . DIRECTORY_SEPARATOR . 'background_presence.json';
    if (is_link($path)) {
        throw new RuntimeException('Background presence report is not safe.');
    }
    $contents = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    $temporary = tempnam($directory, '.background-presence-');
    if ($temporary === false) {
        throw new RuntimeException('Could not stage background presence report.');
    }
    try {
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('Could not write background presence report.');
        }
        @chmod($temporary, 0660);
        if (!@rename($temporary, $path)) {
            throw new RuntimeException('Could not commit background presence report.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
}

/** Remove an unreadable or uncommittable report while the state lock is held. */
function pcv_remove_background_presence_locked(string $directory): void
{
    $path = $directory . DIRECTORY_SEPARATOR . 'background_presence.json';
    if (is_link($path)) {
        throw new RuntimeException('Background presence report is not safe.');
    }
    if (file_exists($path) && (!is_file($path) || !@unlink($path))) {
        throw new RuntimeException('Could not invalidate background presence report.');
    }
}

/** Invalidate only the old request snapshot; background reports have their own lifecycle. */
function pcv_invalidate_ordinary_presence_snapshot(?string $stateDirectory = null): array
{
    $handle = null;
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, false, LOCK_EX);
        if ($handle === null) {
            return ['status' => 'missing', 'reason' => 'presence_missing'];
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'presence.json';
        if (is_link($path) || (file_exists($path) && (!is_file($path) || !@unlink($path)))) {
            pcv_log_event('state.unavailable', 'error', 'unavailable', 'presence_unavailable', ['operation' => 'presence_invalidate']);
            return ['status' => 'unavailable', 'reason' => 'presence_unavailable'];
        }
        return ['status' => 'missing', 'reason' => 'presence_missing'];
    } catch (Throwable $error) {
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'presence_unavailable', $error, ['operation' => 'presence_invalidate']);
        return ['status' => 'unavailable', 'reason' => 'presence_unavailable'];
    } finally {
        pcv_unlock_state($handle);
    }
}

/** Remove the cached report before identity synchronization or after an invalid ordinary payload. */
function pcv_invalidate_eligible_npcs(?string $stateDirectory = null): array
{
    $handle = null;
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, false, LOCK_EX);
        if ($handle === null) {
            return ['status' => 'missing', 'reason' => 'presence_missing'];
        }
        foreach (['presence.json', 'background_presence.json'] as $name) {
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_link($path) || (file_exists($path) && (!is_file($path) || !@unlink($path)))) {
                pcv_log_event('state.unavailable', 'error', 'unavailable', 'presence_unavailable', ['operation' => 'presence_invalidate']);
                return ['status' => 'unavailable', 'reason' => 'presence_unavailable'];
            }
        }
        return ['status' => 'missing', 'reason' => 'presence_missing'];
    } catch (Throwable $error) {
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'presence_unavailable', $error, ['operation' => 'presence_invalidate']);
        return ['status' => 'unavailable', 'reason' => 'presence_unavailable'];
    } finally {
        pcv_unlock_state($handle);
    }
}

/** Read a recent per-source ordering marker while the state lock is held. */
function pcv_presence_order_marker(string $path, string $key, int $now): ?array
{
    if (is_link($path) || !is_file($path)) {
        return null;
    }
    $size = @filesize($path);
    if (!is_int($size) || $size > 32768) {
        return null;
    }
    $contents = @file_get_contents($path);
    if (!is_string($contents)) {
        return null;
    }
    $document = json_decode($contents, true, 16);
    $marker = is_array($document) ? ($document['autonomous_order'] ?? null) : null;
    if (!is_array($document) || !is_string($document['key'] ?? null) || !hash_equals($key, $document['key'])
        || !is_array($marker) || !is_int($marker['request_timestamp'] ?? null)
        || $marker['request_timestamp'] < 1 || !is_int($marker['observed_at'] ?? null)
        || $marker['observed_at'] > $now || $marker['observed_at'] < $now - PCV_PRESENCE_TTL) {
        return null;
    }
    return $marker;
}

/** Cache bounded presence metadata and receipt time under the current playthrough key. */
function pcv_store_presence_snapshot(
    ?string $key,
    array $parsed,
    ?string $stateDirectory = null,
    ?int $requestTimestamp = null,
    bool $autonomous = false
): array
{
    if (!is_string($key) || !pcv_valid_key($key) || !in_array($parsed['status'], ['ready', 'empty'], true)) {
        $cleared = pcv_invalidate_ordinary_presence_snapshot($stateDirectory);
        if (($cleared['status'] ?? null) === 'unavailable') {
            return pcv_presence_result('unavailable', reason: 'presence_unavailable');
        }
        if (!is_string($key) || !pcv_valid_key($key)) {
            return pcv_presence_result('unavailable', reason: 'identity_unavailable');
        }
        return $parsed;
    }

    $handle = null;
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, true, LOCK_EX);
        $observedAt = time();
        $path = $directory . DIRECTORY_SEPARATOR . 'presence.json';
        if (is_link($path)) {
            throw new RuntimeException('Presence report is not safe.');
        }
        $previousOrder = pcv_presence_order_marker($path, $key, $observedAt);
        if ($autonomous && $previousOrder !== null && $requestTimestamp !== null
            && $requestTimestamp <= $previousOrder['request_timestamp']) {
            pcv_log_event('state.presence_rejected', 'warning', 'rejected', 'presence_stale', ['operation' => 'presence_capture']);
            return pcv_presence_result('stale', reason: 'presence_stale');
        }
        $document = [
            'version' => 1,
            'key' => $key,
            'observed_at' => $observedAt,
            'radius' => $parsed['radius'],
            'actors' => $parsed['actors'],
        ];
        if ($autonomous) {
            $document['autonomous_order'] = [
                'request_timestamp' => $requestTimestamp,
                'observed_at' => $observedAt,
            ];
        } elseif ($previousOrder !== null) {
            // Ordinary snapshots refresh shared presence without extending the autonomous clock window.
            $document['autonomous_order'] = $previousOrder;
        }
        $contents = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        $temporary = tempnam($directory, '.presence-');
        if ($temporary === false) {
            throw new RuntimeException('Could not stage presence report.');
        }
        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
                throw new RuntimeException('Could not write presence report.');
            }
            @chmod($temporary, 0660);
            if (!@rename($temporary, $path)) {
                throw new RuntimeException('Could not commit presence report.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
        pcv_log_set_playthrough_ref($key);
        if ($autonomous) {
            pcv_log_event('state.presence_refreshed', 'debug', 'accepted', null, [
                'actor_count' => count($parsed['actors']),
            ]);
        }
        return pcv_presence_result($parsed['status'], $parsed['actors'], $parsed['radius'], $observedAt);
    } catch (Throwable $error) {
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'presence_unavailable', $error, ['operation' => 'presence_capture']);
        return pcv_presence_result('unavailable', reason: 'presence_unavailable');
    } finally {
        pcv_unlock_state($handle);
    }
}

/** Cache a validated ordinary player-routing snapshot, preserving the no-chat clock marker. */
function pcv_capture_presence_snapshot(
    ?string $key,
    $raw,
    ?string $stateDirectory = null,
    ?int $requestTimestamp = null
): array
{
    $parsed = pcv_parse_presence_snapshot($raw);
    if (!in_array($parsed['status'], ['ready', 'empty'], true)) {
        $cleared = pcv_invalidate_ordinary_presence_snapshot($stateDirectory);
        if (($cleared['status'] ?? null) === 'unavailable') {
            return pcv_presence_result('unavailable', reason: 'presence_unavailable');
        }
        if (!is_string($key) || !pcv_valid_key($key)) {
            return pcv_presence_result('unavailable', reason: 'identity_unavailable');
        }
        return $parsed;
    }
    return pcv_store_presence_snapshot($key, $parsed, $stateDirectory, $requestTimestamp);
}

/** Capture a distinct autonomous report; invalid matching events clear stale eligibility. */
function pcv_capture_autonomous_presence_report(
    ?string $key,
    $raw,
    ?string $currentPlayerName,
    $requestTimestamp,
    ?string $stateDirectory = null
): array
{
    $parsed = pcv_parse_autonomous_presence_report($raw);
    $reason = $parsed['reason'] ?? null;
    $timestamp = pcv_parse_presence_request_timestamp($requestTimestamp);
    if (!in_array($parsed['status'], ['ready', 'empty'], true)) {
        $reason = is_string($reason) ? $reason : 'presence_invalid';
    } elseif (!is_string($key) || !pcv_valid_key($key)) {
        $reason = 'identity_unavailable';
    } elseif (!is_string($currentPlayerName) || trim($currentPlayerName) === '') {
        $reason = 'identity_unavailable';
    } else {
        if (!function_exists('pcv_scope_name_key')) {
            require_once __DIR__ . '/scope.php';
        }
        if (pcv_scope_name_key($parsed['player_name']) !== pcv_scope_name_key($currentPlayerName)) {
            $reason = 'presence_invalid';
        } elseif ($timestamp === null) {
            $reason = 'presence_invalid';
        }
    }

    if ($reason !== null) {
        $cleared = pcv_invalidate_ordinary_presence_snapshot($stateDirectory);
        if (($cleared['status'] ?? null) === 'unavailable') {
            $reason = 'presence_unavailable';
        }
        pcv_log_event('state.unavailable', 'error', 'unavailable', $reason, ['operation' => 'presence_capture']);
        return pcv_presence_result('unavailable', reason: $reason);
    }

    return pcv_store_presence_snapshot($key, $parsed, $stateDirectory, $timestamp, true);
}

/** Capture a native close-range heartbeat without making a game or database call. */
function pcv_capture_background_presence_report(
    ?string $key,
    $raw,
    ?string $playerName,
    $requestTimestamp,
    ?string $stateDirectory = null
): array {
    $parsed = pcv_parse_background_presence_report($raw, $playerName);
    $timestamp = pcv_parse_presence_request_timestamp($requestTimestamp);
    $reason = $parsed['reason'] ?? null;
    if (!in_array($parsed['status'], ['ready', 'empty'], true)) {
        $reason = is_string($reason) ? $reason : 'presence_invalid';
    } elseif (!is_string($key) || !pcv_valid_key($key) || $timestamp === null) {
        $reason = 'identity_unavailable';
    }

    if ($reason !== null) {
        try {
            $directory = pcv_state_directory($stateDirectory);
            $handle = pcv_lock_state($directory, true, LOCK_EX);
            try {
                pcv_write_background_presence($directory, [
                    'version' => PCV_BACKGROUND_PRESENCE_VERSION,
                    'source' => 'infonpc_close_v1',
                    'key' => is_string($key) && pcv_valid_key($key) ? $key : null,
                    'player_name' => is_string($playerName) ? trim($playerName) : null,
                    'state' => 'unavailable',
                    'reason' => $reason,
                    'observed_at' => time(),
                    'heartbeat_timestamp' => null,
                    'baseline_timestamp' => null,
                    'actors' => [],
                ]);
            } finally {
                pcv_unlock_state($handle);
            }
        } catch (Throwable $error) {
            pcv_log_exception('state.unavailable', 'error', 'unavailable', 'presence_unavailable', $error, ['operation' => 'presence_capture']);
            return pcv_presence_result('unavailable', reason: 'presence_unavailable');
        }
        pcv_log_event('state.unavailable', 'error', 'unavailable', $reason, ['operation' => 'presence_capture']);
        return pcv_presence_result('unavailable', reason: $reason);
    }

    $handle = null;
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, true, LOCK_EX);
        $now = time();
        $path = $directory . DIRECTORY_SEPARATOR . 'background_presence.json';
        if (is_link($path)) {
            throw new RuntimeException('Background presence report is not safe.');
        }

        $previous = null;
        if (is_file($path)) {
            $size = @filesize($path);
            if (is_int($size) && $size <= 32768) {
                $contents = @file_get_contents($path);
                $document = is_string($contents) ? json_decode($contents, true, 16) : null;
                if (is_array($document)
                    && ($document['version'] ?? null) === PCV_BACKGROUND_PRESENCE_VERSION
                    && ($document['source'] ?? null) === 'infonpc_close_v1'
                    && is_string($document['key'] ?? null) && pcv_valid_key($document['key'])
                    && is_string($document['player_name'] ?? null)
                    && is_int($document['observed_at'] ?? null)
                    && in_array($document['state'] ?? null, ['baseline', 'ready', 'empty'], true)
                    && is_int($document['heartbeat_timestamp'] ?? null)
                    && is_int($document['baseline_timestamp'] ?? null)
                    && $document['baseline_timestamp'] <= $document['heartbeat_timestamp']
                    && is_array($document['actors'] ?? null) && array_is_list($document['actors'])
                    && count($document['actors']) <= 32) {
                    $previous = $document;
                }
            }
        }

        $baseline = $timestamp;
        $state = $parsed['status'] === 'empty' ? 'empty' : 'baseline';
        $sameIdentity = is_array($previous)
            && hash_equals($key, $previous['key'])
            && pcv_scope_name_key($playerName) === pcv_scope_name_key($previous['player_name']);
        if ($sameIdentity) {
            $previousTimestamp = $previous['heartbeat_timestamp'];
            $previousReceipt = $previous['observed_at'];
            $leaseExpired = $now >= $previousReceipt && ($now - $previousReceipt) > PCV_PRESENCE_TTL;
            $serverClockRewound = $previousReceipt > $now;
            if ($timestamp === $previousTimestamp) {
                pcv_log_event('state.presence_rejected', 'warning', 'rejected', 'presence_stale', [
                    'operation' => 'presence_capture',
                ]);
                return pcv_presence_result('stale', observedAt: $previousReceipt, reason: 'presence_stale');
            }
            if ($timestamp < $previousTimestamp && !$leaseExpired && !$serverClockRewound) {
                pcv_log_event('state.presence_rejected', 'warning', 'rejected', 'presence_stale', [
                    'operation' => 'presence_capture',
                ]);
                return pcv_presence_result('stale', observedAt: $previousReceipt, reason: 'presence_stale');
            }
            if ($timestamp > $previousTimestamp && !$leaseExpired && !$serverClockRewound) {
                $baseline = $previous['baseline_timestamp'];
                $state = $parsed['status'] === 'empty' ? 'empty' : 'ready';
            }
        }

        $document = [
            'version' => PCV_BACKGROUND_PRESENCE_VERSION,
            'source' => 'infonpc_close_v1',
            'key' => $key,
            'player_name' => trim($playerName),
            'state' => $state,
            'reason' => null,
            'observed_at' => $now,
            'heartbeat_timestamp' => $timestamp,
            'baseline_timestamp' => $baseline,
            'actors' => $parsed['actors'],
        ];
        pcv_write_background_presence($directory, $document);
        pcv_log_set_playthrough_ref($key);
        pcv_log_event('state.presence_refreshed', 'debug', 'accepted', null, [
            'actor_count' => count($parsed['actors']),
        ]);
        return pcv_presence_result($state, $parsed['actors'], observedAt: $now);
    } catch (Throwable $error) {
        if (is_resource($handle)) {
            try {
                pcv_remove_background_presence_locked($directory);
            } catch (Throwable) {
                // If storage also prevents invalidation, the prior report expires on its original receipt time.
            }
        }
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'presence_unavailable', $error, ['operation' => 'presence_capture']);
        return pcv_presence_result('unavailable', reason: 'presence_unavailable');
    } finally {
        pcv_unlock_state($handle);
    }
}

/** Re-resolve snapshot names against the current catalog on every caller read. */
function pcv_read_eligible_npcs(?string $key, array $catalogRows, ?string $playerName, ?string $stateDirectory = null): array
{
    $empty = ['status' => 'missing', 'known_npcs' => [], 'observed_at' => null, 'reason' => 'presence_missing'];
    if (!is_string($key) || !pcv_valid_key($key) || !is_string($playerName) || trim($playerName) === '') {
        return pcv_presence_read_failure($empty, 'identity_unavailable');
    }

    $handle = null;
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, false, LOCK_SH);
        if ($handle === null) {
            return $empty;
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'background_presence.json';
        if (is_link($path)) {
            return pcv_presence_read_failure($empty, 'presence_unavailable');
        }
        if (!file_exists($path)) {
            return $empty;
        }
        if (!is_file($path)) {
            return pcv_presence_read_failure($empty, 'presence_unavailable');
        }
        $size = @filesize($path);
        if (!is_int($size) || $size > 32768) {
            return pcv_presence_read_failure($empty, 'presence_unavailable');
        }
        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            return pcv_presence_read_failure($empty, 'presence_unavailable');
        }
        $document = json_decode($contents, true, 16);
        if (!is_array($document) || ($document['version'] ?? null) !== PCV_BACKGROUND_PRESENCE_VERSION
            || ($document['source'] ?? null) !== 'infonpc_close_v1'
            || !is_string($document['key'] ?? null) || !pcv_valid_key($document['key'])
            || !is_int($document['observed_at'] ?? null)
            || !is_string($document['player_name'] ?? null)
            || !in_array($document['state'] ?? null, ['baseline', 'ready', 'empty', 'unavailable'], true)
            || !is_array($document['actors'] ?? null) || !array_is_list($document['actors']) || count($document['actors']) > 32) {
            return pcv_presence_read_failure($empty, 'presence_unavailable');
        }
        if (!hash_equals($key, $document['key'])) {
            return pcv_presence_read_failure($empty, 'presence_key_mismatch');
        }
        if (!function_exists('pcv_scope_name_key')) {
            require_once __DIR__ . '/scope.php';
        }
        if (pcv_scope_name_key($playerName) !== pcv_scope_name_key($document['player_name'])) {
            return pcv_presence_read_failure($empty, 'identity_unavailable');
        }
        $now = time();
        if ($document['observed_at'] > $now || $document['observed_at'] < $now - PCV_PRESENCE_TTL) {
            return array_replace($empty, [
                'status' => $document['observed_at'] > $now ? 'unavailable' : 'stale',
                'observed_at' => $document['observed_at'],
                'reason' => $document['observed_at'] > $now ? 'presence_unavailable' : 'presence_stale',
            ]);
        }
        if ($document['state'] === 'unavailable') {
            $reason = is_string($document['reason'] ?? null) ? $document['reason'] : 'presence_unavailable';
            return pcv_presence_read_failure($empty, $reason);
        }
        if (!is_int($document['heartbeat_timestamp'] ?? null)
            || !is_int($document['baseline_timestamp'] ?? null)
            || $document['baseline_timestamp'] > $document['heartbeat_timestamp']) {
            return pcv_presence_read_failure($empty, 'presence_unavailable');
        }
        foreach ($document['actors'] as $actor) {
            if (!is_array($actor) || !is_string($actor['name'] ?? null)
                || trim($actor['name']) === '' || strlen($actor['name']) > 256 || preg_match('//u', $actor['name']) !== 1
                || preg_match('/[\x00-\x1f\x7f]/', $actor['name']) === 1) {
                return pcv_presence_read_failure($empty, 'presence_unavailable');
            }
        }

        if ($document['state'] === 'empty') {
            return [
                'status' => 'empty',
                'known_npcs' => [],
                'observed_at' => $document['observed_at'],
                'reason' => null,
            ];
        }
        if ($document['state'] === 'baseline') {
            return array_replace($empty, [
                'status' => 'stale',
                'observed_at' => $document['observed_at'],
                'reason' => 'presence_stale',
            ]);
        }

        $reportedCounts = [];
        foreach ($document['actors'] as $actor) {
            $nameKey = pcv_scope_name_key($actor['name']);
            $reportedCounts[$nameKey] = ($reportedCounts[$nameKey] ?? 0) + 1;
        }
        $freshNames = [];
        $heartbeat = $document['heartbeat_timestamp'];
        $baseline = $document['baseline_timestamp'];
        foreach (pcvScopeKnownNpcs($catalogRows, $playerName) as $id => $name) {
            $nameKey = pcv_scope_name_key($name);
            if (($reportedCounts[$nameKey] ?? 0) !== 1) {
                continue;
            }
            foreach ($catalogRows as $row) {
                if ((string)($row['id'] ?? '') !== (string)$id
                    || pcv_scope_name_key((string)($row['npc_name'] ?? '')) !== $nameKey) {
                    continue;
                }
                $statusTimestamp = pcv_background_activity_timestamp($row);
                if ($statusTimestamp === null || $statusTimestamp <= $baseline) {
                    continue;
                }
                $combinedAgeSeconds = (($heartbeat - $statusTimestamp) / 1_000_000_000)
                    + ($now - $document['observed_at']);
                if ($combinedAgeSeconds >= 0 && $combinedAgeSeconds <= PCV_PRESENCE_TTL) {
                    $freshNames[$nameKey] = $name;
                }
                break;
            }
        }
        if ($freshNames === []) {
            return array_replace($empty, [
                'status' => 'stale',
                'observed_at' => $document['observed_at'],
                'reason' => 'presence_stale',
            ]);
        }
        $freshActors = [];
        foreach ($document['actors'] as $actor) {
            if (isset($freshNames[pcv_scope_name_key($actor['name'])])) {
                $freshActors[] = $actor;
            }
        }
        $known = pcvScopeEligibleMapFromPresence($freshActors, $catalogRows, $playerName);
        if ($known === []) {
            return array_replace($empty, [
                'status' => 'stale',
                'observed_at' => $document['observed_at'],
                'reason' => 'presence_stale',
            ]);
        }
        return [
            'status' => 'ready',
            'known_npcs' => $known,
            'observed_at' => $document['observed_at'],
            'reason' => null,
        ];
    } catch (Throwable $error) {
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'presence_unavailable', $error, ['operation' => 'presence_read']);
        return array_replace($empty, ['status' => 'unavailable', 'reason' => 'presence_unavailable']);
    } finally {
        pcv_unlock_state($handle);
    }
}

function pcv_result(
    string $status,
    ?array $scope,
    bool $pending,
    ?array $pendingScope = null,
    ?string $configId = null,
    ?string $pendingConfigId = null,
    ?string $reason = null
): array
{
    return [
        'status' => $status,
        'scope' => $scope,
        'pending' => $pending,
        'pending_scope' => $pendingScope,
        'config_id' => $configId,
        'pending_config_id' => $pendingConfigId,
        'reason' => $reason,
    ];
}

function pcv_visible_state(array $state, int $now): array
{
    $active = $state['active'];
    if (is_array($active) && $active['expires_at'] > $now) {
        $scope = pcv_config_with_scene_mode($active['config']);
    } else {
        $scope = null;
    }

    $pendingScope = is_array($state['pending']) && $state['pending']['expires_at'] > $now
        ? pcv_config_with_scene_mode($state['pending']['config'])
        : null;
    $configId = $scope !== null && pcv_valid_config_id($active['config_id'] ?? null)
        ? ($active['config_id'] ?? null) : null;
    $storedPendingConfigId = $state['pending']['config_id'] ?? null;
    $pendingConfigId = $pendingScope !== null && pcv_valid_config_id($storedPendingConfigId)
        ? $storedPendingConfigId : null;
    $pending = $pendingScope !== null;
    if ($scope !== null) {
        return pcv_result('active', $scope, $pending, $pendingScope, $configId, $pendingConfigId);
    }
    return $pending
        ? pcv_result('pending', null, true, $pendingScope, null, $pendingConfigId)
        : pcv_result('off', null, false);
}

function pcv_config_with_scene_mode(array $config): array
{
    if (($config['enabled'] ?? false) === true) {
        $sceneMode = $config['scene_mode'] ?? 'pair';
        unset($config['scene_mode']);
        return ['enabled' => true, 'scene_mode' => $sceneMode] + $config;
    }
    return $config;
}

function pcv_state_log_context(array $config): array
{
    $enabled = ($config['enabled'] ?? false) === true;
    $context = [
        'action' => $enabled ? 'enable' : 'end',
        'exclude_player' => ($config['exclude_player'] ?? true) === true,
        'bystander_mode' => in_array($config['bystander_mode'] ?? null, ['exclude', 'silent'], true)
            ? $config['bystander_mode'] : 'exclude',
    ];
    if ($enabled) {
        $context['scene_mode'] = $config['scene_mode'] ?? 'pair';
        $context['actor_a_id'] = $config['actor_a'] ?? null;
        $context['actor_b_id'] = $config['actor_b'] ?? null;
    }
    return $context;
}

function pcv_read(string $key, ?string $stateDirectory = null): array
{
    if (!pcv_valid_key($key)) {
        pcv_log_set_playthrough_ref(null);
        pcv_log_event('state.unavailable', 'error', 'unavailable', 'invalid_state_key', ['operation' => 'read']);
        return pcv_result('unavailable', null, false);
    }
    pcv_log_set_playthrough_ref($key);

    $handle = null;
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, false, LOCK_SH);
        if ($handle === null) {
            return pcv_result('off', null, false);
        }
        $loaded = pcv_load_store($directory);
        if ($loaded['kind'] === 'missing') {
            return pcv_result('off', null, false);
        }
        if ($loaded['kind'] !== 'ready') {
            pcv_log_event('state.unavailable', 'error', 'unavailable', $loaded['reason'] ?? 'state_read_failed', ['operation' => 'read']);
            return pcv_result('unavailable', null, false);
        }
        $state = $loaded['state'];
        if (!hash_equals($state['key'], $key)) {
            pcv_log_set_config_id(null);
            return pcv_result('off', null, false);
        }
        $result = pcv_visible_state($state, time());
        pcv_log_set_config_id($result['config_id']);
        return $result;
    } catch (Throwable $error) {
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'state_read_failed', $error, ['operation' => 'read']);
        return pcv_result('unavailable', null, false);
    } finally {
        pcv_unlock_state($handle);
    }
}

function pcv_normalize_config(array $desired, array $knownNpcs): array
{
    if (!is_bool($desired['enabled'] ?? null)) {
        throw new InvalidArgumentException('Choose whether the private scope is enabled.');
    }
    if (!$desired['enabled']) {
        return [
            'enabled' => false,
            'actor_a' => '',
            'actor_b' => '',
            'exclude_player' => true,
            'bystander_mode' => 'exclude',
        ];
    }

    $sceneMode = array_key_exists('scene_mode', $desired) ? $desired['scene_mode'] : 'pair';
    if (!in_array($sceneMode, ['pair', 'solo'], true)
        || !is_string($desired['actor_a'] ?? null)
        || !is_bool($desired['exclude_player'] ?? null)
        || !in_array($desired['bystander_mode'] ?? null, ['exclude', 'silent'], true)) {
        throw new InvalidArgumentException('The private conversation settings are incomplete.');
    }

    $actorA = $desired['actor_a'];
    $actorB = $sceneMode === 'solo' ? null : ($desired['actor_b'] ?? null);
    $excludePlayer = $sceneMode === 'solo' ? true : $desired['exclude_player'];
    $config = [
        'enabled' => true,
        'scene_mode' => $sceneMode,
        'actor_a' => $actorA,
        'actor_b' => $actorB,
        'exclude_player' => $excludePlayer,
        'bystander_mode' => $desired['bystander_mode'],
    ];
    if (!pcv_valid_config($config, false)) {
        throw new InvalidArgumentException($sceneMode === 'solo'
            ? 'Choose one valid NPC for solo reflection.'
            : 'Choose two different valid NPCs.');
    }
    $actorIds = $sceneMode === 'solo' ? [$actorA] : [$actorA, $actorB];
    foreach ($actorIds as $actorId) {
        if (!is_string($actorId) || !array_key_exists($actorId, $knownNpcs)
            || !is_string($knownNpcs[$actorId]) || trim($knownNpcs[$actorId]) === '') {
            throw new InvalidArgumentException('The selected NPC is no longer available. Reload the NPC list.');
        }
    }
    $fold = static fn(string $name): string => function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $selectedNames = [];
    foreach ($actorIds as $selectedId) {
        $selectedName = $fold(trim($knownNpcs[$selectedId]));
        if (isset($selectedNames[$selectedName])) {
            throw new InvalidArgumentException('The selected NPC names are ambiguous.');
        }
        $selectedNames[$selectedName] = true;
        foreach ($knownNpcs as $otherId => $otherName) {
            if ((string)$otherId !== $selectedId && is_string($otherName) && $fold(trim($otherName)) === $selectedName) {
                throw new InvalidArgumentException('The selected NPC name is ambiguous.');
            }
        }
    }
    return $config;
}

function pcv_config_actors_are_eligible(array $config, ?array $eligibleNpcMap): bool
{
    if (!is_array($eligibleNpcMap)) {
        return false;
    }
    $actorIds = ($config['scene_mode'] ?? 'pair') === 'solo'
        ? [$config['actor_a'] ?? null]
        : [$config['actor_a'] ?? null, $config['actor_b'] ?? null];
    foreach ($actorIds as $actorId) {
        if (!is_string($actorId) || !array_key_exists($actorId, $eligibleNpcMap)) {
            return false;
        }
    }
    return true;
}

function pcv_stage(string $key, array $desired, array $knownNpcs, ?string $stateDirectory = null): array
{
    if (!pcv_valid_key($key)) {
        pcv_log_set_playthrough_ref(null);
        pcv_log_event('state.unavailable', 'error', 'unavailable', 'invalid_state_key', ['operation' => 'stage']);
        return pcv_result('unavailable', null, false);
    }
    pcv_log_set_playthrough_ref($key);
    $config = pcv_normalize_config($desired, $knownNpcs);

    $handle = null;
    $invalidated = null;
    $expired = [];
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, true, LOCK_EX);
        $loaded = pcv_load_store($directory);
        if ($loaded['kind'] === 'unavailable') {
            pcv_log_event('state.unavailable', 'error', 'unavailable', $loaded['reason'] ?? 'state_stage_failed', ['operation' => 'stage']);
            return pcv_result('unavailable', null, false);
        }

        $state = $loaded['kind'] === 'ready' ? $loaded['state'] : pcv_empty_store($key);
        if ($state['key'] !== $key) {
            $invalidated = [
                'key' => $state['key'],
                'active_config_id' => is_array($state['active']) && pcv_valid_config_id($state['active']['config_id'] ?? null)
                    ? ($state['active']['config_id'] ?? null) : null,
                'pending_config_id' => is_array($state['pending']) && pcv_valid_config_id($state['pending']['config_id'] ?? null)
                    ? ($state['pending']['config_id'] ?? null) : null,
            ];
            $state = pcv_empty_store($key);
        }

        $now = time();
        if (is_array($state['active']) && $state['active']['expires_at'] <= $now) {
            $expired[] = ['target' => 'active', 'config_id' => $state['active']['config_id'] ?? null];
            $state['active'] = null;
        }
        if (is_array($state['pending']) && $state['pending']['expires_at'] <= $now) {
            $expired[] = ['target' => 'pending', 'config_id' => $state['pending']['config_id'] ?? null];
            $state['pending'] = null;
        }
        $state['pending'] = [
            'config' => $config,
            'config_id' => pcv_log_new_uuid(),
            'staged_at' => $now,
            'expires_at' => $now + PCV_PENDING_TTL,
        ];
        pcv_write_store($directory, $state);
        if (is_array($invalidated)) {
            pcv_log_set_playthrough_ref($invalidated['key']);
            pcv_log_set_config_id($invalidated['active_config_id']);
            pcv_log_event('state.scope_invalidated', 'warning', 'invalidated', 'playthrough_changed', [
                'active_config_id' => $invalidated['active_config_id'],
                'pending_config_id' => $invalidated['pending_config_id'],
            ]);
            pcv_log_set_playthrough_ref($key);
        }
        foreach ($expired as $expiredEntry) {
            pcv_log_set_config_id(pcv_valid_config_id($expiredEntry['config_id']) ? $expiredEntry['config_id'] : null);
            pcv_log_event('state.scope_expired', 'info', 'expired', $expiredEntry['target'] . '_ttl', ['target' => $expiredEntry['target']]);
        }
        $pendingConfigId = $state['pending']['config_id'];
        pcv_log_set_config_id($pendingConfigId);
        pcv_log_event('state.scope_staged', 'info', 'ok', null, pcv_state_log_context($config));
        return pcv_visible_state($state, $now);
    } catch (Throwable $error) {
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'state_stage_failed', $error, ['operation' => 'stage']);
        return pcv_result('unavailable', null, false);
    } finally {
        pcv_unlock_state($handle);
    }
}

function pcv_begin_request(string $key, bool $eligible, ?string $stateDirectory = null, ?array $eligibleNpcMap = null): array
{
    if (!pcv_valid_key($key)) {
        pcv_log_set_playthrough_ref(null);
        pcv_log_event('state.unavailable', 'error', 'unavailable', 'invalid_state_key', ['operation' => 'begin']);
        return pcv_result('unavailable', null, false);
    }
    pcv_log_set_playthrough_ref($key);

    $handle = null;
    $expired = [];
    $activated = null;
    $invalidated = null;
    $blockedPending = null;
    $blockedActive = null;
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, true, LOCK_EX);
        $loaded = pcv_load_store($directory);
        if ($loaded['kind'] === 'unavailable') {
            pcv_log_event('state.unavailable', 'error', 'unavailable', $loaded['reason'] ?? 'state_transition_failed', ['operation' => 'begin']);
            return pcv_result('unavailable', null, false);
        }

        $state = $loaded['kind'] === 'ready' ? $loaded['state'] : pcv_empty_store($key);
        $changed = $loaded['kind'] === 'ready' && $state['key'] !== $key;
        if ($changed) {
            $invalidated = [
                'key' => $state['key'],
                'active_config_id' => is_array($state['active']) && pcv_valid_config_id($state['active']['config_id'] ?? null)
                    ? ($state['active']['config_id'] ?? null) : null,
                'pending_config_id' => is_array($state['pending']) && pcv_valid_config_id($state['pending']['config_id'] ?? null)
                    ? ($state['pending']['config_id'] ?? null) : null,
            ];
            $state = pcv_empty_store($key);
        }

        $now = time();
        if (is_array($state['active']) && $state['active']['expires_at'] <= $now) {
            $expired[] = ['target' => 'active', 'config_id' => $state['active']['config_id'] ?? null];
            $state['active'] = null;
            $changed = true;
        }
        if (is_array($state['pending']) && $state['pending']['expires_at'] <= $now) {
            $expired[] = ['target' => 'pending', 'config_id' => $state['pending']['config_id'] ?? null];
            $state['pending'] = null;
            $changed = true;
        }

        if ($eligible && is_array($state['pending'])) {
            $pending = $state['pending'];
            $config = $pending['config'];
            $configId = $pending['config_id'] ?? null;
            $actorsEligible = !$config['enabled'] || pcv_config_actors_are_eligible($config, $eligibleNpcMap);
            if (!$actorsEligible) {
                $blockedPending = $pending;
            } else {
                if (array_key_exists('config_id', $pending) && !pcv_valid_config_id($configId)) {
                    $configId = null;
                } elseif (!array_key_exists('config_id', $pending)) {
                    $configId = pcv_log_new_uuid();
                }
                $state['active'] = $config['enabled'] ? [
                    'config' => $config,
                    'config_id' => $configId,
                    'activated_at' => $now,
                    'expires_at' => $now + PCV_ACTIVE_TTL,
                ] : null;
                $state['pending'] = null;
                $activated = ['config' => $config, 'config_id' => $configId];
                $changed = true;
            }
        }

        if (!is_array($blockedPending) && is_array($state['active'])) {
            $activeConfig = $state['active']['config'] ?? null;
            if (is_array($activeConfig) && ($activeConfig['enabled'] ?? null) === true
                && !pcv_config_actors_are_eligible($activeConfig, $eligibleNpcMap)) {
                $blockedActive = $state['active'];
            }
        }

        if ($changed) {
            pcv_write_store($directory, $state);
        }
        if (is_array($invalidated)) {
            pcv_log_set_playthrough_ref($invalidated['key']);
            pcv_log_set_config_id($invalidated['active_config_id']);
            pcv_log_event('state.scope_invalidated', 'warning', 'invalidated', 'playthrough_changed', [
                'active_config_id' => $invalidated['active_config_id'],
                'pending_config_id' => $invalidated['pending_config_id'],
            ]);
            pcv_log_set_playthrough_ref($key);
        }
        foreach ($expired as $expiredEntry) {
            pcv_log_set_config_id(pcv_valid_config_id($expiredEntry['config_id']) ? $expiredEntry['config_id'] : null);
            pcv_log_event('state.scope_expired', 'info', 'expired', $expiredEntry['target'] . '_ttl', ['target' => $expiredEntry['target']]);
        }
        if (is_array($activated)) {
            pcv_log_set_config_id($activated['config_id']);
            pcv_log_event('state.scope_activated', 'info', 'ok', null, pcv_state_log_context($activated['config']));
        }
        if (is_array($blockedPending)) {
            $pendingConfigId = $blockedPending['config_id'] ?? null;
            if (!is_string($pendingConfigId) || !pcv_log_valid_uuid($pendingConfigId)) {
                $pendingConfigId = null;
            }
            pcv_log_set_config_id($pendingConfigId);
            $sceneMode = $blockedPending['config']['scene_mode'] ?? 'pair';
            pcv_log_event('state.scope_skipped', 'info', 'skipped', 'scene_not_eligible', [
                'operation' => 'begin', 'scene_mode' => $sceneMode,
            ]);
            return pcv_result('unavailable', null, true, pcv_config_with_scene_mode($blockedPending['config']), null,
                is_string($pendingConfigId) ? $pendingConfigId : null, 'scene_not_eligible');
        }
        if (is_array($blockedActive)) {
            $activeConfigId = $blockedActive['config_id'] ?? null;
            if (!is_string($activeConfigId) || !pcv_log_valid_uuid($activeConfigId)) {
                $activeConfigId = null;
            }
            $pendingScope = is_array($state['pending'] ?? null) ? $state['pending']['config'] : null;
            $pendingConfigId = is_array($state['pending'] ?? null) ? ($state['pending']['config_id'] ?? null) : null;
            if (!is_string($pendingConfigId) || !pcv_log_valid_uuid($pendingConfigId)) {
                $pendingConfigId = null;
            }
            pcv_log_set_config_id($activeConfigId);
            $sceneMode = $blockedActive['config']['scene_mode'] ?? 'pair';
            pcv_log_event('state.scope_skipped', 'info', 'skipped', 'scene_not_eligible', [
                'operation' => 'begin', 'scene_mode' => $sceneMode,
            ]);
            return pcv_result('unavailable', null, is_array($pendingScope),
                is_array($pendingScope) ? pcv_config_with_scene_mode($pendingScope) : null,
                is_string($activeConfigId) ? $activeConfigId : null,
                is_string($pendingConfigId) ? $pendingConfigId : null, 'scene_not_eligible');
        }
        $result = pcv_visible_state($state, $now);
        pcv_log_set_config_id($result['config_id']);
        return $result;
    } catch (Throwable $error) {
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'state_transition_failed', $error, ['operation' => 'begin']);
        return pcv_result('unavailable', null, false);
    } finally {
        pcv_unlock_state($handle);
    }
}
