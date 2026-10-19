<?php
session_start();

if (!isset($_SESSION['id_user']) || !in_array($_SESSION['role'] ?? '', ['admin'], true)) {
    header("Location: login.php");
    exit;
}

include "koneksi.php";

/* Statistik */
$q_nasabah = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS jumlah FROM nasabah"
);
$jumlah_nasabah = mysqli_fetch_assoc($q_nasabah)['jumlah'];

$q_transaksi = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS jumlah FROM transaksi"
);
$jumlah_transaksi = mysqli_fetch_assoc($q_transaksi)['jumlah'];

$q_berat = mysqli_query(
    $koneksi,
    "SELECT COALESCE(SUM(berat), 0) AS total FROM transaksi"
);
$total_berat = mysqli_fetch_assoc($q_berat)['total'];

$q_setor = mysqli_query(
    $koneksi,
    "SELECT COALESCE(SUM(total), 0) AS total FROM transaksi"
);
$total_setor = mysqli_fetch_assoc($q_setor)['total'];

$q_tarik = mysqli_query(
    $koneksi,
    "SELECT COALESCE(SUM(jumlah), 0) AS total FROM tarik_saldo"
);
$total_tarik = mysqli_fetch_assoc($q_tarik)['total'];

$saldo = $total_setor - $total_tarik;

/* Data transaksi */
$transaksi = mysqli_query(
    $koneksi,
    "SELECT
        transaksi.*,
        nasabah.nama,
        sampah.nama_sampah
     FROM transaksi
     JOIN nasabah
        ON transaksi.id_nasabah = nasabah.id_nasabah
     JOIN sampah
        ON transaksi.id_sampah = sampah.id_sampah
     ORDER BY transaksi.tanggal DESC"
);
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <title>Cetak Laporan Bank Sampah</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            padding: 30px;
            color: #000;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
        }

        .header p {
            margin: 5px 0;
        }

        .info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 25px;
        }

        .box {
            border: 1px solid #000;
            padding: 15px;
        }

        .box strong {
            display: block;
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 8px;
        }

        th {
            text-align: center;
        }

        .kanan {
            text-align: right;
        }

        .total {
            font-weight: bold;
        }

        .tombol {
            margin-bottom: 20px;
        }

        button,
        a {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: #28a745;
            color: white;
        }

        .kembali {
            background: #6c757d;
            color: white;
        }

        .tanggal-cetak {
            text-align: right;
            margin-top: 30px;
        }

        @media print {

            .tombol {
                display: none;
            }

            body {
                padding: 0;
            }

        }

    </style>

</head>

<body>

<div class="tombol">

    <button onclick="window.print()">
        🖨️ Cetak Laporan
    </button>

    <a href="laporan.php" class="kembali">
        ← Kembali
    </a>

</div>


<div class="header">

    <h1>BANK SAMPAH ATAYA</h1>

    <p>Laporan Transaksi Bank Sampah</p>

    <p>
        Dicetak pada:
        <?= date('d-m-Y'); ?>
    </p>

</div>


<div class="info">

    <div class="box">

        <strong>Total Nasabah</strong>

        <?= $jumlah_nasabah; ?> orang

    </div>


    <div class="box">

        <strong>Total Transaksi</strong>

        <?= $jumlah_transaksi; ?> transaksi

    </div>


    <div class="box">

        <strong>Total Berat Sampah</strong>

        <?= number_format($total_berat, 2, ',', '.'); ?> kg

    </div>


    <div class="box">

        <strong>Total Setoran</strong>

        Rp<?= number_format($total_setor, 0, ',', '.'); ?>

    </div>


    <div class="box">

        <strong>Total Penarikan</strong>

        Rp<?= number_format($total_tarik, 0, ',', '.'); ?>

    </div>


    <div class="box">

        <strong>Saldo Akhir</strong>

        Rp<?= number_format($saldo, 0, ',', '.'); ?>

    </div>

</div>


<h3>Riwayat Transaksi Setoran</h3>

<table>

    <tr>

        <th>No</th>
        <th>Tanggal</th>
        <th>Nasabah</th>
        <th>Jenis Sampah</th>
        <th>Berat</th>
        <th>Harga</th>
        <th>Total</th>

    </tr>

    <?php

    $no = 1;

    if (mysqli_num_rows($transaksi) > 0):

    ?>

        <?php while ($row = mysqli_fetch_assoc($transaksi)): ?>

            <tr>

                <td><?= $no++; ?></td>

                <td>
                    <?= htmlspecialchars($row['tanggal']); ?>
                </td>

                <td>
                    <?= htmlspecialchars($row['nama']); ?>
                </td>

                <td>
                    <?= htmlspecialchars($row['nama_sampah']); ?>
                </td>

                <td class="kanan">
                    <?= number_format($row['berat'], 2, ',', '.'); ?> kg
                </td>

                <td class="kanan">
                    Rp<?= number_format($row['harga'], 0, ',', '.'); ?>
                </td>

                <td class="kanan total">
                    Rp<?= number_format($row['total'], 0, ',', '.'); ?>
                </td>

            </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>

            <td colspan="7" style="text-align:center;">
                Belum ada transaksi.
            </td>

        </tr>

    <?php endif; ?>

</table>


<div class="tanggal-cetak">

    Magetan, <?= date('d-m-Y'); ?>

    <br><br><br>

    <strong>Administrator</strong>

</div>


</body>

</html>