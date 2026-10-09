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
    $desired = pcv_form_desired_state_core($post, $csrfToken, $knownNpcs);
    if (($desired['enabled'] ?? false) !== true) {
        return $desired;
    }
    // 0.1.14 roleplay settings; the state layer validates lengths and values.
    $card = $post['card'] ?? null;
    if ($card !== null && !is_string($card)) {
        throw new PcvUiFormRejection('invalid_configuration', 'The scene card is invalid.');
    }
    if (is_string($card) && trim($card) !== '') {
        $desired['card'] = $card;
    }
    $pace = $post['pace'] ?? null;
    if ($pace !== null && (!is_string($pace) || !in_array($pace, ['short', 'normal', 'long'], true))) {
        throw new PcvUiFormRejection('invalid_configuration', 'The turn length is invalid.');
    }
    if (is_string($pace) && ($desired['scene_mode'] ?? 'pair') !== 'solo') {
        $desired['pace'] = $pace;
    }
    // 0.1.16 scene actions: opt-in groups; solo has no one to brawl with.
    $actions = $post['actions'] ?? null;
    if ($actions !== null) {
        require_once __DIR__ . '/state.php';
        if (!is_array($actions) || !array_is_list($actions)) {
            throw new PcvUiFormRejection('invalid_configuration', 'Choose valid scene actions.');
        }
        foreach ($actions as $group) {
            if (!is_string($group) || !in_array($group, PCV_SCENE_ACTION_GROUPS, true)) {
                throw new PcvUiFormRejection('invalid_configuration', 'Choose valid scene actions.');
            }
        }
        $groups = array_values(array_intersect(PCV_SCENE_ACTION_GROUPS, $actions));
        if (($desired['scene_mode'] ?? 'pair') === 'solo') {
            $groups = array_values(array_diff($groups, ['physical']));
        }
        if ($groups !== []) {
            $desired['actions'] = $groups;
        }
    }
    $cap = $post['free_cap'] ?? null;
    if (($desired['free'] ?? false) === true && $cap !== null) {
        if (!is_string($cap) || preg_match('/\A[2-6]\z/', $cap) !== 1) {
            throw new PcvUiFormRejection('invalid_configuration', 'Choose two to six NPCs for a free scene.');
        }
        $desired['free_cap'] = $cap;
    }
    return $desired;
}

