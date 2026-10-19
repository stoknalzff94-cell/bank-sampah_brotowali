<?php
session_start();

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

include "koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    die("Akses ditolak. Halaman ini khusus guru.");
}

$nama_guru = $_SESSION['nama_user']
    ?? $_SESSION['nama']
    ?? $_SESSION['username']
    ?? 'Guru';


/* =========================================================
   FILTER TANGGAL
========================================================= */

$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_awal)) {
    $tanggal_awal = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir)) {
    $tanggal_akhir = date('Y-m-d');
}


/* =========================================================
   HELPER
========================================================= */

function rupiah($angka)
{
    return 'Rp ' . number_format(
        (float)$angka,
        0,
        ',',
        '.'
    );
}

function angka($angka)
{
    return number_format(
        (float)$angka,
        0,
        ',',
        '.'
    );
}


/* =========================================================
   DATA TRANSAKSI
========================================================= */

$stmt = mysqli_prepare($koneksi, "
    SELECT
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(berat), 0) AS total_berat,
        COALESCE(SUM(total), 0) AS total_nilai
    FROM transaksi
    WHERE status = 'disetujui'
    AND tanggal BETWEEN ? AND ?
");

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $tanggal_awal,
    $tanggal_akhir
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data_transaksi =
    mysqli_fetch_assoc($result)
    ?: [];

mysqli_stmt_close($stmt);


$jumlah_transaksi =
    (int)($data_transaksi['jumlah_transaksi'] ?? 0);

$total_berat =
    (float)($data_transaksi['total_berat'] ?? 0);

$total_nilai =
    (float)($data_transaksi['total_nilai'] ?? 0);


/* =========================================================
   PENARIKAN
========================================================= */

