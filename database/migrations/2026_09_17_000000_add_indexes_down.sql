-- ============================================================================
-- 2026_09_17_000000_add_indexes_down.sql
-- Rollback migration 2026_09_17_000000_add_indexes.sql.
-- Menghapus index yang ditambahkan oleh migration tersebut (urutan terbalik).
-- ============================================================================

BEGIN;

-- H. log_aktivitas
DROP INDEX IF EXISTS idx_log_aktivitas_id_user;
DROP INDEX IF EXISTS idx_log_aktivitas_deskripsi;
DROP INDEX IF EXISTS idx_log_aktivitas_aksi;
DROP INDEX IF EXISTS idx_log_aktivitas_created_id;

-- G. verifikasi_stok
DROP INDEX IF EXISTS idx_verifikasi_stok_tanggal_id;
DROP INDEX IF EXISTS idx_verifikasi_stok_id_user;
DROP INDEX IF EXISTS idx_verifikasi_stok_id_ukuran;

-- F. mutasi_stok
DROP INDEX IF EXISTS idx_mutasi_stok_jenis;
DROP INDEX IF EXISTS idx_mutasi_stok_tanggal_id;
DROP INDEX IF EXISTS idx_mutasi_stok_id_ukuran;

-- E. ukuran
DROP INDEX IF EXISTS idx_ukuran_stok;
DROP INDEX IF EXISTS idx_ukuran_ukuran;
DROP INDEX IF EXISTS idx_ukuran_id_merek;

-- D. merek
DROP INDEX IF EXISTS idx_merek_merek;
DROP INDEX IF EXISTS idx_merek_id_produk;

-- C. produk
DROP INDEX IF EXISTS idx_produk_nama;

-- B. users
DROP INDEX IF EXISTS idx_users_nama;
DROP INDEX IF EXISTS idx_users_username;

COMMIT;

-- ============================================================================
-- Catatan:
-- 5 index FK (idx_merek_id_produk, idx_ukuran_id_merek, idx_mutasi_stok_id_ukuran,
-- idx_verifikasi_stok_id_ukuran, idx_verifikasi_stok_id_user) yang dihapus di sini
-- tetap menjadi bagian dari database/schema.sql dan akan dibuat lagi otomatis
-- pada pembuatan database baru sesuai schema.
-- ============================================================================