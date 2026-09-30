<?php
declare(strict_types=1);

function pcv_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param list<array<string, mixed>> $rows @return array<string, string> */
function pcv_build_known_npcs(array $rows, ?string $playerName): array
{
    require_once __DIR__ . '/scope.php';
    $known = pcvScopeKnownNpcs($rows, $playerName);
    uksort($known, static fn(string $left, string $right): int => (int)$left <=> (int)$right);
    return $known;
}

final class PcvUiFormRejection extends InvalidArgumentException
{
    public string $reasonCode;

    public function __construct(string $reasonCode, string $message)
    {
        $this->reasonCode = $reasonCode;
        parent::__construct($message);
    }
}

/** @return array<string, mixed> */
function pcv_form_desired_state(array $post, string $csrfToken, array $knownNpcs): array
{
    $submittedToken = $post['csrf'] ?? null;
    if (!is_string($submittedToken) || $csrfToken === '' || !hash_equals($csrfToken, $submittedToken)) {
        throw new PcvUiFormRejection('csrf_failed', 'The form token is invalid.');
    }

    $action = $post['action'] ?? null;
    if ($action === 'end') {
        return ['enabled' => false];
    }
    if ($action !== 'arm') {
        $reason = $action === null ? 'missing_settings' : 'invalid_configuration';
        throw new PcvUiFormRejection($reason, 'The requested action is invalid.');
    }

    $sceneMode = array_key_exists('scene_mode', $post) ? $post['scene_mode'] : 'pair';
    if (!is_string($sceneMode) || !in_array($sceneMode, ['pair', 'solo'], true)) {
        throw new PcvUiFormRejection('invalid_configuration', 'The selected scene mode is invalid.');
    }

    if ($sceneMode === 'solo') {
        $actorA = $post['actor_a'] ?? null;
        if ($actorA === null || $actorA === '') {
            throw new PcvUiFormRejection('missing_settings', 'Choose an NPC.');
        }
        if (!is_string($actorA)) {
            throw new PcvUiFormRejection('invalid_configuration', 'The selected NPC is invalid.');
        }
        if (!array_key_exists($actorA, $knownNpcs)) {
            throw new PcvUiFormRejection('actor_unavailable', 'The selected NPC is no longer eligible.');
        }
        $bystanderMode = $post['bystander_mode'] ?? null;
        if (!is_string($bystanderMode) || !in_array($bystanderMode, ['exclude', 'silent'], true)) {
            throw new PcvUiFormRejection('invalid_configuration', 'The other NPCs option is invalid.');
        }
        return [
            'enabled' => true,
            'scene_mode' => 'solo',
            'actor_a' => $actorA,
            'actor_b' => null,
            'exclude_player' => true,
            'bystander_mode' => $bystanderMode,
        ];
    }

    $actorA = $post['actor_a'] ?? null;
    $actorB = $post['actor_b'] ?? null;
    $bystanderMode = $post['bystander_mode'] ?? null;
    $excludePlayer = $post['exclude_player'] ?? null;
    if ($excludePlayer !== null && $excludePlayer !== '1') {
        throw new PcvUiFormRejection('invalid_configuration', 'The player option is invalid.');
    }
    if ($actorA === null || $actorB === null || $bystanderMode === null) {
        throw new PcvUiFormRejection('missing_settings', 'The selected scope is invalid.');
    }
    if (!is_string($actorA) || !is_string($actorB) || !is_string($bystanderMode)
        || !in_array($bystanderMode, ['exclude', 'silent'], true) || $actorA === $actorB) {
        throw new PcvUiFormRejection('invalid_configuration', 'The selected scope is invalid.');
    }
    if (!array_key_exists($actorA, $knownNpcs) || !array_key_exists($actorB, $knownNpcs)) {
        throw new PcvUiFormRejection('actor_unavailable', 'The selected scope is invalid.');
    }

    return [
        'enabled' => true,
        'scene_mode' => 'pair',
        'actor_a' => $actorA,
        'actor_b' => $actorB,
        'exclude_player' => $excludePlayer === '1',
        'bystander_mode' => $bystanderMode,
    ];
}

