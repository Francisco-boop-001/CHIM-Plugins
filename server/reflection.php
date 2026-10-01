<?php
declare(strict_types=1);

require_once __DIR__ . '/log.php';
require_once __DIR__ . '/state.php';
require_once __DIR__ . '/scope.php';

const PCV_REFLECTION_REGISTRY_MAX_BYTES = 8192;
const PCV_REFLECTION_REGISTRY_TTL = 600;

function pcvReflectionRegisterLastOutput(array $requestScope): void
{
    if (($requestScope['route'] ?? null) !== 'solo_reflection') {
        return;
    }
    try {
        $mindPoisoningAvailable = pcv_reflection_load_mind_poisoning();
    } catch (Throwable $error) {
        pcv_reflection_log('reflection.registration_error', 'registration', 'internal_error', $requestScope, $error);
        return;
    }
    if (!$mindPoisoningAvailable) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'mind_poisoning_unavailable', $requestScope);
        return;
    }

    try {
        $store = new \ChimMindPoisoning\PostgresStoreDb();
        pcv_reflection_register_with_store($requestScope, $store);
    } catch (Throwable $error) {
        pcv_reflection_log('reflection.registration_error', 'registration', 'database_unavailable', $requestScope, $error);
    }
}

function pcvReflectionEvaluateAck(array $gameRequest): void
{
    if (($gameRequest[0] ?? null) !== '_speech') {
        return;
    }
    $utteranceId = pcv_reflection_ack_utterance_id($gameRequest);
    if ($utteranceId === null) {
        return;
    }
    $probe = pcv_reflection_registry_probe();
    if ($probe['kind'] === 'missing'
        || ($probe['kind'] === 'ready' && $probe['record']['registration']['utterance_id'] !== $utteranceId)) {
        return;
    }
    if ($probe['kind'] === 'invalid' || $probe['kind'] === 'unavailable') {
        pcv_reflection_log('reflection.ack_error', 'ack', $probe['kind'] === 'invalid' ? 'registry_corrupt' : 'registry_unavailable');
        return;
    }
    if ($probe['record']['status'] !== 'registered') {
        pcv_reflection_log('reflection.ack_skipped', 'ack', 'claim_taken', $probe['record']);
        return;
    }
    if (!pcv_reflection_record_fresh($probe['record'])) {
        pcv_reflection_log('reflection.ack_skipped', 'ack', 'registration_stale', $probe['record']);
        return;
    }
    try {
        $mindPoisoningAvailable = pcv_reflection_load_mind_poisoning();
    } catch (Throwable $error) {
        pcv_reflection_log('reflection.ack_error', 'ack', 'internal_error', $probe['record'], $error);
        return;
    }
    if (!$mindPoisoningAvailable) {
        pcv_reflection_log('reflection.ack_error', 'ack', 'mind_poisoning_unavailable', $probe['record']);
        return;
    }

    try {
        $store = new \ChimMindPoisoning\PostgresStoreDb();
        pcv_reflection_evaluate_with_store($gameRequest, $store);
    } catch (Throwable $error) {
        pcv_reflection_log('reflection.ack_error', 'ack', 'database_unavailable', $probe['record'], $error);
    }
}

function pcvReflectionRevalidate(array $registration, string $phase, string $claimToken): bool
{
    return pcv_reflection_revalidate($registration, $phase, $claimToken, null, null);
}

function pcv_reflection_load_mind_poisoning(): bool
{
    $path = dirname(__DIR__, 2) . '/ext/mind_poisoning/reflection.php';
    if (is_link($path) || !is_file($path)) {
        return false;
    }
    require_once $path;
    return function_exists('ChimMindPoisoning\\mindPoisoningEvaluateReflection')
        && function_exists('ChimMindPoisoning\\reflectionSourceParts')
        && class_exists('ChimMindPoisoning\\PostgresStoreDb');
}

