<?php
// pages/cetak-log.php

use Dompdf\Dompdf;
use Dompdf\Options;

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$isDownload = ($_GET['download'] ?? '') === '1';

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

function build_lanjutkan_cetak_url(string $dari, string $sampai, bool $isDownload): string
{
    $params = [
        'dari' => $dari,
        'sampai' => $sampai,
        'confirm' => '1',
    ];
    if ($isDownload) {
        $params['download'] = '1';
    }
    return '?' . http_build_query($params);
}

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

            #confirm-loading {
                position: fixed;
                inset: 0;
                z-index: 9999;
                background: rgba(10, 38, 71, 0.55);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 14px;
            }

            #confirm-loading .cspinner {
                width: 44px;
                height: 44px;
                border-radius: 50%;
                border: 4px solid rgba(255, 255, 255, 0.35);
                border-top-color: #fff;
                animation: confirm-spin .8s linear infinite;
            }

            #confirm-loading .ctext {
                color: #fff;
                font-size: 14px;
                font-weight: 600;
            }

            @keyframes confirm-spin {
                to {
                    transform: rotate(360deg);
                }
            }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>Data terlalu besar untuk <?= $isDownload ? 'disimpan' : 'dicetak' ?></h2>
            <p>Periode ini berisi <strong><?= number_format($totalLogs, 0, ',', '.') ?></strong> baris aktivitas. Laporan berskala besar akan berat. Pertimbangkan mempersempit periode (maksimal 3 bulan) sebelum melanjutkan.</p>
            <div class="row">
                <a class="btn btn-primary" id="btn-confirm-simpan" href="<?= e(build_lanjutkan_cetak_url($dari, $sampai, $isDownload)) ?>">Lanjutkan <?= $isDownload ? 'Simpan' : 'Cetak' ?></a>
                <button class="btn btn-ghost" onclick="window.close()">Tutup</button>
            </div>
        </div>
        <script>
            (function() {
                var link = document.getElementById('btn-confirm-simpan');
                if (!link) return;

                function showLoading() {
                    if (document.getElementById('confirm-loading')) return;
                    var el = document.createElement('div');
                    el.id = 'confirm-loading';
                    el.innerHTML = '<div class="cspinner"></div><div class="ctext">Menyimpan laporan...</div>';
                    document.body.appendChild(el);
                }

                function hideLoading() {
                    var el = document.getElementById('confirm-loading');
                    if (el) el.remove();
                }

                link.addEventListener('click', function(e) {
                    var url = new URL(link.href, window.location.origin);
                    if (url.searchParams.get('download') !== '1') {
                        showLoading();
                        return;
                    }
                    e.preventDefault();
                    showLoading();
                    var filename = 'laporan-log-aktivitas_' + (url.searchParams.get('dari') || '') + '_sd_' + (url.searchParams.get('sampai') || '') + '.pdf';
                    fetch(url.toString(), {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/pdf'
                        },
                    }).then(function(res) {
                        if (!res.ok) throw new Error('Gagal membuat PDF.');
                        return res.blob();
                    }).then(function(blob) {
                        function saveBlob() {
                            var a = document.createElement('a');
                            var objectUrl = URL.createObjectURL(blob);
                            a.href = objectUrl;
                            a.download = filename;
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                            URL.revokeObjectURL(objectUrl);
                            hideLoading();
                        }
                        if (!window.showSaveFilePicker) {
                            saveBlob();
                            return;
                        }
                        window.showSaveFilePicker({
                            suggestedName: filename,
                            types: [{
                                description: 'PDF Document',
                                accept: {
                                    'application/pdf': ['.pdf']
                                },
                            }],
                        }).then(function(handle) {
                            return handle.createWritable().then(function(w) {
                                return w.write(blob).then(function() {
                                    return w.close();
                                });
                            });
                        }).then(hideLoading, function(err) {
                            hideLoading();
                            if (!err || err.name !== 'AbortError') saveBlob();
                        });
                    }).catch(function() {
                        hideLoading();
                        window.location.href = url.toString();
                    });
                });
            })();
        </script>
    </body>
    </html>
    <?php
    exit;
}

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