function pcv_ui_log_stage_accepted(array $state, string $action): void
{
    $status = $state['status'] ?? 'unavailable';
    if (!in_array($status, ['active', 'pending', 'off', 'unavailable'], true)) {
        $status = 'unavailable';
    }
    $pending = ($state['pending'] ?? false) === true;
    pcv_log_set_config_id($state['pending_config_id'] ?? $state['config_id'] ?? null);
    pcv_log_event('ui.scope_stage_accepted', 'info', 'ok', null, [
        'action' => $action,
        'status' => $status,
        'pending' => $pending,
    ]);
}

function pcv_ui_failure_notice(string $message): string
{
    return $message . ' Reference ID: ' . pcv_log_request_id() . '.';
}

function pcv_form_can_stage(array $desired, ?string $playthroughKey, bool $catalogAvailable): bool
{
    return is_string($playthroughKey) && $playthroughKey !== ''
        && (($desired['enabled'] ?? false) !== true || $catalogAvailable);
}

function pcv_picker_rejection_reason(string $status, ?string $reason): string
{
    return match ($status) {
        'missing' => 'presence_missing',
        'stale' => 'presence_stale',
        'unavailable' => in_array($reason, ['presence_invalid', 'presence_key_mismatch'], true)
            ? $reason : 'presence_unavailable',
        default => 'actor_unavailable',
    };
}

function pcv_picker_rejection_notice(string $status, int $eligibleCount): string
{
    return match ($status) {
        'empty' => 'No eligible NPCs were present in the latest background observation. The list polls automatically for updates.',
        'missing' => 'No qualifying CHIM presence report is available yet; current eligibility is unknown, not empty. The list polls automatically for updates.',
        'stale' => 'Fresh nearby and AI activity could not be confirmed; current eligibility is unknown. The list polls automatically for updates.',
        'unavailable' => 'Eligibility could not be verified; current eligibility is unknown, not empty. The list polls automatically for updates.',
        default => $eligibleCount < 2
            ? 'Pair mode requires two distinct nearby CHIM-AI-active characters. Solo reflection needs one. The list polls automatically for updates.'
            : 'The selected NPCs are no longer both nearby and CHIM-AI-active. Choose two current eligible NPCs from the automatically updated list.',
    };
}

