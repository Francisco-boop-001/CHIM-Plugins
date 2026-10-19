<?php
declare(strict_types=1);

namespace ChimMindPoisoning;

use RuntimeException;
use Throwable;

function sourceIsAfterRestoreBoundary(int $sourceId, int $initRowId): bool
{
    return $sourceId > $initRowId;
}

function baselineLedgerForPlaythroughScope(string $activeScope, int $eventFloor): array
{
    if ($activeScope === '' || $eventFloor < 0) {
        throw new RuntimeException('Invalid restore-policy scope baseline.');
    }
    return ['playthrough_id' => $activeScope, 'floor_event_id' => $eventFloor, 'events' => []];
}

function captureRestorePolicyBoundary(
    StoreDb $store,
    array $sourceEventIds,
    array $gameRequest,
    bool $postRequest,
    ?string &$reason = null
): ?int {
    $reason = 'restore-policy-routing-unavailable';
    $fastCommands = $GLOBALS['all_fast_commands'] ?? null;
    if (!is_array($fastCommands) || !array_is_list($fastCommands)) {
        return null;
    }
    foreach ($fastCommands as $command) {
        if (!is_string($command)) {
            return null;
        }
    }
    if (in_array('init', $fastCommands, true)) {
        $reason = 'restore-policy-init-fast';
        return null;
    }
    if (
        !$store instanceof RestorePolicyStoreDb
        || !array_is_list($sourceEventIds) || $sourceEventIds === []
        || ($postRequest
            ? !in_array($gameRequest[0] ?? null, playerInputTypes(), true)
            : ($gameRequest[0] ?? null) !== '_speech' || !in_array('_speech', $fastCommands, true))
    ) {
        $reason = !$store instanceof RestorePolicyStoreDb
            ? 'restore-policy-store-unavailable'
            : 'restore-policy-route-unsupported';
        return null;
    }
    try {
        $bounds = $store->restoreEventBounds();
    } catch (Throwable) {
        $reason = 'restore-policy-boundary-unavailable';
        return null;
    }
    if (
        !is_array($bounds)
        || !is_int($bounds['latest_init_event_id'] ?? null) || $bounds['latest_init_event_id'] < 1
        || !is_int($bounds['max_event_id'] ?? null) || $bounds['max_event_id'] < $bounds['latest_init_event_id']
    ) {
        $reason = 'restore-policy-boundary-unavailable';
        return null;
    }
    foreach ($sourceEventIds as $sourceId) {
        if (!is_int($sourceId) || $sourceId < 1 || $sourceId > $bounds['max_event_id']) {
            $reason = 'restore-policy-source-unavailable';
            return null;
        }
        if (!sourceIsAfterRestoreBoundary($sourceId, $bounds['latest_init_event_id'])) {
            $reason = 'restore-policy-source-before-init';
            return null;
        }
    }
    $reason = null;
    return $bounds['latest_init_event_id'];
}

/**
 * Baseline a mismatched namespace; callers that observed a mismatch must reload
 * the owner if this returns null because another request may have baselined first.
 */
