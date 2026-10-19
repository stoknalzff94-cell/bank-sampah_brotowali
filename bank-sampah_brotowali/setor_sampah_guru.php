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

$pesan = '';
$tipe_pesan = '';

/* =========================================================
   PROSES TAMBAH SETORAN
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_nasabah = (int)($_POST['id_nasabah'] ?? 0);
    $id_sampah  = (int)($_POST['id_sampah'] ?? 0);
    $tanggal    = $_POST['tanggal'] ?? date('Y-m-d');
    $berat      = (float)($_POST['berat'] ?? 0);

    if ($id_nasabah <= 0 || $id_sampah <= 0) {

        $pesan = 'Nasabah dan jenis sampah wajib dipilih.';
        $tipe_pesan = 'error';

    } elseif ($berat <= 0) {

        $pesan = 'Berat sampah harus lebih dari 0 kg.';
        $tipe_pesan = 'error';

    } else {

        /* =================================================
           AMBIL DATA SAMPAH
           HANYA YANG SUDAH DISETUJUI
        ================================================= */

        $stmt = mysqli_prepare($koneksi, "
            SELECT nama_sampah, harga, satuan
            FROM sampah
            WHERE id_sampah = ?
            AND status = 'disetujui'
            LIMIT 1
        ");

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id_sampah
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $data_sampah = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if (!$data_sampah) {

            $pesan = 'Jenis sampah tidak ditemukan atau belum disetujui.';
            $tipe_pesan = 'error';

        } else {

            $harga = (int)$data_sampah['harga'];
            $total = (int)round($berat * $harga);

            /* =============================================
               CEK NASABAH
            ============================================= */

            $stmt = mysqli_prepare($koneksi, "
                SELECT id_nasabah
                FROM nasabah
                WHERE id_nasabah = ?
                LIMIT 1
            ");

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id_nasabah
            );

            mysqli_stmt_execute($stmt);

            $result_nasabah =
                mysqli_stmt_get_result($stmt);

            $nasabah_valid =
                mysqli_fetch_assoc($result_nasabah);

            mysqli_stmt_close($stmt);

            if (!$nasabah_valid) {

                $pesan = 'Nasabah tidak ditemukan.';
                $tipe_pesan = 'error';

            } else {

                /* =========================================
                   SIMPAN TRANSAKSI
                   STATUS DEFAULT = pending
                ========================================= */

                $stmt = mysqli_prepare($koneksi, "
                    INSERT INTO transaksi
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
                    (?, ?, ?, ?, ?, ?, 'pending')
                ");

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

                    $pesan =
                        'Setoran berhasil dicatat dan menunggu verifikasi.';

                    $tipe_pesan = 'success';

                    $_POST = [];

                } else {

                    $pesan =
                        'Gagal menyimpan setoran: ' .
                        mysqli_error($koneksi);

                    $tipe_pesan = 'error';
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}


/* =========================================================
   DATA NASABAH
========================================================= */

$nasabah_list = [];

$q_nasabah = mysqli_query(
    $koneksi,
    "
    SELECT
        id_nasabah,
        nama,
        alamat
    FROM nasabah
    ORDER BY nama ASC
    "
);

while ($row = mysqli_fetch_assoc($q_nasabah)) {
    $nasabah_list[] = $row;
}


/* =========================================================
   DATA SAMPAH APPROVED
========================================================= */

$sampah_list = [];

$q_sampah = mysqli_query(
    $koneksi,
    "
    SELECT
        id_sampah,
        nama_sampah,
        harga,
        satuan
    FROM sampah
    WHERE status = 'disetujui'
    ORDER BY nama_sampah ASC
    "
);

while ($row = mysqli_fetch_assoc($q_sampah)) {
    $sampah_list[] = $row;
}


/* =========================================================
   TRANSAKSI TERBARU GURU
========================================================= */

$recent_transactions = [];

$q_recent = mysqli_query(
    $koneksi,
    "
    SELECT
        transaksi.id_transaksi,
        transaksi.tanggal,
        transaksi.berat,
        transaksi.harga,
        transaksi.total,
        transaksi.status,
        nasabah.nama,
        sampah.nama_sampah
    FROM transaksi

    INNER JOIN nasabah
        ON nasabah.id_nasabah =
           transaksi.id_nasabah

    INNER JOIN sampah
        ON sampah.id_sampah =
           transaksi.id_sampah

    ORDER BY transaksi.id_transaksi DESC

    LIMIT 6
    "
);

while ($row = mysqli_fetch_assoc($q_recent)) {
    $recent_transactions[] = $row;
}


/* =========================================================
   FORMATTER
========================================================= */

function rupiah($angka)
{
    return 'Rp ' .
        number_format(
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
        2,
        ',',
        '.'
    );
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

<title>Setor Sampah — Guru Ataya</title>

<style>

/* =========================================================
   ROOT
========================================================= */

:root {

    --bg: #070b18;
    --bg-soft: #0a1020;

    --sidebar: #0b1022;

    --card: #10172a;
    --card-2: #131c33;

    --border: #202a46;

    --primary: #6366f1;
    --primary-dark: #4f46e5;

    --violet: #8b5cf6;
    --blue: #38bdf8;

    --success: #34d399;
    --warning: #fbbf24;
    --danger: #fb7185;

    --text: #f8fafc;
    --muted: #94a3b8;
    --muted-2: #64748b;
}


/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
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
            circle at 10% 85%,
            rgba(139,92,246,.08),
            transparent 28%
        ),

        var(--bg);
}


/* =========================================================
   SCROLLBAR
========================================================= */

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
        1px solid rgba(255,255,255,.06);

    z-index: 100;

    overflow-y: auto;
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

    box-shadow:
        inset 0 0 25px
        rgba(99,102,241,.04);
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

    min-height: 100vh;

    padding:
        28px 34px 50px;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    display: flex;

    align-items: center;

    justify-content: space-between;

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
   PAGE HEADER
========================================================= */

.page-header {

    position: relative;

    overflow: hidden;

    padding: 28px;

    border-radius: 22px;

    background:

        radial-gradient(
            circle at 90% 20%,
            rgba(56,189,248,.12),
            transparent 25%
        ),

        radial-gradient(
            circle at 70% 100%,
            rgba(139,92,246,.20),
            transparent 42%
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


.page-header::after {

    content: "";

    position: absolute;

    width: 230px;
    height: 230px;

    right: -80px;
    top: -130px;

    border-radius: 50%;

    background:
        radial-gradient(
            circle,
            rgba(99,102,241,.24),
            transparent 65%
        );

    filter: blur(8px);
}


.header-content {

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


.page-header h1 {

    margin:
        15px 0 7px;

    font-size: 32px;

    letter-spacing: -1px;
}


.page-header p {

    margin: 0;

    max-width: 650px;

    color: #8997b1;

    font-size: 13px;

    line-height: 1.7;
}


/* =========================================================
   LAYOUT
========================================================= */

.page-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1.4fr)
        minmax(290px, .7fr);

    gap: 18px;

    margin-top: 18px;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background:
        linear-gradient(
            145deg,
            rgba(16,23,42,.97),
            rgba(12,18,34,.97)
        );

    border:
        1px solid var(--border);

    border-radius: 20px;

    box-shadow:
        0 14px 45px
        rgba(0,0,0,.16);

    overflow: hidden;
}


.card-header {

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

    font-size: 10px;
}


.card-body {

    padding:
        22px;
}


/* =========================================================
   FORM
========================================================= */

.form-group {

    margin-bottom: 18px;
}


.form-label {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 8px;

    color: #c7d2e5;

    font-size: 11px;

    font-weight: 700;
}


.form-label span {

    color: var(--danger);

    font-size: 10px;
}


.form-control {

    width: 100%;

    height: 48px;

    padding:
        0 14px;

    border:
        1px solid #26314e;

    border-radius: 12px;

    outline: none;

    background:
        rgba(7,11,24,.75);

    color: #e7edf8;

    font-family: inherit;

    font-size: 12px;

    transition: .2s ease;
}


.form-control:focus {

    border-color:
        rgba(99,102,241,.75);

    box-shadow:
        0 0 0 3px
        rgba(99,102,241,.10),

        0 0 25px
        rgba(99,102,241,.06);
}


select.form-control {

    cursor: pointer;
}


textarea.form-control {

    height: 90px;

    padding-top: 13px;

    resize: vertical;
}


.input-hint {

    margin-top: 6px;

    color: #56647c;

    font-size: 9px;

    line-height: 1.5;
}


/* =========================================================
   TWO COLUMN FORM
========================================================= */

.form-row {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 14px;
}


/* =========================================================
   CALCULATION
========================================================= */

.calculation {

    position: relative;

    margin-top: 20px;

    padding: 20px;

    border-radius: 16px;

    background:

        radial-gradient(
            circle at 90% 20%,
            rgba(56,189,248,.09),
            transparent 30%
        ),

        linear-gradient(
            135deg,
            rgba(99,102,241,.10),
            rgba(139,92,246,.06)
        );

    border:
        1px solid
        rgba(99,102,241,.16);
}


.calc-title {

    color: #8fa0bd;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 1px;
}


.calc-line {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-top: 11px;

    color: #9ba8bc;

    font-size: 11px;
}


.calc-line strong {

    color: #dce5f4;

    font-size: 12px;
}


.calc-total {

    display: flex;

    align-items: end;

    justify-content: space-between;

    margin-top: 17px;

    padding-top: 15px;

    border-top:
        1px solid
        rgba(255,255,255,.07);
}


.calc-total span {

    color: #8492aa;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 1px;
}


.calc-total strong {

    background:
        linear-gradient(
            90deg,
            #a5b4fc,
            #67e8f9
        );

    -webkit-background-clip: text;

    background-clip: text;

    color: transparent;

    font-size: 25px;

    letter-spacing: -.8px;
}


/* =========================================================
   BUTTON
========================================================= */

.btn-submit {

    width: 100%;

    height: 49px;

    margin-top: 20px;

    border: 0;

    border-radius: 13px;

    color: #fff;

    background:
        linear-gradient(
            135deg,
            var(--primary),
            var(--violet)
        );

    box-shadow:
        0 12px 30px
        rgba(99,102,241,.22);

    cursor: pointer;

    font-family: inherit;

    font-size: 12px;

    font-weight: 800;

    transition: .2s ease;
}


.btn-submit:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 17px 35px
        rgba(99,102,241,.30);
}


/* =========================================================
   ALERT
========================================================= */

.alert {

    display: flex;

    align-items: flex-start;

    gap: 10px;

    margin-top: 18px;

    padding:
        14px 15px;

    border-radius: 13px;

    font-size: 11px;

    line-height: 1.5;
}


.alert.success {

    background:
        rgba(52,211,153,.07);

    border:
        1px solid
        rgba(52,211,153,.15);

    color: #86efac;
}


.alert.error {

    background:
        rgba(251,113,133,.07);

    border:
        1px solid
        rgba(251,113,133,.15);

    color: #fda4af;
}


/* =========================================================
   INFO CARD
========================================================= */

.info-list {

    display: flex;

    flex-direction: column;

    gap: 10px;
}


.info-item {

    display: flex;

    gap: 12px;

    padding:
        13px;

    border-radius: 13px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(255,255,255,.045);
}


.info-icon {

    flex: 0 0 auto;

    width: 33px;
    height: 33px;

    display: grid;

    place-items: center;

    border-radius: 10px;

    background:
        rgba(99,102,241,.10);

    color: #a5b4fc;

    font-size: 15px;
}


.info-title {

    font-size: 11px;

    font-weight: 750;
}


.info-text {

    margin-top: 4px;

    color: var(--muted-2);

    font-size: 9px;

    line-height: 1.5;
}


/* =========================================================
   PRICE DISPLAY
========================================================= */

.price-box {

    margin-top: 18px;

    padding: 17px;

    border-radius: 15px;

    background:
        rgba(56,189,248,.04);

    border:
        1px solid
        rgba(56,189,248,.10);
}


.price-label {

    color: #64748b;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: 1px;
}


.price-name {

    margin-top: 6px;

    color: #dce5f4;

    font-size: 12px;

    font-weight: 750;
}


.price-value {

    margin-top: 4px;

    color: #67e8f9;

    font-size: 19px;

    font-weight: 800;
}


/* =========================================================
   TABLE
========================================================= */

.recent {

    margin-top: 18px;
}


.table-scroll {

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 680px;

    border-collapse: collapse;
}


th {

    padding:
        12px 20px;

    text-align: left;

    color: #64748b;

    background:
        rgba(255,255,255,.018);

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: 1px;
}


td {

    padding:
        14px 20px;

    border-top:
        1px solid
        rgba(255,255,255,.045);

    color: #b6c1d4;

    font-size: 11px;
}


tbody tr {

    transition: .2s ease;
}


tbody tr:hover {

    background:
        rgba(99,102,241,.035);
}


.person {

    display: flex;

    align-items: center;

    gap: 10px;
}


.avatar {

    width: 30px;
    height: 30px;

    display: grid;

    place-items: center;

    border-radius: 9px;

    background:
        linear-gradient(
            135deg,
            rgba(99,102,241,.23),
            rgba(139,92,246,.12)
        );

    color: #c4b5fd;

    font-size: 10px;

    font-weight: 800;
}


.person-name {

    color: #dce5f4;

    font-weight: 700;
}


.person-date {

    margin-top: 2px;

    color: #58667e;

    font-size: 9px;
}


.status {

    display: inline-flex;

    padding:
        5px 8px;

    border-radius: 999px;

    font-size: 9px;

    font-weight: 800;
}


.status.pending {

    background:
        rgba(251,191,36,.08);

    color: #fcd34d;
}


.status.approved {

    background:
        rgba(52,211,153,.08);

    color: #6ee7b7;
}


.status.rejected {

    background:
        rgba(251,113,133,.08);

    color: #fda4af;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:
        35px 20px;

    text-align: center;

    color: #596780;

    font-size: 11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .page-grid {

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

    .nav a.active::before {

        left: 0;
    }

    .main {

        margin-left: 78px;

        padding:
            20px;
    }
}


@media (max-width: 600px) {

    .main {

        padding:
            15px;
    }

    .clock {

        display: none;
    }

    .topbar {

        margin-bottom: 15px;
    }

    .page-header {

        padding:
            23px;
    }

    .page-header h1 {

        font-size: 27px;
    }

    .form-row {

        grid-template-columns: 1fr;
    }

    .card-body {

        padding:
            18px;
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


    <!-- =================================================
         HEADER
    ================================================== -->

    <section class="page-header">

        <div class="header-content">

            <div class="eyebrow">
                ✦ GURU WORKSPACE
            </div>

            <h1>
                Catat Setoran
            </h1>

            <p>
                Masukkan data setoran sampah nasabah.
                Setiap transaksi baru akan masuk sebagai
                <strong style="color:#a5b4fc;">
                    pending
                </strong>
                dan dapat diverifikasi melalui menu
                Verifikasi Transaksi.
            </p>

        </div>

    </section>


    <!-- =================================================
         FORM + INFO
    ================================================== -->

    <section class="page-grid">


        <!-- FORM -->

        <div class="card">

            <div class="card-header">

                <div class="card-title">
                    Form Setoran Sampah
                </div>

                <div class="card-subtitle">
                    Lengkapi data transaksi di bawah
                </div>

            </div>


            <div class="card-body">


                <?php if ($pesan): ?>

                    <div class="alert <?= $tipe_pesan ?>">

                        <span>
                            <?= $tipe_pesan === 'success'
                                ? '✓'
                                : '!'
                            ?>
                        </span>

                        <div>
                            <?= htmlspecialchars($pesan) ?>
                        </div>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    id="setoranForm"
                >


                    <!-- NASABAH -->

                    <div class="form-group">

                        <label class="form-label">

                            <span style="color:#c7d2e5;">
                                Nasabah
                            </span>

                            <span>* wajib</span>

                        </label>


                        <select
                            name="id_nasabah"
                            id="id_nasabah"
                            class="form-control"
                            required
                        >

                            <option value="">
                                Pilih nasabah...
                            </option>


                            <?php foreach (
                                $nasabah_list
                                as $nasabah
                            ): ?>

                                <option
                                    value="<?= (int)$nasabah['id_nasabah'] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $nasabah['nama']
                                    ) ?>

                                    <?php if (
                                        !empty($nasabah['alamat'])
                                    ): ?>

                                        —
                                        <?= htmlspecialchars(
                                            $nasabah['alamat']
                                        ) ?>

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>


                        <div class="input-hint">

                            Pilih nasabah yang melakukan
                            penyetoran sampah.

                        </div>

                    </div>


                    <!-- SAMPAH -->

                    <div class="form-group">

                        <label class="form-label">

                            <span style="color:#c7d2e5;">
                                Jenis Sampah
                            </span>

                            <span>* wajib</span>

                        </label>


                        <select
                            name="id_sampah"
                            id="id_sampah"
                            class="form-control"
                            required
                        >

                            <option value="">
                                Pilih jenis sampah...
                            </option>


                            <?php foreach (
                                $sampah_list
                                as $sampah
                            ): ?>

                                <option
                                    value="<?= (int)$sampah['id_sampah'] ?>"
                                    data-harga="<?= (int)$sampah['harga'] ?>"
                                    data-satuan="<?= htmlspecialchars(
                                        $sampah['satuan'] ?? 'kg'
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $sampah['nama_sampah']
                                    ) ?>

                                    —
                                    <?= rupiah(
                                        $sampah['harga']
                                    ) ?>

                                    /
                                    <?= htmlspecialchars(
                                        $sampah['satuan'] ?? 'kg'
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>


                        <div class="input-hint">

                            Hanya jenis sampah dengan status
                            <strong style="color:#6ee7b7;">
                                disetujui
                            </strong>
                            yang tersedia.

                        </div>

                    </div>


                    <!-- TANGGAL + BERAT -->

                    <div class="form-row">


                        <div class="form-group">

                            <label class="form-label">

                                <span style="color:#c7d2e5;">
                                    Tanggal
                                </span>

                                <span>* wajib</span>

                            </label>


                            <input
                                type="date"
                                name="tanggal"
                                id="tanggal"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $_POST['tanggal']
                                    ?? date('Y-m-d')
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label class="form-label">

                                <span style="color:#c7d2e5;">
                                    Berat
                                </span>

                                <span>* wajib</span>

                            </label>


                            <input
                                type="number"
                                name="berat"
                                id="berat"
                                class="form-control"
                                placeholder="Contoh: 2.5"
                                min="0.01"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    $_POST['berat'] ?? ''
                                ) ?>"
                                required
                            >


                            <div
                                class="input-hint"
                                id="satuanHint"
                            >
                                Satuan: kg
                            </div>

                        </div>

                    </div>


                    <!-- CALCULATION -->

                    <div class="calculation">

                        <div class="calc-title">
                            Estimasi Nilai Setoran
                        </div>


                        <div class="calc-line">

                            <span>
                                Harga per satuan
                            </span>

                            <strong id="hargaText">
                                Rp 0
                            </strong>

                        </div>


                        <div class="calc-line">

                            <span>
                                Berat
                            </span>

                            <strong id="beratText">
                                0 kg
                            </strong>

                        </div>


                        <div class="calc-total">

                            <span>
                                Total
                            </span>

                            <strong id="totalText">
                                Rp 0
                            </strong>

                        </div>

                    </div>


                    <button
                        type="submit"
                        class="btn-submit"
                    >

                        ♻
                        &nbsp;
                        Simpan Setoran

                    </button>

                </form>

            </div>

        </div>


        <!-- INFO -->

        <div class="card">

            <div class="card-header">

                <div class="card-title">
                    Alur Setoran
                </div>

                <div class="card-subtitle">
                    Proses transaksi Guru
                </div>

            </div>


            <div class="card-body">


                <div class="info-list">


                    <div class="info-item">

                        <div class="info-icon">
                            01
                        </div>

                        <div>

                            <div class="info-title">
                                Pilih Nasabah
                            </div>

                            <div class="info-text">
                                Tentukan nasabah yang
                                menyerahkan sampah.
                            </div>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-icon">
                            02
                        </div>

                        <div>

                            <div class="info-title">
                                Pilih Sampah
                            </div>

                            <div class="info-text">
                                Gunakan jenis sampah yang
                                sudah disetujui.
                            </div>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-icon">
                            03
                        </div>

                        <div>

                            <div class="info-title">
                                Masukkan Berat
                            </div>

                            <div class="info-text">
                                Sistem menghitung nilai
                                setoran secara otomatis.
                            </div>

                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-icon">
                            04
                        </div>

                        <div>

                            <div class="info-title">
                                Menunggu Verifikasi
                            </div>

                            <div class="info-text">
                                Transaksi disimpan sebagai
                                pending sampai diverifikasi.
                            </div>

                        </div>

                    </div>

                </div>


                <div class="price-box">

                    <div class="price-label">
                        Jenis Sampah Dipilih
                    </div>

                    <div
                        class="price-name"
                        id="selectedSampah"
                    >
                        Belum dipilih
                    </div>

                    <div
                        class="price-value"
                        id="selectedHarga"
                    >
                        Rp 0
                    </div>

                </div>


                <div
                    style="
                        margin-top:15px;
                        padding:13px;
                        border-radius:13px;
                        background:rgba(251,191,36,.045);
                        border:1px solid rgba(251,191,36,.10);
                        color:#a89970;
                        font-size:10px;
                        line-height:1.6;
                    "
                >

                    <strong style="color:#fcd34d;">
                        Catatan:
                    </strong>

                    Setoran yang dibuat melalui
                    halaman Guru tidak langsung masuk
                    sebagai saldo final. Transaksi harus
                    melalui proses verifikasi terlebih dahulu.

                </div>

            </div>

        </div>

    </section>


    <!-- =================================================
         RECENT TRANSACTIONS
    ================================================== -->

    <section class="card recent">

        <div class="card-header">

            <div class="card-title">
                Aktivitas Setoran Terbaru
            </div>

            <div class="card-subtitle">
                Transaksi terakhir yang tercatat di sistem
            </div>

        </div>


        <div class="table-scroll">

            <table>

                <thead>

                    <tr>

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
                            Total
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    count($recent_transactions) > 0
                ): ?>


                    <?php foreach (
                        $recent_transactions
                        as $trx
                    ): ?>


                        <?php

                        $nama =
                            $trx['nama']
                            ?? 'Tanpa Nama';

                        $inisial =
                            strtoupper(
                                substr(
                                    trim($nama),
                                    0,
                                    1
                                )
                            );

                        $status =
                            $trx['status']
                            ?? 'pending';

                        ?>


                        <tr>


                            <td>

                                <div class="person">

                                    <div class="avatar">
                                        <?= htmlspecialchars(
                                            $inisial
                                        ) ?>
                                    </div>

                                    <div>

                                        <div class="person-name">

                                            <?= htmlspecialchars(
                                                $nama
                                            ) ?>

                                        </div>

                                        <div class="person-date">

                                            <?= date(
                                                'd M Y',
                                                strtotime(
                                                    $trx['tanggal']
                                                )
                                            ) ?>

                                        </div>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $trx['nama_sampah']
                                ) ?>

                            </td>


                            <td>

                                <?= angka(
                                    $trx['berat']
                                ) ?>

                                kg

                            </td>


                            <td>

                                <strong
                                    style="color:#c4b5fd;"
                                >

                                    <?= rupiah(
                                        $trx['total']
                                    ) ?>

                                </strong>

                            </td>


                            <td>


                                <?php if (
                                    $status === 'disetujui'
                                ): ?>

                                    <span
                                        class="status approved"
                                    >
                                        DISETUJUI
                                    </span>

                                <?php elseif (
                                    $status === 'ditolak'
                                ): ?>

                                    <span
                                        class="status rejected"
                                    >
                                        DITOLAK
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="status pending"
                                    >
                                        PENDING
                                    </span>

                                <?php endif; ?>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="5"
                            class="empty"
                        >

                            Belum ada transaksi.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </section>


    <div
        style="
            margin-top:28px;
            text-align:center;
            color:#4f5d75;
            font-size:10px;
        "
    >

        Bank Sampah Ataya • Guru Workspace •
        <span id="year"></span>

    </div>


</main>


<script>

/* =========================================================
   CLOCK
========================================================= */

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

    document.getElementById(
        'clock'
    ).textContent = time;
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
   DATA SAMPAH
========================================================= */

const sampahSelect =
    document.getElementById(
        'id_sampah'
    );

const beratInput =
    document.getElementById(
        'berat'
    );

const hargaText =
    document.getElementById(
        'hargaText'
    );

const beratText =
    document.getElementById(
        'beratText'
    );

const totalText =
    document.getElementById(
        'totalText'
    );

const satuanHint =
    document.getElementById(
        'satuanHint'
    );

const selectedSampah =
    document.getElementById(
        'selectedSampah'
    );

const selectedHarga =
    document.getElementById(
        'selectedHarga'
    );


/* =========================================================
   RUPIAH
========================================================= */

function formatRupiah(value) {

    return 'Rp ' +
        Number(value || 0)
            .toLocaleString(
                'id-ID'
            );
}


/* =========================================================
   UPDATE CALCULATION
========================================================= */

function updateCalculation() {

    const option =
        sampahSelect.options[
            sampahSelect.selectedIndex
        ];

    if (
        !option ||
        !option.value
    ) {

        hargaText.textContent =
            'Rp 0';

        beratText.textContent =
            '0 kg';

        totalText.textContent =
            'Rp 0';

        selectedSampah.textContent =
            'Belum dipilih';

        selectedHarga.textContent =
            'Rp 0';

        satuanHint.textContent =
            'Satuan: kg';

        return;
    }


    const harga =
        Number(
            option.dataset.harga || 0
        );

    const satuan =
        option.dataset.satuan ||
        'kg';

    const berat =
        Number(
            beratInput.value || 0
        );

    const total =
        Math.round(
            harga * berat
        );


    hargaText.textContent =
        formatRupiah(harga) +
        ' / ' +
        satuan;


    beratText.textContent =
        berat.toLocaleString(
            'id-ID',
            {
                maximumFractionDigits: 2
            }
        ) +
        ' ' +
        satuan;


    totalText.textContent =
        formatRupiah(total);


    selectedSampah.textContent =
        option.textContent.trim()
            .split('—')[0]
            .trim();


    selectedHarga.textContent =
        formatRupiah(harga) +
        ' / ' +
        satuan;


    satuanHint.textContent =
        'Satuan: ' +
        satuan;
}


sampahSelect.addEventListener(
    'change',
    updateCalculation
);

beratInput.addEventListener(
    'input',
    updateCalculation
);

updateCalculation();

</script>

<script src="assets/guru.js"></script>
</body>

</html>