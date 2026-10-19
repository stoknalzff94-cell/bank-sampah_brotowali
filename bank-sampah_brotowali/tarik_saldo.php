<?php
session_start();

/* =========================================================
   CEK LOGIN
========================================================= */
if (!isset($_SESSION['id_user']) || !in_array($_SESSION['role'] ?? '', ['admin','guru'], true)) {
    header("Location: login.php");
    exit;
}

/* =========================================================
   KONEKSI DATABASE
========================================================= */
include "koneksi.php";

$role_user = $_SESSION['role'] ?? '';

$nama_user = $_SESSION['nama_user']
    ?? $_SESSION['nama']
    ?? $_SESSION['username']
    ?? 'User';

/* =========================================================
   DASHBOARD SESUAI ROLE
========================================================= */
if ($role_user === 'guru') {
    $dashboard_link = 'dashboard_guru.php';
} elseif ($role_user === 'admin') {
    $dashboard_link = 'dashboard.php';
} else {
    /*
       Untuk role lain yang sudah login,
       tetap gunakan dashboard utama.
    */
    $dashboard_link = 'dashboard.php';
}

/* =========================================================
   KONEKSI CHECK
========================================================= */
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($koneksi, "utf8mb4");

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

function rupiah($angka)
{
    return 'Rp ' . number_format(
        (float)$angka,
        0,
        ',',
        '.'
    );
}

/* =========================================================
   TAMBAH PENARIKAN SALDO
========================================================= */
if (isset($_POST['tambah_penarikan'])) {

    $id_nasabah = (int)($_POST['id_nasabah'] ?? 0);
    $tanggal    = $_POST['tanggal'] ?? date('Y-m-d');
    $jumlah     = (int)($_POST['jumlah'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (
        $id_nasabah <= 0 ||
        $tanggal === '' ||
        $jumlah <= 0 ||
        $keterangan === ''
    ) {

        $_SESSION['pesan'] = [
            'type' => 'error',
            'text' => 'Data penarikan belum lengkap.'
        ];

        header("Location: tarik_saldo.php");
        exit;
    }

    /* =====================================================
       CEK NASABAH
    ===================================================== */

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id_nasabah, nama
         FROM nasabah
         WHERE id_nasabah = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id_nasabah
    );

    mysqli_stmt_execute($stmt);

    $result_nasabah = mysqli_stmt_get_result($stmt);

    $nasabah = mysqli_fetch_assoc($result_nasabah);

    mysqli_stmt_close($stmt);

    if (!$nasabah) {

        $_SESSION['pesan'] = [
            'type' => 'error',
            'text' => 'Nasabah tidak ditemukan.'
        ];

        header("Location: tarik_saldo.php");
        exit;
    }

    /* =====================================================
       HITUNG TOTAL SALDO NASABAH
       HANYA TRANSAKSI YANG DISETUJUI
    ===================================================== */

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT
            COALESCE(SUM(total), 0) AS total_setoran
         FROM transaksi
         WHERE id_nasabah = ?
         AND status = 'disetujui'"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id_nasabah
    );

    mysqli_stmt_execute($stmt);

    $result_setoran = mysqli_stmt_get_result($stmt);

    $data_setoran = mysqli_fetch_assoc($result_setoran);

    mysqli_stmt_close($stmt);

    $total_setoran =
        (int)($data_setoran['total_setoran'] ?? 0);

    /* =====================================================
       HITUNG TOTAL PENARIKAN SEBELUMNYA
    ===================================================== */

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT
            COALESCE(SUM(jumlah), 0) AS total_tarik
         FROM tarik_saldo
         WHERE id_nasabah = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id_nasabah
    );

    mysqli_stmt_execute($stmt);

    $result_tarik = mysqli_stmt_get_result($stmt);

    $data_tarik = mysqli_fetch_assoc($result_tarik);

    mysqli_stmt_close($stmt);

    $total_tarik =
        (int)($data_tarik['total_tarik'] ?? 0);

    /* =====================================================
       SALDO TERSEDIA
    ===================================================== */

    $saldo_tersedia =
        $total_setoran - $total_tarik;

    /* =====================================================
       CEK SALDO CUKUP
    ===================================================== */

    if ($jumlah > $saldo_tersedia) {

        $_SESSION['pesan'] = [
            'type' => 'error',
            'text' =>
                'Penarikan gagal. Saldo ' .
                $nasabah['nama'] .
                ' hanya tersedia ' .
                rupiah($saldo_tersedia) .
                '.'
        ];

        header("Location: tarik_saldo.php");
        exit;
    }

    /* =====================================================
       INSERT PENARIKAN
    ===================================================== */

    $stmt = mysqli_prepare(
        $koneksi,
        "INSERT INTO tarik_saldo
        (
            id_nasabah,
            tanggal,
            jumlah,
            keterangan
        )
        VALUES (?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "isis",
        $id_nasabah,
        $tanggal,
        $jumlah,
        $keterangan
    );

    if (mysqli_stmt_execute($stmt)) {

        $_SESSION['pesan'] = [
            'type' => 'success',
            'text' =>
                'Penarikan saldo sebesar ' .
                rupiah($jumlah) .
                ' berhasil dicatat.'
        ];

    } else {

        $_SESSION['pesan'] = [
            'type' => 'error',
            'text' =>
                'Gagal mencatat penarikan saldo.'
        ];
    }

    mysqli_stmt_close($stmt);

    header("Location: tarik_saldo.php");
    exit;
}

