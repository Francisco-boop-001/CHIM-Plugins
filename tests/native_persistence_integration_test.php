<?php
declare(strict_types=1);

use ChimMindPoisoning\PostgresStoreDb;
use ChimMindPoisoning\RequestLog;

final class MpNativeFixtureFailure extends RuntimeException
{
    public function __construct(public readonly string $safeCode)
    {
        parent::__construct($safeCode);
    }
}

$mpNativeRequiredScenarios = [
    'combined-affinity-type-and-player-alias',
    'zero-affinity-type-only',
    'snapshot-verification-failure-rolls-back',
    'sequential-and-concurrent-duplicate-delivery',
    'plugin-then-core-fresh-rebase-under-shared-lock',
    'core-then-stale-plugin-whole-judgment-rejected',
    'prewrite-relationship-lock-change-refuses-whole-judgment',
    'prewrite-playthrough-scope-change-refuses-whole-judgment',
    'backend-termination-before-commit-is-atomic',
    'commit-ack-loss-is-unconfirmed-and-replay-is-deduped',
];
$mpNativeScenarios = array_fill_keys($mpNativeRequiredScenarios, ['status' => 'not_run']);
$mpNativeChildren = [];

function mpNativeFail(string $code): never
{
    throw new MpNativeFixtureFailure($code);
}

function mpNativeRequire(bool $condition, string $code): void
{
    if (!$condition) {
        mpNativeFail($code);
    }
}

function mpNativeConfig(): array
{
    mpNativeRequire(PHP_SAPI === 'cli', 'cli-required');
    mpNativeRequire(extension_loaded('pgsql'), 'pgsql-extension-required');
    mpNativeRequire(extension_loaded('mbstring'), 'mbstring-extension-required');
    mpNativeRequire(function_exists('proc_open') && function_exists('proc_get_status'), 'proc-open-required');

    foreach (getenv() as $name => $_value) {
        mpNativeRequire(!str_starts_with(strtoupper((string)$name), 'PG'), 'postgres-environment-forbidden');
    }

    $rootText = getenv('MP_NATIVE_DB_ROOT');
    $socketText = getenv('MP_NATIVE_DB_SOCKET');
    $coreText = getenv('MP_NATIVE_CORE_ROOT');
    mpNativeRequire(is_string($rootText) && preg_match('~\A/tmp/mp-native-db-[A-Za-z0-9_-]{8,32}\z~D', $rootText) === 1, 'scratch-root-shape');
    mpNativeRequire(is_string($socketText) && $socketText === $rootText . '/socket', 'scratch-socket-path');
    mpNativeRequire(is_string($coreText) && $coreText === $rootText . '/core', 'scratch-core-path');
    $root = realpath($rootText);
    $socket = realpath($socketText);
    $core = realpath($coreText);
    mpNativeRequire($root === $rootText && $socket === $rootText . '/socket' && $core === $rootText . '/core', 'scratch-path-canonical');
    mpNativeRequire(!file_exists($root . '/fixture-report.json') && !is_link($root . '/fixture-report.json'), 'fixture-report-path-exists');

    foreach ([$root, $socket, $root . '/data', $core] as $directory) {
        mpNativeRequire(is_dir($directory) && !is_link($directory), 'scratch-directory-required');
        mpNativeRequire(realpath($directory) === $directory, 'scratch-directory-canonical');
        $permissions = fileperms($directory);
        mpNativeRequire(is_int($permissions) && ($permissions & 0077) === 0, 'scratch-directory-permissions');
    }
    $rootOwner = fileowner($root);
    mpNativeRequire(is_int($rootOwner) && fileowner($socket) === $rootOwner && fileowner($root . '/data') === $rootOwner, 'scratch-owner-mismatch');
    $serverSocket = $socket . '/.s.PGSQL.5432';
    mpNativeRequire(!is_link($serverSocket) && filetype($serverSocket) === 'socket', 'scratch-postgres-socket-required');
    mpNativeRequire(fileowner($serverSocket) === $rootOwner, 'scratch-postgres-socket-owner-mismatch');

    $sourceRoot = realpath(dirname(__DIR__));
    mpNativeRequire(is_string($sourceRoot) && is_file($sourceRoot . '/server/store.php'), 'source-snapshot-required');
    $openBasedir = (string)ini_get('open_basedir');
    $allowed = explode(PATH_SEPARATOR, $openBasedir);
    mpNativeRequire(in_array($sourceRoot, $allowed, true) && in_array($root, $allowed, true), 'open-basedir-boundary-required');

    $extensionDir = getenv('MP_NATIVE_PHP_EXTENSION_DIR');
    $pgsqlPath = getenv('MP_NATIVE_PHP_EXTENSION_PATH');
    $mbstringPath = getenv('MP_NATIVE_PHP_MBSTRING_PATH');
    mpNativeRequire(is_string($extensionDir) && $extensionDir !== '', 'php-extension-directory-required');
    mpNativeRequire(is_string($pgsqlPath) && $pgsqlPath !== '', 'pgsql-extension-path-required');
    mpNativeRequire(is_string($mbstringPath) && $mbstringPath !== '', 'mbstring-extension-path-required');

    $coreHashes = [
        'lib/postgresql.class.php' => '0ceb8187394861aca0cda6d90903ff943525948033bc2d309382569a768f076b',
        'lib/relationship_manager.php' => '4497443c9480c7d7ff5d5deabcec9ad5ab4cdec6f28f1fd044f89594d71e06ab',
        'lib/core/npc_master.class.php' => '3a874f93c5dd854981f5bc597c75d3049a02cf0492582efba2256bcdd17b233e',
        'ext/relationship_system/relationship_llm.php' => 'f9da8517a464cd6256f77d0c479cae34076f488b57d5877594efe2f9ad77d6d4',
        'lib/chim_interaction.php' => '3e7dce8a08b46870d21abb729a743c695ac2bb399ec42c13417958132f106de0',
        'lib/playthrough_runtime.php' => 'd480af9d658c8e754b78d1e4ca6056544d21942facbafd94b23ab1da4cb1cd9c',
        'lib/logger.php' => '63b9f6ff8d99335f6c92991120030f89038641435d5f39408721fde1fa988f54',
        'lib/core/game_plugins.php' => '361b021322134bedd796abc73e1394357ee97988735e611e56416fe729549f80',
        'lib/core/tts_filter_presets.php' => '5050e6b340f95fef3d420f8605775705d1ca5242d35b284bf840c4d4bd33e0d8',
        'lib/core/database_schema/core_npc_master.sql' => '9eae322a0183522da4ed9714eb1d538736b566beb04c264c82affdb71bc07fc6',
        'lib/core/database_schema/core_npc_master_history.sql' => 'f0c0c32d106cdc4c6ffb11d4b5490644d4f5fd2dec61822444aad829db225e1e',
        'lib/core/database_schema/core_player.sql' => 'e0dd4157865b4221e19c501062c6eb951501f60a1dec0b762b808b2b0f18455f',
    ];
    foreach ($coreHashes as $relative => $expectedHash) {
        $path = $core . '/' . $relative;
        mpNativeRequire(is_file($path) && hash_file('sha256', $path) === $expectedHash, 'pinned-core-file-mismatch');
    }

    $eventDdl = __DIR__ . '/fixtures/mp-native-db/eventlog-base-ddl.sql';
    $proxyScript = __DIR__ . '/fixtures/mp-native-db/commit_ack_proxy.py';
    mpNativeRequire(is_file($eventDdl) && hash_file('sha256', $eventDdl) === '9d69b26007c1de407b794645ef6318a4b152cf5525a7f731d5e7199a01c35f97', 'eventlog-ddl-mismatch');
    mpNativeRequire(is_file($proxyScript), 'commit-proxy-missing');

    $python = getenv('MP_NATIVE_PYTHON');
    mpNativeRequire(is_string($python) && $python !== '', 'python-runtime-required');
    $configuredErrorLog = $root . '/php-error.log';
    mpNativeRequire(ini_set('error_log', $configuredErrorLog) !== false, 'scratch-error-log-required');
    mpNativeRequire(realpath(dirname($configuredErrorLog)) === $root, 'scratch-error-log-boundary');

    return [
        'root' => $root,
        'socket' => $socket,
        'data' => $root . '/data',
        'core' => $core,
        'source' => $sourceRoot,
        'extension_dir' => $extensionDir,
        'pgsql_extension' => $pgsqlPath,
        'mbstring_extension' => $mbstringPath,
        'python' => $python,
        'proxy_script' => $proxyScript,
        'eventlog_ddl' => $eventDdl,
    ];
}

function mpNativeBootstrapCore(array $config): void
{
    $GLOBALS['ENGINE_PATH'] = rtrim($config['core'], '/\\') . DIRECTORY_SEPARATOR;
    $GLOBALS['PLAYER_NAME'] = 'Dragonborn';
    $GLOBALS['gameRequest'] = [null, null, 0];
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
    set_include_path($config['core'] . '/lib' . PATH_SEPARATOR . get_include_path());

    require_once $config['core'] . '/lib/logger.php';
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (error_reporting() === 0) {
            return true;
        }
        if (!empty($GLOBALS['pgr_operation']) && str_contains($message, 'pg_')) {
            $GLOBALS['pgr_sql_failed'] = true;
        }
        $fingerprint = substr(hash('sha256', $message), 0, 12);
        @error_log('fixture-php-error severity=' . $severity . ' line=' . $line . ' fingerprint=' . $fingerprint);
        return true;
    });
    mpNativeRequire(method_exists('Logger', 'setCustomLog'), 'pinned-logger-api-mismatch');
    \Logger::setCustomLog($config['root'] . '/core.log');

    // This real helper reads or creates only the pinned scratch copy's interaction state.
    require_once $config['core'] . '/lib/chim_interaction.php';
    require_once $config['core'] . '/lib/playthrough_runtime.php';
    require_once $config['core'] . '/lib/core/game_plugins.php';
    require_once $config['core'] . '/lib/core/tts_filter_presets.php';
    require_once $config['core'] . '/lib/relationship_manager.php';
    require_once $config['core'] . '/lib/postgresql.class.php';
    require_once $config['core'] . '/lib/core/npc_master.class.php';
    require_once $config['core'] . '/ext/relationship_system/relationship_llm.php';
    require_once $config['source'] . '/server/logging.php';
    require_once $config['source'] . '/server/influence.php';
    require_once $config['source'] . '/server/store.php';
}

