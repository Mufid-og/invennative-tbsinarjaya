-- ============================================================================
-- 2026_09_20_000001_add_fts5.sql
-- Implementasi FTS5 (Full-Text Search) untuk tabel yang sering di-search.
--
-- Tabel FTS yang dibuat:
--   fts_produk       — mencari nama produk
--   fts_merek        — mencari nama merek (beserta id_produk untuk filter)
--   fts_ukuran       — mencari ukuran (beserta id_merek untuk filter)
--   fts_mutasi_stok  — mencari produk/merek/ukuran/keterangan di mutasi
--   fts_verifikasi_stok — mencari produk/merek/ukuran/keterangan di verifikasi
--   fts_log_aktivitas   — mencari deskripsi/modul/aksi di log
--   fts_users        — mencari nama/username
--
-- Setiap tabel FTS di-sync via TRIGGER INSERT/UPDATE/DELETE pada tabel asli.
-- Untuk populate awal, jalankan bagian "Populate awal" di bawah.
--
-- Rollback: jalankan 2026_09_20_000001_add_fts5_down.sql
-- ============================================================================

PRAGMA foreign_keys = ON;

-- SQLite FTS5 tersedia sejak SQLite 3.9.0 (2015-10-14).
-- Cek dengan: SELECT sqlite_version();  -- harus >= 3.9.0

BEGIN;

-- ============================================================================
-- 1. fts_produk
-- ============================================================================
CREATE VIRTUAL TABLE IF NOT EXISTS fts_produk USING fts5(
    id UNINDEXED,       -- rowid asli dari tabel produk
    nama,               -- kolom yang bisa di-MATCH
    content='produk',   -- content table mode: membaca dari tabel produk
    content_rowid='id'
);

-- Trigger: INSERT produk  → masuk fts_produk
CREATE TRIGGER IF NOT EXISTS trg_fts_produk_ins
AFTER INSERT ON produk BEGIN
    INSERT INTO fts_produk(rowid, id, nama)
    VALUES (new.id, new.id, new.nama);
END;

-- Trigger: UPDATE produk  → update fts_produk (delete+insert idiom untuk FTS5)
CREATE TRIGGER IF NOT EXISTS trg_fts_produk_upd
AFTER UPDATE ON produk BEGIN
    INSERT INTO fts_produk(fts_produk, rowid, id, nama)
    VALUES ('delete', old.id, old.id, old.nama);
    INSERT INTO fts_produk(rowid, id, nama)
    VALUES (new.id, new.id, new.nama);
END;

-- Trigger: DELETE produk  → hapus dari fts_produk
CREATE TRIGGER IF NOT EXISTS trg_fts_produk_del
AFTER DELETE ON produk BEGIN
    INSERT INTO fts_produk(fts_produk, rowid, id, nama)
    VALUES ('delete', old.id, old.id, old.nama);
END;

-- ============================================================================
-- 2. fts_merek
-- ============================================================================
CREATE VIRTUAL TABLE IF NOT EXISTS fts_merek USING fts5(
    id UNINDEXED,
    id_produk UNINDEXED,
    merek,
    content='merek',
    content_rowid='id'
);

CREATE TRIGGER IF NOT EXISTS trg_fts_merek_ins
AFTER INSERT ON merek BEGIN
    INSERT INTO fts_merek(rowid, id, id_produk, merek)
    VALUES (new.id, new.id, new.id_produk, new.merek);
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_merek_upd
AFTER UPDATE ON merek BEGIN
    INSERT INTO fts_merek(fts_merek, rowid, id, id_produk, merek)
    VALUES ('delete', old.id, old.id, old.id_produk, old.merek);
    INSERT INTO fts_merek(rowid, id, id_produk, merek)
    VALUES (new.id, new.id, new.id_produk, new.merek);
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_merek_del
AFTER DELETE ON merek BEGIN
    INSERT INTO fts_merek(fts_merek, rowid, id, id_produk, merek)
    VALUES ('delete', old.id, old.id, old.id_produk, old.merek);
END;

-- ============================================================================
-- 3. fts_ukuran
-- ============================================================================
CREATE VIRTUAL TABLE IF NOT EXISTS fts_ukuran USING fts5(
    id UNINDEXED,
    id_merek UNINDEXED,
    ukuran,
    content='ukuran',
    content_rowid='id'
);

