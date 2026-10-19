<?php
declare(strict_types=1);

final class RestoreInitFixtureFailure extends RuntimeException
{
}

final class Logger
{
    public static function trace(string $message): void {}
    public static function debug(string $message): void {}
    public static function info(string $message): void {}
    public static function warn(string $message): void {}
    public static function error(string $message): void {}
}

/** Test-only CHIM sql shim consumed by PostgresStoreDb's native transaction seam. */
final class sql
{
    public static mixed $link = null;
}

function chimInteractionBegin(): void
{
    $GLOBALS['chim_interaction_generation'] ??= 1;
}

function chimInteractionState(): array
{
    return ['enabled' => true, 'generation' => 1];
}

function chimInteractionAllowed(): bool
{
    if (!empty($GLOBALS['restore_policy_plugin_test_active'])) {
        return true;
    }
    if (isset($GLOBALS['db'])) {
        $GLOBALS['db']->execQuery("INSERT INTO public.restore_fixture_trace (operation) VALUES ('main-post-comm-gate')");
    }
    return false;
}

function chimIsGlobalLlmConnectorEnabled(string $connectorField): bool
{
    return $connectorField === 'RELLLM_CONNECTOR';
}

function extractSpeakerNameFromChatEvent(mixed $eventData): string
{
    return preg_match('/^\\s*([^:]{1,128})\\s*:/u', trim((string)$eventData), $matches) === 1
        ? trim($matches[1])
        : '';
}

function extractTalkTargetMetadata(mixed $eventData): array
{
    if (!preg_match('/\\(\\s*(?:(?:talking|whispering|shouting)\\s+to|speaking\\s+(?:loudly|privately)\\s+to)\\s+([^()]+?)\\s*\\)/iu', (string)$eventData, $matches)) {
        return ['hasExplicitTarget' => false, 'isBroadcast' => false, 'targets' => []];
    }
    $hint = trim($matches[1]);
    if (strcasecmp($hint, 'everyone') === 0) {
        return ['hasExplicitTarget' => true, 'isBroadcast' => true, 'targets' => []];
    }
    $targets = preg_split('/\\s*(?:,|&| and )\\s*/iu', $hint);
    return ['hasExplicitTarget' => true, 'isBroadcast' => false, 'targets' => array_values(array_filter(array_map('trim', $targets ?: [])))];
}

function talkTargetsIncludeName(mixed $targetNames, mixed $candidateName): bool
{
    foreach (is_array($targetNames) ? $targetNames : [] as $targetName) {
        if (is_string($targetName) && is_string($candidateName) && strcasecmp(trim($targetName), trim($candidateName)) === 0) {
            return true;
        }
    }
    return false;
}

function parsePeoplePipeList(mixed $peoplePipe): array
{
    $names = array_map('trim', explode('|', trim((string)$peoplePipe, '|')));
    return array_values(array_unique(array_filter($names, static fn(string $name): bool => $name !== '')));
}

/**
 * Minimal PostgreSQL adapter for the exact core main.php/processor/comm.php path.
 * Eventlog inserts and eventlog/speech deletes reach the disposable database.
 * Other unrelated table operations are recorded as inert fixture substitutions.
 */
final class RestoreInitFixtureDb
{
    private \PgSql\Connection $connection;

    public function __construct(string $connectionString)
    {
        $connection = pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);
        if ($connection === false) {
            throw new RuntimeException('Could not connect to the disposable fixture socket.');
        }
        $this->connection = $connection;
    }

    public function query(string $sql, ?array $params = null): \PgSql\Result
    {
        $result = $params === null
            ? pg_query($this->connection, $sql)
            : pg_query_params($this->connection, $sql, $params);
        if ($result === false) {
            throw new RuntimeException('Fixture query failed: ' . pg_last_error($this->connection));
        }
        return $result;
    }

    public function execQuery(string $sql, ?array $params = null): \PgSql\Result
    {
        return $this->query($sql, $params);
    }

    public function fetchOne(string $sql, ?array $params = null): ?array
    {
        $result = $this->query($sql, $params);
        $row = pg_fetch_assoc($result);
        pg_free_result($result);
        return is_array($row) ? $row : null;
    }

    public function fetchAll(string $sql, bool $log = false): array
    {
        $result = $this->query($sql);
        $rows = [];
        while (($row = pg_fetch_assoc($result)) !== false) {
            $rows[] = $row;
        }
        pg_free_result($result);
        return $rows;
    }

    public function delete(string $table, string $where = 'FALSE'): int
    {
        if (!in_array($table, ['eventlog', 'speech'], true)) {
            $this->recordInertCall('delete', $table);
            return 0;
        }
        if (preg_match('/\A[A-Za-z0-9_ .<>=()\'-]+\z/D', $where) !== 1) {
            throw new RuntimeException('Unexpected fixture delete predicate.');
        }
        $result = $this->query("DELETE FROM public.{$table} WHERE {$where}");
        return pg_affected_rows($result);
    }

    public function insert(string $table, array $data): ?int
    {
        if ($table !== 'eventlog' || $data === []) {
            throw new RuntimeException('Unexpected insert in the isolated INIT fixture.');
        }
        $columns = array_keys($data);
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/\A[a-z_][a-z0-9_]*\z/D', $column) !== 1) {
                throw new RuntimeException('Unexpected fixture insert column.');
            }
        }
        $placeholders = [];
        foreach (array_keys($columns) as $index) {
            $placeholders[] = '$' . ($index + 1);
        }
        $sql = 'INSERT INTO public.eventlog (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', $placeholders) . ') RETURNING rowid';
        $row = $this->fetchOne($sql, array_values($data));
        return isset($row['rowid']) ? (int)$row['rowid'] : null;
    }

    public function update(string $table, string $set, string $where = 'FALSE'): int
    {
        if ($table !== 'responselog') {
            throw new RuntimeException('Unexpected update in the isolated INIT fixture.');
        }
        $this->recordInertCall('update', $table);
        return 0;
    }

    public function upsertRowOnConflict(string $table, array $data, string $conflictTarget): bool
    {
        if ($table !== 'conf_opts') {
            throw new RuntimeException('Unexpected upsert in the isolated INIT fixture.');
        }
        $this->recordInertCall('upsert', $table);
        return true;
    }

    private function recordInertCall(string $operation, string $table): void
    {
        $this->query(
            'INSERT INTO public.restore_fixture_trace (operation, event_type) VALUES ($1, $2)',
            ['inert-' . $operation, $table]
        );
    }

    public function escape(string $value): string
    {
        return pg_escape_string($this->connection, $value);
    }

    public function nativeConnection(): \PgSql\Connection
    {
        return $this->connection;
    }

    public function close(): void
    {
        pg_close($this->connection);
    }
}

function restoreFixtureRequireEnv(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        throw new RuntimeException("Required fixture setting is missing: {$name}");
    }
    return $value;
}

function restoreFixtureAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RestoreInitFixtureFailure($message);
    }
}

function restoreFixtureConnectionString(): string
{
    return sprintf(
        'host=%s port=%s dbname=%s user=%s',
        restoreFixtureRequireEnv('PGHOST'),
        restoreFixtureRequireEnv('PGPORT'),
        restoreFixtureRequireEnv('PGDATABASE'),
        restoreFixtureRequireEnv('PGUSER')
    );
}

function restoreFixtureOpenDb(): RestoreInitFixtureDb
{
    return new RestoreInitFixtureDb(restoreFixtureConnectionString());
}

function restoreFixtureMainBootstrap(string $enginePath): void
{
    $GLOBALS['ENGINE_PATH'] = $enginePath;
    $GLOBALS['db'] = restoreFixtureOpenDb();
    sql::$link = $GLOBALS['db']->nativeConnection();
    $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = true;
    $GLOBALS['HERIKA_NAME'] = 'Synthetic Listener';
    $GLOBALS['PLAYER_NAME'] = 'Synthetic Player';
    $GLOBALS['FUNCTIONS_ARE_ENABLED'] = false;
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    $GLOBALS['EXECUTION_MODE'] = '';
    $GLOBALS['CACHE_PEOPLE'] = '';
    $GLOBALS['CACHE_PEOPLE_LIMITED'] = '';
    $GLOBALS['FEATURES'] = [];
    $GLOBALS['IS_NPC'] = false;
    $GLOBALS['pgr_skip_rollback'] = false;
}

function restoreFixtureVerifyHash(string $path, string $expected): void
{
    restoreFixtureAssert(is_file($path), 'Pinned source file is missing: ' . basename($path));
    $actual = hash_file('sha256', $path);
    restoreFixtureAssert(is_string($actual) && hash_equals($expected, strtolower($actual)), 'Pinned source hash mismatch: ' . basename($path));
}

function restoreFixtureCopy(string $source, string $destination): void
{
    $parent = dirname($destination);
    if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
        throw new RuntimeException('Could not create the scratch engine directory.');
    }
    if (!copy($source, $destination)) {
        throw new RuntimeException('Could not copy a pinned source file into scratch.');
    }
}

