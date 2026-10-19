<?php
session_start();

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    die("Akses ditolak. Halaman ini khusus siswa.");
}

include "koneksi.php";

/*
|--------------------------------------------------------------------------
| IDENTITAS
|--------------------------------------------------------------------------
*/

$id_nasabah = (int)($_SESSION['id_nasabah'] ?? 0);

if ($id_nasabah <= 0) {
    die("
        <div style='font-family:Arial;padding:40px'>
            <h2>Data siswa belum terhubung</h2>
            <p>Session <b>id_nasabah</b> belum tersedia.</p>
        </div>
    ");
}

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id_nasabah, nama
     FROM nasabah
     WHERE id_nasabah = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$nasabah = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$nasabah) {
    die("Data nasabah tidak ditemukan.");
}

$nama_siswa = $nasabah['nama'];

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_setor'])) {
    $_SESSION['csrf_setor'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_setor'];

/*
|--------------------------------------------------------------------------
| PESAN
|--------------------------------------------------------------------------
*/

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| PROSES SETOR
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_setor'], $token)) {

        $error = "Permintaan tidak valid. Silakan coba lagi.";

    } else {

        $id_sampah = (int)($_POST['id_sampah'] ?? 0);
        $berat = (float)($_POST['berat'] ?? 0);
        $tanggal = date('Y-m-d');

        /*
        |--------------------------------------------------------------------------
        | VALIDASI BERAT
        |--------------------------------------------------------------------------
        */

        if ($id_sampah <= 0) {

            $error = "Silakan pilih jenis sampah.";

        } elseif ($berat <= 0) {

            $error = "Berat sampah harus lebih dari 0 Kg.";

        } elseif ($berat > 1000) {

            $error = "Berat maksimal satu pengajuan adalah 1.000 Kg.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | AMBIL HARGA DARI DATABASE
            | HANYA SAMPAH YANG SUDAH DISETUJUI
            |--------------------------------------------------------------------------
            */

            $stmt = mysqli_prepare(
                $koneksi,
                "SELECT id_sampah, nama_sampah, harga, satuan
                 FROM sampah
                 WHERE id_sampah = ?
                 AND status = 'disetujui'
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id_sampah
            );

            mysqli_stmt_execute($stmt);

            $result_sampah = mysqli_stmt_get_result($stmt);
            $sampah = mysqli_fetch_assoc($result_sampah);

            mysqli_stmt_close($stmt);

            if (!$sampah) {

                $error = "Jenis sampah tidak tersedia atau belum disetujui.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | HITUNG TOTAL DI SERVER
                |--------------------------------------------------------------------------
                */

                $harga = (int)$sampah['harga'];

                $total = (int)round($berat * $harga);

                if ($total <= 0) {

                    $error = "Total setoran tidak valid.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | INSERT TRANSAKSI
                    |--------------------------------------------------------------------------
                    */

                    $stmt = mysqli_prepare(
                        $koneksi,
                        "INSERT INTO transaksi
                        (
                            id_nasabah,
                            id_sampah,
                            tanggal,
                            berat,
                            harga,
                            total,
                            status
                        )
                        VALUES
                        (?, ?, ?, ?, ?, ?, 'pending')"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iisdii",
                        $id_nasabah,
                        $id_sampah,
                        $tanggal,
                        $berat,
                        $harga,
                        $total
                    );

                    if (mysqli_stmt_execute($stmt)) {

                        $success =
                            "Pengajuan setoran berhasil dikirim. " .
                            "Menunggu verifikasi Guru/Admin.";

                    } else {

                        $error =
                            "Gagal menyimpan pengajuan setoran: " .
                            mysqli_error($koneksi);
                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| AMBIL JENIS SAMPAH DISETUJUI
|--------------------------------------------------------------------------
*/

$data_sampah = [];

$query_sampah = mysqli_query(
    $koneksi,
    "SELECT
        id_sampah,
        nama_sampah,
        harga,
        satuan
     FROM sampah
     WHERE status = 'disetujui'
     ORDER BY nama_sampah ASC"
);

if ($query_sampah) {

    while ($row = mysqli_fetch_assoc($query_sampah)) {
        $data_sampah[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| RIWAYAT SETORAN
|--------------------------------------------------------------------------
*/

$data_riwayat = [];

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        transaksi.id_transaksi,
        transaksi.tanggal,
        transaksi.berat,
        transaksi.harga,
        transaksi.total,
        transaksi.status,
        sampah.nama_sampah,
        sampah.satuan
     FROM transaksi
     INNER JOIN sampah
        ON sampah.id_sampah = transaksi.id_sampah
     WHERE transaksi.id_nasabah = ?
     ORDER BY transaksi.id_transaksi DESC
     LIMIT 12"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_nasabah
);

mysqli_stmt_execute($stmt);

$result_riwayat = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result_riwayat)) {
    $data_riwayat[] = $row;
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| STATISTIK KECIL
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        COUNT(*) AS jumlah,
        COALESCE(SUM(berat), 0) AS berat,
        COALESCE(SUM(total), 0) AS total
     FROM transaksi
     WHERE id_nasabah = ?
     AND status = 'disetujui'"
);

mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
mysqli_stmt_execute($stmt);

$result_stat = mysqli_stmt_get_result($stmt);
$stat = mysqli_fetch_assoc($result_stat);

mysqli_stmt_close($stmt);

$jumlah_disetujui = (int)($stat['jumlah'] ?? 0);
$total_berat = (float)($stat['berat'] ?? 0);
$total_nilai = (int)($stat['total'] ?? 0);

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function rupiah($angka)
{
    return 'Rp ' . number_format(
        (float)$angka,
        0,
        ',',
        '.'
    );
}

function beratFormat($angka)
{
    return number_format(
        (float)$angka,
        2,
        ',',
        '.'
    );
}

function statusClass($status)
{
    if ($status === 'disetujui') {
        return 'approved';
    }

    if ($status === 'ditolak') {
        return 'rejected';
    }

    return 'pending';
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

<title>Setor Sampah - Ruang Siswa</title>

<style>

:root {

    --bg: #07121d;
    --bg-2: #0a1724;

    --sidebar: #0c1725;

    --card: #101e2e;
    --card-2: #132438;

    --border: rgba(255,255,255,.08);

    --green: #22c55e;
    --green-dark: #16a34a;

    --teal: #14b8a6;
    --cyan: #22d3ee;

    --text: #f8fafc;
    --muted: #94a3b8;
    --muted-2: #64748b;

    --warning: #fbbf24;
    --danger: #fb7185;

    --sidebar-width: 280px;
}

/* =====================================================
   RESET
===================================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family:
        Inter,
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    min-height: 100vh;

    color: var(--text);

    background:

        radial-gradient(
            circle at 85% 5%,
            rgba(20,184,166,.10),
            transparent 27%
        ),

        radial-gradient(
            circle at 15% 90%,
            rgba(34,197,94,.08),
            transparent 30%
        ),

        var(--bg);
}

/* =====================================================
   SIDEBAR
===================================================== */

.sidebar {

    position: fixed;

    top: 0;
    left: 0;

    width: var(--sidebar-width);
    height: 100vh;

    padding: 30px 22px;

    background:
        linear-gradient(
            180deg,
            #101c2c,
            #0b1725 50%,
            #08131f
        );

    border-right:
        1px solid var(--border);

    z-index: 1000;

    display: flex;
    flex-direction: column;

    box-shadow:
        15px 0 50px rgba(0,0,0,.20);
}

/* BRAND */

.brand {

    display: flex;

    align-items: center;

    gap: 14px;

    margin-bottom: 45px;
}

.brand-icon {

    width: 54px;
    height: 54px;

    border-radius: 17px;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 28px;

    background:
        linear-gradient(
            135deg,
            #14b8a6,
            #22c55e
        );

    box-shadow:
        0 12px 35px rgba(20,184,166,.25);
}

.brand-text h2 {

    font-size: 19px;

    font-weight: 800;
}

.brand-text span {

    display: block;

    margin-top: 4px;

    color: #7f91a8;

    font-size: 11px;
}

/* MENU */

.menu-title {

    color: #64748b;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: 1.5px;

    text-transform: uppercase;

    margin:
        0 10px
        12px;
}

.nav {

    display: flex;

    flex-direction: column;

    gap: 6px;
}

.nav a {

    text-decoration: none;

    color: #9aacc0;

    display: flex;

    align-items: center;

    gap: 14px;

    padding: 14px;

    border-radius: 14px;

    font-size: 14px;

    font-weight: 600;

    transition: .25s ease;
}

.nav a:hover {

    color: white;

    background:
        rgba(255,255,255,.045);

    transform:
        translateX(3px);
}

.nav a.active {

    color: white;

    background:
        linear-gradient(
            135deg,
            rgba(22,163,74,.95),
            rgba(20,184,166,.88)
        );

    box-shadow:
        0 12px 30px rgba(20,184,166,.18);
}

.nav-icon {

    width: 22px;

    text-align: center;

    font-size: 17px;
}

.sidebar-spacer {

    flex: 1;
}

.account-box {

    padding: 17px;

    border-radius: 18px;

    background:
        linear-gradient(
            145deg,
            rgba(30,48,70,.85),
            rgba(17,31,47,.8)
        );

    border:
        1px solid
        rgba(148,163,184,.20);
}

.account-label {

    color: #8ca0b7;

    font-size: 10px;

    margin-bottom: 7px;
}

.account-name {

    color: #4ee49a;

    font-size: 13px;

    font-weight: 800;
}

.logout {

    margin-top: 12px;
}

.logout a {

    text-decoration: none;

    display: flex;

    align-items: center;

    gap: 14px;

    padding: 14px;

    border-radius: 14px;

    color: #dbe4ee;

    background:
        rgba(255,255,255,.025);

    font-size: 14px;

    font-weight: 700;
}

/* =====================================================
   MAIN
===================================================== */

.main {

    margin-left:
        var(--sidebar-width);

    min-height: 100vh;
}

/* TOPBAR */

.topbar {

    height: 76px;

    padding:
        0 36px;

    border-bottom:
        1px solid var(--border);

    background:
        rgba(7,18,29,.72);

    backdrop-filter:
        blur(18px);

    display: flex;

    align-items: center;

    justify-content: space-between;

    position: sticky;

    top: 0;

    z-index: 100;
}

.page-title h1 {

    font-size: 18px;

    font-weight: 800;
}

.page-title p {

    color: var(--muted);

    font-size: 12px;

    margin-top: 3px;
}

.clock {

    color: #9fb1c5;

    font-size: 12px;

    display: flex;

    gap: 9px;

    align-items: center;
}

.clock-dot {

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background:
        var(--green);

    box-shadow:
        0 0 12px var(--green);
}

/* CONTENT */

.content {

    padding:
        34px 36px 50px;

    max-width: 1550px;
}

/* =====================================================
   PAGE HERO
===================================================== */

.page-hero {

    position: relative;

    overflow: hidden;

    border-radius: 24px;

    padding: 30px;

    margin-bottom: 22px;

    background:
        linear-gradient(
            120deg,
            rgba(20,184,166,.17),
            rgba(34,197,94,.08),
            rgba(15,23,42,.72)
        );

    border:
        1px solid
        rgba(45,212,191,.16);

    box-shadow:
        0 25px 70px rgba(0,0,0,.18);
}

.page-hero::after {

    content: "";

    position: absolute;

    width: 250px;
    height: 250px;

    right: -80px;
    top: -120px;

    border-radius: 50%;

    background:
        radial-gradient(
            circle,
            rgba(34,211,238,.16),
            transparent 68%
        );
}

.hero-inner {

    position: relative;

    z-index: 2;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 25px;
}

.hero-label {

    color: #6ee7b7;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: 1.4px;

    text-transform: uppercase;

    margin-bottom: 8px;
}

.hero-title {

    font-size: 29px;

    letter-spacing: -.8px;

    margin-bottom: 7px;
}

.hero-title span {

    color: #4ee49a;
}

.hero-desc {

    max-width: 650px;

    color: var(--muted);

    font-size: 12px;

    line-height: 1.7;
}

.hero-badge {

    min-width: 170px;

    padding: 18px;

    border-radius: 18px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid
        rgba(255,255,255,.08);

    text-align: center;
}

.hero-badge-icon {

    font-size: 28px;

    margin-bottom: 8px;
}

.hero-badge strong {

    display: block;

    font-size: 12px;
}

.hero-badge small {

    color: var(--muted-2);

    font-size: 9px;
}

/* =====================================================
   GRID
===================================================== */

.main-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1.1fr)
        minmax(330px, .9fr);

    gap: 20px;

    margin-bottom: 20px;
}

.card {

    background:
        linear-gradient(
            145deg,
            rgba(16,30,46,.96),
            rgba(11,25,39,.96)
        );

    border:
        1px solid var(--border);

    border-radius: 20px;

    padding: 24px;

    box-shadow:
        0 18px 45px rgba(0,0,0,.12);
}

.card-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 22px;
}

.card-title {

    font-size: 15px;

    font-weight: 800;
}

.card-subtitle {

    color: #64748b;

    font-size: 10px;

    margin-top: 4px;
}

/* =====================================================
   FORM
===================================================== */

.form-group {

    margin-bottom: 18px;
}

.form-label {

    display: block;

    color: #cbd5e1;

    font-size: 11px;

    font-weight: 750;

    margin-bottom: 8px;
}

.form-label span {

    color: #64748b;

    font-weight: 500;
}

.select-wrap {

    position: relative;
}

.form-control {

    width: 100%;

    height: 48px;

    border-radius: 13px;

    border:
        1px solid
        rgba(148,163,184,.14);

    outline: none;

    background:
        #0b1826;

    color: #f8fafc;

    padding:
        0 14px;

    font-size: 12px;

    transition: .25s ease;
}

.form-control:focus {

    border-color:
        rgba(45,212,191,.55);

    box-shadow:
        0 0 0 3px
        rgba(20,184,166,.09);
}

.form-control option {

    background: #0b1826;

    color: white;
}

/* INFO PRICE */

.price-preview {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;

    margin-bottom: 18px;
}

.price-box {

    padding: 14px;

    border-radius: 14px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid
        rgba(255,255,255,.06);
}

.price-box-label {

    color: #64748b;

    font-size: 9px;

    margin-bottom: 6px;
}

.price-box-value {

    color: #dbeafe;

    font-size: 14px;

    font-weight: 800;
}

/* TOTAL */

.total-box {

    position: relative;

    overflow: hidden;

    padding: 20px;

    border-radius: 17px;

    margin-top: 5px;

    background:
        linear-gradient(
            135deg,
            rgba(20,184,166,.13),
            rgba(34,197,94,.07)
        );

    border:
        1px solid
        rgba(45,212,191,.16);
}

.total-box::after {

    content: "";

    position: absolute;

    width: 100px;
    height: 100px;

    right: -40px;
    bottom: -55px;

    border-radius: 50%;

    background:
        rgba(34,197,94,.08);
}

.total-label {

    color: #7f91a8;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: 1px;

    font-weight: 800;
}

.total-value {

    color: #6ee7b7;

    font-size: 27px;

    font-weight: 900;

    margin-top: 5px;

    letter-spacing: -.7px;
}

.total-note {

    color: #64748b;

    font-size: 9px;

    margin-top: 5px;
}

/* BUTTON */

.submit-btn {

    width: 100%;

    height: 49px;

    border: none;

    border-radius: 13px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #16a34a,
            #14b8a6
        );

    font-size: 12px;

    font-weight: 850;

    cursor: pointer;

    margin-top: 18px;

    box-shadow:
        0 12px 30px
        rgba(20,184,166,.18);

    transition: .25s ease;
}

.submit-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 17px 35px
        rgba(20,184,166,.25);
}

/* =====================================================
   GUIDE
===================================================== */

.guide-list {

    display: flex;

    flex-direction: column;

    gap: 13px;
}

.guide-item {

    display: flex;

    gap: 13px;

    padding: 13px;

    border-radius: 14px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(255,255,255,.05);
}

.guide-number {

    flex-shrink: 0;

    width: 31px;
    height: 31px;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #6ee7b7;

    background:
        rgba(20,184,166,.10);

    font-size: 11px;

    font-weight: 900;
}

.guide-text strong {

    display: block;

    font-size: 11px;

    margin-bottom: 3px;
}

.guide-text span {

    color: #64748b;

    font-size: 9px;

    line-height: 1.5;
}

/* =====================================================
   ALERT
===================================================== */

.alert {

    padding: 14px 16px;

    border-radius: 13px;

    margin-bottom: 18px;

    font-size: 11px;

    line-height: 1.5;
}

.alert-success {

    color: #86efac;

    background:
        rgba(34,197,94,.08);

    border:
        1px solid
        rgba(34,197,94,.15);
}

.alert-error {

    color: #fda4af;

    background:
        rgba(251,113,133,.08);

    border:
        1px solid
        rgba(251,113,133,.15);
}

/* =====================================================
   MINI STATS
===================================================== */

.mini-stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 10px;

    margin-bottom: 20px;
}

