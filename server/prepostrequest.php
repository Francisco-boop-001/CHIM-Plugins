<?php
declare(strict_types=1);

require_once __DIR__ . '/scope.php';

$requestScope = $GLOBALS['PCV_REQUEST_SCOPE'] ?? null;
if (!is_array($requestScope) || !pcvSoloReflectionRequest($requestScope)) {
    return;
}
$speaker = trim((string)($GLOBALS['HERIKA_NAME'] ?? ''));
if (!pcvScopeSpeakerAllowed($speaker, $requestScope['scope'])) {
    return;
}
if (!empty($GLOBALS['PCV_SOLO_RELATIONSHIP_GUARD_SET'])) {
    return;
}

// Core relationship postrequest treats every nonempty listener as an NPC, including
// our native sentinel. Disable only this request's post-generation relationship queue.
$GLOBALS['PCV_SOLO_RELATIONSHIP_GUARD_SET'] = true;
$hadSetting = array_key_exists('RELATIONSHIP_SYSTEM_ENABLED', $GLOBALS);
$previousSetting = $GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] ?? null;
$GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] = false;
register_shutdown_function(static function () use ($hadSetting, $previousSetting): void {
    if ($hadSetting) {
        $GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] = $previousSetting;
    } else {
        unset($GLOBALS['RELATIONSHIP_SYSTEM_ENABLED']);
    }
});