function baselineRestorePolicyScopeIfNeeded(
    StoreDb $store,
    int $ownerId,
    string $expectedOwnerName,
    string $activeScope,
    string $expectedPlayerName,
    int $initEventId,
    ?string &$commitState = null
): ?string {
    $commitState = 'not_attempted';
    if (!filter_var($GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        return 'restore-policy-changed';
    }
    if (!$store instanceof RestorePolicyStoreDb) {
        return 'restore-policy-store-unavailable';
    }
    $owner = $store->npcById($ownerId);
    if (!is_array($owner) || !sameActorName($owner['npc_name'] ?? null, $expectedOwnerName)) {
        return 'restore-policy-owner-stale';
    }
    if (!(($owner['extended_data'] ?? null) instanceof \stdClass)) {
        return 'restore-policy-owner-invalid';
    }
    if (!restorePolicyOwnerWritable($owner)) {
        return 'restore-policy-owner-locked';
    }
    $plugins = $owner['plugin_extended_data'] ?? null;
    if (!$plugins instanceof \stdClass) {
        return 'restore-policy-ledger-invalid';
    }
    $ledger = storedLedgerNamespace($plugins);
    if ($ledger === null) {
        return 'restore-policy-ledger-invalid';
    }
    if ($ledger === [] || $ledger['playthrough_id'] === $activeScope) {
        return null;
    }
    try {
        if (!$store->acquireRestorePolicyBarrier()) {
            return 'restore-policy-lock-unavailable';
        }
    } catch (Throwable) {
        return 'restore-policy-lock-unavailable';
    }

    $transactionAttempted = false;
    $commitAttempted = false;
    $committed = false;
    $result = null;
    try {
        $bounds = $store->restoreEventBounds();
        $active = $store->activePlaythrough();
        if (!restorePolicyBoundsMatch($bounds, $initEventId)) {
            $result = 'restore-policy-init-changed';
        } elseif (!restorePolicyPlaythroughMatches($active, $activeScope, $expectedPlayerName)) {
            $result = 'restore-policy-scope-changed';
        } else {
            // Set before the call: beginForListener may acquire its advisory lock or
            // BEGIN before a later setup step throws. rollback/release are ownership-aware.
            $transactionAttempted = true;
            if (!$store->beginForListener($ownerId)) {
                $result = 'restore-policy-listener-busy';
            } else {
                $result = restorePolicyWriteScopeBaseline(
                    $store,
                    $ownerId,
                    $expectedOwnerName,
                    $activeScope,
                    $expectedPlayerName,
                    $initEventId,
                    $commitState,
                    $commitAttempted,
                    $committed
                );
            }
        }
    } catch (Throwable) {
        $commitState = $commitAttempted ? 'unconfirmed' : 'not_attempted';
        $result = $commitAttempted ? 'restore-policy-baseline-unconfirmed' : 'restore-policy-baseline-failed';
    } finally {
        $cleanupFailed = false;
        if ($transactionAttempted) {
            if (!$committed) {
                try {
                    $store->rollback();
                } catch (Throwable) {
                    $cleanupFailed = true;
                }
            }
            try {
                $store->release();
            } catch (Throwable) {
                $cleanupFailed = true;
            }
        }
        try {
            $store->releaseRestorePolicyBarrier();
        } catch (Throwable) {
            $result = 'restore-policy-main-release-failed';
        }
        if ($cleanupFailed || ($store instanceof PostgresStoreDb && $store->cleanupFailed())) {
            $result = 'restore-policy-baseline-cleanup-failed';
        }
    }
    return $result;
}

function restorePolicyOwnerWritable(array $owner): bool
{
    $extendedData = $owner['extended_data'] ?? null;
    return $extendedData instanceof \stdClass
        && empty($extendedData->relationships_locked)
        && (int)($owner['lock_profile'] ?? 0) === 0;
}

function restorePolicyBoundsMatch(?array $bounds, int $initEventId): bool
{
    return is_array($bounds)
        && ($bounds['latest_init_event_id'] ?? null) === $initEventId
        && is_int($bounds['max_event_id'] ?? null)
        && $bounds['max_event_id'] >= $initEventId;
}

function restorePolicyPlaythroughMatches(?array $active, string $activeScope, string $expectedPlayerName): bool
{
    return is_array($active)
        && ($active['id'] ?? null) === $activeScope
        && sameActorName($active['player_name'] ?? null, $expectedPlayerName);
}

function restorePolicyWriteScopeBaseline(
    StoreDb $store,
    int $ownerId,
    string $expectedOwnerName,
    string $activeScope,
    string $expectedPlayerName,
    int $initEventId,
    ?string &$commitState,
    bool &$commitAttempted,
    bool &$committed
): ?string {
    $owner = $store->npcById($ownerId, true);
    if (!is_array($owner) || !sameActorName($owner['npc_name'] ?? null, $expectedOwnerName)) {
        return 'restore-policy-owner-stale';
    }
    if (!(($owner['extended_data'] ?? null) instanceof \stdClass)) {
        return 'restore-policy-owner-invalid';
    }
    if (!restorePolicyOwnerWritable($owner)) {
        return 'restore-policy-owner-locked';
    }
    $plugins = $owner['plugin_extended_data'] ?? null;
    $extendedData = $owner['extended_data'] ?? null;
    if (!$plugins instanceof \stdClass || !$extendedData instanceof \stdClass) {
        return 'restore-policy-ledger-invalid';
    }
    $ledger = storedLedgerNamespace($plugins);
    if ($ledger === null) {
        return 'restore-policy-ledger-invalid';
    }
    if ($ledger === [] || $ledger['playthrough_id'] === $activeScope) {
        return null;
    }

    $bounds = $store instanceof RestorePolicyStoreDb ? $store->restoreEventBounds() : null;
    if (!restorePolicyBoundsMatch($bounds, $initEventId)) {
        return 'restore-policy-init-changed';
    }
    if (!restorePolicyPlaythroughMatches($store->activePlaythrough(), $activeScope, $expectedPlayerName)) {
        return 'restore-policy-scope-changed';
    }
    if (!filter_var($GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        return 'restore-policy-changed';
    }
    $gamets = $owner['gamets_last_updated'] ?? null;
    if ((!is_int($gamets) && !is_float($gamets) && !(is_string($gamets) && is_numeric($gamets))) || !is_finite((float)$gamets)) {
        return 'restore-policy-timeline-invalid';
    }

    $nextLedger = baselineLedgerForPlaythroughScope($activeScope, $bounds['max_event_id']);
    $updatedPlugins = clone $plugins;
    $updatedPlugins->mind_poisoning = (object)$nextLedger;
    $expected = [
        'extended_data' => clone $extendedData,
        'plugin_extended_data' => $updatedPlugins,
        'gamets_last_updated' => (float)$gamets,
    ];
    if (!$store->writeNpc($ownerId, [], (object)$nextLedger, (float)$gamets)) {
        return 'restore-policy-baseline-write-failed';
    }
    $written = $store->npcById($ownerId);
    if (
        !is_array($written)
        || !sameJsonValue($written['extended_data'] ?? null, $expected['extended_data'])
        || !sameJsonValue($written['plugin_extended_data'] ?? null, $expected['plugin_extended_data'])
        || (float)($written['gamets_last_updated'] ?? -1) !== $expected['gamets_last_updated']
    ) {
        return 'restore-policy-baseline-verify-failed';
    }
    if (!$store->backupAndVerify($ownerId, $expected)) {
        return 'restore-policy-baseline-history-failed';
    }

    $commitAttempted = true;
    if (!$store->commit()) {
        $commitState = 'unconfirmed';
        return 'restore-policy-baseline-unconfirmed';
    }
    $committed = true;
    $commitState = 'confirmed';
    return 'restore-policy-scope-baselined';
}

function mindPoisoningRestorePolicyEnabled(): bool
{
    return filter_var($GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] ?? false, FILTER_VALIDATE_BOOLEAN);
}

function restorePolicyOutcomeStatus(string $reason): string
{
    return match ($reason) {
        'restore-policy-source-before-init', 'restore-policy-init-fast', 'restore-policy-scope-baselined' => 'restore-policy',
        'restore-policy-owner-locked' => 'locked',
        'restore-policy-owner-stale', 'restore-policy-source-unavailable',
        'restore-policy-init-changed', 'restore-policy-scope-changed' => 'stale',
        'restore-policy-ledger-invalid', 'restore-policy-owner-invalid',
        'restore-policy-owner-state-invalid', 'restore-policy-timeline-invalid' => 'listener-invalid',
        default => 'failed',
    };
}

function restorePolicyOutcomeLevel(string $reason): string
{
    return match ($reason) {
        'restore-policy-ledger-invalid', 'restore-policy-owner-invalid',
        'restore-policy-owner-state-invalid', 'restore-policy-timeline-invalid' => 'warning',
        'restore-policy-source-before-init', 'restore-policy-source-unavailable',
        'restore-policy-init-changed', 'restore-policy-scope-changed',
        'restore-policy-owner-stale', 'restore-policy-owner-locked',
        'restore-policy-scope-baselined' => 'info',
        'restore-policy-init-fast', 'restore-policy-route-unsupported' => 'warning',
        default => 'error',
    };
}

/** Capture the INIT fence for one ACK/Player event or every source in a reflection reply. */
function captureRestorePolicyEventBoundary(
    StoreDb $store,
    array $sourceEventIds,
    array $gameRequest,
    bool $postRequest,
    ?RequestLog $requestLog,
    array &$logFields,
    ?int &$initEventId
): ?string {
    $initEventId = null;
    if (!mindPoisoningRestorePolicyEnabled()) {
        return null;
    }

    $reason = null;
    try {
        $initEventId = captureRestorePolicyBoundary($store, $sourceEventIds, $gameRequest, $postRequest, $reason);
    } catch (Throwable) {
        $reason = 'restore-policy-boundary-unavailable';
    }
    if (is_int($initEventId) && $initEventId > 0) {
        return null;
    }

    $reason ??= 'restore-policy-boundary-unavailable';
    $logFields['stage'] = 'preflight';
    $logFields['reason'] = $reason;
    $logFields['model_outcome'] = 'not_called';
    $requestLog?->event('restore_policy_boundary_checked', restorePolicyOutcomeLevel($reason), [
        'stage' => 'preflight', 'reason' => $reason,
    ]);
    return restorePolicyOutcomeStatus($reason);
}

/** Baseline a mismatched plugin ledger before evaluation, then reload before dedupe/context use. */
function prepareRestorePolicyRecipient(
    array $event,
    array &$listener,
    StoreDb $store,
    ?RequestLog $requestLog,
    array &$logFields
): ?string {
    if (!mindPoisoningRestorePolicyEnabled()) {
        return null;
    }

    $initEventId = $event['restore_policy_init_event_id'] ?? null;
    $expectedOwnerId = $event['opinion_owner_id'] ?? $event['listener_id'] ?? null;
    $storedOwnerId = filter_var($listener['id'] ?? null, FILTER_VALIDATE_INT);
    $ownerName = $listener['npc_name'] ?? null;
    $expectedOwnerName = ($event['source_kind'] ?? null) === 'reflection'
        ? ($event['speaker_name'] ?? null)
        : ($event['listener_name'] ?? null);
    $activeScope = $event['playthrough_id'] ?? null;
    $playerName = $event['player_name'] ?? null;
    if (
        !is_int($initEventId) || $initEventId < 1
        || !is_int($expectedOwnerId) || $expectedOwnerId < 1
        || $storedOwnerId === false || $storedOwnerId < 1
        || !is_string($expectedOwnerName) || trim($expectedOwnerName) === ''
        || !is_string($activeScope) || $activeScope === ''
        || !is_string($playerName) || trim($playerName) === ''
    ) {
        return recordRestorePolicyPreflight($requestLog, $logFields, 'restore-policy-owner-invalid', 'baseline', null, $event);
    }
    if ($storedOwnerId !== $expectedOwnerId || !sameActorName($ownerName, $expectedOwnerName)) {
        return recordRestorePolicyPreflight($requestLog, $logFields, 'restore-policy-owner-stale', 'baseline', null, $event);
    }
    $ownerId = $expectedOwnerId;
    $ownerName = $expectedOwnerName;

    $commitState = 'not_attempted';
    try {
        $reason = baselineRestorePolicyScopeIfNeeded(
            $store,
            $ownerId,
            $ownerName,
            $activeScope,
            $playerName,
            $initEventId,
            $commitState
        );
    } catch (Throwable) {
        $reason = $commitState === 'unconfirmed'
            ? 'restore-policy-baseline-unconfirmed'
            : 'restore-policy-baseline-failed';
    }
    if ($reason !== null) {
        return recordRestorePolicyPreflight($requestLog, $logFields, $reason, 'baseline', $commitState, $event);
    }

    try {
        $fresh = $store->npcById($ownerId);
    } catch (Throwable) {
        return recordRestorePolicyPreflight($requestLog, $logFields, 'restore-policy-baseline-refresh-failed', 'baseline', $commitState, $event);
    }
    if (!is_array($fresh) || !sameActorName($fresh['npc_name'] ?? null, $ownerName)) {
        return recordRestorePolicyPreflight($requestLog, $logFields, 'restore-policy-owner-stale', 'baseline', 'not_attempted', $event);
    }
    $extended = $fresh['extended_data'] ?? null;
    if (!$extended instanceof \stdClass) {
        return recordRestorePolicyPreflight($requestLog, $logFields, 'restore-policy-owner-state-invalid', 'baseline', 'not_attempted', $event);
    }
    if (!empty($extended->relationships_locked) || (int)($fresh['lock_profile'] ?? 0) !== 0) {
        return recordRestorePolicyPreflight($requestLog, $logFields, 'restore-policy-owner-locked', 'baseline', 'not_attempted', $event);
    }
    $listener = $fresh;
    return null;
}

function recordRestorePolicyPreflight(
    ?RequestLog $requestLog,
    array &$logFields,
    string $reason,
    string $stage,
    ?string $commitState = null,
    array $context = []
): string {
    $logFields['stage'] = $stage;
    $logFields['reason'] = $reason;
    $logFields['model_outcome'] = 'not_called';
    if ($commitState !== null) {
        $logFields['commit_state'] = $commitState;
        $logFields['committed'] = $commitState === 'confirmed';
    }
    $fields = ['stage' => $stage, 'reason' => $reason];
    foreach (['event_id', 'utterance_id', 'playthrough_id', 'speaker_id', 'speaker_kind', 'listener_id', 'listener_role', 'addressed_listener_id'] as $key) {
        if (is_int($context[$key] ?? null) || is_string($context[$key] ?? null)) {
            $fields[$key] = $context[$key];
        }
    }
    if ($commitState !== null) {
        $fields['commit_state'] = $commitState;
        $fields['committed'] = $commitState === 'confirmed';
    }
    if ($reason === 'restore-policy-scope-baselined' && $commitState === 'confirmed') {
        $fields['committed'] = true;
    }
    $requestLog?->event('restore_policy_preflight', restorePolicyOutcomeLevel($reason), $fields);
    return restorePolicyOutcomeStatus($reason);
}
