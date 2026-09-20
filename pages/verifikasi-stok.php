<?php
$headerTitle = 'Verifikasi Stok';
$breadcrumb = 'Verifikasi Stok';
$activeMenu = 'verifikasi-stok';
$pageTitle = 'Riwayat Verifikasi Stok - Inventaris Toko Bangunan';

function format_tanggal($dateStr) {
    $months = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    $dt = new DateTime($dateStr);
    return $dt->format('d') . ' ' . $months[(int)$dt->format('m')] . ' ' . $dt->format('Y H:i');
}

$search = trim($_GET['search'] ?? '');
$where = 'v.deleted_at IS NULL';
$params = [];

require_once __DIR__ . '/../includes/search_helper.php';

if ($search !== '' && search_min_length($search)) {
    $conditions = [];

    // FTS5 / prefix LIKE — mencari di produk, merek, ukuran, dan keterangan sekaligus
    [$searchSql, $searchParams] = fts_search_stok($search, 'verifikasi', 'v');
    $conditions[] = $searchSql;
    $params = array_merge($params, $searchParams);

    // Pencarian tanggal via nama bulan Indonesia
    $dateResult = indonesian_month_search($search, 'v');
    if (!empty($dateResult['conditions'])) {
        foreach ($dateResult['conditions'] as $dc) {
            $conditions[] = $dc;
        }
        $params = array_merge($params, $dateResult['params']);
    }

    $where .= ' AND (' . implode(' OR ', $conditions) . ')';
}

$countSql = "SELECT COUNT(*) FROM verifikasi_stok v
    JOIN ukuran u ON u.id = v.id_ukuran
    JOIN merek me ON me.id = u.id_merek
    JOIN produk p ON p.id = me.id_produk
    LEFT JOIN users us ON us.id = v.id_user
    WHERE {$where}";
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT v.*, u.ukuran, me.merek, p.nama as produk_nama, us.nama as user_nama
    FROM verifikasi_stok v
    JOIN ukuran u ON u.id = v.id_ukuran
    JOIN merek me ON me.id = u.id_merek
    JOIN produk p ON p.id = me.id_produk
    LEFT JOIN users us ON us.id = v.id_user
    WHERE {$where}
    ORDER BY v.tanggal DESC, v.id DESC
    LIMIT {$perPage} OFFSET {$offset}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pagination = [
    'rows' => $rows,
    'total' => $total,
    'page' => $page,
    'perPage' => $perPage,
    'totalPages' => $totalPages,
    'hasPages' => $totalPages > 1,
    'hasPrev' => $page > 1,
    'hasNext' => $page < $totalPages,
    'firstItem' => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
    'lastItem' => min($page * $perPage, $total),
];