$stmt = mysqli_prepare($koneksi, "
    SELECT
        COUNT(*) AS jumlah_penarikan,
        COALESCE(SUM(jumlah), 0) AS total_penarikan
    FROM tarik_saldo
    WHERE tanggal BETWEEN ? AND ?
");

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $tanggal_awal,
    $tanggal_akhir
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data_tarik =
    mysqli_fetch_assoc($result)
    ?: [];

mysqli_stmt_close($stmt);


$jumlah_penarikan =
    (int)($data_tarik['jumlah_penarikan'] ?? 0);

$total_penarikan =
    (float)($data_tarik['total_penarikan'] ?? 0);


/* =========================================================
   SALDO PERIODE
========================================================= */

$saldo_periode =
    $total_nilai - $total_penarikan;


/* =========================================================
   STATUS TRANSAKSI
========================================================= */

$stmt = mysqli_prepare($koneksi, "
    SELECT
        SUM(status = 'pending') AS pending,
        SUM(status = 'disetujui') AS disetujui,
        SUM(status = 'ditolak') AS ditolak
    FROM transaksi
    WHERE tanggal BETWEEN ? AND ?
");

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $tanggal_awal,
    $tanggal_akhir
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data_status =
    mysqli_fetch_assoc($result)
    ?: [];

mysqli_stmt_close($stmt);


$pending =
    (int)($data_status['pending'] ?? 0);

$disetujui =
    (int)($data_status['disetujui'] ?? 0);

$ditolak =
    (int)($data_status['ditolak'] ?? 0);


/* =========================================================
   JUMLAH NASABAH AKTIF
========================================================= */

$stmt = mysqli_prepare($koneksi, "
    SELECT COUNT(DISTINCT id_nasabah) AS total
    FROM transaksi
    WHERE tanggal BETWEEN ? AND ?
");

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $tanggal_awal,
    $tanggal_akhir
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data_nasabah =
    mysqli_fetch_assoc($result)
    ?: [];

mysqli_stmt_close($stmt);

$nasabah_aktif =
    (int)($data_nasabah['total'] ?? 0);


/* =========================================================
   GRAFIK HARIAN
========================================================= */

$chart_data = [];

$stmt = mysqli_prepare($koneksi, "
    SELECT
        tanggal,
        COALESCE(SUM(total), 0) AS total,
        COALESCE(SUM(berat), 0) AS berat
    FROM transaksi
    WHERE status = 'disetujui'
    AND tanggal BETWEEN ? AND ?
    GROUP BY tanggal
    ORDER BY tanggal ASC
");

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $tanggal_awal,
    $tanggal_akhir
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {

    $chart_data[] = [
        'tanggal' => $row['tanggal'],
        'total' => (float)$row['total'],
        'berat' => (float)$row['berat']
    ];
}

mysqli_stmt_close($stmt);


/* =========================================================
   TOP NASABAH
========================================================= */

$top_nasabah = [];

$stmt = mysqli_prepare($koneksi, "
    SELECT
        n.nama,
        COUNT(t.id_transaksi) AS jumlah_transaksi,
        COALESCE(SUM(t.berat), 0) AS total_berat,
        COALESCE(SUM(t.total), 0) AS total_nilai
    FROM transaksi t
    INNER JOIN nasabah n
        ON n.id_nasabah = t.id_nasabah
    WHERE t.status = 'disetujui'
    AND t.tanggal BETWEEN ? AND ?
    GROUP BY n.id_nasabah, n.nama
    ORDER BY total_nilai DESC
    LIMIT 5
");

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $tanggal_awal,
    $tanggal_akhir
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $top_nasabah[] = $row;
}

mysqli_stmt_close($stmt);


/* =========================================================
   DETAIL TRANSAKSI
========================================================= */

$detail_transaksi = [];

$stmt = mysqli_prepare($koneksi, "
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.berat,
        t.harga,
        t.total,
        t.status,
        n.nama AS nama_nasabah,
        s.nama_sampah
    FROM transaksi t

    INNER JOIN nasabah n
        ON n.id_nasabah = t.id_nasabah

    INNER JOIN sampah s
        ON s.id_sampah = t.id_sampah

    WHERE t.tanggal BETWEEN ? AND ?

    ORDER BY t.tanggal DESC,
             t.id_transaksi DESC

    LIMIT 100
");

mysqli_stmt_bind_param(
    $stmt,
    "ss",
    $tanggal_awal,
    $tanggal_akhir
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $detail_transaksi[] = $row;
}

mysqli_stmt_close($stmt);


/* =========================================================
   PERSENTASE STATUS
========================================================= */

$total_status =
    $pending +
    $disetujui +
    $ditolak;

if ($total_status > 0) {

    $persen_disetujui =
        round(($disetujui / $total_status) * 100);

    $persen_pending =
        round(($pending / $total_status) * 100);

    $persen_ditolak =
        round(($ditolak / $total_status) * 100);

} else {

    $persen_disetujui = 0;
    $persen_pending = 0;
    $persen_ditolak = 0;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Laporan Guru — Bank Sampah Ataya</title>

<style>

:root {

    --bg: #070b18;
    --sidebar: #0b1022;

    --card: #10172a;
    --card-2: #131c33;

    --border: #202a46;

    --primary: #6366f1;
    --violet: #8b5cf6;
    --blue: #38bdf8;

    --success: #34d399;
    --warning: #fbbf24;
    --danger: #fb7185;

    --text: #f8fafc;
    --muted: #94a3b8;
    --muted-2: #64748b;
}

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    min-height: 100vh;

    font-family:
        Inter,
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    color: var(--text);

    background:

        radial-gradient(
            circle at 85% 5%,
            rgba(99,102,241,.14),
            transparent 28%
        ),

        radial-gradient(
            circle at 5% 90%,
            rgba(139,92,246,.09),
            transparent 30%
        ),

        var(--bg);
}

::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #070b18;
}

::-webkit-scrollbar-thumb {

    background: #293454;

    border-radius: 20px;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    top: 0;
    left: 0;
    bottom: 0;

    width: 270px;

    padding: 24px 18px;

    background:
        linear-gradient(
            180deg,
            rgba(14,20,43,.98),
            rgba(8,13,29,.98)
        );

    border-right:
        1px solid
        rgba(255,255,255,.06);

    z-index: 100;
}

.brand {

    display: flex;

    align-items: center;

    gap: 13px;

    padding:
        5px 10px 26px;
}

.brand-logo {

    width: 43px;
    height: 43px;

    display: grid;

    place-items: center;

    border-radius: 14px;

    background:
        linear-gradient(
            135deg,
            var(--primary),
            var(--violet)
        );

    box-shadow:
        0 0 30px
        rgba(99,102,241,.35);

    font-size: 20px;
}

.brand-title {

    font-weight: 800;

    letter-spacing: -.4px;
}

.brand-subtitle {

    margin-top: 3px;

    color: var(--muted-2);

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 1.5px;
}

.section-label {

    padding:
        16px 12px 9px;

    color: #596681;

    font-size: 10px;

    font-weight: 700;

    letter-spacing: 1.5px;

    text-transform: uppercase;
}

.nav {

    display: flex;

    flex-direction: column;

    gap: 5px;
}

.nav a {

    position: relative;

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        12px 13px;

    border-radius: 12px;

    color: #8e9bb4;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

    transition: .25s ease;
}

.nav a:hover {

    color: #fff;

    background:
        rgba(99,102,241,.09);

    transform:
        translateX(3px);
}

.nav a.active {

    color: #fff;

    background:
        linear-gradient(
            135deg,
            rgba(99,102,241,.24),
            rgba(139,92,246,.12)
        );

    border:
        1px solid
        rgba(99,102,241,.20);
}

.nav a.active::before {

    content: "";

    position: absolute;

    left: -1px;

    top: 10px;
    bottom: 10px;

    width: 3px;

    border-radius: 10px;

    background:
        linear-gradient(
            180deg,
            var(--blue),
            var(--violet)
        );
}

.nav-icon {

    width: 22px;

    text-align: center;

    font-size: 17px;
}

.profile-box {

    margin-top: 25px;

    padding: 15px;

    border-radius: 16px;

    background:
        linear-gradient(
            135deg,
            rgba(99,102,241,.11),
            rgba(139,92,246,.05)
        );

    border:
        1px solid
        rgba(99,102,241,.14);
}

.profile-label {

    color: var(--muted-2);

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 1.2px;
}

.profile-name {

    margin-top: 5px;

    font-size: 14px;

    font-weight: 750;
}

.profile-role {

    display: inline-flex;

    margin-top: 5px;

    padding:
        4px 8px;

    border-radius: 999px;

    background:
        rgba(99,102,241,.12);

    color: #a5b4fc;

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 270px;

    padding:
        28px 34px 50px;

    min-height: 100vh;
}

.topbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    height: 62px;

    margin-bottom: 20px;
}

.breadcrumb {

    color: var(--muted-2);

    font-size: 12px;
}

.breadcrumb strong {
    color: #dbe4f5;
}

.clock {

    display: flex;

    align-items: center;

    gap: 9px;

    padding:
        9px 13px;

    border:
        1px solid var(--border);

    border-radius: 12px;

    background:
        rgba(16,23,42,.72);

    color: var(--muted);

    font-size: 11px;
}

.live-dot {

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: var(--success);

    box-shadow:
        0 0 0 4px
        rgba(52,211,153,.08),

        0 0 14px
        rgba(52,211,153,.65);
}


/* =========================================================
   HERO
========================================================= */

.hero {

    position: relative;

    overflow: hidden;

    padding: 29px;

    border-radius: 22px;

    background:

        radial-gradient(
            circle at 85% 20%,
            rgba(56,189,248,.12),
            transparent 25%
        ),

        radial-gradient(
            circle at 65% 100%,
            rgba(139,92,246,.18),
            transparent 40%
        ),

        linear-gradient(
            135deg,
            #10172d,
            #0d1429
        );

    border:
        1px solid
        rgba(99,102,241,.18);

    box-shadow:
        0 25px 70px
        rgba(0,0,0,.25);
}

.hero-content {
    position: relative;
    z-index: 2;
}

.eyebrow {

    display: inline-flex;

    padding:
        7px 10px;

    border-radius: 999px;

    background:
        rgba(99,102,241,.10);

    border:
        1px solid
        rgba(99,102,241,.15);

    color: #a5b4fc;

    font-size: 10px;

    font-weight: 800;

    letter-spacing: 1.1px;

    text-transform: uppercase;
}

.hero h1 {

    margin:
        15px 0 7px;

    font-size: 32px;

    letter-spacing: -1px;
}

.hero p {

    max-width: 700px;

    margin: 0;

    color: #8997b1;

    font-size: 13px;

    line-height: 1.7;
}


/* =========================================================
   FILTER
========================================================= */

.filter {

    margin-top: 18px;

    padding: 18px;

    border-radius: 18px;

    background:
        rgba(16,23,42,.92);

    border:
        1px solid var(--border);
}

.filter-form {

    display: grid;

    grid-template-columns:
        1fr
        1fr
        auto;

    gap: 12px;

    align-items: end;
}

.field label {

    display: block;

    margin-bottom: 7px;

    color: #8997b1;

    font-size: 10px;

    font-weight: 700;
}

.input {

    width: 100%;

    height: 44px;

    padding:
        0 12px;

    border:
        1px solid #26314e;

    border-radius: 11px;

    outline: none;

    background:
        #090f20;

    color: #e5ebf7;

    font-family: inherit;

    font-size: 11px;
}

.input:focus {

    border-color:
        var(--primary);

    box-shadow:
        0 0 0 3px
        rgba(99,102,241,.10);
}

.btn {

    height: 44px;

    padding:
        0 20px;

    border: 0;

    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            var(--primary),
            var(--violet)
        );

    color: #fff;

    font-family: inherit;

    font-size: 11px;

    font-weight: 800;

    cursor: pointer;

    box-shadow:
        0 10px 25px
        rgba(99,102,241,.20);
}

.btn:hover {

    transform:
        translateY(-1px);
}

.btn-print {

    background:
        rgba(56,189,248,.10);

    border:
        1px solid
        rgba(56,189,248,.18);

    color: #7dd3fc;

    box-shadow: none;
}


/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-top: 18px;
}

.stat {

    padding: 19px;

    border-radius: 18px;

    background:
        linear-gradient(
            145deg,
            rgba(16,23,42,.97),
            rgba(12,18,34,.97)
        );

    border:
        1px solid var(--border);

    transition: .2s ease;
}

.stat:hover {

    transform:
        translateY(-3px);

    border-color:
        rgba(99,102,241,.30);
}

.stat-icon {

    width: 36px;
    height: 36px;

    display: grid;

    place-items: center;

    border-radius: 11px;

    background:
        rgba(99,102,241,.10);

    color: #a5b4fc;
}

.stat-label {

    margin-top: 14px;

    color: var(--muted);

    font-size: 10px;
}

.stat-value {

    margin-top: 4px;

    font-size: 20px;

    font-weight: 850;

    letter-spacing: -.5px;
}

.stat-meta {

    margin-top: 5px;

    color: #596780;

    font-size: 9px;
}


/* =========================================================
   GRID
========================================================= */

.grid {

    display: grid;

    grid-template-columns:
        1.35fr
        .65fr;

    gap: 18px;

    margin-top: 18px;
}

.card {

    overflow: hidden;

    border-radius: 20px;

    background:
        linear-gradient(
            145deg,
            rgba(16,23,42,.97),
            rgba(12,18,34,.97)
        );

    border:
        1px solid var(--border);

    box-shadow:
        0 14px 45px
        rgba(0,0,0,.16);
}

.card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        20px 22px;

    border-bottom:
        1px solid
        rgba(255,255,255,.05);
}

