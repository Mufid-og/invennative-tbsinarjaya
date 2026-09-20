<?php

declare(strict_types=1);

/**
 * dashboard_cache.php
 *
 * File-based cache untuk data dashboard.
 *
 * Dua cache key:
 *   'dashboard_stats'  — totalProduk, totalUkuran, stokMenipis, stokHabis
 *   'dashboard_charts' — data chart mutasi 6 bulan + totalMutasi + tertinggi
 *
 * Invalidasi bersifat event-driven: cache dihapus tepat saat data berubah
 * (mutasi, verifikasi, produk/ukuran CRUD), bukan TTL, sehingga data
 * selalu akurat setelah operasi apapun.
 *
 * File cache disimpan di storage/cache/ dengan format JSON.
 * Direktori dibuat otomatis jika belum ada.
 */

define('DASHBOARD_CACHE_DIR', __DIR__ . '/../storage/cache');

/**
 * Baca cache dari file JSON.
 * Return null jika file tidak ada atau isinya tidak valid.
 */
function dashboard_cache_get(string $key): ?array
{
    $file = DASHBOARD_CACHE_DIR . '/' . $key . '.json';
    if (!file_exists($file)) {
        return null;
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * Tulis cache ke file JSON.
 * Gunakan LOCK_EX agar tidak terjadi race condition saat concurrent request.
 */
function dashboard_cache_set(string $key, array $data): void
{
    if (!is_dir(DASHBOARD_CACHE_DIR)) {
        mkdir(DASHBOARD_CACHE_DIR, 0755, true);
    }
    $file = DASHBOARD_CACHE_DIR . '/' . $key . '.json';
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Hapus satu atau lebih cache key.
 * Dipanggil setiap kali ada operasi CRUD yang mengubah data dashboard.
 *
 * Contoh:
 *   dashboard_cache_invalidate('dashboard_stats');
 *   dashboard_cache_invalidate('dashboard_stats', 'dashboard_charts');
 */
function dashboard_cache_invalidate(string ...$keys): void
{
    foreach ($keys as $key) {
        $file = DASHBOARD_CACHE_DIR . '/' . $key . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
