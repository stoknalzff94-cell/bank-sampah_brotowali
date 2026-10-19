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
| IDENTITAS SISWA
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

/*
|--------------------------------------------------------------------------
| AMBIL DATA NASABAH
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        id_nasabah,
        nama,
        alamat,
        no_hp,
        tanggal_daftar
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

$result_nasabah =
    mysqli_stmt_get_result($stmt);

$nasabah =
    mysqli_fetch_assoc($result_nasabah);

mysqli_stmt_close($stmt);

if (!$nasabah) {
    die("Data nasabah tidak ditemukan.");
}

$nama_siswa = $nasabah['nama'];

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_tarik'])) {
    $_SESSION['csrf_tarik'] =
        bin2hex(random_bytes(32));
}

$csrf_token =
    $_SESSION['csrf_tarik'];

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| HITUNG TOTAL SETORAN DISETUJUI
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(berat), 0) AS total_berat,
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

$result_setoran =
    mysqli_stmt_get_result($stmt);

$data_setoran =
    mysqli_fetch_assoc($result_setoran);

mysqli_stmt_close($stmt);

$total_setoran =
    (int)($data_setoran['total_setoran'] ?? 0);

$total_berat =
    (float)($data_setoran['total_berat'] ?? 0);

$jumlah_transaksi =
    (int)($data_setoran['jumlah_transaksi'] ?? 0);

/*
|--------------------------------------------------------------------------
| HITUNG TOTAL PENARIKAN
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        COUNT(*) AS jumlah_penarikan,
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

$result_tarik =
    mysqli_stmt_get_result($stmt);

$data_tarik =
    mysqli_fetch_assoc($result_tarik);

mysqli_stmt_close($stmt);

$total_tarik =
    (int)($data_tarik['total_tarik'] ?? 0);

$jumlah_penarikan =
    (int)($data_tarik['jumlah_penarikan'] ?? 0);

/*
|--------------------------------------------------------------------------
| SALDO
|--------------------------------------------------------------------------
*/

$saldo =
    $total_setoran - $total_tarik;

if ($saldo < 0) {
    $saldo = 0;
}

