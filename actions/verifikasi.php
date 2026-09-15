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
    $merekId = (int)($_POST['merek_id'] ?? 0);
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
    $raw = trim(str_replace('.', '', (string)($_POST['stok_fisik'] ?? '0')));
    $raw = str_replace(',', '.', $raw);
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
    $tanggal = trim($_POST['tanggal'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $keranjang = $_SESSION['verifikasi_keranjang'] ?? [];

    $_SESSION['verifikasi_tanggal'] = $tanggal;
    $_SESSION['verifikasi_keterangan'] = $keterangan;

    if ($tanggal === '') {
        set_flash('error', 'Tanggal wajib diisi.');
        redirect('/verifikasi-stok/input');
    }
    if (empty($keranjang)) {
        set_flash('error', 'Keranjang verifikasi masih kosong.');
        redirect('/verifikasi-stok/input');
    }

    $userId = auth_id();
    $db = db();

    try {
        $db->beginTransaction();

        foreach ($keranjang as $ukuranId => $cartItem) {
            $stmt = $db->prepare('SELECT * FROM ukuran WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$ukuranId]);
            $ukuran = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ukuran) {
                throw new RuntimeException("Ukuran dengan ID {$ukuranId} tidak ditemukan.");
            }

            $stokSistem = (float)$ukuran['stok'];
            $stokFisik = isset($cartItem['stokFisik']) ? (float)$cartItem['stokFisik'] : $stokSistem;
            $selisih = $stokFisik - $stokSistem;
            $ts = now();

            $insert = $db->prepare('INSERT INTO verifikasi_stok (id_user, id_ukuran, tanggal, stok_sistem, stok_fisik, selisih, keterangan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$userId, $ukuranId, $tanggal, $stokSistem, $stokFisik, $selisih, $keterangan !== '' ? $keterangan : null, $ts, $ts]);

            $update = $db->prepare('UPDATE ukuran SET stok = ?, updated_at = ? WHERE id = ?');
            $update->execute([$stokFisik, $ts, $ukuranId]);
        }

        $db->commit();

        log_aktivitas('CREATE', 'verifikasi-stok', null, "Verifikasi stok {$tanggal} sebanyak " . count($keranjang) . ' item');

        $_SESSION['verifikasi_keranjang'] = [];
        unset($_SESSION['verifikasi_tanggal'], $_SESSION['verifikasi_keterangan']);

        set_flash('success', count($keranjang) . ' verifikasi stok berhasil disimpan.');
        redirect('/verifikasi-stok');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        set_flash('error', 'Gagal menyimpan verifikasi stok: ' . $e->getMessage());
        redirect('/verifikasi-stok/input');
    }
}

set_flash('error', 'Aksi tidak diketahui.');
redirect('/verifikasi-stok/input');