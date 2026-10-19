<?php
session_start();

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['admin'], true)
) {
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
   PROSES VERIFIKASI
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';
    $id_sampah = (int)($_POST['id_sampah'] ?? 0);

    if (
        $id_sampah > 0 &&
        in_array($aksi, ['setujui', 'tolak'], true)
    ) {

        $status = $aksi === 'setujui'
            ? 'disetujui'
            : 'ditolak';

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE sampah
             SET status = ?
             WHERE id_sampah = ?
             AND status = 'pending'"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "si",
            $status,
            $id_sampah
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);
    }

    header("Location: verifikasi_sampah.php");
    exit;
}


/* =========================================================
   FILTER
========================================================= */

$status_filter = $_GET['status'] ?? 'pending';
$keyword = trim($_GET['q'] ?? '');

if (
    !in_array(
        $status_filter,
        ['pending', 'disetujui', 'ditolak', 'semua'],
        true
    )
) {
    $status_filter = 'pending';
}


/* =========================================================
   STATISTIK
========================================================= */

function jumlah_status($koneksi, $status)
{
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT COUNT(*) AS total
         FROM sampah
         WHERE status = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $status
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $row = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0);
}

$pending = jumlah_status(
    $koneksi,
    'pending'
);

$disetujui = jumlah_status(
    $koneksi,
    'disetujui'
);

$ditolak = jumlah_status(
    $koneksi,
    'ditolak'
);

$q_total = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM sampah"
);

$total_sampah = (int)(
    mysqli_fetch_assoc($q_total)['total'] ?? 0
);


/* =========================================================
   DATA SAMPAH
========================================================= */

$where = [];
$params = [];
$types = '';

if ($status_filter !== 'semua') {

    $where[] = "status = ?";

    $params[] = $status_filter;

    $types .= 's';
}

if ($keyword !== '') {

    $like = '%' . $keyword . '%';

    $where[] = "
        (
            nama_sampah LIKE ?
            OR satuan LIKE ?
        )
    ";

    $params[] = $like;
    $params[] = $like;

    $types .= 'ss';
}

$sql = "
    SELECT
        id_sampah,
        nama_sampah,
        harga,
        satuan,
        status
    FROM sampah
";

if (!empty($where)) {

    $sql .=
        " WHERE "
        . implode(" AND ", $where);
}

$sql .= "
    ORDER BY id_sampah DESC
";

$stmt = mysqli_prepare(
    $koneksi,
    $sql
);

if (!empty($params)) {

    mysqli_stmt_bind_param(
        $stmt,
        $types,
        ...$params
    );
}

mysqli_stmt_execute($stmt);

$result_sampah =
    mysqli_stmt_get_result($stmt);

