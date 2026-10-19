<?php

session_start();

/* =========================================================
   CEK LOGIN
========================================================= */

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['admin'], true)
) {
    header("Location: login.php");
    exit;
}


/* =========================================================
   KONEKSI DATABASE
========================================================= */

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


/* =========================================================
   DATA USER
========================================================= */

$nama_admin =
    $_SESSION['nama_user']
    ?? $_SESSION['nama']
    ?? $_SESSION['username']
    ?? 'Administrator';

$role_user =
    $_SESSION['role']
    ?? 'admin';


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
   PROSES VERIFIKASI
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* =========================
       SETUJUI TRANSAKSI
    ========================= */

    if (isset($_POST['setujui'])) {

        $id_transaksi = (int)($_POST['id_transaksi'] ?? 0);

        if ($id_transaksi > 0) {

            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE transaksi
                 SET status = 'disetujui'
                 WHERE id_transaksi = ?
                 AND status = 'pending'"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id_transaksi
            );

            if (mysqli_stmt_execute($stmt)) {

                if (mysqli_stmt_affected_rows($stmt) > 0) {

                    $_SESSION['pesan'] = [
                        'type' => 'success',
                        'text' => 'Transaksi berhasil disetujui.'
                    ];

                } else {

                    $_SESSION['pesan'] = [
                        'type' => 'error',
                        'text' => 'Transaksi tidak dapat disetujui atau sudah diproses.'
                    ];
                }

            } else {

                $_SESSION['pesan'] = [
                    'type' => 'error',
                    'text' => 'Gagal menyetujui transaksi.'
                ];
            }

            mysqli_stmt_close($stmt);
        }

        header("Location: verifikasi_transaksi.php");
        exit;
    }


    /* =========================
       TOLAK TRANSAKSI
    ========================= */

    if (isset($_POST['tolak'])) {

        $id_transaksi = (int)($_POST['id_transaksi'] ?? 0);

        if ($id_transaksi > 0) {

            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE transaksi
                 SET status = 'ditolak'
                 WHERE id_transaksi = ?
                 AND status = 'pending'"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id_transaksi
            );

            if (mysqli_stmt_execute($stmt)) {

                if (mysqli_stmt_affected_rows($stmt) > 0) {

                    $_SESSION['pesan'] = [
                        'type' => 'success',
                        'text' => 'Transaksi berhasil ditolak.'
                    ];

                } else {

                    $_SESSION['pesan'] = [
                        'type' => 'error',
                        'text' => 'Transaksi tidak dapat ditolak atau sudah diproses.'
                    ];
                }

            } else {

                $_SESSION['pesan'] = [
                    'type' => 'error',
                    'text' => 'Gagal menolak transaksi.'
                ];
            }

            mysqli_stmt_close($stmt);
        }

        header("Location: verifikasi_transaksi.php");
        exit;
    }
}


/* =========================================================
   PESAN
========================================================= */

$pesan = $_SESSION['pesan'] ?? null;
unset($_SESSION['pesan']);


/* =========================================================
   FILTER
========================================================= */

$status_filter = $_GET['status'] ?? 'pending';

$allowed_status = [
    'pending',
    'disetujui',
    'ditolak',
    'semua'
];

if (!in_array($status_filter, $allowed_status, true)) {
    $status_filter = 'pending';
}

$keyword = trim($_GET['q'] ?? '');


/* =========================================================
   STATISTIK
========================================================= */

/* Pending */

$q_pending = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM transaksi
     WHERE status = 'pending'"
);

$total_pending = (int)(
    mysqli_fetch_assoc($q_pending)['total'] ?? 0
);


/* Disetujui */

$q_disetujui = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM transaksi
     WHERE status = 'disetujui'"
);

$total_disetujui = (int)(
    mysqli_fetch_assoc($q_disetujui)['total'] ?? 0
);


/* Ditolak */

