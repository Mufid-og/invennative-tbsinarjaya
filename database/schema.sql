PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    nama TEXT NOT NULL,
    password TEXT NOT NULL,
    remember_token TEXT,
    created_at TEXT,
    updated_at TEXT,
    deleted_at TEXT
);

CREATE TABLE IF NOT EXISTS produk (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL,
    created_at TEXT,
    updated_at TEXT,
    deleted_at TEXT
);

CREATE TABLE IF NOT EXISTS merek (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_produk INTEGER NOT NULL,
    merek TEXT NOT NULL,
    created_at TEXT,
    updated_at TEXT,
    deleted_at TEXT,
    FOREIGN KEY (id_produk) REFERENCES produk(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS ukuran (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_merek INTEGER NOT NULL,
    ukuran TEXT NOT NULL,
    stok REAL NOT NULL DEFAULT 0,
    harga_modal REAL NOT NULL DEFAULT 0,
    harga_jual REAL NOT NULL DEFAULT 0,
    created_at TEXT,
    updated_at TEXT,
    deleted_at TEXT,
    FOREIGN KEY (id_merek) REFERENCES merek(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS mutasi_stok (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_ukuran INTEGER NOT NULL,
    tanggal TEXT NOT NULL,
    jenis TEXT NOT NULL CHECK(jenis IN ('masuk','keluar')),
    qty REAL NOT NULL,
    harga_modal REAL NOT NULL DEFAULT 0,
    harga_jual REAL NOT NULL DEFAULT 0,
    keterangan TEXT,
    created_at TEXT,
    updated_at TEXT,
    deleted_at TEXT,
    FOREIGN KEY (id_ukuran) REFERENCES ukuran(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS verifikasi_stok (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_user INTEGER,
    id_ukuran INTEGER NOT NULL,
    tanggal TEXT NOT NULL,
    stok_sistem REAL NOT NULL,
    stok_fisik REAL NOT NULL,
    selisih REAL NOT NULL,
    keterangan TEXT,
    created_at TEXT,
    updated_at TEXT,
    deleted_at TEXT,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (id_ukuran) REFERENCES ukuran(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS log_aktivitas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_user INTEGER,
    aksi TEXT NOT NULL,
    modul TEXT,
    id_referensi INTEGER,
    deskripsi TEXT NOT NULL,
    data_perubahan TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_merek_id_produk ON merek(id_produk);
CREATE INDEX IF NOT EXISTS idx_ukuran_id_merek ON ukuran(id_merek);
CREATE INDEX IF NOT EXISTS idx_mutasi_stok_id_ukuran ON mutasi_stok(id_ukuran);
CREATE INDEX IF NOT EXISTS idx_verifikasi_stok_id_ukuran ON verifikasi_stok(id_ukuran);
CREATE INDEX IF NOT EXISTS idx_verifikasi_stok_id_user ON verifikasi_stok(id_user);
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_modul ON log_aktivitas(modul);
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_id_referensi ON log_aktivitas(id_referensi);

-- ===== Index kolom yang sering di-WHERE / ORDER BY / JOIN (tanpa index) =====

-- Login: WHERE username = ? AND deleted_at IS NULL (actions/auth.php:28, actions/user.php:33)
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username, deleted_at);

-- Pencarian & ORDER BY nama (pages/manajemen-user.php)
CREATE INDEX IF NOT EXISTS idx_users_nama ON users(nama);

-- ORDER BY nama + LIKE 'prefix%' (pages/produk.php, pages/verifikasi-stok-input.php)
CREATE INDEX IF NOT EXISTS idx_produk_nama ON produk(nama);

-- ORDER BY merek + LIKE 'prefix%' (pages/produk.php:209)
CREATE INDEX IF NOT EXISTS idx_merek_merek ON merek(merek);

-- ORDER BY ukuran + LIKE 'prefix%' (pages/produk.php:306)
CREATE INDEX IF NOT EXISTS idx_ukuran_ukuran ON ukuran(ukuran);

-- Dashboard stok menipis/habis: WHERE stok <= 5 (pages/dashboard.php:12,57)
CREATE INDEX IF NOT EXISTS idx_ukuran_stok ON ukuran(stok);

-- ORDER BY tanggal DESC, id DESC + range tanggal (pages/mutasi-stok.php, pages/cetak-mutasi.php)
CREATE INDEX IF NOT EXISTS idx_mutasi_stok_tanggal_id ON mutasi_stok(tanggal DESC, id DESC);

-- Filter/grup jenis (pages/dashboard.php)
CREATE INDEX IF NOT EXISTS idx_mutasi_stok_jenis ON mutasi_stok(jenis);

-- ORDER BY tanggal DESC, id DESC + range tanggal (pages/verifikasi-stok.php, cetak)
CREATE INDEX IF NOT EXISTS idx_verifikasi_stok_tanggal_id ON verifikasi_stok(tanggal DESC, id DESC);

-- ORDER BY created_at DESC, id DESC (pages/log-aktivitas.php, pages/cetak-log.php)
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_created_id ON log_aktivitas(created_at DESC, id DESC);

-- Pencarian aksi (pages/log-aktivitas.php)
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_aksi ON log_aktivitas(aksi);

-- Pencarian deskripsi LIKE 'prefix%' (pages/log-aktivitas.php)
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_deskripsi ON log_aktivitas(deskripsi);

-- Join log_aktivitas -> users (pages/log-aktivitas.php, pages/cetak-log.php)
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_id_user ON log_aktivitas(id_user);

-- KETERBATASAN: filter date(created_at) di pages/cetak-log.php adalah fungsi pada
-- kolom. Expression index date()-nya sengaja tdk dibuat (DB tak terbaca SQLite lawas).
-- Fix sejati: rewrite ke range predicate (created_at >= ? AND created_at < ?).
