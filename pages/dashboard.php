<?php
// pages/dashboard.php
$headerTitle = 'Dashboard';
$breadcrumb = 'Dashboard';
$activeMenu = 'dashboard';
$pageTitle = 'Dashboard - Inventaris Toko Bangunan';

$db = db();

$totalProduk = (int)$db->query('SELECT COUNT(*) FROM produk WHERE deleted_at IS NULL')->fetchColumn();
$totalUkuran = (int)$db->query('SELECT COUNT(*) FROM ukuran WHERE deleted_at IS NULL')->fetchColumn();
$stokMenipis = (int)$db->query('SELECT COUNT(*) FROM ukuran WHERE deleted_at IS NULL AND stok > 0 AND stok <= 5')->fetchColumn();
$stokHabis = (int)$db->query('SELECT COUNT(*) FROM ukuran WHERE deleted_at IS NULL AND stok <= 0')->fetchColumn();

$charts = [];
$awal = (new DateTime())->modify('first day of this month')->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $m = (clone $awal)->modify("+{$i} months");
    $charts[] = [
        'key'   => $m->format('Y-m'),
        'label' => $m->format('F Y'),
        'masuk' => 0,
        'keluar' => 0,
    ];
}

// Agregasi langsung di SQL per bulan+jenis (maks 12 baris), bukan fetch semua baris
$startDate = (clone $awal)->format('Y-m-d 00:00:00');
$endDate = date('Y-m-d 23:59:59');
$stmtMut = $db->prepare("
    SELECT substr(tanggal, 1, 7) AS bulan, jenis, SUM(qty) AS total
    FROM mutasi_stok
    WHERE deleted_at IS NULL AND tanggal >= ? AND tanggal <= ?
    GROUP BY substr(tanggal, 1, 7), jenis
");
$stmtMut->execute([$startDate, $endDate]);

$agregat = [];
while ($r = $stmtMut->fetch(PDO::FETCH_ASSOC)) {
    $agregat[$r['bulan']][$r['jenis']] = (float)$r['total'];
}

foreach ($charts as &$c) {
    $c['masuk']  = $agregat[$c['key']]['masuk']  ?? 0;
    $c['keluar'] = $agregat[$c['key']]['keluar'] ?? 0;
}
unset($c);

$tertinggi = 1;
foreach ($charts as $c) {
    $tertinggi = max($tertinggi, $c['masuk'], $c['keluar']);
}
$maxBar = 160;
$totalMutasi = array_sum(array_map(fn($c) => $c['masuk'] + $c['keluar'], $charts));

// Stok menipis paginated
$page = max(1, (int)($_GET['menipis_page'] ?? 1));
$perPage = 15;
$countMenipis = (int)$db->query('SELECT COUNT(*) FROM ukuran WHERE deleted_at IS NULL AND stok <= 5')->fetchColumn();
$totalPages = max(1, (int)ceil($countMenipis / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmtMenipis = $db->prepare('
    SELECT u.*, m.merek, p.nama as produk_nama
    FROM ukuran u
    JOIN merek m ON m.id = u.id_merek AND m.deleted_at IS NULL
    JOIN produk p ON p.id = m.id_produk AND p.deleted_at IS NULL
    WHERE u.deleted_at IS NULL AND u.stok <= 5
    ORDER BY u.stok ASC
    LIMIT ? OFFSET ?
');
$stmtMenipis->execute([$perPage, $offset]);
$stokMenipisList = $stmtMenipis->fetchAll(PDO::FETCH_ASSOC);

$paginationMenipis = [
    'rows' => $stokMenipisList,
    'total' => $countMenipis,
    'page' => $page,
    'perPage' => $perPage,
    'totalPages' => $totalPages,
    'hasPages' => $totalPages > 1,
    'hasPrev' => $page > 1,
    'hasNext' => $page < $totalPages,
    'firstItem' => $countMenipis > 0 ? ($page - 1) * $perPage + 1 : 0,
    'lastItem' => min($page * $perPage, $countMenipis),
];

$paginationBaseUrl = '/dashboard';

ob_start();
?>
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <h3 class="text-[15px] font-semibold"><i class="bi bi-bar-chart-line text-[#205295]"></i> Mutasi Stok &mdash; 6 Bulan Terakhir</h3>
                <p class="text-[13px] text-[#5a6a7a] mt-0.5">Jumlah qty masuk &amp; keluar per bulan</p>
            </div>
            <div class="flex items-center gap-4 text-[13px]">
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-[#1a7a3a] inline-block"></span> Masuk</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-[#c0392b] inline-block"></span> Keluar</span>
            </div>
        </div>

        <?php if ($totalMutasi > 0): ?>
            <div class="flex items-end justify-between gap-2 sm:gap-4" style="height: <?= $maxBar + 50 ?>px;">
                <?php foreach ($charts as $c): ?>
                    <div class="flex-1 flex flex-col items-center justify-end h-full gap-2 group relative" title="<?= e($c['label']) ?>">
                        <div class="flex items-end gap-1.5 h-full" style="height: <?= $maxBar ?>px;">
                            <div class="flex flex-col items-center justify-end h-full">
                                <span class="text-[11px] font-bold text-[#1a7a4c] mb-1 group-hover:opacity-100 <?= $c['masuk'] ? '' : 'opacity-0' ?>"><?= number_format($c['masuk'], 0, ',', '.') ?></span>
                                <div class="w-5 sm:w-8 rounded-t-md bg-[#35a054] hover:bg-[#1a7a4c] transition-colors"
                                     style="height: <?= max(2, $c['masuk'] / $tertinggi * $maxBar) ?>px;"></div>
                            </div>
                            <div class="flex flex-col items-center justify-end h-full">
                                <span class="text-[11px] font-bold text-[#c0392b] mb-1 group-hover:opacity-100 <?= $c['keluar'] ? '' : 'opacity-0' ?>"><?= number_format($c['keluar'], 0, ',', '.') ?></span>
                                <div class="w-5 sm:w-8 rounded-t-md bg-[#e05959] hover:bg-[#c0392b] transition-colors"
                                     style="height: <?= max(2, $c['keluar'] / $tertinggi * $maxBar) ?>px;"></div>
                            </div>
                        </div>
                        <div class="text-center border-t border-[#dce3ed] pt-2 w-full">
                            <div class="text-[12px] font-semibold text-[#1a2332]"><?= e($c['label']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="py-16 text-center text-[#5a6a7a]">
                <i class="bi bi-bar-chart text-4xl block mb-2 text-[#c3cbd5]"></i>
                Belum ada data mutasi dalam 6 bulan terakhir.
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#dce3ed] flex items-center justify-between">
            <h3 class="text-[15px] font-semibold"><i class="bi bi-clipboard-data text-[#205295]"></i> Stok Menipis / Habis</h3>
            <span class="text-[13px] text-[#5a6a7a]"><?= $stokMenipis ?> menipis, <?= $stokHabis ?> habis</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left bg-[#f0f4f9] text-[#5a6a7a] text-xs uppercase tracking-wide">
                        <th class="px-6 py-3 font-semibold">Produk</th>
                        <th class="px-6 py-3 font-semibold">Merek</th>
                        <th class="px-6 py-3 font-semibold">Ukuran</th>
                        <th class="px-6 py-3 font-semibold">Stok</th>
                        <th class="px-6 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e4e9f0]">
                    <?php if (empty($stokMenipisList)): ?>
                        <tr><td colspan="5" class="px-6 py-10 text-center text-[#5a6a7a]">Semua stok aman. <i class="bi bi-emoji-smile"></i></td></tr>
                    <?php else: ?>
                        <?php foreach ($stokMenipisList as $u): ?>
                            <tr class="hover:bg-[#f7faff]">
                                <td class="px-6 py-3.5 font-medium"><?= e($u['produk_nama'] ?? '-') ?></td>
                                <td class="px-6 py-3.5"><?= e($u['merek'] ?? '-') ?></td>
                                <td class="px-6 py-3.5"><?= e($u['ukuran']) ?></td>
                                <td class="px-6 py-3.5"><?= format_number($u['stok']) ?></td>
                                <td class="px-6 py-3.5">
                                    <?php if ($u['stok'] <= 0): ?>
                                        <span class="px-2.5 py-1 rounded-full bg-[#fde8e8] text-[#c0392b] text-xs font-semibold">Habis</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full bg-[#fef0db] text-[#b86500] text-xs font-semibold">Menipis</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($paginationMenipis['hasPages']): ?>
            <div class="px-6 py-4 border-t border-[#dce3ed]">
                <?php $pagination = $paginationMenipis; $baseUrl = $paginationBaseUrl; $queryParams = []; ?>
                <?php require __DIR__ . '/../templates/components/pagination.php'; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/app.php';