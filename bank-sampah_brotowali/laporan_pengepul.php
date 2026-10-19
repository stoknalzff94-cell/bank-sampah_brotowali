<?php
require_once __DIR__ . '/pengepul_layout.php';
$id_user = wajib_pengepul();

function tanggal_valid($t, $default)
{
    return (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $t) && strtotime($t) !== false) ? $t : $default;
}
$dari   = tanggal_valid($_GET['dari'] ?? '', date('Y-m-01'));
$sampai = tanggal_valid($_GET['sampai'] ?? '', date('Y-m-d'));
if ($dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}

/* Hanya pembelian yang sudah disetujui admin yang masuk laporan */
$stmt = mysqli_prepare($koneksi,
    "SELECT s.nama_sampah, s.satuan, COUNT(*) AS jml, SUM(p.berat) AS kg, SUM(p.total) AS rp
     FROM pembelian_pengepul p INNER JOIN sampah s ON s.id_sampah = p.id_sampah
     WHERE p.id_user = ? AND p.status = 'disetujui' AND DATE(p.tanggal) BETWEEN ? AND ?
     GROUP BY s.id_sampah, s.nama_sampah, s.satuan ORDER BY rp DESC");
mysqli_stmt_bind_param($stmt, "iss", $id_user, $dari, $sampai);
mysqli_stmt_execute($stmt);
$rekap = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$total_kg = array_sum(array_column($rekap, 'kg'));
$total_rp = array_sum(array_column($rekap, 'rp'));
$total_trx = array_sum(array_column($rekap, 'jml'));

pengepul_head('Laporan', 'laporan');
?>
<h1>Laporan pembelian</h1>
<p class="sub">Periode <?= e(date('d/m/Y', strtotime($dari))) ?> sampai <?= e(date('d/m/Y', strtotime($sampai))) ?>. Hanya pembelian yang sudah disetujui admin.</p>

<form class="filter noprint" method="get">
  <div><label for="dari">Dari</label><input type="date" id="dari" name="dari" value="<?= e($dari) ?>"></div>
  <div><label for="sampai">Sampai</label><input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>"></div>
  <button class="btn" type="submit">Tampilkan</button>
  <button class="btn alt" type="button" onclick="window.print()">Cetak</button>
</form>

<div class="grid">
  <div class="stat"><b><?= (int) $total_trx ?></b><span>Pembelian disetujui</span></div>
  <div class="stat"><b><?= angka($total_kg) ?> kg</b><span>Total berat</span></div>
  <div class="stat"><b><?= rupiah($total_rp) ?></b><span>Total pembelian</span></div>
</div>

<section class="panel">
  <h2>Rekap per jenis sampah</h2>
  <?php if (!$rekap): ?>
    <p class="kosong">Belum ada pembelian disetujui pada periode ini.</p>
  <?php else: ?>
  <div class="wrap"><table class="tbl">
    <tr><th>Jenis sampah</th><th class="r">Pembelian</th><th class="r">Berat</th><th class="r">Total</th></tr>
    <?php foreach ($rekap as $r): ?>
      <tr>
        <td><?= e($r['nama_sampah']) ?></td>
        <td class="r"><?= (int) $r['jml'] ?>x</td>
        <td class="r"><?= angka($r['kg']) ?> <?= e($r['satuan']) ?></td>
        <td class="r"><strong><?= rupiah($r['rp']) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    <tr><td><strong>Jumlah</strong></td><td class="r"><strong><?= (int) $total_trx ?>x</strong></td><td class="r"><strong><?= angka($total_kg) ?></strong></td><td class="r"><strong><?= rupiah($total_rp) ?></strong></td></tr>
  </table></div>
  <?php endif; ?>
</section>
<?php pengepul_foot();
