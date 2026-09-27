<?php

declare(strict_types=1);

/**
 * search_helper.php
 *
 * Abstraksi pencarian untuk SQLite di project invennative.
 *
 * STRATEGI (urutan prioritas):
 *  1. FTS5 MATCH  — jika tabel fts_* tersedia DAN $search >= 2 karakter.
 *                   Mendukung pencarian substring bebas (tengah/akhir kata)
 *                   dan kata "satuan menempel" seperti 1kg / m5: diperluas
 *                   menjadi (raw OR pecahan batas digit-huruf) sehingga
 *                   "1kg" juga cocok dengan data tersimpan "1 kg".
 *  2. Prefix LIKE — jika FTS5 tidak tersedia, pakai substring '%kata%'
 *                   termasuk varian "1 kg" hasil normalisasi batas digit-
 *                   huruf, agar "1kg" maupun "kg" tetap bisa menemukan
 *                   "1 kg".
 *
 * CATATAN FTS5 TOKENIZER:
 * SQLite FTS5 default tokenizer (unicode61) memecah token hanya di
 * non-alfanumerik: "1 kg" menjadi token [1, kg], sedangkan "1kg" menjadi
 * satu token [1kg]. Karena itu pencarian menempel ("1kg") perlu bentuk
 * terpecah ("1"+"kg") juga — dijembatani oleh split_alpha_num().
 *
 * CARA PAKAI:
 *
 * // Contoh: search di tabel mutasi_stok (multi-kolom JOIN)
 * [$whereSql, $whereParams, $mode] = fts_search_mutasi($search);
 * // $whereSql  → cuplikan WHERE yang siap di-AND-kan
 * // $whereParams → parameter untuk prepared statement
 * // $mode      → 'fts5'|'prefix'|'like' (untuk debug/logging)
 *
 * CATATAN FTS5 TOKENIZER:
 * SQLite FTS5 default tokenizer (unicode61) tidak memahami karakter khusus
 * seperti angka-satuan "5kg", "1ltr", dll. Untuk query MATCH, spasi dan
 * karakter non-alfanumerik di-escape sehingga query tetap aman.
 */

// --------------------------------------------------------------------------
// Fungsi utilitas internal
// --------------------------------------------------------------------------

/**
 * Periksa apakah FTS5 tersedia dengan mengecek keberadaan tabel virtual.
 * Hasil di-cache di static variable agar cek hanya dilakukan sekali per request.
 */
function fts5_available(string $tableName = 'fts_produk'): bool
{
    static $cache = [];
    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }
    try {
        $stmt = db()->query(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name=" .
            db()->quote($tableName) . " LIMIT 1"
        );
        $cache[$tableName] = (bool)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        $cache[$tableName] = false;
    }
    return $cache[$tableName];
}

/**
 * Escape string untuk dipakai dalam FTS5 MATCH query.
 * FTS5 menggunakan sintaks: MATCH '"kata lengkap"' untuk exact phrase,
 * atau MATCH 'kata*' untuk prefix.
 * Karakter yang perlu di-escape di dalam phrase: kutip ganda.
 */
function fts5_escape(string $term): string
{
    // Hapus karakter kontrol, ganti kutip ganda jadi spasi
    $term = preg_replace('/[\x00-\x1f"]+/', ' ', $term);
    $term = trim($term);
    return $term;
}

/**
 * Buat FTS5 MATCH query untuk pencarian kata-tunggal atau multi-kata.
 * Jika multi-kata, setiap kata di-prefix dengan '*' dan digabung dengan AND implisit.
 * Contoh: "cat kecil" → '"cat" * "kecil" *' (prefix match setiap kata)
 *
 * Kata "menempel" (1kg, m5) ditambah bentuk terpecah lewat OR sehingga
 * cocok dengan token terpisah ("1 kg") maupun token menempel ("1kg"):
 * "1kg" → `("1kg"* OR "1"* "kg"*)`.
 *
 * Gunakan mode phrase '"kata lengkap"' agar tidak salah-match.
 */
function fts5_build_match(string $search): string
{
    $clean = fts5_escape($search);
    $words = array_filter(preg_split('/\s+/', $clean));
    if (empty($words)) {
        return '""';
    }
    // Gunakan prefix match: setiap kata diakhiri * sehingga "cat" cocok "cat", "catatan", dll.
    $parts = [];
    foreach ($words as $w) {
        $raw   = '"' . $w . '"*';
        $split = split_alpha_num($w);
        if (count($split) > 1) {
            $parts[] = '(' . $raw . ' OR ' . implode(' ', array_map(fn($s) => '"' . $s . '"*', $split)) . ')';
        } else {
            $parts[] = $raw;
        }
    }
    return implode(' ', $parts);
}

/**
 * Escape karakter spesial LIKE (%, _, \) untuk prefix search.
 */
function like_escape(string $term): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
}