/*
|--------------------------------------------------------------------------
| PESAN
|--------------------------------------------------------------------------
*/

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| PROSES PENARIKAN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token =
        $_POST['csrf_token'] ?? '';

    $jumlah =
        (int)($_POST['jumlah'] ?? 0);

    $keterangan =
        trim(
            $_POST['keterangan'] ?? ''
        );

    /*
    |--------------------------------------------------------------------------
    | VALIDASI CSRF
    |--------------------------------------------------------------------------
    */

    if (
        !hash_equals(
            $_SESSION['csrf_tarik'],
            $token
        )
    ) {

        $error =
            "Permintaan tidak valid. Silakan coba lagi.";

    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI JUMLAH
    |--------------------------------------------------------------------------
    */

    elseif ($jumlah <= 0) {

        $error =
            "Nominal penarikan harus lebih dari Rp 0.";

    }

    elseif ($jumlah < 1000) {

        $error =
            "Nominal penarikan minimal Rp 1.000.";

    }

    elseif ($jumlah > $saldo) {

        $error =
            "Saldo tidak mencukupi untuk penarikan sebesar " .
            rupiah($jumlah) . ".";

    }

    else {

        /*
        |--------------------------------------------------------------------------
        | KETERANGAN DEFAULT
        |--------------------------------------------------------------------------
        */

        if ($keterangan === '') {
            $keterangan =
                "Penarikan saldo oleh siswa";
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSACTION DATABASE
        |--------------------------------------------------------------------------
        */

        mysqli_begin_transaction($koneksi);

        try {

            /*
            |--------------------------------------------------------------------------
            | CEK SALDO LAGI DARI DATABASE
            | Untuk menghindari saldo berubah sebelum INSERT.
            |--------------------------------------------------------------------------
            */

            $stmt = mysqli_prepare(
                $koneksi,
                "SELECT
                    COALESCE(
                        (
                            SELECT SUM(total)
                            FROM transaksi
                            WHERE id_nasabah = ?
                            AND status = 'disetujui'
                        ),
                        0
                    )
                    -
                    COALESCE(
                        (
                            SELECT SUM(jumlah)
                            FROM tarik_saldo
                            WHERE id_nasabah = ?
                        ),
                        0
                    ) AS saldo
                 FROM dual
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $id_nasabah,
                $id_nasabah
            );

            mysqli_stmt_execute($stmt);

            $result_check =
                mysqli_stmt_get_result($stmt);

            $saldo_db =
                mysqli_fetch_assoc($result_check);

            mysqli_stmt_close($stmt);

            $saldo_terkini =
                (int)($saldo_db['saldo'] ?? 0);

            if ($saldo_terkini < $jumlah) {

                throw new Exception(
                    "Saldo tidak mencukupi."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | INSERT PENARIKAN
            |--------------------------------------------------------------------------
            */

            $tanggal =
                date('Y-m-d');

            $stmt = mysqli_prepare(
                $koneksi,
                "INSERT INTO tarik_saldo
                (
                    id_nasabah,
                    tanggal,
                    jumlah,
                    keterangan
                )
                VALUES
                (?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "isis",
                $id_nasabah,
                $tanggal,
                $jumlah,
                $keterangan
            );

            if (
                !mysqli_stmt_execute($stmt)
            ) {

                throw new Exception(
                    "Gagal menyimpan penarikan."
                );
            }

            mysqli_stmt_close($stmt);

            mysqli_commit($koneksi);

            $success =
                "Penarikan sebesar " .
                rupiah($jumlah) .
                " berhasil dicatat.";

            /*
            |--------------------------------------------------------------------------
            | REFRESH NILAI
            |--------------------------------------------------------------------------
            */

            $total_tarik += $jumlah;

            $jumlah_penarikan++;

            $saldo -= $jumlah;

            if ($saldo < 0) {
                $saldo = 0;
            }

        } catch (Exception $e) {

            mysqli_rollback($koneksi);

            $error =
                $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| RIWAYAT PENARIKAN
|--------------------------------------------------------------------------
*/

$data_riwayat = [];

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        id_tarik,
        tanggal,
        jumlah,
        keterangan
     FROM tarik_saldo
     WHERE id_nasabah = ?
     ORDER BY id_tarik DESC
     LIMIT 15"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_nasabah
);

mysqli_stmt_execute($stmt);

$result_riwayat =
    mysqli_stmt_get_result($stmt);

while (
    $row =
    mysqli_fetch_assoc($result_riwayat)
) {

    $data_riwayat[] = $row;
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| TOTAL SETELAH PENARIKAN
|--------------------------------------------------------------------------
*/

$saldo_awal_tampilan =
    $saldo;

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Tarik Saldo - Ruang Siswa
</title>

<style>

/* =====================================================
   VARIABLES
===================================================== */

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
            circle at 85% 0%,
            rgba(20,184,166,.11),
            transparent 28%
        ),

        radial-gradient(
            circle at 10% 95%,
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
            #101c2c 0%,
            #0b1725 50%,
            #08131f 100%
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
        0 12px 30px
        rgba(20,184,166,.18);
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
   HERO
===================================================== */

.hero {

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

.hero::after {

    content: "";

    position: absolute;

    width: 280px;
    height: 280px;

    right: -100px;
    top: -130px;

    border-radius: 50%;

    background:
        radial-gradient(
            circle,
            rgba(34,211,238,.15),
            transparent 68%
        );
}

.hero-inner {

    position: relative;

    z-index: 2;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 30px;
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

    font-weight: 850;

    letter-spacing: -.8px;

    margin-bottom: 8px;
}

.hero-title span {

    color: #4ee49a;
}

.hero-desc {

    color: var(--muted);

    max-width: 650px;

    font-size: 12px;

    line-height: 1.7;
}

.hero-balance {

    min-width: 250px;

    padding: 21px;

    border-radius: 18px;

    background:
        rgba(255,255,255,.045);

    border:
        1px solid
        rgba(255,255,255,.08);

    box-shadow:
        inset 0 1px
        rgba(255,255,255,.03);
}

.hero-balance-label {

    color: #8ca0b7;

    font-size: 9px;

    font-weight: 800;

    letter-spacing: 1px;

    text-transform: uppercase;

    margin-bottom: 7px;
}

.hero-balance-value {

    color: #ecfdf5;

    font-size: 26px;

    font-weight: 900;

    letter-spacing: -.7px;
}

.hero-balance-note {

    color: #64748b;

    font-size: 9px;

    margin-top: 5px;
}

/* =====================================================
   GRID
===================================================== */

.main-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1.1fr)
        minmax(320px, .9fr);

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

    align-items: center;

    justify-content: space-between;

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
   ALERT
===================================================== */

.alert {

    padding: 14px 16px;

    border-radius: 13px;

    margin-bottom: 20px;

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
   FORM
===================================================== */

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

.input-wrap {

    position: relative;
}

.currency {

    position: absolute;

    left: 15px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #64748b;

    font-size: 12px;

    font-weight: 800;

    pointer-events: none;
}

.form-control {

    width: 100%;

    height: 52px;

    border-radius: 14px;

    border:
        1px solid
        rgba(148,163,184,.14);

    outline: none;

    background:
        #0b1826;

    color: white;

    padding:
        0 15px;

    font-size: 14px;

    transition: .25s ease;
}

.amount-input {

    padding-left: 52px;

    font-size: 19px;

    font-weight: 850;
}

.form-control:focus {

    border-color:
        rgba(45,212,191,.55);

    box-shadow:
        0 0 0 3px
        rgba(20,184,166,.09);
}

textarea.form-control {

    height: 90px;

    resize: vertical;

    padding: 13px;

    font-size: 11px;
}

/* =====================================================
   QUICK NOMINAL
===================================================== */

.quick-label {

    color: #64748b;

    font-size: 9px;

    font-weight: 800;

    margin:
        17px 0 9px;
}

.quick-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 8px;
}

.quick-btn {

    height: 37px;

    border-radius: 10px;

    border:
        1px solid
        rgba(255,255,255,.07);

    color: #b9c7d6;

    background:
        rgba(255,255,255,.03);

    font-size: 9px;

    font-weight: 800;

    cursor: pointer;

    transition: .2s ease;
}

.quick-btn:hover {

    color: white;

    border-color:
        rgba(45,212,191,.25);

    background:
        rgba(20,184,166,.08);
}

.quick-btn.selected {

    color: white;

    border-color:
        rgba(45,212,191,.35);

    background:
        rgba(20,184,166,.12);
}

/* =====================================================
   AFTER BALANCE
===================================================== */

.after-box {

    margin-top: 17px;

    padding: 17px;

    border-radius: 15px;

    background:
        rgba(255,255,255,.035);

    border:
        1px solid
        rgba(255,255,255,.06);

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}

.after-label {

    color: #64748b;

    font-size: 9px;

    margin-bottom: 5px;
}

.after-value {

    color: #6ee7b7;

    font-size: 16px;

    font-weight: 850;
}

.after-icon {

    width: 39px;
    height: 39px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(34,197,94,.09);
}

/* SUBMIT */

.submit-btn {

    width: 100%;

    height: 51px;

    margin-top: 17px;

    border: none;

    border-radius: 14px;

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

    box-shadow:
        0 12px 30px
        rgba(20,184,166,.18);

    transition: .25s ease;
}

.submit-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 17px 38px
        rgba(20,184,166,.25);
}

.submit-btn:disabled {

    opacity: .6;

    cursor: not-allowed;

    transform: none;
}

/* =====================================================
   SUMMARY
===================================================== */

.summary-list {

    display: flex;

    flex-direction: column;

    gap: 10px;
}

.summary-item {

    padding: 15px;

    border-radius: 14px;

    background:
        rgba(255,255,255,.03);

    border:
        1px solid
        rgba(255,255,255,.055);

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.summary-left {

    display: flex;

    align-items: center;

    gap: 12px;
}

.summary-icon {

    width: 38px;
    height: 38px;

    border-radius: 11px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        rgba(20,184,166,.10);
}

.summary-name {

    font-size: 10px;

    color: #8ca0b7;
}

.summary-value {

    font-size: 13px;

    font-weight: 850;
}

.summary-value.green {

    color: #6ee7b7;
}

.summary-value.yellow {

    color: #fcd34d;
}

/* =====================================================
   NOTICE
===================================================== */

.notice {

    margin-top: 17px;

    padding: 14px;

    border-radius: 13px;

    color: #94a3b8;

    background:
        rgba(251,191,36,.05);

    border:
        1px solid
        rgba(251,191,36,.10);

    font-size: 9px;

    line-height: 1.7;
}

/* =====================================================
   TABLE
===================================================== */

.table-wrap {

    overflow-x: auto;
}

table {

    width: 100%;

    min-width: 680px;

    border-collapse: collapse;
}

th {

    text-align: left;

    color: #64748b;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;

    letter-spacing: .8px;

    padding:
        11px 12px;

    border-bottom:
        1px solid
        rgba(255,255,255,.07);
}

td {

    color: #cbd5e1;

    font-size: 11px;

    padding:
        14px 12px;

    border-bottom:
        1px solid
        rgba(255,255,255,.045);
}

tr:hover td {

    background:
        rgba(255,255,255,.018);
}

.date {

    color: #64748b;

    font-size: 10px;
}

.money {

    color: #fcd34d;

    font-weight: 850;
}

.description {

    color: #a9b7c7;

    max-width: 250px;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;
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

@media (max-width: 1050px) {

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

        padding:
            0 20px;
    }

    .content {

        padding:
            24px 18px 40px;
    }

    .hero-inner {

        flex-direction: column;

        align-items: flex-start;
    }

    .hero-balance {

        width: 100%;

        min-width: 0;
    }

}

@media (max-width: 600px) {

    .quick-grid {

        grid-template-columns:
            repeat(2, 1fr);
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

<div class="hero">

    <div class="hero-inner">

        <div>

            <div class="hero-label">
                💰 Dompet Siswa
            </div>

            <div class="hero-title">

                Halo,
                <span>
                    <?php
                    echo htmlspecialchars(
                        $nama_siswa
                    );
                    ?>
                </span> 👋

            </div>

            <div class="hero-desc">

                Saldo kamu berasal dari transaksi
                setoran sampah yang sudah disetujui.
                Gunakan saldo sesuai kebutuhanmu.

            </div>

        </div>


        <div class="hero-balance">

            <div class="hero-balance-label">
                Saldo tersedia
            </div>

            <div
                class="hero-balance-value"
                id="heroSaldo"
                data-saldo="<?php
                echo (int)$saldo;
                ?>"
            >

                <?php
                echo rupiah($saldo);
                ?>

            </div>

            <div class="hero-balance-note">

                <?php
                echo $jumlah_transaksi;
                ?>
                transaksi disetujui

            </div>

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
        echo htmlspecialchars(
            $success
        );
        ?>

    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="alert alert-error">

        ⚠️
        <?php
        echo htmlspecialchars(
            $error
        );
        ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     MAIN GRID
===================================================== -->

<div class="main-grid">


<!-- =====================================================
     FORM TARIK
===================================================== -->

<div class="card">

    <div class="card-header">

        <div>

            <div class="card-title">
                Tarik Saldo
            </div>

            <div class="card-subtitle">
                Masukkan nominal yang ingin ditarik
            </div>

        </div>

        <div style="font-size:22px;">
            💸
        </div>

    </div>


    <form
        method="POST"
        id="formTarik"
        action=""
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?php
            echo htmlspecialchars(
                $csrf_token
            );
            ?>"
        >


        <!-- NOMINAL -->

        <label
            class="form-label"
            for="jumlah"
        >
            Nominal Penarikan
            <span>
                • minimal Rp 1.000
            </span>
        </label>


        <div class="input-wrap">

            <span class="currency">
                Rp
            </span>

            <input
                type="number"
                name="jumlah"
                id="jumlah"
                class="form-control amount-input"
                placeholder="0"
                min="1000"
                max="<?php
                echo (int)$saldo;
                ?>"
                step="1000"
                required
            >

        </div>


        <!-- QUICK -->

        <div class="quick-label">
            NOMINAL CEPAT
        </div>


        <div class="quick-grid">

            <button
                type="button"
                class="quick-btn"
                data-value="10000"
            >
                10 Ribu
            </button>

            <button
                type="button"
                class="quick-btn"
                data-value="25000"
            >
                25 Ribu
            </button>

            <button
                type="button"
                class="quick-btn"
                data-value="50000"
            >
                50 Ribu
            </button>

            <button
                type="button"
                class="quick-btn"
                data-value="100000"
            >
                100 Ribu
            </button>

        </div>


        <!-- KETERANGAN -->

        <div
            style="
                margin-top:19px;
            "
        >

            <label
                class="form-label"
                for="keterangan"
            >
                Keterangan
                <span>
                    • opsional
                </span>
            </label>


            <textarea
                name="keterangan"
                id="keterangan"
                class="form-control"
                placeholder="Contoh: Penarikan untuk kebutuhan siswa..."
                maxlength="255"
            ></textarea>

        </div>


        <!-- AFTER -->

        <div class="after-box">

            <div>

                <div class="after-label">
                    Saldo setelah penarikan
                </div>

                <div
                    class="after-value"
                    id="afterBalance"
                >
                    <?php
                    echo rupiah($saldo);
                    ?>
                </div>

            </div>

            <div class="after-icon">
                💰
            </div>

        </div>


        <button
            type="submit"
            class="submit-btn"
            id="submitBtn"
        >

            💸
            Tarik Saldo Sekarang

        </button>

    </form>

</div>


<!-- =====================================================
     RINGKASAN
===================================================== -->

<div class="card">

    <div class="card-header">

        <div>

            <div class="card-title">
                Ringkasan Saldo
            </div>

            <div class="card-subtitle">
                Informasi keuangan akunmu
            </div>

        </div>

        <div style="font-size:22px;">
            📊
        </div>

    </div>


    <div class="summary-list">


        <div class="summary-item">

            <div class="summary-left">

                <div class="summary-icon">
                    ♻️
                </div>

                <div>

                    <div class="summary-name">
                        Nilai setoran disetujui
                    </div>

                    <div
                        class="summary-value green"
                    >

                        <?php
                        echo rupiah(
                            $total_setoran
                        );
                        ?>

                    </div>

                </div>

            </div>

        </div>


        <div class="summary-item">

            <div class="summary-left">

                <div
                    class="summary-icon"
                    style="
                        background:
                        rgba(251,191,36,.08);
                    "
                >
                    💸
                </div>

                <div>

                    <div class="summary-name">
                        Total sudah ditarik
                    </div>

                    <div
                        class="summary-value yellow"
                    >

                        <?php
                        echo rupiah(
                            $total_tarik
                        );
                        ?>

                    </div>

                </div>

            </div>

        </div>


        <div class="summary-item">

            <div class="summary-left">

                <div
                    class="summary-icon"
                    style="
                        background:
                        rgba(34,211,238,.08);
                    "
                >
                    ⚖️
                </div>

                <div>

                    <div class="summary-name">
                        Total berat setoran
                    </div>

                    <div
                        class="summary-value"
                    >

                        <?php
                        echo number_format(
                            $total_berat,
                            2,
                            ',',
                            '.'
                        );
                        ?>

                        Kg

                    </div>

                </div>

            </div>

        </div>


        <div class="summary-item">

            <div class="summary-left">

                <div
                    class="summary-icon"
                    style="
                        background:
                        rgba(139,92,246,.08);
                    "
                >
                    📋
                </div>

                <div>

                    <div class="summary-name">
                        Jumlah penarikan
                    </div>

                    <div
                        class="summary-value"
                    >

                        <?php
                        echo $jumlah_penarikan;
                        ?>

                        kali

                    </div>

                </div>

            </div>

        </div>


    </div>


    <div class="notice">

        💡 <strong>Info:</strong>
        penarikan akan langsung tercatat
        sebagai penggunaan saldo berdasarkan
        sistem Bank Sampah Ataya saat ini.

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
                Riwayat Penarikan
            </div>

            <div class="card-subtitle">
                Aktivitas penarikan saldo terbaru
            </div>

        </div>

        <div
            style="
                color:#64748b;
                font-size:10px;
            "
        >
            <?php
            echo count($data_riwayat);
            ?>
            transaksi
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
                            Nominal
                        </th>

                        <th>
                            Keterangan
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach (
                    $data_riwayat
                    as $row
                ): ?>

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

                            <div class="money">

                                -
                                <?php
                                echo rupiah(
                                    $row['jumlah']
                                );
                                ?>

                            </div>

                        </td>


                        <td>

                            <div class="description">

                                <?php
                                echo htmlspecialchars(
                                    $row['keterangan']
                                );
                                ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">
                    💰
                </div>

                Belum ada riwayat penarikan.

            </div>

        <?php endif; ?>

    </div>

</div>


</section>

</main>


<script>

/*
|--------------------------------------------------------------------------
| ELEMENT
|--------------------------------------------------------------------------
*/

const jumlahInput =
    document.getElementById('jumlah');

const afterBalance =
    document.getElementById('afterBalance');

const submitBtn =
    document.getElementById('submitBtn');

const saldo =
    parseInt(
        document
            .getElementById('heroSaldo')
            .dataset.saldo || 0
    );


/*
|--------------------------------------------------------------------------
| FORMAT RUPIAH
|--------------------------------------------------------------------------
*/

function rupiah(angka) {

    return new Intl.NumberFormat(
        'id-ID',
        {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }
    ).format(
        Math.max(0, angka)
    );
}


/*
|--------------------------------------------------------------------------
| UPDATE SALDO
|--------------------------------------------------------------------------
*/

function updateSaldo() {

    let jumlah =
        parseInt(
            jumlahInput.value || 0
        );

    if (jumlah < 0) {
        jumlah = 0;
    }

    let saldoAkhir =
        saldo - jumlah;

    if (saldoAkhir < 0) {
        saldoAkhir = 0;
    }

    afterBalance.textContent =
        rupiah(saldoAkhir);


    /*
    |--------------------------------------------------------------------------
    | WARNING JIKA MELEBIHI SALDO
    |--------------------------------------------------------------------------
    */

    if (jumlah > saldo) {

        afterBalance.style.color =
            '#fb7185';

        submitBtn.disabled = true;

        submitBtn.textContent =
            '⚠️ Saldo Tidak Mencukupi';

    }

    else if (
        jumlah > 0 &&
        jumlah < 1000
    ) {

        afterBalance.style.color =
            '#fcd34d';

        submitBtn.disabled = true;

        submitBtn.textContent =
            '⚠️ Minimal Rp 1.000';

    }

    else {

        afterBalance.style.color =
            '#6ee7b7';

        submitBtn.disabled = false;

        submitBtn.textContent =
            '💸 Tarik Saldo Sekarang';
    }

}


/*
|--------------------------------------------------------------------------
| QUICK BUTTON
|--------------------------------------------------------------------------
*/

const quickButtons =
    document.querySelectorAll(
        '.quick-btn'
    );

quickButtons.forEach(
    function(button) {

        button.addEventListener(
            'click',
            function() {

                const value =
                    parseInt(
                        this.dataset.value
                    );

                jumlahInput.value =
                    value;

                quickButtons.forEach(
                    function(btn) {
                        btn.classList.remove(
                            'selected'
                        );
                    }
                );

                this.classList.add(
                    'selected'
                );

                updateSaldo();

            }
        );

    }
);


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

jumlahInput.addEventListener(
    'input',
    function() {

        quickButtons.forEach(
            function(btn) {

                if (
                    parseInt(
                        btn.dataset.value
                    ) ===
                    parseInt(
                        jumlahInput.value || 0
                    )
                ) {

                    btn.classList.add(
                        'selected'
                    );

                } else {

                    btn.classList.remove(
                        'selected'
                    );

                }

            }
        );

        updateSaldo();

    }
);


/*
|--------------------------------------------------------------------------
| SUBMIT CONFIRM
|--------------------------------------------------------------------------
*/

document
    .getElementById('formTarik')
    .addEventListener(
        'submit',
        function(event) {

            const jumlah =
                parseInt(
                    jumlahInput.value || 0
                );

            if (jumlah <= 0) {

                event.preventDefault();

                alert(
                    'Masukkan nominal penarikan.'
                );

                return;
            }

            if (jumlah < 1000) {

                event.preventDefault();

                alert(
                    'Minimal penarikan adalah Rp 1.000.'
                );

                return;
            }

            if (jumlah > saldo) {

                event.preventDefault();

                alert(
                    'Saldo tidak mencukupi.'
                );

                return;
            }


            const konfirmasi =
                confirm(
                    'Tarik ' +
                    rupiah(jumlah) +
                    ' dari saldo kamu?'
                );

            if (!konfirmasi) {

                event.preventDefault();

                return;
            }


            submitBtn.disabled =
                true;

            submitBtn.textContent =
                '⏳ Memproses...';

        }
    );


/*
|--------------------------------------------------------------------------
| CLOCK
|--------------------------------------------------------------------------
*/

function updateClock() {

    const now =
        new Date();

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
        date +
        ' • ' +
        time;
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

updateSaldo();

</script>

<script src="assets/siswa.js"></script>
</body>

</html>