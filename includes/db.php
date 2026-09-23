<?php

declare(strict_types=1);

require_once __DIR__ . '/migrations.php';

$config = require __DIR__ . '/../config/database.php';
$dbPath = $config['path'];

if (!is_file($dbPath)) {
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    $seed = file_get_contents(__DIR__ . '/../database/seed.sql');
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Native prepared statements — SQLite mengompilasi SQL sekali dan cache
    // bytecode di sisi driver; EMULATE_PREPARES=false mematikan emulasi PHP.
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec($schema);
    $db->exec($seed);
    // Lengkapi DB baru dengan objek post-schema (FTS5, ukuran_numeric, dst).
    migrations_run($db);
} else {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Native prepared statements — SQLite mengompilasi SQL sekali dan cache
    // bytecode di sisi driver; EMULATE_PREPARES=false mematikan emulasi PHP.
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA foreign_keys = ON');
    // Auto-heal: hasil restore backup lama / DB versi lama akan dikoreksi
    // otomatis ke struktur terbaru sesuai PRAGMA user_version.
    migrations_run($db);
}

function db(): PDO
{
    if (empty($GLOBALS['__pdo_singleton'])) {
        $config = require __DIR__ . '/../config/database.php';
        $pdo = new PDO('sqlite:' . $config['path']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $GLOBALS['__pdo_singleton'] = $pdo;
        // Auto-heal schema bila file DB berasal dari backup/versi lama.
        migrations_run($pdo);
    }
    return $GLOBALS['__pdo_singleton'];
}

function close_db(): void
{
    if (!empty($GLOBALS['__pdo_singleton'])) {
        try {
            $GLOBALS['__pdo_singleton']->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (Throwable $e) {
            // Abaikan — koneksi akan ditutup paksa
        }
        $GLOBALS['__pdo_singleton'] = null;
    }
}
