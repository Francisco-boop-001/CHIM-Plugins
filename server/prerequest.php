<?php
declare(strict_types=1);

namespace ChimMindPoisoning;

use JsonException;
use Throwable;

$chimMindPoisoningIsSpeech = isset($gameRequest) && is_array($gameRequest) && ($gameRequest[0] ?? null) === '_speech';
$chimMindPoisoningRequestLog = null;
try {
    @require_once __DIR__ . '/logging.php';
    if (!class_exists(RequestLog::class, false)) {
        throw new \RuntimeException('Request logger is unavailable.');
    }
    if ($chimMindPoisoningIsSpeech) {
        $chimMindPoisoningRequestLog = new RequestLog();
        $chimMindPoisoningBootstrapPayload = $gameRequest[3] ?? null;
        $chimMindPoisoningBootstrapContext = [
            'payload_bytes' => is_string($chimMindPoisoningBootstrapPayload) ? strlen($chimMindPoisoningBootstrapPayload) : 0,
        ];
        if (is_string($chimMindPoisoningBootstrapPayload) && strlen($chimMindPoisoningBootstrapPayload) <= 16384) {
            try {
                $chimMindPoisoningBootstrapDecoded = json_decode($chimMindPoisoningBootstrapPayload, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $chimMindPoisoningBootstrapDecoded = null;
            }
            $chimMindPoisoningBootstrapUtteranceId = is_array($chimMindPoisoningBootstrapDecoded)
                ? ($chimMindPoisoningBootstrapDecoded['utterance_id'] ?? null)
                : null;
            if (is_string($chimMindPoisoningBootstrapUtteranceId)) {
                $chimMindPoisoningBootstrapUtteranceId = trim($chimMindPoisoningBootstrapUtteranceId);
            }
            if (
                is_string($chimMindPoisoningBootstrapUtteranceId)
                && preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/D', $chimMindPoisoningBootstrapUtteranceId) === 1
            ) {
                $chimMindPoisoningBootstrapContext['utterance_id'] = $chimMindPoisoningBootstrapUtteranceId;
            }
        }
        $chimMindPoisoningRequestLog->context($chimMindPoisoningBootstrapContext);
    }
} catch (Throwable) {
    if ($chimMindPoisoningIsSpeech) {
        @error_log('Mind Poisoning ACK failed: logging_bootstrap_failed');
        return;
    }
    $chimMindPoisoningRequestLog = null;
}

try {
    @require_once __DIR__ . '/influence.php';
    @require_once __DIR__ . '/model.php';
    @require_once __DIR__ . '/store.php';
    @require_once __DIR__ . '/controls.php';
} catch (Throwable) {
    if ($chimMindPoisoningRequestLog instanceof RequestLog) {
        $chimMindPoisoningRequestLog->finish('failed', 'dependency_bootstrap_failed', ['stage' => 'bootstrap']);
        return;
    }
    throw new \RuntimeException('Mind Poisoning dependency bootstrap failed.');
}

function uniqueNpcNamed(array $identities, string $name): ?array
{
    $matches = array_values(array_filter(
        $identities,
        static fn(array $row): bool => sameActorName($row['npc_name'] ?? null, $name)
    ));
    return count($matches) === 1 ? $matches[0] : null;
}

function promptNpcCopy(array $npc): array
{
    $copy = $npc;
    $extendedData = $npc['extended_data'] ?? new \stdClass();
    if (!$extendedData instanceof \stdClass) {
        throw new \RuntimeException('NPC relationship context is unavailable.');
    }
    $copy['extended_data'] = json_decode(
        json_encode($extendedData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    return $copy;
}

function isPlayerName(string $name, string $playerName): bool
{
    foreach (['Player', 'the Player', 'Player Character', 'the Player Character', 'Dragonborn', 'the Dragonborn', $playerName] as $alias) {
        if ($alias !== '' && sameActorName($name, $alias)) {
            return true;
        }
    }
    return false;
}

function validatedPlayerInputInsert(array $gameRequest, array $insert): ?array
{
    $type = $insert['type'] ?? null;
    $types = playerInputTypes();
    $ts = $insert['ts'] ?? null;
    $gamets = $insert['gamets'] ?? null;
    $data = $insert['data'] ?? null;
    if (
        !is_string($type) || !in_array($type, $types, true) || ($gameRequest[0] ?? null) !== $type
        || (!is_string($ts) && !is_int($ts)) || !is_numeric($ts) || (string)$ts !== (string)($gameRequest[1] ?? '')
        || (!is_string($gamets) && !is_int($gamets)) || !is_numeric($gamets) || (string)$gamets !== (string)($gameRequest[2] ?? '')
        || !is_string($data) || $data !== ($gameRequest[3] ?? null)
        || !is_int($insert['localts'] ?? null) || $insert['localts'] < 1
        || ($insert['sess'] ?? null) !== 'web'
        || !is_string($insert['people'] ?? null) || trim($insert['people']) === ''
    ) {
        return null;
    }

    return [
        'type' => $type,
        'ts' => $ts,
        'gamets' => $gamets,
        'data' => $data,
        'localts' => $insert['localts'],
        'sess' => 'web',
    ];
}

/**
 * Evaluate one exact _speech acknowledgement. The optional callable is a test seam;
 * production uses the configured requestJudgments adapter.
 */
function handleSpeechAck(
    array $gameRequest,
    StoreDb $store,
    ?callable $requestModel = null,
    ?RequestLog $requestLog = null
): string
{
    if (($gameRequest[0] ?? null) !== '_speech') {
        return 'ignored';
    }

    $logFields = ['stage' => 'preflight', 'model_outcome' => 'not_called'];
    $requestLog?->context(['payload_bytes' => is_string($gameRequest[3] ?? null) ? strlen($gameRequest[3]) : 0]);
    $requestLog?->event('ack_started', 'debug', ['stage' => 'preflight']);

    $status = evaluateInfluenceRequest($gameRequest, $store, $requestModel, $requestLog, $logFields);
    $finishedByBatch = ($logFields['_finished_by_batch'] ?? false) === true;
    unset($logFields['_finished_by_batch']);
    if ($finishedByBatch) {
        return $status;
    }
    return finishInfluenceRequest($status, $logFields, $requestLog);
}

/** @param array<string, mixed> $logFields */
function finishInfluenceRequest(string $status, array $logFields, ?RequestLog $requestLog): string
{
    $outcome = 'skipped';
    $reason = $logFields['reason'] ?? $status;
    if ($status === 'committed') {
        $outcome = 'committed';
        $reason = 'committed';
    } elseif ($status === 'model-invalid' || ($status === 'failed' && ($logFields['model_outcome'] ?? null) === 'invalid')) {
        $outcome = 'rejected';
    } elseif ($status === 'listener-invalid' && in_array($reason, [
        'restore-policy-ledger-invalid',
        'restore-policy-owner-invalid',
        'restore-policy-owner-state-invalid',
        'restore-policy-timeline-invalid',
    ], true)) {
        $outcome = 'rejected';
    } elseif ($status === 'failed') {
        $outcome = 'failed';
        $reason = $logFields['reason'] ?? 'evaluation_failed';
    }
    $requestLog?->finish($outcome, $reason, $logFields);
    return $status;
}

function overhearingSettingEnabled(): bool
{
    if (!function_exists('chimGetGeneralSettingBool')) {
        return false;
    }
    try {
        return \chimGetGeneralSettingBool('mind_poisoning_overhearing_enabled', false) === true;
    } catch (Throwable) {
        return false;
    }
}

/** Parse only a small, literal eventlog.people list; malformed snapshots only suppress extras. */
function overhearingPeopleNames(mixed $raw, ?string &$reason = null): ?array
{
    $reason = 'audience_people_invalid';
    if (is_string($raw) && strlen($raw) > 2048) {
        $reason = 'audience_people_oversized';
        return null;
    }
    if (!is_string($raw) || $raw === '' || preg_match('//u', $raw) !== 1 || !function_exists('parsePeoplePipeList')) {
        return null;
    }
    $parts = explode('|', $raw);
    if (count($parts) > 64) {
        $reason = 'audience_people_oversized';
        return null;
    }
    if (($parts[0] ?? null) !== null && trim($parts[0]) === '') {
        array_shift($parts);
    }
    if ($parts !== [] && trim($parts[array_key_last($parts)]) === '') {
        array_pop($parts);
    }
    if ($parts === []) {
        return null;
    }
    $expected = [];
    foreach ($parts as $name) {
        $name = trim($name);
        if (
            $name === '' || strlen($name) > 256 || preg_match('//u', $name) !== 1
            || preg_match('/[\x00-\x1F\x7F]/', $name) === 1
        ) {
            return null;
        }
        if (!in_array($name, $expected, true)) {
            $expected[] = $name;
        }
    }
    try {
        $parsed = \parsePeoplePipeList($raw);
    } catch (Throwable) {
        return null;
    }
    if (!is_array($parsed) || !array_is_list($parsed) || $parsed !== $expected) {
        return null;
    }
    $reason = null;
    return $parsed;
}

/** Return the existing addressed-path preflight without letting it veto other listeners. */
function inspectAckRecipient(
    array $event,
    array $listener,
    StoreDb $store,
    array $identities,
    string $playerName
): array {
    $extended = $listener['extended_data'] ?? null;
    if (!$extended instanceof \stdClass) {
        return ['status' => 'listener-invalid', 'reason' => 'listener_extended_data_invalid'];
    }
    if (!empty($extended->relationships_locked) || (int)($listener['lock_profile'] ?? 0) !== 0) {
        return ['status' => 'locked', 'reason' => 'relationship_locked'];
    }
    $relationshipValue = property_exists($extended, 'relationships') ? $extended->relationships : new \stdClass();
    $relationships = storedRelationshipMap($relationshipValue);
    if (!is_array($relationships)) {
        return ['status' => 'listener-invalid', 'reason' => 'listener_relationships_invalid'];
    }
    $dedupeReason = null;
    if (eventAlreadyProcessed($listener, $event['playthrough_id'], $event['event_id'], $event['utterance_id'], $dedupeReason)) {
        return ['status' => 'duplicate', 'reason' => $dedupeReason ?? 'duplicate'];
    }
    $subjects = findSubjects($event, $identities, $playerName);
    if (count($subjects) > 8) {
        return ['status' => 'too-many-subjects', 'reason' => 'too_many_subjects'];
    }
    if ($subjects === []) {
        return ['status' => 'no-subjects', 'reason' => 'no_subjects'];
    }
    if (array_key_exists('player', $subjects)) {
        $canonicalRelationships = canonicalStoredRelationshipMap($relationships, $playerName);
        if (!is_array($canonicalRelationships)) {
            return ['status' => 'listener-invalid', 'reason' => 'listener_relationships_invalid'];
        }
    }
    foreach ($subjects as $subject) {
        if ($subject['id'] === null) {
            continue;
        }
        $row = $store->npcById($subject['id']);
        if (!is_array($row) || !sameActorName($row['npc_name'] ?? null, $subject['name'])) {
            return ['status' => 'subject-stale', 'reason' => 'subject_stale'];
        }
    }
    return ['status' => 'eligible', 'subjects' => $subjects];
}

/** Return null to continue through the single-listener ACK path without a completed baseline. */
function evaluateOverhearingAck(
    array $event,
    array $source,
    array $speaker,
    array $listener,
    array $identities,
    string $playerName,
    int $connectorId,
    StoreDb $store,
    ?callable $requestModel,
    ?RequestLog $requestLog,
    array &$logFields
): ?string {
    if (!overhearingSettingEnabled()) {
        return null;
    }
    $restorePolicy = mindPoisoningRestorePolicyEnabled();
    if (!$store instanceof WitnessSnapshotStoreDb) {
        if ($restorePolicy) {
            return recordRestorePolicyPreflight($requestLog, $logFields, 'restore-policy-audience-unavailable', 'preflight', null, $event);
        }
        $requestLog?->event('overhearing_audience_skipped', 'warning', [
            'stage' => 'preflight', 'reason' => 'audience_snapshot_unavailable',
        ]);
        return null;
    }
    $peopleReason = null;
    if (!empty($source['source_people_oversized'])) {
        $peopleReason = 'audience_people_oversized';
        $people = null;
    } else {
        $people = overhearingPeopleNames($source['source_people'] ?? null, $peopleReason);
    }
    if (!is_array($people)) {
        if ($restorePolicy) {
            return recordRestorePolicyPreflight($requestLog, $logFields, 'restore-policy-audience-invalid', 'preflight', null, $event);
        }
        $requestLog?->event('overhearing_audience_skipped', 'warning', [
            'stage' => 'preflight', 'reason' => $peopleReason ?? 'audience_people_invalid',
        ]);
        return null;
    }
    $speakerMembers = array_values(array_filter($people, static fn(string $name): bool => sameActorName($name, $event['speaker_name'])));
    if (count($speakerMembers) !== 1) {
        if ($restorePolicy) {
            return recordRestorePolicyPreflight($requestLog, $logFields, 'restore-policy-audience-invalid', 'preflight', null, $event);
        }
        $requestLog?->event('overhearing_audience_skipped', 'warning', [
            'stage' => 'preflight', 'reason' => 'audience_speaker_invalid',
        ]);
        return null;
    }

    $addressedId = $event['listener_id'];
    $addressedName = $event['listener_name'];
    $seenIds = [$event['speaker_id'] => true, $addressedId => true];
    $eligibleExtras = [];
    $filteredAny = false;
    foreach ($people as $name) {
        if (isPlayerName($name, $playerName) || sameActorName($name, 'explicit_disable_rechat')) {
            $filteredAny = true;
            continue;
        }
        $matches = array_values(array_filter(
            $identities,
            static fn(array $row): bool => sameActorName($row['npc_name'] ?? null, $name)
        ));
        if (count($matches) !== 1 || !is_int($matches[0]['id'] ?? null)) {
            $filteredAny = true;
            continue;
        }
        $identity = $matches[0];
        $npcId = $identity['id'];
        if (isset($seenIds[$npcId])) {
            $filteredAny = true;
            continue;
        }
        $seenIds[$npcId] = true;
        try {
            $row = $store->npcById($npcId);
        } catch (Throwable) {
            $requestLog?->event('overhearing_audience_skipped', 'error', [
                'stage' => 'preflight', 'reason' => 'audience_lookup_failed',
            ]);
            continue;
        }
        if (!is_array($row) || !sameActorName($row['npc_name'] ?? null, $name)) {
            $filteredAny = true;
            continue;
        }
        $recipientEvent = $event;
        $recipientEvent['listener_id'] = $npcId;
        $recipientEvent['listener_name'] = $row['npc_name'];
        $recipientEvent['listener_role'] = 'overheard';
        $recipientEvent['addressed_listener_id'] = $addressedId;
        $recipientEvent['addressed_listener_name'] = $addressedName;
        $recipientEvent['source_people'] = $source['source_people'];
        $recipientEvent['source_delivery_state'] = $source['delivery_state'] ?? null;
        if ($restorePolicy) {
            $restorePolicyFields = ['stage' => 'preflight', 'model_outcome' => 'not_called'];
            $recipientLog = $requestLog?->fork([
                'event_id' => $event['event_id'],
                'utterance_id' => $event['utterance_id'],
                'playthrough_id' => $event['playthrough_id'],
                'speaker_id' => $event['speaker_id'],
                'speaker_kind' => 'npc',
                'listener_id' => $npcId,
                'addressed_listener_id' => $addressedId,
                'listener_role' => 'overheard',
            ]);
            $restorePolicyStatus = prepareRestorePolicyRecipient(
                $recipientEvent,
                $row,
                $store,
                $recipientLog,
                $restorePolicyFields
            );
            if ($restorePolicyStatus !== null) {
                finishInfluenceRequest($restorePolicyStatus, $restorePolicyFields, $recipientLog);
                $filteredAny = true;
                continue;
            }
        }
        try {
            $preflight = inspectAckRecipient($recipientEvent, $row, $store, $identities, $playerName);
        } catch (Throwable) {
            $requestLog?->event('overhearing_audience_skipped', 'error', [
                'stage' => 'preflight', 'reason' => 'audience_preflight_failed',
            ]);
            continue;
        }
        if (($preflight['status'] ?? null) !== 'eligible') {
            $filteredAny = true;
            continue;
        }
        $eligibleExtras[] = [
            'event' => $recipientEvent,
            'listener' => $row,
            'subjects' => $preflight['subjects'],
        ];
        if (count($eligibleExtras) > 4) {
            if ($filteredAny) {
                $requestLog?->event('overhearing_audience_filtered', 'debug', [
                    'stage' => 'preflight', 'reason' => 'audience_members_filtered',
                ]);
            }
            $requestLog?->event('overhearing_audience_skipped', 'warning', [
                'stage' => 'preflight', 'reason' => 'audience_cap_reached',
            ]);
            if ($restorePolicy) {
                return recordRestorePolicyPreflight($requestLog, $logFields, 'restore-policy-audience-cap', 'preflight', null, $event);
            }
            return null;
        }
    }
    if ($filteredAny) {
        $requestLog?->event('overhearing_audience_filtered', 'debug', [
            'stage' => 'preflight', 'reason' => 'audience_members_filtered',
        ]);
    }
    if ($eligibleExtras === []) {
        if ($restorePolicy) {
            $addressedEvent = $event + [
                'listener_role' => 'addressed',
                'addressed_listener_id' => $addressedId,
                'addressed_listener_name' => $addressedName,
            ];
            $addressedFields = ['stage' => 'preflight', 'model_outcome' => 'not_called'];
            $addressedLog = $requestLog?->fork([
                'event_id' => $event['event_id'],
                'utterance_id' => $event['utterance_id'],
                'playthrough_id' => $event['playthrough_id'],
                'speaker_id' => $event['speaker_id'],
                'speaker_kind' => 'npc',
                'listener_id' => $addressedId,
                'addressed_listener_id' => $addressedId,
                'listener_role' => 'addressed',
            ]);
            $addressedStatus = prepareRestorePolicyRecipient(
                $addressedEvent,
                $listener,
                $store,
                $addressedLog,
                $addressedFields
            );
            if ($addressedStatus !== null) {
                $logFields = array_merge($logFields, $addressedFields);
                finishInfluenceRequest($addressedStatus, $addressedFields, $addressedLog);
                return $addressedStatus;
            }
        }
        return null;
    }
    $addressedEvent = $event + [
        'listener_role' => 'addressed',
        'addressed_listener_id' => $addressedId,
        'addressed_listener_name' => $addressedName,
    ];
    $addressedPolicyFields = ['stage' => 'preflight', 'model_outcome' => 'not_called'];
    $addressedPolicyLog = $restorePolicy ? $requestLog?->fork([
        'event_id' => $event['event_id'],
        'utterance_id' => $event['utterance_id'],
        'playthrough_id' => $event['playthrough_id'],
        'speaker_id' => $event['speaker_id'],
        'speaker_kind' => 'npc',
        'listener_id' => $addressedId,
        'addressed_listener_id' => $addressedId,
        'listener_role' => 'addressed',
    ]) : null;
    if ($restorePolicy) {
        $restorePolicyStatus = prepareRestorePolicyRecipient(
            $addressedEvent,
            $listener,
            $store,
            $addressedPolicyLog,
            $addressedPolicyFields
        );
        if ($restorePolicyStatus !== null) {
            $addressedPreflight = [
                'status' => $restorePolicyStatus,
                'reason' => $addressedPolicyFields['reason'] ?? $restorePolicyStatus,
                'commit_state' => $addressedPolicyFields['commit_state'] ?? null,
                'committed' => $addressedPolicyFields['committed'] ?? null,
            ];
            $logFields = array_merge($logFields, $addressedPolicyFields);
        } else {
            try {
                $addressedPreflight = inspectAckRecipient($addressedEvent, $listener, $store, $identities, $playerName);
            } catch (Throwable) {
                $requestLog?->event('overhearing_audience_skipped', 'error', [
                    'stage' => 'preflight', 'reason' => 'addressed_preflight_failed',
                ]);
                return null;
            }
        }
    } else {
        try {
            $addressedPreflight = inspectAckRecipient($event, $listener, $store, $identities, $playerName);
        } catch (Throwable) {
            $requestLog?->event('overhearing_audience_skipped', 'error', [
                'stage' => 'preflight', 'reason' => 'addressed_preflight_failed',
            ]);
            return null;
        }
    }
    $selected = [];
    if (($addressedPreflight['status'] ?? null) === 'eligible') {
        $selected[] = [
            'event' => $addressedEvent,
            'listener' => $listener,
            'subjects' => $addressedPreflight['subjects'],
        ];
    }
    $selected = array_merge($selected, $eligibleExtras);
    foreach ($selected as &$recipient) {
        $recipient['current_types'] = relationshipTypeSnapshot(
            promptNpcCopy($recipient['listener']),
            $recipient['subjects'],
            $playerName
        );
    }
    unset($recipient);

    $speakerPrompt = promptNpcCopy($speaker);
    try {
        $promptRecipients = [];
        foreach ($selected as $recipient) {
            $recipient['listener'] = promptNpcCopy($recipient['listener']);
            $promptRecipients[] = $recipient;
        }
        $messages = buildOverhearingMessages($event, $speakerPrompt, $promptRecipients);
    } catch (Throwable) {
        $requestLog?->event('overhearing_prompt_skipped', 'error', [
            'stage' => 'preflight', 'reason' => 'overhearing_prompt_build_failed',
        ]);
        return null;
    }
    if (!overhearingSettingEnabled()) {
        return null;
    }

    $primary = $selected[0];
    $primaryEvent = $primary['event'];
    $primaryId = $primaryEvent['listener_id'];
    $addressedSelected = ($addressedPreflight['status'] ?? null) === 'eligible';
    $addressedStatus = $addressedPreflight['status'] ?? 'listener-invalid';
    $addressedReason = $addressedPreflight['reason'] ?? $addressedStatus;
    $primaryContext = [
        'event_id' => $event['event_id'],
        'utterance_id' => $event['utterance_id'],
        'playthrough_id' => $event['playthrough_id'],
        'speaker_id' => $event['speaker_id'],
        'speaker_kind' => 'npc',
        'listener_id' => $primaryId,
        'addressed_listener_id' => $addressedId,
        'listener_role' => $primaryEvent['listener_role'],
        'connector_id' => $connectorId,
        'subject_count' => count($primary['subjects']),
    ];
    $requestLog?->markBatch();
    $requestLog?->context($primaryContext);
    $recipientLogs = [$primaryId => $requestLog];
    foreach (array_slice($selected, 1) as $recipient) {
        $recipientEvent = $recipient['event'];
        $recipientId = $recipientEvent['listener_id'];
        $recipientLogs[$recipientId] = $requestLog?->fork([
            'event_id' => $event['event_id'],
            'utterance_id' => $event['utterance_id'],
            'playthrough_id' => $event['playthrough_id'],
            'speaker_id' => $event['speaker_id'],
            'speaker_kind' => 'npc',
            'listener_id' => $recipientId,
            'addressed_listener_id' => $addressedId,
            'listener_role' => $recipientEvent['listener_role'],
            'connector_id' => $connectorId,
            'subject_count' => count($recipient['subjects']),
        ]);
    }
    if (!$addressedSelected) {
        $addressedLog = $addressedPolicyLog ?? $requestLog?->fork([
            'event_id' => $event['event_id'],
            'utterance_id' => $event['utterance_id'],
            'playthrough_id' => $event['playthrough_id'],
            'speaker_id' => $event['speaker_id'],
            'speaker_kind' => 'npc',
            'listener_id' => $addressedId,
            'addressed_listener_id' => $addressedId,
            'listener_role' => 'addressed',
            'connector_id' => $connectorId,
        ]);
        $addressedFinishFields = [
            'stage' => 'preflight', 'model_outcome' => 'not_called',
            'reason' => $addressedReason,
        ];
        if (is_string($addressedPreflight['commit_state'] ?? null)) {
            $addressedFinishFields['commit_state'] = $addressedPreflight['commit_state'];
        }
        if (($addressedPreflight['committed'] ?? null) === true) {
            $addressedFinishFields['committed'] = true;
        }
        finishInfluenceRequest($addressedStatus, $addressedFinishFields, $addressedLog);
    }
    $finishBatch = static function (string $primaryStatus, array $primaryFields) use (
        &$logFields,
        $requestLog,
        $addressedSelected,
        $addressedStatus
    ): string {
        $logFields = array_merge($logFields, $primaryFields);
        $logFields['_finished_by_batch'] = true;
        finishInfluenceRequest($primaryStatus, $primaryFields, $requestLog);
        return $addressedSelected ? $primaryStatus : $addressedStatus;
    };

    $logFields = array_merge($logFields, [
        'stage' => 'model',
        'model_outcome' => 'not_called',
        'connector_id' => $connectorId,
        'subject_count' => count($primary['subjects']),
    ]);
    $requestLog?->event('overhearing_batch_started', 'info', [
        'stage' => 'model', 'connector_id' => $connectorId,
        'subject_count' => count($primary['subjects']),
    ]);
    $requestLog?->event('model_started', 'debug', [
        'stage' => 'model', 'connector_id' => $connectorId,
        'subject_count' => count($primary['subjects']),
    ]);
    $requestModel ??= static fn(array $batchMessages): string => requestJudgments($batchMessages, 4096);
    $modelStarted = hrtime(true);
    $response = null;
    $modelFailure = null;
    try {
        $response = $requestModel($messages);
    } catch (Throwable $error) {
        $modelFailure = $error instanceof ModelRequestFailure ? $error->reasonCode : 'model_request_failed';
    }
    $modelMs = RequestLog::elapsedMs($modelStarted);
    $logFields['model_ms'] = $modelMs;
    $subjectsByListener = [];
    $modelStatus = 'committed';
    $modelReason = null;
    $modelOutcome = 'validated';
    if ($modelFailure !== null) {
        $modelStatus = 'failed';
        $modelReason = $modelFailure;
        $modelOutcome = 'failed';
    } elseif (!is_string($response)) {
        $modelStatus = 'failed';
        $modelReason = 'model_response_invalid';
        $modelOutcome = 'invalid';
    } else {
        try {
            $subjectsByListener = parseOverhearingJudgments($response, $selected, $event['text']);
        } catch (JudgmentValidationFailure $error) {
            $modelStatus = 'model-invalid';
            $modelReason = $error->reasonCode;
            $modelOutcome = 'invalid';
        } catch (Throwable) {
            $modelStatus = 'failed';
            $modelReason = 'judgment_parser_internal_failed';
            $modelOutcome = 'failed';
        }
    }
    $logFields['model_outcome'] = $modelOutcome;
    if ($modelReason !== null) {
        $logFields['reason'] = $modelReason;
    }
    $modelLevel = $modelOutcome === 'failed' ? 'error' : ($modelOutcome === 'invalid' ? 'warning' : 'info');
    $requestLog?->event('model_finished', $modelLevel, [
        'stage' => 'model_validation',
        'model_outcome' => $modelOutcome,
        'reason' => $modelReason,
        'model_ms' => $modelMs,
    ]);

    $finishNonPrimary = static function (
        string $status,
        array $fields,
        ?RequestLog $log
    ): void {
        if ($log !== null) {
            finishInfluenceRequest($status, $fields, $log);
        }
    };
    if ($modelStatus !== 'committed') {
        foreach (array_slice($selected, 1) as $recipient) {
            $id = $recipient['event']['listener_id'];
            $log = $recipientLogs[$id] ?? null;
            $log?->event('batch_model_result', $modelOutcome === 'failed' ? 'error' : 'warning', [
                'stage' => 'model_validation', 'model_outcome' => $modelOutcome,
                'reason' => $modelReason, 'model_ms' => $modelMs,
            ]);
            $finishNonPrimary($modelStatus, [
                'stage' => 'model_validation', 'model_outcome' => $modelOutcome,
                'reason' => $modelReason ?? $modelStatus, 'model_ms' => $modelMs,
            ], $log);
        }
        $logFields['reason'] = $modelReason ?? $modelStatus;
        return $finishBatch($modelStatus, [
            'stage' => 'model_validation', 'model_outcome' => $modelOutcome,
            'reason' => $modelReason ?? $modelStatus, 'model_ms' => $modelMs,
        ]);
    }
    $logFields['model_outcome'] = 'validated';
    $requestLog?->event('model_batch_validated', 'info', [
        'stage' => 'model_validation', 'model_outcome' => 'validated', 'model_ms' => $modelMs,
    ]);

    $interactionReason = null;
    $interactionStatus = speechAckInteractionStatus($interactionReason);
    $pauseReason = null;
    $pauseStatus = mindPoisoningPauseStatus($pauseReason);
    if ($interactionStatus !== 'ok' || $pauseStatus !== 'enabled') {
        $gateStatus = $interactionStatus !== 'ok' ? $interactionStatus : $pauseStatus;
        $gateReason = $interactionStatus !== 'ok' ? $interactionReason : $pauseReason;
        foreach (array_slice($selected, 1) as $recipient) {
            $id = $recipient['event']['listener_id'];
            $finishNonPrimary($gateStatus, [
                'stage' => 'post_model_gate', 'model_outcome' => 'validated',
                'reason' => $gateReason ?? $gateStatus, 'model_ms' => $modelMs,
            ], $recipientLogs[$id] ?? null);
        }
        $logFields['stage'] = 'post_model_gate';
        $logFields['reason'] = $gateReason ?? $gateStatus;
        return $finishBatch($gateStatus, [
            'stage' => 'post_model_gate', 'model_outcome' => 'validated',
            'reason' => $gateReason ?? $gateStatus, 'model_ms' => $modelMs,
        ]);
    }

    $settingStillEnabled = overhearingSettingEnabled();
    $writeCandidates = array_values(array_filter($selected, static fn(array $recipient): bool =>
        ($recipient['event']['listener_role'] ?? null) === 'addressed'
        || $settingStillEnabled
    ));
    $writeIds = array_fill_keys(array_map(static fn(array $recipient): int => $recipient['event']['listener_id'], $writeCandidates), true);
    foreach (array_slice($selected, 1) as $recipient) {
        $id = $recipient['event']['listener_id'];
        $log = $recipientLogs[$id] ?? null;
        if (!isset($writeIds[$id])) {
            $finishNonPrimary('disabled', [
                'stage' => 'post_model_gate', 'model_outcome' => 'validated',
                'reason' => 'overhearing_disabled', 'model_ms' => $modelMs,
            ], $log);
            continue;
        }
        $log?->event('batch_model_result', 'info', [
            'stage' => 'model_validation', 'model_outcome' => 'validated', 'model_ms' => $modelMs,
        ]);
    }
    if ($writeCandidates === []) {
        $logFields['stage'] = 'post_model_gate';
        $logFields['reason'] = 'overhearing_disabled';
        return $finishBatch('disabled', [
            'stage' => 'post_model_gate', 'model_outcome' => 'validated',
            'reason' => 'overhearing_disabled', 'model_ms' => $modelMs,
        ]);
    }

    $primaryResult = 'failed';
    $primaryFields = [];
    foreach ($writeCandidates as $index => $recipient) {
        $recipientEvent = $recipient['event'];
        $id = $recipientEvent['listener_id'];
        $recipientLog = $recipientLogs[$id] ?? null;
        $recipientFields = [
            'stage' => 'persistence',
            'model_outcome' => 'validated',
            'model_ms' => $modelMs,
            'subject_count' => count($recipient['subjects']),
        ];
        if (($recipientEvent['listener_role'] ?? null) === 'overheard' && !overhearingSettingEnabled()) {
            $recipientStatus = 'disabled';
            $recipientFields['reason'] = 'overhearing_disabled';
        } else {
            $scopeCheck = null;
            if (($recipientEvent['listener_role'] ?? null) === 'overheard') {
                $scopeCheck = static function (
                    array $expected,
                    array $current,
                    array $active
                ): bool {
                    if (
                        !overhearingSettingEnabled()
                        || ($active['id'] ?? null) !== ($expected['playthrough_id'] ?? null)
                        || ($current['source_people'] ?? null) !== ($expected['source_people'] ?? null)
                        || !deliveryStateNotRegressed($expected['source_delivery_state'] ?? null, $current['delivery_state'] ?? null)
                    ) {
                        return false;
                    }
                    $reason = null;
                    $members = overhearingPeopleNames($current['source_people'] ?? null, $reason);
                    if (!is_array($members)) {
                        return false;
                    }
                    $listenerMembers = array_values(array_filter($members, static fn(string $name): bool =>
                        sameActorName($name, $expected['listener_name'] ?? null)
                    ));
                    if (count($listenerMembers) !== 1 || !function_exists('extractTalkTargetMetadata') || !function_exists('talkTargetsIncludeName')) {
                        return false;
                    }
                    try {
                        $sourceSpeaker = \extractSpeakerNameFromChatEvent($current['source_data'] ?? '');
                        $target = \extractTalkTargetMetadata($current['source_data'] ?? '');
                    } catch (Throwable) {
                        return false;
                    }
                    if (
                        !is_string($sourceSpeaker) || !sameActorName($sourceSpeaker, $expected['speaker_name'] ?? null)
                        || !is_array($target) || empty($target['hasExplicitTarget']) || !empty($target['isBroadcast'])
                        || !\talkTargetsIncludeName($target['targets'] ?? [], $expected['addressed_listener_name'] ?? null)
                    ) {
                        return false;
                    }
                    return true;
                };
            }
            $persistenceStarted = hrtime(true);
            $previousStoreLog = $store instanceof PostgresStoreDb
                ? $store->setRequestLog($recipientLog)
                : null;
            try {
                $recipientStatus = persistJudgments(
                    $recipientEvent,
                    $recipient['subjects'],
                    $subjectsByListener[$id],
                    $store,
                    $recipientLog,
                    null,
                    null,
                    $scopeCheck
                );
            } catch (Throwable) {
                $recipientStatus = 'failed';
                $recipientFields['reason'] = 'persistence_failed';
                $recipientFields['persistence_outcome'] = 'failed';
            } finally {
                if ($store instanceof PostgresStoreDb) {
                    $store->setRequestLog($previousStoreLog);
                }
            }
            $recipientFields['persistence_ms'] = RequestLog::elapsedMs($persistenceStarted);
            $recipientFields['persistence_outcome'] ??= $recipientStatus;
            if ($recipientStatus === 'failed' && !isset($recipientFields['reason'])) {
                $recipientFields['reason'] = 'persistence_failed';
            }
        }
        if ($index > 0 && $recipientLog !== null) {
            finishInfluenceRequest($recipientStatus, $recipientFields, $recipientLog);
        }
        if ($id === $primaryId) {
            $primaryResult = $recipientStatus;
            $primaryFields = $recipientFields;
        }
    }
    return $finishBatch($primaryResult, $primaryFields);
}

/**
 * Evaluate a single postrequest Player input event. $sourceInsert is the exact
 * main.php eventlogInsert snapshot, retained in $GLOBALS until plugin postrequest.
 */
function handlePlayerInput(
    array $gameRequest,
    mixed $sourceInsert,
    StoreDb $store,
    ?callable $requestModel = null,
    ?RequestLog $requestLog = null
): string
{
    if (!in_array($gameRequest[0] ?? null, playerInputTypes(), true)) {
        return 'ignored';
    }

    $logFields = ['stage' => 'preflight', 'model_outcome' => 'not_called'];
    $requestLog?->context(['speaker_kind' => 'player']);
    $requestLog?->context(['payload_bytes' => is_string($gameRequest[3] ?? null) ? strlen($gameRequest[3]) : 0]);
    $requestLog?->event('player_input_started', 'debug', ['stage' => 'preflight']);
    $status = evaluateInfluenceRequest($gameRequest, $store, $requestModel, $requestLog, $logFields, is_array($sourceInsert) ? $sourceInsert : []);
    return finishInfluenceRequest($status, $logFields, $requestLog);
}

/** @param array<string, mixed> $logFields */
function evaluateInfluenceRequest(
    array $gameRequest,
    StoreDb $store,
    ?callable $requestModel,
    ?RequestLog $requestLog,
    array &$logFields,
    ?array $playerInputInsert = null
): string
{
    try {
        $storedRelationships = null;
        $canonicalRelationshipsValidated = false;
        $interactionReason = null;
        $interactionStatus = speechAckInteractionStatus($interactionReason);
        if ($interactionStatus !== 'ok') {
            $logFields['reason'] = $interactionReason;
            return $interactionStatus;
        }
        $pauseReason = null;
        $pauseStatus = mindPoisoningPauseStatus($pauseReason);
        if ($pauseStatus !== 'enabled') {
            $logFields['reason'] = $pauseReason;
            return $pauseStatus;
        }
        // main.php can rewrite this effective value after capturing the routing snapshot.
        if ($playerInputInsert !== null && !in_array(
            $GLOBALS['CHIM_EXECUTION_MODE'] ?? null,
            ['STANDARD', 'WHISPER', 'CLOSE'],
            true
        )) {
            $logFields['reason'] = 'player-input-not-speech';
            return 'player-input-not-speech';
        }
        if (!function_exists('chimIsGlobalLlmConnectorEnabled') || !\chimIsGlobalLlmConnectorEnabled('RELLLM_CONNECTOR')) {
            return 'disabled';
        }
        $connectorId = filter_var($GLOBALS['RELLLM_CONNECTOR'] ?? null, FILTER_VALIDATE_INT);
        if ($connectorId === false || $connectorId === null || $connectorId < 1) {
            return 'connector-invalid';
        }
        $logFields['connector_id'] = $connectorId;
        $requestLog?->context(['connector_id' => $connectorId]);
        if ($playerInputInsert === null) {
            if (
                !function_exists('extractSpeakerNameFromChatEvent')
                || !function_exists('extractTalkTargetMetadata')
                || !function_exists('talkTargetsIncludeName')
            ) {
                return 'event-helpers-unavailable';
            }
        } elseif (
            !function_exists('chimDecodePlayerRoutingSnapshotField')
            || !function_exists('parsePeoplePipeList')
        ) {
            return 'routing-helpers-unavailable';
        }
        $dedupeCheckedBeforeBatch = false;
        if ($playerInputInsert !== null) {
            $logFields['stage'] = 'correlation';
            $profile = $store->activePlaythrough();
            if (!is_array($profile) || !is_string($profile['id'] ?? null) || $profile['id'] === '') {
                return 'stale';
            }
            $requestLog?->context(['playthrough_id' => $profile['id']]);
            $playerName = is_string($profile['player_name'] ?? null) ? trim($profile['player_name']) : '';
            if ($playerName === '') {
                return 'player-identity-unavailable';
            }
            $playerInputInsert = validatedPlayerInputInsert($gameRequest, $playerInputInsert);
            if ($playerInputInsert === null) {
                return 'event-unmatched';
            }

            $source = $store->playerInputEvent($playerInputInsert);
            if (!is_array($source)) {
                return 'event-unmatched';
            }
            $requestLog?->context([
                'speaker_kind' => 'player',
                'event_id' => $source['event_id'],
                'utterance_id' => $source['utterance_id'],
                'playthrough_id' => $profile['id'],
            ]);

            $routeField = is_string($gameRequest[4] ?? null) ? $gameRequest[4] : '';
            $route = \chimDecodePlayerRoutingSnapshotField($routeField);
            $targetMode = is_array($route) ? ($route['target_mode'] ?? null) : null;
            $listenerInput = is_array($route) ? ($route['listener'] ?? null) : null;
            if (
                !in_array($targetMode, ['direct', 'automatic'], true)
                || !is_string($listenerInput) || trim($listenerInput) === ''
                || isPlayerName($listenerInput, $playerName)
            ) {
                return 'listener-unmatched';
            }
            $listenerInput = trim($listenerInput);
            $sourcePeople = $source['source_people'] ?? null;
            if (!is_string($sourcePeople) || trim($sourcePeople) === '') {
                return 'listener-unmatched';
            }
            $people = \parsePeoplePipeList($sourcePeople);
            $audienceMatches = array_values(array_filter(
                $people,
                static fn(mixed $name): bool => is_string($name) && sameActorName($name, $listenerInput)
            ));
            if (count($audienceMatches) !== 1) {
                return 'listener-unmatched';
            }

            $playerTtsSource = $GLOBALS['PLAYER_TTS_SOURCE_TEXT'] ?? null;
            $sourceData = $source['source_data'] ?? null;
            if (is_string($playerTtsSource)) {
                $playerTtsSource = rtrim($playerTtsSource);
            }
            if (
                !is_string($playerName) || trim($playerName) === ''
                || !is_string($playerTtsSource) || $playerTtsSource === ''
                || strlen($playerTtsSource) > 12000 || preg_match('//u', $playerTtsSource) !== 1
                || !is_string($sourceData) || strlen($sourceData) > 16384
                || !str_starts_with($sourceData, $playerTtsSource)
            ) {
                return 'player-text-invalid';
            }
            $speakerColon = strpos($playerTtsSource, ':');
            if ($speakerColon === false || !sameActorName(trim(substr($playerTtsSource, 0, $speakerColon)), $playerName)) {
                return 'player-identity-mismatch';
            }
            $text = trim(substr($playerTtsSource, $speakerColon + 1));
            if ($text === '' || preg_match('//u', $text) !== 1) {
                return 'player-text-invalid';
            }
            $text = str_replace(["\r", "\n", "|"], ' ', $text);
            $text = preg_replace('/\s+/', ' ', $text);
            if (!is_string($text) || $text === '') {
                return 'player-text-invalid';
            }
            $logFields['speech_bytes'] = strlen($text);

            $identities = $store->npcIdentities();
            $listenerIdentity = uniqueNpcNamed($identities, $listenerInput);
            if (!is_array($listenerIdentity)) {
                return 'actor-unmatched';
            }
            $listener = $store->npcById($listenerIdentity['id']);
            if (!is_array($listener) || !sameActorName($listener['npc_name'] ?? null, $listenerInput)) {
                return 'actor-stale';
            }
            $requestLog?->context(['listener_id' => $listenerIdentity['id']]);
            $listenerExtended = $listener['extended_data'] ?? null;
            if (!$listenerExtended instanceof \stdClass) {
                $logFields['reason'] = 'listener_extended_data_invalid';
                return 'listener-invalid';
            }
            if (!empty($listenerExtended->relationships_locked) || (int)($listener['lock_profile'] ?? 0) !== 0) {
                return 'locked';
            }
            $relationshipValue = property_exists($listenerExtended, 'relationships')
                ? $listenerExtended->relationships
                : new \stdClass();
            $storedRelationships = storedRelationshipMap($relationshipValue);
            if (!is_array($storedRelationships)) {
                $logFields['reason'] = 'listener_relationships_invalid';
                return 'listener-invalid';
            }
            $canonicalRelationships = canonicalStoredRelationshipMap($storedRelationships, $playerName);
            if (!is_array($canonicalRelationships)) {
                $logFields['reason'] = 'listener_relationships_invalid';
                return 'listener-invalid';
            }
            $canonicalRelationshipsValidated = true;

            $speaker = [];
            $event = [
                'utterance_id' => $source['utterance_id'],
                'speaker_kind' => 'player',
                'speaker_id' => null,
                'listener_id' => $listenerIdentity['id'],
                'speaker_name' => $playerName,
                'listener_name' => $listener['npc_name'],
                'text' => $text,
                'gamets' => $source['gamets'],
                'event_id' => $source['event_id'],
                'playthrough_id' => $profile['id'],
                'player_name' => $playerName,
                'source_data' => $sourceData,
                'source_kind' => $source['source_kind'],
                'source_type' => $source['source_type'],
                'source_ts' => $source['source_ts'],
                'source_gamets' => $source['source_gamets'],
                'source_localts' => $source['source_localts'],
                'source_sess' => $source['source_sess'],
                'source_people' => $source['source_people'],
            ];
            $restorePolicyInitEventId = null;
            $boundaryStatus = captureRestorePolicyEventBoundary(
                $store,
                [$source['event_id']],
                $gameRequest,
                true,
                $requestLog,
                $logFields,
                $restorePolicyInitEventId
            );
            if ($boundaryStatus !== null) {
                return $boundaryStatus;
            }
            if (is_int($restorePolicyInitEventId)) {
                $event['restore_policy_init_event_id'] = $restorePolicyInitEventId;
            }
        } else {
            $raw = $gameRequest[3] ?? null;
            if (!is_string($raw)) {
                $logFields['reason'] = 'payload_raw_type_invalid';
                return 'oversized';
            }
            if (strlen($raw) > 16384) {
                $logFields['reason'] = 'payload_raw_oversized';
                return 'oversized';
            }
            if (preg_match('//u', $raw) !== 1) {
                $logFields['reason'] = 'payload_invalid_utf8';
                return 'invalid-payload';
            }
            try {
                $decodedPayload = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $logFields['reason'] = 'payload_json_invalid';
                return 'invalid-payload';
            }
            if (!$decodedPayload instanceof \stdClass) {
                $logFields['reason'] = 'payload_root_invalid';
                return 'invalid-payload';
            }
            $payload = get_object_vars($decodedPayload);
            $requiredFields = ['speaker', 'listener', 'speech'];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $payload)) {
                    $logFields['reason'] = 'payload_field_missing';
                    return 'invalid-payload';
                }
            }
            foreach ($requiredFields as $field) {
                if (!is_string($payload[$field])) {
                    $logFields['reason'] = 'payload_field_type_invalid';
                    return 'invalid-payload';
                }
            }
            $hasUtteranceId = array_key_exists('utterance_id', $payload);
            if ($hasUtteranceId && !is_string($payload['utterance_id'])) {
                $logFields['reason'] = 'payload_utterance_id_type_invalid';
                return 'invalid-payload';
            }
            $speakerInput = $payload['speaker'];
            $listenerInput = $payload['listener'];
            $speech = $payload['speech'];
            if (strlen($speakerInput) > 256 || strlen($listenerInput) > 256 || strlen($speech) > 12000) {
                $logFields['reason'] = 'payload_field_oversized';
                return 'invalid-payload';
            }
            if (trim($speakerInput) === '' || trim($listenerInput) === '' || trim($speech) === '') {
                $logFields['reason'] = 'payload_field_empty';
                return 'invalid-payload';
            }
            if (preg_match('//u', $speakerInput) !== 1 || preg_match('//u', $listenerInput) !== 1 || preg_match('//u', $speech) !== 1) {
                $logFields['reason'] = 'payload_invalid_utf8';
                return 'invalid-payload';
            }
            $utteranceId = $hasUtteranceId ? trim($payload['utterance_id']) : '';
            if ($utteranceId === '') {
                $logFields['reason'] = 'utterance_id_absent';
                return 'untracked-speech';
            }
            if (preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/', $utteranceId) !== 1) {
                $logFields['reason'] = 'payload_utterance_id_invalid';
                return 'invalid-payload';
            }
            $speakerInput = trim($speakerInput);
            $listenerInput = trim($listenerInput);
            $speech = trim($speech);
            $logFields['speech_bytes'] = strlen($speech);
            $requestLog?->context(['utterance_id' => $utteranceId]);
            $logFields['stage'] = 'correlation';

            $profile = $store->activePlaythrough();
            if (!is_array($profile) || !is_string($profile['id'] ?? null) || $profile['id'] === '') {
                return 'stale';
            }
            $requestLog?->context(['playthrough_id' => $profile['id']]);
            $playerName = is_string($profile['player_name'] ?? null) ? trim($profile['player_name']) : '';
            if (isPlayerName($speakerInput, $playerName) || isPlayerName($listenerInput, $playerName)) {
                return 'player-actor';
            }
            if (sameActorName($listenerInput, 'explicit_disable_rechat')) {
                return 'actor-unmatched';
            }

            $overhearingRequested = overhearingSettingEnabled();
            $source = $overhearingRequested && $store instanceof WitnessSnapshotStoreDb
                ? $store->acknowledgedEventWithPeople($utteranceId)
                : $store->acknowledgedEvent($utteranceId);
            $sourceData = is_array($source) ? ($source['source_data'] ?? null) : null;
            if (!is_string($sourceData) || strlen($sourceData) > 16384) {
                return 'event-unmatched';
            }
            $sourceSpeaker = \extractSpeakerNameFromChatEvent($sourceData);
            $target = \extractTalkTargetMetadata($sourceData);
            if (
                !is_string($sourceSpeaker) || !sameActorName($sourceSpeaker, $speakerInput)
                || !is_array($target) || empty($target['hasExplicitTarget']) || !empty($target['isBroadcast'])
                || !\talkTargetsIncludeName($target['targets'] ?? [], $listenerInput)
            ) {
                return 'event-mismatch';
            }
            $requestLog?->context(['event_id' => $source['event_id']]);

            $identities = $store->npcIdentities();
            $speakerIdentity = uniqueNpcNamed($identities, $speakerInput);
            $listenerIdentity = uniqueNpcNamed($identities, $listenerInput);
            if (!is_array($speakerIdentity) || !is_array($listenerIdentity) || $speakerIdentity['id'] === $listenerIdentity['id']) {
                return 'actor-unmatched';
            }
            $speaker = $store->npcById($speakerIdentity['id']);
            $listener = $store->npcById($listenerIdentity['id']);
            if (
                !is_array($speaker) || !is_array($listener)
                || !sameActorName($speaker['npc_name'] ?? null, $speakerInput)
                || !sameActorName($listener['npc_name'] ?? null, $listenerInput)
            ) {
                return 'actor-stale';
            }
            $requestLog?->context([
                'speaker_id' => $speakerIdentity['id'],
                'listener_id' => $listenerIdentity['id'],
            ]);
            $event = [
                'utterance_id' => $utteranceId,
                'speaker_id' => $speakerIdentity['id'],
                'listener_id' => $listenerIdentity['id'],
                'speaker_name' => $speaker['npc_name'],
                'listener_name' => $listener['npc_name'],
                'text' => $speech,
                'gamets' => $source['gamets'],
                'event_id' => $source['event_id'],
                'playthrough_id' => $profile['id'],
                'player_name' => $playerName,
                'source_data' => $sourceData,
            ];
            $restorePolicyInitEventId = null;
            $boundaryStatus = captureRestorePolicyEventBoundary(
                $store,
                [$source['event_id']],
                $gameRequest,
                false,
                $requestLog,
                $logFields,
                $restorePolicyInitEventId
            );
            if ($boundaryStatus !== null) {
                return $boundaryStatus;
            }
            if (is_int($restorePolicyInitEventId)) {
                $event['restore_policy_init_event_id'] = $restorePolicyInitEventId;
            }
            if (!mindPoisoningRestorePolicyEnabled()) {
                $dedupeReason = null;
                if (eventAlreadyProcessed($listener, $event['playthrough_id'], $event['event_id'], $event['utterance_id'], $dedupeReason)) {
                    $logFields['reason'] = $dedupeReason ?? 'duplicate';
                    return 'duplicate';
                }
                $dedupeCheckedBeforeBatch = true;
            }
            if ($overhearingRequested) {
                $batchStatus = evaluateOverhearingAck(
                    $event,
                    $source,
                    $speaker,
                    $listener,
                    $identities,
                    $playerName,
                    $connectorId,
                    $store,
                    $requestModel,
                    $requestLog,
                    $logFields
                );
                if ($batchStatus !== null) {
                    return $batchStatus;
                }
            }
            $listenerExtended = $listener['extended_data'] ?? null;
            if (!$listenerExtended instanceof \stdClass) {
                $logFields['reason'] = 'listener_extended_data_invalid';
                return 'listener-invalid';
            }
            if (!empty($listenerExtended->relationships_locked) || (int)($listener['lock_profile'] ?? 0) !== 0) {
                return 'locked';
            }
        }

        if (mindPoisoningRestorePolicyEnabled()) {
            $restorePolicyStatus = prepareRestorePolicyRecipient($event, $listener, $store, $requestLog, $logFields);
            if ($restorePolicyStatus !== null) {
                return $restorePolicyStatus;
            }
            $listenerExtended = $listener['extended_data'] ?? null;
            if (!$listenerExtended instanceof \stdClass) {
                $logFields['reason'] = 'listener_extended_data_invalid';
                return 'listener-invalid';
            }
            $relationshipValue = property_exists($listenerExtended, 'relationships')
                ? $listenerExtended->relationships
                : new \stdClass();
            $storedRelationships = storedRelationshipMap($relationshipValue);
            if (!is_array($storedRelationships)) {
                $logFields['reason'] = 'listener_relationships_invalid';
                return 'listener-invalid';
            }
            $canonicalRelationshipsValidated = false;
        }
        $dedupeReason = null;
        if (!$dedupeCheckedBeforeBatch && eventAlreadyProcessed($listener, $event['playthrough_id'], $event['event_id'], $event['utterance_id'], $dedupeReason)) {
            $logFields['reason'] = $dedupeReason ?? 'duplicate';
            return 'duplicate';
        }

        $subjects = findSubjects($event, $identities, $playerName);
        $logFields['subject_count'] = count($subjects);
        if (count($subjects) > 8) {
            return 'too-many-subjects';
        }
        if ($subjects === []) {
            return 'no-subjects';
        }
        if ($playerInputInsert === null) {
            $relationshipValue = property_exists($listenerExtended, 'relationships')
                ? $listenerExtended->relationships
                : new \stdClass();
            $storedRelationships = storedRelationshipMap($relationshipValue);
            if (!is_array($storedRelationships)) {
                $logFields['reason'] = 'listener_relationships_invalid';
                return 'listener-invalid';
            }
        }
        if (array_key_exists('player', $subjects) && !$canonicalRelationshipsValidated) {
            $canonicalRelationships = canonicalStoredRelationshipMap($storedRelationships, $playerName);
            if (!is_array($canonicalRelationships)) {
                $logFields['reason'] = 'listener_relationships_invalid';
                return 'listener-invalid';
            }
        }
        foreach ($subjects as $subject) {
            if ($subject['id'] === null) {
                continue;
            }
            $row = $store->npcById($subject['id']);
            if (!is_array($row) || !sameActorName($row['npc_name'] ?? null, $subject['name'])) {
                return 'subject-stale';
            }
        }

        $speakerPrompt = ($event['speaker_kind'] ?? 'npc') === 'player' ? [] : promptNpcCopy($speaker);
        $listenerPrompt = promptNpcCopy($listener);
        $currentTypes = relationshipTypeSnapshot($listenerPrompt, $subjects, $playerName);
        $messages = buildMessages($event, $speakerPrompt, $listenerPrompt, $subjects, $currentTypes);
        $requestModel ??= __NAMESPACE__ . '\\requestJudgments';
        $requestLog?->context(['connector_id' => $connectorId, 'subject_count' => count($subjects)]);
        $eligibleEvent = ($event['speaker_kind'] ?? 'npc') === 'player' ? 'player_input_eligible' : 'ack_eligible';
        $requestLog?->event($eligibleEvent, 'debug', [
            'stage' => 'model',
            'connector_id' => $connectorId,
            'subject_count' => count($subjects),
        ]);
        $logFields['stage'] = 'model';
        $requestLog?->event('model_started', 'debug', [
            'stage' => 'model',
            'connector_id' => $connectorId,
            'subject_count' => count($subjects),
        ]);
        $modelStarted = hrtime(true);
        try {
            $response = $requestModel($messages);
        } catch (Throwable $error) {
            $logFields['model_ms'] = RequestLog::elapsedMs($modelStarted);
            $logFields['model_outcome'] = 'failed';
            $failureReason = $error instanceof ModelRequestFailure ? $error->reasonCode : 'model_request_failed';
            $logFields['reason'] = $failureReason;
            $requestLog?->event('model_finished', 'error', [
                'stage' => 'model',
                'model_outcome' => 'failed',
                'reason' => $failureReason,
                'model_ms' => $logFields['model_ms'],
            ]);
            return 'failed';
        }
        $logFields['model_ms'] = RequestLog::elapsedMs($modelStarted);
        if (!is_string($response)) {
            $logFields['model_outcome'] = 'invalid';
            $logFields['reason'] = 'model_response_invalid';
            $requestLog?->event('model_finished', 'warning', [
                'stage' => 'model',
                'model_outcome' => 'invalid',
                'reason' => 'model_response_invalid',
                'model_ms' => $logFields['model_ms'],
            ]);
            return 'model-invalid';
        }
        try {
            $judgments = parseJudgments($response, $subjects, $event['text'], $currentTypes);
        } catch (JudgmentValidationFailure $error) {
            $validationReason = $error->reasonCode;
            $logFields['model_outcome'] = 'invalid';
            $logFields['reason'] = $validationReason;
            $requestLog?->event('model_finished', 'warning', [
                'stage' => 'model_validation',
                'model_outcome' => 'invalid',
                'reason' => $validationReason,
                'model_ms' => $logFields['model_ms'],
            ]);
            return 'failed';
        } catch (Throwable) {
            $validationReason = 'judgment_parser_internal_failed';
            $logFields['model_outcome'] = 'failed';
            $logFields['reason'] = $validationReason;
            $requestLog?->event('model_finished', 'error', [
                'stage' => 'model_validation',
                'model_outcome' => 'failed',
                'reason' => $validationReason,
                'model_ms' => $logFields['model_ms'],
            ]);
            return 'failed';
        }
        $logFields['model_outcome'] = 'validated';
        $requestLog?->event('model_finished', 'info', [
            'stage' => 'model_validation',
            'model_outcome' => 'validated',
            'model_ms' => $logFields['model_ms'],
        ]);
        foreach ($judgments as $subject => $judgment) {
            $requestLog?->event('judgment_proposal', 'debug', [
                'stage' => 'model_validation',
                'subject' => $subject,
                'delta' => $judgment['delta'],
                'model_reason' => $judgment['reason'],
            ]);
        }
        $interactionReason = null;
        $interactionStatus = speechAckInteractionStatus($interactionReason);
        if ($interactionStatus !== 'ok') {
            $logFields['stage'] = 'post_model_gate';
            $logFields['reason'] = $interactionReason;
            return $interactionStatus;
        }
        $logFields['stage'] = 'post_model_gate';
        $pauseReason = null;
        $pauseStatus = mindPoisoningPauseStatus($pauseReason);
        if ($pauseStatus !== 'enabled') {
            $logFields['reason'] = $pauseReason;
            return $pauseStatus;
        }
        $logFields['stage'] = 'persistence';
        $persistenceStarted = hrtime(true);
        try {
            $status = persistJudgments($event, $subjects, $judgments, $store, $requestLog);
        } finally {
            $logFields['persistence_ms'] = RequestLog::elapsedMs($persistenceStarted);
        }
        $logFields['persistence_outcome'] = $status;
        if ($status === 'failed' && !isset($logFields['reason'])) {
            $logFields['reason'] = 'persistence_failed';
        }
        return $status;
    } catch (Throwable) {
        if (!isset($logFields['reason'])) {
            $logFields['reason'] = ($logFields['stage'] ?? null) === 'persistence'
                ? 'persistence_failed'
                : 'evaluation_failed';
        }
        if (($logFields['stage'] ?? null) === 'persistence') {
            $logFields['persistence_outcome'] = 'failed';
        }
        return 'failed';
    }
}

if (isset($gameRequest) && is_array($gameRequest) && ($gameRequest[0] ?? null) === '_speech') {
    try {
        $chimMindPoisoningStore = new PostgresStoreDb($chimMindPoisoningRequestLog);
        handleSpeechAck($gameRequest, $chimMindPoisoningStore, null, $chimMindPoisoningRequestLog);
    } catch (Throwable) {
        if ($chimMindPoisoningRequestLog instanceof RequestLog) {
            $chimMindPoisoningRequestLog->finish('failed', 'runtime_bootstrap_failed', ['stage' => 'bootstrap']);
        } else {
            @error_log('Mind Poisoning ACK failed: runtime_bootstrap_failed');
        }
    }
}
