-- ============================================================================
-- 2026_09_20_000002_add_ukuran_numeric_down.sql
-- Rollback: hapus trigger + index kolom ukuran_numeric.
--
-- CATATAN: SQLite < 3.35.0 tidak mendukung ALTER TABLE DROP COLUMN.
-- Kolom ukuran_numeric dibiarkan di schema (tidak ada data yang hilang).
-- Trigger dihapus sehingga kolom berhenti di-maintain, namun tidak membuat
-- crash — kolom hanya akan memiliki nilai lama yang stale.
--
-- Untuk benar-benar menghapus kolom di SQLite >= 3.35.0:
--   ALTER TABLE ukuran DROP COLUMN ukuran_numeric;
-- ============================================================================

BEGIN;

DROP TRIGGER IF EXISTS trg_ukuran_numeric_upd;
DROP TRIGGER IF EXISTS trg_ukuran_numeric_ins;

DROP INDEX IF EXISTS idx_ukuran_numeric;

COMMIT;
