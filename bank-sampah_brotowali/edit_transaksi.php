<?php
session_start();

if (!isset($_SESSION['id_user']) || !in_array($_SESSION['role'] ?? '', ['admin'], true)) {
    header("Location: login.php");
    exit;
}

include "koneksi.php";

if (!isset($_GET['id'])) {
    header("Location: transaksi.php");
    exit;
}

$id = (int) $_GET['id'];

/* Ambil data transaksi */
$query = mysqli_query(
    $koneksi,
    "SELECT * FROM transaksi WHERE id_transaksi = $id"
);

$data = mysqli_fetch_assoc($query);

if (!$data) {
    die("Data transaksi tidak ditemukan.");
}

/* Ambil data nasabah */
$nasabah = mysqli_query(
    $koneksi,
    "SELECT * FROM nasabah ORDER BY nama ASC"
);

/* Ambil data sampah */
$sampah = mysqli_query(
    $koneksi,
    "SELECT * FROM sampah ORDER BY nama_sampah ASC"
);

/* Proses update */
if (isset($_POST['update'])) {

    $id_nasabah = (int) $_POST['id_nasabah'];
    $id_sampah = (int) $_POST['id_sampah'];
    $tanggal = $_POST['tanggal'];
    $berat = (float) $_POST['berat'];

    /* Ambil harga sampah terbaru */
    $harga_query = mysqli_query(
        $koneksi,
        "SELECT harga FROM sampah WHERE id_sampah = $id_sampah"
    );

    $harga_data = mysqli_fetch_assoc($harga_query);

    if (!$harga_data) {
        die("Jenis sampah tidak ditemukan.");
    }

    $harga = (int) $harga_data['harga'];

    /* Hitung total */
    $total = $berat * $harga;

    /* Update transaksi */
    mysqli_query(
        $koneksi,
        "UPDATE transaksi SET
            id_nasabah = $id_nasabah,
            id_sampah = $id_sampah,
            tanggal = '$tanggal',
            berat = $berat,
            harga = $harga,
            total = $total
         WHERE id_transaksi = $id"
    );

    header("Location: transaksi.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Transaksi</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 30px;
        }

        .container {
            max-width: 550px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h2 {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
        }

        select,
        input {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            margin-top: 20px;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            background: #28a745;
            color: white;
            cursor: pointer;
        }

        .batal {
            display: inline-block;
            margin-left: 8px;
            padding: 10px 15px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .info {
            margin-top: 15px;
            padding: 10px;
            background: #f1f1f1;
            border-radius: 5px;
        }
    </style>
<link rel="stylesheet" href="assets/admin-theme.css">
</head>

<body>
<?php include __DIR__ . '/admin_sidebar.php'; ?>
<div class="main">
<?php $tb_judul = 'Edit Transaksi'; $tb_sub = 'Perbarui data transaksi setoran'; include __DIR__ . '/admin_topbar.php'; ?>
<section class="content">

<div class="container">

    <h2>✏️ Edit Transaksi</h2>

    <form method="POST">

        <label>Nasabah</label>

        <select name="id_nasabah" required>

            <?php while ($n = mysqli_fetch_assoc($nasabah)) : ?>

                <option
                    value="<?= $n['id_nasabah']; ?>"
                    <?= ($n['id_nasabah'] == $data['id_nasabah']) ? 'selected' : ''; ?>
                >
                    <?= htmlspecialchars($n['nama']); ?>
                </option>

            <?php endwhile; ?>

        </select>


        <label>Jenis Sampah</label>

        <select name="id_sampah" id="id_sampah" required onchange="hitungTotal()">

            <?php while ($s = mysqli_fetch_assoc($sampah)) : ?>

                <option
                    value="<?= $s['id_sampah']; ?>"
                    data-harga="<?= $s['harga']; ?>"
                    <?= ($s['id_sampah'] == $data['id_sampah']) ? 'selected' : ''; ?>
                >
                    <?= htmlspecialchars($s['nama_sampah']); ?>
                </option>

            <?php endwhile; ?>

        </select>


        <label>Tanggal</label>

        <input
            type="date"
            name="tanggal"
            value="<?= $data['tanggal']; ?>"
            required
        >


        <label>Berat (kg)</label>

        <input
            type="number"
            name="berat"
            id="berat"
            step="0.01"
            min="0"
            value="<?= $data['berat']; ?>"
            required
            oninput="hitungTotal()"
        >


        <div class="info">
            Harga: <strong id="hargaTampil">Rp0</strong>
            <br><br>
            Total: <strong id="totalTampil">Rp0</strong>
        </div>


        <button type="submit" name="update">
            Simpan Perubahan
        </button>

        <a href="transaksi.php" class="batal">
            Batal
        </a>

    </form>

</div>


<script>

function hitungTotal() {

    let select = document.getElementById("id_sampah");

    let option = select.options[select.selectedIndex];

    let harga = parseInt(option.getAttribute("data-harga")) || 0;

    let berat = parseFloat(
        document.getElementById("berat").value
    ) || 0;

    let total = harga * berat;

    document.getElementById("hargaTampil").innerText =
        "Rp" + harga.toLocaleString("id-ID");

    document.getElementById("totalTampil").innerText =
        "Rp" + total.toLocaleString("id-ID");
}

hitungTotal();

</script>

</section>
</div>
</body>
</html>