/** @param array<string, mixed> $state @param array<string, string> $knownNpcs */
function pcv_render_page(
    string $csrfToken,
    array $state,
    array $knownNpcs,
    string $notice = '',
    string $eligibilityStatus = 'ready',
    bool $catalogAvailable = true,
    ?array $displayNpcs = null,
    string $playthroughRef = ''
): string
{
    if (!in_array($eligibilityStatus, ['ready', 'empty', 'missing', 'stale', 'unavailable'], true)) {
        $eligibilityStatus = 'unavailable';
    }
    $displayNpcs ??= $knownNpcs;
    $eligibleCount = count($knownNpcs);
    if (!$catalogAvailable) {
        $pickerNote = 'The current NPC catalog could not be read. ARM is unavailable; END remains available.';
    } else {
        $pickerNote = match ($eligibilityStatus) {
            'ready' => $eligibleCount >= 1
                ? 'Pair mode requires two distinct nearby CHIM-AI-active characters. Solo reflection needs one nearby CHIM-AI-active character. Choices use CHIM background nearby and recent AI activity observations. This list polls automatically for updates.'
                : 'No eligible NPC is available yet. The list polls automatically for updates.',
            'empty' => 'No eligible NPCs matched the latest report. This is a reported empty result. This list polls automatically for updates.',
            'missing' => 'No qualifying CHIM presence report is available yet; current eligibility is unknown, not empty. This list polls automatically for updates.',
            'stale' => 'Fresh nearby and AI activity could not be confirmed; current eligibility is unknown. This list polls automatically for updates.',
            default => 'The latest CHIM presence report could not be verified; current eligibility is unknown, not empty. This list polls automatically for updates.',
        };
    }
    $status = $state['status'] ?? 'unavailable';
    if (!in_array($status, ['active', 'pending', 'off', 'unavailable'], true)) {
        $status = 'unavailable';
    }
    $pending = ($state['pending'] ?? false) === true;
    $scope = is_array($state['scope'] ?? null) ? $state['scope'] : [];
    $pendingScope = is_array($state['pending_scope'] ?? null) ? $state['pending_scope'] : null;
    $pendingEnd = $pending && $pendingScope !== null && ($pendingScope['enabled'] ?? null) === false;
    $formScope = $pending && $pendingScope !== null && ($pendingScope['enabled'] ?? null) === true
        ? $pendingScope : $scope;
    $sceneMode = ($formScope['scene_mode'] ?? null) === 'solo' ? 'solo' : 'pair';
    $scopeSceneMode = ($scope['scene_mode'] ?? null) === 'solo' ? 'solo' : 'pair';
    $pendingSceneMode = ($pendingScope['scene_mode'] ?? null) === 'solo' ? 'solo' : 'pair';
    $excludePlayer = is_bool($formScope['exclude_player'] ?? null) ? $formScope['exclude_player'] : true;
    $badgeText = match ($status) {
        'active' => $pending ? ($pendingEnd ? 'End queued' : 'Change queued') : 'Active',
        'pending' => $pendingEnd ? 'End queued' : 'Armed',
        'off' => 'Off',
        default => 'Unavailable',
    };
    $badgeState = $pending ? 'pending' : $status;
    $statusText = match ($status) {
        'active' => !$pending
            ? ($scopeSceneMode === 'solo'
                ? 'The selected NPC reflection is active for eligible ordinary in-game input.'
                : 'The selected pair is active for eligible ordinary in-game input.')
            : ($pendingEnd
                ? 'The current scene remains active until the next eligible ordinary in-game input, when it will end.'
                : 'The current scene remains active until the next eligible ordinary in-game input. The queued selection takes effect only if its NPCs remain nearby and CHIM-AI-active.'),
        'pending' => $pendingEnd
            ? 'An end change is queued for the next eligible ordinary in-game input; no scene is active now.'
            : ($pendingSceneMode === 'solo'
                ? 'The reflection is armed. It will become active on the next eligible ordinary in-game input only if the selected NPC is still nearby and CHIM-AI-active.'
                : 'The pair is armed. It will become active on the next eligible ordinary in-game input only if both selected NPCs are still nearby and CHIM-AI-active.'),
        'off' => 'Private conversation is off.',
        default => 'Settings are unavailable until CHIM has a current playthrough and NPC list.',
    };
    $scopeSummary = '';
    $sceneText = static function (array $config) use ($displayNpcs): string {
        $actorA = is_string($config['actor_a'] ?? null) || is_int($config['actor_a'] ?? null)
            ? (string)$config['actor_a'] : '';
        if ($actorA === '') {
            return '';
        }
        $nameA = $displayNpcs[$actorA] ?? ('NPC ID ' . $actorA);
        if (($config['scene_mode'] ?? null) === 'solo') {
            return pcv_html($nameA);
        }
        $actorB = is_string($config['actor_b'] ?? null) || is_int($config['actor_b'] ?? null)
            ? (string)$config['actor_b'] : '';
        if ($actorB === '') {
            return '';
        }
        $nameB = $displayNpcs[$actorB] ?? ('NPC ID ' . $actorB);
        return pcv_html($nameA) . ' and ' . pcv_html($nameB);
    };
    if ($status === 'active' && ($scene = $sceneText($scope)) !== '') {
        $scopeSummary .= '<p>' . ($scopeSceneMode === 'solo' ? 'Current reflection: ' : 'Current pair: ') . $scene . '.</p>';
    }
    if ($pending) {
        if ($pendingEnd) {
            $scopeSummary .= '<p class="pending-summary">End is queued for the next eligible ordinary input.</p>';
        } elseif ($pendingScope !== null && ($scene = $sceneText($pendingScope)) !== '') {
            $scopeSummary .= '<p class="pending-summary">Next ' . ($pendingSceneMode === 'solo' ? 'reflection: ' : 'pair: ') . $scene . '.</p>';
        } else {
            $scopeSummary .= '<p class="pending-summary">A change is queued for the next eligible ordinary input.</p>';
        }
    }

    $mode = in_array($formScope['bystander_mode'] ?? null, ['exclude', 'silent'], true)
        ? $formScope['bystander_mode'] : 'exclude';
    $actorA = is_string($formScope['actor_a'] ?? null) || is_int($formScope['actor_a'] ?? null)
        ? (string)$formScope['actor_a'] : '';
    $actorB = is_string($formScope['actor_b'] ?? null) || is_int($formScope['actor_b'] ?? null)
        ? (string)$formScope['actor_b'] : '';
    $playerSummary = $sceneMode === 'solo'
        ? 'Solo reflection always excludes the player.'
        : ($status === 'active' && is_bool($scope['exclude_player'] ?? null)
        ? 'The player is ' . ($scope['exclude_player'] ? 'excluded' : 'included') . ' in the current pair.'
        : 'Player exclusion is on by default for a new pair.');
    if ($sceneMode !== 'solo' && $pending && is_bool($pendingScope['exclude_player'] ?? null)) {
        $playerSummary .= ' The queued setting will ' . ($pendingScope['exclude_player'] ? 'exclude' : 'include') . ' the player.';
    }
    $optionsFor = static function (string $selectedId) use ($knownNpcs): string {
        $options = '<option value="">Choose an NPC</option>';
        foreach ($knownNpcs as $id => $name) {
            $id = (string)$id;
            $label = $name . ' (ID ' . $id . ')';
            $selected = $id === $selectedId ? ' selected' : '';
            $options .= '<option value="' . pcv_html($id) . '"' . $selected . '>' . pcv_html($label) . "</option>\n";
        }
        return $options;
    };
    $rosterReady = $catalogAvailable && $eligibilityStatus === 'ready' && $eligibleCount >= 1;
    $actorDisabled = !$rosterReady ? ' disabled' : '';
    $pairDisabled = !$rosterReady || $eligibleCount < 2;
    $actorBDisabled = $pairDisabled || $sceneMode === 'solo';
    $soloChecked = $sceneMode === 'solo' ? ' checked' : '';
    $soloDisabled = !$rosterReady ? ' disabled' : '';
    $actorADisabledAttr = $actorDisabled;
    $actorBDisabledAttr = $actorBDisabled ? ' disabled' : '';
    $armDisabledAttr = ($sceneMode === 'solo' ? !$rosterReady : $pairDisabled) ? ' disabled' : '';
    $excludePlayerDisabledAttr = $sceneMode === 'solo' ? ' disabled' : '';
    $optionsA = $optionsFor($actorA);
    $optionsB = str_replace('Choose an NPC', 'Choose a different NPC', $optionsFor($actorB));
    $noticeHtml = $notice === '' ? '' : '<p role="status">' . pcv_html($notice) . '</p>';
    $csrf = pcv_html($csrfToken);

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CHIM Private Conversation — Part of the World of Drama-llama</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="page" data-playthrough-ref="' . pcv_html($playthroughRef) . '" data-refresh-url="?refresh=1">
<div class="page-frame">
<header class="topbar">
<p class="wordmark"><span class="wordmark-mark" aria-hidden="true">✦</span> CHIM <span class="wordmark-divider">/</span> SCENE NOTES</p>
<span class="status-badge status-' . pcv_html($badgeState) . '" role="status">' . pcv_html($badgeText) . '</span>
</header>
<main>
<section class="hero" aria-labelledby="page-title">
<figure class="hero-art"><img src="assets/private-conversation-scene.png" alt="Two travelers speaking quietly at a table beside an inn window, with other patrons and an emo llama illustrated on a hanging banner in the background."></figure>
<div class="hero-copy">
<p class="eyebrow">A quieter kind of scene</p>
<h1 id="page-title">Private<br><span>Conversation</span></h1>
<p class="eyebrow hero-credit">Part of the World of Drama-llama</p>
<p id="page-intro" class="hero-intro">Choose two voices for a conversation, or one NPC to think aloud. CHIM carries that scene direction into the next eligible ordinary Standard-mode input.</p>
<p class="hero-meta"><span>STANDARD MODE</span><span>SCENE DIRECTION ONLY</span></p>
</div>
</section>
<div id="page-notice-container">' . $noticeHtml . '</div>
<div class="content-grid">
<section class="status-panel" aria-labelledby="status-heading">
<h2 id="status-heading">Status</h2>
<p>' . pcv_html($statusText) . '</p>
' . $scopeSummary . '
<p class="small-note">' . pcv_html($playerSummary) . ' Other NPCs can be excluded or present but silent.</p>
<p class="small-note">An existing exchange may finish before the boundary. Use the in-game Stop All Dialogue control to stop it immediately.</p>
<p class="small-note">Scene direction only: it does not supply exact dialogue or NPC-authored lines. Standard text/STT input is supported; Close and Whisper are stopped while a scene is active, and Director mode is outside scope.</p>
<p class="small-note">This is not a privacy barrier; vanilla greetings can still occur, and a model may not follow the scene direction.</p>
<p class="small-note">Only current nearby/audience context is scoped; existing history, memories, and profiles may still mention the player or bystanders. Silent mode adds generic scenery guidance only.</p>
</section>
<div class="controls-panel">
<section class="form-panel" aria-labelledby="arm-heading">
<h2 id="arm-heading">' . ($sceneMode === 'solo' ? 'Solo reflection' : 'Choose the pair') . '</h2>
<p id="picker-status" class="small-note" role="status">' . pcv_html($pickerNote) . '</p>
<p class="small-note">Uses CHIM’s broader nearby range. AI observations expire after 45 seconds. NPCs sharing the same name cannot be distinguished.</p>
<form method="post" class="scope-form">
<input type="hidden" name="csrf" value="' . $csrf . '">
<input type="hidden" name="action" value="arm">
<p class="checkbox-field"><label><input type="checkbox" id="solo-mode" name="scene_mode" value="solo"' . $soloChecked . $soloDisabled . '> <span>Solo reflection</span></label></p>
<p id="mode-guidance" class="small-note"' . ($sceneMode === 'solo' ? '' : ' hidden') . '>Solo reflection asks the NPC to think aloud. Opinion changes require compatible Mind Poisoning support.</p>
<p class="field"><label id="actor-a-label" for="actor-a">' . ($sceneMode === 'solo' ? 'Reflecting NPC' : 'NPC A') . '</label><select id="actor-a" name="actor_a" required' . $actorADisabledAttr . '>
' . $optionsA . '</select></p>
<p class="field"><label id="actor-b-label" for="actor-b">' . ($sceneMode === 'solo' ? 'Second NPC (pair mode only)' : 'NPC B') . '</label><select id="actor-b" name="actor_b" required' . $actorBDisabledAttr . '>
' . $optionsB . '</select></p>
<p class="field"><label for="bystander-mode">Other NPCs</label><select id="bystander-mode" name="bystander_mode">
<option value="exclude"' . ($mode === 'exclude' ? ' selected' : '') . '>Exclude from this conversation</option>
<option value="silent"' . ($mode === 'silent' ? ' selected' : '') . '>Present but silent</option>
</select></p>
<p class="checkbox-field"><label><input type="checkbox" name="exclude_player" value="1"' . ($sceneMode === 'solo' || $excludePlayer ? ' checked' : '') . $excludePlayerDisabledAttr . '> <span>Exclude the player</span></label></p>
<button id="arm-button" class="primary-button" type="submit" data-roster-ready="' . ($rosterReady ? '1' : '0') . '"' . $armDisabledAttr . '>Arm or update on next input</button>
</form>
</section>
<section class="end-panel" aria-labelledby="end-heading">
<h2 id="end-heading">End</h2>
<form method="post" class="end-form">
<input type="hidden" name="csrf" value="' . $csrf . '">
<input type="hidden" name="action" value="end">
<button class="quiet-button" type="submit">End on next input</button>
</form>
</section>
</div>
</div>
</main>
<footer class="footer-note">The selected scene changes at the next eligible ordinary input. It does not interrupt speech already in the queue.</footer>
</div>
<script src="assets/ui-refresh.js" defer></script>
</body>
</html>';
}

