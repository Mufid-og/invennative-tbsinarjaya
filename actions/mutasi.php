<?php
// actions/mutasi.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/mutasi-stok');
}

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'set_jenis':
        $jenis = $_POST['jenis'] ?? '';
        if (!in_array($jenis, ['masuk', 'keluar'])) {
            redirect('/mutasi-stok/input');
        }
        $_SESSION['mutasi_jenis'] = $jenis;
        $_SESSION['mutasi_keranjang'] = [];
        $_SESSION['mutasi_tanggal'] = date('Y-m-d\TH:i');
        $_SESSION['mutasi_keterangan'] = '';
        redirect('/mutasi-stok/input?step=produk');
        break;

    case 'pilih_produk':
        $jenis = $_SESSION['mutasi_jenis'] ?? '';
        if (!$jenis) {
            redirect('/mutasi-stok/input');
        }
        $produkId = (int)($_POST['produk_id'] ?? 0);
        if (!$produkId) {
            redirect('/mutasi-stok/input?step=produk');
        }
        redirect('/mutasi-stok/input?step=merek&produk_id=' . $produkId);
        break;

    case 'pilih_merek':
        $jenis = $_SESSION['mutasi_jenis'] ?? '';
        if (!$jenis) {
            redirect('/mutasi-stok/input');
        }
        $merekId = (int)($_POST['merek_id'] ?? 0);
        $produkId = (int)($_POST['produk_id'] ?? 0);
        if (!$merekId) {
            redirect('/mutasi-stok/input?step=produk');
        }
        redirect('/mutasi-stok/input?step=ukuran&produk_id=' . $produkId . '&merek_id=' . $merekId);
        break;

    case 'tambah_keranjang':
        $jenis = $_SESSION['mutasi_jenis'] ?? '';
        if (!$jenis) {
            redirect('/mutasi-stok/input');
        }
        $ukuranId = (int)($_POST['ukuran_id'] ?? 0);
        $produkId = (int)($_POST['produk_id'] ?? 0);
        $merekId = (int)($_POST['merek_id'] ?? 0);
        $hargaModal = (float)($_POST['harga_modal'] ?? 0);
        $hargaJual = (float)($_POST['harga_jual'] ?? 0);

        if (!$ukuranId) {
            redirect('/mutasi-stok/input?step=ukuran&produk_id=' . $produkId . '&merek_id=' . $merekId);
        }

        if (!isset($_SESSION['mutasi_keranjang'])) {
            $_SESSION['mutasi_keranjang'] = [];
        }

        $_SESSION['mutasi_keranjang'][$ukuranId] = [
            'qty' => 1,
            'hargaModal' => $hargaModal,
            'hargaJual' => $hargaJual,
        ];

        redirect('/mutasi-stok/input?step=ukuran&produk_id=' . $produkId . '&merek_id=' . $merekId);
        break;

    case 'hapus_keranjang':
        $ukuranId = (int)($_POST['ukuran_id'] ?? 0);
        $produkId = (int)($_POST['produk_id'] ?? 0);
        $merekId = (int)($_POST['merek_id'] ?? 0);

        if (isset($_SESSION['mutasi_keranjang'][$ukuranId])) {
            unset($_SESSION['mutasi_keranjang'][$ukuranId]);
        }

        $redirectUrl = '/mutasi-stok/input?step=ukuran';
        if ($produkId) $redirectUrl .= '&produk_id=' . $produkId;
        if ($merekId) $redirectUrl .= '&merek_id=' . $merekId;
        redirect($redirectUrl);
        break;

    case 'kosongkan_keranjang':
        $_SESSION['mutasi_keranjang'] = [];
        $step = $_GET['step'] ?? 'ukuran';
        $produkId = (int)($_GET['produk_id'] ?? 0);
        $merekId = (int)($_GET['merek_id'] ?? 0);
        $redirectUrl = '/mutasi-stok/input?step=' . $step;
        if ($produkId) $redirectUrl .= '&produk_id=' . $produkId;
        if ($merekId) $redirectUrl .= '&merek_id=' . $merekId;
        redirect($redirectUrl);
        break;

    case 'simpan':
        $jenis = $_SESSION['mutasi_jenis'] ?? '';
        $keranjang = $_SESSION['mutasi_keranjang'] ?? [];
        $tanggal = trim($_POST['tanggal'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if (!$jenis) {
            set_flash('error', 'Jenis mutasi belum dipilih.');
            redirect('/mutasi-stok/input');
        }

        if (empty($keranjang)) {
            set_flash('error', 'Keranjang masih kosong. Pilih item terlebih dahulu.');
            redirect('/mutasi-stok/input?step=ukuran');
        }

        if ($tanggal === '') {
            set_flash('error', 'Tanggal wajib diisi.');
            $redirectUrl = '/mutasi-stok/input?step=ukuran';
            $produkId = (int)($_GET['produk_id'] ?? 0);
            $merekId = (int)($_GET['merek_id'] ?? 0);
            if ($produkId) $redirectUrl .= '&produk_id=' . $produkId;
            if ($merekId) $redirectUrl .= '&merek_id=' . $merekId;
            redirect($redirectUrl);
        }

        // Parse datetime
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $tanggal);
        if (!$dt) {
            set_flash('error', 'Format tanggal tidak valid.');
            redirect('/mutasi-stok/input?step=ukuran');
        }
        $tanggalFormatted = $dt->format('Y-m-d H:i:s');

        // Validate each item
        $db = db();
        $validatedItems = [];

        foreach ($keranjang as $ukId => $item) {
            $qty = (int)($_POST['qty_' . $ukId] ?? $item['qty'] ?? 0);
            $hargaModal = (float)($_POST['harga_modal_raw_' . $ukId] ?? $item['hargaModal'] ?? 0);
            $hargaJual = (float)($_POST['harga_jual_raw_' . $ukId] ?? $item['hargaJual'] ?? 0);

            if ($qty <= 0) {
                set_flash('error', 'Qty harus lebih dari 0 untuk setiap item.');
                redirect('/mutasi-stok/input?step=ukuran');
            }

            $stmt = $db->prepare("SELECT u.*, m.merek, p.nama as produk_nama FROM ukuran u JOIN merek m ON m.id = u.id_merek JOIN produk p ON p.id = m.id_produk WHERE u.id = ? AND u.deleted_at IS NULL");
            $stmt->execute([$ukId]);
            $ukuran = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ukuran) {
                set_flash('error', 'Item dengan ID ukuran ' . $ukId . ' tidak ditemukan.');
                redirect('/mutasi-stok/input?step=ukuran');
            }

            if ($jenis === 'keluar' && $ukuran['stok'] < $qty) {
                set_flash('error', 'Stok ' . $ukuran['produk_nama'] . ' (' . $ukuran['merek'] . ' / ' . $ukuran['ukuran'] . ') tidak cukup. Stok tersedia: ' . format_number($ukuran['stok'], 0) . ', diminta: ' . $qty . '.');
                redirect('/mutasi-stok/input?step=ukuran');
            }

            $validatedItems[] = [
                'ukuranId' => $ukId,
                'qty' => $qty,
                'hargaModal' => $hargaModal,
                'hargaJual' => $hargaJual,
                'ukuran' => $ukuran,
            ];
        }

        // Insert in transaction
        try {
            $db->beginTransaction();
            $ts = now();

            foreach ($validatedItems as $vi) {
                $ukId = $vi['ukuranId'];
                $qty = $vi['qty'];
                $hargaModal = $vi['hargaModal'];
                $hargaJual = $vi['hargaJual'];
                $ukuran = $vi['ukuran'];

                $insertStmt = $db->prepare('INSERT INTO mutasi_stok (id_ukuran, tanggal, jenis, qty, harga_modal, harga_jual, keterangan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $insertStmt->execute([$ukId, $tanggalFormatted, $jenis, $qty, $hargaModal, $hargaJual, $keterangan, $ts, $ts]);
                $mutasiId = (int)$db->lastInsertId();

                // Update stok
                if ($jenis === 'masuk') {
                    $stokStmt = $db->prepare('UPDATE ukuran SET stok = stok + ?, harga_modal = ?, harga_jual = ?, updated_at = ? WHERE id = ?');
                    $stokStmt->execute([$qty, $hargaModal, $hargaJual, $ts, $ukId]);
                } else {
                    $stokStmt = $db->prepare('UPDATE ukuran SET stok = stok - ?, updated_at = ? WHERE id = ?');
                    $stokStmt->execute([$qty, $ts, $ukId]);
                    if ($hargaModal > 0 || $hargaJual > 0) {
                        $priceStmt = $db->prepare('UPDATE ukuran SET harga_modal = CASE WHEN ? > 0 THEN ? ELSE harga_modal END, harga_jual = CASE WHEN ? > 0 THEN ? ELSE harga_jual END WHERE id = ?');
                        $priceStmt->execute([$hargaModal, $hargaModal, $hargaJual, $hargaJual, $ukId]);
                    }
                }

                $namaLengkap = $ukuran['produk_nama'] . ' ' . $ukuran['merek'] . ' ' . $ukuran['ukuran'];
                log_aktivitas('CREATE', 'mutasi-stok', $mutasiId, 'Mutasi stok ' . $jenis . ': ' . $namaLengkap . ' x' . $qty, [
                    'produk' => $ukuran['produk_nama'],
                    'merek' => $ukuran['merek'],
                    'ukuran' => $ukuran['ukuran'],
                    'jenis' => $jenis,
                    'qty' => $qty,
                    'harga_modal' => $hargaModal,
                    'harga_jual' => $hargaJual,
                    'tanggal' => $tanggalFormatted,
                ]);
            }

            $db->commit();

            $_SESSION['mutasi_keranjang'] = [];
            $_SESSION['mutasi_jenis'] = '';
            $_SESSION['mutasi_tanggal'] = '';
            $_SESSION['mutasi_keterangan'] = '';

            set_flash('success', 'Berhasil menyimpan ' . count($validatedItems) . ' item mutasi stok ' . $jenis . '.');
            redirect('/mutasi-stok');

        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            set_flash('error', 'Gagal menyimpan mutasi stok: ' . $e->getMessage());
            redirect('/mutasi-stok/input?step=ukuran');
        }
        break;

    default:
        redirect('/mutasi-stok');
        break;
}
