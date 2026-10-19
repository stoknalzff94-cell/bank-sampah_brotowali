<?php
session_start();
include "koneksi.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak. Halaman ini khusus admin.");
}

$nama_admin = $_SESSION['nama_user'] ?? 'Administrator';

function e($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/* =========================
   VALIDASI ID
========================= */

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    die("ID nasabah tidak valid.");
}

/* =========================
   AMBIL DATA NASABAH
========================= */

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT * FROM nasabah WHERE id_nasabah = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($hasil);

mysqli_stmt_close($stmt);

if (!$data) {
    die("Data nasabah tidak ditemukan.");
}

/* =========================
   PROSES UPDATE
========================= */

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {

    $nama = trim($_POST['nama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $tanggal_daftar = $_POST['tanggal_daftar'] ?? '';

    if ($nama === '' || $alamat === '' || $tanggal_daftar === '') {

        $error = "Nama, alamat, dan tanggal daftar wajib diisi.";

    } else {

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE nasabah SET
                nama = ?,
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
            $id
        );

        if (mysqli_stmt_execute($stmt)) {

            mysqli_stmt_close($stmt);

            header("Location: nasabah.php?status=edit");
            exit;

        } else {

            $error = "Gagal memperbarui data nasabah.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Nasabah - Bank Sampah</title>

<style>

/* =========================
   RESET
========================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #070d1d;
    color: #e8edff;
}

a {
    text-decoration: none;
}

button,
input,
textarea {
    font-family: inherit;
}

/* =========================
   LAYOUT
========================= */

.layout {
    display: flex;
    min-height: 100vh;
}

/* =========================
   SIDEBAR
========================= */

.sidebar {
    width: 235px;
    min-width: 235px;

    position: fixed;
    left: 17px;
    top: 17px;
    bottom: 17px;

    padding: 22px 15px;

    border: 1px solid #26365e;
    border-radius: 23px;

    background: linear-gradient(180deg, #151e3b, #0d142b);

    box-shadow: 0 15px 45px rgba(0,0,0,.25);

    display: flex;
    flex-direction: column;

    z-index: 10;
}

/* LOGO */

.logo {
    display: flex;
    align-items: center;
    gap: 11px;

    padding: 7px 8px 22px;

    border-bottom: 1px solid #293556;
}

.logo-icon {
    width: 43px;
    height: 43px;

    display: flex;
    justify-content: center;
    align-items: center;

    border-radius: 14px;

    background: linear-gradient(135deg, #55d7ff, #7883ff);

    font-size: 23px;
    color: white;
}

.logo-text strong {
    display: block;
    font-size: 14px;
    color: white;
}

.logo-text span {
    display: block;
    margin-top: 4px;

    color: #8b9bc4;
    font-size: 9px;
    letter-spacing: 1px;
}

/* MENU */

.menu-label {
    padding: 24px 10px 10px;

    color: #687ca9;
    font-size: 9px;
    font-weight: bold;
    letter-spacing: 1.5px;
}

.menu {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.menu a {
    display: flex;
    align-items: center;
    gap: 11px;

    padding: 12px;

    border-radius: 12px;

    color: #aab8d9;
    font-size: 12px;

    transition: .2s;
}

.menu a:hover {
    background: #202d50;
    color: white;
    transform: translateX(2px);
}

.menu a.active {
    color: white;

    background: linear-gradient(90deg, #263e70, #30315e);

    border: 1px solid #38548b;

    box-shadow: 0 5px 20px rgba(36,71,150,.2);
}

.menu-icon {
    width: 25px;
    height: 25px;

    display: flex;
    justify-content: center;
    align-items: center;

    border-radius: 8px;

    background: rgba(255,255,255,.05);
}

/* SIDEBAR BOTTOM */

.sidebar-bottom {
    margin-top: auto;

    padding-top: 15px;

    border-top: 1px solid #293556;
}

.admin-mini {
    display: flex;
    align-items: center;
    gap: 9px;

    padding: 10px;
    margin-bottom: 8px;

    border-radius: 12px;

    background: rgba(255,255,255,.04);
}

.admin-avatar,
.top-avatar {
    display: flex;
    justify-content: center;
    align-items: center;

    border-radius: 50%;

    background: linear-gradient(135deg, #52cfff, #8276ff);

    color: white;
    font-weight: bold;
}

.admin-avatar {
    width: 33px;
    height: 33px;
    font-size: 12px;
}

.admin-info small {
    display: block;
    font-size: 8px;
    color: #7385ae;
}

.admin-info strong {
    display: block;

    max-width: 125px;

    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;

    margin-top: 3px;

    font-size: 10px;
    color: white;
}

.logout {
    display: flex;
    align-items: center;
    gap: 10px;

    padding: 10px 12px;

    border-radius: 10px;

    color: #9eafd4;
    font-size: 11px;
}

.logout:hover {
    background: #202d50;
    color: white;
}

/* =========================
   MAIN
========================= */

.main {
    width: calc(100% - 269px);
    margin-left: 269px;
    min-height: 100vh;
}

.topbar {
    height: 80px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    padding: 0 34px;

    border-bottom: 1px solid #1c2947;

    background: #080e20;
}

.top-title strong {
    display: block;
    color: white;
    font-size: 16px;
}

.top-title span {
    display: block;

    margin-top: 5px;

    color: #7184b3;
    font-size: 10px;
}

.top-user {
    display: flex;
    align-items: center;
    gap: 9px;
}

.top-avatar {
    width: 35px;
    height: 35px;
    font-size: 12px;
}

.top-user span {
    color: #a4b3d7;
    font-size: 11px;
}

/* =========================
   CONTENT
========================= */

.content {
    max-width: 900px;

    padding: 32px 34px 45px;
}

.page-title small {
    color: #7184b3;

    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.page-title h1 {
    margin-top: 7px;

    color: white;
    font-size: 29px;
}

.page-title p {
    margin-top: 7px;

    color: #7f91bd;
    font-size: 12px;
}

/* =========================
   ALERT
========================= */

.alert {
    margin-top: 22px;
    margin-bottom: 18px;

    padding: 13px 16px;

    border-radius: 11px;

    background: #421f30;
    border: 1px solid #75364d;

    color: #ff9ab6;
    font-size: 11px;
    font-weight: bold;
}

/* =========================
   FORM CARD
========================= */

.form-card {
    margin-top: 25px;

    padding: 28px;

    border: 1px solid #26365c;
    border-radius: 20px;

    background: linear-gradient(145deg, #172342, #10182e);

    box-shadow: 0 10px 35px rgba(0,0,0,.2);
}

.form-header {
    display: flex;
    align-items: center;
    gap: 13px;

    margin-bottom: 26px;
    padding-bottom: 20px;

    border-bottom: 1px solid #293958;
}

.form-icon {
    width: 45px;
    height: 45px;

    display: flex;
    justify-content: center;
    align-items: center;

    border-radius: 13px;

    background: #282b5b;

    color: #a99aff;
    font-size: 21px;
}

.form-header h2 {
    color: white;
    font-size: 18px;
}

.form-header p {
    margin-top: 5px;

    color: #7184b3;
    font-size: 10px;
}

/* FORM */

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;

    margin-bottom: 8px;

    color: #a6b8df;
    font-size: 10px;
    font-weight: bold;
}

.form-group input,
.form-group textarea {
    width: 100%;

    padding: 13px;

    border: 1px solid #304368;
    border-radius: 10px;

    outline: none;

    background: #0b1429;

    color: white;
    font-size: 11px;

    transition: .2s;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: #657be0;

    box-shadow: 0 0 0 3px rgba(101,123,224,.1);
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #596d99;
}

/* BUTTON */

.form-actions {
    display: flex;
    gap: 10px;

    margin-top: 25px;
    padding-top: 20px;

    border-top: 1px solid #293958;
}

.submit-btn,
.cancel-btn {
    padding: 13px 18px;

    border-radius: 10px;

    font-size: 11px;
    font-weight: bold;

    cursor: pointer;
    transition: .2s;
}

.submit-btn {
    border: 1px solid #4b62bd;

    background: linear-gradient(135deg, #405bb4, #6246a5);

    color: white;
}

.cancel-btn {
    border: 1px solid #35486e;

    background: #202d4d;

    color: #b4c4e8;
}

.submit-btn:hover,
.cancel-btn:hover {
    transform: translateY(-2px);
    opacity: .85;
}

/* =========================
   FOOTER
========================= */

footer {
    padding: 20px;

    text-align: center;

    color: #52678f;
    font-size: 9px;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 950px) {

    .sidebar {
        width: 205px;
        min-width: 205px;
    }

    .main {
        width: calc(100% - 229px);
        margin-left: 229px;
    }
}

@media (max-width: 700px) {

    .layout {
        display: block;
    }

    .sidebar {
        position: relative;

        width: 100%;
        min-width: 0;

        left: 0;
        top: 0;
        bottom: auto;

        border-radius: 0;
    }

    .main {
        width: 100%;
        margin-left: 0;
    }

    .topbar {
        padding: 0 18px;
    }

    .top-user span {
        display: none;
    }

    .content {
        padding: 24px 18px 40px;
    }

    .form-card {
        padding: 20px;
    }

    .form-actions {
        flex-direction: column;
    }

    .submit-btn,
    .cancel-btn {
        width: 100%;
        text-align: center;
    }
}

</style>

<link rel="stylesheet" href="assets/admin-theme.css">
</head>

<body>

<div class="layout">

<!-- =========================
     SIDEBAR
========================= -->

<?php include __DIR__ . '/admin_sidebar.php'; ?>

<!-- =========================
     MAIN
========================= -->

<main class="main">

    <?php $tb_judul = 'Edit Nasabah'; $tb_sub = 'Perbarui informasi anggota Bank Sampah'; include __DIR__ . '/admin_topbar.php'; ?>

    <div class="content">

        <div class="page-title">

            <small>Management / Nasabah / Edit</small>

            <h1>Edit Nasabah</h1>

            <p>Perbarui data anggota yang dipilih.</p>

        </div>

        <?php if ($error !== ''): ?>

            <div class="alert">
                ⚠ <?= e($error); ?>
            </div>

        <?php endif; ?>

        <section class="form-card">

            <div class="form-header">

                <div class="form-icon">
                    ✎
                </div>

                <div>
                    <h2>Informasi Nasabah</h2>

                    <p>
                        ID Nasabah #<?= e($data['id_nasabah']); ?>
                    </p>
                </div>

            </div>

            <form method="POST">

                <div class="form-group">

                    <label for="nama">
                        Nama Nasabah
                    </label>

                    <input
                        type="text"
                        id="nama"
                        name="nama"
                        value="<?= e($data['nama']); ?>"
                        maxlength="100"
                        placeholder="Masukkan nama lengkap"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="alamat">
                        Alamat
                    </label>

                    <textarea
                        id="alamat"
                        name="alamat"
                        maxlength="255"
                        placeholder="Masukkan alamat"
                        required
                    ><?= e($data['alamat']); ?></textarea>

                </div>

                <div class="form-group">

                    <label for="no_hp">
                        Nomor HP
                    </label>

                    <input
                        type="text"
                        id="no_hp"
                        name="no_hp"
                        value="<?= e($data['no_hp']); ?>"
                        maxlength="20"
                        placeholder="Contoh: 081234567890"
                    >

                </div>

                <div class="form-group">

                    <label for="tanggal_daftar">
                        Tanggal Daftar
                    </label>

                    <input
                        type="date"
                        id="tanggal_daftar"
                        name="tanggal_daftar"
                        value="<?= e($data['tanggal_daftar']); ?>"
                        required
                    >

                </div>

                <div class="form-actions">

                    <button
                        type="submit"
                        name="update"
                        class="submit-btn"
                    >
                        ✓ Simpan Perubahan
                    </button>

                    <a
                        href="nasabah.php"
                        class="cancel-btn"
                    >
                        ← Kembali
                    </a>

                </div>

            </form>

        </section>

    </div>

    <footer>
        © <?= date('Y'); ?>
        Bank Sampah · Sistem Informasi Bank Sampah
    </footer>

</main>

</div>

</body>
</html>