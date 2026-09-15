<?php
$headerTitle = 'Manajemen User';
$breadcrumb = 'Manajemen User';
$activeMenu = 'user';
$pageTitle = 'Manajemen User - Inventaris Toko Bangunan';

function format_tanggal_user($dateStr) {
    $months = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    $dt = new DateTime($dateStr);
    return $dt->format('d') . ' ' . $months[(int)$dt->format('m')] . ' ' . $dt->format('Y');
}

$search = trim($_GET['search'] ?? '');
$where = 'deleted_at IS NULL';
$params = [];

if ($search !== '') {
    $where .= ' AND (nama LIKE ? OR username LIKE ?)';
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
}

$countSql = "SELECT COUNT(*) FROM users WHERE {$where}";
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT * FROM users WHERE {$where} ORDER BY nama ASC LIMIT {$perPage} OFFSET {$offset}";
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

$baseUrl = '/manajemen-user';
$queryParams = [];
if ($search !== '') {
    $queryParams['search'] = $search;
}

$flash = get_flash();
$showModal = isset($_GET['modal']) || isset($_SESSION['user_errors']);
$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : null;
$userErrors = $_SESSION['user_errors'] ?? [];
$editUser = null;
if ($editId) {
    $stmtE = db()->prepare('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL');
    $stmtE->execute([$editId]);
    $editUser = $stmtE->fetch(PDO::FETCH_ASSOC);
    if (!$editUser) {
        redirect('/manajemen-user');
    }
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
            <h1 class="text-xl font-bold text-[#1a2332]"><i class="bi bi-people text-[#205295]"></i> Manajemen User</h1>
            <p class="text-[13px] text-[#5a6a7a] mt-0.5">Kelola akun pengguna sistem</p>
        </div>
        <a href="/manajemen-user?modal=1"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#205295] text-white text-[13px] font-semibold rounded-xl hover:bg-[#183d72] transition shadow-sm">
            <i class="bi bi-plus-lg"></i> Tambah User
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#dce3ed]">
            <form method="GET" action="/manajemen-user" class="relative">
                <input type="text" name="search" value="<?= e($search) ?>"
                       placeholder="Cari nama / username..."
                       class="w-full pl-10 pr-4 py-2.5 text-[13px] border border-[#dce3ed] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#205295]/20 focus:border-[#205295] transition bg-[#f7faff]">
                <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5a6a7a] text-sm"></i>
                <?php if ($search !== ''): ?>
                    <a href="/manajemen-user" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#5a6a7a] hover:text-[#c0392b] transition text-sm" title="Hapus pencarian">
                        <i class="bi bi-x-circle"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left bg-[#f0f4f8] text-[#5a6a7a] text-xs uppercase tracking-wide">
                        <th class="px-6 py-3 font-semibold">Nama</th>
                        <th class="px-6 py-3 font-semibold">Username</th>
                        <th class="px-6 py-3 font-semibold">Terdaftar</th>
                        <th class="px-6 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e4e9f0]">
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-[#5a6a7a]">
                                <i class="bi bi-people text-4xl block mb-2 text-[#c3cbd5]"></i>
                                <?php if ($search !== ''): ?>
                                    Tidak ada data yang cocok dengan pencarian "<strong><?= e($search) ?></strong>".
                                <?php else: ?>
                                    Belum ada user.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr class="hover:bg-[#f7faff]">
                                <td class="px-6 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-[#e1edfa] text-[#205295] flex items-center justify-center font-semibold text-sm">
                                            <?= e(avatar_initials($row['nama'])) ?>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium"><?= e($row['nama']) ?></span>
                                            <?php if ((int)$row['id'] === auth_id()): ?>
                                                <span class="px-2 py-0.5 rounded-full bg-[#2c74b3] text-white text-[11px] font-semibold">Anda</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-3.5 text-[#5a6a7a]"><?= e($row['username']) ?></td>
                                <td class="px-6 py-3.5 text-[#5a6a7a]"><?= $row['created_at'] ? e(format_tanggal_user($row['created_at'])) : '-' ?></td>
                                <td class="px-6 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="/manajemen-user?modal=1&edit_id=<?= $row['id'] ?>"
                                           class="px-3 py-1.5 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] hover:text-[#2c74b3] hover:border-[#2c74b3] text-xs transition">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ((int)$row['id'] !== auth_id()): ?>
                                            <button type="button"
                                                    onclick="hapusUser(<?= $row['id'] ?>, '<?= e($row['username']) ?>')"
                                                    class="px-3 py-1.5 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] hover:text-[#c0392b] hover:border-[#c0392b] text-xs transition cursor-pointer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
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
                <?php require __DIR__ . '/../templates/components/pagination.php'; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($showModal): ?>
<div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" id="modalBackdrop">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-[#dce3ed] flex items-center justify-between">
            <h3 class="text-[15px] font-semibold"><?= $editUser ? 'Edit' : 'Tambah' ?> User</h3>
            <a href="/manajemen-user" class="text-[#5a6a7a] hover:text-[#c0392b] text-xl cursor-pointer"><i class="bi bi-x-lg"></i></a>
        </div>
        <form method="POST" action="/manajemen-user" class="p-6 space-y-4">
            <input type="hidden" name="action" value="simpan">
            <?php if ($editUser): ?>
                <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
            <?php endif; ?>
            <div>
                <label class="block text-sm font-medium text-[#1a2332] mb-1.5">Nama Lengkap</label>
                <input type="text" name="nama" value="<?= e($editUser['nama'] ?? ($_SESSION['old_nama'] ?? '')) ?>" placeholder="mis: Budi Santoso"
                       class="w-full px-4 py-2.5 border <?= isset($userErrors['nama']) ? 'border-[#c0392b]' : 'border-[#c9cbd3]' ?> rounded-lg text-sm focus:outline-none focus:ring-2 <?= isset($userErrors['nama']) ? 'focus:ring-[#c0392b]/20' : 'focus:ring-[#2c74b3]' ?>">
                <?php if (isset($userErrors['nama'])): ?>
                    <p class="text-[#c0392b] text-xs mt-1"><?= e($userErrors['nama']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1a2332] mb-1.5">Username</label>
                <input type="text" name="username" value="<?= e($editUser['username'] ?? ($_SESSION['old_username'] ?? '')) ?>" placeholder="mis: budi"
                       class="w-full px-4 py-2.5 border <?= isset($userErrors['username']) ? 'border-[#c0392b]' : 'border-[#c9cbd3]' ?> rounded-lg text-sm focus:outline-none focus:ring-2 <?= isset($userErrors['username']) ? 'focus:ring-[#c0392b]/20' : 'focus:ring-[#2c74b3]' ?>">
                <?php if (isset($userErrors['username'])): ?>
                    <p class="text-[#c0392b] text-xs mt-1"><?= e($userErrors['username']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1a2332] mb-1.5">
                    Password <span class="text-[#5a6a7a] font-normal"><?= $editUser ? '(kosongkan jika tidak diubah)' : '' ?></span>
                </label>
                <input type="password" name="password" autocomplete="new-password"
                       class="w-full px-4 py-2.5 border <?= isset($userErrors['password']) ? 'border-[#c0392b]' : 'border-[#c9cbd3]' ?> rounded-lg text-sm focus:outline-none focus:ring-2 <?= isset($userErrors['password']) ? 'focus:ring-[#c0392b]/20' : 'focus:ring-[#2c74b3]' ?>">
                <?php if (isset($userErrors['password'])): ?>
                    <p class="text-[#c0392b] text-xs mt-1"><?= e($userErrors['password']) ?></p>
                <?php endif; ?>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <a href="/manajemen-user"
                   class="px-4 py-2 rounded-lg border border-[#c9cbd3] text-[#5a6a7a] text-sm">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-lg bg-[#205295] hover:bg-[#144272] text-white text-sm font-semibold cursor-pointer">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<form id="hapusForm" method="POST" action="/manajemen-user" style="display:none">
    <input type="hidden" name="action" value="hapus">
    <input type="hidden" name="id" id="hapusId">
</form>

<script>
function hapusUser(id, username) {
    Swal.fire({
        title: 'Hapus User',
        text: 'User "' + username + '" akan dihapus. Lanjutkan?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#c0392b',
        cancelButtonColor: '#98a2b3',
        customClass: { popup: 'rounded-2xl' },
    }).then(function(result) {
        if (result.isConfirmed) {
            document.getElementById('hapusId').value = id;
            document.getElementById('hapusForm').submit();
        }
    });
}
</script>
<?php
unset($_SESSION['old_nama'], $_SESSION['old_username'], $_SESSION['user_errors']);
$content = ob_get_clean();
require __DIR__ . '/../templates/app.php';
