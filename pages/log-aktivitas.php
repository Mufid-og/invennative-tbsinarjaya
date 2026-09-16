<?php
$headerTitle = 'Log Aktivitas';
$breadcrumb = 'Log Aktivitas';
$activeMenu = 'log';
$pageTitle = 'Log Aktivitas - Inventaris Toko Bangunan';

$search = trim($_GET['search'] ?? '');
$where = '1=1';
$params = [];

if ($search !== '') {
    $where .= ' AND (l.deskripsi LIKE ? OR l.modul LIKE ? OR l.aksi LIKE ?)';
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$countSql = "SELECT COUNT(*) FROM log_aktivitas l WHERE {$where}";
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT l.*, us.nama as user_nama FROM log_aktivitas l
    LEFT JOIN users us ON us.id = l.id_user
    WHERE {$where}
    ORDER BY l.created_at DESC, l.id DESC
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

$baseUrl = '/log-aktivitas';
$queryParams = [];
if ($search !== '') {
    $queryParams['search'] = $search;
}

$detailId = isset($_GET['detail_id']) ? (int)$_GET['detail_id'] : null;

ob_start();
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-[#1a2332]"><i class="bi bi-clock-history text-[#205295]"></i> Log Aktivitas</h1>
            <p class="text-[13px] text-[#5a6a7a] mt-0.5">Catatan seluruh aktivitas pengguna dalam sistem</p>
        </div>
        <button onclick="window.openModalCetakLog()" type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-[#205295] text-[#205295] text-[13px] font-medium rounded-xl hover:bg-[#e1edfa] transition shadow-sm">
            <i class="bi bi-printer"></i> Cetak Laporan
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#dce3ed]">
            <form method="GET" action="/log-aktivitas" class="relative">
                <input type="text" name="search" value="<?= e($search) ?>"
                       placeholder="Cari aktivitas..."
                       class="w-full pl-10 pr-4 py-2.5 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295] transition bg-[#f7faff]">
                <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5a6a7a] text-sm"></i>
                <?php if ($search !== ''): ?>
                    <a href="/log-aktivitas" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#5a6a7a] hover:text-[#c0392b] transition text-sm" title="Hapus pencarian">
                        <i class="bi bi-x-circle"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left bg-[#f0f4f9] text-[#5a6a7a] text-xs uppercase tracking-wide">
                        <th class="px-6 py-3 font-semibold">Waktu</th>
                        <th class="px-6 py-3 font-semibold">User</th>
                        <th class="px-6 py-3 font-semibold">Aksi</th>
                        <th class="px-6 py-3 font-semibold">Modul</th>
                        <th class="px-6 py-3 font-semibold">Deskripsi</th>
                        <th class="px-6 py-3 font-semibold text-right">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e4e9f0]">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-[#5a6a7a]">
                                <i class="bi bi-clock-history text-4xl block mb-2 text-[#c3cbd5]"></i>
                                <?php if ($search !== ''): ?>
                                    Tidak ada data yang cocok dengan pencarian "<strong><?= e($search) ?></strong>".
                                <?php else: ?>
                                    Belum ada aktivitas.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr class="hover:bg-[#f7faff]">
                                <td class="px-6 py-3.5 text-[#5a6a7a] whitespace-nowrap"><?= e(date('d M Y H:i', strtotime($row['created_at']))) ?></td>
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-[#e1edfa] text-[#205295] flex items-center justify-center text-xs font-semibold"><?= e($row['user_nama'] ? avatar_initials($row['user_nama']) : 'S') ?></div>
                                        <?php if ($row['user_nama']): ?>
                                            <span class="font-medium text-[#1a2332]"><?= e($row['user_nama']) ?></span>
                                        <?php else: ?>
                                            <span class="text-[#5a6a7a]">Sistem</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5">
                                    <?php if ($row['aksi'] === 'CREATE'): ?>
                                        <span class="px-2.5 py-1 rounded-full bg-[#dff5e4] text-[#1a7a3a] text-xs font-semibold">CREATE</span>
                                    <?php elseif ($row['aksi'] === 'UPDATE'): ?>
                                        <span class="px-2.5 py-1 rounded-full bg-[#e1edfa] text-[#205295] text-xs font-semibold">UPDATE</span>
                                    <?php elseif ($row['aksi'] === 'DELETE'): ?>
                                        <span class="px-2.5 py-1 rounded-full bg-[#fde8e8] text-[#c0392b] text-xs font-semibold">DELETE</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full bg-[#eef2f7] text-[#5a6a7a] text-xs font-semibold"><?= e($row['aksi']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3.5"><span class="px-2 py-1 rounded-md bg-[#f0f4f8] text-[#5a6a7a] text-xs"><?= e($row['modul'] ?? '-') ?></span></td>
                                <td class="px-6 py-3.5 text-[#1a2332] max-w-[260px] truncate" title="<?= e($row['deskripsi']) ?>"><?= e($row['deskripsi']) ?></td>
                                <td class="px-6 py-3.5 text-right">
                                    <?php if (!empty($row['data_perubahan'])): ?>
                                        <a href="/log-aktivitas<?= $search !== '' ? '?search=' . urlencode($search) . '&detail_id=' . $row['id'] : '?detail_id=' . $row['id'] ?>"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] hover:text-[#2c74b3] hover:border-[#2c74b3] text-xs transition">
                                            <i class="bi <?= $detailId === (int)$row['id'] ? 'bi-chevron-up' : 'bi-chevron-down' ?>"></i>
                                            <?= $detailId === (int)$row['id'] ? 'Tutup' : 'Lihat' ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-[#c3cbd5] text-xs">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <?php if ($detailId === (int)$row['id'] && !empty($row['data_perubahan'])): ?>
                                <?php $data = json_decode($row['data_perubahan'], true); ?>
                                <?php if ($data && $row['modul'] === 'verifikasi-stok' && isset($data['items']) && is_array($data['items'])): ?>
                                    <tr class="bg-[#f8fafc]">
                                        <td colspan="6" class="px-6 py-4">
                                            <div class="flex flex-wrap items-center gap-x-6 gap-y-1 mb-3 text-[13px]">
                                                <div><span class="text-[#5a6a7a]">Tanggal:</span> <span class="font-medium text-[#1a2332]"><?= e($data['tanggal'] ?? '-') ?></span></div>
                                                <div><span class="text-[#5a6a7a]">Jumlah item:</span> <span class="font-medium text-[#1a2332]"><?= count($data['items']) ?></span></div>
                                                <?php if (!empty($data['keterangan'])): ?>
                                                    <div><span class="text-[#5a6a7a]">Keterangan:</span> <span class="font-medium text-[#1a2332]"><?= e($data['keterangan']) ?></span></div>
                                                <?php endif; ?>
                                            </div>
                                            <table class="min-w-full divide-y divide-[#dce3ed] border border-[#dce3ed] rounded-lg overflow-hidden text-[13px]">
                                                <thead class="bg-[#f0f4f8]">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-semibold text-[#5a6a7a] uppercase">Produk / Merek / Ukuran</th>
                                                        <th class="px-4 py-2 text-right text-xs font-semibold text-[#5a6a7a] uppercase">Stok Sistem</th>
                                                        <th class="px-4 py-2 text-right text-xs font-semibold text-[#5a6a7a] uppercase">Stok Fisik</th>
                                                        <th class="px-4 py-2 text-right text-xs font-semibold text-[#5a6a7a] uppercase">Selisih</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-[#dce3ed] bg-white">
                                                    <?php foreach ($data['items'] as $item): ?>
                                                        <?php $selisih = (float)($item['selisih'] ?? 0); ?>
                                                        <tr>
                                                            <td class="px-4 py-2 text-sm font-medium text-[#1a2332]">
                                                                <?= e($item['produk'] ?? '-') ?><span class="text-[#5a6a7a] font-normal"> &middot; <?= e($item['merek'] ?? '-') ?> &middot; <?= e($item['ukuran'] ?? '-') ?></span>
                                                            </td>
                                                            <td class="px-4 py-2 text-right text-sm text-[#5a6a7a]"><?= format_number((float)($item['stok_sistem'] ?? 0), 0) ?></td>
                                                            <td class="px-4 py-2 text-right text-sm text-[#1a2332]"><?= format_number((float)($item['stok_fisik'] ?? 0), 0) ?></td>
                                                            <td class="px-4 py-2 text-right text-sm font-semibold <?= $selisih > 0 ? 'text-[#1a7a3a]' : ($selisih < 0 ? 'text-[#c0392b]' : 'text-[#5a6a7a]') ?>">
                                                                <?= $selisih > 0 ? '+' : '' ?><?= format_number($selisih, 0) ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                <?php elseif ($data && $row['modul'] === 'mutasi-stok'): ?>
                                    <tr class="bg-[#f8fafc]">
                                        <td colspan="6" class="px-6 py-4">
                                            <table class="min-w-full divide-y divide-[#dce3ed] border border-[#dce3ed] rounded-lg overflow-hidden text-[13px]">
                                                <tbody class="divide-y divide-[#dce3ed] bg-white">
                                                    <?php
                                                    $mutasiFields = [
                                                        'produk' => 'Produk',
                                                        'merek' => 'Merek',
                                                        'ukuran' => 'Ukuran',
                                                        'jenis' => 'Jenis',
                                                        'qty' => 'Qty',
                                                        'harga_modal' => 'Harga Modal',
                                                        'harga_jual' => 'Harga Jual',
                                                        'tanggal' => 'Tanggal',
                                                    ];
                                                    ?>
                                                    <?php foreach ($mutasiFields as $mKey => $mLabel): ?>
                                                        <?php if (!isset($data[$mKey])) continue; ?>
                                                        <?php
                                                        $mVal = $data[$mKey];
                                                        if (in_array($mKey, ['harga_modal', 'harga_jual'])) {
                                                            $mDisplay = format_rupiah($mVal);
                                                        } elseif ($mKey === 'qty') {
                                                            $mDisplay = format_number((float)$mVal, 0);
                                                        } elseif ($mKey === 'jenis') {
                                                            $mDisplay = $mVal === 'masuk' ? 'Masuk' : ($mVal === 'keluar' ? 'Keluar' : $mVal);
                                                        } elseif ($mKey === 'tanggal') {
                                                            $mDisplay = date('d M Y H:i', strtotime($mVal));
                                                        } else {
                                                            $mDisplay = $mVal;
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td class="px-4 py-2 text-left text-xs font-semibold text-[#5a6a7a] uppercase w-40"><?= e($mLabel) ?></td>
                                                            <td class="px-4 py-2 text-sm text-[#1a2332]"><?= e($mDisplay) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                <?php elseif ($data && isset($data['sesudah'])): ?>
                                    <tr class="bg-[#f8fafc]">
                                        <td colspan="6" class="px-6 py-4">
                                            <table class="min-w-full divide-y divide-[#dce3ed] border border-[#dce3ed] rounded-lg overflow-hidden text-[13px]">
                                                <thead class="bg-[#f0f4f8]">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-semibold text-[#5a6a7a] uppercase">Kolom / Field</th>
                                                        <th class="px-4 py-2 text-left text-xs font-semibold text-[#5a6a7a] uppercase">Data Lama</th>
                                                        <th class="px-4 py-2 text-left text-xs font-semibold text-[#5a6a7a] uppercase">Data Baru</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-[#dce3ed] bg-white">
                                                    <?php foreach ($data['sesudah'] as $key => $newValue): ?>
                                                        <?php
                                                        $oldValue = $data['sebelum'][$key] ?? '-';
                                                        $isChanged = (string)$oldValue !== (string)$newValue;
                                                        if (!$isChanged) continue;
                                                        $label = ucwords(str_replace('_', ' ', (string)$key));
                                                        if (in_array($key, ['harga_modal', 'harga_jual'])) {
                                                            $oldDisplay = format_rupiah($oldValue);
                                                            $newDisplay = format_rupiah($newValue);
                                                        } else {
                                                            $oldDisplay = $oldValue;
                                                            $newDisplay = $newValue;
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td class="px-4 py-2 text-sm font-medium text-[#1a2332]"><?= e($label) ?></td>
                                                            <td class="px-4 py-2 text-sm text-[#c0392b] line-through"><?= e($oldDisplay) ?></td>
                                                            <td class="px-4 py-2 text-sm font-bold text-[#1a7a3a]"><?= e($newDisplay) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
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
window.openModalCetakLog = function() {
    Swal.fire({
        title: 'Cetak Laporan Log Aktivitas',
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
            if (!dari || !sampai) return Swal.showValidationMessage('Isi periode tanggal.');
            if (sampai < dari) return Swal.showValidationMessage('Tanggal tidak valid.');
            window.open('/cetak-log?dari=' + dari + '&sampai=' + sampai, '_blank');
        },
    });
};
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/app.php';