/* =========================================================
   HAPUS PENARIKAN
========================================================= */
if (isset($_GET['hapus'])) {

    $id_hapus = (int)$_GET['hapus'];

    if ($id_hapus > 0) {

        $stmt = mysqli_prepare(
            $koneksi,
            "DELETE FROM tarik_saldo
             WHERE id_tarik = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id_hapus
        );

        if (mysqli_stmt_execute($stmt)) {

            $_SESSION['pesan'] = [
                'type' => 'success',
                'text' =>
                    'Data penarikan berhasil dihapus.'
            ];

        } else {

            $_SESSION['pesan'] = [
                'type' => 'error',
                'text' =>
                    'Gagal menghapus data penarikan.'
            ];
        }

        mysqli_stmt_close($stmt);
    }

    header("Location: tarik_saldo.php");
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

/* Total penarikan */
$query_total_tarik = mysqli_query(
    $koneksi,
    "SELECT
        COUNT(*) AS jumlah,
        COALESCE(SUM(jumlah), 0) AS total
     FROM tarik_saldo"
);

$data_total_tarik =
    mysqli_fetch_assoc($query_total_tarik);

$total_penarikan =
    (int)($data_total_tarik['jumlah'] ?? 0);

$total_ditarik =
    (int)($data_total_tarik['total'] ?? 0);

/* Total saldo dari transaksi disetujui */
$query_total_setoran = mysqli_query(
    $koneksi,
    "SELECT
        COALESCE(SUM(total), 0) AS total
     FROM transaksi
     WHERE status = 'disetujui'"
);

$data_total_setoran =
    mysqli_fetch_assoc($query_total_setoran);

$total_setoran_semua =
    (int)($data_total_setoran['total'] ?? 0);

/* Saldo keseluruhan */
$total_saldo =
    $total_setoran_semua -
    $total_ditarik;

/* Jumlah nasabah */
$query_nasabah_saldo = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM nasabah"
);

$data_nasabah =
    mysqli_fetch_assoc($query_nasabah_saldo);

$total_nasabah =
    (int)($data_nasabah['total'] ?? 0);

/* =========================================================
   SEARCH
========================================================= */
$keyword = trim($_GET['q'] ?? '');

if ($keyword !== '') {

    $keyword_like = '%' . $keyword . '%';

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT
            t.id_tarik,
            t.id_nasabah,
            t.tanggal,
            t.jumlah,
            t.keterangan,
            n.nama AS nama_nasabah
         FROM tarik_saldo t
         INNER JOIN nasabah n
            ON n.id_nasabah = t.id_nasabah
         WHERE n.nama LIKE ?
            OR t.keterangan LIKE ?
         ORDER BY t.id_tarik DESC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ss",
        $keyword_like,
        $keyword_like
    );

    mysqli_stmt_execute($stmt);

    $result_tarik =
        mysqli_stmt_get_result($stmt);

} else {

    $result_tarik = mysqli_query(
        $koneksi,
        "SELECT
            t.id_tarik,
            t.id_nasabah,
            t.tanggal,
            t.jumlah,
            t.keterangan,
            n.nama AS nama_nasabah
         FROM tarik_saldo t
         INNER JOIN nasabah n
            ON n.id_nasabah = t.id_nasabah
         ORDER BY t.id_tarik DESC"
    );
}

