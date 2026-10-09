<?php
declare(strict_types=1);

// 0.1.16 scene actions in context_pre: before CHIM rebuilds its action list, PCV narrows ENABLED_FUNCTIONS to the
// scene's allowed codes (never re-enabling what CHIM or SHARMAT gated off), narrows Brawl's target list to members,
// and registers the post-filter. After the rebuild, only allowed actions (or Talk) may remain.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function shCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $known = ['11' => 'Lidia', '12' => 'Bruce'];
    $pair = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '11', 'actor_b' => '12', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $resolved = pcvResolveScopeNames($pair + ['actions' => ['personal', 'physical', 'intimate']], $known, 'Hawke');

    // SHARMAT left Kiss enabled but gated StartSex off; CHIM has Attack enabled.
    $GLOBALS['ENABLED_FUNCTIONS'] = ['Drink', 'Brawl', 'Attack', 'KillTarget', 'ExtCmdKiss', 'GiveItemTo'];
    $GLOBALS['FUNCTION_PARM_INSPECT'] = ['Lidia', 'Bruce', 'Aelva', 'Hawke'];
    $GLOBALS['action_post_process_fnct_ex'] = ['someOtherPluginFilter'];
    $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
    $enabled = pcvPrepareSceneActions($resolved);
    shCheck($enabled === true && $GLOBALS['FUNCTIONS_ARE_ENABLED'] === true, 'Actions are switched on for a scene with groups.');
    shCheck($GLOBALS['ENABLED_FUNCTIONS'] === ['Drink', 'Brawl', 'ExtCmdKiss'], 'Only allowed and already-enabled codes remain.');
    shCheck(!in_array('ExtCmdStartSex', $GLOBALS['ENABLED_FUNCTIONS'], true), 'SHARMAT-gated actions are never re-enabled.');
    shCheck($GLOBALS['FUNCTION_PARM_INSPECT'] === ['Lidia', 'Bruce'], 'Brawl targets are narrowed to the members.');
    shCheck($GLOBALS['action_post_process_fnct_ex'] === ['someOtherPluginFilter', 'pcvSceneActionPostFilter'], 'The post-filter is registered after others.');
    pcvPrepareSceneActions($resolved);
    shCheck(count(array_keys($GLOBALS['action_post_process_fnct_ex'], 'pcvSceneActionPostFilter', true)) === 1, 'The post-filter is registered once.');

    // After CHIM's rebuild: only allowed actions or Talk.
    shCheck(pcvSceneActionListAllowed(['Talk', 'Drink', 'Brawl', 'ExtCmdKiss'], $resolved), 'Allowed actions pass the check.');
    shCheck(!pcvSceneActionListAllowed(['Talk', 'Attack'], $resolved), 'A stray Attack fails the check.');
    shCheck(pcvSceneActionListAllowed([], $resolved) && pcvSceneActionListAllowed(['Talk'], $resolved), 'Talk-only still passes.');

    // CHIM actions switched off by the user: a scene never switches them on.
    $GLOBALS['ENABLED_FUNCTIONS'] = ['Drink', 'Brawl'];
    $GLOBALS['FUNCTIONS_ARE_ENABLED'] = false;
    shCheck(pcvPrepareSceneActions($resolved) === false && $GLOBALS['FUNCTIONS_ARE_ENABLED'] === false, 'A global actions-off setting is respected.');

    // No groups: talk only, unchanged behaviour.
    $GLOBALS['ENABLED_FUNCTIONS'] = ['Drink', 'Attack'];
    $GLOBALS['FUNCTIONS_ARE_ENABLED'] = true;
    $plain = pcvResolveScopeNames($pair, $known, 'Hawke');
    shCheck(pcvPrepareSceneActions($plain) === false && $GLOBALS['FUNCTIONS_ARE_ENABLED'] === false, 'Without groups actions stay off.');
    shCheck(!pcvSceneActionListAllowed(['Drink'], $plain) && pcvSceneActionListAllowed(['Talk'], $plain), 'Without groups only Talk passes.');

    // Guidance line.
    $guidance = pcvScopeRoleplayGuidance($resolved);
    shCheck(str_contains($guidance, 'You may act, not only talk') && str_contains($guidance, 'only toward the people in this scene'),
        'The scene is told it may act, toward members only.');
    $soloResolved = pcvResolveScopeNames(['enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '11', 'actor_b' => null,
        'exclude_player' => true, 'bystander_mode' => 'exclude', 'actions' => ['personal']], $known, 'Hawke');
    shCheck(str_contains(pcvScopeRoleplayGuidance($soloResolved), 'on your own'), 'Solo is told to act on its own.');

    echo "PASS scene actions hook: narrows enabled actions, never re-enables gated ones, members-only Brawl targets, post-filter registered\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
