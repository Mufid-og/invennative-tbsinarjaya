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
        $merekId  = (int)($_POST['merek_id'] ?? 0);
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
        $ukuranId   = (int)($_POST['ukuran_id'] ?? 0);
        $produkId   = (int)($_POST['produk_id'] ?? 0);
        $merekId    = (int)($_POST['merek_id'] ?? 0);
        $hargaModal = (float)($_POST['harga_modal'] ?? 0);
        $hargaJual  = (float)($_POST['harga_jual'] ?? 0);

        if (!$ukuranId) {
            redirect('/mutasi-stok/input?step=ukuran&produk_id=' . $produkId . '&merek_id=' . $merekId);
        }

        if (!isset($_SESSION['mutasi_keranjang'])) {
            $_SESSION['mutasi_keranjang'] = [];
        }

        $_SESSION['mutasi_keranjang'][$ukuranId] = [
            'qty'        => 1,
            'hargaModal' => $hargaModal,
            'hargaJual'  => $hargaJual,
        ];

        redirect('/mutasi-stok/input?step=ukuran&produk_id=' . $produkId . '&merek_id=' . $merekId);
        break;

    case 'hapus_keranjang':
        $ukuranId = (int)($_POST['ukuran_id'] ?? 0);
        $produkId = (int)($_POST['produk_id'] ?? 0);
        $merekId  = (int)($_POST['merek_id'] ?? 0);

        if (isset($_SESSION['mutasi_keranjang'][$ukuranId])) {
            unset($_SESSION['mutasi_keranjang'][$ukuranId]);
        }

        $redirectUrl = '/mutasi-stok/input?step=ukuran';
        if ($produkId) $redirectUrl .= '&produk_id=' . $produkId;
        if ($merekId)  $redirectUrl .= '&merek_id=' . $merekId;
        redirect($redirectUrl);
        break;

    case 'kosongkan_keranjang':
        $_SESSION['mutasi_keranjang'] = [];
        $step     = $_GET['step'] ?? 'ukuran';
        $produkId = (int)($_GET['produk_id'] ?? 0);
        $merekId  = (int)($_GET['merek_id'] ?? 0);
        $redirectUrl = '/mutasi-stok/input?step=' . $step;
        if ($produkId) $redirectUrl .= '&produk_id=' . $produkId;
        if ($merekId)  $redirectUrl .= '&merek_id=' . $merekId;
        redirect($redirectUrl);
        break;

    case 'simpan':
        $jenis      = $_SESSION['mutasi_jenis'] ?? '';
        $keranjang  = $_SESSION['mutasi_keranjang'] ?? [];
        $tanggal    = trim($_POST['tanggal'] ?? '');
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
            $produkId    = (int)($_GET['produk_id'] ?? 0);
            $merekId     = (int)($_GET['merek_id'] ?? 0);
            if ($produkId) $redirectUrl .= '&produk_id=' . $produkId;
            if ($merekId)  $redirectUrl .= '&merek_id=' . $merekId;
            redirect($redirectUrl);
        }

        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $tanggal);
        if (!$dt) {
            set_flash('error', 'Format tanggal tidak valid.');
            redirect('/mutasi-stok/input?step=ukuran');
        }
        $tanggalFormatted = $dt->format('Y-m-d H:i:s');

        $db        = db();
        $ukuranIds = array_keys($keranjang);
        $n         = count($ukuranIds);

        // ── [QUERY 1] Batch SELECT WHERE IN — mengganti N SELECT ─────────────
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

        // Validasi: semua ID keranjang harus ada di database
        foreach ($ukuranIds as $ukId) {
            if (!isset($ukuranMap[(int)$ukId])) {
                set_flash('error', 'Item dengan ID ukuran ' . $ukId . ' tidak ditemukan.');
                redirect('/mutasi-stok/input?step=ukuran');
            }
        }

        // Validasi qty & stok, bangun $validatedItems
        $validatedItems = [];
        foreach ($keranjang as $ukId => $item) {
            $qty        = parse_qty((string)($_POST['qty_' . $ukId] ?? $item['qty'] ?? 0));
            $hargaModal = (float)($_POST['harga_modal_raw_' . $ukId] ?? $item['hargaModal'] ?? 0);
            $hargaJual  = (float)($_POST['harga_jual_raw_' . $ukId] ?? $item['hargaJual'] ?? 0);

            if ($qty === null || $qty <= 0) {
                set_flash('error', 'Qty harus lebih dari 0 untuk setiap item.');
                redirect('/mutasi-stok/input?step=ukuran');
            }
            $qty = round($qty, 2);

            $ukuran = $ukuranMap[(int)$ukId]; // O(1) lookup, tidak ada query

            if ($jenis === 'keluar' && $ukuran['stok'] < $qty) {
                set_flash('error',
                    'Stok ' . $ukuran['produk_nama'] . ' (' . $ukuran['merek'] . ' / ' . $ukuran['ukuran'] . ')'
                    . ' tidak cukup. Stok tersedia: ' . format_qty((float)$ukuran['stok'])
                    . ', diminta: ' . format_qty($qty) . '.'
                );
                redirect('/mutasi-stok/input?step=ukuran');
            }

            $validatedItems[] = [
                'ukuranId'   => (int)$ukId,
                'qty'        => $qty,
                'hargaModal' => $hargaModal,
                'hargaJual'  => $hargaJual,
                'ukuran'     => $ukuran,
            ];
        }

        try {
            $db->beginTransaction();
            $ts = now();

            // ── [QUERY 2] 1 multi-row INSERT — mengganti N INSERT ─────────────
            // SQL bervariasi per N item sehingga tidak bisa di-cache via static,
            // tapi assign ke variable agar mudah di-debug dan error bisa ditrap.
            $insertRowPlaceholders = [];
            $insertParams          = [];
            foreach ($validatedItems as $vi) {
                $insertRowPlaceholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
                array_push($insertParams,
                    $vi['ukuranId'], $tanggalFormatted, $jenis,
                    $vi['qty'], $vi['hargaModal'], $vi['hargaJual'],
                    $keterangan, $ts, $ts
                );
            }
            // prepare sekali, execute sekali (SQL bervariasi tergantung N)
            $insertStmt = $db->prepare(
                'INSERT INTO mutasi_stok (id_ukuran, tanggal, jenis, qty, harga_modal, harga_jual, keterangan, created_at, updated_at) VALUES '
                . implode(', ', $insertRowPlaceholders)
            );
            $insertStmt->execute($insertParams);

            // SQLite AUTOINCREMENT berurutan dalam satu multi-row INSERT:
            // lastInsertId() = ID baris terakhir, firstId = lastId - n + 1
            $lastInsertedId  = (int)$db->lastInsertId();
            $firstInsertedId = $lastInsertedId - $n + 1;

            // ── [QUERY 3] 1 CASE WHEN UPDATE — mengganti N UPDATE ────────────
            $inIds          = array_column($validatedItems, 'ukuranId');
            $inPlaceholders = implode(',', array_fill(0, $n, '?'));

            if ($jenis === 'masuk') {
                // UPDATE stok + harga_modal + harga_jual sekaligus
                $caseStok  = implode(' ', array_map(fn($vi) => 'WHEN ? THEN stok + ?', $validatedItems));
                $caseModal = implode(' ', array_map(fn($vi) => 'WHEN ? THEN ?', $validatedItems));
                $caseJual  = implode(' ', array_map(fn($vi) => 'WHEN ? THEN ?', $validatedItems));

                $updateParams = [];
                foreach ($validatedItems as $vi) {
                    array_push($updateParams, $vi['ukuranId'], $vi['qty']);
                }
                foreach ($validatedItems as $vi) {
                    array_push($updateParams, $vi['ukuranId'], $vi['hargaModal']);
                }
                foreach ($validatedItems as $vi) {
                    array_push($updateParams, $vi['ukuranId'], $vi['hargaJual']);
                }
                $updateParams[] = $ts;
                foreach ($inIds as $id) {
                    $updateParams[] = $id;
                }

                // prepare sekali, execute sekali (SQL bervariasi tergantung N)
                $updateStmt = $db->prepare(
                    "UPDATE ukuran SET
                        stok        = CASE id {$caseStok}  END,
                        harga_modal = CASE id {$caseModal} END,
                        harga_jual  = CASE id {$caseJual}  END,
                        updated_at  = ?
                     WHERE id IN ({$inPlaceholders})"
                );
                $updateStmt->execute($updateParams);

            } else {
                // keluar: hanya UPDATE stok
                $caseStok = implode(' ', array_map(fn($vi) => 'WHEN ? THEN stok - ?', $validatedItems));

                $updateParams = [];
                foreach ($validatedItems as $vi) {
                    array_push($updateParams, $vi['ukuranId'], $vi['qty']);
                }
                $updateParams[] = $ts;
                foreach ($inIds as $id) {
                    $updateParams[] = $id;
                }

                // prepare sekali, execute sekali (SQL bervariasi tergantung N)
                $updateStmt = $db->prepare(
                    "UPDATE ukuran SET
                        stok       = CASE id {$caseStok} END,
                        updated_at = ?
                     WHERE id IN ({$inPlaceholders})"
                );
                $updateStmt->execute($updateParams);
            }

            $db->commit();

            // Invalidasi dashboard cache: stok berubah (stats) + data mutasi baru (charts)
            require_once __DIR__ . '/../includes/dashboard_cache.php';
            dashboard_cache_invalidate('dashboard_stats', 'dashboard_charts');

            // ── [QUERY 4..3+N] log_aktivitas — tetap per item karena setiap
            //    mutasi_stok punya id_referensi unik; ringan (INSERT tanpa JOIN).
            //    Dipanggil DI LUAR transaksi agar rollback data tidak hilangkan log.
            foreach ($validatedItems as $idx => $vi) {
                $mutasiId    = $firstInsertedId + $idx;
                $namaLengkap = $vi['ukuran']['produk_nama'] . ' ' . $vi['ukuran']['merek'] . ' ' . $vi['ukuran']['ukuran'];
                log_aktivitas('CREATE', 'mutasi-stok', $mutasiId,
                    'Mutasi stok ' . $jenis . ': ' . $namaLengkap . ' x' . format_qty((float)$vi['qty']),
                    [
                        'produk'      => $vi['ukuran']['produk_nama'],
                        'merek'       => $vi['ukuran']['merek'],
                        'ukuran'      => $vi['ukuran']['ukuran'],
                        'jenis'       => $jenis,
                        'qty'         => $vi['qty'],
                        'harga_modal' => $vi['hargaModal'],
                        'harga_jual'  => $vi['hargaJual'],
                        'tanggal'     => $tanggalFormatted,
                    ]
                );
            }

            $_SESSION['mutasi_keranjang']  = [];
            $_SESSION['mutasi_jenis']      = '';
            $_SESSION['mutasi_tanggal']    = '';
            $_SESSION['mutasi_keterangan'] = '';

            set_flash('success', 'Berhasil menyimpan ' . $n . ' item mutasi stok ' . $jenis . '.');
            redirect('/mutasi-stok');

        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            set_flash('error', 'Gagal menyimpan mutasi stok: ' . $e->getMessage());
            redirect('/mutasi-stok/input?step=ukuran');
        }
        break;

    case 'hapus':
        $id = (int)($_POST['id'] ?? 0);

        $redirectUrl = '/mutasi-stok';
        $queryParams    = [];
        $searchHapus    = trim((string)($_POST['search'] ?? ''));
        $pageHapus      = (int)($_POST['page'] ?? 1);
        if ($searchHapus !== '')  $queryParams['search'] = $searchHapus;
        if ($pageHapus > 1)       $queryParams['page']   = $pageHapus;
        if ($queryParams) $redirectUrl .= '?' . http_build_query($queryParams);

        if ($id <= 0) {
            set_flash('error', 'Mutasi stok tidak ditemukan.');
            redirect($redirectUrl);
        }

        $stmt = db()->prepare(
            'SELECT m.*, u.ukuran AS nama_ukuran, me.merek, p.nama AS produk_nama
             FROM mutasi_stok m
             JOIN ukuran u ON u.id = m.id_ukuran
             JOIN merek me ON me.id = u.id_merek
             JOIN produk p ON p.id = me.id_produk
             WHERE m.id = ? AND m.deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        $mutasi = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mutasi) {
            set_flash('error', 'Mutasi stok tidak ditemukan.');
            redirect($redirectUrl);
        }

        // Reversal: masuk → stok -qty, keluar → stok +qty (stok boleh negatif)
        $delta = $mutasi['jenis'] === 'keluar' ? (float)$mutasi['qty'] : -(float)$mutasi['qty'];
        $ts    = now();
        $db    = db();

        try {
            $db->beginTransaction();

            $del = $db->prepare('UPDATE mutasi_stok SET deleted_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL');
            $del->execute([$ts, $ts, $id]);
            if ($del->rowCount() !== 1) {
                throw new RuntimeException('Mutasi stok sudah dihapus atau tidak ditemukan.');
            }

            $db->prepare('UPDATE ukuran SET stok = stok + ?, updated_at = ? WHERE id = ?')
                ->execute([$delta, $ts, (int)$mutasi['id_ukuran']]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            set_flash('error', 'Gagal menghapus mutasi stok: ' . $e->getMessage());
            redirect($redirectUrl);
        }

        // Invalidasi dashboard cache: stok berubah (stats) + riwayat mutasi berubah (charts)
        require_once __DIR__ . '/../includes/dashboard_cache.php';
        dashboard_cache_invalidate('dashboard_stats', 'dashboard_charts');

        $namaLengkap = $mutasi['produk_nama'] . ' ' . $mutasi['merek'] . ' ' . $mutasi['nama_ukuran'];
        log_aktivitas('DELETE', 'mutasi-stok', $id,
            'Mutasi stok ' . $mutasi['jenis'] . ' dihapus: ' . $namaLengkap . ' x' . format_qty((float)$mutasi['qty']) . ' (stok dikembalikan)',
            [
                'produk' => $mutasi['produk_nama'],
                'merek'  => $mutasi['merek'],
                'ukuran' => $mutasi['nama_ukuran'],
                'jenis'  => $mutasi['jenis'],
                'qty'    => $mutasi['qty'],
                'tanggal'=> $mutasi['tanggal'],
            ]
        );
        set_flash('success', 'Mutasi stok berhasil dihapus. Stok ukuran dikembalikan.');
        redirect($redirectUrl);
        break;

    default:
        redirect('/mutasi-stok');
        break;
}
