<?php
session_start();

if (!isset($_SESSION['id_user']) || !in_array($_SESSION['role'] ?? '', ['admin'], true)) {
    header("Location: login.php");
    exit;
}

include "koneksi.php";

if (!isset($_GET['id'])) {
    header("Location: nasabah.php");
    exit;
}

$id = (int) $_GET['id'];

/* Ambil data nasabah */
$query_nasabah = mysqli_query(
    $koneksi,
    "SELECT * FROM nasabah WHERE id_nasabah = $id"
);

$nasabah = mysqli_fetch_assoc($query_nasabah);

if (!$nasabah) {
    die("Data nasabah tidak ditemukan.");
}

/* Total setoran */
$query_setor = mysqli_query(
    $koneksi,
    "SELECT 
        COALESCE(SUM(total), 0) AS total_setor,
        COALESCE(SUM(berat), 0) AS total_berat
     FROM transaksi
     WHERE id_nasabah = $id"
);

$data_setor = mysqli_fetch_assoc($query_setor);

$total_setor = $data_setor['total_setor'];
$total_berat = $data_setor['total_berat'];

/* Total penarikan */
$query_tarik = mysqli_query(
    $koneksi,
    "SELECT COALESCE(SUM(jumlah), 0) AS total_tarik
     FROM tarik_saldo
     WHERE id_nasabah = $id"
);

$data_tarik = mysqli_fetch_assoc($query_tarik);

$total_tarik = $data_tarik['total_tarik'];

/* Saldo */
$saldo = $total_setor - $total_tarik;

/* Riwayat transaksi */
$riwayat = mysqli_query(
    $koneksi,
    "SELECT 
        transaksi.*,
        sampah.nama_sampah
     FROM transaksi
     JOIN sampah
        ON transaksi.id_sampah = sampah.id_sampah
     WHERE transaksi.id_nasabah = $id
     ORDER BY transaksi.tanggal DESC, transaksi.id_transaksi DESC"
);

/* Riwayat penarikan */
$riwayat_tarik = mysqli_query(
    $koneksi,
    "SELECT *
     FROM tarik_saldo
     WHERE id_nasabah = $id
     ORDER BY tanggal DESC, id_tarik DESC"
);
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <title>Detail Nasabah</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 30px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .header h2 {
            margin-top: 0;
        }

        .info {
            line-height: 1.8;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .card h3 {
            margin-top: 0;
            font-size: 15px;
            color: #666;
        }

        .nominal {
            font-size: 24px;
            font-weight: bold;
        }

        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #f1f1f1;
        }

        .saldo {
            font-size: 28px;
            font-weight: bold;
        }

        .kembali {
            display: inline-block;
            background: #6c757d;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }

    </style>

<link rel="stylesheet" href="assets/admin-theme.css">
</head>

<body>
<?php include __DIR__ . '/admin_sidebar.php'; ?>
<div class="main">
<?php $tb_judul = 'Detail Nasabah'; $tb_sub = 'Ringkasan, riwayat setoran, dan penarikan nasabah'; include __DIR__ . '/admin_topbar.php'; ?>
<section class="content">

<div class="container">

    <div class="header">

        <h2>👤 Detail Nasabah</h2>

        <div class="info">

            <strong>Nama:</strong>
            <?= htmlspecialchars($nasabah['nama']); ?>

            <br>

            <strong>Alamat:</strong>
            <?= htmlspecialchars($nasabah['alamat']); ?>

            <br>

            <strong>No. HP:</strong>
            <?= htmlspecialchars($nasabah['no_hp']); ?>

            <br>

            <strong>Tanggal Daftar:</strong>
            <?= htmlspecialchars($nasabah['tanggal_daftar']); ?>

        </div>

        <a href="nasabah.php" class="kembali">
            ← Kembali
        </a>

    </div>


    <div class="cards">

        <div class="card">

            <h3>Total Setoran</h3>

            <div class="nominal">
                Rp<?= number_format($total_setor, 0, ',', '.'); ?>
            </div>

        </div>


        <div class="card">

            <h3>Total Penarikan</h3>

            <div class="nominal">
                Rp<?= number_format($total_tarik, 0, ',', '.'); ?>
            </div>

        </div>


        <div class="card">

            <h3>Saldo Saat Ini</h3>

            <div class="saldo">
                Rp<?= number_format($saldo, 0, ',', '.'); ?>
            </div>

        </div>

    </div>


    <div class="section">

        <h2>📊 Ringkasan Sampah</h2>

        <p>
            Total berat sampah:
            <strong>
                <?= number_format($total_berat, 2, ',', '.'); ?> kg
            </strong>
        </p>

    </div>


    <div class="section">

        <h2>📥 Riwayat Setoran</h2>

        <table>

            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jenis Sampah</th>
                <th>Berat</th>
                <th>Harga</th>
                <th>Total</th>
            </tr>

            <?php
            $no = 1;

            if (mysqli_num_rows($riwayat) > 0):
            ?>

                <?php while ($row = mysqli_fetch_assoc($riwayat)): ?>

                    <tr>

                        <td><?= $no++; ?></td>

                        <td>
                            <?= htmlspecialchars($row['tanggal']); ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['nama_sampah']); ?>
                        </td>

                        <td>
                            <?= number_format($row['berat'], 2, ',', '.'); ?> kg
                        </td>

                        <td>
                            Rp<?= number_format($row['harga'], 0, ',', '.'); ?>
                        </td>

                        <td>
                            <strong>
                                Rp<?= number_format($row['total'], 0, ',', '.'); ?>
                            </strong>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6">
                        Belum ada transaksi setoran.
                    </td>
                </tr>

            <?php endif; ?>

        </table>

    </div>


    <div class="section">

        <h2>💸 Riwayat Penarikan</h2>

        <table>

            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jumlah</th>
                <th>Keterangan</th>
            </tr>

            <?php
            $no = 1;

            if (mysqli_num_rows($riwayat_tarik) > 0):
            ?>

                <?php while ($row = mysqli_fetch_assoc($riwayat_tarik)): ?>

                    <tr>

                        <td><?= $no++; ?></td>

                        <td>
                            <?= htmlspecialchars($row['tanggal']); ?>
                        </td>

                        <td>
                            Rp<?= number_format($row['jumlah'], 0, ',', '.'); ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['keterangan']); ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="4">
                        Belum ada penarikan.
                    </td>
                </tr>

            <?php endif; ?>

        </table>

    </div>

</div>

</section>
</div>
</body>
</html>