$q_ditolak = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM transaksi
     WHERE status = 'ditolak'"
);

$total_ditolak = (int)(
    mysqli_fetch_assoc($q_ditolak)['total'] ?? 0
);


/* Semua */

$q_semua = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM transaksi"
);

$total_transaksi = (int)(
    mysqli_fetch_assoc($q_semua)['total'] ?? 0
);


/* =========================================================
   QUERY DATA TRANSAKSI
========================================================= */

$where = [];
$params = [];
$types = "";


/* FILTER STATUS */

if ($status_filter !== 'semua') {

    $where[] = "t.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}


/* SEARCH */

if ($keyword !== '') {

    $like = '%' . $keyword . '%';

    $where[] = "(
        n.nama LIKE ?
        OR s.nama_sampah LIKE ?
    )";

    $params[] = $like;
    $params[] = $like;

    $types .= "ss";
}


/* WHERE */

$where_sql = '';

if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}


/* QUERY */

$sql = "
    SELECT
        t.id_transaksi,
        t.id_nasabah,
        t.id_sampah,
        t.tanggal,
        t.berat,
        t.harga,
        t.total,
        t.status,

        n.nama AS nama_nasabah,

        s.nama_sampah,
        s.satuan

    FROM transaksi t

    INNER JOIN nasabah n
        ON t.id_nasabah = n.id_nasabah

    INNER JOIN sampah s
        ON t.id_sampah = s.id_sampah

    $where_sql

    ORDER BY t.id_transaksi DESC
";


$stmt_data = mysqli_prepare(
    $koneksi,
    $sql
);

if (!empty($params)) {

    mysqli_stmt_bind_param(
        $stmt_data,
        $types,
        ...$params
    );
}

mysqli_stmt_execute($stmt_data);

$result_transaksi =
    mysqli_stmt_get_result($stmt_data);


/* =========================================================
   LABEL FILTER
========================================================= */

$filter_label = [
    'pending' => 'Transaksi Pending',
    'disetujui' => 'Transaksi Disetujui',
    'ditolak' => 'Transaksi Ditolak',
    'semua' => 'Semua Transaksi'
];

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Verifikasi Transaksi | Bank Sampah</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
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
   SEARCH / FILTER
========================================================= */

.search-box {
    background:
        rgba(5,47,37,.72);

    border: 1px solid #124d3d;

    border-radius: 14px;

    padding: 9px;

    display: flex;

    margin-bottom: 18px;
}

.search-box form {
    display: flex;

    width: 100%;

    gap: 9px;
}

.search-input {
    flex: 1;

    height: 39px;

    border: 1px solid #195743;

    border-radius: 10px;

    background: #03271f;

    color: #dffff5;

    padding: 0 13px;

    outline: none;

    font-size: 10px;
}

.search-input::placeholder {
    color: #477c70;
}

.search-input:focus {
    border-color: #20a981;
}

.filter-select {
    width: 145px;

    height: 39px;

    border: 1px solid #195743;

    border-radius: 10px;

    background: #03271f;

    color: #83c8b8;

    padding: 0 10px;

    outline: none;

    font-size: 10px;

    cursor: pointer;
}

.filter-select:focus {
    border-color: #20a981;
}

.btn-search {
    width: 72px;

    border: 1px solid #17604c;

    border-radius: 10px;

    background: #0b4939;

    color: #83c8b8;

    cursor: pointer;

    font-size: 10px;

    font-weight: 600;
}

.btn-search:hover {
    background: #0e5945;

    color: #ffffff;
}

.btn-reset {
    width: 72px;

    display: flex;

    align-items: center;

    justify-content: center;

    border: 1px solid #17604c;

    border-radius: 10px;

    background: transparent;

    color: #669e90;

    cursor: pointer;

    font-size: 10px;
}

.btn-reset:hover {
    background: #0b4939;

    color: #ffffff;
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

    min-width: 1100px;
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

    font-size: 10px;

    font-weight: 600;
}

