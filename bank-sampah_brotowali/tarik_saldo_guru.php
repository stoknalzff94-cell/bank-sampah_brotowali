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
   PROSES PENARIKAN
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_nasabah = (int)($_POST['id_nasabah'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $jumlah = (int)($_POST['jumlah'] ?? 0);
    $keterangan = trim(
        $_POST['keterangan'] ?? ''
    );

    if ($id_nasabah <= 0) {

        $pesan = 'Nasabah wajib dipilih.';
        $tipe_pesan = 'error';

    } elseif ($jumlah <= 0) {

        $pesan = 'Jumlah penarikan harus lebih dari Rp 0.';
        $tipe_pesan = 'error';

    } elseif ($keterangan === '') {

        $pesan = 'Keterangan penarikan wajib diisi.';
        $tipe_pesan = 'error';

    } else {

        /* =================================================
           CEK NASABAH
        ================================================= */

        $stmt = mysqli_prepare($koneksi, "
            SELECT
                id_nasabah,
                nama
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

        $nasabah =
            mysqli_fetch_assoc($result_nasabah);

        mysqli_stmt_close($stmt);


        if (!$nasabah) {

            $pesan = 'Nasabah tidak ditemukan.';
            $tipe_pesan = 'error';

        } else {

            /* =============================================
               TOTAL SETORAN DISETUJUI
            ============================================= */

            $stmt = mysqli_prepare($koneksi, "
                SELECT
                    COALESCE(SUM(total), 0) AS total_setoran
                FROM transaksi
                WHERE id_nasabah = ?
                AND status = 'disetujui'
            ");

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
                (float)(
                    $data_setoran['total_setoran']
                    ?? 0
                );


            /* =============================================
               TOTAL PENARIKAN SEBELUMNYA

               PENTING:
               tarik_saldo TIDAK punya kolom status
            ============================================= */

            $stmt = mysqli_prepare($koneksi, "
                SELECT
                    COALESCE(SUM(jumlah), 0) AS total_tarik
                FROM tarik_saldo
                WHERE id_nasabah = ?
            ");

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
                (float)(
                    $data_tarik['total_tarik']
                    ?? 0
                );


            /* =============================================
               SALDO TERSEDIA
            ============================================= */

            $saldo_tersedia =
                $total_setoran -
                $total_tarik;


            if ($saldo_tersedia < 0) {
                $saldo_tersedia = 0;
            }


            /* =============================================
               VALIDASI JUMLAH PENARIKAN
            ============================================= */

            if ($jumlah > $saldo_tersedia) {

                $pesan =
                    'Penarikan ditolak. Saldo tersedia hanya ' .
                    rupiah($saldo_tersedia) .
                    '.';

                $tipe_pesan = 'error';

            } else {

                /* =========================================
                   INSERT PENARIKAN
                ========================================= */

                $stmt = mysqli_prepare($koneksi, "
                    INSERT INTO tarik_saldo
                    (
                        id_nasabah,
                        tanggal,
                        jumlah,
                        keterangan
                    )
                    VALUES
                    (?, ?, ?, ?)
                ");

                mysqli_stmt_bind_param(
                    $stmt,
                    "isis",
                    $id_nasabah,
                    $tanggal,
                    $jumlah,
                    $keterangan
                );

                if (mysqli_stmt_execute($stmt)) {

                    $pesan =
                        'Penarikan saldo berhasil dicatat sebesar ' .
                        rupiah($jumlah) .
                        '.';

                    $tipe_pesan = 'success';

                    $_POST = [];

                } else {

                    $pesan =
                        'Gagal menyimpan penarikan: ' .
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

$q = mysqli_query($koneksi, "
    SELECT
        id_nasabah,
        nama
    FROM nasabah
    ORDER BY nama ASC
");

while ($row = mysqli_fetch_assoc($q)) {
    $nasabah_list[] = $row;
}


/* =========================================================
   RINGKASAN SALDO PER NASABAH
========================================================= */

$saldo_nasabah = [];

$q = mysqli_query($koneksi, "
    SELECT
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

    ORDER BY n.nama ASC
");

while ($row = mysqli_fetch_assoc($q)) {

    $setoran =
        (float)$row['total_setoran'];

    $tarik =
        (float)$row['total_tarik'];

    $row['saldo'] =
        max(0, $setoran - $tarik);

    $saldo_nasabah[] = $row;
}


/* =========================================================
   TOTAL SISTEM
========================================================= */

$total_saldo_sistem = 0;

foreach ($saldo_nasabah as $data) {
    $total_saldo_sistem +=
        (float)$data['saldo'];
}


/* =========================================================
   TOTAL PENARIKAN
========================================================= */

$q = mysqli_query($koneksi, "
    SELECT
        COALESCE(SUM(jumlah), 0) AS total
    FROM tarik_saldo
");

$total_penarikan =
    (float)(
        mysqli_fetch_assoc($q)['total']
        ?? 0
    );


/* =========================================================
   TOTAL SETORAN DISETUJUI
========================================================= */

$q = mysqli_query($koneksi, "
    SELECT
        COALESCE(SUM(total), 0) AS total
    FROM transaksi
    WHERE status = 'disetujui'
");

$total_setoran_sistem =
    (float)(
        mysqli_fetch_assoc($q)['total']
        ?? 0
    );


/* =========================================================
   HISTORI PENARIKAN
========================================================= */

$histori = [];

$q = mysqli_query($koneksi, "
    SELECT
        ts.id_tarik,
        ts.tanggal,
        ts.jumlah,
        ts.keterangan,
        n.nama
    FROM tarik_saldo ts

    INNER JOIN nasabah n
        ON n.id_nasabah = ts.id_nasabah

    ORDER BY ts.id_tarik DESC

    LIMIT 10
");

while ($row = mysqli_fetch_assoc($q)) {
    $histori[] = $row;
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

<title>Tarik Saldo — Guru Ataya</title>

<style>

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
   HEADER
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

    max-width: 680px;

    color: #8997b1;

    font-size: 13px;

    line-height: 1.7;
}


/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

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

.stat-top {

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.stat-icon {

    width: 37px;
    height: 37px;

    display: grid;

    place-items: center;

    border-radius: 11px;

    background:
        rgba(99,102,241,.11);

    color: #a5b4fc;
}

.stat-label {

    margin-top: 15px;

    color: var(--muted);

    font-size: 10px;
}

.stat-value {

    margin-top: 4px;

    font-size: 22px;

    font-weight: 800;

    letter-spacing: -.7px;
}

.stat-meta {

    margin-top: 5px;

    color: #596780;

    font-size: 9px;
}


/* =========================================================
   CONTENT
========================================================= */

.content {

    display: grid;

    grid-template-columns:
        minmax(0, 1.15fr)
        minmax(300px, .85fr);

    gap: 18px;

    margin-top: 18px;
}

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

    overflow: hidden;

    box-shadow:
        0 14px 45px
        rgba(0,0,0,.16);
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

    padding: 22px;
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

.required {

    color: var(--danger);

    font-size: 9px;
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
        rgba(99,102,241,.10);
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
   BALANCE BOX
========================================================= */

.balance-box {

    margin-bottom: 20px;

    padding: 20px;

    border-radius: 17px;

    background:

        radial-gradient(
            circle at 90% 20%,
            rgba(56,189,248,.10),
            transparent 30%
        ),

        linear-gradient(
            135deg,
            rgba(99,102,241,.13),
            rgba(139,92,246,.07)
        );

    border:
        1px solid
        rgba(99,102,241,.17);
}

.balance-label {

    color: #7786a0;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: 1px;
}

.balance-name {

    margin-top: 6px;

    color: #dce5f4;

    font-size: 12px;

    font-weight: 750;
}

.balance-value {

    margin-top: 8px;

    background:
        linear-gradient(
            90deg,
            #a5b4fc,
            #67e8f9
        );

    -webkit-background-clip: text;

    background-clip: text;

    color: transparent;

    font-size: 30px;

    font-weight: 850;

    letter-spacing: -1px;
}


/* =========================================================
   CALCULATION
========================================================= */

.calculation {

    margin-top: 20px;

    padding: 18px;

    border-radius: 15px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(255,255,255,.055);
}

.calc-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding: 8px 0;

    color: #7d8ba4;

    font-size: 10px;
}

.calc-row strong {

    color: #dbe5f5;

    font-size: 11px;
}

.calc-after {

    margin-top: 8px;

    padding-top: 13px;

    border-top:
        1px solid
        rgba(255,255,255,.07);
}

.calc-after strong {

    color: #67e8f9;

    font-size: 16px;
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

    gap: 10px;

    margin-bottom: 18px;

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
   INFO LIST
========================================================= */

.info-list {

    display: flex;

    flex-direction: column;

    gap: 10px;
}

.info {

    display: flex;

    gap: 11px;

    padding: 13px;

    border-radius: 13px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(255,255,255,.045);
}

.info-icon {

    width: 34px;
    height: 34px;

    flex: 0 0 auto;

    display: grid;

    place-items: center;

    border-radius: 10px;

    background:
        rgba(99,102,241,.10);

    color: #a5b4fc;

    font-size: 14px;
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
   NASABAH BALANCE LIST
========================================================= */

.balance-list {

    margin-top: 18px;

    display: flex;

    flex-direction: column;

    gap: 8px;

    max-height: 340px;

    overflow-y: auto;
}

.balance-item {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 11px;

    border-radius: 12px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(255,255,255,.04);
}

.avatar {

    width: 30px;
    height: 30px;

    display: grid;

    place-items: center;

    flex: 0 0 auto;

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

.balance-info {

    flex: 1;

    min-width: 0;
}

.balance-person {

    overflow: hidden;

    white-space: nowrap;

    text-overflow: ellipsis;

    color: #dce5f4;

    font-size: 10px;

    font-weight: 700;
}

.balance-detail {

    margin-top: 3px;

    color: #596780;

    font-size: 8px;
}

.balance-amount {

    color: #67e8f9;

    font-size: 10px;

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

    min-width: 700px;

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

tbody tr:hover {

    background:
        rgba(99,102,241,.035);
}

.person {

    display: flex;

    align-items: center;

    gap: 10px;
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

@media (max-width: 1050px) {

    .content {

        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {

    .stats {

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
}

@media (max-width: 600px) {

    .main {

        padding: 15px;
    }

    .clock {

        display: none;
    }

    .page-header {

        padding: 23px;
    }

    .page-header h1 {

        font-size: 27px;
    }

    .card-body {

        padding: 18px;
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
                ✦ GURU FINANCE WORKSPACE
            </div>

            <h1>
                Tarik Saldo Nasabah
            </h1>

            <p>
                Catat penarikan saldo berdasarkan transaksi
                yang sudah disetujui. Sistem otomatis menghitung
                saldo tersedia setiap nasabah agar jumlah
                penarikan tidak melebihi saldo.
            </p>

        </div>

    </section>


    <!-- =================================================
         STATS
    ================================================== -->

    <section class="stats">


        <div class="stat">

            <div class="stat-top">

                <div class="stat-label">
                    Saldo Sistem
                </div>

                <div class="stat-icon">
                    ◈
                </div>

            </div>

            <div class="stat-value">
                <?= rupiah($total_saldo_sistem) ?>
            </div>

            <div class="stat-meta">
                Saldo tersedia seluruh nasabah
            </div>

        </div>


        <div class="stat">

            <div class="stat-top">

                <div class="stat-label">
                    Total Setoran
                </div>

                <div class="stat-icon">
                    ◇
                </div>

            </div>

            <div class="stat-value">
                <?= rupiah($total_setoran_sistem) ?>
            </div>

            <div class="stat-meta">
                Transaksi yang sudah disetujui
            </div>

        </div>


        <div class="stat">

            <div class="stat-top">

                <div class="stat-label">
                    Total Penarikan
                </div>

                <div class="stat-icon">
                    ↗
                </div>

            </div>

            <div class="stat-value">
                <?= rupiah($total_penarikan) ?>
            </div>

            <div class="stat-meta">
                Seluruh penarikan tercatat
            </div>

        </div>


    </section>


    <!-- =================================================
         CONTENT
    ================================================== -->

    <section class="content">


        <!-- FORM -->

        <div class="card">

            <div class="card-header">

                <div class="card-title">
                    Form Penarikan
                </div>

                <div class="card-subtitle">
                    Pilih nasabah dan masukkan nominal
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


                <div class="balance-box">

                    <div class="balance-label">
                        Saldo Nasabah Terpilih
                    </div>

                    <div
                        class="balance-name"
                        id="selectedName"
                    >
                        Belum dipilih
                    </div>

                    <div
                        class="balance-value"
                        id="selectedBalance"
                    >
                        Rp 0
                    </div>

                </div>


                <form
                    method="POST"
                    id="tarikForm"
                >


                    <!-- NASABAH -->

                    <div class="form-group">

                        <label class="form-label">

                            Nasabah

                            <span class="required">
                                * wajib
                            </span>

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
                                $saldo_nasabah
                                as $nasabah
                            ): ?>

                                <option
                                    value="<?= (int)$nasabah['id_nasabah'] ?>"
                                    data-nama="<?= htmlspecialchars(
                                        $nasabah['nama'],
                                        ENT_QUOTES
                                    ) ?>"
                                    data-saldo="<?= (float)$nasabah['saldo'] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $nasabah['nama']
                                    ) ?>

                                    —
                                    <?= rupiah(
                                        $nasabah['saldo']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="input-hint">

                            Saldo dihitung dari total transaksi
                            berstatus disetujui dikurangi seluruh
                            penarikan sebelumnya.

                        </div>

                    </div>


                    <!-- TANGGAL -->

                    <div class="form-group">

                        <label class="form-label">

                            Tanggal Penarikan

                            <span class="required">
                                * wajib
                            </span>

                        </label>


                        <input
                            type="date"
                            name="tanggal"
                            class="form-control"
                            value="<?= htmlspecialchars(
                                $_POST['tanggal']
                                ?? date('Y-m-d')
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- JUMLAH -->

                    <div class="form-group">

                        <label class="form-label">

                            Jumlah Penarikan

                            <span class="required">
                                * wajib
                            </span>

                        </label>


                        <input
                            type="number"
                            name="jumlah"
                            id="jumlah"
                            class="form-control"
                            min="1"
                            step="1"
                            placeholder="Contoh: 50000"
                            value="<?= htmlspecialchars(
                                $_POST['jumlah'] ?? ''
                            ) ?>"
                            required
                        >


                        <div class="input-hint">
                            Jumlah tidak boleh melebihi saldo tersedia.
                        </div>

                    </div>


                    <!-- KETERANGAN -->

                    <div class="form-group">

                        <label class="form-label">

                            Keterangan

                            <span class="required">
                                * wajib
                            </span>

                        </label>


                        <textarea
                            name="keterangan"
                            class="form-control"
                            placeholder="Contoh: Penarikan saldo nasabah..."
                            required
                        ><?= htmlspecialchars(
                            $_POST['keterangan'] ?? ''
                        ) ?></textarea>

                    </div>


                    <!-- CALCULATION -->

                    <div class="calculation">

                        <div class="calc-row">

                            <span>
                                Saldo sebelum tarik
                            </span>

                            <strong id="beforeBalance">
                                Rp 0
                            </strong>

                        </div>


                        <div class="calc-row">

                            <span>
                                Jumlah penarikan
                            </span>

                            <strong id="withdrawAmount">
                                Rp 0
                            </strong>

                        </div>


                        <div class="calc-row calc-after">

                            <span>
                                Saldo setelah tarik
                            </span>

                            <strong id="afterBalance">
                                Rp 0
                            </strong>

                        </div>

                    </div>


                    <button
                        type="submit"
                        class="btn-submit"
                        id="submitButton"
                    >

                        ↗
                        &nbsp;
                        Simpan Penarikan

                    </button>


                </form>

            </div>

        </div>


        <!-- INFO -->

        <div class="card">

            <div class="card-header">

                <div class="card-title">
                    Saldo Nasabah
                </div>

                <div class="card-subtitle">
                    Ringkasan saldo yang tersedia
                </div>

            </div>


            <div class="card-body">


                <div class="info-list">


                    <div class="info">

                        <div class="info-icon">
                            ✓
                        </div>

                        <div>

                            <div class="info-title">
                                Hanya Setoran Disetujui
                            </div>

                            <div class="info-text">
                                Transaksi pending dan ditolak
                                tidak dihitung sebagai saldo.
                            </div>

                        </div>

                    </div>


                    <div class="info">

                        <div class="info-icon">
                            −
                        </div>

                        <div>

                            <div class="info-title">
                                Penarikan Otomatis Dikurangi
                            </div>

                            <div class="info-text">
                                Semua penarikan sebelumnya
                                mengurangi saldo nasabah.
                            </div>

                        </div>

                    </div>


                    <div class="info">

                        <div class="info-icon">
                            !
                        </div>

                        <div>

                            <div class="info-title">
                                Tidak Bisa Minus
                            </div>

                            <div class="info-text">
                                Sistem menolak penarikan yang
                                melebihi saldo tersedia.
                            </div>

                        </div>

                    </div>

                </div>


                <div class="balance-list">

                    <?php if (
                        count($saldo_nasabah) > 0
                    ): ?>

                        <?php foreach (
                            $saldo_nasabah
                            as $data
                        ): ?>

                            <?php

                            $nama =
                                $data['nama']
                                ?? 'Tanpa Nama';

                            $inisial =
                                strtoupper(
                                    substr(
                                        trim($nama),
                                        0,
                                        1
                                    )
                                );

                            ?>

                            <div class="balance-item">

                                <div class="avatar">

                                    <?= htmlspecialchars(
                                        $inisial
                                    ) ?>

                                </div>


                                <div class="balance-info">

                                    <div class="balance-person">

                                        <?= htmlspecialchars(
                                            $nama
                                        ) ?>

                                    </div>

                                    <div class="balance-detail">

                                        Setoran:
                                        <?= rupiah(
                                            $data['total_setoran']
                                        ) ?>

                                        • Tarik:
                                        <?= rupiah(
                                            $data['total_tarik']
                                        ) ?>

                                    </div>

                                </div>


                                <div class="balance-amount">

                                    <?= rupiah(
                                        $data['saldo']
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div
                            style="
                                padding:25px;
                                text-align:center;
                                color:#596780;
                                font-size:10px;
                            "
                        >
                            Belum ada nasabah.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </section>


    <!-- =================================================
         HISTORY
    ================================================== -->

    <section class="card recent">

        <div class="card-header">

            <div class="card-title">
                Riwayat Penarikan
            </div>

            <div class="card-subtitle">
                10 transaksi penarikan terakhir
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
                            Tanggal
                        </th>

                        <th>
                            Jumlah
                        </th>

                        <th>
                            Keterangan
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    count($histori) > 0
                ): ?>


                    <?php foreach (
                        $histori
                        as $row
                    ): ?>


                        <?php

                        $nama =
                            $row['nama']
                            ?? 'Tanpa Nama';

                        $inisial =
                            strtoupper(
                                substr(
                                    trim($nama),
                                    0,
                                    1
                                )
                            );

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

                                            #<?= (int)$row['id_tarik'] ?>

                                        </div>

                                    </div>

                                </div>

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

                                <strong
                                    style="
                                        color:#67e8f9;
                                    "
                                >

                                    <?= rupiah(
                                        $row['jumlah']
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $row['keterangan']
                                ) ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="4"
                            style="
                                text-align:center;
                                padding:40px;
                                color:#596780;
                            "
                        >

                            Belum ada riwayat penarikan.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </section>


    <div class="footer">

        Bank Sampah Ataya • Guru Finance Workspace •
        <span id="year"></span>

    </div>


</main>


<script>

/* =========================================================
   CLOCK
========================================================= */

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
   ELEMENT
========================================================= */

const nasabahSelect =
    document.getElementById(
        'id_nasabah'
    );

const jumlahInput =
    document.getElementById(
        'jumlah'
    );

const selectedName =
    document.getElementById(
        'selectedName'
    );

const selectedBalance =
    document.getElementById(
        'selectedBalance'
    );

const beforeBalance =
    document.getElementById(
        'beforeBalance'
    );

const withdrawAmount =
    document.getElementById(
        'withdrawAmount'
    );

const afterBalance =
    document.getElementById(
        'afterBalance'
    );

const submitButton =
    document.getElementById(
        'submitButton'
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
   UPDATE SALDO
========================================================= */

function updateBalance() {

    const option =
        nasabahSelect.options[
            nasabahSelect.selectedIndex
        ];


    if (
        !option ||
        !option.value
    ) {

        selectedName.textContent =
            'Belum dipilih';

        selectedBalance.textContent =
            'Rp 0';

        beforeBalance.textContent =
            'Rp 0';

        withdrawAmount.textContent =
            'Rp 0';

        afterBalance.textContent =
            'Rp 0';

        submitButton.disabled = false;

        return;
    }


    const nama =
        option.dataset.nama ||
        'Nasabah';


    const saldo =
        Number(
            option.dataset.saldo || 0
        );


    const jumlah =
        Number(
            jumlahInput.value || 0
        );


    const setelah =
        saldo - jumlah;


    selectedName.textContent =
        nama;


    selectedBalance.textContent =
        formatRupiah(saldo);


    beforeBalance.textContent =
        formatRupiah(saldo);


    withdrawAmount.textContent =
        formatRupiah(jumlah);


    afterBalance.textContent =
        formatRupiah(
            Math.max(0, setelah)
        );


    if (jumlah > saldo) {

        afterBalance.style.color =
            '#fb7185';

        submitButton.disabled =
            true;

        submitButton.style.opacity =
            '.45';

        submitButton.style.cursor =
            'not-allowed';

    } else {

        afterBalance.style.color =
            '#67e8f9';

        submitButton.disabled =
            false;

        submitButton.style.opacity =
            '1';

        submitButton.style.cursor =
            'pointer';
    }
}


nasabahSelect.addEventListener(
    'change',
    updateBalance
);

jumlahInput.addEventListener(
    'input',
    updateBalance
);

updateBalance();


/* =========================================================
   CONFIRM SUBMIT
========================================================= */

document.getElementById(
    'tarikForm'
).addEventListener(
    'submit',
    function(e) {

        const option =
            nasabahSelect.options[
                nasabahSelect.selectedIndex
            ];

        const saldo =
            Number(
                option?.dataset.saldo || 0
            );

        const jumlah =
            Number(
                jumlahInput.value || 0
            );


        if (jumlah <= 0) {

            e.preventDefault();

            alert(
                'Jumlah penarikan harus lebih dari Rp 0.'
            );

            return;
        }


        if (jumlah > saldo) {

            e.preventDefault();

            alert(
                'Jumlah penarikan melebihi saldo tersedia.'
            );

            return;
        }


        const nama =
            option.dataset.nama;


        const yakin =
            confirm(
                'Catat penarikan ' +
                formatRupiah(jumlah) +
                ' untuk ' +
                nama +
                '?'
            );


        if (!yakin) {

            e.preventDefault();
        }

    }
);

</script>

<script src="assets/guru.js"></script>
</body>

</html>