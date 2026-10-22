<?php
declare(strict_types=1);

// The current end reason is covered by page_check.php; this keeps the separate no-end-note case.

define('PCV_UI_TEST', true);
define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/index.php';

try {
    $known = ['404' => 'Stalleo', '505' => 'Guard One'];
    if (str_contains(pcv_render_page('tok', ['status' => 'off', 'pending' => false], $known),
        'The scene ended after five minutes of consecutive failed eligibility checks.')) {
        throw new RuntimeException('No note without an automatic end.');
    }
    echo "PASS the page omits an automatic-end note without a stored end reason\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
