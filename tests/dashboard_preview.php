<?php
declare(strict_types=1);

use function ChimMindPoisoning\renderDashboard;

require_once __DIR__ . '/../server/dashboard_view.php';

function dashboardPreviewAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dashboardPreviewModel(string $mode = 'default'): array
{
    $interactions = [
        [
            'request_id' => '0123456789abcdef01234567',
            'event_id' => '1042',
            'utterance_id' => 'utt_7c91a4b2',
            'playthrough_id' => '482910',
            'timestamp' => '2026-09-27T16:14:32Z',
            'speaker' => 'Aerin Vale',
            'listener' => 'Mira Thorne',
            'outcome' => 'committed',
            'attribution' => 'active',
            'model_ms' => 842.6,
            'persistence_ms' => 18.2,
            'changes' => [[
                'subject' => 'npc:310',
                'label' => 'Rowan Black',
                'proposed' => -2.0,
                'before' => 28.63,
                'after' => 26.63,
                'applied' => -2.0,
                'current' => 26.63,
                'current_state' => 'set',
            ]],
        ],
        [
            'request_id' => 'aabbccddeeff001122334455',
            'event_id' => '1039',
            'utterance_id' => 'utt_91c28f34',
            'playthrough_id' => '482910',
            'timestamp' => '2026-09-26T20:12:47Z',
            'speaker' => 'Aerin Vale',
            'listener' => 'Mira Thorne',
            'outcome' => 'commit-unconfirmed',
            'attribution' => 'active',
            'reason' => 'Commit acknowledgement was interrupted.',
            'model_ms' => 734.8,
            'persistence_ms' => null,
            'changes' => [[
                'subject' => 'npc:310',
                'label' => 'Rowan Black',
                'proposed' => 1,
                'before' => null,
                'after' => null,
                'applied' => null,
                'current' => 26.63,
                'current_state' => 'set',
            ]],
        ],
        [
            'request_id' => '112233445566778899aabbcc',
            'event_id' => '1041',
            'utterance_id' => 'utt_118db74c',
            'playthrough_id' => '482910',
            'timestamp' => '2026-09-27T15:58:06Z',
            'speaker' => 'Aerin Vale',
            'listener' => 'Mira Thorne',
            'outcome' => 'zero-change',
            'attribution' => 'active',
            'model_ms' => 791.0,
            'persistence_ms' => 16.4,
            'changes' => [[
                'subject' => 'npc:310',
                'label' => 'Rowan Black',
                'proposed' => 0,
                'before' => 29,
                'after' => 29,
                'applied' => 0,
                'current' => 26.63,
                'current_state' => 'set',
            ]],
        ],
        [
            'request_id' => '2233445566778899aabbccdd',
            'event_id' => '1040',
            'utterance_id' => 'utt_0099aa88',
            'playthrough_id' => '482910',
            'timestamp' => '2026-09-26T21:40:11Z',
            'speaker' => 'Aerin Vale',
            'listener' => 'Mira Thorne',
            'outcome' => 'skipped',
            'attribution' => 'active',
            'reason' => 'No eligible subject judgment.',
            'model_ms' => null,
            'persistence_ms' => null,
            'changes' => [[
                'subject' => 'npc:311',
                'label' => 'Tarin Oakhill',
                'proposed' => null,
                'before' => null,
                'after' => null,
                'applied' => null,
                'current' => null,
                'current_state' => 'unset_default_zero',
            ]],
        ],
    ];
    $records = [
        [
            'timestamp' => '2026-09-27T16:14:32Z',
            'level' => 'info',
            'event' => 'request_finished',
            'stage' => 'request',
            'request_id' => '0123456789abcdef01234567',
            'utterance_id' => 'utt_7c91a4b2',
            'outcome' => 'committed',
            'commit_state' => 'confirmed',
            'committed' => true,
            'cleanup_failed' => false,
            'changes' => [['subject' => 'npc:310', 'delta' => -2, 'before' => 28.63, 'after' => 26.63]],
            'elapsed_ms' => 865.8,
            'model_ms' => 842.6,
            'persistence_ms' => 18.2,
        ],
        [
            'timestamp' => '2026-09-27T16:14:31Z',
            'level' => 'debug',
            'event' => 'model_started',
            'stage' => 'model',
            'request_id' => '0123456789abcdef01234567',
            'utterance_id' => 'utt_7c91a4b2',
            'outcome' => 'pending',
            'reason' => 'validated_subjects',
            'elapsed_ms' => 0.0,
        ],
        [
            'timestamp' => '2026-09-27T15:58:06Z',
            'level' => 'warning',
            'event' => 'persistence_finished',
            'stage' => 'persistence',
            'request_id' => '112233445566778899aabbcc',
            'utterance_id' => 'utt_118db74c',
            'outcome' => 'committed',
            'commit_state' => 'confirmed',
            'committed' => true,
            'cleanup_failed' => true,
            'reason' => 'cleanup_failed',
            'elapsed_ms' => 807.4,
            'model_ms' => 791.0,
            'persistence_ms' => 16.4,
        ],
    ];

    $source = ['logs' => 'available', 'database' => 'available', 'limited' => false];
    if ($mode === 'empty') {
        $interactions = [];
        $records = [];
    } elseif ($mode === 'database-unavailable') {
        $source['database'] = 'unavailable';
        foreach ($interactions as &$interaction) {
            $interaction['attribution'] = 'unverified';
            foreach ($interaction['changes'] as &$change) {
                $change['current'] = null;
                $change['current_state'] = 'unavailable';
            }
            unset($change);
        }
        unset($interaction);
    } elseif ($mode === 'logs-unavailable') {
        $source['logs'] = 'unavailable';
        $records = [];
    }
    usort($interactions, static fn(array $left, array $right): int => strcmp($right['timestamp'], $left['timestamp']));

    return [
        'version' => 'fixture only',
        'generated_at' => gmdate('Y-m-d\\TH:i:s\\Z'),
        'notices' => ['PREVIEW DATA — synthetic records only; this page reads no live CHIM services.'],
        'source' => $source,
        'interactions' => $interactions,
        'records' => $records,
    ];
}

