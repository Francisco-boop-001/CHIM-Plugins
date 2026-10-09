<?php
declare(strict_types=1);

// 0.1.16 page: three scene-action checkboxes (off by default), Physical unavailable for solo, kept while armed.

define('PCV_UI_TEST', true);
define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/index.php';

function suiCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $known = ['404' => 'Stalleo', '505' => 'Guard One', '606' => 'Guard Two'];
    $base = ['csrf' => 'tok', 'action' => 'arm', 'bystander_mode' => 'exclude', 'exclude_player' => '1'];
    $pairPost = $base + ['actor_a' => '404', 'actor_b' => '505', 'opener' => 'auto'];

    $pair = pcv_form_desired_state($pairPost + ['actions' => ['personal', 'physical', 'intimate']], 'tok', $known);
    suiCheck(($pair['actions'] ?? null) === ['personal', 'physical', 'intimate'], 'The pair form passes the ticked groups.');
    $none = pcv_form_desired_state($pairPost, 'tok', $known);
    suiCheck(!array_key_exists('actions', $none), 'No ticks: no actions (talk only).');
    $solo = pcv_form_desired_state($base + ['scene_mode' => 'solo', 'actor_a' => '404', 'actions' => ['personal', 'physical']], 'tok', $known);
    suiCheck(($solo['actions'] ?? null) === ['personal'], 'Solo drops Physical.');
    foreach ([['lethal'], 'personal', [['personal']]] as $bad) {
        $rejected = false;
        try {
            pcv_form_desired_state($pairPost + ['actions' => $bad], 'tok', $known);
        } catch (PcvUiFormRejection) {
            $rejected = true;
        }
        suiCheck($rejected, 'Unknown or malformed action groups are rejected.');
    }

    $html = pcv_render_page('tok', ['status' => 'off', 'pending' => false], $known);
    foreach (['personal', 'physical', 'intimate'] as $group) {
        suiCheck((bool)preg_match('/<input type="checkbox" id="actions-' . $group . '" name="actions\[\]" value="' . $group . '"(?![^>]*checked)[^>]*>/', $html),
            'The page offers the ' . $group . ' group, unticked by default.');
    }

    $active = ['status' => 'active', 'pending' => false, 'config_id' => null,
        'scope' => ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '404', 'actor_b' => '505', 'exclude_player' => true,
            'bystander_mode' => 'exclude', 'actions' => ['personal', 'intimate']]];
    $activeHtml = pcv_render_page('tok', $active, $known);
    suiCheck((bool)preg_match('/id="actions-personal"[^>]*checked/', $activeHtml)
        && (bool)preg_match('/id="actions-intimate"[^>]*checked/', $activeHtml)
        && !preg_match('/id="actions-physical"[^>]*checked/', $activeHtml), 'The form keeps the current groups.');
    suiCheck(str_contains($activeHtml, 'Scene actions: personal, intimate'), 'The status shows the scene actions.');

    $soloActive = ['status' => 'active', 'pending' => false, 'config_id' => null,
        'scope' => ['enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '404', 'actor_b' => null, 'exclude_player' => true,
            'bystander_mode' => 'exclude', 'actions' => ['personal']]];
    suiCheck((bool)preg_match('/id="actions-physical"[^>]*disabled/', pcv_render_page('tok', $soloActive, $known)), 'Physical is disabled for solo.');

    echo "PASS the page offers opt-in scene actions, Physical not for solo, and the form passes them through\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