function pcv_reflection_log(
    string $event,
    string $phase,
    string $reason,
    ?array $scope = null,
    ?Throwable $error = null
): void {
    if (!function_exists('pcv_log_event')) {
        return;
    }
    $configId = is_string($scope['config_id'] ?? null) && pcv_log_valid_uuid($scope['config_id'])
        ? $scope['config_id'] : null;
    pcv_log_set_config_id($configId);
    if (is_array($scope) && pcv_reflection_valid_record($scope)) {
        $registration = $scope['registration'];
        pcv_log_set_correlation([
            'config_id' => $scope['config_id'],
            'event_id' => (string)$registration['event_id'],
            'utterance_id' => $registration['utterance_id'],
        ]);
    } else {
        pcv_log_set_correlation([]);
    }
    $actorId = $scope['actor_a_id'] ?? $scope['actor_id'] ?? ($scope['registration']['actor_id'] ?? null);
    if (!is_string($actorId) && is_int($actorId) && $actorId > 0) {
        $actorId = (string)$actorId;
    }
    $context = ['phase' => $phase, 'route' => 'solo_reflection'];
    if (pcv_log_valid_actor_id($actorId)) {
        $context['actor_a_id'] = $actorId;
    }
    if ($event === 'reflection.registration_error' || $event === 'reflection.ack_error') {
        if ($error !== null) {
            pcv_log_exception($event, 'error', 'failed', $reason, $error, $context);
        } else {
            pcv_log_event($event, 'error', 'failed', $reason, $context);
        }
        return;
    }
    if ($event === 'reflection.output_registered') {
        pcv_log_event($event, 'info', 'accepted', null, $context);
        return;
    }
    if ($event === 'reflection.evaluation_finished') {
        pcv_log_event($event, 'info', 'accepted', null, $context);
        return;
    }
    if ($event === 'reflection.observer_unavailable') {
        pcv_log_event($event, 'info', 'unavailable', $reason, $context);
        return;
    }
    pcv_log_event($event, 'info', 'skipped', $reason, $context);
}

function pcv_reflection_attach_mp_observer(object $requestLog): bool
{
    if (!method_exists($requestLog, 'observe')) {
        return false;
    }
    try {
        $requestLog->observe(static function (array $record, string $level): void {
            try {
                pcv_log_import_mp_record($record, $level);
            } catch (Throwable) {
                // Diagnostics must never alter Mind Poisoning's evaluation or persistence.
            }
        });
        return true;
    } catch (Throwable) {
        return false;
    }
}

function pcv_reflection_fresh_scope(?callable $reader): ?array
{
    try {
        if ($reader !== null) {
            $scope = $reader();
            return is_array($scope) ? $scope : null;
        }
        $identity = pcv_current_identity(true);
        $key = $identity['key'] ?? null;
        if (!is_string($key) || !pcv_valid_key($key)) {
            return null;
        }
        $scope = pcvReadResolvedScope();
        if (!is_array($scope)) {
            return null;
        }
        $scope['pcv_key'] = $key;
        return $scope;
    } catch (Throwable) {
        return null;
    }
}

function pcv_reflection_output_parts(mixed $output, string $actorName, string $utteranceId): ?array
{
    if (!is_string($output) || strlen($output) > 16384 || preg_match('//u', $output) !== 1 || !str_ends_with($output, "\r\n")) {
        return null;
    }
    $output = substr($output, 0, -2);
    if (str_contains($output, "\r") || str_contains($output, "\n")) {
        return null;
    }
    $wire = explode('|', $output);
    if (count($wire) !== 3 || $wire[0] !== $actorName || $wire[1] !== 'ScriptQueue') {
        return null;
    }
    $fields = explode('/', $wire[2]);
    if (count($fields) !== 8
        || trim($fields[0]) === '' || strlen($fields[0]) > 12000 || preg_match('//u', $fields[0]) !== 1
        || $fields[2] !== 'explicit_disable_rechat'
        || $fields[6] !== 'explicit_disable_rechat'
        || $fields[7] !== $utteranceId) {
        return null;
    }
    return ['subtitle' => trim($fields[0])];
}

function pcv_reflection_scope_matches(array $record, array $scope): bool
{
    $registration = $record['registration'] ?? null;
    $resolved = $scope['scope'] ?? null;
    return is_array($registration)
        && ($scope['status'] ?? null) === 'active'
        && is_string($scope['pcv_key'] ?? null)
        && is_string($record['pcv_key'] ?? null)
        && hash_equals($record['pcv_key'], $scope['pcv_key'])
        && ($scope['config_id'] ?? null) === $record['config_id']
        && ($scope['actor_a_id'] ?? null) === (string)$record['actor_id']
        && is_array($resolved)
        && ($resolved['scene_mode'] ?? null) === 'solo'
        && ($resolved['actor_b'] ?? null) === null
        && ($resolved['exclude_player'] ?? null) === true
        && is_string($resolved['actor_a'] ?? null)
        && hash_equals($record['actor_name'], $resolved['actor_a'])
        && $registration['actor_id'] === $record['actor_id']
        && $registration['actor_name'] === $record['actor_name']
        && $registration['config_id'] === $record['config_id'];
}

