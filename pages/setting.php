<?php
// pages/setting.php

if (($_GET['action'] ?? '') === 'download') {
    require __DIR__ . '/../actions/setting.php';
    exit;
}

$headerTitle = 'Setting';
$breadcrumb = 'Setting';
$activeMenu = 'setting';
$pageTitle = 'Setting - Inventaris Toko Bangunan';

$config = require __DIR__ . '/../config/database.php';
$dbPath = $config['path'];

$appVersion = '';
$appName = 'Inventaris Toko Bangunan';
$zipRootFolder = '';
$versionFile = __DIR__ . '/../config/version.php';
if (is_file($versionFile)) {
    $v = include $versionFile;
    if (is_array($v)) {
        if (isset($v['version'])) $appVersion = $v['version'];
        if (isset($v['app_name'])) $appName = $v['app_name'];
        if (isset($v['zip_root_folder'])) $zipRootFolder = $v['zip_root_folder'];
    }
}

function format_ukuran_file(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

function format_tanggal_indo(string $dateStr): string
{
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $dt = new DateTime($dateStr);
    return $dt->format('d') . ' ' . $months[(int)$dt->format('m')] . ' ' . $dt->format('Y H:i');
}

$driverName = strtoupper((string)db()->getAttribute(PDO::ATTR_DRIVER_NAME));
$ukuran = is_file($dbPath) ? filesize($dbPath) : 0;
$mtime = is_file($dbPath) ? @filemtime($dbPath) : null;

$ukuranLabel = format_ukuran_file($ukuran);

$lastBackup = db()->query("
    SELECT * FROM log_aktivitas
    WHERE modul = 'backup' AND aksi = 'BACKUP'
    ORDER BY created_at DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$flash = get_flash();

ob_start();
?>
<div class="space-y-6">

    <?php if ($flash): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: <?= json_encode($flash['type'] === 'success' ? 'success' : 'error') ?>,
                    title: <?= json_encode($flash['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                    customClass: { popup: 'rounded-2xl' },
                });
            });
        </script>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] p-6">
            <h3 class="text-[15px] font-semibold mb-1"><i class="bi bi-database-down text-[#205295]"></i> Download Backup</h3>
            <p class="text-[13px] text-[#5a6a7a] mb-5">Unduh salinan database SQLite (.sqlite) untuk keperluan arsip atau pemindahan server.</p>
            <a href="/setting?action=download"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#2c74b3] text-white text-sm font-semibold hover:bg-[#205295] transition">
                <i class="bi bi-download"></i> Download Backup Sekarang
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] p-6">
            <h3 class="text-[15px] font-semibold mb-2"><i class="bi bi-clock-history text-[#205295]"></i> Info Backup Terakhir</h3>
            <?php if ($lastBackup): ?>
                <div class="flex items-start gap-3 mt-2">
                    <span class="w-10 h-10 rounded-full bg-[#eef3fb] text-[#205295] flex items-center justify-center text-lg flex-shrink-0">
                        <i class="bi bi-database-check"></i>
                    </span>
                    <div>
                        <p class="text-[13px] font-medium"><?= e(format_tanggal_indo($lastBackup['created_at'])) ?></p>
                        <p class="text-[13px] text-[#5a6a7a] mt-0.5"><?= e($lastBackup['deskripsi']) ?></p>
                        <p class="text-[12px] text-[#5a6a7a] mt-0.5">oleh <?= e($lastBackup['ip_address'] ?? '') ?></p>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-[13px] text-[#5a6a7a] mt-2">Belum ada backup yang tercatat.</p>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] p-6">
            <h3 class="text-[15px] font-semibold mb-1"><i class="bi bi-upload text-[#205295]"></i> Restore Database</h3>
            <p class="text-[13px] text-[#5a6a7a] mb-5">Pulihkan database dari file backup (.sqlite/.db). Database saat ini akan ditimpa, pastikan file backup valid.</p>
            <form method="POST" action="/setting" enctype="multipart/form-data">
                <input type="hidden" name="aksi" value="restore">
                <input type="file" name="file_restore" accept=".sqlite,.db" required
                    class="block w-full text-sm text-[#5a6a7a] file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#eef3fb] file:text-[#205295] hover:file:bg-[#dfe9f6]">
                <button type="button" onclick="konfirmasiRestore(this)"
                    class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#c0392b] text-white text-sm font-semibold hover:bg-[#a93226] transition">
                    <i class="bi bi-arrow-repeat"></i> Restore Database
                </button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] p-6">
            <h3 class="text-[15px] font-semibold mb-4"><i class="bi bi-info-circle text-[#205295]"></i> Informasi Database</h3>
            <dl class="text-[13px] space-y-3">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-[#5a6a7a]">Driver</dt>
                    <dd class="font-medium"><?= e($driverName) ?></dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-[#5a6a7a]">Path</dt>
                    <dd class="font-mono text-[12px] break-all text-right max-w-[260px]"><?= e($dbPath) ?></dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-[#5a6a7a]">Ukuran File</dt>
                    <dd class="font-medium"><?= e($ukuranLabel) ?></dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-[#5a6a7a]">Terakhir Dimodifikasi</dt>
                    <dd class="font-medium"><?= $mtime ? e(format_tanggal_indo(date('Y-m-d H:i:s', $mtime))) : '-' ?></dd>
                </div>
            </dl>
        </div>

    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#dce3ed] p-6">
        <h3 class="text-[15px] font-semibold mb-1"><i class="bi bi-cloud-arrow-up text-[#205295]"></i> Update Aplikasi</h3>
        <p class="text-[13px] text-[#5a6a7a] mb-5">Upload file ZIP berisi <strong>satu folder pembungkus</strong> yang di dalamnya terdapat file aplikasi versi terbaru. Seluruh kode akan diganti.</p>
        <div class="flex items-center gap-3 mt-2 mb-5">
            <span class="w-10 h-10 rounded-full bg-[#eef3fb] text-[#205295] flex items-center justify-center text-lg flex-shrink-0">
                <i class="bi bi-box-seam"></i>
            </span>
            <div>
                <p class="text-[13px] text-[#5a6a7a]">Versi Terpasang</p>
                <p class="text-[15px] font-semibold"><?= e($appName) ?> v<?= e($appVersion !== '' ? $appVersion : '-') ?></p>
                <p class="text-[12px] text-[#5a6a7a] mt-1">Folder pembungkus ZIP: <code class="font-mono"><?= e($zipRootFolder) ?></code></p>
            </div>
        </div>
        <div class="rounded-lg bg-[#fff8e1] border border-[#f0d98f] px-4 py-3 text-[12.5px] text-[#8a6d1a] mb-5">
            <i class="bi bi-shield-lock"></i> Proses update <strong>tidak akan mengubah data database.</strong>
        </div>
        <form id="form-update" method="POST" action="/setting" enctype="multipart/form-data">
            <input type="hidden" name="aksi" value="update">
            <input type="file" name="file_app" id="file-app" accept=".zip" required
                class="block w-full text-sm text-[#5a6a7a] file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#eef3fb] file:text-[#205295] hover:file:bg-[#dfe9f6]">
            <button type="button" onclick="konfirmasiUpdate(this)"
                class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#2c74b3] text-white text-sm font-semibold hover:bg-[#205295] transition">
                <i class="bi bi-cloud-arrow-up"></i> Update Aplikasi
            </button>
        </form>
    </div>
</div>

<script>
    function konfirmasiRestore(btn) {
        const form = btn.closest('form');
        const fileInput = form.querySelector('input[type="file"]');

        if (!fileInput.files.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Pilih file terlebih dahulu',
                text: 'Silakan pilih file .sqlite/.db yang ingin dipulihkan.',
                confirmButtonColor: '#2c74b3',
                customClass: { popup: 'rounded-2xl' },
            });
            return;
        }

        Swal.fire({
            title: 'Restore Database?',
            text: 'Database saat ini akan diganti dengan file yang dipilih. Pastikan file backup tersebut valid.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Restore',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#c0392b',
            cancelButtonColor: '#98a2b3',
            customClass: { popup: 'rounded-2xl' },
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    }

    function konfirmasiUpdate(btn) {
        const form = btn.closest('form');
        const fileInput = document.getElementById('file-app');

        if (!fileInput.files.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Pilih file terlebih dahulu',
                text: 'Silakan pilih file ZIP versi terbaru aplikasi.',
                confirmButtonColor: '#2c74b3',
                customClass: { popup: 'rounded-2xl' },
            });
            return;
        }

        Swal.fire({
            title: 'Update Aplikasi?',
            text: 'Seluruh kode aplikasi akan diganti dengan versi terbaru. Database tidak akan diubah.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Update',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#2c74b3',
            cancelButtonColor: '#98a2b3',
            customClass: { popup: 'rounded-2xl' },
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    }
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/app.php';