.sub-info {
    color: #518c7e;

    font-size: 8px;

    margin-top: 4px;
}

.berat {
    color: #d5f7ed;

    font-weight: 600;
}

.harga {
    color: #75b9a9;
}

.total {
    color: #5fe2b0;

    font-weight: 800;
}

.tanggal {
    color: #74aaa0;
}


/* =========================================================
   STATUS
========================================================= */

.status {
    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 6px 9px;

    border-radius: 20px;

    font-size: 8px;

    font-weight: 700;

    white-space: nowrap;
}

.status::before {
    content: "";

    width: 6px;
    height: 6px;

    border-radius: 50%;
}

.status.pending {
    color: #f0c96d;

    background:
        rgba(190,140,38,.12);

    border: 1px solid rgba(190,140,38,.18);
}

.status.pending::before {
    background: #f0bd59;

    box-shadow:
        0 0 8px rgba(240,189,89,.5);
}

.status.disetujui {
    color: #61dfaa;

    background:
        rgba(15,132,96,.15);

    border: 1px solid #17684f;
}

.status.disetujui::before {
    background: #32d485;

    box-shadow:
        0 0 8px rgba(50,212,133,.5);
}

.status.ditolak {
    color: #ef8097;

    background:
        rgba(144,27,59,.14);

    border: 1px solid #713246;
}

.status.ditolak::before {
    background: #e86b8a;

    box-shadow:
        0 0 8px rgba(232,107,138,.4);
}


/* =========================================================
   ACTION
========================================================= */

.actions {
    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}

.btn-action {
    border-radius: 7px;

    padding: 7px 10px;

    font-size: 8px;

    font-weight: 700;

    display: inline-flex;

    align-items: center;

    gap: 4px;

    transition: .2s;

    cursor: pointer;
}

.btn-approve {
    background:
        rgba(16,112,88,.20);

    border: 1px solid #17644f;

    color: #61d6ad;
}

.btn-approve:hover {
    background:
        rgba(20,137,105,.32);
}

.btn-reject {
    background:
        rgba(128,25,55,.25);

    border: 1px solid #703044;

    color: #e86b8a;
}

.btn-reject:hover {
    background:
        rgba(158,30,65,.38);
}


/* =========================================================
   ALERT
========================================================= */

.alert {
    margin-bottom: 18px;

    padding: 12px 15px;

    border-radius: 10px;

    font-size: 10px;

    font-weight: 600;
}

.alert.success {
    color: #8ae7c5;

    background:
        rgba(15,132,96,.18);

    border: 1px solid #17684f;
}