function pcv_reflection_request_is_eligible(array $requestScope): bool
{
    return function_exists('pcvSoloReflectionRequest')
        && pcvSoloReflectionRequest($requestScope)
        && function_exists('pcvRequestScopeModeMatches')
        && pcvRequestScopeModeMatches($requestScope)
        && is_string($requestScope['config_id'] ?? null)
        && pcv_log_valid_uuid($requestScope['config_id'])
        && pcv_log_valid_actor_id((string)($requestScope['actor_a_id'] ?? ''))
        && is_string($requestScope['scope']['actor_a'] ?? null)
        && trim($requestScope['scope']['actor_a']) !== ''
        && ($requestScope['scope']['actor_b'] ?? null) === null
        && ($requestScope['scope']['scene_mode'] ?? null) === 'solo'
        && ($requestScope['scope']['exclude_player'] ?? null) === true;
}

function pcv_reflection_register_with_store(
    array $requestScope,
    \ChimMindPoisoning\StoreDb $store,
    ?string $stateDirectory = null,
    ?callable $freshScopeReader = null
): string {
    if (($requestScope['route'] ?? null) !== 'solo_reflection') {
        return 'not_applicable';
    }
    if (!pcv_reflection_request_is_eligible($requestScope)) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'scope_ineligible', $requestScope);
        return 'scope_ineligible';
    }

    $baselineId = $requestScope['baseline_utterance_id'] ?? null;
    $utteranceId = $GLOBALS['SCRIPTLINE_UTTERANCE_ID'] ?? null;
    $baselineOutput = $requestScope['baseline_output_log'] ?? null;
    $output = is_array($GLOBALS['DEBUG_DATA'] ?? null) ? ($GLOBALS['DEBUG_DATA']['OUTPUT_LOG'] ?? null) : null;
    if (!array_key_exists('baseline_utterance_id', $requestScope)
        || !is_string($baselineOutput) || strlen($baselineOutput) > 16384 || !is_string($output)) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'output_unavailable', $requestScope);
        return 'output_unavailable';
    }
    if (!is_string($utteranceId)
        || preg_match('/\\Autt_[A-Za-z0-9_-]{8,128}\\z/D', $utteranceId) !== 1
        || ($baselineId !== null && (!is_string($baselineId)
            || preg_match('/\\Autt_[A-Za-z0-9_-]{8,128}\\z/D', $baselineId) !== 1
            || $utteranceId === $baselineId))) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'baseline_stale', $requestScope);
        return 'baseline_stale';
    }
    if (strlen($output) > 16384 || trim($output) === '' || preg_match('//u', $output) !== 1) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'output_malformed', $requestScope);
        return 'output_malformed';
    }
    if (hash_equals($baselineOutput, $output)) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'baseline_stale', $requestScope);
        return 'baseline_stale';
    }
    if (($GLOBALS['CHIM_EXECUTION_MODE'] ?? null) !== 'STANDARD'
        || !is_string($GLOBALS['HERIKA_NAME'] ?? null)
        || !hash_equals($requestScope['scope']['actor_a'], $GLOBALS['HERIKA_NAME'])) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'scope_ineligible', $requestScope);
        return 'scope_ineligible';
    }

    $freshScope = pcv_reflection_fresh_scope($freshScopeReader);
    if (!is_array($freshScope)) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'identity_changed', $requestScope);
        return 'identity_changed';
    }
    $actorId = filter_var($requestScope['actor_a_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($actorId === false) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'scope_ineligible', $requestScope);
        return 'scope_ineligible';
    }
    $wire = pcv_reflection_output_parts($output, $requestScope['scope']['actor_a'], $utteranceId);
    if ($wire === null) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'output_malformed', $requestScope);
        return 'output_malformed';
    }
    $expectedRecord = [
        'version' => 1,
        'pcv_key' => $freshScope['pcv_key'] ?? null,
        'config_id' => $requestScope['config_id'],
        'actor_id' => (int)$actorId,
        'actor_name' => $requestScope['scope']['actor_a'],
        'origin_request_type' => $requestScope['origin_request_type'],
        'origin_mode' => 'STANDARD',
        'route' => 'solo_reflection',
        'created_at' => time(),
        'status' => 'registered',
        'claim_token' => null,
        'registration' => null,
    ];
    $scopeRecord = $expectedRecord;
    $scopeRecord['registration'] = [
        'actor_id' => $expectedRecord['actor_id'],
        'actor_name' => $expectedRecord['actor_name'],
        'config_id' => $expectedRecord['config_id'],
    ];
    if (!is_string($expectedRecord['pcv_key'] ?? null) || !pcv_valid_key($expectedRecord['pcv_key'])
        || !pcv_reflection_scope_matches($scopeRecord, $freshScope)) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'scope_changed', $requestScope);
        return 'scope_changed';
    }

    try {
        $profile = $store->activePlaythrough();
    } catch (Throwable $error) {
        pcv_reflection_log('reflection.registration_error', 'registration', 'database_unavailable', $requestScope, $error);
        return 'database_unavailable';
    }
    $playthroughId = is_array($profile) ? ($profile['id'] ?? null) : null;
    if (!is_string($playthroughId) || $playthroughId === '' || strlen($playthroughId) > 64) {
        pcv_reflection_log('reflection.registration_error', 'registration', 'database_unavailable', $requestScope);
        return 'database_unavailable';
    }
    try {
        $source = $store->acknowledgedEvent($utteranceId);
    } catch (Throwable $error) {
        pcv_reflection_log('reflection.registration_error', 'registration', 'database_unavailable', $requestScope, $error);
        return 'database_unavailable';
    }
    if (!is_array($source) || ($source['utterance_id'] ?? null) !== $utteranceId
        || !is_int($source['event_id'] ?? null) || $source['event_id'] < 1) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'event_unmatched', $requestScope);
        return 'event_unmatched';
    }
    if (($source['delivery_state'] ?? null) === 'aborted') {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'source_aborted', $requestScope);
        return 'source_aborted';
    }
    if (!in_array($source['delivery_state'] ?? null, ['emitted', 'spoken'], true)
        || !is_string($source['source_data'] ?? null)
        || !function_exists('ChimMindPoisoning\\reflectionSourceParts')) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'event_unmatched', $requestScope);
        return 'event_unmatched';
    }
    $parts = \ChimMindPoisoning\reflectionSourceParts($source['source_data']);
    $targets = is_array($parts) ? ($parts['target']['targets'] ?? null) : null;
    if (!is_array($parts) || !is_string($parts['speaker'] ?? null)
        || !is_array($targets) || count($targets) !== 1
        || !is_string($targets[0] ?? null)
        || strcasecmp(trim($targets[0]), 'explicit_disable_rechat') !== 0) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'sentinel_mismatch', $requestScope);
        return 'sentinel_mismatch';
    }
    if (strcasecmp(trim($parts['speaker']), $expectedRecord['actor_name']) !== 0) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'event_unmatched', $requestScope);
        return 'event_unmatched';
    }

    $registration = [
        'event_id' => $source['event_id'],
        'utterance_id' => $utteranceId,
        'actor_id' => $expectedRecord['actor_id'],
        'actor_name' => $expectedRecord['actor_name'],
        'playthrough_id' => $playthroughId,
        'config_id' => $expectedRecord['config_id'],
        'rechat_target_hint' => 'explicit_disable_rechat',
        'speech_hash' => hash('sha256', $wire['subtitle']),
    ];
    $record = $expectedRecord;
    $record['registration'] = $registration;
    if (!pcv_reflection_valid_record($record)) {
        pcv_reflection_log('reflection.registration_skipped', 'registration', 'scope_ineligible', $requestScope);
        return 'scope_ineligible';
    }

    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, true, LOCK_EX);
        $skipReason = null;
        try {
            $existing = pcv_reflection_read_locked($directory);
            if ($existing['kind'] === 'invalid') {
                $skipReason = 'registry_corrupt';
            } elseif ($existing['kind'] === 'unavailable') {
                $skipReason = 'registry_unavailable';
            } elseif ($existing['kind'] === 'ready'
                && $existing['record']['status'] === 'claimed'
                && pcv_reflection_record_fresh($existing['record'])) {
                $skipReason = 'claim_taken';
            } else {
                pcv_reflection_write_locked($directory, $record);
            }
        } finally {
            pcv_unlock_state($handle);
        }
        if ($skipReason !== null) {
            $event = in_array($skipReason, ['registry_corrupt', 'registry_unavailable'], true)
                ? 'reflection.registration_error' : 'reflection.registration_skipped';
            pcv_reflection_log($event, 'registration', $skipReason, $requestScope);
            return $skipReason;
        }
    } catch (Throwable $error) {
        pcv_reflection_log('reflection.registration_error', 'registration', 'registry_unavailable', $requestScope, $error);
        return 'registry_unavailable';
    }

    pcv_reflection_log('reflection.output_registered', 'registration', '', $record);
    return 'registered';
}