.card-title {

    font-size: 14px;

    font-weight: 800;
}

.card-subtitle {

    margin-top: 4px;

    color: var(--muted-2);

    font-size: 9px;
}

.card-body {
    padding: 22px;
}


/* =========================================================
   MINI SUMMARY
========================================================= */

.mini-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;
}

.mini {

    padding: 14px;

    border-radius: 13px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(255,255,255,.045);
}

.mini-label {

    color: #64748b;

    font-size: 9px;
}

.mini-value {

    margin-top: 5px;

    color: #dce5f4;

    font-size: 15px;

    font-weight: 800;
}


/* =========================================================
   CHART
========================================================= */

.chart {

    height: 290px;

    display: flex;

    align-items: flex-end;

    gap: 9px;

    padding:
        20px 10px 0;

    overflow-x: auto;
}

.chart-column {

    min-width: 34px;

    height: 100%;

    display: flex;

    flex-direction: column;

    justify-content: flex-end;

    align-items: center;

    gap: 7px;
}

.bar-wrap {

    width: 100%;

    height: 235px;

    display: flex;

    align-items: flex-end;

    justify-content: center;
}

.bar {

    width: 22px;

    min-height: 3px;

    border-radius:
        7px 7px 3px 3px;

    background:
        linear-gradient(
            180deg,
            #67e8f9,
            #6366f1,
            #8b5cf6
        );

    box-shadow:
        0 0 18px
        rgba(99,102,241,.18);

    transition: .25s ease;
}