function laporan_log_filename(string $dari, string $sampai): string
{
    return 'laporan-log-aktivitas_' . preg_replace('/[^0-9-]/', '', $dari) . '_sd_' . preg_replace('/[^0-9-]/', '', $sampai) . '.pdf';
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
$filename = laporan_log_filename($dari, $sampai);

function render_laporan_log(
    string $dari,
    string $sampai,
    int $totalLogs,
    bool $warningPeriodeBesar,
    string $userName,
    string $filename,
    bool $includeToolbar,
    PDOStatement $stmt
): string {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Laporan Log Aktivitas — Sinar Jaya</title>
        <style>
            @page { size: A4 landscape; margin: 6mm 16mm 12mm; }

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
            .kop { border-bottom: 2px solid #0a2647; padding-bottom: 24px; }
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

            .pdf body,
            body.pdf { background: #fff; font-size: 11px; }
            .pdf .container { max-width: none; padding: 0; }
            .pdf .sheet { border: 0; border-radius: 0; box-shadow: none; padding: 8mm 16mm; }
            .pdf table { font-size: 10px; page-break-inside: auto; }
            .pdf tr { page-break-inside: avoid; page-break-after: auto; }
            .pdf thead { display: table-header-group; }
            .pdf thead th { font-size: 9px; padding: 6px 7px; }
            .pdf tbody td { padding: 5px 7px; }

            #report-loading {
                position: fixed;
                inset: 0;
                z-index: 9999;
                background: rgba(10, 38, 71, 0.55);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 14px;
            }

            #report-loading .report-spinner {
                width: 44px;
                height: 44px;
                border-radius: 50%;
                border: 4px solid rgba(255, 255, 255, 0.35);
                border-top-color: #fff;
                animation: report-spin .8s linear infinite;
            }

            #report-loading .report-loading-text {
                color: #fff;
                font-size: 14px;
                font-weight: 600;
            }

            @keyframes report-spin {
                to {
                    transform: rotate(360deg);
                }
            }
        </style>
    </head>

    <body class="<?= $includeToolbar ? '' : 'pdf' ?>">
        <?php if ($includeToolbar): ?>
            <div class="toolbar">
                <div class="toolbar-inner">
                    <div class="toolbar-title">Pratinjau Laporan Log Aktivitas</div>
                    <div>
                        <button class="btn btn-primary" type="button" id="btn-simpan" onclick="saveLaporanLog()">Simpan</button>
                        <button class="btn btn-ghost" type="button" onclick="window.close()">Tutup</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="container">
            <div class="sheet">
                <div class="kop">
                    <table style="width:100%; border:0; border-collapse:collapse;">
                        <tr>
                            <td style="text-align:left; vertical-align:middle; border:0; padding:0; width:50%;">
                                <h1>Sinar Jaya</h1>
                                <p class="kop-sub">Toko Bangunan &amp; Material</p>
                            </td>
                            <td class="kop-right" style="text-align:right; vertical-align:middle; border:0; padding:0; width:50%;">
                                <h2>Laporan Log Aktivitas</h2>
                                <p><?= e(format_tanggal_kop($dari)) ?> — <?= e(format_tanggal_kop($sampai)) ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="summary">Total <strong><?= $totalLogs ?></strong> catatan aktivitas</div>

                <?php if ($warningPeriodeBesar): ?>
                    <div class="warning-box">Periode lebih dari 3 bulan — jumlah data bisa sangat banyak. Persempit periode agar proses lebih ringan.</div>
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
                        <p class="sign-name" style="margin-top:30px; font-weight:600; color:#1a2332;"><?= e($userName) ?></p>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($includeToolbar): ?>
            <script>
                async function saveLaporanLog() {
                    const filename = <?= json_encode($filename) ?>;
                    const url = new URL(window.location.href);
                    url.searchParams.set('download', '1');
                    setReportLoading(true);

                    try {
                        const response = await fetch(url.toString(), {
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/pdf'
                            },
                        });
                        if (!response.ok) {
                            throw new Error('Gagal membuat PDF.');
                        }
                        const contentType = response.headers.get('Content-Type') || '';
                        const blob = await response.blob();
                        if (!contentType.includes('application/pdf')) {
                            throw new Error('Tanggapan bukan PDF.');
                        }

                        if (window.showSaveFilePicker) {
                            setReportLoading(false);
                            const handle = await window.showSaveFilePicker({
                                suggestedName: filename,
                                types: [{
                                    description: 'PDF Document',
                                    accept: {
                                        'application/pdf': ['.pdf']
                                    },
                                }],
                            });
                            const writable = await handle.createWritable();
                            await writable.write(blob);
                            await writable.close();
                        } else {
                            saveBlobDownload(blob, filename);
                            setReportLoading(false);
                        }
                    } catch (error) {
                        setReportLoading(false);
                        if (error && error.name === 'AbortError') {
                            return;
                        }
                        fallbackDownload(url.toString(), filename);
                    }
                }

                function saveBlobDownload(blob, filename) {
                    const link = document.createElement('a');
                    const objectUrl = URL.createObjectURL(blob);
                    link.href = objectUrl;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(objectUrl);
                }

                function setReportLoading(on) {
                    const btn = document.getElementById('btn-simpan');
                    if (btn) {
                        btn.disabled = on;
                    }
                    if (on) {
                        if (document.getElementById('report-loading')) {
                            return;
                        }
                        const overlay = document.createElement('div');
                        overlay.id = 'report-loading';
                        overlay.innerHTML = '<div class="report-spinner"></div><div class="report-loading-text">Menyimpan laporan...</div>';
                        document.body.appendChild(overlay);
                        return;
                    }
                    const overlay = document.getElementById('report-loading');
                    if (overlay) {
                        overlay.remove();
                    }
                }

                function fallbackDownload(url, filename) {
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                }
            </script>
        <?php endif; ?>
    </body>

    </html>
    <?php
    return (string)ob_get_clean();
}

$html = render_laporan_log(
    $dari,
    $sampai,
    $totalLogs,
    $warningPeriodeBesar,
    $userName,
    $filename,
    !$isDownload,
    $stmt
);

if ($isDownload) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        http_response_code(500);
        echo 'Dependency PDF belum tersedia. Jalankan composer install terlebih dahulu.';
        exit;
    }

    require_once $autoload;

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    $pdf = $dompdf->output();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    echo $pdf;
    exit;
}

echo $html;