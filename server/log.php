<?php
declare(strict_types=1);

const PCV_LOG_SCHEMA_VERSION = 1;
const PCV_LOG_MAX_FILES = 5;
const PCV_LOG_MAX_FILE_BYTES = 10485760;
const PCV_LOG_MAX_ENTRY_BYTES = 8192;
const PCV_LOG_DEBUG_MAX_SECONDS = 3600;

function pcv_log_new_uuid(): ?string
{
    try {
        $bytes = random_bytes(16);
    } catch (Throwable) {
        return null;
    }

    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function pcv_log_request_id_fallback(): string
{
    static $counter = 0;
    $counter++;
    $clock = function_exists('hrtime') ? (string)hrtime(true) : sprintf('%.6F', microtime(true));
    $seed = (string)getmypid() . ':' . $clock . ':' . $counter;
    return substr(hash('sha256', $seed), 0, 32);
}

function pcv_log_debug_setting_is_active(): bool
{
    $until = getenv('PCV_LOG_DEBUG_UNTIL');
    $now = time();
    return is_string($until) && preg_match('/^[1-9][0-9]{0,11}$/D', $until) === 1
        && (int)$until > $now && (int)$until <= $now + PCV_LOG_DEBUG_MAX_SECONDS;
}

function &pcv_log_request_context(): array
{
    static $request = null;
    if (!is_array($request)) {
        $start = function_exists('hrtime') ? hrtime(true) : null;
        $requestId = pcv_log_new_uuid();
        $request = [
            'request_id' => $requestId ?? pcv_log_request_id_fallback(),
            'started_ns' => is_int($start) ? $start : null,
            'config_id' => null,
            'debug_enabled' => pcv_log_debug_setting_is_active(),
            'fallback_reported' => false,
            'test_directory' => null,
        ];
    }
    return $request;
}

function pcv_log_begin_request(?string $configId = null): void
{
    $request =& pcv_log_request_context();
    if ($configId !== null) {
        pcv_log_set_config_id($configId);
    }
}

function pcv_log_request_id(): string
{
    $request =& pcv_log_request_context();
    return $request['request_id'];
}

function pcv_log_set_config_id(?string $configId): void
{
    $request =& pcv_log_request_context();
    $request['config_id'] = is_string($configId) && pcv_log_valid_uuid($configId) ? $configId : null;
}

function pcv_log_set_playthrough_ref(?string $playthroughKey): void
{
    $request =& pcv_log_request_context();
    $request['playthrough_ref'] = is_string($playthroughKey)
        && preg_match('/^[a-f0-9]{64}$/D', $playthroughKey) === 1
        ? substr(hash('sha256', $playthroughKey), 0, 16)
        : null;
}

function pcv_log_valid_uuid(string $value): bool
{
    return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value) === 1;
}