function pcv_reflection_evaluate_with_store(
    array $gameRequest,
    \ChimMindPoisoning\StoreDb $store,
    ?callable $requestModel = null,
    ?string $stateDirectory = null,
    ?callable $freshScopeReader = null,
    ?\ChimMindPoisoning\RequestLog $requestLog = null
): string {
    $utteranceId = pcv_reflection_ack_utterance_id($gameRequest);
    if ($utteranceId === null) {
        return 'not_applicable';
    }
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, false, LOCK_EX);
        if ($handle === null) {
            return 'registration_missing';
        }
        $registryKind = 'unavailable';
        try {
            $loaded = pcv_reflection_read_locked($directory);
            $registryKind = $loaded['kind'];
            if ($registryKind === 'ready') {
                $record = $loaded['record'];
            }
        } finally {
            pcv_unlock_state($handle);
        }
        if ($registryKind === 'missing') {
            return 'registration_missing';
        }
        if ($registryKind === 'invalid' || $registryKind === 'unavailable') {
            pcv_reflection_log('reflection.ack_error', 'ack', $registryKind === 'invalid' ? 'registry_corrupt' : 'registry_unavailable');
            return $registryKind === 'invalid' ? 'registry_corrupt' : 'registry_unavailable';
        }
    } catch (Throwable $error) {
        pcv_reflection_log('reflection.ack_error', 'ack', 'registry_unavailable', null, $error);
        return 'registry_unavailable';
    }
    if ($record['registration']['utterance_id'] !== $utteranceId) {
        return 'registration_missing';
    }
    if ($record['status'] !== 'registered') {
        pcv_reflection_log('reflection.ack_skipped', 'ack', 'claim_taken', $record);
        return 'claim_taken';
    }
    if (!pcv_reflection_record_fresh($record)) {
        pcv_reflection_log('reflection.ack_skipped', 'ack', 'registration_stale', $record);
        return 'registration_stale';
    }

    $ack = \ChimMindPoisoning\reflectionAckPayload($gameRequest);
    if (!is_array($ack) || $ack['utterance_id'] !== $utteranceId
        || strcasecmp($ack['speaker'], $record['actor_name']) !== 0
        || !hash_equals($record['registration']['speech_hash'], hash('sha256', $ack['speech']))) {
        pcv_reflection_log('reflection.ack_skipped', 'ack', 'ack_mismatch', $record);
        return 'ack_mismatch';
    }
    try {
        $profile = $store->activePlaythrough();
    } catch (Throwable $error) {
        pcv_reflection_log('reflection.ack_error', 'ack', 'database_unavailable', $record, $error);
        return 'database_unavailable';
    }
    $playerName = is_array($profile) && is_string($profile['player_name'] ?? null) ? trim($profile['player_name']) : '';
    if (!is_array($profile) || ($profile['id'] ?? null) !== $record['registration']['playthrough_id']
        || $playerName === '' || !\ChimMindPoisoning\reflectionPlayerTransport($ack['listener'], $playerName)) {
        pcv_reflection_log('reflection.ack_skipped', 'ack', 'ack_mismatch', $record);
        return 'ack_mismatch';
    }
    $freshScope = pcv_reflection_fresh_scope($freshScopeReader);
    if (!is_array($freshScope)) {
        pcv_reflection_log('reflection.ack_skipped', 'ack', 'identity_changed', $record);
        return 'identity_changed';
    }
    if (!pcv_reflection_scope_matches($record, $freshScope)) {
        pcv_reflection_log('reflection.ack_skipped', 'ack', 'scope_changed', $record);
        return 'scope_changed';
    }

    $claimFailure = null;
    try {
        $handle = pcv_lock_state($directory, false, LOCK_EX);
        if ($handle === null) {
            return 'registration_missing';
        }
        try {
            $latest = pcv_reflection_read_locked($directory);
            if ($latest['kind'] === 'invalid') {
                $claimFailure = 'registry_corrupt';
            } elseif ($latest['kind'] === 'unavailable') {
                $claimFailure = 'registry_unavailable';
            } elseif ($latest['kind'] === 'missing') {
                $claimFailure = 'registration_missing';
            } elseif ($latest['record']['status'] !== 'registered'
                || $latest['record']['registration']['utterance_id'] !== $utteranceId
                || !pcv_reflection_records_match($latest['record'], $record)) {
                $claimFailure = 'claim_taken';
            } else {
                $record = $latest['record'];
                $record['status'] = 'claimed';
                $record['claim_token'] = bin2hex(random_bytes(16));
                pcv_reflection_write_locked($directory, $record);
            }
        } finally {
            pcv_unlock_state($handle);
        }
    } catch (Throwable $error) {
        pcv_reflection_log('reflection.ack_error', 'ack', 'registry_unavailable', $record, $error);
        return 'registry_unavailable';
    }
    if ($claimFailure !== null) {
        $event = in_array($claimFailure, ['registry_corrupt', 'registry_unavailable'], true)
            ? 'reflection.ack_error' : 'reflection.ack_skipped';
        pcv_reflection_log($event, 'ack', $claimFailure, $record);
        return $claimFailure;
    }

    $revalidationReason = null;
    pcv_log_set_config_id($record['config_id']);
    pcv_log_set_correlation([
        'event_id' => (string)$record['registration']['event_id'],
        'utterance_id' => $record['registration']['utterance_id'],
    ]);
    try {
        $requestLog ??= new \ChimMindPoisoning\RequestLog();
        if (!pcv_reflection_attach_mp_observer($requestLog)) {
            pcv_reflection_log('reflection.observer_unavailable', 'ack', 'observer_unsupported', $record);
        }
        $status = \ChimMindPoisoning\mindPoisoningEvaluateReflection(
            $record['registration'],
            $gameRequest,
            $store,
            static function (array $registration, string $phase) use ($record, $stateDirectory, $freshScopeReader, &$revalidationReason): bool {
                return pcv_reflection_revalidate(
                    $registration,
                    $phase,
                    $record['claim_token'],
                    $stateDirectory,
                    $freshScopeReader,
                    $revalidationReason
                );
            },
            $requestModel,
            $requestLog
        );
        if ($status === 'committed') {
            pcv_reflection_log('reflection.evaluation_finished', 'ack', '', $record);
        }
    } catch (Throwable $error) {
        pcv_reflection_log('reflection.ack_error', 'ack', 'internal_error', $record, $error);
        $status = 'failed';
        $errorLogged = true;
    } finally {
        $consumed = pcv_reflection_consume_claim($record, $stateDirectory);
        if (!$consumed) {
            pcv_reflection_log('reflection.ack_error', 'ack', 'registry_unavailable', $record);
        }
    }

    if ($status !== 'committed' && !($errorLogged ?? false)) {
        if (in_array($revalidationReason, ['registry_corrupt', 'registry_unavailable'], true)) {
            pcv_reflection_log('reflection.ack_error', 'ack', $revalidationReason, $record);
        } elseif ($status === 'failed') {
            pcv_reflection_log('reflection.ack_error', 'ack', 'evaluation_failed', $record);
        } else {
            $reason = in_array($revalidationReason, ['identity_changed', 'scope_changed'], true)
                ? $revalidationReason : 'evaluation_rejected';
            pcv_reflection_log('reflection.ack_skipped', 'ack', $reason, $record);
        }
    }
    return $status;
}