/* =========================================================
   DROPDOWN NASABAH + SALDO
========================================================= */
$result_nasabah = mysqli_query(
    $koneksi,
    "SELECT
        n.id_nasabah,
        n.nama,

        COALESCE(
            (
                SELECT SUM(t.total)
                FROM transaksi t
                WHERE t.id_nasabah = n.id_nasabah
                AND t.status = 'disetujui'
            ),
            0
        ) AS total_setoran,

        COALESCE(
            (
                SELECT SUM(ts.jumlah)
                FROM tarik_saldo ts
                WHERE ts.id_nasabah = n.id_nasabah
            ),
            0
        ) AS total_tarik

     FROM nasabah n
     ORDER BY n.nama ASC"
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

<title>Tarik Saldo | Bank Sampah</title>

<link rel="preconnect" href="https://fonts.googleapis.com">

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
            rgba(0, 190, 140, .07),
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
        0 10px 25px rgba(0, 205, 150, .17);
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
    background: rgba(18, 163, 123, .09);
}

.menu a.active {
    color: #ffffff;

    background:
        linear-gradient(
            90deg,
            rgba(16, 159, 120, .25),
            rgba(13, 110, 87, .20)
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
    border-radius: 0 4px 4px 0;

    box-shadow:
        0 0 12px rgba(33, 224, 164, .5);
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
        rgba(18, 103, 81, .35);

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
        rgba(3, 31, 24, .75);
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
        rgba(8, 74, 58, .28);

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
        0 0 10px rgba(34, 227, 163, .8);
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
        0 9px 22px rgba(0, 185, 133, .14);

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
    grid-template-columns: repeat(4, 1fr);

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
            rgba(8, 52, 42, .90),
            rgba(5, 42, 34, .88)
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
        rgba(16, 121, 91, .12);
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
        rgba(5, 47, 37, .72);

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
        rgba(4, 45, 35, .78);

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
        rgba(12, 88, 68, .43);
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
        rgba(20, 110, 85, .06);
}

.number {
    color: #49887a;
}

.id-badge {
    display: inline-block;

    padding: 3px 7px;
    margin-bottom: 6px;

    background:
        rgba(16, 124, 92, .28);

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

.tanggal {
    color: #74aaa0;
}

.jumlah {
    color: #66dfb2;

    font-size: 11px;
    font-weight: 700;
}

.keterangan {
    color: #a1cfc3;
    max-width: 220px;
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

.btn-delete {
    background:
        rgba(128, 25, 55, .25);

    border: 1px solid #703044;
    color: #e86b8a;
}

.btn-delete:hover {
    background:
        rgba(158, 30, 65, .38);
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
        rgba(15, 132, 96, .18);

    border: 1px solid #17684f;
}

.alert.error {
    color: #ff9caf;

    background:
        rgba(144, 27, 59, .16);

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
        rgba(0, 0, 0, .65);

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
        0 25px 70px rgba(0, 0, 0, .5);

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

select.form-control {
    cursor: pointer;
}

select.form-control option {
    background: #073127;
    color: #ffffff;
}

/* =========================================================
   SALDO INFO
========================================================= */

.saldo-info {
    margin-top: 5px;

    padding: 13px;

    border-radius: 10px;

    border: 1px solid #17634f;

    background:
        rgba(11, 105, 79, .18);
}

.saldo-label {
    color: #64a99a;

    font-size: 8px;
    margin-bottom: 5px;
}

.saldo-value {
    color: #66e0b3;

    font-size: 19px;
    font-weight: 800;
}

.saldo-note {
    color: #4f8d80;

    font-size: 8px;
    margin-top: 5px;
}

/* =========================================================
   MODAL ACTIONS
========================================================= */

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
        grid-template-columns: repeat(2, 1fr);
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
        grid-template-columns: repeat(2, 1fr);
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

    <?php $tb_judul = 'Manajemen Tarik Saldo'; $tb_sub = 'Kelola penarikan saldo nasabah Bank Sampah'; include __DIR__ . '/admin_topbar.php'; ?>

    <section class="content">

        <?php if ($pesan): ?>

            <div class="alert <?= $pesan['type'] === 'success'
                ? 'success'
                : 'error' ?>">

                <?= e($pesan['text']) ?>

            </div>

        <?php endif; ?>

        <div class="page-head">

            <div>

                <div class="breadcrumb">
                    BANK SAMPAH / TARIK SALDO
                </div>

                <h1>
                    Tarik Saldo
                </h1>

                <p class="page-description">
                    Kelola dan catat penarikan saldo nasabah.
                </p>

            </div>

            <button
                type="button"
                class="btn-primary"
                onclick="openTambah()"
            >
                + &nbsp; Tarik Saldo
            </button>

        </div>

        <!-- STATS -->

        <div class="stats">

            <div class="stat-card">

                <div class="stat-label">
                    TOTAL SALDO
                </div>

                <div class="stat-number">
                    <?= rupiah($total_saldo) ?>
                </div>

                <div class="stat-info">
                    Saldo seluruh nasabah
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-label">
                    TOTAL DITARIK
                </div>

                <div class="stat-number">
                    <?= rupiah($total_ditarik) ?>
                </div>

                <div class="stat-info">
                    Akumulasi penarikan
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-label">
                    JUMLAH PENARIKAN
                </div>

                <div class="stat-number">
                    <?= $total_penarikan ?>
                </div>

                <div class="stat-info">
                    Transaksi penarikan tercatat
                </div>

            </div>

            <div class="stat-card">

                <div class="stat-label">
                    TOTAL SETORAN
                </div>

                <div class="stat-number">
                    <?= rupiah($total_setoran_semua) ?>
                </div>

                <div class="stat-info">
                    Dari transaksi yang disetujui
                </div>

            </div>

        </div>

        <!-- SEARCH -->

        <div class="search-box">

            <form
                method="GET"
                action="tarik_saldo.php"
            >

                <input
                    type="text"
                    name="q"
                    class="search-input"
                    placeholder="Cari nama nasabah atau keterangan..."
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

        <!-- TABLE -->

        <div class="table-card">

            <div class="table-head">

                <h3>
                    Riwayat Penarikan Saldo
                </h3>

                <span>
                    <?= $total_penarikan ?> penarikan tercatat
                </span>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>NO</th>

                            <th>NASABAH</th>

                            <th>TANGGAL</th>

                            <th>JUMLAH</th>

                            <th>KETERANGAN</th>

                            <th>AKSI</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (
                        $result_tarik &&
                        mysqli_num_rows($result_tarik) > 0
                    ): ?>

                        <?php $no = 1; ?>

                        <?php while (
                            $row =
                            mysqli_fetch_assoc($result_tarik)
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
                                        <?= e($row['nama_nasabah']) ?>
                                    </div>

                                </td>

                                <td class="tanggal">

                                    <?= date(
                                        'd/m/Y',
                                        strtotime($row['tanggal'])
                                    ) ?>

                                </td>

                                <td>

                                    <span class="jumlah">
                                        <?= rupiah($row['jumlah']) ?>
                                    </span>

                                </td>

                                <td>

                                    <div class="keterangan">
                                        <?= e($row['keterangan']) ?>
                                    </div>

                                </td>

                                <td>

                                    <div class="actions">

                                        <a
                                            href="tarik_saldo.php?hapus=<?= (int)$row['id_tarik'] ?>"
                                            class="btn-action btn-delete"
                                            onclick="return confirm('Yakin ingin menghapus data penarikan ini? Saldo nasabah akan bertambah kembali setelah data dihapus.')"
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
                                    Belum ada data penarikan saldo.
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
     MODAL TAMBAH PENARIKAN
========================================================= -->

<div
    class="modal"
    id="modalTambah"
>

    <div class="modal-box">

        <div class="modal-head">

            <h2>
                Tarik Saldo Nasabah
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
                    Nasabah
                </label>

                <select
                    name="id_nasabah"
                    id="id_nasabah"
                    class="form-control"
                    required
                    onchange="tampilkanSaldo()"
                >

                    <option
                        value=""
                        data-saldo="0"
                    >
                        -- Pilih Nasabah --
                    </option>

                    <?php if (
                        $result_nasabah &&
                        mysqli_num_rows($result_nasabah) > 0
                    ): ?>

                        <?php while (
                            $nasabah =
                            mysqli_fetch_assoc($result_nasabah)
                        ): ?>

                            <?php

                            $saldo_nasabah =
                                (int)$nasabah['total_setoran']
                                -
                                (int)$nasabah['total_tarik'];

                            if ($saldo_nasabah < 0) {
                                $saldo_nasabah = 0;
                            }

                            ?>

                            <option
                                value="<?= (int)$nasabah['id_nasabah'] ?>"
                                data-saldo="<?= $saldo_nasabah ?>"
                            >
                                <?= e($nasabah['nama']) ?>
                                -
                                <?= rupiah($saldo_nasabah) ?>
                            </option>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <option value="">
                            Belum ada nasabah
                        </option>

                    <?php endif; ?>

                </select>

            </div>

            <div class="saldo-info">

                <div class="saldo-label">
                    SALDO TERSEDIA
                </div>

                <div
                    class="saldo-value"
                    id="saldoPreview"
                >
                    Rp 0
                </div>

                <div class="saldo-note">
                    Saldo berasal dari transaksi yang sudah disetujui dikurangi penarikan sebelumnya.
                </div>

            </div>

            <div
                class="form-group"
                style="margin-top:15px;"
            >

                <label>
                    Tanggal Penarikan
                </label>

                <input
                    type="date"
                    name="tanggal"
                    class="form-control"
                    value="<?= date('Y-m-d') ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>
                    Jumlah Penarikan
                </label>

                <input
                    type="number"
                    name="jumlah"
                    id="jumlah"
                    class="form-control"
                    placeholder="Contoh: 50000"
                    min="1"
                    step="1"
                    required
                    oninput="cekJumlah()"
                >

            </div>

            <div class="form-group">

                <label>
                    Keterangan
                </label>

                <textarea
                    name="keterangan"
                    class="form-control"
                    placeholder="Contoh: Penarikan saldo tunai"
                    required
                ></textarea>

            </div>

            <div
                id="peringatanSaldo"
                style="
                    display:none;
                    margin-top:5px;
                    padding:10px;
                    border-radius:9px;
                    color:#ff9caf;
                    background:rgba(144,27,59,.16);
                    border:1px solid #713246;
                    font-size:9px;
                "
            >
                Jumlah penarikan melebihi saldo tersedia.
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
                    name="tambah_penarikan"
                    class="btn-save"
                    id="btnSimpan"
                >
                    Simpan Penarikan
                </button>

            </div>

        </form>

    </div>

</div>

<script>

/* =========================================================
   MODAL
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
   FORMAT RUPIAH
========================================================= */

function formatRupiah(angka)
{
    return 'Rp ' +
        Math.round(angka)
            .toLocaleString('id-ID');
}

/* =========================================================
   TAMPILKAN SALDO
========================================================= */

function tampilkanSaldo()
{
    const select =
        document.getElementById('id_nasabah');

    const preview =
        document.getElementById('saldoPreview');

    const option =
        select.options[select.selectedIndex];

    const saldo =
        parseInt(
            option?.getAttribute('data-saldo') || 0
        );

    preview.textContent =
        formatRupiah(saldo);

    cekJumlah();
}

/* =========================================================
   CEK JUMLAH PENARIKAN
========================================================= */

function cekJumlah()
{
    const select =
        document.getElementById('id_nasabah');

    const jumlahInput =
        document.getElementById('jumlah');

    const peringatan =
        document.getElementById('peringatanSaldo');

    const btnSimpan =
        document.getElementById('btnSimpan');

    const option =
        select.options[select.selectedIndex];

    const saldo =
        parseInt(
            option?.getAttribute('data-saldo') || 0
        );

    const jumlah =
        parseInt(
            jumlahInput.value || 0
        );

    if (
        jumlah > 0 &&
        jumlah > saldo
    ) {

        peringatan.style.display = 'block';

        btnSimpan.disabled = true;
        btnSimpan.style.opacity = '.45';
        btnSimpan.style.cursor = 'not-allowed';

    } else {

        peringatan.style.display = 'none';

        btnSimpan.disabled = false;
        btnSimpan.style.opacity = '1';
        btnSimpan.style.cursor = 'pointer';
    }
}

/* =========================================================
   KLIK LUAR MODAL
========================================================= */

window.addEventListener(
    'click',
    function(event)
    {
        const modal =
            document.getElementById('modalTambah');

        if (event.target === modal) {
            closeTambah();
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
        }
    }
);

</script>

</body>
</html>