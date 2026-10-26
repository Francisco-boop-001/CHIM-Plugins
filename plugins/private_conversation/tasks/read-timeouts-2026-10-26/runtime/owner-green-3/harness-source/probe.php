<?php
declare(strict_types=1);

[$script, $packageRoot, $engineRoot, $mode] = $argv;
$packageRoot = rtrim($packageRoot, DIRECTORY_SEPARATOR);
$engineRoot = rtrim($engineRoot, DIRECTORY_SEPARATOR);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $engineRoot . '/log/php-error.log');
$started = null;

function pcv_probe_json(array $value): never
{
    echo json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

function pcv_probe_rows_digest(array $rows): string
{
    usort($rows, static fn(array $left, array $right): int => (int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0));
    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function pcv_probe_catalog_rows(string $mode): never
{
    require_once $GLOBALS['packageRoot'] . '/state.php';
    $started = hrtime(true);
    $rows = pcv_read_npc_catalog();
    pcv_probe_json([
        'mode' => $mode,
        'status' => 'ok',
        'elapsed_ms' => round((hrtime(true) - $started) / 1_000_000, 1),
        'row_count' => count($rows),
        'field_count' => count($rows[0] ?? []),
    ]);
}

function pcv_probe_read_bounds(string $mode): never
{
    require_once $GLOBALS['packageRoot'] . '/state.php';
    $bounds = pcv_with_read_connection(static function ($connection): array {
        $result = @pg_query($connection,
            "SELECT (SELECT setting::integer FROM pg_settings WHERE name = 'lock_timeout') AS lock_ms, "
            . "(SELECT setting::integer FROM pg_settings WHERE name = 'statement_timeout') AS statement_ms");
        if ($result === false) {
            throw new RuntimeException('Read bound probe query failed.');
        }
        try {
            $row = pg_fetch_assoc($result);
            if (!is_array($row)) {
                throw new RuntimeException('Read bound probe returned no row.');
            }
            return ['lock_ms' => (int)$row['lock_ms'], 'statement_ms' => (int)$row['statement_ms']];
        } finally {
            @pg_free_result($result);
        }
    });
    pcv_probe_json(['mode' => $mode, 'status' => 'ok'] + $bounds);
}

function pcv_probe_identity_fallback(string $mode): never
{
    require_once $GLOBALS['packageRoot'] . '/state.php';
    $identity = pcv_current_identity(true);
    $expected = hash('sha256', 'unprofiled:audit player');
    pcv_probe_json([
        'mode' => $mode,
        'status' => is_string($identity['key'] ?? null) ? 'ok' : 'unavailable',
        'fallback_matches' => ($identity['key'] ?? null) === $expected,
        'player_matches' => ($identity['player_name'] ?? null) === 'Audit Player',
    ]);
}

function pcv_probe_caller_preservation(string $mode, string $engineRoot): never
{
    require_once $engineRoot . '/lib/postgresql.class.php';
    $callerObject = new sql();
    $GLOBALS['db'] = $callerObject;
    $link = new ReflectionProperty(sql::class, 'link');
    $link->setAccessible(true);
    $caller = $link->getValue();
    if (!$caller) {
        throw new RuntimeException('Native sql caller connection is unavailable.');
    }
    foreach ([
        "SET lock_timeout = '123ms'",
        "SET statement_timeout = '789ms'",
        'BEGIN',
        'CREATE TEMP TABLE pcv_probe_marker (value integer) ON COMMIT PRESERVE ROWS',
        'INSERT INTO pcv_probe_marker(value) VALUES (41)',
    ] as $query) {
        if (@pg_query($caller, $query) === false) {
            throw new RuntimeException('Caller-transaction fixture setup failed.');
        }
    }
    $started = hrtime(true);
    $readStatus = 'ok';
    $rowCount = null;
    $errorClass = null;
    try {
        if (str_starts_with($mode, 'catalog_preservation_statement_')) {
            require_once $GLOBALS['packageRoot'] . '/state.php';
            $sleepSeconds = str_ends_with($mode, '_short') ? 1.5 : 3.5;
            pcv_with_read_connection(static function ($connection) use ($sleepSeconds): void {
                $result = @pg_query($connection, 'SELECT pg_sleep(' . $sleepSeconds . ')');
                if ($result === false) {
                    throw new RuntimeException('Bounded statement did not complete.');
                }
                @pg_free_result($result);
            });
        } elseif ($mode === 'catalog_preservation_scope') {
            require_once $GLOBALS['packageRoot'] . '/scope.php';
            $rows = pcvScopeLoadNpcCatalog();
        } else {
            if (!defined('PCV_UI_TEST')) {
                define('PCV_UI_TEST', true);
            }
            if (!(require $GLOBALS['packageRoot'] . '/installation_guard.php')) {
                throw new RuntimeException('Isolated package failed its installation guard.');
            }
            require_once $GLOBALS['packageRoot'] . '/index.php';
            $rows = pcv_npc_rows($engineRoot);
        }
        if (!str_starts_with($mode, 'catalog_preservation_statement_') && !is_array($rows)) {
            throw new RuntimeException('Catalog caller returned a non-array.');
        }
        if (isset($rows) && is_array($rows)) {
            $rowCount = count($rows);
        }
    } catch (Throwable $error) {
        $readStatus = 'error';
        $errorClass = get_class($error);
    }
    $elapsed = round((hrtime(true) - $started) / 1_000_000, 1);
    $check = @pg_query($caller,
        "SELECT (SELECT setting::integer FROM pg_settings WHERE name = 'lock_timeout') AS lock_ms, "
        . "(SELECT setting::integer FROM pg_settings WHERE name = 'statement_timeout') AS statement_ms, "
        . '(SELECT count(*) FROM pg_temp.pcv_probe_marker WHERE value = 41) AS marker_count');
    $checkRow = $check === false ? false : pg_fetch_assoc($check);
    if ($check !== false) {
        @pg_free_result($check);
    }
    $callerLinkAfter = $link->getValue();
    $callerPreserved = pg_transaction_status($caller) === PGSQL_TRANSACTION_INTRANS
        && is_array($checkRow)
        && (int)$checkRow['lock_ms'] === 123
        && (int)$checkRow['statement_ms'] === 789
        && (int)$checkRow['marker_count'] === 1
        && $callerLinkAfter === $caller
        && ($GLOBALS['db'] ?? null) === $callerObject;
    @pg_query($caller, 'ROLLBACK');
    pcv_probe_json([
        'mode' => $mode,
        'status' => $readStatus,
        'row_count' => $rowCount,
        'elapsed_ms' => $elapsed,
        'error_class' => $errorClass,
        'caller_preserved' => $callerPreserved,
    ]);
}

try {
    $GLOBALS['ENGINE_PATH'] = $engineRoot . DIRECTORY_SEPARATOR;
    $GLOBALS['packageRoot'] = $packageRoot;

    if ($mode === 'catalog_reader') {
        pcv_probe_catalog_rows($mode);
    }
    if ($mode === 'read_bounds_default' || $mode === 'read_bounds_shorter') {
        pcv_probe_read_bounds($mode);
    }
    if ($mode === 'identity_no_active' || $mode === 'identity_no_profile_table') {
        pcv_probe_identity_fallback($mode);
    }
    if (in_array($mode, [
        'catalog_preservation_index',
        'catalog_preservation_scope',
        'catalog_preservation_statement_default',
        'catalog_preservation_statement_short',
    ], true)) {
        pcv_probe_caller_preservation($mode, $engineRoot);
    }

    if ($mode === 'initialize') {
        $connection = pg_connect('host=127.0.0.1 port=5432 dbname=dwemer user=dwemer password=dwemer', PGSQL_CONNECT_FORCE_NEW);
        if (!$connection) {
            throw new RuntimeException('Private fixture connection failed.');
        }
        require_once $engineRoot . '/lib/playthrough_home.php';
        require_once $engineRoot . '/lib/playthrough_autosave.php';
        dragon_break_ensure_meta_schema($connection);
        $setup = [
            'CREATE SCHEMA chim_profile_audit',
            'CREATE TABLE public.core_player(id text PRIMARY KEY,value text)',
            "INSERT INTO public.core_player VALUES('playthrough_id','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),('player_name','Audit Player')",
            "INSERT INTO chim_meta.playthrough_profiles(name,size_bytes,is_active,storage_type,schema_name,player_name) "
                . "VALUES('Audit',0,true,'schema','chim_profile_audit','Audit Player')",
        ];
        foreach ($setup as $sql) {
            if (@pg_query($connection, $sql) === false) {
                throw new RuntimeException('Identity fixture initialization failed.');
            }
        }
        pg_close($connection);
        pcv_probe_json(['initialized' => true]);
    }

    if ($mode === 'identity' || $mode === 'identity_locked') {
        require_once $packageRoot . '/state.php';
        $started = hrtime(true);
        $identity = pcv_current_identity(true);
        $elapsed = (hrtime(true) - $started) / 1_000_000;
        pcv_probe_json([
            'mode' => $mode,
            'status' => is_string($identity['key'] ?? null) && $identity['key'] !== '' ? 'ok' : 'unavailable',
            'elapsed_ms' => round($elapsed, 1),
            'has_identity' => is_string($identity['key'] ?? null) && $identity['key'] !== '',
            'player_matches' => ($identity['player_name'] ?? null) === 'Audit Player',
        ]);
    }

    if (in_array($mode, ['catalog_index', 'catalog_index_locked'], true)) {
        if (!defined('PCV_UI_TEST')) {
            define('PCV_UI_TEST', true);
        }
        if (!(require $packageRoot . '/installation_guard.php')) {
            throw new RuntimeException('The isolated PCV package did not pass its installation guard.');
        }
        require_once $packageRoot . '/index.php';
        $started = hrtime(true);
        $rows = pcv_npc_rows($engineRoot);
        $elapsed = (hrtime(true) - $started) / 1_000_000;
    } elseif (in_array($mode, ['catalog_scope', 'catalog_scope_locked'], true)) {
        require_once $engineRoot . '/lib/postgresql.class.php';
        $GLOBALS['db'] = new sql();
        require_once $engineRoot . '/lib/core/npc_master.class.php';
        require_once $packageRoot . '/scope.php';
        $started = hrtime(true);
        $rows = pcvScopeLoadNpcCatalog();
        $elapsed = (hrtime(true) - $started) / 1_000_000;
    } else {
        throw new InvalidArgumentException('Unknown probe mode.');
    }

    if (!is_array($rows)) {
        throw new RuntimeException('Catalog caller returned a non-array.');
    }

    if (!isset($GLOBALS['db']) || !($GLOBALS['db'] instanceof sql)) {
        require_once $engineRoot . '/lib/postgresql.class.php';
        $GLOBALS['db'] = new sql();
    }
    require_once $engineRoot . '/lib/core/npc_master.class.php';
    $expected = (new NpcMaster())->getAll();
    if (!is_array($expected)) {
        throw new RuntimeException('Native NpcMaster returned a non-array.');
    }
    pcv_probe_json([
        'mode' => $mode,
        'status' => 'ok',
        'elapsed_ms' => round($elapsed, 1),
        'row_count' => count($rows),
        'field_count' => count($rows[0] ?? []),
        'matches_native' => pcv_probe_rows_digest($rows) === pcv_probe_rows_digest($expected),
        'rows_sha256' => pcv_probe_rows_digest($rows),
    ]);
} catch (Throwable $error) {
    pcv_probe_json([
        'mode' => $mode,
        'status' => 'error',
        'failure' => 'probe_exception',
        'elapsed_ms' => is_int($started) ? round((hrtime(true) - $started) / 1_000_000, 1) : null,
        'error_class' => get_class($error),
    ]);
}