function restoreFixtureBuildEngine(): string
{
    $root = restoreFixtureRequireEnv('RESTORE_FIXTURE_EXPECTED_ROOT');
    $pinned = restoreFixtureRequireEnv('RESTORE_FIXTURE_PINNED_ROOT');
    restoreFixtureAssert(realpath($root) === $root, 'Scratch root does not resolve exactly.');
    restoreFixtureAssert(preg_match('/\A\/tmp\/mp-restore-policy-init-20261018\.[A-Za-z0-9]{6}\z/D', $root) === 1, 'Scratch root name is outside the approved fixture pattern.');
    restoreFixtureAssert(realpath($pinned) === $pinned && str_starts_with($pinned, $root . '/'), 'Pinned input root is outside owned scratch.');

    $files = [
        ['main.php', 'main.php', '3619049e6d54493a77b19c09f8b70420e3c35427545a8bb6e95e93c6fe57ebac'],
        ['processor/comm.php', 'processor/comm.php', 'fc0a3f75bac47f8899a7c1c550aa1dafc3a59fdf36de39c0f1cb7722e432612c'],
        ['lib/core/npc_master.class.php', 'lib/core/npc_master.class.php', '506cf5bcc13d513abf604a4d4c14453312a34cafcbdf83d37c6ad1ab4a9b12dc'],
        ['lib/core/game_plugins.php', 'lib/core/game_plugins.php', '361b021322134bedd796abc73e1394357ee97988735e611e56416fe729549f80'],
        ['lib/core/tts_filter_presets.php', 'lib/core/tts_filter_presets.php', '4168937046a3dc46081b6e6924155f056923e46c5c61c0396cd3686845d8f33c'],
        ['lib/relationship_manager.php', 'lib/relationship_manager.php', '4497443c9480c7d7ff5d5deabcec9ad5ab4cdec6f28f1fd044f89594d71e06ab'],
    ['reference/semaphore_manager.class.php', 'lib/semaphore_manager.class.php', '7e4463adbd42ea4cbaf960ccd5ff55beb623646d17da9e1b063d1a130983b4a6'],
        ['reference/auditing.php', 'lib/auditing.php', '22b6316ffa09e434a089034ca6877a7c115b2214aa5353337965971e19d50a10'],
        ['reference/request_performance.php', 'lib/request_performance.php', '5c750e7f5c5cf82cc2fddbda4d10c9dcff1f8f3b9e95241cb8a332c56fc6d673'],
    ];
    $engine = $root . '/engine';
    foreach ($files as [$relative, $engineRelative, $hash]) {
        $source = $pinned . '/' . $relative;
        restoreFixtureVerifyHash($source, $hash);
        restoreFixtureCopy($source, $engine . '/' . $engineRelative);
    }

    $stubs = [
        'lib/chim_interaction.php' => <<<'STUB'
<?php
function chimInteractionIsTrigger($event): bool { return false; }
function chimInteractionRequire(): void {}
function chimInteractionIsGameOutput($action): bool { return false; }
function chimDecodePlayerRoutingSnapshotField($value): array { return []; }
function buildScopedPeopleForEvent($type, $data, $speaker, $fallback): string { return ''; }
function isStrictSpatialPeopleModeEnabled(): bool { return false; }
function DataBeingsInCloseRange($includePlayer = false): string { return ''; }
function requireFilesRecursively($directory, $name): void {}
STUB,
        'lib/playthrough_guard.php' => <<<'STUB'
<?php
function pgr_http_preflight(string $route): void { $GLOBALS['pgr_skip_rollback'] = false; }
function pgr_complete(): void {
    if (isset($GLOBALS['db'])) {
        $GLOBALS['db']->execQuery("INSERT INTO public.restore_fixture_trace (operation) VALUES ('pgr-complete')");
    }
}
STUB,
        'lib/runtime_bootstrap.php' => <<<'STUB'
<?php
function chimRuntimeBootstrap(string $path, array $options = []): void {
    restoreFixtureMainBootstrap($path);
}
STUB,
        'lib/game_activity.php' => "<?php\n",
        'lib/background_processor.php' => "<?php\n",
        'lib/model_dynmodel.php' => "<?php\n",
        'lib/minimet5_service.php' => "<?php\n",
        'lib/data_functions.php' => <<<'STUB'
<?php
function DataGetCurrentPartyConf(): string { return '[]'; }
STUB,
        'lib/chat_helper_functions.php' => "<?php\n",
        'lib/player_mood_prompts.php' => "<?php\n",
        'lib/compact_context_history.php' => "<?php\n",
        'lib/lazy_xml.php' => "<?php\n",
        'lib/memory_helper_vectordb.php' => "<?php\n",
        'lib/llm_randomizer.php' => "<?php\n",
        'lib/utils_game_timestamp.php' => <<<'STUB'
<?php
function DataLastKnownGameTS(): int {
    $row = $GLOBALS['db']->fetchOne('SELECT COALESCE(MAX(gamets), 0)::int AS gamets FROM public.eventlog');
    return (int)($row['gamets'] ?? 0);
}
STUB,
        'lib/logger.php' => "<?php\n",
        'lib/chim_quest_engine.php' => <<<'STUB'
<?php
function chimQuestEngineResetRuntime(bool $reset = false): void {}
STUB,
        'lib/pipeline_status.php' => "<?php\n",
        'lib/core/api_badge.class.php' => "<?php\n",
        'lib/core/llm_connector.class.php' => "<?php\n",
        'lib/core/tts_connector.class.php' => "<?php\n",
        'lib/core/core_profiles.class.php' => "<?php\n",
        'lib/core/book_read.class.php' => <<<'STUB'
<?php
function bookReadStatePauseForInput(): void {}
STUB,
        'lib/core/narrator.class.php' => <<<'STUB'
<?php
class Narrator {
    public function getBool(string $key, bool $default): bool { return false; }
    public function getInt(string $key, int $default): int { return $default; }
}
STUB,
        'lib/dynamic_update_util.php' => "<?php\n",
        'lib/playthrough_autosave.php' => <<<'STUB'
<?php
function dragon_break_playthrough_if_needed(int $previous, int $incoming): int { return 0; }
STUB,
        'lib/background_life_encounters.php' => "<?php\n",
        'processor/chim_modes.php' => "<?php\n",
        'processor/misc.php' => "<?php\n",
        'processor/narrator_init.php' => "<?php\n",
        'processor/postrequest.php' => "<?php\n",
        'service/processors/snqe/lib/snqe.class.php' => <<<'STUB'
<?php
class SNQEQuestManager {
    public static function load_quests($timestamp): void {}
    public static function save_quests($timestamp): void {}
}
STUB,
    ];

    foreach ($stubs as $relative => $contents) {
        $path = $engine . '/' . $relative;
        $parent = dirname($path);
        if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
            throw new RuntimeException('Could not create an inert service stub directory.');
        }
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Could not write an inert service stub.');
        }
    }
    $soundcache = $engine . '/soundcache';
    if (!is_dir($soundcache) && !mkdir($soundcache, 0700, true) && !is_dir($soundcache)) {
        throw new RuntimeException('Could not create the scratch soundcache directory.');
    }
    file_put_contents($soundcache . '/.placeholder', '');
    return $engine;
}

