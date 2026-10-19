<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$koneksi = mysqli_connect(
    "localhost",
    "root",
    "",
    "banksampah_ataya"
);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($koneksi, "utf8mb4");

$nama_admin = $_SESSION['nama_user']
    ?? $_SESSION['nama']
    ?? 'Administrator';


/* =========================================================
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   FILTER TANGGAL
========================================================= */

$tanggal_mulai = trim($_GET['tanggal_mulai'] ?? '');
$tanggal_selesai = trim($_GET['tanggal_selesai'] ?? '');


$where = "WHERE t.status = 'disetujui'";


if ($tanggal_mulai !== '') {

    $tanggal_mulai_sql = mysqli_real_escape_string(
        $koneksi,
        $tanggal_mulai
    );

    $where .= "
        AND t.tanggal >= '$tanggal_mulai_sql'
    ";
}


if ($tanggal_selesai !== '') {

    $tanggal_selesai_sql = mysqli_real_escape_string(
        $koneksi,
        $tanggal_selesai
    );

    $where .= "
        AND t.tanggal <= '$tanggal_selesai_sql'
    ";
}


/* =========================================================
   STATISTIK
========================================================= */

$q_total_transaksi = mysqli_query(
    $koneksi,
    "
    SELECT COUNT(*) AS total
    FROM transaksi t
    $where
    "
);

$total_transaksi = (int)(
    mysqli_fetch_assoc($q_total_transaksi)['total'] ?? 0
);


$q_total_berat = mysqli_query(
    $koneksi,
    "
    SELECT COALESCE(SUM(t.berat), 0) AS total
    FROM transaksi t
    $where
    "
);

$total_berat = (float)(
    mysqli_fetch_assoc($q_total_berat)['total'] ?? 0
);


$q_total_nilai = mysqli_query(
    $koneksi,
    "
    SELECT COALESCE(SUM(t.total), 0) AS total
    FROM transaksi t
    $where
    "
);

$total_nilai = (int)(
    mysqli_fetch_assoc($q_total_nilai)['total'] ?? 0
);


$q_total_nasabah = mysqli_query(
    $koneksi,
    "
    SELECT COUNT(DISTINCT t.id_nasabah) AS total
    FROM transaksi t
    $where
    "
);

$total_nasabah = (int)(
    mysqli_fetch_assoc($q_total_nasabah)['total'] ?? 0
);


/* =========================================================
   DATA LAPORAN
========================================================= */

$query_laporan = "
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.berat,
        t.harga,
        t.total,

        n.id_nasabah,
        n.nama AS nama_nasabah,

        s.nama_sampah,
        s.satuan

    FROM transaksi t

    INNER JOIN nasabah n
        ON t.id_nasabah = n.id_nasabah

    INNER JOIN sampah s
        ON t.id_sampah = s.id_sampah

    $where

    ORDER BY
        t.tanggal DESC,
        t.id_transaksi DESC
";


$result_laporan = mysqli_query(
    $koneksi,
    $query_laporan
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Laporan | Bank Sampah</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Inter', sans-serif;

    background:
        radial-gradient(
            circle at top right,
            rgba(0,190,140,.07),
            transparent 28%
        ),
        #031f18;

    color: #f3fffb;

    min-height: 100vh;
}

a {
    text-decoration: none;
    color: inherit;
}

button,
input,
select,
textarea {
    font-family: inherit;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    width: 260px;

    background:
        linear-gradient(
            180deg,
            #062c24 0%,
            #05271f 55%,
            #03221b 100%
        );

    border-right: 1px solid #16483b;

    z-index: 1000;

    padding: 30px 17px 22px;

    display: flex;
    flex-direction: column;
}

.brand {
    padding: 0 11px 28px;

    display: flex;
    align-items: center;

    gap: 14px;
}

.brand-icon {
    width: 45px;
    height: 45px;

    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            #18d49b,
            #0ab87e
        );

    display: flex;
    align-items: center;
    justify-content: center;

    color: #05271f;

    font-size: 21px;
    font-weight: 800;

    box-shadow:
        0 10px 25px rgba(0,205,150,.17);
}