function pcv_reflection_ack_utterance_id(array $gameRequest): ?string
{
    $raw = $gameRequest[3] ?? null;
    if (($gameRequest[0] ?? null) !== '_speech' || !is_string($raw) || strlen($raw) > 16384 || preg_match('//u', $raw) !== 1) {
        return null;
    }
    try {
        $payload = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    $utteranceId = $payload instanceof stdClass ? ($payload->utterance_id ?? null) : null;
    if (!is_string($utteranceId)) {
        return null;
    }
    $utteranceId = trim($utteranceId);
    return preg_match('/\\Autt_[A-Za-z0-9_-]{8,128}\\z/D', $utteranceId) === 1
        ? $utteranceId : null;
}

function pcv_reflection_revalidate(
    array $registration,
    string $phase,
    string $claimToken,
    ?string $stateDirectory,
    ?callable $freshScopeReader,
    ?string &$reason = null
): bool {
    $reason = null;
    if (!in_array($phase, ['pre_model', 'transaction'], true)
        || preg_match('/\\A[a-f0-9]{32}\\z/D', $claimToken) !== 1
        || !pcv_reflection_valid_registration($registration)) {
        $reason = 'registration_stale';
        return false;
    }
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, false, LOCK_SH);
        if ($handle === null) {
            $reason = 'registration_stale';
            return false;
        }
        try {
            $loaded = pcv_reflection_read_locked($directory);
            if ($loaded['kind'] !== 'ready') {
                $reason = match ($loaded['kind']) {
                    'invalid' => 'registry_corrupt',
                    'unavailable' => 'registry_unavailable',
                    default => 'registration_stale',
                };
                return false;
            }
            $record = $loaded['record'];
        } finally {
            pcv_unlock_state($handle);
        }
    } catch (Throwable) {
        $reason = 'registry_unavailable';
        return false;
    }
    if ($record['status'] !== 'claimed' || !hash_equals($record['claim_token'], $claimToken)
        || !pcv_reflection_record_fresh($record)
        || !pcv_reflection_registration_matches($record['registration'], $registration)) {
        $reason = 'registration_stale';
        return false;
    }
    $scope = pcv_reflection_fresh_scope($freshScopeReader);
    if (!is_array($scope)) {
        $reason = 'identity_changed';
        return false;
    }
    if (!pcv_reflection_scope_matches($record, $scope)) {
        $reason = is_string($scope['pcv_key'] ?? null) && $scope['pcv_key'] !== $record['pcv_key']
            ? 'identity_changed' : 'scope_changed';
        return false;
    }
    return true;
}