function mpNativeDsn(array $config, string $socketDirectory, string $applicationName): string
{
    mpNativeRequire(preg_match('/\Amp-native-b1-[a-z0-9-]{1,32}\z/D', $applicationName) === 1, 'application-name-invalid');
    mpNativeRequire($socketDirectory === $config['socket'] || $socketDirectory === $config['root'] . '/proxy-after', 'connection-socket-not-private');
    return 'host=' . $socketDirectory
        . ' dbname=mp_native_b1 user=postgres port=5432 sslmode=disable connect_timeout=3 application_name=' . $applicationName;
}

function mpNativeConnect(array $config, string $applicationName, ?string $socketDirectory = null): PgSql\Connection
{
    $socketDirectory ??= $config['socket'];
    $connection = @pg_connect(mpNativeDsn($config, $socketDirectory, $applicationName), PGSQL_CONNECT_FORCE_NEW);
    mpNativeRequire($connection instanceof PgSql\Connection && pg_connection_status($connection) === PGSQL_CONNECTION_OK, 'postgres-connect-failed');
    mpNativeVerifyConnection($connection, $config);
    return $connection;
}

function mpNativeQuery(PgSql\Connection $connection, string $sql, array $parameters = []): PgSql\Result
{
    $result = $parameters === []
        ? @pg_query($connection, $sql)
        : @pg_query_params($connection, $sql, $parameters);
    mpNativeRequire($result instanceof PgSql\Result, 'postgres-query-failed');
    return $result;
}

function mpNativeExec(PgSql\Connection $connection, string $sql, array $parameters = []): void
{
    $result = mpNativeQuery($connection, $sql, $parameters);
    pg_free_result($result);
}

function mpNativeOne(PgSql\Connection $connection, string $sql, array $parameters = []): array
{
    $result = mpNativeQuery($connection, $sql, $parameters);
    try {
        $row = pg_fetch_assoc($result);
        mpNativeRequire(is_array($row), 'postgres-row-missing');
        return $row;
    } finally {
        pg_free_result($result);
    }
}