/**
 * Sisipkan spasi di setiap batas digit<->huruf ("1kg" -> "1 kg",
 * "m5 x 8" -> "m 5 x 8"). Dipakai untuk varian pencarian yang juga
 * cocok dengan data "menempel" (1kg) maupun "ber-spasi" (1 kg).
 */
function add_alpha_num_spaces(string $s): string
{
    return (string)preg_replace('/(?<=[0-9])(?=[a-zA-Z])|(?<=[a-zA-Z])(?=[0-9])/u', ' ', $s);
}

/**
 * Pecah satu kata menjadi runtunan alfanumerik lalu sub-pecah setiap
 * run di batas digit<->huruf. "1kg" -> ['1','kg']; "m5" -> ['m','5'];
 * "1kg×2kg" -> ['1','kg','2','kg'] (pemisah non-alfanumerik diabaikan).
 */
function split_alpha_num(string $word): array
{
    $runs = preg_split('/[^0-9a-zA-Z]+/', $word, -1, PREG_SPLIT_NO_EMPTY);
    $out  = [];
    foreach ($runs as $run) {
        $segs = preg_split('/(?<=[0-9])(?=[a-zA-Z])|(?<=[a-zA-Z])(?=[0-9])/u', $run);
        foreach ($segs as $seg) {
            if ($seg !== '') $out[] = $seg;
        }
    }
    return $out;
}

// --------------------------------------------------------------------------
// Search builders: mengembalikan [string $whereSql, array $params, string $mode]
// --------------------------------------------------------------------------

/**
 * Search di tabel produk (kolom: nama).
 * Dipakai di pages/produk.php dan pages/verifikasi-stok-input.php.
 *
 * @return array{0: string, 1: array, 2: string}
 */
function fts_search_produk(string $search): array
{
    if ($search === '' || mb_strlen($search) < 2) {
        return ['1=1', [], 'none'];
    }

    // FTS5: cari via fts_produk, ambil rowid (= produk.id)
    if (fts5_available('fts_produk')) {
        $match = fts5_build_match($search);
        $sql = "p.id IN (SELECT rowid FROM fts_produk WHERE fts_produk MATCH ?)";
        return [$sql, [$match], 'fts5'];
    }

    // Substring LIKE fallback (+ varian batas digit-huruf agar 1kg/temukan "1 kg")
    $esc  = like_escape($search);
    $escN = like_escape(add_alpha_num_spaces($search));
    $sql  = "(p.nama LIKE ? ESCAPE '\\' OR p.nama LIKE ? ESCAPE '\\')";
    return [$sql, ['%' . $esc . '%', '%' . $escN . '%'], 'like'];
}

/**
 * Search di tabel merek (kolom: merek) dengan filter opsional id_produk.
 *
 * @return array{0: string, 1: array, 2: string}
 */
function fts_search_merek(string $search, ?int $idProduk = null): array
{
    if ($search === '' || mb_strlen($search) < 2) {
        return ['1=1', [], 'none'];
    }

    if (fts5_available('fts_merek')) {
        $match = fts5_build_match($search);
        if ($idProduk !== null) {
            // CAST(id_produk AS INTEGER): binder array PDO mengikat int sbg teks ('27'),
            // sedangkan kolom virtual menyimpan integer -> setarakan tipenya.
            $sql = "m.id IN (SELECT rowid FROM fts_merek WHERE fts_merek MATCH ? AND CAST(id_produk AS INTEGER) = ?)";
            return [$sql, [$match, $idProduk], 'fts5'];
        }
        $sql = "m.id IN (SELECT rowid FROM fts_merek WHERE fts_merek MATCH ?)";
        return [$sql, [$match], 'fts5'];
    }

    // Substring LIKE fallback (+ varian batas digit-huruf agar 1kg/temukan "1 kg")
    $esc  = like_escape($search);
    $escN = like_escape(add_alpha_num_spaces($search));
    $sql  = "(m.merek LIKE ? ESCAPE '\\' OR m.merek LIKE ? ESCAPE '\\')";
    return [$sql, ['%' . $esc . '%', '%' . $escN . '%'], 'like'];
}

/**
 * Search di tabel ukuran (kolom: ukuran) dengan filter opsional id_merek.
 *
 * @return array{0: string, 1: array, 2: string}
 */
function fts_search_ukuran(string $search, ?int $idMerek = null): array
{
    if ($search === '' || mb_strlen($search) < 2) {
        return ['1=1', [], 'none'];
    }

    if (fts5_available('fts_ukuran')) {
        $match = fts5_build_match($search);
        if ($idMerek !== null) {
            // CAST(id_merek AS INTEGER): binder array PDO mengikat int sbg teks ('9'),
            // sedangkan kolom virtual menyimpan integer 9 -> harus disetarakan tipenya.
            $sql = "u.id IN (SELECT rowid FROM fts_ukuran WHERE fts_ukuran MATCH ? AND CAST(id_merek AS INTEGER) = ?)";
            return [$sql, [$match, $idMerek], 'fts5'];
        }
        $sql = "u.id IN (SELECT rowid FROM fts_ukuran WHERE fts_ukuran MATCH ?)";
        return [$sql, [$match], 'fts5'];
    }

    // Substring LIKE fallback (+ varian batas digit-huruf agar 1kg/temukan "1 kg")
    $esc  = like_escape($search);
    $escN = like_escape(add_alpha_num_spaces($search));
    $sql  = "(u.ukuran LIKE ? ESCAPE '\\' OR u.ukuran LIKE ? ESCAPE '\\')";
    return [$sql, ['%' . $esc . '%', '%' . $escN . '%'], 'like'];
}