function pcv_log_event_rules(): array
{
    static $rules = [
        'state.scope_staged' => ['severity' => 'info', 'outcome' => 'ok', 'context' => ['action', 'scene_mode', 'actor_a_id', 'actor_b_id', 'exclude_player', 'bystander_mode']],
        'state.scope_activated' => ['severity' => 'info', 'outcome' => 'ok', 'context' => ['action', 'scene_mode', 'actor_a_id', 'actor_b_id', 'exclude_player', 'bystander_mode']],
        'state.scope_skipped' => ['severity' => 'info', 'outcome' => 'skipped', 'context' => ['operation', 'scene_mode']],
        'state.scope_expired' => ['severity' => 'info', 'outcome' => 'expired', 'context' => ['target']],
        'state.scope_invalidated' => ['severity' => 'warning', 'outcome' => 'invalidated', 'context' => ['active_config_id', 'pending_config_id']],
        'state.unavailable' => ['severity' => 'error', 'outcome' => 'unavailable', 'context' => ['operation']],
        'state.presence_refreshed' => ['severity' => 'debug', 'outcome' => 'accepted', 'context' => ['actor_count']],
        'state.presence_rejected' => ['severity' => 'warning', 'outcome' => 'rejected', 'context' => ['operation']],
        'ui.page_open' => ['severity' => 'info', 'outcome' => 'ok', 'context' => []],
        'ui.unavailable' => ['severity' => 'error', 'outcome' => 'unavailable', 'context' => ['operation']],
        'ui.scope_stage_accepted' => ['severity' => 'info', 'outcome' => 'ok', 'context' => ['action', 'status', 'pending']],
        'ui.scope_stage_rejected' => ['severity' => 'warning', 'outcome' => 'rejected', 'context' => []],
        'ui.scope_stage_failed' => ['severity' => 'error', 'outcome' => 'failed', 'context' => ['action', 'operation']],
        'routing.request_started' => ['severity' => 'info', 'outcome' => 'ok', 'context' => ['request_type']],
        'routing.request_prepared' => ['severity' => 'info', 'outcome' => 'ok', 'context' => ['phase', 'route', 'actor_a_id', 'actor_b_id', 'speaker_id', 'exclude_player', 'bystander_mode']],
        'routing.request_skipped' => ['severity' => 'info', 'outcome' => 'skipped', 'context' => ['phase', 'request_type', 'state_status', 'mode']],
        'routing.request_blocked' => ['severity' => 'warning', 'outcome' => 'blocked', 'context' => ['phase', 'request_type', 'actor_a_id', 'actor_b_id']],
        'routing.request_error' => ['severity' => 'error', 'outcome' => 'failed', 'context' => ['phase', 'request_type', 'actor_a_id', 'actor_b_id']],
        'routing.request_detail' => ['severity' => 'debug', 'outcome' => 'ok', 'context' => ['phase', 'decision', 'request_type', 'actor_a_id', 'actor_b_id', 'speaker_id', 'audience_before_count', 'audience_after_count', 'present_before_count', 'present_after_count']],
        'reflection.registration_skipped' => ['severity' => 'info', 'outcome' => 'skipped', 'context' => ['phase', 'route', 'actor_a_id']],
        'reflection.ack_skipped' => ['severity' => 'info', 'outcome' => 'skipped', 'context' => ['phase', 'route', 'actor_a_id']],
        'reflection.registration_error' => ['severity' => 'error', 'outcome' => 'failed', 'context' => ['phase', 'route', 'actor_a_id']],
        'reflection.ack_error' => ['severity' => 'error', 'outcome' => 'failed', 'context' => ['phase', 'route', 'actor_a_id']],
        'reflection.output_registered' => ['severity' => 'info', 'outcome' => 'accepted', 'context' => ['phase', 'route', 'actor_a_id']],
        'reflection.evaluation_finished' => ['severity' => 'info', 'outcome' => 'accepted', 'context' => ['phase', 'route', 'actor_a_id']],
    ];
    return $rules;
}

function pcv_log_reason_codes(): array
{
    return [
        'active_ttl', 'pending_ttl', 'invalid_state_key', 'identity_unavailable', 'state_unavailable',
        'corrupt_state', 'symlinked_state', 'not_regular_file', 'state_stat_failed', 'state_too_large',
        'state_read_failed', 'invalid_json', 'invalid_state', 'state_stage_failed', 'state_transition_failed', 'profile_lookup_failed',
        'session_unavailable', 'catalog_unavailable', 'readback_mismatch',
        'invalid_configuration', 'actor_unavailable', 'actor_ambiguous', 'missing_settings', 'access_denied', 'csrf_failed',
        'internal_error', 'playthrough_changed', 'scope_off', 'scope_pending', 'unsupported_mode', 'unsupported_special_mode',
        'invalid_input_prefix', 'invalid_input_encoding', 'empty_input', 'malformed_rechat', 'rechat_speaker_outside_pair', 'speaker_outside_pair',
        'solo_rechat_unsupported', 'solo_unrouted_request', 'pair_continuation_player_excluded', 'mode_changed',
        'scope_ineligible', 'baseline_stale', 'output_unavailable', 'output_malformed', 'sentinel_mismatch',
        'event_unmatched', 'registry_unavailable', 'registry_corrupt', 'registration_missing', 'registration_stale',
        'claim_taken', 'ack_mismatch', 'source_aborted', 'scope_changed', 'identity_changed',
        'mind_poisoning_unavailable', 'evaluation_rejected', 'database_unavailable',
        'actors_unavailable', 'player_identity_unavailable', 'profile_switch_failed', 'actions_unavailable',
        'context_unavailable', 'hook_exception', 'debug_detail', 'presence_unavailable', 'presence_stale',
        'presence_missing', 'presence_invalid', 'presence_key_mismatch', 'pair_not_eligible', 'scene_not_eligible',
    ];
}

