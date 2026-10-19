<?php
require_once __DIR__ . '/pengepul_layout.php';
$id_user = wajib_pengepul();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['beli'])) {
    $id_sampah = (int) ($_POST['id_sampah'] ?? 0);
    $berat = round((float) str_replace(',', '.', $_POST['berat'] ?? '0'), 2);

    if (!csrf_ok()) {
        flash('Sesi tidak valid. Muat ulang halaman lalu coba lagi.', 'err');
    } elseif ($id_sampah <= 0 || $berat <= 0) {
        flash('Pilih jenis sampah dan isi berat yang valid.', 'err');
    } else {
        mysqli_begin_transaction($koneksi);
        try {
            /* Kunci jenis sampah supaya dua pembelian tidak menghabiskan stok yang sama */
            $stmt = mysqli_prepare($koneksi,
                "SELECT id_sampah FROM sampah WHERE id_sampah = ? AND status = 'disetujui' FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $id_sampah);
            mysqli_stmt_execute($stmt);
            $ada = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (!$ada) {
                throw new Exception('Jenis sampah tidak ditemukan atau belum disetujui admin.');
            }

            $k = ambil_katalog($koneksi, $id_sampah)[0] ?? null;
            if (!$k || $berat > $k['stok']) {
                throw new Exception('Stok tidak cukup. Tersedia: ' . angka($k['stok'] ?? 0) . ' ' . ($k['satuan'] ?? 'kg') . '.');
            }

            $harga = (float) $k['harga'];
            $total = $berat * $harga;
            $stmt = mysqli_prepare($koneksi,
                "INSERT INTO pembelian_pengepul (id_user, id_sampah, tanggal, berat, harga, total, status)
                 VALUES (?, ?, NOW(), ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, "iiddd", $id_user, $id_sampah, $berat, $harga, $total);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Pengajuan gagal disimpan. Coba lagi.');
            }
            mysqli_stmt_close($stmt);
            mysqli_commit($koneksi);
            flash('Pengajuan ' . angka($berat) . ' ' . $k['satuan'] . ' ' . $k['nama_sampah'] . ' terkirim. Menunggu persetujuan admin.');
        } catch (Throwable $ex) {
            mysqli_rollback($koneksi);
            flash($ex->getMessage(), 'err');
        }
    }
    header("Location: katalog_pengepul.php");
    exit;
}

$katalog = ambil_katalog($koneksi);
$stok_max = max(1, ...array_merge([1], array_column($katalog, 'stok')));

pengepul_head('Beli sampah', 'beli');
?>
<h1>Beli sampah</h1>
<p class="sub">Pilih jenis sampah, isi berat, lalu ajukan ke admin. Stok yang sudah kamu ajukan ikut mengurangi stok tersedia.</p>

<p class="legend">Color Timer: biru aman, kuning mulai turun, merah berkedip hampir habis (di bawah 5 kg).</p>
<section class="panel">
  <?php if (!$katalog): ?>
    <p class="kosong">Belum ada jenis sampah yang disetujui admin.</p>
  <?php else: ?>
  <div class="wrap"><table class="tbl">
    <tr><th>Jenis sampah</th><th class="r">Harga</th><th>Stok tersedia</th><th>Ajukan pembelian</th></tr>
    <?php foreach ($katalog as $k): ?>
      <tr>
        <td><strong><?= e($k['nama_sampah']) ?></strong></td>
        <td class="r"><?= rupiah($k['harga']) ?> / <?= e($k['satuan']) ?></td>
        <td>
          <?= angka($k['stok']) ?> <?= e($k['satuan']) ?>
          <?= color_timer($k['stok'], $stok_max) ?>
        </td>
        <td>
          <?php if ($k['stok'] <= 0): ?>
            <span style="color:var(--muted)">Stok habis</span>
          <?php else: ?>
            <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id_sampah" value="<?= (int) $k['id_sampah'] ?>">
              <input type="number" name="berat" step="0.01" min="0.01" max="<?= e($k['stok']) ?>"
                     placeholder="Berat (<?= e($k['satuan']) ?>)" aria-label="Berat <?= e($k['nama_sampah']) ?>" required style="width:130px">
              <button class="btn" type="submit" name="beli" value="1">Ajukan</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</section>
<?php pengepul_foot();
