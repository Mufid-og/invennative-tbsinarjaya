<?php
$headerTitle = 'Verifikasi Stok';
$breadcrumb = 'Input Verifikasi Stok';
$activeMenu = 'verifikasi-stok-input';
$pageTitle = 'Input Verifikasi Stok - Inventaris Toko Bangunan';

$db = db();
$flash = get_flash();

if (!isset($_SESSION['verifikasi_keranjang'])) {
    $_SESSION['verifikasi_keranjang'] = [];
}
if (!isset($_SESSION['verifikasi_step'])) {
    $_SESSION['verifikasi_step'] = ['produk_id' => null, 'merek_id' => null];
}

// --- Baca param navigasi dari URL (GET), sinkronkan ke session ---
if (isset($_GET['step']) && $_GET['step'] === 'produk') {
    $_SESSION['verifikasi_step'] = ['produk_id' => null, 'merek_id' => null];
} elseif (isset($_GET['step']) && $_GET['step'] === 'merek') {
    $_SESSION['verifikasi_step']['merek_id'] = null;
}
if (isset($_GET['produk_id'])) {
    $_SESSION['verifikasi_step']['produk_id'] = (int)$_GET['produk_id'];
}
if (isset($_GET['merek_id'])) {
    $_SESSION['verifikasi_step']['merek_id'] = (int)$_GET['merek_id'];
}

$step = &$_SESSION['verifikasi_step'];
$keranjang = &$_SESSION['verifikasi_keranjang'];

$search = trim($_GET['search'] ?? '');

require_once __DIR__ . '/../includes/search_helper.php';

$currentStep = 'produk';
if (!empty($step['produk_id']) && empty($step['merek_id'])) {
    $currentStep = 'merek';
} elseif (!empty($step['merek_id'])) {
    $currentStep = 'ukuran';
}

// Produk cards (dipaginasi 100/halaman + search SQL agar tidak tarik semua produk)
$produkSearch = $currentStep === 'produk' ? $search : '';
$produkPerHalaman = 100;

if ($produkSearch !== '' && search_min_length($produkSearch)) {
    // Gunakan FTS5 / prefix search
    [$produkSearchSql, $produkSearchParams] = fts_search_produk($produkSearch);
    $countP = $db->prepare('SELECT COUNT(*) FROM produk p WHERE p.deleted_at IS NULL AND ' . $produkSearchSql);
    $countP->execute($produkSearchParams);
    $produkTotal = (int)$countP->fetchColumn();
} else {
    $produkTotal = (int)$db->query('SELECT COUNT(*) FROM produk WHERE deleted_at IS NULL')->fetchColumn();
    $produkSearchSql = null;
    $produkSearchParams = [];
}
$produkTotalPages = max(1, (int)ceil($produkTotal / $produkPerHalaman));
$produkHalaman = max(1, min((int)($_GET['halaman'] ?? 1), $produkTotalPages));
$produkOffset = ($produkHalaman - 1) * $produkPerHalaman;

if ($produkSearchSql !== null) {
    // Ada search: pakai FTS / prefix
    // LEFT JOIN + COUNT(m.id) mengganti correlated subquery COUNT(*) per baris
    // — filter deleted_at di ON clause agar produk tanpa merek aktif tetap muncul (COUNT=0)
    $produkStmt = $db->prepare(
        'SELECT p.*, COUNT(m.id) AS merek_count
         FROM produk p
         LEFT JOIN merek m ON m.id_produk = p.id AND m.deleted_at IS NULL
         WHERE p.deleted_at IS NULL AND ' . $produkSearchSql .
        ' GROUP BY p.id ORDER BY p.nama LIMIT ? OFFSET ?'
    );
    $produkStmt->execute(array_merge($produkSearchParams, [$produkPerHalaman, $produkOffset]));
} else {
    // prepare sekali, execute sekali — SQL tidak bervariasi di branch ini
    // LEFT JOIN + COUNT(m.id) mengganti correlated subquery COUNT(*) per baris
    $produkStmt = $db->prepare(
        'SELECT p.*, COUNT(m.id) AS merek_count
         FROM produk p
         LEFT JOIN merek m ON m.id_produk = p.id AND m.deleted_at IS NULL
         WHERE p.deleted_at IS NULL
         GROUP BY p.id ORDER BY p.nama LIMIT ? OFFSET ?'
    );
    $produkStmt->execute([$produkPerHalaman, $produkOffset]);
}
$produkCards = $produkStmt->fetchAll(PDO::FETCH_ASSOC);