function pcv_log_reason_allowed(string $event, ?string $reason): bool
{
    if ($reason === null) {
        return !in_array($event, ['state.scope_expired', 'state.unavailable', 'ui.unavailable', 'ui.scope_stage_rejected', 'ui.scope_stage_failed',
            'routing.request_skipped', 'routing.request_blocked', 'routing.request_error', 'state.scope_skipped'], true);
    }

    if (!in_array($reason, pcv_log_reason_codes(), true)) {
        return false;
    }
    if ($event === 'state.scope_expired') {
        return in_array($reason, ['active_ttl', 'pending_ttl'], true);
    }
    if ($event === 'state.scope_skipped') {
        return $reason === 'scene_not_eligible';
    }
    if ($event === 'state.presence_rejected') {
        return $reason === 'presence_stale';
    }
    if ($event === 'ui.scope_stage_rejected') {
        return in_array($reason, ['invalid_configuration', 'actor_unavailable', 'actor_ambiguous', 'missing_settings', 'state_unavailable', 'access_denied', 'csrf_failed', 'internal_error', 'presence_missing', 'presence_stale', 'presence_unavailable', 'presence_invalid', 'presence_key_mismatch'], true);
    }
    if ($event === 'ui.unavailable') {
        return in_array($reason, ['session_unavailable', 'identity_unavailable', 'catalog_unavailable', 'state_unavailable'], true);
    }
    if ($event === 'ui.scope_stage_failed') {
        return in_array($reason, ['state_unavailable', 'readback_mismatch', 'internal_error'], true);
    }
    if ($event === 'routing.request_skipped') {
        return in_array($reason, ['scope_off', 'scope_pending', 'identity_unavailable', 'unsupported_mode', 'scene_not_eligible'], true);
    }
    if ($event === 'routing.request_blocked') {
        return in_array($reason, ['unsupported_special_mode', 'invalid_input_prefix', 'invalid_input_encoding', 'empty_input', 'malformed_rechat', 'rechat_speaker_outside_pair', 'speaker_outside_pair', 'solo_rechat_unsupported', 'solo_unrouted_request', 'pair_continuation_player_excluded', 'mode_changed', 'scene_not_eligible'], true);
    }
    if ($event === 'routing.request_error') {
        return in_array($reason, ['state_unavailable', 'actors_unavailable', 'player_identity_unavailable', 'profile_switch_failed', 'actions_unavailable', 'context_unavailable', 'hook_exception'], true);
    }
    if ($event === 'state.unavailable') {
        return in_array($reason, ['invalid_state_key', 'identity_unavailable', 'state_unavailable', 'corrupt_state', 'symlinked_state', 'not_regular_file', 'state_stat_failed', 'state_too_large', 'state_read_failed', 'invalid_json', 'invalid_state', 'state_stage_failed', 'state_transition_failed', 'profile_lookup_failed', 'catalog_unavailable', 'presence_unavailable', 'presence_stale', 'presence_missing', 'presence_invalid', 'presence_key_mismatch', 'unsupported_special_mode'], true);
    }
    if ($event === 'state.scope_invalidated') {
        return $reason === 'playthrough_changed';
    }
    if ($event === 'routing.request_detail') {
        return $reason === 'debug_detail';
    }
    if (in_array($event, ['reflection.registration_skipped', 'reflection.ack_skipped'], true)) {
        return in_array($reason, [
            'scope_ineligible', 'baseline_stale', 'output_unavailable', 'output_malformed', 'sentinel_mismatch',
            'event_unmatched', 'registry_unavailable', 'registry_corrupt', 'registration_missing', 'registration_stale',
            'claim_taken', 'ack_mismatch', 'source_aborted', 'scope_changed', 'identity_changed',
            'mind_poisoning_unavailable', 'evaluation_rejected',
        ], true);
    }
    if (in_array($event, ['reflection.registration_error', 'reflection.ack_error'], true)) {
        return in_array($reason, [
            'registry_unavailable', 'registry_corrupt', 'database_unavailable',
            'mind_poisoning_unavailable', 'internal_error',
        ], true);
    }
    if ($event === 'reflection.output_registered') {
        return $reason === null;
    }
    if ($event === 'reflection.evaluation_finished') {
        return $reason === null;
    }
    return false;
}

