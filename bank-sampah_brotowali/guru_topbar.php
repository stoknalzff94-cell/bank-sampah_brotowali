<?php
/* Topbar guru bersama (hamburger, pencarian, lonceng, profil).
   Halaman pemanggil tidak perlu mengisi apa pun. */
if (!isset($koneksi)) {
    require_once __DIR__ . '/koneksi.php';
}
$tb_nama = $nama_guru ?? ($_SESSION['nama_user'] ?? $_SESSION['nama'] ?? 'Guru');

$tb_pending = 0;
$tb_r = @mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM transaksi WHERE status = 'pending'");
if ($tb_r) {
    $tb_d = mysqli_fetch_assoc($tb_r);
    $tb_pending = (int) ($tb_d['total'] ?? 0);
}
?>
<header class="gs-top">

    <button type="button" class="gs-burger" aria-label="Buka atau tutup menu"
            onclick="gsMenu()">
        <span></span><span></span><span></span>
    </button>

    <label class="gs-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
        <input type="search" id="gsCari" placeholder="Cari data di halaman ini..." autocomplete="off">
    </label>

    <a class="gs-bell" href="laporan_guru.php" title="<?= $tb_pending ?> transaksi menunggu verifikasi">
        <svg viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0-6 6v3.6L4.2 16a1 1 0 0 0 .9 1.5h13.8a1 1 0 0 0 .9-1.5L18 12.600V9a6 6 0 0 0-6-6zm-2 16a2 2 0 0 0 4 0z"/></svg>
        <?php if ($tb_pending > 0): ?><b><?= $tb_pending > 99 ? '99+' : $tb_pending ?></b><?php endif; ?>
    </a>

    <div class="gs-user">
        <div class="gs-avatar"><svg viewBox="0 0 24 24"><circle cx="12" cy="8.5" r="4"/><path d="M4 21c0-4.2 3.6-7 8-7s8 2.8 8 7z"/></svg></div>
        <div>
            <strong><?= htmlspecialchars($tb_nama, ENT_QUOTES, 'UTF-8') ?></strong>
            <small>Role Guru</small>
        </div>
    </div>

</header>
