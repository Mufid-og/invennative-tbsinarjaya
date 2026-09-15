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