.alert.error {
    color: #ff9caf;

    background:
        rgba(144,27,59,.16);

    border: 1px solid #713246;
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

    .search-box form {
        flex-wrap: wrap;
    }

    .search-input {
        flex-basis: 100%;
    }

    .filter-select {
        flex: 1;
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

    .filter-select,
    .btn-search,
    .btn-reset {
        width: 100%;
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

    <?php $tb_judul = 'Verifikasi Transaksi'; $tb_sub = 'Periksa dan kelola status transaksi Bank Sampah'; include __DIR__ . '/admin_topbar.php'; ?>



    <!-- CONTENT -->

    <section class="content">


        <!-- ALERT -->

        <?php if ($pesan): ?>

            <div class="alert <?= $pesan['type'] === 'success'
                ? 'success'
                : 'error' ?>">

                <?= e($pesan['text']) ?>

            </div>

        <?php endif; ?>



        <!-- PAGE HEADER -->

        <div class="page-head">

            <div>

                <div class="breadcrumb">
                    BANK SAMPAH / VERIFIKASI TRANSAKSI
                </div>

                <h1>
                    Verifikasi Transaksi
                </h1>

                <p class="page-description">
                    Periksa transaksi nasabah dan tentukan apakah transaksi disetujui atau ditolak.
                </p>

            </div>

        </div>



        <!-- =====================================================
             STATS
        ===================================================== -->

        <div class="stats">


            <!-- PENDING -->

            <div class="stat-card">

                <div class="stat-label">
                    MENUNGGU VERIFIKASI
                </div>

                <div class="stat-number">
                    <?= $total_pending ?>
                </div>

                <div class="stat-info">
                    Transaksi membutuhkan pemeriksaan
                </div>

            </div>


            <!-- DISETUJUI -->

            <div class="stat-card">

                <div class="stat-label">
                    DISETUJUI
                </div>

                <div class="stat-number">
                    <?= $total_disetujui ?>
                </div>

                <div class="stat-info">
                    Transaksi telah disetujui
                </div>

            </div>


            <!-- DITOLAK -->

            <div class="stat-card">

                <div class="stat-label">
                    DITOLAK
                </div>

                <div class="stat-number">
                    <?= $total_ditolak ?>
                </div>

                <div class="stat-info">
                    Transaksi ditolak
                </div>

            </div>


            <!-- TOTAL -->

            <div class="stat-card">

                <div class="stat-label">
                    TOTAL TRANSAKSI
                </div>

                <div class="stat-number">
                    <?= $total_transaksi ?>
                </div>

                <div class="stat-info">
                    Seluruh transaksi tercatat
                </div>

            </div>


        </div>



        <!-- =====================================================
             SEARCH + FILTER
        ===================================================== -->

        <div class="search-box">

            <form
                method="GET"
                action="verifikasi_transaksi.php"
            >

                <input
                    type="text"
                    name="q"
                    class="search-input"
                    placeholder="Cari nama nasabah atau jenis sampah..."
                    value="<?= e($keyword) ?>"
                >


                <select
                    name="status"
                    class="filter-select"
                >

                    <option
                        value="pending"
                        <?= $status_filter === 'pending'
                            ? 'selected'
                            : '' ?>
                    >
                        Menunggu Verifikasi
                    </option>

                    <option
                        value="disetujui"
                        <?= $status_filter === 'disetujui'
                            ? 'selected'
                            : '' ?>
                    >
                        Disetujui
                    </option>

                    <option
                        value="ditolak"
                        <?= $status_filter === 'ditolak'
                            ? 'selected'
                            : '' ?>
                    >
                        Ditolak
                    </option>

                    <option
                        value="semua"
                        <?= $status_filter === 'semua'
                            ? 'selected'
                            : '' ?>
                    >
                        Semua Status
                    </option>

                </select>


                <button
                    type="submit"
                    class="btn-search"
                >
                    🔎 Cari
                </button>


                <a
                    href="verifikasi_transaksi.php"
                    class="btn-reset"
                >
                    Reset
                </a>

            </form>

        </div>



        <!-- =====================================================
             TABLE
        ===================================================== -->

        <div class="table-card">


            <div class="table-head">

                <h3>
                    <?= e($filter_label[$status_filter]) ?>
                </h3>

                <span>

                    <?php
                    $jumlah_data = $result_transaksi
                        ? mysqli_num_rows($result_transaksi)
                        : 0;
                    ?>

                    <?= $jumlah_data ?> transaksi

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
                                TRANSAKSI
                            </th>

                            <th>
                                NASABAH
                            </th>

                            <th>
                                JENIS SAMPAH
                            </th>

                            <th>
                                TANGGAL
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

                            <th>
                                STATUS
                            </th>

                            <th>
                                AKSI
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        $result_transaksi &&
                        mysqli_num_rows($result_transaksi) > 0
                    ): ?>


                        <?php $no = 1; ?>


                        <?php while (
                            $row =
                            mysqli_fetch_assoc(
                                $result_transaksi
                            )
                        ): ?>


                            <tr>


                                <!-- NO -->

                                <td class="number">
                                    <?= $no++ ?>
                                </td>


                                <!-- ID TRANSAKSI -->

                                <td>

                                    <span class="id-badge">
                                        ID #<?= (int)$row['id_transaksi'] ?>
                                    </span>

                                </td>


                                <!-- NASABAH -->

                                <td>

                                    <div class="nasabah-name">
                                        <?= e($row['nama_nasabah']) ?>
                                    </div>

                                </td>


                                <!-- JENIS SAMPAH -->

                                <td>

                                    <div class="jenis-sampah">
                                        <?= e($row['nama_sampah']) ?>
                                    </div>

                                    <div class="sub-info">
                                        <?= e($row['satuan']) ?>
                                    </div>

                                </td>


                                <!-- TANGGAL -->

                                <td class="tanggal">

                                    <?= date(
                                        'd/m/Y',
                                        strtotime(
                                            $row['tanggal']
                                        )
                                    ) ?>

                                </td>


                                <!-- BERAT -->

                                <td>

                                    <div class="berat">

                                        <?= number_format(
                                            (float)$row['berat'],
                                            2,
                                            ',',
                                            '.'
                                        ) ?>

                                        <?= e($row['satuan']) ?>

                                    </div>

                                </td>


                                <!-- HARGA -->

                                <td>

                                    <div class="harga">

                                        Rp
                                        <?= number_format(
                                            (int)$row['harga'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                    </div>

                                    <div class="sub-info">
                                        per <?= e($row['satuan']) ?>
                                    </div>

                                </td>


                                <!-- TOTAL -->

                                <td>

                                    <div class="total">

                                        Rp
                                        <?= number_format(
                                            (int)$row['total'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                    </div>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="status <?= e($row['status']) ?>"
                                    >

                                        <?php if ($row['status'] === 'pending'): ?>

                                            Menunggu

                                        <?php elseif ($row['status'] === 'disetujui'): ?>

                                            Disetujui

                                        <?php else: ?>

                                            Ditolak

                                        <?php endif; ?>

                                    </span>

                                </td>


                                <!-- AKSI -->

                                <td>

                                    <?php if (
                                        $row['status'] === 'pending'
                                    ): ?>


                                        <div class="actions">


                                            <!-- SETUJUI -->

                                            <form
                                                method="POST"
                                                style="display:inline;"
                                                onsubmit="return confirm('Yakin ingin menyetujui transaksi ini?')"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="id_transaksi"
                                                    value="<?= (int)$row['id_transaksi'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="setujui"
                                                    class="btn-action btn-approve"
                                                >
                                                    ✓ Setujui
                                                </button>

                                            </form>


                                            <!-- TOLAK -->

                                            <form
                                                method="POST"
                                                style="display:inline;"
                                                onsubmit="return confirm('Yakin ingin menolak transaksi ini?')"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="id_transaksi"
                                                    value="<?= (int)$row['id_transaksi'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="tolak"
                                                    class="btn-action btn-reject"
                                                >
                                                    × Tolak
                                                </button>

                                            </form>


                                        </div>


                                    <?php else: ?>

                                        <span
                                            style="
                                                color:#467e72;
                                                font-size:8px;
                                            "
                                        >
                                            Sudah diproses
                                        </span>

                                    <?php endif; ?>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td colspan="10">

                                <div class="empty">

                                    <?php if ($keyword !== ''): ?>

                                        Tidak ada transaksi dengan kata
                                        <strong>
                                            "<?= e($keyword) ?>"
                                        </strong>.

                                    <?php elseif ($status_filter === 'pending'): ?>

                                        Tidak ada transaksi yang menunggu verifikasi.

                                    <?php elseif ($status_filter === 'disetujui'): ?>

                                        Belum ada transaksi yang disetujui.

                                    <?php elseif ($status_filter === 'ditolak'): ?>

                                        Belum ada transaksi yang ditolak.

                                    <?php else: ?>

                                        Belum ada data transaksi.

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