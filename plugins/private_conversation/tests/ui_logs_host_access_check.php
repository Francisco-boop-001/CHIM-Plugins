<?php
declare(strict_types=1);

// Live 0.1.9 finding: the player's browser opens CHIM through the WSL VM address
// (http://172.17.226.57:8081/...), so REMOTE_ADDR is the Windows host on the virtual switch and the
// Logs page refused it. Trust exactly that host (the guest's single private default gateway).

define('PCV_UI_TEST', true);
define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/index.php';

function hostCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $route = "Iface\tDestination\tGateway \tFlags\tRefCnt\tUse\tMetric\tMask\t\tMTU\tWindow\tIRTT\n"
        . "eth0\t00000000\t01E011AC\t0003\t0\t0\t0\t00000000\t0\t0\t0\n"
        . "eth0\t00E011AC\t00000000\t0001\t0\t0\t0\t00F0FFFF\t0\t0\t0\n";
    hostCheck(pcv_ui_wsl_host_address($route) === '172.17.224.1', 'The default gateway must decode from little-endian hex.');
    hostCheck(pcv_ui_wsl_host_address('') === null, 'Unreadable routes trust nothing.');
    hostCheck(pcv_ui_wsl_host_address($route . "eth1\t00000000\t0101A8C0\t0003\t0\t0\t0\t00000000\t0\t0\t0\n") === null,
        'Two different default gateways are ambiguous.');
    hostCheck(pcv_ui_wsl_host_address("Iface\tDestination\tGateway\neth0\t00000000\t08080808\t0003\n") === null,
        'A public gateway is never trusted.');

    hostCheck(pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.1'], '172.17.224.1'), 'The WSL host is admitted.');
    hostCheck(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.9'], '172.17.224.1'), 'Another private address is refused.');
    hostCheck(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'], '172.17.224.1'),
        'Forwarded requests are refused even from the host.');
    hostCheck(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.1'], null), 'Without a resolved host, only loopback counts.');
    hostCheck(pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '127.0.0.1'], null), 'Loopback still works.');

    $page = pcv_render_logs_locked_page(['SERVER_PORT' => '8081', 'SCRIPT_NAME' => '/HerikaServer/ext/private_conversation/index.php']);
    hostCheck(str_contains($page, 'href="http://127.0.0.1:8081/HerikaServer/ext/private_conversation/index.php?view=logs"'),
        'The refusal page links to the same page through 127.0.0.1.');
    $hostile = pcv_render_logs_locked_page(['SERVER_PORT' => '80"><script>', 'SCRIPT_NAME' => '/x"><script>alert(1)</script>']);
    hostCheck(!str_contains($hostile, '<script>'), 'Hostile server values never reach the page.');

    // The status panel shows the active scene's last turn from the tiny last-turn record.
    $logDirectory = sys_get_temp_dir() . '/pcv-last-turn-' . bin2hex(random_bytes(6));
    hostCheck(mkdir($logDirectory, 0700) && pcv_log_set_test_directory($logDirectory), 'could not create isolated logger fixture');
    $config = '123e4567-e89b-42d3-a456-426614174000';
    pcv_log_set_config_id($config);
    pcv_log_event('routing.request_finished', 'warning', 'unobserved', 'request_unobserved', ['phase' => 'shutdown', 'request_type' => 'rechat']);
    $html = pcv_render_page('fixture-token', ['status' => 'active', 'pending' => false, 'config_id' => $config,
        'scope' => ['scene_mode' => 'pair', 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude']],
        ['101' => 'Lidia', '202' => 'Bruce']);
    hostCheck(str_contains($html, 'Last scene turn: completion hook not observed')
        && str_contains($html, 'request reason, speech delivery and playback are unknown')
        && str_contains($html, 'Check CHIM/native logs for the actual reason')
        && !str_contains($html, 'ended without speech') && !str_contains($html, 'rechat budget'),
        'An unobserved completion hook leaves request reason, delivery, and playback unknown.');
    pcv_log_event('routing.request_finished', 'info', 'postrequest_observed', null,
        ['phase' => 'postrequest', 'route' => 'player_speech']);
    $observed = pcv_render_page('fixture-token', ['status' => 'active', 'pending' => false, 'config_id' => $config,
        'scope' => ['scene_mode' => 'pair', 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude']],
        ['101' => 'Lidia', '202' => 'Bruce']);
    hostCheck(str_contains($observed, 'Last scene turn: completion hook observed')
        && str_contains($observed, 'speech delivery and playback are unknown')
        && !str_contains($observed, 'Last scene turn: spoke') && !str_contains($observed, 'rechat budget'),
        'An observed completion hook does not claim speech delivery or playback.');
    $other = pcv_render_page('fixture-token', ['status' => 'active', 'pending' => false, 'config_id' => '123e4567-e89b-42d3-a456-4266141740ff',
        'scope' => ['scene_mode' => 'pair', 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude']],
        ['101' => 'Lidia', '202' => 'Bruce']);
    hostCheck(!str_contains($other, 'Last scene turn'), 'Another scene never shows this scene\'s last turn.');
    foreach (glob($logDirectory . '/*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($logDirectory);

    echo "PASS logs access trusts only loopback and the WSL host, and explains refusals; last scene turn shown\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