$baseUrl = '/verifikasi-stok';
$queryParams = [];
if ($search !== '') {
    $queryParams['search'] = $search;
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

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-[#1a2332]"><i class="bi bi-clipboard-check text-[#205295]"></i> Riwayat Verifikasi Stok</h1>
            <p class="text-[13px] text-[#5a6a7a] mt-0.5">Seluruh catatan verifikasi fisik stok</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="/verifikasi-stok/input"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#205295] text-white text-[13px] font-semibold rounded-xl hover:bg-[#183d72] transition shadow-sm">
                <i class="bi bi-plus-lg"></i> Input Verifikasi Stok
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#dce3ed]">
            <form method="GET" action="/verifikasi-stok" class="relative">
                <input type="text" name="search" value="<?= e($search) ?>"
                       placeholder="Cari produk / merek / ukuran / tanggal"
                       minlength="2"
                       title="Ketik minimal 2 karakter untuk mencari"
                       class="w-full pl-10 pr-4 py-2.5 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295] transition bg-[#f7faff]">
                <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5a6a7a] text-sm"></i>
                <?php if ($search !== ''): ?>
                    <a href="/verifikasi-stok" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#5a6a7a] hover:text-[#c0392b] transition text-sm" title="Hapus pencarian">
                        <i class="bi bi-x-circle"></i>
                    </a>
                <?php endif; ?>
                <p class="mt-1 text-[11px] text-[#5a6a7a] pl-1">Ketik minimal 2 karakter untuk mencari</p>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left bg-[#f0f4f9] text-[#5a6a7a] text-xs uppercase tracking-wide">
                        <th class="px-6 py-3 font-semibold">No</th>
                        <th class="px-6 py-3 font-semibold">Produk</th>
                        <th class="px-6 py-3 font-semibold">Tanggal</th>
                        <th class="px-6 py-3 font-semibold text-right">Stok Sistem</th>
                        <th class="px-6 py-3 font-semibold text-right">Stok Fisik</th>
                        <th class="px-6 py-3 font-semibold text-right">Selisih</th>
                        <th class="px-6 py-3 font-semibold">Keterangan</th>
                        <th class="px-6 py-3 font-semibold">Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e4e9f0]">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-[#5a6a7a]">
                                <i class="bi bi-clipboard-check text-4xl block mb-2 text-[#c3cbd5]"></i>
                                <?php if ($search !== ''): ?>
                                    Tidak ada data yang cocok dengan pencarian "<strong><?= e($search) ?></strong>".
                                <?php else: ?>
                                    Belum ada riwayat verifikasi stok.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = ($page - 1) * $perPage; ?>
                        <?php foreach ($rows as $row): $no++; ?>
                            <tr class="hover:bg-[#f7faff]">
                                <td class="px-6 py-3.5 text-[#5a6a7a]"><?= $no ?></td>
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-[#eef4fb] flex items-center justify-center flex-shrink-0">
                                            <i class="bi bi-box-seam text-[#205295] text-sm"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-[#1a2332]"><?= e($row['produk_nama']) ?></div>
                                            <div class="text-[11px] text-[#5a6a7a]"><?= e($row['merek']) ?> &middot; <?= e($row['ukuran']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5 text-[#5a6a7a] whitespace-nowrap"><?= e(format_tanggal($row['tanggal'])) ?></td>
                                <td class="px-6 py-3.5 text-right text-[#5a6a7a]"><?= format_number($row['stok_sistem'], 0) ?></td>
                                <td class="px-6 py-3.5 text-right font-semibold text-[#1a2332]"><?= format_number($row['stok_fisik'], 0) ?></td>
                                <td class="px-6 py-3.5 text-right">
                                    <?php $selisih = $row['selisih']; ?>
                                    <?php if ($selisih == 0): ?>
                                        <span class="px-2.5 py-1 rounded-full bg-[#dff5e4] text-[#1a7a3a] text-xs font-semibold"><i class="bi bi-check-lg"></i> Sesuai</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full <?= $selisih < 0 ? 'bg-[#fde8e8] text-[#c0392b]' : 'bg-[#dff5e4] text-[#1a7a3a]' ?> text-xs font-semibold"><?= $selisih > 0 ? '+' : '' ?><?= format_number($selisih, 0) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3.5 text-[#5a6a7a] max-w-[180px] truncate" title="<?= e($row['keterangan'] ?? '') ?>"><?= e($row['keterangan'] ?? '-') ?></td>
                                <td class="px-6 py-3.5 text-[#5a6a7a]"><?= e($row['user_nama'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination['hasPages']): ?>
            <div class="px-6 py-4 border-t border-[#dce3ed]">
                <?php require __DIR__ . '/../templates/components/pagination.php'; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
window.openModalCetakVerifikasi = function() {
    Swal.fire({
        title: 'Cetak Laporan Verifikasi',
        html: `
            <div class="text-left space-y-3">
                <div>
                    <label class="block text-[13px] font-medium text-[#5a6a7a] mb-1">Dari Tanggal</label>
                    <input type="date" id="cetak-dari" class="w-full px-3 py-2 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295]">
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-[#5a6a7a] mb-1">Sampai Tanggal</label>
                    <input type="date" id="cetak-sampai" class="w-full px-3 py-2 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295]">
                </div>
            </div>
        `,
        confirmButtonText: 'Tampilkan & Cetak',
        confirmButtonColor: '#205295',
        cancelButtonText: 'Batal',
        showCancelButton: true,
        customClass: { popup: 'rounded-2xl' },
        preConfirm: () => {
            const dari = document.getElementById('cetak-dari').value;
            const sampai = document.getElementById('cetak-sampai').value;
            if (!dari || !sampai) {
                Swal.showValidationMessage('Isi periode tanggal terlebih dahulu.');
                return false;
            }
            if (sampai < dari) {
                Swal.showValidationMessage('Tanggal "sampai" tidak boleh sebelum "dari".');
                return false;
            }
            window.open('/cetak-mutasi?dari=' + dari + '&sampai=' + sampai, '_blank');
        },
    });
};
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/app.php';
