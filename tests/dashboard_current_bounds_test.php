<?php
declare(strict_types=1);

use function ChimMindPoisoning\dashboardCurrent;

require_once __DIR__ . '/../server/dashboard_data.php';

$identityNames = ['Subject' => 1];
foreach ([-100, 100] as $boundary) {
    $listener = ['extended_data' => (object)['relationships' => (object)[
        'Subject' => (object)['aff' => $boundary],
    ]]];
    $current = dashboardCurrent($listener, 'npc:9', 'Subject', '', $identityNames);
    if ($current !== ['value' => $boundary, 'state' => 'set', 'type' => null, 'type_state' => 'unset']) {
        throw new RuntimeException('Legal affinity boundary was not shown as a set current value.');
    }
}

foreach ([250, -250, '1e309'] as $invalidAffinity) {
    $listener = ['extended_data' => (object)['relationships' => (object)[
        'Subject' => (object)['aff' => $invalidAffinity],
    ]]];
    $current = dashboardCurrent($listener, 'npc:9', 'Subject', '', $identityNames);
    if ($current !== ['value' => null, 'state' => 'invalid', 'type' => null, 'type_state' => 'unset']) {
        throw new RuntimeException('Out-of-domain affinity was rendered as a valid current value.');
    }
}

echo "dashboard current-affinity bounds checks passed\n";
