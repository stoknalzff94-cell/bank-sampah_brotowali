<?php
session_start();
include "koneksi.php";

mysqli_set_charset($koneksi, "utf8mb4");

/* =====================================
   CEK LOGIN ADMIN
===================================== */
if (
    !isset($_SESSION['id_user']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header("Location: login.php");
    exit;
}

/* =====================================
   HELPER
===================================== */
function e($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function rupiah($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = "";
$tipe_pesan = "success";

/* =====================================
   PROSES VERIFIKASI PEMBELIAN
===================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verifikasi'])) {
    $token = $_POST['csrf_token'] ?? '';
    $id_pembelian = (int)($_POST['id_pembelian'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $pesan = "Token keamanan tidak valid. Muat ulang halaman.";
        $tipe_pesan = "error";
    } elseif ($id_pembelian <= 0 || !in_array($aksi, ['disetujui', 'ditolak'], true)) {
        $pesan = "Data verifikasi tidak valid.";
        $tipe_pesan = "error";
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            /* Ambil pengajuan dan kunci baris pembelian */
            $stmt = mysqli_prepare(
                $koneksi,
                "SELECT id_pembelian, id_sampah, berat, status
                 FROM pembelian_pengepul
                 WHERE id_pembelian = ?
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($stmt, "i", $id_pembelian);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $pembelian = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$pembelian) {
                throw new Exception("Pengajuan pembelian tidak ditemukan.");
            }

            if ($pembelian['status'] !== 'pending') {
                throw new Exception("Pengajuan ini sudah diverifikasi sebelumnya.");
            }

            if ($aksi === 'disetujui') {
                $id_sampah = (int)$pembelian['id_sampah'];
                $berat_beli = (float)$pembelian['berat'];

                /*
                 * Kunci jenis sampah agar proses persetujuan
                 * pembelian untuk jenis yang sama berjalan bergantian.
                 */
                $stmt = mysqli_prepare(
                    $koneksi,
                    "SELECT id_sampah, nama_sampah, status
                     FROM sampah
                     WHERE id_sampah = ?
                     FOR UPDATE"
                );

                mysqli_stmt_bind_param($stmt, "i", $id_sampah);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $sampah = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                if (!$sampah || $sampah['status'] !== 'disetujui') {
                    throw new Exception("Jenis sampah tidak ditemukan atau belum disetujui.");
                }

                /* Total setoran nasabah yang sudah disetujui */
                $stmt = mysqli_prepare(
                    $koneksi,
                    "SELECT COALESCE(SUM(berat), 0) AS total_stok
                     FROM transaksi
                     WHERE id_sampah = ?
                       AND status = 'disetujui'"
                );

                mysqli_stmt_bind_param($stmt, "i", $id_sampah);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $data_stok = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                $total_stok = (float)($data_stok['total_stok'] ?? 0);

                /*
                 * Pembelian lain yang masih pending atau disetujui
                 * tetap mengurangi stok. Pengajuan yang sedang
                 * diverifikasi dikecualikan dari perhitungan.
                 */
                $stmt = mysqli_prepare(
                    $koneksi,
                    "SELECT COALESCE(SUM(berat), 0) AS total_pembelian
                     FROM pembelian_pengepul
                     WHERE id_sampah = ?
                       AND status IN ('pending', 'disetujui')
                       AND id_pembelian <> ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "ii",
                    $id_sampah,
                    $id_pembelian
                );

                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $data_pembelian = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                $total_pembelian = (float)($data_pembelian['total_pembelian'] ?? 0);
                $stok_tersedia = $total_stok - $total_pembelian;

                if ($berat_beli > $stok_tersedia) {
                    throw new Exception(
                        "Stok tidak mencukupi untuk menyetujui pembelian ini. " .
                        "Stok yang tersedia saat ini: " .
                        number_format(max(0, $stok_tersedia), 2, ',', '.') . " kg."
                    );
                }
            }

            /* Perbarui status pengajuan */
            $stmt = mysqli_prepare(
                $koneksi,
                "UPDATE pembelian_pengepul
                 SET status = ?
                 WHERE id_pembelian = ?
                   AND status = 'pending'"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $aksi,
                $id_pembelian
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Gagal memperbarui status pembelian.");
            }

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Status pembelian tidak berubah. Coba muat ulang.");
            }

            mysqli_stmt_close($stmt);
            mysqli_commit($koneksi);

            $pesan = $aksi === 'disetujui'
                ? "Pembelian berhasil disetujui."
                : "Pengajuan pembelian berhasil ditolak.";

        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = $e->getMessage();
            $tipe_pesan = "error";
        }
    }
}

/* =====================================
   AMBIL DATA PEMBELIAN
===================================== */
$sql = "
    SELECT
        p.id_pembelian,
        p.id_user,
        p.id_sampah,
        p.tanggal,
        p.berat,
        p.harga,
        p.total,
        p.status,
        s.nama_sampah,
        s.satuan
    FROM pembelian_pengepul p
    INNER JOIN sampah s
        ON s.id_sampah = p.id_sampah
    ORDER BY
        CASE
            WHEN p.status = 'pending' THEN 0
            ELSE 1
        END,
        p.id_pembelian DESC
";

$result = mysqli_query($koneksi, $sql);