function restoreFixtureAssertTarget(RestoreInitFixtureDb $db): array
{
    $root = restoreFixtureRequireEnv('RESTORE_FIXTURE_EXPECTED_ROOT');
    $data = restoreFixtureRequireEnv('RESTORE_FIXTURE_EXPECTED_DATA');
    $socket = restoreFixtureRequireEnv('RESTORE_FIXTURE_EXPECTED_SOCKET');
    restoreFixtureAssert(getenv('PGDATABASE') === 'mp_restore_policy_init_fixture', 'Unexpected fixture database name.');
    restoreFixtureAssert(getenv('PGUSER') === 'postgres', 'Unexpected fixture database user.');
    restoreFixtureAssert(getenv('PGPORT') === '55489', 'Unexpected fixture port.');
    restoreFixtureAssert(getenv('PGHOST') === $socket, 'PGHOST is not the owned socket directory.');
    restoreFixtureAssert($data === $root . '/pgdata' && $socket === $root . '/socket', 'Expected data/socket path mismatch.');
    restoreFixtureAssert(realpath($root) === $root && realpath($data) === $data && realpath($socket) === $socket, 'Fixture target path mismatch.');

    $identity = $db->fetchOne(
        "SELECT current_user AS db_user,
                current_database() AS db_name,
                current_setting('data_directory') AS data_directory,
                current_setting('listen_addresses') AS listen_addresses,
                current_setting('unix_socket_directories') AS socket_directories,
                current_setting('port') AS port,
                inet_server_addr()::text AS inet_address,
                COALESCE((
                    SELECT string_agg(c.relname, ',' ORDER BY c.relname)
                    FROM pg_class c
                    JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f')
                ), '') AS public_relations,
                (SELECT count(*)::text FROM public.core_npc_master) AS npc_rows,
                (SELECT count(*)::text FROM public.core_npc_master_history) AS history_rows,
                (SELECT count(*)::text FROM public.eventlog) AS eventlog_rows,
                (SELECT count(*)::text FROM public.speech) AS speech_rows"
    );
    restoreFixtureAssert(is_array($identity), 'Could not read fixture identity.');
    restoreFixtureAssert(($identity['db_user'] ?? null) === 'postgres', 'Database user identity mismatch.');
    restoreFixtureAssert(($identity['db_name'] ?? null) === 'mp_restore_policy_init_fixture', 'Database name identity mismatch.');
    restoreFixtureAssert(($identity['data_directory'] ?? null) === $data, 'PostgreSQL data directory mismatch.');
    restoreFixtureAssert(($identity['listen_addresses'] ?? null) === '', 'PostgreSQL TCP listening is enabled.');
    restoreFixtureAssert(($identity['socket_directories'] ?? null) === $socket, 'PostgreSQL socket directory mismatch.');
    restoreFixtureAssert(($identity['port'] ?? null) === '55489', 'PostgreSQL port mismatch.');
    restoreFixtureAssert(($identity['inet_address'] ?? null) === null, 'Fixture connection is not a Unix socket.');
    restoreFixtureAssert(
        ($identity['public_relations'] ?? null) === 'core_npc_master,core_npc_master_history,core_npc_master_history_history_id_seq,core_player,core_profiles,eventlog,eventlog_rowid_seq,npc_master_id_seq,relationship_eval_queue,relationship_init_queue,speech,speech_rowid_seq',
        'Fixture schema contains unexpected or missing core relations.'
    );
    foreach (['npc_rows', 'history_rows', 'eventlog_rows', 'speech_rows'] as $key) {
        restoreFixtureAssert(($identity[$key] ?? null) === '0', "Fixture table is not empty before seeding: {$key}");
    }
    return $identity;
}

function restoreFixtureInstallTrace(RestoreInitFixtureDb $db): void
{
    $db->execQuery(<<<'SQL'
CREATE TABLE public.restore_fixture_trace (
    trace_id BIGSERIAL PRIMARY KEY,
    operation TEXT NOT NULL,
    row_id BIGINT NULL,
    event_type TEXT NULL,
    gamets BIGINT NULL,
    npc_id BIGINT NULL
);
CREATE FUNCTION public.restore_fixture_trace_eventlog() RETURNS trigger
LANGUAGE plpgsql AS $trace$
BEGIN
    IF TG_OP = 'DELETE' THEN
        INSERT INTO public.restore_fixture_trace (operation, row_id, event_type, gamets)
        VALUES ('eventlog-delete', OLD.rowid, OLD.type, OLD.gamets);
        RETURN OLD;
    END IF;
    INSERT INTO public.restore_fixture_trace (operation, row_id, event_type, gamets)
    VALUES ('eventlog-insert', NEW.rowid, NEW.type, NEW.gamets);
    RETURN NEW;
END;
$trace$;
CREATE TRIGGER restore_fixture_eventlog_trace
AFTER INSERT OR DELETE ON public.eventlog
FOR EACH ROW EXECUTE FUNCTION public.restore_fixture_trace_eventlog();

CREATE FUNCTION public.restore_fixture_trace_speech_delete() RETURNS trigger
LANGUAGE plpgsql AS $trace$
BEGIN
    INSERT INTO public.restore_fixture_trace (operation, row_id, gamets)
    VALUES ('speech-delete', OLD.rowid, OLD.gamets);
    RETURN OLD;
END;
$trace$;
CREATE TRIGGER restore_fixture_speech_delete_trace
AFTER DELETE ON public.speech
FOR EACH ROW EXECUTE FUNCTION public.restore_fixture_trace_speech_delete();

CREATE FUNCTION public.restore_fixture_trace_npc() RETURNS trigger
LANGUAGE plpgsql AS $trace$
BEGIN
    IF TG_OP = 'DELETE' THEN
        INSERT INTO public.restore_fixture_trace (operation, npc_id)
        VALUES ('npc-delete', OLD.id);
        RETURN OLD;
    END IF;
    INSERT INTO public.restore_fixture_trace (operation, npc_id)
    VALUES ('npc-insert', NEW.id);
    RETURN NEW;
END;
$trace$;
CREATE TRIGGER restore_fixture_npc_trace
AFTER INSERT OR DELETE ON public.core_npc_master
FOR EACH ROW EXECUTE FUNCTION public.restore_fixture_trace_npc();
SQL);
}

function restoreFixtureInsertEvent(RestoreInitFixtureDb $db, int $gamets, string $type, string $data): int
{
    $row = $db->fetchOne(
        "INSERT INTO public.eventlog (ts, gamets, type, data, sess, localts, people, location, party)
         VALUES ($1, $2, $3, $4, 'fixture', 1, '', '', '') RETURNING rowid",
        [1700000000, $gamets, $type, $data]
    );
    restoreFixtureAssert(is_array($row) && isset($row['rowid']), 'Could not seed synthetic eventlog source.');
    return (int)$row['rowid'];
}

function restoreFixtureInsertEventAtId(
    RestoreInitFixtureDb $db,
    int $rowId,
    int $gamets,
    string $type,
    string $data,
    ?string $utteranceId = null,
    ?string $people = null
): void {
    $db->execQuery(
        'INSERT INTO public.eventlog
            (rowid, ts, gamets, type, data, sess, localts, people, location, party, utterance_id, delivery_state)
         VALUES ($1, $2, $3, $4, $5, $6, 1, $7, $8, $9, $10, $11)',
        [
            $rowId,
            1700000000,
            $gamets,
            $type,
            $data,
            $type === 'chat' ? 'fixture' : 'fixture-old',
            $people,
            '',
            '',
            $utteranceId,
            $type === 'chat' ? 'spoken' : null,
        ]
    );
}

function restoreFixtureInsertAckEvent(RestoreInitFixtureDb $db, int $gamets, string $utteranceId, string $text): int
{
    $row = $db->fetchOne(
        "INSERT INTO public.eventlog
            (ts, gamets, type, data, sess, localts, people, location, party, utterance_id, delivery_state)
         VALUES ($1, $2, 'chat', $3, 'fixture', 1, '|Aela|Synthetic Listener|Synthetic Target|', '', '', $4, 'spoken')
         RETURNING rowid",
        [1700000000, $gamets, 'Aela: ' . $text . ' (talking to Synthetic Listener)', $utteranceId]
    );
    restoreFixtureAssert(is_array($row) && isset($row['rowid']), 'Could not insert synthetic acknowledged chat source.');
    return (int)$row['rowid'];
}

function restoreFixtureBuildNativeAckEvent(
    \ChimMindPoisoning\PostgresStoreDb $store,
    int $eventId,
    string $utteranceId,
    string $text,
    int $initEventId
): array {
    $source = $store->eventById($eventId, $utteranceId);
    restoreFixtureAssert(is_array($source), 'Production store did not read the native acknowledged event row.');
    return $source + [
        'speaker_kind' => 'npc',
        'speaker_id' => 7001,
        'speaker_name' => 'Aela',
        'listener_id' => 7003,
        'listener_name' => 'Synthetic Listener',
        'player_name' => 'Synthetic Player',
        'playthrough_id' => 'unprofiled',
        'text' => $text,
        'source_delivery_state' => 'spoken',
        'restore_policy_init_event_id' => $initEventId,
    ];
}