function pcv_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (!session_start()) {
        throw new RuntimeException('The PHP session is unavailable.');
    }
}

function pcv_send_text(int $statusCode, string $body, array $extraHeaders = []): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=UTF-8');
    foreach ($extraHeaders as $name => $value) {
        header($name . ': ' . $value);
    }
    echo $body;
}

function pcv_unavailable_state(): array
{
    return ['status' => 'unavailable', 'scope' => null, 'pending' => false];
}

function pcv_npc_rows(string $enginePath): array
{
    $enginePath = rtrim($enginePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $runtimeBootstrap = $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'runtime_bootstrap.php';
    $sampleConfig = $enginePath . 'conf' . DIRECTORY_SEPARATOR . 'conf.sample.php';
    $config = $enginePath . 'conf' . DIRECTORY_SEPARATOR . 'conf.php';
    if (!is_file($runtimeBootstrap) || !is_file($config)) {
        throw new RuntimeException('The CHIM runtime configuration is unavailable.');
    }

    require_once $runtimeBootstrap;
    if (is_file($sampleConfig)) {
        require $sampleConfig;
    }
    require $config;
    if (!function_exists('chimRuntimeImportConfigVariables')) {
        throw new RuntimeException('The CHIM runtime configuration loader is unavailable.');
    }
    \chimRuntimeImportConfigVariables(get_defined_vars());
    $GLOBALS['ENGINE_PATH'] = $enginePath;

    $driver = $GLOBALS['DBDRIVER'] ?? null;
    if (!is_string($driver) || preg_match('/\A[A-Za-z0-9_]+\z/', $driver) !== 1) {
        throw new RuntimeException('The CHIM database driver is unavailable.');
    }
    $driverPath = $enginePath . 'lib' . DIRECTORY_SEPARATOR . $driver . '.class.php';
    $npcMasterPath = $enginePath . 'lib' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'npc_master.class.php';
    if (!is_file($driverPath) || !is_file($npcMasterPath)) {
        throw new RuntimeException('The CHIM NPC catalog is unavailable.');
    }
    require_once $driverPath;
    if (!class_exists('sql')) {
        throw new RuntimeException('The CHIM database driver did not load.');
    }
    if (!isset($GLOBALS['db']) || !($GLOBALS['db'] instanceof \sql)) {
        $GLOBALS['db'] = new \sql();
    }
    require_once $npcMasterPath;
    if (!class_exists('NpcMaster')) {
        throw new RuntimeException('The CHIM NPC catalog is unavailable.');
    }
    $npcMaster = new \NpcMaster();
    $rows = $npcMaster->getAll();
    if (!is_array($rows)) {
        throw new RuntimeException('The CHIM NPC catalog is invalid.');
    }
    return array_values($rows);
}

function pcv_run_page(): void
{
    require_once __DIR__ . '/log.php';
    pcv_log_begin_request();

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; script-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'self'");

    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'POST'], true)) {
        pcv_send_text(405, "Method not allowed.\n", ['Allow' => 'GET, POST']);
        return;
    }

    try {
        pcv_start_session();
        if (!isset($_SESSION['pcv_csrf']) || !is_string($_SESSION['pcv_csrf'])
            || preg_match('/\A[a-f0-9]{64}\z/', $_SESSION['pcv_csrf']) !== 1) {
            $_SESSION['pcv_csrf'] = bin2hex(random_bytes(32));
        }
        $csrfToken = $_SESSION['pcv_csrf'];
        session_write_close();
    } catch (Throwable $error) {
        pcv_log_exception('ui.unavailable', 'error', 'unavailable', 'session_unavailable', $error, ['operation' => 'session']);
        pcv_send_text(503, pcv_ui_failure_notice('Settings are unavailable.') . "\n");
        return;
    }

    try {
        require_once __DIR__ . '/state.php';
        foreach (['pcv_current_playthrough_key', 'pcv_current_player_name', 'pcv_read', 'pcv_stage', 'pcv_read_eligible_npcs'] as $function) {
            if (!function_exists($function)) {
                throw new RuntimeException('Private Conversation state is unavailable.');
            }
        }
    } catch (Throwable $error) {
        pcv_log_exception('ui.unavailable', 'error', 'unavailable', 'state_unavailable', $error, ['operation' => 'read']);
        pcv_send_text(503, pcv_ui_failure_notice('Settings are unavailable.') . "\n");
        return;
    }

    $key = null;
    $playerName = null;
    $knownNpcs = [];
    $displayNpcs = [];
    $catalogRows = [];
    $eligibilityStatus = 'unavailable';
    $eligibilityReason = null;
    $catalogAvailable = false;
    $state = pcv_unavailable_state();
    $notice = '';
    $identityFailureLogged = false;
    try {
        $key = pcv_current_playthrough_key();
        $playerName = pcv_current_player_name();
    } catch (Throwable $error) {
        pcv_log_exception('ui.unavailable', 'error', 'unavailable', 'identity_unavailable', $error, ['operation' => 'identity']);
        $identityFailureLogged = true;
        $key = null;
        $playerName = null;
    }
    if (!is_string($key) || $key === '' || !is_string($playerName) || trim($playerName) === '') {
        if (!$identityFailureLogged) {
            pcv_log_event('ui.unavailable', 'error', 'unavailable', 'identity_unavailable', ['operation' => 'identity']);
        }
        $notice = pcv_ui_failure_notice('The current CHIM playthrough is unavailable.');
    } else {
        $stateFailureLogged = false;
        try {
            $loaded = pcv_read($key);
            if (is_array($loaded)) {
                $state = $loaded;
            }
        } catch (Throwable $error) {
            pcv_log_exception('ui.unavailable', 'error', 'unavailable', 'state_unavailable', $error, ['operation' => 'read']);
            $stateFailureLogged = true;
            $state = pcv_unavailable_state();
            $notice = pcv_ui_failure_notice('Private Conversation state is unavailable.');
        }
        if (($state['status'] ?? null) === 'unavailable' && !$stateFailureLogged) {
            pcv_log_event('ui.unavailable', 'error', 'unavailable', 'state_unavailable', ['operation' => 'read']);
            if ($notice === '') {
                $notice = pcv_ui_failure_notice('Private Conversation state is unavailable.');
            }
        }
        try {
            $enginePath = dirname(__DIR__, 2);
            $catalogRows = pcv_npc_rows($enginePath);
            $displayNpcs = pcv_build_known_npcs($catalogRows, $playerName);
            $catalogAvailable = true;
        } catch (Throwable $error) {
            pcv_log_exception('ui.unavailable', 'error', 'unavailable', 'catalog_unavailable', $error, ['operation' => 'catalog']);
            $knownNpcs = [];
            if ($notice === '') {
                $notice = pcv_ui_failure_notice('The current NPC list is unavailable.');
            }
        }
        if ($catalogAvailable) {
            try {
                $eligibility = pcv_read_eligible_npcs($key, $catalogRows, $playerName);
                $eligibilityStatus = $eligibility['status'] ?? 'unavailable';
                $eligibilityReason = is_string($eligibility['reason'] ?? null) ? $eligibility['reason'] : null;
                $knownNpcs = is_array($eligibility['known_npcs'] ?? null) ? $eligibility['known_npcs'] : [];
                if (!in_array($eligibilityStatus, ['ready', 'empty', 'missing', 'stale', 'unavailable'], true)) {
                    $eligibilityStatus = 'unavailable';
                    $eligibilityReason = 'presence_unavailable';
                    $knownNpcs = [];
                } elseif ($eligibilityStatus !== 'ready') {
                    $knownNpcs = [];
                }
            } catch (Throwable $error) {
                pcv_log_exception('ui.unavailable', 'error', 'unavailable', 'state_unavailable', $error, ['operation' => 'read']);
                $eligibilityStatus = 'unavailable';
                $eligibilityReason = 'presence_unavailable';
                $knownNpcs = [];
            }
        }
    }

    $responseCode = 200;
    if ($method === 'POST') {
        $stageAction = ($_POST['action'] ?? null) === 'end' ? 'end' : 'enable';
        $operation = 'stage';
        try {
            $desired = pcv_form_desired_state($_POST, $csrfToken, $knownNpcs);
            if (!pcv_form_can_stage($desired, $key, $catalogAvailable)) {
                pcv_log_event('ui.scope_stage_failed', 'error', 'failed', 'state_unavailable',
                    ['action' => $stageAction, 'operation' => 'stage']);
                $responseCode = 503;
                $state = pcv_unavailable_state();
                $notice = pcv_ui_failure_notice('The current playthrough or NPC catalog is unavailable.');
            } else {
                $stageAction = ($desired['enabled'] ?? false) === true ? 'enable' : 'end';
                $staged = pcv_stage($key, $desired, $knownNpcs);
                if (!is_array($staged) || !in_array($staged['status'] ?? null, ['active', 'pending', 'off'], true)) {
                    $reason = is_array($staged) && ($staged['status'] ?? null) === 'unavailable'
                        ? 'state_unavailable' : 'internal_error';
                    pcv_log_event('ui.scope_stage_failed', 'error', 'failed', $reason,
                        ['action' => $stageAction, 'operation' => 'stage']);
                    $responseCode = 503;
                    $state = pcv_unavailable_state();
                    $notice = pcv_ui_failure_notice('The requested change could not be staged.');
                } else {
                    pcv_ui_log_stage_accepted($staged, $stageAction);
                    $operation = 'readback';
                    $loaded = pcv_read($key);
                    if (!is_array($loaded) || ($loaded['status'] ?? null) === 'unavailable') {
                        pcv_log_set_config_id($staged['pending_config_id'] ?? $staged['config_id'] ?? null);
                        pcv_log_event('ui.scope_stage_failed', 'error', 'failed', 'state_unavailable',
                            ['action' => $stageAction, 'operation' => 'readback']);
                        $responseCode = 503;
                        $state = pcv_unavailable_state();
                        $notice = pcv_ui_failure_notice('The requested change could not be confirmed.');
                    } else {
                        $state = $loaded;
                        $notice = $stageAction === 'enable'
                            ? 'The selected pair was staged. The status below reflects the latest settings.'
                            : 'The end request was staged. The status below reflects the latest settings.';
                    }
                }
            }
        } catch (PcvUiFormRejection $error) {
            if (!$catalogAvailable && $error->reasonCode === 'actor_unavailable') {
                pcv_log_event('ui.scope_stage_failed', 'error', 'failed', 'state_unavailable',
                    ['action' => $stageAction, 'operation' => 'stage']);
                $responseCode = 503;
                $notice = pcv_ui_failure_notice('The current NPC list is unavailable.');
            } else {
                $reason = $error->reasonCode === 'actor_unavailable'
                    ? pcv_picker_rejection_reason($eligibilityStatus, $eligibilityReason)
                    : $error->reasonCode;
                pcv_log_event('ui.scope_stage_rejected', 'warning', 'rejected', $reason);
                $responseCode = 400;
                $message = $error->reasonCode === 'actor_unavailable'
                    ? pcv_picker_rejection_notice($eligibilityStatus, count($knownNpcs))
                    : 'The submitted form was invalid. Refresh the page and choose two available NPCs.';
                $notice = pcv_ui_failure_notice($message);
            }
        } catch (InvalidArgumentException $error) {
            if ($operation === 'stage') {
                pcv_log_event('ui.scope_stage_rejected', 'warning', 'rejected',
                    pcv_picker_rejection_reason($eligibilityStatus, $eligibilityReason));
                $responseCode = 400;
                $notice = pcv_ui_failure_notice(pcv_picker_rejection_notice($eligibilityStatus, count($knownNpcs)));
            } else {
                pcv_log_set_config_id($staged['pending_config_id'] ?? $staged['config_id'] ?? null);
                pcv_log_exception('ui.scope_stage_failed', 'error', 'failed', 'internal_error', $error,
                    ['action' => $stageAction, 'operation' => 'readback']);
                $responseCode = 503;
                $state = pcv_unavailable_state();
                $notice = pcv_ui_failure_notice('The requested change could not be confirmed.');
            }
        } catch (Throwable $error) {
            if ($operation === 'readback' && is_array($staged ?? null)) {
                pcv_log_set_config_id($staged['pending_config_id'] ?? $staged['config_id'] ?? null);
            }
            pcv_log_exception('ui.scope_stage_failed', 'error', 'failed', 'internal_error', $error,
                ['action' => $stageAction, 'operation' => $operation]);
            $responseCode = 503;
            $state = pcv_unavailable_state();
            $notice = $operation === 'readback'
                ? pcv_ui_failure_notice('The requested change could not be confirmed.')
                : pcv_ui_failure_notice('The requested change could not be staged. Settings may be unavailable.');
        }
    }

    if (!$catalogAvailable) {
        $knownNpcs = [];
        if ($notice === '') {
            $notice = pcv_ui_failure_notice('The current playthrough or NPC list is unavailable.');
        }
    }

    $isRefresh = $method === 'GET' && ($_GET['refresh'] ?? null) === '1';
    if ($method === 'GET' && !$isRefresh && $notice === '') {
        pcv_log_event('ui.page_open', 'info', 'ok');
    }

    http_response_code($responseCode);
    header('Content-Type: text/html; charset=UTF-8');
    $playthroughRef = is_string($key) && $key !== '' ? hash('sha256', 'pcv-ui:' . $key) : '';
    echo pcv_render_page($csrfToken, $state, $knownNpcs, $notice, $eligibilityStatus, $catalogAvailable, $displayNpcs, $playthroughRef);
}

if (!defined('PCV_UI_TEST')) {
    pcv_run_page();
}
