<?php
/* Topbar siswa bersama (hamburger, pencarian, lonceng, profil). */
if (!isset($koneksi)) {
    require_once __DIR__ . '/koneksi.php';
}
$tb_nama = $nama_siswa ?? ($_SESSION['nama_user'] ?? $_SESSION['nama'] ?? 'Siswa');

/* Lonceng: jumlah setoran milik siswa ini yang masih menunggu verifikasi */
$tb_pending = 0;
$tb_id = (int) ($_SESSION['id_nasabah'] ?? 0);
if ($tb_id > 0) {
    $tb_r = @mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi WHERE id_nasabah = $tb_id AND status = 'pending'");
    if ($tb_r) {
        $tb_d = mysqli_fetch_assoc($tb_r);
        $tb_pending = (int) ($tb_d['total'] ?? 0);
    }
}
?>
<header class="sw-top">

    <button type="button" class="sw-burger" aria-label="Buka atau tutup menu" onclick="swMenu()">
        <span></span><span></span><span></span>
    </button>

    <label class="sw-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
        <input type="search" id="swCari" placeholder="Cari data, transaksi, atau informasi..." autocomplete="off">
    </label>

    <a class="sw-bell" href="setor_sampah_siswa.php" title="<?= $tb_pending ?> setoran menunggu verifikasi">
        <svg viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0-6 6v3.6L4.2 16a1 1 0 0 0 .9 1.5h13.8a1 1 0 0 0 .9-1.5L18 12.6V9a6 6 0 0 0-6-6zm-2 16a2 2 0 0 0 4 0z"/></svg>
        <?php if ($tb_pending > 0): ?><b><?= $tb_pending > 99 ? '99+' : $tb_pending ?></b><?php endif; ?>
    </a>

    <div class="sw-user">
        <img src="assets/sw-ava.png" alt="" class="sw-ava">
        <div>
            <strong><?= htmlspecialchars($tb_nama, ENT_QUOTES, 'UTF-8') ?></strong>
            <small>Siswa</small>
        </div>
    </div>

</header>
