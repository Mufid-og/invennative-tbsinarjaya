<?php
/**
 * Pagination component.
 * Expects: $pagination (array from paginate()), $baseUrl (string), $queryParams (array of extra params)
 */
$pagination = $pagination ?? null;
if (!$pagination || !$pagination['hasPages']) return;
$baseUrl = $baseUrl ?? $_SERVER['REQUEST_URI'];
$queryParams = $queryParams ?? [];
$currentPage = $pagination['page'];
$totalPages = $pagination['totalPages'];
$firstItem = $pagination['firstItem'];
$lastItem = $pagination['lastItem'];
$total = $pagination['total'];

function pag_url(string $base, array $params, int $page): string
{
    $params['page'] = $page;
    return $base . '?' . http_build_query($params);
}
?>
<nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">
    <div class="flex justify-between flex-1 sm:hidden">
        <span>
            <?php if (!$pagination['hasPrev']): ?>
                <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-[#aab4c0] bg-white border border-[#e1e9f3] rounded-lg leading-5">&laquo; Sebelumnya</span>
            <?php else: ?>
                <a href="<?= e(pag_url($baseUrl, $queryParams, $currentPage - 1)) ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-[#5a6a7a] bg-white border border-[#c9d7e8] rounded-lg leading-5 hover:bg-[#eef4fb] hover:text-[#2c74b3] transition">&laquo; Sebelumnya</a>
            <?php endif; ?>
        </span>
        <span>
            <?php if (!$pagination['hasNext']): ?>
                <span class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-[#aab4c0] bg-white border border-[#e1e9f2] rounded-lg leading-5">Berikutnya &raquo;</span>
            <?php else: ?>
                <a href="<?= e(pag_url($baseUrl, $queryParams, $currentPage + 1)) ?>" class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-[#5a6a7a] bg-white border border-[#c9d7e8] rounded-lg leading-5 hover:bg-[#eef4fb] hover:text-[#2c74b3] transition">Berikutnya &raquo;</a>
            <?php endif; ?>
        </span>
    </div>

    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-[#5a6a7a] leading-5">
                Menampilkan <span class="font-medium text-[#1a2332]"><?= $firstItem ?></span>
                sampai <span class="font-medium text-[#1a2332]"><?= $lastItem ?></span>
                dari <span class="font-medium text-[#1a2332]"><?= $total ?></span> data
            </p>
        </div>

        <div>
            <span class="relative z-0 inline-flex rounded-lg border border-[#c9d7e8] bg-white shadow-sm overflow-hidden">
                <span>
                    <?php if ($pagination['hasPrev']): ?>
                        <a href="<?= e(pag_url($baseUrl, $queryParams, $currentPage - 1)) ?>" aria-label="Sebelumnya" class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-[#5a6a7a] bg-white border-r border-[#dde5ef] leading-5 hover:bg-[#eef4fb] hover:text-[#2c74b3] transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-[#aab4c0] bg-white border-r border-[#dde5ef] cursor-default leading-5" aria-hidden="true">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </span>
                    <?php endif; ?>
                </span>

                <?php
                $start = max(1, $currentPage - 2);
                $end = min($totalPages, $currentPage + 2);
                for ($p = $start; $p <= $end; $p++):
                ?>
                    <?php if ($p == $currentPage): ?>
                        <span aria-current="page">
                            <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-semibold text-white bg-[#2c74b3] border-r border-[#2c74b3] leading-5 cursor-default"><?= $p ?></span>
                        </span>
                    <?php else: ?>
                        <a href="<?= e(pag_url($baseUrl, $queryParams, $p)) ?>" class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-[#5a6a7a] bg-white border-r border-[#dde5ef] leading-5 hover:text-[#2c74b3] hover:bg-[#eef4fb] transition"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <span>
                    <?php if ($pagination['hasNext']): ?>
                        <a href="<?= e(pag_url($baseUrl, $queryParams, $currentPage + 1)) ?>" aria-label="Berikutnya" class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-[#5a6a7a] bg-white leading-5 hover:bg-[#eef4fb] hover:text-[#2c74b3] transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-[#aab4c0] bg-white leading-5" aria-hidden="true">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                        </span>
                    <?php endif; ?>
                </span>
            </span>
        </div>
    </div>
</nav>