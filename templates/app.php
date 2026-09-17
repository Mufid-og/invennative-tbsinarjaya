<?php
$headerTitle = $headerTitle ?? 'Inventaris';
$breadcrumb = $breadcrumb ?? 'Menu';
$activeMenu = $activeMenu ?? '';
$pageTitle = $pageTitle ?? 'Inventaris - Toko Bangunan';

$user = auth_user();
$initials = $user ? avatar_initials($user['nama']) : 'A';

$produkCount = (int)db()->query('SELECT COUNT(*) FROM produk WHERE deleted_at IS NULL')->fetchColumn();
$fullUri = $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        #lw-progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 0;
            z-index: 10000;
            background: linear-gradient(90deg, #2c74b3, #a8d0f0);
            transition: width 0.25s ease;
            opacity: 0;
            pointer-events: none;
        }

        body.lw-loading #lw-progress-bar {
            opacity: 1;
            animation: lw-slide 1.2s ease-in-out infinite;
        }

        @keyframes lw-slide {
            0%   { width: 0; }
            50%  { width: 55%; }
            100% { width: 100%; }
        }
    </style>
</head>

<body class="min-h-screen bg-[#f0f4f8] text-[#1a2332] font-sans antialiased" onsubmit="window.flushRupiah && window.flushRupiah()">

    <div id="lw-progress-bar"></div>

    <div id="overlay" class="hidden fixed inset-0 bg-black/35 z-40" onclick="toggleSidebar()"></div>

    <aside id="sidebar"
        class="fixed top-0 left-0 h-screen w-[260px] bg-[#0a2647] text-white flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200 z-50"
        aria-label="Sidebar">
        <div class="px-6 py-5 border-b border-white/10">
            <h1 class="text-lg font-bold flex items-center gap-2.5 tracking-tight">
                <i class="bi bi-building text-[#a8d0f0] text-[22px]"></i> Sinar Jaya
            </h1>
            <small class="block text-xs text-white/50 mt-0.5">Inventory Management</small>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4">
            <div class="text-[11px] uppercase tracking-widest text-white/30 px-3 pb-1.5 pt-3">Menu</div>

            <a href="/dashboard"
                class="flex items-center gap-3 px-4 py-3 rounded-lg mb-0.5 text-sm font-medium transition text-white/65 hover:bg-white/[0.08] hover:text-white <?= $activeMenu === 'dashboard' ? 'bg-white/[0.12] text-white shadow-[inset_3px_0_0_#a8d0f0]' : '' ?>">
                <i class="bi bi-grid-1x2 text-lg w-5 text-center"></i> Dashboard
            </a>

            <a href="/produk"
                class="flex items-center gap-3 px-4 py-3 rounded-lg mb-0.5 text-sm font-medium transition text-white/65 hover:bg-white/[0.08] hover:text-white <?= $activeMenu === 'produk' ? 'bg-white/[0.12] text-white shadow-[inset_3px_0_0_#a8d0f0]' : '' ?>"
                style="margin-bottom:2px">
                <i class="bi bi-box-seam text-lg w-5 text-center"></i> Manajemen Produk
                <span class="ml-auto bg-[#2c74b3] text-white text-[11px] font-semibold px-2 py-0.5 rounded-full"><?= $produkCount ?></span>
            </a>

            <a href="/mutasi-stok"
                class="flex items-center gap-3 px-4 py-3 rounded-lg mb-0.5 text-sm font-medium transition text-white/65 hover:bg-white/[0.08] hover:text-white <?= in_array($activeMenu, ['mutasi-stok', 'mutasi-stok-input']) ? 'bg-white/[0.12] text-white shadow-[inset_3px_0_0_#a8d0f0]' : '' ?>"
                style="margin-bottom:2px">
                <i class="bi bi-arrow-left-right text-lg w-5 text-center"></i> Mutasi Stok
            </a>

            <a href="/verifikasi-stok"
                class="flex items-center gap-3 px-4 py-3 rounded-lg mb-0.5 text-sm font-medium transition text-white/65 hover:bg-white/[0.08] hover:text-white <?= in_array($activeMenu, ['verifikasi-stok', 'verifikasi-stok-input']) ? 'bg-white/[0.12] text-white shadow-[inset_3px_0_0_#a8d0f0]' : '' ?>"
                style="margin-bottom:2px">
                <i class="bi bi-clipboard-check text-lg w-5 text-center"></i> Verifikasi Stok
            </a>

            <a href="/manajemen-user"
                class="flex items-center gap-3 px-4 py-3 rounded-lg mb-0.5 text-sm font-medium transition text-white/65 hover:bg-white/[0.08] hover:text-white <?= $activeMenu === 'user' ? 'bg-white/[0.12] text-white shadow-[inset_3px_0_0_#a8d0f0]' : '' ?>"
                style="margin-bottom:2px">
                <i class="bi bi-people text-lg w-5 text-center"></i> Manajemen User
            </a>

            <a href="/log-aktivitas"
                class="flex items-center gap-3 px-4 py-3 rounded-lg mb-0.5 text-sm font-medium transition text-white/65 hover:bg-white/[0.08] hover:text-white <?= $activeMenu === 'log' ? 'bg-white/[0.12] text-white shadow-[inset_3px_0_0_#a8d0f0]' : '' ?>"
                style="margin-bottom:2px">
                <i class="bi bi-clock-history text-lg w-5 text-center"></i> Log Aktivitas
            </a>

            <a href="/setting"
                class="flex items-center gap-3 px-4 py-3 rounded-lg mb-0.5 text-sm font-medium transition text-white/65 hover:bg-white/[0.08] hover:text-white <?= $activeMenu === 'setting' ? 'bg-white/[0.12] text-white shadow-[inset_3px_0_0_#a8d0f0]' : '' ?>"
                style="margin-bottom:2px">
                <i class="bi bi-gear text-lg w-5 text-center"></i> Setting
            </a>
        </nav>

        <div class="px-5 py-4 border-t border-white/10 text-xs text-white/35">
            <i class="bi bi-shield-check"></i> &copy; 2026 SinarJaya
        </div>
    </aside>

    <div class="lg:ml-[260px] flex flex-col min-h-screen">
        <header
            class="sticky top-0 z-30 h-16 bg-white border-b border-[#dce3ed] flex items-center justify-between px-6 lg:px-8">
            <div class="flex items-center gap-4">
                <button class="lg:hidden bg-transparent border-0 text-xl text-[#1a2332] cursor-pointer" onclick="toggleSidebar()" type="button">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h2 class="text-base font-semibold"><?= e($headerTitle) ?></h2>
                    <div class="text-[13px] text-[#5a6a7a]">Toko Bangunan / <span class="text-[#2c74b3] font-medium"><?= e($breadcrumb) ?></span></div>
                </div>
            </div>
            <div class="flex items-center gap-5">
                <span class="hidden lg:block text-[13px] text-[#5a6a7a]" id="dateDisplay"></span>
                <div class="flex items-center gap-3">
                    <?php if ($user): ?>
                        <div class="w-9 h-9 rounded-full bg-[#2c74b3] text-white flex items-center justify-center font-semibold text-sm cursor-pointer" title="<?= e($user['nama']) ?>">
                            <?= e($initials) ?>
                        </div>
                        <a href="/logout"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-[#dce3ed] text-[13px] font-medium text-[#5a6a7a] hover:bg-[#f0f4f8] hover:text-[#c0392b] transition">
                            <i class="bi bi-box-arrow-right"></i> Keluar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main class="flex-1 p-6 lg:p-8">
            <?= $content ?>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sb = document.getElementById('sidebar');
            const ov = document.getElementById('overlay');
            sb.classList.toggle('-translate-x-full');
            ov.classList.toggle('hidden');
        }
        document.getElementById('overlay').addEventListener('click', toggleSidebar);
        const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('dateDisplay').textContent = new Date().toLocaleDateString('id-ID', opts);

        // ---- Format Rupiah reusable (offline, tanpa library) ----
        window.formatAllRupiah = function () {
            document.querySelectorAll('input[data-rupiah-sync]').forEach(function (hidden) {
                const vis = hidden.parentElement.querySelector('input[data-rupiah]');
                if (vis && document.activeElement !== vis) {
                    vis.value = window.rupiahDisplay(hidden.value);
                }
            });
        };

        window.flushRupiah = function () {
            document.querySelectorAll('input[data-rupiah-sync]').forEach(function (hidden) {
                const vis = hidden.parentElement.querySelector('input[data-rupiah]');
                if (!vis) return;
                const digits = vis.value.replace(/[^\d]/g, '').slice(0, 15);
                if (hidden.value !== digits) {
                    hidden.value = digits;
                }
            });
        };

        window.rupiahDisplay = function (value) {
            const digits = String(value == null ? '' : value).replace(/[^\d]/g, '');
            return digits ? 'Rp ' + Number(digits).toLocaleString('id-ID') : '';
        };

        document.addEventListener('input', function (e) {
            const vis = e.target.closest('input[data-rupiah]');
            if (!vis) return;
            const digits = vis.value.replace(/[^\d]/g, '').slice(0, 15);
            vis.value = window.rupiahDisplay(digits);
            const hidden = vis.parentElement.querySelector('input[data-rupiah-sync]');
            if (hidden) hidden.value = digits;
        });

        document.addEventListener('change', function (e) {
            const vis = e.target.closest('input[data-rupiah]');
            if (!vis) return;
            const hidden = vis.parentElement.querySelector('input[data-rupiah-sync]');
            if (hidden) hidden.value = vis.value.replace(/[^\d]/g, '').slice(0, 15);
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-confirm]')) return;
            if (e.target.closest('button[type="submit"], input[type="submit"], form')) {
                window.flushRupiah && window.flushRupiah();
            }
        }, true);

        document.addEventListener('DOMContentLoaded', function () {
            window.formatAllRupiah && window.formatAllRupiah();
        });

        window.swalConfirm = function (options) {
            return Swal.fire({
                title: options.title || 'Apakah Anda yakin?',
                text: options.text || 'Data ini akan dihapus dan tidak dapat dikembalikan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: options.confirmText || 'Ya, hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#c0392b',
                cancelButtonColor: '#98a2b3',
                customClass: { popup: 'rounded-2xl' },
            }).then(function (result) {
                if (result.isConfirmed && options.action) options.action();
            });
        };

        window.hapusKonfirmasi = function (btn) {
            const form = btn.closest('form');
            const title = btn.getAttribute('data-confirm-title') || 'Hapus Data?';
            const text = btn.getAttribute('data-confirm-text') || 'Data ini akan dihapus dan tidak dapat dikembalikan.';
            swalConfirm({
                title,
                text,
                action: function () {
                    if (form) form.submit();
                },
            });
        };
    </script>
</body>

</html>