.brand-text h2 {
    font-size: 18px;
    letter-spacing: .5px;
}

.brand-text span {
    color: #69a99a;

    font-size: 10px;

    margin-top: 3px;

    display: block;
}

.menu-title {
    color: #5c998a;

    font-size: 10px;
    font-weight: 700;

    letter-spacing: 2px;

    margin: 4px 11px 13px;
}

.menu {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.menu a {
    min-height: 49px;

    display: flex;
    align-items: center;

    gap: 14px;

    padding: 0 20px;

    color: #77afa2;

    font-size: 13px;
    font-weight: 500;

    border: 1px solid transparent;

    border-radius: 13px;

    position: relative;

    transition: .2s;
}

.menu a:hover {
    color: #eafff9;

    background:
        rgba(18,163,123,.09);
}

.menu a.active {
    color: #ffffff;

    background:
        linear-gradient(
            90deg,
            rgba(16,159,120,.25),
            rgba(13,110,87,.20)
        );

    border-color: #14755e;
}

.menu a.active::before {
    content: "";

    position: absolute;

    left: -1px;

    top: 7px;
    bottom: 7px;

    width: 3px;

    background: #21e0a4;

    border-radius:
        0 4px 4px 0;

    box-shadow:
        0 0 12px rgba(33,224,164,.5);
}

.menu-icon {
    width: 17px;

    text-align: center;

    font-size: 15px;
}

.sidebar-line {
    height: 1px;

    background: #124237;

    margin: 23px 0 20px;
}

.account-title {
    color: #5c998a;

    font-size: 10px;
    font-weight: 700;

    letter-spacing: 2px;

    margin: 0 11px 11px;
}

.account-card {
    background:
        rgba(18,103,81,.35);

    border: 1px solid #12634f;

    border-radius: 14px;

    padding: 12px;

    display: flex;
    align-items: center;

    gap: 11px;
}

.account-avatar {
    width: 35px;
    height: 35px;

    border-radius: 50%;

    background: #49dfa8;

    color: #073125;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 800;

    font-size: 14px;
}

.account-info small {
    color: #5fa394;

    font-size: 8px;

    display: block;

    margin-bottom: 2px;
}

.account-info strong {
    font-size: 11px;

    color: #ffffff;
}

.logout {
    display: block;

    margin: 17px 11px 0;

    color: #69a99a;

    font-size: 11px;
}

.logout:hover {
    color: #ffffff;
}


/* =========================================================
   MAIN
========================================================= */

.main {
    margin-left: 260px;

    min-height: 100vh;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {
    height: 82px;

    border-bottom: 1px solid #123d32;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 35px;

    background:
        rgba(3,31,24,.75);
}

.top-title h3 {
    font-size: 14px;

    font-weight: 700;
}

.top-title p {
    margin-top: 6px;

    color: #63988c;

    font-size: 9px;
}

.system-status {
    border: 1px solid #145b49;

    background:
        rgba(8,74,58,.28);

    border-radius: 30px;

    padding: 9px 14px;

    color: #72b5a4;

    font-size: 9px;

    display: flex;

    align-items: center;

    gap: 8px;
}

.status-dot {
    width: 6px;
    height: 6px;

    border-radius: 50%;

    background: #22e3a3;

    box-shadow:
        0 0 10px rgba(34,227,163,.8);
}


/* =========================================================
   CONTENT
========================================================= */

.content {
    padding: 32px 36px 28px;
}

.page-head {
    display: flex;

    justify-content: space-between;

    align-items: flex-end;

    margin-bottom: 25px;
}

.breadcrumb {
    color: #35c89a;

    font-size: 9px;

    letter-spacing: 2px;

    font-weight: 700;

    margin-bottom: 10px;
}

.page-head h1 {
    font-size: 29px;

    line-height: 1.1;

    font-weight: 800;

    letter-spacing: -.8px;
}

.page-description {
    color: #65998d;

    font-size: 11px;

    margin-top: 9px;
}


/* =========================================================
   FILTER
========================================================= */

.filter-card {
    background:
        rgba(5,47,37,.72);

    border: 1px solid #124d3d;

    border-radius: 14px;

    padding: 10px;

    display: flex;

    margin-bottom: 18px;
}

.filter-form {
    display: flex;

    width: 100%;

    gap: 9px;

    align-items: center;

    flex-wrap: wrap;
}

.filter-group {
    flex: 1;

    min-width: 180px;

    display: flex;

    flex-direction: column;

    gap: 5px;
}

.filter-group label {
    color: #61a698;

    font-size: 8px;

    font-weight: 700;

    letter-spacing: 1px;
}

.filter-input {
    width: 100%;

    height: 39px;

    border: 1px solid #195743;

    border-radius: 10px;

    background: #03271f;

    color: #dffff5;

    padding: 0 13px;

    outline: none;

    font-size: 10px;
}

.filter-input:focus {
    border-color: #20a981;
}

.btn-filter {
    height: 39px;

    border: 1px solid #17604c;

    border-radius: 10px;

    background: #0b4939;

    color: #83c8b8;

    cursor: pointer;

    padding: 0 15px;

    font-size: 10px;

    font-weight: 600;
}

.btn-filter:hover {
    background: #0e5945;

    color: #ffffff;
}

.btn-reset {
    height: 39px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 0 15px;

    border: 1px solid #17604c;

    border-radius: 10px;

    background: transparent;

    color: #83c8b8;

    font-size: 10px;

    font-weight: 600;
}

.btn-reset:hover {
    background: #0b4939;

    color: #ffffff;
}

.btn-print {
    height: 39px;

    border: 1px solid #17604c;

    border-radius: 10px;

    background:
        linear-gradient(
            135deg,
            #16c892,
            #0b9f74
        );

    color: #ffffff;

    cursor: pointer;

    padding: 0 15px;

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   STATS
========================================================= */

.stats {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 14px;

    margin-bottom: 20px;
}

.stat-card {
    min-height: 138px;

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            rgba(8,52,42,.90),
            rgba(5,42,34,.88)
        );

    border: 1px solid #155341;

    border-radius: 16px;

    padding: 24px 21px;
}

.stat-card::after {
    content: "";

    position: absolute;

    right: -15px;
    bottom: -28px;

    width: 85px;
    height: 85px;

    border-radius: 50%;

    background:
        rgba(16,121,91,.12);
}

.stat-label {
    color: #61a698;

    font-size: 8px;

    font-weight: 700;

    letter-spacing: 1.5px;
}

.stat-number {
    font-size: 23px;

    font-weight: 800;

    margin-top: 14px;
}

.stat-info {
    color: #4f8d80;

    font-size: 8px;

    margin-top: 8px;
}


/* =========================================================
   TABLE
========================================================= */

.table-card {
    background:
        rgba(4,45,35,.78);

    border: 1px solid #15513f;

    border-radius: 17px;

    overflow: hidden;
}

.table-head {
    min-height: 55px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 21px;

    border-bottom: 1px solid #12483a;
}

.table-head h3 {
    font-size: 12px;
}

.table-head span {
    color: #57998b;

    font-size: 9px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 1000px;
}

thead {
    background:
        rgba(12,88,68,.43);
}

th {
    text-align: left;

    padding: 12px 17px;

    color: #65a999;

    font-size: 8px;

    font-weight: 700;

    letter-spacing: .8px;
}

td {
    padding: 15px 17px;

    border-top: 1px solid #103f33;

    color: #74a99e;

    font-size: 9px;
}

tbody tr:hover {
    background:
        rgba(20,110,85,.06);
}

.number {
    color: #49887a;
}

.id-badge {
    display: inline-block;

    padding: 3px 7px;

    margin-bottom: 6px;

    background:
        rgba(16,124,92,.28);

    color: #52d4a7;

    border-radius: 5px;

    font-size: 7px;

    font-weight: 700;
}

.nasabah-name {
    color: #ffffff;

    font-size: 10px;

    font-weight: 700;
}

.jenis-sampah {
    color: #9ad4c6;

    font-size: 9px;
}

.berat {
    color: #8fd1c1;

    font-weight: 600;
}

.harga {
    color: #8bc8b9;
}

.total {
    color: #52d4a7;

    font-weight: 700;
}

.tanggal {
    color: #74aaa0;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {
    text-align: center;

    padding: 45px 20px;

    color: #5c9588;

    font-size: 11px;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {
    text-align: center;

    color: #397467;

    font-size: 8px;

    padding: 25px 0 3px;
}


/* =========================================================
   PRINT
========================================================= */

@media print {

    .sidebar,
    .topbar,
    .filter-card {
        display: none !important;
    }

    .main {
        margin-left: 0;

        background: #ffffff;

        color: #111111;
    }

    .content {
        padding: 20px;
    }

    .stats {
        grid-template-columns:
            repeat(4, 1fr);
    }

    .stat-card,
    .table-card {
        background: #ffffff;

        border: 1px solid #cccccc;
    }

    .stat-label,
    .stat-info,
    .table-head span {
        color: #555555;
    }

    .stat-number,
    .table-head h3,
    .nasabah-name {
        color: #111111;
    }

    thead {
        background: #eeeeee;
    }

    th {
        color: #222222;
    }

    td {
        color: #333333;

        border-color: #dddddd;
    }

    .id-badge {
        background: #eeeeee;

        color: #222222;
    }

    .jenis-sampah,
    .berat,
    .harga,
    .tanggal,
    .total {
        color: #222222;
    }
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1200px) {

    .stats {
        grid-template-columns:
            repeat(2,1fr);
    }
}

@media (max-width: 1050px) {

    .sidebar {
        width: 225px;
    }

    .main {
        margin-left: 225px;
    }

    .content {
        padding: 28px 25px;
    }
}

@media (max-width: 850px) {

    .sidebar {
        position: relative;

        width: 100%;
        height: auto;

        border-right: 0;

        border-bottom: 1px solid #16483b;
    }

    .main {
        margin-left: 0;
    }

    .menu {
        display: grid;

        grid-template-columns:
            repeat(2,1fr);
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .topbar {
        padding: 0 20px;
    }

    .filter-form {
        flex-direction: column;

        align-items: stretch;
    }

    .filter-group {
        width: 100%;
    }

    .btn-filter,
    .btn-reset,
    .btn-print {
        width: 100%;
    }
}

@media (max-width: 600px) {

    .menu {
        grid-template-columns: 1fr;
    }

    .page-head {
        flex-direction: column;

        align-items: flex-start;

        gap: 18px;
    }

    .content {
        padding: 22px 15px;
    }

    .topbar {
        height: auto;

        padding: 17px 15px;

        gap: 15px;

        flex-direction: column;

        align-items: flex-start;
    }
}

</style>

<link rel="stylesheet" href="assets/admin-theme.css">
</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php include __DIR__ . '/admin_sidebar.php'; ?>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- TOPBAR -->

    <?php $tb_judul = 'Laporan Bank Sampah'; $tb_sub = 'Rekapitulasi transaksi Bank Sampah'; include __DIR__ . '/admin_topbar.php'; ?>


    <!-- CONTENT -->

    <section class="content">


        <!-- PAGE HEADER -->

        <div class="page-head">

            <div>

                <div class="breadcrumb">
                    BANK SAMPAH / LAPORAN
                </div>

                <h1>
                    Laporan
                </h1>

                <p class="page-description">
                    Lihat dan cetak rekap transaksi yang telah disetujui.
                </p>

            </div>

        </div>


        <!-- =====================================================
             FILTER
        ====================================================== -->

        <div class="filter-card">

            <form
                method="GET"
                action="laporan.php"
                class="filter-form"
            >

                <div class="filter-group">

                    <label>
                        TANGGAL MULAI
                    </label>

                    <input
                        type="date"
                        name="tanggal_mulai"
                        class="filter-input"
                        value="<?= e($tanggal_mulai) ?>"
                    >

                </div>


                <div class="filter-group">

                    <label>
                        TANGGAL SELESAI
                    </label>

                    <input
                        type="date"
                        name="tanggal_selesai"
                        class="filter-input"
                        value="<?= e($tanggal_selesai) ?>"
                    >

                </div>


                <button
                    type="submit"
                    class="btn-filter"
                >
                    🔎 Filter
                </button>


                <a
                    href="laporan.php"
                    class="btn-reset"
                >
                    Reset
                </a>


                <button
                    type="button"
                    class="btn-print"
                    onclick="window.print()"
                >
                    🖨 Cetak
                </button>

            </form>

        </div>


        <!-- =====================================================
             STATS
        ====================================================== -->

        <div class="stats">


            <div class="stat-card">

                <div class="stat-label">
                    TOTAL TRANSAKSI
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $total_transaksi,
                        0,
                        ',',
                        '.'
                    ) ?>
                </div>

                <div class="stat-info">
                    Transaksi disetujui
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    TOTAL BERAT
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $total_berat,
                        2,
                        ',',
                        '.'
                    ) ?>
                    Kg
                </div>

                <div class="stat-info">
                    Sampah terkumpul
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    TOTAL NILAI
                </div>

                <div class="stat-number">
                    Rp <?= number_format(
                        $total_nilai,
                        0,
                        ',',
                        '.'
                    ) ?>
                </div>

                <div class="stat-info">
                    Nilai transaksi disetujui
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    NASABAH
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $total_nasabah,
                        0,
                        ',',
                        '.'
                    ) ?>
                </div>

                <div class="stat-info">
                    Nasabah melakukan setoran
                </div>

            </div>


        </div>


        <!-- =====================================================
             TABLE
        ====================================================== -->

        <div class="table-card">


            <div class="table-head">

                <h3>
                    Data Laporan Transaksi
                </h3>

                <span>
                    <?= number_format(
                        $total_transaksi,
                        0,
                        ',',
                        '.'
                    ) ?>
                    transaksi disetujui
                </span>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                NO
                            </th>

                            <th>
                                TANGGAL
                            </th>

                            <th>
                                NASABAH
                            </th>

                            <th>
                                JENIS SAMPAH
                            </th>

                            <th>
                                BERAT
                            </th>

                            <th>
                                HARGA
                            </th>

                            <th>
                                TOTAL
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        $result_laporan &&
                        mysqli_num_rows(
                            $result_laporan
                        ) > 0
                    ): ?>


                        <?php $no = 1; ?>


                        <?php while (
                            $row =
                            mysqli_fetch_assoc(
                                $result_laporan
                            )
                        ): ?>


                            <tr>


                                <td class="number">

                                    <?= $no++ ?>

                                </td>


                                <td class="tanggal">

                                    <?= date(
                                        'd/m/Y',
                                        strtotime(
                                            $row['tanggal']
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <span class="id-badge">
                                        ID #<?= (int)$row['id_nasabah'] ?>
                                    </span>

                                    <div class="nasabah-name">
                                        <?= e(
                                            $row['nama_nasabah']
                                        ) ?>
                                    </div>

                                </td>


                                <td>

                                    <div class="jenis-sampah">

                                        <?= e(
                                            $row['nama_sampah']
                                        ) ?>

                                    </div>

                                    <div
                                        style="
                                            color:#4f8d80;
                                            font-size:8px;
                                            margin-top:4px;
                                        "
                                    >
                                        <?= e(
                                            $row['satuan']
                                        ) ?>
                                    </div>

                                </td>


                                <td class="berat">

                                    <?= number_format(
                                        $row['berat'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                    <?= e(
                                        $row['satuan']
                                    ) ?>

                                </td>


                                <td class="harga">

                                    Rp <?= number_format(
                                        $row['harga'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                </td>


                                <td class="total">

                                    Rp <?= number_format(
                                        $row['total'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td colspan="7">

                                <div class="empty">

                                    <?php if (
                                        $tanggal_mulai !== '' ||
                                        $tanggal_selesai !== ''
                                    ): ?>

                                        Tidak ada transaksi
                                        disetujui pada
                                        periode yang dipilih.

                                    <?php else: ?>

                                        Belum ada data transaksi
                                        yang disetujui.

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>


        <div class="footer">
            © 2026 Bank Sampah - Bank Sampah Digital
        </div>


    </section>

</main>


</body>
</html>