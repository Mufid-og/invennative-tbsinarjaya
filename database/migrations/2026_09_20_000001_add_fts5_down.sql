-- ============================================================================
-- 2026_09_20_000001_add_fts5_down.sql
-- Rollback: hapus semua virtual table FTS5 dan trigger sinkronisasinya.
-- ============================================================================

BEGIN;

-- Hapus triggers sinkronisasi
DROP TRIGGER IF EXISTS trg_fts_produk_ins;
DROP TRIGGER IF EXISTS trg_fts_produk_upd;
DROP TRIGGER IF EXISTS trg_fts_produk_del;

DROP TRIGGER IF EXISTS trg_fts_merek_ins;
DROP TRIGGER IF EXISTS trg_fts_merek_upd;
DROP TRIGGER IF EXISTS trg_fts_merek_del;

DROP TRIGGER IF EXISTS trg_fts_ukuran_ins;
DROP TRIGGER IF EXISTS trg_fts_ukuran_upd;
DROP TRIGGER IF EXISTS trg_fts_ukuran_del;

DROP TRIGGER IF EXISTS trg_fts_mutasi_ins;
DROP TRIGGER IF EXISTS trg_fts_mutasi_upd;
DROP TRIGGER IF EXISTS trg_fts_mutasi_del;

DROP TRIGGER IF EXISTS trg_fts_verifikasi_ins;
DROP TRIGGER IF EXISTS trg_fts_verifikasi_upd;
DROP TRIGGER IF EXISTS trg_fts_verifikasi_del;

DROP TRIGGER IF EXISTS trg_fts_log_ins;
DROP TRIGGER IF EXISTS trg_fts_log_upd;
DROP TRIGGER IF EXISTS trg_fts_log_del;

DROP TRIGGER IF EXISTS trg_fts_users_ins;
DROP TRIGGER IF EXISTS trg_fts_users_upd;
DROP TRIGGER IF EXISTS trg_fts_users_del;

-- Hapus virtual tables FTS5
DROP TABLE IF EXISTS fts_produk;
DROP TABLE IF EXISTS fts_merek;
DROP TABLE IF EXISTS fts_ukuran;
DROP TABLE IF EXISTS fts_mutasi_stok;
DROP TABLE IF EXISTS fts_verifikasi_stok;
DROP TABLE IF EXISTS fts_log_aktivitas;
DROP TABLE IF EXISTS fts_users;

COMMIT;
