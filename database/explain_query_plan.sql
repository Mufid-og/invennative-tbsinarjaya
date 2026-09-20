-- ============================================================================
-- EXPLAIN QUERY PLAN — Perbandingan Performa Sebelum vs Sesudah
-- Project: invennative (PHP Native + SQLite)
-- Tanggal: 2026-09-20
--
-- Cara menjalankan:
--   sqlite3 database/inventaris.sqlite < database/explain_query_plan.sql
-- ============================================================================

PRAGMA foreign_keys = ON;

-- ============================================================================
-- 0. Setup: aktifkan FTS jika belum ada
-- ============================================================================
-- (jalankan migration terlebih dahulu)
-- .read database/migrations/2026_09_20_000001_add_fts5.sql

-- ============================================================================
-- BAGIAN 1: pages/mutasi-stok.php
-- Search: p.nama, me.merek, u.ukuran, m.keterangan
-- ============================================================================

SELECT '=== MUTASI STOK ===' AS label;

-- --- SEBELUM: LIKE '%cat%' → full table scan di semua JOIN ---
SELECT '--- SEBELUM (leading wildcard LIKE) ---' AS label;
EXPLAIN QUERY PLAN
SELECT m.id, p.nama, me.merek, u.ukuran, m.keterangan
FROM mutasi_stok m
JOIN ukuran u ON u.id = m.id_ukuran
JOIN merek me ON me.id = u.id_merek
JOIN produk p ON p.id = me.id_produk
WHERE m.deleted_at IS NULL
  AND (
    p.nama LIKE '%cat%'
    OR me.merek LIKE '%cat%'
    OR u.ukuran LIKE '%cat%'
    OR m.keterangan LIKE '%cat%'
  )
ORDER BY m.tanggal DESC, m.id DESC
LIMIT 15 OFFSET 0;
-- HASIL YANG DIHARAPKAN:
-- SCAN produk       → Full table scan (FTS tidak dipakai)
-- SCAN merek        → Full table scan
-- SCAN ukuran       → Full table scan
-- SEARCH mutasi_stok USING COVERING INDEX idx_mutasi_stok_tanggal_id
-- Karena '%cat%' di LIKE: SQLite TIDAK bisa pakai index untuk predicate ini.

-- --- SESUDAH MODE 1: FTS5 MATCH ---
SELECT '--- SESUDAH (FTS5 MATCH) ---' AS label;
EXPLAIN QUERY PLAN
SELECT m.id, p.nama, me.merek, u.ukuran, m.keterangan
FROM mutasi_stok m
JOIN ukuran u ON u.id = m.id_ukuran
JOIN merek me ON me.id = u.id_merek
JOIN produk p ON p.id = me.id_produk
WHERE m.deleted_at IS NULL
  AND (
    me.id_produk IN (SELECT rowid FROM fts_produk WHERE fts_produk MATCH '"cat"*')
    OR u.id_merek IN (SELECT rowid FROM fts_merek WHERE fts_merek MATCH '"cat"*')
    OR m.id_ukuran IN (SELECT rowid FROM fts_ukuran WHERE fts_ukuran MATCH '"cat"*')
    OR m.id IN (SELECT rowid FROM fts_mutasi_stok WHERE fts_mutasi_stok MATCH '"cat"*')
  )
ORDER BY m.tanggal DESC, m.id DESC
LIMIT 15 OFFSET 0;
-- HASIL YANG DIHARAPKAN:
-- SCAN fts_produk VIRTUAL TABLE INDEX      → FTS5 index scan (jauh lebih cepat)
-- SEARCH merek USING INTEGER PRIMARY KEY   → lookup by rowid
-- SCAN fts_merek VIRTUAL TABLE INDEX
-- SCAN fts_ukuran VIRTUAL TABLE INDEX
-- SCAN fts_mutasi_stok VIRTUAL TABLE INDEX
-- SEARCH mutasi_stok USING INDEX idx_mutasi_stok_tanggal_id

-- --- SESUDAH MODE 2: Prefix LIKE 'cat%' (fallback tanpa FTS5) ---
SELECT '--- SESUDAH (Prefix LIKE fallback) ---' AS label;
EXPLAIN QUERY PLAN
SELECT m.id, p.nama, me.merek, u.ukuran, m.keterangan
FROM mutasi_stok m
JOIN ukuran u ON u.id = m.id_ukuran
JOIN merek me ON me.id = u.id_merek
JOIN produk p ON p.id = me.id_produk
WHERE m.deleted_at IS NULL
  AND (
    p.nama LIKE 'cat%' ESCAPE '\'
    OR me.merek LIKE 'cat%' ESCAPE '\'
    OR u.ukuran LIKE 'cat%' ESCAPE '\'
    OR m.keterangan LIKE 'cat%' ESCAPE '\'
  )
