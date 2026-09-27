<?php

declare(strict_types=1);

/**
 * tools/smoke_test.php
 *
 * Smoke test otomatis yang dipakai sebagai GATE sebelum build paket ZIP.
 *
 * ALUR:
 *   1. Bangun database uji SEMENTARA (sys_get_temp_dir) dari schema.sql +
 *      seed.sql, dengan setelan PDO identik produksi (WAL, foreign_keys ON,
 *      emulate_prepares false), lalu jalankan migrations_run() — persis
 *      seperti yang diterima client baru.
 *   2. Uji rollback-transaksi untuk alur tulis kritis: add/edit ukuran,
 *      mutasi, verifikasi, edit user. Setiap skenario memakai
 *      beginTransaction() ... rollBack() lalu memastikan tidak ada jejak
 *      yang tertinggal (termasuk shadow index FTS5).
 *   3. Uji integritas: PRAGMA integrity_check, foreign_key_check, dan
 *      invarian sinkronisasi COUNT(fts_X) == COUNT(X) untuk 7 tabel.
 *
 * Database produksi (database/inventaris.sqlite) TIDAK pernah disentuh.
 *
 * KELUARAN: daftar PASS/FAIL + ringkasan. Exit code 0 bila semua hijau,
 * 1 bila ada yang gagal — cocok dipanggil dari pipeline build.
 *
 * Jalankan: php tools/smoke_test.php
 */

require_once __DIR__ . '/../includes/migrations.php';

const SMOKE_APP_ROOT = __DIR__ . '/..';

// [tabel asli => tabel FTS5 miliknya]
const SMOKE_FTS_TABLES = [
    'produk'          => 'fts_produk',
    'merek'           => 'fts_merek',
    'ukuran'          => 'fts_ukuran',
    'mutasi_stok'     => 'fts_mutasi_stok',
    'verifikasi_stok' => 'fts_verifikasi_stok',
    'log_aktivitas'   => 'fts_log_aktivitas',
    'users'           => 'fts_users',
];

$passCount = 0;
$failCount = 0;

function record(string $label, bool $cond): void
{
    global $passCount, $failCount;
    echo ($cond ? 'PASS' : 'FAIL') . " - {$label}\n";
    if ($cond) {
        $passCount++;
    } else {
        $failCount++;
    }
}

