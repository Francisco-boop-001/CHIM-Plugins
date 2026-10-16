<?php
declare(strict_types=1);

namespace ChimMindPoisoning;

function renderDashboard(array $model, array $filters): void
{
    $escape = static fn(mixed $value): string => htmlspecialchars(
        is_scalar($value) ? (string)$value : '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
    $text = static function (mixed $value, string $fallback = 'Unavailable'): string {
        return (is_string($value) || is_int($value)) && trim((string)$value) !== '' ? (string)$value : $fallback;
    };
    $formatNumber = static function (mixed $value, bool $signed = false): ?string {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value)) {
            return null;
        }
        if (is_int($value)) {
            $formatted = (string)$value;
            $positive = $value > 0;
        } else {
            $number = (float)$value;
            if ($number === 0.0) {
                $number = 0.0;
            }
            $formatted = json_encode($number, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            if (str_ends_with($formatted, '.0')) {
                $formatted = substr($formatted, 0, -2);
            }
            $positive = $number > 0;
        }
        return ($signed && $positive ? '+' : '') . $formatted;
    };
    $codeLabel = static fn(string $value): string => ucwords(str_replace(['-', '_'], ' ', $value));
    $currentTypeDisplay = static function (mixed $value, mixed $state) use ($escape): string {
        return match (is_string($state) ? $state : '') {
            'set' => $escape($value),
            'unset' => 'Not set',
            'ambiguous' => 'Ambiguous',
            'invalid' => is_string($value) && $value !== '' ? 'Invalid: <code>' . $escape($value) . '</code>' : 'Invalid',
            default => 'Unavailable',
        };
    };

    $outcomeOptions = [
        'committed' => 'Committed',
        'zero-change' => 'Zero change',
        'unconfirmed' => 'Unconfirmed',
        'skipped' => 'Skipped',
        'rejected' => 'Rejected',
        'failed' => 'Failed',
    ];
    $levelOptions = ['debug' => 'Debug', 'info' => 'Info', 'warning' => 'Warning', 'error' => 'Error'];
    $safeLogFields = array_fill_keys([
        'schema_version', 'plugin', 'version', 'timestamp', 'request_id', 'level', 'event',
        'event_id', 'playthrough_id', 'speaker_id', 'speaker_kind', 'listener_id', 'addressed_listener_id', 'listener_role', 'batch_id', 'opinion_owner_id', 'source_kind', 'connector_id', 'utterance_id',
        'stage', 'outcome', 'reason', 'model_outcome', 'persistence_outcome', 'persistence_reason',
        'commit_state', 'elapsed_ms', 'model_ms', 'persistence_ms', 'payload_bytes', 'subject_count',
        'speech_bytes', 'changed_count', 'affinity_changed_count', 'type_changed_count', 'delta', 'subject', 'committed', 'cleanup_failed', 'changes', 'type_changes', 'type_decisions',
    ], true);
    $tab = ($filters['tab'] ?? null) === 'diagnostics' ? 'diagnostics' : 'interactions';
    $q = is_string($filters['q'] ?? null) ? $filters['q'] : '';
    $theme = ($filters['theme'] ?? null) === 'night' ? 'night' : 'day';
    $requestedOutcome = is_string($filters['outcome'] ?? null) ? $filters['outcome'] : '';
    $outcome = isset($outcomeOptions[$requestedOutcome]) ? $requestedOutcome : '';
    $requestedLevel = is_string($filters['level'] ?? null) ? $filters['level'] : '';
    $level = isset($levelOptions[$requestedLevel]) ? $requestedLevel : '';
    $queryUrl = static function (string $nextTab, bool $download = false, bool $keepFilters = true, ?string $nextTheme = null) use ($q, $outcome, $level, $theme): string {
        $query = ['tab' => $nextTab];
        $query['theme'] = in_array($nextTheme, ['day', 'night'], true) ? $nextTheme : $theme;
        if ($keepFilters) {
            foreach (['q' => $q, 'outcome' => $outcome, 'level' => $level] as $key => $value) {
                if ($value !== '') {
                    $query[$key] = $value;
                }
            }
        }
        if ($download) {
            $query['download'] = '1';
        }
        return 'dashboard.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    };
    $source = is_array($model['source'] ?? null) ? $model['source'] : [];
    $databaseState = strtolower(is_string($source['database'] ?? null) ? $source['database'] : '');
    $logsState = strtolower(is_string($source['logs'] ?? null) ? $source['logs'] : '');
    $databaseUnavailable = in_array($databaseState, ['unavailable', 'error', 'failed'], true);
    $logsUnavailable = in_array($logsState, ['unavailable', 'error', 'failed'], true);
    $sourceClass = static function (mixed $value): string {
        return match (strtolower(is_string($value) ? $value : '')) {
            'available', 'ready', 'ok' => 'source-value--available',
            'unavailable', 'error', 'failed' => 'source-value--unavailable',
            'limited', 'partial' => 'source-value--limited',
            default => 'source-value--neutral',
        };
    };
    $outcomeClass = static function (string $value): string {
        return match (strtolower($value)) {
            'committed' => 'status--success',
            'zero-change', 'skipped', 'ignored' => 'status--quiet',
            'rejected', 'failed', 'unconfirmed', 'commit-unconfirmed', 'commit_unconfirmed' => 'status--warning',
            default => 'status--quiet',
        };
    };
    $outcomeLabel = static fn(string $value): string => match (strtolower($value)) {
        'zero-change' => 'Zero change',
        'unconfirmed', 'commit-unconfirmed', 'commit_unconfirmed' => 'Unconfirmed',
        default => $codeLabel($value),
    };
    $levelClass = static fn(string $value): string => match (strtolower($value)) {
        'error' => 'status--error',
        'warning' => 'status--warning',
        'info' => 'status--success',
        default => 'status--quiet',
    };
    $version = $text($model['version'] ?? null);
    $generatedAt = $text($model['generated_at'] ?? null);
    $scopeHeading = is_string($model['scope_label'] ?? null) ? $model['scope_label'] : '';
    $scopeNotice = is_string($model['scope_notice'] ?? null) ? $model['scope_notice'] : '';
    $notices = is_array($model['notices'] ?? null) ? $model['notices'] : [];
    $interactions = is_array($model['interactions'] ?? null) ? $model['interactions'] : [];
    $records = is_array($model['records'] ?? null) ? $model['records'] : [];

    ?>
<!doctype html>
<html lang="en" data-theme="<?= $escape($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mind Poisoning — Part of the Drama-Llama world — Interactions and Logs</title>
    <link rel="stylesheet" href="dashboard.css">
    <script defer src="dashboard.js"></script>
</head>
<body>
<div class="dashboard-shell">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="poster" aria-labelledby="dashboard-title">
        <div class="poster-overline">
            <p>CHIM <span aria-hidden="true">/</span> SERVER PLUGIN</p>
            <nav class="theme-control" aria-label="Color theme">
                <span>Appearance</span>
                <?php foreach (['day' => 'Day', 'night' => 'Night'] as $themeValue => $themeLabel): ?>
                    <?php if ($theme === $themeValue): ?>
                        <span class="theme-current" aria-current="page"><?= $escape($themeLabel) ?></span>
                    <?php else: ?>
                        <a href="<?= $escape($queryUrl($tab, false, true, $themeValue)) ?>"><?= $escape($themeLabel) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
        <h1 class="poster-title" id="dashboard-title"><span>MIND</span><span class="title-rust">POISONING</span></h1>
        <p class="poster-world">Part of the Drama-Llama world</p>
        <figure class="poster-illustration">
            <img src="dashboard-art.webp" width="1536" height="1024" alt="Two travelers whisper together on a snowy mountain pass while a small llama watches from the distant right slope." fetchpriority="high" decoding="async">
            <figcaption>Someone had something to say about you.</figcaption>
        </figure>
        <div class="poster-imprint">
            <span>A LISTENER’S AFFINITY RECORD</span>
            <span>VERSION <?= $escape($version) ?> <span aria-hidden="true">/</span> UPDATED <span data-dashboard-refresh-region="last-updated"><time id="dashboard-last-updated" datetime="<?= $escape($generatedAt) ?>"><?= $escape($generatedAt) ?></time></span></span>
        </div>
        <nav class="printed-tabs" aria-label="Journal sections">
            <a href="<?= $escape($queryUrl('interactions')) ?>"<?= $tab === 'interactions' ? ' aria-current="page"' : '' ?>>Interactions</a>
            <a href="<?= $escape($queryUrl('diagnostics')) ?>"<?= $tab === 'diagnostics' ? ' aria-current="page"' : '' ?>>Logs</a>
        </nav>
    </header>

    <section class="source-strip" aria-label="Data source status">
        <p>Sources</p>
        <dl>
            <div><dt>Plugin log</dt><dd class="<?= $escape($sourceClass($source['logs'] ?? null)) ?>" data-dashboard-refresh-region="logs-source"><?= $escape($text($source['logs'] ?? null)) ?></dd></div>
            <div><dt>Affinity data</dt><dd class="<?= $escape($sourceClass($source['database'] ?? null)) ?>" data-dashboard-refresh-region="database-source"><?= $escape($text($source['database'] ?? null)) ?></dd></div>
        </dl>
    </section>

    <div class="refresh-controls" data-dashboard-refresh-controls hidden>
        <button id="dashboard-refresh-toggle" type="button" aria-pressed="false">Pause updates</button>
        <p id="dashboard-refresh-status" role="status" aria-live="polite">Automatic updates every 5 seconds.</p>
    </div>

    <div data-dashboard-refresh-region="notices">
    <?php if ($scopeHeading !== ''): ?>
        <p class="notice notice--limited" role="status">Scope: <strong><?= $escape($scopeHeading) ?></strong><?php if ($scopeNotice !== ''): ?>. <?= $escape($scopeNotice) ?><?php endif; ?></p>
    <?php endif; ?>
    <?php if (($source['limited'] ?? false) === true): ?>
        <p class="notice notice--limited" role="status">The available records are limited. Older log entries may have been truncated or rotated.</p>
    <?php endif; ?>
    <?php if ($databaseUnavailable): ?>
        <p class="notice notice--limited" role="status">Showing log-only history. Current affinity is unavailable because the database source could not be read. <a href="<?= $escape($queryUrl('interactions')) ?>">Retry</a></p>
    <?php endif; ?>

    <?php if ($notices !== []): ?>
        <ul class="notice-list" aria-label="Dashboard notices">
            <?php foreach ($notices as $notice): ?>
                <?php if (is_string($notice) && trim($notice) !== ''): ?>
                    <li><?= $escape($notice) ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    </div>

    <main id="main-content">
        <?php if ($tab === 'interactions'): ?>
            <section aria-labelledby="interactions-heading">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Recorded decisions</p>
                        <h2 id="interactions-heading">Interactions</h2>
                    </div>
                    <p class="section-note">A proposal is not an applied change. Current affinity is read separately.</p>
                </div>

                <form class="filter-form" method="get" action="dashboard.php">
                    <input type="hidden" name="tab" value="interactions">
                    <input type="hidden" name="theme" value="<?= $escape($theme) ?>">
                    <div class="filter-field filter-field--search">
                        <label for="interaction-q">Find an actor, subject, or ID</label>
                        <input id="interaction-q" name="q" type="search" maxlength="120" value="<?= $escape($q) ?>">
                    </div>
                    <div class="filter-field">
                        <label for="interaction-outcome">Outcome</label>
                        <select id="interaction-outcome" name="outcome">
                            <option value="">All outcomes</option>
                            <?php foreach ($outcomeOptions as $value => $label): ?>
                                <option value="<?= $escape($value) ?>"<?= $outcome === $value ? ' selected' : '' ?>><?= $escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Apply filters</button>
                    <?php if ($q !== '' || $outcome !== ''): ?>
                        <a class="clear-link" href="<?= $escape($queryUrl('interactions', false, false)) ?>">Clear filters</a>
                    <?php endif; ?>
                </form>

                <div data-dashboard-refresh-region="interactions">
                <?php if ($interactions === []): ?>
                    <div class="empty-state" role="status">
                        <h3><?= $q !== '' || $outcome !== '' ? 'No matching interactions' : ($databaseUnavailable ? 'No log-only interactions available' : 'No recorded interactions') ?></h3>
                        <p><?= $q !== '' || $outcome !== '' ? 'Try another filter or clear the current filters.' : ($databaseUnavailable ? 'The database history is unavailable, and no matching decisions were recovered from the log.' : 'When the source contains recorded decisions, they will appear here.') ?></p>
                        <?php if ($q !== '' || $outcome !== ''): ?>
                            <a href="<?= $escape($queryUrl('interactions', false, false)) ?>">Clear filters</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="interaction-ledger">
                        <?php $lastDay = null; ?>
                        <?php foreach ($interactions as $interaction): ?>
                            <?php if (!is_array($interaction)) { continue; } ?>
                            <?php
                            $timestamp = $text($interaction['timestamp'] ?? null);
                            $day = preg_match('/\A\d{4}-\d{2}-\d{2}/', $timestamp) === 1 ? substr($timestamp, 0, 10) : 'Date unavailable';
                            $eventOutcome = $text($interaction['outcome'] ?? null);
                            $correlationLabel = is_string($interaction['utterance_id'] ?? null) && str_starts_with($interaction['utterance_id'], 'input_')
                                ? 'Input event'
                                : 'Utterance';
                            $attribution = strtolower(is_string($interaction['attribution'] ?? null) ? $interaction['attribution'] : '');
                            $attributionLabel = match ($attribution) {
                                'active' => 'Active history',
                                'shared' => 'Shared server history',
                                'unverified' => 'Unverified history',
                                'unattributed' => 'Unattributed log record',
                                default => 'Attribution unavailable',
                            };
                            $playthroughId = is_string($interaction['playthrough_id'] ?? null) ? $interaction['playthrough_id'] : '';
                            $scopeTerm = $playthroughId === 'unprofiled' ? 'Scope' : 'Playthrough';
                            $scopeValue = $playthroughId === 'unprofiled' ? 'Shared server' : $playthroughId;
                            $changes = is_array($interaction['changes'] ?? null) ? $interaction['changes'] : [];
                            $typeDecisions = is_array($interaction['type_decisions'] ?? null) ? $interaction['type_decisions'] : [];
                            $typeChanges = is_array($interaction['type_changes'] ?? null) ? $interaction['type_changes'] : [];
                            ?>
                            <?php if ($day !== $lastDay): ?>
                                <h3 class="day-heading"><?= $escape($day) ?></h3>
                                <?php $lastDay = $day; ?>
                            <?php endif; ?>
                            <article class="interaction-entry">
                                <header class="interaction-header">
                                    <div>
                                        <p class="interaction-time"><?= $escape($timestamp) ?></p>
                                        <?php if (($interaction['source_kind'] ?? null) === 'reflection'): ?>
                                            <h4><?= $escape($text($interaction['opinion_owner'] ?? $interaction['speaker'] ?? null, 'Unknown NPC')) ?> · Solo reflection</h4>
                                        <?php else: ?>
                                            <h4><?= $escape($text($interaction['speaker'] ?? null)) ?><span class="exchange-arrow" aria-hidden="true">→</span><span class="visually-hidden"> to </span><?= $escape($text($interaction['listener'] ?? null)) ?></h4>
                                            <?php if (($interaction['listener_role'] ?? null) === 'addressed'): ?>
                                                <p class="exposure-label">Addressed listener</p>
                                            <?php elseif (($interaction['listener_role'] ?? null) === 'overheard'): ?>
                                                <p class="exposure-label">Roster-listed overhearer evaluation · addressed to <?= $escape($text($interaction['addressed_listener'] ?? null)) ?>; playback is not verified</p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <span class="status <?= $escape($outcomeClass($eventOutcome)) ?>"><?= $escape($outcomeLabel($eventOutcome)) ?></span>
                                </header>
                                <p class="event-identifiers">
                                    <span>Event <code><?= $escape($text($interaction['event_id'] ?? null)) ?></code></span>
                                    <span><?= $escape($correlationLabel) ?> <code><?= $escape($text($interaction['utterance_id'] ?? null)) ?></code></span>
                                    <span>Request <code><?= $escape($text($interaction['request_id'] ?? null)) ?></code></span>
                                    <span><?= $escape($scopeTerm) ?> <code><?= $escape($text($scopeValue)) ?></code></span>
                                    <span class="attribution-label"><?= $escape($attributionLabel) ?></span>
                                </p>
                                <?php if (is_string($interaction['reason'] ?? null) && $interaction['reason'] !== ''): ?>
                                    <p class="reason-line">Reason <code><?= $escape($interaction['reason']) ?></code></p>
                                <?php endif; ?>

                                <?php if ($changes === []): ?>
                                    <p class="no-subjects">No affinity changes were recorded for this interaction.</p>
                                <?php else: ?>
                                    <div class="change-list" aria-label="Subject affinity values">
                                        <?php foreach ($changes as $change): ?>
                                            <?php if (!is_array($change)) { continue; } ?>
                                            <?php
                                                $subject = $text($change['label'] ?? null, $text($change['subject'] ?? null));
                                                $proposed = $formatNumber($change['proposed'] ?? null, true);
                                                $applied = $formatNumber($change['applied'] ?? null, true);
                                            if ($applied === null) {
                                                $applied = in_array(strtolower($eventOutcome), ['unconfirmed', 'commit-unconfirmed', 'commit_unconfirmed'], true)
                                                    ? 'Unconfirmed'
                                                    : (in_array(strtolower($eventOutcome), ['skipped', 'rejected', 'ignored'], true) ? 'Not applied' : 'Unavailable');
                                            }
                                            $before = $formatNumber($change['before'] ?? null);
                                            $after = $formatNumber($change['after'] ?? null);
                                            $transitionUnavailable = $before === null && $after === null;
                                            $currentState = strtolower(is_string($change['current_state'] ?? null) ? $change['current_state'] : '');
                                            $current = match (true) {
                                                $databaseUnavailable => 'Unavailable',
                                                $currentState === 'set' => $formatNumber($change['current'] ?? null) ?? 'Unavailable',
                                                $currentState === 'unset_default_zero' => '0 (default)',
                                                $currentState === 'ambiguous' => 'Ambiguous',
                                                $currentState === 'invalid' => 'Invalid',
                                                default => 'Unavailable',
                                            };
                                            $typeEventState = strtolower(is_string($change['type_event_state'] ?? null) ? $change['type_event_state'] : '');
                                            $typeBefore = is_string($change['type_before'] ?? null) ? $change['type_before'] : null;
                                            $typeAfter = is_string($change['type_after'] ?? null) ? $change['type_after'] : null;
                                            ?>
                                            <dl class="change-row">
                                                <div class="change-subject"><dt>Subject</dt><dd><?= $escape($subject) ?></dd></div>
                                                <div><dt>Proposed</dt><dd class="numeric"><?= $escape($proposed ?? 'Unavailable') ?></dd></div>
                                                <div><dt>Applied</dt><dd class="numeric"><?= $escape($applied) ?></dd></div>
                                                <div class="change-transition">
                                                    <dt>At event (before to after)</dt>
                                                    <dd class="numeric"><?php if ($transitionUnavailable): ?>Unavailable<?php else: ?><span>Before <?= $escape($before ?? 'Unavailable') ?></span><span class="transition-arrow" aria-hidden="true">→</span><span>After <?= $escape($after ?? 'Unavailable') ?></span><?php endif; ?></dd>
                                                    <dt>Type at event</dt>
                                                    <dd><?php if ($typeEventState === 'set' && $typeBefore !== null && $typeAfter !== null): ?><?= $escape($typeBefore) ?><span class="transition-arrow" aria-hidden="true">→</span><?= $escape($typeAfter) ?><?php elseif ($typeEventState === 'unconfirmed'): ?>Unconfirmed<?php else: ?>Unavailable<?php endif; ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Current</dt>
                                                    <dd class="numeric"><?= $escape($current) ?></dd>
                                                    <dt>Current type</dt>
                                                    <dd><?= $currentTypeDisplay($change['current_type'] ?? null, $change['current_type_state'] ?? null) ?></dd>
                                                </div>
                                            </dl>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($typeDecisions !== [] || $typeChanges !== []): ?>
                                    <div class="change-list" aria-label="Relationship type decisions and transitions">
                                        <h5>Relationship types</h5>
                                        <?php foreach ($typeDecisions as $decision): ?>
                                            <?php if (!is_array($decision)) { continue; } ?>
                                            <?php
                                                $subject = $text($decision['label'] ?? null, $text($decision['subject'] ?? null));
                                                $from = $text($decision['from'] ?? null);
                                                $to = $text($decision['to'] ?? null);
                                                $reason = $decision['reason'] ?? null;
                                                $reasonLabel = match ($reason) {
                                                    'change_eligible' => 'Change eligible',
                                                    'same_type' => 'Same type',
                                                    'protected_type_preserved' => 'Protected type preserved',
                                                    'custom_type_preserved' => 'Custom type preserved',
                                                    default => 'Proposal recorded',
                                                };
                                                $retainedType = in_array($reason, ['same_type', 'protected_type_preserved', 'custom_type_preserved'], true) ? $from : 'Unavailable';
                                            ?>
                                            <dl class="change-row">
                                                <div class="change-subject"><dt>Subject</dt><dd><?= $escape($subject) ?></dd></div>
                                                <div><dt>Proposed type change</dt><dd><?= $escape($from) ?><span class="transition-arrow" aria-hidden="true">→</span><?= $escape($to) ?></dd></div>
                                                <div><dt>Decision</dt><dd><?= $escape($reasonLabel) ?></dd></div>
                                                <div><dt>Retained type</dt><dd><?= $escape($retainedType) ?></dd></div>
                                                <div><dt>Current type</dt><dd><?= $currentTypeDisplay($decision['current_type'] ?? null, $decision['current_type_state'] ?? null) ?></dd></div>
                                            </dl>
                                        <?php endforeach; ?>
                                        <?php foreach ($typeChanges as $typeChange): ?>
                                            <?php if (!is_array($typeChange)) { continue; } ?>
                                            <?php
                                                $subject = $text($typeChange['label'] ?? null, $text($typeChange['subject'] ?? null));
                                                $beforeType = $text($typeChange['before'] ?? null);
                                                $afterType = $text($typeChange['after'] ?? null);
                                            ?>
                                            <dl class="change-row">
                                                <div class="change-subject"><dt>Subject</dt><dd><?= $escape($subject) ?></dd></div>
                                                <div><dt>Applied type transition</dt><dd><?= $escape($beforeType) ?><span class="transition-arrow" aria-hidden="true">→</span><?= $escape($afterType) ?></dd></div>
                                                <div><dt>Current type</dt><dd><?= $currentTypeDisplay($typeChange['current_type'] ?? null, $typeChange['current_type_state'] ?? null) ?></dd></div>
                                            </dl>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <p class="timing-line">
                                    Model <span class="numeric"><?php $modelTime = $formatNumber($interaction['model_ms'] ?? null); ?><?= $escape($modelTime ?? 'Unavailable') ?><?= $modelTime === null ? '' : ' ms' ?></span>
                                    <span aria-hidden="true">·</span>
                                    Persistence <span class="numeric"><?php $persistenceTime = $formatNumber($interaction['persistence_ms'] ?? null); ?><?= $escape($persistenceTime ?? 'Unavailable') ?><?= $persistenceTime === null ? '' : ' ms' ?></span>
                                    <span aria-hidden="true">·</span>
                                    <?= $databaseUnavailable ? 'Database source unavailable at' : 'Current values queried at' ?> <?= $escape($generatedAt) ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <section aria-labelledby="logs-heading">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Plugin record trace</p>
                        <h2 id="logs-heading">Logs</h2>
                    </div>
                    <div class="download-slot" data-dashboard-refresh-region="download">
                        <?php if (!$logsUnavailable && $records !== []): ?>
                            <a class="download-link" href="<?= $escape($queryUrl('diagnostics', true)) ?>">Download filtered plugin log</a>
                        <?php else: ?>
                            <span class="download-unavailable" aria-disabled="true"><?= $logsUnavailable ? 'Plugin log download unavailable' : 'No matching log records to download' ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <form class="filter-form" method="get" action="dashboard.php">
                    <input type="hidden" name="tab" value="diagnostics">
                    <input type="hidden" name="theme" value="<?= $escape($theme) ?>">
                    <div class="filter-field filter-field--search">
                        <label for="diagnostic-q">Find a request, utterance, or reason</label>
                        <input id="diagnostic-q" name="q" type="search" maxlength="120" value="<?= $escape($q) ?>">
                    </div>
                    <div class="filter-field">
                        <label for="diagnostic-outcome">Outcome</label>
                        <select id="diagnostic-outcome" name="outcome">
                            <option value="">All outcomes</option>
                            <?php foreach ($outcomeOptions as $value => $label): ?>
                                <option value="<?= $escape($value) ?>"<?= $outcome === $value ? ' selected' : '' ?>><?= $escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label for="diagnostic-level">Level</label>
                        <select id="diagnostic-level" name="level">
                            <option value="">All levels</option>
                            <?php foreach ($levelOptions as $value => $label): ?>
                                <option value="<?= $escape($value) ?>"<?= $level === $value ? ' selected' : '' ?>><?= $escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Apply filters</button>
                    <?php if ($q !== '' || $outcome !== '' || $level !== ''): ?>
                        <a class="clear-link" href="<?= $escape($queryUrl('diagnostics', false, false)) ?>">Clear filters</a>
                    <?php endif; ?>
                </form>

                <div data-dashboard-refresh-region="diagnostics">
                <?php if ($logsUnavailable): ?>
                    <div class="empty-state" role="status">
                        <h3>Plugin log unavailable</h3>
                        <p>The log source could not be read. No download is available from this view.</p>
                        <a href="<?= $escape($queryUrl('diagnostics', false, false)) ?>">Retry</a>
                    </div>
                <?php elseif ($records === []): ?>
                    <div class="empty-state" role="status">
                        <h3><?= $q !== '' || $outcome !== '' || $level !== '' ? 'No matching records' : 'No diagnostic records' ?></h3>
                        <p><?= $q !== '' || $outcome !== '' || $level !== '' ? 'Try another filter or clear the current filters.' : 'The log source returned no plugin records.' ?></p>
                        <?php if ($q !== '' || $outcome !== '' || $level !== ''): ?>
                            <a href="<?= $escape($queryUrl('diagnostics', false, false)) ?>">Clear filters</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="record-list">
                        <?php foreach ($records as $record): ?>
                            <?php if (!is_array($record)) { continue; } ?>
                            <?php
                            $recordLevel = $text($record['level'] ?? null);
                            $recordEvent = $text($record['event'] ?? null);
                            $correlationLabel = is_string($record['utterance_id'] ?? null) && str_starts_with($record['utterance_id'], 'input_')
                                ? 'Input event ID'
                                : 'Utterance ID';
                            $safeRecord = array_intersect_key($record, $safeLogFields);
                            $recordJson = json_encode(
                                $safeRecord,
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                            );
                            $recordKey = hash('sha256', is_string($recordJson) ? $recordJson : '{}');
                            ?>
                            <details class="diagnostic-record" data-refresh-key="<?= $escape($recordKey) ?>">
                                <summary>
                                    <span class="record-time"><?= $escape($text($record['timestamp'] ?? null)) ?></span>
                                    <span class="status <?= $escape($levelClass($recordLevel)) ?>"><?= $escape($codeLabel($recordLevel)) ?></span>
                                    <span class="record-event"><?= $escape($codeLabel($recordEvent)) ?></span>
                                    <span class="record-reason"><?= $escape($text($record['reason'] ?? null)) ?></span>
                                </summary>
                                <dl class="record-details">
                                    <div><dt>Request ID</dt><dd><code><?= $escape($text($record['request_id'] ?? null)) ?></code></dd></div>
                                    <div><dt><?= $escape($correlationLabel) ?></dt><dd><code><?= $escape($text($record['utterance_id'] ?? null)) ?></code></dd></div>
                                    <div><dt>Stage</dt><dd><?= $escape($text($record['stage'] ?? null)) ?></dd></div>
                                    <div><dt>Outcome</dt><dd><?= $escape($text($record['outcome'] ?? $record['model_outcome'] ?? $record['persistence_outcome'] ?? null)) ?></dd></div>
                                    <div><dt>Reason</dt><dd><code><?= $escape($text($record['reason'] ?? $record['persistence_reason'] ?? null)) ?></code></dd></div>
                                    <?php foreach (['Elapsed' => 'elapsed_ms', 'Model' => 'model_ms', 'Persistence' => 'persistence_ms'] as $timeLabel => $timeField): ?>
                                        <?php $recordTime = $formatNumber($record[$timeField] ?? null); ?>
                                        <div><dt><?= $escape($timeLabel) ?></dt><dd class="numeric"><?= $escape($recordTime ?? 'Unavailable') ?><?= $recordTime === null ? '' : ' ms' ?></dd></div>
                                    <?php endforeach; ?>
                                    <div><dt>Commit state</dt><dd><?= $escape($text($record['commit_state'] ?? null)) ?></dd></div>
                                </dl>
                                <pre class="record-json"><code><?= $escape(is_string($recordJson) ? $recordJson : '{}') ?></code></pre>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer class="page-footer">
        <p>Current affinity is read separately from each event’s history; diagnostic logs may be incomplete or truncated.</p>
    </footer>
</div>
</body>
</html>
    <?php
}
