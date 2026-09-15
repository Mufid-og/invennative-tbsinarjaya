<?php
// pages/cetak-mutasi.php

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

$dariTgl = $dari . ' 00:00:00';
$sampaiTgl = $sampai . ' 23:59:59';

function bulan_indonesia(int $bulan): string
{
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    return $months[$bulan] ?? '';
}

function format_tanggal($dateStr): string
{
    $dt = new DateTime($dateStr);
    return $dt->format('d') . ' ' . bulan_indonesia((int)$dt->format('m')) . ' ' . $dt->format('Y H:i');
}

function format_tanggal_kop(string $dateStr): string
{
    $dt = new DateTime($dateStr);
    return $dt->format('d') . ' ' . bulan_indonesia((int)$dt->format('m')) . ' ' . $dt->format('Y');
}

// Rekap per ukuran
$rekapStmt = db()->prepare("
    SELECT p.nama as produk, me.merek, u.ukuran, u.stok as stok_akhir,
           SUM(CASE WHEN ms.jenis = 'masuk' THEN ms.qty ELSE 0 END) as total_masuk,
           SUM(CASE WHEN ms.jenis = 'keluar' THEN ms.qty ELSE 0 END) as total_keluar
    FROM mutasi_stok ms
    JOIN ukuran u ON u.id = ms.id_ukuran
    JOIN merek me ON me.id = u.id_merek
    JOIN produk p ON p.id = me.id_produk
    WHERE ms.deleted_at IS NULL AND ms.tanggal BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY p.nama, me.merek, u.ukuran
");
$rekapStmt->execute([$dariTgl, $sampaiTgl]);
$rekapRows = $rekapStmt->fetchAll(PDO::FETCH_ASSOC);

// Detail mutasi (masuk/keluar)
$detail = [];
$stmt = db()->prepare("
    SELECT ms.tanggal, ms.jenis, ms.qty, ms.harga_modal, ms.harga_jual, ms.keterangan,
           p.nama as produk, me.merek, u.ukuran
    FROM mutasi_stok ms
    JOIN ukuran u ON u.id = ms.id_ukuran
    JOIN merek me ON me.id = u.id_merek
    JOIN produk p ON p.id = me.id_produk
    WHERE ms.deleted_at IS NULL AND ms.tanggal BETWEEN ? AND ?
");
$stmt->execute([$dariTgl, $sampaiTgl]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $detail[] = [
        'tanggal'      => $row['tanggal'],
        'produk'       => $row['produk'] . ' ' . $row['merek'] . ' - ' . $row['ukuran'],
        'tipe'         => $row['jenis'] === 'masuk' ? 'Masuk' : 'Keluar',
        'qty'          => $row['qty'],
        'selisih'      => null,
        'harga_modal'  => $row['harga_modal'],
        'harga_jual'   => $row['harga_jual'],
        'keterangan'   => $row['keterangan'],
    ];
}

// Detail verifikasi stok
$stmt = db()->prepare("
    SELECT v.tanggal, v.selisih, v.keterangan,
           p.nama as produk, me.merek, u.ukuran
    FROM verifikasi_stok v
    JOIN ukuran u ON u.id = v.id_ukuran
    JOIN merek me ON me.id = u.id_merek
    JOIN produk p ON p.id = me.id_produk
    WHERE v.deleted_at IS NULL AND v.tanggal BETWEEN ? AND ?
");
$stmt->execute([$dariTgl, $sampaiTgl]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $detail[] = [
        'tanggal'      => $row['tanggal'],
        'produk'       => $row['produk'] . ' ' . $row['merek'] . ' - ' . $row['ukuran'],
        'tipe'         => 'Verifikasi',
        'qty'          => null,
        'selisih'      => $row['selisih'],
        'harga_modal'  => null,
        'harga_jual'   => null,
        'keterangan'   => $row['keterangan'],
    ];
}

usort($detail, function ($a, $b) {
    return strcmp($a['tanggal'], $b['tanggal']);
});

// Rowspan grouping per produk & merek
$rowCount = count($rekapRows);
$produkRowspan = [];
$merekRowspan = [];
foreach ($rekapRows as $i => $r) {
    $prevProduk = $i > 0 ? $rekapRows[$i - 1]['produk'] : null;
    $prevMerek  = $i > 0 ? $rekapRows[$i - 1]['merek'] : null;

    if ($i === 0 || $prevProduk !== $r['produk']) {
        $j = $i;
        while ($j < $rowCount && $rekapRows[$j]['produk'] === $r['produk']) {
            $j++;
        }
        $produkRowspan[$i] = $j - $i;
    }

    if ($i === 0 || $prevProduk !== $r['produk'] || $prevMerek !== $r['merek']) {
        $j = $i;
        while ($j < $rowCount && $rekapRows[$j]['produk'] === $r['produk'] && $rekapRows[$j]['merek'] === $r['merek']) {
            $j++;
        }
        $merekRowspan[$i] = $j - $i;
    }
}

$user = auth_user();
$userName = $user['nama'] ?? '________';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Mutasi Stok - Sinar Jaya</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: #eef2f7; color: #1a2332; font-size: 14px; }
        .toolbar { position: sticky; top: 0; z-index: 40; background: #0a2647; color: #fff; }
        .toolbar-inner { max-width: 1080px; margin: 0 auto; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; }
        .toolbar-title { display: flex; align-items: center; gap: 10px; font-weight: 600; }
        .btn { border: 0; border-radius: 8px; padding: 9px 16px; font-size: 13px; font-weight: 500; cursor: pointer; color: #fff; }
        .btn-primary { background: #2c74b3; }
        .btn-primary:hover { background: #205295; }
        .btn-ghost { background: rgba(255,255,255,0.12); }
        .btn-ghost:hover { background: rgba(255,255,255,0.22); }
        .container { max-width: 1080px; margin: 0 auto; padding: 32px 24px; }
        .sheet { background: #fff; border: 1px solid #dce3ed; border-radius: 12px; padding: 32px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .kop { display: flex; align-items: flex-start; justify-content: space-between; border-bottom: 2px solid #0a2647; padding-bottom: 24px; }
        .kop h1 { font-size: 22px; }
        .kop-sub { color: #5a6a7a; margin-top: 4px; }
        .kop-right { text-align: right; }
        .kop-right h2 { font-size: 17px; }
        .kop-right p { color: #5a6a7a; margin-top: 4px; }
        h3 { font-size: 15px; margin: 28px 0 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #dce3ed; border-radius: 8px; overflow: hidden; }
        thead { background: #f0f4f9; }
        thead th { text-align: left; text-transform: uppercase; font-size: 11px; letter-spacing: 0.04em; color: #5a6a7a; font-weight: 600; padding: 10px 12px; border-bottom: 1px solid #dce3ed; }
        tbody td { padding: 9px 12px; border-bottom: 1px solid #e4e9f0; vertical-align: top; }
        tbody tr:last-child td { border-bottom: 0; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-in { background: #dff5e4; color: #1a7a3a; }
        .badge-out { background: #fde8e8; color: #c0392b; }
        .badge-verif { background: #e1edfa; color: #205295; }
        .green { color: #1a7a3a; }
        .red { color: #c0392b; }
        .empty { text-align: center; color: #5a6a7a; padding: 28px 12px; }
        .footer-print { display: flex; justify-content: space-between; margin-top: 36px; font-size: 13px; color: #5a6a7a; }
        .sign { text-align: center; }
        .sign-name { margin-top: 30px; font-weight: 600; color: #1a2332; }
        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; }
            .sheet { border: 0; box-shadow: none; padding: 0; }
            thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <div class="toolbar-inner">
            <div class="toolbar-title">Pratinjau Laporan Mutasi Stok</div>
            <div>
                <button class="btn btn-primary" onclick="window.print()">Cetak</button>
                <button class="btn btn-ghost" onclick="window.close()">Tutup</button>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="sheet">
            <div class="kop">
                <div>
                    <h1>Sinar Jaya</h1>
                    <p class="kop-sub">Toko Bangunan &amp; Material</p>
                </div>
                <div class="kop-right">
                    <h2>Laporan Mutasi Stok</h2>
                    <p><?= e(format_tanggal_kop($dari)) ?> — <?= e(format_tanggal_kop($sampai)) ?></p>
                </div>
            </div>

            <h3>Rekap Per Produk - Ukuran</h3>
            <table>
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Merek</th>
                        <th>Ukuran</th>
                        <th>Stok Awal</th>
                        <th>Masuk</th>
                        <th>Keluar</th>
                        <th>Stok Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rekapRows)): ?>
                        <tr><td colspan="7" class="empty">Tidak ada data mutasi pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rekapRows as $i => $r): ?>
                            <?php $stokAwal = (float)$r['stok_akhir'] - ((float)$r['total_masuk'] - (float)$r['total_keluar']); ?>
                            <tr>
                                <?php if (isset($produkRowspan[$i])): ?>
                                    <td rowspan="<?= $produkRowspan[$i] ?>" style="vertical-align:top; font-weight:600;"><?= e($r['produk']) ?></td>
                                <?php endif; ?>
                                <?php if (isset($merekRowspan[$i])): ?>
                                    <td rowspan="<?= $merekRowspan[$i] ?>" style="vertical-align:top; font-weight:600;"><?= e($r['merek']) ?></td>
                                <?php endif; ?>
                                <td><?= e($r['ukuran']) ?></td>
                                <td><?= format_number($stokAwal) ?></td>
                                <td class="green"><?= format_number($r['total_masuk']) ?></td>
                                <td class="red"><?= format_number($r['total_keluar']) ?></td>
                                <td><strong><?= format_number($r['stok_akhir']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3>Detail Mutasi</h3>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Produk</th>
                        <th>Tipe</th>
                        <th>Qty</th>
                        <th>Selisih</th>
                        <th>Harga Modal</th>
                        <th>Harga Jual</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($detail)): ?>
                        <tr><td colspan="8" class="empty">Tidak ada data pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($detail as $d): ?>
                            <tr>
                                <td><?= e(format_tanggal($d['tanggal'])) ?></td>
                                <td><?= e($d['produk']) ?></td>
                                <td>
                                    <?php if ($d['tipe'] === 'Verifikasi'): ?>
                                        <span class="badge badge-verif">Verifikasi</span>
                                    <?php elseif ($d['tipe'] === 'Masuk'): ?>
                                        <span class="badge badge-in">Masuk</span>
                                    <?php else: ?>
                                        <span class="badge badge-out">Keluar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($d['tipe'] !== 'Verifikasi'): ?>
                                        <?= format_number($d['qty']) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($d['tipe'] === 'Verifikasi'): ?>
                                        <span class="<?= $d['selisih'] >= 0 ? 'green' : 'red' ?>"><?= format_number($d['selisih']) ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($d['tipe'] !== 'Verifikasi'): ?>
                                        <?= format_rupiah($d['harga_modal']) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($d['tipe'] !== 'Verifikasi'): ?>
                                        <?= format_rupiah($d['harga_jual']) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= e($d['keterangan']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="footer-print">
                <span></span>
                <span class="sign">
                    <p>Dicetak pada <?= e(format_tanggal(now())) ?></p>
                    <p style="margin-top:30px; font-weight:600; color:#1a2332;"><?= e($userName) ?></p>
                </span>
            </div>
        </div>
    </div>
</body>

</html>