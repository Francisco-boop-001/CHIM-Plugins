<?php
declare(strict_types=1);

define('PCV_UI_TEST', true);
require_once __DIR__ . '/../server/index.php';

echo pcv_render_page(
    'fixture-preview-token',
    [
        'status' => 'active',
        'scope' => [
            'enabled' => true,
            'actor_a' => '101',
            'actor_b' => '202',
            'exclude_player' => true,
            'bystander_mode' => 'exclude',
        ],
        'pending' => true,
        'pending_scope' => [
            'enabled' => true,
            'actor_a' => '303',
            'actor_b' => '404',
            'exclude_player' => false,
            'bystander_mode' => 'silent',
        ],
    ],
    [
        '101' => 'Aela',
        '202' => 'Lydia',
        '303' => 'Mjoll',
        '404' => 'Brynjolf',
    ],
    'Fixture preview: no CHIM bootstrap or live state was used.'
);
