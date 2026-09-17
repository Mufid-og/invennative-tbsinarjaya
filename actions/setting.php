<?php
// actions/setting.php

$config = require __DIR__ . '/../config/database.php';
$dbPath = $config['path'];

if (($_GET['action'] ?? '') === 'download') {
    if (!is_file($dbPath)) {
        set_flash('error', 'File database tidak ditemukan.');
        redirect('/setting');
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
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'restore') {
        if (!isset($_FILES['file_restore']) || $_FILES['file_restore']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file_restore']['tmp_name'])) {
            set_flash('error', 'Pilih file database (.sqlite/.db) terlebih dahulu.');
            redirect('/setting');
        }

        $tmpFile = $_FILES['file_restore']['tmp_name'];

        $handle = fopen($tmpFile, 'rb');
        $header = fread($handle, 16);
        fclose($handle);

        if (!str_starts_with($header, "SQLite format 3\0")) {
            set_flash('error', 'File bukan SQLite yang valid.');
            redirect('/setting');
        }

        log_aktivitas('RESTORE', 'backup', null, "Restore database dari: " . $_FILES['file_restore']['name']);

        $db = null;

        copy($dbPath, $dbPath . '.bak');

        if (!move_uploaded_file($tmpFile, $dbPath)) {
            set_flash('error', 'Gagal menimpa file database.');
            redirect('/setting');
        }

        set_flash('success', 'Database berhasil dipulihkan.');
        redirect('/dashboard');
    }

    if ($aksi === 'update') {
        handle_update_app();
    }

    redirect('/setting');
}

redirect('/setting');

