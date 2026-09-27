# Inventaris Toko Bangunan (invennative)

Aplikasi inventaris toko bangunan: manajemen produk/merek/ukuran, mutasi &
verifikasi stok, pengguna, log aktivitas, laporan PDF, backup/restore database,
dan update aplikasi dari file ZIP.

## Kebutuhan Server

### Versi PHP
- **PHP 8.0 atau lebih baru** (dianjurkan 8.1+, diuji pada 8.3). Kode memakai
  fungsi bawaan PHP 8 seperti `str_starts_with`, `str_contains`, dan tipe `mixed`.

### Ekstensi PHP yang wajib aktif
| Ekstensi   | Fungsi                                                        | php.ini                       |
|------------|---------------------------------------------------------------|-------------------------------|
| `pdo_sqlite` | Semua koneksi database (PDO + SQLite)                        | `extension=pdo_sqlite`        |
| `mbstring` | Pemrosesan teks multibyte (pencarian, validasi)               | `extension=mbstring`          |
| `dom`      | Membuat laporan PDF (Dompdf)                                  | `extension=dom`               |
| `json`     | Penyimpanan cache & data (bawaan PHP 8, tidak perlu diaktifkan) | —                             |

### Ekstensi bersyarat (fitur terkait, bukan aplikasi secara umum)
- **`zip`** (`ZipArchive`) — wajib hanya untuk fitur **Update Aplikasi** dari ZIP
  di halaman Pengaturan. Tanpa ekstensi ini, semua fitur lain tetap berjalan.

### Dukungan SQLite
- `pdo_sqlite` harus dibangun bersama **modul FTS5** agar pencarian
  produk/merek/ukuran cepat dan trigger sinkronisasi berfungsi. Cara cek cepat:
  > `SELECT sqlite_compileoption_used('ENABLE_FTS5');` (harus bernilai 1)

### Cek otomatis di halaman masuk
Halaman masuk menampilkan banner peringatan bila server tidak memenuhi syarat
di atas (versi PHP, ekstensi, FTS5). Jika banner muncul, perbaiki konfigurasi
server sesuai tabel, bukan melapor sebagai bug aplikasi.

## Menjalankan (pengembangan lokal)

    php -S localhost:8080 router.php

Database dibuat otomatis saat pertama kali aplikasi dibuka
(dari `database/schema.sql` + `database/seed.sql`). Akun awal: `admin` /
`admin123` (disarankan segera diganti).

## Menjalankan smoke test sebelum build

    php tools/smoke_test.php

Membuka salinan database sementara, menjalankan migrasi, lalu menguji
rollback-transaksi untuk CRUD ukuran, mutasi, verifikasi, edit user,
integritas, dan sinkronisasi FTS. Keluar dengan kode 0 bila semua lulus —
jadikan ini gate sebelum membuat paket distribusi ZIP.

## Struktur utama

    pages/            Halaman (route GET)
    actions/          Penangan aksi (route POST)
    includes/         db, auth, helpers, migrasi, cache dashboard, pencarian FTS
    api/              Endpoint ajax (produk-cascade)
    templates/        Layout admin & halaman masuk
    database/         schema.sql, seed.sql, migrasi .sql + file SQLite
    storage/cache/    Cache dashboard (file, dibangun ulang otomatis)
    config/           database.php, version.php