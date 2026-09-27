<?php
$headerTitle = 'Detail Verifikasi Stok';
$breadcrumb = 'Verifikasi Stok';
$activeMenu = 'verifikasi-stok';
$pageTitle = 'Detail Verifikasi Stok - Inventaris Toko Bangunan';

function format_tanggal($dateStr) {
    $months = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    $dt = new DateTime($dateStr);
    return $dt->format('d') . ' ' . $months[(int)$dt->format('m')] . ' ' . $dt->format('Y H:i');
}

$id     = (int)($_GET['id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$listUrl = '/verifikasi-stok';
$queryParams = [];
if ($search !== '') $queryParams['search'] = $search;
if ($page > 1)      $queryParams['page']   = $page;
if ($queryParams)   $listUrl .= '?' . http_build_query($queryParams);

$row = null;
if ($id > 0) {
    $stmt = db()->prepare(
        'SELECT v.*, u.ukuran AS nama_ukuran, u.stok AS stok_saat_ini, me.merek, p.nama AS produk_nama, us.nama AS user_nama
         FROM verifikasi_stok v
         JOIN ukuran u ON u.id = v.id_ukuran
         JOIN merek me ON me.id = u.id_merek
         JOIN produk p ON p.id = me.id_produk
         LEFT JOIN users us ON us.id = v.id_user
         WHERE v.id = ? AND v.deleted_at IS NULL'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}

$flash = get_flash();

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

    <?php if (!$row): ?>
        <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] overflow-hidden">
            <div class="px-6 py-16 text-center">
                <i class="bi bi-clipboard-check text-4xl block mb-3 text-[#c3cbd5]"></i>
                <p class="text-[14px] font-medium text-[#1a2332]">Data verifikasi stok tidak ditemukan.</p>
                <p class="text-[12px] text-[#5a6a7a] mt-1">Mungkin sudah dihapus atau tautan tidak valid.</p>
                <a href="<?= e($listUrl) ?>" class="inline-flex items-center gap-2 mt-5 px-4 py-2.5 bg-[#205295] text-white text-[13px] font-semibold rounded-xl hover:bg-[#183d72] transition shadow-sm">
                    <i class="bi bi-arrow-left"></i> Kembali ke Riwayat Verifikasi
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="flex flex-wrap items-center justify-end gap-2">
            <a href="<?= e($listUrl) ?>"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-[#dce3ed] text-[#5a6a7a] text-[13px] font-medium rounded-xl hover:bg-[#f0f4f8] transition shadow-sm">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <form method="POST" action="/verifikasi-stok">
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="search" value="<?= e($search) ?>">
                <?php endif; ?>
                <input type="hidden" name="page" value="<?= $page ?>">
                <button type="button"
                        data-confirm-title="Hapus Verifikasi Stok"
                        data-confirm-text="<?= e($row['produk_nama'] . ' ' . $row['merek'] . ' ' . $row['nama_ukuran']) ?> (tanggal <?= format_tanggal($row['tanggal']) ?>) akan dihapus. Stok ukuran dikembalikan ke kondisi sebelum verifikasi (<?= format_qty((float)$row['stok_sistem']) ?>). Lanjutkan?"
                        onclick="hapusKonfirmasi(this)"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#c0392b] text-white text-[13px] font-semibold rounded-xl hover:bg-[#a02820] transition shadow-sm">
                    <i class="bi bi-trash"></i> Hapus
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] overflow-hidden">
            <div class="flex justify-between items-center gap-3 p-5">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-[#e1edfa] text-[#205295] flex items-center justify-center text-xl flex-shrink-0">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[13px] leading-snug">
                        <span class="font-semibold text-[#1a2332] text-lg"><?= e($row['produk_nama']) ?></span>
                        <span class="text-[#5a6a7a] text-lg">&middot; <?= e($row['merek']) ?> &middot; <?= e($row['nama_ukuran']) ?></span>
                    </div>
                </div>
                <div class="flex flex-col items-end">
                    <?php if ((float)$row['selisih'] == 0): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#dff5e4] text-[#1a7a3a] text-xs font-semibold">
                            <i class="bi bi-check-lg"></i> Stok Sesuai
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full <?= (float)$row['selisih'] < 0 ? 'bg-[#fde8e8] text-[#c0392b]' : 'bg-[#dff5e4] text-[#1a7a3a]' ?> text-xs font-semibold">
                            <i class="bi <?= (float)$row['selisih'] < 0 ? 'bi-arrow-down-short' : 'bi-arrow-up-short' ?>"></i>
                            Selisih <?= (float)$row['selisih'] > 0 ? '+' : '' ?><?= format_qty((float)$row['selisih']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex flex-col lg:flex-row">
                <div class="flex-1 px-6 py-5">
                    <div class="flex items-center gap-2.5 mb-5">
                        <h3 class="text-[12px] font-semibold text-[#1a2332] uppercase tracking-wide">Info</h3>
                    </div>
                    <div class="flex flex-col gap-4">
                        <div>
                            <div class="flex items-center gap-1.5 text-[11px] uppercase tracking-wide text-[#5a6a7a] font-medium mb-1">
                                <i class="bi bi-calendar3 text-[#8aa5c0]"></i> Tanggal
                            </div>
                            <div class="text-[16px] font-semibold text-[#1a2332]"><?= e(format_tanggal($row['tanggal'])) ?></div>
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5 text-[11px] uppercase tracking-wide text-[#5a6a7a] font-medium mb-1">
                                <i class="bi bi-person text-[#8aa5c0]"></i> Petugas
                            </div>
                            <div class="text-[16px] font-semibold text-[#1a2332]"><?= e($row['user_nama'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>

                <div class="flex-1 px-6 py-5">
                    <div class="flex items-center gap-2.5 mb-5">
                        <h3 class="text-[12px] font-semibold text-[#1a2332] uppercase tracking-wide">Produk</h3>
                    </div>
                    <div class="flex flex-col gap-4">
                        <div>
                            <div class="flex items-center gap-1.5 text-[11px] uppercase tracking-wide text-[#5a6a7a] font-medium mb-1">
                                <i class="bi bi-box-seam text-[#8aa5c0]"></i> Produk
                            </div>
                            <div class="text-[16px] font-semibold text-[#1a2332]"><?= e($row['produk_nama']) ?></div>
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5 text-[11px] uppercase tracking-wide text-[#5a6a7a] font-medium mb-1">
                                <i class="bi bi-tags text-[#8aa5c0]"></i> Merek
                            </div>
                            <div class="text-[16px] font-semibold text-[#1a2332]"><?= e($row['merek']) ?></div>
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5 text-[11px] uppercase tracking-wide text-[#5a6a7a] font-medium mb-1">
                                <i class="bi bi-rulers text-[#8aa5c0]"></i> Ukuran
                            </div>
                            <div class="text-[16px] font-semibold text-[#1a2332]"><?= e($row['nama_ukuran']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="flex-1 px-6 py-5">
                    <div class="flex items-center gap-2.5 mb-5">
                        <h3 class="text-[12px] font-semibold text-[#1a2332] uppercase tracking-wide">Stok</h3>
                    </div>
                    <div class="flex flex-col gap-4">
                        <div>
                            <div class="flex items-center gap-1.5 text-[11px] uppercase tracking-wide text-[#5a6a7a] font-medium mb-1">
                                <i class="bi bi-hdd-stack text-[#8aa5c0]"></i> Stok Sistem
                            </div>
                            <div class="text-[16px] font-semibold text-[#1a2332]"><?= format_qty((float)$row['stok_sistem']) ?></div>
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5 text-[11px] uppercase tracking-wide text-[#5a6a7a] font-medium mb-1">
                                <i class="bi bi-box2-heart text-[#8aa5c0]"></i> Stok Saat Ini
                            </div>
                            <div class="text-[16px] font-semibold text-[#1a2332]"><?= format_qty((float)$row['stok_saat_ini']) ?></div>
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5 text-[11px] uppercase tracking-wide text-[#5a6a7a] font-medium mb-1">
                                <i class="bi bi-clipboard-data text-[#8aa5c0]"></i> Stok Fisik
                            </div>
                            <div class="text-[16px] font-semibold text-[#1a2332]"><?= format_qty((float)$row['stok_fisik']) ?></div>
                        </div>
                        <div>
                            <div class="flex items-center gap-1.5 text-[11px] uppercase tracking-wide text-[#5a6a7a] font-medium mb-1">
                                <i class="bi bi-chat-left-text text-[#8aa5c0]"></i> Keterangan
                            </div>
                            <div class="text-[16px] font-medium text-[#1a2332] whitespace-pre-line"><?= e($row['keterangan'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/app.php';