function handle_update_app(): void
{
    $config = require __DIR__ . '/../config/database.php';
    $dbPath = $config['path'];
    $root = realpath(__DIR__ . '/..');

    $appInfo = include __DIR__ . '/../config/version.php';
    $rootFolder = trim((string)($appInfo['zip_root_folder'] ?? ''), '/');

    if ($rootFolder === '') {
        set_flash('error', 'Nama folder pembungkus ZIP belum diatur di config/version.php.');
        redirect('/setting');
    }

    $normalizeZipName = function (string $name): string {
        $name = ltrim($name, '/');
        return str_replace('\\', '/', $name);
    };

    if (!isset($_FILES['file_app']) || $_FILES['file_app']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file_app']['tmp_name'])) {
        set_flash('error', 'Pilih file ZIP aplikasi terlebih dahulu.');
        redirect('/setting');
    }

    $tmpFile = $_FILES['file_app']['tmp_name'];
    $originalName = $_FILES['file_app']['name'];

    if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
        set_flash('error', 'File harus berformat ZIP.');
        redirect('/setting');
    }

    if (!class_exists('ZipArchive')) {
        set_flash('error', 'Ekstensi php-zip tidak tersedia. Aktifkan terlebih dahulu.');
        redirect('/setting');
    }

    $staging = sys_get_temp_dir() . '/invennative_update_' . bin2hex(random_bytes(8));

    if (!mkdir($staging, 0777, true) && !is_dir($staging)) {
        set_flash('error', 'Gagal membuat folder staging update.');
        redirect('/setting');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpFile) !== true) {
        set_flash('error', 'File ZIP tidak valid / korup.');
        redirect('/setting');
    }

    db()->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetchAll();
    if (is_file($dbPath)) {
        $backupFile = $dbPath . '.bak';
        copy($dbPath, $backupFile);
    }

    $totalUncompressed = 0;
    $totalEntries = $zip->numFiles;

    if ($totalEntries === 0) {
        $zip->close();
        set_flash('error', 'File ZIP kosong.');
        redirect('/setting');
    }
    if ($totalEntries > 5000) {
        $zip->close();
        set_flash('error', 'Terlalu banyak file dalam ZIP.');
        redirect('/setting');
    }

    for ($i = 0; $i < $totalEntries; $i++) {
        $stat = $zip->statIndex($i);
        $totalUncompressed += (int)($stat['size'] ?? 0);
    }

    if ($totalUncompressed > 209715200) {
        $zip->close();
        set_flash('error', 'Ukuran total file dalam ZIP melebihi batas (200 MB).');
        redirect('/setting');
    }

    $foundMarkers = ['index.php' => false, 'router.php' => false, 'pages.php' => false];
    $markerDirs = ['pages', 'actions', 'config', 'includes', 'templates'];

    for ($i = 0; $i < $totalEntries; $i++) {
        $stat = $zip->statIndex($i);
        $name = $normalizeZipName((string)($stat['name'] ?? ''));

        if ($name === '') {
            continue;
        }
        if ($name !== $rootFolder && !str_starts_with($name, $rootFolder . '/')) {
            $zip->close();
            set_flash('error', "Struktur ZIP tidak valid. ZIP harus berisi satu folder pembungkus bernama '{$rootFolder}' yang di dalamnya terdapat file aplikasi.");
            redirect('/setting');
        }
        if ($name === $rootFolder) {
            continue;
        }

        $cleanName = substr($name, strlen($rootFolder) + 1);
        if ($cleanName === '' || str_ends_with($cleanName, '/')) {
            continue;
        }
        if (str_starts_with($cleanName, 'database/')) {
            continue;
        }
        if ($cleanName === 'database' || in_array($cleanName, ['database', 'database/'], true)) {
            continue;
        }

        foreach (['..', '://', '%00', "\0"] as $bad) {
            if (str_contains($cleanName, $bad)) {
                $zip->close();
                set_flash('error', 'ZIP mengandung path tidak valid: ' . $cleanName);
                redirect('/setting');
            }
        }
        if (preg_match('/^[A-Za-z]:/', $cleanName)) {
            $zip->close();
            set_flash('error', 'ZIP mengandung path absolut.');
            redirect('/setting');
        }
        if (str_starts_with($cleanName, '/')) {
            $zip->close();
            set_flash('error', 'ZIP mengandung path absolut (/).');
            redirect('/setting');
        }

        if ($cleanName === 'index.php') {
            $foundMarkers['index.php'] = true;
        }
        if ($cleanName === 'router.php') {
            $foundMarkers['router.php'] = true;
        }
        foreach ($markerDirs as $dir) {
            if ($cleanName === $dir || str_starts_with($cleanName, $dir . '/')) {
                $foundMarkers['pages.php'] = true;
            }
        }
    }

    if (!$foundMarkers['index.php'] || !$foundMarkers['router.php'] || !$foundMarkers['pages.php']) {
        $zip->close();
        set_flash('error', "ZIP bukan paket aplikasi yang valid di dalam folder '{$rootFolder}'. Pastikan berisi index.php, router.php, dan folder pages/, actions/, config/, includes/, templates/.");
        redirect('/setting');
    }

    for ($i = 0; $i < $totalEntries; $i++) {
        $stat = $zip->statIndex($i);
        $name = $normalizeZipName((string)($stat['name'] ?? ''));

        if ($name === '') {
            continue;
        }
        if ($name !== $rootFolder && !str_starts_with($name, $rootFolder . '/')) {
            continue;
        }
        if ($name === $rootFolder) {
            continue;
        }

        $relName = substr($name, strlen($rootFolder) + 1);
        if ($relName === '' || str_ends_with($relName, '/')) {
            continue;
        }
        if (str_starts_with($relName, 'database/') || in_array($relName, ['database', 'database/'], true)) {
            continue;
        }

        if (str_starts_with($relName, 'storage/')) {
            continue;
        }

        $target = $root . '/' . $relName;
        $targetDir = dirname($target);

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $content = $zip->getFromIndex($i);
        if ($content === false) {
            $zip->close();
            set_flash('error', 'Gagal membaca entri ZIP: ' . $name);
            redirect('/setting');
        }

        @file_put_contents($target, $content);
    }

    $zip->close();

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    @array_map('unlink', glob($staging . '/*') ?: []);
    @rmdir($staging);

    $newVersion = 'baru';
    $versionFile = $root . '/config/version.php';
    if (is_file($versionFile)) {
        $v = include $versionFile;
        if (is_array($v) && isset($v['version'])) {
            $newVersion = $v['version'];
        }
    }

    log_aktivitas('UPDATE', 'setting', null, "Update aplikasi ke v{$newVersion} dari: {$originalName}");

    set_flash('success', "Update aplikasi berhasil (v{$newVersion}). Database tidak diubah.");
    redirect('/setting');
}