function mpNativeAll(PgSql\Connection $connection, string $sql, array $parameters = []): array
{
    $result = mpNativeQuery($connection, $sql, $parameters);
    try {
        $rows = [];
        while ($row = pg_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    } finally {
        pg_free_result($result);
    }
}

function mpNativeVerifyConnection(PgSql\Connection $connection, array $config): void
{
    $identity = mpNativeOne($connection, "SELECT current_database() AS database_name,
        current_user AS user_name,
        current_setting('data_directory') AS data_directory,
        current_setting('unix_socket_directories') AS socket_directories,
        current_setting('port') AS port,
        inet_server_addr() IS NULL AS unix_only,
        (SELECT rolsuper FROM pg_roles WHERE rolname = current_user) AS is_superuser");
    mpNativeRequire(($identity['database_name'] ?? null) === 'mp_native_b1', 'database-identity-mismatch');
    mpNativeRequire(($identity['user_name'] ?? null) === 'postgres' && ($identity['is_superuser'] ?? null) === 't', 'database-user-mismatch');
    mpNativeRequire(($identity['data_directory'] ?? null) === $config['data'], 'database-data-directory-mismatch');
    mpNativeRequire(($identity['socket_directories'] ?? null) === $config['socket'], 'database-socket-directory-mismatch');
    mpNativeRequire(($identity['port'] ?? null) === '5432' && ($identity['unix_only'] ?? null) === 't', 'database-listener-mismatch');
}

function mpNativeSqlWrapper(PgSql\Connection $connection, string $socketDirectory, array $config): object
{
    $reflection = new ReflectionClass(\sql::class);
    $wrapper = $reflection->newInstanceWithoutConstructor();
    $link = new ReflectionProperty(\sql::class, 'link');
    $link->setAccessible(true);
    $link->setValue(null, $connection);
    $reconnect = new ReflectionProperty(\sql::class, 'connString');
    $reconnect->setAccessible(true);
    $reconnect->setValue($wrapper, mpNativeDsn($config, $socketDirectory, 'mp-native-b1-reconnect'));
    $debug = new ReflectionProperty(\sql::class, 'debug_level');
    $debug->setAccessible(true);
    $debug->setValue($wrapper, 0);
    mpNativeRequire(pg_connection_status($link->getValue()) === PGSQL_CONNECTION_OK, 'injected-connection-unhealthy');
    return $wrapper;
}

function mpNativeAssertFreshDatabase(PgSql\Connection $connection): void
{
    $row = mpNativeOne($connection, "SELECT
        EXISTS (SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
                WHERE c.relkind IN ('r','p','v','m','S') AND n.nspname NOT IN ('pg_catalog','information_schema')) AS has_user_relations,
        EXISTS (SELECT 1 FROM pg_namespace WHERE nspname='chim_meta') AS has_chim_meta,
        EXISTS (SELECT 1 FROM pg_roles WHERE rolname='dwemer') AS has_dwemer");
    mpNativeRequire(($row['has_user_relations'] ?? null) === 'f', 'scratch-database-not-empty');
    mpNativeRequire(($row['has_chim_meta'] ?? null) === 'f' && ($row['has_dwemer'] ?? null) === 'f', 'scratch-database-fixture-present');
}

function mpNativeReadDdl(string $path): string
{
    $sql = @file_get_contents($path);
    mpNativeRequire(is_string($sql) && $sql !== '', 'pinned-ddl-unreadable');
    return $sql;
}

function mpNativeSetupDatabase(PgSql\Connection $connection, array $config): void
{
    mpNativeAssertFreshDatabase($connection);
    mpNativeExec($connection, 'CREATE ROLE dwemer NOLOGIN');
    mpNativeExec($connection, 'CREATE TABLE public.core_profiles (id integer PRIMARY KEY)');
    foreach ([
        'lib/core/database_schema/core_player.sql',
        'lib/core/database_schema/core_npc_master_history.sql',
        'lib/core/database_schema/core_npc_master.sql',
    ] as $relative) {
        mpNativeExec($connection, mpNativeReadDdl($config['core'] . '/' . $relative));
        mpNativeExec($connection, 'SET search_path TO public');
    }
    mpNativeExec($connection, mpNativeReadDdl($config['eventlog_ddl']));
    mpNativeExec($connection, 'CREATE SCHEMA chim_meta');
    mpNativeExec($connection, 'CREATE TABLE chim_meta.playthrough_profiles (id integer PRIMARY KEY, is_active boolean NOT NULL)');
    mpNativeExec($connection, 'CREATE SCHEMA mp_native_b1');
    mpNativeExec($connection, 'CREATE TABLE mp_native_b1.barriers (owner_id integer NOT NULL, app_name text NOT NULL UNIQUE, reached boolean NOT NULL DEFAULT false)');
    mpNativeExec($connection, 'CREATE TABLE mp_native_b1.faults (owner_id integer NOT NULL, app_name text NOT NULL UNIQUE, mode text NOT NULL CHECK (mode IN (\'corrupt_history\', \'terminate_backend\')))');
    mpNativeExec($connection, "CREATE FUNCTION mp_native_b1.barrier_before_master_update() RETURNS trigger LANGUAGE plpgsql AS \$\$
        BEGIN
            UPDATE mp_native_b1.barriers SET reached = true
            WHERE owner_id = NEW.id AND app_name = current_setting('application_name', true);
            RETURN NEW;
        END
        \$\$");
    mpNativeExec($connection, 'CREATE TRIGGER mp_native_b1_barrier BEFORE UPDATE ON public.core_npc_master FOR EACH ROW EXECUTE FUNCTION mp_native_b1.barrier_before_master_update()');
    mpNativeExec($connection, "CREATE FUNCTION mp_native_b1.history_fault_before_insert() RETURNS trigger LANGUAGE plpgsql AS \$\$
        DECLARE selected_mode text;
        BEGIN
            SELECT mode INTO selected_mode FROM mp_native_b1.faults
            WHERE owner_id = NEW.npc_id AND app_name = current_setting('application_name', true);
            IF selected_mode = 'corrupt_history' THEN
                NEW.extended_data := jsonb_set(COALESCE(NEW.extended_data, '{}'::jsonb), '{fixture_corruption}', '\"snapshot\"'::jsonb, true);
            ELSIF selected_mode = 'terminate_backend' THEN
                PERFORM pg_terminate_backend(pg_backend_pid());
            END IF;
            RETURN NEW;
        END
        \$\$");
    mpNativeExec($connection, 'CREATE TRIGGER mp_native_b1_history_fault BEFORE INSERT ON public.core_npc_master_history FOR EACH ROW EXECUTE FUNCTION mp_native_b1.history_fault_before_insert()');
    mpNativeExec($connection, "INSERT INTO chim_meta.playthrough_profiles (id, is_active) VALUES (1, true), (2, false)");
    mpNativeExec($connection, "INSERT INTO public.core_player (id, value) VALUES ('player_name', 'Dragonborn')");
    mpNativeSeedNpcs($connection);
    mpNativeSeedEvents($connection);
}

function mpNativeEdgeSeed(string $target, int $affinity, string $type, string $note): array
{
    return [
        $target => ['aff' => $affinity, 'type' => $type, 'note' => $note, 'custom_marker' => 'preserve-' . $target],
    ];
}

function mpNativeSeedNpcs(PgSql\Connection $connection): void
{
    $rows = [
        [101, 'Aela', []],
        [103, 'Balgruuf', []],
        [104, 'Inigo', []],
        [2001, 'Lydia', ['Dragonborn' => ['aff' => 20, 'type' => 'neutral', 'note' => 'legacy player edge', 'custom_marker' => 'preserve-Dragonborn'], 'Balgruuf' => ['aff' => 12, 'type' => 'neutral', 'note' => 'unrelated edge', 'custom_marker' => 'preserve-Balgruuf'], 'Inigo' => ['aff' => 7, 'type' => 'neutral', 'note' => 'untouched', 'custom_marker' => 'preserve-Inigo']]],
        [2002, 'Mara', array_merge(mpNativeEdgeSeed('Balgruuf', 12, 'neutral', 'baseline'), mpNativeEdgeSeed('Inigo', 7, 'neutral', 'untouched'))],
        [2003, 'Runa', array_merge(mpNativeEdgeSeed('Balgruuf', 12, 'neutral', 'baseline'), mpNativeEdgeSeed('Inigo', 7, 'neutral', 'untouched'))],
        [2004, 'Sigrid', array_merge(mpNativeEdgeSeed('Balgruuf', 12, 'neutral', 'baseline'), mpNativeEdgeSeed('Inigo', 7, 'neutral', 'untouched'))],
        [2005, 'Tova', array_merge(mpNativeEdgeSeed('Balgruuf', 12, 'neutral', 'baseline'), mpNativeEdgeSeed('Inigo', 7, 'neutral', 'untouched'))],
        [2006, 'Ysolda', array_merge(mpNativeEdgeSeed('Balgruuf', 12, 'neutral', 'baseline'), mpNativeEdgeSeed('Inigo', 7, 'neutral', 'untouched'))],
        [2007, 'Gerdur', array_merge(mpNativeEdgeSeed('Balgruuf', 12, 'neutral', 'baseline'), mpNativeEdgeSeed('Inigo', 7, 'neutral', 'untouched'))],
        [2008, 'Idgrod', array_merge(mpNativeEdgeSeed('Balgruuf', 12, 'neutral', 'baseline'), mpNativeEdgeSeed('Inigo', 7, 'neutral', 'untouched'))],
        [2009, 'Nenya', array_merge(mpNativeEdgeSeed('Balgruuf', 12, 'neutral', 'baseline'), mpNativeEdgeSeed('Inigo', 7, 'neutral', 'untouched'))],
        [2010, 'Sena', array_merge(mpNativeEdgeSeed('Balgruuf', 12, 'neutral', 'baseline'), mpNativeEdgeSeed('Inigo', 7, 'neutral', 'untouched'))],
    ];
    foreach ($rows as [$id, $name, $relationships]) {
        $extended = ['fixture_field' => 'preserve-' . $id];
        if ($relationships !== []) {
            $extended['relationships'] = (object)$relationships;
        }
        $plugin = [
            'other_plugin' => ['keep' => 'plugin-' . $id],
            'mind_poisoning' => ['playthrough_id' => '1', 'floor_event_id' => 0, 'events' => []],
        ];
        $extendedJson = json_encode((object)$extended, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $pluginJson = json_encode((object)$plugin, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        mpNativeExec($connection, "INSERT INTO public.core_npc_master
            (id, npc_name, lock_profile, extended_data, plugin_extended_data, gamets_last_updated, md5)
            VALUES ($1, $2, 0, $3::jsonb, $4::jsonb, 10, $5)", [$id, $name, $extendedJson, $pluginJson, 'fixture-' . $id]);
    }
}

function mpNativeCases(): array
{
    $rows = [
        'combined' => [2001, 3001, 'utt_native_b1_combined', 'Dragonborn signals the guard.', 1010],
        'zero_type' => [2002, 3002, 'utt_native_b1_zero_type', 'Balgruuf raises the gate.', 1020],
        'snapshot_fault' => [2003, 3003, 'utt_native_b1_snapshot', 'Balgruuf changes the plan.', 1030],
        'duplicate' => [2004, 3004, 'utt_native_b1_duplicate', 'Balgruuf closes the gate.', 1040],
        'duplicate_concurrent' => [2004, 3014, 'utt_native_b1_dupconcurrent', 'Balgruuf closes the gate.', 1041],
        'core_rebase' => [2005, 3005, 'utt_native_b1_core_rebase', 'Balgruuf changes the plan.', 1050],
        'core_stale' => [2006, 3006, 'utt_native_b1_core_stale', 'Balgruuf changes the plan.', 1060],
        'backend_loss' => [2007, 3007, 'utt_native_b1_backend_loss', 'Balgruuf changes the plan.', 1070],
        'ack_loss' => [2008, 3008, 'utt_native_b1_ack_loss', 'Balgruuf changes the plan.', 1080],
        'lock_stale' => [2009, 3009, 'utt_native_b1_lock_stale', 'Balgruuf changes the plan.', 1090],
        'scope_stale' => [2010, 3010, 'utt_native_b1_scope_stale', 'Balgruuf changes the plan.', 1100],
    ];
    $names = [2001 => 'Lydia', 2002 => 'Mara', 2003 => 'Runa', 2004 => 'Sigrid', 2005 => 'Tova', 2006 => 'Ysolda', 2007 => 'Gerdur', 2008 => 'Idgrod', 2009 => 'Nenya', 2010 => 'Sena'];
    $cases = [];
    foreach ($rows as $key => [$ownerId, $eventId, $utteranceId, $text, $gamets]) {
        $payload = [
            'speaker_id' => 101,
            'listener_id' => $ownerId,
            'speaker_name' => 'Aela',
            'listener_name' => $names[$ownerId],
            'text' => $text,
            'gamets' => $gamets,
            'playthrough_id' => '1',
        ];
        if ($key === 'combined') {
            $payload['player_name'] = 'Dragonborn';
        }
        $sourceData = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $event = [
            'event_id' => $eventId,
            'utterance_id' => $utteranceId,
            'speaker_id' => 101,
            'speaker_kind' => 'npc',
            'listener_id' => $ownerId,
            'speaker_name' => 'Aela',
            'listener_name' => $names[$ownerId],
            'text' => $text,
            'gamets' => (float)$gamets,
            'playthrough_id' => '1',
            'source_data' => $sourceData,
        ];
        if ($key === 'combined') {
            $event['player_name'] = 'Dragonborn';
        }
        $cases[$key] = ['owner_id' => $ownerId, 'event' => $event, 'source_data' => $sourceData];
    }
    return $cases;
}

function mpNativeSeedEvents(PgSql\Connection $connection): void
{
    foreach (mpNativeCases() as $case) {
        $event = $case['event'];
        mpNativeExec($connection, "INSERT INTO public.eventlog
            (type, data, sess, gamets, localts, ts, rowid, people, location, party, utterance_id, delivery_state)
            VALUES ('chat', $1, 'mp-native-fixture', $2, $3, $4, $5, 'Aela', 'Whiterun', '', $6, 'emitted')",
            [$case['source_data'], (int)$event['gamets'], 100000 + $event['event_id'], 200000 + $event['event_id'], $event['event_id'], $event['utterance_id']]);
    }
}

function mpNativeJudgments(string $caseName): array
{
    $evidence = match ($caseName) {
        'combined' => 'Dragonborn signals',
        'zero_type' => 'Balgruuf raises',
        'duplicate', 'duplicate_concurrent' => 'Balgruuf closes',
        default => 'Balgruuf changes',
    };
    return match ($caseName) {
        'combined' => ['player' => ['delta' => 2, 'reason' => 'Aela trusts the Dragonborn.', 'evidence' => $evidence, 'type_change' => ['from' => 'neutral', 'to' => 'suspicious']]],
        'zero_type' => ['npc:103' => ['delta' => 0, 'reason' => 'Aela distrusts the plan.', 'evidence' => 'Balgruuf raises', 'type_change' => ['from' => 'neutral', 'to' => 'suspicious']]],
        'core_rebase' => ['npc:103' => ['delta' => 2, 'reason' => 'Aela distrusts the plan.', 'evidence' => $evidence, 'type_change' => ['from' => 'neutral', 'to' => 'suspicious']]],
        'core_stale' => ['npc:103' => ['delta' => 2, 'reason' => 'Aela mistrusts the plan.', 'evidence' => $evidence, 'type_change' => ['from' => 'neutral', 'to' => 'suspicious']]],
        default => ['npc:103' => ['delta' => 1, 'reason' => 'Aela notes the change.', 'evidence' => $evidence]],
    };
}

function mpNativeCase(string $name): array
{
    $cases = mpNativeCases();
    mpNativeRequire(isset($cases[$name]), 'case-unknown');
    return $cases[$name];
}

function mpNativeSafeRootFile(array $config, string $name): string
{
    mpNativeRequire(preg_match('/\A[a-z0-9-]{1,64}\.(?:json|ready|out|err)\z/D', $name) === 1, 'fixture-filename-invalid');
    return $config['root'] . '/' . $name;
}

function mpNativeWriteJson(string $path, array $payload): void
{
    $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $handle = @fopen($path, 'xb');
    mpNativeRequire(is_resource($handle), 'fixture-output-create-failed');
    try {
        mpNativeRequire(fwrite($handle, $json . "\n") !== false, 'fixture-output-write-failed');
        fflush($handle);
    } finally {
        fclose($handle);
    }
    @chmod($path, 0600);
}

function mpNativeWriteChildStatus(array $config, string $task, array $status): void
{
    mpNativeWriteJson(mpNativeSafeRootFile($config, 'child-' . $task . '.json'), $status);
}

function mpNativeChildMain(array $config, string $task): never
{
    mpNativeRequire(preg_match('/\A[a-z0-9-]{1,48}\z/D', $task) === 1, 'child-task-invalid');
    $tasks = [
        'persist-combined' => ['kind' => 'persist', 'case' => 'combined', 'app' => 'mp-native-b1-combined'],
        'persist-zero-type' => ['kind' => 'persist', 'case' => 'zero_type', 'app' => 'mp-native-b1-zero-type'],
        'persist-snapshot-fault' => ['kind' => 'persist', 'case' => 'snapshot_fault', 'app' => 'mp-native-b1-snapshot-fault'],
        'persist-duplicate-first' => ['kind' => 'persist', 'case' => 'duplicate', 'app' => 'mp-native-b1-duplicate-first'],
        'persist-duplicate-second' => ['kind' => 'persist', 'case' => 'duplicate', 'app' => 'mp-native-b1-duplicate-second'],
        'persist-concurrent-first' => ['kind' => 'persist', 'case' => 'duplicate_concurrent', 'app' => 'mp-native-b1-dup-concurrent-first'],
        'persist-concurrent-second' => ['kind' => 'persist', 'case' => 'duplicate_concurrent', 'app' => 'mp-native-b1-dup-concurrent-second'],
        'persist-concurrent-replay' => ['kind' => 'persist', 'case' => 'duplicate_concurrent', 'app' => 'mp-native-b1-dup-concurrent-replay'],
        'persist-mp-core-first' => ['kind' => 'persist', 'case' => 'core_rebase', 'app' => 'mp-native-b1-mp-first'],
        'core-after-mp' => ['kind' => 'core', 'case' => 'core_rebase', 'app' => 'mp-native-b1-core-after'],
        'core-first' => ['kind' => 'core', 'case' => 'core_stale', 'app' => 'mp-native-b1-core-first'],
        'persist-after-core' => ['kind' => 'persist', 'case' => 'core_stale', 'app' => 'mp-native-b1-mp-after'],
        'persist-lock-stale' => ['kind' => 'persist', 'case' => 'lock_stale', 'app' => 'mp-native-b1-lock-stale', 'wait_for_parent' => true],
        'persist-scope-stale' => ['kind' => 'persist', 'case' => 'scope_stale', 'app' => 'mp-native-b1-scope-stale', 'wait_for_parent' => true],
        'persist-backend-loss' => ['kind' => 'persist', 'case' => 'backend_loss', 'app' => 'mp-native-b1-backend-loss'],
        'persist-ack-loss' => ['kind' => 'persist', 'case' => 'ack_loss', 'app' => 'mp-native-b1-ack-loss', 'proxy' => true],
        'persist-duplicate-replay' => ['kind' => 'persist', 'case' => 'ack_loss', 'app' => 'mp-native-b1-ack-replay'],
    ];
    mpNativeRequire(isset($tasks[$task]), 'child-task-unknown');
    $spec = $tasks[$task];
    $socketDirectory = !empty($spec['proxy']) ? $config['root'] . '/proxy-after' : $config['socket'];
    $connection = mpNativeConnect($config, $spec['app'], $socketDirectory);
    $wrapper = mpNativeSqlWrapper($connection, $socketDirectory, $config);
    $GLOBALS['db'] = $wrapper;

    try {
        if ($spec['kind'] === 'persist') {
            $case = mpNativeCase($spec['case']);
            $store = new PostgresStoreDb();
            $subjects = \ChimMindPoisoning\findSubjects($case['event'], $store->npcIdentities(), 'Dragonborn');
            $judgments = mpNativeJudgments($spec['case']);
            $observed = [];
            $log = new RequestLog(static function (string $_record, string $_level): void {}, false);
            $log->observe(static function (array $record) use (&$observed): void {
                if (($record['event'] ?? null) === 'persistence_finished') {
                    $observed = $record;
                }
            });
            // Rebind the store logger so the same request log captures the native persistence result.
            $store->setRequestLog($log);
            $ready = mpNativeSafeRootFile($config, 'child-' . $task . '.ready');
            mpNativeWriteJson($ready, ['ready' => true]);
            if (!empty($spec['wait_for_parent'])) {
                mpNativeWaitFile(mpNativeSafeRootFile($config, 'child-' . $task . '-go.json'), 5000, 'parent-release-timeout');
            }
            $outcome = \ChimMindPoisoning\persistJudgments($case['event'], $subjects, $judgments, $store, $log);
            mpNativeWriteChildStatus($config, $task, [
                'status' => 'ok',
                'outcome' => $outcome,
                'persistence' => [
                    'persistence_outcome' => $observed['persistence_outcome'] ?? null,
                    'persistence_reason' => $observed['persistence_reason'] ?? null,
                    'commit_state' => $observed['commit_state'] ?? null,
                    'committed' => $observed['committed'] ?? null,
                    'cleanup_failed' => $observed['cleanup_failed'] ?? false,
                    'affinity_changed_count' => $observed['affinity_changed_count'] ?? null,
                    'type_changed_count' => $observed['type_changed_count'] ?? null,
                ],
            ]);
        } else {
            $ownerId = $spec['case'] === 'core_rebase' ? 2005 : 2006;
            $currentRels = [
                'Balgruuf' => ['aff' => 12, 'type' => 'neutral', 'note' => 'baseline', 'custom_marker' => 'preserve-Balgruuf'],
                'Inigo' => ['aff' => 7, 'type' => 'neutral', 'note' => 'untouched', 'custom_marker' => 'preserve-Inigo'],
            ];
            $changeType = $spec['case'] === 'core_rebase' ? 'neutral' : 'suspicious';
            $changes = ['Balgruuf' => ['delta' => 1, 'type' => $changeType, 'reason' => 'Aela observes a plan change.', 'relation' => null]];
            $ready = mpNativeSafeRootFile($config, 'child-' . $task . '.ready');
            mpNativeWriteJson($ready, ['ready' => true]);
            $instance = (new ReflectionClass(RelationshipLLM::class))->newInstanceWithoutConstructor();
            $dbProperty = new ReflectionProperty(RelationshipLLM::class, 'db');
            $dbProperty->setAccessible(true);
            $dbProperty->setValue($instance, $wrapper);
            $method = new ReflectionMethod(RelationshipLLM::class, 'applyChanges');
            $method->setAccessible(true);
            $applied = $method->invoke($instance, $ownerId, $changes, $currentRels);
            mpNativeWriteChildStatus($config, $task, ['status' => 'ok', 'applied_targets' => is_array($applied) ? array_keys($applied) : []]);
        }
    } finally {
        // Close through the actual wrapper so its static link is cleared before PHP teardown.
        try {
            if (method_exists($wrapper, 'close')) {
                $wrapper->close();
            }
        } catch (Throwable) {
        }
    }
    exit(0);
}

function mpNativeChildEnvironment(array $config): array
{
    $environment = [];
    foreach ([
        'MP_NATIVE_DB_ROOT' => $config['root'],
        'MP_NATIVE_DB_SOCKET' => $config['socket'],
        'MP_NATIVE_CORE_ROOT' => $config['core'],
        'MP_NATIVE_PHP_EXTENSION_DIR' => $config['extension_dir'],
        'MP_NATIVE_PHP_EXTENSION_PATH' => $config['pgsql_extension'],
        'MP_NATIVE_PHP_MBSTRING_PATH' => $config['mbstring_extension'],
        'MP_NATIVE_PYTHON' => $config['python'],
    ] as $name => $value) {
        $environment[$name] = $value;
    }
    return $environment;
}

function mpNativePhpChildCommand(array $config, string $task): array
{
    $openBasedir = $config['source'] . PATH_SEPARATOR . $config['root'];
    return [
        PHP_BINARY,
        '-n',
        '-d', 'extension_dir=' . $config['extension_dir'],
        '-d', 'extension=' . $config['pgsql_extension'],
        '-d', 'extension=' . $config['mbstring_extension'],
        '-d', 'error_log=' . $config['root'] . '/php-error.log',
        '-d', 'open_basedir=' . $openBasedir,
        '-d', 'display_errors=0',
        '-d', 'log_errors=1',
        __FILE__,
        '--child',
        $task,
    ];
}

function mpNativeStartProcess(array $config, array $command, string $name, bool $python = false): mixed
{
    global $mpNativeChildren;
    $base = 'child-' . $name;
    if ($python) {
        $base = 'proxy-' . $name;
    }
    $stdout = mpNativeSafeRootFile($config, $base . '.out');
    $stderr = mpNativeSafeRootFile($config, $base . '.err');
    mpNativeRequire(!file_exists($stdout) && !file_exists($stderr), 'fixture-output-exists');
    $descriptors = [0 => ['pipe', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']];
    $process = @proc_open($command, $descriptors, $pipes, $config['source'], mpNativeChildEnvironment($config), ['bypass_shell' => true]);
    mpNativeRequire(is_resource($process), 'fixture-process-start-failed');
    fclose($pipes[0]);
    @chmod($stdout, 0600);
    @chmod($stderr, 0600);
    $mpNativeChildren[$name] = $process;
    return $process;
}

function mpNativeStartPhpChild(array $config, string $task): mixed
{
    $ready = mpNativeSafeRootFile($config, 'child-' . $task . '.ready');
    $result = mpNativeSafeRootFile($config, 'child-' . $task . '.json');
    mpNativeRequire(!file_exists($ready) && !file_exists($result), 'child-output-exists');
    return mpNativeStartProcess($config, mpNativePhpChildCommand($config, $task), $task);
}

function mpNativeWaitProcess(string $name, int $timeoutMs): int
{
    global $mpNativeChildren;
    mpNativeRequire(isset($mpNativeChildren[$name]) && is_resource($mpNativeChildren[$name]), 'child-process-missing');
    $process = $mpNativeChildren[$name];
    $deadline = microtime(true) + ($timeoutMs / 1000);
    $exitCode = null;
    do {
        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = is_int($status['exitcode']) ? $status['exitcode'] : null;
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    if ($exitCode === null) {
        @proc_terminate($process, 9);
        $stopDeadline = microtime(true) + 1.0;
        do {
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $stopDeadline);
        proc_close($process);
        unset($mpNativeChildren[$name]);
        mpNativeFail('child-timeout');
    }
    $closeCode = proc_close($process);
    unset($mpNativeChildren[$name]);
    return $exitCode >= 0 ? $exitCode : $closeCode;
}

function mpNativeWaitFile(string $path, int $timeoutMs, string $failureCode): void
{
    $deadline = microtime(true) + ($timeoutMs / 1000);
    while (microtime(true) < $deadline) {
        if (!is_link($path) && (is_file($path) || @filetype($path) === 'socket')) {
            return;
        }
        usleep(5000);
    }
    mpNativeFail($failureCode);
}

function mpNativeReadChildStatus(array $config, string $task): array
{
    $path = mpNativeSafeRootFile($config, 'child-' . $task . '.json');
    $contents = @file_get_contents($path);
    $status = is_string($contents) ? json_decode($contents, true) : null;
    mpNativeRequire(is_array($status), 'child-result-invalid');
    if (($status['status'] ?? null) === 'failed') {
        $code = $status['code'] ?? null;
        $safeCode = is_string($code) && preg_match('/\A[a-z0-9-]{1,96}\z/D', $code) === 1 ? $code : 'child-failure';
        mpNativeFail('child-' . $task . '-' . $safeCode);
    }
    mpNativeRequire(($status['status'] ?? null) === 'ok', 'child-result-invalid');
    return $status;
}

function mpNativeRunChildSync(array $config, string $task, int $timeoutMs = 10000): array
{
    mpNativeStartPhpChild($config, $task);
    $processName = $task;
    $exitCode = mpNativeWaitProcess($processName, $timeoutMs);
    $status = mpNativeReadChildStatus($config, $task);
    mpNativeRequire($exitCode === 0, 'child-exit-nonzero');
    return $status;
}

function mpNativeWaitForLockWait(PgSql\Connection $controller, string $applicationName, string $queryNeedle, int $timeoutMs = 700): void
{
    $deadline = microtime(true) + ($timeoutMs / 1000);
    while (microtime(true) < $deadline) {
        $rows = mpNativeAll($controller, "SELECT wait_event_type, query FROM pg_stat_activity
            WHERE application_name = $1 AND pid <> pg_backend_pid() ORDER BY backend_start DESC LIMIT 1", [$applicationName]);
        if (isset($rows[0])) {
            $row = $rows[0];
            if (($row['wait_event_type'] ?? null) === 'Lock' && str_contains(strtolower((string)($row['query'] ?? '')), strtolower($queryNeedle))) {
                return;
            }
        }
        usleep(5000);
    }
    mpNativeFail('database-barrier-not-reached');
}

function mpNativeAssertAdvisoryPair(PgSql\Connection $controller, string $firstApp, string $secondApp, int $ownerId): void
{
    $rows = mpNativeAll($controller, "SELECT a.application_name,
        l.granted,
        ((l.classid::bigint << 32) | l.objid::bigint) AS lock_key
        FROM pg_locks l JOIN pg_stat_activity a USING (pid)
        WHERE l.locktype = 'advisory' AND l.objsubid = 1
          AND a.application_name IN ($1, $2)", [$firstApp, $secondApp]);
    $key = 1001000000 + $ownerId;
    $granted = false;
    $waiting = false;
    foreach ($rows as $row) {
        if ((int)($row['lock_key'] ?? -1) !== $key) {
            continue;
        }
        if (($row['application_name'] ?? null) === $firstApp && ($row['granted'] ?? null) === 't') {
            $granted = true;
        }
        if (($row['application_name'] ?? null) === $secondApp && ($row['granted'] ?? null) === 'f') {
            $waiting = true;
        }
    }
    mpNativeRequire($granted && $waiting, 'shared-advisory-lock-not-proven');
}

function mpNativeBeginBarrier(PgSql\Connection $controller, PgSql\Connection $lockConnection, int $ownerId, string $appName): void
{
    mpNativeExec($controller, 'INSERT INTO mp_native_b1.barriers (owner_id, app_name, reached) VALUES ($1, $2, false)', [$ownerId, $appName]);
    mpNativeExec($lockConnection, 'BEGIN');
    mpNativeExec($lockConnection, 'SELECT app_name FROM mp_native_b1.barriers WHERE app_name = $1 FOR UPDATE', [$appName]);
}

function mpNativeReleaseBarrier(PgSql\Connection $lockConnection): void
{
    if (pg_transaction_status($lockConnection) !== PGSQL_TRANSACTION_IDLE) {
        mpNativeExec($lockConnection, 'ROLLBACK');
    }
}

function mpNativeReadOwner(PgSql\Connection $connection, int $ownerId): array
{
    $row = mpNativeOne($connection, 'SELECT extended_data::text AS extended_data,
        plugin_extended_data::text AS plugin_extended_data,
        gamets_last_updated::text AS gamets_last_updated
        FROM public.core_npc_master WHERE id = $1', [$ownerId]);
    $extended = json_decode((string)$row['extended_data'], true, 512, JSON_THROW_ON_ERROR);
    $plugin = json_decode((string)$row['plugin_extended_data'], true, 512, JSON_THROW_ON_ERROR);
    mpNativeRequire(is_array($extended) && is_array($plugin), 'owner-json-invalid');
    return ['extended_data' => $extended, 'plugin_extended_data' => $plugin, 'gamets_last_updated' => (float)$row['gamets_last_updated']];
}

function mpNativeHistory(PgSql\Connection $connection, int $ownerId): array
{
    $rows = mpNativeAll($connection, 'SELECT to_jsonb(h)::text AS snapshot
        FROM public.core_npc_master_history h WHERE npc_id = $1 ORDER BY history_id', [$ownerId]);
    foreach ($rows as &$row) {
        $snapshot = json_decode((string)($row['snapshot'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        mpNativeRequire(is_array($snapshot), 'history-snapshot-invalid');
        $row = $snapshot;
    }
    unset($row);
    return $rows;
}

function mpNativeMasterSnapshot(PgSql\Connection $connection, int $ownerId): array
{
    $row = mpNativeOne($connection, 'SELECT to_jsonb(n)::text AS snapshot
        FROM public.core_npc_master n WHERE id = $1', [$ownerId]);
    $snapshot = json_decode((string)($row['snapshot'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    mpNativeRequire(is_array($snapshot), 'master-snapshot-invalid');
    return $snapshot;
}

function mpNativeEdge(array $owner, string $name): array
{
    $edge = $owner['extended_data']['relationships'][$name] ?? null;
    mpNativeRequire(is_array($edge), 'expected-edge-missing');
    return $edge;
}

function mpNativeCanonicalJsonValue(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    foreach ($value as $key => $child) {
        $value[$key] = mpNativeCanonicalJsonValue($child);
    }
    if (!array_is_list($value)) {
        ksort($value, SORT_STRING);
    }
    return $value;
}

function mpNativeSameJsonValue(mixed $left, mixed $right): bool
{
    return mpNativeCanonicalJsonValue($left) === mpNativeCanonicalJsonValue($right);
}

function mpNativeLedger(array $owner): array
{
    $ledger = $owner['plugin_extended_data']['mind_poisoning'] ?? null;
    mpNativeRequire(is_array($ledger), 'ledger-missing');
    return $ledger;
}

function mpNativeAssertUnrelated(array $owner): void
{
    $edge = mpNativeEdge($owner, 'Inigo');
    mpNativeRequire(mpNativeSameJsonValue($edge, ['aff' => 7, 'type' => 'neutral', 'note' => 'untouched', 'custom_marker' => 'preserve-Inigo']), 'unrelated-edge-changed');
    $ownerSuffix = substr((string)($owner['extended_data']['fixture_field'] ?? ''), strlen('preserve-'));
    mpNativeRequire($ownerSuffix !== '' && ($owner['plugin_extended_data']['other_plugin']['keep'] ?? null) === 'plugin-' . $ownerSuffix, 'unrelated-extended-data-changed');
}

function mpNativeAssertLedgerFor(array $owner, int $eventId, int $expectedCount): void
{
    $ledger = mpNativeLedger($owner);
    $events = $ledger['events'] ?? null;
    mpNativeRequire(is_array($events) && count($events) === $expectedCount, 'ledger-event-count-mismatch');
    if ($expectedCount > 0) {
        mpNativeRequire((int)($events[0]['event_id'] ?? 0) === $eventId, 'ledger-event-id-mismatch');
    }
    $ownerSuffix = substr((string)($owner['extended_data']['fixture_field'] ?? ''), strlen('preserve-'));
    mpNativeRequire($ownerSuffix !== '' && ($owner['plugin_extended_data']['other_plugin']['keep'] ?? null) === 'plugin-' . $ownerSuffix, 'unrelated-plugin-data-invalid');
}

function mpNativeHistorySource(array $historyRow): bool
{
    return ($historyRow['extended_data']['_chim_history_source'] ?? null) === 'relationship';
}

function mpNativeAssertHistoryMatchesMaster(array $masterSnapshot, int $ownerId, array $historyRow): void
{
    $expected = $masterSnapshot;
    unset($expected['id']);
    $expected['extended_data']['_chim_history_source'] = 'relationship';
    $expected['history_id'] = $historyRow['history_id'] ?? null;
    $expected['npc_id'] = $ownerId;
    $expected['created'] = $historyRow['created'] ?? null;
    mpNativeRequire(mpNativeSameJsonValue($historyRow, $expected), 'history-full-row-mismatch');
}

function mpNativeAssertHistorySnapshot(PgSql\Connection $connection, int $ownerId, array $historyRow): void
{
    mpNativeAssertHistoryMatchesMaster(mpNativeMasterSnapshot($connection, $ownerId), $ownerId, $historyRow);
}

function mpNativeSetFault(PgSql\Connection $connection, int $ownerId, string $applicationName, string $mode): void
{
    mpNativeExec($connection, 'INSERT INTO mp_native_b1.faults (owner_id, app_name, mode) VALUES ($1, $2, $3)', [$ownerId, $applicationName, $mode]);
}

function mpNativeClearFault(PgSql\Connection $connection, string $applicationName): void
{
    mpNativeExec($connection, 'DELETE FROM mp_native_b1.faults WHERE app_name = $1', [$applicationName]);
}

function mpNativeLoadProxyReport(array $config): array
{
    $path = mpNativeSafeRootFile($config, 'proxy-after.json');
    $contents = @file_get_contents($path);
    $report = is_string($contents) ? json_decode($contents, true) : null;
    mpNativeRequire(is_array($report) && ($report['mode'] ?? null) === 'after', 'commit-proxy-report-invalid');
    return $report;
}

function mpNativeStartAckProxy(array $config): void
{
    $proxyDirectory = $config['root'] . '/proxy-after';
    $report = mpNativeSafeRootFile($config, 'proxy-after.json');
    mpNativeRequire(!file_exists($proxyDirectory) && !file_exists($report), 'commit-proxy-path-exists');
    $command = [$config['python'], $config['proxy_script'], '--root', $config['root'], '--mode', 'after'];
    mpNativeStartProcess($config, $command, 'ack-after', true);
    $socketPath = $proxyDirectory . '/.s.PGSQL.5432';
    mpNativeWaitFile($socketPath, 3000, 'commit-proxy-socket-timeout');
}

function mpNativeRunScenario(string $name, callable $run): void
{
    global $mpNativeScenarios;
    try {
        $details = $run();
        $mpNativeScenarios[$name] = ['status' => 'passed'] + $details;
    } catch (Throwable $error) {
        $code = $error instanceof MpNativeFixtureFailure ? $error->safeCode : 'scenario-exception';
        $mpNativeScenarios[$name] = ['status' => 'failed', 'code' => $code];
        throw new MpNativeFixtureFailure('scenario-' . $name . '-' . $code);
    }
}

function mpNativeRunSuite(array $config): void
{
    $controller = mpNativeConnect($config, 'mp-native-b1-controller');
    $lockConnection = mpNativeConnect($config, 'mp-native-b1-barrier-lock');
    mpNativeSetupDatabase($controller, $config);

    mpNativeRunScenario('combined-affinity-type-and-player-alias', static function () use ($config, $controller): array {
        $result = mpNativeRunChildSync($config, 'persist-combined');
        mpNativeRequire(($result['outcome'] ?? null) === 'committed' && ($result['persistence']['commit_state'] ?? null) === 'confirmed', 'combined-not-committed');
        $owner = mpNativeReadOwner($controller, 2001);
        $player = mpNativeEdge($owner, 'Player');
        mpNativeRequire(($player['aff'] ?? null) === 22 && ($player['type'] ?? null) === 'suspicious', 'combined-edge-mismatch');
        mpNativeRequire(($player['custom_marker'] ?? null) === 'preserve-Dragonborn', 'player-alias-custom-field-lost');
        mpNativeRequire(!array_key_exists('Dragonborn', $owner['extended_data']['relationships']), 'player-alias-not-canonicalized');
        mpNativeRequire($owner['gamets_last_updated'] === 1010.0, 'combined-timeline-mismatch');
        mpNativeRequire(($owner['plugin_extended_data']['other_plugin']['keep'] ?? null) === 'plugin-2001', 'combined-plugin-field-lost');
        mpNativeAssertUnrelated($owner);
        mpNativeAssertLedgerFor($owner, 3001, 1);
        $history = mpNativeHistory($controller, 2001);
        mpNativeRequire(count($history) === 1 && mpNativeHistorySource($history[0]), 'combined-history-mismatch');
        mpNativeRequire(($history[0]['extended_data']['relationships']['Player']['aff'] ?? null) === 22, 'combined-history-edge-mismatch');
        mpNativeRequire(($history[0]['plugin_extended_data']['mind_poisoning']['events'][0]['event_id'] ?? null) === 3001, 'combined-history-ledger-mismatch');
        mpNativeAssertHistorySnapshot($controller, 2001, $history[0]);
        return ['independent_history_rows' => count($history), 'independent_ledger_events' => 1];
    });

    mpNativeRunScenario('zero-affinity-type-only', static function () use ($config, $controller): array {
        $result = mpNativeRunChildSync($config, 'persist-zero-type');
        mpNativeRequire(($result['outcome'] ?? null) === 'committed' && ($result['persistence']['commit_state'] ?? null) === 'confirmed', 'type-only-not-committed');
        mpNativeRequire(($result['persistence']['affinity_changed_count'] ?? null) === 0 && ($result['persistence']['type_changed_count'] ?? null) === 1, 'type-only-change-count-mismatch');
        $owner = mpNativeReadOwner($controller, 2002);
        $edge = mpNativeEdge($owner, 'Balgruuf');
        mpNativeRequire(($edge['aff'] ?? null) === 12 && ($edge['type'] ?? null) === 'suspicious', 'type-only-edge-mismatch');
        mpNativeRequire($owner['gamets_last_updated'] === 1020.0, 'type-only-timeline-mismatch');
        mpNativeAssertUnrelated($owner);
        mpNativeAssertLedgerFor($owner, 3002, 1);
        $history = mpNativeHistory($controller, 2002);
        mpNativeRequire(count($history) === 1 && mpNativeHistorySource($history[0]), 'type-only-history-mismatch');
        mpNativeRequire(($history[0]['extended_data']['relationships']['Balgruuf']['type'] ?? null) === 'suspicious', 'type-only-history-edge-mismatch');
        mpNativeAssertHistorySnapshot($controller, 2002, $history[0]);
        return ['independent_history_rows' => count($history), 'affinity_changed_count' => 0];
    });

    mpNativeRunScenario('snapshot-verification-failure-rolls-back', static function () use ($config, $controller): array {
        mpNativeSetFault($controller, 2003, 'mp-native-b1-snapshot-fault', 'corrupt_history');
        try {
            $result = mpNativeRunChildSync($config, 'persist-snapshot-fault');
        } finally {
            mpNativeClearFault($controller, 'mp-native-b1-snapshot-fault');
        }
        mpNativeRequire(($result['outcome'] ?? null) === 'failed' && ($result['persistence']['persistence_reason'] ?? null) === 'snapshot-verification-failed', 'snapshot-fault-not-observed');
        mpNativeRequire(($result['persistence']['commit_state'] ?? null) === 'not_attempted', 'snapshot-fault-commit-state-untruthful');
        $owner = mpNativeReadOwner($controller, 2003);
        mpNativeRequire(mpNativeSameJsonValue(mpNativeEdge($owner, 'Balgruuf'), ['aff' => 12, 'type' => 'neutral', 'note' => 'baseline', 'custom_marker' => 'preserve-Balgruuf']), 'snapshot-fault-partial-edge');
        mpNativeRequire($owner['gamets_last_updated'] === 10.0, 'snapshot-fault-partial-timeline');
        mpNativeRequire(count(mpNativeLedger($owner)['events'] ?? []) === 0 && count(mpNativeHistory($controller, 2003)) === 0, 'snapshot-fault-partial-ledger-or-history');
        mpNativeAssertUnrelated($owner);
        return ['outcome' => 'failed', 'commit_state' => 'not_attempted', 'partial_rows' => 0];
    });

    mpNativeRunScenario('sequential-and-concurrent-duplicate-delivery', static function () use ($config, $controller, $lockConnection): array {
        $first = mpNativeRunChildSync($config, 'persist-duplicate-first');
        mpNativeRequire(($first['outcome'] ?? null) === 'committed', 'duplicate-first-not-committed');
        $firstOwner = mpNativeReadOwner($controller, 2004);
        $firstMaster = mpNativeMasterSnapshot($controller, 2004);
        $firstHistory = mpNativeHistory($controller, 2004);
        mpNativeRequire(count($firstHistory) === 1 && ($firstOwner['gamets_last_updated'] ?? null) === 1040.0, 'duplicate-first-state-mismatch');
        mpNativeAssertHistorySnapshot($controller, 2004, $firstHistory[0]);
        $second = mpNativeRunChildSync($config, 'persist-duplicate-second');
        mpNativeRequire(($second['outcome'] ?? null) === 'duplicate', 'sequential-duplicate-not-rejected');
        $app = 'mp-native-b1-dup-concurrent-first';
        mpNativeBeginBarrier($controller, $lockConnection, 2004, $app);
        try {
            $task = 'persist-concurrent-first';
            mpNativeStartPhpChild($config, $task);
            mpNativeWaitForLockWait($controller, $app, 'update core_npc_master', 700);
            $secondTask = 'persist-concurrent-second';
            mpNativeStartPhpChild($config, $secondTask);
            $secondExit = mpNativeWaitProcess($secondTask, 2500);
            $contender = mpNativeReadChildStatus($config, $secondTask);
            mpNativeRequire($secondExit === 0, 'duplicate-contender-exit');
            mpNativeRequire(($contender['outcome'] ?? null) === 'busy', 'concurrent-duplicate-lock-not-held');
            mpNativeReleaseBarrier($lockConnection);
            $firstExit = mpNativeWaitProcess($task, 8000);
            $barrierResult = mpNativeReadChildStatus($config, $task);
            mpNativeRequire($firstExit === 0, 'duplicate-barrier-first-exit');
            mpNativeRequire(($barrierResult['outcome'] ?? null) === 'committed', 'duplicate-barrier-first-not-committed');
        } finally {
            mpNativeReleaseBarrier($lockConnection);
        }
        $replay = mpNativeRunChildSync($config, 'persist-concurrent-replay');
        mpNativeRequire(($replay['outcome'] ?? null) === 'duplicate', 'concurrent-duplicate-replay-not-rejected');
        $owner = mpNativeReadOwner($controller, 2004);
        $edge = mpNativeEdge($owner, 'Balgruuf');
        mpNativeRequire(($edge['aff'] ?? null) === 14, 'duplicate-affinity-applied-more-than-once');
        $ledgerIds = array_map(static fn(array $entry): int => (int)($entry['event_id'] ?? 0), mpNativeLedger($owner)['events'] ?? []);
        $history = mpNativeHistory($controller, 2004);
        mpNativeRequire($ledgerIds === [3004, 3014] && count($history) === 2, 'duplicate-ledger-or-history-applied-more-than-once');
        mpNativeRequire(($history[0]['plugin_extended_data']['mind_poisoning']['events'][0]['event_id'] ?? null) === 3004
            && ($history[0]['extended_data']['relationships']['Balgruuf']['aff'] ?? null) === 13, 'duplicate-first-history-state-mismatch');
        mpNativeRequire(($history[1]['plugin_extended_data']['mind_poisoning']['events'][1]['event_id'] ?? null) === 3014
            && ($history[1]['extended_data']['relationships']['Balgruuf']['aff'] ?? null) === 14, 'duplicate-second-history-state-mismatch');
        mpNativeAssertHistoryMatchesMaster($firstMaster, 2004, $history[0]);
        mpNativeAssertHistorySnapshot($controller, 2004, $history[1]);
        mpNativeAssertUnrelated($owner);
        return ['sequential_replay' => 'duplicate', 'concurrent_contender' => 'busy', 'concurrent_replay' => 'duplicate', 'ledger_events' => 2, 'history_rows' => 2];
    });

    mpNativeRunScenario('plugin-then-core-fresh-rebase-under-shared-lock', static function () use ($config, $controller, $lockConnection): array {
        $pluginApp = 'mp-native-b1-mp-first';
        $coreApp = 'mp-native-b1-core-after';
        mpNativeBeginBarrier($controller, $lockConnection, 2005, $pluginApp);
        try {
            $pluginTask = 'persist-mp-core-first';
            mpNativeStartPhpChild($config, $pluginTask);
            mpNativeWaitForLockWait($controller, $pluginApp, 'update core_npc_master', 700);
            $coreTask = 'core-after-mp';
            mpNativeStartPhpChild($config, $coreTask);
            mpNativeWaitForLockWait($controller, $coreApp, 'pg_advisory_lock', 700);
            mpNativeAssertAdvisoryPair($controller, $pluginApp, $coreApp, 2005);
        } finally {
            mpNativeReleaseBarrier($lockConnection);
        }
        $pluginExit = mpNativeWaitProcess('persist-mp-core-first', 8000);
        $coreExit = mpNativeWaitProcess('core-after-mp', 8000);
        $plugin = mpNativeReadChildStatus($config, 'persist-mp-core-first');
        $core = mpNativeReadChildStatus($config, 'core-after-mp');
        mpNativeRequire($pluginExit === 0, 'plugin-first-child-exit');
        mpNativeRequire($coreExit === 0, 'core-after-child-exit');
        mpNativeRequire(($plugin['outcome'] ?? null) === 'committed' && ($plugin['persistence']['commit_state'] ?? null) === 'confirmed', 'plugin-first-not-committed');
        mpNativeRequire(($core['applied_targets'] ?? null) === ['Balgruuf'], 'core-after-not-applied');
        $owner = mpNativeReadOwner($controller, 2005);
        $edge = mpNativeEdge($owner, 'Balgruuf');
        mpNativeRequire(($edge['aff'] ?? null) === 15 && ($edge['type'] ?? null) === 'suspicious', 'core-stale-neutral-rebase-mismatch');
        mpNativeAssertUnrelated($owner);
        mpNativeAssertLedgerFor($owner, 3005, 1);
        $history = mpNativeHistory($controller, 2005);
        mpNativeRequire(count($history) === 2 && mpNativeHistorySource($history[0]) && mpNativeHistorySource($history[1]), 'core-rebase-history-mismatch');
        mpNativeRequire(($history[0]['extended_data']['relationships']['Balgruuf']['aff'] ?? null) === 14
            && ($history[0]['extended_data']['relationships']['Balgruuf']['type'] ?? null) === 'suspicious'
            && ($history[0]['plugin_extended_data']['mind_poisoning']['events'][0]['event_id'] ?? null) === 3005, 'core-rebase-plugin-history-mismatch');
        mpNativeAssertHistorySnapshot($controller, 2005, $history[1]);
        mpNativeAssertAdvisoryPairReleased($controller, 2005, $pluginApp, $coreApp);
        return ['final_affinity' => 15, 'final_type' => 'suspicious', 'history_rows' => count($history), 'shared_lock' => true];
    });

    mpNativeRunScenario('core-then-stale-plugin-whole-judgment-rejected', static function () use ($config, $controller): array {
        $core = mpNativeRunChildSync($config, 'core-first');
        mpNativeRequire(($core['applied_targets'] ?? null) === ['Balgruuf'], 'core-first-not-applied');
        $before = mpNativeReadOwner($controller, 2006);
        mpNativeRequire((mpNativeEdge($before, 'Balgruuf')['type'] ?? null) === 'suspicious', 'core-first-type-not-written');
        $result = mpNativeRunChildSync($config, 'persist-after-core');
        mpNativeRequire(($result['outcome'] ?? null) === 'stale' && ($result['persistence']['persistence_reason'] ?? null) === 'relationship-type-stale', 'stale-plugin-not-rejected');
        mpNativeRequire(($result['persistence']['commit_state'] ?? null) === 'not_attempted', 'stale-plugin-commit-state-untruthful');
        $after = mpNativeReadOwner($controller, 2006);
        mpNativeRequire(mpNativeSameJsonValue(mpNativeEdge($after, 'Balgruuf'), mpNativeEdge($before, 'Balgruuf')), 'stale-type-applied-affinity-partially');
        mpNativeRequire(count(mpNativeLedger($after)['events'] ?? []) === 0, 'stale-type-ledger-partial');
        mpNativeRequire(count(mpNativeHistory($controller, 2006)) === 1, 'stale-plugin-added-history');
        $history = mpNativeHistory($controller, 2006);
        mpNativeAssertHistorySnapshot($controller, 2006, $history[0]);
        mpNativeAssertUnrelated($after);
        return ['plugin_outcome' => 'stale', 'affinity_delta' => 0, 'ledger_events' => 0];
    });

    mpNativeRunScenario('prewrite-relationship-lock-change-refuses-whole-judgment', static function () use ($config, $controller): array {
        $task = 'persist-lock-stale';
        mpNativeStartPhpChild($config, $task);
        mpNativeWaitFile(mpNativeSafeRootFile($config, 'child-' . $task . '.ready'), 3000, 'lock-stale-child-not-ready');
        $before = mpNativeReadOwner($controller, 2009);
        mpNativeExec($controller, "UPDATE public.core_npc_master
            SET extended_data = jsonb_set(COALESCE(extended_data, '{}'::jsonb), '{relationships_locked}', 'true'::jsonb, true)
            WHERE id = 2009");
        $locked = mpNativeOne($controller, "SELECT extended_data->>'relationships_locked' AS locked FROM public.core_npc_master WHERE id = 2009");
        mpNativeRequire(($locked['locked'] ?? null) === 'true', 'relationship-lock-change-not-visible');
        mpNativeWriteJson(mpNativeSafeRootFile($config, 'child-' . $task . '-go.json'), ['released' => true]);
        $exitCode = mpNativeWaitProcess($task, 8000);
        $result = mpNativeReadChildStatus($config, $task);
        mpNativeRequire($exitCode === 0, 'lock-stale-child-exit');
        mpNativeRequire(($result['outcome'] ?? null) === 'locked'
            && ($result['persistence']['persistence_reason'] ?? null) === 'relationship-locked'
            && ($result['persistence']['commit_state'] ?? null) === 'not_attempted', 'prewrite-lock-not-rejected');
        $after = mpNativeReadOwner($controller, 2009);
        mpNativeRequire(mpNativeSameJsonValue(mpNativeEdge($after, 'Balgruuf'), mpNativeEdge($before, 'Balgruuf')), 'prewrite-lock-partial-affinity');
        mpNativeRequire(count(mpNativeLedger($after)['events'] ?? []) === 0
            && count(mpNativeHistory($controller, 2009)) === 0
            && $after['gamets_last_updated'] === 10.0, 'prewrite-lock-partial-ledger-history-or-timeline');
        mpNativeAssertUnrelated($after);
        return ['captured_before_lock_change' => true, 'outcome' => 'locked', 'commit_state' => 'not_attempted', 'partial_rows' => 0];
    });

    mpNativeRunScenario('prewrite-playthrough-scope-change-refuses-whole-judgment', static function () use ($config, $controller): array {
        $task = 'persist-scope-stale';
        mpNativeStartPhpChild($config, $task);
        mpNativeWaitFile(mpNativeSafeRootFile($config, 'child-' . $task . '.ready'), 3000, 'scope-stale-child-not-ready');
        $before = mpNativeReadOwner($controller, 2010);
        try {
            mpNativeExec($controller, 'UPDATE chim_meta.playthrough_profiles SET is_active = (id = 2)');
            $active = mpNativeOne($controller, 'SELECT id::text AS id FROM chim_meta.playthrough_profiles WHERE is_active IS TRUE');
            mpNativeRequire(($active['id'] ?? null) === '2', 'playthrough-scope-change-not-visible');
            mpNativeWriteJson(mpNativeSafeRootFile($config, 'child-' . $task . '-go.json'), ['released' => true]);
            $exitCode = mpNativeWaitProcess($task, 8000);
            $result = mpNativeReadChildStatus($config, $task);
            mpNativeRequire($exitCode === 0, 'scope-stale-child-exit');
            mpNativeRequire(($result['outcome'] ?? null) === 'stale'
                && ($result['persistence']['persistence_reason'] ?? null) === 'event-stale'
                && ($result['persistence']['commit_state'] ?? null) === 'not_attempted', 'prewrite-scope-not-rejected');
            $after = mpNativeReadOwner($controller, 2010);
            mpNativeRequire(mpNativeSameJsonValue(mpNativeEdge($after, 'Balgruuf'), mpNativeEdge($before, 'Balgruuf')), 'prewrite-scope-partial-affinity');
            mpNativeRequire(count(mpNativeLedger($after)['events'] ?? []) === 0
                && count(mpNativeHistory($controller, 2010)) === 0
                && $after['gamets_last_updated'] === 10.0, 'prewrite-scope-partial-ledger-history-or-timeline');
            mpNativeAssertUnrelated($after);
        } finally {
            mpNativeExec($controller, 'UPDATE chim_meta.playthrough_profiles SET is_active = (id = 1)');
        }
        return ['captured_under_playthrough' => '1', 'current_before_persist' => '2', 'outcome' => 'stale', 'partial_rows' => 0];
    });

    mpNativeRunScenario('backend-termination-before-commit-is-atomic', static function () use ($config, $controller): array {
        mpNativeSetFault($controller, 2007, 'mp-native-b1-backend-loss', 'terminate_backend');
        try {
            $result = mpNativeRunChildSync($config, 'persist-backend-loss');
        } finally {
            mpNativeClearFault($controller, 'mp-native-b1-backend-loss');
        }
        mpNativeRequire(($result['outcome'] ?? null) === 'failed', 'backend-loss-not-failed');
        mpNativeRequire(($result['persistence']['commit_state'] ?? null) === 'not_attempted', 'backend-loss-commit-state-untruthful');
        mpNativeRequire(($result['persistence']['cleanup_failed'] ?? null) === true, 'backend-loss-cleanup-failure-not-reported');
        $owner = mpNativeReadOwner($controller, 2007);
        mpNativeRequire(mpNativeSameJsonValue(mpNativeEdge($owner, 'Balgruuf'), ['aff' => 12, 'type' => 'neutral', 'note' => 'baseline', 'custom_marker' => 'preserve-Balgruuf']), 'backend-loss-partial-edge');
        mpNativeRequire($owner['gamets_last_updated'] === 10.0, 'backend-loss-partial-timeline');
        mpNativeRequire(count(mpNativeLedger($owner)['events'] ?? []) === 0 && count(mpNativeHistory($controller, 2007)) === 0, 'backend-loss-partial-ledger-or-history');
        mpNativeAssertUnrelated($owner);
        return ['commit_state' => 'not_attempted', 'cleanup_failed' => true, 'partial_rows' => 0];
    });

    mpNativeRunScenario('commit-ack-loss-is-unconfirmed-and-replay-is-deduped', static function () use ($config, $controller): array {
        mpNativeStartAckProxy($config);
        $result = mpNativeRunChildSync($config, 'persist-ack-loss', 12000);
        $proxyExit = mpNativeWaitProcess('ack-after', 8000);
        mpNativeRequire($proxyExit === 0, 'commit-proxy-exit-nonzero');
        $report = mpNativeLoadProxyReport($config);
        mpNativeRequire(($report['commit_forwarded'] ?? null) === true
            && ($report['server_command_complete'] ?? null) === true
            && ($report['server_ready_for_query'] ?? null) === true
            && ($report['ack_withheld'] ?? null) === true, 'commit-ack-loss-not-proven-on-wire');
        mpNativeRequire(($result['outcome'] ?? null) === 'failed' && ($result['persistence']['commit_state'] ?? null) === 'unconfirmed', 'commit-ack-loss-not-unconfirmed');
        mpNativeRequire(($result['persistence']['committed'] ?? null) === false, 'commit-ack-loss-claim-untruthful');
        $owner = mpNativeReadOwner($controller, 2008);
        mpNativeRequire((mpNativeEdge($owner, 'Balgruuf')['aff'] ?? null) === 13, 'commit-ack-server-commit-not-visible');
        mpNativeRequire($owner['gamets_last_updated'] === 1080.0, 'commit-ack-timeline-mismatch');
        mpNativeRequire(count(mpNativeLedger($owner)['events'] ?? []) === 1 && count(mpNativeHistory($controller, 2008)) === 1, 'commit-ack-server-effect-not-atomic');
        $history = mpNativeHistory($controller, 2008);
        mpNativeAssertHistorySnapshot($controller, 2008, $history[0]);
        $replay = mpNativeRunChildSync($config, 'persist-duplicate-replay');
        mpNativeRequire(($replay['outcome'] ?? null) === 'duplicate', 'commit-ack-replay-not-deduped');
        $afterReplay = mpNativeReadOwner($controller, 2008);
        mpNativeRequire((mpNativeEdge($afterReplay, 'Balgruuf')['aff'] ?? null) === 13
            && count(mpNativeLedger($afterReplay)['events'] ?? []) === 1
            && count(mpNativeHistory($controller, 2008)) === 1, 'commit-ack-replay-applied-twice');
        mpNativeAssertUnrelated($afterReplay);
        return ['server_commit_observed' => true, 'client_commit_state' => 'unconfirmed', 'replay' => 'duplicate', 'history_rows' => 1];
    });

    @pg_close($lockConnection);
    @pg_close($controller);
}

function mpNativeAssertAdvisoryPairReleased(PgSql\Connection $controller, int $ownerId, string $firstApp, string $secondApp): void
{
    $rows = mpNativeAll($controller, "SELECT count(*)::int AS held FROM pg_locks l
        JOIN pg_stat_activity a USING (pid)
        WHERE l.locktype='advisory' AND l.objsubid=1
          AND ((l.classid::bigint << 32) | l.objid::bigint) = $1
          AND a.application_name IN ($2, $3)", [1001000000 + $ownerId, $firstApp, $secondApp]);
    mpNativeRequire((int)($rows[0]['held'] ?? -1) === 0, 'shared-advisory-lock-not-released');
}

function mpNativeCleanupChildren(): void
{
    global $mpNativeChildren;
    foreach ($mpNativeChildren as $name => $process) {
        if (!is_resource($process)) {
            continue;
        }
        $status = proc_get_status($process);
        if ($status['running']) {
            @proc_terminate($process, 9);
        }
        @proc_close($process);
        unset($mpNativeChildren[$name]);
    }
}

$mpNativeExit = 0;
$mpNativeFailure = null;
$mpNativeConfig = null;
try {
    $mpNativeConfig = mpNativeConfig();
    mpNativeBootstrapCore($mpNativeConfig);
    if (($argv[1] ?? null) === '--child') {
        $task = $argv[2] ?? '';
        try {
            mpNativeChildMain($mpNativeConfig, $task);
        } catch (Throwable $error) {
            $safeCode = $error instanceof MpNativeFixtureFailure ? $error->safeCode : 'child-exception';
            try {
                mpNativeWriteChildStatus($mpNativeConfig, $task, ['status' => 'failed', 'code' => $safeCode]);
            } catch (Throwable) {
            }
            exit(2);
        }
    }
    mpNativeRunSuite($mpNativeConfig);
} catch (Throwable $error) {
    $mpNativeExit = 1;
    $mpNativeFailure = $error instanceof MpNativeFixtureFailure ? $error->safeCode : 'fixture-exception';
} finally {
    mpNativeCleanupChildren();
}

$mpNativeSummary = [
    'suite' => 'mp-native-postgres-b1',
    'status' => $mpNativeExit === 0 ? 'passed' : 'failed',
    'scenarios' => $mpNativeScenarios,
];
if ($mpNativeFailure !== null) {
    $mpNativeSummary['failure_code'] = $mpNativeFailure;
}
if (is_array($mpNativeConfig)) {
    try {
        mpNativeWriteJson(mpNativeSafeRootFile($mpNativeConfig, 'fixture-report.json'), $mpNativeSummary);
    } catch (Throwable) {
        $mpNativeExit = 1;
        $mpNativeFailure = 'fixture-report-write-failed';
        $mpNativeSummary['status'] = 'failed';
        $mpNativeSummary['failure_code'] = $mpNativeFailure;
    }
}
echo json_encode($mpNativeSummary, JSON_UNESCAPED_SLASHES) . "\n";
exit($mpNativeExit);
