<?php

declare(strict_types=1);

/**
 * includes/migrations.php
 *
 * Auto-heal skema database SQLite.
 *
 * MASALAH YANG DISELESAIKAN:
 *   Restore file backup lama (aksi 'restore' di actions/setting.php) menimpa
 *   file DB SEBAGAIMANA ADANYA — tanpa menjalankan migration. Akibatnya DB
 *   hasil restore bisa kehilangan objek baru (FTS5 content mode, kolom
 *   ukuran_numeric, index, dst) sehingga INSERT/UPDATE memicu trigger rusak:
 *       SQLSTATE[HY000]: General error: 1 SQL logic error
 *
 * SOLUSI:
 *   Setiap koneksi DB membaca PRAGMA user_version, lalu menjalankan migrasi
 *   yang belum diterapkan. user_version ikut terbawa pada file backup, jadi
 *   setiap restore DB lama (apapun isinya) otomatis "di-heal" kembali ke
 *   struktur yang diharapkan aplikasi — tanpa perlu sesuaikan manual.
 *
 * URUTAN PENTING:
 *   v1 (ukuran_numeric) WAJIB dijalankan SEBELUM v2 (FTS5). Jika kolom
 *   ukuran_numeric ditambahkan (ALTER TABLE) SETELAH tabel FTS dibuat,
 *   FTS5 content mode menganggap index-nya tidak sinkron dan melempar
 *   "database disk image is malformed" pada operasi tulis berikutnya.
 *
 * PRINSIP:
 *   - Idempotent: DROP ... IF EXISTS / CREATE ... IF NOT EXISTS aman diulang.
 *   - Fail-soft: migrasi yang gagal dicatat ke error_log, aplikasi tetap
 *     berjalan (tidak memblokir seluruh request).
 *   - Gate: PRAGMA user_version (nilai integer) — tiap migrasi punya nomor.
 */

const MIGRATIONS_LATEST = 4;

/**
 * Jalankan seluruh migrasi yang belum diterapkan ke $db.
 * Dipanggil sekali per koneksi (lihat includes/db.php).
 */
function migrations_run(PDO $db): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $current = (int)$db->query('PRAGMA user_version')->fetchColumn();

    $migrations = migrations_list();

    foreach ($migrations as $version => $callback) {
        if ($version <= $current) {
            continue;
        }

        try {
            $db->beginTransaction();
            $callback($db);
            $db->exec('PRAGMA user_version = ' . (int)$version);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                try {
                    $db->rollBack();
                } catch (\Throwable $ignored) {
                }
            }
            error_log(sprintf(
                '[migrations] gagal menerapkan migrasi v%d: %s',
                $version,
                $e->getMessage()
            ));
            // Hentikan agar migrasi berikutnya tidak berjalan di atas
            // skema yang belum siap.
            break;
        }
    }
}

/**
 * Daftar migrasi terurut: [version => callable(PDO): void].
 * URUTAN MENGIKAT: v1 harus sebelum v2 (lihat docblock).
 */
function migrations_list(): array
{
    return [
        1 => 'migrations_1_ukuran_numeric',
        2 => 'migrations_2_fts5_rebuild',
        3 => 'migrations_3_cleanup_laravel_tables',
        4 => 'migrations_4_indexes',
    ];
}

/**
 * Migrasi 1 — kolom + trigger + index ukuran_numeric.
 *
 * Sesuai database/migrations/2026_09_20_000002_add_ukuran_numeric.sql.
 * Kolom ditambahkan hanya bila belum ada (SQLite tidak punya
 * ALTER TABLE ... ADD COLUMN IF NOT EXISTS, jadi dicek via PRAGMA
 * table_info).
 */