function pcv_reflection_consume_claim(array $claimedRecord, ?string $stateDirectory): bool
{
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, false, LOCK_EX);
        if ($handle === null) {
            return false;
        }
        try {
            $loaded = pcv_reflection_read_locked($directory);
            if ($loaded['kind'] === 'ready'
                && $loaded['record']['status'] === 'claimed'
                && hash_equals($loaded['record']['claim_token'], $claimedRecord['claim_token'] ?? '')
                && pcv_reflection_records_match($loaded['record'], $claimedRecord)) {
                $record = $loaded['record'];
                $record['status'] = 'consumed';
                pcv_reflection_write_locked($directory, $record);
                return true;
            }
            return false;
        } finally {
            pcv_unlock_state($handle);
        }
    } catch (Throwable) {
        // The persisted claimed state still prevents a duplicate provider attempt.
        return false;
    }
}

function pcv_reflection_records_match(array $left, array $right): bool
{
    return pcv_reflection_valid_record($left) && pcv_reflection_valid_record($right)
        && $left['pcv_key'] === $right['pcv_key']
        && $left['config_id'] === $right['config_id']
        && $left['actor_id'] === $right['actor_id']
        && $left['actor_name'] === $right['actor_name']
        && $left['origin_request_type'] === $right['origin_request_type']
        && $left['created_at'] === $right['created_at']
        && pcv_reflection_registration_matches($left['registration'], $right['registration']);
}

