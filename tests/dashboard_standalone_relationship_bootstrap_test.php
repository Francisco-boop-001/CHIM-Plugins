<?php
declare(strict_types=1);

use function ChimMindPoisoning\dashboardCurrent;
use function ChimMindPoisoning\dashboardFilters;
use function ChimMindPoisoning\dashboardLoad;

require_once __DIR__ . '/../server/dashboard_data.php';

function dashboardStandaloneAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dashboardStandaloneFixtureRoot(string $helperMode): array
{
    $tempBase = realpath(sys_get_temp_dir());
    $sourceHelper = realpath(__DIR__ . '/fixtures/relationship_manager.cf5030f15781637498be86debe26fcf102f5690d.php');
    if ($tempBase === false || $sourceHelper === false) {
        throw new RuntimeException('The isolated dashboard fixture paths are unavailable.');
    }

    $serverRoot = $tempBase . DIRECTORY_SEPARATOR . 'mp-dashboard-affinity-' . bin2hex(random_bytes(8));
    $libraryDirectory = $serverRoot . DIRECTORY_SEPARATOR . 'lib';
    $helperPath = $libraryDirectory . DIRECTORY_SEPARATOR . 'relationship_manager.php';
    if (!mkdir($libraryDirectory, 0700, true)) {
        dashboardStandaloneRemoveRoot($tempBase, $serverRoot, $libraryDirectory, $helperPath);
        throw new RuntimeException('Could not create the isolated core-helper fixture.');
    }
    if ($helperMode === 'pinned' && !copy($sourceHelper, $helperPath)) {
        dashboardStandaloneRemoveRoot($tempBase, $serverRoot, $libraryDirectory, $helperPath);
        throw new RuntimeException('Could not copy the pinned core-helper fixture.');
    }
    if ($helperMode === 'malformed' && file_put_contents($helperPath, "<?php\nfunction malformed(\n") === false) {
        dashboardStandaloneRemoveRoot($tempBase, $serverRoot, $libraryDirectory, $helperPath);
        throw new RuntimeException('Could not create the malformed core-helper fixture.');
    }

    return [$tempBase, $serverRoot, $libraryDirectory, $helperPath];
}

function dashboardStandaloneRemoveRoot(string $tempBase, string $serverRoot, string $libraryDirectory, string $helperPath): void
{
    $resolvedRoot = realpath($serverRoot);
    if ($resolvedRoot === false) {
        return;
    }
    if (is_link($serverRoot)
        || !str_starts_with($resolvedRoot, $tempBase . DIRECTORY_SEPARATOR)
        || !str_starts_with(basename($resolvedRoot), 'mp-dashboard-affinity-')) {
        throw new RuntimeException('Refusing to remove a path outside this dashboard fixture.');
    }
    if (is_file($helperPath)) {
        unlink($helperPath);
    }
    if (is_dir($libraryDirectory)) {
        rmdir($libraryDirectory);
    }
    rmdir($serverRoot);
}

function dashboardStandaloneUnavailableCase(string $helperMode): void
{
    dashboardStandaloneAssert(!class_exists(\RelationshipManager::class, false), 'The helper-failure case must use a fresh process.');
    dashboardStandaloneAssert(!array_key_exists('ENGINE_PATH', $GLOBALS), 'The helper-failure case must not provide ENGINE_PATH.');
    dashboardStandaloneAssert(!array_key_exists('PLAYER_NAME', $GLOBALS), 'The helper-failure case must not provide PLAYER_NAME.');

    [$tempBase, $serverRoot, $libraryDirectory, $helperPath] = dashboardStandaloneFixtureRoot($helperMode);
    try {
        $model = dashboardLoad($serverRoot, dashboardFilters([]));
        dashboardStandaloneAssert(is_array($model), 'An unavailable core helper stopped dashboard loading.');
        dashboardStandaloneAssert(!class_exists(\RelationshipManager::class, false), 'An unavailable core helper was partially loaded.');

        $player = dashboardCurrent(
            ['extended_data' => (object)['relationships' => (object)[
                'Player' => (object)['aff' => 47],
            ]]],
            'player',
            'Player',
            'Dragonborn',
            []
        );
        dashboardStandaloneAssert(
            $player === ['value' => null, 'state' => 'unavailable', 'type' => null, 'type_state' => 'unavailable'],
            'A missing or malformed core helper was reported as corrupt Player affinity.'
        );

        $npc = dashboardCurrent(
            ['extended_data' => (object)['relationships' => (object)[
                'Subject' => (object)['aff' => 18],
            ]]],
            'npc:9',
            'Subject',
            'Dragonborn',
            ['Subject' => 1]
        );
        dashboardStandaloneAssert(
            $npc === ['value' => 18, 'state' => 'set', 'type' => null, 'type_state' => 'unset'],
            'Core-helper failure affected NPC current affinity.'
        );
        dashboardStandaloneAssert(
            !array_key_exists('ENGINE_PATH', $GLOBALS) && !array_key_exists('PLAYER_NAME', $GLOBALS),
            'Core-helper failure leaked request globals.'
        );
        echo "dashboard_standalone_relationship_bootstrap_test {$helperMode}: ok\n";
    } finally {
        dashboardStandaloneRemoveRoot($tempBase, $serverRoot, $libraryDirectory, $helperPath);
    }
}

