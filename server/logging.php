<?php
declare(strict_types=1);

namespace ChimMindPoisoning;

final class RequestLog
{
    private const CONTEXT_FIELDS = [
        'event_id', 'utterance_id', 'config_id', 'playthrough_id', 'speaker_id', 'speaker_kind', 'listener_id', 'addressed_listener_id', 'listener_role', 'batch_id', 'opinion_owner_id', 'source_kind',
    ];
    private const CODE_FIELDS = [
        'stage', 'outcome', 'reason', 'model_outcome', 'persistence_outcome', 'persistence_reason',
        'commit_state',
    ];
    private const MAX_CHANGES = 8;

    /** @var array<string, mixed> */
    private array $context = [];
    /** @var callable|null */
    private $sink;
    /** @var callable|null */
    private $observer = null;
    private \stdClass $observerState;
    private bool $diagnostic;
    private string $requestId;
    private string $version;
    private int $startedAt;
    private bool $finished = false;
    private bool $finishing = false;
    private string $finishedOutcome = 'unknown';

    public function __construct(?callable $sink = null, ?bool $diagnostic = null)
    {
        $this->sink = $sink;
        $this->diagnostic = $diagnostic ?? self::diagnosticEnabled();
        $this->observerState = (object)['observing' => false];
        $this->requestId = self::newRequestId();
        $this->version = self::readVersion();
        try {
            $this->startedAt = hrtime(true);
        } catch (\Throwable) {
            $this->startedAt = 0;
        }
    }

    public function context(array $fields): void
    {
        try {
            $cleanFields = self::sanitizeFields($fields, $this->diagnostic, $this->diagnostic ? 'debug' : 'info');
            foreach ($cleanFields as $key => $value) {
                $this->context[$key] = $value;
            }
        } catch (\Throwable) {
        }
    }

    public function fork(array $context = []): self
    {
        $fork = new self($this->sink, $this->diagnostic);
        $fork->observer = $this->observer;
        $fork->observerState = $this->observerState;
        $fork->context($this->context);
        $fork->context(['batch_id' => $this->requestId]);
        $fork->context($context);
        return $fork;
    }

    public function markBatch(): void
    {
        $this->context(['batch_id' => $this->requestId]);
    }

    public function observe(?callable $observer): void
    {
        $this->observer = $observer;
    }

    public function event(string $event, string $level, array $fields = []): void
    {
        try {
            if ($this->finished || !in_array($level, ['debug', 'info', 'warning', 'error'], true)) {
                return;
            }
            if ($level === 'debug' && !$this->diagnostic) {
                return;
            }
            if (preg_match('/\A[a-z][a-z0-9_]{0,47}\z/D', $event) !== 1) {
                return;
            }

            $record = [
                'schema_version' => 1,
                'plugin' => 'mind_poisoning',
                'version' => $this->version,
                'timestamp' => self::utcTimestamp(),
                'request_id' => $this->requestId,
                'level' => $level,
                'event' => $event,
            ];
            $safeFields = self::sanitizeFields(array_merge($this->context, $fields), $this->diagnostic, $level);
            $contextSpeakerKind = $this->context['speaker_kind'] ?? null;
            if (in_array($contextSpeakerKind, ['npc', 'player'], true)) {
                $safeFields['speaker_kind'] = $contextSpeakerKind;
            } else {
                unset($safeFields['speaker_kind']);
            }
            if ($contextSpeakerKind === 'player') {
                unset($safeFields['speaker_id']);
            }
            $record = array_merge($record, $safeFields);
            $this->write($record, $level);
        } catch (\Throwable) {
        }
    }

    public function finish(string $outcome, string $reason, array $fields = []): string
    {
        if ($this->finished || $this->finishing) {
            return $this->finishedOutcome;
        }
        $this->finishing = true;
        $this->finishedOutcome = self::sanitizeCode($outcome) ?? 'unknown';
        $reason = self::sanitizeCode($reason) ?? 'unknown';
        try {
            $fields['outcome'] = $this->finishedOutcome;
            $fields['reason'] = $reason;
            $fields['elapsed_ms'] = self::elapsedMs($this->startedAt);
            $level = self::finishLevel($this->finishedOutcome, $reason, array_merge($this->context, $fields));
            $this->event('request_finished', $level, $fields);
        } catch (\Throwable) {
        } finally {
            $this->finished = true;
            $this->finishing = false;
        }
        return $this->finishedOutcome;
    }