function pcv_reflection_registration_matches(array $left, array $right): bool
{
    foreach (['event_id', 'utterance_id', 'actor_id', 'actor_name', 'playthrough_id', 'config_id', 'rechat_target_hint', 'speech_hash'] as $key) {
        if (($left[$key] ?? null) !== ($right[$key] ?? null)) {
            return false;
        }
    }
    return pcv_reflection_valid_registration($left) && pcv_reflection_valid_registration($right);
}

function pcv_reflection_valid_registration(array $registration): bool
{
    $keys = ['event_id', 'utterance_id', 'actor_id', 'actor_name', 'playthrough_id', 'config_id', 'rechat_target_hint', 'speech_hash'];
    $actual = array_keys($registration);
    sort($actual, SORT_STRING);
    sort($keys, SORT_STRING);
    return $actual === $keys
        && is_int($registration['event_id']) && $registration['event_id'] > 0
        && is_string($registration['utterance_id']) && preg_match('/\\Autt_[A-Za-z0-9_-]{8,128}\\z/D', $registration['utterance_id']) === 1
        && is_int($registration['actor_id']) && $registration['actor_id'] > 0
        && is_string($registration['actor_name']) && trim($registration['actor_name']) !== ''
        && strlen($registration['actor_name']) <= 256 && preg_match('//u', $registration['actor_name']) === 1
        && is_string($registration['playthrough_id']) && $registration['playthrough_id'] !== ''
        && strlen($registration['playthrough_id']) <= 64
        && is_string($registration['config_id']) && pcv_log_valid_uuid($registration['config_id'])
        && $registration['rechat_target_hint'] === 'explicit_disable_rechat'
        && is_string($registration['speech_hash']) && preg_match('/\\A[a-f0-9]{64}\\z/D', $registration['speech_hash']) === 1;
}