function count_rows(PDO $db, string $table): int
{
    return (int)$db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

/** PDO dengan setelan identik includes/db.php (produksi). */
function smoke_make_pdo(string $path): PDO
{
    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('PRAGMA foreign_keys = ON');
    return $db;
}

/** Sisipkan sampel produk → merek → ukuran (harus berada dalam transaksi). */
function smoke_insert_sample_chain(PDO $db): array
{
    $db->prepare('INSERT INTO produk (nama, created_at, updated_at) VALUES (?,?,?)')
        ->execute(['Sample Produk', 't', 't']);
    $idP = (int)$db->lastInsertId();

    $db->prepare('INSERT INTO merek (id_produk, merek, created_at, updated_at) VALUES (?,?,?,?)')
        ->execute([$idP, 'Sample Merek', 't', 't']);
    $idM = (int)$db->lastInsertId();

    $db->prepare('INSERT INTO ukuran (id_merek, ukuran, stok, harga_modal, harga_jual, created_at, updated_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([$idM, '1.5', 0, 0, 0, 't', 't']);
    $idU = (int)$db->lastInsertId();

    return ['produk' => $idP, 'merek' => $idM, 'ukuran' => $idU];
}

/** Periksa sinkronisasi FTS untuk seluruh pasangan tabel. */
function smoke_check_fts_sync(PDO $db, string $label): void
{
    foreach (SMOKE_FTS_TABLES as $base => $fts) {
        $ok = count_rows($db, $fts) === count_rows($db, $base);
        record("{$label}: COUNT({$fts}) == COUNT({$base})", $ok);
    }
}

/** Hapus direktori temp beserta isinya. */
function smoke_cleanup(string $dir): void
{
    foreach (glob($dir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($dir);
}

// ============================================================================
// Skenario tes
// ============================================================================

function smoke_test_structure(PDO $db): void
{
    record(
        'PRAGMA user_version mencapai MIGRATIONS_LATEST (' . MIGRATIONS_LATEST . ')',
        (int)$db->query('PRAGMA user_version')->fetchColumn() === MIGRATIONS_LATEST
    );
    record(
        'PRAGMA integrity_check = ok',
        strtolower((string)$db->query('PRAGMA integrity_check')->fetchColumn()) === 'ok'
    );
    record(
        'PRAGMA foreign_key_check kosong',
        count($db->query('PRAGMA foreign_key_check')->fetchAll()) === 0
    );

    foreach (SMOKE_FTS_TABLES as $fts) {
        $exists = (bool)$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='{$fts}'")->fetchColumn();
        record("tabel FTS tersedia: {$fts}", $exists);
    }

    $hasNumeric = false;
    foreach ($db->query('PRAGMA table_info(ukuran)')->fetchAll() as $col) {
        if (($col['name'] ?? '') === 'ukuran_numeric') {
            $hasNumeric = true;
            break;
        }
    }
    record('kolom ukuran_numeric ada (+ trigger v1)', $hasNumeric);
}

function smoke_test_ukuran(PDO $db): void
{
    $db->beginTransaction();
    $ids = smoke_insert_sample_chain($db);

    $row = $db->query("SELECT ukuran, ukuran_numeric FROM ukuran WHERE id={$ids['ukuran']}")->fetch();
    record('trigger ukuran_numeric mengisi CAST saat INSERT (1.5)', (float)$row['ukuran_numeric'] === 1.5);

    $db->exec("UPDATE ukuran SET ukuran='2' WHERE id={$ids['ukuran']}");
    $row = $db->query("SELECT ukuran, ukuran_numeric FROM ukuran WHERE id={$ids['ukuran']}")->fetch();
    record('trigger ukuran_numeric sinkron saat UPDATE (2)', (float)$row['ukuran_numeric'] === 2.0);

    record(
        'FTS produk/merek/ukuran sinkron di dalam transaksi',
        count_rows($db, 'fts_produk') === count_rows($db, 'produk')
        && count_rows($db, 'fts_merek') === count_rows($db, 'merek')
        && count_rows($db, 'fts_ukuran') === count_rows($db, 'ukuran')
    );
    $db->rollBack();

    record('rollback add/edit ukuran: produk kosong', count_rows($db, 'produk') === 0);
    record('rollback add/edit ukuran: merek kosong', count_rows($db, 'merek') === 0);
    record('rollback add/edit ukuran: ukuran kosong', count_rows($db, 'ukuran') === 0);
    smoke_check_fts_sync($db, 'rollback add/edit ukuran: FTS balik');
}

function smoke_test_mutasi(PDO $db): void
{
    $db->beginTransaction();
    $ids = smoke_insert_sample_chain($db);

    $db->exec("UPDATE ukuran SET stok = stok + 10 WHERE id={$ids['ukuran']}");
    $db->prepare('INSERT INTO mutasi_stok (id_ukuran, tanggal, jenis, qty, harga_modal, harga_jual, keterangan, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$ids['ukuran'], date('Y-m-d'), 'masuk', 10, 1000, 1200, 'QA mutasi', 't', 't']);
    $idMut = (int)$db->lastInsertId();

    $db->prepare('INSERT INTO log_aktivitas (id_user, aksi, modul, id_referensi, deskripsi, created_at, updated_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([1, 'CREATE', 'mutasi', $idMut, 'QA mutasi', 't', 't']);

    record(
        'stok ukuran bertambah selama transaksi (10)',
        (float)$db->query("SELECT stok FROM ukuran WHERE id={$ids['ukuran']}")->fetchColumn() === 10.0
    );
    record('FTS mutasi/log/ukuran sinkron di dalam transaksi', count_rows($db, 'fts_mutasi_stok') === count_rows($db, 'mutasi_stok'));
    $db->rollBack();

    record('rollback mutasi: mutasi_stok kosong', count_rows($db, 'mutasi_stok') === 0);
    record('rollback mutasi: log_aktivitas kosong', count_rows($db, 'log_aktivitas') === 0);
    record(
        'rollback mutasi: stok ukuran kembali 0',
        (float)$db->query("SELECT COALESCE((SELECT MAX(stok) FROM ukuran), 0)")->fetchColumn() === 0.0
    );
    smoke_check_fts_sync($db, 'rollback mutasi: FTS balik');
}

function smoke_test_verifikasi(PDO $db): void
{
    $db->beginTransaction();
    $ids = smoke_insert_sample_chain($db);

    $db->prepare('INSERT INTO verifikasi_stok (id_user, id_ukuran, tanggal, stok_sistem, stok_fisik, selisih, keterangan, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([1, $ids['ukuran'], date('Y-m-d'), 5, 7, 2, 'QA verifikasi', 't', 't']);
    $db->exec("UPDATE ukuran SET stok=7 WHERE id={$ids['ukuran']}");

    record('FTS verifikasi/ukuran sinkron di dalam transaksi', count_rows($db, 'fts_verifikasi_stok') === count_rows($db, 'verifikasi_stok'));
    $db->rollBack();

    record('rollback verifikasi: verifikasi_stok kosong', count_rows($db, 'verifikasi_stok') === 0);
    record(
        'rollback verifikasi: stok ukuran kembali 0',
        (float)$db->query("SELECT COALESCE((SELECT MAX(stok) FROM ukuran), 0)")->fetchColumn() === 0.0
    );
    smoke_check_fts_sync($db, 'rollback verifikasi: FTS balik');
}

function smoke_test_edit_user(PDO $db): void
{
    $db->beginTransaction();
    $db->exec("UPDATE users SET nama='QA Rename', updated_at='t' WHERE username='admin'");

    $nama = (string)$db->query("SELECT nama FROM users WHERE username='admin'")->fetchColumn();
    record('nama user berubah selama transaksi (QA Rename)', $nama === 'QA Rename');
    record('FTS users sinkron selama transaksi', count_rows($db, 'fts_users') === count_rows($db, 'users'));

    $found = $db->query("SELECT id FROM fts_users WHERE fts_users MATCH 'Rename'")->fetchColumn();
    record('FTS users dapat di-MATCH (Rename -> id 1)', (int)$found === 1);

    $db->rollBack();

    $nama = (string)$db->query("SELECT nama FROM users WHERE username='admin'")->fetchColumn();
    record('rollback edit user: nama kembali ke Administrator', $nama === 'Administrator');
    record('FTS users balik setelah rollback', count_rows($db, 'fts_users') === count_rows($db, 'users'));
}

function smoke_test_final(PDO $db): void
{
    record(
        'integrity_check akhir = ok',
        strtolower((string)$db->query('PRAGMA integrity_check')->fetchColumn()) === 'ok'
    );
    record(
        'foreign_key_check akhir kosong',
        count($db->query('PRAGMA foreign_key_check')->fetchAll()) === 0
    );
    smoke_check_fts_sync($db, 'FTS sinkron akhir (semua tes di-rollback)');
}

// ============================================================================
// Main
// ============================================================================

$tmpDir = sys_get_temp_dir() . '/invennative_smoke_' . bin2hex(random_bytes(4));
if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
    fwrite(STDERR, "SMOKE: gagal membuat direktori temp {$tmpDir}\n");
    exit(1);
}
$dbFile = $tmpDir . '/smoke.sqlite';

echo "== Smoke Test invennative ==\n";

try {
    $db = smoke_make_pdo($dbFile);
    $db->exec(file_get_contents(SMOKE_APP_ROOT . '/database/schema.sql'));
    $db->exec(file_get_contents(SMOKE_APP_ROOT . '/database/seed.sql'));
    migrations_run($db);

    smoke_test_structure($db);
    smoke_check_fts_sync($db, 'FTS sinkron awal (seed)');
    smoke_test_ukuran($db);
    smoke_test_mutasi($db);
    smoke_test_verifikasi($db);
    smoke_test_edit_user($db);
    smoke_test_final($db);

    echo "\nHasil: {$passCount} PASS, {$failCount} FAIL\n";

    unset($db);
    smoke_cleanup($tmpDir);

    exit($failCount === 0 ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'SMOKE ERROR: ' . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    try {
        unset($db);
    } catch (Throwable $ignored) {
    }
    smoke_cleanup($tmpDir);
    exit(1);
}