function migrations_1_ukuran_numeric(PDO $db): void
{
    $hasColumn = false;
    $cols = $db->query('PRAGMA table_info(ukuran)')->fetchAll();
    foreach ($cols as $row) {
        if (($row['name'] ?? '') === 'ukuran_numeric') {
            $hasColumn = true;
            break;
        }
    }

    if (!$hasColumn) {
        $db->exec('ALTER TABLE ukuran ADD COLUMN ukuran_numeric REAL');
    }

    // Backfill baris yang belum punya nilai (aman: hanya mengisi NULL).
    $db->exec('UPDATE ukuran SET ukuran_numeric = CAST(ukuran AS REAL) WHERE ukuran_numeric IS NULL');

    $db->exec(
        'CREATE INDEX IF NOT EXISTS idx_ukuran_numeric
         ON ukuran(id_merek, ukuran_numeric ASC)'
    );

    $db->exec(
        'CREATE TRIGGER IF NOT EXISTS trg_ukuran_numeric_ins
         AFTER INSERT ON ukuran BEGIN
            UPDATE ukuran SET ukuran_numeric = CAST(new.ukuran AS REAL) WHERE id = new.id;
         END'
    );
    $db->exec(
        'CREATE TRIGGER IF NOT EXISTS trg_ukuran_numeric_upd
         AFTER UPDATE OF ukuran ON ukuran BEGIN
            UPDATE ukuran SET ukuran_numeric = CAST(new.ukuran AS REAL) WHERE id = new.id;
         END'
    );
}

/**
 * Migrasi 2 — rebuild LENGKAP tabel FTS5 (7 tabel).
 *
 * Membuat ulang SEMUA tabel FTS sesuai database/migrations/
 * 2026_09_20_000001_add_fts5.sql, dengan trigger yang idiomnya benar.
 * Menyembuhkan beberapa kondisi rusak sekaligus:
 *
 *   1. fts_produk/fts_merek/fts_ukuran pernah dibangun sbg FTS "plain"
 *      (tanpa content=) padahal content mode yang diharapkan. Trigger
 *      update 'delete' khusus FTS tidak valid di FTS plain →
 *      "SQL logic error" — inilah penyebab error simpan ukuran,
 *      mutasi, dan verifikasi stok.
 *   2. Isi index fts_produk tidak sinkron dengan tabel produk (desync
 *      rowid) sehingga hasil pencarian salah. Direbuild dari tabel asli.
 *   3. Tabel FTS plain (users/mutasi/verifikasi/log) memakai idiom
 *      'delete' yang sama tapi salah untuk FTS plain. Diganti idiom
 *      baku DELETE ... WHERE rowid = ?.
 *   4. DB tua yang tidak punya tabel FTS sama sekali ikut dibuatkan
 *      berikut populate awal + trigger sync.
 */
