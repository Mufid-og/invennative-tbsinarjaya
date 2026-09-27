<?php

declare(strict_types=1);

/**
 * env_check.php
 *
 * Pengecekan lingkungan runtime minimum aplikasi. Dipakai di halaman masuk
 * agar masalah konfigurasi server (versi PHP, ekstensi, SQLite FTS5)
 * langsung terlihat sebelum pengguna login — bukan berupa error acak
 * sesudah login.
 *
 * Return: array daftar masalah ['label' => string, 'detail' => string].
 * Kosong bila seluruh persyaratan terpenuhi.
 */
function env_requirements(): array
{
    $issues = [];

    // PHP >= 8.0 — kode memakai str_starts_with/str_contains dan tipe mixed.
    if (version_compare(PHP_VERSION, '8.0.0', '<')) {
        $issues[] = [
            'label' => 'Versi PHP terlalu lama',
            'detail' => 'Aplikasi membutuhkan PHP 8.0 atau lebih baru (terdeteksi: ' . PHP_VERSION . ').',
        ];
    }

    // Ekstensi wajib.
    if (!extension_loaded('pdo_sqlite')) {
        $issues[] = [
            'label' => 'Ekstensi pdo_sqlite tidak aktif',
            'detail' => 'Database membutuhkan PDO SQLite. Aktifkan extension=pdo_sqlite di php.ini.',
        ];
    }

    if (!extension_loaded('mbstring')) {
        $issues[] = [
            'label' => 'Ekstensi mbstring tidak aktif',
            'detail' => 'Pemrosesan teks (pencarian, validasi) membutuhkan mbstring. Aktifkan extension=mbstring di php.ini.',
        ];
    }

    if (!extension_loaded('dom')) {
        $issues[] = [
            'label' => 'Ekstensi dom tidak aktif',
            'detail' => 'Laporan PDF (Dompdf) membutuhkan ekstensi dom. Aktifkan extension=dom di php.ini.',
        ];
    }

    // Ekstensi bersyarat — aplikasi tetap berjalan, tapi fitur terkait mati.
    if (!class_exists('ZipArchive')) {
        $issues[] = [
            'label' => 'Ekstensi zip tidak aktif (fitur update aplikasi)',
            'detail' => 'Aktifkan extension=zip di php.ini agar update aplikasi dari file ZIP berfungsi. Fitur lain tetap berjalan.',
        ];
    }

    // SQLite harus dibangun dengan modul FTS5 (pencarian + trigger sinkron).
    $fts5Ok = false;
    try {
        $probe = new PDO('sqlite::memory:');
        $fts5Ok = (int)$probe->query("SELECT sqlite_compileoption_used('ENABLE_FTS5')")->fetchColumn() === 1;
    } catch (Throwable $e) {
        $fts5Ok = false;
    }
    if (!$fts5Ok) {
        $issues[] = [
            'label' => 'SQLite tidak mendukung FTS5',
            'detail' => 'Pencarian produk/merek/ukuran bergantung pada modul FTS5. Gunakan build PHP dengan dukungan SQLite FTS5.',
        ];
    }

    return $issues;
}