.bar:hover {

    filter:
        brightness(1.2);

    transform:
        scaleX(1.08);
}

.chart-date {

    color: #596780;

    font-size: 8px;

    white-space: nowrap;
}


/* =========================================================
   STATUS
========================================================= */

.status-item {

    margin-bottom: 18px;
}

.status-head {

    display: flex;

    justify-content: space-between;

    margin-bottom: 7px;

    color: #a8b4c9;

    font-size: 10px;
}

.progress {

    height: 7px;

    overflow: hidden;

    border-radius: 20px;

    background:
        #1a2339;
}

.progress span {

    display: block;

    height: 100%;

    border-radius: inherit;
}

.progress-success span {

    background:
        linear-gradient(
            90deg,
            #34d399,
            #2dd4bf
        );
}

.progress-warning span {

    background:
        linear-gradient(
            90deg,
            #fbbf24,
            #f59e0b
        );
}

.progress-danger span {

    background:
        linear-gradient(
            90deg,
            #fb7185,
            #f43f5e
        );
}


/* =========================================================
   TOP NASABAH
========================================================= */

.customer {

    display: flex;

    align-items: center;

    gap: 10px;

    padding:
        11px 0;

    border-bottom:
        1px solid
        rgba(255,255,255,.045);
}

.customer:last-child {
    border-bottom: 0;
}