CREATE TRIGGER IF NOT EXISTS trg_fts_ukuran_ins
AFTER INSERT ON ukuran BEGIN
    INSERT INTO fts_ukuran(rowid, id, id_merek, ukuran)
    VALUES (new.id, new.id, new.id_merek, new.ukuran);
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_ukuran_upd
AFTER UPDATE ON ukuran BEGIN
    INSERT INTO fts_ukuran(fts_ukuran, rowid, id, id_merek, ukuran)
    VALUES ('delete', old.id, old.id, old.id_merek, old.ukuran);
    INSERT INTO fts_ukuran(rowid, id, id_merek, ukuran)
    VALUES (new.id, new.id, new.id_merek, new.ukuran);
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_ukuran_del
AFTER DELETE ON ukuran BEGIN
    INSERT INTO fts_ukuran(fts_ukuran, rowid, id, id_merek, ukuran)
    VALUES ('delete', old.id, old.id, old.id_merek, old.ukuran);
END;

-- ============================================================================
-- 4. fts_mutasi_stok
-- Menyimpan denormalisasi produk_nama+merek+ukuran+keterangan agar satu
-- MATCH query bisa mencakup semua kolom sekaligus.
-- Karena denormalisasi, content table mode tidak dipakai (content='').
-- Sync dilakukan via trigger di tabel mutasi_stok.
-- ============================================================================
CREATE VIRTUAL TABLE IF NOT EXISTS fts_mutasi_stok USING fts5(
    id UNINDEXED,
    teks               -- gabungan: produk_nama || ' ' || merek || ' ' || ukuran || ' ' || keterangan
);

-- Catatan: trigger insert/update/delete di mutasi_stok membutuhkan JOIN ke
-- ukuran/merek/produk untuk mendapat nama. SQLite tidak mendukung JOIN di
-- trigger body, sehingga kita simpan hanya keterangan di trigger, dan populate
-- awal menggunakan INSERT ... SELECT dengan JOIN.
-- Untuk produk/merek/ukuran, FTS5 dipakai via fts_produk/fts_merek/fts_ukuran
-- sedangkan fts_mutasi_stok hanya untuk pencarian keterangan + id ukuran.
CREATE TRIGGER IF NOT EXISTS trg_fts_mutasi_ins
AFTER INSERT ON mutasi_stok BEGIN
    INSERT INTO fts_mutasi_stok(rowid, id, teks)
    VALUES (new.id, new.id, COALESCE(new.keterangan, ''));
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_mutasi_upd
AFTER UPDATE ON mutasi_stok BEGIN
    INSERT INTO fts_mutasi_stok(fts_mutasi_stok, rowid, id, teks)
    VALUES ('delete', old.id, old.id, COALESCE(old.keterangan, ''));
    INSERT INTO fts_mutasi_stok(rowid, id, teks)
    VALUES (new.id, new.id, COALESCE(new.keterangan, ''));
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_mutasi_del
AFTER DELETE ON mutasi_stok BEGIN
    INSERT INTO fts_mutasi_stok(fts_mutasi_stok, rowid, id, teks)
    VALUES ('delete', old.id, old.id, COALESCE(old.keterangan, ''));
END;

-- ============================================================================
-- 5. fts_verifikasi_stok
-- ============================================================================
CREATE VIRTUAL TABLE IF NOT EXISTS fts_verifikasi_stok USING fts5(
    id UNINDEXED,
    teks
);

CREATE TRIGGER IF NOT EXISTS trg_fts_verifikasi_ins
AFTER INSERT ON verifikasi_stok BEGIN
    INSERT INTO fts_verifikasi_stok(rowid, id, teks)
    VALUES (new.id, new.id, COALESCE(new.keterangan, ''));
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_verifikasi_upd
AFTER UPDATE ON verifikasi_stok BEGIN
    INSERT INTO fts_verifikasi_stok(fts_verifikasi_stok, rowid, id, teks)
    VALUES ('delete', old.id, old.id, COALESCE(old.keterangan, ''));
    INSERT INTO fts_verifikasi_stok(rowid, id, teks)
    VALUES (new.id, new.id, COALESCE(new.keterangan, ''));
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_verifikasi_del
AFTER DELETE ON verifikasi_stok BEGIN
    INSERT INTO fts_verifikasi_stok(fts_verifikasi_stok, rowid, id, teks)
    VALUES ('delete', old.id, old.id, COALESCE(old.keterangan, ''));
END;

-- ============================================================================
-- 6. fts_log_aktivitas
-- ============================================================================
CREATE VIRTUAL TABLE IF NOT EXISTS fts_log_aktivitas USING fts5(
    id UNINDEXED,
    teks               -- deskripsi || ' ' || COALESCE(modul,'') || ' ' || aksi
);