.mini-stat {

    padding: 15px;

    border-radius: 15px;

    background:
        rgba(255,255,255,.03);

    border:
        1px solid
        rgba(255,255,255,.06);
}

.mini-stat-label {

    color: #64748b;

    font-size: 9px;

    margin-bottom: 7px;
}

.mini-stat-value {

    font-size: 15px;

    font-weight: 850;
}

/* =====================================================
   TABLE
===================================================== */

.table-wrap {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 760px;
}

th {

    text-align: left;

    color: #64748b;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: .8px;

    font-weight: 800;

    padding:
        11px 12px;

    border-bottom:
        1px solid
        rgba(255,255,255,.07);
}

td {

    padding:
        14px 12px;

    border-bottom:
        1px solid
        rgba(255,255,255,.045);

    color: #cbd5e1;

    font-size: 11px;
}

tr:hover td {

    background:
        rgba(255,255,255,.018);
}

.sampah-name {

    color: white;

    font-weight: 750;
}

.date {

    color: #64748b;

    font-size: 10px;
}

.money {

    color: #6ee7b7;

    font-weight: 800;
}

.status {

    display: inline-flex;

    padding:
        5px 8px;

    border-radius: 7px;

    font-size: 8px;

    font-weight: 850;
}

.status.pending {

    color: #fcd34d;

    background:
        rgba(251,191,36,.10);
}