if (in_array($argv[1] ?? null, ['--missing-helper', '--malformed-helper'], true)) {
    dashboardStandaloneUnavailableCase(($argv[1] ?? null) === '--missing-helper' ? 'missing' : 'malformed');
    exit(0);
}

dashboardStandaloneAssert(!class_exists(\RelationshipManager::class, false), 'The isolated test process must not preload the core helper.');
dashboardStandaloneAssert(!array_key_exists('ENGINE_PATH', $GLOBALS), 'The isolated test process must not provide ENGINE_PATH.');
dashboardStandaloneAssert(!array_key_exists('PLAYER_NAME', $GLOBALS), 'The isolated test process must not provide PLAYER_NAME.');

[$tempBase, $serverRoot, $libraryDirectory, $helperPath] = dashboardStandaloneFixtureRoot('pinned');

try {
    $model = dashboardLoad($serverRoot, dashboardFilters([]));
    dashboardStandaloneAssert(
        ($model['source']['database'] ?? null) === 'unavailable' && !class_exists('sql', false),
        'The isolated root must stop before loading SQL or connecting to a database.'
    );

    $canonical = dashboardCurrent(
        ['extended_data' => (object)['relationships' => (object)[
            'Player' => (object)['aff' => 47, 'type' => 'romantic'],
        ]]],
        'player',
        'Player',
        'Dragonborn',
        []
    );
    dashboardStandaloneAssert(
        $canonical === ['value' => 47, 'state' => 'set', 'type' => 'romantic', 'type_state' => 'set'],
        'A valid canonical Player edge became Invalid: ' . json_encode($canonical, JSON_THROW_ON_ERROR)
    );

    $duplicateAliases = dashboardCurrent(
        ['extended_data' => (object)['relationships' => (object)[
            'Player' => (object)['aff' => 50, 'type' => 'romantic', 'custom_info' => 'preserve'],
            'Dragonborn' => (object)['aff' => 1, 'type' => 'neutral'],
        ]]],
        'player',
        'Player',
        'Dragonborn',
        []
    );
    dashboardStandaloneAssert(
        $duplicateAliases === ['value' => 50, 'state' => 'set', 'type' => 'romantic', 'type_state' => 'set'],
        'Duplicate Player aliases did not follow the pinned core normalizer.'
    );

    foreach ([
        [(object)['Dragonborn' => [1]], null, 'invalid'],
        [(object)['Player' => (object)['aff' => 'not-a-number']], 'neutral', 'set'],
        [(object)['Player' => (object)['aff' => 250]], 'neutral', 'set'],
    ] as [$invalidRelationships, $type, $typeState]) {
        $invalid = dashboardCurrent(
            ['extended_data' => (object)['relationships' => $invalidRelationships]],
            'player',
            'Player',
            'Dragonborn',
            []
        );
        dashboardStandaloneAssert(
            $invalid === ['value' => null, 'state' => 'invalid', 'type' => $type, 'type_state' => $typeState],
            'Malformed or out-of-range Player affinity was accepted.'
        );
    }

    $npc = dashboardCurrent(
        ['extended_data' => (object)['relationships' => (object)[
            'Subject' => (object)['aff' => 18, 'type' => 'friend'],
        ]]],
        'npc:9',
        'Subject',
        'Dragonborn',
        ['Subject' => 1]
    );
    dashboardStandaloneAssert(
        $npc === ['value' => 18, 'state' => 'set', 'type' => 'friend', 'type_state' => 'set'],
        'NPC current affinity changed.'
    );
    dashboardStandaloneAssert(
        !array_key_exists('ENGINE_PATH', $GLOBALS) && !array_key_exists('PLAYER_NAME', $GLOBALS),
        'Dashboard helper loading or Player normalization leaked request globals.'
    );

    foreach (['--missing-helper', '--malformed-helper'] as $case) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-n', __FILE__, $case],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start fresh process for ' . $case . '.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        dashboardStandaloneAssert(
            $status === 0 && $stdout === 'dashboard_standalone_relationship_bootstrap_test '
                . ($case === '--missing-helper' ? 'missing' : 'malformed') . ": ok\n" && $stderr === '',
            'Fresh-process helper-unavailable case failed: ' . $case . '; exit=' . $status . '; stderr=' . $stderr
        );
    }

    echo "dashboard_standalone_relationship_bootstrap_test: ok\n";
} finally {
    dashboardStandaloneRemoveRoot($tempBase, $serverRoot, $libraryDirectory, $helperPath);
}