function restoreFixtureSeed(RestoreInitFixtureDb $db): array
{
    $db->execQuery('TRUNCATE public.core_npc_master, public.core_npc_master_history, public.eventlog, public.speech RESTART IDENTITY');
    $db->execQuery("INSERT INTO public.core_player (id, value) VALUES ('player_name', 'Synthetic Player')");
    $db->execQuery(
        'INSERT INTO public.core_npc_master
            (id, npc_name, lock_profile, extended_data, plugin_extended_data, gamets_last_updated, md5)
         VALUES ($1, $2, 0, $3::jsonb, $4::jsonb, 200, $5)',
        [
            7003,
            'Synthetic Listener',
            json_encode(['relationships' => ['Synthetic Target' => ['aff' => 13, 'type' => 'neutral', 'note' => 'newer relationship']], 'fixture_marker' => 'newer'], JSON_THROW_ON_ERROR),
            json_encode(['mind_poisoning' => [
                'playthrough_id' => 'unprofiled',
                'floor_event_id' => 2999,
                'events' => [['event_id' => 3000, 'utterance_id' => 'utt_restore_retained_3000'], ['event_id' => 3001, 'utterance_id' => 'utt_restore_pruned_3001']],
                'reflection_state' => ['basis' => str_repeat('b', 64), 'subjects' => ['npc:7004']],
            ]], JSON_THROW_ON_ERROR),
            'synthetic-current'
        ]
    );
    $db->execQuery(
        'INSERT INTO public.core_npc_master_history
            (npc_id, npc_name, lock_profile, extended_data, plugin_extended_data, gamets_last_updated)
         VALUES ($1, $2, 0, $3::jsonb, $4::jsonb, 100)',
        [
            7003,
            'Synthetic Listener',
            json_encode(['relationships' => ['Synthetic Target' => ['aff' => 12, 'type' => 'neutral', 'note' => 'historical relationship']], 'fixture_marker' => 'historical'], JSON_THROW_ON_ERROR),
            json_encode(['mind_poisoning' => [
                'playthrough_id' => 'unprofiled',
                'floor_event_id' => 2998,
                'events' => [['event_id' => 3000, 'utterance_id' => 'utt_restore_retained_3000']],
                'reflection_state' => ['basis' => str_repeat('a', 64), 'subjects' => ['npc:7003']],
            ]], JSON_THROW_ON_ERROR),
        ]
    );
    foreach ([
        [7001, 'Aela', ['relationships' => new stdClass()]],
        [7002, 'Synthetic Target', ['relationships' => new stdClass()]],
    ] as [$id, $name, $extended]) {
        $db->execQuery(
            'INSERT INTO public.core_npc_master
                (id, npc_name, lock_profile, extended_data, plugin_extended_data, gamets_last_updated, md5)
             VALUES ($1, $2, 0, $3::jsonb, $4::jsonb, 0, $5)',
            [$id, $name, json_encode($extended, JSON_THROW_ON_ERROR), '{}', 'synthetic-' . $id]
        );
    }

    $oldInit = 2999;
    $preEvent = 3000;
    $atBoundaryEvent = 3001;
    $futureEvent = 3002;
    restoreFixtureInsertEventAtId($db, $oldInit, 50, 'init', 'fixture-old-init');
    $retainedSourceData = 'Aela: Synthetic Target kept the promise. (talking to Synthetic Listener)';
    restoreFixtureInsertEventAtId($db, $preEvent, 99, 'chat', $retainedSourceData, 'utt_restore_retained_3000', '|Aela|Synthetic Listener|Synthetic Target|');
    restoreFixtureInsertEventAtId($db, $atBoundaryEvent, 100, 'chat', 'fixture-at-boundary', 'utt_restore_pruned_3001', '|Aela|Synthetic Listener|');
    restoreFixtureInsertEventAtId($db, $futureEvent, 101, 'chat', 'fixture-after-boundary', 'utt_restore_pruned_3002', '|Aela|Synthetic Listener|');
    $db->execQuery("SELECT setval('public.eventlog_rowid_seq', 3002, true)");
    $db->execQuery(
        "INSERT INTO public.speech (ts, gamets, speaker, speech, sess, localts)
         VALUES (1700000000, 99, 'Synthetic Speaker', 'pre-boundary speech', 'spoken', 1),
                (1700000000, 100, 'Synthetic Speaker', 'at-boundary speech', 'spoken', 1),
                (1700000000, 101, 'Synthetic Speaker', 'after-boundary speech', 'spoken', 1)"
    );
    $db->execQuery('TRUNCATE public.restore_fixture_trace RESTART IDENTITY');

    return [
        'old_init_rowid' => $oldInit,
        'pre_event_rowid' => $preEvent,
        'retained_source_data' => $retainedSourceData,
        'at_boundary_event_rowid' => $atBoundaryEvent,
        'future_event_rowid' => $futureEvent,
        'pre_run_sequence_last_value' => (int)($db->fetchOne('SELECT last_value FROM public.eventlog_rowid_seq')['last_value'] ?? 0),
    ];
}

function restoreFixtureReadNpc(RestoreInitFixtureDb $db): array
{
    $row = $db->fetchOne(
        'SELECT id, gamets_last_updated::text AS gamets_last_updated,
                extended_data::text AS extended_data_json,
                plugin_extended_data::text AS plugin_extended_data_json
         FROM public.core_npc_master WHERE id = 7003'
    );
    restoreFixtureAssert(is_array($row), 'Synthetic NPC was missing after core INIT.');
    $row['extended_data'] = json_decode((string)$row['extended_data_json'], true, 512, JSON_THROW_ON_ERROR);
    $row['plugin_extended_data'] = json_decode((string)$row['plugin_extended_data_json'], true, 512, JSON_THROW_ON_ERROR);
    return $row;
}

