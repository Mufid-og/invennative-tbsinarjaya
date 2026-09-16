<?php
$headerTitle = 'Mutasi Stok';
$breadcrumb = 'Mutasi Stok';
$activeMenu = 'mutasi-stok';
$pageTitle = 'Riwayat Mutasi Stok - Inventaris Toko Bangunan';

function format_tanggal($dateStr) {
    $months = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    $dt = new DateTime($dateStr);
    return $dt->format('d') . ' ' . $months[(int)$dt->format('m')] . ' ' . $dt->format('Y H:i');
}

$search = trim($_GET['search'] ?? '');
$where = 'm.deleted_at IS NULL';
$params = [];

if ($search !== '') {
    $conditions = [];
    $conditions[] = '(p.nama LIKE ? OR me.merek LIKE ? OR u.ukuran LIKE ? OR m.keterangan LIKE ?)';
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;

    $dateResult = indonesian_month_search($search, 'm');
    if (!empty($dateResult['conditions'])) {
        foreach ($dateResult['conditions'] as $dc) {
            $conditions[] = $dc;
        }
        $params = array_merge($params, $dateResult['params']);
    }

    $where .= ' AND (' . implode(' OR ', $conditions) . ')';
}

$countSql = "SELECT COUNT(*) FROM mutasi_stok m
    JOIN ukuran u ON u.id = m.id_ukuran
    JOIN merek me ON me.id = u.id_merek
    JOIN produk p ON p.id = me.id_produk
    WHERE {$where}";
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT m.*, u.ukuran, u.stok, me.merek, p.nama as produk_nama
    FROM mutasi_stok m
    JOIN ukuran u ON u.id = m.id_ukuran
    JOIN merek me ON me.id = u.id_merek
    JOIN produk p ON p.id = me.id_produk
    WHERE {$where}
    ORDER BY m.tanggal DESC, m.id DESC
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

$baseUrl = '/mutasi-stok';
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
            <h1 class="text-xl font-bold text-[#1a2332]"><i class="bi bi-arrow-left-right text-[#205295]"></i> Riwayat Mutasi Stok</h1>
            <p class="text-[13px] text-[#5a6a7a] mt-0.5">Seluruh catatan masuk &amp; keluar barang</p>
        </div>
        <div class="flex items-center gap-2.5">
            <button onclick="window.openModalCetakMutasi()" type="button"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-[#dce3ed] text-[#5a6a7a] text-[13px] font-medium rounded-xl hover:bg-[#f0f4f8] transition shadow-sm">
                <i class="bi bi-printer"></i> Cetak Laporan
            </button>
            <a href="/mutasi-stok/input?new=1"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#205295] text-white text-[13px] font-semibold rounded-xl hover:bg-[#183d72] transition shadow-sm">
                <i class="bi bi-plus-lg"></i> Input Mutasi Stok
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#dce3ed]">
            <form method="GET" action="/mutasi-stok" class="relative">
                <input type="text" name="search" value="<?= e($search) ?>"
                       placeholder="Cari produk / merek / ukuran / tanggal"
                       class="w-full pl-10 pr-4 py-2.5 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295] transition bg-[#f7faff]">
                <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5a6a7a] text-sm"></i>
                <?php if ($search !== ''): ?>
                    <a href="/mutasi-stok" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#5a6a7a] hover:text-[#c0392b] transition text-sm" title="Hapus pencarian">
                        <i class="bi bi-x-circle"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left bg-[#f0f4f9] text-[#5a6a7a] text-xs uppercase tracking-wide">
                        <th class="px-6 py-3 font-semibold">No</th>
                        <th class="px-6 py-3 font-semibold">Produk</th>
                        <th class="px-6 py-3 font-semibold">Tanggal</th>
                        <th class="px-6 py-3 font-semibold">Jenis</th>
                        <th class="px-6 py-3 font-semibold text-right">Qty</th>
                        <th class="px-6 py-3 font-semibold text-right">Harga Modal</th>
                        <th class="px-6 py-3 font-semibold text-right">Harga Jual</th>
                        <th class="px-6 py-3 font-semibold">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e4e9f0]">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-[#5a6a7a]">
                                <i class="bi bi-inbox text-4xl block mb-2 text-[#c3cbd5]"></i>
                                <?php if ($search !== ''): ?>
                                    Tidak ada data yang cocok dengan pencarian "<strong><?= e($search) ?></strong>".
                                <?php else: ?>
                                    Belum ada riwayat mutasi stok.
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
                                <td class="px-6 py-3.5">
                                    <?php if ($row['jenis'] === 'masuk'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                            <i class="bi bi-arrow-down-circle"></i> Masuk
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-50 text-red-700 text-xs font-semibold">
                                            <i class="bi bi-arrow-up-circle"></i> Keluar
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3.5 text-right font-semibold text-[#1a2332]"><?= format_number($row['qty'], 0) ?></td>
                                <td class="px-6 py-3.5 text-right text-[#5a6a7a]"><?= e(format_rupiah($row['harga_modal'])) ?></td>
                                <td class="px-6 py-3.5 text-right text-[#5a6a7a]"><?= e(format_rupiah($row['harga_jual'])) ?></td>
                                <td class="px-6 py-3.5 text-[#5a6a7a] max-w-[180px] truncate" title="<?= e($row['keterangan']) ?>"><?= e($row['keterangan'] ?? '-') ?></td>
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
window.openModalCetakMutasi = function() {
    var now = new Date();
    var pad = function(n) { return String(n).padStart(2, '0'); };
    var dari = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-01';
    var lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    var sampai = lastDay.getFullYear() + '-' + pad(lastDay.getMonth() + 1) + '-' + pad(lastDay.getDate());

    Swal.fire({
        title: 'Cetak Laporan Mutasi',
        html: `
            <div class="text-left space-y-3">
                <div>
                    <label class="block text-[13px] font-medium text-[#5a6a7a] mb-1">Dari Tanggal</label>
                    <input type="date" id="cetak-dari" value="${dari}" class="w-full px-3 py-2 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295]">
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-[#5a6a7a] mb-1">Sampai Tanggal</label>
                    <input type="date" id="cetak-sampai" value="${sampai}" class="w-full px-3 py-2 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295]">
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