ORDER BY m.tanggal DESC, m.id DESC
LIMIT 15 OFFSET 0;
-- HASIL YANG DIHARAPKAN:
-- SEARCH produk USING INDEX idx_produk_nama (nama>'cat' AND nama<'cau')
-- SEARCH merek  USING INDEX idx_merek_merek (merek>'cat' AND merek<'cau')
-- SEARCH ukuran USING INDEX idx_ukuran_ukuran (ukuran>'cat' AND ukuran<'cau')
-- Index BISA dipakai karena tidak ada leading '%'.

-- ============================================================================
-- BAGIAN 2: pages/produk.php
-- Search: produk.nama saja
-- ============================================================================

SELECT '=== PRODUK ===' AS label;

SELECT '--- SEBELUM (LIKE %nama%) ---' AS label;
EXPLAIN QUERY PLAN
SELECT p.*, COUNT(DISTINCT m.id) AS merek_count
FROM produk p
LEFT JOIN merek m ON m.id_produk = p.id AND m.deleted_at IS NULL
WHERE p.deleted_at IS NULL AND p.nama LIKE '%avian%'
GROUP BY p.id
ORDER BY p.nama ASC
LIMIT 15 OFFSET 0;

SELECT '--- SESUDAH (FTS5 MATCH) ---' AS label;
EXPLAIN QUERY PLAN
SELECT p.*, COUNT(DISTINCT m.id) AS merek_count
FROM produk p
LEFT JOIN merek m ON m.id_produk = p.id AND m.deleted_at IS NULL
WHERE p.deleted_at IS NULL
  AND p.id IN (SELECT rowid FROM fts_produk WHERE fts_produk MATCH '"avian"*')
GROUP BY p.id
ORDER BY p.nama ASC
LIMIT 15 OFFSET 0;

SELECT '--- SESUDAH (Prefix LIKE fallback) ---' AS label;
EXPLAIN QUERY PLAN
SELECT p.*, COUNT(DISTINCT m.id) AS merek_count
FROM produk p
LEFT JOIN merek m ON m.id_produk = p.id AND m.deleted_at IS NULL
WHERE p.deleted_at IS NULL AND p.nama LIKE 'avian%' ESCAPE '\'
GROUP BY p.id
ORDER BY p.nama ASC
LIMIT 15 OFFSET 0;

-- ============================================================================
-- BAGIAN 3: pages/log-aktivitas.php
-- Search: deskripsi, modul, aksi
-- ============================================================================

SELECT '=== LOG AKTIVITAS ===' AS label;

SELECT '--- SEBELUM (LIKE %update%) ---' AS label;
EXPLAIN QUERY PLAN
SELECT l.*, us.nama as user_nama
FROM log_aktivitas l
LEFT JOIN users us ON us.id = l.id_user
WHERE 1=1
  AND (l.deskripsi LIKE '%update%' OR l.modul LIKE '%update%' OR l.aksi LIKE '%update%')
ORDER BY l.created_at DESC, l.id DESC
LIMIT 15 OFFSET 0;

SELECT '--- SESUDAH (FTS5 MATCH) ---' AS label;
EXPLAIN QUERY PLAN
SELECT l.*, us.nama as user_nama
FROM log_aktivitas l
LEFT JOIN users us ON us.id = l.id_user
WHERE 1=1
  AND l.id IN (SELECT rowid FROM fts_log_aktivitas WHERE fts_log_aktivitas MATCH '"update"*')
ORDER BY l.created_at DESC, l.id DESC
LIMIT 15 OFFSET 0;

SELECT '--- SESUDAH (Prefix LIKE fallback) ---' AS label;
EXPLAIN QUERY PLAN
SELECT l.*, us.nama as user_nama
FROM log_aktivitas l
LEFT JOIN users us ON us.id = l.id_user
WHERE 1=1
  AND (
    l.deskripsi LIKE 'update%' ESCAPE '\'
    OR l.modul LIKE 'update%' ESCAPE '\'
    OR l.aksi LIKE 'update%' ESCAPE '\'
  )