function pcv_log_enum_values(string $key): array
{
    static $values = [
        'action' => ['enable', 'end'],
        'scene_mode' => ['pair', 'solo'],
        'bystander_mode' => ['exclude', 'silent'],
        'target' => ['active', 'pending'],
        'operation' => ['session', 'identity', 'catalog', 'read', 'stage', 'readback', 'begin', 'presence_capture', 'presence_read', 'presence_invalidate'],
        'status' => ['active', 'pending', 'off', 'unavailable'],
        'request_type' => ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s', 'rechat', 'narrator_inputtext', 'chat', 'prechat', 'continue', 'continue_group', 'instruction', 'bored', 'narration', 'other'],
        'phase' => ['preprocessing', 'prerequest', 'context_pre', 'context', 'registration', 'ack'],
        'route' => ['scene_direction', 'player_speech', 'rechat_clamped', 'pair_continuation', 'generated_event', 'solo_reflection'],
        'state_status' => ['off', 'pending', 'identity_unavailable', 'active', 'unavailable'],
        'mode' => ['standard', 'close', 'whisper', 'autochat', 'other'],
        'decision' => ['non_candidate_request', 'director_excluded', 'scope_off', 'scope_pending', 'identity_unavailable', 'unsupported_mode', 'input_rewritten', 'player_speech_preserved', 'solo_reflection_routed', 'rechat_clamped', 'continuation_routed', 'responder_selected', 'context_prepared', 'action_constraints_refreshed', 'action_instructions_removed'],
    ];
    return $values[$key] ?? [];
}

function pcv_log_valid_actor_id($value): bool
{
    return is_string($value) && strlen($value) <= 20
        && preg_match('/^[1-9][0-9]*$/D', $value) === 1;
}

