<?php
declare(strict_types=1);

use function ChimMindPoisoning\renderDashboard;

require_once __DIR__ . '/../server/dashboard_view.php';

function dashboardRestorePolicyAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dashboardRestorePolicyRender(array $model, array $filters): string
{
    ob_start();
    renderDashboard($model, $filters);
    return (string)ob_get_clean();
}

function dashboardRestorePolicyExplanations(string $html): array
{
    preg_match_all('/<p class="reason-explanation">([\s\S]*?)<\/p>/', $html, $matches);
    return array_map(
        static fn(string $text): string => html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        $matches[1]
    );
}

$interactions = [
    [
        'timestamp' => '2026-10-18T16:40:07Z',
        'event_id' => '42',
        'utterance_id' => 'utt_restore01',
        'request_id' => 'restore-primary',
        'playthrough_id' => '17',
        'speaker' => 'Speaker',
        'listener' => 'Listener',
        'outcome' => 'skipped',
        'reason' => 'restore-policy',
        'changes' => [],
    ],
    [
        'timestamp' => '2026-10-18T16:41:07Z',
        'event_id' => '43',
        'utterance_id' => 'utt_other01',
        'request_id' => 'other-reason',
        'playthrough_id' => '17',
        'speaker' => 'Speaker',
        'listener' => 'Listener',
        'outcome' => 'skipped',
        'reason' => '<custom_reason>',
        'changes' => [],
    ],
];
$records = [
    [
        'timestamp' => '2026-10-18T16:40:07Z',
        'request_id' => 'restore-primary',
        'level' => 'info',
        'event' => 'request_finished',
        'stage' => 'preflight',
        'outcome' => 'skipped',
        'reason' => 'restore-policy',
        'model_outcome' => 'not_called',
    ],
    [
        'timestamp' => '2026-10-18T16:40:08Z',
        'request_id' => 'restore-persistence',
        'level' => 'warning',
        'event' => 'request_finished',
        'stage' => 'persistence',
        'outcome' => 'failed',
        'model_outcome' => 'validated',
        'persistence_outcome' => 'failed',
        'persistence_reason' => 'restore-policy',
    ],
    [
        'timestamp' => '2026-10-18T16:41:07Z',
        'request_id' => 'other-reason',
        'level' => 'error',
        'event' => 'request_finished',
        'stage' => 'persistence',
        'outcome' => 'failed',
        'reason' => '<custom_reason>',
    ],
];
$model = [
    'version' => '0.1.23',
    'generated_at' => '2026-10-18T16:42:00Z',
    'source' => ['logs' => 'available', 'database' => 'available', 'limited' => false],
    'interactions' => $interactions,
    'records' => $records,
];
$originalModel = $model;
$interactionHtml = dashboardRestorePolicyRender($model, ['tab' => 'interactions']);
$interactionExplanations = dashboardRestorePolicyExplanations($interactionHtml);
dashboardRestorePolicyAssert(
    count($interactionExplanations) === 1
        && str_contains($interactionHtml, '<code>restore-policy</code>')
        && str_contains($interactionHtml, '<code>&lt;custom_reason&gt;</code>')
        && !str_contains($interactionHtml, '<custom_reason>'),
    'Interactions must explain the historical restore-policy reason, preserve its raw code, and keep other reasons escaped.'
);

$filters = ['tab' => 'diagnostics', 'q' => 'restore-policy', 'outcome' => 'failed', 'level' => 'warning', 'theme' => 'night'];
$originalFilters = $filters;
$logsHtml = dashboardRestorePolicyRender($model, $filters);
$logExplanations = dashboardRestorePolicyExplanations($logsHtml);
dashboardRestorePolicyAssert(
    count($logExplanations) === 2
        && $interactionExplanations[0] === $logExplanations[0]
        && $logExplanations[0] === $logExplanations[1]
        && substr_count($logsHtml, '<code>restore-policy</code>') === 2
        && str_contains($logsHtml, '<code>&lt;custom_reason&gt;</code>')
        && str_contains($logsHtml, '<span class="status status--success">Info</span>')
        && str_contains($logsHtml, '<span class="status status--warning">Warning</span>')
        && str_contains($logsHtml, '<span class="status status--error">Error</span>')
        && str_contains($logsHtml, 'dashboard.php?tab=diagnostics&amp;theme=night&amp;q=restore-policy&amp;outcome=failed&amp;level=warning&amp;download=1'),
    'Logs must explain both primary and persistence-only restore reasons while retaining status, level, escaping, and filtered export.'
);
$explanation = $interactionExplanations[0];
$hasHistoricalScope = str_contains($explanation, 'At this recorded event')
    && str_contains($explanation, 'does not establish the setting’s current value');
$hasRestoreHistoryRisk = str_contains($explanation, 'newer relationship scores')
    && str_contains($explanation, 'older Mind Poisoning processing history')
    && str_contains($explanation, 'change a score again');
$hasOperatorGuidance = str_contains($explanation, 'Review the restore policy with the server administrator')
    && str_contains($explanation, 'back up the database and configuration')
    && str_contains($explanation, 'before any setting change')
    && str_contains($explanation, 'do not toggle it blindly');
$hasModelStageCaveat = str_contains($explanation, 'before model work')
    && str_contains($explanation, 'after validated model output');
dashboardRestorePolicyAssert(
    str_contains($explanation, 'NEVER_CLEAR_RELATIONSHIP_DATA=true')
        && $hasHistoricalScope
        && $hasRestoreHistoryRisk
        && $hasOperatorGuidance
        && $hasModelStageCaveat,
    'The explanation must state the historical setting, restore-history risk, operator/backups guidance, and both possible model stages.'
);

preg_match_all('/<pre class="record-json"><code>([\s\S]*?)<\/code><\/pre>/', $logsHtml, $jsonMatches);
dashboardRestorePolicyAssert(count($jsonMatches[1] ?? []) === count($records), 'Every log record must retain its raw JSON view.');
foreach ($records as $index => $record) {
    $decodedRecord = json_decode(
        html_entity_decode($jsonMatches[1][$index], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    dashboardRestorePolicyAssert($decodedRecord === $record, 'The explanation must not alter log JSON fields or reason codes.');
}
dashboardRestorePolicyAssert($model === $originalModel && $filters === $originalFilters, 'Rendering must leave its model and filters unchanged.');

echo "dashboard restore-policy render checks passed\n";
