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
   TAMBAH NASABAH
========================================================= */

if (isset($_POST['tambah_nasabah'])) {

    $nama = trim($_POST['nama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $tanggal_daftar = $_POST['tanggal_daftar'] ?? date('Y-m-d');

    if (
        $nama === '' ||
        $alamat === '' ||
        $no_hp === '' ||
        $tanggal_daftar === ''
    ) {
        $_SESSION['pesan'] = [
            'type' => 'error',
            'text' => 'Semua data nasabah wajib diisi.'
        ];

        header("Location: nasabah.php");
        exit;
    }

    $stmt = mysqli_prepare(
        $koneksi,
        "INSERT INTO nasabah
        (nama, alamat, no_hp, tanggal_daftar)
        VALUES (?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $nama,
        $alamat,
        $no_hp,
        $tanggal_daftar
    );

    if (mysqli_stmt_execute($stmt)) {

        $_SESSION['pesan'] = [
            'type' => 'success',
            'text' => 'Nasabah berhasil ditambahkan.'
        ];

    } else {

        $_SESSION['pesan'] = [
            'type' => 'error',
            'text' => 'Gagal menambahkan nasabah.'
        ];
    }

    mysqli_stmt_close($stmt);

    header("Location: nasabah.php");
    exit;
}


/* =========================================================
   EDIT NASABAH
========================================================= */

if (isset($_POST['edit_nasabah'])) {

    $id_nasabah = (int)($_POST['id_nasabah'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $tanggal_daftar = $_POST['tanggal_daftar'] ?? '';

    if (
        $id_nasabah <= 0 ||
        $nama === '' ||
        $alamat === '' ||
        $no_hp === '' ||
        $tanggal_daftar === ''
    ) {
        $_SESSION['pesan'] = [
            'type' => 'error',
            'text' => 'Data nasabah belum lengkap.'
        ];

        header("Location: nasabah.php");
        exit;
    }

    $stmt = mysqli_prepare(
        $koneksi,
        "UPDATE nasabah
         SET nama = ?,
             alamat = ?,
             no_hp = ?,
             tanggal_daftar = ?
         WHERE id_nasabah = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ssssi",
        $nama,
        $alamat,
        $no_hp,
        $tanggal_daftar,
        $id_nasabah
    );

    if (mysqli_stmt_execute($stmt)) {

        $_SESSION['pesan'] = [
            'type' => 'success',
            'text' => 'Data nasabah berhasil diperbarui.'
        ];

    } else {

        $_SESSION['pesan'] = [
            'type' => 'error',
            'text' => 'Gagal memperbarui data nasabah.'
        ];
    }

    mysqli_stmt_close($stmt);

    header("Location: nasabah.php");
    exit;
}


/* =========================================================
   HAPUS NASABAH
========================================================= */

if (isset($_GET['hapus'])) {

    $id_hapus = (int)$_GET['hapus'];

    if ($id_hapus > 0) {

        /* Cek transaksi */
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT COUNT(*) AS jumlah
             FROM transaksi
             WHERE id_nasabah = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id_hapus
        );

        mysqli_stmt_execute($stmt);

        $hasil = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($hasil);

        mysqli_stmt_close($stmt);

        $jumlah_transaksi = (int)($data['jumlah'] ?? 0);


        /* Cek tarik saldo */
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT COUNT(*) AS jumlah
             FROM tarik_saldo
             WHERE id_nasabah = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id_hapus
        );

        mysqli_stmt_execute($stmt);

        $hasil = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($hasil);

        mysqli_stmt_close($stmt);

        $jumlah_tarik = (int)($data['jumlah'] ?? 0);


        if (
            $jumlah_transaksi > 0 ||
            $jumlah_tarik > 0
        ) {

            $_SESSION['pesan'] = [
                'type' => 'error',
                'text' =>
                    'Nasabah tidak dapat dihapus karena masih memiliki data transaksi atau penarikan saldo.'
            ];

        } else {

            $stmt = mysqli_prepare(
                $koneksi,
                "DELETE FROM nasabah
                 WHERE id_nasabah = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id_hapus
            );

            if (mysqli_stmt_execute($stmt)) {

                $_SESSION['pesan'] = [
                    'type' => 'success',
                    'text' => 'Nasabah berhasil dihapus.'
                ];

            } else {

                $_SESSION['pesan'] = [
                    'type' => 'error',
                    'text' => 'Gagal menghapus nasabah.'
                ];
            }

            mysqli_stmt_close($stmt);
        }
    }

    header("Location: nasabah.php");
    exit;
}


/* =========================================================
   PESAN
========================================================= */

$pesan = $_SESSION['pesan'] ?? null;
unset($_SESSION['pesan']);


/* =========================================================
   STATISTIK
========================================================= */

$q_total = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM nasabah"
);

$total_nasabah = (int)(
    mysqli_fetch_assoc($q_total)['total'] ?? 0
);


$q_transaksi = mysqli_query(
    $koneksi,
    "SELECT COUNT(DISTINCT id_nasabah) AS total
     FROM transaksi"
);

$nasabah_transaksi = (int)(
    mysqli_fetch_assoc($q_transaksi)['total'] ?? 0
);


$q_aktif = mysqli_query(
    $koneksi,
    "SELECT COUNT(DISTINCT id_nasabah) AS total
     FROM transaksi
     WHERE status = 'disetujui'"
);

$nasabah_aktif = (int)(
    mysqli_fetch_assoc($q_aktif)['total'] ?? 0
);


$q_baru = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM nasabah
     WHERE MONTH(tanggal_daftar) = MONTH(CURDATE())
     AND YEAR(tanggal_daftar) = YEAR(CURDATE())"
);

$nasabah_baru = (int)(
    mysqli_fetch_assoc($q_baru)['total'] ?? 0
);


/* =========================================================
   SEARCH
========================================================= */

$keyword = trim($_GET['q'] ?? '');

if ($keyword !== '') {

    $like = '%' . $keyword . '%';

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT
            id_nasabah,
            nama,
            alamat,
            no_hp,
            tanggal_daftar
         FROM nasabah
         WHERE nama LIKE ?
            OR alamat LIKE ?
            OR no_hp LIKE ?
         ORDER BY id_nasabah DESC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $like,
        $like,
        $like
    );

    mysqli_stmt_execute($stmt);

    $result_nasabah =
        mysqli_stmt_get_result($stmt);

} else {

    $result_nasabah = mysqli_query(
        $koneksi,
        "SELECT
            id_nasabah,
            nama,
            alamat,
            no_hp,
            tanggal_daftar
         FROM nasabah
         ORDER BY id_nasabah DESC"
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

<title>Nasabah | Bank Sampah</title>

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

.btn-primary {
    border: 0;

    cursor: pointer;

    background:
        linear-gradient(
            135deg,
            #17c993,
            #0b9d72
        );

    color: #ffffff;

    padding: 12px 18px;

    border-radius: 11px;

    font-size: 11px;

    font-weight: 700;

    box-shadow:
        0 9px 22px rgba(0,185,133,.14);

    transition: .2s;
}

.btn-primary:hover {
    transform: translateY(-1px);

    filter: brightness(1.08);
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

.alamat {
    max-width: 260px;

    color: #75a99d;

    line-height: 1.5;
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
   MODAL
========================================================= */

.modal {
    display: none;

    position: fixed;

    inset: 0;

    background:
        rgba(0,0,0,.65);

    z-index: 2000;

    align-items: center;

    justify-content: center;

    padding: 20px;
}

.modal.show {
    display: flex;
}

.modal-box {
    width: 100%;

    max-width: 500px;

    max-height: 90vh;

    overflow-y: auto;

    background:
        linear-gradient(
            145deg,
            #073127,
            #04251e
        );

    border: 1px solid #19604b;

    border-radius: 17px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.5);

    padding: 24px;
}

.modal-head {
    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 22px;
}

.modal-head h2 {
    font-size: 16px;
}

.modal-close {
    width: 30px;
    height: 30px;

    border-radius: 8px;

    border: 1px solid #1a5745;

    background: #073b2f;

    color: #7bb5a7;

    cursor: pointer;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;

    color: #71aa9d;

    font-size: 9px;

    font-weight: 600;

    margin-bottom: 7px;
}

.form-control {
    width: 100%;

    min-height: 40px;

    border: 1px solid #195743;

    border-radius: 9px;

    background: #03271f;

    color: #e9fff8;

    padding: 0 12px;

    outline: none;

    font-size: 10px;
}

textarea.form-control {
    padding: 11px 12px;

    resize: vertical;

    min-height: 80px;
}

.form-control:focus {
    border-color: #20a981;
}

.modal-actions {
    display: flex;

    justify-content: flex-end;

    gap: 8px;

    margin-top: 22px;
}

.btn-cancel {
    border: 1px solid #1a5745;

    background: transparent;

    color: #72a99c;

    border-radius: 9px;

    padding: 10px 15px;

    cursor: pointer;

    font-size: 10px;
}

.btn-save {
    border: 0;

    background:
        linear-gradient(
            135deg,
            #16c892,
            #0b9f74
        );

    color: #ffffff;

    border-radius: 9px;

    padding: 10px 17px;

    cursor: pointer;

    font-size: 10px;

    font-weight: 700;
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

    .btn-primary {
        width: 100%;
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

    <?php $tb_judul = 'Manajemen Nasabah'; $tb_sub = 'Kelola data nasabah Bank Sampah'; include __DIR__ . '/admin_topbar.php'; ?>


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
                    BANK SAMPAH / NASABAH
                </div>

                <h1>
                    Nasabah
                </h1>

                <p class="page-description">
                    Kelola data anggota dan nasabah Bank Sampah.
                </p>

            </div>


            <button
                type="button"
                class="btn-primary"
                onclick="openTambah()"
            >
                + &nbsp; Tambah Nasabah
            </button>

        </div>


        <!-- =====================================================
             STATS
        ===================================================== -->

        <div class="stats">


            <div class="stat-card">

                <div class="stat-label">
                    TOTAL NASABAH
                </div>

                <div class="stat-number">
                    <?= $total_nasabah ?>
                </div>

                <div class="stat-info">
                    Seluruh nasabah terdaftar
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    NASABAH BERTRANSAKSI
                </div>

                <div class="stat-number">
                    <?= $nasabah_transaksi ?>
                </div>

                <div class="stat-info">
                    Pernah melakukan setoran
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    NASABAH AKTIF
                </div>

                <div class="stat-number">
                    <?= $nasabah_aktif ?>
                </div>

                <div class="stat-info">
                    Memiliki transaksi disetujui
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    NASABAH BARU
                </div>

                <div class="stat-number">
                    <?= $nasabah_baru ?>
                </div>

                <div class="stat-info">
                    Terdaftar bulan ini
                </div>

            </div>


        </div>


        <!-- =====================================================
             SEARCH
        ===================================================== -->

        <div class="search-box">

            <form
                method="GET"
                action="nasabah.php"
            >

                <input
                    type="text"
                    name="q"
                    class="search-input"
                    placeholder="Cari nama, alamat, atau nomor HP..."
                    value="<?= e($keyword) ?>"
                >


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
                    Data Nasabah
                </h3>

                <span>
                    <?= $total_nasabah ?> nasabah terdaftar
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
                                NASABAH
                            </th>

                            <th>
                                ALAMAT
                            </th>

                            <th>
                                NO. HP
                            </th>

                            <th>
                                TANGGAL DAFTAR
                            </th>

                            <th>
                                AKSI
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (
                        $result_nasabah &&
                        mysqli_num_rows(
                            $result_nasabah
                        ) > 0
                    ): ?>


                        <?php $no = 1; ?>


                        <?php while (
                            $row =
                            mysqli_fetch_assoc(
                                $result_nasabah
                            )
                        ): ?>


                            <tr>


                                <td class="number">
                                    <?= $no++ ?>
                                </td>


                                <td>

                                    <span class="id-badge">
                                        ID #<?= (int)$row['id_nasabah'] ?>
                                    </span>

                                    <div class="nasabah-name">
                                        <?= e($row['nama']) ?>
                                    </div>

                                </td>


                                <td>

                                    <div class="alamat">
                                        <?= e($row['alamat']) ?>
                                    </div>

                                </td>


                                <td>

                                    <div class="no-hp">
                                        <?= e($row['no_hp']) ?>
                                    </div>

                                </td>


                                <td class="tanggal">

                                    <?= date(
                                        'd/m/Y',
                                        strtotime(
                                            $row['tanggal_daftar']
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <div class="actions">


                                        <button
                                            type="button"
                                            class="btn-action btn-edit"
                                            onclick='openEdit(
                                                <?= json_encode(
                                                    (int)$row['id_nasabah']
                                                ) ?>,
                                                <?= json_encode($row['nama']) ?>,
                                                <?= json_encode($row['alamat']) ?>,
                                                <?= json_encode($row['no_hp']) ?>,
                                                <?= json_encode($row['tanggal_daftar']) ?>
                                            )'
                                        >
                                            ✎ Edit
                                        </button>


                                        <a
                                            href="nasabah.php?hapus=<?= (int)$row['id_nasabah'] ?>"
                                            class="btn-action btn-delete"
                                            onclick="return confirm('Yakin ingin menghapus nasabah ini? Data hanya dapat dihapus jika belum memiliki transaksi atau penarikan saldo.')"
                                        >
                                            × Hapus
                                        </a>


                                    </div>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td colspan="6">

                                <div class="empty">

                                    <?php if ($keyword !== ''): ?>

                                        Data nasabah dengan kata
                                        <strong>
                                            "<?= e($keyword) ?>"
                                        </strong>
                                        tidak ditemukan.

                                    <?php else: ?>

                                        Belum ada data nasabah.

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


<!-- =========================================================
     MODAL TAMBAH
========================================================= -->

<div
    class="modal"
    id="modalTambah"
>

    <div class="modal-box">


        <div class="modal-head">

            <h2>
                Tambah Nasabah
            </h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeTambah()"
            >
                ×
            </button>

        </div>


        <form method="POST">


            <div class="form-group">

                <label>
                    Nama Nasabah
                </label>

                <input
                    type="text"
                    name="nama"
                    class="form-control"
                    placeholder="Masukkan nama nasabah"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Alamat
                </label>

                <textarea
                    name="alamat"
                    class="form-control"
                    placeholder="Masukkan alamat nasabah"
                    required
                ></textarea>

            </div>


            <div class="form-group">

                <label>
                    Nomor HP
                </label>

                <input
                    type="text"
                    name="no_hp"
                    class="form-control"
                    placeholder="Contoh: 081234567890"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Tanggal Daftar
                </label>

                <input
                    type="date"
                    name="tanggal_daftar"
                    class="form-control"
                    value="<?= date('Y-m-d') ?>"
                    required
                >

            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeTambah()"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    name="tambah_nasabah"
                    class="btn-save"
                >
                    Simpan Nasabah
                </button>

            </div>


        </form>

    </div>

</div>


<!-- =========================================================
     MODAL EDIT
========================================================= -->

<div
    class="modal"
    id="modalEdit"
>

    <div class="modal-box">


        <div class="modal-head">

            <h2>
                Edit Nasabah
            </h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeEdit()"
            >
                ×
            </button>

        </div>


        <form method="POST">


            <input
                type="hidden"
                name="id_nasabah"
                id="edit_id"
            >


            <div class="form-group">

                <label>
                    Nama Nasabah
                </label>

                <input
                    type="text"
                    name="nama"
                    id="edit_nama"
                    class="form-control"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Alamat
                </label>

                <textarea
                    name="alamat"
                    id="edit_alamat"
                    class="form-control"
                    required
                ></textarea>

            </div>


            <div class="form-group">

                <label>
                    Nomor HP
                </label>

                <input
                    type="text"
                    name="no_hp"
                    id="edit_no_hp"
                    class="form-control"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Tanggal Daftar
                </label>

                <input
                    type="date"
                    name="tanggal_daftar"
                    id="edit_tanggal"
                    class="form-control"
                    required
                >

            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="btn-cancel"
                    onclick="closeEdit()"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    name="edit_nasabah"
                    class="btn-save"
                >
                    Simpan Perubahan
                </button>

            </div>


        </form>

    </div>

</div>


<script>

/* =========================================================
   TAMBAH
========================================================= */

function openTambah()
{
    document
        .getElementById('modalTambah')
        .classList.add('show');
}

function closeTambah()
{
    document
        .getElementById('modalTambah')
        .classList.remove('show');
}


/* =========================================================
   EDIT
========================================================= */

function openEdit(
    id,
    nama,
    alamat,
    noHp,
    tanggal
) {

    document.getElementById(
        'edit_id'
    ).value = id;

    document.getElementById(
        'edit_nama'
    ).value = nama;

    document.getElementById(
        'edit_alamat'
    ).value = alamat;

    document.getElementById(
        'edit_no_hp'
    ).value = noHp;

    document.getElementById(
        'edit_tanggal'
    ).value = tanggal;

    document
        .getElementById('modalEdit')
        .classList.add('show');
}

function closeEdit()
{
    document
        .getElementById('modalEdit')
        .classList.remove('show');
}


/* =========================================================
   KLIK LUAR MODAL
========================================================= */

window.addEventListener(
    'click',
    function(event)
    {
        const tambah =
            document.getElementById(
                'modalTambah'
            );

        const edit =
            document.getElementById(
                'modalEdit'
            );

        if (event.target === tambah) {
            closeTambah();
        }

        if (event.target === edit) {
            closeEdit();
        }
    }
);


/* =========================================================
   ESCAPE
========================================================= */

document.addEventListener(
    'keydown',
    function(event)
    {
        if (event.key === 'Escape') {
            closeTambah();
            closeEdit();
        }
    }
);

</script>


</body>
</html>