if (!$result) {
    die("Gagal mengambil data pembelian: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Verifikasi Pembelian Pengepul - Bank Sampah</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #07131c;
    color: #eef8ff;
}

header {
    padding: 24px 5%;
    background: #0b202b;
    border-bottom: 1px solid #1d4350;
}

header h1 {
    margin: 0 0 8px;
    font-size: 25px;
}

header p {
    margin: 0;
    color: #9ab8c5;
}

main {
    width: 92%;
    max-width: 1500px;
    margin: 30px auto;
}

.page-heading {
    margin-bottom: 22px;
}

.page-heading h2 {
    margin: 0 0 8px;
}

.page-heading p {
    margin: 0;
    color: #9ab8c5;
}

.alert {
    padding: 14px 17px;
    margin-bottom: 20px;
    border-radius: 10px;
    background: #103d31;
    border: 1px solid #1c8768;
}

.alert.error {
    background: #421f29;
    border-color: #a33c54;
}

.table-wrap {
    overflow-x: auto;
    background: #0d2531;
    border: 1px solid #1d4350;
    border-radius: 14px;
}

table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
}

th, td {
    padding: 15px 13px;
    text-align: left;
    border-bottom: 1px solid #1d4350;
}

th {
    background: #103542;
    color: #b9d3dd;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .5px;
}

td {
    font-size: 14px;
}

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    background: #5a4820;
    color: #ffe08a;
}

.badge.disetujui {
    background: #124a39;
    color: #8af0c9;
}

.badge.ditolak {
    background: #512632;
    color: #ffb1c0;
}

.actions {
    display: flex;
    gap: 8px;
}

button {
    border: 0;
    border-radius: 7px;
    padding: 9px 12px;
    font-weight: bold;
    cursor: pointer;
}

.btn-approve {
    background: #12b88b;
    color: #04231b;
}

.btn-reject {
    background: #572b38;
    color: #ffc2cd;
    border: 1px solid #91475a;
}

button:hover {
    filter: brightness(1.12);
}

.empty {
    padding: 25px;
    text-align: center;
    color: #9ab8c5;
}

.back-link {
    display: inline-block;
    margin-top: 20px;
    color: #64e4bd;
    text-decoration: none;
}

.back-link:hover {
    text-decoration: underline;
}

@media (max-width: 700px) {
    main {
        width: 94%;
    }

    header {
        padding: 20px 4%;
    }
}
</style>
<link rel="stylesheet" href="assets/admin-theme.css">
</head>

<body>

<?php include __DIR__ . '/admin_sidebar.php'; ?>
<div class="main">
<?php $tb_judul = 'Verifikasi Pembelian'; $tb_sub = 'Kelola pengajuan pembelian sampah dari pengepul'; include __DIR__ . '/admin_topbar.php'; ?>
<section class="content">

<main>

    <div class="page-heading">
        <h2>Daftar Pembelian Pengepul</h2>
        <p>Periksa jenis sampah, berat, dan total pembelian sebelum menyetujui.</p>
    </div>

    <?php if ($pesan !== ""): ?>
        <div class="alert <?= $tipe_pesan === 'error' ? 'error' : '' ?>">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID Pembelian</th>
                    <th>ID Pengepul</th>
                    <th>Tanggal</th>
                    <th>Jenis Sampah</th>
                    <th>Berat</th>
                    <th>Harga Satuan</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php $status = strtolower($row['status']); ?>

                    <tr>
                        <td>#<?= (int)$row['id_pembelian'] ?></td>
                        <td><?= (int)$row['id_user'] ?></td>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?>
                        </td>
                        <td><?= e($row['nama_sampah']) ?></td>
                        <td>
                            <?= number_format((float)$row['berat'], 2, ',', '.') ?>
                            <?= e($row['satuan']) ?>
                        </td>
                        <td><?= rupiah($row['harga']) ?></td>
                        <td><strong><?= rupiah($row['total']) ?></strong></td>
                        <td>
                            <span class="badge <?= e($status) ?>">
                                <?= e(ucfirst($status)) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($status === 'pending'): ?>
                                <div class="actions">
                                    <form method="POST"
                                          onsubmit="return confirm('Setujui pembelian ini?')">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="id_pembelian"
                                               value="<?= (int)$row['id_pembelian'] ?>">
                                        <button class="btn-approve"
                                                type="submit"
                                                name="verifikasi"
                                                value="1"
                                                onclick="this.form.aksi.value='disetujui'">
                                            Setujui
                                        </button>
                                        <input type="hidden" name="aksi" value="disetujui">
                                    </form>

                                    <form method="POST"
                                          onsubmit="return confirm('Tolak pembelian ini?')">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="id_pembelian"
                                               value="<?= (int)$row['id_pembelian'] ?>">
                                        <input type="hidden" name="aksi" value="ditolak">
                                        <button class="btn-reject"
                                                type="submit"
                                                name="verifikasi"
                                                value="1">
                                            Tolak
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span style="color:#8da8b3;">Sudah diproses</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="empty">
                        Belum ada pengajuan pembelian dari pengepul.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="dashboard.php" class="back-link">
        ← Kembali ke Dashboard Admin
    </a>

</main>
</section>
</div>

</body>
</html>