.customer-rank {

    width: 28px;
    height: 28px;

    display: grid;

    place-items: center;

    border-radius: 9px;

    background:
        rgba(99,102,241,.10);

    color: #a5b4fc;

    font-size: 10px;

    font-weight: 800;
}

.customer-info {

    flex: 1;

    min-width: 0;
}

.customer-name {

    overflow: hidden;

    white-space: nowrap;

    text-overflow: ellipsis;

    color: #dce5f4;

    font-size: 10px;

    font-weight: 750;
}

.customer-meta {

    margin-top: 3px;

    color: #596780;

    font-size: 8px;
}

.customer-value {

    color: #67e8f9;

    font-size: 10px;

    font-weight: 800;
}


/* =========================================================
   TABLE
========================================================= */

.table-card {

    margin-top: 18px;
}

.table-scroll {
    overflow-x: auto;
}

table {

    width: 100%;

    min-width: 850px;

    border-collapse: collapse;
}

th {

    padding:
        12px 18px;

    text-align: left;

    color: #64748b;

    background:
        rgba(255,255,255,.018);

    font-size: 8px;

    letter-spacing: 1px;

    text-transform: uppercase;
}

td {

    padding:
        13px 18px;

    border-top:
        1px solid
        rgba(255,255,255,.045);

    color: #b6c1d4;

    font-size: 10px;
}

tbody tr:hover {

    background:
        rgba(99,102,241,.035);
}

.badge {

    display: inline-flex;

    padding:
        5px 8px;

    border-radius: 999px;

    font-size: 8px;

    font-weight: 800;
}

.badge-success {

    color: #6ee7b7;

    background:
        rgba(52,211,153,.09);
}

.badge-warning {

    color: #fcd34d;

    background:
        rgba(251,191,36,.09);
}

.badge-danger {

    color: #fda4af;

    background:
        rgba(251,113,133,.09);
}


/* =========================================================
   PRINT
========================================================= */

.print-header {
    display: none;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    margin-top: 28px;

    text-align: center;

    color: #4f5d75;

    font-size: 10px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .grid {

        grid-template-columns: 1fr;
    }
}

