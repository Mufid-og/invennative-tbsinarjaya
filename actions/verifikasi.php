<?php
// actions/verifikasi.php
$action = $_POST['action'] ?? '';

if (!isset($_SESSION['verifikasi_keranjang'])) {
    $_SESSION['verifikasi_keranjang'] = [];
}
if (!isset($_SESSION['verifikasi_step'])) {
    $_SESSION['verifikasi_step'] = ['produk_id' => null, 'merek_id' => null];
}

// ===== SET STEP =====
if ($action === 'set_step') {
    $step = $_POST['step'] ?? 'produk';

    if ($step === 'produk') {
        $_SESSION['verifikasi_step'] = ['produk_id' => null, 'merek_id' => null];
    } elseif ($step === 'merek') {
        $_SESSION['verifikasi_step']['merek_id'] = null;
    }

    redirect('/verifikasi-stok/input');
}

// ===== PILIH PRODUK =====
if ($action === 'pilih_produk') {
    $produkId = (int)($_POST['produk_id'] ?? 0);

    $stmt = db()->prepare('SELECT id FROM produk WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$produkId]);
    if (!$stmt->fetch()) {
        set_flash('error', 'Produk tidak ditemukan.');
        redirect('/verifikasi-stok/input');
    }

    $_SESSION['verifikasi_step'] = ['produk_id' => $produkId, 'merek_id' => null];
    redirect('/verifikasi-stok/input');
}

// ===== PILIH MEREK =====
if ($action === 'pilih_merek') {
    $merekId  = (int)($_POST['merek_id'] ?? 0);
    $produkId = (int)($_SESSION['verifikasi_step']['produk_id'] ?? 0);

    $stmt = db()->prepare('SELECT id FROM merek WHERE id = ? AND id_produk = ? AND deleted_at IS NULL');
    $stmt->execute([$merekId, $produkId]);
    if (!$stmt->fetch()) {
        set_flash('error', 'Merek tidak ditemukan.');
        redirect('/verifikasi-stok/input');
    }

    $_SESSION['verifikasi_step']['merek_id'] = $merekId;
    redirect('/verifikasi-stok/input');
}

// ===== TAMBAH KERANJANG =====
if ($action === 'tambah_keranjang') {
    $ukuranId = (int)($_POST['ukuran_id'] ?? 0);

    $stmt = db()->prepare('SELECT * FROM ukuran WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$ukuranId]);
    $ukuran = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ukuran) {
        set_flash('error', 'Ukuran tidak ditemukan.');
        redirect('/verifikasi-stok/input');
    }

    if (!isset($_SESSION['verifikasi_keranjang'][$ukuranId])) {
        $_SESSION['verifikasi_keranjang'][$ukuranId] = [
            'stokFisik' => (float)$ukuran['stok'],
        ];
    }

    redirect('/verifikasi-stok/input');
}

// ===== UPDATE STOK FISIK KERANJANG =====
if ($action === 'update_stok_fisik') {
    $ukuranId = (int)($_POST['ukuran_id'] ?? 0);
    $raw      = trim(str_replace('.', '', (string)($_POST['stok_fisik'] ?? '0')));
    $raw      = str_replace(',', '.', $raw);
    $stokFisik = (float)$raw;

    if (isset($_SESSION['verifikasi_keranjang'][$ukuranId])) {
        $_SESSION['verifikasi_keranjang'][$ukuranId]['stokFisik'] = $stokFisik;
    }

    redirect('/verifikasi-stok/input');
}

// ===== HAPUS KERANJANG =====
if ($action === 'hapus_keranjang') {
    $ukuranId = (int)($_POST['ukuran_id'] ?? 0);
    if (isset($_SESSION['verifikasi_keranjang'][$ukuranId])) {
        unset($_SESSION['verifikasi_keranjang'][$ukuranId]);
    }
    redirect('/verifikasi-stok/input');
}

// ===== KOSONGKAN KERANJANG =====
if ($action === 'kosongkan_keranjang') {
    $_SESSION['verifikasi_keranjang'] = [];
    unset($_SESSION['verifikasi_tanggal'], $_SESSION['verifikasi_keterangan']);
    redirect('/verifikasi-stok/input');
}

// ===== SIMPAN =====
if ($action === 'simpan') {
    $tanggal    = trim($_POST['tanggal'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $keranjang  = $_SESSION['verifikasi_keranjang'] ?? [];

    $_SESSION['verifikasi_tanggal']    = $tanggal;
    $_SESSION['verifikasi_keterangan'] = $keterangan;

    if ($tanggal === '') {
        set_flash('error', 'Tanggal wajib diisi.');
        redirect('/verifikasi-stok/input');
    }
    if (empty($keranjang)) {
        set_flash('error', 'Keranjang verifikasi masih kosong.');
        redirect('/verifikasi-stok/input');
    }

    $userId    = auth_id();
    $db        = db();
    $ukuranIds = array_keys($keranjang);
    $n         = count($ukuranIds);

    // ── [QUERY 1] Batch SELECT WHERE IN — mengganti N SELECT di dalam transaksi
    $placeholders = implode(',', array_fill(0, $n, '?'));
    $batchStmt = $db->prepare(
        "SELECT u.*, m.merek, p.nama AS produk_nama
         FROM ukuran u
         JOIN merek m ON m.id = u.id_merek
         JOIN produk p ON p.id = m.id_produk
         WHERE u.id IN ({$placeholders}) AND u.deleted_at IS NULL"
    );
    $batchStmt->execute($ukuranIds);
    $ukuranMap = [];
    while ($row = $batchStmt->fetch(PDO::FETCH_ASSOC)) {
        $ukuranMap[(int)$row['id']] = $row;
    }

    // Validasi: semua ID keranjang harus ada
    foreach ($ukuranIds as $ukId) {
        if (!isset($ukuranMap[(int)$ukId])) {
            set_flash('error', "Ukuran dengan ID {$ukId} tidak ditemukan.");
            redirect('/verifikasi-stok/input');
        }
    }

    // Bangun data yang akan diinsert & diupdate
    $ts          = now();
    $detailItems = [];
    $insertRows  = [];
    $insertParams = [];
    // Untuk CASE WHEN UPDATE stok
    $caseStok     = [];
    $caseParams   = [];
    $inIds        = [];

    foreach ($keranjang as $ukuranId => $cartItem) {
        $ukuran     = $ukuranMap[(int)$ukuranId]; // O(1) lookup
        $stokSistem = (float)$ukuran['stok'];
        $stokFisik  = isset($cartItem['stokFisik']) ? (float)$cartItem['stokFisik'] : $stokSistem;
        $selisih    = $stokFisik - $stokSistem;

        $insertRows[]   = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
        array_push($insertParams,
            $userId, $ukuranId, $tanggal,
            $stokSistem, $stokFisik, $selisih,
            $keterangan !== '' ? $keterangan : null,
            $ts, $ts
        );

        $caseStok[]   = 'WHEN ? THEN ?';
        array_push($caseParams, (int)$ukuranId, $stokFisik);
        $inIds[]      = (int)$ukuranId;

        $detailItems[] = [
            'produk'      => $ukuran['produk_nama'],
            'merek'       => $ukuran['merek'],
            'ukuran'      => $ukuran['ukuran'],
            'stok_sistem' => $stokSistem,
            'stok_fisik'  => $stokFisik,
            'selisih'     => $selisih,
        ];
    }

    try {
        $db->beginTransaction();

        // ── [QUERY 2] 1 multi-row INSERT verifikasi_stok — mengganti N INSERT ─
        // prepare sekali, execute sekali (SQL bervariasi tergantung N)
        $insertStmt = $db->prepare(
            'INSERT INTO verifikasi_stok (id_user, id_ukuran, tanggal, stok_sistem, stok_fisik, selisih, keterangan, created_at, updated_at) VALUES '
            . implode(', ', $insertRows)
        );
        $insertStmt->execute($insertParams);

        // ── [QUERY 3] 1 CASE WHEN UPDATE ukuran.stok — mengganti N UPDATE ────
        $inPlaceholders = implode(',', array_fill(0, $n, '?'));
        $caseSql        = implode(' ', $caseStok);

        $updateParams = array_merge($caseParams, [$ts], $inIds);
        // prepare sekali, execute sekali (SQL bervariasi tergantung N)
        $updateStmt = $db->prepare(
            "UPDATE ukuran SET
                stok       = CASE id {$caseSql} END,
                updated_at = ?
             WHERE id IN ({$inPlaceholders})"
        );
        $updateStmt->execute($updateParams);

        $db->commit();

        // Invalidasi dashboard cache: stok berubah setelah verifikasi
        require_once __DIR__ . '/../includes/dashboard_cache.php';
        dashboard_cache_invalidate('dashboard_stats');

        // ── [QUERY 4] 1 log_aktivitas — sudah benar, di luar transaksi & loop ─
        log_aktivitas('CREATE', 'verifikasi-stok', null,
            "Verifikasi stok {$tanggal} sebanyak {$n} item",
            [
                'tanggal'    => $tanggal,
                'keterangan' => $keterangan,
                'items'      => $detailItems,
            ]
        );

        $_SESSION['verifikasi_keranjang'] = [];
        unset($_SESSION['verifikasi_tanggal'], $_SESSION['verifikasi_keterangan']);

        set_flash('success', $n . ' verifikasi stok berhasil disimpan.');
        redirect('/verifikasi-stok');

    } catch (\Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        set_flash('error', 'Gagal menyimpan verifikasi stok: ' . $e->getMessage());
        redirect('/verifikasi-stok/input');
    }
}

set_flash('error', 'Aksi tidak diketahui.');
redirect('/verifikasi-stok/input');
