<?php
$selectedProdukId = isset($_GET['produk']) && $_GET['produk'] !== '' ? (int)$_GET['produk'] : null;
$selectedMerekId = isset($_GET['merek']) && $_GET['merek'] !== '' ? (int)$_GET['merek'] : null;
$search = trim($_GET['search'] ?? '');

$headerTitle = 'Manajemen Produk';
$breadcrumb = 'Produk';
$activeMenu = 'produk';
$pageTitle = 'Manajemen Produk - Inventaris Toko Bangunan';

$db = db();

// Fetch current produk/merek for breadcrumb & title
$currentProduk = null;
$currentMerek = null;
if ($selectedProdukId) {
    $stmt = db()->prepare('SELECT * FROM produk WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$selectedProdukId]);
    $currentProduk = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentProduk) {
        redirect('/produk');
    }
}
if ($selectedMerekId) {
    $stmt = db()->prepare('SELECT * FROM merek WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$selectedMerekId]);
    $currentMerek = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentMerek) {
        redirect('/produk?produk=' . $selectedProdukId);
    }
}

require_once __DIR__ . '/../actions/produk.php';

// ----- Pagination helpers -----
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$pageKey = 'page';
if ($selectedProdukId) $pageKey = 'merekPage';
if ($selectedMerekId) $pageKey = 'ukuranPage';

$flash = get_flash();

