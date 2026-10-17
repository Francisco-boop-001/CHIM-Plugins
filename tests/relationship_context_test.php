<?php
declare(strict_types=1);

require __DIR__ . '/../server/influence.php';
require_once __DIR__ . '/fixtures/relationship_manager.cf5030f15781637498be86debe26fcf102f5690d.php';
require_once __DIR__ . '/../server/prerequest.php';

use ChimMindPoisoning\JudgmentValidationFailure;
use function ChimMindPoisoning\buildMessages;
use function ChimMindPoisoning\buildOverhearingMessages;
use function ChimMindPoisoning\buildReflectionMessages;
use function ChimMindPoisoning\parseJudgments;
use function ChimMindPoisoning\promptNpcCopy;
use function ChimMindPoisoning\relationshipFor;
use function ChimMindPoisoning\relationshipTypeSnapshot;

function relationshipContextCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function relationshipContextPayload(array $messages): array
{
    return json_decode($messages[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data'];
}

$subjects = ['npc:33' => ['name' => 'Rowan', 'id' => 33]];
$event = [
    'utterance_id' => 'context-ordinary-1',
    'speaker_id' => 10,
    'listener_id' => 11,
    'speaker_name' => 'Aela',
    'listener_name' => 'Lydia',
    'text' => 'Rowan returned the silver key before dawn.',
    'gamets' => 101.0,
    'event_id' => 901,
    'playthrough_id' => 'context-fixture',
];
$speaker = [
    'npc_name' => 'Aela',
    'extended_data' => ['relationships' => [
        'Rowan' => ['aff' => -7, 'type' => 'friend', 'note' => 'Speaker-only note.'],
    ]],
];
$noteExcerpt = 'Rowan stole the silver key last night.';
$long = $noteExcerpt . ' ' . str_repeat('ñ', 100);
$listenerStored = [
    'npc_name' => 'Lydia',
    'extended_data' => (object)['relationships' => (object)[
        'Rowan' => (object)[
            'aff' => 31,
            'type' => 'ally',
            'relation' => $long,
            'note' => $long,
            'best' => $long,
            'worst' => $long,
            'custom_info' => 'This unrelated field must not be forwarded.',
        ],
        'Aela' => (object)['aff' => 46, 'type' => 'friend', 'note' => 'Credibility note stays out of this projection.'],
    ]],
];
$listener = promptNpcCopy($listenerStored);
$subjectsWithPlayer = $subjects + ['player' => ['name' => 'Player', 'id' => null]];
$currentTypes = ['npc:33' => 'ally'];
$listenerBefore = serialize($listenerStored);
$ordinary = relationshipContextPayload(buildMessages($event, $speaker, $listener, $subjects, $currentTypes));
$rowan = $ordinary['candidates']['npc:33'];
$expectedLong = mb_strcut($long, 0, 160, 'UTF-8');

relationshipContextCheck(
    ($rowan['listener_prior_relation']['relation'] ?? null) === $expectedLong,
    'Ordinary candidate context must include the listener-owned relation note.'
);
relationshipContextCheck(
    ($rowan['listener_prior_relation']['note'] ?? null) === $expectedLong
        && ($rowan['listener_prior_relation']['best'] ?? null) === $expectedLong
        && ($rowan['listener_prior_relation']['worst'] ?? null) === $expectedLong,
    'Ordinary candidate context must include all four bounded stored fields.'
);
foreach (['relation', 'note', 'best', 'worst'] as $field) {
    $value = $rowan['listener_prior_relation'][$field];
    relationshipContextCheck(strlen($value) <= 160 && preg_match('//u', $value) === 1, "$field must be valid UTF-8 capped at 160 bytes.");
}
relationshipContextCheck(
    $rowan['listener_prior_relation']['aff'] === 31
        && $rowan['listener_prior_relation']['type'] === 'ally'
        && $rowan['current_type'] === 'ally',
    'Affinity and the separately captured type must remain unchanged.'
);
relationshipContextCheck(
    $rowan['speaker_bias'] === ['aff' => -7, 'type' => 'friend']
        && $ordinary['listener_prior_relation_to_speaker'] === ['aff' => 46, 'type' => 'friend'],
    'Speaker bias and listener-to-speaker credibility must stay affinity/type-only.'
);
relationshipContextCheck(
    !array_key_exists('custom_info', $rowan['listener_prior_relation']),
    'Unapproved relationship fields must not be forwarded.'
);
relationshipContextCheck(
    relationshipFor($listener, 'Rowan') === ['aff' => 31, 'type' => 'ally']
        && relationshipTypeSnapshot($listener, $subjects) === ['npc:33' => 'ally'],
    'The default relationship lookup and type snapshot must retain their old shapes.'
);
relationshipContextCheck(serialize($listenerStored) === $listenerBefore, 'Prompt copying/building must not mutate stored relationship data.');

$jsonMalformedOwner = promptNpcCopy([
    'npc_name' => 'Lydia',
    'extended_data' => (object)['relationships' => (object)[
        'Rowan' => (object)[
            'aff' => -4,
            'type' => 'neutral',
            'relation' => (object)['unexpected' => 'object'],
            'note' => null,
            'best' => ['not', 'text'],
            'worst' => 42,
        ],
    ]],
]);
$invalidContext = relationshipContextPayload(buildMessages($event, $speaker, $jsonMalformedOwner, $subjects, $currentTypes));
relationshipContextCheck(
    $invalidContext['candidates']['npc:33']['listener_prior_relation'] === ['aff' => -4, 'type' => 'neutral'],
    'JSON-object, null, array, and numeric optional fields must be omitted after the actual prompt-copy round trip.'
);
$invalidUtf8Owner = [
    'npc_name' => 'Lydia',
    'extended_data' => ['relationships' => [
        'Rowan' => [
            'aff' => -4,
            'type' => 'neutral',
            'relation' => " \t",
            'note' => "\xC3\x28",
            'best' => 'A valid sibling remains available.',
            'worst' => '',
        ],
    ]],
];
$invalidUtf8Context = relationshipContextPayload(buildMessages($event, $speaker, $invalidUtf8Owner, $subjects, $currentTypes));
relationshipContextCheck(
    $invalidUtf8Context['candidates']['npc:33']['listener_prior_relation'] === [
        'aff' => -4,
        'type' => 'neutral',
        'best' => 'A valid sibling remains available.',
    ],
    'Blank and malformed UTF-8 fields must be omitted independently while valid siblings remain available.'
);

$playerEvent = $event;
$playerEvent['utterance_id'] = 'context-player-1';
$playerEvent['speaker_kind'] = 'player';
$playerEvent['speaker_id'] = null;
$playerEvent['speaker_name'] = 'Dragonborn';
$playerEvent['player_name'] = 'Dragonborn';
$playerListener = [
    'npc_name' => 'Lydia',
    'extended_data' => ['relationships' => [
        'Player' => ['aff' => 50, 'type' => 'romantic', 'note' => 'Canonical Player edge.'],
        'Dragonborn' => ['aff' => 1, 'type' => 'neutral', 'note' => 'Legacy alias must lose.'],
        'Rowan' => ['aff' => 18, 'type' => 'ally', 'note' => 'Listener-to-Rowan context.'],
    ]],
];
$playerSpeakerPayload = relationshipContextPayload(buildMessages(
    $playerEvent,
    [],
    $playerListener,
    $subjects,
    ['npc:33' => 'ally']
));
relationshipContextCheck(
    ($playerSpeakerPayload['candidates']['npc:33']['listener_prior_relation']['note'] ?? null) === 'Listener-to-Rowan context.',
    'Player-origin speech must receive listener-owned context for an NPC subject.'
);
relationshipContextCheck(
    $playerSpeakerPayload['listener_prior_relation_to_speaker'] === ['aff' => 50, 'type' => 'romantic']
        && $playerSpeakerPayload['candidates']['npc:33']['speaker_bias'] === null,
    'Player credibility and the absent Player speaker bias must remain context-free.'
);
$npcSpeakerPlayerEvent = $event;
$npcSpeakerPlayerEvent['player_name'] = 'Dragonborn';
$canonicalPlayerPayload = relationshipContextPayload(buildMessages(
    $npcSpeakerPlayerEvent,
    $speaker,
    $playerListener,
    ['player' => ['name' => 'Player', 'id' => null]],
    ['player' => 'romantic']
));
relationshipContextCheck(
    ($canonicalPlayerPayload['candidates']['player']['listener_prior_relation']['note'] ?? null) === 'Canonical Player edge.'
        && $canonicalPlayerPayload['candidates']['player']['listener_prior_relation']['aff'] === 50,
    'Player candidate context must retain context from the selected canonical Player edge.'
);

$reflectionActorStored = [
    'npc_name' => 'Lydia',
    'personality' => 'Cautious and direct.',
    'extended_data' => (object)['relationships' => (object)[
        'Rowan' => (object)['aff' => 12, 'type' => 'friend', 'note' => 'A directional opinion held by Lydia.'],
        'Player' => (object)['aff' => 24, 'type' => 'romantic', 'note' => 'Lydia keeps the canonical Player edge.'],
        'Dragonborn' => (object)['aff' => 2, 'type' => 'neutral', 'note' => 'Legacy alias.'],
    ]],
];
$reflectionActor = promptNpcCopy($reflectionActorStored);
$reflectionEvent = [
    'source_kind' => 'reflection',
    'opinion_owner_id' => 11,
    'speaker_id' => 11,
    'speaker_name' => 'Lydia',
    'listener_id' => null,
    'player_name' => 'Dragonborn',
    'text' => 'I still trust Rowan with the map, and Dragonborn understands why.',
];
$reflectionNpc = relationshipContextPayload(buildReflectionMessages(
    $reflectionEvent,
    $reflectionActor,
    $subjects,
    [],
    ['npc:33' => 'friend']
));
relationshipContextCheck(
    ($reflectionNpc['candidates']['npc:33']['actor_current_opinion']['note'] ?? null) === 'A directional opinion held by Lydia.',
    'Reflection must include the actor-owned NPC relationship context.'
);
$reflectionPlayer = relationshipContextPayload(buildReflectionMessages(
    $reflectionEvent,
    $reflectionActor,
    $subjectsWithPlayer,
    [],
    ['npc:33' => 'friend', 'player' => 'romantic']
));
relationshipContextCheck(
    ($reflectionPlayer['candidates']['player']['actor_current_opinion']['note'] ?? null) === 'Lydia keeps the canonical Player edge.',
    'Reflection Player context must use the selected canonical Player edge.'
);

$recipientMira = [
    'npc_name' => 'Mira',
    'extended_data' => ['relationships' => [
        'Rowan' => ['aff' => -20, 'type' => 'enemy', 'note' => 'Mira distrusts Rowan for her own reasons.'],
    ]],
];
$addressedEvent = $event + [
    'listener_role' => 'addressed',
    'addressed_listener_id' => 11,
    'addressed_listener_name' => 'Lydia',
];
$overheardEvent = $event;
$overheardEvent['listener_id'] = 12;
$overheardEvent['listener_name'] = 'Mira';
$overheardEvent['listener_role'] = 'overheard';
$overheardEvent['addressed_listener_id'] = 11;
$overheardEvent['addressed_listener_name'] = 'Lydia';
$overhearing = json_decode(buildOverhearingMessages($event, $speaker, [
    ['event' => $addressedEvent, 'listener' => $listener, 'subjects' => $subjects, 'current_types' => $currentTypes],
    ['event' => $overheardEvent, 'listener' => $recipientMira, 'subjects' => $subjects, 'current_types' => $currentTypes],
])[1]['content'], true, 512, JSON_THROW_ON_ERROR)['untrusted_data']['recipients'];
relationshipContextCheck(
    ($overhearing[0]['candidates']['npc:33']['listener_prior_relation']['note'] ?? null) === $expectedLong
        && ($overhearing[1]['candidates']['npc:33']['listener_prior_relation']['note'] ?? null) === 'Mira distrusts Rowan for her own reasons.',
    'Overhearing must keep each listener’s owner-to-subject context separate.'
);

$noteOnlyResponse = json_encode(['judgments' => [[
    'subject' => 'npc:33',
    'subject_mentioned' => true,
    'delta' => -2,
    'reason' => 'The stored note changes my view.',
    'evidence' => $noteExcerpt,
]]], JSON_THROW_ON_ERROR);
try {
    parseJudgments($noteOnlyResponse, $subjects, $event['text']);
    throw new RuntimeException('A stored note must not satisfy current-utterance evidence.');
} catch (JudgmentValidationFailure $failure) {
    relationshipContextCheck($failure->reasonCode === 'judgment_evidence_invalid', 'Note-only evidence should fail at the existing parser boundary.');
}

echo "relationship context checks passed\n";
