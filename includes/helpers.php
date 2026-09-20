<?php

declare(strict_types=1);

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function sanitize(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function e(mixed $value): string
{
    return sanitize($value);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function nowDisplay(): string
{
    return date('d M Y, H:i');
}

function format_rupiah($angka): string
{
    $angka = (float)$angka;
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function format_number($angka, int $decimals = 2): string
{
    return number_format((float)$angka, $decimals, ',', '.');
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function get_input(string $key, $default = ''): string
{
    return trim($_POST[$key] ?? $_GET[$key] ?? $default);
}

function log_aktivitas(string $aksi, ?string $modul, ?int $idReferensi, string $deskripsi, ?array $dataPerubahan = null): void
{
    // prepare sekali per request, execute berkali-kali (static cache).
    // Kritis saat log_aktivitas dipanggil N kali dalam loop (misal: batch mutasi stok)
    // agar tidak terjadi N× rekompilasi SQL + alokasi statement baru.
    static $stmt = null;
    if ($stmt === null) {
        $stmt = db()->prepare(
            'INSERT INTO log_aktivitas
                (id_user, aksi, modul, id_referensi, deskripsi, data_perubahan, ip_address, user_agent, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
    }

    $userId   = auth_id();
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $jsonData = $dataPerubahan !== null ? json_encode($dataPerubahan, JSON_UNESCAPED_UNICODE) : null;
    $ts       = now();

    $stmt->execute([$userId, $aksi, $modul, $idReferensi, $deskripsi, $jsonData, $ip, $ua, $ts, $ts]);
}

function paginate(string $table, string $where, array $params, int $perPage = 15, string $orderBy = 'id DESC'): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    $countSql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
    $stmt = db()->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);

    $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
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
}

function indonesian_month_search(string $search, string $tableAlias = ''): array
{
    $conditions = [];
    $params     = [];
    $term       = mb_strtolower(trim($search));

    $bulanMap = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
    ];

    preg_match('/\b(19|20)\d{2}\b/', $term, $tahunMatches);
    $tahun = $tahunMatches[0] ?? null;
    $bulan = null;
    $hari  = null;

    foreach ($bulanMap as $nama => $no) {
        if (str_contains($term, $nama)) {
            $bulan = $no;
            break;
        }
    }

    preg_match('/\b(\d{1,2})\b/', $term, $hariMatches);
    if (isset($hariMatches[1]) && (int)$hariMatches[1] >= 1 && (int)$hariMatches[1] <= 31) {
        $hari = (int)$hariMatches[1];
    }

    $col    = $tableAlias ? "{$tableAlias}.tanggal" : 'tanggal';
    $bulanS = $bulan !== null ? str_pad((string)$bulan, 2, '0', STR_PAD_LEFT) : null;
    $hariS  = $hari  !== null ? str_pad((string)$hari,  2, '0', STR_PAD_LEFT) : null;

    // ── Range predicate — kolom tidak dibungkus fungsi sehingga index bisa dipakai.
    // Urutan prioritas: (hari+bulan+tahun) > (bulan+tahun) > (tahun) > (bulan) > (hari+bulan)

    if ($bulan !== null && $hari !== null && $tahun !== null) {
        // "15 September 2026" → range 1 hari penuh
        $start = "{$tahun}-{$bulanS}-{$hariS} 00:00:00";
        $end   = date('Y-m-d', strtotime($start) + 86400) . ' 00:00:00';
        $conditions[] = "{$col} >= ? AND {$col} < ?";
        $params[]     = $start;
        $params[]     = $end;

    } elseif ($bulan !== null && $tahun !== null) {
        // "September 2026" → range 1 bulan penuh
        $start  = "{$tahun}-{$bulanS}-01 00:00:00";
        // Bulan berikutnya: tambah 1 bulan lalu ambil hari pertama
        $endDt  = new DateTime("{$tahun}-{$bulanS}-01");
        $endDt->modify('+1 month');
        $end    = $endDt->format('Y-m-d') . ' 00:00:00';
        $conditions[] = "{$col} >= ? AND {$col} < ?";
        $params[]     = $start;
        $params[]     = $end;

    } elseif ($tahun !== null) {
        // "2026" → range 1 tahun penuh
        $conditions[] = "{$col} >= ? AND {$col} < ?";
        $params[]     = "{$tahun}-01-01 00:00:00";
        $params[]     = ((int)$tahun + 1) . '-01-01 00:00:00';

    } elseif ($bulan !== null && $hari !== null) {
        // "15 September" (tanpa tahun) → LIKE '____-09-15%'
        // Tidak ada leading wildcard pada segmen bulan-hari; SQLite bisa partial scan.
        $conditions[] = "{$col} LIKE ?";
        $params[]     = "____-{$bulanS}-{$hariS}%";

    } elseif ($bulan !== null) {
        // "September" (tanpa tahun) → LIKE '____-09-%'
        $conditions[] = "{$col} LIKE ?";
        $params[]     = "____-{$bulanS}-%";
    }

    return ['conditions' => $conditions, 'params' => $params];
}

function avatar_initials(string $nama): string
{
    $words = explode(' ', trim($nama));
    if (count($words) >= 2) {
        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1));
    }
    return mb_strtoupper(mb_substr($nama, 0, 2));
}