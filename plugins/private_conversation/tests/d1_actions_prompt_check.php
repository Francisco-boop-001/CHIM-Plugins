<?php
declare(strict_types=1);

define('PCV_LOG_TESTING', true);
$logDirectory = null;
function d1RegisterLogCleanup(string $directory): void
{
    register_shutdown_function(static function () use ($directory): void {
        if (!is_dir($directory) || is_link($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $path = $directory . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path) && !is_link($path)) {
                    @unlink($path);
                }
            }
        }
        @rmdir($directory);
    });
}

require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/scope.php';

function d1Check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pcv_current_player_name(): ?string
{
    return 'Player';
}

/** Match core hook loading: require_once runs from inside a function's local scope. */
function d1RequireHookInFunctionScope(string $path): void
{
    require_once $path;
}

/** Rebuild a core-shaped available-actions section from the globals PCV has narrowed. */
function d1RebuildCoreActionPrompt(): void
{
    $enabled = ($GLOBALS['FUNCTIONS_ARE_ENABLED'] ?? false) === true;
    $codes = $enabled && is_array($GLOBALS['ENABLED_FUNCTIONS'] ?? null)
        ? $GLOBALS['ENABLED_FUNCTIONS']
        : [];
    $GLOBALS['FUNC_LIST'] = $codes === [] ? ['Talk'] : array_merge($codes, ['Talk']);
    $GLOBALS['PROMPT_ACTIONS_LIST'] = '';

    if ($codes !== []) {
        $lines = [];
        $targets = implode(',', $GLOBALS['FUNCTION_PARM_INSPECT'] ?? []);
        foreach ($codes as $code) {
            $line = 'AVAILABLE ACTION: ' . $code . ' (fixture description)';
            if (in_array($code, ['Attack', 'Brawl'], true)) {
                $line .= '(available targets: ' . $targets . ')';
            }
            $lines[] = $line;
        }
        $lines[] = 'AVAILABLE ACTION: Talk (default action, used when no other action is suitable)';
        $GLOBALS['PROMPT_ACTIONS_LIST'] = "\n<available_actions_list>\n"
            . implode("\n", $lines) . "\n</available_actions_list>";
    }

    $GLOBALS['responseTemplate'] = ['listener' => 'base listener description'];
    $GLOBALS['structuredOutputTemplate'] = ['json_schema' => ['schema' => ['properties' => [
        'action' => ['enum' => $GLOBALS['FUNC_LIST']],
        'listener' => ['type' => 'string'],
    ]]]];
    foreach ($GLOBALS['HOOKS']['JSON_TEMPLATE'] ?? [] as $hook) {
        if (is_callable($hook)) {
            $hook();
        }
    }
}

function chimRefreshJsonResponseState(bool $loadExtensionCustomizers = false): void
{
    d1RebuildCoreActionPrompt();
}

function d1FormattedActionSection(string $formattedPrompt): string
{
    if (preg_match('/(?ms)^#{1,6} Available Actions\\s*\\n(.*?)(?=^#{1,6} |\\z)/', $formattedPrompt, $matches) !== 1) {
        return '';
    }
    return $matches[1];
}

