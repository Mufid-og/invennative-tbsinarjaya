<?php
$headerTitle = 'Input Mutasi Stok';
$breadcrumb = 'Input Mutasi Stok';
$activeMenu = 'mutasi-stok-input';
$pageTitle = 'Input Mutasi Stok - Inventaris Toko Bangunan';

$db = db();
$jenis = $_SESSION['mutasi_jenis'] ?? '';
$step = $_GET['step'] ?? 'jenis';
$produkId = (int)($_GET['produk_id'] ?? 0);
$merekId = (int)($_GET['merek_id'] ?? 0);
$tanggal = $_SESSION['mutasi_tanggal'] ?? date('Y-m-d\TH:i');
$keterangan = $_SESSION['mutasi_keterangan'] ?? '';
$keranjang = $_SESSION['mutasi_keranjang'] ?? [];

$flash = get_flash();

$produkList = [];
if ($jenis === 'masuk') {
    $produkList = $db->query("SELECT p.* FROM produk p WHERE p.deleted_at IS NULL ORDER BY p.nama ASC")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($jenis === 'keluar') {
    $produkList = $db->query("SELECT DISTINCT p.* FROM produk p JOIN merek m ON m.id_produk = p.id AND m.deleted_at IS NULL JOIN ukuran u ON u.id_merek = m.id AND u.deleted_at IS NULL WHERE p.deleted_at IS NULL AND u.stok > 0 ORDER BY p.nama ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$merekList = [];
if ($jenis && $step === 'merek' && $produkId) {
    if ($jenis === 'masuk') {
        $stmt = $db->prepare("SELECT m.* FROM merek m WHERE m.id_produk = ? AND m.deleted_at IS NULL ORDER BY m.merek ASC");
    } else {
        $stmt = $db->prepare("SELECT DISTINCT m.* FROM merek m JOIN ukuran u ON u.id_merek = m.id AND u.deleted_at IS NULL WHERE m.id_produk = ? AND m.deleted_at IS NULL AND u.stok > 0 ORDER BY m.merek ASC");
    }
    $stmt->execute([$produkId]);
    $merekList = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$ukuranList = [];
if ($jenis && $step === 'ukuran' && $merekId) {
    $merekInfo = $db->prepare("SELECT m.merek, p.nama as produk_nama FROM merek m JOIN produk p ON p.id = m.id_produk WHERE m.id = ?");
    $merekInfo->execute([$merekId]);
    $merekInfo = $merekInfo->fetch(PDO::FETCH_ASSOC);

    if ($jenis === 'masuk') {
        $stmt = $db->prepare("SELECT u.* FROM ukuran u WHERE u.id_merek = ? AND u.deleted_at IS NULL ORDER BY CAST(u.ukuran AS REAL) ASC");
    } else {
        $stmt = $db->prepare("SELECT u.* FROM ukuran u WHERE u.id_merek = ? AND u.deleted_at IS NULL AND u.stok > 0 ORDER BY CAST(u.ukuran AS REAL) ASC");
    }
    $stmt->execute([$merekId]);
    $ukuranList = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

ob_start();
?>

<div class="space-y-6">
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

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-[#1a2332]">
                <i class="bi bi-arrow-left-right text-[#205295]"></i>
                Input Mutasi Stok
                <?php if ($jenis === 'masuk'): ?>
                    <span class="ml-2 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold"><i class="bi bi-arrow-down-circle"></i> Masuk</span>
                <?php elseif ($jenis === 'keluar'): ?>
                    <span class="ml-2 inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-red-50 text-red-700 text-xs font-semibold"><i class="bi bi-arrow-up-circle"></i> Keluar</span>
                <?php endif; ?>
            </h1>
        </div>
        <a href="/mutasi-stok" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-[#dce3ed] text-[#5a6a7a] text-[13px] font-medium rounded-xl hover:bg-[#f0f4f8] transition shadow-sm">
            <i class="bi bi-arrow-left"></i> Kembali ke Riwayat
        </a>
    </div>

    <?php if (!$jenis): ?>
        <!-- STEP 0: Pilih Jenis Mutasi -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-2xl mx-auto mt-10">
            <form method="POST" action="/mutasi-stok/input">
                <input type="hidden" name="action" value="set_jenis">
                <input type="hidden" name="jenis" value="masuk">
                <button type="submit" class="w-full group cursor-pointer">
                    <div class="bg-white rounded-2xl border-2 border-[#dce3ed] p-8 text-center hover:border-emerald-400 hover:shadow-lg transition-all duration-200 group-hover:bg-emerald-50/30">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-4 group-hover:bg-emerald-200 transition">
                            <i class="bi bi-arrow-down-circle text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-[#1a2332] mb-1">Barang Masuk</h3>
                        <p class="text-[13px] text-[#5a6a7a]">Input stok barang yang masuk / datang</p>
                    </div>
                </button>
            </form>
            <form method="POST" action="/mutasi-stok/input">
                <input type="hidden" name="action" value="set_jenis">
                <input type="hidden" name="jenis" value="keluar">
                <button type="submit" class="w-full group cursor-pointer">
                    <div class="bg-white rounded-2xl border-2 border-[#dce3ed] p-8 text-center hover:border-red-400 hover:shadow-lg transition-all duration-200 group-hover:bg-red-50/30">
                        <div class="w-16 h-16 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4 group-hover:bg-red-200 transition">
                            <i class="bi bi-arrow-up-circle text-3xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-[#1a2332] mb-1">Barang Keluar</h3>
                        <p class="text-[13px] text-[#5a6a7a]">Input stok barang yang keluar / terjual</p>
                    </div>
                </button>
            </form>
        </div>

    <?php else: ?>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- LEFT COLUMN: Steps -->
            <div class="flex-1 min-w-0">
                <!-- Step indicators -->
                <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] p-4 mb-6">
                    <div class="flex items-center gap-2 text-[13px]">
                        <?php
                        $steps = ['jenis' => 1, 'produk' => 2, 'merek' => 3, 'ukuran' => 4];
                        $currentStep = $steps[$step] ?? 1;
                        $stepLabels = ['jenis' => 'Jenis', 'produk' => 'Produk', 'merek' => 'Merek', 'ukuran' => 'Ukuran'];
                        foreach ($stepLabels as $sKey => $sLabel):
                            $sNum = $steps[$sKey];
                            $isActive = $sNum === $currentStep;
                            $isDone = $sNum < $currentStep;
                        ?>
                            <?php if ($sKey === 'jenis'): ?>
                                <a href="/mutasi-stok/input"
                                   class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg <?= $isActive ? 'bg-[#205295] text-white font-semibold' : ($isDone ? 'bg-emerald-100 text-emerald-700 font-medium' : 'bg-[#f0f4f8] text-[#5a6a7a]') ?>">
                                    <?php if ($isDone): ?><i class="bi bi-check-circle-fill text-xs"></i><?php else: ?><span class="w-5 h-5 rounded-full border <?= $isActive ? 'border-white' : 'border-[#c3cbd5]' ?> flex items-center justify-center text-[11px] font-bold"><?= $sNum ?></span><?php endif; ?>
                                    <?= $sLabel ?>
                                </a>
                            <?php else: ?>
                                <span class="text-[#c3cbd5]"><i class="bi bi-chevron-right"></i></span>
                                <?php
                                $href = '#';
                                if ($sKey === 'produk' && $jenis) $href = "/mutasi-stok/input?step=produk";
                                if ($sKey === 'merek' && $produkId) $href = "/mutasi-stok/input?step=merek&produk_id={$produkId}";
                                if ($sKey === 'ukuran' && $merekId) $href = "/mutasi-stok/input?step=ukuran&produk_id={$produkId}&merek_id={$merekId}";
                                ?>
                                <a href="<?= $href ?>"
                                   class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg <?= $isActive ? 'bg-[#205295] text-white font-semibold' : ($isDone ? 'bg-emerald-100 text-emerald-700 font-medium' : 'bg-[#f0f4f8] text-[#5a6a7a]') ?>">
                                    <?php if ($isDone): ?><i class="bi bi-check-circle-fill text-xs"></i><?php else: ?><span class="w-5 h-5 rounded-full border <?= $isActive ? 'border-white' : 'border-[#c3cbd5]' ?> flex items-center justify-center text-[11px] font-bold"><?= $sNum ?></span><?php endif; ?>
                                    <?= $sLabel ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step: Pilih Produk -->
                <?php if ($step === 'produk'): ?>
                    <div class="mb-3">
                        <form method="GET" action="/mutasi-stok/input" class="relative max-w-md">
                            <input type="hidden" name="step" value="produk">
                            <input type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Cari produk..."
                                   class="w-full pl-10 pr-4 py-2.5 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295] transition bg-[#f7faff]">
                            <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5a6a7a] text-sm"></i>
                        </form>
                    </div>
                    <?php
                    $qProduk = trim($_GET['q'] ?? '');
                    if ($qProduk !== '') {
                        $filteredProduk = array_values(array_filter($produkList, fn($p) => stripos($p['nama'], $qProduk) !== false));
                    } else {
                        $filteredProduk = $produkList;
                    }
                    ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php if (empty($filteredProduk)): ?>
                            <div class="col-span-full py-10 text-center text-[#5a6a7a]">
                                <i class="bi bi-inbox text-3xl block mb-2 text-[#c3cbd5]"></i>
                                Tidak ada produk ditemukan.
                            </div>
                        <?php else: ?>
                            <?php foreach ($filteredProduk as $p): ?>
                                <form method="POST" action="/mutasi-stok/input">
                                    <input type="hidden" name="action" value="pilih_produk">
                                    <input type="hidden" name="produk_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="w-full text-left group cursor-pointer">
                                        <div class="bg-white rounded-xl border border-[#dce3ed] p-4 hover:border-[#205295] hover:shadow-md transition-all duration-150 group-hover:bg-[#f0f6ff]">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-lg bg-[#eef4fb] flex items-center justify-center flex-shrink-0 group-hover:bg-[#d6e6fa]">
                                                    <i class="bi bi-box-seam text-[#205295]"></i>
                                                </div>
                                                <div>
                                                    <div class="font-semibold text-[#1a2332] text-sm group-hover:text-[#205295]"><?= e($p['nama']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                <!-- Step: Pilih Merek -->
                <?php elseif ($step === 'merek'): ?>
                    <div class="mb-3">
                        <form method="GET" action="/mutasi-stok/input" class="relative max-w-md">
                            <input type="hidden" name="step" value="merek">
                            <input type="hidden" name="produk_id" value="<?= $produkId ?>">
                            <input type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Cari merek..."
                                   class="w-full pl-10 pr-4 py-2.5 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295] transition bg-[#f7faff]">
                            <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5a6a7a] text-sm"></i>
                        </form>
                    </div>
                    <?php
                    $qMerek = trim($_GET['q'] ?? '');
                    if ($qMerek !== '') {
                        $filteredMerek = array_values(array_filter($merekList, fn($m) => stripos($m['merek'], $qMerek) !== false));
                    } else {
                        $filteredMerek = $merekList;
                    }
                    ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php if (empty($filteredMerek)): ?>
                            <div class="col-span-full py-10 text-center text-[#5a6a7a]">
                                <i class="bi bi-inbox text-3xl block mb-2 text-[#c3cbd5]"></i>
                                Tidak ada merek ditemukan.
                            </div>
                        <?php else: ?>
                            <?php foreach ($filteredMerek as $m): ?>
                                <form method="POST" action="/mutasi-stok/input">
                                    <input type="hidden" name="action" value="pilih_merek">
                                    <input type="hidden" name="merek_id" value="<?= $m['id'] ?>">
                                    <input type="hidden" name="produk_id" value="<?= $produkId ?>">
                                    <button type="submit" class="w-full text-left group cursor-pointer">
                                        <div class="bg-white rounded-xl border border-[#dce3ed] p-4 hover:border-[#205295] hover:shadow-md transition-all duration-150 group-hover:bg-[#f0f6ff]">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-lg bg-[#eef4fb] flex items-center justify-center flex-shrink-0 group-hover:bg-[#d6e6fa]">
                                                    <i class="bi bi-bookmark text-[#205295]"></i>
                                                </div>
                                                <div>
                                                    <div class="font-semibold text-[#1a2332] text-sm group-hover:text-[#205295]"><?= e($m['merek']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                <!-- Step: Pilih Ukuran -->
                <?php elseif ($step === 'ukuran'): ?>
                    <div class="mb-3">
                        <form method="GET" action="/mutasi-stok/input" class="relative max-w-md">
                            <input type="hidden" name="step" value="ukuran">
                            <input type="hidden" name="produk_id" value="<?= $produkId ?>">
                            <input type="hidden" name="merek_id" value="<?= $merekId ?>">
                            <input type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Cari ukuran..."
                                   class="w-full pl-10 pr-4 py-2.5 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295] transition bg-[#f7faff]">
                            <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5a6a7a] text-sm"></i>
                        </form>
                    </div>
                    <?php
                    $qUkuran = trim($_GET['q'] ?? '');
                    if ($qUkuran !== '') {
                        $filteredUkuran = array_values(array_filter($ukuranList, fn($u) => stripos($u['ukuran'], $qUkuran) !== false));
                    } else {
                        $filteredUkuran = $ukuranList;
                    }
                    ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php if (empty($filteredUkuran)): ?>
                            <div class="col-span-full py-10 text-center text-[#5a6a7a]">
                                <i class="bi bi-inbox text-3xl block mb-2 text-[#c3cbd5]"></i>
                                Tidak ada ukuran ditemukan.
                            </div>
                        <?php else: ?>
                            <?php foreach ($filteredUkuran as $u): ?>
                                <?php $inCart = isset($keranjang[$u['id']]); ?>
                                <div class="bg-white rounded-xl border <?= $inCart ? 'border-emerald-400 bg-emerald-50/30' : 'border-[#dce3ed]' ?> p-4 <?= $inCart ? '' : 'hover:border-[#205295] hover:shadow-md' ?> transition-all duration-150">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg <?= $inCart ? 'bg-emerald-100' : 'bg-[#eef4fb]' ?> flex items-center justify-center flex-shrink-0">
                                            <i class="bi <?= $inCart ? 'bi-check-circle-fill text-emerald-600' : 'bi-rulers text-[#205295]' ?>"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-semibold text-[#1a2332] text-sm">Ukuran <?= e($u['ukuran']) ?></div>
                                            <div class="text-[11px] text-[#5a6a7a]">Stok: <?= format_number($u['stok'], 0) ?></div>
                                        </div>
                                        <?php if (!$inCart): ?>
                                            <form method="POST" action="/mutasi-stok/input">
                                                <input type="hidden" name="action" value="tambah_keranjang">
                                                <input type="hidden" name="ukuran_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="produk_id" value="<?= $produkId ?>">
                                                <input type="hidden" name="merek_id" value="<?= $merekId ?>">
                                                <input type="hidden" name="harga_modal" value="<?= $u['harga_modal'] ?>">
                                                <input type="hidden" name="harga_jual" value="<?= $u['harga_jual'] ?>">
                                                <button type="submit" class="px-3 py-1.5 bg-[#205295] text-white text-[12px] font-semibold rounded-lg hover:bg-[#183d72] transition whitespace-nowrap">
                                                    <i class="bi bi-plus-lg"></i> Tambah
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="px-3 py-1.5 bg-emerald-100 text-emerald-700 text-[12px] font-semibold rounded-lg whitespace-nowrap">
                                                <i class="bi bi-check-lg"></i> Terpilih
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT COLUMN: Keranjang -->
            <div class="w-full lg:w-[380px] flex-shrink-0">
                <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] sticky top-20">
                    <div class="px-5 py-4 border-b border-[#dce3ed] flex items-center justify-between">
                        <h3 class="text-[15px] font-semibold">
                            <i class="bi bi-cart3 text-[#205295]"></i> Keranjang
                            <?php if (!empty($keranjang)): ?>
                                <span class="ml-1.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-[#205295] text-white text-[11px] font-bold"><?= count($keranjang) ?></span>
                            <?php endif; ?>
                        </h3>
                        <?php if (!empty($keranjang)): ?>
                            <form method="POST" action="/mutasi-stok/input" class="inline">
                                <input type="hidden" name="action" value="kosongkan_keranjang">
                                <button type="submit" class="text-[12px] text-[#c0392b] hover:text-red-700 font-medium transition" onclick="return confirm('Kosongkan semua item dari keranjang?')">
                                    <i class="bi bi-trash3"></i> Kosongkan
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="/mutasi-stok/input" id="formKeranjang">
                        <input type="hidden" name="action" value="simpan">

                        <div class="p-5 space-y-4">
                            <!-- Tanggal -->
                            <div>
                                <label class="block text-[13px] font-medium text-[#5a6a7a] mb-1">Tanggal &amp; Waktu</label>
                                <input type="datetime-local" name="tanggal" value="<?= e($tanggal) ?>"
                                       class="w-full px-3 py-2 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295] transition">
                            </div>

                            <!-- Keterangan -->
                            <div>
                                <label class="block text-[13px] font-medium text-[#5a6a7a] mb-1">Keterangan</label>
                                <textarea name="keterangan" rows="2" placeholder="Catatan opsional..."
                                          class="w-full px-3 py-2 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295] transition resize-none"><?= e($keterangan) ?></textarea>
                            </div>

                            <!-- Cart Items -->
                            <?php if (empty($keranjang)): ?>
                                <div class="py-8 text-center text-[#5a6a7a]">
                                    <i class="bi bi-cart-x text-3xl block mb-2 text-[#c3cbd5]"></i>
                                    <p class="text-[13px]">Belum ada item di keranjang.</p>
                                    <p class="text-[11px] text-[#aab4c0] mt-1">Pilih ukuran barang terlebih dahulu.</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-3 max-h-[350px] overflow-y-auto pr-1" id="cartItems">
                                    <?php foreach ($keranjang as $ukId => $item): ?>
                                        <?php
                                        $itemDetail = $db->prepare("SELECT u.ukuran, u.stok, m.merek, p.nama as produk_nama FROM ukuran u JOIN merek m ON m.id = u.id_merek JOIN produk p ON p.id = m.id_produk WHERE u.id = ?");
                                        $itemDetail->execute([$ukId]);
                                        $itemDetail = $itemDetail->fetch(PDO::FETCH_ASSOC);
                                        ?>
                                        <div class="bg-[#f7faff] rounded-xl border border-[#e4e9f0] p-3">
                                            <div class="flex items-start justify-between gap-2 mb-2">
                                                <div class="min-w-0">
                                                    <div class="text-[13px] font-semibold text-[#1a2332] truncate"><?= e($itemDetail['produk_nama']) ?></div>
                                                    <div class="text-[11px] text-[#5a6a7a]"><?= e($itemDetail['merek']) ?> &middot; Uk. <?= e($itemDetail['ukuran']) ?> &middot; Stok: <?= format_number($itemDetail['stok'], 0) ?></div>
                                                </div>
                                                <form method="POST" action="/mutasi-stok/input" class="flex-shrink-0">
                                                    <input type="hidden" name="action" value="hapus_keranjang">
                                                    <input type="hidden" name="ukuran_id" value="<?= $ukId ?>">
                                                    <input type="hidden" name="produk_id" value="<?= $produkId ?>">
                                                    <input type="hidden" name="merek_id" value="<?= $merekId ?>">
                                                    <button type="submit" class="text-[#c0392b] hover:text-red-700 transition text-sm" title="Hapus">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="grid grid-cols-4 gap-2 items-end">
                                                <div class="col-span-1">
                                                    <label class="block text-[10px] text-[#5a6a7a] mb-0.5">Qty</label>
                                                    <input type="number" name="qty_<?= $ukId ?>" value="<?= e((string)(int)$item['qty']) ?>" min="0"
                                                           class="w-full px-2 py-1.5 text-[13px] text-center border border-[#dce3ed] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295] cart-qty" data-ukid="<?= $ukId ?>">
                                                </div>
                                                <div class="col-span-3">
                                                    <label class="block text-[10px] text-[#5a6a7a] mb-0.5">Harga Modal</label>
                                                    <div class="relative">
                                                        <input type="text" inputmode="numeric" data-rupiah
                                                               name="harga_modal_<?= $ukId ?>"
                                                               value="<?= e(format_rupiah($item['hargaModal'])) ?>"
                                                               class="w-full px-2 py-1.5 text-[13px] border border-[#dce3ed] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295]">
                                                        <input type="hidden" name="harga_modal_raw_<?= $ukId ?>" data-rupiah-sync="hargaModal"
                                                               value="<?= e((string)(int)$item['hargaModal']) ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <label class="block text-[10px] text-[#5a6a7a] mb-0.5">Harga Jual</label>
                                                <div class="relative">
                                                    <input type="text" inputmode="numeric" data-rupiah
                                                           name="harga_jual_<?= $ukId ?>"
                                                           value="<?= e(format_rupiah($item['hargaJual'])) ?>"
                                                           class="w-full px-2 py-1.5 text-[13px] border border-[#dce3ed] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295]">
                                                    <input type="hidden" name="harga_jual_raw_<?= $ukId ?>" data-rupiah-sync="hargaJual"
                                                           value="<?= e((string)(int)$item['hargaJual']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($keranjang)): ?>
                            <div class="px-5 pb-5">
                                <button type="submit"
                                        class="w-full py-3 bg-[#205295] text-white text-[14px] font-bold rounded-xl hover:bg-[#183d72] transition shadow-sm flex items-center justify-center gap-2">
                                    <i class="bi bi-check-circle"></i> Simpan Mutasi Stok
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var key = 'mutasi_draft_' + <?= json_encode($jenis) ?>;
    if (<?= json_encode($jenis) ?>) {
        try {
            var draft = JSON.parse(localStorage.getItem(key) || '{}');
            if (draft.tanggal) {
                var tglInput = document.querySelector('input[name="tanggal"]');
                if (tglInput) tglInput.value = draft.tanggal;
            }
            if (draft.keterangan) {
                var ketInput = document.querySelector('textarea[name="keterangan"]');
                if (ketInput) ketInput.value = draft.keterangan;
            }
        } catch(e) {}
    }

    document.querySelectorAll('.cart-qty').forEach(function(input) {
        input.addEventListener('change', function() {
            var ukid = this.getAttribute('data-ukid');
            var val = parseInt(this.value) || 0;
            if (val < 0) this.value = 0;
            document.querySelectorAll('.cart-qty').forEach(function(qi) {
                qi.addEventListener('input', saveDraft);
            });
        });
    });

    function saveDraft() {
        if (!<?= json_encode($jenis) ?>) return;
        var draft = {};
        var tgl = document.querySelector('input[name="tanggal"]');
        var ket = document.querySelector('textarea[name="keterangan"]');
        if (tgl) draft.tanggal = tgl.value;
        if (ket) draft.keterangan = ket.value;
        try { localStorage.setItem(key, JSON.stringify(draft)); } catch(e) {}
    }

    document.querySelectorAll('input[name="tanggal"], textarea[name="keterangan"]').forEach(function(el) {
        el.addEventListener('input', saveDraft);
        el.addEventListener('change', saveDraft);
    });
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/app.php';
