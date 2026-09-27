<?php
// pages/login.php
require_once __DIR__ . '/../includes/env_check.php';

$pageTitle = 'Masuk - Inventaris Toko Bangunan';
$errors = $_SESSION['login_errors'] ?? [];
$old = $_SESSION['login_old'] ?? [];
unset($_SESSION['login_errors'], $_SESSION['login_old']);

$envIssues = env_requirements();
$flash = get_flash();
ob_start();
?>
<div class="bg-white rounded-2xl shadow-lg border border-[#dce3ed] p-8">
    <div class="text-center mb-8">
        <div class="inline-flex items-center gap-3 mb-3">
            <i class="bi bi-building text-[#2c74b3] text-4xl"></i>
            <h1 class="text-2xl font-bold text-[#0a2647]">Sinar Jaya App</h1>
        </div>
        <p class="text-sm text-[#5a6a7a]">Inventory Management &mdash; Toko Bangunan</p>
    </div>

    <?php if ($envIssues): ?>
        <div class="mb-4 px-4 py-3 rounded-lg border border-[#f0d390] bg-[#fff7e6] text-sm">
            <p class="font-semibold text-[#8a5b00] mb-2"><i class="bi bi-exclamation-triangle"></i> Server belum memenuhi syarat aplikasi</p>
            <ul class="space-y-2">
                <?php foreach ($envIssues as $issue): ?>
                    <li class="text-[#8a5b00]">
                        <strong><?= e($issue['label']) ?>.</strong>
                        <?= e($issue['detail']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="mb-4 px-4 py-3 rounded-lg <?= $flash['type'] === 'error' ? 'bg-[#fde8e8] text-[#c0392b]' : 'bg-[#dff5e4] text-[#1a7a3a]' ?> text-sm">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/login" class="space-y-5">
        <input type="hidden" name="do" value="login">
        <div>
            <label for="username" class="block text-sm font-medium text-[#1a2332] mb-1.5">Username</label>
            <div class="relative">
                <i class="bi bi-person absolute left-3 top-1/2 -translate-y-1/2 text-[#5a6a7a]"></i>
                <input type="text" id="username" name="username" value="<?= e($old['username'] ?? '') ?>" autofocus autocomplete="username"
                       class="w-full pl-10 pr-4 py-2.5 border <?= isset($errors['username']) ? 'border-red-400' : 'border-[#dce3ed]' ?> rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2c74b3]">
            </div>
            <?php if (isset($errors['username'])): ?>
                <p class="mt-1 text-xs text-[#c0392b]"><?= e($errors['username']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-[#1a2332] mb-1.5">Password</label>
            <div class="relative">
                <i class="bi bi-lock absolute left-3 top-1/2 -translate-y-1/2 text-[#5a6a7a]"></i>
                <input type="password" id="password" name="password" autocomplete="current-password"
                       class="w-full pl-10 pr-4 py-2.5 border <?= isset($errors['password']) ? 'border-[#c0392b]' : 'border-[#c9cbd3]' ?> rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#2c74b3]">
            </div>
            <?php if (isset($errors['password'])): ?>
                <p class="mt-1 text-xs text-[#c0392b]"><?= e($errors['password']) ?></p>
            <?php endif; ?>
        </div>

        <label class="flex items-center gap-2 text-sm text-[#5a6a7a] cursor-pointer select-none">
            <input type="checkbox" name="remember" value="1" class="rounded border-[#c9cbd3] text-[#2c74b3] focus:ring-[#2c74b3]" <?= $remember ?? false ? 'checked' : '' ?>>
            Ingat saya
        </label>

        <button type="submit"
                class="w-full py-2.5 bg-[#205295] hover:bg-[#144272] text-white text-sm font-semibold rounded-lg transition cursor-pointer">
            <i class="bi bi-box-arrow-in-right"></i> Masuk
        </button>
    </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/guest.php';