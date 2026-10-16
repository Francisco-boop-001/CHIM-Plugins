<?php
declare(strict_types=1);

require __DIR__ . '/../server/influence.php';

use ChimMindPoisoning\JudgmentValidationFailure;
use function ChimMindPoisoning\buildMessages;
use function ChimMindPoisoning\buildOverhearingMessages;
use function ChimMindPoisoning\buildReflectionMessages;
use function ChimMindPoisoning\canonicalRelationshipType;
use function ChimMindPoisoning\parseJudgments;
use function ChimMindPoisoning\parseOverhearingJudgments;
use function ChimMindPoisoning\relationshipTypeAllowedTypes;
use function ChimMindPoisoning\relationshipTypePreservationReason;
use function ChimMindPoisoning\relationshipTypeSnapshot;

if (!function_exists('sameActorName')) {
    function sameActorName(mixed $left, mixed $right): bool
    {
        return is_string($left) && is_string($right)
            && mb_strtolower(trim($left), 'UTF-8') === mb_strtolower(trim($right), 'UTF-8');
    }
}

function typeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function typeResponse(array $rows): string
{
    return json_encode(['judgments' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function typeJudgment(string $subject, mixed $typeChange = null, int $delta = 0, bool $mentioned = true): array
{
    $judgment = [
        'subject' => $subject,
        'subject_mentioned' => $mentioned,
        'delta' => $delta,
        'reason' => 'The statement describes a lasting change in how they relate.',
        'evidence' => 'Farkas stood by her through the winter.',
    ];
    if ($typeChange !== null) {
        $judgment['type_change'] = $typeChange;
    }
    return $judgment;
}

function rejectedType(callable $call, string $reason, string $message): void
{
    try {
        $call();
    } catch (JudgmentValidationFailure $error) {
        typeCheck($error->reasonCode === $reason, $message . ' (got ' . $error->reasonCode . ')');
        return;
    }
    throw new RuntimeException($message . ' (response accepted)');
}

function optionalHelperChecks(string $mode): void
{
    $helperRoot = null;
    unset($GLOBALS['ENGINE_PATH']);
    if ($mode !== '--without-helper') {
        $helperRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chim-mp-type-' . bin2hex(random_bytes(8));
        mkdir($helperRoot . DIRECTORY_SEPARATOR . 'lib', 0700, true);
        $helperSource = $mode === '--throwing-helper'
            ? "<?php\nthrow new \\RuntimeException('injected helper failure');\n"
            : "<?php\nclass RelationshipManager { public const TYPES = []; public static function canonicalizeRelationshipType(\$type, \$custom = []) { return null; } }\n";
        file_put_contents($helperRoot . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'relationship_manager.php', $helperSource);
        $GLOBALS['ENGINE_PATH'] = $helperRoot;
    }

    try {
        typeCheck(relationshipTypeAllowedTypes() === null, 'Unavailable or invalid optional helper types must be represented as unavailable.');
        $subjects = ['npc:3' => ['name' => 'Farkas', 'id' => 3]];
        $utterance = 'Farkas stood by her through the winter.';
        $legacy = parseJudgments(typeResponse([typeJudgment('npc:3')]), $subjects, $utterance);
        typeCheck(array_keys($legacy['npc:3']) === ['delta', 'reason', 'evidence'], 'Affinity-only parsing must survive an unavailable type helper.');
        rejectedType(
            fn() => parseJudgments(typeResponse([typeJudgment('npc:3', ['from' => 'neutral', 'to' => 'rival'])]), $subjects, $utterance, ['npc:3' => 'neutral']),
            'judgment_type_change_invalid',
            'Typed proposals must fail closed when the optional helper is unavailable.'
        );
        $event = [
            'utterance_id' => 'utt_type_eval_0001', 'speaker_id' => 1, 'listener_id' => 2,
            'speaker_name' => 'Aela', 'listener_name' => 'Lydia', 'text' => $utterance,
            'gamets' => 1, 'event_id' => 51, 'playthrough_id' => 'save-a',
        ];
        $messages = buildMessages($event, ['npc_name' => 'Aela'], ['npc_name' => 'Lydia'], $subjects);
        $data = json_decode($messages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
        typeCheck(!array_key_exists('allowed_relationship_types', $data), 'The affinity prompt must still build without optional type context.');
    } finally {
        unset($GLOBALS['ENGINE_PATH']);
        if (is_string($helperRoot)) {
            unlink($helperRoot . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'relationship_manager.php');
            rmdir($helperRoot . DIRECTORY_SEPARATOR . 'lib');
            rmdir($helperRoot);
        }
    }
    echo "optional type helper checks passed: $mode\n";
}

if (isset($argv[1]) && in_array($argv[1], ['--without-helper', '--throwing-helper', '--malformed-helper'], true)) {
    optionalHelperChecks($argv[1]);
    exit(0);
}

require_once __DIR__ . '/fixtures/relationship_manager.cf5030f15781637498be86debe26fcf102f5690d.php';

$subjects = ['npc:3' => ['name' => 'Farkas', 'id' => 3]];
$utterance = 'Farkas stood by her through the winter.';
$currentTypes = ['npc:3' => 'romantic'];
$typeProposal = typeJudgment('npc:3', ['from' => 'romance', 'to' => 'enemies']);
$parsed = null;
try {
    $parsed = parseJudgments(typeResponse([$typeProposal]), $subjects, $utterance, $currentTypes);
} catch (JudgmentValidationFailure $error) {
    typeCheck(false, 'A valid optional type proposal should be accepted (got ' . $error->reasonCode . ').');
}
typeCheck(
    is_array($parsed)
        && $parsed['npc:3']['delta'] === 0
        && $parsed['npc:3']['type_change'] === ['from' => 'romantic', 'to' => 'enemy'],
    'A zero-affinity judgment should retain an alias-canonicalized optional type proposal.'
);

$legacy = parseJudgments(typeResponse([typeJudgment('npc:3')]), $subjects, $utterance, $currentTypes);
typeCheck(
    array_keys($legacy['npc:3']) === ['delta', 'reason', 'evidence'],
    'A keep-type response must retain the exact legacy parsed judgment shape.'
);
$explicitKeep = typeJudgment('npc:3');
$explicitKeep['type_change'] = null;
$nullKeep = parseJudgments(typeResponse([$explicitKeep]), $subjects, $utterance, $currentTypes);
typeCheck(
    array_keys($nullKeep['npc:3']) === ['delta', 'reason', 'evidence'],
    'An explicit null type proposal must normalize to the exact legacy parsed shape.'
);

rejectedType(
    fn() => parseJudgments(typeResponse([typeJudgment('npc:3', ['from' => 'enemy', 'to' => 'rival'])]), $subjects, $utterance, $currentTypes),
    'judgment_type_change_invalid',
    'A proposal whose from value differs from the captured current type must be rejected.'
);
rejectedType(
    fn() => parseJudgments(typeResponse([typeJudgment('npc:3', ['from' => 'romantic', 'to' => 'invented'])]), $subjects, $utterance, $currentTypes),
    'judgment_type_change_invalid',
    'An unknown target type must reject the whole response.'
);
rejectedType(
    fn() => parseJudgments(typeResponse([typeJudgment('npc:3', ['from' => 'romantic', 'to' => 'enemy', 'confidence' => 1])]), $subjects, $utterance, $currentTypes),
    'judgment_type_change_invalid',
    'The nested type proposal must be an exact closed object.'
);
rejectedType(
    fn() => parseJudgments(typeResponse([typeJudgment('npc:3', ['romantic', 'enemy'])]), $subjects, $utterance, $currentTypes),
    'judgment_type_change_invalid',
    'A list must not be accepted as a type proposal object.'
);
rejectedType(
    fn() => parseJudgments(typeResponse([typeJudgment('npc:3', ['from' => 'romantic', 'to' => 'enemy'])]), $subjects, $utterance),
    'judgment_type_change_invalid',
    'A proposal must fail closed when no pre-model current-type snapshot was captured.'
);
$unsupportedTypeEvidence = typeJudgment('npc:3', ['from' => 'romantic', 'to' => 'enemy']);
$unsupportedTypeEvidence['evidence'] = 'not in the utterance';
rejectedType(
    fn() => parseJudgments(typeResponse([$unsupportedTypeEvidence]), $subjects, $utterance, $currentTypes),
    'judgment_evidence_invalid',
    'A type proposal must retain the exact-substring evidence requirement.'
);
rejectedType(
    fn() => parseJudgments(typeResponse([typeJudgment('npc:3', ['from' => 'romantic', 'to' => 'enemy'], 0, false)]), $subjects, $utterance, $currentTypes),
    'judgment_type_change_invalid',
    'A type proposal must require a confirmed subject mention.'
);

$custom = parseJudgments(
    typeResponse([typeJudgment('npc:3', ['from' => 'Moonbound', 'to' => 'rival'])]),
    $subjects,
    $utterance,
    ['npc:3' => 'Moonbound']
);
typeCheck(
    $custom['npc:3']['type_change'] === ['from' => 'moonbound', 'to' => 'rival'],
    'A current custom label may be preserved as the source type while targets stay built-in.'
);
$protected = parseJudgments(
    typeResponse([typeJudgment('npc:3', ['from' => 'romantic', 'to' => 'jealous'])]),
    $subjects,
    $utterance,
    $currentTypes
);
typeCheck(
    $protected['npc:3']['type_change'] === ['from' => 'romantic', 'to' => 'jealous']
        && relationshipTypePreservationReason('romantic', 'jealous') === 'protected_type_preserved',
    'A valid protected proposal must remain available to storage with its fixed preservation reason.'
);
typeCheck(canonicalRelationshipType('enemies') === 'enemy', 'Type aliases must use the CHIM canonicalizer.');
typeCheck(relationshipTypeAllowedTypes() === \RelationshipManager::TYPES, 'Allowed targets must come from CHIM RelationshipManager::TYPES.');
typeCheck(
    relationshipTypePreservationReason('moonbound', 'rival') === 'custom_type_preserved'
        && relationshipTypePreservationReason('romantic', 'jealous') === 'protected_type_preserved'
        && relationshipTypePreservationReason('enemy', 'rival') === null,
    'The shared storage policy helper must preserve custom and protected pairs only.'
);

$owner = [
    'npc_name' => 'Lydia',
    'extended_data' => ['relationships' => ['Farkas' => ['aff' => 12, 'type' => 'romance']]],
];
typeCheck(
    relationshipTypeSnapshot($owner, $subjects) === ['npc:3' => 'romantic'],
    'Pre-model snapshots must canonicalize the owner’s current relationship type.'
);

$event = [
    'utterance_id' => 'utt_type_eval_0001', 'speaker_id' => 1, 'listener_id' => 2,
    'speaker_name' => 'Aela', 'listener_name' => 'Lydia', 'text' => $utterance,
    'gamets' => 1, 'event_id' => 51, 'playthrough_id' => 'save-a',
];
$listener = $owner + ['personality' => 'Cautious.'];
$pairMessages = buildMessages($event, ['npc_name' => 'Aela'], $listener, $subjects, $currentTypes);
$pairData = json_decode($pairMessages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
typeCheck(
    $pairData['candidates']['npc:3']['current_type'] === 'romantic'
        && $pairData['allowed_relationship_types'] === \RelationshipManager::TYPES
        && str_contains($pairMessages[0]['content'], 'type_change')
        && str_contains(strtolower($pairMessages[0]['content']), 'lasting'),
    'Ordinary prompts must offer an optional lasting type proposal with the captured type and CHIM allow-list.'
);

$reflectionEvent = [
    'source_kind' => 'reflection', 'opinion_owner_id' => 2, 'speaker_id' => 2, 'listener_id' => null,
    'speaker_name' => 'Lydia', 'player_name' => 'Hawke', 'text' => $utterance,
];
$reflectionMessages = buildReflectionMessages($reflectionEvent, $owner, $subjects, []);
$reflectionData = json_decode($reflectionMessages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
typeCheck(
    $reflectionData['candidates']['npc:3']['current_type'] === 'romantic'
        && $reflectionData['allowed_relationship_types'] === \RelationshipManager::TYPES
        && str_contains($reflectionMessages[0]['content'], 'type_change')
        && str_contains(strtolower($reflectionMessages[0]['content']), 'lasting'),
    'Reflection prompts must offer the same optional type contract for the actor’s own opinion.'
);

$overhearingRecipients = [];
foreach ([2 => 'enemy', 9 => 'romantic'] as $listenerId => $currentType) {
    $recipientEvent = $event + ['listener_role' => 'addressed'];
    $recipientEvent['listener_id'] = $listenerId;
    $recipientEvent['listener_name'] = $listenerId === 2 ? 'Lydia' : 'Serana';
    $overhearingRecipients[] = [
        'event' => $recipientEvent,
        'listener' => ['npc_name' => $recipientEvent['listener_name'], 'extended_data' => [
            'relationships' => ['Farkas' => ['aff' => 0, 'type' => $currentType]],
        ]],
        'subjects' => $subjects,
        'current_types' => ['npc:3' => $currentType],
    ];
}
$overhearingMessages = buildOverhearingMessages($event, ['npc_name' => 'Aela'], $overhearingRecipients);
$overhearingData = json_decode($overhearingMessages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
typeCheck(
    $overhearingData['recipients'][0]['candidates']['npc:3']['current_type'] === 'enemy'
        && $overhearingData['recipients'][1]['candidates']['npc:3']['current_type'] === 'romantic'
        && $overhearingData['recipients'][0]['allowed_relationship_types'] === \RelationshipManager::TYPES
        && str_contains($overhearingMessages[0]['content'], 'type_change'),
    'Overhearing prompts must isolate current type context and allow-list by recipient.'
);
$overhearingResponse = json_encode(['judgments' => [
    ['listener_id' => 2] + typeJudgment('npc:3', ['from' => 'enemy', 'to' => 'rival']),
    ['listener_id' => 9] + typeJudgment('npc:3', ['from' => 'romantic', 'to' => 'enemy']),
]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$overhearingParsed = parseOverhearingJudgments($overhearingResponse, $overhearingRecipients, $utterance);
typeCheck(
    $overhearingParsed[2]['npc:3']['type_change']['from'] === 'enemy'
        && $overhearingParsed[9]['npc:3']['type_change']['from'] === 'romantic',
    'Overhearing proposals must validate against each listener’s own pre-model type snapshot.'
);
$recipientMismatch = json_decode($overhearingResponse, true, 512, JSON_THROW_ON_ERROR);
$recipientMismatch['judgments'][1]['type_change']['from'] = 'enemy';
rejectedType(
    fn() => parseOverhearingJudgments(
        json_encode($recipientMismatch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        $overhearingRecipients,
        $utterance
    ),
    'judgment_type_change_invalid',
    'An overhearer proposal must not borrow another recipient’s current-type snapshot.'
);

echo "relationship type judgment checks passed\n";