$produkTerpilih = null;
if (!empty($step['produk_id'])) {
    $stmtP = $db->prepare('SELECT * FROM produk WHERE id = ? AND deleted_at IS NULL');
    $stmtP->execute([$step['produk_id']]);
    $produkTerpilih = $stmtP->fetch(PDO::FETCH_ASSOC);
    if (!$produkTerpilih) {
        $_SESSION['verifikasi_step'] = ['produk_id' => null, 'merek_id' => null];
        $step = &$_SESSION['verifikasi_step'];
        $currentStep = 'produk';
    }
}

$merekCards = [];
if (in_array($currentStep, ['merek', 'ukuran'])) {
    $merekSearch = $currentStep === 'merek' ? $search : '';
    $merekWhere = 'm.id_produk = ? AND m.deleted_at IS NULL';
    $merekParams = [(int)$step['produk_id']];
    if ($merekSearch !== '' && search_min_length($merekSearch)) {
        [$merekSearchSql, $merekSearchParams] = fts_search_merek($merekSearch, (int)$step['produk_id']);
        $merekWhere .= ' AND ' . $merekSearchSql;
        $merekParams = array_merge($merekParams, $merekSearchParams);
    }
    // LEFT JOIN + COUNT(u.id) mengganti correlated subquery COUNT(*) per baris merek
    // — filter deleted_at di ON clause agar merek tanpa ukuran aktif tetap muncul (COUNT=0)
    $merekSql = 'SELECT m.*, COUNT(u.id) AS ukuran_count
                 FROM merek m
                 LEFT JOIN ukuran u ON u.id_merek = m.id AND u.deleted_at IS NULL
                 WHERE ' . $merekWhere . ' GROUP BY m.id ORDER BY m.merek';
    $merekStmt = $db->prepare($merekSql);
    $merekStmt->execute($merekParams);
    $merekCards = $merekStmt->fetchAll(PDO::FETCH_ASSOC);
}

$merekTerpilih = null;
if (!empty($step['merek_id'])) {
    $stmtM = $db->prepare('SELECT * FROM merek WHERE id = ? AND deleted_at IS NULL');
    $stmtM->execute([$step['merek_id']]);
    $merekTerpilih = $stmtM->fetch(PDO::FETCH_ASSOC);
    if (!$merekTerpilih) {
        $_SESSION['verifikasi_step']['merek_id'] = null;
        $step = &$_SESSION['verifikasi_step'];
        $currentStep = 'merek';
    }
}

$ukuranCards = [];
if ($currentStep === 'ukuran') {
    $ukuranSearch = $search;
    $ukuranWhere = 'id_merek = ? AND deleted_at IS NULL';
    $ukuranParams = [(int)$step['merek_id']];
    if ($ukuranSearch !== '' && search_min_length($ukuranSearch)) {
        [$ukuranSearchSql, $ukuranSearchParams] = fts_search_ukuran($ukuranSearch, (int)$step['merek_id']);
        // Untuk prefix LIKE fallback, alias 'u' tidak tersedia — sesuaikan
        // Ganti alias 'u.' jika ada (fts_search_ukuran menghasilkan "u.id IN ..." atau "u.ukuran LIKE ...")
        $ukuranSearchSqlNoAlias = str_replace('u.id IN ', 'id IN ', str_replace('u.ukuran LIKE', 'ukuran LIKE', $ukuranSearchSql));
        $ukuranWhere .= ' AND ' . $ukuranSearchSqlNoAlias;
        $ukuranParams = array_merge($ukuranParams, $ukuranSearchParams);
    }
    $ukuranSql = 'SELECT * FROM ukuran WHERE ' . $ukuranWhere . ' ORDER BY ukuran';
    $ukuranStmt = $db->prepare($ukuranSql);
    $ukuranStmt->execute($ukuranParams);
    $ukuranCards = $ukuranStmt->fetchAll(PDO::FETCH_ASSOC);
}