function dashboardPreviewFilters(array $query): array
{
    $tab = ($query['tab'] ?? null) === 'diagnostics' ? 'diagnostics' : 'interactions';
    $theme = ($query['theme'] ?? null) === 'night' ? 'night' : 'day';
    $q = is_string($query['q'] ?? null) ? $query['q'] : '';
    if (preg_match_all('/./us', $q, $matches) === false || count($matches[0]) > 120) {
        $q = '';
    }
    $outcomes = ['committed', 'zero-change', 'unconfirmed', 'skipped', 'rejected', 'failed'];
    $levels = ['info', 'warning', 'error', 'debug'];
    $outcome = is_string($query['outcome'] ?? null) && in_array($query['outcome'], $outcomes, true)
        ? $query['outcome']
        : '';
    $level = is_string($query['level'] ?? null) && in_array($query['level'], $levels, true)
        ? $query['level']
        : '';
    return ['tab' => $tab, 'q' => $q, 'outcome' => $outcome, 'level' => $level, 'theme' => $theme];
}

function dashboardPreviewContains(array $row, string $query): bool
{
    if ($query === '') {
        return true;
    }
    $encoded = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($encoded) && stripos($encoded, $query) !== false;
}

function dashboardPreviewFiltered(array $model, array $filters): array
{
    $collection = ($filters['tab'] ?? 'interactions') === 'diagnostics' ? 'records' : 'interactions';
    $rows = is_array($model[$collection] ?? null) ? $model[$collection] : [];
    $rows = array_filter($rows, static function (mixed $row) use ($filters, $collection): bool {
        if (!is_array($row) || !dashboardPreviewContains($row, $filters['q'] ?? '')) {
            return false;
        }
        if (($filters['outcome'] ?? '') !== '') {
            $rowOutcome = ($row['outcome'] ?? null) === 'commit-unconfirmed' ? 'unconfirmed' : ($row['outcome'] ?? null);
            if ($rowOutcome !== $filters['outcome']) {
                return false;
            }
        }
        return $collection !== 'records' || ($filters['level'] ?? '') === '' || ($row['level'] ?? null) === $filters['level'];
    });
    $model[$collection] = array_values($rows);
    return $model;
}