function restoreFixtureRunNativeStoreChecks(
    RestoreInitFixtureDb $db,
    string $engine,
    int $firstInitRowId,
    array $seed
): array {
    $pluginRoot = restoreFixtureRequireEnv('RESTORE_FIXTURE_PLUGIN_ROOT');
    restoreFixtureVerifyHash(
        $pluginRoot . '/server/store.php',
        restoreFixtureRequireEnv('RESTORE_FIXTURE_STORE_SHA256')
    );
    restoreFixtureVerifyHash(
        $pluginRoot . '/server/restore_policy.php',
        restoreFixtureRequireEnv('RESTORE_FIXTURE_POLICY_SHA256')
    );
    $GLOBALS['ENGINE_PATH'] = $engine . '/';
    $GLOBALS['NEVER_CLEAR_RELATIONSHIP_DATA'] = true;
    $GLOBALS['all_fast_commands'] = ['_speech'];
    $GLOBALS['runid'] = 'restore-policy-store-fixture';
    require_once $pluginRoot . '/server/store.php';

    $store = new \ChimMindPoisoning\PostgresStoreDb();
    $oldFields = [];
    $oldInitId = null;
    $oldStatus = \ChimMindPoisoning\captureRestorePolicyEventBoundary(
        $store,
        [(int)$seed['pre_event_rowid']],
        ['_speech'],
        false,
        null,
        $oldFields,
        $oldInitId
    );
    restoreFixtureAssert(
        $oldStatus === 'restore-policy' && ($oldFields['reason'] ?? null) === 'restore-policy-source-before-init',
        'Production store accepted a retained source row at or before the actual INIT marker.'
    );

    $writeText = 'Synthetic Target kept a new promise.';
    $writeUtterance = 'utt_restore_native_write_0001';
    $writeEventId = restoreFixtureInsertAckEvent($db, 101, $writeUtterance, $writeText);
    $writeFields = [];
    $writeInitId = null;
    $writeBoundaryStatus = \ChimMindPoisoning\captureRestorePolicyEventBoundary(
        $store,
        [$writeEventId],
        ['_speech'],
        false,
        null,
        $writeFields,
        $writeInitId
    );
    restoreFixtureAssert($writeBoundaryStatus === null && $writeInitId === $firstInitRowId, 'Production store rejected a new source after INIT.');
    $writeEvent = restoreFixtureBuildNativeAckEvent($store, $writeEventId, $writeUtterance, $writeText, $writeInitId);
    $listener = $store->npcById(7003);
    restoreFixtureAssert(is_array($listener), 'Production store could not load the restore owner row.');
    $writeFields = [];
    restoreFixtureAssert(
        \ChimMindPoisoning\prepareRestorePolicyRecipient($writeEvent, $listener, $store, null, $writeFields) === null,
        'Production recipient helper rejected a matching-scope restored owner.'
    );

    $subjects = ['npc:7002' => ['id' => 7002, 'name' => 'Synthetic Target']];
    $judgments = ['npc:7002' => [
        'delta' => 1,
        'reason' => 'The new promise supports trust.',
        'evidence' => $writeText,
    ]];
    $historyBeforeWrite = (int)$db->fetchOne('SELECT count(*) AS count FROM public.core_npc_master_history')['count'];
    $writeStatus = \ChimMindPoisoning\persistJudgments($writeEvent, $subjects, $judgments, $store);
    restoreFixtureAssert($writeStatus === 'committed', 'Production PostgreSQL persistence did not commit the eligible post-INIT event: ' . $writeStatus);
    $afterWrite = $store->npcById(7003);
    $afterWriteLedger = $afterWrite['plugin_extended_data']->mind_poisoning ?? null;
    $afterWriteEventIds = array_map(
        static fn(mixed $row): int => (int)($row->event_id ?? 0),
        is_array($afterWriteLedger->events ?? null) ? $afterWriteLedger->events : []
    );
    restoreFixtureAssert(
        ($afterWrite['extended_data']->relationships->{'Synthetic Target'}->aff ?? null) === 14
        && in_array($writeEventId, $afterWriteEventIds, true),
        'Production PostgreSQL persistence did not save both the relationship change and event receipt.'
    );
    $historyAfterWrite = (int)$db->fetchOne('SELECT count(*) AS count FROM public.core_npc_master_history')['count'];
    restoreFixtureAssert($historyAfterWrite === $historyBeforeWrite + 1, 'Production persistence did not create one verified CHIM history snapshot.');

    $retryListener = $store->npcById(7003);
    $retryFields = [];
    restoreFixtureAssert(
        \ChimMindPoisoning\prepareRestorePolicyRecipient($writeEvent, $retryListener, $store, null, $retryFields) === null,
        'Production recipient helper rejected the exact committed retry before dedupe.'
    );
    $retryStatus = \ChimMindPoisoning\persistJudgments($writeEvent, $subjects, $judgments, $store);
    $afterRetry = $store->npcById(7003);
    restoreFixtureAssert(
        $retryStatus === 'duplicate'
        && ($afterRetry['extended_data']->relationships->{'Synthetic Target'}->aff ?? null) === 14
        && (int)$db->fetchOne('SELECT count(*) AS count FROM public.core_npc_master_history')['count'] === $historyAfterWrite,
        'Exact post-INIT retry changed state or created another history snapshot.'
    );

    $orderedText = 'Synthetic Target kept a promise through the restore boundary.';
    $orderedUtterance = 'utt_restore_native_ordered_0001';
    $orderedEventId = restoreFixtureInsertAckEvent($db, 101, $orderedUtterance, $orderedText);
    $orderedFields = [];
    $orderedInitId = null;
    $orderedBoundaryStatus = \ChimMindPoisoning\captureRestorePolicyEventBoundary(
        $store,
        [$orderedEventId],
        ['_speech'],
        false,
        null,
        $orderedFields,
        $orderedInitId
    );
    restoreFixtureAssert($orderedBoundaryStatus === null && $orderedInitId === $firstInitRowId, 'Production store rejected the ordered write source before the second INIT.');

    $barrierStore = new \ChimMindPoisoning\PostgresStoreDb();
    restoreFixtureAssert($barrierStore->acquireRestorePolicyBarrier(), 'Production store could not acquire the real MAIN barrier.');
    $barrierHeld = true;
    $listenerTransaction = false;
    $mainChild = null;
    try {
        $mainChild = restoreFixtureSpawnMain($engine, 102);
        $preLockMarker = null;
        $deadline = microtime(true) + 8.0;
        while (microtime(true) < $deadline) {
            $preLockMarker = $db->fetchOne(
                "SELECT rowid FROM public.eventlog WHERE type='user_input' AND gamets=102 ORDER BY rowid DESC LIMIT 1"
            );
            if (is_array($preLockMarker)) {
                break;
            }
            $status = proc_get_status($mainChild['process']);
            restoreFixtureAssert($status['running'], 'Pinned main.php child exited before the store-barrier check.');
            usleep(20000);
        }
        restoreFixtureAssert(is_array($preLockMarker), 'Pinned main.php child did not reach its pre-lock marker at the second INIT.');
        usleep(150000);
        restoreFixtureAssert(proc_get_status($mainChild['process'])['running'], 'Pinned main.php did not wait on the store-owned MAIN barrier.');
        restoreFixtureAssert(
            $db->fetchOne("SELECT rowid FROM public.eventlog WHERE type='init' AND gamets=102 ORDER BY rowid DESC LIMIT 1") === null,
            'Pinned comm INIT passed the store-owned MAIN barrier before the write.'
        );

        $listenerTransaction = true;
        restoreFixtureAssert($barrierStore->beginForListener(7003), 'Production store could not begin the listener write under MAIN.');
        $orderedOwner = $barrierStore->npcById(7003, true);
        restoreFixtureAssert(is_array($orderedOwner), 'Production store could not lock the owner row for the ordered write.');
        $orderedNamespace = \ChimMindPoisoning\storedLedgerNamespace($orderedOwner['plugin_extended_data']);
        restoreFixtureAssert(is_array($orderedNamespace), 'Production store rejected its current namespace for the ordered write.');
        $orderedJudgments = ['npc:7002' => [
            'delta' => 1,
            'reason' => 'The promise supports trust.',
            'evidence' => $orderedText,
        ]];
        $nextOrderedLedger = \ChimMindPoisoning\nextLedger(
            $orderedNamespace,
            'unprofiled',
            $orderedEventId,
            $orderedUtterance,
            $orderedJudgments
        );
        $orderedEdge = clone $orderedOwner['extended_data']->relationships->{'Synthetic Target'};
        $orderedEdge->aff = (int)$orderedEdge->aff + 1;
        $orderedExtended = clone $orderedOwner['extended_data'];
        $orderedRelationships = clone $orderedExtended->relationships;
        $orderedRelationships->{'Synthetic Target'} = $orderedEdge;
        $orderedExtended->relationships = $orderedRelationships;
        $orderedPlugins = clone $orderedOwner['plugin_extended_data'];
        $orderedPlugins->mind_poisoning = (object)$nextOrderedLedger;
        restoreFixtureAssert(
            $barrierStore->writeNpc(7003, ['Synthetic Target' => $orderedEdge], (object)$nextOrderedLedger, 101.0),
            'Production store did not write the ordered relationship and receipt under MAIN.'
        );
        $orderedWritten = $barrierStore->npcById(7003);
        restoreFixtureAssert(
            is_array($orderedWritten)
            && \ChimMindPoisoning\sameJsonValue($orderedWritten['extended_data'] ?? null, $orderedExtended)
            && \ChimMindPoisoning\sameJsonValue($orderedWritten['plugin_extended_data'] ?? null, $orderedPlugins)
            && (float)($orderedWritten['gamets_last_updated'] ?? -1) === 101.0
            && $barrierStore->backupAndVerify(7003, [
                'extended_data' => $orderedExtended,
                'plugin_extended_data' => $orderedPlugins,
                'gamets_last_updated' => 101.0,
            ]),
            'Production store did not verify the ordered NPC write and CHIM history snapshot.'
        );
        restoreFixtureAssert($barrierStore->commit(), 'Production store did not commit the ordered listener transaction.');
        $listenerTransaction = false;
        $barrierStore->release();
        restoreFixtureAssert(!$barrierStore->cleanupFailed(), 'Production store reported a cleanup failure after the ordered write.');

        $beforeSecondRestore = restoreFixtureReadNpc($db);
        $beforeSecondEventIds = array_map(static fn(array $row): int => (int)($row['event_id'] ?? 0), $beforeSecondRestore['plugin_extended_data']['mind_poisoning']['events'] ?? []);
        restoreFixtureAssert(
            ($beforeSecondRestore['extended_data']['relationships']['Synthetic Target']['aff'] ?? null) === 15
            && in_array($writeEventId, $beforeSecondEventIds, true)
            && in_array($orderedEventId, $beforeSecondEventIds, true)
            && is_array($store->eventById($orderedEventId, $orderedUtterance)),
            'The ordered store write was not visible before the waiting INIT resumed.'
        );
        $barrierStore->releaseRestorePolicyBarrier();
        $barrierHeld = false;
        $secondInitResult = restoreFixtureWaitChild($mainChild, 30000);
        $mainChild = null;
        restoreFixtureAssert(!$secondInitResult['timed_out'] && $secondInitResult['exit_code'] === 0, 'Pinned second INIT failed after the ordered store commit: ' . trim($secondInitResult['stderr']));
    } finally {
        if ($listenerTransaction) {
            $barrierStore->rollback();
            $barrierStore->release();
        }
        if ($barrierHeld) {
            $barrierStore->releaseRestorePolicyBarrier();
        }
        if (is_array($mainChild)) {
            restoreFixtureWaitChild($mainChild, 5000);
        }
    }

    $secondInit = $db->fetchOne(
        "SELECT rowid::text AS rowid, gamets::text AS gamets FROM public.eventlog WHERE type='init' ORDER BY rowid DESC LIMIT 1"
    );
    restoreFixtureAssert(is_array($secondInit) && (int)$secondInit['gamets'] === 102, 'The second actual INIT marker is missing.');
    $secondInitRowId = (int)$secondInit['rowid'];
    $afterSecondRestore = restoreFixtureReadNpc($db);
    $restoredWriteEventIds = array_map(static fn(array $row): int => (int)($row['event_id'] ?? 0), $afterSecondRestore['plugin_extended_data']['mind_poisoning']['events'] ?? []);
    restoreFixtureAssert(
        ($afterSecondRestore['extended_data']['relationships']['Synthetic Target']['aff'] ?? null) === 15
        && in_array($writeEventId, $restoredWriteEventIds, true)
        && in_array($orderedEventId, $restoredWriteEventIds, true)
        && is_array($store->eventById($writeEventId, $writeUtterance)),
        'The actual second INIT did not retain the ordered relationship, receipts and source rows.'
    );
    $afterLoadFields = [];
    $afterLoadInitId = null;
    $afterLoadStatus = \ChimMindPoisoning\captureRestorePolicyEventBoundary(
        $store,
        [$writeEventId],
        ['_speech'],
        false,
        null,
        $afterLoadFields,
        $afterLoadInitId
    );
    restoreFixtureAssert(
        $afterLoadStatus === 'restore-policy' && ($afterLoadFields['reason'] ?? null) === 'restore-policy-source-before-init'
        && $afterLoadInitId === null,
        'Production store did not fence the same surviving event after the second INIT.'
    );

    $baselineText = 'Synthetic Target shared another new fact.';
    $baselineUtterance = 'utt_restore_native_baseline_0001';
    $baselineEventId = restoreFixtureInsertAckEvent($db, 103, $baselineUtterance, $baselineText);
    $db->execQuery(
        "UPDATE public.core_npc_master
         SET plugin_extended_data = jsonb_set(plugin_extended_data, '{mind_poisoning,playthrough_id}', to_jsonb($1::text), false)
         WHERE id = $2",
        ['synthetic-imported-scope', 7003]
    );
    $baselineInitId = \ChimMindPoisoning\captureRestorePolicyBoundary(
        $store,
        [$baselineEventId],
        ['_speech'],
        false,
        $baselineReason
    );
    restoreFixtureAssert(is_int($baselineInitId) && $baselineInitId === $secondInitRowId, 'Could not capture the baseline fixture INIT fence.');
    $baselineEvent = restoreFixtureBuildNativeAckEvent($store, $baselineEventId, $baselineUtterance, $baselineText, $baselineInitId);
    $relationshipsBeforeBaseline = json_encode($store->npcById(7003)['extended_data']->relationships, JSON_THROW_ON_ERROR);
    $historyBeforeBaselineFailure = (int)$db->fetchOne('SELECT count(*) AS count FROM public.core_npc_master_history')['count'];
    $db->execQuery(<<<'SQL'
CREATE FUNCTION public.restore_fixture_fail_history_insert() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'intentional isolated baseline history failure';
END;
$$
SQL);
    $db->execQuery(<<<'SQL'
CREATE TRIGGER restore_fixture_fail_history_insert
BEFORE INSERT ON public.core_npc_master_history
FOR EACH ROW EXECUTE FUNCTION public.restore_fixture_fail_history_insert()
SQL);
    $baselineFailureFields = [];
    $failedListener = $store->npcById(7003);
    $baselineFailureStatus = \ChimMindPoisoning\prepareRestorePolicyRecipient($baselineEvent, $failedListener, $store, null, $baselineFailureFields);
    $db->execQuery('DROP TRIGGER restore_fixture_fail_history_insert ON public.core_npc_master_history');
    $db->execQuery('DROP FUNCTION public.restore_fixture_fail_history_insert()');
    $afterBaselineFailure = $store->npcById(7003);
    restoreFixtureAssert(
        $baselineFailureStatus === 'failed'
        && ($baselineFailureFields['reason'] ?? null) === 'restore-policy-baseline-failed'
        && ($baselineFailureFields['commit_state'] ?? null) === 'not_attempted'
        && ($baselineFailureFields['committed'] ?? null) === false
        && ($afterBaselineFailure['plugin_extended_data']->mind_poisoning->playthrough_id ?? null) === 'synthetic-imported-scope'
        && json_encode($afterBaselineFailure['extended_data']->relationships, JSON_THROW_ON_ERROR) === $relationshipsBeforeBaseline
        && (int)$db->fetchOne('SELECT count(*) AS count FROM public.core_npc_master_history')['count'] === $historyBeforeBaselineFailure,
        'Failed baseline did not roll back atomically while preserving relationships and the imported namespace.'
    );

    $baselineFields = [];
    $baselineListener = $store->npcById(7003);
    $baselineStatus = \ChimMindPoisoning\prepareRestorePolicyRecipient($baselineEvent, $baselineListener, $store, null, $baselineFields);
    $afterBaseline = $store->npcById(7003);
    $baselineLedger = $afterBaseline['plugin_extended_data']->mind_poisoning ?? null;
    $afterBaselineBounds = $store->restoreEventBounds();
    $historySnapshot = $db->fetchOne(
        'SELECT plugin_extended_data::text AS plugin_extended_data FROM public.core_npc_master_history WHERE npc_id = $1 ORDER BY history_id DESC LIMIT 1',
        [7003]
    );
    $historyPlugins = is_array($historySnapshot) ? json_decode((string)$historySnapshot['plugin_extended_data']) : null;
    restoreFixtureAssert(
        $baselineStatus === 'restore-policy'
        && ($baselineFields['reason'] ?? null) === 'restore-policy-scope-baselined'
        && ($baselineFields['commit_state'] ?? null) === 'confirmed'
        && ($baselineFields['committed'] ?? null) === true
        && ($baselineFields['model_outcome'] ?? null) === 'not_called'
        && ($baselineLedger->playthrough_id ?? null) === 'unprofiled'
        && ($baselineLedger->floor_event_id ?? null) === ($afterBaselineBounds['max_event_id'] ?? null)
        && ($baselineLedger->events ?? null) === []
        && json_encode($afterBaseline['extended_data']->relationships, JSON_THROW_ON_ERROR) === $relationshipsBeforeBaseline
        && \ChimMindPoisoning\sameJsonValue($historyPlugins, $afterBaseline['plugin_extended_data']),
        'Successful baseline did not preserve relationships and round-trip its new JSON namespace through core NPC history.'
    );

    return [
        'retained_pre_init_source_rejected' => true,
        'post_init_store_write' => [
            'event_id' => $writeEventId,
            'persist' => $writeStatus,
            'exact_retry' => $retryStatus,
            'relationship_after_write_and_second_init' => $afterSecondRestore['extended_data']['relationships']['Synthetic Target']['aff'],
            'receipt_survived_second_init' => true,
        ],
        'ordered_write_before_init' => [
            'event_id' => $orderedEventId,
            'store_methods' => ['beginForListener', 'writeNpc', 'backupAndVerify', 'commit'],
            'init_waited_for_main_release' => true,
            'relationship_after_commit_and_init' => $afterSecondRestore['extended_data']['relationships']['Synthetic Target']['aff'],
            'receipt_survived_init' => true,
        ],
        'store_main_barrier_blocked_actual_init' => true,
        'second_init' => $secondInit,
        'post_second_init_source_rejected' => true,
        'baseline_fault' => [
            'result' => $baselineFailureStatus,
            'reason' => $baselineFailureFields['reason'],
            'rolled_back' => true,
        ],
        'baseline_roundtrip' => [
            'result' => $baselineStatus,
            'reason' => $baselineFields['reason'],
            'commit_state' => $baselineFields['commit_state'],
            'scope' => $baselineLedger->playthrough_id,
            'floor_event_id' => $baselineLedger->floor_event_id,
            'history_json_matches' => true,
        ],
    ];
}