?>
<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Verifikasi Sampah | Bank Sampah</title>

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
select {
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


/* =========================================================
   ACCOUNT
========================================================= */

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
   SEARCH
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

    min-width: 950px;
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

.no-hp {
    color: #9ad4c6;
}

.tanggal {
    color: #74aaa0;
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

.btn-edit {
    background:
        rgba(16,112,88,.20);

    border: 1px solid #17644f;

    color: #61d6ad;
}

.btn-edit:hover {
    background:
        rgba(20,137,105,.32);
}

.btn-delete {
    background:
        rgba(128,25,55,.25);

    border: 1px solid #703044;

    color: #e86b8a;
}

.btn-delete:hover {
    background:
        rgba(158,30,65,.38);
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

    <?php $tb_judul = 'Verifikasi Sampah'; $tb_sub = 'Kelola verifikasi jenis sampah Bank Sampah'; include __DIR__ . '/admin_topbar.php'; ?>


    <!-- CONTENT -->

    <section class="content">


        <!-- PAGE HEADER -->

        <div class="page-head">

            <div>

                <div class="breadcrumb">
                    BANK SAMPAH / VERIFIKASI SAMPAH
                </div>

                <h1>
                    Verifikasi Sampah
                </h1>

                <p class="page-description">
                    Periksa dan kelola status jenis sampah yang diajukan ke sistem.
                </p>

            </div>

        </div>


        <!-- =====================================================
             STATS
        ===================================================== -->

        <div class="stats">


            <div class="stat-card">

                <div class="stat-label">
                    MENUNGGU VERIFIKASI
                </div>

                <div class="stat-number">
                    <?= $pending ?>
                </div>

                <div class="stat-info">
                    Data menunggu persetujuan
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    DISETUJUI
                </div>

                <div class="stat-number">
                    <?= $disetujui ?>
                </div>

                <div class="stat-info">
                    Jenis sampah disetujui
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    DITOLAK
                </div>

                <div class="stat-number">
                    <?= $ditolak ?>
                </div>

                <div class="stat-info">
                    Pengajuan ditolak
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    TOTAL SAMPAH
                </div>

                <div class="stat-number">
                    <?= $total_sampah ?>
                </div>

                <div class="stat-info">
                    Seluruh data jenis sampah
                </div>

            </div>


        </div>


        <!-- =====================================================
             SEARCH
        ===================================================== -->

        <div class="search-box">

            <form
                method="GET"
                action="verifikasi_sampah.php"
            >

                <input
                    type="text"
                    name="q"
                    class="search-input"
                    placeholder="Cari nama sampah atau satuan..."
                    value="<?= e($keyword) ?>"
                >


                <select
                    name="status"
                    class="search-input"
                    style="max-width:180px;"
                >

                    <option
                        value="pending"
                        <?= $status_filter === 'pending'
                            ? 'selected'
                            : '' ?>
                    >
                        Menunggu
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
                        Semua
                    </option>

                </select>


                <button
                    type="submit"
                    class="btn-search"
                >
                    🔎 Cari
                </button>

            </form>

        </div>


        <!-- =====================================================
             TABLE
        ===================================================== -->

        <div class="table-card">


            <div class="table-head">

                <h3>
                    Data Verifikasi Sampah
                </h3>

                <span>
                    <?= mysqli_num_rows($result_sampah) ?>
                    data ditemukan
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
                                JENIS SAMPAH
                            </th>

                            <th>
                                HARGA
                            </th>

                            <th>
                                SATUAN
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
                        mysqli_num_rows($result_sampah) > 0
                    ): ?>


                        <?php $no = 1; ?>


                        <?php while (
                            $row = mysqli_fetch_assoc(
                                $result_sampah
                            )
                        ): ?>


                            <tr>


                                <td class="number">

                                    <?= $no++ ?>

                                </td>


                                <td>

                                    <span class="id-badge">

                                        ID #<?= (int)$row['id_sampah'] ?>

                                    </span>


                                    <div class="nasabah-name">

                                        <?= e(
                                            $row['nama_sampah']
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <div class="no-hp">

                                        Rp
                                        <?= number_format(
                                            $row['harga'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <div class="tanggal">

                                        <?= e(
                                            $row['satuan']
                                        ) ?>

                                    </div>

                                </td>


                                <td>


                                    <?php if (
                                        $row['status']
                                        === 'pending'
                                    ): ?>

                                        <span class="id-badge">

                                            MENUNGGU

                                        </span>


                                    <?php elseif (
                                        $row['status']
                                        === 'disetujui'
                                    ): ?>

                                        <span
                                            class="id-badge"
                                            style="
                                                color:#78e2bd;
                                                background:rgba(16,124,92,.20);
                                            "
                                        >

                                            DISETUJUI

                                        </span>


                                    <?php else: ?>

                                        <span
                                            class="id-badge"
                                            style="
                                                color:#ed7896;
                                                background:rgba(128,25,55,.20);
                                            "
                                        >

                                            DITOLAK

                                        </span>

                                    <?php endif; ?>


                                </td>


                                <td>


                                    <?php if (
                                        $row['status']
                                        === 'pending'
                                    ): ?>


                                        <div class="actions">


                                            <!-- SETUJUI -->

                                            <form method="POST">

                                                <input
                                                    type="hidden"
                                                    name="id_sampah"
                                                    value="<?= (int)$row['id_sampah'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="aksi"
                                                    value="setujui"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn-action btn-edit"
                                                >

                                                    ✓ Setujui

                                                </button>

                                            </form>


                                            <!-- TOLAK -->

                                            <form method="POST">

                                                <input
                                                    type="hidden"
                                                    name="id_sampah"
                                                    value="<?= (int)$row['id_sampah'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="aksi"
                                                    value="tolak"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn-action btn-delete"
                                                >

                                                    × Tolak

                                                </button>

                                            </form>


                                        </div>


                                    <?php else: ?>


                                        <span class="tanggal">

                                            Tidak ada aksi

                                        </span>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td colspan="6">

                                <div class="empty">

                                    <?php if (
                                        $keyword !== ''
                                    ): ?>

                                        Data sampah dengan kata

                                        <strong>
                                            "<?= e($keyword) ?>"
                                        </strong>

                                        tidak ditemukan.

                                    <?php else: ?>

                                        Belum ada data sampah.

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