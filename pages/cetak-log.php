<?php
// pages/cetak-log.php

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

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

// ===== Guard & streaming: COUNT dulu (1 angka), jangan fetchAll semua baris =====
// Range predicate langsung di kolom (bukan date() function) agar idx_log_aktivitas_created_id
// bisa dipakai — date(created_at) di WHERE mencegah index digunakan.
// Batas atas: awal hari setelah $sampai (exclusive <) menggantikan inclusive BETWEEN.
$sampaiNext = date('Y-m-d', strtotime($sampai . ' +1 day')) . ' 00:00:00';
$dariStart  = $dari . ' 00:00:00';

$countStmt = db()->prepare("
    SELECT COUNT(*)
    FROM log_aktivitas l
    WHERE l.created_at >= ? AND l.created_at < ?
");
$countStmt->execute([$dariStart, $sampaiNext]);
$totalLogs = (int)$countStmt->fetchColumn();

// Peringatan bila periode lintas lebih dari 3 bulan
$dariObj = new DateTime($dari);
$sampaiObj = new DateTime($sampai);
$selisih = $dariObj->diff($sampaiObj);
$bulanPeriode = $selisih->y * 12 + $selisih->m;
$warningPeriodeBesar = $bulanPeriode > 3;
unset($dariObj, $sampaiObj, $selisih);

// Konfirmasi bila data lebih dari 10.000 baris (sebelum render penuh)
if ($totalLogs > 10000 && !isset($_GET['confirm'])) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Konfirmasi — Laporan Log Aktivitas</title>
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family:'Segoe UI',Roboto,Arial,sans-serif; background:#eef2f7; color:#1a2332; }
            .card { max-width:520px; margin:70px auto; background:#fff; border:1px solid #dce3ed; border-radius:12px; padding:28px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
            .card h2 { margin-top:0; font-size:18px; }
            .card p { color:#5a6a7a; font-size:14px; line-height:1.6; }
            .row { display:flex; gap:10px; margin-top:20px; }
            .btn { border:0; border-radius:8px; padding:10px 16px; font-size:13px; font-weight:600; cursor:pointer; color:#fff; text-decoration:none; }
            .btn-primary { background:#2c74b3; }
            .btn-ghost { background:#c3cbd5; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>Data terlalu besar untuk dicetak</h2>
            <p>Periode ini berisi <strong><?= number_format($totalLogs, 0, ',', '.') ?></strong> baris aktivitas. Cetakan berskala besar akan berat. Pertimbangkan mempersempit periode (maksimal 3 bulan) sebelum mencetak.</p>
            <div class="row">
                <a class="btn btn-primary" href="?dari=<?= e(urlencode($dari)) ?>&sampai=<?= e(urlencode($sampai)) ?>&confirm=1">Lanjutkan Cetak</a>
                <button class="btn btn-ghost" onclick="window.close()">Tutup</button>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Streaming: fetch per baris, bukan fetchAll (memori PHP konstan)
// Range predicate: idx_log_aktivitas_created_id (created_at DESC, id DESC) dipakai
// untuk filter DAN sort sekaligus — tidak perlu filesort terpisah.
$stmt = db()->prepare("
    SELECT l.*, us.nama as user_nama
    FROM log_aktivitas l
    LEFT JOIN users us ON us.id = l.id_user
    WHERE l.created_at >= ? AND l.created_at < ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$dariStart, $sampaiNext]);
$stmt->setFetchMode(PDO::FETCH_ASSOC);

$user = auth_user();
$userName = $user['nama'] ?? '________';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Log Aktivitas — Sinar Jaya</title>
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
        .summary { color: #5a6a7a; margin: 22px 0 12px; }
        .warning-box { background:#fff7e6; border:1px solid #f0d390; color:#8a5b00; padding:10px 14px; border-radius:8px; font-size:13px; margin:0 0 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #dce3ed; border-radius: 8px; overflow: hidden; }
        thead { background: #f0f4f9; }
        thead th { text-align: left; text-transform: uppercase; font-size: 11px; letter-spacing: 0.04em; color: #5a6a7a; font-weight: 600; padding: 10px 12px; border-bottom: 1px solid #dce3ed; }
        tbody td { padding: 9px 12px; border-bottom: 1px solid #e4e9f0; vertical-align: top; }
        tbody tr:last-child td { border-bottom: 0; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-create { background: #dff5e4; color: #1a7a3a; }
        .badge-update { background: #e1edfa; color: #205295; }
        .badge-delete { background: #fde8e8; color: #c0392b; }
        .badge-other { background: #eef2f7; color: #5a6a7a; }
        .empty { text-align: center; color: #5a6a7a; padding: 28px 12px; }
        .footer-print { display: flex; justify-content: space-between; margin-top: 36px; font-size: 13px; color: #5a6a7a; }
        .sign { text-align: center; }
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
            <div class="toolbar-title">Pratinjau Laporan Log Aktivitas</div>
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
                    <h2>Laporan Log Aktivitas</h2>
                    <p><?= e(format_tanggal_kop($dari)) ?> — <?= e(format_tanggal_kop($sampai)) ?></p>
                </div>
            </div>

            <div class="summary">Total <strong><?= $totalLogs ?></strong> catatan aktivitas</div>

            <?php if ($warningPeriodeBesar): ?>
                <div class="warning-box">Periode lebih dari 3 bulan — jumlah data bisa sangat banyak. Persempit periode agar proses cetak lebih ringan.</div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Aksi</th>
                        <th>Modul</th>
                        <th>Deskripsi</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalLogs === 0): ?>
                        <tr><td colspan="6" class="empty">Tidak ada data pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php while ($log = $stmt->fetch()): ?>
                            <tr>
                                <td><?= e(format_tanggal($log['created_at'])) ?></td>
                                <td><?= e($log['user_nama'] !== null && $log['user_nama'] !== '' ? $log['user_nama'] : 'Sistem') ?></td>
                                <td>
                                    <?php if ($log['aksi'] === 'CREATE'): ?>
                                        <span class="badge badge-create"><?= e($log['aksi']) ?></span>
                                    <?php elseif ($log['aksi'] === 'UPDATE'): ?>
                                        <span class="badge badge-update"><?= e($log['aksi']) ?></span>
                                    <?php elseif ($log['aksi'] === 'DELETE'): ?>
                                        <span class="badge badge-delete"><?= e($log['aksi']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-other"><?= e($log['aksi']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($log['modul']) ?></td>
                                <td><?= e($log['deskripsi']) ?></td>
                                <td><?= e($log['ip_address']) ?></td>
                            </tr>
                        <?php endwhile; ?>
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