function dashboardPreviewSelfTest(): void
{
    ob_start();
    require_once __DIR__ . '/../server/dashboard_view.php';
    $includeOutput = ob_get_clean();
    dashboardPreviewAssert($includeOutput === '', 'Including the view produced output.');

    $model = dashboardPreviewModel();
    $model['version'] = '1.2"><script>alert(1)</script>';
    $model['notices'][] = '<img src=x onerror=alert(1)>';
    $model['interactions'][0]['speaker'] = '<script>alert("x")</script>';
    ob_start();
    renderDashboard($model, ['tab' => 'interactions', 'q' => 'Éowyn & Mira', 'outcome' => 'committed', 'theme' => 'night']);
    $html = ob_get_clean();
    dashboardPreviewAssert(is_string($html) && str_starts_with($html, '<!doctype html>'), 'Renderer did not return a complete document.');
    dashboardPreviewAssert(str_contains($html, '<html lang="en" data-theme="night">'), 'Explicit night mode was not rendered.');
    dashboardPreviewAssert(str_contains($html, '<h1 class="poster-title" id="dashboard-title"><span>MIND</span><span class="title-rust">POISONING</span></h1>'), 'Selectable poster heading is missing.');
    dashboardPreviewAssert(str_contains($html, 'src="dashboard-art.webp"'), 'Poster artwork is not served from the plugin directory.');
    dashboardPreviewAssert(preg_match('/<script\b(?=[^>]*\bsrc="dashboard\.js")(?=[^>]*\bdefer\b)[^>]*><\/script>/', $html) === 1, 'External auto-refresh script is missing.');
    dashboardPreviewAssert(str_contains($html, 'PREVIEW DATA — synthetic records only'), 'Synthetic fixture is not labeled.');
    dashboardPreviewAssert(str_contains($html, 'href="#main-content">Skip to content</a>'), 'Skip link is missing.');
    dashboardPreviewAssert(str_contains($html, 'aria-current="page">Interactions</a>'), 'Active Interactions tab state is missing.');
    dashboardPreviewAssert(str_contains($html, '>Logs</a>'), 'Printed Logs tab is missing.');
    dashboardPreviewAssert(str_contains($html, 'name="theme" value="night"'), 'The active theme was not retained by the filter form.');
    dashboardPreviewAssert(str_contains($html, 'name="q" type="search" maxlength="120" value="Éowyn &amp; Mira"'), 'Unicode filter value did not render safely.');
    dashboardPreviewAssert(str_contains($html, 'tab=diagnostics&amp;theme=night&amp;q=%C3%89owyn%20%26%20Mira&amp;outcome=committed'), 'Printed tab link did not preserve the theme and encoded filters.');
    dashboardPreviewAssert(str_contains($html, 'tab=interactions&amp;theme=day&amp;q=%C3%89owyn%20%26%20Mira&amp;outcome=committed'), 'Day-mode link did not preserve the active tab and encoded filters.');
    dashboardPreviewAssert(str_contains($html, '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;'), 'Untrusted text was not escaped.');
    dashboardPreviewAssert(!str_contains($html, '<script>'), 'Untrusted text escaped into markup.');
    dashboardPreviewAssert(str_contains($html, '26.63'), 'Small decimal affinity values were lost.');
    dashboardPreviewAssert(str_contains($html, '0</dd>'), 'A confirmed zero decision was not preserved.');
    dashboardPreviewAssert(str_contains($html, '0 (default)'), 'Explicit default-zero state was not rendered.');
    dashboardPreviewAssert(str_contains($html, 'Zero change'), 'Zero-change outcome label is missing.');
    dashboardPreviewAssert(str_contains($html, 'Unconfirmed'), 'Unconfirmed commit is not distinguished from no change.');
    dashboardPreviewAssert(str_contains($html, 'At event (before to after)'), 'Historical before/after values are missing.');
    dashboardPreviewAssert(str_contains($html, 'Before 28.63'), 'Historical before affinity was not rendered.');
    dashboardPreviewAssert(str_contains($html, 'After 26.63'), 'Historical after affinity was not rendered.');
    dashboardPreviewAssert(str_contains($html, 'Clear filters'), 'Filtered results lack a clear action.');
    dashboardPreviewAssert(str_contains($html, '> to </span>'), 'Accessible actor direction text is missing.');

    $diagnosticModel = dashboardPreviewModel();
    $diagnosticModel['records'][0]['speech'] = 'SHOULD NEVER BE VISIBLE';
    $diagnosticModel['records'][0]['model_reason'] = 'SENSITIVE RATIONALE';
    ob_start();
    renderDashboard(dashboardPreviewFiltered($diagnosticModel, dashboardPreviewFilters([
        'tab' => 'diagnostics', 'q' => '0123456789abcdef01234567', 'outcome' => 'committed', 'level' => 'info', 'theme' => 'night',
    ])), ['tab' => 'diagnostics', 'q' => '0123456789abcdef01234567', 'outcome' => 'committed', 'level' => 'info', 'theme' => 'night']);
    $diagnosticsHtml = ob_get_clean();
    dashboardPreviewAssert(is_string($diagnosticsHtml) && str_contains($diagnosticsHtml, 'Download filtered plugin log'), 'Diagnostics download action is missing.');
    dashboardPreviewAssert(str_contains($diagnosticsHtml, 'tab=diagnostics&amp;theme=night&amp;q=0123456789abcdef01234567&amp;outcome=committed&amp;level=info&amp;download=1'), 'Download URL does not preserve the active filters and theme.');
    dashboardPreviewAssert(str_contains($diagnosticsHtml, 'level=info'), 'Download URL did not preserve the level filter.');
    dashboardPreviewAssert(str_contains($diagnosticsHtml, 'Commit state'), 'Diagnostic record details are missing.');
    dashboardPreviewAssert(str_contains($diagnosticsHtml, '&quot;committed&quot;: true'), 'Sanitized record omits confirmed commit evidence.');
    dashboardPreviewAssert(str_contains($diagnosticsHtml, '&quot;changes&quot;: ['), 'Sanitized record omits change details.');
    dashboardPreviewAssert(!str_contains($diagnosticsHtml, 'SHOULD NEVER BE VISIBLE'), 'Raw speech escaped the safe diagnostic record fields.');
    dashboardPreviewAssert(!str_contains($diagnosticsHtml, 'SENSITIVE RATIONALE'), 'Untrusted model rationale escaped the safe diagnostic record fields.');

    $unavailable = dashboardPreviewModel('database-unavailable');
    ob_start();
    renderDashboard($unavailable, ['tab' => 'interactions']);
    $unavailableHtml = ob_get_clean();
    dashboardPreviewAssert(is_string($unavailableHtml) && str_contains($unavailableHtml, 'Showing log-only history.'), 'Database failure did not produce the log-only warning.');
    dashboardPreviewAssert(str_contains($unavailableHtml, 'Unverified history'), 'Unverified log attribution was not labeled.');
    dashboardPreviewAssert(
        preg_match('/<dt>Current<\/dt>\s*<dd class="numeric">Unavailable<\/dd>/', $unavailableHtml) === 1,
        'Current affinity was implied when the database was unavailable.'
    );

    ob_start();
    renderDashboard(dashboardPreviewModel('empty'), ['tab' => 'interactions']);
    $emptyHtml = ob_get_clean();
    dashboardPreviewAssert(is_string($emptyHtml) && str_contains($emptyHtml, 'No recorded interactions'), 'Empty data did not get a distinct empty state.');

    echo "dashboard_preview: PASS (poster, themes, escaping, accessible navigation, filters, values, source states)\n";
}

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === '--self-test') {
    dashboardPreviewSelfTest();
    exit(0);
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (in_array($path, ['/dashboard.css', '/dashboard.js', '/dashboard-art.webp'], true)) {
    return false;
}
if ($path !== '/dashboard.php') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Not found.\n";
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo "Method not allowed.\n";
    exit;
}

$mode = is_string($_GET['preview'] ?? null) ? $_GET['preview'] : 'default';
if (!in_array($mode, ['default', 'empty', 'database-unavailable', 'logs-unavailable'], true)) {
    $mode = 'default';
}
$filters = dashboardPreviewFilters($_GET);
$model = dashboardPreviewFiltered(dashboardPreviewModel($mode), $filters);
if (array_key_exists('download', $_GET)) {
    if ($_GET['download'] !== '1' || $filters['tab'] !== 'diagnostics' || $model['source']['logs'] !== 'available') {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Invalid preview download.\n";
        exit;
    }
    header('Content-Type: application/x-ndjson; charset=UTF-8');
    header('Content-Disposition: attachment; filename="mind-poisoning-preview.jsonl"');
    foreach ($model['records'] as $record) {
        echo json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
renderDashboard($model, $filters);