.status.approved {

    color: #6ee7b7;

    background:
        rgba(34,197,94,.10);
}

.status.rejected {

    color: #fda4af;

    background:
        rgba(251,113,133,.10);
}

.empty {

    padding: 45px 15px;

    text-align: center;

    color: #64748b;

    font-size: 11px;
}

.empty-icon {

    font-size: 30px;

    margin-bottom: 10px;
}

/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1100px) {

    .main-grid {

        grid-template-columns: 1fr;
    }

}

@media (max-width: 800px) {

    :root {
        --sidebar-width: 0px;
    }

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

        border-right: none;

        border-bottom:
            1px solid var(--border);

        padding: 20px;
    }

    .brand {

        margin-bottom: 20px;
    }

    .menu-title {

        display: none;
    }

    .nav {

        flex-direction: row;

        overflow-x: auto;

        padding-bottom: 5px;
    }

    .nav a {

        white-space: nowrap;
    }

    .sidebar-spacer,
    .account-box,
    .logout {

        display: none;
    }

    .main {

        margin-left: 0;
    }

    .topbar {

        position: relative;

        padding: 0 20px;
    }

    .content {

        padding:
            24px 18px 40px;
    }

    .hero-inner {

        flex-direction: column;

        align-items: flex-start;
    }

    .hero-badge {

        width: 100%;
    }

}