function pcv_reflection_valid_record(array $record): bool
{
    $keys = ['version', 'pcv_key', 'config_id', 'actor_id', 'actor_name', 'origin_request_type', 'origin_mode', 'route', 'created_at', 'status', 'claim_token', 'registration'];
    $actual = array_keys($record);
    sort($actual, SORT_STRING);
    sort($keys, SORT_STRING);
    $status = $record['status'] ?? null;
    $claim = $record['claim_token'] ?? null;
    return $actual === $keys
        && ($record['version'] ?? null) === 1
        && is_string($record['pcv_key'] ?? null) && pcv_valid_key($record['pcv_key'])
        && is_string($record['config_id'] ?? null) && pcv_log_valid_uuid($record['config_id'])
        && is_int($record['actor_id'] ?? null) && $record['actor_id'] > 0
        && is_string($record['actor_name'] ?? null) && trim($record['actor_name']) !== ''
        && strlen($record['actor_name']) <= 256 && preg_match('//u', $record['actor_name']) === 1
        && in_array($record['origin_request_type'] ?? null, ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'], true)
        && ($record['origin_mode'] ?? null) === 'STANDARD'
        && ($record['route'] ?? null) === 'solo_reflection'
        && is_int($record['created_at'] ?? null) && $record['created_at'] > 0
        && in_array($status, ['registered', 'claimed', 'consumed'], true)
        && (($status === 'registered' && $claim === null)
            || ($status !== 'registered' && is_string($claim) && preg_match('/\\A[a-f0-9]{32}\\z/D', $claim) === 1))
        && is_array($record['registration'] ?? null)
        && pcv_reflection_valid_registration($record['registration'])
        && $record['registration']['config_id'] === $record['config_id']
        && $record['registration']['actor_id'] === $record['actor_id']
        && $record['registration']['actor_name'] === $record['actor_name'];
}

function pcv_reflection_record_fresh(array $record): bool
{
    $age = time() - ($record['created_at'] ?? 0);
    return $age >= 0 && $age <= PCV_REFLECTION_REGISTRY_TTL;
}

function pcv_reflection_read_locked(string $directory): array
{
    $path = $directory . DIRECTORY_SEPARATOR . 'reflection.json';
    if (is_link($path)) {
        return ['kind' => 'invalid'];
    }
    if (!file_exists($path)) {
        return ['kind' => 'missing'];
    }
    if (!is_file($path)) {
        return ['kind' => 'invalid'];
    }
    clearstatcache(true, $path);
    $size = @filesize($path);
    $mode = @fileperms($path);
    if (!is_int($size) || $size > PCV_REFLECTION_REGISTRY_MAX_BYTES || !is_int($mode) || ($mode & 0077) !== 0) {
        return ['kind' => 'invalid'];
    }
    $contents = @file_get_contents($path);
    if (!is_string($contents)) {
        return ['kind' => 'unavailable'];
    }
    try {
        $record = json_decode($contents, true, 12, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return ['kind' => 'invalid'];
    }
    if (!is_array($record) || !pcv_reflection_valid_record($record)) {
        return ['kind' => 'invalid'];
    }
    return ['kind' => 'ready', 'record' => $record];
}

function pcv_reflection_registry_probe(?string $stateDirectory = null): array
{
    try {
        $directory = pcv_state_directory($stateDirectory);
        $handle = pcv_lock_state($directory, false, LOCK_SH);
        if ($handle === null) {
            return ['kind' => 'missing'];
        }
        try {
            return pcv_reflection_read_locked($directory);
        } finally {
            pcv_unlock_state($handle);
        }
    } catch (Throwable) {
        return ['kind' => 'unavailable'];
    }
}

function pcv_reflection_write_locked(string $directory, array $record): void
{
    if (!pcv_reflection_valid_record($record)) {
        throw new RuntimeException('Invalid reflection registration.');
    }
    $path = $directory . DIRECTORY_SEPARATOR . 'reflection.json';
    if (is_link($path)) {
        throw new RuntimeException('Reflection registry is not safe.');
    }
    $contents = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    if (strlen($contents) > PCV_REFLECTION_REGISTRY_MAX_BYTES) {
        throw new RuntimeException('Reflection registry is too large.');
    }
    $temporary = tempnam($directory, '.reflection-');
    if ($temporary === false) {
        throw new RuntimeException('Reflection registry is unavailable.');
    }
    try {
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('Reflection registry is unavailable.');
        }
        @chmod($temporary, 0600);
        if (is_link($path) || !@rename($temporary, $path)) {
            throw new RuntimeException('Reflection registry is unavailable.');
        }
        @chmod($path, 0600);
    } finally {
        if (is_file($temporary) && !is_link($temporary)) {
            @unlink($temporary);
        }
    }
}
