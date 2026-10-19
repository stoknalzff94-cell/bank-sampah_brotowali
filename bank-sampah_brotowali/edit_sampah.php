<?php
session_start();
include "koneksi.php";

/* =========================
   CEK LOGIN ADMIN
========================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak. Halaman ini khusus admin.");
}

/* =========================
   CEK ID
========================= */
if (!isset($_GET['id'])) {
    header("Location: sampah.php");
    exit;
}

$id = (int) $_GET['id'];

/* =========================
   AMBIL DATA
========================= */
$query = mysqli_query(
    $koneksi,
    "SELECT *
     FROM sampah
     WHERE id_sampah = $id
     LIMIT 1"
);

if (!$query || mysqli_num_rows($query) == 0) {
    die("Data jenis sampah tidak ditemukan.");
}

$data = mysqli_fetch_assoc($query);

$error = "";

/* =========================
   PROSES UPDATE
========================= */
if (isset($_POST['update'])) {

    $nama_sampah = mysqli_real_escape_string(
        $koneksi,
        $_POST['nama_sampah']
    );

    $harga = (int) $_POST['harga'];

    $satuan = mysqli_real_escape_string(
        $koneksi,
        $_POST['satuan']
    );

    if ($nama_sampah == "" || $harga <= 0 || $satuan == "") {

        $error = "Semua data harus diisi dengan benar.";

    } else {

        /* cek nama tidak bentrok dengan data lain */
        $cek = mysqli_query(
            $koneksi,
            "SELECT *
             FROM sampah
             WHERE nama_sampah = '$nama_sampah'
             AND id_sampah != $id
             LIMIT 1"
        );

        if (mysqli_num_rows($cek) > 0) {

            $error = "Nama jenis sampah tersebut sudah digunakan.";

        } else {

            $update = mysqli_query(
                $koneksi,
                "UPDATE sampah
                 SET
                    nama_sampah = '$nama_sampah',
                    harga = $harga,
                    satuan = '$satuan'
                 WHERE id_sampah = $id"
            );

            if ($update) {

                header("Location: sampah.php?status=edit");
                exit;

            } else {

                $error = "Gagal memperbarui data: " . mysqli_error($koneksi);

            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Sampah - Bank Sampah</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7f5;
    color: #263238;
}

/* SIDEBAR */

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100vh;
    background: linear-gradient(180deg, #087f5b, #075e45);
    color: white;
    padding: 25px 15px;
}

.logo {
    text-align: center;
    margin-bottom: 30px;
}

.logo .icon {
    font-size: 42px;
}

.logo h2 {
    margin: 8px 0 3px;
    font-size: 20px;
}

.logo p {
    margin: 0;
    font-size: 12px;
    opacity: .8;
}

.menu {
    margin-top: 20px;
}

.menu a {
    display: block;
    color: white;
    text-decoration: none;
    padding: 13px 15px;
    margin-bottom: 6px;
    border-radius: 10px;
    font-size: 14px;
}

.menu a:hover {
    background: rgba(255,255,255,.15);
}

.menu a.active {
    background: rgba(255,255,255,.20);
    font-weight: bold;
}

.menu .logout {
    margin-top: 25px;
    background: rgba(220,53,69,.85);
}

/* MAIN */

.main {
    margin-left: 250px;
    min-height: 100vh;
}

/* TOPBAR */

.topbar {
    height: 70px;
    background: white;
    border-bottom: 1px solid #e4e9e6;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
}

.topbar h3 {
    margin: 0;
    color: #087f5b;
}

.profile {
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-icon {
    width: 38px;
    height: 38px;
    background: #dff5ec;
    color: #087f5b;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

/* CONTENT */

.content {
    padding: 30px;
}

.page-title {
    margin-bottom: 25px;
}

.page-title h1 {
    margin: 0 0 7px;
    font-size: 27px;
}

.page-title p {
    margin: 0;
    color: #78909c;
}

/* FORM CARD */

.form-card {
    max-width: 650px;
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,.06);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: bold;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 13px;
    border: 1px solid #d7dfda;
    border-radius: 9px;
    outline: none;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #087f5b;
}

.form-help {
    margin-top: 6px;
    color: #90a4ae;
    font-size: 12px;
}

/* ALERT */

.alert {
    max-width: 650px;
    padding: 14px 17px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.alert.error {
    background: #fde5e7;
    color: #a51d2d;
    border: 1px solid #f5c2c7;
}

/* BUTTON */

.buttons {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.btn {
    border: none;
    border-radius: 9px;
    padding: 12px 18px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    display: inline-block;
}

.btn-save {
    background: #087f5b;
    color: white;
}

.btn-back {
    background: #e9eeee;
    color: #455a64;
}

.btn:hover {
    opacity: .9;
}

/* FOOTER */

.footer {
    text-align: center;
    padding: 25px;
    color: #90a4ae;
    font-size: 12px;
}

/* MOBILE */

@media (max-width: 700px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
    }

    .topbar {
        padding: 0 18px;
    }

    .content {
        padding: 18px;
    }

    .form-card {
        padding: 20px;
    }

    .buttons {
        flex-direction: column;
    }

    .buttons .btn {
        width: 100%;
        text-align: center;
    }
}

</style>

<link rel="stylesheet" href="assets/admin-theme.css">
</head>

<body>

<!-- SIDEBAR -->

<?php include __DIR__ . '/admin_sidebar.php'; ?>


<!-- MAIN -->

<main class="main">

    <?php $tb_judul = 'Edit Jenis Sampah'; $tb_sub = 'Perbarui data jenis sampah'; include __DIR__ . '/admin_topbar.php'; ?>


    <div class="content">

        <div class="page-title">

            <h1>✏️ Edit Jenis Sampah</h1>

            <p>
                Perbarui informasi jenis sampah dan harga pembelian.
            </p>

        </div>


        <?php if ($error != "") { ?>

            <div class="alert error">

                ⚠️ <?= htmlspecialchars($error); ?>

            </div>

        <?php } ?>


        <div class="form-card">

            <form method="POST">

                <div class="form-group">

                    <label>
                        Nama Sampah
                    </label>

                    <input
                        type="text"
                        name="nama_sampah"
                        value="<?= htmlspecialchars($data['nama_sampah']); ?>"
                        placeholder="Contoh: Kardus"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Harga
                    </label>

                    <input
                        type="number"
                        name="harga"
                        value="<?= $data['harga']; ?>"
                        min="1"
                        required
                    >

                    <div class="form-help">
                        Masukkan harga pembelian per satuan.
                    </div>

                </div>


                <div class="form-group">

                    <label>
                        Satuan
                    </label>

                    <select
                        name="satuan"
                        required
                    >

                        <option value="">
                            -- Pilih Satuan --
                        </option>

                        <option
                            value="kg"
                            <?= $data['satuan'] == 'kg' ? 'selected' : ''; ?>
                        >
                            Kilogram (kg)
                        </option>

                        <option
                            value="pcs"
                            <?= $data['satuan'] == 'pcs' ? 'selected' : ''; ?>
                        >
                            Pieces (pcs)
                        </option>

                        <option
                            value="botol"
                            <?= $data['satuan'] == 'botol' ? 'selected' : ''; ?>
                        >
                            Botol
                        </option>

                        <option
                            value="liter"
                            <?= $data['satuan'] == 'liter' ? 'selected' : ''; ?>
                        >
                            Liter
                        </option>

                    </select>

                </div>


                <div class="buttons">

                    <button
                        type="submit"
                        name="update"
                        class="btn btn-save"
                    >
                        💾 Simpan Perubahan
                    </button>

                    <a
                        href="sampah.php"
                        class="btn btn-back"
                    >
                        ← Kembali
                    </a>

                </div>

            </form>

        </div>


        <div class="footer">

            © <?= date('Y'); ?> Bank Sampah —
            Sistem Informasi Bank Sampah

        </div>

    </div>

</main>

</body>
</html>