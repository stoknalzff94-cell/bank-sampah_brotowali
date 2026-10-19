<?php
/* Sidebar admin bersama. Menu aktif ditentukan otomatis dari nama file. */
$hal_aktif = basename($_SERVER['SCRIPT_NAME'] ?? '');
$nama_sb   = $_SESSION['nama_user'] ?? $_SESSION['nama'] ?? 'Admin';

$menu_admin = [
    ['dashboard.php',            '🏠', 'Dashboard',            ['dashboard.php']],
    ['nasabah.php',              '👤', 'Data Nasabah',         ['nasabah.php', 'edit_nasabah.php', 'detail_nasabah.php']],
    ['sampah.php',               '♻️', 'Data Sampah',          ['sampah.php', 'edit_sampah.php']],
    ['transaksi.php',            '🧾', 'Transaksi',            ['transaksi.php', 'edit_transaksi.php']],
    ['tarik_saldo.php',          '💰', 'Tarik Saldo',          ['tarik_saldo.php']],
    ['verifikasi_transaksi.php', '✅', 'Verifikasi Setoran',   ['verifikasi_transaksi.php']],
    ['verifikasi_sampah.php',    '🔎', 'Verifikasi Sampah',    ['verifikasi_sampah.php']],
    ['verifikasi_pembelian.php', '🛒', 'Verifikasi Pembelian', ['verifikasi_pembelian.php']],
    ['akun.php',                 '🚚', 'Kelola Akun',          ['akun.php']],
    ['laporan.php',              '📊', 'Laporan',              ['laporan.php', 'cetak_laporan.php']],
];
?>
<aside class="sidebar bs-side" id="bsSide">

    <div class="bs-brand">
        <div class="bs-brand-icon">♻</div>
        <div>
            <h2>BANK SAMPAH</h2>
            <p>Jaga Lingkungan, Raih Manfaat</p>
        </div>
    </div>

    <nav class="bs-menu">
        <?php foreach ($menu_admin as $m): ?>
            <a href="<?= $m[0] ?>" class="<?= in_array($hal_aktif, $m[3], true) ? 'active' : '' ?>">
                <span class="bs-mi"><?= $m[1] ?></span>
                <span><?= $m[2] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="bs-side-bottom">
        <div class="bs-side-user">
            <div class="bs-avatar">👤</div>
            <div>
                <strong><?= htmlspecialchars($nama_sb, ENT_QUOTES, 'UTF-8') ?></strong>
                <small>Administrator</small>
            </div>
        </div>
        <a href="logout.php" class="bs-logout">↪ Keluar dari sistem</a>
        <div class="bs-side-deco">
            <span>Sampah Hari Ini<br>Berkah Esok Nanti</span>
        </div>
    </div>

</aside>
<div class="bs-overlay" onclick="document.body.classList.remove('menu-terbuka')"></div>