function pcv_log_clean_context(string $event, array $context): array
{
    $rules = pcv_log_event_rules()[$event]['context'] ?? [];
    $clean = [];
    foreach ($rules as $key) {
        if (!array_key_exists($key, $context)) {
            continue;
        }
        $value = $context[$key];
        if (in_array($key, ['actor_a_id', 'actor_b_id', 'speaker_id'], true)) {
            if (pcv_log_valid_actor_id($value)) {
                $clean[$key] = $value;
            }
        } elseif (in_array($key, ['exclude_player', 'pending'], true)) {
            if (is_bool($value)) {
                $clean[$key] = $value;
            }
        } elseif (str_ends_with($key, '_count')) {
            if (is_int($value) && $value >= 0 && $value <= 10000) {
                $clean[$key] = $value;
            }
        } elseif (in_array($key, ['action', 'scene_mode', 'bystander_mode', 'target', 'operation', 'status', 'request_type', 'phase', 'route', 'state_status', 'mode', 'decision'], true)) {
            if (is_string($value) && in_array($value, pcv_log_enum_values($key), true)) {
                $clean[$key] = $value;
            }
        }
    }

    foreach (['active_config_id', 'pending_config_id', 'exception_class', 'exception_code', 'source_file', 'source_line'] as $key) {
        if (!array_key_exists($key, $context)) {
            continue;
        }
        $value = $context[$key];
        if (in_array($key, ['active_config_id', 'pending_config_id'], true)
            && is_string($value) && pcv_log_valid_uuid($value)) {
            $clean[$key] = $value;
        } elseif ($key === 'exception_class' && is_string($value)
            && preg_match('~^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$~D', $value) === 1) {
            $clean[$key] = $value;
        } elseif ($key === 'exception_code' && is_int($value)) {
            $clean[$key] = $value;
        } elseif ($key === 'source_file' && is_string($value)
            && preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $value) === 1) {
            $clean[$key] = $value;
        } elseif ($key === 'source_line' && is_int($value) && $value > 0) {
            $clean[$key] = $value;
        }
    }
    return $clean;
}

function pcv_log_plugin_version(): string
{
    static $version = null;
    if (is_string($version)) {
        return $version;
    }
    $version = 'unknown';
    $manifest = __DIR__ . '/manifest.json';
    if (is_file($manifest) && !is_link($manifest)) {
        $contents = @file_get_contents($manifest);
        if (is_string($contents)) {
            $decoded = json_decode($contents, true);
            $candidate = is_array($decoded) ? ($decoded['version'] ?? null) : null;
            if (is_string($candidate) && preg_match('/^[0-9A-Za-z][0-9A-Za-z.+-]{0,31}$/D', $candidate) === 1) {
                $version = $candidate;
            }
        }
    }
    return $version;
}

function pcv_log_effective_uid(): ?int
{
    if (function_exists('posix_geteuid')) {
        return posix_geteuid();
    }
    return null;
}

function pcv_log_absolute_path(string $path): bool
{
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/D', $path) === 1
        || str_starts_with($path, '\\\\');
}

function pcv_log_path_is_within(string $path, string $parent): bool
{
    $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    $parent = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $parent), DIRECTORY_SEPARATOR);
    if (PHP_OS_FAMILY === 'Windows') {
        $path = strtolower($path);
        $parent = strtolower($parent);
    }
    return $path === $parent || str_starts_with($path, $parent . DIRECTORY_SEPARATOR);
}

function pcv_log_document_root()
{
    $root = $_SERVER['DOCUMENT_ROOT'] ?? getenv('DOCUMENT_ROOT');
    if (!is_string($root) || $root === '') {
        return PHP_SAPI === 'cli' ? null : false;
    }
    $canonical = realpath($root);
    return $canonical === false ? false : $canonical;
}

function pcv_log_known_webroot(): ?string
{
    $directory = realpath(__DIR__);
    if ($directory === false) {
        return null;
    }
    for ($depth = 0; $depth < 6; $depth++) {
        if (strtolower(basename($directory)) === 'ext') {
            $serverRoot = realpath(dirname($directory));
            if ($serverRoot === false) {
                return null;
            }
            // Without DOCUMENT_ROOT, conservatively treat the server root's parent as public.
            return realpath(dirname($serverRoot)) ?: $serverRoot;
        }
        $parent = dirname($directory);
        if ($parent === $directory) {
            break;
        }
        $directory = $parent;
    }
    return null;
}