@media (max-width: 800px) {

    .sidebar {

        width: 78px;

        padding:
            18px 10px;
    }

    .brand {

        justify-content: center;

        padding:
            5px 0 22px;
    }

    .brand-text,
    .section-label,
    .nav-text,
    .profile-box {

        display: none;
    }

    .nav a {

        justify-content: center;

        padding:
            13px 8px;
    }

    .main {

        margin-left: 78px;

        padding:
            20px;
    }

    .filter-form {

        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {

    .main {

        padding: 15px;
    }

    .clock {

        display: none;
    }

    .hero {

        padding: 23px;
    }

    .hero h1 {

        font-size: 27px;
    }

    .stats {

        grid-template-columns: 1fr;
    }
}


/* =========================================================
   PRINT MODE
========================================================= */

@media print {

    body {

        background: #fff;

        color: #111;
    }

    .sidebar,
    .topbar,
    .filter,
    .btn-print,
    .footer {

        display: none !important;
    }

    .main {

        margin: 0;

        padding: 20px;
    }

    .hero {

        background: #fff;

        color: #111;

        box-shadow: none;

        border:
            1px solid #ddd;
    }

    .hero h1,
    .hero p {

        color: #111;
    }

    .card,
    .stat {

        background: #fff;

        color: #111;

        box-shadow: none;

        border:
            1px solid #ddd;
    }

    .print-header {

        display: block;

        margin-bottom: 20px;

        text-align: center;
    }

    .print-header h2 {

        margin: 0;

        color: #111;
    }

    .print-header p {

        margin: 5px 0;

        color: #555;

        font-size: 11px;
    }

    th {

        background: #f3f4f6;

        color: #333;
    }

    td {

        color: #222;

        border-color: #ddd;
    }

}

</style>

    <link rel="stylesheet" href="assets/guru-theme.css">
</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<?php include __DIR__ . '/guru_sidebar.php'; ?>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <?php include __DIR__ . '/guru_topbar.php'; ?>
    <span id="clock" hidden></span>


    <div class="print-header">

        <h2>
            BANK SAMPAH ATAYA
        </h2>

        <p>
            Laporan Aktivitas Guru
        </p>

        <p>
            Periode:
            <?= date(
                'd M Y',
                strtotime($tanggal_awal)
            ) ?>
            -
            <?= date(
                'd M Y',
                strtotime($tanggal_akhir)
            ) ?>
        </p>

    </div>


    <!-- =================================================
         HERO
    ================================================== -->

    <section class="hero">

        <div class="hero-content">

            <div class="eyebrow">
                ✦ GURU ANALYTICS CENTER
            </div>

            <h1>
                Laporan Aktivitas
            </h1>

            <p>
                Pantau performa transaksi, berat sampah,
                nilai setoran, penarikan saldo, serta
                aktivitas nasabah berdasarkan periode
                yang kamu pilih.
            </p>

        </div>

    </section>


    <!-- =================================================
         FILTER
    ================================================== -->

    <section class="filter">

        <form
            method="GET"
            class="filter-form"
        >

            <div class="field">

                <label>
                    Tanggal Awal
                </label>

                <input
                    type="date"
                    name="tanggal_awal"
                    class="input"
                    value="<?= htmlspecialchars(
                        $tanggal_awal
                    ) ?>"
                >

            </div>


            <div class="field">

                <label>
                    Tanggal Akhir
                </label>

                <input
                    type="date"
                    name="tanggal_akhir"
                    class="input"
                    value="<?= htmlspecialchars(
                        $tanggal_akhir
                    ) ?>"
                >

            </div>


            <button
                type="submit"
                class="btn"
            >
                ◈ Tampilkan Laporan
            </button>


            <button
                type="button"
                class="btn btn-print"
                onclick="window.print()"
            >
                🖨 Cetak
            </button>

        </form>

    </section>


    <!-- =================================================
         STATS
    ================================================== -->

    <section class="stats">


        <div class="stat">

            <div class="stat-icon">
                #
            </div>

            <div class="stat-label">
                Transaksi Disetujui
            </div>

            <div class="stat-value">
                <?= angka($jumlah_transaksi) ?>
            </div>

            <div class="stat-meta">
                Periode terpilih
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                ◇
            </div>

            <div class="stat-label">
                Total Berat
            </div>

            <div class="stat-value">
                <?= angka($total_berat) ?> kg
            </div>

            <div class="stat-meta">
                Sampah berhasil disetor
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                Rp
            </div>

            <div class="stat-label">
                Nilai Setoran
            </div>

            <div class="stat-value">
                <?= rupiah($total_nilai) ?>
            </div>

            <div class="stat-meta">
                Transaksi disetujui
            </div>

        </div>


        <div class="stat">

            <div class="stat-icon">
                ↗
            </div>

            <div class="stat-label">
                Penarikan
            </div>

            <div class="stat-value">
                <?= rupiah($total_penarikan) ?>
            </div>

            <div class="stat-meta">
                <?= angka($jumlah_penarikan) ?>
                kali penarikan
            </div>

        </div>


    </section>


    <!-- =================================================
         ANALYTICS
    ================================================== -->

    <section class="grid">


        <!-- CHART -->

        <div class="card">

            <div class="card-header">

                <div>

                    <div class="card-title">
                        Aktivitas Setoran
                    </div>

                    <div class="card-subtitle">
                        Nilai transaksi disetujui per hari
                    </div>

                </div>

            </div>


            <div class="card-body">

                <?php if (
                    count($chart_data) > 0
                ): ?>

                    <?php

                    $max_chart =
                        max(
                            array_column(
                                $chart_data,
                                'total'
                            )
                        );

                    if ($max_chart <= 0) {
                        $max_chart = 1;
                    }

                    ?>

                    <div class="chart">

                        <?php foreach (
                            $chart_data
                            as $item
                        ): ?>

                            <?php

                            $height =
                                (
                                    $item['total']
                                    /
                                    $max_chart
                                ) * 100;

                            $height =
                                max(
                                    3,
                                    $height
                                );

                            ?>

                            <div
                                class="chart-column"
                                title="<?= htmlspecialchars(
                                    $item['tanggal']
                                ) ?> — <?= rupiah(
                                    $item['total']
                                ) ?>"
                            >

                                <div class="bar-wrap">

                                    <div
                                        class="bar"
                                        style="
                                            height:
                                            <?= $height ?>%;
                                        "
                                    ></div>

                                </div>

                                <div class="chart-date">

                                    <?= date(
                                        'd/m',
                                        strtotime(
                                            $item['tanggal']
                                        )
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div
                        style="
                            height:290px;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            color:#596780;
                            font-size:11px;
                        "
                    >

                        Belum ada transaksi
                        disetujui pada periode ini.

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- STATUS -->

        <div class="card">

            <div class="card-header">

                <div>

                    <div class="card-title">
                        Status Transaksi
                    </div>

                    <div class="card-subtitle">
                        Distribusi aktivitas
                    </div>

                </div>

            </div>


            <div class="card-body">


                <div class="status-item">

                    <div class="status-head">

                        <span>
                            Disetujui
                        </span>

                        <strong>
                            <?= $disetujui ?>
                            ·
                            <?= $persen_disetujui ?>%
                        </strong>

                    </div>

                    <div class="progress progress-success">

                        <span
                            style="
                                width:
                                <?= $persen_disetujui ?>%;
                            "
                        ></span>

                    </div>

                </div>


                <div class="status-item">

                    <div class="status-head">

                        <span>
                            Pending
                        </span>

                        <strong>
                            <?= $pending ?>
                            ·
                            <?= $persen_pending ?>%
                        </strong>

                    </div>

                    <div class="progress progress-warning">

                        <span
                            style="
                                width:
                                <?= $persen_pending ?>%;
                            "
                        ></span>

                    </div>

                </div>


                <div class="status-item">

                    <div class="status-head">

                        <span>
                            Ditolak
                        </span>

                        <strong>
                            <?= $ditolak ?>
                            ·
                            <?= $persen_ditolak ?>%
                        </strong>

                    </div>

                    <div class="progress progress-danger">

                        <span
                            style="
                                width:
                                <?= $persen_ditolak ?>%;
                            "
                        ></span>

                    </div>

                </div>


                <div class="mini-grid">

                    <div class="mini">

                        <div class="mini-label">
                            Nasabah Aktif
                        </div>

                        <div class="mini-value">
                            <?= angka($nasabah_aktif) ?>
                        </div>

                    </div>


                    <div class="mini">

                        <div class="mini-label">
                            Saldo Periode
                        </div>

                        <div class="mini-value">

                            <?= rupiah(
                                $saldo_periode
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- =================================================
         TOP NASABAH
    ================================================== -->

    <section
        class="grid"
        style="margin-top:18px;"
    >


        <div class="card">

            <div class="card-header">

                <div>

                    <div class="card-title">
                        Nasabah Teraktif
                    </div>

                    <div class="card-subtitle">
                        Berdasarkan nilai setoran
                    </div>

                </div>

            </div>


            <div class="card-body">


                <?php if (
                    count($top_nasabah) > 0
                ): ?>


                    <?php
                    $rank = 1;
                    ?>


                    <?php foreach (
                        $top_nasabah
                        as $nasabah
                    ): ?>


                        <div class="customer">


                            <div class="customer-rank">

                                <?= $rank ?>

                            </div>


                            <div class="customer-info">

                                <div class="customer-name">

                                    <?= htmlspecialchars(
                                        $nasabah['nama']
                                    ) ?>

                                </div>

                                <div class="customer-meta">

                                    <?= angka(
                                        $nasabah[
                                            'jumlah_transaksi'
                                        ]
                                    ) ?>
                                    transaksi ·
                                    <?= angka(
                                        $nasabah[
                                            'total_berat'
                                        ]
                                    ) ?>
                                    kg

                                </div>

                            </div>


                            <div class="customer-value">

                                <?= rupiah(
                                    $nasabah[
                                        'total_nilai'
                                    ]
                                ) ?>

                            </div>


                        </div>


                        <?php
                        $rank++;
                        ?>

                    <?php endforeach; ?>


                <?php else: ?>

                    <div
                        style="
                            padding:30px;
                            text-align:center;
                            color:#596780;
                            font-size:10px;
                        "
                    >
                        Belum ada data nasabah.
                    </div>

                <?php endif; ?>


            </div>

        </div>


        <!-- SUMMARY -->

        <div class="card">

            <div class="card-header">

                <div>

                    <div class="card-title">
                        Financial Snapshot
                    </div>

                    <div class="card-subtitle">
                        Ringkasan periode laporan
                    </div>

                </div>

            </div>


            <div class="card-body">


                <div class="mini-grid">

                    <div class="mini">

                        <div class="mini-label">
                            Setoran
                        </div>

                        <div class="mini-value">
                            <?= rupiah($total_nilai) ?>
                        </div>

                    </div>


                    <div class="mini">

                        <div class="mini-label">
                            Penarikan
                        </div>

                        <div class="mini-value">
                            <?= rupiah($total_penarikan) ?>
                        </div>

                    </div>


                    <div class="mini">

                        <div class="mini-label">
                            Berat Sampah
                        </div>

                        <div class="mini-value">
                            <?= angka($total_berat) ?> kg
                        </div>

                    </div>


                    <div class="mini">

                        <div class="mini-label">
                            Nasabah Aktif
                        </div>

                        <div class="mini-value">
                            <?= angka($nasabah_aktif) ?>
                        </div>

                    </div>

                </div>


                <div
                    style="
                        margin-top:16px;
                        padding:17px;
                        border-radius:14px;
                        background:
                        linear-gradient(
                            135deg,
                            rgba(99,102,241,.10),
                            rgba(56,189,248,.05)
                        );
                        border:
                        1px solid
                        rgba(99,102,241,.13);
                    "
                >

                    <div
                        style="
                            color:#64748b;
                            font-size:9px;
                        "
                    >
                        SALDO BERSIH PERIODE
                    </div>


                    <div
                        style="
                            margin-top:5px;
                            color:#67e8f9;
                            font-size:24px;
                            font-weight:850;
                        "
                    >

                        <?= rupiah(
                            $saldo_periode
                        ) ?>

                    </div>


                    <div
                        style="
                            margin-top:5px;
                            color:#596780;
                            font-size:8px;
                        "
                    >

                        Nilai setoran dikurangi
                        penarikan pada periode terpilih.

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- =================================================
         DETAIL TABLE
    ================================================== -->

    <section class="card table-card">

        <div class="card-header">

            <div>

                <div class="card-title">
                    Detail Transaksi
                </div>

                <div class="card-subtitle">
                    Maksimal 100 transaksi pada periode terpilih
                </div>

            </div>

        </div>


        <div class="table-scroll">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Tanggal
                        </th>

                        <th>
                            Nasabah
                        </th>

                        <th>
                            Jenis Sampah
                        </th>

                        <th>
                            Berat
                        </th>

                        <th>
                            Harga
                        </th>

                        <th>
                            Total
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    count($detail_transaksi) > 0
                ): ?>


                    <?php foreach (
                        $detail_transaksi
                        as $row
                    ): ?>


                        <tr>


                            <td>

                                #<?= (int)
                                    $row['id_transaksi']
                                ?>

                            </td>


                            <td>

                                <?= date(
                                    'd M Y',
                                    strtotime(
                                        $row['tanggal']
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row['nama_nasabah']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row['nama_sampah']
                                ) ?>

                            </td>


                            <td>

                                <?= angka(
                                    $row['berat']
                                ) ?>
                                kg

                            </td>


                            <td>

                                <?= rupiah(
                                    $row['harga']
                                ) ?>

                            </td>


                            <td>

                                <strong
                                    style="
                                        color:#67e8f9;
                                    "
                                >

                                    <?= rupiah(
                                        $row['total']
                                    ) ?>

                                </strong>

                            </td>


                            <td>


                                <?php if (
                                    $row['status']
                                    === 'disetujui'
                                ): ?>

                                    <span
                                        class="
                                            badge
                                            badge-success
                                        "
                                    >
                                        Disetujui
                                    </span>

                                <?php elseif (
                                    $row['status']
                                    === 'pending'
                                ): ?>

                                    <span
                                        class="
                                            badge
                                            badge-warning
                                        "
                                    >
                                        Pending
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="
                                            badge
                                            badge-danger
                                        "
                                    >
                                        Ditolak
                                    </span>

                                <?php endif; ?>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="8"
                            style="
                                text-align:center;
                                padding:45px;
                                color:#596780;
                            "
                        >

                            Tidak ada transaksi pada
                            periode yang dipilih.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </section>


    <div class="footer">

        Bank Sampah Ataya • Guru Analytics Center •
        <span id="year"></span>

    </div>


</main>


<script>

function updateClock() {

    const now = new Date();

    document.getElementById(
        'clock'
    ).textContent =
        now.toLocaleTimeString(
            'id-ID',
            {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }
        );
}

updateClock();

setInterval(
    updateClock,
    1000
);


document.getElementById(
    'year'
).textContent =
    new Date().getFullYear();


/* =========================================================
   VALIDASI FILTER
========================================================= */

document
    .querySelector('.filter-form')
    .addEventListener(
        'submit',
        function(e) {

            const awal =
                document.querySelector(
                    '[name="tanggal_awal"]'
                ).value;

            const akhir =
                document.querySelector(
                    '[name="tanggal_akhir"]'
                ).value;

            if (
                awal &&
                akhir &&
                awal > akhir
            ) {

                e.preventDefault();

                alert(
                    'Tanggal awal tidak boleh lebih besar dari tanggal akhir.'
                );
            }
        }
    );

</script>

<script src="assets/guru.js"></script>
</body>

</html>