try {
    $scenario = $argv[1] ?? 'actions';
    d1Check(in_array($scenario, ['actions', 'talk-only'], true), 'Choose the actions or talk-only fixture scenario.');

    $coreRoot = getenv('PCV_TEST_CHIM_CORE_ROOT');
    if (!is_string($coreRoot) || trim($coreRoot) === '') {
        $coreRoot = dirname(__DIR__, 3) . '/artifacts/chim-drama-encounter-20261005';
    }
    $formatter = rtrim($coreRoot, DIRECTORY_SEPARATOR) . '/lib/prompt_composition.php';
    d1Check(is_file($formatter), 'The pinned CHIM source fixture is required; set PCV_TEST_CHIM_CORE_ROOT to its root.');
    require_once $formatter;
    d1Check(function_exists('chimFormatPromptHeadSection'), 'The pinned CHIM prompt formatter did not load.');

    $logDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv-d1-actions-' . bin2hex(random_bytes(8));
    d1Check(mkdir($logDirectory, 0700), 'Could not create the isolated temporary log directory.');
    d1Check(pcv_log_set_test_directory($logDirectory), 'Could not direct PCV logs to the isolated temporary directory.');

    $actionGroups = $scenario === 'actions' ? ['physical'] : [];
    $scope = pcvResolveScopeNames([
        'enabled' => true,
        'scene_mode' => 'pair',
        'actor_a' => '101',
        'actor_b' => '202',
        'exclude_player' => true,
        'bystander_mode' => 'exclude',
        'actions' => $actionGroups,
    ], ['101' => 'Aela', '202' => 'Bryn'], 'Player');
    d1Check(is_array($scope), 'The synthetic stable-ID pair did not resolve.');

    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    $GLOBALS['gameRequest'] = ['inputtext', '1710000000', 'Aela', 'Player: Meet me by the old bridge.', 'raw'];
    $GLOBALS['HERIKA_NAME'] = 'Aela';
    $GLOBALS['PCV_REQUEST_SCOPE'] = [
        'status' => 'active',
        'scope' => $scope,
        'route' => 'scene_direction',
        'origin_mode' => 'STANDARD',
        'origin_request_type' => 'inputtext',
        'origin_dialogue' => 'Player: Meet me by the old bridge.',
        'config_id' => '123e4567-e89b-42d3-a456-426614174000',
        'speaker_switched' => false,
    ];
    $GLOBALS['requestRoutingSnapshot'] = [
        'audience' => '|Aela|Bryn|Player|Nazeem|',
        'present_actors' => [['name' => 'Nazeem']],
    ];
    $GLOBALS['CACHE_PEOPLE'] = '|Aela|Bryn|Player|Nazeem|';
    $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
    $GLOBALS['ENABLED_FUNCTIONS'] = ['Brawl', 'Surrender', 'Attack'];
    $GLOBALS['FUNCTION_PARM_INSPECT'] = ['Aela', 'Bryn', 'Nazeem', 'Player'];
    $GLOBALS['action_post_process_fnct_ex'] = [];
    $GLOBALS['HOOKS'] = ['JSON_TEMPLATE' => []];

    $fullCoreActionList = "\n<available_actions_list>\n"
        . 'AVAILABLE ACTION: Attack (fixture description)(available targets: Aela,Bryn,Nazeem,Player)' . "\n"
        . 'AVAILABLE ACTION: Brawl (fixture description)(available targets: Aela,Bryn,Nazeem,Player)' . "\n"
        . 'AVAILABLE ACTION: Surrender (fixture description)' . "\n"
        . 'AVAILABLE ACTION: Talk (default action, used when no other action is suitable)' . "\n"
        . '</available_actions_list>';
    $GLOBALS['PROMPT_ACTIONS_LIST'] = $fullCoreActionList;
    // main.php captures this at top level before requireFilesRecursively() includes context_pre.php.
    $actionsList = $GLOBALS['PROMPT_ACTIONS_LIST'];
    $GLOBALS['actionsList'] = $actionsList;

    d1RequireHookInFunctionScope(dirname(__DIR__) . '/server/context_pre.php');
    d1RegisterLogCleanup($logDirectory);

    if ($scenario === 'talk-only') {
        d1Check($GLOBALS['PROMPT_ACTIONS_LIST'] === '' && $GLOBALS['actionsList'] === '' && $actionsList === '',
            'Talk-only scenes must leave the captured and rebuilt available-actions prompt empty.');
        $formattedPrompt = chimFormatPromptHeadSection(
            "<general_instructions>Keep the scene grounded.</general_instructions>\n" . $actionsList,
            true
        );
        d1Check(!str_contains($formattedPrompt, 'Available Actions'), 'Talk-only prompt unexpectedly gained an action heading.');
        echo "PASS D1 talk-only: captured and rebuilt available-actions text remains empty.\n";
        exit(0);
    }

    d1Check($GLOBALS['actionsList'] === $GLOBALS['PROMPT_ACTIONS_LIST'] && $actionsList === $GLOBALS['PROMPT_ACTIONS_LIST'],
        'D1: context_pre refreshed PROMPT_ACTIONS_LIST but left main.php’s captured actionsList stale.');
    d1Check($GLOBALS['ENABLED_FUNCTIONS'] === ['Brawl', 'Surrender']
        && $GLOBALS['FUNC_LIST'] === ['Brawl', 'Surrender', 'Talk']
        && ($GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']['action']['enum'] ?? null)
            === ['Brawl', 'Surrender', 'Talk']
        && ($GLOBALS['action_post_process_fnct_ex'] ?? null) === ['pcvSceneActionPostFilter'],
        'Refreshing the action prompt changed PCV’s schema or post-filter gates.');
    d1Check($GLOBALS['FUNCTION_PARM_INSPECT'] === ['Aela', 'Bryn'], 'Brawl target preparation did not stay scoped to selected NPCs.');

    $formattedPrompt = chimFormatPromptHeadSection(
        "<general_instructions>Keep the scene grounded.</general_instructions>\n"
            . $actionsList
            . "\n<history>Nazeem mentioned Attack last week; Player remembers that conversation.</history>",
        true
    );
    $actionSection = d1FormattedActionSection($formattedPrompt);
    d1Check(str_contains($actionSection, 'Brawl') && str_contains($actionSection, 'Aela,Bryn'),
        'Formatted action section lost the allowed Brawl action or its narrowed targets.');
    d1Check(!str_contains($actionSection, 'Attack') && !str_contains($actionSection, 'Nazeem')
        && !str_contains($actionSection, 'Player'),
        'Formatted action section retained a disallowed action or a bystander/player target.');
    d1Check(str_contains($formattedPrompt, 'Nazeem mentioned Attack last week; Player remembers that conversation.'),
        'Formatting or action narrowing removed intended history/topic text outside the action section.');

    echo "PASS D1 actions: captured available-actions section matches PCV-narrowed actions before core Markdown formatting.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