$detailKeranjang = [];
if (!empty($keranjang)) {
    // ── Batch load: 1 query WHERE IN mengganti N SELECT per item keranjang ──
    $keranjangIds = array_keys($keranjang);
    $kPh          = implode(',', array_fill(0, count($keranjangIds), '?'));
    $kStmt        = $db->prepare(
        "SELECT u.*, m.merek, p.nama AS produk_nama
         FROM ukuran u
         JOIN merek m ON m.id = u.id_merek
         JOIN produk p ON p.id = m.id_produk
         WHERE u.id IN ({$kPh})"
    );
    $kStmt->execute($keranjangIds);
    $ukuranRows = [];
    while ($r = $kStmt->fetch(PDO::FETCH_ASSOC)) {
        $ukuranRows[(int)$r['id']] = $r;
    }

    foreach ($keranjang as $ukuranId => $item) {
        $ukuranRow = $ukuranRows[(int)$ukuranId] ?? null;
        if (!$ukuranRow) continue;
        $stokSistem = (float)$ukuranRow['stok'];
        $stokFisik  = isset($item['stokFisik']) ? (float)$item['stokFisik'] : $stokSistem;
        $detailKeranjang[] = [
            'id'         => $ukuranId,
            'nama'       => $ukuranRow['produk_nama'] . ' ' . $ukuranRow['merek'] . ' — ' . $ukuranRow['ukuran'],
            'stokSistem' => $stokSistem,
            'stokFisik'  => $stokFisik,
            'selisih'    => $stokFisik - $stokSistem,
        ];
    }
}

$totalItem = count($detailKeranjang);
$tanggalOld = $_SESSION['verifikasi_tanggal'] ?? date('Y-m-d\TH:i');
$keteranganOld = $_SESSION['verifikasi_keterangan'] ?? '';

ob_start();
?>