@media (max-width: 600px) {

    .mini-stats {

        grid-template-columns: 1fr;
    }

    .price-preview {

        grid-template-columns: 1fr;
    }

    .hero-title {

        font-size: 25px;
    }

    .clock {

        display: none;
    }

}

</style>

    <link rel="stylesheet" href="assets/siswa-theme.css">
</head>

<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<?php include __DIR__ . '/siswa_sidebar.php'; ?>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


<?php include __DIR__ . '/siswa_topbar.php'; ?>
<span id="clock" hidden></span>


<section class="content">


<!-- =====================================================
     HERO
===================================================== -->

<div class="page-hero">

    <div class="hero-inner">

        <div>

            <div class="hero-label">
                ♻️ Setoran Baru
            </div>

            <div class="hero-title">

                Yuk setor sampah,
                <span>
                    <?php
                    echo htmlspecialchars($nama_siswa);
                    ?>
                </span>!

            </div>

            <div class="hero-desc">

                Pilih jenis sampah yang tersedia,
                masukkan beratnya, lalu kirim pengajuan.
                Setoran akan diperiksa Guru/Admin sebelum
                saldo masuk ke akunmu.

            </div>

        </div>


        <div class="hero-badge">

            <div class="hero-badge-icon">
                🌱
            </div>

            <strong>
                Sedikit demi sedikit
            </strong>

            <small>
                Sampah jadi nilai
            </small>

        </div>

    </div>

