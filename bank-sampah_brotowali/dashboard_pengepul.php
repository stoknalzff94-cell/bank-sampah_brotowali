<?php
require_once __DIR__ . '/pengepul_layout.php';
$id_user = wajib_pengepul();

/* Ringkasan pembelian milik pengepul ini, per status */
$ringkas = ['pending' => [0, 0, 0], 'disetujui' => [0, 0, 0], 'ditolak' => [0, 0, 0]];
$stmt = mysqli_prepare($koneksi,
    "SELECT status, COUNT(*) AS jml, COALESCE(SUM(berat),0) AS kg, COALESCE(SUM(total),0) AS rp
     FROM pembelian_pengepul WHERE id_user = ? GROUP BY status");
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) {
    if (isset($ringkas[$r['status']])) {
        $ringkas[$r['status']] = [(int)$r['jml'], (float)$r['kg'], (float)$r['rp']];
    }
}
mysqli_stmt_close($stmt);

$katalog = ambil_katalog($koneksi);
$stok_total = array_sum(array_column($katalog, 'stok'));
$stok_max = max(1, ...array_merge([1], array_column($katalog, 'stok')));

$stmt = mysqli_prepare($koneksi,
    "SELECT p.id_pembelian, p.tanggal, p.berat, p.total, p.status, s.nama_sampah, s.satuan
     FROM pembelian_pengepul p INNER JOIN sampah s ON s.id_sampah = p.id_sampah
     WHERE p.id_user = ? ORDER BY p.id_pembelian DESC LIMIT 5");
mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$terbaru = mysqli_stmt_get_result($stmt);

pengepul_head('Dashboard', 'beranda', true);
?>
<h1>Siap bertugas, <?= e($_SESSION['nama_user'] ?? 'Pengepul') ?></h1>
<p class="sub">Semua pembelian sampah diajukan ke admin dan baru sah setelah admin menyetujui.</p>

<div class="grid">
  <div class="stat"><div class="ico" aria-hidden="true">♻</div><div><span>Stok tersedia</span><b><?= angka($stok_total) ?> kg</b></div></div>
  <div class="stat"><div class="ico" aria-hidden="true">⏳</div><div><span>Menunggu admin</span><b><?= $ringkas['pending'][0] ?> pengajuan</b></div></div>
  <div class="stat"><div class="ico" aria-hidden="true">✓</div><div><span>Sampah sudah dibeli</span><b><?= angka($ringkas['disetujui'][1]) ?> kg</b></div></div>
  <div class="stat"><div class="ico" aria-hidden="true">Rp</div><div><span>Total pembelian disetujui</span><b><?= rupiah($ringkas['disetujui'][2]) ?></b></div></div>
</div>

<section class="panel">
  <h2>Stok tersedia</h2>
  <p class="legend">Color Timer: biru aman, kuning mulai turun, merah berkedip hampir habis (di bawah 5 kg).</p>
  <?php if (!$katalog): ?>
    <p class="kosong">Belum ada jenis sampah yang disetujui admin.</p>
  <?php else: foreach ($katalog as $k): ?>
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;gap:8px">
        <span><?= e($k['nama_sampah']) ?></span>
        <strong><?= angka($k['stok']) ?> <?= e($k['satuan']) ?></strong>
      </div>
      <?= color_timer($k['stok'], $stok_max) ?>
    </div>
  <?php endforeach; endif; ?>
  <div class="lihat" style="justify-content:flex-start"><a class="btn" href="katalog_pengepul.php">Beli sampah</a></div>
</section>

<section class="panel">
  <h2>Pembelian terbaru</h2>
  <?php if (mysqli_num_rows($terbaru) === 0): ?>
    <p class="kosong">Belum ada pembelian. Mulai dari menu Beli sampah.</p>
  <?php else: ?>
    <div class="wrap"><table class="tbl">
      <tr><th>Tanggal</th><th>Jenis sampah</th><th class="r">Berat</th><th class="r">Total</th><th>Status</th></tr>
      <?php while ($p = mysqli_fetch_assoc($terbaru)): ?>
        <tr>
          <td><?= e(date('d/m/Y', strtotime($p['tanggal']))) ?></td>
          <td><?= e($p['nama_sampah']) ?></td>
          <td class="r"><?= angka($p['berat']) ?> <?= e($p['satuan']) ?></td>
          <td class="r"><?= rupiah($p['total']) ?></td>
          <td><?= badge($p['status']) ?></td>
        </tr>
      <?php endwhile; ?>
    </table></div>
  <?php endif; ?>
  <div class="lihat"><a class="btn" href="riwayat_pembelian.php">Lihat semua &rarr;</a></div>
</section>
<?php pengepul_foot(true);