function pcv_log_has_symlink_component(string $path): bool
{
    $path = str_replace('\\', '/', $path);
    if (preg_match('/^[A-Za-z]:\//', $path) === 1) {
        $current = substr($path, 0, 3);
        $parts = explode('/', substr($path, 3));
    } elseif (str_starts_with($path, '/')) {
        $current = '/';
        $parts = explode('/', substr($path, 1));
    } else {
        return true;
    }
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        $current = rtrim($current, '/') . '/' . $part;
        clearstatcache(true, $current);
        if (is_link($current)) {
            return true;
        }
    }
    return false;
}

function pcv_log_directory_is_safe(string $directory, bool $mustExist): bool
{
    if ($directory === '' || str_contains($directory, "\0") || !pcv_log_absolute_path($directory)
        || pcv_log_has_symlink_component($directory)) {
        return false;
    }
    clearstatcache(true, $directory);
    if (!file_exists($directory)) {
        return !$mustExist && pcv_log_outside_webroot($directory);
    }
    if (!is_dir($directory)) {
        return false;
    }
    $canonical = realpath($directory);
    $uid = pcv_log_effective_uid();
    $owner = @fileowner($directory);
    $permissions = @fileperms($directory);
    if ($canonical === false || $uid === null || $owner !== $uid || $permissions === false
        || (($permissions & 0077) !== 0) || (($permissions & 0700) !== 0700)) {
        return false;
    }
    return pcv_log_outside_webroot($canonical);
}

function pcv_log_outside_webroot(string $directory): bool
{
    $knownWebroot = pcv_log_known_webroot();
    if ($knownWebroot !== null && pcv_log_path_is_within($directory, $knownWebroot)) {
        return false;
    }
    $documentRoot = pcv_log_document_root();
    if ($documentRoot === false) {
        return false;
    }
    if ($documentRoot === null) {
        return PHP_SAPI === 'cli';
    }
    return !pcv_log_path_is_within($directory, $documentRoot);
}

function pcv_log_default_directory(bool $create): ?string
{
    $base = realpath(sys_get_temp_dir());
    $uid = pcv_log_effective_uid();
    $extensionPath = realpath(__DIR__);
    if ($base === false || $uid === null || $extensionPath === false || is_link(sys_get_temp_dir())) {
        return null;
    }
    $directory = $base . DIRECTORY_SEPARATOR . 'private-conversation-' . $uid . '-'
        . substr(hash('sha256', $extensionPath), 0, 16);
    clearstatcache(true, $directory);
    if (!file_exists($directory) && $create) {
        if (@mkdir($directory, 0700)) {
            @chmod($directory, 0700);
        } elseif (!is_dir($directory)) {
            return null;
        }
    }
    if (file_exists($directory) && !pcv_log_directory_is_safe($directory, true)) {
        return null;
    }
    return pcv_log_outside_webroot($directory) ? $directory : null;
}

function pcv_log_resolve_directory(bool $create): ?string
{
    $request =& pcv_log_request_context();
    if (is_string($request['test_directory'])) {
        return pcv_log_directory_is_safe($request['test_directory'], true) ? $request['test_directory'] : null;
    }

    $override = getenv('PCV_LOG_DIR');
    if (is_string($override) && $override !== '' && pcv_log_directory_is_safe($override, true)) {
        return realpath($override) ?: null;
    }
    return pcv_log_default_directory($create);
}

function pcv_log_path(): ?string
{
    if (PHP_SAPI !== 'cli') {
        return null;
    }
    try {
        $directory = pcv_log_resolve_directory(false);
        return $directory === null ? null : $directory . DIRECTORY_SEPARATOR . 'events.jsonl';
    } catch (Throwable) {
        return null;
    }
}

function pcv_log_set_test_directory(string $directory): bool
{
    if (PHP_SAPI !== 'cli' || !defined('PCV_LOG_TESTING') || PCV_LOG_TESTING !== true) {
        return false;
    }
    clearstatcache(true, $directory);
    $canonical = realpath($directory);
    $tempRoot = realpath(sys_get_temp_dir());
    if ($canonical === false || $tempRoot === false || is_link($directory)
        || !pcv_log_path_is_within($canonical, $tempRoot)
        || !pcv_log_directory_is_safe($canonical, true)) {
        return false;
    }
    $request =& pcv_log_request_context();
    $request['test_directory'] = $canonical;
    return true;
}