<div data-verifikasi-input>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-5">
            <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-[#e1edfa] text-[#205295] flex items-center justify-center text-xl">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <div>
                            <div class="text-[15px] font-semibold">Verifikasi Stok</div>
                            <div class="text-[13px] text-[#5a6a7a]">Pilih produk lalu merek, kemudian ukuran untuk verifikasi fisik.</div>
                        </div>
                    </div>
                    <a href="/verifikasi-stok"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] hover:text-[#205295] hover:border-[#205295] text-xs transition">
                        <i class="bi bi-list-ul"></i> Riwayat
                    </a>
                </div>
            </div>

            <form method="GET" action="/verifikasi-stok/input" class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-[#5a6a7a] z-10"></i>
                <input type="text" name="search" value="<?= e($search) ?>"
                       placeholder="Cari <?= $currentStep === 'ukuran' ? 'ukuran' : ($currentStep === 'merek' ? 'merek' : 'produk') ?>..."
                       minlength="2"
                       title="Ketik minimal 2 karakter untuk mencari"
                       class="w-full pl-10 pr-10 py-2.5 border border-[#c9cbd3] rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#2c74b3]">
                <?php if ($search !== ''): ?>
                    <a href="/verifikasi-stok/input?<?= $currentStep === 'merek' ? 'step=merek&produk_id=' . (int)$step['produk_id'] : ($currentStep === 'ukuran' ? 'step=ukuran&produk_id=' . (int)$step['produk_id'] . '&merek_id=' . (int)$step['merek_id'] : '') ?>"
                       class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#5a6a7a] hover:text-[#c0392b] text-sm">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </form>

            <div class="flex flex-wrap items-center gap-2 text-sm">
                <a href="/verifikasi-stok/input?step=produk"
                   class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-[#dce3ed] bg-white text-[#5a6a7a] hover:text-[#2c74b3] hover:border-[#2c74b3] transition">
                    <i class="bi bi-box-seam"></i> Produk
                </a>

                <?php if ($produkTerpilih): ?>
                    <i class="bi bi-chevron-right text-[#c3cbd5]"></i>
                    <?php if ($currentStep === 'merek'): ?>
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#e1edfa] text-[#144272] font-medium"><?= e($produkTerpilih['nama']) ?></span>
                    <?php else: ?>
                        <a href="/verifikasi-stok/input?step=merek&produk_id=<?= (int)$step['produk_id'] ?>"
                           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#e1edfa] text-[#144272] font-medium transition hover:opacity-80">
                            <?= e($produkTerpilih['nama']) ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($merekTerpilih): ?>
                    <i class="bi bi-chevron-right text-[#c3cbd5]"></i>
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#205295] text-white font-medium">
                        <i class="bi bi-tag"></i> <?= e($merekTerpilih['merek']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($flash): ?>
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                     x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium shadow-sm
                            <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                    <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill text-emerald-500' : 'bi-exclamation-circle-fill text-red-500' ?>"></i>
                    <?= e($flash['message']) ?>
                    <button @click="show = false" class="ml-auto opacity-50 hover:opacity-100"><i class="bi bi-x-lg"></i></button>
                </div>
            <?php endif; ?>

            <?php if ($currentStep === 'produk'): ?>
                <div>
                    <h3 class="text-[15px] font-semibold mb-3"><i class="bi bi-box-seam text-[#205295]"></i> Pilih Produk</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php if (empty($produkCards)): ?>
                            <div class="col-span-full text-center py-8 text-[#5a6a7a]">Belum ada produk. Tambahkan lewat Manajemen Produk.</div>
                        <?php else: ?>
                            <?php foreach ($produkCards as $p): ?>
                                <a href="/verifikasi-stok/input?step=merek&produk_id=<?= (int)$p['id'] ?>"
                                   class="flex items-center gap-4 rounded-xl border border-[#dce3ed] bg-white p-4 shadow-sm hover:border-[#205295] hover:shadow-md transition-all text-left">
                                    <div class="w-11 h-11 rounded-lg bg-[#e1edfa] text-[#205295] flex items-center justify-center text-lg shrink-0"><i class="bi bi-box-seam"></i></div>
                                    <div class="min-w-0">
                                        <div class="font-medium truncate"><?= e($p['nama']) ?></div>
                                        <div class="text-xs text-[#5a6a7a]"><?= (int)$p['merek_count'] ?> merek</div>
                                    </div>
                                    <i class="bi bi-chevron-right ml-auto text-[#c3cbd5]"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($produkTotalPages > 1): ?>
                        <div class="mt-4 flex items-center justify-between gap-3 text-[13px] text-[#5a6a7a]">
                            <span>Menampilkan <?= $produkTotal > 0 ? (($produkHalaman - 1) * $produkPerHalaman + 1) : 0 ?>–<?= min($produkHalaman * $produkPerHalaman, $produkTotal) ?> dari <?= $produkTotal ?> produk</span>
                            <span class="flex items-center gap-2">
                                <?php if ($produkHalaman > 1): ?>
                                    <a href="/verifikasi-stok/input?step=produk&search=<?= e(urlencode($search)) ?>&halaman=<?= $produkHalaman - 1 ?>"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-[#dce3ed] bg-white hover:border-[#2c74b3] hover:text-[#2c74b3] transition">
                                        <i class="bi bi-chevron-left"></i> Sebelumnya
                                    </a>
                                <?php endif; ?>
                                <?php if ($produkHalaman < $produkTotalPages): ?>
                                    <a href="/verifikasi-stok/input?step=produk&search=<?= e(urlencode($search)) ?>&halaman=<?= $produkHalaman + 1 ?>"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-[#dce3ed] bg-white hover:border-[#2c74b3] hover:text-[#2c74b3] transition">
                                        Selanjutnya <i class="bi bi-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($currentStep === 'merek'): ?>
                <div>
                    <h3 class="text-[15px] font-semibold mb-3"><i class="bi bi-tags text-[#205295]"></i> Pilih Merek — <?= e($produkTerpilih['nama']) ?></h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php if (empty($merekCards)): ?>
                            <div class="col-span-full text-center py-8 text-[#5a6a7a]">Belum ada merek untuk produk ini.</div>
                        <?php else: ?>
                            <?php foreach ($merekCards as $m): ?>
                                <a href="/verifikasi-stok/input?step=ukuran&produk_id=<?= (int)$step['produk_id'] ?>&merek_id=<?= (int)$m['id'] ?>"
                                   class="flex items-center gap-4 rounded-xl border border-[#dce3ed] bg-white p-4 shadow-sm hover:border-[#205295] hover:shadow-md transition-all text-left">
                                    <div class="w-11 h-11 rounded-lg bg-[#e1edfa] text-[#205295] flex items-center justify-center text-lg shrink-0"><i class="bi bi-tag"></i></div>
                                    <div class="min-w-0">
                                        <div class="font-medium truncate"><?= e($m['merek']) ?></div>
                                        <div class="text-xs text-[#5a6a7a]"><?= (int)$m['ukuran_count'] ?> ukuran</div>
                                    </div>
                                    <i class="bi bi-chevron-right ml-auto text-[#c3cbd5]"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div>
                    <h3 class="text-[15px] font-semibold mb-3"><i class="bi bi-rulers text-[#205295]"></i> Pilih Ukuran — <?= e($merekTerpilih['merek']) ?></h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php if (empty($ukuranCards)): ?>
                            <div class="col-span-full text-center py-8 text-[#5a6a7a]">Belum ada ukuran pada merek ini.</div>
                        <?php else: ?>
                            <?php foreach ($ukuranCards as $u): ?>
                                <?php $sudahDiKeranjang = isset($keranjang[$u['id']]); ?>
                                <?php if ($sudahDiKeranjang): ?>
                                    <div class="flex items-center gap-4 rounded-xl border border-[#1a7a3a] bg-[#f0faf2] p-4 shadow-sm">
                                <?php else: ?>
                                    <form method="POST" action="/verifikasi-stok/input"
                                          class="flex items-center gap-4 rounded-xl border border-[#dce3ed] bg-white p-4 shadow-sm hover:border-[#205295] hover:shadow-md transition-all text-left">
                                      <input type="hidden" name="action" value="tambah_keranjang">
                                      <input type="hidden" name="ukuran_id" value="<?= (int)$u['id'] ?>">
                                      <button type="submit" class="flex items-center gap-4 text-left w-full cursor-pointer">
                                <?php endif; ?>
                                    <div class="w-11 h-11 rounded-lg bg-[#e1edfa] text-[#205295] flex items-center justify-center text-lg shrink-0"><i class="bi bi-rulers"></i></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium"><?= e($u['ukuran']) ?></div>
                                        <div class="text-xs text-[#5a6a7a]">Stok sistem: <span class="text-[#205295] font-medium"><?= format_qty((float)$u['stok']) ?></span></div>
                                    </div>
                                    <?php if ($sudahDiKeranjang): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#1a7a3a] text-white text-xs font-semibold"><i class="bi bi-check-lg"></i> Di keranjang</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#dff5e4] text-[#1a7a3a] text-xs font-semibold"><i class="bi bi-plus-lg"></i> Verifikasi</span>
                                    <?php endif; ?>
                                <?php if ($sudahDiKeranjang): ?>
                                    </div>
                                <?php else: ?>
                                      </button>
                                    </form>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] p-5 lg:sticky lg:top-20">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-[15px] font-semibold"><i class="bi bi-clipboard-check text-[#205295]"></i> Daftar Verifikasi
                        <span class="ml-1 text-xs font-bold text-white bg-[#2c74b3] rounded-full px-2 py-0.5"><?= $totalItem ?></span>
                    </h3>
                    <?php if ($totalItem > 0): ?>
                        <form method="POST" action="/verifikasi-stok/input" style="display:inline">
                            <input type="hidden" name="action" value="kosongkan_keranjang">
                            <button type="submit" class="text-[13px] text-[#c0392b] hover:underline cursor-pointer">Kosongkan</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="space-y-4 max-h-[55vh] overflow-y-auto pr-1">
                    <?php if (empty($detailKeranjang)): ?>
                        <div class="text-center py-8 text-[#5a6a7a] text-[13px]">
                            <i class="bi bi-clipboard-check text-3xl block mb-2 text-[#c3cbd5]"></i>
                            Daftar verifikasi masih kosong.<br>Pilih ukuran produk untuk menambahkan.
                        </div>
                    <?php else: ?>
                        <?php foreach ($detailKeranjang as $item): ?>
                            <div class="rounded-xl p-3.5 border-l-4 border-l-[#205295] border border-[#bcd4f0] bg-[#eaf3fc]">
                                <div class="flex items-start justify-between gap-2 mb-2.5">
                                    <div class="min-w-0">
                                        <div class="text-[13px] font-semibold text-[#144272] leading-snug"><?= e($item['nama']) ?></div>
                                        <div class="text-[11px] font-medium text-[#5a6a7a]">Stok sistem: <?= format_qty((float)$item['stokSistem']) ?></div>
                                    </div>
                                    <form method="POST" action="/verifikasi-stok/input" style="display:inline">
                                        <input type="hidden" name="action" value="hapus_keranjang">
                                        <input type="hidden" name="ukuran_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="text-[#c0392b] hover:text-[#a02820] cursor-pointer shrink-0"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                                <div class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <label class="block text-[11px] text-[#5a6a7a] mb-1">Stok Fisik</label>
                                        <form method="POST" action="/verifikasi-stok/input" style="display:inline">
                                            <input type="hidden" name="action" value="update_stok_fisik">
                                            <input type="hidden" name="ukuran_id" value="<?= $item['id'] ?>">
                                            <input type="text" inputmode="decimal" data-qty name="stok_fisik"
                                                   value="<?= format_qty((float)$item['stokFisik']) ?>"
                                                   data-stok-fisik-verifikasi data-sistem="<?= $item['stokSistem'] ?>" data-target="selisih-<?= $item['id'] ?>"
                                                   onchange="this.form.submit()"
                                                   class="w-full px-2 py-1.5 bg-white border border-[#c9cbd3] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2c74b3]">
                                        </form>
                                    </div>
                                    <span data-selisih-verifikasi id="selisih-<?= $item['id'] ?>"
                                          class="px-2.5 py-1.5 rounded-lg text-xs font-semibold mb-0.5 whitespace-nowrap
                                          <?= abs((float)$item['selisih']) <= 0.005 ? 'bg-[#dff5e4] text-[#1a7a3a]' : 'bg-[#fde8e8] text-[#c0392b]' ?>">
                                        <?php if (abs((float)$item['selisih']) <= 0.005): ?>
                                            Sesuai
                                        <?php else: ?>
                                            <?= $item['selisih'] > 0 ? '+' : '' ?><?= format_qty((float)$item['selisih']) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($totalItem > 0): ?>
                    <form method="POST" action="/verifikasi-stok/input" class="mt-5 space-y-4 border-t border-[#dce3ed] pt-4">
                        <input type="hidden" name="action" value="simpan">
                        <div>
                            <label class="block text-[13px] font-medium text-[#1a2332] mb-1.5">Tanggal</label>
                            <input type="datetime-local" name="tanggal" value="<?= e($tanggalOld) ?>"
                                   class="w-full px-3 py-2.5 border border-[#c9cbd3] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2c74b3]">
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-[#1a2332] mb-1.5">Keterangan</label>
                            <textarea name="keterangan" rows="2" placeholder="mis: Hasil opname fisik gudang"
                                      class="w-full px-3 py-2.5 border border-[#c9cbd3] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2c74b3]"><?= e($keteranganOld) ?></textarea>
                        </div>
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-[#205295] hover:bg-[#144272] text-white text-sm font-semibold transition cursor-pointer">
                            <i class="bi bi-check-lg"></i> Simpan Verifikasi
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function recomputeSelisih(inp) {
    const sistem = parseFloat(inp.dataset.sistem) || 0;
    const fisik = window.parseQtyValue(inp.value);
    const el = document.getElementById(inp.dataset.target);
    if (isNaN(fisik) || !el) return;
    const sel = fisik - sistem;
    if (Math.abs(sel) <= 0.005) {
        el.textContent = 'Sesuai';
        el.classList.remove('bg-[#fde8e8]', 'text-[#c0392b]');
        el.classList.add('bg-[#dff5e4]', 'text-[#1a7a3a]');
    } else {
        el.textContent = (sel > 0 ? '+' : '') + sel.toLocaleString('id-ID', { maximumFractionDigits: 2 });
        el.classList.remove('bg-[#dff5e4]', 'text-[#1a7a3a]');
        el.classList.add('bg-[#fde8e8]', 'text-[#c0392b]');
    }
}

document.addEventListener('input', function (e) {
    const inp = e.target.closest('[data-stok-fisik-verifikasi]');
    if (!inp) return;
    setTimeout(function () { recomputeSelisih(inp); }, 0);
});
document.addEventListener('change', function (e) {
    const inp = e.target.closest('[data-stok-fisik-verifikasi]');
    if (!inp) return;
    recomputeSelisih(inp);
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/app.php';