function restoreFixtureSpawnMain(string $engine, int $gamets = 100): array
{
    $php = restoreFixtureRequireEnv('RESTORE_FIXTURE_PHP_BIN');
    $extensionDir = restoreFixtureRequireEnv('RESTORE_FIXTURE_PHP_EXTENSION_DIR');
    $extensionArgs = [
        '-d', 'extension_dir=' . $extensionDir,
        '-d', 'extension=pgsql.so',
        '-d', 'extension=mbstring.so',
    ];
    $sysvsemExtension = getenv('RESTORE_FIXTURE_SYSVSEM_EXTENSION');
    if (is_string($sysvsemExtension) && $sysvsemExtension !== '') {
        if ($sysvsemExtension !== 'sysvsem.so') {
            throw new RuntimeException('Unexpected sysvsem extension name.');
        }
        array_push($extensionArgs, '-d', 'extension=' . $sysvsemExtension);
    }
    $command = array_merge([$php, '-n'], $extensionArgs, [__FILE__, '--main-child']);
    $env = [
        'PATH' => restoreFixtureRequireEnv('RESTORE_FIXTURE_SAFE_PATH'),
        'HOME' => restoreFixtureRequireEnv('RESTORE_FIXTURE_PG_HOME'),
        'TMPDIR' => restoreFixtureRequireEnv('RESTORE_FIXTURE_PG_TMP'),
        'LANG' => 'C',
        'LC_ALL' => 'C',
        'PHPUNIT_TEST' => '1',
        'PGHOST' => restoreFixtureRequireEnv('PGHOST'),
        'PGPORT' => restoreFixtureRequireEnv('PGPORT'),
        'PGDATABASE' => restoreFixtureRequireEnv('PGDATABASE'),
        'PGUSER' => restoreFixtureRequireEnv('PGUSER'),
        'RESTORE_FIXTURE_ROLE' => 'main-child',
        'RESTORE_FIXTURE_INIT_GAMETS' => (string)$gamets,
        'RESTORE_FIXTURE_EXPECTED_ROOT' => restoreFixtureRequireEnv('RESTORE_FIXTURE_EXPECTED_ROOT'),
        'RESTORE_FIXTURE_PINNED_ROOT' => restoreFixtureRequireEnv('RESTORE_FIXTURE_PINNED_ROOT'),
        'RESTORE_FIXTURE_EXPECTED_DATA' => restoreFixtureRequireEnv('RESTORE_FIXTURE_EXPECTED_DATA'),
        'RESTORE_FIXTURE_EXPECTED_SOCKET' => restoreFixtureRequireEnv('RESTORE_FIXTURE_EXPECTED_SOCKET'),
        'RESTORE_FIXTURE_ENGINE' => $engine,
        'RESTORE_FIXTURE_PHP_BIN' => $php,
        'RESTORE_FIXTURE_PHP_EXTENSION_DIR' => $extensionDir,
        'RESTORE_FIXTURE_SYSVSEM_EXTENSION' => is_string($sysvsemExtension) ? $sysvsemExtension : '',
        'RESTORE_FIXTURE_PHP_SAFE_PATH' => restoreFixtureRequireEnv('RESTORE_FIXTURE_SAFE_PATH'),
        'RESTORE_FIXTURE_PG_HOME' => restoreFixtureRequireEnv('RESTORE_FIXTURE_PG_HOME'),
        'RESTORE_FIXTURE_PG_TMP' => restoreFixtureRequireEnv('RESTORE_FIXTURE_PG_TMP'),
    ];
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, dirname($engine), $env);
    restoreFixtureAssert(is_resource($process), 'Could not start the exact main.php child process.');
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    return ['process' => $process, 'pipes' => $pipes];
}

