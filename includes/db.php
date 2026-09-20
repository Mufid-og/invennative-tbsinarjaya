<?php

declare(strict_types=1);

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
} else {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Native prepared statements — SQLite mengompilasi SQL sekali dan cache
    // bytecode di sisi driver; EMULATE_PREPARES=false mematikan emulasi PHP.
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA foreign_keys = ON');
}

function db(): PDO
{
    global $db;
    return $db;
}
