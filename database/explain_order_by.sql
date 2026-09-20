-- ============================================================================
-- explain_order_by.sql
-- Verifikasi bahwa ORDER BY sekarang memakai index, bukan USE TEMP B-TREE.
--
-- Cara menjalankan (setelah migration dijalankan):
--   sqlite3 database/inventaris.sqlite < database/explain_order_by.sql
-- ============================================================================

PRAGMA foreign_keys = ON;

-- ============================================================================
-- 1. ORDER BY ukuran_numeric — mutasi-stok-input.php (masuk & keluar)
-- ============================================================================

SELECT '=== [1] ukuran ORDER BY ukuran_numeric ===' AS label;

SELECT '--- SEBELUM: CAST() di ORDER BY ---' AS label;
EXPLAIN QUERY PLAN
SELECT u.*
FROM ukuran u
WHERE u.id_merek = 1 AND u.deleted_at IS NULL
ORDER BY CAST(u.ukuran AS REAL) ASC
LIMIT 200;
-- HASIL LAMA:
--   SCAN ukuran
--   USE TEMP B-TREE FOR ORDER BY   ← filesort, tidak memakai index

SELECT '--- SESUDAH: ORDER BY ukuran_numeric ---' AS label;
EXPLAIN QUERY PLAN
SELECT u.*
FROM ukuran u
WHERE u.id_merek = 1 AND u.deleted_at IS NULL
ORDER BY u.ukuran_numeric ASC
LIMIT 200;
-- HASIL YANG DIHARAPKAN:
--   SEARCH ukuran USING INDEX idx_ukuran_numeric (id_merek=?)
--   ← tidak ada "USE TEMP B-TREE FOR ORDER BY"

SELECT '--- SESUDAH + filter stok > 0 (jenis keluar) ---' AS label;
EXPLAIN QUERY PLAN
SELECT u.*
FROM ukuran u
WHERE u.id_merek = 1 AND u.deleted_at IS NULL AND u.stok > 0
ORDER BY u.ukuran_numeric ASC
LIMIT 200;
-- HASIL YANG DIHARAPKAN:
--   SEARCH ukuran USING INDEX idx_ukuran_numeric (id_merek=?)

-- ============================================================================
-- 2. cetak-log.php — WHERE date() vs range predicate
-- ============================================================================

SELECT '=== [2] log_aktivitas WHERE + ORDER BY ===' AS label;

SELECT '--- SEBELUM: date() function di WHERE ---' AS label;
EXPLAIN QUERY PLAN
SELECT l.id
FROM log_aktivitas l
WHERE date(l.created_at) BETWEEN date('2026-09-01') AND date('2026-09-30')
ORDER BY l.created_at DESC;
-- HASIL LAMA:
--   SCAN log_aktivitas   ← full scan karena date() mencegah index
--   USE TEMP B-TREE FOR ORDER BY (mungkin)

SELECT '--- SESUDAH: range predicate langsung di kolom ---' AS label;
EXPLAIN QUERY PLAN
SELECT l.id
FROM log_aktivitas l
WHERE l.created_at >= '2026-09-01 00:00:00'
  AND l.created_at <  '2026-10-01 00:00:00'
ORDER BY l.created_at DESC;
-- HASIL YANG DIHARAPKAN:
--   SEARCH log_aktivitas USING INDEX idx_log_aktivitas_created_id
--     (created_at>? AND created_at<?)
--   ← index menangani filter DAN sort sekaligus

-- ============================================================================
-- 3. dashboard.php — ORDER BY stok ASC
-- ============================================================================

SELECT '=== [3] ukuran ORDER BY stok ASC ===' AS label;

EXPLAIN QUERY PLAN
SELECT u.*, m.merek, p.nama as produk_nama
FROM ukuran u
JOIN merek m ON m.id = u.id_merek AND m.deleted_at IS NULL
JOIN produk p ON p.id = m.id_produk AND p.deleted_at IS NULL
WHERE u.deleted_at IS NULL AND u.stok <= 5
ORDER BY u.stok ASC
LIMIT 15 OFFSET 0;
-- HASIL YANG DIHARAPKAN:
--   SEARCH ukuran USING INDEX idx_ukuran_stok (stok<?)

-- ============================================================================
-- 4. mutasi-stok.php / verifikasi-stok.php — ORDER BY tanggal DESC, id DESC
-- ============================================================================

SELECT '=== [4] mutasi_stok ORDER BY tanggal DESC, id DESC ===' AS label;

EXPLAIN QUERY PLAN
SELECT m.id, m.tanggal
FROM mutasi_stok m
WHERE m.deleted_at IS NULL
ORDER BY m.tanggal DESC, m.id DESC
LIMIT 15 OFFSET 0;
-- HASIL YANG DIHARAPKAN:
--   SCAN mutasi_stok USING INDEX idx_mutasi_stok_tanggal_id

SELECT '=== [5] verifikasi_stok ORDER BY tanggal DESC, id DESC ===' AS label;

EXPLAIN QUERY PLAN
SELECT v.id, v.tanggal
FROM verifikasi_stok v
WHERE v.deleted_at IS NULL
ORDER BY v.tanggal DESC, v.id DESC
LIMIT 15 OFFSET 0;
-- HASIL YANG DIHARAPKAN:
--   SCAN verifikasi_stok USING INDEX idx_verifikasi_stok_tanggal_id

SELECT '=== [6] log_aktivitas ORDER BY created_at DESC, id DESC ===' AS label;

EXPLAIN QUERY PLAN
SELECT l.id
FROM log_aktivitas l
WHERE 1=1
ORDER BY l.created_at DESC, l.id DESC
LIMIT 15 OFFSET 0;
-- HASIL YANG DIHARAPKAN:
--   SCAN log_aktivitas USING INDEX idx_log_aktivitas_created_id

-- ============================================================================
-- RINGKASAN
-- ============================================================================
-- Setelah migration dijalankan, semua query di atas seharusnya menunjukkan
-- "USING INDEX ..." dan TIDAK ada "USE TEMP B-TREE FOR ORDER BY".
--
-- Cara jalankan migration:
--   sqlite3 database/inventaris.sqlite \
--     < database/migrations/2026_09_17_000000_add_indexes.sql
--   sqlite3 database/inventaris.sqlite \
--     < database/migrations/2026_09_20_000002_add_ukuran_numeric.sql
-- ============================================================================
