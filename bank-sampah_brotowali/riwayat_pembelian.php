<?php
require_once __DIR__ . '/pengepul_layout.php';
$id_user = wajib_pengepul();

/* Batalkan pengajuan yang masih menunggu admin */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batal'])) {
    $id = (int) ($_POST['id_pembelian'] ?? 0);
    if (!csrf_ok()) {
        flash('Sesi tidak valid. Muat ulang halaman lalu coba lagi.', 'err');
    } else {
        $stmt = mysqli_prepare($koneksi,
            "DELETE FROM pembelian_pengepul WHERE id_pembelian = ? AND id_user = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt, "ii", $id, $id_user);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) === 1) {
            flash('Pengajuan dibatalkan.');
        } else {
            flash('Pengajuan tidak bisa dibatalkan karena sudah diproses admin.', 'err');
        }
        mysqli_stmt_close($stmt);
    }
    header("Location: riwayat_pembelian.php");
    exit;
}

$filter = $_GET['status'] ?? '';
if (!in_array($filter, ['pending', 'disetujui', 'ditolak'], true)) {
    $filter = '';
}

$sql = "SELECT p.id_pembelian, p.tanggal, p.berat, p.harga, p.total, p.status, s.nama_sampah, s.satuan
        FROM pembelian_pengepul p INNER JOIN sampah s ON s.id_sampah = p.id_sampah
        WHERE p.id_user = ?" . ($filter !== '' ? " AND p.status = ?" : "") . "
        ORDER BY p.id_pembelian DESC";
$stmt = mysqli_prepare($koneksi, $sql);
if ($filter !== '') {
    mysqli_stmt_bind_param($stmt, "is", $id_user, $filter);
} else {
    mysqli_stmt_bind_param($stmt, "i", $id_user);
}
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

pengepul_head('Riwayat pembelian', 'riwayat');
?>
<h1>Riwayat pembelian</h1>
<p class="sub">Pengajuan yang masih menunggu admin masih bisa dibatalkan.</p>

<form class="filter noprint" method="get">
  <div>
    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="">Semua</option>
      <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Menunggu admin</option>
      <option value="disetujui" <?= $filter === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
      <option value="ditolak" <?= $filter === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
    </select>
  </div>
  <button class="btn" type="submit">Tampilkan</button>
</form>

<section class="panel">
  <?php if (mysqli_num_rows($hasil) === 0): ?>
    <p class="kosong">Belum ada pembelian<?= $filter !== '' ? ' dengan status ini' : '' ?>. <a href="katalog_pengepul.php">Beli sampah</a></p>
  <?php else: ?>
  <div class="wrap"><table class="tbl">
    <tr><th>Tanggal</th><th>Sampah</th><th class="r">Berat</th><th class="r">Harga</th><th class="r">Total</th><th>Status</th><th></th></tr>
    <?php while ($p = mysqli_fetch_assoc($hasil)): ?>
      <tr>
        <td><?= e(date('d/m/Y H:i', strtotime($p['tanggal']))) ?></td>
        <td><?= e($p['nama_sampah']) ?></td>
        <td class="r"><?= angka($p['berat']) ?> <?= e($p['satuan']) ?></td>
        <td class="r"><?= rupiah($p['harga']) ?></td>
        <td class="r"><strong><?= rupiah($p['total']) ?></strong></td>
        <td><?= badge($p['status']) ?></td>
        <td>
          <?php if ($p['status'] === 'pending'): ?>
            <form method="post" onsubmit="return confirm('Batalkan pengajuan ini?')">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id_pembelian" value="<?= (int) $p['id_pembelian'] ?>">
              <button class="btn bad" type="submit" name="batal" value="1">Batalkan</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endwhile; ?>
  </table></div>
  <?php endif; ?>
</section>
<?php pengepul_foot();
