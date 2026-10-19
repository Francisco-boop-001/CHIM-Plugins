<?php
declare(strict_types=1);

namespace ChimMindPoisoning;

use JsonException;
use Throwable;

const MIND_POISONING_REFLECTION_API_VERSION = 1;
const MIND_POISONING_REFLECTION_REPLY_API_VERSION = 2;

require_once __DIR__ . '/controls.php';
require_once __DIR__ . '/influence.php';
require_once __DIR__ . '/model.php';
require_once __DIR__ . '/store.php';

/**
 * Evaluate one registered solo-reflection ACK. The revalidation callback must
 * read the server-only PCV registration and confirm the exact active scope.
 */
function mindPoisoningEvaluateReflection(
    array $registration,
    array $gameRequest,
    StoreDb $store,
    callable $revalidate,
    ?callable $requestModel = null,
    ?RequestLog $requestLog = null
): string {
    return runReflectionEvaluation($registration, $gameRequest, $store, $revalidate, $requestModel, $requestLog, false);
}

/**
 * Evaluate a complete server-registered reply. Emitted source rows show line
 * attempts; they do not prove hearing or completed audio playback.
 */
function mindPoisoningEvaluateReflectionReply(
    array $registration,
    array $gameRequest,
    StoreDb $store,
    callable $revalidate,
    ?callable $requestModel = null,
    ?RequestLog $requestLog = null
): string {
    return runReflectionEvaluation($registration, $gameRequest, $store, $revalidate, $requestModel, $requestLog, true);
}

function runReflectionEvaluation(
    array $registration,
    array $gameRequest,
    StoreDb $store,
    callable $revalidate,
    ?callable $requestModel,
    ?RequestLog $requestLog,
    bool $fullReply
): string {
    if ($requestLog === null) {
        try {
            $requestLog = new RequestLog();
        } catch (Throwable) {
            $requestLog = null;
        }
    }
    $logFields = ['stage' => 'preflight', 'model_outcome' => 'not_called'];
    $requestLog?->context([
        'source_kind' => 'reflection',
        'payload_bytes' => is_string($gameRequest[3] ?? null) ? strlen($gameRequest[3]) : 0,
    ]);
    $requestLog?->event('reflection_started', 'debug', ['stage' => 'preflight']);

    try {
        $status = evaluateReflection($registration, $gameRequest, $store, $revalidate, $requestModel, $requestLog, $logFields, $fullReply);
    } catch (Throwable) {
        $status = 'failed';
        $logFields['reason'] ??= 'reflection-failed';
    }

    $reason = is_string($logFields['reason'] ?? null) ? $logFields['reason'] : $status;
    $outcome = match ($status) {
        'committed' => 'committed',
        'failed' => 'failed',
        'invalid', 'model-invalid', 'invalid-payload' => 'rejected',
        'too-large' => $fullReply ? 'rejected' : 'skipped',
        default => 'skipped',
    };
    if ($status === 'event-mismatch' && in_array($reason, ['reflection-ack-mismatch', 'reflection-source-mismatch'], true)) {
        $outcome = 'rejected';
    }
    if ($status === 'listener-invalid' && in_array($reason, [
        'restore-policy-ledger-invalid',
        'restore-policy-owner-invalid',
        'restore-policy-owner-state-invalid',
        'restore-policy-timeline-invalid',
    ], true)) {
        $outcome = 'rejected';
    }
    $requestLog?->finish($outcome, $reason, $logFields);
    return $status;
}