function pcv_form_desired_state_core(array $post, string $csrfToken, array $knownNpcs): array
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
    if (!is_string($sceneMode) || !in_array($sceneMode, ['pair', 'solo', 'free'], true)) {
        throw new PcvUiFormRejection('invalid_configuration', 'The selected scene mode is invalid.');
    }

    if ($sceneMode === 'free') {
        // Free scene (0.1.12): the nearest six eligible NPCs are chosen at activation; pickers are ignored.
        $bystanderMode = $post['bystander_mode'] ?? null;
        if (!is_string($bystanderMode) || !in_array($bystanderMode, ['exclude', 'silent'], true)) {
            throw new PcvUiFormRejection('invalid_configuration', 'The other NPCs option is invalid.');
        }
        return ['enabled' => true, 'scene_mode' => 'pair', 'free' => true, 'exclude_player' => true, 'bystander_mode' => $bystanderMode];
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

    // Group form (0.1.11): optional NPC C and D plus an opener. A post without the opener field is the
    // pre-0.1.11 pair form and keeps the legacy shape.
    if (!array_key_exists('opener', $post)) {
        return [
            'enabled' => true,
            'scene_mode' => 'pair',
            'actor_a' => $actorA,
            'actor_b' => $actorB,
            'exclude_player' => $excludePlayer === '1',
            'bystander_mode' => $bystanderMode,
        ];
    }
    $ids = [$actorA, $actorB];
    foreach (['actor_c', 'actor_d'] as $optionalKey) {
        $optional = $post[$optionalKey] ?? '';
        if ($optional === '') {
            continue;
        }
        if (!is_string($optional) || in_array($optional, $ids, true)) {
            throw new PcvUiFormRejection('invalid_configuration', 'Choose two to four different NPCs.');
        }
        if (!array_key_exists($optional, $knownNpcs)) {
            throw new PcvUiFormRejection('actor_unavailable', 'The selected scope is invalid.');
        }
        $ids[] = $optional;
    }
    $opener = $post['opener'];
    if (!is_string($opener) || ($opener !== 'auto' && !in_array($opener, $ids, true))) {
        throw new PcvUiFormRejection('invalid_configuration', 'The opener must be one of the selected NPCs.');
    }
    return [
        'enabled' => true,
        'scene_mode' => 'pair',
        'actor_ids' => $ids,
        'opener' => $opener,
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
    $freeMode = $sceneMode === 'pair' && ($formScope['free'] ?? false) === true;
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
        if (is_array($config['actor_ids'] ?? null) && count($config['actor_ids']) > 2) {
            $names = array_map(static fn($id) => pcv_html($displayNpcs[(string)$id] ?? ('NPC ID ' . $id)), $config['actor_ids']);
            return implode(', ', array_slice($names, 0, -1)) . ' and ' . $names[count($names) - 1];
        }
        $actorB = is_string($config['actor_b'] ?? null) || is_int($config['actor_b'] ?? null)
            ? (string)$config['actor_b'] : '';
        if ($actorB === '') {
            return '';
        }
        $nameB = $displayNpcs[$actorB] ?? ('NPC ID ' . $actorB);
        return pcv_html($nameA) . ' and ' . pcv_html($nameB);
    };
    $isGroup = static fn(?array $config): bool => is_array($config) && is_array($config['actor_ids'] ?? null) && count($config['actor_ids']) > 2;
    $isFree = static fn(?array $config): bool => is_array($config) && ($config['free'] ?? false) === true;
    if ($status === 'active' && ($scene = $sceneText($scope)) !== '') {
        $scopeSummary .= '<p>' . ($scopeSceneMode === 'solo' ? 'Current reflection: '
            : ($isFree($scope) ? 'Current free scene: ' : ($isGroup($scope) ? 'Current group: ' : 'Current pair: ')))
            . $scene . '.</p>';
        if (is_string($scope['card'] ?? null) && $scope['card'] !== '') {
            $scopeSummary .= '<p class="small-note">Scene card: ' . pcv_html($scope['card']) . '</p>';
        }
        if (is_array($scope['actions'] ?? null) && $scope['actions'] !== []) {
            $scopeSummary .= '<p class="small-note">Scene actions: ' . pcv_html(implode(', ', $scope['actions'])) . '</p>';
        }
    }
    if ($status === 'active' && is_array($state['dropped'] ?? null)) {
        foreach ($state['dropped'] as $droppedEntry) {
            $droppedName = pcv_html($displayNpcs[(string)($droppedEntry['id'] ?? '')] ?? ('NPC ID ' . ($droppedEntry['id'] ?? '?')));
            $scopeSummary .= ($droppedEntry['reason'] ?? null) === 'left_scene'
                ? '<p class="small-note">' . $droppedName . ' left the scene.</p>'
                : '<p class="small-note">Started without ' . $droppedName . ' (not nearby).</p>';
        }
    }
    if ($status !== 'active' && ($state['last_end']['reason'] ?? null) === 'members_gone') {
        $scopeSummary .= '<p class="small-note">The scene ended: its NPCs were gone for five minutes.</p>';
    }
    $lastTurn =$status === 'active' && is_string($state['config_id'] ?? null) && function_exists('pcv_log_read_last_turn')
        ? pcv_log_read_last_turn($state['config_id']) : null;
    if (is_array($lastTurn)) {
        $spoke = $lastTurn['outcome'] === 'postrequest_observed';
        $scopeSummary .= '<p class="last-turn">Last scene turn: ' . ($spoke ? 'spoke' : 'ended without speech')
            . ' (' . pcv_html(substr($lastTurn['timestamp'], 11, 8)) . ' UTC).'
            . ($spoke ? '' : ' A silent rechat is normal when CHIM has used its rechat budget.') . '</p>';
    }
    if ($pending) {
        if ($pendingEnd) {
            $scopeSummary .= '<p class="pending-summary">End is queued for the next eligible ordinary input.</p>';
        } elseif ($isFree($pendingScope) && !is_array($pendingScope['actor_ids'] ?? null)) {
            $scopeSummary .= '<p class="pending-summary">Next: free scene (nearest six).</p>';
        } elseif ($pendingScope !== null && ($scene = $sceneText($pendingScope)) !== '') {
            $scopeSummary .= '<p class="pending-summary">Next ' . ($pendingSceneMode === 'solo' ? 'reflection: ' : ($isGroup($pendingScope) ? 'group: ' : 'pair: '))
                . $scene . '.</p>';
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
    $actorBDisabled = $pairDisabled || $sceneMode === 'solo' || $freeMode;
    $soloChecked = $sceneMode === 'solo' ? ' checked' : '';
    $soloDisabled = !$rosterReady ? ' disabled' : '';
    $freeChecked = $freeMode ? ' checked' : '';
    $freeDisabled = !$rosterReady ? ' disabled' : '';
    $actorADisabledAttr = $freeMode ? ' disabled' : $actorDisabled;
    $actorBDisabledAttr = $actorBDisabled ? ' disabled' : '';
    $armDisabledAttr = ($sceneMode === 'solo' ? !$rosterReady : $pairDisabled) ? ' disabled' : '';
    $excludePlayerDisabledAttr = $sceneMode === 'solo' || $freeMode ? ' disabled' : '';
    $optionsA = $optionsFor($actorA);
    $optionsB = str_replace('Choose an NPC', 'Choose a different NPC', $optionsFor($actorB));
    // Optional group members (0.1.11) and the opener picker.
    $formIds = is_array($formScope['actor_ids'] ?? null) ? array_values($formScope['actor_ids']) : [];
    $actorC = isset($formIds[2]) ? (string)$formIds[2] : '';
    $actorD = isset($formIds[3]) ? (string)$formIds[3] : '';
    $optionsC = str_replace('Choose an NPC', 'None', $optionsFor($actorC));
    $optionsD = str_replace('Choose an NPC', 'None', $optionsFor($actorD));
    $formOpener = is_string($formScope['opener'] ?? null) ? $formScope['opener'] : 'auto';
    $openerOptions = '<option value="auto"' . ($formOpener === 'auto' ? ' selected' : '') . '>Auto (named in the direction, else NPC A)</option>' . "\n";
    foreach ($knownNpcs as $id => $name) {
        $id = (string)$id;
        $openerOptions .= '<option value="' . pcv_html($id) . '"' . ($formOpener === $id ? ' selected' : '') . '>'
            . pcv_html($name . ' (ID ' . $id . ')') . "</option>\n";
    }
    $extraDisabledAttr = ($pairDisabled || $sceneMode === 'solo' || $freeMode) ? ' disabled' : '';
    // 0.1.14 roleplay settings.
    $formCard = is_string($formScope['card'] ?? null) ? $formScope['card'] : '';
    $formPace = in_array($formScope['pace'] ?? null, ['short', 'long'], true) ? $formScope['pace'] : 'normal';
    $paceOptions = '';
    foreach (['short' => 'Short (one or two sentences)', 'normal' => 'Normal', 'long' => 'Long (up to six sentences)'] as $value => $label) {
        $paceOptions .= '<option value="' . $value . '"' . ($formPace === $value ? ' selected' : '') . '>' . $label . "</option>\n";
    }
    $formCap = is_int($formScope['free_cap'] ?? null) ? $formScope['free_cap'] : 6;
    $capOptions = '';
    for ($cap = 2; $cap <= 6; $cap++) {
        $capOptions .= '<option value="' . $cap . '"' . ($formCap === $cap ? ' selected' : '') . '>' . ($cap === 6 ? '6 (nearest six)' : (string)$cap) . "</option>\n";
    }
    $paceDisabledAttr = $sceneMode === 'solo' ? ' disabled' : '';
    // 0.1.16 scene actions; unticked by default, so a new scene never inherits them by surprise.
    $formActions = is_array($formScope['actions'] ?? null) ? $formScope['actions'] : [];
    $actionLabels = [
        'personal' => 'Personal (drink, toast, eat, sit, relax)',
        'physical' => 'Physical (a bare-fisted brawl between members; not for solo)',
        'intimate' => 'Intimate (SHARMAT actions between members, or alone in solo; SHARMAT\'s own rules still apply)',
    ];
    $actionBoxes = '';
    foreach ($actionLabels as $group => $label) {
        $actionBoxes .= '<p class="checkbox-field"><label><input type="checkbox" id="actions-' . $group . '" name="actions[]" value="' . $group . '"'
            . (in_array($group, $formActions, true) ? ' checked' : '')
            . ($group === 'physical' && $sceneMode === 'solo' ? ' disabled' : '') . '> <span>' . pcv_html($label) . "</span></label></p>\n";
    }
    $capDisabledAttr = $freeMode ? '' : ' disabled';
    $noticeHtml =$notice === '' ? '' : '<p role="status">' . pcv_html($notice) . '</p>';
    $csrf = pcv_html($csrfToken);

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CHIM Private Conversation — Part of the World of Drama-llama</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="page" data-playthrough-ref="' . pcv_html($playthroughRef) . '" data-refresh-url="?refresh=1" data-logs-url="?view=logs">
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
<p id="page-intro" class="hero-intro">Choose two to four voices for a conversation, or one NPC to think aloud. CHIM carries that scene direction into the next eligible ordinary Standard-mode input.</p>
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
<p class="small-note">An already-started exchange may finish; use the in-game Stop All Dialogue control to stop playback immediately.</p>
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
<p class="checkbox-field"><label><input type="checkbox" id="free-mode" name="scene_mode" value="free"' . $freeChecked . $freeDisabled . '> <span>Free scene (the nearest six NPCs, player excluded)</span></label></p>
<p id="mode-guidance" class="small-note"' . ($sceneMode === 'solo' ? '' : ' hidden') . '>Solo reflection asks the NPC to think aloud. Opinion changes require compatible Mind Poisoning support.</p>
<p class="field"><label id="actor-a-label" for="actor-a">' . ($sceneMode === 'solo' ? 'Reflecting NPC' : 'NPC A') . '</label><select id="actor-a" name="actor_a" required' . $actorADisabledAttr . '>
' . $optionsA . '</select></p>
<p class="field"><label id="actor-b-label" for="actor-b">' . ($sceneMode === 'solo' ? 'Second NPC (pair mode only)' : 'NPC B') . '</label><select id="actor-b" name="actor_b" required' . $actorBDisabledAttr . '>
' . $optionsB . '</select></p>
<p class="field group-field"><label for="actor-c">NPC C (optional)</label><select id="actor-c" name="actor_c"' . $extraDisabledAttr . '>
' . $optionsC . '</select></p>
<p class="field group-field"><label for="actor-d">NPC D (optional)</label><select id="actor-d" name="actor_d"' . $extraDisabledAttr . '>
' . $optionsD . '</select></p>
<p class="field group-field"><label for="opener">Who speaks first</label><select id="opener" name="opener"' . $extraDisabledAttr . '>
' . $openerOptions . '</select></p>
<p class="field"><label for="bystander-mode">Other NPCs</label><select id="bystander-mode" name="bystander_mode">
<option value="exclude"' . ($mode === 'exclude' ? ' selected' : '') . '>Exclude from this conversation</option>
<option value="silent"' . ($mode === 'silent' ? ' selected' : '') . '>Present but silent</option>
</select></p>
<p class="field"><label for="free-cap">How many (free scene)</label><select id="free-cap" name="free_cap"' . $capDisabledAttr . '>
' . $capOptions . '</select></p>
<p class="field"><label for="pace">Turn length</label><select id="pace" name="pace"' . $paceDisabledAttr . '>
' . $paceOptions . '</select></p>
<p class="field"><label for="scene-card">Scene card (optional)</label><textarea id="scene-card" name="card" maxlength="300" rows="2" placeholder="Late night at the Bannered Mare. Tense. The treaty is on the table.">' . pcv_html($formCard) . '</textarea></p>
<p class="small-note">The card and turn length shape every scene turn. They add to each NPC\'s own state (for example a SHARMAT drunk stage) and never replace it.</p>
<fieldset class="scene-actions"><legend>Scene actions (optional; talk only when none is ticked)</legend>
' . $actionBoxes . '<p class="small-note">Actions only ever reach the people in the scene. Attack and killing are never allowed.</p></fieldset>
<p class="checkbox-field"><label><input type="checkbox" name="exclude_player" value="1"' . ($sceneMode === 'solo' || $freeMode || $excludePlayer ? ' checked' : '') . $excludePlayerDisabledAttr . '> <span>Exclude the player</span></label></p>
<button id="arm-button" class="primary-button" type="submit" data-roster-ready="' . ($rosterReady ? '1' : '0') . '"' . $armDisabledAttr . '>Arm or update on next input</button>
</form>
</section>
<section class="end-panel" aria-labelledby="end-heading">
<h2 id="end-heading">End</h2>
<form method="post" class="end-form">
<input type="hidden" name="csrf" value="' . $csrf . '">
<input type="hidden" name="action" value="end">
<button class="quiet-button" type="submit">End now</button>
</form>
</section>
</div>
</div>
</main>
<footer class="footer-note">ARM or update takes effect on the next eligible ordinary input. END clears the active scene immediately but does not interrupt speech already in the queue. <a class="logs-link" href="?view=logs">Operational logs</a></footer>
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

/**
 * The Windows host as seen from a WSL2 NAT guest: the single private IPv4 default gateway in
 * /proc/net/route. Missing, ambiguous or public data trusts nothing.
 */
function pcv_ui_wsl_host_address(string $routeTable): ?string
{
    $gateways = [];
    foreach (preg_split('/\r?\n/', $routeTable) ?: [] as $line) {
        $fields = preg_split('/\s+/', trim($line));
        if (!is_array($fields) || count($fields) < 3 || $fields[1] !== '00000000'
            || preg_match('/\A[0-9A-Fa-f]{8}\z/', $fields[2]) !== 1) {
            continue;
        }
        $octets = array_reverse(array_map('hexdec', str_split($fields[2], 2)));
        $gateways[implode('.', $octets)] = true;
    }
    if (count($gateways) !== 1) {
        return null;
    }
    $address = (string)array_key_first($gateways);
    $isIpv4 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    $isPrivate = $isIpv4
        && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false
        && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_RES_RANGE) !== false;
    return $isPrivate ? $address : null;
}

/**
 * Diagnostics are local-only: direct loopback, the WSL host (the player's own Windows browser), or a
 * server-authenticated REMOTE_USER. Any forwarding header disqualifies the address checks.
 */
function pcv_ui_logs_access_allowed(array $server, ?string $hostAddress = null, bool $resolveHost = false): bool
{
    $forwarded = array_key_exists('HTTP_FORWARDED', $server)
        || array_key_exists('HTTP_X_FORWARDED_FOR', $server)
        || array_key_exists('HTTP_X_REAL_IP', $server);
    $remoteAddress = $server['REMOTE_ADDR'] ?? null;
    if ($resolveHost && $hostAddress === null && !$forwarded) {
        $routes = @file_get_contents('/proc/net/route');
        $hostAddress = is_string($routes) ? pcv_ui_wsl_host_address($routes) : null;
    }
    $local = !$forwarded && is_string($remoteAddress)
        && (in_array($remoteAddress, ['127.0.0.1', '::1'], true) || ($hostAddress !== null && $remoteAddress === $hostAddress));
    $remoteUser = $server['REMOTE_USER'] ?? null;
    $authenticatedUser = is_string($remoteUser) && trim($remoteUser) !== '';
    return $local || $authenticatedUser;
}

function pcv_ui_diagnostics_rejected(string $reason, string $operation): void
{
    if (!in_array($reason, ['access_denied', 'csrf_failed', 'invalid_filter', 'invalid_failure_code'], true)
        || !in_array($operation, ['logs_read', 'log_export', 'browser_report'], true)) {
        return;
    }
    pcv_log_event('ui.diagnostics_rejected', 'warning', 'rejected', $reason,
        ['operation' => $operation, 'source' => 'browser']);
}

function pcv_ui_logs_operation($action): string
{
    return match ($action) {
        'export' => 'log_export',
        'client_report' => 'browser_report',
        default => 'logs_read',
    };
}

function pcv_ui_logs_filters(array $post): array
{
    $allowed = ['action', 'csrf', 'request', 'config', 'event', 'severity', 'event_id', 'utterance_id', 'linked_request_id', 'limit'];
    foreach (array_keys($post) as $key) {
        if (!is_string($key) || !in_array($key, $allowed, true)) {
            throw new InvalidArgumentException('invalid filter');
        }
    }

    $filters = [];
    foreach (['request', 'config', 'event', 'severity', 'event_id', 'utterance_id', 'linked_request_id'] as $key) {
        $value = $post[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new InvalidArgumentException('invalid filter');
        }
        $filters[$key] = $value === '' ? null : $value;
    }
    $limit = $post['limit'] ?? '100';
    if (!is_string($limit)) {
        throw new InvalidArgumentException('invalid filter');
    }
    if ($limit === '') {
        $limit = '100';
    }
    if (preg_match('/\A[1-9][0-9]{0,3}\z/D', $limit) !== 1) {
        throw new InvalidArgumentException('invalid filter');
    }
    $filters['limit'] = (int)$limit;
    require_once __DIR__ . '/log_reader.php';
    return pcv_diagnostics_normalize_filters($filters);
}

function pcv_ui_logs_session_token(): string
{
    pcv_start_session();
    if (!isset($_SESSION['pcv_csrf']) || !is_string($_SESSION['pcv_csrf'])
        || preg_match('/\A[a-f0-9]{64}\z/', $_SESSION['pcv_csrf']) !== 1) {
        $_SESSION['pcv_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pcv_csrf'];
}

function pcv_ui_logs_send_html(int $statusCode, string $html): void
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
}

function pcv_render_logs_locked_page(array $server = []): string
{
    $port = preg_match('/\A[0-9]{1,5}\z/', (string)($server['SERVER_PORT'] ?? '')) === 1 ? (string)$server['SERVER_PORT'] : '8081';
    $script = preg_match('~\A/[A-Za-z0-9_./-]{1,200}\z~', (string)($server['SCRIPT_NAME'] ?? '')) === 1
        ? (string)$server['SCRIPT_NAME'] : '/HerikaServer/ext/private_conversation/index.php';
    $localUrl = "http://127.0.0.1:{$port}{$script}?view=logs";
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>CHIM Private Conversation — Operational Logs</title><link rel="stylesheet" href="assets/style.css"></head>'
        . '<body class="page"><main class="page-frame logs-page"><header class="topbar"><p class="wordmark">CHIM / SCENE NOTES</p>'
        . '<a href="?">Return to Private Conversation</a></header><section class="logs-panel"><h1>Operational logs</h1>'
        . '<p role="status">Logs access is locked. Open this page directly on the CHIM host or use a web server that sets a trusted REMOTE_USER.</p>'
        . '<p>Diagnostic access accepts direct loopback requests without forwarding headers, the Windows PC hosting this WSL server, or a nonempty server-authenticated REMOTE_USER.</p>'
        . '<p>On the PC running this server, open: <a href="' . pcv_html($localUrl) . '">' . pcv_html($localUrl) . '</a></p>'
        . '</section></main></body></html>';
}

function pcv_render_logs_page(string $csrfToken, array $filters, ?array $result = null, string $notice = ''): string
{
    require_once __DIR__ . '/log_reader.php';
    $fields = [
        'request' => 'Request ID', 'config' => 'Configuration ID', 'event_id' => 'Event ID',
        'utterance_id' => 'Utterance ID', 'linked_request_id' => 'Linked request ID',
    ];
    $formFields = '';
    foreach ($fields as $name => $label) {
        $value = is_string($filters[$name] ?? null) ? $filters[$name] : '';
        $formFields .= '<label class="logs-field">' . pcv_html($label) . '<input name="' . pcv_html($name)
            . '" value="' . pcv_html($value) . '" autocomplete="off"></label>';
    }
    $event = is_string($filters['event'] ?? null) ? $filters['event'] : '';
    $eventOptions = '<option value="">Any event</option>';
    foreach (array_keys(pcv_log_event_rules()) as $eventName) {
        $selected = $event === $eventName ? ' selected' : '';
        $eventOptions .= '<option value="' . pcv_html($eventName) . '"' . $selected . '>' . pcv_html($eventName) . '</option>';
    }
    $severity = is_string($filters['severity'] ?? null) ? $filters['severity'] : '';
    $severityOptions = '<option value="">Any severity</option>';
    foreach (['debug', 'info', 'warning', 'error'] as $severityName) {
        $selected = $severity === $severityName ? ' selected' : '';
        $severityOptions .= '<option value="' . $severityName . '"' . $selected . '>' . $severityName . '</option>';
    }
    $limit = is_int($filters['limit'] ?? null) ? $filters['limit'] : 100;
    $csrf = pcv_html($csrfToken);
    $noticeHtml = $notice === '' ? '' : '<p role="status">' . pcv_html($notice) . '</p>';
    $resultHtml = '<p class="small-note">Choose filters and read when needed. The viewer does not poll or load logs on page open.</p>';

    if ($result !== null) {
        $health = is_array($result['health'] ?? null) ? $result['health'] : [];
        $storage = is_array($health['storage'] ?? null) ? $health['storage'] : [];
        $omissions = is_array($health['omissions'] ?? null) ? $health['omissions'] : [];
        $readStatus = is_string($health['read_status'] ?? null) ? $health['read_status'] : 'unavailable';
        $failureCodes = is_array($storage['failure_codes'] ?? null) ? implode(', ', array_filter($storage['failure_codes'], 'is_string')) : '';
        $storageReason = is_string($storage['reason'] ?? null) ? $storage['reason'] : 'none';
        $storageCompleteness = is_string($storage['completeness'] ?? null) ? $storage['completeness'] : 'unknown';
        $resultHtml = '<section class="logs-health" aria-labelledby="logs-health-heading"><h2 id="logs-health-heading">Read and write health</h2>'
            . '<p>Read status: <strong>' . pcv_html($readStatus) . '</strong>. Captured segments: '
            . pcv_html((string)($health['captured_segments'] ?? 0)) . '. History completeness: '
            . pcv_html(is_string($health['completeness'] ?? null) ? $health['completeness'] : 'unknown') . '.</p>'
            . '<p>Current request only: storage mode ' . pcv_html(is_string($storage['storage_mode'] ?? null) ? $storage['storage_mode'] : 'unknown')
            . ', storage reason ' . pcv_html($storageReason) . ', storage completeness ' . pcv_html($storageCompleteness)
            . ', write status ' . pcv_html(is_string($storage['write_status'] ?? null) ? $storage['write_status'] : 'unknown')
            . ', failure codes ' . pcv_html($failureCodes === '' ? 'none' : $failureCodes) . '.</p>'
            . '<p>Retention: ' . pcv_html(is_string($storage['retention'] ?? null) ? $storage['retention'] : 'unknown')
            . '; maximum files ' . pcv_html((string)($storage['max_files'] ?? 'unknown'))
            . '; maximum file bytes ' . pcv_html((string)($storage['max_file_bytes'] ?? 'unknown'))
            . '; maximum entry bytes ' . pcv_html((string)($storage['max_entry_bytes'] ?? 'unknown')) . '.</p>'
            . '<p>Filtered and capped rows are normal bounded-read counts, not corruption. Malformed, oversized, unknown-schema, unsupported-revision, and read-failed rows are omissions.</p>'
            . '<dl class="logs-omissions">';
        foreach (['malformed', 'oversized', 'unknown_schema', 'unsupported_revision', 'read_failed', 'filtered', 'capped'] as $omission) {
            $count = is_int($omissions[$omission] ?? null) ? $omissions[$omission] : 0;
            $resultHtml .= '<dt>' . pcv_html($omission) . '</dt><dd>' . pcv_html((string)$count) . '</dd>';
        }
        $resultHtml .= '</dl></section>';

        $entries = is_array($result['entries'] ?? null) ? $result['entries'] : [];
        $rows = '';
        foreach ($entries as $entry) {
            $entry = pcv_diagnostics_project_entry($entry);
            if ($entry === null) {
                continue;
            }
            $context = json_encode($entry['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $rows .= '<tr><td>' . pcv_html($entry['timestamp']) . '</td><td>' . pcv_html($entry['event']) . '</td><td>'
                . pcv_html($entry['severity'] . ' / ' . $entry['outcome']) . '</td><td>'
                . pcv_html(is_string($entry['reason']) ? $entry['reason'] : '—') . '</td><td>'
                . pcv_html($entry['request_id']) . '</td><td>'
                . pcv_html(is_string($entry['config_id']) ? $entry['config_id'] : '—') . '</td><td>'
                . pcv_html($context === false ? '{}' : $context) . '</td></tr>';
        }
        $resultHtml .= $rows === '' ? '<p>No entries matched these filters.</p>'
            : '<div class="logs-table-wrap"><table class="logs-table"><thead><tr><th>Timestamp (UTC)</th><th>Event</th><th>Severity / outcome</th>'
                . '<th>Reason</th><th>Request ID</th><th>Configuration ID</th><th>Sanitized context</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    }

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>CHIM Private Conversation — Operational Logs</title><link rel="stylesheet" href="assets/style.css"></head>'
        . '<body class="page"><main class="page-frame logs-page"><header class="topbar"><p class="wordmark">CHIM / SCENE NOTES</p>'
        . '<a href="?">Return to Private Conversation</a></header><section class="logs-panel"><p class="eyebrow">Private Conversation diagnostics</p>'
        . '<h1>Operational logs</h1><p>Entries are filtered, sanitized projections of bounded JSONL history. Timestamps are UTC.</p>' . $noticeHtml
        . '<form method="post" class="logs-form"><input type="hidden" name="csrf" value="' . $csrf . '">'
        . $formFields . '<label class="logs-field">Event<select name="event">' . $eventOptions . '</select></label>'
        . '<label class="logs-field">Severity<select name="severity">' . $severityOptions . '</select></label>'
        . '<label class="logs-field">Maximum rows<input type="number" name="limit" min="1" max="1000" value="' . $limit . '"></label>'
        . '<div class="logs-actions"><button class="primary-button" type="submit" name="action" value="read">Read filtered logs</button>'
        . '<button class="quiet-button" type="submit" name="action" value="export">Export JSONL</button></div></form>'
        . $resultHtml . '</section></main></body></html>';
}

function pcv_run_logs_route(string $method): void
{
    if (!pcv_ui_logs_access_allowed($_SERVER, null, true)) {
        pcv_ui_diagnostics_rejected('access_denied', pcv_ui_logs_operation($_POST['action'] ?? null));
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo pcv_render_logs_locked_page($_SERVER);
        return;
    }
    require_once __DIR__ . '/log_reader.php';

    try {
        $csrfToken = pcv_ui_logs_session_token();
    } catch (Throwable $error) {
        pcv_log_exception('ui.unavailable', 'error', 'unavailable', 'session_unavailable', $error, ['operation' => 'session']);
        pcv_send_text(503, "Diagnostics session is unavailable.\n");
        return;
    }

    if ($method === 'GET') {
        session_write_close();
        pcv_ui_logs_send_html(200, pcv_render_logs_page($csrfToken, pcv_diagnostics_normalize_filters([])));
        return;
    }

    $action = $_POST['action'] ?? null;
    $operation = pcv_ui_logs_operation($action);
    $submittedToken = $_POST['csrf'] ?? null;
    if (!is_string($submittedToken) || $csrfToken === '' || !hash_equals($csrfToken, $submittedToken)) {
        session_write_close();
        pcv_ui_diagnostics_rejected('csrf_failed', $operation);
        pcv_send_text(403, "The diagnostics form token is invalid.\n");
        return;
    }

    if (!in_array($action, ['read', 'export', 'client_report'], true)) {
        session_write_close();
        pcv_ui_diagnostics_rejected('invalid_filter', 'logs_read');
        pcv_send_text(400, "Invalid diagnostics request.\n");
        return;
    }

    if ($action === 'client_report') {
        foreach (array_keys($_POST) as $key) {
            if (!in_array($key, ['action', 'csrf', 'code'], true)) {
                session_write_close();
                pcv_ui_diagnostics_rejected('invalid_filter', 'browser_report');
                pcv_send_text(400, "Invalid browser report.\n");
                return;
            }
        }
        $code = $_POST['code'] ?? null;
        $codes = ['timeout', 'network', 'http', 'invalid_response', 'unknown'];
        if (!is_string($code) || !in_array($code, $codes, true)) {
            session_write_close();
            pcv_ui_diagnostics_rejected('invalid_failure_code', 'browser_report');
            pcv_send_text(400, "Invalid browser failure code.\n");
            return;
        }
        $now = time();
        $throttle = is_array($_SESSION['pcv_browser_report_throttle'] ?? null) ? $_SESSION['pcv_browser_report_throttle'] : [];
        $lastReported = $throttle[$code] ?? null;
        $shouldReport = !is_int($lastReported) || $lastReported <= $now - 60;
        if ($shouldReport) {
            $throttle[$code] = $now;
            $_SESSION['pcv_browser_report_throttle'] = $throttle;
        }
        session_write_close();
        if ($shouldReport) {
            $reason = 'browser_refresh_' . $code;
            pcv_log_event('ui.browser_refresh_failed', 'warning', 'reported', $reason, ['source' => 'browser']);
        }
        http_response_code(204);
        header('Content-Length: 0');
        return;
    }

    session_write_close();
    try {
        $filters = pcv_ui_logs_filters($_POST);
    } catch (InvalidArgumentException) {
        pcv_ui_diagnostics_rejected('invalid_filter', $operation);
        pcv_ui_logs_send_html(400, pcv_render_logs_page($csrfToken, pcv_diagnostics_normalize_filters([]), null,
            'One or more diagnostic filters are invalid.'));
        return;
    }

    require_once __DIR__ . '/log_reader.php';
    $result = pcv_diagnostics_load($filters);
    $entries = is_array($result['entries'] ?? null) ? $result['entries'] : [];
    $safeEntries = [];
    foreach ($entries as $entry) {
        $safe = pcv_diagnostics_project_entry($entry);
        if ($safe !== null) {
            $safeEntries[] = $safe;
        }
    }
    $result['entries'] = $safeEntries;
    $health = is_array($result['health'] ?? null) ? $result['health'] : [];
    $omissions = is_array($health['omissions'] ?? null) ? $health['omissions'] : [];
    $omittedCount = 0;
    foreach (['malformed', 'oversized', 'unknown_schema', 'unsupported_revision', 'read_failed'] as $lossCategory) {
        $count = $omissions[$lossCategory] ?? 0;
        if (is_int($count) && $count > 0) {
            $omittedCount += $count;
        }
    }
    $readStatus = $result['status'] ?? 'unavailable';
    if (!in_array($readStatus, ['ok', 'empty', 'busy', 'unavailable'], true)) {
        $readStatus = 'unavailable';
        $result['status'] = 'unavailable';
        $health['read_status'] = 'unavailable';
    }
    $outcome = in_array($readStatus, ['busy', 'unavailable'], true) ? $readStatus
        : ($omittedCount > 0 ? 'limited' : (count($safeEntries) > 0 ? ($action === 'export' ? 'exported' : 'returned') : 'empty'));
    $event = $action === 'export' ? 'ui.log_export' : 'ui.logs_read';
    $reason = match ($outcome) {
        'limited' => 'logs_read_limited',
        'unavailable' => $action === 'export' ? 'log_export_failed' : 'logs_read_failed',
        default => null,
    };
    $severity = match ($outcome) {
        'busy', 'limited' => 'warning',
        'unavailable' => 'error',
        default => 'info',
    };
    pcv_log_event($event, $severity, $outcome, $reason, [
        'source' => 'browser', 'returned_count' => count($safeEntries), 'omitted_count' => $omittedCount,
    ]);
    $health['storage'] = pcv_log_storage_health();
    $result['health'] = $health;

    if ($action === 'export') {
        if (in_array($readStatus, ['busy', 'unavailable'], true)) {
            $message = $readStatus === 'busy' ? "The log is busy. Retry the export later.\n"
                : "The log is unavailable. Check the configured diagnostics storage.\n";
            pcv_send_text(503, $message, ['X-PCV-Diagnostics-Read-Status' => $readStatus,
                'X-PCV-Log-Write-Status' => is_string($health['storage']['write_status'] ?? null)
                    && in_array($health['storage']['write_status'], ['not_verified', 'written', 'degraded'], true)
                    ? $health['storage']['write_status'] : 'unknown']);
            return;
        }
        $lines = [];
        foreach ($safeEntries as $entry) {
            $lines[] = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        }
        $omissionHeader = [];
        foreach (['malformed', 'oversized', 'unknown_schema', 'unsupported_revision', 'read_failed', 'filtered', 'capped'] as $category) {
            $count = $omissions[$category] ?? 0;
            $omissionHeader[] = $category . '=' . (is_int($count) && $count >= 0 ? $count : 0);
        }
        $completeness = ($health['completeness'] ?? null) === 'bounded_history' ? 'bounded_history' : 'unknown';
        $writeStatus = is_string($health['storage']['write_status'] ?? null)
            && in_array($health['storage']['write_status'], ['not_verified', 'written', 'degraded'], true)
            ? $health['storage']['write_status'] : 'unknown';
        http_response_code(200);
        header('Content-Type: application/x-ndjson; charset=UTF-8');
        header('Content-Disposition: attachment; filename="private-conversation-logs.jsonl"');
        header('X-PCV-Diagnostics-Read-Status: ' . $readStatus);
        header('X-PCV-Diagnostics-Completeness: ' . $completeness);
        header('X-PCV-Diagnostics-Omissions: ' . implode(',', $omissionHeader));
        header('X-PCV-Log-Write-Status: ' . $writeStatus);
        echo $lines === [] ? '' : implode("\n", $lines) . "\n";
        return;
    }

    $responseStatus = in_array($readStatus, ['busy', 'unavailable'], true) ? 503 : 200;
    $notice = $readStatus === 'busy' ? 'The log is busy; retry a manual read later.'
        : ($readStatus === 'unavailable' ? 'The log reader is unavailable. Check current request health below.' : '');
    pcv_ui_logs_send_html($responseStatus, pcv_render_logs_page($csrfToken, $filters, $result, $notice));
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
    if (($_GET['view'] ?? null) === 'logs') {
        pcv_run_logs_route($method);
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
                            ? 'The selected scene was staged. The status below reflects the latest settings.'
                            : 'The active and pending private scene state was cleared immediately.';
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