ob_start();
?>
<div class="relative">
    <div class="mb-5 flex flex-wrap items-center gap-2 text-sm">
        <a href="/produk"
           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-[#dce3ed] bg-white text-[#5a6a7a] hover:text-[#2c74b3] hover:border-[#2c74b3] transition">
            <i class="bi bi-box-seam"></i> Produk
        </a>

        <?php if ($selectedProdukId): ?>
            <i class="bi bi-chevron-right text-[#c3cbd5]"></i>
            <a href="/produk?produk=<?= $selectedProdukId ?>"
               class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#e1edfa] text-[#144272] font-medium transition">
                <?= e($currentProduk['nama'] ?? '') ?>
            </a>
        <?php endif; ?>

        <?php if ($selectedMerekId): ?>
            <i class="bi bi-chevron-right text-[#c3cbd5]"></i>
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#dfefe4] text-[#1a7a3a] font-medium">
                <i class="bi bi-tag"></i> <?= e($currentMerek['merek'] ?? '') ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm <?= $flash['type'] === 'error' ? 'bg-[#fde8e8] text-[#c0392b]' : 'bg-[#dff5e4] text-[#1a7a3a]' ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-wrap gap-3 mb-5 items-center">
        <div class="relative flex-1 min-w-[200px]">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-[#5a6a7a]"></i>
            <form method="GET" action="/produk">
                <?php if ($selectedProdukId): ?><input type="hidden" name="produk" value="<?= $selectedProdukId ?>"><?php endif; ?>
                <?php if ($selectedMerekId): ?><input type="hidden" name="merek" value="<?= $selectedMerekId ?>"><?php endif; ?>
                <input name="search" type="text" value="<?= e($search) ?>" placeholder="Cari data..."
                       class="w-full pl-10 pr-4 py-2.5 border border-[#c9cbd3] rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#2c74b3]">
            </form>
        </div>

        <?php if (!$selectedProdukId): ?>
            <a href="/produk?modal=produk"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#205295] hover:bg-[#144272] text-white text-sm font-medium rounded-lg transition">
                <i class="bi bi-plus-lg"></i> Tambah Produk
            </a>
        <?php elseif (!$selectedMerekId): ?>
            <a href="/produk?produk=<?= $selectedProdukId ?>&modal=merek"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#205295] hover:bg-[#144272] text-white text-sm font-medium rounded-lg transition">
                <i class="bi bi-plus-lg"></i> Tambah Merek
            </a>
        <?php else: ?>
            <a href="/produk?produk=<?= $selectedProdukId ?>&merek=<?= $selectedMerekId ?>&modal=ukuran"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#205295] hover:bg-[#144272] text-white text-sm font-medium rounded-lg transition">
                <i class="bi bi-plus-lg"></i> Tambah Ukuran
            </a>
        <?php endif; ?>
    </div>

    <?php if (!$selectedProdukId): ?>
        <?php
        $params = [];
        if ($search !== '') $params[] = 'p.nama LIKE ?'; else $params = [];
        $where = 'p.deleted_at IS NULL';
        $whereParams = [];
        if ($search !== '') { $where .= ' AND p.nama LIKE ?'; $whereParams[] = '%' . $search . '%'; }
        $stmt = $db->prepare("SELECT COUNT(*) FROM produk p WHERE {$where}");
        $stmt->execute($whereParams);
        $total = (int)$stmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $stmt = $db->prepare("
            SELECT p.*, COUNT(DISTINCT m.id) AS merek_count
            FROM produk p
            LEFT JOIN merek m ON m.id_produk = p.id AND m.deleted_at IS NULL
            WHERE {$where}
            GROUP BY p.id
            ORDER BY p.nama ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($whereParams);
        $produkList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pagination = [
            'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages,
            'hasPages' => $totalPages > 1, 'hasPrev' => $page > 1, 'hasNext' => $page < $totalPages,
            'firstItem' => $total > 0 ? ($page - 1) * $perPage + 1 : 0, 'lastItem' => min($page * $perPage, $total),
        ];
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] overflow-hidden">
            <div class="px-6 py-4 border-b border-[#dce3ed] flex items-center justify-between">
                <h3 class="text-[15px] font-semibold"><i class="bi bi-box text-[#205295]"></i> Daftar Produk</h3>
                <span class="text-[13px] text-[#5a6a7a]"><?= $total ?> produk</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="text-left bg-[#f0f4f8] text-[#5a6a7a] text-xs uppercase tracking-wide">
                            <th class="px-6 py-3 font-semibold">Nama Produk</th>
                            <th class="px-6 py-3 font-semibold">Jml Merek</th>
                            <th class="px-6 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e4e9f0]">
                        <?php if (empty($produkList)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-10 text-center text-[#5a6a7a]">
                                    <i class="bi bi-inbox text-3xl block mb-2"></i>
                                    Belum ada produk. Klik "Tambah Produk" untuk memulai.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produkList as $produk): ?>
                                <tr class="hover:bg-[#f7faff]">
                                    <td class="px-6 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-lg bg-[#e1edfa] text-[#205295] flex items-center justify-center">
                                                <i class="bi bi-box"></i>
                                            </div>
                                            <span class="font-medium"><?= e($produk['nama']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3.5"><span class="px-2 py-0.5 rounded-full bg-[#eef2f7] text-[#5a6a7a] text-xs"><?= $produk['merek_count'] ?></span></td>
                                    <td class="px-6 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <a href="/produk?produk=<?= $produk['id'] ?>"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#205295] hover:bg-[#144272] text-white text-xs font-semibold transition">
                                                <i class="bi bi-boxes"></i> Lihat Merek
                                            </a>
                                            <a href="/produk?produk=<?= $produk['id'] ?>&modal=produk&edit_id=<?= $produk['id'] ?>"
                                               class="px-3 py-1.5 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] hover:text-[#2c74b3] hover:border-[#2c74b3] text-xs transition">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="/produk">
                                                <input type="hidden" name="action" value="hapus_produk">
                                                <input type="hidden" name="id" value="<?= $produk['id'] ?>">
                                                <button type="button"
                                                        data-confirm-title="Hapus Produk"
                                                        data-confirm-text="<?= e($produk['nama']) ?> akan dihapus beserta merek &amp; ukurannya. Lanjutkan?"
                                                        onclick="hapusKonfirmasi(this)"
                                                        class="px-3 py-1.5 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] hover:text-[#c0392b] hover:border-[#c0392b] text-xs transition">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pagination['hasPages']): ?>
                <div class="px-6 py-4 border-t border-[#dce3ed]">
                    <?php $baseUrl = '/produk'; $queryParams = ['search' => $search]; ?>
                    <?php require __DIR__ . '/../templates/components/pagination.php'; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif (!$selectedMerekId): ?>
        <?php
        $where = 'm.id_produk = ? AND m.deleted_at IS NULL';
        $whereParams = [$selectedProdukId];
        if ($search !== '') { $where .= ' AND m.merek LIKE ?'; $whereParams[] = '%' . $search . '%'; }
        $stmt = $db->prepare("SELECT COUNT(*) FROM merek m WHERE {$where}");
        $stmt->execute($whereParams);
        $total = (int)$stmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $stmt = $db->prepare("
            SELECT m.*, COUNT(DISTINCT u.id) AS ukuran_count
            FROM merek m
            LEFT JOIN ukuran u ON u.id_merek = m.id AND u.deleted_at IS NULL
            WHERE {$where}
            GROUP BY m.id
            ORDER BY m.merek ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($whereParams);
        $merekList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pagination = [
            'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages,
            'hasPages' => $totalPages > 1, 'hasPrev' => $page > 1, 'hasNext' => $page < $totalPages,
            'firstItem' => $total > 0 ? ($page - 1) * $perPage + 1 : 0, 'lastItem' => min($page * $perPage, $total),
        ];
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] overflow-hidden">
            <div class="px-6 py-4 border-b border-[#dce3ed] flex items-center justify-between">
                <h3 class="text-[15px] font-semibold"><i class="bi bi-tags text-[#205295]"></i> Merek &mdash; <?= e($currentProduk['nama'] ?? '') ?></h3>
                <span class="text-[13px] text-[#5a6a7a]"><?= $total ?> merek</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="text-left bg-[#f0f4f9] text-[#5a6a7a] text-xs uppercase tracking-wide">
                            <th class="px-6 py-3 font-semibold">Merek</th>
                            <th class="px-6 py-3 font-semibold">Jml Ukuran</th>
                            <th class="px-6 py-3 font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e4e9f0]">
                        <?php if (empty($merekList)): ?>
                            <tr><td colspan="3" class="px-6 py-10 text-center text-[#5a6a7a]">
                                <i class="bi bi-tag text-3xl block mb-2"></i>
                                Belum ada merek untuk produk ini.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($merekList as $merek): ?>
                                <tr class="hover:bg-[#f7faff]">
                                    <td class="px-6 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-lg bg-[#e1edfa] text-[#205295] flex items-center justify-center">
                                                <i class="bi bi-tag"></i>
                                            </div>
                                            <span class="font-medium"><?= e($merek['merek']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3.5"><span class="px-2 py-0.5 rounded-full bg-[#eaf2f7] text-[#5a6a7a] text-xs"><?= $merek['ukuran_count'] ?></span></td>
                                    <td class="px-6 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <a href="/produk?produk=<?= $selectedProdukId ?>&merek=<?= $merek['id'] ?>"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#205295] hover:bg-[#144272] text-white text-xs font-semibold transition">
                                                <i class="bi bi-rulers"></i> Lihat Ukuran
                                            </a>
                                            <a href="/produk?produk=<?= $selectedProdukId ?>&modal=merek&edit_id=<?= $merek['id'] ?>"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] hover:text-[#2c74b3] hover:border-[#2c74b3] text-xs transition">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <form method="POST" action="/produk">
                                                <input type="hidden" name="action" value="hapus_merek">
                                                <input type="hidden" name="id" value="<?= $merek['id'] ?>">
                                                <button type="button"
                                                        data-confirm-title="Hapus Merek"
                                                        data-confirm-text="<?= e($merek['merek']) ?> akan dihapus beserta ukurannya. Lanjutkan?"
                                                        onclick="hapusKonfirmasi(this)"
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] hover:text-[#c0392b] hover:border-[#c0392b] text-xs transition">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pagination['hasPages']): ?>
                <div class="px-6 py-4 border-t border-[#dce3ed]">
                    <?php $baseUrl = '/produk'; $queryParams = ['produk' => $selectedProdukId, 'search' => $search]; ?>
                    <?php require __DIR__ . '/../templates/components/pagination.php'; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <?php
        $where = 'u.id_merek = ? AND u.deleted_at IS NULL';
        $whereParams = [$selectedMerekId];
        if ($search !== '') { $where .= ' AND u.ukuran LIKE ?'; $whereParams[] = '%' . $search . '%'; }
        $stmt = $db->prepare("SELECT COUNT(*) FROM ukuran u WHERE {$where}");
        $stmt->execute($whereParams);
        $total = (int)$stmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $stmt = $db->prepare("
            SELECT * FROM ukuran u
            WHERE {$where}
            ORDER BY u.ukuran ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($whereParams);
        $ukuranList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pagination = [
            'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages,
            'hasPages' => $totalPages > 1, 'hasPrev' => $page > 1, 'hasNext' => $page < $totalPages,
            'firstItem' => $total > 0 ? ($page - 1) * $perPage + 1 : 0, 'lastItem' => min($page * $perPage, $total),
        ];
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] overflow-hidden">
            <div class="px-6 py-4 border-b border-[#dce3ed] flex items-center justify-between">
                <h3 class="text-[15px] font-semibold"><i class="bi bi-rulers text-[#205295]"></i> Ukuran &mdash; <?= e($currentMerek['merek'] ?? '') ?></h3>
                <span class="text-[13px] text-[#5a6a7a]"><?= $total ?> ukuran</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="text-left bg-[#f0f4f9] text-[#5a6a7a] text-xs uppercase tracking-wide">
                            <th class="px-6 py-3 font-semibold">Ukuran</th>
                            <th class="px-6 py-3 font-semibold">Stok</th>
                            <th class="px-6 py-3 font-semibold">Harga Modal</th>
                            <th class="px-6 py-3 font-semibold">Harga Jual</th>
                            <th class="px-6 py-3 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e4e9f0]">
                        <?php if (empty($ukuranList)): ?>
                            <tr><td colspan="5" class="px-6 py-10 text-center text-[#5a6a7a]">
                                <i class="bi bi-rulers text-3xl block mb-2"></i>
                                Belum ada ukuran pada merek ini.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($ukuranList as $ukuran): ?>
                                <?php $stok = (float)$ukuran['stok']; ?>
                                <tr class="hover:bg-[#f7faff]">
                                    <td class="px-6 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-lg bg-[#e1edfa] text-[#205295] flex items-center justify-center">
                                                <i class="bi bi-rulers"></i>
                                            </div>
                                            <span class="font-medium"><?= e($ukuran['ukuran']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3.5">
                                        <?php if ($stok <= 0): ?>
                                            <span class="px-2 py-1 rounded-full bg-[#fde8e8] text-[#c0392b] text-xs font-semibold">Habis</span>
                                        <?php elseif ($stok <= 5): ?>
                                            <span class="px-2 py-1 rounded-full bg-[#fef0db] text-[#b86500] text-xs font-semibold"><?= format_number($stok) ?> &mdash; Menipis</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 rounded-full bg-[#dff5e4] text-[#1a7a3a] text-xs font-semibold"><?= format_number($stok) ?> &mdash; Tersedia</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-3.5"><?= format_rupiah($ukuran['harga_modal']) ?></td>
                                    <td class="px-6 py-3.5 font-semibold text-[#205295]"><?= format_rupiah($ukuran['harga_jual']) ?></td>
                                    <td class="px-6 py-3.5 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="/produk?produk=<?= $selectedProdukId ?>&merek=<?= $selectedMerekId ?>&modal=ukuran&edit_id=<?= $ukuran['id'] ?>"
                                               class="px-3 py-1.5 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] hover:text-[#2c74b3] hover:border-[#2c74b3] text-xs transition">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="/produk">
                                                <input type="hidden" name="action" value="hapus_ukuran">
                                                <input type="hidden" name="id" value="<?= $ukuran['id'] ?>">
                                                <input type="hidden" name="search" value="<?= e($search) ?>">
                                                <input type="hidden" name="page" value="<?= (int)$pagination['page'] ?>">
                                                <button type="button"
                                                    data-confirm-title="Hapus Ukuran"
                                                    data-confirm-text="<?= e($ukuran['ukuran']) ?> akan dihapus. Lanjutkan?"
                                                    onclick="hapusKonfirmasi(this)"
                                                    class="px-3 py-1.5 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] hover:text-[#c0392b] hover:border-[#c0392b] text-xs transition">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pagination['hasPages']): ?>
                <div class="px-6 py-4 border-t border-[#dce3ed]">
                    <?php $baseUrl = '/produk'; $queryParams = ['produk' => $selectedProdukId, 'merek' => $selectedMerekId, 'search' => $search]; ?>
                    <?php require __DIR__ . '/../templates/components/pagination.php'; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php
    $modal = $_GET['modal'] ?? null;
    $editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
    $errors = $_SESSION['form_errors'] ?? [];
    $old = $_SESSION['form_old'] ?? [];
    unset($_SESSION['form_errors'], $_SESSION['form_old']);
    ?>

    <?php if ($modal === 'produk'): ?>
    <?php
        $editProduk = null;
        if ($editId > 0) {
            $stmt = $db->prepare('SELECT * FROM produk WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$editId]);
            $editProduk = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $namaProdukVal = $editProduk['nama'] ?? ($old['nama'] ?? '');
    ?>
    <div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-[#dce3ed] flex items-center justify-between">
                <h3 class="text-[15px] font-semibold"><?= $editId ? 'Edit' : 'Tambah' ?> Produk</h3>
                <a href="/produk" class="text-[#5a6a7a] hover:text-[#c0392b] text-xl"><i class="bi bi-x-lg"></i></a>
            </div>
            <form method="POST" action="/produk" class="p-6 space-y-4">
                <input type="hidden" name="action" value="simpan_produk">
                <input type="hidden" name="edit_id" value="<?= $editId ?>">
                <div>
                    <label class="block text-sm font-medium text-[#1a2332] mb-1.5">Nama Produk</label>
                    <input type="text" name="nama" value="<?= e($namaProdukVal) ?>" class="w-full px-4 py-2.5 border <?= isset($errors['nama']) ? 'border-red-400' : 'border-[#c9cbd3]' ?> rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2c74b3]">
                    <?php if (isset($errors['nama'])): ?><p class="mt-1 text-xs text-[#c0392b]"><?= e($errors['nama']) ?></p><?php endif; ?>
                </div>
                <div class="flex justify-end gap-2">
                    <a href="/produk" class="px-4 py-2 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] text-sm">Batal</a>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-[#205295] hover:bg-[#144272] text-white text-sm font-semibold">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($modal === 'merek'): ?>
    <?php
        $editMerek = null;
        if ($editId > 0) {
            $stmt = $db->prepare('SELECT * FROM merek WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$editId]);
            $editMerek = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $namaMerekVal = $editMerek['merek'] ?? ($old['nama'] ?? '');
    ?>
    <div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-[#dce3ed] flex items-center justify-between">
                <h3 class="text-[15px] font-semibold"><?= $editId ? 'Edit' : 'Tambah' ?> Merek</h3>
                <a href="/produk?produk=<?= $selectedProdukId ?>" class="text-[#5a6a7a] hover:text-[#c0392b] text-xl"><i class="bi bi-x-lg"></i></a>
            </div>
            <form method="POST" action="/produk" class="p-6 space-y-4">
                <input type="hidden" name="action" value="simpan_merek">
                <input type="hidden" name="edit_id" value="<?= $editId ?>">
                <input type="hidden" name="id_produk" value="<?= $selectedProdukId ?>">
                <div>
                    <label class="block text-sm font-medium text-[#1a2332] mb-1.5">Nama Merek</label>
                    <input type="text" name="nama" value="<?= e($namaMerekVal) ?>" placeholder="mis: Avian" class="w-full px-4 py-2.5 border <?= isset($errors['nama']) ? 'border-red-400' : 'border-[#c9cbd3]' ?> rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2c74b3]">
                    <?php if (isset($errors['nama'])): ?><p class="mt-1 text-xs text-[#c0392b]"><?= e($errors['nama']) ?></p><?php endif; ?>
                </div>
                <div class="flex justify-end gap-2">
                    <a href="/produk?produk=<?= $selectedProdukId ?>" class="px-4 py-2 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] text-sm">Batal</a>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-[#205295] hover:bg-[#144272] text-white text-sm font-semibold">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($modal === 'ukuran'): ?>
    <?php
        $editUkuran = null;
        if ($editId > 0) {
            $stmt = $db->prepare('SELECT * FROM ukuran WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$editId]);
            $editUkuran = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $namaUkuranVal = $editUkuran['ukuran'] ?? ($old['ukuran'] ?? '');
        $stokVal = $editUkuran['stok'] ?? ($old['stok'] ?? '0');
        $hargaModalVal = (int)($editUkuran['harga_modal'] ?? ($old['harga_modal'] ?? 0));
        $hargaJualVal = (int)($editUkuran['harga_jual'] ?? ($old['harga_jual'] ?? 0));
    ?>
    <div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl overflow-hidden max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-[#dce3ed] flex items-center justify-between">
                <h3 class="text-[15px] font-semibold"><?= $editId ? 'Edit' : 'Tambah' ?> Ukuran</h3>
                <a href="/produk?produk=<?= $selectedProdukId ?>&merek=<?= $selectedMerekId ?>" class="text-[#5a6a7a] hover:text-[#c0392b] text-xl"><i class="bi bi-x-lg"></i></a>
            </div>
            <form method="POST" action="/produk" class="p-6 space-y-4">
                <input type="hidden" name="action" value="simpan_ukuran">
                <input type="hidden" name="edit_id" value="<?= $editId ?>">
                <input type="hidden" name="id_merek" value="<?= $selectedMerekId ?>">
                <input type="hidden" name="search" value="<?= e($search) ?>">
                <input type="hidden" name="page" value="<?= (int)($_GET['page'] ?? 1) ?>">
                <div>
                    <label class="block text-sm font-medium text-[#1a2332] mb-1.5">Ukuran</label>
                    <input type="text" name="ukuran" value="<?= e($namaUkuranVal) ?>" placeholder="mis: 1 liter, 200 ml" class="w-full px-4 py-2.5 border <?= isset($errors['ukuran']) ? 'border-red-400' : 'border-[#c9cbd3]' ?> rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2c74b3]">
                    <?php if (isset($errors['ukuran'])): ?><p class="mt-1 text-xs text-[#c0392b]"><?= e($errors['ukuran']) ?></p><?php endif; ?>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-[#1a2332] mb-1.5">Stok</label>
                        <input type="text" name="stok" value="<?= e($stokVal) ?>" <?= $editId ? 'disabled' : '' ?> class="w-full px-3 py-2.5 border border-[#c9cbd3] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2c74b3] <?= $editId ? 'bg-[#f0f4f8] text-[#5a6a7a]' : '' ?>">
                        <?php if ($editId): ?><p class="mt-1 text-[11px] text-[#5a6a7a]">Stok diubah lewat Mutasi Stok.</p><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#1a2332] mb-1.5">Harga Modal</label>
                        <input type="text" inputmode="numeric" data-rupiah name="harga_modal_display" value="<?= $hargaModalVal > 0 ? format_rupiah($hargaModalVal) : '' ?>" class="w-full px-3 py-2.5 border border-[#c9cbd3] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2c74b3]">
                        <input type="hidden" name="harga_modal" data-rupiah-sync="harga_modal" value="<?= e((string)$hargaModalVal) ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#1a2332] mb-1.5">Harga Jual</label>
                        <input type="text" inputmode="numeric" data-rupiah name="harga_jual_display" value="<?= $hargaJualVal > 0 ? format_rupiah($hargaJualVal) : '' ?>" class="w-full px-3 py-2.5 border border-[#c9cbd3] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2c74b3]">
                        <input type="hidden" name="harga_jual" data-rupiah-sync="harga_jual" value="<?= e((string)$hargaJualVal) ?>">
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <a href="/produk?produk=<?= $selectedProdukId ?>&merek=<?= $selectedMerekId ?>" class="px-4 py-2 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] text-sm">Batal</a>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-[#205295] hover:bg-[#144272] text-white text-sm font-semibold">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/app.php';