function reflectionRegistrationValid(array $registration): bool
{
    $keys = ['event_id', 'utterance_id', 'actor_id', 'actor_name', 'playthrough_id', 'config_id', 'rechat_target_hint', 'speech_hash'];
    $actualKeys = array_keys($registration);
    sort($actualKeys, SORT_STRING);
    sort($keys, SORT_STRING);
    if ($actualKeys !== $keys) {
        return false;
    }
    return is_int($registration['event_id']) && $registration['event_id'] > 0
        && is_string($registration['utterance_id']) && preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/D', $registration['utterance_id']) === 1
        && is_int($registration['actor_id']) && $registration['actor_id'] > 0
        && is_string($registration['actor_name']) && trim($registration['actor_name']) !== ''
        && strlen($registration['actor_name']) <= 256 && preg_match('//u', $registration['actor_name']) === 1
        && is_string($registration['playthrough_id']) && $registration['playthrough_id'] !== ''
        && strlen($registration['playthrough_id']) <= 64
        && is_string($registration['config_id'])
        && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/iD', $registration['config_id']) === 1
        && $registration['rechat_target_hint'] === 'explicit_disable_rechat'
        && is_string($registration['speech_hash']) && preg_match('/\A[a-f0-9]{64}\z/D', $registration['speech_hash']) === 1;
}

function logReflectionRegistrationContext(array $registration, ?RequestLog $requestLog): void
{
    $requestLog?->context([
        'event_id' => $registration['event_id'],
        'config_id' => $registration['config_id'],
        'utterance_id' => $registration['utterance_id'],
        'playthrough_id' => $registration['playthrough_id'],
        'speaker_id' => $registration['actor_id'],
        'speaker_kind' => 'npc',
        'opinion_owner_id' => $registration['actor_id'],
    ]);
}