function restoreFixtureWaitChild(array $child, int $timeoutMs): array
{
    $process = $child['process'];
    $pipes = $child['pipes'];
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + ($timeoutMs / 1000);
    $status = proc_get_status($process);
    while ($status['running'] && microtime(true) < $deadline) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        usleep(20000);
        $status = proc_get_status($process);
    }
    if ($status['running']) {
        proc_terminate($process);
        $killDeadline = microtime(true) + 2.0;
        do {
            usleep(20000);
            $status = proc_get_status($process);
        } while ($status['running'] && microtime(true) < $killDeadline);
        $timedOut = true;
    } else {
        $timedOut = false;
    }
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closeExit = proc_close($process);
    $exit = isset($status['exitcode']) && $status['exitcode'] >= 0 ? $status['exitcode'] : $closeExit;
    return ['exit_code' => $exit, 'timed_out' => $timedOut, 'stdout' => $stdout, 'stderr' => $stderr];
}

function restoreFixtureFindTrace(array $trace, string $operation, ?string $eventType = null, ?int $rowId = null, ?int $npcId = null): ?array
{
    foreach ($trace as $row) {
        if (($row['operation'] ?? null) !== $operation) {
            continue;
        }
        if ($eventType !== null && ($row['event_type'] ?? null) !== $eventType) {
            continue;
        }
        if ($rowId !== null && (int)($row['row_id'] ?? 0) !== $rowId) {
            continue;
        }
        if ($npcId !== null && (int)($row['npc_id'] ?? 0) !== $npcId) {
            continue;
        }
        return $row;
    }
    return null;
}

function restoreFixtureSourceGateEvidence(string $pinnedRoot): array
{
    $mainPath = $pinnedRoot . '/main.php';
    restoreFixtureVerifyHash($mainPath, '3619049e6d54493a77b19c09f8b70420e3c35427545a8bb6e95e93c6fe57ebac');
    $source = file_get_contents($mainPath);
    restoreFixtureAssert(is_string($source), 'Could not read pinned main.php.');
    restoreFixtureAssert(
        preg_match('/\$fast_commands\s*=\s*\[(?<items>.*?)\];/s', $source, $match) === 1,
        'Could not locate pinned main fast-command list.'
    );
    $items = $match['items'];
    restoreFixtureAssert(preg_match('/["\']_speech["\']/', $items) === 1, 'Pinned _speech ACK route is no longer in the fast-command list.');
    restoreFixtureAssert(preg_match('/["\']init["\']/', $items) !== 1, 'Pinned init unexpectedly entered the fast-command list.');
    restoreFixtureAssert(
        preg_match('/SemaphoreWait\("MAIN",\s*\$semaphore_timeout,\s*1003,\s*null\)/', $source) === 1,
        'Pinned main semaphore call no longer uses MAIN/1003 for non-fast requests.'
    );
    return [
        'init_has_main_semaphore' => true,
        'main_semaphore_tick_argument' => 1003,
        'speech_ack_route_in_fast_list' => true,
        'speech_ack_runtime_exercised' => false,
    ];
}

function restoreFixtureRunMainChild(string $engine): never
{
    restoreFixtureAssert(getenv('RESTORE_FIXTURE_ROLE') === 'main-child', 'Unexpected child role.');
    $gamets = filter_var(getenv('RESTORE_FIXTURE_INIT_GAMETS') ?: '100', FILTER_VALIDATE_INT);
    restoreFixtureAssert($gamets !== false && $gamets > 0, 'Unexpected INIT timestamp.');
    $_GET = [];
    $_SERVER['QUERY_STRING'] = 'DATA=' . base64_encode('init|1700000000|' . $gamets . '|');
    $_SERVER['SCRIPT_FILENAME'] = $engine . '/main.php';
    $_SERVER['SCRIPT_NAME'] = '/main.php';
    require $engine . '/main.php';
    throw new RuntimeException('Exact pinned main.php returned instead of terminating its init request.');
}

