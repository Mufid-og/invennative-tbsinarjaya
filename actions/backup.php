<?php
// actions/backup.php

if (($_GET['action'] ?? '') === 'download') {
    $dbPath = (require __DIR__ . '/../config/database.php')['path'];

    if (!is_file($dbPath)) {
        set_flash('error', 'File database tidak ditemukan.');
        redirect('/backup-database');
    }

    db()->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetchAll();

    $filename = 'inventaris_backup_' . date('Y-m-d_His') . '.sqlite';

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($dbPath));
    header('Cache-Control: no-cache');
    header('Pragma: public');

    readfile($dbPath);
    log_aktivitas('BACKUP', 'backup', null, "Backup database: {$filename}");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file_restore']) || $_FILES['file_restore']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file_restore']['tmp_name'])) {
        set_flash('error', 'Pilih file database (.sqlite/.db) terlebih dahulu.');
        redirect('/backup-database');
    }

    $tmpFile = $_FILES['file_restore']['tmp_name'];

    $handle = fopen($tmpFile, 'rb');
    $header = fread($handle, 16);
    fclose($handle);

    if (!str_starts_with($header, "SQLite format 3\0")) {
        set_flash('error', 'File bukan SQLite yang valid.');
        redirect('/backup-database');
    }

    $config = require __DIR__ . '/../config/database.php';
    $dbPath = $config['path'];

    log_aktivitas('RESTORE', 'backup', null, "Restore database dari: " . $_FILES['file_restore']['name']);

    $db = null;

    copy($dbPath, $dbPath . '.bak');

    if (!move_uploaded_file($tmpFile, $dbPath)) {
        set_flash('error', 'Gagal menimpa file database.');
        redirect('/backup-database');
    }

    set_flash('success', 'Database berhasil dipulihkan.');
    redirect('/dashboard');
}

redirect('/backup-database');