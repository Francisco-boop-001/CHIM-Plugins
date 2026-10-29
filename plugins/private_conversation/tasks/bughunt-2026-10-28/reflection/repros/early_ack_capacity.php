<?php
declare(strict_types=1);

// Exercise the production receipt store with the supported 13-line reply case.
// State and locking are isolated stubs; no CHIM, database, HTTP, or MP calls occur.
$repo = 'K:/ActorwrightExchange/projects/CHIM-PrivateConversation-dev';
$key = str_repeat('a', 64);
$configId = '123e4567-e89b-42d3-a456-426614174000';
$actorId = '11';
$GLOBALS['fixture_generation'] = 17;

function pcv_valid_key(mixed $value): bool
{
    return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
}

function pcv_log_valid_uuid(string $value): bool
{
    return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/iD', $value) === 1;
}

function pcv_state_directory(?string $directory = null): string
{
    if (!is_string($directory) || !is_dir($directory)) {
        throw new RuntimeException('Fixture state directory is unavailable.');
    }
    return $directory;
}

function pcv_lock_state(string $directory, bool $create, int $mode): object
{
    return new stdClass();
}

function pcv_unlock_state(mixed $handle): void {}

function pcv_load_store(string $directory): array
{
    return ['kind' => 'ready', 'state' => [
        'key' => str_repeat('a', 64),
        'active' => [
            'config' => [
                'enabled' => true,
                'scene_mode' => 'solo',
                'actor_a' => '11',
                'actor_b' => null,
                'exclude_player' => true,
            ],
            'config_id' => '123e4567-e89b-42d3-a456-426614174000',
            'expires_at' => time() + 600,
        ],
        'pending' => null,
    ]];
}

function pcv_reflection_active_solo_from_state(array $state): ?array
{
    $active = $state['active'] ?? null;
    $config = is_array($active) ? ($active['config'] ?? null) : null;
    if (!is_array($active) || !is_array($config)) {
        return null;
    }
    return [
        'pcv_key' => $state['key'],
        'config_id' => $active['config_id'],
        'actor_id' => $config['actor_a'],
    ];
}

function pcv_reflection_interaction_epochs_match(int $sourceGeneration, int $ackGeneration): bool
{
    return $sourceGeneration === $ackGeneration && $ackGeneration === $GLOBALS['fixture_generation'];
}

function pcv_scope_name_key(string $name): string
{
    return strtolower(trim($name));
}

require_once $repo . '/server/reflection_receipt.php';

$directory = __DIR__ . '/scratch-' . bin2hex(random_bytes(6));
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('Could not create the isolated receipt directory.');
}

try {
    $scope = [
        'pcv_key' => $key,
        'config_id' => $configId,
        'actor_a_id' => $actorId,
        'scope' => ['actor_a' => 'Aela'],
    ];
    $kinds = [];
    for ($line = 1; $line <= 13; $line++) {
        $tuple = [
            'speaker' => 'Aela',
            'listener' => 'Dragonborn',
            'speech' => "Reflection line $line.",
            'utterance_id' => sprintf('utt_streamline%04d', $line),
        ];
        $stored = pcv_reflection_store_ack_receipt($tuple, $scope, 17, $directory);
        $kinds[] = $stored['kind'] ?? 'missing';
    }

    $ledger = pcv_reflection_read_receipts_locked($directory);
    $count = count($ledger['receipts'] ?? []);
    if (array_slice($kinds, 0, 8) !== array_fill(0, 8, 'ready')
        || ($kinds[8] ?? null) !== 'busy' || ($kinds[12] ?? null) !== 'busy'
        || $count !== PCV_REFLECTION_RECEIPT_MAX_COUNT) {
        throw new RuntimeException('The receipt capacity behavior differed from the expected bounded result.');
    }

    echo 'supported_reply_lines=13 receipt_limit=' . PCV_REFLECTION_RECEIPT_MAX_COUNT . "\n";
    echo 'first_eight=ready ack_09=' . $kinds[8] . ' final_ack_13=' . $kinds[12] . "\n";
    echo 'stored_receipts=' . $count . "\n";
} finally {
    $receiptPath = $directory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE;
    if (is_file($receiptPath) && !is_link($receiptPath)) {
        @unlink($receiptPath);
    }
    @rmdir($directory);
}