function restoreFixtureDriver(): void
{
    $root = restoreFixtureRequireEnv('RESTORE_FIXTURE_EXPECTED_ROOT');
    $pinnedRoot = restoreFixtureRequireEnv('RESTORE_FIXTURE_PINNED_ROOT');
    $engine = restoreFixtureBuildEngine();
    $db = restoreFixtureOpenDb();
    $GLOBALS['db'] = $db;
    sql::$link = $db->nativeConnection();
    $databaseIdentity = restoreFixtureAssertTarget($db);
    restoreFixtureInstallTrace($db);
    $seed = restoreFixtureSeed($db);
    $staticGate = restoreFixtureSourceGateEvidence($pinnedRoot);

    require_once $engine . '/lib/logger.php';
    require_once $engine . '/lib/semaphore_manager.class.php';
    restoreFixtureAssert(function_exists('sem_get'), 'PHP sysvsem extension is not loaded.');

    $GLOBALS['runid'] = 'restore-init-parent';
    $lockAcquired = SemaphoreManager::wait('MAIN', 1, 1003, null);
    restoreFixtureAssert($lockAcquired, 'Parent could not acquire the exact MAIN semaphore.');

    $child = null;
    $lockHeld = true;
    try {
        $child = restoreFixtureSpawnMain($engine);
        $deadline = microtime(true) + 8.0;
        $preLockMarker = null;
        while (microtime(true) < $deadline) {
            $preLockMarker = $db->fetchOne(
                "SELECT rowid FROM public.eventlog WHERE type='user_input' AND gamets=100 ORDER BY rowid DESC LIMIT 1"
            );
            if (is_array($preLockMarker)) {
                break;
            }
            $status = proc_get_status($child['process']);
            restoreFixtureAssert($status['running'], 'Pinned main.php child exited before its pre-lock init marker.');
            usleep(20000);
        }
        restoreFixtureAssert(is_array($preLockMarker), 'Pinned main.php child did not write its pre-lock input marker.');
        $preLockRowId = (int)$preLockMarker['rowid'];
        usleep(150000);
        $status = proc_get_status($child['process']);
        restoreFixtureAssert($status['running'], 'Pinned main.php did not wait on MAIN while the fixture owned the semaphore.');
        $blockedMarker = $db->fetchOne("SELECT rowid FROM public.eventlog WHERE type='init' AND gamets=100 ORDER BY rowid DESC LIMIT 1");
        restoreFixtureAssert($blockedMarker === null, 'Core comm INIT ran before the MAIN semaphore was released.');
        $beforeRestore = restoreFixtureReadNpc($db);
        restoreFixtureAssert(($beforeRestore['extended_data']['relationships']['Synthetic Target']['aff'] ?? null) === 13, 'NPC row changed before the MAIN semaphore was released.');
        restoreFixtureAssert(
            count($beforeRestore['plugin_extended_data']['mind_poisoning']['events'] ?? []) === 2,
            'Current MP ledger changed before the MAIN semaphore was released.'
        );

        restoreFixtureAssert(SemaphoreManager::release('MAIN'), 'Parent could not release its owned MAIN semaphore.');
        $lockHeld = false;
        $childResult = restoreFixtureWaitChild($child, 30000);
        $child = null;
        restoreFixtureAssert(!$childResult['timed_out'], 'Pinned main.php init child exceeded its 30-second bound.');
        restoreFixtureAssert($childResult['exit_code'] === 0, 'Pinned main.php init child failed: ' . trim($childResult['stderr']));

        $latestInit = $db->fetchOne(
            "SELECT rowid::text AS rowid, gamets::text AS gamets, sess
             FROM public.eventlog WHERE type='init' ORDER BY rowid DESC LIMIT 1"
        );
        restoreFixtureAssert(is_array($latestInit), 'Actual comm INIT marker is missing.');
        $latestInitRowId = (int)$latestInit['rowid'];
        restoreFixtureAssert((int)$latestInit['gamets'] === 100, 'Latest INIT marker has an unexpected game timestamp.');
        restoreFixtureAssert($latestInitRowId > (int)$seed['pre_run_sequence_last_value'], 'Eventlog row ID did not advance beyond the seed sequence value.');
        restoreFixtureAssert(($latestInit['sess'] ?? null) === 'pending', 'Actual comm INIT marker has an unexpected session state.');

        $preSource = $db->fetchOne("SELECT rowid FROM public.eventlog WHERE rowid=$1 AND data=$2", [$seed['pre_event_rowid'], $seed['retained_source_data']]);
        $atSource = $db->fetchOne("SELECT rowid FROM public.eventlog WHERE rowid=$1", [$seed['at_boundary_event_rowid']]);
        $futureSource = $db->fetchOne("SELECT rowid FROM public.eventlog WHERE rowid=$1", [$seed['future_event_rowid']]);
        $preLockSource = $db->fetchOne("SELECT rowid FROM public.eventlog WHERE rowid=$1 AND type='user_input'", [$preLockRowId]);
        restoreFixtureAssert(is_array($preSource), 'Pre-boundary event was not retained.');
        restoreFixtureAssert($atSource === null && $futureSource === null, 'Inclusive at/after-boundary event pruning failed.');
        restoreFixtureAssert($preLockSource === null, 'The main pre-lock input marker was not pruned by the actual INIT handler.');

        $speechCounts = $db->fetchOne(
            "SELECT count(*) FILTER (WHERE gamets=99)::text AS before_count,
                    count(*) FILTER (WHERE gamets>=100)::text AS at_or_after_count
             FROM public.speech"
        );
        restoreFixtureAssert(($speechCounts['before_count'] ?? null) === '1', 'Pre-boundary speech row was not retained.');
        restoreFixtureAssert(($speechCounts['at_or_after_count'] ?? null) === '0', 'At/after-boundary speech rows were not pruned.');

        $afterRestore = restoreFixtureReadNpc($db);
        restoreFixtureAssert(($afterRestore['extended_data']['relationships']['Synthetic Target']['aff'] ?? null) === 13, 'Actual INIT did not retain the current relationship under true restore policy.');
        restoreFixtureAssert(($afterRestore['extended_data']['fixture_marker'] ?? null) === 'historical', 'Actual INIT did not restore the historical extended-data marker.');
        restoreFixtureAssert(($afterRestore['plugin_extended_data']['mind_poisoning']['floor_event_id'] ?? null) === 2998, 'Actual INIT did not restore the historical MP floor.');
        $restoredEvents = array_map(static fn(array $row): int => (int)($row['event_id'] ?? 0), $afterRestore['plugin_extended_data']['mind_poisoning']['events'] ?? []);
        restoreFixtureAssert($restoredEvents === [3000], 'Actual INIT did not restore the historical MP event list.');
        restoreFixtureAssert(($afterRestore['plugin_extended_data']['mind_poisoning']['reflection_state']['basis'] ?? null) === str_repeat('a', 64), 'Actual INIT did not restore historical reflection state.');
        restoreFixtureAssert((float)$afterRestore['gamets_last_updated'] === 100.0, 'Restored NPC game timestamp is incorrect.');

        $trace = $db->fetchAll('SELECT trace_id::text AS trace_id, operation, row_id::text AS row_id, event_type, gamets::text AS gamets, npc_id::text AS npc_id FROM public.restore_fixture_trace ORDER BY trace_id');
        $initInsertTrace = restoreFixtureFindTrace($trace, 'eventlog-insert', 'init', $latestInitRowId);
        $inputDeleteTrace = restoreFixtureFindTrace($trace, 'eventlog-delete', 'user_input', $preLockRowId);
        $npcDeleteTrace = restoreFixtureFindTrace($trace, 'npc-delete', null, null, 7003);
        $npcInsertTrace = restoreFixtureFindTrace($trace, 'npc-insert', null, null, 7003);
        restoreFixtureAssert(is_array($initInsertTrace) && is_array($inputDeleteTrace), 'INIT marker or pre-lock deletion was not traced.');
        restoreFixtureAssert(is_array($npcDeleteTrace) && is_array($npcInsertTrace), 'Actual NpcMaster restore writes were not traced.');
        restoreFixtureAssert((int)$inputDeleteTrace['trace_id'] < (int)$initInsertTrace['trace_id'], 'Pre-lock event pruning did not precede the new INIT marker.');
        restoreFixtureAssert(
            (int)$initInsertTrace['trace_id'] < (int)$npcDeleteTrace['trace_id']
            && (int)$initInsertTrace['trace_id'] < (int)$npcInsertTrace['trace_id'],
            'Actual NPC restore ran before the INIT marker was inserted.'
        );
        $sequence = $db->fetchOne('SELECT last_value::text AS last_value, is_called FROM public.eventlog_rowid_seq');
        restoreFixtureAssert((int)($sequence['last_value'] ?? 0) >= $latestInitRowId, 'Eventlog sequence was reset below the latest INIT marker.');

        $inertCalls = $db->fetchAll(
            "SELECT DISTINCT event_type AS table FROM public.restore_fixture_trace WHERE operation LIKE 'inert-%' ORDER BY event_type"
        );
        $requiredInert = ['currentmission', 'diarylog', 'books', 'responselog', 'rolemaster', 'actions_issued', 'moods_issued', 'rumors', 'named_cell', 'sneq_quests_saved', 'bgl_history', 'bgl_encounters', 'memory_summary', 'memory'];
        $inertTables = array_values(array_map(static fn(array $call): string => (string)$call['table'], $inertCalls));
        foreach ($requiredInert as $table) {
            restoreFixtureAssert(in_array($table, $inertTables, true), 'Expected inert unrelated service call was not recorded: ' . $table);
        }
        $nativeStoreProof = restoreFixtureRunNativeStoreChecks($db, $engine, $latestInitRowId, $seed);

        echo json_encode([
            'result' => 'PASS',
            'proof_scope' => 'exact pinned main.php INIT and processor/comm.php restore path, production store boundaries and PostgreSQL persistence on a synthetic database, with inert unrelated services',
            'database_identity' => $databaseIdentity,
            'main_child_exit' => $childResult['exit_code'],
            'main_child_output' => trim($childResult['stdout']),
            'pinned_main_gate' => $staticGate,
            'semaphore_blocked_before_release' => true,
            'main_prelock_input_rowid' => $preLockRowId,
            'latest_comm_init_marker' => $latestInit,
            'eventlog_sequence_last_value' => $sequence['last_value'] ?? null,
            'source_pruning' => [
                'pre_boundary_event_retained' => true,
                'at_boundary_event_pruned' => true,
                'after_boundary_event_pruned' => true,
                'main_prelock_user_input_pruned' => true,
                'pre_boundary_speech_retained' => true,
                'at_or_after_boundary_speech_pruned' => true,
            ],
            'restore_order' => [
                'prelock_prune_before_init_marker' => true,
                'init_marker_before_npc_restore' => true,
                'final_affinity' => 13,
                'final_npc_gamets' => (float)$afterRestore['gamets_last_updated'],
                'historical_mp_floor' => $afterRestore['plugin_extended_data']['mind_poisoning']['floor_event_id'],
                'historical_mp_events' => $restoredEvents,
                'historical_reflection_basis' => $afterRestore['plugin_extended_data']['mind_poisoning']['reflection_state']['basis'],
            ],
            'restore_policy_store' => $nativeStoreProof,
            'inert_unrelated_services' => $inertTables,
            'model_or_provider_called' => false,
            'limitation' => 'No full ACK runtime request, model/provider, installed CHIM server, or gameplay session was exercised. The ordered method-level store transaction runs under the real MAIN semaphore while exact pinned INIT waits; it does not model the complete concurrent ACK request. Playthrough preflight, Dragon Break, unrelated timeline-table deletes, async queues, narrator welcome, and non-INIT services are explicit inert fixture stubs or synthetic tables.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    } finally {
        if ($lockHeld) {
            SemaphoreManager::release('MAIN');
        }
        if (is_array($child)) {
            restoreFixtureWaitChild($child, 5000);
        }
        $db->close();
    }
}

try {
    if (($argv[1] ?? '') === '--main-child') {
        $engine = restoreFixtureRequireEnv('RESTORE_FIXTURE_ENGINE');
        restoreFixtureRunMainChild($engine);
    }
    restoreFixtureDriver();
} catch (Throwable $error) {
    fwrite(STDERR, 'RESTORE_INIT_FIXTURE_FAIL=' . $error->getMessage() . PHP_EOL);
    exit(1);
}