/**
 * Search di halaman mutasi-stok / verifikasi-stok.
 * Mencari di: produk.nama, merek.merek, ukuran.ukuran, dan keterangan.
 *
 * Strategi FTS5:
 *   - Produk/merek/ukuran: pakai fts_produk, fts_merek, fts_ukuran
 *   - Keterangan: pakai fts_mutasi_stok atau fts_verifikasi_stok
 *
 * @param string $search    Kata kunci
 * @param string $type      'mutasi' | 'verifikasi'
 * @param string $alias_m   Alias tabel mutasi/verifikasi (default 'm' atau 'v')
 * @return array{0: string, 1: array, 2: string}
 */
function fts_search_stok(string $search, string $type = 'mutasi', string $alias_m = 'm'): array
{
    if ($search === '' || mb_strlen($search) < 2) {
        return ['1=1', [], 'none'];
    }

    $ftsTable = $type === 'verifikasi' ? 'fts_verifikasi_stok' : 'fts_mutasi_stok';
    $ketAlias = $alias_m;

    if (fts5_available('fts_produk') && fts5_available($ftsTable)) {
        $match = fts5_build_match($search);

        // Gabungkan: produk ATAU merek ATAU ukuran ATAU keterangan
        $sql = "(
            -- cocok di nama produk
            me.id_produk IN (SELECT rowid FROM fts_produk WHERE fts_produk MATCH ?)
            OR
            -- cocok di merek
            u.id_merek IN (SELECT rowid FROM fts_merek WHERE fts_merek MATCH ?)
            OR
            -- cocok di ukuran
            {$ketAlias}.id_ukuran IN (SELECT rowid FROM fts_ukuran WHERE fts_ukuran MATCH ?)
            OR
            -- cocok di keterangan
            {$ketAlias}.id IN (SELECT rowid FROM {$ftsTable} WHERE {$ftsTable} MATCH ?)
        )";
        return [$sql, [$match, $match, $match, $match], 'fts5'];
    }

    // Fallback: prefix LIKE (hanya kolom teks, tanpa leading %)
    $esc = like_escape($search);
    $term = $esc . '%';
    $sql = "(
        p.nama LIKE ? ESCAPE '\\'
        OR me.merek LIKE ? ESCAPE '\\'
        OR u.ukuran LIKE ? ESCAPE '\\'
        OR {$ketAlias}.keterangan LIKE ? ESCAPE '\\'
    )";
    return [$sql, [$term, $term, $term, $term], 'prefix'];
}

/**
 * Search di halaman log-aktivitas.
 * Mencari di: deskripsi, modul, aksi.
 *
 * @return array{0: string, 1: array, 2: string}
 */
function fts_search_log(string $search): array
{
    if ($search === '' || mb_strlen($search) < 2) {
        return ['1=1', [], 'none'];
    }

    if (fts5_available('fts_log_aktivitas')) {
        $match = fts5_build_match($search);
        $sql = "l.id IN (SELECT rowid FROM fts_log_aktivitas WHERE fts_log_aktivitas MATCH ?)";
        return [$sql, [$match], 'fts5'];
    }

    $esc = like_escape($search);
    $term = $esc . '%';
    $sql = "(l.deskripsi LIKE ? ESCAPE '\\' OR l.modul LIKE ? ESCAPE '\\' OR l.aksi LIKE ? ESCAPE '\\')";
    return [$sql, [$term, $term, $term], 'prefix'];
}

/**
 * Search di halaman manajemen-user.
 * Mencari di: nama, username.
 *
 * @return array{0: string, 1: array, 2: string}
 */
function fts_search_users(string $search): array
{
    if ($search === '' || mb_strlen($search) < 2) {
        return ['1=1', [], 'none'];
    }

    if (fts5_available('fts_users')) {
        $match = fts5_build_match($search);
        $sql = "id IN (SELECT rowid FROM fts_users WHERE fts_users MATCH ?)";
        return [$sql, [$match], 'fts5'];
    }

    $esc = like_escape($search);
    $term = $esc . '%';
    $sql = "(nama LIKE ? ESCAPE '\\' OR username LIKE ? ESCAPE '\\')";
    return [$sql, [$term, $term], 'prefix'];
}

/**
 * Minimum panjang pencarian agar search dijalankan.
 * Return true jika search cukup panjang untuk diproses.
 */
function search_min_length(string $search, int $min = 2): bool
{
    return mb_strlen(trim($search)) >= $min;
}
