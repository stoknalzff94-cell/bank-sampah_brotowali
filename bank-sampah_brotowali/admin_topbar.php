<?php
/* Topbar admin bersama.
   Halaman pemanggil boleh mengisi $tb_judul dan $tb_sub sebelum include. */
if (!isset($koneksi)) {
    require_once __DIR__ . '/koneksi.php';
}

$tb_judul = $tb_judul ?? 'Dashboard';
$tb_sub   = $tb_sub   ?? '';
$tb_nama  = $_SESSION['nama_user'] ?? $_SESSION['nama'] ?? 'Admin';

/* Jumlah item yang menunggu verifikasi (untuk lonceng) */
$tb_hitung = function ($sql) use ($koneksi) {
    $r = @mysqli_query($koneksi, $sql);
    if (!$r) { return 0; }
    $d = mysqli_fetch_assoc($r);
    return (int) ($d['total'] ?? 0);
};
$tb_p_setoran   = $tb_hitung("SELECT COUNT(*) AS total FROM transaksi WHERE status = 'pending'");
$tb_p_sampah    = $tb_hitung("SELECT COUNT(*) AS total FROM sampah WHERE status = 'pending'");
$tb_p_pembelian = $tb_hitung("SELECT COUNT(*) AS total FROM pembelian_pengepul WHERE status = 'pending'");
$tb_pending     = $tb_p_setoran + $tb_p_sampah + $tb_p_pembelian;
?>
<header class="topbar bs-top">

    <button type="button" class="bs-burger" aria-label="Menu"
            onclick="document.body.classList.toggle('menu-terbuka'); document.body.classList.toggle('menu-tutup');">☰</button>

    <div class="bs-top-title">
        <h3><?= $tb_judul ?></h3>
        <?php if ($tb_sub !== ''): ?><p><?= $tb_sub ?></p><?php endif; ?>
    </div>

    <form class="bs-search" action="nasabah.php" method="get">
        <span>🔍</span>
        <input type="text" name="q" placeholder="Cari nasabah...">
    </form>

    <details class="bs-bell">
        <summary aria-label="Notifikasi">🔔<?php if ($tb_pending > 0): ?><b><?= $tb_pending ?></b><?php endif; ?></summary>
        <div class="bs-pop">
            <strong>Menunggu verifikasi</strong>
            <a href="verifikasi_transaksi.php">Setoran <em><?= $tb_p_setoran ?></em></a>
            <a href="verifikasi_sampah.php">Jenis sampah <em><?= $tb_p_sampah ?></em></a>
            <a href="verifikasi_pembelian.php">Pembelian pengepul <em><?= $tb_p_pembelian ?></em></a>
        </div>
    </details>

    <details class="bs-me">
        <summary>
            <span class="bs-avatar">👤</span>
            <span class="bs-me-name"><?= htmlspecialchars($tb_nama, ENT_QUOTES, 'UTF-8') ?><small>Administrator</small></span>
            <span>▾</span>
        </summary>
        <div class="bs-pop">
            <a href="akun.php">Kelola Akun</a>
            <a href="logout.php">Keluar</a>
        </div>
    </details>

</header>
