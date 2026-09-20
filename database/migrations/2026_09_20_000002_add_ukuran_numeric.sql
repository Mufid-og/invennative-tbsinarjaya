-- ============================================================================
-- 2026_09_20_000002_add_ukuran_numeric.sql
--
-- MASALAH:
--   ORDER BY CAST(u.ukuran AS REAL) di mutasi-stok-input.php tidak bisa
--   memanfaatkan B-tree index karena CAST() adalah fungsi/expression.
--   SQLite harus sort semua baris di memori (USE TEMP B-TREE FOR ORDER BY).
--
-- SOLUSI:
--   Tambah kolom REAL `ukuran_numeric` yang menyimpan CAST(ukuran AS REAL).
--   Kolom ini di-maintain otomatis oleh trigger INSERT/UPDATE.
--   Index composite (id_merek, ukuran_numeric) memungkinkan:
--     WHERE id_merek = ? ORDER BY ukuran_numeric
--   diselesaikan seluruhnya lewat index — tanpa filesort.
--
-- PERILAKU CAST(ukuran AS REAL):
--   SQLite mengambil angka numerik terdepan dari string.
--   '10 kg'     -> 10.0
--   '2.5 liter' -> 2.5
--   '500ml'     -> 500.0
--   '1/2'       -> 1.0   (hanya angka sebelum /)
--   'PVC'       -> 0.0   (tidak ada angka)
--   Nilai ukuran_numeric identik dengan hasil CAST sebelumnya.
--
-- ROLLBACK: jalankan 2026_09_20_000002_add_ukuran_numeric_down.sql
-- ============================================================================

PRAGMA foreign_keys = ON;

BEGIN;

-- ============================================================================
-- 1. Tambah kolom ukuran_numeric
--    SQLite ALTER TABLE hanya mendukung ADD COLUMN — aman, non-destructive.
-- ============================================================================
ALTER TABLE ukuran ADD COLUMN ukuran_numeric REAL;

-- ============================================================================
-- 2. Populate semua baris lama
-- ============================================================================
UPDATE ukuran SET ukuran_numeric = CAST(ukuran AS REAL);

-- ============================================================================
-- 3. Index composite: cover WHERE id_merek = ? ORDER BY ukuran_numeric ASC
--    Query planner bisa gunakan ini untuk filter + sort sekaligus tanpa filesort.
-- ============================================================================
CREATE INDEX IF NOT EXISTS idx_ukuran_numeric
    ON ukuran(id_merek, ukuran_numeric ASC);

-- ============================================================================
-- 4. Trigger INSERT — isi ukuran_numeric saat baris baru masuk
-- ============================================================================
CREATE TRIGGER IF NOT EXISTS trg_ukuran_numeric_ins
AFTER INSERT ON ukuran
BEGIN
    UPDATE ukuran
    SET    ukuran_numeric = CAST(new.ukuran AS REAL)
    WHERE  id = new.id;
END;

-- ============================================================================
-- 5. Trigger UPDATE — sinkronisasi saat teks ukuran berubah
--    AFTER UPDATE OF ukuran: hanya aktif bila kolom ukuran yang diubah,
--    tidak perlu recalculate bila UPDATE hanya kolom stok/harga.
-- ============================================================================
CREATE TRIGGER IF NOT EXISTS trg_ukuran_numeric_upd
AFTER UPDATE OF ukuran ON ukuran
BEGIN
    UPDATE ukuran
    SET    ukuran_numeric = CAST(new.ukuran AS REAL)
    WHERE  id = new.id;
END;

-- ============================================================================
-- 6. Idempotent guard: pastikan semua index ORDER BY dari add_indexes.sql
--    sudah ada di database (IF NOT EXISTS = aman bila sudah ada).
-- ============================================================================

-- mutasi_stok: ORDER BY tanggal DESC, id DESC
CREATE INDEX IF NOT EXISTS idx_mutasi_stok_tanggal_id
    ON mutasi_stok(tanggal DESC, id DESC);

-- verifikasi_stok: ORDER BY tanggal DESC, id DESC
CREATE INDEX IF NOT EXISTS idx_verifikasi_stok_tanggal_id
    ON verifikasi_stok(tanggal DESC, id DESC);

-- log_aktivitas: ORDER BY created_at DESC, id DESC
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_created_id
    ON log_aktivitas(created_at DESC, id DESC);

-- ukuran: ORDER BY stok ASC (dashboard stok menipis)
CREATE INDEX IF NOT EXISTS idx_ukuran_stok
    ON ukuran(stok);

-- ============================================================================
-- 7. Perbarui statistik query planner
-- ============================================================================
ANALYZE;

COMMIT;