ORDER BY l.created_at DESC, l.id DESC
LIMIT 15 OFFSET 0;

-- ============================================================================
-- BAGIAN 4: pages/manajemen-user.php
-- Search: nama, username
-- ============================================================================

SELECT '=== MANAJEMEN USER ===' AS label;

SELECT '--- SEBELUM (LIKE %budi%) ---' AS label;
EXPLAIN QUERY PLAN
SELECT * FROM users
WHERE deleted_at IS NULL
  AND (nama LIKE '%budi%' OR username LIKE '%budi%')
ORDER BY nama ASC
LIMIT 15 OFFSET 0;

SELECT '--- SESUDAH (FTS5 MATCH) ---' AS label;
EXPLAIN QUERY PLAN
SELECT * FROM users
WHERE deleted_at IS NULL
  AND id IN (SELECT rowid FROM fts_users WHERE fts_users MATCH '"budi"*')
ORDER BY nama ASC
LIMIT 15 OFFSET 0;

SELECT '--- SESUDAH (Prefix LIKE fallback) ---' AS label;
EXPLAIN QUERY PLAN
SELECT * FROM users
WHERE deleted_at IS NULL
  AND (nama LIKE 'budi%' ESCAPE '\' OR username LIKE 'budi%' ESCAPE '\')
ORDER BY nama ASC
LIMIT 15 OFFSET 0;

-- ============================================================================
-- RINGKASAN ANALISIS PERFORMA
-- ============================================================================
--
-- SEBELUM (LIKE '%kata%'):
-- ┌──────────────────────┬──────────────────────────────────────────────────┐
-- │ Halaman              │ Masalah                                          │
-- ├──────────────────────┼──────────────────────────────────────────────────┤
-- │ mutasi-stok.php      │ Full scan 4 tabel via JOIN (produk,merek,ukuran, │
-- │                      │ mutasi_stok). O(N) di setiap tabel.              │
-- │ verifikasi-stok.php  │ Sama, 4 tabel                                    │
-- │ log-aktivitas.php    │ Full scan log_aktivitas (bisa ribuan baris)      │
-- │ manajemen-user.php   │ Full scan users                                  │
-- │ produk.php (×3)      │ Full scan produk/merek/ukuran                    │
-- │ verifikasi-input.php │ Full scan produk/merek/ukuran (di wizard step)   │
-- └──────────────────────┴──────────────────────────────────────────────────┘
--
-- SESUDAH (FTS5 MATCH):
-- ┌──────────────────────┬──────────────────────────────────────────────────┐
-- │ Halaman              │ Perbaikan                                        │
-- ├──────────────────────┼──────────────────────────────────────────────────┤
-- │ Semua                │ FTS5 inverted index: O(log N + hasil) bukan O(N) │
-- │                      │ Mendukung: prefix, multi-kata, substring bebas   │
-- │                      │ Tidak perlu index B-tree tambahan                │
-- └──────────────────────┴──────────────────────────────────────────────────┘
--
-- SESUDAH (Prefix LIKE fallback — tanpa FTS5):
-- ┌──────────────────────┬──────────────────────────────────────────────────┐
-- │ Halaman              │ Perbaikan                                        │
-- ├──────────────────────┼──────────────────────────────────────────────────┤
-- │ produk.php           │ idx_produk_nama: range scan 'kata' <= x < 'kata{│
-- │                      │ +1}' — jauh lebih cepat dari full scan           │
-- │ log-aktivitas.php    │ idx_log_aktivitas_deskripsi: range scan          │
-- │ Semua tabel lain     │ Index yang sudah ada di migration add_indexes    │
-- │                      │ bisa dipakai karena tidak ada leading '%'        │
-- └──────────────────────┴──────────────────────────────────────────────────┘
--
-- KETERBATASAN PREFIX LIKE:
--   - Hanya cocok dengan awal kata (prefix), bukan tengah/akhir.
--   - Untuk "cat" → menemukan "cat", "catatan", "catu", tapi TIDAK "kucing".
--   - FTS5 lebih fleksibel: "cat" MATCH juga menemukan semua prefix token.
--
-- REKOMENDASI:
--   1. Jalankan migration FTS5 di database produksi.
--   2. Semua halaman sudah otomatis pakai FTS5 jika tersedia (via fts5_available()).
--   3. Fallback prefix LIKE aktif otomatis jika tabel fts_* tidak ada.
--   4. Untuk SQLite < 3.9.0: gunakan prefix LIKE (sudah jadi default fallback).