CREATE TRIGGER IF NOT EXISTS trg_fts_log_ins
AFTER INSERT ON log_aktivitas BEGIN
    INSERT INTO fts_log_aktivitas(rowid, id, teks)
    VALUES (new.id, new.id,
        new.deskripsi || ' ' || COALESCE(new.modul, '') || ' ' || new.aksi);
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_log_upd
AFTER UPDATE ON log_aktivitas BEGIN
    INSERT INTO fts_log_aktivitas(fts_log_aktivitas, rowid, id, teks)
    VALUES ('delete', old.id, old.id,
        old.deskripsi || ' ' || COALESCE(old.modul, '') || ' ' || old.aksi);
    INSERT INTO fts_log_aktivitas(rowid, id, teks)
    VALUES (new.id, new.id,
        new.deskripsi || ' ' || COALESCE(new.modul, '') || ' ' || new.aksi);
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_log_del
AFTER DELETE ON log_aktivitas BEGIN
    INSERT INTO fts_log_aktivitas(fts_log_aktivitas, rowid, id, teks)
    VALUES ('delete', old.id, old.id,
        old.deskripsi || ' ' || COALESCE(old.modul, '') || ' ' || old.aksi);
END;

-- ============================================================================
-- 7. fts_users
-- ============================================================================
CREATE VIRTUAL TABLE IF NOT EXISTS fts_users USING fts5(
    id UNINDEXED,
    teks               -- nama || ' ' || username
);

CREATE TRIGGER IF NOT EXISTS trg_fts_users_ins
AFTER INSERT ON users BEGIN
    INSERT INTO fts_users(rowid, id, teks)
    VALUES (new.id, new.id, new.nama || ' ' || new.username);
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_users_upd
AFTER UPDATE ON users BEGIN
    INSERT INTO fts_users(fts_users, rowid, id, teks)
    VALUES ('delete', old.id, old.id, old.nama || ' ' || old.username);
    INSERT INTO fts_users(rowid, id, teks)
    VALUES (new.id, new.id, new.nama || ' ' || new.username);
END;

CREATE TRIGGER IF NOT EXISTS trg_fts_users_del
AFTER DELETE ON users BEGIN
    INSERT INTO fts_users(fts_users, rowid, id, teks)
    VALUES ('delete', old.id, old.id, old.nama || ' ' || old.username);
END;

-- ============================================================================
-- Populate awal: masukkan data yang sudah ada ke dalam tabel FTS
-- ============================================================================

-- fts_produk
INSERT OR IGNORE INTO fts_produk(rowid, id, nama)
SELECT id, id, nama FROM produk;

-- fts_merek
INSERT OR IGNORE INTO fts_merek(rowid, id, id_produk, merek)
SELECT id, id, id_produk, merek FROM merek;

-- fts_ukuran
INSERT OR IGNORE INTO fts_ukuran(rowid, id, id_merek, ukuran)
SELECT id, id, id_merek, ukuran FROM ukuran;

-- fts_mutasi_stok (hanya keterangan, tanpa JOIN)
INSERT OR IGNORE INTO fts_mutasi_stok(rowid, id, teks)
SELECT id, id, COALESCE(keterangan, '') FROM mutasi_stok;

-- fts_verifikasi_stok
INSERT OR IGNORE INTO fts_verifikasi_stok(rowid, id, teks)
SELECT id, id, COALESCE(keterangan, '') FROM verifikasi_stok;

-- fts_log_aktivitas
INSERT OR IGNORE INTO fts_log_aktivitas(rowid, id, teks)
SELECT id, id,
       deskripsi || ' ' || COALESCE(modul, '') || ' ' || aksi
FROM log_aktivitas;

-- fts_users
INSERT OR IGNORE INTO fts_users(rowid, id, teks)
SELECT id, id, nama || ' ' || username FROM users;

-- Optimasi FTS index setelah bulk insert
INSERT INTO fts_produk(fts_produk) VALUES ('optimize');
INSERT INTO fts_merek(fts_merek) VALUES ('optimize');
INSERT INTO fts_ukuran(fts_ukuran) VALUES ('optimize');
INSERT INTO fts_mutasi_stok(fts_mutasi_stok) VALUES ('optimize');
INSERT INTO fts_verifikasi_stok(fts_verifikasi_stok) VALUES ('optimize');
INSERT INTO fts_log_aktivitas(fts_log_aktivitas) VALUES ('optimize');
INSERT INTO fts_users(fts_users) VALUES ('optimize');

COMMIT;