function pcv_log_fallback_once(string $code): void
{
    try {
        $request =& pcv_log_request_context();
        if ($request['fallback_reported']) {
            return;
        }
        $request['fallback_reported'] = true;
        if (!in_array($code, ['directory_unavailable', 'lock_unavailable', 'rotation_failed', 'append_failed', 'entry_too_large', 'invalid_event'], true)) {
            $code = 'logger_failed';
        }
        $configId = is_string($request['config_id']) && pcv_log_valid_uuid($request['config_id'])
            ? $request['config_id']
            : 'none';
        @error_log('Private Conversation logger: ' . $code
            . ' request_id=' . $request['request_id']
            . ' config_id=' . $configId);
    } catch (Throwable) {
        // Logging fallback must not escape through CHIM's process-wide error handler.
    }
}

function pcv_log_private_file(string $path): bool
{
    if (is_link($path) || !is_file($path)) {
        return false;
    }
    clearstatcache(true, $path);
    $uid = pcv_log_effective_uid();
    $owner = @fileowner($path);
    $permissions = @fileperms($path);
    return $uid !== null && $owner === $uid && $permissions !== false
        && (($permissions & 0077) === 0) && (($permissions & 0600) === 0600);
}

function pcv_log_rotate(string $directory): bool
{
    $active = $directory . DIRECTORY_SEPARATOR . 'events.jsonl';
    for ($index = PCV_LOG_MAX_FILES - 1; $index >= 1; $index--) {
        $source = $index === 1 ? $active : $directory . DIRECTORY_SEPARATOR . 'events.' . ($index - 1) . '.jsonl';
        $destination = $directory . DIRECTORY_SEPARATOR . 'events.' . $index . '.jsonl';
        clearstatcache(true, $source);
        clearstatcache(true, $destination);
        if (is_link($source) || is_link($destination)) {
            return false;
        }
        if (!file_exists($source)) {
            continue;
        }
        if (!pcv_log_private_file($source)) {
            return false;
        }
        if (file_exists($destination)) {
            if (!pcv_log_private_file($destination) || !@unlink($destination)) {
                return false;
            }
        }
        if (!@rename($source, $destination)) {
            return false;
        }
        @chmod($destination, 0600);
    }
    return true;
}

function pcv_log_write_line(string $line): bool
{
    $directory = pcv_log_resolve_directory(true);
    if ($directory === null) {
        pcv_log_fallback_once('directory_unavailable');
        return false;
    }
    $lockPath = $directory . DIRECTORY_SEPARATOR . 'events.lock';
    if (is_link($lockPath)) {
        pcv_log_fallback_once('lock_unavailable');
        return false;
    }
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) {
        pcv_log_fallback_once('lock_unavailable');
        return false;
    }
    @chmod($lockPath, 0600);
    if (!pcv_log_private_file($lockPath) || !@flock($lock, LOCK_EX | LOCK_NB)) {
        @fclose($lock);
        pcv_log_fallback_once('lock_unavailable');
        return false;
    }

    $ok = false;
    try {
        $active = $directory . DIRECTORY_SEPARATOR . 'events.jsonl';
        if (is_link($active)) {
            pcv_log_fallback_once('append_failed');
            return false;
        }
        if (file_exists($active)) {
            clearstatcache(true, $active);
            if (!pcv_log_private_file($active)) {
                pcv_log_fallback_once('append_failed');
                return false;
            }
            $size = @filesize($active);
            if ($size === false) {
                pcv_log_fallback_once('append_failed');
                return false;
            }
            if ($size + strlen($line) > PCV_LOG_MAX_FILE_BYTES) {
                if (!pcv_log_rotate($directory)) {
                    pcv_log_fallback_once('rotation_failed');
                    return false;
                }
            }
        }

        clearstatcache(true, $active);
        $originalSize = file_exists($active) ? @filesize($active) : 0;
        if (!is_int($originalSize) && $originalSize !== 0) {
            pcv_log_fallback_once('append_failed');
            return false;
        }
        $stream = @fopen($active, 'ab');
        if ($stream === false) {
            pcv_log_fallback_once('append_failed');
            return false;
        }
        @chmod($active, 0600);
        if (!pcv_log_private_file($active)) {
            @fclose($stream);
            pcv_log_fallback_once('append_failed');
            return false;
        }
        $written = 0;
        $length = strlen($line);
        while ($written < $length) {
            $count = @fwrite($stream, substr($line, $written));
            if (!is_int($count) || $count < 1) {
                @ftruncate($stream, $originalSize);
                @fflush($stream);
                @fclose($stream);
                pcv_log_fallback_once('append_failed');
                return false;
            }
            $written += $count;
        }
        $ok = @fflush($stream);
        if (!$ok) {
            @ftruncate($stream, $originalSize);
            @fflush($stream);
        }
        @fclose($stream);
    } catch (Throwable) {
        pcv_log_fallback_once('append_failed');
        $ok = false;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }

    if (!$ok) {
        pcv_log_fallback_once('append_failed');
    }
    return $ok;
}

