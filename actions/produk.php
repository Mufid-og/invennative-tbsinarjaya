<?php

// actions/produk.php
// POST handler for produk/merek/ukuran CRUD.
// Redirects back to /produk with appropriate query params.
// This file is required from pages/produk.php via require_once.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$action = $_POST['action'] ?? '';
$db = db();

function produk_url(?int $produkId = null, ?int $merekId = null, string $search = '', int $page = 0): string
{
    $q = [];
    if ($produkId) $q['produk'] = $produkId;
    if ($merekId) $q['merek'] = $merekId;
    if ($search !== '') $q['search'] = $search;
    if ($page > 0) $q['page'] = $page;
    return '/produk' . ($q ? '?' . http_build_query($q) : '');
}

function merek_produk_id(int $idMerek): int
{
    $stmt = db()->prepare('SELECT id_produk FROM merek WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$idMerek]);
    return (int)$stmt->fetchColumn();
}

function produk_require_nama(string $field = 'nama', array &$errors = []): void
{
    if (trim($_POST[$field] ?? '') === '') {
        $errors[$field] = 'Nama wajib diisi.';
    } elseif (mb_strlen($_POST[$field]) > 150) {
        $errors[$field] = 'Nama maksimal 150 karakter.';
    }
}

$errors = [];

switch ($action) {
    case 'simpan_produk':
        $editId = (int)($_POST['edit_id'] ?? 0);
        produk_require_nama('nama', $errors);
        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old'] = ['nama' => $_POST['nama'] ?? ''];
            redirect('/produk?modal=produk' . ($editId ? '&edit_id=' . $editId : ''));
        }
        $nama = trim($_POST['nama']);
        $ts = now();

        if ($editId > 0) {
            $stmt = $db->prepare('SELECT * FROM produk WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$editId]);
            $produk = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$produk) {
                set_flash('error', 'Produk tidak ditemukan.');
                redirect('/produk');
            }
            $sebelum = ['nama' => $produk['nama']];
            $db->prepare('UPDATE produk SET nama = ?, updated_at = ? WHERE id = ?')->execute([$nama, $ts, $editId]);
            log_aktivitas('UPDATE', 'produk', $editId, "Perubahan nama produk menjadi \"{$nama}\"", ['sebelum' => $sebelum, 'sesudah' => ['nama' => $nama]]);
            set_flash('success', 'Data produk berhasil diperbarui.');
        } else {
            $db->prepare('INSERT INTO produk (nama, created_at, updated_at) VALUES (?, ?, ?)')->execute([$nama, $ts, $ts]);
            $newId = (int)$db->lastInsertId();
            log_aktivitas('CREATE', 'produk', $newId, "Produk baru \"{$nama}\" ditambahkan", ['sebelum' => [], 'sesudah' => ['nama' => $nama]]);
            set_flash('success', 'Data produk berhasil ditambahkan.');
        }
        redirect(produk_url());

    case 'hapus_produk':
        $produkId = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM produk WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$produkId]);
        $produk = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$produk) {
            set_flash('error', 'Produk tidak ditemukan.');
            redirect('/produk');
        }
        $ts = now();
        $db->beginTransaction();
        try {
            // Soft-delete ukuran milik merek produk ini
            $stmtMerek = $db->prepare('SELECT id FROM merek WHERE id_produk = ? AND deleted_at IS NULL');
            $stmtMerek->execute([$produkId]);
            $idsMerek = $stmtMerek->fetchAll(PDO::FETCH_COLUMN);
            if ($idsMerek) {
                $in = implode(',', array_fill(0, count($idsMerek), '?'));
                $db->prepare("UPDATE ukuran SET deleted_at = ?, updated_at = ? WHERE id_merek IN ({$in}) AND deleted_at IS NULL")->execute(array_merge([$ts, $ts], $idsMerek));
            }
            // Soft-delete merek
            $db->prepare('UPDATE merek SET deleted_at = ?, updated_at = ? WHERE id_produk = ? AND deleted_at IS NULL')->execute([$ts, $ts, $produkId]);
            // Soft-delete produk
            $db->prepare('UPDATE produk SET deleted_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL')->execute([$ts, $ts, $produkId]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            set_flash('error', 'Terjadi kesalahan saat menghapus produk.');
            redirect('/produk');
        }
        log_aktivitas('DELETE', 'produk', $produkId, "Produk \"{$produk['nama']}\" beserta merek & ukuran dihapus", null);
        set_flash('success', 'Produk beserta merek & ukurannya berhasil dihapus.');
        redirect('/produk');

    case 'simpan_merek':
        $editId = (int)($_POST['edit_id'] ?? 0);
        $idProduk = (int)($_POST['id_produk'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        if ($nama === '') {
            $_SESSION['form_errors'] = ['nama' => 'Nama wajib diisi.'];
            redirect('/produk?produk=' . $idProduk . '&modal=merek' . ($editId ? '&edit_id=' . $editId : ''));
        }
        $ts = now();

        if ($editId > 0) {
            $stmt = $db->prepare('SELECT * FROM merek WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$editId]);
            $merek = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$merek) {
                set_flash('error', 'Merek tidak ditemukan.');
                redirect('/produk');
            }
            $sebelum = ['merek' => $merek['merek']];
            $db->prepare('UPDATE merek SET merek = ?, updated_at = ? WHERE id = ?')->execute([$nama, $ts, $editId]);
            log_aktivitas('UPDATE', 'merek', $editId, "Perubahan merek menjadi \"{$nama}\"", ['sebelum' => $sebelum, 'sesudah' => ['merek' => $nama]]);
            set_flash('success', 'Data merek berhasil diperbarui.');
        } else {
            $db->prepare('INSERT INTO merek (id_produk, merek, created_at, updated_at) VALUES (?, ?, ?, ?)')->execute([$idProduk, $nama, $ts, $ts]);
            $newId = (int)$db->lastInsertId();
            log_aktivitas('CREATE', 'merek', $newId, "Merek baru \"{$nama}\" ditambahkan", ['sebelum' => [], 'sesudah' => ['merek' => $nama]]);
            set_flash('success', 'Data merek berhasil ditambahkan.');
        }
        redirect(produk_url($idProduk));

    case 'hapus_merek':
        $merekId = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM merek WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$merekId]);
        $merek = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$merek) {
            set_flash('error', 'Merek tidak ditemukan.');
            redirect('/produk');
        }
        $ts = now();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE ukuran SET deleted_at = ?, updated_at = ? WHERE id_merek = ? AND deleted_at IS NULL')->execute([$ts, $ts, $merekId]);
            $db->prepare('UPDATE merek SET deleted_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL')->execute([$ts, $ts, $merekId]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            set_flash('error', 'Terjadi kesalahan saat menghapus merek.');
            redirect('/produk');
        }
        log_aktivitas('DELETE', 'merek', $merekId, "Merek \"{$merek['merek']}\" beserta ukurannya dihapus", null);
        set_flash('success', 'Merek beserta ukurannya berhasil dihapus.');
        redirect(produk_url((int)$merek['id_produk']));

    case 'simpan_ukuran':
        $editId = (int)($_POST['edit_id'] ?? 0);
        $idMerek = (int)($_POST['id_merek'] ?? 0);
        $namaUkuran = trim($_POST['ukuran'] ?? '');
        $stok = (float)($_POST['stok'] ?? 0);
        $hargaModal = (float)(preg_replace('/[^\d]/', '', $_POST['harga_modal'] ?? '0'));
        $hargaJual = (float)(preg_replace('/[^\d]/', '', $_POST['harga_jual'] ?? '0'));

        $errors = [];
        if ($namaUkuran === '') {
            $errors['ukuran'] = 'Ukuran wajib diisi.';
        }
        if ($hargaModal < 0) {
            $errors['harga_modal'] = 'Harga modal tidak boleh negatif.';
        }
        if ($hargaJual < 0) {
            $errors['harga_jual'] = 'Harga jual tidak boleh negatif.';
        }
        if ($errors) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_old'] = ['ukuran' => $namaUkuran, 'stok' => (string)$stok, 'harga_modal' => (string)(int)$hargaModal, 'harga_jual' => (string)(int)$hargaJual];
            redirect(produk_url(merek_produk_id($idMerek), $idMerek, get_input('search'), (int)($_POST['page'] ?? 0)) . '&modal=ukuran' . ($editId ? '&edit_id=' . $editId : ''));
        }

        $ts = now();
        if ($editId > 0) {
            $stmt = $db->prepare('SELECT * FROM ukuran WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$editId]);
            $ukuran = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ukuran) {
                set_flash('error', 'Ukuran tidak ditemukan.');
                redirect('/produk');
            }
            $sebelum = ['ukuran' => $ukuran['ukuran'], 'stok' => (float)$ukuran['stok'], 'harga_modal' => (float)$ukuran['harga_modal'], 'harga_jual' => (float)$ukuran['harga_jual']];
            // Stok TIDAK diubah manual di sini; hanya via mutasi / verifikasi
            $db->prepare('UPDATE ukuran SET ukuran = ?, harga_modal = ?, harga_jual = ?, updated_at = ? WHERE id = ?')
                ->execute([$namaUkuran, $hargaModal, $hargaJual, $ts, $editId]);
            log_aktivitas('UPDATE', 'ukuran', $editId, "Perubahan data pada SKU \"{$namaUkuran}\"", ['sebelum' => $sebelum, 'sesudah' => $sebelum]);
            set_flash('success', 'Data ukuran berhasil diperbarui.');
        } else {
            $db->prepare('INSERT INTO ukuran (id_merek, ukuran, stok, harga_modal, harga_jual, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$idMerek, $namaUkuran, $stok, $hargaModal, $hargaJual, $ts, $ts]);
            $newId = (int)$db->lastInsertId();
            log_aktivitas('CREATE', 'ukuran', $newId, "Ukuran baru \"{$namaUkuran}\" ditambahkan", ['sebelum' => [], 'sesudah' => ['ukuran' => $namaUkuran, 'stok' => $stok, 'harga_modal' => $hargaModal, 'harga_jual' => $hargaJual]]);
            set_flash('success', 'Data ukuran berhasil ditambahkan.');
        }
        redirect(produk_url(merek_produk_id($idMerek), $idMerek, get_input('search'), (int)($_POST['page'] ?? 0)));

    case 'hapus_ukuran':
        $ukuranId = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM ukuran WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$ukuranId]);
        $ukuran = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ukuran) {
            set_flash('error', 'Ukuran tidak ditemukan.');
            redirect('/produk');
        }
        $ts = now();
        $db->prepare('UPDATE ukuran SET deleted_at = ?, updated_at = ? WHERE id = ?')->execute([$ts, $ts, $ukuranId]);
        log_aktivitas('DELETE', 'ukuran', $ukuranId, "Ukuran \"{$ukuran['ukuran']}\" dihapus", null);
        set_flash('success', 'Data ukuran berhasil dihapus.');
        redirect(produk_url(merek_produk_id((int)$ukuran['id_merek']), (int)$ukuran['id_merek'], get_input('search'), (int)($_POST['page'] ?? 0)));

    default:
        set_flash('error', 'Aksi tidak dikenali.');
        redirect('/produk');
}