function migrations_2_fts5_rebuild(PDO $db): void
{
    $fts = ['produk', 'merek', 'ukuran', 'mutasi_stok', 'verifikasi_stok', 'log_aktivitas', 'users'];

    // Drop SEMUA trigger FTS lama terlebih dahulu. SQLite hanya auto-drop
    // trigger yang ATTACHED ke tabel yang di-drop, bukan trigger di tabel
    // lain yang body-nya menunjuk tabel FTS — jadi harus manual.
    foreach ($fts as $tbl) {
        foreach (['ins', 'upd', 'del'] as $kind) {
            $db->exec("DROP TRIGGER IF EXISTS trg_fts_{$tbl}_{$kind}");
        }
        // Drop tabel FTS + shadow table-nya (FTS5 men-drop shadow otomatis).
        $db->exec("DROP TABLE IF EXISTS fts_{$tbl}");
    }

    // Nama trigger plain di migrasi asli TIDAK memakai akhiran tabel penuh:
    //   trg_fts_mutasi_{ins,upd,del}        (bukan trg_fts_mutasi_stok_*)
    //   trg_fts_verifikasi_{ins,upd,del}    (bukan trg_fts_verifikasi_stok_*)
    //   trg_fts_log_{ins,upd,del}           (bukan trg_fts_log_aktivitas_*)
    foreach (['mutasi', 'verifikasi', 'log'] as $short) {
        foreach (['ins', 'upd', 'del'] as $kind) {
            $db->exec("DROP TRIGGER IF EXISTS trg_fts_{$short}_{$kind}");
        }
    }

    // ===== 1. Content mode: produk, merek, ukuran =====
    $db->exec(
        "CREATE VIRTUAL TABLE fts_produk USING fts5(
            id UNINDEXED,
            nama,
            content='produk',
            content_rowid='id'
        )"
    );
    $db->exec(
        "CREATE VIRTUAL TABLE fts_merek USING fts5(
            id UNINDEXED,
            id_produk UNINDEXED,
            merek,
            content='merek',
            content_rowid='id'
        )"
    );
    $db->exec(
        "CREATE VIRTUAL TABLE fts_ukuran USING fts5(
            id UNINDEXED,
            id_merek UNINDEXED,
            ukuran,
            content='ukuran',
            content_rowid='id'
        )"
    );

    // Populate index content mode dari tabel asli (mengoreksi desync).
    $db->exec("INSERT INTO fts_produk(fts_produk) VALUES('rebuild')");
    $db->exec("INSERT INTO fts_merek(fts_merek) VALUES('rebuild')");
    $db->exec("INSERT INTO fts_ukuran(fts_ukuran) VALUES('rebuild')");

    // ===== 2. Plain: users, mutasi_stok, verifikasi_stok, log_aktivitas =====
    $db->exec(
        "CREATE VIRTUAL TABLE fts_mutasi_stok USING fts5(
            id UNINDEXED,
            teks
        )"
    );
    $db->exec(
        "CREATE VIRTUAL TABLE fts_verifikasi_stok USING fts5(
            id UNINDEXED,
            teks
        )"
    );
    $db->exec(
        "CREATE VIRTUAL TABLE fts_log_aktivitas USING fts5(
            id UNINDEXED,
            teks
        )"
    );
    $db->exec(
        "CREATE VIRTUAL TABLE fts_users USING fts5(
            id UNINDEXED,
            teks
        )"
    );

    // Populate awal FTS plain dari tabel asli (table baru → isi kosong,
    // INSERT OR IGNORE aman untuk menghindari duplikat rowid).
    $db->exec('INSERT OR IGNORE INTO fts_mutasi_stok(rowid, id, teks) SELECT id, id, COALESCE(keterangan, "") FROM mutasi_stok');
    $db->exec('INSERT OR IGNORE INTO fts_verifikasi_stok(rowid, id, teks) SELECT id, id, COALESCE(keterangan, "") FROM verifikasi_stok');
    $db->exec('INSERT OR IGNORE INTO fts_log_aktivitas(rowid, id, teks) SELECT id, id, deskripsi || " " || COALESCE(modul, "") || " " || aksi FROM log_aktivitas');
    $db->exec('INSERT OR IGNORE INTO fts_users(rowid, id, teks) SELECT id, id, nama || " " || username FROM users');

    // ===== 3. Trigger — PRODUCT / MEREK / UKURAN (content mode) =====
    $db->exec(
        "CREATE TRIGGER trg_fts_produk_ins
         AFTER INSERT ON produk BEGIN
            INSERT INTO fts_produk(rowid, id, nama) VALUES (new.id, new.id, new.nama);
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_produk_upd
         AFTER UPDATE ON produk BEGIN
            INSERT INTO fts_produk(fts_produk, rowid, id, nama)
            VALUES ('delete', old.id, old.id, old.nama);
            INSERT INTO fts_produk(rowid, id, nama) VALUES (new.id, new.id, new.nama);
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_produk_del
         AFTER DELETE ON produk BEGIN
            INSERT INTO fts_produk(fts_produk, rowid, id, nama)
            VALUES ('delete', old.id, old.id, old.nama);
         END"
    );

    $db->exec(
        "CREATE TRIGGER trg_fts_merek_ins
         AFTER INSERT ON merek BEGIN
            INSERT INTO fts_merek(rowid, id, id_produk, merek)
            VALUES (new.id, new.id, new.id_produk, new.merek);
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_merek_upd
         AFTER UPDATE ON merek BEGIN
            INSERT INTO fts_merek(fts_merek, rowid, id, id_produk, merek)
            VALUES ('delete', old.id, old.id, old.id_produk, old.merek);
            INSERT INTO fts_merek(rowid, id, id_produk, merek)
            VALUES (new.id, new.id, new.id_produk, new.merek);
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_merek_del
         AFTER DELETE ON merek BEGIN
            INSERT INTO fts_merek(fts_merek, rowid, id, id_produk, merek)
            VALUES ('delete', old.id, old.id, old.id_produk, old.merek);
         END"
    );

    $db->exec(
        "CREATE TRIGGER trg_fts_ukuran_ins
         AFTER INSERT ON ukuran BEGIN
            INSERT INTO fts_ukuran(rowid, id, id_merek, ukuran)
            VALUES (new.id, new.id, new.id_merek, new.ukuran);
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_ukuran_upd
         AFTER UPDATE ON ukuran BEGIN
            INSERT INTO fts_ukuran(fts_ukuran, rowid, id, id_merek, ukuran)
            VALUES ('delete', old.id, old.id, old.id_merek, old.ukuran);
            INSERT INTO fts_ukuran(rowid, id, id_merek, ukuran)
            VALUES (new.id, new.id, new.id_merek, new.ukuran);
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_ukuran_del
         AFTER DELETE ON ukuran BEGIN
            INSERT INTO fts_ukuran(fts_ukuran, rowid, id, id_merek, ukuran)
            VALUES ('delete', old.id, old.id, old.id_merek, old.ukuran);
         END"
    );

    // ===== 4. Trigger — USERS / MUTASI / VERIFIKASI / LOG (plain) =====
    // FTS plain TIDAK mengenal perintah khusus 'delete'; gunakan idiom baku
    // DELETE ... WHERE rowid = ? + re-INSERT.
    $db->exec(
        "CREATE TRIGGER trg_fts_users_ins
         AFTER INSERT ON users BEGIN
            INSERT INTO fts_users(rowid, id, teks)
            VALUES (new.id, new.id, new.nama || ' ' || new.username);
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_users_upd
         AFTER UPDATE ON users BEGIN
            DELETE FROM fts_users WHERE rowid = old.id;
            INSERT INTO fts_users(rowid, id, teks)
            VALUES (new.id, new.id, new.nama || ' ' || new.username);
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_users_del
         AFTER DELETE ON users BEGIN
            DELETE FROM fts_users WHERE rowid = old.id;
         END"
    );

    $db->exec(
        "CREATE TRIGGER trg_fts_mutasi_ins
         AFTER INSERT ON mutasi_stok BEGIN
            INSERT INTO fts_mutasi_stok(rowid, id, teks)
            VALUES (new.id, new.id, COALESCE(new.keterangan, ''));
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_mutasi_upd
         AFTER UPDATE ON mutasi_stok BEGIN
            DELETE FROM fts_mutasi_stok WHERE rowid = old.id;
            INSERT INTO fts_mutasi_stok(rowid, id, teks)
            VALUES (new.id, new.id, COALESCE(new.keterangan, ''));
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_mutasi_del
         AFTER DELETE ON mutasi_stok BEGIN
            DELETE FROM fts_mutasi_stok WHERE rowid = old.id;
         END"
    );

    $db->exec(
        "CREATE TRIGGER trg_fts_verifikasi_ins
         AFTER INSERT ON verifikasi_stok BEGIN
            INSERT INTO fts_verifikasi_stok(rowid, id, teks)
            VALUES (new.id, new.id, COALESCE(new.keterangan, ''));
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_verifikasi_upd
         AFTER UPDATE ON verifikasi_stok BEGIN
            DELETE FROM fts_verifikasi_stok WHERE rowid = old.id;
            INSERT INTO fts_verifikasi_stok(rowid, id, teks)
            VALUES (new.id, new.id, COALESCE(new.keterangan, ''));
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_verifikasi_del
         AFTER DELETE ON verifikasi_stok BEGIN
            DELETE FROM fts_verifikasi_stok WHERE rowid = old.id;
         END"
    );

    $db->exec(
        "CREATE TRIGGER trg_fts_log_ins
         AFTER INSERT ON log_aktivitas BEGIN
            INSERT INTO fts_log_aktivitas(rowid, id, teks)
            VALUES (new.id, new.id,
                new.deskripsi || ' ' || COALESCE(new.modul, '') || ' ' || new.aksi);
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_log_upd
         AFTER UPDATE ON log_aktivitas BEGIN
            DELETE FROM fts_log_aktivitas WHERE rowid = old.id;
            INSERT INTO fts_log_aktivitas(rowid, id, teks)
            VALUES (new.id, new.id,
                new.deskripsi || ' ' || COALESCE(new.modul, '') || ' ' || new.aksi);
         END"
    );
    $db->exec(
        "CREATE TRIGGER trg_fts_log_del
         AFTER DELETE ON log_aktivitas BEGIN
            DELETE FROM fts_log_aktivitas WHERE rowid = old.id;
         END"
    );

    // Optimasi index FTS setelah rebuild/populate massal.
    foreach ($fts as $tbl) {
        $db->exec("INSERT INTO fts_{$tbl}(fts_{$tbl}) VALUES('optimize')");
    }
}