function reflectionReplyRegistrationValid(array $registration): bool
{
    $lines = $registration['lines'] ?? null;
    $base = $registration;
    unset($base['lines']);
    if (!reflectionRegistrationValid($base) || !is_array($lines) || !array_is_list($lines) || count($lines) < 1 || count($lines) > MIND_POISONING_REFLECTION_REPLY_MAX_LINES) {
        return false;
    }

    $seenUtterances = [];
    $previousEventId = 0;
    foreach ($lines as $line) {
        $keys = is_array($line) ? array_keys($line) : [];
        sort($keys, SORT_STRING);
        if (
            $keys !== ['event_id', 'speech_hash', 'utterance_id']
            || !is_int($line['event_id'] ?? null) || $line['event_id'] <= $previousEventId
            || !is_string($line['utterance_id'] ?? null) || preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/D', $line['utterance_id']) !== 1
            || isset($seenUtterances[$line['utterance_id']])
            || !is_string($line['speech_hash'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/D', $line['speech_hash']) !== 1
        ) {
            return false;
        }
        $previousEventId = $line['event_id'];
        $seenUtterances[$line['utterance_id']] = true;
    }

    $last = $lines[array_key_last($lines)];
    return $registration['event_id'] === $last['event_id']
        && $registration['utterance_id'] === $last['utterance_id']
        && hash_equals($registration['speech_hash'], $last['speech_hash']);
}

/**
 * Source rows identify exact lines; only the companion callback can establish
 * that this complete ordered list came from one immutable server reply.
 */
function reflectionReplySourceSnapshot(StoreDb $store, array $registration, ?string &$reason = null): ?array
{
    $reason = null;
    $sources = [];
    $texts = [];
    foreach ($registration['lines'] as $line) {
        $source = $store->acknowledgedEvent($line['utterance_id']);
        if (
            !is_array($source) || ($source['event_id'] ?? null) !== $line['event_id']
            || ($source['utterance_id'] ?? null) !== $line['utterance_id']
            || !in_array($source['delivery_state'] ?? null, ['emitted', 'spoken'], true)
            || !is_string($source['source_data'] ?? null)
            || !is_numeric($source['gamets'] ?? null) || !is_finite((float)$source['gamets'])
        ) {
            $reason = 'reflection-source-unmatched';
            return null;
        }
        $parts = reflectionSourceParts($source['source_data']);
        if (
            !is_array($parts) || !sameActorName($parts['speaker'] ?? null, $registration['actor_name'])
            || !hash_equals($line['speech_hash'], hash('sha256', $parts['text']))
        ) {
            $reason = 'reflection-source-mismatch';
            return null;
        }
        $sources[] = [
            'event_id' => $line['event_id'],
            'utterance_id' => $line['utterance_id'],
            'speech_hash' => $line['speech_hash'],
            'gamets' => (float)$source['gamets'],
            'source_data' => $source['source_data'],
            'text' => $parts['text'],
        ];
        $texts[] = $parts['text'];
    }

    $text = implode(' ', $texts);
    if (strlen($text) > 8000 || mb_strlen($text, 'UTF-8') > 2000) {
        $reason = 'reflection-reply-too-large';
        return null;
    }
    return ['sources' => $sources, 'text' => $text];
}

function reflectionReplySourcesCurrent(StoreDb $store, array $sources, string $actorName): bool
{
    if (!array_is_list($sources) || count($sources) < 1 || count($sources) > MIND_POISONING_REFLECTION_REPLY_MAX_LINES) {
        return false;
    }
    foreach ($sources as $source) {
        if (
            !is_array($source) || !is_int($source['event_id'] ?? null) || $source['event_id'] < 1
            || !is_string($source['utterance_id'] ?? null)
            || !is_string($source['speech_hash'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/D', $source['speech_hash']) !== 1
            || !is_numeric($source['gamets'] ?? null) || !is_string($source['source_data'] ?? null)
            || !is_string($source['text'] ?? null)
        ) {
            return false;
        }
        try {
            $current = $store->eventById($source['event_id'], $source['utterance_id']);
        } catch (Throwable) {
            return false;
        }
        if (
            !is_array($current) || ($current['event_id'] ?? null) !== $source['event_id']
            || ($current['utterance_id'] ?? null) !== $source['utterance_id']
            || !in_array($current['delivery_state'] ?? null, ['emitted', 'spoken'], true)
            || (float)($current['gamets'] ?? -1) !== (float)$source['gamets']
            || ($current['source_data'] ?? null) !== $source['source_data']
        ) {
            return false;
        }
        $parts = reflectionSourceParts($current['source_data']);
        if (
            !is_array($parts) || !sameActorName($parts['speaker'] ?? null, $actorName)
            || ($parts['text'] ?? null) !== $source['text']
            || !hash_equals($source['speech_hash'], hash('sha256', $parts['text']))
        ) {
            return false;
        }
    }
    return true;
}

function reflectionAckPayload(array $gameRequest): ?array
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
    if (!$payload instanceof \stdClass) {
        return null;
    }
    $fields = get_object_vars($payload);
    foreach (['speaker', 'listener', 'speech', 'utterance_id'] as $field) {
        if (!is_string($fields[$field] ?? null)) {
            return null;
        }
    }
    if (
        strlen($fields['speaker']) > 256 || strlen($fields['listener']) > 256 || strlen($fields['speech']) > 12000
        || trim($fields['speaker']) === '' || trim($fields['listener']) === '' || trim($fields['speech']) === ''
        || preg_match('//u', $fields['speaker']) !== 1 || preg_match('//u', $fields['listener']) !== 1 || preg_match('//u', $fields['speech']) !== 1
    ) {
        return null;
    }
    $fields['speaker'] = trim($fields['speaker']);
    $fields['listener'] = trim($fields['listener']);
    $fields['speech'] = trim($fields['speech']);
    $fields['utterance_id'] = trim($fields['utterance_id']);
    return preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/D', $fields['utterance_id']) === 1 ? $fields : null;
}

function reflectionPlayerTransport(string $listener, string $playerName): bool
{
    foreach ([$playerName, 'Player', 'the Player', 'Dragonborn', 'the Dragonborn'] as $candidate) {
        if (trim($candidate) !== '' && sameActorName($listener, $candidate)) {
            return true;
        }
    }
    return false;
}

/** @param array<string, mixed> $logFields */
function evaluateReflection(
    array $registration,
    array $gameRequest,
    StoreDb $store,
    callable $revalidate,
    ?callable $requestModel,
    ?RequestLog $requestLog,
    array &$logFields,
    bool $fullReply = false
): string {
    $fail = static function (string $status, string $reason) use (&$logFields): string {
        $logFields['reason'] = $reason;
        return $status;
    };
    if ($fullReply && is_array($registration['lines'] ?? null) && count($registration['lines']) > MIND_POISONING_REFLECTION_REPLY_MAX_LINES) {
        $baseRegistration = $registration;
        unset($baseRegistration['lines']);
        if (reflectionRegistrationValid($baseRegistration)) {
            logReflectionRegistrationContext($registration, $requestLog);
            return $fail('invalid-payload', 'reflection-reply-too-many-lines');
        }
    }
    $validRegistration = $fullReply
        ? reflectionReplyRegistrationValid($registration)
        : reflectionRegistrationValid($registration);
    if (!$validRegistration || ($gameRequest[0] ?? null) !== '_speech') {
        return $fail('invalid-payload', 'reflection-registration-invalid');
    }
    logReflectionRegistrationContext($registration, $requestLog);
    $interactionReason = null;
    if (speechAckInteractionStatus($interactionReason) !== 'ok') {
        return $fail('interaction-off', $interactionReason ?? 'interaction_off');
    }
    $pauseReason = null;
    if (mindPoisoningPauseStatus($pauseReason) !== 'enabled') {
        return $fail('paused', $pauseReason ?? 'plugin_paused');
    }
    if (!function_exists('chimIsGlobalLlmConnectorEnabled') || !\chimIsGlobalLlmConnectorEnabled('RELLLM_CONNECTOR')) {
        return $fail('disabled', 'connector-disabled');
    }
    $connectorId = positiveConnectorId($GLOBALS['RELLLM_CONNECTOR'] ?? null);
    if ($connectorId === null) {
        return $fail('connector-invalid', 'connector-invalid');
    }
    $profile = $store->activePlaythrough();
    $playerName = is_string($profile['player_name'] ?? null) ? trim($profile['player_name']) : '';
    if (
        !is_array($profile) || !is_string($profile['id'] ?? null) || $profile['id'] !== $registration['playthrough_id']
        || $playerName === '' || strlen($playerName) > 256 || preg_match('//u', $playerName) !== 1
    ) {
        return $fail('stale', 'reflection-scope-stale');
    }
    $ack = reflectionAckPayload($gameRequest);
    if (!is_array($ack)) {
        return $fail('event-mismatch', 'reflection-ack-mismatch');
    }
    $replySources = null;
    $replyText = null;
    if ($fullReply) {
        $ackLine = null;
        foreach ($registration['lines'] as $index => $line) {
            if ($line['utterance_id'] === $ack['utterance_id']) {
                $ackLine = [$index, $line];
                break;
            }
        }
        if (
            $ackLine === null || !sameActorName($ack['speaker'], $registration['actor_name'])
            || reflectionPlayerTransport($ack['speaker'], $playerName)
            || !reflectionPlayerTransport($ack['listener'], $playerName)
            || !hash_equals($ackLine[1]['speech_hash'], hash('sha256', $ack['speech']))
        ) {
            return $fail('event-mismatch', 'reflection-ack-mismatch');
        }
        if ($ackLine[0] !== array_key_last($registration['lines'])) {
            return $fail('non-final', 'reflection-non-final-ack');
        }
        $sourceReason = null;
        $snapshot = reflectionReplySourceSnapshot($store, $registration, $sourceReason);
        if (!is_array($snapshot)) {
            return $sourceReason === 'reflection-reply-too-large'
                ? $fail('too-large', $sourceReason)
                : $fail($sourceReason === 'reflection-source-unmatched' ? 'event-unmatched' : 'event-mismatch', $sourceReason ?? 'reflection-source-mismatch');
        }
        $replySources = $snapshot['sources'];
        $replyText = $snapshot['text'];
        $source = $replySources[array_key_last($replySources)];
    } else {
        if (
            $ack['utterance_id'] !== $registration['utterance_id']
            || !sameActorName($ack['speaker'], $registration['actor_name'])
            || reflectionPlayerTransport($ack['speaker'], $playerName)
            || !reflectionPlayerTransport($ack['listener'], $playerName)
            || !hash_equals($registration['speech_hash'], hash('sha256', $ack['speech']))
        ) {
            return $fail('event-mismatch', 'reflection-ack-mismatch');
        }
        $source = $store->acknowledgedEvent($registration['utterance_id']);
        if (
            !is_array($source) || ($source['event_id'] ?? null) !== $registration['event_id']
            || ($source['utterance_id'] ?? null) !== $registration['utterance_id']
            || !in_array($source['delivery_state'] ?? null, ['emitted', 'spoken'], true)
            || !is_string($source['source_data'] ?? null)
        ) {
            return $fail('event-unmatched', 'reflection-source-unmatched');
        }
        $parts = reflectionSourceParts($source['source_data']);
        if (!is_array($parts) || !sameActorName($parts['speaker'], $registration['actor_name'])) {
            return $fail('event-mismatch', 'reflection-source-mismatch');
        }
    }
    try {
        if (!$revalidate($registration, 'pre_model')) {
            return $fail('stale', 'reflection-registration-stale');
        }
    } catch (Throwable) {
        return $fail('stale', 'reflection-registration-stale');
    }
    if ($fullReply && !reflectionReplySourcesCurrent($store, $replySources, $registration['actor_name'])) {
        return $fail('stale', 'reflection-source-stale');
    }

    $restorePolicyInitEventId = null;
    $restoreSourceIds = $fullReply
        ? array_map(static fn(array $replySource): mixed => $replySource['event_id'] ?? null, $replySources)
        : [$source['event_id'] ?? null];
    $boundaryStatus = captureRestorePolicyEventBoundary(
        $store,
        $restoreSourceIds,
        $gameRequest,
        false,
        $requestLog,
        $logFields,
        $restorePolicyInitEventId
    );
    if ($boundaryStatus !== null) {
        return $boundaryStatus;
    }

    $identities = $store->npcIdentities();
    $matches = array_values(array_filter($identities, static fn(array $row): bool => sameActorName($row['npc_name'] ?? null, $registration['actor_name'])));
    if (count($matches) !== 1 || (int)($matches[0]['id'] ?? 0) !== $registration['actor_id']) {
        return $fail('actor-unmatched', 'reflection-actor-unmatched');
    }
    $actor = $store->npcById($registration['actor_id']);
    if (!is_array($actor) || !sameActorName($actor['npc_name'] ?? null, $registration['actor_name'])) {
        return $fail('actor-stale', 'reflection-actor-stale');
    }
    $extendedData = $actor['extended_data'] ?? null;
    $pluginData = $actor['plugin_extended_data'] ?? null;
    if (!$extendedData instanceof \stdClass || !$pluginData instanceof \stdClass) {
        return $fail('actor-invalid', 'reflection-actor-state-invalid');
    }
    if (!empty($extendedData->relationships_locked) || (int)($actor['lock_profile'] ?? 0) !== 0) {
        return $fail('locked', 'relationship-locked');
    }

    $event = [
        'utterance_id' => $registration['utterance_id'],
        'event_id' => $registration['event_id'],
        'speaker_kind' => 'npc',
        'speaker_id' => $registration['actor_id'],
        'opinion_owner_id' => $registration['actor_id'],
        'listener_id' => null,
        'speaker_name' => $actor['npc_name'],
        'text' => $fullReply ? $replyText : $ack['speech'],
        'gamets' => $source['gamets'],
        'playthrough_id' => $registration['playthrough_id'],
        'player_name' => $playerName,
        'source_data' => $source['source_data'],
        'source_kind' => 'reflection',
        'speech_hash' => $fullReply ? hash('sha256', $replyText) : $registration['speech_hash'],
    ];
    if ($fullReply) {
        $event['reflection_sources'] = $replySources;
    }
    if (is_int($restorePolicyInitEventId)) {
        $event['restore_policy_init_event_id'] = $restorePolicyInitEventId;
    }
    if (mindPoisoningRestorePolicyEnabled()) {
        $baselineFields = ['stage' => 'preflight', 'model_outcome' => 'not_called'];
        $baselineStatus = prepareRestorePolicyRecipient($event, $actor, $store, $requestLog, $baselineFields);
        if ($baselineStatus !== null) {
            $logFields = array_merge($logFields, $baselineFields);
            return $baselineStatus;
        }
        $extendedData = $actor['extended_data'] ?? null;
        $pluginData = $actor['plugin_extended_data'] ?? null;
        if (!$extendedData instanceof \stdClass || !$pluginData instanceof \stdClass) {
            return $fail('invalid', 'reflection-actor-state-invalid');
        }
    }
    $dedupeSources = $fullReply ? $replySources : [$event];
    foreach ($dedupeSources as $dedupeSource) {
        $dedupeReason = null;
        if (eventAlreadyProcessed($actor, $event['playthrough_id'], $dedupeSource['event_id'], $dedupeSource['utterance_id'], $dedupeReason)) {
            return $fail('duplicate', $dedupeReason ?? 'duplicate-event');
        }
    }
    $ledger = storedLedgerNamespace($pluginData);
    if ($ledger === null) {
        return $fail('invalid', 'ledger-invalid');
    }

    $subjects = findSubjects($event, $identities, $playerName);
    if (count($subjects) > 8) {
        return $fail('too-many-subjects', 'reflection-too-many-subjects');
    }
    if ($subjects === []) {
        return $fail('no-subjects', 'reflection-no-subjects');
    }
    $relationshipValue = property_exists($extendedData, 'relationships')
        ? $extendedData->relationships
        : new \stdClass();
    $relationships = storedRelationshipMap($relationshipValue);
    if (!is_array($relationships)) {
        return $fail('actor-invalid', 'relationships-invalid');
    }
    if (array_key_exists('player', $subjects)) {
        $canonicalRelationships = canonicalStoredRelationshipMap($relationships, $playerName);
        if (!is_array($canonicalRelationships)) {
            return $fail('actor-invalid', 'relationships-invalid');
        }
    }
    foreach ($subjects as $token => $subject) {
        if ($token === 'player') {
            continue;
        }
        $target = $store->npcById($subject['id']);
        $targetMatches = array_values(array_filter($identities, static fn(array $row): bool => sameActorName($row['npc_name'] ?? null, $subject['name'])));
        if (!is_array($target) || !sameActorName($target['npc_name'] ?? null, $subject['name']) || count($targetMatches) !== 1 || (int)$targetMatches[0]['id'] !== $subject['id']) {
            return $fail('subject-stale', 'reflection-subject-stale');
        }
    }

    $history = null;
    $basisReason = null;
    $basis = reflectionBasisForEvent($actor, $store, $event, $history, $basisReason);
    if (!is_string($basis) || !is_array($history)) {
        return $fail('stale', $basisReason ?? 'reflection-basis-unavailable');
    }
    $pending = [];
    foreach ($subjects as $token => $subject) {
        if (!reflectionBasisProcessed($actor, $event['playthrough_id'], $basis, $token)) {
            $pending[$token] = $subject;
        }
    }
    if ($pending === []) {
        return $fail('duplicate', 'reflection-basis-duplicate');
    }
    $storedState = $ledger !== [] && $ledger['playthrough_id'] === $event['playthrough_id'] ? ($ledger['reflection_state'] ?? null) : null;
    $existingCount = is_array($storedState) && ($storedState['basis'] ?? null) === $basis ? count($storedState['subjects']) : 0;
    $newCount = count(array_diff(array_keys($pending), is_array($storedState) && ($storedState['basis'] ?? null) === $basis ? $storedState['subjects'] : []));
    if ($existingCount + $newCount > 32) {
        return $fail('duplicate', 'reflection-subject-cap');
    }
    $subjects = $pending;

    $interactionReason = null;
    if (speechAckInteractionStatus($interactionReason) !== 'ok') {
        return $fail('interaction-stale', $interactionReason ?? 'interaction-stale');
    }
    $pauseReason = null;
    if (mindPoisoningPauseStatus($pauseReason) !== 'enabled') {
        return $fail('paused', $pauseReason ?? 'plugin-paused');
    }
    try {
        if (!$revalidate($registration, 'pre_model')) {
            return $fail('stale', 'reflection-registration-stale');
        }
    } catch (Throwable) {
        return $fail('stale', 'reflection-registration-stale');
    }
    if ($fullReply && !reflectionReplySourcesCurrent($store, $replySources, $registration['actor_name'])) {
        return $fail('stale', 'reflection-source-stale');
    }

    $requestModel ??= __NAMESPACE__ . '\\requestJudgments';
    $actorPrompt = $actor;
    $actorPrompt['extended_data'] = json_decode(json_encode($extendedData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true, 512, JSON_THROW_ON_ERROR);
    $currentTypes = relationshipTypeSnapshot($actorPrompt, $subjects, $event['player_name']);
    $messages = buildReflectionMessages($event + ['reflection_basis' => $basis], $actorPrompt, $subjects, $history, $currentTypes);
    $requestLog?->context(['connector_id' => $connectorId, 'subject_count' => count($subjects)]);
    $requestLog?->event('reflection_model_started', 'debug', ['stage' => 'model', 'connector_id' => $connectorId, 'subject_count' => count($subjects)]);
    $started = hrtime(true);
    try {
        $response = $requestModel($messages);
    } catch (Throwable $error) {
        $reason = $error instanceof ModelRequestFailure ? $error->reasonCode : 'model_request_failed';
        $logFields['model_outcome'] = 'failed';
        $logFields['reason'] = $reason;
        $logFields['model_ms'] = RequestLog::elapsedMs($started);
        $requestLog?->event('reflection_model_finished', 'error', ['stage' => 'model', 'model_outcome' => 'failed', 'reason' => $reason, 'model_ms' => $logFields['model_ms']]);
        return 'failed';
    }
    $logFields['model_ms'] = RequestLog::elapsedMs($started);
    if (!is_string($response)) {
        $logFields['model_outcome'] = 'invalid';
        $requestLog?->event('reflection_model_finished', 'warning', ['stage' => 'model', 'model_outcome' => 'invalid', 'reason' => 'model_response_invalid_type', 'model_ms' => $logFields['model_ms']]);
        return $fail('model-invalid', 'model_response_invalid_type');
    }
    try {
        $judgments = parseJudgments($response, $subjects, $event['text'], $currentTypes);
    } catch (JudgmentValidationFailure $error) {
        $logFields['model_outcome'] = 'invalid';
        $logFields['reason'] = $error->reasonCode;
        $requestLog?->event('reflection_model_finished', 'warning', ['stage' => 'model', 'model_outcome' => 'invalid', 'reason' => $error->reasonCode, 'model_ms' => $logFields['model_ms']]);
        return $fail('model-invalid', $error->reasonCode);
    }
    $logFields['model_outcome'] = 'valid';
    $requestLog?->event('reflection_model_finished', 'info', ['stage' => 'model', 'model_outcome' => 'valid', 'model_ms' => $logFields['model_ms']]);
    $logFields['stage'] = 'persistence';
    $status = persistJudgments($event + ['reflection_basis' => $basis], $subjects, $judgments, $store, $requestLog, $revalidate, $registration);
    $logFields['reason'] = $status;
    return $status;
}