function pcv_log_event(string $event, string $severity, string $outcome, ?string $reason = null, array $context = []): void
{
    try {
        $rules = pcv_log_event_rules();
        if (!isset($rules[$event]) || $rules[$event]['severity'] !== $severity
            || $rules[$event]['outcome'] !== $outcome || !pcv_log_reason_allowed($event, $reason)) {
            pcv_log_fallback_once('invalid_event');
            return;
        }

        $request =& pcv_log_request_context();
        if ($severity === 'debug' && !$request['debug_enabled']) {
            return;
        }
        $started = $request['started_ns'];
        $elapsed = is_int($started) && function_exists('hrtime')
            ? max(0, (int)((hrtime(true) - $started) / 1000000))
            : null;
        $now = microtime(true);
        $timestamp = gmdate('Y-m-d\TH:i:s', (int)$now)
            . sprintf('.%03dZ', (int)(($now - floor($now)) * 1000));
        $entry = [
            'schema_version' => PCV_LOG_SCHEMA_VERSION,
            'plugin_version' => pcv_log_plugin_version(),
            'timestamp' => $timestamp,
            'event' => $event,
            'severity' => $severity,
            'outcome' => $outcome,
            'reason' => $reason,
            'request_id' => $request['request_id'],
            'config_id' => $request['config_id'],
            'playthrough_ref' => $request['playthrough_ref'] ?? null,
            'elapsed_ms' => $elapsed,
            'context' => pcv_log_clean_context($event, $context),
        ];
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;
        $line = json_encode($entry, $flags) . "\n";
        if (strlen($line) > PCV_LOG_MAX_ENTRY_BYTES) {
            $entry['context'] = [];
            $line = json_encode($entry, $flags) . "\n";
        }
        if (strlen($line) > PCV_LOG_MAX_ENTRY_BYTES) {
            pcv_log_fallback_once('entry_too_large');
            return;
        }
        pcv_log_write_line($line);
    } catch (Throwable) {
        pcv_log_fallback_once('append_failed');
    }
}

function pcv_log_exception(string $event, string $severity, string $outcome, string $reason, Throwable $error, array $context = []): void
{
    try {
        $context['exception_class'] = get_class($error);
        $context['exception_code'] = (int)$error->getCode();
        $context['source_file'] = basename($error->getFile());
        $context['source_line'] = $error->getLine();
        pcv_log_event($event, $severity, $outcome, $reason, $context);
    } catch (Throwable) {
        pcv_log_fallback_once('append_failed');
    }
}