/**
 * Migrasi 3 — bersihkan sisa tabel Laravel.
 *
 * DB backup era lama masih membawa tabel sisa framework (sessions, jobs,
 * cache, ...) yang tidak dipakai aplikasi ini. Dibersihkan otomatis tiap
 * restore supaya tidak menumpuk kembali.
 */
function migrations_3_cleanup_laravel_tables(PDO $db): void
{
    $tables = [
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        'migrations',
        '_test', // tabel uji FTS sisa eksperimen
    ];

    foreach ($tables as $table) {
        $db->exec("DROP TABLE IF EXISTS {$table}");
    }
}

/**
 * Migrasi 4 — index kinerja (cermin 2026_09_17_000000_add_indexes.sql).
 *
 * Semua statement CREATE INDEX IF NOT EXISTS — idempotent dan aman diulang
 * pada DB apa pun. INDEX hanya dibangun bila belum ada, jadi tidak
 * mengganggu file DB yang sudah terindeks.
 */
function migrations_4_indexes(PDO $db): void
{
    $indexes = [
        // A. FK reconciliation + dua index tambahan dari schema.sql
        'CREATE INDEX IF NOT EXISTS idx_merek_id_produk ON merek(id_produk)',
        'CREATE INDEX IF NOT EXISTS idx_ukuran_id_merek ON ukuran(id_merek)',
        'CREATE INDEX IF NOT EXISTS idx_mutasi_stok_id_ukuran ON mutasi_stok(id_ukuran)',
        'CREATE INDEX IF NOT EXISTS idx_verifikasi_stok_id_ukuran ON verifikasi_stok(id_ukuran)',
        'CREATE INDEX IF NOT EXISTS idx_verifikasi_stok_id_user ON verifikasi_stok(id_user)',
        'CREATE INDEX IF NOT EXISTS idx_log_aktivitas_modul ON log_aktivitas(modul)',
        'CREATE INDEX IF NOT EXISTS idx_log_aktivitas_id_referensi ON log_aktivitas(id_referensi)',
        // B. users
        'CREATE INDEX IF NOT EXISTS idx_users_username ON users(username, deleted_at)',
        'CREATE INDEX IF NOT EXISTS idx_users_nama ON users(nama)',
        // C. produk
        'CREATE INDEX IF NOT EXISTS idx_produk_nama ON produk(nama)',
        // D. merek
        'CREATE INDEX IF NOT EXISTS idx_merek_merek ON merek(merek)',
        // E. ukuran
        'CREATE INDEX IF NOT EXISTS idx_ukuran_ukuran ON ukuran(ukuran)',
        'CREATE INDEX IF NOT EXISTS idx_ukuran_stok ON ukuran(stok)',
        // F. mutasi_stok
        'CREATE INDEX IF NOT EXISTS idx_mutasi_stok_tanggal_id ON mutasi_stok(tanggal DESC, id DESC)',
        'CREATE INDEX IF NOT EXISTS idx_mutasi_stok_jenis ON mutasi_stok(jenis)',
        // G. verifikasi_stok
        'CREATE INDEX IF NOT EXISTS idx_verifikasi_stok_tanggal_id ON verifikasi_stok(tanggal DESC, id DESC)',
        // H. log_aktivitas
        'CREATE INDEX IF NOT EXISTS idx_log_aktivitas_created_id ON log_aktivitas(created_at DESC, id DESC)',
        'CREATE INDEX IF NOT EXISTS idx_log_aktivitas_aksi ON log_aktivitas(aksi)',
        'CREATE INDEX IF NOT EXISTS idx_log_aktivitas_deskripsi ON log_aktivitas(deskripsi)',
        'CREATE INDEX IF NOT EXISTS idx_log_aktivitas_id_user ON log_aktivitas(id_user)',
    ];

    foreach ($indexes as $sql) {
        $db->exec($sql);
    }

    // Perbarui statistik agar query planner memanfaatkan index baru.
    $db->exec('ANALYZE');
}