</div>


<!-- =====================================================
     ALERT
===================================================== -->

<?php if ($success): ?>

    <div class="alert alert-success">

        ✅
        <?php
        echo htmlspecialchars($success);
        ?>

    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="alert alert-error">

        ⚠️
        <?php
        echo htmlspecialchars($error);
        ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     MAIN FORM
===================================================== -->

<div class="main-grid">


<!-- FORM -->

<div class="card">

    <div class="card-header">

        <div>

            <div class="card-title">
                Buat Pengajuan Setoran
            </div>

            <div class="card-subtitle">
                Isi data sampah yang ingin kamu setor
            </div>

        </div>

        <div style="font-size:22px;">
            📦
        </div>

    </div>


    <form
        method="POST"
        action=""
        id="formSetor"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?php
            echo htmlspecialchars($csrf_token);
            ?>"
        >


        <!-- JENIS SAMPAH -->

        <div class="form-group">

            <label
                class="form-label"
                for="id_sampah"
            >
                Jenis Sampah
                <span>
                    • wajib dipilih
                </span>
            </label>


            <div class="select-wrap">

                <select
                    name="id_sampah"
                    id="id_sampah"
                    class="form-control"
                    required
                >

                    <option value="">
                        — Pilih jenis sampah —
                    </option>


                    <?php foreach ($data_sampah as $sampah): ?>

                        <option
                            value="<?php
                            echo (int)$sampah['id_sampah'];
                            ?>"
                            data-harga="<?php
                            echo (int)$sampah['harga'];
                            ?>"
                            data-satuan="<?php
                            echo htmlspecialchars(
                                $sampah['satuan'] ?: 'Kg'
                            );
                            ?>"
                        >

                            <?php
                            echo htmlspecialchars(
                                $sampah['nama_sampah']
                            );
                            ?>

                            —
                            <?php
                            echo rupiah($sampah['harga']);
                            ?>

                            /
                            <?php
                            echo htmlspecialchars(
                                $sampah['satuan'] ?: 'Kg'
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <!-- PRICE -->

        <div class="price-preview">

            <div class="price-box">

                <div class="price-box-label">
                    HARGA SAMPAH
                </div>

                <div
                    class="price-box-value"
                    id="hargaText"
                >
                    Rp 0
                </div>

            </div>


            <div class="price-box">

                <div class="price-box-label">
                    SATUAN
                </div>

                <div
                    class="price-box-value"
                    id="satuanText"
                >
                    Kg
                </div>

            </div>

        </div>


        <!-- BERAT -->

        <div class="form-group">

            <label
                class="form-label"
                for="berat"
            >
                Berat Sampah
                <span>
                    • maksimal 1.000 Kg
                </span>
            </label>


            <input
                type="number"
                name="berat"
                id="berat"
                class="form-control"
                placeholder="Contoh: 2.5"
                min="0.01"
                max="1000"
                step="0.01"
                required
            >

        </div>


        <!-- TOTAL -->

        <div class="total-box">

            <div class="total-label">
                Estimasi Nilai Setoran
            </div>

            <div
                class="total-value"
                id="totalText"
            >
                Rp 0
            </div>

            <div class="total-note">

                Nilai final mengikuti hasil verifikasi
                Guru/Admin.

            </div>

        </div>


        <button
            type="submit"
            class="submit-btn"
            id="submitBtn"
        >

            ♻️
            Kirim Pengajuan Setoran

        </button>

    </form>

</div>


<!-- PETUNJUK -->

<div class="card">

    <div class="card-header">

        <div>

            <div class="card-title">
                Cara Setor Sampah
            </div>

            <div class="card-subtitle">
                Ikuti langkah sederhana berikut
            </div>

        </div>

        <div style="font-size:22px;">
            💡
        </div>

    </div>


    <div class="guide-list">


        <div class="guide-item">

            <div class="guide-number">
                01
            </div>

            <div class="guide-text">

                <strong>
                    Pilih jenis sampah
                </strong>

                <span>
                    Pilih jenis sampah yang sudah
                    tersedia dan disetujui oleh
                    pengelola Bank Sampah Ataya.
                </span>

            </div>

        </div>


        <div class="guide-item">

            <div class="guide-number">
                02
            </div>

            <div class="guide-text">

                <strong>
                    Masukkan berat
                </strong>

                <span>
                    Masukkan berat sampah sesuai
                    hasil penimbangan.
                </span>

            </div>

        </div>


        <div class="guide-item">

            <div class="guide-number">
                03
            </div>

            <div class="guide-text">

                <strong>
                    Cek estimasi
                </strong>

                <span>
                    Sistem otomatis menghitung
                    perkiraan nilai berdasarkan
                    harga sampah.
                </span>

            </div>

        </div>


        <div class="guide-item">

            <div class="guide-number">
                04
            </div>

            <div class="guide-text">

                <strong>
                    Kirim pengajuan
                </strong>

                <span>
                    Setelah dikirim, status menjadi
                    pending sampai diverifikasi
                    Guru/Admin.
                </span>

            </div>

        </div>


        <div class="guide-item">

            <div class="guide-number">
                05
            </div>

            <div class="guide-text">

                <strong>
                    Saldo bertambah
                </strong>

                <span>
                    Jika disetujui, nilai transaksi
                    akan masuk dalam perhitungan
                    saldo kamu.
                </span>

            </div>

        </div>

    </div>

</div>

</div>


<!-- =====================================================
     MINI STATS
===================================================== -->

<div class="mini-stats">

    <div class="mini-stat">

        <div class="mini-stat-label">
            TRANSAKSI DISETUJUI
        </div>

        <div class="mini-stat-value">

            <?php
            echo $jumlah_disetujui;
            ?>

        </div>

    </div>


    <div class="mini-stat">

        <div class="mini-stat-label">
            TOTAL BERAT
        </div>

        <div class="mini-stat-value">

            <?php
            echo beratFormat($total_berat);
            ?>

            Kg

        </div>

    </div>


    <div class="mini-stat">

        <div class="mini-stat-label">
            NILAI SETORAN
        </div>

        <div
            class="mini-stat-value"
            style="color:#6ee7b7;"
        >

            <?php
            echo rupiah($total_nilai);
            ?>

        </div>

    </div>

</div>


<!-- =====================================================
     RIWAYAT
===================================================== -->

<div class="card">

    <div class="card-header">

        <div>

            <div class="card-title">
                Riwayat Setoran
            </div>

            <div class="card-subtitle">
                Pengajuan setoran terbaru kamu
            </div>

        </div>

        <div
            style="
                color:#64748b;
                font-size:10px;
            "
        >
            Maks. 12 transaksi
        </div>

    </div>


    <div class="table-wrap">

        <?php if (count($data_riwayat) > 0): ?>

            <table>

                <thead>

                    <tr>

                        <th>
                            Tanggal
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

                <?php foreach ($data_riwayat as $row): ?>

                    <tr>

                        <td>

                            <div class="date">

                                <?php
                                echo date(
                                    'd M Y',
                                    strtotime(
                                        $row['tanggal']
                                    )
                                );
                                ?>

                            </div>

                        </td>


                        <td>

                            <div class="sampah-name">

                                <?php
                                echo htmlspecialchars(
                                    $row['nama_sampah']
                                );
                                ?>

                            </div>

                        </td>


                        <td>

                            <?php
                            echo beratFormat(
                                $row['berat']
                            );
                            ?>

                            <?php
                            echo htmlspecialchars(
                                $row['satuan'] ?: 'Kg'
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo rupiah(
                                $row['harga']
                            );
                            ?>

                        </td>


                        <td>

                            <div class="money">

                                <?php
                                echo rupiah(
                                    $row['total']
                                );
                                ?>

                            </div>

                        </td>


                        <td>

                            <span
                                class="status <?php
                                echo statusClass(
                                    $row['status']
                                );
                                ?>"
                            >

                                <?php
                                echo ucfirst(
                                    $row['status']
                                );
                                ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">
                    ♻️
                </div>

                Belum ada riwayat setoran.

            </div>

        <?php endif; ?>

    </div>

</div>


</section>

</main>


<script>

/*
|--------------------------------------------------------------------------
| DATA HARGA DARI SELECT
|--------------------------------------------------------------------------
*/

const selectSampah =
    document.getElementById('id_sampah');

const inputBerat =
    document.getElementById('berat');

const hargaText =
    document.getElementById('hargaText');

const satuanText =
    document.getElementById('satuanText');

const totalText =
    document.getElementById('totalText');


/*
|--------------------------------------------------------------------------
| FORMAT RUPIAH
|--------------------------------------------------------------------------
*/

function formatRupiah(angka) {

    return new Intl.NumberFormat(
        'id-ID',
        {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }
    ).format(angka);

}


/*
|--------------------------------------------------------------------------
| UPDATE ESTIMASI
|--------------------------------------------------------------------------
*/

function updateEstimasi() {

    const option =
        selectSampah.options[
            selectSampah.selectedIndex
        ];

    if (!option || !option.value) {

        hargaText.textContent =
            'Rp 0';

        satuanText.textContent =
            'Kg';

        totalText.textContent =
            'Rp 0';

        return;
    }


    const harga =
        parseFloat(
            option.dataset.harga || 0
        );

    const satuan =
        option.dataset.satuan || 'Kg';

    const berat =
        parseFloat(
            inputBerat.value || 0
        );


    hargaText.textContent =
        formatRupiah(harga);


    satuanText.textContent =
        satuan;


    const total =
        harga * berat;


    totalText.textContent =
        formatRupiah(
            isFinite(total)
                ? total
                : 0
        );

}


/*
|--------------------------------------------------------------------------
| EVENT
|--------------------------------------------------------------------------
*/

selectSampah.addEventListener(
    'change',
    updateEstimasi
);

inputBerat.addEventListener(
    'input',
    updateEstimasi
);


/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/

document
    .getElementById('formSetor')
    .addEventListener(
        'submit',
        function(event) {

            const berat =
                parseFloat(
                    inputBerat.value || 0
                );

            if (berat <= 0) {

                event.preventDefault();

                alert(
                    'Masukkan berat sampah terlebih dahulu.'
                );

                return;
            }


            if (berat > 1000) {

                event.preventDefault();

                alert(
                    'Berat maksimal adalah 1.000 Kg.'
                );

                return;
            }


            const btn =
                document.getElementById(
                    'submitBtn'
                );

            btn.disabled = true;

            btn.textContent =
                '⏳ Mengirim pengajuan...';

        }
    );


/*
|--------------------------------------------------------------------------
| CLOCK
|--------------------------------------------------------------------------
*/

function updateClock() {

    const now = new Date();

    const time =
        now.toLocaleTimeString(
            'id-ID',
            {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }
        );

    const date =
        now.toLocaleDateString(
            'id-ID',
            {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            }
        );

    document.getElementById(
        'clock'
    ).textContent =
        date + ' • ' + time;
}

updateClock();

setInterval(
    updateClock,
    1000
);


/*
|--------------------------------------------------------------------------
| INIT
|--------------------------------------------------------------------------
*/

updateEstimasi();

</script>

<script src="assets/siswa.js"></script>
</body>

</html>