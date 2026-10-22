<?php
declare(strict_types=1);

// A private route address is not caller identity. Keep logs local or use server-authenticated REMOTE_USER.

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
    foreach (['172.17.224.1', '172.17.0.1'] as $gatewayLikeAddress) {
        hostCheck(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => $gatewayLikeAddress]),
            'A private default-gateway-like address alone must not authorize diagnostics.');
    }
    // Simulate the formerly trusted /proc/net/route result passed into the production gate.
    hostCheck(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.1'], '172.17.224.1', true),
        'A formerly resolved private gateway must not authorize diagnostics.');
    hostCheck(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']),
        'Forwarded requests are refused even when their source resembles a private gateway.');
    hostCheck(!pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.1']), 'Private addresses do not authorize diagnostics.');
    hostCheck(pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '127.0.0.1']), 'Loopback still works.');
    hostCheck(pcv_ui_logs_access_allowed(['REMOTE_ADDR' => '172.17.224.1', 'REMOTE_USER' => 'operator']),
        'A server-authenticated user still works from a non-loopback source.');

    $page = pcv_render_logs_locked_page(['SERVER_PORT' => '8081', 'SCRIPT_NAME' => '/HerikaServer/ext/private_conversation/index.php']);
    hostCheck(str_contains($page, 'href="http://127.0.0.1:8081/HerikaServer/ext/private_conversation/index.php?view=logs"'),
        'The refusal page links to the same page through 127.0.0.1.');
    hostCheck(!str_contains($page, 'Windows PC hosting this WSL server')
        && str_contains($page, 'server that sets a trusted REMOTE_USER'),
        'The refusal page does not promise automatic Windows-host access and names authenticated-server access.');
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

    echo "PASS logs access trusts only loopback or server authentication, and explains refusals; last scene turn shown\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
