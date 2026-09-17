-- ============================================================================
-- 2026_09_17_000000_add_indexes.sql
-- Menambahkan index untuk kolom yang sering dipakai di WHERE / ORDER BY / JOIN
-- tanpa index, sehingga menghilangkan full table scan pada database/inventaris.sqlite.
-- Tidak mengubah struktur tabel dan tidak menghapus index yang sudah ada.
-- Reversible: jalankan 2026_09_17_000000_add_indexes_down.sql untuk rollback.
-- ============================================================================

PRAGMA foreign_keys = ON;

BEGIN;

-- ===== A. FK reconciliation (index yang dideklarasi database/schema.sql =====
--        tapi belum ada di database/inventaris.sqlite) =========================

-- Join produk <-> merek, dipakai di JOIN list produk/ukuran (pages/produk.php:209)
CREATE INDEX IF NOT EXISTS idx_merek_id_produk ON merek(id_produk);

-- Join merek <-> ukuran, dipakai di JOIN list mutasi/verifikasi/stok
CREATE INDEX IF NOT EXISTS idx_ukuran_id_merek ON ukuran(id_merek);

-- Join ukuran <-> mutasi_stok, dipakai di JOIN list mutasi (pages/mutasi-stok.php:52)
CREATE INDEX IF NOT EXISTS idx_mutasi_stok_id_ukuran ON mutasi_stok(id_ukuran);

-- Join ukuran <-> verifikasi_stok (pages/verifikasi-stok.php:52)
CREATE INDEX IF NOT EXISTS idx_verifikasi_stok_id_ukuran ON verifikasi_stok(id_ukuran);

-- Join users <-> verifikasi_stok (pages/verifikasi-stok.php:52)
CREATE INDEX IF NOT EXISTS idx_verifikasi_stok_id_user ON verifikasi_stok(id_user);

-- ===== B. users ===============================================================

-- Login: WHERE username = ? AND deleted_at IS NULL (actions/auth.php:28).
-- Composite (username, deleted_at) agar filter login seluruhnya lewat index.
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username, deleted_at);

-- Pencarian & ORDER BY nama (pages/manajemen-user.php:14,18,39)
CREATE INDEX IF NOT EXISTS idx_users_nama ON users(nama);

-- ===== C. produk ==============================================================

-- ORDER BY nama + pencarian LIKE 'prefix%' + dropdown equality
-- (pages/produk.php, pages/verifikasi-stok-input.php, templates/components/...)
CREATE INDEX IF NOT EXISTS idx_produk_nama ON produk(nama);

-- ===== D. merek ===============================================================

-- ORDER BY merek + LIKE 'prefix%' (pages/produk.php:209)
CREATE INDEX IF NOT EXISTS idx_merek_merek ON merek(merek);

-- ===== E. ukuran ==============================================================

-- ORDER BY ukuran + LIKE 'prefix%' (pages/produk.php:306)
CREATE INDEX IF NOT EXISTS idx_ukuran_ukuran ON ukuran(ukuran);

-- Dashboard stok menipis/habis: WHERE stok <= 5 / stok = 0 (pages/dashboard.php:12,57)
CREATE INDEX IF NOT EXISTS idx_ukuran_stok ON ukuran(stok);

-- ===== F. mutasi_stok =========================================================

-- ORDER BY tanggal DESC, id DESC + filter range tanggal
-- (pages/mutasi-stok.php:58, pages/cetak-mutasi.php:50-83, pages/dashboard.php:29).
-- Tanggal disimpan ISO TEXT sehingga urutan DESC index == urutan kronologis.
CREATE INDEX IF NOT EXISTS idx_mutasi_stok_tanggal_id ON mutasi_stok(tanggal DESC, id DESC);

-- Filter / grouping berdasarkan jenis masuk/keluar (pages/dashboard.php)
CREATE INDEX IF NOT EXISTS idx_mutasi_stok_jenis ON mutasi_stok(jenis);

-- ===== G. verifikasi_stok =====================================================

-- ORDER BY tanggal DESC, id DESC + filter range tanggal
-- (pages/verifikasi-stok.php:60, cetak verifikasi)
CREATE INDEX IF NOT EXISTS idx_verifikasi_stok_tanggal_id ON verifikasi_stok(tanggal DESC, id DESC);

-- ===== H. log_aktivitas =======================================================

-- ORDER BY created_at DESC, id DESC (pages/log-aktivitas.php:33, pages/cetak-log.php:34)
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_created_id ON log_aktivitas(created_at DESC, id DESC);

-- Pencarian berdasarkan aksi (pages/log-aktivitas.php), melengkapi (modul, aksi)
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_aksi ON log_aktivitas(aksi);

-- Pencarian deskripsi LIKE 'prefix%' (pages/log-aktivitas.php)
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_deskripsi ON log_aktivitas(deskripsi);

-- Join log_aktivitas.<-> users.id (pages/log-aktivitas.php:32, pages/cetak-log.php:33)
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_id_user ON log_aktivitas(id_user);

-- ===== KETERBATASAN (sengaja TIDAK dibuat): =====
-- pages/cetak-log.php menyaring dengan date(l.created_at) — fungsi di kolom.
-- Expression index date()-nya TIDAK dipakai karena membuat DB tidak terbaca oleh
-- SQLite lawas (CLI 3.19.1: "malformed database schema - non-deterministic functions
-- prohibited in index expressions"). Solusi yang benar: rewrite kueri jadi range
-- predicate (created_at >= ? AND created_at < ?), yang bisa memakai
-- idx_log_aktivitas_created_id di atas. Perlu perubahan PHP, di luar scope migrasi ini.

-- Perbarui statistik agar query planner memanfaatkan index baru
ANALYZE;

COMMIT;