    public static function elapsedMs(int $start): float
    {
        try {
            $now = hrtime(true);
            if ($start <= 0 || $now < $start) {
                return 0.0;
            }
            return round(($now - $start) / 1_000_000, 1);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private static function diagnosticEnabled(): bool
    {
        try {
            return getenv('MIND_POISONING_LOG_LEVEL') === 'debug';
        } catch (\Throwable) {
            return false;
        }
    }

    private static function newRequestId(): string
    {
        try {
            return bin2hex(random_bytes(12));
        } catch (\Throwable) {
            static $counter = 0;
            $counter++;
            try {
                $seed = (string)hrtime(true) . ':' . (string)getmypid() . ':' . (string)$counter;
                return substr(hash('sha256', $seed), 0, 24);
            } catch (\Throwable) {
                return 'fallback-' . (string)$counter;
            }
        }
    }

    private static function readVersion(): string
    {
        try {
            $contents = @file_get_contents(__DIR__ . '/manifest.json');
            if (!is_string($contents)) {
                return 'unknown';
            }
            $manifest = json_decode($contents, true);
            $version = is_array($manifest) ? ($manifest['version'] ?? null) : null;
            if (is_string($version) && preg_match('/\Amind_poisoning-v([0-9]+\.[0-9]+\.[0-9]+)\z/D', $version, $matches) === 1) {
                $version = $matches[1];
            }
            return is_string($version) && preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+\z/D', $version) === 1
                ? $version
                : 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private static function utcTimestamp(): string
    {
        try {
            return gmdate('Y-m-d\TH:i:s\Z');
        } catch (\Throwable) {
            return '1970-01-01T00:00:00Z';
        }
    }

    private static function sanitizeCode(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A[a-z][a-z0-9_.-]{0,63}\z/D', $value) === 1
            ? $value
            : null;
    }

    private static function finishLevel(string $outcome, string $reason, array $fields): string
    {
        $safeFields = self::sanitizeFields($fields, false, 'info');
        if (($safeFields['cleanup_failed'] ?? null) === true) {
            return 'error';
        }
        if (($safeFields['commit_state'] ?? null) === 'unconfirmed') {
            return 'error';
        }
        if (($safeFields['model_outcome'] ?? null) === 'invalid') {
            return 'warning';
        }
        $warnings = [
            'malformed', 'invalid-payload', 'oversized', 'model-invalid', 'connector-invalid', 'rejected',
            'model_response_invalid', 'judgment_validation_failed',
            'payload_json_invalid', 'payload_root_invalid', 'payload_field_missing', 'payload_field_type_invalid',
            'payload_field_oversized', 'payload_field_empty', 'payload_invalid_utf8', 'payload_utterance_id_missing',
            'payload_utterance_id_type_invalid', 'payload_utterance_id_invalid', 'payload_raw_oversized', 'payload_raw_type_invalid',
            'pause_control_invalid', 'audience_snapshot_unavailable', 'audience_people_invalid',
            'audience_people_oversized', 'audience_speaker_invalid', 'audience_cap_reached',
            'overhearing_prompt_invalid', 'listener-scope-stale',
        ];
        if (in_array($outcome, $warnings, true) || in_array($reason, $warnings, true)) {
            return 'warning';
        }
        if ($outcome === 'failed') {
            return 'error';
        }
        return 'info';
    }

    private static function sanitizeId(mixed $value): ?string
    {
        if (is_int($value)) {
            return $value > 0 ? (string)$value : null;
        }
        return is_string($value) && preg_match('/\A[1-9][0-9]{0,18}\z/D', $value) === 1
            ? $value
            : null;
    }

    private static function sanitizeUtteranceId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        if (preg_match('/\Autt_[A-Za-z0-9_-]{8,128}\z/D', $value) === 1) {
            return $value;
        }
        if (preg_match('/\Ainput_([1-9][0-9]{0,18})\z/D', $value, $matches) !== 1) {
            return null;
        }
        $digits = $matches[1];
        return strlen($digits) === 19 && strcmp($digits, '9223372036854775807') > 0 ? null : $value;
    }

    private static function sanitizeConfigId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/iD', $value) === 1
            ? $value
            : null;
    }

    private static function sanitizeBatchId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A(?:[a-f0-9]{24}|fallback-[1-9][0-9]*)\z/D', $value) === 1
            ? $value
            : null;
    }

    private static function sanitizeSubject(mixed $value): ?string
    {
        if ($value === 'player') {
            return 'player';
        }
        return is_string($value) && preg_match('/\Anpc:[1-9][0-9]{0,18}\z/D', $value) === 1
            ? $value
            : null;
    }

    private static function boundedInteger(mixed $value, int $min, int $max): ?int
    {
        return is_int($value) && $value >= $min && $value <= $max ? $value : null;
    }

    private static function boundedNumber(mixed $value, float $min, float $max): int|float|null
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value)) {
            return null;
        }
        $number = (float)$value;
        if ($number < $min || $number > $max) {
            return null;
        }
        return is_int($value) ? $value : round($number, 2);
    }

    private static function finiteNumber(mixed $value, ?float $min = null, ?float $max = null): int|float|null
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value)) {
            return null;
        }
        $number = (float)$value;
        if (($min !== null && $number < $min) || ($max !== null && $number > $max)) {
            return null;
        }
        return $value;
    }

    private static function boundedText(mixed $value, int $maxBytes): ?string
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            return null;
        }
        if (strlen($value) > $maxBytes) {
            $value = substr($value, 0, $maxBytes);
            while ($value !== '' && preg_match('//u', $value) !== 1) {
                $value = substr($value, 0, -1);
            }
        }
        return $value;
    }

    private static function sanitizeChanges(mixed $value): ?array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return null;
        }
        $changes = [];
        foreach (array_slice($value, 0, self::MAX_CHANGES) as $change) {
            if (!is_array($change)) {
                continue;
            }
            $subject = self::sanitizeSubject($change['subject'] ?? null);
            $delta = self::boundedInteger($change['delta'] ?? null, -5, 5);
            $before = self::finiteNumber($change['before'] ?? null);
            $after = self::finiteNumber($change['after'] ?? null, -100, 100);
            if ($subject === null || $delta === null || $before === null || $after === null) {
                continue;
            }
            $changes[] = ['subject' => $subject, 'delta' => $delta, 'before' => $before, 'after' => $after];
        }
        return $changes;
    }

    private static function sanitizeRelationshipType(mixed $value): ?string
    {
        return is_string($value)
            && strlen($value) <= 50
            && preg_match('/\A[a-z][a-z0-9_-]{0,49}\z/D', $value) === 1
                ? $value
                : null;
    }

    private static function sanitizeTypeChanges(mixed $value): ?array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return null;
        }
        $changes = [];
        $seen = [];
        foreach (array_slice($value, 0, self::MAX_CHANGES) as $change) {
            if (!is_array($change)) {
                continue;
            }
            $subject = self::sanitizeSubject($change['subject'] ?? null);
            $before = self::sanitizeRelationshipType($change['before'] ?? null);
            $after = self::sanitizeRelationshipType($change['after'] ?? null);
            if ($subject === null || $before === null || $after === null || $before === $after || isset($seen[$subject])) {
                continue;
            }
            $changes[] = ['subject' => $subject, 'before' => $before, 'after' => $after];
            $seen[$subject] = true;
        }
        return $changes;
    }

    private static function sanitizeTypeDecisions(mixed $value): ?array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return null;
        }
        $decisions = [];
        $seen = [];
        foreach (array_slice($value, 0, self::MAX_CHANGES) as $decision) {
            if (!is_array($decision)) {
                continue;
            }
            $subject = self::sanitizeSubject($decision['subject'] ?? null);
            $from = self::sanitizeRelationshipType($decision['from'] ?? null);
            $to = self::sanitizeRelationshipType($decision['to'] ?? null);
            $reason = $decision['reason'] ?? null;
            if (
                $subject === null || $from === null || $to === null
                || isset($seen[$subject])
                || !in_array($reason, ['change_eligible', 'same_type', 'protected_type_preserved', 'custom_type_preserved'], true)
            ) {
                continue;
            }
            $decisions[] = ['subject' => $subject, 'from' => $from, 'to' => $to, 'reason' => $reason];
            $seen[$subject] = true;
        }
        return $decisions;
    }

    private static function sanitizeField(string $key, mixed $value, bool $diagnostic, string $level = 'info'): mixed
    {
        if (in_array($key, self::CONTEXT_FIELDS, true)) {
            if ($key === 'utterance_id') {
                return self::sanitizeUtteranceId($value);
            }
            if ($key === 'config_id') {
                return self::sanitizeConfigId($value);
            }
            if ($key === 'speaker_kind') {
                return in_array($value, ['npc', 'player'], true) ? $value : null;
            }
            if ($key === 'source_kind') {
                return $value === 'reflection' ? $value : null;
            }
            if ($key === 'listener_role') {
                return in_array($value, ['addressed', 'overheard'], true) ? $value : null;
            }
            if ($key === 'batch_id') {
                return self::sanitizeBatchId($value);
            }
            if ($key === 'playthrough_id' && $value === 'unprofiled') {
                return $value;
            }
            return self::sanitizeId($value);
        }
        if (in_array($key, self::CODE_FIELDS, true)) {
            if ($key === 'commit_state') {
                return in_array($value, ['confirmed', 'unconfirmed', 'not_attempted'], true) ? $value : null;
            }
            return self::sanitizeCode($value);
        }
        return match ($key) {
            'connector_id' => self::sanitizeId($value),
            'payload_bytes', 'subject_count' => self::boundedInteger($value, 0, PHP_INT_MAX),
            'speech_bytes' => self::boundedInteger($value, 0, 12000),
            'changed_count' => self::boundedInteger($value, 0, 8),
            'affinity_changed_count', 'type_changed_count' => self::boundedInteger($value, 0, 8),
            'delta' => self::boundedInteger($value, -5, 5),
            'elapsed_ms', 'model_ms', 'persistence_ms' => self::boundedNumber($value, 0, 86_400_000),
            'subject' => self::sanitizeSubject($value),
            'committed', 'cleanup_failed' => is_bool($value) ? $value : null,
            'changes' => self::sanitizeChanges($value),
            'type_changes' => self::sanitizeTypeChanges($value),
            'type_decisions' => self::sanitizeTypeDecisions($value),
            'model_reason' => $diagnostic && $level === 'debug' ? self::boundedText($value, 240) : null,
            default => null,
        };
    }

    private static function sanitizeFields(array $fields, bool $diagnostic, string $level): array
    {
        $clean = [];
        foreach ($fields as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $filtered = self::sanitizeField($key, $value, $diagnostic, $level);
            if ($filtered !== null) {
                $clean[$key] = $filtered;
            }
        }
        if (($clean['commit_state'] ?? null) !== 'confirmed' || ($clean['committed'] ?? null) !== true) {
            unset($clean['type_changes'], $clean['type_changed_count']);
        }
        if (($clean['speaker_kind'] ?? null) === 'player') {
            unset($clean['speaker_id']);
        }
        return $clean;
    }

    private function write(array $record, string $level): void
    {
        try {
            $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_string($json)) {
                if (is_callable($this->sink)) {
                    $sink = $this->sink;
                    @$sink($json, $level);
                } else {
                    $method = match ($level) {
                        'debug' => 'debug',
                        'info' => 'info',
                        'warning' => 'warn',
                        'error' => 'error',
                    };
                    if (class_exists('Logger', false) && is_callable(['\\Logger', $method])) {
                        @\Logger::$method($json);
                    } else {
                        @error_log($json);
                    }
                }
            }
        } catch (\Throwable) {
        }

        if ($this->observerState->observing || !is_callable($this->observer)) {
            return;
        }
        $observerRecord = $record;
        unset($observerRecord['model_reason']);
        $observer = $this->observer;
        $this->observerState->observing = true;
        try {
            @$observer($observerRecord, $level);
        } catch (\Throwable) {
        } finally {
            $this->observerState->observing = false;
        }
    }
}
